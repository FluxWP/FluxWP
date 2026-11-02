/* Flux Donate 2.0.3: saved address drop-down on the checkout (block and classic). GPL v2 or later. */
(function () {
	'use strict';
	var D = window.FLXBAddr;
	if (!D || !D.rows || !D.rows.length) { return; }
	var KEYS = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'];
	var norm = function (v) { return String(v || '').trim().toLowerCase().replace(/\s+/g, ' '); };
	var sameAs = function (a, b) { return norm(a.address_1) === norm(b.address_1) && norm(a.city) === norm(b.city) && norm(a.postcode) === norm(b.postcode); };

	function build(current) {
		var wrap = document.createElement('div');
		wrap.className = 'flxb-addr';
		var id = 'flxb-addr-' + Math.random().toString(36).slice(2, 8);
		var head = document.createElement('div');
		head.className = 'flxb-addr__head';
		var lab = document.createElement('label');
		lab.htmlFor = id; lab.textContent = D.label;
		var link = document.createElement('a');
		link.href = D.url; link.textContent = D.manage; link.className = 'flxb-addr__manage';
		head.appendChild(lab); head.appendChild(link);
		var sel = document.createElement('select');
		sel.id = id; sel.className = 'flxb-addr__select';
		var pick = '';
		D.rows.forEach(function (r) {
			var o = document.createElement('option');
			o.value = r.id; o.textContent = r.text;
			sel.appendChild(o);
			if (!pick && current && sameAs(r.address, current)) { pick = r.id; }
		});
		var n = document.createElement('option');
		n.value = '__new'; n.textContent = D.newText;
		sel.appendChild(n);
		if (!pick) {
			var def = D.rows.filter(function (r) { return r.default; })[0];
			pick = current && norm(current.address_1) ? '__new' : (def ? def.id : D.rows[0].id);
		}
		sel.value = pick;
		var help = document.createElement('p');
		help.className = 'flxb-addr__help'; help.textContent = D.newHelp; help.hidden = pick !== '__new';
		wrap.appendChild(head); wrap.appendChild(sel); wrap.appendChild(help);
		return { wrap: wrap, sel: sel, help: help, pick: pick };
	}

	function row(id) { return D.rows.filter(function (r) { return r.id === id; })[0]; }

	/* ---------- Block checkout ---------- */
	function blockStore() { return window.wp && wp.data && wp.data.select('wc/store/cart') ? wp.data : null; }

	function applyBlock(id, ui) {
		var data = blockStore();
		if (!data) { return; }
		var cur = data.select('wc/store/cart').getCustomerData().billingAddress || {};
		var next = Object.assign({}, cur);
		if (id === '__new') {
			['company', 'address_1', 'address_2', 'city', 'state', 'postcode'].forEach(function (k) { next[k] = ''; });
		} else {
			var r = row(id);
			if (!r) { return; }
			KEYS.forEach(function (k) { if (r.address[k] !== undefined) { next[k] = r.address[k]; } });
		}
		data.dispatch('wc/store/cart').setBillingAddress(next);
		ui.help.hidden = id !== '__new';
		if (id === '__new') {
			setTimeout(function () {
				var edit = document.querySelector('#billing-fields .wc-block-components-address-card__edit, .wc-block-checkout__billing-fields .wc-block-components-address-card__edit');
				if (edit) { edit.click(); }
				var f = document.getElementById('billing-address_1');
				if (f) { f.focus(); }
			}, 60);
		}
	}

	function mountBlock() {
		var host = document.querySelector('#billing-fields .wc-block-components-checkout-step__content, .wc-block-checkout__billing-fields .wc-block-components-checkout-step__content');
		if (!host || host.querySelector('.flxb-addr')) { return !!host; }
		var data = blockStore();
		var cur = data ? (data.select('wc/store/cart').getCustomerData().billingAddress || {}) : {};
		var ui = build(cur);
		host.insertBefore(ui.wrap, host.firstChild);
		ui.sel.addEventListener('change', function () { applyBlock(ui.sel.value, ui); });
		if (ui.pick !== '__new' && !sameAs(row(ui.pick).address, cur)) { applyBlock(ui.pick, ui); }
		return true;
	}

	/* ---------- Classic checkout ---------- */
	function mountClassic(slot) {
		var get = function (k) { var el = document.getElementById('billing_' + k); return el ? el.value : ''; };
		var cur = {}; KEYS.forEach(function (k) { cur[k] = get(k); });
		var ui = build(cur);
		slot.appendChild(ui.wrap);
		ui.sel.addEventListener('change', function () {
			var id = ui.sel.value, r = row(id), $ = window.jQuery;
			ui.help.hidden = id !== '__new';
			KEYS.forEach(function (k) {
				var el = document.getElementById('billing_' + k);
				if (!el || (id === '__new' && ['first_name', 'last_name', 'country', 'phone'].indexOf(k) > -1)) { return; }
				var v = id === '__new' ? '' : (r ? r.address[k] || '' : '');
				el.value = v;
				if ($) { $(el).trigger('change'); } else { el.dispatchEvent(new Event('change', { bubbles: true })); }
				if (k === 'country' && r) { var st = document.getElementById('billing_state'); if (st) { st.value = r.address.state || ''; if ($) { $(st).trigger('change'); } } }
			});
			if ($) { $(document.body).trigger('update_checkout'); }
		});
	}

	function start() {
		var slot = document.querySelector('[data-flxb-addr-classic]');
		if (slot) { mountClassic(slot); return; }
		if (!document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout')) { return; }
		mountBlock();
		/* The block checkout renders late and may re-render the step: keep the list in place. */
		new MutationObserver(function () { mountBlock(); }).observe(document.body, { childList: true, subtree: true });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
