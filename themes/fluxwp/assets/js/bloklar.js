/* Tema blokları (editör önizlemesi). Ön yüz çıktısı PHP'de: inc/dil.php, inc/woocommerce.php */
(function (blocks, el, i18n) {
	var h = el.createElement, __ = i18n.__;
	var ic = function (d) {
		return h('svg', { viewBox: '0 0 24 24', width: 22, height: 22, fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true }, h('path', { d: d }));
	};
	blocks.registerBlockType('fluxwp/dil-secici', {
		apiVersion: 3,
		title: __("Language Switcher", 'fluxwp'),
		description: __("Shows the languages added with TranslatePress, Polylang or WPML. Hidden when there is only one language.", 'fluxwp'),
		category: 'theme',
		icon: 'translation',
		supports: { html: false },
		edit: function () {
			return h('nav', { className: 'flx-lang' }, h('a', { className: 'is-current' }, 'TR'), h('a', null, 'EN'));
		},
		save: function () { return null; }
	});
	blocks.registerBlockType('fluxwp/hesap-sepet', {
		apiVersion: 3,
		title: __("Account and Cart", 'fluxwp'),
		description: __("WooCommerce account icon and mini cart. Hidden when WooCommerce is inactive.", 'fluxwp'),
		category: 'theme',
		icon: 'cart',
		supports: { html: false },
		edit: function () {
			return h('div', { className: 'flx-hs' },
				h('span', { className: 'flx-hs__btn' }, ic('M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.6-7 8-7s8 3 8 7')),
				h('span', { className: 'flx-hs__btn' }, ic('M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2')));
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.i18n);
