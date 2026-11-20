<?php
/**
 * One-click site setup: installs and activates ACF if needed, then imports the team's
 * ACF export (field group, "Areas We Serve" post type, Location taxonomy) into the ACF
 * database, exactly like a manual ACF > Tools > Import. Everything stays editable in ACF.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Setup {

	const ACF_FREE = 'advanced-custom-fields/acf.php';
	const ACF_PRO  = 'advanced-custom-fields-pro/acf.php';
	const ACF_ZIP  = 'https://downloads.wordpress.org/plugin/advanced-custom-fields.latest-stable.zip';

	public static function acf_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		if ( isset( $all[ self::ACF_PRO ] ) ) {
			return self::ACF_PRO;
		}
		return isset( $all[ self::ACF_FREE ] ) ? self::ACF_FREE : '';
	}

	/**
	 * Step 1: make sure ACF is installed and active.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_acf() {
		if ( function_exists( 'acf_get_field_groups' ) ) {
			return true;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'seob_cap', __( 'Your user cannot activate plugins.', 'seo-boster' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$file = self::acf_installed();
		if ( '' === $file ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return new WP_Error( 'seob_cap', __( 'Your user cannot install plugins.', 'seo-boster' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( self::ACF_ZIP );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				$errors = $upgrader->skin->get_errors();
				return is_wp_error( $errors ) && $errors->has_errors() ? $errors : new WP_Error( 'seob_acf', __( 'ACF could not be installed. Install "Advanced Custom Fields" from Plugins > Add New, then press Setup again.', 'seo-boster' ) );
			}
			wp_clean_plugins_cache();
			$file = self::acf_installed();
		}
		$r = activate_plugin( $file );
		return is_wp_error( $r ) ? $r : true;
	}

	/**
	 * Step 2 (runs in a fresh request, with ACF loaded): import into the ACF database.
	 *
	 * @return array|WP_Error What was imported or already there.
	 */
	public static function import_acf() {
		if ( ! function_exists( 'acf_import_field_group' ) ) {
			return new WP_Error( 'seob_acf', __( 'ACF is not active.', 'seo-boster' ) );
		}
		$file = SEOB_DIR . 'modules/area-pages/data/acf-export.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'seob_acf', __( 'The ACF export file is missing.', 'seo-boster' ) );
		}

		$report = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || empty( $item['key'] ) ) {
				continue;
			}
			if ( isset( $item['fields'] ) ) {
				// Field group: import the full group (with the 3 fields the Elementor layout needs).
				if ( acf_get_field_group_post( $item['key'] ) ) {
					$report[] = array( 'field group', $item['title'], 'already there' );
					self::add_missing_fields( $item['key'] );
					continue;
				}
				$group = SEOB_AP_Schema::acf_group();
				$group = $group ? $group : $item;
				unset( $group['ID'] );
				acf_import_field_group( $group );
				$report[] = array( 'field group', $item['title'], 'imported' );
			} elseif ( isset( $item['post_type'] ) && function_exists( 'acf_import_internal_post_type' ) ) {
				if ( acf_get_internal_post_type_post( $item['key'], 'acf-post-type' ) ) {
					$report[] = array( 'post type', $item['title'], 'already there' );
					continue;
				}
				acf_import_internal_post_type( $item, 'acf-post-type' );
				$report[] = array( 'post type', $item['title'], 'imported' );
			} elseif ( isset( $item['taxonomy'] ) && function_exists( 'acf_import_internal_post_type' ) ) {
				if ( acf_get_internal_post_type_post( $item['key'], 'acf-taxonomy' ) ) {
					$report[] = array( 'taxonomy', $item['title'], 'already there' );
					continue;
				}
				acf_import_internal_post_type( $item, 'acf-taxonomy' );
				$report[] = array( 'taxonomy', $item['title'], 'imported' );
			}
		}
		update_option( 'seob_flush_rewrite', 1, false );
		return $report;
	}

	/**
	 * If an older copy of the group is in the database, add the Elementor fields it lacks.
	 *
	 * @param string $group_key Group key.
	 */
	private static function add_missing_fields( $group_key ) {
		if ( ! function_exists( 'acf_get_fields' ) || ! function_exists( 'acf_update_field' ) ) {
			return;
		}
		$group = acf_get_field_group( $group_key );
		if ( ! $group ) {
			return;
		}
		$names = wp_list_pluck( (array) acf_get_fields( $group ), 'name' );
		foreach ( SEOB_AP_Schema::extra_fields() as $f ) {
			if ( ! in_array( $f['name'], $names, true ) && ! acf_get_field( $f['key'] ) ) {
				$f['parent'] = $group['ID'];
				acf_update_field( $f );
			}
		}
	}

	/** Status lines for the Setup screen. */
	public static function status() {
		$acf_on  = function_exists( 'acf_get_field_groups' );
		$group   = $acf_on && function_exists( 'acf_get_field_group_post' ) && acf_get_field_group_post( SEOB_AP_Schema::GROUP_KEY );
		$cpt_db  = $acf_on && function_exists( 'acf_get_internal_post_type_posts' ) && (bool) array_filter(
			(array) acf_get_internal_post_type_posts( 'acf-post-type' ),
			static function ( $p ) {
				return isset( $p['post_type'] ) && SEOB_AP_Schema::POST_TYPE === $p['post_type'];
			}
		);
		return array(
			array( $acf_on, __( 'ACF plugin active', 'seo-boster' ) ),
			array( (bool) $group, __( 'ACF field group "Locations" in ACF > Field Groups (editable)', 'seo-boster' ) ),
			array( $cpt_db, __( '"Areas We Serve" post type in ACF > Post Types', 'seo-boster' ) ),
			array( post_type_exists( SEOB_AP_Schema::POST_TYPE ), __( 'Areas We Serve pages ready', 'seo-boster' ) ),
		);
	}
}
