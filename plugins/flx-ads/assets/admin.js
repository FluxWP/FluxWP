/* Flux Ads · yönetim: tarih aralığı seçimi, göster/gizle anahtarları, UTM bağlantı oluşturucu. */
(function () {
	'use strict';
	var L = window.flxAdsAdmin || {};
	var range = document.querySelector('[data-flxads-range]');
	if (range) {
		range.addEventListener('change', function () {
			document.querySelectorAll('.flxads-custom').forEach(function (el) { el.hidden = range.value !== 'custom'; });
		});
	}
	document.querySelectorAll('[data-flxads-toggle]').forEach(function (t) {
		var sync = function () {
			t.form.querySelectorAll('[data-flxads-show="' + t.getAttribute('data-flxads-toggle') + '"]').forEach(function (el) { el.hidden = !t.checked; });
		};
		t.addEventListener('change', sync);
		sync();
	});
	var b = document.querySelector('[data-flxads-builder]');
	if (!b) return;
	var out = b.querySelector('[data-flxads-out]');
	function slug(v) {
		return String(v || '').trim().toLowerCase()
			.replace(/[çÇ]/g, 'c').replace(/[ğĞ]/g, 'g').replace(/[ıİ]/g, 'i').replace(/[öÖ]/g, 'o').replace(/[şŞ]/g, 's').replace(/[üÜ]/g, 'u')
			.replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
	}
	function build() {
		var u;
		try { u = new URL(b.querySelector('[name=url]').value || L.home); } catch (e) { out.textContent = ''; return; }
		['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'].forEach(function (k) {
			var v = slug(b.querySelector('[name=' + k + ']').value);
			if (v) u.searchParams.set(k, v); else u.searchParams.delete(k);
		});
		out.textContent = u.toString();
	}
	b.addEventListener('input', build);
	b.addEventListener('change', build);
	build();
	var btn = b.querySelector('[data-flxads-copy]');
	btn.addEventListener('click', function () {
		var t = out.textContent;
		(navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).catch(function () {
			var r = document.createRange(); r.selectNodeContents(out); var s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy');
		}).then(function () {
			btn.textContent = L.copied || 'Copied';
			setTimeout(function () { btn.textContent = L.copy || 'Copy'; }, 1500);
		});
	});
})();
