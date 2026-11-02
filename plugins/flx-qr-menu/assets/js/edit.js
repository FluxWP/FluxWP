/**
 * Flux QR Menu: menü düzenleyici (derleme adımı yok, bağımlılık yok).
 * Sekmeler: Menü / Ayarlar / Paylaş / Bağlantı (yalnız yönetici) / Geçmiş / JSON. Kayıt REST ile, iyimser eşzamanlılık (base_version → 409).
 * QR kod: assets/vendor/qrcode-generator (MIT).
 */
(function () {
	'use strict';

	var B = window.FLXQ_BOOT;
	var I = window.FLXQ_I18N || {};
	var app = document.getElementById('flxq-app');
	if (!B || !app) return;

	function t(s) { return I[s] || s; }
	function fmt(s) {
		var a = arguments, i = 0;
		return String(s).replace(/%(\d+)\$[sd]|%[sd]/g, function (m, n) { return String(a[n ? +n : ++i]); });
	}

	/* ---------- Durum ---------- */

	var S = {
		menu: B.menu,
		data: JSON.parse(B.menu.data_json),
		name: B.menu.name,
		base: B.menu.version,
		saved: '',
		busy: false,
		tab: 'menu',
		hist: { page: 1, open: 0 },
		jsonDirty: false
	};
	var openItems = new WeakSet();
	var closedCats = new WeakSet();
	var autoId = new WeakSet();
	var openSettings = new WeakSet();
	var nameInput = document.getElementById('flxq-name');

	function snapshot() { return JSON.stringify({ n: S.name, d: S.data }); }
	S.saved = snapshot();
	function isDirty() { return snapshot() !== S.saved; }

	/* ---------- Yardımcılar ---------- */

	function h(tag, attrs, kids) {
		var e = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) return;
				if (k === 'class') e.className = v;
				else if (k === 'text') e.textContent = v;
				else if (k === 'html') e.innerHTML = v;
				else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), v);
				else if (k === 'value') e.value = v;
				else if (k === 'checked') e.checked = !!v;
				else e.setAttribute(k, v === true ? '' : v);
			});
		}
		(Array.isArray(kids) ? kids : kids === undefined ? [] : [kids]).forEach(function (c) {
			if (c === null || c === undefined || c === false) return;
			e.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
		});
		return e;
	}
	function panel(name) { return app.querySelector('[data-panel="' + name + '"]'); }

	function restUrl(path) {
		var root = B.restRoot;
		var route = '/' + B.ns + '/menus/' + S.menu.id + (path || '');
		if (root.indexOf('rest_route=') !== -1) return root.replace(/rest_route=[^&]*/, 'rest_route=' + encodeURIComponent(route).replace(/%2F/g, '/'));
		return root.replace(/\/$/, '') + route;
	}
	function api(path, method, body) {
		var url = restUrl(path);
		var q = '_locale=user' + (method === 'GET' ? '&_t=' + Date.now() : '');
		if (body && method === 'GET') {
			Object.keys(body).forEach(function (k) { q += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(body[k]); });
			body = null;
		}
		url += (url.indexOf('?') === -1 ? '?' : '&') + q;
		return fetch(url, {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': B.nonce, 'Content-Type': 'application/json', Accept: 'application/json' },
			body: body ? JSON.stringify(body) : undefined
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) {
					var e = new Error(j && j.message ? j.message : 'HTTP ' + r.status);
					e.status = r.status;
					e.data = j;
					throw e;
				}
				return j;
			});
		});
	}

	function slug(s) {
		var map = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'İ': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'Ç': 'c', 'Ğ': 'g', 'Ö': 'o', 'Ş': 's', 'Ü': 'u', 'â': 'a', 'î': 'i', 'û': 'u' };
		return String(s || '').replace(/[çğıİöşüÇĞÖŞÜâîû]/g, function (c) { return map[c]; })
			.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
			.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
	}
	function uniqueId(base, cat) {
		var id = base || 'kategori', n = 2, ids = S.data.kategoriler.filter(function (k) { return k !== cat; }).map(function (k) { return k.id; });
		while (ids.indexOf(id) !== -1) id = base + '-' + n++;
		return id;
	}
	function parsePrice(v) {
		v = String(v).trim();
		if (v === '') return null;
		var n = v.replace(/\s/g, '').replace(',', '.');
		if (/^-?\d+(\.\d+)?$/.test(n)) return Number(n);
		return v;
	}
	function priceText(v) { return v === null || v === undefined ? '' : String(v); }
	function isSub(u) { return Object.prototype.hasOwnProperty.call(u, 'araBaslik'); }
	// Göreli yol eklentiyle gelen örnek görsellerden biriyse (img/x.jpg) onun adresi, değilse ''.
	function bundledUrl(g) {
		var b = S.menu.bundled;
		if (!b || !b.files || !g) return '';
		var rel = String(g).trim().replace(/^\.\//, '').replace(/^\/+/, '');
		return b.files.indexOf(rel) !== -1 ? b.base + rel : '';
	}
	function imgUrl(g) {
		if (!g) return '';
		if (/^(https?:)?\/\//i.test(g)) return g;
		var bu = bundledUrl(g);
		if (bu) return bu;
		var base = S.menu.base || '';
		if (!base) return '';
		if (g.charAt(0) === '/') { var m = base.match(/^https?:\/\/[^/]+/i); return m ? m[0] + g : ''; }
		return base + g;
	}
	function copy(text, btn) {
		var done = function () {
			if (!btn) return;
			var o = btn.textContent;
			btn.textContent = t('Kopyalandı');
			setTimeout(function () { btn.textContent = o; }, 1500);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { fallback(); });
		} else fallback();
		function fallback() {
			var ta = h('textarea', { style: 'position:fixed;opacity:0' });
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			try { document.execCommand('copy'); done(); } catch (e) { /* yok */ }
			ta.remove();
		}
	}
	function dateTime(ts) {
		if (!ts) return '';
		try {
			return new Intl.DateTimeFormat(B.locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ts * 1000));
		} catch (e) { return new Date(ts * 1000).toLocaleString(); }
	}
	function toast(msg, bad) {
		var el = h('div', { class: 'flxq-toast' + (bad ? ' is-bad' : ''), role: 'status', text: msg });
		document.body.appendChild(el);
		setTimeout(function () { el.classList.add('is-out'); }, 2600);
		setTimeout(function () { el.remove(); }, 3000);
	}

	/* ---------- Kaydetme çubuğu ---------- */

	var statusEl = document.getElementById('flxq-save-status');
	var saveBtn = document.getElementById('flxq-save');
	var revertBtn = document.getElementById('flxq-revert');
	var conflictEl = document.getElementById('flxq-conflict');
	var stateEl = document.getElementById('flxq-state');

	function changed() {
		var d = isDirty();
		app.classList.toggle('is-dirty', d);
		app.classList.toggle('hide-savebar', !d && (S.tab === 'connect' || S.tab === 'history' || S.tab === 'share'));
		saveBtn.disabled = !d || S.busy;
		revertBtn.disabled = !d || S.busy;
		if (S.busy) return;
		statusEl.className = 'flxq-save-status' + (d ? ' is-dirty' : '');
		statusEl.textContent = d ? t('Kaydedilmemiş değişiklikler') : fmt(t('Kaydedildi · v%s'), S.base);
		previewSchedule();
	}
	function renderState() {
		stateEl.className = 'flxq-pill ' + (S.menu.enabled ? 'is-on' : 'is-off');
		stateEl.textContent = S.menu.enabled ? t('Yayında') : t('Kapalı');
	}

	function applyServerMenu(m, keepObjects) {
		S.menu = m;
		S.base = m.version;
		S.name = m.name;
		nameInput.value = m.name;
		var server = JSON.parse(m.data_json);
		if (!keepObjects || JSON.stringify(server) !== JSON.stringify(S.data)) {
			S.data = server;
			renderAll();
		}
		S.saved = snapshot();
		S.jsonDirty = false;
		renderState();
		if (S.tab === 'share') renderShare();
		renderImageNotice();
		changed();
	}

	function save(force) {
		if (S.busy || (!isDirty() && !force)) return;
		S.busy = true;
		saveBtn.disabled = revertBtn.disabled = true;
		statusEl.className = 'flxq-save-status';
		statusEl.textContent = t('Kaydediliyor…');
		api('', 'POST', { name: S.name, data_json: JSON.stringify(S.data), base_version: S.base, force: !!force })
			.then(function (r) {
				S.busy = false;
				conflictEl.hidden = true;
				applyServerMenu(r.menu, true);
				if (S.tab === 'history') loadHistory(1);
				toast(r.unchanged ? t('Değişiklik yok.') : fmt(t('Kaydedildi · v%s'), r.version));
			})
			.catch(function (e) {
				S.busy = false;
				changed();
				if (e.status === 409) return showConflict(e.data && e.data.data ? e.data.data : {});
				statusEl.className = 'flxq-save-status is-bad';
				statusEl.textContent = fmt(t('Kaydedilemedi: %s'), e.message);
			});
	}

	function showConflict(d) {
		conflictEl.hidden = false;
		conflictEl.innerHTML = '';
		var list = h('ul', null, (d.changes || []).map(function (c) {
			return h('li', null, [h('strong', { text: 'v' + c.version }), ' · ' + (c.user || '—') + ' · ' + c.time_str + ': ' + (c.summary || '')]);
		}));
		conflictEl.appendChild(h('p', null, [h('strong', { text: t('Bu menü siz düzenlerken başka bir yerden kaydedildi.') }), ' ', fmt(t('Sunucudaki sürüm: v%s.'), d.version)]));
		if ((d.changes || []).length) conflictEl.appendChild(list);
		conflictEl.appendChild(h('div', { class: 'flxq-row' }, [
			h('button', { type: 'button', class: 'flxq-btn', text: t('Değişikliklerimi JSON olarak kopyala'), onclick: function (e) { copy(JSON.stringify(S.data, null, 2), e.currentTarget); } }),
			h('button', { type: 'button', class: 'flxq-btn', text: t('Sunucudakini yükle (değişikliklerim silinir)'), onclick: function () {
				if (!confirm(t('Kaydedilmemiş değişiklikleriniz silinecek. Devam edilsin mi?'))) return;
				reload();
			} }),
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-danger', text: t('Benimkini yine de kaydet'), onclick: function () {
				if (!confirm(t('Sunucudaki yeni değişikliklerin üzerine yazılacak. Emin misiniz?'))) return;
				save(true);
			} })
		]));
	}

	function reload() {
		return api('', 'GET').then(function (m) {
			conflictEl.hidden = true;
			applyServerMenu(m, false);
			toast(fmt(t('Sunucudaki sürüm yüklendi · v%s'), m.version));
		}).catch(function (e) { toast(e.message, true); });
	}

	saveBtn.addEventListener('click', function () { save(false); });
	revertBtn.addEventListener('click', function () {
		if (!confirm(t('Kaydedilmemiş değişiklikler geri alınsın mı?'))) return;
		var s = JSON.parse(S.saved);
		S.data = s.d;
		S.name = s.n;
		nameInput.value = s.n;
		S.jsonDirty = false;
		renderAll();
		changed();
	});
	nameInput.addEventListener('input', function () { S.name = nameInput.value; changed(); });
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) {
			e.preventDefault();
			save(false);
		}
	});
	window.addEventListener('beforeunload', function (e) {
		if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
	});

	/* ---------- Sekmeler ---------- */

	var tabs = app.querySelectorAll('.flxq-tabs [data-tab]');
	function showTab(name) {
		S.tab = name;
		tabs.forEach(function (b) {
			var on = b.getAttribute('data-tab') === name;
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		app.querySelectorAll('.flxq-tabpanel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
		changed();
		if (name === 'history') loadHistory(S.hist.page);
		if (name === 'json' && !S.jsonDirty) renderJson();
		if (name === 'connect') renderConnect();
		if (name === 'share') renderShare();
		try { sessionStorage.setItem('flxq-tab-' + S.menu.id, name); } catch (e) { /* yok */ }
	}
	tabs.forEach(function (b) { b.addEventListener('click', function () { showTab(b.getAttribute('data-tab')); }); });

	function renderAll() {
		renderMenu();
		renderSettings();
		renderConnect();
		renderShare();
		if (S.tab === 'json') renderJson();
	}

	/* ---------- Menü sekmesi ---------- */

	var drag = null;

	function catStats(k) {
		var n = 0, out = 0, hid = 0;
		(k.urunler || []).forEach(function (u) {
			if (isSub(u)) return;
			n++;
			if (u.bitti) out++;
			if (u.gizli) hid++;
		});
		return { n: n, out: out, hid: hid };
	}
	function badges(k) {
		var s = catStats(k);
		return [
			h('span', { class: 'flxq-tag', text: fmt(t('%s ürün'), s.n) }),
			s.out ? h('span', { class: 'flxq-tag is-warn', text: fmt(t('%s tükendi'), s.out) }) : null,
			s.hid ? h('span', { class: 'flxq-tag', text: fmt(t('%s gizli'), s.hid) }) : null
		];
	}
	function refreshBadges(sec, k) {
		var b = sec.querySelector('.flxq-cat-badges');
		if (!b) return;
		b.innerHTML = '';
		badges(k).forEach(function (x) { if (x) b.appendChild(x); });
		refreshTotals();
	}
	function refreshTotals() {
		var el = panel('menu').querySelector('.flxq-totals');
		if (!el) return;
		var n = 0, out = 0, hid = 0;
		S.data.kategoriler.forEach(function (k) { var s = catStats(k); n += s.n; out += s.out; hid += s.hid; });
		el.textContent = fmt(t('%1$s ürün · %2$s tükendi · %3$s gizli'), n, out, hid);
	}

	function move(arr, from, to) {
		if (to < 0 || to >= arr.length || from === to) return;
		var x = arr.splice(from, 1)[0];
		arr.splice(to, 0, x);
	}

	function renderMenu() {
		var p = panel('menu');
		var y = window.scrollY;
		p.innerHTML = '';
		var search = h('input', { type: 'search', class: 'flxq-input flxq-search', placeholder: t('Ürün ara…'), 'aria-label': t('Ürün ara…'), oninput: function () { filter(search.value); } });
		p.appendChild(h('div', { class: 'flxq-toolbar' }, [
			search,
			h('span', { class: 'flxq-totals flxq-muted' }),
			h('span', { class: 'flxq-grow' }),
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Tümünü daralt'), onclick: function () { S.data.kategoriler.forEach(function (k) { closedCats.add(k); }); renderMenu(); } }),
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Tümünü aç'), onclick: function () { S.data.kategoriler.forEach(function (k) { closedCats.delete(k); }); renderMenu(); } })
		]));
		p.appendChild(h('div', { class: 'flxq-imgnote', id: 'flxq-imgnote', hidden: true }));
		var wrap = h('div', { class: 'flxq-cats' });
		S.data.kategoriler.forEach(function (k, ci) { wrap.appendChild(renderCat(k, ci)); });
		p.appendChild(wrap);
		p.appendChild(h('div', { class: 'flxq-add-row' }, [
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', text: '＋ ' + t('Kategori ekle'), onclick: function () {
				var k = { id: '', baslik: t('Yeni kategori'), not: '', ikon: 'yemek', urunler: [] };
				k.id = uniqueId(slug(k.baslik), k);
				autoId.add(k);
				S.data.kategoriler.push(k);
				renderMenu();
				changed();
				var secs = panel('menu').querySelectorAll('.flxq-cat');
				var last = secs[secs.length - 1];
				if (last) { last.scrollIntoView({ block: 'center' }); var f = last.querySelector('.flxq-cat-title-input'); if (f) f.select(); }
			} })
		]));
		refreshTotals();
		renderImageNotice();
		window.scrollTo(0, y);
	}

	/* ---------- Göreli görseller → medya kütüphanesi ---------- */

	function isRelImg(g) { return !!g && !/^([a-z][a-z0-9+.-]*:|\/\/)/i.test(String(g).trim()); }
	function relImages() {
		var out = [];
		(S.data.kategoriler || []).forEach(function (k) {
			(k.urunler || []).forEach(function (u) { if (isRelImg(u.gorsel) && out.indexOf(u.gorsel) === -1) out.push(u.gorsel); });
		});
		return out;
	}
	var importing = false;
	function renderImageNotice(msg) {
		var box = document.getElementById('flxq-imgnote');
		if (!box) return;
		var rel = relImages(), n = rel.length;
		var own = rel.filter(function (g) { return !bundledUrl(g); }).length;
		box.innerHTML = '';
		box.hidden = !n && !msg;
		if (box.hidden) return;
		var can = S.menu.can && S.menu.can.upload;
		box.appendChild(h('p', null, [
			msg ? h('strong', { text: msg }) : (own
				? fmt(t('%s ürün görseli menü sitesindeki dosyalara (img/…) bağlı. Bu sitede yayınlanan menüde görünmeleri için medya kütüphanesine aktarın.'), n)
				: fmt(t('%s ürün görseli eklentiyle gelen örnek görsellere (img/…) bağlı. Medya kütüphanesine aktarırsanız ortam kütüphanesinde görünür ve oradan değiştirilebilir.'), n))
		]));
		if (n && can) {
			box.appendChild(h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', id: 'flxq-import-images', disabled: importing, text: t('Görselleri medya kütüphanesine aktar'), onclick: importImages }));
			if (!S.menu.base && own) box.appendChild(h('p', { class: 'flxq-muted', text: t('Görsellerin indirileceği menü sitesi bilinmiyor: menü sitesi bu menüye bağlanıp en az bir kez veri almalı.') }));
		}
	}
	function importImages() {
		if (importing) return;
		if (isDirty()) { toast(t('Önce değişiklikleri kaydedin.'), true); return; }
		importing = true;
		var skip = [], total = 0, failed = [];
		function step() {
			renderImageNotice(fmt(t('Aktarılıyor… %s görsel aktarıldı'), total));
			api('/import-images', 'POST', { base_version: S.base, skip: skip }).then(function (r) {
				total += r.done;
				(r.failed || []).forEach(function (f) { skip.push(f.path); failed.push(f.path + ': ' + f.error); });
				applyServerMenu(r.menu, false);
				if (r.remaining > 0 && (r.done > 0 || (r.failed || []).length)) return step();
				importing = false;
				renderImageNotice(failed.length ? fmt(t('%1$s görsel aktarıldı, %2$s görsel aktarılamadı.'), total, failed.length) : fmt(t('%s görsel aktarıldı.'), total));
				if (failed.length) {
					var box = document.getElementById('flxq-imgnote');
					if (box) box.appendChild(h('ul', { class: 'flxq-muted' }, failed.slice(0, 20).map(function (x) { return h('li', { text: x }); })));
				}
			}).catch(function (e) {
				importing = false;
				renderImageNotice();
				if (e.status === 409) return showConflict(e.data && e.data.data ? e.data.data : {});
				toast(e.message, true);
			});
		}
		step();
	}

	function filter(q) {
		q = q.trim().toLocaleLowerCase(B.locale);
		panel('menu').querySelectorAll('.flxq-cat').forEach(function (sec) {
			var any = false;
			sec.querySelectorAll('.flxq-item').forEach(function (li) {
				var hit = !q || (li.getAttribute('data-name') || '').toLocaleLowerCase(B.locale).indexOf(q) !== -1;
				li.classList.toggle('is-filtered', !hit);
				if (hit) any = true;
			});
			sec.classList.toggle('is-filtered', !!q && !any);
			if (q && any) sec.classList.add('is-open-search');
			else sec.classList.remove('is-open-search');
		});
	}

	function field(label, input, cls) {
		return h('label', { class: 'flxq-field' + (cls ? ' ' + cls : '') }, [h('span', { text: label }), input]);
	}
	function textInput(val, on, attrs) {
		var a = Object.assign({ type: 'text', class: 'flxq-input', value: val === undefined || val === null ? '' : String(val) }, attrs || {});
		a.oninput = function (e) { on(e.target.value, e); changed(); };
		return h('input', a);
	}
	var ICON_NAMES = { yemek: t('Yemek'), salata: t('Salata'), icecek: t('İçecek'), tatli: t('Tatlı'), fidan: t('Fidan') };

	function renderCat(k, ci) {
		var open = !closedCats.has(k);
		var sec = h('section', { class: 'flxq-cat' + (open ? ' is-open' : ''), 'data-ci': ci });
		var head = h('div', { class: 'flxq-cat-head' }, [
			h('span', { class: 'flxq-handle', title: t('Sürükleyerek taşı'), 'aria-hidden': 'true', text: '⋮⋮', onmousedown: function () { sec.draggable = true; } }),
			h('button', { type: 'button', class: 'flxq-cat-toggle', 'aria-expanded': open ? 'true' : 'false', onclick: function () {
				if (closedCats.has(k)) closedCats.delete(k); else closedCats.add(k);
				var s2 = renderCat(k, ci);
				sec.replaceWith(s2);
			} }, [h('span', { class: 'flxq-caret', 'aria-hidden': 'true' }), h('span', { class: 'flxq-cat-name', text: k.baslik || k.id }), h('span', { class: 'flxq-cat-badges' }, badges(k))]),
			h('span', { class: 'flxq-icon-btns' }, [
				h('button', { type: 'button', class: 'flxq-icon-btn', title: t('Yukarı taşı'), 'aria-label': t('Yukarı taşı'), disabled: ci === 0, text: '↑', onclick: function () { move(S.data.kategoriler, ci, ci - 1); renderMenu(); changed(); } }),
				h('button', { type: 'button', class: 'flxq-icon-btn', title: t('Aşağı taşı'), 'aria-label': t('Aşağı taşı'), disabled: ci === S.data.kategoriler.length - 1, text: '↓', onclick: function () { move(S.data.kategoriler, ci, ci + 1); renderMenu(); changed(); } }),
				h('button', { type: 'button', class: 'flxq-icon-btn is-danger', title: t('Kategoriyi sil'), 'aria-label': t('Kategoriyi sil'), text: '✕', onclick: function () {
					if (!confirm(fmt(t('"%1$s" kategorisi ve içindeki %2$s ürün silinsin mi?'), k.baslik, catStats(k).n))) return;
					S.data.kategoriler.splice(ci, 1);
					renderMenu();
					changed();
				} })
			])
		]);
		sec.appendChild(head);

		// Kategori sürükle-bırak.
		sec.addEventListener('dragstart', function (e) {
			if (e.target !== sec) return;
			drag = { type: 'cat', ci: ci };
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData('text/plain', 'cat'); } catch (er) { /* yok */ }
			sec.classList.add('is-dragging');
		});
		sec.addEventListener('dragend', function () { sec.draggable = false; sec.classList.remove('is-dragging'); clearDrop(); drag = null; });
		sec.addEventListener('dragover', function (e) {
			if (!drag) return;
			if (drag.type === 'cat') {
				e.preventDefault();
				clearDrop();
				var r = sec.getBoundingClientRect();
				sec.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
			} else if (drag.type === 'item' && (!open || !(k.urunler || []).length)) {
				e.preventDefault();
				clearDrop();
				sec.classList.add('drop-into');
			}
		});
		sec.addEventListener('drop', function (e) {
			if (!drag) return;
			e.preventDefault();
			if (drag.type === 'cat') {
				var to = sec.classList.contains('drop-after') ? ci + 1 : ci;
				if (drag.ci < to) to--;
				move(S.data.kategoriler, drag.ci, to);
			} else if (drag.type === 'item' && sec.classList.contains('drop-into')) {
				var src = S.data.kategoriler[drag.ci].urunler;
				var it = src.splice(drag.ii, 1)[0];
				k.urunler = k.urunler || [];
				k.urunler.push(it);
			} else return;
			drag = null;
			clearDrop();
			renderMenu();
			changed();
		});

		if (!open) return sec;

		var body = h('div', { class: 'flxq-cat-body' });
		var iconSel = h('select', { class: 'flxq-input', onchange: function (e) { k.ikon = e.target.value; changed(); } },
			(B.icons.indexOf(k.ikon) === -1 && k.ikon ? B.icons.concat([k.ikon]) : B.icons).map(function (ic) {
				return h('option', { value: ic, selected: ic === k.ikon ? 'selected' : null, text: ICON_NAMES[ic] || ic });
			}));
		var idInput = textInput(k.id, function (v) { k.id = slug(v) || v; autoId.delete(k); }, { class: 'flxq-input flxq-mono', pattern: '[a-z0-9-]+', maxlength: 40 });
		idInput.addEventListener('change', function () { k.id = uniqueId(slug(idInput.value), k); idInput.value = k.id; changed(); });
		var tabToggle = h('input', { type: 'checkbox', checked: k.sekme !== false, onchange: function (e) {
			if (e.target.checked) delete k.sekme; else k.sekme = false;
			changed();
		} });
		var settings = h('details', { class: 'flxq-cat-settings', open: openSettings.has(k) || autoId.has(k) ? 'open' : null, ontoggle: function (e) {
			if (e.target.open) openSettings.add(k); else openSettings.delete(k);
		} }, [h('summary', { text: t('Kategori ayarları') })]);
		body.appendChild(settings);
		settings.appendChild(h('div', { class: 'flxq-grid flxq-cat-fields' }, [
			field(t('Başlık'), textInput(k.baslik, function (v) {
				k.baslik = v;
				head.querySelector('.flxq-cat-name').textContent = v || k.id;
				if (autoId.has(k)) { k.id = uniqueId(slug(v), k); idInput.value = k.id; }
			}, { class: 'flxq-input flxq-cat-title-input' })),
			field(t('Not'), textInput(k.not, function (v) { if (v) k.not = v; else delete k.not; })),
			field(t('İkon'), iconSel),
			field(t('Kimlik'), idInput),
			h('label', { class: 'flxq-switch' }, [tabToggle, h('span', { text: t('Alt çubukta sekmesi olsun') })])
		]));

		var list = h('ul', { class: 'flxq-items' });
		(k.urunler || []).forEach(function (u, ii) { list.appendChild(renderItem(k, ci, u, ii, sec)); });
		body.appendChild(list);
		body.appendChild(h('div', { class: 'flxq-add-row' }, [
			h('button', { type: 'button', class: 'flxq-btn', text: '＋ ' + t('Ürün ekle'), onclick: function () {
				var u = { ad: '', aciklama: '', fiyat: null, gorsel: '', bitti: false };
				k.urunler = k.urunler || [];
				k.urunler.push(u);
				openItems.add(u);
				rerenderCat(sec, k, ci);
				changed();
				var items = panel('menu').querySelectorAll('.flxq-cat[data-ci="' + ci + '"] .flxq-item');
				var last = items[items.length - 1];
				if (last) { var f = last.querySelector('.flxq-item-name'); if (f) f.focus(); }
			} }),
			h('button', { type: 'button', class: 'flxq-btn', text: '＋ ' + t('Ara başlık ekle'), onclick: function () {
				k.urunler = k.urunler || [];
				k.urunler.push({ araBaslik: t('Yeni ara başlık') });
				rerenderCat(sec, k, ci);
				changed();
			} })
		]));
		sec.appendChild(body);
		return sec;
	}

	function rerenderCat(sec, k, ci) {
		var n = renderCat(k, ci);
		sec.replaceWith(n);
		refreshTotals();
		return n;
	}

	function clearDrop() {
		panel('menu').querySelectorAll('.drop-before,.drop-after,.drop-into').forEach(function (e) { e.classList.remove('drop-before', 'drop-after', 'drop-into'); });
	}

	function toggleBtn(label, on, cls, onclick) {
		return h('button', { type: 'button', class: 'flxq-tog ' + cls + (on ? ' is-on' : ''), 'aria-pressed': on ? 'true' : 'false', text: label, onclick: onclick });
	}

	function renderItem(k, ci, u, ii, sec) {
		var sub = isSub(u);
		var open = openItems.has(u);
		var li = h('li', { class: 'flxq-item' + (sub ? ' is-sub' : '') + (open ? ' is-open' : '') + (u.bitti ? ' is-out' : '') + (u.gizli ? ' is-hidden' : ''), 'data-name': sub ? u.araBaslik : u.ad });
		var handle = h('span', { class: 'flxq-handle', title: t('Sürükleyerek taşı'), 'aria-hidden': 'true', text: '⋮⋮', onmousedown: function () { li.draggable = true; } });
		li.addEventListener('dragstart', function (e) {
			e.stopPropagation();
			drag = { type: 'item', ci: ci, ii: ii };
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData('text/plain', 'item'); } catch (er) { /* yok */ }
			li.classList.add('is-dragging');
		});
		li.addEventListener('dragend', function () { li.draggable = false; li.classList.remove('is-dragging'); clearDrop(); drag = null; });
		li.addEventListener('dragover', function (e) {
			if (!drag || drag.type !== 'item') return;
			e.preventDefault();
			e.stopPropagation();
			clearDrop();
			var r = li.getBoundingClientRect();
			li.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
		});
		li.addEventListener('drop', function (e) {
			if (!drag || drag.type !== 'item') return;
			e.preventDefault();
			e.stopPropagation();
			var after = li.classList.contains('drop-after');
			var src = S.data.kategoriler[drag.ci].urunler;
			var it = src.splice(drag.ii, 1)[0];
			var to = ii + (after ? 1 : 0);
			if (drag.ci === ci && drag.ii < to) to--;
			k.urunler.splice(to, 0, it);
			drag = null;
			clearDrop();
			renderMenu();
			changed();
		});

		function setFlag(f, btn) {
			u[f] = !u[f];
			if (!u[f] && f === 'gizli') delete u.gizli;
			btn.classList.toggle('is-on', !!u[f]);
			btn.setAttribute('aria-pressed', u[f] ? 'true' : 'false');
			li.classList.toggle(f === 'bitti' ? 'is-out' : 'is-hidden', !!u[f]);
			refreshBadges(sec, k);
			changed();
		}
		var hideBtn = toggleBtn(t('Gizli'), !!u.gizli, 'is-hide', function () { setFlag('gizli', hideBtn); });
		var moveBtns = [
			h('button', { type: 'button', class: 'flxq-icon-btn', title: t('Yukarı taşı'), 'aria-label': t('Yukarı taşı'), disabled: ii === 0, text: '↑', onclick: function () { move(k.urunler, ii, ii - 1); rerenderCat(sec, k, ci); changed(); } }),
			h('button', { type: 'button', class: 'flxq-icon-btn', title: t('Aşağı taşı'), 'aria-label': t('Aşağı taşı'), disabled: ii === k.urunler.length - 1, text: '↓', onclick: function () { move(k.urunler, ii, ii + 1); rerenderCat(sec, k, ci); changed(); } })
		];
		var del = function () {
			if (!confirm(fmt(t('"%s" silinsin mi?'), (sub ? u.araBaslik : u.ad) || t('(adsız ürün)')))) return;
			k.urunler.splice(ii, 1);
			rerenderCat(sec, k, ci);
			changed();
		};

		if (sub) {
			li.appendChild(h('div', { class: 'flxq-item-row' }, [
				handle,
				h('span', { class: 'flxq-sub-label', text: t('Ara başlık') }),
				textInput(u.araBaslik, function (v) { u.araBaslik = v; li.setAttribute('data-name', v); }, { class: 'flxq-input flxq-item-name', 'aria-label': t('Ara başlık') }),
				h('span', { class: 'flxq-toggles' }, [hideBtn]),
				h('span', { class: 'flxq-icon-btns' }, moveBtns.concat([h('button', { type: 'button', class: 'flxq-icon-btn is-danger', title: t('Sil'), 'aria-label': t('Sil'), text: '✕', onclick: del })]))
			]));
			return li;
		}

		var thumb = itemThumb(u, function () { return refs.gorselIn; });
		var refs = {};
		var outBtn = toggleBtn(t('Tükendi'), !!u.bitti, 'is-out', function () { setFlag('bitti', outBtn); });
		var priceIn = textInput(priceText(u.fiyat), function (v) { u.fiyat = parsePrice(v); }, { class: 'flxq-input flxq-price', inputmode: 'decimal', 'aria-label': t('Fiyat'), placeholder: t('Fiyat') });
		priceIn.addEventListener('change', function () { priceIn.value = priceText(u.fiyat); });
		var nameIn = textInput(u.ad, function (v) { u.ad = v; li.setAttribute('data-name', v); }, { class: 'flxq-input flxq-item-name', 'aria-label': t('Ürün adı'), placeholder: t('Ürün adı') });
		var expand = h('button', { type: 'button', class: 'flxq-icon-btn flxq-expand', title: t('Tüm alanlar'), 'aria-label': t('Tüm alanlar'), 'aria-expanded': open ? 'true' : 'false', onclick: function () {
			if (openItems.has(u)) openItems.delete(u); else openItems.add(u);
			li.replaceWith(renderItem(k, ci, u, ii, sec));
		} }, h('span', { class: 'flxq-caret', 'aria-hidden': 'true' }));

		li.appendChild(h('div', { class: 'flxq-item-row' }, [
			handle, thumb.el,
			h('span', { class: 'flxq-item-main' }, [nameIn, priceIn]),
			h('span', { class: 'flxq-toggles' }, [outBtn, hideBtn]),
			expand
		]));
		if (open) li.appendChild(renderItemDetails(k, ci, u, ii, sec, li, thumb, moveBtns, del, nameIn, priceIn, refs));
		return li;
	}

	/* ---------- Ürün görseli: satırdaki kutu ve ortam kütüphanesi ---------- */

	var CAMERA = '<svg class="flxq-thumb-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.6-2.2h6.8L17 8h3v11H4z"/><circle cx="12" cy="13.2" r="3.4"/></svg>';

	/** Ortam kütüphanesinden görsel seçtirir; yükleme yetkisi yoksa adres sorar. done(url) */
	function pickImage(current, done) {
		var canUpload = !S.menu.can || S.menu.can.upload !== false;
		if (!canUpload || !window.wp || !wp.media) {
			if (canUpload) toast(t('Ortam kütüphanesi yüklenemedi.'), true);
			var v = window.prompt(t('Görselin adresi (https://…)'), current || '');
			if (v !== null && v.trim() !== current) done(v.trim());
			return;
		}
		var frame = wp.media({ title: t('Görsel seç'), library: { type: 'image' }, multiple: false, button: { text: t('Seç') } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var sz = a.sizes && (a.sizes.large || a.sizes.medium_large || a.sizes.full);
			done(sz ? sz.url : a.url);
		});
		frame.open();
	}

	/**
	 * Satırdaki görsel kutusu: tıklayınca görsel seçilir, × ile kaldırılır.
	 * input() açık ayrıntılardaki görsel alanını döndürür (yoksa null).
	 */
	function itemThumb(u, input) {
		var wrap = h('span', { class: 'flxq-thumb' });
		var btn = h('button', { type: 'button', class: 'flxq-thumb-btn', onclick: function () {
			pickImage(u.gorsel || '', function (url) { set(url); });
		} });
		var x = h('button', { type: 'button', class: 'flxq-thumb-x', title: t('Görseli kaldır'), 'aria-label': t('Görseli kaldır'), text: '✕', onclick: function () { set(''); } });
		wrap.appendChild(btn);
		wrap.appendChild(x);
		function draw() {
			var src = imgUrl(u.gorsel);
			btn.innerHTML = CAMERA;
			wrap.classList.toggle('is-empty', !src);
			x.hidden = !u.gorsel;
			var label = u.gorsel ? t('Görseli değiştir') : t('Görsel ekle');
			btn.setAttribute('aria-label', label);
			btn.title = label;
			if (src) btn.insertBefore(h('img', { src: src, alt: '', loading: 'lazy', onerror: function (e) { e.target.remove(); wrap.classList.add('is-empty'); } }), btn.firstChild);
		}
		function set(url) {
			if (url) u.gorsel = url;
			else if (Object.prototype.hasOwnProperty.call(u, 'gorsel')) u.gorsel = '';
			var inp = input && input();
			if (inp) inp.value = u.gorsel || '';
			draw();
			changed();
		}
		draw();
		return { el: wrap, draw: draw, set: set };
	}

	var KNOWN = ['ad', 'aciklama', 'fiyat', 'eskiFiyat', 'birim', 'gorsel', 'bitti', 'gizli', 'rozet', 'link', 'buyukGorsel', 'bittiNot'];

	function renderItemDetails(k, ci, u, ii, sec, li, thumb, moveBtns, del, nameIn, priceIn, refs) {
		var d = h('div', { class: 'flxq-item-details' });
		function opt(key) {
			return function (v) { if (v === '' || v === null) delete u[key]; else u[key] = v; };
		}
		var gorselIn = textInput(u.gorsel, function (v) { u.gorsel = v; thumb.draw(); }, { class: 'flxq-input', placeholder: 'https://… ' + t('ya da') + ' img/x.jpg' });
		refs.gorselIn = gorselIn;
		var pick = h('button', { type: 'button', class: 'flxq-btn', text: S.menu.can && S.menu.can.upload === false ? t('Adres gir') : t('Ortamdan seç'), onclick: function () {
			pickImage(u.gorsel || '', thumb.set);
		} });
		var rozetMode = u.rozet === true ? 'default' : (typeof u.rozet === 'string' && u.rozet ? 'custom' : 'none');
		var rozetText = textInput(rozetMode === 'custom' ? u.rozet : '', function (v) { u.rozet = v || true; }, { class: 'flxq-input', placeholder: t('ör. Yeni'), maxlength: 40 });
		rozetText.hidden = rozetMode !== 'custom';
		var rozetSel = h('select', { class: 'flxq-input', onchange: function (e) {
			var m = e.target.value;
			if (m === 'none') { if (Object.prototype.hasOwnProperty.call(u, 'rozet')) u.rozet = false; }
			else if (m === 'default') u.rozet = true;
			else { u.rozet = rozetText.value || t('Yeni'); rozetText.value = u.rozet; }
			rozetText.hidden = m !== 'custom';
			changed();
		} }, [
			h('option', { value: 'none', text: t('Yok'), selected: rozetMode === 'none' ? 'selected' : null }),
			h('option', { value: 'default', text: t('Varsayılan rozet'), selected: rozetMode === 'default' ? 'selected' : null }),
			h('option', { value: 'custom', text: t('Özel metin'), selected: rozetMode === 'custom' ? 'selected' : null })
		]);
		var big = h('input', { type: 'checkbox', checked: !!u.buyukGorsel, onchange: function (e) { if (e.target.checked) u.buyukGorsel = true; else delete u.buyukGorsel; changed(); } });
		var desc = h('textarea', { class: 'flxq-input', rows: 2, oninput: function (e) { u.aciklama = e.target.value; changed(); } });
		desc.value = u.aciklama || '';
		var moveSel = S.data.kategoriler.length > 1 ? h('select', { class: 'flxq-input flxq-move-cat', 'aria-label': t('Kategoriye taşı'), onchange: function (e) {
			var to = +e.target.value;
			if (isNaN(to) || to === ci) return;
			k.urunler.splice(ii, 1);
			S.data.kategoriler[to].urunler = S.data.kategoriler[to].urunler || [];
			S.data.kategoriler[to].urunler.push(u);
			renderMenu();
			changed();
		} }, [h('option', { value: '', text: t('Kategoriye taşı…') })].concat(S.data.kategoriler.map(function (c, j) {
			return j === ci ? null : h('option', { value: String(j), text: c.baslik || c.id });
		}))) : null;

		d.appendChild(h('div', { class: 'flxq-grid' }, [
			field(t('Açıklama'), desc, 'is-wide'),
			field(t('Eski fiyat'), textInput(priceText(u.eskiFiyat), function (v) { var p = parsePrice(v); if (p === null) delete u.eskiFiyat; else u.eskiFiyat = p; }, { inputmode: 'decimal', placeholder: t('üstü çizili gösterilir') })),
			field(t('Birim'), textInput(u.birim, opt('birim'), { placeholder: t('ör. 1 adet') })),
			h('div', { class: 'flxq-field is-wide' }, [h('span', { text: t('Görsel') }), h('div', { class: 'flxq-row' }, [gorselIn, pick])]),
			h('div', { class: 'flxq-field' }, [h('span', { text: t('Rozet') }), h('div', { class: 'flxq-row' }, [rozetSel, rozetText])]),
			field(t('Bağlantı'), textInput(u.link, opt('link'), { type: 'url', placeholder: 'https://' })),
			field(t('Tükendi notu'), textInput(u.bittiNot, opt('bittiNot'), { placeholder: t('ör. Yoğun ilgiden dolayı tükendi.') }), 'is-wide'),
			h('label', { class: 'flxq-switch' }, [big, h('span', { text: t('Büyük görsel') })])
		]));
		var extra = Object.keys(u).filter(function (x) { return KNOWN.indexOf(x) === -1; });
		if (extra.length) d.appendChild(h('p', { class: 'flxq-muted flxq-small', text: fmt(t('Diğer alanlar korunur: %s'), extra.join(', ')) }));
		d.appendChild(h('div', { class: 'flxq-item-actions' }, moveBtns.concat([
			moveSel,
			h('button', { type: 'button', class: 'flxq-btn', text: t('Kopyala'), onclick: function () {
				var c = JSON.parse(JSON.stringify(u));
				c.ad = fmt(t('%s (kopya)'), u.ad);
				k.urunler.splice(ii + 1, 0, c);
				openItems.add(c);
				rerenderCat(sec, k, ci);
				changed();
			} }),
			h('span', { class: 'flxq-grow' }),
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-danger', text: t('Sil'), onclick: del })
		])));
		return d;
	}

	/* ---------- Ayarlar sekmesi ---------- */

	function renderSettings() {
		var p = panel('settings');
		p.innerHTML = '';
		var e = S.data.etkinlik = S.data.etkinlik || { baslik: '' };
		// Menü tarihe göre kendiliğinden kapanmaz: yalnız bu kutu karar verir (kapanis false = açık, true = teşekkür sayfası).
		if (e.kapanis !== true) e.kapanis = false;
		var activeBox = h('input', { type: 'checkbox', checked: e.kapanis !== true, onchange: function (ev) {
			e.kapanis = !ev.target.checked;
			changed();
		} });
		var kapSel = h('div', null, [
			h('label', { class: 'flxq-switch' }, [activeBox, h('span', { text: t('Menü aktif') })]),
			h('p', { class: 'flxq-muted', text: t('Kapalıyken ziyaretçiler menü yerine teşekkür sayfasını görür. Menü tarihe göre kendiliğinden kapanmaz.') })
		]);
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Etkinlik') }),
			h('div', { class: 'flxq-grid' }, [
				field(t('Başlık'), textInput(e.baslik, function (v) { e.baslik = v; })),
				field(t('Slogan'), textInput(e.slogan, function (v) { if (v) e.slogan = v; else delete e.slogan; })),
				field(t('Site'), textInput(e.site, function (v) { if (v) e.site = v; else delete e.site; }, { placeholder: 'example.org' })),
				field(t('Durum'), kapSel)
			])
		]));

		e.gunler = Array.isArray(e.gunler) ? e.gunler : [];
		var days = h('div', { class: 'flxq-days' });
		function drawDays() {
			days.innerHTML = '';
			e.gunler.forEach(function (g, i) {
				var lab = textInput(g.etiket, function (v) { g.etiket = v; }, { placeholder: t('ör. 9 Ekim'), 'aria-label': t('Etiket') });
				var date = h('input', { type: 'date', class: 'flxq-input', value: g.tarih || '', 'aria-label': t('Tarih'), onchange: function (ev) {
					g.tarih = ev.target.value;
					if (!g.etiket && g.tarih) {
						try { g.etiket = new Intl.DateTimeFormat(B.locale, { day: 'numeric', month: 'long' }).format(new Date(g.tarih + 'T12:00:00')); lab.value = g.etiket; } catch (er) { /* yok */ }
					}
					changed();
				} });
				days.appendChild(h('div', { class: 'flxq-row flxq-day' }, [lab, date, h('button', { type: 'button', class: 'flxq-icon-btn is-danger', title: t('Sil'), 'aria-label': t('Sil'), text: '✕', onclick: function () { e.gunler.splice(i, 1); drawDays(); changed(); } })]));
			});
		}
		drawDays();
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Günler') }),
			h('p', { class: 'flxq-muted', text: t('Menü sitesi bugünü ve son günü bu tarihlere göre gösterir.') }),
			days,
			h('button', { type: 'button', class: 'flxq-btn', text: '＋ ' + t('Gün ekle'), onclick: function () { e.gunler.push({ etiket: '', tarih: '' }); drawDays(); changed(); } })
		]));

		var info = h('textarea', { class: 'flxq-input', rows: 4, oninput: function (ev) {
			S.data.genelBilgi = ev.target.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
			changed();
		} });
		info.value = (S.data.genelBilgi || []).join('\n');
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Genel bilgi') }),
			h('p', { class: 'flxq-muted', text: t('Menünün altında görünür. Her satır ayrı bir madde.') }),
			info
		]));
	}

	/* ---------- Bağlantı sekmesi ---------- */

	var newKey = '';

	function renderConnect() {
		var p = panel('connect');
		if (!p || !(S.menu.can && S.menu.can.admin)) return;
		p.innerHTML = '';
		var m = S.menu;
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Durum') }),
			h('div', { class: 'flxq-row' }, [
				h('span', { class: 'flxq-pill ' + (m.enabled ? 'is-on' : 'is-off'), text: m.enabled ? t('Yayında') : t('Kapalı') }),
				h('span', { class: 'flxq-muted', text: m.enabled ? t('Menü siteleri veriyi alabiliyor.') : t('Menü siteleri "yayından kaldırıldı" yanıtı alır.') }),
				h('span', { class: 'flxq-grow' }),
				h('button', { type: 'button', class: 'flxq-btn' + (m.enabled ? ' flxq-btn-danger' : ' flxq-btn-primary'), text: m.enabled ? t('Yayından kaldır') : t('Yayına al'), onclick: function (ev) {
					ev.currentTarget.disabled = true;
					api('/toggle', 'POST', { enabled: !m.enabled }).then(function (r) {
						S.menu = Object.assign(r.menu, {});
						renderState();
						renderConnect();
						toast(S.menu.enabled ? t('Menü yayına alındı') : t('Menü yayından kaldırıldı'));
					}).catch(function (e) { toast(e.message, true); renderConnect(); });
				} })
			])
		]));

		var keyBox = null;
		if (newKey) {
			keyBox = h('div', { class: 'flxq-newkey' }, [
				h('p', null, h('strong', { text: t('Yeni anahtar: bu anahtar yalnız şimdi gösterilir. Kopyalayıp menü sitesinin paneline girin.') })),
				h('div', { class: 'flxq-row' }, [
					h('code', { class: 'flxq-code-box', id: 'flxq-newkey', text: newKey }),
					h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', text: t('Kopyala'), onclick: function (ev) { copy(newKey, ev.currentTarget); } })
				])
			]);
		}
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Bağlantı bilgileri') }),
			h('div', { class: 'flxq-kv' }, [
				h('span', { class: 'flxq-k', text: t('Bağlantı adresi') }),
				h('div', { class: 'flxq-row' }, [h('code', { class: 'flxq-code-box', text: B.home }), h('button', { type: 'button', class: 'flxq-btn', text: t('Kopyala'), onclick: function (ev) { copy(B.home, ev.currentTarget); } })]),
				h('span', { class: 'flxq-k', text: t('Anahtar') }),
				h('div', { class: 'flxq-row' }, [
					m.key.has
						? h('span', null, [h('code', { text: 'flxq_…' + m.key.last4 }), ' ', h('span', { class: 'flxq-muted', text: fmt(t('oluşturuldu: %s'), dateTime(m.key.created)) })])
						: h('span', { class: 'flxq-tag is-warn', text: t('Anahtar yok') }),
					h('button', { type: 'button', class: 'flxq-btn' + (m.key.has ? '' : ' flxq-btn-primary'), id: 'flxq-genkey', text: t('Yeni anahtar oluştur'), onclick: function (ev) {
						if (m.key.has && !confirm(t('Yeni anahtar oluşturulunca eski anahtar hemen geçersiz olur ve bağlı menü siteleri yeni anahtar girilene kadar veri alamaz. Devam edilsin mi?'))) return;
						ev.currentTarget.disabled = true;
						api('/key', 'POST', {}).then(function (r) {
							newKey = r.key;
							S.menu = r.menu;
							renderConnect();
							if (S.tab === 'history') loadHistory(1);
						}).catch(function (e) { toast(e.message, true); renderConnect(); });
					} })
				])
			]),
			keyBox
		]));

		var sites = m.sites || [];
		var table = sites.length ? h('table', { class: 'flxq-table flxq-sites' }, [
			h('thead', null, h('tr', null, [t('Site'), t('İstemci'), t('Son görülme'), t('Anlık bildirim'), ''].map(function (x) { return h('th', { text: x }); }))),
			h('tbody', null, sites.map(function (s) {
				return h('tr', null, [
					h('td', { 'data-label': t('Site') }, s.url ? h('a', { href: s.url, target: '_blank', rel: 'noopener noreferrer', text: s.url.replace(/^https?:\/\//, '') }) : h('span', { class: 'flxq-muted', text: s.ip })),
					h('td', { 'data-label': t('İstemci'), text: s.client || '—' }),
					h('td', { 'data-label': t('Son görülme'), title: dateTime(s.last), text: fmt(t('%s önce'), s.ago) }),
					h('td', { 'data-label': t('Anlık bildirim') }, h('span', { class: 'flxq-tag' + (s.hook ? ' is-ok' : ''), text: s.hook ? t('Var') : t('Yok') })),
					h('td', { class: 'flxq-td-act' }, h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Kaldır'), onclick: function (ev) {
						if (!confirm(t('Site listeden kaldırılsın mı? Anahtar geçerli kaldıkça bir sonraki istekte yeniden görünür; erişimi kesmek için yeni anahtar oluşturun.'))) return;
						ev.currentTarget.disabled = true;
						api('/sites/remove', 'POST', { site: s.id }).then(function (r) { S.menu = r.menu; renderConnect(); }).catch(function (e) { toast(e.message, true); });
					} }))
				]);
			}))
		]) : h('p', { class: 'flxq-muted', text: m.key.has ? t('Henüz bağlanan site yok. Menü sitesi ilk veri isteğinde burada görünür.') : t('Önce bir anahtar oluşturun.') });
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('div', { class: 'flxq-row' }, [h('h2', { text: t('Bağlı siteler') }), h('span', { class: 'flxq-grow' }), h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Yenile'), onclick: function () { api('', 'GET').then(function (r) { S.menu.sites = r.sites; S.menu.base = r.base; renderConnect(); }); } })]),
			table
		]));

		p.appendChild(h('div', { class: 'flxq-panel flxq-howto' }, [
			h('h2', { text: t('Menü sitesini bağlama') }),
			h('ol', null, [
				h('li', { text: t('Menü sitesini (qr-menu) istediğiniz alan adına kurun ve ilk açılışta yönetici anahtarını belirleyin.') }),
				h('li', { text: t('Menü sitesinde /admin/ sayfasını açın.') }),
				h('li', { text: t('Bağlantı adresi olarak yukarıdaki adresi, anahtar olarak bu menünün anahtarını girin ve kaydedin.') }),
				h('li', { text: t('Site bu listede görünür. Kaydettiğiniz her değişiklik anlık bildirimle menü sitesine iletilir; bildirim ulaşmazsa site yaklaşık bir dakika içinde kendisi kontrol eder.') })
			])
		]));
	}

	/* ---------- Paylaş sekmesi ---------- */

	var shareUrl = '';
	if (window.qrcode && qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

	function qrMatrix(text) {
		var q = qrcode(0, 'M');
		q.addData(text);
		q.make();
		return q;
	}
	function qrSvg(text) {
		var q = qrMatrix(text), n = q.getModuleCount(), m = 4, size = n + m * 2, d = '';
		for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += 'M' + (c + m) + ' ' + (r + m) + 'h1v1h-1z';
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size * 8 + '" height="' + size * 8 + '" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="' + size + '" height="' + size + '" fill="#fff"/><path fill="#000" d="' + d + '"/></svg>';
	}
	function download(blob, name) {
		var a = h('a', { href: URL.createObjectURL(blob), download: name });
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
	}
	function qrName(ext) {
		var m = S.menu;
		return 'qr-' + (m.slug || (m.host ? m.host.split('.')[0] : '') || 'menu-' + m.id) + '.' + ext;
	}
	function qrPng(text) {
		var q = qrMatrix(text), n = q.getModuleCount(), m = 4, sc = 16, size = (n + m * 2) * sc;
		var cv = document.createElement('canvas');
		cv.width = cv.height = size;
		var x = cv.getContext('2d');
		x.fillStyle = '#fff';
		x.fillRect(0, 0, size, size);
		x.fillStyle = '#000';
		for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) x.fillRect((c + m) * sc, (r + m) * sc, sc, sc);
		cv.toBlob(function (b) { if (b) download(b, qrName('png')); }, 'image/png');
	}

	function renderShare() {
		var p = panel('share');
		if (!p) return;
		p.innerHTML = '';
		var m = S.menu, urls = (m.urls || []).map(function (u) { return u.url; });
		var labels = { host: t('Alt alan adı'), slug: t('Adres'), front: t('Ana sayfa') };
		if (urls.indexOf(shareUrl) === -1) shareUrl = urls[0] || '';

		var list = urls.length ? h('div', { class: 'flxq-urls' }, (m.urls || []).map(function (u) {
			return h('div', { class: 'flxq-row flxq-url-row' }, [
				h('span', { class: 'flxq-tag', text: labels[u.type] || u.type }),
				h('code', { class: 'flxq-code-box', text: u.url }),
				h('button', { type: 'button', class: 'flxq-btn', text: t('Kopyala'), onclick: function (ev) { copy(u.url, ev.currentTarget); } }),
				h('a', { class: 'flxq-btn', href: u.url, target: '_blank', rel: 'noopener', text: t('Aç') + ' ↗' })
			]);
		})) : h('p', { class: 'flxq-muted', text: m.can && m.can.address ? t('Bu menünün henüz bu sitede bir adresi yok. Aşağıdan bir adres verin.') : t('Bu menünün henüz bu sitede bir adresi yok. Adres vermesi için site yöneticisine başvurun.') });
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('h2', { text: t('Menünün adresi') }),
			m.enabled ? null : h('p', null, h('span', { class: 'flxq-tag is-warn', text: t('Menü yayında değil: adreste "Menü yayında değil" sayfası açılır.') })),
			list
		]));

		if (urls.length) {
			var box = h('div', { class: 'flxq-qr', id: 'flxq-qr' });
			box.innerHTML = qrSvg(shareUrl);
			var sel = urls.length > 1 ? h('select', { class: 'flxq-input', 'aria-label': t('QR kodun adresi'), onchange: function (e) { shareUrl = e.target.value; renderShare(); } },
				urls.map(function (u) { return h('option', { value: u, selected: u === shareUrl ? 'selected' : null, text: u }); })) : null;
			p.appendChild(h('div', { class: 'flxq-panel flxq-qr-panel' }, [
				h('h2', { text: t('QR kod') }),
				h('div', { class: 'flxq-qr-wrap' }, [
					box,
					h('div', { class: 'flxq-qr-side' }, [
						sel || h('p', null, h('code', { text: shareUrl })),
						h('div', { class: 'flxq-row' }, [
							h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', id: 'flxq-qr-svg', text: t('SVG indir'), onclick: function () { download(new Blob([qrSvg(shareUrl)], { type: 'image/svg+xml' }), qrName('svg')); } }),
							h('button', { type: 'button', class: 'flxq-btn', id: 'flxq-qr-png', text: t('PNG indir'), onclick: function () { qrPng(shareUrl); } })
						]),
						h('p', { class: 'flxq-muted', text: t('Baskı için SVG önerilir (her boyutta net). Kodun etrafındaki beyaz boşluğu kesmeyin.') })
					])
				])
			]));
		}

		if (m.can && m.can.address) {
			var slugIn = h('input', { type: 'text', class: 'flxq-input flxq-mono', id: 'flxq-slug', value: m.slug || '', placeholder: 'menu', pattern: '[a-z0-9-]*', maxlength: 60, autocomplete: 'off', oninput: function (e) { var v = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''); if (v !== e.target.value) e.target.value = v; } });
			var hostIn = h('input', { type: 'text', class: 'flxq-input flxq-mono', id: 'flxq-host', value: m.host || '', placeholder: 'menu1.' + (B.homeHost || 'alanadi.com'), maxlength: 253, autocomplete: 'off' });
			var msg = h('div', { class: 'flxq-addr-msg', role: 'status' });
			var homeShow = String(B.home || '').replace(/^https?:\/\//, '').replace(/\/$/, '');
			p.appendChild(h('div', { class: 'flxq-panel' }, [
				h('h2', { text: t('Adres ayarı') }),
				field(t('Adres'), h('div', { class: 'flxq-affix' }, [h('span', { class: 'flxq-muted', text: homeShow + '/' }), slugIn, h('span', { class: 'flxq-muted', text: '/' })])),
				h('p', { class: 'flxq-muted', text: t('Küçük harf, rakam ve tire. Boş bırakılırsa bu yolda menü açılmaz.') }),
				field(t('Alt alan adı'), hostIn),
				h('p', { class: 'flxq-muted', text: t('İsteğe bağlı. Alt alan adının hostingde bu siteyle aynı klasöre yönlenmesi (ör. *.alanadi.com joker alt alan adı) ve SSL sertifikasının onu da kapsaması gerekir.') }),
				h('div', { class: 'flxq-row' }, [
					h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', id: 'flxq-addr-save', text: t('Adresi kaydet'), onclick: function (ev) {
						var btn = ev.currentTarget;
						btn.disabled = true;
						msg.innerHTML = '';
						api('/address', 'POST', { slug: slugIn.value.trim(), host: hostIn.value.trim() }).then(function (r) {
							S.menu = r.menu;
							renderShare();
							var mm = panel('share').querySelector('.flxq-addr-msg');
							if (mm) (r.warnings || []).forEach(function (w) { mm.appendChild(h('p', { class: 'flxq-tag is-warn', text: w })); });
							toast(r.changed ? t('Adres kaydedildi.') : t('Değişiklik yok.'));
							if (S.tab === 'history') loadHistory(1);
						}).catch(function (e) {
							btn.disabled = false;
							msg.appendChild(h('p', { class: 'flxq-error', text: e.message }));
						});
					} })
				]),
				msg
			]));
		}

		if (m.can && m.can.admin) {
			var mine = (m.editors || []).map(function (e) { return e.id; });
			var boxes = (B.managers || []).map(function (u) {
				return h('label', { class: 'flxq-check' }, [
					h('input', { type: 'checkbox', value: String(u.id), checked: mine.indexOf(u.id) !== -1, 'data-user': u.login }),
					' ' + u.name + ' ',
					h('small', { class: 'flxq-muted', text: '(' + u.login + ')' }),
					u.id === m.author ? h('span', { class: 'flxq-tag', text: t('menüyü açan') }) : null
				]);
			});
			p.appendChild(h('div', { class: 'flxq-panel flxq-editors' }, [
				h('h2', { text: t('Düzenleyenler') }),
				h('p', { class: 'flxq-muted', text: fmt(t('Menüyü açan: %s. Seçili menü yöneticileri bu menüyü görüp düzenleyebilir; diğer menüleri göremez.'), m.author_name || '—') }),
				boxes.length ? h('div', { class: 'flxq-checks' }, boxes) : h('p', null, [
					t('Henüz menü yöneticisi yok.') + ' ',
					B.usersUrl ? h('a', { href: B.usersUrl, text: t('Kullanıcı ekleyin (rol: Menü yöneticisi).') }) : null
				]),
				boxes.length ? h('div', { class: 'flxq-row' }, [
					h('button', { type: 'button', class: 'flxq-btn', id: 'flxq-editors-save', text: t('Düzenleyenleri kaydet'), onclick: function (ev) {
						var ids = [];
						panel('share').querySelectorAll('.flxq-editors input[type=checkbox]:checked').forEach(function (c) { ids.push(+c.value); });
						ev.currentTarget.disabled = true;
						api('/editors', 'POST', { users: ids }).then(function (r) {
							S.menu = r.menu;
							renderShare();
							toast(r.changed ? t('Düzenleyenler kaydedildi.') : t('Değişiklik yok.'));
						}).catch(function (e) { toast(e.message, true); renderShare(); });
					} })
				]) : null
			]));
		}
	}

	/* ---------- Geçmiş sekmesi ---------- */

	function actionLabel(a) {
		var map = { create: t('Oluşturuldu'), save: t('Kaydedildi'), restore: t('Geri dönüldü'), import: t('İçe aktarıldı'), key: t('Anahtar'), toggle: t('Durum'), mcp: t('Yapay zekâ (MCP)'), address: t('Adres'), editors: t('Düzenleyenler'), images: t('Görseller') };
		return map[a] || a;
	}

	function loadHistory(page) {
		var p = panel('history');
		S.hist.page = page;
		if (!p.firstChild) p.appendChild(h('p', { class: 'flxq-muted', text: t('Yükleniyor…') }));
		api('/history', 'GET', { page: page }).then(function (r) { renderHistory(r); }).catch(function (e) {
			p.innerHTML = '';
			p.appendChild(h('p', { class: 'flxq-error', text: e.message }));
		});
	}

	function renderHistory(r) {
		var p = panel('history');
		p.innerHTML = '';
		var rows = r.rows || [];
		var head = h('div', { class: 'flxq-row' }, [
			h('h2', { text: t('Değişiklik geçmişi') }),
			h('span', { class: 'flxq-muted', text: fmt(t('%s kayıt · menü başına son 300 kayıt tutulur'), r.total) }),
			h('span', { class: 'flxq-grow' }),
			h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Yenile'), onclick: function () { loadHistory(S.hist.page); } })
		]);
		var list = h('ol', { class: 'flxq-history' }, rows.map(function (row) {
			var li = h('li', { class: 'flxq-rev is-' + row.action, 'data-hid': row.id });
			var viewBox = h('div', { class: 'flxq-rev-view', hidden: true });
			var current = row.version === S.base && row.has_data && rows.filter(function (x) { return x.has_data; })[0] === row && r.page === 1;
			li.appendChild(h('div', { class: 'flxq-rev-head' }, [
				h('span', { class: 'flxq-rev-ver', text: 'v' + row.version }),
				h('span', { class: 'flxq-tag flxq-act', text: actionLabel(row.action) }),
				h('span', { class: 'flxq-rev-meta', text: row.time_str + (row.user ? ' · ' + row.user : '') }),
				current ? h('span', { class: 'flxq-tag is-ok', text: t('Şu anki sürüm') }) : null
			]));
			li.appendChild(h('p', { class: 'flxq-rev-sum', text: row.summary || '—' }));
			var acts = h('div', { class: 'flxq-row' });
			if (row.has_data) {
				acts.appendChild(h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Görüntüle'), onclick: function () {
					if (!viewBox.hidden) { viewBox.hidden = true; return; }
					viewBox.hidden = false;
					viewBox.innerHTML = '';
					viewBox.appendChild(h('p', { class: 'flxq-muted', text: t('Yükleniyor…') }));
					api('/history/' + row.id, 'GET').then(function (d) {
						viewBox.innerHTML = '';
						var data = JSON.parse(d.data_json);
						viewBox.appendChild(readable(data));
						var pre = h('pre', { class: 'flxq-pre', hidden: true, text: JSON.stringify(data, null, 2) });
						viewBox.appendChild(h('div', { class: 'flxq-row' }, [
							h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('JSON\'u göster'), onclick: function () { pre.hidden = !pre.hidden; } }),
							h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('JSON\'u kopyala'), onclick: function (ev) { copy(JSON.stringify(data, null, 2), ev.currentTarget); } })
						]));
						viewBox.appendChild(pre);
					}).catch(function (e) { viewBox.innerHTML = ''; viewBox.appendChild(h('p', { class: 'flxq-error', text: e.message })); });
				} }));
				if (!current) {
					acts.appendChild(h('button', { type: 'button', class: 'flxq-btn flxq-btn-small', text: t('Bu sürüme dön'), onclick: function (ev) {
						var msg = isDirty()
							? t('Kaydedilmemiş değişiklikleriniz silinecek ve menü bu sürüme döndürülecek (yeni sürüm olarak kaydedilir). Devam edilsin mi?')
							: fmt(t('Menü v%s sürümüne döndürülsün mü? Yeni sürüm olarak kaydedilir ve menü sitelerine bildirilir.'), row.version);
						if (!confirm(msg)) return;
						ev.currentTarget.disabled = true;
						api('/history/' + row.id + '/restore', 'POST', { base_version: S.base }).then(function (res) {
							applyServerMenu(res.menu, false);
							toast(fmt(t('v%1$s sürümüne dönüldü · yeni sürüm v%2$s'), row.version, res.version));
							loadHistory(1);
						}).catch(function (e) {
							ev.currentTarget.disabled = false;
							if (e.status === 409) { showConflict(e.data && e.data.data ? e.data.data : {}); return; }
							toast(e.message, true);
						});
					} }));
				}
			}
			li.appendChild(acts);
			li.appendChild(viewBox);
			return li;
		}));
		p.appendChild(h('div', { class: 'flxq-panel' }, [head, rows.length ? list : h('p', { class: 'flxq-muted', text: t('Henüz kayıt yok.') })]));
		if (r.pages > 1) {
			p.appendChild(h('div', { class: 'flxq-pager' }, [
				h('button', { type: 'button', class: 'flxq-btn', disabled: r.page <= 1, text: '← ' + t('Daha yeni'), onclick: function () { loadHistory(r.page - 1); } }),
				h('span', { class: 'flxq-muted', text: fmt(t('Sayfa %1$s / %2$s'), r.page, r.pages) }),
				h('button', { type: 'button', class: 'flxq-btn', disabled: r.page >= r.pages, text: t('Daha eski') + ' →', onclick: function () { loadHistory(r.page + 1); } })
			]));
		}
	}

	/** Bir sürümün okunur özeti: kategoriler ve ürünler, fiyat ve durumlarıyla. */
	function readable(data) {
		var box = h('div', { class: 'flxq-readable' });
		(data.kategoriler || []).forEach(function (k) {
			box.appendChild(h('h4', { text: k.baslik || k.id }));
			box.appendChild(h('ul', null, (k.urunler || []).map(function (u) {
				if (isSub(u)) return h('li', { class: 'is-sub' + (u.gizli ? ' is-hidden' : ''), text: '— ' + u.araBaslik + ' —' });
				return h('li', { class: (u.bitti ? 'is-out ' : '') + (u.gizli ? 'is-hidden' : '') }, [
					h('span', { text: u.ad || t('(adsız ürün)') }),
					h('span', { class: 'flxq-muted', text: ' ' + priceText(u.fiyat) }),
					u.bitti ? h('span', { class: 'flxq-tag is-warn', text: t('Tükendi') }) : null,
					u.gizli ? h('span', { class: 'flxq-tag', text: t('Gizli') }) : null
				]);
			})));
		});
		return box;
	}

	/* ---------- JSON sekmesi ---------- */

	function renderJson() {
		var p = panel('json');
		p.innerHTML = '';
		var ta = h('textarea', { class: 'flxq-input flxq-code flxq-json', rows: 24, spellcheck: 'false', oninput: function () { S.jsonDirty = true; msg.textContent = ''; } });
		ta.value = JSON.stringify(S.data, null, 2);
		var msg = h('p', { class: 'flxq-json-msg', role: 'status' });
		function parse() {
			var d;
			try { d = JSON.parse(ta.value); } catch (e) {
				msg.className = 'flxq-json-msg flxq-error';
				msg.textContent = fmt(t('Geçersiz JSON: %s'), e.message);
				return null;
			}
			if (d && d.data && d.data.kategoriler) d = d.data;
			if (!d || typeof d !== 'object' || Array.isArray(d) || !Array.isArray(d.kategoriler)) {
				msg.className = 'flxq-json-msg flxq-error';
				msg.textContent = t('"kategoriler" listesi bulunamadı.');
				return null;
			}
			for (var i = 0; i < d.kategoriler.length; i++) {
				var k = d.kategoriler[i];
				if (!k || typeof k !== 'object' || (k.urunler !== undefined && !Array.isArray(k.urunler))) {
					msg.className = 'flxq-json-msg flxq-error';
					msg.textContent = fmt(t('%s. kategori geçersiz.'), i + 1);
					return null;
				}
			}
			return d;
		}
		p.appendChild(h('div', { class: 'flxq-panel' }, [
			h('p', { class: 'flxq-muted', text: t('Menünün ham verisi. Değiştirip "Uygula" deyince düzenleyiciye aktarılır; kalıcı olması için ayrıca kaydedin.') }),
			ta,
			h('div', { class: 'flxq-row' }, [
				h('button', { type: 'button', class: 'flxq-btn', text: t('Doğrula'), onclick: function () {
					var d = parse();
					if (!d) return;
					var n = 0;
					d.kategoriler.forEach(function (k) { (k.urunler || []).forEach(function (u) { if (!isSub(u)) n++; }); });
					msg.className = 'flxq-json-msg flxq-ok';
					msg.textContent = fmt(t('Geçerli: %1$s kategori, %2$s ürün.'), d.kategoriler.length, n);
				} }),
				h('button', { type: 'button', class: 'flxq-btn flxq-btn-primary', text: t('Uygula'), onclick: function () {
					var d = parse();
					if (!d) return;
					S.data = d;
					S.jsonDirty = false;
					renderMenu();
					renderSettings();
					changed();
					ta.value = JSON.stringify(S.data, null, 2);
					msg.className = 'flxq-json-msg flxq-ok';
					msg.textContent = t('Uygulandı. Kaydetmeyi unutmayın.');
				} }),
				h('button', { type: 'button', class: 'flxq-btn', text: t('Kopyala'), onclick: function (ev) { copy(ta.value, ev.currentTarget); } }),
				h('button', { type: 'button', class: 'flxq-btn', text: t('Sıfırla'), onclick: function () { S.jsonDirty = false; renderJson(); } })
			]),
			msg
		]));
	}

	/* ---------- Canlı önizleme ---------- */
	/* Kaydedilmemiş veri /preview ucuna gönderilir (yazmadan ~500 ms sonra, aynı anda tek istek); dönen HTML
	   sandbox'lı bir iframe'e srcdoc olarak yüklenir. Yeni çerçeve arkada yüklenip eski kaydırma konumuna
	   getirildikten sonra öne alınır, böylece sayfa zıplamaz. */

	var PV = {
		box: document.getElementById('flxq-preview'),
		screen: document.getElementById('flxq-phone-screen'),
		phone: document.getElementById('flxq-phone'),
		wrap: document.getElementById('flxq-phone-wrap'),
		status: document.getElementById('flxq-preview-status'),
		link: document.getElementById('flxq-preview-open'),
		toggle: document.getElementById('flxq-preview-toggle'),
		close: document.getElementById('flxq-preview-close'),
		sent: null, timer: 0, busy: false, again: false, stale: true, frame: null, next: null, y: 0
	};
	var wide = window.matchMedia ? window.matchMedia('(min-width: 1100px)') : { matches: true };

	function pvOpen() { return !!PV.box && PV.box.classList.contains('is-open'); }
	function pvVisible() { return !!PV.box && (wide.matches || pvOpen()) && !document.hidden; }
	function pvStatus(text, cls, title) {
		if (!PV.status) return;
		PV.status.className = 'flxq-preview-status' + (cls ? ' ' + cls : '');
		PV.status.textContent = text || '';
		PV.status.title = title || '';
	}
	function previewLink() {
		if (!PV.link) return;
		var u = (S.menu.urls || [])[0];
		PV.link.hidden = !u || !S.menu.enabled || S.menu.status !== 'publish';
		if (u) PV.link.href = u.url;
	}
	function previewSchedule(now) {
		if (!PV.box) return;
		previewLink();
		var json = JSON.stringify(S.data);
		if (json === PV.sent && !now) return;
		clearTimeout(PV.timer);
		if (!pvVisible()) { PV.stale = true; return; }
		pvStatus(t('Güncelleniyor…'), 'is-busy');
		PV.timer = setTimeout(previewFetch, now ? 0 : 500);
	}
	function previewFetch() {
		if (PV.busy) { PV.again = true; return; }
		var json = JSON.stringify(S.data);
		PV.busy = true;
		PV.stale = false;
		api('/preview', 'POST', { data_json: json }).then(function (r) {
			PV.sent = json;
			if (r && typeof r.html === 'string') previewShow(r.html);
		}).catch(function (e) {
			PV.sent = json;
			pvStatus(t('Önizleme güncellenemedi'), 'is-bad', e.message);
		}).then(function () {
			PV.busy = false;
			if (PV.again) { PV.again = false; previewSchedule(); }
		});
	}
	function previewShow(html) {
		var f = document.createElement('iframe');
		f.setAttribute('sandbox', 'allow-scripts');
		f.setAttribute('title', t('Menü önizlemesi'));
		f.setAttribute('tabindex', '0');
		if (PV.frame) f.className = 'is-next';
		if (PV.next) { clearTimeout(PV.next.flxqTimer); PV.next.remove(); }
		PV.next = f;
		f.flxqTimer = setTimeout(function () { previewSwap(f); }, 3000);
		f.srcdoc = html;
		PV.screen.appendChild(f);
	}
	function previewSwap(f) {
		if (f !== PV.next) return;
		clearTimeout(f.flxqTimer);
		if (PV.frame && PV.frame !== f) PV.frame.remove();
		f.classList.remove('is-next');
		PV.frame = f;
		PV.next = null;
		if (!PV.busy && JSON.stringify(S.data) === PV.sent) pvStatus('');
	}
	window.addEventListener('message', function (e) {
		var d = e.data;
		if (!d || typeof d !== 'object' || !d.flxqPreview) return;
		if (PV.next && e.source === PV.next.contentWindow) {
			if (d.loaded) PV.next.contentWindow.postMessage({ flxqScroll: 1, y: PV.y }, '*');
			else if (d.ready) previewSwap(PV.next);
			return;
		}
		if (PV.frame && e.source === PV.frame.contentWindow && typeof d.y === 'number') PV.y = d.y;
	});

	/* Telefon çerçevesi (390×780) ekrana sığacak kadar küçültülür. */
	function previewFit() {
		if (!PV.box) return;
		var open = pvOpen();
		if (!wide.matches && !open) return;
		var bar = PV.box.querySelector('.flxq-preview-bar');
		var note = PV.box.querySelector('.flxq-preview-note');
		var barH = (bar ? bar.offsetHeight : 30) + 8;
		var availH, availW;
		if (open && !wide.matches) {
			availH = window.innerHeight - barH - 24;
			availW = window.innerWidth - 24;
		} else {
			availH = window.innerHeight - 46 - barH - (note ? note.offsetHeight + 8 : 0) - 16;
			availW = 410;
		}
		var sc = Math.max(0.45, Math.min(1, availH / 800, availW / 410));
		PV.phone.style.setProperty('--flxq-ps', String(sc));
		PV.wrap.style.width = Math.round(410 * sc) + 'px';
		PV.wrap.style.height = Math.round(800 * sc) + 'px';
		app.style.setProperty('--flxq-pw', Math.round(410 * sc) + 'px');
	}
	function previewOpen(on) {
		if (!PV.box) return;
		PV.box.classList.toggle('is-open', on);
		document.body.classList.toggle('flxq-preview-on', on);
		if (PV.toggle) PV.toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
		if (on) {
			previewFit();
			if (PV.stale || !PV.frame) previewSchedule(true);
			if (PV.close) PV.close.focus();
		} else if (PV.toggle) PV.toggle.focus();
	}
	if (PV.box) {
		if (PV.toggle) PV.toggle.addEventListener('click', function () { previewOpen(!pvOpen()); });
		if (PV.close) PV.close.addEventListener('click', function () { previewOpen(false); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pvOpen() && !document.querySelector('.media-modal')) previewOpen(false); });
		var fitRaf = 0;
		window.addEventListener('resize', function () { cancelAnimationFrame(fitRaf); fitRaf = requestAnimationFrame(previewFit); });
		var onWide = function () {
			if (wide.matches && pvOpen()) previewOpen(false);
			previewFit();
			if (pvVisible() && (PV.stale || !PV.frame)) previewSchedule(true);
		};
		if (wide.addEventListener) wide.addEventListener('change', onWide); else if (wide.addListener) wide.addListener(onWide);
		document.addEventListener('visibilitychange', function () { if (pvVisible() && PV.stale) previewSchedule(true); });
	}

	/* ---------- Başlat ---------- */

	renderAll();
	renderState();
	changed();
	var start = 'menu';
	try {
		var hash = (location.hash || '').replace('#', '');
		start = ['menu', 'settings', 'share', 'connect', 'history', 'json'].indexOf(hash) !== -1 ? hash : (sessionStorage.getItem('flxq-tab-' + S.menu.id) || 'menu');
	} catch (e) { /* yok */ }
	if (!app.querySelector('.flxq-tabs [data-tab="' + start + '"]')) start = 'menu';
	showTab(start);
	previewFit();
	previewSchedule(true);
}());
