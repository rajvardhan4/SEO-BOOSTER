<?php
/**
 * Match the site's own look on Area pages.
 *
 * The admin screen opens the homepage in a hidden frame at desktop, tablet and phone width and
 * measures the real headings, body text and buttons (font, size, weight, line height, letter
 * spacing, case, corner radius, padding). Area pages then use exactly those values, so they look
 * like the rest of the site on every screen size, whatever theme or builder made the site.
 *
 * Also makes the Areas We Serve menu easy to use on phones (Divi shows every sub item open).
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Style {

	const OPTION = 'seob_ap_site_style';
	const TAGS   = array( 'h1', 'h2', 'h3', 'h4', 'p', 'btn', 'btn_hover', 'btn2', 'btn2_hover' );
	const DATA_V = 3; // Bump when the measuring script reads more, so sites measure again.
	const SIZES  = array( 'desktop', 'tablet', 'phone' );

	public function hooks() {
		add_action( 'wp_ajax_seob_ap_style', array( __CLASS__, 'ajax_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'front_css' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'menu_script' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_bake' ) );
	}

	public static function get() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		if ( empty( $s['v'] ) ) {
			// 1.8.1: buttons are a full copy of the site's buttons by default.
			$s['buttons'] = 'all';
			$s['v']       = 2;
		}
		return wp_parse_args(
			$s,
			array(
				'on'      => 1,
				'v'       => 2,
				'dv'      => 0,
				'baked'   => '', // divi / elementor: the site look was written into that Theme Builder template.
				'buttons' => 'all', // all = exact copy of the site's buttons (colors, hover); shape = font and shape only.
				'time'    => 0,
				'data'    => array(),
				'error'   => '',
			)
		);
	}

	public static function save( array $s ) {
		update_option( self::OPTION, array_merge( self::get(), $s ), false );
	}

	/**
	 * Divi / Elementor sites: write the measured look into the Theme Builder template once,
	 * so the template shows the site design and all later edits happen in the Theme Builder.
	 */
	public static function maybe_bake() {
		if ( wp_doing_ajax() || ! SEOB_Security::can_use() ) {
			return;
		}
		SEOB_AP_Bake::maybe_fix();
		if ( ! self::has_data() || SEOB_AP_Bake::is_baked() ) {
			return;
		}
		if ( in_array( SEOB_AP_Design::settings()['mode'], array( 'divi', 'elementor' ), true ) ) {
			SEOB_AP_Bake::apply();
		}
	}

	public static function has_data() {
		$s = self::get();
		return ! empty( $s['data']['desktop'] );
	}

	/** Measure again when nothing was measured or it is older than 14 days. */
	public static function needs_detect() {
		$s = self::get();
		return ! self::has_data() || (int) $s['dv'] < self::DATA_V || ( time() - (int) $s['time'] ) > 14 * DAY_IN_SECONDS;
	}

	/* ==================================================================
	 * Admin: measure the homepage
	 * ================================================================== */

	public static function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'seo-boster-area-pages' ) ) {
			return;
		}
		wp_enqueue_script( 'seob-site-style', SEOB_URL . 'assets/js/site-style.js', array(), SEOB_VERSION, true );
		wp_localize_script(
			'seob-site-style',
			'seobStyle',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'seob_ap_style' ),
				'home'  => add_query_arg( 'seob_measure', '1', home_url( '/' ) ),
				'auto'  => self::needs_detect() ? 1 : 0,
				'i18n'  => array(
					'working' => __( 'Reading the site style...', 'seo-boster' ),
					'done'    => __( 'Site style updated. Area pages now use the same fonts, sizes and buttons as the site.', 'seo-boster' ),
					'fail'    => __( 'Could not read the homepage (it may block being shown in a frame). The layout keeps its own sizes.', 'seo-boster' ),
				),
			)
		);
	}

	private static function clean_value( $prop, $v ) {
		$v = trim( (string) $v );
		if ( '' === $v || strlen( $v ) > 200 ) {
			return '';
		}
		switch ( $prop ) {
			case 'fontFamily':
				return preg_match( '/^[A-Za-z0-9 ,"\'\-_.]+$/', $v ) ? $v : '';
			case 'fontWeight':
				return preg_match( '/^([1-9]00|normal|bold)$/', $v ) ? $v : '';
			case 'fontStyle':
				return in_array( $v, array( 'normal', 'italic', 'oblique' ), true ) ? $v : '';
			case 'textDecorationLine':
				return in_array( $v, array( 'none', 'underline', 'overline', 'line-through' ), true ) ? $v : '';
			case 'transitionDuration':
				return preg_match( '/^\d+(\.\d+)?m?s$/', $v ) ? $v : '';
			case 'backgroundImage':
				if ( 'none' === $v ) {
					return $v;
				}
				return ( preg_match( '/^(repeating-)?(linear|radial|conic)-gradient\([#a-zA-Z0-9\s,.%()\-]+\)$/', $v ) && false === stripos( $v, 'url' ) ) ? $v : '';
			case 'boxShadow':
				if ( 'none' === $v ) {
					return $v;
				}
				return ( preg_match( '/^[#a-zA-Z0-9\s,.%()\-]+$/', $v ) && false === stripos( $v, 'url' ) && false === stripos( $v, 'expression' ) ) ? $v : '';
			case 'textTransform':
				return in_array( $v, array( 'none', 'uppercase', 'lowercase', 'capitalize' ), true ) ? $v : '';
			case 'borderStyle':
				return in_array( $v, array( 'none', 'solid', 'dashed', 'dotted', 'double' ), true ) ? $v : '';
			case 'backgroundColor':
			case 'color':
			case 'borderColor':
				return preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d.\s,%]+\)|transparent)$/', $v ) ? $v : '';
			case 'lineHeight':
			case 'letterSpacing':
				return preg_match( '/^(normal|-?\d+(\.\d+)?(px|em|rem|%)?)$/', $v ) ? $v : '';
			default: // Sizes, radius, padding, border width.
				if ( preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%)$/', $v ) ) {
					return $v;
				}
				// "10px 20px" style shorthand (radius).
				return preg_match( '/^(\d+(\.\d+)?(px|%)\s?){1,4}$/', $v ) ? $v : '';
		}
	}

	private static function props() {
		return array( 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'textTransform', 'textDecorationLine', 'borderRadius', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderWidth', 'borderStyle', 'borderColor', 'backgroundColor', 'backgroundImage', 'color', 'boxShadow', 'transitionDuration' );
	}

	public static function ajax_save() {
		check_ajax_referer( 'seob_ap_style', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value is checked below.
		$raw  = isset( $_POST['style'] ) ? json_decode( (string) wp_unslash( $_POST['style'] ), true ) : null;
		$data = array();
		if ( is_array( $raw ) ) {
			foreach ( self::SIZES as $size ) {
				foreach ( self::TAGS as $tag ) {
					if ( empty( $raw[ $size ][ $tag ] ) || ! is_array( $raw[ $size ][ $tag ] ) ) {
						continue;
					}
					foreach ( self::props() as $prop ) {
						if ( isset( $raw[ $size ][ $tag ][ $prop ] ) ) {
							$v = self::clean_value( $prop, $raw[ $size ][ $tag ][ $prop ] );
							if ( '' !== $v ) {
								$data[ $size ][ $tag ][ $prop ] = $v;
							}
						}
					}
				}
			}
		}
		$fonts = array();
		if ( isset( $raw['desktop']['fonts'] ) && is_array( $raw['desktop']['fonts'] ) ) {
			foreach ( array_slice( $raw['desktop']['fonts'], 0, 6 ) as $url ) {
				$url  = esc_url_raw( (string) $url, array( 'https' ) );
				$host = (string) wp_parse_url( $url, PHP_URL_HOST );
				if ( $url && in_array( $host, array( 'fonts.googleapis.com', 'fonts.bunny.net', 'use.typekit.net' ), true ) ) {
					$fonts[] = $url;
				}
			}
		}
		$data['fonts'] = array_values( array_unique( $fonts ) );
		if ( empty( $data['desktop'] ) ) {
			self::save( array( 'error' => 'no data', 'time' => time() ) );
			wp_send_json_error( array( 'message' => 'no data' ) );
		}
		self::save(
			array(
				'data'  => $data,
				'dv'    => self::DATA_V,
				'time'  => time(),
				'error' => '',
			)
		);
		// Read on purpose ("Read the site again") or never copied yet: put the look into the Theme Builder template.
		if ( ! empty( $_POST['apply'] ) || ! SEOB_AP_Bake::is_baked() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
			SEOB_AP_Bake::apply();
		}
		wp_send_json_success( array( 'summary' => self::summary() ) );
	}

	/** Short readable list for the Design tab. */
	public static function summary() {
		$d   = self::get()['data'];
		$out = array();
		$fam = static function ( $f ) {
			$f = explode( ',', (string) $f );
			return trim( $f[0], " \"'" );
		};
		foreach ( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => __( 'Text', 'seo-boster' ), 'btn' => __( 'Buttons', 'seo-boster' ) ) as $tag => $label ) {
			if ( empty( $d['desktop'][ $tag ] ) ) {
				continue;
			}
			$x    = $d['desktop'][ $tag ];
			$line = $label . ': ' . $fam( isset( $x['fontFamily'] ) ? $x['fontFamily'] : '' ) . ' ' . ( isset( $x['fontSize'] ) ? $x['fontSize'] : '' );
			if ( isset( $d['phone'][ $tag ]['fontSize'] ) && $d['phone'][ $tag ]['fontSize'] !== ( isset( $x['fontSize'] ) ? $x['fontSize'] : '' ) ) {
				/* translators: %s size on phones */
				$line .= ' ' . sprintf( __( '(phone %s)', 'seo-boster' ), $d['phone'][ $tag ]['fontSize'] );
			}
			if ( 'btn' === $tag && isset( $x['borderRadius'] ) ) {
				/* translators: %s corner radius */
				$line .= ', ' . sprintf( __( 'corners %s', 'seo-boster' ), $x['borderRadius'] );
				if ( isset( $x['backgroundColor'] ) ) {
					/* translators: %s color */
					$line .= ', ' . sprintf( __( 'color %s', 'seo-boster' ), $x['backgroundColor'] );
				}
				$line .= ', ' . ( empty( $d['desktop']['btn_hover'] ) ? __( 'no hover change found', 'seo-boster' ) : __( 'hover effect copied', 'seo-boster' ) );
				if ( ! empty( $d['desktop']['btn2'] ) ) {
					$line .= ', ' . __( 'second button style copied', 'seo-boster' );
				}
			}
			$out[] = $line;
		}
		return $out;
	}

	/* ==================================================================
	 * Front end
	 * ================================================================== */

	public static function is_area_view() {
		if ( is_singular( SEOB_AP_Schema::POST_TYPE ) ) {
			return true;
		}
		$hub = (int) SEOB_AP_Nav::settings()['hub_id'];
		return $hub && is_page( $hub );
	}

	public static function body_class( $classes ) {
		$s = self::get();
		if ( $s['on'] && self::has_data() && self::is_area_view() ) {
			$classes[] = 'seob-match';
		}
		return $classes;
	}

	private static function rules( array $x, $tag, $buttons ) {
		$map = array(
			'fontFamily'    => 'font-family',
			'fontSize'      => 'font-size',
			'fontWeight'    => 'font-weight',
			'lineHeight'    => 'line-height',
			'letterSpacing' => 'letter-spacing',
			'textTransform' => 'text-transform',
		);
		$is_btn = 0 === strpos( $tag, 'btn' );
		if ( $is_btn ) {
			$map += array(
				'fontStyle'          => 'font-style',
				'textDecorationLine' => 'text-decoration-line',
				'borderRadius'       => 'border-radius',
				'paddingTop'         => 'padding-top',
				'paddingRight'       => 'padding-right',
				'paddingBottom'      => 'padding-bottom',
				'paddingLeft'        => 'padding-left',
				'borderWidth'        => 'border-width',
				'borderStyle'        => 'border-style',
			);
			if ( 'all' === $buttons ) {
				$map += array(
					'backgroundColor' => 'background-color',
					'backgroundImage' => 'background-image',
					'color'           => 'color',
					'borderColor'     => 'border-color',
					'boxShadow'       => 'box-shadow',
				);
			}
		}
		$css = '';
		foreach ( $map as $prop => $name ) {
			if ( isset( $x[ $prop ] ) && '' !== $x[ $prop ] ) {
				$css .= $name . ':' . $x[ $prop ] . ' !important;';
			}
		}
		if ( $is_btn && $css && false === strpos( $tag, 'hover' ) && ! empty( $x['transitionDuration'] ) && '0s' !== $x['transitionDuration'] ) {
			$css .= 'transition:all ' . $x['transitionDuration'] . ' ease !important;';
		}
		return $css;
	}

	/** The CSS that makes Area pages use the site's typography and buttons. */
	public static function css() {
		$s = self::get();
		$d = $s['data'];
		if ( empty( $d['desktop'] ) ) {
			return '';
		}
		// Content area of every page template the plugin can use: Divi Theme Builder body,
		// Elementor Pro single template, and the plugin's own template / hub page.
		// The two :not(#id) parts only raise priority: Divi and Elementor module styles often use
		// "body #page-container ..." with !important, which would otherwise win over the site copy.
		// When the look is already inside the Theme Builder template, only the plugin's own
		// template (hub page, built-in mode) is styled here, so Theme Builder edits always show.
		// Buttons are always an exact copy of the site's buttons (other button plugins load their own
		// default CSS after the Theme Builder styles, so template settings alone cannot guarantee it).
		$full  = 'body.seob-match:not(#seob-x1):not(#seob-x2) :is(.et-l--body,.elementor-location-single,.seob-area)';
		$scope = SEOB_AP_Bake::is_baked() ? 'body.seob-match:not(#seob-x1):not(#seob-x2) .seob-area' : $full;
		$btn   = ':is(.et_pb_button,.elementor-button,.seob-area-btn,.wp-block-button__link,[class*="dnxte"] a[href],[class*="dual_button"] a[href],[class*="dual-button"] a[href])';
		// Second style: the second button of a pair, or any button switched to it for contrast (seob-use2).
		$btn2  = ':is(.seob-use2,.seob-area-btn.is-ghost:not(.seob-use1),[class*="dual"] a[href]:is(.buttonTwo,[class*="two"],[class*="second"]):not(.seob-use1),[class*="dual"] a[href]:last-of-type:not(:first-of-type):not(.seob-use1),[class*="dual"] > :last-child:not(:first-child) a[href]:not(.seob-use1),.is-style-outline .wp-block-button__link:not(.seob-use1))';
		$hov   = static function ( $list ) {
			return ':is(' . substr( $list, 4, -1 ) . '):is(:hover,:focus-visible)';
		};
		$sel   = array(
			'h1'  => $scope . ' h1',
			'h2'  => $scope . ' h2',
			'h3'  => $scope . ' h3',
			'h4'  => $scope . ' :is(h4,h5,h6,.et_pb_toggle_title)',
			'p'   => $scope . ' :is(p,li,.et_pb_toggle_content)',
			'btn'        => $full . ' ' . $btn,
			'btn_hover'  => $full . ' ' . $hov( $btn ),
			// "html " in front: the second style must win over the main one.
			'btn2'       => 'html ' . $full . ' ' . $btn2,
			'btn2_hover' => 'html ' . $full . ' ' . $hov( $btn2 ),
		);
		$out   = '';
		$media = array(
			'desktop' => '',
			'tablet'  => '@media (max-width:980px)',
			'phone'   => '@media (max-width:767px)',
		);
		foreach ( $media as $size => $mq ) {
			if ( empty( $d[ $size ] ) ) {
				continue;
			}
			$block = '';
			foreach ( $sel as $tag => $selector ) {
				$x = isset( $d[ $size ][ $tag ] ) ? $d[ $size ][ $tag ] : array();
				if ( 'h4' === $tag && empty( $x ) && ! empty( $d[ $size ]['h3'] ) ) {
					// Smaller headings: same font as the others, theme size.
					$x = array_intersect_key( $d[ $size ]['h3'], array_flip( array( 'fontFamily', 'fontWeight', 'letterSpacing', 'textTransform' ) ) );
				}
				if ( 'desktop' !== $size ) {
					// Smaller screens only need what changes.
					$x = array_intersect_key( $x, array_flip( array( 'fontSize', 'lineHeight', 'letterSpacing', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft' ) ) );
				}
				$rules = self::rules( $x, $tag, $s['buttons'] );
				if ( $rules ) {
					$block .= $selector . '{' . $rules . '}';
				}
			}
			if ( $block ) {
				$out .= $mq ? $mq . '{' . $block . '}' : $block;
			}
		}
		if ( ! empty( $d['desktop']['btn_hover'] ) && 'all' === $s['buttons'] ) {
			// The site has its own hover, so the layout's darken / lift effect is not added on top.
			$out .= $full . ' .seob-area-btn:hover{filter:none !important;transform:none !important}';
		}
		// Button pairs: one neat row that wraps on small screens, both buttons the same height.
		$wrap = $full . ' .dnxt-button-wrapper';
		$out .= $wrap . '{display:flex !important;flex-wrap:wrap;align-items:center;gap:12px 16px}'
			. $wrap . '.dnxt_dual_button_alignment_center{justify-content:center}'
			. $wrap . '.dnxt_dual_button_alignment_right{justify-content:flex-end}'
			. $wrap . ' > a{margin:0 !important;white-space:nowrap;box-sizing:border-box;text-align:center}'
			. $full . ' :is(.et_pb_toggle_title,.elementor-tab-title){line-height:1.35 !important}';
		// Always good on small screens: nothing wider than the screen, buttons wrap, long words break.
		$out .= '@media (max-width:767px){' . $full . ' :is(h1,h2,h3){overflow-wrap:break-word}' . $full . ' img{max-width:100%;height:auto}' . $full . ' :is(.seob-area-actions,[class*="dual_button"],[class*="dnxte_dual"]){flex-wrap:wrap}'
			. $wrap . ' > a{white-space:normal}}'
			// Very narrow phones: buttons full width, one under the other.
			. '@media (max-width:400px){' . $wrap . ' > a{flex:1 1 100%}}';
		return $out;
	}

	/**
	 * Admin preview: the site's buttons as Area pages will show them (hover them to check).
	 */
	public static function preview() {
		$s = self::get();
		$d = isset( $s['data']['desktop'] ) ? $s['data']['desktop'] : array();
		if ( empty( $d['btn'] ) ) {
			return;
		}
		$css = '.seob-btn-preview{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:18px;background:#fff;border:1px solid #e2e4e7;border-radius:8px;margin:10px 0}.seob-btn-preview a{display:inline-block;text-decoration:none;cursor:pointer}';
		$map = array(
			'btn'        => '.seob-btn-preview .seob-pv-1',
			'btn_hover'  => '.seob-btn-preview .seob-pv-1:hover',
			'btn2'       => '.seob-btn-preview .seob-pv-2',
			'btn2_hover' => '.seob-btn-preview .seob-pv-2:hover',
		);
		foreach ( $map as $tag => $sel ) {
			$x = isset( $d[ $tag ] ) ? $d[ $tag ] : ( 'btn2' === $tag ? $d['btn'] : array() );
			$r = self::rules( $x, $tag, $s['buttons'] );
			if ( $r ) {
				$css .= $sel . '{' . $r . '}';
			}
		}
		foreach ( (array) ( isset( $s['data']['fonts'] ) ? $s['data']['fonts'] : array() ) as $i => $url ) {
			echo '<link rel="stylesheet" href="' . esc_url( $url ) . '" media="all">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
		echo '<style>' . wp_strip_all_tags( $css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values validated on save.
		echo '<p class="seob-help"><strong>' . esc_html__( 'Buttons on Area pages (move the mouse over them):', 'seo-boster' ) . '</strong></p>';
		echo '<div class="seob-btn-preview"><a class="seob-pv-1" href="#" onclick="return false">' . esc_html__( 'Call Now', 'seo-boster' ) . '</a><a class="seob-pv-2" href="#" onclick="return false">' . esc_html__( 'Contact Us', 'seo-boster' ) . '</a></div>';
	}

	public static function front_css() {
		$s = self::get();
		if ( ! $s['on'] || ! self::has_data() || ! self::is_area_view() ) {
			return;
		}
		foreach ( (array) ( isset( $s['data']['fonts'] ) ? $s['data']['fonts'] : array() ) as $i => $url ) {
			echo '<link rel="stylesheet" id="seob-site-font-' . (int) $i . '" href="' . esc_url( $url ) . '" media="all">' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- printed late on purpose, same files the homepage uses.
		}
		$css = self::css();
		if ( $css ) {
			echo '<style id="seob-site-style">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values validated on save, tags stripped.
		}		// Buttons that blend into their section (same color) switch to the site's other button
		// style, or to a white / dark version, so every button is always visible.
		?>
<script id="seob-btn-contrast">
(function(){
var SEL='.et-l--body :is(.et_pb_button,[class*="dnxte"] a[href],[class*="dual_button"] a[href]),.elementor-location-single .elementor-button,.seob-area .seob-area-btn';
function rgb(c){var m=String(c).match(/[\d.]+/g);return m?{r:+m[0],g:+m[1],b:+m[2],a:m.length>3?+m[3]:1}:null;}
function lum(c){var f=function(v){v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4);};return .2126*f(c.r)+.7152*f(c.g)+.0722*f(c.b);}
function ratio(a,b){var x=lum(a),y=lum(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
function back(el){for(var p=el.parentElement;p;p=p.parentElement){var cs=getComputedStyle(p);if(cs.backgroundImage&&cs.backgroundImage!=='none'){return {r:30,g:30,b:30,a:1};}var c=rgb(cs.backgroundColor);if(c&&c.a>.5){return c;}}return {r:255,g:255,b:255,a:1};}
function key(a){var cs=getComputedStyle(a),bg=rgb(cs.backgroundColor);if(bg&&bg.a>.5){return bg;}var bc=rgb(cs.borderTopColor);if(parseFloat(cs.borderTopWidth)>0&&bc&&bc.a>.3){return bc;}return rgb(cs.color);}
function ok(a,sec){return ratio(key(a),sec)>=1.7;}
function fix(){document.querySelectorAll(SEL).forEach(function(a){if(!a.offsetWidth){return;}var sec=back(a);if(ok(a,sec)){return;}var two=a.matches('.buttonTwo,[class*="two"],.is-ghost');a.classList.add(two?'seob-use1':'seob-use2');if(ok(a,sec)){return;}a.classList.remove('seob-use1','seob-use2');var dark=lum(sec)<.4,hex='rgb('+sec.r+','+sec.g+','+sec.b+')';a.style.setProperty('background-color',dark?'#fff':'#111','important');a.style.setProperty('background-image','none','important');a.style.setProperty('border-color',dark?'#fff':'#111','important');a.style.setProperty('color',dark?hex:'#fff','important');});}
if(document.readyState!=='loading'){fix();}else{document.addEventListener('DOMContentLoaded',fix);}
window.addEventListener('load',function(){fix();setTimeout(fix,1500);});
})();
</script>
		<?php
	}

	/**
	 * Areas We Serve menu.
	 * Desktop: locations in a clean list, each opens its services to the side (to the left when
	 * there is no room on the right), arrows point the way the list opens.
	 * Phones: Divi shows every sub item open, which makes a long list; items open with a tap instead.
	 */
	public static function menu_script() {
		if ( empty( SEOB_AP_Nav::settings()['items'] ) ) {
			return;
		}
		?>
<style id="seob-menu-css">
.et_mobile_menu li.seob-aws>ul.sub-menu{display:none!important}
.et_mobile_menu li.seob-aws.seob-open>ul.sub-menu{display:block!important}
.et_mobile_menu li.seob-aws.menu-item-has-children>a{position:relative;padding-right:44px!important}
.et_mobile_menu li.seob-aws.menu-item-has-children>a:after{content:""!important;display:block!important;visibility:visible!important;position:absolute;right:16px;top:50%;width:8px;height:8px;margin-top:-6px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .2s}
.et_mobile_menu li.seob-aws.seob-open>a:after{transform:rotate(-135deg);margin-top:-2px}
@media (min-width:981px){
li.seob-aws-top li.seob-aws{position:relative!important}
li.seob-aws-top li.seob-aws>ul.sub-menu{left:100%!important;right:auto!important;top:0;margin:0!important;transform:none}
li.seob-aws-top li.seob-aws.seob-flip>ul.sub-menu{left:auto!important;right:100%!important}
li.seob-aws-top>ul.sub-menu{max-height:none}
li.seob-aws-top>ul.sub-menu>li.menu-item-has-children>a{position:relative;padding-right:30px!important}
li.seob-aws-top>ul.sub-menu>li.menu-item-has-children>a:after{content:""!important;font-family:inherit!important;position:absolute!important;right:12px!important;left:auto!important;top:50%!important;width:7px!important;height:7px!important;margin-top:-4px!important;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg)!important;background:none!important;opacity:.7}
li.seob-aws-top>ul.sub-menu>li.seob-flip>a:after{transform:rotate(135deg)!important}
li.seob-aws-top li.seob-aws>ul.sub-menu a{white-space:normal}
}
</style>
<script id="seob-menu-js">
(function(){
function place(li){var ul=li.querySelector(':scope>ul.sub-menu');if(!ul||window.innerWidth<981){return;}var box=li.getBoundingClientRect(),w=ul.offsetWidth||260,pt=parseFloat(getComputedStyle(li.parentNode).paddingTop)||0;li.classList.toggle('seob-flip',box.right+w>document.documentElement.clientWidth-8&&box.left-w>=0);ul.style.top=(-pt)+'px';}
document.addEventListener('mouseover',function(e){var li=e.target.closest?e.target.closest('li.seob-aws-top > ul > li.menu-item-has-children'):null;if(li){place(li);}});
document.addEventListener('focusin',function(e){var li=e.target.closest?e.target.closest('li.seob-aws-top > ul > li.menu-item-has-children'):null;if(li){place(li);}});
document.addEventListener('click',function(e){var a=e.target.closest?e.target.closest('.et_mobile_menu li.seob-aws.menu-item-has-children > a'):null;if(!a){return;}var li=a.parentNode;if(!li.classList.contains('seob-open')){e.preventDefault();e.stopPropagation();li.classList.add('seob-open');}else if(!a.getAttribute('href')||a.getAttribute('href').charAt(0)==='#'){e.preventDefault();e.stopPropagation();li.classList.remove('seob-open');}},true);})();
</script>
		<?php
	}
}
