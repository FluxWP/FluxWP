/* Flux POS · yönetim: bağlantı testi, kopyalama, onaylar. GPL-2.0-or-later. */
(function () {
	'use strict';
	var A = window.flxposAdmin || { i18n: {} };
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-flxpos-test]');
		if (!b) return;
		e.preventDefault();
		var id = b.getAttribute('data-flxpos-test');
		var msg = document.querySelector('[data-flxpos-msg="' + id + '"]');
		if (!msg) return;
		msg.hidden = false;
		msg.className = 'flxpos-test-msg is-busy';
		msg.textContent = A.i18n.testing;
		b.disabled = true;
		fetch(A.rest + 'test/' + encodeURIComponent(id), { method: 'POST', headers: { 'X-WP-Nonce': A.nonce }, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				msg.className = 'flxpos-test-msg ' + (j.ok === true ? 'is-ok' : (j.ok === false ? 'is-bad' : 'is-info'));
				msg.textContent = j.message || A.i18n.fail;
			})
			.catch(function () { msg.className = 'flxpos-test-msg is-bad'; msg.textContent = A.i18n.fail; })
			.then(function () { b.disabled = false; });
	});
	document.addEventListener('focusin', function (e) {
		if (e.target.matches && e.target.matches('.flxpos-copy input')) e.target.select();
	});
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (f.hasAttribute('data-flxpos-confirm') && !window.confirm(A.i18n.confirm)) e.preventDefault();
		if (f.hasAttribute('data-flxpos-refund') && !window.confirm(A.i18n.refund)) e.preventDefault();
	});
})();
/* Taksit tablosu: aç/kapa ve örnek tutar. */
(function () {
	'use strict';
	var A = window.flxposAdmin || { i18n: {} };
	var box = document.querySelector('.flxpos-itable');
	if (!box) return;
	var sample = document.querySelector('[data-flxpos-sample]');
	var fmt;
	try { fmt = new Intl.NumberFormat(A.loc || 'tr-TR', { style: 'currency', currency: A.cur && A.cur !== 'TRY' ? 'TRY' : 'TRY' }); } catch (e) { fmt = { format: function (x) { return x.toFixed(2) + ' ₺'; } }; }
	var num = function (v) { var x = parseFloat(String(v || '').replace(/\s/g, '').replace(',', '.')); return isNaN(x) ? 0 : x; };
	function paint() {
		var base = num(sample && sample.value) || 1000;
		box.querySelectorAll('.flxpos-irow').forEach(function (row) {
			var n = +row.getAttribute('data-n');
			var inp = row.querySelector('input[name^="installments"]');
			var ex = row.querySelector('[data-flxpos-iex]');
			var on = row.querySelector('[data-flxpos-ion]').checked;
			row.classList.toggle('is-on', on);
			inp.disabled = !on;
			if (!on) { ex.textContent = A.i18n.off; return; }
			var r = num(inp.value);
			var total = base * (1 + r / 100);
			ex.textContent = A.i18n.iex.replace('%1$s', fmt.format(total / n)).replace('%2$s', fmt.format(total)) + (r === 0 ? ' · ' + A.i18n.free : '');
		});
	}
	box.addEventListener('change', function (e) {
		if (e.target.matches('[data-flxpos-ion]')) {
			var inp = e.target.closest('.flxpos-irow').querySelector('input[name^="installments"]');
			if (e.target.checked && inp.value === '') inp.value = '0';
			paint();
			if (e.target.checked) { inp.disabled = false; inp.focus(); inp.select(); }
		}
	});
	box.addEventListener('input', paint);
	if (sample) sample.addEventListener('input', paint);
	paint();
})();
