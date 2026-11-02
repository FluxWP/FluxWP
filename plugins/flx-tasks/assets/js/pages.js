/* Flux Tasks — kabuk: yönlendirme, kenar menü, sayfalar (görünümler, bildirimler, pano, ayarlar, profil), proje/liste ayarları, yapay zekâ sohbeti. */
(function () {
	'use strict';
	var Y = window.FLXG;
	var t = Y.t;
	var h = Y.h;
	var S = Y.S;
	var B = Y.B;
	var app;
	var R = {};
	function store(k, v) {
		try {
			if (v === undefined) return localStorage.getItem('flxg-' + k);
			if (v === null) localStorage.removeItem('flxg-' + k);
			else localStorage.setItem('flxg-' + k, v);
		} catch (e) { return null; }
		return null;
	}

	/* =====================================================================
	 * Tema
	 * ================================================================== */
	Y.applyTheme = function () {
		var m = store('theme') || 'auto';
		var dark = m === 'dark' || (m === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
		app.dataset.theme = dark ? 'dark' : 'light';
		document.documentElement.classList.toggle('flxg-dark', dark);
	};
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () { if (app) Y.applyTheme(); });

	/* =====================================================================
	 * Yönlendirme
	 * ================================================================== */
	Y.go = function (hash, replace) {
		if (location.hash === hash) { route(); return; }
		if (replace && history.replaceState) { history.replaceState(null, '', hash); route(); }
		else location.hash = hash;
	};
	/** Görev penceresi açılıp kapanırken korunacak adres (görünüm, doküman sayfası). */
	Y.routeBase = function () {
		var r = Y.route || {};
		return (r.base || '#/home') + (r.view ? '/v/' + r.view : '') + (r.pg ? '/p/' + r.pg : '');
	};
	Y.scopeHash = function () {
		if (!S.scope) return '#/home';
		return S.scope.type === 'home' ? '#/home' : '#/' + S.scope.type + '/' + S.scope.id;
	};
	function parse() {
		var parts = location.hash.replace(/^#\/?/, '').split('/').filter(Boolean);
		var r = { page: parts[0] || '', id: 0, view: 0, task: 0, pg: 0 };
		var i = 1;
		if (r.page === 'list' || r.page === 'space' || r.page === 'task' || r.page === 'doc') { r.id = +parts[1] || 0; i = 2; }
		for (; i < parts.length; i += 2) {
			if (parts[i] === 'v') r.view = +parts[i + 1] || 0;
			if (parts[i] === 't') r.task = +parts[i + 1] || 0;
			if (parts[i] === 'p') r.pg = +parts[i + 1] || 0;
		}
		r.base = '#/' + r.page + (r.id && r.page !== 'task' ? '/' + r.id : '');
		return r;
	}
	function defaultHash() {
		var last = store('last');
		if (last && /^#\/(list|space)\/\d+/.test(last)) {
			var m = /^#\/(list|space)\/(\d+)/.exec(last);
			if ((m[1] === 'list' && Y.list(m[2])) || (m[1] === 'space' && Y.space(m[2]))) return m[0];
		}
		if (S.lists.length) return '#/list/' + S.lists[0].id;
		return '#/home';
	}

	var routing = Promise.resolve();
	function route() {
		routing = routing.then(doRoute, doRoute);
	}
	function doRoute() {
		var r = parse();
		if (!r.page) { Y.go(B.start === 'dashboard' ? '#/dashboard' : B.start === 'settings' ? '#/settings' : defaultHash(), true); return; }
		if (r.page === 'task') {
			return Y.api('GET', 'tasks/' + r.id).then(function (d) { Y.go('#/list/' + d.task.list + '/t/' + r.id, true); }, function (e) { Y.fail(e); Y.go(defaultHash(), true); });
		}
		var prev = Y.route || {};
		Y.route = r;
		closeNav();
		if (r.page !== 'doc' && prev.page === 'doc') Y.leaveDoc();
		if (r.page === 'doc') {
			if (prev.base !== r.base) { S.q = ''; S.scope = null; S.tasks = {}; }
			renderSide();
			return Y.showDoc(R.main, r.id, r.pg).then(function () { Y.showTask(r.task); });
		}
		var scopeType = r.page === 'list' || r.page === 'space' ? r.page : r.page === 'home' ? 'home' : null;
		var p = Promise.resolve();
		if (prev.base !== r.base || !R.main.firstChild) {
			S.q = '';
			if (scopeType) {
				if (r.page === 'list' && !Y.list(r.id)) { Y.toast(t('Liste bulunamadı ya da erişiminiz yok.'), { error: true }); Y.go(defaultHash(), true); return; }
				if (r.page === 'space' && !Y.space(r.id)) { Y.toast(t('Proje bulunamadı.'), { error: true }); Y.go(defaultHash(), true); return; }
				showLoading();
				p = Y.loadScope(scopeType, r.id).then(function (ok) {
					if (!ok) return;
					pickView(r.view);
					if (scopeType !== 'home') store('last', r.base);
					renderScopePage();
				}, function (e) { Y.fail(e); showError(e); });
			} else {
				S.scope = null;
				S.tasks = {};
				renderOtherPage(r.page);
			}
		} else if (scopeType && prev.view !== r.view) {
			pickView(r.view);
			renderScopePage();
		}
		renderSide();
		return p.then(function () { Y.showTask(r.task); });
	}
	function pickView(id) {
		var v = S.views.filter(function (x) { return x.id === id; })[0];
		if (!v) {
			var rem = +store('view-' + Y.scopeHash());
			v = S.views.filter(function (x) { return x.id === rem; })[0] || S.views[0];
		}
		Y.setView(v || null);
		if (v) store('view-' + Y.scopeHash(), v.id);
	}
	function showLoading() {
		Y.clear(R.main).appendChild(Y.skeleton('list'));
	}
	function showError(e) {
		Y.clear(R.main).appendChild(h('.flxg-empty', h('p', e.message), h('button.flxg-btn', { type: 'button', onclick: function () { location.reload(); } }, t('Yeniden dene'))));
	}

	/* =====================================================================
	 * Kabuk
	 * ================================================================== */
	function shell() {
		Y.clear(app);
		R.side = h('aside.flxg-side');
		R.main = h('main.flxg-main');
		R.shade = h('.flxg-nav-shade', { onclick: closeNav });
		app.appendChild(h('.flxg-shell', R.side, R.shade, R.main));
		var w = +store('side-w');
		if (w >= 200 && w <= 480) app.style.setProperty('--side-w', w + 'px');
		app.classList.toggle('side-collapsed', store('side-col') === '1');
	}
	/** Masaüstünde kenar menüyü gizler / gösterir (tercih saklanır). */
	function setSideCollapsed(v) {
		app.classList.toggle('side-collapsed', v);
		store('side-col', v ? '1' : null);
	}
	Y.toggleSide = function () {
		if (Y.isMobile()) { openNav(); return; }
		setSideCollapsed(!app.classList.contains('side-collapsed'));
	};
	/** Kenar menünün sağ kenarından genişlik ayarı; çift tıklama varsayılana döndürür. */
	function sideResizer() {
		var grip = h('.flxg-side-resize', { title: t('Genişliği değiştirmek için sürükleyin, sıfırlamak için çift tıklayın') });
		grip.addEventListener('pointerdown', function (e) {
			if (e.button !== 0) return;
			e.preventDefault();
			var sx = e.clientX;
			var sw = R.side.getBoundingClientRect().width;
			var w = sw;
			var raw = sw;
			document.body.classList.add('flxg-col-resizing');
			app.classList.add('is-side-resizing');
			function mv(ev) {
				raw = sw + ev.clientX - sx;
				w = Math.round(Math.max(200, Math.min(480, raw)));
				app.style.setProperty('--side-w', w + 'px');
			}
			function up() {
				document.removeEventListener('pointermove', mv);
				document.removeEventListener('pointerup', up);
				document.body.classList.remove('flxg-col-resizing');
				app.classList.remove('is-side-resizing');
				// Çok daraltılırsa tamamen gizle.
				if (raw < 130) { setSideCollapsed(true); app.style.setProperty('--side-w', sw + 'px'); return; }
				store('side-w', String(w));
			}
			document.addEventListener('pointermove', mv);
			document.addEventListener('pointerup', up);
		});
		grip.addEventListener('dblclick', function () { app.style.removeProperty('--side-w'); store('side-w', null); });
		return grip;
	}
	function openNav() { app.classList.add('nav-open'); }
	function closeNav() { app.classList.remove('nav-open'); }

	function collapsed(key, val) {
		var c = {};
		try { c = JSON.parse(store('collapsed') || '{}'); } catch (e) { c = {}; }
		if (val === undefined) return !!c[key];
		if (val) c[key] = 1;
		else delete c[key];
		store('collapsed', JSON.stringify(c));
		return val;
	}

	/** Projeler varsayılan kapalı; yalnız açılanlar ve o an açık listenin/dokümanın projesi açık. */
	function spaceOpen(id, val) {
		var c = {};
		try { c = JSON.parse(store('sp-open') || '{}'); } catch (e) { c = {}; }
		if (val === undefined) return !!c[id];
		if (val) c[id] = 1;
		else delete c[id];
		store('sp-open', JSON.stringify(c));
		return val;
	}
	var autoBase = null;
	/** Yeni bir sayfaya gelince o sayfanın projesi (ve klasörü) açılır; sonra kullanıcı kapatabilir. */
	function autoOpen(r) {
		if (!r || r.base === autoBase) return;
		autoBase = r.base;
		var obj = r.page === 'list' ? Y.list(r.id) : r.page === 'doc' ? Y.doc(r.id) : null;
		var sid = r.page === 'space' ? r.id : obj ? obj.space_id : 0;
		if (sid) spaceOpen(sid, true);
		if (obj && obj.folder_id) collapsed('f' + obj.folder_id, false);
	}

	function navItem(hash, icon, label, extra, active) {
		return h('a.flxg-nav-item' + (active ? '.is-active' : ''), { href: hash }, Y.icon(icon, 17), h('span.flxg-nav-label', label), extra || null);
	}

	function renderSide() {
		if (!R.side) return;
		var r = Y.route || {};
		Y.clear(R.side);
		var brand = h('.flxg-brand',
			B.icon ? h('img.flxg-brand-logo', { src: B.icon, alt: '' }) : h('span.flxg-brand-mark', Y.icon('check', 16)),
			h('.flxg-brand-text', h('b', t('Görevler')), h('span', B.brand)),
			h('button.flxg-icon-btn.flxg-only-mobile', { type: 'button', 'aria-label': t('Kapat'), onclick: closeNav }, Y.icon('x', 18)),
			h('button.flxg-icon-btn.is-sm.flxg-side-hide', { type: 'button', title: t('Kenar menüyü gizle'), 'aria-label': t('Kenar menüyü gizle'), onclick: function () { setSideCollapsed(true); } }, Y.icon('sidebar', 17))
		);
		R.side.appendChild(brand);
		var nav = h('nav.flxg-nav');
		nav.appendChild(navItem('#/home', 'home', t('Benim görevlerim'), null, r.page === 'home'));
		nav.appendChild(navItem('#/inbox', 'inbox', t('Bildirimler'), S.unread ? h('span.flxg-count', S.unread > 99 ? '99+' : S.unread) : null, r.page === 'inbox'));
		if (S.me.can.ai) nav.appendChild(h('button.flxg-nav-item', { type: 'button', onclick: function () { closeNav(); Y.openAI(); } }, Y.icon('sparkle', 17), h('span.flxg-nav-label', t('Yapay zekâ asistanı'))));
		if (S.me.can.manage) {
			nav.appendChild(navItem('#/dashboard', 'chart', t('Kullanım panosu'), null, r.page === 'dashboard'));
			nav.appendChild(navItem('#/settings', 'settings', t('Ayarlar'), null, r.page === 'settings'));
		}
		R.side.appendChild(nav);

		var tree = h('.flxg-tree');
		tree.appendChild(h('.flxg-tree-head', h('span', t('Projeler')), S.me.can.create ? h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Yeni proje'), onclick: function () { Y.spaceModal(); } }, Y.icon('plus', 15)) : null));
		autoOpen(r);
		var canSort = S.spaces.length > 1;
		S.spaces.forEach(function (sp) {
			var isCol = !spaceOpen(sp.id);
			var spActive = r.page === 'space' && r.id === sp.id;
			var canMake = S.me.can.create && Y.rank(sp.role) >= 3;
			var spEl = h('.flxg-space' + (isCol ? '.is-collapsed' : ''), { 'data-space': sp.id });
			var spRow = h('.flxg-tree-row.is-space' + (spActive ? '.is-active' : ''),
				h('button.flxg-caret', { type: 'button', 'aria-label': t('Daralt'), onclick: function () { spaceOpen(sp.id, isCol); renderSide(); } }, Y.icon(isCol ? 'chevR' : 'chevD', 14)),
				h('a.flxg-tree-link', { href: '#/space/' + sp.id }, h('span.flxg-space-ic', { style: { background: sp.color } }, sp.name.slice(0, 1).toUpperCase()), h('span.flxg-tree-name', sp.name), sp.access === 'members' ? Y.icon('lock', 12, 'flxg-lock') : null),
				canMake || Y.rank(sp.role) >= 4 ? h('button.flxg-icon-btn.is-sm.flxg-tree-more', { type: 'button', 'aria-label': t('Proje menüsü'), onclick: function (e) { spaceMenu(e.currentTarget, sp); } }, Y.icon('dots', 15)) : null
			);
			spEl.appendChild(spRow);
			treeDrop(spRow, { space: sp, folder: 0 });
			if (canSort && Y.rank(sp.role) >= 4) treeDrag(spRow, { space: sp });
			if (!isCol) {
				var kids = h('.flxg-tree-kids');
				S.folders.filter(function (f) { return f.space_id === sp.id; }).forEach(function (f) {
					var fk = 'f' + f.id;
					var fCol = collapsed(fk);
					var lists = S.lists.filter(function (l) { return l.folder_id === f.id; });
					var fEl = h('.flxg-folder');
					var fRow = h('.flxg-tree-row.is-folder',
						h('button.flxg-caret', { type: 'button', onclick: function () { collapsed(fk, !fCol); renderSide(); } }, Y.icon(fCol ? 'chevR' : 'chevD', 14)),
						h('span.flxg-tree-link', { onclick: function () { collapsed(fk, !fCol); renderSide(); } }, Y.icon('folder', 15), h('span.flxg-tree-name', f.name)),
						canMake ? h('button.flxg-icon-btn.is-sm.flxg-tree-more', { type: 'button', onclick: function (e) { folderMenu(e.currentTarget, f, sp); } }, Y.icon('dots', 15)) : null
					);
					fEl.appendChild(fRow);
					treeDrop(fRow, { space: sp, folder: f.id });
					if (!fCol) {
						var fk2 = h('.flxg-tree-kids');
						var fdocs = (S.docs || []).filter(function (d) { return d.folder_id === f.id; });
						lists.forEach(function (l) { fk2.appendChild(listRow(l, r)); });
						fdocs.forEach(function (d) { fk2.appendChild(docRow(d, r)); });
						if (!lists.length && !fdocs.length) fk2.appendChild(h('.flxg-tree-empty', t('Boş klasör')));
						fEl.appendChild(fk2);
					}
					kids.appendChild(fEl);
				});
				S.lists.filter(function (l) { return l.space_id === sp.id && !l.folder_id; }).forEach(function (l) { kids.appendChild(listRow(l, r)); });
				(S.docs || []).filter(function (d) { return d.space_id === sp.id && (!d.folder_id || !Y.folder(d.folder_id)); }).forEach(function (d) { kids.appendChild(docRow(d, r)); });
				if (canMake) kids.appendChild(h('button.flxg-tree-add', { type: 'button', onclick: function (e) { addMenu(e.currentTarget, sp.id, 0); } }, Y.icon('plus', 14), t('Ekle')));
				spEl.appendChild(kids);
			}
			tree.appendChild(spEl);
		});
		if (!S.spaces.length) tree.appendChild(h('.flxg-tree-empty', t('Görebildiğiniz proje yok.')));
		R.side.appendChild(tree);

		// Alt: kullanıcı.
		var mode = store('theme') || 'auto';
		R.side.appendChild(h('.flxg-side-foot',
			h('a.flxg-me' + (r.page === 'profile' ? '.is-active' : ''), { href: '#/profile' }, Y.avatar(S.me, 28), h('span', S.me.name)),
			h('button.flxg-icon-btn', { type: 'button', title: t('Tema'), onclick: function (e) {
				Y.menu(e.currentTarget, [
					{ label: t('Sistem'), checked: mode === 'auto', onClick: function () { store('theme', 'auto'); Y.applyTheme(); renderSide(); } },
					{ label: t('Açık'), icon: 'sun', checked: mode === 'light', onClick: function () { store('theme', 'light'); Y.applyTheme(); renderSide(); } },
					{ label: t('Koyu'), icon: 'moon', checked: mode === 'dark', onClick: function () { store('theme', 'dark'); Y.applyTheme(); renderSide(); } }
				]);
			} }, Y.icon(app.dataset.theme === 'dark' ? 'moon' : 'sun', 17)),
			h('a.flxg-icon-btn', { href: B.adminUrl, title: t('WordPress paneline dön') }, Y.icon('back', 17))
		));
		R.side.appendChild(sideResizer());
	}
	function listRow(l, r) {
		var active = r.page === 'list' && r.id === l.id;
		var row = h('.flxg-tree-row.is-list' + (active ? '.is-active' : ''),
			h('a.flxg-tree-link', { href: '#/list/' + l.id }, h('span.flxg-dot', { style: { background: l.color } }), h('span.flxg-tree-name', l.name), l.access === 'members' ? Y.icon('lock', 12, 'flxg-lock') : null),
			Y.rank(l.role) >= 4 ? h('button.flxg-icon-btn.is-sm.flxg-tree-more', { type: 'button', 'aria-label': t('Liste menüsü'), onclick: function (e) { listMenu(e.currentTarget, l); } }, Y.icon('dots', 15)) : null
		);
		if (Y.rank(l.role) >= 4) treeDrag(row, { list: l });
		return row;
	}

	/* ---------------- Sol ağaçta sürükle-bırak: proje sırası, listeyi projeye/klasöre taşı ---------------- */
	var DRAG = null;
	function treeDrag(row, info) {
		row.setAttribute('draggable', 'true');
		row.addEventListener('dragstart', function (e) {
			DRAG = info;
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData('text/plain', info.list ? 'list:' + info.list.id : 'space:' + info.space.id); } catch (er) { /* yok */ }
			row.classList.add('is-dragging');
			R.side.classList.add('is-tree-drag');
		});
		row.addEventListener('dragend', function () {
			DRAG = null;
			row.classList.remove('is-dragging');
			R.side.classList.remove('is-tree-drag');
			Y.$$('.is-drop-in,.is-drop-before,.is-drop-after', R.side).forEach(function (x) { x.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after'); });
		});
	}
	/** Proje satırı: proje sürükleniyorsa üstüne/altına (sıra), liste sürükleniyorsa içine (taşı). Klasör satırı: liste içine. */
	function treeDrop(row, target) {
		function where(e) {
			if (!DRAG) return '';
			if (DRAG.list) {
				var l = DRAG.list;
				if (l.space_id === target.space.id && (l.folder_id || 0) === target.folder) return '';
				return Y.rank(target.space.role) >= 3 ? 'in' : '';
			}
			if (!target.folder && DRAG.space && DRAG.space.id !== target.space.id) {
				var r = row.getBoundingClientRect();
				return e.clientY < r.top + r.height / 2 ? 'before' : 'after';
			}
			return '';
		}
		row.addEventListener('dragover', function (e) {
			var w = where(e);
			row.classList.toggle('is-drop-in', w === 'in');
			row.classList.toggle('is-drop-before', w === 'before');
			row.classList.toggle('is-drop-after', w === 'after');
			if (w) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
		});
		row.addEventListener('dragleave', function () { row.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after'); });
		row.addEventListener('drop', function (e) {
			var w = where(e);
			var d = DRAG;
			row.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after');
			if (!w || !d) return;
			e.preventDefault();
			if (d.list) { moveListDialog(d.list, target.space.id, target.folder); return; }
			var others = S.spaces.filter(function (x) { return x.id !== d.space.id; });
			var i = others.indexOf(target.space) + (w === 'after' ? 1 : 0);
			var pos = Y.between(i > 0 ? others[i - 1].position : null, i < others.length ? others[i].position : null);
			d.space.position = pos;
			S.spaces = others.slice(0, i).concat([d.space], others.slice(i));
			renderSide();
			Y.api('PATCH', 'spaces/' + d.space.id, { position: pos }, { quiet: true }).then(Y.applyStructure, function (er) { Y.fail(er); Y.loadBoot(); });
		});
	}

	/**
	 * Listeyi başka projeye ya da klasöre taşı. Proje değişiyorsa: durumlar istenirse hedefteki bir listenin durumlarına eşlenir,
	 * etiketler hedef projede aynı adla bağlanır, listeyi göremeyecek kişiler isteğe bağlı hedef projeye üye eklenir.
	 */
	function moveListDialog(l, toSpace, toFolder) {
		var sel = h('select.flxg-input');
		S.spaces.filter(function (sp) { return Y.rank(sp.role) >= 3; }).forEach(function (sp) {
			var g = h('optgroup', { label: sp.name });
			g.appendChild(h('option', { value: sp.id + ':0' }, sp.name));
			S.folders.filter(function (f) { return f.space_id === sp.id; }).forEach(function (f) { g.appendChild(h('option', { value: sp.id + ':' + f.id }, sp.name + ' › ' + f.name)); });
			sel.appendChild(g);
		});
		sel.value = (toSpace || l.space_id) + ':' + (toSpace ? toFolder || 0 : l.folder_id || 0);
		var extra = h('.flxg-move-extra');
		var st = { check: null, tmpl: 0, map: {}, add: {} };
		function target() { var p = sel.value.split(':'); return { space: +p[0], folder: +p[1] }; }
		function statusDot(s) { return h('span.flxg-dot', { style: { background: s.color } }); }
		function autoMap(tl) {
			st.map = {};
			// Önce aynı ad; yoksa aynı türdeki durumlar arasında sıradaki yerine en yakın olan.
			(l.statuses || []).forEach(function (s) {
				var same = tl.statuses.filter(function (x) { return Y.norm(x.label) === Y.norm(s.label); })[0];
				var src = l.statuses.filter(function (x) { return x.type === s.type; });
				var dst = tl.statuses.filter(function (x) { return x.type === s.type; });
				var byType = dst.length ? dst[src.length > 1 ? Math.round(src.indexOf(s) * (dst.length - 1) / (src.length - 1)) : 0] : null;
				st.map[s.key] = (same || byType || tl.statuses[0]).key;
			});
		}
		function draw() {
			Y.clear(extra);
			var tg = target();
			if (tg.space === l.space_id) { extra.appendChild(h('p.flxg-muted', t('Aynı proje içinde taşınır; durumlar, alanlar ve etiketler değişmez.'))); return; }
			if (!st.check) { extra.appendChild(h('.flxg-skel-line', Y.sk('70%', 12))); return; }
			// Durumlar
			var tls = S.lists.filter(function (x) { return x.space_id === tg.space && x.id !== l.id; });
			var mode = h('select.flxg-input', { onchange: function () { st.tmpl = +mode.value; if (st.tmpl) autoMap(Y.list(st.tmpl)); draw(); } },
				h('option', { value: '0' }, t('Listenin kendi durumlarını koru')),
				tls.map(function (x) { return h('option', { value: x.id }, t('"{n}" listesinin durumlarını kullan', { n: x.name })); }));
			mode.value = String(st.tmpl);
			var sec = h('.flxg-move-sec', h('h4', t('Durumlar')), mode);
			if (st.tmpl) {
				var tl = Y.list(st.tmpl);
				var tbl = h('.flxg-move-map');
				(l.statuses || []).forEach(function (s) {
					var dst = h('select.flxg-input', { onchange: function () { st.map[s.key] = dst.value; } }, tl.statuses.map(function (x) { return h('option', { value: x.key }, x.label); }));
					dst.value = st.map[s.key];
					tbl.appendChild(h('.flxg-move-row', h('span.flxg-move-src', statusDot(s), s.label), Y.icon('chevR', 14), dst));
				});
				sec.appendChild(h('p.flxg-muted', t('Her durumun karşılığını seçin; görevler bu duruma geçer.')));
				sec.appendChild(tbl);
			} else sec.appendChild(h('p.flxg-muted', t('Durumlar ve özel alanlar listeyle birlikte taşınır, görevler aynı durumda kalır.')));
			extra.appendChild(sec);
			// Etiketler
			if (st.check.tags.length) {
				var ex = st.check.tags.filter(function (x) { return x.exists; }).length;
				extra.appendChild(h('.flxg-move-sec', h('h4', t('Etiketler')),
					h('p.flxg-muted', t('{n} etiket hedef projede aynı adla bağlanır: {e} tanesi zaten var, {c} tanesi oluşturulur.', { n: st.check.tags.length, e: ex, c: st.check.tags.length - ex })),
					h('.flxg-move-tags', st.check.tags.map(function (x) { return h('span.flxg-tag', { style: { '--c': x.color } }, x.name + (x.exists ? '' : ' +')); }))));
			}
			// Kişiler
			if (st.check.people.length) {
				var ps = h('.flxg-move-sec', h('h4', t('Erişimi kalkacak kişiler')), h('p.flxg-muted', t('Bu kişiler hedef projenin üyesi değil; işaretlerseniz projeye üye eklenir.')));
				st.check.people.forEach(function (u) {
					var cb = h('input', { type: 'checkbox', checked: st.add[u.id] !== false, onchange: function () { st.add[u.id] = cb.checked; } });
					ps.appendChild(h('label.flxg-move-person', cb, Y.avatar(S.users[u.id] || { id: u.id, name: u.name }, 20), h('span', u.name)));
				});
				extra.appendChild(ps);
			}
		}
		var seq = 0;
		function check() {
			var tg = target();
			st.check = null;
			st.tmpl = 0;
			draw();
			if (tg.space === l.space_id) return;
			var my = ++seq;
			Y.api('GET', 'lists/' + l.id + '/move-check', { space: tg.space }).then(function (d) { if (my !== seq) return; st.check = d; draw(); }, Y.fail);
		}
		sel.addEventListener('change', check);
		var body = h('.flxg-move', h('label.flxg-field', h('span', t('Hedef proje ya da klasör')), sel), extra);
		Y.modal(t('"{n}" listesini taşı', { n: l.name }), body, { actions: [
			{ label: t('Vazgeç') },
			{ label: t('Taşı'), primary: true, onClick: function () {
				var tg = target();
				if (tg.space === l.space_id && tg.folder === (l.folder_id || 0)) return true;
				var cross = tg.space !== l.space_id;
				if (cross && !st.check) return false;
				var b = { space: tg.space, folder: tg.folder };
				if (cross && st.tmpl) { b.statuses_from = st.tmpl; b.status_map = st.map; }
				if (cross) b.add_members = st.check.people.filter(function (u) { return st.add[u.id] !== false; }).map(function (u) { return u.id; });
				return Y.api('POST', 'lists/' + l.id + '/move', b, { label: t('Liste taşınıyor') }).then(function (d) {
					Y.applyStructure(d);
					spaceOpen(tg.space, true);
					if (tg.folder) collapsed('f' + tg.folder, false);
					renderSide();
					Y.toast(t('"{n}" taşındı.', { n: l.name }));
					Y.emit('list-moved', l.id);
				});
			} }
		] });
		check();
	}

	function docRow(d, r) {
		var active = r.page === 'doc' && r.id === d.id;
		return h('.flxg-tree-row.is-list.is-doc' + (active ? '.is-active' : ''),
			h('a.flxg-tree-link', { href: '#/doc/' + d.id }, Y.icon('doc', 15, 'flxg-doc-tic'), h('span.flxg-tree-name', d.title)),
			Y.rank(d.role) >= 3 ? h('button.flxg-icon-btn.is-sm.flxg-tree-more', { type: 'button', 'aria-label': t('Doküman menüsü'), onclick: function (e) { Y.docMenu(e.currentTarget, d); } }, Y.icon('dots', 15)) : null
		);
	}
	/** Projeye / klasöre ekle: görev listesi, toplantı listesi, doküman. */
	function addMenu(anchor, spaceId, folderId) {
		Y.menu(anchor, [
			{ label: t('Görev listesi'), icon: 'list', onClick: function () { Y.listModal(spaceId, folderId, 'task'); } },
			{ label: t('Toplantı listesi'), icon: 'meeting', onClick: function () { Y.listModal(spaceId, folderId, 'meeting'); } },
			{ label: t('Doküman'), icon: 'doc', onClick: function () { Y.newDoc(spaceId, folderId); } }
		]);
	}
	function spaceMenu(anchor, sp) {
		var make = S.me.can.create && Y.rank(sp.role) >= 3;
		Y.menu(anchor, [
			make ? { label: t('Yeni liste'), icon: 'list', onClick: function () { Y.listModal(sp.id, 0); } } : null,
			make ? { label: t('Yeni toplantı listesi'), icon: 'meeting', onClick: function () { Y.listModal(sp.id, 0, 'meeting'); } } : null,
			make ? { label: t('Yeni doküman'), icon: 'doc', onClick: function () { Y.newDoc(sp.id, 0); } } : null,
			make ? { label: t('Yeni klasör'), icon: 'folder', onClick: function () {
				Y.prompt(t('Klasör adı'), '', t('Oluştur')).then(function (n) {
					if (n) Y.api('POST', 'folders', { space: sp.id, name: n }).then(Y.applyStructure, Y.fail);
				});
			} } : null,
			Y.rank(sp.role) >= 4 ? { sep: true } : null,
			Y.rank(sp.role) >= 4 ? { label: t('Proje ayarları ve üyeler'), icon: 'settings', onClick: function () { Y.spaceModal(sp); } } : null,
			Y.rank(sp.role) >= 4 ? { label: t('Projeyi sil'), icon: 'trash', danger: true, onClick: function () {
				Y.confirm(t('Projeyi sil'), t('"{n}" projesindeki tüm listeler, görevler, yorumlar ve dosyalar kalıcı olarak silinecek.', { n: sp.name }), t('Sil'), true).then(function (ok) {
					if (ok) Y.api('DELETE', 'spaces/' + sp.id).then(function (d) { Y.applyStructure(d); Y.go(defaultHash()); }, Y.fail);
				});
			} } : null
		]);
	}
	function folderMenu(anchor, f, sp) {
		Y.menu(anchor, [
			{ label: t('Bu klasöre liste ekle'), icon: 'plus', onClick: function () { Y.listModal(sp.id, f.id); } },
			{ label: t('Bu klasöre toplantı listesi ekle'), icon: 'meeting', onClick: function () { Y.listModal(sp.id, f.id, 'meeting'); } },
			{ label: t('Bu klasöre doküman ekle'), icon: 'doc', onClick: function () { Y.newDoc(sp.id, f.id); } },
			{ label: t('Yeniden adlandır'), icon: 'edit', onClick: function () {
				Y.prompt(t('Klasör adı'), f.name).then(function (n) { if (n) Y.api('PATCH', 'folders/' + f.id, { name: n }).then(Y.applyStructure, Y.fail); });
			} },
			Y.rank(sp.role) >= 4 ? { label: t('Klasörü sil'), icon: 'trash', danger: true, onClick: function () {
				Y.confirm(t('Klasörü sil'), t('Klasör silinir; içindeki listeler projenin altına taşınır.'), t('Sil'), true).then(function (ok) {
					if (ok) Y.api('DELETE', 'folders/' + f.id).then(Y.applyStructure, Y.fail);
				});
			} } : null
		]);
	}
	function listMenu(anchor, l) {
		Y.menu(anchor, [
			{ label: t('Liste ayarları'), icon: 'settings', onClick: function () { Y.listSettings(l.id, 'general'); } },
			{ label: t('Durumlar'), icon: 'check', onClick: function () { Y.listSettings(l.id, 'statuses'); } },
			{ label: t('Özel alanlar'), icon: 'columns', onClick: function () { Y.listSettings(l.id, 'fields'); } },
			{ label: t('Yetkiler'), icon: 'lock', onClick: function () { Y.listSettings(l.id, 'access'); } },
			{ label: t('Taşı…'), icon: 'move', onClick: function () { moveListDialog(l); } },
			{ sep: true },
			{ label: t('Listeyi sil'), icon: 'trash', danger: true, onClick: function () {
				Y.confirm(t('Listeyi sil'), t('"{n}" listesindeki tüm görevler, yorumlar ve dosyalar kalıcı olarak silinecek.', { n: l.name }), t('Sil'), true).then(function (ok) {
					if (ok) Y.api('DELETE', 'lists/' + l.id).then(function (d) { Y.applyStructure(d); if (S.scope && S.scope.type === 'list' && S.scope.id === l.id) Y.go(defaultHash()); }, Y.fail);
				});
			} }
		]);
	}

	/* =====================================================================
	 * Görev sayfası (liste / proje / benim görevlerim)
	 * ================================================================== */
	Y.topBar = topBar;
	function topBar(title, extra) {
		return h('header.flxg-top',
			h('button.flxg-icon-btn.flxg-menu-btn', { type: 'button', title: t('Kenar menüyü göster'), 'aria-label': t('Menü'), onclick: Y.toggleSide }, Y.icon(Y.isMobile() ? 'menu' : 'sidebar', 20)),
			h('.flxg-top-title', title),
			h('.flxg-top-actions', extra,
				h('a.flxg-icon-btn.flxg-only-mobile.flxg-bell', { href: '#/inbox', 'aria-label': t('Bildirimler') }, Y.icon('bell', 19), S.unread ? h('span.flxg-dotbadge', S.unread > 9 ? '9+' : S.unread) : null)
			)
		);
	}

	function scopeTitle() {
		var sc = S.scope;
		if (sc.type === 'home') return h('.flxg-crumbs', h('h1', t('Benim görevlerim')));
		if (sc.type === 'space') {
			var sp = Y.space(sc.id);
			return h('.flxg-crumbs', h('span.flxg-space-ic', { style: { background: sp.color } }, sp.name.slice(0, 1).toUpperCase()), h('h1', sp.name), h('span.flxg-muted.flxg-hide-sm', t('Tüm listeler')));
		}
		var l = Y.list(sc.id);
		var s2 = Y.space(l.space_id);
		var f = l.folder_id ? Y.folder(l.folder_id) : null;
		return h('.flxg-crumbs',
			s2 ? h('a.flxg-crumb.flxg-hide-sm', { href: '#/space/' + s2.id }, s2.name) : null,
			s2 ? h('span.flxg-crumb-sep.flxg-hide-sm', '/') : null,
			f ? h('span.flxg-crumb.flxg-hide-sm', f.name) : null,
			f ? h('span.flxg-crumb-sep.flxg-hide-sm', '/') : null,
			h('span.flxg-dot.is-lg', { style: { background: l.color } }),
			h('h1', l.name),
			l.access === 'members' ? h('span.flxg-pill-sm', { title: t('Yalnız üyeler görür') }, Y.icon('lock', 12), t('Özel')) : null
		);
	}

	function renderScopePage() {
		Y.clear(R.main);
		var sc = S.scope;
		var extra = [];
		if (sc.type === 'list' && Y.rank(sc.role) >= 4) {
			extra.push(h('button.flxg-btn.is-ghost.is-sm.flxg-hide-sm', { type: 'button', onclick: function () { Y.listSettings(sc.id, 'access'); } }, Y.icon('users', 15), t('Paylaş')));
			extra.push(h('button.flxg-icon-btn', { type: 'button', title: t('Liste ayarları'), onclick: function () { Y.listSettings(sc.id, 'general'); } }, Y.icon('settings', 18)));
		}
		if (S.me.can.ai) extra.push(h('button.flxg-icon-btn.is-ai', { type: 'button', title: t('Yapay zekâ asistanı'), onclick: function () { Y.openAI(); } }, Y.icon('sparkle', 18)));
		R.main.appendChild(topBar(scopeTitle(), extra));
		R.viewbar = h('nav.flxg-viewbar');
		R.toolbar = h('.flxg-toolbar');
		R.content = h('.flxg-content');
		R.main.appendChild(R.viewbar);
		R.main.appendChild(R.toolbar);
		R.main.appendChild(R.content);
		renderViewbar();
		Y.renderToolbar(R.toolbar);
		renderContent();
	}

	function viewIcon(type) { return type === 'board' ? 'board' : type === 'calendar' ? 'calendar' : 'list'; }

	function renderViewbar() {
		Y.clear(R.viewbar);
		var tabs = h('.flxg-tabs');
		S.views.forEach(function (v) {
			var on = S.view && S.view.id === v.id;
			tabs.appendChild(h('a.flxg-tab' + (on ? '.is-on' : ''), { href: Y.scopeHash() + '/v/' + v.id }, Y.icon(viewIcon(v.type), 15), h('span', v.name), v.user_id ? Y.icon('lock', 11, 'flxg-lock') : null,
				on ? h('button.flxg-tab-more', { type: 'button', 'aria-label': t('Görünüm menüsü'), onclick: function (e) { e.preventDefault(); e.stopPropagation(); viewMenu(e.currentTarget, v); } }, Y.icon('chevD', 13)) : null));
		});
		tabs.appendChild(h('button.flxg-tab.is-add', { type: 'button', onclick: function (e) { addView(e.currentTarget); } }, Y.icon('plus', 15), h('span', t('Görünüm'))));
		R.viewbar.appendChild(tabs);
	}

	function canEditView(v) {
		if (v.user_id) return v.user_id === S.me.id;
		return Y.rank(S.scope.role) >= 3;
	}
	function viewMenu(anchor, v) {
		var editable = canEditView(v);
		Y.menu(anchor, [
			editable ? { label: t('Yeniden adlandır'), icon: 'edit', onClick: function () {
				Y.prompt(t('Görünüm adı'), v.name).then(function (n) { if (n) Y.api('PATCH', 'views/' + v.id, { name: n }).then(function (r) { v.name = r.view.name; renderViewbar(); }, Y.fail); });
			} } : null,
			{ label: t('Kopyasını oluştur'), icon: 'copy', onClick: function () {
				Y.api('POST', 'views', { scope: S.scope.type, scope_id: S.scope.type === 'home' ? S.me.id : S.scope.id, name: v.name + ' 2', type: v.type, config: S.draft || v.config, shared: Y.rank(S.scope.role) >= 3 && S.scope.type !== 'home' }).then(function (r) { S.views = r.views; Y.go(Y.scopeHash() + '/v/' + r.view.id); }, Y.fail);
			} },
			editable && S.scope.type !== 'home' && Y.rank(S.scope.role) >= 3 ? { label: v.user_id ? t('Herkesle paylaş') : t('Yalnız bana özel yap'), icon: v.user_id ? 'users' : 'lock', onClick: function () {
				Y.api('PATCH', 'views/' + v.id, { shared: !!v.user_id }).then(function (r) { v.user_id = r.view.user_id; renderViewbar(); }, Y.fail);
			} } : null,
			editable && S.views.length > 1 ? { sep: true } : null,
			editable && S.views.length > 1 ? { label: t('Görünümü sil'), icon: 'trash', danger: true, onClick: function () {
				Y.confirm(t('Görünümü sil'), t('"{n}" görünümü silinecek. Görevler etkilenmez.', { n: v.name }), t('Sil'), true).then(function (ok) {
					if (ok) Y.api('DELETE', 'views/' + v.id).then(function (r) { S.views = r.views; Y.go(Y.scopeHash()); }, Y.fail);
				});
			} } : null
		]);
	}
	function addView(anchor) {
		var mk = function (type, name) {
			var shared = S.scope.type !== 'home' && Y.rank(S.scope.role) >= 3;
			Y.api('POST', 'views', { scope: S.scope.type, scope_id: S.scope.type === 'home' ? S.me.id : S.scope.id, name: name, type: type, shared: shared, config: { fields: ['assignees', 'due', 'priority', 'tags'], cover: type !== 'list', group: type === 'calendar' ? 'none' : 'status' } }).then(function (r) {
				S.views = r.views;
				Y.go(Y.scopeHash() + '/v/' + r.view.id);
			}, Y.fail);
		};
		Y.menu(anchor, [
			{ head: t('Yeni görünüm') },
			{ label: t('Liste'), icon: 'list', onClick: function () { mk('list', t('Liste')); } },
			{ label: t('Pano'), icon: 'board', onClick: function () { mk('board', t('Pano')); } },
			{ label: t('Takvim'), icon: 'calendar', onClick: function () { mk('calendar', t('Takvim')); } }
		]);
	}

	var raf = 0;
	function renderContent() {
		if (!R.content || !S.scope || !document.body.contains(R.content)) return;
		cancelAnimationFrame(raf);
		raf = requestAnimationFrame(function () {
			var v = S.view;
			var sc = R.content.scrollTop;
			var board = R.content.querySelector('.flxg-board');
			var bsc = board ? board.scrollLeft : 0;
			R.content.className = 'flxg-content is-' + (v ? v.type : 'list');
			if (!v) { Y.clear(R.content).appendChild(Y.emptyState()); return; }
			if (v.type === 'board') Y.renderBoard(R.content);
			else if (v.type === 'calendar') Y.renderCalendar(R.content);
			else Y.renderList(R.content);
			R.content.scrollTop = sc;
			var nb = R.content.querySelector('.flxg-board');
			if (nb) nb.scrollLeft = bsc;
		});
	}
	Y.on('tasks', renderContent);
	Y.on('view-data', renderContent);
	Y.on('view', function () { if (S.scope && R.toolbar) { Y.renderToolbar(R.toolbar); renderContent(); } });
	Y.on('structure', function () {
		renderSide();
		if (S.scope && S.scope.type === 'list' && !Y.list(S.scope.id)) return;
		if (S.scope && R.main && R.main.querySelector('.flxg-top-title')) {
			var tt = R.main.querySelector('.flxg-top-title');
			Y.clear(tt).appendChild(scopeTitle());
			renderContent();
		}
	});
	Y.on('unread', function () {
		renderSide();
		var bell = Y.$('.flxg-bell');
		if (bell) {
			var b = bell.querySelector('.flxg-dotbadge');
			if (b) b.remove();
			if (S.unread) bell.appendChild(h('span.flxg-dotbadge', S.unread > 9 ? '9+' : S.unread));
		}
		document.title = (S.unread ? '(' + S.unread + ') ' : '') + t('Görevler') + ' ‹ ' + B.brand;
	});
	mqWatch();
	function mqWatch() {
		window.matchMedia('(max-width: 720px)').addEventListener('change', function () { renderContent(); if (Y.D.task) Y.reloadTask(); });
	}

	/* =====================================================================
	 * Diğer sayfalar
	 * ================================================================== */
	function renderOtherPage(page) {
		Y.clear(R.main);
		if (page === 'inbox') return inboxPage();
		if (page === 'dashboard' && S.me.can.manage) return dashboardPage();
		if (page === 'settings' && S.me.can.manage) return settingsPage();
		if (page === 'profile') return profilePage();
		Y.go(defaultHash(), true);
	}

	Y.notifText = function (n) {
		var who = n.actor ? (S.users[n.actor] ? S.users[n.actor].name : t('Biri')) : '';
		switch (n.type) {
			case 'assigned': return t('{n} sizi bir göreve atadı', { n: who });
			case 'mention': return t('{n} bir yorumda sizden bahsetti', { n: who });
			case 'comment': return t('{n} yorum yazdı', { n: who });
			case 'status': return t('{n} durumu "{s}" yaptı', { n: who, s: (n.data && n.data.label) || '' });
			case 'due': return t('Bitiş tarihi yarın');
			case 'overdue': return t('Bitiş tarihi geçti');
		}
		return n.type;
	};
	var notifIcon = { assigned: 'user', mention: 'at', comment: 'msg', status: 'check', due: 'clock', overdue: 'clock' };

	function inboxPage() {
		var filter = store('inbox-filter') || 'all';
		var items = [];
		var more = true;
		var list = h('.flxg-inbox-list');
		R.main.appendChild(topBar(h('.flxg-crumbs', h('h1', t('Bildirimler'))), [
			h('.flxg-seg', [['all', t('Tümü')], ['unread', t('Okunmamış')]].map(function (o) {
				return h('button' + (filter === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { store('inbox-filter', o[0]); renderOtherPage('inbox'); } }, o[1]);
			})),
			h('button.flxg-btn.is-sm', { type: 'button', onclick: function () {
				Y.api('POST', 'notifications/read', { all: true }).then(function (r) { S.unread = r.unread; Y.emit('unread'); items.forEach(function (n) { n.read = true; }); draw(); }, Y.fail);
			} }, Y.icon('check', 14), h('span.flxg-hide-sm', t('Tümünü okundu say')))
		]));
		var wrap = h('.flxg-content.is-page', h('.flxg-page-inner', list));
		R.main.appendChild(wrap);
		function fetchMore() {
			var before = items.length ? items[items.length - 1].id : 0;
			return Y.api('GET', 'notifications', { before: before || '', unread: filter === 'unread' ? 1 : '' }).then(function (r) {
				items = items.concat(r.items);
				more = r.items.length >= 30;
				if (items.length) S.lastNotif = Math.max(S.lastNotif, items[0].id);
				S.unread = r.unread;
				Y.emit('unread');
				draw();
			}, Y.fail);
		}
		function draw() {
			Y.clear(list);
			if (!items.length) { list.appendChild(h('.flxg-empty', h('.flxg-empty-ic', Y.icon('bell', 34)), h('p', filter === 'unread' ? t('Okunmamış bildirim yok. 🎉') : t('Henüz bildirim yok.')))); return; }
			var lastDay = '';
			items.forEach(function (n) {
				var d = new Date(n.created);
				var dk = Y.dayKey(d);
				if (dk !== lastDay) {
					lastDay = dk;
					var today = Y.dayKey(new Date());
					list.appendChild(h('.flxg-inbox-day', dk === today ? t('Bugün') : dk === Y.addDays(today, -1) ? t('Dün') : d.toLocaleDateString(Y.locale, { dateStyle: 'medium' })));
				}
				var l = Y.list(n.list);
				list.appendChild(h('button.flxg-notif' + (n.read ? '' : '.is-unread'), { type: 'button', onclick: function () {
					if (!n.read) {
						n.read = true;
						Y.api('POST', 'notifications/read', { ids: [n.id] }).then(function (r) { S.unread = r.unread; Y.emit('unread'); });
					}
					Y.go('#/inbox/t/' + n.task);
					draw();
				} },
				n.actor ? Y.avatar(S.users[n.actor], 34) : h('span.flxg-notif-ic', Y.icon(notifIcon[n.type] || 'bell', 18)),
				h('.flxg-notif-main',
					h('.flxg-notif-line', h('span.flxg-notif-type', Y.icon(notifIcon[n.type] || 'bell', 13)), h('span', Y.notifText(n))),
					h('.flxg-notif-title', n.title || t('(silinmiş görev)')),
					n.data && n.data.excerpt ? h('.flxg-notif-ex', n.data.excerpt) : null,
					h('.flxg-notif-meta', l ? h('span', h('span.flxg-dot', { style: { background: l.color } }), l.name) : null, h('span', { title: Y.fullTime(n.created) }, Y.ago(n.created)))
				),
				n.read ? null : h('span.flxg-unread-dot')));
			});
			if (more) list.appendChild(h('button.flxg-btn.flxg-more', { type: 'button', onclick: fetchMore }, t('Daha fazla')));
		}
		fetchMore();
	}

	/* ---------------- Pano (yönetici) ---------------- */
	function kpi(label, value, sub, tone) {
		return h('.flxg-kpi' + (tone ? '.is-' + tone : ''), h('span.flxg-kpi-label', label), h('b.flxg-kpi-val', value), sub ? h('span.flxg-kpi-sub', sub) : null);
	}
	function bar(v, max, color) {
		return h('span.flxg-bar', h('span', { style: { width: (max ? Math.max(1, Math.round((v / max) * 100)) : 0) + '%', background: color || 'var(--accent)' } }));
	}
	function dashboardPage() {
		R.main.appendChild(topBar(h('.flxg-crumbs', h('h1', t('Kullanım panosu'))), [h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { renderOtherPage('dashboard'); } }, t('Yenile'))]));
		var inner = h('.flxg-page-inner.is-wide', Y.skeleton('kpi'));
		R.main.appendChild(h('.flxg-content.is-page', inner));
		Y.api('GET', 'dashboard').then(function (d) {
			Y.clear(inner);
			var s = d.storage;
			var tk = d.tasks;
			inner.appendChild(h('.flxg-kpis',
				kpi(t('Dosya depolama'), Y.bytes(s.total), t('{n} dosya', { n: Y.num(s.count) }) + (s.thumbs ? ' · ' + t('önizlemeler {s}', { s: Y.bytes(s.thumbs) }) : '')),
				kpi(t('Açık görev'), Y.num(tk.open), t('{n} tamamlandı', { n: Y.num(tk.done) })),
				kpi(t('Geciken'), Y.num(tk.overdue), t('bitiş tarihi geçmiş açık görev'), tk.overdue ? 'warn' : ''),
				kpi(t('Son 30 gün'), '+' + Y.num(tk.created30), t('{n} görev tamamlandı', { n: Y.num(tk.completed30) })),
				kpi(t('Yorum'), Y.num(tk.comments), t('{n} aktif kişi (30 gün)', { n: Y.num(tk.active30) })),
				kpi(t('Yapı'), Y.num(tk.spaces) + ' / ' + Y.num(tk.lists), t('proje / liste · {n} kullanıcı', { n: Y.num(tk.users) }))
			));
			// Türlere göre depolama.
			var kinds = { image: [t('Görsel'), '#22c55e'], pdf: [t('PDF'), '#ef4444'], doc: [t('Belge'), '#3b82f6'], video: [t('Video'), '#a855f7'], audio: [t('Ses'), '#f59e0b'], archive: [t('Arşiv'), '#64748b'], other: [t('Diğer'), '#94a3b8'] };
			var stack = h('.flxg-stack');
			var legend = h('.flxg-legend');
			Object.keys(kinds).forEach(function (k) {
				var v = s.kinds[k];
				if (!v) return;
				stack.appendChild(h('span', { style: { width: (s.total ? (v.size / s.total) * 100 : 0) + '%', background: kinds[k][1] }, title: kinds[k][0] + ': ' + Y.bytes(v.size) }));
				legend.appendChild(h('span.flxg-legend-item', h('span.flxg-dot', { style: { background: kinds[k][1] } }), kinds[k][0], h('b', Y.bytes(v.size)), h('span.flxg-muted', '(' + Y.num(v.count) + ')')));
			});
			var up = s.uploads;
			inner.appendChild(h('section.flxg-card-box',
				h('h2', t('Depolama')),
				s.total ? stack : h('p.flxg-muted', t('Henüz dosya yüklenmemiş.')),
				legend,
				h('.flxg-dl',
					h('div', h('span', t('Görev dosyaları (uploads/flx-tasks)')), h('b', Y.bytes(s.total + s.thumbs))),
					s.pending.count ? h('div', h('span', t('Gönderilmemiş yorum ekleri (24 saat sonra silinir)')), h('b', Y.bytes(s.pending.size) + ' · ' + Y.num(s.pending.count))) : null,
					h('div', h('span', t('Tek dosya sınırı')), h('b', Y.bytes(s.limit))),
					s.free !== null ? h('div', h('span', t('Sunucuda boş alan')), h('b', Y.bytes(s.free))) : null,
					h('div', h('span', t('Tüm medya klasörü (wp-content/uploads)')), h('b', up ? Y.bytes(up.size) + ' · ' + t('{n} dosya', { n: Y.num(up.count) }) + (up.complete ? '' : ' +') : '—'),
						h('button.flxg-btn.is-sm', { type: 'button', onclick: function (e) {
							e.currentTarget.disabled = true;
							e.currentTarget.textContent = t('Hesaplanıyor…');
							Y.api('POST', 'dashboard/scan').then(function () { renderOtherPage('dashboard'); }, Y.fail);
						} }, up ? t('Yeniden hesapla') : t('Hesapla')))
				)
			));
			// Listeler.
			var maxL = Math.max.apply(null, d.lists.map(function (x) { return x.size; }).concat([1]));
			inner.appendChild(h('section.flxg-card-box',
				h('h2', t('Listelere göre')),
				h('.flxg-table-wrap', h('table.flxg-table',
					h('thead', h('tr', h('th', t('Liste')), h('th', t('Proje')), h('th.is-num', t('Görev')), h('th.is-num', t('Dosya')), h('th.is-num', t('Boyut')), h('th', ''))),
					h('tbody', d.lists.map(function (x) {
						return h('tr', h('td', h('a', { href: '#/list/' + x.id }, x.name)), h('td.flxg-muted', x.space), h('td.is-num', Y.num(x.tasks)), h('td.is-num', Y.num(x.files)), h('td.is-num', Y.bytes(x.size)), h('td.is-bar', bar(x.size, maxL)));
					}))
				))
			));
			// Kullanıcılar ve en büyük dosyalar.
			var maxU = Math.max.apply(null, d.users.map(function (x) { return x.size; }).concat([1]));
			inner.appendChild(h('.flxg-two',
				h('section.flxg-card-box',
					h('h2', t('Kişilere göre yüklenen')),
					d.users.length ? h('.flxg-table-wrap', h('table.flxg-table', h('tbody', d.users.map(function (x) {
						return h('tr', h('td', h('span.flxg-person', Y.avatar(S.users[x.user], 22), x.name)), h('td.is-num', Y.num(x.count)), h('td.is-num', Y.bytes(x.size)), h('td.is-bar', bar(x.size, maxU)));
					})))) : h('p.flxg-muted', t('Veri yok.'))
				),
				h('section.flxg-card-box',
					h('h2', t('Aylık yükleme')),
					monthBars(s.months)
				)
			));
			inner.appendChild(h('section.flxg-card-box',
				h('h2', t('En büyük dosyalar')),
				d.largest.length ? h('.flxg-table-wrap', h('table.flxg-table',
					h('thead', h('tr', h('th', t('Dosya')), h('th', t('Görev')), h('th', t('Yükleyen')), h('th.is-num', t('Boyut')), h('th', ''))),
					h('tbody', d.largest.map(function (x) {
						return h('tr',
							h('td', h('a', { href: Y.fileUrl(x.id, '', true) }, x.name)),
							h('td', x.task ? h('a', { href: '#/task/' + x.task }, x.title || '#' + x.task) : h('span.flxg-muted', t('bağlanmamış')), x.list ? h('span.flxg-muted', ' · ' + x.list) : null),
							h('td.flxg-muted', x.user),
							h('td.is-num', Y.bytes(x.size)),
							h('td', h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Sil'), onclick: function () {
								Y.confirm(t('Dosyayı sil'), x.name + ' (' + Y.bytes(x.size) + ')', t('Sil'), true).then(function (ok) {
									if (ok) Y.api('DELETE', 'files/' + x.id).then(function () { renderOtherPage('dashboard'); }, Y.fail);
								});
							} }, Y.icon('trash', 15)))
						);
					}))
				)) : h('p.flxg-muted', t('Henüz dosya yüklenmemiş.'))
			));
			inner.appendChild(h('.flxg-two',
				h('section.flxg-card-box',
					h('h2', t('Veritabanı')),
					h('.flxg-table-wrap', h('table.flxg-table',
						h('thead', h('tr', h('th', t('Tablo')), h('th.is-num', t('Satır')), h('th.is-num', t('Boyut')))),
						h('tbody', d.db.tables.map(function (x) {
							return h('tr', h('td', h('code', 'flxg_' + x.name)), h('td.is-num', Y.num(x.rows)), h('td.is-num', x.size === null ? '—' : Y.bytes(x.size)));
						})),
						d.db.total !== null ? h('tfoot', h('tr', h('th', t('Toplam')), h('th', ''), h('th.is-num', Y.bytes(d.db.total)))) : null
					))
				),
				h('section.flxg-card-box',
					h('h2', t('Yapay zekâ kullanımı')),
					d.ai ? h('.flxg-dl',
						h('div', h('span', t('Ay')), h('b', d.ai.month)),
						h('div', h('span', t('İstek')), h('b', Y.num(d.ai.calls))),
						h('div', h('span', t('Token')), h('b', Y.num(d.ai.tokens))),
						h('div', h('span', t('Maliyet (OpenRouter)')), h('b', '$' + (d.ai.cost || 0).toFixed(4)))
					) : h('p.flxg-muted', t('Bu ay kullanım yok.'))
				)
			));
		}, function (e) { Y.clear(inner).appendChild(h('p.flxg-error', e.message)); });
	}
	function monthBars(months) {
		var keys = Object.keys(months || {});
		if (!keys.length) return h('p.flxg-muted', t('Veri yok.'));
		var max = Math.max.apply(null, keys.map(function (k) { return months[k]; }).concat([1]));
		return h('.flxg-vbars', keys.map(function (k) {
			var p = k.split('-');
			return h('.flxg-vbar', { title: Y.bytes(months[k]) }, h('span.flxg-vbar-v', Y.bytes(months[k])), h('span.flxg-vbar-b', { style: { height: Math.max(2, Math.round((months[k] / max) * 100)) + '%' } }), h('span.flxg-vbar-l', new Date(+p[0], +p[1] - 1, 1).toLocaleDateString(Y.locale, { month: 'short' })));
		}));
	}

	/* ---------------- Ayarlar (yönetici) ---------------- */
	function settingsPage() {
		R.main.appendChild(topBar(h('.flxg-crumbs', h('h1', t('Ayarlar')))));
		var inner = h('.flxg-page-inner', Y.skeleton('form'));
		R.main.appendChild(h('.flxg-content.is-page', inner));
		Y.api('GET', 'settings').then(function (d) { drawSettings(inner, d); }, function (e) { Y.clear(inner).appendChild(h('p.flxg-error', e.message)); });
	}
	function systemChecks(checks, refresh) {
		var order = { error: 0, warning: 1, info: 2, ok: 3 };
		var cnt = { ok: 0, warning: 0, error: 0, info: 0 };
		checks.forEach(function (c) { cnt[c.status] = (cnt[c.status] || 0) + 1; });
		var label = { ok: t('Tamam'), warning: t('Uyarı'), error: t('Hata'), info: t('Bilgi') };
		var sum = cnt.error ? t('{n} sorun var', { n: Y.num(cnt.error) }) : (cnt.warning ? t('{n} uyarı var', { n: Y.num(cnt.warning) }) : t('Her şey yerli yerinde'));
		var box = h('section.flxg-card-box.flxg-syscheck',
			h('.flxg-sys-head',
				h('.flxg-sys-badge.is-' + (cnt.error ? 'error' : cnt.warning ? 'warning' : 'ok'), Y.icon(cnt.error || cnt.warning ? 'alert' : 'check', 16)),
				h('div', h('h3', t('Sistem kontrolü')), h('p.flxg-muted', sum)),
				h('span.flxg-grow'),
				h('button.flxg-btn.is-sm', { type: 'button', onclick: refresh }, t('Yeniden kontrol et'))
			)
		);
		// Kaynak (eklenti/tema) ve gruba göre; sorunlu olanlar üstte.
		var bySrc = {};
		checks.forEach(function (c) { var k = c.source || ''; (bySrc[k] = bySrc[k] || []).push(c); });
		Object.keys(bySrc).forEach(function (src) {
			var list = bySrc[src].slice().sort(function (a, b) { return (order[a.status] - order[b.status]) || Y.cmpStr(a.group, b.group); });
			var bad = list.filter(function (c) { return c.status === 'error' || c.status === 'warning'; }).length;
			var det = h('details.flxg-sys-src', bad ? { open: true } : {},
				h('summary', h('b', src || t('Diğer')), h('span.flxg-muted', bad ? t('{n} konu', { n: Y.num(bad) }) : t('Tamam')))
			);
			list.forEach(function (c) {
				det.appendChild(h('.flxg-sys-row.is-' + c.status,
					h('span.flxg-sys-dot', { title: label[c.status] || c.status }),
					h('.flxg-sys-txt', h('b', c.label), c.group ? h('span.flxg-sys-group', c.group) : null, h('span.flxg-muted', c.message)),
					c.action_url && c.action_url.indexOf('page=flx-tasks-ayarlar') < 0 ? h('a.flxg-btn.is-sm.is-ghost', { href: c.action_url }, c.action_label || t('Aç')) : null
				));
			});
			box.appendChild(det);
		});
		return box;
	}
	function drawSettings(inner, d) {
		var s = d.settings;
		var vals = {};
		Y.clear(inner);
		function roleChecks(key) {
			return h('.flxg-checks', d.roles.map(function (r) {
				var on = (s[key] || []).indexOf(r.key) > -1 || r.key === 'administrator';
				return h('label.flxg-check-item', h('input', { type: 'checkbox', checked: on, disabled: r.key === 'administrator', onchange: function (e) {
					var arr = (vals[key] || s[key] || []).slice();
					if (e.target.checked) arr.push(r.key);
					else arr = arr.filter(function (x) { return x !== r.key; });
					vals[key] = arr;
				} }), h('span', r.name));
			}));
		}
		function sw(key, label, help) {
			return h('label.flxg-switch-row.is-setting', h('input', { type: 'checkbox', checked: !!s[key], onchange: function (e) { vals[key] = e.target.checked ? 1 : 0; } }), h('span', h('b', label), help ? h('small', help) : null));
		}
		function num(key, label, min, max, help) {
			return h('label.flxg-field', h('span', label), h('input.flxg-input', { type: 'number', min: min, max: max, value: s[key], onchange: function (e) { vals[key] = +e.target.value; } }), help ? h('small.flxg-help', help) : null);
		}
		// Yapay zekâ: anahtar ve model Flux AI'de; burada yalnız bu eklentiye özel model ve notlar.
		var modelSel = h('input.flxg-input', { type: 'text', value: s.ai_model || '', placeholder: s.ai_model_eff ? t('Flux AI varsayılanı: {m}', { m: s.ai_model_eff }) : t('Flux AI varsayılanı'), onchange: function (e) { vals.ai_model = e.target.value.trim(); } });
		modelSel.setAttribute('data-flxai-model', '');
		var usage = d.usage;
		// Sistem kontrolü: kurulumun yerli yerinde olduğunu gösterir (FluxWP eklentileri ve tema ortak listesi).
		if (d.checks && d.checks.length) inner.appendChild(systemChecks(d.checks, function () { renderOtherPage('settings'); }));
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('Erişim')),
			h('p.flxg-muted', t('Hangi WordPress rollerinin görevleri kullanabileceğini seçin. Yöneticiler her zaman tüm projeleri görür.')),
			h('h3', t('Görevleri kullanabilir')), roleChecks('roles_use'),
			h('h3', t('Proje, klasör ve liste oluşturabilir')), roleChecks('roles_create'),
			h('h3', t('Yapay zekâyı kullanabilir')), roleChecks('ai_roles')
		));
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('Bildirimler ve güncelleme')),
			num('poll_interval', t('Canlı güncelleme aralığı (saniye)'), 15, 600, t('Açık sekme bu aralıkla tek bir hafif istek atar; arka plandaki sekme hiç istek atmaz. Sunucu yoğunsa artırın (varsayılan 45).')),
			sw('email_notify', t('E-posta bildirimleri'), t('Atama, bahsetme ve yaklaşan bitiş tarihi için e-posta gönderilir. Kişiler profilinden kapatabilir.'))
		));
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('Dosyalar')),
			num('max_upload_mb', t('Tek dosya sınırı (MB)'), 1, 512, t('Sunucunun PHP sınırı daha düşükse o geçerli olur.'))
		));
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('Yapay zekâ')),
			s.ai_ready ? h('p.flxg-muted', t('Anahtarlar, harcama sınırı ve raporlar Flux AI eklentisinden gelir. Model alanını boş bırakırsanız Flux AI varsayılanı kullanılır.'), ' ', h('a', { href: s.ai_hub_url }, t('Flux AI ayarları')))
				: h('.flxg-notice.is-warning', Y.icon('alert', 16), h('span', s.ai_hub ? t('Yapay zekâ kapalı: Flux AI\'de anahtar ya da model eksik.') : t('Yapay zekâ kapalı: Flux AI eklentisi kurulu değil. Asistan ve hızlı işlemler Flux AI ile çalışır.')), ' ', h('a', { href: s.ai_hub_url }, s.ai_hub ? t('Flux AI ayarları') : t('Eklentiler'))),
			s.ai_hub ? h('label.flxg-field', h('span', t('Model (isteğe bağlı)')), modelSel) : null,
			h('label.flxg-field', h('span', t('Yaratıcılık (temperature)')), h('input.flxg-input', { type: 'number', step: '0.1', min: 0, max: 1.5, value: s.ai_temperature, onchange: function (e) { vals.ai_temperature = +e.target.value; } })),
			h('label.flxg-field', h('span', t('Kurum notları (asistan her istekte bunu bilir)')), h('textarea.flxg-input', { rows: 4, placeholder: t('Örn: Dernek İstanbul şubesi. Etkinlik görevlerine "Etkinlik" etiketi koy. Tarihleri gün.ay.yıl yaz.'), onchange: function (e) { vals.ai_instructions = e.target.value; } }, s.ai_instructions)),
			usage ? h('p.flxg-muted', t('Bu ay: {c} istek, {t} token, ${m}', { c: Y.num(usage.calls), t: Y.num(usage.tokens), m: (usage.cost || 0).toFixed(4) })) : null
		));
		if (s.ai_hub && window.FLXAIModelPicker) setTimeout(function () { window.FLXAIModelPicker.attach(modelSel); }, 0);
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('MCP sunucusu')),
			sw('mcp_enabled', t('MCP sunucusunu aç'), t('Claude, Cursor gibi yapay zekâ istemcileri görevleri okuyup yazabilir. Her kişi kendi anahtarını Profil sayfasından üretir.')),
			h('.flxg-copyrow', h('code', d.mcpUrl), h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { Y.copy(d.mcpUrl); } }, Y.icon('copy', 14), t('Kopyala'))),
			h('a.flxg-link-btn', { href: '#/profile' }, t('Anahtarları yönet'))
		));
		inner.appendChild(h('section.flxg-card-box',
			h('h2', t('Veri')),
			sw('delete_data', t('Eklenti silinince tüm görev verisini de sil'), t('Kapalıyken eklentiyi silmek verilere dokunmaz.'))
		));
		inner.appendChild(h('.flxg-savebar', h('button.flxg-btn.is-primary', { type: 'button', onclick: function (e) {
			var btn = e.currentTarget;
			btn.disabled = true;
			Y.api('POST', 'settings', vals).then(function (r) {
				btn.disabled = false;
				Y.toast(t('Ayarlar kaydedildi'));
				S.config.poll = Math.max(15, r.settings.poll_interval);
				Y.loadBoot();
				drawSettings(inner, r);
			}, function (er) { btn.disabled = false; Y.fail(er); });
		} }, t('Kaydet'))));
	}

	/* ---------------- Profil ---------------- */
	function profilePage() {
		R.main.appendChild(topBar(h('.flxg-crumbs', h('h1', t('Profil')))));
		var inner = h('.flxg-page-inner');
		R.main.appendChild(h('.flxg-content.is-page', inner));
		var mode = store('theme') || 'auto';
		inner.appendChild(h('section.flxg-card-box',
			h('.flxg-profile-head', Y.avatar(S.me, 48), h('div', h('b', S.me.name), h('span.flxg-muted', t('Profil fotoğrafı ve adı WordPress profilinden gelir.')))),
			h('h3', t('Tema')),
			h('.flxg-seg', [['auto', t('Sistem')], ['light', t('Açık')], ['dark', t('Koyu')]].map(function (o) {
				return h('button' + (mode === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { store('theme', o[0]); Y.applyTheme(); renderOtherPage('profile'); renderSide(); } }, o[1]);
			})),
			S.config.emailOn ? h('label.flxg-switch-row.is-setting', h('input', { type: 'checkbox', checked: S.me.email, onchange: function (e) {
				S.me.email = e.target.checked;
				Y.api('POST', 'me', { email: e.target.checked }).then(function () { Y.toast(t('Kaydedildi')); }, Y.fail);
			} }), h('span', h('b', t('E-posta bildirimleri')), h('small', t('Size atanınca, bahsedilince ve bitiş tarihi yaklaşınca e-posta alın.')))) : null,
			'Notification' in window ? h('label.flxg-switch-row.is-setting', h('input', { type: 'checkbox', checked: store('desktop') === '1' && Notification.permission === 'granted', onchange: function (e) {
				if (e.target.checked) {
					Notification.requestPermission().then(function (p) { store('desktop', p === 'granted' ? '1' : null); if (p !== 'granted') { e.target.checked = false; Y.toast(t('Tarayıcı izin vermedi.'), { error: true }); } });
				} else store('desktop', null);
			} }), h('span', h('b', t('Masaüstü bildirimleri')), h('small', t('Görevler sekmesi açıkken yeni bildirimler tarayıcı bildirimi olarak da gelir.')))) : null
		));
		var keysBox = h('section.flxg-card-box');
		inner.appendChild(keysBox);
		function drawKeys(r, fresh) {
			Y.clear(keysBox);
			keysBox.appendChild(h('h2', t('MCP bağlantısı')));
			if (!r.enabled) keysBox.appendChild(h('p.flxg-error', t('MCP sunucusu kapalı. Yönetici Ayarlar sayfasından açabilir.')));
			keysBox.appendChild(h('p.flxg-muted', t('Claude, ChatGPT, Cursor gibi MCP destekli bir istemciye bu adresi ve kişisel anahtarınızı ekleyin. Asistan yalnız sizin görebildiğiniz listelerde, sizin yetkinizle çalışır.')));
			keysBox.appendChild(h('.flxg-copyrow', h('code', r.url), h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { Y.copy(r.url); } }, Y.icon('copy', 14), t('Kopyala'))));
			if (fresh) {
				keysBox.appendChild(h('.flxg-newkey',
					h('b', t('Yeni anahtarınız (yalnız şimdi gösterilir):')),
					h('.flxg-copyrow', h('code', fresh), h('button.flxg-btn.is-sm.is-primary', { type: 'button', onclick: function () { Y.copy(fresh); } }, Y.icon('copy', 14), t('Kopyala'))),
					h('small.flxg-help', t('İstemcide başlık olarak "Authorization: Bearer <anahtar>" kullanın. Başlık eklenemiyorsa adresin sonuna ?key=<anahtar> ekleyin.'))
				));
			}
			var rows = r.keys.map(function (k) {
				return h('tr',
					h('td', h('b', k.label || t('Adsız')), h('div.flxg-muted', h('code', k.hint))),
					S.me.can.manage ? h('td', k.userName) : null,
					h('td.flxg-muted', t('Son kullanım: {d}', { d: k.lastUsed ? Y.ago(k.lastUsed) : t('hiç') })),
					h('td', h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Sil'), onclick: function () {
						Y.confirm(t('Anahtarı sil'), t('Bu anahtarı kullanan istemciler bağlantıyı kaybeder.'), t('Sil'), true).then(function (ok) {
							if (ok) Y.api('DELETE', 'mcp-keys/' + k.id).then(function (x) { drawKeys(x); }, Y.fail);
						});
					} }, Y.icon('trash', 15)))
				);
			});
			if (rows.length) keysBox.appendChild(h('.flxg-table-wrap', h('table.flxg-table', h('tbody', rows))));
			keysBox.appendChild(h('.flxg-row-btns.is-left', h('button.flxg-btn', { type: 'button', onclick: function () {
				Y.prompt(t('Anahtar adı (örn: Claude masaüstü)'), '', t('Oluştur')).then(function (label) {
					if (label === null) return;
					Y.api('POST', 'mcp-keys', { label: label }).then(function (x) { drawKeys(x, x.key); }, Y.fail);
				});
			} }, Y.icon('key', 15), t('Yeni anahtar oluştur'))));
		}
		Y.api('GET', 'mcp-keys').then(function (r) { drawKeys(r); }, Y.fail);
	}

	/* =====================================================================
	 * Proje ve liste pencereleri
	 * ================================================================== */
	var COLORS = ['#2e7d32', '#16a34a', '#0d9488', '#0284c7', '#2563eb', '#7c3aed', '#c026d3', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#64748b'];
	function colorPicker(cur, onPick) {
		var wrap = h('.flxg-colors');
		function draw() {
			Y.clear(wrap);
			COLORS.forEach(function (c) {
				wrap.appendChild(h('button.flxg-color' + (c === cur ? '.is-on' : ''), { type: 'button', style: { background: c }, 'aria-label': c, onclick: function () { cur = c; onPick(c); draw(); } }));
			});
			var custom = h('input', { type: 'color', value: cur || '#2e7d32', onchange: function (e) { cur = e.target.value; onPick(cur); draw(); } });
			wrap.appendChild(h('label.flxg-color.is-custom', { title: t('Özel renk') }, custom));
		}
		draw();
		return wrap;
	}
	function roleSelect(cur, onChange, allowNone) {
		var sel = h('select.flxg-input.is-sm', { onchange: function (e) { onChange(e.target.value); } });
		if (allowNone) sel.appendChild(h('option', { value: '' }, t('Erişim yok')));
		Object.keys(S.config.roles).forEach(function (k) { sel.appendChild(h('option', { value: k, selected: k === cur }, S.config.roles[k])); });
		return sel;
	}
	function membersEditor(members, opts) {
		opts = opts || {};
		var box = h('.flxg-members');
		function draw() {
			Y.clear(box);
			members.forEach(function (m, i) {
				var u = S.users[m.user];
				box.appendChild(h('.flxg-member', Y.avatar(u, 28), h('span.flxg-grow', u ? u.name : '#' + m.user),
					roleSelect(m.role, function (v) { m.role = v; }),
					h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kaldır'), onclick: function () { members.splice(i, 1); draw(); } }, Y.icon('x', 15))));
			});
			if (!members.length) box.appendChild(h('.flxg-empty-sm', opts.empty || t('Henüz üye eklenmedi.')));
			box.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function (e) {
				Y.pick.people(e.currentTarget, members.map(function (m) { return m.user; }), function (ids) {
					ids.forEach(function (id) { if (!members.some(function (m) { return m.user === id; })) members.push({ user: id, role: opts.defRole || 'edit' }); });
					for (var j = members.length - 1; j >= 0; j--) if (ids.indexOf(members[j].user) < 0) members.splice(j, 1);
					draw();
				});
			} }, Y.icon('plus', 14), t('Kişi ekle')));
		}
		draw();
		return box;
	}

	Y.spaceModal = function (sp) {
		var d = sp ? { name: sp.name, color: sp.color, access: sp.access, default_role: sp.default_role, subtask_depth: sp.subtask_depth || 3 } : { name: '', color: COLORS[0], access: 'everyone', default_role: 'edit', subtask_depth: 3, list: t('İşler') };
		var depthIn = h('input.flxg-input.is-num', { type: 'number', min: 1, max: 10, step: 1, value: d.subtask_depth });
		var members = [];
		var name = h('input.flxg-input', { type: 'text', value: d.name, placeholder: t('Proje adı') });
		var accessBox = h('.flxg-radios');
		var memBox = h('div');
		function drawAccess() {
			Y.clear(accessBox);
			[['everyone', t('Herkes'), t('Görevleri kullanabilen herkes bu projeye girer.')], ['members', t('Yalnız üyeler'), t('Yalnız aşağıda eklenen kişiler görür.')]].forEach(function (o) {
				accessBox.appendChild(h('label.flxg-radio' + (d.access === o[0] ? '.is-on' : ''), h('input', { type: 'radio', name: 'flxg-acc', checked: d.access === o[0], onchange: function () { d.access = o[0]; drawAccess(); } }), h('span', h('b', o[1]), h('small', o[2]))));
			});
			if (d.access === 'everyone') accessBox.appendChild(h('label.flxg-field.is-inline', h('span', t('Herkesin varsayılan rolü')), roleSelect(d.default_role, function (v) { d.default_role = v; })));
		}
		drawAccess();
		var body = h('.flxg-form', h('label.flxg-field', h('span', t('Ad')), name), h('.flxg-field', h('span', t('Renk')), colorPicker(d.color, function (c) { d.color = c; })), h('.flxg-field', h('span', t('Erişim')), accessBox), h('.flxg-field', h('span', t('Üyeler ve rolleri')), memBox));
		body.appendChild(h('label.flxg-field', h('span', t('Alt görev kademe sınırı')), depthIn, h('small.flxg-muted', t('Bir görevin altına en fazla kaç kademe alt görev eklenebileceği (1–10). Varsayılan 3.'))));
		if (!sp) body.appendChild(h('label.flxg-field', h('span', t('İlk liste')), h('input.flxg-input', { type: 'text', value: d.list, onchange: function (e) { d.list = e.target.value; } })));
		var membersLoaded = !sp;
		if (sp) {
			memBox.appendChild(Y.skeleton('list', { rows: 3 }));
			Y.api('GET', 'spaces/' + sp.id + '/members').then(function (r) { members = r.members; membersLoaded = true; Y.clear(memBox).appendChild(membersEditor(members)); }, Y.fail);
		} else {
			memBox.appendChild(membersEditor(members, { empty: t('Siz yönetici olarak eklenirsiniz.') }));
		}
		Y.modal(sp ? t('Proje ayarları') : t('Yeni proje'), body, { actions: [
			{ label: t('Vazgeç') },
			{ label: sp ? t('Kaydet') : t('Oluştur'), primary: true, onClick: function () {
				d.name = name.value.trim();
				if (!d.name) { name.focus(); return false; }
				d.subtask_depth = Math.max(1, Math.min(10, parseInt(depthIn.value, 10) || 3));
				if (sp) {
					return Y.api('PATCH', 'spaces/' + sp.id, { name: d.name, color: d.color, subtask_depth: d.subtask_depth }).then(function (r) {
						if (r && r.spaces) Y.applyStructure(r);
						return membersLoaded ? Y.api('POST', 'spaces/' + sp.id + '/members', { members: members, access: d.access, default_role: d.default_role }) : null;
					}).then(function (r) { if (r) Y.applyStructure(r); Y.toast(t('Proje kaydedildi')); });
				}
				return Y.api('POST', 'spaces', d).then(function (r) {
					Y.applyStructure(r);
					var p = members.length ? Y.api('POST', 'spaces/' + r.id + '/members', { members: members }).then(Y.applyStructure) : Promise.resolve();
					return p.then(function () { Y.go('#/space/' + r.id); });
				});
			} }
		] });
	};

	Y.listModal = function (spaceId, folderId, kind) {
		var d = { name: '', color: COLORS[4], access: 'inherit', folder: folderId || 0, kind: kind === 'meeting' ? 'meeting' : 'task' };
		var name = h('input.flxg-input', { type: 'text', placeholder: t('Liste adı') });
		var folders = S.folders.filter(function (f) { return f.space_id === spaceId; });
		var tpl = h('.flxg-tpl-pick');
		function drawTpl() {
			Y.clear(tpl);
			[['task', 'list', t('Görev listesi'), t('Görevler, alt görevler, durumlar')], ['meeting', 'meeting', t('Toplantı listesi'), t('Toplantılar, gündem, kararlar, PDF rapor')]].forEach(function (x) {
				tpl.appendChild(h('button.flxg-tpl' + (d.kind === x[0] ? '.is-on' : ''), { type: 'button', onclick: function () { d.kind = x[0]; name.placeholder = x[0] === 'meeting' ? t('Örn. Yönetim kurulu toplantıları') : t('Liste adı'); drawTpl(); } },
					h('span.flxg-tpl-ic', Y.icon(x[1], 18)), h('span.flxg-tpl-txt', h('b', x[2]), h('small', x[3]))));
			});
		}
		drawTpl();
		var body = h('.flxg-form',
			h('.flxg-field', h('span', t('Şablon')), tpl),
			h('label.flxg-field', h('span', t('Ad')), name),
			h('.flxg-field', h('span', t('Renk')), colorPicker(d.color, function (c) { d.color = c; })),
			folders.length ? h('label.flxg-field', h('span', t('Klasör')), h('select.flxg-input', { onchange: function (e) { d.folder = +e.target.value; } }, h('option', { value: 0 }, t('Klasörsüz (projenin altında)')), folders.map(function (f) { return h('option', { value: f.id, selected: f.id === d.folder }, f.name); }))) : null,
			h('label.flxg-switch-row', h('input', { type: 'checkbox', onchange: function (e) { d.access = e.target.checked ? 'members' : 'inherit'; } }), h('span', h('b', t('Özel liste')), h('small', t('Yalnız sizin ekleyeceğiniz kişiler görür. Kapalıyken proje üyeleri görür.'))))
		);
		Y.modal(t('Yeni liste'), body, { actions: [
			{ label: t('Vazgeç') },
			{ label: t('Oluştur'), primary: true, onClick: function () {
				d.name = name.value.trim();
				if (!d.name) { name.focus(); return false; }
				return Y.api('POST', 'lists', { space: spaceId, folder: d.folder, name: d.name, color: d.color, access: d.access, kind: d.kind }).then(function (r) {
					Y.applyStructure(r);
					Y.go('#/list/' + r.id);
				});
			} }
		] });
	};

	/* Liste ayarları: genel, durumlar, özel alanlar, yetkiler. */
	Y.listSettings = function (listId, tab) {
		var l = Y.list(listId);
		if (!l) return;
		var cur = tab || 'general';
		var body = h('.flxg-lset');
		var tabs = h('.flxg-seg.flxg-lset-tabs');
		var pane = h('.flxg-lset-pane');
		body.appendChild(tabs);
		body.appendChild(pane);
		var data = { fields: null, members: null };
		function drawTabs() {
			Y.clear(tabs);
			[['general', t('Genel')], ['statuses', t('Durumlar')], ['fields', t('Özel alanlar')], ['access', t('Yetkiler')]].forEach(function (o) {
				tabs.appendChild(h('button' + (cur === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { cur = o[0]; drawTabs(); draw(); } }, o[1]));
			});
		}
		function draw() {
			Y.clear(pane);
			l = Y.list(listId);
			if (cur === 'general') general();
			if (cur === 'statuses') statuses();
			if (cur === 'fields') fields();
			if (cur === 'access') access();
		}
		function general() {
			var d = { name: l.name, color: l.color, folder_id: l.folder_id, kind: l.kind || 'task' };
			var name = h('input.flxg-input', { type: 'text', value: l.name });
			var folders = S.folders.filter(function (f) { return f.space_id === l.space_id; });
			pane.appendChild(h('.flxg-form',
				h('label.flxg-field', h('span', t('Ad')), name),
				h('label.flxg-field', h('span', t('Liste türü')), h('select.flxg-input', { onchange: function (e) { d.kind = e.target.value; } },
					h('option', { value: 'task', selected: d.kind !== 'meeting' }, t('Görev listesi')),
					h('option', { value: 'meeting', selected: d.kind === 'meeting' }, t('Toplantı listesi')))),
				h('.flxg-field', h('span', t('Renk')), colorPicker(l.color, function (c) { d.color = c; })),
				h('label.flxg-field', h('span', t('Klasör')), h('select.flxg-input', { onchange: function (e) { d.folder_id = +e.target.value; } }, h('option', { value: 0 }, t('Klasörsüz (projenin altında)')), folders.map(function (f) { return h('option', { value: f.id, selected: f.id === l.folder_id }, f.name); }))),
				S.spaces.length > 1 ? h('label.flxg-field', h('span', t('Proje')), h('select.flxg-input', { onchange: function (e) { d.space_id = +e.target.value; d.folder_id = 0; } }, S.spaces.filter(function (sp) { return Y.rank(sp.role) >= 3; }).map(function (sp) { return h('option', { value: sp.id, selected: sp.id === l.space_id }, sp.name); }))) : null,
				h('.flxg-row-btns', h('button.flxg-btn.is-primary', { type: 'button', onclick: function () {
					d.name = name.value.trim() || l.name;
					Y.api('PATCH', 'lists/' + l.id, d).then(function (r) { Y.applyStructure(r); Y.toast(t('Kaydedildi')); }, Y.fail);
				} }, t('Kaydet')))
			));
		}
		function statuses() {
			var arr = JSON.parse(JSON.stringify(l.statuses));
			var box = h('.flxg-statuses');
			function drawS() {
				Y.clear(box);
				arr.forEach(function (s, i) {
					var colorIn = h('input.flxg-color-in', { type: 'color', value: s.color, onchange: function (e) { s.color = e.target.value; } });
					box.appendChild(h('.flxg-status-edit',
						h('.flxg-updown',
							h('button.flxg-icon-btn.is-xs', { type: 'button', disabled: i === 0, onclick: function () { arr.splice(i - 1, 0, arr.splice(i, 1)[0]); drawS(); } }, Y.icon('chevD', 12, 'is-up')),
							h('button.flxg-icon-btn.is-xs', { type: 'button', disabled: i === arr.length - 1, onclick: function () { arr.splice(i + 1, 0, arr.splice(i, 1)[0]); drawS(); } }, Y.icon('chevD', 12))
						),
						colorIn,
						h('input.flxg-input.is-sm', { type: 'text', value: s.label, onchange: function (e) { s.label = e.target.value; } }),
						h('select.flxg-input.is-sm', { onchange: function (e) { s.type = e.target.value; } }, h('option', { value: 'open', selected: s.type !== 'done' }, t('Açık')), h('option', { value: 'done', selected: s.type === 'done' }, t('Tamamlandı'))),
						h('button.flxg-icon-btn.is-sm', { type: 'button', disabled: arr.length < 2, 'aria-label': t('Sil'), onclick: function () { arr.splice(i, 1); drawS(); } }, Y.icon('trash', 15))
					));
				});
				box.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { arr.splice(Math.max(0, arr.length - 1), 0, { key: '', label: t('Yeni durum'), color: '#f59e0b', type: 'open' }); drawS(); } }, Y.icon('plus', 14), t('Durum ekle')));
			}
			drawS();
			pane.appendChild(h('p.flxg-muted', t('Silinen durumdaki görevler ilk duruma alınır. "Tamamlandı" türündeki durumlar görevi bitmiş sayar.')));
			pane.appendChild(box);
			pane.appendChild(h('.flxg-row-btns', h('button.flxg-btn.is-primary', { type: 'button', onclick: function () {
				Y.api('PATCH', 'lists/' + l.id, { statuses: arr }).then(function (r) { Y.applyStructure(r); Y.toast(t('Durumlar kaydedildi')); if (S.scope) Y.reloadTasks(); draw(); }, Y.fail);
			} }, t('Kaydet'))));
		}
		function fields() {
			if (!data.fields) {
				pane.appendChild(Y.skeleton('form', { rows: 2 }));
				Y.api('GET', 'lists/' + l.id).then(function (r) { data.fields = r.fields; draw(); }, Y.fail);
				return;
			}
			var list = h('.flxg-fieldlist');
			data.fields.forEach(function (f) {
				list.appendChild(h('.flxg-fielditem',
					h('span.flxg-ftype', Y.fieldTypeIcon(f.type)),
					h('span.flxg-grow', h('b', f.name), h('small.flxg-muted', ' · ' + (S.config.fieldTypes[f.type] || f.type))),
					h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Düzenle'), onclick: function () { fieldForm(f); } }, Y.icon('edit', 15)),
					h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Sil'), onclick: function () {
						Y.confirm(t('Alanı sil'), t('"{n}" alanı ve tüm görevlerdeki değerleri silinecek.', { n: f.name }), t('Sil'), true).then(function (ok) {
							if (ok) Y.api('DELETE', 'fields/' + f.id).then(function (r) { data.fields = r.fields; afterFields(); }, Y.fail);
						});
					} }, Y.icon('trash', 15))
				));
			});
			if (!data.fields.length) list.appendChild(h('.flxg-empty-sm', t('Bu listede özel alan yok. Metin, sayı, tarih, açılır liste, yıldız, dosya gibi alanlar ekleyebilirsiniz.')));
			pane.appendChild(list);
			pane.appendChild(h('.flxg-row-btns.is-left', h('button.flxg-btn', { type: 'button', onclick: function () { fieldForm(null); } }, Y.icon('plus', 14), t('Alan ekle'))));
		}
		function afterFields() {
			if (S.scope && (S.scope.type === 'list' && S.scope.id === l.id)) S.fields = data.fields.slice();
			else if (S.scope) {
				S.fields = S.fields.filter(function (f) { return f.list_id !== l.id; }).concat(data.fields);
			}
			Y.emit('view');
			draw();
		}
		function fieldForm(f) {
			var d = f ? JSON.parse(JSON.stringify(f)) : { name: '', type: 'text', options: {} };
			d.options = d.options || {};
			Y.clear(pane);
			var name = h('input.flxg-input', { type: 'text', value: d.name, placeholder: t('Alan adı') });
			var typeSel = h('select.flxg-input', { disabled: !!f, onchange: function (e) { d.type = e.target.value; drawOpts(); } }, Object.keys(S.config.fieldTypes).map(function (k) { return h('option', { value: k, selected: k === d.type }, S.config.fieldTypes[k]); }));
			var opts = h('div');
			function drawOpts() {
				Y.clear(opts);
				if (d.type === 'dropdown' || d.type === 'labels') {
					d.options.choices = d.options.choices || [{ label: '', color: COLORS[1] }];
					var ch = h('.flxg-choices');
					var drawC = function () {
						Y.clear(ch);
						d.options.choices.forEach(function (c, i) {
							ch.appendChild(h('.flxg-choice-edit',
								h('input.flxg-color-in', { type: 'color', value: c.color || '#94a3b8', onchange: function (e) { c.color = e.target.value; } }),
								h('input.flxg-input.is-sm', { type: 'text', value: c.label, placeholder: t('Seçenek'), onchange: function (e) { c.label = e.target.value; } }),
								h('button.flxg-icon-btn.is-sm', { type: 'button', onclick: function () { d.options.choices.splice(i, 1); drawC(); } }, Y.icon('x', 14))));
						});
						ch.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { d.options.choices.push({ label: '', color: COLORS[(d.options.choices.length * 3) % COLORS.length] }); drawC(); var ins = Y.$$('input[type=text]', ch); ins[ins.length - 1].focus(); } }, Y.icon('plus', 14), t('Seçenek ekle')));
					};
					drawC();
					opts.appendChild(h('.flxg-field', h('span', t('Seçenekler')), ch));
				}
				if (d.type === 'rating') opts.appendChild(h('label.flxg-field', h('span', t('En fazla yıldız')), h('input.flxg-input', { type: 'number', min: 3, max: 10, value: d.options.max || 5, onchange: function (e) { d.options.max = +e.target.value; } })));
				if (d.type === 'money') opts.appendChild(h('label.flxg-field', h('span', t('Para birimi')), h('input.flxg-input', { type: 'text', maxlength: 3, value: d.options.currency || 'TRY', onchange: function (e) { d.options.currency = e.target.value.toUpperCase(); } })));
				if (d.type === 'number') opts.appendChild(h('label.flxg-field', h('span', t('Ondalık basamak')), h('input.flxg-input', { type: 'number', min: 0, max: 4, value: d.options.decimals || 0, onchange: function (e) { d.options.decimals = +e.target.value; } })));
				opts.appendChild(h('label.flxg-field', h('span', t('Açıklama (isteğe bağlı)')), h('input.flxg-input', { type: 'text', value: d.options.hint || '', onchange: function (e) { d.options.hint = e.target.value; } })));
			}
			drawOpts();
			pane.appendChild(h('.flxg-form',
				h('label.flxg-field', h('span', t('Ad')), name),
				h('label.flxg-field', h('span', t('Tür')), typeSel, f ? h('small.flxg-help', t('Tür sonradan değiştirilemez.')) : null),
				opts,
				h('.flxg-row-btns',
					h('button.flxg-btn', { type: 'button', onclick: draw }, t('Vazgeç')),
					h('button.flxg-btn.is-primary', { type: 'button', onclick: function () {
						d.name = name.value.trim();
						if (!d.name) { name.focus(); return; }
						var p = f ? Y.api('PATCH', 'fields/' + f.id, { name: d.name, options: d.options }) : Y.api('POST', 'lists/' + l.id + '/fields', d);
						p.then(function (r) {
							data.fields = r.fields;
							// Yeni alan görünümlerde varsayılan olarak görünür olsun.
							if (!f && r.field && S.draft && S.scope && S.scope.type === 'list' && S.scope.id === l.id) {
								S.draft.fields = (S.draft.fields || []).concat(['f' + r.field.id]);
								S.dirty = true;
							}
							afterFields();
						}, Y.fail);
					} }, f ? t('Kaydet') : t('Alanı ekle'))
				)
			));
			name.focus();
		}
		function access() {
			if (!data.members) {
				pane.appendChild(Y.skeleton('form', { rows: 2 }));
				Y.api('GET', 'lists/' + l.id + '/members').then(function (r) { data.members = r; draw(); }, Y.fail);
				return;
			}
			var m = data.members;
			var acc = m.access;
			var members = m.members.slice();
			var radios = h('.flxg-radios');
			var drawR = function () {
				Y.clear(radios);
				[['inherit', t('Proje üyeleri'), m.spaceAccess === 'everyone' ? t('Görevleri kullanabilen herkes ({r}).', { r: S.config.roles[m.spaceDefault] }) : t('Projenin üyeleri, projedeki rolleriyle.')], ['members', t('Yalnız seçilen kişiler'), t('Özel liste: yalnız aşağıdaki kişiler ve proje yöneticileri görür.')]].forEach(function (o) {
					radios.appendChild(h('label.flxg-radio' + (acc === o[0] ? '.is-on' : ''), h('input', { type: 'radio', name: 'flxg-lacc', checked: acc === o[0], onchange: function () { acc = o[0]; drawR(); } }), h('span', h('b', o[1]), h('small', o[2]))));
				});
			};
			drawR();
			pane.appendChild(h('.flxg-form',
				h('.flxg-field', h('span', t('Kimler görür')), radios),
				h('.flxg-field', h('span', t('Kişiye özel roller')), h('small.flxg-help', t('Görüntüler: yalnız okur. Yorum yapar: okur ve yorum yazar. Düzenler: görev ekler, değiştirir. Yönetir: liste ayarları, alanlar ve yetkiler.')), membersEditor(members)),
				h('.flxg-row-btns', h('button.flxg-btn.is-primary', { type: 'button', onclick: function () {
					Y.api('POST', 'lists/' + l.id + '/members', { members: members, access: acc }).then(function (r) { Y.applyStructure(r); data.members = null; Y.toast(t('Yetkiler kaydedildi')); draw(); }, Y.fail);
				} }, t('Kaydet')))
			));
		}
		drawTabs();
		draw();
		Y.modal(t('Liste ayarları: {n}', { n: l.name }), body, { wide: true });
	};

	/* =====================================================================
	 * Yapay zekâ sohbeti
	 * ================================================================== */
	var AI = { el: null, msgs: [] };
	try { AI.msgs = JSON.parse(sessionStorage.getItem('flxg-ai') || '[]'); } catch (e) { AI.msgs = []; }
	Y.openAI = function (ctx) {
		ctx = ctx || {};
		if (AI.el) { AI.el.remove(); AI.el = null; if (!ctx.task) return; }
		var log = h('.flxg-ai-log');
		var ta = h('textarea.flxg-composer-input', { rows: 2, placeholder: t('Ne yapmak istersiniz? Örn: "Bu listede yarın biten görevleri göster" ya da "Ayşe\'ye cuma günü bitecek afiş görevi aç"') });
		Y.autosize(ta);
		var ctxChip = h('.flxg-ai-ctx');
		var taskCtx = ctx.task || S.openTask || 0;
		var pageCtx = ctx.page || (Y.docPage ? Y.docPage() : null);
		function drawCtx() {
			Y.clear(ctxChip);
			if (S.scope && S.scope.type === 'list') ctxChip.appendChild(h('span.flxg-chip', h('span.flxg-dot', { style: { background: Y.list(S.scope.id).color } }), Y.list(S.scope.id).name));
			if (taskCtx) ctxChip.appendChild(h('span.flxg-chip', '#' + taskCtx, h('button', { type: 'button', 'aria-label': t('Kaldır'), onclick: function () { taskCtx = 0; drawCtx(); } }, Y.icon('x', 12))));
			if (pageCtx) ctxChip.appendChild(h('span.flxg-chip', Y.icon('doc', 12), pageCtx.title || t('Başlıksız'), h('button', { type: 'button', 'aria-label': t('Kaldır'), onclick: function () { pageCtx = null; drawCtx(); } }, Y.icon('x', 12))));
		}
		drawCtx();
		function draw() {
			Y.clear(log);
			if (!AI.msgs.length) {
				log.appendChild(h('.flxg-ai-hello', Y.icon('sparkle', 28), h('b', t('Merhaba! Görevlerle ilgili ne yapalım?')),
					h('.flxg-ai-sugs', [t('Bana atanan geciken görevleri listele'), t('Bu listeye 3 görev ekle: …'), t('Bu haftanın özetini çıkar')].map(function (s) {
						return h('button.flxg-chip-btn', { type: 'button', onclick: function () { ta.value = s; ta.focus(); } }, s);
					}))));
			}
			AI.msgs.forEach(function (m) {
				var node = h('.flxg-ai-msg.is-' + m.role, m.role === 'assistant' ? h('.flxg-prose', { html: Y.richText(m.content).replace(/#(\d+)/g, '<a href="#/task/$1">#$1</a>') }) : h('div', m.content));
				if (m.actions && m.actions.length) {
					node.appendChild(h('.flxg-ai-actions', m.actions.map(function (a) {
						var href = a.task ? '#/task/' + a.task : (a.doc ? '#/doc/' + a.doc + (a.page ? '/p/' + a.page : '') : null);
						return h(href ? 'a.flxg-chip' : 'span.flxg-chip', { href: href, class: a.ok ? '' : 'is-error' }, Y.icon(a.ok ? 'check' : 'x', 12), aiActionLabel(a));
					})));
				}
				log.appendChild(node);
			});
			if (AI.busy) log.appendChild(h('.flxg-ai-msg.is-assistant', h('.flxg-typing', h('span'), h('span'), h('span'))));
			log.scrollTop = log.scrollHeight;
		}
		function aiActionLabel(a) {
			var map = { create_task: t('Görev oluşturuldu'), update_task: t('Görev güncellendi'), add_comment: t('Yorum eklendi'), delete_task: t('Görev silindi'), create_list: t('Liste oluşturuldu'), edit_doc_page: t('Doküman sayfası düzenlendi'), create_doc: t('Doküman oluşturuldu'), create_doc_page: t('Dokümana sayfa eklendi') };
			return (map[a.tool] || a.tool) + (a.task ? ' #' + a.task : '') + (a.title ? ': ' + a.title : '') + (a.error ? ' – ' + a.error : '');
		}
		function send() {
			var text = ta.value.trim();
			if (!text || AI.busy) return;
			AI.msgs.push({ role: 'user', content: text });
			ta.value = '';
			AI.busy = true;
			draw();
			Y.api('POST', 'ai/chat', { messages: AI.msgs.map(function (m) { return { role: m.role, content: m.content }; }), context: { list_id: S.scope && S.scope.type === 'list' ? S.scope.id : 0, task_id: taskCtx, doc_page: pageCtx ? pageCtx.id : 0 } }).then(function (r) {
				AI.busy = false;
				AI.msgs.push({ role: 'assistant', content: r.reply || '…', actions: r.actions });
				save();
				draw();
				if (r.actions && r.actions.length) {
					// Asistanın dokunduğu görevler filtreye uymasa da ekranda kalsın.
					r.actions.forEach(function (a) { if (a.ok && a.task && a.tool !== 'delete_task') Y.keepTask(a.task); });
					Y.reloadTasks();
					if (Y.D.task) Y.reloadTask();
					if (Y.docSyncNow && r.actions.some(function (a) { return a.ok && a.doc; })) Y.docSyncNow();
				}
			}, function (e) {
				AI.busy = false;
				AI.msgs.push({ role: 'assistant', content: '⚠️ ' + e.message });
				save();
				draw();
			});
		}
		function save() { try { sessionStorage.setItem('flxg-ai', JSON.stringify(AI.msgs.slice(-30))); } catch (e) { /* yok */ } }
		ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
		AI.el = h('aside.flxg-ai',
			h('header.flxg-ai-head', Y.icon('sparkle', 18), h('b', t('Yapay zekâ asistanı')), h('span.flxg-grow'),
				h('button.flxg-icon-btn', { type: 'button', title: t('Sohbeti temizle'), onclick: function () { AI.msgs = []; save(); draw(); } }, Y.icon('trash', 16)),
				h('button.flxg-icon-btn', { type: 'button', title: t('Kapat'), onclick: function () { AI.el.remove(); AI.el = null; } }, Y.icon('x', 18))),
			log,
			h('.flxg-ai-foot', ctxChip, h('.flxg-composer', ta, h('.flxg-composer-bar', h('span.flxg-muted.flxg-hint', t('Asistan sizin yetkinizle görev ekler ve değiştirir.')), h('span.flxg-grow'), h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: send }, Y.icon('send', 14), t('Gönder')))))
		);
		app.appendChild(AI.el);
		draw();
		setTimeout(function () { ta.focus(); }, 50);
	};

	/* =====================================================================
	 * Yeni bildirimler
	 * ================================================================== */
	Y.on('notifs', function (list) {
		list.slice(0, 3).reverse().forEach(function (n) {
			if (n.read) return;
			Y.toast(Y.notifText(n) + ': ' + n.title, { action: { label: t('Aç'), fn: function () { Y.go(Y.scopeHash() + '/t/' + n.task); } } });
			if (store('desktop') === '1' && 'Notification' in window && Notification.permission === 'granted' && document.hidden === false) {
				try {
					var nt = new Notification(Y.notifText(n), { body: n.title + (n.data && n.data.excerpt ? '\n' + n.data.excerpt : ''), icon: B.icon || undefined, tag: 'flxg-' + n.id });
					nt.onclick = function () { window.focus(); Y.go('#/task/' + n.task); };
				} catch (e) { /* yok */ }
			}
		});
		if (Y.route && Y.route.page === 'inbox' && !Y.D.task) renderOtherPage('inbox');
	});

	/* Kısayollar. */
	document.addEventListener('keydown', function (e) {
		var a = document.activeElement;
		if (a && (a.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(a.tagName))) return;
		if (e.ctrlKey || e.metaKey || e.altKey) return;
		if (e.key === 'n' && S.scope) { e.preventDefault(); Y.quickCreate({}); }
		if (e.key === '/' && S.scope) { var s = Y.$('.flxg-search'); if (s) { e.preventDefault(); s.focus(); } }
	});

	/* =====================================================================
	 * Başlangıç
	 * ================================================================== */
	function init() {
		app = Y.$('#flxg-app');
		if (!app) return;
		if (B.locale) app.setAttribute('lang', B.locale);
		app.style.setProperty('--brand', B.accent || '#2e7d32');
		document.documentElement.style.setProperty('--brand', B.accent || '#2e7d32');
		Y.applyTheme();
		app.appendChild(h('.flxg-skel-app', h('.flxg-skel-nav', Y.sk('70%', 18), Y.sk('85%', 12), Y.sk('60%', 12), Y.sk('75%', 12), Y.sk('50%', 12)), Y.skeleton('list')));
		Y.loadBoot().then(function () {
			shell();
			window.addEventListener('hashchange', route);
			route();
			Y.startSync();
			document.title = (S.unread ? '(' + S.unread + ') ' : '') + t('Görevler') + ' ‹ ' + B.brand;
		}, function (e) {
			Y.clear(app).appendChild(h('.flxg-empty', h('p', e.message), h('button.flxg-btn', { type: 'button', onclick: function () { location.reload(); } }, t('Yeniden dene'))));
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
