/* Flux Donate 2.1.0 (2.2.3) · card fields for iyzico (block checkout, classic checkout, order-pay page). GPL v2 or later.
 * The card is only read from these inputs when the order is placed; it is not kept in the browser. */
(function () {
	'use strict';
	var D = window.FLXBCard || {};
	var T = D.i18n || {};

	function h(tag, attrs, kids) {
		var e = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') e.textContent = attrs[k];
			else if (k === 'html') e.innerHTML = attrs[k];
			else if (attrs[k] !== false && attrs[k] != null) e.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
		});
		(kids || []).forEach(function (c) { if (c) e.appendChild(c); });
		return e;
	}
	function digits(s) { return String(s || '').replace(/\D+/g, ''); }
	function brand(n) {
		if (/^4/.test(n)) return 'Visa';
		if (/^(5[1-5]|2[2-7])/.test(n)) return 'Mastercard';
		if (/^3[47]/.test(n)) return 'Amex';
		if (/^9792/.test(n)) return 'Troy';
		return '';
	}
	function luhn(n) {
		var sum = 0, alt = false;
		for (var i = n.length - 1; i >= 0; i--) {
			var d = +n[i];
			if (alt) { d *= 2; if (d > 9) d -= 9; }
			sum += d; alt = !alt;
		}
		return n.length >= 12 && sum % 10 === 0;
	}
	function money(v) {
		try { return new Intl.NumberFormat(D.locale || 'tr-TR', { style: 'currency', currency: D.currency || 'TRY' }).format(v); }
		catch (e) { return String(v); }
	}
	function fmt(s, tpl) { var a = [].slice.call(arguments, 1); return String(s).replace(/%(\d)\$s/g, function (m, i) { return a[i - 1]; }); }

	function mount(node, opts) {
		opts = opts || {};
		var named = !opts.blocks;
		var nm = function (n) { return named ? 'flxb_card_' + n : false; };
		var st = { saved: '', inst: 1, bin: '', req: 0 };
		var cards = D.cards || [];
		node.innerHTML = '';
		node.classList.add('flxb-cardpay');

		/* Saved cards */
		var savedBox = null;
		if (cards.length) {
			var opts2 = cards.map(function (c, i) {
				return h('label', { class: 'flxb-card__opt' }, [h('input', { type: 'radio', name: 'flxb_card_pick', value: c.id, checked: i === 0 }), h('span', { text: c.label })]);
			});
			opts2.push(h('label', { class: 'flxb-card__opt' }, [h('input', { type: 'radio', name: 'flxb_card_pick', value: '' }), h('span', { text: T.newCard })]));
			savedBox = h('fieldset', { class: 'flxb-card__saved' }, [h('legend', { text: T.savedCards })].concat(opts2));
			node.appendChild(savedBox);
			st.saved = cards[0].id;
		}
		var savedInput = h('input', { type: 'hidden', name: nm('saved'), value: st.saved });

		/* New card */
		var num = h('input', { type: 'text', inputmode: 'numeric', autocomplete: 'cc-number', name: nm('number'), id: 'flxb-cc-number', maxlength: '23', placeholder: '0000 0000 0000 0000', 'aria-describedby': 'flxb-cc-number-e', spellcheck: 'false' });
		var badge = h('span', { class: 'flxb-card__brand', 'aria-hidden': 'true' });
		var name = h('input', { type: 'text', autocomplete: 'cc-name', name: nm('name'), id: 'flxb-cc-name', maxlength: '60', 'aria-describedby': 'flxb-cc-name-e', spellcheck: 'false' });
		var exp = h('input', { type: 'text', inputmode: 'numeric', autocomplete: 'cc-exp', name: nm('exp'), id: 'flxb-cc-exp', maxlength: '7', placeholder: T.expHolder || 'MM/YY', 'aria-describedby': 'flxb-cc-exp-e' });
		var cvc = h('input', { type: 'text', inputmode: 'numeric', autocomplete: 'cc-csc', name: nm('cvc'), id: 'flxb-cc-cvc', maxlength: '4', placeholder: '•••', 'aria-describedby': 'flxb-cc-cvc-e' });
		function field(id, label, input, extra) {
			return h('div', { class: 'flxb-card__f' }, [h('label', { for: id, text: label }), h('div', { class: 'flxb-card__in' }, [input, extra || null]), h('p', { class: 'flxb-card__err', id: id + '-e', role: 'alert' })]);
		}
		var fresh = h('div', { class: 'flxb-card__new' }, [
			field('flxb-cc-number', T.number, num, badge),
			field('flxb-cc-name', T.name, name),
			h('div', { class: 'flxb-card__row' }, [field('flxb-cc-exp', T.exp, exp), field('flxb-cc-cvc', T.cvc, cvc)])
		]);
		node.appendChild(fresh);
		node.appendChild(savedInput);

		/* Installments */
		var instInput = h('input', { type: 'hidden', name: nm('inst'), value: '1' });
		var instList = h('div', { class: 'flxb-card__inst-list', role: 'radiogroup' });
		var instBox = h('fieldset', { class: 'flxb-card__inst', hidden: true }, [h('legend', { text: T.inst }), instList]);
		node.appendChild(instBox);
		node.appendChild(instInput);

		/* Save / monthly */
		var save = null;
		if (D.monthly) node.appendChild(h('p', { class: 'flxb-card__note', text: T.monthly }));
		else if (D.canSave) {
			save = h('input', { type: 'checkbox', name: nm('save'), value: '1' });
			node.appendChild(h('label', { class: 'flxb-card__save' }, [save, h('span', {}, [h('b', { text: T.save }), h('small', { text: T.saveHelp })])]));
		}
		node.appendChild(h('p', { class: 'flxb-card__secure', text: T.secure }));
		if (D.sandbox) {
			/* 2.2.3: one tap fills the sandbox card, so a typo never reaches iyzico. */
			var fill = h('button', { type: 'button', class: 'flxb-card__fill', text: T.testFill });
			fill.addEventListener('click', function () {
				if (st.saved && savedBox) { var o = savedBox.querySelector('input[value=""]'); if (o) { o.checked = true; st.saved = ''; showNew(); } }
				var set = function (el, v) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); };
				set(num, '5528790000000008'); set(exp, '1230'); set(cvc, '123');
				if (!name.value.trim()) set(name, 'Test Kart');
			});
			node.appendChild(h('div', { class: 'flxb-card__test' }, [h('span', { text: T.test }), fill]));
		}

		function showNew() {
			fresh.hidden = !!st.saved;
			savedInput.value = st.saved;
			if (save) save.closest('label').hidden = !!st.saved;
		}
		if (savedBox) {
			savedBox.addEventListener('change', function (e) {
				if (e.target.name !== 'flxb_card_pick') return;
				st.saved = e.target.value; st.bin = ''; showNew(); loadInst();
			});
		}
		showNew();

		function setInst(rows) {
			instList.innerHTML = '';
			st.inst = 1; instInput.value = '1';
			if (!rows || rows.length < 2 || (D.maxInst || 1) < 2) { instBox.hidden = true; return; }
			rows.forEach(function (r, i) {
				var lab = r[0] === 1 ? T.single + ' · ' + money(r[2]) : fmt(T.instRow, r[0], money(r[1]), money(r[2]));
				var inp = h('input', { type: 'radio', name: 'flxb_card_inst_pick', value: r[0], checked: i === 0 });
				instList.appendChild(h('label', { class: 'flxb-card__opt' }, [inp, h('span', { text: lab })]));
			});
			instBox.hidden = false;
		}
		instList.addEventListener('change', function (e) { st.inst = +e.target.value || 1; instInput.value = String(st.inst); });

		function loadInst() {
			if ((D.maxInst || 1) < 2 || !D.inst) { setInst(null); return; }
			var body = {};
			if (st.saved) body.card = st.saved;
			else if (st.bin.length >= 8) body.bin = st.bin;
			else { setInst(null); return; }
			if (node.dataset.order) { body.order = node.dataset.order; body.key = node.dataset.key; }
			var my = ++st.req;
			fetch(D.inst, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce }, body: JSON.stringify(body) })
				.then(function (r) { return r.json(); })
				.then(function (j) { if (my !== st.req) return; if (j && j.family && !st.saved) badge.textContent = j.family; setInst(j && j.rows); })
				.catch(function () { if (my === st.req) setInst(null); });
		}

		num.addEventListener('input', function () {
			var d = digits(num.value).slice(0, 19), b = brand(d);
			num.value = b === 'Amex' ? d.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, function (m, a, c, e) { return [a, c, e].filter(Boolean).join(' '); }) : d.replace(/(\d{4})(?=\d)/g, '$1 ');
			badge.textContent = b;
			var bin = d.slice(0, 8);
			if (bin.length === 8 && bin !== st.bin) { st.bin = bin; loadInst(); }
			else if (bin.length < 8 && st.bin) { st.bin = ''; setInst(null); }
			clear(num);
		});
		exp.addEventListener('input', function (e) {
			var d = digits(exp.value).slice(0, 4);
			if (d.length === 1 && +d > 1) d = '0' + d;
			exp.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : (d.length === 2 && e.inputType !== 'deleteContentBackward' ? d + '/' : d);
			clear(exp);
		});
		cvc.addEventListener('input', function () { cvc.value = digits(cvc.value).slice(0, 4); clear(cvc); });
		name.addEventListener('input', function () { clear(name); });

		function err(input, msg) {
			input.setAttribute('aria-invalid', 'true');
			var p = document.getElementById(input.id + '-e'); if (p) p.textContent = msg;
		}
		function clear(input) {
			input.removeAttribute('aria-invalid');
			var p = document.getElementById(input.id + '-e'); if (p) p.textContent = '';
		}

		var api = {
			/* First problem as text (also marks the field), or '' when the card can be sent. */
			validate: function () {
				if (st.saved) return '';
				var d = digits(num.value), first = '', ed = digits(exp.value), now = new Date();
				var m = +ed.slice(0, 2), y = 2000 + (+ed.slice(2, 4));
				var checks = [
					[num, luhn(d) && d.length <= 19, T.badNumber],
					[name, name.value.trim().length >= 3, T.badName],
					[exp, ed.length === 4 && m >= 1 && m <= 12 && (y > now.getFullYear() || (y === now.getFullYear() && m >= now.getMonth() + 1)), T.badExp],
					[cvc, /^\d{3,4}$/.test(cvc.value), T.badCvc]
				];
				if (D.testCards && D.testCards.length && luhn(d)) checks[0] = [num, D.testCards.indexOf(d) >= 0, T.notTest];
				checks.forEach(function (c) { if (!c[1]) { err(c[0], c[2]); if (!first) { first = c[2]; c[0].focus(); } } else clear(c[0]); });
				return first;
			},
			data: function () {
				return {
					flxb_card_saved: st.saved,
					flxb_card_number: st.saved ? '' : digits(num.value),
					flxb_card_name: st.saved ? '' : name.value.trim(),
					flxb_card_exp: st.saved ? '' : digits(exp.value),
					flxb_card_cvc: st.saved ? '' : cvc.value,
					flxb_card_inst: String(st.inst),
					flxb_card_save: save && save.checked && !st.saved ? '1' : ''
				};
			},
			reload: loadInst
		};
		if (st.saved) loadInst();
		node.flxbCard = api;
		return api;
	}

	window.FLXBCardForm = { mount: mount };

	/* Classic checkout and the order-pay page. */
	function classic() {
		document.querySelectorAll('[data-flxb-card]').forEach(function (n) { if (!n.flxbCard) mount(n, {}); });
	}
	function selected() {
		var r = document.querySelector('input[name="payment_method"]:checked');
		return r && r.value === 'flxb_iyzico';
	}
	function block(e) {
		var n = document.querySelector('[data-flxb-card]');
		if (!n || !n.flxbCard || !selected()) return true;
		if (n.flxbCard.validate()) { if (e) { e.preventDefault(); e.stopImmediatePropagation(); } return false; }
		return true;
	}
	document.addEventListener('DOMContentLoaded', classic);
	if (window.jQuery) {
		window.jQuery(document.body).on('updated_checkout payment_method_selected', classic);
		window.jQuery('form.checkout').on('checkout_place_order_flxb_iyzico', function () { return block(); });
	}
	document.addEventListener('submit', function (e) { if (e.target && e.target.id === 'order_review') block(e); }, true);
})();
