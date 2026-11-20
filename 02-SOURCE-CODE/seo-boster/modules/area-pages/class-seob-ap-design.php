<?php
/**
 * Page design for Area pages.
 *
 * Three ways, picked on the Setup screen:
 *  - builtin:   SEO Boster's own template. Works on any theme, uses the site's fonts and brand color.
 *  - divi:      imports the team's SCO layout into Divi Theme Builder for "Areas We Serve".
 *  - elementor: imports the team's Elementor layout as an Elementor Pro Single template for "Areas We Serve".
 * Layout colors are swapped for the site's brand colors and links to old client sites are fixed.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Design {

	const OPTION = 'seob_ap_design';

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'mode'          => 'builtin',
				'accent'        => '',
				'faq_schema'    => 1,
				'divi_template' => 0,
				'divi_layout'   => 0,
				'el_template'   => 0,
			)
		);
	}

	public static function save( array $s ) {
		update_option( self::OPTION, array_merge( self::settings(), $s ), false );
	}

	public function hooks() {
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_action(
			'admin_init',
			static function () {
				if ( ! get_option( 'seob_divi_hf_fixed' ) && self::has_divi() ) {
					self::repair_divi_template();
					update_option( 'seob_divi_hf_fixed', 1, false );
				}
			}
		);
		add_filter(
			'show_admin_bar',
			static function ( $show ) {
				return ! empty( $_GET['seob_preview'] ) ? false : $show; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only hides the toolbar.
			}
		);
		add_action( 'wp_head', array( $this, 'faq_schema' ), 30 );
	}

	/* ---------------- Builders present? ---------------- */

	public static function has_divi() {
		return function_exists( 'et_setup_theme' ) || defined( 'ET_BUILDER_VERSION' ) || 'Divi' === get_template();
	}

	public static function has_elementor() {
		return defined( 'ELEMENTOR_VERSION' );
	}

	public static function has_elementor_pro() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public static function has_acf() {
		return function_exists( 'acf_add_local_field_group' );
	}

	/* ---------------- Brand colors ---------------- */

	/**
	 * Brand colors already set on the site (Divi, Elementor kit, block theme palette).
	 *
	 * @return array[] label, color.
	 */
	public static function site_colors() {
		$cached = get_transient( 'seob_ap_site_colors' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$named = array();
		if ( self::has_elementor() ) {
			$kit = (int) get_option( 'elementor_active_kit' );
			$ps  = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
			if ( ! empty( $ps['system_colors'] ) && is_array( $ps['system_colors'] ) ) {
				foreach ( $ps['system_colors'] as $c ) {
					if ( ! empty( $c['color'] ) ) {
						$named[] = array( 'label' => 'Elementor ' . ( isset( $c['title'] ) ? $c['title'] : '' ), 'color' => $c['color'] );
					}
				}
			}
		}
		$divi = get_option( 'et_divi', array() );
		if ( is_array( $divi ) ) {
			foreach ( array( 'accent_color' => 'Divi accent', 'all_buttons_bg_color' => 'Divi buttons', 'link_color' => 'Divi links', 'primary_nav_bg' => 'Divi menu', 'footer_bg' => 'Divi footer' ) as $k => $label ) {
				if ( ! empty( $divi[ $k ] ) && is_string( $divi[ $k ] ) ) {
					$named[] = array( 'label' => $label, 'color' => $divi[ $k ] );
				}
			}
		}
		if ( function_exists( 'wp_get_global_settings' ) && ! self::has_divi() ) {
			foreach ( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) as $c ) {
				if ( ! empty( $c['color'] ) ) {
					$named[] = array( 'label' => 'Theme ' . ( isset( $c['name'] ) ? $c['name'] : '' ), 'color' => $c['color'] );
				}
			}
		}

		// The colors the homepage actually uses most are the real brand colors.
		$used = array();
		$res  = wp_remote_get( home_url( '/' ), array( 'timeout' => 8, 'sslverify' => false ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			preg_match_all( '/#[0-9a-fA-F]{6}\b/', (string) wp_remote_retrieve_body( $res ), $m );
			foreach ( $m[0] as $c ) {
				$c = strtolower( $c );
				if ( ! self::is_neutral( $c ) && ! in_array( $c, self::builder_defaults(), true ) ) {
					$used[ $c ] = isset( $used[ $c ] ) ? $used[ $c ] + 1 : 1;
				}
			}
			arsort( $used );
		}

		$out  = array();
		$seen = array();
		$add  = static function ( $label, $color ) use ( &$out, &$seen ) {
			$k = strtolower( (string) $color );
			if ( ! preg_match( '/^#[0-9a-f]{6}$/', $k ) || isset( $seen[ $k ] ) ) {
				return;
			}
			$seen[ $k ] = true;
			$out[]      = array( 'label' => $label, 'color' => $k );
		};
		foreach ( array_slice( $used, 0, 6, true ) as $c => $n ) {
			$add( sprintf( 'Used on homepage (%d times)', $n ), $c );
		}
		foreach ( $named as $c ) {
			$add( trim( $c['label'] ), $c['color'] );
		}
		$add( 'Black', '#111111' );
		$add( 'White', '#ffffff' );
		$out = array_slice( $out, 0, 10 );
		set_transient( 'seob_ap_site_colors', $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	public static function accent() {
		$a = self::settings()['accent'];
		if ( preg_match( '/^#[0-9a-f]{6}$/i', (string) $a ) ) {
			return $a;
		}
		$colors = self::site_colors();
		// Prefer colors named like a brand color, then any strong color. Skip pale ones.
		usort(
			$colors,
			static function ( $a, $b ) {
				$score = static function ( $c ) {
					return preg_match( '/primary|accent|brand|button|secondary/i', $c['label'] ) ? 0 : 1;
				};
				return $score( $a ) <=> $score( $b );
			}
		);
		foreach ( $colors as $c ) {
			if ( 7 === strlen( $c['color'] ) && ! self::is_neutral( $c['color'] ) && self::luminance( $c['color'] ) < 0.6 ) {
				return $c['color'];
			}
		}
		return '#1e73be';
	}

	/**
	 * Second brand color: the next strong site color that differs enough from the first.
	 *
	 * @param string $accent First brand color.
	 */
	public static function accent2( $accent ) {
		list( $r1, $g1, $b1 ) = self::rgb( $accent );
		foreach ( self::site_colors() as $c ) {
			if ( 7 !== strlen( $c['color'] ) || self::is_neutral( $c['color'] ) || strtolower( $c['color'] ) === strtolower( $accent ) ) {
				continue;
			}
			list( $r2, $g2, $b2 ) = self::rgb( $c['color'] );
			// Dark enough for white button text, and clearly a different shade.
			if ( abs( $r1 - $r2 ) + abs( $g1 - $g2 ) + abs( $b1 - $b2 ) > 60 && self::luminance( $c['color'] ) < 0.35 ) {
				return $c['color'];
			}
		}
		// No second color on the site: a darker shade of the first.
		return sprintf( '#%02x%02x%02x', (int) ( $r1 * 0.7 ), (int) ( $g1 * 0.7 ), (int) ( $b1 * 0.7 ) );
	}

	/** Builder default colors that are not the site's brand. */
	private static function builder_defaults() {
		return array( '#2ea3f2', '#7ebec5', '#82c0c7', '#0c71c3', '#6ec1e4', '#54595f', '#7a7a7a', '#61ce70' );
	}

	/**
	 * Color map that turns the saved layout's colors into this site's brand colors.
	 *
	 * @param string $which divi|elementor.
	 */
	public static function auto_color_map( $which ) {
		$accent  = self::accent();
		$accent2 = self::accent2( $accent );
		// The layout's own brand colors (most used first) become the site's brand colors.
		// Black, white and greys stay as they are.
		$src = 'divi' === $which ? self::layout_colors( self::divi_source() ) : self::layout_colors( (string) wp_json_encode( self::elementor_source() ) );
		$map = array();
		if ( isset( $src[0] ) ) {
			$map[ $src[0] ] = $accent;
		}
		if ( isset( $src[1] ) ) {
			$map[ $src[1] ] = $accent2;
		}
		return $map;
	}

	/**
	 * Pick the right design for this site automatically:
	 * Divi -> Divi Theme Builder template, Elementor Pro -> Elementor single template, anything else -> built-in layout.
	 * An existing template is kept (so edits made in Theme Builder are never overwritten).
	 *
	 * @param bool $force Install a fresh copy even if one exists.
	 * @return array mode and message.
	 */
	public static function auto_install( $force = false ) {
		$s = self::settings();
		if ( self::has_divi() ) {
			if ( ! $force && $s['divi_template'] && 'publish' === get_post_status( $s['divi_template'] ) ) {
				self::repair_divi_template();
				self::save( array( 'mode' => 'divi' ) );
				return array( 'mode' => 'divi', 'message' => 'kept' );
			}
			$r = self::install_divi( self::auto_color_map( 'divi' ) );
			return is_wp_error( $r ) ? array( 'mode' => 'builtin', 'message' => $r->get_error_message() ) : array( 'mode' => 'divi', 'message' => 'installed' );
		}
		if ( self::has_elementor_pro() ) {
			if ( ! $force && $s['el_template'] && 'publish' === get_post_status( $s['el_template'] ) ) {
				self::save( array( 'mode' => 'elementor' ) );
				return array( 'mode' => 'elementor', 'message' => 'kept' );
			}
			$r = self::install_elementor( self::auto_color_map( 'elementor' ) );
			return is_wp_error( $r ) ? array( 'mode' => 'builtin', 'message' => $r->get_error_message() ) : array( 'mode' => 'elementor', 'message' => 'installed' );
		}
		self::save( array( 'mode' => 'builtin' ) );
		return array( 'mode' => 'builtin', 'message' => 'builtin' );
	}

	/**
	 * Image fields the page design really shows (Divi: the installed template's dynamic fields).
	 * Other designs show all of them.
	 *
	 * @return string[]
	 */
	public static function used_image_fields() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$all   = array_keys( SEOB_AP_Schema::image_fields() );
		$cache = $all;
		if ( 'divi' !== self::settings()['mode'] ) {
			return $cache;
		}
		$body    = class_exists( 'SEOB_AP_Bake' ) ? SEOB_AP_Bake::divi_body_id() : 0;
		$content = $body ? (string) get_post_field( 'post_content', $body ) : self::divi_source();
		$used    = array();
		if ( preg_match_all( '/@ET-DC@([A-Za-z0-9+\/=]+)@/', $content, $m ) ) {
			foreach ( $m[1] as $b ) {
				$j = json_decode( (string) base64_decode( $b ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Divi dynamic content.
				if ( isset( $j['content'] ) && 0 === strpos( $j['content'], 'custom_meta_' ) ) {
					$used[] = substr( $j['content'], 12 );
				}
			}
		}
		$keep  = array_values( array_intersect( $all, $used ) );
		$cache = $keep ? $keep : $all;
		return $cache;
	}

	/** Where the design is edited, for links in the admin. */
	public static function edit_link() {
		$s = self::settings();
		if ( 'divi' === $s['mode'] ) {
			return array( admin_url( 'admin.php?page=et_theme_builder' ), __( 'Divi > Theme Builder', 'seo-boster' ) );
		}
		if ( 'elementor' === $s['mode'] && $s['el_template'] ) {
			return array( admin_url( 'post.php?post=' . (int) $s['el_template'] . '&action=elementor' ), __( 'Elementor template', 'seo-boster' ) );
		}
		return array( admin_url( 'admin.php?page=seo-boster-area-pages&tab=layout' ), __( 'Page layout', 'seo-boster' ) );
	}

	private static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Relative luminance 0 (black) to 1 (white).
	 *
	 * @param string $hex Color.
	 */
	public static function luminance( $hex ) {
		$l = array();
		foreach ( self::rgb( $hex ) as $c ) {
			$c   = $c / 255;
			$l[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
	}

	public static function is_neutral( $hex ) {
		list( $r, $g, $b ) = self::rgb( $hex );
		return ( max( $r, $g, $b ) - min( $r, $g, $b ) ) < 18;
	}

	/**
	 * Brand colors used inside a layout (not black, white or grey), most used first.
	 *
	 * @param string $text Layout text.
	 */
	public static function layout_colors( $text ) {
		preg_match_all( '/#[0-9a-fA-F]{6}\b/', $text, $m );
		$count = array();
		foreach ( $m[0] as $c ) {
			$c = strtolower( $c );
			if ( ! self::is_neutral( $c ) ) {
				$count[ $c ] = isset( $count[ $c ] ) ? $count[ $c ] + 1 : 1;
			}
		}
		arsort( $count );
		return array_keys( $count );
	}

	/* ---------------- Layout files ---------------- */

	private static function read( $file ) {
		$path = SEOB_DIR . 'modules/area-pages/data/' . $file;
		return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function divi_source() {
		$d = json_decode( self::read( 'divi-layout.json' ), true );
		return is_array( $d ) && ! empty( $d['data'] ) ? (string) reset( $d['data'] ) : '';
	}

	public static function elementor_source() {
		$d = json_decode( self::read( 'elementor-layout.json' ), true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Swap colors and fix links that point to other client sites.
	 *
	 * @param string $text   Layout text.
	 * @param array  $colors old => new.
	 */
	public static function adapt( $text, array $colors ) {
		foreach ( $colors as $old => $new ) {
			if ( preg_match( '/^#[0-9a-f]{6}$/i', $old ) && preg_match( '/^#[0-9a-f]{6}$/i', $new ) ) {
				$text = str_ireplace( $old, $new, $text );
			}
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$home = untrailingslashit( home_url() );
		return preg_replace_callback(
			'#https?:(\\\\?/\\\\?/)(?:www\.)?([a-z0-9.-]+\.[a-z]{2,})((?:\\\\?/[^"\'\s\]\\\\]*)*)#i',
			static function ( $m ) use ( $host, $home ) {
				if ( strtolower( $m[2] ) === strtolower( (string) $host ) ) {
					return $m[0];
				}
				$path = str_replace( '\\/', '/', $m[3] );
				if ( preg_match( '#/wp-content/uploads/#', $path ) ) {
					return ''; // Image from another client's site: drop it, the page image comes from the fields.
				}
				$url = $home . ( '' === $path ? '/' : $path );
				return false !== strpos( $m[1], '\\' ) ? str_replace( '/', '\\/', $url ) : $url;
			},
			$text
		);
	}

	/* ---------------- Divi ---------------- */

	/**
	 * Put the SCO layout into Divi Theme Builder for Areas We Serve.
	 *
	 * @param array $colors Color map.
	 * @return array|WP_Error IDs.
	 */
	public static function install_divi( array $colors ) {
		if ( ! self::has_divi() ) {
			return new WP_Error( 'seob_no_divi', __( 'Divi is not active on this site.', 'seo-boster' ) );
		}
		$content = self::divi_source();
		if ( '' === $content ) {
			return new WP_Error( 'seob_no_layout', __( 'The Divi layout file is missing.', 'seo-boster' ) );
		}
		$content = self::adapt( $content, $colors );
		// Typography follows the site: fixed fonts in the layout become Divi's global heading and body fonts.
		$content = str_replace(
			array( '="Chivo|', '="Roboto Flex|' ),
			array( '="--et_global_heading_font|', '="--et_global_body_font|' ),
			$content
		);
		$title   = __( 'Areas We Serve (SEO Boster)', 'seo-boster' );

		// Backup copy in the Divi Library so it can always be loaded by hand.
		$lib = wp_insert_post(
			array(
				'post_type'    => 'et_pb_layout',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( ! is_wp_error( $lib ) ) {
			update_post_meta( $lib, '_et_pb_built_for_post_type', 'page' );
			if ( taxonomy_exists( 'layout_type' ) ) {
				wp_set_object_terms( $lib, 'layout', 'layout_type' );
			}
		}

		$body = wp_insert_post(
			array(
				'post_type'    => 'et_body_layout',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		update_post_meta( $body, '_et_pb_use_builder', 'on' );
		update_post_meta( $body, '_et_pb_show_page_creation', 'off' );

		$tpl = wp_insert_post(
			array(
				'post_type'   => 'et_template',
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $tpl ) ) {
			return $tpl;
		}
		// Header and footer: the same global ones the rest of the site uses.
		$global = self::divi_global_layouts();
		$meta   = array(
			'_et_autogenerated_title'   => '0',
			'_et_default'               => '0',
			'_et_enabled'               => '1',
			'_et_header_layout_id'      => (string) $global['header'],
			'_et_header_layout_enabled' => $global['header_on'],
			'_et_body_layout_id'        => (string) $body,
			'_et_body_layout_enabled'   => '1',
			'_et_footer_layout_id'      => (string) $global['footer'],
			'_et_footer_layout_enabled' => $global['footer_on'],
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $tpl, $k, $v );
		}
		add_post_meta( $tpl, '_et_use_on', 'singular:post_type:' . SEOB_AP_Schema::POST_TYPE . ':all' );

		$tb = 0;
		if ( function_exists( 'et_theme_builder_get_theme_builder_post_id' ) ) {
			$tb = (int) et_theme_builder_get_theme_builder_post_id( true, true );
		}
		if ( ! $tb ) {
			$found = get_posts( array( 'post_type' => 'et_theme_builder', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
			$tb    = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => 'et_theme_builder', 'post_status' => 'publish', 'post_title' => 'Theme Builder' ) );
		}
		if ( $tb ) {
			add_post_meta( $tb, '_et_template', (string) $tpl );
		}
		if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
			ET_Core_PageResource::remove_static_resources( 'all', 'all' );
		}

		self::save(
			array(
				'mode'          => 'divi',
				'divi_template' => (int) $tpl,
				'divi_layout'   => is_wp_error( $lib ) ? 0 : (int) $lib,
			)
		);
		SEOB_AP_Bake::apply(); // Fonts, sizes and buttons of the site go straight into the new template.
		return array( 'template' => (int) $tpl, 'body' => (int) $body, 'library' => is_wp_error( $lib ) ? 0 : (int) $lib );
	}

	/**
	 * Header and footer used by Divi's default (global) Theme Builder template.
	 */
	public static function divi_global_layouts() {
		$out = array( 'header' => 0, 'footer' => 0, 'header_on' => '1', 'footer_on' => '1' );
		$tb  = function_exists( 'et_theme_builder_get_theme_builder_post_id' ) ? (int) et_theme_builder_get_theme_builder_post_id( true, false ) : 0;
		$ids = $tb ? array_map( 'absint', (array) get_post_meta( $tb, '_et_template' ) ) : array();
		if ( ! $ids ) {
			$ids = get_posts( array( 'post_type' => 'et_template', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids' ) );
		}
		foreach ( $ids as $id ) {
			if ( '1' === (string) get_post_meta( $id, '_et_default', true ) ) {
				$out['header']    = (int) get_post_meta( $id, '_et_header_layout_id', true );
				$out['footer']    = (int) get_post_meta( $id, '_et_footer_layout_id', true );
				$h                = get_post_meta( $id, '_et_header_layout_enabled', true );
				$f                = get_post_meta( $id, '_et_footer_layout_enabled', true );
				$out['header_on'] = '' === (string) $h ? '1' : (string) $h;
				$out['footer_on'] = '' === (string) $f ? '1' : (string) $f;
				break;
			}
		}
		return $out;
	}

	/**
	 * Fix an SEO Boster Divi template made before header and footer were copied from the global template.
	 */
	public static function repair_divi_template() {
		$id = (int) self::settings()['divi_template'];
		if ( ! $id || 'et_template' !== get_post_type( $id ) ) {
			return false;
		}
		$g = self::divi_global_layouts();
		// Only fill slots still at 0, so a header or footer chosen in Theme Builder is never replaced.
		if ( ! (int) get_post_meta( $id, '_et_header_layout_id', true ) ) {
			update_post_meta( $id, '_et_header_layout_id', (string) $g['header'] );
			update_post_meta( $id, '_et_header_layout_enabled', $g['header_on'] );
		}
		if ( ! (int) get_post_meta( $id, '_et_footer_layout_id', true ) ) {
			update_post_meta( $id, '_et_footer_layout_id', (string) $g['footer'] );
			update_post_meta( $id, '_et_footer_layout_enabled', $g['footer_on'] );
		}
		if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
			ET_Core_PageResource::remove_static_resources( 'all', 'all' );
		}
		return true;
	}

	/* ---------------- Elementor ---------------- */

	/**
	 * Make the static buttons in the Elementor layout use the page's own fields.
	 *
	 * @param array $elements Elementor elements.
	 */
	private static function link_buttons( array $elements ) {
		$fields  = SEOB_AP_Schema::fields();
		$calls   = array( 'section_2_call_button', 'section_7_call_button' );
		$contact = array( 'section_2_contact_button', 'section_7_contact_button' );
		$ci      = 0;
		$ki      = 0;

		$tag = static function ( $name ) use ( $fields ) {
			$key = isset( $fields[ $name ] ) ? $fields[ $name ]['key'] : '';
			return '[elementor-tag id="' . substr( md5( $name ), 0, 7 ) . '" name="acf-url" settings="' . rawurlencode( wp_json_encode( array( 'key' => $key . ':' . $name ) ) ) . '"]';
		};

		$walk = static function ( array $els ) use ( &$walk, &$ci, &$ki, $calls, $contact, $tag ) {
			foreach ( $els as $i => $el ) {
				if ( isset( $el['widgetType'] ) && 'button' === $el['widgetType'] ) {
					$url = isset( $el['settings']['link']['url'] ) ? (string) $el['settings']['link']['url'] : '';
					$txt = isset( $el['settings']['text'] ) ? strtolower( (string) $el['settings']['text'] ) : '';
					if ( 0 === strpos( $url, 'tel:' ) || false !== strpos( $txt, 'call' ) ) {
						$field = $calls[ min( $ci++, count( $calls ) - 1 ) ];
					} elseif ( false !== strpos( $txt, 'contact' ) || false !== strpos( $url, 'contact' ) ) {
						$field = $contact[ min( $ki++, count( $contact ) - 1 ) ];
					} else {
						$field = '';
					}
					if ( $field ) {
						$dyn                               = isset( $el['settings']['__dynamic__'] ) && is_array( $el['settings']['__dynamic__'] ) ? $el['settings']['__dynamic__'] : array();
						$dyn['link']                       = $tag( $field );
						$els[ $i ]['settings']['__dynamic__'] = $dyn;
						$els[ $i ]['settings']['link']['url'] = ''; // Old client's static link must never show.
					}
				}
				if ( ! empty( $el['elements'] ) ) {
					$els[ $i ]['elements'] = $walk( $el['elements'] );
				}
			}
			return $els;
		};
		return $walk( $elements );
	}

	/**
	 * Add the Elementor layout as an Elementor Pro Single template for Areas We Serve.
	 *
	 * @param array $colors Color map.
	 * @return array|WP_Error
	 */
	public static function install_elementor( array $colors ) {
		if ( ! self::has_elementor() ) {
			return new WP_Error( 'seob_no_el', __( 'Elementor is not active on this site.', 'seo-boster' ) );
		}
		$src = self::elementor_source();
		if ( empty( $src['content'] ) ) {
			return new WP_Error( 'seob_no_layout', __( 'The Elementor layout file is missing.', 'seo-boster' ) );
		}
		$content = self::link_buttons( $src['content'] );
		$json    = self::adapt( (string) wp_json_encode( $content ), $colors );
		$content = json_decode( $json, true );
		if ( ! is_array( $content ) ) {
			return new WP_Error( 'seob_el_json', __( 'Could not prepare the Elementor layout.', 'seo-boster' ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => __( 'Areas We Serve (SEO Boster)', 'seo-boster' ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'single-post' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		update_post_meta( $id, '_elementor_page_settings', isset( $src['page_settings'] ) && is_array( $src['page_settings'] ) ? $src['page_settings'] : array() );
		if ( taxonomy_exists( 'elementor_library_type' ) ) {
			wp_set_object_terms( $id, 'single-post', 'elementor_library_type' );
		}

		$conditions = array( 'include/singular/' . SEOB_AP_Schema::POST_TYPE );
		$saved      = false;
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			try {
				$saved = (bool) \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions( $id, $conditions );
			} catch ( \Throwable $e ) {
				$saved = false;
			}
		}
		if ( ! $saved ) {
			update_post_meta( $id, '_elementor_conditions', $conditions );
			$all = get_option( 'elementor_pro_theme_builder_conditions', array() );
			$all = is_array( $all ) ? $all : array();
			$all['single'][ $id ] = $conditions;
			update_option( 'elementor_pro_theme_builder_conditions', $all );
		}

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		self::save( array( 'mode' => 'elementor', 'el_template' => (int) $id ) );
		SEOB_AP_Bake::apply(); // Fonts, sizes and buttons of the site go straight into the new template.
		return array( 'template' => (int) $id, 'pro' => self::has_elementor_pro() );
	}

	/* ---------------- Built-in template ---------------- */

	/**
	 * Use the built-in template for Area pages when that mode is chosen.
	 *
	 * @param string $template Template path.
	 */
	public function template( $template ) {
		if ( 'builtin' === self::settings()['mode'] && is_singular( SEOB_AP_Schema::POST_TYPE ) ) {
			$own = SEOB_DIR . 'modules/area-pages/templates/single-area.php';
			if ( is_readable( $own ) ) {
				$layout = self::preview_layout();
				$layout = $layout ? SEOB_AP_Layout::sanitize( $layout ) : SEOB_AP_Layout::get();
				wp_enqueue_style( 'seob-area', SEOB_URL . 'assets/css/area-pages.css', array(), SEOB_VERSION );
				wp_add_inline_style( 'seob-area', SEOB_AP_Layout::css_vars( $layout['global'] ) );
				return $own;
			}
		}
		return $template;
	}

	/**
	 * Unsaved layout from the editor, only for the logged-in editor with a valid preview link.
	 *
	 * @return array|null
	 */
	public static function preview_layout() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked with wp_verify_nonce below.
		if ( empty( $_GET['seob_preview'] ) || ! SEOB_Security::can_use() ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['seob_preview'] ) );
		// phpcs:enable
		if ( ! wp_verify_nonce( $nonce, 'seob_ap_preview' ) ) {
			return null;
		}
		$l = get_transient( 'seob_ap_layout_preview_' . get_current_user_id() );
		return is_array( $l ) ? $l : null;
	}

	/**
	 * FAQPage structured data for every Area page.
	 */
	public function faq_schema() {
		if ( ! is_singular( SEOB_AP_Schema::POST_TYPE ) || ! SEOB_AP_Layout::get()['global']['faq_schema'] ) {
			return;
		}
		$id    = get_queried_object_id();
		$items = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$q = trim( wp_strip_all_tags( SEOB_AP_Schema::get( $id, "faq_{$i}_heading" ) ) );
			$a = trim( wp_strip_all_tags( SEOB_AP_Schema::get( $id, "faq_{$i}_description" ) ) );
			if ( '' !== $q && '' !== $a ) {
				$items[] = array(
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ),
				);
			}
		}
		if ( ! $items ) {
			return;
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $items,
		);
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
	}
}
