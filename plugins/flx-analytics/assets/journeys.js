/* Flux Analytics: Yolculuklar ekranı (bağımlılıksız). Veri /wp-json/flxan/v1/journeys'den gelir. */
(function () {
	'use strict';
	var C = window.FLXAN || {};
	var L = C.i18n || {};
	var app = document.getElementById('flxan-journeys');
	if (!app) return;
	function t(s) { return L[s] || s; }
	function fmt(s) { var a = arguments; return String(s).replace(/%(\d)\$s|%s/g, function (m, i) { return i ? a[+i] : a[1]; }); }
	var nf = new Intl.NumberFormat(C.locale || undefined);
	var pf = new Intl.NumberFormat(C.locale || undefined, { maximumFractionDigits: 1 });
	function n(x) { return nf.format(x || 0); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function el(html) { var x = document.createElement('div'); x.innerHTML = html.trim(); return x.firstChild; }
	function api(path, params) {
		var q = new URLSearchParams(params || {});
		q.set('_locale', 'user');
		q.set('_t', Date.now());
		return fetch(C.rest + path + '?' + q.toString(), { headers: { 'X-WP-Nonce': C.nonce, 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
	}

	// --- Durum (adres çubuğunda) ---
	var S = { range: '30d', start: '', end: '', seg: 'all', rc: '', steps: 4, from: '', to: '', goal: C.goal || '', group: false, top: 15, cmp: true, tab: 'all', q: '' };
	(function readHash() {
		var h = new URLSearchParams(location.hash.slice(1));
		if (C.presets[h.get('range')]) S.range = h.get('range');
		S.start = h.get('start') || ''; S.end = h.get('end') || '';
		S.seg = h.get('seg') || 'all'; S.rc = h.get('rc') || '';
		if (h.get('steps')) S.steps = Math.max(2, Math.min(6, +h.get('steps') || 4));
		S.from = h.get('from') || ''; S.to = h.get('to') || '';
		if (h.has('goal')) S.goal = h.get('goal');
		S.group = h.get('group') === '1';
		if (h.get('top')) S.top = +h.get('top') || 15;
		S.cmp = h.get('cmp') !== '0';
	})();
	function writeHash() {
		var h = new URLSearchParams();
		h.set('range', S.range);
		if (S.range === 'custom') { h.set('start', S.start); h.set('end', S.end); }
		if (S.seg !== 'all') h.set('seg', S.seg);
		if (S.rc) h.set('rc', S.rc);
		if (S.steps !== 4) h.set('steps', S.steps);
		if (S.from) h.set('from', S.from);
		if (S.to) h.set('to', S.to);
		if (S.goal !== (C.goal || '')) h.set('goal', S.goal);
		if (S.group) h.set('group', '1');
		if (S.top !== 15) h.set('top', S.top);
		if (!S.cmp) h.set('cmp', '0');
		history.replaceState(null, '', '#' + h.toString());
	}
	function params() {
		var p = { range: S.range, steps: S.steps, goal: S.goal, top: S.top };
		if (S.range === 'custom') { p.start = S.start; p.end = S.end; }
		if (S.seg !== 'all') p.seg = S.seg;
		if (S.rc) p.rc = S.rc;
		if (S.from) p.from = S.from;
		if (S.to) p.to = S.to;
		if (S.group) p.group = 1;
		return p;
	}

	// --- İskelet ---
	app.innerHTML = '';
	var head = el('<div class="flxan-head"><h1>' + esc(t('Yolculuklar')) + '</h1><div class="flxan-tools"></div></div>');
	var tools = head.querySelector('.flxan-tools');
	var sel = el('<select aria-label="' + esc(t('Tarih aralığı')) + '"></select>');
	Object.keys(C.presets).forEach(function (k) { sel.appendChild(el('<option value="' + k + '">' + esc(C.presets[k]) + '</option>')); });
	var custom = el('<span class="flxan-custom"><input type="date" aria-label="' + esc(t('Başlangıç')) + '"><span>–</span><input type="date" aria-label="' + esc(t('Bitiş')) + '"><button type="button" class="flxan-btn">' + esc(t('Uygula')) + '</button></span>');
	var segSel = el('<select aria-label="' + esc(t('Ziyaretçi türü')) + '"></select>');
	var segOpts = [['all', t('Tüm ziyaretçiler')], ['guest', t('Dışarıdan gelenler')], ['members', t('Giriş yapanlar')]];
	Object.keys(C.segs || {}).forEach(function (k) { segOpts.push([k, fmt(t('Rol: %s'), C.segs[k])]); });
	segOpts.forEach(function (o) { segSel.appendChild(el('<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>')); });
	var rcSel = el('<select aria-label="' + esc(t('Referans')) + '"><option value="">' + esc(t('Tüm kaynaklar')) + '</option><option value="*">' + esc(t('Yalnız referans bağlantıları')) + '</option></select>');
	[sel, custom, segSel, rcSel].forEach(function (x) { tools.appendChild(x); });
	app.appendChild(head);

	// Seçenek çubuğu: önceki dönem, hedef, gruplama.
	var opts = el('<div class="flxan-jbar"></div>');
	var cmp = el('<label class="flxan-switch"><input type="checkbox"><span></span>' + esc(t('Önceki dönemle karşılaştır')) + '</label>');
	var goalWrap = el('<label class="flxan-jgoal"><span class="flxan-jgoal__ico" aria-hidden="true">◎</span>' + esc(t('Hedef sayfa')) + ' <select></select></label>');
	var goalSel = goalWrap.querySelector('select');
	Object.keys(C.goals || {}).forEach(function (k) { goalSel.appendChild(el('<option value="' + esc(k) + '">' + esc(C.goals[k]) + '</option>')); });
	var grp = el('<label class="flxan-switch" title="' + esc(t('/haber/… gibi alt sayfaları tek kutuda topla')) + '"><input type="checkbox"><span></span>' + esc(t('Sayfaları grupla')) + '</label>');
	opts.appendChild(cmp); opts.appendChild(goalWrap); opts.appendChild(grp);
	app.appendChild(opts);

	var cards = el('<div class="flxan-cards flxan-kpis flxan-kpis--5"></div>');
	app.appendChild(cards);

	var flowPanel = el('<div class="flxan-panel flxan-flowpanel"><div class="flxan-panel__head"><h2>' + esc(t('Girişten itibaren yollar')) + '</h2><div class="flxan-jctl"></div></div><div class="flxan-flow"></div><p class="flxan-muted flxan-flowhint"></p></div>');
	var ctl = flowPanel.querySelector('.flxan-jctl');
	var fromIn = el('<label class="flxan-jfield"><span>' + esc(t('Şuradan başlayan')) + '</span><input class="flxan-in" list="flxan-jpages" placeholder="' + esc(t('Tüm sayfalar')) + '"></label>');
	var toIn = el('<label class="flxan-jfield"><span>' + esc(t('Şurada biten')) + '</span><input class="flxan-in" list="flxan-jpages" placeholder="' + esc(t('Tüm sayfalar')) + '"></label>');
	var stepper = el('<div class="flxan-jfield"><span>' + esc(t('Adım')) + '</span><div class="flxan-stepper"><button type="button" class="flxan-btn" data-d="-1" aria-label="' + esc(t('Adım azalt')) + '">−</button><b></b><button type="button" class="flxan-btn" data-d="1" aria-label="' + esc(t('Adım artır')) + '">+</button></div></div>');
	var topSel = el('<label class="flxan-jfield"><span>' + esc(t('Gösterilen yol')) + '</span><select class="flxan-in"></select></label>');
	[10, 15, 25, 50].forEach(function (k) { topSel.querySelector('select').appendChild(el('<option value="' + k + '">' + fmt(t('En çok %s yol'), k) + '</option>')); });
	[fromIn, toIn, stepper, topSel].forEach(function (x) { ctl.appendChild(x); });
	var dl = el('<datalist id="flxan-jpages"></datalist>');
	app.appendChild(dl);
	app.appendChild(flowPanel);
	var flow = flowPanel.querySelector('.flxan-flow');

	var pathPanel = el('<div class="flxan-panel"><div class="flxan-panel__head"><h2>' + esc(t('En sık izlenen yollar')) + '</h2><div class="flxan-filterbar"><div class="flxan-tabs" role="tablist"></div><input type="search" class="flxan-search" placeholder="' + esc(t('Sayfa ara…')) + '"></div></div><div class="flxan-paths"></div></div>');
	var tabs = pathPanel.querySelector('.flxan-tabs');
	[['all', t('Tümü')], ['reach', t('Hedefe ulaşan')], ['never', t('Ulaşmayan')]].forEach(function (x) {
		tabs.appendChild(el('<button type="button" role="tab" data-tab="' + x[0] + '" aria-selected="' + (S.tab === x[0]) + '">' + esc(x[1]) + '</button>'));
	});
	app.appendChild(pathPanel);
	var pathsBox = pathPanel.querySelector('.flxan-paths');
	var search = pathPanel.querySelector('.flxan-search');
	app.appendChild(el('<p class="flxan-note">' + esc(t('Yolculuk: bir ziyarette gezilen sayfaların sırası (ilk 8 adım; aynı sayfanın yenilenmesi tek sayılır). Sepete ekleme, ödeme formu, "Ödeme yap", ödeme yöntemi, sipariş, WhatsApp/telefon tıklaması gibi olaylar ⚡ ile adım olarak görünür. Yalnız en az 2 sayfa gezilen ziyaretler gösterilir. Hedef, Ayarlar › Huniler\'deki adımlardan seçilir. Bu ekran 0.3.0 sürümünden sonraki ziyaretleri gösterir.')) + '</p>'));

	// --- Olaylar ---
	function sync() {
		sel.value = S.range;
		custom.classList.toggle('on', S.range === 'custom');
		var di = custom.querySelectorAll('input'); di[0].value = S.start; di[1].value = S.end;
		segSel.value = S.seg; rcSel.value = S.rc;
		segSel.classList.toggle('on', S.seg !== 'all'); rcSel.classList.toggle('on', !!S.rc);
		cmp.querySelector('input').checked = S.cmp;
		grp.querySelector('input').checked = S.group;
		goalSel.value = S.goal;
		goalWrap.classList.toggle('on', !!S.goal);
		fromIn.querySelector('input').value = S.from;
		toIn.querySelector('input').value = S.to;
		stepper.querySelector('b').textContent = S.steps;
		stepper.querySelector('[data-d="-1"]').setAttribute('aria-disabled', S.steps <= 2 ? 'true' : 'false');
		stepper.querySelector('[data-d="1"]').setAttribute('aria-disabled', S.steps >= 6 ? 'true' : 'false');
		topSel.querySelector('select').value = S.top;
	}
	function change(fn) { return function (e) { fn(e); writeHash(); load(); }; }
	sel.addEventListener('change', function () { S.range = sel.value; custom.classList.toggle('on', S.range === 'custom'); if (S.range !== 'custom') { writeHash(); load(); } });
	custom.querySelector('button').addEventListener('click', function () { var i = custom.querySelectorAll('input'); if (!i[0].value || !i[1].value) return; S.start = i[0].value; S.end = i[1].value; writeHash(); load(); });
	segSel.addEventListener('change', change(function () { S.seg = segSel.value; }));
	rcSel.addEventListener('change', change(function () { S.rc = rcSel.value; }));
	goalSel.addEventListener('change', change(function () { S.goal = goalSel.value; }));
	grp.querySelector('input').addEventListener('change', change(function (e) { S.group = e.target.checked; }));
	cmp.querySelector('input').addEventListener('change', function (e) { S.cmp = e.target.checked; writeHash(); if (D) render(D); });
	topSel.querySelector('select').addEventListener('change', function (e) { S.top = +e.target.value; writeHash(); if (D) renderPaths(D); });
	stepper.addEventListener('click', function (e) {
		var b = e.target.closest('[data-d]'); if (!b) return;
		var v = Math.max(2, Math.min(6, S.steps + +b.getAttribute('data-d')));
		if (v === S.steps) return;
		S.steps = v; writeHash(); load();
	});
	[[fromIn, 'from'], [toIn, 'to']].forEach(function (x) {
		var inp = x[0].querySelector('input');
		inp.addEventListener('change', function () {
			var v = inp.value.trim();
			var hit = pageByLabel[v]; if (hit) v = hit;
			if (v === S[x[1]]) return;
			S[x[1]] = v; writeHash(); load();
		});
	});
	tabs.addEventListener('click', function (e) {
		var b = e.target.closest('[data-tab]'); if (!b) return;
		S.tab = b.getAttribute('data-tab');
		tabs.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
		if (D) renderPaths(D);
	});
	search.addEventListener('input', function () { S.q = search.value.trim().toLowerCase(); if (D) renderPaths(D); });

	// --- Yükleme ---
	var D = null, seq = 0, pageByLabel = {};
	function load() {
		var my = ++seq;
		app.setAttribute('aria-busy', 'true');
		sync();
		skeleton();
		api('journeys', params()).then(function (d) {
			if (my !== seq) return;
			D = d;
			if (d.options) { S.goal = d.options.goal; S.steps = d.options.steps; }
			sync();
			render(d);
		}).catch(function () {
			if (my !== seq) return;
			flow.innerHTML = '<p class="flxan-empty">' + esc(t('Veri alınamadı. Sayfayı yenileyin.')) + '</p>';
		}).then(function () {
			if (my !== seq) return;
			app.removeAttribute('aria-busy');
		});
	}

	// Veri gelene kadar bütün bloklar yerinde iskelet olarak durur (ilk açılış ve her süzgeç / tarih değişikliği).
	function skeleton() {
		cards.innerHTML = [['violet', 'layers'], ['blue', 'route'], ['teal', 'enter'], ['amber', 'exit'], ['green', 'target']].map(function (x) {
			return '<div class="flxan-card flxan-kpi flxan-kpi--' + x[0] + ' flxan-kpi--sk"><div class="flxan-kpi__top"><span class="flxan-kpi__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[x[1]] + '</svg></span><i class="flxan-sk" style="width:60%"></i></div><i class="flxan-sk flxan-sk--val"></i><i class="flxan-sk flxan-sk--sub"></i></div>';
		}).join('');
		var cols = '';
		for (var i = 0; i < S.steps; i++) {
			cols += '<div class="flxan-skflow__col"><i class="flxan-sk flxan-sk--title"></i>';
			for (var j = 0; j < Math.max(2, 5 - i); j++) cols += '<i class="flxan-sk" style="height:' + Math.max(46, 110 - j * 18 - i * 8) + 'px"></i>';
			cols += '</div>';
		}
		flow.innerHTML = '<div class="flxan-skflow" style="--c:' + S.steps + '">' + cols + '</div>';
		flowPanel.querySelector('.flxan-flowhint').textContent = '';
		var rows = '<div class="flxan-skrows">';
		for (var k = 0; k < 8; k++) {
			rows += '<div class="flxan-skrow"><span style="display:flex;gap:6px;flex:1">';
			for (var m = 0; m < 2 + (k % 3); m++) rows += '<i class="flxan-sk" style="width:' + (70 + (k * 13 + m * 29) % 50) + 'px;height:18px;border-radius:99px"></i>';
			rows += '</span><i class="flxan-sk flxan-sk--n"></i><i class="flxan-sk" style="width:90px"></i><i class="flxan-sk flxan-sk--n"></i></div>';
		}
		pathsBox.innerHTML = rows + '</div>';
	}

	function render(d) {
		renderCards(d);
		renderFlow(d);
		renderPaths(d);
		var seen = {};
		dl.innerHTML = '';
		pageByLabel = {};
		var add = function (s) {
			if (!s.path || seen[s.path]) return;
			seen[s.path] = 1;
			pageByLabel[s.label] = s.path;
			dl.appendChild(el('<option value="' + esc(s.path) + '">' + esc(s.label) + '</option>'));
		};
		(d.flow.steps || []).forEach(function (c) { c.forEach(add); });
		(d.paths.items || []).forEach(function (p) { p.steps.forEach(add); });
	}

	// --- Kartlar ---
	var ICONS = {
		layers: '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
		route: '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
		enter: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
		exit: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
		target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'
	};
	function delta(ch) {
		if (!S.cmp || ch === null || ch === undefined) return '';
		return '<span class="flxan-delta ' + (ch > 0 ? 'up' : ch < 0 ? 'down' : '') + '" title="' + esc(t('Önceki eşit döneme göre')) + '">' + (ch > 0 ? '↑' : ch < 0 ? '↓' : '') + pf.format(Math.abs(ch)) + '%</span>';
	}
	function card(label, value, ch, sub, tone, icon, small) {
		return '<div class="flxan-card flxan-kpi flxan-kpi--' + tone + (small ? ' flxan-kpi--text' : '') + '"><div class="flxan-kpi__top"><span class="flxan-kpi__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[icon] + '</svg></span><span class="flxan-card__label">' + esc(label) + '</span></div>' +
			'<div class="flxan-kpi__row"><div class="flxan-card__value" title="' + esc(small ? value : '') + '">' + esc(value) + '</div>' + delta(ch) + '</div><div class="flxan-card__sub" title="' + esc(sub || '') + '">' + esc(sub || '') + '</div></div>';
	}
	function renderCards(d) {
		var s = d.summary;
		var page = function (x) { return x ? x.label : '—'; };
		var psub = function (x) { return x ? fmt(t('%1$s ziyaret · %2$s'), n(x.count), pf.format(x.pct) + '%') : t('Veri yok'); };
		cards.innerHTML =
			card(t('Çok sayfalı ziyaret'), n(s.multi), s.multi_change, fmt(t('Tüm ziyaretlerin %s'), pf.format(s.sessions ? s.multi / s.sessions * 100 : 0) + '%'), 'violet', 'layers') +
			card(t('Ort. yol uzunluğu'), fmt(t('%s sayfa'), pf.format(s.avg_len)), s.avg_len_change, t('Çok sayfalı ziyaretlerde'), 'blue', 'route') +
			card(t('En sık giriş'), page(s.top_entry), null, psub(s.top_entry), 'teal', 'enter', true) +
			card(t('2+ sayfadan sonra en sık çıkış'), page(s.top_exit), null, psub(s.top_exit), 'amber', 'exit', true) +
			(s.goal_rate === null
				? card(t('Hedefe ulaşma'), '—', null, t('Hedef seçilmedi'), 'green', 'target')
				: card(t('Hedefe ulaşma'), pf.format(s.goal_rate) + '%', s.goal_change, fmt(t('%1$s ziyaret · %2$s'), n(s.goal_reached), (C.goals || {})[d.options.goal] || ''), 'green', 'target'));
	}

	// --- Akış grafiği (sankey) ---
	var PAL = ['#1f8a5b', '#2563eb', '#7c3aed', '#d97706', '#0d9488', '#e11d48', '#0891b2', '#65a30d', '#c026d3', '#ea580c'];
	function renderFlow(d) {
		var cols = d.flow.steps || [], links = d.flow.links || [];
		var hint = flowPanel.querySelector('.flxan-flowhint');
		if (!cols.length || !cols[0].length) {
			flow.innerHTML = '<div class="flxan-empty flxan-empty--big"><b>' + esc(t('Henüz yolculuk yok')) + '</b><br>' + esc(t('Seçili dönemde en az 2 sayfa gezilen ziyaret bulunamadı. Süzgeçleri genişletin ya da yeni ziyaretleri bekleyin.')) + '</div>';
			hint.textContent = '';
			return;
		}
		hint.textContent = t('Bir kutuya tıklayınca aşağıdaki yollar o sayfaya göre süzülür. Kalınlık ziyaret sayısını gösterir.');
		var color = {}, ci = 0;
		cols.forEach(function (c) { c.forEach(function (x) { if (x.key === '~') return; if (!color[x.key]) color[x.key] = PAL[ci++ % PAL.length]; }); });
		var colorOf = function (k) { return k === '~' ? '#9aa1a8' : color[k]; };

		var NW = 172, GAP = 10, MINH = 46, colN = cols.length;
		var W = Math.max(flow.clientWidth || 900, colN * (NW + 70));
		var total = cols[0].reduce(function (a, x) { return a + x.count; }, 0) || 1;
		var avail = Math.max(260, Math.min(560, 70 * Math.max.apply(null, cols.map(function (c) { return c.length; }))));
		var k = avail / total;
		var pos = {}, H = 0;
		var colX = function (i) { return colN === 1 ? 0 : i * (W - NW) / (colN - 1); };
		cols.forEach(function (c, i) {
			var y = 34;
			c.forEach(function (x) {
				var h = Math.max(MINH, x.count * k);
				pos[i + '|' + x.key] = { x: colX(i), y: y, h: h, outY: y, inY: y, n: x };
				y += h + GAP;
			});
			H = Math.max(H, y);
		});
		var svg = '<svg class="flxan-sankey__links" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" aria-hidden="true">';
		links.slice().sort(function (a, b) { return a.step - b.step || b.count - a.count; }).forEach(function (l) {
			var a = pos[l.step + '|' + l.from], b = pos[(l.step + 1) + '|' + l.to];
			if (!a || !b) return;
			var th = Math.max(2, l.count * k);
			var y1 = a.outY + th / 2, y2 = b.inY + th / 2;
			a.outY += th; b.inY += th;
			var x1 = a.x + NW, x2 = b.x, mx = (x1 + x2) / 2;
			svg += '<path class="flxan-link" d="M' + x1 + ',' + y1 + ' C' + mx + ',' + y1 + ' ' + mx + ',' + y2 + ' ' + x2 + ',' + y2 + '" stroke="' + colorOf(l.from) + '" stroke-width="' + th + '" data-from="' + esc(l.from) + '" data-to="' + esc(l.to) + '"><title>' + esc((a.n.label) + ' → ' + (b.n.label) + ': ' + fmt(t('%s ziyaret'), n(l.count))) + '</title></path>';
		});
		svg += '</svg>';
		var html = '<div class="flxan-sankey" style="width:' + W + 'px;height:' + H + 'px">' + svg;
		cols.forEach(function (c, i) {
			var head = i === 0 ? t('Giriş') : fmt(t('%s. adım'), i + 1);
			var colTotal = c.reduce(function (a, x) { return a + x.count; }, 0);
			html += '<div class="flxan-sankey__col" style="left:' + colX(i) + 'px;width:' + NW + 'px"><b>' + esc(head) + '</b> <span>' + n(colTotal) + '</span></div>';
			c.forEach(function (x) {
				var p = pos[i + '|' + x.key];
				var endPct = x.count ? Math.round(x.ends / x.count * 100) : 0;
				var sub = x.ends > 0 ? fmt(t('%s burada bitti'), endPct + '%') : (i < colN - 1 ? t('hepsi devam etti') : t('devam ediyor'));
				html += '<button type="button" class="flxan-node' + (x.key === '~' ? ' flxan-node--other' : '') + (x.ev ? ' flxan-node--ev' : '') + '" style="left:' + p.x + 'px;top:' + p.y + 'px;width:' + NW + 'px;height:' + p.h + 'px;--c:' + colorOf(x.key) + '" data-key="' + esc(x.key) + '" data-label="' + esc(x.label) + '" title="' + esc(x.label + (x.path ? ' (' + x.path + ')' : '')) + '">' +
					'<span class="flxan-node__l">' + (x.ev ? '<span class="flxan-evi" aria-hidden="true">⚡</span>' : '') + esc(x.label) + '</span><span class="flxan-node__s"><b>' + n(x.count) + '</b> · ' + esc(sub) + '</span>' +
					'<i class="flxan-node__end" style="height:' + endPct + '%"></i></button>';
			});
		});
		html += '</div>';
		flow.innerHTML = html;
		flow.querySelectorAll('.flxan-node').forEach(function (b) {
			var key = b.getAttribute('data-key');
			b.addEventListener('mouseenter', function () { flow.classList.add('hl'); flow.querySelectorAll('.flxan-link').forEach(function (l) { l.classList.toggle('on', l.getAttribute('data-from') === key || l.getAttribute('data-to') === key); }); });
			b.addEventListener('mouseleave', function () { flow.classList.remove('hl'); });
			b.addEventListener('click', function () {
				if (key === '~') return;
				search.value = b.getAttribute('data-label');
				S.q = search.value.toLowerCase();
				renderPaths(D);
				pathPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		});
	}

	// --- En sık izlenen yollar ---
	function renderPaths(d) {
		var items = (d.paths.items || []).map(function (p) {
			var cnt = S.tab === 'reach' ? p.reached : S.tab === 'never' ? p.sessions - p.reached : p.sessions;
			return { p: p, c: cnt };
		}).filter(function (x) {
			if (!x.c) return false;
			if (!S.q) return true;
			return x.p.steps.some(function (s) { return (s.label + ' ' + s.path).toLowerCase().indexOf(S.q) !== -1; });
		});
		items.sort(function (a, b) { return b.c - a.c; });
		var sum = items.reduce(function (a, x) { return a + x.c; }, 0) || 1;
		var hasGoal = d.summary.goal_rate !== null;
		tabs.hidden = !hasGoal;
		var shown = items.slice(0, S.top);
		if (!shown.length) {
			pathsBox.innerHTML = '<p class="flxan-empty">' + esc(S.q ? t('Aramaya uyan yol yok.') : t('Gösterilecek yol yok.')) + '</p>';
			return;
		}
		var h = '<table class="flxan-table flxan-ptable"><thead><tr><th class="n">#</th><th>' + esc(t('Yol')) + '</th><th class="n">' + esc(t('Ziyaret')) + '</th><th class="flxan-pshare">' + esc(t('Pay')) + '</th>' +
			(S.cmp ? '<th class="n n2">' + esc(t('Öncekine göre')) + '</th>' : '') + (hasGoal ? '<th class="n">' + esc(t('Hedefe ulaşma')) + '</th>' : '') + '</tr></thead><tbody>';
		shown.forEach(function (x, i) {
			var p = x.p, share = x.c / sum * 100;
			var chips = p.steps.map(function (s, j) {
				return (j ? '<span class="flxan-arrow" aria-hidden="true">→</span>' : '') + '<span class="flxan-pchip' + (s.ev ? ' flxan-pchip--ev' : '') + '" title="' + esc(s.path || s.label) + '">' + (s.ev ? '⚡ ' : '') + esc(s.label) + '</span>';
			}).join('');
			var ch = p.change;
			h += '<tr><td class="n flxan-muted">' + (i + 1) + '</td><td><div class="flxan-pchips">' + chips + '</div></td><td class="n"><b>' + n(x.c) + '</b></td>' +
				'<td class="flxan-pshare"><div class="flxan-sharebar"><i style="width:' + Math.max(2, share) + '%"></i></div><span>' + pf.format(share) + '%</span></td>' +
				(S.cmp ? '<td class="n n2">' + (ch === null ? '<span class="flxan-muted">' + esc(p.prev ? '—' : t('yeni')) + '</span>' : '<span class="flxan-delta ' + (ch > 0 ? 'up' : ch < 0 ? 'down' : '') + '">' + (ch > 0 ? '↑' : ch < 0 ? '↓' : '') + pf.format(Math.abs(ch)) + '%</span>') + '</td>' : '') +
				(hasGoal ? '<td class="n"><span class="flxan-rate' + (p.rate >= 50 ? ' hi' : p.rate > 0 ? ' mid' : '') + '">' + pf.format(p.rate) + '%</span></td>' : '') + '</tr>';
		});
		h += '</tbody></table>';
		if (items.length > shown.length) h += '<p class="flxan-muted">' + esc(fmt(t('%1$s yoldan %2$s tanesi gösteriliyor.'), n(items.length), n(shown.length))) + '</p>';
		pathsBox.innerHTML = h;
	}

	var rt;
	window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { if (D) renderFlow(D); }, 200); });
	load();
})();
