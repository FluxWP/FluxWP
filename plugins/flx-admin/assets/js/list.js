/* Flux Admin ortak liste: form[data-flxa-list] filtre çubuğu + sayfa başı seçici. Sözleşme: docs/flx-admin/LISTE-API.md */
(function () {
	'use strict';
	var T = (window.FLXA_LIST && window.FLXA_LIST.i18n) || { filters: 'Filtreler', clear: 'Temizle', search: 'Ara' };

	function isField(el) {
		return el.matches('input, select, textarea') ? el : el.querySelector('input:not([type=hidden]), select, textarea');
	}
	function hasValue(item) {
		var n = 0;
		if (item.matches('select, textarea, input:not([type=hidden]):not([type=checkbox]):not([type=radio])')) { return item.value !== '' ? 1 : 0; }
		item.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea').forEach(function (f) {
			if (f.value !== '') { n = 1; }
		});
		item.querySelectorAll('input[type=checkbox]:checked, input[type=radio]:checked').forEach(function (f) {
			if (f.value !== '') { n = 1; }
		});
		return n;
	}
	function store(key, val) {
		try {
			if (val === undefined) { return sessionStorage.getItem('flxa-list-' + key); }
			sessionStorage.setItem('flxa-list-' + key, val);
		} catch (e) { return null; }
	}

	function enhance(form) {
		if (form.classList.contains('flxa-list')) { return; }
		var key = form.getAttribute('data-flxa-list') || location.search.replace(/.*[?&]page=([^&]+).*/, '$1') || 'list';
		var primaryN = parseInt(form.getAttribute('data-flxa-primary') || '2', 10);
		var auto = form.hasAttribute('data-flxa-autosubmit');
		var kids = Array.prototype.slice.call(form.children);
		var search = null, items = [], buttons = [];

		kids.forEach(function (el) {
			if (el.matches('input[type=hidden], script, noscript')) { return; }
			if (el.matches('button, input[type=submit]') || (el.matches('.button') && !isField(el))) { buttons.push(el); return; }
			var f = isField(el);
			if (!f) { return; }
			if (!search && f.matches('input[type=search]') && !el.hasAttribute('data-flxa-more')) { search = el; return; }
			items.push(el);
		});

		var marked = items.filter(function (el) { return el.hasAttribute('data-flxa-primary'); });
		var primary = marked.length ? marked : items.filter(function (el) { return !el.hasAttribute('data-flxa-more'); }).slice(0, primaryN);
		var more = items.filter(function (el) { return primary.indexOf(el) < 0; });

		form.classList.add('flxa-list');
		var top = document.createElement('div');
		top.className = 'flxa-list__top';
		var row = document.createElement('div');
		row.className = 'flxa-list__row';
		var panel = document.createElement('div');
		panel.className = 'flxa-list__more';
		panel.id = 'flxa-list-more-' + key;

		if (search) {
			search.classList.add('flxa-list__search');
			top.appendChild(search);
			var sf = isField(search);
			if (!sf.getAttribute('aria-label') && sf.placeholder) { sf.setAttribute('aria-label', sf.placeholder); }
		}
		primary.forEach(function (el) { row.appendChild(el); });
		more.forEach(function (el) { panel.appendChild(el); });

		var activeMore = more.filter(hasValue).length;
		var activeAll = (search && hasValue(search) ? 1 : 0) + primary.filter(hasValue).length + activeMore;

		if (more.length) {
			var tg = document.createElement('button');
			tg.type = 'button';
			tg.className = 'button flxa-list__toggle';
			tg.setAttribute('aria-controls', panel.id);
			tg.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span class="flxa-list__tl"></span>';
			tg.querySelector('.flxa-list__tl').textContent = T.filters;
			if (activeMore) {
				var b = document.createElement('b');
				b.className = 'flxa-list__n';
				b.textContent = activeMore;
				tg.appendChild(b);
			}
			var open = store(key) === '1';
			var setOpen = function (o) {
				panel.hidden = !o;
				tg.setAttribute('aria-expanded', o ? 'true' : 'false');
				tg.classList.toggle('is-open', o);
			};
			setOpen(open);
			tg.addEventListener('click', function () {
				var o = panel.hidden;
				setOpen(o);
				store(key, o ? '1' : '0');
			});
			top.appendChild(tg);
		}

		/* Gönder düğmeleri: otomatik gönderimde gizlenir (Enter / değişiklik yeter), değilse panelin sonunda. */
		buttons.forEach(function (bt) {
			if (auto) { bt.classList.add('flxa-list__hidden'); form.appendChild(bt); } else { (more.length ? panel : row).appendChild(bt); }
		});

		if (activeAll) {
			var clr = document.createElement('a');
			clr.className = 'flxa-list__clear';
			clr.textContent = T.clear;
			var u = new URL(form.getAttribute('action') || location.href, location.href);
			u.search = '';
			form.querySelectorAll('input[type=hidden]').forEach(function (h) { if (h.name && h.value !== '') { u.searchParams.set(h.name, h.value); } });
			clr.href = u.toString();
			(row.children.length ? row : top).appendChild(clr);
		}

		form.insertBefore(top, form.firstChild);
		if (row.children.length) { top.after(row); }
		if (more.length) { (row.children.length ? row : top).after(panel); }

		if (auto) {
			form.addEventListener('change', function (e) {
				if (e.target.matches('select, input[type=date], input[type=month], input[type=checkbox], input[type=radio]')) { submit(form); }
			});
		}
		form.addEventListener('submit', function () { clean(form); });
	}

	/* Boş alanları URL'ye koyma, sayfayı başa al. */
	function clean(form) {
		form.querySelectorAll('input, select').forEach(function (f) {
			if (f.name && f.value === '' && f.type !== 'hidden') { f.disabled = true; }
			if (f.name === 'paged') { f.disabled = true; }
		});
		setTimeout(function () { form.querySelectorAll('[disabled]').forEach(function (f) { f.disabled = false; }); }, 0);
	}
	function submit(form) {
		clean(form);
		if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
	}

	function init() {
		document.querySelectorAll('form[data-flxa-list]').forEach(enhance);
		document.querySelectorAll('select[data-flxa-perpage]').forEach(function (s) {
			if (s.dataset.flxaBound) { return; }
			s.dataset.flxaBound = '1';
			s.addEventListener('change', function () { s.form.submit(); });
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	window.flxaListInit = init;
}());
