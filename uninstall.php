<?php
/**
 * Remove Website Analyzer data on uninstall.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'website_analyzer_usage';

$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'website_analyzer_settings' );
delete_option( 'website_analyzer_db_version' );
