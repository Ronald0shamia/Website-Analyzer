<?php
/**
 * Admin Dashboard template.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WebsiteAnalyzer\Statistics\StatisticsManager;

$totals  = StatisticsManager::get_totals();
$domains = StatisticsManager::get_top_domains( 10 );
$chart   = StatisticsManager::get_chart_data( 'week' );
?>
<div class="wrap wa-admin-wrap">
	<h1><?php esc_html_e( 'Website Analyzer — Dashboard', 'website-analyzer' ); ?></h1>

	<!-- Stat Cards -->
	<div class="wa-admin-cards">
		<div class="wa-admin-card">
			<span class="wa-admin-card-value"><?php echo esc_html( number_format( $totals['all'] ) ); ?></span>
			<span class="wa-admin-card-label"><?php esc_html_e( 'Total Analyses', 'website-analyzer' ); ?></span>
		</div>
		<div class="wa-admin-card">
			<span class="wa-admin-card-value"><?php echo esc_html( number_format( $totals['today'] ) ); ?></span>
			<span class="wa-admin-card-label"><?php esc_html_e( 'Today', 'website-analyzer' ); ?></span>
		</div>
		<div class="wa-admin-card">
			<span class="wa-admin-card-value"><?php echo esc_html( number_format( $totals['week'] ) ); ?></span>
			<span class="wa-admin-card-label"><?php esc_html_e( 'This Week', 'website-analyzer' ); ?></span>
		</div>
		<div class="wa-admin-card">
			<span class="wa-admin-card-value"><?php echo esc_html( number_format( $totals['month'] ) ); ?></span>
			<span class="wa-admin-card-label"><?php esc_html_e( 'This Month', 'website-analyzer' ); ?></span>
		</div>
	</div>

	<!-- Chart -->
	<div class="wa-admin-panel">
		<h2><?php esc_html_e( 'Analyses per Day (Last 7 Days)', 'website-analyzer' ); ?></h2>
		<canvas id="wa-usage-chart" height="100"></canvas>
	</div>

	<!-- Top Domains -->
	<div class="wa-admin-panel">
		<h2><?php esc_html_e( 'Most Analyzed Domains', 'website-analyzer' ); ?></h2>
		<?php if ( empty( $domains ) ) : ?>
			<p><?php esc_html_e( 'No data yet.', 'website-analyzer' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Domain', 'website-analyzer' ); ?></th>
						<th><?php esc_html_e( 'Analyses', 'website-analyzer' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $domains as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['domain'] ); ?></td>
						<td><?php echo esc_html( number_format( (int) $row['count'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const chartData = <?php echo wp_json_encode( $chart ); ?>;
	const labels = chartData.map(r => r.date);
	const data   = chartData.map(r => parseInt(r.count, 10));

	const ctx = document.getElementById('wa-usage-chart');
	if (ctx && window.Chart) {
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels,
				datasets: [{
					label: '<?php echo esc_js( __( 'Analyses', 'website-analyzer' ) ); ?>',
					data,
					backgroundColor: 'rgba(0,115,170,0.6)',
					borderColor: 'rgba(0,115,170,1)',
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
			}
		});
	}
});
</script>
