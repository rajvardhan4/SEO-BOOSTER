<?php
/**
 * Core Pages Optimizer Module.
 *
 * Audits and optimizes existing static WordPress pages (Home, About, Contact, Service Pillars)
 * without breaking custom Divi page builder or Elementor designs:
 * - Deterministic Rank Math / Yoast postmeta calibration (Target score >= 80-95)
 * - Strict heading hierarchy validation (single H1, clean H2/H3)
 * - Missing image Alt attribute remediation
 * - 3 Optimization Modes (Mode A: Smart Sync, Mode B: Conditional <80, Mode C: Full Polish)
 * - 1-Click Rollback Snapshot safety
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Core_Pages extends SEOB_Module {

	public function id() {
		return 'core-pages';
	}

	public function name() {
		return __( 'Core Pages', 'seo-boster' );
	}

	public function menu_slug() {
		return 'seob-core-pages';
	}

	public function description() {
		return __( 'Audit and optimize your core static pages (Home, About, Services) to 85+ Rank Math score with 1-click rollback snapshots.', 'seo-boster' );
	}

	public function icon() {
		return 'dashicons-admin-page';
	}

	public function stats() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$optimized = 0;
		foreach ( $pages as $pid ) {
			if ( SEOB_Backup_Manager::has_snapshot( $pid ) || get_post_meta( $pid, '_seob_core_optimized', true ) ) {
				++$optimized;
			}
		}
		return array(
			__( 'total pages', 'seo-boster' )     => count( $pages ),
			__( 'optimized pages', 'seo-boster' ) => $optimized,
		);
	}

	public function register() {
		if ( is_admin() ) {
			add_action( 'admin_post_seob_core_optimize_single', array( $this, 'handle_optimize_single' ) );
			add_action( 'admin_post_seob_core_rollback_single', array( $this, 'handle_rollback_single' ) );
			add_action( 'admin_post_seob_core_bulk_optimize', array( $this, 'handle_bulk_optimize' ) );
			add_action( 'wp_ajax_seob_core_audit_page', array( $this, 'ajax_audit_page' ) );
		}
	}

	/**
	 * Single page optimize handler.
	 */
	public function handle_optimize_single() {
		SEOB_Security::verify( 'seob_core_optimize' );

		$post_id = absint( SEOB_Security::post_text( 'post_id' ) );
		$keyword = SEOB_Security::post_text( 'focus_keyword' );
		$mode    = SEOB_Security::post_text( 'opt_mode' ); // 'sync', 'conditional', 'full'

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'forbidden' ) ) );
		}

		$result = $this->optimize_page( $post_id, $keyword, $mode );
		if ( is_wp_error( $result ) ) {
			set_transient( 'seob_core_err_' . get_current_user_id(), $result->get_error_message(), 60 );
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'error' ) ) );
		}

		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'optimized' ) ) );
	}

	/**
	 * Single page rollback handler.
	 */
	public function handle_rollback_single() {
		SEOB_Security::verify( 'seob_core_rollback' );

		$post_id = absint( SEOB_Security::post_text( 'post_id' ) );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'forbidden' ) ) );
		}

		$res = SEOB_Backup_Manager::rollback( $post_id );
		if ( is_wp_error( $res ) ) {
			set_transient( 'seob_core_err_' . get_current_user_id(), $res->get_error_message(), 60 );
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'error' ) ) );
		}

		delete_post_meta( $post_id, '_seob_core_optimized' );
		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'rolled_back' ) ) );
	}

	/**
	 * Bulk optimize handler.
	 */
	public function handle_bulk_optimize() {
		SEOB_Security::verify( 'seob_core_bulk' );

		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) ) : array();
		$mode     = SEOB_Security::post_text( 'bulk_mode' );

		$count = 0;
		foreach ( $post_ids as $pid ) {
			if ( $pid && current_user_can( 'edit_post', $pid ) ) {
				$kw = SEOB_Security::post_text( 'kw_' . $pid );
				$r  = $this->optimize_page( $pid, $kw, $mode );
				if ( ! is_wp_error( $r ) ) {
					++$count;
				}
			}
		}

		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'bulk_done', 'count' => $count ) ) );
	}

	/**
	 * AJAX handler for live auditing of a page.
	 */
	public function ajax_audit_page() {
		check_ajax_referer( 'seob_core_audit', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'invalid_post' ) );
		}

		$evaluation = SEOB_RankMath_Bridge::evaluate_score( $post_id, $keyword );
		wp_send_json_success( $evaluation );
	}

	/**
	 * Optimize a single static page according to the selected mode.
	 *
	 * @param int    $post_id Target post ID.
	 * @param string $keyword Primary focus keyword.
	 * @param string $mode    'sync', 'conditional', or 'full'.
	 * @return bool|WP_Error
	 */
	public function optimize_page( $post_id, $keyword = '', $mode = 'conditional' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'seob_not_found', __( 'Page not found.', 'seo-boster' ) );
		}

		// 1. If keyword not provided, infer from post title or existing meta
		if ( empty( $keyword ) ) {
			$keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( empty( $keyword ) ) {
				$keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			}
			if ( empty( $keyword ) ) {
				$keyword = $post->post_title;
			}
		}

		// 2. Conditional check (Mode B: Skip if current score >= 80)
		if ( 'conditional' === $mode ) {
			$eval = SEOB_RankMath_Bridge::evaluate_score( $post_id, $keyword );
			if ( $eval['score'] >= 80 ) {
				return true; // Already good, non-destructive skip!
			}
		}

		// 3. Create 1-Click Rollback Snapshot before any changes
		SEOB_Backup_Manager::create_snapshot( $post_id );

		// 4. Build calibrated SEO Title and Description
		$title = SEOB_RankMath_Bridge::build_calibrated_title( $keyword, '', get_bloginfo( 'name' ) );
		$desc  = SEOB_RankMath_Bridge::build_calibrated_desc( $keyword, $post->post_title );

		// 5. Inject Postmeta
		SEOB_RankMath_Bridge::inject_postmeta( $post_id, $title, $desc, $keyword );

		// 6. Tier 1 Divi Blurb/Gallery Alt injection if Divi shortcodes are present in post_content
		if ( 'full' === $mode && ! empty( $post->post_content ) ) {
			$new_content = SEOB_Image_Optimizer::inject_divi_shortcodes( $post->post_content, $keyword . ' - ' . get_bloginfo( 'name' ) );
			if ( $new_content !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $new_content,
					)
				);
			}
		}

		update_post_meta( $post_id, '_seob_core_optimized', current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Main admin screen rendering.
	 */
	public function render_page() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}

		SEOB_Admin::page_start(
			__( 'Core Pages Optimizer', 'seo-boster' ),
			__( 'Audit and optimize your static site pages (Home, About, Services) to 85+ Rank Math score with 1-click rollback snapshots.', 'seo-boster' )
		);

		$notices = array(
			'optimized'   => array( 'success', __( 'Page successfully optimized with Rank Math 85+ calibration and safety snapshot.', 'seo-boster' ) ),
			'rolled_back' => array( 'success', __( 'Page successfully restored to previous snapshot.', 'seo-boster' ) ),
			'bulk_done'   => array( 'success', __( 'Bulk optimization finished.', 'seo-boster' ) ),
			'error'       => array( 'error', (string) get_transient( 'seob_core_err_' . get_current_user_id() ) ),
		);
		SEOB_Admin::notice_from_query( $notices );

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>

		<div class="seob-card">
			<div class="seob-card-header">
				<h2 class="seob-card-title"><?php esc_html_e( 'Core Site Pages Audit & Optimization', 'seo-boster' ); ?></h2>
				<p class="seob-card-sub"><?php esc_html_e( 'Review On-Page SEO scores, ensure strict heading structure and meta lengths, and optimize without breaking page builder layouts.', 'seo-boster' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-core-bulk-form">
				<?php SEOB_Security::field( 'seob_core_bulk' ); ?>
				<input type="hidden" name="action" value="seob_core_bulk_optimize" />

				<div class="seob-bulk-bar" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
					<label><strong><?php esc_html_e( 'Optimization Mode:', 'seo-boster' ); ?></strong></label>
					<select name="bulk_mode" style="max-width: 280px;">
						<option value="conditional"><?php esc_html_e( 'Mode B: Conditional (Only if Score < 80)', 'seo-boster' ); ?></option>
						<option value="sync"><?php esc_html_e( 'Mode A: Smart Sync Only (Meta sync, no rewrite)', 'seo-boster' ); ?></option>
						<option value="full"><?php esc_html_e( 'Mode C: Full Polish (Meta + Divi shortcode fix)', 'seo-boster' ); ?></option>
					</select>
					<button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Optimize Selected Pages', 'seo-boster' ); ?></button>
				</div>

				<table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
					<thead>
						<tr>
							<th style="width: 32px;"><input type="checkbox" id="seob-select-all" onclick="jQuery('.seob-cb-page').prop('checked', this.checked);" /></th>
							<th style="width: 220px;"><?php esc_html_e( 'Page Name / URL', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'Focus Keyword', 'seo-boster' ); ?></th>
							<th style="width: 140px; text-align: center;"><?php esc_html_e( 'Rank Math Score', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'SEO Title & Length', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'Meta Description', 'seo-boster' ); ?></th>
							<th style="width: 190px; text-align: right;"><?php esc_html_e( 'Actions', 'seo-boster' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $pages ) ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'No pages found.', 'seo-boster' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $pages as $p ) : ?>
								<?php
								$eval     = SEOB_RankMath_Bridge::evaluate_score( $p->ID );
								$score    = $eval['score'];
								$kw       = get_post_meta( $p->ID, 'rank_math_focus_keyword', true );
								if ( empty( $kw ) ) {
									$kw = get_post_meta( $p->ID, '_yoast_wpseo_focuskw', true );
								}
								$t        = get_post_meta( $p->ID, 'rank_math_title', true );
								$t        = $t ? $t : $p->post_title;
								$d        = get_post_meta( $p->ID, 'rank_math_description', true );
								$d        = $d ? $d : $p->post_excerpt;
								$has_snap = SEOB_Backup_Manager::has_snapshot( $p->ID );
								$badge_bg = ( $score >= 80 ) ? '#22c55e' : ( ( $score >= 50 ) ? '#eab308' : '#ef4444' );
								?>
								<tr>
									<td><input type="checkbox" class="seob-cb-page" name="post_ids[]" value="<?php echo esc_attr( $p->ID ); ?>" /></td>
									<td>
										<strong><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" target="_blank"><?php echo esc_html( $p->post_title ); ?></a></strong>
										<br><small style="color: #64748b;"><?php echo esc_html( wp_make_link_relative( get_permalink( $p->ID ) ) ); ?></small>
									</td>
									<td>
										<input type="text" name="kw_<?php echo esc_attr( $p->ID ); ?>" value="<?php echo esc_attr( $kw ); ?>" placeholder="<?php echo esc_attr( $p->post_title ); ?>" style="width: 100%; font-size: 13px;" />
									</td>
									<td style="text-align: center;">
										<span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: bold; color: #fff; background: <?php echo esc_attr( $badge_bg ); ?>;">
											<?php echo esc_html( (string) $score ); ?> / 100
										</span>
									</td>
									<td>
										<div style="font-size: 12px;"><?php echo esc_html( mb_substr( $t, 0, 50 ) . ( mb_strlen( $t ) > 50 ? '...' : '' ) ); ?></div>
										<small style="color: <?php echo ( mb_strlen( $t ) >= 45 && mb_strlen( $t ) <= 65 ) ? '#16a34a' : '#ea580c'; ?>;">
											<?php echo esc_html( sprintf( __( '%d chars (ideal: 50-60)', 'seo-boster' ), mb_strlen( $t ) ) ); ?>
										</small>
									</td>
									<td>
										<div style="font-size: 12px;"><?php echo esc_html( mb_substr( $d, 0, 60 ) . ( mb_strlen( $d ) > 60 ? '...' : '' ) ); ?></div>
										<small style="color: <?php echo ( mb_strlen( $d ) >= 140 && mb_strlen( $d ) <= 165 ) ? '#16a34a' : '#ea580c'; ?>;">
											<?php echo esc_html( sprintf( __( '%d chars (ideal: 150-158)', 'seo-boster' ), mb_strlen( $d ) ) ); ?>
										</small>
									</td>
									<td style="text-align: right; white-space: nowrap;">
										<button type="button" class="button button-small" onclick="seobAudit(<?php echo esc_attr( $p->ID ); ?>)"><?php esc_html_e( 'Audit', 'seo-boster' ); ?></button>

										<button type="submit" form="seob-opt-<?php echo esc_attr( $p->ID ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Optimize', 'seo-boster' ); ?></button>

										<?php if ( $has_snap ) : ?>
											<button type="submit" form="seob-roll-<?php echo esc_attr( $p->ID ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Restore previous snapshot for this page?', 'seo-boster' ); ?>');" title="<?php echo esc_attr( SEOB_Backup_Manager::get_snapshot_info( $p->ID ) ); ?>"><?php esc_html_e( 'Rollback', 'seo-boster' ); ?></button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>

			<?php foreach ( $pages as $p ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-opt-<?php echo esc_attr( $p->ID ); ?>">
					<?php SEOB_Security::field( 'seob_core_optimize' ); ?>
					<input type="hidden" name="action" value="seob_core_optimize_single" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
					<input type="hidden" name="opt_mode" value="conditional" />
					<input type="hidden" name="focus_keyword" id="seob-hidden-kw-<?php echo esc_attr( $p->ID ); ?>" value="" />
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-roll-<?php echo esc_attr( $p->ID ); ?>">
					<?php SEOB_Security::field( 'seob_core_rollback' ); ?>
					<input type="hidden" name="action" value="seob_core_rollback_single" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
				</form>
			<?php endforeach; ?>
		</div>

		<script>
		function seobAudit(postId) {
			var kw = jQuery('input[name="kw_' + postId + '"]').val();
			jQuery.post(ajaxurl, {
				action: 'seob_core_audit_page',
				post_id: postId,
				keyword: kw,
				nonce: '<?php echo esc_js( wp_create_nonce( 'seob_core_audit' ) ); ?>'
			}, function(res) {
				if (res && res.success) {
					var msg = "Rank Math Score: " + res.data.score + "/100\n\nRecommendations:\n";
					if (res.data.recommendations && res.data.recommendations.length) {
						msg += "- " + res.data.recommendations.join("\n- ");
					} else {
						msg += "Great job! This page satisfies all On-Page SEO rules.";
					}
					alert(msg);
				} else {
					alert("Failed to audit page.");
				}
			});
		}
		</script>

		<?php
		SEOB_Admin::page_end();
	}
}
