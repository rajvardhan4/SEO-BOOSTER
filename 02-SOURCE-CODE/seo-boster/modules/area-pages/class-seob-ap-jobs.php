<?php
/**
 * Job queue: one job = one form submit, one item = one Service x Location page.
 * Items are processed one at a time from the admin screen (AJAX) and by WP-Cron
 * as a backup, so a job keeps going even if the browser tab is closed.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

class SEOB_AP_Jobs {

	const DB_VERSION = '1';
	const CRON_HOOK  = 'seob_ap_run_jobs';

	public static function jobs_table() {
		global $wpdb;
		return $wpdb->prefix . 'seob_ap_jobs';
	}

	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'seob_ap_items';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c  = $wpdb->get_charset_collate();
		$jt = self::jobs_table();
		$it = self::items_table();

		dbDelta(
			"CREATE TABLE {$jt} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			settings longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'running',
			total int(11) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id)
		) {$c};"
		);
		dbDelta(
			"CREATE TABLE {$it} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			row_index int(11) unsigned NOT NULL DEFAULT 0,
			service_index int(11) unsigned NOT NULL DEFAULT 0,
			location_index int(11) unsigned NOT NULL DEFAULT 0,
			service varchar(191) NOT NULL DEFAULT '',
			location varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			keyword varchar(191) NOT NULL DEFAULT '',
			attempts int(11) unsigned NOT NULL DEFAULT 0,
			message text NULL,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY job_status (job_id,status)
		) {$c};"
		);
		update_option( 'seob_ap_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'seob_ap_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ---------------- Create ---------------- */

	/**
	 * Create a job and one item per Service x Location.
	 *
	 * @param array $settings Clean job settings.
	 * @return int Job ID.
	 */
	public static function create( array $settings ) {
		global $wpdb;
		$wpdb->insert(
			self::jobs_table(),
			array(
				'name'       => $settings['business'],
				'settings'   => wp_json_encode( $settings ),
				'status'     => 'running',
				'total'      => count( $settings['services'] ) * count( $settings['locations'] ),
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		$job_id = (int) $wpdb->insert_id;

		$row = 0;
		foreach ( $settings['services'] as $si => $service ) {
			foreach ( $settings['locations'] as $li => $location ) {
				$wpdb->insert(
					self::items_table(),
					array(
						'job_id'         => $job_id,
						'row_index'      => $row++,
						'service_index'  => $si,
						'location_index' => $li,
						'service'        => $service['name'],
						'location'       => $location,
						'status'         => 'pending',
						'updated_at'     => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}
		}

		self::schedule();
		return $job_id;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/* ---------------- Read ---------------- */

	public static function job( $id ) {
		global $wpdb;
		$t   = self::jobs_table();
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $job ) {
			$job->settings = json_decode( $job->settings, true );
		}
		return $job;
	}

	public static function jobs( $limit = 30 ) {
		global $wpdb;
		$t = self::jobs_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, status, total, created_at FROM {$t} ORDER BY id DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function items( $job_id ) {
		global $wpdb;
		$t = self::items_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job_id = %d ORDER BY row_index ASC", $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function counts( $job_id ) {
		global $wpdb;
		$t    = self::items_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$t} WHERE job_id = %d GROUP BY status", $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array( 'pending' => 0, 'working' => 0, 'done' => 0, 'warning' => 0, 'failed' => 0, 'skipped' => 0 );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		$out['total']    = array_sum( $out );
		$out['finished'] = $out['done'] + $out['warning'] + $out['failed'] + $out['skipped'];
		return $out;
	}

	public static function set_job_status( $job_id, $status ) {
		global $wpdb;
		$wpdb->update( self::jobs_table(), array( 'status' => $status ), array( 'id' => (int) $job_id ), array( '%s' ), array( '%d' ) );
	}

	private static function update_item( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::items_table(), $data, array( 'id' => (int) $id ) );
	}

	/* ---------------- Locking ---------------- */

	private static function lock( $job_id ) {
		$key = 'seob_ap_lock_' . (int) $job_id;
		if ( add_option( $key, time(), '', 'no' ) ) {
			return true;
		}
		$since = (int) get_option( $key );
		if ( $since && time() - $since > 180 ) { // Stale lock from a request the host stopped (AI timeout is 120s).
			update_option( $key, time(), false );
			return true;
		}
		return false;
	}

	private static function unlock( $job_id ) {
		delete_option( 'seob_ap_lock_' . (int) $job_id );
	}

	/* ---------------- Run ---------------- */

	/**
	 * Process the next pending page of a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array Status for the progress screen.
	 */
	public static function step( $job_id ) {
		global $wpdb;
		$job = self::job( $job_id );
		if ( ! $job ) {
			return array( 'error' => 'missing' );
		}
		if ( 'running' !== $job->status ) {
			return array( 'counts' => self::counts( $job_id ), 'status' => $job->status );
		}

		$item = self::claim( (int) $job_id );
		$last = null;
		if ( $item ) {
			ignore_user_abort( true ); // Finish the page even if the browser tab is closed mid-request.
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$last = self::process_item( $job, $item );
		}

		$counts = self::counts( $job_id );
		$job    = self::job( $job_id );
		if ( 'running' === $job->status && 0 === $counts['pending'] + $counts['working'] ) {
			self::set_job_status( $job_id, 'finished' );
			$job->status = 'finished';
		}
		return array(
			'counts' => $counts,
			'status' => $item || 'running' !== $job->status ? $job->status : 'busy',
			'last'   => $last,
		);
	}

	/**
	 * Take the next waiting page of a job for this request. Several requests can run at once:
	 * each one claims a different page, so pages are written in parallel.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null Item row.
	 */
	private static function claim( $job_id ) {
		global $wpdb;
		$t = self::items_table();
		// Pages left "working" by a request the host stopped are put back in the queue.
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'pending' WHERE job_id = %d AND status = 'working' AND updated_at < %s", $job_id, gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 240 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DateTime.CurrentTimeTimestamp.Requested

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE job_id = %d AND status = 'pending' ORDER BY row_index ASC LIMIT 12", $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $ids as $id ) {
			$won = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'working', attempts = attempts + 1, updated_at = %s WHERE id = %d AND status = 'pending'", current_time( 'mysql' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 === (int) $won ) {
				return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		return null;
	}

	/** How many pages to write at the same time. */
	public static function parallel() {
		$n = (int) get_option( 'seob_ap_parallel', 4 );
		return max( 1, min( 8, $n ) );
	}

	/**
	 * Write and save one page.
	 *
	 * @param object $job  Job row.
	 * @param array  $item Item row.
	 */
	private static function process_item( $job, array $item ) {
		$s     = $job->settings;
		$title = $item['service'] . ' in ' . $item['location'];

		$existing = get_page_by_path( sanitize_title( $title ), OBJECT, SEOB_AP_Schema::POST_TYPE );
		if ( $existing && 'trash' !== $existing->post_status && 'skip' === $s['existing'] ) {
			self::update_item( $item['id'], array( 'status' => 'skipped', 'post_id' => $existing->ID, 'message' => __( 'A page with this title already exists.', 'seo-boster' ) ) );
			return array( 'title' => $title, 'status' => 'skipped' );
		}

		$state_key = 'seob_ap_state_' . (int) $item['id'];
		$state     = get_option( $state_key, array() );
		$result    = SEOB_AP_Writer::attempt( $s, $item, self::used_headings( (int) $job->id, (int) $item['service_index'] ), is_array( $state ) ? $state : array() );

		if ( ! is_wp_error( $result ) && empty( $result['done'] ) ) {
			// Answer had problems: keep it and ask the AI to fix them on the next request.
			update_option( $state_key, $result['state'], false );
			self::update_item( $item['id'], array( 'status' => 'pending', 'attempts' => max( 0, (int) $item['attempts'] - 1 ) ) );
			return array( 'title' => $title, 'status' => 'checking' );
		}
		delete_option( $state_key );

		if ( is_wp_error( $result ) ) {
			$fatal = in_array( $result->get_error_code(), array( 'seob_no_key' ), true )
				|| ( is_array( $result->get_error_data() ) && in_array( (int) ( $result->get_error_data()['status'] ?? 0 ), array( 401, 403 ), true ) );
			$retry = ! $fatal && (int) $item['attempts'] < 3;
			self::update_item(
				$item['id'],
				array(
					'status'  => $retry ? 'pending' : 'failed',
					'message' => $result->get_error_message(),
				)
			);
			if ( $fatal || ( ! $retry && self::same_failures( (int) $job->id, $result->get_error_message() ) ) ) {
				// The same error on every page (bad key, bad model, no credit): stop instead of failing the whole list.
				self::set_job_status( (int) $job->id, 'paused' );
			}
			return array( 'title' => $title, 'status' => 'failed', 'message' => $result->get_error_message() );
		}

		$v       = $result['values'];
		$post_id = self::save_post( $v, $s, $existing );
		if ( is_wp_error( $post_id ) ) {
			self::update_item( $item['id'], array( 'status' => 'failed', 'message' => $post_id->get_error_message() ) );
			return array( 'title' => $title, 'status' => 'failed', 'message' => $post_id->get_error_message() );
		}

		$status = $result['warnings'] ? 'warning' : 'done';
		self::update_item(
			$item['id'],
			array(
				'status'  => $status,
				'post_id' => $post_id,
				'keyword' => $v['primary_keyword'],
				'message' => $result['warnings'] ? implode( '; ', array_slice( $result['warnings'], 0, 6 ) ) : '',
			)
		);
		self::remember_heading( (int) $job->id, (int) $item['service_index'], $v );

		return array(
			'title'  => $title,
			'status' => $status,
			'link'   => get_permalink( $post_id ),
		);
	}

	/**
	 * Create or update the post and every field.
	 *
	 * @param array        $v        Field values.
	 * @param array        $s        Job settings.
	 * @param WP_Post|null $existing Existing post to update.
	 * @return int|WP_Error
	 */
	private static function save_post( array $v, array $s, $existing ) {
		$postarr = array(
			'post_type'   => SEOB_AP_Schema::POST_TYPE,
			'post_title'  => $v['post_title'],
			'post_name'   => sanitize_title( $v['post_title'] ),
			'post_status' => 'publish' === $s['post_status'] ? 'publish' : 'draft',
			'post_author' => (int) $s['user_id'],
		);
		if ( $existing && 'update' === $s['existing'] ) {
			SEOB_Backup_Manager::create_snapshot( $existing->ID );
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Optional: each page gets its own copy of every photo, named after the page (SEO file name and alt).
		if ( ! empty( $s['image_copies'] ) ) {
			$v = self::page_image_copies( $v, (int) $post_id );
		}

		$skip = array( 'post_title', 'seo_title', 'meta_desc', 'primary_keyword', 'location', 'service' );
		foreach ( $v as $field => $value ) {
			if ( ! in_array( $field, $skip, true ) ) {
				SEOB_AP_Schema::save_field( $post_id, $field, $value );
			}
		}
		update_post_meta( $post_id, 'seo_title', $v['seo_title'] );
		update_post_meta( $post_id, 'meta_desc', $v['meta_desc'] );
		update_post_meta( $post_id, '_seob_ap_keyword', $v['primary_keyword'] );
		update_post_meta( $post_id, '_seob_ap_service', $v['service'] );

		// Calibrated Rank Math 85+ and Yoast SEO postmeta injection.
		SEOB_RankMath_Bridge::inject_postmeta( $post_id, $v['seo_title'], $v['meta_desc'], $v['primary_keyword'] );

		if ( ! empty( $v['section_2_left_image'] ) ) {
			set_post_thumbnail( $post_id, (int) $v['section_2_left_image'] );
		}
		if ( taxonomy_exists( SEOB_AP_Schema::TAXONOMY ) ) {
			wp_set_object_terms( $post_id, array( $v['location'] ), SEOB_AP_Schema::TAXONOMY, false );
		}
		SEOB_AP_Schema::save_field( $post_id, 'location', $v['location'] );

		do_action( 'seo_boster_area_page_saved', $post_id, $v, $s );
		return (int) $post_id;
	}

	/**
	 * Replace the page's photos with copies named for this page, e.g. balayage-in-cary-nc-1.jpg.
	 *
	 * @param array $v       Field values.
	 * @param int   $post_id Area page ID.
	 */
	private static function page_image_copies( array $v, $post_id ) {
		$city = SEOB_AP_Writer::city( $v['location'] );
		$base = sanitize_title( ( '' !== $v['primary_keyword'] ? $v['primary_keyword'] : $v['service'] ) . ' in ' . $v['location'] );
		$alts = array(
			'section_2_left_image'       => 'section_2_left_image_alt_tag',
			'section_3_right_image'      => 'section_3_right_image_alt_tag',
			'section_4_right_image'      => 'section_4_right_image_alt_tag',
			'section_7_background_image' => '',
		);
		$n = 0;
		foreach ( $alts as $field => $alt_field ) {
			if ( empty( $v[ $field ] ) ) {
				continue;
			}
			++$n;
			$alt         = ( $alt_field && ! empty( $v[ $alt_field ] ) ) ? $v[ $alt_field ] : $v['service'] . ' in ' . $city;
			$title       = SEOB_AP_Writer::title_case( $v['service'] . ' in ' . $city ) . ' ' . $n;
			$v[ $field ] = SEOB_AP_Images::page_copy( (int) $v[ $field ], $post_id, $base . '-' . $n, wp_strip_all_tags( $alt ), $title );
		}
		return $v;
	}

	/**
	 * True when the last 3 finished pages of a job all failed with this same message.
	 *
	 * @param int    $job_id  Job ID.
	 * @param string $message Error message.
	 */
	private static function same_failures( $job_id, $message ) {
		global $wpdb;
		$t    = self::items_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, message FROM {$t} WHERE job_id = %d AND status IN ('done','warning','failed') ORDER BY updated_at DESC, id DESC LIMIT 3", $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( count( $rows ) < 3 ) {
			return false;
		}
		foreach ( $rows as $r ) {
			if ( 'failed' !== $r->status || $r->message !== $message ) {
				return false;
			}
		}
		return true;
	}

	/* ---------------- Uniqueness memory ---------------- */

	private static function used_headings( $job_id, $service_index ) {
		$all = get_option( 'seob_ap_used_' . $job_id, array() );
		return isset( $all[ $service_index ] ) ? (array) $all[ $service_index ] : array();
	}

	private static function remember_heading( $job_id, $service_index, array $v ) {
		$all = get_option( 'seob_ap_used_' . $job_id, array() );
		$all = is_array( $all ) ? $all : array();
		foreach ( array( 'section_2_heading', 'section_3_heading', 'section_4_heading', 'section_5_main_heading', 'section_7_heading' ) as $k ) {
			$all[ $service_index ][] = $v[ $k ];
		}
		$all[ $service_index ] = array_slice( $all[ $service_index ], -60 );
		update_option( 'seob_ap_used_' . $job_id, $all, false );
	}

	/* ---------------- Cron backup ---------------- */

	/**
	 * WP-Cron: process running jobs for up to ~40 seconds, then reschedule.
	 */
	public static function cron() {
		global $wpdb;
		$jt    = self::jobs_table();
		$ids   = $wpdb->get_col( "SELECT id FROM {$jt} WHERE status = 'running' ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$start = time();
		foreach ( $ids as $id ) {
			while ( time() - $start < 40 ) {
				$r = self::step( (int) $id );
				if ( empty( $r['last'] ) ) {
					break;
				}
			}
		}
		if ( $ids ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/* ---------------- Bulk actions ---------------- */

	public static function publish_all( $job_id ) {
		$n = 0;
		foreach ( self::items( $job_id ) as $it ) {
			if ( $it->post_id && 'draft' === get_post_status( $it->post_id ) && current_user_can( 'publish_post', $it->post_id ) ) {
				wp_update_post( array( 'ID' => (int) $it->post_id, 'post_status' => 'publish' ) );
				++$n;
			}
		}
		return $n;
	}

	public static function retry_failed( $job_id ) {
		global $wpdb;
		$wpdb->update( self::items_table(), array( 'status' => 'pending', 'attempts' => 0 ), array( 'job_id' => (int) $job_id, 'status' => 'failed' ) );
		self::set_job_status( $job_id, 'running' );
		self::schedule();
	}

	/**
	 * Give every finished page of a job its own photos (no AI, no text changes).
	 * Fixes pages made with the same photos on every location.
	 *
	 * @param int $job_id Job ID.
	 * @return int Pages changed.
	 */
	public static function refresh_images( $job_id ) {
		global $wpdb;
		$job = self::job( $job_id );
		if ( ! $job || empty( $job->settings['services'] ) ) {
			return 0;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$s               = $job->settings;
		$s['image_mode'] = 'page';
		if ( empty( $s['image_pool'] ) ) {
			$s['image_pool'] = SEOB_AP_Images::pools( $s['services'], SEOB_AP_Images::has_pexels(), (string) $s['business'] );
		}
		if ( ! isset( $s['image_copies'] ) ) {
			$s['image_copies'] = true;
		}
		$wpdb->update( self::jobs_table(), array( 'settings' => wp_json_encode( $s ) ), array( 'id' => (int) $job_id ) );

		$n = 0;
		foreach ( self::items( $job_id ) as $it ) {
			$post_id = (int) $it->post_id;
			if ( ! $post_id || ! in_array( $it->status, array( 'done', 'warning' ), true ) || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$v = SEOB_AP_Writer::page_images( $s, (array) $it );
			if ( ! empty( $s['image_copies'] ) ) {
				$v = array_merge(
					$v,
					array(
						'location'                      => $it->location,
						'service'                       => $it->service,
						'primary_keyword'               => (string) get_post_meta( $post_id, '_seob_ap_keyword', true ),
						'section_2_left_image_alt_tag'  => (string) SEOB_AP_Schema::get( $post_id, 'section_2_left_image_alt_tag' ),
						'section_3_right_image_alt_tag' => (string) SEOB_AP_Schema::get( $post_id, 'section_3_right_image_alt_tag' ),
						'section_4_right_image_alt_tag' => (string) SEOB_AP_Schema::get( $post_id, 'section_4_right_image_alt_tag' ),
					)
				);
				$v = self::page_image_copies( $v, $post_id );
			}
			foreach ( SEOB_AP_Writer::photo_fields() as $field ) {
				if ( ! empty( $v[ $field ] ) ) {
					SEOB_AP_Schema::save_field( $post_id, $field, (int) $v[ $field ] );
				}
			}
			if ( ! empty( $v['section_2_left_image'] ) ) {
				set_post_thumbnail( $post_id, (int) $v['section_2_left_image'] );
			}
			++$n;
		}
		return $n;
	}

	public static function trash_pages( $job_id ) {
		$n = 0;
		foreach ( self::items( $job_id ) as $it ) {
			if ( $it->post_id && in_array( $it->status, array( 'done', 'warning' ), true ) && current_user_can( 'delete_post', $it->post_id ) ) {
				if ( wp_trash_post( (int) $it->post_id ) ) {
					++$n;
				}
			}
		}
		return $n;
	}
}
