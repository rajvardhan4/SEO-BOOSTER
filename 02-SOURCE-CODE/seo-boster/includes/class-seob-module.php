<?php
/**
 * Base class for every SEO Boster module.
 *
 * To add a new SEO feature later:
 *   1. Create /modules/<your-module>/class-seob-<your-module>.php
 *   2. Extend SEOB_Module and fill in id(), name(), description(), render_page()
 *   3. Require the file in seo-boster.php and add it in SEOB_Plugin::core_modules()
 *      (or from another plugin, use the 'seo_boster_modules' filter).
 * The module then gets its own menu page, a card on the dashboard and an on/off switch.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

abstract class SEOB_Module {

	/** Unique slug, lowercase letters, numbers and dashes. */
	abstract public function id();

	/** Name shown in the menu and on the dashboard. */
	abstract public function name();

	/** One line about what the module does. */
	abstract public function description();

	/** Output the module admin screen. */
	abstract public function render_page();

	/** Dashicon class for the dashboard card. */
	public function icon() {
		return 'dashicons-admin-generic';
	}

	/** Hook into WordPress. Called once when the module is enabled. */
	public function register() {}

	/** Short numbers for the dashboard card, label => value. */
	public function stats() {
		return array();
	}

	public function menu_slug() {
		return 'seo-boster-' . $this->id();
	}

	public function admin_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $this->menu_slug() ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Module settings live in one option per module.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 */
	public function get_setting( $key, $default = null ) {
		$all = get_option( 'seob_settings_' . $this->id(), array() );
		return is_array( $all ) && array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public function update_setting( $key, $value ) {
		$all         = get_option( 'seob_settings_' . $this->id(), array() );
		$all         = is_array( $all ) ? $all : array();
		$all[ $key ] = $value;
		update_option( 'seob_settings_' . $this->id(), $all, false );
	}
}
