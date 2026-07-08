<?php
/**
 * Main plugin container.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates public, admin, and AJAX modules.
 */
final class Website_Analyzer_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot modules.
	 */
	public function boot(): void {
		$settings   = new Website_Analyzer_Settings();
		$statistics = new Website_Analyzer_Statistics();
		$public     = new Website_Analyzer_Public( $settings );
		$ajax       = new Website_Analyzer_Ajax( $statistics );

		$settings->register_hooks();
		$statistics->register_hooks();
		$public->register_hooks();
		$ajax->register_hooks();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}
}
