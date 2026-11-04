/* Flux Studio — uygulama: galeri, klasörler, üretim çubuğu, görüntüleyici, Beyin fırtınası, kişiler, ayarlar. */
(function () {
	'use strict';
	var S = window.FLXST;
	var h = S.h, t = S.t, B = S.B;

	var ROLES = [
		['', t('Genel')],
		['identity', t('Kişi (yüz/kimlik)')],
		['style', t('Stil')],
		['product', t('Ürün')],
		['background', t('Arka plan')],
		['composition', t('Kompozisyon')],
		['first_frame', t('İlk kare')],
		['last_frame', t('Son kare')]
	];
	function roleLabel(k) {
		for (var i = 0; i < ROLES.length; i++) if (ROLES[i][0] === k) return ROLES[i][1];
		return k;
	}
	var QUALITY = { auto: t('Otomatik'), low: t('Düşük'), medium: t('Orta'), high: t('Yüksek') };
	var PENDING = { queued: 1, running: 1 };

	var st = {
		boot: null,
		items: [],
		byId: {},
		more: true,
		loading: false,
		f: { folder: 'all', kind: '', fav: false, q: '', owner: 'me' },
		sel: {},
		selecting: false,
		c: S.pref('composer') || {},
		refs: [],
		polling: null,
		pollStart: 0,
		dash: false
	};

	var root, side, gallery, grid, sentinel, selBar, composer, topTitle, searchIn, dashEl, dashApi;

	/* ---------------- Tema ---------------- */
	function applyTheme() {
		var m = S.pref('theme') || B.mode || 'auto';
		var dark = m === 'dark' || (m === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
		root.setAttribute('data-theme', dark ? 'dark' : 'light');
		return m;
	}
	function cycleTheme() {
		var order = ['auto', 'light', 'dark'];
		var m = S.pref('theme') || B.mode || 'auto';
		S.pref('theme', order[(order.indexOf(m) + 1) % 3]);
		var now = applyTheme();
		S.toast({ auto: t('Tema: sistem'), light: t('Tema: açık'), dark: t('Tema: koyu') }[now]);
	}

	/* ---------------- Başlangıç ---------------- */
	function start() {
		root = document.getElementById('flxst-app');
		if (!root) return;
		S.root = root;
		applyTheme();
		if (window.matchMedia) {
			var mq = window.matchMedia('(prefers-color-scheme: dark)');
			(mq.addEventListener ? mq.addEventListener.bind(mq, 'change') : mq.addListener.bind(mq))(applyTheme);
		}
		root.appendChild(h('.flxst-boot', h('.flxst-spin'), h('span', t('Stüdyo açılıyor…'))));
		S.api('GET', 'boot').then(function (d) {
			st.boot = d;
			S.clear(root);
			build();
			load(true);
			resumePending();
		}, function (e) {
			S.clear(root).appendChild(h('.flxst-empty', S.icon('alert', 28), h('p', e.message), h('button.flxst-btn', { type: 'button', onclick: function () { location.reload(); } }, t('Yeniden dene'))));
		});
	}

	function models(kind) {
		var all = (st.boot.models && st.boot.models.models) || {};
		return Object.keys(all).filter(function (id) { return all[id].kind === kind; }).map(function (id) {
			var m = all[id];
			m.id = id;
			return m;
		});
	}
	function model(id) {
		var all = (st.boot.models && st.boot.models.models) || {};
		return all[id] || null;
	}
	function providerLabel(p) {
		var pr = (st.boot.models && st.boot.models.providers) || {};
		return (pr[p] && pr[p].label) || p;
	}

	/* ---------------- İskelet ---------------- */
	function build() {
		side = h('aside.flxst-side');
		topTitle = h('h1.flxst-title');
		searchIn = h('input.flxst-search', { type: 'search', placeholder: t('İstem, başlık ya da model ara'), 'aria-label': t('Ara'), value: st.f.q });
		searchIn.addEventListener('input', S.debounce(function () { st.f.q = searchIn.value.trim(); load(true); }, 350));
		var top = h('header.flxst-top',
			S.iconBtn('menu', t('Menü'), function () { root.classList.toggle('is-nav'); }, 'flxst-only-m'),
			topTitle,
			h('.flxst-searchbox', S.icon('search', 16), searchIn),
			st.boot.user.manage ? ownerSwitch() : null,
			S.iconBtn('check', t('Seç'), function () { setSelecting(!st.selecting); }, 'flxst-selbtn')
		);
		grid = h('.flxst-grid');
		sentinel = h('.flxst-sentinel');
		gallery = h('.flxst-gallery', grid, sentinel);
		selBar = h('.flxst-selbar', { hidden: true });
		composer = h('.flxst-composer');
		dashEl = h('.flxst-dash');
		var main = h('main.flxst-main', top, aiBanner(), gallery, selBar, dashEl, composer);
		root.appendChild(side);
		root.appendChild(main);
		root.appendChild(h('.flxst-scrim', { onclick: function () { root.classList.remove('is-nav'); } }));
		drawSide();
		drawComposer();
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) {
				if (en[0].isIntersecting && st.more && !st.loading && st.items.length) load(false);
			}, { root: gallery, rootMargin: '600px' }).observe(sentinel);
		}
		document.addEventListener('visibilitychange', function () { if (!document.hidden) schedulePoll(500); });
		document.addEventListener('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && document.activeElement === S.$('.flxst-prompt', composer)) {
				e.preventDefault();
				generate();
			}
		});
	}

	function aiBanner() {
		if (st.boot.ai.media && models('image').concat(models('video')).some(function (m) { return m.providers.length; })) return null;
		var msg = st.boot.ai.media ? t('Görsel ve video üretimi için Flux AI & MCP › Ayarlar › Görsel ve video bölümünden en az bir anahtar (fal.ai, EvoLink…) girin.') : t('Flux AI (0.6.0+) kurulu değil. Üretim Flux AI üzerinden yapılır.');
		return h('.flxst-banner', S.icon('alert', 16), h('span', msg), st.boot.ai.setup ? h('a.flxst-btn.is-ghost', { href: st.boot.ai.setup }, t('Flux AI ayarları')) : null);
	}

	function ownerSwitch() {
		var seg = h('.flxst-seg.is-small');
		[['me', t('Benim')], ['all', t('Herkes')]].forEach(function (o) {
			seg.appendChild(h('button', { type: 'button', class: 'flxst-seg-b' + (st.f.owner === o[0] ? ' is-on' : ''), onclick: function () {
				st.f.owner = o[0];
				Array.prototype.forEach.call(seg.children, function (c, i) { c.classList.toggle('is-on', i === (o[0] === 'me' ? 0 : 1)); });
				load(true);
			} }, o[1]));
		});
		return seg;
	}

	/* ---------------- Kenar çubuğu ---------------- */
	function navItem(icon, label, on, onClick, extra) {
		return h('.flxst-nav' + (on ? '.is-on' : ''), { role: 'button', tabindex: 0, onclick: onClick, onkeydown: function (e) { if (e.key === 'Enter') onClick(); } }, S.icon(icon, 17), h('span.flxst-nav-l', label), extra || null);
	}
	function showDash(on) {
		st.dash = on;
		root.classList.toggle('is-dash', on);
		root.classList.remove('is-nav');
		if (!on) { S.dashHideTip && S.dashHideTip(); return; }
		if (st.selecting) setSelecting(false);
		drawSide();
		if (dashApi) dashApi.refresh();
		else dashApi = S.dash(dashEl);
	}
	function setFilter(o) {
		if (st.dash) showDash(false);
		Object.keys(o).forEach(function (k) { st.f[k] = o[k]; });
		root.classList.remove('is-nav');
		drawSide();
		load(true);
	}
	function drawSide() {
		var f = st.f, b = st.boot;
		S.clear(side);
		side.appendChild(h('.flxst-brand', ((window.FLXST_BOOT || {}).logo ? h('img.flxst-logo.is-img', { src: window.FLXST_BOOT.logo, alt: '' }) : h('span.flxst-logo', S.icon('sparkle', 18))), h('strong', 'Flux Studio'), h('span.flxst-ver', 'v' + b.version)));
		var all = f.folder === 'all' && !f.fav && !st.dash;
		f = st.dash ? {} : f;
		side.appendChild(h('nav.flxst-navs',
			navItem('grid', t('Tümü'), all && !f.kind, function () { setFilter({ folder: 'all', fav: false, kind: '' }); }),
			navItem('star', t('Favoriler'), f.fav, function () { setFilter({ folder: 'all', fav: true, kind: '' }); }),
			navItem('image', t('Görseller'), all && f.kind === 'image', function () { setFilter({ folder: 'all', fav: false, kind: 'image' }); }),
			navItem('video', t('Videolar'), all && f.kind === 'video', function () { setFilter({ folder: 'all', fav: false, kind: 'video' }); })
		));

		// Yönetici panosu: kullanım, üretimler, harcama (Flux AI kayıtları).
		if (b.user.manage) side.appendChild(h('nav.flxst-navs.is-admin', navItem('chart', t('Pano'), st.dash, function () { showDash(true); })));

		side.appendChild(h('.flxst-side-h', h('span', t('Klasörler')), S.iconBtn('folderPlus', t('Yeni klasör'), function () { newFolder(0); })));
		var tree = h('.flxst-tree');
		tree.appendChild(navItem('home', t('Klasörsüz'), f.folder === 0 || f.folder === '0', function () { setFilter({ folder: 0, fav: false }); }));
		dropTarget(tree.lastChild, 0);
		(function walk(parent, depth) {
			b.folders.filter(function (x) { return x.parent === parent; }).sort(function (a, c) { return a.name.localeCompare(c.name, S.locale); }).forEach(function (fo) {
				var mine = fo.owner === b.user.id || b.user.manage;
				var el = navItem('folder', fo.name, +f.folder === fo.id && f.folder !== 'all', function () { setFilter({ folder: fo.id, fav: false }); },
					h('span.flxst-nav-x', fo.shared ? h('span.flxst-tag', { title: t('Ekiple paylaşılıyor') }, S.icon('users', 12)) : null,
						mine ? S.iconBtn('dots', t('Klasör işlemleri'), function (e) { e.stopPropagation(); folderMenu(e.currentTarget, fo); }, 'is-xs') : null));
				el.style.paddingLeft = (10 + depth * 14) + 'px';
				dropTarget(el, fo.id);
				tree.appendChild(el);
				walk(fo.id, depth + 1);
			});
		})(0, 0);
		side.appendChild(tree);

		side.appendChild(h('.flxst-side-h', h('span', t('Kişiler')), S.iconBtn('plus', t('Kişi ekle'), function () { peopleModal(true); })));
		var pl = h('.flxst-people');
		b.people.slice(0, 12).forEach(function (p) {
			pl.appendChild(h('button.flxst-person', { type: 'button', title: t('{name} — üretime ekle', { name: p.name }), onclick: function () { addRef({ type: 'person', id: p.id, name: p.name, thumb: p.photos[0] ? p.photos[0].thumb : '' }); } },
				p.photos[0] ? h('img', { src: p.photos[0].thumb, alt: '' }) : h('span.flxst-av', p.name.charAt(0).toUpperCase()), h('span', p.name)));
		});
		if (!b.people.length) pl.appendChild(h('p.flxst-muted.is-small', t('Yüz referansı için kişi ekleyin; istemde @ad yazarak kullanın.')));
		pl.appendChild(h('button.flxst-link', { type: 'button', onclick: function () { peopleModal(false); } }, t('Kişileri yönet')));
		side.appendChild(pl);

		var foot = h('.flxst-side-foot');
		var bud = budgetLine();
		if (bud) foot.appendChild(bud);
		foot.appendChild(h('.flxst-row',
			h('a.flxst-ib', { href: B.adminUrl, title: t('Panele dön'), 'aria-label': t('Panele dön') }, S.icon('back', 18)),
			S.iconBtn((S.pref('theme') || B.mode) === 'dark' ? 'moon' : 'sun', t('Tema'), cycleTheme),
			b.user.manage ? S.iconBtn('settings', t('Stüdyo ayarları'), settingsModal) : null
		));
		side.appendChild(foot);
		updateTitle();
	}
	function budgetLine() {
		var bs = st.boot.budget;
		if (!bs) return null;
		var k = Object.keys(bs).filter(function (x) { return bs[x].limit > 0; })[0];
		if (!k) return null;
		var x = bs[k];
		return h('.flxst-budget' + (x.over ? '.is-over' : ''), { title: t('Flux AI harcama sınırı') },
			h('.flxst-budget-t', h('span', x.label), h('span', S.money(x.spent) + ' / ' + S.money(x.limit))),
			h('.flxst-bar', h('i', { style: { width: Math.min(100, x.pct) + '%' } })));
	}
	function updateTitle() {
		var f = st.f, txt = t('Tümü');
		if (st.dash) txt = t('Pano');
		else if (f.fav) txt = t('Favoriler');
		else if (f.folder === 0 || f.folder === '0') txt = t('Klasörsüz');
		else if (f.folder !== 'all') {
			var fo = st.boot.folders.filter(function (x) { return x.id === +f.folder; })[0];
			txt = fo ? fo.name : t('Klasör');
		} else if (f.kind === 'image') txt = t('Görseller');
		else if (f.kind === 'video') txt = t('Videolar');
		topTitle.textContent = txt;
	}

	function newFolder(parent) {
		S.prompt(parent ? t('Alt klasör') : t('Yeni klasör'), '', t('Oluştur')).then(function (name) {
			if (!name) return;
			S.api('POST', 'folders', { name: name, parent: parent }).then(function (fo) {
				st.boot.folders.push(fo);
				drawSide();
				S.toast(t('Klasör oluşturuldu'));
			}, S.fail);
		});
	}
	function folderMenu(anchor, fo) {
		S.menu(anchor, [
			{ icon: 'edit', label: t('Yeniden adlandır'), onClick: function () {
				S.prompt(t('Klasör adı'), fo.name).then(function (n) {
					if (!n) return;
					S.api('POST', 'folders/' + fo.id, { name: n }).then(function (x) { Object.assign(fo, x); drawSide(); }, S.fail);
				});
			} },
			{ icon: 'users', label: fo.shared ? t('Paylaşımı kapat') : t('Ekiple paylaş'), onClick: function () {
				S.api('POST', 'folders/' + fo.id, { shared: !fo.shared }).then(function (x) { Object.assign(fo, x); drawSide(); S.toast(x.shared ? t('Klasör ekiple paylaşılıyor') : t('Klasör yalnız sizin')); }, S.fail);
			} },
			{ icon: 'folderPlus', label: t('Alt klasör'), onClick: function () { newFolder(fo.id); } },
			{ sep: true },
			{ icon: 'trash', label: t('Klasörü sil'), danger: true, onClick: function () {
				S.confirm(t('Klasör silinsin mi?'), t('İçindeki üretimler ve alt klasörler bir üst klasöre taşınır; hiçbir dosya silinmez.'), t('Sil'), true).then(function (ok) {
					if (!ok) return;
					S.api('POST', 'folders/' + fo.id + '/delete').then(function () {
						st.boot.folders = st.boot.folders.filter(function (x) { return x.id !== fo.id; });
						st.boot.folders.forEach(function (x) { if (x.parent === fo.id) x.parent = fo.parent; });
						if (+st.f.folder === fo.id) st.f.folder = 'all';
						drawSide();
						load(true);
					}, S.fail);
				});
			} }
		]);
	}
	function dropTarget(el, folderId) {
		el.addEventListener('dragover', function (e) {
			if (e.dataTransfer.types.indexOf('application/x-flxst-items') < 0) return;
			e.preventDefault();
			el.classList.add('is-drop');
		});
		el.addEventListener('dragleave', function () { el.classList.remove('is-drop'); });
		el.addEventListener('drop', function (e) {
			el.classList.remove('is-drop');
			var raw = e.dataTransfer.getData('application/x-flxst-items');
			if (!raw) return;
			e.preventDefault();
			moveItems(JSON.parse(raw), folderId);
		});
	}
	function moveItems(ids, folder) {
		return S.api('POST', 'items/move', { ids: ids, folder: folder }).then(function (d) {
			ids.forEach(function (id) { if (st.byId[id]) st.byId[id].folder = folder; });
			S.toast(t('{n} üretim taşındı', { n: d.moved }));
			if (st.f.folder !== 'all') load(true);
		}, S.fail);
	}

	/* ---------------- Galeri ---------------- */
	function load(reset) {
		if (st.loading && !reset) return;
		var f = st.f;
		var q = ['limit=60'];
		if (f.folder !== 'all') q.push('folder=' + f.folder);
		if (f.kind) q.push('kind=' + f.kind);
		if (f.fav) q.push('fav=1');
		if (f.q) q.push('q=' + encodeURIComponent(f.q));
		if (f.owner === 'all') q.push('owner=all');
		if (!reset && st.items.length) q.push('before=' + st.items[st.items.length - 1].id);
		st.loading = true;
		var token = (st.loadToken = (st.loadToken || 0) + 1);
		if (reset) {
			st.sel = {};
			updateSel();
			if (!st.items.length) S.clear(grid).appendChild(skeleton());
		}
		updateTitle();
		S.api('GET', 'items?' + q.join('&')).then(function (rows) {
			if (token !== st.loadToken) return;
			if (reset) { st.items = []; st.byId = {}; S.clear(grid); }
			rows.forEach(function (it) {
				if (st.byId[it.id]) return;
				st.items.push(it);
				st.byId[it.id] = it;
				grid.appendChild(card(it));
			});
			st.more = rows.length >= 60;
			if (!st.items.length) grid.appendChild(empty());
			schedulePoll(1500);
		}, function (e) { if (reset) S.clear(grid).appendChild(h('.flxst-empty', S.icon('alert', 24), h('p', e.message))); }).then(function () {
			if (token === st.loadToken) st.loading = false;
		});
	}
	function skeleton() {
		var f = document.createDocumentFragment();
		for (var i = 0; i < 8; i++) f.appendChild(h('.flxst-card.is-sk', { style: { aspectRatio: [1, 0.75, 1.33, 1][i % 4] } }));
		return f;
	}
	function empty() {
		var filt = st.f.q || st.f.fav || st.f.kind || st.f.folder !== 'all';
		return h('.flxst-empty.flxst-grid-empty', S.icon(filt ? 'search' : 'sparkle', 30),
			h('h3', filt ? t('Burada üretim yok') : t('İlk görselinizi üretin')),
			h('p', filt ? t('Filtreyi değiştirin ya da aşağıdan yeni bir üretim başlatın.') : t('Aşağıya ne istediğinizi yazın. Referans görsel sürükleyip bırakabilir, @ ile kişi ekleyebilirsiniz.')));
	}

	function card(it) {
		var el = h('.flxst-card', { dataset: { id: it.id }, tabindex: 0, draggable: it.state === 'done' ? 'true' : 'false' });
		fillCard(el, it);
		el.addEventListener('click', function (e) {
			if (e.target.closest('button')) return;
			if (st.selecting || e.shiftKey || e.metaKey || e.ctrlKey) return toggleSel(it.id);
			if (it.state === 'done') viewer(it.id);
		});
		el.addEventListener('keydown', function (e) { if (e.key === 'Enter' && it.state === 'done') viewer(it.id); });
		el.addEventListener('dragstart', function (e) {
			var ids = st.sel[it.id] ? Object.keys(st.sel).map(Number) : [it.id];
			e.dataTransfer.setData('application/x-flxst-items', JSON.stringify(ids));
			e.dataTransfer.setData('text/uri-list', it.url);
			e.dataTransfer.effectAllowed = 'copyMove';
		});
		return el;
	}
	function ratio(it) {
		if (it.width && it.height) return it.width + ' / ' + it.height;
		var a = (it.params && it.params.aspect) || '1:1';
		var m = /^(\d+):(\d+)$/.exec(a);
		return m ? m[1] + ' / ' + m[2] : (it.kind === 'video' ? '16 / 9' : '1 / 1');
	}
	function fillCard(el, it) {
		S.clear(el);
		el.className = 'flxst-card is-' + it.state + (st.sel[it.id] ? ' is-sel' : '');
		el.style.aspectRatio = ratio(it);
		if (it.state === 'done') {
			if (it.kind === 'video') {
				var v = h('video', { src: it.url, muted: true, loop: true, playsinline: true, preload: 'metadata' });
				v.muted = true;
				el.addEventListener('mouseenter', function () { v.play().catch(function () {}); });
				el.addEventListener('mouseleave', function () { v.pause(); });
				el.appendChild(v);
				el.appendChild(h('span.flxst-badge', S.icon('video', 12), it.params && it.params.duration ? it.params.duration + 's' : ''));
			} else {
				el.appendChild(h('img', { src: it.thumb || it.url, alt: it.title || it.prompt.slice(0, 80), loading: 'lazy', draggable: 'false' }));
			}
			el.appendChild(h('.flxst-card-over',
				h('.flxst-card-top',
					h('span.flxst-check', S.icon('check', 14)),
					h('span.flxst-grow'),
					h('button', { type: 'button', class: 'flxst-ib is-xs is-fav' + (it.favorite ? ' is-on' : ''), title: it.favorite ? t('Favoriden çıkar') : t('Favorilere ekle'), onclick: function () { toggleFav(it); } }, S.icon('star', 15))),
				h('.flxst-card-cap', it.title ? h('strong', it.title) : null, h('span', it.prompt))));
		} else if (PENDING[it.state]) {
			var pct = Math.round(((it.params && it.params.progress) || 0) * 100);
			el.appendChild(h('.flxst-pend',
				h('.flxst-spin'),
				h('strong', it.state === 'queued' ? t('Sırada') : t('Üretiliyor')),
				h('small', ((model(it.model) || {}).label || it.model) + (it.provider ? ' · ' + providerLabel(it.provider) : '')),
				pct ? h('.flxst-bar', h('i', { style: { width: pct + '%' } })) : null,
				h('button.flxst-link', { type: 'button', onclick: function () { cancelJob(it); } }, t('İptal'))));
		} else {
			el.appendChild(h('.flxst-pend.is-err',
				S.icon(it.state === 'cancelled' ? 'stop' : 'alert', 22),
				h('strong', it.state === 'cancelled' ? t('İptal edildi') : t('Üretilemedi')),
				h('small', ((model(it.model) || {}).label || it.model) + (it.provider ? ' · ' + providerLabel(it.provider) : '')),
				h('small.flxst-err', it.error || ''),
				h('.flxst-row',
					h('button.flxst-link', { type: 'button', onclick: function () { reuse(it); } }, t('Ayarları kullan')),
					h('button.flxst-link', { type: 'button', onclick: function () { deleteItems([it.id]); } }, t('Sil')))));
		}
	}
	function redrawCard(it) {
		var el = grid.querySelector('.flxst-card[data-id="' + it.id + '"]');
		if (el) fillCard(el, it);
	}
	function upsert(it, prepend) {
		var old = st.byId[it.id];
		if (old) {
			var wasDone = old.state === 'done';
			Object.assign(old, it);
			if (!wasDone || it.state !== 'done') redrawCard(old);
			return old;
		}
		if (!matchesFilter(it)) return it;
		st.byId[it.id] = it;
		var em = grid.querySelector('.flxst-grid-empty');
		if (em) em.remove();
		if (prepend) {
			st.items.unshift(it);
			grid.insertBefore(card(it), grid.firstChild);
		} else {
			st.items.push(it);
			grid.appendChild(card(it));
		}
		return it;
	}
	function matchesFilter(it) {
		var f = st.f;
		if (f.fav || f.q) return false;
		if (f.kind && it.kind !== f.kind) return false;
		if (f.folder !== 'all' && +f.folder !== it.folder) return false;
		return true;
	}
	function toggleFav(it) {
		it.favorite = !it.favorite;
		redrawCard(it);
		S.api('POST', 'items/' + it.id, { favorite: it.favorite }).catch(function (e) { it.favorite = !it.favorite; redrawCard(it); S.fail(e); });
	}

	/* ---------------- Seçim ---------------- */
	function setSelecting(on) {
		st.selecting = on;
		if (!on) st.sel = {};
		root.classList.toggle('is-selecting', on);
		st.items.forEach(redrawCard);
		updateSel();
	}
	function toggleSel(id) {
		if (st.sel[id]) delete st.sel[id];
		else st.sel[id] = true;
		if (!st.selecting) { st.selecting = true; root.classList.add('is-selecting'); }
		redrawCard(st.byId[id]);
		updateSel();
	}
	function updateSel() {
		if (!selBar) return;
		var ids = Object.keys(st.sel).map(Number);
		S.clear(selBar);
		selBar.hidden = !st.selecting;
		if (!st.selecting) return;
		selBar.style.bottom = (composer.offsetHeight + 28) + 'px';
		selBar.appendChild(h('strong', ids.length ? t('{n} seçili', { n: ids.length }) : t('Üretimlere dokunarak seçin')));
		selBar.appendChild(h('span.flxst-grow'));
		if (ids.length) {
			selBar.appendChild(S.btn('layers', t('Referans yap'), function () {
				ids.forEach(function (id) {
					var it = st.byId[id];
					if (it && it.state === 'done' && it.kind === 'image') addRef({ type: 'item', id: id, thumb: it.thumb, name: it.title || '' });
				});
				setSelecting(false);
			}, 'is-ghost'));
			selBar.appendChild(S.btn('move', t('Taşı'), function (e) { folderPicker(e.currentTarget, function (fid) { moveItems(ids, fid).then(function () { setSelecting(false); }); }); }, 'is-ghost'));
			selBar.appendChild(S.btn('trash', t('Sil'), function () { deleteItems(ids).then(function (ok) { if (ok) setSelecting(false); }); }, 'is-ghost is-danger'));
		}
		selBar.appendChild(S.iconBtn('x', t('Seçimi kapat'), function () { setSelecting(false); }));
	}
	function folderPicker(anchor, cb) {
		var items = [{ icon: 'home', label: t('Klasörsüz'), onClick: function () { cb(0); } }];
		(function walk(parent, depth) {
			st.boot.folders.filter(function (x) { return x.parent === parent && (x.owner === st.boot.user.id || x.shared || st.boot.user.manage); }).forEach(function (fo) {
				items.push({ icon: 'folder', label: new Array(depth + 1).join('  ') + fo.name, onClick: function () { cb(fo.id); } });
				walk(fo.id, depth + 1);
			});
		})(0, 0);
		items.push({ sep: true }, { icon: 'folderPlus', label: t('Yeni klasör…'), onClick: function () {
			S.prompt(t('Yeni klasör'), '', t('Oluştur')).then(function (name) {
				if (!name) return;
				S.api('POST', 'folders', { name: name }).then(function (fo) { st.boot.folders.push(fo); drawSide(); cb(fo.id); }, S.fail);
			});
		} });
		S.menu(anchor, items);
	}
	function deleteItems(ids) {
		return S.confirm(ids.length > 1 ? t('{n} üretim silinsin mi?', { n: ids.length }) : t('Üretim silinsin mi?'), t('Dosyalar sunucudan silinir. Medya kitaplığına eklenmiş kopyalar kalır.'), t('Sil'), true).then(function (ok) {
			if (!ok) return false;
			return S.api('POST', 'items/delete', { ids: ids }).then(function () {
				ids.forEach(function (id) {
					delete st.byId[id];
					var el = grid.querySelector('.flxst-card[data-id="' + id + '"]');
					if (el) el.remove();
				});
				st.items = st.items.filter(function (x) { return st.byId[x.id]; });
				if (!st.items.length) grid.appendChild(empty());
				return true;
			}, function (e) { S.fail(e); return false; });
		});
	}

	/* ---------------- Takip (polling) ---------------- */
	function pendingJobs() {
		var jobs = {};
		st.items.forEach(function (it) { if (PENDING[it.state] && it.job) jobs[it.job] = 1; });
		return Object.keys(jobs);
	}
	function schedulePoll(ms) {
		clearTimeout(st.polling);
		var jobs = pendingJobs();
		if (!jobs.length) { st.pollStart = 0; return; }
		if (!st.pollStart) st.pollStart = Date.now();
		var age = (Date.now() - st.pollStart) / 1000;
		var wait = ms || (age < 120 ? 4000 : age < 600 ? 8000 : 15000);
		st.polling = setTimeout(poll, wait);
	}
	function poll() {
		if (document.hidden) return; // Sekme görünür olunca yeniden başlar.
		var jobs = pendingJobs().slice(0, 8);
		if (!jobs.length) return;
		S.api('GET', 'poll?jobs=' + jobs.join(',')).then(function (d) {
			var finished = 0, failed = 0;
			(d.items || []).forEach(function (it) {
				var old = st.byId[it.id];
				if (old && PENDING[old.state] && it.state === 'done') finished++;
				if (old && PENDING[old.state] && it.state === 'failed') failed++;
				upsert(it, true);
			});
			if (finished) S.toast(finished > 1 ? t('{n} üretim hazır', { n: finished }) : t('Üretim hazır'));
			if (failed) S.toast(t('Bir üretim başarısız oldu'), 'error');
			if (finished || failed) refreshBudget();
		}, function () { /* geçici hata: bir sonraki turda yeniden denenir */ }).then(function () { schedulePoll(); });
	}
	function resumePending() { schedulePoll(1500); }
	function cancelJob(it) {
		S.api('POST', 'jobs/' + it.job + '/cancel').then(function (d) { (d.items || []).forEach(function (x) { upsert(x); }); }, S.fail);
	}
	var refreshBudget = S.debounce(function () {
		S.api('GET', 'boot').then(function (d) { st.boot.budget = d.budget; drawSide(); }, function () {});
	}, 2000);

	/* ---------------- Üretim çubuğu ---------------- */
	function cfg() {
		var c = st.c;
		c.kind = c.kind === 'video' ? 'video' : 'image';
		var list = models(c.kind);
		var def = c.kind === 'video' ? st.boot.settings.video_model : st.boot.settings.image_model;
		if (!c.model || !model(c.model) || model(c.model).kind !== c.kind) {
			var usable = list.filter(function (m) { return m.providers.length; });
			c.model = model(def) && model(def).providers.length ? def : (usable[0] || list[0] || {}).id || '';
		}
		var m = model(c.model) || { aspects: [], resolutions: [], qualities: [], providers: [] };
		if (m.aspects.indexOf(c.aspect) < 0) c.aspect = c.kind === 'video' ? (m.aspects.indexOf('16:9') >= 0 ? '16:9' : m.aspects[0]) : (m.aspects.indexOf('1:1') >= 0 ? '1:1' : m.aspects[0]);
		if (m.resolutions.indexOf(c.resolution) < 0) c.resolution = m.resolutions[c.kind === 'video' ? Math.min(1, m.resolutions.length - 1) : 0];
		if ((m.qualities || []).length && m.qualities.indexOf(c.quality) < 0) c.quality = m.qualities[0];
		var maxN = Math.max(1, Math.min(st.boot.settings.max_n, m.max_n || 1));
		c.n = c.kind === 'video' ? 1 : Math.max(1, Math.min(maxN, +c.n || 1));
		if (m.duration) c.duration = Math.max(m.duration[0], Math.min(m.duration[1], +c.duration || m.duration[2]));
		if (c.audio === undefined) c.audio = true;
		if (c.provider && c.provider !== 'auto' && m.providers.indexOf(c.provider) < 0) c.provider = 'auto';
		return m;
	}
	function saveCfg() {
		var c = Object.assign({}, st.c);
		delete c.prompt;
		S.pref('composer', c);
	}
	function cost(m) {
		var c = st.c;
		var p = m.price && m.price[c.resolution];
		if (!p) return '';
		return '~' + S.money(c.kind === 'video' ? p * (c.duration || 5) : p * c.n);
	}

	function sel(label, value, options, onChange, cls) {
		var s = h('select.flxst-sel' + (cls ? '.' + cls : ''), { 'aria-label': label, title: label });
		options.forEach(function (o) {
			var op = h('option', { value: o[0] }, o[1]);
			if (String(o[0]) === String(value)) op.selected = true;
			if (o[2]) op.disabled = true;
			s.appendChild(op);
		});
		s.addEventListener('change', function () { onChange(s.value); });
		return s;
	}

	function drawComposer() {
		var c = st.c, m = cfg();
		var keepPrompt = S.$('.flxst-prompt', composer);
		var promptVal = keepPrompt ? keepPrompt.value : (c.prompt || '');
		S.clear(composer);

		// Referanslar
		var refs = h('.flxst-refs');
		st.refs.forEach(function (r, i) {
			refs.appendChild(h('.flxst-ref', { title: r.name || roleLabel(r.role || '') },
				r.thumb ? h('img', { src: r.thumb, alt: '' }) : h('span.flxst-av', (r.name || '?').charAt(0)),
				r.type === 'person' ? h('span.flxst-ref-n', '@' + r.name) : h('button.flxst-ref-role', { type: 'button', onclick: function (e) { roleMenu(e.currentTarget, r); } }, roleLabel(r.role || '')),
				h('button.flxst-ref-x', { type: 'button', title: t('Kaldır'), 'aria-label': t('Kaldır'), onclick: function () { st.refs.splice(i, 1); drawComposer(); } }, S.icon('x', 12))));
		});
		var addB = h('button.flxst-ref-add', { type: 'button', title: t('Referans ekle'), onclick: function (e) { refMenu(e.currentTarget); } }, S.icon('plus', 18));
		refs.appendChild(addB);
		var maxRefs = m.max_refs || 14;
		if (st.refs.length) refs.appendChild(h('small.flxst-muted', st.refs.length + ' / ' + maxRefs));

		// İstem
		var ta = h('textarea.flxst-prompt', { rows: 1, placeholder: c.kind === 'video' ? t('Videoda ne olsun? Konu, hareket, kamera… (@ ile kişi ekleyin)') : t('Ne üretmek istiyorsunuz? (@ ile kişi ekleyin, görsel sürükleyip bırakın)'), value: promptVal });
		ta.addEventListener('input', function () {
			autosize(ta);
			c.prompt = ta.value;
			mention(ta);
		});
		ta.addEventListener('paste', function (e) {
			var files = Array.prototype.filter.call((e.clipboardData && e.clipboardData.files) || [], function (f) { return /^image\//.test(f.type); });
			if (files.length) { e.preventDefault(); uploadRefs(files); }
		});

		// Ayarlar
		var kindSeg = h('.flxst-seg');
		[['image', 'image', t('Görsel')], ['video', 'video', t('Video')]].forEach(function (k) {
			kindSeg.appendChild(h('button', { type: 'button', class: 'flxst-seg-b' + (c.kind === k[0] ? ' is-on' : ''), onclick: function () { c.kind = k[0]; c.model = ''; saveCfg(); drawComposer(); } }, S.icon(k[1], 15), h('span', k[2])));
		});
		// Ayarlar: en-boy, çözünürlük, kalite, adet/süre referans satırında sağda; model (önce sağlayıcı, sonra model) altta.
		var opts = h('.flxst-opts',
			m.aspects.length ? sel(t('En-boy oranı'), c.aspect, m.aspects.map(function (a) { return [a, a === 'adaptive' ? t('Uyarlanır') : a]; }), function (v) { c.aspect = v; saveCfg(); }) : null,
			m.resolutions.length ? sel(t('Çözünürlük'), c.resolution, m.resolutions.map(function (r) { return [r, r]; }), function (v) { c.resolution = v; saveCfg(); drawComposer(); }) : null,
			(m.qualities || []).length ? sel(t('Kalite'), c.quality, m.qualities.map(function (q) { return [q, QUALITY[q] || q]; }), function (v) { c.quality = v; saveCfg(); }) : null,
			c.kind === 'image' && (m.max_n || 1) > 1 ? sel(t('Adet'), c.n, range(1, Math.min(st.boot.settings.max_n, m.max_n)).map(function (n) { return [n, '×' + n]; }), function (v) { c.n = +v; saveCfg(); drawComposer(); }) : null,
			c.kind === 'video' && m.duration ? sel(t('Süre'), c.duration, range(m.duration[0], m.duration[1]).map(function (n) { return [n, n + ' sn']; }), function (v) { c.duration = +v; saveCfg(); drawComposer(); }) : null,
			c.kind === 'video' ? h('label.flxst-chk', { title: t('Ses') }, h('input', { type: 'checkbox', checked: c.audio !== false, onchange: function (e) { c.audio = e.target.checked; saveCfg(); } }), h('span', t('Ses'))) : null
		);
		var prov = c.provider && c.provider !== 'auto' ? c.provider : (m.auto || m.providers[0] || '');
		var ctrls = h('.flxst-ctrls', kindSeg,
			models(c.kind).length ? h('button.flxst-mpick', { type: 'button', title: t('Sağlayıcı ve model'), onclick: function (e) { modelPicker(e.currentTarget); } },
				prov ? h('small', providerLabel(prov)) : null,
				h('span', m.label || t('Model seç')),
				S.icon('chevD', 14)) : h('span.flxst-muted', t('Model yok'))
		);

		var price = cost(m);
		var genB = h('button.flxst-btn.is-primary.flxst-gen', { type: 'button', onclick: generate, disabled: !m.providers || !m.providers.length, title: t('Üret (Ctrl/⌘+Enter)') },
			S.icon('sparkle', 16), h('span', t('Üret')), price ? h('small', price) : null);
		var acts = h('.flxst-acts',
			S.iconBtn('template', t('Şablonlar'), function (e) { templateMenu(e.currentTarget); }),
			h('button.flxst-btn.is-ghost', { type: 'button', onclick: brainstorm, disabled: !st.boot.ai.text, title: st.boot.ai.text ? t('Bir fikirden birden çok farklı istem') : t('Flux AI metin modeli hazır değil') }, S.icon('bulb', 16), h('span', t('Beyin fırtınası'))),
			h('button.flxst-btn.is-ghost.flxst-enh', { type: 'button', onclick: function (e) { enhance(ta, e.currentTarget); }, disabled: !st.boot.ai.text, title: st.boot.ai.text ? t('Taslağı modele uygun ayrıntılı isteme çevir') : t('Flux AI metin modeli hazır değil') }, S.icon('sparkle', 16), h('span', t('Geliştir'))),
			genB);

		composer.appendChild(h('.flxst-compose-top', refs, opts));
		composer.appendChild(h('.flxst-compose-main', ta));
		composer.appendChild(h('.flxst-compose-foot', ctrls, acts));
		autosize(ta);

		// Sürükle-bırak: dosya ya da galeriden üretim
		if (!composer.dataset.dnd) {
			composer.dataset.dnd = 1;
			composer.addEventListener('dragover', function (e) { e.preventDefault(); composer.classList.add('is-drop'); });
			composer.addEventListener('dragleave', function (e) { if (!composer.contains(e.relatedTarget)) composer.classList.remove('is-drop'); });
			composer.addEventListener('drop', function (e) {
				e.preventDefault();
				composer.classList.remove('is-drop');
				var raw = e.dataTransfer.getData('application/x-flxst-items');
				if (raw) {
					JSON.parse(raw).forEach(function (id) {
						var it = st.byId[id];
						if (it && it.kind === 'image' && it.state === 'done') addRef({ type: 'item', id: id, thumb: it.thumb, name: it.title });
					});
					return;
				}
				var files = Array.prototype.slice.call(e.dataTransfer.files || []);
				if (files.length) uploadRefs(files);
			});
		}
	}
	function range(a, b) {
		var out = [];
		for (var i = a; i <= b; i++) out.push(i);
		return out;
	}
	/* Model seçici: önce sağlayıcı, sonra o sağlayıcının modelleri. Anahtarı olmayan sağlayıcı soluk görünür. */
	function modelPicker(anchor) {
		var c = st.c, cur = cfg();
		var pr = (st.boot.models && st.boot.models.providers) || {};
		var list = models(c.kind);
		var provs = Object.keys(pr).filter(function (p) { return list.some(function (x) { return (x.routes || x.providers).indexOf(p) >= 0; }); });
		provs.sort(function (a, b) { return (pr[b].ready ? 1 : 0) - (pr[a].ready ? 1 : 0); });
		var box = h('.flxst-mp');
		// Her adımda yeniden konumlanır (liste boyu değişince ekrandan taşmasın).
		var step = function (p) {
			S.clear(box);
			fill(p);
			S.pop(anchor, box, { cls: 'flxst-mp-pop' });
		};
		var fill = function (p) {
			if (!p) {
				box.appendChild(h('.flxst-mp-h', t('Sağlayıcı')));
				provs.forEach(function (id) {
					var n = list.filter(function (x) { return (x.routes || x.providers).indexOf(id) >= 0; }).length;
					var on = id === (c.provider && c.provider !== 'auto' ? c.provider : cur.auto);
					box.appendChild(h('button.flxst-mp-i' + (on ? '.is-on' : '') + (pr[id].ready ? '' : '.is-off'), { type: 'button', onclick: function () { step(id); } },
						h('span', pr[id].label),
						h('small', pr[id].ready ? t('{n} model', { n: n }) : t('anahtar yok')),
						S.icon('chevR', 14)));
				});
				return;
			}
			box.appendChild(h('button.flxst-mp-back', { type: 'button', onclick: function () { step(''); } }, S.icon('chevL', 14), h('span', pr[p] ? pr[p].label : p)));
			list.filter(function (x) { return (x.routes || x.providers).indexOf(p) >= 0; }).forEach(function (x) {
				var ok = x.providers.indexOf(p) >= 0;
				var on = x.id === c.model && p === (c.provider && c.provider !== 'auto' ? c.provider : cur.auto);
				box.appendChild(h('button.flxst-mp-i' + (on ? '.is-on' : '') + (ok ? '' : '.is-off'), {
					type: 'button', disabled: !ok, title: ok ? '' : t('{p} anahtarı girilmemiş (Flux AI & MCP › Ayarlar › Görsel ve video).', { p: pr[p] ? pr[p].label : p }),
					onclick: function () { c.model = x.id; c.provider = p; saveCfg(); S.closePop(); drawComposer(); }
				}, h('span', x.label), on ? S.icon('check', 14) : null));
			});
		};
		step(c.provider && c.provider !== 'auto' ? c.provider : (cur.auto || ''));
	}

	function autosize(ta) {
		ta.style.height = 'auto';
		// En çok iki satır; fazlası kutuda kayar.
		var lh = parseFloat(getComputedStyle(ta).lineHeight) || 22;
		ta.style.height = Math.min(lh * 2 + 12, ta.scrollHeight + 2) + 'px';
	}

	function addRef(r) {
		var m = model(st.c.model) || {};
		var max = m.max_refs || 14;
		if (st.refs.some(function (x) { return x.type === r.type && x.id === r.id; })) return S.toast(t('Bu referans zaten ekli'));
		if (st.refs.length >= max) return S.toast(t('Bu model en çok {n} referans alır', { n: max }), 'error');
		st.refs.push(r);
		drawComposer();
	}
	function roleMenu(anchor, r) {
		S.menu(anchor, ROLES.map(function (x) {
			return { label: x[1], checked: (r.role || '') === x[0], onClick: function () { r.role = x[0]; drawComposer(); } };
		}).concat(r.type === 'ref' ? [{ sep: true }, { icon: 'star', label: t('Kayıtlı referanslara ekle'), onClick: function () {
			S.api('POST', 'refs/' + r.id, { saved: true, role: r.role || '' }).then(function (x) {
				st.boot.refs = st.boot.refs.filter(function (y) { return y.id !== x.id; }).concat([x]);
				S.toast(t('Kaydedildi'));
			}, S.fail);
		} }] : []));
	}
	function refMenu(anchor) {
		var saved = st.boot.refs.filter(function (r) { return r.saved && !r.person; });
		var items = [
			{ icon: 'upload', label: t('Dosyadan yükle'), onClick: pickFiles },
			{ icon: 'grid', label: t('Galeriden seç'), onClick: galleryPicker }
		];
		if (st.boot.people.length) {
			items.push({ head: t('Kişiler') });
			st.boot.people.forEach(function (p) {
				items.push({ icon: 'user', label: p.name, hint: p.photos.length ? t('{n} foto', { n: p.photos.length }) : t('foto yok'), disabled: !p.photos.length, onClick: function () { addRef({ type: 'person', id: p.id, name: p.name, thumb: p.photos[0] ? p.photos[0].thumb : '' }); } });
			});
		}
		if (saved.length) {
			items.push({ head: t('Kayıtlı referanslar') });
			saved.slice(0, 12).forEach(function (r) {
				items.push({ icon: 'image', label: r.name || roleLabel(r.role) || t('Referans'), onClick: function () { addRef({ type: 'ref', id: r.id, role: r.role, thumb: r.thumb, name: r.name }); } });
			});
		}
		S.menu(anchor, items);
	}
	function pickFiles() {
		var inp = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif', multiple: true });
		inp.addEventListener('change', function () { uploadRefs(Array.prototype.slice.call(inp.files)); });
		inp.click();
	}
	function uploadRefs(files) {
		files = files.filter(function (f) { return /^image\/(png|jpeg|webp|gif)$/.test(f.type); });
		if (!files.length) return S.toast(t('Yalnız PNG, JPEG, WebP ya da GIF eklenebilir.'), 'error');
		files.forEach(function (f) {
			var fd = new FormData();
			fd.append('file', f, f.name);
			fd.append('name', f.name.replace(/\.[^.]+$/, ''));
			var tmp = { type: 'ref', id: 'tmp' + Math.random(), thumb: URL.createObjectURL(f), name: f.name, role: '' };
			st.refs.push(tmp);
			drawComposer();
			S.api('POST', 'refs', fd).then(function (r) {
				Object.assign(tmp, { id: r.id, thumb: r.thumb, name: r.name });
				st.boot.refs.push(r);
				drawComposer();
			}, function (e) {
				st.refs = st.refs.filter(function (x) { return x !== tmp; });
				drawComposer();
				S.fail(e);
			});
		});
	}
	function galleryPicker() {
		var chosen = {};
		var list = h('.flxst-pick');
		var m = S.modal(t('Galeriden referans seç'), list, {
			wide: true,
			foot: [h('button.flxst-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxst-btn.is-primary', { type: 'button', onclick: function () {
					Object.keys(chosen).forEach(function (id) { var it = chosen[id]; addRef({ type: 'item', id: it.id, thumb: it.thumb, name: it.title }); });
					m.close();
				} }, t('Ekle'))]
		});
		list.appendChild(h('.flxst-spin'));
		S.api('GET', 'items?kind=image&limit=120').then(function (rows) {
			S.clear(list);
			rows.filter(function (x) { return x.state === 'done'; }).forEach(function (it) {
				var b = h('button.flxst-pick-i', { type: 'button', onclick: function () {
					if (chosen[it.id]) delete chosen[it.id];
					else chosen[it.id] = it;
					b.classList.toggle('is-sel', !!chosen[it.id]);
				} }, h('img', { src: it.thumb, alt: '', loading: 'lazy' }), h('span.flxst-check', S.icon('check', 14)));
				list.appendChild(b);
			});
			if (!list.firstChild) list.appendChild(h('p.flxst-muted', t('Henüz görsel yok.')));
		}, S.fail);
	}

	/* @kişi önerisi */
	function mention(ta) {
		var before = ta.value.slice(0, ta.selectionStart);
		var mm = /(^|\s)@([^\s@]{0,30})$/.exec(before);
		if (!mm || !st.boot.people.length) return S.closePop();
		var q = S.norm(mm[2]);
		var hits = st.boot.people.filter(function (p) { return S.norm(p.name).indexOf(q) === 0 || S.norm(p.name).indexOf(' ' + q) >= 0; }).slice(0, 8);
		if (!hits.length) return S.closePop();
		S.menu(ta, hits.map(function (p) {
			return { icon: 'user', label: p.name, hint: t('{n} foto', { n: p.photos.length }), onClick: function () {
				var start = before.length - mm[2].length - 1;
				ta.value = ta.value.slice(0, start) + '@' + p.name + ' ' + ta.value.slice(ta.selectionStart);
				st.c.prompt = ta.value;
				ta.focus();
				var pos = start + p.name.length + 2;
				ta.setSelectionRange(pos, pos);
				if (p.photos.length) addRef({ type: 'person', id: p.id, name: p.name, thumb: p.photos[0].thumb });
				else S.toast(t('{name} için fotoğraf yok; Kişileri yönet’ten ekleyin', { name: p.name }), 'error');
			} };
		}), { cls: 'is-mention' });
	}

	function refPayload() {
		return st.refs.filter(function (r) { return typeof r.id === 'number'; }).map(function (r) { return { type: r.type, id: r.id, role: r.role || '' }; });
	}

	function enhance(ta, btn) {
		if (!ta.value.trim()) return S.toast(t('Önce kısa bir taslak yazın.'), 'error');
		var draft = ta.value;
		btn.disabled = true;
		btn.classList.add('is-busy');
		S.api('POST', 'enhance', { prompt: draft, mode: st.c.kind, refs: refPayload(), model_label: (model(st.c.model) || {}).label || '' }).then(function (d) {
			ta.value = d.prompt;
			st.c.prompt = d.prompt;
			st.c.enhanced = true;
			st.c.draft = draft;
			autosize(ta);
			S.toast(t('İstem geliştirildi'), null);
			showUndo(ta, draft);
		}, S.fail).then(function () { btn.disabled = false; btn.classList.remove('is-busy'); });
	}
	function showUndo(ta, draft) {
		var b = h('button.flxst-undo', { type: 'button', onclick: function () { ta.value = draft; st.c.prompt = draft; st.c.enhanced = false; autosize(ta); b.remove(); } }, S.icon('undo', 14), t('Taslağa dön'));
		ta.parentNode.appendChild(b);
		setTimeout(function () { b.remove(); }, 15000);
	}

	function generate(extra) {
		extra = extra && extra.prompt ? extra : {};
		var c = st.c, m = cfg();
		var ta = S.$('.flxst-prompt', composer);
		var prompt = extra.prompt || (ta ? ta.value.trim() : '');
		if (!prompt) { if (ta) ta.focus(); return Promise.resolve(S.toast(t('Ne üretileceğini yazın.'), 'error')); }
		if (st.refs.some(function (r) { return typeof r.id !== 'number'; })) return Promise.resolve(S.toast(t('Referanslar yükleniyor, bir saniye…')));
		var body = {
			kind: c.kind,
			model: c.model,
			provider: c.provider || 'auto',
			prompt: prompt,
			n: extra.n || c.n,
			aspect: c.aspect,
			resolution: c.resolution,
			quality: c.quality || '',
			refs: refPayload(),
			folder: st.f.folder !== 'all' ? +st.f.folder : 0,
			enhanced: !extra.prompt && !!c.enhanced,
			draft: !extra.prompt && c.enhanced ? c.draft || '' : ''
		};
		if (c.kind === 'video') {
			body.duration = c.duration;
			body.audio = c.audio !== false;
		}
		['batch', 'title', 'idea', 'mask', 'parent', 'layers'].forEach(function (k) { if (extra[k] !== undefined) body[k] = extra[k]; });
		var btn = S.$('.flxst-gen', composer);
		if (btn && !extra.batch) btn.classList.add('is-busy');
		return S.api('POST', 'generate', body).then(function (d) {
			(d.items || []).slice().reverse().forEach(function (it) { upsert(it, true); });
			gallery.scrollTo({ top: 0, behavior: 'smooth' });
			// İstem kutusu boşalır; iş galeride kuyruk kartı olarak görünür (ayarlar karttan tekrar kullanılabilir).
			if (!extra.prompt && ta) {
				c.enhanced = false;
				c.draft = '';
				c.prompt = '';
				ta.value = '';
				autosize(ta);
				var undo = S.$('.flxst-undo', composer);
				if (undo) undo.remove();
			}
			st.pollStart = Date.now();
			schedulePoll(3000);
			return d;
		}, function (e) {
			((e.data && e.data.items) || []).forEach(function (it) { upsert(it, true); });
			S.fail(e);
			throw e;
		}).then(function (d) { if (btn) btn.classList.remove('is-busy'); return d; }, function (e) { if (btn) btn.classList.remove('is-busy'); if (extra.mask || extra.batch) throw e; });
	}

	function reuse(it) {
		var p = it.params || {};
		st.c.kind = it.kind;
		st.c.model = it.model;
		['aspect', 'resolution', 'quality', 'duration'].forEach(function (k) { if (p[k]) st.c[k] = p[k]; });
		if (p.n) st.c.n = p.n;
		if (p.audio !== undefined) st.c.audio = p.audio;
		st.refs = (p.refs || []).filter(function (r) { return r.role !== 'edit'; }).map(function (r) { return { type: r.type, id: r.id, role: r.role, thumb: r.thumb, name: r.name || '' }; });
		var ta = S.$('.flxst-prompt', composer);
		if (ta) ta.value = it.prompt;
		st.c.prompt = it.prompt;
		saveCfg();
		drawComposer();
		var ta2 = S.$('.flxst-prompt', composer);
		if (ta2) ta2.focus();
	}

	/* ---------------- Şablonlar ---------------- */
	function templateMenu(anchor) {
		var tps = st.boot.templates;
		var items = tps.map(function (tp) {
			return { icon: 'template', label: tp.name, hint: tp.shared ? t('ekip') : '', onClick: function () { reuse({ kind: tp.params.kind || 'image', model: tp.params.model || '', prompt: tp.prompt, params: tp.params }); } };
		});
		if (!items.length) items.push({ label: t('Henüz şablon yok'), disabled: true });
		items.push({ sep: true }, { icon: 'plus', label: t('Şu anki ayarları kaydet'), onClick: saveTemplate });
		if (tps.length) items.push({ icon: 'trash', label: t('Şablon sil…'), onClick: deleteTemplates });
		S.menu(anchor, items);
	}
	function saveTemplate() {
		var name = h('input.flxst-input', { type: 'text', placeholder: t('Örn: Etkinlik afişi 4:5') });
		var shared = h('input', { type: 'checkbox' });
		var m = S.modal(t('Şablon olarak kaydet'), [
			h('.flxst-field', h('label', t('Ad')), name),
			h('label.flxst-chk', shared, h('span', t('Ekiple paylaş'))),
			h('p.flxst-muted', t('İstem, model, oran, çözünürlük ve referanslar kaydedilir.'))
		], {
			foot: [h('button.flxst-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxst-btn.is-primary', { type: 'button', onclick: function () {
					if (!name.value.trim()) return name.focus();
					var c = st.c, ta = S.$('.flxst-prompt', composer);
					S.api('POST', 'templates', { name: name.value.trim(), shared: shared.checked, prompt: ta ? ta.value : '', params: { kind: c.kind, model: c.model, aspect: c.aspect, resolution: c.resolution, quality: c.quality, n: c.n, duration: c.duration, audio: c.audio, refs: refPayload() } }).then(function (d) {
						st.boot.templates = d.templates;
						m.close();
						S.toast(t('Şablon kaydedildi'));
					}, S.fail);
				} }, t('Kaydet'))]
		});
	}
	function deleteTemplates() {
		var list = h('.flxst-list');
		function draw() {
			S.clear(list);
			st.boot.templates.forEach(function (tp) {
				var mine = tp.owner === st.boot.user.id || st.boot.user.manage;
				list.appendChild(h('.flxst-list-i', S.icon('template', 16), h('span.flxst-grow', tp.name), mine ? S.iconBtn('trash', t('Sil'), function () {
					S.api('POST', 'templates/' + tp.id + '/delete').then(function (d) { st.boot.templates = d.templates; draw(); }, S.fail);
				}) : null));
			});
		}
		draw();
		S.modal(t('Şablonlar'), list);
	}

	/* ---------------- Görüntüleyici ---------------- */
	function viewer(id) {
		var idx = st.items.findIndex(function (x) { return x.id === id; });
		var body = h('.flxst-view');
		var m = S.modal(null, body, { full: true, cls: 'flxst-view-modal', noFocus: true });
		function key(e) {
			if (S.topModal() !== m || /INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName)) return;
			if (e.key === 'ArrowRight') step(1);
			else if (e.key === 'ArrowLeft') step(-1);
		}
		document.addEventListener('keydown', key);
		var close0 = m.close;
		m.close = function () { document.removeEventListener('keydown', key); close0(); };
		function step(d) {
			var j = idx;
			do { j += d; } while (st.items[j] && st.items[j].state !== 'done');
			if (st.items[j]) { idx = j; draw(); }
		}
		function draw() {
			var it = st.items[idx];
			if (!it) return m.close();
			S.clear(body);
			var mod = model(it.model);
			var p = it.params || {};
			var parent = it.parent && st.byId[it.parent];
			var compareMode = body.dataset.cmp === '1' && parent;
			var media = it.kind === 'video'
				? h('video', { src: it.url, controls: true, autoplay: true, loop: true, playsinline: true })
				: compareMode ? S.compare(parent.url, it.url, it.width, it.height) : h('img', { src: it.url, alt: it.title || '' });
			var stage = h('.flxst-view-stage', media,
				S.iconBtn('chevL', t('Önceki'), function () { step(-1); }, 'flxst-view-prev'),
				S.iconBtn('chevR', t('Sonraki'), function () { step(1); }, 'flxst-view-next'),
				S.iconBtn('x', t('Kapat'), function () { m.close(); }, 'flxst-view-close'));

			var refs = (p.refs || []).length ? h('.flxst-view-refs', p.refs.map(function (r) {
				return h('span.flxst-ref.is-sm', { title: r.type === 'person' ? '@' + r.name : roleLabel(r.role || '') }, r.thumb ? h('img', { src: r.thumb, alt: '' }) : h('span.flxst-av', (r.name || '?').charAt(0)));
			})) : null;

			var meta = h('dl.flxst-meta',
				h('dt', t('Model')), h('dd', (mod && mod.label) || it.model),
				it.provider ? [h('dt', t('Sağlayıcı')), h('dd', providerLabel(it.provider))] : null,
				it.width ? [h('dt', t('Boyut')), h('dd', it.width + '×' + it.height + (p.resolution ? ' · ' + p.resolution : ''))] : null,
				p.duration ? [h('dt', t('Süre')), h('dd', p.duration + ' sn')] : null,
				it.cost ? [h('dt', t('Maliyet')), h('dd', S.money(it.cost))] : null,
				h('dt', t('Tarih')), h('dd', S.ago(it.created)),
				it.owner !== st.boot.user.id ? [h('dt', t('Üreten')), h('dd', it.owner_name)] : null);

			var acts = h('.flxst-view-acts',
				S.btn('star', it.favorite ? t('Favoride') : t('Favori'), function () { toggleFav(it); draw(); }, 'is-ghost' + (it.favorite ? ' is-on' : '')),
				S.btn('download', t('İndir'), function () { S.download(it.url, (it.title || 'flx-studio-' + it.id) + (it.kind === 'video' ? '.mp4' : '')); }, 'is-ghost'),
				S.btn('repeat', t('Ayarları kullan'), function () { m.close(); reuse(it); }, 'is-ghost'),
				it.kind === 'image' ? S.btn('layers', t('Referans yap'), function () { addRef({ type: 'item', id: it.id, thumb: it.thumb, name: it.title }); S.toast(t('Referanslara eklendi')); }, 'is-ghost') : null,
				it.kind === 'image' ? S.btn('brush', t('Alanı düzenle'), function () { editArea(it); }, 'is-ghost', mod && mod.mask ? '' : t('Bu model alan düzenlemeyi desteklemiyor')) : null,
				parent && it.kind === 'image' ? S.btn('compare', compareMode ? t('Karşılaştırmayı kapat') : t('Önceki ile karşılaştır'), function () { body.dataset.cmp = compareMode ? '' : '1'; draw(); }, 'is-ghost') : null,
				B.library ? S.btn('library', it.attachment ? t('Kitaplıkta') : t('Medya kitaplığına ekle'), function (e) { toLibrary(it, e.currentTarget); }, 'is-ghost') : null,
				S.btn('move', t('Taşı'), function (e) { folderPicker(e.currentTarget, function (fid) { moveItems([it.id], fid); }); }, 'is-ghost'),
				S.btn('trash', t('Sil'), function () { deleteItems([it.id]).then(function (ok) { if (ok) { st.items[idx] ? draw() : m.close(); } }); }, 'is-ghost is-danger'));

			var title = h('input.flxst-view-title', { type: 'text', value: it.title, placeholder: t('Başlık ekle'), 'aria-label': t('Başlık') });
			title.addEventListener('change', function () {
				S.api('POST', 'items/' + it.id, { title: title.value }).then(function (x) { Object.assign(it, x); redrawCard(it); }, S.fail);
			});

			body.appendChild(stage);
			body.appendChild(h('aside.flxst-view-side',
				title,
				h('.flxst-view-prompt', h('p', it.prompt), S.iconBtn('copy', t('İstemi kopyala'), function () { S.copy(it.prompt); }, 'is-xs')),
				p.draft ? h('details.flxst-draft', h('summary', t('İlk taslak')), h('p', p.draft)) : null,
				refs,
				meta,
				it.error ? h('p.flxst-warn', S.icon('alert', 14), it.error) : null,
				acts));
		}
		draw();
	}
	function toLibrary(it, btn) {
		if (it.attachment) return S.toast(t('Zaten medya kitaplığında'));
		btn.disabled = true;
		S.api('POST', 'items/' + it.id + '/library').then(function (d) {
			it.attachment = d.attachment;
			S.toast(t('Medya kitaplığına eklendi'));
			btn.querySelector('span').textContent = t('Kitaplıkta');
		}, S.fail).then(function () { btn.disabled = false; });
	}
	function editArea(it) {
		var mod = model(it.model);
		var target = mod && mod.mask ? it.model : (models('image').filter(function (x) { return x.mask && x.providers.length; })[0] || {}).id;
		if (!target) return S.toast(t('Alan düzenlemeyi destekleyen ve anahtarı girilmiş model yok.'), 'error');
		S.maskEditor(it, {
			onSubmit: function (mask, prompt, layers) {
				var prev = { kind: st.c.kind, model: st.c.model, n: st.c.n };
				st.c.kind = 'image';
				st.c.model = target;
				st.c.n = 1;
				cfg();
				var p = generate({ prompt: prompt, mask: mask, parent: it.id, layers: layers, n: 1 });
				Object.assign(st.c, prev);
				return p.then(function () {
					S.toast(t('Düzenleme başladı; sonuç galeride görünecek'));
				});
			}
		});
	}

	/* ---------------- Beyin fırtınası ---------------- */
	function brainstorm() {
		var ta = S.$('.flxst-prompt', composer);
		var brief = h('textarea.flxst-input', { rows: 3, placeholder: t('Fikri anlatın: Örn. 11 Kasım fidan dikim etkinliği için Instagram görseli') });
		brief.value = ta ? ta.value : '';
		var max = Math.max(1, Math.min(8, st.boot.settings.max_batch));
		var count = sel(t('Fikir sayısı'), Math.min(4, max), range(2, Math.max(2, max)).map(function (n) { return [n, t('{n} fikir', { n: n })]; }), function () {});
		var style = sel(t('Tarz'), 'simple', [['simple', t('Genel')], ['youtube', t('YouTube küçük resmi')]], function () {});
		var out = h('.flxst-ideas');
		var go = h('button.flxst-btn.is-primary', { type: 'button' }, S.icon('bulb', 16), h('span', t('Fikir üret')));
		var run = h('button.flxst-btn.is-primary', { type: 'button', hidden: true });
		var ideas = [];
		var batch = '';
		go.addEventListener('click', function () {
			if (!brief.value.trim()) return brief.focus();
			go.disabled = true;
			go.classList.add('is-busy');
			S.clear(out).appendChild(h('.flxst-pend', h('.flxst-spin'), h('span', t('Fikirler hazırlanıyor…'))));
			S.api('POST', 'brainstorm', { brief: brief.value, count: +count.value, style: style.value, refs: refPayload(), mode: st.c.kind }).then(function (d) {
				batch = d.batch;
				ideas = d.ideas.map(function (x) { return { title: x.title, prompt: x.prompt, on: true }; });
				drawIdeas();
			}, function (e) { S.clear(out); S.fail(e); }).then(function () { go.disabled = false; go.classList.remove('is-busy'); });
		});
		function drawIdeas() {
			S.clear(out);
			ideas.forEach(function (idea) {
				var p = h('textarea.flxst-input', { rows: 4 });
				p.value = idea.prompt;
				p.addEventListener('input', function () { idea.prompt = p.value; });
				var tt = h('input.flxst-input.is-title', { type: 'text', value: idea.title });
				tt.addEventListener('input', function () { idea.title = tt.value; });
				out.appendChild(h('.flxst-idea' + (idea.on ? '.is-on' : ''),
					h('label.flxst-chk', h('input', { type: 'checkbox', checked: idea.on, onchange: function (e) { idea.on = e.target.checked; e.target.closest('.flxst-idea').classList.toggle('is-on', idea.on); upd(); } }), tt),
					p));
			});
			upd();
		}
		function upd() {
			var n = ideas.filter(function (x) { return x.on; }).length;
			var m0 = model(st.c.model) || {};
			var price = m0.price && m0.price[st.c.resolution];
			run.hidden = !ideas.length;
			run.disabled = !n;
			S.clear(run).appendChild(h('span', t('Seçilenleri üret ({n})', { n: n })));
			if (price) run.appendChild(h('small', '~' + S.money(price * n * (st.c.kind === 'video' ? st.c.duration || 5 : 1))));
		}
		run.addEventListener('click', function () {
			var todo = ideas.filter(function (x) { return x.on && x.prompt.trim(); });
			run.disabled = true;
			var i = 0, ok = 0;
			(function next() {
				if (i >= todo.length) {
					m.close();
					if (ok) S.toast(t('{n} üretim başladı', { n: ok }));
					return;
				}
				var idea = todo[i++];
				run.firstChild.textContent = t('Gönderiliyor {i}/{n}', { i: i, n: todo.length });
				generate({ prompt: idea.prompt, title: idea.title, idea: idea.title, batch: batch, n: 1 }).then(function () { ok++; next(); }, function () { next(); });
			})();
		});
		var m = S.modal(t('Beyin fırtınası'), [
			h('p.flxst-muted', t('Bir fikirden birbirinden farklı istemler üretir. Beğendiklerinizi seçip tek seferde üretin. Model ve oran üretim çubuğundaki ayarlardan alınır.')),
			h('.flxst-field', h('label', t('Fikir')), brief),
			h('.flxst-row', count, style, h('span.flxst-grow'), go),
			out
		], { wide: true, foot: [h('button.flxst-btn', { type: 'button', onclick: function () { m.close(); } }, t('Kapat')), run] });
	}

	/* ---------------- Kişiler ---------------- */
	function peopleModal(addNew) {
		var list = h('.flxst-plist');
		function reloadBoot() {
			return S.api('GET', 'boot').then(function (d) { st.boot.people = d.people; st.boot.refs = d.refs; drawSide(); draw(); });
		}
		function draw() {
			S.clear(list);
			if (!st.boot.people.length) list.appendChild(h('p.flxst-muted', t('Henüz kişi yok. Kişi ekleyip 1-4 net yüz fotoğrafı yükleyin; istemde @ad yazınca bu fotoğraflar kimlik referansı olarak gider.')));
			st.boot.people.forEach(function (p) {
				var mine = p.owner === st.boot.user.id || st.boot.user.manage;
				var photos = h('.flxst-pphotos');
				p.photos.forEach(function (ph) {
					photos.appendChild(h('.flxst-pphoto', h('img', { src: ph.thumb, alt: '' }), mine ? h('button.flxst-ref-x', { type: 'button', title: t('Fotoğrafı sil'), onclick: function () {
						S.api('POST', 'refs/' + ph.id + '/delete').then(reloadBoot, S.fail);
					} }, S.icon('x', 12)) : null));
				});
				if (mine && p.photos.length < 8) {
					photos.appendChild(h('button.flxst-ref-add', { type: 'button', title: t('Fotoğraf ekle'), onclick: function () {
						var inp = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', multiple: true });
						inp.addEventListener('change', function () {
							var files = Array.prototype.slice.call(inp.files);
							Promise.all(files.map(function (f) {
								var fd = new FormData();
								fd.append('file', f, f.name);
								fd.append('person', p.id);
								fd.append('role', 'identity');
								fd.append('name', p.name);
								return S.api('POST', 'refs', fd);
							})).then(reloadBoot, function (e) { S.fail(e); reloadBoot(); });
						});
						inp.click();
					} }, S.icon('plus', 18)));
				}
				list.appendChild(h('.flxst-pcard',
					h('.flxst-pcard-h', h('strong', p.name), h('small.flxst-muted', t('{n} foto', { n: p.photos.length })), h('span.flxst-grow'),
						mine ? S.iconBtn('edit', t('Yeniden adlandır'), function () {
							S.prompt(t('Kişi adı'), p.name).then(function (n) { if (n) S.api('POST', 'people/' + p.id, { name: n }).then(reloadBoot, S.fail); });
						}, 'is-xs') : null,
						mine ? S.iconBtn('trash', t('Kişiyi sil'), function () {
							S.confirm(t('{name} silinsin mi?', { name: p.name }), t('Kişi ve fotoğrafları silinir. Önceki üretimler kalır.'), t('Sil'), true).then(function (ok) {
								if (ok) S.api('POST', 'people/' + p.id + '/delete').then(reloadBoot, S.fail);
							});
						}, 'is-xs') : null),
					photos));
			});
		}
		function add() {
			S.prompt(t('Yeni kişi'), '', t('Ekle')).then(function (n) {
				if (!n) return;
				S.api('POST', 'people', { name: n }).then(function (p) { st.boot.people.push(p); drawSide(); draw(); }, S.fail);
			});
		}
		draw();
		var m = S.modal(t('Kişiler'), list, { wide: true, foot: [h('button.flxst-btn', { type: 'button', onclick: function () { m.close(); } }, t('Kapat')), h('button.flxst-btn.is-primary', { type: 'button', onclick: add }, S.icon('plus', 16), h('span', t('Kişi ekle')))] });
		if (addNew) add();
	}

	/* ---------------- Ayarlar (yönetici) ---------------- */
	function settingsModal() {
		var s = st.boot.settings;
		var im = sel(t('Varsayılan görsel modeli'), s.image_model, models('image').map(function (m) { return [m.id, m.label]; }), function () {});
		var vm = sel(t('Varsayılan video modeli'), s.video_model, models('video').map(function (m) { return [m.id, m.label]; }), function () {});
		var tm = h('input.flxst-input', { type: 'text', placeholder: t('Boş: Flux AI varsayılanı'), value: s.text_model || '' });
		var mn = h('input.flxst-input', { type: 'number', min: 1, max: 8, value: s.max_n });
		var mb = h('input.flxst-input', { type: 'number', min: 1, max: 40, value: s.max_batch });
		var sp = h('input', { type: 'checkbox', checked: s.shared_people !== false });
		var m = S.modal(t('Stüdyo ayarları'), [
			h('.flxst-field', h('label', t('Varsayılan görsel modeli')), im),
			h('.flxst-field', h('label', t('Varsayılan video modeli')), vm),
			h('.flxst-field', h('label', t('Geliştir / Beyin fırtınası metin modeli')), tm),
			h('.flxst-row', h('.flxst-field', h('label', t('Bir seferde en çok görsel')), mn), h('.flxst-field', h('label', t('Beyin fırtınasında en çok fikir')), mb)),
			h('label.flxst-chk', sp, h('span', t('Kişiler tüm Stüdyo kullanıcılarıyla paylaşılsın'))),
			h('p.flxst-muted', t('Anahtarlar, sağlayıcılar ve harcama sınırı Flux AI & MCP › Ayarlar’dadır.'), ' ', st.boot.ai.setup ? h('a', { href: st.boot.ai.setup }, t('Flux AI ayarları')) : null)
		], {
			foot: [h('button.flxst-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxst-btn.is-primary', { type: 'button', onclick: function () {
					S.api('POST', 'settings', { image_model: im.value, video_model: vm.value, text_model: tm.value.trim(), max_n: +mn.value, max_batch: +mb.value, shared_people: sp.checked }).then(function (d) {
						Object.assign(st.boot.settings, { image_model: d.image_model || s.image_model, video_model: d.video_model || s.video_model, max_n: d.max_n, max_batch: d.max_batch, text_model: d.text_model, shared_people: !!d.shared_people });
						m.close();
						drawComposer();
						S.toast(t('Ayarlar kaydedildi'));
					}, S.fail);
				} }, t('Kaydet'))]
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
