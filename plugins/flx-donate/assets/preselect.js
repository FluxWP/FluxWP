/* Flux Donate · preselect an option and quantity on a Flux Forms form linked to a donation type.
 * window.FLXB.preselect({ form, option, qty, beneficiaries, lock }) -> Promise<boolean>; URL ?flxb_opt=&flxb_qty=.
 * lock (default: the type's "hide the choice" setting) replaces the choice with a summary and a
 * "Change" link; FLXB.unlock(form) shows it again.
 * Choices that cannot be paid (inactive option, price not announced) are hidden and disabled.
 * Events on document: flxb:ready { form }, flxb:change { form, option, qty, total }, flxb:unlock { form }. */
(function () {
	'use strict';
	var forms = window.flxbForms || {};

	function first(form) {
		if (form && forms[form]) return forms[form];
		for (var k in forms) if (Object.prototype.hasOwnProperty.call(forms, k)) return forms[k];
		return null;
	}

	function field(id) {
		return document.getElementById(id + '_') || document.getElementById(id);
	}

	function choiceEl(id) {
		var el = document.getElementById(id);
		if (el) return el;
		return document.querySelector('option[data-id="' + id + '"], option[data-vid="' + id + '"]');
	}

	function ready(d) {
		if (document.readyState !== 'complete') return false;
		var all = [];
		Object.keys(d.opts).forEach(function (k) { d.opts[k].forEach(function (c) { all.push(c.el); }); });
		return all.some(function (id) { return !!choiceEl(id); });
	}

	function whenReady(d, timeout) {
		return new Promise(function (resolve) {
			/* Give Flux Forms a moment to bind its own handlers after the fields appear. */
			var done = function () { setTimeout(function () { resolve(true); }, 400); };
			if (ready(d)) return done();
			var waited = 0;
			var t = setInterval(function () {
				waited += 150;
				if (ready(d)) { clearInterval(t); done(); } else if (waited >= timeout) { clearInterval(t); resolve(false); }
			}, 150);
		});
	}

	function fire(el, types) {
		types.forEach(function (ty) { el.dispatchEvent(new Event(ty, { bubbles: true })); });
	}

	function setValue(el, v) {
		var proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
		var desc = Object.getOwnPropertyDescriptor(proto, 'value');
		if (desc && desc.set) desc.set.call(el, v); else el.value = v;
		fire(el, ['input', 'change', 'keyup']);
	}

	function pick(el) {
		if (el.tagName === 'OPTION') {
			var sel = el.closest('select');
			if (sel && sel.value !== el.value) { sel.value = el.value; fire(sel, ['change']); }
			return true;
		}
		if (el.type === 'radio' || (el.type === 'checkbox' && !el.checked)) el.click();
		return el.checked !== false;
	}

	function current(d) {
		var out = { form: d.form, option: '', qty: 0, total: 0 };
		Object.keys(d.opts).forEach(function (k) {
			d.opts[k].forEach(function (c) {
				var el = choiceEl(c.el);
				var on = el && (el.tagName === 'OPTION' ? el.selected : el.checked);
				if (on) { out.option = k; out.qty += c.units; }
			});
		});
		if (out.option && d.qty) {
			var q = field(d.qty);
			var n = q ? parseInt(q.value, 10) : 1;
			out.qty = out.qty * (n > 0 ? n : 1);
		}
		var p = out.option ? d.prices[out.option] : null;
		out.total = p ? Math.round(p * out.qty * 100) / 100 : 0;
		return out;
	}

	function t(k, fb) {
		var i = window.flxbI18n || {};
		return i[k] || fb;
	}

	function money(v, cur) {
		try {
			return Number(v).toLocaleString(document.documentElement.lang || undefined, { style: 'currency', currency: cur || 'TRY', maximumFractionDigits: v % 1 ? 2 : 0 });
		} catch (e) {
			return String(v) + ' ' + (cur || '');
		}
	}

	/* Inactive / unpriced choices: not selectable (the server refuses them anyway). */
	function disableOff(d) {
		(d.off || []).forEach(function (id) {
			var el = choiceEl(id);
			if (!el) return;
			if (el.tagName === 'OPTION') {
				el.disabled = true;
				el.hidden = true;
				if (el.selected) { var sel = el.closest('select'); if (sel) { sel.value = ''; fire(sel, ['change']); } }
				return;
			}
			if (el.checked) { el.checked = false; fire(el, ['change']); }
			el.disabled = true;
			el.setAttribute('aria-disabled', 'true');
			var w = document.getElementById(id + '-v') || el.closest('.form-check');
			if (w) w.style.setProperty('display', 'none', 'important');
		});
	}

	function fieldBox(el) {
		return el ? (el.closest('div.efbField') || el.closest('div[data-tag]')) : null;
	}

	function unlock(form) {
		var d = first(form);
		if (!d || !d._lock) return false;
		d._lock.boxes.forEach(function (b) { b.style.removeProperty('display'); var o = b.getAttribute('data-flxb-display'); if (o) b.style.display = o; b.removeAttribute('data-flxb-display'); });
		if (d._lock.note.parentNode) d._lock.note.parentNode.removeChild(d._lock.note);
		var focus = d._lock.boxes[0] && d._lock.boxes[0].querySelector('input:not([disabled]),select,textarea');
		d._lock = null;
		if (focus) focus.focus();
		document.dispatchEvent(new CustomEvent('flxb:unlock', { detail: { form: d.form } }));
		return true;
	}

	function lock(d, choice) {
		if (d._lock) unlock(d.form);
		var boxes = [];
		var b1 = fieldBox(choice);
		if (b1) boxes.push(b1);
		var q = d.qty ? fieldBox(field(d.qty)) : null;
		if (q && boxes.indexOf(q) === -1) boxes.push(q);
		if (!boxes.length) return;
		var c = current(d);
		var lab = choice.tagName === 'OPTION' ? choice.textContent : ((document.getElementById(choice.id + '_lab') || {}).textContent || choice.value || '');
		/* Same column box as Flux Forms's own fields, so it lines up with them. */
		var note = document.createElement('div');
		note.className = 'efb col-12 flxb-locked-wrap';
		var inner = document.createElement('div');
		inner.className = 'flxb-locked';
		inner.setAttribute('role', 'status');
		note.appendChild(inner);
		var txt = document.createElement('span');
		txt.className = 'flxb-locked__text';
		txt.appendChild(document.createTextNode(t('choice', 'Your choice') + ': '));
		var strong = document.createElement('strong');
		strong.textContent = String(lab).trim() + (d.qty && c.qty > 1 && choice.tagName !== 'OPTION' ? ' × ' + (parseInt((field(d.qty) || {}).value, 10) || 1) : '');
		txt.appendChild(strong);
		if (c.total) txt.appendChild(document.createTextNode(' · ' + money(c.total, d.currency)));
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'flxb-locked__change';
		btn.textContent = t('change', 'Change');
		btn.addEventListener('click', function () { unlock(d.form); });
		inner.appendChild(txt);
		inner.appendChild(btn);
		boxes[0].parentNode.insertBefore(note, boxes[0]);
		boxes.forEach(function (b) { b.setAttribute('data-flxb-display', b.style.display || ''); b.style.setProperty('display', 'none', 'important'); });
		d._lock = { boxes: boxes, note: note };
	}

	function preselect(args) {
		args = args || {};
		var d = first(args.form);
		if (!d) return Promise.resolve(false);
		var key = String(args.option || '');
		var qty = Math.max(1, parseInt(args.qty, 10) || 1);
		var cands = d.opts[key];
		if (!cands || !cands.length || d.prices[key] === null || d.prices[key] === undefined) return Promise.resolve(false);
		if (d.maxQty > 0 && qty > d.maxQty) return Promise.resolve(false);
		return whenReady(d, 15000).then(function (ok) {
			if (!ok) return false;
			var exact = cands.filter(function (c) { return c.units === qty; })[0];
			var one = cands.filter(function (c) { return c.units === 1; })[0];
			var c, n = 1;
			if (d.qty && one) { c = one; n = qty; } else if (exact) { c = exact; } else if (qty === 1 && one) { c = one; } else { return false; }
			var el = choiceEl(c.el);
			if (!el || !pick(el)) return false;
			if (d.qty) { var q = field(d.qty); if (q) setValue(q, String(n)); else if (n !== 1) return false; }
			if (args.beneficiaries && d.beneficiaries) {
				var b = field(d.beneficiaries);
				if (b) setValue(b, [].concat(args.beneficiaries).join('\n'));
			}
			var doLock = typeof args.lock === 'boolean' ? args.lock : !!d.lock;
			if (doLock) lock(d, el); else if (d._lock) unlock(d.form);
			return true;
		});
	}

	window.FLXB = window.FLXB || {};
	window.FLXB.preselect = preselect;
	window.FLXB.unlock = unlock;
	window.FLXB.forms = forms;

	Object.keys(forms).forEach(function (k) {
		var d = forms[k];
		whenReady(d, 20000).then(function (ok) {
			if (!ok) return;
			disableOff(d);
			document.dispatchEvent(new CustomEvent('flxb:ready', { detail: { form: d.form } }));
			var last = '';
			var notify = function () {
				var c = current(d);
				var sig = c.option + '|' + c.qty;
				if (sig !== last) { last = sig; document.dispatchEvent(new CustomEvent('flxb:change', { detail: c })); }
			};
			document.addEventListener('change', notify, true);
			document.addEventListener('input', notify, true);
		});
	});

	var q = new URLSearchParams(window.location.search);
	if (q.get('flxb_opt')) {
		preselect({ form: parseInt(q.get('flxb_form'), 10) || 0, option: q.get('flxb_opt'), qty: q.get('flxb_qty') || 1 });
	}
})();
