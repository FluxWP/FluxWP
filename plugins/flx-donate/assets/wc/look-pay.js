/* Flux Donate 2.16.0 · checkout payment layouts: the number of methods is the column count of the cards / tabs grid. GPL v2 or later. */
(function () {
	'use strict';
	var set = function () {
		document.querySelectorAll('.wc-block-checkout__payment-method .wc-block-components-radio-control').forEach(function (r) {
			var n = String(r.querySelectorAll(':scope > .wc-block-components-radio-control-accordion-option').length || 1);
			if (r.style.getPropertyValue('--flxb-pay-n') !== n) r.style.setProperty('--flxb-pay-n', n);
		});
	};
	set();
	if (window.MutationObserver) new MutationObserver(set).observe(document.body, { childList: true, subtree: true });
}());
