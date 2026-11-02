/* Flux Tasks — seçiciler, araç çubuğu, liste / pano / takvim görünümleri. */
(function () {
	'use strict';
	var Y = window.FLXG;
	var t = Y.t;
	var h = Y.h;
	var S = Y.S;
	var mobileMq = window.matchMedia('(max-width: 720px)');
	Y.isMobile = function () { return mobileMq.matches; };

	/* =====================================================================
	 * Seçiciler
	 * ================================================================== */
	var P = (Y.pick = {});

	P.status = function (anchor, listId, current, onPick) {
		var l = Y.list(listId);
		if (!l) return;
		Y.menu(anchor, [{ head: t('Durum') }].concat(l.statuses.map(function (s) {
			return { label: s.label, swatch: s.color, checked: s.key === current, onClick: function () { onPick(s.key); } };
		})));
	};

	P.priority = function (anchor, current, onPick) {
		var items = [{ head: t('Öncelik') }];
		(S.config.priorities || []).forEach(function (p) {
			items.push({ label: p.label, swatch: p.color, checked: +current === p.id, onClick: function () { onPick(p.id); } });
		});
		items.push({ sep: true });
		items.push({ label: t('Önceliği kaldır'), icon: 'x', onClick: function () { onPick(0); } });
		Y.menu(anchor, items);
	};

	P.people = function (anchor, selected, onChange, opts) {
		opts = opts || {};
		var sel = selected.slice();
		var input = h('input.flxg-input.is-sm', { type: 'search', placeholder: t('Kişi ara…') });
		var list = h('.flxg-pick-list');
		function draw() {
			Y.clear(list);
			var q = Y.norm(input.value);
			var users = S.userList.slice().sort(function (a, b) {
				var sa = sel.indexOf(a.id) > -1 ? 0 : 1;
				var sb = sel.indexOf(b.id) > -1 ? 0 : 1;
				if (a.id === S.me.id) sa -= 0.5;
				if (b.id === S.me.id) sb -= 0.5;
				return sa - sb || Y.cmpStr(a.name, b.name);
			});
			users.filter(function (u) { return !q || Y.norm(u.name).indexOf(q) > -1; }).forEach(function (u) {
				var on = sel.indexOf(u.id) > -1;
				list.appendChild(h('button.flxg-pick-item' + (on ? '.is-on' : ''), {
					type: 'button',
					onclick: function () {
						if (opts.single) { sel = on ? [] : [u.id]; onChange(sel); Y.closePop(); return; }
						if (on) sel = sel.filter(function (x) { return x !== u.id; });
						else sel.push(u.id);
						onChange(sel.slice());
						draw();
					}
				}, Y.avatar(u, 24), h('span', u.name + (u.id === S.me.id ? ' (' + t('ben') + ')' : '')), on ? Y.icon('check', 14, 'flxg-menu-check') : null));
			});
			if (!list.firstChild) list.appendChild(h('.flxg-empty-sm', t('Sonuç yok')));
		}
		input.addEventListener('input', draw);
		draw();
		Y.pop(anchor, h('.flxg-pick', input, list));
	};

	P.date = function (anchor, value, onPick, opts) {
		opts = opts || {};
		var today = Y.dayKey(new Date());
		var day = value ? value.slice(0, 10) : '';
		var time = value && value.length > 10 ? value.slice(11, 16) : '';
		var dIn = h('input.flxg-input.is-sm', { type: 'date', value: day });
		var tIn = h('input.flxg-input.is-sm', { type: 'time', value: time });
		function emit(d, tm) { onPick(d ? d + (tm ? 'T' + tm : '') : ''); Y.closePop(); }
		var nextMon = (function () { var d = new Date(); var add = (8 - d.getDay()) % 7 || 7; d.setDate(d.getDate() + add); return Y.dayKey(d); })();
		var quick = [
			[t('Bugün'), today],
			[t('Yarın'), Y.addDays(today, 1)],
			[t('Gelecek hafta'), nextMon],
			[t('2 hafta sonra'), Y.addDays(today, 14)]
		];
		var box = h('.flxg-datepick',
			h('.flxg-datepick-quick', quick.map(function (q) {
				return h('button.flxg-chip-btn', { type: 'button', onclick: function () { emit(q[1], tIn.value); } }, q[0]);
			})),
			h('label.flxg-field', h('span', opts.label || t('Tarih')), dIn),
			h('label.flxg-field', h('span', t('Saat (isteğe bağlı)')), tIn),
			h('.flxg-row-btns',
				value ? h('button.flxg-btn.is-ghost', { type: 'button', onclick: function () { emit(''); } }, t('Temizle')) : null,
				h('button.flxg-btn.is-primary', { type: 'button', onclick: function () { emit(dIn.value, tIn.value); } }, t('Uygula'))
			)
		);
		dIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') emit(dIn.value, tIn.value); });
		Y.pop(anchor, box, { noFocus: true });
	};

	P.tags = function (anchor, spaceId, selected, onChange) {
		var sel = selected.slice();
		var input = h('input.flxg-input.is-sm', { type: 'search', placeholder: t('Etiket ara veya oluştur…') });
		var list = h('.flxg-pick-list');
		function draw() {
			Y.clear(list);
			var q = input.value.trim();
			var nq = Y.norm(q);
			var tags = S.tags.filter(function (x) { return x.space_id === spaceId && (!nq || Y.norm(x.name).indexOf(nq) > -1); });
			tags.forEach(function (tg) {
				var on = sel.indexOf(tg.id) > -1;
				list.appendChild(h('button.flxg-pick-item' + (on ? '.is-on' : ''), {
					type: 'button',
					onclick: function () {
						sel = on ? sel.filter(function (x) { return x !== tg.id; }) : sel.concat([tg.id]);
						onChange(sel.slice());
						draw();
					}
				}, h('span.flxg-tag', { style: { '--c': tg.color } }, tg.name), on ? Y.icon('check', 14, 'flxg-menu-check') : null));
			});
			if (q && !S.tags.some(function (x) { return x.space_id === spaceId && Y.norm(x.name) === nq; })) {
				list.appendChild(h('button.flxg-pick-item.is-create', {
					type: 'button',
					onclick: function () {
						Y.api('POST', 'tags', { space: spaceId, name: q }).then(function (r) {
							S.tags = r.tags;
							sel.push(r.tag.id);
							onChange(sel.slice());
							input.value = '';
							draw();
						}, Y.fail);
					}
				}, Y.icon('plus'), h('span', t('Oluştur: {n}', { n: q }))));
			}
			if (!list.firstChild) list.appendChild(h('.flxg-empty-sm', t('Henüz etiket yok. Yazıp oluşturun.')));
		}
		input.addEventListener('input', draw);
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { var b = list.querySelector('.is-create') || list.querySelector('.flxg-pick-item'); if (b) b.click(); }
		});
		draw();
		Y.pop(anchor, h('.flxg-pick', input, list));
	};

	P.list = function (anchor, onPick, opts) {
		opts = opts || {};
		var items = [];
		S.spaces.forEach(function (sp) {
			var ls = S.lists.filter(function (l) { return l.space_id === sp.id && Y.rank(l.role) >= Y.rank(opts.need || 'edit'); });
			if (!ls.length) return;
			items.push({ head: sp.name });
			ls.forEach(function (l) {
				var f = l.folder_id ? Y.folder(l.folder_id) : null;
				items.push({ label: (f ? f.name + ' / ' : '') + l.name, swatch: l.color, checked: opts.current === l.id, onClick: function () { onPick(l.id); } });
			});
		});
		if (!items.length) items.push({ head: t('Görev ekleyebileceğiniz liste yok.') });
		Y.menu(anchor, items);
	};

	/* =====================================================================
	 * Hücre ve kart parçaları
	 * ================================================================== */
	/** Toplantı listesi: görev altyapısı aynı, yalnız adlar ve ekran farklı. */
	Y.isMeetingList = function (id) { var l = Y.list(id); return !!l && l.kind === 'meeting'; };
	Y.meetingScope = function () { return !!S.scope && S.scope.type === 'list' && Y.isMeetingList(S.scope.id); };
	Y.BUILTIN = function () {
		var mt = Y.meetingScope();
		var b = [
			{ key: 'status', name: t('Durum'), w: '130px' },
			{ key: 'assignees', name: mt ? t('Katılımcılar') : t('Atananlar'), w: '120px' },
			{ key: 'due', name: mt ? t('Tarih') : t('Bitiş'), w: '120px' },
			{ key: 'start', name: t('Başlangıç'), w: '120px' },
			{ key: 'priority', name: t('Öncelik'), w: '110px' },
			{ key: 'tags', name: t('Etiketler'), w: '170px' },
			{ key: 'created', name: t('Oluşturma'), w: '120px' },
			{ key: 'updated', name: t('Güncelleme'), w: '120px' },
			{ key: 'comments', name: t('Yorum'), w: '80px' }
		];
		if (S.scope && S.scope.type !== 'list') b.splice(1, 0, { key: 'list', name: t('Liste'), w: '150px' });
		return b;
	};
	Y.columnDefs = function () {
		var all = Y.BUILTIN();
		Y.scopeFields().forEach(function (f) {
			all.push({ key: f.key, name: f.name, w: f.type === 'textarea' ? '220px' : f.type === 'checkbox' || f.type === 'rating' ? '110px' : '150px', field: f });
		});
		return all;
	};
	Y.visibleCols = function () {
		var want = Y.viewCfg().fields || [];
		var defs = Y.columnDefs();
		return want.map(function (k) { return defs.filter(function (d) { return d.key === k; })[0]; }).filter(Boolean);
	};

	function canEdit(task) { return Y.canList(task.list, 'edit'); }

	Y.statusPill = function (task, small) {
		var st = Y.status(task);
		return h('span.flxg-status' + (small ? '.is-sm' : ''), { style: { '--c': st.color } }, st.label);
	};
	Y.dueChip = function (task, key) {
		key = key || 'due';
		var v = task[key];
		if (!v) return null;
		var state = key === 'due' ? Y.dueState(v, Y.isDone(task)) : '';
		return h('span.flxg-due' + (state ? '.is-' + state : ''), { title: Y.fmtDue(v, { full: true }) }, Y.icon(key === 'due' ? 'calendar' : 'clock', 13), Y.fmtDue(v));
	};
	Y.prioFlag = function (n, withLabel) {
		var p = Y.priority(n);
		if (!p) return null;
		return h('span.flxg-prio', { style: { '--c': p.color }, title: p.label }, Y.icon('flag', 13), withLabel ? p.label : null);
	};
	Y.tagChips = function (ids, max) {
		var wrap = h('span.flxg-tags');
		ids.slice(0, max || 99).forEach(function (id) {
			var tg = Y.tag(id);
			if (tg) wrap.appendChild(h('span.flxg-tag', { style: { '--c': tg.color } }, tg.name));
		});
		if (max && ids.length > max) wrap.appendChild(h('span.flxg-tag.is-more', '+' + (ids.length - max)));
		return wrap;
	};

	/** Özel alan değerinin görünümü. */
	Y.fieldView = function (col, v, compact) {
		if (v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length)) return null;
		var f = col.field || col;
		var o = f.options || {};
		switch (f.type) {
			case 'checkbox':
				return v ? h('span.flxg-fv.is-check', Y.icon('check', 14)) : null;
			case 'rating':
				var st = h('span.flxg-stars');
				for (var i = 1; i <= (o.max || 5); i++) st.appendChild(Y.icon(i <= v ? 'star-fill' : 'star', 13, i <= v ? 'is-on' : ''));
				return st;
			case 'dropdown':
				var c = (o.choices || []).filter(function (x) { return x.id === v; })[0];
				return c ? h('span.flxg-choice', { style: { '--c': c.color } }, c.label) : null;
			case 'labels':
				var w = h('span.flxg-tags');
				(Array.isArray(v) ? v : []).forEach(function (id) {
					var c2 = (o.choices || []).filter(function (x) { return x.id === id; })[0];
					if (c2) w.appendChild(h('span.flxg-tag', { style: { '--c': c2.color } }, c2.label));
				});
				return w;
			case 'people':
				return Y.avatars((Array.isArray(v) ? v : []).map(Number), 20, 3);
			case 'money':
				return h('span.flxg-fv', Y.money(v, o.currency));
			case 'number':
				return h('span.flxg-fv', new Intl.NumberFormat(Y.locale, { maximumFractionDigits: o.decimals || 2 }).format(v));
			case 'date':
				return h('span.flxg-fv', Y.fmtDue(v));
			case 'url':
				return h('a.flxg-fv.is-link', { href: v, target: '_blank', rel: 'noopener', onclick: function (e) { e.stopPropagation(); } }, String(v).replace(/^https?:\/\/(www\.)?/, '').slice(0, 40));
			case 'email':
				return h('a.flxg-fv.is-link', { href: 'mailto:' + v, onclick: function (e) { e.stopPropagation(); } }, v);
			case 'phone':
				return h('a.flxg-fv.is-link', { href: 'tel:' + String(v).replace(/\s/g, ''), onclick: function (e) { e.stopPropagation(); } }, v);
			case 'file':
				return h('span.flxg-fv', Y.icon('clip', 13), ' ', (Array.isArray(v) ? v.length : 0));
			case 'textarea':
				return h('span.flxg-fv.is-text', compact ? String(v).slice(0, 80) : v);
			default:
				return h('span.flxg-fv', String(v));
		}
	};

	/** Liste hücresi (tıklanınca yerinde düzenler). */
	function cell(task, col, opts) {
		opts = opts || {};
		var patch = opts.patch || Y.patchTask;
		var wrap = h('.flxg-cell', { dataset: { col: col.key } });
		var editable = canEdit(task);
		function editable_(fn) {
			if (!editable) return;
			wrap.classList.add('is-edit');
			wrap.addEventListener('click', function (e) { e.stopPropagation(); fn(wrap); });
		}
		switch (col.key) {
			case 'status':
				wrap.appendChild(Y.statusPill(task, true));
				editable_(function (a) { P.status(a, task.list, task.status, function (k) { patch(task.id, { status: k }); }); });
				break;
			case 'list':
				var l = Y.list(task.list);
				wrap.appendChild(h('span.flxg-listname', h('span.flxg-dot', { style: { background: l ? l.color : '#999' } }), l ? l.name : ''));
				break;
			case 'assignees':
				wrap.appendChild(task.assignees.length ? Y.avatars(task.assignees, 24, 3) : h('span.flxg-ph-ic', Y.icon('user', 15)));
				editable_(function (a) { P.people(a, task.assignees, function (ids) { patch(task.id, { assignees: ids }); }); });
				break;
			case 'due':
			case 'start':
				wrap.appendChild(Y.dueChip(task, col.key) || h('span.flxg-ph-ic', Y.icon('calendar', 15)));
				editable_(function (a) { P.date(a, task[col.key], function (v) { var p = {}; p[col.key] = v; patch(task.id, p); }); });
				break;
			case 'priority':
				wrap.appendChild(Y.prioFlag(task.priority, true) || h('span.flxg-ph-ic', Y.icon('flag', 15)));
				editable_(function (a) { P.priority(a, task.priority, function (n) { patch(task.id, { priority: n }); }); });
				break;
			case 'tags':
				wrap.appendChild(task.tags.length ? Y.tagChips(task.tags, 3) : h('span.flxg-ph-ic', Y.icon('tag', 15)));
				editable_(function (a) {
					var l2 = Y.list(task.list);
					P.tags(a, l2 ? l2.space_id : 0, task.tags, Y.debounce(function (ids) { patch(task.id, { tags: ids }); }, 400));
				});
				break;
			case 'created':
			case 'updated':
				wrap.appendChild(h('span.flxg-muted', { title: Y.fullTime(task[col.key]) }, Y.ago(task[col.key])));
				break;
			case 'comments':
				if (task.comments) wrap.appendChild(h('span.flxg-meta', Y.icon('msg', 13), task.comments));
				break;
			default:
				if (col.field) {
					var fv = Y.fieldValue(task, col.field);
					var node = fv ? Y.fieldView(fv.field, fv.v, true) : null;
					wrap.appendChild(node || h('span.flxg-ph-ic', '—'));
					var own = Y.fieldsFor(task.list).filter(function (f) { return col.field.ids.indexOf(f.id) > -1; })[0];
					if (own && editable) {
						wrap.classList.add('is-edit');
						wrap.addEventListener('click', function (e) {
							e.stopPropagation();
							Y.editFieldPop(wrap, task, own, opts.after);
						});
					}
				}
		}
		return wrap;
	}
	Y.cell = cell;

	/** Özel alanı açılır kutuda düzenleme (liste hücresi ve detay). */
	Y.editFieldPop = function (anchor, task, f, after) {
		var cur = task.fields ? task.fields[f.id] : null;
		function save(v) {
			var o = {};
			o[f.id] = v;
			var p = Y.patchTask(task.id, { fields: o });
			if (after) p.then(after, function () {});
		}
		var o = f.options || {};
		if (f.type === 'dropdown') {
			Y.menu(anchor, [{ head: f.name }].concat((o.choices || []).map(function (c) {
				return { label: c.label, swatch: c.color, checked: cur === c.id, onClick: function () { save(c.id); } };
			})).concat([{ sep: true }, { label: t('Temizle'), icon: 'x', onClick: function () { save(null); } }]));
			return;
		}
		if (f.type === 'labels') {
			var sel = Array.isArray(cur) ? cur.slice() : [];
			var list = h('.flxg-pick-list');
			var draw = function () {
				Y.clear(list);
				(o.choices || []).forEach(function (c) {
					var on = sel.indexOf(c.id) > -1;
					list.appendChild(h('button.flxg-pick-item' + (on ? '.is-on' : ''), {
						type: 'button',
						onclick: function () { sel = on ? sel.filter(function (x) { return x !== c.id; }) : sel.concat([c.id]); draw(); deb(sel); }
					}, h('span.flxg-tag', { style: { '--c': c.color } }, c.label), on ? Y.icon('check', 14, 'flxg-menu-check') : null));
				});
			};
			var deb = Y.debounce(function (v) { save(v.slice()); }, 500);
			draw();
			Y.pop(anchor, h('.flxg-pick', h('.flxg-menu-head', f.name), list));
			return;
		}
		if (f.type === 'checkbox') { save(!cur); return; }
		if (f.type === 'date') { P.date(anchor, cur || '', function (v) { save(v || null); }, { label: f.name }); return; }
		if (f.type === 'people') { P.people(anchor, Array.isArray(cur) ? cur.map(Number) : [], Y.debounce(function (ids) { save(ids); }, 500)); return; }
		if (f.type === 'rating') {
			var stars = h('.flxg-stars.is-big');
			for (var i = 1; i <= (o.max || 5); i++) {
				(function (n) {
					stars.appendChild(h('button', { type: 'button', 'aria-label': String(n), onclick: function () { save(n === cur ? null : n); Y.closePop(); } }, Y.icon(n <= (cur || 0) ? 'star-fill' : 'star', 22, n <= (cur || 0) ? 'is-on' : '')));
				})(i);
			}
			Y.pop(anchor, h('.flxg-pick', h('.flxg-menu-head', f.name), stars));
			return;
		}
		if (f.type === 'file') { Y.openTask(task.id); return; }
		var isArea = f.type === 'textarea';
		var inp = h(isArea ? 'textarea.flxg-input' : 'input.flxg-input', {
			type: f.type === 'number' || f.type === 'money' ? 'number' : f.type === 'email' ? 'email' : f.type === 'url' ? 'url' : f.type === 'phone' ? 'tel' : 'text',
			step: 'any',
			rows: isArea ? 5 : null,
			value: cur === null || cur === undefined ? '' : String(cur)
		});
		if (isArea) inp.value = cur || '';
		var go = function () { save(inp.value.trim() === '' ? null : inp.value.trim()); Y.closePop(); };
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (!isArea || e.ctrlKey || e.metaKey)) { e.preventDefault(); go(); } });
		Y.pop(anchor, h('.flxg-pick.is-form', h('.flxg-menu-head', f.name), inp, h('.flxg-row-btns', h('button.flxg-btn.is-primary.is-sm', { type: 'button', onclick: go }, t('Kaydet')))));
	};

	/* =====================================================================
	 * Araç çubuğu
	 * ================================================================== */
	Y.renderToolbar = function (bar) {
		Y.clear(bar);
		var c = Y.viewCfg();
		var v = S.view;
		var search = h('input.flxg-input.flxg-search', { type: 'search', placeholder: t('Görev ara…'), value: S.q || '' });
		search.addEventListener('input', Y.debounce(function () { S.q = search.value; Y.emit('view-data'); }, 150));
		var fcount = 0;
		var f = c.filters || {};
		['status', 'assignees', 'priority', 'tags'].forEach(function (k) { if (f[k] && f[k].length) fcount++; });
		if (f.due) fcount++;
		if (f.fields) fcount += Object.keys(f.fields).length;
		if (c.showDone === false) fcount++;

		var btns = [];
		btns.push(h('button.flxg-tb-btn' + (fcount ? '.is-active' : ''), { type: 'button', onclick: function (e) { Y.filterPop(e.currentTarget); } }, Y.icon('filter', 15), h('span.flxg-tb-label', t('Filtre')), fcount ? h('span.flxg-badge', fcount) : null));
		if (v && v.type !== 'calendar') {
			var gl = (Y.groupOptions().filter(function (o) { return o.id === c.group; })[0] || {}).label || t('Durum');
			btns.push(h('button.flxg-tb-btn', { type: 'button', onclick: function (e) {
				Y.menu(e.currentTarget, [{ head: t('Grupla') }].concat(Y.groupOptions().map(function (o) {
					return { label: o.label, checked: (c.group || 'status') === o.id, onClick: function () { c.group = o.id; Y.touchDraft(); } };
				})));
			} }, Y.icon('group', 15), h('span.flxg-tb-label', t('Grup: {n}', { n: gl }))));
			btns.push(h('button.flxg-tb-btn' + (c.sort && c.sort.by !== 'position' ? '.is-active' : ''), { type: 'button', onclick: function (e) {
				var opts = [['position', t('Elle sıralama')], ['due', t('Bitiş tarihi')], ['start', t('Başlangıç tarihi')], ['priority', t('Öncelik')], ['created', t('Oluşturma')], ['updated', t('Güncelleme')], ['title', t('Ad')]];
				var items = [{ head: t('Sırala') }].concat(opts.map(function (o) {
					return { label: o[1], checked: (c.sort || {}).by === o[0], onClick: function () { c.sort = { by: o[0], dir: (c.sort || {}).dir || 'asc' }; Y.touchDraft(); } };
				}));
				items.push({ sep: true });
				items.push({ label: t('Artan'), checked: (c.sort || {}).dir !== 'desc', onClick: function () { c.sort = { by: (c.sort || {}).by || 'position', dir: 'asc' }; Y.touchDraft(); } });
				items.push({ label: t('Azalan'), checked: (c.sort || {}).dir === 'desc', onClick: function () { c.sort = { by: (c.sort || {}).by || 'position', dir: 'desc' }; Y.touchDraft(); } });
				Y.menu(e.currentTarget, items);
			} }, Y.icon('sort', 15), h('span.flxg-tb-label', t('Sırala'))));
		}
		if (v && v.type === 'list') {
			var sm = c.subtasks || 'open';
			btns.push(h('button.flxg-tb-btn' + (sm !== 'open' ? '.is-active' : ''), { type: 'button', onclick: function (e) { Y.subtaskMenu(e.currentTarget); } }, Y.icon('subtask', 15), h('span.flxg-tb-label', t('Alt görevler'))));
		}
		btns.push(h('button.flxg-tb-btn', { type: 'button', onclick: function (e) { Y.fieldsPop(e.currentTarget); } }, Y.icon('columns', 15), h('span.flxg-tb-label', t('Alanlar'))));
		if (S.dirty && v) {
			btns.push(h('button.flxg-tb-btn.is-save', { type: 'button', onclick: function (e) { Y.saveViewMenu(e.currentTarget); } }, Y.icon('check', 15), h('span.flxg-tb-label', t('Görünümü kaydet'))));
		}
		var canAdd = Y.scopeLists().some(function (l) { return Y.rank(l.role) >= 3; });
		bar.appendChild(h('.flxg-tb-left', h('label.flxg-search-wrap', Y.icon('search', 15), search)));
		bar.appendChild(h('.flxg-tb-right', btns, canAdd ? h('button.flxg-btn.is-primary.flxg-add-task', { type: 'button', onclick: function () { Y.quickCreate({}); } }, Y.icon('plus', 16), h('span', Y.meetingScope() ? t('Toplantı') : t('Görev'))) : null));
	};

	/** Liste görünümünde alt görevlerin gösterimi. */
	Y.subtaskMenu = function (anchor) {
		var c = Y.viewCfg();
		var m = c.subtasks || 'open';
		function set(v) {
			c.subtasks = v;
			Y.storeJSON('sub-open', null);
			Y.L.addUnder = 0;
			Y.saveViewPart(['subtasks']);
			Y.emit('view');
		}
		Y.menu(anchor, [
			{ head: t('Alt görevler') },
			{ label: t('Görevin altında, açık'), checked: m === 'open', onClick: function () { set('open'); } },
			{ label: t('Görevin altında, kapalı'), checked: m === 'closed', onClick: function () { set('closed'); } },
			{ label: t('Gösterme'), checked: m === 'off', onClick: function () { set('off'); } },
			{ sep: true },
			{ label: t('Filtreyi alt görevlere de uygula'), checked: !!c.subFilter, onClick: function () { c.subFilter = !c.subFilter; Y.touchDraft(); } }
		]);
	};

	Y.saveViewMenu = function (anchor) {
		var v = S.view;
		var role = S.scope ? S.scope.role : 'view';
		var canShared = v.user_id === 0 ? Y.rank(role) >= 3 : true;
		Y.menu(anchor, [
			{ label: t('Bu görünüme kaydet'), icon: 'check', disabled: !canShared, onClick: function () {
				Y.api('PATCH', 'views/' + v.id, { config: S.draft }).then(function (r) {
					Object.assign(v, r.view);
					Y.setView(v);
					Y.emit('view');
					Y.toast(t('Görünüm kaydedildi'));
				}, Y.fail);
			} },
			{ label: t('Yeni görünüm olarak kaydet'), icon: 'plus', onClick: function () {
				Y.prompt(t('Görünüm adı'), v.name + ' – ' + t('filtreli')).then(function (name) {
					if (!name) return;
					Y.api('POST', 'views', { scope: S.scope.type, scope_id: S.scope.type === 'home' ? S.me.id : S.scope.id, name: name, type: v.type, config: S.draft, shared: Y.rank(role) >= 3 }).then(function (r) {
						S.views = r.views;
						Y.go(Y.scopeHash() + '/v/' + r.view.id);
					}, Y.fail);
				});
			} },
			{ sep: true },
			{ label: t('Değişiklikleri geri al'), icon: 'x', onClick: function () { Y.setView(v); Y.emit('view'); } }
		]);
	};

	Y.filterPop = function (anchor) {
		var c = Y.viewCfg();
		c.filters = c.filters || {};
		var f = c.filters;
		var box = h('.flxg-filter');
		function toggle(key, val) {
			f[key] = f[key] || [];
			var i = f[key].indexOf(val);
			if (i > -1) f[key].splice(i, 1);
			else f[key].push(val);
			if (!f[key].length) delete f[key];
			Y.touchDraft();
			draw();
		}
		function chip(key, val, label, color, extra) {
			var on = (f[key] || []).indexOf(val) > -1;
			return h('button.flxg-fchip' + (on ? '.is-on' : ''), { type: 'button', style: color ? { '--c': color } : null, onclick: function () { toggle(key, val); } }, extra || (color ? h('span.flxg-dot', { style: { background: color } }) : null), label);
		}
		function section(title, nodes) {
			return h('.flxg-fsec', h('.flxg-fsec-title', title), h('.flxg-fchips', nodes));
		}
		function draw() {
			Y.clear(box);
			box.appendChild(section(t('Durum'), Y.scopeStatuses().map(function (s) { return chip('status', s.key, s.label, s.color); })));
			var people = [chip('assignees', 'me', t('Bana atananlar')), chip('assignees', 'none', t('Atanmamış'))];
			S.userList.forEach(function (u) { if (u.id !== S.me.id) people.push(chip('assignees', String(u.id), u.name, null, Y.avatar(u, 18))); });
			box.appendChild(section(t('Atanan'), people));
			box.appendChild(section(t('Öncelik'), (S.config.priorities || []).map(function (p) { return chip('priority', String(p.id), p.label, p.color); }).concat([chip('priority', '0', t('Yok'))])));
			var tags = Y.scopeTags();
			if (tags.length) box.appendChild(section(t('Etiket'), tags.map(function (tg) { return chip('tags', String(tg.id), tg.name, tg.color); }).concat([chip('tags', 'none', t('Etiketsiz'))])));
			var dues = [['overdue', t('Gecikmiş')], ['today', t('Bugün')], ['week', t('7 gün içinde')], ['has', t('Tarihli')], ['none', t('Tarihsiz')]];
			box.appendChild(section(t('Bitiş tarihi'), dues.map(function (d) {
				return h('button.flxg-fchip' + (f.due === d[0] ? '.is-on' : ''), { type: 'button', onclick: function () { if (f.due === d[0]) delete f.due; else f.due = d[0]; Y.touchDraft(); draw(); } }, d[1]);
			})));
			// Özel alanlar.
			Y.scopeFields().forEach(function (col) {
				f.fields = f.fields || {};
				var cur = f.fields[col.key];
				var set = function (v) {
					if (v === undefined || (Array.isArray(v) && !v.length)) delete f.fields[col.key];
					else f.fields[col.key] = v;
					if (!Object.keys(f.fields).length) delete f.fields;
					Y.touchDraft();
					draw();
				};
				var nodes = [];
				if (col.type === 'dropdown' || col.type === 'labels') {
					var arr = Array.isArray(cur) ? cur : [];
					(col.options.choices || []).forEach(function (ch) {
						var on = arr.indexOf(ch.id) > -1;
						nodes.push(h('button.flxg-fchip' + (on ? '.is-on' : ''), { type: 'button', onclick: function () { set(on ? arr.filter(function (x) { return x !== ch.id; }) : arr.concat([ch.id])); } }, h('span.flxg-dot', { style: { background: ch.color } }), ch.label));
					});
					var onE = arr.indexOf('__empty') > -1;
					nodes.push(h('button.flxg-fchip' + (onE ? '.is-on' : ''), { type: 'button', onclick: function () { set(onE ? arr.filter(function (x) { return x !== '__empty'; }) : arr.concat(['__empty'])); } }, t('Boş')));
				} else if (col.type === 'people') {
					var arr2 = Array.isArray(cur) ? cur : [];
					S.userList.forEach(function (u) {
						var on = arr2.indexOf(String(u.id)) > -1;
						nodes.push(h('button.flxg-fchip' + (on ? '.is-on' : ''), { type: 'button', onclick: function () { set(on ? arr2.filter(function (x) { return x !== String(u.id); }) : arr2.concat([String(u.id)])); } }, Y.avatar(u, 18), u.name));
					});
				} else if (col.type === 'checkbox') {
					[['yes', t('İşaretli')], ['no', t('İşaretsiz')]].forEach(function (o) {
						nodes.push(h('button.flxg-fchip' + (cur === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { set(cur === o[0] ? undefined : o[0]); } }, o[1]));
					});
				} else if (col.type === 'rating') {
					for (var i = 1; i <= (col.options.max || 5); i++) {
						(function (n) {
							nodes.push(h('button.flxg-fchip' + (+cur === n ? '.is-on' : ''), { type: 'button', onclick: function () { set(+cur === n ? undefined : String(n)); } }, n + '★+'));
						})(i);
					}
				} else {
					[['__set', t('Dolu')], ['__empty', t('Boş')]].forEach(function (o) {
						nodes.push(h('button.flxg-fchip' + (cur === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { set(cur === o[0] ? undefined : o[0]); } }, o[1]));
					});
				}
				box.appendChild(section(col.name, nodes));
			});
			var doneOn = c.showDone !== false;
			box.appendChild(h('label.flxg-switch-row', h('input', { type: 'checkbox', checked: doneOn, onchange: function (e) { c.showDone = e.target.checked; Y.touchDraft(); } }), h('span', t('Tamamlanan görevleri göster'))));
			if (S.view && S.view.type === 'list') {
				box.appendChild(h('label.flxg-switch-row', h('input', { type: 'checkbox', checked: !!c.subFilter, onchange: function (e) { c.subFilter = e.target.checked; Y.touchDraft(); } }), h('span', h('b', t('Filtreyi alt görevlere de uygula')), h('small', t('Kapalıyken filtreye uyan görevlerin tüm alt görevleri görünür. Açıkken yalnız filtreye uyan alt görevler görünür; üst görevi uymasa da soluk olarak gösterilir.')))));
			}
			box.appendChild(h('.flxg-row-btns', h('button.flxg-btn.is-ghost.is-sm', { type: 'button', onclick: function () { c.filters = {}; c.showDone = true; f = c.filters; Y.touchDraft(); draw(); } }, t('Filtreleri temizle'))));
		}
		draw();
		Y.pop(anchor, box, { cls: 'is-wide', alignRight: true, noFocus: true });
	};

	Y.fieldsPop = function (anchor) {
		var c = Y.viewCfg();
		var box = h('.flxg-fieldspop');
		function draw() {
			Y.clear(box);
			var vis = c.fields || [];
			if (S.view && S.view.type !== 'list') {
				box.appendChild(h('label.flxg-switch-row.is-strong', h('input', { type: 'checkbox', checked: !!c.cover, onchange: function (e) { c.cover = e.target.checked; Y.touchDraft(); } }), Y.icon('image', 15), h('span', t('Kapak görselini göster'))));
			}
			if (S.view && S.view.type === 'list') {
				box.appendChild(h('label.flxg-switch-row.is-strong', h('input', { type: 'checkbox', checked: !!c.wrap, onchange: function (e) { c.wrap = e.target.checked; Y.saveViewPart(['wrap']); Y.emit('view'); } }), Y.icon('list', 15), h('span', h('b', t('Metni sar')), h('small', t('Uzun metinler en fazla 3 satır görünür, fazlası … ile kısalır.')))));
			}
			if (S.view && S.view.type === 'calendar') {
				box.appendChild(h('.flxg-menu-head', t('Takvimde kullanılacak tarih')));
				[['due', t('Bitiş tarihi')], ['start', t('Başlangıç tarihi')]].forEach(function (o) {
					box.appendChild(h('label.flxg-switch-row', h('input', { type: 'radio', name: 'flxg-df', checked: (c.dateField || 'due') === o[0], onchange: function () { c.dateField = o[0]; Y.touchDraft(); } }), h('span', o[1])));
				});
			}
			box.appendChild(h('.flxg-menu-head', S.view && S.view.type === 'list' ? t('Sütunlar') : t('Kartta gösterilecek alanlar')));
			Y.columnDefs().forEach(function (d) {
				if (S.view && S.view.type !== 'list' && d.key === 'status' && false) return;
				var on = vis.indexOf(d.key) > -1;
				box.appendChild(h('label.flxg-switch-row', h('input', { type: 'checkbox', checked: on, onchange: function () {
					c.fields = on ? vis.filter(function (k) { return k !== d.key; }) : vis.concat([d.key]);
					Y.touchDraft();
					draw();
				} }), d.field ? h('span.flxg-ftype', Y.fieldTypeIcon(d.field.type)) : null, h('span', d.name)));
			});
			if (S.scope && S.scope.type === 'list' && Y.rank(S.scope.role) >= 4) {
				box.appendChild(h('.flxg-row-btns', h('button.flxg-btn.is-ghost.is-sm', { type: 'button', onclick: function () { Y.closePop(); Y.listSettings(S.scope.id, 'fields'); } }, Y.icon('plus', 14), t('Özel alan ekle'))));
			}
		}
		draw();
		Y.pop(anchor, box, { alignRight: true, noFocus: true });
	};

	Y.fieldTypeIcon = function (type) {
		var m = { text: 'T', textarea: '¶', number: '#', money: '₺', date: '📅', checkbox: '☑', dropdown: '▾', labels: '⧉', rating: '★', people: '@', file: '📎', url: '🔗', email: '✉', phone: '☎' };
		return m[type] || '•';
	};

	/* =====================================================================
	 * Sürükle-bırak sonucu
	 * ================================================================== */
	function dropToGroup(info, groups, extra) {
		var id = +info.id;
		var task = S.tasks[id];
		if (!task) return;
		var gKey = info.zone.dataset.drop;
		var fromKey = info.el.closest('[data-drop]') ? info.el.closest('[data-drop]').dataset.drop : null;
		var g = groups.filter(function (x) { return x.key === gKey; })[0];
		var patch = Object.assign({}, extra || {});
		if (g && gKey !== fromKey) {
			if (!g.patch) { Y.toast(t('Bu gruba taşınamaz.'), { error: true }); Y.emit('tasks'); return; }
			var p = g.patch(task, fromKey);
			if (!p) { Y.toast(t('Bu listede uygun durum yok.'), { error: true }); Y.emit('tasks'); return; }
			Object.assign(patch, p);
		}
		var c = Y.viewCfg();
		if (!c.sort || c.sort.by === 'position') {
			var a = info.after ? S.tasks[info.after] : null;
			var b = info.before ? S.tasks[info.before] : null;
			var pos = Y.between(a ? a.pos : null, b ? b.pos : null);
			if (pos !== task.pos) patch.position = pos;
		}
		if (Object.keys(patch).length) Y.patchTask(id, patch);
		else Y.emit('tasks');
	}

	/**
	 * Listede bırakma: satır, bırakıldığı yerin kademesine geçer (alt görev olur ya da üst seviyeye çıkar).
	 * Altında bir kademe içeride satır varsa o satırın kardeşi olur; yoksa üstteki satırın kardeşi.
	 */
	function dropInList(info, groups, wrap) {
		var id = +info.id;
		var task = S.tasks[id];
		if (!task) return;
		var box = info.zone;
		function rowOf(x) { return x ? box.querySelector('.flxg-row[data-id="' + x + '"]') : null; }
		var prevEl = rowOf(info.after);
		var nextEl = rowOf(info.before);
		var dPrev = prevEl ? +prevEl.dataset.depth : -1;
		var dNext = nextEl ? +nextEl.dataset.depth : -1;
		var parent;
		if (nextEl && dNext > dPrev) parent = +nextEl.dataset.parent;
		else if (prevEl) parent = +prevEl.dataset.parent;
		else parent = nextEl ? +nextEl.dataset.parent : 0;
		var curParent = +info.el.dataset.parent;
		// Kendi altına bırakılamaz.
		for (var p = parent, i = 0; p && i < 15; i++) {
			if (p === id) { Y.emit('tasks'); return; }
			p = S.tasks[p] ? S.tasks[p].parent : 0;
		}
		if (!parent) { dropToGroup(info, groups, curParent ? { parent: 0 } : null); return; }
		var patch = {};
		if (parent !== curParent) {
			patch.parent = parent;
			var pt = S.tasks[parent];
			if (pt && pt.list !== task.list) patch.list = pt.list;
		}
		var c = Y.viewCfg();
		if (!c.sort || c.sort.by === 'position') {
			// Aynı üst görevin altındaki komşular arasına.
			var a = null;
			for (var x = prevEl ? S.tasks[+prevEl.dataset.id] : null, j = 0; x && j < 15; j++) {
				if ((x.parent || 0) === parent && x.id !== parent) { a = x; break; }
				if (x.id === parent) break;
				x = S.tasks[x.parent];
			}
			var b = nextEl && +nextEl.dataset.parent === parent ? S.tasks[+nextEl.dataset.id] : null;
			var pos = Y.between(a ? a.pos : null, b ? b.pos : null);
			if (pos !== task.pos) patch.position = pos;
		}
		if (patch.parent !== undefined) Y.L.open = parent;
		if (Object.keys(patch).length) Y.patchTask(id, patch).then(function () { Y.emit('tasks'); }, function () {});
		else Y.emit('tasks');
	}

	/* =====================================================================
	 * Liste görünümü
	 * ================================================================== */
	var L = (Y.L = { addUnder: 0 });

	/** Kullanıcının görev bazında açtığı / kapattığı alt görevler (tarayıcıda saklanır). */
	function subOverrides() { return Y.storeJSON('sub-open') || {}; }
	function isSubOpen(id, mode) {
		var o = subOverrides();
		if (o[id] !== undefined) return !!o[id];
		return mode !== 'closed';
	}
	function setSubOpen(id, open) {
		var o = subOverrides();
		o[id] = open ? 1 : 0;
		var keys = Object.keys(o);
		if (keys.length > 400) keys.slice(0, keys.length - 400).forEach(function (k) { delete o[k]; });
		Y.storeJSON('sub-open', o);
	}

	/** Projedeki alt görev kademe sınırı. */
	Y.depthLimit = function (listId) {
		var l = Y.list(listId);
		var sp = l ? Y.space(l.space_id) : null;
		return sp && sp.subtask_depth ? +sp.subtask_depth : 3;
	};
	/** Görevin kademesi (üst görev 0); üstü yüklü değilse en az 1 sayılır. */
	Y.taskDepth = function (task) {
		var d = 0;
		var x = task;
		for (var i = 0; i < 12 && x && x.parent; i++) { d++; x = S.tasks[x.parent]; }
		return d;
	};

	function colWidth(col) {
		var w = (Y.viewCfg().widths || {})[col.key];
		return w ? +w : parseInt(col.w, 10) || 140;
	}
	function colsTemplate(cols) {
		// Sütunlar ekrana sığdırılmaz; taşarsa yatay kayar, en sağdaki boşluk satırı doldurur.
		var tw = (Y.viewCfg().widths || {}).title || 360;
		return '34px ' + tw + 'px ' + cols.map(function (x) { return colWidth(x) + 'px'; }).join(' ') + ' minmax(0, 1fr)';
	}

	/** Görünüm ayarının bir kısmını (sütun genişliği, sırası) hemen kaydeder; yetki yoksa taslakta kalır. */
	Y.canEditView = function (v) {
		if (!v || !S.scope) return false;
		if (v.user_id) return v.user_id === S.me.id;
		return Y.rank(S.scope.role) >= 3;
	};
	Y.saveViewPart = function (keys) {
		var v = S.view;
		if (!v) return;
		var c = Y.viewCfg();
		if (!Y.canEditView(v)) { Y.touchDraft(); return; }
		var conf = JSON.parse(JSON.stringify(v.config || {}));
		keys.forEach(function (k) {
			if (c[k] === undefined) delete conf[k];
			else conf[k] = JSON.parse(JSON.stringify(c[k]));
		});
		v.config = conf;
		Y.api('PATCH', 'views/' + v.id, { config: conf }).then(function (r) { if (r && r.view && S.view === v) v.config = r.view.config; }, Y.fail);
	};

	/** Başlık satırı: sütunlar sürüklenerek sıralanır, kenarından genişletilir. */
	function headRow(cols, wrap) {
		var c = Y.viewCfg();
		var resizing = false;
		var dragKey = null;
		function cellFor(key, name, movable) {
			var cellEl = h('span.flxg-hcell' + (movable ? '.is-movable' : ''), { dataset: { key: key }, draggable: movable ? 'true' : null, title: movable ? t('Sırayı değiştirmek için sürükleyin') : null }, h('span.flxg-hcell-label', name));
			var grip = h('span.flxg-col-resize', { title: t('Genişliği değiştirmek için sürükleyin, sıfırlamak için çift tıklayın'), 'aria-hidden': 'true' });
			grip.addEventListener('pointerdown', function (e) {
				if (e.button !== 0) return;
				e.preventDefault();
				e.stopPropagation();
				resizing = true;
				var sx = e.clientX;
				var sw = cellEl.getBoundingClientRect().width;
				c.widths = Object.assign({}, c.widths || {});
				document.body.classList.add('flxg-col-resizing');
				function mv(ev) {
					c.widths[key] = Math.round(Math.max(key === 'title' ? 160 : 60, Math.min(900, sw + ev.clientX - sx)));
					wrap.style.setProperty('--cols', colsTemplate(cols));
				}
				function up() {
					document.removeEventListener('pointermove', mv);
					document.removeEventListener('pointerup', up);
					document.body.classList.remove('flxg-col-resizing');
					setTimeout(function () { resizing = false; }, 0);
					Y.saveViewPart(['widths']);
				}
				document.addEventListener('pointermove', mv);
				document.addEventListener('pointerup', up);
			});
			grip.addEventListener('dblclick', function (e) {
				e.stopPropagation();
				c.widths = Object.assign({}, c.widths || {});
				delete c.widths[key];
				wrap.style.setProperty('--cols', colsTemplate(cols));
				Y.saveViewPart(['widths']);
			});
			grip.addEventListener('click', function (e) { e.stopPropagation(); });
			cellEl.appendChild(grip);
			if (movable) {
				cellEl.addEventListener('dragstart', function (e) {
					if (resizing) { e.preventDefault(); return; }
					dragKey = key;
					cellEl.classList.add('is-dragging');
					try { e.dataTransfer.setData('text/plain', key); e.dataTransfer.effectAllowed = 'move'; } catch (er) { /* yok */ }
				});
				cellEl.addEventListener('dragend', function () {
					dragKey = null;
					Y.$$('.flxg-hcell', wrap).forEach(function (x) { x.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after'); });
				});
			}
			cellEl.addEventListener('dragover', function (e) {
				if (!dragKey || dragKey === key) return;
				e.preventDefault();
				var r = cellEl.getBoundingClientRect();
				var after = key !== 'title' && e.clientX > r.left + r.width / 2;
				Y.$$('.flxg-hcell', wrap).forEach(function (x) { if (x !== cellEl) x.classList.remove('is-drop-before', 'is-drop-after'); });
				cellEl.classList.toggle('is-drop-after', after);
				cellEl.classList.toggle('is-drop-before', !after);
			});
			cellEl.addEventListener('dragleave', function () { cellEl.classList.remove('is-drop-before', 'is-drop-after'); });
			cellEl.addEventListener('drop', function (e) {
				if (!dragKey || dragKey === key) return;
				e.preventDefault();
				var after = cellEl.classList.contains('is-drop-after');
				var list = (c.fields || []).filter(function (k) { return k !== dragKey; });
				var i = key === 'title' ? 0 : list.indexOf(key) + (after ? 1 : 0);
				list.splice(Math.max(0, i), 0, dragKey);
				c.fields = list;
				dragKey = null;
				Y.saveViewPart(['fields']);
				Y.emit('view');
			});
			return cellEl;
		}
		return h('.flxg-row.is-head', h('span'), cellFor('title', Y.meetingScope() ? t('Toplantı') : t('Görev adı'), false), cols.map(function (col) { return cellFor(col.key, col.name, true); }));
	}

	Y.renderList = function (root) {
		Y.clear(root);
		var c = Y.viewCfg();
		var mode = c.subtasks || 'open';
		var subFilter = !!c.subFilter;
		var tasks = Y.filtered({ context: mode !== 'off' && subFilter });
		var groups = Y.groups(tasks, c.group || 'status');
		if (!tasks.length && S.scope && S.scope.type === 'home') { root.appendChild(Y.emptyState()); Y.renderBulkBar(); return; }
		var cols = Y.visibleCols();
		var mobile = Y.isMobile();
		var wrap = h('.flxg-listview' + (mobile ? '.is-mobile' : '') + (c.wrap ? '.is-wrap' : ''));
		wrap.style.setProperty('--cols', colsTemplate(cols));
		var kidsIdx = mode === 'off' ? {} : Y.childIndex();
		var canAddAny = Y.scopeLists().some(function (l) { return Y.rank(l.role) >= 3; });
		function visibleKids(task) {
			return (kidsIdx[task.id] || []).filter(function (k) { return !subFilter || Y.taskMatches(k) || (S.keep && S.keep[k.id]) || hasMatch(k); });
		}
		function hasMatch(task) {
			return (kidsIdx[task.id] || []).some(function (k) { return Y.taskMatches(k) || hasMatch(k); });
		}
		function addRows(rows, task, depth, guard) {
			var kids = mode === 'off' ? [] : visibleKids(task);
			var open = kids.length > 0 && isSubOpen(task.id, mode);
			var limit = Y.depthLimit(task.list);
			if (L.open === task.id && !open && kids.length) { setSubOpen(task.id, true); open = true; L.open = 0; }
			rows.appendChild(Y.listRow(task, cols, mobile, {
				depth: depth,
				parent: depth ? task.parent : 0,
				kids: mode === 'off' ? -1 : kids.length,
				open: open,
				ctx: subFilter && depth > 0 && !Y.taskMatches(task),
				canSub: mode !== 'off' && canEdit(task) && Y.taskDepth(task) < limit,
				toggle: function () { setSubOpen(task.id, !open); Y.emit('view-data'); },
				addSub: function () {
					setSubOpen(task.id, true);
					L.addUnder = task.id;
					Y.emit('view-data');
				}
			}));
			if (open && guard < 12) kids.forEach(function (k) { addRows(rows, k, depth + 1, guard + 1); });
			if (L.addUnder === task.id) rows.appendChild(subAddRow(task, depth + 1, mobile));
		}
		var collapsed = c.collapsed || [];
		var total = 0;
		groups.forEach(function (g) {
			if (!g.tasks.length && g.key === 'all' && !tasks.length) return;
			if (!g.tasks.length && (c.group === 'tag' || c.group === 'assignee') && g.key !== 'as:none') return;
			total += g.tasks.length;
			var isCol = collapsed.indexOf(g.key) > -1;
			var sec = h('section.flxg-group' + (isCol ? '.is-collapsed' : ''), { dataset: { drop: g.key } });
			if (!g.patch) sec.dataset.nodrop = '1';
			var head = h('.flxg-group-head',
				h('button.flxg-caret', { type: 'button', 'aria-label': t('Daralt'), onclick: function () {
					c.collapsed = isCol ? collapsed.filter(function (k) { return k !== g.key; }) : collapsed.concat([g.key]);
					S.draft.collapsed = c.collapsed;
					Y.emit('view-data');
				} }, Y.icon(isCol ? 'chevR' : 'chevD', 16)),
				h('span.flxg-gpill', { style: { '--c': g.color } }, g.user ? Y.avatar(S.users[g.user], 18) : null, g.label),
				h('span.flxg-gcount', g.tasks.filter(function (x) { return !x._ctx; }).length)
			);
			sec.appendChild(head);
			if (!isCol) {
				if (!mobile) sec.appendChild(headRow(cols, wrap));
				var rows = h('.flxg-rows', { dataset: { dropItems: '1' } });
				g.tasks.forEach(function (task) { addRows(rows, task, 0, 0); });
				if (g.patch && canAddAny) rows.appendChild(Y.inlineAdd(g, { tail: true }));
				sec.appendChild(rows);
			}
			wrap.appendChild(sec);
		});
		if (!total) wrap.appendChild(Y.emptyState());
		var scroller = h('.flxg-scroll-x', wrap);
		var sl = L.scrollLeft || 0;
		scroller.addEventListener('scroll', function () {
			L.scrollLeft = scroller.scrollLeft;
			wrap.classList.toggle('is-scrolled', scroller.scrollLeft > 2);
		}, { passive: true });
		root.appendChild(scroller);
		if (sl) { scroller.scrollLeft = sl; wrap.classList.toggle('is-scrolled', scroller.scrollLeft > 2); }
		Y.dnd(wrap, {
			item: '.flxg-row[data-id]:not(.is-drag-hidden)',
			zone: '.flxg-group[data-drop]',
			onDrop: function (info) { dropInList(info, groups, wrap); },
			canStart: function (el) { return canEdit(S.tasks[el.dataset.id] || {}); },
			// Sürüklenen görevin alt görevleri onunla birlikte gider; o sırada gizlenir.
			onStart: function (el) {
				var d = +el.dataset.depth;
				for (var n = el.nextElementSibling; n && n.matches('.flxg-row[data-id]') && +n.dataset.depth > d; n = n.nextElementSibling) n.classList.add('is-drag-hidden');
			},
			onEnd: function () { Y.$$('.is-drag-hidden', wrap).forEach(function (n) { n.classList.remove('is-drag-hidden'); }); }
		});
		var sub = Y.$('.flxg-subadd-row input', wrap);
		if (sub) sub.focus();
		Y.renderBulkBar();
	};

	/** Liste içinde alt görev ekleme satırı. */
	function subAddRow(parent, depth, mobile) {
		var inp = h('input.flxg-input.is-sm', { type: 'text', placeholder: t('Alt görev adı yazıp Enter’a basın') });
		function close() { if (L.addUnder === parent.id) { L.addUnder = 0; Y.emit('view-data'); } }
		inp.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { e.preventDefault(); close(); return; }
			if (e.key !== 'Enter') return;
			e.preventDefault();
			var v = inp.value.trim();
			if (!v) { close(); return; }
			inp.value = '';
			Y.createTask({ list: parent.list, parent: parent.id, title: v }).then(function () {
				if (S.tasks[parent.id]) S.tasks[parent.id].subtasks = (S.tasks[parent.id].subtasks || 0) + 1;
				Y.emit('tasks');
			}, Y.fail);
		});
		inp.addEventListener('blur', function () { setTimeout(function () { if (!inp.value.trim() && document.activeElement !== inp) close(); }, 150); });
		return h('.flxg-row.flxg-subadd-row' + (mobile ? '.is-mobile' : ''), { style: { '--depth': depth } },
			mobile ? null : h('span'),
			h('.flxg-cell.flxg-title-cell.flxg-subadd-cell', h('span.flxg-subindent'), Y.icon('subtask', 14), inp)
		);
	}

	/** Filtreye artık uymayan ama bu ekranda değiştirilmiş görev için küçük işaret. */
	Y.outsideChip = function (task) {
		if (!task._outside) return null;
		var who = task.assignees.length && task.assignees.indexOf(S.me.id) < 0 ? (S.users[task.assignees[0]] || {}).name : '';
		return h('span.flxg-outside', { title: t('Bu görev artık bu görünümün filtresine uymuyor. Sayfa değişince gizlenir.') }, who ? t('Atandı: {n}', { n: who }) : t('Filtre dışı'));
	};

	/**
	 * Liste satırı. o: { depth, kids (görünen alt görev sayısı, -1 = alt görevler gizli), open, ctx, canSub, toggle(), addSub() }
	 */
	Y.listRow = function (task, cols, mobile, o) {
		o = o || {};
		var depth = o.depth || 0;
		var st = Y.status(task);
		var sel = !!S.selected[task.id];
		var check = h('label.flxg-check.flxg-nodrag', { onclick: function (e) { e.stopPropagation(); } }, h('input', { type: 'checkbox', checked: sel, onchange: function (e) {
			if (e.target.checked) S.selected[task.id] = 1;
			else delete S.selected[task.id];
			row.classList.toggle('is-selected', e.target.checked);
			Y.renderBulkBar();
		} }));
		var dot = h('button.flxg-sdot' + (st.type === 'done' ? '.is-done' : ''), {
			type: 'button',
			style: { '--c': st.color },
			title: st.label,
			onclick: function (e) {
				e.stopPropagation();
				if (canEdit(task)) P.status(e.currentTarget, task.list, task.status, function (k) { Y.patchTask(task.id, { status: k }); });
			}
		}, st.type === 'done' ? Y.icon('check', 11) : null);
		var caret = null;
		if (o.kids > 0) {
			caret = h('button.flxg-subcaret' + (o.open ? '.is-open' : ''), { type: 'button', title: o.open ? t('Alt görevleri gizle') : t('Alt görevleri göster'), 'aria-expanded': o.open ? 'true' : 'false', onclick: function (e) { e.stopPropagation(); o.toggle(); } }, Y.icon('chevR', 14));
		} else if (o.kids === 0 || depth > 0) {
			caret = h('span.flxg-subcaret.is-empty');
		}
		var subCount = o.kids > 0 ? o.kids : task.subtasks;
		var badges = h('span.flxg-badges',
			subCount ? h(o.kids > 0 ? 'button.flxg-meta.is-btn' : 'span.flxg-meta', { title: t('Alt görev'), type: o.kids > 0 ? 'button' : null, onclick: o.kids > 0 ? function (e) { e.stopPropagation(); o.toggle(); } : null }, Y.icon('subtask', 13), subCount) : null,
			task.agenda ? h('span.flxg-meta', { title: t('Gündem maddesi') }, Y.icon('list', 13), task.agenda) : null,
			task.decisions ? h('span.flxg-meta', { title: t('Karar') }, Y.icon('gavel', 13), task.decisions) : null,
			task.comments && !(cols || []).some(function (c) { return c.key === 'comments'; }) ? h('span.flxg-meta', Y.icon('msg', 13), task.comments) : null,
			task.files ? h('span.flxg-meta', Y.icon('clip', 13), task.files) : null,
			task.hasDesc ? h('span.flxg-meta', { title: t('Açıklama var') }, Y.icon('list', 13)) : null
		);
		var addSub = o.canSub ? h('button.flxg-row-addsub.flxg-nodrag', { type: 'button', title: t('Alt görev ekle'), onclick: function (e) { e.stopPropagation(); o.addSub(); } }, Y.icon('plus', 14)) : null;
		var title = h('.flxg-cell.flxg-title-cell', h('span.flxg-subindent'), caret, dot, h('span.flxg-title' + (st.type === 'done' ? '.is-done' : ''), task.title), badges, Y.outsideChip(task), addSub);
		var cls = '.flxg-row' + (sel ? '.is-selected' : '') + (S.openTask === task.id ? '.is-open' : '') + (task._outside ? '.is-outside' : '') + (depth ? '.is-sub' : '') + (task._ctx || o.ctx ? '.is-ctx' : '') + (S.saving[task.id] ? '.is-pending' : '');
		var data = { id: task.id, depth: depth, parent: o.parent || 0 };
		if (!depth) data.top = '1';
		var row = h(cls, {
			dataset: data,
			style: { '--depth': depth },
			tabindex: '0',
			title: task._ctx || o.ctx ? t('Filtreye uyan alt görevi olduğu için gösteriliyor') : null,
			onclick: function () { Y.openTask(task.id); },
			onkeydown: function (e) { if (e.key === 'Enter') Y.openTask(task.id); }
		});
		if (mobile) {
			row.appendChild(check);
			var meta = h('.flxg-row-meta',
				Y.statusPill(task, true),
				Y.dueChip(task),
				Y.prioFlag(task.priority),
				task.tags.length ? Y.tagChips(task.tags, 2) : null,
				task.assignees.length ? Y.avatars(task.assignees, 20, 3) : null
			);
			row.appendChild(h('.flxg-row-main', title, meta));
		} else {
			row.appendChild(check);
			row.appendChild(title);
			cols.forEach(function (col) { row.appendChild(cell(task, col)); });
		}
		return row;
	};

	/** Satır içi görev ekleme. */
	Y.inlineAdd = function (g, opts) {
		opts = opts || {};
		var box = h('.flxg-inline-add', { dataset: opts.tail ? { dropTail: '1' } : {} });
		var btn = h('button.flxg-add-btn', { type: 'button', onclick: open }, Y.icon('plus', 15), Y.meetingScope() ? t('Toplantı ekle') : t('Görev ekle'));
		box.appendChild(btn);
		function open() {
			Y.clear(box);
			var inp = h('input.flxg-input', { type: 'text', placeholder: t('Görev adı yazıp Enter’a basın') });
			var listSel = null;
			var lists = Y.scopeLists().filter(function (l) { return Y.rank(l.role) >= 3; });
			var chosen = opts.list || (g && g.list) || (S.scope.type === 'list' ? S.scope.id : (lists[0] || {}).id);
			if (S.scope.type !== 'list' && !(g && g.list) && lists.length > 1) {
				listSel = h('select.flxg-input.is-sm', lists.map(function (l) { return h('option', { value: l.id, selected: l.id === chosen }, l.name); }));
			}
			var save = function () {
				var title = inp.value.trim();
				if (!title) { close(); return; }
				var list = listSel ? +listSel.value : chosen;
				var data = { list: list, title: title };
				if (opts.extra) Object.assign(data, opts.extra);
				var fake = { id: 0, list: list, assignees: [], fields: {}, tags: [], status: '', priority: 0, due: '' };
				if (g && g.patch) {
					var p = g.patch(fake, null);
					if (p) Object.assign(data, p);
				}
				if (S.scope.type === 'home' && !data.assignees) data.assignees = [S.me.id];
				var c = Y.viewCfg();
				inp.value = '';
				Y.createTask(data).then(function (task) {
					if (c.sort && c.sort.by === 'position') { /* sona eklendi */ }
					if (opts.onCreated) opts.onCreated(task);
					var again = Y.$('.flxg-inline-add input', box.closest('[data-drop]') || document);
					if (again) again.focus();
				}, Y.fail);
			};
			function close() { Y.clear(box); box.appendChild(btn); }
			inp.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); save(); }
				if (e.key === 'Escape') close();
			});
			inp.addEventListener('blur', function () { setTimeout(function () { if (!inp.value.trim() && document.activeElement !== listSel) close(); }, 150); });
			box.appendChild(h('.flxg-inline-form', inp, listSel, h('button.flxg-btn.is-primary.is-sm', { type: 'button', onmousedown: function (e) { e.preventDefault(); save(); } }, t('Ekle'))));
			setTimeout(function () { inp.focus(); }, 0);
		}
		if (opts.autoOpen) open();
		return box;
	};

	Y.emptyState = function () {
		var c = Y.viewCfg();
		var filtered = S.q || Object.keys(c.filters || {}).length || c.showDone === false;
		return h('.flxg-empty',
			h('.flxg-empty-ic', Y.icon(filtered ? 'filter' : 'checklist', 34)),
			h('p', filtered ? t('Filtreye uyan görev yok.') : S.scope && S.scope.type === 'home' ? t('Size atanmış açık görev yok. 🎉') : t('Bu listede henüz görev yok.')),
			filtered ? h('button.flxg-btn', { type: 'button', onclick: function () { S.q = ''; c.filters = {}; c.showDone = true; Y.touchDraft(); } }, t('Filtreleri temizle')) : null
		);
	};

	/* Toplu işlem çubuğu. */
	Y.renderBulkBar = function () {
		var old = Y.$('.flxg-bulk');
		var ids = Object.keys(S.selected).map(Number).filter(function (id) { return S.tasks[id]; });
		if (!ids.length) { if (old) old.remove(); return; }
		var bar = h('.flxg-bulk',
			h('span.flxg-bulk-n', t('{n} seçili', { n: ids.length })),
			h('button.flxg-tb-btn', { type: 'button', onclick: function (e) {
				var items = [{ head: t('Durum') }].concat(Y.scopeStatuses().map(function (s) {
					return { label: s.label, swatch: s.color, onClick: function () {
						bulkEach(function (task) {
							var l = Y.list(task.list);
							var m = l && l.statuses.filter(function (x) { return Y.norm(x.label) === s.key; })[0];
							return m ? { status: m.key } : null;
						});
					} };
				}));
				Y.menu(e.currentTarget, items);
			} }, Y.icon('check', 15), h('span.flxg-tb-label', t('Durum'))),
			h('button.flxg-tb-btn', { type: 'button', onclick: function (e) { P.people(e.currentTarget, [], Y.debounce(function (sel) { bulk({ add_assignees: sel }); }, 900)); } }, Y.icon('user', 15), h('span.flxg-tb-label', t('Ata'))),
			h('button.flxg-tb-btn', { type: 'button', onclick: function (e) { P.priority(e.currentTarget, 0, function (n) { bulk({ priority: n }); }); } }, Y.icon('flag', 15), h('span.flxg-tb-label', t('Öncelik'))),
			h('button.flxg-tb-btn', { type: 'button', onclick: function (e) { P.date(e.currentTarget, '', function (v) { bulk({ due: v }); }); } }, Y.icon('calendar', 15), h('span.flxg-tb-label', t('Bitiş'))),
			h('button.flxg-tb-btn', { type: 'button', onclick: function (e) { P.list(e.currentTarget, function (lid) { bulk({ list: lid }); }); } }, Y.icon('move', 15), h('span.flxg-tb-label', t('Taşı'))),
			h('button.flxg-tb-btn.is-danger', { type: 'button', onclick: function () {
				Y.confirm(t('Görevleri sil'), t('{n} görev, yorum ve dosyalarıyla birlikte kalıcı olarak silinecek.', { n: ids.length }), t('Sil'), true).then(function (ok) {
					if (!ok) return;
					Y.api('POST', 'tasks/bulk', { ids: ids, delete: true }).then(function (r) {
						r.deleted.forEach(function (id) { delete S.tasks[id]; });
						S.selected = {};
						Y.emit('tasks');
					}, Y.fail);
				});
			} }, Y.icon('trash', 15)),
			h('button.flxg-icon-btn', { type: 'button', 'aria-label': t('Seçimi kaldır'), onclick: function () { S.selected = {}; Y.emit('tasks'); } }, Y.icon('x', 16))
		);
		function bulk(patch) {
			Y.api('POST', 'tasks/bulk', { ids: ids, patch: patch }).then(function (r) {
				r.tasks.forEach(Y.upsertTask);
				Y.emit('tasks');
				Y.toast(t('{n} görev güncellendi', { n: r.tasks.length }));
			}, Y.fail);
		}
		function bulkEach(fn) {
			Promise.all(ids.map(function (id) { var p = fn(S.tasks[id]); return p ? Y.patchTask(id, p, { silent: true }).catch(function () {}) : null; })).then(function () { Y.emit('tasks'); });
		}
		if (old) old.replaceWith(bar);
		else (Y.$('.flxg-main') || document.body).appendChild(bar);
	};

	/* =====================================================================
	 * Pano (kanban)
	 * ================================================================== */
	Y.card = function (task, c) {
		var st = Y.status(task);
		var vis = c.fields || [];
		var cover = c.cover && task.cover ? h('.flxg-card-cover', h('img', { src: Y.fileUrl(task.cover, 'thumb'), alt: '', loading: 'lazy', draggable: 'false' })) : null;
		var fieldsBox = h('.flxg-card-fields');
		Y.visibleCols().forEach(function (col) {
			if (['assignees', 'due', 'priority', 'tags', 'comments', 'status', 'created', 'updated', 'start', 'list'].indexOf(col.key) > -1) return;
			var fv = col.field ? Y.fieldValue(task, col.field) : null;
			var node = fv ? Y.fieldView(fv.field, fv.v, true) : null;
			if (node) fieldsBox.appendChild(h('.flxg-card-field', h('span.flxg-card-flabel', col.name), node));
		});
		var foot = h('.flxg-card-foot',
			vis.indexOf('due') > -1 ? Y.dueChip(task) : null,
			vis.indexOf('start') > -1 ? Y.dueChip(task, 'start') : null,
			vis.indexOf('priority') > -1 ? Y.prioFlag(task.priority) : null,
			task.subtasks ? h('span.flxg-meta', Y.icon('subtask', 13), task.subtasks) : null,
			task.agenda ? h('span.flxg-meta', { title: t('Gündem maddesi') }, Y.icon('list', 13), task.agenda) : null,
			task.decisions ? h('span.flxg-meta', { title: t('Karar') }, Y.icon('gavel', 13), task.decisions) : null,
			task.comments ? h('span.flxg-meta', Y.icon('msg', 13), task.comments) : null,
			task.files ? h('span.flxg-meta', Y.icon('clip', 13), task.files) : null,
			h('span.flxg-grow'),
			vis.indexOf('assignees') > -1 && task.assignees.length ? Y.avatars(task.assignees, 22, 3) : null
		);
		var l = vis.indexOf('list') > -1 ? Y.list(task.list) : null;
		return h('article.flxg-card' + (st.type === 'done' ? '.is-done' : '') + (S.openTask === task.id ? '.is-open' : '') + (task._outside ? '.is-outside' : '') + (S.saving[task.id] ? '.is-pending' : ''), {
			dataset: { id: task.id },
			tabindex: '0',
			style: { '--c': st.color },
			onclick: function () { Y.openTask(task.id); },
			onkeydown: function (e) { if (e.key === 'Enter') Y.openTask(task.id); }
		},
		cover,
		h('.flxg-card-body',
			vis.indexOf('tags') > -1 && task.tags.length ? Y.tagChips(task.tags, 4) : null,
			l ? h('.flxg-card-list', h('span.flxg-dot', { style: { background: l.color } }), l.name) : null,
			h('.flxg-card-title', vis.indexOf('status') > -1 && (c.group || 'status') !== 'status' ? Y.statusPill(task, true) : null, task.title),
			Y.outsideChip(task),
			fieldsBox.firstChild ? fieldsBox : null,
			foot.children.length > 1 ? foot : null
		));
	};

	Y.renderBoard = function (root) {
		Y.clear(root);
		var c = Y.viewCfg();
		var tasks = Y.filtered();
		var groups = Y.groups(tasks, c.group || 'status');
		var board = h('.flxg-board');
		var canAdd = Y.scopeLists().some(function (l) { return Y.rank(l.role) >= 3; });
		groups.forEach(function (g) {
			if (!g.tasks.length && (c.group === 'tag') && g.key !== 'tg:none') return;
			var col = h('section.flxg-col', { dataset: { drop: g.key }, style: { '--c': g.color } });
			if (!g.patch) col.dataset.nodrop = '1';
			col.appendChild(h('.flxg-col-head',
				h('span.flxg-gpill', { style: { '--c': g.color } }, g.user ? Y.avatar(S.users[g.user], 18) : null, g.label),
				h('span.flxg-gcount', g.tasks.length),
				h('span.flxg-grow'),
				canAdd && g.patch ? h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Görev ekle'), onclick: function () {
					var add = col.querySelector('.flxg-inline-add');
					if (add) { add.remove(); }
					var box = Y.inlineAdd(g, { tail: true, autoOpen: true });
					cards.insertBefore(box, cards.firstChild);
				} }, Y.icon('plus', 15)) : null
			));
			var cards = h('.flxg-cards', { dataset: { dropItems: '1' } });
			g.tasks.forEach(function (task) { cards.appendChild(Y.card(task, c)); });
			if (canAdd && g.patch) cards.appendChild(Y.inlineAdd(g, { tail: true }));
			col.appendChild(cards);
			board.appendChild(col);
		});
		root.appendChild(board);
		if (!tasks.length && (S.q || Object.keys(c.filters || {}).length)) root.appendChild(Y.emptyState());
		Y.dnd(board, { item: '.flxg-card', zone: '.flxg-col[data-drop]', onDrop: function (info) { dropToGroup(info, groups); }, canStart: function (el) { return canEdit(S.tasks[el.dataset.id] || {}); } });
	};

	/* =====================================================================
	 * Takvim
	 * ================================================================== */
	S.cal = S.cal || { y: new Date().getFullYear(), m: new Date().getMonth(), mode: null };

	Y.renderCalendar = function (root) {
		Y.clear(root);
		var c = Y.viewCfg();
		var key = c.dateField === 'start' ? 'start' : 'due';
		var mode = S.cal.mode || (Y.isMobile() ? 'agenda' : 'month');
		var tasks = Y.filtered({ includeSub: true });
		var byDay = {};
		var undated = [];
		tasks.forEach(function (x) {
			var d = x[key] ? x[key].slice(0, 10) : '';
			if (!d) { undated.push(x); return; }
			(byDay[d] = byDay[d] || []).push(x);
		});
		Object.keys(byDay).forEach(function (d) {
			byDay[d].sort(function (a, b) { return (a[key] || '').localeCompare(b[key] || '') || (a.priority || 9) - (b.priority || 9); });
		});
		var ws = S.config.weekStart || 0;
		var today = Y.dayKey(new Date());

		var head = h('.flxg-cal-head',
			h('.flxg-cal-nav',
				h('button.flxg-icon-btn', { type: 'button', 'aria-label': t('Önceki'), onclick: function () { shift(-1); } }, Y.icon('chevL', 18)),
				h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { var n = new Date(); S.cal.y = n.getFullYear(); S.cal.m = n.getMonth(); S.cal.week = null; Y.emit('view-data'); } }, t('Bugün')),
				h('button.flxg-icon-btn', { type: 'button', 'aria-label': t('Sonraki'), onclick: function () { shift(1); } }, Y.icon('chevR', 18)),
				h('h3.flxg-cal-title', mode === 'week' ? weekTitle() : Y.monthName(S.cal.y, S.cal.m))
			),
			h('.flxg-seg',
				[['month', t('Ay')], ['week', t('Hafta')], ['agenda', t('Ajanda')]].map(function (o) {
					return h('button' + (mode === o[0] ? '.is-on' : ''), { type: 'button', onclick: function () { S.cal.mode = o[0]; if (o[0] === 'week' && !S.cal.week) S.cal.week = startOfWeek(today); Y.emit('view-data'); } }, o[1]);
				})
			),
			undated.length ? h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onclick: function (e) { undatedPop(e.currentTarget); } }, t('Tarihsiz: {n}', { n: undated.length })) : null
		);
		root.appendChild(head);

		function startOfWeek(k) {
			var d = Y.parseDay(k);
			var diff = (d.getDay() - ws + 7) % 7;
			d.setDate(d.getDate() - diff);
			return Y.dayKey(d);
		}
		function weekTitle() {
			var s = S.cal.week || startOfWeek(today);
			var e = Y.addDays(s, 6);
			return Y.parseDay(s).toLocaleDateString(Y.locale, { day: 'numeric', month: 'short' }) + ' – ' + Y.parseDay(e).toLocaleDateString(Y.locale, { day: 'numeric', month: 'short', year: 'numeric' });
		}
		function shift(n) {
			if (mode === 'week') { S.cal.week = Y.addDays(S.cal.week || startOfWeek(today), 7 * n); var d = Y.parseDay(S.cal.week); S.cal.y = d.getFullYear(); S.cal.m = d.getMonth(); }
			else { var m = S.cal.m + n; S.cal.y += Math.floor(m / 12); S.cal.m = ((m % 12) + 12) % 12; }
			Y.emit('view-data');
		}
		function undatedPop(anchor) {
			var list = h('.flxg-pick-list');
			undated.slice(0, 100).forEach(function (x) {
				list.appendChild(h('button.flxg-pick-item', { type: 'button', onclick: function () { Y.closePop(); Y.openTask(x.id); } }, h('span.flxg-dot', { style: { background: Y.status(x).color } }), h('span', x.title)));
			});
			Y.pop(anchor, h('.flxg-pick', h('.flxg-menu-head', t('Tarihsiz görevler')), list), { alignRight: true });
		}
		function chip(x) {
			var st = Y.status(x);
			var showCover = c.cover && x.cover;
			var time = x[key] && x[key].length > 10 ? x[key].slice(11, 16) : '';
			return h('button.flxg-cal-chip' + (st.type === 'done' ? '.is-done' : '') + (showCover ? '.has-cover' : ''), {
				type: 'button',
				dataset: { id: x.id },
				style: { '--c': st.color },
				title: x.title,
				onclick: function (e) { e.stopPropagation(); Y.openTask(x.id); }
			},
			showCover ? h('img', { src: Y.fileUrl(x.cover, 'thumb'), alt: '', loading: 'lazy', draggable: 'false' }) : null,
			h('span.flxg-cal-chip-t', time ? h('b', time + ' ') : null, x.title),
			(c.fields || []).indexOf('assignees') > -1 && x.assignees.length && !showCover ? Y.avatars(x.assignees, 16, 2) : null);
		}
		function quickAdd(day) {
			if (!Y.scopeLists().some(function (l) { return Y.rank(l.role) >= 3; })) return;
			var extra = {};
			extra[key] = day;
			Y.quickCreate(extra);
		}

		if (mode === 'agenda') {
			var ag = h('.flxg-agenda');
			var start = S.cal.y + '-' + String(S.cal.m + 1).padStart(2, '0') + '-01';
			var endD = new Date(S.cal.y, S.cal.m + 1, 0);
			var end = Y.dayKey(endD);
			var days = Object.keys(byDay).filter(function (d) { return d >= start && d <= end; }).sort();
			if (!days.length) ag.appendChild(h('.flxg-empty-sm', t('Bu ay tarihli görev yok.')));
			days.forEach(function (d) {
				var dd = Y.parseDay(d);
				ag.appendChild(h('.flxg-agenda-day' + (d === today ? '.is-today' : '') + (d < today ? '.is-past' : ''),
					h('.flxg-agenda-date', h('b', dd.getDate()), h('span', dd.toLocaleDateString(Y.locale, { weekday: 'short' }))),
					h('.flxg-agenda-items', byDay[d].map(function (x) { return c.cover && x.cover ? Y.card(x, c) : chip(x); }))
				));
			});
			root.appendChild(ag);
			return;
		}

		var grid = h('.flxg-cal' + (mode === 'week' ? '.is-week' : ''));
		grid.appendChild(h('.flxg-cal-wd', Y.weekdayNames(ws).map(function (n) { return h('span', n); })));
		var first;
		var count;
		if (mode === 'week') {
			first = S.cal.week || startOfWeek(today);
			count = 7;
		} else {
			first = startOfWeek(S.cal.y + '-' + String(S.cal.m + 1).padStart(2, '0') + '-01');
			var last = new Date(S.cal.y, S.cal.m + 1, 0);
			var lastKey = Y.dayKey(last);
			count = 0;
			while (Y.addDays(first, count) <= lastKey || count % 7) count++;
		}
		var body = h('.flxg-cal-grid', { style: { '--rows': Math.ceil(count / 7) } });
		for (var i = 0; i < count; i++) {
			(function (d) {
				var dd = Y.parseDay(d);
				var items = byDay[d] || [];
				var other = mode === 'month' && dd.getMonth() !== S.cal.m;
				var cellEl = h('.flxg-cal-cell' + (d === today ? '.is-today' : '') + (other ? '.is-other' : '') + (dd.getDay() === 0 || dd.getDay() === 6 ? '.is-weekend' : ''), {
					dataset: { drop: d },
					onclick: function (e) { if (e.target === cellEl || e.target.classList.contains('flxg-cal-day')) quickAdd(d); }
				}, h('span.flxg-cal-day', dd.getDate() === 1 && mode === 'month' ? dd.toLocaleDateString(Y.locale, { day: 'numeric', month: 'short' }) : dd.getDate()));
				var max = mode === 'week' ? 50 : (c.cover ? 2 : 4);
				var box = h('.flxg-cal-items', { dataset: { dropItems: '1' } });
				items.slice(0, max).forEach(function (x) { box.appendChild(chip(x)); });
				if (items.length > max) {
					box.appendChild(h('button.flxg-cal-more', { type: 'button', onclick: function (e) {
						e.stopPropagation();
						Y.pop(e.currentTarget, h('.flxg-pick', h('.flxg-menu-head', dd.toLocaleDateString(Y.locale, { dateStyle: 'full' })), h('.flxg-cal-poplist', items.map(chip))));
					} }, t('+{n} daha', { n: items.length - max })));
				}
				cellEl.appendChild(box);
				body.appendChild(cellEl);
			})(Y.addDays(first, i));
		}
		grid.appendChild(body);
		root.appendChild(grid);
		Y.dnd(body, {
			item: '.flxg-cal-chip',
			zone: '.flxg-cal-cell[data-drop]',
			mode: 'cell',
			canStart: function (el) { return canEdit(S.tasks[el.dataset.id] || {}); },
			onDrop: function (info) {
				var task = S.tasks[+info.id];
				if (!task) return;
				var day = info.zone.dataset.drop;
				var old = task[key] || '';
				if (old.slice(0, 10) === day) return;
				var p = {};
				p[key] = day + (old.length > 10 ? old.slice(10) : '');
				Y.patchTask(task.id, p);
			}
		});
	};

	/* Hızlı görev oluşturma penceresi. */
	Y.quickCreate = function (extra) {
		extra = extra || {};
		var lists = Y.scopeLists().filter(function (l) { return Y.rank(l.role) >= 3; });
		if (!lists.length) lists = S.lists.filter(function (l) { return Y.rank(l.role) >= 3; });
		if (!lists.length) { Y.toast(t('Görev ekleyebileceğiniz liste yok.'), { error: true }); return; }
		var data = { list: S.scope && S.scope.type === 'list' ? S.scope.id : lists[0].id, assignees: S.scope && S.scope.type === 'home' ? [S.me.id] : [], priority: 0, due: extra.due || '', start: extra.start || '' };
		var mt = Y.isMeetingList(data.list);
		var title = h('input.flxg-input.is-lg', { type: 'text', placeholder: mt ? t('Toplantı adı') : t('Görev adı') });
		var desc = h('textarea.flxg-input', { rows: 3, placeholder: t('Açıklama (isteğe bağlı)') });
		var row = h('.flxg-qc-row');
		function drawRow() {
			Y.clear(row);
			var l = Y.list(data.list);
			mt = l.kind === 'meeting';
			title.placeholder = mt ? t('Toplantı adı') : t('Görev adı');
			if (lists.length > 1) row.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function (e) { P.list(e.currentTarget, function (id) { data.list = id; drawRow(); }, { current: data.list }); } }, h('span.flxg-dot', { style: { background: l.color } }), l.name));
			row.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function (e) { P.people(e.currentTarget, data.assignees, function (ids) { data.assignees = ids; drawRow(); }); } }, data.assignees.length ? Y.avatars(data.assignees, 18) : Y.icon('user', 14), data.assignees.length ? null : (mt ? t('Katılımcılar') : t('Ata'))));
			row.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function (e) { P.date(e.currentTarget, data.due, function (v) { data.due = v; drawRow(); }); } }, Y.icon('calendar', 14), data.due ? Y.fmtDue(data.due) : (mt ? t('Tarih') : t('Bitiş'))));
			if (!mt) row.appendChild(h('button.flxg-chip-btn', { type: 'button', onclick: function (e) { P.priority(e.currentTarget, data.priority, function (n) { data.priority = n; drawRow(); }); } }, Y.prioFlag(data.priority) || Y.icon('flag', 14), data.priority ? Y.priority(data.priority).label : t('Öncelik')));
		}
		drawRow();
		var m;
		var go = function (open) {
			if (!title.value.trim()) { title.focus(); return Promise.resolve(false); }
			var body = Object.assign({}, data, { title: title.value.trim() });
			if (desc.value.trim()) body.description = '<p>' + desc.value.trim().replace(/[&<>]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]; }).replace(/\n/g, '<br>') + '</p>';
			return Y.createTask(body).then(function (task) {
				if (open) Y.openTask(task.id);
				else Y.toast(mt ? t('Toplantı oluşturuldu') : t('Görev oluşturuldu'), { action: { label: t('Aç'), fn: function () { Y.openTask(task.id); } } });
				return true;
			});
		};
		m = Y.modal(mt ? t('Yeni toplantı') : t('Yeni görev'), h('.flxg-qc', title, desc, row), {
			actions: [
				{ label: t('Oluştur ve aç'), onClick: function () { return go(true); } },
				{ label: t('Oluştur'), primary: true, onClick: function () { return go(false); } }
			]
		});
		title.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(false).then(function (ok) { if (ok) m.close(); }); } });
	};
})();
