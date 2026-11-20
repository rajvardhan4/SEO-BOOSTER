<?php
/**
 * Menu and hub page for Area pages.
 *
 * Menu: adds "Areas We Serve" > Locations > Services (or Services > Locations) to a menu
 * the site already has. Only items made by SEO Boster are ever changed or removed.
 * Hub page: an "Areas We Serve" page with a banner and one card per location listing its services.
 * Both use the same order, set by drag and drop.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Nav {

	const OPTION    = 'seob_ap_nav';
	const SHORTCODE = 'seob_areas_we_serve';

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'menu_id'   => 0,
				'synced'    => 0,
				'menu_in'   => 0,
				'error'     => '',
				'label'     => 'Areas We Serve',
				'group'     => 'location',
				'loc_order' => array(),
				'svc_order' => array(),
				'items'     => array(),
				'auto'      => 1,
				'hub_id'    => 0,
				'hub_title' => 'Areas We Serve',
				'hub_intro' => 'We proudly serve customers across these areas. Choose your location to see the services we offer near you.',
			)
		);
	}

	public static function save( array $s ) {
		update_option( self::OPTION, array_merge( self::settings(), $s ), false );
	}

	public function hooks() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'seob_ap_menu_sync', array( __CLASS__, 'sync' ) );
		add_action( 'admin_init', array( __CLASS__, 'first_sync' ) );
		add_action( 'admin_post_seob_ap_nav', array( __CLASS__, 'handle' ) );
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'item_classes' ), 99, 2 );
	}

	/** Menus built by 1.8.2 and older carry "mega-menu" on Areas We Serve; drop it when shown. */
	public static function item_classes( $classes, $item ) {
		if ( is_array( $classes ) && in_array( 'seob-aws-top', $classes, true ) ) {
			$classes = array_values( array_diff( $classes, array( 'mega-menu' ) ) );
		}
		return $classes;
	}

	/* ==================================================================
	 * Data
	 * ================================================================== */

	/**
	 * Published Area pages with their service and location.
	 *
	 * @return array[] id, url, service, location.
	 */
	public static function pages() {
		$posts = get_posts(
			array(
				'post_type'      => SEOB_AP_Schema::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 2000,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$f     = SEOB_AP_Layout::facts( $p->ID );
			$out[] = array(
				'id'       => $p->ID,
				'url'      => get_permalink( $p ),
				'service'  => '' !== $f['{service}'] ? $f['{service}'] : get_the_title( $p ),
				'location' => '' !== $f['{location}'] ? $f['{location}'] : __( 'Other', 'seo-boster' ),
			);
		}
		return $out;
	}

	/**
	 * Names in saved order; new names go to the end alphabetically.
	 *
	 * @param string[] $names Names found.
	 * @param string[] $order Saved order.
	 */
	public static function ordered( array $names, array $order ) {
		$names = array_values( array_unique( $names ) );
		$known = array_values( array_intersect( $order, $names ) );
		$rest  = array_diff( $names, $known );
		natcasesort( $rest );
		return array_merge( $known, array_values( $rest ) );
	}

	/**
	 * Tree: group name => list of [ name, url, id ], in the saved order.
	 *
	 * @param array|null $s Settings.
	 */
	public static function tree( $s = null ) {
		$s     = $s ? $s : self::settings();
		$pages = self::pages();
		$locs  = self::ordered( wp_list_pluck( $pages, 'location' ), (array) $s['loc_order'] );
		$svcs  = self::ordered( wp_list_pluck( $pages, 'service' ), (array) $s['svc_order'] );
		$by    = 'service' === $s['group'] ? 'service' : 'location';
		$child = 'service' === $by ? 'location' : 'service';
		$outer = 'service' === $by ? $svcs : $locs;
		$inner = 'service' === $by ? $locs : $svcs;

		$tree = array();
		foreach ( $outer as $o ) {
			$kids = array();
			foreach ( $pages as $p ) {
				if ( $p[ $by ] === $o ) {
					$kids[ $p[ $child ] ] = array( 'name' => $p[ $child ], 'url' => $p['url'], 'id' => $p['id'] );
				}
			}
			$sorted = array();
			foreach ( $inner as $i ) {
				if ( isset( $kids[ $i ] ) ) {
					$sorted[] = $kids[ $i ];
				}
			}
			if ( $sorted ) {
				$tree[ $o ] = $sorted;
			}
		}
		return $tree;
	}

	public static function hub_url() {
		$id = (int) self::settings()['hub_id'];
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
	}

	/* ==================================================================
	 * Menu
	 * ================================================================== */

	/**
	 * The menu shown in the site's main header (the "live" menu).
	 *
	 * @return int Menu term ID or 0.
	 */
	public static function live_menu() {
		$locations = array_filter( (array) get_nav_menu_locations() );
		if ( ! $locations ) {
			return 0;
		}
		foreach ( array( 'primary-menu', 'primary', 'main', 'main-menu', 'main_menu', 'menu-1', 'header', 'header-menu', 'top', 'top-menu', 'primary_navigation', 'menu_primary' ) as $loc ) {
			if ( ! empty( $locations[ $loc ] ) && wp_get_nav_menu_object( (int) $locations[ $loc ] ) ) {
				return (int) $locations[ $loc ];
			}
		}
		// Any location whose name says main, primary or header.
		foreach ( $locations as $loc => $id ) {
			if ( preg_match( '/primary|main|header/i', $loc ) && wp_get_nav_menu_object( (int) $id ) ) {
				return (int) $id;
			}
		}
		$first = (int) reset( $locations );
		return wp_get_nav_menu_object( $first ) ? $first : 0;
	}

	/** The menu to use: the one chosen in the settings, or the live header menu. */
	public static function menu_id() {
		$id = (int) self::settings()['menu_id'];
		return $id && wp_get_nav_menu_object( $id ) ? $id : self::live_menu();
	}

	/**
	 * Put (or refresh) the Areas We Serve items in the site menu.
	 * The block goes before "Contact" the first time; after that it stays where it is,
	 * even if someone moved it in Appearance > Menus.
	 *
	 * @return int|WP_Error Number of items added.
	 */
	public static function build_menu() {
		$s    = self::settings();
		$menu = wp_get_nav_menu_object( self::menu_id() );
		if ( ! $menu ) {
			return new WP_Error( 'seob_menu', __( 'This site has no menu in the header yet. Create one in Appearance > Menus and set it as the main menu.', 'seo-boster' ) );
		}
		$tree = self::tree( $s );

		// Where the block goes.
		$all      = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) );
		$all      = $all ? $all : array();
		$position = null;
		foreach ( $all as $it ) {
			if ( get_post_meta( $it->ID, '_seob_ap_menu_top', true ) ) {
				$position = (int) $it->menu_order;
				break;
			}
		}
		$mine = array();
		foreach ( $all as $it ) {
			if ( get_post_meta( $it->ID, '_seob_ap_menu', true ) ) {
				$mine[] = (int) $it->ID;
			}
		}
		if ( null === $position ) {
			foreach ( $all as $it ) {
				if ( ! (int) $it->menu_item_parent && ! in_array( (int) $it->ID, $mine, true ) && preg_match( '/contact|get in touch|book|appointment|quote|estimate/i', $it->title . ' ' . $it->url ) ) {
					$position = (int) $it->menu_order;
					break;
				}
			}
		}

		self::remove_menu();
		if ( ! $tree ) {
			return 0; // No published Area pages: nothing to show.
		}

		// Other items keep their order; the block is placed in between.
		$others = array();
		foreach ( $all as $it ) {
			if ( ! in_array( (int) $it->ID, $mine, true ) && get_post( $it->ID ) ) {
				$others[] = $it;
			}
		}
		$before = array();
		$after  = array();
		foreach ( $others as $it ) {
			if ( null !== $position && (int) $it->menu_order >= $position ) {
				$after[] = $it;
			} else {
				$before[] = $it;
			}
		}

		$order = count( $before );
		$hub   = self::hub_url();
		$ids   = array();
		$add   = static function ( $args ) use ( $menu, &$order, &$ids ) {
			++$order;
			$args['menu-item-position'] = $order;
			$args['menu-item-status']   = 'publish';
			$id                         = wp_update_nav_menu_item( $menu->term_id, 0, $args );
			if ( is_wp_error( $id ) ) {
				--$order;
				return 0;
			}
			$ids[] = (int) $id;
			update_post_meta( $id, '_seob_ap_menu', 1 );
			return (int) $id;
		};

		// No Divi "mega-menu" class: it breaks inside Theme Builder menus (columns pile up).
		// Locations show as a clean list and each one opens its services to the side.
		$mega = '';
		$top  = $add(
			array(
				'menu-item-title'   => $s['label'],
				'menu-item-url'     => $hub ? $hub : '#',
				'menu-item-type'    => 'custom',
				'menu-item-classes' => 'seob-aws seob-aws-top' . $mega,
			)
		);
		if ( ! $top ) {
			return new WP_Error( 'seob_menu', __( 'The menu item could not be added.', 'seo-boster' ) );
		}
		update_post_meta( $top, '_seob_ap_menu_top', 1 );
		foreach ( $tree as $group => $kids ) {
			$gid = $add(
				array(
					'menu-item-title'     => $group,
					'menu-item-url'       => $hub ? $hub . ( 'location' === $s['group'] ? '#' . sanitize_title( $group ) : '' ) : '#',
					'menu-item-type'      => 'custom',
					'menu-item-parent-id' => $top,
					'menu-item-classes'   => 'seob-aws',
				)
			);
			if ( ! $gid ) {
				continue;
			}
			foreach ( $kids as $k ) {
				$add(
					array(
						'menu-item-title'     => $k['name'],
						'menu-item-object'    => SEOB_AP_Schema::POST_TYPE,
						'menu-item-object-id' => $k['id'],
						'menu-item-type'      => 'post_type',
						'menu-item-parent-id' => $gid,
						'menu-item-classes'   => 'seob-aws',
					)
				);
			}
		}

		// Items after the block move down so the menu order stays clean.
		global $wpdb;
		foreach ( $after as $it ) {
			++$order;
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $order ), array( 'ID' => (int) $it->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( (int) $it->ID );
		}

		self::save(
			array(
				'items'   => $ids,
				'synced'  => time(),
				'menu_in' => (int) $menu->term_id,
			)
		);
		return count( $ids );
	}

	/**
	 * Remove only the items SEO Boster added.
	 */
	public static function remove_menu() {
		$s   = self::settings();
		$ids = array_map( 'absint', (array) $s['items'] );
		$more = get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'any', 'meta_key' => '_seob_ap_menu', 'fields' => 'ids', 'posts_per_page' => -1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( array_unique( array_merge( $ids, $more ) ) as $id ) {
			if ( $id && 'nav_menu_item' === get_post_type( $id ) ) {
				wp_delete_post( $id, true );
			}
		}
		self::save( array( 'items' => array() ) );
	}

	/**
	 * Keep the menu up to date when Area pages are published or removed.
	 * Runs once at the end of the request, so publishing 50 pages rebuilds the menu one time.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function on_status( $new, $old, $post ) {
		if ( SEOB_AP_Schema::POST_TYPE !== $post->post_type || $new === $old || ( 'publish' !== $new && 'publish' !== $old ) ) {
			return;
		}
		if ( self::settings()['auto'] && ! has_action( 'shutdown', array( __CLASS__, 'sync' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'sync' ) );
		}
	}

	/** Rebuild the menu items (automatic mode). */
	public static function sync() {
		$s = self::settings();
		if ( ! $s['auto'] ) {
			return;
		}
		$r = self::build_menu();
		if ( is_wp_error( $r ) ) {
			self::save( array( 'error' => $r->get_error_message() ) );
		} else {
			self::save( array( 'error' => '' ) );
		}
	}

	/**
	 * Sites that already have published Area pages get the menu once, without a click.
	 */
	public static function first_sync() {
		if ( get_option( 'seob_ap_menu_first' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'seob_ap_menu_first', 1, false );
		$s = self::settings();
		if ( $s['auto'] && ! $s['items'] && self::pages() ) {
			self::sync();
		}
	}

	/* ==================================================================
	 * Hub page
	 * ================================================================== */

	public static function build_hub() {
		$s       = self::settings();
		$content = '[' . self::SHORTCODE . ']';
		$id      = (int) $s['hub_id'];
		$arr     = array(
			'post_title'   => $s['hub_title'],
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			$arr['ID'] = $id;
			unset( $arr['post_content'] ); // Keep anything the user added around the shortcode.
			$id = wp_update_post( $arr, true );
		} else {
			$arr['post_name'] = 'service-areas';
			$id               = wp_insert_post( $arr, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// Divi: full width, no duplicate page title.
		update_post_meta( $id, '_et_pb_page_layout', 'et_full_width_page' );
		update_post_meta( $id, '_et_pb_show_title', 'off' );
		self::save( array( 'hub_id' => (int) $id ) );
		return (int) $id;
	}

	/**
	 * The hub page body.
	 */
	public static function shortcode() {
		$s      = self::settings();
		$layout = SEOB_AP_Layout::get();
		wp_enqueue_style( 'seob-area', SEOB_URL . 'assets/css/area-pages.css', array(), SEOB_VERSION );
		wp_add_inline_style( 'seob-area', SEOB_AP_Layout::css_vars( $layout['global'] ) );

		$tree = self::tree( array_merge( $s, array( 'group' => 'location' ) ) );
		ob_start();
		echo '<div class="seob-area seob-hub seob-preset-sco alignfull">';
		echo '<section class="seob-sec seob-sec-hero seob-bg-accent seob-align-center"><div class="seob-area-wrap"><div class="seob-hero-in">';
		echo '<h1>' . esc_html( $s['hub_title'] ) . '</h1>';
		if ( '' !== trim( $s['hub_intro'] ) ) {
			echo '<p class="seob-hero-sub">' . esc_html( $s['hub_intro'] ) . '</p>';
		}
		echo '</div></div></section>';
		echo '<section class="seob-sec seob-bg-soft"><div class="seob-area-wrap"><div class="seob-hub-grid">';
		foreach ( $tree as $loc => $kids ) {
			echo '<div class="seob-hub-card" id="' . esc_attr( sanitize_title( $loc ) ) . '">';
			echo '<h2><span class="dashicons dashicons-location" aria-hidden="true"></span>' . esc_html( $loc ) . '</h2><ul>';
			foreach ( $kids as $k ) {
				echo '<li><a href="' . esc_url( $k['url'] ) . '">' . esc_html( $k['name'] ) . '</a></li>';
			}
			echo '</ul></div>';
		}
		if ( ! $tree ) {
			echo '<p>' . esc_html__( 'Service areas will appear here soon.', 'seo-boster' ) . '</p>';
		}
		echo '</div></div></section></div>';
		wp_enqueue_style( 'dashicons' );
		return ob_get_clean();
	}

	/* ==================================================================
	 * Admin
	 * ================================================================== */

	public static function handle() {
		SEOB_Security::verify( 'seob_ap_nav' );
		$do  = sanitize_key( SEOB_Security::post_text( 'do' ) );
		$new = array(
			'menu_id'   => absint( SEOB_Security::post_text( 'menu_id' ) ),
			'label'     => SEOB_Security::post_text( 'label' ) ? SEOB_Security::post_text( 'label' ) : 'Areas We Serve',
			'group'     => 'service' === SEOB_Security::post_text( 'group' ) ? 'service' : 'location',
			'auto'      => '1' === SEOB_Security::post_text( 'auto' ) ? 1 : 0,
			'loc_order' => array_values( SEOB_Security::post_text_array( 'loc_order' ) ),
			'svc_order' => array_values( SEOB_Security::post_text_array( 'svc_order' ) ),
			'hub_title' => SEOB_Security::post_text( 'hub_title' ) ? SEOB_Security::post_text( 'hub_title' ) : 'Areas We Serve',
			'hub_intro' => SEOB_Security::post_textarea( 'hub_intro' ),
		);
		self::save( $new );

		$msg = 'nav_saved';
		$err = '';
		if ( 'menu' === $do ) {
			self::save( array( 'auto' => 1 ) );
			$r   = self::build_menu();
			$msg = is_wp_error( $r ) ? 'nav_fail' : 'menu_ok';
			$err = is_wp_error( $r ) ? $r->get_error_message() : '';
		} elseif ( 'unmenu' === $do ) {
			self::remove_menu();
			self::save( array( 'auto' => 0 ) ); // Removed on purpose: do not add it back by itself.
			$msg = 'menu_removed';
		} elseif ( 'hub' === $do ) {
			$r   = self::build_hub();
			$msg = is_wp_error( $r ) ? 'nav_fail' : 'hub_ok';
			$err = is_wp_error( $r ) ? $r->get_error_message() : '';
			if ( ! is_wp_error( $r ) && self::settings()['auto'] ) {
				self::build_menu(); // Point the menu at the hub page.
			}
		} elseif ( self::settings()['auto'] ) {
			self::build_menu(); // Order changed: refresh the menu too.
		}
		if ( $err ) {
			set_transient( 'seob_ap_err_' . get_current_user_id(), $err, 120 );
		}
		SEOB_Security::redirect( admin_url( 'admin.php?page=seo-boster-area-pages&tab=menu&seob_msg=' . $msg ) );
	}

	/**
	 * Menu & hub page screen.
	 *
	 * @param SEOB_Area_Pages $module Module.
	 */
	public static function screen( $module ) {
		$s     = self::settings();
		$pages = self::pages();
		$menus = wp_get_nav_menus();
		$locs  = self::ordered( wp_list_pluck( $pages, 'location' ), (array) $s['loc_order'] );
		$svcs  = self::ordered( wp_list_pluck( $pages, 'service' ), (array) $s['svc_order'] );
		$hub   = self::hub_url();
		$err   = (string) get_transient( 'seob_ap_err_' . get_current_user_id() );

		$menu_id = (int) $s['menu_id'];
		$live    = self::live_menu();
		$live_m  = $live ? wp_get_nav_menu_object( $live ) : null;
		$in_m    = $s['items'] && $s['menu_in'] ? wp_get_nav_menu_object( (int) $s['menu_in'] ) : null;

		SEOB_Admin::notice_from_query(
			array(
				'nav_saved'    => array( 'success', __( 'Saved.', 'seo-boster' ) ),
				'menu_ok'      => array( 'success', __( 'Menu updated. Check it on the site, desktop and phone.', 'seo-boster' ) ),
				'menu_removed' => array( 'success', __( 'Areas We Serve items removed from the menu. Nothing else was touched.', 'seo-boster' ) ),
				'hub_ok'       => array( 'success', __( 'Hub page is ready.', 'seo-boster' ) ),
				/* translators: %s error */
				'nav_fail'     => array( 'error', sprintf( __( 'Could not finish: %s', 'seo-boster' ), $err ) ),
			)
		);

		if ( ! $pages ) {
			SEOB_Admin::notice( 'info', __( 'Publish some Area pages first. The menu and hub page list only published pages.', 'seo-boster' ) );
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seob-nav-form">
			<?php SEOB_Security::field( 'seob_ap_nav' ); ?>
			<input type="hidden" name="action" value="seob_ap_nav">

			<div class="seob-nav-grid">
				<div class="seob-card">
					<h3><?php esc_html_e( 'Order', 'seo-boster' ); ?></h3>
					<p class="seob-help"><?php esc_html_e( 'Drag to change the order. The menu and the hub page both follow it.', 'seo-boster' ); ?></p>
					<div class="seob-nav-cols">
						<div>
							<span class="seob-label"><?php esc_html_e( 'Locations', 'seo-boster' ); ?></span>
							<ul class="seob-sort" data-seob-sort>
								<?php foreach ( $locs as $l ) : ?>
									<li><span class="dashicons dashicons-menu"></span><?php echo esc_html( $l ); ?><input type="hidden" name="loc_order[]" value="<?php echo esc_attr( $l ); ?>"></li>
								<?php endforeach; ?>
							</ul>
						</div>
						<div>
							<span class="seob-label"><?php esc_html_e( 'Services', 'seo-boster' ); ?></span>
							<ul class="seob-sort" data-seob-sort>
								<?php foreach ( $svcs as $v ) : ?>
									<li><span class="dashicons dashicons-menu"></span><?php echo esc_html( $v ); ?><input type="hidden" name="svc_order[]" value="<?php echo esc_attr( $v ); ?>"></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>

				<div class="seob-card">
					<h3><?php esc_html_e( 'Site menu (automatic)', 'seo-boster' ); ?></h3>
					<?php if ( $s['auto'] && $in_m ) : ?>
						<p class="seob-status-ok"><span class="dashicons dashicons-yes-alt"></span>
							<?php
							/* translators: 1: label, 2: menu name, 3: time ago */
							echo esc_html( sprintf( __( '"%1$s" is in the menu "%2$s" and updates itself. Last update: %3$s ago.', 'seo-boster' ), $s['label'], $in_m->name, human_time_diff( (int) $s['synced'] ) ) );
							?>
						</p>
					<?php elseif ( $s['auto'] && '' !== (string) $s['error'] ) : ?>
						<p class="seob-status-bad"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $s['error'] ); ?></p>
					<?php elseif ( $s['auto'] ) : ?>
						<p class="seob-help"><?php esc_html_e( 'Nothing to do: as soon as Area pages are published, "Areas We Serve" with a dropdown of locations and their services is added to the site menu by itself.', 'seo-boster' ); ?></p>
					<?php else : ?>
						<p class="seob-help"><?php esc_html_e( 'Automatic menu is off. Press "Add to menu" to turn it on again.', 'seo-boster' ); ?></p>
					<?php endif; ?>
					<p class="seob-help"><?php esc_html_e( 'Only these items are ever changed; the rest of the menu is never touched. It goes before "Contact" the first time; if you move it in Appearance > Menus it stays there. Theme mobile menus show it as a dropdown too.', 'seo-boster' ); ?></p>
					<div class="seob-form-grid">
						<div>
							<label class="seob-label" for="seob-nav-menu"><?php esc_html_e( 'Menu', 'seo-boster' ); ?></label>
							<select id="seob-nav-menu" name="menu_id" class="seob-select">
								<option value="0" <?php selected( $menu_id, 0 ); ?>>
									<?php
									/* translators: %s menu name */
									echo esc_html( $live_m ? sprintf( __( 'Live header menu: %s (recommended)', 'seo-boster' ), $live_m->name ) : __( 'Live header menu (none set yet)', 'seo-boster' ) );
									?>
								</option>
								<?php foreach ( $menus as $m ) : ?>
									<option value="<?php echo (int) $m->term_id; ?>" <?php selected( $menu_id, (int) $m->term_id ); ?>><?php echo esc_html( $m->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label class="seob-label" for="seob-nav-label"><?php esc_html_e( 'Menu item text', 'seo-boster' ); ?></label>
							<input id="seob-nav-label" class="seob-input" type="text" name="label" value="<?php echo esc_attr( $s['label'] ); ?>">
						</div>
					</div>
					<span class="seob-label"><?php esc_html_e( 'Dropdown order', 'seo-boster' ); ?></span>
					<label class="seob-check"><input type="radio" name="group" value="location" <?php checked( $s['group'], 'location' ); ?>> <?php esc_html_e( 'Areas We Serve > Location > Services', 'seo-boster' ); ?></label>
					<label class="seob-check"><input type="radio" name="group" value="service" <?php checked( $s['group'], 'service' ); ?>> <?php esc_html_e( 'Areas We Serve > Service > Locations', 'seo-boster' ); ?></label>
					<label class="seob-check"><input type="checkbox" name="auto" value="1" <?php checked( $s['auto'] ); ?>> <?php esc_html_e( 'Automatic: add and update the menu by itself when Area pages are published or removed', 'seo-boster' ); ?></label>

					<div class="seob-nav-tree">
						<strong><?php echo esc_html( $s['label'] ); ?></strong>
						<ul>
							<?php foreach ( array_slice( self::tree( $s ), 0, 8, true ) as $g => $kids ) : ?>
								<li><?php echo esc_html( $g ); ?>
									<ul><?php foreach ( $kids as $k ) : ?><li><?php echo esc_html( $k['name'] ); ?></li><?php endforeach; ?></ul>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="seob-actions">
						<button type="submit" name="do" value="menu" class="seob-btn seob-btn-primary"><span class="dashicons dashicons-menu-alt"></span> <?php echo $s['items'] ? esc_html__( 'Update menu now', 'seo-boster' ) : esc_html__( 'Add to menu', 'seo-boster' ); ?></button>
						<?php if ( $s['items'] ) : ?>
							<button type="submit" name="do" value="unmenu" class="seob-btn seob-btn-ghost seob-danger"><?php esc_html_e( 'Remove from menu', 'seo-boster' ); ?></button>
							<a class="seob-link-btn" href="<?php echo esc_url( admin_url( 'nav-menus.php?menu=' . (int) $s['menu_in'] ) ); ?>"><?php esc_html_e( 'Open in Appearance > Menus', 'seo-boster' ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<div class="seob-card">
					<h3><?php esc_html_e( 'Option 2: Areas We Serve page', 'seo-boster' ); ?></h3>
					<p class="seob-help"><?php esc_html_e( 'One page with a banner and a card for every location listing its services. Uses the site brand colors. If the menu is added too, it links here.', 'seo-boster' ); ?></p>
					<label class="seob-label" for="seob-hub-title"><?php esc_html_e( 'Page title', 'seo-boster' ); ?></label>
					<input id="seob-hub-title" class="seob-input" type="text" name="hub_title" value="<?php echo esc_attr( $s['hub_title'] ); ?>">
					<label class="seob-label" for="seob-hub-intro"><?php esc_html_e( 'Intro text', 'seo-boster' ); ?></label>
					<textarea id="seob-hub-intro" class="seob-textarea" name="hub_intro" rows="3"><?php echo esc_textarea( $s['hub_intro'] ); ?></textarea>
					<div class="seob-actions">
						<button type="submit" name="do" value="hub" class="seob-btn seob-btn-primary"><span class="dashicons dashicons-admin-page"></span> <?php echo $hub ? esc_html__( 'Update hub page', 'seo-boster' ) : esc_html__( 'Create hub page', 'seo-boster' ); ?></button>
						<?php if ( $hub ) : ?>
							<a class="seob-btn seob-btn-ghost" href="<?php echo esc_url( $hub ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'seo-boster' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="seob-actions">
				<button type="submit" name="do" value="save" class="seob-btn seob-btn-ghost"><?php esc_html_e( 'Save order', 'seo-boster' ); ?></button>
			</div>
		</form>
		<?php
	}
}
