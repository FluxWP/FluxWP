/* Flux Donate 2.0.9: send the terms tick of the block checkout to the server (extension data flxb.terms). */
(function () {
	'use strict';
	var D = window.wp && window.wp.data;
	if (!D) {
		return;
	}
	function send(on) {
		var a = D.dispatch('wc/store/checkout');
		if (!a) {
			return;
		}
		if (a.setExtensionData) {
			a.setExtensionData('flxb', { terms: !!on });
		} else if (a.__internalSetExtensionData) {
			a.__internalSetExtensionData('flxb', { terms: !!on });
		}
	}
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (t && t.type === 'checkbox' && t.closest && t.closest('.wc-block-checkout__terms')) {
			send(t.checked);
		}
	});
	/* The tick survives a re-render; send it once more right before the order is placed. */
	document.addEventListener('click', function (e) {
		if (e.target && e.target.closest && e.target.closest('.wc-block-components-checkout-place-order-button')) {
			var c = document.querySelector('.wc-block-checkout__terms input[type=checkbox]');
			if (c) {
				send(c.checked);
			}
		}
	}, true);
})();
