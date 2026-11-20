<?php
/**
 * Agency defaults built into this copy of the plugin, so they do not have to be typed on every site.
 * A key saved on a site (Area Pages > AI & images) or defined in wp-config.php wins over these.
 *
 * Keep this plugin zip private: anyone who has the zip or the site files can read this key.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SEOB_DEFAULT_MAGNIFIC_KEY' ) ) {
	define( 'SEOB_DEFAULT_MAGNIFIC_KEY', '8d8ce8050ce531f21ec1162b37a6288c' );
}

if ( ! defined( 'SEOB_DEFAULT_SEMRUSH_KEY' ) ) {
	define( 'SEOB_DEFAULT_SEMRUSH_KEY', 'semrtkn-pat-Gl4hYDW0TXWMa0PskGeX-g-uY0T2gCP43jSFYh0y3pRD8J7_2R10Vjh' );
}
