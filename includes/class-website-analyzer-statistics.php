<?php
/**
 * Usage statistics.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles usage persistence and admin reporting.
 */
final class Website_Analyzer_Statistics {
	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register statistics page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'website-analyzer',
			__( 'Statistiken', 'website-analyzer' ),
			__( 'Statistiken', 'website-analyzer' ),
			'manage_options',
			'website-analyzer-statistics',
			array( $this, 'render_statistics_page' )
		);
	}

	/**
	 * Store a usage event without analysis results.
	 *
	 * @param string $url Submitted URL.
	 * @return bool
	 */
	public function record_usage( string $url ): bool {
		global $wpdb;

		$normalized_url = $this->normalize_url( $url );
		$host           = wp_parse_url( $normalized_url, PHP_URL_HOST );

		if ( empty( $host ) ) {
			return false;
		}

		$table_name = $this->get_table_name();
		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$result = $wpdb->insert(
			$table_name,
			array(
				'domain'     => strtolower( $host ),
				'url_hash'   => hash( 'sha256', $normalized_url ),
				'created_at' => current_time( 'mysql', true ),
				'user_id'    => get_current_user_id() ?: null,
				'ip_hash'    => $ip_address ? hash( 'sha256', $ip_address . wp_salt( 'nonce' ) ) : null,
				'user_agent' => substr( $user_agent, 0, 255 ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Render statistics page.
	 */
	public function render_statistics_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'website-analyzer' ) );
		}

		$summary = $this->get_summary();
		$domains = $this->get_domain_rows();
		$recent  = $this->get_recent_rows();
		?>
		<div class="wrap website-analyzer-admin">
			<h1><?php esc_html_e( 'Website Analyzer Statistiken', 'website-analyzer' ); ?></h1>
			<div class="website-analyzer-stat-grid">
				<div class="website-analyzer-stat-card">
					<span><?php esc_html_e( 'Nutzungen gesamt', 'website-analyzer' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $summary['total'] ) ); ?></strong>
				</div>
				<div class="website-analyzer-stat-card">
					<span><?php esc_html_e( 'Domains', 'website-analyzer' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $summary['domains'] ) ); ?></strong>
				</div>
				<div class="website-analyzer-stat-card">
					<span><?php esc_html_e( 'Letzte Nutzung', 'website-analyzer' ); ?></span>
					<strong><?php echo esc_html( $summary['last_used'] ? get_date_from_gmt( $summary['last_used'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '-' ); ?></strong>
				</div>
			</div>

			<h2><?php esc_html_e( 'Meist analysierte Domains', 'website-analyzer' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Domain', 'website-analyzer' ); ?></th>
						<th><?php esc_html_e( 'Anzahl', 'website-analyzer' ); ?></th>
						<th><?php esc_html_e( 'Zuletzt genutzt', 'website-analyzer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $domains ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'Noch keine Nutzung erfasst.', 'website-analyzer' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $domains as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['domain'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['uses'] ) ); ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $row['last_used'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Letzte Nutzungen', 'website-analyzer' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Zeitpunkt', 'website-analyzer' ); ?></th>
						<th><?php esc_html_e( 'Domain', 'website-analyzer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'Noch keine Nutzung erfasst.', 'website-analyzer' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent as $row ) : ?>
							<tr>
								<td><?php echo esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
								<td><?php echo esc_html( $row['domain'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Normalize a URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function normalize_url( string $url ): string {
		$url = trim( $url );

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Get table name.
	 */
	private function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'website_analyzer_usage';
	}

	/**
	 * Get summary data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_summary(): array {
		global $wpdb;

		$table_name = $this->get_table_name();

		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total, COUNT(DISTINCT domain) AS domains, MAX(created_at) AS last_used FROM {$table_name}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : array(
			'total'     => 0,
			'domains'   => 0,
			'last_used' => '',
		);
	}

	/**
	 * Get domain aggregates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_domain_rows(): array {
		global $wpdb;

		$table_name = $this->get_table_name();

		$rows = $wpdb->get_results(
			"SELECT domain, COUNT(*) AS uses, MAX(created_at) AS last_used FROM {$table_name} GROUP BY domain ORDER BY uses DESC, last_used DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent usage rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_recent_rows(): array {
		global $wpdb;

		$table_name = $this->get_table_name();

		$rows = $wpdb->get_results(
			"SELECT domain, created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
