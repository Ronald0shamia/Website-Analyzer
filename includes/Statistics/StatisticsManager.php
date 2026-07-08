<?php
/**
 * Statistics Manager.
 *
 * @package WebsiteAnalyzer\Statistics
 */

namespace WebsiteAnalyzer\Statistics;

/**
 * Manages analysis statistics in the database.
 * Only stores metadata (domain, duration, success) — never full reports.
 */
class StatisticsManager {

	/**
	 * Table name constant.
	 *
	 * @var string
	 */
	const TABLE_SUFFIX = 'wa_statistics';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Nothing to hook at runtime; methods are called directly.
	}

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create database tables on activation.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table      = self::get_table_name();
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			domain      VARCHAR(255)        NOT NULL DEFAULT '',
			duration_ms INT(11)             NOT NULL DEFAULT 0,
			success     TINYINT(1)          NOT NULL DEFAULT 1,
			ip_address  VARCHAR(45)         NOT NULL DEFAULT '',
			user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			user_agent  VARCHAR(512)        NOT NULL DEFAULT '',
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY domain (domain(191)),
			KEY created_at (created_at),
			KEY success (success)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'website_analyzer_db_version', '1.0.0' );
	}

	/**
	 * Record a single analysis.
	 *
	 * @param array<string, mixed> $data Analysis metadata.
	 * @return void
	 */
	public static function record( array $data ): void {
		global $wpdb;

		$table = self::get_table_name();

		$wpdb->insert(
			$table,
			[
				'domain'     => sanitize_text_field( $data['domain'] ?? '' ),
				'duration_ms'=> absint( $data['duration'] ?? 0 ),
				'success'    => (int) ( $data['success'] ?? true ),
				'ip_address' => '',
				'user_id'    => absint( $data['user_id'] ?? 0 ),
				'user_agent' => '',
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%d', '%s', '%d', '%s', '%s' ]
		);
	}

	/**
	 * Get statistics with optional filter.
	 *
	 * @param string $filter Period filter: today|week|month|year|all.
	 * @param int    $page   Pagination page.
	 * @return array<string, mixed>
	 */
	public static function get_statistics( string $filter = 'week', int $page = 1 ): array {
		global $wpdb;

		$table      = self::get_table_name();
		$per_page   = 50;
		$offset     = ( $page - 1 ) * $per_page;
		$date_where = self::get_date_where( $filter );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, domain, duration_ms, success, ip_address, user_id, user_agent, created_at
				 FROM {$table}
				 WHERE {$date_where}
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE {$date_where}"
		);

		// Aggregate stats.
		$totals  = self::get_totals();
		$chart   = self::get_chart_data( $filter );
		$domains = self::get_top_domains( 10 );
		// phpcs:enable

		return [
			'rows'       => $rows,
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'totals'     => $totals,
			'chart'      => $chart,
			'top_domains'=> $domains,
		];
	}

	/**
	 * Get dashboard totals.
	 *
	 * @return array<string, int>
	 */
	public static function get_totals(): array {
		global $wpdb;
		$table = self::get_table_name();

		$today = current_time( 'Y-m-d' );

		return [
			'all'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s", $today ) ),
			'week'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
			'month' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" ),
		];
	}

	/**
	 * Get chart data grouped by day.
	 *
	 * @param string $filter Period filter.
	 * @return array<string, mixed>
	 */
	public static function get_chart_data( string $filter = 'week' ): array {
		global $wpdb;
		$table      = self::get_table_name();
		$date_where = self::get_date_where( $filter );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT DATE(created_at) as date, COUNT(*) as count
			 FROM {$table}
			 WHERE {$date_where}
			 GROUP BY DATE(created_at)
			 ORDER BY date ASC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Get top analyzed domains.
	 *
	 * @param int $limit Number of results.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_top_domains( int $limit = 10 ): array {
		global $wpdb;
		$table = self::get_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT domain, COUNT(*) as count
				 FROM {$table}
				 GROUP BY domain
				 ORDER BY count DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Clear all statistics.
	 *
	 * @return void
	 */
	public static function clear_all(): void {
		global $wpdb;
		$table = self::get_table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build SQL WHERE clause for date filtering.
	 *
	 * @param string $filter Period filter.
	 * @return string
	 */
	private static function get_date_where( string $filter ): string {
		return match ( $filter ) {
			'today' => "DATE(created_at) = CURDATE()",
			'week'  => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			'year'  => "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
			default => '1=1',
		};
	}

	/**
	 * Drop all plugin tables on uninstall.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
