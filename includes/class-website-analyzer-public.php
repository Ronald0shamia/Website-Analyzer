<?php
/**
 * Public frontend.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the analyzer shortcode.
 */
final class Website_Analyzer_Public {
	/**
	 * Settings service.
	 *
	 * @var Website_Analyzer_Settings
	 */
	private Website_Analyzer_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Website_Analyzer_Settings $settings Settings service.
	 */
	public function __construct( Website_Analyzer_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_shortcode( 'website_analyzer', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register public assets.
	 */
	public function register_assets(): void {
		wp_register_style(
			'website-analyzer-public',
			WEBSITE_ANALYZER_URL . 'public/css/website-analyzer.css',
			array(),
			WEBSITE_ANALYZER_VERSION
		);

		wp_register_script(
			'website-analyzer-public',
			WEBSITE_ANALYZER_URL . 'public/js/website-analyzer.js',
			array(),
			WEBSITE_ANALYZER_VERSION,
			true
		);

		wp_localize_script(
			'website-analyzer-public',
			'WebsiteAnalyzerConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'website_analyzer_frontend' ),
				'i18n'    => array(
					'analyzing' => __( 'Analyse läuft...', 'website-analyzer' ),
					'error'     => __( 'Die URL konnte nicht analysiert werden.', 'website-analyzer' ),
				),
			)
		);
	}

	/**
	 * Render shortcode markup.
	 *
	 * @return string
	 */
	public function render_shortcode(): string {
		wp_enqueue_style( 'website-analyzer-public' );
		wp_enqueue_script( 'website-analyzer-public' );

		$instance_id = 'website-analyzer-' . wp_generate_uuid4();

		ob_start();
		?>
		<div class="website-analyzer" id="<?php echo esc_attr( $instance_id ); ?>" data-website-analyzer>
			<form class="website-analyzer__form" data-role="form" novalidate>
				<label class="website-analyzer__label" for="<?php echo esc_attr( $instance_id ); ?>-url">
					<?php esc_html_e( 'Website-URL', 'website-analyzer' ); ?>
				</label>
				<div class="website-analyzer__input-row">
					<input
						id="<?php echo esc_attr( $instance_id ); ?>-url"
						class="website-analyzer__input"
						type="url"
						inputmode="url"
						placeholder="https://example.com"
						autocomplete="url"
						required
						data-role="url"
					/>
					<button class="website-analyzer__button" type="submit" data-role="submit">
						<?php esc_html_e( 'Analysieren', 'website-analyzer' ); ?>
					</button>
				</div>
				<p class="website-analyzer__message" data-role="message" aria-live="polite"></p>
			</form>

			<div class="website-analyzer__progress" data-role="progress" hidden>
				<div class="website-analyzer__progress-bar" data-role="progress-bar"></div>
			</div>

			<section class="website-analyzer__results" data-role="results" hidden>
				<div class="website-analyzer__summary">
					<div>
						<span><?php esc_html_e( 'Gesamtscore', 'website-analyzer' ); ?></span>
						<strong data-role="score">0</strong>
					</div>
					<div>
						<span><?php esc_html_e( 'Analysierte Domain', 'website-analyzer' ); ?></span>
						<strong data-role="domain">-</strong>
					</div>
					<div>
						<span><?php esc_html_e( 'Zeitpunkt', 'website-analyzer' ); ?></span>
						<strong data-role="timestamp">-</strong>
					</div>
				</div>

				<div class="website-analyzer__grid" data-role="checks"></div>

				<div class="website-analyzer__exports">
					<button type="button" class="website-analyzer__secondary" data-export="pdf"><?php esc_html_e( 'PDF', 'website-analyzer' ); ?></button>
					<button type="button" class="website-analyzer__secondary" data-export="csv"><?php esc_html_e( 'CSV', 'website-analyzer' ); ?></button>
					<button type="button" class="website-analyzer__secondary" data-export="json"><?php esc_html_e( 'JSON', 'website-analyzer' ); ?></button>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
