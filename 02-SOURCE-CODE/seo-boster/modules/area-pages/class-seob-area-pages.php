<?php
/**
 * Area Pages module: SEO team fills one form, the plugin builds every
 * Service x Location page with AI content, images, SEO meta and design.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_Area_Pages extends SEOB_Module {

	const MAX_SERVICES  = 50;
	const MAX_LOCATIONS = 200;
	const MAX_PAGES     = 1000;

	public function id() {
		return 'area-pages';
	}

	public function name() {
		return __( 'Area Pages', 'seo-boster' );
	}

	public function description() {
		return __( 'Fill one form with services, locations and images. The plugin writes and builds every Service x Location page with SEO content and your site design.', 'seo-boster' );
	}

	public function icon() {
		return 'dashicons-location-alt';
	}

	public function stats() {
		$c = wp_count_posts( SEOB_AP_Schema::POST_TYPE );
		return array(
			__( 'live pages', 'seo-boster' ) => isset( $c->publish ) ? (int) $c->publish : 0,
			__( 'drafts', 'seo-boster' )     => isset( $c->draft ) ? (int) $c->draft : 0,
		);
	}

	public function register() {
		SEOB_AP_Jobs::maybe_upgrade();
		( new SEOB_AP_Schema() )->hooks();
		( new SEOB_AP_Design() )->hooks();
		( new SEOB_AP_Nav() )->hooks();
		( new SEOB_AP_Style() )->hooks();
		add_action( SEOB_AP_Jobs::CRON_HOOK, array( 'SEOB_AP_Jobs', 'cron' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
			add_action( 'admin_post_seob_ap_save_ai', array( $this, 'handle_save_ai' ) );
			add_action( 'admin_post_seob_ap_test_ai', array( $this, 'handle_test_ai' ) );
			add_action( 'admin_post_seob_ap_design', array( $this, 'handle_design' ) );
			add_action( 'admin_post_seob_ap_create', array( $this, 'handle_create' ) );
			add_action( 'admin_post_seob_ap_job_action', array( $this, 'handle_job_action' ) );
			add_action( 'wp_ajax_seob_ap_step', array( $this, 'ajax_step' ) );
			add_action( 'admin_post_seob_ap_setup', array( $this, 'handle_setup' ) );
			add_action( 'admin_post_seob_ap_setup2', array( $this, 'handle_setup2' ) );
			add_action( 'admin_post_seob_ap_layout_export', array( $this, 'handle_layout_export' ) );
			add_action( 'admin_post_seob_ap_layout_import', array( $this, 'handle_layout_import' ) );
			add_action( 'admin_post_seob_ap_layout_reset', array( $this, 'handle_layout_reset' ) );
			add_action( 'admin_post_seob_ap_save_images', array( $this, 'handle_save_images' ) );
			add_action( 'admin_post_seob_ap_style', array( $this, 'handle_style' ) );
			add_action( 'wp_ajax_seob_ap_layout', array( $this, 'ajax_layout' ) );
			add_action( 'wp_ajax_seob_ap_images', array( $this, 'ajax_images' ) );
			add_action( 'wp_ajax_seob_ap_models', array( $this, 'ajax_models' ) );
			add_action( 'wp_ajax_seob_ap_semrush_csv', array( $this, 'ajax_semrush_csv' ) );
			add_action( 'admin_post_seob_ap_approval_publish', array( $this, 'handle_approval_publish' ) );
			add_action( 'admin_post_seob_ap_approval_rollback', array( $this, 'handle_approval_rollback' ) );
		}
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, $this->menu_slug() ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'seob-area-admin', SEOB_URL . 'assets/js/area-pages-admin.js', array( 'jquery', 'jquery-ui-sortable' ), SEOB_VERSION, true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		if ( isset( $_GET['tab'] ) && 'layout' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			wp_enqueue_script( 'seob-layout-editor', SEOB_URL . 'assets/js/layout-editor.js', array( 'jquery', 'jquery-ui-sortable' ), SEOB_VERSION, true );
			wp_localize_script( 'seob-layout-editor', 'seobLayout', $this->layout_editor_data() );
		}
		wp_localize_script(
			'seob-area-admin',
			'seobArea',
			array(
				'ajax'         => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'seob_ap_step' ),
				'semrushNonce' => wp_create_nonce( 'seob_ap_semrush' ),
				'parallel'   => SEOB_AP_Jobs::parallel(),
				'imageNames' => array_intersect_key( SEOB_AP_Schema::image_fields(), array_flip( SEOB_AP_Design::used_image_fields() ) ),
				'i18n'       => array(
					'choose'    => __( 'Choose', 'seo-boster' ),
					'change'    => __( 'Change', 'seo-boster' ),
					'remove'    => __( 'Remove', 'seo-boster' ),
					'useAll'    => __( 'Use for all', 'seo-boster' ),
					'pickTitle' => __( 'Choose image', 'seo-boster' ),
					'noService' => __( 'Type the services above and the image grid appears here.', 'seo-boster' ),
					'done'      => __( 'All pages are finished.', 'seo-boster' ),
					'paused'    => __( 'Paused. Check the AI settings, then press Resume.', 'seo-boster' ),
					'busy'      => __( 'Working in the background...', 'seo-boster' ),
					'finding'   => __( 'Finding images...', 'seo-boster' ),
					'found'     => __( 'All images filled. Change any you do not like by clicking it.', 'seo-boster' ),
					/* translators: %d empty cells */
					'someEmpty' => __( 'Images filled. %d cells are still empty: add a few photos to the Media Library or a Magnific key, then press Auto-fill again.', 'seo-boster' ),
					'noneFound' => __( 'No images to use yet. Upload a few photos of your work to the Media Library (or add a Magnific key), then press Auto-fill again.', 'seo-boster' ),
				),
				'imgNonce'   => wp_create_nonce( 'seob_ap_images' ),
			)
		);
	}

	/* ==================================================================
	 * Parsing helpers
	 * ================================================================== */

	/**
	 * Split a list typed one per line or with "||".
	 *
	 * @param string $raw Text.
	 * @param int    $max Max items.
	 */
	public static function split_list( $raw, $max ) {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/\r\n|\r|\n|\|\|/', (string) $raw ) as $line ) {
			$line = trim( preg_replace( '/^\s*(\d+\s*[\.\)]|[-*\x{2022}])\s*/u', '', $line ) );
			$line = trim( preg_replace( '/\s+/', ' ', $line ) );
			if ( '' === $line || mb_strlen( $line ) > 150 ) {
				continue;
			}
			$k = strtolower( $line );
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = true;
			$out[]      = $line;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Phones: one per line or "||". No numbering is removed, so 814.834.1700 stays whole.
	 *
	 * @param string $raw Text.
	 */
	public static function split_phones( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n|\|\||;/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/\d{7,}/', preg_replace( '/\D/', '', $line ) ) && ! in_array( $line, $out, true ) ) {
				$out[] = $line;
			}
		}
		return array_slice( $out, 0, 50 );
	}

	/**
	 * Keywords: one line (or "||" block) per service, comma separated.
	 *
	 * @param string $raw Text.
	 */
	public static function split_keywords( $raw ) {
		$raw   = trim( (string) $raw );
		$parts = false !== strpos( $raw, '||' ) ? explode( '||', $raw ) : preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		foreach ( $parts as $p ) {
			$kws = array();
			foreach ( explode( ',', $p ) as $k ) {
				$k = trim( preg_replace( '/\s+/', ' ', $k ) );
				if ( '' !== $k && mb_strlen( $k ) <= 100 ) {
					$kws[] = $k;
				}
			}
			$out[] = array_slice( $kws, 0, 12 );
		}
		return $out;
	}

	/* ==================================================================
	 * Handlers
	 * ================================================================== */

	public function handle_save_ai() {
		SEOB_Security::verify( 'seob_ap_save_ai' );
		if ( '1' === SEOB_Security::post_text( 'forget' ) ) {
			SEOB_AP_AI::forget_key();
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'key_removed' ) ) );
		}
		// The key is read raw (no text filter) so special characters survive; it is stored encrypted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above.
		$key = isset( $_POST['api_key'] ) ? trim( preg_replace( '/[^\x21-\x7E]/', '', (string) wp_unslash( $_POST['api_key'] ) ) ) : '';
		SEOB_AP_AI::save( sanitize_key( SEOB_Security::post_text( 'provider' ) ), SEOB_Security::post_text( 'model' ), substr( $key, 0, 500 ), sanitize_key( SEOB_Security::post_text( 'speed' ) ), SEOB_Security::post_text( 'base_url' ) );
		update_option( 'seob_ap_parallel', max( 1, min( 8, absint( SEOB_Security::post_text( 'parallel' ) ) ?: 4 ) ), false );
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'ai_saved' ) ) );
	}

	public function handle_style() {
		SEOB_Security::verify( 'seob_ap_style_save' );
		SEOB_AP_Style::save(
			array(
				'on'      => '1' === SEOB_Security::post_text( 'on' ) ? 1 : 0,
				'buttons' => 'all' === SEOB_Security::post_text( 'buttons' ) ? 'all' : 'shape',
			)
		);
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'design', 'seob_msg' => 'style_saved' ) ) );
	}

	public function handle_test_ai() {
		SEOB_Security::verify( 'seob_ap_test_ai' );
		$r = SEOB_AP_AI::test();
		if ( is_wp_error( $r ) ) {
			set_transient( 'seob_ap_test_' . get_current_user_id(), $r->get_error_message(), 120 );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'ai_fail' ) ) );
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'ai_ok' ) ) );
	}

	public function handle_design() {
		SEOB_Security::verify( 'seob_ap_design' );
		$mode   = sanitize_key( SEOB_Security::post_text( 'mode' ) );
		$mode   = in_array( $mode, array( 'builtin', 'divi', 'elementor' ), true ) ? $mode : 'builtin';
		$accent = sanitize_hex_color( SEOB_Security::post_text( 'accent' ) );

		$old    = SEOB_Security::post_text_array( 'color_old' );
		$new    = SEOB_Security::post_text_array( 'color_new' );
		$colors = array();
		foreach ( $old as $i => $o ) {
			$o = sanitize_hex_color( $o );
			$n = isset( $new[ $i ] ) ? sanitize_hex_color( $new[ $i ] ) : '';
			if ( $o && $n && strtolower( $o ) !== strtolower( $n ) ) {
				$colors[ $o ] = $n;
			}
		}

		SEOB_AP_Design::save(
			array(
				'accent'     => $accent ? $accent : '',
				'faq_schema' => '1' === SEOB_Security::post_text( 'faq_schema' ) ? 1 : 0,
			)
		);

		$msg = 'design_saved';
		if ( 'builtin' === $mode ) {
			SEOB_AP_Design::save( array( 'mode' => 'builtin' ) );
		} else {
			$r = 'divi' === $mode ? SEOB_AP_Design::install_divi( $colors ) : SEOB_AP_Design::install_elementor( $colors );
			if ( is_wp_error( $r ) ) {
				set_transient( 'seob_ap_err_' . get_current_user_id(), $r->get_error_message(), 120 );
				$msg = 'design_fail';
			} else {
				$msg = 'divi' === $mode ? 'divi_ok' : ( ! empty( $r['pro'] ) ? 'el_ok' : 'el_nopro' );
			}
		}
		flush_rewrite_rules( false );
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'design', 'seob_msg' => $msg ) ) );
	}

	public function handle_create() {
		SEOB_Security::verify( 'seob_ap_create' );

		$business  = SEOB_Security::post_text( 'business' );
		$services  = self::split_list( SEOB_Security::post_textarea( 'services' ), self::MAX_SERVICES );
		$keywords  = self::split_keywords( SEOB_Security::post_textarea( 'keywords' ) );
		$locations = self::split_list( SEOB_Security::post_textarea( 'locations' ), self::MAX_LOCATIONS );
		$phones    = self::split_phones( SEOB_Security::post_textarea( 'phones' ) );

		$form = array(
			'business'  => $business,
			'services'  => SEOB_Security::post_textarea( 'services' ),
			'keywords'  => SEOB_Security::post_textarea( 'keywords' ),
			'locations' => SEOB_Security::post_textarea( 'locations' ),
			'phones'    => SEOB_Security::post_textarea( 'phones' ),
			'website'   => SEOB_Security::post_text( 'website' ),
			'notes'     => SEOB_Security::post_textarea( 'notes' ),
		);
		set_transient( 'seob_ap_form_' . get_current_user_id(), $form, DAY_IN_SECONDS );

		if ( '' === $business || ! $services || ! $locations ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'missing' ) ) );
		}
		if ( count( $services ) * count( $locations ) > self::MAX_PAGES ) {
			SEOB_Security::redirect( $this->admin_url( array( 'seob_msg' => 'too_many' ) ) );
		}
		if ( ! SEOB_AP_AI::has_key() ) {
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'need_key' ) ) );
		}

		$svc = array();
		foreach ( $services as $i => $name ) {
			$svc[] = array(
				'name'     => $name,
				'keywords' => isset( $keywords[ $i ] ) ? $keywords[ $i ] : array(),
			);
		}

		$images = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$raw = isset( $_POST['images'] ) && is_array( $_POST['images'] ) ? wp_unslash( $_POST['images'] ) : array();
		foreach ( array_keys( SEOB_AP_Schema::image_fields() ) as $field ) {
			$ids = array();
			for ( $i = 0; $i < count( $svc ); $i++ ) {
				$id      = isset( $raw[ $field ][ $i ] ) && is_scalar( $raw[ $field ][ $i ] ) ? absint( $raw[ $field ][ $i ] ) : 0;
				$ids[ $i ] = ( $id && wp_attachment_is_image( $id ) && current_user_can( 'read_post', $id ) ) ? $id : 0;
			}
			$images[ $field ] = $ids;
		}

		// Different photos on every page (default) or the same photos for every location of a service.
		$image_mode   = 'service' === SEOB_Security::post_text( 'image_mode' ) ? 'service' : 'page';
		$image_source = ( 'page' === $image_mode && SEOB_AP_Images::use_magnific() ) ? 'magnific' : 'library';

		// Cells filled by the Auto-fill button (not picked by hand). With Magnific they are only a
		// preview: every page still gets its own new photos, these are the fallback.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$auto_raw  = isset( $_POST['images_auto'] ) && is_array( $_POST['images_auto'] ) ? wp_unslash( $_POST['images_auto'] ) : array();
		$auto_cell = static function ( $field, $i ) use ( $auto_raw ) {
			return isset( $auto_raw[ $field ][ $i ] ) && '1' === (string) $auto_raw[ $field ][ $i ];
		};
		$auto_fallback = array();
		if ( 'magnific' === $image_source ) {
			foreach ( SEOB_AP_Writer::photo_fields() as $field ) {
				foreach ( $images[ $field ] as $i => $id ) {
					if ( $id && $auto_cell( $field, $i ) ) {
						$auto_fallback[ $i ][] = $id;
						$images[ $field ][ $i ] = 0;
					}
				}
			}
		}

		// Fill empty image cells automatically from the Media Library (and Pexels if a key is saved).
		// With Magnific, the photo cells stay empty so every page gets its own new photos.
		if ( '1' === SEOB_Security::post_text( 'auto_images' ) ) {
			$auto = SEOB_AP_Images::suggest( $svc, SEOB_AP_Images::has_pexels(), $business );
			foreach ( $images as $field => $ids ) {
				$given = array_filter( $ids );
				if ( 1 === count( $given ) && ! empty( $ids[0] ) ) {
					continue; // One image chosen on purpose for every service.
				}
				if ( 'magnific' === $image_source && in_array( $field, SEOB_AP_Writer::photo_fields(), true ) ) {
					continue;
				}
				foreach ( $ids as $i => $id ) {
					if ( ! $id && isset( $auto[ $field ][ $i ]['id'] ) ) {
						$images[ $field ][ $i ] = (int) $auto[ $field ][ $i ]['id'];
					}
				}
				if ( 'section_5_same_image' === $field && ! $given && isset( $auto[ $field ][0]['id'] ) ) {
					$images[ $field ][0] = (int) $auto[ $field ][0]['id'];
				}
			}
		}

		$image_pool = array();
		if ( 'page' === $image_mode && '1' === SEOB_Security::post_text( 'auto_images' ) ) {
			$image_pool = SEOB_AP_Images::pools( $svc, SEOB_AP_Images::has_pexels(), $business );
		}
		foreach ( $auto_fallback as $i => $ids ) {
			$image_pool[ $i ] = array_values( array_unique( array_merge( $ids, isset( $image_pool[ $i ] ) ? (array) $image_pool[ $i ] : array() ) ) );
		}

		$website = esc_url_raw( SEOB_Security::post_text( 'website' ) );
		$contact = SEOB_Security::post_text( 'contact_url' );
		$contact = '' === $contact ? '/contact-us/' : ( 0 === strpos( $contact, '/' ) ? '/' . ltrim( sanitize_text_field( $contact ), '/' ) : esc_url_raw( $contact ) );

		$settings = array(
			'business'     => $business,
			'website'      => $website ? $website : home_url( '/' ),
			'services'     => $svc,
			'locations'    => $locations,
			'phones'       => $phones,
			'country_code' => preg_replace( '/\D/', '', SEOB_Security::post_text( 'country_code' ) ) ?: '1',
			'contact_url'  => $contact,
			'faq_count'    => max( 1, min( 10, absint( SEOB_Security::post_text( 'faq_count' ) ) ?: 5 ) ),
			'post_status'  => 'publish' === SEOB_Security::post_text( 'post_status' ) ? 'publish' : 'draft',
			'existing'     => 'update' === SEOB_Security::post_text( 'existing' ) ? 'update' : 'skip',
			'notes'        => mb_substr( SEOB_Security::post_textarea( 'notes' ), 0, 1500 ),
			'images'       => $images,
			'image_mode'   => $image_mode,
			'image_source' => $image_source,
			'image_pool'   => $image_pool,
			'image_copies' => '1' === SEOB_Security::post_text( 'image_copies' ),
			'user_id'      => get_current_user_id(),
		);

		$job_id = SEOB_AP_Jobs::create( $settings );
		delete_transient( 'seob_ap_form_' . get_current_user_id() );
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'jobs', 'job' => $job_id ) ) );
	}

	public function handle_job_action() {
		SEOB_Security::verify( 'seob_ap_job_action' );
		$job_id = absint( SEOB_Security::post_text( 'job' ) );
		$do     = sanitize_key( SEOB_Security::post_text( 'do' ) );
		$job    = SEOB_AP_Jobs::job( $job_id );
		if ( ! $job ) {
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'jobs' ) ) );
		}
		$n = 0;
		switch ( $do ) {
			case 'publish':
				$n = SEOB_AP_Jobs::publish_all( $job_id );
				break;
			case 'retry':
				SEOB_AP_Jobs::retry_failed( $job_id );
				break;
			case 'pause':
				SEOB_AP_Jobs::set_job_status( $job_id, 'paused' );
				break;
			case 'resume':
				SEOB_AP_Jobs::set_job_status( $job_id, 'running' );
				SEOB_AP_Jobs::schedule();
				break;
			case 'images':
				$n = SEOB_AP_Jobs::refresh_images( $job_id );
				break;
			case 'trash':
				SEOB_AP_Jobs::set_job_status( $job_id, 'paused' );
				$n = SEOB_AP_Jobs::trash_pages( $job_id );
				break;
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'jobs', 'job' => $job_id, 'seob_msg' => 'job_' . $do, 'n' => $n ) ) );
	}

	public function ajax_step() {
		check_ajax_referer( 'seob_ap_step', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$job_id = isset( $_POST['job'] ) ? absint( $_POST['job'] ) : 0;
		$r      = SEOB_AP_Jobs::step( $job_id );
		// Titles and messages are shown with jQuery .text(), so they are sent as plain text.
		if ( ! empty( $r['last']['title'] ) ) {
			$r['last']['title'] = wp_strip_all_tags( $r['last']['title'] );
		}
		if ( ! empty( $r['last']['message'] ) ) {
			$r['last']['message'] = wp_strip_all_tags( $r['last']['message'] );
		}
		if ( ! empty( $r['last']['link'] ) ) {
			$r['last']['link'] = esc_url( $r['last']['link'] );
		}
		wp_send_json_success( $r );
	}

	/* ==================================================================
	 * One-click setup
	 * ================================================================== */

	public function handle_setup() {
		SEOB_Security::verify( 'seob_ap_setup' );
		$r = SEOB_AP_Setup::ensure_acf();
		if ( is_wp_error( $r ) ) {
			set_transient( 'seob_ap_err_' . get_current_user_id(), $r->get_error_message(), 300 );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'design', 'seob_msg' => 'setup_fail' ) ) );
		}
		// Step 2 runs in a new request so ACF is fully loaded.
		SEOB_Security::redirect( add_query_arg( array( 'action' => 'seob_ap_setup2', '_wpnonce' => wp_create_nonce( 'seob_ap_setup2' ), 'reinstall' => '1' === SEOB_Security::post_text( 'reinstall' ) ? '1' : '' ), admin_url( 'admin-post.php' ) ) );
	}

	public function handle_setup2() {
		SEOB_Security::verify( 'seob_ap_setup2' );
		$r = SEOB_AP_Setup::import_acf();
		if ( is_wp_error( $r ) ) {
			set_transient( 'seob_ap_err_' . get_current_user_id(), $r->get_error_message(), 300 );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'design', 'seob_msg' => 'setup_fail' ) ) );
		}
		// Design: pick the site's builder and put the SCO layout in its Theme Builder.
		$design = SEOB_AP_Design::auto_install( '1' === ( isset( $_GET['reinstall'] ) ? sanitize_key( wp_unslash( $_GET['reinstall'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$labels = array(
			'divi'      => 'Divi Theme Builder template for Areas We Serve',
			'elementor' => 'Elementor Pro template for Areas We Serve',
			'builtin'   => 'SEO Boster SCO layout',
		);
		$r[]    = array( 'design', $labels[ $design['mode'] ], 'kept' === $design['message'] ? 'already there, your edits kept' : ( in_array( $design['message'], array( 'installed', 'builtin' ), true ) ? 'ready' : 'could not install (' . $design['message'] . '), using the SEO Boster layout' ) );
		set_transient( 'seob_ap_setup_report_' . get_current_user_id(), $r, 600 );
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'design', 'seob_msg' => 'setup_ok' ) ) );
	}

	/* ==================================================================
	 * Layout editor
	 * ================================================================== */

	private function layout_editor_data() {
		$pages = get_posts(
			array(
				'post_type'      => SEOB_AP_Schema::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 40,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$list = array();
		foreach ( $pages as $p ) {
			$list[] = array(
				'id'    => $p->ID,
				'title' => get_the_title( $p ),
				'url'   => 'publish' === $p->post_status ? get_permalink( $p ) : add_query_arg( array( 'post_type' => $p->post_type, 'p' => $p->ID, 'preview' => 'true' ), home_url( '/' ) ),
			);
		}
		return array(
			'ajax'         => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'seob_ap_layout' ),
			'previewNonce' => wp_create_nonce( 'seob_ap_preview' ),
			'layout'       => SEOB_AP_Layout::get(),
			'defaults'     => array_map( array( 'SEOB_AP_Layout', 'type_defaults' ), array_combine( array_keys( SEOB_AP_Layout::types() ), array_keys( SEOB_AP_Layout::types() ) ) ),
			'types'        => SEOB_AP_Layout::types(),
			'imageFields'  => array_intersect_key( SEOB_AP_Schema::image_fields(), array_flip( SEOB_AP_Design::used_image_fields() ) ),
			'pages'        => $list,
			'siteColors'   => SEOB_AP_Design::site_colors(),
			'autoAccent'   => SEOB_AP_Design::accent(),
			'autoAccent2'  => SEOB_AP_Design::accent2( SEOB_AP_Design::accent() ),
			'presets'      => SEOB_AP_Layout::presets(),
			'mode'         => SEOB_AP_Design::settings()['mode'],
		);
	}

	/**
	 * Save the layout, or store it as an unsaved preview.
	 */
	public function ajax_layout() {
		check_ajax_referer( 'seob_ap_layout', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$raw    = isset( $_POST['layout'] ) ? json_decode( wp_unslash( (string) $_POST['layout'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value is cleaned by SEOB_AP_Layout::sanitize().
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'preview';
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => 'bad layout' ), 400 );
		}
		$clean = SEOB_AP_Layout::sanitize( $raw );
		if ( 'save' === $do ) {
			SEOB_AP_Layout::save( $clean );
			SEOB_AP_Design::save( array( 'mode' => 'builtin' ) );
			delete_transient( 'seob_ap_layout_preview_' . get_current_user_id() );
		} else {
			set_transient( 'seob_ap_layout_preview_' . get_current_user_id(), $clean, HOUR_IN_SECONDS );
		}
		wp_send_json_success( array( 'layout' => $clean ) );
	}

	public function handle_layout_export() {
		SEOB_Security::verify( 'seob_ap_layout_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seo-boster-area-layout-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( array( 'seo_boster_layout' => 1, 'version' => SEOB_VERSION, 'layout' => SEOB_AP_Layout::get() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function handle_layout_import() {
		SEOB_Security::verify( 'seob_ap_layout_import' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- verified; JSON is cleaned by sanitize().
		$raw  = isset( $_POST['layout_json'] ) ? (string) wp_unslash( $_POST['layout_json'] ) : '';
		$data = json_decode( substr( $raw, 0, 200000 ), true );
		if ( is_array( $data ) && isset( $data['layout'] ) && is_array( $data['layout'] ) ) {
			SEOB_AP_Layout::save( $data['layout'] );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'layout', 'seob_msg' => 'layout_in' ) ) );
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'layout', 'seob_msg' => 'layout_bad' ) ) );
	}

	public function handle_layout_reset() {
		SEOB_Security::verify( 'seob_ap_layout_reset' );
		SEOB_AP_Layout::reset();
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'layout', 'seob_msg' => 'layout_reset' ) ) );
	}

	/* ==================================================================
	 * Images
	 * ================================================================== */

	public function handle_save_images() {
		SEOB_Security::verify( 'seob_ap_save_images' );
		$do = sanitize_key( SEOB_Security::post_text( 'do' ) );
		if ( '1' === SEOB_Security::post_text( 'forget' ) ) {
			SEOB_AP_Images::forget_pexels();
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'img_saved' ) ) );
		}
		if ( 'forget_magnific' === $do ) {
			SEOB_AP_Images::forget_magnific();
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'img_saved' ) ) );
		}
		// Keys are read raw (no text filter) so special characters survive; they are stored encrypted.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above.
		$pexels   = isset( $_POST['pexels_key'] ) ? preg_replace( '/[^\x21-\x7E]/', '', (string) wp_unslash( $_POST['pexels_key'] ) ) : '';
		$magnific = isset( $_POST['magnific_key'] ) ? preg_replace( '/[^\x21-\x7E]/', '', (string) wp_unslash( $_POST['magnific_key'] ) ) : '';
		// phpcs:enable
		SEOB_AP_Images::save_pexels_key( substr( $pexels, 0, 200 ) );
		SEOB_AP_Images::save_magnific_key( substr( $magnific, 0, 200 ) );
		SEOB_AP_Images::save_source( SEOB_Security::post_text( 'source' ) );

		if ( 'test_magnific' === $do ) {
			$r = SEOB_AP_Images::test_magnific();
			if ( is_wp_error( $r ) ) {
				set_transient( 'seob_ap_test_' . get_current_user_id(), $r->get_error_message(), 120 );
				SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'mg_fail' ) ) );
			}
			update_option( 'seob_magnific_error', '', false );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'mg_ok' ) ) );
		}
		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'settings', 'seob_msg' => 'img_saved' ) ) );
	}

	/**
	 * Suggest images for the services typed in the form.
	 */
	/** List the models the saved key can use (for the Model box). */
	public function ajax_models() {
		check_ajax_referer( 'seob_ap_models', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$models = SEOB_AP_AI::list_models();
		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'message' => $models->get_error_message() ) );
		}
		sort( $models, SORT_NATURAL | SORT_FLAG_CASE );
		wp_send_json_success( array( 'models' => $models ) );
	}

	public function ajax_images() {
		check_ajax_referer( 'seob_ap_images', 'nonce' );
		if ( ! SEOB_Security::can_use() || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$services = self::split_list( isset( $_POST['services'] ) ? sanitize_textarea_field( wp_unslash( $_POST['services'] ) ) : '', self::MAX_SERVICES );
		$keywords = self::split_keywords( isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '' );
		$svc      = array();
		foreach ( $services as $i => $name ) {
			$svc[] = array( 'name' => $name, 'keywords' => isset( $keywords[ $i ] ) ? $keywords[ $i ] : array() );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$use_pexels = ! empty( $_POST['pexels'] ) && SEOB_AP_Images::has_pexels();
		$business   = isset( $_POST['business'] ) ? sanitize_text_field( wp_unslash( $_POST['business'] ) ) : '';
		wp_send_json_success( array( 'grid' => SEOB_AP_Images::suggest( $svc, $use_pexels, $business ) ) );
	}

	/* ==================================================================
	 * Screens
	 * ================================================================== */

	public function render_page() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'generate';
		$tabs = array(
			'generate' => __( '1. Create pages', 'seo-boster' ),
			'approval' => __( 'Review & Approval Table', 'seo-boster' ),
			'jobs'     => __( '2. Progress & report', 'seo-boster' ),
			'layout'   => __( 'Design', 'seo-boster' ),
			'menu'     => __( 'Menu & hub page', 'seo-boster' ),
			'design'   => __( 'Setup', 'seo-boster' ),
			'settings' => __( 'AI & images', 'seo-boster' ),
		);
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'generate';

		SEOB_Admin::page_start( $this->name(), $this->description(), $tabs, $tab, $this->admin_url() );

		$uid  = get_current_user_id();
		$err  = (string) get_transient( 'seob_ap_err_' . $uid );
		$test = (string) get_transient( 'seob_ap_test_' . $uid );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$n = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;
		SEOB_Admin::notice_from_query(
			array(
				'missing'      => array( 'error', __( 'Business name, at least one service and at least one location are required.', 'seo-boster' ) ),
				/* translators: %d max pages */
				'too_many'     => array( 'error', sprintf( __( 'That is more than %d pages in one go. Split it into smaller batches.', 'seo-boster' ), self::MAX_PAGES ) ),
				'need_key'     => array( 'warning', __( 'Add your AI API key first, then go back to Create pages. Your form is saved.', 'seo-boster' ) ),
				'style_saved'  => array( 'success', __( 'Saved.', 'seo-boster' ) ),
				'mg_ok'        => array( 'success', __( 'Magnific works. Photos will come from Magnific.', 'seo-boster' ) ),
				/* translators: %s error */
				'mg_fail'      => array( 'error', sprintf( __( 'Magnific did not answer correctly: %s', 'seo-boster' ), (string) get_transient( 'seob_ap_test_' . get_current_user_id() ) ) ),
				'ai_saved'     => array( 'success', __( 'AI settings saved. Press "Test now" below to check the key.', 'seo-boster' ) ),
				'key_removed'  => array( 'success', __( 'API key removed.', 'seo-boster' ) ),
				/* translators: 1: provider, 2: model */
				'ai_ok'        => array( 'success', sprintf( __( 'Connection works. %1$s answered correctly using the model %2$s.', 'seo-boster' ), SEOB_AP_AI::info()['name'], SEOB_AP_AI::model() ) ),
				/* translators: %s error */
				'ai_fail'      => array( 'error', sprintf( __( 'Connection failed: %s', 'seo-boster' ), $test ) ),
				'design_saved' => array( 'success', __( 'Design saved. Area pages now use the built-in SEO Boster template.', 'seo-boster' ) ),
				'divi_ok'      => array( 'success', __( 'Divi layout installed and assigned to all Areas We Serve pages in Theme Builder.', 'seo-boster' ) ),
				'el_ok'        => array( 'success', __( 'Elementor template installed and set to show on all Areas We Serve pages.', 'seo-boster' ) ),
				'el_nopro'     => array( 'warning', __( 'Template added to Elementor > Templates. Activate Elementor Pro so it shows on Areas We Serve pages automatically.', 'seo-boster' ) ),
				/* translators: %s error */
				'design_fail'  => array( 'error', sprintf( __( 'Design install failed: %s', 'seo-boster' ), $err ) ),
				/* translators: %d count */
				'job_publish'  => array( 'success', sprintf( __( '%d pages published.', 'seo-boster' ), $n ) ),
				'job_retry'    => array( 'success', __( 'Failed pages queued again.', 'seo-boster' ) ),
				'job_pause'    => array( 'success', __( 'Job paused.', 'seo-boster' ) ),
				'job_resume'   => array( 'success', __( 'Job resumed.', 'seo-boster' ) ),
				/* translators: %d count */
				'job_images'   => array( 'success', sprintf( __( 'Images changed on %d pages. Every page now has its own photos.', 'seo-boster' ), $n ) ),
				'job_trash'    => array( 'success', sprintf( __( '%d pages moved to trash. You can restore them from Areas We Serve > Trash.', 'seo-boster' ), $n ) ),
				'setup_ok'     => array( 'success', __( 'Setup finished. ACF fields, post type and taxonomy are in ACF and can be edited there.', 'seo-boster' ) ),
				/* translators: %s error */
				'setup_fail'   => array( 'error', sprintf( __( 'Setup stopped: %s', 'seo-boster' ), $err ) ),
				'layout_reset' => array( 'success', __( 'Layout reset to the default.', 'seo-boster' ) ),
				'layout_in'    => array( 'success', __( 'Layout imported.', 'seo-boster' ) ),
				'layout_bad'   => array( 'error', __( 'That layout file could not be read.', 'seo-boster' ) ),
				'img_saved'    => array( 'success', __( 'Image settings saved.', 'seo-boster' ) ),
				'app_publish'  => array( 'success', sprintf( __( '%d pages successfully published from review table.', 'seo-boster' ), $n ) ),
				'app_rollback' => array( 'success', __( 'Page successfully restored to previous snapshot.', 'seo-boster' ) ),
				'kws_imported' => array( 'success', __( 'High-intent keywords and services successfully imported from Keyword Research!', 'seo-boster' ) ),
			)
		);

		switch ( $tab ) {
			case 'approval':
				$this->screen_approval();
				break;
			case 'jobs':
				$this->screen_jobs();
				break;
			case 'layout':
				$this->screen_layout();
				break;
			case 'menu':
				SEOB_AP_Nav::screen( $this );
				break;
			case 'design':
				$this->screen_design();
				break;
			case 'settings':
				$this->screen_settings();
				break;
			default:
				$this->screen_generate();
		}
		SEOB_Admin::page_end();
	}

	private function checklist() {
		$d     = SEOB_AP_Design::settings();
		$items = array(
			array( SEOB_AP_AI::has_key(), __( 'AI API key saved', 'seo-boster' ), $this->admin_url( array( 'tab' => 'settings' ) ) ),
			array( post_type_exists( SEOB_AP_Schema::POST_TYPE ), __( 'Areas We Serve post type ready', 'seo-boster' ), '' ),
			array( SEOB_AP_Design::has_acf(), __( 'ACF set up (Setup tab)', 'seo-boster' ), $this->admin_url( array( 'tab' => 'design' ) ) ),
			array( true, 'builtin' === $d['mode'] ? __( 'Design: SEO Boster layout', 'seo-boster' ) : sprintf( /* translators: %s design mode */ __( 'Design: %s template', 'seo-boster' ), ucfirst( $d['mode'] ) ), $this->admin_url( array( 'tab' => 'layout' ) ) ),
		);
		echo '<div class="seob-checklist">';
		foreach ( $items as $it ) {
			$label = esc_html( $it[1] );
			if ( ! $it[0] && $it[2] ) {
				$label = '<a href="' . esc_url( $it[2] ) . '">' . $label . '</a>';
			}
			echo '<span class="seob-check-item ' . ( $it[0] ? 'is-ok' : 'is-bad' ) . '"><span class="dashicons ' . ( $it[0] ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '"></span>' . $label . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div>';
	}

	/**
	 * Phone numbers and contact page found on the site, so the team does not have to type them.
	 *
	 * @return array phones (string), contact (string)
	 */
	public static function site_contact() {
		$cached = get_transient( 'seob_ap_site_contact' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out  = array( 'phones' => '', 'contact' => '' );
		$res  = wp_remote_get( home_url( '/' ), array( 'timeout' => 8, 'sslverify' => false ) );
		$html = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		if ( $html && preg_match_all( '/href=["\']tel:([^"\']+)["\']/i', $html, $m ) ) {
			$counts = array();
			foreach ( $m[1] as $raw ) {
				$key = substr( preg_replace( '/\D/', '', rawurldecode( $raw ) ), -10 );
				if ( strlen( $key ) >= 7 ) {
					$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
				}
			}
			// The number used most on the homepage is the main one; use only that.
			arsort( $counts );
			$main = $counts ? (string) key( $counts ) : '';
			$seen = array();
			if ( 10 === strlen( $main ) ) {
				$seen[ $main ] = '(' . substr( $main, 0, 3 ) . ') ' . substr( $main, 3, 3 ) . '-' . substr( $main, 6 );
			} elseif ( '' !== $main ) {
				$seen[ $main ] = $main;
			}
			if ( isset( $seen[ $main ] ) ) {
				$out['phones'] = $seen[ $main ];
			}
		}
		foreach ( array( 'contact-us', 'contact', 'get-in-touch', 'book-now', 'book', 'request-a-quote', 'free-estimate' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$out['contact'] = wp_make_link_relative( get_permalink( $page ) );
				break;
			}
		}
		if ( '' === $out['contact'] ) {
			$out['contact'] = wp_make_link_relative( home_url( '/contact-us/' ) );
		}
		set_transient( 'seob_ap_site_contact', $out, DAY_IN_SECONDS );
		return $out;
	}

	private function screen_generate() {
		$site = self::site_contact();
		$form = get_transient( 'seob_ap_form_' . get_current_user_id() );
		$form = is_array( $form ) ? $form : array();
		$val  = static function ( $k, $d = '' ) use ( $form ) {
			return isset( $form[ $k ] ) ? $form[ $k ] : $d;
		};
		$this->checklist();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-ap-form">
			<?php SEOB_Security::field( 'seob_ap_create' ); ?>
			<input type="hidden" name="action" value="seob_ap_create">

			<div class="seob-card">
				<h3><?php esc_html_e( 'Business', 'seo-boster' ); ?></h3>
				<div class="seob-form-grid">
					<div>
						<label class="seob-label" for="seob-business"><?php esc_html_e( 'Business name', 'seo-boster' ); ?> *</label>
						<input id="seob-business" class="seob-input" type="text" name="business" required value="<?php echo esc_attr( $val( 'business', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ); ?>">
					</div>
					<div>
						<label class="seob-label" for="seob-website"><?php esc_html_e( 'Website URL', 'seo-boster' ); ?></label>
						<input id="seob-website" class="seob-input" type="url" name="website" value="<?php echo esc_attr( $val( 'website', home_url( '/' ) ) ); ?>">
					</div>
					<div>
						<label class="seob-label" for="seob-phones"><?php esc_html_e( 'Phone numbers', 'seo-boster' ); ?></label>
						<textarea id="seob-phones" class="seob-textarea" name="phones" rows="2" placeholder="(352) 489-6673"><?php echo esc_textarea( $val( 'phones', $site['phones'] ) ); ?></textarea>
						<p class="seob-help"><?php esc_html_e( 'One per line. One number = used on every page. Several = given to pages in order.', 'seo-boster' ); ?></p>
					</div>
					<div>
						<label class="seob-label" for="seob-contact"><?php esc_html_e( 'Contact button link', 'seo-boster' ); ?></label>
						<input id="seob-contact" class="seob-input" type="text" name="contact_url" value="<?php echo esc_attr( $val( 'contact_url', $site['contact'] ) ); ?>">
						<p class="seob-help"><?php esc_html_e( 'Country code for phones:', 'seo-boster' ); ?> <input class="seob-input seob-input-xs" type="text" name="country_code" value="1" maxlength="4"></p>
					</div>
				</div>
			</div>

			<div class="seob-card">
				<h3><?php esc_html_e( 'Services, keywords and locations', 'seo-boster' ); ?></h3>
				<div class="seob-form-grid seob-form-grid-3">
					<div>
						<label class="seob-label" for="seob-services"><?php esc_html_e( 'Main services', 'seo-boster' ); ?> *</label>
						<textarea id="seob-services" class="seob-textarea" name="services" rows="8" required placeholder="AC Repair&#10;AC Installation&#10;Heat Pump Services" data-seob-lines="#seob-count-services"><?php echo esc_textarea( $val( 'services' ) ); ?></textarea>
						<p class="seob-counter"><span id="seob-count-services">0</span> <?php esc_html_e( 'services', 'seo-boster' ); ?></p>
					</div>
					<div>
						<label class="seob-label" for="seob-keywords"><?php esc_html_e( 'Service keywords (optional)', 'seo-boster' ); ?></label>
						<textarea id="seob-keywords" class="seob-textarea" name="keywords" rows="8" placeholder="ac repair, air conditioner repair&#10;ac installation, new ac unit&#10;heat pump repair"><?php echo esc_textarea( $val( 'keywords' ) ); ?></textarea>
						<p class="seob-help"><?php esc_html_e( 'Line 1 = keywords for service 1, and so on. Separate keywords with commas. Empty = the AI creates them.', 'seo-boster' ); ?></p>
					</div>
					<div>
						<label class="seob-label" for="seob-locations"><?php esc_html_e( 'Locations', 'seo-boster' ); ?> *</label>
						<textarea id="seob-locations" class="seob-textarea" name="locations" rows="8" required placeholder="Beverly Hills, FL&#10;Citrus Springs, FL&#10;Dunnellon, FL" data-seob-lines="#seob-count-locations"><?php echo esc_textarea( $val( 'locations' ) ); ?></textarea>
						<p class="seob-counter"><span id="seob-count-locations">0</span> <?php esc_html_e( 'locations', 'seo-boster' ); ?></p>
					</div>
				</div>
				<div class="seob-semrush-bar" style="margin-top: 15px; padding: 12px 16px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">
					<div>
						<strong><?php esc_html_e( 'SEMrush Bulk Drop-In (Zero API Cost):', 'seo-boster' ); ?></strong>
						<span style="display: block; font-size: 12px; color: #64748b;"><?php esc_html_e( '1-Click copy search strings ({Service} {City}) for SEMrush Bulk Analysis, then drop the CSV here to auto-cluster keywords by Volume and low KD%.', 'seo-boster' ); ?></span>
					</div>
					<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seob-research' ) ); ?>" class="button button-primary"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Auto Research Keywords (In-Plugin)', 'seo-boster' ); ?></a>
						<button type="button" class="button" id="seob-copy-semrush-btn"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy Bulk Query List', 'seo-boster' ); ?></button>
						<label class="button button-secondary" style="position: relative; overflow: hidden; cursor: pointer; margin: 0;">
							<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Drop SEMrush CSV', 'seo-boster' ); ?>
							<input type="file" id="seob-semrush-csv-input" accept=".csv" style="position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;" />
						</label>
					</div>
				</div>
				<div class="seob-total" id="seob-total" aria-live="polite"></div>
			</div>

			<div class="seob-card">
				<h3><?php esc_html_e( 'Images', 'seo-boster' ); ?></h3>
				<p class="seob-help"><?php echo esc_html( SEOB_AP_Images::use_magnific() ? __( 'Nothing to do here: every page gets its own new photos from Magnific automatically, with SEO file names and alt text. Pick images below only if you want your own photos for a service.', 'seo-boster' ) : __( 'Nothing to do here: images are added to every page automatically from the Media Library (and Pexels if you saved a key). Press "Auto-fill images" only if you want to see or change them first.', 'seo-boster' ) ); ?></p>
				<div class="seob-image-mode">
					<label class="seob-check"><input type="radio" name="image_mode" value="page" checked> <strong><?php esc_html_e( 'Different images on every page (recommended)', 'seo-boster' ); ?></strong> <span class="seob-help"><?php esc_html_e( 'Each location page of a service gets its own set of matching photos. The grid below shows the first page; other pages get other matching photos.', 'seo-boster' ); ?></span></label>
					<label class="seob-check"><input type="radio" name="image_mode" value="service"> <?php esc_html_e( 'Same images on every location page of a service (old way)', 'seo-boster' ); ?></label>
					<label class="seob-check"><input type="checkbox" name="image_copies" value="1" checked> <strong><?php esc_html_e( 'SEO image names', 'seo-boster' ); ?></strong> <span class="seob-help"><?php esc_html_e( 'Each page gets its own copy of its photos with the page keyword and city in the file name (balayage-in-cary-nc-1.jpg) and the page alt text, like renaming and uploading by hand. Uses a little more disk space.', 'seo-boster' ); ?></span></label>
				</div>
				<p class="seob-help"><?php esc_html_e( 'You can still change any cell in the grid. If you only fill the first row of a column, that image is used on every page.', 'seo-boster' ); ?></p>
				<div class="seob-actions seob-actions-tight">
					<button type="button" class="seob-btn seob-btn-primary" id="seob-auto-images"><span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Auto-fill images', 'seo-boster' ); ?></button>
					<?php if ( SEOB_AP_Images::has_pexels() ) : ?>
						<label class="seob-check"><input type="checkbox" id="seob-use-pexels" checked> <?php esc_html_e( 'Also use Pexels when the library has too few', 'seo-boster' ); ?></label>
					<?php endif; ?>
					<label class="seob-check"><input type="checkbox" name="auto_images" value="1" checked> <?php esc_html_e( 'Fill any empty cell automatically when creating pages', 'seo-boster' ); ?></label>
					<span class="seob-help" id="seob-img-status" aria-live="polite"></span>
				</div>
				<div id="seob-image-grid" class="seob-image-grid"></div>
			</div>

			<div class="seob-card">
				<h3><?php esc_html_e( 'Options', 'seo-boster' ); ?></h3>
				<div class="seob-form-grid seob-form-grid-3">
					<div>
						<label class="seob-label" for="seob-status"><?php esc_html_e( 'New pages are', 'seo-boster' ); ?></label>
						<select id="seob-status" name="post_status" class="seob-select">
							<option value="draft"><?php esc_html_e( 'Drafts (review first)', 'seo-boster' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Published right away', 'seo-boster' ); ?></option>
						</select>
					</div>
					<div>
						<label class="seob-label" for="seob-existing"><?php esc_html_e( 'If a page already exists', 'seo-boster' ); ?></label>
						<select id="seob-existing" name="existing" class="seob-select">
							<option value="skip"><?php esc_html_e( 'Skip it', 'seo-boster' ); ?></option>
							<option value="update"><?php esc_html_e( 'Rewrite it', 'seo-boster' ); ?></option>
						</select>
					</div>
					<div>
						<label class="seob-label" for="seob-faq"><?php esc_html_e( 'FAQs per page', 'seo-boster' ); ?></label>
						<input id="seob-faq" class="seob-input" type="number" name="faq_count" min="1" max="10" value="5">
					</div>
				</div>
				<label class="seob-label" for="seob-notes"><?php esc_html_e( 'Extra notes for the writer (optional)', 'seo-boster' ); ?></label>
				<textarea id="seob-notes" class="seob-textarea" name="notes" rows="2" maxlength="1500" placeholder="<?php esc_attr_e( 'For example: family owned since 1998, offers 24/7 emergency service.', 'seo-boster' ); ?>"><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea>
			</div>

			<div class="seob-actions seob-actions-sticky seob-card">
				<button type="submit" class="seob-btn seob-btn-primary seob-btn-lg"><span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Create pages', 'seo-boster' ); ?></button>
				<span class="seob-help"><?php esc_html_e( 'Pages are written one by one in the background. You can watch the progress or close the tab.', 'seo-boster' ); ?></span>
			</div>
		</form>
		<?php
	}

	private function screen_jobs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$job_id = isset( $_GET['job'] ) ? absint( $_GET['job'] ) : 0;
		$job    = $job_id ? SEOB_AP_Jobs::job( $job_id ) : null;

		if ( ! $job ) {
			$jobs = SEOB_AP_Jobs::jobs();
			echo '<div class="seob-card seob-card-flush">';
			if ( ! $jobs ) {
				echo '<div class="seob-empty"><p>' . esc_html__( 'No jobs yet.', 'seo-boster' ) . '</p><a class="seob-btn seob-btn-primary" href="' . esc_url( $this->admin_url() ) . '">' . esc_html__( 'Create pages', 'seo-boster' ) . '</a></div></div>';
				return;
			}
			echo '<table class="seob-table"><thead><tr><th>#</th><th>' . esc_html__( 'Business', 'seo-boster' ) . '</th><th>' . esc_html__( 'Pages', 'seo-boster' ) . '</th><th>' . esc_html__( 'Status', 'seo-boster' ) . '</th><th>' . esc_html__( 'Started', 'seo-boster' ) . '</th></tr></thead><tbody>';
			foreach ( $jobs as $j ) {
				$c = SEOB_AP_Jobs::counts( $j->id );
				echo '<tr><td>' . (int) $j->id . '</td><td><a href="' . esc_url( $this->admin_url( array( 'tab' => 'jobs', 'job' => $j->id ) ) ) . '"><strong>' . esc_html( $j->name ) . '</strong></a></td><td>' . (int) $c['finished'] . ' / ' . (int) $c['total'] . '</td><td><span class="seob-badge seob-badge-' . esc_attr( 'finished' === $j->status ? 'high' : ( 'paused' === $j->status ? 'low' : 'medium' ) ) . '">' . esc_html( ucfirst( $j->status ) ) . '</span></td><td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $j->created_at ) ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			return;
		}

		$c     = SEOB_AP_Jobs::counts( $job_id );
		$items = SEOB_AP_Jobs::items( $job_id );
		$pct   = $c['total'] ? (int) floor( $c['finished'] / $c['total'] * 100 ) : 0;
		$act   = function ( $do, $label, $class = 'seob-btn-ghost', $confirm = '' ) use ( $job_id ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-seob-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
			SEOB_Security::field( 'seob_ap_job_action' );
			echo '<input type="hidden" name="action" value="seob_ap_job_action"><input type="hidden" name="job" value="' . (int) $job_id . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
			echo '<button type="submit" class="seob-btn ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
		};
		?>
		<div class="seob-card seob-progress-card" id="seob-progress" data-job="<?php echo (int) $job_id; ?>" data-status="<?php echo esc_attr( $job->status ); ?>">
			<div class="seob-report-bar">
				<h2><?php echo esc_html( $job->name ); ?> <span class="seob-pill"><?php echo (int) $c['total']; ?> <?php esc_html_e( 'pages', 'seo-boster' ); ?></span></h2>
				<div class="seob-job-actions">
					<?php
					if ( 'running' === $job->status ) {
						$act( 'pause', __( 'Pause', 'seo-boster' ) );
					} elseif ( 'paused' === $job->status ) {
						$act( 'resume', __( 'Resume', 'seo-boster' ), 'seob-btn-primary' );
					}
					if ( $c['failed'] ) {
						$act( 'retry', __( 'Retry failed', 'seo-boster' ) );
					}
					if ( $c['done'] + $c['warning'] ) {
						$act( 'publish', __( 'Publish all drafts', 'seo-boster' ), 'seob-btn-primary', __( 'Publish every draft page from this job?', 'seo-boster' ) );
						$act( 'images', __( 'Different images on every page', 'seo-boster' ), 'seob-btn-ghost', __( 'Give every page of this job its own matching photos (with SEO file names)? Text is not changed.', 'seo-boster' ) );
						$act( 'trash', __( 'Move pages to trash', 'seo-boster' ), 'seob-btn-ghost seob-danger', __( 'Move every page from this job to the trash?', 'seo-boster' ) );
					}
					?>
				</div>
			</div>
			<div class="seob-bar"><span id="seob-bar-fill" style="width:<?php echo (int) $pct; ?>%"></span></div>
			<p class="seob-progress-text"><strong id="seob-pct"><?php echo (int) $pct; ?>%</strong> &middot; <span id="seob-done"><?php echo (int) $c['finished']; ?></span> / <?php echo (int) $c['total']; ?> &middot; <span id="seob-state"></span></p>
			<ul class="seob-mini-stats">
				<li><span class="seob-dot is-done"></span><?php esc_html_e( 'Done', 'seo-boster' ); ?> <strong id="seob-c-done"><?php echo (int) $c['done']; ?></strong></li>
				<li><span class="seob-dot is-warning"></span><?php esc_html_e( 'Done with notes', 'seo-boster' ); ?> <strong id="seob-c-warning"><?php echo (int) $c['warning']; ?></strong></li>
				<li><span class="seob-dot is-skipped"></span><?php esc_html_e( 'Skipped', 'seo-boster' ); ?> <strong id="seob-c-skipped"><?php echo (int) $c['skipped']; ?></strong></li>
				<li><span class="seob-dot is-failed"></span><?php esc_html_e( 'Failed', 'seo-boster' ); ?> <strong id="seob-c-failed"><?php echo (int) $c['failed']; ?></strong></li>
			</ul>
		</div>

		<div class="seob-card seob-card-flush">
			<div class="seob-table-head">
				<p><?php esc_html_e( 'Every page from this job. The list refreshes when the job finishes.', 'seo-boster' ); ?></p>
				<input type="search" class="seob-input seob-filter" placeholder="<?php esc_attr_e( 'Filter...', 'seo-boster' ); ?>" data-seob-filter="#seob-job-table">
			</div>
			<table class="seob-table" id="seob-job-table">
				<thead><tr><th class="seob-col-num">#</th><th><?php esc_html_e( 'Page', 'seo-boster' ); ?></th><th><?php esc_html_e( 'Keyword', 'seo-boster' ); ?></th><th><?php esc_html_e( 'Status', 'seo-boster' ); ?></th><th><?php esc_html_e( 'Links', 'seo-boster' ); ?></th></tr></thead>
				<tbody>
				<?php
				$labels = array(
					'pending' => array( 'medium', __( 'Waiting', 'seo-boster' ) ),
					'working' => array( 'medium', __( 'Writing', 'seo-boster' ) ),
					'done'    => array( 'high', __( 'Done', 'seo-boster' ) ),
					'warning' => array( 'low', __( 'Done, check notes', 'seo-boster' ) ),
					'skipped' => array( 'none', __( 'Skipped', 'seo-boster' ) ),
					'failed'  => array( 'none', __( 'Failed', 'seo-boster' ) ),
				);
				foreach ( $items as $it ) :
					$l     = isset( $labels[ $it->status ] ) ? $labels[ $it->status ] : $labels['pending'];
					$title = $it->service . ' in ' . $it->location;
					?>
					<tr data-seob-row="<?php echo esc_attr( strtolower( $title ) ); ?>">
						<td class="seob-col-num"><?php echo (int) $it->row_index + 1; ?></td>
						<td><strong><?php echo esc_html( $title ); ?></strong><?php echo $it->post_id ? ' <span class="seob-reason">' . esc_html( ucfirst( (string) get_post_status( $it->post_id ) ) ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( $it->keyword ); ?></td>
						<td><span class="seob-badge seob-badge-<?php echo esc_attr( $l[0] ); ?>"><?php echo esc_html( $l[1] ); ?></span><?php echo $it->message ? '<span class="seob-reason">' . esc_html( $it->message ) . '</span>' : ''; ?></td>
						<td class="seob-link-row">
							<?php if ( $it->post_id && get_post( $it->post_id ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $it->post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'seo-boster' ); ?></a>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $it->post_id ) ); ?>"><?php esc_html_e( 'Edit', 'seo-boster' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function screen_layout() {
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=seob_ap_layout_export' ), 'seob_ap_layout_export' );
		$mode = SEOB_AP_Design::settings()['mode'];
		if ( 'builtin' !== $mode ) :
			list( $link, $where ) = SEOB_AP_Design::edit_link();
			?>
			<div class="seob-card seob-tb-card">
				<span class="seob-tb-icon dashicons dashicons-layout"></span>
				<div>
					<h3><?php echo 'divi' === $mode ? esc_html__( 'Your Area pages use the Divi Theme Builder template', 'seo-boster' ) : esc_html__( 'Your Area pages use the Elementor Theme Builder template', 'seo-boster' ); ?></h3>
					<p><?php esc_html_e( 'One template is assigned to "All Areas We Serve" and it already has the site\'s fonts, sizes, colors and buttons. Any design change (font, size, color, button, spacing, sections) is made in the Theme Builder: edit, save, and every Area page changes. Each page\'s text, images and links come from its own ACF fields (Areas We Serve > Edit page).', 'seo-boster' ); ?></p>
					<a class="seob-btn seob-btn-primary seob-btn-lg" href="<?php echo esc_url( $link ); ?>"><span class="dashicons dashicons-edit"></span> <?php echo esc_html( sprintf( /* translators: %s place */ __( 'Edit design in %s', 'seo-boster' ), $where ) ); ?></a>
					<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SEOB_AP_Schema::POST_TYPE ) ); ?>"><?php esc_html_e( 'Edit page content (ACF fields)', 'seo-boster' ); ?></a>
				</div>
			</div>
			<?php
			// Theme Builder sites: all design work happens in the Theme Builder, nothing to edit here.
			return;
		endif;
		?>
		<div class="seob-le" id="seob-le">
			<div class="seob-le-bar seob-card">
				<div>
					<strong><?php esc_html_e( 'One layout for every Area page.', 'seo-boster' ); ?></strong>
					<span class="seob-help"><?php esc_html_e( 'Show, hide, reorder and style sections here. Save once and every Area page updates. Text and images still come from each page\'s fields.', 'seo-boster' ); ?></span>
				</div>
				<div class="seob-le-bar-actions">
					<span class="seob-le-status" id="seob-le-status" aria-live="polite"></span>
					<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( $export ); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export', 'seo-boster' ); ?></a>
					<button type="button" class="seob-btn seob-btn-ghost" id="seob-le-import-toggle"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import', 'seo-boster' ); ?></button>
					<button type="button" class="seob-btn seob-btn-primary" id="seob-le-save"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save layout', 'seo-boster' ); ?></button>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card seob-le-import" id="seob-le-import" hidden>
				<?php SEOB_Security::field( 'seob_ap_layout_import' ); ?>
				<input type="hidden" name="action" value="seob_ap_layout_import">
				<label class="seob-label" for="seob-le-json"><?php esc_html_e( 'Paste a layout exported from another site', 'seo-boster' ); ?></label>
				<textarea id="seob-le-json" name="layout_json" rows="6" class="seob-textarea" required></textarea>
				<div class="seob-actions"><button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Import layout', 'seo-boster' ); ?></button></div>
			</form>

			<div class="seob-le-main">
				<div class="seob-le-side">
					<div class="seob-card seob-le-global" id="seob-le-global"></div>
					<div class="seob-card seob-le-sections">
						<h3><?php esc_html_e( 'Sections', 'seo-boster' ); ?></h3>
						<p class="seob-help"><?php esc_html_e( 'Drag to reorder. Eye = show or hide. Click a section to edit it.', 'seo-boster' ); ?></p>
						<ul class="seob-le-list" id="seob-le-list"></ul>
						<div class="seob-le-add">
							<select id="seob-le-add-type" class="seob-select"></select>
							<button type="button" class="seob-btn seob-btn-ghost" id="seob-le-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add section', 'seo-boster' ); ?></button>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-seob-confirm="<?php esc_attr_e( 'Reset the layout to the default? Your changes will be lost.', 'seo-boster' ); ?>">
							<?php SEOB_Security::field( 'seob_ap_layout_reset' ); ?>
							<input type="hidden" name="action" value="seob_ap_layout_reset">
							<button type="submit" class="seob-link-btn seob-danger"><?php esc_html_e( 'Reset to default layout', 'seo-boster' ); ?></button>
						</form>
					</div>
				</div>
				<div class="seob-le-preview seob-card">
					<div class="seob-le-preview-bar">
						<label class="seob-le-sample" for="seob-le-page"><?php esc_html_e( 'Sample content', 'seo-boster' ); ?></label>
						<select id="seob-le-page" class="seob-select" title="<?php esc_attr_e( 'Only fills the preview with real text. Your changes apply to every Area page.', 'seo-boster' ); ?>"></select>
						<div class="seob-le-devices" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'seo-boster' ); ?>">
							<button type="button" class="is-active" data-w="100%" title="<?php esc_attr_e( 'Desktop', 'seo-boster' ); ?>"><span class="dashicons dashicons-desktop"></span></button>
							<button type="button" data-w="820px" title="<?php esc_attr_e( 'Tablet', 'seo-boster' ); ?>"><span class="dashicons dashicons-tablet"></span></button>
							<button type="button" data-w="390px" title="<?php esc_attr_e( 'Phone', 'seo-boster' ); ?>"><span class="dashicons dashicons-smartphone"></span></button>
						</div>
						<a class="seob-link-btn" id="seob-le-open" href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open page', 'seo-boster' ); ?></a>
					</div>
					<p class="seob-le-tip"><span class="dashicons dashicons-info-outline"></span><?php esc_html_e( 'You are editing ONE layout for all Area pages. Click any section in the preview to edit it. The sample page only fills the preview with real text.', 'seo-boster' ); ?></p>
					<div class="seob-le-frame-wrap"><iframe id="seob-le-frame" title="<?php esc_attr_e( 'Layout preview', 'seo-boster' ); ?>"></iframe><div class="seob-le-empty" id="seob-le-empty" hidden><?php esc_html_e( 'Create at least one Area page to see a preview here.', 'seo-boster' ); ?></div></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function screen_design() {
		$d       = SEOB_AP_Design::settings();
		$site    = SEOB_AP_Design::site_colors();
		$sources = array(
			'divi'      => SEOB_AP_Design::layout_colors( SEOB_AP_Design::divi_source() ),
			'elementor' => SEOB_AP_Design::layout_colors( (string) wp_json_encode( SEOB_AP_Design::elementor_source() ) ),
		);
		$site_hex = wp_list_pluck( $site, 'color' );
		$accent   = SEOB_AP_Design::accent();
		$report   = get_transient( 'seob_ap_setup_report_' . get_current_user_id() );
		$style    = SEOB_AP_Style::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card">
			<?php SEOB_Security::field( 'seob_ap_style_save' ); ?>
			<input type="hidden" name="action" value="seob_ap_style">
			<h3><?php esc_html_e( 'Same look as the site (automatic)', 'seo-boster' ); ?></h3>
			<?php $seob_builder = in_array( $d['mode'], array( 'divi', 'elementor' ), true ); ?>
			<?php if ( $seob_builder ) : ?>
				<p class="seob-help"><?php esc_html_e( 'The plugin reads the homepage (desktop, tablet and phone) and writes the same heading and text fonts, sizes, line spacing and an exact copy of the site\'s buttons (font, size, colors, corners, border, padding, hover) into your Theme Builder template. Nothing to set here: to change anything, edit the template in the Theme Builder.', 'seo-boster' ); ?></p>
			<?php else : ?>
				<p class="seob-help"><?php esc_html_e( 'The plugin reads the homepage on desktop, tablet and phone and gives Area pages the same heading and text fonts, sizes and line spacing, and an exact copy of the site\'s buttons: font, size, colors, corners, border, shadow and hover effect. Nothing to set: it runs by itself and refreshes every 2 weeks.', 'seo-boster' ); ?></p>
			<?php endif; ?>
			<ul class="seob-style-summary" id="seob-style-summary">
				<?php foreach ( SEOB_AP_Style::summary() as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php SEOB_AP_Style::preview(); ?>
			<p id="seob-style-status" class="seob-help">
				<?php
				if ( SEOB_AP_Style::has_data() ) {
					/* translators: %s time ago */
					echo esc_html( sprintf( __( 'Last read %s ago.', 'seo-boster' ), human_time_diff( (int) $style['time'] ) ) );
					if ( $seob_builder && SEOB_AP_Bake::is_baked() ) {
						echo ' ' . esc_html__( 'Copied into the Theme Builder template.', 'seo-boster' );
					}
				}
				?>
			</p>
			<?php if ( $seob_builder ) : ?>
				<?php list( $seob_tb_link, $seob_tb_where ) = SEOB_AP_Design::edit_link(); ?>
				<div class="seob-actions">
					<a class="seob-btn seob-btn-primary" href="<?php echo esc_url( $seob_tb_link ); ?>"><span class="dashicons dashicons-edit"></span> <?php echo esc_html( sprintf( /* translators: %s place */ __( 'Edit design in %s', 'seo-boster' ), $seob_tb_where ) ); ?></a>
					<button type="button" class="seob-btn seob-btn-ghost" id="seob-style-detect" data-seob-confirm-click="<?php esc_attr_e( 'Copy the site look into the Theme Builder template again? Font, size and button changes made in the template are replaced. Colors of sections, text and images stay.', 'seo-boster' ); ?>"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Copy the site look again', 'seo-boster' ); ?></button>
				</div>
				<p class="seob-help"><?php esc_html_e( 'Use "Copy the site look again" only after the site\'s own fonts or buttons change.', 'seo-boster' ); ?></p>
			<?php else : ?>
				<label class="seob-check"><input type="checkbox" name="on" value="1" <?php checked( $style['on'] ); ?>> <?php esc_html_e( 'Use the site\'s fonts, sizes and buttons on Area pages', 'seo-boster' ); ?></label>
				<label class="seob-check"><input type="checkbox" name="buttons" value="all" <?php checked( $style['buttons'], 'all' ); ?>> <?php esc_html_e( 'Copy the button colors and hover effect too (on by default; turn off only if a button disappears on a colored section)', 'seo-boster' ); ?></label>
				<div class="seob-actions">
					<button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Save', 'seo-boster' ); ?></button>
					<button type="button" class="seob-btn seob-btn-ghost" id="seob-style-detect"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Read the site again', 'seo-boster' ); ?></button>
				</div>
			<?php endif; ?>
		</form>
		<div class="seob-card seob-setup-card">
			<div class="seob-setup-head">
				<div>
					<h3><?php esc_html_e( 'One-click setup', 'seo-boster' ); ?></h3>
					<p class="seob-help"><?php esc_html_e( 'Does everything you used to do by hand: installs ACF, imports your ACF export (Locations field group, Areas We Serve post type, Location taxonomy), finds the site builder and puts the SCO layout in its Theme Builder for all Areas We Serve pages (Divi or Elementor Pro). Sites without a builder get the same SCO design from the plugin. Colors are matched to the site brand.', 'seo-boster' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php SEOB_Security::field( 'seob_ap_setup' ); ?>
					<input type="hidden" name="action" value="seob_ap_setup">
					<button type="submit" class="seob-btn seob-btn-primary seob-btn-lg"><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Set up this site', 'seo-boster' ); ?></button>
					<label class="seob-check seob-help"><input type="checkbox" name="reinstall" value="1"> <?php esc_html_e( 'Install a fresh copy of the design (your Theme Builder edits are replaced)', 'seo-boster' ); ?></label>
				</form>
			</div>
			<ul class="seob-setup-status">
				<?php foreach ( SEOB_AP_Setup::status() as $st ) : ?>
					<li class="<?php echo $st[0] ? 'is-ok' : 'is-bad'; ?>"><span class="dashicons <?php echo $st[0] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><?php echo esc_html( $st[1] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( is_array( $report ) && $report ) : ?>
				<p class="seob-help">
					<?php
					foreach ( $report as $r ) {
						echo esc_html( ucfirst( $r[0] ) . ' "' . $r[1] . '": ' . $r[2] ) . '<br>';
					}
					?>
				</p>
			<?php endif; ?>
			<?php list( $seob_link, $seob_where ) = SEOB_AP_Design::edit_link(); ?>
			<p class="seob-help"><?php esc_html_e( 'Design for all Area pages is edited in one place:', 'seo-boster' ); ?> <a href="<?php echo esc_url( $seob_link ); ?>"><?php echo esc_html( $seob_where ); ?></a></p>
		</div>
		<details class="seob-card seob-advanced">
			<summary><strong><?php esc_html_e( 'Advanced: use a Divi or Elementor template instead', 'seo-boster' ); ?></strong></summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card" id="seob-design-form">
			<?php SEOB_Security::field( 'seob_ap_design' ); ?>
			<input type="hidden" name="action" value="seob_ap_design">

			<h3><?php esc_html_e( 'How should Area pages look?', 'seo-boster' ); ?></h3>
			<div class="seob-choice-grid">
				<label class="seob-choice">
					<input type="radio" name="mode" value="builtin" <?php checked( $d['mode'], 'builtin' ); ?>>
					<span><strong><?php esc_html_e( 'Built-in template', 'seo-boster' ); ?></strong><?php esc_html_e( 'Works on any theme right away. Uses your theme header, footer and fonts plus your brand color.', 'seo-boster' ); ?></span>
				</label>
				<label class="seob-choice <?php echo SEOB_AP_Design::has_divi() ? '' : 'is-disabled'; ?>">
					<input type="radio" name="mode" value="divi" <?php checked( $d['mode'], 'divi' ); ?> <?php disabled( ! SEOB_AP_Design::has_divi() ); ?>>
					<span><strong><?php esc_html_e( 'Divi (SCO layout)', 'seo-boster' ); ?></strong><?php echo SEOB_AP_Design::has_divi() ? esc_html__( 'Adds your SCO layout to Theme Builder for all Areas We Serve pages.', 'seo-boster' ) : esc_html__( 'Divi is not active on this site.', 'seo-boster' ); ?></span>
				</label>
				<label class="seob-choice <?php echo SEOB_AP_Design::has_elementor() ? '' : 'is-disabled'; ?>">
					<input type="radio" name="mode" value="elementor" <?php checked( $d['mode'], 'elementor' ); ?> <?php disabled( ! SEOB_AP_Design::has_elementor() ); ?>>
					<span><strong><?php esc_html_e( 'Elementor Pro layout', 'seo-boster' ); ?></strong><?php echo SEOB_AP_Design::has_elementor() ? ( SEOB_AP_Design::has_elementor_pro() ? esc_html__( 'Adds your Elementor layout as a Single template for all Areas We Serve pages.', 'seo-boster' ) : esc_html__( 'Elementor found, but Elementor Pro is needed to assign the template automatically.', 'seo-boster' ) ) : esc_html__( 'Elementor is not active on this site.', 'seo-boster' ); ?></span>
				</label>
			</div>

			<?php if ( ! SEOB_AP_Design::has_acf() ) : ?>
				<?php SEOB_Admin::notice( 'warning', __( 'Divi and Elementor layouts read the page fields through ACF. Install and activate the free Advanced Custom Fields plugin before using them. The built-in template does not need it.', 'seo-boster' ) ); ?>
			<?php endif; ?>

			<div class="seob-field">
				<label class="seob-label" for="seob-accent"><?php esc_html_e( 'Brand color (built-in template)', 'seo-boster' ); ?></label>
				<input type="color" id="seob-accent" name="accent" value="<?php echo esc_attr( $accent ); ?>">
				<?php if ( $site ) : ?>
					<span class="seob-swatches">
						<?php foreach ( $site as $c ) : ?>
							<button type="button" class="seob-swatch" style="background:<?php echo esc_attr( $c['color'] ); ?>" title="<?php echo esc_attr( $c['label'] . ' ' . $c['color'] ); ?>" data-seob-color="<?php echo esc_attr( $c['color'] ); ?>" data-seob-target="#seob-accent"></button>
						<?php endforeach; ?>
					</span>
					<p class="seob-help"><?php esc_html_e( 'Swatches are the colors already set on this site. Click one to use it.', 'seo-boster' ); ?></p>
				<?php endif; ?>
			</div>

			<?php foreach ( $sources as $mode => $colors ) : ?>
				<?php if ( ! $colors ) { continue; } ?>
				<div class="seob-field seob-color-map" data-seob-mode="<?php echo esc_attr( $mode ); ?>">
					<span class="seob-label"><?php echo 'divi' === $mode ? esc_html__( 'Divi layout colors to replace with your brand', 'seo-boster' ) : esc_html__( 'Elementor layout colors to replace with your brand', 'seo-boster' ); ?></span>
					<?php foreach ( $colors as $i => $old ) : ?>
						<?php $new = isset( $site_hex[ $i ] ) ? $site_hex[ $i ] : $old; ?>
						<div class="seob-map-row">
							<span class="seob-swatch is-static" style="background:<?php echo esc_attr( $old ); ?>"></span>
							<code><?php echo esc_html( $old ); ?></code>
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<input type="hidden" name="color_old[<?php echo esc_attr( $mode . $i ); ?>]" value="<?php echo esc_attr( $old ); ?>">
							<input type="color" name="color_new[<?php echo esc_attr( $mode . $i ); ?>]" value="<?php echo esc_attr( preg_match( '/^#[0-9a-f]{6}$/i', $new ) ? $new : $old ); ?>">
						</div>
					<?php endforeach; ?>
					<p class="seob-help"><?php esc_html_e( 'Left: color in the saved layout. Right: color it becomes on this site. Keep it the same to leave it unchanged. Links to old client sites are fixed automatically.', 'seo-boster' ); ?></p>
				</div>
			<?php endforeach; ?>

			<div class="seob-field">
				<label class="seob-check"><input type="checkbox" name="faq_schema" value="1" <?php checked( $d['faq_schema'] ); ?>> <?php esc_html_e( 'Add FAQ structured data (Google rich results) to every Area page', 'seo-boster' ); ?></label>
			</div>

			<div class="seob-actions">
				<button type="submit" class="seob-btn seob-btn-primary seob-btn-lg"><?php esc_html_e( 'Save and install design', 'seo-boster' ); ?></button>
				<?php if ( $d['divi_template'] ) : ?>
					<span class="seob-help"><?php esc_html_e( 'Divi template already installed. Installing again adds a fresh copy; remove the old one in Divi > Theme Builder.', 'seo-boster' ); ?></span>
				<?php elseif ( $d['el_template'] ) : ?>
					<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $d['el_template'] . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Edit Elementor template', 'seo-boster' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		</details>
		<?php
	}

	private function screen_settings() {
		$s = SEOB_AP_AI::settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card" autocomplete="off">
			<?php SEOB_Security::field( 'seob_ap_save_ai' ); ?>
			<input type="hidden" name="action" value="seob_ap_save_ai">
			<h3><?php esc_html_e( 'AI writer', 'seo-boster' ); ?></h3>
			<div class="seob-form-grid">
				<div class="seob-span-2">
					<label class="seob-label" for="seob-provider"><?php esc_html_e( 'AI provider', 'seo-boster' ); ?></label>
					<select id="seob-provider" name="provider" class="seob-select">
						<?php
						$saved = SEOB_AP_AI::saved_keys();
						foreach ( SEOB_AP_AI::providers() as $pid => $pinfo ) :
							?>
							<option value="<?php echo esc_attr( $pid ); ?>" data-keys="<?php echo esc_attr( $pinfo['keys'] ); ?>" data-model="<?php echo esc_attr( $pinfo['fast'] ); ?>" data-saved="<?php echo isset( $saved[ $pid ] ) ? esc_attr( $saved[ $pid ] ) : ''; ?>" <?php selected( $s['provider'], $pid ); ?>>
								<?php echo esc_html( $pinfo['name'] . ( isset( $saved[ $pid ] ) ? '  (' . __( 'key saved', 'seo-boster' ) . ')' : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="seob-help" id="seob-keys-help"></p>
				</div>
				<div class="seob-span-2" id="seob-base-wrap" <?php echo 'custom' === $s['provider'] ? '' : 'hidden'; ?>>
					<label class="seob-label" for="seob-base"><?php esc_html_e( 'API address (OpenAI-compatible)', 'seo-boster' ); ?></label>
					<input id="seob-base" class="seob-input" type="url" name="base_url" value="<?php echo esc_attr( $s['base_url'] ); ?>" placeholder="https://api.together.xyz/v1">
					<p class="seob-help"><?php esc_html_e( 'Any provider that works like the OpenAI API: Together, Fireworks, Perplexity, Cerebras, Azure, your own server... Paste the address that ends in /v1 (the plugin adds /chat/completions).', 'seo-boster' ); ?></p>
				</div>
				<div>
					<label class="seob-label" for="seob-speed"><?php esc_html_e( 'Writing speed', 'seo-boster' ); ?></label>
					<select id="seob-speed" name="speed" class="seob-select">
						<option value="fast" <?php selected( $s['speed'], 'fast' ); ?>><?php esc_html_e( 'Fast (quicker, lower cost)', 'seo-boster' ); ?></option>
						<option value="balanced" <?php selected( $s['speed'], 'balanced' ); ?>><?php esc_html_e( 'Best quality (slower)', 'seo-boster' ); ?></option>
					</select>
				</div>
				<div>
					<label class="seob-label" for="seob-parallel"><?php esc_html_e( 'Pages written at the same time', 'seo-boster' ); ?></label>
					<select id="seob-parallel" name="parallel" class="seob-select">
						<?php foreach ( array( 1, 2, 3, 4, 5, 6, 8 ) as $pn ) : ?>
							<option value="<?php echo (int) $pn; ?>" <?php selected( SEOB_AP_Jobs::parallel(), $pn ); ?>><?php echo (int) $pn; ?></option>
						<?php endforeach; ?>
					</select>
					<p class="seob-help"><?php esc_html_e( '4 is safe on most hosting. Lower it if the host shows errors.', 'seo-boster' ); ?></p>
				</div>
				<div>
					<label class="seob-label" for="seob-model"><?php esc_html_e( 'Model', 'seo-boster' ); ?></label>
					<input id="seob-model" class="seob-input" type="text" name="model" list="seob-model-list" value="<?php echo esc_attr( $s['model'] ); ?>" placeholder="<?php echo esc_attr( SEOB_AP_AI::model() ? SEOB_AP_AI::model() : __( 'Automatic', 'seo-boster' ) ); ?>">
					<datalist id="seob-model-list"></datalist>
					<p class="seob-help"><?php esc_html_e( 'Leave empty and the plugin picks a good model for your key automatically. Or type any model name.', 'seo-boster' ); ?>
						<?php if ( SEOB_AP_AI::has_key() ) : ?>
							<button type="button" class="button-link" id="seob-load-models" data-nonce="<?php echo esc_attr( wp_create_nonce( 'seob_ap_models' ) ); ?>"><?php esc_html_e( 'Show my models', 'seo-boster' ); ?></button>
							<span id="seob-models-msg"></span>
						<?php endif; ?>
					</p>
				</div>
				<div class="seob-span-2">
					<label class="seob-label" for="seob-key"><?php esc_html_e( 'API key', 'seo-boster' ); ?></label>
					<input id="seob-key" class="seob-input" type="password" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo $s['last4'] ? esc_attr( sprintf( /* translators: %s last 4 chars */ __( 'Saved key ending in %s. Type a new one to replace it.', 'seo-boster' ), $s['last4'] ) ) : esc_attr__( 'Paste your API key', 'seo-boster' ); ?>">
					<p class="seob-help"><?php esc_html_e( 'Each provider keeps its own key, so you can switch any time. Stored encrypted, never shown again, and only sent to that provider\'s API.', 'seo-boster' ); ?></p>
				</div>
			</div>
			<script>
			( function () {
				var sel = document.getElementById( 'seob-provider' ), help = document.getElementById( 'seob-keys-help' ),
					base = document.getElementById( 'seob-base-wrap' ), key = document.getElementById( 'seob-key' ),
					startProvider = sel.value;
				function upd() {
					var o = sel.options[ sel.selectedIndex ], k = o.getAttribute( 'data-keys' ), saved = o.getAttribute( 'data-saved' );
					help.textContent = k ? <?php echo wp_json_encode( __( 'Get a key at:', 'seo-boster' ) ); ?> + ' ' + k : '';
					base.hidden = sel.value !== 'custom';
					key.placeholder = saved ? <?php echo wp_json_encode( __( 'Saved key ending in', 'seo-boster' ) ); ?> + ' ' + saved + '. ' + <?php echo wp_json_encode( __( 'Type a new one to replace it.', 'seo-boster' ) ); ?> : <?php echo wp_json_encode( __( 'Paste your API key', 'seo-boster' ) ); ?>;
					if ( sel.value !== startProvider ) { document.getElementById( 'seob-model' ).value = ''; }
				}
				sel.addEventListener( 'change', upd ); upd();
				var btn = document.getElementById( 'seob-load-models' );
				if ( btn ) {
					btn.addEventListener( 'click', function () {
						var msg = document.getElementById( 'seob-models-msg' ), fd = new FormData();
						if ( sel.value !== startProvider ) { msg.textContent = <?php echo wp_json_encode( __( 'Save first, then show models.', 'seo-boster' ) ); ?>; return; }
						fd.append( 'action', 'seob_ap_models' ); fd.append( 'nonce', btn.getAttribute( 'data-nonce' ) );
						msg.textContent = '...';
						fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
							if ( ! r.success ) { msg.textContent = r.data && r.data.message ? r.data.message : 'Error'; return; }
							var dl = document.getElementById( 'seob-model-list' ); dl.innerHTML = '';
							r.data.models.forEach( function ( m ) { var o = document.createElement( 'option' ); o.value = m; dl.appendChild( o ); } );
							msg.textContent = r.data.models.length + ' ' + <?php echo wp_json_encode( __( 'models found. Click the Model box to choose one.', 'seo-boster' ) ); ?>;
						} ).catch( function () { msg.textContent = 'Error'; } );
					} );
				}
			}() );
			</script>
			<div class="seob-actions">
				<button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Save', 'seo-boster' ); ?></button>
				<?php if ( $s['last4'] ) : ?>
					<button type="submit" name="forget" value="1" class="seob-btn seob-btn-ghost seob-danger" formnovalidate><?php esc_html_e( 'Remove key', 'seo-boster' ); ?></button>
				<?php endif; ?>
			</div>
		</form>
		<?php
		$img    = SEOB_AP_Images::settings();
		$msrc   = SEOB_AP_Images::magnific_key_source();
		$merror = (string) get_option( 'seob_magnific_error', '' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card" autocomplete="off">
			<?php SEOB_Security::field( 'seob_ap_save_images' ); ?>
			<input type="hidden" name="action" value="seob_ap_save_images">
			<h3><?php esc_html_e( 'Automatic images', 'seo-boster' ); ?></h3>
			<span class="seob-label"><?php esc_html_e( 'Where photos come from', 'seo-boster' ); ?></span>
			<label class="seob-check"><input type="radio" name="source" value="magnific" <?php checked( $img['source'], 'magnific' ); ?>> <strong><?php esc_html_e( 'Magnific (recommended)', 'seo-boster' ); ?></strong> <span class="seob-help"><?php esc_html_e( 'Every page gets its own new stock photos from Magnific, saved in the Media Library with the page name and alt text. If Magnific has too few, Media Library photos fill the gap.', 'seo-boster' ); ?></span></label>
			<label class="seob-check"><input type="radio" name="source" value="library" <?php checked( $img['source'], 'library' ); ?>> <?php esc_html_e( 'Media Library first (the client\'s own photos), Pexels when there are too few', 'seo-boster' ); ?></label>

			<label class="seob-label" for="seob-magnific"><?php esc_html_e( 'Magnific API key', 'seo-boster' ); ?></label>
			<?php
			if ( 'agency' === $msrc ) {
				$mph = __( 'Agency key built into the plugin is used. Paste a key only to use a different one on this site.', 'seo-boster' );
			} elseif ( 'config' === $msrc ) {
				$mph = __( 'Key from wp-config.php is used.', 'seo-boster' );
			} elseif ( 'site' === $msrc ) {
				/* translators: %s last 4 chars */
				$mph = sprintf( __( 'Saved key ending in %s. Type a new one to replace it.', 'seo-boster' ), $img['magnific_last4'] );
			} else {
				$mph = __( 'Paste your Magnific API key', 'seo-boster' );
			}
			?>
			<input id="seob-magnific" class="seob-input" type="password" name="magnific_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $mph ); ?>">
			<?php if ( 'agency' === $msrc ) : ?>
				<p class="seob-status-ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Ready: the agency Magnific key is set by default, nothing to type.', 'seo-boster' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $merror ) : ?>
				<p class="seob-status-bad"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( sprintf( /* translators: %s error */ __( 'Last Magnific error: %s', 'seo-boster' ), $merror ) ); ?></p>
			<?php endif; ?>

			<label class="seob-label" for="seob-pexels"><?php esc_html_e( 'Pexels API key (optional)', 'seo-boster' ); ?></label>
			<input id="seob-pexels" class="seob-input" type="password" name="pexels_key" value="" autocomplete="new-password" placeholder="<?php echo $img['pexels_last4'] ? esc_attr( sprintf( /* translators: %s last 4 */ __( 'Saved key ending in %s', 'seo-boster' ), $img['pexels_last4'] ) ) : esc_attr__( 'Paste your Pexels API key', 'seo-boster' ); ?>">
			<div class="seob-actions">
				<button type="submit" class="seob-btn seob-btn-primary"><?php esc_html_e( 'Save', 'seo-boster' ); ?></button>
				<button type="submit" name="do" value="test_magnific" class="seob-btn seob-btn-ghost"><?php esc_html_e( 'Test Magnific', 'seo-boster' ); ?></button>
				<?php if ( 'site' === $msrc ) : ?>
					<button type="submit" name="do" value="forget_magnific" class="seob-btn seob-btn-ghost seob-danger"><?php esc_html_e( 'Remove site Magnific key', 'seo-boster' ); ?></button>
				<?php endif; ?>
				<?php if ( $img['pexels_last4'] ) : ?>
					<button type="submit" name="forget" value="1" class="seob-btn seob-btn-ghost seob-danger"><?php esc_html_e( 'Remove Pexels key', 'seo-boster' ); ?></button>
				<?php endif; ?>
			</div>
		</form>
		<?php if ( SEOB_AP_AI::has_key() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seob-card">
				<?php SEOB_Security::field( 'seob_ap_test_ai' ); ?>
				<input type="hidden" name="action" value="seob_ap_test_ai">
				<h3><?php esc_html_e( 'Test connection', 'seo-boster' ); ?></h3>
				<p class="seob-help"><?php esc_html_e( 'Sends one tiny request to check the key and model.', 'seo-boster' ); ?></p>
				<button type="submit" class="seob-btn seob-btn-ghost"><?php esc_html_e( 'Test now', 'seo-boster' ); ?></button>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * AJAX handler for parsing and clustering SEMrush CSV.
	 */
	public function ajax_semrush_csv() {
		check_ajax_referer( 'seob_ap_semrush', 'nonce' );
		if ( ! SEOB_Security::can_use() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$csv_content = isset( $_POST['csv_content'] ) ? (string) wp_unslash( $_POST['csv_content'] ) : '';
		if ( empty( $csv_content ) && ! empty( $_FILES['file']['tmp_name'] ) ) {
			$csv_content = (string) file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$services  = self::split_list( isset( $_POST['services'] ) ? sanitize_textarea_field( wp_unslash( $_POST['services'] ) ) : '', self::MAX_SERVICES );
		$locations = self::split_list( isset( $_POST['locations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['locations'] ) ) : '', self::MAX_LOCATIONS );

		if ( empty( $csv_content ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV file content was empty.', 'seo-boster' ) ) );
		}

		$parsed   = SEOB_CSV_Parser::parse_semrush_csv( $csv_content );
		$clusters = SEOB_CSV_Parser::cluster_keywords( $parsed, $services, $locations );

		$lines = array();
		foreach ( $services as $s_name ) {
			$service_kws = array();
			foreach ( $locations as $l_name ) {
				$k = $s_name . '|' . $l_name;
				if ( ! empty( $clusters[ $k ]['primary_keyword'] ) ) {
					$service_kws[] = $clusters[ $k ]['primary_keyword'];
				}
				if ( ! empty( $clusters[ $k ]['secondary_keywords'] ) ) {
					$service_kws = array_merge( $service_kws, $clusters[ $k ]['secondary_keywords'] );
				}
			}
			$service_kws = array_unique( $service_kws );
			$lines[]     = implode( ', ', array_slice( $service_kws, 0, 8 ) );
		}

		wp_send_json_success(
			array(
				'keywords_text' => implode( "\n", $lines ),
				'total_rows'    => count( $parsed ),
				'clusters'      => count( $clusters ),
			)
		);
	}

	/**
	 * Handle publishing approved drafts from the Review & Approval table.
	 */
	public function handle_approval_publish() {
		SEOB_Security::verify( 'seob_ap_approval' );

		$post_ids    = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) ) : array();
		$publish_all = ! empty( $_POST['publish_all'] );

		if ( $publish_all ) {
			$drafts   = get_posts(
				array(
					'post_type'      => SEOB_AP_Schema::POST_TYPE,
					'post_status'    => 'draft',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			$post_ids = $drafts;
		}

		$count = 0;
		foreach ( $post_ids as $pid ) {
			if ( $pid && current_user_can( 'edit_post', $pid ) ) {
				wp_update_post(
					array(
						'ID'          => $pid,
						'post_status' => 'publish',
					)
				);
				++$count;
			}
		}

		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'approval', 'seob_msg' => 'app_publish', 'n' => $count ) ) );
	}

	/**
	 * Handle 1-click rollback of an Area Page from the Review & Approval table.
	 */
	public function handle_approval_rollback() {
		SEOB_Security::verify( 'seob_ap_rollback' );

		$post_id = absint( SEOB_Security::post_text( 'post_id' ) );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'approval', 'seob_msg' => 'forbidden' ) ) );
		}

		$res = SEOB_Backup_Manager::rollback( $post_id );
		if ( is_wp_error( $res ) ) {
			set_transient( 'seob_ap_err_' . get_current_user_id(), $res->get_error_message(), 60 );
			SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'approval', 'seob_msg' => 'design_fail' ) ) );
		}

		SEOB_Security::redirect( $this->admin_url( array( 'tab' => 'approval', 'seob_msg' => 'app_rollback' ) ) );
	}

	/**
	 * Screen: Side-by-Side Review & Approval Table (Pillar 6 Human-in-the-Loop).
	 */
	private function screen_approval() {
		$posts = get_posts(
			array(
				'post_type'      => SEOB_AP_Schema::POST_TYPE,
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$draft_count = 0;
		foreach ( $posts as $p ) {
			if ( 'draft' === $p->post_status ) {
				++$draft_count;
			}
		}
		?>
		<div class="seob-card">
			<div class="seob-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
				<div>
					<h2 class="seob-card-title"><?php esc_html_e( 'Side-by-Side Review & Approval Table', 'seo-boster' ); ?></h2>
					<p class="seob-card-sub"><?php esc_html_e( 'Human-in-the-Loop safety guardrail: review projected Rank Math 85+ scores, keywords, and meta lengths before publishing.', 'seo-boster' ); ?></p>
				</div>
				<?php if ( $draft_count > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php SEOB_Security::field( 'seob_ap_approval' ); ?>
						<input type="hidden" name="action" value="seob_ap_approval_publish" />
						<input type="hidden" name="publish_all" value="1" />
						<button type="submit" class="seob-btn seob-btn-primary" onclick="return confirm('<?php esc_attr_e( 'Publish all draft area pages now?', 'seo-boster' ); ?>');">
							<span class="dashicons dashicons-yes-alt"></span> <?php printf( esc_html__( 'Publish All %d Drafts', 'seo-boster' ), $draft_count ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-approval-bulk-form">
				<?php SEOB_Security::field( 'seob_ap_approval' ); ?>
				<input type="hidden" name="action" value="seob_ap_approval_publish" />

				<table class="wp-list-table widefat fixed striped" style="margin-top: 15px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
					<thead>
						<tr>
							<th style="width: 32px;"><input type="checkbox" id="seob-app-select-all" onclick="jQuery('.seob-cb-app').prop('checked', this.checked);" /></th>
							<th style="width: 220px;"><?php esc_html_e( 'Page Name / Location', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'Focus Keyword', 'seo-boster' ); ?></th>
							<th style="width: 140px; text-align: center;"><?php esc_html_e( 'Projected Score', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'Proposed SEO Title', 'seo-boster' ); ?></th>
							<th><?php esc_html_e( 'Meta Description', 'seo-boster' ); ?></th>
							<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Status', 'seo-boster' ); ?></th>
							<th style="width: 130px; text-align: right;"><?php esc_html_e( 'Actions', 'seo-boster' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $posts ) ) : ?>
							<tr><td colspan="8"><?php esc_html_e( 'No area pages generated yet. Go to "Create pages" to create your first batch.', 'seo-boster' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $posts as $p ) : ?>
								<?php
								$eval     = SEOB_RankMath_Bridge::evaluate_score( $p->ID );
								$score    = $eval['score'];
								$kw       = get_post_meta( $p->ID, 'rank_math_focus_keyword', true );
								if ( empty( $kw ) ) {
									$kw = get_post_meta( $p->ID, '_seob_ap_keyword', true );
								}
								$t        = get_post_meta( $p->ID, 'rank_math_title', true );
								$t        = $t ? $t : $p->post_title;
								$d        = get_post_meta( $p->ID, 'rank_math_description', true );
								$d        = $d ? $d : $p->post_excerpt;
								$loc      = get_post_meta( $p->ID, 'location', true );
								$badge_bg = ( $score >= 80 ) ? '#22c55e' : ( ( $score >= 50 ) ? '#eab308' : '#ef4444' );
								$has_snap = SEOB_Backup_Manager::has_snapshot( $p->ID );
								?>
								<tr>
									<td>
										<?php if ( 'draft' === $p->post_status ) : ?>
											<input type="checkbox" class="seob-cb-app" name="post_ids[]" value="<?php echo esc_attr( $p->ID ); ?>" />
										<?php endif; ?>
									</td>
									<td>
										<strong><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" target="_blank"><?php echo esc_html( $p->post_title ); ?></a></strong>
										<?php if ( $loc ) : ?>
											<br><small style="color: #64748b;"><?php echo esc_html( $loc ); ?></small>
										<?php endif; ?>
									</td>
									<td><strong><?php echo esc_html( $kw ); ?></strong></td>
									<td style="text-align: center;">
										<span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: bold; color: #fff; background: <?php echo esc_attr( $badge_bg ); ?>;">
											<?php echo esc_html( (string) $score ); ?> / 100
										</span>
									</td>
									<td>
										<div style="font-size: 12px;"><?php echo esc_html( $t ); ?></div>
										<small style="color: <?php echo ( mb_strlen( $t ) >= 45 && mb_strlen( $t ) <= 65 ) ? '#16a34a' : '#ea580c'; ?>;">
											<?php echo esc_html( sprintf( __( '%d chars (ideal: 50-60)', 'seo-boster' ), mb_strlen( $t ) ) ); ?>
										</small>
									</td>
									<td>
										<div style="font-size: 12px;"><?php echo esc_html( mb_substr( $d, 0, 70 ) . ( mb_strlen( $d ) > 70 ? '...' : '' ) ); ?></div>
										<small style="color: <?php echo ( mb_strlen( $d ) >= 140 && mb_strlen( $d ) <= 165 ) ? '#16a34a' : '#ea580c'; ?>;">
											<?php echo esc_html( sprintf( __( '%d chars (ideal: 150-158)', 'seo-boster' ), mb_strlen( $d ) ) ); ?>
										</small>
									</td>
									<td style="text-align: center;">
										<span class="badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: 600; background: <?php echo ( 'publish' === $p->post_status ) ? '#dcfce7; color: #166534;' : '#fef9c3; color: #854d0e;'; ?>">
											<?php echo esc_html( $p->post_status ); ?>
										</span>
									</td>
									<td style="text-align: right; white-space: nowrap;">
										<?php if ( 'draft' === $p->post_status ) : ?>
											<button type="submit" form="seob-pub-single-<?php echo esc_attr( $p->ID ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'seo-boster' ); ?></button>
										<?php else : ?>
											<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'View', 'seo-boster' ); ?></a>
										<?php endif; ?>
										<?php if ( $has_snap ) : ?>
											<button type="submit" form="seob-app-roll-<?php echo esc_attr( $p->ID ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Rollback this page to previous snapshot?', 'seo-boster' ); ?>');" title="<?php echo esc_attr( SEOB_Backup_Manager::get_snapshot_info( $p->ID ) ); ?>"><?php esc_html_e( 'Rollback', 'seo-boster' ); ?></button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>

			<?php foreach ( $posts as $p ) : ?>
				<?php if ( 'draft' === $p->post_status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-pub-single-<?php echo esc_attr( $p->ID ); ?>">
						<?php SEOB_Security::field( 'seob_ap_approval' ); ?>
						<input type="hidden" name="action" value="seob_ap_approval_publish" />
						<input type="hidden" name="post_ids[]" value="<?php echo esc_attr( $p->ID ); ?>" />
					</form>
				<?php endif; ?>
				<?php if ( SEOB_Backup_Manager::has_snapshot( $p->ID ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-app-roll-<?php echo esc_attr( $p->ID ); ?>">
						<?php SEOB_Security::field( 'seob_ap_rollback' ); ?>
						<input type="hidden" name="action" value="seob_ap_approval_rollback" />
						<input type="hidden" name="post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
					</form>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
