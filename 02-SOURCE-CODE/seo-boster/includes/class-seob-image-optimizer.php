<?php
/**
 * Image Alt Optimizer & Divi Blurb/Gallery Fix.
 *
 * Implements Tier 1 (Shortcode attribute injection) and Tier 2 (Runtime front-end filter)
 * to eliminate missing image alt tags across Divi modules, Elementor, and standard WordPress themes.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Image_Optimizer {

	/** @var SEOB_Image_Optimizer|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {}

	public function hooks() {
		// Tier 2: Runtime HTML output filter at priority 999
		add_filter( 'the_content', array( $this, 'filter_frontend_images' ), 999 );
		add_filter( 'widget_text', array( $this, 'filter_frontend_images' ), 999 );
	}

	/**
	 * Tier 1: Inject image_alt and image_title directly into Divi shortcodes.
	 *
	 * Divi Blurbs ([et_pb_blurb]) and Galleries ([et_pb_gallery]) support undocumented
	 * image_alt and image_title shortcode parameters.
	 *
	 * @param string $content Raw post content containing shortcodes.
	 * @param string $alt     Desired localized alt text.
	 * @param string $title   Optional title attribute.
	 * @return string Modified shortcode content.
	 */
	public static function inject_divi_shortcodes( $content, $alt, $title = '' ) {
		if ( false === strpos( $content, 'et_pb_blurb' ) && false === strpos( $content, 'et_pb_gallery' ) ) {
			return $content;
		}

		$alt_attr = esc_attr( wp_strip_all_tags( (string) $alt ) );
		$ttl_attr = esc_attr( wp_strip_all_tags( (string) $title ) );
		if ( '' === $ttl_attr ) {
			$ttl_attr = $alt_attr;
		}

		// Inject into [et_pb_blurb ...]
		$content = preg_replace_callback(
			'/\[et_pb_blurb\s+([^\]]*?)\]/is',
			static function ( $m ) use ( $alt_attr, $ttl_attr ) {
				$inner = $m[1];
				// If image_alt is already present, replace it; otherwise append
				if ( preg_match( '/image_alt="[^"]*"/i', $inner ) ) {
					$inner = preg_replace( '/image_alt="[^"]*"/i', 'image_alt="' . $alt_attr . '"', $inner );
				} else {
					$inner .= ' image_alt="' . $alt_attr . '"';
				}
				if ( preg_match( '/image_title="[^"]*"/i', $inner ) ) {
					$inner = preg_replace( '/image_title="[^"]*"/i', 'image_title="' . $ttl_attr . '"', $inner );
				} else {
					$inner .= ' image_title="' . $ttl_attr . '"';
				}
				return '[et_pb_blurb ' . trim( $inner ) . ']';
			},
			$content
		);

		return $content;
	}

	/**
	 * Tier 2: Runtime HTML output filter on the_content.
	 *
	 * Scans the rendered DOM output for any <img> tag with an empty or missing alt attribute
	 * and injects a deterministic, localized alt text based on page context.
	 *
	 * @param string $content Rendered HTML.
	 * @return string
	 */
	public function filter_frontend_images( $content ) {
		if ( is_admin() || empty( $content ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$fallback_alt = self::generate_context_alt( $post_id );

		// Replace any <img> with missing alt or alt="" or alt=" "
		return preg_replace_callback(
			'/<img\s+([^>]*?)>/is',
			static function ( $m ) use ( $fallback_alt ) {
				$attrs = $m[1];

				// Case 1: Empty alt="" or alt=''
				if ( preg_match( '/alt=(["\'])\s*\1/i', $attrs ) ) {
					$attrs = preg_replace( '/alt=(["\'])\s*\1/i', 'alt="' . esc_attr( $fallback_alt ) . '"', $attrs );
					return '<img ' . $attrs . '>';
				}

				// Case 2: Completely missing alt attribute
				if ( ! preg_match( '/\balt\s*=/i', $attrs ) ) {
					$attrs .= ' alt="' . esc_attr( $fallback_alt ) . '"';
					return '<img ' . $attrs . '>';
				}

				return $m[0];
			},
			$content
		);
	}

	/**
	 * Generate a rich, localized fallback alt text based on postmeta and site name.
	 *
	 * Format: "{Primary Keyword} in {City}, {State} - {Site Name}"
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function generate_context_alt( $post_id ) {
		$site_name = get_bloginfo( 'name' );

		// 1. Try Rank Math or Yoast focus keyword
		$keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( empty( $keyword ) ) {
			$keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		}
		if ( empty( $keyword ) ) {
			$keyword = get_post_meta( $post_id, '_seob_ap_keyword', true );
		}

		// 2. Location
		$location = get_post_meta( $post_id, 'location', true );

		if ( ! empty( $keyword ) && ! empty( $location ) ) {
			return sprintf( '%s in %s - %s', $keyword, $location, $site_name );
		}

		if ( ! empty( $keyword ) ) {
			return sprintf( '%s - %s', $keyword, $site_name );
		}

		$title = get_the_title( $post_id );
		return sprintf( '%s - %s', $title, $site_name );
	}
}
