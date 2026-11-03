/* Flux Forms · Dashboard. Vanilla JS, hand-written SVG charts, no external libraries.
 * Every piece of stored data is written with textContent / setAttribute, never innerHTML. */
(function () {
	'use strict';

	var B = window.FLXF_DASH;
	var root = document.getElementById('flxfd-app');
	if (!B || !root) return;

	var T = B.i18n || {};
	var LOC = B.locale || 'en-US';
	try { new Intl.NumberFormat(LOC); } catch (e) { LOC = 'en-US'; }
	var SVGNS = 'http://www.w3.org/2000/svg';
	var COLORS = {
		primary: '#4338CA',
		prev: '#8A9AA3',
		pay: { paid: '#4338CA', pending: '#B7791F', failed: '#B83227', refunded: '#6B7C88' }
	};
	var HEAT = ['#DCEFE3', '#A9D8BB', '#6DBE94', '#2E9A6D', '#3730A3'];

	/* ---------------- helpers ---------------- */

	function h(tag, attrs, kids) {
		var n = document.createElement(tag);
		setAttrs(n, attrs);
		append(n, kids);
		return n;
	}
	function s(tag, attrs, kids) {
		var n = document.createElementNS(SVGNS, tag);
		setAttrs(n, attrs);
		append(n, kids);
		return n;
	}
	function setAttrs(n, attrs) {
		if (!attrs) return;
		Object.keys(attrs).forEach(function (k) {
			var v = attrs[k];
			if (v === null || v === undefined || v === false) return;
			if (k === 'text') n.textContent = String(v);
			else if (k === 'class') n.setAttribute('class', v);
			else if (k.indexOf('on') === 0 && typeof v === 'function') n.addEventListener(k.slice(2), v);
			else n.setAttribute(k, String(v));
		});
	}
	function append(n, kids) {
		if (kids === null || kids === undefined || kids === false) return;
		if (!Array.isArray(kids)) kids = [kids];
		kids.forEach(function (k) {
			if (k === null || k === undefined || k === false) return;
			n.appendChild(typeof k === 'object' ? k : document.createTextNode(String(k)));
		});
	}
	function fmt(str) {
		var a = Array.prototype.slice.call(arguments, 1), i = 0;
		return String(str).replace(/%(\d+\$)?[sd]/g, function (m, p) { return p ? a[parseInt(p, 10) - 1] : a[i++]; });
	}
	function pad(x) { return (x < 10 ? '0' : '') + x; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parseDate(str) {
		var m = String(str || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/);
		if (!m) return null;
		return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
	}
	function addDays(dayStr, n) { var d = parseDate(dayStr); d.setDate(d.getDate() + n); return ymd(d); }
	var cacheNF = {};
	function nf(opts) {
		var key = JSON.stringify(opts || {});
		if (!cacheNF[key]) {
			try { cacheNF[key] = new Intl.NumberFormat(LOC, opts); } catch (e) { cacheNF[key] = new Intl.NumberFormat('en-US', opts && opts.style === 'currency' ? {} : opts); }
		}
		return cacheNF[key];
	}
	function num(v) { return nf({ maximumFractionDigits: 0 }).format(v || 0); }
	function compact(v) { return nf({ notation: 'compact', maximumFractionDigits: 1 }).format(v || 0); }
	function pct(v, digits) { return nf({ style: 'percent', maximumFractionDigits: digits === undefined ? 1 : digits }).format((v || 0) / 100); }
	function money(v, cur) {
		try { return new Intl.NumberFormat(LOC, { style: 'currency', currency: cur || 'TRY' }).format(v || 0); } catch (e) { return num(v) + ' ' + (cur || ''); }
	}
	function dfmt(d, o) { try { return new Intl.DateTimeFormat(LOC, o).format(d); } catch (e) { return ymd(d); } }
	function dayShort(str) { return dfmt(parseDate(str), { day: 'numeric', month: 'short' }); }
	function dayLong(str) { return dfmt(parseDate(str), { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }); }
	function dateTime(str) { return dfmt(parseDate(str), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
	function ago(str, nowStr) {
		var d = parseDate(str), now = parseDate(nowStr);
		if (!d || !now) return '';
		var sec = Math.round((d - now) / 1000), abs = Math.abs(sec);
		var units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
		try {
			var rtf = new Intl.RelativeTimeFormat(LOC, { numeric: 'auto' });
			for (var i = 0; i < units.length; i++) if (abs >= units[i][1]) return rtf.format(Math.round(sec / units[i][1]), units[i][0]);
			return rtf.format(0, 'minute');
		} catch (e) { return dateTime(str); }
	}
	/* Integer-friendly axis: a 1-2-2.5-5 step giving 3-5 intervals. */
	function niceScale(max) {
		if (max <= 4) return { top: 4, ticks: 4 };
		var raw = max / 4, p = Math.pow(10, Math.floor(Math.log10(raw))), m = raw / p;
		var step = (m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10) * p;
		if (step < 1) step = 1;
		var ticks = Math.ceil(max / step);
		return { top: ticks * step, ticks: ticks };
	}
	function store(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
	function load(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
	function panelLink(formId, msgId) {
		var u = B.panelUrl + '&flxf_form=' + encodeURIComponent(formId);
		if (msgId) u += '&flxf_msg=' + encodeURIComponent(msgId);
		return u;
	}
	function formTitle(id, title) { return title || (T.deleted + ' #' + id); }
	function empty(text) { return h('div', { class: 'flxfd-empty', text: text }); }
	function card(title, opts, kids) {
		opts = opts || {};
		return h('section', { class: 'flxfd-card' + (opts.cls ? ' ' + opts.cls : '') }, [
			h('header', { class: 'flxfd-card__head' }, [
				h('div', null, [h('h2', { class: 'flxfd-card__title', text: title }), opts.hint ? h('p', { class: 'flxfd-card__hint', text: opts.hint }) : null]),
				opts.aside || null
			]),
			kids
		]);
	}

	/* ---------------- tooltip ---------------- */

	var tip = h('div', { class: 'flxfd-tip', role: 'tooltip', hidden: 'hidden' });
	document.body.appendChild(tip);
	function showTip(ev, title, lines) {
		tip.textContent = '';
		append(tip, h('strong', { text: title }));
		(lines || []).forEach(function (l) {
			var row = h('span', { class: 'flxfd-tip__row' });
			if (l.color) append(row, h('i', { class: 'flxfd-sw' + (l.dashed ? ' is-dashed' : ''), style: 'background:' + l.color }));
			append(row, [h('span', { text: l.label }), h('b', { text: l.value })]);
			append(tip, row);
		});
		tip.hidden = false;
		var r = tip.getBoundingClientRect(), x = ev.clientX + 14, y = ev.clientY + 14;
		if (x + r.width > window.innerWidth - 8) x = ev.clientX - r.width - 14;
		if (x < 8) x = 8;
		if (y + r.height > window.innerHeight - 8) y = ev.clientY - r.height - 14;
		tip.style.left = x + 'px';
		tip.style.top = y + 'px';
	}
	function hideTip() { tip.hidden = true; }

	/* ---------------- state ---------------- */

	var state = { preset: '30', from: '', to: '', form: '' };
	var saved = load('flxfd_filters');
	if (saved && typeof saved === 'object') {
		['preset', 'from', 'to', 'form'].forEach(function (k) { if (typeof saved[k] === 'string') state[k] = saved[k]; });
	}
	var qs = new URLSearchParams(location.search);
	if (qs.get('form')) state.form = qs.get('form');
	if (state.form && !B.forms.some(function (f) { return String(f.id) === String(state.form); })) state.form = '';
	if (['7', '30', '90', 'custom'].indexOf(state.preset) < 0) state.preset = '30';
	if (state.preset === 'custom' && !(state.from && state.to)) state.preset = '30';

	function range() {
		if (state.preset === 'custom') return [state.from, state.to];
		var n = parseInt(state.preset, 10) || 30;
		return [addDays(B.today, -(n - 1)), B.today];
	}

	if (!B.forms.length) {
		root.textContent = '';
		append(root, h('div', { class: 'flxfd-card flxfd-emptystate' }, [
			h('h2', { text: T.noForms }),
			B.createUrl ? h('a', { class: 'flxfd-btn flxfd-btn--primary', href: B.createUrl, text: T.createForm }) : null
		]));
		return;
	}

	/* ---------------- filter bar ---------------- */

	var presetBtns = {};
	var seg = h('div', { class: 'flxfd-seg', role: 'group', 'aria-label': T.range });
	[['7', T.d7], ['30', T.d30], ['90', T.d90], ['custom', T.custom]].forEach(function (p) {
		var b = h('button', { type: 'button', 'data-p': p[0], text: p[1], onclick: function () {
			state.preset = p[0];
			if (p[0] === 'custom') {
				var r = range();
				if (!state.from) { state.from = addDays(B.today, -29); state.to = B.today; }
				inFrom.value = state.from || r[0]; inTo.value = state.to || r[1];
				syncBar(); inFrom.focus();
				return;
			}
			refresh();
		} });
		presetBtns[p[0]] = b;
		seg.appendChild(b);
	});
	var inFrom = h('input', { type: 'date', max: B.today, 'aria-label': T.from });
	var inTo = h('input', { type: 'date', max: B.today, 'aria-label': T.to });
	var applyBtn = h('button', { type: 'button', class: 'flxfd-btn', text: T.apply, onclick: function () {
		if (!inFrom.value || !inTo.value) return;
		state.from = inFrom.value < inTo.value ? inFrom.value : inTo.value;
		state.to = inFrom.value < inTo.value ? inTo.value : inFrom.value;
		refresh();
	} });
	var customBox = h('div', { class: 'flxfd-custom' }, [
		h('label', null, [h('span', { class: 'screen-reader-text', text: T.from }), inFrom]),
		h('span', { class: 'flxfd-dash', 'aria-hidden': 'true', text: '–' }),
		h('label', null, [h('span', { class: 'screen-reader-text', text: T.to }), inTo]),
		applyBtn
	]);
	var formSel = h('select', { 'aria-label': T.formFilter }, [h('option', { value: '', text: T.allForms })].concat(B.forms.map(function (f) {
		return h('option', { value: String(f.id), text: f.title });
	})));
	formSel.addEventListener('change', function () { state.form = formSel.value; refresh(); });
	var csvBtn = h('a', { class: 'flxfd-btn flxfd-btn--ghost', href: '#', title: T.csvHint }, [
		s('svg', { width: 16, height: 16, viewBox: '0 0 24 24', 'aria-hidden': 'true', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
			s('path', { d: 'M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2' })
		]),
		h('span', { text: T.csv })
	]);
	var bar = h('div', { class: 'flxfd-filters' }, [seg, customBox, h('div', { class: 'flxfd-filters__end' }, [formSel, csvBtn])]);
	var body = h('div', { class: 'flxfd-body' });
	root.textContent = '';
	append(root, [bar, body]);

	function syncBar() {
		Object.keys(presetBtns).forEach(function (k) {
			presetBtns[k].classList.toggle('is-on', k === state.preset);
			presetBtns[k].setAttribute('aria-pressed', k === state.preset ? 'true' : 'false');
		});
		customBox.classList.toggle('is-open', state.preset === 'custom');
		var r = range();
		if (state.preset !== 'custom') { inFrom.value = r[0]; inTo.value = r[1]; }
		formSel.value = state.form;
		csvBtn.href = B.csvUrl + '&from=' + encodeURIComponent(r[0]) + '&to=' + encodeURIComponent(r[1]) + '&form=' + encodeURIComponent(state.form);
	}

	/* ---------------- loading ---------------- */

	var seq = 0, S = null;
	function refresh() {
		store('flxfd_filters', state);
		syncBar();
		var r = range(), my = ++seq;
		root.classList.add('is-busy');
		var url = B.ajaxUrl + '?action=flxf_dash_stats&nonce=' + encodeURIComponent(B.nonce) + '&from=' + encodeURIComponent(r[0]) + '&to=' + encodeURIComponent(r[1]) + '&form=' + encodeURIComponent(state.form);
		fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (res) { return res.json().catch(function () { throw new Error(String(res.status)); }); })
			.then(function (j) {
				if (my !== seq) return;
				if (!j || !j.success) throw new Error(j && j.data && j.data.message ? j.data.message : T.error);
				S = j.data;
				render();
			})
			.catch(function (e) {
				if (my !== seq) return;
				body.textContent = '';
				append(body, h('div', { class: 'flxfd-card flxfd-error', role: 'alert' }, [
					h('p', { text: T.error + (e && e.message && e.message !== T.error ? ' (' + e.message + ')' : '') }),
					h('button', { type: 'button', class: 'flxfd-btn', text: T.retry, onclick: refresh })
				]));
			})
			.then(function () { if (my === seq) root.classList.remove('is-busy'); });
	}

	/* ---------------- KPIs ---------------- */

	function delta(cur, prev) {
		if (prev === null || prev === undefined) return null;
		var vs = fmt(T.vsPrev, S.days);
		if (!prev && !cur) return h('span', { class: 'flxfd-delta' }, [h('span', { text: '= ' + T.noChange }), ' ', h('span', { class: 'flxfd-muted', text: vs })]);
		if (!prev) return h('span', { class: 'flxfd-delta is-up' }, [h('span', { text: '▲ ' + T.newVal }), ' ', h('span', { class: 'flxfd-muted', text: vs })]);
		var x = (cur - prev) / prev * 100;
		if (Math.abs(x) < 0.5) return h('span', { class: 'flxfd-delta' }, [h('span', { text: '= ' + T.noChange }), ' ', h('span', { class: 'flxfd-muted', text: vs })]);
		return h('span', { class: 'flxfd-delta ' + (x > 0 ? 'is-up' : 'is-down') }, [
			h('span', { 'aria-label': (x > 0 ? T.up : T.down) + ' ' + pct(Math.abs(x), 0), text: (x > 0 ? '▲ ' : '▼ ') + pct(Math.abs(x), 0) }), ' ',
			h('span', { class: 'flxfd-muted', text: vs })
		]);
	}
	function kpi(label, value, sub, accent) {
		return h('div', { class: 'flxfd-kpi', style: '--accent:' + accent }, [
			h('div', { class: 'flxfd-kpi__label', text: label }),
			h('div', { class: 'flxfd-kpi__value', title: value, text: value }),
			h('div', { class: 'flxfd-kpi__sub' }, sub || ' ')
		]);
	}
	function kpis() {
		var t = S.totals, p = S.prev, pay = S.payments;
		var unreadSub = t.unreadAll !== t.unread ? h('span', { text: fmt(T.unreadAll, num(t.unreadAll)) }) : h('a', { href: B.panelUrl, text: T.viewAll + ' →' });
		var viewsSub = t.views === null ? h('span', { class: 'flxfd-muted', text: T.viewsNone }) : (t.viewsSince ? h('span', { class: 'flxfd-muted', text: fmt(T.viewsSince, dayShort(t.viewsSince)) }) : (p.views === null ? h('span', { class: 'flxfd-muted', text: T.viewsKept }) : delta(t.views, p.views)));
		var convSub = h('span', { class: 'flxfd-muted', text: t.viewsSince ? fmt(T.convSince, dayShort(t.viewsSince)) : T.convHint });
		var main = pay.paid.length ? pay.paid[0] : { cur: S.currency, amount: 0, n: 0 };
		var paySub;
		if (pay.paid.length) {
			var paidN = 0;
			pay.paid.forEach(function (x) { paidN += x.n; });
			var parts = [h('span', { text: fmt(T.paidCount, num(paidN)) })];
			if (pay.paid.length > 1) parts.push(h('span', { class: 'flxfd-muted', text: ' · ' + fmt(T.otherCur, pay.paid.slice(1).map(function (x) { return money(x.amount, x.cur); }).join(', ')) }));
			else if (p.paid !== null && p.paid !== undefined) parts = [delta(main.amount, p.paid)];
			paySub = parts;
		} else {
			paySub = h('span', { class: 'flxfd-muted', text: T.noPayments });
		}
		return h('div', { class: 'flxfd-kpis' }, [
			kpi(T.responses, num(t.n), delta(t.n, p.n), COLORS.primary),
			kpi(T.unread, num(t.unread), unreadSub, '#2F8F6A'),
			kpi(T.views, t.views === null ? '—' : num(t.views), viewsSub, '#3A7CA5'),
			kpi(T.conversion, t.conversion === null ? '—' : pct(t.conversion, 1), convSub, '#1E1B4B'),
			kpi(T.payments, money(main.amount, main.cur), paySub, '#B7791F')
		]);
	}

	/* ---------------- line chart ---------------- */

	function lineChart(box) {
		var pts = S.series, n = pts.length;
		box.textContent = '';
		var total = 0, max = 0;
		pts.forEach(function (p) { total += p.n; max = Math.max(max, p.n, p.p); });
		var W = Math.max(280, Math.round(box.clientWidth || 600)), narrow = W < 520;
		var H = narrow ? 210 : 260, L = 36, R = 12, TP = 12, BT = 28, iw = W - L - R, ih = H - TP - BT;
		var sc = niceScale(Math.max(max, 1)), top = sc.top, ticks = sc.ticks;
		var x = function (i) { return L + (n > 1 ? i * iw / (n - 1) : iw / 2); };
		var y = function (v) { return TP + ih - v / top * ih; };
		var svg = s('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': T.daily + ': ' + num(total) + ' ' + T.responsesOn });
		var grid = s('g', { class: 'flxfd-gl' });
		for (var g = 0; g <= ticks; g++) {
			var gv = top / ticks * g, gy = Math.round(y(gv)) + 0.5;
			grid.appendChild(s('line', { x1: L, x2: W - R, y1: gy, y2: gy, class: g === 0 ? 'is-base' : '' }));
			grid.appendChild(s('text', { x: L - 8, y: gy + 4, 'text-anchor': 'end', text: gv >= 1000 ? compact(gv) : num(gv) }));
		}
		svg.appendChild(grid);
		var every = Math.max(1, Math.ceil(n / (narrow ? 4 : 8)));
		var xl = s('g', { class: 'flxfd-axis' });
		for (var i = 0; i < n; i++) {
			var last = i === n - 1;
			if ((i % every === 0 && (n - 1 - i >= every * 0.85 || last)) || last) {
				xl.appendChild(s('text', { x: x(i), y: H - 8, 'text-anchor': i === 0 ? 'start' : last ? 'end' : 'middle', text: dayShort(pts[i].d) }));
			}
		}
		svg.appendChild(xl);
		var path = function (key) { return pts.map(function (p, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p[key]).toFixed(1); }).join(' '); };
		var defs = s('defs', null, [s('linearGradient', { id: 'flxfd-area', x1: 0, x2: 0, y1: 0, y2: 1 }, [
			s('stop', { offset: 0, 'stop-color': COLORS.primary, 'stop-opacity': 0.16 }),
			s('stop', { offset: 1, 'stop-color': COLORS.primary, 'stop-opacity': 0 })
		])]);
		svg.appendChild(defs);
		svg.appendChild(s('path', { d: path('n') + ' L' + x(n - 1).toFixed(1) + ' ' + y(0) + ' L' + x(0).toFixed(1) + ' ' + y(0) + ' Z', fill: 'url(#flxfd-area)' }));
		svg.appendChild(s('path', { d: path('p'), fill: 'none', stroke: COLORS.prev, 'stroke-width': 1.5, 'stroke-dasharray': '4 4', 'stroke-linejoin': 'round' }));
		svg.appendChild(s('path', { d: path('n'), fill: 'none', stroke: COLORS.primary, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
		var cross = s('line', { class: 'flxfd-cross', y1: TP, y2: TP + ih, visibility: 'hidden' });
		var dotP = s('circle', { r: 4, fill: COLORS.prev, stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' });
		var dotN = s('circle', { r: 5, fill: COLORS.primary, stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' });
		svg.appendChild(cross); svg.appendChild(dotP); svg.appendChild(dotN);
		var hit = s('rect', { x: L - 6, y: 0, width: iw + 12, height: H, fill: 'transparent' });
		svg.appendChild(hit);
		function move(ev) {
			var r = svg.getBoundingClientRect(), cx = (ev.clientX - r.left) / r.width * W;
			var i = n > 1 ? Math.max(0, Math.min(n - 1, Math.round((cx - L) / (iw / (n - 1))))) : 0, p = pts[i];
			cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.setAttribute('visibility', 'visible');
			dotN.setAttribute('cx', x(i)); dotN.setAttribute('cy', y(p.n)); dotN.setAttribute('visibility', 'visible');
			dotP.setAttribute('cx', x(i)); dotP.setAttribute('cy', y(p.p)); dotP.setAttribute('visibility', 'visible');
			showTip(ev, dayLong(p.d), [
				{ color: COLORS.primary, label: T.thisPeriod, value: num(p.n) },
				{ color: COLORS.prev, dashed: true, label: T.prevPeriod + ' (' + dayShort(addDays(p.d, -S.days)) + ')', value: num(p.p) }
			]);
		}
		hit.addEventListener('mousemove', move);
		hit.addEventListener('touchstart', function (e) { if (e.touches[0]) move(e.touches[0]); }, { passive: true });
		hit.addEventListener('mouseleave', function () { [cross, dotN, dotP].forEach(function (el) { el.setAttribute('visibility', 'hidden'); }); hideTip(); });
		box.appendChild(svg);
	}

	/* ---------------- bar chart (responses by form) ---------------- */

	function barChart(box) {
		box.textContent = '';
		var rows = S.forms.filter(function (f) { return f.n > 0; });
		if (!rows.length) { box.appendChild(empty(T.noData)); return; }
		var MAXR = 8;
		if (rows.length > MAXR) {
			var rest = rows.slice(MAXR - 1), other = { id: 0, title: '', n: 0, other: rest.length };
			rest.forEach(function (r) { other.n += r.n; });
			rows = rows.slice(0, MAXR - 1).concat([other]);
		}
		var total = S.totals.n || 1, top = 0;
		rows.forEach(function (r) { top = Math.max(top, r.n); });
		var W = Math.max(260, Math.round(box.clientWidth || 400)), ROW = 40, BAR = 12, VAL = 64, H = rows.length * ROW;
		var iw = W - VAL;
		var svg = s('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': T.byForm });
		var maxChars = Math.max(8, Math.floor(iw / 7.2));
		rows.forEach(function (r, i) {
			var y0 = i * ROW, w = Math.max(r.n ? 4 : 0, r.n / top * iw), label = r.other ? fmt(T.otherForms, num(r.other)) : formTitle(r.id, r.title);
			if (label.length > maxChars) label = label.slice(0, maxChars - 1) + '…';
			var g = s('g', { class: 'flxfd-barrow' });
			g.appendChild(s('text', { x: 0, y: y0 + 14, class: 'flxfd-bar__label', text: label }));
			g.appendChild(s('rect', { x: 0, y: y0 + 21, width: iw, height: BAR, rx: 4, class: 'flxfd-bar__track' }));
			var bw = Math.max(0, w), rr = Math.min(4, bw / 2);
			g.appendChild(s('path', { class: 'flxfd-bar__fill', fill: COLORS.primary, d: 'M0 ' + (y0 + 21) + ' h' + (bw - rr) + ' a' + rr + ' ' + rr + ' 0 0 1 ' + rr + ' ' + rr + ' v' + (BAR - 2 * rr) + ' a' + rr + ' ' + rr + ' 0 0 1 -' + rr + ' ' + rr + ' h-' + (bw - rr) + ' Z' }));
			g.appendChild(s('text', { x: W, y: y0 + 31, 'text-anchor': 'end', class: 'flxfd-bar__val', text: num(r.n) }));
			g.appendChild(s('rect', { x: 0, y: y0, width: W, height: ROW, fill: 'transparent' }));
			g.addEventListener('mousemove', function (ev) {
				showTip(ev, r.other ? fmt(T.otherForms, num(r.other)) : formTitle(r.id, r.title), [
					{ color: COLORS.primary, label: T.responses, value: num(r.n) },
					{ label: T.colShare, value: pct(r.n / total * 100, 0) }
				].concat(r.unread ? [{ label: T.unread, value: num(r.unread) }] : []));
			});
			g.addEventListener('mouseleave', hideTip);
			if (r.id && !r.other) {
				var a = s('a', { href: panelLink(r.id) });
				a.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', panelLink(r.id));
				a.appendChild(g);
				svg.appendChild(a);
			} else {
				svg.appendChild(g);
			}
		});
		box.appendChild(svg);
	}

	/* ---------------- payment status ---------------- */

	function payChart(box) {
		box.textContent = '';
		var list = S.payments.status;
		if (!list.length) { box.appendChild(empty(T.noPayments)); return; }
		var total = 0;
		list.forEach(function (x) { total += x.n; });
		var W = Math.max(240, Math.round(box.clientWidth || 300)), H = 16, GAP = 2;
		var svg = s('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': T.payStatus });
		var avail = W - GAP * (list.length - 1), xpos = 0;
		list.forEach(function (x, i) {
			var w = Math.max(3, x.n / total * avail);
			if (i === list.length - 1) w = Math.max(3, W - xpos);
			var seg = s('rect', { x: xpos, y: 0, width: w, height: H, rx: 4, fill: COLORS.pay[x.s] || COLORS.prev });
			seg.addEventListener('mousemove', function (ev) { showTip(ev, T['pay_' + x.s] || x.s, payLines(x)); });
			seg.addEventListener('mouseleave', hideTip);
			svg.appendChild(seg);
			xpos += w + GAP;
		});
		box.appendChild(svg);
		var legend = h('ul', { class: 'flxfd-paylegend' });
		list.forEach(function (x) {
			var amounts = Object.keys(x.amount).map(function (c) { return money(x.amount[c], c); }).join(' + ');
			legend.appendChild(h('li', null, [
				h('i', { class: 'flxfd-sw', style: 'background:' + (COLORS.pay[x.s] || COLORS.prev) }),
				h('span', { class: 'flxfd-paylegend__label', text: T['pay_' + x.s] || x.s }),
				h('span', { class: 'flxfd-paylegend__n', text: num(x.n) + ' · ' + pct(x.n / total * 100, 0) }),
				h('span', { class: 'flxfd-paylegend__amt', text: amounts })
			]));
		});
		box.appendChild(legend);
	}
	function payLines(x) {
		var lines = [{ label: fmt(T.payN, num(x.n)), value: '' }];
		Object.keys(x.amount).forEach(function (c) { lines.push({ label: c, value: money(x.amount[c], c) }); });
		return lines;
	}

	/* ---------------- heatmap ---------------- */

	function heatmap(box) {
		box.textContent = '';
		if (!S.heat.length) { box.appendChild(empty(T.noData)); return; }
		var m = {}, max = 0;
		S.heat.forEach(function (c) { m[c[0] + '-' + c[1]] = c[2]; max = Math.max(max, c[2]); });
		var start = ((B.weekStart || 0) + 6) % 7; // PHP weekday 0=Sunday → our 0=Monday.
		var order = []; for (var k = 0; k < 7; k++) order.push((start + k) % 7);
		var monday = new Date(2024, 0, 1);
		var W = Math.max(280, Math.round(box.clientWidth || 500)), LBL = 40, TOPL = 18, GAP = 2;
		var cell = Math.max(7, Math.min(26, Math.floor((W - LBL - GAP * 23) / 24)));
		var gridW = cell * 24 + GAP * 23, H = TOPL + 7 * cell + 6 * GAP;
		W = LBL + gridW;
		var svg = s('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': T.heat + ' — ' + T.heatHint });
		for (var hr = 0; hr < 24; hr += (cell < 14 ? 6 : 3)) {
			svg.appendChild(s('text', { x: LBL + hr * (cell + GAP), y: 11, class: 'flxfd-axis-t', text: pad(hr) }));
		}
		order.forEach(function (d, row) {
			var day = new Date(monday); day.setDate(1 + d);
			var y0 = TOPL + row * (cell + GAP);
			svg.appendChild(s('text', { x: 0, y: y0 + cell / 2 + 4, class: 'flxfd-axis-t', text: dfmt(day, { weekday: 'short' }) }));
			for (var hh = 0; hh < 24; hh++) {
				var v = m[d + '-' + hh] || 0, lvl = v ? Math.min(4, Math.floor(v / max * 4.999)) : -1;
				var r = s('rect', { x: LBL + hh * (cell + GAP), y: y0, width: cell, height: cell, rx: Math.min(3, cell / 4), fill: lvl < 0 ? 'var(--flxfd-cell0)' : HEAT[lvl] });
				(function (dd, h1, val) {
					r.addEventListener('mousemove', function (ev) {
						showTip(ev, dfmt(dd, { weekday: 'long' }) + ' ' + pad(h1) + ':00–' + pad((h1 + 1) % 24) + ':00', [{ label: T.responses, value: num(val) }]);
					});
				})(day, hh, v);
				r.addEventListener('mouseleave', hideTip);
				svg.appendChild(r);
			}
		});
		box.appendChild(svg);
		var lg = h('div', { class: 'flxfd-heatlegend' }, [h('span', { text: T.heatLess })]);
		['var(--flxfd-cell0)'].concat(HEAT).forEach(function (c) { lg.appendChild(h('i', { style: 'background:' + c })); });
		lg.appendChild(h('span', { text: T.heatMore }));
		box.appendChild(lg);
	}

	/* ---------------- tables ---------------- */

	function recentTable() {
		if (!S.recent.length) return empty(T.noData);
		var tb = h('tbody');
		S.recent.forEach(function (r) {
			var link = panelLink(r.form_id, r.id);
			tb.appendChild(h('tr', { class: r.unread ? 'is-unread' : '' }, [
				h('td', { class: 'flxfd-col-form', 'data-label': T.colForm }, h('a', { href: panelLink(r.form_id), text: formTitle(r.form_id, r.form) })),
				h('td', { class: 'flxfd-col-preview', 'data-label': T.colPreview }, h('a', { href: link, class: 'flxfd-preview', text: r.preview || T.noPreview })),
				h('td', { class: 'flxfd-col-date', 'data-label': T.colDate }, h('time', { datetime: r.date, title: dateTime(r.date), text: ago(r.date, S.now) })),
				h('td', { class: 'flxfd-col-status', 'data-label': T.colStatus }, h('span', { class: 'flxfd-badge' + (r.unread ? ' is-new' : ''), text: r.unread ? T.statusUnread : T.statusRead })),
				h('td', { class: 'flxfd-col-act' }, h('a', { href: link, class: 'flxfd-open', 'aria-label': T.open + ': ' + formTitle(r.form_id, r.form) + ' #' + r.id, text: T.open + ' →' }))
			]));
		});
		return h('div', { class: 'flxfd-tablewrap' }, h('table', { class: 'flxfd-table flxfd-table--recent' }, [
			h('thead', null, h('tr', null, [
				h('th', { scope: 'col', text: T.colForm }), h('th', { scope: 'col', text: T.colPreview }), h('th', { scope: 'col', text: T.colDate }),
				h('th', { scope: 'col', text: T.colStatus }), h('th', { scope: 'col' }, h('span', { class: 'screen-reader-text', text: T.open }))
			])),
			tb
		]));
	}

	function formsTable() {
		var rows = S.forms;
		if (!rows.length) return empty(T.noData);
		var total = S.totals.n || 0, tb = h('tbody');
		rows.forEach(function (r) {
			var share = total ? r.n / total * 100 : 0;
			tb.appendChild(h('tr', null, [
				h('th', { scope: 'row', class: 'flxfd-col-form', 'data-label': T.colForm }, [
					h('a', { href: panelLink(r.id), text: formTitle(r.id, r.title) }),
					r.unread ? h('span', { class: 'flxfd-badge is-new', text: num(r.unread) + ' ' + T.statusUnread.toLowerCase() }) : null
				]),
				h('td', { class: 'num', 'data-label': T.colResponses }, h('span', { class: 'flxfd-sharecell' }, [
					h('b', { text: num(r.n) }),
					h('span', { class: 'flxfd-share', title: pct(share, 0) }, h('span', { style: 'width:' + Math.max(r.n ? 3 : 0, share).toFixed(1) + '%' }))
				])),
				h('td', { class: 'num', 'data-label': T.colViews, text: r.views === null ? '—' : num(r.views) }),
				h('td', { class: 'num', 'data-label': T.colConv, text: r.conv === null ? '—' : pct(r.conv, 1) }),
				h('td', { class: 'num', 'data-label': T.colTotal, text: num(r.total) }),
				h('td', { 'data-label': T.colLast }, r.last ? h('time', { datetime: r.last, title: dateTime(r.last), text: ago(r.last, S.now) }) : h('span', { class: 'flxfd-muted', text: T.never })),
				h('td', { class: 'flxfd-col-act' }, r.exists ? h('a', { href: panelLink(r.id), class: 'flxfd-open', text: T.openResponses + ' →' }) : null)
			]));
		});
		return h('div', { class: 'flxfd-tablewrap' }, h('table', { class: 'flxfd-table flxfd-table--forms' }, [
			h('thead', null, h('tr', null, [
				h('th', { scope: 'col', text: T.colForm }), h('th', { scope: 'col', class: 'num', text: T.colResponses }), h('th', { scope: 'col', class: 'num', text: T.colViews }),
				h('th', { scope: 'col', class: 'num', text: T.colConv }), h('th', { scope: 'col', class: 'num', text: T.colTotal }), h('th', { scope: 'col', text: T.colLast }),
				h('th', { scope: 'col' }, h('span', { class: 'screen-reader-text', text: T.open }))
			])),
			tb
		]));
	}

	/* ---------------- render ---------------- */

	var boxes = {};
	function render() {
		hideTip();
		body.textContent = '';
		boxes = { line: h('div', { class: 'flxfd-chart' }), bars: h('div', { class: 'flxfd-chart' }), pay: h('div', { class: 'flxfd-chart' }), heat: h('div', { class: 'flxfd-chart flxfd-chart--heat' }) };
		var legend = h('div', { class: 'flxfd-legend' }, [
			h('span', null, [h('i', { class: 'flxfd-sw', style: 'background:' + COLORS.primary }), T.thisPeriod]),
			h('span', null, [h('i', { class: 'flxfd-sw is-dashed', style: 'background:' + COLORS.prev }), T.prevPeriod])
		]);
		var hasPay = S.payments.status.length > 0 || S.payments.source !== 'none';
		var rowA = h('div', { class: 'flxfd-grid' + (hasPay ? ' flxfd-grid--2-1' : '') }, [
			card(T.daily, { aside: legend }, S.totals.n || S.prev.n ? boxes.line : empty(T.noData)),
			hasPay ? card(T.payStatus, null, boxes.pay) : null
		]);
		var rowB = h('div', { class: 'flxfd-grid flxfd-grid--1-1' }, [
			state.form ? null : card(T.byForm, null, boxes.bars),
			card(T.heat, { hint: T.heatHint }, boxes.heat)
		]);
		if (state.form) rowB.className = 'flxfd-grid';
		append(body, [
			kpis(), rowA, rowB,
			card(T.recent, { aside: h('a', { class: 'flxfd-link', href: state.form ? panelLink(state.form) : B.panelUrl, text: T.viewAll + ' →' }) }, recentTable()),
			card(T.topForms, null, formsTable())
		]);
		drawCharts();
	}
	function drawCharts() {
		if (!S) return;
		if (boxes.line.isConnected) lineChart(boxes.line);
		if (boxes.bars.isConnected) barChart(boxes.bars);
		if (boxes.pay.isConnected) payChart(boxes.pay);
		if (boxes.heat.isConnected) heatmap(boxes.heat);
	}
	var rz = 0, lastW = window.innerWidth;
	window.addEventListener('resize', function () {
		if (window.innerWidth === lastW) return;
		lastW = window.innerWidth;
		clearTimeout(rz);
		rz = setTimeout(drawCharts, 150);
	});
	window.addEventListener('scroll', hideTip, { passive: true });

	refresh();
})();
