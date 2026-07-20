<?php
/**
 * Plugin Name: MRS Website Analyzer
 * Plugin URI:  https://mrs-dev.com/loesungen/website-analyzer
 * Description: Comprehensive website analysis tool with AI-powered insights via Google Gemini API.
 * Version:     1.0.1
 * Author:      Website Analyzer
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: website-analyzer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package WebsiteAnalyzer
 */

namespace WebsiteAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBSITE_ANALYZER_VERSION', '1.0.1' );
define( 'WEBSITE_ANALYZER_FILE', __FILE__ );
define( 'WEBSITE_ANALYZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEBSITE_ANALYZER_URL', plugin_dir_url( __FILE__ ) );
define( 'WEBSITE_ANALYZER_BASENAME', plugin_basename( __FILE__ ) );

// PSR-4 Autoloader.
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'WebsiteAnalyzer\\';
	$base_dir = WEBSITE_ANALYZER_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Bootstrap the plugin.
 */
function website_analyzer_init(): void {
	$plugin = Plugin::get_instance();
	$plugin->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\website_analyzer_init' );

register_activation_hook( __FILE__, [ 'WebsiteAnalyzer\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WebsiteAnalyzer\\Plugin', 'deactivate' ] );
