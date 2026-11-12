/* Flux Admin · tarih seçici.
 * Paneldeki her <input type="date"> dropdown görünümlü bir düğme + takvim penceresi olur. Asıl input yerinde kalır
 * (değer, name, required, min/max, form gönderimi ve change/input olayları aynen çalışır), yalnız görünmez.
 * Kapsam dışı: data-flxa-native olan input, React'in yönettiği input, uygulama ekranları (flxa-appmode), blok düzenleyici.
 * API: window.flxaDate = { enhance(root), open(input), format(ymd) }.
 */
/* global FLXA */
(function () {
	'use strict';
	var d = document;
	var B = window.FLXA || {};
	var T = B.i18n || {};
	var lang = (d.documentElement.lang || 'tr-TR').replace('_', '-');
	var weekStart = parseInt(B.weekStart, 10);
	if (isNaN(weekStart)) weekStart = 1;
	var proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
	var ICON_CAL = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>';
	var ICON_L = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
	var ICON_R = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';

	function fmt(opts) { try { return new Intl.DateTimeFormat(lang, opts); } catch (e) { return new Intl.DateTimeFormat('en', opts); } }
	var fLong = fmt({ day: 'numeric', month: 'long', year: 'numeric' });
	var fMonth = fmt({ month: 'long', year: 'numeric' });
	var fDay = fmt({ weekday: 'short' });
	var fFull = fmt({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function ymd(dt) { return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()); }
	function parse(s) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
		if (!m) return null;
		var dt = new Date(+m[1], +m[2] - 1, +m[3], 12);
		return isNaN(dt) ? null : dt;
	}
	function format(s) { var dt = parse(s); return dt ? fLong.format(dt) : ''; }
	function today() { var n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate(), 12); }
	function addDays(dt, n) { return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate() + n, 12); }
	function addMonths(dt, n) {
		var y = dt.getFullYear(), m = dt.getMonth() + n, last = new Date(y, m + 1, 0).getDate();
		return new Date(y, m, Math.min(dt.getDate(), last), 12);
	}
	function cap(s) { return s ? s.charAt(0).toLocaleUpperCase(lang) + s.slice(1) : s; }

	function skip(input) {
		if (input.flxaDate || input.hasAttribute('data-flxa-native')) return true;
		if (!d.body.classList.contains('flxa') || d.body.classList.contains('flxa-appmode')) return true;
		if (input.closest('.block-editor, .edit-post-layout, .components-popover, [data-flxa-native]')) return true;
		for (var k in input) { if (k.indexOf('__react') === 0) return true; }
		return false;
	}

	/* ---------- düğme (görünen alan) ---------- */
	function enhanceOne(input) {
		if (skip(input)) return;
		var btn = d.createElement('button');
		btn.type = 'button';
		btn.className = 'flxa-date';
		btn.setAttribute('aria-haspopup', 'dialog');
		btn.innerHTML = '<span class="flxa-date-ico">' + ICON_CAL + '</span><span class="flxa-date-val"></span>';
		var w = input.offsetWidth, pw = input.parentNode && input.parentNode.clientWidth;
		var cs = getComputedStyle(input);
		if (w && pw && w >= pw - 24) btn.style.width = '100%';
		else if (w) btn.style.minWidth = Math.max(w, 150) + 'px';
		if (cs.maxWidth && cs.maxWidth !== 'none') btn.style.maxWidth = cs.maxWidth;
		if (input.id) {
			var lab = d.querySelector('label[for="' + input.id + '"]');
			if (lab) { if (!lab.id) lab.id = input.id + '-flxa-l'; btn.setAttribute('aria-labelledby', lab.id + ' ' + (input.id + '-flxa-v')); }
		}
		btn.querySelector('.flxa-date-val').id = input.id ? input.id + '-flxa-v' : '';
		input.parentNode.insertBefore(btn, input.nextSibling);
		input.classList.add('flxa-date-native');
		input.tabIndex = -1;
		input.setAttribute('aria-hidden', 'true');
		input.flxaDate = { btn: btn, paint: paint };

		function paint() {
			var v = proto.get.call(input);
			var val = btn.querySelector('.flxa-date-val');
			val.textContent = v ? format(v) : (input.getAttribute('placeholder') || T.datePick || 'Select date');
			btn.classList.toggle('is-empty', !v);
			btn.disabled = input.disabled;
			btn.classList.toggle('is-readonly', input.readOnly);
			if (v) btn.classList.remove('is-invalid');
		}
		/* Kod value'yu değiştirince düğme de güncellensin. */
		try {
			Object.defineProperty(input, 'value', {
				configurable: true,
				get: function () { return proto.get.call(this); },
				set: function (v) { proto.set.call(this, v); paint(); }
			});
		} catch (e) { /* eski tarayıcı: olaylarla yetin */ }
		input.addEventListener('change', paint);
		input.addEventListener('input', paint);
		input.addEventListener('invalid', function () { btn.classList.add('is-invalid'); });
		input.addEventListener('focus', function () { btn.focus(); });
		input.addEventListener('click', function (e) { e.preventDefault(); openFor(input); });
		if (window.MutationObserver) new MutationObserver(paint).observe(input, { attributes: true, attributeFilter: ['value', 'disabled', 'readonly', 'min', 'max'] });
		if (input.form) input.form.addEventListener('reset', function () { setTimeout(paint, 0); });
		btn.addEventListener('click', function () { openFor(input); });
		btn.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); openFor(input); }
			else if ((e.key === 'Backspace' || e.key === 'Delete') && !input.required && !input.readOnly) { e.preventDefault(); setVal(input, ''); }
		});
		paint();
	}

	function setVal(input, v) {
		if (proto.get.call(input) === v) return;
		proto.set.call(input, v);
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		if (input.flxaDate) input.flxaDate.paint();
	}

	/* ---------- takvim penceresi ---------- */
	var pop, cur = null, view, focusDay;

	function build() {
		pop = d.createElement('div');
		pop.className = 'flxa-dp';
		pop.setAttribute('role', 'dialog');
		pop.setAttribute('aria-modal', 'false');
		pop.hidden = true;
		pop.innerHTML =
			'<div class="flxa-dp-head">' +
				'<button type="button" class="flxa-dp-nav" data-m="-1" aria-label="' + (T.datePrev || 'Previous month') + '">' + ICON_L + '</button>' +
				'<div class="flxa-dp-title" aria-live="polite"></div>' +
				'<button type="button" class="flxa-dp-nav" data-m="1" aria-label="' + (T.dateNext || 'Next month') + '">' + ICON_R + '</button>' +
			'</div>' +
			'<div class="flxa-dp-week" aria-hidden="true"></div>' +
			'<div class="flxa-dp-grid" role="grid"></div>' +
			'<div class="flxa-dp-foot">' +
				'<button type="button" class="flxa-dp-act" data-a="clear">' + (T.dateClear || 'Clear') + '</button>' +
				'<button type="button" class="flxa-dp-act is-main" data-a="today">' + (T.dateToday || 'Today') + '</button>' +
			'</div>';
		d.body.appendChild(pop);
		var wk = pop.querySelector('.flxa-dp-week');
		var base = new Date(2024, 0, 1, 12); // Pazartesi
		for (var i = 0; i < 7; i++) {
			var dd = addDays(base, ((weekStart + 6) % 7) + i);
			var s = d.createElement('span');
			s.textContent = cap(fDay.format(dd)).replace('.', '');
			wk.appendChild(s);
		}
		pop.addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b || !cur) return;
			if (b.dataset.m) { view = addMonths(view, +b.dataset.m); focusDay = new Date(view.getFullYear(), view.getMonth(), Math.min(focusDay.getDate(), new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate()), 12); render(false); }
			else if (b.dataset.day) pick(b.dataset.day);
			else if (b.dataset.a === 'today') { var t = ymd(today()); if (allowed(t)) pick(t); else { view = today(); focusDay = today(); render(true); } }
			else if (b.dataset.a === 'clear') { setVal(cur, ''); close(true); }
		});
		pop.addEventListener('keydown', onKey);
		d.addEventListener('mousedown', function (e) {
			if (cur && !pop.contains(e.target) && e.target !== cur.flxaDate.btn && !cur.flxaDate.btn.contains(e.target)) close(false);
		}, true);
		window.addEventListener('resize', function () { if (cur) place(); });
		window.addEventListener('scroll', function () { if (cur) place(); }, true);
	}

	function allowed(s) {
		if (!cur) return true;
		var mn = cur.min, mx = cur.max;
		return !((mn && s < mn) || (mx && s > mx));
	}

	function render(focus) {
		pop.querySelector('.flxa-dp-title').textContent = cap(fMonth.format(view));
		var grid = pop.querySelector('.flxa-dp-grid');
		grid.innerHTML = '';
		var first = new Date(view.getFullYear(), view.getMonth(), 1, 12);
		var lead = (first.getDay() - weekStart + 7) % 7;
		var start = addDays(first, -lead);
		var sel = proto.get.call(cur), tday = ymd(today()), fd = ymd(focusDay);
		var row;
		for (var i = 0; i < 42; i++) {
			if (i % 7 === 0) { row = d.createElement('div'); row.setAttribute('role', 'row'); row.className = 'flxa-dp-row'; grid.appendChild(row); }
			var dt = addDays(start, i), s = ymd(dt);
			var b = d.createElement('button');
			b.type = 'button';
			b.setAttribute('role', 'gridcell');
			b.className = 'flxa-dp-day';
			b.textContent = dt.getDate();
			b.dataset.day = s;
			b.setAttribute('aria-label', fFull.format(dt));
			b.tabIndex = s === fd ? 0 : -1;
			if (dt.getMonth() !== view.getMonth()) b.classList.add('is-out');
			if (s === tday) b.classList.add('is-today');
			if (s === sel) { b.classList.add('is-sel'); b.setAttribute('aria-selected', 'true'); }
			if (!allowed(s)) { b.disabled = true; }
			row.appendChild(b);
		}
		var mn = parse(cur.min), mx = parse(cur.max);
		pop.querySelector('[data-m="-1"]').disabled = !!(mn && new Date(view.getFullYear(), view.getMonth(), 0, 12) < mn);
		pop.querySelector('[data-m="1"]').disabled = !!(mx && new Date(view.getFullYear(), view.getMonth() + 1, 1, 12) > mx);
		pop.querySelector('[data-a="clear"]').hidden = cur.required;
		var t = pop.querySelector('[data-a="today"]');
		t.disabled = !allowed(tday);
		if (focus) { var f = grid.querySelector('[data-day="' + fd + '"]'); if (f) f.focus(); }
	}

	function place() {
		var r = cur.flxaDate.btn.getBoundingClientRect();
		var vw = window.innerWidth, vh = window.innerHeight;
		if (vw <= 600) { pop.classList.add('is-sheet'); pop.style.left = pop.style.top = ''; return; }
		pop.classList.remove('is-sheet');
		var pw = pop.offsetWidth, ph = pop.offsetHeight;
		var left = Math.min(Math.max(8, r.left), vw - pw - 8);
		var top = r.bottom + 6;
		if (top + ph > vh - 8 && r.top - ph - 6 > 8) top = r.top - ph - 6;
		pop.style.left = left + 'px';
		pop.style.top = top + 'px';
	}

	function openFor(input) {
		if (!input.flxaDate || input.disabled || input.readOnly) return;
		if (!pop) build();
		if (cur === input) { close(true); return; }
		cur = input;
		var v = parse(proto.get.call(input));
		var base = v || today();
		var mn = parse(input.min), mx = parse(input.max);
		if (!v && mn && base < mn) base = mn;
		if (!v && mx && base > mx) base = mx;
		view = new Date(base.getFullYear(), base.getMonth(), 1, 12);
		focusDay = base;
		pop.setAttribute('aria-label', input.flxaDate.btn.textContent);
		input.flxaDate.btn.setAttribute('aria-expanded', 'true');
		input.flxaDate.btn.classList.add('is-open');
		pop.hidden = false;
		render(true);
		place();
	}

	function close(refocus) {
		if (!cur) return;
		var b = cur.flxaDate.btn;
		b.setAttribute('aria-expanded', 'false');
		b.classList.remove('is-open');
		pop.hidden = true;
		cur = null;
		if (refocus) b.focus();
	}

	function pick(s) {
		if (!allowed(s)) return;
		var input = cur;
		close(true);
		setVal(input, s);
	}

	function onKey(e) {
		if (!cur) return;
		if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); return; }
		if (e.key === 'Tab') {
			var f = Array.prototype.filter.call(pop.querySelectorAll('button'), function (b) { return !b.disabled && !b.hidden && b.tabIndex !== -1; });
			if (!f.length) return;
			if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
			return;
		}
		if (!e.target.classList.contains('flxa-dp-day')) return;
		var mv = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
		var nd = null;
		if (mv) nd = addDays(focusDay, mv);
		else if (e.key === 'PageUp') nd = addMonths(focusDay, e.shiftKey ? -12 : -1);
		else if (e.key === 'PageDown') nd = addMonths(focusDay, e.shiftKey ? 12 : 1);
		else if (e.key === 'Home') nd = addDays(focusDay, -((focusDay.getDay() - weekStart + 7) % 7));
		else if (e.key === 'End') nd = addDays(focusDay, 6 - ((focusDay.getDay() - weekStart + 7) % 7));
		if (!nd) return;
		e.preventDefault();
		focusDay = nd;
		if (nd.getMonth() !== view.getMonth() || nd.getFullYear() !== view.getFullYear()) view = new Date(nd.getFullYear(), nd.getMonth(), 1, 12);
		render(true);
	}

	function enhance(root) {
		Array.prototype.forEach.call((root || d).querySelectorAll('input[type="date"]'), enhanceOne);
	}

	window.flxaDate = { enhance: enhance, open: openFor, format: format };

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
