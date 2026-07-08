<?php
/**
 * Admin Menu registration.
 *
 * @package WebsiteAnalyzer\Admin
 */

namespace WebsiteAnalyzer\Admin;

/**
 * Registers admin menu pages.
 */
class AdminMenu {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Add menu and sub-menu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Website Analyzer', 'website-analyzer' ),
			__( 'Website Analyzer', 'website-analyzer' ),
			'manage_options',
			'website-analyzer',
			[ $this, 'render_dashboard' ],
			'dashicons-chart-area',
			30
		);

		add_submenu_page(
			'website-analyzer',
			__( 'Dashboard', 'website-analyzer' ),
			__( 'Dashboard', 'website-analyzer' ),
			'manage_options',
			'website-analyzer',
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			'website-analyzer',
			__( 'Statistics', 'website-analyzer' ),
			__( 'Statistics', 'website-analyzer' ),
			'manage_options',
			'website-analyzer-statistics',
			[ $this, 'render_statistics' ]
		);

		add_submenu_page(
			'website-analyzer',
			__( 'Settings', 'website-analyzer' ),
			__( 'Settings', 'website-analyzer' ),
			'manage_options',
			'website-analyzer-settings',
			[ $this, 'render_settings' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$pages = [
			'toplevel_page_website-analyzer',
			'website-analyzer_page_website-analyzer-statistics',
			'website-analyzer_page_website-analyzer-settings',
		];

		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'website-analyzer-admin',
			WEBSITE_ANALYZER_URL . 'assets/css/admin.css',
			[],
			WEBSITE_ANALYZER_VERSION
		);

		wp_enqueue_script(
			'website-analyzer-admin',
			WEBSITE_ANALYZER_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WEBSITE_ANALYZER_VERSION,
			true
		);

		wp_localize_script(
			'website-analyzer-admin',
			'waAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wa_admin_nonce' ),
				'i18n'    => [
					'confirmDelete' => __( 'Are you sure you want to delete all statistics?', 'website-analyzer' ),
				],
			]
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'website-analyzer' ) );
		}
		include WEBSITE_ANALYZER_DIR . 'templates/admin/dashboard.php';
	}

	/**
	 * Render statistics page.
	 *
	 * @return void
	 */
	public function render_statistics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'website-analyzer' ) );
		}
		include WEBSITE_ANALYZER_DIR . 'templates/admin/statistics.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'website-analyzer' ) );
		}
		include WEBSITE_ANALYZER_DIR . 'templates/admin/settings.php';
	}
}
