/* Flux Tasks — görev detayı: özellikler, açıklama, alt görevler, ekler, yorumlar, etkinlik, yapay zekâ. */
(function () {
	'use strict';
	var Y = window.FLXG;
	var t = Y.t;
	var h = Y.h;
	var S = Y.S;
	var P = Y.pick;
	var B = Y.B;
	var D = { cache: {}, inflight: {}, uploads: {}, task: null, el: null, tab: 'details', showActivity: Y.store('task-act') !== '0', pending: [], mentions: [], editing: false, seq: 0 };
	Y.D = D;

	Y.openTask = function (id) {
		id = +id;
		Y.go(Y.routeBase() + '/t/' + id, true);
	};
	Y.closeTask = function () {
		Y.go(Y.routeBase(), true);
	};

	/** Yönlendirici çağırır: id varsa çekmeceyi açar, yoksa kapatır. */
	Y.showTask = function (id) {
		if (!id) { hide(); return; }
		if (D.task && D.task.id === id && D.el && S.openTask === id) return;
		S.openTask = id;
		D.tab = 'details';
		D.pending = [];
		D.mentions = [];
		ensureShell();
		// Daha önce açılmış görev önbellekten anında çizilir, arkadan tazelenir.
		if (D.cache[id]) {
			D.task = D.cache[id];
			mergeInflight(D.task);
			render();
			load(id, true);
		} else {
			D.task = null;
			// Listedeki bilgiyle başlık hemen görünür, gerisi iskelet.
			var known = S.tasks[id];
			Y.clear(D.head);
			Y.clear(D.body).appendChild(Y.skeleton('task', { title: known ? known.title : '' }));
			load(id);
		}
		Y.emit('open-task', id);
	};
	function hide() {
		if (!D.el) return;
		D.el.classList.remove('is-open');
		document.documentElement.classList.remove('flxg-task-open', 'flxg-task-page');
		var el = D.el;
		var shade = D.shade;
		setTimeout(function () { el.remove(); shade.remove(); }, 200);
		D.el = null;
		D.task = null;
		S.openTask = 0;
		Y.$$('.flxg-row.is-open, .flxg-card.is-open').forEach(function (x) { x.classList.remove('is-open'); });
		Y.emit('close-task');
	}

	function ensureShell() {
		if (D.el) return;
		D.shade = h('.flxg-drawer-shade', { onclick: Y.closeTask });
		D.head = h('header.flxg-dr-head');
		D.body = h('.flxg-dr-body');
		D.el = h('aside.flxg-drawer', { role: 'dialog', 'aria-modal': 'true', 'aria-label': t('Görev') }, D.head, D.body);
		document.documentElement.classList.add('flxg-task-open');
		var app = Y.$('#flxg-app');
		app.appendChild(D.shade);
		app.appendChild(D.el);
		requestAnimationFrame(function () { D.el.classList.add('is-open'); });
	}

	function load(id, keepScroll) {
		var seq = ++D.seq;
		return Y.api('GET', 'tasks/' + id).then(function (r) {
			if (seq !== D.seq || S.openTask !== id) return;
			D.task = r.task;
			mergeInflight(D.task);
			remember(r.task);
			if (D.remoteStale) D.remoteStale = false;
			if (typeof r.unread === 'number' && r.unread !== S.unread) { S.unread = r.unread; Y.emit('unread'); }
			var sc = keepScroll && D.body.querySelector('.flxg-dr-main') ? D.body.querySelector('.flxg-dr-main').scrollTop : 0;
			var sc2 = keepScroll && D.stream ? D.stream.scrollTop : null;
			render();
			if (keepScroll) {
				var m = D.body.querySelector('.flxg-dr-main');
				if (m) m.scrollTop = sc;
				if (sc2 !== null && D.stream) D.stream.scrollTop = sc2;
			}
		}, function (e) {
			if (seq !== D.seq) return;
			Y.clear(D.body).appendChild(h('.flxg-empty', h('p', e.message), h('button.flxg-btn', { type: 'button', onclick: Y.closeTask }, t('Kapat'))));
		});
	}
	function remember(task) {
		delete D.cache[task.id];
		D.cache[task.id] = task;
		var keys = Object.keys(D.cache);
		if (keys.length > 30) delete D.cache[keys[0]];
	}
	Y.reloadTask = function () { if (D.task) load(D.task.id, true); };

	/* ---------------- Gönderilmekte olan yorum / alt görevler ----------------
	 * Sunucu cevabı gelmeden görev kapatılıp açılsa ya da tazelense bile kaybolmasınlar.
	 */
	function inflight(id) { return D.inflight[id] || (D.inflight[id] = { comments_list: [], children: [] }); }
	function mergeInflight(task) {
		var f = D.inflight[task.id];
		if (!f) return;
		['comments_list', 'children'].forEach(function (k) {
			task[k] = task[k] || [];
			f[k].forEach(function (x) { if (!task[k].some(function (y) { return y._tmp === x._tmp; })) task[k].push(x); });
		});
	}
	/** Geçici kaydı sunucudan gelen gerçeğiyle değiştirir (real null ise siler). */
	function settle(taskId, k, tmp, real) {
		var f = D.inflight[taskId];
		if (f) f[k] = f[k].filter(function (x) { return x !== tmp; });
		var objs = [];
		[D.task, D.cache[taskId]].forEach(function (o) { if (o && o.id === taskId && objs.indexOf(o) < 0) objs.push(o); });
		objs.forEach(function (o) {
			var arr = o[k] || (o[k] = []);
			var i = -1;
			arr.forEach(function (x, j) { if (x._tmp === tmp._tmp) i = j; });
			if (i > -1) {
				if (real) arr.splice(i, 1, real); else arr.splice(i, 1);
			} else if (real && !arr.some(function (x) { return x.id === real.id; })) {
				arr.push(real);
			}
		});
		return D.task && D.task.id === taskId;
	}

	Y.on('task-remote', function () {
		if (!D.task) return;
		// Yazarken ekranı bozma; bitince tazelenir.
		if (D.editing || (D.composer && D.composer.value.trim()) || D.pending.length) { D.remoteStale = true; return; }
		load(D.task.id, true);
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && D.el && !Y.isPopOpen() && !Y.$('.flxg-modal-shade') && !Y.$('.flxg-lightbox')) {
			var a = document.activeElement;
			if (a && (a.isContentEditable || /INPUT|TEXTAREA/.test(a.tagName))) { a.blur(); return; }
			Y.closeTask();
		}
	});

	function role() { return D.task ? D.task.role : 'view'; }
	function canEdit() { return Y.rank(role()) >= 3; }
	function canComment() { return Y.rank(role()) >= 2; }

	/** Görevi kaydeder ve detayı tazeler. */
	function save(patch) {
		var id = D.task.id;
		// İyimser arayüz: değişiklik hemen ekranda, kaydedilene kadar alan hafif soluk; hata olursa geri alınır.
		var before = JSON.parse(JSON.stringify(D.task));
		var keys = pendKeys(patch);
		applyLocal(D.task, patch);
		D.pend = D.pend || {};
		keys.forEach(function (k) { D.pend[k] = (D.pend[k] || 0) + 1; });
		var unpend = function () { keys.forEach(function (k) { D.pend[k] = (D.pend[k] || 1) - 1; if (D.pend[k] <= 0) delete D.pend[k]; }); };
		if (keys.some(function (k) { return k !== 'description' && k !== 'title'; })) render(true);
		patch.detail = true;
		Y.keepTask(id);
		D.saving = (D.saving || 0) + 1;
		if (D.el) D.el.classList.add('is-saving');
		var end = function () { D.saving = Math.max(0, D.saving - 1); if (!D.saving && D.el) D.el.classList.remove('is-saving'); };
		return Y.api('PATCH', 'tasks/' + id, patch).then(function (r) {
			end();
			unpend();
			if (D.task && D.task.id === id) {
				D.task = r.task;
				mergeInflight(D.task);
				remember(r.task);
				render(true);
			}
			Y.upsertTask(JSON.parse(JSON.stringify(r.task)));
			Y.emit('tasks');
			return r.task;
		}, function (e) {
			end();
			unpend();
			if (D.task && D.task.id === id) { D.task = before; render(true); }
			Y.fail(e);
			throw e;
		});
	}
	function pendKeys(patch) {
		var out = [];
		Object.keys(patch).forEach(function (k) {
			if (k === 'fields') Object.keys(patch.fields || {}).forEach(function (f) { out.push('f' + f); });
			else if (k !== 'detail') out.push(k);
		});
		return out;
	}
	function applyLocal(task, patch) {
		Object.keys(patch).forEach(function (k) {
			if (k === 'fields') {
				task.fields = Object.assign({}, task.fields);
				Object.keys(patch.fields).forEach(function (f) {
					if (patch.fields[f] === null || patch.fields[f] === '') delete task.fields[f]; else task.fields[f] = patch.fields[f];
				});
			} else if (k === 'list') task.list = +patch.list;
			else if (k !== 'detail') task[k] = patch[k];
		});
	}
	function markPending() {
		if (!D.pend || !D.body) return;
		Object.keys(D.pend).forEach(function (k) {
			Y.$$('[data-k~="' + k + '"]', D.el).forEach(function (x) { x.classList.add('is-pending'); });
		});
	}
	Y.saveTask = save;

	/* =====================================================================
	 * Çizim
	 * ================================================================== */
	function render(keepScroll) {
		var task = D.task;
		if (!task || !D.el) return;
		var main = D.body.querySelector('.flxg-dr-main');
		var sc = keepScroll && main ? main.scrollTop : 0;
		renderHead(task);
		Y.clear(D.body);
		var mobile = Y.isMobile();
		D.el.classList.toggle('is-mobile', mobile);
		// Toplantı ve maddeleri modal değil sayfa gibi açılır (arkada yine aynı çekmece).
		var page = Y.isMeetingList(task.list);
		D.el.classList.toggle('is-page', page);
		document.documentElement.classList.toggle('flxg-task-page', page);
		if (mobile) {
			D.body.appendChild(h('.flxg-seg.flxg-dr-tabs',
				h('button' + (D.tab === 'details' ? '.is-on' : ''), { type: 'button', onclick: function () { D.tab = 'details'; render(); } }, t('Detaylar')),
				h('button' + (D.tab === 'comments' ? '.is-on' : ''), { type: 'button', onclick: function () { D.tab = 'comments'; render(); } }, t('Yorumlar'), task.comments_list.length ? h('span.flxg-badge', task.comments_list.length) : null)
			));
		}
		var mainEl = renderMain(task);
		var sideOpen = Y.store('task-side') !== '0';
		D.el.classList.toggle('is-side-closed', !mobile && !sideOpen);
		if (mobile) {
			D.body.appendChild(D.tab === 'details' ? mainEl : renderSide(task));
		} else {
			D.body.appendChild(mainEl);
			D.body.appendChild(sideOpen ? renderSide(task) : sideRail(task));
		}
		if (keepScroll) mainEl.scrollTop = sc;
		if (D.focusSubAdd) {
			D.focusSubAdd = false;
			setTimeout(function () { var i = Y.$('.flxg-sub-add input', D.body); if (i) { i.scrollIntoView({ block: 'center' }); i.focus(); } }, 30);
		}
		Y.$$('.flxg-row.is-open, .flxg-card.is-open').forEach(function (x) { x.classList.remove('is-open'); });
		Y.$$('[data-id="' + task.id + '"]').forEach(function (x) { if (x.matches('.flxg-row,.flxg-card')) x.classList.add('is-open'); });
		markPending();
	}

	function renderHead(task) {
		Y.clear(D.head);
		var l = Y.list(task.list);
		var sp = l ? Y.space(l.space_id) : null;
		var crumbs = h('.flxg-dr-crumbs',
			sp ? h('span', sp.name) : null,
			sp ? Y.icon('chevR', 13) : null,
			l ? h('a', { href: '#/list/' + l.id, onclick: function () { } }, h('span.flxg-dot', { style: { background: l.color } }), l.name) : null,
			h('button.flxg-idchip', { type: 'button', title: t('Bağlantıyı kopyala'), onclick: function () { Y.copy(Y.B.appUrl + '#/task/' + task.id); } }, '#' + task.id)
		);
		var watching = task.watching;
		if (task.parentInfo) {
			// Alt görev / gündem / karar içinden bir üst kayda belirgin dönüş.
			var pk = task.kind === 'agenda' || task.kind === 'decision' ? t('Toplantıya dön') : t('Üst göreve dön');
			D.head.appendChild(h('button.flxg-back-btn', { type: 'button', title: task.parentInfo.title, onclick: function () { Y.openTask(task.parentInfo.id); } },
				Y.icon('back', 16), h('span.flxg-back-k', pk), h('span.flxg-back-t', task.parentInfo.title)));
		}
		D.head.appendChild(crumbs);
		D.head.appendChild(h('.flxg-dr-actions',
			h('button.flxg-icon-btn' + (watching ? '.is-on' : ''), {
				type: 'button',
				title: watching ? t('Takibi bırak') : t('Takip et (bildirim al)'),
				onclick: function () {
					Y.api('POST', 'tasks/' + task.id + '/watch', { watch: !watching }).then(function (r) {
						task.watchers = r.watchers;
						task.watching = !watching;
						renderHead(task);
						Y.toast(task.watching ? t('Bu görevin bildirimlerini alacaksınız') : t('Bu görevin bildirimleri kapatıldı'));
					}, Y.fail);
				}
			}, Y.icon('bell', 17), h('span.flxg-watch-n', task.watchers.length)),
			h('button.flxg-icon-btn', { type: 'button', title: t('Diğer'), onclick: function (e) { moreMenu(e.currentTarget); } }, Y.icon('dots', 18)),
			h('button.flxg-icon-btn', { type: 'button', title: t('Kapat'), onclick: Y.closeTask }, Y.icon('x', 19))
		));
	}

	function moreMenu(anchor) {
		var task = D.task;
		Y.menu(anchor, [
			{ label: t('Bağlantıyı kopyala'), icon: 'link', onClick: function () { Y.copy(Y.B.appUrl + '#/task/' + task.id); } },
			canEdit() ? { label: t('Başka listeye taşı'), icon: 'move', onClick: function () {
				P.list(anchor, function (lid) { save({ list: lid }).then(function () { Y.toast(t('Görev taşındı')); }); }, { current: task.list });
			} } : null,
			canEdit() ? { label: t('Alt görev ekle'), icon: 'subtask', onClick: function () { D.tab = 'details'; render(); var i = Y.$('.flxg-sub-add input', D.body); if (i) i.focus(); } } : null,
			canEdit() ? { label: t('Kopyasını oluştur'), icon: 'copy', onClick: duplicate } : null,
			canEdit() ? { label: task.archived ? t('Arşivden çıkar') : t('Arşivle'), icon: 'archive', onClick: function () { save({ archived: !task.archived }).then(function () { if (!task.archived) { delete S.tasks[task.id]; Y.emit('tasks'); } }); } } : null,
			canEdit() ? { sep: true } : null,
			canEdit() ? { label: t('Görevi sil'), icon: 'trash', danger: true, onClick: function () {
				Y.confirm(t('Görevi sil'), t('"{n}" görevi, yorum ve dosyalarıyla birlikte kalıcı olarak silinecek.', { n: task.title }), t('Sil'), true).then(function (ok) {
					if (ok) Y.deleteTask(task.id).then(function () { Y.closeTask(); Y.toast(t('Görev silindi')); }, Y.fail);
				});
			} } : null
		]);
	}

	function duplicate() {
		var x = D.task;
		Y.createTask({ list: x.list, title: x.title + ' ' + t('(kopya)'), description: x.description, status: x.status, priority: x.priority, start: x.start, due: x.due, assignees: x.assignees, tags: x.tags, fields: x.fields, parent: x.parent || 0 }).then(function (n) {
			Y.toast(t('Kopya oluşturuldu'));
			Y.openTask(n.id);
		}, Y.fail);
	}

	function attrRow(icon, label, value, onClick, key) {
		key = key || { users: 'assignees', flag: 'priority', tag: 'tags', calendar: 'start due' }[icon];
		var v = h('.flxg-attr-val' + (onClick ? '.is-edit' : ''), { dataset: key ? { k: key } : {} }, value);
		if (onClick) {
			v.tabIndex = 0;
			v.addEventListener('click', function () { onClick(v); });
			v.addEventListener('keydown', function (e) { if (e.key === 'Enter') onClick(v); });
		}
		return h('.flxg-attr', h('.flxg-attr-label', Y.icon(icon, 15), h('span', label)), v);
	}

	function renderMain(task) {
		var main = h('.flxg-dr-main');
		var edit = canEdit();
		if (edit) {
			// Detay sütununun herhangi bir yerine bırakılan dosya eklere yüklenir.
			var dd = 0;
			main.addEventListener('dragenter', function (e) { if (!hasFiles(e)) return; e.preventDefault(); dd++; main.classList.add('is-dropping'); });
			main.addEventListener('dragover', function (e) { if (hasFiles(e)) e.preventDefault(); });
			main.addEventListener('dragleave', function () { dd = Math.max(0, dd - 1); if (!dd) main.classList.remove('is-dropping'); });
			main.addEventListener('drop', function (e) {
				dd = 0;
				main.classList.remove('is-dropping');
				if (!hasFiles(e)) return;
				e.preventDefault();
				uploadTaskFiles(task, Array.prototype.slice.call(e.dataTransfer.files || []));
			});
		}
		var l = Y.list(task.list);
		var mtg = isMeeting(task);
		if (mtg) main.classList.add('is-meeting');
		// Kapak.
		if (task.cover) {
			main.appendChild(h('.flxg-dr-cover',
				h('img', { src: Y.fileUrl(task.cover, 'thumb'), alt: '', onclick: function () {
					var imgs = task.attachments.filter(function (f) { return f.image; });
					Y.lightbox(imgs, Math.max(0, imgs.map(function (f) { return f.id; }).indexOf(task.cover)));
				} }),
				edit ? h('.flxg-dr-cover-btns',
					h('button.flxg-btn.is-sm', { type: 'button', onclick: function (e) { coverPick(e.currentTarget); } }, Y.icon('image', 14), t('Kapağı değiştir')),
					h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { save({ cover: 0 }); } }, t('Kaldır'))
				) : null
			));
		}
		// Başlık.
		var title = h('textarea.flxg-dr-title', { rows: 1, readonly: !edit, 'aria-label': t('Görev adı') });
		title.value = task.title;
		Y.autosize(title);
		title.addEventListener('focus', function () { D.editing = true; });
		title.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); title.blur(); } });
		title.addEventListener('blur', function () {
			D.editing = false;
			var v = title.value.replace(/\s+/g, ' ').trim();
			if (v && v !== task.title) save({ title: v });
			else title.value = task.title;
		});
		// Başlık ve durum satırı kaydırınca üstte sabit kalır.
		var sticky = h('.flxg-dr-sticky', title);
		main.appendChild(sticky);

		// Durum satırı.
		var st = Y.status(task);
		var doneKey = l ? (l.statuses.filter(function (s) { return s.type === 'done'; })[0] || {}).key : null;
		sticky.appendChild(h('.flxg-dr-statusbar',
			h('button.flxg-status.is-btn', { type: 'button', style: { '--c': st.color }, dataset: { k: 'status' }, disabled: !edit, onclick: function (e) { P.status(e.currentTarget, task.list, task.status, function (k) { save({ status: k }); }); } }, st.label, edit ? Y.icon('chevD', 14) : null),
			edit && doneKey && st.type !== 'done' ? h('button.flxg-btn.is-sm.is-done-btn', { type: 'button', onclick: function () { save({ status: doneKey }); } }, Y.icon('check', 14), mtg ? t('Yapıldı') : t('Tamamla')) : null,
			st.type === 'done' && task.completed && !mtg ? h('span.flxg-muted', t('Tamamlandı: {d}', { d: Y.ago(task.completed) })) : null,
			!edit ? h('span.flxg-readonly', Y.icon('lock', 13), Y.rank(role()) >= 2 ? t('Yalnız yorum yapabilirsiniz') : t('Yalnız görüntüleme')) : null,
			mtg ? h('span.flxg-grow') : null,
			mtg ? h('button.flxg-btn.is-sm.is-ghost', { type: 'button', title: t('Toplantı raporunu yazdır ya da PDF olarak kaydet'), onclick: function () { meetingReport(task); } }, Y.icon('printer', 14), h('span', t('PDF rapor'))) : null,
			mtg ? h('button.flxg-icon-btn.is-sm' + (D.mtgCustom ? '.is-on' : ''), { type: 'button', title: D.mtgCustom ? t('Özelleştirmeyi gizle') : t('Ekranı özelleştir'), onclick: function () { D.mtgCustom = !D.mtgCustom; render(true); } }, Y.icon('dots', 16)) : null
		));
		if (mtg) return meetingBody(task, l, edit, main);

		// Özellikler.
		var attrs = h('.flxg-attrs');
		attrs.appendChild(attrRow('users', t('Atananlar'), task.assignees.length ? h('span.flxg-people', task.assignees.map(function (id) {
			var u = S.users[id];
			return h('span.flxg-person', Y.avatar(u, 22), h('span', u ? u.name : '?'));
		})) : h('span.flxg-muted', t('Boş')), edit ? function (a) {
			P.people(a, task.assignees, Y.debounce(function (ids) { save({ assignees: ids }); }, 700));
		} : null));
		attrs.appendChild(attrRow('calendar', t('Tarihler'), h('span.flxg-dates',
			h('button.flxg-date-btn', { type: 'button', disabled: !edit, onclick: function (e) { e.stopPropagation(); P.date(e.currentTarget, task.start, function (v) { save({ start: v }); }, { label: t('Başlangıç') }); } }, task.start ? Y.fmtDue(task.start) : t('Başlangıç')),
			Y.icon('chevR', 13),
			h('button.flxg-date-btn' + (Y.dueState(task.due, st.type === 'done') ? '.is-' + Y.dueState(task.due, st.type === 'done') : ''), { type: 'button', disabled: !edit, onclick: function (e) { e.stopPropagation(); P.date(e.currentTarget, task.due, function (v) { save({ due: v }); }, { label: t('Bitiş') }); } }, task.due ? Y.fmtDue(task.due) : t('Bitiş'))
		)));
		attrs.appendChild(attrRow('flag', t('Öncelik'), Y.prioFlag(task.priority, true) || h('span.flxg-muted', t('Boş')), edit ? function (a) {
			P.priority(a, task.priority, function (n) { save({ priority: n }); });
		} : null));
		attrs.appendChild(attrRow('tag', t('Etiketler'), task.tags.length ? Y.tagChips(task.tags) : h('span.flxg-muted', t('Boş')), edit ? function (a) {
			P.tags(a, l ? l.space_id : 0, task.tags, Y.debounce(function (ids) { save({ tags: ids }); }, 700));
		} : null));
		// Özel alanlar.
		Y.fieldsFor(task.list).concat(S.scope && S.scope.type !== 'list' || !Y.fieldsFor(task.list).length ? [] : []).forEach(function (f) {
			attrs.appendChild(fieldRow(task, f, edit));
		});
		ensureFields(task);
		main.appendChild(limitAttrs(attrs));
		if (l && Y.rank(l.role) >= 4) {
			main.appendChild(h('button.flxg-link-btn', { type: 'button', onclick: function () { Y.listSettings(l.id, 'fields'); } }, Y.icon('plus', 14), t('Özel alan ekle / düzenle')));
		}

		// Açıklama.
		var aiBox = h('.flxg-ai-inline-wrap');
		main.appendChild(h('h3.flxg-dr-h.has-tools', h('span', t('Açıklama')),
			S.me.can.ai && canComment() ? h('button.flxg-ai-btn' + (D.ai && D.ai.task === task.id ? '.is-on' : ''), { type: 'button', onclick: function () {
				if (D.ai && D.ai.task === task.id) { D.ai = null; } else { D.ai = { task: task.id, state: 'menu' }; }
				drawAI(aiBox, task);
				var b = Y.$('.flxg-ai-btn', D.body); if (b) b.classList.toggle('is-on', !!D.ai);
			} }, Y.icon('sparkle', 14), h('span', t('Yapay zekâ'))) : null));
		D.aiBox = aiBox;
		drawAI(aiBox, task);
		var descH = main.lastElementChild;
		main.appendChild(collapsible('desc', h('.flxg-sec-box', descH, aiBox, descEditor(task, edit))));

		// Alt görevler: mini tablo (görev adı + seçilen sütunlar), iç içe.
		main.appendChild(collapsible('subtasks', subTable(task, edit, l)));

		// Ekler.
		main.appendChild(filesSection(task, edit));
		return main;
	}

	/* ---------------- Katlanan bölümler ve kısaltılan alanlar (hesapta saklanır) ---------------- */
	function secOpen(k) { return !(Y.pref('sec-col') || {})[k]; }
	/** box'ın ilk .flxg-dr-h başlığı tıklanınca bölüm katlanır; başlık ve sayaç görünür kalır. */
	function collapsible(k, box) {
		var head = box.querySelector('.flxg-dr-h');
		if (!head) return box;
		var open = secOpen(k);
		box.classList.add('flxg-sec');
		box.classList.toggle('is-collapsed', !open);
		head.classList.add('is-tog');
		head.insertBefore(h('button.flxg-sec-tog', { type: 'button', 'aria-expanded': open ? 'true' : 'false', title: open ? t('Daralt') : t('Genişlet') }, Y.icon('chevD', 15)), head.firstChild);
		head.addEventListener('click', function (e) {
			if (e.target.closest('button:not(.flxg-sec-tog), a, input, label')) return;
			var m = Object.assign({}, Y.pref('sec-col') || {});
			if (open) m[k] = 1; else delete m[k];
			Y.setPref('sec-col', m);
			render(true);
		});
		return box;
	}
	function filesSection(task, edit) {
		var files = task.attachments.filter(function (f) { return f.context !== 'field'; });
		return collapsible('files', h('.flxg-sec-box', h('h3.flxg-dr-h', t('Ekler'), files.length ? h('span.flxg-gcount', files.length) : null), attachments(task, files, edit)));
	}
	var ATTR_MIN = 4;
	/** 4'ten fazla özellik/alan varsa ilk 4'ü görünür, gerisi "N alan daha" ile açılır. */
	function limitAttrs(box) {
		var rows = Array.prototype.slice.call(box.children);
		if (rows.length <= ATTR_MIN) return box;
		var all = !!Y.pref('attrs-all');
		if (!all) rows.slice(ATTR_MIN).forEach(function (r) { r.remove(); });
		box.appendChild(h('button.flxg-attrs-more', { type: 'button', onclick: function () { Y.setPref('attrs-all', all ? null : 1); render(true); } },
			Y.icon(all ? 'chevU' : 'chevD', 14), all ? t('Daha az göster') : t('{n} alan daha', { n: rows.length - ATTR_MIN })));
		return box;
	}

	function ensureFields(task) {
		if (!Y.fieldsFor(task.list).length && D.fieldsFor !== task.list) {
			// Liste dışı kapsamda (proje/benim) alanlar yüklenmemiş olabilir.
			D.fieldsFor = task.list;
			Y.api('GET', 'lists/' + task.list).then(function (r) {
				var known = {};
				S.fields.forEach(function (f) { known[f.id] = 1; });
				var addf = (r.fields || []).filter(function (f) { return !known[f.id]; });
				if (addf.length) { S.fields = S.fields.concat(addf); render(true); }
			}, function () {});
		}
	}

	/* =====================================================================
	 * Toplantı: görev altyapısı, sade ekran (tarih, katılımcılar, gündem, kararlar, PDF rapor)
	 * ================================================================== */
	function isMeeting(task) {
		return Y.isMeetingList(task.list) && (task.kind || 'task') === 'task' && !task.parent;
	}
	function mtgSections() {
		return [['date', t('Tarih')], ['people', t('Katılımcılar')], ['tags', t('Etiketler')], ['fields', t('Özel alanlar')], ['priority', t('Öncelik')],
			['desc', t('Açıklama')], ['agenda', t('Gündem')], ['decisions', t('Kararlar')], ['subtasks', t('Alt görevler')], ['files', t('Ekler')]];
	}
	function mtgHidden() { return Y.pref('mtg-hide') || { priority: 1, subtasks: 1 }; }
	function plain(html) {
		if (!html) return '';
		var doc = new DOMParser().parseFromString(String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|li|h\d)>/gi, '\n'), 'text/html');
		return (doc.body.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
	}
	function meetingBody(task, l, edit, main) {
		var hid = mtgHidden();
		function on(k) { return !hid[k]; }
		if (D.mtgCustom) {
			var chips = h('.flxg-mtg-chips');
			mtgSections().forEach(function (x) {
				chips.appendChild(h('button.flxg-fchip' + (on(x[0]) ? '.is-on' : ''), { type: 'button', onclick: function () {
					var m = Object.assign({}, mtgHidden());
					if (m[x[0]]) delete m[x[0]]; else m[x[0]] = 1;
					Y.setPref('mtg-hide', m);
					render(true);
				} }, on(x[0]) ? Y.icon('eye', 13) : Y.icon('eyeOff', 13), x[1]));
			});
			main.appendChild(h('.flxg-mtg-custom',
				h('.flxg-mtg-custom-head', h('b', t('Ekranda görünenler')), h('span.flxg-muted', t('Bu tarayıcıda saklanır.')), h('span.flxg-grow'),
					h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Kapat'), onclick: function () { D.mtgCustom = false; render(true); } }, Y.icon('x', 15))),
				chips,
				l && Y.rank(l.role) >= 4 ? h('.flxg-mtg-custom-links',
					h('button.flxg-link-btn', { type: 'button', onclick: function () { Y.listSettings(l.id, 'fields'); } }, Y.icon('plus', 14), t('Özel alan ekle / düzenle')),
					h('button.flxg-link-btn', { type: 'button', onclick: function () { Y.listSettings(l.id, 'statuses'); } }, Y.icon('check', 14), t('Durumları düzenle'))
				) : null
			));
		}
		var meta = h('.flxg-attrs.flxg-mtg-meta');
		if (on('date')) {
			meta.appendChild(attrRow('calendar', t('Tarih'), h('button.flxg-date-btn', { type: 'button', disabled: !edit, onclick: function (e) {
				e.stopPropagation();
				P.date(e.currentTarget, task.due, function (v) { save({ due: v }); }, { label: t('Tarih') });
			} }, task.due ? Y.fmtDue(task.due, { full: true }) : t('Tarih seçin'))));
		}
		if (on('people')) {
			meta.appendChild(attrRow('users', t('Katılımcılar'), task.assignees.length ? h('span.flxg-people', task.assignees.map(function (id) {
				var u = S.users[id];
				return h('span.flxg-person', Y.avatar(u, 22), h('span', u ? u.name : '?'));
			})) : h('span.flxg-muted', t('Kişi ekleyin')), edit ? function (a) {
				P.people(a, task.assignees, Y.debounce(function (ids) { save({ assignees: ids }); }, 700));
			} : null));
		}
		if (on('tags')) {
			meta.appendChild(attrRow('tag', t('Etiketler'), task.tags.length ? Y.tagChips(task.tags) : h('span.flxg-muted', t('Boş')), edit ? function (a) {
				P.tags(a, l ? l.space_id : 0, task.tags, Y.debounce(function (ids) { save({ tags: ids }); }, 700));
			} : null));
		}
		if (on('priority')) {
			meta.appendChild(attrRow('flag', t('Öncelik'), Y.prioFlag(task.priority, true) || h('span.flxg-muted', t('Boş')), edit ? function (a) {
				P.priority(a, task.priority, function (n) { save({ priority: n }); });
			} : null));
		}
		if (on('fields')) {
			Y.fieldsFor(task.list).forEach(function (f) { meta.appendChild(fieldRow(task, f, edit)); });
			ensureFields(task);
		}
		if (meta.children.length) main.appendChild(limitAttrs(meta));
		if (on('desc')) main.appendChild(collapsible('desc', h('.flxg-sec-box', h('h3.flxg-dr-h', t('Açıklama')), descEditor(task, edit))));
		if (on('agenda')) main.appendChild(collapsible('agenda', agendaBox(task, edit, l)));
		if (on('decisions')) main.appendChild(collapsible('decisions', subTable(task, edit, l, { kind: 'decision', key: 'dec-cols', title: t('Kararlar'), head: t('Karar'), ph: t('Karar ekle… (görev gibi atanır, tarih ve etiket alır)'), def: ['assignees', 'due', 'tags'] })));
		if (on('subtasks')) main.appendChild(collapsible('subtasks', subTable(task, edit, l)));
		if (on('files')) main.appendChild(filesSection(task, edit));
		return main;
	}

	/* ---------------- Gündem: sade liste, tıklayınca açılır ---------------- */
	function agendaBox(task, edit, l) {
		var items = (task.children || []).filter(function (c) { return c.kind === 'agenda'; }).sort(function (a, b) { return (a.pos || 0) - (b.pos || 0) || a.id - b.id; });
		var done = items.filter(Y.isDone).length;
		var box = h('.flxg-agenda-wrap', h('h3.flxg-dr-h', t('Gündem'), items.length ? h('span.flxg-gcount', done + '/' + items.length) : null));
		var list = h('.flxg-mtg-agenda', { 'data-drop': '1' });
		var open = D.agOpen || (D.agOpen = {});
		var doneK = l ? (l.statuses.filter(function (s) { return s.type === 'done'; })[0] || {}).key : null;
		var openK = l ? l.statuses[0].key : null;
		items.forEach(function (c, i) {
			var files = (task.childFiles && task.childFiles[c.id]) || [];
			var imgs = files.filter(function (f) { return f.image; });
			var isDone = Y.isDone(c);
			var exp = !!open[c.id] && !!c.id;
			var note = plain(c.description);
			function toggle() { if (!c.id) return; open[c.id] = !exp; render(true); }
			var row = h('.flxg-ag-item' + (isDone ? '.is-done' : '') + (exp ? '.is-open' : '') + (c.sending || (D.subPend && D.subPend[c.id]) ? '.is-sending' : ''), { 'data-id': c.id || '' },
				h('.flxg-ag-line',
					h('span.flxg-ag-num', (i + 1) + '.'),
					h('button.flxg-ag-check', { type: 'button', disabled: !edit || !c.id, title: isDone ? t('Görüşülmedi olarak işaretle') : t('Görüşüldü olarak işaretle'), onclick: function () {
						subPatch(task.id)(c.id, { status: isDone ? openK : doneK });
					} }, isDone ? Y.icon('check', 12) : null),
					h('button.flxg-ag-title', { type: 'button', onclick: toggle }, h('span.flxg-ag-t', c.title), note && !exp ? h('span.flxg-ag-note1', note) : null),
					imgs.length && !exp ? h('span.flxg-ag-thumbs', imgs.slice(0, 3).map(function (f) {
						return h('img', { src: Y.fileUrl(f.id, 'thumb'), alt: '', loading: 'lazy', onclick: function (e) { e.stopPropagation(); Y.lightbox(imgs, imgs.indexOf(f)); } });
					}), imgs.length > 3 ? h('span.flxg-ag-more', '+' + (imgs.length - 3)) : null) : null,
					files.length > imgs.length && !exp ? h('span.flxg-meta', Y.icon('clip', 13), files.length - imgs.length) : null,
					c.comments ? h('span.flxg-meta', Y.icon('msg', 13), c.comments) : null,
					h('button.flxg-icon-btn.is-sm.flxg-ag-chev', { type: 'button', 'aria-label': exp ? t('Daralt') : t('Aç'), onclick: toggle }, Y.icon(exp ? 'chevD' : 'chevR', 14))
				));
			if (exp) row.appendChild(agendaDetail(c, files, task, edit));
			list.appendChild(row);
		});
		if (!items.length && !edit) list.appendChild(h('.flxg-muted.flxg-ag-empty', t('Gündem maddesi yok.')));
		box.appendChild(list);
		if (edit) {
			var inp = h('input.flxg-input.is-sm', { type: 'text', placeholder: t('Gündem maddesi ekle…') });
			inp.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' || !inp.value.trim()) return;
				var v = inp.value.trim();
				inp.value = '';
				var tid = task.id;
				var tmp = { id: 0, _tmp: 'a' + Date.now(), kind: 'agenda', title: v, list: task.list, status: openK, assignees: [], tags: [], fields: {}, priority: 0, due: '', start: '', pos: 1e12, sending: true };
				task.children.push(tmp);
				inflight(tid).children.push(tmp);
				render(true);
				var refocus = function () { var i = Y.$('.flxg-ag-add input', D.body); if (i) i.focus(); };
				refocus();
				Y.createTask({ list: task.list, parent: tid, title: v, kind: 'agenda' }).then(function (nt) {
					if (settle(tid, 'children', tmp, nt)) { render(true); refocus(); }
					Y.keepTask(tid);
				}, function (er) {
					if (settle(tid, 'children', tmp, null)) render(true);
					Y.fail(er);
				});
			});
			box.appendChild(h('.flxg-ag-add', Y.icon('plus', 14), inp));
			Y.dnd(list, { item: '.flxg-ag-item', zone: '[data-drop]', canStart: function (el) { return !!+el.dataset.id && !el.classList.contains('is-open'); }, onDrop: function (info) {
				var byId = {};
				items.forEach(function (c) { byId[c.id] = c; });
				var a = info.after ? byId[info.after] : null;
				var b = info.before ? byId[info.before] : null;
				var pos = Y.between(a ? a.pos || 0 : null, b ? b.pos || 0 : null);
				var c = byId[info.id];
				if (!c) return;
				c.pos = pos;
				subPatch(task.id)(c.id, { position: pos });
			} });
		}
		return box;
	}
	function agendaDetail(c, files, task, edit) {
		var box = h('.flxg-ag-detail');
		var ta = h('textarea.flxg-input.flxg-ag-notein', { rows: 2, placeholder: edit ? t('Not ekleyin…') : '', readonly: !edit });
		ta.value = plain(c.description);
		Y.autosize(ta);
		ta.addEventListener('focus', function () { D.editing = true; });
		ta.addEventListener('blur', function () {
			D.editing = false;
			var v = ta.value.trim();
			if (v === plain(c.description)) return;
			c.description = v ? '<p>' + v.replace(/[&<>]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]; }).replace(/\n/g, '<br>') + '</p>' : '';
			subPatch(task.id)(c.id, { description: c.description });
		});
		if (edit || ta.value) box.appendChild(ta);
		var imgs = files.filter(function (f) { return f.image; });
		if (files.length) {
			box.appendChild(h('.flxg-ag-files', files.map(function (f) {
				if (f.image) return h('button.flxg-ag-img', { type: 'button', title: f.name, onclick: function () { Y.lightbox(imgs, imgs.indexOf(f)); } }, h('img', { src: Y.fileUrl(f.id, 'thumb'), alt: f.name, loading: 'lazy' }));
				return h('a.flxg-ag-file', { href: Y.fileUrl(f.id, '', true), download: f.name }, Y.icon('file', 14), h('span', f.name));
			})));
		}
		box.appendChild(h('.flxg-ag-actions',
			edit ? h('label.flxg-chip-btn', Y.icon('upload', 14), t('Görsel / dosya ekle'), h('input', { type: 'file', multiple: true, hidden: true, onchange: function (e) {
				var list = Array.prototype.slice.call(e.target.files || []);
				e.target.value = '';
				Promise.all(list.map(function (file) { return Y.upload(file, { task: c.id, context: 'task' }); })).then(function () { load(task.id, true); }, function (er) { Y.fail(er); load(task.id, true); });
			} })) : null,
			h('button.flxg-chip-btn', { type: 'button', onclick: function () { Y.openTask(c.id); } }, Y.icon('eye', 14), t('Ayrıntı ve yorumlar')),
			edit ? h('button.flxg-chip-btn.is-danger', { type: 'button', onclick: function () {
				Y.confirm(t('Gündem maddesini sil'), t('"{n}" silinecek.', { n: c.title }), t('Sil'), true).then(function (ok) {
					if (ok) Y.deleteTask(c.id).then(function () { load(task.id, true); }, Y.fail);
				});
			} }, Y.icon('trash', 14), t('Sil')) : null
		));
		return box;
	}

	/* ---------------- Toplantı raporu (yazdır / PDF) ---------------- */
	var REPORT_CSS = '.rp{max-width:800px;color:#1f2937;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
		'.rp-head{display:flex;align-items:center;gap:12px;padding-bottom:14px;border-bottom:3px solid var(--ac);margin-bottom:22px}' +
		'.rp-head img{width:40px;height:40px;border-radius:9px;object-fit:cover}.rp-org{font-weight:700;font-size:15px;line-height:1.2}.rp-org small{display:block;font-weight:500;color:#6b7280;font-size:12px}' +
		'.rp-kind{margin-left:auto;text-align:right;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--ac);font-weight:700}.rp-kind small{display:block;letter-spacing:0;text-transform:none;color:#6b7280;font-weight:500;font-size:12px}' +
		'.rp h1{font-size:28px;line-height:1.25;margin:0 0 10px;letter-spacing:-.01em}' +
		'.pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11.5px;font-weight:600;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);white-space:nowrap}' +
		'.rp-info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.rp-info>div{border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;background:#fafafa}' +
		'.rp-info .k{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;font-weight:600;margin-bottom:3px}.rp-info .wide{grid-column:1/-1}' +
		'.ppl{display:flex;flex-wrap:wrap;gap:6px 14px}.ppl span{display:inline-flex;align-items:center;gap:6px}.av{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;background:var(--c)}' +
		'.chip{display:inline-block;padding:1px 8px;border-radius:5px;font-size:12px;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);margin:0 4px 4px 0}' +
		'.rp-stats{display:flex;gap:10px;margin:0 0 8px}.rp-stats div{flex:1;border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--ac) 8%,#fff)}.rp-stats b{display:block;font-size:22px;color:var(--ac);line-height:1.2}.rp-stats span{font-size:12px;color:#4b5563}' +
		'.rp h2{display:flex;align-items:center;gap:10px;font-size:17px;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid #e5e7eb;page-break-after:avoid}.rp h2 .n{font-size:12px;font-weight:600;color:#6b7280}' +
		'.ag{display:flex;gap:12px;padding:10px 0;border-bottom:1px dashed #e5e7eb;page-break-inside:avoid}.ag:last-child{border-bottom:0}.ag-no{flex:none;width:26px;height:26px;border-radius:50%;background:var(--ac);color:#fff;font-weight:700;font-size:12.5px;display:flex;align-items:center;justify-content:center}' +
		'.ag.is-done .ag-no{background:#16a34a}.ag-t{font-weight:600}.ag-note{margin-top:3px;color:#374151}.ok{color:#16a34a;font-size:12px;font-weight:600;margin-left:6px}.ag-sub{margin-top:5px;font-size:13px}' +
		'.rp table.t{border-collapse:collapse;width:100%;border:1px solid #e5e7eb;margin:6px 0}.rp table.t thead{display:table-header-group}.rp table.t th{background:#f3f4f6;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#4b5563;border:0;border-bottom:1px solid #e5e7eb;padding:7px 10px}' +
		'.rp table.t td{border:0;border-bottom:1px solid #f0f0f0;padding:8px 10px}.rp table.t tr:last-child td{border-bottom:0}.rp table.t tr{page-break-inside:avoid}.rp table.t td.c{white-space:nowrap;font-size:12.5px}' +
		'.sub-t{display:flex;align-items:flex-start;gap:7px}.box-c{flex:none;width:13px;height:13px;border:1.5px solid #9ca3af;border-radius:4px;margin-top:4px}.box-c.on{background:#16a34a;border-color:#16a34a;position:relative}.box-c.on::after{content:"";position:absolute;left:3px;top:0;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}' +
		'.is-done .sub-name{color:#6b7280;text-decoration:line-through}.rp .muted{color:#6b7280}.rp .desc{font-size:12.5px;color:#4b5563;margin-top:2px}' +
		'.rp-foot{margin-top:36px;padding-top:10px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:11px;color:#9ca3af}' +
		'@media print{.rp h1{font-size:24px}}';

	function meetingReport(task) {
		var esc = Y.esc;
		var l = Y.list(task.list);
		var sp = l ? Y.space(l.space_id) : null;
		var st = Y.status(task);
		var hid = mtgHidden();
		var ac = (sp && sp.color) || '#2e7d32';
		function user(id) { return S.users[id] || { name: '?', color: '#64748b', initials: '?' }; }
		function pill(s) { return '<span class="pill" style="--c:' + esc(s.color || '#64748b') + '">' + esc(s.label) + '</span>'; }
		function people(ids) {
			return '<div class="ppl">' + ids.map(function (id) { var u = user(id); return '<span><i class="av" style="--c:' + esc(u.color || '#64748b') + '">' + esc(u.initials || (u.name || '?').slice(0, 1)) + '</i>' + esc(u.name) + '</span>'; }).join('') + '</div>';
		}
		function due(d) { return d ? esc(Y.fmtDue(d, { full: true })) : '<span class="muted">—</span>'; }
		var kids = task.children || [];
		var agenda = kids.filter(function (c) { return c.kind === 'agenda' && c.id; }).sort(function (a, b) { return (a.pos || 0) - (b.pos || 0) || a.id - b.id; });
		var dec = kids.filter(function (c) { return c.kind === 'decision' && c.id; });
		// Alt görevler: doğrudan görev türündekiler ve onların alt ağacı, girintili.
		var byParent = {};
		(task.subtree || []).forEach(function (c) { (byParent[c.parent] = byParent[c.parent] || []).push(c); });
		function tree(list, depth, acc) {
			list.slice().sort(function (a, b) { return (a.pos || 0) - (b.pos || 0) || a.id - b.id; }).forEach(function (c) {
				acc.push({ t: c, d: depth });
				if (depth < 9) tree(byParent[c.id] || [], depth + 1, acc);
			});
			return acc;
		}
		var subs = tree(kids.filter(function (c) { return (c.kind || 'task') === 'task' && c.id; }), 0, []);
		function people0(ids) { return esc(ids.map(function (id) { return user(id).name; }).join(', ')); }
		/** Alt madde satırı (karar ya da alt görev tablosunda, girintili, işaretli). */
		function subRow(x, lead) {
			var c = x.t;
			var ok = Y.isDone(c);
			return '<tr' + (ok ? ' class="is-done"' : '') + '>' + (lead ? '<td></td>' : '') + '<td><div class="sub-t" style="padding-left:' + (x.d * 20) + 'px"><i class="box-c' + (ok ? ' on' : '') + '"></i><div><span class="sub-name">' + esc(c.title) + '</span>' +
				(plain(c.description) ? '<div class="desc">' + esc(plain(c.description).slice(0, 240)) + '</div>' : '') + '</div></div></td><td class="c">' +
				people0(c.assignees) + '</td><td class="c">' + due(c.due) + '</td><td class="c">' + pill(Y.status(c)) + '</td></tr>';
		}
		var subDone = subs.filter(function (x) { return Y.isDone(x.t); }).length;

		var out = '<div class="rp-head">' + (B.icon ? '<img src="' + esc(B.icon) + '" alt="">' : '') +
			'<div class="rp-org">' + esc(B.brand || '') + '<small>' + esc((sp ? sp.name : '') + (l ? ' / ' + l.name : '')) + '</small></div>' +
			'<div class="rp-kind">' + esc(t('Toplantı raporu')) + '<small>' + (task.due ? esc(Y.fmtDue(task.due, { full: true })) : '') + '</small></div></div>';
		out += '<h1>' + esc(task.title) + '</h1>' + pill(st);
		var info = [];
		info.push([t('Tarih'), due(task.due)]);
		info.push([t('Proje'), esc((sp ? sp.name + ' / ' : '') + (l ? l.name : ''))]);
		if (task.assignees.length) info.push([t('Katılımcılar'), people(task.assignees), 1]);
		if (task.tags.length) info.push([t('Etiketler'), task.tags.map(function (id) { var tg = Y.tag(id); return tg ? '<span class="chip" style="--c:' + esc(tg.color || '#64748b') + '">' + esc(tg.name) + '</span>' : ''; }).join(''), 1]);
		if (!hid.fields) {
			Y.fieldsFor(task.list).forEach(function (f) {
				var v = task.fields ? task.fields[f.id] : null;
				if (v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length) || f.type === 'file') return;
				var node = Y.fieldView(f, v, false);
				var txt = f.type === 'checkbox' ? (v ? '✓' : '') : node ? node.textContent : String(v);
				if (txt) info.push([f.name, esc(txt), f.type === 'textarea' ? 1 : 0]);
			});
		}
		out += '<div class="rp-info">' + info.map(function (r) { return '<div' + (r[2] ? ' class="wide"' : '') + '><div class="k">' + esc(r[0]) + '</div>' + r[1] + '</div>'; }).join('') + '</div>';
		var stats = [];
		if (agenda.length) stats.push([agenda.filter(Y.isDone).length + '/' + agenda.length, t('gündem maddesi görüşüldü')]);
		if (dec.length) stats.push([dec.length, t('karar alındı')]);
		if (subs.length) stats.push([subDone + '/' + subs.length, t('alt görev tamamlandı')]);
		if (stats.length) out += '<div class="rp-stats">' + stats.map(function (x) { return '<div><b>' + esc(x[0]) + '</b><span>' + esc(x[1]) + '</span></div>'; }).join('') + '</div>';
		if (plain(task.description)) {
			var dd = new DOMParser().parseFromString(task.description, 'text/html');
			Y.$$('img[data-file]', dd.body).forEach(function (img) { img.setAttribute('src', Y.fileUrl(img.getAttribute('data-file'))); });
			out += '<h2>' + esc(t('Açıklama')) + '</h2>' + dd.body.innerHTML;
		}
		if (agenda.length) {
			out += '<h2>' + esc(t('Gündem')) + ' <span class="n">' + agenda.length + '</span></h2>';
			agenda.forEach(function (c, i) {
				var files = ((task.childFiles || {})[c.id] || []).filter(function (f) { return f.image; });
				var others = ((task.childFiles || {})[c.id] || []).filter(function (f) { return !f.image; });
				var note = plain(c.description);
				var ok = Y.isDone(c);
				out += '<div class="ag' + (ok ? ' is-done' : '') + '"><span class="ag-no">' + (i + 1) + '</span><div><span class="ag-t">' + esc(c.title) + '</span>' + (ok ? '<span class="ok">✓ ' + esc(t('Görüşüldü')) + '</span>' : '') +
					(note ? '<div class="ag-note">' + esc(note).replace(/\n/g, '<br>') + '</div>' : '') +
					(files.length ? '<div class="thumbs">' + files.map(function (f) { return '<img src="' + esc(Y.fileUrl(f.id, 'thumb')) + '" alt="">'; }).join('') + '</div>' : '') +
					(others.length ? '<div class="muted">📎 ' + esc(others.map(function (f) { return f.name; }).join(', ')) + '</div>' : '') +
					tree(byParent[c.id] || [], 0, []).map(function (x) { var k = Y.isDone(x.t); return '<div class="sub-t ag-sub' + (k ? ' is-done' : '') + '" style="padding-left:' + (x.d * 20) + 'px"><i class="box-c' + (k ? ' on' : '') + '"></i><span class="sub-name">' + esc(x.t.title) + '</span>' + (x.t.assignees.length ? '<span class="muted"> · ' + people0(x.t.assignees) + '</span>' : '') + '</div>'; }).join('') + '</div></div>';
			});
		}
		if (dec.length) {
			out += '<h2>' + esc(t('Kararlar')) + ' <span class="n">' + dec.length + '</span></h2><table class="t"><thead><tr><th style="width:28px">#</th><th>' + esc(t('Karar')) + '</th><th>' + esc(t('Sorumlu')) + '</th><th>' + esc(t('Son tarih')) + '</th><th>' + esc(t('Durum')) + '</th></tr></thead><tbody>';
			dec.forEach(function (c, i) {
				out += '<tr><td class="muted">' + (i + 1) + '</td><td><b>' + esc(c.title) + '</b>' + (plain(c.description) ? '<div class="desc">' + esc(plain(c.description)) + '</div>' : '') + '</td><td class="c">' +
					esc(c.assignees.map(function (id) { return user(id).name; }).join(', ')) + '</td><td class="c">' + due(c.due) + '</td><td class="c">' + pill(Y.status(c)) + '</td></tr>';
				tree(byParent[c.id] || [], 0, []).forEach(function (x) { out += subRow(x, true); });
			});
			out += '</tbody></table>';
		}
		if (subs.length) {
			out += '<h2>' + esc(t('Alt görevler')) + ' <span class="n">' + subDone + '/' + subs.length + '</span></h2><table class="t"><thead><tr><th>' + esc(t('Görev')) + '</th><th>' + esc(t('Atananlar')) + '</th><th>' + esc(t('Bitiş')) + '</th><th>' + esc(t('Durum')) + '</th></tr></thead><tbody>';
			subs.forEach(function (x) { out += subRow(x); });
			out += '</tbody></table>';
		}
		out += '<div class="rp-foot"><span>' + esc(B.brand || '') + '</span><span>' + esc(t('Rapor: {d} · {n}', { d: new Date().toLocaleString(Y.locale), n: S.me.name })) + '</span></div>';
		Y.printHtml(task.title, '<div class="rp" style="--ac:' + esc(ac) + '">' + out + '</div>', REPORT_CSS);
	}

	/* ---------------- Alt görevler tablosu ---------------- */
	var SUB_DEFAULT = ['assignees'];
	function subColDefs(task, o) {
		var defs = Y.BUILTIN().filter(function (d) { return ['list', 'comments'].indexOf(d.key) < 0; });
		if (o && o.kind === 'decision') {
			defs.forEach(function (d) {
				if (d.key === 'assignees') d.name = t('Sorumlu');
				if (d.key === 'due') d.name = t('Son tarih');
			});
		}
		Y.fieldsFor(task.list).forEach(function (f) {
			defs.push({ key: 'f' + f.id, name: f.name, w: f.type === 'textarea' ? '200px' : '140px', field: { key: 'f' + f.id, id: f.id, ids: [f.id], name: f.name, type: f.type, options: f.options } });
		});
		return defs;
	}
	function subOpts(o) {
		return o || { kind: 'task', key: 'sub-cols', title: t('Alt görevler'), head: t('Görev adı'), ph: t('Alt görev ekle…'), def: SUB_DEFAULT };
	}
	function subCols(task, o) {
		o = subOpts(o);
		var want = Y.pref(o.key) || o.def;
		var defs = subColDefs(task, o);
		return want.map(function (k) { return defs.filter(function (d) { return d.key === k; })[0]; }).filter(Boolean);
	}
	/** Alt görevde değişiklik: tabloda hemen görünür, kaydedilince detay tazelenir. */
	function subPatch(taskId) {
		return function (id, patch) {
			var objs = [];
			if (D.task) objs = objs.concat(D.task.children || [], D.task.subtree || []);
			objs.forEach(function (o) {
				if (o.id !== id) return;
				Object.keys(patch).forEach(function (k) {
					if (k === 'fields') { o.fields = Object.assign({}, o.fields, patch.fields); return; }
					o[k] = patch[k];
				});
			});
			var sp = D.subPend || (D.subPend = {});
			sp[id] = (sp[id] || 0) + 1;
			var un = function () { sp[id]--; if (sp[id] <= 0) delete sp[id]; };
			if (D.task && D.task.id === taskId) render(true);
			return Y.patchTask(id, patch, { silent: true }).then(function (r) {
				un();
				if (D.task && D.task.id === taskId) load(taskId, true);
				return r;
			}, function () { un(); if (D.task && D.task.id === taskId) load(taskId, true); });
		};
	}
	function subTable(task, edit, l, o) {
		o = subOpts(o);
		var box = h('.flxg-subtable-wrap' + (o.kind !== 'task' ? '.is-' + o.kind : ''));
		var all = (task.children || []).concat(task.subtree || []);
		var kids = {};
		all.forEach(function (c) { var p = c.parent || task.id; (kids[p] = kids[p] || []).push(c); });
		var direct = (task.children || []).filter(function (c) { return (c.kind || 'task') === o.kind; });
		var cols = subCols(task, o);
		var limit = task.depthLimit || Y.depthLimit(task.list);
		var depth0 = task.depth || 0;
		var canAdd = edit && depth0 < limit;
		var narrow = Y.isMobile();
		var wkey = o.key.replace('-cols', '-w');
		var widths = Object.assign({}, Y.pref(wkey) || {});
		function tplOf() {
			return (widths.title ? widths.title + 'px' : 'minmax(' + (narrow ? 150 : 220) + 'px, 1fr)') + ' ' + cols.map(function (c) {
				return (widths[c.key] || Math.min(parseInt(c.w, 10) || 140, narrow ? 110 : 170)) + 'px';
			}).join(' ') + ' 36px';
		}
		var tpl = tplOf();
		var done = direct.filter(Y.isDone).length;
		box.appendChild(h('h3.flxg-dr-h', o.title, direct.length ? h('span.flxg-gcount', done + '/' + direct.length) : null,
			direct.length ? h('span.flxg-subprog', h('span', { style: { width: Math.round(done / direct.length * 100) + '%' } })) : null));
		if (!direct.length && !canAdd) return box;
		var table = h('.flxg-subtable', { style: { '--subcols': tpl } });
		var pick = h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Sütunları göster / gizle'), onclick: function (e) { colPick(e.currentTarget, task, o); } }, Y.icon('plus', 15));
		/* Sütun genişliği: başlık kenarından sürüklenir, çift tık sıfırlar; hesapta saklanır. */
		function headCell(key, name) {
			var cell = h('span.flxg-subhead', name);
			if (narrow) return cell;
			var grip = h('span.flxg-col-resize', { title: t('Genişliği değiştirmek için sürükleyin, sıfırlamak için çift tıklayın'), 'aria-hidden': 'true' });
			grip.addEventListener('pointerdown', function (e) {
				if (e.button !== 0) return;
				e.preventDefault();
				var sx = e.clientX;
				var sw = cell.getBoundingClientRect().width;
				document.body.classList.add('flxg-col-resizing');
				function mv(ev) {
					widths[key] = Math.round(Math.max(key === 'title' ? 140 : 60, Math.min(700, sw + ev.clientX - sx)));
					table.style.setProperty('--subcols', tplOf());
				}
				function up() {
					document.removeEventListener('pointermove', mv);
					document.removeEventListener('pointerup', up);
					document.body.classList.remove('flxg-col-resizing');
					Y.setPref(wkey, widths);
				}
				document.addEventListener('pointermove', mv);
				document.addEventListener('pointerup', up);
			});
			grip.addEventListener('dblclick', function () {
				delete widths[key];
				table.style.setProperty('--subcols', tplOf());
				Y.setPref(wkey, Object.keys(widths).length ? widths : null);
			});
			cell.appendChild(grip);
			return cell;
		}
		table.appendChild(h('.flxg-subrow.is-head', headCell('title', o.head), cols.map(function (c) { return headCell(c.key, c.name); }), h('span.flxg-subpick', pick)));
		var open = D.subOpen || (D.subOpen = {});
		function addRow(c, depth) {
			var cs = Y.status(c);
			var cl = Y.list(c.list);
			var doneK = cl ? (cl.statuses.filter(function (s) { return s.type === 'done'; })[0] || {}).key : null;
			var openK = cl ? cl.statuses[0].key : null;
			var ch = kids[c.id] || [];
			var isOpen = ch.length && open[c.id] !== false;
			var canEditC = edit && !!c.id;
			var titleCell = h('.flxg-subcell.flxg-subname',
				h('span.flxg-subindent', { style: { '--depth': depth } }),
				ch.length ? h('button.flxg-subcaret' + (isOpen ? '.is-open' : ''), { type: 'button', onclick: function () { open[c.id] = !isOpen; render(true); } }, Y.icon('chevR', 14)) : h('span.flxg-subcaret.is-empty'),
				h('button.flxg-sdot' + (cs.type === 'done' ? '.is-done' : ''), { type: 'button', style: { '--c': cs.color }, disabled: !canEditC, title: cs.label, onclick: function () {
					subPatch(task.id)(c.id, { status: cs.type === 'done' ? openK : doneK });
				} }, cs.type === 'done' ? Y.icon('check', 11) : null),
				h('button.flxg-sub-title', { type: 'button', onclick: function () { if (c.id) Y.openTask(c.id); } }, c.title),
				c.subtasks && !ch.length ? h('span.flxg-meta', { title: t('Alt görev') }, Y.icon('subtask', 13), c.subtasks) : null,
				canEditC && depth0 + depth + 1 < limit ? h('button.flxg-row-addsub', { type: 'button', title: t('Alt görev ekle'), onclick: function () { D.focusSubAdd = true; Y.openTask(c.id); } }, Y.icon('plus', 14)) : null
			);
			var row = h('.flxg-subrow' + (cs.type === 'done' ? '.is-done' : '') + (c.sending || (D.subPend && D.subPend[c.id]) ? '.is-sending' : '') + (depth ? '.is-nested' : ''), titleCell,
				cols.map(function (col) {
					if (!c.id) return h('.flxg-subcell');
					return h('.flxg-subcell', Y.cell(c, col, { patch: subPatch(task.id), after: function () { load(task.id, true); } }));
				}),
				h('span')
			);
			table.appendChild(row);
			if (isOpen) ch.forEach(function (k) { addRow(k, depth + 1); });
		}
		direct.forEach(function (c) { addRow(c, 0); });
		if (canAdd) {
			var sIn = h('input.flxg-input.is-sm', { type: 'text', placeholder: o.ph });
			sIn.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' || !sIn.value.trim()) return;
				var v = sIn.value.trim();
				sIn.value = '';
				// Alt görev hemen listede görünür; sunucu cevabıyla gerçeği yerleşir.
				var tmp = { id: 0, _tmp: 's' + Date.now(), kind: o.kind, title: v, list: task.list, status: (l && l.statuses[0] || {}).key, assignees: [], tags: [], fields: {}, priority: 0, due: '', start: '', sending: true };
				var tid = task.id;
				task.children.push(tmp);
				inflight(tid).children.push(tmp);
				render(true);
				var refocus = function () { var i = Y.$('.flxg-subtable-wrap' + (o.kind !== 'task' ? '.is-' + o.kind : ':not(.is-decision)') + ' .flxg-sub-add input', D.body); if (i) i.focus(); };
				refocus();
				Y.createTask({ list: task.list, parent: tid, title: v, kind: o.kind }).then(function (nt) {
					var isOpenTask = settle(tid, 'children', tmp, nt);
					if (S.tasks[tid] && o.kind === 'task') S.tasks[tid].subtasks = (S.tasks[tid].subtasks || 0) + 1;
					if (S.tasks[tid] && o.kind === 'decision') S.tasks[tid].decisions = (S.tasks[tid].decisions || 0) + 1;
					Y.keepTask(tid);
					Y.emit('tasks');
					if (isOpenTask) { render(true); refocus(); }
				}, function (er) {
					if (settle(tid, 'children', tmp, null)) render(true);
					Y.fail(er);
				});
			});
			table.appendChild(h('.flxg-subrow.flxg-sub-add', h('.flxg-subcell.flxg-subname', Y.icon('plus', 14), sIn)));
		}
		box.appendChild(h('.flxg-subtable-scroll', table));
		return box;
	}
	function colPick(anchor, task, o) {
		o = subOpts(o);
		var want = (Y.pref(o.key) || o.def).slice();
		var box = h('.flxg-fieldspop');
		function draw() {
			Y.clear(box);
			box.appendChild(h('.flxg-menu-head', o.kind === 'decision' ? t('Karar sütunları') : t('Alt görev sütunları')));
			subColDefs(task, o).forEach(function (d) {
				var on = want.indexOf(d.key) > -1;
				box.appendChild(h('label.flxg-switch-row', h('input', { type: 'checkbox', checked: on, onchange: function () {
					want = on ? want.filter(function (k) { return k !== d.key; }) : want.concat([d.key]);
					Y.setPref(o.key, want);
					draw();
					render(true);
				} }), d.field ? h('span.flxg-ftype', Y.fieldTypeIcon(d.field.type)) : null, h('span', d.name)));
			});
		}
		draw();
		Y.pop(anchor, box, { alignRight: true, noFocus: true });
	}

	function fieldRow(task, f, edit) {
		var v = task.fields ? task.fields[f.id] : null;
		var val;
		if (f.type === 'file') {
			var ids = Array.isArray(v) ? v : [];
			var fl = task.attachments.filter(function (x) { return ids.indexOf(x.id) > -1; });
			val = h('.flxg-field-files', fl.map(function (x) { return fileChip(x, task, edit, function () { save({ fields: (function () { var o = {}; o[f.id] = ids.filter(function (z) { return z !== x.id; }); return o; })() }); }); }),
				edit ? h('label.flxg-chip-btn', Y.icon('upload', 14), t('Dosya ekle'), h('input', { type: 'file', multiple: true, hidden: true, onchange: function (e) {
					var list = Array.prototype.slice.call(e.target.files);
					Promise.all(list.map(function (file) { return Y.upload(file, { task: task.id, context: 'pending' }); })).then(function (up) {
						var o = {};
						o[f.id] = ids.concat(up.map(function (u) { return u.id; }));
						return save({ fields: o });
					}).catch(Y.fail);
				} })) : null);
			return h('.flxg-attr', h('.flxg-attr-label', h('span.flxg-ftype', Y.fieldTypeIcon(f.type)), h('span', f.name)), h('.flxg-attr-val', { dataset: { k: 'f' + f.id } }, val));
		}
		if (f.type === 'checkbox') {
			val = h('label.flxg-check-inline', h('input', { type: 'checkbox', checked: !!v, disabled: !edit, onchange: function (e) { var o = {}; o[f.id] = e.target.checked; save({ fields: o }); } }));
			return h('.flxg-attr', h('.flxg-attr-label', h('span.flxg-ftype', Y.fieldTypeIcon(f.type)), h('span', f.name)), h('.flxg-attr-val', { dataset: { k: 'f' + f.id } }, val));
		}
		var view = Y.fieldView(f, v, false);
		return attrRowField(f, view || h('span.flxg-muted', t('Boş')), edit ? function (a) {
			Y.editFieldPop(a, task, f, function () { load(task.id, true); });
		} : null);
	}
	function attrRowField(f, value, onClick) {
		var v = h('.flxg-attr-val' + (onClick ? '.is-edit' : ''), { dataset: { k: 'f' + f.id } }, value);
		if (onClick) { v.tabIndex = 0; v.addEventListener('click', function (e) { if (e.target.closest('a')) return; onClick(v); }); }
		return h('.flxg-attr', { title: f.options && f.options.hint ? f.options.hint : null }, h('.flxg-attr-label', h('span.flxg-ftype', Y.fieldTypeIcon(f.type)), h('span', f.name)), v);
	}

	/* ---------------- Açıklama düzenleyici ---------------- */
	function descEditor(task, edit) {
		var wrap = h('.flxg-desc');
		var ed = h('.flxg-desc-ed.flxg-prose', { contenteditable: edit ? 'true' : 'false', 'data-ph': edit ? t('Açıklama ekleyin… (liste, kontrol listesi, bağlantı)') : '' });
		ed.innerHTML = task.description || '';
		// Açıklamadaki görseller dosya kimliğiyle saklanır, adres burada (oturum anahtarıyla) verilir.
		Y.$$('img[data-file]', ed).forEach(function (img) { img.src = Y.fileUrl(img.getAttribute('data-file')); img.alt = img.alt || ''; });
		function clean() {
			var c = ed.cloneNode(true);
			Y.$$('img', c).forEach(function (img) {
				if (!img.getAttribute('data-file')) { img.remove(); return; }
				img.removeAttribute('src');
				img.removeAttribute('class');
				img.removeAttribute('style');
			});
			return c.innerHTML.trim();
		}
		var original = clean();
		function cmd(c, v) { ed.focus(); document.execCommand(c, false, v || null); }
		var tb = h('.flxg-desc-tb',
			[['bold', 'bold', t('Kalın')], ['italic', 'italic', t('İtalik')], ['heading', 'formatBlock:h3', t('Başlık')], ['ul', 'insertUnorderedList', t('Madde listesi')], ['ol', 'insertOrderedList', t('Numaralı liste')], ['checklist', 'checklist', t('Kontrol listesi')], ['quote', 'formatBlock:blockquote', t('Alıntı')], ['code', 'formatBlock:pre', t('Kod')], ['link', 'link', t('Bağlantı')]].map(function (b) {
				return h('button.flxg-icon-btn.is-sm', { type: 'button', title: b[2], onmousedown: function (e) {
					e.preventDefault();
					if (b[1] === 'link') {
						var url = window.prompt(t('Bağlantı adresi'), 'https://');
						if (url) cmd('createLink', url);
						return;
					}
					if (b[1] === 'checklist') {
						cmd('insertUnorderedList');
						var sel = window.getSelection();
						var node = sel && sel.anchorNode;
						var ul = node && (node.nodeType === 1 ? node : node.parentElement).closest('ul');
						if (ul) {
							ul.setAttribute('data-checklist', '1');
							Y.$$('li', ul).forEach(function (li) { if (!li.hasAttribute('data-checked')) li.setAttribute('data-checked', '0'); });
						}
						return;
					}
					if (b[1].indexOf('formatBlock:') === 0) {
						var tag = b[1].split(':')[1];
						var cur = document.queryCommandValue('formatBlock');
						cmd('formatBlock', cur && cur.toLowerCase() === tag ? 'p' : tag);
						return;
					}
					cmd(b[1]);
				} }, Y.icon(b[0], 15));
			})
		);
		// Kontrol listesi kutucukları.
		ed.addEventListener('click', function (e) {
			var li = e.target.closest('ul[data-checklist] > li');
			if (!li) return;
			var r = li.getBoundingClientRect();
			if (e.clientX - r.left > 26) return;
			if (!edit) return;
			li.setAttribute('data-checked', li.getAttribute('data-checked') === '1' ? '0' : '1');
			commit();
		});
		ed.addEventListener('focus', function () { D.editing = true; wrap.classList.add('is-focus'); });
		ed.addEventListener('blur', function () { D.editing = false; wrap.classList.remove('is-focus'); commit(); });
		/** Görseli imlecin olduğu yere koyar, yüklenince kaydeder. Görsel olmayan dosyalar eklere gider. */
		function insertFiles(files) {
			var imgs = files.filter(function (f) { return /^image\//.test(f.type); });
			var others = files.filter(function (f) { return !/^image\//.test(f.type); });
			if (others.length) uploadTaskFiles(task, others);
			imgs.forEach(function (file) {
				var img = h('img.is-uploading', { src: URL.createObjectURL(file), alt: file.name || '' });
				var sel = window.getSelection();
				if (sel && sel.rangeCount && ed.contains(sel.anchorNode)) {
					var rg = sel.getRangeAt(0);
					rg.deleteContents();
					rg.insertNode(img);
					rg.setStartAfter(img);
					rg.collapse(true);
					sel.removeAllRanges();
					sel.addRange(rg);
				} else {
					ed.appendChild(img);
				}
				uploadTaskFiles(task, [file], false, true).then(function (up) {
					var f = up[0];
					if (!f) { img.remove(); return; }
					img.setAttribute('data-file', f.id);
					img.classList.remove('is-uploading');
					// Bu arada başka göreve geçildiyse görsel yalnız eklerde kalır.
					if (D.task && D.task.id === task.id) commit();
				});
			});
		}
		if (edit) {
			ed.addEventListener('dragover', function (e) { if (hasFiles(e)) { e.preventDefault(); e.stopPropagation(); } });
			ed.addEventListener('drop', function (e) {
				if (!hasFiles(e)) return;
				e.preventDefault();
				e.stopPropagation();
				if (document.caretRangeFromPoint) {
					var r = document.caretRangeFromPoint(e.clientX, e.clientY);
					if (r && ed.contains(r.startContainer)) { var sl = window.getSelection(); sl.removeAllRanges(); sl.addRange(r); }
				}
				insertFiles(Array.prototype.slice.call(e.dataTransfer.files || []));
			});
		}
		ed.addEventListener('paste', function (e) {
			var pf = Array.prototype.slice.call((e.clipboardData && e.clipboardData.files) || []);
			if (pf.length && edit) {
				e.preventDefault();
				insertFiles(pf.map(function (f, i) { return f.name && f.name !== 'image.png' ? f : new File([f], 'gorsel-' + Date.now() + '-' + i + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }); }));
				return;
			}
			// Biçimli yapıştırmada yalnız metin.
			var html = e.clipboardData && e.clipboardData.getData('text/html');
			if (html) {
				e.preventDefault();
				var text = e.clipboardData.getData('text/plain');
				document.execCommand('insertText', false, text);
			}
		});
		function commit() {
			// Yeni maddelere kontrol kutusu.
			Y.$$('ul[data-checklist] > li', ed).forEach(function (li) { if (!li.hasAttribute('data-checked')) li.setAttribute('data-checked', '0'); });
			var html = clean();
			if (html === '<br>' || html === '<p><br></p>') html = '';
			if (html === original) return;
			original = html;
			save({ description: html });
		}
		if (edit) wrap.appendChild(tb);
		wrap.appendChild(ed);
		return wrap;
	}

	/* ---------------- Ekler ---------------- */
	function fileChip(f, task, edit, onRemove) {
		var kind = Y.fileKind(f.mime, f.name);
		return h('.flxg-file' + (f.image ? '.is-image' : ''),
			f.image ? h('button.flxg-file-thumb', { type: 'button', onclick: function () {
				var imgs = task.attachments.filter(function (x) { return x.image; });
				Y.lightbox(imgs, imgs.indexOf(f));
			} }, h('img', { src: Y.fileUrl(f.id, 'thumb'), alt: f.name, loading: 'lazy' }))
				: h('a.flxg-file-icon.is-' + kind, { href: Y.fileUrl(f.id), target: '_blank', rel: 'noopener' }, Y.icon('file', 22), h('span', (f.name.split('.').pop() || '').slice(0, 4).toUpperCase())),
			h('.flxg-file-info',
				h('a.flxg-file-name', { href: Y.fileUrl(f.id, '', true), title: f.name }, f.name),
				h('span.flxg-muted', Y.bytes(f.size))
			),
			edit || f.user === S.me.id ? h('button.flxg-icon-btn.is-sm.flxg-file-more', { type: 'button', onclick: function (e) {
				Y.menu(e.currentTarget, [
					{ label: t('İndir'), icon: 'download', onClick: function () { window.open(Y.fileUrl(f.id, '', true)); } },
					f.image && canEdit() && task.cover !== f.id ? { label: t('Kapak görseli yap'), icon: 'image', onClick: function () { save({ cover: f.id }); } } : null,
					f.image && canEdit() && task.cover === f.id ? { label: t('Kapaktan kaldır'), icon: 'image', onClick: function () { save({ cover: 0 }); } } : null,
					{ sep: true },
					{ label: t('Sil'), icon: 'trash', danger: true, onClick: function () {
						Y.confirm(t('Dosyayı sil'), f.name, t('Sil'), true).then(function (ok) {
							if (!ok) return;
							if (onRemove) { onRemove(); }
							Y.api('DELETE', 'files/' + f.id).then(function () { load(task.id, true); }, Y.fail);
						});
					} }
				]);
			} }, Y.icon('dots', 16)) : null
		);
	}

	function attachments(task, files, edit) {
		var box = h('.flxg-attach');
		var grid = h('.flxg-files');
		files.forEach(function (f) { grid.appendChild(fileChip(f, task, edit)); });
		(D.uploads[task.id] || []).forEach(function (u) { grid.appendChild(upTile(u)); });
		box.appendChild(grid);
		if (edit) {
			var input = h('input', { type: 'file', multiple: true, hidden: true, onchange: function (e) { uploadTaskFiles(task, Array.prototype.slice.call(e.target.files)); e.target.value = ''; } });
			var drop = h('label.flxg-dropzone', input, Y.icon('upload', 18), h('span', t('Dosyaları buraya sürükleyin ya da seçin')), h('span.flxg-muted', t('En fazla {s}', { s: Y.bytes(S.config.maxUpload) })));
			['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
			['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
			drop.addEventListener('drop', function (e) { e.stopPropagation(); uploadTaskFiles(task, Array.prototype.slice.call(e.dataTransfer.files || [])); });
			box.appendChild(drop);
		}
		return box;
	}

	/** Yüklenen dosya: hemen önizlemeli kutu olarak görünür, ilerleme çubuğu dolar. */
	function upTile(u) {
		return h('.flxg-file.is-uploading' + (u.error ? '.is-error' : ''), { 'data-up': u.key },
			u.preview ? h('span.flxg-file-thumb', h('img', { src: u.preview, alt: '' })) : h('span.flxg-file-icon', Y.icon('file', 22)),
			h('.flxg-file-info',
				h('span.flxg-file-name', u.name),
				u.error ? h('span.flxg-pfile-err', u.error) : h('span.flxg-pfile-bar', h('span', { style: { width: Math.round(u.progress * 100) + '%' } }))
			)
		);
	}
	/** Görevin eklerine yükler. Dönen söz, yüklenen dosyaların listesiyle çözülür. */
	function uploadTaskFiles(task, list, asCover, noReload) {
		list = (list || []).filter(function (file) {
			if (file.size > S.config.maxUpload) { Y.toast(t('{n} çok büyük (en fazla {s}).', { n: file.name, s: Y.bytes(S.config.maxUpload) }), { error: true }); return false; }
			return true;
		});
		if (!list.length) return Promise.resolve([]);
		var tid = task.id;
		var ups = D.uploads[tid] || (D.uploads[tid] = []);
		var mine = list.map(function (file, i) {
			var u = { key: 'u' + Date.now() + '-' + i, name: file.name || t('Dosya'), progress: 0, preview: /^image\//.test(file.type) ? URL.createObjectURL(file) : '' };
			ups.push(u);
			return u;
		});
		if (!noReload && D.task && D.task.id === tid) render(true);
		return Promise.all(list.map(function (file, i) {
			var u = mine[i];
			return Y.upload(file, { task: tid, context: 'task', cover: asCover ? 1 : '' }, function (p) {
				u.progress = p;
				var bar = D.body && D.body.querySelector('[data-up="' + u.key + '"] .flxg-pfile-bar > span');
				if (bar) bar.style.width = Math.round(p * 100) + '%';
			}).then(function (f) {
				D.uploads[tid] = (D.uploads[tid] || []).filter(function (x) { return x !== u; });
				var o = D.task && D.task.id === tid ? D.task : null;
				if (o && o.attachments && !o.attachments.some(function (x) { return x.id === f.id; })) o.attachments.push(f);
				return f;
			}, function (e) {
				D.uploads[tid] = (D.uploads[tid] || []).filter(function (x) { return x !== u; });
				Y.fail(e);
				return null;
			});
		})).then(function (res) {
			if (!noReload && D.task && D.task.id === tid) load(tid, true);
			return res.filter(Boolean);
		});
	}

	function coverPick(anchor) {
		var task = D.task;
		var imgs = task.attachments.filter(function (f) { return f.image; });
		var grid = h('.flxg-cover-grid');
		imgs.forEach(function (f) {
			grid.appendChild(h('button.flxg-cover-opt' + (task.cover === f.id ? '.is-on' : ''), { type: 'button', onclick: function () { Y.closePop(); save({ cover: f.id }); } }, h('img', { src: Y.fileUrl(f.id, 'thumb'), alt: f.name, loading: 'lazy' })));
		});
		var up = h('label.flxg-btn.is-sm', Y.icon('upload', 14), t('Yeni görsel yükle'), h('input', { type: 'file', accept: 'image/*', hidden: true, onchange: function (e) { Y.closePop(); uploadTaskFiles(task, Array.prototype.slice.call(e.target.files).slice(0, 1), true); } }));
		Y.pop(anchor, h('.flxg-pick', h('.flxg-menu-head', t('Kapak görseli')), imgs.length ? grid : h('.flxg-empty-sm', t('Görevde henüz görsel yok.')), h('.flxg-row-btns', up)));
	}
	Y.coverPick = coverPick;

	/* ---------------- Yorumlar ve etkinlik ---------------- */
	function activityText(a) {
		var d = a.data || {};
		var who = S.users[a.user] ? S.users[a.user].name : (a.user ? t('Silinmiş kullanıcı') : t('Sistem'));
		var l = Y.list(D.task.list);
		function stLabel(k) { var s = l && l.statuses.filter(function (x) { return x.key === k; })[0]; return s ? s.label : k; }
		function stNode(k) { var s = l && l.statuses.filter(function (x) { return x.key === k; })[0]; return h('span.flxg-status.is-sm', { style: { '--c': s ? s.color : '#999' } }, stLabel(k)); }
		var uName = function (id) { return S.users[id] ? S.users[id].name : t('Silinmiş kullanıcı'); };
		switch (a.action) {
			case 'created': return [h('b', who), ' ', t('görevi oluşturdu')];
			case 'status': return [h('b', who), ' ', t('durumu değiştirdi:'), ' ', stNode(d.from), ' → ', stNode(d.to)];
			case 'title': return [h('b', who), ' ', t('adı değiştirdi:'), ' ', h('i', d.to)];
			case 'description': return [h('b', who), ' ', t('açıklamayı güncelledi')];
			case 'priority': return [h('b', who), ' ', d.to ? t('önceliği "{p}" yaptı', { p: (Y.priority(d.to) || {}).label || '' }) : t('önceliği kaldırdı')];
			case 'due': return [h('b', who), ' ', d.to ? t('bitiş tarihini {d} yaptı', { d: Y.fmtDue(d.to, { full: true }) }) : t('bitiş tarihini kaldırdı')];
			case 'start': return [h('b', who), ' ', d.to ? t('başlangıç tarihini {d} yaptı', { d: Y.fmtDue(d.to, { full: true }) }) : t('başlangıç tarihini kaldırdı')];
			case 'assign': return [h('b', who), ' ', d.user === a.user ? t('görevi üstlendi') : t('{n} kişisini atadı', { n: uName(d.user) })];
			case 'unassign': return [h('b', who), ' ', t('{n} atamasını kaldırdı', { n: uName(d.user) })];
			case 'tag': return [h('b', who), ' ', t('etiket ekledi:'), ' ', Y.tagChips([d.tag])];
			case 'untag': return [h('b', who), ' ', t('etiketi kaldırdı:'), ' ', Y.tagChips([d.tag])];
			case 'field': return [h('b', who), ' ', t('"{f}" alanını güncelledi', { f: d.name || '' })];
			case 'moved': var fl = Y.list(d.from); return [h('b', who), ' ', t('görevi taşıdı:'), ' ', fl ? fl.name : '…', ' → ', (Y.list(d.to) || {}).name || '…'];
			case 'cover': return [h('b', who), ' ', d.to ? t('kapak görselini değiştirdi') : t('kapak görselini kaldırdı')];
			case 'attach': return [h('b', who), ' ', t('dosya ekledi:'), ' ', h('i', d.name || '')];
			case 'detach': return [h('b', who), ' ', t('dosya sildi:'), ' ', h('i', d.name || '')];
			case 'archived': return [h('b', who), ' ', t('görevi arşivledi')];
			case 'unarchived': return [h('b', who), ' ', t('görevi arşivden çıkardı')];
		}
		return [h('b', who), ' ', a.action];
	}

	function commentNode(c, task) {
		var u = S.users[c.user];
		var mine = c.user === S.me.id;
		var bodyEl = h('.flxg-cmt-body.flxg-prose', { html: Y.richText(c.body) });
		var node = h('.flxg-cmt' + (mine ? '.is-mine' : '') + (c.sending ? '.is-sending' : ''), { id: 'flxg-c' + c.id },
			Y.avatar(u, 30),
			h('.flxg-cmt-main',
				h('.flxg-cmt-head',
					h('b', u ? u.name : t('Silinmiş kullanıcı')),
					h('span.flxg-muted', { title: Y.fullTime(c.created) }, c.sending ? t('Gönderiliyor…') : Y.ago(c.created)),
					c.edited ? h('span.flxg-muted', '· ' + t('düzenlendi')) : null,
					h('span.flxg-grow'),
					!c.sending && (mine || Y.rank(role()) >= 4) ? h('button.flxg-icon-btn.is-sm', { type: 'button', onclick: function (e) {
						Y.menu(e.currentTarget, [
							mine ? { label: t('Düzenle'), icon: 'edit', onClick: function () { editComment(c, bodyEl); } } : null,
							{ label: t('Sil'), icon: 'trash', danger: true, onClick: function () {
								Y.confirm(t('Yorumu sil'), t('Yorum ve ekleri silinecek.'), t('Sil'), true).then(function (ok) {
									if (ok) Y.api('DELETE', 'comments/' + c.id).then(function () { load(task.id, true); bumpCount(task.id, -1); }, Y.fail);
								});
							} }
						]);
					} }, Y.icon('dots', 15)) : null
				),
				c.body ? bodyEl : null,
				c.files && c.files.length ? h('.flxg-cmt-files', c.files.map(function (f) {
					if (f.image) {
						return h('button.flxg-cmt-img', { type: 'button', onclick: function () { var imgs = c.files.filter(function (x) { return x.image; }); Y.lightbox(imgs, imgs.indexOf(f)); } }, h('img', { src: Y.fileUrl(f.id, 'thumb'), alt: f.name, loading: 'lazy' }));
					}
					return h('a.flxg-cmt-file', { href: Y.fileUrl(f.id, '', true) }, Y.icon('file', 16), h('span', f.name), h('span.flxg-muted', Y.bytes(f.size)));
				})) : null
			)
		);
		return node;
	}

	function editComment(c, bodyEl) {
		var ta = h('textarea.flxg-input', { rows: 3 });
		ta.value = c.body.replace(/@\{(\d+)\}/g, function (_, id) { return '@{' + id + '}'; });
		Y.autosize(ta);
		var box = h('.flxg-cmt-edit', ta, h('.flxg-row-btns',
			h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { box.replaceWith(bodyEl); } }, t('Vazgeç')),
			h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: function () {
				Y.api('PATCH', 'comments/' + c.id, { body: ta.value }).then(function () { load(D.task.id, true); }, Y.fail);
			} }, t('Kaydet'))
		));
		bodyEl.replaceWith(box);
		ta.focus();
	}

	function bumpCount(id, n) {
		if (S.tasks[id]) { S.tasks[id].comments = Math.max(0, (S.tasks[id].comments || 0) + n); Y.emit('tasks'); }
	}

	/** Yorum/etkinlik sütunu açık mı kapalı mı: son seçim saklanır, sonraki görevlerde de öyle açılır. */
	function setSideOpen(v) {
		Y.store('task-side', v ? null : '0');
		render(true);
	}
	function sideRail(task) {
		var n = (task.comments_list || []).length;
		return h('button.flxg-dr-rail', { type: 'button', title: t('Yorumları ve etkinliği göster'), onclick: function () { setSideOpen(true); } },
			Y.icon('chevL', 16), Y.icon('msg', 17), n ? h('span.flxg-badge', n) : null, h('span.flxg-dr-rail-label', t('Yorumlar ve etkinlik')));
	}

	function renderSide(task) {
		var side = h('.flxg-dr-side');
		var head = h('.flxg-side-head',
			h('h3', t('Yorumlar ve etkinlik')),
			h('label.flxg-switch-sm', h('input', { type: 'checkbox', checked: D.showActivity, onchange: function (e) { D.showActivity = e.target.checked; Y.store('task-act', e.target.checked ? null : '0'); render(true); } }), h('span', t('Etkinliği göster'))),
			Y.isMobile() ? null : h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Yorumları ve etkinliği gizle'), onclick: function () { setSideOpen(false); } }, Y.icon('chevR', 16))
		);
		var stream = h('.flxg-stream');
		var items = task.comments_list.map(function (c) { return { time: c.created, c: c }; });
		if (D.showActivity) items = items.concat(task.activity.map(function (a) { return { time: a.created, a: a }; }));
		items.sort(function (a, b) { return a.time < b.time ? -1 : a.time > b.time ? 1 : 0; });
		if (!items.length) stream.appendChild(h('.flxg-empty-sm', t('Henüz yorum yok. İlk yorumu siz yazın.')));
		items.forEach(function (it) {
			if (it.c) stream.appendChild(commentNode(it.c, task));
			else stream.appendChild(h('.flxg-act', h('span.flxg-act-dot'), h('span.flxg-act-text', activityText(it.a)), h('span.flxg-muted', { title: Y.fullTime(it.a.created) }, Y.ago(it.a.created))));
		});
		side.appendChild(head);
		side.appendChild(stream);
		D.stream = stream;
		if (canComment()) side.appendChild(composer(task, side));
		else side.appendChild(h('.flxg-readonly.is-block', Y.icon('lock', 14), t('Bu listede yorum yazma yetkiniz yok.')));
		setTimeout(function () { stream.scrollTop = stream.scrollHeight; }, 0);
		return side;
	}

	/* ---------------- Yorum yazma kutusu ---------------- */
	function composer(task, side) {
		var ta = h('textarea.flxg-composer-input', { rows: 2, placeholder: t('Yorum yazın… @ ile birinden bahsedin, dosya sürükleyin') });
		if (D.draftFor === task.id && D.draft) ta.value = D.draft;
		D.composer = ta;
		Y.autosize(ta);
		var pend = h('.flxg-pending');
		var fileIn = h('input', { type: 'file', multiple: true, hidden: true, onchange: function (e) { addFiles(Array.prototype.slice.call(e.target.files)); e.target.value = ''; } });
		var sendBtn = h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: send }, Y.icon('send', 14), h('span', t('Gönder')));
		var box = h('.flxg-composer',
			pend,
			ta,
			h('.flxg-composer-bar',
				h('label.flxg-icon-btn', { title: t('Dosya ekle') }, Y.icon('clip', 17), fileIn),
				h('button.flxg-icon-btn', { type: 'button', title: t('Birinden bahset'), onclick: function () { insertAt('@'); mentionCheck(); } }, Y.icon('at', 17)),
				h('span.flxg-grow'),
				h('span.flxg-muted.flxg-hint', t('Ctrl+Enter ile gönder')),
				sendBtn
			)
		);
		function drawPending() {
			Y.clear(pend);
			D.pending.forEach(function (p) {
				pend.appendChild(h('.flxg-pfile' + (p.error ? '.is-error' : ''),
					p.preview ? h('img', { src: p.preview, alt: '' }) : Y.icon('file', 18),
					h('span.flxg-pfile-name', p.file.name),
					p.id || p.error ? null : h('span.flxg-pfile-bar', h('span', { style: { width: Math.round((p.progress || 0) * 100) + '%' } })),
					p.error ? h('span.flxg-pfile-err', p.error) : null,
					h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kaldır'), onclick: function () {
						D.pending = D.pending.filter(function (x) { return x !== p; });
						if (p.id) Y.api('DELETE', 'files/' + p.id).catch(function () {});
						drawPending();
					} }, Y.icon('x', 14))
				));
			});
			sendBtn.disabled = D.pending.some(function (p) { return !p.id && !p.error; });
		}
		function addFiles(list) {
			list.forEach(function (file) {
				if (file.size > S.config.maxUpload) { Y.toast(t('{n} çok büyük (en fazla {s}).', { n: file.name, s: Y.bytes(S.config.maxUpload) }), { error: true }); return; }
				var p = { file: file, progress: 0 };
				if (/^image\//.test(file.type)) p.preview = URL.createObjectURL(file);
				D.pending.push(p);
				// Çekmece bu arada yeniden çizilebilir; her zaman güncel kutuyu tazele.
				var redraw = function () { if (D.drawPending) D.drawPending(); };
				Y.upload(file, { task: task.id, context: 'pending' }, function (x) { p.progress = x; redraw(); }).then(function (f) { p.id = f.id; redraw(); }, function (e) { p.error = e.message; redraw(); });
			});
			drawPending();
		}
		D.addFiles = addFiles;
		D.drawPending = drawPending;
		drawPending();
		// Sürükle-bırak: tüm yorum sütunu.
		var over = h('.flxg-drop-over', Y.icon('upload', 28), h('span', t('Yoruma eklemek için bırakın')));
		var depth = 0;
		side.addEventListener('dragenter', function (e) { if (!hasFiles(e)) return; e.preventDefault(); depth++; side.classList.add('is-dropping'); });
		side.addEventListener('dragover', function (e) { if (hasFiles(e)) e.preventDefault(); });
		side.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) side.classList.remove('is-dropping'); });
		side.addEventListener('drop', function (e) {
			if (!hasFiles(e)) return;
			e.preventDefault();
			depth = 0;
			side.classList.remove('is-dropping');
			addFiles(Array.prototype.slice.call(e.dataTransfer.files || []));
		});
		side.appendChild(over);
		ta.addEventListener('paste', function (e) {
			var files = Array.prototype.slice.call((e.clipboardData && e.clipboardData.files) || []);
			if (files.length) { e.preventDefault(); addFiles(files.map(function (f, i) { return f.name && f.name !== 'image.png' ? f : new File([f], 'ekran-' + Date.now() + '-' + i + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }); })); }
		});
		ta.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); }
		});
		ta.addEventListener('input', function () { D.draft = ta.value; D.draftFor = task.id; mentionCheck(); });
		ta.addEventListener('focus', function () { D.editing = true; });
		ta.addEventListener('blur', function () { D.editing = false; });
		function insertAt(s) {
			var p = ta.selectionStart;
			ta.value = ta.value.slice(0, p) + s + ta.value.slice(ta.selectionEnd);
			ta.focus();
			ta.selectionStart = ta.selectionEnd = p + s.length;
		}
		function mentionCheck() {
			var before = ta.value.slice(0, ta.selectionStart);
			var m = /(^|\s)@([^\s@]{0,30})$/.exec(before);
			if (!m) { if (Y.isPopOpen() && D.mentionOpen) { Y.closePop(); D.mentionOpen = false; } return; }
			var q = Y.norm(m[2]);
			var users = S.userList.filter(function (u) { return !q || Y.norm(u.name).indexOf(q) > -1; }).slice(0, 8);
			if (!users.length) { Y.closePop(); return; }
			var list = h('.flxg-pick-list');
			users.forEach(function (u, i) {
				list.appendChild(h('button.flxg-pick-item' + (i === 0 ? '.is-active' : ''), { type: 'button', onmousedown: function (e) { e.preventDefault(); pickMention(u, m[2].length); } }, Y.avatar(u, 22), h('span', u.name)));
			});
			D.mentionOpen = true;
			Y.pop(ta, h('.flxg-pick.is-mention', list), { noFocus: true, inline: true, onClose: function () { D.mentionOpen = false; } });
			D.mentionUsers = users;
			D.mentionLen = m[2].length;
		}
		ta.addEventListener('keydown', function (e) {
			if (!D.mentionOpen) return;
			if (e.key === 'Enter' || e.key === 'Tab') {
				e.preventDefault();
				var act = Y.$('.flxg-pop .flxg-pick-item.is-active');
				var idx = act ? Y.$$('.flxg-pop .flxg-pick-item').indexOf(act) : 0;
				pickMention(D.mentionUsers[idx], D.mentionLen);
			} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				var items = Y.$$('.flxg-pop .flxg-pick-item');
				var i = items.indexOf(Y.$('.flxg-pop .flxg-pick-item.is-active'));
				items.forEach(function (x) { x.classList.remove('is-active'); });
				i = (i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
				items[i].classList.add('is-active');
			}
		});
		function pickMention(u, len) {
			var p = ta.selectionStart;
			var start = p - len - 1;
			var label = '@' + u.name + ' ';
			ta.value = ta.value.slice(0, start) + label + ta.value.slice(p);
			ta.selectionStart = ta.selectionEnd = start + label.length;
			D.mentions.push({ name: u.name, id: u.id });
			Y.closePop();
			D.mentionOpen = false;
			ta.focus();
		}
		function send() {
			var body = ta.value;
			// Bahsedilen adları @{id} biçimine çevir (uzun adlar önce).
			D.mentions.slice().sort(function (a, b) { return b.name.length - a.name.length; }).forEach(function (m) {
				body = body.split('@' + m.name).join('@{' + m.id + '}');
			});
			var files = D.pending.filter(function (p) { return p.id; }).map(function (p) { return p.id; });
			if (!body.trim() && !files.length) return;
			// Yorum hemen görünür; sunucu cevabı gelince gerçeğiyle değiştirilir.
			var tid = task.id;
			var tmp = {
				id: 'tmp' + Date.now(),
				_tmp: 'c' + Date.now(),
				user: S.me.id,
				body: body,
				created: new Date().toISOString(),
				files: D.pending.filter(function (p) { return p.id; }).map(function (p) { return { id: p.id, name: p.file.name, size: p.file.size, mime: p.file.type, image: /^image\//.test(p.file.type) }; }),
				sending: true
			};
			var draft = { text: ta.value, pending: D.pending, mentions: D.mentions };
			task.comments_list.push(tmp);
			inflight(tid).comments_list.push(tmp);
			ta.value = '';
			D.draft = '';
			D.pending = [];
			D.mentions = [];
			bumpCount(task.id, 1);
			Y.keepTask(task.id);
			render(true);
			if (D.stream) D.stream.scrollTop = D.stream.scrollHeight;
			if (D.composer && !Y.isMobile()) D.composer.focus();
			Y.api('POST', 'tasks/' + tid + '/comments', { body: body, files: files }).then(function (c) {
				if (!c || !c.id) throw new Error(t('Yorum kaydedilemedi.'));
				if (settle(tid, 'comments_list', tmp, c)) {
					render(true);
					if (D.remoteStale && !D.pending.length && !(D.composer && D.composer.value.trim())) load(tid, true);
				}
			}).catch(function (e) {
				var open = settle(tid, 'comments_list', tmp, null);
				bumpCount(tid, -1);
				// Yazılanı geri koy ki kaybolmasın.
				D.draft = draft.text;
				D.draftFor = tid;
				if (open) {
					D.pending = draft.pending;
					D.mentions = draft.mentions;
					render(true);
				}
				Y.fail(e);
			});
		}
		return box;
	}
	function hasFiles(e) {
		return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') > -1;
	}

	/* =====================================================================
	 * Yapay zekâ (görev için)
	 * ================================================================== */
	/* İçerik alanında satır içi yapay zekâ paneli. Durum D.ai'de; çekmece yeniden çizilse de panel ve sonuç kalır. */
	var AI_PRESETS = [
		{ action: 'description', label: t('Açıklama yaz / geliştir'), hint: '' },
		{ action: 'description', label: t('Kısalt'), hint: 'Make it shorter and clearer, keep every important point.' },
		{ action: 'description', label: t('Kontrol listesine çevir'), hint: 'Turn it into a checklist: <ul data-checklist="1"> with <li data-checked="0"> items.' },
		{ action: 'description', label: t('Yazımı düzelt'), hint: 'Only fix spelling, grammar and punctuation. Keep the meaning, wording and structure.' },
		{ action: 'description', label: t('Daha detaylı yaz'), hint: 'Make it more detailed: goal, steps and done criteria.' },
		{ action: 'title', label: t('Görev adını iyileştir'), hint: '' },
		{ action: 'subtasks', label: t('Alt görevlere böl'), hint: '' },
		{ action: 'summary', label: t('Yorumları özetle'), hint: '' }
	];
	function drawAI(box, task) {
		// Çekmece bu arada yeniden çizildiyse güncel kutuya ve güncel göreve çiz.
		if (!box.isConnected && D.aiBox && D.aiBox.isConnected) box = D.aiBox;
		if (D.task && D.task.id === task.id) task = D.task;
		Y.clear(box);
		var st = D.ai;
		if (!st || st.task !== task.id) return;
		var edit = canEdit();
		var panel = h('.flxg-ai-inline');
		box.appendChild(panel);
		var close = h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kapat'), onclick: function () { D.ai = null; drawAI(box, task); var b = Y.$('.flxg-ai-btn', D.body); if (b) b.classList.remove('is-on'); } }, Y.icon('x', 14));
		// Serbest yönerge kutusu: her durumda altta, akış bozulmadan devam edilir.
		var ask = h('input.flxg-input.is-sm', { type: 'text', placeholder: st.result ? t('Değiştirmek istediğini yaz… (örn. daha resmi olsun)') : t('Ne yapılsın? Örn: hedef kitleyi ekle, maddeler halinde yaz') });
		var go = function () {
			var v = ask.value.trim();
			if (!v) return;
			var action = st.result && st.action !== 'summary' ? st.action : 'description';
			run(action, (st.result && st.hint ? st.hint + '\n' : '') + v, v);
		};
		ask.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
		var askRow = h('.flxg-ai-ask', Y.icon('sparkle', 15), ask, h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: go }, Y.icon('send', 13)));
		function run(action, hint, label) {
			st.state = 'loading';
			st.action = action;
			st.hint = hint || '';
			st.label = label || '';
			st.result = null;
			st.applied = null;
			drawAI(box, task);
			Y.api('POST', 'ai/task/' + task.id, { action: action, hint: hint || '' }, { label: t('Yapay zekâ çalışıyor') }).then(function (r) {
				if (D.ai !== st) return;
				st.state = 'result';
				st.result = r;
				if (D.task && D.task.id === task.id) drawAI(box, D.task);
			}, function (e) {
				if (D.ai !== st) return;
				st.state = 'error';
				st.error = e.message;
				drawAI(box, task);
			});
		}
		panel.appendChild(h('.flxg-ai-inline-head', Y.icon('sparkle', 15), h('b', t('Yapay zekâ')), st.label ? h('span.flxg-muted.flxg-ai-label', st.label) : null, h('span.flxg-grow'), close));
		if (st.state === 'menu' || st.state === 'error') {
			if (st.error && st.state === 'error') panel.appendChild(h('p.flxg-error', st.error));
			var chips = h('.flxg-ai-chips');
			AI_PRESETS.forEach(function (p) {
				if (p.action !== 'summary' && !edit) return;
				chips.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function () { run(p.action, p.hint, p.label); } }, p.label));
			});
			chips.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function () { Y.openAI({ task: task.id }); } }, Y.icon('msg', 13), t('Sohbet et')));
			panel.appendChild(chips);
			if (edit) panel.appendChild(askRow);
			if (edit && st.state === 'menu') setTimeout(function () { if (!Y.isMobile()) ask.focus(); }, 30);
			return;
		}
		if (st.state === 'loading') {
			var sk = h('.flxg-ai-sk');
			['92%', '80%', '86%', '54%'].forEach(function (w) { sk.appendChild(Y.sk(w, 11)); });
			panel.appendChild(h('.flxg-ai-thinking', h('span.flxg-ai-dots', h('i'), h('i'), h('i')), t('Yapay zekâ düşünüyor…')));
			panel.appendChild(sk);
			return;
		}
		// Sonuç.
		var r = st.result;
		var body = h('.flxg-ai-body');
		var foot = h('.flxg-ai-foot-row');
		panel.appendChild(body);
		panel.appendChild(foot);
		function applied(msg, undo) {
			st.applied = msg;
			st.undo = undo || null;
			drawAI(box, D.task && D.task.id === task.id ? D.task : task);
		}
		if (st.applied) {
			body.appendChild(h('.flxg-ai-done', Y.icon('check', 15), h('span', st.applied), st.undo ? h('button.flxg-link-btn', { type: 'button', onclick: function () { var u = st.undo; st.undo = null; st.applied = null; u(); } }, t('Geri al')) : null));
		}
		var again = h('button.flxg-btn.is-ghost.is-sm', { type: 'button', onclick: function () { run(st.action, st.hint, st.label); } }, Y.icon('sparkle', 13), t('Yeniden üret'));
		var back = h('button.flxg-btn.is-ghost.is-sm', { type: 'button', onclick: function () { st.state = 'menu'; st.result = null; st.applied = null; st.label = ''; drawAI(box, task); } }, t('Diğer seçenekler'));
		if (st.action === 'title') {
			var inp = h('input.flxg-input', { type: 'text', value: r.text });
			body.appendChild(inp);
			if (!st.applied) foot.appendChild(h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: function () {
				var old = task.title;
				save({ title: inp.value.trim() }).then(function () { applied(t('Görev adı güncellendi.'), function () { save({ title: old }); }); });
			} }, t('Uygula')));
		} else if (st.action === 'description') {
			if (!st.applied) body.appendChild(h('.flxg-prose.flxg-ai-preview', { html: r.html }));
			if (!st.applied) {
				var oldD = task.description || '';
				foot.appendChild(h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: function () {
					save({ description: r.html }).then(function () { applied(t('Açıklama güncellendi.'), function () { save({ description: oldD }); }); });
				} }, oldD ? t('Değiştir') : t('Uygula')));
				if (oldD) foot.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () {
					save({ description: oldD + r.html }).then(function () { applied(t('Açıklamanın sonuna eklendi.'), function () { save({ description: oldD }); }); });
				} }, t('Sona ekle')));
			}
		} else if (st.action === 'subtasks') {
			var checks = (r.items || []).map(function (it) {
				var cb = h('input', { type: 'checkbox', checked: true });
				var ti = h('input.flxg-input.is-sm', { type: 'text', value: it });
				return { cb: cb, ti: ti, row: h('label.flxg-ai-sub', cb, ti) };
			});
			body.appendChild(h('.flxg-ai-subs', checks.map(function (c) { return c.row; })));
			if (!st.applied) foot.appendChild(h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: function (e) {
				e.currentTarget.disabled = true;
				var list = checks.filter(function (c) { return c.cb.checked && c.ti.value.trim(); });
				list.reduce(function (p, c) {
					return p.then(function () { return Y.api('POST', 'tasks', { list: task.list, parent: task.id, title: c.ti.value.trim() }); });
				}, Promise.resolve()).then(function () {
					st.applied = t('{n} alt görev oluşturuldu', { n: list.length });
					load(task.id, true);
					Y.reloadTasks();
				}, Y.fail);
			} }, t('Alt görevleri oluştur')));
		} else {
			body.appendChild(h('.flxg-prose.flxg-ai-preview', { html: Y.richText(r.text) }));
			foot.appendChild(h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { Y.copy(r.text); } }, Y.icon('copy', 13), t('Kopyala')));
			if (canComment() && !st.applied) foot.appendChild(h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: function () {
				Y.api('POST', 'tasks/' + task.id + '/comments', { body: '✨ ' + t('Özet') + '\n' + r.text }).then(function () { bumpCount(task.id, 1); st.applied = t('Yorum olarak eklendi.'); load(task.id, true); }, Y.fail);
			} }, t('Yorum olarak ekle')));
		}
		foot.appendChild(h('span.flxg-grow'));
		foot.appendChild(back);
		foot.appendChild(again);
		if (edit && st.action !== 'summary') panel.appendChild(askRow);
	}
})();
