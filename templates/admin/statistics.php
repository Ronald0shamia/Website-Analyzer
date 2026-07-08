<?php
/**
 * Admin Statistics template.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WebsiteAnalyzer\Statistics\StatisticsManager;
use WebsiteAnalyzer\Admin\Settings;

$filter  = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'week';
$page    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$data    = StatisticsManager::get_statistics( $filter, $page );
$rows    = $data['rows'];
$total   = $data['total'];
$store_ip = Settings::get( 'store_ip', true );

$filters = [
	'today' => __( 'Today', 'website-analyzer' ),
	'week'  => __( 'This Week', 'website-analyzer' ),
	'month' => __( 'This Month', 'website-analyzer' ),
	'year'  => __( 'This Year', 'website-analyzer' ),
	'all'   => __( 'All Time', 'website-analyzer' ),
];
?>
<div class="wrap wa-admin-wrap">
	<h1><?php esc_html_e( 'Analysis Statistics', 'website-analyzer' ); ?></h1>

	<!-- Filter Tabs -->
	<nav class="wa-filter-nav">
		<?php foreach ( $filters as $key => $label ) : ?>
			<a
				href="<?php echo esc_url( add_query_arg( [ 'page' => 'website-analyzer-statistics', 'filter' => $key, 'paged' => 1 ], admin_url( 'admin.php' ) ) ); ?>"
				class="wa-filter-link <?php echo $key === $filter ? 'active' : ''; ?>"
			>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
		<button id="wa-clear-stats" class="button button-secondary wa-clear-btn" style="margin-left:auto">
			<?php esc_html_e( 'Clear All Statistics', 'website-analyzer' ); ?>
		</button>
	</nav>

	<p class="wa-total-count">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: total count */
				_n( '%d record', '%d records', $total, 'website-analyzer' ),
				$total
			)
		);
		?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'website-analyzer' ); ?></th>
				<th><?php esc_html_e( 'Time', 'website-analyzer' ); ?></th>
				<th><?php esc_html_e( 'Domain', 'website-analyzer' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'website-analyzer' ); ?></th>
				<th><?php esc_html_e( 'Status', 'website-analyzer' ); ?></th>
				<th><?php esc_html_e( 'User', 'website-analyzer' ); ?></th>
				<?php if ( $store_ip ) : ?>
				<th><?php esc_html_e( 'IP Address', 'website-analyzer' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Browser', 'website-analyzer' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rows ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No statistics found.', 'website-analyzer' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$dt      = new DateTime( $row['created_at'] ?? 'now' );
				$user    = (int) $row['user_id'] > 0 ? get_userdata( (int) $row['user_id'] ) : false;
				$success = (bool) $row['success'];
				?>
				<tr>
					<td><?php echo esc_html( $dt->format( 'Y-m-d' ) ); ?></td>
					<td><?php echo esc_html( $dt->format( 'H:i:s' ) ); ?></td>
					<td><?php echo esc_html( $row['domain'] ); ?></td>
					<td><?php echo esc_html( number_format( (int) $row['duration_ms'] ) . ' ms' ); ?></td>
					<td>
						<?php if ( $success ) : ?>
							<span class="wa-badge wa-badge-success"><?php esc_html_e( 'Success', 'website-analyzer' ); ?></span>
						<?php else : ?>
							<span class="wa-badge wa-badge-error"><?php esc_html_e( 'Error', 'website-analyzer' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo $user ? esc_html( $user->user_login ) : esc_html__( 'Guest', 'website-analyzer' ); ?></td>
					<?php if ( $store_ip ) : ?>
					<td><?php echo esc_html( $row['ip_address'] ); ?></td>
					<?php endif; ?>
					<td title="<?php echo esc_attr( $row['user_agent'] ?? '' ); ?>">
						<?php echo esc_html( wp_html_excerpt( $row['user_agent'] ?? '', 40, '…' ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php
	// Pagination.
	$total_pages = (int) ceil( $total / $data['per_page'] );
	if ( $total_pages > 1 ) :
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo paginate_links( [
			'base'      => add_query_arg( [ 'paged' => '%#%', 'filter' => $filter ], admin_url( 'admin.php?page=website-analyzer-statistics' ) ),
			'format'    => '',
			'current'   => $page,
			'total'     => $total_pages,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		] );
		echo '</div></div>';
	endif;
	?>
</div>
