<?php
/**
 * Frontend Shortcode.
 *
 * @package WebsiteAnalyzer\Frontend
 */

namespace WebsiteAnalyzer\Frontend;

use WebsiteAnalyzer\Admin\Settings;

/**
 * Registers and renders the [website_analyzer] shortcode.
 */
class Shortcode {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'website_analyzer', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'website-analyzer-frontend',
			WEBSITE_ANALYZER_URL . 'assets/css/frontend.css',
			[],
			WEBSITE_ANALYZER_VERSION
		);

		wp_enqueue_script(
			'website-analyzer-frontend',
			WEBSITE_ANALYZER_URL . 'assets/js/analyzer.js',
			[],
			WEBSITE_ANALYZER_VERSION,
			true
		);

		// jsPDF for report generation.
		wp_enqueue_script(
			'jspdf',
			'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
			[],
			'2.5.1',
			true
		);

		wp_localize_script(
			'website-analyzer-frontend',
			'waConfig',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wa_analyze_nonce' ),
				'companyName' => esc_js( Settings::get( 'company_name', get_bloginfo( 'name' ) ) ),
				'pdfLogo'     => esc_js( Settings::get( 'pdf_logo', '' ) ),
				'hasGemini'   => ! empty( Settings::get( 'gemini_api_key', '' ) ),
				'i18n'        => [
					'analyzing'       => __( 'Analyzing…', 'website-analyzer' ),
					'complete'        => __( 'Analysis complete', 'website-analyzer' ),
					'error'           => __( 'An error occurred', 'website-analyzer' ),
					'enterUrl'        => __( 'Please enter a valid URL.', 'website-analyzer' ),
					'rateLimited'     => __( 'Rate limit reached. Please try again later.', 'website-analyzer' ),
					'downloadPdf'     => __( 'Download PDF', 'website-analyzer' ),
					'downloadJson'    => __( 'Download JSON', 'website-analyzer' ),
					'downloadCsv'     => __( 'Download CSV', 'website-analyzer' ),
				],
			]
		);
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( array|string $atts ): string {
		ob_start();
		include WEBSITE_ANALYZER_DIR . 'templates/frontend/analyzer.php';
		return ob_get_clean() ?: '';
	}
}
