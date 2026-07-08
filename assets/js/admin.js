/**
 * Website Analyzer — Admin JavaScript
 *
 * @package WebsiteAnalyzer
 */

/* global waAdmin */

'use strict';

(function ($) {
	$(document).ready(function () {

		// Clear statistics button.
		$('#wa-clear-stats').on('click', function () {
			if (!confirm(waAdmin.i18n.confirmDelete)) return;

			$.post(waAdmin.ajaxUrl, {
				action: 'wa_clear_statistics',
				nonce:  waAdmin.nonce,
			}, function (response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data?.message || 'Error clearing statistics.');
				}
			});
		});

	});
}(jQuery));
