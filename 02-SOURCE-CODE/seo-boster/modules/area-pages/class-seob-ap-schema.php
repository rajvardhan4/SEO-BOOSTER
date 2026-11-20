<?php
/**
 * Areas We Serve: post type, location taxonomy and the page fields.
 *
 * Field names and ACF keys are the same as the team's existing ACF export,
 * so old pages, Divi layouts and Elementor layouts keep working.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Schema {

	const POST_TYPE = 'areas-we-serve';
	const TAXONOMY  = 'location';
	const GROUP_KEY = 'group_69206005b8377';

	/**
	 * Fields used by the Elementor layout that were missing from the ACF export.
	 * Keys match the ones the Elementor layout points to.
	 */
	public static function extra_fields() {
		return array(
			array( 'key' => 'field_6a21710141dc9', 'name' => 'section_5_heading', 'label' => 'Section 5 Heading', 'type' => 'text' ),
			array( 'key' => 'field_6a21711341dca', 'name' => 'section_5_description', 'label' => 'Section 5 Description', 'type' => 'wysiwyg' ),
			array( 'key' => 'field_6a2170a241dc7', 'name' => 'section_5_right_image', 'label' => 'Section 5 Right Image', 'type' => 'image', 'return_format' => 'array' ),
		);
	}

	/** Image fields, in the order shown on the Generate screen. */
	public static function image_fields() {
		return array(
			'section_2_left_image'       => __( 'Section 2 image', 'seo-boster' ),
			'section_3_right_image'      => __( 'Section 3 image', 'seo-boster' ),
			'section_4_right_image'      => __( 'Section 4 image', 'seo-boster' ),
			'section_5_same_image'       => __( 'Section 5 icon', 'seo-boster' ),
			'section_7_background_image' => __( 'Section 7 background', 'seo-boster' ),
		);
	}

	/**
	 * The team's ACF field group plus the missing Elementor fields.
	 *
	 * @return array|null
	 */
	public static function acf_group() {
		static $group = null;
		if ( null !== $group ) {
			return $group;
		}
		$file = SEOB_DIR . 'modules/area-pages/data/acf-export.json';
		$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$group = null;
		foreach ( (array) $json as $item ) {
			if ( is_array( $item ) && isset( $item['key'], $item['fields'] ) && self::GROUP_KEY === $item['key'] ) {
				$group = $item;
				break;
			}
		}
		if ( $group ) {
			$names = wp_list_pluck( $group['fields'], 'name' );
			foreach ( self::extra_fields() as $f ) {
				if ( ! in_array( $f['name'], $names, true ) ) {
					$group['fields'][] = $f;
				}
			}
		}
		return $group;
	}

	/** name => field array. */
	public static function fields() {
		$out   = array();
		$group = self::acf_group();
		foreach ( $group ? $group['fields'] : self::extra_fields() as $f ) {
			$out[ $f['name'] ] = $f;
		}
		return $out;
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_types' ), 20 );
		add_action( 'acf/init', array( $this, 'register_acf' ) );
	}

	/**
	 * Register the post type and taxonomy unless another plugin (for example ACF) already did.
	 */
	public function register_types() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			register_post_type(
				self::POST_TYPE,
				array(
					'labels'        => array(
						'name'          => __( 'Areas We Serve', 'seo-boster' ),
						'singular_name' => __( 'Area We Serve', 'seo-boster' ),
						'add_new_item'  => __( 'Add New Area Page', 'seo-boster' ),
						'edit_item'     => __( 'Edit Area Page', 'seo-boster' ),
						'all_items'     => __( 'All Area Pages', 'seo-boster' ),
					),
					'public'        => true,
					'show_in_rest'  => true,
					'has_archive'   => false,
					'menu_icon'     => 'dashicons-location-alt',
					'menu_position' => 21,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
					'rewrite'       => array( 'slug' => self::POST_TYPE, 'with_front' => false ),
				)
			);
		}
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			register_taxonomy(
				self::TAXONOMY,
				array( self::POST_TYPE ),
				array(
					'labels'            => array(
						'name'          => __( 'Locations', 'seo-boster' ),
						'singular_name' => __( 'Location', 'seo-boster' ),
					),
					'public'            => true,
					'hierarchical'      => false,
					'show_in_rest'      => true,
					'show_admin_column' => true,
				)
			);
		} else {
			register_taxonomy_for_object_type( self::TAXONOMY, self::POST_TYPE );
		}
	}

	/**
	 * Add the field group to ACF. If the site already imported the team's group into the
	 * database, only the three missing Elementor fields are added, so nothing is duplicated.
	 */
	public function register_acf() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		$in_db = get_posts(
			array(
				'post_type'      => 'acf-field-group',
				'name'           => self::GROUP_KEY,
				'post_status'    => array( 'publish', 'acf-disabled' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! $in_db ) {
			$group = self::acf_group();
			if ( $group ) {
				acf_add_local_field_group( $group );
			}
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'acf-field',
				'post_parent'    => (int) $in_db[0],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$names = array();
		foreach ( $existing as $fid ) {
			$names[] = get_post_field( 'post_excerpt', $fid );
		}
		$extra = array();
		foreach ( self::extra_fields() as $f ) {
			if ( ! in_array( $f['name'], $names, true ) ) {
				$extra[] = $f;
			}
		}
		if ( $extra ) {
			acf_add_local_field_group(
				array(
					'key'      => 'group_seob_area_extra',
					'title'    => 'Locations (SEO Boster extra fields)',
					'fields'   => $extra,
					'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => self::POST_TYPE ) ) ),
				)
			);
		}
	}

	/**
	 * Save one field the way ACF expects (value + reference), with or without ACF active.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name.
	 * @param mixed  $value   Value.
	 */
	public static function save_field( $post_id, $name, $value ) {
		$fields = self::fields();
		if ( function_exists( 'update_field' ) && isset( $fields[ $name ] ) ) {
			update_field( $fields[ $name ]['key'], $value, $post_id );
			return;
		}
		update_post_meta( $post_id, $name, $value );
		if ( isset( $fields[ $name ] ) ) {
			update_post_meta( $post_id, '_' . $name, $fields[ $name ]['key'] );
		}
	}

	/**
	 * Read a field as plain value (image fields return a URL).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name.
	 */
	public static function get( $post_id, $name ) {
		$value = get_post_meta( $post_id, $name, true );
		if ( isset( self::image_fields()[ $name ] ) || 'section_5_right_image' === $name ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$url = wp_get_attachment_image_url( (int) $value, 'large' );
				return $url ? $url : '';
			}
			return ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) ? esc_url_raw( $value ) : '';
		}
		return is_string( $value ) ? $value : '';
	}
}
