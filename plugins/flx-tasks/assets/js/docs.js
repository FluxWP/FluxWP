/* Flux Tasks — dokümanlar: çok sayfalı belge, sayfa ağacı, blok düzenleyici, ortak düzenleme (hafif yoklama), canlı imleçler. */
(function () {
	'use strict';
	var Y = window.FLXG;
	var t = Y.t;
	var h = Y.h;
	var S = Y.S;
	var B = Y.B;

	/* Bloklar editörün doğrudan çocuklarıdır; her biri data-b kimliği taşır. Sunucuya yalnız değişen bloklar gider. */
	var BLOCK = { P: 1, H1: 1, H2: 1, H3: 1, H4: 1, UL: 1, OL: 1, BLOCKQUOTE: 1, PRE: 1, HR: 1, FIGURE: 1, TABLE: 1 };
	var D = null;

	function newId() { return Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-5); }
	function me() { return S.me ? S.me.id : 0; }
	function user(id) { return S.users[id] || { id: id, name: t('Biri'), color: '#64748b', initials: '?' }; }
	Y.doc = function (id) { return (S.docs || []).filter(function (d) { return d.id === +id; })[0] || null; };

	/* =====================================================================
	 * Doküman oluştur / menü (kenar menü buradan çağırır)
	 * ================================================================== */
	Y.newDoc = function (spaceId, folderId) {
		Y.prompt(t('Doküman adı'), '', t('Oluştur')).then(function (n) {
			if (n === null) return;
			Y.api('POST', 'docs', { space: spaceId, folder: folderId || 0, title: n }).then(function (r) {
				Y.applyStructure(r);
				Y.go('#/doc/' + r.id);
			}, Y.fail);
		});
	};
	function renameDoc(doc) {
		Y.prompt(t('Doküman adı'), doc.title).then(function (n) {
			if (!n) return;
			Y.api('POST', 'docs/' + doc.id, { title: n }).then(function (r) {
				Y.applyStructure(r);
				if (D && D.doc.id === doc.id) { D.doc = r.doc; drawCrumbs(); drawTree(); }
			}, Y.fail);
		});
	}
	function deleteDoc(doc) {
		Y.confirm(t('Dokümanı sil'), t('"{n}" dokümanı tüm sayfaları ve görselleriyle kalıcı olarak silinecek.', { n: doc.title }), t('Sil'), true).then(function (ok) {
			if (!ok) return;
			Y.api('DELETE', 'docs/' + doc.id).then(function (r) {
				if (D && D.doc.id === doc.id) Y.leaveDoc();
				Y.applyStructure(r);
				Y.go('#/space/' + doc.space_id);
			}, Y.fail);
		});
	}
	Y.docMenu = function (anchor, doc) {
		var canEdit = Y.rank(doc.role) >= 3;
		var canDel = Y.rank(doc.role) >= 4 || (canEdit && doc.owner_id === me());
		Y.menu(anchor, [
			canEdit ? { label: t('Yeniden adlandır'), icon: 'edit', onClick: function () { renameDoc(doc); } } : null,
			{ label: t('Bağlantıyı kopyala'), icon: 'link', onClick: function () { Y.copy(location.href.split('#')[0] + '#/doc/' + doc.id); } },
			canDel ? { sep: true } : null,
			canDel ? { label: t('Dokümanı sil'), icon: 'trash', danger: true, onClick: function () { deleteDoc(doc); } } : null
		]);
	};

	/* =====================================================================
	 * Yazdırma / PDF (doküman sayfası ve toplantı raporu ortak)
	 * ================================================================== */
	Y.printHtml = function (title, bodyHtml, extraCss) {
		var w = window.open('', '_blank');
		if (!w) { Y.toast(t('Açılır pencere engellendi. Tarayıcıda bu site için açılır pencerelere izin verin.'), { error: true }); return; }
		var css = 'body{font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:760px;padding:0 24px}' +
			'h1{font-size:26px;margin:0 0 4px}h2{font-size:19px;margin:22px 0 8px}h3{font-size:16px;margin:16px 0 6px}' +
			'.meta{color:#6b7280;font-size:12.5px;margin-bottom:18px}img{max-width:100%;height:auto;border-radius:6px}' +
			'table{border-collapse:collapse;width:100%;margin:8px 0}td,th{border:1px solid #d1d5db;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f3f4f6}' +
			'blockquote{border-left:3px solid #d1d5db;margin:8px 0;padding:2px 12px;color:#4b5563}pre{background:#f3f4f6;padding:10px;border-radius:6px;white-space:pre-wrap}' +
			'ul[data-checklist]{list-style:none;padding-left:4px}li[data-checked]::before{content:"☐ "}li[data-checked="1"]::before{content:"☑ "}' +
			'.flxg-tref{background:#eef2ff;border-radius:4px;padding:0 4px}.box{border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin:8px 0;page-break-inside:avoid}' +
			'.thumbs{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}.thumbs img{width:150px;height:110px;object-fit:cover}.muted{color:#6b7280}' +
			'@page{margin:16mm}@media print{body{margin:0;max-width:none}}' + (extraCss || '');
		w.document.open();
		w.document.write('<!doctype html><html lang="' + (B.locale || 'tr').slice(0, 2) + '"><head><meta charset="utf-8"><title></title><style>' + css + '</style></head><body></body></html>');
		w.document.close();
		w.document.title = title;
		w.document.body.innerHTML = bodyHtml;
		var imgs = Array.prototype.slice.call(w.document.images);
		var left = imgs.length;
		var printed = false;
		function go() { if (printed) return; printed = true; setTimeout(function () { w.focus(); w.print(); }, 150); }
		if (!left) go();
		imgs.forEach(function (im) { if (im.complete) { if (--left <= 0) go(); } else { im.onload = im.onerror = function () { if (--left <= 0) go(); }; } });
		setTimeout(go, 6000);
	};
	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
	Y.esc = esc;

	/* =====================================================================
	 * Sayfa: yönlendirici çağırır
	 * ================================================================== */
	/** #/doc/<id>[/p/<sayfa>] */
	Y.showDoc = function (main, docId, pageId) {
		docId = +docId;
		pageId = +pageId || 0;
		if (D && D.doc.id === docId && D.main === main && main.contains(D.root)) {
			if (pageId && (!D.page || D.page.id !== pageId)) openPage(pageId);
			else if (!pageId && D.page) Y.go('#/doc/' + docId + '/p/' + D.page.id, true);
			return Promise.resolve();
		}
		Y.leaveDoc();
		var known = Y.doc(docId);
		function setup(d) {
			D = {
				main: main, doc: d.doc, role: d.role, pages: d.pages, page: null, rev: 0, known: {}, order: [], others: [],
				edit: Y.rank(d.role) >= 3, err: 0, timer: null, timerAt: 0, inflight: false, titleDirty: false, lastInput: 0, seq: 0
			};
			buildShell();
			var first = pageId && pageById(pageId) ? pageId : (rootPages()[0] || {}).id;
			if (first) {
				if (first !== pageId) Y.go('#/doc/' + docId + '/p/' + first, true);
				else openPage(first);
			}
		}
		// Daha önce açılmış doküman beklemeden açılır; ağaç arkada tazelenir.
		var dc = DC[docId];
		if (dc) setup(dc);
		else Y.clear(main).appendChild(Y.skeleton('list'));
		return Y.api('GET', 'docs/' + docId, undefined, dc ? { quiet: true } : null).then(function (d) {
			DC[docId] = { doc: d.doc, role: d.role, pages: d.pages };
			if (!dc) { setup(d); return; }
			if (!D || D.doc.id !== docId) return;
			if (d.role !== D.role) { Y.leaveDoc(); setup(d); return; }
			D.pages = d.pages;
			D.doc = d.doc;
			drawTree();
			drawCrumbs();
			hydratePrefs(D.ed);
			drawSubs();
			if (D.page && !pageById(D.page.id)) Y.go('#/doc/' + docId, true);
		}, function (e) {
			delete DC[docId];
			if (dc) Y.leaveDoc();
			Y.fail(e);
			Y.clear(main).appendChild(h('.flxg-empty', h('p', e.message), known ? null : h('a.flxg-btn', { href: '#/home' }, t('Benim görevlerim'))));
		});
	};

	/** Doküman ekranından çıkarken: bekleyen değişiklikleri gönder, "buradayım" kaydını kaldır. */
	var DC = {};
	Y.leaveDoc = function () {
		if (!D) return;
		flushLeave(true);
		cachePage();
		DC[D.doc.id] = { doc: D.doc, role: D.role, pages: D.pages };
		clearTimeout(D.timer);
		aiClose();
		D = null;
	};
	function flushLeave(keepalive) {
		if (!D || !D.page) return;
		var c = D.edit ? collect() : { ops: [] };
		var body = { since: D.rev, leave: 1 };
		if (c.ops.length) body.ops = c.ops;
		if (D.titleDirty) body.title = D.titleEl.value;
		var url = B.rest + 'doc-pages/' + D.page.id + '/sync';
		url += (url.indexOf('?') > -1 ? '&' : '?') + '_locale=user';
		try {
			fetch(url, { method: 'POST', keepalive: !!keepalive, credentials: 'same-origin', headers: { 'X-WP-Nonce': B.nonce, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
		} catch (e) { /* yok */ }
	}
	window.addEventListener('pagehide', function () { if (D) flushLeave(true); });
	window.addEventListener('beforeunload', function (e) {
		if (!D || !D.edit || !D.page) return;
		if (collect().ops.length || D.titleDirty) { e.preventDefault(); e.returnValue = ''; }
	});
	document.addEventListener('visibilitychange', function () {
		if (!D) return;
		if (!document.hidden) schedule(200);
		else if (D.edit && collect().ops.length) schedule(0);
		else schedule(interval());
	});

	function pageById(id) { return D.pages.filter(function (p) { return p.id === +id; })[0] || null; }
	function kids(parent) { return D.pages.filter(function (p) { return p.parent === parent; }).sort(function (a, b) { return a.pos - b.pos || a.id - b.id; }); }
	function rootPages() { return kids(0); }

	/* =====================================================================
	 * Kabuk: üst çubuk, sayfa ağacı, araç çubuğu, editör
	 * ================================================================== */
	/** Bekleyen değişiklikleri gönderir; taslak sunucudaki son içerikten üretilir. */
	function flushNow() {
		return new Promise(function (done) {
			var t0 = Date.now();
			(function wait() {
				if (!D || !D.page) return done();
				var pending = D.edit && (collect().ops.length || D.titleDirty);
				if (!pending && !D.inflight) return done();
				if (Date.now() - t0 > 8000) return done();
				if (pending && !D.inflight) schedule(0);
				setTimeout(wait, 150);
			})();
		});
	}
	function mailDraft() {
		if (!D || !D.page) return;
		var btn = D.mailBtn;
		if (btn) btn.disabled = true;
		var pid = D.page.id;
		flushNow().then(function () {
			return Y.api('POST', 'doc-pages/' + pid + '/email-draft', {});
		}).then(function (r) {
			if (btn) btn.disabled = false;
			Y.toast(t('E-posta taslağı oluşturuldu.'), { ms: 9000, action: { label: t('Posta kutusunda aç'), fn: function () { window.open(r.url, '_blank'); } } });
		}, function (e) {
			if (btn) btn.disabled = false;
			Y.fail(e);
		});
	}

	function buildShell() {
		var main = D.main;
		Y.clear(main);
		D.crumbs = h('.flxg-crumbs');
		D.people = h('.flxg-doc-people');
		D.saveEl = h('span.flxg-doc-save');
		var treeBtn = h('button.flxg-icon-btn', { type: 'button', title: t('Sayfalar'), 'aria-label': t('Sayfalar'), onclick: toggleTree }, Y.icon('sidebar', 18));
		var more = h('button.flxg-icon-btn', { type: 'button', title: t('Diğer'), 'aria-label': t('Diğer'), onclick: function (e) {
			var doc = Y.doc(D.doc.id) || D.doc;
			var canDel = Y.rank(D.role) >= 4 || (D.edit && doc.owner_id === me());
			Y.menu(e.currentTarget, [
				D.edit ? { label: t('Dokümanı yeniden adlandır'), icon: 'edit', onClick: function () { renameDoc(D.doc); } } : null,
				D.edit ? { label: t('Alt sayfa ekle'), icon: 'plus', onClick: function () { addPage(D.page ? D.page.id : 0); } } : null,
				{ label: t('Bağlantıyı kopyala'), icon: 'link', onClick: function () { Y.copy(location.href); } },
				{ label: t('Yazdır / PDF'), icon: 'printer', onClick: printPage },
				Y.S.config.mailDraft ? { label: t('E-posta taslağı oluştur'), icon: 'send', onClick: mailDraft } : null,
				canDel ? { sep: true } : null,
				canDel ? { label: t('Dokümanı sil'), icon: 'trash', danger: true, onClick: function () { deleteDoc(D.doc); } } : null
			], { alignRight: true });
		} }, Y.icon('dots', 18));
		// Flux Mail varsa: sayfadan posta kutusunda taslak (alıcıyı orada seçip gönderirsiniz).
		D.mailBtn = Y.S.config.mailDraft ? h('button.flxg-btn.is-sm.flxg-doc-mailbtn', { type: 'button', title: t('Bu sayfadan e-posta taslağı oluştur'), onclick: mailDraft }, Y.icon('send', 15), h('span', t('E-posta taslağı'))) : null;
		D.aiBtn = aiOn() ? h('button.flxg-btn.is-sm.flxg-doc-aibtn', { type: 'button', title: t('Yapay zekâ (Ctrl+J)'), onclick: function (e) { openAIPage(e.currentTarget); } }, Y.icon('sparkle', 15), h('span', t('Yapay zekâ'))) : null;
		main.appendChild(Y.topBar(D.crumbs, [D.people, D.saveEl, D.aiBtn, D.mailBtn, treeBtn, more]));

		D.treeEl = h('.flxg-doc-pages');
		D.tree = h('aside.flxg-doc-tree', D.treeEl);
		D.treeShade = h('.flxg-doc-tree-shade', { onclick: function () { D.root.classList.remove('tree-open'); } });
		D.titleEl = h('textarea.flxg-doc-title', { rows: 1, placeholder: t('Başlıksız'), readonly: !D.edit });
		D.fitTitle = Y.autosize(D.titleEl);
		D.metaEl = h('.flxg-doc-meta');
		D.ed = h('.flxg-doc-ed.flxg-prose', { contenteditable: D.edit ? 'true' : 'false', spellcheck: 'true', 'data-ph': D.edit ? t('Yazmaya başlayın… "/" ile blok, "#" ile görev ekleyin') : '' });
		D.layer = h('.flxg-doc-layer', { 'aria-hidden': 'true' });
		D.gut = D.edit ? gutter() : null;
		D.tbl = D.edit ? tableUi() : null;
		D.wrap = h('.flxg-doc-edwrap', D.ed, D.layer, D.gut, D.tbl);
		D.subsEl = h('.flxg-doc-subs');
		D.tb = D.edit ? toolbar() : null;
		D.paper = h('.flxg-doc-paper', D.titleEl, D.metaEl, D.wrap, D.subsEl);
		D.scroll = h('.flxg-doc-scroll', D.tb, D.paper);
		// Alt sayfa önce sağda yarım panelde açılır (Notion gibi); solda üst sayfa görünür kalır.
		D.peekBg = h('.flxg-doc-peekbg', { onclick: peekClose });
		D.peekBar = h('.flxg-doc-peekbar',
			h('button.flxg-btn.is-sm', { type: 'button', title: t('Tam sayfa aç'), onclick: peekFull }, Y.icon('columns', 14), h('span', t('Tam sayfa'))),
			h('span.flxg-grow'),
			h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Kapat'), 'aria-label': t('Kapat'), onclick: peekClose }, Y.icon('x', 16)));
		D.scroll.insertBefore(D.peekBar, D.scroll.firstChild);
		D.root = h('.flxg-doc' + (Y.store('doc-tree') === '0' ? '.tree-hidden' : ''), D.tree, D.treeShade, D.peekBg, D.scroll);
		var w = +Y.store('doc-tree-w');
		if (w >= 180 && w <= 420) D.root.style.setProperty('--tree-w', w + 'px');
		D.tree.appendChild(treeResizer());
		D.scroll.addEventListener('scroll', bubbleSoon, { passive: true });
		main.appendChild(D.root);
		drawCrumbs();
		drawTree();
		bindEditor();
		setSave('');
	}
	function toggleTree() {
		if (Y.isMobile()) { D.root.classList.toggle('tree-open'); return; }
		var hid = !D.root.classList.contains('tree-hidden');
		D.root.classList.toggle('tree-hidden', hid);
		Y.store('doc-tree', hid ? '0' : null);
	}
	function treeResizer() {
		var grip = h('.flxg-doc-tree-resize');
		grip.addEventListener('pointerdown', function (e) {
			if (e.button !== 0) return;
			e.preventDefault();
			var sx = e.clientX;
			var sw = D.tree.getBoundingClientRect().width;
			var w = sw;
			document.body.classList.add('flxg-col-resizing');
			function mv(ev) { w = Math.round(Math.max(180, Math.min(420, sw + ev.clientX - sx))); D.root.style.setProperty('--tree-w', w + 'px'); }
			function up() {
				document.removeEventListener('pointermove', mv);
				document.removeEventListener('pointerup', up);
				document.body.classList.remove('flxg-col-resizing');
				Y.store('doc-tree-w', String(w));
			}
			document.addEventListener('pointermove', mv);
			document.addEventListener('pointerup', up);
		});
		return grip;
	}
	function drawCrumbs() {
		var sp = Y.space(D.doc.space_id);
		var f = D.doc.folder_id ? Y.folder(D.doc.folder_id) : null;
		Y.clear(D.crumbs).append(
			sp ? h('a.flxg-crumb.flxg-hide-sm', { href: '#/space/' + sp.id }, sp.name) : '',
			sp ? h('span.flxg-crumb-sep.flxg-hide-sm', '/') : '',
			f ? h('span.flxg-crumb.flxg-hide-sm', f.name) : '',
			f ? h('span.flxg-crumb-sep.flxg-hide-sm', '/') : '',
			h('span.flxg-doc-ic', Y.icon('doc', 16)),
			h('h1', D.doc.title)
		);
	}
	function setSave(state) {
		if (!D) return;
		D.saveState = state;
		var txt = { saving: t('Kaydediliyor…'), saved: t('Kaydedildi'), error: t('Kaydedilemedi, yeniden denenecek'), offline: t('Bağlantı yok') }[state] || '';
		D.saveEl.textContent = txt;
		D.saveEl.className = 'flxg-doc-save' + (state ? ' is-' + state : '');
		clearTimeout(D.saveFade);
		// "Kaydedildi" kalıcı durmasın; başkasının düzenlemesi sırasında "ben yazıyorum" gibi görünüyordu.
		if (state === 'saved') D.saveFade = setTimeout(function () { if (D && D.saveState === 'saved') setSave(''); }, 2500);
	}

	/* ---------------- Sayfa ağacı ---------------- */
	function colMap(v) {
		var m = Y.storeJSON('doc-col') || {};
		if (v === undefined) return m;
		Y.storeJSON('doc-col', v);
		return v;
	}
	function drawTree() {
		if (!D) return;
		var col = colMap();
		var box = Y.clear(D.treeEl);
		box.appendChild(h('.flxg-doc-tree-head',
			h('span.flxg-doc-ic', Y.icon('doc', 15)),
			h('b.flxg-doc-tree-title', { title: D.doc.title }, D.doc.title),
			D.edit ? h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Sayfa ekle'), onclick: function () { addPage(0); } }, Y.icon('plus', 15)) : null
		));
		var list = h('.flxg-doc-plist', { 'data-drop': '1' });
		function walk(parent, depth) {
			kids(parent).forEach(function (p) {
				var ch = kids(p.id);
				var closed = !!col[p.id];
				var active = D.page && D.page.id === p.id;
				var row = h('.flxg-doc-prow' + (active ? '.is-active' : ''), { 'data-id': p.id, style: { '--depth': depth } },
					ch.length ? h('button.flxg-caret', { type: 'button', 'aria-label': t('Daralt'), onclick: function (e) {
						e.stopPropagation();
						var m = colMap();
						if (m[p.id]) delete m[p.id]; else m[p.id] = 1;
						colMap(m);
						drawTree();
					} }, Y.icon(closed ? 'chevR' : 'chevD', 13)) : h('span.flxg-caret.is-leaf', Y.icon('file', 13)),
					h('a.flxg-doc-plink', { href: '#/doc/' + D.doc.id + '/p/' + p.id, onclick: function () { D.root.classList.remove('tree-open'); } }, p.title || t('Başlıksız')),
					D.edit ? h('button.flxg-icon-btn.is-xs.flxg-doc-padd', { type: 'button', title: t('Alt sayfa ekle'), onclick: function (e) { e.stopPropagation(); addPage(p.id); } }, Y.icon('plus', 13)) : null,
					D.edit ? h('button.flxg-icon-btn.is-xs.flxg-doc-pmore', { type: 'button', title: t('Sayfa menüsü'), onclick: function (e) { e.stopPropagation(); pageMenu(e.currentTarget, p); } }, Y.icon('dots', 13)) : null
				);
				list.appendChild(row);
				if (ch.length && !closed) walk(p.id, depth + 1);
			});
		}
		walk(0, 0);
		box.appendChild(list);
		if (D.edit) {
			box.appendChild(h('button.flxg-tree-add', { type: 'button', onclick: function () { addPage(0); } }, Y.icon('plus', 14), t('Sayfa ekle')));
			Y.dnd(list, { item: '.flxg-doc-prow', zone: '[data-drop]', onDrop: dropPage });
		}
	}
	/** Sürükleyip bırakma: bırakılan yerin üstündeki sayfanın kardeşi olur. */
	function dropPage(info) {
		var p = pageById(info.id);
		if (!p) return;
		var above = info.after ? pageById(info.after) : null;
		var below = info.before ? pageById(info.before) : null;
		var parent = above ? above.parent : below ? below.parent : 0;
		// Bırakılan yer kendi alt sayfalarının arasıysa yok say.
		for (var q = parent; q; q = (pageById(q) || {}).parent) { if (q === p.id) { drawTree(); return; } }
		var sib = kids(parent).filter(function (x) { return x.id !== p.id; });
		var ai = above && above.parent === parent ? sib.indexOf(above) : -1;
		var prevPos = ai > -1 ? sib[ai].pos : null;
		var nextPos = sib[ai + 1] ? sib[ai + 1].pos : null;
		movePage(p, parent, Y.between(prevPos, nextPos));
	}
	function movePage(p, parent, pos) {
		p.parent = parent;
		p.pos = pos;
		drawTree();
		Y.api('POST', 'doc-pages/' + p.id, { parent: parent, position: pos }).then(applyPages, function (e) { Y.fail(e); reloadTree(); });
	}
	function applyPages(r) {
		if (!D || !r) return;
		if (r.pages) D.pages = r.pages;
		if (r.doc) D.doc = r.doc;
		drawTree();
		hydratePrefs(D.ed);
		drawSubs();
	}
	function reloadTree() {
		if (!D) return;
		var id = D.doc.id;
		Y.api('GET', 'docs/' + id).then(function (d) {
			if (!D || D.doc.id !== id) return;
			D.pages = d.pages;
			D.doc = d.doc;
			drawTree();
			drawCrumbs();
			hydratePrefs(D.ed);
			drawSubs();
			if (D.page && !pageById(D.page.id)) Y.go('#/doc/' + id, true);
		}, function () { /* sonraki turda */ });
	}
	function addPage(parent) {
		if (parent && D.edit && D.page && D.page.id === parent) { subPage(); return; }
		Y.api('POST', 'docs/' + D.doc.id + '/pages', { parent: parent, title: '' }).then(function (r) {
			if (!D) return;
			applyPages(r);
			if (parent) { var m = colMap(); delete m[parent]; colMap(m); }
			Y.go('#/doc/' + D.doc.id + '/p/' + r.id);
			setTimeout(function () { if (D && D.titleEl) D.titleEl.focus(); }, 300);
		}, Y.fail);
	}
	function pageMenu(anchor, p) {
		var sib = kids(p.parent);
		var i = sib.indexOf(p);
		var parent = p.parent ? pageById(p.parent) : null;
		Y.menu(anchor, [
			{ label: t('Alt sayfa ekle'), icon: 'plus', onClick: function () { addPage(p.id); } },
			{ label: t('Yeniden adlandır'), icon: 'edit', onClick: function () {
				Y.prompt(t('Sayfa adı'), p.title).then(function (n) {
					if (n === null) return;
					p.title = n;
					drawTree();
					if (D.page && D.page.id === p.id) { D.titleEl.value = n; D.fitTitle(); }
					Y.api('POST', 'doc-pages/' + p.id, { title: n }).then(applyPages, Y.fail);
				});
			} },
			i > 0 ? { label: t('Üstteki sayfanın altına taşı'), icon: 'chevR', onClick: function () {
				var nk = kids(sib[i - 1].id);
				movePage(p, sib[i - 1].id, Y.between(nk.length ? nk[nk.length - 1].pos : null, null));
			} } : null,
			parent ? { label: t('Bir üst düzeye taşı'), icon: 'chevL', onClick: function () {
				var ps = kids(parent.parent);
				var pi = ps.indexOf(parent);
				movePage(p, parent.parent, Y.between(parent.pos, ps[pi + 1] ? ps[pi + 1].pos : null));
			} } : null,
			{ label: t('Bağlantıyı kopyala'), icon: 'link', onClick: function () { Y.copy(location.href.split('#')[0] + '#/doc/' + D.doc.id + '/p/' + p.id); } },
			{ sep: true },
			{ label: t('Sayfayı sil'), icon: 'trash', danger: true, onClick: function () {
				var n = 0;
				(function cnt(id) { kids(id).forEach(function (k) { n++; cnt(k.id); }); })(p.id);
				Y.confirm(t('Sayfayı sil'), n ? t('"{p}" sayfası ve {n} alt sayfası silinecek.', { p: p.title || t('Başlıksız'), n: n }) : t('"{p}" sayfası silinecek.', { p: p.title || t('Başlıksız') }), t('Sil'), true).then(function (ok) {
					if (!ok) return;
					Y.api('DELETE', 'doc-pages/' + p.id).then(function (r) {
						var wasOpen = D.page && (D.page.id === p.id || !r.pages.some(function (x) { return x.id === D.page.id; }));
						applyPages(r);
						if (wasOpen) { D.page = null; Y.go('#/doc/' + D.doc.id, true); }
					}, Y.fail);
				});
			} }
		]);
	}

	/* =====================================================================
	 * Sayfa yükleme
	 * ================================================================== */
	/** Açılmış sayfaların son sunucu hali: tekrar açılınca beklemeden gösterilir, arkada tazelenir. */
	var PC = {};
	function cachePage() {
		if (!D || !D.page || D.ed.classList.contains('is-loading')) return;
		var blocks = [];
		Array.prototype.forEach.call(D.ed.children, function (el) {
			var b = el.getAttribute('data-b');
			var k = D.known[b];
			if (k) blocks.push({ b: b, html: k.html, pos: k.pos, rev: k.rev });
		});
		delete PC[D.page.id];
		PC[D.page.id] = { page: D.page, rev: D.rev, blocks: blocks, presence: [] };
		var keys = Object.keys(PC);
		if (keys.length > 30) delete PC[keys[0]];
	}
	function openPage(pid) {
		if (D.page) { flushLeave(false); cachePage(); clearTimeout(D.timer); }
		var seq = ++D.seq;
		var cached = PC[pid] || null;
		D.page = pageById(pid) || { id: pid, title: '' };
		D.known = {};
		D.order = [];
		D.rev = 0;
		D.others = [];
		D.titleDirty = false;
		D.titleEl.value = D.page.title || '';
		D.fitTitle();
		Y.clear(D.layer);
		// Yeni sayfa her zaman en üstten başlar.
		D.scroll.scrollTop = 0;
		// Yalnız bağlantıdan açılınca yarım panel; ağaçtan/adres çubuğundan gelince tam sayfa.
		D.peek = D.peekNext && D.peekNext !== pid ? D.peekNext : null;
		D.peekNext = null;
		drawPeek();
		if (cached) paint(cached);
		else {
			Y.clear(D.ed);
			D.ed.classList.add('is-loading');
			D.ed.appendChild(h('.flxg-doc-skel', Y.sk('90%', 14), Y.sk('75%', 14), Y.sk('82%', 14), Y.sk('40%', 14)));
		}
		drawTree();
		drawPeople();
		return Y.api('GET', 'doc-pages/' + pid, undefined, cached ? { quiet: true } : null).then(function (d) {
			if (!D || seq !== D.seq) return;
			if (!cached) paint(d);
			else if (d.rev !== D.rev || (d.page.title || '') !== (D.page.title || '')) {
				// Arkada güncelleme geldi: yerelde yazılmamışsa sessizce yenile (kaydırma yerinde kalır), yazıldıysa eşitleme birleştirir.
				var c = D.edit ? collect() : { ops: [] };
				if (!c.ops.length && !D.titleDirty) { var st = D.scroll.scrollTop; paint(d); D.scroll.scrollTop = st; }
				else setPresence(d.presence || []);
			} else { D.page = d.page; drawMeta(); setPresence(d.presence || []); }
			schedule(300);
		}, function (e) {
			if (!D || seq !== D.seq) return;
			if (cached) { delete PC[pid]; if (e.status !== 404) { schedule(3000); return; } }
			Y.fail(e);
			D.ed.classList.remove('is-loading');
			Y.clear(D.ed).appendChild(h('.flxg-empty', h('p', e.message)));
		});
	}
	function peekOpen(pid) {
		if (!D || !D.page) return;
		D.peekNext = window.matchMedia('(max-width: 900px)').matches ? null : D.page.id;
		D.peekScroll = D.scroll.scrollTop;
		Y.go('#/doc/' + D.doc.id + '/p/' + pid);
	}
	function peekClose() {
		if (!D || !D.peek) return;
		var back = D.peek;
		Y.go('#/doc/' + D.doc.id + '/p/' + back);
	}
	function peekFull() {
		if (!D) return;
		D.peek = null;
		drawPeek();
	}
	/** Yarım panelde solda üst sayfanın son hali (salt okunur); tıklayınca panel kapanır. */
	function drawPeek() {
		D.root.classList.toggle('is-peek', !!D.peek);
		Y.clear(D.peekBg);
		if (!D.peek) return;
		var c = PC[D.peek];
		var pg = pageById(D.peek) || (c && c.page) || {};
		var body = h('.flxg-prose.flxg-doc-ed.flxg-doc-peekbody');
		(c ? c.blocks : []).forEach(function (b) { var el = fromHtml(b.html, b.b); el.removeAttribute('data-b'); body.appendChild(el); });
		D.peekBg.appendChild(h('.flxg-doc-paper.flxg-doc-peekpaper', h('.flxg-doc-title.is-static', pg.title || t('Başlıksız')), body));
		if (D.peekScroll) { D.peekBg.scrollTop = D.peekScroll; D.peekScroll = 0; }
	}
	function paint(d) {
		D.page = d.page;
		D.rev = d.rev;
		D.known = {};
		D.ed.classList.remove('is-loading');
		Y.clear(D.ed);
		d.blocks.forEach(function (b) {
			var el = fromHtml(b.html, b.b);
			D.ed.appendChild(el);
			D.known[b.b] = { html: cleanHtml(el), pos: b.pos, rev: b.rev };
		});
		if (!d.blocks.length && D.edit) D.ed.appendChild(fromHtml('', newId()));
		D.order = Object.keys(D.known).length ? blockIds() : [];
		D.titleEl.value = d.page.title || '';
		D.fitTitle();
		// Ortak Flux AI sohbeti sayfa adını görür ("bu sayfa").
		document.title = (d.page.title || t('Başlıksız')) + ' · ' + D.doc.title + ' ‹ ' + B.brand;
		drawMeta();
		setPresence(d.presence || []);
		refreshTaskRefs();
		markEmpty();
		drawSubs();
		setSave('');
		D.err = 0;
	}
	function drawMeta() {
		if (!D.page) return;
		var u = D.page.updatedBy ? user(D.page.updatedBy) : null;
		Y.clear(D.metaEl).append(u ? t('Son düzenleme: {n}, {t}', { n: u.name, t: Y.ago(D.page.updated) }) : '');
	}

	/* ---------------- Blok yardımcıları ---------------- */
	function fromHtml(html, b) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html || '';
		var el = tmp.firstElementChild;
		if (!el || !BLOCK[el.tagName] || tmp.children.length > 1) {
			el = document.createElement('p');
			el.innerHTML = html || '';
		}
		if (/^(P|H[1-4]|PRE|BLOCKQUOTE)$/.test(el.tagName) && !el.textContent && !el.querySelector('img,br,span')) el.innerHTML = '<br>';
		el.setAttribute('data-b', b);
		hydrate(el);
		return el;
	}
	function hydrate(root) {
		Y.$$('img[data-file]', root).forEach(function (img) { img.src = Y.fileUrl(img.getAttribute('data-file')); });
		Y.$$('a[data-file]', root).forEach(function (a) { a.href = Y.fileUrl(a.getAttribute('data-file'), 0, true); });
		Y.$$('span.flxg-tref', root).forEach(function (s) { s.setAttribute('contenteditable', 'false'); });
		hydratePrefs(root);
	}
	/** Blok HTML'i (geçici sınıf/öznitelikler olmadan); karşılaştırma ve gönderim için. */
	function cleanHtml(el) {
		var c = el.cloneNode(true);
		c.removeAttribute('data-b');
		c.removeAttribute('data-by');
		c.removeAttribute('class');
		if (c.style) {
			c.style.removeProperty('--uc');
			if (!c.getAttribute('style')) c.removeAttribute('style');
		}
		Y.$$('img', c).forEach(function (img) {
			if (!img.getAttribute('data-file')) { img.remove(); return; }
			img.removeAttribute('src');
			img.removeAttribute('class');
			img.removeAttribute('style');
		});
		Y.$$('a[data-file]', c).forEach(function (a) { a.removeAttribute('href'); });
		Y.$$('span.flxg-tref', c).forEach(function (s) { s.removeAttribute('data-done'); s.removeAttribute('title'); });
		Y.$$('span.flxg-pref', c).forEach(function (s) { s.removeAttribute('data-gone'); });
		Y.$$('[data-b]', c).forEach(function (x) { x.removeAttribute('data-b'); });
		return c.outerHTML;
	}
	function blockIds() { return Array.prototype.map.call(D.ed.children, function (el) { return el.getAttribute('data-b'); }); }
	function blockById(b) { return b ? D.ed.querySelector(':scope > [data-b="' + b + '"]') : null; }
	function blockOf(node) {
		while (node && node.parentNode !== D.ed) node = node.parentNode;
		return node && node.nodeType === 1 ? node : null;
	}
	/** Editörün doğrudan çocuklarını blok yapar: yalın metin/öğe paragrafa sarılır, div → p, kimliksiz/tekrarlı kimlik yenilenir. */
	function normalize() {
		var ed = D.ed;
		var seen = {};
		var caret = null;
		var nodes = Array.prototype.slice.call(ed.childNodes);
		var nested = Y.$$(':scope > p', ed).filter(function (p) { return Array.prototype.some.call(p.children, function (c) { return BLOCK[c.tagName]; }); });
		var needCaret = nested.length || nodes.some(function (n) { return n.nodeType !== 1 || !BLOCK[n.tagName]; });
		if (needCaret) caret = rawCaret();
		// Chrome listeyi/tabloyu paragrafın içine koyabiliyor: <p><ul>…</ul></p> → ayrı bloklar.
		nested.forEach(function (p) {
			var run2 = null;
			Array.prototype.slice.call(p.childNodes).forEach(function (n) {
				if (n.nodeType === 1 && BLOCK[n.tagName]) {
					run2 = null;
					if (n.tagName === 'P' && !n.textContent && !n.querySelector('img,br,span')) { n.remove(); return; }
					ed.insertBefore(n, p);
					return;
				}
				if (n.nodeType === 3 && !n.textContent.trim() && !run2) { n.remove(); return; }
				if (!run2) { run2 = document.createElement('p'); ed.insertBefore(run2, p); }
				run2.appendChild(n);
			});
			p.remove();
		});
		nodes = Array.prototype.slice.call(ed.childNodes);
		var run = null;
		nodes.forEach(function (n) {
			if (n.nodeType === 3 && !n.textContent.trim() && !run) { n.remove(); return; }
			if (n.nodeType === 1 && n.tagName === 'DIV') {
				var p = document.createElement('p');
				while (n.firstChild) p.appendChild(n.firstChild);
				if (!p.firstChild) p.innerHTML = '<br>';
				if (n.getAttribute('data-b')) p.setAttribute('data-b', n.getAttribute('data-b'));
				ed.replaceChild(p, n);
				run = null;
				return;
			}
			if (n.nodeType === 1 && n.tagName === 'IMG') {
				var fig = document.createElement('figure');
				ed.replaceChild(fig, n);
				fig.appendChild(n);
				run = null;
				return;
			}
			if (n.nodeType !== 1 || !BLOCK[n.tagName]) {
				if (n.nodeType === 1 && n.tagName === 'BR' && !run) { n.remove(); return; }
				if (!run) { run = document.createElement('p'); ed.insertBefore(run, n); }
				run.appendChild(n);
				return;
			}
			run = null;
		});
		Array.prototype.forEach.call(ed.children, function (el) {
			var b = el.getAttribute('data-b');
			if (!b || seen[b]) { b = newId(); el.setAttribute('data-b', b); }
			seen[b] = 1;
			if (el.tagName === 'UL' && el.hasAttribute('data-checklist')) Y.$$(':scope > li', el).forEach(function (li) { if (!li.hasAttribute('data-checked')) li.setAttribute('data-checked', '0'); });
		});
		if (!ed.children.length) ed.appendChild(fromHtml('', newId()));
		if (caret) restoreRaw(caret);
	}
	/** Düğümler taşınırken canlı aralık sıfırlanır; metin düğümü + sıra saklanır. */
	function rawCaret() {
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount) return null;
		var r = sel.getRangeAt(0);
		if (!D.ed.contains(r.startContainer)) return null;
		return { n: r.startContainer, o: r.startOffset };
	}
	function restoreRaw(c) {
		if (!c.n.isConnected || !D.ed.contains(c.n)) return;
		var sel = window.getSelection();
		var r = document.createRange();
		try { r.setStart(c.n, Math.min(c.o, c.n.nodeType === 3 ? c.n.length : c.n.childNodes.length)); } catch (e) { return; }
		r.collapse(true);
		sel.removeAllRanges();
		sel.addRange(r);
	}

	/* ---------------- İmleç konumu: blok + karakter sırası ---------------- */
	function textOffset(block, node, off) {
		var r = document.createRange();
		r.setStart(block, 0);
		try { r.setEnd(node, off); } catch (e) { return 0; }
		return r.toString().length;
	}
	function pointAt(block, off) {
		var w = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
		var n;
		var last = null;
		while ((n = w.nextNode())) {
			if (off <= n.length) return { node: n, off: off };
			off -= n.length;
			last = n;
		}
		return last ? { node: last, off: last.length } : { node: block, off: 0 };
	}
	function getCaret() {
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount) return null;
		var r = sel.getRangeAt(0);
		if (!D.ed.contains(r.startContainer)) return null;
		var sb = blockOf(r.startContainer);
		var eb = blockOf(r.endContainer);
		if (!sb || !sb.getAttribute('data-b')) return null;
		var c = { b: sb.getAttribute('data-b'), o: textOffset(sb, r.startContainer, r.startOffset) };
		if (!r.collapsed && eb && eb.getAttribute('data-b')) { c.e = eb.getAttribute('data-b'); c.eo = textOffset(eb, r.endContainer, r.endOffset); }
		if (Date.now() - D.lastInput < 4000) c.w = 1;
		return c;
	}
	function setCaret(c) {
		var sel = window.getSelection();
		if (!sel) return;
		var bl = blockById(c.b);
		if (!bl) return;
		var p = pointAt(bl, c.o);
		var r = document.createRange();
		try { r.setStart(p.node, p.off); } catch (e) { return; }
		r.collapse(true);
		sel.removeAllRanges();
		sel.addRange(r);
	}

	/* =====================================================================
	 * Ortak düzenleme: topla → gönder → başkalarının değişikliklerini uygula
	 * ================================================================== */
	/** Değişen bloklar: içerik farkı, sıra farkı (yalnız bilinen bloklar arasında), silinenler. */
	function collect() {
		normalize();
		var ops = [];
		var html = {};
		var order = [];
		var prevAny = '';
		var prevKnown = '';
		var lastPrev = {};
		var present = {};
		Array.prototype.forEach.call(D.ed.children, function (el) { present[el.getAttribute('data-b')] = 1; });
		var lp = '';
		D.order.forEach(function (b) { if (!present[b] || !D.known[b]) return; lastPrev[b] = lp; lp = b; });
		Array.prototype.forEach.call(D.ed.children, function (el) {
			var b = el.getAttribute('data-b');
			order.push(b);
			var k = D.known[b];
			var cur = cleanHtml(el);
			var op = null;
			if (!k || k.html !== cur) { op = { b: b, html: cur }; html[b] = cur; }
			if (!k || lastPrev[b] !== prevKnown) { op = op || { b: b }; op.after = prevAny; }
			if (op) ops.push(op);
			prevAny = b;
			if (k) prevKnown = b;
		});
		Object.keys(D.known).forEach(function (b) { if (!present[b]) ops.push({ b: b, del: 1 }); });
		return { ops: ops, html: html, order: order };
	}
	function schedule(ms) {
		if (!D) return;
		clearTimeout(D.timer);
		D.timerAt = Date.now() + ms;
		D.timer = setTimeout(tick, ms);
	}
	/** Yazarken: en geç ms sonra gönder (sürekli yazmak gönderimi geciktirmesin). */
	function soon(ms) {
		if (!D) return;
		if (D.inflight) { D.again = true; return; }
		if (D.timer && D.timerAt <= Date.now() + ms) return;
		schedule(ms);
	}
	/** Yoklama aralığı: başkası varken sık, yalnızken seyrek; sekme gizliyse durur (Hostinger istek sınırı). */
	function interval() {
		if (document.hidden) return 20000;
		var active = Date.now() - D.lastInput < 30000;
		// Biri yazarken sık, kimse yazmıyorsa seyrek: toplam istek sayısı artmadan gecikme düşer.
		if (D.others.some(function (p) { return p.cursor && p.cursor.w; })) return 1500;
		if (D.others.length) return active ? 2000 : 4000;
		if (Date.now() - D.lastInput > 300000) return 15000;
		return active ? 4000 : 8000;
	}
	function tick() {
		if (!D || !D.page || D.ed.classList.contains('is-loading')) return;
		D.timer = null;
		if (D.inflight) { D.again = true; return; }
		var c = D.edit ? collect() : { ops: [], html: {}, order: [] };
		var pid = D.page.id;
		var seq = D.seq;
		var body = { since: Math.max(0, D.rev - 8) };
		var cur = getCaret();
		if (cur) body.cursor = cur;
		if (c.ops.length) body.ops = c.ops;
		var titleSent = null;
		if (D.titleDirty) { titleSent = D.titleEl.value; body.title = titleSent; }
		if (c.ops.length || titleSent !== null) setSave('saving');
		D.inflight = true;
		D.again = false;
		Y.api('POST', 'doc-pages/' + pid + '/sync', body, { quiet: true }).then(function (r) {
			if (!D || D.seq !== seq) return;
			D.inflight = false;
			D.err = 0;
			var sp = r.savedPos || {};
			c.ops.forEach(function (op) {
				if (op.del) { delete D.known[op.b]; return; }
				var k = D.known[op.b] || (D.known[op.b] = { html: '', pos: 0, rev: 0 });
				if (op.html !== undefined) k.html = op.html;
				if (sp[op.b]) { k.pos = sp[op.b].p; k.rev = sp[op.b].r; }
			});
			if (c.ops.length) D.order = c.order;
			if (titleSent !== null && D.titleEl.value === titleSent) D.titleDirty = false;
			if (titleSent !== null) {
				var pg = pageById(pid);
				if (pg) pg.title = titleSent;
				drawTree();
			}
			applyChanges(r.changes || []);
			D.rev = Math.max(D.rev, r.rev || 0);
			setPresence(r.presence || []);
			if (!D.titleDirty && document.activeElement !== D.titleEl && typeof r.title === 'string' && r.title !== D.titleEl.value) {
				D.titleEl.value = r.title;
				D.fitTitle();
				var pg2 = pageById(pid);
				if (pg2) { pg2.title = r.title; drawTree(); }
			}
			if (r.treeRev && r.treeRev !== D.doc.treeRev) { D.doc.treeRev = r.treeRev; reloadTree(); }
			if (c.ops.length || titleSent !== null) {
				setSave('saved');
				D.page.updated = new Date().toISOString();
				D.page.updatedBy = me();
				drawMeta();
			}
			schedule(r.more || D.again ? 60 : interval());
		}, function (e) {
			if (!D || D.seq !== seq) return;
			D.inflight = false;
			D.err++;
			setSave(c.ops.length || titleSent !== null ? 'error' : navigator.onLine === false ? 'offline' : D.saveState);
			if (e.status === 403 && D.edit) { D.edit = false; D.ed.setAttribute('contenteditable', 'false'); Y.fail(e); return; }
			if (e.status === 404) { Y.fail(e); reloadTree(); return; }
			var busy = e.status === 429 || e.status === 503;
			schedule(Math.min(300000, (busy ? 20000 : 4000) * Math.pow(2, Math.min(D.err - 1, 5))));
		});
	}
	/** Başkalarının değişiklikleri. Yerelde gönderilmemiş değişikliği olan blok ezilmez. */
	function applyChanges(list) {
		if (!list.length) return;
		var caret = getCaret();
		var touched = false;
		var last = 0;
		list.forEach(function (c) {
			var k = D.known[c.b];
			if (k && k.rev >= c.rev) return;
			var el = blockById(c.b);
			if (c.del) {
				if (el && (!k || cleanHtml(el) === k.html)) { el.remove(); touched = true; }
				delete D.known[c.b];
				return;
			}
			if (el && k && cleanHtml(el) !== k.html) return;
			var nel = fromHtml(c.html, c.b);
			var u = user(c.user);
			if (el) {
				if (cleanHtml(el) !== cleanHtml(nel)) { D.ed.replaceChild(nel, el); flash(nel, u); }
				else nel = el;
			} else {
				flash(nel, u);
			}
			D.known[c.b] = { html: cleanHtml(nel), pos: c.pos, rev: c.rev };
			place(nel, c.pos);
			touched = true;
			if (c.user) last = c.user;
		});
		if (!touched) return;
		if (last && last !== me()) {
			D.page.updatedBy = last;
			D.page.updated = new Date().toISOString();
			drawMeta();
		}
		D.order = blockIds().filter(function (b) { return D.known[b]; });
		if (caret && blockById(caret.b) && document.activeElement === D.ed) setCaret(caret);
		refreshTaskRefs();
		markEmpty();
		drawCursors();
	}
	/** Bloğu sunucu sırasına göre yerleştirir; zaten doğru yerdeyse dokunmaz. */
	function place(el, pos) {
		var prev = el.previousElementSibling;
		var next = el.nextElementSibling;
		function kp(x) { var k = x && D.known[x.getAttribute('data-b')]; return k ? k.pos : null; }
		var ok = el.parentNode === D.ed;
		while (ok && prev && kp(prev) === null) prev = prev.previousElementSibling;
		while (ok && next && kp(next) === null) next = next.nextElementSibling;
		if (ok && (!prev || kp(prev) < pos) && (!next || kp(next) > pos)) return;
		var target = null;
		Array.prototype.some.call(D.ed.children, function (x) {
			if (x === el) return false;
			var p = kp(x);
			if (p !== null && p > pos) { target = x; return true; }
			return false;
		});
		D.ed.insertBefore(el, target);
	}
	function flash(el, u) {
		el.classList.add('is-remote');
		el.style.setProperty('--uc', u.color);
		el.setAttribute('data-by', u.name);
		clearTimeout(el._flash);
		el._flash = setTimeout(function () {
			el.classList.remove('is-remote');
			el.removeAttribute('data-by');
			el.style.removeProperty('--uc');
			if (!el.getAttribute('style')) el.removeAttribute('style');
			if (!el.getAttribute('class')) el.removeAttribute('class');
		}, 2600);
	}

	/* ---------------- Kimler burada, imleçleri ---------------- */
	function setPresence(list) {
		D.others = list.filter(function (p) { return p.user !== me(); });
		drawPeople();
		drawCursors();
	}
	function drawPeople() {
		if (!D) return;
		Y.clear(D.people);
		D.others.slice(0, 5).forEach(function (p) {
			var u = user(p.user);
			var av = Y.avatar(u, 26);
			av.classList.add('flxg-doc-person');
			av.style.setProperty('--uc', u.color);
			av.title = u.name + (p.cursor && p.cursor.w ? ' · ' + t('yazıyor') : '');
			if (p.cursor && p.cursor.b) av.addEventListener('click', function () { var b = blockById(p.cursor.b); if (b) b.scrollIntoView({ block: 'center', behavior: 'smooth' }); });
			D.people.appendChild(av);
		});
		if (D.others.length > 5) D.people.appendChild(h('span.flxg-av.is-more', { style: { width: '26px', height: '26px' } }, '+' + (D.others.length - 5)));
	}
	function drawCursors() {
		if (!D || !D.layer) return;
		Y.clear(D.layer);
		var wr = D.wrap.getBoundingClientRect();
		D.others.forEach(function (p) {
			var c = p.cursor;
			if (!c || !c.b) return;
			var sb = blockById(c.b);
			if (!sb) return;
			var u = user(p.user);
			var r = document.createRange();
			var s = pointAt(sb, c.o || 0);
			try { r.setStart(s.node, s.off); } catch (e) { return; }
			r.collapse(true);
			var eb = c.e ? blockById(c.e) : null;
			if (eb) {
				var e2 = pointAt(eb, c.eo || 0);
				try { r.setEnd(e2.node, e2.off); } catch (e) { r.collapse(true); }
			}
			if (!r.collapsed) {
				Array.prototype.forEach.call(r.getClientRects(), function (x) {
					if (x.width < 1) return;
					D.layer.appendChild(h('.flxg-doc-sel', { style: { left: x.left - wr.left + 'px', top: x.top - wr.top + 'px', width: x.width + 'px', height: x.height + 'px', background: u.color } }));
				});
			}
			var end = r.cloneRange();
			end.collapse(false);
			var rects = end.getClientRects();
			var rc = rects.length ? rects[rects.length - 1] : null;
			if (!rc || (!rc.height && !rc.top)) {
				var br = (eb || sb).getBoundingClientRect();
				rc = { left: br.left, top: br.top, height: Math.min(br.height || 20, 22) };
			}
			D.layer.appendChild(h('.flxg-doc-caret' + (c.w ? '.is-typing' : ''), { style: { left: rc.left - wr.left + 'px', top: rc.top - wr.top + 'px', height: (rc.height || 20) + 'px', '--uc': u.color } },
				h('span.flxg-doc-caret-name', u.name)));
		});
	}
	window.addEventListener('resize', Y.debounce(function () { if (D) drawCursors(); }, 150));

	/* ---------------- Görev bağlantıları: güncel ad ve durum ---------------- */
	function refreshTaskRefs() {
		var refs = Y.$$('span.flxg-tref[data-task]', D.ed);
		if (!refs.length) return;
		var ids = {};
		refs.forEach(function (s) { ids[s.getAttribute('data-task')] = 1; });
		var key = Object.keys(ids).sort().join(',');
		var cache = D.refCache || {};
		function paint() {
			refs.forEach(function (s) {
				var x = cache[s.getAttribute('data-task')];
				if (!x) return;
				s.setAttribute('title', x.title + (x.list && Y.list(x.list) ? ' · ' + Y.list(x.list).name : ''));
				if (Y.isDone(x)) s.setAttribute('data-done', '1'); else s.removeAttribute('data-done');
			});
		}
		if (D.refKey === key) { paint(); return; }
		D.refKey = key;
		Y.api('GET', 'tasks', { ids: key }).then(function (r) {
			if (!D) return;
			(r.tasks || []).forEach(function (x) { cache[x.id] = x; });
			D.refCache = cache;
			paint();
		}, function () { /* yok */ });
	}

	/* =====================================================================
	 * Editör olayları
	 * ================================================================== */
	function bindEditor() {
		var ed = D.ed;
		try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* yok */ }
		D.titleEl.addEventListener('input', function () {
			D.titleDirty = true;
			D.lastInput = Date.now();
			soon(900);
		});
		D.titleEl.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				ed.focus({ preventScroll: true });
				var first = ed.firstElementChild;
				if (first) setCaret({ b: first.getAttribute('data-b'), o: 0 });
			}
		});
		ed.addEventListener('input', function (e) {
			D.lastInput = Date.now();
			if (e.inputType !== 'insertText' && e.inputType !== 'insertCompositionText') normalize();
			if (mdCheck(e)) { markEmpty(); soon(700); return; }
			markEmpty();
			soon(D.others.length ? 700 : 1500);
			requestAnimationFrame(drawCursors);
			slashCheck();
		});
		ed.addEventListener('keydown', onKey);
		ed.addEventListener('mousedown', function (e) { if (e.button === 0) { D.dragging = true; if (D.aiBub) D.aiBub.classList.remove('is-on'); } });
		ed.addEventListener('click', onClick);
		ed.addEventListener('paste', onPaste);
		ed.addEventListener('dragover', function (e) { if (D.edit && hasFiles(e)) { e.preventDefault(); } });
		ed.addEventListener('drop', function (e) {
			if (!D.edit || !hasFiles(e)) return;
			e.preventDefault();
			if (document.caretRangeFromPoint) {
				var r = document.caretRangeFromPoint(e.clientX, e.clientY);
				if (r && ed.contains(r.startContainer)) { var sl = window.getSelection(); sl.removeAllRanges(); sl.addRange(r); }
			}
			insertFiles(Array.prototype.slice.call(e.dataTransfer.files || []));
		});
		ed.addEventListener('focus', function () { if (D.tb) D.tb.classList.add('is-focus'); });
		if (D.edit) {
			// Sayfanın altındaki boşluğa tıklayınca son satıra geçer.
			D.paper.addEventListener('mousedown', function (e) {
				if (e.target !== D.paper || e.clientY < D.ed.getBoundingClientRect().bottom) return;
				e.preventDefault();
				var last = D.ed.lastElementChild;
				D.ed.focus({ preventScroll: true });
				if (last) setCaret({ b: last.getAttribute('data-b'), o: last.textContent.length });
			});
			D.scroll.addEventListener('pointermove', gutMove, { passive: true });
			D.scroll.addEventListener('pointerleave', function () { if (D && !D.gutDrag) gutShow(null); });
			ed.addEventListener('keydown', function () { if (D.gut) D.gut.classList.remove('is-on'); tblHide(); });
		}
	}
	document.addEventListener('selectionchange', function () {
		if (!D || !D.ed || !D.ed.isConnected) return;
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount || !D.ed.contains(sel.anchorNode)) return;
		if (D.tb) updateToolbar();
		D.lastBlock = blockOf(sel.anchorNode);
		var c = getCaret();
		var key = c ? c.b + ':' + c.o + ':' + (c.e || '') + ':' + (c.eo || 0) : '';
		if (key !== D.lastCaret) { D.lastCaret = key; if (D.others.length) soon(1200); }
	});
	function markEmpty() {
		var only = D.ed.children.length === 1 ? D.ed.firstElementChild : null;
		D.ed.classList.toggle('is-empty', !D.ed.children.length || (!!only && only.tagName === 'P' && !only.textContent && !only.querySelector('img,span,a')));
	}
	function hasFiles(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') > -1; }

	function onKey(e) {
		if (D.slash && D.slash.key(e)) return;
		if (e.key === 'Tab') {
			var cell = curCell();
			if (cell) {
				e.preventDefault();
				var cells = Y.$$('td,th', cell.closest('table'));
				var i = cells.indexOf(cell) + (e.shiftKey ? -1 : 1);
				if (i >= cells.length) { tableOp('row-below'); i = cells.length; cells = Y.$$('td,th', cell.closest('table')); }
				if (cells[i]) selectNode(cells[i]);
				return;
			}
			if (inTag('LI')) { e.preventDefault(); document.execCommand(e.shiftKey ? 'outdent' : 'indent'); return; }
		}
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); makeLink(); return; }
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); schedule(0); return; }
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'j' && aiOn()) { e.preventDefault(); if (window.getSelection().isCollapsed) openAIPage(); else openAISel(); return; }
		if (e.key === '#' && !e.ctrlKey && !e.metaKey && !atLineStart()) {
			// "#" yazıldıktan sonra görev seçici açılır; seçilmezse "#" metin olarak kalır.
			setTimeout(function () { taskPicker(true); }, 0);
		}
	}
	function onClick(e) {
		var ref = e.target.closest('span.flxg-tref[data-task]');
		if (ref) { e.preventDefault(); Y.openTask(+ref.getAttribute('data-task')); return; }
		var pref = e.target.closest('span.flxg-pref[data-page]');
		if (pref) { e.preventDefault(); if (!pref.hasAttribute('data-gone')) peekOpen(+pref.getAttribute('data-page')); return; }
		var a = e.target.closest('a[href]');
		if (a) {
			if (!D.edit || e.ctrlKey || e.metaKey || a.hasAttribute('data-file')) { e.preventDefault(); window.open(a.href, '_blank', 'noopener'); return; }
			linkPop(a);
			return;
		}
		var img = e.target.closest('img[data-file]');
		if (img) {
			if (!D.edit) { Y.lightbox([{ id: +img.getAttribute('data-file'), name: img.alt || '', image: true, url: img.src }], 0); return; }
			imagePop(img);
			return;
		}
		var li = e.target.closest('ul[data-checklist] > li');
		if (li && D.edit) {
			var r = li.getBoundingClientRect();
			if (e.clientX - r.left > 26) return;
			li.setAttribute('data-checked', li.getAttribute('data-checked') === '1' ? '0' : '1');
			D.lastInput = Date.now();
			soon(500);
		}
	}
	function linkPop(a) {
		var box = h('.flxg-doc-linkpop',
			h('a.flxg-doc-linkurl', { href: a.href, target: '_blank', rel: 'noopener' }, a.href),
			h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Düzenle'), onclick: function () { Y.closePop(); selectNode(a, true); makeLink(a.getAttribute('href')); } }, Y.icon('edit', 14)),
			h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Bağlantıyı kaldır'), onclick: function () { Y.closePop(); selectNode(a, true); document.execCommand('unlink'); } }, Y.icon('x', 14))
		);
		Y.pop(a, box, { inline: true, noFocus: true });
	}
	function imagePop(img) {
		Y.menu(img, [
			{ label: t('Küçük'), checked: img.getAttribute('width') === '240', onClick: function () { img.setAttribute('width', '240'); changed(); } },
			{ label: t('Orta'), checked: img.getAttribute('width') === '420', onClick: function () { img.setAttribute('width', '420'); changed(); } },
			{ label: t('Tam genişlik'), checked: !img.getAttribute('width'), onClick: function () { img.removeAttribute('width'); changed(); } },
			{ sep: true },
			{ label: t('Büyüt'), icon: 'eye', onClick: function () { Y.lightbox([{ id: +img.getAttribute('data-file'), name: img.alt || '', image: true, url: img.src }], 0); } },
			{ label: t('Görseli kaldır'), icon: 'trash', danger: true, onClick: function () { var b = blockOf(img); if (b && b.tagName === 'FIGURE') b.remove(); else img.remove(); changed(); } }
		], { inline: true });
	}
	function changed() { D.lastInput = Date.now(); soon(400); requestAnimationFrame(drawCursors); requestAnimationFrame(drawSubs); }
	function pulse(el) { el.classList.add('is-ai-new'); setTimeout(function () { el.classList.remove('is-ai-new'); if (!el.getAttribute('class')) el.removeAttribute('class'); }, 1600); }
	function selectNode(n, whole) {
		var r = document.createRange();
		if (whole) r.selectNode(n);
		else { r.selectNodeContents(n); r.collapse(true); }
		var s = window.getSelection();
		s.removeAllRanges();
		s.addRange(r);
	}
	function curNode() {
		var s = window.getSelection();
		if (!s || !s.rangeCount || !D.ed.contains(s.anchorNode)) return null;
		var n = s.anchorNode;
		return n.nodeType === 1 ? n : n.parentElement;
	}
	function inTag(tag) { var n = curNode(); return !!(n && n.closest(tag) && D.ed.contains(n.closest(tag))); }
	function curCell() { var n = curNode(); var c = n && n.closest('td,th'); return c && D.ed.contains(c) ? c : null; }

	/* ---------------- Yapıştırma: izinli etiketlere temizle ---------------- */
	var KEEP = { P: 1, BR: 1, B: 1, STRONG: 1, I: 1, EM: 1, U: 1, S: 1, STRIKE: 1, DEL: 1, MARK: 1, SUB: 1, SUP: 1, H1: 1, H2: 1, H3: 1, H4: 1, UL: 1, OL: 1, LI: 1, BLOCKQUOTE: 1, CODE: 1, PRE: 1, HR: 1, A: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TD: 1, TH: 1 };
	function sanitize(html) {
		var doc = new DOMParser().parseFromString(html, 'text/html');
		(function walk(node) {
			Array.prototype.slice.call(node.childNodes).forEach(function (n) {
				if (n.nodeType === 8) { n.remove(); return; }
				if (n.nodeType !== 1) return;
				if (/^(SCRIPT|STYLE|META|LINK|TITLE|IMG|SVG|IFRAME|OBJECT|VIDEO|AUDIO|FORM|INPUT|BUTTON|SELECT|TEXTAREA)$/.test(n.tagName)) { n.remove(); return; }
				walk(n);
				if (!KEEP[n.tagName]) {
					var frag = document.createDocumentFragment();
					if (n.tagName === 'DIV' || n.tagName === 'SECTION' || n.tagName === 'ARTICLE') { var p = document.createElement('p'); while (n.firstChild) p.appendChild(n.firstChild); frag.appendChild(p); }
					else while (n.firstChild) frag.appendChild(n.firstChild);
					n.replaceWith(frag);
					return;
				}
				Array.prototype.slice.call(n.attributes).forEach(function (a) {
					if (n.tagName === 'A' && a.name === 'href' && /^(https?:|mailto:|tel:)/i.test(a.value)) return;
					if ((n.tagName === 'TD' || n.tagName === 'TH') && (a.name === 'colspan' || a.name === 'rowspan')) return;
					n.removeAttribute(a.name);
				});
				if (n.tagName === 'A') { n.setAttribute('target', '_blank'); n.setAttribute('rel', 'noopener'); }
			});
		})(doc.body);
		return doc.body.innerHTML;
	}
	function onPaste(e) {
		if (!D.edit) return;
		var cd = e.clipboardData;
		var files = Array.prototype.slice.call((cd && cd.files) || []);
		if (files.length) {
			e.preventDefault();
			insertFiles(files.map(function (f, i) { return f.name && f.name !== 'image.png' ? f : new File([f], 'gorsel-' + Date.now() + '-' + i + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }); }));
			return;
		}
		var html = cd && cd.getData('text/html');
		if (html && !curCell() && !inTag('PRE')) {
			e.preventDefault();
			document.execCommand('insertHTML', false, sanitize(html));
		} else if (html) {
			e.preventDefault();
			document.execCommand('insertText', false, cd.getData('text/plain'));
		}
		changed();
	}

	/* ---------------- Görsel ve dosya ekleme ---------------- */
	function curBlock() { var n = curNode(); return n ? blockOf(n) : D.ed.lastElementChild; }
	function insertBlockAfter(el, ref) {
		ref = ref || curBlock();
		if (ref && ref.parentNode === D.ed) {
			// Boş paragrafın yerine geçer.
			if (ref.tagName === 'P' && !ref.textContent.trim() && !ref.querySelector('img,span')) { D.ed.replaceChild(el, ref); return el; }
			D.ed.insertBefore(el, ref.nextSibling);
		} else D.ed.appendChild(el);
		return el;
	}
	function ensureParaAfter(el) {
		var n = el.nextElementSibling;
		if (!n || n.tagName !== 'P') { n = fromHtml('', newId()); D.ed.insertBefore(n, el.nextSibling); }
		return n;
	}
	function insertFiles(files) {
		if (!D.edit) return;
		var docId = D.doc.id;
		var ref = curBlock();
		files.forEach(function (file) {
			var isImg = /^image\//.test(file.type);
			var el;
			if (isImg) {
				el = h('figure', { 'data-b': newId() }, h('img.is-uploading', { src: URL.createObjectURL(file), alt: file.name || '' }));
			} else {
				el = h('p', { 'data-b': newId() }, h('a.is-uploading', { 'data-file': '' }, Y.icon('clip', 13), ' ' + (file.name || t('Dosya'))));
			}
			insertBlockAfter(el, ref);
			ref = el;
			Y.upload(file, { doc: docId }).then(function (f) {
				if (!D || D.doc.id !== docId || !el.isConnected) return;
				if (isImg) {
					var img = el.querySelector('img');
					img.setAttribute('data-file', f.id);
					img.classList.remove('is-uploading');
					if (!img.getAttribute('class')) img.removeAttribute('class');
				} else {
					var a = el.querySelector('a');
					Y.clear(a).append(f.name);
					a.setAttribute('data-file', f.id);
					a.removeAttribute('class');
					a.href = Y.fileUrl(f.id, 0, true);
				}
				changed();
			}, function (e) { Y.fail(e); if (el.isConnected) el.remove(); });
		});
		if (ref) { var p = ensureParaAfter(ref); setCaret({ b: p.getAttribute('data-b'), o: 0 }); }
		changed();
	}
	function pickFiles(accept) {
		var inp = h('input', { type: 'file', multiple: true, accept: accept || null, style: { display: 'none' } });
		inp.addEventListener('change', function () { insertFiles(Array.prototype.slice.call(inp.files || [])); inp.remove(); });
		document.body.appendChild(inp);
		inp.click();
	}

	/* ---------------- Biçim komutları ---------------- */
	/** Seçili metni satır içi kod yapar ya da kod biçimini kaldırır. */
	function inlineCode() {
		var sel = window.getSelection();
		if (!sel.rangeCount || sel.isCollapsed) return;
		var n = curNode();
		var c = n && n.closest('code');
		if (c && D.ed.contains(c) && !c.closest('pre')) { var tx = document.createTextNode(c.textContent); c.parentNode.replaceChild(tx, c); changed(); return; }
		cmd('insertHTML', '<code>' + esc(sel.toString()) + '</code>');
	}
	function cmd(c, v) { D.ed.focus({ preventScroll: true }); document.execCommand(c, false, v === undefined ? null : v); normalize(); changed(); }
	function setBlock(tag) {
		var cur = (document.queryCommandValue('formatBlock') || '').toLowerCase();
		cmd('formatBlock', cur === tag && tag !== 'p' ? 'p' : tag);
	}
	function checklist() {
		var n = curNode();
		var ul = n && n.closest('ul');
		if (ul && ul.hasAttribute('data-checklist')) { cmd('insertUnorderedList'); return; }
		if (!ul) cmd('insertUnorderedList');
		n = curNode();
		ul = n && n.closest('ul');
		if (ul) {
			ul.setAttribute('data-checklist', '1');
			Y.$$(':scope > li', ul).forEach(function (li) { if (!li.hasAttribute('data-checked')) li.setAttribute('data-checked', '0'); });
		}
		changed();
	}
	function align(a) {
		var bl = curBlock();
		if (!bl || !/^(P|H[1-4])$/.test(bl.tagName)) return;
		if (a === 'left') bl.style.removeProperty('text-align');
		else bl.style.textAlign = a;
		if (!bl.getAttribute('style')) bl.removeAttribute('style');
		changed();
	}
	function makeLink(val) {
		var s = window.getSelection();
		var saved = s && s.rangeCount ? s.getRangeAt(0).cloneRange() : null;
		Y.prompt(t('Bağlantı adresi'), val || 'https://', t('Ekle')).then(function (url) {
			if (!url) return;
			if (!/^(https?:|mailto:|tel:|#)/i.test(url)) url = 'https://' + url;
			D.ed.focus({ preventScroll: true });
			if (saved) { s.removeAllRanges(); s.addRange(saved); }
			if (saved && saved.collapsed) document.execCommand('insertHTML', false, '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a>');
			else document.execCommand('createLink', false, url);
			Y.$$('a[href]:not([target])', D.ed).forEach(function (a) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener'); });
			changed();
		});
	}
	function insertHr() {
		var hr = insertBlockAfter(h('hr', { 'data-b': newId() }));
		var p = ensureParaAfter(hr);
		setCaret({ b: p.getAttribute('data-b'), o: 0 });
		changed();
	}
	function insertTable(rows, cols) {
		rows = rows || 3;
		cols = cols || 3;
		var tb = h('tbody');
		for (var i = 0; i < rows; i++) {
			var tr = h('tr');
			for (var j = 0; j < cols; j++) tr.appendChild(h(i === 0 ? 'th' : 'td', h('br')));
			tb.appendChild(tr);
		}
		var tbl = insertBlockAfter(h('table', { 'data-b': newId() }, tb));
		ensureParaAfter(tbl);
		selectNode(tbl.querySelector('th,td'));
		changed();
	}
	function tableOp(op) {
		var cell = curCell();
		if (!cell) return;
		var tr = cell.parentNode;
		var table = cell.closest('table');
		var idx = Array.prototype.indexOf.call(tr.children, cell);
		var rows = Y.$$('tr', table);
		if (op === 'row-below' || op === 'row-above') {
			var nr = h('tr');
			for (var i = 0; i < tr.children.length; i++) nr.appendChild(h('td', h('br')));
			tr.parentNode.insertBefore(nr, op === 'row-below' ? tr.nextSibling : tr);
			selectNode(nr.children[Math.min(idx, nr.children.length - 1)]);
		} else if (op === 'col-right' || op === 'col-left') {
			rows.forEach(function (r, ri) {
				var ref = r.children[idx];
				var c = h(ri === 0 && r.firstElementChild && r.firstElementChild.tagName === 'TH' ? 'th' : 'td', h('br'));
				r.insertBefore(c, op === 'col-right' ? (ref ? ref.nextSibling : null) : ref);
			});
		} else if (op === 'row-del') {
			if (rows.length <= 1) { table.remove(); }
			else { var nx = tr.nextElementSibling || tr.previousElementSibling; tr.remove(); if (nx) selectNode(nx.children[Math.min(idx, nx.children.length - 1)]); }
		} else if (op === 'col-del') {
			if (tr.children.length <= 1) { table.remove(); }
			else rows.forEach(function (r) { if (r.children[idx]) r.children[idx].remove(); });
		} else if (op === 'table-del') {
			table.remove();
		}
		changed();
	}

	/* ---------------- Görev bağlantısı ---------------- */
	/** fromHash: "#" yazılarak açıldı; seçilince o "#" silinir. */
	function taskPicker(fromHash) {
		var s = window.getSelection();
		if (!s || !s.rangeCount || !D.ed.contains(s.anchorNode)) {
			if (fromHash) return;
			D.ed.focus({ preventScroll: true });
			var last = D.ed.lastElementChild;
			if (last) setCaret({ b: last.getAttribute('data-b'), o: last.textContent.length });
		}
		var range = s.getRangeAt(0).cloneRange();
		var rect = range.getBoundingClientRect();
		if (!rect || (!rect.top && !rect.left)) { var bl = curBlock(); rect = bl ? bl.getBoundingClientRect() : D.ed.getBoundingClientRect(); }
		var anchor = { getBoundingClientRect: function () { return rect; }, contains: function () { return false; } };
		var inp = h('input.flxg-input', { type: 'search', placeholder: t('Görev ara…') });
		var list = h('.flxg-pick-list');
		var items = [];
		var hi = 0;
		var picked = false;
		function draw() {
			Y.clear(list);
			if (!items.length) { list.appendChild(h('.flxg-muted.flxg-pick-empty', inp.value ? t('Sonuç yok') : t('Aramak için yazın'))); return; }
			items.forEach(function (x, i) {
				var l = Y.list(x.list);
				list.appendChild(h('button.flxg-pick-item' + (i === hi ? '.is-hi' : ''), { type: 'button', onmousedown: function (e) { e.preventDefault(); choose(x); } },
					h('span.flxg-dot', { style: { background: Y.status(x).color } }), h('span.flxg-grow', x.title), l ? h('small.flxg-muted', l.name) : null));
			});
		}
		var seq = 0;
		var search = Y.debounce(function () {
			var my = ++seq;
			Y.api('GET', 'tasks', { q: inp.value.trim(), limit: 12 }).then(function (r) {
				if (my !== seq) return;
				items = (r.tasks || []).filter(function (x) { return !x.kind || x.kind === 'task' || x.kind === 'decision'; }).slice(0, 12);
				hi = 0;
				draw();
			}, function () { /* yok */ });
		}, 220);
		function choose(x) {
			picked = true;
			Y.closePop();
			D.ed.focus({ preventScroll: true });
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
			if (fromHash) {
				var r2 = range.cloneRange();
				if (r2.startContainer.nodeType === 3 && r2.startOffset > 0 && r2.startContainer.textContent.charAt(r2.startOffset - 1) === '#') {
					r2.setStart(r2.startContainer, r2.startOffset - 1);
					r2.deleteContents();
				}
			}
			var r3 = sel.getRangeAt(0);
			r3.deleteContents();
			var span = h('span.flxg-tref', { 'data-task': x.id, contenteditable: 'false' }, '#' + x.id + ' ' + x.title);
			var sp = document.createTextNode('\u00a0');
			r3.insertNode(sp);
			r3.insertNode(span);
			r3.setStart(sp, 1);
			r3.collapse(true);
			sel.removeAllRanges();
			sel.addRange(r3);
			D.refKey = '';
			refreshTaskRefs();
			changed();
		}
		inp.addEventListener('input', search);
		inp.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); hi = Math.min(items.length - 1, hi + 1); draw(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); hi = Math.max(0, hi - 1); draw(); }
			else if (e.key === 'Enter') { e.preventDefault(); if (items[hi]) choose(items[hi]); }
			else if (e.key === 'Backspace' && !inp.value && fromHash) { Y.closePop(); }
		});
		Y.pop(anchor, h('.flxg-pick.flxg-doc-taskpick', inp, list), { inline: true, onClose: function () {
			if (!picked && D && D.ed) { D.ed.focus({ preventScroll: true }); var sl = window.getSelection(); sl.removeAllRanges(); sl.addRange(range); }
		} });
		search();
	}

	/* ---------------- "/" menüsü ---------------- */
	/** "/" menüsü (Notion gibi sade): gruplar, sağda markdown kısayolu, vurgulanan öğenin önizlemesi, Türkçe/İngilizce arama. */
	function blockItems() {
		var g1 = t('Temel bloklar');
		var g2 = t('Medya ve bağlantı');
		var g3 = t('Yapay zekâ');
		var aiPage = function (k) { return function () { var it = AI_PAGE.filter(function (x) { return x.k === k; })[0]; openAIPage(); if (AIS && it) aiRun(AIS, it, ''); }; };
		var li = '<ul><li>' + esc(t('Madde')) + '</li><li>' + esc(t('Madde')) + '</li></ul>';
		return [
			{ g: g1, k: 'p', label: t('Metin'), hint: t('Düz paragraf'), icon: 'edit', tx: 'T', al: 'text paragraf yazi', pv: '<p>' + esc(t('Düz paragraf')) + '</p>', run: function () { setBlock('p'); } },
			D.page ? { g: g1, k: 'sayfa', label: t('Sayfa'), hint: t('Bu sayfanın içinde alt sayfa'), icon: 'doc', al: 'page subpage alt sayfa', pv: '<p><span class="flxg-pref">' + esc(t('Alt sayfa')) + '</span></p>', run: function () { subPage(); } } : null,
			{ g: g1, k: 'h1', label: t('Başlık 1'), hint: t('Büyük bölüm başlığı'), tx: 'H1', md: '#', al: 'heading baslik1 #', pv: '<h1>' + esc(t('Başlık')) + '</h1>', run: function () { setBlock('h1'); } },
			{ g: g1, k: 'h2', label: t('Başlık 2'), hint: t('Orta bölüm başlığı'), tx: 'H2', md: '##', al: 'heading baslik2 ##', pv: '<h2>' + esc(t('Başlık')) + '</h2>', run: function () { setBlock('h2'); } },
			{ g: g1, k: 'h3', label: t('Başlık 3'), hint: t('Küçük bölüm başlığı'), tx: 'H3', md: '###', al: 'heading baslik3 ###', pv: '<h3>' + esc(t('Başlık')) + '</h3>', run: function () { setBlock('h3'); } },
			{ g: g1, k: 'ul', label: t('Madde listesi'), hint: t('Basit madde işaretli liste'), icon: 'ul', md: '-', al: 'bullet list liste madde -', pv: li, run: function () { cmd('insertUnorderedList'); } },
			{ g: g1, k: 'ol', label: t('Numaralı liste'), hint: t('Sıra numaralı liste'), icon: 'ol', md: '1.', al: 'numbered list numara 1.', pv: li.replace(/ul>/g, 'ol>'), run: function () { cmd('insertOrderedList'); } },
			{ g: g1, k: 'todo', label: t('Kontrol listesi'), hint: t('İşaretlenebilir yapılacaklar'), icon: 'checklist', md: '[]', al: 'checklist check yapilacak gorev todo []', pv: li.replace('<ul>', '<ul data-checklist="1">').replace('<li>', '<li data-checked="1">').replace('<li>', '<li data-checked="0">'), run: checklist },
			{ g: g1, k: 'quote', label: t('Alıntı'), hint: t('Alıntı ya da not'), icon: 'quote', md: '>', al: 'quote alinti >', pv: '<blockquote>' + esc(t('Alıntı ya da not')) + '</blockquote>', run: function () { setBlock('blockquote'); } },
			{ g: g1, k: 'code', label: t('Kod'), hint: t('Kod bloğu'), icon: 'code', md: '```', al: 'code kod pre', pv: '<pre>const a = 1;</pre>', run: function () { setBlock('pre'); } },
			{ g: g1, k: 'table', label: t('Tablo'), hint: t('3×3 tablo'), icon: 'table', al: 'table tablo', pv: '<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>', run: function () { insertTable(); } },
			{ g: g1, k: 'hr', label: t('Ayırıcı çizgi'), hint: t('Bölümleri ayırır'), icon: 'minus', md: '---', al: 'divider ayirici cizgi ---', pv: '<p>' + esc(t('Metin')) + '</p><hr><p>' + esc(t('Metin')) + '</p>', run: insertHr },
			{ g: g2, k: 'img', label: t('Görsel'), hint: t('Resim yükle'), icon: 'image', al: 'image resim gorsel foto', run: function () { pickFiles('image/*'); } },
			{ g: g2, k: 'file', label: t('Dosya'), hint: t('Dosya ekle'), icon: 'clip', al: 'file dosya ek', run: function () { pickFiles(); } },
			{ g: g2, k: 'task', label: t('Görev bağlantısı'), hint: t('Bir görevi bağla (#)'), icon: 'hash', md: '#12', al: 'task gorev mention', run: function () { taskPicker(false); } },
			{ g: g2, k: 'link', label: t('Bağlantı'), hint: t('Web adresi ekle'), icon: 'link', al: 'link url baglanti', run: function () { makeLink(); } },
			aiOn() ? { g: g3, k: 'ai', label: t('Yapay zekâ ile yaz'), hint: t('İstediğini yazdır'), icon: 'sparkle', al: 'ai yapay zeka yaz write', run: function () { openAIPage(); } } : null,
			aiOn() ? { g: g3, k: 'devam', label: t('Devam et'), hint: t('Sayfayı sürdürür'), icon: 'edit', al: 'continue ai', run: aiPage('continue') } : null,
			aiOn() ? { g: g3, k: 'ozet', label: t('Özetle'), hint: t('Sayfanın özeti'), icon: 'list', al: 'summary summarize ai', run: aiPage('summary') } : null,
			aiOn() ? { g: g3, k: 'yapilacak', label: t('Yapılacakları çıkar'), hint: t('Maddeleri göreve çevir'), icon: 'checklist', al: 'action items todo ai', run: aiPage('actions') } : null
		].filter(Boolean);
	}
	/** "/…" ya da "# " gibi metni silip öğeyi uygular. */
	function runBlockItem(it, bl, from, to) {
		var r = document.createRange();
		var a = pointAt(bl, from);
		var b2 = pointAt(bl, to);
		r.setStart(a.node, a.off);
		r.setEnd(b2.node, b2.off);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(r);
		document.execCommand('delete');
		if (!bl.textContent && !bl.querySelector('br')) bl.appendChild(document.createElement('br'));
		it.run();
	}
	function slashCheck() {
		var s = window.getSelection();
		if (!s || !s.rangeCount || !s.isCollapsed) { closeSlash(); return; }
		var bl = curBlock();
		if (!bl || !/^(P|H[1-4])$/.test(bl.tagName)) { closeSlash(); return; }
		var before = textOffset(bl, s.anchorNode, s.anchorOffset);
		var txt = bl.textContent.slice(0, before);
		// Satır başında ya da boşluktan sonra "/" yazılınca açılır.
		var m = /(^|[\s ])\/([^\s/]{0,20})$/.exec(txt);
		if (!m) { closeSlash(); return; }
		var from = before - m[2].length - 1;
		var q = Y.norm(m[2]);
		var items = blockItems().filter(function (it) {
			if (!q) return true;
			return it.k.indexOf(q) === 0 || Y.norm(it.label).indexOf(q) > -1 || (' ' + Y.norm(it.al)).indexOf(' ' + q) > -1;
		});
		if (!items.length) { closeSlash(); return; }
		var rect = s.getRangeAt(0).getBoundingClientRect();
		if (!rect.top && !rect.left) rect = bl.getBoundingClientRect();
		var anchor = { getBoundingClientRect: function () { return rect; }, contains: function () { return false; } };
		var hi = D.slash && D.slash.q === q ? Math.min(D.slash.hi, items.length - 1) : 0;
		var list = h('.flxg-slash-list');
		var box = h('.flxg-doc-slash', list,
			h('button.flxg-slash-close', { type: 'button', onmousedown: function (e) { e.preventDefault(); closeSlash(); } }, h('span', t('Menüyü kapat')), h('kbd', 'esc')));
		var tip = h('.flxg-slash-tip');
		function run(it) { closeSlash(); runBlockItem(it, bl, from, before); }
		var lastG = null;
		var btns = [];
		items.forEach(function (it, i) {
			if (it.g !== lastG) { lastG = it.g; list.appendChild(h('.flxg-menu-head', it.g)); }
			var b = h('button.flxg-menu-item.flxg-slash-item' + (i === hi ? '.is-on' : ''), { type: 'button', onmousedown: function (e) { e.preventDefault(); run(it); }, onmouseenter: function () { setHi(i, false); } },
				it.tx ? h('span.flxg-slash-ic.is-tx', it.tx) : h('span.flxg-slash-ic', Y.icon(it.icon, 17)), h('span.flxg-slash-txt', it.label), it.md ? h('kbd', it.md) : null);
			btns.push(b);
			list.appendChild(b);
		});
		/** Vurgulanan öğenin önizlemesi menünün yanında (Notion gibi); mobilde yok. */
		function showTip() {
			var it = items[D.slash.hi];
			var b = btns[D.slash.hi];
			var pop = box.closest('.flxg-pop');
			Y.clear(tip);
			if (!it || !pop || window.matchMedia('(max-width: 720px)').matches) { tip.classList.remove('is-on'); return; }
			if (it.pv) { var pv = h('.flxg-slash-pv.flxg-prose'); pv.innerHTML = it.pv; tip.appendChild(pv); }
			tip.appendChild(h('.flxg-slash-hint', it.hint));
			var pr = pop.getBoundingClientRect();
			var br = b.getBoundingClientRect();
			tip.classList.add('is-on');
			var left = pr.right + 6;
			if (left + tip.offsetWidth > window.innerWidth - 8) left = pr.left - tip.offsetWidth - 6;
			tip.style.left = Math.max(8, left) + 'px';
			tip.style.top = Math.max(8, Math.min(br.top, window.innerHeight - tip.offsetHeight - 8)) + 'px';
		}
		function setHi(i, scroll) {
			D.slash.hi = i;
			btns.forEach(function (b, j) { b.classList.toggle('is-on', j === i); });
			if (scroll) btns[i].scrollIntoView({ block: 'nearest' });
			showTip();
		}
		Y.pop(anchor, box, { inline: true, noFocus: true, cls: 'flxg-doc-slashpop', onClose: function () { tip.remove(); if (D) D.slash = null; } });
		var root = Y.$('#flxg-app');
		if (root && root.dataset.theme) tip.dataset.theme = root.dataset.theme;
		document.body.appendChild(tip);
		D.slash = {
			hi: hi,
			q: q,
			key: function (e) {
				if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
					e.preventDefault();
					setHi((D.slash.hi + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length, true);
					return true;
				}
				if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); run(items[D.slash.hi]); return true; }
				if (e.key === 'Escape') { e.preventDefault(); closeSlash(); return true; }
				return false;
			}
		};
		btns[hi].scrollIntoView({ block: 'nearest' });
		showTip();
	}
	function closeSlash() { if (D && D.slash) { D.slash = null; Y.closePop(); } }
	/** Markdown kısayolları satır başında: "# ", "## ", "### ", "- ", "1. ", "[] ", "> ", "``` ", "---". */
	var MD = { '#': 'h1', '##': 'h2', '###': 'h3', '-': 'ul', '*': 'ul', '1.': 'ol', '[]': 'todo', '>': 'quote', '```': 'code', '---': 'hr' };
	function mdCheck(e) {
		if (!D.edit || e.inputType !== 'insertText' || (e.data !== ' ' && e.data !== '-')) return false;
		var s = window.getSelection();
		if (!s || !s.rangeCount || !s.isCollapsed) return false;
		var bl = curBlock();
		if (!bl || bl.tagName !== 'P') return false;
		var before = textOffset(bl, s.anchorNode, s.anchorOffset);
		var txt = bl.textContent.slice(0, before).replace(/ /g, ' ');
		var k = e.data === '-' ? (txt === '---' ? 'hr' : '') : MD[txt.slice(0, -1)];
		if (!k || k === 'hr' && e.data !== '-') return false;
		var it = blockItems().filter(function (x) { return x.k === k; })[0];
		if (!it) return false;
		runBlockItem(it, bl, 0, before);
		return true;
	}
	/** "#" satır başındaysa başlık kısayolu olabilir; görev seçici yalnız metin içinde açılır. */
	function atLineStart() {
		var s = window.getSelection();
		var bl = curBlock();
		if (!s || !s.rangeCount || !bl || bl.tagName !== 'P') return false;
		return /^#*$/.test(bl.textContent.slice(0, textOffset(bl, s.anchorNode, s.anchorOffset)));
	}

	/* ---------------- Alt sayfa blokları (iç içe sınırsız sayfa) ---------------- */
	function prefHtml(id, title) { return '<p><span class="flxg-pref" data-page="' + (+id) + '" contenteditable="false">' + esc(title || t('Başlıksız')) + '</span></p>'; }
	/** Sayfa bağlantılarının adını ağaçtan tazeler; silinmiş sayfa soluk görünür. */
	function hydratePrefs(root) {
		Y.$$('span.flxg-pref[data-page]', root).forEach(function (s) {
			s.setAttribute('contenteditable', 'false');
			if (!D || !D.pages) return;
			var p = pageById(s.getAttribute('data-page'));
			if (p) { s.removeAttribute('data-gone'); if (s.textContent !== (p.title || t('Başlıksız'))) s.textContent = p.title || t('Başlıksız'); }
			else s.setAttribute('data-gone', '1');
		});
	}
	/** Bu sayfanın içinde yeni alt sayfa: imlecin yerine bağlantı bloğu, sonra yeni sayfaya geçer. */
	function subPage(title, html) {
		if (!D || !D.edit || !D.page) return Promise.resolve(null);
		var docId = D.doc.id;
		var pid = D.page.id;
		var ref = curBlock();
		return Y.api('POST', 'docs/' + docId + '/pages', { parent: pid, title: (title || '').slice(0, 200), html: html || '' }).then(function (r) {
			if (!D || D.doc.id !== docId) return r;
			applyPages(r);
			if (D.page && D.page.id === pid) {
				var el = fromHtml(prefHtml(r.id, title), newId());
				insertBlockAfter(el, ref && ref.parentNode === D.ed ? ref : D.ed.lastElementChild);
				changed();
			}
			var m = colMap(); delete m[pid]; colMap(m);
			Y.go('#/doc/' + docId + '/p/' + r.id);
			if (!title) setTimeout(function () { if (D && D.titleEl) D.titleEl.focus(); }, 300);
			return r;
		}, Y.fail);
	}
	/** Sayfada bağlantısı olmayan alt sayfalar en altta liste olarak görünür. */
	function drawSubs() {
		if (!D || !D.subsEl) return;
		Y.clear(D.subsEl);
		if (!D.page || D.ed.classList.contains('is-loading')) return;
		var linked = {};
		Y.$$('span.flxg-pref[data-page]', D.ed).forEach(function (s) { linked[s.getAttribute('data-page')] = 1; });
		var rest = kids(D.page.id).filter(function (p) { return !linked[p.id]; });
		rest.forEach(function (p) {
			D.subsEl.appendChild(h('a.flxg-pref', { href: '#/doc/' + D.doc.id + '/p/' + p.id, onclick: function (e) { if (e.ctrlKey || e.metaKey) return; e.preventDefault(); peekOpen(p.id); } }, p.title || t('Başlıksız')));
		});
	}

	/* ---------------- Tablo: satır/sütun tutamaçları, taşı, genişlik, renk ---------------- */
	var TCOLORS = ['gray', 'brown', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'red'];
	function tcolorName(c) {
		return { gray: t('Gri'), brown: t('Kahverengi'), orange: t('Turuncu'), yellow: t('Sarı'), green: t('Yeşil'), blue: t('Mavi'), purple: t('Mor'), pink: t('Pembe'), red: t('Kırmızı') }[c] || t('Varsayılan');
	}
	function tableUi() {
		var ui = h('.flxg-doc-tbl', { contenteditable: 'false', 'aria-hidden': 'true' });
		ui.col = h('button.flxg-doc-tbl-h.is-col', { type: 'button', title: t('Sütun: tıkla menü, sürükle taşı'), onmousedown: function (e) { e.preventDefault(); }, onpointerdown: function (e) { tblHandleDown(e, 'col'); } }, Y.icon('grip', 12));
		ui.row = h('button.flxg-doc-tbl-h.is-row', { type: 'button', title: t('Satır: tıkla menü, sürükle taşı'), onmousedown: function (e) { e.preventDefault(); }, onpointerdown: function (e) { tblHandleDown(e, 'row'); } }, Y.icon('grip', 12));
		ui.rz = h('.flxg-doc-tbl-rz', { onpointerdown: tblResizeDown });
		ui.line = h('.flxg-doc-tbl-line');
		ui.append(ui.col, ui.row, ui.rz, ui.line);
		return ui;
	}
	function tblRows(table) { return Y.$$('tr', table); }
	function tblHide() { if (D.tbl && !D.tblBusy) { D.tbl.className = 'flxg-doc-tbl'; D.tblCell = null; } }
	function tblMove(e) {
		if (!D.tbl || D.tblBusy) return;
		if (D.tbl.contains(e.target)) return;
		var cell = e.target.closest && e.target.closest('td,th');
		if (!cell || !D.ed.contains(cell)) {
			// Tutamaçlara doğru giderken hemen kaybolmasın.
			var tb = D.tblCell && D.tblCell.isConnected ? D.tblCell.closest('table').getBoundingClientRect() : null;
			if (tb && e.clientX > tb.left - 28 && e.clientX < tb.right + 8 && e.clientY > tb.top - 22 && e.clientY < tb.bottom + 8) return;
			tblHide();
			return;
		}
		D.tblCell = cell;
		var ui = D.tbl;
		var table = cell.closest('table');
		var wr = D.wrap.getBoundingClientRect();
		var tr = table.getBoundingClientRect();
		var cr = cell.getBoundingClientRect();
		var rr = cell.parentNode.getBoundingClientRect();
		ui.className = 'flxg-doc-tbl is-on';
		ui.col.style.left = Math.round(cr.left - wr.left + cr.width / 2 - 14) + 'px';
		ui.col.style.top = Math.round(tr.top - wr.top - 9) + 'px';
		ui.row.style.left = Math.round(tr.left - wr.left - 9) + 'px';
		ui.row.style.top = Math.round(rr.top - wr.top + rr.height / 2 - 14) + 'px';
		// Sütun kenarına yakınsa genişlik tutamacı.
		var near = Math.abs(e.clientX - cr.right) < 6 ? cell : Math.abs(e.clientX - cr.left) < 6 ? cell.previousElementSibling : null;
		if (near) {
			var nr = near.getBoundingClientRect();
			ui.rz.style.left = Math.round(nr.right - wr.left - 3) + 'px';
			ui.rz.style.top = Math.round(tr.top - wr.top) + 'px';
			ui.rz.style.height = Math.round(tr.height) + 'px';
			ui.rz.cell = near;
			ui.classList.add('rz-on');
		}
	}
	function tblCtx(kind) {
		var cell = D.tblCell;
		if (!cell || !cell.isConnected) return null;
		var table = cell.closest('table');
		var tr = cell.parentNode;
		var rows = tblRows(table);
		return { kind: kind, cell: cell, table: table, tr: tr, rows: rows, ri: rows.indexOf(tr), ci: Array.prototype.indexOf.call(tr.children, cell) };
	}
	function colCells(c, ci) { return c.rows.map(function (r) { return r.children[ci]; }).filter(Boolean); }
	function tblCells(c) { return c.kind === 'col' ? colCells(c, c.ci) : Array.prototype.slice.call(c.tr.children); }
	function tblMoveTo(c, to) {
		if (c.kind === 'row') {
			if (to === c.ri || to < 0 || to >= c.rows.length) return;
			var ref = c.rows[to];
			ref.parentNode.insertBefore(c.tr, to > c.ri ? ref.nextSibling : ref);
		} else {
			var n = c.tr.children.length;
			if (to === c.ci || to < 0 || to >= n) return;
			c.rows.forEach(function (r) {
				var a = r.children[c.ci];
				var b = r.children[to];
				if (a && b) r.insertBefore(a, to > c.ci ? b.nextSibling : b);
			});
		}
		changed();
	}
	function tblHandleDown(e, kind) {
		if (e.button !== 0) return;
		e.preventDefault();
		var c = tblCtx(kind);
		if (!c) return;
		var handle = e.currentTarget;
		var sx = e.clientX, sy = e.clientY;
		var moved = false;
		var to = kind === 'row' ? c.ri : c.ci;
		var ui = D.tbl;
		function mv(ev) {
			if (!moved && Math.abs(ev.clientX - sx) + Math.abs(ev.clientY - sy) < 5) return;
			if (!moved) { moved = true; D.tblBusy = true; tblCells(c).forEach(function (x) { x.classList.add('is-tdrag'); }); ui.classList.add('line-on'); }
			var wr = D.wrap.getBoundingClientRect();
			var tr = c.table.getBoundingClientRect();
			if (kind === 'row') {
				to = c.rows.length - 1;
				for (var i = 0; i < c.rows.length; i++) { var r = c.rows[i].getBoundingClientRect(); if (ev.clientY < r.top + r.height / 2) { to = i; break; } }
				var tgt = c.rows[to].getBoundingClientRect();
				var y = to > c.ri ? tgt.bottom : tgt.top;
				ui.line.className = 'flxg-doc-tbl-line is-h';
				ui.line.style.cssText = 'left:' + Math.round(tr.left - wr.left) + 'px;top:' + Math.round(y - wr.top - 1) + 'px;width:' + Math.round(tr.width) + 'px';
			} else {
				var cells = Array.prototype.slice.call(c.rows[0].children);
				to = cells.length - 1;
				for (var j = 0; j < cells.length; j++) { var q = cells[j].getBoundingClientRect(); if (ev.clientX < q.left + q.width / 2) { to = j; break; } }
				var tq = cells[to].getBoundingClientRect();
				var x = to > c.ci ? tq.right : tq.left;
				ui.line.className = 'flxg-doc-tbl-line is-v';
				ui.line.style.cssText = 'left:' + Math.round(x - wr.left - 1) + 'px;top:' + Math.round(tr.top - wr.top) + 'px;height:' + Math.round(tr.height) + 'px';
			}
		}
		function up() {
			document.removeEventListener('pointermove', mv);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', up);
			if (!moved) { tblMenu(handle, c); return; }
			D.tblBusy = false;
			ui.classList.remove('line-on');
			Y.$$('.is-tdrag', c.table).forEach(function (x) { x.classList.remove('is-tdrag'); if (!x.getAttribute('class')) x.removeAttribute('class'); });
			tblMoveTo(c, to);
			tblHide();
		}
		document.addEventListener('pointermove', mv);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', up);
	}
	function tblResizeDown(e) {
		var cell = D.tbl.rz.cell;
		if (e.button !== 0 || !cell || !cell.isConnected) return;
		e.preventDefault();
		var table = cell.closest('table');
		var first = tblRows(table)[0];
		var ci = Array.prototype.indexOf.call(cell.parentNode.children, cell);
		// İlk sürüklemede tüm sütunların genişliği sabitlenir.
		Array.prototype.forEach.call(first.children, function (x) { if (!x.style.width) x.style.width = Math.round(x.getBoundingClientRect().width) + 'px'; });
		table.setAttribute('data-fixed', '1');
		var head = first.children[ci];
		var sx = e.clientX;
		var sw = parseFloat(head.style.width) || head.getBoundingClientRect().width;
		D.tblBusy = true;
		document.body.classList.add('flxg-col-resizing');
		function mv(ev) { head.style.width = Math.round(Math.max(48, Math.min(900, sw + ev.clientX - sx))) + 'px'; }
		function up() {
			document.removeEventListener('pointermove', mv);
			document.removeEventListener('pointerup', up);
			D.tblBusy = false;
			document.body.classList.remove('flxg-col-resizing');
			tblHide();
			changed();
		}
		document.addEventListener('pointermove', mv);
		document.addEventListener('pointerup', up);
	}
	function tblColor(c, attr, val) {
		tblCells(c).forEach(function (x) { if (val) x.setAttribute(attr, val); else x.removeAttribute(attr); });
		changed();
	}
	/** Satır/sütun menüsü: ekle, taşı, renk, sil. */
	function tblMenu(anchor, c) {
		var row = c.kind === 'row';
		var n = row ? c.rows.length : c.tr.children.length;
		var i = row ? c.ri : c.ci;
		function op(name) { return function () { Y.closePop(); selectNode(c.cell); tableOp(name); tblHide(); }; }
		function item(label, icon, fn, danger) { return h('button.flxg-menu-item' + (danger ? '.is-danger' : ''), { type: 'button', onclick: fn }, Y.icon(icon, 15), h('span', label)); }
		var cur = tblCells(c)[0] || c.cell;
		function swatches(attr) {
			var wrap = h('.flxg-doc-tbl-sw');
			[''].concat(TCOLORS).forEach(function (col) {
				wrap.appendChild(h('button.flxg-doc-tbl-swb' + ((cur.getAttribute(attr) || '') === col ? '.is-on' : ''), { type: 'button', title: tcolorName(col), 'data-k': attr === 'data-bg' ? 'bg' : 'fg', 'data-c': col || 'none', onclick: function () { Y.closePop(); tblColor(c, attr, col); } }, attr === 'data-fg' ? 'A' : ''));
			});
			return wrap;
		}
		var box = h('.flxg-menu.flxg-doc-tbl-menu',
			row ? item(t('Üstüne satır ekle'), 'plus', op('row-above')) : item(t('Sola sütun ekle'), 'plus', op('col-left')),
			row ? item(t('Altına satır ekle'), 'plus', op('row-below')) : item(t('Sağa sütun ekle'), 'plus', op('col-right')),
			i > 0 ? item(row ? t('Yukarı taşı') : t('Sola taşı'), row ? 'chevL' : 'chevL', function () { Y.closePop(); tblMoveTo(c, i - 1); }) : null,
			i < n - 1 ? item(row ? t('Aşağı taşı') : t('Sağa taşı'), 'chevR', function () { Y.closePop(); tblMoveTo(c, i + 1); }) : null,
			h('.flxg-menu-sep'),
			h('.flxg-menu-head', t('Arka plan')), swatches('data-bg'),
			h('.flxg-menu-head', t('Metin rengi')), swatches('data-fg'),
			h('.flxg-menu-sep'),
			item(row ? t('Satırı sil') : t('Sütunu sil'), 'trash', op(row ? 'row-del' : 'col-del'), true),
			item(t('Tabloyu sil'), 'trash', op('table-del'), true)
		);
		Y.pop(anchor, box, { inline: true, noFocus: true });
	}

	/* ---------------- Satır tutamacı: + ve sürükle (Notion gibi) ---------------- */
	function gutter() {
		var g = h('.flxg-doc-gut', { contenteditable: 'false', 'aria-hidden': 'true' },
			h('button.flxg-doc-gut-btn', { type: 'button', title: t('Tıkla: altına blok ekle. Alt+tıkla: üstüne ekle'), onmousedown: function (e) { e.preventDefault(); }, onclick: function (e) { gutAdd(e.altKey); } }, Y.icon('plus', 16)),
			h('button.flxg-doc-gut-btn.flxg-doc-gut-grip', { type: 'button', title: t('Sürükle: taşı. Tıkla: menü'), onmousedown: function (e) { e.preventDefault(); }, onpointerdown: gripDown }, Y.icon('grip', 16)));
		return g;
	}
	function blockAtY(y) {
		var kids2 = D.ed.children;
		for (var i = 0; i < kids2.length; i++) {
			var r = kids2[i].getBoundingClientRect();
			var cs = getComputedStyle(kids2[i]);
			if (y >= r.top - parseFloat(cs.marginTop) && y <= r.bottom + parseFloat(cs.marginBottom)) return kids2[i];
		}
		return null;
	}
	function gutShow(bl) {
		if (!D.gut) return;
		if (!bl || D.gutDrag) { if (!D.gutDrag) { D.gut.classList.remove('is-on'); D.gutB = null; } return; }
		D.gutB = bl;
		var wr = D.wrap.getBoundingClientRect();
		var r = bl.getBoundingClientRect();
		var cs = getComputedStyle(bl.tagName === 'UL' || bl.tagName === 'OL' ? (bl.firstElementChild || bl) : bl);
		var lh = parseFloat(cs.lineHeight) || 26;
		var top = r.top - wr.top + (bl.tagName === 'TABLE' ? 6 : parseFloat(getComputedStyle(bl).paddingTop) || 0) + Math.max(0, (lh - 26) / 2);
		D.gut.style.top = Math.round(top) + 'px';
		D.gut.classList.add('is-on');
	}
	function gutMove(e) {
		if (!D || !D.edit || D.gutDrag || e.pointerType === 'touch') return;
		tblMove(e);
		if (D.gut.contains(e.target)) return;
		var pr = D.paper.getBoundingClientRect();
		if (e.clientX < pr.left - 10 || e.clientX > pr.right) { gutShow(null); return; }
		gutShow(blockAtY(e.clientY));
	}
	function gutAdd(above) {
		var bl = D.gutB;
		if (!bl || !bl.isConnected) return;
		var empty = bl.tagName === 'P' && !bl.textContent.trim() && !bl.querySelector('img,span');
		var el = empty ? bl : fromHtml('', newId());
		if (!empty) D.ed.insertBefore(el, above ? bl : bl.nextSibling);
		D.ed.focus({ preventScroll: true });
		setCaret({ b: el.getAttribute('data-b'), o: 0 });
		document.execCommand('insertText', false, '/');
	}
	function gripDown(e) {
		if (e.button !== 0 || !D.gutB) return;
		e.preventDefault();
		var bl = D.gutB;
		var grip = e.currentTarget;
		var sy = e.clientY;
		var moved = false;
		var line = null;
		var target = null;
		function mv(ev) {
			if (!moved && Math.abs(ev.clientY - sy) < 4) return;
			if (!moved) { moved = true; D.gutDrag = true; bl.classList.add('is-dragging'); line = h('.flxg-doc-dropline'); D.wrap.appendChild(line); Y.closePop(); }
			var sr = D.scroll.getBoundingClientRect();
			if (ev.clientY < sr.top + 40) D.scroll.scrollTop -= 12;
			else if (ev.clientY > sr.bottom - 40) D.scroll.scrollTop += 12;
			target = null;
			var kids2 = D.ed.children;
			for (var i = 0; i < kids2.length; i++) {
				var r = kids2[i].getBoundingClientRect();
				if (ev.clientY < r.top + r.height / 2) { target = kids2[i]; break; }
			}
			var wr = D.wrap.getBoundingClientRect();
			var y = target ? target.getBoundingClientRect().top - 3 : D.ed.lastElementChild.getBoundingClientRect().bottom + 1;
			line.style.top = Math.round(y - wr.top) + 'px';
			line.style.display = target === bl || target === bl.nextElementSibling ? 'none' : '';
		}
		function up() {
			document.removeEventListener('pointermove', mv);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', up);
			if (!moved) { blockMenu(grip, bl); return; }
			D.gutDrag = false;
			bl.classList.remove('is-dragging');
			if (line) line.remove();
			if (target !== bl && target !== bl.nextElementSibling) { D.ed.insertBefore(bl, target); changed(); pulse(bl); }
			gutShow(bl);
		}
		document.addEventListener('pointermove', mv);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', up);
	}
	/** Tutamaç menüsü: dönüştür, çoğalt, taşı, yapay zekâ, sil. */
	function blockMenu(anchor, bl) {
		var text = /^(P|H[1-4]|BLOCKQUOTE|PRE)$/.test(bl.tagName) && !bl.querySelector('span.flxg-pref');
		function turn(tag) {
			var n = document.createElement(tag);
			if (tag === 'pre') n.textContent = bl.textContent;
			else if (bl.tagName === 'PRE') n.textContent = bl.textContent;
			else n.innerHTML = bl.innerHTML;
			if (!n.textContent) n.innerHTML = '<br>';
			n.setAttribute('data-b', bl.getAttribute('data-b'));
			if (bl.getAttribute('style') && tag !== 'pre') n.setAttribute('style', bl.getAttribute('style'));
			D.ed.replaceChild(n, bl);
			changed();
		}
		var cur = bl.tagName.toLowerCase();
		var conv = [['p', t('Metin')], ['h1', t('Başlık 1')], ['h2', t('Başlık 2')], ['h3', t('Başlık 3')], ['blockquote', t('Alıntı')], ['pre', t('Kod')]];
		Y.menu(anchor, [
			text ? { head: t('Dönüştür') } : null
		].concat(text ? conv.map(function (c) { return { label: c[1], checked: cur === c[0], onClick: function () { if (cur !== c[0]) turn(c[0]); } }; }) : []).concat([
			text ? { sep: true } : null,
			aiOn() && bl.textContent.trim() ? { label: t('Yapay zekâ ile düzenle'), icon: 'sparkle', onClick: function () {
				var r = document.createRange();
				r.selectNodeContents(bl);
				var s = window.getSelection();
				s.removeAllRanges();
				s.addRange(r);
				openAISel();
			} } : null,
			{ label: t('Çoğalt'), icon: 'copy', onClick: function () { var c = fromHtml(cleanHtml(bl), newId()); D.ed.insertBefore(c, bl.nextSibling); changed(); pulse(c); } },
			bl.previousElementSibling ? { label: t('Yukarı taşı'), icon: 'chevL', onClick: function () { D.ed.insertBefore(bl, bl.previousElementSibling); changed(); } } : null,
			bl.nextElementSibling ? { label: t('Aşağı taşı'), icon: 'chevR', onClick: function () { D.ed.insertBefore(bl, bl.nextElementSibling.nextSibling); changed(); } } : null,
			{ sep: true },
			{ label: t('Sil'), icon: 'trash', danger: true, onClick: function () { bl.remove(); normalize(); markEmpty(); changed(); gutShow(null); } }
		]), { inline: true });
	}

	/* ---------------- Araç çubuğu ---------------- */
	function tbtn(icon, title, fn, key) {
		return h('button.flxg-icon-btn.is-sm', { type: 'button', title: title, 'data-k': key || null, onmousedown: function (e) { e.preventDefault(); fn(e); } }, Y.icon(icon, 16));
	}
	function toolbar() {
		var styleBtn = h('button.flxg-doc-style', { type: 'button', onmousedown: function (e) {
			e.preventDefault();
			Y.menu(e.currentTarget, [['p', t('Metin')], ['h1', t('Başlık 1')], ['h2', t('Başlık 2')], ['h3', t('Başlık 3')], ['blockquote', t('Alıntı')], ['pre', t('Kod')]].map(function (x) {
				return { label: x[1], checked: (document.queryCommandValue('formatBlock') || 'p').toLowerCase() === x[0], onClick: function () { setBlock(x[0]); } };
			}), { inline: true });
		} }, h('span', t('Metin')), Y.icon('chevD', 13));
		D.styleBtn = styleBtn;
		D.tableBar = h('.flxg-doc-tbgroup.flxg-doc-tablebar',
			tbtn('plus', t('Alta satır ekle'), function () { tableOp('row-below'); }),
			h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onmousedown: function (e) {
				e.preventDefault();
				Y.menu(e.currentTarget, [
					{ label: t('Üste satır ekle'), onClick: function () { tableOp('row-above'); } },
					{ label: t('Alta satır ekle'), onClick: function () { tableOp('row-below'); } },
					{ label: t('Sola sütun ekle'), onClick: function () { tableOp('col-left'); } },
					{ label: t('Sağa sütun ekle'), onClick: function () { tableOp('col-right'); } },
					{ sep: true },
					{ label: t('Satırı sil'), danger: true, onClick: function () { tableOp('row-del'); } },
					{ label: t('Sütunu sil'), danger: true, onClick: function () { tableOp('col-del'); } },
					{ label: t('Tabloyu sil'), danger: true, onClick: function () { tableOp('table-del'); } }
				], { inline: true });
			} }, Y.icon('table', 15), t('Tablo'), Y.icon('chevD', 12))
		);
		return h('.flxg-doc-tb',
			h('.flxg-doc-tbgroup', tbtn('undo', t('Geri al'), function () { cmd('undo'); }), tbtn('redo', t('Yinele'), function () { cmd('redo'); })),
			h('.flxg-doc-tbgroup', styleBtn),
			h('.flxg-doc-tbgroup',
				tbtn('bold', t('Kalın'), function () { cmd('bold'); }, 'bold'),
				tbtn('italic', t('İtalik'), function () { cmd('italic'); }, 'italic'),
				tbtn('underline', t('Altı çizili'), function () { cmd('underline'); }, 'underline'),
				tbtn('strike', t('Üstü çizili'), function () { cmd('strikeThrough'); }, 'strikeThrough'),
				tbtn('link', t('Bağlantı (Ctrl+K)'), function () { makeLink(); })
			),
			h('.flxg-doc-tbgroup',
				tbtn('ul', t('Madde listesi'), function () { cmd('insertUnorderedList'); }),
				tbtn('ol', t('Numaralı liste'), function () { cmd('insertOrderedList'); }),
				tbtn('checklist', t('Kontrol listesi'), checklist)
			),
			h('.flxg-doc-tbgroup',
				tbtn('alignL', t('Hizalama'), function (e) {
					Y.menu(e.currentTarget, [
						{ label: t('Sola'), icon: 'alignL', onClick: function () { align('left'); } },
						{ label: t('Ortaya'), icon: 'alignC', onClick: function () { align('center'); } },
						{ label: t('Sağa'), icon: 'alignR', onClick: function () { align('right'); } }
					], { inline: true });
				}),
				tbtn('image', t('Görsel ekle'), function () { pickFiles('image/*'); }),
				tbtn('clip', t('Dosya ekle'), function () { pickFiles(); }),
				tbtn('table', t('Tablo ekle'), function () { insertTable(); }),
				tbtn('minus', t('Ayırıcı çizgi'), insertHr),
				tbtn('hash', t('Görev bağlantısı (#)'), function () { taskPicker(false); })
			),
			D.tableBar
		);
	}
	function updateToolbar() {
		var v = (document.queryCommandValue('formatBlock') || 'p').toLowerCase();
		var names = { p: t('Metin'), h1: t('Başlık 1'), h2: t('Başlık 2'), h3: t('Başlık 3'), h4: t('Başlık 3'), blockquote: t('Alıntı'), pre: t('Kod') };
		D.styleBtn.firstChild.textContent = names[v] || t('Metin');
		Y.$$('[data-k]', D.tb).forEach(function (b) {
			var on = false;
			try { on = document.queryCommandState(b.getAttribute('data-k')); } catch (e) { on = false; }
			b.classList.toggle('is-on', on);
		});
		D.tableBar.classList.toggle('is-shown', !!curCell());
	}

	/* =====================================================================
	 * Yapay zekâ (Flux AI üzerinden): üst çubuktaki genel düğme ve seçili metnin üstünde çıkan düğme.
	 * Sonuç önce önizlenir; "Değiştir/Ekle" ile editöre normal düzenleme gibi yazılır (ortak düzenleme aynen çalışır).
	 * ================================================================== */
	function aiOn() { return !!(S.me && S.me.can && S.me.can.ai); }
	/** Asistan ve ortak sohbet için açık sayfa. */
	Y.docPage = function () { return D && D.page ? { id: D.page.id, title: (D.titleEl && D.titleEl.value) || D.page.title || '' } : null; };
	Y.docSyncNow = function () { if (D && D.page) schedule(0); };

	var AI_SEL = [
		{ k: 'improve', label: t('İyileştir'), icon: 'sparkle', edit: 1 },
		{ k: 'fix', label: t('Yazım ve dil bilgisini düzelt'), icon: 'check', edit: 1 },
		{ k: 'shorter', label: t('Kısalt'), icon: 'minus', edit: 1 },
		{ k: 'longer', label: t('Uzat'), icon: 'plus', edit: 1 },
		{ k: 'simplify', label: t('Sadeleştir'), icon: 'edit', edit: 1 },
		{ k: 'formal', label: t('Daha resmi'), icon: 'user', edit: 1 },
		{ k: 'friendly', label: t('Daha samimi'), icon: 'star', edit: 1 },
		{ k: 'bullets', label: t('Maddelere çevir'), icon: 'ul', edit: 1 },
		{ k: 'table', label: t('Tabloya çevir'), icon: 'table', edit: 1 },
		{ k: 'translate', lang: 'en', label: t('İngilizceye çevir'), icon: 'msg' },
		{ k: 'translate', lang: 'tr', label: t('Türkçeye çevir'), icon: 'msg' },
		{ k: 'explain', label: t('Açıkla'), icon: 'eye' }
	];
	var AI_PAGE = [
		{ k: 'continue', label: t('Devam et'), icon: 'edit', edit: 1 },
		{ k: 'summary', label: t('Özetle'), icon: 'list' },
		{ k: 'actions', label: t('Yapılacakları çıkar'), icon: 'checklist' },
		{ k: 'outline', label: t('Taslak çıkar'), icon: 'heading', edit: 1 },
		{ k: 'title', label: t('Başlık öner'), icon: 'sparkle', edit: 1 }
	];
	var AIS = null;

	/** Editördeki seçim: aralık, metin, HTML ve satır içi mi (tek blok içinde). */
	function selInfo() {
		var sel = window.getSelection();
		if (!D || !sel || !sel.rangeCount || sel.isCollapsed) return null;
		var r = sel.getRangeAt(0);
		if (!D.ed.contains(r.commonAncestorContainer)) return null;
		var text = r.toString();
		if (text.trim().length < 2) return null;
		var box = document.createElement('div');
		box.appendChild(r.cloneContents());
		Y.$$('[data-b]', box).forEach(function (x) { x.removeAttribute('data-b'); });
		Y.$$('img', box).forEach(function (x) { x.remove(); });
		return {
			range: r.cloneRange(), text: text, html: box.innerHTML,
			inline: !box.querySelector('p,h1,h2,h3,h4,li,ul,ol,table,tr,blockquote,pre,figure'),
			end: blockOf(r.endContainer)
		};
	}
	function markSel(range) {
		try {
			if (!window.CSS || !CSS.highlights || typeof Highlight === 'undefined') return;
			if (range) CSS.highlights.set('flxg-ai-sel', new Highlight(range)); else CSS.highlights.delete('flxg-ai-sel');
		} catch (e) { /* yok */ }
	}
	function placeBubble() {
		if (!D || !D.root || (!aiOn() && !D.edit)) return;
		var info = !D.dragging ? selInfo() : null;
		if (!info) { if (D.aiBub) D.aiBub.classList.remove('is-on'); return; }
		if (!D.aiBub) {
			// Seçim araç çubuğu: yapay zekâ + biçim düğmeleri (düzenleyebilen için).
			var fb = function (icon, title, fn, key) {
				return h('button.flxg-doc-aibub-fmt', { type: 'button', title: title, 'aria-label': title, 'data-k': key || null, onmousedown: function (e) { e.preventDefault(); }, onclick: function () { fn(); bubbleSoon(); } }, Y.icon(icon, 15));
			};
			D.aiBub = h('.flxg-doc-aibub', { onmousedown: function (e) { e.preventDefault(); } },
				aiOn() ? h('button.flxg-doc-aibub-ai', { type: 'button', title: t('Yapay zekâ (Ctrl+J)'), onclick: function () { openAISel(); } }, Y.icon('sparkle', 14), h('span', t('Yapay zekâ'))) : null,
				D.edit ? h('span.flxg-doc-aibub-grp',
					fb('bold', t('Kalın'), function () { cmd('bold'); }, 'bold'),
					fb('italic', t('İtalik'), function () { cmd('italic'); }, 'italic'),
					fb('underline', t('Altı çizili'), function () { cmd('underline'); }, 'underline'),
					fb('strike', t('Üstü çizili'), function () { cmd('strikeThrough'); }, 'strikeThrough'),
					fb('code', t('Kod'), function () { inlineCode(); }),
					fb('link', t('Bağlantı (Ctrl+K)'), function () { makeLink(); }),
					fb('heading', t('Başlığa çevir'), function () { setBlock('h2'); }),
					fb('ul', t('Madde listesi'), function () { cmd('insertUnorderedList'); })
				) : null);
			D.root.appendChild(D.aiBub);
		}
		var b = D.aiBub;
		Y.$$('[data-k]', b).forEach(function (x) { var on = false; try { on = document.queryCommandState(x.getAttribute('data-k')); } catch (e) { on = false; } x.classList.toggle('is-on', on); });
		var rects = info.range.getClientRects();
		var first = rects.length ? rects[0] : info.range.getBoundingClientRect();
		var last = rects.length ? rects[rects.length - 1] : first;
		b.classList.add('is-on');
		var top = Y.isMobile() ? last.bottom + 10 : first.top - b.offsetHeight - 8;
		if (top < 60) top = last.bottom + 10;
		b.style.top = Math.round(Math.min(top, window.innerHeight - b.offsetHeight - 8)) + 'px';
		b.style.left = Math.round(Math.max(8, Math.min(first.left, window.innerWidth - b.offsetWidth - 8))) + 'px';
	}
	var bubbleSoon = (function () {
		var raf = 0;
		return function () { if (!raf) raf = requestAnimationFrame(function () { raf = 0; placeBubble(); }); };
	})();
	document.addEventListener('selectionchange', function () { if (D && D.ed && D.ed.isConnected) bubbleSoon(); });
	document.addEventListener('mouseup', function () { if (D && D.dragging) { D.dragging = false; bubbleSoon(); } }, true);
	function caretBlock() {
		var sel = window.getSelection();
		if (sel && sel.rangeCount && D.ed.contains(sel.anchorNode)) return blockOf(sel.anchorNode);
		return D.lastBlock && D.lastBlock.isConnected ? D.lastBlock : null;
	}
	function openAISel() {
		var info = selInfo();
		if (!info) { openAIPage(D.aiBtn); return; }
		if (D.aiBub) D.aiBub.classList.remove('is-on');
		var rect = info.range.getBoundingClientRect();
		var anchor = D.aiBub && D.aiBub.offsetParent ? D.aiBub : { getBoundingClientRect: function () { return rect; }, contains: function () { return false; } };
		aiOpen({ mode: 'sel', info: info, state: 'menu', anchor: anchor });
		markSel(info.range);
	}
	function openAIPage(anchor) {
		if (!D || !D.page) return;
		var at = caretBlock();
		if (!anchor || !anchor.getBoundingClientRect) {
			var r = at ? at.getBoundingClientRect() : D.ed.getBoundingClientRect();
			anchor = { getBoundingClientRect: function () { return r; }, contains: function () { return false; } };
		}
		aiOpen({ mode: 'page', at: at, state: 'menu', anchor: anchor });
	}
	/** Panel kendi başına durur: sayfada başka yere tıklayınca kapanmaz, yalnız ✕ ya da Esc ile kapanır. */
	function aiOpen(st) {
		if (!aiOn()) return;
		aiClose();
		AIS = st;
		st.box = h('.flxg-doc-ai');
		st.pop = h('.flxg-pop.flxg-doc-aipop', { role: 'dialog', 'aria-label': t('Yapay zekâ') }, st.box);
		var root = Y.$('#flxg-app');
		if (root && root.dataset.theme) st.pop.dataset.theme = root.dataset.theme;
		st.pop.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); aiClose(); } });
		document.body.appendChild(st.pop);
		var a = st.anchor.getBoundingClientRect();
		var w = st.pop.offsetWidth;
		var left = st.anchor === D.aiBtn ? a.right - w : a.left;
		st.pop.style.left = Math.max(8, Math.min(left, window.innerWidth - w - 8)) + 'px';
		aiDraw(st);
	}
	function aiClose() {
		var st = AIS;
		if (!st) return;
		AIS = null;
		markSel(null);
		if (st.pop) st.pop.remove();
		bubbleSoon();
	}
	function aiFit(st) {
		var el = st.pop;
		if (!el || !el.isConnected) return;
		var a = st.anchor.getBoundingClientRect();
		var hh = el.offsetHeight;
		var top = a.bottom + 4;
		if (top + hh > window.innerHeight - 8) top = Math.max(8, Math.min(a.top - hh - 4, window.innerHeight - hh - 8));
		if (st.top === undefined || Math.abs(st.top - top) > 2) st.top = top;
		el.style.top = st.top + 'px';
	}
	function aiRun(st, item, prompt, prev) {
		if (!D || !D.page) return;
		st.state = 'loading';
		st.item = item;
		st.prompt = prompt || '';
		st.err = '';
		var seq = st.seq = (st.seq || 0) + 1;
		aiDraw(st);
		var body = { action: item.k, lang: item.lang || '', prompt: prompt || '', prev: prev || '' };
		if (st.mode === 'sel') { body.html = st.info.html; body.text = st.info.text; body.inline = st.info.inline ? 1 : 0; }
		var pid = D.page.id;
		// Sayfa işlemleri son yazılanı görsün diye önce bekleyen değişiklikler gönderilir.
		(st.mode === 'page' ? flushNow() : Promise.resolve()).then(function () {
			return Y.api('POST', 'doc-pages/' + pid + '/ai', body, { quiet: true });
		}).then(function (r) {
			if (AIS !== st || st.seq !== seq) return;
			st.state = 'result';
			st.result = r;
			aiDraw(st);
		}, function (e) {
			if (AIS !== st || st.seq !== seq) return;
			st.state = 'error';
			st.err = e.message || String(e);
			aiDraw(st);
		});
	}
	function aiPlain(html) { var d = document.createElement('div'); d.innerHTML = html; return (d.innerText || d.textContent || '').trim(); }
	function aiDraw(st) {
		if (AIS !== st) return;
		var box = Y.clear(st.box);
		var edit = D.edit;
		// Menü dar ve kompakt; sonuç/maddeler için panel genişler.
		st.pop.classList.toggle('is-menu', st.state === 'menu' || st.state === 'error' || st.state === 'loading');
		var label = st.item ? st.item.label : (st.mode === 'sel' ? t('Seçili metin') : t('Bu sayfa'));
		box.appendChild(h('.flxg-doc-ai-head', Y.icon('sparkle', 15), h('b', t('Yapay zekâ')), h('span.flxg-muted.flxg-doc-ai-label', label), h('span.flxg-grow'),
			h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kapat'), onclick: aiClose }, Y.icon('x', 14))));
		var inp = h('input.flxg-input.is-sm', { type: 'text', placeholder: st.state === 'result' && !st.result.answer ? t('Değiştirmek istediğini yaz… (örn. daha kısa olsun)') : (st.mode === 'sel' ? t('Seçili metne ne yapılsın?') : (edit ? t('Ne yazılsın? Örn: toplantı gündemi taslağı') : t('Bu sayfaya bir soru sorun'))) });
		function go(kind) {
			var v = inp.value.trim();
			if (!v) { inp.focus(); return; }
			if (st.state === 'result' && !st.result.answer && kind !== 'ask') {
				aiRun(st, st.item, (st.prompt ? st.prompt + '\n' : '') + v, st.result.items ? JSON.stringify({ items: st.result.items }) : (st.result.html || st.result.text || ''));
				return;
			}
			if (st.mode === 'sel') aiRun(st, { k: 'custom', label: v }, v);
			else if (kind === 'ask' || !edit) aiRun(st, { k: 'ask', label: v }, v);
			else aiRun(st, { k: 'write', label: v }, v);
		}
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } e.stopPropagation(); });
		var askRow = h('.flxg-doc-ai-ask', inp,
			st.mode === 'page' && edit && st.state !== 'result' ? h('button.flxg-btn.is-sm', { type: 'button', title: t('Sayfaya soru sor (cevap sayfaya yazılmaz)'), onclick: function () { go('ask'); } }, t('Sor')) : null,
			h('button.flxg-btn.is-primary.is-sm', { type: 'button', 'aria-label': t('Gönder'), onclick: function () { go(); } }, Y.icon('send', 13)));

		if (st.state === 'menu' || st.state === 'error') {
			if (st.state === 'error') box.appendChild(h('p.flxg-error.flxg-doc-ai-err', st.err));
			if (edit || st.mode === 'page') box.appendChild(askRow);
			var list = h('.flxg-menu.flxg-doc-ai-list');
			(st.mode === 'sel' ? AI_SEL : AI_PAGE).forEach(function (it) {
				if (it.edit && !edit) return;
				list.appendChild(h('button.flxg-menu-item', { type: 'button', onclick: function () { aiRun(st, it, ''); } }, Y.icon(it.icon, 15), h('span', it.label)));
			});
			list.appendChild(h('.flxg-menu-sep'));
			list.appendChild(h('button.flxg-menu-item', { type: 'button', onclick: function () { aiClose(); Y.openAI({ page: Y.docPage() }); } }, Y.icon('msg', 15), h('span', t('Asistanla sohbet et'))));
			box.appendChild(list);
			if (!Y.isMobile()) setTimeout(function () { if (inp.isConnected) inp.focus(); }, 20);
			aiFit(st);
			return;
		}
		if (st.state === 'loading') {
			var sk = h('.flxg-ai-sk');
			['92%', '78%', '85%', '50%'].forEach(function (w) { sk.appendChild(Y.sk(w, 11)); });
			box.appendChild(h('.flxg-ai-thinking', h('span.flxg-ai-dots', h('i'), h('i'), h('i')), t('Yapay zekâ yazıyor…')));
			box.appendChild(sk);
			aiFit(st);
			return;
		}
		// Sonuç.
		var r = st.result;
		if (r.items) { aiItems(st, box, askRow); aiFit(st); return; }
		var isText = typeof r.text === 'string' && !r.html;
		var prev = isText ? h('.flxg-doc-ai-prev.is-text', r.text) : h('.flxg-doc-ai-prev.flxg-prose', { html: r.inline ? '<p>' + r.html + '</p>' : r.html });
		box.appendChild(prev);
		var foot = h('.flxg-doc-ai-foot');
		var k = st.item.k;
		function btn(lbl, fn, primary, icon, ghost) {
			foot.appendChild(h('button.flxg-btn.is-sm' + (primary ? '.is-primary' : '') + (ghost ? '.is-ghost' : ''), { type: 'button', onclick: fn }, icon ? Y.icon(icon, 13) : null, lbl));
		}
		var blocksHtml = r.inline ? '<p>' + r.html + '</p>' : r.html;
		if (edit && st.mode === 'sel' && !r.answer) btn(t('Değiştir'), function () { aiClose(); aiReplace(st.info, r); }, true, 'check');
		if (edit && st.mode === 'sel') btn(t('Altına ekle'), function () { aiClose(); aiInsert(blocksHtml, st.info.end && st.info.end.isConnected ? st.info.end : caretBlock()); }, !!r.answer);
		if (edit && st.mode === 'page' && k === 'title') btn(t('Başlık yap'), function () {
			aiClose();
			var old = D.titleEl.value;
			D.titleEl.value = r.text;
			D.fitTitle();
			D.titleDirty = true;
			soon(300);
			Y.toast(t('Sayfa başlığı değişti.'), { ms: 7000, action: { label: t('Geri al'), fn: function () { if (!D) return; D.titleEl.value = old; D.fitTitle(); D.titleDirty = true; soon(300); } } });
		}, true, 'check');
		if (edit && st.mode === 'page' && k !== 'title' && k !== 'ask') {
			var at = st.at && st.at.isConnected ? st.at : null;
			btn(at ? t('İmlecin altına ekle') : t('Sona ekle'), function () { aiClose(); aiInsert(blocksHtml, at || D.ed.lastElementChild); }, true, 'plus');
			if (at && at !== D.ed.lastElementChild) btn(t('Sona ekle'), function () { aiClose(); aiInsert(blocksHtml, D.ed.lastElementChild); });
			if (k === 'summary' || k === 'actions') btn(t('Başa ekle'), function () { aiClose(); aiInsert(blocksHtml, null, true); });
		}
		if (edit && k !== 'title' && !(st.mode === 'sel' && r.answer && k !== 'explain')) btn(t('Yeni sayfa olarak ekle'), function () { aiNewPage(blocksHtml, aiTitleOf(blocksHtml, st.item.label)); }, false, 'doc');
		if (edit && st.mode === 'page' && k === 'ask') btn(t('Sayfaya ekle'), function () { aiClose(); aiInsert(blocksHtml, (st.at && st.at.isConnected) ? st.at : D.ed.lastElementChild); });
		btn(t('Kopyala'), function () { Y.copy(isText ? r.text : aiPlain(blocksHtml)); }, false, 'copy', true);
		btn(t('Yeniden üret'), function () { aiRun(st, st.item, st.prompt); }, false, 'sparkle', true);
		btn(t('Geri'), function () { st.state = 'menu'; st.item = null; st.result = null; aiDraw(st); }, false, 'back', true);
		box.appendChild(foot);
		if (edit || st.mode === 'page') box.appendChild(askRow);
		aiFit(st);
	}
	/** "Yapılacakları çıkar": maddeler düzenlenir, silinir, eklenir; seçilen listeye görev olarak ya da sayfaya kontrol listesi olarak eklenir. */
	function aiItems(st, box, askRow) {
		var r = st.result;
		var edit = D.edit;
		var lists = S.lists.filter(function (l) { return Y.canList(l.id, 'edit'); });
		var sp = D.doc.space_id;
		lists.sort(function (a, b) { return (a.space_id === sp ? 0 : 1) - (b.space_id === sp ? 0 : 1); });
		var saved = +Y.store('doc-ai-list');
		if (!st.list || !Y.list(st.list)) st.list = (lists.filter(function (l) { return l.id === saved; })[0] || lists[0] || {}).id || 0;
		var rows = h('.flxg-doc-ai-items');
		function people(id) { var u = id ? user(id) : null; return u ? h('span.flxg-chip.flxg-doc-ai-who', u.name) : null; }
		function draw() {
			Y.clear(rows);
			r.items.forEach(function (it, i) {
				if (it.task) {
					rows.appendChild(h('.flxg-doc-ai-item.is-done', Y.icon('check', 14), h('a', { href: '#/task/' + it.task }, '#' + it.task + ' ' + it.title)));
					return;
				}
				var cb = h('input', { type: 'checkbox', checked: it.on !== false, 'aria-label': t('Seç'), onchange: function () { it.on = cb.checked; count(); } });
				var ti = h('input.flxg-input.is-sm', { type: 'text', value: it.title, oninput: function () { it.title = ti.value; } });
				var du = h('input.flxg-input.is-sm.flxg-doc-ai-due', { type: 'date', value: it.due || '', title: t('Bitiş'), onchange: function () { it.due = du.value; } });
				rows.appendChild(h('.flxg-doc-ai-item', cb, ti, people(it.assignee), du,
					h('button.flxg-icon-btn.is-xs', { type: 'button', title: t('Sil'), onclick: function () { r.items.splice(i, 1); draw(); count(); } }, Y.icon('x', 13))));
			});
			rows.appendChild(h('button.flxg-link-btn.flxg-doc-ai-add', { type: 'button', onclick: function () {
				r.items.push({ title: '', assignee: 0, due: '' });
				draw();
				var ins = Y.$$('.flxg-doc-ai-item input[type=text]', rows);
				if (ins.length) ins[ins.length - 1].focus();
			} }, Y.icon('plus', 13), t('Madde ekle')));
			aiFit(st);
		}
		function chosen() { return r.items.filter(function (it) { return !it.task && it.on !== false && (it.title || '').trim(); }); }
		var addBtn = h('button.flxg-btn.is-sm.is-primary', { type: 'button' });
		function count() {
			var n = chosen().length;
			Y.clear(addBtn).append(Y.icon('plus', 13), t('Görev olarak ekle ({n})', { n: n }));
			addBtn.disabled = !n || !st.list;
		}
		addBtn.onclick = function () {
			var todo = chosen();
			if (!todo.length || !st.list) return;
			addBtn.disabled = true;
			Y.store('doc-ai-list', st.list);
			var ok = 0;
			var chain = Promise.resolve();
			todo.forEach(function (it) {
				chain = chain.then(function () {
					var body = { list: st.list, title: it.title.trim() };
					if (it.due) body.due = it.due;
					if (it.assignee) body.assignees = [it.assignee];
					return Y.api('POST', 'tasks', body, { quiet: true }).then(function (res) { it.task = res.task.id; ok++; }, function (e) { it.err = e.message; });
				});
			});
			chain.then(function () {
				if (AIS === st) { draw(); count(); }
				var l = Y.list(st.list);
				Y.toast(t('{n} görev "{l}" listesine eklendi.', { n: ok, l: l ? l.name : '' }), { ms: 8000, action: l ? { label: t('Listeyi aç'), fn: function () { Y.go('#/list/' + l.id); } } : null });
				var bad = todo.filter(function (it) { return it.err; });
				if (bad.length) Y.toast(bad[0].err, { error: true });
				if (Y.reloadTasks) Y.reloadTasks();
			});
		};
		box.appendChild(rows);
		draw();
		var foot = h('.flxg-doc-ai-foot');
		if (lists.length) {
			var sel = h('select.flxg-input.is-sm.flxg-doc-ai-list-sel', { 'aria-label': t('Liste'), onchange: function () { st.list = +sel.value; count(); } });
			var lastSp = null;
			var grp = null;
			lists.forEach(function (l) {
				if (l.space_id !== lastSp) { lastSp = l.space_id; var spc = Y.space(l.space_id); grp = h('optgroup', { label: spc ? spc.name : '' }); sel.appendChild(grp); }
				grp.appendChild(h('option', { value: l.id, selected: l.id === st.list }, l.name));
			});
			box.appendChild(h('.flxg-doc-ai-target', h('span.flxg-muted', t('Liste')), sel));
			foot.appendChild(addBtn);
			count();
		} else box.appendChild(h('p.flxg-muted.flxg-doc-ai-err', t('Görev ekleyebileceğiniz bir liste yok.')));
		function asHtml() {
			return '<ul data-checklist>' + chosen().map(function (it) { return '<li data-checked="0">' + esc(it.title.trim()) + '</li>'; }).join('') + '</ul>';
		}
		if (edit) foot.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { if (!chosen().length) return; aiInsert(asHtml(), (st.at && st.at.isConnected) ? st.at : D.ed.lastElementChild); } }, t('Sayfaya ekle')));
		if (edit) foot.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { if (chosen().length) aiNewPage(asHtml(), t('Yapılacaklar')); } }, Y.icon('doc', 13), t('Yeni sayfa olarak ekle')));
		foot.appendChild(h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onclick: function () { Y.copy(chosen().map(function (it) { return '- ' + it.title.trim(); }).join('\n')); } }, Y.icon('copy', 13), t('Kopyala')));
		foot.appendChild(h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onclick: function () { aiRun(st, st.item, st.prompt); } }, Y.icon('sparkle', 13), t('Yeniden üret')));
		foot.appendChild(h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onclick: function () { st.state = 'menu'; st.item = null; st.result = null; aiDraw(st); } }, Y.icon('back', 13), t('Geri')));
		box.appendChild(foot);
		box.appendChild(askRow);
	}
	/** Sonucu bu sayfanın altına yeni bir sayfa olarak ekler ve o sayfayı açar. */
	function aiNewPage(html, title) {
		if (!D || !D.edit || !D.page) return;
		subPage(title, html).then(function (r) { if (r) { aiClose(); Y.toast(t('Yeni sayfa oluşturuldu.')); } });
	}
	function aiTitleOf(html, fallback) {
		var d = document.createElement('div');
		d.innerHTML = html;
		var hd = d.querySelector('h1,h2,h3');
		return (hd ? hd.textContent : fallback || '').trim();
	}
	/** Seçimi sonuçla değiştirir (tarayıcının geri al'ı da çalışır). Seçim değiştiyse sonuç altına eklenir. */
	function aiReplace(info, r) {
		if (!D) return;
		var rg = info.range;
		var ok = rg.startContainer.isConnected && rg.endContainer.isConnected && D.ed.contains(rg.commonAncestorContainer) && rg.toString() === info.text;
		if (!ok) {
			Y.toast(t('Seçili metin bu arada değişti; sonuç altına eklendi.'));
			aiInsert(r.inline ? '<p>' + r.html + '</p>' : r.html, info.end && info.end.isConnected ? info.end : caretBlock());
			return;
		}
		var html = r.html;
		// Seçimin hemen arkasındaki noktalama sonuçta da varsa ikilenmesin ("planladı..").
		var ec = rg.endContainer;
		var next = ec.nodeType === 3 ? ec.textContent.charAt(rg.endOffset) : '';
		if (r.inline && next && /[.,;:!?]/.test(next) && html.slice(-1) === next) html = html.slice(0, -1);
		D.ed.focus({ preventScroll: true });
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(rg);
		document.execCommand('insertHTML', false, html);
		// Tarayıcı eklediği metne hesaplanmış stil (span style="caret-color…") koyar; sunucu bunları zaten siler.
		Y.$$('span[style]:not([class]):not([data-task])', D.ed).forEach(function (sp) { while (sp.firstChild) sp.parentNode.insertBefore(sp.firstChild, sp); sp.remove(); });
		Y.$$('strong[style],em[style],b[style],i[style],u[style],a[style],s[style],mark[style],code[style]', D.ed).forEach(function (x) { x.removeAttribute('style'); });
		normalize();
		hydrate(D.ed);
		markEmpty();
		changed();
		refreshTaskRefs();
		Y.toast(t('Değiştirildi.'), { ms: 7000, action: { label: t('Geri al'), fn: function () { if (!D) return; D.ed.focus({ preventScroll: true }); document.execCommand('undo'); normalize(); markEmpty(); changed(); } } });
	}
	/** Sonuç bloklarını ref bloğunun altına (before ise en başa) ekler. Boş paragrafın yerine geçer. */
	function aiInsert(html, ref, before) {
		if (!D) return;
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		var els = [];
		var loose = null;
		Array.prototype.slice.call(tmp.childNodes).forEach(function (n) {
			if (n.nodeType === 1 && BLOCK[n.tagName]) { loose = null; els.push(fromHtml(n.outerHTML, newId())); return; }
			if (n.nodeType === 3 && !n.textContent.trim()) return;
			if (!loose) { loose = fromHtml('', newId()); loose.innerHTML = ''; els.push(loose); }
			loose.appendChild(n);
		});
		if (!els.length) return;
		var removed = null;
		if (before) {
			var first = D.ed.firstElementChild;
			els.forEach(function (el) { D.ed.insertBefore(el, first); });
		} else {
			var at = ref && ref.parentNode === D.ed ? ref : D.ed.lastElementChild;
			if (at && at.tagName === 'P' && !at.textContent.trim() && !at.querySelector('img,span,a')) { removed = at; D.ed.replaceChild(els[0], at); at = els[0]; els.slice(1).forEach(function (el) { D.ed.insertBefore(el, at.nextSibling); at = el; }); }
			else els.forEach(function (el) { if (at) D.ed.insertBefore(el, at.nextSibling); else D.ed.appendChild(el); at = el; });
		}
		els.forEach(function (el) { el.classList.add('is-ai-new'); setTimeout(function () { el.classList.remove('is-ai-new'); if (!el.getAttribute('class')) el.removeAttribute('class'); }, 1600); });
		var last = els[els.length - 1];
		if (D.edit) { D.ed.focus({ preventScroll: true }); setCaret({ b: last.getAttribute('data-b'), o: last.textContent.length }); }
		// Sayfa yerinde kalır; eklenen kısım görünmüyorsa yalnız ona kadar kayar.
		els[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		markEmpty();
		changed();
		refreshTaskRefs();
		Y.toast(t('Sayfaya eklendi.'), { ms: 7000, action: { label: t('Geri al'), fn: function () {
			if (!D) return;
			var anchor = els[0];
			if (removed && anchor.isConnected) D.ed.insertBefore(removed, anchor);
			els.forEach(function (el) { if (el.isConnected) el.remove(); });
			if (!D.ed.children.length) D.ed.appendChild(fromHtml('', newId()));
			markEmpty();
			changed();
		} } });
	}

	/* ---------------- Yazdır ---------------- */
	function printPage() {
		var c = D.ed.cloneNode(true);
		Y.$$('[data-b]', c).forEach(function (x) { x.removeAttribute('data-b'); });
		Y.$$('[contenteditable]', c).forEach(function (x) { x.removeAttribute('contenteditable'); });
		var title = D.titleEl.value || t('Başlıksız');
		Y.printHtml(D.doc.title + ' — ' + title, '<h1>' + esc(title) + '</h1><div class="meta">' + esc(D.doc.title) + ' · ' + esc(new Date().toLocaleDateString(Y.locale)) + '</div>' + c.innerHTML);
	}
})();
