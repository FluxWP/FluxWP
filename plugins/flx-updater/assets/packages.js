/**
 * Flux Updater › Güncellemeler: gerekli eklenti uyarıları ve sayfadan çıkmadan kurma/güncelleme.
 * Veri: #flxu-packages[data-deps] = {pk: {slug: {name, state, req[]}}, pl: {slug: {name, installed, active, flx}}}
 */
(function () {
	'use strict';
	var L = window.flxuL10n || {};
	var data = { pk: {}, pl: {} };
	var extra = [];      // Tabloda satırı olmayan (ya da kurulu ama etkin olmayan) gerekli eklentiler.
	var notes = [];      // Bir sonraki çizimde gösterilecek bilgi satırları.
	var busy = false;
	var doneMarks = {};  // slug => {ok, html}: tablo yenilenince satır sonucu korunur.

	function fmt(s) {
		var a = Array.prototype.slice.call(arguments, 1), i = 0;
		return String(s || '').replace(/%(\d+\$)?s|%(\d+\$)?d/g, function (m, p1, p2) {
			var n = p1 || p2;
			return String(n ? a[parseInt(n, 10) - 1] : a[i++]);
		});
	}
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = String(s);
		return d.innerHTML;
	}
	function form() { return document.getElementById('flxu-packages'); }
	function load() {
		var f = form();
		try { data = JSON.parse(f.getAttribute('data-deps') || '{}'); } catch (e) { data = {}; }
		data.pk = data.pk || {};
		data.pl = data.pl || {};
	}
	function box(slug) { return form().querySelector('.flxu-check[value="' + slug + '"]'); }
	function selected() {
		return Array.prototype.map.call(form().querySelectorAll('.flxu-check:checked'), function (b) { return b.value; });
	}
	function name(slug) {
		return (data.pk[slug] && data.pk[slug].name) || (data.pl[slug] && data.pl[slug].name) || slug;
	}
	function active(slug) { return !!(data.pl[slug] && data.pl[slug].active); }
	function satisfied(dep, sel) {
		return active(dep) || sel.indexOf(dep) > -1 || extra.indexOf(dep) > -1;
	}
	function req(slug) { return (data.pk[slug] && data.pk[slug].req) || []; }

	/** Seçimdeki eksik gerekli eklentiler: [{by, dep}] */
	function missing(sel) {
		var out = [];
		sel.concat(extra).forEach(function (s) {
			req(s).forEach(function (d) {
				if (!satisfied(d, sel)) { out.push({ by: s, dep: d }); }
			});
		});
		return out;
	}
	/** Bir slug ve ona gereken her şey (özyinelemeli), etkin olmayanlar. */
	function closure(slug, acc) {
		acc = acc || [];
		req(slug).forEach(function (d) {
			if (!active(d) && acc.indexOf(d) < 0) { acc.push(d); closure(d, acc); }
		});
		return acc;
	}

	function addDep(dep) {
		var b = box(dep);
		if (b) { b.checked = true; } else if (extra.indexOf(dep) < 0) { extra.push(dep); }
		draw();
	}
	/** Seçimden çıkan paketi gerektiren seçili paketleri de çıkar (zincirleme). */
	function dropDependents(gone) {
		var queue = [gone];
		while (queue.length) {
			var g = queue.shift();
			if (active(g)) { continue; }
			selected().forEach(function (s) {
				if (req(s).indexOf(g) > -1) {
					box(s).checked = false;
					notes.push(fmt(L.removed, name(s), name(g)));
					queue.push(s);
				}
			});
		}
		// Artık kimsenin gerektirmediği ek eklentiler sessizce çıkar.
		var sel = selected();
		extra = extra.filter(function (x) {
			return sel.concat(extra).some(function (s) { return s !== x && req(s).indexOf(x) > -1; });
		});
	}

	function draw() {
		var f = form(); if (!f) { return; }
		var sel = selected();
		var btn = document.getElementById('flxu-do-selected');
		if (btn) { btn.disabled = busy || !sel.length; }
		var panel = document.getElementById('flxu-deps');
		var html = '';
		notes.forEach(function (n) { html += '<p class="flxu-deps__note">' + esc(n) + '</p>'; });
		notes = [];
		missing(sel).forEach(function (m) {
			var pl = data.pl[m.dep] || {};
			html += '<p class="flxu-deps__miss"><span class="dashicons dashicons-warning"></span> ' +
				esc(fmt(L.needs, name(m.by), name(m.dep), pl.installed ? L.inactive : L.notInstalled)) +
				' <button type="button" class="button button-small flxu-add-dep" data-dep="' + esc(m.dep) + '">' + esc(fmt(L.addDep, name(m.dep))) + '</button></p>';
		});
		if (extra.length) {
			html += '<p class="flxu-deps__extra"><span class="dashicons dashicons-plus-alt2"></span> ' + esc(L.together) + ' ';
			extra.forEach(function (x) {
				var pl = data.pl[x] || {};
				html += '<span class="flxu-chip">' + esc(name(x)) + (!pl.flx && !pl.installed ? ' <small>' + esc(L.wporg) + '</small>' : '') +
					' <button type="button" class="flxu-chip__x flxu-drop-dep" data-dep="' + esc(x) + '" aria-label="' + esc(L.remove) + '">×</button></span> ';
			});
			html += '</p>';
		}
		panel.innerHTML = html;
		panel.hidden = !html;
		// Satırdaki "Gerekli:" işaretleri.
		f.querySelectorAll('.flxu-row').forEach(function (tr) {
			var b = tr.querySelector('.flxu-check');
			tr.classList.toggle('flxu-row--sel', !!(b && b.checked));
		});
	}

	/* ---------------- Sayfadan çıkmadan çalıştırma ---------------- */

	function post(params) {
		var f = form();
		var body = new URLSearchParams();
		body.append('nonce', f.getAttribute('data-nonce'));
		Object.keys(params).forEach(function (k) {
			var v = params[k];
			if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
		});
		return fetch(f.getAttribute('data-ajax'), { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) {
				return r.text().then(function (t) {
					var j = null;
					try { j = JSON.parse(t); } catch (e) { /* PHP hatası JSON'u bozdu */ }
					if (!r.ok || !j || !j.success) {
						var msg = (j && j.data && j.data.message) || ('HTTP ' + r.status);
						throw new Error(msg);
					}
					return j.data;
				});
			});
	}

	var log = {
		el: function () { return document.getElementById('flxu-log'); },
		start: function (title) {
			var el = this.el();
			el.hidden = false;
			el.classList.add('is-busy');
			el.classList.remove('is-ok', 'is-err');
			el.querySelector('.flxu-log__lines').innerHTML = '';
			el.querySelector('.flxu-log__lines').hidden = false;
			el.querySelector('.flxu-log__toggle').textContent = L.hideLog;
			this.title(title);
			this.progress(0, 0);
			el.scrollIntoView({ behavior: 'smooth', block: 'start' });
		},
		title: function (t) { this.el().querySelector('.flxu-log__title').textContent = t; },
		progress: function (i, n) {
			var el = this.el();
			el.querySelector('.flxu-log__count').textContent = n ? fmt(L.stepOf, i, n) : '';
			el.querySelector('.flxu-log__bar span').style.width = (n ? Math.round(i / n * 100) : 5) + '%';
		},
		line: function (text, state) {
			var li = document.createElement('li');
			li.className = 'flxu-log__line is-' + (state || 'run');
			var t = new Date();
			li.innerHTML = '<time>' + ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2) + ':' + ('0' + t.getSeconds()).slice(-2) + '</time><span class="flxu-log__ico"></span><span class="flxu-log__txt"></span>';
			li.querySelector('.flxu-log__txt').textContent = text;
			this.el().querySelector('.flxu-log__lines').appendChild(li);
			return li;
		},
		sub: function (li, text, ok) {
			var d = document.createElement('div');
			d.className = 'flxu-log__sub ' + (ok ? 'is-ok' : 'is-err');
			d.textContent = text;
			li.appendChild(d);
		},
		finish: function (okN, errN) {
			var el = this.el();
			el.classList.remove('is-busy');
			el.classList.add(errN ? 'is-err' : 'is-ok');
			this.title(fmt(L.done, okN, errN));
		}
	};

	function rowEl(slug) { return form().querySelector('.flxu-row[data-slug="' + slug + '"]'); }
	function rowRun(slug, kind) {
		var tr = rowEl(slug); if (!tr) { return; }
		tr.classList.add('flxu-row--busy');
		tr.classList.remove('flxu-row--queued');
		tr.querySelector('.flxu-run').hidden = false;
		tr.querySelector('.flxu-run__text').textContent = (L.short && L.short[kind]) || '';
		tr.querySelector('.flxu-idle').hidden = true;
		tr.querySelector('.flxu-done').hidden = true;
	}
	function rowQueued(slug) {
		var tr = rowEl(slug); if (!tr) { return; }
		tr.classList.add('flxu-row--queued');
		tr.querySelector('.flxu-done').hidden = false;
		tr.querySelector('.flxu-done').innerHTML = '<span class="flxu-badge">' + esc(L.short.queued) + '</span>';
		tr.querySelector('.flxu-idle').hidden = true;
	}
	function rowDone(slug, ok, text) {
		var html = '<span class="flxu-res ' + (ok ? 'is-ok' : 'is-err') + '"><span class="dashicons ' + (ok ? 'dashicons-yes-alt' : 'dashicons-dismiss') + '"></span>' + esc(text) + '</span>';
		doneMarks[slug] = html;
		var tr = rowEl(slug); if (!tr) { return; }
		tr.classList.remove('flxu-row--busy', 'flxu-row--queued');
		tr.classList.add(ok ? 'flxu-row--ok' : 'flxu-row--err');
		tr.querySelector('.flxu-run').hidden = true;
		tr.querySelector('.flxu-done').hidden = false;
		tr.querySelector('.flxu-done').innerHTML = html;
	}

	function setBadge(n) {
		document.querySelectorAll('#toplevel_page_flx-updater .update-plugins').forEach(function (b) {
			b.className = 'update-plugins count-' + n;
			var c = b.querySelector('.plugin-count'); if (c) { c.textContent = n; }
			b.style.display = n ? '' : 'none';
		});
	}

	/** İşlem bitince tabloyu ve geçmişi sayfayı yenilemeden tazele. */
	function refresh() {
		return fetch(window.location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
			var doc = new DOMParser().parseFromString(html, 'text/html');
			['flxu-status-wrap', 'flxu-packages', 'flxu-history-wrap'].forEach(function (id) {
				var neu = doc.getElementById(id), old = document.getElementById(id);
				if (neu && old) { old.replaceWith(document.importNode(neu, true)); }
			});
			load();
			extra = [];
			Object.keys(doneMarks).forEach(function (slug) {
				var tr = rowEl(slug); if (!tr) { return; }
				var d = tr.querySelector('.flxu-done');
				d.hidden = false;
				d.innerHTML = doneMarks[slug];
			});
			draw();
		}).catch(function () { /* yenilenemezse eski tablo kalır */ });
	}

	function run(params, title) {
		if (busy) { return; }
		busy = true;
		doneMarks = {};
		form().classList.add('is-busy');
		draw();
		log.start(title || L.preparing);
		var okN = 0, errN = 0;
		var planP = params.steps ? Promise.resolve({ steps: params.steps }) : post(params);
		planP.then(function (res) {
			var steps = res.steps || [];
			if (!steps.length) { log.line(L.nothing, 'ok'); return; }
			log.title(L.working);
			steps.forEach(function (st) { rowQueued(st.slug); });
			var i = 0;
			var next = function () {
				if (i >= steps.length) { return Promise.resolve(); }
				var st = steps[i];
				log.progress(i, steps.length);
				var li = log.line(fmt((L.kind && L.kind[st.kind]) || '%s', st.name) + (st.why ? ' (' + st.why + ')' : ''), 'run');
				rowRun(st.slug, st.kind);
				return post({ action: 'flxu_step', kind: st.kind, slug: st.slug, version: st.version || '' }).then(function (r) {
					(r.lines || []).forEach(function (l) { log.sub(li, l.text, l.ok); });
					li.className = 'flxu-log__line is-' + (r.ok ? 'ok' : 'err');
					r.ok ? okN++ : errN++;
					var last = (r.lines || []).filter(function (l) { return !l.ok; })[0] || (r.lines || [])[(r.lines || []).length - 1];
					rowDone(st.slug, r.ok, last ? last.text : (r.ok ? L.ok : L.fail));
					if (typeof r.pending === 'number') { setBadge(r.pending); }
				}).catch(function (e) {
					errN++;
					li.className = 'flxu-log__line is-err';
					log.sub(li, fmt(L.http, e.message), false);
					rowDone(st.slug, false, fmt(L.http, e.message));
				}).then(function () {
					i++;
					log.progress(i, steps.length);
					return next();
				});
			};
			return next();
		}).catch(function (e) {
			errN++;
			log.line(fmt(L.http, e.message), 'err');
		}).then(function () {
			log.finish(okN, errN);
			busy = false;
			return refresh();
		}).then(function () {
			var f = form(); if (f) { f.classList.remove('is-busy'); }
		});
	}

	/* ---------------- Olaylar ---------------- */

	document.addEventListener('change', function (e) {
		var f = form(); if (!f || !f.contains(e.target)) { return; }
		if (e.target.id === 'flxu-check-all') {
			f.querySelectorAll('.flxu-check').forEach(function (b) { b.checked = e.target.checked; });
			if (!e.target.checked) { extra = []; }
		} else if (e.target.classList.contains('flxu-check') && !e.target.checked) {
			dropDependents(e.target.value);
		}
		draw();
	});

	document.addEventListener('click', function (e) {
		var f = form(); if (!f) { return; }
		var t = e.target;
		if (t.closest('.flxu-add-dep')) { addDep(t.closest('.flxu-add-dep').getAttribute('data-dep')); return; }
		if (t.closest('.flxu-drop-dep')) {
			var d = t.closest('.flxu-drop-dep').getAttribute('data-dep');
			extra = extra.filter(function (x) { return x !== d; });
			dropDependents(d);
			draw();
			return;
		}
		if (t.closest('.flxu-log__toggle')) {
			var lines = document.querySelector('#flxu-log .flxu-log__lines');
			lines.hidden = !lines.hidden;
			t.closest('.flxu-log__toggle').textContent = lines.hidden ? L.showLog : L.hideLog;
			return;
		}
		var ck = t.closest('#flxu-check');
		if (ck && window.fetch) {
			e.preventDefault();
			if (busy || ck.classList.contains('is-busy')) { return; }
			ck.classList.add('is-busy');
			var lbl = document.getElementById('flxu-checked');
			if (lbl) { lbl.textContent = L.checking; }
			post({ action: 'flxu_check' }).then(function (res) {
				setBadge(res.pending);
				return refresh().then(function () {
					ck.classList.remove('is-busy');
					var l = document.getElementById('flxu-checked');
					if (l) { l.textContent = res.label + ' · ' + res.summary; l.className = 'flxu-checked ' + (res.ok ? 'is-ok' : 'is-err'); }
				});
			}).catch(function (err) {
				ck.classList.remove('is-busy');
				if (lbl) { lbl.textContent = err.message; lbl.className = 'flxu-checked is-err'; }
			});
			return;
		}
		var b = t.closest('button[name="flxu_do"]');
		if (!b || !f.contains(b) || !window.fetch) { return; }
		e.preventDefault();
		if (busy) { return; }
		var activate = document.getElementById('flxu-activate');
		activate = activate && activate.checked ? 1 : 0;

		if (b.value === 'version') {
			var v = b.parentNode.querySelector('select').value;
			if (!window.confirm(fmt(L.confirmVer, b.getAttribute('data-name'), v))) { return; }
			run({ steps: [{ kind: 'version', slug: b.getAttribute('data-slug'), name: b.getAttribute('data-name') + ' → ' + v, version: v }] });
			return;
		}
		if (b.value === 'all') {
			run({ action: 'flxu_plan', all: 1, activate: activate });
			return;
		}
		var sel = b.value === 'one' ? [b.getAttribute('data-slug')] : selected();
		if (!sel.length) { return; }
		// Eksik gerekli eklentiler: kullanıcıya sor, kabul ederse birlikte kur.
		var need = [], by = [];
		sel.forEach(function (s) {
			closure(s).forEach(function (d) {
				if (sel.indexOf(d) > -1 || extra.indexOf(d) > -1) { return; }
				if (need.indexOf(d) < 0) { need.push(d); }
				if (by.indexOf(s) < 0) { by.push(s); }
			});
		});
		if (need.length && !window.confirm(fmt(L.confirmDeps, by.map(name).join(', '), need.map(name).join(', ')))) { return; }
		run({ action: 'flxu_plan', slugs: sel, activate: b.value === 'one' ? 1 : activate });
	});

	window.addEventListener('beforeunload', function (e) {
		if (busy) { e.preventDefault(); e.returnValue = L.leave; return L.leave; }
	});

	if (form()) { load(); draw(); }
})();
