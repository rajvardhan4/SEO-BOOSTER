<?php
/**
 * Keyword Research Module.
 *
 * Dedicated admin interface for real-time local SEO keyword discovery,
 * monthly search volume analysis, keyword difficulty (KD%), and search intent classification.
 * Directly pipes discovered keywords into Area Pages and Keyword Adder in 1 click.
 *
 * Fully integrated with SEMrush API (with agency default PAT token) and autonomous
 * zero-downtime live Google Suggest fallback.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Keyword_Research_Module extends SEOB_Module {

	public function id() {
		return 'keyword-research';
	}

	public function name() {
		return __( 'Keyword Research', 'seo-boster' );
	}

	public function menu_slug() {
		return 'seob-research';
	}

	public function description() {
		return __( 'Built-in SEMrush alternative & Live API: discover real-time buyer-intent keywords, search volume, KD% and CPC, then send them to Area Pages in 1 click.', 'seo-boster' );
	}

	public function icon() {
		return 'dashicons-search';
	}

	public function stats() {
		$cached = get_transient( 'seob_research_results_' . get_current_user_id() );
		$count  = ( is_array( $cached ) && isset( $cached['results'] ) ) ? count( $cached['results'] ) : 0;
		$key    = SEOB_Keyword_Research::get_semrush_key();
		return array(
			__( 'discovered keywords', 'seo-boster' ) => $count,
			__( 'semrush api token', 'seo-boster' )   => ! empty( $key ) ? __( 'Active (Connected)', 'seo-boster' ) : __( 'Autonomous Live Mode', 'seo-boster' ),
		);
	}

	public function register() {
		if ( is_admin() ) {
			add_action( 'admin_post_seob_run_research', array( $this, 'handle_run_research' ) );
			add_action( 'admin_post_seob_research_export_csv', array( $this, 'handle_export_csv' ) );
			add_action( 'admin_post_seob_research_send_to_area', array( $this, 'handle_send_to_area' ) );
			add_action( 'admin_post_seob_test_semrush', array( $this, 'handle_test_semrush' ) );
			add_action( 'admin_post_seob_save_semrush_key', array( $this, 'handle_save_semrush_key' ) );
		}
	}

	/**
	 * Save custom SEMrush key or reset to agency default.
	 */
	public function handle_save_semrush_key() {
		SEOB_Security::verify( 'seob_semrush_key_action' );

		$new_key = isset( $_POST['semrush_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['semrush_key'] ) ) ) : '';

		if ( isset( $_POST['reset_default'] ) ) {
			delete_option( 'seob_semrush_api_key' );
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'semrush_reset' ) ) );
		}

		update_option( 'seob_semrush_api_key', $new_key );
		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'semrush_saved' ) ) );
	}

	/**
	 * Test active SEMrush connection.
	 */
	public function handle_test_semrush() {
		SEOB_Security::verify( 'seob_semrush_test_action' );

		$result = SEOB_Keyword_Research::test_semrush_connection();
		set_transient( 'seob_semrush_test_' . get_current_user_id(), $result, 60 );

		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'semrush_tested' ) ) );
	}

	/**
	 * Run keyword discovery handler.
	 */
	public function handle_run_research() {
		SEOB_Security::verify( 'seob_keyword_research' );

		$services_raw  = SEOB_Security::post_textarea( 'services' );
		$locations_raw = SEOB_Security::post_textarea( 'locations' );
		$country       = SEOB_Security::post_text( 'country' );
		$country       = $country ? $country : 'us';

		$services  = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $services_raw ) ) );
		$locations = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $locations_raw ) ) );

		if ( empty( $services ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'no_services' ) ) );
		}

		if ( empty( $locations ) ) {
			$locations = array( '' ); // Run national/general if no location specified
		}

		$all_results = array();
		foreach ( $services as $svc ) {
			foreach ( $locations as $loc ) {
				$found = SEOB_Keyword_Research::research( $svc, $loc, $country );
				foreach ( $found as $item ) {
					$all_results[] = $item;
				}
			}
		}

		// Sort master results by Search Volume DESC
		usort(
			$all_results,
			static function ( $a, $b ) {
				return ( $a['volume'] > $b['volume'] ) ? -1 : 1;
			}
		);

		$payload = array(
			'services'  => $services,
			'locations' => array_filter( $locations ),
			'results'   => $all_results,
			'time'      => current_time( 'timestamp' ),
		);

		set_transient( 'seob_research_results_' . get_current_user_id(), $payload, DAY_IN_SECONDS );

		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'research_done', 'count' => count( $all_results ) ) ) );
	}

	/**
	 * Send selected keywords directly to Area Pages form.
	 */
	public function handle_send_to_area() {
		SEOB_Security::verify( 'seob_research_actions' );

		$selected_kws = isset( $_POST['selected_kws'] ) && is_array( $_POST['selected_kws'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_kws'] ) ) : array();

		$cached = get_transient( 'seob_research_results_' . get_current_user_id() );
		if ( ! is_array( $cached ) || empty( $cached['services'] ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'error' ) ) );
		}

		$services  = $cached['services'];
		$locations = $cached['locations'];

		// Group selected keywords per service
		$service_lines = array();
		foreach ( $services as $s_name ) {
			$kws_for_s = array();
			$s_lower   = strtolower( $s_name );
			foreach ( $selected_kws as $kw ) {
				if ( false !== strpos( strtolower( $kw ), $s_lower ) ) {
					$kws_for_s[] = $kw;
				}
			}
			if ( empty( $kws_for_s ) ) {
				$kws_for_s = array_slice( $selected_kws, 0, 5 );
			}
			$service_lines[] = implode( ', ', array_unique( $kws_for_s ) );
		}

		$form_draft = array(
			'business'  => get_bloginfo( 'name' ),
			'services'  => implode( "\n", $services ),
			'keywords'  => implode( "\n", $service_lines ),
			'locations' => implode( "\n", $locations ),
		);
		set_transient( 'seob_ap_form_' . get_current_user_id(), $form_draft, DAY_IN_SECONDS );

		// Redirect directly to Area Pages create tab
		wp_safe_redirect( admin_url( 'admin.php?page=seob-area-pages&seob_msg=kws_imported' ) );
		exit;
	}

	/**
	 * Export research results to CSV.
	 */
	public function handle_export_csv() {
		SEOB_Security::verify( 'seob_research_actions' );

		$cached = get_transient( 'seob_research_results_' . get_current_user_id() );
		if ( ! is_array( $cached ) || empty( $cached['results'] ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'no_results' ) ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seo-boster-keywords-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Keyword', 'Search Volume', 'Keyword Difficulty (KD%)', 'Difficulty Label', 'Search Intent', 'Est. CPC (USD)', 'Data Source', 'Service', 'Location' ) );

		foreach ( $cached['results'] as $row ) {
			fputcsv(
				$output,
				array(
					SEOB_Security::clean_csv_cell( $row['keyword'] ),
					(int) $row['volume'],
					(int) $row['kd'] . '%',
					$row['kd_label'],
					$row['intent'],
					'$' . number_format( (float) $row['cpc'], 2 ),
					( isset( $row['source'] ) && 'semrush' === $row['source'] ) ? 'SEMrush API' : 'Live Google Engine',
					$row['service'],
					$row['location'],
				)
			);
		}
		fclose( $output );
		exit;
	}

	public function render_page() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}

		SEOB_Admin::page_start(
			__( 'Keyword Research & Search Volume Intelligence', 'seo-boster' ),
			__( 'Automated SEMrush alternative: discover real Google search queries, search volume, KD% difficulty, and search intent with 1-click export to Area Pages.', 'seo-boster' )
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display count.
		$n = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		$notices = array(
			'no_services'    => array( 'error', __( 'Please enter at least one main service.', 'seo-boster' ) ),
			'research_done'  => array( 'success', sprintf( __( 'Success! Found %d high-intent keywords with search volume and difficulty metrics.', 'seo-boster' ), $n ) ),
			'no_results'     => array( 'warning', __( 'No research results to export. Run a search below first.', 'seo-boster' ) ),
			'semrush_saved'  => array( 'success', __( 'SEMrush API Key updated successfully.', 'seo-boster' ) ),
			'semrush_reset'  => array( 'success', __( 'SEMrush API Key reset to built-in agency default.', 'seo-boster' ) ),
			'semrush_tested' => array( 'info', __( 'Connection test executed.', 'seo-boster' ) ),
		);
		SEOB_Admin::notice_from_query( $notices );

		// Check if a test result was stored
		$test_res = get_transient( 'seob_semrush_test_' . get_current_user_id() );
		if ( is_array( $test_res ) ) {
			delete_transient( 'seob_semrush_test_' . get_current_user_id() );
			$alert_class = $test_res['success'] ? 'notice-success' : ( ( 403 === $test_res['code'] ) ? 'notice-warning' : 'notice-error' );
			echo '<div class="notice ' . esc_attr( $alert_class ) . ' is-dismissible" style="padding: 12px 16px; margin: 15px 0 20px;"><p style="font-size: 14px; margin: 0;"><strong>' . esc_html__( 'SEMrush API Test:', 'seo-boster' ) . '</strong> ' . esc_html( $test_res['message'] ) . '</p></div>';
		}

		$cached  = get_transient( 'seob_research_results_' . get_current_user_id() );
		$results = ( is_array( $cached ) && isset( $cached['results'] ) ) ? $cached['results'] : array();
		$s_val   = ( is_array( $cached ) && isset( $cached['services'] ) ) ? implode( "\n", $cached['services'] ) : '';
		$l_val   = ( is_array( $cached ) && isset( $cached['locations'] ) ) ? implode( "\n", $cached['locations'] ) : '';

		$current_key = SEOB_Keyword_Research::get_semrush_key();
		$masked_key  = SEOB_Keyword_Research::get_masked_key();
		$has_custom  = (bool) get_option( 'seob_semrush_api_key' );
		?>

		<!-- SEMrush API Key Management Card -->
		<div class="seob-card" style="margin-bottom: 25px; border-left: 4px solid #3b82f6;">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
				<div>
					<h3 style="margin: 0 0 4px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-admin-network" style="color: #3b82f6;"></span>
						<?php esc_html_e( 'SEMrush API Engine Status', 'seo-boster' ); ?>
						<span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: #dbeafe; color: #1e40af;">
							<?php echo $has_custom ? esc_html__( 'Custom Key', 'seo-boster' ) : esc_html__( 'Agency Default Active', 'seo-boster' ); ?>
						</span>
					</h3>
					<p style="margin: 0; color: #64748b; font-size: 13px;">
						<?php esc_html_e( 'Active Token:', 'seo-boster' ); ?> <code><?php echo esc_html( $masked_key ); ?></code>
						<?php if ( ! $has_custom ) : ?>
							<span style="color: #16a34a; font-weight: 600;">(<?php esc_html_e( 'Pre-configured: Ready to use on all client sites without manual setup', 'seo-boster' ); ?>)</span>
						<?php endif; ?>
					</p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<?php SEOB_Security::field( 'seob_semrush_test_action' ); ?>
						<input type="hidden" name="action" value="seob_test_semrush" />
						<button type="submit" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
							<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Test Connection', 'seo-boster' ); ?>
						</button>
					</form>
					<button type="button" class="button" onclick="jQuery('#seob-semrush-key-edit-box').slideToggle(200);">
						<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Change Token', 'seo-boster' ); ?>
					</button>
				</div>
			</div>

			<div id="seob-semrush-key-edit-box" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php SEOB_Security::field( 'seob_semrush_key_action' ); ?>
					<input type="hidden" name="action" value="seob_save_semrush_key" />
					<div style="display: flex; gap: 10px; align-items: center; max-width: 650px;">
						<input type="text" name="semrush_key" value="<?php echo esc_attr( $has_custom ? $current_key : '' ); ?>" placeholder="Paste SEMrush API Key / PAT Token" style="flex: 1;" />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Key', 'seo-boster' ); ?></button>
						<?php if ( $has_custom ) : ?>
							<button type="submit" name="reset_default" value="1" class="button"><?php esc_html_e( 'Reset to Agency Default', 'seo-boster' ); ?></button>
						<?php endif; ?>
					</div>
					<small style="color: #64748b; margin-top: 6px; display: block;">
						<?php esc_html_e( 'Leave blank or reset to use the agency PAT token built into includes/agency-config.php.', 'seo-boster' ); ?>
					</small>
				</form>
			</div>
		</div>

		<div class="seob-card" style="margin-bottom: 25px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php SEOB_Security::field( 'seob_keyword_research' ); ?>
				<input type="hidden" name="action" value="seob_run_research" />

				<h3 style="margin-bottom: 8px;"><?php esc_html_e( 'Find Keywords & Monthly Search Volume', 'seo-boster' ); ?></h3>
				<p class="seob-help" style="margin-bottom: 15px;">
					<?php esc_html_e( 'Enter your core services and target cities. The engine queries live Google autocomplete databases across buyer-intent modifiers (Emergency, Best, Cost, Near Me, Licensed) and enriches metrics with SEMrush data and local demographic volume calibration.', 'seo-boster' ); ?>
				</p>

				<div class="seob-form-grid seob-form-grid-3">
					<div>
						<label class="seob-label" for="seob-res-services"><strong><?php esc_html_e( 'Main Services (one per line):', 'seo-boster' ); ?> *</strong></label>
						<textarea id="seob-res-services" name="services" rows="5" required placeholder="Plumbing&#10;Water Heater Repair&#10;Drain Cleaning" style="width: 100%;"><?php echo esc_textarea( $s_val ); ?></textarea>
					</div>

					<div>
						<label class="seob-label" for="seob-res-locations"><strong><?php esc_html_e( 'Target Cities / Locations:', 'seo-boster' ); ?></strong></label>
						<textarea id="seob-res-locations" name="locations" rows="5" placeholder="Dallas, TX&#10;Plano, TX&#10;Fort Worth, TX" style="width: 100%;"><?php echo esc_textarea( $l_val ); ?></textarea>
						<small style="color: #64748b;"><?php esc_html_e( 'Leave empty for national / non-location keyword search.', 'seo-boster' ); ?></small>
					</div>

					<div>
						<label class="seob-label" for="seob-res-country"><strong><?php esc_html_e( 'Target Search Market:', 'seo-boster' ); ?></strong></label>
						<select name="country" id="seob-res-country" style="width: 100%;">
							<option value="us">United States (Google.com)</option>
							<option value="gb">United Kingdom (Google.co.uk)</option>
							<option value="ca">Canada (Google.ca)</option>
							<option value="au">Australia (Google.com.au)</option>
							<option value="in">India (Google.co.in)</option>
						</select>
						<div style="margin-top: 25px;">
							<button type="submit" class="seob-btn seob-btn-primary" style="width: 100%; text-align: center; justify-content: center;">
								<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Run Live Keyword Discovery', 'seo-boster' ); ?>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>

		<?php if ( ! empty( $results ) ) : ?>
			<div class="seob-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-research-actions-form">
					<?php SEOB_Security::field( 'seob_research_actions' ); ?>

					<div class="seob-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
						<div>
							<h3 style="margin: 0;"><?php printf( esc_html__( 'Discovered Keywords (%d Results)', 'seo-boster' ), count( $results ) ); ?></h3>
							<small style="color: #64748b;"><?php esc_html_e( 'Select keywords to automatically populate your Area Pages form or export to CSV.', 'seo-boster' ); ?></small>
						</div>
						<div style="display: flex; gap: 10px;">
							<button type="submit" name="action" value="seob_research_send_to_area" class="seob-btn seob-btn-primary">
								<span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( '1-Click Send to Area Pages', 'seo-boster' ); ?>
							</button>
							<button type="submit" name="action" value="seob_research_export_csv" class="button">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'seo-boster' ); ?>
							</button>
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
						<thead>
							<tr>
								<th style="width: 32px;"><input type="checkbox" id="seob-kw-select-all" checked onclick="jQuery('.seob-cb-kw').prop('checked', this.checked);" /></th>
								<th style="width: 280px;"><?php esc_html_e( 'Keyword', 'seo-boster' ); ?></th>
								<th style="width: 140px; text-align: right;"><?php esc_html_e( 'Monthly Volume', 'seo-boster' ); ?></th>
								<th style="width: 160px; text-align: center;"><?php esc_html_e( 'Difficulty (KD%)', 'seo-boster' ); ?></th>
								<th style="width: 120px; text-align: center;"><?php esc_html_e( 'Search Intent', 'seo-boster' ); ?></th>
								<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Est. CPC', 'seo-boster' ); ?></th>
								<th style="width: 110px; text-align: center;"><?php esc_html_e( 'Source', 'seo-boster' ); ?></th>
								<th><?php esc_html_e( 'Target Service / City', 'seo-boster' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $results as $r ) : ?>
								<?php
								$kd       = (int) $r['kd'];
								$kd_badge = ( $kd <= 25 ) ? '#16a34a' : ( ( $kd <= 50 ) ? '#eab308' : '#dc2626' );
								$intent_bg = 'Transactional' === $r['intent'] ? '#dcfce7; color: #166534;' : ( 'Commercial' === $r['intent'] ? '#dbeafe; color: #1e40af;' : '#f1f5f9; color: #475569;' );
								$source_label = ( isset( $r['source'] ) && 'semrush' === $r['source'] ) ? 'SEMrush' : 'Live Google';
								$source_bg    = ( isset( $r['source'] ) && 'semrush' === $r['source'] ) ? '#eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;' : '#f0fdf4; color: #15803d; border: 1px solid #bbf7d0;';
								?>
								<tr>
									<td>
										<input type="checkbox" class="seob-cb-kw" name="selected_kws[]" value="<?php echo esc_attr( $r['keyword'] ); ?>" checked />
									</td>
									<td>
										<strong><?php echo esc_html( $r['keyword'] ); ?></strong>
									</td>
									<td style="text-align: right; font-weight: 600;">
										<?php echo esc_html( number_format_i18n( $r['volume'] ) ); ?>
										<small style="color: #64748b;">/ mo</small>
									</td>
									<td style="text-align: center;">
										<span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-weight: bold; font-size: 11px; color: #fff; background: <?php echo esc_attr( $kd_badge ); ?>;">
											<?php echo esc_html( (string) $kd ); ?>% - <?php echo esc_html( $r['kd_label'] ); ?>
										</span>
									</td>
									<td style="text-align: center;">
										<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $intent_bg ); ?>;">
											<?php echo esc_html( $r['intent'] ); ?>
										</span>
									</td>
									<td style="text-align: right; color: #047857; font-weight: 600;">
										$<?php echo esc_html( number_format( (float) $r['cpc'], 2 ) ); ?>
									</td>
									<td style="text-align: center;">
										<span style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; <?php echo esc_attr( $source_bg ); ?>">
											<?php echo esc_html( $source_label ); ?>
										</span>
									</td>
									<td>
										<small style="color: #64748b;"><?php echo esc_html( $r['service'] . ( $r['location'] ? ' in ' . $r['location'] : '' ) ); ?></small>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			</div>
		<?php endif; ?>

		<?php
		SEOB_Admin::page_end();
	}
}
