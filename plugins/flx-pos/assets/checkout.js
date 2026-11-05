/* Flux POS · klasik ödeme kart formu: biçimlendirme, kart markası, BIN'e göre taksitler. GPL v2 or later. */
(function () {
	'use strict';
	var C = window.FLXPOS || {};
	var api = window.FLXPOS_API = window.FLXPOS_API || {};

	api.brand = function (pan) {
		pan = String(pan || '').replace(/\D/g, '');
		if (/^4/.test(pan)) return 'visa';
		if (/^(5[1-5]|2[2-7])/.test(pan)) return 'mastercard';
		if (/^3[47]/.test(pan)) return 'amex';
		if (/^(9792|65|36)/.test(pan)) return 'troy';
		return '';
	};
	api.formatPan = function (v) {
		var d = String(v || '').replace(/\D/g, '').slice(0, 19);
		return d.replace(/(\d{4})(?=\d)/g, '$1 ');
	};
	api.formatExp = function (v) {
		var d = String(v || '').replace(/\D/g, '').slice(0, 4);
		return d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d;
	};
	var cache = {};
	api.installments = function (account, amount, bin, cb) {
		var key = account + '|' + amount + '|' + bin;
		if (cache[key]) { cb(cache[key]); return; }
		var url = C.rest + 'installments?account=' + encodeURIComponent(account) + '&amount=' + encodeURIComponent(amount) + '&bin=' + encodeURIComponent(bin);
		fetch(url, { headers: { 'X-WP-Nonce': C.nonce }, credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : { options: [] }; })
			.then(function (j) { cache[key] = (j && j.options) || []; cb(cache[key]); })
			.catch(function () { cb([]); });
	};

	function onInput(e) {
		var t = e.target;
		if (!t || !t.closest) return;
		var box = t.closest('.flxpos-card');
		if (!box) return;
		if (t.name === 'flxpos_pan') {
			t.value = api.formatPan(t.value);
			var b = api.brand(t.value);
			var s = box.querySelector('.flxpos-brand');
			if (s) s.setAttribute('data-brand', C.brands ? b : '');
			var bin = t.value.replace(/\D/g, '').slice(0, 8);
			var row = box.querySelector('.flxpos-inst');
			if (!row) return;
			if (bin.length < 6) { row.hidden = true; return; }
			if (box.getAttribute('data-bin') === bin) return;
			box.setAttribute('data-bin', bin);
			api.installments(box.getAttribute('data-account'), box.getAttribute('data-amount'), bin, function (opts) {
				var sel = row.querySelector('select');
				var keep = sel.value;
				sel.innerHTML = '';
				var one = document.createElement('option');
				one.value = '1'; one.textContent = C.i18n.single;
				sel.appendChild(one);
				opts.forEach(function (o) {
					if (o.n < 2) return;
					var op = document.createElement('option');
					op.value = String(o.n); op.textContent = o.label;
					sel.appendChild(op);
				});
				if (sel.querySelector('option[value="' + keep + '"]')) sel.value = keep;
				row.hidden = sel.options.length < 2;
			});
		} else if (t.name === 'flxpos_exp') {
			if (e.inputType && e.inputType.indexOf('delete') === 0) return;
			t.value = api.formatExp(t.value);
		} else if (t.name === 'flxpos_cvc') {
			t.value = t.value.replace(/\D/g, '').slice(0, 4);
		}
	}
	document.addEventListener('input', onInput);
})();
