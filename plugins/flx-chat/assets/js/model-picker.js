/* Flux Chat — model seçici (1.17.0). Ayarlar, test sohbeti, AI düzenleyici ve benchmark ortak kullanır.
 * FLXCModelPicker.create({ value, onChange, name, empty, tools, vision }) → element
 * FLXCModelPicker.load(cb) → cb({ models, providers })
 */
(function () {
	'use strict';
	var C = window.FLXC_MP || {}, T = C.i18n || {};
	function t(s, v) {
		s = T[s] || s;
		if (v) Object.keys(v).forEach(function (k) { s = s.split('{' + k + '}').join(v[k]); });
		return s;
	}
	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (text != null) e.textContent = text;
		return e;
	}
	var DATA = null, wait = [];
	function load(cb, fresh) {
		if (DATA && !fresh) return cb(DATA);
		wait.push(cb);
		if (wait.length > 1) return;
		fetch((C.rest || '') + 'admin/ai/models' + (fresh ? '?fresh=1' : ''), { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce || '' } })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || ('HTTP ' + r.status)); return j; }); })
			.then(function (j) { DATA = { models: j.models || [], providers: j.providers || [] }; })
			.catch(function (e) { DATA = { models: [], providers: [], error: e.message }; })
			.then(function () { var w = wait; wait = []; w.forEach(function (f) { f(DATA); }); });
	}
	function provOf(id) { return String(id || '').indexOf('claude:') === 0 ? 'anthropic' : 'openrouter'; }
	function money(v) {
		v = +v || 0;
		if (v === 0) return '$0';
		return '$' + (v < 1 ? v.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') : v.toFixed(2).replace(/\.00$/, ''));
	}
	function priceText(m) { return m.in === 0 && m.out === 0 ? t('Ücretsiz') : money(m.in) + ' / ' + money(m.out); }
	function shortName(m) {
		var n = m.name || m.id;
		return m.family && n.indexOf(m.family + ': ') === 0 ? n.slice(m.family.length + 2) : n;
	}
	function ctxText(c) {
		if (!c) return '';
		return c >= 1000000 ? (Math.round(c / 100000) / 10).toString().replace(/\.0$/, '') + 'M' : Math.round(c / 1000) + 'K';
	}
	function find(id) {
		var l = DATA ? DATA.models : [];
		for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i];
		return null;
	}
	var open = null; // açık seçici (aynı anda bir tane)
	document.addEventListener('mousedown', function (e) { if (open && !open.wrap.contains(e.target)) open.close(); });
	document.addEventListener('keydown', function (e) { if (open && e.key === 'Escape') { open.close(); open = null; } });

	function create(o) {
		o = o || {};
		var value = o.value || '';
		var wrap = el('div', 'flxmp');
		var hidden = null;
		if (o.name) { hidden = el('input'); hidden.type = 'hidden'; hidden.name = o.name; hidden.value = value; wrap.appendChild(hidden); }
		var prov = el('select', 'flxmp-prov');
		prov.setAttribute('aria-label', t('Sağlayıcı'));
		prov.hidden = true;
		var trig = el('button', 'flxmp-trig');
		trig.type = 'button';
		trig.setAttribute('aria-haspopup', 'listbox');
		var tName = el('span', 'flxmp-tn'), tBadge = el('span', 'flxmp-tb'), tArrow = el('span', 'flxmp-ta', '▾');
		trig.appendChild(tName); trig.appendChild(tBadge); trig.appendChild(tArrow);
		var row = el('div', 'flxmp-row');
		row.appendChild(prov); row.appendChild(trig);
		wrap.appendChild(row);

		var pop = el('div', 'flxmp-pop');
		pop.hidden = true;
		var q = el('input', 'flxmp-q'); q.type = 'search'; q.autocomplete = 'off';
		var tabs = el('div', 'flxmp-tabs');
		var pr = el('div', 'flxmp-pr');
		var pMin = el('input', 'flxmp-pn'), pMax = el('input', 'flxmp-pn');
		[pMin, pMax].forEach(function (i) { i.type = 'number'; i.min = '0'; i.step = '0.01'; i.inputMode = 'decimal'; });
		pMin.placeholder = t('en az'); pMax.placeholder = t('en çok');
		pr.appendChild(el('span', null, t('Giriş fiyatı $')));
		pr.appendChild(pMin); pr.appendChild(el('span', null, '–')); pr.appendChild(pMax);
		var clr = el('button', 'flxmp-clr', t('Temizle')); clr.type = 'button'; clr.hidden = true;
		pr.appendChild(clr);
		var list = el('div', 'flxmp-list');
		list.setAttribute('role', 'listbox');
		var foot = el('div', 'flxmp-foot', t('Fiyat: 1M token giriş / çıkış'));
		var close = el('button', 'flxmp-x', '×'); close.type = 'button'; close.setAttribute('aria-label', t('Kapat'));
		var head = el('div', 'flxmp-head'); head.appendChild(q); head.appendChild(close);
		pop.appendChild(head); pop.appendChild(tabs); pop.appendChild(pr); pop.appendChild(list); pop.appendChild(foot);
		wrap.appendChild(pop);

		var tab = 'all', provider = provOf(value || o.def), active = -1;

		function base() {
			return (DATA ? DATA.models : []).filter(function (m) {
				if (m.provider !== provider) return false;
				if (o.tools && !m.tools && m.id !== value) return false;
				if (o.vision && !m.vision && m.id !== value) return false;
				return true;
			});
		}
		// Boş seçenek: "Ana model · Claude Sonnet 5.5" (o.def varsayılan modelin kimliği).
		function emptyLabel() {
			var e = o.empty || t('Model seçin');
			if (!o.def) return e;
			var d = find(o.def);
			return e + ' · ' + (d ? shortName(d) : o.def);
		}
		function paintTrig() {
			var m = find(value);
			tName.textContent = value ? (m ? shortName(m) : value) : emptyLabel();
			tBadge.textContent = m ? priceText(m) : '';
			tBadge.className = 'flxmp-tb' + (m && m.in === 0 && m.out === 0 ? ' is-free' : '');
			tBadge.hidden = !m;
			trig.title = value || '';
		}
		function paintProv() {
			var ps = DATA ? DATA.providers : [];
			prov.innerHTML = '';
			ps.forEach(function (p) { var op = el('option', null, p.label); op.value = p.id; prov.appendChild(op); });
			prov.hidden = ps.length < 2;
			if (!ps.some(function (p) { return p.id === provider; }) && ps.length) provider = ps[0].id;
			prov.value = provider;
		}
		function paintTabs(all) {
			tabs.innerHTML = '';
			var fam = {}, free = 0;
			all.forEach(function (m) { if (m.family) fam[m.family] = (fam[m.family] || 0) + 1; if (m.in === 0 && m.out === 0) free++; });
			var names = Object.keys(fam).sort(function (a, b) { return fam[b] - fam[a] || a.localeCompare(b); });
			var items = [['all', t('Tümü')]];
			if (free) items.push(['free', t('Ücretsiz')]);
			if (names.length > 1) names.forEach(function (n) { items.push(['f:' + n, n]); });
			if (!items.some(function (i) { return i[0] === tab; })) tab = 'all';
			tabs.hidden = items.length < 2;
			items.forEach(function (i) {
				var b = el('button', 'flxmp-tab' + (tab === i[0] ? ' is-on' : ''), i[1]);
				b.type = 'button';
				b.addEventListener('click', function () { tab = i[0]; render(); });
				tabs.appendChild(b);
			});
		}
		function num(i) { var v = parseFloat(String(i.value).replace(',', '.')); return isNaN(v) ? null : v; }
		function pick(id) {
			value = id;
			if (hidden) hidden.value = id;
			paintTrig();
			hide();
			if (o.onChange) o.onChange(id);
		}
		function optRow(id, title, sub, right, free) {
			var b = el('button', 'flxmp-opt' + (id === value ? ' is-on' : ''));
			b.type = 'button';
			b.setAttribute('role', 'option');
			b.setAttribute('aria-selected', id === value ? 'true' : 'false');
			var l = el('span', 'flxmp-ol');
			l.appendChild(el('span', 'flxmp-on', title));
			if (sub) l.appendChild(el('span', 'flxmp-os', sub));
			b.appendChild(l);
			if (right) b.appendChild(el('span', 'flxmp-op' + (free ? ' is-free' : ''), right));
			b.addEventListener('click', function () { pick(id); });
			list.appendChild(b);
		}
		function render() {
			var all = base();
			paintTabs(all);
			q.placeholder = t('{n} model içinde ara…', { n: all.length });
			var s = q.value.trim().toLowerCase(), lo = num(pMin), hi = num(pMax);
			clr.hidden = lo === null && hi === null;
			var rows = all.filter(function (m) {
				if (tab === 'free' && !(m.in === 0 && m.out === 0)) return false;
				if (tab.indexOf('f:') === 0 && m.family !== tab.slice(2)) return false;
				if (lo !== null && m.in < lo) return false;
				if (hi !== null && m.in > hi) return false;
				return !s || (m.id + ' ' + m.name).toLowerCase().indexOf(s) > -1;
			}).sort(function (a, b) { return (a.in + a.out) - (b.in + b.out) || a.name.localeCompare(b.name); });
			list.innerHTML = '';
			active = -1;
			if (o.empty && !s) optRow('', emptyLabel(), t('Varsayılan'), '', false);
			rows.slice(0, 250).forEach(function (m) {
				var sub = [m.family, m.ctx ? t('{n} bağlam', { n: ctxText(m.ctx) }) : '', m.tools ? t('araç') : '', m.vision ? t('görsel') : ''].filter(Boolean).join(' · ');
				optRow(m.id, shortName(m), sub, priceText(m), m.in === 0 && m.out === 0);
			});
			if (rows.length > 250) list.appendChild(el('p', 'flxmp-more', t('{n} model daha; aramayı daraltın.', { n: rows.length - 250 })));
			// Listede olmayan bir kimlik yazıldıysa (ör. yeni çıkan model) elle kullanılabilir.
			var raw = q.value.trim().replace(/[^A-Za-z0-9._:\/~@+-]/g, '');
			if (raw.length > 2 && /[\/:-]/.test(raw) && !find(raw)) {
				var id = provider === 'anthropic' && raw.indexOf('claude:') !== 0 ? 'claude:' + raw : raw;
				optRow(id, t('Kullan: {id}', { id: id }), t('Listede yok, kimliği elle girilir'), '', false);
			}
			if (!list.children.length) list.appendChild(el('p', 'flxmp-more', DATA && DATA.error ? t('Model listesi alınamadı:') + ' ' + DATA.error : t('Eşleşen model yok.')));
		}
		function show() {
			if (open && open !== api) open.close();
			pop.hidden = false;
			wrap.classList.add('is-open');
			trig.setAttribute('aria-expanded', 'true');
			open = api;
			// Sağ kenara yakın seçicide panel sağa yaslanır (ekrandan taşmasın).
			pop.style.left = ''; pop.style.right = '';
			var r = wrap.getBoundingClientRect();
			if (window.innerWidth > 600 && r.left + pop.offsetWidth > window.innerWidth - 8) { pop.style.left = 'auto'; pop.style.right = '0'; }
			if (!DATA) { list.innerHTML = ''; list.appendChild(el('p', 'flxmp-more', t('Modeller yükleniyor…'))); }
			load(function () { paintProv(); render(); });
			setTimeout(function () { q.focus(); }, 0);
		}
		function hide() {
			pop.hidden = true;
			wrap.classList.remove('is-open');
			trig.setAttribute('aria-expanded', 'false');
			if (open === api) open = null;
		}
		var api = { wrap: wrap, close: hide };
		trig.addEventListener('click', function () { if (pop.hidden) show(); else hide(); });
		close.addEventListener('click', hide);
		prov.addEventListener('change', function () { provider = prov.value; tab = 'all'; if (!pop.hidden) render(); else show(); });
		q.addEventListener('input', render);
		pMin.addEventListener('input', render);
		pMax.addEventListener('input', render);
		clr.addEventListener('click', function () { pMin.value = ''; pMax.value = ''; render(); });
		q.addEventListener('keydown', function (e) {
			var opts = list.querySelectorAll('.flxmp-opt');
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (!opts.length) return;
				active = (active + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
				opts.forEach(function (b, i) { b.classList.toggle('is-act', i === active); });
				opts[active].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'Enter') {
				e.preventDefault();
				var b = opts[active > -1 ? active : 0];
				if (b) b.click();
			}
		});
		paintTrig();
		// Sağlayıcı seçimi ve fiyat rozeti için liste arka planda yüklenir.
		load(function () { paintProv(); paintTrig(); });
		wrap.flxmpSet = function (v) { value = v || ''; provider = provOf(value || o.def); if (hidden) hidden.value = value; if (DATA) paintProv(); paintTrig(); };
		return wrap;
	}
	window.FLXCModelPicker = { create: create, load: load, priceText: priceText };
})();
