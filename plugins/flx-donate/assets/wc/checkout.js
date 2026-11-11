/* Flux Donate 2.21.0 · compact checkout: terms box and "Pay {total}" in the summary column on wide screens.
 * Both drive WooCommerce's own checkbox and place order button (its validation, payment methods and 3D Secure stay). GPL v2 or later. */
(function () {
	'use strict';
	var C = window.FLXBCheckout || {};
	var lock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
	var box = null, err = null, btn = null, mirror = null;
	var q = function (s) { return document.querySelector(s); };
	var store = function () { return window.wp && wp.data && wp.data.select('wc/store/cart') ? wp.data : null; };

	function fmt(t) {
		var u = +t.currency_minor_unit || 0, n = (parseInt(t.total_price, 10) || 0) / Math.pow(10, u);
		var parts = n.toFixed(u).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, t.currency_thousand_separator || '');
		return (t.currency_prefix || '') + parts.join(t.currency_decimal_separator || ',') + (t.currency_suffix || '');
	}
	function realTerms() { return q('.wc-block-checkout__terms input[type="checkbox"]'); }
	function realBtn() { return q('.wc-block-components-checkout-place-order-button'); }

	function build() {
		box = document.createElement('div');
		box.className = 'flxb-co-pay';
		box.innerHTML = '<button type="button" class="flxb-co-btn"></button><p class="flxb-co-sec">' + lock + '<span></span></p>';
		btn = box.querySelector('.flxb-co-btn');
		box.querySelector('.flxb-co-sec span').textContent = C.secure || '';
		btn.addEventListener('click', function () {
			var t = realTerms();
			if (t && mirror && !mirror.checked) {
				mirror.parentNode.classList.add('has-error');
				if (err) { err.hidden = false; }
				mirror.focus();
				return;
			}
			var r = realBtn();
			if (r) { r.click(); }
		});
	}

	function terms() {
		var t = realTerms(), host = q('.wc-block-checkout__terms');
		var lab = box.querySelector('.flxb-co-terms');
		if (!t || !host) { if (lab) { lab.remove(); } if (err) { err.remove(); err = null; } mirror = null; return; }
		if (!lab) {
			var text = host.querySelector('.wc-block-components-checkbox__label') || host.querySelector('label');
			lab = document.createElement('label');
			lab.className = 'flxb-co-terms';
			lab.innerHTML = '<input type="checkbox"><span></span>';
			lab.querySelector('span').innerHTML = text ? text.innerHTML : '';
			mirror = lab.querySelector('input');
			mirror.addEventListener('change', function () {
				var rt = realTerms();
				if (rt && rt.checked !== mirror.checked) { rt.click(); }
				lab.classList.remove('has-error');
				if (err) { err.hidden = true; }
			});
			err = document.createElement('p');
			err.className = 'flxb-co-terms__err'; err.setAttribute('role', 'alert'); err.hidden = true;
			err.textContent = C.terms || '';
			box.insertBefore(err, box.firstChild);
			box.insertBefore(lab, box.firstChild);
		}
		if (mirror.checked !== t.checked) { mirror.checked = t.checked; }
	}

	function paint() {
		if (!box) { return; }
		var d = store(), r = realBtn();
		var total = document.body.getAttribute('data-flxb-pay') || (d ? fmt(d.select('wc/store/cart').getCartTotals() || {}) : '');
		var label = total ? String(C.pay || '%s').replace('%s', total) : (C.payPlain || '');
		var html = lock + '<span></span>';
		if (btn.getAttribute('data-l') !== label) { btn.innerHTML = html; btn.querySelector('span').textContent = label; btn.setAttribute('data-l', label); }
		var busy = !!(r && (r.disabled || r.classList.contains('wc-block-components-button--loading')));
		if (btn.disabled !== busy) { btn.disabled = busy; }
	}

	function place() {
		var wide = !!q('.wc-block-checkout.is-large');
		if (document.body.classList.contains('flxb-co-wide') !== wide) { document.body.classList.toggle('flxb-co-wide', wide); }
		var summary = q('.wc-block-checkout__sidebar .wp-block-woocommerce-checkout-order-summary-block');
		if (!summary) { return; }
		if (!box) { build(); }
		if (summary.nextElementSibling !== box) { summary.parentNode.insertBefore(box, summary.nextSibling); }
		terms();
		paint();
		notice();
	}

	/* An error from the payment: WooCommerce shows it at the top, under the fixed header. Bring it into view
	   below the header and repeat it over the pay button, where the donor is looking. */
	var seen = null;
	function notice() {
		var n = q('.wc-block-checkout .wc-block-components-notice-banner.is-error');
		var txt = n ? (n.querySelector('.wc-block-components-notice-banner__content') || n).textContent.trim() : '';
		var m = box && box.querySelector('.flxb-co-msg');
		if (!txt) { if (m) { m.remove(); } seen = null; return; }
		if (box && !m) { m = document.createElement('p'); m.className = 'flxb-co-msg'; m.setAttribute('role', 'alert'); box.insertBefore(m, btn); }
		if (m && m.textContent !== txt) { m.textContent = txt; }
		if (n !== seen) {
			seen = n;
			var h = parseInt(getComputedStyle(document.body).getPropertyValue('--flxb-head'), 10) || 0;
			window.scrollTo({ top: Math.max(0, n.getBoundingClientRect().top + window.pageYOffset - h - 16), behavior: 'smooth' });
		}
	}

	function head() {
		var h = q('.flxb-fixhead') || q('header');
		if (h) { document.body.style.setProperty('--flxb-head', h.offsetHeight + 'px'); }
	}

	function start() {
		if (!q('.wp-block-woocommerce-checkout, .wc-block-checkout')) { return; }
		var busy = false;
		var run = function () { if (busy) { return; } busy = true; try { place(); } finally { busy = false; } };
		run(); head();
		new MutationObserver(run).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'disabled'] });
		var d = store(); if (d) { d.subscribe(paint); }
		window.addEventListener('flxb:paytotal', paint);
		document.addEventListener('change', function (e) { if (e.target === realTerms()) { terms(); } }, true);
		window.addEventListener('resize', function () { run(); head(); });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
