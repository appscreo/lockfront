<?php
/**
 * Lockfront Uninstall
 * Removes all plugin data when deleted from the WordPress admin.
 *
 * @package Lockfront
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove options.
delete_option( 'lkfr_settings' );
delete_option( 'lkfr_db_version' );

// Drop custom tables.
$lkfr_tables = array( 'lkfr_login_logs', 'lkfr_bypass_tokens', 'lkfr_login_attempts' );
foreach ( $lkfr_tables as $lkfr_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$lkfr_table}" );
}
