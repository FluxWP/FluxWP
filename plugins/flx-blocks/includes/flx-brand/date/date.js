/* FluxWP date picker (flx-brand 1.8.0): one calendar for the site, wp-admin and every package.
 *
 * Two ways in:
 *  - Fields: <input type="date|datetime-local"> becomes a button + calendar. The real input stays in place (hidden):
 *    value, name, required, min/max, form submit and input/change events behave as before.
 *    Enhanced automatically inside .flx-fld (flx_field type=date), with data-flx-date, and in Flux Admin screens (body.flxa).
 *    Opt out with data-flx-native; data-flx-date-quick="0" hides the quick list (Today, Tomorrow …).
 *  - Code: flxDate.open(anchor, { value, time, quick, min, max, required, label, theme, onPick, onClose }).
 *  Look (Ahmet 2026-11-17, design B): quick picks on the left, calendar with month / year menus on the right,
 *  typed date (+ time) and Apply at the bottom; a bottom sheet on phones.
 *    value / onPick use "YYYY-MM-DD" or "YYYY-MM-DDTHH:MM" ('' = cleared).
 *
 * Language comes from FLX_DATE (PHP: flx_date_enqueue): locale for month and day names, weekStart, i18n (TR / EN).
 * API: window.flxDate = { open, close, enhance, format, parse }. GPL v2 or later.
 */
/* global FLX_DATE */
(function () {
	'use strict';
	if (window.flxDate) return;
	var d = document;
	var C = window.FLX_DATE || {};
	var T = C.i18n || {};
	var lang = String(C.locale || d.documentElement.lang || 'en').replace('_', '-');
	var weekStart = parseInt(C.weekStart, 10);
	if (isNaN(weekStart)) weekStart = /^tr\b/i.test(lang) ? 1 : 0;
	var proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

	function svg(p, s) { return '<svg viewBox="0 0 24 24" width="' + (s || 18) + '" height="' + (s || 18) + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; }
	var I_CAL = svg('<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>', 18);
	var I_L = svg('<path d="m15 6-6 6 6 6"/>'), I_R = svg('<path d="m9 6 6 6-6 6"/>');
	var I_CLK = svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 16);
	var I_DOWN = svg('<path d="m6 9 6 6 6-6"/>', 14);

	function fmt(o) { try { return new Intl.DateTimeFormat(lang, o); } catch (e) { return new Intl.DateTimeFormat('en', o); } }
	var F = {
		long: fmt({ day: 'numeric', month: 'long', year: 'numeric' }),
		short: fmt({ day: 'numeric', month: 'short' }),
		month: fmt({ month: 'long' }),
		monthS: fmt({ month: 'short' }),
		wd: fmt({ weekday: 'short' }),
		full: fmt({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
	};
	function t(k, def) { return T[k] || def; }
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function ymd(x) { return x.getFullYear() + '-' + pad(x.getMonth() + 1) + '-' + pad(x.getDate()); }
	function parse(s) {
		var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
		if (!m) return null;
		var x = new Date(+m[1], +m[2] - 1, +m[3], 12);
		return isNaN(x) || x.getDate() !== +m[3] ? null : x;
	}
	function timeOf(s) { var m = /T(\d{2}:\d{2})/.exec(s || ''); return m ? m[1] : ''; }
	function today() { var n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate(), 12); }
	function addDays(x, n) { return new Date(x.getFullYear(), x.getMonth(), x.getDate() + n, 12); }
	function addMonths(x, n) {
		var y = x.getFullYear(), m = x.getMonth() + n, last = new Date(y, m + 1, 0).getDate();
		return new Date(y, m, Math.min(x.getDate(), last), 12);
	}
	function cap(s) { return s ? s.charAt(0).toLocaleUpperCase(lang) + s.slice(1) : s; }
	function wdName(x) { return cap(F.wd.format(x)).replace('.', ''); }
	/** "2026-11-20" → "20 Kasım 2026"; with time "20 Kasım 2026 · 14:30". style: long (default) | short. */
	function format(s, style) {
		var x = parse(s);
		if (!x) return '';
		var tm = timeOf(s);
		return (style === 'short' ? F.short : F.long).format(x) + (tm ? ' · ' + tm : '');
	}
	function cleanTime(v) {
		var m = /^(\d{1,2})(?::?(\d{2}))?$/.exec(String(v || '').trim());
		if (!m) return '';
		var h = +m[1], mi = m[2] ? +m[2] : 0;
		return h > 23 || mi > 59 ? '' : pad(h) + ':' + pad(mi);
	}

	/* ---------- theme ---------- */
	function themeOf(el) {
		var a = el && el.closest ? el.closest('[data-theme]') : null;
		if (a && /^(dark|light)$/.test(a.getAttribute('data-theme'))) return a.getAttribute('data-theme');
		var h = d.documentElement.classList, b = d.body ? d.body.classList : h;
		if (h.contains('flxa-dark') || h.contains('flxfw-dark') || h.contains('flxg-dark') || h.contains('dark')) return 'dark';
		var auto = h.contains('flxa-auto') || (b.contains('flx-dark') && !d.documentElement.hasAttribute('data-flxfw-theme'));
		return auto && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}

	/* ---------- the popover (one, shared) ---------- */
	var pop, shade, S = null; // S = current session

	function build() {
		pop = d.createElement('div');
		pop.className = 'flx-dp';
		pop.setAttribute('role', 'dialog');
		pop.hidden = true;
		shade = d.createElement('div');
		shade.className = 'flx-dp-shade';
		shade.hidden = true;
		d.body.appendChild(shade);
		d.body.appendChild(pop);
		pop.addEventListener('click', onClick);
		pop.addEventListener('keydown', onKey);
		shade.addEventListener('click', function () { close(false); });
		d.addEventListener('mousedown', function (e) {
			if (S && !pop.contains(e.target) && !(S.anchor && S.anchor.contains(e.target))) close(false);
		}, true);
		window.addEventListener('resize', function () { if (S) place(); });
		window.addEventListener('scroll', function (e) { if (S && !pop.contains(e.target)) place(); }, true);
	}

	function allowed(s) {
		return !S || !((S.min && s < S.min) || (S.max && s > S.max));
	}

	/* Typed date: order and separator from the locale ("20.11.2026" in Turkish, "11/20/2026" in US English). */
	var PAT = (function () {
		var order = [], sep = '.';
		try {
			fmt({ day: '2-digit', month: '2-digit', year: 'numeric' }).formatToParts(new Date(2026, 10, 20)).forEach(function (p) {
				if (p.type === 'day' || p.type === 'month' || p.type === 'year') order.push(p.type);
				else if (p.type === 'literal' && p.value.trim()) sep = p.value.trim().charAt(0);
			});
		} catch (e) { /* default below */ }
		if (order.length !== 3) order = ['day', 'month', 'year'];
		var tr = /^tr\b/i.test(lang);
		var ph = order.map(function (k) { return k === 'year' ? (tr ? 'YYYY' : 'YYYY') : k === 'day' ? (tr ? 'GG' : 'DD') : (tr ? 'AA' : 'MM'); }).join(sep);
		return { order: order, sep: sep, ph: ph };
	})();
	function typed(s) {
		if (!s) return '';
		var x = parse(s);
		if (!x) return '';
		var v = { day: pad(x.getDate()), month: pad(x.getMonth() + 1), year: String(x.getFullYear()) };
		return PAT.order.map(function (k) { return v[k]; }).join(PAT.sep);
	}
	function fromTyped(str) {
		var p = String(str || '').trim().split(/[^\d]+/).filter(Boolean);
		if (p.length !== 3) return '';
		var v = {};
		PAT.order.forEach(function (k, i) { v[k] = +p[i]; });
		if (v.year < 100) v.year += 2000;
		var s = v.year + '-' + pad(v.month) + '-' + pad(v.day);
		return parse(s) ? s : '';
	}

	function quickList() {
		if (!S.quick) return [];
		if (Array.isArray(S.quick)) return S.quick;
		var td = today(), add = (8 - td.getDay()) % 7 || 7, fri = (5 - td.getDay() + 7) % 7;
		var list = [
			{ label: t('today', 'Today'), value: ymd(td), hint: wdName(td) },
			{ label: t('tomorrow', 'Tomorrow'), value: ymd(addDays(td, 1)), hint: wdName(addDays(td, 1)) }
		];
		if (fri > 1) list.push({ label: t('thisFriday', 'This Friday'), value: ymd(addDays(td, fri)), hint: F.short.format(addDays(td, fri)) });
		list.push({ label: t('nextWeek', 'Next week'), value: ymd(addDays(td, add)), hint: wdName(addDays(td, add)) });
		list.push({ label: t('in2Weeks', 'In 2 weeks'), value: ymd(addDays(td, 14)), hint: F.short.format(addDays(td, 14)) });
		return list;
	}

	function btn(cls, html, attrs) {
		var a = '';
		for (var k in attrs || {}) a += ' ' + k + '="' + String(attrs[k]).replace(/"/g, '&quot;') + '"';
		return '<button type="button" class="' + cls + '"' + a + '>' + html + '</button>';
	}
	function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function render(focus) {
		var h = '', v = S.view, i;
		var q = quickList();
		/* left: quick picks (B) */
		if (q.length) {
			h += '<div class="flx-dp-side">';
			q.forEach(function (o, n) {
				if (!allowed(o.value)) return;
				h += btn('flx-dp-q' + (o.value === S.day ? ' is-on' : ''), '<span>' + esc(o.label) + '</span>' + (o.hint ? '<small>' + esc(o.hint) + '</small>' : ''), { 'data-q': n });
			});
			if (!S.required) h += '<hr>' + btn('flx-dp-q is-none', '<span>' + esc(t('noDate', 'No date')) + '</span>', { 'data-a': 'clear' });
			h += '</div>';
		}
		h += '<div class="flx-dp-main">';
		if (S.mode === 'days') {
			h += '<div class="flx-dp-head"><span class="flx-dp-sels">' +
				btn('flx-dp-sel', esc(cap(F.month.format(v))) + I_DOWN, { 'data-mode': 'months', 'aria-label': t('pickMonth', 'Choose month') }) +
				btn('flx-dp-sel', String(v.getFullYear()) + I_DOWN, { 'data-mode': 'years', 'aria-label': t('pickYear', 'Choose year') }) + '</span>' +
				btn('flx-dp-nav', I_L, { 'data-m': -1, 'aria-label': t('prev', 'Previous month') }) + btn('flx-dp-nav', I_R, { 'data-m': 1, 'aria-label': t('next', 'Next month') }) + '</div>';
			h += '<div class="flx-dp-week" aria-hidden="true">';
			for (i = 0; i < 7; i++) h += '<span>' + esc(wdName(addDays(new Date(2024, 0, 1, 12), ((weekStart + 6) % 7) + i))) + '</span>';
			h += '</div><div class="flx-dp-grid" role="grid">';
			var first = new Date(v.getFullYear(), v.getMonth(), 1, 12);
			var start = addDays(first, -((first.getDay() - weekStart + 7) % 7));
			var td = ymd(today()), fd = ymd(S.focus);
			for (i = 0; i < 42; i++) {
				if (i % 7 === 0) h += (i ? '</div>' : '') + '<div class="flx-dp-row" role="row">';
				var x = addDays(start, i), s = ymd(x), c = 'flx-dp-day';
				if (x.getMonth() !== v.getMonth()) c += ' is-out';
				if (s === td) c += ' is-today';
				if (s === S.day) c += ' is-sel';
				h += '<button type="button" role="gridcell" class="' + c + '" data-day="' + s + '" tabindex="' + (s === fd ? 0 : -1) + '" aria-label="' + esc(F.full.format(x)) + '"' + (s === S.day ? ' aria-selected="true"' : '') + (allowed(s) ? '' : ' disabled') + '>' + x.getDate() + '</button>';
			}
			h += '</div></div>';
		} else if (S.mode === 'months') {
			h += '<div class="flx-dp-head"><span class="flx-dp-sels">' + btn('flx-dp-sel is-on', esc(t('month', 'Month')), { 'data-mode': 'days' }) + btn('flx-dp-sel', String(v.getFullYear()) + I_DOWN, { 'data-mode': 'years', 'aria-label': t('pickYear', 'Choose year') }) + '</span>' +
				btn('flx-dp-nav', I_L, { 'data-y': -1, 'aria-label': t('prevYear', 'Previous year') }) + btn('flx-dp-nav', I_R, { 'data-y': 1, 'aria-label': t('nextYear', 'Next year') }) + '</div><div class="flx-dp-cells">';
			for (i = 0; i < 12; i++) {
				var mx = new Date(v.getFullYear(), i, 1, 12), cur = S.day && S.day.slice(0, 7) === ymd(mx).slice(0, 7);
				h += btn('flx-dp-cell' + (cur ? ' is-sel' : '') + (i === v.getMonth() ? ' is-focus' : ''), esc(cap(F.month.format(mx))), { 'data-month': i, tabindex: i === v.getMonth() ? 0 : -1 });
			}
			h += '</div>';
		} else {
			var y0 = v.getFullYear() - (v.getFullYear() % 12);
			h += '<div class="flx-dp-head"><span class="flx-dp-sels"><span class="flx-dp-range">' + y0 + ' – ' + (y0 + 11) + '</span></span>' +
				btn('flx-dp-nav', I_L, { 'data-y': -12, 'aria-label': t('prev', 'Previous') }) + btn('flx-dp-nav', I_R, { 'data-y': 12, 'aria-label': t('next', 'Next') }) + '</div><div class="flx-dp-cells">';
			for (i = 0; i < 12; i++) {
				var yy = y0 + i;
				h += btn('flx-dp-cell' + (S.day && +S.day.slice(0, 4) === yy ? ' is-sel' : '') + (yy === v.getFullYear() ? ' is-focus' : ''), String(yy), { 'data-year': yy, tabindex: yy === v.getFullYear() ? 0 : -1 });
			}
			h += '</div>';
		}
		/* bottom: typed date, time, Apply */
		h += '<div class="flx-dp-foot">';
		h += '<label class="flx-dp-in flx-dp-in--date">' + svg('<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>', 16) + '<input type="text" inputmode="numeric" autocomplete="off" maxlength="10" data-k="date" placeholder="' + esc(PAT.ph) + '" value="' + esc(typed(S.day)) + '" aria-label="' + esc(t('pick', 'Date')) + '"></label>';
		if (S.time) h += '<label class="flx-dp-in flx-dp-in--time">' + I_CLK + '<input type="text" inputmode="numeric" autocomplete="off" maxlength="5" data-k="time" placeholder="--:--" value="' + esc(S.tm) + '" aria-label="' + esc(t('time', 'Time')) + '"></label>';
		h += '</div><div class="flx-dp-acts">';
		if (!q.length && !S.required && (S.orig || S.day)) h += btn('flx-dp-act', esc(t('clear', 'Clear')), { 'data-a': 'clear' });
		h += '<span class="flx-dp-sp"></span>' + btn('flx-dp-act', esc(t('cancel', 'Cancel')), { 'data-a': 'cancel' }) + btn('flx-dp-act is-main', esc(t('apply', 'Apply')), { 'data-a': 'apply' });
		h += '</div></div>';
		pop.innerHTML = h;
		pop.classList.toggle('has-side', q.length > 0);
		var di = pop.querySelector('input[data-k="date"]'), ti = pop.querySelector('input[data-k="time"]');
		di.addEventListener('input', function () {
			S.typing = di.value;
			var s = fromTyped(di.value);
			if (s && allowed(s)) {
				S.day = s; S.focus = parse(s); S.view = new Date(S.focus.getFullYear(), S.focus.getMonth(), 1, 12);
				var keep = di.selectionStart;
				render(false);
				var n = pop.querySelector('input[data-k="date"]'); n.focus(); try { n.setSelectionRange(keep, keep); } catch (e) { /* type */ }
			}
		});
		di.addEventListener('blur', function () { if (!fromTyped(di.value)) di.value = typed(S.day); });
		if (ti) {
			ti.addEventListener('input', function () {
				var raw = ti.value.replace(/[^\d]/g, '').slice(0, 4);
				ti.value = raw.length > 2 ? raw.slice(0, 2) + ':' + raw.slice(2) : raw;
				S.tm = ti.value;
			});
			ti.addEventListener('blur', function () { var c = cleanTime(ti.value); if (c || !ti.value) { ti.value = c; S.tm = c; } });
		}
		if (focus) {
			var f = pop.querySelector('.flx-dp-day[tabindex="0"], .flx-dp-cell[tabindex="0"]');
			if (f) f.focus({ preventScroll: true });
		}
		if (S.mode === 'days') {
			var mn = parse(S.min), mxd = parse(S.max);
			var pb = pop.querySelector('[data-m="-1"]'), nb = pop.querySelector('[data-m="1"]');
			if (pb) pb.disabled = !!(mn && new Date(v.getFullYear(), v.getMonth(), 0, 12) < mn);
			if (nb) nb.disabled = !!(mxd && new Date(v.getFullYear(), v.getMonth() + 1, 1, 12) > mxd);
		}
	}

	function place() {
		if (!S) return;
		var sheet = window.innerWidth <= 600;
		pop.classList.toggle('is-sheet', sheet);
		shade.hidden = !sheet;
		if (sheet) { pop.style.left = pop.style.top = ''; return; }
		var r = S.anchor.getBoundingClientRect();
		var vw = window.innerWidth, vh = window.innerHeight, pw = pop.offsetWidth, ph = pop.offsetHeight;
		var left = Math.min(Math.max(8, r.left), vw - pw - 8);
		var top = r.bottom + 6;
		if (top + ph > vh - 8 && r.top - ph - 6 > 8) top = r.top - ph - 6;
		if (top + ph > vh - 8) top = Math.max(8, vh - ph - 8);
		pop.style.left = left + 'px';
		pop.style.top = top + 'px';
	}

	/**
	 * Opens the calendar next to anchor.
	 * opts: value, time (true: time box), quick (default true; false | [{label, value, hint}]), min, max, required (no "No date"),
	 *       label, theme ('dark'|'light'), onPick(value), onClose().
	 * A day click selects; Apply (or Enter, or a double click) confirms. A quick pick confirms at once.
	 */
	function open(anchor, opts) {
		opts = opts || {};
		if (!pop) build();
		if (S && S.anchor === anchor) { close(true); return; }
		if (S) close(false);
		var v = String(opts.value || '');
		var day = parse(v) ? v.slice(0, 10) : '';
		var base = parse(day) || today();
		var mn = parse(opts.min), mx = parse(opts.max);
		if (!day && mn && base < mn) base = mn;
		if (!day && mx && base > mx) base = mx;
		S = {
			anchor: anchor, orig: v, day: day, tm: timeOf(v), time: !!opts.time, quick: opts.quick === undefined ? true : opts.quick,
			min: opts.min ? String(opts.min).slice(0, 10) : '', max: opts.max ? String(opts.max).slice(0, 10) : '',
			required: !!opts.required, onPick: opts.onPick || function () {}, onClose: opts.onClose || null,
			view: new Date(base.getFullYear(), base.getMonth(), 1, 12), focus: base, mode: 'days'
		};
		pop.setAttribute('data-theme', opts.theme || themeOf(anchor));
		shade.setAttribute('data-theme', pop.getAttribute('data-theme'));
		pop.setAttribute('aria-label', opts.label || t('pick', 'Select date'));
		if (anchor.setAttribute) { anchor.setAttribute('aria-expanded', 'true'); anchor.classList.add('is-open'); }
		pop.hidden = false;
		render(true);
		place();
	}

	function close(refocus) {
		if (!S) return;
		var s = S;
		S = null;
		pop.hidden = true;
		shade.hidden = true;
		if (s.anchor.setAttribute) { s.anchor.setAttribute('aria-expanded', 'false'); s.anchor.classList.remove('is-open'); }
		if (refocus && s.anchor.focus) s.anchor.focus({ preventScroll: true });
		if (s.onClose) s.onClose();
	}

	function commit(day) {
		if (day && !allowed(day)) return;
		var s = S, tm = s.time ? cleanTime(s.tm) : '';
		var out = day ? day + (tm ? 'T' + tm : '') : '';
		close(true);
		if (out !== s.orig) s.onPick(out);
	}
	function apply() {
		var di = pop.querySelector('input[data-k="date"]');
		var typedDay = di ? fromTyped(di.value) : '';
		commit(typedDay || S.day || (S.time && cleanTime(S.tm) ? ymd(today()) : ''));
	}

	function onClick(e) {
		var b = e.target.closest('button');
		if (!b || !S || b.disabled) return;
		var v = S.view;
		if (b.hasAttribute('data-m')) {
			S.view = addMonths(v, +b.getAttribute('data-m'));
			S.focus = new Date(S.view.getFullYear(), S.view.getMonth(), Math.min(S.focus.getDate(), new Date(S.view.getFullYear(), S.view.getMonth() + 1, 0).getDate()), 12);
			render(false);
		} else if (b.hasAttribute('data-y')) {
			S.view = new Date(v.getFullYear() + +b.getAttribute('data-y'), v.getMonth(), 1, 12);
			render(false);
		} else if (b.hasAttribute('data-mode')) {
			S.mode = b.getAttribute('data-mode');
			render(true);
		} else if (b.hasAttribute('data-month')) {
			S.view = new Date(v.getFullYear(), +b.getAttribute('data-month'), 1, 12);
			S.focus = new Date(S.view.getFullYear(), S.view.getMonth(), Math.min(S.focus.getDate(), new Date(S.view.getFullYear(), S.view.getMonth() + 1, 0).getDate()), 12);
			S.mode = 'days';
			render(true);
		} else if (b.hasAttribute('data-year')) {
			S.view = new Date(+b.getAttribute('data-year'), v.getMonth(), 1, 12);
			S.mode = 'months';
			render(true);
		} else if (b.hasAttribute('data-day')) {
			var day = b.getAttribute('data-day'), x = parse(day);
			if (e.detail > 1 && day === S.day) { commit(day); return; }
			S.day = day; S.focus = x;
			if (x.getMonth() !== v.getMonth() || x.getFullYear() !== v.getFullYear()) S.view = new Date(x.getFullYear(), x.getMonth(), 1, 12);
			render(true);
		} else if (b.hasAttribute('data-q')) {
			commit(quickList()[+b.getAttribute('data-q')].value);
		} else {
			var a = b.getAttribute('data-a');
			if (a === 'clear') commit('');
			else if (a === 'apply') apply();
			else if (a === 'cancel') close(true);
		}
	}

	function onKey(e) {
		if (!S) return;
		if (e.key === 'Escape') {
			e.preventDefault(); e.stopPropagation();
			if (S.mode !== 'days') { S.mode = 'days'; render(true); } else close(true);
			return;
		}
		if (e.key === 'Enter' && (e.target.tagName === 'INPUT' || e.target.classList.contains('flx-dp-day'))) {
			e.preventDefault();
			if (e.target.classList.contains('flx-dp-day')) { S.day = e.target.getAttribute('data-day'); commit(S.day); return; }
			if (e.target.getAttribute('data-k') === 'time') S.tm = cleanTime(e.target.value) || S.tm;
			apply();
			return;
		}
		if (e.key === 'Tab') {
			var f = Array.prototype.filter.call(pop.querySelectorAll('button, input'), function (b) { return !b.disabled && b.tabIndex !== -1; });
			if (!f.length) return;
			if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
			return;
		}
		if (e.target.classList.contains('flx-dp-cell')) {
			var cells = Array.prototype.slice.call(pop.querySelectorAll('.flx-dp-cell'));
			var ix = cells.indexOf(e.target), mv = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3 }[e.key];
			if (mv && cells[ix + mv]) { e.preventDefault(); cells[ix].tabIndex = -1; cells[ix + mv].tabIndex = 0; cells[ix + mv].focus(); }
			return;
		}
		if (!e.target.classList.contains('flx-dp-day')) return;
		var step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key], nd = null, fd = S.focus;
		if (step) nd = addDays(fd, step);
		else if (e.key === 'PageUp') nd = addMonths(fd, e.shiftKey ? -12 : -1);
		else if (e.key === 'PageDown') nd = addMonths(fd, e.shiftKey ? 12 : 1);
		else if (e.key === 'Home') nd = addDays(fd, -((fd.getDay() - weekStart + 7) % 7));
		else if (e.key === 'End') nd = addDays(fd, 6 - ((fd.getDay() - weekStart + 7) % 7));
		if (!nd) return;
		e.preventDefault();
		S.focus = nd;
		if (nd.getMonth() !== S.view.getMonth() || nd.getFullYear() !== S.view.getFullYear()) S.view = new Date(nd.getFullYear(), nd.getMonth(), 1, 12);
		render(true);
	}

	/* ---------- fields: <input type=date|datetime-local> ---------- */
	function wanted(input) {
		if (input.flxDate || input.hasAttribute('data-flx-native') || input.hasAttribute('data-flxa-native')) return false;
		if (input.closest('[data-flx-native], [data-flxa-native], .block-editor, .edit-post-layout, .components-popover')) return false;
		for (var k in input) { if (k.indexOf('__react') === 0) return false; }
		if (input.hasAttribute('data-flx-date') || input.closest('.flx-fld, [data-flx-date]')) return true;
		var b = d.body.classList;
		return b.contains('flxa') && !b.contains('flxa-appmode');
	}

	function enhanceOne(input) {
		if (!wanted(input)) return;
		var withTime = input.type === 'datetime-local';
		var inField = input.classList.contains('flx-fld__input');
		var btnEl = d.createElement('button');
		btnEl.type = 'button';
		btnEl.className = 'flx-dt' + (inField ? ' flx-dt--fld' : '') + (d.body.classList.contains('flxa') && !inField ? ' flxa-date' : '');
		btnEl.setAttribute('aria-haspopup', 'dialog');
		btnEl.setAttribute('aria-expanded', 'false');
		btnEl.innerHTML = (inField ? '' : '<span class="flx-dt__ico flxa-date-ico">' + I_CAL + '</span>') + '<span class="flx-dt__val flxa-date-val"></span>';
		if (!inField) {
			var w = input.offsetWidth, pw = input.parentNode && input.parentNode.clientWidth, cs = getComputedStyle(input);
			if (w && pw && w >= pw - 24) btnEl.style.width = '100%';
			else if (w) btnEl.style.minWidth = Math.max(w, withTime ? 190 : 150) + 'px';
			if (cs.maxWidth && cs.maxWidth !== 'none') btnEl.style.maxWidth = cs.maxWidth;
		}
		var valEl = btnEl.querySelector('.flx-dt__val');
		if (input.id) {
			valEl.id = input.id + '-flx-v';
			var lab = d.querySelector('label[for="' + input.id + '"]');
			if (lab) { if (!lab.id) lab.id = input.id + '-flx-l'; btnEl.setAttribute('aria-labelledby', lab.id + ' ' + valEl.id); }
			else if (input.getAttribute('aria-label')) btnEl.setAttribute('aria-label', input.getAttribute('aria-label'));
		} else if (input.getAttribute('aria-label')) btnEl.setAttribute('aria-label', input.getAttribute('aria-label'));
		input.parentNode.insertBefore(btnEl, input.nextSibling);
		input.classList.add('flx-dt-native', 'flxa-date-native');
		input.tabIndex = -1;
		input.setAttribute('aria-hidden', 'true');

		function paint() {
			var v = proto.get.call(input);
			valEl.textContent = v ? format(v) : (input.getAttribute('placeholder') || t('pick', 'Select date'));
			btnEl.classList.toggle('is-empty', !v);
			btnEl.disabled = input.disabled;
			btnEl.classList.toggle('is-readonly', input.readOnly);
			if (v) btnEl.classList.remove('is-invalid');
		}
		function openIt() {
			if (input.disabled || input.readOnly) return;
			open(btnEl, {
				value: proto.get.call(input), time: withTime, required: input.required,
				min: input.min, max: input.max, quick: input.getAttribute('data-flx-date-quick') !== '0',
				label: btnEl.textContent,
				onPick: function (v) { setVal(input, v); }
			});
		}
		input.flxDate = { btn: btnEl, paint: paint, open: openIt };
		input.flxaDate = input.flxDate;
		try {
			Object.defineProperty(input, 'value', {
				configurable: true,
				get: function () { return proto.get.call(this); },
				set: function (v) { proto.set.call(this, v); paint(); }
			});
		} catch (e) { /* old browser: events only */ }
		input.addEventListener('change', paint);
		input.addEventListener('input', paint);
		input.addEventListener('invalid', function () { btnEl.classList.add('is-invalid'); });
		input.addEventListener('focus', function () { btnEl.focus(); });
		input.addEventListener('click', function (e) { e.preventDefault(); openIt(); });
		if (window.MutationObserver) new MutationObserver(paint).observe(input, { attributes: true, attributeFilter: ['value', 'disabled', 'readonly', 'min', 'max'] });
		if (input.form) input.form.addEventListener('reset', function () { setTimeout(paint, 0); });
		btnEl.addEventListener('click', openIt);
		btnEl.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); openIt(); }
			else if ((e.key === 'Backspace' || e.key === 'Delete') && !input.required && !input.readOnly) { e.preventDefault(); setVal(input, ''); }
		});
		paint();
	}

	function setVal(input, v) {
		if (input.type === 'date') v = v.slice(0, 10);
		else if (input.type === 'datetime-local' && v && v.length === 10) v += 'T00:00';
		if (proto.get.call(input) === v) return;
		proto.set.call(input, v);
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		if (input.flxDate) input.flxDate.paint();
	}

	function enhance(root) {
		Array.prototype.forEach.call((root || d).querySelectorAll('input[type="date"], input[type="datetime-local"]'), enhanceOne);
	}

	window.flxDate = { open: open, close: function () { close(false); }, enhance: enhance, format: format, parse: parse };
	/* Flux Admin < 1.9 API name. */
	window.flxaDate = window.flxaDate || { enhance: enhance, format: format, open: function (input) { if (input && input.flxDate) input.flxDate.open(); } };

	function boot() {
		enhance(d);
		if (!window.MutationObserver) return;
		var q = false;
		new MutationObserver(function (muts) {
			if (q) return;
			for (var i = 0; i < muts.length; i++) {
				if (muts[i].addedNodes.length) { q = true; requestAnimationFrame(function () { q = false; enhance(d); }); return; }
			}
		}).observe(d.body, { childList: true, subtree: true });
	}
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})();
