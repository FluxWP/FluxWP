/* Flux Donate · payment plugins without their own checkout block support. GPL v2 or later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var el = window.wp && window.wp.element && window.wp.element.createElement;
	if (!reg || !settings || !el) return;
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	(window.flxbGenericBlocks || []).forEach(function (name) {
		var data = settings.getSetting(name + '_data', null);
		if (!data) return;
		var title = decode(data.title || name);
		var desc = function () {
			return data.description ? el('p', { className: 'flxb-blocks-generic' }, decode(data.description)) : null;
		};
		var label = el('span', { className: 'flxb-blocks-generic__label' }, title,
			data.icon ? el('img', { src: data.icon, alt: '', className: 'flxb-blocks-generic__icon', loading: 'lazy' }) : null);
		var m = {
			name: name,
			label: label,
			ariaLabel: title,
			content: el(desc),
			edit: el(desc),
			/* Availability per cart comes from the server (cart.paymentMethods). */
			canMakePayment: function () { return true; },
			supports: { features: data.supports && data.supports.length ? data.supports : ['products'] }
		};
		if (data.button) m.placeOrderButtonLabel = decode(data.button);
		reg.registerPaymentMethod(m);
	});
})();
