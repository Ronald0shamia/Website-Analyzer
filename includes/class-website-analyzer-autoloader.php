<?php
/**
 * Minimal class autoloader.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin classes from the includes directory.
 */
final class Website_Analyzer_Autoloader {
	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Load a plugin class.
	 *
	 * @param string $class_name Class name.
	 */
	public static function autoload( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'Website_Analyzer_' ) ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$file_path = WEBSITE_ANALYZER_PATH . 'includes/' . $file_name;

		if ( is_readable( $file_path ) ) {
			require_once $file_path;
		}
	}
}
