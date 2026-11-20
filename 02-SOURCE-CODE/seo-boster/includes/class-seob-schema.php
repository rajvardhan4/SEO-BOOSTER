<?php
/**
 * Rich JSON-LD Schema Engine for SEO.
 *
 * Automatically injects valid schema.org structured data into wp_head:
 * - LocalBusiness / Organization schema (site-wide or localized)
 * - Service schema (on Service and Area pages)
 * - FAQPage schema (reads ACF FAQ fields and Divi accordions)
 * - BreadcrumbList schema (hierarchical navigation)
 * - WebPage / AboutPage / ContactPage schema
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Schema {

	const OPTION_KEY = 'seob_schema_settings';

	/** @var SEOB_Schema|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {}

	public function hooks() {
		add_action( 'wp_head', array( $this, 'render_schema' ), 2 );
	}

	/**
	 * Default schema configuration settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'enabled'            => 1,
			'business_type'      => 'LocalBusiness',
			'business_name'      => get_bloginfo( 'name' ),
			'telephone'          => '',
			'street_address'     => '',
			'city'               => '',
			'state'              => '',
			'postal_code'        => '',
			'country'            => 'US',
			'logo_url'           => '',
			'price_range'        => '$$',
			'opening_hours'      => 'Mo-Fr 08:00-18:00',
			'enable_local_biz'   => 1,
			'enable_service'     => 1,
			'enable_faq'         => 1,
			'enable_breadcrumbs' => 1,
			'enable_webpage'     => 1,
		);
	}

	/**
	 * Retrieve saved schema settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Save schema settings.
	 *
	 * @param array $settings Input settings.
	 * @return bool
	 */
	public static function save_settings( array $settings ) {
		$clean = array(
			'enabled'            => ! empty( $settings['enabled'] ) ? 1 : 0,
			'business_type'      => sanitize_text_field( isset( $settings['business_type'] ) ? $settings['business_type'] : 'LocalBusiness' ),
			'business_name'      => sanitize_text_field( isset( $settings['business_name'] ) ? $settings['business_name'] : get_bloginfo( 'name' ) ),
			'telephone'          => sanitize_text_field( isset( $settings['telephone'] ) ? $settings['telephone'] : '' ),
			'street_address'     => sanitize_text_field( isset( $settings['street_address'] ) ? $settings['street_address'] : '' ),
			'city'               => sanitize_text_field( isset( $settings['city'] ) ? $settings['city'] : '' ),
			'state'              => sanitize_text_field( isset( $settings['state'] ) ? $settings['state'] : '' ),
			'postal_code'        => sanitize_text_field( isset( $settings['postal_code'] ) ? $settings['postal_code'] : '' ),
			'country'            => sanitize_text_field( isset( $settings['country'] ) ? $settings['country'] : 'US' ),
			'logo_url'           => esc_url_raw( isset( $settings['logo_url'] ) ? $settings['logo_url'] : '' ),
			'price_range'        => sanitize_text_field( isset( $settings['price_range'] ) ? $settings['price_range'] : '$$' ),
			'opening_hours'      => sanitize_text_field( isset( $settings['opening_hours'] ) ? $settings['opening_hours'] : 'Mo-Fr 08:00-18:00' ),
			'enable_local_biz'   => ! empty( $settings['enable_local_biz'] ) ? 1 : 0,
			'enable_service'     => ! empty( $settings['enable_service'] ) ? 1 : 0,
			'enable_faq'         => ! empty( $settings['enable_faq'] ) ? 1 : 0,
			'enable_breadcrumbs' => ! empty( $settings['enable_breadcrumbs'] ) ? 1 : 0,
			'enable_webpage'     => ! empty( $settings['enable_webpage'] ) ? 1 : 0,
		);
		return update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * Supported schema.org business types.
	 *
	 * @return array
	 */
	public static function business_types() {
		return array(
			'LocalBusiness'                => __( 'Local Business (General)', 'seo-boster' ),
			'ProfessionalService'          => __( 'Professional Service', 'seo-boster' ),
			'HomeAndConstructionBusiness'  => __( 'Home & Construction Business', 'seo-boster' ),
			'Plumber'                      => __( 'Plumbing Service', 'seo-boster' ),
			'RoofingContractor'            => __( 'Roofing Contractor', 'seo-boster' ),
			'GeneralContractor'            => __( 'General Contractor', 'seo-boster' ),
			'Electrician'                  => __( 'Electrician', 'seo-boster' ),
			'HVACBusiness'                 => __( 'HVAC / Air Conditioning', 'seo-boster' ),
			'HealthAndBeautyBusiness'      => __( 'Salon / Health & Beauty', 'seo-boster' ),
			'LegalService'                 => __( 'Legal Service / Attorney', 'seo-boster' ),
			'MedicalBusiness'              => __( 'Medical / Dental Business', 'seo-boster' ),
			'AutomotiveBusiness'           => __( 'Automotive Business / Repair', 'seo-boster' ),
			'RealEstateAgent'              => __( 'Real Estate Agency', 'seo-boster' ),
			'CleaningService'              => __( 'Cleaning / Carpet Service', 'seo-boster' ),
			'Organization'                 => __( 'Organization (Non-Local / Nationwide)', 'seo-boster' ),
		);
	}

	/**
	 * Main output function hooked to wp_head.
	 */
	public function render_schema() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$post_id   = get_queried_object_id();
		$post      = $post_id ? get_post( $post_id ) : null;
		$graph     = array();

		// 1. Organization / LocalBusiness Schema
		if ( ! empty( $settings['enable_local_biz'] ) ) {
			$local_biz = $this->build_local_business( $settings, $post );
			if ( $local_biz ) {
				$graph[] = $local_biz;
			}
		}

		// 2. WebPage Schema
		if ( ! empty( $settings['enable_webpage'] ) && $post ) {
			$webpage = $this->build_webpage( $settings, $post );
			if ( $webpage ) {
				$graph[] = $webpage;
			}
		}

		// 3. Service Schema (if on an Area Page or a Service page)
		if ( ! empty( $settings['enable_service'] ) && $post ) {
			$service = $this->build_service( $settings, $post );
			if ( $service ) {
				$graph[] = $service;
			}
		}

		// 4. BreadcrumbList Schema
		if ( ! empty( $settings['enable_breadcrumbs'] ) && $post ) {
			$breadcrumbs = $this->build_breadcrumbs( $post );
			if ( $breadcrumbs ) {
				$graph[] = $breadcrumbs;
			}
		}

		// 5. FAQPage Schema
		if ( ! empty( $settings['enable_faq'] ) && $post ) {
			$faq = $this->build_faq( $post );
			if ( $faq ) {
				$graph[] = $faq;
			}
		}

		$graph = apply_filters( 'seo_boster_schema_graph', $graph, $post, $settings );
		if ( empty( $graph ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n<!-- SEO Boster Rich Schema v" . esc_attr( SEOB_VERSION ) . " -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "</script>\n<!-- /SEO Boster Rich Schema -->\n";
	}

	/**
	 * Build LocalBusiness / Organization Schema.
	 *
	 * @param array   $settings Schema settings.
	 * @param WP_Post $post     Current post.
	 * @return array
	 */
	public function build_local_business( array $settings, $post = null ) {
		$type = ! empty( $settings['business_type'] ) ? $settings['business_type'] : 'LocalBusiness';
		$name = ! empty( $settings['business_name'] ) ? $settings['business_name'] : get_bloginfo( 'name' );
		$url  = home_url( '/' );

		// Check if Area Page has localized phone/city
		$phone = ! empty( $settings['telephone'] ) ? $settings['telephone'] : '';
		$city  = ! empty( $settings['city'] ) ? $settings['city'] : '';
		$state = ! empty( $settings['state'] ) ? $settings['state'] : '';

		if ( $post && 'areas-we-serve' === $post->post_type ) {
			$loc = get_post_meta( $post->ID, 'location', true );
			if ( $loc ) {
				$parts = explode( ',', (string) $loc );
				$city  = trim( $parts[0] );
				if ( isset( $parts[1] ) ) {
					$state = trim( $parts[1] );
				}
			}
			$p_phone = get_post_meta( $post->ID, 'phone_number', true );
			if ( $p_phone ) {
				$phone = $p_phone;
			}
		}

		$schema = array(
			'@type' => $type,
			'@id'   => $url . '#localbusiness',
			'name'  => $name,
			'url'   => $url,
		);

		if ( ! empty( $phone ) ) {
			$schema['telephone'] = $phone;
		}

		$logo = ! empty( $settings['logo_url'] ) ? $settings['logo_url'] : '';
		if ( empty( $logo ) && function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
			$logo_id = get_theme_mod( 'custom_logo' );
			$logo    = wp_get_attachment_image_url( $logo_id, 'full' );
		}
		if ( ! empty( $logo ) ) {
			$schema['logo']  = $logo;
			$schema['image'] = $logo;
		}

		if ( ! empty( $settings['price_range'] ) ) {
			$schema['priceRange'] = $settings['price_range'];
		}

		if ( ! empty( $city ) || ! empty( $settings['street_address'] ) ) {
			$address = array(
				'@type' => 'PostalAddress',
			);
			if ( ! empty( $settings['street_address'] ) ) {
				$address['streetAddress'] = $settings['street_address'];
			}
			if ( ! empty( $city ) ) {
				$address['addressLocality'] = $city;
			}
			if ( ! empty( $state ) ) {
				$address['addressRegion'] = $state;
			}
			if ( ! empty( $settings['postal_code'] ) ) {
				$address['postalCode'] = $settings['postal_code'];
			}
			if ( ! empty( $settings['country'] ) ) {
				$address['addressCountry'] = $settings['country'];
			}
			$schema['address'] = $address;
		}

		if ( ! empty( $city ) ) {
			$schema['areaServed'] = array(
				'@type' => 'City',
				'name'  => $city . ( $state ? ', ' . $state : '' ),
			);
		}

		if ( ! empty( $settings['opening_hours'] ) ) {
			$schema['openingHours'] = $settings['opening_hours'];
		}

		return $schema;
	}

	/**
	 * Build WebPage Schema.
	 *
	 * @param array   $settings Schema settings.
	 * @param WP_Post $post     Current post.
	 * @return array
	 */
	public function build_webpage( array $settings, $post ) {
		$type = 'WebPage';
		$slug = $post->post_name;
		if ( is_front_page() ) {
			$type = 'WebSite';
		} elseif ( false !== strpos( $slug, 'about' ) ) {
			$type = 'AboutPage';
		} elseif ( false !== strpos( $slug, 'contact' ) ) {
			$type = 'ContactPage';
		}

		$title = get_post_meta( $post->ID, 'rank_math_title', true );
		if ( empty( $title ) ) {
			$title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		}
		if ( empty( $title ) ) {
			$title = get_the_title( $post );
		}

		$desc = get_post_meta( $post->ID, 'rank_math_description', true );
		if ( empty( $desc ) ) {
			$desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		}
		if ( empty( $desc ) ) {
			$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
		}

		$schema = array(
			'@type'       => $type,
			'@id'         => get_permalink( $post ) . '#' . strtolower( $type ),
			'url'         => get_permalink( $post ),
			'name'        => $title,
			'description' => $desc,
			'isPartOf'    => array(
				'@id' => home_url( '/' ) . '#localbusiness',
			),
		);

		if ( has_post_thumbnail( $post ) ) {
			$schema['primaryImageOfPage'] = get_the_post_thumbnail_url( $post, 'full' );
		}

		return $schema;
	}

	/**
	 * Build Service Schema for Area pages or specific Service pages.
	 *
	 * @param array   $settings Schema settings.
	 * @param WP_Post $post     Current post.
	 * @return array|null
	 */
	public function build_service( array $settings, $post ) {
		$is_area_page = ( 'areas-we-serve' === $post->post_type );
		$is_service   = $is_area_page || ( false !== strpos( $post->post_name, 'service' ) );

		if ( ! $is_service && ! is_singular( 'service' ) ) {
			return null;
		}

		$service_name = '';
		$location     = '';
		if ( $is_area_page ) {
			$service_name = get_post_meta( $post->ID, '_seob_ap_service', true );
			$location     = get_post_meta( $post->ID, 'location', true );
		}

		if ( empty( $service_name ) ) {
			$service_name = get_the_title( $post );
		}

		$desc = get_post_meta( $post->ID, 'rank_math_description', true );
		if ( empty( $desc ) ) {
			$desc = get_post_meta( $post->ID, 'section_2_description', true );
		}
		if ( empty( $desc ) ) {
			$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
		}

		$schema = array(
			'@type'        => 'Service',
			'@id'          => get_permalink( $post ) . '#service',
			'name'         => $service_name . ( $location ? ' in ' . $location : '' ),
			'serviceType'  => $service_name,
			'description'  => $desc,
			'url'          => get_permalink( $post ),
			'provider'     => array(
				'@id' => home_url( '/' ) . '#localbusiness',
			),
		);

		if ( ! empty( $location ) ) {
			$schema['areaServed'] = array(
				'@type' => 'AdministrativeArea',
				'name'  => $location,
			);
		}

		return $schema;
	}

	/**
	 * Build BreadcrumbList Schema.
	 *
	 * @param WP_Post $post Current post.
	 * @return array|null
	 */
	public function build_breadcrumbs( $post ) {
		if ( is_front_page() ) {
			return null;
		}

		$items = array();
		$pos   = 1;

		// 1. Home
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => __( 'Home', 'seo-boster' ),
			'item'     => home_url( '/' ),
		);

		if ( 'areas-we-serve' === $post->post_type ) {
			// 2. Hub / Areas We Serve
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => __( 'Areas We Serve', 'seo-boster' ),
				'item'     => home_url( '/service-areas/' ),
			);

			// 3. Location
			$loc = get_post_meta( $post->ID, 'location', true );
			if ( $loc ) {
				$parts = explode( ',', (string) $loc );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => trim( $parts[0] ),
					'item'     => home_url( '/service-areas/#' . sanitize_title( $loc ) ),
				);
			}

			// 4. Current Page
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $post ),
				'item'     => get_permalink( $post ),
			);
		} else {
			// Hierarchical pages
			$ancestors = get_post_ancestors( $post );
			if ( ! empty( $ancestors ) ) {
				$ancestors = array_reverse( $ancestors );
				foreach ( $ancestors as $ancestor_id ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => get_the_title( $ancestor_id ),
						'item'     => get_permalink( $ancestor_id ),
					);
				}
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $post ),
				'item'     => get_permalink( $post ),
			);
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post ) . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Build FAQPage Schema from ACF fields or Divi accordion content.
	 *
	 * @param WP_Post $post Current post.
	 * @return array|null
	 */
	public function build_faq( $post ) {
		$faqs = array();

		// Priority 1: Check ACF FAQ fields (used on Area Pages)
		for ( $i = 1; $i <= 10; $i++ ) {
			$q = get_post_meta( $post->ID, "faq_{$i}_heading", true );
			$a = get_post_meta( $post->ID, "faq_{$i}_description", true );
			if ( ! empty( $q ) && ! empty( $a ) ) {
				$faqs[] = array(
					'q' => trim( wp_strip_all_tags( (string) $q ) ),
					'a' => trim( wp_strip_all_tags( (string) $a ) ),
				);
			}
		}

		// Priority 2: If none from ACF, check Divi accordion shortcodes in post content
		if ( empty( $faqs ) && ! empty( $post->post_content ) ) {
			if ( preg_match_all( '/\[et_pb_accordion_item\s+[^\]]*title="([^"]+)"[^\]]*\](.*?)\[\/et_pb_accordion_item\]/is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					$q = trim( wp_strip_all_tags( $m[1] ) );
					$a = trim( wp_strip_all_tags( $m[2] ) );
					if ( '' !== $q && '' !== $a ) {
						$faqs[] = array( 'q' => $q, 'a' => $a );
					}
				}
			}
		}

		if ( empty( $faqs ) ) {
			return null;
		}

		$entities = array();
		foreach ( $faqs as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['a'],
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post ) . '#faq',
			'mainEntity' => $entities,
		);
	}
}
