<?php
/**
 * Database layer. Stores every keyword placement so it can be reported and undone.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

final class SEOB_DB {

	const DB_VERSION = '1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'seob_placements';
	}

	/**
	 * Create or update the table. Runs on activation and when the schema version changes.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id varchar(40) NOT NULL DEFAULT '',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			keyword varchar(191) NOT NULL DEFAULT '',
			sentence text NOT NULL,
			line_no int(11) unsigned NOT NULL DEFAULT 0,
			score decimal(8,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			meta_backup longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY post_status (post_id,status),
			KEY batch_id (batch_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'seob_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'seob_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Insert a placement row.
	 *
	 * @param array $data Row data.
	 * @return int Insert ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'batch_id'    => (string) $data['batch_id'],
				'post_id'     => (int) $data['post_id'],
				'keyword'     => (string) $data['keyword'],
				'sentence'    => (string) $data['sentence'],
				'line_no'     => (int) $data['line_no'],
				'score'       => (float) $data['score'],
				'status'      => 'active',
				'meta_backup' => isset( $data['meta_backup'] ) ? (string) $data['meta_backup'] : null,
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function active_for_post( $post_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d AND status = 'active' ORDER BY line_no ASC, id ASC", $post_id ) );
	}

	public static function by_batch( $batch_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_id = %s ORDER BY post_id ASC, line_no ASC", $batch_id ) );
	}

	/**
	 * All active placements, newest batch first.
	 */
	public static function all_active( $limit = 500 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY post_id ASC, line_no ASC LIMIT %d", $limit ) );
	}

	/**
	 * Batch history with counts.
	 */
	public static function batches( $limit = 20 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT batch_id, MIN(created_at) AS created_at, COUNT(*) AS total, SUM(status = 'active') AS active, COUNT(DISTINCT post_id) AS pages FROM {$table} GROUP BY batch_id ORDER BY MIN(id) DESC LIMIT %d", $limit ) );
	}

	public static function stats() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( "SELECT COUNT(*) AS keywords, COUNT(DISTINCT post_id) AS pages FROM {$table} WHERE status = 'active'" );
		return array(
			'keywords' => $row ? (int) $row->keywords : 0,
			'pages'    => $row ? (int) $row->pages : 0,
		);
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	public static function set_line( $id, $line ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'line_no' => (int) $line ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
	}

	public static function drop() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
