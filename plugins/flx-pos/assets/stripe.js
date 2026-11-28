/* Flux POS · Stripe Payment Element: klasik ödeme, blok ödeme ve sitenin kendi Stripe ödeme sayfası. GPL v3 or later. Kaynak: README. */
(function () {
	'use strict';
	var T = window.FLXPOS_STRIPE_I18N || {};
	var api = window.FLXPOS_STRIPE = window.FLXPOS_STRIPE || {};
	var clients = {};

	function client(pk, locale) {
		if (!window.Stripe || !pk) return null;
		var k = pk + '|' + locale;
		if (!clients[k]) clients[k] = window.Stripe(pk, { locale: locale || 'auto' });
		return clients[k];
	}

	function dark(node) {
		var b = document.body;
		if (b && (b.classList.contains('flx-dark') || b.classList.contains('is-dark-theme'))) return true;
		if (b && b.classList.contains('flx-light')) return false;
		try {
			var c = getComputedStyle(node || b).color.match(/\d+/g);
			if (c && c.length >= 3) return (+c[0] * 299 + +c[1] * 587 + +c[2] * 114) / 1000 > 160; /* açık yazı = koyu zemin */
		} catch (e) { /* yok */ }
		return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
	}

	/* Site yazı tipi ve renkleriyle uyumlu görünüm. */
	function appearance(node, primary) {
		var cs = node ? getComputedStyle(node) : null;
		var v = { borderRadius: '8px', spacingUnit: '4px' };
		if (cs) {
			v.fontFamily = cs.fontFamily;
			v.colorText = cs.color;
		}
		if (primary) v.colorPrimary = primary;
		return { theme: dark(node) ? 'night' : 'stripe', variables: v };
	}

	function fonts() {
		/* Elements iFrame'i sitenin web fontunu göremez; yalnız açık CSS adresi verilebilir. */
		return [];
	}

	/**
	 * Payment Element'i bir kutuya kurar (ertelenmiş intent: tutar/para birimi; PaymentIntent sunucuda açılır).
	 * cfg: pk, amount (en küçük birim), currency, locale, save, primary.
	 * Dönüş: { tokenize(billing) → Promise<pm id>, update(amount), destroy(), ready }
	 */
	api.mount = function (node, cfg) {
		var stripe = client(cfg.pk, cfg.locale);
		if (!stripe) {
			return { error: T.load, tokenize: function () { return Promise.reject(new Error(T.load)); }, update: function () {}, destroy: function () {} };
		}
		var opts = {
			mode: 'payment',
			amount: Math.max(1, cfg.amount | 0),
			currency: cfg.currency,
			paymentMethodCreation: 'manual',
			appearance: appearance(node.parentNode || node, cfg.primary),
			fonts: fonts()
		};
		if (cfg.save) opts.setupFutureUsage = 'off_session';
		var elements = stripe.elements(opts);
		var pe = elements.create('payment', { layout: { type: 'tabs', defaultCollapsed: false }, fields: { billingDetails: { name: 'auto', email: 'auto' } } });
		pe.mount(node);
		var busy = null;
		return {
			tokenize: function (billing) {
				if (busy) return busy;
				busy = elements.submit().then(function (r) {
					if (r && r.error) throw new Error(r.error.message || T.fail);
					return stripe.createPaymentMethod({ elements: elements, params: { billing_details: billing || {} } });
				}).then(function (r) {
					if (r.error) throw new Error(r.error.message || T.fail);
					return r.paymentMethod.id;
				});
				busy.then(function () { busy = null; }, function () { busy = null; });
				return busy;
			},
			update: function (amount) {
				amount = Math.max(1, amount | 0);
				if (amount !== opts.amount) { opts.amount = amount; elements.update({ amount: amount }); }
			},
			destroy: function () { try { pe.unmount(); pe.destroy(); } catch (e) { /* yok */ } }
		};
	};

	/* ---------------- Klasik ödeme (WooCommerce form.checkout ve order-pay) ---------------- */

	var boxes = [];

	function boxFor(form) {
		var r = form.querySelector('input[name="payment_method"]:checked');
		var id = r ? r.value : 'flxpos_card';
		var li = form.querySelector('.payment_method_' + id);
		return li ? li.querySelector('.flxpos-stripe') : null;
	}

	function val(form, name) {
		var e = form.querySelector('[name="' + name + '"]');
		return e && e.value ? e.value.trim() : undefined;
	}

	function billing(form) {
		var name = [val(form, 'billing_first_name'), val(form, 'billing_last_name')].filter(Boolean).join(' ');
		var b = { name: name || undefined, email: val(form, 'billing_email'), phone: val(form, 'billing_phone') };
		var a = { line1: val(form, 'billing_address_1'), line2: val(form, 'billing_address_2'), city: val(form, 'billing_city'), state: val(form, 'billing_state'), postal_code: val(form, 'billing_postcode'), country: val(form, 'billing_country') };
		if (Object.keys(a).some(function (k) { return a[k]; })) b.address = a;
		Object.keys(b).forEach(function (k) { if (b[k] === undefined) delete b[k]; });
		return b;
	}

	function showErr(box, msg) {
		var p = box && box.querySelector('.flxpos-stripe__err');
		if (!p) return;
		p.textContent = msg || '';
		p.hidden = !msg;
		if (msg && p.scrollIntoView) p.scrollIntoView({ block: 'center', behavior: 'smooth' });
	}

	function mountAll() {
		boxes = boxes.filter(function (b) { return document.body.contains(b.node); });
		document.querySelectorAll('.flxpos-stripe').forEach(function (node) {
			if (node.__flxpos) return;
			var cfg;
			try { cfg = JSON.parse(node.getAttribute('data-flxpos-stripe') || '{}'); } catch (e) { cfg = {}; }
			var el = node.querySelector('.flxpos-stripe__el');
			var ctl = api.mount(el, cfg);
			node.__flxpos = ctl;
			if (ctl.error) showErr(node, ctl.error);
			boxes.push({ node: node, ctl: ctl });
		});
	}

	/* Belirteci üret, gizli alana yaz, formu yeniden gönder. */
	function intercept(form, submit) {
		var box = boxFor(form);
		if (!box || !box.__flxpos) return true;
		var hidden = box.querySelector('input[name="flxpos_token"]');
		if (hidden.value) return true;
		showErr(box, '');
		box.__flxpos.tokenize(billing(form)).then(function (pm) {
			hidden.value = pm;
			submit();
		}, function (e) {
			showErr(box, (e && e.message) || T.fail);
		});
		return false;
	}

	function clearTokens() {
		document.querySelectorAll('.flxpos-stripe input[name="flxpos_token"]').forEach(function (i) { i.value = ''; });
	}

	function classic() {
		var $ = window.jQuery;
		mountAll();
		if (!$) return;
		$(document.body).on('updated_checkout payment_method_selected', mountAll);
		$(document.body).on('checkout_error', clearTokens);
		var $checkout = $('form.checkout');
		$checkout.on('checkout_place_order_flxpos_card', function () {
			return intercept(this, function () { $checkout.trigger('submit'); });
		});
		/* Ödeme bekleyen sipariş sayfası (order-pay). */
		var pay = document.getElementById('order_review');
		if (pay && pay.tagName === 'FORM') {
			pay.addEventListener('submit', function (ev) {
				var r = pay.querySelector('input[name="payment_method"]:checked');
				if (!r || r.value !== 'flxpos_card') return;
				if (!intercept(pay, function () { pay.submit(); })) { ev.preventDefault(); ev.stopImmediatePropagation(); }
			}, true);
		}
	}

	/* ---------------- Sitenin kendi Stripe ödeme sayfası (belirteçsiz ödemeler) ---------------- */

	api.page = function (cfg) {
		var form = document.getElementById('flxpos-stripe-page');
		if (!form) return;
		var btn = form.querySelector('button');
		var err = form.querySelector('.err');
		var say = function (m) { err.textContent = m || ''; err.hidden = !m; };
		var stripe = client(cfg.pk, cfg.locale);
		if (!stripe) { say(T.load); return; }
		var elements = stripe.elements({ clientSecret: cfg.clientSecret, appearance: appearance(document.querySelector('main'), '') });
		var pe = elements.create('payment', { layout: { type: 'tabs', defaultCollapsed: false }, defaultValues: { billingDetails: cfg.billing || {} } });
		pe.mount('#el');
		pe.on('ready', function () { btn.disabled = false; });
		pe.on('loaderror', function () { say(T.load); });
		var label = btn.textContent;
		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (btn.disabled) return;
			btn.disabled = true; btn.textContent = T.wait || label; say('');
			stripe.confirmPayment({ elements: elements, confirmParams: { return_url: cfg.returnUrl } }).then(function (r) {
				/* Başarıda Stripe dönüş adresine götürür; buraya yalnız hata gelir. */
				if (r && r.error) { say(r.error.message || T.fail); btn.disabled = false; btn.textContent = label; }
			});
		});
	};

	if (!document.getElementById('flxpos-stripe-page')) {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', classic);
		else classic();
	}
})();
