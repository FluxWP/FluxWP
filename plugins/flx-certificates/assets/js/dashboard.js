/* Flux Certificates · Dashboard: rakamlar + SVG grafikler (kütüphanesiz) */
(function () {
	'use strict';
	var A = window.FLXSA, esc = A.esc;
	var root = document.getElementById('flxs-dash');
	if (!root) return;
	var DATA = JSON.parse(document.getElementById('flxs-dash-data').textContent);
	var MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
	var DAYS = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
	var PRESETS = [['today', 'Bugün'], ['7', '7 gün'], ['30', '30 gün'], ['90', '90 gün'], ['month', 'Bu ay'], ['year', 'Bu yıl'], ['all', 'Tümü']];
	var STATUS = [['sent', 'Gönderildi'], ['failed', 'Başarısız'], ['waiting', 'Bekliyor'], ['cancelled', 'İptal']];

	var f = { preset: '30', from: '', to: '', ref: '', tpl: '', event: '', metric: 'fidan', table: false };
	try { Object.assign(f, JSON.parse(localStorage.getItem('flxs_dash') || '{}')); } catch (e) {}
	f.table = false;
	var qs = new URLSearchParams(location.search);
	if (qs.has('event')) { f.event = qs.get('event'); f.preset = 'all'; }
	if (f.event && f.event !== '0' && !DATA.events.some(function (e) { return String(e.id) === String(f.event); })) f.event = '';
	var S = null; // son veri

	function n(x) { return Number(x || 0).toLocaleString('tr-TR'); }
	function pad(x) { return (x < 10 ? '0' : '') + x; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parse(k) { var p = k.split('-'); return new Date(+p[0], +p[1] - 1, +(p[2] || 1)); }
	function shortLabel(k) { var d = parse(k); return S.unit === 'month' ? MONTHS[d.getMonth()].slice(0, 3) + ' ' + String(d.getFullYear()).slice(2) : d.getDate() + ' ' + MONTHS[d.getMonth()].slice(0, 3); }
	function longLabel(k) {
		var d = parse(k);
		if (S.unit === 'month') return MONTHS[d.getMonth()] + ' ' + d.getFullYear();
		return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ', ' + DAYS[(d.getDay() + 6) % 7];
	}
	function rangeFor(p) {
		var t = new Date(), a = new Date(t);
		if (p === 'today') return [ymd(t), ymd(t)];
		if (p === 'month') { a.setDate(1); return [ymd(a), ymd(t)]; }
		if (p === 'year') { a.setMonth(0, 1); return [ymd(a), ymd(t)]; }
		if (p === 'all') return ['all', ymd(t)];
		if (p === 'custom') return [f.from, f.to];
		a.setDate(a.getDate() - (+p - 1));
		return [ymd(a), ymd(t)];
	}
	function save() { try { localStorage.setItem('flxs_dash', JSON.stringify(f)); } catch (e) {} }

	/* ---------- İskelet ---------- */
	root.innerHTML =
		'<div class="flxs-dash__filters">' +
		'<span class="flxs-seg flxs-seg--sm" data-presets>' + PRESETS.map(function (p) { return '<button type="button" data-p="' + p[0] + '">' + p[1] + '</button>'; }).join('') + '</span>' +
		'<span class="flxs-dash__dates"><input type="date" data-from aria-label="Başlangıç"> – <input type="date" data-to aria-label="Bitiş"></span>' +
		(DATA.events.length ? '<select data-event aria-label="Etkinlik"><option value="">Tüm etkinlikler</option>' +
			DATA.events.filter(function (e) { return e.status === 'open'; }).map(function (e) { return '<option value="' + esc(e.id) + '">' + esc(e.name) + '</option>'; }).join('') +
			(DATA.events.some(function (e) { return e.status !== 'open'; }) ? '<optgroup label="Arşiv">' + DATA.events.filter(function (e) { return e.status !== 'open'; }).map(function (e) { return '<option value="' + esc(e.id) + '">' + esc(e.name) + '</option>'; }).join('') + '</optgroup>' : '') +
			'<option value="0">Etkinliksiz</option></select>' : '') +
		'<select data-ref aria-label="Referans personel"><option value="">Tüm personel</option><option value="0">Referanssız</option>' + DATA.refs.map(function (r) { return '<option value="' + esc(r.id) + '">' + esc(r.name) + '</option>'; }).join('') + '</select>' +
		(DATA.templates.length > 1 ? '<select data-tpl aria-label="Şablon"><option value="">Tüm şablonlar</option>' + DATA.templates.map(function (t) { return '<option value="' + esc(t.id) + '">' + esc(t.name) + '</option>'; }).join('') + '</select>' : '') +
		'<button type="button" class="button" data-csv><span class="dashicons dashicons-download"></span> CSV</button>' +
		'</div>' +
		'<div class="flxs-dash__body"></div>' +
		'<div class="flxs-tip" hidden></div>';
	var body = root.querySelector('.flxs-dash__body');
	var tip = root.querySelector('.flxs-tip');

	function syncFilters() {
		root.querySelectorAll('[data-p]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-p') === f.preset); });
		var r = rangeFor(f.preset);
		root.querySelector('[data-from]').value = S && f.preset === 'all' ? S.from : (r[0] === 'all' ? '' : r[0]);
		root.querySelector('[data-to]').value = r[1];
		root.querySelector('[data-ref]').value = f.ref;
		var t = root.querySelector('[data-tpl]'); if (t) t.value = f.tpl;
		var ev = root.querySelector('[data-event]'); if (ev) ev.value = f.event;
	}
	root.querySelector('.flxs-dash__filters').addEventListener('click', function (e) {
		var b = e.target.closest('[data-p]');
		if (b) { f.preset = b.getAttribute('data-p'); load(); return; }
		if (e.target.closest('[data-csv]')) csv();
	});
	root.querySelector('.flxs-dash__filters').addEventListener('change', function (e) {
		var t = e.target;
		if (t.hasAttribute('data-from') || t.hasAttribute('data-to')) {
			f.preset = 'custom';
			f.from = root.querySelector('[data-from]').value;
			f.to = root.querySelector('[data-to]').value;
			if (!f.from || !f.to) return;
		}
		if (t.hasAttribute('data-ref')) f.ref = t.value;
		if (t.hasAttribute('data-event')) { f.event = t.value; if (f.event && f.event !== '0') f.preset = 'all'; }
		if (t.hasAttribute('data-tpl')) f.tpl = t.value;
		load();
	});

	var seq = 0;
	function load() {
		save();
		syncFilters();
		var r = rangeFor(f.preset), my = ++seq;
		root.classList.add('is-busy');
		A.api('GET', 'stats?from=' + encodeURIComponent(r[0]) + '&to=' + encodeURIComponent(r[1]) + '&ref=' + encodeURIComponent(f.ref) + '&tpl=' + encodeURIComponent(f.tpl || '') + '&event=' + encodeURIComponent(f.event || ''))
			.then(function (d) { if (my !== seq) return; S = d; syncFilters(); render(); })
			.catch(function (e) { body.innerHTML = '<div class="flxs-card">Veri alınamadı: ' + esc(e.message || e) + '</div>'; })
			.then(function () { root.classList.remove('is-busy'); });
	}

	/* ---------- Kartlar ---------- */
	function delta(cur, prev) {
		if (!prev && !cur) return '';
		if (!prev) return '<span class="flxs-delta is-up">▲ yeni</span>';
		var p = Math.round((cur - prev) / prev * 100);
		if (!p) return '<span class="flxs-delta">= önceki dönemle aynı</span>';
		return '<span class="flxs-delta ' + (p > 0 ? 'is-up' : 'is-down') + '">' + (p > 0 ? '▲ %' + p : '▼ %' + Math.abs(p)) + '</span>';
	}
	function kpi(label, value, sub, extra) {
		return '<div class="flxs-kpi"><div class="flxs-kpi__label">' + label + '</div><div class="flxs-kpi__value">' + value + '</div><div class="flxs-kpi__sub">' + (sub || '&nbsp;') + '</div>' + (extra || '') + '</div>';
	}
	function render() {
		var t = S.totals, p = S.prev;
		var sent = 0, failed = 0, waiting = 0;
		S.messages.forEach(function (m) { sent += m.sent; failed += m.failed; waiting += m.waiting; });
		var rate = sent + failed ? Math.round(sent / (sent + failed) * 100) : null;
		var prevTxt = 'önceki ' + S.days + ' güne göre';
		var periodTxt = S.from === S.to ? longLabelDay(S.from) : longLabelDay(S.from) + ' – ' + longLabelDay(S.to);

		body.innerHTML =
			'<div class="flxs-dash__top">' +
			'<div class="flxs-hero"><div class="flxs-hero__label">' + (S.event ? esc(S.event.name) + (S.event.status !== 'open' ? ' (arşiv)' : '') + ' · toplanan fidan' : 'Bugüne kadar hediye edilen fidan') + '</div><div class="flxs-hero__value">' + n(S.all_fidan) + '</div>' +
			'<div class="flxs-hero__sub">' + esc(periodTxt) + ' döneminde <strong>' + n(t.fidan) + '</strong> fidan</div>' +
			(S.event && S.event.target ? '<div class="flxs-hero__goal"><div class="flxs-event__bar"><span style="width:' + Math.min(100, S.all_fidan / S.event.target * 100) + '%"></span></div><small>Hedef ' + n(S.event.target) + ' fidan · %' + Math.round(S.all_fidan / S.event.target * 100) + '</small></div>' : '') +
			'<span class="flxs-hero__leaf" aria-hidden="true"></span></div>' +
			'<div class="flxs-kpis">' +
			kpi('Fidan', n(t.fidan), delta(t.fidan, p.fidan) + ' <small>' + prevTxt + '</small>') +
			kpi('Sertifika', n(t.certs), delta(t.certs, p.certs) + (t.revoked ? ' <small>' + t.revoked + ' iptal hariç</small>' : '')) +
			kpi('Kişi', n(t.people), t.new_people ? '<strong>' + n(t.new_people) + '</strong> ilk kez' : 'yeni kişi yok') +
			kpi('Sertifika başına', t.certs ? (t.fidan / t.certs).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) : '–', 'ortalama fidan') +
			kpi('En yoğun ' + (S.unit === 'month' ? 'ay' : 'gün'), t.max_day ? n(t.max_day.fidan) + ' <small>fidan</small>' : '–', t.max_day ? esc(longLabelShort(t.max_day.key)) : '') +
			kpi('Gönderim', rate === null ? '–' : '%' + rate, n(sent) + ' gönderildi' + (failed ? ' · <span class="flxs-danger">' + n(failed) + ' hata</span>' : '') + (waiting ? ' · ' + n(waiting) + ' bekliyor' : '')) +
			'</div></div>' +

			'<div class="flxs-card flxs-dash__main">' +
			'<div class="flxs-card__head"><h2 class="flxs-card__title">' + (S.unit === 'month' ? 'Aylık' : 'Günlük') + ' ' + (f.metric === 'fidan' ? 'fidan' : 'sertifika') + ' <small>' + esc(periodTxt) + '</small></h2>' +
			'<div class="flxs-card__acts"><span class="flxs-seg flxs-seg--sm" data-metric><button type="button" data-m="fidan">Fidan</button><button type="button" data-m="certs">Sertifika</button><button type="button" data-m="cum">Kümülatif</button></span>' +
			'<button type="button" class="button button-small" data-table>' + (f.table ? 'Grafik' : 'Tablo') + '</button></div></div>' +
			'<div class="flxs-chart" data-chart="main"></div></div>' +

			'<div class="flxs-dash__grid">' +
			'<div class="flxs-card"><h2 class="flxs-card__title">Haftanın günleri <small>hangi gün daha çok fidan</small></h2><div class="flxs-chart" data-chart="week"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">Gönderimler <small>kanal ve durum</small></h2><div data-chart="msg"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">Referans personel <small>fidan · sertifika</small></h2><div data-chart="ref"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">Şablonlar</h2><div data-chart="tpl"></div></div>' +
			(!S.event && DATA.events.length ? '<div class="flxs-card"><h2 class="flxs-card__title">Etkinlikler <small>fidan · sertifika</small></h2><div data-chart="evt"></div></div>' : '') +
			'</div>' +

			'<div class="flxs-card"><h2 class="flxs-card__title">En çok fidan hediye edenler <small>ilk 10</small></h2><div data-chart="top"></div></div>';

		body.querySelectorAll('[data-m]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-m') === f.metric); });
		drawAll();
	}
	function longLabelDay(k) { var d = parse(k); return d.getDate() + ' ' + MONTHS[d.getMonth()] + (d.getFullYear() !== new Date().getFullYear() ? ' ' + d.getFullYear() : ''); }
	function longLabelShort(k) { var d = parse(k); return S.unit === 'month' ? MONTHS[d.getMonth()] + ' ' + d.getFullYear() : d.getDate() + ' ' + MONTHS[d.getMonth()] + ', ' + DAYS[(d.getDay() + 6) % 7]; }

	body.addEventListener('click', function (e) {
		var m = e.target.closest('[data-m]');
		if (m) { f.metric = m.getAttribute('data-m'); save(); render(); return; }
		if (e.target.closest('[data-table]')) { f.table = !f.table; render(); }
	});

	function drawAll() {
		if (!S) return;
		var main = body.querySelector('[data-chart=main]');
		if (f.table) main.innerHTML = seriesTable(); else drawMain(main);
		drawWeek(body.querySelector('[data-chart=week]'));
		drawMsg(body.querySelector('[data-chart=msg]'));
		hbars(body.querySelector('[data-chart=ref]'), S.by_ref, 'Bu dönemde sertifika yok.');
		hbars(body.querySelector('[data-chart=tpl]'), S.by_tpl, 'Bu dönemde sertifika yok.');
		var ce = body.querySelector('[data-chart=evt]'); if (ce) hbars(ce, S.by_event, 'Bu dönemde sertifika yok.');
		drawTop(body.querySelector('[data-chart=top]'));
	}
	var lastW = 0;
	window.addEventListener('resize', A.debounce(function () { if (root.clientWidth !== lastW) { lastW = root.clientWidth; drawAll(); } }, 150));

	/* ---------- Ölçek yardımcıları ---------- */
	function niceMax(v) {
		if (v <= 4) return Math.max(1, Math.ceil(v));
		var p = Math.pow(10, Math.floor(Math.log10(v))), m = v / p;
		var s = m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10;
		return s * p;
	}
	function barPath(x, y, w, h, r) {
		r = Math.min(r, w / 2, h);
		return 'M' + x + ',' + (y + h) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y + 'H' + (x + w - r) + 'Q' + (x + w) + ',' + y + ' ' + (x + w) + ',' + (y + r) + 'V' + (y + h) + 'Z';
	}
	function showTip(html, ev) {
		tip.innerHTML = html;
		tip.hidden = false;
		var r = root.getBoundingClientRect(), tw = tip.offsetWidth, th = tip.offsetHeight;
		var x = ev.clientX - r.left + 14, y = ev.clientY - r.top - th - 10;
		if (x + tw > root.clientWidth) x = ev.clientX - r.left - tw - 14;
		if (y < 0) y = ev.clientY - r.top + 18;
		tip.style.left = Math.max(0, x) + 'px';
		tip.style.top = y + 'px';
	}
	function hideTip() { tip.hidden = true; }

	/* Dikey çubuk grafik (ana grafik + haftanın günleri) */
	function vbars(el, items, o) {
		var W = Math.max(280, el.clientWidth), H = o.h || 260;
		var L = 40, R = 8, T = o.valueLabels ? 30 : 18, B = 26, pw = W - L - R, ph = H - T - B;
		var vals = items.map(o.value), max = niceMax(Math.max.apply(null, vals.concat([0])));
		var nb = items.length, band = pw / nb, bw = Math.max(2, Math.min(o.maxBar || 34, band * 0.72));
		var y = function (v) { return T + ph - v / max * ph; };
		var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + esc(o.aria) + '">';
		for (var g = 0; g <= 4; g++) {
			var gv = max / 4 * g, gy = y(gv);
			s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + gy + '" y2="' + gy + '" class="' + (g ? 'flxs-grid' : 'flxs-axis') + '"/>';
			s += '<text x="' + (L - 6) + '" y="' + (gy + 4) + '" class="flxs-tick" text-anchor="end">' + n(Math.round(gv * 10) / 10) + '</text>';
		}
		var step = Math.max(1, Math.ceil(nb / Math.max(1, pw / (o.labelW || 56))));
		var maxI = vals.indexOf(Math.max.apply(null, vals));
		if (o.line) {
			var pts = items.map(function (it, i) { return (L + band * i + band / 2) + ',' + y(vals[i]); });
			s += '<path d="M' + (L + band / 2) + ',' + y(0) + 'L' + pts.join('L') + 'L' + (L + band * (nb - 1) + band / 2) + ',' + y(0) + 'Z" class="flxs-area"/>';
			s += '<polyline points="' + pts.join(' ') + '" class="flxs-line"/>';
		}
		items.forEach(function (it, i) {
			var cx = L + band * i + band / 2, v = vals[i];
			if (!o.line && v > 0) s += '<path d="' + barPath(cx - bw / 2, y(v), bw, y(0) - y(v), Math.min(4, bw / 2)) + '" class="flxs-bar" data-i="' + i + '"/>';
			if (o.line) s += '<circle cx="' + cx + '" cy="' + y(v) + '" r="4" class="flxs-dot" data-i="' + i + '"/>';
			if (i % step === 0 || (nb <= 12)) s += '<text x="' + cx + '" y="' + (H - 8) + '" class="flxs-tick" text-anchor="middle">' + esc(o.label(it)) + '</text>';
			if (o.valueLabels ? v > 0 : (i === maxI && v > 0 && !o.line)) {
				/* Dar çubuklarda yazı küçülür, çok darsa dikey yazılır */
				var lbl = n(v), fs = Math.max(9, Math.min(11.5, band * 0.9 / Math.max(1, lbl.length * 0.62)));
				if (band >= lbl.length * fs * 0.62 + 2) s += '<text x="' + cx + '" y="' + (y(v) - 5) + '" class="flxs-vlabel" text-anchor="middle" style="font-size:' + fs.toFixed(1) + 'px">' + lbl + '</text>';
				else if (band >= 7) s += '<text x="' + (cx + 3.5) + '" y="' + (y(v) - 4) + '" class="flxs-vlabel" text-anchor="start" transform="rotate(-90 ' + (cx + 3.5) + ' ' + (y(v) - 4) + ')" style="font-size:9.5px">' + lbl + '</text>';
			}
			s += '<rect x="' + (L + band * i) + '" y="' + T + '" width="' + band + '" height="' + ph + '" class="flxs-hit" data-i="' + i + '"/>';
		});
		if (o.avg && nb > 1) {
			var av = vals.reduce(function (a, b) { return a + b; }, 0) / nb;
			if (av > 0) s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + y(av) + '" y2="' + y(av) + '" class="flxs-avg"/><text x="' + (L + 4) + '" y="' + (y(av) - 5) + '" text-anchor="start" class="flxs-tick flxs-tick--avg">ort. ' + av.toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + '</text>';
		}
		s += '</svg>';
		el.innerHTML = s;
		el.querySelectorAll('.flxs-hit').forEach(function (h) {
			var i = +h.getAttribute('data-i');
			h.addEventListener('mousemove', function (ev) {
				el.querySelectorAll('[data-i="' + i + '"].flxs-bar,[data-i="' + i + '"].flxs-dot').forEach(function (b) { b.classList.add('is-hover'); });
				showTip(o.tip(items[i], i), ev);
			});
			h.addEventListener('mouseleave', function () {
				el.querySelectorAll('.is-hover').forEach(function (b) { b.classList.remove('is-hover'); });
				hideTip();
			});
		});
	}

	function drawMain(el) {
		if (!S.totals.certs && !S.series.some(function (x) { return x.certs; })) { el.innerHTML = empty('Bu dönemde sertifika yok.'); return; }
		var cum = 0, items = S.series.map(function (x) { cum += x.fidan; return Object.assign({ cum: cum }, x); });
		var key = f.metric === 'cum' ? 'cum' : f.metric;
		vbars(el, items, {
			aria: 'Günlük fidan grafiği',
			value: function (x) { return x[key]; },
			label: function (x) { return shortLabel(x.key); },
			line: key === 'cum',
			avg: key !== 'cum',
			valueLabels: key !== 'cum',
			tip: function (x) {
				return '<strong>' + esc(longLabel(x.key)) + '</strong><div>Fidan <b>' + n(x.fidan) + '</b></div><div>Sertifika <b>' + n(x.certs) + '</b></div>' + (key === 'cum' ? '<div>Toplam <b>' + n(x.cum) + '</b></div>' : '');
			}
		});
	}
	function drawWeek(el) {
		if (!S.totals.certs) { el.innerHTML = empty('Veri yok.'); return; }
		var short = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
		var items = S.weekday.map(function (x, i) { return Object.assign({ i: i }, x); });
		vbars(el, items, {
			h: 210, maxBar: 40, valueLabels: true, aria: 'Haftanın günlerine göre fidan',
			value: function (x) { return x.fidan; },
			label: function (x) { return short[x.i]; },
			tip: function (x) { return '<strong>' + DAYS[x.i] + '</strong><div>Fidan <b>' + n(x.fidan) + '</b></div><div>Sertifika <b>' + n(x.certs) + '</b></div>'; }
		});
	}

	/* Yatay çubuklar (HTML) */
	function hbars(el, list, emptyTxt) {
		if (!list.length) { el.innerHTML = empty(emptyTxt); return; }
		var max = Math.max.apply(null, list.map(function (x) { return x.fidan; }).concat([1]));
		el.innerHTML = '<div class="flxs-hb">' + list.slice(0, 12).map(function (x) {
			return '<div class="flxs-hb__row" title="' + esc(x.name + ': ' + x.fidan + ' fidan, ' + x.certs + ' sertifika') + '"><div class="flxs-hb__name">' + esc(x.name) + '</div>' +
				'<div class="flxs-hb__track"><span style="width:' + Math.max(x.fidan ? 2 : 0, x.fidan / max * 100) + '%"></span></div>' +
				'<div class="flxs-hb__val"><b>' + n(x.fidan) + '</b> <small>· ' + n(x.certs) + '</small></div></div>';
		}).join('') + '</div>';
	}
	function drawMsg(el) {
		var any = S.messages.some(function (m) { return m.sent + m.failed + m.waiting + m.cancelled; });
		if (!any) { el.innerHTML = empty('Bu dönemde gönderim yok.'); return; }
		var tot = {}; STATUS.forEach(function (s) { tot[s[0]] = 0; });
		S.messages.forEach(function (m) { STATUS.forEach(function (s) { tot[s[0]] += m[s[0]]; }); });
		var max = Math.max.apply(null, S.messages.map(function (m) { return m.sent + m.failed + m.waiting + m.cancelled; }));
		el.innerHTML = '<div class="flxs-legend">' + STATUS.filter(function (s) { return tot[s[0]]; }).map(function (s) { return '<span><i class="flxs-st flxs-st--' + s[0] + '"></i>' + s[1] + ' <b>' + n(tot[s[0]]) + '</b></span>'; }).join('') + '</div>' +
			'<div class="flxs-hb">' + S.messages.map(function (m) {
				var sum = m.sent + m.failed + m.waiting + m.cancelled;
				return '<div class="flxs-hb__row"><div class="flxs-hb__name">' + esc(m.label) + '</div><div class="flxs-hb__track flxs-hb__track--stack">' +
					STATUS.filter(function (s) { return m[s[0]]; }).map(function (s) {
						return '<span class="flxs-st--' + s[0] + '" style="width:' + (m[s[0]] / max * 100) + '%" data-tipm="' + esc(m.label + ' · ' + s[1] + ': ' + m[s[0]]) + '"></span>';
					}).join('') + '</div><div class="flxs-hb__val"><b>' + n(sum) + '</b></div></div>';
			}).join('') + '</div>';
		el.querySelectorAll('[data-tipm]').forEach(function (s) {
			s.addEventListener('mousemove', function (ev) { showTip(esc(s.getAttribute('data-tipm')), ev); });
			s.addEventListener('mouseleave', hideTip);
		});
	}
	function drawTop(el) {
		if (!S.top.length) { el.innerHTML = empty('Bu dönemde kayıtlı kişi yok.'); return; }
		el.innerHTML = '<table class="widefat striped flxs-dtable flxs-top"><thead><tr><th>#</th><th>Kişi</th><th class="ph">Telefon</th><th class="num">Sertifika</th><th class="num">Fidan</th></tr></thead><tbody>' +
			S.top.map(function (p, i) { return '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(p.url) + '">' + esc(p.name) + '</a></td><td class="flxs-muted ph">' + esc(p.phone || '') + '</td><td class="num">' + n(p.certs) + '</td><td class="num"><b>' + n(p.fidan) + '</b></td></tr>'; }).join('') +
			'</tbody></table>';
	}
	function seriesTable() {
		var cum = 0;
		return '<div class="flxs-dash__tablewrap"><table class="widefat striped flxs-dtable"><thead><tr><th>' + (S.unit === 'month' ? 'Ay' : 'Gün') + '</th><th class="num">Fidan</th><th class="num">Sertifika</th><th class="num">Toplam fidan</th></tr></thead><tbody>' +
			S.series.map(function (x) { cum += x.fidan; return '<tr><td>' + esc(longLabel(x.key)) + '</td><td class="num">' + n(x.fidan) + '</td><td class="num">' + n(x.certs) + '</td><td class="num">' + n(cum) + '</td></tr>'; }).join('') +
			'</tbody></table></div>';
	}
	function empty(t) { return '<div class="flxs-empty-chart">' + esc(t) + '</div>'; }

	function csv() {
		if (!S) return;
		var rows = [[S.unit === 'month' ? 'Ay' : 'Gün', 'Fidan', 'Sertifika']];
		S.series.forEach(function (x) { rows.push([x.key, x.fidan, x.certs]); });
		rows.push([]); rows.push(['Referans personel', 'Fidan', 'Sertifika']);
		S.by_ref.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		rows.push([]); rows.push(['Etkinlik', 'Fidan', 'Sertifika']);
		S.by_event.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		rows.push([]); rows.push(['Şablon', 'Fidan', 'Sertifika']);
		S.by_tpl.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		var txt = '﻿' + rows.map(function (r) { return r.map(function (c) { c = String(c); return /[;"\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c; }).join(';'); }).join('\r\n');
		A.download(new Blob([txt], { type: 'text/csv;charset=utf-8' }), 'fidan-dashboard-' + S.from + '_' + S.to + '.csv');
	}

	load();
})();
