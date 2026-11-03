/* Flux Commerce · Havale/EFT, ödeme bloğunda. GPL-2.0-or-later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var el = window.wp && window.wp.element && window.wp.element.createElement;
	if (!reg || !settings || !el) return;
	var data = settings.getSetting('flxcm_bank_data', {});
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var title = decode(data.title || 'Havale/EFT');

	function Content() {
		return el('div', { className: 'flxcm-blocks-bank' },
			el('p', null, decode(data.description || '')),
			data.iban ? el('p', { className: 'flxcm-blocks-bank__iban' },
				el('span', null, decode(data.holder || '')), el('br'), el('strong', null, data.iban)) : null
		);
	}

	reg.registerPaymentMethod({
		name: 'flxcm_bank',
		label: el('span', { className: 'flxcm-blocks-bank__label' }, title),
		ariaLabel: title,
		content: el(Content),
		edit: el(Content),
		canMakePayment: function () { return true; },
		supports: { features: data.supports || ['products'] }
	});
})();
