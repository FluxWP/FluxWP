/* Flux Donate 2.17.0: transaction fee follows the payment method the donor picks at the checkout. */
(function () {
	'use strict';
	var F = window.FLXB_FEE || {};
	var D = window.wp && window.wp.data;
	var last = null, busy = false;

	function push(method) {
		var bc = window.wc && window.wc.blocksCheckout;
		if (!bc || !bc.extensionCartUpdate || !method || method === last) {
			return;
		}
		last = method;
		busy = true;
		Promise.resolve(bc.extensionCartUpdate({ namespace: F.ns || 'flxb-fee', data: { method: method } })).then(function () { busy = false; }, function () { busy = false; last = null; });
	}

	/* Block checkout: watch the active payment method in the store. */
	if (D && D.subscribe) {
		D.subscribe(function () {
			var s = D.select('wc/store/payment');
			var m = s && (s.getActivePaymentMethod ? s.getActivePaymentMethod() : '');
			if (m && !busy) {
				push(m);
			}
		});
	}

	/* Classic checkout: WooCommerce re-calculates the order review on "update_checkout". */
	if (window.jQuery) {
		window.jQuery(document.body).on('change', 'input[name="payment_method"]', function () {
			window.jQuery(document.body).trigger('update_checkout');
		});
	}
})();
