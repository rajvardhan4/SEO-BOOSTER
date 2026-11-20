<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

final class SEOB_Plugin {

	/** @var SEOB_Plugin|null */
	private static $instance = null;

	/** @var SEOB_Module[] All known modules, enabled or not. */
	private $modules = array();

	/** @var SEOB_Module[] Enabled modules only. */
	private $enabled = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		SEOB_DB::maybe_upgrade();
		load_plugin_textdomain( 'seo-boster', false, dirname( plugin_basename( SEOB_FILE ) ) . '/languages' );

		$this->load_modules();

		if ( is_admin() ) {
			( new SEOB_Admin( $this ) )->hooks();
		}
	}

	/**
	 * Modules that ship with SEO Boster. Add new built-in modules here.
	 */
	private function core_modules() {
		return array(
			new SEOB_Keyword_Adder(),
			new SEOB_Area_Pages(),
			new SEOB_Core_Pages(),
			new SEOB_Schema_Module(),
			new SEOB_Keyword_Research_Module(),
		);
	}

	private function load_modules() {
		$modules  = apply_filters( 'seo_boster_modules', $this->core_modules() );
		$disabled = (array) get_option( 'seob_disabled_modules', array() );

		foreach ( (array) $modules as $module ) {
			if ( ! $module instanceof SEOB_Module ) {
				continue;
			}
			$id = sanitize_key( $module->id() );
			if ( '' === $id || isset( $this->modules[ $id ] ) ) {
				continue;
			}
			$this->modules[ $id ] = $module;
			if ( ! in_array( $id, $disabled, true ) ) {
				$this->enabled[ $id ] = $module;
				$module->register();
			}
		}
	}

	public function modules() {
		return $this->modules;
	}

	public function enabled_modules() {
		return $this->enabled;
	}

	public function is_enabled( $id ) {
		return isset( $this->enabled[ $id ] );
	}
}
