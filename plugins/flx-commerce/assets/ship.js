/* Flux Commerce · ücretsiz kargo çubuğu, blok sepet/ödemede sepet değişince güncellenir. GPL-2.0-or-later. */
(function () {
	'use strict';
	var el = document.querySelector('[data-flxcm-ship]');
	var data = window.wp && window.wp.data;
	if (!el || !data || !data.subscribe) return;
	var cfg; try { cfg = JSON.parse(el.getAttribute('data-flxcm-ship')); } catch (e) { return; }
	var text = el.querySelector('[data-flxcm-ship-text]'), bar = el.querySelector('.flxcm-ship__bar span'), last = '';
	function fmt(n, t) {
		var d = t.currency_minor_unit || 0, s = n.toFixed(d).split('.');
		s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, t.currency_thousand_separator || '');
		var v = s.join(t.currency_decimal_separator || '.');
		return (t.currency_prefix || '') + v + (t.currency_suffix || '');
	}
	data.subscribe(function () {
		var store = data.select('wc/store/cart');
		if (!store || !store.getCartTotals) return;
		var t = store.getCartTotals(), div = Math.pow(10, t.currency_minor_unit || 0);
		if (!t || t.total_items === undefined) return;
		var sub = (parseInt(t.total_items, 10) + (cfg.incl ? parseInt(t.total_items_tax || 0, 10) : 0) - parseInt(t.total_discount || 0, 10) - (cfg.incl ? parseInt(t.total_discount_tax || 0, 10) : 0)) / div;
		var key = sub + '';
		if (key === last) return;
		last = key;
		var left = Math.max(0, cfg.min - sub);
		el.classList.toggle('is-done', left <= 0);
		// Plain-text template, amount in <strong>; nothing is parsed as HTML.
		while (text.firstChild) text.removeChild(text.firstChild);
		var strong = document.createElement('strong');
		if (left > 0) {
			var parts = String(cfg.left || '').split('%s');
			strong.textContent = fmt(left, t);
			text.appendChild(document.createTextNode(parts[0] || ''));
			text.appendChild(strong);
			text.appendChild(document.createTextNode(parts.slice(1).join('%s')));
		} else {
			strong.textContent = String(cfg.done || '');
			text.appendChild(strong);
		}
		if (bar) bar.style.width = Math.min(100, Math.max(0, sub / cfg.min * 100)).toFixed(1) + '%';
	});
})();
