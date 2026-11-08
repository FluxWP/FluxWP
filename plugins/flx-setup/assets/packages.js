/**
 * FluxWP Panel › Güncellemeler: gerekli eklenti uyarıları ve sayfadan çıkmadan kurma/güncelleme.
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
	/** Kurulacak/güncellenecek seçim (kurulu ve güncel paketler hariç). */
	function selected() {
		return Array.prototype.filter.call(form().querySelectorAll('.flxu-check:checked'), function (b) {
			return b.getAttribute('data-state') !== 'current';
		}).map(function (b) { return b.value; });
	}
	/** Toplu işlem (etkinleştir/kapat/kaldır) için kurulu seçim. */
	function selectedInstalled() {
		return Array.prototype.filter.call(form().querySelectorAll('.flxu-check:checked'), function (b) {
			return b.getAttribute('data-state') !== 'missing';
		});
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
		var mb = document.getElementById('flxu-do-manage'), op = document.getElementById('flxu-bulk-op');
		if (mb) { mb.disabled = busy || !op || !op.value || !selectedInstalled().length; }
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

	/* İşlem bitene kadar ekranın üstünde sabit kalan "sayfadan ayrılmayın" şeridi (kaydırınca kaybolmaz). */
	var stay = {
		el: function () {
			var el = document.getElementById('flxu-stay');
			if (!el) {
				el = document.createElement('div');
				el.id = 'flxu-stay';
				el.className = 'flxu-stay';
				el.setAttribute('role', 'status');
				el.setAttribute('aria-live', 'polite');
				el.hidden = true;
				el.innerHTML = '<span class="flxu-stay__spin" aria-hidden="true"></span><span class="flxu-stay__body"><strong class="flxu-stay__title"></strong><span class="flxu-stay__step"></span></span><span class="flxu-stay__count"></span><button type="button" class="flxu-stay__close" aria-label="' + esc(L.close) + '" title="' + esc(L.close) + '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button><span class="flxu-stay__done" hidden><span class="flxu-stay__hint"></span><button type="button" class="flxu-stay__reload"><span class="dashicons dashicons-update" aria-hidden="true"></span><span></span></button></span><span class="flxu-stay__bar"><span></span></span>';
				el.querySelector('.flxu-stay__hint').textContent = L.reloadHint;
				el.querySelector('.flxu-stay__reload span:last-child').textContent = L.reload;
				el.querySelector('.flxu-stay__close').addEventListener('click', function () { stay.hide(); });
				el.querySelector('.flxu-stay__reload').addEventListener('click', function () { window.location.reload(); });
				document.body.appendChild(el);
			}
			return el;
		},
		show: function () {
			var el = this.el();
			clearTimeout(this.t);
			el.classList.remove('is-ok', 'is-err');
			el.querySelector('.flxu-stay__done').hidden = true;
			el.querySelector('.flxu-stay__title').textContent = L.stay || L.working;
			this.step('', 0, 0);
			el.hidden = false;
			document.body.classList.add('flxu-staying');
		},
		step: function (text, i, n) {
			var el = this.el();
			el.querySelector('.flxu-stay__step').textContent = text || '';
			el.querySelector('.flxu-stay__count').textContent = n ? fmt(L.stepOf, Math.min(i + 1, n), n) : '';
			el.querySelector('.flxu-stay__bar span').style.width = (n ? Math.round(i / n * 100) : 5) + '%';
		},
		done: function (okN, errN) {
			var el = this.el();
			el.classList.add(errN ? 'is-err' : 'is-ok');
			el.querySelector('.flxu-stay__title').textContent = fmt(L.done, okN, errN);
			el.querySelector('.flxu-stay__step').textContent = '';
			el.querySelector('.flxu-stay__count').textContent = '';
			el.querySelector('.flxu-stay__bar span').style.width = '100%';
			/* Değişen eklentilerin yeni kodu (menü, ekranlar) ancak sayfa yenilenince yüklenir: düğme göster, kullanıcı kapatana kadar kalsın. */
			if (okN) {
				el.querySelector('.flxu-stay__done').hidden = false;
				el.querySelector('.flxu-stay__reload').focus({ preventScroll: true });
			} else {
				this.t = setTimeout(function () { stay.hide(); }, 6000);
			}
		},
		hide: function () {
			clearTimeout(this.t);
			var el = document.getElementById('flxu-stay');
			if (el) { el.hidden = true; }
			document.body.classList.remove('flxu-staying');
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
		document.querySelectorAll('#toplevel_page_flx-setup .update-plugins').forEach(function (b) {
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
		stay.show();
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
				stay.step(fmt((L.kind && L.kind[st.kind]) || '%s', st.name), i, steps.length);
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
			stay.done(okN, errN);
		});
	}

	/** Güncellemeleri sunucudan kontrol et; tablo ve rozet sayfa yenilenmeden tazelenir. */
	function checkNow() {
		var ck = document.getElementById('flxu-check');
		if (!ck || !window.fetch || busy || ck.classList.contains('is-busy')) { return; }
		ck.classList.add('is-busy');
		var lbl = document.getElementById('flxu-checked');
		if (lbl) { lbl.textContent = L.checking; lbl.className = 'flxu-checked'; }
		post({ action: 'flxu_check' }).then(function (res) {
			setBadge(res.pending);
			/* Bu arada kurulum/güncelleme başladıysa tabloyu değiştirme; o iş bitince kendisi tazeler. */
			return (busy ? Promise.resolve() : refresh()).then(function () {
				ck.classList.remove('is-busy');
				var l = document.getElementById('flxu-checked');
				if (l) { l.textContent = res.label + ' · ' + res.summary; l.className = 'flxu-checked ' + (res.ok ? 'is-ok' : 'is-err'); }
			});
		}).catch(function (err) {
			ck.classList.remove('is-busy');
			if (lbl) { lbl.textContent = err.message; lbl.className = 'flxu-checked is-err'; }
		});
	}

	/* ---------------- Read-only package previews ---------------- */
	function previewURL(value) {
		try {
			var url = new URL(String(value || ''), location.href);
			if (url.protocol !== 'https:' || url.username || url.password) { return ''; }
			return url.href;
		} catch (error) { return ''; }
	}
	var previewDialog, previewTrigger;
	function makePreview() {
		if (previewDialog) { return previewDialog; }
		var dialog = document.createElement('dialog');
		dialog.id = 'flxu-preview'; dialog.className = 'flxu-preview';
		dialog.setAttribute('aria-labelledby', 'flxu-preview-title');
		dialog.setAttribute('aria-describedby', 'flxu-preview-description');
		dialog.innerHTML = '<div class="flxu-preview__head"><h2 id="flxu-preview-title"></h2><button type="button" class="flxu-preview__close" aria-label="' + esc(L.previewClose) + '">×</button></div>' +
			'<p id="flxu-preview-description" class="flxu-preview__description"></p><section class="flxu-preview__gallery" aria-label="' + esc(L.previewImages) + '"><p class="flxu-preview__label">' + esc(L.previewImages) + '</p><div class="flxu-preview__stage"><img alt=""><p class="flxu-preview__empty" hidden>' + esc(L.previewEmpty) + '</p></div><div class="flxu-preview__thumbs"></div></section>' +
			'<div class="flxu-preview__foot"><p>' + esc(L.previewMore) + '</p><a class="flxu-preview__website" target="_blank" rel="noopener noreferrer">' + esc(L.previewWebsite) + '<span aria-hidden="true">↗</span></a></div>';
		document.body.appendChild(dialog);
		dialog.querySelector('.flxu-preview__close').addEventListener('click', function () { dialog.close(); });
		dialog.addEventListener('click', function (e) {
			var rect = dialog.getBoundingClientRect();
			if (e.target === dialog && (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom)) { dialog.close(); }
		});
		dialog.addEventListener('close', function () {
			if (previewTrigger && previewTrigger.isConnected) { previewTrigger.focus(); }
		});
		previewDialog = dialog; return dialog;
	}
	function openPreview(slug, trigger) {
		var info = data.pk[slug] && data.pk[slug].preview;
		if (!info) { return; }
		var dialog = makePreview();
		if (typeof dialog.showModal !== 'function') { return; }
		previewTrigger = trigger;
		dialog.querySelector('h2').textContent = info.name || name(slug);
		dialog.querySelector('.flxu-preview__description').textContent = info.description || L.previewDescription;
		var website = dialog.querySelector('.flxu-preview__website');
		website.removeAttribute('href');
		var websiteURL = previewURL(info.website);
		if (websiteURL) { website.href = websiteURL; }
		dialog.querySelector('.flxu-preview__foot').hidden = !websiteURL;
		var stage = dialog.querySelector('.flxu-preview__stage');
		var image = dialog.querySelector('.flxu-preview__stage img'), empty = dialog.querySelector('.flxu-preview__empty');
		var thumbs = dialog.querySelector('.flxu-preview__thumbs');
		thumbs.replaceChildren();
		var images = (info.images || []).map(previewURL).filter(Boolean), failed = {};
		function show(index) {
			stage.classList.remove('is-empty');
			image.hidden = false; empty.hidden = true;
			image.alt = (info.name || name(slug)) + ' — ' + fmt(L.previewImage, index + 1);
			image.referrerPolicy = 'no-referrer'; image.decoding = 'async';
			image.onerror = function () {
				failed[index] = true;
				if (thumbs.children[index]) { thumbs.children[index].hidden = true; }
				var next = images.findIndex(function (_, i) { return !failed[i]; });
				if (next >= 0) { show(next); } else { image.hidden = true; empty.hidden = false; stage.classList.add('is-empty'); }
			};
			image.src = images[index];
			Array.prototype.forEach.call(thumbs.children, function (button, i) { button.setAttribute('aria-pressed', i === index ? 'true' : 'false'); });
		}
		images.forEach(function (url, index) {
			var button = document.createElement('button'); button.type = 'button'; button.className = 'flxu-preview__thumb';
			button.setAttribute('aria-label', fmt(L.previewImage, index + 1));
			var thumb = document.createElement('img'); thumb.alt = ''; thumb.loading = 'lazy'; thumb.decoding = 'async'; thumb.referrerPolicy = 'no-referrer'; thumb.src = url;
			thumb.onerror = function () { button.hidden = true; };
			button.appendChild(thumb); button.addEventListener('click', function () { show(index); }); thumbs.appendChild(button);
		});
		stage.classList.toggle('is-empty', !images.length);
		thumbs.hidden = images.length < 2;
		image.onerror = null; image.removeAttribute('src'); image.hidden = !images.length; empty.hidden = !!images.length;
		if (images.length) { show(0); }
		if (!dialog.open) { dialog.showModal(); }
		dialog.querySelector('.flxu-preview__close').focus();
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
		var preview = t.closest('.flxu-pkg');
		if (preview && f.contains(preview) && !t.closest('a,input,select,textarea')) {
			e.preventDefault();
			var row = preview.closest('.flxu-row');
			openPreview(row.getAttribute('data-slug'), preview.querySelector('.flxu-preview-open'));
			return;
		}
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
			checkNow();
			return;
		}
		var b = t.closest('button[name="flxu_do"]');
		if (!b || !f.contains(b)) { return; }
		if (b.value === 'manage') {
			// Sayfa yenilenerek admin-post'ta çalışır; kurulu olmayan seçimler gönderilmez.
			var op = document.getElementById('flxu-bulk-op').value, inst = selectedInstalled();
			if (busy || !op || !inst.length) { e.preventDefault(); return; }
			f.querySelectorAll('.flxu-check[data-state="missing"]:checked').forEach(function (x) { x.checked = false; });
			if (op === 'delete' && !window.confirm(fmt(L.confirmDel, inst.map(function (x) { return name(x.value); }).join(', ')))) { e.preventDefault(); }
			return;
		}
		if (!window.fetch) { return; }
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
	/* Sayfa açılınca bir kez kendiliğinden kontrol (sunucu son kontrol yakın zamandaysa data-auto koymaz). */
	var ck0 = document.getElementById('flxu-check');
	if (ck0 && ck0.getAttribute('data-auto') === '1') { checkNow(); }
})();
