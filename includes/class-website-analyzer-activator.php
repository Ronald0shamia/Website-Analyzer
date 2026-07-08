<?php
/**
 * Activation tasks.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates required storage.
 */
final class Website_Analyzer_Activator {
	/**
	 * Create the statistics table.
	 */
	public static function activate(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'website_analyzer_usage';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			url_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY domain (domain),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'website_analyzer_db_version', WEBSITE_ANALYZER_VERSION );
	}
}
