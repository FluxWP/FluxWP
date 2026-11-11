/* Flux Donate 2.21.0 (since 2.0.4): one contact section on a donation-only checkout, e-mail or phone (at least one). GPL v2 or later. */
(function () {
	'use strict';
	var C = window.FLXBContact;
	if (!C) { return; }
	var reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
	var digits = function (v) { return String(v || '').replace(/\D+/g, ''); };
	var phoneOk = function (v) { return digits(v).length >= 10; };
	var isPh = function (e) {
		e = String(e || '').toLowerCase();
		if (/@telefon\.invalid$/.test(e)) { return true; }
		var tail = '@' + String(C.domain || '').toLowerCase();
		return e.length > tail.length && e.slice(-tail.length) === tail && /^bagisci-[0-9a-z]+$/.test(e.slice(0, -tail.length));
	};
	var placeholder = function (p) { return 'bagisci-' + digits(p) + '@' + C.domain; };
	var store = function () { return window.wp && wp.data && wp.data.select('wc/store/cart') ? wp.data : null; };
	var ui = null, timer = 0;

	function field(id, type, label, val, extra) {
		/* 2.23.0: the shared FluxWP field (icon, label inside, one error look); checked by this script. */
		var w = document.createElement('div');
		w.className = 'flx-fld flx-fld--wide flxb-ct__field';
		w.setAttribute('data-flx-manual', '');
		var box = document.createElement('label'); box.className = 'flx-fld__box'; box.htmlFor = id;
		var ico = window.flxField ? window.flxField.icon('tel' === type ? 'phone' : 'mail') : '';
		if (ico) { box.insertAdjacentHTML('beforeend', ico); }
		var inn = document.createElement('span'); inn.className = 'flx-fld__in';
		var l = document.createElement('span'); l.className = 'flx-fld__label'; l.textContent = label;
		var i = document.createElement('input'); i.className = 'flx-fld__input'; i.id = id; i.type = type; i.value = val || '';
		Object.keys(extra || {}).forEach(function (k) { i.setAttribute(k, extra[k]); });
		inn.appendChild(l); inn.appendChild(i); box.appendChild(inn); w.appendChild(box);
		return { w: w, i: i };
	}

	function push() {
		var d = store(); if (!d || !ui) { return; }
		var e = ui.email.i.value.trim(), p = ui.phone.i.value.trim();
		var cur = d.select('wc/store/cart').getCustomerData().billingAddress || {};
		var next = Object.assign({}, cur, { phone: p, email: reEmail.test(e) ? e : (phoneOk(p) ? placeholder(p) : e) });
		if (next.email !== cur.email || next.phone !== cur.phone) { d.dispatch('wc/store/cart').setBillingAddress(next); }
	}

	function check() {
		var e = ui.email.i.value.trim(), p = ui.phone.i.value.trim(), msg = '';
		if (!e && !p) { msg = C.need; }
		else if (e && !reEmail.test(e)) { msg = C.badEmail; }
		else if (p && !phoneOk(p)) { msg = C.badPhone; }
		else if (!e && !phoneOk(p)) { msg = C.need; }
		ui.err.textContent = msg; ui.err.hidden = !msg;
		ui.box.classList.toggle('has-error', !!msg);
		var badE = !!msg && (msg === C.badEmail || msg === C.need), badP = !!msg && (msg === C.badPhone || msg === C.need);
		ui.email.w.classList.toggle('is-error', badE); ui.phone.w.classList.toggle('is-error', badP);
		ui.email.i.setAttribute('aria-invalid', badE ? 'true' : 'false'); ui.phone.i.setAttribute('aria-invalid', badP ? 'true' : 'false');
		return !msg;
	}

	function mount() {
		var host = document.querySelector('.wc-block-checkout__contact-fields .wc-block-components-checkout-step__content');
		var emailInput = document.getElementById('email');
		if (!host || !emailInput) { return; }
		var wcEmail = emailInput.closest('.wc-block-components-text-input');
		if (wcEmail) { wcEmail.classList.add('flxb-ct-hide'); }
		if (host.querySelector('.flxb-ct')) { return; }
		var d = store(), b = d ? (d.select('wc/store/cart').getCustomerData().billingAddress || {}) : {};
		var box = document.createElement('div'); box.className = 'flxb-ct';
		var email = field('flxb-ct-email', 'email', C.email, isPh(b.email) ? '' : (b.email || emailInput.value), { autocomplete: 'email', inputmode: 'email' });
		var phone = field('flxb-ct-phone', 'tel', C.phone, b.phone || '', { autocomplete: 'tel', inputmode: 'tel', placeholder: C.phoneHolder });
		var help = document.createElement('p'); help.className = 'flxb-ct__help'; help.textContent = C.help;
		var err = document.createElement('p'); err.className = 'flxb-ct__err flx-fld__msg'; err.setAttribute('role', 'alert'); err.hidden = true;
		var row = document.createElement('div'); row.className = 'flxb-ct__row';
		row.appendChild(phone.w); row.appendChild(email.w);
		box.appendChild(row); box.appendChild(help); box.appendChild(err);
		host.insertBefore(box, host.firstChild);
		ui = { box: box, email: email, phone: phone, err: err };
		/* 2.21.0: a signed-in donor's e-mail and phone are read-only (changed under My account). */
		var U = C.user;
		if (U) {
			if (U.email) { email.i.value = U.email; email.i.readOnly = true; email.w.classList.add('is-readonly'); }
			if (U.phone) { phone.i.value = U.phone; phone.i.readOnly = true; phone.w.classList.add('is-readonly'); }
			if (U.email && U.phone) { help.hidden = true; }
			var who = document.createElement('p'); who.className = 'flxb-ct__who';
			var t = document.createElement('span'); t.textContent = U.who; who.appendChild(t);
			[[U.edit, U.editUrl], [U.out, U.outUrl]].forEach(function (l) {
				if (!l[0] || !l[1]) { return; }
				var a = document.createElement('a'); a.href = l[1]; a.textContent = l[0]; who.appendChild(a);
			});
			box.insertBefore(who, err);
			/* The account's name fills an empty name on the order (WooCommerce only reads the billing name). */
			if (d && (U.first || U.last) && !b.first_name && !b.last_name) {
				d.dispatch('wc/store/cart').setBillingAddress(Object.assign({}, b, { first_name: U.first || '', last_name: U.last || '' }));
			}
		}
		/* 2.0.6: what the donor typed on the donation form wins (it was lost on some setups). */
		if (!U) try {
			var carry = JSON.parse(window.sessionStorage.getItem('flxb_contact') || 'null');
			if (carry) {
				if (carry.p) { phone.i.value = carry.p; }
				if (carry.e) { email.i.value = carry.e; }
				window.sessionStorage.removeItem('flxb_contact');
			}
		} catch (x) {}
		[email.i, phone.i].forEach(function (i) {
			i.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(push, 250); if (!err.hidden) { check(); } signup(); });
			i.addEventListener('blur', function () { push(); });
		});
		push();
	}

	/* 2.21.0: "Create an account" needs a real e-mail; hidden (and unticked) while there is none. */
	function signup() {
		var s = document.querySelector('.wc-block-checkout__create-account');
		if (!s || !ui) { return; }
		var ok = reEmail.test(ui.email.i.value.trim());
		var cb = s.querySelector('input[type="checkbox"]');
		if (!ok && cb && cb.checked) { cb.click(); }
		s.classList.toggle('flxb-ct-hide', !ok);
		var h = s.nextElementSibling && s.nextElementSibling.classList.contains('flxb-co-hint') ? s.nextElementSibling : null;
		if (!h && C.signupHint) {
			h = document.createElement('p'); h.className = 'flxb-co-hint'; h.textContent = C.signupHint;
			s.parentNode.insertBefore(h, s.nextSibling);
		}
		var hide = !ok || !(cb && cb.checked);
		if (h && h.hidden !== hide) { h.hidden = hide; }
	}
	document.addEventListener('change', function (ev) { if (ev.target.closest && ev.target.closest('.wc-block-checkout__create-account')) { setTimeout(signup, 0); } }, true);

	document.addEventListener('click', function (ev) {
		var btn = ev.target.closest && ev.target.closest('.wc-block-components-checkout-place-order-button');
		if (!btn || !ui) { return; }
		push();
		if (!check()) { ev.preventDefault(); ev.stopImmediatePropagation(); ui.box.scrollIntoView({ block: 'center', behavior: 'smooth' }); (ui.email.i.value ? ui.phone.i : ui.email.i).focus(); }
	}, true);

	/* Support line (WhatsApp) above the place order bar. */
	function support() {
		if (!C.support || document.querySelector('.flxb-ct-support')) { return; }
		var actions = document.querySelector('.wc-block-checkout__actions');
		if (!actions || !actions.parentNode) { return; }
		var w = document.createElement('div'); w.className = 'flxb-ct-support'; w.innerHTML = C.support;
		actions.parentNode.insertBefore(w, actions);
	}

	/* 2.0.5: header fixed at the top (the theme's sticky one did not hold on every setup). */
	function fixHead() {
		var h = document.querySelector('.flx-checkout-header');
		h = h ? (h.closest('header') || h) : null;
		if (!h) { return; }
		h.classList.add('flxb-fixhead');
		var pad = function () { document.body.style.paddingTop = h.offsetHeight + 'px'; };
		pad(); window.addEventListener('resize', pad);
	}

	/* 2.0.5: slim bar at the bottom: total + button; it clicks WooCommerce's own place order button
	   (which then shows what is missing). Hidden while that button itself is on screen. */
	var bar = null;
	function fmt(t) {
		var u = +t.currency_minor_unit || 0, n = (parseInt(t.total_price, 10) || 0) / Math.pow(10, u);
		var parts = n.toFixed(u).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, t.currency_thousand_separator || '');
		return (t.currency_prefix || '') + parts.join(t.currency_decimal_separator || ',') + (t.currency_suffix || '');
	}
	function paybar() {
		var real = document.querySelector('.wc-block-components-checkout-place-order-button');
		if (!real || document.body.classList.contains('flxb-nostick')) { return; } // 2.23.0: Appearance › fixed bar off
		if (!bar) {
			bar = document.createElement('div'); bar.className = 'flxb-paybar';
			bar.innerHTML = '<div class="flxb-paybar__in"><div class="flxb-paybar__sum"><span></span><strong></strong></div><button type="button" class="flxb-paybar__btn"></button></div>';
			bar.querySelector('span').textContent = C.total; bar.querySelector('button').textContent = C.pay;
			document.body.appendChild(bar);
			document.body.style.paddingBottom = '84px';
			bar.querySelector('button').addEventListener('click', function () {
				var r = document.querySelector('.wc-block-components-checkout-place-order-button');
				if (r) { r.click(); }
			});
			var d = store();
			var paint = function () {
				var r = document.querySelector('.wc-block-components-checkout-place-order-button');
				if (d) { bar.querySelector('strong').textContent = fmt(d.select('wc/store/cart').getCartTotals() || {}); }
				bar.querySelector('button').disabled = !!(r && r.disabled);
			};
			paint();
			if (d) { d.subscribe(paint); }
		}
		if (!real.__flxbSeen && 'IntersectionObserver' in window) {
			real.__flxbSeen = true;
			new IntersectionObserver(function (es) { es.forEach(function (e) { bar.classList.toggle('is-off', e.isIntersecting); }); }).observe(real);
		}
	}

	function start() {
		if (!document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout')) { return; }
		document.body.classList.add('flxb-ct-on');
		mount(); support(); fixHead(); paybar(); signup();
		new MutationObserver(function () { mount(); support(); paybar(); signup(); }).observe(document.body, { childList: true, subtree: true });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
