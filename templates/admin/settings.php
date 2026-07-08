<?php
/**
 * Admin Settings template.
 *
 * @package WebsiteAnalyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wa-admin-wrap">
	<h1><?php esc_html_e( 'Website Analyzer Settings', 'website-analyzer' ); ?></h1>

	<?php settings_errors( 'website_analyzer_settings' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( \WebsiteAnalyzer\Admin\Settings::OPTION_GROUP );
		do_settings_sections( 'website-analyzer-settings' );
		submit_button( __( 'Save Settings', 'website-analyzer' ) );
		?>
	</form>
</div>
