<?php
/**
 * AJAX endpoints.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests.
 */
final class Website_Analyzer_Ajax {
	/**
	 * Statistics service.
	 *
	 * @var Website_Analyzer_Statistics
	 */
	private Website_Analyzer_Statistics $statistics;

	/**
	 * Constructor.
	 *
	 * @param Website_Analyzer_Statistics $statistics Statistics service.
	 */
	public function __construct( Website_Analyzer_Statistics $statistics ) {
		$this->statistics = $statistics;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_website_analyzer_record_usage', array( $this, 'record_usage' ) );
		add_action( 'wp_ajax_nopriv_website_analyzer_record_usage', array( $this, 'record_usage' ) );
	}

	/**
	 * Record a usage event.
	 */
	public function record_usage(): void {
		check_ajax_referer( 'website_analyzer_frontend', 'nonce' );

		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';

		if ( empty( $url ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Es wurde keine URL übermittelt.', 'website-analyzer' ) ),
				400
			);
		}

		if ( $this->is_rate_limited() ) {
			wp_send_json_error(
				array( 'message' => __( 'Bitte warte kurz, bevor du die naechste Analyse startest.', 'website-analyzer' ) ),
				429
			);
		}

		$stored = $this->statistics->record_usage( $url );

		if ( ! $stored ) {
			wp_send_json_error(
				array( 'message' => __( 'Die Nutzung konnte nicht gespeichert werden.', 'website-analyzer' ) ),
				400
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Nutzung wurde gespeichert.', 'website-analyzer' ) )
		);
	}

	/**
	 * Basic per-client rate limit for usage logging.
	 */
	private function is_rate_limited(): bool {
		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key        = 'website_analyzer_rate_' . hash( 'sha256', $ip_address . wp_salt( 'nonce' ) );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, '1', 10 );

		return false;
	}
}
