<?php
/**
 * Frontend analyzer template.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="wa-analyzer" class="wa-analyzer" role="main">

	<div class="wa-input-section">
		<h2 class="wa-heading"><?php esc_html_e( 'Website Analyzer', 'website-analyzer' ); ?></h2>
		<p class="wa-subheading"><?php esc_html_e( 'Enter any URL to get a comprehensive analysis of performance, SEO, security, and more.', 'website-analyzer' ); ?></p>

		<div class="wa-input-row">
			<label for="wa-url-input" class="screen-reader-text"><?php esc_html_e( 'Website URL', 'website-analyzer' ); ?></label>
			<input
				type="url"
				id="wa-url-input"
				class="wa-url-input"
				placeholder="https://example.com"
				aria-label="<?php esc_attr_e( 'Website URL to analyze', 'website-analyzer' ); ?>"
				autocomplete="url"
				spellcheck="false"
			/>
			<button
				id="wa-analyze-btn"
				class="wa-btn wa-btn-primary"
				type="button"
				aria-describedby="wa-url-input"
			>
				<span class="wa-btn-text"><?php esc_html_e( 'Analyze', 'website-analyzer' ); ?></span>
				<span class="wa-btn-icon" aria-hidden="true">→</span>
			</button>
		</div>

		<div id="wa-error-msg" class="wa-error-msg" role="alert" aria-live="polite" hidden></div>
	</div>

	<div id="wa-progress" class="wa-progress" hidden aria-live="polite" aria-label="<?php esc_attr_e( 'Analysis progress', 'website-analyzer' ); ?>">
		<div class="wa-progress-header">
			<div class="wa-spinner" aria-hidden="true"></div>
			<span id="wa-progress-label" class="wa-progress-label"><?php esc_html_e( 'Starting analysis…', 'website-analyzer' ); ?></span>
		</div>
		<div class="wa-progress-bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="wa-progress-bar-aria">
			<div id="wa-progress-bar" class="wa-progress-bar"></div>
		</div>
		<ul id="wa-progress-steps" class="wa-progress-steps" aria-label="<?php esc_attr_e( 'Analysis steps', 'website-analyzer' ); ?>"></ul>
	</div>

	<div id="wa-results" class="wa-results" hidden>

		<div class="wa-results-header">
			<div class="wa-score-ring-wrap">
				<svg class="wa-score-ring" viewBox="0 0 120 120" aria-hidden="true">
					<circle class="wa-ring-bg" cx="60" cy="60" r="52"/>
					<circle id="wa-ring-fill" class="wa-ring-fill" cx="60" cy="60" r="52"/>
				</svg>
				<div class="wa-score-center">
					<span id="wa-overall-score" class="wa-score-number">—</span>
					<span class="wa-score-label"><?php esc_html_e( 'Score', 'website-analyzer' ); ?></span>
				</div>
			</div>
			<div class="wa-results-meta">
				<h2 id="wa-result-url" class="wa-result-url"></h2>
				<p id="wa-result-summary" class="wa-result-summary"></p>
				<div class="wa-download-buttons">
					<button id="wa-download-pdf" class="wa-btn wa-btn-outline" type="button">
						⬇ <?php esc_html_e( 'PDF', 'website-analyzer' ); ?>
					</button>
					<button id="wa-download-json" class="wa-btn wa-btn-outline" type="button">
						⬇ <?php esc_html_e( 'JSON', 'website-analyzer' ); ?>
					</button>
					<button id="wa-download-csv" class="wa-btn wa-btn-outline" type="button">
						⬇ <?php esc_html_e( 'CSV', 'website-analyzer' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Category Score Cards -->
		<div class="wa-score-cards" id="wa-score-cards"></div>

		<!-- Tabs -->
		<div class="wa-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Analysis categories', 'website-analyzer' ); ?>">
			<button class="wa-tab active" role="tab" aria-selected="true" aria-controls="tab-performance" data-tab="performance"><?php esc_html_e( 'Performance', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-seo" data-tab="seo"><?php esc_html_e( 'SEO', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-security" data-tab="security"><?php esc_html_e( 'Security', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-mobile" data-tab="mobile"><?php esc_html_e( 'Mobile', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-technical" data-tab="technical"><?php esc_html_e( 'Technical', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-accessibility" data-tab="accessibility"><?php esc_html_e( 'Accessibility', 'website-analyzer' ); ?></button>
			<button class="wa-tab" role="tab" aria-selected="false" aria-controls="tab-ai" data-tab="ai"><?php esc_html_e( 'AI Analysis', 'website-analyzer' ); ?></button>
		</div>

		<div class="wa-tab-panels">
			<div id="tab-performance" class="wa-tab-panel active" role="tabpanel" aria-labelledby="tab-performance"></div>
			<div id="tab-seo"         class="wa-tab-panel"        role="tabpanel" aria-labelledby="tab-seo" hidden></div>
			<div id="tab-security"    class="wa-tab-panel"        role="tabpanel" aria-labelledby="tab-security" hidden></div>
			<div id="tab-mobile"      class="wa-tab-panel"        role="tabpanel" aria-labelledby="tab-mobile" hidden></div>
			<div id="tab-technical"   class="wa-tab-panel"        role="tabpanel" aria-labelledby="tab-technical" hidden></div>
			<div id="tab-accessibility" class="wa-tab-panel"      role="tabpanel" aria-labelledby="tab-accessibility" hidden></div>
			<div id="tab-ai"          class="wa-tab-panel"        role="tabpanel" aria-labelledby="tab-ai" hidden></div>
		</div>

		<!-- Recommendations -->
		<div class="wa-section">
			<h3 class="wa-section-title"><?php esc_html_e( 'Recommendations', 'website-analyzer' ); ?></h3>
			<div id="wa-recommendations" class="wa-recommendations"></div>
		</div>

	</div>
</div>
