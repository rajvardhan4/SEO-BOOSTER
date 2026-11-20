<?php
/**
 * Area page layout, edited inside the plugin (SEO Boster > Area Pages > Layout).
 *
 * The layout is one list of sections stored in one option. Every Area page is drawn
 * from it, so a change made once shows on all pages, on any theme or builder.
 * Page content (headings, text, FAQs, images) still comes from each page's ACF fields.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Layout {

	const OPTION = 'seob_ap_layout';

	/* ==================================================================
	 * Definition
	 * ================================================================== */

	/** Section types the editor knows, with their labels. */
	public static function types() {
		return array(
			'hero'    => __( 'Top banner (page heading)', 'seo-boster' ),
			'split'   => __( 'Image + text', 'seo-boster' ),
			'boxes'   => __( 'Service boxes', 'seo-boster' ),
			'faq'     => __( 'FAQ', 'seo-boster' ),
			'cta'     => __( 'Call to action', 'seo-boster' ),
			'text'    => __( 'Custom text', 'seo-boster' ),
			'buttons' => __( 'Buttons row', 'seo-boster' ),
		);
	}

	public static function default_global() {
		return array(
			'preset'       => 'sco',
			'accent'       => '',
			'accent2'      => '',
			'dark'         => '#171f22',
			'soft'         => '',
			'max_width'    => 1280,
			'radius'       => 8,
			'button_style' => 'solid',
			'button_shape' => 'pill',
			'heading_size' => 'm',
			'image_ratio'  => '4/3',
			'faq_schema'   => 1,
		);
	}

	/** Default layout: the team's SCO layout. */
	public static function default_sections() {
		return array(
			array( 'id' => 'hero', 'type' => 'hero', 'on' => 1, 's' => array( 'bg' => 'accent', 'size' => 'm', 'align' => 'center', 'line' => 0, 'subtitle' => '' ) ),
			array( 'id' => 's2', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 2, 'side' => 'left', 'bg' => 'none', 'show_image' => 1, 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ) ),
			array( 'id' => 's3', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 3, 'side' => 'right', 'bg' => 'accent', 'show_image' => 1, 'show_call' => 1, 'show_contact' => 0, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ) ),
			array( 'id' => 's4', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 4, 'side' => 'left', 'bg' => 'none', 'show_image' => 1, 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ) ),
			array( 'id' => 'boxes', 'type' => 'boxes', 'on' => 1, 's' => array( 'bg' => 'soft', 'columns' => 3, 'show_icon' => 1, 'style' => 'card', 'count' => 6, 'align' => 'center' ) ),
			array( 'id' => 'faq', 'type' => 'faq', 'on' => 1, 's' => array( 'bg' => 'none', 'open_first' => 0, 'count' => 10, 'align' => 'center' ) ),
			array( 'id' => 'cta', 'type' => 'cta', 'on' => 1, 's' => array( 'bg' => 'image', 'bg_field' => 'section_7_background_image', 'overlay' => 52, 'align' => 'center', 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ) ),
		);
	}

	/** The earlier SEO Boster look, kept as a second starting point. */
	public static function modern_sections() {
		return array(
			array( 'id' => 'hero', 'type' => 'hero', 'on' => 1, 's' => array( 'bg' => 'image', 'bg_field' => 'section_7_background_image', 'overlay' => 60, 'size' => 'm', 'align' => 'center', 'line' => 1 ) ),
			array( 'id' => 's2', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 2, 'side' => 'left', 'bg' => 'none' ) ),
			array( 'id' => 's3', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 3, 'side' => 'right', 'bg' => 'soft', 'show_contact' => 0 ) ),
			array( 'id' => 's4', 'type' => 'split', 'on' => 1, 's' => array( 'n' => 4, 'side' => 'left', 'bg' => 'none' ) ),
			array( 'id' => 'boxes', 'type' => 'boxes', 'on' => 1, 's' => array( 'bg' => 'soft', 'columns' => 3, 'style' => 'card' ) ),
			array( 'id' => 'faq', 'type' => 'faq', 'on' => 1, 's' => array( 'bg' => 'none', 'open_first' => 1 ) ),
			array( 'id' => 'cta', 'type' => 'cta', 'on' => 1, 's' => array( 'bg' => 'image', 'overlay' => 70 ) ),
		);
	}

	/**
	 * Ready-made starting points for the editor.
	 */
	public static function presets() {
		$sco    = self::sanitize( array( 'global' => array( 'preset' => 'sco' ), 'sections' => self::default_sections() ) );
		$modern = self::sanitize( array( 'global' => array( 'preset' => 'modern', 'dark' => '#111827', 'radius' => 12, 'button_shape' => 'rounded', 'max_width' => 1180 ), 'sections' => self::modern_sections() ) );
		return array(
			'sco'    => array( 'label' => __( 'SCO layout (team standard)', 'seo-boster' ), 'layout' => $sco ),
			'modern' => array( 'label' => __( 'Modern', 'seo-boster' ), 'layout' => $modern ),
		);
	}

	/** Settings every section has. */
	public static function common_defaults() {
		return array(
			'bg'       => 'none',
			'bg_field' => 'section_7_background_image',
			'overlay'  => 60,
			'padding'  => 'm',
			'align'    => 'left',
			'hide_d'   => 0,
			'hide_t'   => 0,
			'hide_m'   => 0,
			'anchor'   => '',
			'class'    => '',
		);
	}

	/** Defaults per type, merged over the common ones. */
	public static function type_defaults( $type ) {
		$d = array(
			'hero'    => array( 'bg' => 'image', 'size' => 'm', 'align' => 'center', 'line' => 1, 'subtitle' => '' ),
			'split'   => array( 'n' => 2, 'side' => 'left', 'show_image' => 1, 'show_heading' => 1, 'show_text' => 1, 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ),
			'boxes'   => array( 'bg' => 'soft', 'show_heading' => 1, 'columns' => 3, 'show_icon' => 1, 'style' => 'card', 'count' => 6, 'align' => 'center' ),
			'faq'     => array( 'show_heading' => 1, 'open_first' => 1, 'count' => 10 ),
			'cta'     => array( 'bg' => 'image', 'overlay' => 70, 'align' => 'center', 'show_heading' => 1, 'show_text' => 1, 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ),
			'text'    => array( 'heading' => '', 'content' => '' ),
			'buttons' => array( 'align' => 'center', 'show_call' => 1, 'show_contact' => 1, 'call_label' => 'Call Now', 'contact_label' => 'Contact Us' ),
		);
		return array_merge( self::common_defaults(), isset( $d[ $type ] ) ? $d[ $type ] : array() );
	}

	/* ==================================================================
	 * Storage
	 * ================================================================== */

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return self::sanitize(
			array(
				'global'   => isset( $saved['global'] ) ? $saved['global'] : array(),
				'sections' => ! empty( $saved['sections'] ) ? $saved['sections'] : self::default_sections(),
			)
		);
	}

	public static function save( array $layout ) {
		$clean = self::sanitize( $layout );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function reset() {
		delete_option( self::OPTION );
	}

	/**
	 * Clean a layout coming from the editor or an imported file. Only known keys and values survive.
	 *
	 * @param array $in Raw layout.
	 */
	public static function sanitize( array $in ) {
		$g   = isset( $in['global'] ) && is_array( $in['global'] ) ? $in['global'] : array();
		$def = self::default_global();
		$out = array(
			'global'   => array(
				'preset'       => isset( $g['preset'] ) && in_array( $g['preset'], array( 'sco', 'modern' ), true ) ? $g['preset'] : $def['preset'],
				'accent'       => isset( $g['accent'] ) && sanitize_hex_color( $g['accent'] ) ? sanitize_hex_color( $g['accent'] ) : '',
				'accent2'      => isset( $g['accent2'] ) && sanitize_hex_color( $g['accent2'] ) ? sanitize_hex_color( $g['accent2'] ) : '',
				'dark'         => isset( $g['dark'] ) && sanitize_hex_color( $g['dark'] ) ? sanitize_hex_color( $g['dark'] ) : $def['dark'],
				'soft'         => isset( $g['soft'] ) && sanitize_hex_color( $g['soft'] ) ? sanitize_hex_color( $g['soft'] ) : '',
				'max_width'    => isset( $g['max_width'] ) ? max( 800, min( 1600, absint( $g['max_width'] ) ) ) : $def['max_width'],
				'radius'       => isset( $g['radius'] ) ? max( 0, min( 40, absint( $g['radius'] ) ) ) : $def['radius'],
				'button_style' => isset( $g['button_style'] ) && in_array( $g['button_style'], array( 'solid', 'outline' ), true ) ? $g['button_style'] : $def['button_style'],
				'button_shape' => isset( $g['button_shape'] ) && in_array( $g['button_shape'], array( 'square', 'rounded', 'pill' ), true ) ? $g['button_shape'] : $def['button_shape'],
				'heading_size' => isset( $g['heading_size'] ) && in_array( $g['heading_size'], array( 's', 'm', 'l' ), true ) ? $g['heading_size'] : $def['heading_size'],
				'image_ratio'  => isset( $g['image_ratio'] ) && in_array( $g['image_ratio'], array( '4/3', '1/1', '16/9', 'auto' ), true ) ? $g['image_ratio'] : $def['image_ratio'],
				'faq_schema'   => empty( $g['faq_schema'] ) ? 0 : 1,
			),
			'sections' => array(),
		);

		$types = self::types();
		$ids   = array();
		foreach ( isset( $in['sections'] ) && is_array( $in['sections'] ) ? array_slice( $in['sections'], 0, 40 ) : array() as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['type'] ) || ! isset( $types[ $sec['type'] ] ) ) {
				continue;
			}
			$type = $sec['type'];
			$id   = isset( $sec['id'] ) ? sanitize_key( $sec['id'] ) : '';
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = $type . '_' . wp_generate_password( 5, false, false );
				$id = sanitize_key( $id );
			}
			$ids[ $id ] = true;
			$raw        = isset( $sec['s'] ) && is_array( $sec['s'] ) ? $sec['s'] : array();
			$s          = array();
			foreach ( self::type_defaults( $type ) as $key => $default ) {
				$v         = array_key_exists( $key, $raw ) ? $raw[ $key ] : $default;
				$s[ $key ] = self::clean_value( $key, $v, $default );
			}
			$out['sections'][] = array(
				'id'   => $id,
				'type' => $type,
				'on'   => empty( $sec['on'] ) ? 0 : 1,
				's'    => $s,
			);
		}
		if ( ! $out['sections'] ) {
			$out['sections'] = self::sanitize( array( 'sections' => self::default_sections() ) )['sections'];
		}
		return $out;
	}

	private static function clean_value( $key, $v, $default ) {
		switch ( $key ) {
			case 'bg':
				return in_array( $v, array( 'none', 'soft', 'accent', 'dark', 'image' ), true ) ? $v : $default;
			case 'bg_field':
				return array_key_exists( $v, SEOB_AP_Schema::image_fields() ) ? $v : $default;
			case 'padding':
			case 'size':
				return in_array( $v, array( 's', 'm', 'l' ), true ) ? $v : $default;
			case 'align':
				return in_array( $v, array( 'left', 'center' ), true ) ? $v : $default;
			case 'side':
				return in_array( $v, array( 'left', 'right' ), true ) ? $v : $default;
			case 'style':
				return in_array( $v, array( 'card', 'plain', 'line' ), true ) ? $v : $default;
			case 'n':
				return in_array( (int) $v, array( 2, 3, 4 ), true ) ? (int) $v : $default;
			case 'columns':
				return max( 1, min( 4, absint( $v ) ) );
			case 'count':
				return max( 1, min( 10, absint( $v ) ) );
			case 'overlay':
				return max( 0, min( 95, absint( $v ) ) );
			case 'content':
				return wp_kses_post( (string) $v );
			case 'anchor':
			case 'class':
				return implode( ' ', array_filter( array_map( 'sanitize_html_class', explode( ' ', (string) $v ) ) ) );
			default:
				if ( is_int( $default ) ) {
					return empty( $v ) ? 0 : 1;
				}
				return sanitize_text_field( (string) $v );
		}
	}

	/* ==================================================================
	 * Rendering
	 * ================================================================== */

	/**
	 * Page facts used in placeholders like {service} and {city}.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function facts( $post_id ) {
		$service  = (string) get_post_meta( $post_id, '_seob_ap_service', true );
		$location = (string) get_post_meta( $post_id, 'location', true );
		if ( '' === $service || '' === $location ) {
			$parts = explode( ' in ', get_the_title( $post_id ), 2 );
			$service  = '' !== $service ? $service : trim( $parts[0] );
			$location = '' !== $location ? $location : ( isset( $parts[1] ) ? trim( $parts[1] ) : '' );
		}
		$tel = SEOB_AP_Schema::get( $post_id, 'section_2_call_button' );
		return array(
			'{service}'  => $service,
			'{location}' => $location,
			'{city}'     => SEOB_AP_Writer::city( $location ),
			'{business}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{phone}'    => preg_replace( '/^tel:\+?1?/', '', $tel ),
			'{keyword}'  => (string) get_post_meta( $post_id, '_seob_ap_keyword', true ),
		);
	}

	private static function fill( $text, array $facts ) {
		return strtr( (string) $text, $facts );
	}

	/** CSS variables for the whole page. */
	public static function css_vars( array $g ) {
		$accent = $g['accent'] ? $g['accent'] : SEOB_AP_Design::accent();
		$on     = SEOB_AP_Design::luminance( $accent ) > 0.45 ? '#111111' : '#ffffff';
		$radius = array(
			'square'  => '0px',
			'rounded' => min( 10, (int) $g['radius'] ) . 'px',
			'pill'    => '999px',
		);
		$scale  = array( 's' => '0.88', 'm' => '1', 'l' => '1.15' );
		$vars   = array(
			'--seob-accent'    => $accent,
			'--seob-on-accent' => $on,
			'--seob-dark'      => $g['dark'],
			'--seob-accent2'   => $g['accent2'] ? $g['accent2'] : SEOB_AP_Design::accent2( $accent ),
			'--seob-max'       => (int) $g['max_width'] . 'px',
			'--seob-radius'    => (int) $g['radius'] . 'px',
			'--seob-btn-radius' => $radius[ $g['button_shape'] ],
			'--seob-h-scale'   => $scale[ $g['heading_size'] ],
			'--seob-ratio'     => 'auto' === $g['image_ratio'] ? 'auto' : $g['image_ratio'],
		);
		if ( $g['soft'] ) {
			$vars['--seob-soft'] = $g['soft'];
		}
		$css = '';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return '.seob-area{' . $css . '}';
	}

	/**
	 * Draw the whole page body.
	 *
	 * @param int        $post_id Post ID.
	 * @param array|null $layout  Layout to use (preview) or null for the saved one.
	 */
	public static function render( $post_id, $layout = null ) {
		$layout = $layout ? self::sanitize( $layout ) : self::get();
		$facts  = self::facts( $post_id );
		$g      = $layout['global'];

		echo '<main class="seob-area seob-preset-' . esc_attr( $g['preset'] ) . ' seob-btn-' . esc_attr( $g['button_style'] ) . '" id="main">';
		foreach ( $layout['sections'] as $sec ) {
			if ( empty( $sec['on'] ) ) {
				continue;
			}
			$method = 'section_' . $sec['type'];
			if ( method_exists( __CLASS__, $method ) ) {
				ob_start();
				self::$method( $post_id, $sec['s'], $facts );
				$inner = trim( ob_get_clean() );
				if ( '' !== $inner ) {
					self::wrap_open( $sec, $post_id );
					echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping below.
					echo '</div></section>';
				}
			}
		}
		echo '</main>';
	}

	private static function wrap_open( array $sec, $post_id ) {
		$s       = $sec['s'];
		$classes = array( 'seob-sec', 'seob-sec-' . $sec['type'], 'seob-bg-' . $s['bg'], 'seob-pad-' . $s['padding'], 'seob-align-' . $s['align'] );
		foreach ( array( 'hide_d' => 'seob-hide-d', 'hide_t' => 'seob-hide-t', 'hide_m' => 'seob-hide-m' ) as $k => $c ) {
			if ( ! empty( $s[ $k ] ) ) {
				$classes[] = $c;
			}
		}
		if ( $s['class'] ) {
			$classes[] = $s['class'];
		}
		$style = '';
		if ( 'image' === $s['bg'] ) {
			$img = SEOB_AP_Schema::get( $post_id, $s['bg_field'] );
			if ( $img ) {
				$style = ' style="background-image:url(' . esc_url( $img ) . ');--seob-overlay:' . ( (int) $s['overlay'] / 100 ) . '"';
			} else {
				$classes[] = 'seob-bg-dark';
			}
		}
		$id = $s['anchor'] ? ' id="' . esc_attr( $s['anchor'] ) . '"' : '';
		echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $id . $style . ' data-seob-sec="' . esc_attr( $sec['id'] ) . '"><div class="seob-area-wrap">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped.
	}

	/* ---------------- Small parts ---------------- */

	private static function f( $post_id, $name ) {
		return SEOB_AP_Schema::get( $post_id, $name );
	}

	private static function rich( $html ) {
		return wp_kses_post( wpautop( (string) $html ) );
	}

	private static function button( $url, $label, $second = false ) {
		$url = trim( (string) $url );
		if ( '' === $url || '' === trim( (string) $label ) ) {
			return '';
		}
		if ( 0 !== strpos( $url, 'tel:' ) && 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}
		return '<a class="seob-area-btn ' . ( $second ? 'is-ghost is-contact' : 'is-call' ) . '" href="' . esc_url( $url, array( 'http', 'https', 'tel', 'mailto' ) ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function image( $url, $alt, $class = '' ) {
		return '' === $url ? '' : '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}

	private static function buttons_html( $post_id, array $s, array $facts, $call_field, $contact_field ) {
		$out = '';
		if ( ! empty( $s['show_call'] ) ) {
			$out .= self::button( self::f( $post_id, $call_field ), self::fill( $s['call_label'], $facts ) );
		}
		if ( ! empty( $s['show_contact'] ) ) {
			$out .= self::button( self::f( $post_id, $contact_field ), self::fill( $s['contact_label'], $facts ), true );
		}
		return '' === $out ? '' : '<div class="seob-area-actions">' . $out . '</div>';
	}

	/* ---------------- Sections ---------------- */

	private static function section_hero( $post_id, array $s, array $facts ) {
		$h = self::f( $post_id, 'page_heading' );
		$h = '' !== $h ? $h : get_the_title( $post_id );
		echo '<div class="seob-hero-in seob-size-' . esc_attr( $s['size'] ) . '">';
		echo '<h1' . ( $s['line'] ? ' class="has-line"' : '' ) . '>' . esc_html( $h ) . '</h1>';
		if ( '' !== trim( $s['subtitle'] ) ) {
			echo '<p class="seob-hero-sub">' . esc_html( self::fill( $s['subtitle'], $facts ) ) . '</p>';
		}
		echo '</div>';
	}

	private static function section_split( $post_id, array $s, array $facts ) {
		$n       = (int) $s['n'];
		$img_map = array( 2 => 'section_2_left_image', 3 => 'section_3_right_image', 4 => 'section_4_right_image' );
		$alt_map = array( 2 => 'section_2_left_image_alt_tag', 3 => 'section_3_right_image_alt_tag', 4 => 'section_4_right_image_alt_tag' );
		$heading = $s['show_heading'] ? self::f( $post_id, "section_{$n}_heading" ) : '';
		$text    = $s['show_text'] ? self::f( $post_id, "section_{$n}_description" ) : '';
		$img     = $s['show_image'] ? self::image( self::f( $post_id, $img_map[ $n ] ), self::f( $post_id, $alt_map[ $n ] ) ) : '';
		$btns    = self::buttons_html( $post_id, $s, $facts, "section_{$n}_call_button", 2 === $n || 4 === $n ? "section_{$n}_contact_button" : 'section_2_contact_button' );
		if ( '' === $heading && '' === $text && '' === $img ) {
			return;
		}
		$solo = '' === $img;
		echo '<div class="seob-area-grid' . ( 'right' === $s['side'] ? ' is-reverse' : '' ) . ( $solo ? ' is-solo' : '' ) . '">';
		if ( ! $solo ) {
			echo '<div class="seob-area-media">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<div class="seob-area-text">';
		if ( '' !== $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
		}
		echo self::rich( $text ) . $btns; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div>';
	}

	private static function section_boxes( $post_id, array $s, array $facts ) {
		$boxes = '';
		$icon  = $s['show_icon'] ? self::image( self::f( $post_id, 'section_5_same_image' ), '', 'seob-area-icon' ) : '';
		for ( $i = 1; $i <= min( 6, (int) $s['count'] ); $i++ ) {
			$h = self::f( $post_id, "section_5_box_{$i}_heading" );
			if ( '' === $h ) {
				continue;
			}
			$boxes .= '<div class="seob-area-box">' . $icon . '<h3>' . esc_html( $h ) . '</h3>' . self::rich( self::f( $post_id, "section_5_box_{$i}_description" ) ) . '</div>';
		}
		if ( '' === $boxes ) {
			return;
		}
		$h = $s['show_heading'] ? self::f( $post_id, 'section_5_main_heading' ) : '';
		if ( '' !== $h ) {
			echo '<h2 class="seob-sec-title">' . esc_html( $h ) . '</h2>';
		}
		echo '<div class="seob-area-box-grid seob-cols-' . (int) $s['columns'] . ' seob-box-' . esc_attr( $s['style'] ) . '">' . $boxes . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function section_faq( $post_id, array $s, array $facts ) {
		$items = '';
		for ( $i = 1; $i <= (int) $s['count']; $i++ ) {
			$q = self::f( $post_id, "faq_{$i}_heading" );
			if ( '' === $q ) {
				continue;
			}
			$open   = ( $s['open_first'] && '' === $items ) ? ' open' : '';
			$items .= '<details class="seob-area-q"' . $open . '><summary>' . esc_html( $q ) . '</summary><div>' . self::rich( self::f( $post_id, "faq_{$i}_description" ) ) . '</div></details>';
		}
		if ( '' === $items ) {
			return;
		}
		$h = $s['show_heading'] ? self::f( $post_id, 'section_6_main_heading' ) : '';
		echo '<div class="seob-area-narrow">';
		if ( '' !== $h ) {
			echo '<h2 class="seob-sec-title">' . esc_html( $h ) . '</h2>';
		}
		echo $items . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function section_cta( $post_id, array $s, array $facts ) {
		$h = $s['show_heading'] ? self::f( $post_id, 'section_7_heading' ) : '';
		$t = $s['show_text'] ? self::f( $post_id, 'section_7_description' ) : '';
		$b = self::buttons_html( $post_id, $s, $facts, 'section_7_call_button', 'section_7_contact_button' );
		if ( '' === $h && '' === $t && '' === $b ) {
			return;
		}
		echo '<div class="seob-area-narrow">';
		if ( '' !== $h ) {
			echo '<h2>' . esc_html( $h ) . '</h2>';
		}
		echo self::rich( $t ) . $b . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function section_text( $post_id, array $s, array $facts ) {
		$h = self::fill( $s['heading'], $facts );
		$c = self::fill( $s['content'], $facts );
		if ( '' === trim( $h ) && '' === trim( wp_strip_all_tags( $c ) ) ) {
			return;
		}
		echo '<div class="seob-area-narrow">';
		if ( '' !== trim( $h ) ) {
			echo '<h2>' . esc_html( $h ) . '</h2>';
		}
		echo self::rich( $c ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function section_buttons( $post_id, array $s, array $facts ) {
		echo self::buttons_html( $post_id, $s, $facts, 'section_2_call_button', 'section_2_contact_button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
