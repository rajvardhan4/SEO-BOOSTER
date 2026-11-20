<?php
/**
 * Automatic images for Area pages.
 *
 * 1. Media Library: scores every image by its file name, title, alt text and caption
 *    against the service name and keywords, and picks the best different images.
 * 2. Magnific (default source, agency key built in): every page gets its own new stock photos,
 *    downloaded into the Media Library with the page's SEO file name and alt text.
 * 3. Pexels (optional, free API key): when the library has nothing that fits.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Images {

	const OPTION    = 'seob_ap_images';
	const MIN_SCORE = 2.0;

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'pexels'         => '',
				'pexels_last4'   => '',
				'magnific'       => '',
				'magnific_last4' => '',
				'source'         => 'magnific', // magnific = new Magnific photos on every page; library = Media Library first.
			)
		);
	}

	private static function update( array $changes ) {
		update_option( self::OPTION, array_merge( self::settings(), $changes ), false );
	}

	/* ---------------- Magnific key ---------------- */

	/** Key in use: saved on this site, then wp-config.php, then the agency key built into the plugin. */
	public static function magnific_key() {
		$saved = SEOB_AP_AI::decrypt( self::settings()['magnific'] );
		if ( '' !== $saved ) {
			return $saved;
		}
		if ( defined( 'SEOB_MAGNIFIC_KEY' ) && '' !== (string) SEOB_MAGNIFIC_KEY ) {
			return (string) SEOB_MAGNIFIC_KEY;
		}
		return defined( 'SEOB_DEFAULT_MAGNIFIC_KEY' ) ? (string) SEOB_DEFAULT_MAGNIFIC_KEY : '';
	}

	/** Where the key comes from: site, config, agency or none. */
	public static function magnific_key_source() {
		if ( '' !== self::settings()['magnific'] ) {
			return 'site';
		}
		if ( defined( 'SEOB_MAGNIFIC_KEY' ) && '' !== (string) SEOB_MAGNIFIC_KEY ) {
			return 'config';
		}
		return ( defined( 'SEOB_DEFAULT_MAGNIFIC_KEY' ) && '' !== (string) SEOB_DEFAULT_MAGNIFIC_KEY ) ? 'agency' : 'none';
	}

	public static function has_magnific() {
		return '' !== self::magnific_key();
	}

	public static function use_magnific() {
		return 'magnific' === self::settings()['source'] && self::has_magnific();
	}

	public static function save_magnific_key( $key ) {
		$key = trim( (string) $key );
		if ( '' !== $key ) {
			self::update(
				array(
					'magnific'       => SEOB_AP_AI::encrypt( $key ),
					'magnific_last4' => substr( $key, -4 ),
				)
			);
		}
	}

	public static function forget_magnific() {
		self::update( array( 'magnific' => '', 'magnific_last4' => '' ) );
	}

	public static function save_source( $source ) {
		self::update( array( 'source' => 'library' === $source ? 'library' : 'magnific' ) );
	}

	public static function save_pexels_key( $key ) {
		$s = self::settings();
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return;
		}
		$s['pexels']       = SEOB_AP_AI::encrypt( $key );
		$s['pexels_last4'] = substr( $key, -4 );
		update_option( self::OPTION, $s, false );
	}

	public static function forget_pexels() {
		self::update( array( 'pexels' => '', 'pexels_last4' => '' ) );
	}

	public static function has_pexels() {
		return '' !== self::settings()['pexels'];
	}

	/* ---------------- Media Library ---------------- */

	/** Words that say nothing about the subject of a photo. */
	private static function noise() {
		return array( 'img', 'image', 'photo', 'pic', 'jpg', 'jpeg', 'png', 'webp', 'scaled', 'copy', 'final', 'new', 'hero', 'section', 'banner', 'bg', 'background', 'section1', 'section2', 'section3', 'section4', 'gallery', 'download', 'project', 'and', 'the', 'for', 'with', 'of', 'in' );
	}

	/**
	 * Words for a text, with simple plural folding.
	 *
	 * @param string $text Text.
	 */
	public static function words( $text ) {
		$text = strtolower( preg_replace( '/[^A-Za-z0-9]+/', ' ', (string) $text ) );
		$out  = array();
		foreach ( preg_split( '/\s+/', trim( $text ) ) as $w ) {
			if ( strlen( $w ) < 3 || is_numeric( $w ) || in_array( $w, self::noise(), true ) ) {
				continue;
			}
			if ( strlen( $w ) > 4 && 's' === substr( $w, -1 ) && 'ss' !== substr( $w, -2 ) ) {
				$w = substr( $w, 0, -1 );
			}
			$out[] = $w;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Every image in the library with its searchable words (cached for the request).
	 */
	public static function library() {
		static $lib = null;
		if ( null !== $lib ) {
			return $lib;
		}
		$lib = array();
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' ),
				'post_status'    => 'inherit',
				'posts_per_page' => 3000,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			$meta = wp_get_attachment_metadata( $id );
			$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
			$text = get_the_title( $id ) . ' ' . basename( (string) get_attached_file( $id ) ) . ' ' . get_post_meta( $id, '_wp_attachment_image_alt', true ) . ' ' . wp_get_attachment_caption( $id );
			$lib[ $id ] = array(
				'words' => self::words( $text ),
				'width'  => $w,
				'height' => $h,
				'logo'   => (bool) preg_match( '/logo|icon|favicon|avatar|badge|chamber|member|certif|award|seal|partner/i', $text ),
				'copy'   => (bool) get_post_meta( $id, '_seob_ap_copy_of', true ),
				// A tall photo of a team member (headshot) does not show the service.
				'person' => $h > $w && (bool) preg_match( '/headshot|staff|team|owner|founder|director|manager|stylist|technician|employee|portrait|our-|meet/i', $text ),
			);
		}
		$lib = apply_filters( 'seo_boster_image_library', $lib );
		return $lib;
	}

	/**
	 * Images in the library ranked for a service.
	 *
	 * @param string   $service  Service name.
	 * @param string[] $keywords Keywords.
	 * @return array id => score, best first.
	 */
	public static function rank( $service, array $keywords, $strict = false ) {
		$main  = self::words( $service );
		$extra = array_diff( self::words( implode( ' ', $keywords ) ), $main );
		$lib   = self::library();
		$out   = array();
		foreach ( $lib as $id => $img ) {
			if ( ! self::usable( $img ) ) {
				continue;
			}
			$score    = 0.0;
			$specific = false; // Matched a word that only a few photos have (balayage, bridal, nail).
			foreach ( $main as $w ) {
				if ( in_array( $w, $img['words'], true ) ) {
					$score   += 2 * self::weight( $w );
					$specific = $specific || self::weight( $w ) >= 1.0;
				}
			}
			foreach ( $extra as $w ) {
				if ( in_array( $w, $img['words'], true ) ) {
					$score   += self::weight( $w );
					$specific = $specific || self::weight( $w ) >= 1.0;
				}
			}
			if ( $strict && ! $specific ) {
				continue;
			}
			$portrait = $img['width'] && $img['height'] && $img['width'] < 0.9 * $img['height'];
			if ( $score >= ( $portrait ? 1.2 : 1.0 ) ) {
				$out[ $id ] = $score + self::shape_bonus( $img );
			}
		}
		arsort( $out );
		return $out;
	}

	/**
	 * Words found on most images (brand name, city) say little about the photo.
	 *
	 * @param string $word Word.
	 */
	private static function weight( $word ) {
		static $df = null;
		if ( null === $df ) {
			$df = array();
			foreach ( self::library() as $img ) {
				foreach ( $img['words'] as $w ) {
					$df[ $w ] = isset( $df[ $w ] ) ? $df[ $w ] + 1 : 1;
				}
			}
		}
		$n     = max( 1, count( self::library() ) );
		$share = isset( $df[ $word ] ) ? $df[ $word ] / $n : 0;
		if ( $share > 0.4 ) {
			return 0.2;
		}
		return $share > 0.2 ? 0.6 : 1.0;
	}

	/** A photo that can go in a page section (no logos, icons, tiny files or plugin copies). */
	private static function usable( array $img ) {
		return ! $img['logo'] && ! $img['copy'] && ! $img['person'] && ( ! $img['width'] || $img['width'] >= 400 );
	}

	/** Wide photos fit the page sections best; tall portraits (headshots) the least. */
	private static function shape_bonus( array $img ) {
		$bonus = min( 0.3, $img['width'] / 6000 );
		if ( $img['width'] && $img['height'] ) {
			$ratio  = $img['width'] / $img['height'];
			$bonus += $ratio >= 1.2 ? 0.4 : ( $ratio < 0.9 ? -0.8 : 0 );
		}
		return $bonus;
	}

	/**
	 * Good general photos of the business, used when a service has too few matching photos.
	 *
	 * @param string $context Business name and all services.
	 * @return int[]
	 */
	public static function general( $context ) {
		$words = self::words( $context );
		$out   = array();
		foreach ( self::library() as $id => $img ) {
			if ( ! self::usable( $img ) || ( $img['width'] && $img['width'] < 600 ) || ( $img['height'] && $img['width'] < $img['height'] ) ) {
				continue;
			}
			$score = self::shape_bonus( $img );
			foreach ( $words as $w ) {
				if ( in_array( $w, $img['words'], true ) ) {
					$score += 0.5 * self::weight( $w );
				}
			}
			if ( $score > -0.5 ) {
				$out[ $id ] = $score;
			}
		}
		arsort( $out );
		return array_map( 'intval', array_keys( $out ) );
	}

	/**
	 * A list of photos for every service, best match first, so each page can get different ones.
	 *
	 * @param array  $services List of [ name, keywords[] ].
	 * @param bool   $pexels   Top up from Pexels when the library has too few.
	 * @param string $business Business name.
	 * @return array service index => int[]
	 */
	public static function pools( array $services, $pexels, $business = '' ) {
		$pools  = array();
		$strict = array();
		foreach ( $services as $i => $svc ) {
			$strict[ $i ] = array_map( 'intval', array_slice( array_keys( self::rank( $svc['name'], (array) $svc['keywords'], true ) ), 0, 24 ) );
		}
		$general = self::general( $business );
		foreach ( $services as $i => $svc ) {
			// Photos that clearly belong to another service never go on this service's pages.
			$others = array();
			foreach ( $strict as $j => $ids ) {
				if ( $j !== $i ) {
					$others = array_merge( $others, $ids );
				}
			}
			$others = array_diff( $others, $strict[ $i ] );
			$pool   = $strict[ $i ];
			if ( count( $pool ) < 4 ) {
				$cap = $pool ? 4 : 8; // Looser matches only fill the gap.
				foreach ( array_keys( self::rank( $svc['name'], (array) $svc['keywords'] ) ) as $id ) {
					if ( count( $pool ) >= $cap ) {
						break;
					}
					if ( ! in_array( (int) $id, $others, true ) && ! in_array( (int) $id, $pool, true ) ) {
						$pool[] = (int) $id;
					}
				}
			}
			if ( $pexels && count( $pool ) < 8 && self::has_pexels() ) {
				$pool = array_merge( $pool, self::from_pexels( $svc['name'], (array) $svc['keywords'], 8 - count( $pool ) ) );
			}
			// Still too few: add general photos of the business (shop, team at work).
			foreach ( array( array_diff( $general, $others ), $general ) as $list ) {
				foreach ( $list as $id ) {
					if ( count( $pool ) >= 4 ) {
						break 2;
					}
					if ( ! in_array( (int) $id, $pool, true ) ) {
						$pool[] = (int) $id;
					}
				}
			}
			$pools[ $i ] = array_values( array_unique( $pool ) );
		}
		return $pools;
	}

	/**
	 * The 4 section photos for one page. Each location gets a different set from the pool.
	 *
	 * @param int[] $pool      Photos for the service, best first.
	 * @param int   $loc_index Location number.
	 * @param int   $slots     Photos per page.
	 * @return int[]
	 */
	public static function for_page( array $pool, $loc_index, $slots = 4 ) {
		$pool = array_values( array_filter( array_map( 'absint', $pool ) ) );
		$n    = count( $pool );
		if ( ! $n ) {
			return array_fill( 0, $slots, 0 );
		}
		if ( $n <= $slots || $slots < 1 ) {
			$start = $loc_index % $n;
		} elseif ( 1 === self::gcd( $n, $slots ) ) {
			$start = ( $loc_index * $slots ) % $n;
		} else {
			// After a full round, shift by one so the same photos do not come back together.
			$start = ( $loc_index * $slots + intdiv( $loc_index * $slots, $n ) ) % $n;
		}
		$set = array();
		for ( $k = 0; $k < $slots; $k++ ) {
			$set[] = $pool[ ( $start + $k ) % $n ];
		}
		// The last slot is the wide background: put the widest photo of the set there.
		$lib  = self::library();
		$best = $slots - 1;
		foreach ( $set as $k => $id ) {
			if ( isset( $lib[ $id ], $lib[ $set[ $best ] ] ) && self::ratio( $lib[ $id ] ) > self::ratio( $lib[ $set[ $best ] ] ) + 0.2 ) {
				$best = $k;
			}
		}
		if ( $best !== $slots - 1 ) {
			$tmp               = $set[ $slots - 1 ];
			$set[ $slots - 1 ] = $set[ $best ];
			$set[ $best ]      = $tmp;
		}
		return $set;
	}

	private static function gcd( $a, $b ) {
		return 0 === $b ? $a : self::gcd( $b, $a % $b );
	}

	private static function ratio( array $img ) {
		return ( $img['width'] && $img['height'] ) ? $img['width'] / $img['height'] : 1.3;
	}

	/**
	 * Copy an image into a new Media Library item with an SEO file name and alt text for one page.
	 * The file and its sizes are copied (no resizing), so it is fast. Reused when the page is rewritten.
	 *
	 * @param int    $source  Attachment ID.
	 * @param int    $post_id Area page ID.
	 * @param string $slug    File name without extension, e.g. balayage-in-cary-nc-1.
	 * @param string $alt     Alt text.
	 * @param string $title   Image title.
	 * @return int Attachment ID (the source ID when copying is not possible).
	 */
	public static function page_copy( $source, $post_id, $slug, $alt, $title ) {
		$source = absint( $source );
		if ( ! $source || ! $post_id ) {
			return $source;
		}
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_seob_ap_copy_of', 'value' => (string) $source ),
				),
			)
		);
		if ( $found ) {
			$id = (int) $found[0];
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			return $id;
		}

		// A photo downloaded just for this page: give it the page's alt text, no copy needed.
		if ( get_post_meta( $source, '_seob_ap_page_only', true ) ) {
			$parent = (int) wp_get_post_parent_id( $source );
			if ( ! $parent || $parent === (int) $post_id ) {
				update_post_meta( $source, '_wp_attachment_image_alt', $alt );
				wp_update_post( array( 'ID' => $source, 'post_parent' => (int) $post_id, 'post_title' => $title ) );
				return $source;
			}
		}
		$file = get_attached_file( $source );
		$meta = wp_get_attachment_metadata( $source );
		if ( ! $file || ! is_readable( $file ) ) {
			return $source;
		}
		$dir  = trailingslashit( dirname( $file ) );
		$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$base = sanitize_file_name( $slug );
		$name = wp_unique_filename( $dir, $base . '.' . $ext );
		$stem = pathinfo( $name, PATHINFO_FILENAME );
		if ( ! @copy( $file, $dir . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $source;
		}

		$new_meta = is_array( $meta ) ? $meta : array();
		if ( ! empty( $new_meta['file'] ) ) {
			$new_meta['file'] = ltrim( trailingslashit( dirname( $new_meta['file'] ) ), './' ) . $name;
			$new_meta['file'] = ltrim( str_replace( './', '', $new_meta['file'] ), '/' );
		}
		unset( $new_meta['original_image'] );
		$copied = array();
		if ( ! empty( $new_meta['sizes'] ) && is_array( $new_meta['sizes'] ) ) {
			foreach ( $new_meta['sizes'] as $size => $info ) {
				if ( empty( $info['file'] ) || ! is_readable( $dir . $info['file'] ) ) {
					unset( $new_meta['sizes'][ $size ] );
					continue;
				}
				if ( isset( $copied[ $info['file'] ] ) ) {
					$new_meta['sizes'][ $size ]['file'] = $copied[ $info['file'] ];
					continue;
				}
				$suffix = isset( $info['width'], $info['height'] ) ? '-' . (int) $info['width'] . 'x' . (int) $info['height'] : '-' . sanitize_key( $size );
				$sub    = $stem . $suffix . '.' . strtolower( pathinfo( $info['file'], PATHINFO_EXTENSION ) );
				if ( ! file_exists( $dir . $sub ) && @copy( $dir . $info['file'], $dir . $sub ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$new_meta['sizes'][ $size ]['file'] = $sub;
					$copied[ $info['file'] ]             = $sub;
				} else {
					unset( $new_meta['sizes'][ $size ] );
				}
			}
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => get_post_mime_type( $source ),
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_excerpt'   => wp_get_attachment_caption( $source ),
			),
			$dir . $name,
			$post_id,
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			wp_delete_file( $dir . $name );
			return $source;
		}
		wp_update_attachment_metadata( $id, $new_meta );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $id, '_seob_ap_copy_of', (string) $source );
		return (int) $id;
	}

	/**
	 * Fill the image grid for every service.
	 *
	 * @param array $services List of [ name, keywords[] ].
	 * @param bool  $pexels   Also use Pexels when the library has too few matches.
	 * @return array field => [ service index => [ id, url, source ] ]
	 */
	public static function suggest( array $services, $pexels, $business = '' ) {
		$start  = time();
		$fields = array_keys( SEOB_AP_Schema::image_fields() );
		$photo  = SEOB_AP_Writer::photo_fields();
		$slots  = count( $photo );
		$grid   = array();
		$pools  = self::pools( $services, $pexels, $business );

		// Too few photos for a service in the library: new ones from Magnific (stops in time
		// so the button never hangs; the rest is filled from what was found).
		if ( self::use_magnific() ) {
			foreach ( $services as $i => $svc ) {
				if ( time() - $start > 100 ) {
					break;
				}
				$have = isset( $pools[ $i ] ) ? count( $pools[ $i ] ) : 0;
				if ( $have < $slots ) {
					$svc += array( 'name' => '', 'keywords' => array() );
					$got  = self::magnific_for_page( $svc, 0, 1, sanitize_title( $svc['name'] ), $slots - $have );
					$pools[ $i ] = array_values( array_unique( array_merge( isset( $pools[ $i ] ) ? $pools[ $i ] : array(), $got ) ) );
				}
			}
		}

		// Last resort so no cell stays empty: every photo found for any service, then any usable library photo.
		$all = array();
		foreach ( $pools as $list ) {
			$all = array_merge( $all, $list );
		}
		if ( ! $all ) {
			foreach ( self::library() as $id => $img ) {
				if ( ! $img['logo'] && ! $img['copy'] ) {
					$all[] = (int) $id;
				}
			}
		}
		$all = array_values( array_unique( array_map( 'intval', $all ) ) );
		// Wider list of good business photos, so services without their own photos still differ.
		$wide = array_values( array_unique( array_merge( self::general( $business . ' ' . implode( ' ', wp_list_pluck( $services, 'name' ) ) ), $all ) ) );

		$used = array();
		foreach ( $services as $i => $svc ) {
			$list = ! empty( $pools[ $i ] ) ? array_values( $pools[ $i ] ) : $wide;
			if ( ! $list ) {
				continue;
			}
			// Photos not shown for another service yet come first, so rows look different.
			$fresh = array_values( array_diff( $list, $used ) );
			$order = array_values( array_unique( array_merge( $fresh, array_diff( $wide, $used ), $list, $wide ) ) );
			$pick  = array();
			for ( $n = 0; $n < $slots; $n++ ) {
				$pick[] = $order[ $n % count( $order ) ];
			}
			// The background slot gets the widest photo of the set.
			$lib  = self::library();
			$best = $slots - 1;
			foreach ( $pick as $k => $id ) {
				if ( isset( $lib[ $id ], $lib[ $pick[ $best ] ] ) && self::ratio( $lib[ $id ] ) > self::ratio( $lib[ $pick[ $best ] ] ) + 0.2 ) {
					$best = $k;
				}
			}
			if ( $best !== $slots - 1 ) {
				$tmp                 = $pick[ $slots - 1 ];
				$pick[ $slots - 1 ]  = $pick[ $best ];
				$pick[ $best ]       = $tmp;
			}
			foreach ( $photo as $n => $field ) {
				$grid[ $field ][ $i ] = self::cell( $pick[ $n ] );
				$used[]               = $pick[ $n ];
			}
		}

		// Section 5 icon: an icon image from the library, else the site icon or logo, same for every service.
		$icon = 0;
		foreach ( self::library() as $id => $img ) {
			if ( $img['logo'] && preg_match( '/icon/', implode( ' ', $img['words'] ) ) ) {
				$icon = (int) $id;
				break;
			}
		}
		if ( ! $icon ) {
			$icon = (int) get_option( 'site_icon' );
		}
		if ( ! $icon ) {
			$icon = (int) get_theme_mod( 'custom_logo' );
		}
		if ( ! $icon ) {
			foreach ( self::library() as $id => $img ) {
				if ( $img['logo'] ) {
					$icon = (int) $id;
					break;
				}
			}
		}
		if ( ! $icon && $all ) {
			$icon = $all[0];
		}
		if ( $icon && wp_attachment_is_image( $icon ) && in_array( 'section_5_same_image', SEOB_AP_Design::used_image_fields(), true ) ) {
			foreach ( $services as $i => $svc ) {
				$grid['section_5_same_image'][ $i ] = self::cell( $icon );
			}
		}
		foreach ( $fields as $f ) {
			if ( ! isset( $grid[ $f ] ) ) {
				$grid[ $f ] = array();
			}
		}
		return $grid;
	}

	private static function cell( $id ) {
		$src = wp_get_attachment_image_src( $id, 'thumbnail' );
		return array(
			'id'  => (int) $id,
			'url' => $src ? $src[0] : '',
		);
	}

	/* ---------------- Pexels ---------------- */

	/**
	 * Download up to $n landscape photos for a service into the Media Library.
	 *
	 * @param string   $service  Service.
	 * @param string[] $keywords Keywords.
	 * @param int      $n        How many.
	 * @return int[] Attachment IDs.
	 */
	public static function from_pexels( $service, array $keywords, $n ) {
		$key = SEOB_AP_AI::decrypt( self::settings()['pexels'] );
		if ( '' === $key || $n < 1 ) {
			return array();
		}
		$query = trim( $service . ( $keywords ? ' ' . $keywords[0] : '' ) );
		$res   = wp_remote_get(
			add_query_arg(
				array(
					'query'       => rawurlencode( $query ),
					'per_page'    => min( 15, $n + 4 ),
					'orientation' => 'landscape',
				),
				'https://api.pexels.com/v1/search'
			),
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => $key ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['photos'] ) ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ids = array();
		foreach ( $data['photos'] as $k => $p ) {
			if ( count( $ids ) >= $n ) {
				break;
			}
			$url = isset( $p['src']['large2x'] ) ? (string) $p['src']['large2x'] : ( isset( $p['src']['large'] ) ? (string) $p['src']['large'] : '' );
			if ( 0 !== strpos( $url, 'https://images.pexels.com/' ) ) {
				continue; // Only download from Pexels' own image host.
			}
			$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_seob_pexels_id', 'meta_value' => (string) (int) $p['id'], 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $existing ) {
				$ids[] = (int) $existing[0];
				continue;
			}
			$tmp = download_url( $url, 30 );
			if ( is_wp_error( $tmp ) ) {
				continue;
			}
			$name = sanitize_title( $service . '-' . ( $k + 1 ) ) . '.jpg';
			$id   = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0, ucwords( $service ) );
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			$alt = ! empty( $p['alt'] ) ? sanitize_text_field( $p['alt'] ) : ucfirst( $service );
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			update_post_meta( $id, '_seob_pexels_id', (string) (int) $p['id'] );
			wp_update_post(
				array(
					'ID'           => $id,
					'post_excerpt' => sprintf( 'Photo by %s on Pexels', isset( $p['photographer'] ) ? sanitize_text_field( $p['photographer'] ) : 'Pexels' ),
				)
			);
			$ids[] = (int) $id;
		}
		return $ids;
	}

	/* ---------------- Magnific ---------------- */

	const MAGNIFIC_API = 'https://api.magnific.com/v1';

	private static function magnific_get( $path, array $query = array(), $timeout = 20 ) {
		$url = self::MAGNIFIC_API . $path . ( $query ? '?' . http_build_query( $query ) : '' );
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'x-magnific-api-key' => self::magnific_key(),
					'Accept-Language'    => 'en-US',
					'Accept'             => 'application/json',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data = is_array( $data ) ? $data : array();
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : ( isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $code );
			return new WP_Error( 'seob_magnific', sanitize_text_field( $msg ), array( 'status' => $code ) );
		}
		return $data;
	}

	/**
	 * Search results for a term (photos, landscape), kept for a day so every page of a job
	 * takes the next photos from the same list.
	 *
	 * @param string $term Search words.
	 * @param int    $need Results needed (index + 1).
	 * @return array[]|WP_Error List of [ id, title, author ].
	 */
	public static function magnific_results( $term, $need ) {
		$key  = 'seob_mg_' . md5( strtolower( $term ) );
		$list = get_transient( $key );
		$list = is_array( $list ) ? $list : array( 'items' => array(), 'page' => 0, 'last' => 0 );
		while ( count( $list['items'] ) < $need && ( ! $list['last'] || $list['page'] < $list['last'] ) && $list['page'] < 10 ) {
			$data = self::magnific_get(
				'/resources',
				array(
					'term'    => $term,
					'page'    => $list['page'] + 1,
					'limit'   => 50,
					'order'   => 'relevance',
					'filters' => array(
						'content_type' => array( 'photo' => 1 ),
						'orientation'  => array( 'landscape' => 1 ),
					),
				)
			);
			if ( is_wp_error( $data ) ) {
				return $list['items'] ? $list['items'] : $data;
			}
			++$list['page'];
			$list['last'] = isset( $data['meta']['last_page'] ) ? (int) $data['meta']['last_page'] : $list['page'];
			$rows         = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
			if ( ! $rows ) {
				$list['last'] = $list['page'];
			}
			foreach ( $rows as $r ) {
				if ( empty( $r['id'] ) ) {
					continue;
				}
				$list['items'][] = array(
					'id'     => (int) $r['id'],
					'title'  => isset( $r['title'] ) ? sanitize_text_field( (string) $r['title'] ) : '',
					'author' => isset( $r['author']['name'] ) ? sanitize_text_field( (string) $r['author']['name'] ) : '',
				);
			}
		}
		set_transient( $key, $list, DAY_IN_SECONDS );
		return $list['items'];
	}

	/**
	 * Download one Magnific photo into the Media Library (reused if it was downloaded before).
	 *
	 * @param array  $item Search result.
	 * @param string $name File name without extension.
	 * @param string $alt  Alt text.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function magnific_download( array $item, $name, $alt ) {
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_seob_magnific_id', 'meta_value' => (string) (int) $item['id'], 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $found ) {
			return (int) $found[0];
		}
		$data = self::magnific_get( '/resources/' . (int) $item['id'] . '/download', array( 'image_size' => 'medium' ), 30 );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$url = isset( $data['data']['url'] ) ? (string) $data['data']['url'] : '';
		if ( 0 !== strpos( $url, 'https://' ) ) {
			return new WP_Error( 'seob_magnific', 'No download link' );
		}
		$file = isset( $data['data']['filename'] ) ? (string) $data['data']['filename'] : '';
		$ext  = strtolower( pathinfo( wp_parse_url( $file ? $file : $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			$ext = 'jpg';
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 60 ); // Safe download: public addresses only.
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		if ( ! @getimagesize( $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( $tmp );
			return new WP_Error( 'seob_magnific', 'Not an image' );
		}
		$id = media_handle_sideload( array( 'name' => sanitize_file_name( $name ) . '.' . $ext, 'tmp_name' => $tmp ), 0, $alt );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $id, '_seob_magnific_id', (string) (int) $item['id'] );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => $item['author'] ? sprintf( 'Image by %s on Magnific', $item['author'] ) : 'Image from Magnific',
			)
		);
		return (int) $id;
	}

	/**
	 * New Magnific photos for one page. Page n of a service takes results n*4 .. n*4+3 of the same
	 * search, so every location page gets different photos.
	 *
	 * @param array  $svc       Service [ name, keywords ].
	 * @param int    $loc_index Location number.
	 * @param int    $locs      Number of locations in the job.
	 * @param string $base      File name start, e.g. balayage-in-cary-nc.
	 * @param int    $slots     Photos needed.
	 * @return int[] Attachment IDs (fewer when Magnific has no more).
	 */
	public static function magnific_for_page( array $svc, $loc_index, $locs, $base, $slots = 4 ) {
		if ( ! self::has_magnific() ) {
			return array();
		}
		$term  = trim( $svc['name'] . ( ! empty( $svc['keywords'][0] ) && false === stripos( $svc['keywords'][0], $svc['name'] ) ? ' ' . $svc['keywords'][0] : '' ) );
		$locs  = max( 1, (int) $locs );
		$ids   = array();
		$tries = 0;
		for ( $k = 0; $k < $slots; $k++ ) {
			// First choice for this slot, then the same slot in later rounds if a photo cannot be used.
			for ( $round = 0; $round < 3 && count( $ids ) <= $k; $round++ ) {
				$idx   = $loc_index * $slots + $k + $round * $locs * $slots;
				$items = self::magnific_results( $term, $idx + 1 );
				if ( is_wp_error( $items ) ) {
					self::magnific_error( $items->get_error_message() );
					return $ids;
				}
				if ( ! isset( $items[ $idx ] ) ) {
					// Fewer results than pages: reuse from the start of the list.
					$idx = $items ? ( $loc_index * $slots + $k ) % count( $items ) : -1;
					if ( $idx < 0 ) {
						return $ids;
					}
				}
				$item = $items[ $idx ];
				$alt  = '' !== $item['title'] ? $item['title'] : ucwords( $svc['name'] );
				$id   = self::magnific_download( $item, $base . '-' . ( $k + 1 ), $alt );
				if ( is_wp_error( $id ) ) {
					$status = (int) ( is_array( $id->get_error_data() ) && isset( $id->get_error_data()['status'] ) ? $id->get_error_data()['status'] : 0 );
					if ( 429 === $status || 401 === $status ) {
						self::magnific_error( $id->get_error_message() );
						return $ids;
					}
					if ( ++$tries > 6 ) {
						return $ids;
					}
					continue;
				}
				if ( ! in_array( (int) $id, $ids, true ) ) {
					$ids[] = (int) $id;
				}
			}
		}
		self::magnific_error( '' );
		return $ids;
	}

	private static function magnific_error( $msg ) {
		if ( (string) get_option( 'seob_magnific_error', '' ) !== $msg ) {
			update_option( 'seob_magnific_error', $msg, false );
		}
	}

	/**
	 * Check the key with one small search.
	 *
	 * @return true|WP_Error
	 */
	public static function test_magnific() {
		if ( ! self::has_magnific() ) {
			return new WP_Error( 'seob_magnific', __( 'No Magnific key.', 'seo-boster' ) );
		}
		$data = self::magnific_get( '/resources', array( 'term' => 'house', 'limit' => 1, 'filters' => array( 'content_type' => array( 'photo' => 1 ) ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['data'] ) ? true : new WP_Error( 'seob_magnific', __( 'Connected, but the answer was not as expected.', 'seo-boster' ) );
	}
}
