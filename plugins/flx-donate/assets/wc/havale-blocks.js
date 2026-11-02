/* Flux Donate · bank transfer method in the checkout block (ported from Flux WooCommerce Bağış). GPL v2 or later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var el = window.wp && window.wp.element && window.wp.element.createElement;
	if (!reg || !settings || !el) return;
	var data = settings.getSetting('flxb_havale_data', {});
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var title = decode(data.title || 'Bank transfer');

	function Content() {
		return el('div', { className: 'flxwb-blocks-havale' },
			el('p', null, decode(data.description || '')),
			data.iban ? el('p', { className: 'flxwb-blocks-havale__iban' },
				el('span', null, decode(data.holder || '')), el('br'), el('strong', null, data.iban)) : null
		);
	}

	reg.registerPaymentMethod({
		name: 'flxb_havale',
		label: el('span', { className: 'flxwb-blocks-havale__label' }, title),
		ariaLabel: title,
		content: el(Content),
		edit: el(Content),
		canMakePayment: function () { return true; },
		supports: { features: data.supports || ['products'] }
	});
})();
