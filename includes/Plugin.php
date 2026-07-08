<?php
/**
 * Main Plugin class.
 *
 * @package WebsiteAnalyzer
 */

namespace WebsiteAnalyzer;

use WebsiteAnalyzer\Admin\AdminMenu;
use WebsiteAnalyzer\Admin\Settings;
use WebsiteAnalyzer\Frontend\Shortcode;
use WebsiteAnalyzer\API\AjaxHandler;
use WebsiteAnalyzer\Statistics\StatisticsManager;

/**
 * Central plugin bootstrap class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();
		$this->register_components();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'website-analyzer',
			false,
			dirname( WEBSITE_ANALYZER_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register all plugin components.
	 *
	 * @return void
	 */
	private function register_components(): void {
		// Admin.
		if ( is_admin() ) {
			( new AdminMenu() )->register();
			( new Settings() )->register();
		}

		// Frontend shortcode.
		( new Shortcode() )->register();

		// AJAX handlers (both logged-in and not).
		( new AjaxHandler() )->register();

		// Statistics tracker.
		( new StatisticsManager() )->register();
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		StatisticsManager::create_tables();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
