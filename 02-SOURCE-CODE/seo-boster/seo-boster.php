<?php
/**
 * Plugin Name:       SEO Boster
 * Description:       Modular SEO toolkit. Keyword Adder puts each keyword on the right page and reports the exact line. Area Pages builds every Service x Location page with AI content, images, SEO meta and your design.
 * Version:           2.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rajvardhan Singh Rathore
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-boster
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

define( 'SEOB_VERSION', '2.2.0' );
define( 'SEOB_FILE', __FILE__ );
define( 'SEOB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOB_URL', plugin_dir_url( __FILE__ ) );

require_once SEOB_DIR . 'includes/agency-config.php';
require_once SEOB_DIR . 'includes/class-seob-security.php';
require_once SEOB_DIR . 'includes/class-seob-db.php';
require_once SEOB_DIR . 'includes/class-seob-module.php';
require_once SEOB_DIR . 'includes/class-seob-admin.php';
require_once SEOB_DIR . 'includes/class-seob-plugin.php';
require_once SEOB_DIR . 'includes/class-seob-schema.php';
require_once SEOB_DIR . 'includes/class-seob-image-optimizer.php';
require_once SEOB_DIR . 'includes/class-seob-rankmath-bridge.php';
require_once SEOB_DIR . 'includes/class-seob-backup-manager.php';
require_once SEOB_DIR . 'includes/class-seob-csv-parser.php';
require_once SEOB_DIR . 'includes/class-seob-keyword-research.php';

// Built-in modules. New SEO features live in /modules/<name>/ and register the same way.
require_once SEOB_DIR . 'modules/keyword-adder/class-seob-keyword-matcher.php';
require_once SEOB_DIR . 'modules/keyword-adder/class-seob-sentence-writer.php';
require_once SEOB_DIR . 'modules/keyword-adder/class-seob-content-injector.php';
require_once SEOB_DIR . 'modules/keyword-adder/class-seob-keyword-adder.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-schema.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-ai.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-writer.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-jobs.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-design.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-layout.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-setup.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-images.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-nav.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-style.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-ap-bake.php';
require_once SEOB_DIR . 'modules/area-pages/class-seob-area-pages.php';
require_once SEOB_DIR . 'modules/core-pages/class-seob-core-pages.php';
require_once SEOB_DIR . 'modules/schema/class-seob-schema-module.php';
require_once SEOB_DIR . 'modules/keyword-research/class-seob-keyword-research-module.php';

register_activation_hook(
	__FILE__,
	static function () {
		SEOB_DB::install();
		SEOB_AP_Jobs::install();
		update_option( 'seob_flush_rewrite', 1, false );
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'seob_ap_run_jobs' );
	}
);

// Flush permalinks once after activation so Area page links work right away.
add_action(
	'init',
	static function () {
		if ( get_option( 'seob_flush_rewrite' ) ) {
			delete_option( 'seob_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	},
	99
);

add_action(
	'plugins_loaded',
	static function () {
		SEOB_Image_Optimizer::instance()->hooks();
		SEOB_Plugin::instance()->boot();
	}
);
