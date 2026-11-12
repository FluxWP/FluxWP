/* FluxWP form fields (flx-brand 1.5.0): formatting while typing, validation and one way to show errors.
 *
 * Markup comes from flx_field() / flx_check() (PHP) or flxField.html() (JS). Rules live on the input:
 *   required                      data-flx-format="card|exp|digits|money|upper|name"
 *   data-flx-rules="email luhn exp min:120 max:5000 minlen:3 maxlen:80 digits:3-4 phone"
 *   data-flx-msg-<rule>="…"      text for one rule (else the built-in text)
 * A form with data-flx-validate is checked on submit; the first wrong field gets the focus.
 *
 * data-flx-manual on the wrapper: markup and look only, the owner validates and formats itself (React forms).
 * API: window.flxField = { validate(el|form), setError(el, msg), clear(el), value(el), format(name, v),
 *      addRule(name, fn(value, arg, input) → true|false), addFormat(name, fn(value, input) → string), html(args) }
 * Event on the wrapper: "flx-field:change" { valid, value }.  GPL v2 or later. */
(function () {
	'use strict';
	if (window.flxField) { return; }
	var T = (window.FLX_FIELD && window.FLX_FIELD.i18n) || {};
	var digits = function (v) { return String(v || '').replace(/\D+/g, ''); };
	var num = function (v) {
		v = String(v || '').replace(/[^\d.,-]/g, '');
		if (v.indexOf(',') > -1) { v = v.replace(/\./g, '').replace(',', '.'); }
		return parseFloat(v);
	};
	var luhn = function (n) {
		var s = 0, alt = false;
		for (var i = n.length - 1; i >= 0; i--) { var d = +n[i]; if (alt) { d *= 2; if (d > 9) { d -= 9; } } s += d; alt = !alt; }
		return n.length >= 13 && n.length <= 19 && s % 10 === 0;
	};

	var formats = {
		digits: function (v, i) { var m = +i.getAttribute('maxlength') || 99; return digits(v).slice(0, m); },
		card: function (v) {
			var n = digits(v);
			if (/^3[47]/.test(n)) { return n.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, function (m, a, b, c) { return [a, b, c].filter(Boolean).join(' '); }); }
			return n.slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
		},
		exp: function (v) { var d = digits(v).slice(0, 4); return d.length > 2 ? d.slice(0, 2) + ' / ' + d.slice(2) : d; },
		upper: function (v) { return String(v).toLocaleUpperCase(document.documentElement.lang || undefined); },
		name: function (v) { return String(v).replace(/\s{2,}/g, ' ').replace(/^\s+/, ''); },
		/* Amount: digits with one decimal separator (the site's: data-flx-dec, default ","). */
		money: function (v, i) {
			var dec = i.getAttribute('data-flx-dec') || ',';
			v = String(v).replace(dec === ',' ? /\./g : /,/g, '').replace(/[^\d.,]/g, '');
			var p = v.split(dec);
			return p[0].replace(/^0+(?=\d)/, '') + (p.length > 1 ? dec + p.slice(1).join('').slice(0, 2) : '');
		}
	};

	var rules = {
		required: function (v, a, i) { return i.type === 'checkbox' || i.type === 'radio' ? i.checked : String(v).trim() !== ''; },
		email: function (v) { return !v || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v).trim()); },
		luhn: function (v) { return !v || luhn(digits(v)); },
		exp: function (v) {
			if (!v) { return true; }
			var d = digits(v); if (d.length !== 4) { return false; }
			var m = +d.slice(0, 2), y = 2000 + +d.slice(2), n = new Date();
			return m >= 1 && m <= 12 && y * 100 + m >= n.getFullYear() * 100 + n.getMonth() + 1;
		},
		min: function (v, a) { return !v || num(v) >= parseFloat(a); },
		max: function (v, a) { return !v || num(v) <= parseFloat(a); },
		minlen: function (v, a) { return !v || String(v).trim().length >= +a; },
		maxlen: function (v, a) { return String(v).length <= +a; },
		digits: function (v, a) { if (!v) { return true; } var r = String(a).split('-'), n = digits(v).length; return n >= +r[0] && n <= +(r[1] || r[0]); },
		phone: function (v) { if (!v) { return true; } var n = digits(v).length; return n >= 10 && n <= 15; }
	};

	var wrapOf = function (el) { return el && el.closest ? el.closest('.flx-fld, .flx-chk') : null; };
	var inputOf = function (w) { return w ? w.querySelector('.flx-fld__input, .flx-chk__input, .flx-ph__num') : null; };
	var msgOf = function (w, make) {
		var m = w.classList.contains('flx-chk') ? w.nextElementSibling : w.querySelector(':scope > .flx-fld__msg');
		if (m && !m.classList.contains('flx-fld__msg')) { m = null; }
		if (!m && make) {
			m = document.createElement('p'); m.className = 'flx-fld__msg'; m.setAttribute('role', 'alert'); m.hidden = true;
			if (w.classList.contains('flx-chk')) { w.parentNode.insertBefore(m, w.nextSibling); } else { var b = w.querySelector(':scope > .flx-fld__box'); w.insertBefore(m, b ? b.nextSibling : null); }
		}
		return m;
	};

	function setError(el, msg) {
		var w = wrapOf(el) || el; if (!w || !w.classList) { return; }
		var i = inputOf(w), m = msgOf(w, !!msg);
		w.classList.toggle('is-error', !!msg);
		if (msg) { w.classList.remove('is-ok'); }
		if (i) { i.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
		if (m) {
			if (m.textContent !== (msg || '')) { m.textContent = msg || ''; }
			m.hidden = !msg;
			if (msg && i) { if (!m.id) { m.id = 'flx-m-' + Math.random().toString(36).slice(2, 9); } i.setAttribute('aria-describedby', m.id); }
		}
	}
	function clear(el) { var w = wrapOf(el) || el; setError(w, ''); if (w && w.classList) { w.classList.remove('is-ok'); } }

	function text(i, rule, arg) {
		var own = i.getAttribute('data-flx-msg-' + rule) || i.getAttribute('data-flx-msg');
		if (own) { return own; }
		var s = T[rule] || T.invalid || '';
		return s.replace('%s', arg === undefined ? '' : arg);
	}

	/** Checks one field. Returns '' when fine, else the message (and shows it unless quiet). */
	function check(i, quiet) {
		var w = wrapOf(i); if (!w || i.disabled || (w.hidden || w.closest('[hidden]'))) { return ''; }
		if (w.hasAttribute('data-flx-manual')) { return ''; } /* the owner (React, own script) sets the state itself */
		var v = i.value, list = [];
		if (i.required || i.hasAttribute('data-flx-required')) { list.push(['required']); }
		String(i.getAttribute('data-flx-rules') || '').split(/\s+/).forEach(function (r) { if (r) { var p = r.split(':'); list.push([p[0], p.slice(1).join(':')]); } });
		var msg = '';
		for (var k = 0; k < list.length; k++) {
			var f = rules[list[k][0]];
			if (f && !f(v, list[k][1], i)) { msg = text(i, list[k][0], list[k][1]); break; }
		}
		/* The shared phone field knows better whether the number is real. */
		if (!msg && w.classList.contains('flx-fld--phone')) {
			var ph = w.querySelector('.flx-ph');
			if (ph && ph.classList.contains('is-bad')) { msg = text(i, 'phone'); }
		}
		if (!quiet) {
			setError(w, msg);
			w.classList.toggle('is-ok', !msg && String(v).trim() !== '' && w.hasAttribute('data-flx-tick'));
			w.dispatchEvent(new CustomEvent('flx-field:change', { bubbles: true, detail: { valid: !msg, value: v } }));
		}
		return msg;
	}

	function validate(root) {
		root = root || document;
		if (root.matches && root.matches('.flx-fld, .flx-chk')) { var one = inputOf(root); return one ? !check(one) : true; }
		if (root.matches && root.matches('input, select, textarea')) { return !check(root); }
		var first = null;
		root.querySelectorAll('.flx-fld__input, .flx-chk__input, .flx-fld--phone .flx-ph__num').forEach(function (i) {
			i.setAttribute('data-flx-touched', '1');
			if (check(i) && !first) { first = i; }
		});
		if (first) {
			var w = wrapOf(first);
			if (w && w.scrollIntoView) { w.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
			try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
		}
		return !first;
	}

	function applyFormat(i) {
		var f = formats[i.getAttribute('data-flx-format')];
		if (!f || (wrapOf(i) && wrapOf(i).hasAttribute('data-flx-manual'))) { return; }
		var old = i.value, next = f(old, i);
		if (next === old) { return; }
		var atEnd = i.selectionStart === null || i.selectionStart >= old.length;
		var pos = i.selectionStart;
		i.value = next;
		if (!atEnd && pos !== null) { try { i.setSelectionRange(pos, pos); } catch (e) {} }
	}

	document.addEventListener('input', function (e) {
		var i = e.target; if (!i.classList || !(i.classList.contains('flx-fld__input') || i.classList.contains('flx-chk__input'))) { return; }
		applyFormat(i);
		if (i.hasAttribute('data-flx-touched')) { check(i); }
	}, true);
	document.addEventListener('change', function (e) {
		var i = e.target; if (i.classList && i.classList.contains('flx-chk__input')) { i.setAttribute('data-flx-touched', '1'); check(i); }
	}, true);
	document.addEventListener('focusout', function (e) {
		var i = e.target; if (!i.classList || !(i.classList.contains('flx-fld__input') || i.classList.contains('flx-ph__num'))) { return; }
		if (!wrapOf(i)) { return; }
		if (String(i.value).trim() !== '' || i.hasAttribute('data-flx-touched')) { i.setAttribute('data-flx-touched', '1'); setTimeout(function () { check(i); }, 0); }
	}, true);
	document.addEventListener('flx-phone:change', function (e) {
		var w = e.target && e.target.closest ? e.target.closest('.flx-fld--phone') : null, i = w && w.querySelector('.flx-ph__num');
		if (i && i.hasAttribute('data-flx-touched')) { check(i); }
	}, true);
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (f && f.hasAttribute && f.hasAttribute('data-flx-validate') && !validate(f)) { e.preventDefault(); e.stopPropagation(); }
	}, true);

	/* ---------- Markup from JS (same as flx_field() in PHP) ---------- */
	var ICONS = (window.FLX_FIELD && window.FLX_FIELD.icons) || {};
	var esc = function (s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	var STATE = '<span class="flx-fld__state flx-fld__state--ok" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg></span><span class="flx-fld__state flx-fld__state--err" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 7v6M12 16.5v.5"/></svg></span>';
	function icon(name) { return ICONS[name] ? '<span class="flx-fld__ico" aria-hidden="true"><svg viewBox="0 0 24 24">' + ICONS[name] + '</svg></span>' : ''; }
	function html(a) {
		a = a || {};
		var id = a.id || 'flx-f-' + Math.random().toString(36).slice(2, 9), at = '';
		Object.keys(a.attrs || {}).forEach(function (k) { at += ' ' + esc(k) + '="' + esc(a.attrs[k]) + '"'; });
		var cls = 'flx-fld' + (a.class ? ' ' + esc(a.class) : '') + (a.error ? ' is-error' : '') + (a.readonly ? ' is-readonly' : '') + (a.wide ? ' flx-fld--wide' : '');
		return '<div class="' + cls + '"' + (a.tick ? ' data-flx-tick' : '') + '><label class="flx-fld__box" for="' + esc(id) + '">' + icon(a.icon) +
			'<span class="flx-fld__in"><span class="flx-fld__label">' + esc(a.label) + (a.required ? ' <em class="flx-fld__req" aria-hidden="true">*</em>' : '') + (a.hint ? '<small class="flx-fld__hint">' + esc(a.hint) + '</small>' : '') + '</span>' +
			'<input class="flx-fld__input" id="' + esc(id) + '" type="' + esc(a.type || 'text') + '"' + (a.name ? ' name="' + esc(a.name) + '"' : '') + ' value="' + esc(a.value) + '"' + (a.placeholder ? ' placeholder="' + esc(a.placeholder) + '"' : '') +
			(a.required ? ' required' : '') + (a.readonly ? ' readonly' : '') + (a.format ? ' data-flx-format="' + esc(a.format) + '"' : '') + (a.rules ? ' data-flx-rules="' + esc(a.rules) + '"' : '') + at + '></span>' +
			(a.suffix ? '<span class="flx-fld__suf">' + esc(a.suffix) + '</span>' : '') + STATE + '</label>' +
			'<p class="flx-fld__msg" role="alert"' + (a.error ? '' : ' hidden') + '>' + esc(a.error) + '</p>' + (a.help ? '<p class="flx-fld__help">' + esc(a.help) + '</p>' : '') + '</div>';
	}

	window.flxField = {
		validate: validate, check: check, setError: setError, clear: clear, html: html, icon: icon, icons: ICONS, state: STATE,
		value: function (el) { var i = inputOf(wrapOf(el) || el); return i ? i.value : ''; },
		format: function (name, v, i) { return formats[name] ? formats[name](v, i || document.createElement('input')) : v; },
		addRule: function (name, fn) { rules[name] = fn; }, addFormat: function (name, fn) { formats[name] = fn; },
		number: num, digits: digits, luhn: luhn
	};
})();
