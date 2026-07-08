<?php
/**
 * Google Gemini API Client.
 *
 * @package WebsiteAnalyzer\API
 */

namespace WebsiteAnalyzer\API;

/**
 * Sends analysis data to Google Gemini and retrieves AI-powered insights.
 */
class GeminiClient {

	/**
	 * Gemini API endpoint.
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Gemini API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Analyze website data using Gemini AI.
	 *
	 * @param array<string, mixed> $analysis_data Collected analysis data.
	 * @return array<string, mixed>
	 * @throws \Exception On API error.
	 */
	public function analyze( array $analysis_data ): array {
		$prompt = $this->build_prompt( $analysis_data );

		$response = wp_remote_post(
			self::API_ENDPOINT . '?key=' . urlencode( $this->api_key ),
			[
				'timeout'     => 60,
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'body'        => wp_json_encode( [
					'contents' => [
						[
							'parts' => [
								[ 'text' => $prompt ],
							],
						],
					],
					'generationConfig' => [
						'temperature'    => 0.2,
						'responseMimeType' => 'application/json',
					],
				] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$message = $data['error']['message'] ?? __( 'Gemini API error', 'website-analyzer' );
			throw new \Exception( esc_html( $message ) );
		}

		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( empty( $text ) ) {
			throw new \Exception( __( 'Empty response from Gemini API.', 'website-analyzer' ) );
		}

		$parsed = json_decode( $text, true );
		if ( ! is_array( $parsed ) ) {
			throw new \Exception( __( 'Invalid JSON response from Gemini API.', 'website-analyzer' ) );
		}

		return $parsed;
	}

	/**
	 * Build the analysis prompt.
	 *
	 * @param array<string, mixed> $data Analysis data.
	 * @return string
	 */
	private function build_prompt( array $data ): string {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT );

		return <<<PROMPT
You are an expert website analyst. Analyze the following website data and provide a comprehensive assessment.

IMPORTANT: Use ONLY the provided data. Do not make assumptions beyond what is given.

Website Data:
{$json}

Respond ONLY with a valid JSON object (no markdown, no preamble) with this exact structure:
{
  "overall_score": <integer 0-100>,
  "summary": "<2-3 sentence executive summary>",
  "ratings": {
    "performance": <0-100>,
    "seo": <0-100>,
    "security": <0-100>,
    "mobile": <0-100>,
    "accessibility": <0-100>,
    "technical": <0-100>
  },
  "critical_issues": [
    {"issue": "<issue title>", "description": "<details>", "priority": "critical|high|medium|low"}
  ],
  "seo_report": {
    "assessment": "<paragraph>",
    "strengths": ["<item>"],
    "weaknesses": ["<item>"]
  },
  "performance_tips": ["<actionable tip>"],
  "content_analysis": "<paragraph about content quality and structure>",
  "recommendations": [
    {"title": "<title>", "description": "<details>", "priority": "critical|high|medium|low", "category": "seo|performance|security|accessibility|technical"}
  ],
  "ai_notes": "<additional expert observations>"
}
PROMPT;
	}
}
