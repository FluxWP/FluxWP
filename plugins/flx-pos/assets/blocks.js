/* Flux POS · blok ödeme: kart formu ve cüzdan yöntemleri. GPL v2 or later. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var wpEl = window.wp && window.wp.element;
	if (!reg || !settings || !wpEl) return;
	var el = wpEl.createElement, useState = wpEl.useState, useEffect = wpEl.useEffect, useRef = wpEl.useRef;
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };
	var C = window.FLXPOS || { i18n: {} };
	var T = C.i18n || {};

	function brand(pan) {
		pan = String(pan || '').replace(/\D/g, '');
		if (/^4/.test(pan)) return 'visa';
		if (/^(5[1-5]|2[2-7])/.test(pan)) return 'mastercard';
		if (/^3[47]/.test(pan)) return 'amex';
		if (/^(9792|65|36)/.test(pan)) return 'troy';
		return '';
	}
	function luhn(pan) {
		pan = String(pan).replace(/\D/g, '');
		if (pan.length < 12 || pan.length > 19) return false;
		var s = 0, alt = false;
		for (var i = pan.length - 1; i >= 0; i--) {
			var n = +pan[i];
			if (alt) { n *= 2; if (n > 9) n -= 9; }
			s += n; alt = !alt;
		}
		return s % 10 === 0;
	}
	function fmtPan(v) { return String(v || '').replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 '); }
	function fmtExp(v) { var d = String(v || '').replace(/\D/g, '').slice(0, 4); return d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d; }

	function field(id, label, props) {
		return el('div', { className: 'flxpos-b-field' },
			el('label', { htmlFor: id }, label),
			el('input', Object.assign({ id: id, className: 'flxpos-b-input' }, props)));
	}

	/* Açılır pencere gösterimi: sipariş oluştuktan sonra yönlendirme yerine çerçeve (checkout.js). */
	function useFrame(props) {
		var on = props.eventRegistration && props.eventRegistration.onCheckoutSuccess;
		useEffect(function () {
			if (!on) return undefined;
			return on(function (d) {
				var pd = (d && d.processingResponse && d.processingResponse.paymentDetails) || {};
				var api = window.FLXPOS_API;
				if (!pd.flxpos_frame || !api || !api.frame) return true;
				api.frame(pd.flxpos_frame, pd.flxpos_cancel);
				return new Promise(function () {}); /* sayfa çerçeveden çıkınca değişir */
			});
		}, [on]);
	}

	function CardForm(data) {
		return function (props) {
			useFrame(props);
			var s = useState({ name: '', pan: '', exp: '', cvc: '', inst: '1' });
			var v = s[0], set = s[1];
			var o = useState([]); var opts = o[0], setOpts = o[1];
			var ref = useRef(v); ref.current = v;
			var total = props.billing && props.billing.cartTotal ? props.billing.cartTotal.value / Math.pow(10, props.billing.currency.minorUnit) : 0;
			var bin = v.pan.replace(/\D/g, '').slice(0, 8);
			var binKey = bin.length >= 6 ? bin : '';

			useEffect(function () {
				if (!data.inst || !binKey) { setOpts([]); return; }
				var url = C.rest + 'installments?account=' + encodeURIComponent(data.account) + '&amount=' + encodeURIComponent(total) + '&bin=' + encodeURIComponent(binKey);
				var live = true;
				fetch(url, { headers: { 'X-WP-Nonce': C.nonce }, credentials: 'same-origin' })
					.then(function (r) { return r.ok ? r.json() : { options: [] }; })
					.then(function (j) { if (live) setOpts(((j && j.options) || []).filter(function (x) { return x.n > 1; })); })
					.catch(function () { if (live) setOpts([]); });
				return function () { live = false; };
			}, [binKey, total]);

			useEffect(function () {
				return props.eventRegistration.onPaymentSetup(function () {
					var c = ref.current;
					var err = '';
					var m = c.exp.replace(/\s/g, '').match(/^(\d{1,2})\/?(\d{2})$/);
					if (!luhn(c.pan)) err = T.badCard;
					else if (!m || +m[1] < 1 || +m[1] > 12) err = T.badExp;
					else if (!/^\d{3,4}$/.test(c.cvc)) err = T.badCvc;
					else if (!c.name.trim()) err = T.badName;
					if (err) return { type: props.emitResponse.responseTypes.ERROR, message: err };
					return {
						type: props.emitResponse.responseTypes.SUCCESS,
						meta: { paymentMethodData: { flxpos_name: c.name, flxpos_pan: c.pan.replace(/\D/g, ''), flxpos_exp: c.exp.replace(/\s/g, ''), flxpos_cvc: c.cvc, flxpos_inst: c.inst } }
					};
				});
			}, [props.eventRegistration.onPaymentSetup]);

			var up = function (k, f) { return function (e) { var n = Object.assign({}, ref.current); n[k] = f ? f(e.target.value) : e.target.value; set(n); }; };
			var b = C.brands ? brand(v.pan) : '';
			return el('div', { className: 'flxpos-card flxpos-b' },
				data.description ? el('p', null, decode(data.description)) : null,
				field('flxpos-b-name', T.name, { value: v.name, onChange: up('name'), autoComplete: 'cc-name', spellCheck: false }),
				el('div', { className: 'flxpos-b-field flxpos-pan' },
					el('label', { htmlFor: 'flxpos-b-pan' }, T.number),
					el('input', { id: 'flxpos-b-pan', className: 'flxpos-b-input', value: v.pan, onChange: up('pan', fmtPan), inputMode: 'numeric', autoComplete: 'cc-number', placeholder: '•••• •••• •••• ••••' }),
					el('span', { className: 'flxpos-brand', 'data-brand': b, 'aria-hidden': 'true' })),
				el('div', { className: 'flxpos-b-row' },
					field('flxpos-b-exp', T.expiry, { value: v.exp, onChange: up('exp', fmtExp), inputMode: 'numeric', autoComplete: 'cc-exp', placeholder: 'AA / YY' }),
					field('flxpos-b-cvc', T.cvc, { value: v.cvc, onChange: up('cvc', function (x) { return x.replace(/\D/g, '').slice(0, 4); }), inputMode: 'numeric', autoComplete: 'cc-csc', placeholder: 'CVC' })),
				opts.length ? el('div', { className: 'flxpos-b-field' },
					el('label', { htmlFor: 'flxpos-b-inst' }, T.inst),
					el('select', { id: 'flxpos-b-inst', className: 'flxpos-b-input', value: v.inst, onChange: up('inst') },
						[el('option', { key: 1, value: '1' }, T.single)].concat(opts.map(function (x) { return el('option', { key: x.n, value: String(x.n) }, x.label); })))) : null,
				el('p', { className: 'flxpos-secure' }, T.secure),
				data.test ? el('p', { className: 'flxpos-test' }, T.test) : null);
		};
	}

	(window.flxposBlocks || []).forEach(function (name) {
		var data = settings.getSetting(name + '_data', null);
		if (!data) return;
		var title = decode(data.title || name);
		var Desc = function () { return data.description ? el('p', null, decode(data.description)) : null; };
		var Content = data.card ? CardForm(data) : function (props) { useFrame(props); return Desc(); };
		reg.registerPaymentMethod({
			name: name,
			label: el('span', { className: 'flxpos-b-label' }, title, data.icon ? el('img', { src: data.icon, alt: '', loading: 'lazy' }) : null),
			ariaLabel: title,
			content: el(Content),
			edit: el(Desc),
			canMakePayment: function () { return true; },
			supports: { features: data.supports && data.supports.length ? data.supports : ['products'] }
		});
	});
})();
