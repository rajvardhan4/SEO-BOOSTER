<?php
/**
 * 1-Click Snapshot Backup & Rollback Manager.
 *
 * Automatically saves a complete pre-modification snapshot of any post
 * (post fields, ACF custom fields, Yoast & Rank Math postmeta) before
 * optimization, enabling instant 1-click restore if needed.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Backup_Manager {

	const META_KEY = '_seob_backup_snapshot';

	/**
	 * Create an automatic rollback snapshot for a post.
	 *
	 * @param int $post_id Target post ID.
	 * @return bool True if snapshot was saved.
	 */
	public static function create_snapshot( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$post_id = (int) $post->ID;

		// 1. Core Post Data
		$core = array(
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $post->post_status,
			'post_name'    => $post->post_name,
		);

		// 2. SEO Postmeta
		$seo_meta = array(
			'rank_math_title'         => get_post_meta( $post_id, 'rank_math_title', true ),
			'rank_math_description'   => get_post_meta( $post_id, 'rank_math_description', true ),
			'rank_math_focus_keyword' => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			'rank_math_robots'        => get_post_meta( $post_id, 'rank_math_robots', true ),
			'_yoast_wpseo_title'      => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'_yoast_wpseo_metadesc'   => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'_yoast_wpseo_focuskw'    => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
			'seo_title'               => get_post_meta( $post_id, 'seo_title', true ),
			'meta_desc'               => get_post_meta( $post_id, 'meta_desc', true ),
			'_seob_ap_keyword'        => get_post_meta( $post_id, '_seob_ap_keyword', true ),
		);

		// 3. ACF Fields if ACF is active
		$acf_fields = array();
		if ( function_exists( 'get_fields' ) ) {
			$acf_data = get_fields( $post_id );
			if ( is_array( $acf_data ) ) {
				$acf_fields = $acf_data;
			}
		}

		$snapshot = array(
			'timestamp'  => current_time( 'timestamp' ),
			'date_time'  => current_time( 'mysql' ),
			'user_id'    => get_current_user_id(),
			'core'       => $core,
			'seo_meta'   => $seo_meta,
			'acf_fields' => $acf_fields,
		);

		return (bool) update_post_meta( $post_id, self::META_KEY, $snapshot );
	}

	/**
	 * Perform a 1-click restore from snapshot.
	 *
	 * @param int $post_id Post ID to restore.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function rollback( $post_id ) {
		$post_id  = (int) $post_id;
		$snapshot = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $snapshot ) || empty( $snapshot['core'] ) ) {
			return new WP_Error( 'seob_no_snapshot', __( 'No rollback snapshot found for this page.', 'seo-boster' ) );
		}

		// 1. Restore Core Post Data
		$update_args = array(
			'ID'           => $post_id,
			'post_title'   => $snapshot['core']['post_title'],
			'post_content' => $snapshot['core']['post_content'],
			'post_excerpt' => $snapshot['core']['post_excerpt'],
			'post_status'  => $snapshot['core']['post_status'],
		);
		$updated = wp_update_post( $update_args, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// 2. Restore SEO Postmeta
		if ( ! empty( $snapshot['seo_meta'] ) && is_array( $snapshot['seo_meta'] ) ) {
			foreach ( $snapshot['seo_meta'] as $meta_key => $meta_val ) {
				if ( '' === $meta_val || false === $meta_val || null === $meta_val ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $meta_val );
				}
			}
		}

		// 3. Restore ACF Fields
		if ( ! empty( $snapshot['acf_fields'] ) && is_array( $snapshot['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $snapshot['acf_fields'] as $f_key => $f_val ) {
				update_field( $f_key, $f_val, $post_id );
			}
		}

		// Log rollback action
		update_post_meta( $post_id, '_seob_last_rollback', current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Check if a post has an active rollback snapshot.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_snapshot( $post_id ) {
		$snapshot = get_post_meta( (int) $post_id, self::META_KEY, true );
		return is_array( $snapshot ) && ! empty( $snapshot['timestamp'] );
	}

	/**
	 * Get formatted date/time info of a snapshot.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_snapshot_info( $post_id ) {
		$snapshot = get_post_meta( (int) $post_id, self::META_KEY, true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['timestamp'] ) ) {
			return '';
		}
		return sprintf( __( 'Backup from %s', 'seo-boster' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $snapshot['timestamp'] ) );
	}
}
