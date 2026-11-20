<?php
/**
 * Keyword Adder module.
 *
 * Flow: 1) paste keywords  2) plugin picks a page for each one and writes a line
 * 3) you review and change anything  4) apply  5) report shows page, line and link.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Keyword_Adder extends SEOB_Module {

	const MAX_KEYWORDS = 200;
	const MAX_LENGTH   = 120;
	const MAX_PAGES    = 500;
	const TTL          = 7200; // Two hours to review.

	public function id() {
		return 'keyword-adder';
	}

	public function name() {
		return __( 'Keyword Adder', 'seo-boster' );
	}

	public function description() {
		return __( 'Paste keywords. The plugin finds the right page for each one, adds it to that page and tells you the exact page, line and link.', 'seo-boster' );
	}

	public function icon() {
		return 'dashicons-tag';
	}

	public function stats() {
		$s = SEOB_DB::stats();
		return array(
			__( 'keywords live', 'seo-boster' ) => $s['keywords'],
			__( 'pages', 'seo-boster' )         => $s['pages'],
		);
	}

	public function register() {
		( new SEOB_Content_Injector() )->hooks();

		if ( is_admin() ) {
			add_action( 'admin_post_seob_ka_analyze', array( $this, 'handle_analyze' ) );
			add_action( 'admin_post_seob_ka_apply', array( $this, 'handle_apply' ) );
			add_action( 'admin_post_seob_ka_remove', array( $this, 'handle_remove' ) );
			add_action( 'admin_post_seob_ka_remove_batch', array( $this, 'handle_remove_batch' ) );
			add_action( 'admin_post_seob_ka_export', array( $this, 'handle_export' ) );
		}
	}

	/* ==================================================================
	 * Helpers
	 * ================================================================== */

	private function transient_key() {
		return 'seob_ka_' . get_current_user_id();
	}

	/**
	 * Clean the pasted list: one keyword per line, numbering like "1." removed, duplicates dropped.
	 *
	 * @param string $raw Pasted text.
	 * @return string[]
	 */
	public static function parse_keywords( $raw ) {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/[\r\n,;]+/', (string) $raw ) as $line ) {
			$line = preg_replace( '/^\s*(\d+\s*[\.\):-]|[-*\x{2022}])\s*/u', '', $line );
			$line = trim( preg_replace( '/\s+/', ' ', $line ) );
			if ( '' === $line || mb_strlen( $line ) > self::MAX_LENGTH ) {
				continue;
			}
			$key = strtolower( $line );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $line;
			if ( count( $out ) >= self::MAX_KEYWORDS ) {
				break;
			}
		}
		return $out;
	}

	private function allowed_post_types( array $wanted ) {
		$public = get_post_types( array( 'public' => true ), 'names' );
		unset( $public['attachment'] );
		$types = array_values( array_intersect( $wanted, array_keys( $public ) ) );
		return $types ? $types : array( 'page' );
	}

	/**
	 * Pages that should never receive keywords (shop system pages, noindex pages, chosen exclusions).
	 *
	 * @param int[] $manual Manually excluded IDs.
	 */
	private function excluded_ids( array $manual ) {
		$ids = $manual;
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'shop', 'terms' ) as $p ) {
				$ids[] = (int) wc_get_page_id( $p );
			}
		}
		$ids[] = (int) get_option( 'wp_page_for_privacy_policy' );
		$ids   = apply_filters( 'seo_boster_excluded_ids', $ids );
		return array_filter( array_map( 'absint', (array) $ids ) );
	}

	/**
	 * Gather published pages in the shape the matcher needs.
	 *
	 * @param string[] $types   Post types.
	 * @param int[]    $exclude Excluded IDs.
	 */
	private function collect_pages( array $types, array $exclude ) {
		$posts = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'publish',
				'numberposts'      => self::MAX_PAGES,
				'has_password'     => false,
				'exclude'          => $exclude,
				'suppress_filters' => true,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
			)
		);

		$front = (int) get_option( 'page_on_front' );
		$pages = array();
		foreach ( $posts as $post ) {
			if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$content = preg_replace( '/\[\/?[A-Za-z0-9_\-]+[^\]]*\]/', ' ', (string) $post->post_content );
			$text    = wp_strip_all_tags( $content );
			$pages[] = array(
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'slug'      => (string) $post->post_name,
				'focus'     => (string) get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ) . ' ' . (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ),
				'seo_title' => preg_replace( '/%%[^%]+%%/', ' ', (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true ) ),
				'text'      => mb_substr( $text, 0, 40000 ),
				'is_front'  => (int) $post->ID === $front,
			);
		}
		return $pages;
	}

	private function writer() {
		return new SEOB_Sentence_Writer( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	private function yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/* ==================================================================
	 * Actions
	 * ================================================================== */

	/**
	 * Step 1 to 2: read keywords, match them to pages, write lines, save for review.
	 */
	public function handle_analyze() {
		SEOB_Security::verify( 'seob_ka_analyze' );

		$keywords = self::parse_keywords( SEOB_Security::post_textarea( 'keywords' ) );
		if ( ! $keywords ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'no_keywords' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$wanted_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array( 'page' );
		$types        = $this->allowed_post_types( $wanted_types );
		$position     = min( 50, absint( SEOB_Security::post_text( 'position' ) ) );
		$yoast        = '1' === SEOB_Security::post_text( 'yoast_focus' );
		$manual_ex    = array_filter( array_map( 'absint', explode( ',', SEOB_Security::post_text( 'exclude' ) ) ) );

		$this->update_setting( 'post_types', $types );
		$this->update_setting( 'position', $position );
		$this->update_setting( 'yoast_focus', $yoast );
		$this->update_setting( 'exclude', implode( ',', $manual_ex ) );

		$pages = $this->collect_pages( $types, $this->excluded_ids( $manual_ex ) );
		if ( ! $pages ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'no_pages' ) ) );
		}

		$matcher = new SEOB_Keyword_Matcher();
		$rows    = $matcher->match( $keywords, $pages );
		$writer  = $this->writer();

		$slots = array();
		foreach ( $rows as $i => $row ) {
			$pid = (int) $row['post_id'];
			if ( ! isset( $slots[ $pid ] ) ) {
				$slots[ $pid ] = $pid ? count( SEOB_DB::active_for_post( $pid ) ) : 0;
			}
			$rows[ $i ]['sentence'] = $writer->write( $row['keyword'], $slots[ $pid ]++ );
		}

		$titles = array();
		foreach ( $pages as $p ) {
			$titles[ $p['id'] ] = $p['title'];
		}

		set_transient(
			$this->transient_key(),
			array(
				'rows'     => $rows,
				'pages'    => $titles,
				'position' => $position,
				'yoast'    => $yoast,
				'created'  => time(),
			),
			self::TTL
		);

		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'review' ) ) );
	}

	/**
	 * Step 3 to 4: save the reviewed placements.
	 */
	public function handle_apply() {
		SEOB_Security::verify( 'seob_ka_apply' );

		$data = get_transient( $this->transient_key() );
		if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'expired' ) ) );
		}

		$chosen    = SEOB_Security::post_int_array( 'page' );
		$sentences = SEOB_Security::post_text_array( 'sentence' );
		$batch     = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
		$writer    = $this->writer();
		$touched   = array();
		$added     = 0;
		$skipped   = 0;

		foreach ( $data['rows'] as $i => $row ) {
			$keyword = (string) $row['keyword']; // Always from the server copy, never from the form.
			$pid     = isset( $chosen[ $i ] ) ? (int) $chosen[ $i ] : 0;

			if ( ! $pid || ! isset( $data['pages'][ $pid ] ) ) {
				++$skipped;
				continue;
			}
			$post = get_post( $pid );
			if ( ! $post || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $pid ) ) {
				++$skipped;
				continue;
			}

			$existing = SEOB_DB::active_for_post( $pid );
			$dupe     = false;
			foreach ( $existing as $e ) {
				if ( 0 === strcasecmp( $e->keyword, $keyword ) ) {
					$dupe = true;
					break;
				}
			}
			if ( $dupe ) {
				++$skipped;
				continue;
			}

			$sentence = isset( $sentences[ $i ] ) ? trim( $sentences[ $i ] ) : '';
			if ( '' === $sentence || false === stripos( $sentence, $keyword ) || mb_strlen( $sentence ) > 400 ) {
				$sentence = $writer->write( $keyword, count( $existing ) );
			}

			$backup = null;
			if ( ! empty( $data['yoast'] ) && $this->yoast_active() && '' === trim( (string) get_post_meta( $pid, '_yoast_wpseo_focuskw', true ) ) ) {
				update_post_meta( $pid, '_yoast_wpseo_focuskw', $keyword );
				$backup = wp_json_encode( array( '_yoast_wpseo_focuskw' => '' ) );
			}

			SEOB_DB::insert(
				array(
					'batch_id'    => $batch,
					'post_id'     => $pid,
					'keyword'     => $keyword,
					'sentence'    => $sentence,
					'line_no'     => count( $existing ) + 1,
					'score'       => (float) $row['score'],
					'meta_backup' => $backup,
				)
			);
			$touched[ $pid ] = true;
			++$added;
		}

		foreach ( array_keys( $touched ) as $pid ) {
			if ( ! metadata_exists( 'post', $pid, SEOB_Content_Injector::META_POSITION ) ) {
				update_post_meta( $pid, SEOB_Content_Injector::META_POSITION, (int) $data['position'] );
			}
			SEOB_Content_Injector::rebuild( $pid );
			clean_post_cache( $pid );
		}

		delete_transient( $this->transient_key() );
		do_action( 'seo_boster_keywords_applied', $batch, array_keys( $touched ) );

		SEOB_Security::redirect(
			$this->admin_url(
				array(
					'tab'      => 'report',
					'batch'    => $batch,
					'seob_msg' => 'applied',
					'added'    => $added,
					'skipped'  => $skipped,
				)
			)
		);
	}

	/**
	 * Undo one placement.
	 */
	public function handle_remove() {
		SEOB_Security::verify( 'seob_ka_remove' );
		$row = SEOB_DB::get( absint( SEOB_Security::post_text( 'id' ) ) );
		if ( $row && 'active' === $row->status && current_user_can( 'edit_post', $row->post_id ) ) {
			$this->undo_row( $row );
			SEOB_Content_Injector::rebuild( (int) $row->post_id );
			clean_post_cache( (int) $row->post_id );
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'report', 'seob_msg' => 'removed' ) ) );
	}

	/**
	 * Undo a whole batch.
	 */
	public function handle_remove_batch() {
		SEOB_Security::verify( 'seob_ka_remove_batch' );
		$batch = preg_replace( '/[^A-Za-z0-9\-]/', '', SEOB_Security::post_text( 'batch' ) );
		$posts = array();
		foreach ( SEOB_DB::by_batch( $batch ) as $row ) {
			if ( 'active' === $row->status && current_user_can( 'edit_post', $row->post_id ) ) {
				$this->undo_row( $row );
				$posts[ (int) $row->post_id ] = true;
			}
		}
		foreach ( array_keys( $posts ) as $pid ) {
			SEOB_Content_Injector::rebuild( $pid );
			clean_post_cache( $pid );
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'report', 'seob_msg' => 'batch_removed' ) ) );
	}

	private function undo_row( $row ) {
		SEOB_DB::set_status( $row->id, 'removed' );
		if ( $row->meta_backup ) {
			$backup = json_decode( $row->meta_backup, true );
			if ( is_array( $backup ) && isset( $backup['_yoast_wpseo_focuskw'] ) ) {
				// Restore only if nobody changed the focus keyphrase since.
				if ( 0 === strcasecmp( (string) get_post_meta( $row->post_id, '_yoast_wpseo_focuskw', true ), $row->keyword ) ) {
					update_post_meta( $row->post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $backup['_yoast_wpseo_focuskw'] ) );
				}
			}
		}
	}

	/**
	 * Download the report as CSV.
	 */
	public function handle_export() {
		SEOB_Security::verify( 'seob_ka_export' );

		$rows = SEOB_DB::all_active( 5000 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seo-boster-keywords-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Keyword', 'Page', 'Page link', 'Line', 'Where on the page', 'Sentence', 'Direct link to line', 'Added on' ) );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					array( 'SEOB_Security', 'csv_cell' ),
					array(
						$r->keyword,
						get_the_title( $r->post_id ),
						get_permalink( $r->post_id ),
						$r->line_no,
						SEOB_Content_Injector::describe( $r->post_id, $r->line_no ),
						$r->sentence,
						SEOB_Content_Injector::line_url( $r->post_id, $r->line_no ),
						$r->created_at,
					)
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ==================================================================
	 * Screens
	 * ================================================================== */

	public function render_page() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'add';
		$tab  = in_array( $tab, array( 'add', 'review', 'report' ), true ) ? $tab : 'add';
		$tabs = array(
			'add'    => __( '1. Add keywords', 'seo-boster' ),
			'review' => __( '2. Review', 'seo-boster' ),
			'report' => __( '3. Report', 'seo-boster' ),
		);

		SEOB_Admin::page_start( $this->name(), $this->description(), $tabs, $tab, $this->admin_url() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$added   = isset( $_GET['added'] ) ? absint( $_GET['added'] ) : 0;
		$skipped = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
		// phpcs:enable
		SEOB_Admin::notice_from_query(
			array(
				'no_keywords'   => array( 'error', __( 'No keywords found. Paste one keyword per line.', 'seo-boster' ) ),
				'no_pages'      => array( 'error', __( 'No published pages were found to match against.', 'seo-boster' ) ),
				'expired'       => array( 'warning', __( 'The review expired. Please analyze the keywords again.', 'seo-boster' ) ),
				/* translators: 1: added count, 2: skipped count */
				'applied'       => array( 'success', sprintf( __( 'Done. %1$d keywords added to the site, %2$d skipped (no page chosen or already on that page).', 'seo-boster' ), $added, $skipped ) ),
				'removed'       => array( 'success', __( 'Keyword removed from the page.', 'seo-boster' ) ),
				'batch_removed' => array( 'success', __( 'That whole run has been undone.', 'seo-boster' ) ),
			)
		);

		if ( 'review' === $tab ) {
			$this->screen_review();
		} elseif ( 'report' === $tab ) {
			$this->screen_report();
		} else {
			$this->screen_add();
		}

		SEOB_Admin::page_end();
	}

	private function screen_add() {
		$types    = (array) $this->get_setting( 'post_types', array( 'page' ) );
		$position = (int) $this->get_setting( 'position', 0 );
		$yoast    = (bool) $this->get_setting( 'yoast_focus', true );
		$exclude  = (string) $this->get_setting( 'exclude', '' );
		$public   = get_post_types( array( 'public' => true ), 'objects' );
		unset( $public['attachment'] );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card">
			<?php SEOB_Security::field( 'seob_ka_analyze' ); ?>
			<input type="hidden" name="action" value="seob_ka_analyze">

			<div class="seob-grid">
				<div class="seob-col-main">
					<label class="seob-label" for="seob-keywords"><?php esc_html_e( 'Keywords', 'seo-boster' ); ?></label>
					<p class="seob-help"><?php esc_html_e( 'One per line. Numbering like "1." is removed automatically. Up to 200 keywords.', 'seo-boster' ); ?></p>
					<textarea id="seob-keywords" name="keywords" rows="16" class="seob-textarea" placeholder="aged corp&#10;aged corp for sale&#10;shelf corp&#10;..." data-seob-count="#seob-kw-count" required></textarea>
					<p class="seob-counter"><span id="seob-kw-count">0</span> <?php esc_html_e( 'keywords', 'seo-boster' ); ?></p>
				</div>

				<div class="seob-col-side">
					<div class="seob-field">
						<span class="seob-label"><?php esc_html_e( 'Search in', 'seo-boster' ); ?></span>
						<?php foreach ( $public as $slug => $obj ) : ?>
							<label class="seob-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $types, true ) ); ?>> <?php echo esc_html( $obj->labels->name ); ?></label>
						<?php endforeach; ?>
					</div>

					<div class="seob-field">
						<label class="seob-label" for="seob-position"><?php esc_html_e( 'Where on the page', 'seo-boster' ); ?></label>
						<select id="seob-position" name="position" class="seob-select">
							<option value="0" <?php selected( $position, 0 ); ?>><?php esc_html_e( 'End of page content', 'seo-boster' ); ?></option>
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $position, $i ); ?>>
									<?php /* translators: %d paragraph number */ echo esc_html( sprintf( __( 'After paragraph %d', 'seo-boster' ), $i ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<p class="seob-help"><?php esc_html_e( 'Used for pages that get keywords for the first time.', 'seo-boster' ); ?></p>
					</div>

					<?php if ( $this->yoast_active() ) : ?>
						<div class="seob-field">
							<label class="seob-check"><input type="checkbox" name="yoast_focus" value="1" <?php checked( $yoast ); ?>> <?php esc_html_e( 'Set Yoast focus keyphrase when the page has none', 'seo-boster' ); ?></label>
						</div>
					<?php endif; ?>

					<div class="seob-field">
						<label class="seob-label" for="seob-exclude"><?php esc_html_e( 'Never use these page IDs', 'seo-boster' ); ?></label>
						<input id="seob-exclude" type="text" name="exclude" class="seob-input" value="<?php echo esc_attr( $exclude ); ?>" placeholder="12, 45">
					</div>
				</div>
			</div>

			<div class="seob-actions">
				<button type="submit" class="seob-btn seob-btn-primary seob-btn-lg"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Find the right pages', 'seo-boster' ); ?></button>
				<span class="seob-help"><?php esc_html_e( 'Nothing changes on the site yet. You review everything first.', 'seo-boster' ); ?></span>
			</div>
		</form>
		<?php
	}

	private function screen_review() {
		$data = get_transient( $this->transient_key() );
		if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
			echo '<div class="seob-card seob-empty"><p>' . esc_html__( 'Nothing to review. Start by adding keywords.', 'seo-boster' ) . '</p><a class="seob-btn seob-btn-primary" href="' . esc_url( $this->admin_url() ) . '">' . esc_html__( 'Add keywords', 'seo-boster' ) . '</a></div>';
			return;
		}

		$levels = array(
			'high'   => __( 'Strong match', 'seo-boster' ),
			'medium' => __( 'Good match', 'seo-boster' ),
			'low'    => __( 'Weak match', 'seo-boster' ),
			'none'   => __( 'No match, pick a page', 'seo-boster' ),
		);
		$counts = array_count_values( wp_list_pluck( $data['rows'], 'level' ) );
		?>
		<div class="seob-stats seob-stats-small">
			<?php foreach ( $levels as $lvl => $label ) : ?>
				<div class="seob-stat seob-stat-<?php echo esc_attr( $lvl ); ?>"><span class="seob-stat-num"><?php echo esc_html( (string) ( isset( $counts[ $lvl ] ) ? $counts[ $lvl ] : 0 ) ); ?></span><span class="seob-stat-label"><?php echo esc_html( $label ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card seob-card-flush">
			<?php SEOB_Security::field( 'seob_ka_apply' ); ?>
			<input type="hidden" name="action" value="seob_ka_apply">

			<div class="seob-table-head">
				<p><?php esc_html_e( 'The plugin picked a page and wrote one line for each keyword. Change the page or the line if you want, or choose "Skip".', 'seo-boster' ); ?></p>
				<input type="search" class="seob-input seob-filter" placeholder="<?php esc_attr_e( 'Filter keywords...', 'seo-boster' ); ?>" data-seob-filter="#seob-review-table">
			</div>

			<table class="seob-table" id="seob-review-table">
				<thead>
					<tr>
						<th class="seob-col-num">#</th>
						<th><?php esc_html_e( 'Keyword', 'seo-boster' ); ?></th>
						<th><?php esc_html_e( 'Page', 'seo-boster' ); ?></th>
						<th><?php esc_html_e( 'Line to add', 'seo-boster' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $data['rows'] as $i => $row ) : ?>
					<tr data-seob-row="<?php echo esc_attr( strtolower( $row['keyword'] ) ); ?>">
						<td class="seob-col-num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
						<td>
							<strong><?php echo esc_html( $row['keyword'] ); ?></strong>
							<span class="seob-badge seob-badge-<?php echo esc_attr( $row['level'] ); ?>"><?php echo esc_html( $levels[ $row['level'] ] ); ?></span>
							<?php if ( $row['reasons'] ) : ?>
								<span class="seob-reason"><?php echo esc_html( implode( ' · ', $row['reasons'] ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<select name="page[<?php echo esc_attr( (string) $i ); ?>]" class="seob-select">
								<option value="0"><?php esc_html_e( 'Skip this keyword', 'seo-boster' ); ?></option>
								<?php foreach ( $data['pages'] as $pid => $title ) : ?>
									<option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( (int) $row['post_id'], (int) $pid ); ?>><?php echo esc_html( $title ? $title : '#' . $pid ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( ! empty( $row['alternatives'] ) ) : ?>
								<span class="seob-reason">
									<?php esc_html_e( 'Also fits:', 'seo-boster' ); ?>
									<?php echo esc_html( implode( ', ', wp_list_pluck( $row['alternatives'], 'title' ) ) ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td><textarea name="sentence[<?php echo esc_attr( (string) $i ); ?>]" rows="2" class="seob-textarea seob-sentence" maxlength="400"><?php echo esc_textarea( $row['sentence'] ); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="seob-actions seob-actions-sticky">
				<button type="submit" class="seob-btn seob-btn-primary seob-btn-lg"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Add keywords to pages', 'seo-boster' ); ?></button>
				<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( $this->admin_url() ); ?>"><?php esc_html_e( 'Start over', 'seo-boster' ); ?></a>
				<span class="seob-help"><?php esc_html_e( 'Every line must contain its keyword. If you remove it, the plugin writes a new line.', 'seo-boster' ); ?></span>
			</div>
		</form>
		<?php
	}

	private function screen_report() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter only.
		$batch = isset( $_GET['batch'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', wp_unslash( $_GET['batch'] ) ) : '';
		$rows  = $batch ? array_filter(
			SEOB_DB::by_batch( $batch ),
			static function ( $r ) {
				return 'active' === $r->status;
			}
		) : SEOB_DB::all_active();

		$by_page = array();
		foreach ( $rows as $r ) {
			$by_page[ (int) $r->post_id ][] = $r;
		}
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=seob_ka_export' ), 'seob_ka_export' );
		?>
		<div class="seob-report-layout">
			<div class="seob-report-main">
				<div class="seob-report-bar">
					<h2>
						<?php
						echo $batch
							? esc_html__( 'Keywords added in this run', 'seo-boster' )
							: esc_html__( 'All keywords live on the site', 'seo-boster' );
						?>
					</h2>
					<div>
						<?php if ( $batch ) : ?>
							<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( $this->admin_url( array( 'tab' => 'report' ) ) ); ?>"><?php esc_html_e( 'Show all', 'seo-boster' ); ?></a>
						<?php endif; ?>
						<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( $export_url ); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'seo-boster' ); ?></a>
					</div>
				</div>

				<?php if ( ! $by_page ) : ?>
					<div class="seob-card seob-empty"><p><?php esc_html_e( 'No keywords added yet.', 'seo-boster' ); ?></p></div>
				<?php endif; ?>

				<?php foreach ( $by_page as $pid => $items ) : ?>
					<div class="seob-card seob-page-card">
						<div class="seob-page-head">
							<div>
								<h3><?php echo esc_html( get_the_title( $pid ) ); ?></h3>
								<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener noreferrer" class="seob-url"><?php echo esc_html( get_permalink( $pid ) ); ?></a>
							</div>
							<div class="seob-page-links">
								<span class="seob-pill"><?php /* translators: %d count */ echo esc_html( sprintf( _n( '%d keyword', '%d keywords', count( $items ), 'seo-boster' ), count( $items ) ) ); ?></span>
								<a class="seob-btn seob-btn-ghost seob-btn-sm" href="<?php echo esc_url( get_permalink( $pid ) . '#seob-line-1' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'seo-boster' ); ?></a>
							</div>
						</div>
						<table class="seob-table">
							<thead>
								<tr>
									<th class="seob-col-num"><?php esc_html_e( 'Line', 'seo-boster' ); ?></th>
									<th><?php esc_html_e( 'Keyword', 'seo-boster' ); ?></th>
									<th><?php esc_html_e( 'Text added', 'seo-boster' ); ?></th>
									<th><?php esc_html_e( 'Where', 'seo-boster' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $items as $r ) : ?>
								<?php $line_url = SEOB_Content_Injector::line_url( $pid, $r->line_no ); ?>
								<tr>
									<td class="seob-col-num"><span class="seob-line-no"><?php echo esc_html( (string) $r->line_no ); ?></span></td>
									<td><strong><?php echo esc_html( $r->keyword ); ?></strong></td>
									<td class="seob-sentence-cell"><?php echo esc_html( $r->sentence ); ?></td>
									<td>
										<span class="seob-where"><?php echo esc_html( SEOB_Content_Injector::describe( $pid, $r->line_no ) ); ?></span>
										<span class="seob-link-row">
											<a href="<?php echo esc_url( $line_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open line', 'seo-boster' ); ?></a>
											<button type="button" class="seob-link-btn" data-seob-copy="<?php echo esc_attr( $line_url ); ?>"><?php esc_html_e( 'Copy link', 'seo-boster' ); ?></button>
										</span>
									</td>
									<td class="seob-col-actions">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-seob-confirm="<?php esc_attr_e( 'Remove this keyword from the page?', 'seo-boster' ); ?>">
											<?php SEOB_Security::field( 'seob_ka_remove' ); ?>
											<input type="hidden" name="action" value="seob_ka_remove">
											<input type="hidden" name="id" value="<?php echo esc_attr( (string) $r->id ); ?>">
											<button type="submit" class="seob-icon-btn" title="<?php esc_attr_e( 'Remove', 'seo-boster' ); ?>"><span class="dashicons dashicons-trash"></span></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

			<aside class="seob-report-side">
				<div class="seob-card">
					<h3><?php esc_html_e( 'History', 'seo-boster' ); ?></h3>
					<?php $batches = SEOB_DB::batches(); ?>
					<?php if ( ! $batches ) : ?>
						<p class="seob-help"><?php esc_html_e( 'No runs yet.', 'seo-boster' ); ?></p>
					<?php endif; ?>
					<ul class="seob-history">
						<?php foreach ( $batches as $b ) : ?>
							<li class="<?php echo $b->batch_id === $batch ? 'is-current' : ''; ?>">
								<a href="<?php echo esc_url( $this->admin_url( array( 'tab' => 'report', 'batch' => $b->batch_id ) ) ); ?>">
									<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $b->created_at ) ); ?></strong>
									<span><?php /* translators: 1 active, 2 pages */ echo esc_html( sprintf( __( '%1$d live keywords on %2$d pages', 'seo-boster' ), (int) $b->active, (int) $b->pages ) ); ?></span>
								</a>
								<?php if ( (int) $b->active > 0 ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-seob-confirm="<?php esc_attr_e( 'Undo every keyword from this run?', 'seo-boster' ); ?>">
										<?php SEOB_Security::field( 'seob_ka_remove_batch' ); ?>
										<input type="hidden" name="action" value="seob_ka_remove_batch">
										<input type="hidden" name="batch" value="<?php echo esc_attr( $b->batch_id ); ?>">
										<button type="submit" class="seob-link-btn seob-danger"><?php esc_html_e( 'Undo run', 'seo-boster' ); ?></button>
									</form>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</aside>
		</div>
		<?php
	}
}
