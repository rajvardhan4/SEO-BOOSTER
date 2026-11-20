<?php
/**
 * Area Pages: writes the site's look (fonts, sizes, line spacing, buttons) into the
 * Theme Builder template itself, so the template shows the site design and every later
 * change is made in the Theme Builder. After this the plugin forces no typography CSS.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Bake {

	/* ==================================================================
	 * Values
	 * ================================================================== */

	/** "rgb(200, 16, 46)" => "#c8102e"; keeps rgba with transparency. */
	public static function color( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $v ) ) {
			return $v;
		}
		if ( 'transparent' === $v ) {
			return 'rgba(0,0,0,0)';
		}
		if ( preg_match( '/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,\/]+([\d.]+%?))?\s*\)$/', $v, $m ) ) {
			$a = isset( $m[4] ) ? $m[4] : '1';
			$a = '%' === substr( $a, -1 ) ? (float) $a / 100 : (float) $a;
			if ( $a < 1 ) {
				return 'rgba(' . (int) $m[1] . ',' . (int) $m[2] . ',' . (int) $m[3] . ',' . round( $a, 2 ) . ')';
			}
			return sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return '';
	}

	public static function family( $f ) {
		$f = explode( ',', (string) $f );
		return trim( $f[0], " \"'" );
	}

	private static function weight( $w ) {
		if ( 'bold' === $w ) {
			return '700';
		}
		return preg_match( '/^[1-9]00$/', (string) $w ) ? (string) $w : '';
	}

	/** Relative line height "1.30" => "1.3em". */
	private static function lh( $v ) {
		if ( ! is_numeric( $v ) ) {
			return '';
		}
		return rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' ) . 'em';
	}

	private static function px( $v ) {
		$n = (float) $v;
		return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' ) . 'px';
	}

	/**
	 * One measured style for a tag at all three screen sizes.
	 *
	 * @return array [ desktop => [], tablet => [], phone => [] ]
	 */
	private static function sizes( array $d, $tag ) {
		$out = array();
		foreach ( array( 'desktop', 'tablet', 'phone' ) as $s ) {
			$out[ $s ] = isset( $d[ $s ][ $tag ] ) && is_array( $d[ $s ][ $tag ] ) ? $d[ $s ][ $tag ] : array();
		}
		if ( 'h4' === $tag && empty( $out['desktop'] ) ) {
			return self::sizes( $d, 'h3' );
		}
		if ( 'btn2' === $tag && empty( $out['desktop'] ) ) {
			return self::sizes( $d, 'btn' );
		}
		return $out;
	}

	/* ==================================================================
	 * Divi
	 * ================================================================== */

	/** Divi font string: family|weight|italic|uppercase|underline|smallcaps|strike|linecolor|linestyle. */
	private static function divi_font( array $x ) {
		$fam = self::family( isset( $x['fontFamily'] ) ? $x['fontFamily'] : '' );
		if ( '' === $fam || ! preg_match( '/^[A-Za-z0-9 \-_.]+$/', $fam ) ) {
			return '';
		}
		$w  = self::weight( isset( $x['fontWeight'] ) ? $x['fontWeight'] : '' );
		$it = isset( $x['fontStyle'] ) && 'italic' === $x['fontStyle'] ? 'on' : '';
		$up = isset( $x['textTransform'] ) && 'uppercase' === $x['textTransform'] ? 'on' : '';
		$un = isset( $x['textDecorationLine'] ) && 'underline' === $x['textDecorationLine'] ? 'on' : '';
		return $fam . '|' . $w . '|' . $it . '|' . $up . '|' . $un . '||||';
	}

	/**
	 * Font, size (desktop / tablet / phone), line height and letter spacing for one Divi prefix.
	 *
	 * @param array  $a      Attributes.
	 * @param string $prefix Like "header_2" or "text".
	 * @param array  $st     sizes() result.
	 * @param bool   $fonts_only Only family and weight (for heading levels the site does not use).
	 */
	private static function divi_text( array &$a, $prefix, array $st, $fonts_only = false ) {
		$x = $st['desktop'];
		if ( ! $x ) {
			return;
		}
		$font = self::divi_font( $x );
		if ( $font ) {
			$a[ $prefix . '_font' ] = $font;
		}
		if ( $fonts_only ) {
			return;
		}
		if ( ! empty( $x['fontSize'] ) ) {
			$a[ $prefix . '_font_size' ] = self::px( $x['fontSize'] );
			$t = isset( $st['tablet']['fontSize'] ) ? self::px( $st['tablet']['fontSize'] ) : '';
			$p = isset( $st['phone']['fontSize'] ) ? self::px( $st['phone']['fontSize'] ) : '';
			if ( $t || $p ) {
				$a[ $prefix . '_font_size_tablet' ]      = $t ? $t : $a[ $prefix . '_font_size' ];
				$a[ $prefix . '_font_size_phone' ]       = $p ? $p : $a[ $prefix . '_font_size_tablet' ];
				$a[ $prefix . '_font_size_last_edited' ] = 'on|phone';
			}
		}
		if ( ! empty( $x['lineHeight'] ) && self::lh( $x['lineHeight'] ) ) {
			$a[ $prefix . '_line_height' ] = self::lh( $x['lineHeight'] );
		}
		if ( ! empty( $x['letterSpacing'] ) && 'normal' !== $x['letterSpacing'] ) {
			$a[ $prefix . '_letter_spacing' ] = $x['letterSpacing'];
		}
	}

	private static function pad( array $x ) {
		$v = array();
		foreach ( array( 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft' ) as $k ) {
			$v[] = isset( $x[ $k ] ) ? self::px( $x[ $k ] ) : '';
		}
		return implode( '|', $v ) . '|false|false';
	}

	/** Extra looks Divi has no simple field for (shadow, gradient): kept in the module's Custom CSS. */
	private static function extra_css( $old, array $x ) {
		$old = preg_replace( '/(box-shadow|background-image|transition)\s*:[^;]*;?/i', '', (string) $old );
		$add = '';
		if ( ! empty( $x['boxShadow'] ) && 'none' !== $x['boxShadow'] ) {
			$add .= 'box-shadow:' . $x['boxShadow'] . ';';
		}
		if ( ! empty( $x['backgroundImage'] ) && 'none' !== $x['backgroundImage'] ) {
			$add .= 'background-image:' . $x['backgroundImage'] . ';';
		}
		if ( ! empty( $x['transitionDuration'] ) && '0s' !== $x['transitionDuration'] ) {
			$add .= 'transition:all ' . $x['transitionDuration'] . ' ease;';
		}
		return trim( $old . $add );
	}

	/** Divi Next dual button (the SCO layout's "Call Now" / "Contact Us"). */
	private static function divi_dual( array &$a, $n, array $st, array $hover ) {
		$x = $st['desktop'];
		if ( ! $x ) {
			return;
		}
		$k = 'one' === $n ? 'one' : 'two';
		$font = self::divi_font( $x );
		if ( $font ) {
			$a[ 'btn_' . $k . '_fonts_font' ] = $font;
		}
		if ( ! empty( $x['fontSize'] ) ) {
			$a[ 'btn_' . $k . '_fonts_font_size' ] = self::px( $x['fontSize'] );
			if ( ! empty( $st['phone']['fontSize'] ) ) {
				$a[ 'btn_' . $k . '_fonts_font_size_tablet' ]      = ! empty( $st['tablet']['fontSize'] ) ? self::px( $st['tablet']['fontSize'] ) : self::px( $x['fontSize'] );
				$a[ 'btn_' . $k . '_fonts_font_size_phone' ]       = self::px( $st['phone']['fontSize'] );
				$a[ 'btn_' . $k . '_fonts_font_size_last_edited' ] = 'on|phone';
			}
		}
		if ( ! empty( $x['letterSpacing'] ) && 'normal' !== $x['letterSpacing'] ) {
			$a[ 'btn_' . $k . '_fonts_letter_spacing' ] = $x['letterSpacing'];
		}
		$color = self::color( isset( $x['color'] ) ? $x['color'] : '' );
		if ( $color ) {
			$a[ 'btn_' . $k . '_fonts_text_color' ] = $color;
			$a[ 'btn_' . $k . '_icon_color' ]       = $color;
		}
		$bg = self::color( isset( $x['backgroundColor'] ) ? $x['backgroundColor'] : '' );
		if ( $bg ) {
			$a[ 'one' === $k ? 'btn_one_show_hide' : 'btn_two_color_show_hide' ] = 'on';
			$a[ 'button_bg_' . $k ] = $bg;
		}
		if ( isset( $x['borderRadius'] ) ) {
			$r = self::px( $x['borderRadius'] );
			$a[ 'border_radii_' . $k . '_border' ] = 'on|' . $r . '|' . $r . '|' . $r . '|' . $r;
		}
		if ( isset( $x['borderWidth'] ) ) {
			$a[ 'border_width_all_' . $k . '_border' ] = isset( $x['borderStyle'] ) && 'none' === $x['borderStyle'] ? '0px' : self::px( $x['borderWidth'] );
		}
		$bc = self::color( isset( $x['borderColor'] ) ? $x['borderColor'] : '' );
		if ( $bc ) {
			$a[ 'border_color_all_' . $k . '_border' ] = $bc;
		}
		$a[ 'button_' . $k . '_padding' ] = self::pad( $x );
		unset( $a[ 'button_' . $k . '_padding_tablet' ], $a[ 'button_' . $k . '_padding_phone' ], $a[ 'button_' . $k . '_padding_last_edited' ] );

		// Hover: the site's hover color (or the normal color when the site has no hover change).
		$hbg = self::color( isset( $hover['backgroundColor'] ) ? $hover['backgroundColor'] : '' );
		$a[ 'dnxt_dual_' . $k . '_hover_bg_color' ] = $hbg ? $hbg : ( $bg ? $bg : ( isset( $a[ 'dnxt_dual_' . $k . '_hover_bg_color' ] ) ? $a[ 'dnxt_dual_' . $k . '_hover_bg_color' ] : '' ) );
		$hc = self::color( isset( $hover['color'] ) ? $hover['color'] : '' );
		$hc = $hc ? $hc : $color;
		if ( $hc ) {
			$a[ 'btn_' . $k . '_fonts_text_color__hover' ]         = $hc;
			$a[ 'btn_' . $k . '_fonts_text_color__hover_enabled' ] = 'on|hover';
			$a[ 'btn_' . $k . '_icon_color__hover' ]               = $hc;
			$a[ 'btn_' . $k . '_icon_color__hover_enabled' ]       = 'on|hover';
		}
		$hb = self::color( isset( $hover['borderColor'] ) ? $hover['borderColor'] : '' );
		if ( $hb ) {
			$a[ 'border_color_all_' . $k . '_border__hover' ]         = $hb;
			$a[ 'border_color_all_' . $k . '_border__hover_enabled' ] = 'on|hover';
		}
		$css_key       = 'custom_css_button_' . $k . '_link';
		$a[ $css_key ] = self::extra_css( isset( $a[ $css_key ] ) ? $a[ $css_key ] : '', $x );
		if ( '' === $a[ $css_key ] ) {
			unset( $a[ $css_key ] );
		}
	}

	/** Divi's own Button module. */
	private static function divi_button( array &$a, array $st, array $hover ) {
		$x = $st['desktop'];
		if ( ! $x ) {
			return;
		}
		$a['custom_button'] = 'on';
		$font               = self::divi_font( $x );
		if ( $font ) {
			$a['button_font'] = $font;
		}
		if ( ! empty( $x['fontSize'] ) ) {
			$a['button_text_size'] = self::px( $x['fontSize'] );
			if ( ! empty( $st['phone']['fontSize'] ) ) {
				$a['button_text_size_tablet']      = ! empty( $st['tablet']['fontSize'] ) ? self::px( $st['tablet']['fontSize'] ) : self::px( $x['fontSize'] );
				$a['button_text_size_phone']       = self::px( $st['phone']['fontSize'] );
				$a['button_text_size_last_edited'] = 'on|phone';
			}
		}
		if ( ! empty( $x['letterSpacing'] ) && 'normal' !== $x['letterSpacing'] ) {
			$a['button_letter_spacing'] = $x['letterSpacing'];
		}
		$map = array(
			'color'           => 'button_text_color',
			'backgroundColor' => 'button_bg_color',
			'borderColor'     => 'button_border_color',
		);
		foreach ( $map as $prop => $attr ) {
			$c = self::color( isset( $x[ $prop ] ) ? $x[ $prop ] : '' );
			if ( $c ) {
				$a[ $attr ] = $c;
			}
			$h = self::color( isset( $hover[ $prop ] ) ? $hover[ $prop ] : '' );
			if ( $h ) {
				$a[ $attr . '__hover' ]         = $h;
				$a[ $attr . '__hover_enabled' ] = 'on|hover';
			}
		}
		if ( isset( $x['borderWidth'] ) ) {
			$a['button_border_width'] = isset( $x['borderStyle'] ) && 'none' === $x['borderStyle'] ? '0px' : self::px( $x['borderWidth'] );
		}
		if ( isset( $x['borderRadius'] ) ) {
			$a['button_border_radius'] = self::px( $x['borderRadius'] );
		}
		$a['custom_padding'] = self::pad( $x );
		$a['custom_css_main_element'] = self::extra_css( isset( $a['custom_css_main_element'] ) ? $a['custom_css_main_element'] : '', $x );
		if ( '' === $a['custom_css_main_element'] ) {
			unset( $a['custom_css_main_element'] );
		}
	}

	private static function parse_attrs( $text ) {
		$a = array();
		if ( preg_match_all( '/([A-Za-z0-9_]+)="([^"]*)"/', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$a[ $x[1] ] = $x[2];
			}
		}
		return $a;
	}

	private static function build_attrs( array $a ) {
		$out = '';
		foreach ( $a as $k => $v ) {
			$out .= ' ' . $k . '="' . str_replace( array( '"', '[', ']' ), array( '%22', '%91', '%93' ), (string) $v ) . '"';
		}
		return $out;
	}

	/**
	 * Write the measured site look into Divi layout shortcodes.
	 *
	 * @param string $content Layout content.
	 * @param array  $d       Measured data (SEOB_AP_Style data).
	 */
	public static function divi( $content, array $d ) {
		$h = array(
			'h1'  => self::sizes( $d, 'h1' ),
			'h2'  => self::sizes( $d, 'h2' ),
			'h3'  => self::sizes( $d, 'h3' ),
			'h4'  => self::sizes( $d, 'h4' ),
			'p'   => self::sizes( $d, 'p' ),
			'b1'  => self::sizes( $d, 'btn' ),
			'b2'  => self::sizes( $d, 'btn2' ),
		);
		$hov1 = isset( $d['desktop']['btn_hover'] ) ? (array) $d['desktop']['btn_hover'] : array();
		$hov2 = isset( $d['desktop']['btn2_hover'] ) ? (array) $d['desktop']['btn2_hover'] : ( empty( $d['desktop']['btn2'] ) ? $hov1 : array() );
		return preg_replace_callback(
			'/\[(et_pb_text|et_pb_accordion_item|et_pb_accordion|et_pb_toggle|dnxte_dual_button|et_pb_button)((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*(\/?)\]/',
			static function ( $m ) use ( $h, $hov1, $hov2 ) {
				$a = self::parse_attrs( $m[2] );
				switch ( $m[1] ) {
					case 'et_pb_text':
						self::divi_text( $a, 'header', $h['h1'] );
						self::divi_text( $a, 'header_2', $h['h2'] );
						self::divi_text( $a, 'header_3', $h['h3'] );
						self::divi_text( $a, 'header_4', $h['h4'] );
						// H5 / H6 are used as big display headings in the layouts: same as the site's H2.
						foreach ( array( 'header_5', 'header_6' ) as $px ) {
							if ( isset( $a[ $px . '_font' ] ) || isset( $a[ $px . '_font_size' ] ) ) {
								self::divi_text( $a, $px, $h['h2'] );
							}
						}
						self::divi_text( $a, 'text', $h['p'] );
						break;
					case 'et_pb_accordion':
					case 'et_pb_toggle':
						// FAQ titles: H4 style, or the H2 font when the homepage has no small headings.
						$t = $h['h4']['desktop'] ? $h['h4'] : $h['h2'];
						self::divi_text( $a, 'toggle', $t, ! $h['h4']['desktop'] );
						self::divi_text( $a, 'closed_toggle', $t, true );
						self::divi_text( $a, 'body', $h['p'] );
						break;
					case 'et_pb_accordion_item':
						self::divi_text( $a, 'body', $h['p'] );
						break;
					case 'dnxte_dual_button':
						self::divi_dual( $a, 'one', $h['b1'], $hov1 );
						self::divi_dual( $a, 'two', $h['b2'], $hov2 );
						break;
					case 'et_pb_button':
						self::divi_button( $a, $h['b1'], $hov1 );
						break;
				}
				return '[' . $m[1] . self::build_attrs( $a ) . ( $m[3] ? ' /' : '' ) . ']';
			},
			$content
		);
	}

	/* ------------------------------------------------------------------
	 * Layout fixes (contrast and small screens). Fonts and buttons are not touched,
	 * so it is safe to run again on a template that was edited in the Theme Builder.
	 * ------------------------------------------------------------------ */

	const FIX_V = 2;

	private static function rgb_of( $c ) {
		$c = trim( (string) $c );
		if ( preg_match( '/^#([0-9a-f]{3})$/i', $c, $m ) ) {
			$c = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		if ( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i', $c, $m ) ) {
			return array( hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
		}
		if ( preg_match( '/rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/', $c, $m ) ) {
			return array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		return null;
	}

	/** Relative brightness 0 (black) .. 1 (white). */
	public static function light( $c ) {
		$r = self::rgb_of( $c );
		return $r ? ( 0.2126 * $r[0] + 0.7152 * $r[1] + 0.0722 * $r[2] ) / 255 : -1;
	}

	/** Same color, darker (keeps the brand hue). */
	public static function darker( $c, $f = 0.45 ) {
		$r = self::rgb_of( $c );
		return $r ? sprintf( '#%02x%02x%02x', (int) ( $r[0] * $f ), (int) ( $r[1] * $f ), (int) ( $r[2] * $f ) ) : $c;
	}

	private static function is_white( $c ) {
		return self::light( $c ) > 0.9;
	}

	private static function dc( $field ) {
		return '@ET-DC@' . base64_encode( (string) wp_json_encode( array( 'dynamic' => true, 'content' => 'custom_meta_' . $field, 'settings' => array( 'before' => '', 'after' => '', 'enable_html' => 'off' ) ) ) ) . '@';
	}

	/**
	 * "white" / "dark" for the text colors a section really shows (each text block's tag comes
	 * from the dynamic field's "before" HTML: <h1> uses header_text_color, <h3> header_3, none = text).
	 */
	private static function used_text( $inner ) {
		$out = array();
		if ( ! preg_match_all( '/\[et_pb_text((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*\](.*?)\[\/et_pb_text\]/s', $inner, $mm, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $mm as $t ) {
			$a   = self::parse_attrs( $t[1] );
			$tag = '';
			if ( preg_match( '/@ET-DC@([A-Za-z0-9+\/=]+)@/', $t[2], $dm ) ) {
				$j = json_decode( (string) base64_decode( $dm[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Divi dynamic content.
				if ( isset( $j['settings']['before'] ) && preg_match( '/<h([1-6])\b/i', (string) $j['settings']['before'], $hm ) ) {
					$tag = 'h' . $hm[1];
				}
			} elseif ( preg_match( '/<h([1-6])\b/i', $t[2], $hm ) ) {
				$tag = 'h' . $hm[1];
			}
			$key = '' === $tag ? 'text_text_color' : ( 'h1' === $tag ? 'header_text_color' : 'header_' . substr( $tag, 1 ) . '_text_color' );
			if ( isset( $a[ $key ] ) && '' !== $a[ $key ] ) {
				$out[] = self::is_white( $a[ $key ] ) ? 'white' : ( self::light( $a[ $key ] ) < 0.4 ? 'dark' : 'mid' );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string $content Divi layout.
	 * @return string
	 */
	public static function divi_fix( $content ) {
		$first = true;
		// Sections, one by one (opening tag plus everything up to its closing tag).
		$content = preg_replace_callback(
			'/\[et_pb_section((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*\](.*?)\[\/et_pb_section\]/s',
			static function ( $m ) use ( &$first ) {
				$a     = self::parse_attrs( $m[1] );
				$inner = $m[2];
				$hero  = $first;
				$first = false;
				// White text inside this section? Only the colors of the tags each text block really shows.
				$white = in_array( 'white', self::used_text( $inner ), true );
				$bg    = isset( $a['background_color'] ) ? $a['background_color'] : '';
				$light = '' !== $bg && self::light( $bg ) > 0.42;
				$has_overlay = isset( $a['use_background_color_gradient'] ) && 'on' === $a['use_background_color_gradient'];
				if ( $hero && $white && $light && empty( $a['background_image'] ) ) {
					// Hero: a photo of the page behind the title, with a dark layer so the white text reads well.
					$a['background_image']    = self::dc( 'section_4_right_image' );
					$a['background_enable_image'] = 'on';
					$a['background_size']     = 'cover';
					$a['background_position'] = 'center';
					$a['_dynamic_attributes'] = trim( ( isset( $a['_dynamic_attributes'] ) ? $a['_dynamic_attributes'] . ',' : '' ) . 'background_image', ',' );
					unset( $a['background_blend'] );
				}
				if ( $hero && $white && ( $light || ! empty( $a['background_image'] ) ) && ! $has_overlay ) {
					$a['use_background_color_gradient']            = 'on';
					$a['background_color_gradient_stops']          = 'rgba(0,0,0,0.55) 0%|rgba(0,0,0,0.6) 100%';
					$a['background_color_gradient_overlays_image'] = 'on';
				} elseif ( $white && $light && ! $has_overlay && empty( $a['background_image'] ) ) {
					// White text on a light brand color: a darker shade of the same color.
					$a['background_color'] = self::darker( $bg );
				}
				return '[et_pb_section' . self::build_attrs( $a ) . ']' . $inner . '[/et_pb_section]';
			},
			$content
		);

		return preg_replace_callback(
			'/\[(et_pb_accordion|et_pb_accordion_item|et_pb_toggle|dnxte_dual_button)((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*(\/?)\]/',
			static function ( $m ) {
				$a = self::parse_attrs( $m[2] );
				if ( 'dnxte_dual_button' === $m[1] ) {
					// Both buttons on every screen; the plugin puts them in one row that wraps.
					foreach ( array( 'one', 'two' ) as $k ) {
						foreach ( array( '', '_tablet', '_phone' ) as $sz ) {
							$key = 'custom_css_button_' . $k . '_link' . $sz;
							if ( isset( $a[ $key ] ) ) {
								// Hidden on phones only = shown again; hidden everywhere = left as designed.
								$pat       = '' === $sz ? '/margin-right\s*:\s*[^;]*\s*;?/i' : '/(display\s*:\s*none|margin-right\s*:\s*[^;]*)\s*;?/i';
								$a[ $key ] = trim( preg_replace( $pat, '', $a[ $key ] ) );
								if ( '' === $a[ $key ] ) {
									unset( $a[ $key ] );
								}
							}
						}
						if ( empty( $a[ 'custom_css_button_' . $k . '_link_tablet' ] ) && empty( $a[ 'custom_css_button_' . $k . '_link_phone' ] ) ) {
							unset( $a[ 'custom_css_button_' . $k . '_link_last_edited' ] );
						}
					}
				} else {
					// FAQ titles: readable colors and the same size on every screen.
					foreach ( array( 'open', 'closed' ) as $st ) {
						$tc = isset( $a[ $st . '_toggle_text_color' ] ) ? $a[ $st . '_toggle_text_color' ] : '';
						$bk = isset( $a[ $st . '_toggle_background_color' ] ) ? $a[ $st . '_toggle_background_color' ] : '';
						if ( $tc && $bk && self::is_white( $tc ) && self::light( $bk ) > 0.42 ) {
							$a[ $st . '_toggle_background_color' ] = self::darker( $bk );
						}
					}
					if ( 'et_pb_accordion_item' !== $m[1] ) {
						$size = isset( $a['toggle_font_size'] ) && (float) $a['toggle_font_size'] > 0 ? (float) $a['toggle_font_size'] : 20;
						$a['toggle_font_size']             = self::px( $size );
						$a['toggle_font_size_tablet']      = self::px( max( 16, min( $size, 19 ) ) );
						$a['toggle_font_size_phone']       = self::px( max( 15, min( $size, 17 ) ) );
						$a['toggle_font_size_last_edited'] = 'on|phone';
						foreach ( array( 'closed_toggle_font_size', 'closed_toggle_font_size_tablet', 'closed_toggle_font_size_phone', 'closed_toggle_font_size_last_edited' ) as $k ) {
							unset( $a[ $k ] ); // Closed titles use the same size as open ones.
						}
						$a['toggle_line_height']        = '1.35em';
						$a['closed_toggle_line_height'] = '1.35em';
					} else {
						foreach ( array( 'toggle_font_size', 'toggle_font_size_tablet', 'toggle_font_size_phone', 'toggle_font_size_last_edited', 'closed_toggle_font_size', 'closed_toggle_font_size_tablet', 'closed_toggle_font_size_phone', 'closed_toggle_font_size_last_edited' ) as $k ) {
							unset( $a[ $k ] ); // Items follow the accordion, so all questions look the same.
						}
					}
				}
				return '[' . $m[1] . self::build_attrs( $a ) . ( $m[3] ? ' /' : '' ) . ']';
			},
			$content
		);
	}

	/** The Divi body layout used by the SEO Boster Theme Builder template. */
	public static function divi_body_id() {
		$tpl = (int) SEOB_AP_Design::settings()['divi_template'];
		if ( ! $tpl || 'et_template' !== get_post_type( $tpl ) ) {
			return 0;
		}
		$body = (int) get_post_meta( $tpl, '_et_body_layout_id', true );
		return $body && 'et_body_layout' === get_post_type( $body ) ? $body : 0;
	}

	/* ==================================================================
	 * Elementor
	 * ================================================================== */

	private static function el_size( $v, $unit = 'px' ) {
		return array( 'unit' => $unit, 'size' => (float) $v, 'sizes' => array() );
	}

	private static function el_type( array &$s, $prefix, array $st, $fonts_only = false ) {
		$x = $st['desktop'];
		if ( ! $x ) {
			return;
		}
		$s[ $prefix . 'typography' ] = 'custom';
		$fam = self::family( isset( $x['fontFamily'] ) ? $x['fontFamily'] : '' );
		if ( $fam ) {
			$s[ $prefix . 'font_family' ] = $fam;
		}
		$w = self::weight( isset( $x['fontWeight'] ) ? $x['fontWeight'] : '' );
		if ( $w ) {
			$s[ $prefix . 'font_weight' ] = $w;
		}
		if ( $fonts_only ) {
			return;
		}
		if ( ! empty( $x['fontSize'] ) ) {
			$s[ $prefix . 'font_size' ] = self::el_size( $x['fontSize'] );
		}
		if ( ! empty( $st['tablet']['fontSize'] ) ) {
			$s[ $prefix . 'font_size_tablet' ] = self::el_size( $st['tablet']['fontSize'] );
		}
		if ( ! empty( $st['phone']['fontSize'] ) ) {
			$s[ $prefix . 'font_size_mobile' ] = self::el_size( $st['phone']['fontSize'] );
		}
		if ( ! empty( $x['lineHeight'] ) && is_numeric( $x['lineHeight'] ) ) {
			$s[ $prefix . 'line_height' ] = self::el_size( $x['lineHeight'], 'em' );
		}
		if ( ! empty( $x['letterSpacing'] ) && 'normal' !== $x['letterSpacing'] ) {
			$s[ $prefix . 'letter_spacing' ] = self::el_size( $x['letterSpacing'] );
		}
		if ( ! empty( $x['textTransform'] ) ) {
			$s[ $prefix . 'text_transform' ] = $x['textTransform'];
		}
		if ( ! empty( $x['fontStyle'] ) && 'normal' !== $x['fontStyle'] ) {
			$s[ $prefix . 'font_style' ] = $x['fontStyle'];
		}
	}

	private static function el_button( array &$s, array $st, array $hover ) {
		$x = $st['desktop'];
		if ( ! $x ) {
			return;
		}
		self::el_type( $s, 'typography_', $st );
		$map = array(
			'color'           => array( 'button_text_color', 'hover_color' ),
			'backgroundColor' => array( 'background_color', 'button_background_hover_color' ),
			'borderColor'     => array( 'border_color', 'button_hover_border_color' ),
		);
		foreach ( $map as $prop => $keys ) {
			$c = self::color( isset( $x[ $prop ] ) ? $x[ $prop ] : '' );
			if ( $c ) {
				$s[ $keys[0] ] = $c;
			}
			$h = self::color( isset( $hover[ $prop ] ) ? $hover[ $prop ] : '' );
			$s[ $keys[1] ] = $h ? $h : ( $c ? $c : ( isset( $s[ $keys[1] ] ) ? $s[ $keys[1] ] : '' ) );
		}
		if ( isset( $s['background_color'] ) ) {
			$s['background_background'] = 'classic';
		}
		if ( isset( $x['borderWidth'] ) && ( ! isset( $x['borderStyle'] ) || 'none' !== $x['borderStyle'] ) && (float) $x['borderWidth'] > 0 ) {
			$w                     = (string) (float) $x['borderWidth'];
			$s['border_border']    = isset( $x['borderStyle'] ) ? $x['borderStyle'] : 'solid';
			$s['border_width']     = array( 'unit' => 'px', 'top' => $w, 'right' => $w, 'bottom' => $w, 'left' => $w, 'isLinked' => true );
		} else {
			$s['border_border'] = 'none';
		}
		if ( isset( $x['borderRadius'] ) ) {
			$r                  = (string) (float) $x['borderRadius'];
			$s['border_radius'] = array( 'unit' => 'px', 'top' => $r, 'right' => $r, 'bottom' => $r, 'left' => $r, 'isLinked' => true );
		}
		$s['text_padding'] = array(
			'unit'     => 'px',
			'top'      => (string) (float) ( isset( $x['paddingTop'] ) ? $x['paddingTop'] : 0 ),
			'right'    => (string) (float) ( isset( $x['paddingRight'] ) ? $x['paddingRight'] : 0 ),
			'bottom'   => (string) (float) ( isset( $x['paddingBottom'] ) ? $x['paddingBottom'] : 0 ),
			'left'     => (string) (float) ( isset( $x['paddingLeft'] ) ? $x['paddingLeft'] : 0 ),
			'isLinked' => false,
		);
		if ( ! empty( $x['boxShadow'] ) && 'none' !== $x['boxShadow'] ) {
			$css               = isset( $s['custom_css'] ) ? preg_replace( '/selector \.elementor-button\{box-shadow:[^}]*\}/', '', (string) $s['custom_css'] ) : '';
			$s['custom_css']   = trim( $css . ' selector .elementor-button{box-shadow:' . $x['boxShadow'] . '}' );
		}
	}

	private static function el_walk( array $els, array $h, array $hov1, array $hov2, &$btn_n ) {
		foreach ( $els as $i => $e ) {
			if ( isset( $e['elType'] ) && 'widget' === $e['elType'] && isset( $e['settings'] ) && is_array( $e['settings'] ) ) {
				$s = $e['settings'];
				switch ( $e['widgetType'] ) {
					case 'heading':
						$tag = isset( $s['header_size'] ) ? $s['header_size'] : 'h2';
						$key = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $tag : ( in_array( $tag, array( 'h5', 'h6' ), true ) ? 'h4' : 'p' );
						self::el_type( $s, 'typography_', $h[ $key ] );
						break;
					case 'text-editor':
						self::el_type( $s, 'typography_', $h['p'] );
						break;
					case 'button':
						// Buttons alternate main / second style the way they sit side by side in the layout.
						$second = 1 === ( $btn_n % 2 ) && ! empty( $h['b2']['desktop'] );
						self::el_button( $s, $second ? $h['b2'] : $h['b1'], $second ? $hov2 : $hov1 );
						++$btn_n;
						break;
					case 'nested-accordion':
					case 'accordion':
					case 'toggle':
						$t = $h['h4']['desktop'] ? $h['h4'] : $h['h2'];
						self::el_type( $s, 'title_typography_', $t, ! $h['h4']['desktop'] );
						self::el_type( $s, 'content_typography_', $h['p'] );
						break;
				}
				$els[ $i ]['settings'] = $s;
			}
			if ( ! empty( $e['elements'] ) && is_array( $e['elements'] ) ) {
				$els[ $i ]['elements'] = self::el_walk( $e['elements'], $h, $hov1, $hov2, $btn_n );
			}
		}
		return $els;
	}

	public static function elementor( array $els, array $d ) {
		$h     = array(
			'h1' => self::sizes( $d, 'h1' ),
			'h2' => self::sizes( $d, 'h2' ),
			'h3' => self::sizes( $d, 'h3' ),
			'h4' => self::sizes( $d, 'h4' ),
			'p'  => self::sizes( $d, 'p' ),
			'b1' => self::sizes( $d, 'btn' ),
			'b2' => self::sizes( $d, 'btn2' ),
		);
		$hov1  = isset( $d['desktop']['btn_hover'] ) ? (array) $d['desktop']['btn_hover'] : array();
		$hov2  = isset( $d['desktop']['btn2_hover'] ) ? (array) $d['desktop']['btn2_hover'] : array();
		$btn_n = 0;
		return self::el_walk( $els, $h, $hov1, $hov2, $btn_n );
	}

	/* ==================================================================
	 * Apply to the installed template
	 * ================================================================== */

	/**
	 * Copy the measured site look into the Theme Builder template.
	 *
	 * @return string divi|elementor|'' (nothing to do).
	 */
	public static function apply() {
		$st = SEOB_AP_Style::get();
		$d  = $st['data'];
		if ( empty( $d['desktop'] ) ) {
			return '';
		}
		$mode = SEOB_AP_Design::settings()['mode'];
		$done = '';
		if ( 'divi' === $mode ) {
			$body = self::divi_body_id();
			if ( $body ) {
				$post = get_post( $body );
				$new  = self::divi_fix( self::divi( (string) $post->post_content, $d ) );
				if ( $new !== $post->post_content ) {
					wp_update_post( array( 'ID' => $body, 'post_content' => wp_slash( $new ) ) );
				}
				if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
					ET_Core_PageResource::remove_static_resources( 'all', 'all' );
				}
				$done = 'divi';
			}
		} elseif ( 'elementor' === $mode ) {
			$id = (int) SEOB_AP_Design::settings()['el_template'];
			if ( $id && get_post( $id ) ) {
				$els = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
				if ( is_array( $els ) ) {
					update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( self::elementor( $els, $d ) ) ) );
					delete_post_meta( $id, '_elementor_css' );
					if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
						\Elementor\Plugin::$instance->files_manager->clear_cache();
					}
					$done = 'elementor';
				}
			}
		}
		if ( $done ) {
			SEOB_AP_Style::save(
				array(
					'baked'      => $done,
					'baked_time' => time(),
					'fix_v'      => self::FIX_V,
				)
			);
		}
		return $done;
	}

	/** Template made by an older version: run only the layout fixes once (fonts and Theme Builder edits stay). */
	public static function maybe_fix() {
		$st = SEOB_AP_Style::get();
		if ( (int) ( isset( $st['fix_v'] ) ? $st['fix_v'] : 0 ) >= self::FIX_V || 'divi' !== SEOB_AP_Design::settings()['mode'] ) {
			return;
		}
		$body = self::divi_body_id();
		if ( $body ) {
			$post = get_post( $body );
			$new  = self::divi_fix( (string) $post->post_content );
			if ( $new !== $post->post_content ) {
				wp_update_post( array( 'ID' => $body, 'post_content' => wp_slash( $new ) ) );
				if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
					ET_Core_PageResource::remove_static_resources( 'all', 'all' );
				}
			}
		}
		SEOB_AP_Style::save( array( 'fix_v' => self::FIX_V ) );
	}

	/** True when the Theme Builder template holds the site look (no forced CSS needed). */
	public static function is_baked() {
		$st   = SEOB_AP_Style::get();
		$mode = SEOB_AP_Design::settings()['mode'];
		return ! empty( $st['baked'] ) && $st['baked'] === $mode;
	}
}
