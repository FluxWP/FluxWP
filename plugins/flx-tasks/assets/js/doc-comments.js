/* Flux Tasks — doküman yorumları ve sayfa koruması.
 * Metin aralığına ya da bloğa (görsel, dosya, tablo) yorum; sağ panelde konular, yanıtlar, düzenle/sil, çöz.
 * Sayfa kilidi (kilitli sayfada düzenlemeye çalışınca "kilidi aç" uyarısı) ve sayfa erişimi (yalnız seçilen kişiler).
 * Editöre (docs.js) Y.docx yüzü ve Y.docHooks olaylarıyla bağlanır; işaretler DOM'a yazılmaz (CSS Highlight API),
 * böylece ortak düzenleme ve kayıt etkilenmez. */
(function () {
	'use strict';
	var Y = window.FLXG;
	if (!Y || !Y.docx) return;
	var t = Y.t;
	var h = Y.h;
	var X = Y.docx;
	var S = Y.S;

	/* Sayfanın yorum durumu. */
	var C = null;
	function D() { return X.D(); }
	function me() { return X.me(); }
	function rank(r) { return Y.rank(r); }
	function pageRole() { var d = D(); return d && d.page ? d.page.role || d.role : ''; }
	function canComment() { return rank(pageRole()) >= 2; }
	function canEditRole(p) { var d = D(); return !!d && d.docEdit && rank((p && p.role) || d.role) >= 3; }
	Y.docCanComment = function () { var d = D(); return !!(d && d.page && C && C.page === d.page.id && canComment()); };

	function hl() { return window.CSS && CSS.highlights && typeof Highlight !== 'undefined'; }
	function esc(s) { var x = document.createElement('div'); x.textContent = s == null ? '' : String(s); return x.innerHTML; }
	/** Yorum metni: düz metin; bağlantılar ve satır sonları. */
	function bodyHtml(s) {
		return esc(s).replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g, '<br>');
	}

	Y.docHooks = (Y.docHooks || []).concat([function (n, a) {
		if (n === 'shell') shell();
		else if (n === 'open') reset(a);
		else if (n === 'paint') loaded();
		else if (n === 'sync') synced(a);
		else if (n === 'mode') banner();
		else if (n === 'locked') nudge(true);
		else if (n === 'content') paintSoon();
		else if (n === 'comment-sel') fromSelection();
		else if (n === 'comment-block') fromBlock(a);
	}]);

	/* =====================================================================
	 * Kabuk: üst çubuk düğmesi, sağ panel, işaret katmanı, kilit bandı
	 * ================================================================== */
	function shell() {
		var d = D();
		d.cmBtn = h('button.flxg-btn.is-sm.flxg-doc-cmbtn', { type: 'button', title: t('Yorumlar'), 'aria-label': t('Yorumlar'), onclick: function () { toggle(); } }, Y.icon('msg', 15), h('span.flxg-doc-cmn'));
		d.xtop.appendChild(d.cmBtn);
		d.cmLayer = h('.flxg-doc-cmlayer', { 'aria-hidden': 'true' });
		d.wrap.appendChild(d.cmLayer);
		d.cmShade = h('.flxg-doc-cmshade', { onclick: function () { toggle(false); } });
		d.root.appendChild(d.cmShade);
		d.side.className = 'flxg-doc-side';
		d.ed.addEventListener('click', onEdClick);
		d.ed.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.altKey && (e.key === 'm' || e.key === 'M' || e.code === 'KeyM')) { e.preventDefault(); fromSelection(); }
		});
		// Kilitli sayfada düzenleme denemesi: yazma, yapıştırma, bırakma, başlığa tıklama.
		var tryEdit = function (e) {
			var dd = D();
			if (!dd || !dd.page || !dd.page.locked || !canEditRole(dd.page)) return;
			if (e.type === 'keydown' && (e.ctrlKey || e.metaKey || e.altKey || /^(Arrow|Page|Home|End|Escape|Tab|Shift|Control|Alt|Meta)/.test(e.key))) return;
			if (e.type === 'drop') e.preventDefault();
			nudge();
		};
		['keydown', 'paste', 'drop'].forEach(function (ev) { d.scroll.addEventListener(ev, tryEdit, true); });
		d.titleEl.addEventListener('mousedown', tryEdit);
		d.ed.addEventListener('dblclick', tryEdit);
		// Seçim yapılmadan klavye ile gezinebilmek için (kilitli/okuma modunda editör odaklanamaz).
		d.scroll.setAttribute('tabindex', '-1');
		window.addEventListener('resize', paintSoon);
		C = { page: 0, list: [], rev: -1, active: 0, filter: 'open', draft: null, open: Y.store('doc-cm') === '1', ranges: {}, loading: false };
		drawPanel();
	}
	function reset(pid) {
		var d = D();
		if (!d || !C) return;
		C.page = pid;
		C.list = [];
		C.rev = -1;
		C.active = 0;
		C.draft = null;
		C.editing = 0;
		C.ranges = {};
		clearMarks();
		drawPanel();
		banner();
	}
	function toggle(on) {
		var d = D();
		if (!d || !C) return;
		C.open = on === undefined ? !C.open : !!on;
		if (!Y.isMobile()) Y.store('doc-cm', C.open ? '1' : '0');
		drawPanel();
		paintSoon();
	}

	/* =====================================================================
	 * Veri
	 * ================================================================== */
	function loaded() {
		var d = D();
		if (!d || !d.page || !C) return;
		if (C.page !== d.page.id) reset(d.page.id);
		banner();
		var rev = d.page.cmRev || 0;
		if (C.rev !== rev || C.rev < 0) load();
		else paintSoon();
	}
	function synced(r) {
		var d = D();
		if (!d || !d.page || !C || C.page !== d.page.id) return;
		banner();
		if (typeof r.cmRev === 'number' && r.cmRev !== C.rev && !C.loading) load();
	}
	function load() {
		var d = D();
		if (!d || !d.page || !C) return;
		var pid = d.page.id;
		C.loading = true;
		Y.api('GET', 'doc-pages/' + pid + '/comments', undefined, { quiet: true }).then(function (r) {
			C.loading = false;
			if (!D() || !C || C.page !== pid) return;
			C.list = r.comments || [];
			C.rev = r.rev || 0;
			if (D().page) D().page.cmRev = C.rev;
			drawPanel();
			paintSoon();
		}, function () { if (C) C.loading = false; });
	}
	function threads() {
		var roots = C.list.filter(function (c) { return !c.parent; });
		var replies = {};
		C.list.forEach(function (c) { if (c.parent) (replies[c.parent] = replies[c.parent] || []).push(c); });
		roots.forEach(function (r) { r.replies = replies[r.id] || []; });
		return roots;
	}
	function apply(r) {
		if (!C) return;
		if (r && r.comment) {
			var i = C.list.map(function (c) { return c.id; }).indexOf(r.comment.id);
			if (i > -1) C.list[i] = r.comment; else C.list.push(r.comment);
		}
		if (r && typeof r.rev === 'number') { C.rev = r.rev; var d = D(); if (d && d.page) d.page.cmRev = r.rev; }
		drawPanel();
		paintSoon();
	}

	/* =====================================================================
	 * Bağlantı noktası: seçimden ya da bloktan
	 * ================================================================== */
	function blockLabel(bl) {
		var f = bl.querySelector('[data-file]');
		var img = bl.querySelector('img');
		if (bl.tagName === 'TABLE') return t('Tablo');
		if (f && f.tagName === 'A') return (f.textContent || '').trim() || t('Dosya');
		if (img) return img.getAttribute('alt') || t('Görsel');
		var tx = (bl.textContent || '').trim();
		return tx.length > 80 ? tx.slice(0, 80) + '…' : tx;
	}
	function fromSelection() {
		var d = D();
		if (!d || !canComment()) return;
		var info = X.selInfo();
		if (!info) {
			// Seçim yoksa imlecin olduğu blok.
			var sel = window.getSelection();
			var bl = sel && sel.rangeCount ? X.blockOf(sel.anchorNode) : null;
			if (bl) fromBlock(bl); else Y.toast(t('Yorum için metin seçin ya da bir bloğun menüsünü kullanın.'));
			return;
		}
		var r = info.range;
		var sb = X.blockOf(r.startContainer);
		var eb = X.blockOf(r.endContainer);
		if (!sb || !eb) return;
		var q = info.text.replace(/\s+/g, ' ').trim();
		startDraft({
			b: sb.getAttribute('data-b'), o: X.textOffset(sb, r.startContainer, r.startOffset),
			eb: eb.getAttribute('data-b'), eo: X.textOffset(eb, r.endContainer, r.endOffset),
			q: q.slice(0, 500), kind: 'text', label: ''
		}, r.cloneRange());
		if (d.aiBub) d.aiBub.classList.remove('is-on');
	}
	function fromBlock(bl) {
		if (!bl || !canComment()) return;
		var b = bl.getAttribute('data-b');
		var len = (bl.textContent || '').length;
		var media = !!bl.querySelector('img,[data-file]') || bl.tagName === 'TABLE' || bl.tagName === 'FIGURE' || bl.tagName === 'HR';
		startDraft({ b: b, o: 0, eb: b, eo: len, q: media ? '' : (bl.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 500), kind: media ? 'block' : 'text', label: media ? blockLabel(bl) : '' }, null);
	}
	function startDraft(anchor, range) {
		C.draft = { anchor: anchor, body: '' };
		C.active = 0;
		C.open = true;
		C.draftRange = range;
		drawPanel();
		paintSoon();
		var ta = C.panel && C.panel.querySelector('.flxg-doc-cm-draft textarea');
		if (ta) setTimeout(function () { ta.focus(); }, 30);
	}

	/** Bağlantının bugünkü aralığı (metin değiştiyse alıntı aranır; bulunamazsa null). */
	function rangeOf(a) {
		var sb = X.blockById(a.b);
		var eb = X.blockById(a.eb || a.b);
		if (!sb) return null;
		var r = document.createRange();
		if (a.kind === 'block') { r.selectNodeContents(sb); return r; }
		var norm = function (s) { return String(s || '').replace(/\s+/g, ' ').trim(); };
		if (eb) {
			try {
				var ps = X.pointAt(sb, a.o);
				var pe = X.pointAt(eb, a.eo);
				r.setStart(ps.node, ps.off);
				r.setEnd(pe.node, pe.off);
				if (!a.q || norm(r.toString()) === norm(a.q)) return r;
			} catch (e) { /* aşağıda ara */ }
		}
		if (!a.q) return null;
		// Metin kaydı: alıntıyı aynı blokta (en yakın yerde) ara.
		var txt = sb.textContent || '';
		var best = -1;
		for (var i = txt.indexOf(a.q); i > -1; i = txt.indexOf(a.q, i + 1)) {
			if (best < 0 || Math.abs(i - a.o) < Math.abs(best - a.o)) best = i;
		}
		if (best < 0) return null;
		var s = X.pointAt(sb, best);
		var e = X.pointAt(sb, best + a.q.length);
		r.setStart(s.node, s.off);
		r.setEnd(e.node, e.off);
		return r;
	}

	/* =====================================================================
	 * İşaretler: vurgulama (CSS Highlight) ve kenar balonları
	 * ================================================================== */
	var paintTimer = 0;
	function paintSoon() { clearTimeout(paintTimer); paintTimer = setTimeout(paintMarks, 120); }
	function clearMarks() {
		if (hl()) { CSS.highlights.delete('flxg-cm'); CSS.highlights.delete('flxg-cm-on'); }
		var d = D();
		if (d && d.cmLayer) Y.clear(d.cmLayer);
	}
	function paintMarks() {
		var d = D();
		if (!d || !C || !d.cmLayer || !d.page || C.page !== d.page.id) return;
		var all = [];
		var on = [];
		C.ranges = {};
		Y.clear(d.cmLayer);
		var wr = d.wrap.getBoundingClientRect();
		var slots = {};
		threads().forEach(function (th) {
			th.orphan = false;
			if (th.resolved || (th.deleted && !th.replies.length) || !th.anchor) return;
			var r = rangeOf(th.anchor);
			if (!r) { th.orphan = true; return; }
			C.ranges[th.id] = r;
			if (th.anchor.kind !== 'block') (th.id === C.active ? on : all).push(r);
			var rect = r.getClientRects()[0] || r.getBoundingClientRect();
			var bl = X.blockById(th.anchor.b);
			if (th.anchor.kind === 'block' && bl) {
				var br = bl.getBoundingClientRect();
				d.cmLayer.appendChild(h('.flxg-doc-cmbox' + (th.id === C.active ? '.is-on' : ''), { style: { top: Math.round(br.top - wr.top - 3) + 'px', left: Math.round(br.left - wr.left - 4) + 'px', width: Math.round(br.width + 8) + 'px', height: Math.round(br.height + 6) + 'px' } }));
				rect = br;
			}
			if (!rect) return;
			var top = Math.round(rect.top - wr.top);
			while (slots[top]) top += 26;
			slots[top] = 1;
			var n = 1 + th.replies.length;
			d.cmLayer.appendChild(h('button.flxg-doc-cmpin' + (th.id === C.active ? '.is-on' : ''), {
				type: 'button', title: t('Yorumu aç'), 'aria-label': t('Yorumu aç'), style: { top: top + 'px' },
				onclick: function (e) { e.preventDefault(); focus(th.id, false); }
			}, Y.icon('msg', 13), n > 1 ? h('span', String(n)) : null));
		});
		if (C.draft && C.draftRange) on.push(C.draftRange);
		if (hl()) {
			try {
				if (all.length) CSS.highlights.set('flxg-cm', new Highlight(all.length ? all[0] : null)); else CSS.highlights.delete('flxg-cm');
				if (all.length) { var hh = CSS.highlights.get('flxg-cm'); all.slice(1).forEach(function (r) { hh.add(r); }); }
				if (on.length) { var h2 = new Highlight(); on.forEach(function (r) { h2.add(r); }); CSS.highlights.set('flxg-cm-on', h2); } else CSS.highlights.delete('flxg-cm-on');
			} catch (e) { /* desteklenmiyor */ }
		}
		count();
	}
	function count() {
		var d = D();
		if (!d || !d.cmBtn) return;
		var n = threads().filter(function (th) { return !th.resolved && !(th.deleted && !th.replies.length); }).length;
		var el = d.cmBtn.querySelector('.flxg-doc-cmn');
		el.textContent = n ? String(n) : t('Yorum');
		d.cmBtn.classList.toggle('has-n', !!n);
		d.cmBtn.classList.toggle('is-on', !!C.open);
	}
	/** Vurgulu metne tıklayınca ilgili konu açılır. */
	function onEdClick(e) {
		if (!C || !C.list.length) return;
		var sel = window.getSelection();
		if (sel && !sel.isCollapsed) return;
		var pos = document.caretRangeFromPoint ? document.caretRangeFromPoint(e.clientX, e.clientY) : null;
		if (!pos && document.caretPositionFromPoint) { var cp = document.caretPositionFromPoint(e.clientX, e.clientY); if (cp) { pos = document.createRange(); pos.setStart(cp.offsetNode, cp.offset); } }
		if (!pos) return;
		var hit = 0;
		Object.keys(C.ranges).forEach(function (id) {
			var r = C.ranges[id];
			var th = C.list.filter(function (c) { return c.id === +id; })[0];
			if (!th || th.anchor.kind === 'block') return;
			try { if (r.comparePoint(pos.startContainer, pos.startOffset) === 0 && !r.collapsed) hit = hit || +id; } catch (er) { /* yok */ }
		});
		if (hit) focus(hit, true);
		else if (C.active) { C.active = 0; drawPanel(); paintSoon(); }
	}
	/** Konuyu öne çıkarır; fromEditor değilse editörde yerine kaydırır. */
	function focus(id, fromEditor) {
		var d = D();
		C.active = id;
		C.open = true;
		drawPanel();
		paintSoon();
		var card = C.panel && C.panel.querySelector('[data-th="' + id + '"]');
		if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		if (!fromEditor && d) {
			var r = C.ranges[id];
			var rect = r ? (r.getClientRects()[0] || r.getBoundingClientRect()) : null;
			if (rect) {
				var sr = d.scroll.getBoundingClientRect();
				if (rect.top < sr.top + 60 || rect.bottom > sr.bottom - 40) d.scroll.scrollTop += rect.top - sr.top - sr.height / 3;
			}
		}
	}

	/* =====================================================================
	 * Sağ panel
	 * ================================================================== */
	function drawPanel() {
		var d = D();
		if (!d || !C) return;
		d.root.classList.toggle('cm-open', !!C.open);
		var side = Y.clear(d.side);
		count();
		if (!C.open) return;
		var all = threads();
		var nOpen = all.filter(function (x) { return !x.resolved && !(x.deleted && !x.replies.length); }).length;
		var nDone = all.filter(function (x) { return x.resolved; }).length;
		var seg = function (k, label, n) {
			return h('button.flxg-doc-cm-seg' + (C.filter === k ? '.is-on' : ''), { type: 'button', onclick: function () { C.filter = k; drawPanel(); } }, label, n ? h('span', String(n)) : null);
		};
		var head = h('.flxg-doc-cm-head',
			h('b', t('Yorumlar')),
			h('.flxg-doc-cm-segs', seg('open', t('Çözülmemiş'), nOpen), seg('resolved', t('Çözülen'), nDone)),
			h('button.flxg-icon-btn.is-sm', { type: 'button', title: t('Kapat'), 'aria-label': t('Kapat'), onclick: function () { toggle(false); } }, Y.icon('x', 16)));
		var list = h('.flxg-doc-cm-list');
		C.panel = h('.flxg-doc-cm', head, list);
		side.appendChild(C.panel);
		if (C.draft) list.appendChild(draftCard());
		// Belgedeki sıraya göre: blok sırası, sonra karakter sırası; yeri kaybolanlar en sonda.
		var order = {};
		Array.prototype.forEach.call(d.ed.children, function (el, i) { order[el.getAttribute('data-b')] = i; });
		var shown = all.filter(function (x) {
			if (x.deleted && !x.replies.length) return false;
			return C.filter === 'resolved' ? !!x.resolved : !x.resolved;
		}).sort(function (a, b) {
			var ia = a.anchor && order[a.anchor.b] !== undefined ? order[a.anchor.b] : 1e9;
			var ib = b.anchor && order[b.anchor.b] !== undefined ? order[b.anchor.b] : 1e9;
			return ia - ib || (a.anchor ? a.anchor.o : 0) - (b.anchor ? b.anchor.o : 0) || a.id - b.id;
		});
		shown.forEach(function (th) { list.appendChild(threadCard(th)); });
		if (!shown.length && !C.draft) {
			list.appendChild(h('.flxg-doc-cm-empty',
				Y.icon('msg', 22),
				h('p', C.filter === 'resolved' ? t('Çözülen yorum yok.') : t('Henüz yorum yok.')),
				canComment() && C.filter === 'open' ? h('small', t('Metin seçip "Yorum yap" düğmesine basın ya da bir bloğun menüsünden (⋮⋮) yorum ekleyin.')) : null));
		}
		if (C.loading && !C.list.length) list.appendChild(h('.flxg-doc-cm-sk', Y.sk('70%', 12), Y.sk('90%', 12), Y.sk('50%', 12)));
	}
	function quoteEl(a, orphan) {
		if (!a) return null;
		if (a.kind === 'block') return h('.flxg-doc-cm-quote.is-block', Y.icon(/\.(png|jpe?g|gif|webp|svg)$/i.test(a.label) || a.label === t('Görsel') ? 'image' : a.label === t('Tablo') ? 'table' : 'clip', 13), h('span', a.label || t('Blok')), orphan ? h('em', t('silindi')) : null);
		return h('.flxg-doc-cm-quote' + (orphan ? '.is-orphan' : ''), h('span', a.q), orphan ? h('em', t('metin değişti')) : null);
	}
	function composer(opts) {
		var ta = h('textarea.flxg-input.flxg-doc-cm-ta', { rows: opts.rows || 2, placeholder: opts.ph || '' });
		ta.value = opts.value || '';
		var fit = Y.autosize ? Y.autosize(ta) : function () {};
		var send = h('button.flxg-btn.is-sm.is-primary', { type: 'button', disabled: !ta.value.trim(), onclick: go }, opts.ok);
		ta.addEventListener('input', function () { send.disabled = !ta.value.trim(); if (opts.onInput) opts.onInput(ta.value); fit(); });
		ta.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); go(); }
			if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); if (opts.onCancel) opts.onCancel(); }
		});
		function go() {
			var v = ta.value.trim();
			if (!v || send.disabled) return;
			send.disabled = true;
			ta.disabled = true;
			Promise.resolve(opts.onSend(v)).then(function () { ta.value = ''; ta.disabled = false; }, function (er) { ta.disabled = false; send.disabled = false; Y.fail(er); });
		}
		setTimeout(fit, 0);
		return h('.flxg-doc-cm-compose', ta, h('.flxg-doc-cm-actions', opts.onCancel ? h('button.flxg-btn.is-sm.is-ghost', { type: 'button', onclick: opts.onCancel }, t('Vazgeç')) : null, send));
	}
	function draftCard() {
		var dr = C.draft;
		var u = S.users[me()];
		return h('.flxg-doc-cm-card.flxg-doc-cm-draft.is-on',
			quoteEl(dr.anchor, false),
			h('.flxg-doc-cm-who', Y.avatar(u, 24), h('b', u ? u.name : '')),
			composer({ ph: t('Yorum yazın… (Ctrl+Enter gönderir)'), ok: t('Yorum yap'), value: dr.body, rows: 3,
				onInput: function (v) { dr.body = v; },
				onCancel: function () { C.draft = null; C.draftRange = null; drawPanel(); paintSoon(); },
				onSend: function (v) {
					var pid = C.page;
					return Y.api('POST', 'doc-pages/' + pid + '/comments', { body: v, anchor: dr.anchor }).then(function (r) {
						if (!C || C.page !== pid) return;
						C.draft = null;
						C.draftRange = null;
						C.active = r.comment.id;
						var s = window.getSelection();
						if (s) s.removeAllRanges();
						apply(r);
					});
				} }));
	}
	function commentEl(c, th) {
		var u = S.users[c.user] || X.user(c.user);
		var body;
		if (C.editing === c.id) {
			body = composer({ value: c.body, ok: t('Kaydet'), rows: 2,
				onCancel: function () { C.editing = 0; drawPanel(); },
				onSend: function (v) { return Y.api('POST', 'doc-comments/' + c.id, { body: v }).then(function (r) { C.editing = 0; apply(r); }); } });
		} else {
			body = h('.flxg-doc-cm-body');
			if (c.deleted) body.appendChild(h('em.flxg-muted', t('Bu yorum silindi.')));
			else body.innerHTML = bodyHtml(c.body);
		}
		var items = [
			c.canEdit ? { label: t('Düzenle'), icon: 'edit', onClick: function () { C.editing = c.id; C.active = th.id; drawPanel(); var ta = C.panel.querySelector('[data-c="' + c.id + '"] textarea'); if (ta) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); } } } : null,
			{ label: t('Bağlantıyı kopyala'), icon: 'link', onClick: function () { Y.copy(location.href.split('#')[0] + '#/doc/' + D().doc.id + '/p/' + C.page); } },
			c.canDelete ? { sep: true } : null,
			c.canDelete ? { label: t('Sil'), icon: 'trash', danger: true, onClick: function () { remove(c, th); } } : null
		];
		return h('.flxg-doc-cm-c', { 'data-c': c.id },
			h('.flxg-doc-cm-who',
				Y.avatar(u, 24),
				h('b', u ? u.name : ''),
				h('time', { title: c.created || '' }, Y.ago(c.created)),
				c.edited && !c.deleted ? h('small.flxg-muted', t('(düzenlendi)')) : null,
				h('span.flxg-grow'),
				!c.parent && th.canResolve && !c.deleted ? h('button.flxg-icon-btn.is-xs.flxg-doc-cm-res', { type: 'button', title: th.resolved ? t('Yeniden aç') : t('Çözüldü olarak işaretle'), 'aria-label': th.resolved ? t('Yeniden aç') : t('Çözüldü olarak işaretle'), onclick: function (e) { e.stopPropagation(); resolve(th, !th.resolved); } }, Y.icon(th.resolved ? 'undo' : 'check', 15)) : null,
				!c.deleted && (c.canEdit || c.canDelete) ? h('button.flxg-icon-btn.is-xs', { type: 'button', title: t('Diğer'), 'aria-label': t('Diğer'), onclick: function (e) { e.stopPropagation(); Y.menu(e.currentTarget, items, { alignRight: true }); } }, Y.icon('dots', 15)) : null),
			body);
	}
	function threadCard(th) {
		var on = C.active === th.id;
		var card = h('.flxg-doc-cm-card' + (on ? '.is-on' : '') + (th.resolved ? '.is-resolved' : ''), { 'data-th': th.id, onclick: function (e) {
			if (e.target.closest('button,a,textarea,input')) return;
			if (C.active !== th.id) focus(th.id, false);
		} },
			quoteEl(th.anchor, th.orphan),
			th.resolved ? h('.flxg-doc-cm-resolved', Y.icon('check', 13), t('{n} çözdü', { n: (S.users[th.resolved.by] || X.user(th.resolved.by)).name })) : null,
			commentEl(th, th));
		var rep = th.replies;
		// Kapalıyken uzun konular kısalır: son iki yanıt görünür.
		var hidden = !on && rep.length > 2 ? rep.length - 2 : 0;
		if (hidden) card.appendChild(h('button.flxg-doc-cm-more', { type: 'button', onclick: function () { focus(th.id, false); } }, t('{n} yanıt daha', { n: hidden })));
		rep.slice(hidden).forEach(function (c) { card.appendChild(commentEl(c, th)); });
		if (canComment() && !th.deleted || (th.deleted && canComment())) {
			if (on) {
				card.appendChild(composer({ ph: t('Yanıtla… (Ctrl+Enter gönderir)'), ok: t('Yanıtla'), value: C.reply && C.reply.id === th.id ? C.reply.v : '',
					onInput: function (v) { C.reply = { id: th.id, v: v }; },
					onSend: function (v) { return Y.api('POST', 'doc-pages/' + C.page + '/comments', { body: v, parent: th.id }).then(function (r) { C.reply = null; apply(r); load(); }); } }));
			} else if (!th.resolved) {
				card.appendChild(h('button.flxg-doc-cm-replybtn', { type: 'button', onclick: function () { focus(th.id, false); setTimeout(function () { var ta = card.isConnected ? null : C.panel.querySelector('[data-th="' + th.id + '"] textarea'); if (ta) ta.focus(); }, 30); } }, t('Yanıtla')));
			}
		}
		return card;
	}
	function remove(c, th) {
		var isRoot = !c.parent;
		Y.confirm(t('Yorumu sil'), isRoot && th.replies.length ? t('Yorum silinecek; yanıtlar konuşmada kalır.') : t('Yorum kalıcı olarak silinecek.'), t('Sil'), true).then(function (ok) {
			if (!ok) return;
			Y.api('DELETE', 'doc-comments/' + c.id).then(function (r) {
				if (isRoot && C.active === c.id && !th.replies.length) C.active = 0;
				if (C && typeof r.rev === 'number') C.rev = -2;
				load();
			}, Y.fail);
		});
	}
	function resolve(th, val) {
		Y.api('POST', 'doc-comments/' + th.id, { resolved: val ? 1 : 0 }).then(function (r) {
			if (val && C.active === th.id) C.active = 0;
			apply(r);
			if (val) Y.toast(t('Yorum çözüldü'), { action: { label: t('Geri al'), fn: function () { resolve(th, false); } } });
		}, Y.fail);
	}

	/* =====================================================================
	 * Sayfa kilidi
	 * ================================================================== */
	function banner() {
		var d = D();
		if (!d || !d.banner) return;
		var p = d.page || {};
		Y.clear(d.banner);
		d.banner.className = 'flxg-doc-banner';
		if (p.locked) {
			var u = p.locked.by ? (S.users[p.locked.by] || X.user(p.locked.by)) : null;
			d.banner.classList.add('is-on');
			d.banner.append(
				h('span.flxg-doc-banner-ic', Y.icon('lock', 15)),
				h('span.flxg-doc-banner-txt', h('b', t('Bu sayfa kilitli.')), ' ', u ? t('{n} kilitledi, {t}.', { n: u.name, t: Y.ago(p.locked.at) }) : '', canEditRole(p) ? ' ' + t('Düzenlemek için kilidi açın.') : ''),
				canEditRole(p) ? h('button.flxg-btn.is-sm', { type: 'button', onclick: function () { unlock(p); } }, Y.icon('unlock', 14), h('span', t('Kilidi aç'))) : null
			);
		} else if (d.docEdit && rank(p.role || d.role) < 3 && p.role) {
			d.banner.classList.add('is-on', 'is-ro');
			d.banner.append(h('span.flxg-doc-banner-ic', Y.icon('eye', 15)), h('span.flxg-doc-banner-txt', rank(p.role) >= 2 ? t('Bu sayfayı görüntüleyip yorum yapabilirsiniz.') : t('Bu sayfayı yalnız görüntüleyebilirsiniz.')));
		}
		var flag = d.crumbs && d.crumbs.querySelector('.flxg-doc-lockchip');
		if (flag) flag.remove();
		if (p.locked && d.crumbs) d.crumbs.appendChild(h('span.flxg-doc-lockchip', { title: t('Sayfa kilitli') }, Y.icon('lock', 13)));
	}
	var lastNudge = 0;
	function nudge(fromServer) {
		var d = D();
		if (!d || !d.page) return;
		if (Date.now() - lastNudge < 3500) { pulse(); return; }
		lastNudge = Date.now();
		pulse();
		var p = d.page;
		Y.toast(fromServer ? t('Sayfa kilitlendi; değişiklikleriniz kilit açılınca kaydedilir.') : t('Bu sayfa kilitli. Düzenlemek için kilidi açın.'), canEditRole(p) ? { action: { label: t('Kilidi aç'), fn: function () { unlock(p); } } } : {});
	}
	function pulse() {
		var d = D();
		if (!d || !d.banner) return;
		d.banner.classList.remove('is-pulse');
		void d.banner.offsetWidth;
		d.banner.classList.add('is-pulse');
	}
	function setLock(p, on) {
		var d = D();
		var go = function () {
			return Y.api('POST', 'doc-pages/' + p.id + '/lock', { locked: on ? 1 : 0 }).then(function (r) {
				var dd = D();
				if (!dd) return;
				X.applyPages(r);
				if (dd.page && dd.page.id === p.id) { dd.page.locked = r.locked; X.setMode(); banner(); }
				Y.toast(on ? t('Sayfa kilitlendi') : t('Kilit açıldı'));
			}, Y.fail);
		};
		// Kilitlemeden önce bekleyen yazılar kaydedilir.
		return on && d && d.page && d.page.id === p.id ? X.flushNow().then(go) : go();
	}
	function unlock(p) {
		var u = p.locked && p.locked.by && p.locked.by !== me() ? (S.users[p.locked.by] || X.user(p.locked.by)) : null;
		Y.confirm(t('Kilidi aç'), u ? t('{n} bu sayfayı yanlışlıkla değiştirilmesin diye kilitledi. Kilidi açarsanız düzenleyebilen herkes yeniden değiştirebilir.', { n: u.name }) : t('Kilidi açarsanız düzenleyebilen herkes sayfayı yeniden değiştirebilir.'), t('Kilidi aç')).then(function (ok) { if (ok) setLock(p, false); });
	}

	/* Sayfa menüsü (ağaç ve üst çubuk) ve ağaçtaki işaretler. */
	Y.docPageItems = function (p) {
		if (!canEditRole(p)) return [];
		return [
			{ sep: true },
			p.locked ? { label: t('Kilidi aç'), icon: 'unlock', onClick: function () { unlock(p); } } : { label: t('Sayfayı kilitle'), icon: 'lock', onClick: function () { setLock(p, true); } },
			{ label: t('Erişim…'), icon: 'users', onClick: function () { accessModal(p); } }
		];
	};
	Y.docPageFlags = function (p) {
		if (!p.locked && !p.restricted) return null;
		return h('span.flxg-doc-pflags',
			p.restricted ? h('span', { title: t('Erişim kısıtlı') }, Y.icon('eyeOff', 12)) : null,
			p.locked ? h('span', { title: t('Kilitli') }, Y.icon('lock', 12)) : null);
	};

	/* =====================================================================
	 * Sayfa erişimi
	 * ================================================================== */
	function accessModal(p) {
		var body = h('.flxg-form.flxg-doc-acc', Y.skeleton ? Y.skeleton('list', { rows: 3 }) : null);
		var st = null;
		var m = Y.modal(t('Sayfa erişimi'), body, { actions: [
			{ label: t('Vazgeç') },
			{ label: t('Kaydet'), primary: true, onClick: function () {
				if (!st) return false;
				return Y.api('POST', 'doc-pages/' + p.id + '/access', { access: st.access, members: st.members }).then(function (r) {
					X.applyPages(r);
					var dd = D();
					var np = dd && X.pageById(p.id);
					if (dd && dd.page && dd.page.id === p.id && np) { dd.page.restricted = np.restricted; }
					Y.toast(t('Erişim kaydedildi'));
				});
			} }
		] });
		void m;
		Y.api('GET', 'doc-pages/' + p.id + '/access').then(function (r) {
			st = { access: r.access, members: r.members.filter(function (x) { return x.user !== me(); }), candidates: r.candidates || [], admins: r.admins || [], inherited: r.inherited };
			draw();
		}, function (e) { Y.fail(e); Y.clear(body).appendChild(h('p.flxg-muted', e.message)); });
		var roles = [['view', t('Görüntüler')], ['comment', t('Yorum yapar')], ['edit', t('Düzenler')]];
		function draw() {
			Y.clear(body);
			var radios = h('.flxg-radios');
			[['doc', t('Dokümana erişen herkes'), t('Projede bu dokümanı görebilen herkes bu sayfayı da görür.')], ['restricted', t('Yalnız seçtiğim kişiler'), t('Sayfa ve alt sayfaları yalnız aşağıdaki kişilere ve proje yöneticilerine görünür.')]].forEach(function (o) {
				radios.appendChild(h('label.flxg-radio' + (st.access === o[0] ? '.is-on' : ''), h('input', { type: 'radio', name: 'flxg-pacc', checked: st.access === o[0], onchange: function () { st.access = o[0]; draw(); } }), h('span', h('b', o[1]), h('small', o[2]))));
			});
			body.appendChild(h('.flxg-field', h('span', t('Kimler görebilir')), radios));
			if (st.inherited) body.appendChild(h('p.flxg-doc-acc-note', Y.icon('alert', 14), t('Üst sayfa da kısıtlı: bu sayfaya yalnız orada izni olanlar erişebilir.')));
			if (st.access !== 'restricted') return;
			var box = h('.flxg-members');
			var mu = S.users[me()];
			box.appendChild(h('.flxg-member', Y.avatar(mu, 28), h('span.flxg-grow', mu ? mu.name : ''), h('small.flxg-muted', t('Düzenler (siz)'))));
			st.members.forEach(function (mm, i) {
				var u = S.users[mm.user] || X.user(mm.user);
				var sel = h('select.flxg-input.is-sm', { 'aria-label': t('Rol'), onchange: function (e) { mm.role = e.target.value; } }, roles.map(function (r) { return h('option', { value: r[0], selected: mm.role === r[0] }, r[1]); }));
				box.appendChild(h('.flxg-member', Y.avatar(u, 28), h('span.flxg-grow', u.name), sel,
					h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kaldır'), onclick: function () { st.members.splice(i, 1); draw(); } }, Y.icon('x', 15))));
			});
			var taken = st.members.map(function (x) { return x.user; }).concat([me()]);
			var free = st.candidates.filter(function (u) { return taken.indexOf(u) < 0 && st.admins.indexOf(u) < 0; });
			if (free.length) {
				var add = h('select.flxg-input.is-sm.flxg-doc-acc-add', { 'aria-label': t('Kişi ekle'), onchange: function (e) {
					var id = +e.target.value;
					if (id) { st.members.push({ user: id, role: 'view' }); draw(); }
				} }, h('option', { value: '' }, t('+ Kişi ekle')), free.map(function (id) { var u = S.users[id] || X.user(id); return h('option', { value: id }, u.name); }));
				box.appendChild(add);
			}
			body.appendChild(h('.flxg-field', h('span', t('Kişiler ve rolleri')), box));
			if (st.admins.length) body.appendChild(h('small.flxg-muted', t('Proje yöneticileri ({n}) her zaman erişir.', { n: st.admins.map(function (id) { return (S.users[id] || X.user(id)).name; }).join(', ') })));
		}
	}
})();
