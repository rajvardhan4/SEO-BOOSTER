<?php
/**
 * Schema Markup Module.
 *
 * Dedicated admin dashboard interface for configuring site-wide and localized
 * rich schema markup (LocalBusiness, Service, FAQPage, BreadcrumbList, WebPage).
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Schema_Module extends SEOB_Module {

	public function id() {
		return 'schema-markup';
	}

	public function name() {
		return __( 'Schema Markup', 'seo-boster' );
	}

	public function menu_slug() {
		return 'seob-schema';
	}

	public function description() {
		return __( 'Rich JSON-LD Schema markup on every page: LocalBusiness, Service, FAQPage, BreadcrumbList and WebPage schemas for Google rich search results.', 'seo-boster' );
	}

	public function icon() {
		return 'dashicons-networking';
	}

	public function stats() {
		$settings = SEOB_Schema::get_settings();
		$enabled  = ! empty( $settings['enabled'] );
		return array(
			__( 'schema status', 'seo-boster' ) => $enabled ? __( 'Active (All Pages)', 'seo-boster' ) : __( 'Disabled', 'seo-boster' ),
			__( 'business type', 'seo-boster' ) => isset( $settings['business_type'] ) ? $settings['business_type'] : 'LocalBusiness',
		);
	}

	public function register() {
		SEOB_Schema::instance()->hooks();

		if ( is_admin() ) {
			add_action( 'admin_post_seob_save_schema', array( $this, 'handle_save_schema' ) );
		}
	}

	public function handle_save_schema() {
		SEOB_Security::verify( 'seob_schema_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input = isset( $_POST['schema'] ) && is_array( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : array();
		SEOB_Schema::save_settings( $input );

		SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'saved' ) ) );
	}

	public function render_page() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}

		SEOB_Admin::page_start(
			__( 'Rich SEO Schema Markup', 'seo-boster' ),
			__( 'Automatically outputs structured JSON-LD schema on every page to boost Google rankings, rich snippets, and local map visibility.', 'seo-boster' )
		);

		$notices = array(
			'saved' => array( 'success', __( 'Schema settings successfully saved.', 'seo-boster' ) ),
		);
		SEOB_Admin::notice_from_query( $notices );

		$settings = SEOB_Schema::get_settings();
		$types    = SEOB_Schema::business_types();
		?>

		<div class="seob-card" style="max-width: 850px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php SEOB_Security::field( 'seob_schema_settings' ); ?>
				<input type="hidden" name="action" value="seob_save_schema" />

				<div class="seob-form-row">
					<label class="seob-switch" style="display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600;">
						<input type="checkbox" name="schema[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enable Rich Schema Markup across all site pages', 'seo-boster' ); ?></span>
					</label>
				</div>

				<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

				<h3 style="margin-bottom: 15px; font-size: 16px;"><?php esc_html_e( 'Business & Organization Profile', 'seo-boster' ); ?></h3>

				<div class="seob-form-row">
					<label for="seob_biz_type"><strong><?php esc_html_e( 'Business Type (Schema.org Type):', 'seo-boster' ); ?></strong></label>
					<select name="schema[business_type]" id="seob_biz_type" style="width: 100%; max-width: 400px;">
						<?php foreach ( $types as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['business_type'], $val ); ?>>
								<?php echo esc_html( $lbl ); ?> (<?php echo esc_html( $val ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="seob-form-row">
					<label for="seob_biz_name"><strong><?php esc_html_e( 'Business / Brand Name:', 'seo-boster' ); ?></strong></label>
					<input type="text" name="schema[business_name]" id="seob_biz_name" value="<?php echo esc_attr( $settings['business_name'] ); ?>" class="regular-text" style="width: 100%;" />
				</div>

				<div class="seob-form-row">
					<label for="seob_telephone"><strong><?php esc_html_e( 'Primary Telephone:', 'seo-boster' ); ?></strong></label>
					<input type="text" name="schema[telephone]" id="seob_telephone" value="<?php echo esc_attr( $settings['telephone'] ); ?>" placeholder="+1 (555) 000-0000" class="regular-text" style="width: 100%;" />
					<small style="color: #64748b;"><?php esc_html_e( 'Note: For Area Pages, the page localized phone overrides this default.', 'seo-boster' ); ?></small>
				</div>

				<div class="seob-form-row" style="display: flex; gap: 15px;">
					<div style="flex: 2;">
						<label for="seob_street"><strong><?php esc_html_e( 'Street Address:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[street_address]" id="seob_street" value="<?php echo esc_attr( $settings['street_address'] ); ?>" style="width: 100%;" />
					</div>
					<div style="flex: 1;">
						<label for="seob_city"><strong><?php esc_html_e( 'City:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[city]" id="seob_city" value="<?php echo esc_attr( $settings['city'] ); ?>" style="width: 100%;" />
					</div>
					<div style="flex: 1;">
						<label for="seob_state"><strong><?php esc_html_e( 'State / Region:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[state]" id="seob_state" value="<?php echo esc_attr( $settings['state'] ); ?>" style="width: 100%;" />
					</div>
				</div>

				<div class="seob-form-row" style="display: flex; gap: 15px;">
					<div style="flex: 1;">
						<label for="seob_postal"><strong><?php esc_html_e( 'Postal / ZIP Code:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[postal_code]" id="seob_postal" value="<?php echo esc_attr( $settings['postal_code'] ); ?>" style="width: 100%;" />
					</div>
					<div style="flex: 1;">
						<label for="seob_country"><strong><?php esc_html_e( 'Country:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[country]" id="seob_country" value="<?php echo esc_attr( $settings['country'] ); ?>" style="width: 100%;" />
					</div>
					<div style="flex: 1;">
						<label for="seob_price"><strong><?php esc_html_e( 'Price Range:', 'seo-boster' ); ?></strong></label>
						<input type="text" name="schema[price_range]" id="seob_price" value="<?php echo esc_attr( $settings['price_range'] ); ?>" placeholder="$$" style="width: 100%;" />
					</div>
				</div>

				<div class="seob-form-row">
					<label for="seob_logo_url"><strong><?php esc_html_e( 'Custom Logo URL (optional):', 'seo-boster' ); ?></strong></label>
					<input type="url" name="schema[logo_url]" id="seob_logo_url" value="<?php echo esc_attr( $settings['logo_url'] ); ?>" placeholder="https://site.com/logo.png" style="width: 100%;" />
					<small style="color: #64748b;"><?php esc_html_e( 'Leave empty to automatically use WordPress theme custom logo.', 'seo-boster' ); ?></small>
				</div>

				<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

				<h3 style="margin-bottom: 15px; font-size: 16px;"><?php esc_html_e( 'Enabled Schema Graph Modules', 'seo-boster' ); ?></h3>

				<div class="seob-form-row">
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="schema[enable_local_biz]" value="1" <?php checked( $settings['enable_local_biz'], 1 ); ?> />
						<strong><?php esc_html_e( 'LocalBusiness / Organization Schema', 'seo-boster' ); ?></strong>
						<span style="display: block; color: #64748b; font-size: 13px; margin-left: 24px;"><?php esc_html_e( 'Provides Google with NAP (Name, Address, Phone), logo, and local service coordinates.', 'seo-boster' ); ?></span>
					</label>
				</div>

				<div class="seob-form-row">
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="schema[enable_service]" value="1" <?php checked( $settings['enable_service'], 1 ); ?> />
						<strong><?php esc_html_e( 'Service Schema', 'seo-boster' ); ?></strong>
						<span style="display: block; color: #64748b; font-size: 13px; margin-left: 24px;"><?php esc_html_e( 'Outputs structured Service entity on all Area Pages and Service pages.', 'seo-boster' ); ?></span>
					</label>
				</div>

				<div class="seob-form-row">
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="schema[enable_faq]" value="1" <?php checked( $settings['enable_faq'], 1 ); ?> />
						<strong><?php esc_html_e( 'FAQPage Schema (Rich Google Accordion Snippets)', 'seo-boster' ); ?></strong>
						<span style="display: block; color: #64748b; font-size: 13px; margin-left: 24px;"><?php esc_html_e( 'Automatically extracts FAQ items from ACF fields or Divi accordion modules.', 'seo-boster' ); ?></span>
					</label>
				</div>

				<div class="seob-form-row">
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="schema[enable_breadcrumbs]" value="1" <?php checked( $settings['enable_breadcrumbs'], 1 ); ?> />
						<strong><?php esc_html_e( 'BreadcrumbList Schema', 'seo-boster' ); ?></strong>
						<span style="display: block; color: #64748b; font-size: 13px; margin-left: 24px;"><?php esc_html_e( 'Produces structured navigational breadcrumbs in Google search snippets.', 'seo-boster' ); ?></span>
					</label>
				</div>

				<div class="seob-form-row">
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="schema[enable_webpage]" value="1" <?php checked( $settings['enable_webpage'], 1 ); ?> />
						<strong><?php esc_html_e( 'WebPage / AboutPage / ContactPage Schema', 'seo-boster' ); ?></strong>
						<span style="display: block; color: #64748b; font-size: 13px; margin-left: 24px;"><?php esc_html_e( 'Categorizes standard pages and links them to your organization graph.', 'seo-boster' ); ?></span>
					</label>
				</div>

				<div style="margin-top: 25px;">
					<button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Save Schema Settings', 'seo-boster' ); ?></button>
				</div>
			</form>
		</div>

		<?php
		SEOB_Admin::page_end();
	}
}
