/* Flux Forms · Builder skin behaviour: searchable palette, settings drawer (header, backdrop,
   click-to-edit, selected field), section navigation for the form settings.
   Works around EFB's own functions; never replaces them. */
(function () {
	'use strict';
	var L = window.FLXF_BUILDER || {};
	var body = document.body;
	var SEARCH_SVG = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';

	function el(tag, cls, html) { var n = document.createElement(tag); if (cls) n.className = cls; if (html != null) n.innerHTML = html; return n; }
	function norm(s) { return String(s || '').toLocaleLowerCase(document.documentElement.lang || undefined).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i'); }

	/* ---------- Palette: header with title + search ---------- */
	function setupPalette() {
		var list = document.getElementById('listElEfb');
		if (!list || list.querySelector('.flxb-pal-head')) return;
		var cats = document.getElementById('listCatEfb');
		var grid = list.querySelector(':scope > .row');
		var head = el('div', 'flxb-pal-head');
		head.appendChild(el('p', 'flxb-pal-title', '')).textContent = L.elements || 'Elements';
		var wrap = el('label', 'flxb-search', SEARCH_SVG);
		var input = el('input');
		input.type = 'search';
		input.placeholder = L.search || 'Search…';
		input.setAttribute('aria-label', L.search || 'Search');
		wrap.appendChild(input);
		head.appendChild(wrap);
		if (cats) head.appendChild(cats);
		list.insertBefore(head, list.firstChild);
		var empty = el('p', 'flxb-empty');
		empty.textContent = L.noResult || '';
		empty.hidden = true;
		if (grid) grid.appendChild(empty);
		input.addEventListener('input', function () {
			var q = norm(input.value.trim());
			var shown = 0;
			list.querySelectorAll('.tag.draggable-efb').forEach(function (t) {
				var b = t.querySelector('.btn-select-form');
				var name = norm((b && (b.title || b.textContent)) || t.id);
				var hit = !q || name.indexOf(q) > -1 || norm(t.id).indexOf(q) > -1;
				if (q) t.style.setProperty('display', hit ? 'block' : 'none', 'important'); else t.style.removeProperty('display');
				if (q && hit) shown++;
			});
			if (cats) cats.style.opacity = q ? '.45' : '';
			empty.hidden = !q || shown > 0;
		});
	}

	/* ---------- Drawer ---------- */
	var backdrop = el('div', 'flxb-backdrop');
	backdrop.addEventListener('click', function () { if (typeof window.sideMenuEfb === 'function') window.sideMenuEfb(0); });
	body.appendChild(backdrop);

	function drawer() { return document.getElementById('sideBoxEfb'); }
	function isOpen(d) { return d && !d.classList.contains('efbDW-0') && d.classList.contains('show'); }

	function clearSelected() { document.querySelectorAll('#dropZoneEFB .flxb-selected').forEach(function (n) { n.classList.remove('flxb-selected'); }); }

	function syncOpen() {
		var d = drawer();
		var open = isOpen(d);
		body.classList.toggle('flxb-drawer-open', !!open);
		if (!open) { clearSelected(); body.classList.remove('flxb-form-drawer'); }
	}

	function fieldInfo(idset) {
		if (idset === 'formSet') return { kicker: L.formTitle, title: (window.valj_efb && valj_efb[0] && valj_efb[0].formName) || L.formTitle, icon: 'bi-sliders' };
		if (idset === 'button_group') {
			var bt = document.getElementById('button_group_button_single_text');
			return { kicker: L.fieldTitle, title: bt ? bt.textContent.trim() : 'Button', icon: 'bi-hand-index' };
		}
		var row = (window.valj_efb || []).find(function (x) { return x && (x.dataId === idset || x.id_ + '-id' === idset); }) || {};
		if (!row.type) { var n = document.querySelector('#dropZoneEFB [data-id="' + idset + '"][data-tag]'); if (n) row = { type: n.getAttribute('data-tag') }; }
		if (!row.name) { var lab = document.getElementById('labelEl'); if (lab && lab.value) row.name = lab.value; }
		var pal = row.type ? document.getElementById(row.type + '_b') : null;
		var icon = 'bi-input-cursor-text';
		if (pal) { var i = pal.querySelector('.tagIcon'); if (i) { var m = i.className.match(/\bbi-[a-z0-9-]+/); if (m) icon = m[0]; } }
		var typeName = pal ? (pal.title || pal.textContent.trim()) : (row.type || '');
		return { kicker: (L.fieldTitle || '') + (typeName ? ' · ' + typeName : ''), title: row.name || typeName, icon: icon };
	}

	function decorateHead(idset) {
		var d = drawer();
		if (!d) return;
		var head = d.querySelector(':scope > .head.sidemenu');
		if (!head) return;
		var info = fieldInfo(idset);
		var dh = head.querySelector('.flxb-dh');
		if (!dh) {
			dh = el('div', 'flxb-dh', '<span class="flxb-dh__icon"><i class="bi"></i></span><span class="flxb-dh__txt"><span class="flxb-dh__kicker"></span><span class="flxb-dh__title"></span></span>');
			head.insertBefore(dh, head.firstChild);
			var done = head.querySelector('.close.text-success');
			if (done && !done.querySelector('.flxb-done')) { var s = el('span', 'flxb-done'); s.textContent = L.done || 'Done'; done.appendChild(s); }
			var x = head.querySelector('.close.text-danger');
			if (x) x.setAttribute('aria-label', L.close || 'Close');
		}
		dh.querySelector('i').className = 'bi ' + info.icon;
		dh.querySelector('.flxb-dh__kicker').textContent = info.kicker || '';
		dh.querySelector('.flxb-dh__title').textContent = info.title || '';
	}

	function selectField(idset) {
		clearSelected();
		if (!idset || idset === 'formSet') return;
		var n = document.querySelector('#dropZoneEFB [data-id="' + (window.CSS && CSS.escape ? CSS.escape(idset) : idset) + '"]');
		var zone = document.getElementById('dropZoneEFB');
		if (!n || !zone) return;
		while (n.parentElement && n.parentElement !== zone) n = n.parentElement;
		if (n.parentElement === zone) n.classList.add('flxb-selected');
	}

	/* Form settings: section headings + sticky chips that jump to them. */
	var SECTIONS = [
		{ key: 'secGeneral', find: function (c) { return c.querySelector('label[for="formNameEl"]'); } },
		{ key: 'secNotify', find: function (c) { return c.querySelector('label[for="adminFormEmailEl"]'); } },
		{ key: 'secSms', find: function (c) { var s = c.querySelector('#collapseSMS'); return s && s.previousElementSibling; } },
		{ key: 'secLogic', find: function (c) { return c.querySelector('#efb-conlog-btn-wrap'); } },
		{ key: 'secAdvanced', find: function (c) { var s = c.querySelector('#collapseAdvanced'); return s && s.previousElementSibling; } }
	];
	function decorateFormSettings() {
		var d = drawer();
		var c = document.getElementById('childsSideMenuConEfb');
		if (!d || !c) return;
		var old = d.querySelector('.flxb-secnav');
		if (window.efbOpenSettingIdEfb !== 'formSet') { if (old) old.remove(); return; }
		if (c.querySelector('.flxb-sec')) return;
		var nav = old || el('div', 'flxb-secnav');
		nav.textContent = '';
		var made = [];
		SECTIONS.forEach(function (s, i) {
			var anchor = s.find(c);
			if (!anchor || !anchor.parentNode) return;
			var h = el('h3', 'flxb-sec');
			h.id = 'flxb-sec-' + i;
			h.textContent = L[s.key] || s.key;
			anchor.parentNode.insertBefore(h, anchor);
			var b = el('button');
			b.type = 'button';
			b.textContent = h.textContent;
			b.addEventListener('click', function () {
				var top = h.getBoundingClientRect().top - d.getBoundingClientRect().top + d.scrollTop - 120;
				d.scrollTo({ top: top, behavior: 'smooth' });
			});
			nav.appendChild(b);
			made.push({ h: h, b: b });
		});
		if (!made.length) return;
		if (!old) { var head = d.querySelector(':scope > .head.sidemenu'); head.parentNode.insertBefore(nav, head.nextSibling); }
		var spy = function () {
			var cur = made[0];
			made.forEach(function (m) { var r = m.h.getBoundingClientRect(); if (r.height > 0 && r.top - d.getBoundingClientRect().top < 150) cur = m; });
			made.forEach(function (m) { m.b.classList.toggle('is-active', m === cur); });
		};
		d.removeEventListener('scroll', d._flxbSpy || function () {});
		d._flxbSpy = spy;
		d.addEventListener('scroll', spy, { passive: true });
		spy();
	}

	/* ---------- Settings: each h5 section becomes a card; licence/plan leftovers hidden ---------- */
	function decorateSettings() {
		var root = document.getElementById('nav-tabContent');
		if (!root || root._flxb) return;
		root._flxb = true;
		body.classList.add('flxb-settings');
		var parents = [];
		root.querySelectorAll('h5.card-title').forEach(function (h) { if (parents.indexOf(h.parentNode) < 0) parents.push(h.parentNode); });
		parents.forEach(function (par) {
			var kids = Array.prototype.slice.call(par.children), card = null;
			kids.forEach(function (k) {
				if (k.matches('h5.card-title')) {
					card = el('section', 'flxb-scard');
					par.insertBefore(card, k);
					var head = el('div', 'flxb-scard__head');
					var ic = k.querySelector('i');
					var icon = el('span', 'flxb-scard__icon');
					if (ic) { ic.className = ic.className.replace(/\bm[xy]?-\d\b/g, ''); icon.appendChild(ic); }
					head.appendChild(icon);
					var txt = el('div', 'flxb-scard__txt');
					k.classList.add('flxb-scard__title');
					txt.appendChild(k);
					head.appendChild(txt);
					card.appendChild(head);
					card._txt = txt;
					return;
				}
				if (!card || k.matches('.clearfix') || k.tagName === 'SCRIPT') return;
				/* The paragraph right after the title is its description. */
				if (card.children.length === 1 && k.tagName === 'P' && !k.querySelector('input,button,select')) { k.classList.add('flxb-scard__desc'); card._txt.appendChild(k); return; }
				card.appendChild(k);
			});
		});
		root.querySelectorAll('.flxb-scard').forEach(function (c) {
			if (c.querySelector('#activeCode_emsFormBuilder, #changePlanBtn, #efbCurrentPlanBadge')) c.classList.add('flxb-hide');
			if (c.children.length === 1) c.classList.add('flxb-scard--solo');
			var t = c.querySelector('.flxb-scard__title');
			if (t && (!t.textContent.trim() || t.classList.contains('d-none'))) c.classList.add('flxb-hide');
		});
		/* Translation-discount promo (upstream marketing) */
		root.querySelectorAll('#nav-text [style*="gradient"]').forEach(function (n) { if (n.querySelector('.bi-translate')) n.classList.add('flxb-hide'); });
	}

	function afterShow(idset) {
		body.classList.toggle('flxb-form-drawer', idset === 'formSet');
		decorateHead(idset);
		selectField(idset);
		syncOpen();
		decorateFormSettings();
	}

	function hook() {
		if (typeof window.show_setting_window_efb !== 'function' || window.show_setting_window_efb._flxb) return false;
		var orig = window.show_setting_window_efb;
		var wrapped = function (idset) {
			var r = orig.apply(this, arguments);
			try { afterShow(window.efbOpenSettingIdEfb || idset); } catch (e) { /* skin only */ }
			setTimeout(function () { try { if (isOpen(drawer())) afterShow(window.efbOpenSettingIdEfb || idset); else syncOpen(); } catch (e) {} }, 350);
			return r;
		};
		wrapped._flxb = true;
		window.show_setting_window_efb = wrapped;
		return true;
	}

	/* Click anywhere on a field in the canvas opens its settings (EFB needs the gear). */
	document.addEventListener('click', function (e) {
		var zone = document.getElementById('dropZoneEFB');
		if (!zone || !zone.contains(e.target)) return;
		if (e.target.closest('.btn-edit-holder, a, button, input, select, textarea, label.btn, .iti, .leaflet-container')) return;
		var n = e.target;
		while (n && n.parentElement !== zone) n = n.parentElement;
		if (!n || n.id === 'button_group_efb' || n.id === 'recaptcha_efb') return;
		var gear = n.querySelector('#settingElEFb, .btn-edit[onclick*="show_setting_window_efb"]');
		if (!gear) return;
		var m = (gear.getAttribute('onclick') || '').match(/show_setting_window_efb\('([^']+)'\)/);
		if (!m || (window.efbOpenSettingIdEfb === m[1] && isOpen(drawer()))) return;
		window.show_setting_window_efb(m[1]);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && isOpen(drawer()) && !document.querySelector('.modal.show') && typeof window.sideMenuEfb === 'function') window.sideMenuEfb(0);
	});

	function boot() {
		setupPalette();
		decorateSettings();
		hook();
		var d = drawer();
		if (d && !d._flxbObs) {
			d._flxbObs = new MutationObserver(syncOpen);
			d._flxbObs.observe(d, { attributes: true, attributeFilter: ['class'] });
		}
		var c = document.getElementById('childsSideMenuConEfb');
		if (c && !c._flxbObs) {
			c._flxbObs = new MutationObserver(function () { if (window.efbOpenSettingIdEfb === 'formSet') decorateFormSettings(); });
			c._flxbObs.observe(c, { childList: true });
		}
	}
	/* The builder is built on demand (create / edit), so keep watching for its parts. */
	var root = document.getElementById('wpbody-content') || body;
	new MutationObserver(function () { boot(); }).observe(root, { childList: true, subtree: true });
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
