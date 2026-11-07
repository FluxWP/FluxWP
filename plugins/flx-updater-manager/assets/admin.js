/* Flux Updater Manager · yönetim. GPL-2.0-or-later. */
(function () {
	'use strict';
	var A = window.flxumAdmin || { i18n: {} };
	var L = A.i18n;

	// Grafik ve harita ipucu: data-t "etiket|site|kontrol|indirme" ya da "ülke|site".
	document.querySelectorAll('.flxum-tip-host').forEach(function (host) {
		var tip = host.querySelector('.flxum-tip');
		host.addEventListener('mousemove', function (ev) {
			var t = ev.target.getAttribute && ev.target.getAttribute('data-t');
			if (!t) { tip.style.display = 'none'; return; }
			var p = t.split('|');
			tip.textContent = '';
			var b = document.createElement('b');
			b.textContent = p[0];
			tip.appendChild(b);
			var lines = p.length > 2 ? [L.site + ': ' + p[1], L.checks + ': ' + p[2], L.dl + ': ' + p[3]] : [L.site + ': ' + p[1]];
			lines.forEach(function (l) { tip.appendChild(document.createElement('br')); tip.appendChild(document.createTextNode(l)); });
			var r = host.getBoundingClientRect();
			tip.style.left = (ev.clientX - r.left) + 'px';
			tip.style.top = (ev.clientY - r.top - 10) + 'px';
			tip.style.display = 'block';
		});
		host.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
	});

	// Ülke satırı: tıklayınca paketler.
	document.querySelectorAll('.flxum-geo tr.flxum-geo-row.has').forEach(function (row) {
		function toggle() {
			var d = document.querySelector('.flxum-geo tr.flxum-geo-detail[data-for="' + row.getAttribute('data-k') + '"]');
			if (!d) return;
			d.hidden = !d.hidden;
			row.setAttribute('aria-expanded', d.hidden ? 'false' : 'true');
		}
		row.addEventListener('click', toggle);
		row.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); toggle(); } });
	});

	// "Tümünü göster"
	document.querySelectorAll('[data-more]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll(btn.getAttribute('data-more') + ' .flxum-more').forEach(function (r) { r.hidden = false; });
			btn.remove();
		});
	});

	// Erişim: "tümü" ↔ seçim; depo "tümü" kutusu.
	document.querySelectorAll('select[data-scope]').forEach(function (sel) {
		var box = sel.nextElementSibling;
		sel.addEventListener('change', function () { if (box) box.hidden = sel.value === 'all'; });
	});
	document.querySelectorAll('.flxum-repo-all input').forEach(function (cb) {
		cb.addEventListener('change', function () { cb.closest('fieldset').classList.toggle('whole', cb.checked); });
	});

	// Kopyala
	document.querySelectorAll('[data-copy]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var el = document.querySelector(btn.getAttribute('data-copy'));
			if (!el || !navigator.clipboard) return;
			navigator.clipboard.writeText(el.textContent.trim()).then(function () { btn.textContent = L.copied; });
		});
	});

	// Uzun işlemler (ülke veritabanı): düğmeyi kilitle.
	document.querySelectorAll('.flxum form').forEach(function (f) {
		f.addEventListener('submit', function (ev) {
			if (ev.defaultPrevented) return;
			var b = f.querySelector('button:not([type=button])');
			setTimeout(function () { if (b) { b.disabled = true; if (f.hasAttribute('data-busy')) b.textContent = L.working; } }, 0);
		});
	});

	// Ülke seçici: etiket + datalist ("TR · Türkiye").
	document.querySelectorAll('.flxum-cc-pick').forEach(function (box) {
		var hidden = box.querySelector('input[type=hidden]'), input = box.querySelector('.flxum-chip-in');
		var opts = Array.prototype.map.call(document.querySelectorAll('#flxum-cc-list option'), function (o) { return o.value; });
		function values() { return Array.prototype.map.call(box.querySelectorAll('.flxum-chip'), function (c) { return c.getAttribute('data-v'); }); }
		function sync() { hidden.value = values().join(','); }
		function find(v) {
			v = v.trim();
			if (!v) return '';
			var up = v.toUpperCase();
			var hit = opts.filter(function (o) { return o.slice(0, 2) === up && (up.length === 2 || o === v); });
			if (hit.length) return hit[0];
			var lo = v.toLowerCase();
			var hits = opts.filter(function (o) { return o.slice(5).toLowerCase().indexOf(lo) === 0; });
			return hits.length === 1 ? hits[0] : '';
		}
		function add(v) {
			var o = find(v);
			if (!o) return false;
			var cc = o.slice(0, 2);
			if (values().indexOf(cc) >= 0) return true;
			var chip = document.createElement('span');
			chip.className = 'flxum-chip';
			chip.setAttribute('data-v', cc);
			var b = document.createElement('b'); b.className = 'flxum-cc'; b.textContent = cc;
			var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', L.remove);
			chip.appendChild(b); chip.appendChild(document.createTextNode(o.slice(5))); chip.appendChild(x);
			box.insertBefore(chip, input);
			sync();
			return true;
		}
		box.addEventListener('click', function (ev) {
			if (ev.target.tagName === 'BUTTON') { ev.target.closest('.flxum-chip').remove(); sync(); return; }
			input.focus();
		});
		input.addEventListener('input', function () { if (opts.indexOf(input.value) >= 0 && add(input.value)) input.value = ''; });
		input.addEventListener('keydown', function (ev) {
			if (ev.key === 'Enter' || ev.key === ',') { ev.preventDefault(); if (add(input.value)) input.value = ''; }
			if (ev.key === 'Backspace' && !input.value) { var c = box.querySelectorAll('.flxum-chip'); if (c.length) { c[c.length - 1].remove(); sync(); } }
		});
	});

	// Depo seçici: GitHub depoları sunucu üzerinden (AJAX), owner/repo + Enter ile elle ekleme.
	var repoCache = {};
	function loadRepos(fresh) {
		var k = fresh ? 'f' : 'n';
		if (!repoCache[k]) {
			repoCache[k] = fetch(A.ajax + '?action=flxum_repos&nonce=' + encodeURIComponent(A.nonce) + (fresh ? '&fresh=1' : ''), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.catch(function () { return { repos: [], error: L.fail }; });
		}
		return repoCache[k];
	}
	document.querySelectorAll('.flxum-chips:not(.flxum-cc-pick)').forEach(function (box) {
		var hidden = box.querySelector('input[type=hidden]'), input = box.querySelector('.flxum-chip-in'), dd = box.querySelector('.flxum-chip-dd');
		var single = box.hasAttribute('data-single'), mode = box.getAttribute('data-mode'), active = -1;
		function values() { return Array.prototype.map.call(box.querySelectorAll('.flxum-chip'), function (c) { return c.getAttribute('data-v'); }); }
		function sync() { hidden.value = values().join(', '); input.placeholder = single && values().length ? '' : (single ? L.pick : L.add); }
		function add(v) {
			v = v.trim().replace(/^https?:\/\/github\.com\//, '').replace(/\/+$/, '');
			if (!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(v)) return false;
			if (values().some(function (x) { return x.toLowerCase() === v.toLowerCase(); })) return true;
			if (single) box.querySelectorAll('.flxum-chip').forEach(function (c) { c.remove(); });
			var chip = document.createElement('span');
			chip.className = 'flxum-chip';
			chip.setAttribute('data-v', v);
			chip.textContent = v;
			var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', L.remove);
			chip.appendChild(x);
			box.insertBefore(chip, input);
			sync();
			return true;
		}
		function render(fresh) {
			dd.hidden = false;
			dd.innerHTML = '<div class="note">' + L.loading + '</div>';
			loadRepos(fresh).then(function (data) {
				dd.innerHTML = '';
				var q = input.value.trim().toLowerCase(), have = values().map(function (v) { return v.toLowerCase(); });
				var list = (data.repos || []).filter(function (r) {
					if (have.indexOf(r.name.toLowerCase()) >= 0) return false;
					if (mode === 'private' && !r.private) return false;
					if (mode === 'public' && r.private) return false;
					return !q || r.name.toLowerCase().indexOf(q) >= 0;
				}).slice(0, 50);
				active = -1;
				list.forEach(function (r) {
					var d = document.createElement('div');
					var n = document.createElement('span'); n.textContent = r.name; d.appendChild(n);
					if (r.private) { var s = document.createElement('span'); s.className = 'lock'; s.textContent = 'private'; d.appendChild(s); }
					d.addEventListener('mousedown', function (ev) { ev.preventDefault(); add(r.name); input.value = ''; render(false); });
					dd.appendChild(d);
				});
				var note = document.createElement('div');
				note.className = 'note';
				note.textContent = (data.error ? data.error + ' · ' : '') + L.enter + ' · ';
				var re = document.createElement('a'); re.href = '#'; re.textContent = L.refresh;
				re.addEventListener('mousedown', function (ev) { ev.preventDefault(); repoCache = {}; render(true); });
				note.appendChild(re);
				dd.appendChild(note);
			});
		}
		box.addEventListener('click', function (ev) {
			if (ev.target.tagName === 'BUTTON') { ev.target.closest('.flxum-chip').remove(); sync(); return; }
			input.focus();
		});
		input.addEventListener('focus', function () { render(false); });
		input.addEventListener('input', function () { render(false); });
		input.addEventListener('blur', function () { setTimeout(function () { dd.hidden = true; }, 120); });
		input.addEventListener('keydown', function (ev) {
			var items = dd.querySelectorAll('div:not(.note)');
			if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
				ev.preventDefault();
				active = Math.max(0, Math.min(items.length - 1, active + (ev.key === 'ArrowDown' ? 1 : -1)));
				items.forEach(function (it, i) { it.classList.toggle('on', i === active); });
			} else if (ev.key === 'Enter' || ev.key === ',') {
				ev.preventDefault();
				var v = active >= 0 && items[active] ? items[active].firstChild.textContent : input.value;
				if (add(v)) { input.value = ''; render(false); }
			} else if (ev.key === 'Backspace' && !input.value) {
				var c = box.querySelectorAll('.flxum-chip');
				if (c.length) { c[c.length - 1].remove(); sync(); }
			}
		});
		sync();
	});
})();
