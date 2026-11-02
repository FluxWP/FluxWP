/* Flux Tasks — durum: açılış verisi, kapsam (liste/proje/benim), görevler, filtre/sıralama/gruplama, canlı güncelleme. */
(function () {
	'use strict';
	var Y = window.FLXG;
	var t = Y.t;
	var S = (Y.S = {
		users: {},
		userList: [],
		spaces: [],
		folders: [],
		lists: [],
		docs: [],
		tags: [],
		config: {},
		unread: 0,
		saving: {},
		scope: null,
		views: [],
		view: null,
		draft: null,
		fields: [],
		tasks: {},
		since: '',
		q: '',
		selected: {},
		lastNotif: 0,
		rev: 0
	});

	Y.applyStructure = function (d) {
		if (!d) return;
		if (d.spaces) S.spaces = d.spaces;
		if (d.folders) S.folders = d.folders;
		if (d.lists) S.lists = d.lists;
		if (d.docs) S.docs = d.docs;
		if (d.tags) S.tags = d.tags;
		if (typeof d.rev === 'number') S.rev = d.rev;
		Y.emit('structure');
	};

	Y.loadBoot = function () {
		return Y.api('GET', 'boot').then(function (d) {
			S.me = d.me;
			S.userList = d.users;
			S.users = {};
			d.users.forEach(function (u) { S.users[u.id] = u; });
			if (!S.users[d.me.id]) S.users[d.me.id] = d.me;
			S.config = d.config;
			S.unread = d.unread;
			Y.applyStructure(d);
			return d;
		});
	};

	/* ---------------- Olaylar ---------------- */
	var handlers = {};
	Y.on = function (ev, fn) { (handlers[ev] = handlers[ev] || []).push(fn); };
	Y.emit = function (ev, a) { (handlers[ev] || []).forEach(function (fn) { try { fn(a); } catch (e) { console.error(e); } }); };

	/* ---------------- Yapı yardımcıları ---------------- */
	Y.list = function (id) { return S.lists.filter(function (l) { return l.id === +id; })[0] || null; };
	Y.space = function (id) { return S.spaces.filter(function (s) { return s.id === +id; })[0] || null; };
	Y.folder = function (id) { return S.folders.filter(function (f) { return f.id === +id; })[0] || null; };
	Y.tag = function (id) { return S.tags.filter(function (x) { return x.id === +id; })[0] || null; };
	Y.rank = function (r) { return { view: 1, comment: 2, edit: 3, admin: 4 }[r] || 0; };
	Y.canList = function (id, need) { var l = Y.list(id); return !!l && Y.rank(l.role) >= Y.rank(need || 'view'); };
	Y.status = function (task) {
		var l = Y.list(task.list);
		if (!l) return { key: task.status, label: task.status, color: '#94a3b8', type: 'open' };
		for (var i = 0; i < l.statuses.length; i++) if (l.statuses[i].key === task.status) return l.statuses[i];
		return l.statuses[0];
	};
	Y.isDone = function (task) { return Y.status(task).type === 'done'; };
	Y.priority = function (n) {
		return (S.config.priorities || []).filter(function (p) { return p.id === +n; })[0] || null;
	};
	Y.scopeLists = function () {
		if (!S.scope) return [];
		if (S.scope.type === 'list') return [Y.list(S.scope.id)].filter(Boolean);
		if (S.scope.type === 'space') return S.lists.filter(function (l) { return l.space_id === S.scope.id; });
		return S.lists.slice();
	};
	Y.scopeTags = function () {
		if (!S.scope) return S.tags;
		var spaces = {};
		Y.scopeLists().forEach(function (l) { spaces[l.space_id] = 1; });
		return S.tags.filter(function (x) { return spaces[x.space_id]; });
	};
	/* Kapsamdaki durumlar (çok listede etikete göre birleşik). */
	Y.scopeStatuses = function () {
		var out = [];
		var seen = {};
		Y.scopeLists().forEach(function (l) {
			l.statuses.forEach(function (s) {
				var k = Y.norm(s.label);
				if (!seen[k]) { seen[k] = 1; out.push({ key: k, label: s.label, color: s.color, type: s.type }); }
			});
		});
		return out;
	};
	Y.fieldsFor = function (listId) {
		return S.fields.filter(function (f) { return f.list_id === +listId; });
	};
	/* Kapsamdaki alanlar; aynı adlı alanlar tek sütun olur (proje görünümü). */
	Y.scopeFields = function () {
		var out = [];
		var seen = {};
		S.fields.forEach(function (f) {
			var k = Y.norm(f.name) + '|' + f.type;
			if (seen[k]) { seen[k].ids.push(f.id); return; }
			var g = { key: 'f' + f.id, id: f.id, ids: [f.id], name: f.name, type: f.type, options: f.options };
			seen[k] = g;
			out.push(g);
		});
		return out;
	};
	Y.fieldValue = function (task, col) {
		var ids = col.ids || [col.id];
		for (var i = 0; i < ids.length; i++) {
			var v = task.fields && task.fields[ids[i]];
			if (v !== undefined && v !== null) return { v: v, field: S.fields.filter(function (f) { return f.id === ids[i]; })[0] };
		}
		return null;
	};

	/* ---------------- Kapsam yükleme ---------------- */
	var loadSeq = 0;
	Y.loadScope = function (type, id) {
		var seq = ++loadSeq;
		var meta = type === 'list' ? Y.api('GET', 'lists/' + id) : type === 'space' ? Y.api('GET', 'spaces/' + id) : Y.api('GET', 'home');
		var q = type === 'list' ? { list: id } : type === 'space' ? { space: id } : { mine: 1 };
		var tasks = Y.api('GET', 'tasks', q);
		return Promise.all([meta, tasks]).then(function (r) {
			if (seq !== loadSeq) return false;
			S.scope = { type: type, id: +id || 0, role: r[0].role || (type === 'home' ? 'admin' : 'view') };
			S.views = r[0].views || [];
			S.fields = type === 'list' ? r[0].fields || [] : r[1].fields || r[0].fields || [];
			S.tasks = {};
			S.keep = {};
			r[1].tasks.forEach(function (x) { S.tasks[x.id] = x; });
			S.since = r[1].time;
			S.selected = {};
			return true;
		});
	};
	Y.reloadTasks = function () {
		if (!S.scope) return Promise.resolve();
		var q = S.scope.type === 'list' ? { list: S.scope.id } : S.scope.type === 'space' ? { space: S.scope.id } : { mine: 1 };
		var keep = Object.keys(S.keep || {});
		if (keep.length) q.keep = keep.slice(-100).join(',');
		return Y.api('GET', 'tasks', q).then(function (r) {
			S.tasks = {};
			r.tasks.forEach(function (x) { S.tasks[x.id] = x; });
			if (r.fields && S.scope.type !== 'list') S.fields = r.fields;
			S.since = r.time;
			Y.emit('tasks');
		});
	};
	Y.setView = function (v) {
		S.view = v;
		S.draft = v ? JSON.parse(JSON.stringify(v.config)) : null;
		S.dirty = false;
	};
	Y.viewCfg = function () { return S.draft || {}; };
	Y.touchDraft = function () { S.dirty = true; Y.emit('view'); };

	/* ---------------- Filtre / sıralama / gruplama ---------------- */
	function inScopeTask(task) {
		if (!S.scope) return false;
		if (S.scope.type === 'list') return task.list === S.scope.id;
		if (S.scope.type === 'space') { var l = Y.list(task.list); return !!l && l.space_id === S.scope.id; }
		return task.assignees.indexOf(S.me.id) > -1;
	}
	Y.inScopeTask = inScopeTask;

	/** Görünümde üst satır olarak çizilir mi: üst görevi yoksa ya da üst görevi bu kapsamda yüklü değilse. */
	Y.isTop = function (x) {
		var p = x.parent ? S.tasks[x.parent] : null;
		return !p || p.archived;
	};

	/** Görev, görünümün arama ve filtrelerine uyuyor mu? */
	Y.taskMatches = function (x) {
		var c = Y.viewCfg();
		var f = c.filters || {};
		var q = Y.norm(S.q || f.q || '');
		var today = Y.dayKey(new Date());
		var week = Y.addDays(today, 7);
		var st = Y.status(x);
		if (c.showDone === false && st.type === 'done') return false;
		if (q) {
			var hay = Y.norm(x.title) + ' #' + x.id;
			if (hay.indexOf(q) < 0 && String(x.id) !== q.replace('#', '')) return false;
		}
		if (f.status && f.status.length && f.status.indexOf(Y.norm(st.label)) < 0) return false;
		if (f.assignees && f.assignees.length) {
			var ok = f.assignees.some(function (a) {
				if (a === 'none') return x.assignees.length === 0;
				if (a === 'me') return x.assignees.indexOf(S.me.id) > -1;
				return x.assignees.indexOf(+a) > -1;
			});
			if (!ok) return false;
		}
		if (f.priority && f.priority.length && f.priority.indexOf(String(x.priority || 0)) < 0) return false;
		if (f.tags && f.tags.length && !f.tags.some(function (tg) { return tg === 'none' ? x.tags.length === 0 : x.tags.indexOf(+tg) > -1; })) return false;
		if (f.due) {
			var d = x.due ? x.due.slice(0, 10) : '';
			if (f.due === 'overdue' && !(d && d < today && st.type !== 'done')) return false;
			if (f.due === 'today' && d !== today) return false;
			if (f.due === 'week' && !(d && d >= today && d <= week)) return false;
			if (f.due === 'none' && d) return false;
			if (f.due === 'has' && !d) return false;
		}
		if (f.fields) {
			var cols = Y.scopeFields();
			var pass = Object.keys(f.fields).every(function (key) {
				var want = f.fields[key];
				var col = cols.filter(function (g) { return g.key === key; })[0];
				if (!col) return true;
				var fv = Y.fieldValue(x, col);
				var v = fv ? fv.v : null;
				var empty = v === null || v === '' || (Array.isArray(v) && !v.length) || v === false;
				if (want === '__set') return !empty;
				if (want === '__empty') return empty;
				if (Array.isArray(want)) {
					if (!want.length) return true;
					if (empty) return want.indexOf('__empty') > -1;
					if (Array.isArray(v)) return v.some(function (z) { return want.indexOf(String(z)) > -1; });
					return want.indexOf(String(v)) > -1;
				}
				if (col.type === 'checkbox') return want === 'yes' ? v === true : !v;
				if (col.type === 'rating') return +v >= +want;
				return true;
			});
			if (!pass) return false;
		}
		return true;
	};

	/** Üst görev → doğrudan alt görevler (arşivsiz, görünüm sırasıyla). */
	/** Toplantının gündem maddeleri ve kararları görünümlerde ayrı satır olmaz (kararlar "Benim görevlerim"de görünür). */
	Y.hiddenKind = function (x) {
		return x.kind === 'agenda' || (x.kind === 'decision' && !(S.scope && S.scope.type === 'home'));
	};
	Y.childIndex = function () {
		var idx = {};
		Object.keys(S.tasks).forEach(function (id) {
			var x = S.tasks[id];
			if (x.archived || !x.parent || !S.tasks[x.parent] || Y.hiddenKind(x)) return;
			(idx[x.parent] = idx[x.parent] || []).push(x);
		});
		var sort = Y.viewCfg().sort;
		Object.keys(idx).forEach(function (k) { Y.sortTasks(idx[k], sort); });
		return idx;
	};

	/**
	 * Üst satırlar. opts.includeSub: alt görevler de ayrı satır olarak gelir (pano/takvim değil, eski davranış).
	 * opts.context: alt görevi filtreye uyan ama kendisi uymayan üst görevler de (x._ctx = true) gelir.
	 */
	Y.filtered = function (opts) {
		opts = opts || {};
		var c = Y.viewCfg();
		var out = [];
		var have = {};
		var ctx = {};
		Object.keys(S.tasks).forEach(function (id) {
			var x = S.tasks[id];
			x._ctx = false;
			if (x.archived || Y.hiddenKind(x)) return;
			var top = opts.includeSub || Y.isTop(x);
			if (Y.taskMatches(x)) {
				if (top) { out.push(x); have[x.id] = 1; return; }
				if (opts.context) {
					// Filtreye uyan alt görevin en üstteki görevi bağlam olarak gösterilir.
					var p = x;
					for (var i = 0; i < 12 && p && !Y.isTop(p); i++) p = S.tasks[p.parent];
					if (p && p !== x) ctx[p.id] = 1;
				}
			}
		});
		Object.keys(ctx).forEach(function (id) {
			if (have[id]) return;
			var x = S.tasks[id];
			x._ctx = true;
			out.push(x);
			have[id] = 1;
		});
		// Kullanıcının bu ekranda değiştirdiği görevler (ör. başkasına atandı, durumu filtre dışına çıktı)
		// sayfa değişene kadar görünür kalır; "filtre dışı" diye işaretlenir.
		out.forEach(function (x) { x._outside = !!(S.keep && S.keep[x.id] && !inScopeTask(x)); });
		Object.keys(S.keep || {}).forEach(function (id) {
			var x = S.tasks[id];
			if (!x || have[x.id] || x.archived || Y.hiddenKind(x) || (!opts.includeSub && !Y.isTop(x))) return;
			x._outside = true;
			out.push(x);
		});
		return Y.sortTasks(out, c.sort);
	};

	Y.sortTasks = function (arr, sort) {
		sort = sort || { by: 'position', dir: 'asc' };
		var dir = sort.dir === 'desc' ? -1 : 1;
		var key = sort.by;
		arr.sort(function (a, b) {
			var r = 0;
			if (key === 'due' || key === 'start') {
				var da = a[key] || '';
				var db = b[key] || '';
				if (!da && db) return 1;
				if (da && !db) return -1;
				r = da < db ? -1 : da > db ? 1 : 0;
			} else if (key === 'priority') {
				var pa = a.priority || 9;
				var pb = b.priority || 9;
				r = pa - pb;
			} else if (key === 'created' || key === 'updated') {
				r = (a[key] || '') < (b[key] || '') ? -1 : 1;
			} else if (key === 'title') {
				r = Y.cmpStr(a.title, b.title);
			} else {
				r = a.pos - b.pos || a.id - b.id;
			}
			return r * dir || a.id - b.id;
		});
		return arr;
	};

	Y.groupOptions = function () {
		var opts = [
			{ id: 'status', label: t('Durum') },
			{ id: 'priority', label: t('Öncelik') },
			{ id: 'assignee', label: t('Atanan') },
			{ id: 'due', label: t('Bitiş tarihi') },
			{ id: 'tag', label: t('Etiket') }
		];
		if (S.scope && S.scope.type !== 'list') opts.push({ id: 'list', label: t('Liste') });
		Y.scopeFields().forEach(function (f) {
			if (f.type === 'dropdown') opts.push({ id: f.key, label: f.name });
		});
		opts.push({ id: 'none', label: t('Gruplama yok') });
		return opts;
	};

	/**
	 * Gruplar: [{ key, label, color, tasks[], patch(task) → değişiklik | null (bırakılamaz) }]
	 */
	Y.groups = function (tasks, by) {
		by = by || 'status';
		var groups = [];
		var map = {};
		function g(key, label, color, patch, extra) {
			if (!map[key]) {
				map[key] = { key: key, label: label, color: color, tasks: [], patch: patch };
				if (extra) Object.keys(extra).forEach(function (k) { map[key][k] = extra[k]; });
				groups.push(map[key]);
			}
			return map[key];
		}
		if (by === 'status') {
			Y.scopeStatuses().forEach(function (s) {
				g('st:' + s.key, s.label, s.color, function (task) {
					var l = Y.list(task.list);
					var m = l && l.statuses.filter(function (x) { return Y.norm(x.label) === s.key; })[0];
					return m ? { status: m.key } : null;
				}, { statusType: s.type, statusKey: s.key });
			});
			tasks.forEach(function (x) { var st = Y.status(x); (map['st:' + Y.norm(st.label)] || g('st:' + Y.norm(st.label), st.label, st.color, null)).tasks.push(x); });
		} else if (by === 'priority') {
			(S.config.priorities || []).forEach(function (p) {
				g('pr:' + p.id, p.label, p.color, function () { return { priority: p.id }; }, { priority: p.id });
			});
			g('pr:0', t('Öncelik yok'), '#cbd5e1', function () { return { priority: 0 }; }, { priority: 0 });
			tasks.forEach(function (x) { map['pr:' + (x.priority || 0)].tasks.push(x); });
		} else if (by === 'assignee') {
			var seen = {};
			tasks.forEach(function (x) { x.assignees.forEach(function (u) { seen[u] = 1; }); });
			var ids = Object.keys(seen).map(Number).sort(function (a, b) {
				if (a === S.me.id) return -1;
				if (b === S.me.id) return 1;
				return Y.cmpStr((S.users[a] || {}).name, (S.users[b] || {}).name);
			});
			ids.forEach(function (u) {
				var usr = S.users[u];
				g('as:' + u, usr ? usr.name : t('Silinmiş kullanıcı'), usr ? usr.color : '#94a3b8', function (task, from) {
					var list = task.assignees.slice();
					if (from && from.indexOf('as:') === 0) list = list.filter(function (z) { return z !== +from.slice(3); });
					if (list.indexOf(u) < 0) list.push(u);
					return { assignees: list };
				}, { user: u });
			});
			g('as:none', t('Atanmamış'), '#cbd5e1', function () { return { assignees: [] }; });
			tasks.forEach(function (x) {
				if (!x.assignees.length) map['as:none'].tasks.push(x);
				x.assignees.forEach(function (u) { map['as:' + u].tasks.push(x); });
			});
		} else if (by === 'due') {
			var today = Y.dayKey(new Date());
			var tomorrow = Y.addDays(today, 1);
			var week = Y.addDays(today, 7);
			g('due:overdue', t('Gecikmiş'), '#dc2626', null);
			g('due:today', t('Bugün'), '#f59e0b', function (task) { return { due: today + (task.due && task.due.length > 10 ? task.due.slice(10) : '') }; });
			g('due:tomorrow', t('Yarın'), '#3b82f6', function (task) { return { due: tomorrow + (task.due && task.due.length > 10 ? task.due.slice(10) : '') }; });
			g('due:week', t('Bu hafta'), '#8b5cf6', null);
			g('due:later', t('Daha sonra'), '#64748b', null);
			g('due:none', t('Tarihsiz'), '#cbd5e1', function () { return { due: '' }; });
			tasks.forEach(function (x) {
				var d = x.due ? x.due.slice(0, 10) : '';
				var k = !d ? 'none' : d < today ? 'overdue' : d === today ? 'today' : d === tomorrow ? 'tomorrow' : d <= week ? 'week' : 'later';
				map['due:' + k].tasks.push(x);
			});
		} else if (by === 'tag') {
			Y.scopeTags().forEach(function (tg) { g('tg:' + tg.id, tg.name, tg.color, null); });
			g('tg:none', t('Etiketsiz'), '#cbd5e1', null);
			tasks.forEach(function (x) {
				if (!x.tags.length) map['tg:none'].tasks.push(x);
				x.tags.forEach(function (id) { if (map['tg:' + id]) map['tg:' + id].tasks.push(x); });
			});
			groups = groups.filter(function (gr) { return gr.tasks.length || gr.key === 'tg:none'; });
		} else if (by === 'list') {
			Y.scopeLists().forEach(function (l) {
				g('ls:' + l.id, l.name, l.color, Y.rank(l.role) >= 3 ? function () { return { list: l.id }; } : null, { list: l.id });
			});
			tasks.forEach(function (x) { if (map['ls:' + x.list]) map['ls:' + x.list].tasks.push(x); });
		} else if (by.indexOf('f') === 0 && by !== 'none') {
			var col = Y.scopeFields().filter(function (f) { return f.key === by; })[0];
			if (col) {
				(col.options.choices || []).forEach(function (c) {
					g('fv:' + c.id, c.label, c.color, function (task) {
						var fid = col.ids.filter(function (id) { return Y.fieldsFor(task.list).some(function (f) { return f.id === id; }); })[0];
						if (!fid) return null;
						var o = {};
						o[fid] = c.id;
						return { fields: o };
					}, { choice: c.id });
				});
				g('fv:none', t('Boş'), '#cbd5e1', function (task) {
					var fid = col.ids.filter(function (id) { return Y.fieldsFor(task.list).some(function (f) { return f.id === id; }); })[0];
					if (!fid) return null;
					var o = {};
					o[fid] = null;
					return { fields: o };
				});
				tasks.forEach(function (x) {
					var fv = Y.fieldValue(x, col);
					var k = fv && map['fv:' + fv.v] ? 'fv:' + fv.v : 'fv:none';
					map[k].tasks.push(x);
				});
			}
		}
		if (!groups.length) {
			g('all', t('Tüm görevler'), '#94a3b8', function () { return {}; }).tasks = tasks;
		}
		return groups;
	};

	/* ---------------- Görev değişiklikleri ---------------- */
	Y.upsertTask = function (task) {
		if (!task) return;
		var old = S.tasks[task.id];
		if (old && old.hasDesc !== undefined && task.hasDesc === undefined) task.hasDesc = !!(task.description && task.description.replace(/<img[^>]*>/g, 'x').replace(/<[^>]+>/g, '').trim());
		delete task.description;
		delete task.comments_list;
		delete task.activity;
		delete task.attachments;
		delete task.children;
		delete task.subtree;
		if (inScopeTask(task) || (S.scope && S.scope.type === 'home' && old)) S.tasks[task.id] = task;
		else delete S.tasks[task.id];
	};

	/** İyimser güncelleme: yerelde hemen uygular, sunucu cevabıyla düzeltir. */
	/** Bu ekranda dokunulan görev, filtreye uymasa da sayfa değişene kadar görünür kalır. */
	Y.keepTask = function (id) {
		if (!S.keep) S.keep = {};
		if (id) S.keep[id] = 1;
	};
	Y.patchTask = function (id, patch, opts) {
		opts = opts || {};
		Y.keepTask(id);
		var old = S.tasks[id] ? JSON.parse(JSON.stringify(S.tasks[id])) : null;
		var cur = S.tasks[id];
		// Değişiklik ekranda hemen görünür; kaydedilene kadar satır hafif soluk (S.saving).
		S.saving[id] = (S.saving[id] || 0) + 1;
		var done = function () { S.saving[id] = Math.max(0, (S.saving[id] || 1) - 1); if (!S.saving[id]) delete S.saving[id]; };
		if (cur) {
			Object.keys(patch).forEach(function (k) {
				if (k === 'fields') {
					cur.fields = Object.assign({}, cur.fields);
					Object.keys(patch.fields).forEach(function (f) {
						if (patch.fields[f] === null || patch.fields[f] === '') delete cur.fields[f];
						else cur.fields[f] = patch.fields[f];
					});
				} else if (k === 'list') cur.list = +patch.list;
				else if (k === 'position') cur.pos = patch.position;
				else if (k === 'cover') cur.cover = patch.cover;
				else if (['tag_names', 'add_assignees', 'remove_assignees', 'detail'].indexOf(k) < 0) cur[k] = patch[k];
			});
			if (!opts.silent) Y.emit('tasks');
		}
		return Y.api('PATCH', 'tasks/' + id, patch).then(function (r) {
			done();
			if (!r.task) Y.emit('tasks');
			if (r.task) {
				if (patch.detail) {
					Y.upsertTask(JSON.parse(JSON.stringify(r.task)));
				} else {
					Y.upsertTask(r.task);
				}
				Y.emit('tasks');
				Y.emit('task-saved', r.task);
			}
			return r.task;
		}, function (e) {
			done();
			if (old) S.tasks[id] = old;
			Y.emit('tasks');
			Y.fail(e);
			throw e;
		});
	};

	Y.createTask = function (data) {
		return Y.api('POST', 'tasks', data).then(function (r) {
			Y.upsertTask(r.task);
			Y.emit('tasks');
			return r.task;
		});
	};

	Y.deleteTask = function (id) {
		return Y.api('DELETE', 'tasks/' + id).then(function () {
			delete S.tasks[id];
			Y.emit('tasks');
		});
	};

	/* ---------------- Canlı güncelleme (hafif yoklama) ----------------
	 * Kullanıcı ekrandayken 10 sn, 2 dk hareketsizse config.poll (varsayılan 45), gizliyken hiç istek yok.
	 * Aynı anda tek istek; hata olursa bekleme 5 dakikaya kadar katlanır.
	 */
	var syncTimer = null;
	var inFlight = false;
	var failCount = 0;
	var lastSync = 0;
	var lastInput = Date.now();
	function interval() {
		var base = Math.max(15, S.config.poll || 45) * 1000;
		if (failCount) return Math.min(300000, base * Math.pow(2, failCount));
		// Kullanıcı ekrandayken 10 sn (diğerlerinin değişiklikleri hızlı gelsin), 2 dk hareketsizse ayardaki aralık.
		return Date.now() - lastInput < 120000 ? Math.min(base, 10000) : base;
	}
	var inputThrottle = 0;
	['pointerdown', 'keydown', 'wheel', 'touchstart', 'mousemove'].forEach(function (ev) {
		window.addEventListener(ev, function () {
			var now = Date.now();
			if (now - inputThrottle < 2000) return;
			inputThrottle = now;
			var wasIdle = now - lastInput >= 120000;
			lastInput = now;
			// Boştan dönünce beklemeden tazele.
			if (wasIdle && !failCount && !inFlight && now - lastSync > 10000) schedule(300);
		}, { passive: true, capture: true });
	});
	function schedule(ms) {
		clearTimeout(syncTimer);
		if (document.hidden) return;
		syncTimer = setTimeout(Y.sync, ms === undefined ? interval() : ms);
	}
	Y.sync = function () {
		if (inFlight || document.hidden || !S.me) return;
		inFlight = true;
		lastSync = Date.now();
		var q = { since: S.since, after: S.lastNotif };
		if (S.scope) {
			if (S.scope.type === 'list') q.list = S.scope.id;
			else if (S.scope.type === 'space') q.space = S.scope.id;
			else q.mine = 1;
		}
		if (S.openTask) q.task = S.openTask;
		Y.api('GET', 'sync', q).then(function (d) {
			inFlight = false;
			failCount = 0;
			if (S.since && S.scope) {
				var changed = false;
				(d.gone || []).forEach(function (id) { if (S.tasks[id]) { delete S.tasks[id]; changed = true; } });
				(d.tasks || []).forEach(function (x) {
					var o = S.tasks[x.id];
					if (!o || o.updated !== x.updated || o.comments !== x.comments || o.files !== x.files) { Y.upsertTask(x); changed = true; }
				});
				if (changed) Y.emit('tasks');
			}
			S.since = d.time;
			if (d.unread !== S.unread) { S.unread = d.unread; Y.emit('unread'); }
			if (d.notifs && d.notifs.length) {
				S.lastNotif = Math.max(S.lastNotif, d.notifs[0].id);
				Y.emit('notifs', d.notifs);
			}
			if (d.rev !== S.rev) Y.loadBoot().then(function () { Y.emit('structure'); });
			if (d.taskChanged) Y.emit('task-remote');
			schedule();
		}, function () {
			inFlight = false;
			failCount = Math.min(failCount + 1, 5);
			schedule();
		});
	};
	Y.startSync = function () { schedule(); };
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { clearTimeout(syncTimer); return; }
		lastInput = Date.now();
		if (Date.now() - lastSync > 10000) schedule(300);
		else schedule();
	});
})();
