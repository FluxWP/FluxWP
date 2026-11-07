/* Flux AI Chatbot · site sohbetleri gelen kutusu. GPL-2.0-or-later. */
(function () {
	'use strict';
	var C = window.flxcbInbox || {};
	var T = C.i18n || {};
	var root = document.querySelector('[data-flxcb-inbox]');
	if (!root) return;

	var $items = root.querySelector('[data-items]');
	var $filters = root.querySelector('[data-filters]');
	var $search = root.querySelector('[data-search]');
	var $conv = root.querySelector('[data-conv]');
	var pick = root.querySelector('[data-pick]');
	if (pick) pick.textContent = T.pick || '';

	var state = { filter: 'open', q: '', items: [], cur: C.conv || 0, conv: null, msgs: [], last: 0, sending: false };
	var timers = { list: 0, conv: 0, typing: 0 };

	var SVG = {
		clip: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l8.6-8.6a4 4 0 0 1 5.7 5.7l-8.6 8.6a2 2 0 0 1-2.9-2.9l8.5-8.5"/>',
		send: '<path d="M14.5 21.7a.5.5 0 0 0 .9 0l6.5-19a.5.5 0 0 0-.6-.6l-19 6.5a.5.5 0 0 0 0 .9l7.9 3.2a2 2 0 0 1 1.1 1.1z"/><path d="m21.9 2.1-10.9 10.9"/>',
		x: '<path d="M18 6 6 18M6 6l12 12"/>',
		trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
		ext: '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		back: '<path d="m15 18-6-6 6-6"/>',
		file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><path d="M14 2v6h6"/>',
		star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>'
	};
	function ico(n, s) {
		return '<svg class="flxcb-i" width="' + (s || 16) + '" height="' + (s || 16) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (SVG[n] || '') + '</svg>';
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	/* WhatsApp benzeri biçim + bağlantılar (asistan Markdown'ı da kabaca). */
	function fmt(s) {
		var h = esc(s);
		h = h.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
		h = h.replace(/(^|[\s(])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
		h = h.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>').replace(/(^|\W)\*([^*\n]+)\*(?=\W|$)/g, '$1<strong>$2</strong>');
		h = h.replace(/(^|\W)_([^_\n]+)_(?=\W|$)/g, '$1<em>$2</em>').replace(/(^|\W)~([^~\n]+)~(?=\W|$)/g, '$1<s>$2</s>');
		h = h.replace(/`([^`\n]+)`/g, '<code>$1</code>').replace(/^[ \t]*[-*][ \t]+/gm, '• ');
		return h.replace(/\n/g, '<br>');
	}
	/* Liste önizlemesi için biçimsiz metin. */
	function plain(s) {
		return String(s || '').replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/[*_~`#]+/g, '').replace(/\s+/g, ' ');
	}
	function initials(s) {
		s = String(s || '').trim();
		if (!s) return '?';
		var p = s.split(/\s+/);
		return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
	}
	function hue(id) {
		return (Number(id) * 47) % 360;
	}

	function api(path, opts) {
		opts = opts || {};
		var headers = { 'X-WP-Nonce': C.nonce };
		var body = opts.body;
		if (body && !(body instanceof FormData)) {
			headers['Content-Type'] = 'application/json';
			body = JSON.stringify(body);
		}
		/* Kalıcı bağlantı kapalıysa REST adresi ?rest_route= içerir; sorgu & ile eklenir. */
		var q = path.indexOf('?');
		var url = C.rest + (q > -1 ? path.slice(0, q) + (C.rest.indexOf('?') > -1 ? '&' : '?') + path.slice(q + 1) : path);
		return fetch(url, { method: opts.method || 'GET', headers: headers, body: body, credentials: 'same-origin' }).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) throw new Error((j && j.message) || T.error);
				return j;
			});
		});
	}

	/* ------------ Liste ------------ */
	var FILTERS = [['open', T.open], ['bot', T.bot], ['closed', T.closed], ['', T.all]];
	$filters.innerHTML = FILTERS.map(function (f) {
		return '<button type="button" data-f="' + f[0] + '" class="' + (f[0] === state.filter ? 'is-on' : '') + '">' + esc(f[1]) + '</button>';
	}).join('');
	$filters.addEventListener('click', function (e) {
		var b = e.target.closest('[data-f]');
		if (!b) return;
		state.filter = b.getAttribute('data-f');
		[].forEach.call($filters.children, function (x) { x.classList.toggle('is-on', x === b); });
		loadList();
	});
	$search.placeholder = T.search || '';
	var sT;
	$search.addEventListener('input', function () {
		clearTimeout(sT);
		sT = setTimeout(function () { state.q = $search.value.trim(); loadList(); }, 300);
	});

	function loadList() {
		clearTimeout(timers.list);
		var qs = '?status=' + encodeURIComponent(state.filter) + '&q=' + encodeURIComponent(state.q);
		return api('conversations' + qs).then(function (j) {
			state.items = j.items || [];
			drawList();
		}).catch(function () {}).then(function () {
			timers.list = setTimeout(loadList, document.hidden ? 60000 : 15000);
		});
	}

	function badge(st) {
		return '<span class="flxcb-ib is-' + esc(st) + '">' + esc(T['st_' + st] || st) + '</span>';
	}

	function drawList() {
		if (!state.items.length) {
			$items.innerHTML = '<li class="flxcb-iitems__empty">' + esc(T.empty) + '</li>';
			return;
		}
		$items.innerHTML = state.items.map(function (c) {
			var last = c.last ? (c.last.role === 'agent' ? T.you + ': ' : c.last.role === 'bot' ? '✦ ' : '') + plain(c.last.body) : '';
			return '<li><button type="button" class="flxcb-iitem' + (c.id === state.cur ? ' is-on' : '') + (c.unread ? ' is-unread' : '') + '" data-id="' + c.id + '">' +
				'<span class="flxcb-iav" style="--h:' + hue(c.id) + '">' + esc(initials(c.name || c.email || '#')) + (c.online ? '<i></i>' : '') + '</span>' +
				'<span class="flxcb-iitem__b"><span class="flxcb-iitem__r"><strong>' + esc(c.title) + '</strong><small>' + esc(c.ago) + '</small></span>' +
				'<span class="flxcb-iitem__r"><span class="flxcb-iitem__last">' + (c.typing ? '<em>' + esc(T.typing) + '</em>' : esc(last)) + '</span>' + (c.unread ? '<b>' + c.unread + '</b>' : '') + '</span>' +
				'<span class="flxcb-iitem__r">' + badge(c.status) + (c.page && c.page.title ? '<small class="flxcb-iitem__pg">' + esc(c.page.title) + '</small>' : '') + '</span></span></button></li>';
		}).join('');
	}

	$items.addEventListener('click', function (e) {
		var b = e.target.closest('[data-id]');
		if (b) openConv(Number(b.getAttribute('data-id')));
	});

	/* ------------ Sohbet ------------ */
	function openConv(id) {
		clearTimeout(timers.conv);
		state.cur = id;
		state.conv = null;
		state.msgs = [];
		state.last = 0;
		root.classList.add('has-conv');
		[].forEach.call($items.querySelectorAll('[data-id]'), function (b) { b.classList.toggle('is-on', Number(b.getAttribute('data-id')) === id); });
		$conv.innerHTML = '<div class="flxcb-iempty"><p>' + esc(T.loading) + '</p></div>';
		try { history.replaceState(null, '', location.pathname + '?page=flx-chatbot-inbox&conv=' + id); } catch (e) {}
		fetchConv(true);
	}

	function fetchConv(first) {
		clearTimeout(timers.conv);
		var id = state.cur;
		if (!id) return;
		api('conversations/' + id + (first ? '' : '?after=' + state.last)).then(function (j) {
			if (id !== state.cur) return;
			var was = state.conv;
			state.conv = j.conv;
			if (first) {
				state.msgs = j.messages || [];
				drawConv();
			} else {
				addMsgs(j.messages || []);
				if (!was || was.status !== j.conv.status || was.rating !== j.conv.rating || was.email !== j.conv.email) drawHead();
				drawTyping();
			}
			var it = state.items.filter(function (x) { return x.id === id; })[0];
			if (it && it.unread) { it.unread = 0; drawList(); }
		}).catch(function (e) {
			if (first) $conv.innerHTML = '<div class="flxcb-iempty"><p>' + esc(e.message) + '</p></div>';
		}).then(function () {
			if (id === state.cur) timers.conv = setTimeout(fetchConv, document.hidden ? 30000 : (state.conv && /queued|agent/.test(state.conv.status) ? 4000 : 10000));
		});
	}

	function drawConv() {
		$conv.innerHTML =
			'<header class="flxcb-ihead" data-head></header>' +
			'<div class="flxcb-ibody"><div class="flxcb-imsgs" data-msgs></div><aside class="flxcb-iinfo" data-info></aside></div>' +
			'<form class="flxcb-icomp" data-comp>' +
			'<div class="flxcb-icomp__file" data-filebox hidden></div>' +
			'<div class="flxcb-icomp__row"><label class="flxcb-ibtn" title="' + esc(T.attach) + '">' + ico('clip', 18) + '<input type="file" hidden data-file></label>' +
			'<textarea rows="1" data-text placeholder="' + esc(T.placeholder) + '"></textarea>' +
			'<button type="submit" class="button button-primary" data-send>' + ico('send', 16) + '<span>' + esc(T.send) + '</span></button></div></form>';
		drawHead();
		var box = $conv.querySelector('[data-msgs]');
		box.innerHTML = '';
		addMsgs(state.msgs, true);
		bindComposer();
	}

	function drawHead() {
		var c = state.conv;
		var h = $conv.querySelector('[data-head]');
		var info = $conv.querySelector('[data-info]');
		if (!c || !h) return;
		h.innerHTML =
			'<button type="button" class="flxcb-ibtn flxcb-ihead__back" data-back title="' + esc(T.back) + '">' + ico('back', 18) + '</button>' +
			'<span class="flxcb-iav" style="--h:' + hue(c.id) + '">' + esc(initials(c.name || c.email || '#')) + (c.online ? '<i></i>' : '') + '</span>' +
			'<span class="flxcb-ihead__t"><strong>' + esc(c.title) + '</strong><small>' + badge(c.status) + ' <span data-typing>' + esc(c.online ? T.online : T.away) + '</span></small></span>' +
			'<span class="flxcb-ihead__a">' +
			(c.fluxChat ? '<a class="button" href="' + esc(c.fluxChat) + '">' + ico('ext', 14) + esc(T.fluxChat) + '</a>' : '') +
			(c.status !== 'closed' ? '<button type="button" class="button" data-close>' + ico('x', 14) + esc(T.close) + '</button>' : '') +
			(C.admin ? '<button type="button" class="flxcb-ibtn is-bad" data-del title="' + esc(T.delete) + '">' + ico('trash', 16) + '</button>' : '') +
			'</span>';
		var rows = [];
		function row(k, v) { if (v) rows.push('<dt>' + esc(k) + '</dt><dd>' + v + '</dd>'); }
		row(T.email, c.email ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '');
		row(T.phone, c.phone ? '<a href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : '');
		row(T.page, c.page && c.page.url ? '<a href="' + esc(c.page.url) + '" target="_blank" rel="noopener">' + esc(c.page.title || c.page.url) + '</a>' : '');
		row(T.member, c.user ? '#' + c.user : '');
		row(T.assigned, esc(c.agent));
		row(T.order, c.order ? (c.orderUrl ? '<a href="' + esc(c.orderUrl) + '">#' + c.order + '</a>' : '#' + c.order) : '');
		row(T.rating, c.rating ? '<span class="flxcb-istars">' + new Array(c.rating + 1).join('★') + '<i>' + new Array(6 - c.rating).join('★') + '</i></span>' + (c.note ? '<q>' + esc(c.note) + '</q>' : '') : '');
		row(T.lead, c.lead ? '✓' : '');
		info.innerHTML = '<h3>' + esc(T.details) + '</h3><dl>' + rows.join('') + '</dl>' +
			(c.summary ? '<div class="flxcb-isum"><strong>' + ico('info', 14) + esc(T.summary) + '</strong><p>' + fmt(c.summary) + '</p></div>' : '') +
			(c.fluxChat ? '<p class="flxcb-muted">' + esc(T.inFluxChat) + '</p>' : '');
		var cl = h.querySelector('[data-close]');
		if (cl) cl.onclick = function () {
			if (!window.confirm(T.confirmEnd)) return;
			api('conversations/' + c.id + '/close', { method: 'POST' }).then(function (j) { state.conv = j.conv; drawHead(); addMsgs(j.messages || []); loadList(); }).catch(alertErr);
		};
		var del = h.querySelector('[data-del]');
		if (del) del.onclick = function () {
			if (!window.confirm(T.confirmDel)) return;
			api('conversations/' + c.id, { method: 'DELETE' }).then(function () {
				state.cur = 0;
				root.classList.remove('has-conv');
				$conv.innerHTML = '<div class="flxcb-iempty"><p>' + esc(T.pick) + '</p></div>';
				loadList();
			}).catch(alertErr);
		};
		h.querySelector('[data-back]').onclick = function () { root.classList.remove('has-conv'); };
	}

	function drawTyping() {
		var t = $conv.querySelector('[data-typing]');
		if (!t || !state.conv) return;
		t.textContent = state.conv.typing ? T.typing : (state.conv.online ? T.online : T.away);
		t.classList.toggle('is-typing', !!state.conv.typing);
	}

	function msgHtml(m) {
		if (m.role === 'system') return '<div class="flxcb-isys">' + esc(m.body) + '<time>' + esc(m.time) + '</time></div>';
		var who = m.role === 'visitor' ? (state.conv && state.conv.name) || T.visitor : m.author;
		var body = '';
		if (m.file) {
			body += /^image\//.test(m.file.mime)
				? '<a href="' + esc(m.file.url) + '" target="_blank" rel="noopener" class="flxcb-iimg"><img src="' + esc(m.file.url) + '" alt="' + esc(m.file.name) + '" loading="lazy"></a>'
				: '<a href="' + esc(m.file.url) + '" target="_blank" rel="noopener" class="flxcb-ifile">' + ico('file', 16) + esc(m.file.name) + '</a>';
		}
		if (m.body) body += '<div class="flxcb-itx">' + fmt(m.body) + '</div>';
		if (m.cards && m.cards.length) {
			body += '<div class="flxcb-icards"><small>' + esc(T.products) + '</small>' + m.cards.map(function (p) {
				return '<a href="' + esc(p.url) + '" target="_blank" rel="noopener">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '') + '<span>' + esc(p.name) + '<b>' + esc(p.priceText || '') + '</b></span></a>';
			}).join('') + '</div>';
		}
		return '<div class="flxcb-im is-' + m.role + '" data-mid="' + m.id + '"><div class="flxcb-im__b">' + body + '</div><div class="flxcb-im__m">' + (m.role === 'bot' ? '✦ ' : '') + esc(who) + ' · ' + esc(m.time) + '</div></div>';
	}

	function addMsgs(list, all) {
		var box = $conv.querySelector('[data-msgs]');
		if (!box) return;
		var near = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
		var html = '';
		list.forEach(function (m) {
			if (m.id <= state.last && !all) return;
			if (box.querySelector('[data-mid="' + m.id + '"]')) return;
			html += msgHtml(m);
			if (!all) state.msgs.push(m);
			state.last = Math.max(state.last, m.id);
		});
		if (all) list.forEach(function (m) { state.last = Math.max(state.last, m.id); });
		if (html) box.insertAdjacentHTML('beforeend', html);
		if (all || near) box.scrollTop = box.scrollHeight;
	}

	function bindComposer() {
		var f = $conv.querySelector('[data-comp]');
		var ta = f.querySelector('[data-text]');
		var file = f.querySelector('[data-file]');
		var fb = f.querySelector('[data-filebox]');
		function grow() { ta.style.height = 'auto'; ta.style.height = Math.min(160, ta.scrollHeight) + 'px'; }
		ta.addEventListener('input', function () {
			grow();
			if (!timers.typing && state.cur) {
				api('conversations/' + state.cur + '/typing', { method: 'POST' }).catch(function () {});
				timers.typing = setTimeout(function () { timers.typing = 0; }, 5000);
			}
		});
		ta.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit')); }
		});
		file.addEventListener('change', function () {
			var x = file.files[0];
			fb.hidden = !x;
			fb.innerHTML = x ? ico('file', 14) + '<span>' + esc(x.name) + '</span><button type="button" class="flxcb-ibtn">' + ico('x', 14) + '</button>' : '';
			if (x) fb.querySelector('button').onclick = function () { file.value = ''; fb.hidden = true; };
		});
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = ta.value.trim();
			if ((!text && !file.files[0]) || state.sending) return;
			var fd = new FormData();
			fd.append('text', text);
			if (file.files[0]) fd.append('file', file.files[0]);
			state.sending = true;
			f.classList.add('is-busy');
			api('conversations/' + state.cur + '/reply?after=' + state.last, { method: 'POST', body: fd }).then(function (j) {
				ta.value = '';
				grow();
				file.value = '';
				fb.hidden = true;
				state.conv = j.conv;
				drawHead();
				addMsgs(j.messages || []);
				var box = $conv.querySelector('[data-msgs]');
				box.scrollTop = box.scrollHeight;
				loadList();
			}).catch(alertErr).then(function () {
				state.sending = false;
				f.classList.remove('is-busy');
				ta.focus();
			});
		});
		ta.focus();
	}

	function alertErr(e) {
		window.alert((e && e.message) || T.error);
	}

	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) { loadList(); if (state.cur) fetchConv(); }
	});

	loadList().then(function () { if (state.cur) openConv(state.cur); });
})();
