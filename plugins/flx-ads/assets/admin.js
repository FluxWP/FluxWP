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
	/* Tekrarlanan satırlar (Stüdyo: reklam setleri, reklamlar): <template data-flxads-tpl="x">, [data-flxads-rows="x"], [data-flxads-add="x"], [data-flxads-del]. */
	var seq = 0;
	document.addEventListener('click', function (e) {
		var add = e.target.closest('[data-flxads-add]');
		var del = e.target.closest('[data-flxads-del]');
		if (add) {
			var k = add.getAttribute('data-flxads-add');
			var tpl = document.querySelector('template[data-flxads-tpl="' + k + '"]');
			var box = document.querySelector('[data-flxads-rows="' + k + '"]');
			if (!tpl || !box) return;
			var max = parseInt(box.getAttribute('data-max') || '6', 10);
			if (box.children.length >= max) return;
			var w = document.createElement('div');
			w.innerHTML = tpl.innerHTML.replace(/__i__/g, 'n' + (seq++));
			var row = w.firstElementChild;
			box.appendChild(row);
			var f = row.querySelector('input,textarea,select');
			if (f) f.focus();
		} else if (del) {
			var r = del.closest('[data-flxads-row]');
			if (r && r.parentNode.children.length > 1) r.remove();
		}
	});
	/* Reklam önizlemesi: metin, başlık, görsel yazılırken güncellenir. */
	document.addEventListener('input', function (e) {
		var row = e.target.closest('[data-flxads-row="ads"]');
		if (!row) return;
		var pv = row.querySelector('[data-flxads-pv]');
		if (!pv) return;
		['text', 'headline', 'description'].forEach(function (k) {
			var src = row.querySelector('[data-k="' + k + '"]');
			var dst = pv.querySelector('[data-pv="' + k + '"]');
			if (src && dst) dst.textContent = src.value;
		});
		var img = row.querySelector('[data-k="image"]');
		var pi = pv.querySelector('[data-pv="image"]');
		if (img && pi) pi.style.backgroundImage = img.value ? 'url("' + img.value.replace(/"/g, '') + '")' : '';
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
