/* Flux Commerce · yönetim. GPL-2.0-or-later. */
(function () {
	'use strict';
	var cfg = window.flxcmAdmin || {};
	function post(data) {
		var body = new FormData();
		body.append('_ajax_nonce', cfg.nonce);
		Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function fmt(s, a, b) { return String(s).replace('%1$s', a).replace('%2$s', b); }

	// Müşteri listesini yeniden oluştur (100'lük parçalar).
	var btn = document.querySelector('[data-flxcm-rebuild]');
	var bar = document.querySelector('[data-flxcm-progress]');
	if (btn && bar) {
		btn.addEventListener('click', function () {
			btn.disabled = true;
			bar.hidden = false;
			bar.textContent = fmt(cfg.working, 0, '…');
			(function step(page) {
				post({ action: 'flxcm_rebuild', page: page }).then(function (r) {
					if (!r || !r.success) throw new Error('fail');
					var d = r.data;
					bar.textContent = fmt(cfg.working, d.scanned.toLocaleString(), d.total.toLocaleString());
					if (d.done) { bar.textContent = cfg.done; location.reload(); return; }
					step(page + 1);
				}).catch(function () { bar.textContent = cfg.fail; btn.disabled = false; });
			})(1);
		});
	}

	// Müşteri notu: alandan çıkınca ya da Enter ile kaydedilir.
	document.querySelectorAll('[data-flxcm-note]').forEach(function (el) {
		var last = el.value;
		function save() {
			if (el.value === last) return;
			post({ action: 'flxcm_note', id: el.getAttribute('data-flxcm-note'), note: el.value }).then(function (r) {
				if (r && r.success) {
					last = el.value;
					el.classList.add('is-saved');
					el.title = cfg.saved;
					setTimeout(function () { el.classList.remove('is-saved'); }, 1200);
				}
			});
		}
		el.addEventListener('blur', save);
		el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } });
	});

	// Kopyala düğmeleri.
	document.querySelectorAll('[data-flxcm-copy]').forEach(function (b) {
		b.addEventListener('click', function () {
			var v = b.getAttribute('data-flxcm-copy');
			(navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () {
				var t = b.lastChild && b.lastChild.nodeType === 3 ? b.lastChild : null;
				if (t) { var o = t.textContent; t.textContent = ' ' + cfg.copied; setTimeout(function () { t.textContent = o; }, 1400); }
			}).catch(function () {});
		});
	});
})();
