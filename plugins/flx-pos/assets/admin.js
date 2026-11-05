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
