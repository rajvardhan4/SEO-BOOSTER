<?php
/**
 * Security helpers. Every admin action goes through verify() so that
 * capability and nonce checks are never forgotten.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

final class SEOB_Security {

	/**
	 * Capability needed to use SEO Boster. Filterable so a site can allow editors.
	 */
	public static function cap() {
		return (string) apply_filters( 'seo_boster_capability', 'manage_options' );
	}

	public static function can_use() {
		return current_user_can( self::cap() );
	}

	/**
	 * Stop the request unless the user has the capability and a valid nonce.
	 *
	 * @param string $action Nonce action.
	 */
	public static function verify( $action ) {
		if ( ! self::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Print a nonce field for a form.
	 *
	 * @param string $action Nonce action.
	 */
	public static function field( $action ) {
		wp_nonce_field( $action );
	}

	/**
	 * Safe redirect back into wp-admin and stop.
	 *
	 * @param string $url Target URL.
	 */
	public static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Read a plain text field from POST.
	 *
	 * @param string $key Field name.
	 */
	public static function post_text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run verify() first.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Read a textarea field from POST.
	 *
	 * @param string $key Field name.
	 */
	public static function post_textarea( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run verify() first.
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Read an array of plain text values from POST, keyed by integer.
	 *
	 * @param string $key Field name.
	 * @return array<int,string>
	 */
	public static function post_text_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run verify() first.
		$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
		$out = array();
		foreach ( $raw as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$out[ absint( $k ) ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	/**
	 * Read an array of integers from POST, keyed by integer.
	 *
	 * @param string $key Field name.
	 * @return array<int,int>
	 */
	public static function post_int_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run verify() first.
		$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
		$out = array();
		foreach ( $raw as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$out[ absint( $k ) ] = absint( $v );
			}
		}
		return $out;
	}

	/**
	 * Make a value safe for a CSV cell (blocks spreadsheet formula injection).
	 *
	 * @param string $value Cell value.
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
