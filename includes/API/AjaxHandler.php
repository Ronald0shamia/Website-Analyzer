<?php
/**
 * AJAX Handler.
 *
 * @package WebsiteAnalyzer\API
 */

namespace WebsiteAnalyzer\API;

use WebsiteAnalyzer\Admin\Settings;
use WebsiteAnalyzer\Statistics\StatisticsManager;
use WebsiteAnalyzer\Helpers\RateLimiter;
use WebsiteAnalyzer\Helpers\IpHelper;
use WebsiteAnalyzer\Helpers\UrlValidator;

/**
 * Handles all AJAX requests for the plugin.
 */
class AjaxHandler {

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_wa_analyze', [ $this, 'handle_analyze' ] );
		add_action( 'wp_ajax_nopriv_wa_analyze', [ $this, 'handle_analyze' ] );

		add_action( 'wp_ajax_wa_ai_analyze', [ $this, 'handle_ai_analyze' ] );

		add_action( 'wp_ajax_wa_get_statistics', [ $this, 'handle_get_statistics' ] );
		add_action( 'wp_ajax_wa_clear_statistics', [ $this, 'handle_clear_statistics' ] );
	}

	/**
	 * Handle website analysis request.
	 *
	 * @return void
	 */
	public function handle_analyze(): void {
		check_ajax_referer( 'wa_analyze_nonce', 'nonce' );

		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$url     = UrlValidator::normalize_public_url( $raw_url );

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( [ 'message' => $url->get_error_message() ], 400 );
		}

		// Rate limiting.
		$ip            = IpHelper::get_ip();
		$max_per_hour  = (int) Settings::get( 'max_analyses_per_hour', 10 );
		$rate_limiter  = new RateLimiter();

		if ( ! $rate_limiter->check( $ip, $max_per_hour ) ) {
			wp_send_json_error( [ 'message' => __( 'Rate limit reached. Please try again later.', 'website-analyzer' ) ], 429 );
		}

		$start_time = microtime( true );

		try {
			$analyzer = new WebsiteAnalyzerService( $url );
			$results  = $analyzer->analyze();

			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			// Record statistics (domain only, no full report).
			StatisticsManager::record( [
				'domain'   => wp_parse_url( $url, PHP_URL_HOST ) ?? $url,
				'duration' => $duration,
				'success'  => true,
				'ip'       => '',
				'user_id'  => get_current_user_id(),
			] );

			wp_send_json_success( $results );

		} catch ( \Exception $e ) {
			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			StatisticsManager::record( [
				'domain'   => wp_parse_url( $url, PHP_URL_HOST ) ?? $url,
				'duration' => $duration,
				'success'  => false,
				'ip'       => '',
				'user_id'  => get_current_user_id(),
			] );

			wp_send_json_error( [ 'message' => esc_html( $e->getMessage() ) ], 500 );
		}
	}

	/**
	 * Handle AI analysis via Google Gemini.
	 *
	 * @return void
	 */
	public function handle_ai_analyze(): void {
		check_ajax_referer( 'wa_analyze_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'AI analysis is restricted to administrators.', 'website-analyzer' ) ], 403 );
		}

		$ip           = IpHelper::get_ip();
		$rate_limiter = new RateLimiter();
		if ( ! $rate_limiter->check( 'ai-' . $ip, 5 ) ) {
			wp_send_json_error( [ 'message' => __( 'AI rate limit reached. Please try again later.', 'website-analyzer' ) ], 429 );
		}

		$api_key = Settings::get( 'gemini_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'Gemini API key not configured.', 'website-analyzer' ) ], 400 );
		}

		$analysis_data = isset( $_POST['analysis_data'] ) ? wp_unslash( $_POST['analysis_data'] ) : '';
		if ( empty( $analysis_data ) ) {
			wp_send_json_error( [ 'message' => __( 'No analysis data provided.', 'website-analyzer' ) ], 400 );
		}

		// Decode and sanitize.
		$data = json_decode( sanitize_textarea_field( $analysis_data ), true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid analysis data.', 'website-analyzer' ) ], 400 );
		}

		$gemini = new GeminiClient( $api_key );

		try {
			$result = $gemini->analyze( $data );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Handle get statistics request (admin only).
	 *
	 * @return void
	 */
	public function handle_get_statistics(): void {
		check_ajax_referer( 'wa_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'website-analyzer' ) ], 403 );
		}

		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'week';
		$page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		$data = StatisticsManager::get_statistics( $filter, $page );
		wp_send_json_success( $data );
	}

	/**
	 * Handle clear statistics request (admin only).
	 *
	 * @return void
	 */
	public function handle_clear_statistics(): void {
		check_ajax_referer( 'wa_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'website-analyzer' ) ], 403 );
		}

		StatisticsManager::clear_all();
		wp_send_json_success( [ 'message' => __( 'Statistics cleared.', 'website-analyzer' ) ] );
	}
}
