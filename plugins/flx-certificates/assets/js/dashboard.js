/* Flux PDF & Certificates · Dashboard: rakamlar + SVG grafikler (kütüphanesiz) */
(function () {
	'use strict';
	var A = window.FLXSA, esc = A.esc;
	var root = document.getElementById('flxs-dash');
	if (!root) return;
	var DATA = JSON.parse(document.getElementById('flxs-dash-data').textContent);
	var locale = DATA.locale;
	function format(source) {
		var args = Array.prototype.slice.call(arguments, 1), next = 0;
		return (DATA.strings[source] || source).replace(/%(?:(\d+)\$)?s/g, function (match, position) {
			return String(args[position ? Number(position) - 1 : next++] ?? match);
		});
	}
	function text() { return esc(format.apply(null, arguments)); }
	var PRESETS = [['today', text('Today')], ['7', text('7 days')], ['30', text('30 days')], ['90', text('90 days')], ['month', text('This month')], ['year', text('This year')], ['all', text('All')]];
	var STATUS = [['sent', format('Sent')], ['failed', format('Failed')], ['waiting', format('Waiting')], ['cancelled', format('Cancelled')]];
	var weekday = new Intl.DateTimeFormat(locale, { weekday: 'long' });
	var shortWeekday = new Intl.DateTimeFormat(locale, { weekday: 'short' });
	var monday = new Date(2024, 0, 1);
	function formatWeekday(day, formatter) { return formatter.format(new Date(monday.getFullYear(), 0, 1 + day)); }

	var f = { preset: '30', from: '', to: '', ref: '', tpl: '', event: '', metric: 'fidan', table: false };
	try { Object.assign(f, JSON.parse(localStorage.getItem('flxs_dash') || '{}')); } catch (e) {}
	f.table = false;
	var qs = new URLSearchParams(location.search);
	if (qs.has('event')) { f.event = qs.get('event'); f.preset = 'all'; }
	if (f.event && f.event !== '0' && !DATA.events.some(function (e) { return String(e.id) === String(f.event); })) f.event = '';
	var S = null; // son veri

	function n(x) { return Number(x || 0).toLocaleString(locale); }
	function percent(x) { return x.toLocaleString(locale, { style: 'percent', maximumFractionDigits: 0 }); }
	function pad(x) { return (x < 10 ? '0' : '') + x; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parse(k) { var p = k.split('-'); return new Date(+p[0], +p[1] - 1, +(p[2] || 1)); }
	function dateLabel(key, options) { return parse(key).toLocaleDateString(locale, options); }
	function shortLabel(key) {
		return dateLabel(key, S.unit === 'month' ? { month: 'short', year: '2-digit' } : { month: 'short', day: 'numeric' });
	}
	function longLabel(key) {
		return dateLabel(key, S.unit === 'month' ? { month: 'long', year: 'numeric' } : { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
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
		'<span class="flxs-dash__dates"><input type="date" data-from aria-label="' + text('Start date') + '"> – <input type="date" data-to aria-label="' + text('End date') + '"></span>' +
		(DATA.events.length ? '<select data-event aria-label="' + text('Event') + '"><option value="">' + text('All events') + '</option>' +
			DATA.events.filter(function (e) { return e.status === 'open'; }).map(function (e) { return '<option value="' + esc(e.id) + '">' + esc(e.name) + '</option>'; }).join('') +
			(DATA.events.some(function (e) { return e.status !== 'open'; }) ? '<optgroup label="' + text('Archive') + '">' + DATA.events.filter(function (e) { return e.status !== 'open'; }).map(function (e) { return '<option value="' + esc(e.id) + '">' + esc(e.name) + '</option>'; }).join('') + '</optgroup>' : '') +
			'<option value="0">' + text('Without an event') + '</option></select>' : '') +
		'<select data-ref aria-label="' + text('Referring staff') + '"><option value="">' + text('All staff') + '</option><option value="0">' + text('Without a referrer') + '</option>' + DATA.refs.map(function (r) { return '<option value="' + esc(r.id) + '">' + esc(r.name) + '</option>'; }).join('') + '</select>' +
		(DATA.templates.length > 1 ? '<select data-tpl aria-label="' + text('Template') + '"><option value="">' + text('All templates') + '</option>' + DATA.templates.map(function (t) { return '<option value="' + esc(t.id) + '">' + esc(t.name) + '</option>'; }).join('') + '</select>' : '') +
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
			.catch(function (e) { body.innerHTML = '<div class="flxs-card">' + text('Could not load data:') + ' ' + esc(e.message || e) + '</div>'; })
			.then(function () { root.classList.remove('is-busy'); });
	}

	/* ---------- Kartlar ---------- */
	function delta(cur, prev) {
		if (!prev && !cur) return '';
		if (!prev) return '<span class="flxs-delta is-up">▲ ' + text('new') + '</span>';
		var p = Math.round((cur - prev) / prev * 100);
		if (!p) return '<span class="flxs-delta">= ' + text('Same as the previous period') + '</span>';
		return '<span class="flxs-delta ' + (p > 0 ? 'is-up' : 'is-down') + '">' + (p > 0 ? '▲ ' : '▼ ') + percent(Math.abs(p) / 100) + '</span>';
	}
	function kpi(label, value, sub, extra) {
		return '<div class="flxs-kpi"><div class="flxs-kpi__label">' + label + '</div><div class="flxs-kpi__value">' + value + '</div><div class="flxs-kpi__sub">' + (sub || '&nbsp;') + '</div>' + (extra || '') + '</div>';
	}
	function render() {
		var totals = S.totals, p = S.prev;
		var sent = 0, failed = 0, waiting = 0;
		S.messages.forEach(function (m) { sent += m.sent; failed += m.failed; waiting += m.waiting; });
		var rate = sent + failed ? Math.round(sent / (sent + failed) * 100) : null;
		var prevTxt = text('Compared with the previous %s days', n(S.days));
		var periodTxt = S.from === S.to ? longLabelDay(S.from) : longLabelDay(S.from) + ' – ' + longLabelDay(S.to);

		body.innerHTML =
			'<div class="flxs-dash__top">' +
			'<div class="flxs-hero"><div class="flxs-hero__label">' + (S.event ? esc(S.event.name) + (S.event.status !== 'open' ? ' (' + text('archived') + ')' : '') + ' · ' + text('Saplings collected') : text('Saplings gifted to date')) + '</div><div class="flxs-hero__value">' + n(S.all_fidan) + '</div>' +
			'<div class="flxs-hero__sub">' + text('%2$s saplings during %1$s', periodTxt, n(totals.fidan)) + '</div>' +
			(S.event && S.event.target ? '<div class="flxs-hero__goal"><div class="flxs-event__bar"><span style="width:' + Math.min(100, S.all_fidan / S.event.target * 100) + '%"></span></div><small>' + text('Sapling goal') + ': ' + n(S.event.target) + ' · ' + percent(S.all_fidan / S.event.target) + '</small></div>' : '') +
			'<span class="flxs-hero__leaf" aria-hidden="true"></span></div>' +
			'<div class="flxs-kpis">' +
			kpi(text('Saplings'), n(totals.fidan), delta(totals.fidan, p.fidan) + ' <small>' + prevTxt + '</small>') +
			kpi(text('Certificates'), n(totals.certs), delta(totals.certs, p.certs) + (totals.revoked ? ' <small>' + text('Excluding %s revoked', n(totals.revoked)) + '</small>' : '')) +
			kpi(text('People'), n(totals.people), totals.new_people ? text('%s first-time recipients', n(totals.new_people)) : text('No new people')) +
			kpi(text('Per certificate'), totals.certs ? (totals.fidan / totals.certs).toLocaleString(locale, { maximumFractionDigits: 1 }) : '–', text('Average saplings')) +
			kpi(text(S.unit === 'month' ? 'Busiest month' : 'Busiest day'), totals.max_day ? n(totals.max_day.fidan) + ' <small>' + text('Saplings') + '</small>' : '–', totals.max_day ? esc(longLabelShort(totals.max_day.key)) : '') +
			kpi(text('Delivery'), rate === null ? '–' : percent(rate / 100), text('%s sent', n(sent)) + (failed ? ' · <span class="flxs-danger">' + text('%s failed', n(failed)) + '</span>' : '') + (waiting ? ' · ' + text('%s waiting', n(waiting)) : '')) +
			'</div></div>' +

			'<div class="flxs-card flxs-dash__main">' +
			'<div class="flxs-card__head"><h2 class="flxs-card__title">' + text(f.metric === 'fidan' ? (S.unit === 'month' ? 'Monthly saplings' : 'Daily saplings') : (S.unit === 'month' ? 'Monthly certificates' : 'Daily certificates')) + ' <small>' + esc(periodTxt) + '</small></h2>' +
			'<div class="flxs-card__acts"><span class="flxs-seg flxs-seg--sm" data-metric><button type="button" data-m="fidan">' + text('Saplings') + '</button><button type="button" data-m="certs">' + text('Certificates') + '</button><button type="button" data-m="cum">' + text('Cumulative') + '</button></span>' +
			'<button type="button" class="button button-small" data-table>' + text(f.table ? 'Chart' : 'Table') + '</button></div></div>' +
			'<div class="flxs-chart" data-chart="main"></div></div>' +

			'<div class="flxs-dash__grid">' +
			'<div class="flxs-card"><h2 class="flxs-card__title">' + text('Days of the week') + ' <small>' + text('Saplings by day of the week') + '</small></h2><div class="flxs-chart" data-chart="week"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">' + text('Deliveries') + ' <small>' + text('Channel and status') + '</small></h2><div data-chart="msg"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">' + text('Referring staff') + ' <small>' + text('Saplings · certificates') + '</small></h2><div data-chart="ref"></div></div>' +
			'<div class="flxs-card"><h2 class="flxs-card__title">' + text('Templates') + '</h2><div data-chart="tpl"></div></div>' +
			(!S.event && DATA.events.length ? '<div class="flxs-card"><h2 class="flxs-card__title">' + text('Events') + ' <small>' + text('Saplings · certificates') + '</small></h2><div data-chart="evt"></div></div>' : '') +
			'</div>' +

			'<div class="flxs-card"><h2 class="flxs-card__title">' + text('Top sapling donors') + ' <small>' + text('Top 10') + '</small></h2><div data-chart="top"></div></div>';

		body.querySelectorAll('[data-m]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-m') === f.metric); });
		drawAll();
	}
	function longLabelDay(key) {
		return dateLabel(key, { day: 'numeric', month: 'long', ...(parse(key).getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) });
	}
	function longLabelShort(key) {
		return dateLabel(key, S.unit === 'month' ? { month: 'long', year: 'numeric' } : { weekday: 'long', month: 'long', day: 'numeric' });
	}

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
		hbars(body.querySelector('[data-chart=ref]'), S.by_ref, format('No certificates in this period.'));
		hbars(body.querySelector('[data-chart=tpl]'), S.by_tpl, format('No certificates in this period.'));
		var ce = body.querySelector('[data-chart=evt]'); if (ce) hbars(ce, S.by_event, format('No certificates in this period.'));
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
			if (av > 0) s += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + y(av) + '" y2="' + y(av) + '" class="flxs-avg"/><text x="' + (L + 4) + '" y="' + (y(av) - 5) + '" text-anchor="start" class="flxs-tick flxs-tick--avg">' + text('Avg.') + ' ' + av.toLocaleString(locale, { maximumFractionDigits: 1 }) + '</text>';
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
		if (!S.totals.certs && !S.series.some(function (x) { return x.certs; })) { el.innerHTML = empty(format('No certificates in this period.')); return; }
		var cum = 0, items = S.series.map(function (x) { cum += x.fidan; return Object.assign({ cum: cum }, x); });
		var key = f.metric === 'cum' ? 'cum' : f.metric;
		vbars(el, items, {
			aria: format('Daily saplings chart'),
			value: function (x) { return x[key]; },
			label: function (x) { return shortLabel(x.key); },
			line: key === 'cum',
			avg: key !== 'cum',
			valueLabels: key !== 'cum',
			tip: function (x) {
				return '<strong>' + esc(longLabel(x.key)) + '</strong><div>' + text('Saplings') + ' <b>' + n(x.fidan) + '</b></div><div>' + text('Certificates') + ' <b>' + n(x.certs) + '</b></div>' + (key === 'cum' ? '<div>' + text('Total') + ' <b>' + n(x.cum) + '</b></div>' : '');
			}
		});
	}
	function drawWeek(el) {
		if (!S.totals.certs) { el.innerHTML = empty(format('No data.')); return; }

		var items = S.weekday.map(function (x, i) { return Object.assign({ i: i }, x); });
		vbars(el, items, {
			h: 210, maxBar: 40, valueLabels: true, aria: format('Saplings by day of the week'),
			value: function (x) { return x.fidan; },
			label: function (x) { return formatWeekday(x.i, shortWeekday); },
			tip: function (x) { return '<strong>' + esc(formatWeekday(x.i, weekday)) + '</strong><div>' + text('Saplings') + ' <b>' + n(x.fidan) + '</b></div><div>' + text('Certificates') + ' <b>' + n(x.certs) + '</b></div>'; }
		});
	}

	/* Yatay çubuklar (HTML) */
	function hbars(el, list, emptyTxt) {
		if (!list.length) { el.innerHTML = empty(emptyTxt); return; }
		var max = Math.max.apply(null, list.map(function (x) { return x.fidan; }).concat([1]));
		el.innerHTML = '<div class="flxs-hb">' + list.slice(0, 12).map(function (x) {
			return '<div class="flxs-hb__row" title="' + esc(format('%1$s: %2$s saplings, %3$s certificates', x.name, n(x.fidan), n(x.certs))) + '"><div class="flxs-hb__name">' + esc(x.name) + '</div>' +
				'<div class="flxs-hb__track"><span style="width:' + Math.max(x.fidan ? 2 : 0, x.fidan / max * 100) + '%"></span></div>' +
				'<div class="flxs-hb__val"><b>' + n(x.fidan) + '</b> <small>· ' + n(x.certs) + '</small></div></div>';
		}).join('') + '</div>';
	}
	function drawMsg(el) {
		var any = S.messages.some(function (m) { return m.sent + m.failed + m.waiting + m.cancelled; });
		if (!any) { el.innerHTML = empty(format('No deliveries in this period.')); return; }
		var tot = {}; STATUS.forEach(function (s) { tot[s[0]] = 0; });
		S.messages.forEach(function (m) { STATUS.forEach(function (s) { tot[s[0]] += m[s[0]]; }); });
		var max = Math.max.apply(null, S.messages.map(function (m) { return m.sent + m.failed + m.waiting + m.cancelled; }));
		el.innerHTML = '<div class="flxs-legend">' + STATUS.filter(function (s) { return tot[s[0]]; }).map(function (s) { return '<span><i class="flxs-st flxs-st--' + s[0] + '"></i>' + esc(s[1]) + ' <b>' + n(tot[s[0]]) + '</b></span>'; }).join('') + '</div>' +
			'<div class="flxs-hb">' + S.messages.map(function (m) {
				var sum = m.sent + m.failed + m.waiting + m.cancelled;
				return '<div class="flxs-hb__row"><div class="flxs-hb__name">' + esc(m.label) + '</div><div class="flxs-hb__track flxs-hb__track--stack">' +
					STATUS.filter(function (s) { return m[s[0]]; }).map(function (s) {
						return '<span class="flxs-st--' + s[0] + '" style="width:' + (m[s[0]] / max * 100) + '%" data-tipm="' + esc(m.label + ' · ' + s[1] + ': ' + n(m[s[0]])) + '"></span>';
					}).join('') + '</div><div class="flxs-hb__val"><b>' + n(sum) + '</b></div></div>';
			}).join('') + '</div>';
		el.querySelectorAll('[data-tipm]').forEach(function (s) {
			s.addEventListener('mousemove', function (ev) { showTip(esc(s.getAttribute('data-tipm')), ev); });
			s.addEventListener('mouseleave', hideTip);
		});
	}
	function drawTop(el) {
		if (!S.top.length) { el.innerHTML = empty(format('No people registered in this period.')); return; }
		el.innerHTML = '<table class="widefat striped flxs-dtable flxs-top"><thead><tr><th>#</th><th>' + text('People') + '</th><th class="ph">' + text('Phone') + '</th><th class="num">' + text('Certificates') + '</th><th class="num">' + text('Saplings') + '</th></tr></thead><tbody>' +
			S.top.map(function (p, i) { return '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(p.url) + '">' + esc(p.name) + '</a></td><td class="flxs-muted ph">' + esc(p.phone || '') + '</td><td class="num">' + n(p.certs) + '</td><td class="num"><b>' + n(p.fidan) + '</b></td></tr>'; }).join('') +
			'</tbody></table>';
	}
	function seriesTable() {
		var cum = 0;
		return '<div class="flxs-dash__tablewrap"><table class="widefat striped flxs-dtable"><thead><tr><th>' + text(S.unit === 'month' ? 'Month' : 'Day') + '</th><th class="num">' + text('Saplings') + '</th><th class="num">' + text('Certificates') + '</th><th class="num">' + text('Total saplings') + '</th></tr></thead><tbody>' +
			S.series.map(function (x) { cum += x.fidan; return '<tr><td>' + esc(longLabel(x.key)) + '</td><td class="num">' + n(x.fidan) + '</td><td class="num">' + n(x.certs) + '</td><td class="num">' + n(cum) + '</td></tr>'; }).join('') +
			'</tbody></table></div>';
	}
	function empty(t) { return '<div class="flxs-empty-chart">' + esc(t) + '</div>'; }

	function csv() {
		if (!S) return;
		var rows = [[S.unit === 'month' ? format('Month') : format('Day'), format('Saplings'), format('Certificates')]];
		S.series.forEach(function (x) { rows.push([x.key, x.fidan, x.certs]); });
		rows.push([]); rows.push([format('Referring staff'), format('Saplings'), format('Certificates')]);
		S.by_ref.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		rows.push([]); rows.push([format('Event'), format('Saplings'), format('Certificates')]);
		S.by_event.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		rows.push([]); rows.push([format('Template'), format('Saplings'), format('Certificates')]);
		S.by_tpl.forEach(function (x) { rows.push([x.name, x.fidan, x.certs]); });
		var txt = '﻿' + rows.map(function (r) { return r.map(function (c) { c = String(c); return /[;"\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c; }).join(';'); }).join('\r\n');
		A.download(new Blob([txt], { type: 'text/csv;charset=utf-8' }), 'fidan-dashboard-' + S.from + '_' + S.to + '.csv');
	}

	load();
})();
