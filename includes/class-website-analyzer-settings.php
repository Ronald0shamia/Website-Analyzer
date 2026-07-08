<?php
/**
 * Admin settings.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings page and option.
 */
final class Website_Analyzer_Settings {
	private const OPTION_NAME = 'website_analyzer_settings';

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = '' ): mixed {
		$options = get_option( self::OPTION_NAME, array() );

		return is_array( $options ) && array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	/**
	 * Register admin pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Website Analyzer', 'website-analyzer' ),
			__( 'Website Analyzer', 'website-analyzer' ),
			'manage_options',
			'website-analyzer',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			80
		);

		add_submenu_page(
			'website-analyzer',
			__( 'Einstellungen', 'website-analyzer' ),
			__( 'Einstellungen', 'website-analyzer' ),
			'manage_options',
			'website-analyzer',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register options and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'website_analyzer_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'gemini_api_key' => '',
				),
			)
		);

		add_settings_section(
			'website_analyzer_api',
			__( 'API-Konfiguration', 'website-analyzer' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Der API-Key wird serverseitig gespeichert und nicht an das Frontend ausgegeben.', 'website-analyzer' ) . '</p>';
			},
			'website_analyzer_settings'
		);

		add_settings_field(
			'gemini_api_key',
			__( 'Google Gemini API-Key', 'website-analyzer' ),
			array( $this, 'render_gemini_field' ),
			'website_analyzer_settings',
			'website_analyzer_api'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, string>
	 */
	public function sanitize_settings( array $input ): array {
		$api_key = isset( $input['gemini_api_key'] ) && is_scalar( $input['gemini_api_key'] )
			? sanitize_text_field( wp_unslash( (string) $input['gemini_api_key'] ) )
			: '';

		return array(
			'gemini_api_key' => $api_key,
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'website-analyzer' ) ) {
			return;
		}

		wp_enqueue_style(
			'website-analyzer-admin',
			WEBSITE_ANALYZER_URL . 'admin/css/admin.css',
			array(),
			WEBSITE_ANALYZER_VERSION
		);
	}

	/**
	 * Render API key field.
	 */
	public function render_gemini_field(): void {
		$value = (string) $this->get( 'gemini_api_key', '' );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gemini_api_key]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<p class="description">
			<?php esc_html_e( 'Optional für zukünftige KI-Zusammenfassungen. Die browserbasierte Analyse funktioniert ohne API-Key.', 'website-analyzer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'website-analyzer' ) );
		}
		?>
		<div class="wrap website-analyzer-admin">
			<h1><?php esc_html_e( 'Website Analyzer', 'website-analyzer' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'website_analyzer_settings' );
				do_settings_sections( 'website_analyzer_settings' );
				submit_button();
				?>
			</form>
			<div class="website-analyzer-shortcode">
				<strong><?php esc_html_e( 'Shortcode', 'website-analyzer' ); ?></strong>
				<code>[website_analyzer]</code>
			</div>
		</div>
		<?php
	}
}
