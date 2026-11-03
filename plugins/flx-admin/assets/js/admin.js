/* Flux Admin · panel davranışları: tema, yan panel, alt menü akordeonu, komut paleti, canlı rozetler, PWA. */
/* global FLXA, jQuery */
(function () {
	'use strict';
	/* iOS Safari user-scalable=no'yu yok sayar: panelde iki parmakla yakınlaştırmayı engelle. */
	document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });
	if (!window.FLXA) return;
	var B = window.FLXA;
	var T = B.i18n || {};
	var d = document;
	var html = d.documentElement;

	function $(s, r) { return (r || d).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
	function el(tag, attrs, kids) {
		var n = d.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'html') n.innerHTML = attrs[k];
			else if (k === 'text') n.textContent = attrs[k];
			else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), attrs[k]);
			else n.setAttribute(k, attrs[k]);
		});
		(kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? d.createTextNode(c) : c); });
		return n;
	}
	function post(action, data) {
		var f = new FormData();
		f.append('action', action);
		f.append('nonce', B.nonce);
		Object.keys(data || {}).forEach(function (k) { f.append(k, data[k]); });
		return fetch(B.ajax, { method: 'POST', body: f, credentials: 'same-origin' });
	}
	var toastTimer;
	function toast(msg) {
		var t = $('#flxa-toast');
		if (!t) return;
		t.textContent = msg;
		t.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { t.hidden = true; }, 2600);
	}

	/* ───────── Tema: otomatik → açık → koyu ───────── */
	var modes = ['auto', 'light', 'dark'];
	var mode = B.mode || 'auto';
	function modeIcon() { return mode === 'dark' ? B.icons.moon : (mode === 'light' ? B.icons.sun : B.icons.auto); }
	function modeLabel() { return mode === 'dark' ? T.modeDark : (mode === 'light' ? T.modeLight : T.modeAuto); }
	function paintModeButtons() {
		$$('[data-flxa-mode], #wp-admin-bar-flxa-mode > .ab-item').forEach(function (b) {
			var sr = b.querySelector('.screen-reader-text');
			var svg = b.querySelector('svg');
			if (svg) svg.outerHTML = modeIcon();
			b.setAttribute('title', modeLabel());
			if (sr) sr.textContent = modeLabel();
		});
	}
	function setMode(m) {
		mode = m;
		if (window.FLXA_APPLY_MODE) window.FLXA_APPLY_MODE(m);
		d.body.classList.remove('flxa-mode-auto', 'flxa-mode-light', 'flxa-mode-dark');
		d.body.classList.add('flxa-mode-' + m);
		paintModeButtons();
		toast(modeLabel());
		post('flxa_mode', { mode: m });
	}
	function cycleMode(e) {
		if (e) e.preventDefault();
		setMode(modes[(modes.indexOf(mode) + 1) % modes.length]);
	}
	d.addEventListener('click', function (e) {
		var t = e.target.closest('[data-flxa-mode], #wp-admin-bar-flxa-mode > .ab-item');
		if (t) cycleMode(e);
	});

	/* ───────── Mobil yan panel ───────── */
	/* WordPress'in kendi düğmesi uygulama ekranlarında ve iOS dokunuşlarında güvenilmez: aç / kapat tamamen bizde. */
	var dWrap = d.getElementById('wpwrap');
	function drawerOpen() { return !!dWrap && dWrap.classList.contains('wp-responsive-open'); }
	function setDrawer(on) {
		if (!dWrap) return;
		dWrap.classList.toggle('wp-responsive-open', on);
		html.classList.toggle('flxa-drawer-on', on);
		var tg = $('#wp-admin-bar-menu-toggle .ab-item');
		if (tg) tg.setAttribute('aria-expanded', on ? 'true' : 'false');
		var mw = d.getElementById('adminmenuwrap');
		if (mw) mw.style.transform = '';
	}
	function toggleDrawer() { setDrawer(!drawerOpen()); }
	window.addEventListener('click', function (e) {
		if (window.innerWidth > 782) return;
		var t = e.target.closest && e.target.closest('[data-flxa-drawer], #wp-admin-bar-menu-toggle');
		if (t) { e.preventDefault(); e.stopImmediatePropagation(); toggleDrawer(); return; }
		if (e.target.id === 'flxa-scrim') { e.preventDefault(); e.stopImmediatePropagation(); setDrawer(false); }
	}, true);
	d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawerOpen()) setDrawer(false); });
	/* Sola kaydırarak kapat */
	(function () {
		var mw = d.getElementById('adminmenuwrap');
		if (!mw) return;
		var x0 = 0, y0 = 0, dx = 0, on = false, lock = '';
		mw.addEventListener('touchstart', function (e) {
			if (!drawerOpen() || e.touches.length !== 1) return;
			on = true; lock = ''; dx = 0; x0 = e.touches[0].clientX; y0 = e.touches[0].clientY;
		}, { passive: true });
		mw.addEventListener('touchmove', function (e) {
			if (!on) return;
			var ex = e.touches[0].clientX - x0, ey = e.touches[0].clientY - y0;
			if (!lock) { if (Math.abs(ex) < 8 && Math.abs(ey) < 8) return; lock = Math.abs(ex) > Math.abs(ey) ? 'x' : 'y'; }
			if (lock !== 'x') return;
			dx = Math.min(0, ex);
			mw.style.transition = 'none';
			mw.style.transform = 'translateX(' + dx + 'px)';
		}, { passive: true });
		mw.addEventListener('touchend', function () {
			if (!on) return;
			on = false;
			mw.style.transition = '';
			if (lock === 'x' && dx < -70) setDrawer(false);
			else mw.style.transform = '';
		});
	})();

	/* ───────── Alt menü akordeonu (geniş menü) ───────── */
	/* Açık sayfanın alt menüsü de kapatılabilir; kapalı bırakılanlar hatırlanır (flxa-subshut). */
	var subShut = store('flxa-subshut') || {};
	$$('#adminmenu > li.wp-has-submenu').forEach(function (li) {
		var cur = li.classList.contains('wp-has-current-submenu');
		var sub = li.querySelector('.wp-submenu');
		if (!sub || sub.querySelectorAll('li:not(.wp-submenu-head)').length < 2) return;
		var b = el('button', { type: 'button', 'class': 'flxa-subtoggle', 'aria-expanded': cur && !subShut[li.id] ? 'true' : 'false', 'aria-label': T.subToggle, html: B.icons.chevron });
		b.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var open;
			if (cur) {
				open = !li.classList.toggle('flxa-sub-shut');
				if (open) delete subShut[li.id]; else subShut[li.id] = 1;
				store('flxa-subshut', subShut);
			} else {
				open = li.classList.toggle('flxa-open');
			}
			b.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		li.classList.add('flxa-has-toggle');
		li.appendChild(b);
	});

	/* ───────── Mobil: menü öğesinin adına dokununca sayfaya git ─────────
	   WordPress telefonda alt menüsü olan öğede ilk dokunuşu yutup alt menüyü açar; alt menüyü burada yalnız
	   sağdaki ok (.flxa-subtoggle) açar, öğenin kendisi doğrudan sayfaya gider. */
	var amenu = d.getElementById('adminmenu');
	if (amenu) {
		amenu.addEventListener('click', function (e) {
			if (window.innerWidth > 782 || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
			var a = e.target.closest && e.target.closest('li.wp-has-submenu > a.menu-top');
			if (!a || !a.href) return;
			e.stopPropagation(); // WordPress'in "önce alt menüyü aç" işleyicisi çalışmasın
			if (window.FLXA_PROGRESS) window.FLXA_PROGRESS.start();
		}, true);
	}

	/* ───────── Geniş tablolar: sayfa değil yalnız tablo yatay kayar ─────────
	   İçeriğe sığmayan tablo .flxa-tscroll kutusuna alınır (WordPress'in kendi liste tabloları telefonda zaten
	   kart görünümüne geçer, onlara dokunulmaz). Sonradan çizilen tablolar için hafif bir gözlemci var. */
	(function () {
		var root = d.getElementById('wpbody-content');
		if (!root || d.body.classList.contains('flxa-appmode')) return;
		var t = 0;
		function scan() {
			t = 0;
			$$('table', root).forEach(function (tb) {
				if (tb.closest('.flxa-tscroll, .wp-list-table, .form-table, .mce-container, .CodeMirror, [contenteditable]') || tb.classList.contains('wp-list-table') || tb.classList.contains('form-table')) return;
				var p = tb.parentElement;
				if (!p || tb.offsetParent === null) return;
				var ps = getComputedStyle(p);
				if (/(auto|scroll)/.test(ps.overflowX)) return; // zaten kendi kutusunda kayıyor
				if (tb.scrollWidth <= p.clientWidth + 2) return;
				var w = el('div', { 'class': 'flxa-tscroll' });
				p.insertBefore(w, tb);
				w.appendChild(tb);
			});
		}
		function later() { if (!t) t = setTimeout(scan, 250); }
		scan();
		window.addEventListener('resize', later);
		if (window.MutationObserver) new MutationObserver(later).observe(root, { childList: true, subtree: true });
	})();

	/* ───────── Canlı rozetler (Heartbeat) ───────── */
	var counts = B.counts || {};
	function paintCounts() {
		$$('[data-flxa-count]').forEach(function (b) {
			var n = +counts[b.getAttribute('data-flxa-count')] || 0;
			b.textContent = n > 99 ? '99+' : String(n);
			b.hidden = !n;
		});
		$$('[data-flxa-count-text]').forEach(function (b) {
			var k = b.getAttribute('data-flxa-count-text');
			if (k in counts) b.textContent = (+counts[k] || 0).toLocaleString(html.lang || undefined);
		});
		/* Menüdeki WhatsApp / Görev rozetleri. */
		[['toplevel_page_flx-chat', 'whatsapp'], ['toplevel_page_flx-tasks', 'tasks']].forEach(function (p) {
			var li = d.getElementById(p[0]);
			if (!li || !(p[1] in counts)) return;
			var name = li.querySelector('.wp-menu-name');
			var badge = name && name.querySelector('.awaiting-mod');
			var n = +counts[p[1]] || 0;
			if (!n && badge) badge.remove();
			if (n && name) {
				if (!badge) { badge = el('span', { 'class': 'awaiting-mod' }, [el('span', { 'class': 'pending-count' })]); name.appendChild(d.createTextNode(' ')); name.appendChild(badge); }
				badge.className = 'awaiting-mod count-' + n;
				badge.firstChild.textContent = String(n);
			}
		});
		var total = (+counts.whatsapp || 0) + (+counts.tasks || 0);
		if ('setAppBadge' in navigator) {
			try { if (total) navigator.setAppBadge(total); else navigator.clearAppBadge(); } catch (e) { /* desteklenmiyor */ }
		}
	}
	if (window.jQuery) {
		jQuery(d).on('heartbeat-send.flxa', function (e, data) { data.flxa = 1; });
		jQuery(d).on('heartbeat-tick.flxa', function (e, data) {
			if (data && data.flxa && data.flxa.counts) { counts = data.flxa.counts; paintCounts(); }
		});
	}
	paintCounts();

	/* ───────── Başlangıç: modülleri düzenle ───────── */
	d.addEventListener('click', function (e) {
		var b = e.target.closest('[data-flxa-edit]');
		if (!b) return;
		var on = d.body.classList.toggle('flxa-editing');
		b.setAttribute('aria-expanded', on ? 'true' : 'false');
		var link = $('#show-settings-link');
		var open = link && link.getAttribute('aria-expanded') === 'true';
		if (link && open !== on) link.click();
	});

	/* ───────── Komut paleti ───────── */
	var pal, palInput, palList, palItems = [], palSel = 0, palTimer, palReq = 0;
	function indexMenu() {
		var out = [];
		$$('#adminmenu > li.menu-top').forEach(function (li) {
			var top = li.querySelector('a.menu-top');
			if (!top) return;
			var name = (li.querySelector('.wp-menu-name') || top).childNodes[0];
			var label = (name && name.textContent || top.textContent).trim();
			var lic = (li.className.match(/\byta-lic-([a-z0-9-]+)/) || [])[1];
			out.push({ g: T.pages, label: label, url: top.href, icon: lic ? '<span class="flxa-lic-' + lic + '"><span class="wp-menu-image flxa-pal-mi"></span></span>' : B.icons.grid });
			$$('.wp-submenu a', li).forEach(function (a) {
				var t = (a.childNodes[0] && a.childNodes[0].textContent || a.textContent).trim();
				if (t && t !== label) out.push({ g: T.pages, label: t, hint: label, url: a.href, icon: B.icons.chevron });
			});
		});
		return out;
	}
	function baseItems() {
		var out = [];
		(B.apps || []).forEach(function (a) { out.push({ g: T.apps, label: a.label, url: a.url, icon: a.icon }); });
		if (B.newPost) out.push({ g: T.actions, label: T.newPost, url: B.newPost, icon: B.icons.plus });
		if (B.newPage) out.push({ g: T.actions, label: T.newPage, url: B.newPage, icon: B.icons.plus });
		if (B.task) out.push({ g: T.actions, label: T.newTask, run: function () { taskModal(); }, icon: B.icons.check });
		out.push({ g: T.actions, label: T.modeAuto, run: function () { setMode('auto'); }, icon: B.icons.auto });
		out.push({ g: T.actions, label: T.modeLight, run: function () { setMode('light'); }, icon: B.icons.sun });
		out.push({ g: T.actions, label: T.modeDark, run: function () { setMode('dark'); }, icon: B.icons.moon });
		return out.concat(indexMenu());
	}
	function norm(s) { return (s || '').toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i'); }
	function renderPal(remote) {
		var q = norm(palInput.value.trim());
		var all = baseItems();
		var list = q ? all.filter(function (i) { return norm(i.label + ' ' + (i.hint || '')).indexOf(q) !== -1; }) : all.filter(function (i) { return i.g !== T.pages || !i.hint; });
		list = list.slice(0, 40).concat(remote || []);
		palItems = list;
		palSel = Math.min(palSel, Math.max(0, list.length - 1));
		palList.innerHTML = '';
		if (!list.length) { palList.appendChild(el('li', { 'class': 'flxa-pal-empty', text: T.noResults })); return; }
		var lastG = '';
		list.forEach(function (it, i) {
			if (it.g !== lastG) { palList.appendChild(el('li', { 'class': 'flxa-pal-h', role: 'presentation', text: it.g })); lastG = it.g; }
			var li = el('li', { 'class': 'flxa-pal-item', role: 'option', id: 'flxa-pal-' + i, 'aria-selected': i === palSel ? 'true' : 'false', html: it.icon || '' });
			li.appendChild(el('span', { text: it.label }));
			if (it.hint) li.appendChild(el('small', { text: it.hint }));
			li.addEventListener('mousemove', function () { if (palSel !== i) { palSel = i; mark(); } });
			li.addEventListener('click', function () { go(i); });
			palList.appendChild(li);
		});
		palInput.setAttribute('aria-activedescendant', 'flxa-pal-' + palSel);
	}
	function mark() {
		$$('.flxa-pal-item', palList).forEach(function (n) { n.setAttribute('aria-selected', n.id === 'flxa-pal-' + palSel ? 'true' : 'false'); });
		var cur = d.getElementById('flxa-pal-' + palSel);
		if (cur) { cur.scrollIntoView({ block: 'nearest' }); palInput.setAttribute('aria-activedescendant', cur.id); }
	}
	function go(i) {
		var it = palItems[i];
		if (!it) return;
		closePal();
		if (it.run) it.run();
		else if (it.url) location.href = it.url;
	}
	function searchContent() {
		var q = palInput.value.trim();
		clearTimeout(palTimer);
		if (!B.canSearch || q.length < 3) { renderPal(); return; }
		renderPal();
		palTimer = setTimeout(function () {
			var id = ++palReq;
			fetch(B.rest + 'wp/v2/search?per_page=6&search=' + encodeURIComponent(q), { headers: { 'X-WP-Nonce': B.rnonce }, credentials: 'same-origin' })
				.then(function (r) { return r.ok ? r.json() : []; })
				.then(function (rows) {
					if (id !== palReq || !pal || pal.hidden) return;
					renderPal((rows || []).map(function (r) {
						var edit = B.ajax.replace('admin-ajax.php', 'post.php?action=edit&post=' + r.id);
						return { g: T.content, label: r.title || ('#' + r.id), hint: r.subtype, url: r.type === 'post' ? edit : r.url, icon: B.icons.file };
					}));
				}).catch(function () {});
		}, 250);
	}
	function buildPal() {
		pal = el('div', { 'class': 'flxa-pal', hidden: '', role: 'dialog', 'aria-modal': 'true', 'aria-label': T.search });
		var box = el('div', { 'class': 'flxa-pal-box' });
		palInput = el('input', { type: 'search', placeholder: T.search, 'aria-label': T.search, role: 'combobox', 'aria-expanded': 'true', 'aria-controls': 'flxa-pal-list', autocomplete: 'off', spellcheck: 'false' });
		palList = el('ul', { 'class': 'flxa-pal-list', id: 'flxa-pal-list', role: 'listbox' });
		box.appendChild(el('div', { 'class': 'flxa-pal-in', html: B.icons.search }, [palInput, el('button', { type: 'button', 'class': 'flxa-pal-x', 'aria-label': T.close, title: T.close, html: B.icons.x, onclick: function () { closePal(); } })]));
		box.appendChild(palList);
		box.appendChild(el('div', { 'class': 'flxa-pal-foot', text: T.hint }));
		pal.appendChild(box);
		pal.addEventListener('click', function (e) { if (e.target === pal) closePal(); });
		var palDeb = 0;
		palInput.addEventListener('input', function () {
			clearTimeout(palDeb);
			palDeb = setTimeout(function () { palDeb = 0; palSel = 0; searchContent(); }, 180);
		});
		palInput.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); palSel = Math.min(palItems.length - 1, palSel + 1); mark(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); palSel = Math.max(0, palSel - 1); mark(); }
			else if (e.key === 'Enter') { e.preventDefault(); if (palDeb) { clearTimeout(palDeb); palDeb = 0; palSel = 0; renderPal(); } go(palSel); }
			else if (e.key === 'Escape') { e.preventDefault(); closePal(); }
		});
		d.body.appendChild(pal);
	}
	var lastFocus;
	function openPal(e) {
		if (e) e.preventDefault();
		if (!B.palette) return;
		if (!pal) buildPal();
		lastFocus = d.activeElement;
		pal.hidden = false;
		palInput.value = '';
		palSel = 0;
		renderPal();
		setTimeout(function () { palInput.focus(); }, 10);
	}
	function closePal() {
		if (!pal) return;
		pal.hidden = true;
		if (lastFocus && lastFocus.focus) lastFocus.focus();
	}
	d.addEventListener('click', function (e) {
		if (e.target.closest('[data-flxa-palette], #wp-admin-bar-flxa-search > .ab-item')) openPal(e);
	});
	d.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && (e.key === 'k' || e.key === 'K')) {
			if (pal && !pal.hidden) closePal(); else openPal(e);
		}
	});

	/* ───────── Bağlantı durumu ───────── */
	window.addEventListener('offline', function () { toast(T.offline); });
	window.addEventListener('online', function () { toast(T.online); });

	/* ───────── PWA ───────── */
	if (B.pwa && 'serviceWorker' in navigator && window.isSecureContext) {
		window.addEventListener('load', function () {
			navigator.serviceWorker.register(B.sw, { scope: B.scope, updateViaCache: 'all' }).catch(function () {});
		});
	}
	var deferred = null;
	var card = $('.flxa-install');
	var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
	var dismissed = false;
	try { dismissed = localStorage.getItem('flxa-install-closed') === '1'; } catch (e) { /* özel pencere */ }
	function showCard() { if (card && !standalone && !dismissed) card.hidden = false; }
	window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; showCard(); });
	window.addEventListener('appinstalled', function () { if (card) card.hidden = true; toast(T.installed); });
	if (B.pwa && card && /iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone) {
		var hint = card.querySelector('[data-flxa-install-hint]');
		if (hint) hint.textContent = T.installIOS;
		var btn = card.querySelector('[data-flxa-install]');
		if (btn) btn.hidden = true;
		showCard();
	}
	d.addEventListener('click', function (e) {
		if (e.target.closest('[data-flxa-install]') && deferred) {
			deferred.prompt();
			deferred.userChoice.finally(function () { deferred = null; if (card) card.hidden = true; });
		}
		if (e.target.closest('[data-flxa-install-close]') && card) {
			card.hidden = true;
			try { localStorage.setItem('flxa-install-closed', '1'); } catch (er) { /* yok */ }
		}
	});
	if (standalone) {
		html.classList.add('flxa-standalone');
		// Sunucu kalıcı oturumu yalnız uygulama (PWA) girişlerinde verir; tarayıcıdaki oturumlar normal kalır.
		try { d.cookie = 'flxa_app=1; path=/; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : ''); } catch (e) {}
	}
	/* ───────── v1.1 · yardımcılar ───────── */
	function store(k, v) {
		try {
			if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null');
			if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v));
		} catch (e) { /* özel pencere */ }
		return null;
	}
	var M = B.menu || { groups: {}, ids: {}, pins: [], away: [], home: {} };
	var menuEl = d.getElementById('adminmenu');
	var wide = window.matchMedia('(min-width: 783px)');
	function slugOf(li) { return li && li.id ? M.ids[li.id] : null; }
	function liOf(slug) {
		var id = Object.keys(M.ids).filter(function (k) { return M.ids[k] === slug; })[0];
		return id ? d.getElementById(id) : null;
	}
	function isFolded() { return d.body.classList.contains('folded') || (d.body.classList.contains('auto-fold') && wide.matches && window.innerWidth <= 960); }

	/* ───────── Daralt / genişlet ───────── */
	d.addEventListener('click', function (e) {
		if (!e.target.closest('[data-flxa-fold]')) return;
		e.preventDefault();
		if (d.body.classList.contains('flxa-appmode')) {
			// WhatsApp / Görevler: panelin genel daraltma ayarına dokunmadan yalnız bu ekranlar için hatırlanır.
			var open = d.body.classList.toggle('folded') === false;
			html.classList.toggle('flxa-app-open', open);
			try { localStorage.setItem('flxa-app-open', open ? '1' : '0'); } catch (x) {}
			paintGroups();
			window.dispatchEvent(new Event('resize'));
			return;
		}
		var b = d.getElementById('collapse-button');
		if (b) b.click(); else d.body.classList.toggle('folded');
		paintGroups();
	});

	/* "[" tuşu: menüyü daralt / genişlet (yazı alanında değilken). */
	d.addEventListener('keydown', function (e) {
		if (e.key !== '[' || e.ctrlKey || e.metaKey || e.altKey || window.innerWidth <= 782) return;
		var t = e.target;
		if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || (t.closest && t.closest('[contenteditable], .block-editor, .editor-styles-wrapper')))) return;
		var b = $('[data-flxa-fold]');
		if (b) { e.preventDefault(); b.click(); }
	});

	/* ───────── Genişlik: kenarı sürükle ───────── */
	var wrap = d.getElementById('adminmenuwrap');
	if (wrap && !d.body.classList.contains('flxa-appmode')) {
		var rz = el('div', { 'class': 'flxa-resizer', role: 'separator', 'aria-orientation': 'vertical', title: T.resize, 'aria-label': T.resize });
		wrap.appendChild(rz);
		var rzOn = false;
		rz.addEventListener('pointerdown', function (e) {
			if (isFolded()) return;
			e.preventDefault();
			rzOn = true;
			rz.setPointerCapture(e.pointerId);
			html.classList.add('flxa-resizing');
		});
		rz.addEventListener('pointermove', function (e) {
			if (!rzOn) return;
			var w = Math.round(e.clientX);
			if (w < 150) return;
			html.style.setProperty('--flxa-side-w', Math.max(200, Math.min(420, w)) + 'px');
		});
		rz.addEventListener('pointerup', function (e) {
			if (!rzOn) return;
			rzOn = false;
			html.classList.remove('flxa-resizing');
			if (e.clientX < 150) {
				var b = d.getElementById('collapse-button');
				if (b) b.click();
				return;
			}
			var w = parseInt(getComputedStyle(html).getPropertyValue('--flxa-side-w'), 10);
			if (w) store('flxa-side-w', w);
		});
		rz.addEventListener('dblclick', function () {
			html.style.removeProperty('--flxa-side-w');
			store('flxa-side-w', null);
		});
	}

	/* ───────── Grup başlıkları: aç / kapat ───────── */
	var gc = store('flxa-gc') || {};
	function groupItems(sep) {
		var out = [];
		var n = sep.nextElementSibling;
		while (n && !n.classList.contains('flxa-group') && !n.classList.contains('flxa-side-foot') && !n.classList.contains('flxa-side-edit') && n.id !== 'collapse-menu') {
			if (n.classList.contains('menu-top')) out.push(n);
			n = n.nextElementSibling;
		}
		return out;
	}
	function isCur(li) { return li.classList.contains('current') || li.classList.contains('wp-has-current-submenu'); }
	function groupClosed(sep) {
		var g = M.groups[sep.id];
		if (!g) return false;
		var key = g.key;
		// Daraltılmış menüde her grup tek simgeye iner (sabitlenenler hariç); üstüne gelince öğeleri yanda açılır.
		if (isFolded() && window.innerWidth > 782) return key !== 'pinned';
		// Açık sayfanın grubu açık gelir; kişi onu açık sayfadayken kapattıysa (2) kapalı kalır.
		if (groupItems(sep).some(isCur) && gc[key] !== 2) return false;
		return key in gc ? !!gc[key] : !!g.collapsed;
	}
	function paintGroups() {
		if (!menuEl) return;
		$$('li.flxa-group', menuEl).forEach(function (sep) {
			var items = groupItems(sep);
			var closed = groupClosed(sep);
			sep.classList.toggle('is-closed', closed);
			sep.classList.toggle('has-current', items.some(isCur));
			sep.setAttribute('aria-expanded', closed ? 'false' : 'true');
			var c = sep.querySelector('.flxa-gcount');
			if (c) c.textContent = closed ? String(items.length) : '';
			var last = null;
			items.forEach(function (li) {
				li.classList.toggle('flxa-gc-hide', closed);
				li.classList.remove('flxa-glast');
				if (!closed && getComputedStyle(li).display !== 'none') last = li;
			});
			if (last) last.classList.add('flxa-glast');
			var pinned = M.groups[sep.id] && M.groups[sep.id].key === 'pinned';
			sep.classList.toggle('flxa-gpins', !!pinned);
			if (pinned) paintPins(items);
		});
	}
	/* Sabitlenenler kutucuk olur; açık sayfanın alt menüsü varsa o kutucuk tam genişlikte en sona geçer. */
	function paintPins(items) {
		var wideOne = null;
		items.forEach(function (li) {
			li.classList.add('flxa-pin');
			var w = !!$('.wp-submenu li', li) && li.classList.contains('wp-has-current-submenu');
			li.classList.toggle('flxa-pin-wide', w);
			if (w) wideOne = li;
		});
		if (wideOne && items.length > 1 && items[items.length - 1] !== wideOne) placeAfter(wideOne, items[items.length - 1]);
	}
	if (menuEl) {
		$$('li.flxa-group', menuEl).forEach(function (sep) {
			var g = M.groups[sep.id];
			if (!g || !g.label) return;
			if (sep.querySelector('.flxa-gchev')) { sep.setAttribute('role', 'button'); sep.setAttribute('tabindex', '0'); sep.setAttribute('title', T.groupToggle); return; }
			sep.classList.add('flxa-gh');
			sep.setAttribute('role', 'button');
			sep.setAttribute('tabindex', '0');
			sep.setAttribute('title', T.groupToggle);
			sep.appendChild(el('span', { 'class': 'flxa-gcount' }));
			sep.appendChild(el('span', { 'class': 'flxa-gchev', html: B.icons.chevron }));
			sep.appendChild(el('span', { 'class': 'flxa-gmore', html: g.icon || (B.gicons && B.gicons[g.key]) || B.icons.more }));
		});
		menuEl.addEventListener('click', function (e) {
			var sep = e.target.closest('li.flxa-gh');
			if (!sep) return;
			if (isFolded()) { if (sep.classList.contains('is-closed')) gflyShow(sep); return; }
			var g = M.groups[sep.id];
			gc[g.key] = sep.classList.contains('is-closed') ? 0 : (groupItems(sep).some(isCur) ? 2 : 1);
			store('flxa-gc', gc);
			paintGroups();
		});
		menuEl.addEventListener('keydown', function (e) {
			if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('li.flxa-gh')) { e.preventDefault(); e.target.click(); }
		});
		paintGroups();
		wide.addEventListener && wide.addEventListener('change', paintGroups);
		// Açık sayfanın menü öğesi görünmüyorsa (ör. Sistem grubunun içinde) menüyü ona kaydır.
		(function () {
			var aw = d.getElementById('adminmenuwrap'), cur = $('#adminmenu > li.current, #adminmenu > li.wp-has-current-submenu');
			if (!aw || !cur || isFolded() || window.innerWidth <= 782) return;
			var wr = aw.getBoundingClientRect(), r = cur.getBoundingClientRect();
			if (r.top < wr.top + 160 || r.bottom > wr.bottom - 120) aw.scrollTop += r.top - wr.top - Math.max(160, (wr.height - r.height) / 2);
		})();
		var autoFold = window.matchMedia('(max-width: 960px)');
		autoFold.addEventListener && autoFold.addEventListener('change', paintGroups);
	}

	/* ───────── Daraltılmış menü: kapalı grup "•••" ve sağda açılan liste ───────── */
	var gfly = null, gflyT = 0, gflySep = null;
	function gflyHide() {
		clearTimeout(gflyT);
		if (gfly) gfly.classList.remove('is-on');
		if (gflySep) gflySep.classList.remove('is-open');
		gflySep = null;
	}
	function gflyHovered() {
		try { return !!((gfly && gfly.matches(':hover')) || (gflySep && gflySep.matches(':hover'))); } catch (x) { return false; }
	}
	// Fare listenin ya da grup simgesinin üstündeyse kapanmaz (geçişte arada bir an boşluk olsa da).
	function gflyLater() {
		clearTimeout(gflyT);
		gflyT = setTimeout(function check() {
			if (gflyHovered()) { gflyT = setTimeout(check, 250); return; }
			gflyHide();
		}, 350);
	}
	function gflyShow(sep) {
		clearTimeout(gflyT);
		if (gflySep === sep && gfly && gfly.classList.contains('is-on')) return;
		gflyHide();
		if (!gfly) {
			gfly = el('div', { id: 'flxa-gfly', role: 'menu' });
			gfly.addEventListener('mouseenter', function () { clearTimeout(gflyT); });
			gfly.addEventListener('mouseleave', gflyLater);
			d.body.appendChild(gfly);
		}
		var g = M.groups[sep.id] || {};
		gfly.innerHTML = '';
		if (g.label) gfly.appendChild(el('div', { 'class': 'flxa-gfly-head', text: g.label }));
		groupItems(sep).forEach(function (li) {
			var a = $('a.menu-top', li);
			if (!a) return;
			var row = el('a', { 'class': 'flxa-gfly-item', href: a.getAttribute('href') || '#', role: 'menuitem' });
			row.style.setProperty('--flxa-t', getComputedStyle(li).getPropertyValue('--flxa-t'));
			var img = $('.wp-menu-image', a);
			if (img) {
				var ic = img.cloneNode(true);
				ic.removeAttribute('id');
				ic.classList.add('flxa-gfly-ico');
				if (li.classList.contains('flxa-letter')) { ic.innerHTML = ''; ic.className = 'flxa-gfly-ico flxa-gfly-ch'; ic.textContent = li.getAttribute('data-flxa-ch') || getComputedStyle(img, '::before').content.replace(/^["']|["']$/g, '').replace(/^(none|normal)$/, ''); }
				else if (li.classList.contains('flxa-lic')) { ic.innerHTML = ''; ic.className = 'flxa-gfly-ico flxa-gfly-mask'; ic.style.setProperty('--flxa-i', getComputedStyle(img, '::before').getPropertyValue('--flxa-i')); }
				else if (li.classList.contains('flxa-logo')) { ic.innerHTML = ''; ic.className = 'flxa-gfly-ico flxa-gfly-logo'; ic.style.backgroundImage = getComputedStyle(img).backgroundImage; }
				else if (getComputedStyle(img).backgroundImage !== 'none') { ic.innerHTML = ''; ic.className = 'flxa-gfly-ico flxa-gfly-logo flxa-gfly-bg'; ic.style.backgroundImage = getComputedStyle(img).backgroundImage; }
				row.appendChild(ic);
			}
			row.appendChild(el('span', { 'class': 'flxa-gfly-name', text: tipText(a) }));
			gfly.appendChild(row);
		});
		gflySep = sep;
		sep.classList.add('is-open');
		var r = sep.getBoundingClientRect();
		var aw = d.getElementById('adminmenuwrap');
		gfly.style.left = Math.round((aw ? aw.getBoundingClientRect().right : r.right) + 8) + 'px';
		gfly.style.top = '0px';
		gfly.classList.add('is-on');
		var h = gfly.offsetHeight;
		var top = Math.round(Math.max(8, Math.min(r.top - 6, window.innerHeight - h - 8)));
		gfly.style.top = top + 'px';
		// Liste ile simgeyi bağlayan ok: simgenin ortasına hizalı.
		gfly.style.setProperty('--flxa-fly-y', Math.round(Math.max(14, Math.min(h - 14, r.top + r.height / 2 - top))) + 'px');
	}
	if (menuEl) {
		menuEl.addEventListener('mouseover', function (e) {
			if (!isFolded() || window.innerWidth <= 782) return;
			var sep = e.target.closest('li.flxa-gh.is-closed');
			if (sep) gflyShow(sep);
			else if (gflySep) gflyLater();
		});
		menuEl.addEventListener('mouseleave', function () { if (gflySep) gflyLater(); });
		var gaw = d.getElementById('adminmenuwrap');
		if (gaw) gaw.addEventListener('scroll', function () { if (gflySep && !gflyHovered()) gflyHide(); }, { passive: true });
		d.addEventListener('keydown', function (e) { if (e.key === 'Escape') gflyHide(); });
	}

	/* ───────── Sağ tık: sabitle, Diğer'e taşı, sırala ───────── */
	function saveMenu(op, slug, extra) {
		var data = { op: op, slug: slug || '' };
		Object.keys(extra || {}).forEach(function (k) { data[k] = extra[k]; });
		return post('flxa_menu_user', data).then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.success) throw new Error('x');
			toast(T.saved);
		}).catch(function () { toast(T.error); });
	}
	function ensureSep(key, label, before) {
		var id = 'flxa-g-' + key;
		var sep = d.getElementById(id);
		if (sep) return sep;
		sep = el('li', { 'class': 'wp-menu-separator flxa-group flxa-gh', id: id, role: 'button', tabindex: '0', title: T.groupToggle }, [el('div', { 'class': 'separator' })]);
		sep.querySelector('.separator').setAttribute('data-label', label);
		sep.appendChild(el('span', { 'class': 'flxa-gcount' }));
		sep.appendChild(el('span', { 'class': 'flxa-gchev', html: B.icons.chevron }));
		sep.appendChild(el('span', { 'class': 'flxa-gmore', html: B.icons.more }));
		M.groups[id] = { key: key, label: label, collapsed: key === 'other', n: 0 };
		var css = d.getElementById('flxa-vars');
		if (css) css.appendChild(d.createTextNode('#' + id + ' .separator::after{content:' + JSON.stringify(label) + '}'));
		menuEl.insertBefore(sep, before || null);
		return sep;
	}
	function lastOf(sep) { var it = groupItems(sep); return it.length ? it[it.length - 1] : sep; }
	function placeAfter(node, ref) { ref.parentNode.insertBefore(node, ref.nextSibling); }
	function firstGroup() { return $('li.flxa-group', menuEl); }
	function footEl() { return $('li.flxa-side-foot', menuEl); }
	function goHome(li, slug) {
		var ref = M.home[slug] && d.getElementById(M.home[slug]);
		var refSlug = ref && M.ids[ref.id];
		if (ref && ref !== li && ref.parentNode === menuEl && !(refSlug && (M.pins.indexOf(refSlug) !== -1 || M.away.indexOf(refSlug) !== -1))) placeAfter(li, ref);
		else return false;
		return true;
	}
	function moveLive(op, slug) {
		var li = liOf(slug);
		if (!li || !menuEl) return false;
		if (op === 'pin') {
			var ps = ensureSep('pinned', T.pinned, firstGroup());
			placeAfter(li, lastOf(ps));
			M.pins = M.pins.filter(function (s) { return s !== slug; }).concat([slug]);
			M.away = M.away.filter(function (s) { return s !== slug; });
		} else if (op === 'away') {
			var os = d.getElementById('flxa-g-other') || ensureSep('other', T.other, footEl());
			placeAfter(li, lastOf(os));
			M.away.push(slug);
			M.pins = M.pins.filter(function (s) { return s !== slug; });
		} else if (op === 'unpin' || op === 'back') {
			if (!goHome(li, slug)) return false;
			M.pins = M.pins.filter(function (s) { return s !== slug; });
			M.away = M.away.filter(function (s) { return s !== slug; });
		}
		var la = li.querySelector('a.menu-top');
		if (la) { if (M.pins.indexOf(slug) !== -1) la.setAttribute('draggable', 'true'); else la.removeAttribute('draggable'); }
		li.classList.add('flxa-flash');
		setTimeout(function () { li.classList.remove('flxa-flash'); }, 900);
		paintGroups();
		return true;
	}
	function menuAct(op, slug) {
		var live = moveLive(op, slug);
		saveMenu(op, slug).then(function () { if (!live) location.reload(); });
	}
	function pinMove(slug, dir) {
		var i = M.pins.indexOf(slug);
		var j = i + dir;
		if (i < 0 || j < 0 || j >= M.pins.length) return;
		var li = liOf(slug), other = liOf(M.pins[j]);
		if (li && other) { if (dir < 0) other.parentNode.insertBefore(li, other); else placeAfter(li, other); }
		M.pins.splice(i, 1);
		M.pins.splice(j, 0, slug);
		saveMenu('order', '', { pins: JSON.stringify(M.pins) });
	}

	var ctx;
	function closeCtx() { if (ctx) { ctx.remove(); ctx = null; } }
	function openCtx(x, y, li) {
		closeCtx();
		var slug = slugOf(li);
		if (!slug) return;
		var a = li.querySelector('a.menu-top');
		var pinned = M.pins.indexOf(slug) !== -1;
		var away = M.away.indexOf(slug) !== -1;
		var items = [];
		items.push(pinned ? { l: T.unpin, i: B.icons.pin, run: function () { menuAct('unpin', slug); } } : { l: T.pin, i: B.icons.pin, run: function () { menuAct('pin', slug); } });
		if (pinned) {
			if (M.pins.indexOf(slug) > 0) items.push({ l: T.moveUp, i: B.icons.up, run: function () { pinMove(slug, -1); } });
			if (M.pins.indexOf(slug) < M.pins.length - 1) items.push({ l: T.moveDown, i: B.icons.down, run: function () { pinMove(slug, 1); } });
		} else {
			items.push(away ? { l: T.back, i: B.icons.inbox, run: function () { menuAct('back', slug); } } : { l: T.away, i: B.icons.inbox, run: function () { menuAct('away', slug); } });
		}
		if (a) items.push({ sep: 1 }, { l: T.newTab, i: B.icons.external, run: function () { window.open(a.href, '_blank'); } });
		if (M.editUrl) items.push({ l: T.editMenu, i: B.icons.menu, run: function () { location.href = M.editUrl; } });
		ctx = el('ul', { 'class': 'flxa-ctx', role: 'menu' });
		items.forEach(function (it) {
			if (it.sep) { ctx.appendChild(el('li', { 'class': 'flxa-ctx-sep', role: 'separator' })); return; }
			var b = el('button', { type: 'button', role: 'menuitem', html: it.i || '' }, [el('span', { text: it.l })]);
			b.addEventListener('click', function () { closeCtx(); it.run(); });
			ctx.appendChild(el('li', {}, [b]));
		});
		d.body.appendChild(ctx);
		var r = ctx.getBoundingClientRect();
		ctx.style.left = Math.min(x, window.innerWidth - r.width - 8) + 'px';
		ctx.style.top = Math.min(y, window.innerHeight - r.height - 8) + 'px';
		var f = ctx.querySelector('button');
		if (f) f.focus();
	}
	if (menuEl) {
		menuEl.addEventListener('contextmenu', function (e) {
			var li = e.target.closest('li.menu-top');
			if (!li || !slugOf(li) || e.shiftKey) return;
			e.preventDefault();
			openCtx(e.clientX, e.clientY, li);
		});
		/* Dokunmatik: uzun basma. */
		var lp;
		menuEl.addEventListener('touchstart', function (e) {
			var li = e.target.closest('li.menu-top');
			if (!li || !slugOf(li)) return;
			var t = e.touches[0];
			lp = setTimeout(function () { lp = 'fired'; openCtx(t.clientX, t.clientY, li); }, 550);
		}, { passive: true });
		['touchend', 'touchmove', 'touchcancel'].forEach(function (ev) {
			menuEl.addEventListener(ev, function (e) {
				if (lp === 'fired' && ev === 'touchend') e.preventDefault();
				if (lp !== 'fired') clearTimeout(lp);
				lp = null;
			});
		});
	}
	d.addEventListener('click', function (e) { if (ctx && !ctx.contains(e.target)) closeCtx(); });
	d.addEventListener('keydown', function (e) {
		if (!ctx) return;
		if (e.key === 'Escape') { closeCtx(); return; }
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			var bs = $$('button', ctx), i = bs.indexOf(d.activeElement);
			bs[(i + (e.key === 'ArrowDown' ? 1 : bs.length - 1)) % bs.length].focus();
		}
	});
	window.addEventListener('scroll', closeCtx, true);
	window.addEventListener('resize', closeCtx);

	/* Sabitlenenler: sürükleyerek sırala. */
	if (menuEl) {
		var pd = null;
		menuEl.addEventListener('dragstart', function (e) {
			var li = e.target.closest && e.target.closest('li.menu-top');
			var slug = slugOf(li);
			if (!slug || M.pins.indexOf(slug) === -1) return;
			pd = li;
			li.classList.add('is-drag');
			try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', slug); } catch (er) { /* eski tarayıcı */ }
		});
		menuEl.addEventListener('dragover', function (e) {
			if (!pd) return;
			var li = e.target.closest('li.menu-top');
			if (!li || li === pd || M.pins.indexOf(slugOf(li)) === -1) return;
			e.preventDefault();
			var r = li.getBoundingClientRect();
			if (e.clientY < r.top + r.height / 2) li.parentNode.insertBefore(pd, li); else placeAfter(pd, li);
		});
		menuEl.addEventListener('dragend', function () {
			if (!pd) return;
			pd.classList.remove('is-drag');
			pd = null;
			var sep = d.getElementById('flxa-g-pinned');
			if (!sep) return;
			var order = groupItems(sep).map(slugOf).filter(Boolean);
			if (order.join() !== M.pins.join()) { M.pins = order; saveMenu('order', '', { pins: JSON.stringify(order) }); }
		});
		M.pins.forEach(function (s) { var li = liOf(s); if (li) { var a = li.querySelector('a.menu-top'); if (a) a.setAttribute('draggable', 'true'); } });
	}

	/* ───────── Başlangıç modülleri: filtreler yerinde yenilenir ───────── */
	d.addEventListener('change', function (e) {
		var s = e.target.closest && e.target.closest('select[data-flxa-wf]');
		if (!s) return;
		var box = s.closest('[data-flxa-widget]');
		if (!box) return;
		var prefs = {};
		$$('select[data-flxa-wf]', box).forEach(function (x) { prefs[x.getAttribute('data-flxa-wf')] = x.value; });
		box.classList.add('is-loading');
		post('flxa_widget', { widget: box.getAttribute('data-flxa-widget'), prefs: JSON.stringify(prefs) })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.success) box.innerHTML = j.data.html; else toast(T.error); })
			.catch(function () { toast(T.error); })
			.finally(function () { box.classList.remove('is-loading'); });
	});

	/* ───────── Hızlı görev ───────── */
	var tm;
	function taskModal() {
		if (!B.task) return;
		if (tm) { tm.hidden = false; tm.querySelector('[name=title]').focus(); return; }
		tm = el('div', { 'class': 'flxa-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': T.newTask });
		var f = el('form', { 'class': 'flxa-modal-box' });
		f.innerHTML = '<h2>' + T.newTask + '</h2>' +
			'<label class="flxa-field"><span>' + T.taskTitle + '</span><input type="text" name="title" required maxlength="200" autocomplete="off"></label>' +
			'<div class="flxa-field-row"><label class="flxa-field"><span>' + T.taskList + '</span><select name="list" required></select></label>' +
			'<label class="flxa-field"><span>' + T.taskDue + '</span><input type="date" name="due"></label>' +
			'<label class="flxa-field"><span>' + T.taskPrio + '</span><select name="priority"><option value="0">' + T.noPrio + '</option></select></label></div>' +
			'<label class="flxa-check-l"><input type="checkbox" name="mine" value="1" checked> ' + T.taskMine + '</label>' +
			'<p class="flxa-modal-err" hidden></p>' +
			'<div class="flxa-modal-actions"><button type="button" class="button" data-close>' + T.cancel + '</button><button type="submit" class="button button-primary">' + T.create + '</button></div>';
		tm.appendChild(f);
		d.body.appendChild(tm);
		function close() { tm.hidden = true; }
		tm.addEventListener('click', function (e) { if (e.target === tm || e.target.closest('[data-close]')) close(); });
		tm.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
		post('flxa_task_form').then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.success) return;
			var ls = f.querySelector('[name=list]'), ps = f.querySelector('[name=priority]');
			j.data.lists.forEach(function (l) { ls.appendChild(el('option', { value: l.id, text: (l.space ? l.space + ' / ' : '') + l.name })); });
			if (j.data.last) ls.value = String(j.data.last);
			j.data.priorities.forEach(function (p) { ps.appendChild(el('option', { value: p.id, text: p.label })); });
		});
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = f.querySelector('.flxa-modal-err');
			var btn = f.querySelector('[type=submit]');
			btn.disabled = true;
			err.hidden = true;
			post('flxa_task_create', {
				title: f.title.value, list: f.list.value, due: f.due.value, priority: f.priority.value, mine: f.mine.checked ? 1 : ''
			}).then(function (r) { return r.json(); }).then(function (j) {
				if (!j || !j.success) { err.textContent = (j && j.data && j.data.message) || T.error; err.hidden = false; return; }
				close();
				f.title.value = '';
				toastLink(T.taskDone, T.open, j.data.url);
				var w = $('[data-flxa-widget="tasks"] select[data-flxa-wf]');
				if (w) w.dispatchEvent(new Event('change', { bubbles: true }));
			}).catch(function () { err.textContent = T.error; err.hidden = false; })
				.finally(function () { btn.disabled = false; });
		});
		setTimeout(function () { f.title.focus(); }, 20);
	}
	function toastLink(msg, label, url) {
		var t = $('#flxa-toast');
		if (!t) return;
		t.innerHTML = '';
		t.appendChild(d.createTextNode(msg + ' '));
		t.appendChild(el('a', { href: url, text: label }));
		t.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { t.hidden = true; }, 5000);
	}
	d.addEventListener('click', function (e) {
		if (e.target.closest('[data-flxa-task]') && B.task) { e.preventDefault(); taskModal(); }
	});

	/* Simgesi boş kalan menü öğelerine (eklentinin kendi CSS'i yoksa) baş harf rozeti. */
	function iconFallback() {
		var css = '';
		$$('#adminmenu > li.menu-top').forEach(function (li) {
			if (/\b(flxa-logo|flxa-lic|flxa-letter)\b/.test(li.className)) return;
			var box = $('.wp-menu-image', li);
			if (!box || box.classList.contains('flxa-svgmask')) return;
			var img = $('img', box);
			if (img && img.getAttribute('src')) return;
			var cs = getComputedStyle(box);
			if (cs.backgroundImage && cs.backgroundImage !== 'none') return;
			var bc = getComputedStyle(box, '::before').content;
			if (bc && bc !== 'none' && bc !== 'normal' && bc !== '""' && bc !== "''") return;
			if ($('svg, i, span', box)) return;
			var name = $('.wp-menu-name', li);
			var t = name ? (name.childNodes[0] && name.childNodes[0].nodeType === 3 ? name.childNodes[0].nodeValue : name.textContent) : '';
			t = (t || '').trim();
			var c = t ? t.charAt(0) : '\u2022';
			c = c === 'i' ? '\u0130' : c.toLocaleUpperCase('tr');
			li.classList.add('flxa-letter');
			li.setAttribute('data-flxa-ch', c);
			if (li.id) css += '#adminmenu #' + li.id.replace(/([^\w-])/g, '\\$1') + ' div.wp-menu-image::before{content:"' + c.replace(/["\\]/g, '') + '"!important}';
		});
		if (css) d.head.appendChild(el('style', { id: 'flxa-letters', text: css }));
	}
	if (d.readyState === 'complete') iconFallback();
	else window.addEventListener('load', iconFallback);

	/* ───────── Menü daralma animasyonu ───────── */
	function sideAnim() {
		html.classList.add('flxa-side-anim');
		clearTimeout(sideAnim.t);
		sideAnim.t = setTimeout(function () { html.classList.remove('flxa-side-anim'); }, 320);
	}
	function sideReveal() {
		if (!html.classList.contains('flxa-side-from')) return;
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				sideAnim();
				html.classList.remove('flxa-side-from');
			});
		});
	}
	if (d.prerendering) d.addEventListener('prerenderingchange', sideReveal, { once: true });
	else sideReveal();
	function sideRemember() {
		var x = d.getElementById('adminmenuwrap');
		try { if (x && window.innerWidth > 960) sessionStorage.setItem('flxa-pw', String(x.getBoundingClientRect().width)); } catch (e) {}
	}
	window.addEventListener('pagehide', sideRemember);
	d.addEventListener('click', function (e) { if (e.target.closest('[data-flxa-fold], #collapse-button')) sideAnim(); }, true);

	/* ───────── Daraltılmış menü: alt menü konumu ve ad ipucu ───────── */
	var tip = null;
	function railOn() { return d.body.classList.contains('folded') || (d.body.classList.contains('auto-fold') && window.innerWidth <= 960); }
	function tipText(a) {
		var n = $('.wp-menu-name', a);
		if (n) {
			var c = n.childNodes[0];
			return ((c && c.nodeType === 3 ? c.nodeValue : n.textContent) || '').trim();
		}
		return (a.getAttribute('aria-label') || a.getAttribute('title') || a.textContent || '').trim();
	}
	function tipHide() { if (tip) tip.classList.remove('is-on'); }
	/* Daraltılmış menüde aynı anda tek açılır alt menü: WordPress'in hoverIntent'i bazen "opensub"u
	   eski öğelerde bırakıyor; o zaman tüm alt menüler kendi satırında üst üste kart gibi açık kalıyordu. */
	function subReset(keep) {
		[].forEach.call(d.querySelectorAll('#adminmenu > li.menu-top'), function (o) {
			if (o === keep) return;
			o.classList.remove('opensub');
			o.style.removeProperty('--flxa-sub-top');
		});
	}
	var amenu = d.getElementById('adminmenu');
	if (amenu) {
		amenu.addEventListener('mouseover', function (e) {
			if (!railOn() || window.innerWidth <= 782) return;
			var li = e.target.closest('#adminmenu > li.menu-top');
			if (li) {
				subReset(li);
				var sub = $('.wp-submenu', li);
				if (sub) {
					var r = li.getBoundingClientRect();
					var h = sub.offsetHeight || 0;
					var top = Math.max(8, Math.min(r.top - 1, window.innerHeight - h - 8));
					li.style.setProperty('--flxa-sub-top', top + 'px');
				}
			}
			var a = e.target.closest('#adminmenu > li.menu-top:not(.wp-has-submenu) > a, .flxa-side-head a, .flxa-side-head button, .flxa-side-foot a, .flxa-side-foot button');
			if (!a) { tipHide(); return; }
			var t = tipText(a);
			if (!t) { tipHide(); return; }
			if (!tip) { tip = el('div', { id: 'flxa-tip', role: 'tooltip' }); d.body.appendChild(tip); }
			tip.textContent = t;
			var rr = a.getBoundingClientRect();
			tip.style.left = Math.round(rr.right + 10) + 'px';
			tip.style.top = Math.round(rr.top + rr.height / 2 - 13) + 'px';
			tip.classList.add('is-on');
		});
		amenu.addEventListener('mouseleave', function () { tipHide(); subReset(null); });
		var aw = d.getElementById('adminmenuwrap');
		if (aw) aw.addEventListener('scroll', tipHide, { passive: true });
		/* Sayfa değişince menü aynı kaydırma yerinde açılsın (ilk boyamada menu early paint geri koyar). */
		if (aw) window.addEventListener('pagehide', function () { try { sessionStorage.setItem('flxa-ms', String(aw.scrollTop)); } catch (e) {} });
	}

	var botBar = d.getElementById('flxa-bottom');

	/* ───────── Güvenli alan: panel kabuğunun (üst çubuk, sol menü, Flux AI paneli, mobil alt bar) kapladığı kenarlar ─────────
	   html'de --flxa-safe-top/right/bottom/left (px) olarak durur, değişince window'a 'flxa:safearea' olayı gider.
	   Eklentiler tam ekran sayfa ve modallarını bu boşluklara göre yerleştirir; JS'ten FLXA.safeArea(). */
	var SAFE = { top: 0, right: 0, bottom: 0, left: 0 }, safeT = 0;
	function edge(id, side) {
		var n = d.getElementById(id);
		if (!n) return 0;
		var cs = getComputedStyle(n);
		if (cs.display === 'none' || cs.visibility === 'hidden') return 0;
		var r = n.getBoundingClientRect();
		if (r.width < 1 || r.height < 1) return 0;
		if (side === 'top') return r.top <= 1 ? Math.max(0, Math.round(r.bottom)) : 0;
		if (side === 'left') return r.left <= 1 && r.right < window.innerWidth / 2 ? Math.max(0, Math.round(r.right)) : 0;
		if (side === 'bottom') return r.bottom >= window.innerHeight - 1 ? Math.max(0, Math.round(window.innerHeight - r.top)) : 0;
		return 0;
	}
	function safeCalc() {
		safeT = 0;
		var ai = html.classList.contains('flxai-docked') ? parseFloat(getComputedStyle(html).getPropertyValue('--flxai-w')) || 0 : 0;
		var n = {
			top: edge('wpadminbar', 'top'),
			right: window.innerWidth >= 1024 ? Math.round(ai) : 0,
			bottom: html.classList.contains('flxa-kb') ? 0 : edge('flxa-bottom', 'bottom'),
			left: window.innerWidth > 782 ? edge('adminmenuwrap', 'left') : 0
		};
		if (n.top === SAFE.top && n.right === SAFE.right && n.bottom === SAFE.bottom && n.left === SAFE.left) return;
		SAFE = n;
		for (var k in n) html.style.setProperty('--flxa-safe-' + k, n[k] + 'px');
		try { window.dispatchEvent(new CustomEvent('flxa:safearea', { detail: B.safeArea() })); } catch (e) {}
	}
	function safeLater() { if (!safeT) safeT = requestAnimationFrame(safeCalc); }
	B.safeArea = function () { return { top: SAFE.top, right: SAFE.right, bottom: SAFE.bottom, left: SAFE.left }; };
	safeCalc();
	window.addEventListener('resize', safeLater);
	window.addEventListener('flxai:layout', safeLater);
	if (window.ResizeObserver) {
		var safeRO = new ResizeObserver(safeLater);
		['wpadminbar', 'adminmenuwrap', 'flxa-bottom'].forEach(function (id) { var n = d.getElementById(id); if (n) safeRO.observe(n); });
	}
	if (window.MutationObserver) new MutationObserver(safeLater).observe(html, { attributes: true, attributeFilter: ['class'] });
	var aw2 = d.getElementById('adminmenuwrap');
	if (aw2) aw2.addEventListener('transitionend', safeLater);

	/* ───────── Modal açıkken: modal sol menüyü / üst çubuğu örtmüyorsa onlar da karartılır (html.flxa-dim-side / flxa-dim-bar),
	   tıklanamaz. Mobilde alt bar arkaya alınır (Kaydet düğmeleri altında kalmasın). Modal kendini [aria-modal="true"] ya da
	   [data-flxa-modal] ile işaretler; kabuğu karartmasın istenirse [data-flxa-nodim]. Elle: FLXA.modal(true|false). */
	if (window.MutationObserver) {
		var MODAL_SEL = '[aria-modal="true"], [data-flxa-modal], .media-modal, .wc-backbone-modal, #TB_window';
		var modalT = 0, forced = 0;
		var shown = function (n) {
			if (n.closest('[hidden], .flxa-pal')) return false;
			var cs = getComputedStyle(n);
			if (cs.display === 'none' || cs.visibility === 'hidden') return false;
			var r = n.getBoundingClientRect();
			return r.width > 0 && r.height > 0;
		};
		/* Kabuk parçası modalın arkasında mı (modalın kendi perdesi zaten örtüyorsa ikinci kez karartma) */
		var bare = function (id, x, y) {
			var n = d.getElementById(id);
			if (!n || !n.getBoundingClientRect().width) return false;
			var t = d.elementFromPoint(x, y);
			return !!(t && n.contains(t));
		};
		/* Tam sayfa açılan detay (ör. Görev detayı): içerik alanını opak biçimde tümüyle kaplıyor, perde yok; kabuk karartılmaz */
		var alpha = function (c) { var m = /rgba?\(([^)]+)\)/.exec(c || ''); if (!m) return 0; var p = m[1].split(/[ ,\/]+/).filter(Boolean); return p.length > 3 ? parseFloat(p[3]) : 1; };
		var fullPage = function (m) {
			var r = m.getBoundingClientRect(), a = B.safeArea();
			var w = window.innerWidth - a.left - a.right, h = window.innerHeight - a.top - a.bottom;
			return r.width >= w * 0.95 && r.height >= h * 0.9 && alpha(getComputedStyle(m).backgroundColor) >= 0.95;
		};
		var setCls = function (c, on) { if (html.classList.contains(c) !== on) html.classList.toggle(c, on); };
		var modalCheck = function () {
			modalT = 0;
			var list = $$(MODAL_SEL).filter(shown), on = forced > 0 || list.length > 0;
			var dim = on && (forced > 0 || list.some(function (m) { return !m.closest('[data-flxa-nodim]') && !fullPage(m); }));
			setCls('flxa-modal-open', on);
			/* Önce karartmayı kaldırıp ölç, sonra gerekiyorsa koy (karartılmış öğe tıklanamaz olduğundan ölçümü bozmasın) */
			setCls('flxa-dim-side', false); setCls('flxa-dim-bar', false);
			if (!dim) return;
			var mobile = window.innerWidth <= 782;
			setCls('flxa-dim-side', !mobile && bare('adminmenuwrap', 8, Math.round(window.innerHeight / 2)));
			setCls('flxa-dim-bar', bare('wpadminbar', Math.round(window.innerWidth / 2), 8));
		};
		B.modal = function (on) { forced = Math.max(0, forced + (on ? 1 : -1)); modalCheck(); };
		new MutationObserver(function () { if (!modalT) modalT = requestAnimationFrame(modalCheck); })
			.observe(d.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden', 'style', 'aria-hidden', 'open', 'aria-modal'] });
		modalCheck();
	}

	/* ───────── Mobil alt bar: klavye açıkken gizlenir (yazı alanına odak ya da görünür alan küçülünce) ───────── */
	if (botBar) {
		var KB_SEL = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=range]):not([type=color]):not([type=file]), textarea, select, [contenteditable=""], [contenteditable="true"]';
		var kbFocus = false;
		var kbSet = function () {
			var vv = window.visualViewport;
			var shrunk = !!vv && window.innerHeight - vv.height > 150;
			var on = window.innerWidth <= 782 && (kbFocus || shrunk);
			if (html.classList.contains('flxa-kb') !== on) html.classList.toggle('flxa-kb', on);
		};
		d.addEventListener('focusin', function (e) { kbFocus = !!(e.target.matches && e.target.matches(KB_SEL)); kbSet(); });
		d.addEventListener('focusout', function () {
			setTimeout(function () { var a = d.activeElement; kbFocus = !!(a && a.matches && a.matches(KB_SEL)); kbSet(); }, 60);
		});
		if (window.visualViewport) window.visualViewport.addEventListener('resize', kbSet);
	}

	/* ───────── Üst bar: sığmayanlar "…" menüsüne ───────── */
	var abRoot = d.getElementById('wp-admin-bar-root-default');
	var abSec = d.getElementById('wp-admin-bar-top-secondary');
	var abMore = null, abMoved = [];
	function abFit() {
		var bar = d.getElementById('wpadminbar');
		if (!bar || !abRoot || !abSec) { d.documentElement.classList.add('flxa-abfit'); return; }
		if (abMore) {
			abMoved.forEach(function (li) { abRoot.insertBefore(li, abMore); });
			abMoved = [];
			abMore.style.display = 'none';
		}
		var mob = window.innerWidth <= 782;
		var avail = bar.clientWidth - abSec.getBoundingClientRect().width - (mob ? 0 : 12);
		function used() {
			var w = 0;
			Array.prototype.forEach.call(abRoot.children, function (li) { if (li.offsetParent !== null) w += li.getBoundingClientRect().width; });
			return w;
		}
		d.documentElement.classList.add('flxa-abfit');
		if (used() <= avail) return;
		if (!abMore) {
			abMore = el('li', { id: 'wp-admin-bar-flxa-more', 'class': 'menupop' }, [
				el('a', { 'class': 'ab-item', href: '#', role: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false', title: T.more || '', 'aria-label': T.more || '', html: B.icons.more || '…' }),
				el('div', { 'class': 'ab-sub-wrapper' }, [el('ul', { 'class': 'ab-submenu', id: 'wp-admin-bar-flxa-more-default' })])
			]);
			abRoot.appendChild(abMore);
			var btn = abMore.firstChild;
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var on = abMore.classList.toggle('is-open');
				btn.setAttribute('aria-expanded', on ? 'true' : 'false');
			});
			d.addEventListener('click', function (e) { if (abMore && !abMore.contains(e.target)) abMore.classList.remove('is-open'); });
		}
		abMore.style.display = '';
		var list = abMore.querySelector('.ab-submenu');
		var keep = /^wp-admin-bar-(menu-toggle|wp-logo|site-name|flxa-more)$/;
		var kids = Array.prototype.slice.call(abRoot.children);
		for (var i = kids.length - 1; i >= 0 && used() > avail; i--) {
			var li = kids[i];
			if (keep.test(li.id) || li.offsetParent === null) continue;
			list.insertBefore(li, list.firstChild);
			abMoved.unshift(li);
			// Telefonda üst bar yalnız simge gösterir; menüde adı görünsün.
			var ai = li.querySelector(':scope > .ab-item');
			if (ai && !ai.hasAttribute('data-flxa-l')) {
				var lb = li.querySelector(':scope > .ab-item .ab-label');
				var nm = (lb && lb.textContent.trim()) || ai.getAttribute('aria-label') || ai.getAttribute('title') || '';
				if (!nm) { var sr = ai.querySelector('.screen-reader-text'); nm = sr ? sr.textContent.trim() : ''; }
				var own = ai.cloneNode(true);
				Array.prototype.forEach.call(own.querySelectorAll('.screen-reader-text'), function (x) { x.remove(); });
				if (nm && !own.textContent.trim()) ai.setAttribute('data-flxa-l', nm.slice(0, 40));
			}
		}
	}
	var abT;
	window.addEventListener('resize', function () { clearTimeout(abT); abT = setTimeout(abFit, 120); });
	if (d.fonts && d.fonts.ready) d.fonts.ready.then(abFit);
	abFit();

	/* ───────── Koyu tema koruması: eklentilerin sabit beyaz kutuları ve koyu yazıları ───────── */
	function rgb(v) {
		var m = v && v.match(/rgba?\(([^)]+)\)/), k = 1;
		if (!m) {
			// color-mix() sonuçları "color(srgb r g b / a)" biçiminde (0-1 aralığı) gelir.
			m = v && v.match(/color\(srgb ([^)]+)\)/);
			k = 255;
		}
		if (!m) return null;
		var p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat);
		return { r: p[0] * k, g: p[1] * k, b: p[2] * k, a: p.length > 3 ? p[3] : 1 };
	}
	// Yazının gerçekte üstünde durduğu zemin: ilk opak arka plan. Koyulaştırılacaksa null (koyu sayılır).
	function effBg(e, dark) {
		for (var x = e; x && x !== d.body; x = x.parentElement) {
			if (dark.indexOf(x) !== -1) return null;
			var b = rgb(getComputedStyle(x).backgroundColor);
			if (b && b.a > 0.5) return b;
		}
		return null;
	}
	function lum(c) { return (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255; }
	function sat(c) { return Math.max(c.r, c.g, c.b) - Math.min(c.r, c.g, c.b); }
	var dgDone = [];
	var DG_SKIP = /^(IMG|SVG|PATH|CANVAS|VIDEO|IFRAME|BR|SCRIPT|STYLE|OPTION|NOSCRIPT|PICTURE|SOURCE|HR)$/;
	function hasText(n) {
		for (var c = n.firstChild; c; c = c.nextSibling) if (c.nodeType === 3 && c.nodeValue.trim()) return true;
		return false;
	}
	function dgSet(n, prop, val) {
		dgDone.push([n, prop, n.style.getPropertyValue(prop), n.style.getPropertyPriority(prop)]);
		n.style.setProperty(prop, val, 'important');
	}
	function dgClear() {
		dgDone.forEach(function (x) { x[0].style.removeProperty(x[1]); if (x[2]) x[0].style.setProperty(x[1], x[2], x[3]); });
		dgDone = [];
	}
	function dgScan() {
		if (!html.classList.contains('flxa-dark') || d.body.classList.contains('flxa-appmode')) return;
		var root = d.getElementById('wpbody');
		if (!root) return;
		var all = root.getElementsByTagName('*');
		var n = Math.min(all.length, 6000), todo = [], darkBg = [];
		for (var i = 0; i < n; i++) {
			var e = all[i];
			if (DG_SKIP.test(e.tagName.toUpperCase()) || e instanceof SVGElement) continue;
			var cs = getComputedStyle(e);
			if (cs.display === 'none') continue;
			var bg = rgb(cs.backgroundColor);
			if (bg && bg.a > 0.5 && lum(bg) > 0.8 && cs.backgroundImage === 'none') {
				var r = e.getBoundingClientRect();
				if (r.width * r.height > 900 || e.tagName === 'TD' || e.tagName === 'TR') {
					todo.push([e, 'background-color', sat(bg) > 14 ? 'var(--flxa-surface-2)' : 'var(--flxa-surface)']);
					darkBg.push(e);
					var bc = rgb(cs.borderTopColor);
					if (bc && lum(bc) > 0.7 && parseFloat(cs.borderTopWidth) > 0) todo.push([e, 'border-color', 'var(--flxa-border)']);
				}
			}
			var c = rgb(cs.color);
			var eb = c && lum(c) < 0.36 && hasText(e) ? effBg(e, darkBg) : null;
			// Açık bir rozet/düğme üstündeki koyu yazı zaten okunur; dokunma (yoksa açık zeminde açık yazı olur).
			if (c && lum(c) < 0.36 && hasText(e) && !(eb && lum(eb) > 0.5)) {
				todo.push([e, 'color', sat(c) > 50 ? 'color-mix(in srgb, ' + cs.color + ' 45%, #fff)' : 'var(--flxa-text)']);
			}
		}
		todo.forEach(function (t) { dgSet(t[0], t[1], t[2]); });
	}
	var dgT;
	function dgLater() { clearTimeout(dgT); dgT = setTimeout(dgScan, 250); }
	if (window.MutationObserver) {
		new MutationObserver(function () {
			if (html.classList.contains('flxa-dark')) dgLater(); else if (dgDone.length) dgClear();
		}).observe(html, { attributes: true, attributeFilter: ['class'] });
		var wpb = d.getElementById('wpbody');
		if (wpb) new MutationObserver(function () { if (html.classList.contains('flxa-dark')) dgLater(); }).observe(wpb, { childList: true, subtree: true });
	}
	if (d.readyState === 'complete') dgScan(); else window.addEventListener('load', dgScan);

	/* Başlangıç: Ekran ayarları / Yardım başlık satırındaki düğmelerin yanında */
	var heroAct = $('.flxa-hero-actions'), sml = d.getElementById('screen-meta-links');
	if (heroAct && sml) heroAct.insertBefore(sml, heroAct.firstChild);

	/* ───────── Alt menüler: az kullanılanlar sonda kapalı "Diğer" altında ───────── */
	var SUB_RARE = /(ayar|setting|şablon|template|kayıtlar|\blog\b|\(log\)|kuyru|queue|sağlayıcı|provider|sistem kontrol|system check|system status|içe aktar|dışa aktar|import|export|lisans|licen|yardım|\bhelp\b|entegrasyon|integration|webhook|gelişmiş|advanced|hakkında|about|uzantı|extension|araçlar|\btools\b|durum|status)/i;
	var SUB_CORE = /^menu-(dashboard|posts|media|pages|comments|appearance|plugins|users|tools|settings)$/;
	var subAway = (M.subAway || []).slice(), subKeep = (M.subKeep || []).slice();
	function subKey(a) { return a ? (a.getAttribute('href') || '') : ''; }
	function subText(a) {
		var c = a && a.childNodes[0];
		return ((c && c.nodeType === 3 ? c.nodeValue : (a ? a.textContent : '')) || '').trim();
	}
	function subOpenKey(top) { return 'flxa-subopen-' + (top.id || ''); }
	function subLayout(top) {
		var ul = $('.wp-submenu', top);
		if (!ul) return;
		var old = $('li.flxa-sub-more', ul);
		if (old) old.remove();
		var items = $$(':scope > li', ul).filter(function (li) { return !li.classList.contains('wp-submenu-head'); });
		if (!items.length) return;
		var auto = !SUB_CORE.test(top.id || '');
		var rare = [];
		items.forEach(function (li, i) {
			li.classList.remove('flxa-sub-rare');
			var a = $('a', li), k = subKey(a);
			var r = subAway.indexOf(k) !== -1 || (auto && i > 0 && subKeep.indexOf(k) === -1 && SUB_RARE.test(subText(a)));
			if (r) rare.push(li);
		});
		// Tek bir az kullanılan öğe için "Diğer" açılmaz; öğe olduğu yerde görünür.
		if (rare.length < 2 || rare.length === items.length) return;
		var cur = false;
		rare.forEach(function (li) { li.classList.add('flxa-sub-rare'); ul.appendChild(li); if (li.classList.contains('current')) cur = true; });
		var open = cur;
		try { open = open || localStorage.getItem(subOpenKey(top)) === '1'; } catch (e) {}
		ul.classList.toggle('flxa-sub-open', open);
		var btn = el('a', { href: '#', role: 'button', 'aria-expanded': open ? 'true' : 'false' }, [
			el('span', { text: T.other || 'Diğer' }),
			el('span', { 'class': 'flxa-sub-n', text: String(rare.length) }),
			el('span', { 'class': 'flxa-sub-chev', html: B.icons.chevron || '' })
		]);
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var on = ul.classList.toggle('flxa-sub-open');
			btn.setAttribute('aria-expanded', on ? 'true' : 'false');
			try { localStorage.setItem(subOpenKey(top), on ? '1' : '0'); } catch (er) {}
		});
		ul.insertBefore(el('li', { 'class': 'flxa-sub-more' }, [btn]), rare[0]);
	}
	$$('#adminmenu > li.menu-top .wp-submenu').forEach(function (ul) {
		$$(':scope > li', ul).forEach(function (li, i) { li.setAttribute('data-flxa-i', i); });
	});
	$$('#adminmenu > li.menu-top').forEach(subLayout);
	function subAct(op, a, top) {
		var k = subKey(a);
		if (!k) return;
		subAway = subAway.filter(function (x) { return x !== k; });
		subKeep = subKeep.filter(function (x) { return x !== k; });
		if (op === 'subaway') subAway.push(k); else subKeep.push(k);
		var ul = $('.wp-submenu', top);
		// Önce özgün sıraya dön, sonra yeniden diz.
		if (ul) $$(':scope > li[data-flxa-i]', ul).sort(function (x, y) { return x.getAttribute('data-flxa-i') - y.getAttribute('data-flxa-i'); }).forEach(function (li) { ul.appendChild(li); });
		subLayout(top);
		saveMenu(op, k);
	}
	if (menuEl) {
		menuEl.addEventListener('contextmenu', function (e) {
			var sli = e.target.closest('.wp-submenu > li:not(.wp-submenu-head):not(.flxa-sub-more)');
			if (!sli || e.shiftKey) return;
			var a = $('a', sli), top = sli.closest('li.menu-top');
			if (!a || !top) return;
			e.preventDefault();
			e.stopPropagation();
			closeCtx();
			var rare = sli.classList.contains('flxa-sub-rare');
			var items = [
				rare ? { l: T.back, i: B.icons.inbox, run: function () { subAct('subback', a, top); } } : { l: T.away, i: B.icons.inbox, run: function () { subAct('subaway', a, top); } },
				{ sep: 1 },
				{ l: T.newTab, i: B.icons.external, run: function () { window.open(a.href, '_blank'); } }
			];
			ctx = el('ul', { 'class': 'flxa-ctx', role: 'menu' });
			items.forEach(function (it) {
				if (it.sep) { ctx.appendChild(el('li', { 'class': 'flxa-ctx-sep', role: 'separator' })); return; }
				var b = el('button', { type: 'button', role: 'menuitem', html: it.i || '' }, [el('span', { text: it.l })]);
				b.addEventListener('click', function () { closeCtx(); it.run(); });
				ctx.appendChild(el('li', {}, [b]));
			});
			d.body.appendChild(ctx);
			var r = ctx.getBoundingClientRect();
			ctx.style.left = Math.min(e.clientX, window.innerWidth - r.width - 8) + 'px';
			ctx.style.top = Math.min(e.clientY, window.innerHeight - r.height - 8) + 'px';
			var f = ctx.querySelector('button');
			if (f) f.focus();
		}, true);
	}

	/* ───────── Sayfa yükleniyor: üstte ince ilerleme çubuğu ─────────
	   Bağlantıya / form gönderimine basınca hemen başlar; yeni sayfa gelince zaten kaybolur. İndirme gibi
	   sayfa değişmeyen durumlarda 12 sn sonra kendiliğinden söner. */
	(function () {
		var bar = el('div', { id: 'flxa-progress', 'aria-hidden': 'true' });
		d.body.appendChild(bar);
		var tm = 0;
		function stop() { clearTimeout(tm); html.classList.remove('flxa-loading'); }
		function start() {
			stop();
			void bar.offsetWidth; // animasyon baştan başlasın
			html.classList.add('flxa-loading');
			tm = setTimeout(stop, 12000);
		}
		window.FLXA_PROGRESS = { start: start, stop: stop };
		window.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
			var a = e.target.closest && e.target.closest('a[href]');
			if (!a || a.hasAttribute('download') || (a.target && a.target !== '_self')) return;
			var u;
			try { u = new URL(a.href, location.href); } catch (x) { return; }
			if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return;
			if (u.pathname === location.pathname && u.search === location.search && u.hash) return;
			start();
		});
		window.addEventListener('submit', function (e) {
			var f = e.target;
			if (e.defaultPrevented || (f.target && f.target !== '_self')) return;
			start();
		});
		window.addEventListener('pageshow', stop);
	})();


	/* Üçüncü parti menü simgeleri (data:image/svg, çoğu beyaz): açık temada görünmüyordu. Maske olarak
	   kullanılıp çizgi simgeler gibi kutucuk rengine boyanır (açık ve koyu temada okunur). */
	(function () {
		var els = document.querySelectorAll('#adminmenu li.menu-top:not(.flxa-logo):not(.flxa-lic):not(.flxa-letter) div.wp-menu-image.svg');
		for (var i = 0; i < els.length; i++) {
			var bg = els[i].style.backgroundImage || '';
			if (bg.indexOf('data:image/svg') === -1) { continue; }
			els[i].style.setProperty('--flxa-svg', bg);
			els[i].classList.add('flxa-svgmask');
		}
	})();

})();
