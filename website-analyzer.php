<?php
/**
 * Plugin Name: MRS Website Analyzer
 * Plugin URI: https://example.com/website-analyzer
 * Description: Browserbasierte Website-Analyse mit Exporten, Admin-Einstellungen und Nutzungsstatistiken.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Website Analyzer
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: website-analyzer
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBSITE_ANALYZER_VERSION', '1.0.0' );
define( 'WEBSITE_ANALYZER_FILE', __FILE__ );
define( 'WEBSITE_ANALYZER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBSITE_ANALYZER_URL', plugin_dir_url( __FILE__ ) );

require_once WEBSITE_ANALYZER_PATH . 'includes/class-website-analyzer-autoloader.php';

Website_Analyzer_Autoloader::register();

register_activation_hook( __FILE__, array( Website_Analyzer_Activator::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Website_Analyzer_Plugin::instance()->boot();
	}
);
