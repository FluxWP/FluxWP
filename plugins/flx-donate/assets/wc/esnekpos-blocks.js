/* Flux Donate 2.21.0 · EsnekPOS card fields on the checkout block, installments, 3D Secure in a modal. GPL v2 or later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var wpEl = window.wp && window.wp.element;
	if (!reg || !settings || !wpEl) return;
	var el = wpEl.createElement, useState = wpEl.useState, useEffect = wpEl.useEffect, useRef = wpEl.useRef;
	var NAME = 'esnekpospos';
	var data = settings.getSetting(NAME + '_data', null);
	if (!data) return;
	var t = data.i18n || {};
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var fmt = function (s) { var a = [].slice.call(arguments, 1), i = 0; return String(s).replace(/%(\d\$)?[sd]/g, function (m, n) { return n ? a[parseInt(n, 10) - 1] : a[i++]; }); };

	/* ---------- Card helpers ---------- */
	var digits = function (v) { return String(v || '').replace(/\D+/g, ''); };
	var brandOf = function (n) {
		if (/^4/.test(n)) return 'visa';
		if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(n)) return 'mastercard';
		if (/^9792/.test(n) || /^65/.test(n)) return 'troy';
		if (/^3[47]/.test(n)) return 'amex';
		return '';
	};
	var luhn = function (n) {
		var s = 0, alt = false;
		for (var i = n.length - 1; i >= 0; i--) { var d = +n[i]; if (alt) { d *= 2; if (d > 9) d -= 9; } s += d; alt = !alt; }
		return n.length >= 13 && s % 10 === 0;
	};
	var groupCard = function (n) {
		if (/^3[47]/.test(n)) return n.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, function (m, a, b, c) { return [a, b, c].filter(Boolean).join(' '); });
		return n.slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
	};
	var groupExp = function (v) { var d = digits(v).slice(0, 4); return d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d; };
	var expOk = function (v) {
		var d = digits(v); if (d.length !== 4) return 'bad';
		var m = +d.slice(0, 2), y = 2000 + +d.slice(2); if (m < 1 || m > 12) return 'bad';
		var now = new Date(); return y * 100 + m < now.getFullYear() * 100 + now.getMonth() + 1 ? 'expired' : '';
	};
	var phoneOk = function (v) { var d = digits(v); return d.length >= 10 && d.length <= 13; };
	var money = function (v, cur) {
		try { return new Intl.NumberFormat(document.documentElement.lang || 'tr-TR', { style: 'currency', currency: cur || 'TRY' }).format(v); } catch (e) { return (+v).toFixed(2) + ' ' + (cur || ''); }
	};
	var BRAND = { visa: 'VISA', mastercard: 'Mastercard', troy: 'troy', amex: 'AMEX' };

	/* ---------- 3D Secure modal ---------- */
	var modal = function (url, order, key) {
		return new Promise(function (resolve) {
			var root = document.createElement('div');
			root.className = 'flxb-3ds';
			root.setAttribute('role', 'dialog');
			root.setAttribute('aria-modal', 'true');
			root.setAttribute('aria-label', t.modalTitle);
			root.innerHTML = '<div class="flxb-3ds__box"><div class="flxb-3ds__head"><span class="flxb-3ds__lock" aria-hidden="true"></span><strong></strong><button type="button" class="flxb-3ds__x"></button></div>' +
				'<div class="flxb-3ds__body"><div class="flxb-3ds__wait"><span class="flxb-3ds__spin" aria-hidden="true"></span><span></span></div><iframe title="" referrerpolicy="no-referrer-when-downgrade"></iframe></div>' +
				'<div class="flxb-3ds__foot"><span></span> <a href="#"></a></div></div>';
			root.querySelector('strong').textContent = t.modalTitle;
			var x = root.querySelector('.flxb-3ds__x'); x.textContent = '✕'; x.setAttribute('aria-label', t.close);
			root.querySelector('.flxb-3ds__wait span:last-child').textContent = t.modalLoading;
			root.querySelector('.flxb-3ds__foot span').textContent = t.modalNote;
			var blank = root.querySelector('.flxb-3ds__foot a'); blank.textContent = t.modalBlank; blank.href = url;
			var frame = root.querySelector('iframe'); frame.title = t.modalTitle;
			var done = false;
			var finish = function (r) {
				if (done) return; done = true;
				window.removeEventListener('message', onMsg); document.removeEventListener('keydown', onKey);
				document.documentElement.classList.remove('flxb-3ds-open');
				root.remove(); resolve(r);
			};
			var onMsg = function (e) {
				if (e.origin !== window.location.origin || !e.data || e.data.flxb3ds !== 1) return;
				finish({ paid: !!e.data.paid, url: e.data.url, message: e.data.message });
			};
			var checkAndClose = function () {
				root.querySelector('.flxb-3ds__wait span:last-child').textContent = t.checking;
				root.classList.add('is-checking');
				window.fetch(data.rest + 'status', {
					method: 'POST', credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					body: JSON.stringify({ order: order, key: key })
				}).then(function (r) { return r.json(); }).then(function (j) {
					finish(j && j.paid ? { paid: true, url: j.url } : { paid: false, cancelled: true, message: t.cancelled });
				}).catch(function () { finish({ paid: false, cancelled: true, message: t.cancelled }); });
			};
			var onKey = function (e) { if (e.key === 'Escape') ask(); };
			var ask = function () { if (window.confirm(t.closeAsk)) checkAndClose(); };
			x.addEventListener('click', ask);
			frame.addEventListener('load', function () { root.classList.add('is-loaded'); });
			window.addEventListener('message', onMsg);
			document.addEventListener('keydown', onKey);
			document.documentElement.classList.add('flxb-3ds-open');
			document.body.appendChild(root);
			frame.src = url;
			x.focus();
		});
	};

	/* ---------- Card form ---------- */
	var Field = function (p) {
		return el('div', { className: 'flxb-cc__f' + (p.error ? ' has-error' : '') + (p.wide ? ' is-wide' : '') },
			el('label', { className: 'flxb-cc__box' },
				el('span', { className: 'flxb-cc__label' }, p.label),
				el('input', {
					type: p.type || 'text', inputMode: p.inputMode, autoComplete: p.autoComplete, value: p.value,
					placeholder: p.placeholder || '', maxLength: p.maxLength, 'aria-invalid': p.error ? 'true' : 'false',
					onChange: function (e) { p.onChange(e.target.value); }, onBlur: p.onBlur, readOnly: !!p.readOnly
				}),
				p.after || null
			),
			p.error ? el('span', { className: 'flxb-cc__err', role: 'alert' }, p.error) : null
		);
	};

	var Content = function (props) {
		var reg = props.eventRegistration, emit = props.emitResponse;
		var billing = props.billing || {};
		var addr = billing.billingAddress || {};
		var total = billing.cartTotal ? billing.cartTotal.value / Math.pow(10, (billing.currency && billing.currency.minorUnit) || 2) : 0;
		var cur = (billing.currency && billing.currency.code) || 'TRY';
		var needPhone = !phoneOk(addr.phone || '');
		var defName = ((addr.first_name || '') + ' ' + (addr.last_name || '')).trim().toLocaleUpperCase('tr-TR');

		var s = {
			num: useState(''), exp: useState(''), cvv: useState(''), name: useState(null), phone: useState(''),
			inst: useState(1), opts: useState(null), err: useState({}), touched: useState({})
		};
		var v = function (k) { return k === 'name' && s.name[0] === null ? defName : s[k][0]; }, set = function (k, x) { s[k][1](x); };
		var latest = useRef({}); latest.current = { num: v('num'), exp: v('exp'), cvv: v('cvv'), name: v('name'), phone: v('phone'), inst: v('inst'), needPhone: needPhone };
		var num = digits(v('num')), brand = brandOf(num);

		var check = function (c) {
			var e = {};
			var n = digits(c.num);
			if (!luhn(n) || n.length > 19) e.num = t.badCard;
			var x = expOk(c.exp); if (x) e.exp = x === 'expired' ? t.expired : t.badExp;
			if (!/^\d{3,4}$/.test(digits(c.cvv))) e.cvv = t.badCvv;
			if (String(c.name || '').trim().length < 3) e.name = t.badName;
			if (c.needPhone && !phoneOk(c.phone)) e.phone = t.badPhone;
			return e;
		};

		/* Installments once the BIN is known. */
		var bin = num.slice(0, 8), bin6 = num.slice(0, 6);
		/* The pay button in the summary column shows the installment total (checkout.js reads it). */
		var pickN = +v('inst') || 1, pickOpts = v('opts');
		useEffect(function () {
			var o = pickN > 1 && pickOpts ? pickOpts.filter(function (x) { return +x.n === pickN; })[0] : null;
			var val = o ? money(o.total, cur) : '';
			if ((document.body.getAttribute('data-flxb-pay') || '') !== val) {
				if (val) { document.body.setAttribute('data-flxb-pay', val); } else { document.body.removeAttribute('data-flxb-pay'); }
				window.dispatchEvent(new Event('flxb:paytotal'));
			}
		}, [pickN, pickOpts, cur]);
		useEffect(function () {
			return function () { document.body.removeAttribute('data-flxb-pay'); window.dispatchEvent(new Event('flxb:paytotal')); };
		}, []);
		useEffect(function () {
			if (!data.installments || bin6.length < 6 || !total) { set('opts', null); set('inst', 1); return; }
			var stop = false;
			var q = data.rest + 'installments?bin=' + encodeURIComponent(bin.length >= 8 ? bin : bin6) + '&amount=' + encodeURIComponent(total);
			window.fetch(q, { credentials: 'same-origin', headers: { 'X-WP-Nonce': data.nonce } })
				.then(function (r) { return r.ok ? r.json() : { options: [] }; })
				.then(function (j) { if (!stop) { set('opts', j && j.options ? j.options : []); set('inst', 1); } })
				.catch(function () { if (!stop) set('opts', []); });
			return function () { stop = true; };
		}, [bin6, total]);

		useEffect(function () {
			return reg.onPaymentSetup(function () {
				var c = latest.current, e = check(c);
				if (Object.keys(e).length) {
					set('err', e); set('touched', { num: 1, exp: 1, cvv: 1, name: 1, phone: 1 });
					return { type: emit.responseTypes.ERROR, message: e[Object.keys(e)[0]], messageContext: emit.noticeContexts.PAYMENTS };
				}
				return {
					type: emit.responseTypes.SUCCESS,
					meta: { paymentMethodData: {
						flxb_cc: '1', flxb_cc_number: digits(c.num), flxb_cc_exp: digits(c.exp), flxb_cc_cvv: digits(c.cvv),
						flxb_cc_name: String(c.name).trim(), flxb_inst: String(c.inst || 1), flxb_phone: c.needPhone ? c.phone : ''
					} }
				};
			});
		}, [reg.onPaymentSetup]);

		useEffect(function () {
			return reg.onCheckoutSuccess(function (r) {
				var pr = (r && r.processingResponse) || {}, d = pr.paymentDetails || {};
				if (!d.flxb_3ds) return true;
				return modal(d.flxb_3ds, d.flxb_order, d.flxb_key).then(function (res) {
					if (res.paid) {
						if (res.url && res.url !== pr.redirectUrl) window.location.assign(res.url);
						return true;
					}
					set('cvv', '');
					return { type: emit.responseTypes.ERROR, message: res.message || t.cancelled, messageContext: emit.noticeContexts.PAYMENTS, retry: true };
				});
			});
		}, [reg.onCheckoutSuccess]);

		var blur = function (k) { return function () { var tt = Object.assign({}, v('touched')); tt[k] = 1; set('touched', tt); set('err', check(latest.current)); }; };
		var errOf = function (k) { return v('touched')[k] ? v('err')[k] : ''; };
		var change = function (k, f) { return function (x) { set(k, f ? f(x) : x); if (v('touched')[k]) { var c = Object.assign({}, latest.current); c[k] = f ? f(x) : x; set('err', check(c)); } }; };

		var opts = v('opts');
		var instBox = null;
		if (data.installments) {
			instBox = el('div', { className: 'flxb-cc__inst' },
				el('div', { className: 'flxb-cc__inst-h' }, t.inst),
				opts === null ? el('p', { className: 'flxb-cc__muted' }, t.instWait)
					: el('div', { className: 'flxb-cc__inst-list', role: 'radiogroup', 'aria-label': t.inst },
						[{ n: 1, total: total, per: total }].concat(opts).map(function (o) {
							var on = +v('inst') === +o.n;
							return el('label', { key: o.n, className: 'flxb-cc__inst-o' + (on ? ' is-on' : '') },
								el('input', { type: 'radio', name: 'flxb-inst', checked: on, onChange: function () { set('inst', o.n); } }),
								el('span', { className: 'flxb-cc__inst-n' }, o.n === 1 ? t.single : fmt(t.nInst, o.n)),
								el('span', { className: 'flxb-cc__inst-a' }, o.n === 1 ? money(total, cur) : fmt(t.perInst, money(o.per, cur), money(o.total, cur)))
							);
						})
					),
				opts && !opts.length ? el('p', { className: 'flxb-cc__muted' }, t.instNone) : null
			);
		}

		return el('div', { className: 'flxb-cc' },
			el(Field, {
				label: t.card, value: v('num'), inputMode: 'numeric', autoComplete: 'cc-number', placeholder: '0000 0000 0000 0000', maxLength: 23, wide: true,
				onChange: change('num', function (x) { return groupCard(digits(x)); }), onBlur: blur('num'), error: errOf('num'),
				after: el('span', { className: 'flxb-cc__brand flxb-cc__brand--' + (brand || 'none'), 'aria-hidden': 'true' }, BRAND[brand] || '')
			}),
			el('div', { className: 'flxb-cc__row' },
				el(Field, { label: t.exp, value: v('exp'), inputMode: 'numeric', autoComplete: 'cc-exp', placeholder: 'AA / YY', maxLength: 7, onChange: change('exp', groupExp), onBlur: blur('exp'), error: errOf('exp') }),
				el(Field, { label: t.cvv, value: v('cvv'), inputMode: 'numeric', autoComplete: 'cc-csc', placeholder: '•••', maxLength: 4, type: 'password', onChange: change('cvv', function (x) { return digits(x).slice(0, 4); }), onBlur: blur('cvv'), error: errOf('cvv') })
			),
			el(Field, { label: t.name, value: v('name'), autoComplete: 'cc-name', wide: true, onChange: change('name', function (x) { return x.toLocaleUpperCase('tr-TR'); }), onBlur: blur('name'), error: errOf('name') }),
			needPhone ? el(Field, { label: t.phone, value: v('phone'), type: 'tel', inputMode: 'tel', autoComplete: 'tel', placeholder: '0532 123 45 67', wide: true, onChange: change('phone'), onBlur: blur('phone'), error: errOf('phone') || '' }) : null,
			needPhone && !errOf('phone') ? el('p', { className: 'flxb-cc__muted flxb-cc__hint' }, t.phoneHelp) : null,
			instBox,
			el('div', { className: 'flxb-cc__3ds' },
				el('span', { className: 'flxb-cc__3ds-ico', 'aria-hidden': 'true' }),
				el('span', { className: 'flxb-cc__3ds-t' }, el('strong', null, '3D Secure'), ' ', t.sec)
			),
			el('div', { className: 'flxb-cc__foot' },
				el('p', { className: 'flxb-cc__muted flxb-cc__safe' }, t.secShort),
				el('span', { className: 'flxb-cc-label__brands', 'aria-hidden': 'true' },
					el('i', { className: 'flxb-cc__brand flxb-cc__brand--visa' }, 'VISA'),
					el('i', { className: 'flxb-cc__brand flxb-cc__brand--mastercard' }, ''),
					el('i', { className: 'flxb-cc__brand flxb-cc__brand--troy' }, 'troy')))
		);
	};

	var title = decode(data.title || 'EsnekPOS');
	var Label = function () {
		return el('span', { className: 'flxb-cc-label' }, title);
	};
	reg.registerPaymentMethod({
		name: NAME,
		label: el(Label),
		ariaLabel: title,
		content: el(Content),
		edit: el('p', null, title),
		canMakePayment: function () { return true; },
		supports: { features: data.supports || ['products'] }
	});
})();
