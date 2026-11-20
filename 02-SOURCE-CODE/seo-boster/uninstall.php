<?php
/**
 * Removes everything SEO Boster stored when the plugin is deleted.
 *
 * @package SEO_Boster
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$seob_table = $wpdb->prefix . 'seob_placements';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$seob_table}" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", '_seob_block', '_seob_position' ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 'seob\_%', '\_transient\_%seob\_ka\_%' ) );

$seob_tables = array( $wpdb->prefix . 'seob_ap_jobs', $wpdb->prefix . 'seob_ap_items' );
foreach ( $seob_tables as $seob_t ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$seob_t}" );
}
wp_clear_scheduled_hook( 'seob_ap_run_jobs' );
// Area pages themselves are content, so they are kept.
