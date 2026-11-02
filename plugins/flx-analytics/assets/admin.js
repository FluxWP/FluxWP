/* Flux Analiz: İstatistikler ekranı (bağımlılıksız). Veri /wp-json/flxan/v1'den gelir. */
(function () {
	'use strict';
	var C = window.FLXAN || {};
	var L = C.i18n || {};
	function t(s) { return L[s] || s; }
	function fmt(s) { var a = arguments; return String(s).replace(/%(\d)\$s|%s/g, function (m, i) { return i ? a[+i] : a[1]; }); }

	// Bu tarayıcı sayılmasın (dışlanan rol) / tekrar sayılsın.
	try { if (C.ignore) localStorage.setItem('flxan_ignore', '1'); else localStorage.removeItem('flxan_ignore'); } catch (e) {}

	// Hızlı uç testi (günde bir; Ayarlar ekranında her açılışta).
	if (C.fast) {
		var pill = document.getElementById('flxan-fast-test');
		fetch(C.fast + '?test=1&_t=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
			.then(function (r) { return r.ok ? r.text() : ''; })
			.catch(function () { return ''; })
			.then(function (txt) {
				var ok = txt.trim() === 'ok';
				if (pill) { pill.hidden = false; pill.className = 'flxan-pill flxan-pill--' + (ok ? 'ok' : 'err'); pill.textContent = ok ? t('Hızlı uç çalışıyor') : t('Hızlı uç erişilemiyor, REST kullanılacak'); }
				return fetch(C.rest + 'fast-test?_locale=user', { method: 'POST', headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ ok: ok }) });
			}).catch(function () {});
	}

	var app = document.getElementById('flxan-app');
	if (!app) return;

	var nf = new Intl.NumberFormat(C.locale || undefined);
	var pf = new Intl.NumberFormat(C.locale || undefined, { maximumFractionDigits: 1 });
	function n(x) { return nf.format(x || 0); }
	function money(x) { try { return new Intl.NumberFormat(C.locale || undefined, { maximumFractionDigits: 0 }).format(x || 0); } catch (e) { return n(x); } }
	function d(s, opt) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(C.locale || undefined, opt || { day: 'numeric', month: 'short' }); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function el(html) { var x = document.createElement('div'); x.innerHTML = html.trim(); return x.firstChild; }

	function api(path, params) {
		var q = new URLSearchParams(params || {});
		q.set('_locale', 'user');
		q.set('_t', Date.now());
		var ctl = window.AbortController ? new AbortController() : null;
		var timer = ctl ? setTimeout(function () { ctl.abort(); }, 20000) : 0;
		return fetch(C.rest + path + '?' + q.toString(), { headers: { 'X-WP-Nonce': C.nonce, 'Accept': 'application/json' }, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
			.then(function (r) { clearTimeout(timer); if (!r.ok) throw new Error(r.status); return r.json(); });
	}

	// --- Durum (adres çubuğunda: #range=30d, #range=custom&start=…&end=…, #page=/yol/) ---
	var S = { range: '30d', start: '', end: '', seg: 'all', rc: '', by: 'channel', fid: 0, data: null, eventName: '', tabs: { tech: 'device', camp: 'utm_source', src: 'channel' } };
	function readHash() {
		var h = new URLSearchParams(location.hash.slice(1));
		S.range = C.presets[h.get('range')] ? h.get('range') : (S.range || '30d');
		S.start = h.get('start') || '';
		S.end = h.get('end') || '';
		S.seg = h.get('seg') || 'all';
		S.rc = h.get('rc') || '';
		return h.get('page');
	}
	function writeHash() {
		var h = new URLSearchParams();
		h.set('range', S.range);
		if (S.range === 'custom') { h.set('start', S.start); h.set('end', S.end); }
		if (S.seg !== 'all') h.set('seg', S.seg);
		if (S.rc) h.set('rc', S.rc);
		history.replaceState(null, '', '#' + h.toString());
	}
	function filterParams() { var p = {}; if (S.seg !== 'all') p.seg = S.seg; if (S.rc) p.rc = S.rc; return p; }
	function rangeParams() { var p = Object.assign({ range: S.range }, filterParams()); if (S.range === 'custom') { p.start = S.start; p.end = S.end; } return p; }
	function dur(sec) {
		sec = Math.round(sec || 0);
		if (sec < 60) return fmt(t('%s sn'), sec);
		return fmt(t('%1$s dk %2$s sn'), Math.floor(sec / 60), String(sec % 60).padStart(2, '0'));
	}
	function copy(text, btn) {
		var done = function () { if (btn) { var o = btn.textContent; btn.textContent = t('Kopyalandı'); setTimeout(function () { btn.textContent = o; }, 1500); } };
		if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () { window.prompt(t('Bağlantı'), text); }); return; }
		window.prompt(t('Bağlantı'), text);
	}

	// --- İskelet ---
	app.innerHTML = '';
	app.appendChild(el('<div class="flxan-loadbar"></div>'));
	var head = el('<div class="flxan-head"><h1>' + esc(t('İstatistikler')) + '</h1><div class="flxan-tools"></div></div>');
	var tools = head.querySelector('.flxan-tools');
	var sel = el('<select aria-label="' + esc(t('Tarih aralığı')) + '"></select>');
	Object.keys(C.presets).forEach(function (k) { sel.appendChild(el('<option value="' + k + '">' + esc(C.presets[k]) + '</option>')); });
	var custom = el('<span class="flxan-custom"><input type="date" aria-label="' + esc(t('Başlangıç')) + '"><span>–</span><input type="date" aria-label="' + esc(t('Bitiş')) + '"><button type="button" class="flxan-btn">' + esc(t('Uygula')) + '</button></span>');
	var exp = el('<a class="flxan-btn" href="#">' + esc(t('CSV indir')) + '</a>');
	var segSel = el('<select aria-label="' + esc(t('Ziyaretçi türü')) + '"></select>');
	var segOpts = [['all', t('Tüm ziyaretçiler')], ['guest', t('Dışarıdan gelenler')], ['members', t('Giriş yapanlar')]];
	Object.keys(C.segs || {}).forEach(function (k) { segOpts.push([k, fmt(t('Rol: %s'), C.segs[k])]); });
	segOpts.forEach(function (o) { segSel.appendChild(el('<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>')); });
	var rcSel = el('<select aria-label="' + esc(t('Referans')) + '"><option value="">' + esc(t('Tüm kaynaklar')) + '</option><option value="*">' + esc(t('Yalnız referans bağlantıları')) + '</option></select>');
	tools.appendChild(sel); tools.appendChild(custom); tools.appendChild(segSel); tools.appendChild(rcSel); tools.appendChild(exp);
	// Üst kısım (süzgeçler + göstergeler) kaydırırken sabit kalır.
	var sticky = el('<div class="flxan-sticky"></div>');
	var sentinel = el('<div class="flxan-sentinel" aria-hidden="true"></div>');
	app.appendChild(sentinel);
	app.appendChild(sticky);
	if (window.IntersectionObserver) {
		new IntersectionObserver(function (e) { sticky.classList.toggle('stuck', !e[0].isIntersecting); }, { rootMargin: '-34px 0px 0px 0px' }).observe(sentinel);
	}
	sticky.appendChild(head);
	var banner = el('<div class="flxan-banner" hidden></div>');
	sticky.appendChild(banner);
	var cards = el('<div class="flxan-cards flxan-kpis"></div>');
	sticky.appendChild(cards);
	var top = el('<div class="flxan-top"></div>');
	var chartPanel = el('<div class="flxan-panel"><div class="flxan-panel__head"><h2></h2><div class="flxan-legend"><span><i style="background:var(--flxan-accent)"></i>' + esc(t('Ziyaretçi')) + '</span><span><i style="background:var(--flxan-accent-2)"></i>' + esc(t('Görüntülenme')) + '</span></div></div><div class="flxan-chart"></div></div>');
	var livePanel = el('<div class="flxan-panel flxan-live" id="flxan-rt"></div>');
	top.appendChild(chartPanel); top.appendChild(livePanel);
	app.appendChild(top);
	var grid = el('<div class="flxan-grid"></div>');
	app.appendChild(grid);
	app.appendChild(el('<p class="flxan-note">' + esc(t('Ziyaretçi: her gün için tekil kişi sayısının toplamı. Kişi yalnız aynı gün içinde tanınır; referans bağlantısıyla gelen 30 gün boyunca o koda sayılır. Ülke, IP\'ye bakılmadan tarayıcının saat diliminden çıkarılır.')) + '</p>'));

	// --- İskelet: veri gelene kadar (ilk açılış ve süzgeç / tarih değişince) bütün bloklar yerinde, yanıp sönen gri. ---
	function skRows(k, cols) {
		var h = '<div class="flxan-skrows">';
		for (var i = 0; i < k; i++) h += '<div class="flxan-skrow"><i class="flxan-sk" style="width:' + (78 - (i * 9) % 40) + '%"></i>' + (cols ? '<i class="flxan-sk flxan-sk--n"></i>' : '') + '</div>';
		return h + '</div>';
	}
	function skPanel(wide, inner, title) {
		return '<div class="flxan-panel flxan-skpanel' + (wide ? ' flxan-wide' : '') + '"><div class="flxan-panel__head"><i class="flxan-sk flxan-sk--title" style="width:' + (title || 38) + '%"></i></div>' + inner + '</div>';
	}
	var TONES = [['green', 'users'], ['blue', 'eye'], ['violet', 'door'], ['amber', 'layers'], ['teal', 'clock'], ['rose', 'bounce']];
	function skeleton(first) {
		lastKpi = '';
		cards.innerHTML = TONES.map(function (x) {
			return '<div class="flxan-card flxan-kpi flxan-kpi--' + x[0] + ' flxan-kpi--sk"><div class="flxan-kpi__top"><span class="flxan-kpi__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[x[1]] + '</svg></span><i class="flxan-sk" style="width:55%"></i></div>' +
				'<i class="flxan-sk flxan-sk--val"></i><i class="flxan-sk flxan-sk--sub"></i></div>';
		}).join('');
		chartPanel.querySelector('h2').innerHTML = '<i class="flxan-sk flxan-sk--title" style="width:260px"></i>';
		var bars = '';
		for (var i = 0; i < 14; i++) bars += '<i class="flxan-sk" style="height:' + (30 + ((i * 37) % 55)) + '%"></i>';
		chartPanel.querySelector('.flxan-chart').innerHTML = '<div class="flxan-skchart">' + bars + '</div>';
		if (first) livePanel.innerHTML = '<i class="flxan-sk flxan-sk--title" style="width:30%"></i><i class="flxan-sk flxan-sk--big"></i>' + skRows(5, true);
		grid.innerHTML =
			skPanel(true, '<div class="flxan-skmap"><i class="flxan-sk"></i>' + skRows(6, true) + '</div>', 22) +
			skPanel(true, '<div class="flxan-skfunnel">' + [100, 64, 41, 27, 16, 9].map(function (h) { return '<i class="flxan-sk" style="height:' + h + '%"></i>'; }).join('') + '</div>', 26) +
			skPanel(true, skRows(4, true), 24) +
			[34, 30, 44, 36, 40, 28].map(function (w) { return skPanel(false, skRows(6, true), w); }).join('');
	}

	segSel.addEventListener('change', function () { S.seg = segSel.value; writeHash(); load(); });
	rcSel.addEventListener('change', function () { S.rc = rcSel.value; writeHash(); load(); });
	function setRc(code) { S.rc = code; writeHash(); load(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
	function syncFilters(D) {
		segSel.value = S.seg;
		if (segSel.value !== S.seg) { segSel.appendChild(el('<option value="' + esc(S.seg) + '">' + esc(S.seg) + '</option>')); segSel.value = S.seg; }
		var have = {};
		rcSel.querySelectorAll('option[data-code]').forEach(function (o) { o.remove(); });
		(D.refs || []).forEach(function (r) { have[r.code] = 1; rcSel.appendChild(el('<option data-code="1" value="' + esc(r.code) + '">' + esc(r.label + (r.label !== r.code ? ' (' + r.code + ')' : '')) + '</option>')); });
		if (S.rc && S.rc !== '*' && !have[S.rc]) rcSel.appendChild(el('<option data-code="1" value="' + esc(S.rc) + '">' + esc(S.rc) + '</option>'));
		rcSel.value = S.rc;
		segSel.classList.toggle('on', S.seg !== 'all');
		rcSel.classList.toggle('on', !!S.rc);
	}

	sel.addEventListener('change', function () {
		S.range = sel.value;
		custom.classList.toggle('on', S.range === 'custom');
		if (S.range !== 'custom') { writeHash(); load(); }
	});
	custom.querySelector('button').addEventListener('click', function () {
		var i = custom.querySelectorAll('input');
		if (!i[0].value || !i[1].value) return;
		S.start = i[0].value; S.end = i[1].value; writeHash(); load();
	});
	exp.addEventListener('click', function (e) {
		e.preventDefault();
		var q = new URLSearchParams(rangeParams()); q.set('type', 'site');
		location.href = C.export + '&' + q.toString();
	});

	// --- Grafik ---
	function chart(box, items, group) {
		box.innerHTML = '';
		var W = Math.max(300, box.clientWidth || 600), H = box.clientHeight || 260, pl = 40, pb = 22, pt = 8;
		var max = 0;
		items.forEach(function (it) { max = Math.max(max, it.pageviews); });
		var step = niceStep(max);
		var top = Math.max(step, Math.ceil(max / step) * step);
		var iw = W - pl, ih = H - pb - pt, bw = iw / Math.max(1, items.length);
		var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(t('Ziyaretçi ve görüntülenme grafiği')) + '"><g class="grid">';
		for (var y = 0; y <= top; y += step) {
			var yy = pt + ih - y / top * ih;
			s += '<line x1="' + pl + '" x2="' + W + '" y1="' + yy + '" y2="' + yy + '"/>';
			s += '<text class="ax" x="' + (pl - 6) + '" y="' + (yy + 4) + '" text-anchor="end" style="fill:var(--flxan-muted);font-size:11px">' + compact(y) + '</text>';
		}
		s += '</g>';
		var every = Math.ceil(items.length / Math.max(2, Math.floor(iw / 60)));
		items.forEach(function (it, i) {
			// Dar ekranda çubuklar daha geniş; sıfır olmayan değer en az 3 px görünür (ziyaretçi çubuğu kaybolmasın).
			var x = pl + i * bw, w = Math.max(2, bw * (W < 520 ? 0.86 : 0.72)), ox = x + (bw - w) / 2;
			var hp = it.pageviews ? Math.max(3, it.pageviews / top * ih) : 0, hv = it.visitors ? Math.max(3, it.visitors / top * ih) : 0;
			s += '<rect class="pv" x="' + ox + '" y="' + (pt + ih - hp) + '" width="' + w + '" height="' + hp + '" rx="' + Math.min(3, w / 3) + '"/>';
			s += '<rect class="v" x="' + ox + '" y="' + (pt + ih - hv) + '" width="' + w + '" height="' + hv + '" rx="' + Math.min(3, w / 3) + '"/>';
			s += '<rect class="hit" data-i="' + i + '" x="' + x + '" y="' + pt + '" width="' + bw + '" height="' + ih + '"/>';
			if (i % every === 0) s += '<text class="ax" x="' + (x + bw / 2) + '" y="' + (H - 6) + '" text-anchor="middle" style="fill:var(--flxan-muted);font-size:11px">' + esc(label(it.d, group, true)) + '</text>';
		});
		s += '</svg>';
		box.innerHTML = s + '<div class="flxan-tip"></div>';
		var tip = box.querySelector('.flxan-tip');
		box.querySelectorAll('.hit').forEach(function (r) {
			r.addEventListener('mouseenter', function () {
				var it = items[+r.getAttribute('data-i')];
				tip.innerHTML = '<b>' + esc(label(it.d, group)) + '</b><br>' + esc(t('Ziyaretçi')) + ': ' + n(it.visitors) + '<br>' + esc(t('Görüntülenme')) + ': ' + n(it.pageviews);
				var bx = r.getBBox(), sc = box.clientWidth / W;
				tip.style.left = ((bx.x + bx.width / 2) * sc) + 'px';
				tip.style.top = Math.max(10, (pt + ih - it.pageviews / top * ih) * (box.clientHeight / H) - 6) + 'px';
				tip.style.display = 'block';
			});
			r.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
		});
	}
	function niceStep(max) {
		if (max <= 4) return 1;
		var raw = max / 4, p = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / p;
		return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
	}
	function compact(x) { try { return new Intl.NumberFormat(C.locale || undefined, { notation: 'compact' }).format(x); } catch (e) { return n(x); } }
	function label(day, group, short) {
		if (group === 'month') return d(day, { month: 'short', year: short ? '2-digit' : 'numeric' });
		if (group === 'week') return short ? d(day) : fmt(t('%s haftası'), d(day, { day: 'numeric', month: 'long' }));
		return short ? d(day) : d(day, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
	}

	// --- Kartlar ---
	function delta(ch, invert) {
		if (ch === null || ch === undefined) return '';
		var good = invert ? ch < 0 : ch > 0, bad = invert ? ch > 0 : ch < 0;
		var cls = good ? 'up' : bad ? 'down' : '';
		return '<span class="flxan-delta ' + cls + '" title="' + esc(t('Önceki eşit döneme göre')) + '">' + (ch > 0 ? '↑' : ch < 0 ? '↓' : '') + pf.format(Math.abs(ch)) + '%</span>';
	}
	// Gösterge ikonları (24x24, çizgi).
	var ICONS = {
		users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		door: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
		layers: '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
		clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		bounce: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>'
	};
	function card(label, value, ch, sub, invert, tone, icon) {
		return '<div class="flxan-card flxan-kpi flxan-kpi--' + tone + '"><div class="flxan-kpi__top"><span class="flxan-kpi__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[icon] + '</svg></span><span class="flxan-card__label">' + esc(label) + '</span></div>' +
			'<div class="flxan-kpi__row"><div class="flxan-card__value">' + value + '</div>' + delta(ch, invert) + '</div><div class="flxan-card__sub" title="' + esc(sub || '') + '">' + esc(sub || '') + '</div></div>';
	}
	function renderCards(D) {
		var ppv = D.totals.visitors ? D.totals.pageviews / D.totals.visitors : 0;
		var pp = D.prev.visitors ? D.prev.pageviews / D.prev.visitors : 0;
		var V = D.visits, VP = D.visits_prev, ch = D.change;
		var prev = function (x) { return fmt(t('Önceki dönem: %s'), x); };
		cards.innerHTML =
			card(t('Ziyaretçi'), n(D.totals.visitors), ch.visitors, prev(n(D.prev.visitors)), false, 'green', 'users') +
			card(t('Görüntülenme'), n(D.totals.pageviews), ch.pageviews, prev(n(D.prev.pageviews)), false, 'blue', 'eye') +
			card(t('Ziyaret'), n(V.sessions), ch.sessions, prev(n(VP.sessions)), false, 'violet', 'door') +
			card(t('Sayfa / ziyaretçi'), pf.format(ppv), pp ? Math.round((ppv - pp) / pp * 1000) / 10 : null, prev(pf.format(pp)), false, 'amber', 'layers') +
			card(t('Ort. ziyaret süresi'), esc(dur(V.avg_duration)), ch.avg_duration, prev(dur(VP.avg_duration)), false, 'teal', 'clock') +
			card(t('Hemen çıkma'), pf.format(V.bounce_rate) + '%', ch.bounce_rate, t('Tek sayfa görüp ayrılanlar'), true, 'rose', 'bounce');
	}

	// --- Canlı ---
	var lastActive = null;
	function liveList(title, items, lab, val) {
		if (!items || !items.length) return '';
		var max = 0; items.forEach(function (x) { max = Math.max(max, val(x)); });
		return '<div class="flxan-live__list"><h3>' + esc(title) + '</h3>' + items.slice(0, 5).map(function (x) {
			return '<div class="flxan-live__row"><span class="bar" style="width:' + (max ? val(x) / max * 100 : 0).toFixed(1) + '%"></span><span class="txt">' + lab(x) + '</span><b>' + n(val(x)) + '</b></div>';
		}).join('') + '</div>';
	}
	function renderRealtime(R) {
		if (!R) return;
		var max = Math.max.apply(null, R.per_minute.concat([1])), w = 100 / R.per_minute.length, bars = '';
		R.per_minute.forEach(function (v, i) { var h = v / max * 40; bars += '<rect x="' + (i * w + w * 0.12) + '" y="' + (40 - h) + '" width="' + (w * 0.76) + '" height="' + Math.max(h, v ? 1.5 : 0.6) + '" rx="1"' + (v ? '' : ' class="z"') + '><title>' + esc(fmt(t('%1$s dk önce: %2$s'), R.per_minute.length - 1 - i, v)) + '</title></rect>'; });
		var flash = lastActive !== null && lastActive !== R.active ? ' flash' : '';
		lastActive = R.active;
		livePanel.innerHTML =
			'<div class="flxan-panel__head"><h2><span class="flxan-live-dot" aria-hidden="true"></span>' + esc(t('Canlı')) + '</h2><span class="flxan-muted">' + esc(t('5 sn\'de bir yenilenir')) + '</span></div>' +
			'<div class="flxan-live__now"><div><div class="flxan-live__big' + flash + '">' + n(R.active) + '</div><div class="flxan-muted">' + esc(t('şu an sitede (son 5 dk)')) + '</div></div>' +
			'<div><div class="flxan-live__mid">' + n(R.visitors) + '</div><div class="flxan-muted">' + esc(fmt(t('son %s dk ziyaretçi'), R.minutes)) + ' · ' + esc(fmt(t('%s görüntülenme'), n(R.pageviews))) + '</div></div></div>' +
			'<svg class="flxan-live__bars" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">' + bars + '</svg>' +
			'<div class="flxan-live__axis"><span>' + esc(fmt(t('%s dk önce'), R.minutes)) + '</span><span>' + esc(t('şimdi')) + '</span></div>' +
			(R.visitors ? '' : '<div class="flxan-empty">' + esc(t('Şu an kimse yok')) + '</div>') +
			liveList(t('Sayfalar'), R.pages, function (x) { return esc(x.title); }, function (x) { return x.pageviews; }) +
			liveList(t('Referans bağlantıları'), R.codes, function (x) { return esc(x.label); }, function (x) { return x.visitors; }) +
			liveList(t('Ülkeler'), R.countries, function (x) { return flag(x.value) + esc(x.label); }, function (x) { return x.visitors; }) +
			liveList(t('Yönlendiren siteler'), R.referrers, function (x) { return esc(x.host); }, function (x) { return x.pageviews; });
	}
	function flag(cc) {
		if (!/^[A-Z]{2}$/.test(cc || '')) return '';
		// Emoji bayrak yok: WordPress emoji betiği onu dış görsele çeviriyor (kırık görünüyor), Windows'ta da çıkmıyor.
		return '<span class="flxan-cc" aria-hidden="true">' + cc + '</span>';
	}

	// --- Tablolar ---
	function table(items, opt) {
		if (!items.length) return '<div class="flxan-empty">' + esc(opt.empty || t('Bu aralıkta veri yok.')) + '</div>';
		var max = 0, key = opt.sort || 'pageviews';
		items.forEach(function (it) { max = Math.max(max, it[key] || 0); });
		var h = '<table class="flxan-table"><thead><tr><th>' + esc(opt.title) + '</th>' + opt.cols.map(function (c, i) { return '<th class="n' + (i ? ' n2' : '') + '">' + esc(c[1]) + '</th>'; }).join('') + '</tr></thead><tbody>';
		items.forEach(function (it, i) {
			var pct = max ? (it[key] || 0) / max * 100 : 0;
			h += '<tr class="' + (opt.click ? 'flxan-row' : '') + '" data-i="' + i + '"' + (opt.click ? ' tabindex="0"' : '') + '><td class="flxan-lbl"><span class="bar" style="width:' + pct.toFixed(1) + '%"></span><span class="txt">' + opt.label(it) + '</span></td>' +
				opt.cols.map(function (c, j) { return '<td class="n' + (j ? ' n2' : '') + '">' + (c[2] ? c[2](it[c[0]]) : n(it[c[0]])) + '</td>'; }).join('') + '</tr>';
		});
		return h + '</tbody></table>';
	}
	function bindRows(box, items, fn) {
		box.querySelectorAll('.flxan-row').forEach(function (r) {
			var go = function () { fn(items[+r.getAttribute('data-i')]); };
			r.addEventListener('click', go);
			r.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
		});
	}
	var COLS = [['visitors', t('Ziyaretçi')], ['pageviews', t('Görüntülenme')]];
	function pageLabel(it) { return esc(it.title) + (it.title !== it.path ? '<small>' + esc(it.path) + '</small>' : ''); }

	var LISTS = {
		pages: { title: t('Sayfalar'), cols: COLS, label: pageLabel, click: openPage, type: 'pages' },
		referrers: { title: t('Site'), cols: COLS, sort: 'visitors', label: function (it) { return esc(it.host) + '<small>' + esc(C.channels[it.channel] || '') + '</small>'; }, type: 'referrers', click: function (it) { window.open('https://' + it.host, '_blank', 'noopener'); } },
		notfound: { title: t('Adres'), cols: COLS, label: function (it) { return esc(it.path); }, type: 'notfound' }
	};

	function panel(id, title, extra, wide) {
		var p = el('<div class="flxan-panel' + (wide ? ' flxan-wide' : '') + '" id="flxan-p-' + id + '"><div class="flxan-panel__head"><h2>' + esc(title) + '</h2>' + (extra || '') + '</div><div class="flxan-body"></div></div>');
		grid.appendChild(p);
		return p;
	}
	function moreBtn(p, cfg, params, total, shown) {
		if (total <= shown) return;
		var m = el('<div class="flxan-more"><button type="button" class="flxan-btn">' + esc(fmt(t('Tümünü gör (%s)'), n(total))) + '</button></div>');
		m.querySelector('button').addEventListener('click', function () { openList(cfg, params); });
		p.querySelector('.flxan-body').appendChild(m);
	}

	function renderGrid(D) {
		grid.innerHTML = '';
		var rp = rangeParams();

		renderMap(D);
		renderFunnel(D.funnel);
		renderRefs(D.refs);

		var p = panel('channel', t('Trafik kaynakları'), '', false);
		p.querySelector('.flxan-body').innerHTML = table(D.dims.channel.items, { title: t('Kanal'), cols: [['visitors', t('Ziyaretçi')], ['pageviews', t('Görüntülenme')]], sort: 'visitors', label: function (it) { return esc(it.label); } });

		p = panel('roles', t('Rollere göre'), '', false);
		var segItems = D.segments || [];
		p.querySelector('.flxan-body').innerHTML = table(segItems, { title: t('Ziyaretçi türü'), cols: COLS, sort: 'visitors', click: true, empty: t('Bu aralıkta veri yok.'), label: function (it) { return esc(it.label) + (it.value === '' ? '<small>' + esc(t('giriş yapmamış')) + '</small>' : ''); } });
		bindRows(p, segItems, function (it) { S.seg = it.value === '' ? 'guest' : it.value; writeHash(); load(); });
		p.querySelector('.flxan-body').appendChild(el('<p class="flxan-note">' + esc(t('Satıra tıklayınca bütün ekran o ziyaretçi türüne süzülür. Yalnız dışarıdan gelen trafik için üstten "Dışarıdan gelenler"i seçin.')) + '</p>'));

		p = panel('pages', t('En çok görüntülenen sayfalar'), '', false);
		p.querySelector('.flxan-body').innerHTML = table(D.pages.items, LISTS.pages);
		bindRows(p, D.pages.items, openPage);
		moreBtn(p, LISTS.pages, rp, D.pages.total, D.pages.items.length);

		p = panel('refsites', t('Yönlendiren siteler'), '', false);
		p.querySelector('.flxan-body').innerHTML = table(D.referrers.items, Object.assign({ empty: t('Henüz başka siteden gelen yok.') }, LISTS.referrers));
		bindRows(p, D.referrers.items, LISTS.referrers.click);
		moreBtn(p, LISTS.referrers, rp, D.referrers.total, D.referrers.items.length);

		tabbedDim('tech', t('Cihaz ve tarayıcı'), ['device', 'browser', 'os', 'lang'], D);
		var hasCamp = ['utm_source', 'utm_medium', 'utm_campaign'].some(function (k) { return D.dims[k].items.length; });
		if (hasCamp) tabbedDim('camp', t('Kampanyalar (UTM)'), ['utm_source', 'utm_medium', 'utm_campaign'], D);

		renderEvents(D);

		if (D.notfound.items.length) {
			p = panel('nf', t('Bulunamayan sayfalar (404)'), '', false);
			p.querySelector('.flxan-body').innerHTML = table(D.notfound.items, LISTS.notfound);
			moreBtn(p, LISTS.notfound, rp, D.notfound.total, D.notfound.items.length);
		}
	}

	// --- Harita ---
	function renderMap(D) {
		var items = D.dims.country.items.filter(function (x) { return /^[A-Z]{2}$/.test(x.value); });
		var p = panel('map', t('Ülkelere göre ziyaretçi'), '', true);
		var body = p.querySelector('.flxan-body');
		var W = window.FLXAN_WORLD;
		var by = {}, max = 0;
		items.forEach(function (x) { by[x.value] = x; max = Math.max(max, x.visitors); });
		var svg = '';
		if (W) {
			svg = '<svg viewBox="0 0 ' + W.w + ' ' + W.h + '" role="img" aria-label="' + esc(t('Dünya haritası')) + '">';
			Object.keys(W.p).forEach(function (cc) {
				var x = by[cc], lvl = x ? Math.max(1, Math.ceil(Math.sqrt(x.visitors / max) * 5)) : 0;
				svg += '<path d="' + W.p[cc] + '" class="l' + lvl + '" data-cc="' + cc + '"/>';
			});
			svg += '</svg>';
		}
		var list = items.slice(0, 10);
		var lmax = list.length ? list[0].visitors : 0;
		body.innerHTML = '<div class="flxan-map"><div class="flxan-map__svg">' + svg + '<div class="flxan-tip"></div>' +
			'<div class="flxan-map__legend"><span>' + esc(t('Az')) + '</span><i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i><span>' + esc(t('Çok')) + '</span></div></div>' +
			'<div class="flxan-map__list">' + (list.length ? '<table class="flxan-table"><thead><tr><th>' + esc(t('Ülke')) + '</th><th class="n">' + esc(t('Ziyaretçi')) + '</th></tr></thead><tbody>' + list.map(function (x) {
				return '<tr><td class="flxan-lbl"><span class="bar" style="width:' + (lmax ? x.visitors / lmax * 100 : 0).toFixed(1) + '%"></span><span class="txt">' + flag(x.value) + esc(x.label) + '</span></td><td class="n">' + n(x.visitors) + '</td></tr>';
			}).join('') + '</tbody></table>' : '<div class="flxan-empty">' + esc(t('Bu aralıkta ülke bilgisi yok.')) + '</div>') + '</div></div>';
		var tip = body.querySelector('.flxan-tip'), box = body.querySelector('.flxan-map__svg');
		body.querySelectorAll('path').forEach(function (path) {
			path.addEventListener('mousemove', function (e) {
				var cc = path.getAttribute('data-cc'), x = by[cc], r = box.getBoundingClientRect();
				var name = x ? x.label : cc;
				try { if (!x && window.Intl && Intl.DisplayNames) name = new Intl.DisplayNames([C.locale], { type: 'region' }).of(cc); } catch (er) {}
				tip.innerHTML = '<b>' + esc(name) + '</b><br>' + esc(t('Ziyaretçi')) + ': ' + n(x ? x.visitors : 0);
				tip.style.left = (e.clientX - r.left) + 'px';
				tip.style.top = (e.clientY - r.top - 8) + 'px';
				tip.style.display = 'block';
			});
			path.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
		});
	}

	// --- Huni ---
	function renderFunnel(F) {
		if (!F || !F.found) return;
		var fsel = F.funnels.length > 1 ? '<select class="flxan-fsel" aria-label="' + esc(t('Huni')) + '">' + F.funnels.map(function (f) { return '<option value="' + f.id + '"' + (f.id === F.id ? ' selected' : '') + '>' + esc(f.name) + '</option>'; }).join('') + '</select>' : '';
		var tabs = '<div class="flxan-tabs" role="tablist">' + Object.keys(C.by).map(function (k) { return '<button type="button" role="tab" data-k="' + k + '" aria-selected="' + (S.by === k) + '">' + esc(C.by[k]) + '</button>'; }).join('') + '</div>';
		var p = panel('funnel', fmt(t('Huni: %s'), F.name), fsel + tabs, true);
		var body = p.querySelector('.flxan-body');
		function draw(F) {
			var first = F.steps[0] ? F.steps[0].count : 0;
			var h = '<div class="flxan-funnel" style="--n:' + F.steps.length + '">';
			F.steps.forEach(function (st, i) {
				var pct = first ? st.count / first * 100 : 0;
				h += '<div class="flxan-fstep"><div class="flxan-fstep__head"><span class="flxan-fstep__no">' + (i + 1) + '</span><span class="flxan-fstep__label">' + esc(st.label) + '</span></div>' +
					'<div class="flxan-fstep__bar"><span style="height:' + Math.max(pct, st.count ? 2 : 0).toFixed(1) + '%;--w:' + Math.max(pct, st.count ? 2 : 0).toFixed(1) + '%"></span></div>' +
					'<div class="flxan-fstep__val">' + n(st.count) + '</div><div class="flxan-fstep__rate">' + pf.format(st.rate) + '%' + (i && st.drop > 0 ? ' <span class="flxan-drop" title="' + esc(t('Önceki adımdan kayıp')) + '">−' + pf.format(st.drop) + '%</span>' : '') + '</div></div>';
			});
			h += '</div>';
			if (!first) h += '<div class="flxan-empty">' + esc(t('Bu aralıkta huniye giren ziyaret yok.')) + '</div>';
			if (F.groups && F.groups.length) {
				h += '<div class="flxan-scroll"><table class="flxan-table flxan-ftable"><thead><tr><th>' + esc(C.by[F.by] || '') + '</th>' + F.steps.map(function (st, i) { return '<th class="n" title="' + esc(st.label) + '">' + (i + 1) + '. ' + esc(st.label) + '</th>'; }).join('') + '<th class="n">' + esc(t('Tamamlama')) + '</th></tr></thead><tbody>';
				F.groups.forEach(function (g) {
					h += '<tr' + (F.by === 'rc' && g.value ? ' class="flxan-row" tabindex="0" data-rc="' + esc(g.value) + '"' : '') + '><td class="flxan-lbl"><span class="txt">' + (F.by === 'country' ? flag(g.value) : '') + esc(g.label) + '</span></td>' + g.counts.map(function (c, i) {
						var r = g.counts[0] ? c / g.counts[0] : 0;
						return '<td class="n"><span class="flxan-heat" style="--a:' + (0.08 + r * 0.6).toFixed(2) + '">' + n(c) + '</span></td>';
					}).join('') + '<td class="n"><b>' + pf.format(g.rate) + '%</b></td></tr>';
				});
				h += '</tbody></table></div>';
			}
			h += '<p class="flxan-note">' + esc(t('Her ziyaretçinin gün içinde ulaştığı en ileri adım sayılır. Adımları Ayarlar › Huniler\'den değiştirebilirsiniz.')) + (C.settings ? ' <a href="' + esc(C.settings) + '">' + esc(t('Huni ayarları')) + '</a>' : '') + '</p>';
			body.innerHTML = h;
			body.querySelectorAll('tr[data-rc]').forEach(function (r) { r.addEventListener('click', function () { setRc(r.getAttribute('data-rc')); }); });
		}
		function reload() {
			body.style.opacity = '.5';
			api('funnel', Object.assign({ id: S.fid || F.id, by: S.by }, rangeParams())).then(function (R) { body.style.opacity = ''; draw(R); }).catch(function () { body.style.opacity = ''; });
		}
		p.querySelectorAll('[role=tab]').forEach(function (b) {
			b.addEventListener('click', function () {
				S.by = b.getAttribute('data-k');
				p.querySelectorAll('[role=tab]').forEach(function (x) { x.setAttribute('aria-selected', x === b); });
				reload();
			});
		});
		var fs = p.querySelector('.flxan-fsel');
		if (fs) fs.addEventListener('change', function () { S.fid = +fs.value; p.querySelector('h2').textContent = fmt(t('Huni: %s'), fs.options[fs.selectedIndex].text); reload(); });
		if (S.by !== F.by || (S.fid && S.fid !== F.id)) reload(); else draw(F);
	}

	// --- Referans bağlantıları ---
	function renderRefs(items) {
		var add = C.refedit ? '<button type="button" class="flxan-btn flxan-btn--primary flxan-addref">+ ' + esc(t('Yeni bağlantı')) + '</button>' : '';
		var p = panel('codes', t('Referans bağlantıları'), add, true);
		var body = p.querySelector('.flxan-body');
		var h = '<div class="flxan-refform" hidden><input type="text" class="flxan-in" name="name" maxlength="80" placeholder="' + esc(t('Ad (ör. Ayşe\'nin Instagram\'ı)')) + '"><input type="text" class="flxan-in" name="code" maxlength="32" placeholder="' + esc(t('Kod (ör. ayse-ig)')) + '"><button type="button" class="flxan-btn flxan-btn--primary">' + esc(t('Kaydet')) + '</button><span class="flxan-refout"></span></div>';
		if (!items.length) {
			h += '<div class="flxan-empty">' + esc(fmt(t('Henüz referans bağlantısı yok. Sitenin adresini paylaştığınız yerde sonuna ?r=kod ekleyin (ör. %s); buraya ad verirseniz raporlarda o adla görünür.'), C.home.replace(/\/$/, '') + '/?r=ayse-ig')) + '</div>';
		} else {
			var max = 0; items.forEach(function (x) { max = Math.max(max, x.visitors); });
			h += '<div class="flxan-scroll"><table class="flxan-table flxan-reftable"><thead><tr><th>' + esc(t('Bağlantı')) + '</th><th class="n">' + esc(t('Ziyaretçi')) + '</th><th class="n n2">' + esc(t('Ziyaret')) + '</th><th class="n n2">' + esc(t('Sayfa / ziyaret')) + '</th><th class="n n2">' + esc(t('Ort. süre')) + '</th><th class="n">' + esc(t('En ileri adım')) + '</th><th class="n">' + esc(t('Bağış')) + '</th><th></th></tr></thead><tbody>';
			items.forEach(function (x, i) {
				h += '<tr data-i="' + i + '"><td class="flxan-lbl"><span class="bar" style="width:' + (max ? x.visitors / max * 100 : 0).toFixed(1) + '%"></span><span class="txt"><a href="#" class="flxan-rcgo" title="' + esc(t('Bu bağlantıyla gelenlerin ne yaptığını göster')) + '">' + esc(x.label) + '</a><small>?r=' + esc(x.code) + '</small></span></td>' +
					'<td class="n">' + n(x.visitors) + '</td><td class="n n2">' + n(x.sessions) + '</td><td class="n n2">' + pf.format(x.pages_per) + '</td><td class="n n2">' + esc(dur(x.avg_duration)) + '</td>' +
					'<td class="n">' + (x.deepest ? '<span class="flxan-step" title="' + esc(x.deepest_label) + '">' + x.deepest + '. ' + esc(x.deepest_label) + '</span>' : '—') + '</td>' +
					'<td class="n">' + (x.donations ? n(x.donations) + ' · ' + money(x.amount) : '—') + '</td>' +
					'<td class="n flxan-acts"><button type="button" class="flxan-ico flxan-copy" title="' + esc(t('Bağlantıyı kopyala')) + '">⧉</button>' + (C.refedit ? '<button type="button" class="flxan-ico flxan-edit" title="' + esc(t('Adını değiştir')) + '">✎</button>' + (x.name ? '<button type="button" class="flxan-ico flxan-del" title="' + esc(t('Adı sil')) + '">×</button>' : '') : '') + '</td></tr>';
			});
			h += '</tbody></table></div>';
		}
		h += '<p class="flxan-note">' + esc(t('Bağlantıyla gelen kişiye 30 günlük bir çerez konur; sonraki sayfaları, sonraki günleri ve bağışı da o koda sayılır. Bir satıra tıklayınca bütün ekran o bağlantıyla gelenlere süzülür.')) + '</p>';
		body.innerHTML = h;
		var form = body.querySelector('.flxan-refform'), nameIn = form.querySelector('[name=name]'), codeIn = form.querySelector('[name=code]'), out = form.querySelector('.flxan-refout'), touched = false;
		var slug = function (v) { return v.toLocaleLowerCase('tr').replace(/ı/g, 'i').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 32); };
		nameIn.addEventListener('input', function () { if (!touched) codeIn.value = slug(nameIn.value); });
		codeIn.addEventListener('input', function () { touched = true; });
		var addBtn = p.querySelector('.flxan-addref');
		if (addBtn) addBtn.addEventListener('click', function () { form.hidden = !form.hidden; if (!form.hidden) nameIn.focus(); });
		function save(code, name, del) {
			return fetch(C.rest + 'refs/save?_locale=user', { method: 'POST', headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ code: code, name: name || '', delete: !!del }) })
				.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || r.status); return j; }); });
		}
		form.querySelector('button').addEventListener('click', function () {
			var code = slug(codeIn.value || nameIn.value);
			if (!code) { codeIn.focus(); return; }
			save(code, nameIn.value.trim()).then(function (r) {
				out.innerHTML = '<code>' + esc(r.url) + '</code> <button type="button" class="flxan-btn">' + esc(t('Kopyala')) + '</button>';
				out.querySelector('button').addEventListener('click', function (e) { copy(r.url, e.target); });
				copy(r.url);
				setTimeout(load, 600);
			}).catch(function (e) { out.textContent = e.message; });
		});
		body.querySelectorAll('.flxan-reftable tbody tr').forEach(function (tr) {
			var x = items[+tr.getAttribute('data-i')];
			tr.querySelector('.flxan-rcgo').addEventListener('click', function (e) { e.preventDefault(); setRc(x.code); });
			tr.querySelector('.flxan-copy').addEventListener('click', function (e) { copy(x.url, e.target); });
			var ed = tr.querySelector('.flxan-edit');
			if (ed) ed.addEventListener('click', function () { var v = window.prompt(fmt(t('"%s" için ad'), x.code), x.name || ''); if (v !== null) save(x.code, v.trim()).then(load); });
			var dl = tr.querySelector('.flxan-del');
			if (dl) dl.addEventListener('click', function () { if (window.confirm(t('Ad silinsin mi? İstatistikler kalır, kod adıyla görünür.'))) save(x.code, '', true).then(load); });
		});
	}

	function tabbedDim(id, title, kinds, D) {
		var available = kinds.filter(function (k) { return k !== 'country' || D.dims[k].items.length; });
		var tabs = '<div class="flxan-tabs" role="tablist">' + available.map(function (k) { return '<button type="button" role="tab" data-k="' + k + '" aria-selected="' + (S.tabs[id] === k) + '">' + esc(C.kinds[k]) + '</button>'; }).join('') + '</div>';
		var p = panel(id, title, tabs, false);
		function show(k) {
			S.tabs[id] = k;
			p.querySelectorAll('[role=tab]').forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-k') === k); });
			var cfg = { title: C.kinds[k], cols: COLS, label: function (it) { return esc(it.label); }, type: 'dim', kind: k };
			var body = p.querySelector('.flxan-body');
			body.innerHTML = table(D.dims[k].items, cfg);
			moreBtn(p, cfg, Object.assign({ kind: k }, rangeParams()), D.dims[k].total, D.dims[k].items.length);
		}
		p.querySelectorAll('[role=tab]').forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-k')); }); });
		if (available.indexOf(S.tabs[id]) < 0) S.tabs[id] = available[0];
		show(S.tabs[id]);
	}

	function evCfg(name) {
		var showAmount = name === 'donation';
		var cols = [['hits', t('Sayı')], ['uniques', t('Tekil')]];
		if (showAmount) cols = [['hits', t('Sayı')], ['amount', t('Tutar'), money]];
		return { title: t('Olay'), cols: cols, sort: 'hits', type: 'events', name: name, label: function (it) { return (name ? '' : esc(it.label) + ' · ') + esc(it.param || '—'); } };
	}
	function renderEvents(D) {
		var p = panel('events', t('Tıklamalar ve dönüşümler'), '', true);
		var body = p.querySelector('.flxan-body');
		if (!D.event_sum.length) {
			body.innerHTML = '<div class="flxan-empty">' + esc(t('Bu aralıkta olay yok. Dış bağlantı, indirme, telefon/WhatsApp tıklaması, arama ve bağışlar burada görünür.')) + '</div>';
			return;
		}
		var chips = '<div class="flxan-chips"><button type="button" class="flxan-chip" data-n="" aria-pressed="' + (!S.eventName) + '">' + esc(t('Tümü')) + '</button>' +
			D.event_sum.map(function (e) { return '<button type="button" class="flxan-chip" data-n="' + esc(e.name) + '" aria-pressed="' + (S.eventName === e.name) + '">' + esc(e.label) + '<b>' + n(e.hits) + '</b>' + (e.amount ? ' · ' + money(e.amount) : '') + '</button>'; }).join('') + '</div>';
		body.innerHTML = chips + '<div class="flxan-evt"></div>';
		var box = body.querySelector('.flxan-evt');
		function show(name, data) {
			S.eventName = name;
			body.querySelectorAll('.flxan-chip').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-n') === name); });
			var cfg = evCfg(name);
			box.innerHTML = table(data.items, cfg);
			var old = p.querySelector('.flxan-more'); if (old) old.remove();
			moreBtn(p, cfg, Object.assign({ name: name }, rangeParams()), data.total, data.items.length);
		}
		body.querySelectorAll('.flxan-chip').forEach(function (b) {
			b.addEventListener('click', function () {
				var name = b.getAttribute('data-n');
				if (!name) { show('', D.events); return; }
				api('list', Object.assign({ type: 'events', name: name, limit: 10 }, rangeParams())).then(function (r) { show(name, r); });
			});
		});
		show('', D.events);
	}

	// --- Pencereler ---
	function modal(title) {
		var m = el('<div class="flxan flxan-modal" role="dialog" aria-modal="true"><div class="flxan-modal__box"><div class="flxan-modal__head"><h2></h2><button type="button" class="flxan-x" aria-label="' + esc(t('Kapat')) + '">×</button></div><div class="flxan-modal__body"><div class="flxan-skel" style="min-height:120px;border-radius:10px"></div></div></div></div>');
		m.querySelector('h2').textContent = title;
		var close = function () { m.remove(); document.removeEventListener('keydown', key); };
		var key = function (e) { if (e.key === 'Escape') close(); };
		m.addEventListener('click', function (e) { if (e.target === m) close(); });
		m.querySelector('.flxan-x').addEventListener('click', close);
		document.addEventListener('keydown', key);
		document.body.appendChild(m);
		m.querySelector('.flxan-x').focus();
		return m.querySelector('.flxan-modal__body');
	}

	function openList(cfg, params) {
		var body = modal(cfg.title === t('Olay') ? t('Tıklamalar ve dönüşümler') : cfg.title);
		var state = { offset: 0, q: '', items: [] };
		var limit = 50;
		function fetchPage(reset) {
			if (reset) { state.offset = 0; state.items = []; }
			var q = Object.assign({}, params, { type: cfg.type, limit: limit, offset: state.offset });
			if (cfg.kind) q.kind = cfg.kind;
			if (state.q) q.q = state.q;
			return api('list', q).then(function (r) {
				state.items = state.items.concat(r.items);
				state.offset += r.items.length;
				var canSearch = cfg.type === 'pages' || cfg.type === 'referrers' || cfg.type === 'notfound';
				body.innerHTML = (canSearch ? '<p><input type="search" class="flxan-search" style="width:100%" placeholder="' + esc(t('Ara')) + '" value="' + esc(state.q) + '"></p>' : '') + table(state.items, cfg) +
					(state.offset < r.total ? '<div class="flxan-more"><button type="button" class="flxan-btn">' + esc(t('Daha fazla')) + '</button></div>' : '');
				if (cfg.click) bindRows(body, state.items, function (it) { cfg.click(it); });
				var more = body.querySelector('.flxan-more button');
				if (more) more.addEventListener('click', function () { more.setAttribute('aria-disabled', 'true'); fetchPage(false); });
				var s = body.querySelector('.flxan-search');
				if (s) {
					var tm;
					s.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(function () { state.q = s.value.trim(); fetchPage(true).then(function () { var x = body.querySelector('.flxan-search'); if (x) { x.focus(); x.setSelectionRange(x.value.length, x.value.length); } }); }, 350); });
				}
			}).catch(function () { body.innerHTML = '<div class="flxan-empty">' + esc(t('Yüklenemedi. Biraz sonra yeniden deneyin.')) + '</div>'; });
		}
		fetchPage(true);
	}

	function openPage(it) {
		var target = it.post_id ? String(it.post_id) : it.path;
		var body = modal(it.title || it.path);
		api('page', Object.assign({ target: target }, rangeParams())).then(function (r) {
			var items = (S.data && S.data.series.group === 'day') ? fill(r.series, S.data.range.start, S.data.range.end) : r.series;
			body.innerHTML = '<div class="flxan-cards" style="grid-template-columns:repeat(2,minmax(0,1fr))"><div class="flxan-card"><div class="flxan-card__label">' + esc(t('Ziyaretçi')) + '</div><div class="flxan-card__value">' + n(r.visitors) + '</div></div><div class="flxan-card"><div class="flxan-card__label">' + esc(t('Görüntülenme')) + '</div><div class="flxan-card__value">' + n(r.pageviews) + '</div></div></div>' +
				'<div class="flxan-chart"></div><p class="flxan-note">' + (r.paths || []).map(function (p) { return '<a href="' + esc(C.home.replace(/\/$/, '') + p) + '" target="_blank" rel="noopener">' + esc(p) + '</a>'; }).join(' · ') + '</p>';
			chart(body.querySelector('.flxan-chart'), items, 'day');
		}).catch(function () { body.innerHTML = '<div class="flxan-empty">' + esc(t('Yüklenemedi. Biraz sonra yeniden deneyin.')) + '</div>'; });
	}
	function fill(series, s, e) {
		var by = {}; series.forEach(function (x) { by[x.d] = x; });
		var out = [], p = s.split('-'), cur = new Date(+p[0], +p[1] - 1, +p[2]), q = e.split('-'), end = new Date(+q[0], +q[1] - 1, +q[2]);
		while (cur <= end) {
			var k = cur.getFullYear() + '-' + String(cur.getMonth() + 1).padStart(2, '0') + '-' + String(cur.getDate()).padStart(2, '0');
			out.push(by[k] || { d: k, visitors: 0, pageviews: 0 });
			cur.setDate(cur.getDate() + 1);
		}
		return out;
	}

	// --- Yükleme ---
	var seq = 0;
	function load(silent) {
		var my = ++seq;
		if (!silent) { app.setAttribute('aria-busy', 'true'); skeleton(!S.data); }
		sel.value = S.range;
		custom.classList.toggle('on', S.range === 'custom');
		return api('report', rangeParams()).then(function (D) {
			if (my !== seq) return;
			S.data = D;
			if (S.range === 'custom') { var i = custom.querySelectorAll('input'); i[0].value = D.range.start; i[1].value = D.range.end; }
			syncFilters(D);
			var filtered = S.seg !== 'all' || S.rc;
			var empty = !filtered && !D.totals.pageviews && !D.prev.pageviews && !D.realtime.pageviews;
			banner.hidden = !empty && !filtered;
			if (empty) banner.innerHTML = '<p><b>' + esc(t('Henüz veri yok.')) + '</b></p><p>' + esc(t('Siteyi giriş yapmadığınız bir tarayıcıda (ya da telefonda) açın; ziyaret birkaç dakika içinde burada görünür.')) + '</p>';
			else if (filtered) {
				var parts = [];
				if (S.seg !== 'all') parts.push(segSel.options[segSel.selectedIndex] ? segSel.options[segSel.selectedIndex].text : S.seg);
				if (S.rc) parts.push(rcSel.options[rcSel.selectedIndex] ? rcSel.options[rcSel.selectedIndex].text : S.rc);
				banner.innerHTML = '<p class="flxan-filterbar">' + esc(t('Süzgeç:')) + ' <b>' + esc(parts.join(' · ')) + '</b> <button type="button" class="flxan-btn">' + esc(t('Süzgeci kaldır')) + '</button></p>';
				banner.querySelector('button').addEventListener('click', function () { S.seg = 'all'; S.rc = ''; writeHash(); load(); });
			}
			renderRealtime(D.realtime);
			chartPanel.querySelector('h2').textContent = D.range.label + ' · ' + d(D.range.start, { day: 'numeric', month: 'long', year: 'numeric' }) + ' – ' + d(D.range.end, { day: 'numeric', month: 'long', year: 'numeric' });
			lastKpi = '';
			renderKpi(D);
			// Sessiz yenilemede açık form / odaktaki alan bozulmasın.
			var busy = silent && (grid.querySelector('.flxan-refform:not([hidden])') || grid.contains(document.activeElement));
			if (!busy) { var y = window.scrollY; renderGrid(D); if (silent) window.scrollTo(0, y); }
		}).catch(function () {
			if (my !== seq) return;
			banner.hidden = false;
			banner.innerHTML = '<p>' + esc(t('Yüklenemedi. Biraz sonra yeniden deneyin.')) + '</p>';
		}).then(function () { if (my === seq) app.setAttribute('aria-busy', 'false'); });
	}

	// Canlı kutu: 30 sn'de bir, sekme görünürken; hata olursa 5 dk bekler.
	// Göstergeler + grafik değiştiyse yeniden çizilir (fare üzerindeyken titremesin).
	var lastKpi = '';
	function renderKpi(K) {
		var sig = JSON.stringify([K.totals, K.visits, K.series]);
		if (sig === lastKpi) return;
		var first = lastKpi === '';
		lastKpi = sig;
		renderCards(K);
		chart(chartPanel.querySelector('.flxan-chart'), K.series.items, K.series.group);
		if (!first) { cards.classList.remove('flxan-fresh'); void cards.offsetWidth; cards.classList.add('flxan-fresh'); }
	}

	// Canlı: sayfa açık ve görünürken 5 sn'de bir (canlı panel + üst göstergeler + grafik); tablolar 60 sn'de bir sessizce.
	// Hata olursa 5 dk bekler.
	var RT = 5000, FULL = 60000, rtWait = RT, rtTimer = 0, lastFull = Date.now();
	function rtTick() {
		clearTimeout(rtTimer);
		if (document.hidden) return;
		if (Date.now() - lastFull >= FULL) {
			lastFull = Date.now();
			load(true).then(function () { rtTimer = setTimeout(rtTick, RT); });
			return;
		}
		api('realtime', Object.assign({ kpi: 1 }, rangeParams())).then(function (R) {
			rtWait = RT;
			renderRealtime(R);
			if (R.kpi && S.data) { Object.assign(S.data, R.kpi); renderKpi(S.data); }
		}).catch(function () { rtWait = 300000; }).then(function () { rtTimer = setTimeout(rtTick, rtWait); });
	}
	document.addEventListener('visibilitychange', function () { clearTimeout(rtTimer); if (!document.hidden) rtTimer = setTimeout(rtTick, 1000); });

	var resizeT;
	window.addEventListener('resize', function () { clearTimeout(resizeT); resizeT = setTimeout(function () { if (S.data) chart(chartPanel.querySelector('.flxan-chart'), S.data.series.items, S.data.series.group); }, 200); });

	var pageFromHash = readHash();
	load().then(function () {
		rtTimer = setTimeout(rtTick, rtWait);
		if (pageFromHash) { writeHash(); openPage({ path: pageFromHash, title: pageFromHash }); }
	});
})();
