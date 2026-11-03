/* Flux Social — uygulama kabuğu: kenar menü, yönlendirme, gönderiler, takvim, hesaplar, arka plan tetik. */
(function () {
	'use strict';
	var S = window.FLXSM;
	var h = S.h, t = S.t, B = S.B;

	var STATUS = {
		draft: t('Taslak'),
		pending: t('Onay bekliyor'),
		scheduled: t('Zamanlandı'),
		publishing: t('Yayınlanıyor'),
		published: t('Yayınlandı'),
		partial: t('Kısmen yayınlandı'),
		failed: t('Başarısız'),
		cancelled: t('İptal'),
		processing: t('Gönderiliyor'),
		deleted: t('Ağdan silindi')
	};
	S.STATUS = STATUS;
	S.badge = function (st) { return h('span.flxsm-badge.is-' + st, STATUS[st] || st); };

	var st = (S.st = { boot: null, route: '', q: {} });
	var root, side, main, topTitle, topActs, view;

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
		root = document.getElementById('flxsm-app');
		if (!root) return;
		S.root = root;
		applyTheme();
		if (window.matchMedia) {
			var mq = window.matchMedia('(prefers-color-scheme: dark)');
			(mq.addEventListener ? mq.addEventListener.bind(mq, 'change') : mq.addListener.bind(mq))(applyTheme);
		}
		root.appendChild(h('.flxsm-boot', h('.flxsm-spin'), h('span', t('Sosyal Medya açılıyor…'))));
		S.api('GET', 'boot').then(function (d) {
			setBoot(d);
			S.clear(root);
			build();
			window.addEventListener('hashchange', route);
			route();
			heartbeat();
		}, function (e) {
			S.clear(root).appendChild(h('.flxsm-empty', S.icon('alert', 28), h('p', e.message), h('button.flxsm-btn', { type: 'button', onclick: function () { location.reload(); } }, t('Yeniden dene'))));
		});
	}
	function setBoot(d) {
		st.boot = d;
		S.setTz(d.tz_offset);
		S.accounts = d.accounts;
	}
	S.reloadBoot = function () {
		return S.api('GET', 'boot').then(function (d) { setBoot(d); drawSide(); return d; });
	};
	S.account = function (id) {
		for (var i = 0; i < st.boot.accounts.length; i++) if (st.boot.accounts[i].id === +id) return st.boot.accounts[i];
		return null;
	};

	/* ---------------- İskelet ---------------- */
	function build() {
		side = h('aside.flxsm-side');
		topTitle = h('h1.flxsm-title');
		topActs = h('.flxsm-top-acts');
		view = h('.flxsm-view');
		main = h('main.flxsm-main',
			h('header.flxsm-top', S.iconBtn('menu', t('Menü'), function () { root.classList.toggle('is-nav'); }, 'flxsm-only-m'), topTitle, topActs),
			view
		);
		root.appendChild(side);
		root.appendChild(main);
		root.appendChild(h('.flxsm-scrim', { onclick: function () { root.classList.remove('is-nav'); } }));
		drawSide();
	}

	function navItem(icon, label, key, count, cls) {
		var on = st.route === key;
		return h('a.flxsm-nav' + (on ? '.is-on' : '') + (cls ? '.' + cls : ''), { href: '#/' + key, onclick: function () { root.classList.remove('is-nav'); } },
			S.icon(icon, 17), h('span.flxsm-nav-l', label), count ? h('span.flxsm-count', String(count)) : null);
	}
	function drawSide() {
		if (!side) return;
		var b = st.boot, c = b.counts || {};
		S.clear(side);
		side.appendChild(h('.flxsm-brand', ((window.FLXSM_BOOT || {}).logo ? h('img.flxsm-logo.is-img', { src: window.FLXSM_BOOT.logo, alt: '' }) : h('span.flxsm-logo', S.icon('share', 17))), h('strong', 'Flux Social'), h('span.flxsm-ver', 'v' + b.version)));
		side.appendChild(h('button.flxsm-btn.is-primary.flxsm-new', { type: 'button', onclick: function () { root.classList.remove('is-nav'); S.compose(null); } }, S.icon('plus', 16), h('span', t('Yeni gönderi'))));
		side.appendChild(h('nav.flxsm-navs',
			navItem('chart', t('Pano'), 'dashboard'),
			navItem('list', t('Gönderiler'), 'posts'),
			b.user.publish ? navItem('check', t('Onay bekleyenler'), 'approvals', c.pending) : null,
			navItem('calendar', t('Takvim'), 'calendar'),
			navItem('users', t('Hesaplar'), 'accounts', b.accounts.filter(function (a) { return a.status === 'expired'; }).length || null, 'is-warn'),
			b.user.manage ? navItem('settings', t('Ayarlar'), 'settings') : null
		));
		var accs = b.accounts.filter(function (a) { return a.status !== 'disabled'; });
		if (accs.length) {
			side.appendChild(h('.flxsm-side-h', h('span', t('Hesaplar'))));
			var list = h('.flxsm-side-accs');
			accs.forEach(function (a) {
				list.appendChild(h('a.flxsm-side-acc' + (a.status === 'expired' ? '.is-bad' : ''), { href: '#/posts?account=' + a.id, title: a.network + ' › ' + a.name, onclick: function () { root.classList.remove('is-nav'); } }, S.avatar(a, 24), h('span', a.name), a.status === 'expired' ? S.icon('alert', 14) : null));
			});
			side.appendChild(list);
		}
		side.appendChild(h('.flxsm-side-foot',
			c.failed ? h('a.flxsm-side-alert', { href: '#/posts?status=failed,partial' }, S.icon('alert', 15), h('span', t('{n} gönderide hata', { n: c.failed }))) : null,
			h('.flxsm-row', S.iconBtn('sun', t('Tema'), cycleTheme), h('a.flxsm-ib', { href: B.adminUrl, title: t('Panele dön') }, S.icon('back', 18)))
		));
	}
	S.drawSide = drawSide;

	S.setTop = function (title, acts) {
		topTitle.textContent = title;
		S.clear(topActs);
		(acts || []).forEach(function (a) { if (a) topActs.appendChild(a); });
		document.title = title + ' ‹ ' + t('Sosyal Medya');
	};

	/* ---------------- Yönlendirme ---------------- */
	function parseHash() {
		var raw = (location.hash || '').replace(/^#\/?/, '');
		var qi = raw.indexOf('?');
		var path = qi < 0 ? raw : raw.slice(0, qi);
		var q = {};
		if (qi >= 0) raw.slice(qi + 1).split('&').forEach(function (kv) {
			if (!kv) return;
			var p = kv.split('=');
			q[decodeURIComponent(p[0])] = decodeURIComponent((p[1] || '').replace(/\+/g, ' '));
		});
		return { path: path || 'dashboard', q: q };
	}
	S.go = function (path) { location.hash = '#/' + path; };
	function route() {
		var r = parseHash();
		var key = r.path.split('/')[0];
		if (key === 'settings' && !st.boot.user.manage) key = 'dashboard';
		if (key === 'approvals' && !st.boot.user.publish) key = 'posts';
		st.route = key;
		st.q = r.q;
		drawSide();
		S.closePop();
		S.dashHideTip && S.dashHideTip();
		S.clear(view);
		view.scrollTop = 0;
		view.className = 'flxsm-view is-' + key;
		if (key === 'dashboard') S.dashView(view);
		else if (key === 'posts') postsView(view, r.q);
		else if (key === 'approvals') postsView(view, { status: 'pending', _approvals: 1 });
		else if (key === 'calendar') calendarView(view, r.q);
		else if (key === 'accounts') accountsView(view, r.q);
		else if (key === 'settings') S.settingsView(view, r.q);
		else if (key === 'compose') { S.go('posts'); S.compose(+r.path.split('/')[1] || null); }
		else S.dashView(view);
	}
	S.rerender = route;

	/* ---------------- Arka plan: sayılar + panel tetiği ---------------- */
	function heartbeat() {
		var busy = false;
		var tick = function () {
			if (document.hidden || busy) return;
			busy = true;
			S.api('GET', 'counts').then(function (c) {
				var changed = JSON.stringify(c) !== JSON.stringify(st.boot.counts);
				st.boot.counts = c;
				if (changed) drawSide();
				if (c.due > 0) {
					return S.api('POST', 'tick').then(function (r) {
						if (r.processed && S.onTick) S.onTick();
					});
				}
			}).catch(function () { /* sessiz */ }).then(function () { busy = false; });
		};
		setInterval(tick, 45000);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
		setTimeout(tick, 4000);
	}
	/* Gönderi işlemi sonrası: kuyruğu hemen dürt (sunucu ayrıca kendine istek atar). */
	S.nudge = function () {
		setTimeout(function () {
			S.api('POST', 'tick').then(function () { S.onTick && S.onTick(); }).catch(function () {});
		}, 1500);
	};

	/* ---------------- Gönderi satırı (liste + takvim + pano ortak) ---------------- */
	S.postText = function (p) {
		var s = p.title || p.content || '';
		if (!s && p.targets) for (var i = 0; i < p.targets.length; i++) if (p.targets[i].content) { s = p.targets[i].content; break; }
		return s || t('(metinsiz)');
	};
	S.postTime = function (p) {
		if (p.published_at) return p.published_at;
		if (p.scheduled_at) return p.scheduled_at;
		return p.created;
	};
	S.targetsStrip = function (p, size) {
		var box = h('.flxsm-tstrip');
		(p.targets || []).forEach(function (tg) {
			var a = S.account(tg.account_id) || { name: tg.account, provider: tg.provider, avatar: tg.avatar };
			box.appendChild(h('span.flxsm-tchip.is-' + tg.status, { title: a.name + ' — ' + (STATUS[tg.status] || tg.status) + (tg.error ? '\n' + tg.error : '') }, S.avatar(a, size || 24), h('i.flxsm-dot')));
		});
		return box;
	};
	S.postMenu = function (anchor, p, after) {
		var u = st.boot.user;
		var open = ['draft', 'pending', 'scheduled', 'failed', 'cancelled', 'partial'].indexOf(p.status) >= 0;
		var mine = p.user_id === u.id;
		var canEdit = u.publish || (mine && ['draft', 'pending', 'failed', 'cancelled'].indexOf(p.status) >= 0);
		var act = function (a, ok) {
			return function () {
				S.api('POST', 'posts/' + p.id + '/action', { action: a }).then(function () { S.toast(ok); if (a === 'now' || a === 'approve') S.nudge(); after && after(); }, S.fail);
			};
		};
		S.menu(anchor, [
			{ icon: 'eye', label: t('Ayrıntılar'), onClick: function () { S.postDetail(p.id, after); } },
			canEdit && open ? { icon: 'edit', label: t('Düzenle'), onClick: function () { S.compose(p.id, { onSaved: after }); } } : null,
			{ icon: 'copy', label: t('Kopyasını oluştur'), onClick: function () { S.api('POST', 'posts/' + p.id + '/duplicate').then(function (np) { S.toast(t('Kopya taslak olarak oluşturuldu.')); S.compose(np.id, { onSaved: after }); }, S.fail); } },
			{ sep: true },
			u.publish && p.status === 'pending' ? { icon: 'check', label: t('Onayla'), onClick: act('approve', t('Onaylandı.')) } : null,
			u.publish && p.status === 'pending' ? { icon: 'x', label: t('Reddet'), onClick: function () { S.reject(p, after); } } : null,
			u.publish && open && p.status !== 'pending' ? { icon: 'send', label: t('Şimdi paylaş'), onClick: function () {
				S.confirm(t('Şimdi paylaşılsın mı?'), t('Gönderi seçili hesaplarda hemen yayınlanır.'), t('Paylaş')).then(function (ok) { if (ok) act('now', t('Sıraya alındı, birazdan yayında.'))(); });
			} } : null,
			!u.publish && mine && p.status === 'draft' ? { icon: 'send', label: t('Onaya gönder'), onClick: act('submit', t('Onaya gönderildi.')) } : null,
			canEdit && p.status === 'scheduled' ? { icon: 'pause', label: t('Zamanlamayı kaldır (taslağa al)'), onClick: act('draft', t('Taslağa alındı.')) } : null,
			{ sep: true },
			canEdit ? { icon: 'trash', label: t('Sil'), danger: true, onClick: function () { S.deletePost(p, after); } } : null
		].filter(function (x, i, arr) { return x && !(x.sep && (!arr[i - 1] || arr[i - 1].sep)); }), { alignRight: true });
	};
	S.reject = function (p, after) {
		var note = h('textarea.flxsm-input', { rows: 3, placeholder: t('Neden? (yazana görünür)') });
		var m = S.modal(t('Gönderiyi reddet'), [h('p.flxsm-muted', t('Gönderi taslağa döner; yazan kişi notu görür.')), note], {
			foot: [
				h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxsm-btn.is-primary.is-danger', { type: 'button', onclick: function () {
					S.api('POST', 'posts/' + p.id + '/action', { action: 'reject', note: note.value }).then(function () { m.close(); S.toast(t('Reddedildi.')); after && after(); }, S.fail);
				} }, t('Reddet'))
			]
		});
	};
	S.deletePost = function (p, after) {
		var live = (p.targets || []).filter(function (x) { return x.status === 'published' && x.caps && x.caps.delete; });
		var remote = h('input', { type: 'checkbox' });
		var m = S.modal(t('Gönderiyi sil'), [
			h('p', t('Gönderi listeden silinir.')),
			live.length ? h('label.flxsm-chk', remote, h('span', t('Yayınlandığı ağlardan da sil ({n} hesap)', { n: live.length }))) : null,
			(p.targets || []).some(function (x) { return x.status === 'published' && !(x.caps && x.caps.delete); }) ? h('p.flxsm-muted.is-small', t('Instagram ve TikTok gönderileri API ile silinemez; oradan elle silin.')) : null
		], {
			foot: [
				h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxsm-btn.is-primary.is-danger', { type: 'button', onclick: function () {
					S.api('POST', 'posts/' + p.id + '/delete', { remote: remote.checked }).then(function () { m.close(); S.toast(t('Silindi.')); after && after(); S.closeDetail && S.closeDetail(); }, S.fail);
				} }, t('Sil'))
			]
		});
	};

	/* ---------------- Gönderiler ---------------- */
	var TABS = [
		['', t('Tümü')],
		['draft', t('Taslaklar')],
		['pending', t('Onay bekliyor')],
		['scheduled,publishing', t('Zamanlanmış')],
		['published,partial', t('Yayınlanan')],
		['failed,partial', t('Hatalı')]
	];
	function postsView(el, q) {
		var f = { status: q.status || '', account: +q.account || 0, provider: q.provider || '', q: q.q || '', mine: q.mine ? 1 : 0, page: 1 };
		var approvals = !!q._approvals;
		var list = h('.flxsm-plist');
		var more = h('button.flxsm-btn.flxsm-more', { type: 'button', hidden: true, onclick: function () { f.page++; load(false); } }, t('Daha fazla'));
		var sel = {};
		var bulk = h('.flxsm-bulk', { hidden: true });
		var search = h('input.flxsm-search', { type: 'search', placeholder: t('Metin, başlık ya da etiket ara'), value: f.q, 'aria-label': t('Ara') });
		search.addEventListener('input', S.debounce(function () { f.q = search.value.trim(); f.page = 1; load(true); }, 350));
		var accSel = h('select.flxsm-sel', { 'aria-label': t('Hesap') }, h('option', { value: '' }, t('Tüm hesaplar')));
		st.boot.accounts.forEach(function (a) { accSel.appendChild(h('option', { value: a.id, selected: a.id === f.account }, a.network + ' › ' + a.name)); });
		accSel.addEventListener('change', function () { f.account = +accSel.value; f.page = 1; load(true); });
		var mine = h('label.flxsm-chk', h('input', { type: 'checkbox', checked: !!f.mine, onchange: function (e) { f.mine = e.target.checked ? 1 : 0; f.page = 1; load(true); } }), h('span', t('Yalnız benimkiler')));

		S.setTop(approvals ? t('Onay bekleyenler') : t('Gönderiler'), [h('.flxsm-searchbox', S.icon('search', 16), search)]);
		if (!approvals) {
			var tabs = h('.flxsm-tabs');
			TABS.forEach(function (tb) {
				tabs.appendChild(h('button', { type: 'button', class: 'flxsm-tab' + (f.status === tb[0] ? ' is-on' : ''), onclick: function () {
					f.status = tb[0]; f.page = 1;
					Array.prototype.forEach.call(tabs.children, function (c) { c.classList.toggle('is-on', c === this); }, this);
					load(true);
				} }, tb[1]));
			});
			el.appendChild(h('.flxsm-toolbar', tabs, h('.flxsm-row', accSel, mine)));
		} else {
			el.appendChild(h('p.flxsm-muted.flxsm-lead', t('Yayın yetkisi olmayan kişilerin hazırladığı gönderiler. Onaylayınca zamanı geldiğinde (ya da hemen) paylaşılır.')));
		}
		el.appendChild(bulk);
		el.appendChild(list);
		el.appendChild(more);

		function drawBulk() {
			var ids = Object.keys(sel).filter(function (k) { return sel[k]; }).map(Number);
			bulk.hidden = !ids.length;
			S.clear(bulk);
			if (!ids.length) return;
			var run = function (a, ok) {
				return function () {
					S.api('POST', 'posts/bulk', { action: a, ids: ids }).then(function (r) {
						S.toast(ok + ' (' + r.done + ')');
						if (r.errors && r.errors.length) S.toast(r.errors.join('\n'), 'error');
						if (a === 'now' || a === 'approve') S.nudge();
						sel = {}; load(true);
					}, S.fail);
				};
			};
			bulk.appendChild(h('span', t('{n} seçili', { n: ids.length })));
			if (st.boot.user.publish) {
				if (approvals) bulk.appendChild(S.btn('check', t('Onayla'), run('approve', t('Onaylandı.')), 'is-primary'));
				bulk.appendChild(S.btn('clock', t('Zamanla'), run('schedule', t('Zamanlandı.'))));
				bulk.appendChild(S.btn('pause', t('Taslağa al'), run('draft', t('Taslağa alındı.'))));
			}
			bulk.appendChild(S.btn('trash', t('Sil'), function () {
				S.confirm(t('Seçilenler silinsin mi?'), t('Yalnız listeden silinir; ağlardaki gönderilere dokunulmaz.'), t('Sil'), true).then(function (ok) { if (ok) run('delete', t('Silindi.'))(); });
			}, 'is-danger'));
			bulk.appendChild(h('button.flxsm-link', { type: 'button', onclick: function () { sel = {}; Array.prototype.forEach.call(list.querySelectorAll('input[type=checkbox]'), function (c) { c.checked = false; }); drawBulk(); } }, t('Seçimi kaldır')));
		}

		function row(p) {
			var thumb = p.media && p.media[0] ? h('.flxsm-pthumb', h('img', { src: p.media[0].thumb || p.media[0].url, alt: '', loading: 'lazy' }), p.media[0].kind === 'video' ? h('span.flxsm-pplay', S.icon('play', 12)) : null) : h('.flxsm-pthumb.is-text', S.icon('msg', 18));
			var ck = h('input', { type: 'checkbox', 'aria-label': t('Seç'), onclick: function (e) { e.stopPropagation(); sel[p.id] = e.target.checked; drawBulk(); } });
			var tm = S.postTime(p);
			return h('.flxsm-prow', { onclick: function () { S.postDetail(p.id, function () { load(true); }); } },
				h('label.flxsm-pck', { onclick: function (e) { e.stopPropagation(); } }, ck),
				thumb,
				h('.flxsm-pbody',
					h('.flxsm-ptext', S.postText(p)),
					h('.flxsm-pmeta', S.badge(p.status), tm ? h('span', S.icon('clock', 13), S.fmt(tm)) : null, p.user ? h('span', p.user) : null, p.repeat_days ? h('span', S.icon('repeat', 13), t('{n} günde bir', { n: p.repeat_days })) : null, p.note && p.status === 'draft' ? h('span.flxsm-pnote', S.icon('msg', 13), p.note) : null)
				),
				S.targetsStrip(p),
				S.iconBtn('dots', t('İşlemler'), function (e) { e.stopPropagation(); S.postMenu(e.currentTarget, p, function () { load(true); }); })
			);
		}

		function load(reset) {
			if (reset) { f.page = 1; S.clear(list).appendChild(h('.flxsm-boot', h('.flxsm-spin'))); }
			var qs = ['page=' + f.page, 'per=30'];
			['status', 'account', 'provider', 'q', 'mine'].forEach(function (k) { if (f[k]) qs.push(k + '=' + encodeURIComponent(f[k])); });
			S.api('GET', 'posts?' + qs.join('&')).then(function (r) {
				if (reset) S.clear(list);
				if (!r.items.length && reset) {
					list.appendChild(h('.flxsm-empty', S.icon(approvals ? 'check' : 'inbox', 28), h('h3', approvals ? t('Onay bekleyen gönderi yok') : t('Gönderi yok')),
						approvals ? null : h('p', t('İlk gönderiyi oluşturun; birden çok hesaba aynı anda, her birine ayrı metinle paylaşabilirsiniz.')),
						approvals ? null : S.btn('plus', t('Yeni gönderi'), function () { S.compose(null, { onSaved: function () { load(true); } }); }, 'is-primary')));
				}
				r.items.forEach(function (p) { list.appendChild(row(p)); });
				more.hidden = r.page * r.per >= r.total;
			}, function (e) { S.clear(list).appendChild(h('.flxsm-empty', S.icon('alert', 24), h('p', e.message))); });
		}
		S.onTick = function () { load(true); };
		load(true);
	}

	/* ---------------- Takvim ---------------- */
	function calendarView(el, q) {
		var now = Math.floor(Date.now() / 1000);
		var p0 = S.parts(now);
		var cur = { y: p0.y, m: p0.m };
		var mode = S.pref('calmode') || (window.innerWidth < 700 ? 'list' : 'month');
		var accF = 0;
		var grid = h('.flxsm-cal');
		var title = h('strong.flxsm-cal-t');
		var accSel = h('select.flxsm-sel', { 'aria-label': t('Hesap') }, h('option', { value: '' }, t('Tüm hesaplar')));
		st.boot.accounts.forEach(function (a) { accSel.appendChild(h('option', { value: a.id }, a.network + ' › ' + a.name)); });
		accSel.addEventListener('change', function () { accF = +accSel.value; load(); });
		var seg = h('.flxsm-seg.is-small');
		[['month', t('Ay')], ['list', t('Liste')]].forEach(function (o) {
			seg.appendChild(h('button', { type: 'button', class: 'flxsm-seg-b' + (mode === o[0] ? ' is-on' : ''), onclick: function () {
				mode = o[0]; S.pref('calmode', mode);
				Array.prototype.forEach.call(seg.children, function (c, i) { c.classList.toggle('is-on', i === (mode === 'month' ? 0 : 1)); });
				load();
			} }, o[1]));
		});
		S.setTop(t('Takvim'), [seg]);
		el.appendChild(h('.flxsm-toolbar',
			h('.flxsm-row', S.iconBtn('chevL', t('Önceki ay'), function () { shift(-1); }), title, S.iconBtn('chevR', t('Sonraki ay'), function () { shift(1); }), h('button.flxsm-btn.is-ghost', { type: 'button', onclick: function () { cur = { y: p0.y, m: p0.m }; load(); } }, t('Bugün'))),
			h('.flxsm-row', accSel, h('span.flxsm-muted.is-small', t('Saat dilimi: {tz}', { tz: st.boot.tz })))
		));
		el.appendChild(grid);
		function shift(d) {
			cur.m += d;
			if (cur.m < 0) { cur.m = 11; cur.y--; }
			if (cur.m > 11) { cur.m = 0; cur.y++; }
			load();
		}
		function load() {
			title.textContent = new Date(Date.UTC(cur.y, cur.m, 1)).toLocaleDateString(S.locale, { timeZone: 'UTC', month: 'long', year: 'numeric' });
			var wk = st.boot.week;
			var first = S.mk(cur.y, cur.m, 1);
			var lead = (S.parts(first).w - wk + 7) % 7;
			var from = first - lead * 86400;
			var days = Math.ceil((lead + new Date(Date.UTC(cur.y, cur.m + 1, 0)).getUTCDate()) / 7) * 7;
			var to = from + days * 86400 + 3600;
			S.clear(grid).appendChild(h('.flxsm-boot', h('.flxsm-spin')));
			S.api('GET', 'posts?per=200&asc=1&from=' + from + '&to=' + to + (accF ? '&account=' + accF : '')).then(function (r) {
				S.clear(grid);
				if (mode === 'list') return drawList(r.items);
				drawMonth(from, days, r.items);
			}, function (e) { S.clear(grid).appendChild(h('p.flxsm-err', e.message)); });
		}
		function chip(p) {
			var tm = S.postTime(p);
			var nets = {};
			(p.targets || []).forEach(function (x) { nets[x.provider] = 1; });
			var draggable = st.boot.user.publish && ['draft', 'scheduled', 'failed', 'cancelled'].indexOf(p.status) >= 0;
			var c = h('button.flxsm-cchip.is-' + p.status, { type: 'button', draggable: draggable ? 'true' : null, title: S.postText(p), onclick: function (e) { e.stopPropagation(); S.postDetail(p.id, load); } },
				h('span.flxsm-cnets', Object.keys(nets).slice(0, 4).map(function (n) { return S.net(n, 13); })),
				h('span.flxsm-ctime', S.fmtTime(tm)),
				h('span.flxsm-ctext', S.postText(p)));
			if (draggable) c.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', JSON.stringify({ id: p.id, tm: tm, status: p.status })); e.dataTransfer.effectAllowed = 'move'; });
			return c;
		}
		function drawMonth(from, days, items) {
			var head = h('.flxsm-cal-head');
			for (var i = 0; i < 7; i++) head.appendChild(h('span', new Date(Date.UTC(2024, 0, 7 + ((st.boot.week + i) % 7))).toLocaleDateString(S.locale, { timeZone: 'UTC', weekday: 'short' })));
			grid.appendChild(head);
			var body = h('.flxsm-cal-grid');
			var by = {};
			items.forEach(function (p) {
				var d = S.dayStart(S.postTime(p));
				(by[d] = by[d] || []).push(p);
			});
			var today = S.dayStart(now);
			for (var k = 0; k < days; k++) {
				(function (ds) {
					ds = S.dayStart(ds + 7200);
					var pp = S.parts(ds);
					var cell = h('.flxsm-cday' + (pp.m !== cur.m ? '.is-out' : '') + (ds === today ? '.is-today' : '') + (ds < today ? '.is-past' : ''),
						h('.flxsm-cday-h', h('span', String(pp.d)), ds >= today ? S.iconBtn('plus', t('Bu güne gönderi'), function () { S.compose(null, { at: ds + 10 * 3600, onSaved: load }); }, 'is-xs') : null));
					var list = (by[ds] || []);
					list.slice(0, 4).forEach(function (p) { cell.appendChild(chip(p)); });
					if (list.length > 4) cell.appendChild(h('button.flxsm-link', { type: 'button', onclick: function () { dayModal(ds, list); } }, t('+{n} daha', { n: list.length - 4 })));
					cell.addEventListener('dragover', function (e) { if (ds >= today) { e.preventDefault(); cell.classList.add('is-drop'); } });
					cell.addEventListener('dragleave', function () { cell.classList.remove('is-drop'); });
					cell.addEventListener('drop', function (e) {
						e.preventDefault();
						cell.classList.remove('is-drop');
						var d;
						try { d = JSON.parse(e.dataTransfer.getData('text/plain')); } catch (x) { return; }
						var tp = S.parts(d.tm);
						var at = ds + tp.h * 3600 + tp.i * 60;
						if (at < now + 60) at = now + 300;
						S.api('POST', 'posts/' + d.id, { scheduled_at: at, action: d.status === 'scheduled' ? 'schedule' : '' }).then(function () { S.toast(t('Yeni zaman: {t}', { t: S.fmt(at) })); load(); }, S.fail);
					});
					body.appendChild(cell);
				})(from + k * 86400);
			}
			grid.appendChild(body);
		}
		function dayModal(ds, list) {
			S.modal(S.fmtDay(ds, { weekday: 'long', year: 'numeric' }), h('.flxsm-cday-list', list.map(chip)));
		}
		function drawList(items) {
			if (!items.length) {
				grid.appendChild(h('.flxsm-empty', S.icon('calendar', 28), h('p', t('Bu ay gönderi yok.')), S.btn('plus', t('Yeni gönderi'), function () { S.compose(null, { onSaved: load }); }, 'is-primary')));
				return;
			}
			var last = null;
			var box = h('.flxsm-agenda');
			items.forEach(function (p) {
				var ds = S.dayStart(S.postTime(p));
				if (ds !== last) { box.appendChild(h('.flxsm-agenda-h', S.fmtDay(ds, { weekday: 'long' }))); last = ds; }
				box.appendChild(chip(p));
			});
			grid.appendChild(box);
		}
		S.onTick = load;
		load();
	}

	/* ---------------- Hesaplar ---------------- */
	function accountsView(el, q) {
		var b = st.boot;
		S.setTop(t('Hesaplar'), [b.user.manage ? S.btn('plus', t('Hesap bağla'), connectModal, 'is-primary') : null]);
		if (q.error) S.toast(q.error, 'error');
		if (q.added) S.toast(t('Hesap eklendi.'));
		if (q.updated && !q.pick) S.toast(t('{n} hesabın bağlantısı yenilendi.', { n: q.updated }));
		if (q.error || q.added || q.updated || q.pick) {
			if (q.pick) pickModal(q.pick);
			history.replaceState(null, '', '#/accounts');
			if (q.added || q.updated) S.reloadBoot().then(function () { draw(); });
		}
		var box = h('.flxsm-accs');
		el.appendChild(box);
		function draw() {
			S.clear(box);
			if (!b.accounts.length) {
				box.appendChild(h('.flxsm-empty', S.icon('users', 30), h('h3', t('Henüz hesap yok')),
					h('p', b.user.manage ? t('Önce Ayarlar › Uygulamalar’dan ağın anahtarlarını girin, sonra buradan hesabı bağlayın.') : t('Hesapları yönetici bağlar.')),
					b.user.manage ? h('.flxsm-row', S.btn('plus', t('Hesap bağla'), connectModal, 'is-primary'), h('a.flxsm-btn', { href: '#/settings?tab=apps' }, S.icon('key', 16), h('span', t('Uygulama anahtarları')))) : null));
				return;
			}
			var groups = {};
			b.accounts.forEach(function (a) { (groups[a.provider] = groups[a.provider] || []).push(a); });
			Object.keys(b.providers).forEach(function (pid) {
				if (!groups[pid]) return;
				var sec = h('section.flxsm-accgroup', h('h3', S.net(pid, 20), b.providers[pid].label, h('span.flxsm-muted', String(groups[pid].length))));
				var gridEl = h('.flxsm-accgrid');
				groups[pid].forEach(function (a) { gridEl.appendChild(card(a)); });
				sec.appendChild(gridEl);
				box.appendChild(sec);
			});
		}
		function card(a) {
			var caps = a.caps || {};
			var feats = [];
			if (caps.max_text) feats.push(t('{n} karakter', { n: caps.max_text.toLocaleString(S.locale) }));
			if (caps.image) feats.push(t('{n} görsel', { n: caps.max_images }));
			if (caps.video) feats.push(t('video'));
			if (caps.edit) feats.push(t('düzenleme'));
			if (caps.delete) feats.push(t('silme'));
			var stLabel = { active: t('Etkin'), expired: t('Yeniden bağlanmalı'), disabled: t('Kapalı') }[a.status] || a.status;
			return h('.flxsm-acc.is-' + a.status,
				h('.flxsm-acc-top', S.avatar(a, 44), h('.flxsm-acc-n', h('strong', a.name), h('span.flxsm-muted', [a.username ? '@' + String(a.username).replace(/^@/, '') : '', a.kind ? kindLabel(a.kind) : ''].filter(Boolean).join(' · '))),
					b.user.manage ? S.iconBtn('dots', t('İşlemler'), function (e) { accMenu(e.currentTarget, a); }) : null),
				h('.flxsm-acc-st', h('span.flxsm-badge.is-' + (a.status === 'active' ? 'published' : a.status === 'expired' ? 'failed' : 'cancelled'), stLabel), a.premium ? h('span.flxsm-badge.is-scheduled', 'Premium') : null),
				a.last_error ? h('p.flxsm-acc-err', a.last_error) : null,
				h('p.flxsm-muted.is-small.flxsm-acc-f', feats.join(' · ')),
				a.status === 'expired' && b.user.manage && a.provider !== 'whatsapp' ? S.btn('refresh', t('Yeniden bağla'), function () { startConnect(connectFor(a)); }, 'is-primary') : null
			);
		}
		function kindLabel(k) {
			var m = { page: t('Sayfa'), person: t('Profil'), org: t('Şirket sayfası'), channel: t('Kanal'), location: t('Konum'), status: t('Durum'), group: t('Grup'), business: t('İşletme'), media_creator: t('İçerik üretici'), creator: t('İçerik üretici'), user: '', personal: '' };
			return Object.prototype.hasOwnProperty.call(m, k) ? m[k] : k;
		}
		function connectFor(a) {
			if (a.provider === 'instagram') return a.via === 'instagram' ? 'instagram' : 'facebook';
			return a.provider;
		}
		function accMenu(anchor, a) {
			S.menu(anchor, [
				{ icon: 'refresh', label: t('Bağlantıyı sına ve yenile'), onClick: function () {
					S.toast(t('Deneniyor…'));
					S.api('POST', 'accounts/' + a.id + '/refresh').then(function () { S.toast(t('Bağlantı çalışıyor.')); S.reloadBoot().then(draw); }, function (e) { S.fail(e); S.reloadBoot().then(draw); });
				} },
				a.provider !== 'whatsapp' ? { icon: 'plug', label: t('Yeniden bağla'), onClick: function () { startConnect(connectFor(a)); } } : null,
				{ icon: 'edit', label: t('Adını değiştir'), onClick: function () {
					S.prompt(t('Hesap adı'), a.name).then(function (v) { if (v) S.api('POST', 'accounts/' + a.id, { name: v }).then(function () { S.reloadBoot().then(draw); }, S.fail); });
				} },
				{ icon: a.status === 'disabled' ? 'play' : 'pause', label: a.status === 'disabled' ? t('Etkinleştir') : t('Kapat (gönderi seçiminde görünmez)'), onClick: function () {
					S.api('POST', 'accounts/' + a.id, { disabled: a.status !== 'disabled' }).then(function () { S.reloadBoot().then(draw); }, S.fail);
				} },
				{ sep: true },
				{ icon: 'trash', label: t('Kaldır'), danger: true, onClick: function () {
					S.confirm(t('Hesap kaldırılsın mı?'), t('Belirteç silinir; bu hesaba zamanlanmış gönderiler paylaşılmaz. Yayınlanmış gönderiler ağda kalır.'), t('Kaldır'), true).then(function (ok) {
						if (ok) S.api('POST', 'accounts/' + a.id + '/delete').then(function () { S.toast(t('Kaldırıldı.')); S.reloadBoot().then(draw); }, S.fail);
					});
				} }
			].filter(Boolean), { alignRight: true });
		}
		draw();
	}

	function startConnect(forId) {
		location.href = S.restUrl('oauth/start?for=' + encodeURIComponent(forId) + '&_wpnonce=' + encodeURIComponent(B.nonce));
	}
	function connectModal() {
		var apps = st.boot.apps || [];
		var list = h('.flxsm-connect');
		apps.forEach(function (app) {
			app.connect.forEach(function (c) {
				list.appendChild(h('button.flxsm-conn' + (app.ready ? '' : '.is-off'), { type: 'button', onclick: function () {
					if (!app.ready) { m.close(); S.go('settings?tab=apps&app=' + app.id); return; }
					if (c.local) { m.close(); waModal(); return; }
					startConnect(c.for);
				} }, S.net(c.provider, 30), h('span.flxsm-conn-l', h('strong', c.label), h('small', app.ready ? app.label : t('Önce anahtar girin: {app}', { app: app.label }))), S.icon(app.ready ? 'chevR' : 'key', 16)));
			});
		});
		var m = S.modal(t('Hesap bağla'), [h('p.flxsm-muted', t('Ağın giriş sayfası açılır; izin verince buraya dönersiniz. Birden çok sayfa/kanal varsa hangilerinin ekleneceğini seçersiniz.')), list], { wide: false });
	}
	function pickModal(key) {
		S.api('GET', 'oauth/pick/' + key).then(function (d) {
			var chosen = {};
			var list = h('.flxsm-picklist');
			d.items.forEach(function (it) {
				chosen[it.id] = true;
				list.appendChild(h('label.flxsm-pickrow', h('input', { type: 'checkbox', checked: true, onchange: function (e) { chosen[it.id] = e.target.checked; } }), S.avatar(it, 34), h('span', h('strong', it.name), h('small.flxsm-muted', it.network + (it.username ? ' · @' + it.username : '')))));
			});
			var m = S.modal(t('Eklenecek hesapları seçin'), list, {
				sticky: true,
				foot: [
					h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
					h('button.flxsm-btn.is-primary', { type: 'button', onclick: function () {
						var ids = Object.keys(chosen).filter(function (k) { return chosen[k]; });
						S.api('POST', 'oauth/pick/' + key, { ids: ids }).then(function (r) { m.close(); S.toast(t('{n} hesap eklendi.', { n: r.added })); S.reloadBoot().then(route); }, S.fail);
					} }, t('Ekle'))
				]
			});
		}, S.fail);
	}
	function waModal() {
		var body = h('div', h('.flxsm-boot', h('.flxsm-spin')));
		var chosen = {};
		var items = [];
		var m = S.modal(t('WhatsApp: Durum ve gruplar'), body, {
			foot: [
				h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
				h('button.flxsm-btn.is-primary', { type: 'button', onclick: function () {
					var sel = items.filter(function (x) { return chosen[x.ext_id]; });
					if (!sel.length) return m.close();
					S.api('POST', 'whatsapp/add', { items: sel }).then(function (r) { m.close(); S.toast(t('{n} hesap eklendi.', { n: r.added })); S.reloadBoot().then(route); }, S.fail);
				} }, t('Ekle'))
			]
		});
		S.api('GET', 'whatsapp/targets').then(function (d) {
			items = d.items;
			S.clear(body);
			body.appendChild(h('p.flxsm-muted', t('Bağlantı Flux Mail’deki Evolution hattından gelir. Durum: hattın WhatsApp Durum’u (24 saat). Grup: hattın üye olduğu gruplar.')));
			var list = h('.flxsm-picklist');
			items.forEach(function (it) {
				list.appendChild(h('label.flxsm-pickrow' + (it.exists ? '.is-off' : ''), h('input', { type: 'checkbox', disabled: it.exists, checked: it.exists, onchange: function (e) { chosen[it.ext_id] = e.target.checked; } }),
					S.avatar({ name: it.name, avatar: it.avatar, provider: 'whatsapp' }, 34),
					h('span', h('strong', it.name), h('small.flxsm-muted', it.kind === 'status' ? t('Durum') : t('Grup') + (it.size ? ' · ' + t('{n} üye', { n: it.size }) : '') + (it.exists ? ' · ' + t('ekli') : '')))));
			});
			body.appendChild(list);
		}, function (e) { S.clear(body).appendChild(h('p.flxsm-err', e.message)); });
	}
	S.connectModal = connectModal;

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
