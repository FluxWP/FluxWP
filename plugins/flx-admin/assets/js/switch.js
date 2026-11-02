/* Flux Admin · uygulama geçişi (WhatsApp / Görevler tam ekran arayüzlerinde). Alt+1..9 kısayolları. */
/* global FLXA_SWITCH */
(function () {
	'use strict';
	var B = window.FLXA_SWITCH;
	if (!B || !B.apps || B.apps.length < 2) return;
	var d = document;
	var wrap = d.createElement('div');
	wrap.className = 'flxa-sw';
	var tab = d.createElement('button');
	tab.type = 'button';
	tab.className = 'flxa-sw-tab';
	tab.setAttribute('aria-label', B.label);
	tab.setAttribute('aria-expanded', 'false');
	tab.title = B.label;
	tab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
	var menu = d.createElement('nav');
	menu.className = 'flxa-sw-menu';
	menu.hidden = true;
	menu.setAttribute('aria-label', B.label);
	B.apps.forEach(function (a, i) {
		var l = d.createElement('a');
		l.href = a.url;
		if (a.active) { l.className = 'is-current'; l.setAttribute('aria-current', 'page'); }
		l.innerHTML = a.icon;
		var s = d.createElement('span');
		s.textContent = a.label;
		l.appendChild(s);
		if (i < 9) { var k = d.createElement('kbd'); k.textContent = 'Alt ' + (i + 1); l.appendChild(k); }
		menu.appendChild(l);
	});
	function set(open) { menu.hidden = !open; tab.setAttribute('aria-expanded', open ? 'true' : 'false'); wrap.classList.toggle('is-open', open); }
	tab.addEventListener('click', function (e) { e.stopPropagation(); set(menu.hidden); if (!menu.hidden) menu.querySelector('a').focus(); });
	d.addEventListener('click', function (e) { if (!wrap.contains(e.target)) set(false); });
	d.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') set(false);
		if (e.altKey && !e.ctrlKey && !e.metaKey && /^[1-9]$/.test(e.key)) {
			var a = B.apps[+e.key - 1];
			if (a && !a.active) { e.preventDefault(); location.href = a.url; }
		}
	});
	wrap.appendChild(tab);
	wrap.appendChild(menu);
	d.body.appendChild(wrap);
})();
