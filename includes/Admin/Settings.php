<?php
/**
 * Plugin Settings using WordPress Settings API.
 *
 * @package WebsiteAnalyzer\Admin
 */

namespace WebsiteAnalyzer\Admin;

/**
 * Registers and manages plugin settings.
 */
class Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'website_analyzer_settings';

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'website_analyzer_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ $this, 'sanitize_options' ],
				'default'           => $this->get_defaults(),
			]
		);

		// API Section.
		add_settings_section(
			'wa_api_section',
			__( 'API Configuration', 'website-analyzer' ),
			null,
			'website-analyzer-settings'
		);

		add_settings_field(
			'gemini_api_key',
			__( 'Google Gemini API Key', 'website-analyzer' ),
			[ $this, 'render_api_key_field' ],
			'website-analyzer-settings',
			'wa_api_section'
		);

		// Analysis Section.
		add_settings_section(
			'wa_analysis_section',
			__( 'Analysis Settings', 'website-analyzer' ),
			null,
			'website-analyzer-settings'
		);

		add_settings_field(
			'analysis_timeout',
			__( 'Analysis Timeout (seconds)', 'website-analyzer' ),
			[ $this, 'render_timeout_field' ],
			'website-analyzer-settings',
			'wa_analysis_section'
		);

		add_settings_field(
			'max_analyses_per_hour',
			__( 'Max Analyses per Hour', 'website-analyzer' ),
			[ $this, 'render_max_analyses_field' ],
			'website-analyzer-settings',
			'wa_analysis_section'
		);

		// Report Section.
		add_settings_section(
			'wa_report_section',
			__( 'Report Settings', 'website-analyzer' ),
			null,
			'website-analyzer-settings'
		);

		add_settings_field(
			'company_name',
			__( 'Company Name', 'website-analyzer' ),
			[ $this, 'render_company_name_field' ],
			'website-analyzer-settings',
			'wa_report_section'
		);

		add_settings_field(
			'pdf_logo',
			__( 'PDF Logo URL', 'website-analyzer' ),
			[ $this, 'render_pdf_logo_field' ],
			'website-analyzer-settings',
			'wa_report_section'
		);

		// Privacy Section.
		add_settings_section(
			'wa_privacy_section',
			__( 'Privacy & Statistics', 'website-analyzer' ),
			null,
			'website-analyzer-settings'
		);

		add_settings_field(
			'store_ip',
			__( 'Store IP Addresses', 'website-analyzer' ),
			[ $this, 'render_store_ip_field' ],
			'website-analyzer-settings',
			'wa_privacy_section'
		);

		add_settings_field(
			'disable_cache',
			__( 'Disable Cache', 'website-analyzer' ),
			[ $this, 'render_disable_cache_field' ],
			'website-analyzer-settings',
			'wa_privacy_section'
		);
	}

	/**
	 * Get default option values.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [
			'gemini_api_key'        => '',
			'analysis_timeout'      => 12,
			'max_analyses_per_hour' => 10,
			'company_name'          => get_bloginfo( 'name' ),
			'pdf_logo'              => '',
			'store_ip'              => false,
			'disable_cache'         => false,
		];
	}

	/**
	 * Get a specific option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$options = get_option( self::OPTION_NAME, [] );
		return $options[ $key ] ?? $default;
	}

	/**
	 * Sanitize options before saving.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_options( array $input ): array {
		$sanitized = [];

		$sanitized['gemini_api_key']        = sanitize_text_field( $input['gemini_api_key'] ?? '' );
		$sanitized['analysis_timeout']      = absint( $input['analysis_timeout'] ?? 30 );
		$sanitized['max_analyses_per_hour'] = absint( $input['max_analyses_per_hour'] ?? 10 );
		$sanitized['company_name']          = sanitize_text_field( $input['company_name'] ?? '' );
		$sanitized['pdf_logo']              = esc_url_raw( $input['pdf_logo'] ?? '' );
		$sanitized['store_ip']              = false;
		$sanitized['disable_cache']         = ! empty( $input['disable_cache'] );

		// Clamp values.
		$sanitized['analysis_timeout']      = max( 5, min( 12, $sanitized['analysis_timeout'] ) );
		$sanitized['max_analyses_per_hour'] = max( 1, min( 100, $sanitized['max_analyses_per_hour'] ) );

		return $sanitized;
	}

	/**
	 * Render API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$value = self::get( 'gemini_api_key', '' );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gemini_api_key]"
			id="gemini_api_key"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter your Google Gemini API key for AI-powered analysis.', 'website-analyzer' ); ?>
			<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Get API Key', 'website-analyzer' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render timeout field.
	 *
	 * @return void
	 */
	public function render_timeout_field(): void {
		$value = self::get( 'analysis_timeout', 12 );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[analysis_timeout]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			min="5"
			max="12"
			class="small-text"
		/>
		<p class="description"><?php esc_html_e( 'Timeout in seconds (10–120).', 'website-analyzer' ); ?></p>
		<?php
	}

	/**
	 * Render max analyses field.
	 *
	 * @return void
	 */
	public function render_max_analyses_field(): void {
		$value = self::get( 'max_analyses_per_hour', 10 );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_analyses_per_hour]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			min="1"
			max="100"
			class="small-text"
		/>
		<p class="description"><?php esc_html_e( 'Maximum analyses allowed per hour per IP.', 'website-analyzer' ); ?></p>
		<?php
	}

	/**
	 * Render company name field.
	 *
	 * @return void
	 */
	public function render_company_name_field(): void {
		$value = self::get( 'company_name', '' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[company_name]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php
	}

	/**
	 * Render PDF logo field.
	 *
	 * @return void
	 */
	public function render_pdf_logo_field(): void {
		$value = self::get( 'pdf_logo', '' );
		?>
		<input
			type="url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[pdf_logo]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://"
		/>
		<p class="description"><?php esc_html_e( 'URL to the logo image used in PDF reports.', 'website-analyzer' ); ?></p>
		<?php
	}

	/**
	 * Render store IP checkbox.
	 *
	 * @return void
	 */
	public function render_store_ip_field(): void {
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[store_ip]"
				value="1"
				disabled
			/>
			<?php esc_html_e( 'Store visitor IP addresses in statistics', 'website-analyzer' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Disabled for privacy. IP addresses are used only transiently for rate limiting and are not stored in reports or statistics.', 'website-analyzer' ); ?></p>
		<?php
	}

	/**
	 * Render disable cache checkbox.
	 *
	 * @return void
	 */
	public function render_disable_cache_field(): void {
		$value = self::get( 'disable_cache', false );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_cache]"
				value="1"
				<?php checked( $value ); ?>
			/>
			<?php esc_html_e( 'Disable result caching', 'website-analyzer' ); ?>
		</label>
		<?php
	}
}
