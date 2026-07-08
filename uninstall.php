<?php
/**
 * Uninstall script.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all database tables and options created by the plugin.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom statistics table.
$table = $wpdb->prefix . 'wa_statistics';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete all plugin options.
delete_option( 'website_analyzer_options' );
delete_option( 'website_analyzer_db_version' );

// Remove all rate-limit transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_wa_rate_%'
	 OR option_name LIKE '_transient_timeout_wa_rate_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
