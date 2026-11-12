/*! Flux AI Chatbot · sohbet penceresi. GPL-2.0-or-later. Bağımlılık yok; Shadow DOM içinde çalışır. */
(function () {
	'use strict';
	var C = window.flxcbConfig;
	if (!C || window.FluxChatbot) {
		return;
	}
	var T = C.i18n || {};
	var W = window;
	var D = document;

	/* ---------------- Simgeler (Lucide çizgileri) ---------------- */
	var P = {
		chat: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		sparkles: '<path d="M9.94 14.06 4 20M14 4l1.5 3.5L19 9l-3.5 1.5L14 14l-1.5-3.5L9 9l3.5-1.5Z"/><path d="M5 3v4M3 5h4"/>',
		bot: '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
		headset: '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
		help: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
		smile: '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
		x: '<path d="M18 6 6 18M6 6l12 12"/>',
		down: '<path d="m6 9 6 6 6-6"/>',
		send: '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.03.94l7.93 3.18a2 2 0 0 1 1.11 1.11Z"/><path d="m21.85 2.15-10.94 10.94"/>',
		mic: '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v3"/>',
		clip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
		back: '<path d="m15 18-6-6 6-6"/>',
		next: '<path d="m9 18 6-6-6-6"/>',
		arrow: '<path d="M5 12h14M12 5l7 7-7 7"/>',
		more: '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
		home: '<path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
		msgs: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		user: '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
		mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
		whatsapp: '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
		link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		checks: '<path d="M18 6 7 17l-5-5M22 10l-7.5 7.5L13 16"/>',
		file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		star: '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01Z"/>',
		end: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		bolt: '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
		search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'
	};
	function ic(n) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || P.chat) + '</svg>';
	}

	/* ---------------- Yardımcılar ---------------- */
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function safeUrl(u) {
		u = String(u || '').trim();
		return /^(https?:|mailto:|tel:|\/|#)/i.test(u) ? u : '';
	}
	function ls(k, v) {
		try {
			if (v === undefined) {
				return W.localStorage.getItem(k);
			}
			if (v === null) {
				W.localStorage.removeItem(k);
			} else {
				W.localStorage.setItem(k, v);
			}
		} catch (e) {}
		return null;
	}
	function ss(k, v) {
		try {
			if (v === undefined) {
				return W.sessionStorage.getItem(k);
			}
			W.sessionStorage.setItem(k, v);
		} catch (e) {}
		return null;
	}
	function fmt(s, n) {
		return String(s || '').replace('%d', n);
	}

	/* Satır içi biçim: Markdown (asistan) ya da WhatsApp biçimi (temsilci). */
	function inline(s, wa) {
		var keep = [];
		function hold(h) {
			keep.push(h);
			return '\u0000' + (keep.length - 1) + '\u0000';
		}
		s = s.replace(/`([^`\n]+)`/g, function (m, c) {
			return hold('<code>' + c + '</code>');
		});
		s = s.replace(/\[([^\]\n]{1,200})\]\(([^)\s]{1,800})\)/g, function (m, t, u) {
			var url = safeUrl(u.replace(/&amp;/g, '&'));
			return url ? hold('<a href="' + esc(url) + '" target="_blank" rel="noopener">' + t + '</a>') : m;
		});
		s = s.replace(/(^|[\s(])((?:https?:\/\/|www\.)[^\s<]+[^\s<.,;:!?)'"])/g, function (m, pre, u) {
			var url = /^www\./i.test(u) ? 'https://' + u : u;
			return pre + hold('<a href="' + esc(url.replace(/&amp;/g, '&')) + '" target="_blank" rel="noopener">' + u + '</a>');
		});
		if (wa) {
			s = s.replace(/(^|[\s_~])\*([^*\n]+)\*(?=$|[\s.,;:!?_~])/g, '$1<strong>$2</strong>');
			s = s.replace(/(^|[\s*~])_([^_\n]+)_(?=$|[\s.,;:!?*~])/g, '$1<em>$2</em>');
			s = s.replace(/(^|[\s*_])~([^~\n]+)~(?=$|[\s.,;:!?*_])/g, '$1<del>$2</del>');
		} else {
			s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
			s = s.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
			s = s.replace(/(^|[^*\w])\*([^*\n]+)\*(?!\w)/g, '$1<em>$2</em>');
		}
		return s.replace(/\u0000(\d+)\u0000/g, function (m, i) {
			return keep[+i];
		});
	}
	function rich(text, wa) {
		var s = esc(text).replace(/\r/g, '');
		var out = [];
		var blocks = s.split(/```/);
		blocks.forEach(function (b, i) {
			if (i % 2) {
				out.push('<pre><code>' + b.replace(/^[a-z]*\n/i, '') + '</code></pre>');
				return;
			}
			var list = null;
			var para = [];
			function flushP() {
				if (para.length) {
					out.push('<p>' + para.join('<br>') + '</p>');
					para = [];
				}
			}
			function flushL() {
				if (list) {
					out.push('<' + list.t + '>' + list.items.join('') + '</' + list.t + '>');
					list = null;
				}
			}
			b.split('\n').forEach(function (line) {
				var ul = line.match(/^\s*[-*•]\s+(.*)$/);
				var ol = line.match(/^\s*\d+[.)]\s+(.*)$/);
				if (ul || ol) {
					flushP();
					var t = ul ? 'ul' : 'ol';
					if (!list || list.t !== t) {
						flushL();
						list = { t: t, items: [] };
					}
					list.items.push('<li>' + inline((ul || ol)[1], wa) + '</li>');
				} else if (!line.trim()) {
					flushL();
					flushP();
				} else {
					flushL();
					para.push(inline(line.replace(/^#{1,6}\s+/, ''), wa));
				}
			});
			flushL();
			flushP();
		});
		return out.join('');
	}

	function isMobile() {
		return W.matchMedia && W.matchMedia('(max-width: 768px)').matches;
	}

	/* ---------------- Durum ---------------- */
	var S = {
		open: false,
		view: 'home',
		token: ls('flxcb_t') || '',
		conv: null,
		msgs: [],
		lastId: 0,
		loaded: false,
		unread: parseInt(ls('flxcb_unread') || '0', 10) || 0,
		busy: false,
		menu: false,
		contactDone: false,
		askContact: false,
		rated: false,
		online: !!C.online,
		pollT: 0,
		lastTyping: 0
	};

	/* ---------------- DOM ---------------- */
	var host = D.createElement('div');
	host.id = 'flxcb';
	var sh = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
	var css = D.createElement('link');
	css.rel = 'stylesheet';
	css.href = C.css;
	sh.appendChild(css);
	var root = D.createElement('div');
	root.className = 'root';
	sh.appendChild(root);
	var el = {};

	function setVars() {
		var st = host.style;
		var fallback = 'var(--wp--preset--color--primary, #1f2937)';
		st.setProperty('--cb-color', C.color || fallback);
		st.setProperty('--cb-color-text', C.colorText || '#fff');
		st.setProperty('--cb-ox', C.offsetX + 'px');
		st.setProperty('--cb-oy', C.offsetY + 'px');
		st.setProperty('--cb-ls', C.launcher.size + 'px');
		st.setProperty('--cb-r', C.radius + 'px');
		st.setProperty('--cb-w', C.width + 'px');
		st.setProperty('--cb-font', C.font === 'system' ? 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif' : 'inherit');
		host.setAttribute('data-pos', C.position);
		root.classList.toggle('grad', C.header === 'gradient');
		root.classList.toggle('mfull', !!C.mobileFull);
		applyTheme();
		applyDevice();
	}
	function applyTheme() {
		var dark = false;
		if (C.theme === 'dark') {
			dark = true;
		} else if (C.theme === 'auto' && W.matchMedia && W.matchMedia('(prefers-color-scheme: dark)').matches) {
			dark = C.flxTheme ? D.body.classList.contains('flx-dark') : true;
		}
		root.classList.toggle('dark', dark);
	}
	function applyDevice() {
		if (C.preview) {
			host.hidden = false;
			return;
		}
		var m = isMobile();
		host.hidden = m ? !C.devices.mobile : !C.devices.desktop;
	}

	function avatar(cls, on) {
		var inner = C.avatar ? '<img src="' + esc(C.avatar) + '" alt="">' : ic(C.avatarIcon === 'logo' ? 'bot' : C.avatarIcon || 'bot');
		return '<span class="av ' + (cls || '') + (on ? ' on' : '') + '">' + inner + '</span>';
	}
	function personAv(a, cls) {
		return '<span class="av ' + (cls || '') + '">' + (a && a.avatar ? '<img src="' + esc(a.avatar) + '" alt="">' : ic('user')) + '</span>';
	}

	function build() {
		var L = C.launcher;
		var main = L.icon === 'avatar' && C.avatar ? '<img src="' + esc(C.avatar) + '" alt="">' : ic(L.icon === 'avatar' ? 'chat' : L.icon);
		root.innerHTML =
			'<div class="panel" role="dialog" aria-label="' + esc(C.botName) + '" hidden></div>' +
			'<div class="teaser" hidden></div>' +
			'<div class="launch-row">' +
			'<button class="launcher" type="button" aria-label="' + esc(T.open) + '" aria-expanded="false"><span class="ic ic-main">' + main + '</span><span class="ic ic-x">' + ic('down') + '</span><span class="badge" hidden></span></button>' +
			(L.label ? '<button class="launch-label" type="button">' + esc(L.label) + '</button>' : '') +
			'</div>';
		el.panel = root.querySelector('.panel');
		el.teaser = root.querySelector('.teaser');
		el.launcher = root.querySelector('.launcher');
		el.badge = root.querySelector('.badge');
		el.launcher.addEventListener('click', toggle);
		var lab = root.querySelector('.launch-label');
		if (lab) {
			lab.addEventListener('click', function () {
				open();
			});
		}
		updateBadge();
	}

	/* ---------------- Ana ekran ---------------- */
	function activeConv() {
		return S.conv && S.conv.status !== 'closed';
	}
	function statusLine() {
		if (S.conv && S.conv.status === 'agent' && S.conv.agent) {
			return S.conv.agent.name;
		}
		if (C.ai) {
			return T.aiReply;
		}
		return S.online ? T.replyTime : T.offline;
	}
	function lastPreview() {
		for (var i = S.msgs.length - 1; i >= 0; i--) {
			var m = S.msgs[i];
			if (m.role !== 'system' && (m.body || m.file)) {
				return (m.role === 'visitor' ? T.you + ': ' : '') + (m.body || (m.file && m.file.name) || '');
			}
		}
		return '';
	}
	function renderHome() {
		S.view = 'home';
		var H = C.home;
		var team = '';
		if (H.team && H.team.length) {
			team = '<span class="avs">' + avatar('sm') + H.team.map(function (a) {
				return personAv(a, 'sm');
			}).join('') + '</span>';
		} else {
			team = avatar('sm');
		}
		var cont = activeConv() && S.msgs.length;
		var quick = (C.quick || []).length
			? '<div class="card"><h3>' + esc(T.askAnything) + '</h3><div class="rows">' + C.quick.map(function (q, i) {
				return '<button class="row" type="button" data-q="' + i + '"><span class="ri">' + ic('search') + '</span><span>' + esc(q) + '</span>' + '<span class="chev">' + ic('next') + '</span></button>';
			}).join('') + '</div></div>'
			: '';
		var links = (H.links || []).length
			? '<div class="card"><h3>' + esc(T.contactUs) + '</h3><div class="rows">' + H.links.map(function (l) {
				var k = l.kind === 'mail' ? 'mail' : l.kind === 'phone' ? 'phone' : l.kind === 'whatsapp' ? 'whatsapp' : 'link';
				return '<a class="row" href="' + esc(safeUrl(l.url) || '#') + '" target="_blank" rel="noopener"><span class="ri' + (k === 'whatsapp' ? ' wa' : '') + '">' + ic(k) + '</span><span>' + esc(l.label) + '</span><span class="chev">' + ic('next') + '</span></a>';
			}).join('') + '</div>' + (C.hours ? '<div class="hours">' + ic('clock') + '<span>' + esc(T.hours) + ': ' + esc(C.hours) + '</span></div>' : '') + '</div>'
			: (C.hours ? '<div class="card"><div class="hours">' + ic('clock') + '<span>' + esc(T.hours) + ': ' + esc(C.hours) + '</span></div></div>' : '');
		var onDot = C.ai || S.online;
		el.panel.innerHTML =
			'<div class="view"><div class="home">' +
			'<div class="hero"><div class="hero-top"><span class="brand">' + team + '</span><button class="icon-btn" type="button" data-act="close" aria-label="' + esc(T.close) + '">' + ic('x') + '</button></div>' +
			'<h2>' + esc(H.title) + '</h2><p>' + esc(H.text) + '</p></div>' +
			'<div class="cards">' +
			'<div class="card"><button class="start" type="button" data-act="chat">' + avatar('', onDot) + '<span class="t"><strong>' + esc(cont ? T.continueChat : T.startChat) + '</strong><small>' + (cont ? esc(lastPreview()) : '<i class="dot' + (onDot ? ' on' : '') + '"></i>' + esc(statusLine())) + '</small></span><span class="go">' + ic('send') + '</span></button></div>' +
			quick + links +
			'</div></div>' + tabs('home') + footHtml(false) + '</div>';
		bindView();
	}
	function tabs(on) {
		return '<nav class="tabs"><button class="tab' + (on === 'home' ? ' on' : '') + '" type="button" data-act="home">' + ic('home') + esc(T.home) + '</button><button class="tab' + (on === 'chat' ? ' on' : '') + '" type="button" data-act="chat">' + ic('msgs') + esc(T.messages) + (S.unread && on !== 'chat' ? '<span class="badge">' + S.unread + '</span>' : '') + '</button></nav>';
	}
	function footHtml(withPrivacy) {
		var parts = [];
		if (withPrivacy && C.privacy) {
			parts.push('<span class="privacy">' + C.privacy + '</span>');
		}
		if (C.branding) {
			parts.push('<a href="https://fluxwp.com/?utm_source=chatbot" target="_blank" rel="noopener">' + ic('bolt') + esc(T.poweredBy) + '</a>');
		}
		return parts.length ? '<div class="foot">' + parts.join('') + '</div>' : '';
	}

	/* ---------------- Sohbet ekranı ---------------- */
	function renderChat() {
		S.view = 'chat';
		S.unread = 0;
		ls('flxcb_unread', '0');
		updateBadge();
		el.panel.innerHTML =
			'<div class="view">' +
			'<div class="head">' + (C.home.enabled ? '<button class="icon-btn" type="button" data-act="home" aria-label="' + esc(T.back) + '">' + ic('back') + '</button>' : '') +
			'<span class="hav"></span><div class="who"><b></b><small></small></div>' +
			'<button class="icon-btn" type="button" data-act="menu" aria-label="…">' + ic('more') + '</button>' +
			'<button class="icon-btn" type="button" data-act="close" aria-label="' + esc(T.close) + '">' + ic('x') + '</button></div>' +
			'<div class="banner" hidden></div>' +
			'<div class="msgs" role="log" aria-live="polite"></div>' +
			'<div class="extras"></div>' +
			'<div class="composer"><div class="cbox">' +
			'<textarea rows="1" placeholder="' + esc(C.placeholder) + '" aria-label="' + esc(C.placeholder) + '" maxlength="2000"></textarea>' +
			(C.dictation && SR ? '<button class="cbtn mic" type="button" data-act="dictate" aria-label="' + esc(T.dictate) + '" title="' + esc(T.dictate) + '">' + ic('mic') + '</button>' : '') +
			(C.uploads ? '<button class="cbtn" type="button" data-act="attach" aria-label="' + esc(T.attach) + '">' + ic('clip') + '</button><input type="file" hidden accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx">' : '') +
			'<button class="cbtn send" type="button" data-act="send" aria-label="' + esc(T.send) + '" disabled>' + ic('send') + '</button>' +
			'</div>' + footHtml(true) + '</div></div>';
		el.msgs = el.panel.querySelector('.msgs');
		el.extras = el.panel.querySelector('.extras');
		el.banner = el.panel.querySelector('.banner');
		el.ta = el.panel.querySelector('textarea');
		el.sendBtn = el.panel.querySelector('.send');
		el.file = el.panel.querySelector('input[type=file]');
		el.head = el.panel.querySelector('.head');
		el.ta.addEventListener('input', onInput);
		el.ta.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
				e.preventDefault();
				sendText();
			}
		});
		if (el.file) {
			el.file.addEventListener('change', function () {
				if (el.file.files[0]) {
					upload(el.file.files[0]);
				}
				el.file.value = '';
			});
			var view = el.panel.querySelector('.view');
			view.addEventListener('dragover', function (e) {
				if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') > -1) {
					e.preventDefault();
					showDrop(true);
				}
			});
			view.addEventListener('dragleave', function (e) {
				if (e.target.classList.contains('drop')) {
					showDrop(false);
				}
			});
			view.addEventListener('drop', function (e) {
				e.preventDefault();
				showDrop(false);
				if (e.dataTransfer.files[0]) {
					upload(e.dataTransfer.files[0]);
				}
			});
		}
		bindView();
		updateHead();
		renderMessages();
		updateExtras();
		if (!isMobile() && !C.preview) {
			setTimeout(function () {
				el.ta && el.ta.focus();
			}, 60);
		}
	}
	function showDrop(on) {
		var v = el.panel.querySelector('.view');
		var d = v.querySelector('.drop');
		if (on && !d) {
			d = D.createElement('div');
			d.className = 'drop';
			d.textContent = T.attach;
			v.appendChild(d);
		} else if (!on && d) {
			d.remove();
		}
	}
	function bindView() {
		el.panel.querySelectorAll('[data-act]').forEach(function (b) {
			b.addEventListener('click', function (e) {
				var a = b.getAttribute('data-act');
				if (a === 'close') {
					close();
				} else if (a === 'home') {
					S.menu = false;
					renderHome();
				} else if (a === 'chat') {
					renderChat();
				} else if (a === 'send') {
					sendText();
				} else if (a === 'dictate') {
					dictate(b);
				} else if (a === 'attach') {
					el.file && el.file.click();
				} else if (a === 'menu') {
					e.stopPropagation();
					toggleMenu();
				}
			});
		});
		el.panel.querySelectorAll('[data-q]').forEach(function (b) {
			b.addEventListener('click', function () {
				renderChat();
				send(C.quick[+b.getAttribute('data-q')]);
			});
		});
	}
	function updateHead() {
		if (S.view !== 'chat' || !el.head) {
			return;
		}
		var agent = S.conv && S.conv.status === 'agent' && S.conv.agent;
		var on = agent || C.ai || S.online;
		el.head.querySelector('.hav').innerHTML = agent ? personAv(agent).replace('class="av ', 'class="av on ') : avatar('', on);
		el.head.querySelector('.who b').textContent = agent ? agent.name : C.botName;
		var sub = agent ? T.agentJoined + ' · ' + C.siteName : (S.conv && S.conv.status === 'queued' ? T.queued : statusLine());
		if (S.conv && S.conv.typing) {
			sub = (agent ? agent.name + ' ' : '') + T.typing;
		}
		el.head.querySelector('.who small').textContent = sub;
		// Kuyruk bandı
		if (S.conv && S.conv.status === 'queued') {
			el.banner.hidden = false;
			el.banner.innerHTML = '<i class="spin"></i><span><b>' + esc(T.queued) + '</b>' + (S.conv.queue ? ' · ' + esc(fmt(T.queue, S.conv.queue)) : '') + '</span>';
		} else {
			el.banner.hidden = true;
		}
	}
	function toggleMenu() {
		var m = el.panel.querySelector('.menu');
		if (m) {
			m.remove();
			return;
		}
		var items = [];
		if (C.live && S.conv && (S.conv.status === 'bot' || S.conv.status === 'closed')) {
			items.push('<button type="button" data-m="human">' + ic('headset') + esc(T.human) + '</button>');
		}
		if (S.conv && S.conv.status !== 'closed') {
			items.push('<button type="button" data-m="end">' + ic('end') + esc(T.end) + '</button>');
		}
		if (!items.length) {
			items.push('<button type="button" data-m="x">' + ic('x') + esc(T.close) + '</button>');
		}
		m = D.createElement('div');
		m.className = 'menu';
		m.innerHTML = items.join('');
		el.panel.querySelector('.view').appendChild(m);
		m.querySelectorAll('[data-m]').forEach(function (b) {
			b.addEventListener('click', function () {
				var k = b.getAttribute('data-m');
				m.remove();
				if (k === 'human') {
					askHuman();
				} else if (k === 'end') {
					endChat();
				} else {
					close();
				}
			});
		});
	}

	/* Mesajlar */
	function dayKey(ts) {
		var d = new Date(ts);
		return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
	}
	function dayLabel(ts) {
		if (dayKey(ts) === dayKey(Date.now())) {
			return T.today;
		}
		try {
			return new Date(ts).toLocaleDateString((C.locale || 'tr').replace('_', '-'), { day: 'numeric', month: 'long' });
		} catch (e) {
			return new Date(ts).toDateString();
		}
	}
	function sizeText(n) {
		return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
	}
	function msgNode(m, prev) {
		if (m.role === 'system') {
			var s = D.createElement('div');
			s.className = 'sys ev-' + (m.event || 'info');
			s.textContent = m.body;
			s.dataset.id = m.id;
			return s;
		}
		var me = m.role === 'visitor';
		var who = me ? 'me' : m.role + (m.author || '');
		var pwho = prev && prev.role !== 'system' ? (prev.role === 'visitor' ? 'me' : prev.role + (prev.author || '')) : '';
		var cont = pwho === who && prev && m.ts - prev.ts < 300000;
		var n = D.createElement('div');
		n.className = 'm' + (me ? ' me' : '') + (cont ? ' cont' : ' gap');
		n.dataset.id = m.id;
		var avHtml = '';
		if (!me) {
			avHtml = '<span class="avs-slot">' + (cont ? '' : (m.role === 'agent' ? personAv(m, 'sm') : avatar('sm'))) + '</span>';
		}
		var body = '';
		if (m.file) {
			var url = esc(m.file.url);
			if (/^image\//.test(m.file.mime)) {
				body += '<a class="img" href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + esc(m.file.name) + '" loading="lazy"></a>';
			} else if (/^audio\//.test(m.file.mime)) {
				body += '<audio controls preload="none" src="' + url + '"></audio>';
			} else {
				body += '<a class="file" href="' + url + '" target="_blank" rel="noopener"><span class="fi">' + ic('file') + '</span><span>' + esc(m.file.name) + '<small>' + sizeText(m.file.size || 0) + '</small></span></a>';
			}
		}
		if (m.body) {
			body += '<div class="bubble">' + (me ? rich(m.body, true) : rich(m.body, m.role === 'agent')) + '</div>';
		}
		if (m.cards && m.cards.length) {
			body += '<div class="pcards">' + m.cards.map(cardHtml).join('') + '</div>';
		}
		n.innerHTML = avHtml + '<div class="col">' + (!me && !cont && m.role === 'agent' && m.author ? '<span class="author">' + esc(m.author) + '</span>' : '') + body + '</div>';
		return n;
	}
	function cardHtml(c) {
		var url = esc(safeUrl(c.url) || '#');
		return '<div class="pc"><a class="pi" href="' + url + '" target="_blank" rel="noopener">' + (c.image ? '<img src="' + esc(c.image) + '" alt="" loading="lazy">' : '') + '</a><div class="pb"><a class="pt" href="' + url + '" target="_blank" rel="noopener">' + esc(c.title) + '</a>' +
			(c.price ? '<span class="pp">' + esc(c.price) + '</span>' : '') + (c.stock === 'out' ? '<span class="ps">' + esc(T.outOfStock) + '</span>' : '') +
			'<div class="pa"><a href="' + url + '" target="_blank" rel="noopener">' + esc(T.view) + '</a>' + (c.cart ? '<a class="pri" href="' + esc(safeUrl(c.cart)) + '">' + esc(T.addToCart) + '</a>' : '') + '</div></div></div>';
	}
	function nearBottom() {
		return !el.msgs || el.msgs.scrollHeight - el.msgs.scrollTop - el.msgs.clientHeight < 120;
	}
	function toBottom(force) {
		if (el.msgs && (force || nearBottom())) {
			el.msgs.scrollTop = el.msgs.scrollHeight;
		}
	}
	function renderMessages() {
		if (!el.msgs) {
			return;
		}
		el.msgs.innerHTML = '';
		var list = S.msgs.slice();
		if (!list.length && C.welcome) {
			list.push({ id: 0, role: 'bot', body: C.welcome, ts: Date.now() });
		}
		/* Yönetim önizlemesi: gerçek sohbet yoksa örnek konuşma. */
		if (C.preview && C.demo && C.demo.length && list.length < 2) {
			list = list.concat(C.demo);
		}
		var prev = null;
		var lastDay = '';
		list.forEach(function (m) {
			var dk = dayKey(m.ts);
			if (dk !== lastDay) {
				var d = D.createElement('div');
				d.className = 'day';
				d.textContent = dayLabel(m.ts);
				el.msgs.appendChild(d);
				lastDay = dk;
				prev = null;
			}
			el.msgs.appendChild(msgNode(m, prev));
			prev = m;
		});
		receipts();
		toBottom(true);
	}
	function appendMsg(m) {
		if (!el.msgs || S.view !== 'chat') {
			return;
		}
		var prev = null;
		for (var i = S.msgs.length - 1; i >= 0; i--) {
			if (S.msgs[i] !== m && S.msgs[i].id !== m.id) {
				prev = S.msgs[i];
				break;
			}
		}
		if (!prev || dayKey(prev.ts) !== dayKey(m.ts)) {
			var d = D.createElement('div');
			d.className = 'day';
			d.textContent = dayLabel(m.ts);
			if (prev || !el.msgs.querySelector('.day')) {
				el.msgs.appendChild(d);
			}
			prev = null;
		}
		var stick = nearBottom();
		el.msgs.appendChild(msgNode(m, prev));
		receipts();
		toBottom(stick || m.role === 'visitor');
	}
	function receipts() {
		if (!el.msgs) {
			return;
		}
		el.msgs.querySelectorAll('.meta.rc').forEach(function (x) {
			x.remove();
		});
		if (!S.conv || (S.conv.status !== 'agent' && S.conv.status !== 'queued')) {
			return;
		}
		var last = null;
		S.msgs.forEach(function (m) {
			if (m.role === 'visitor' && m.id > 0) {
				last = m;
			}
		});
		if (!last) {
			return;
		}
		var n = el.msgs.querySelector('.m.me[data-id="' + last.id + '"] .col');
		if (!n) {
			return;
		}
		var read = last.id <= (S.conv.readUpTo || 0);
		var x = D.createElement('div');
		x.className = 'meta rc' + (read ? ' read' : '');
		x.innerHTML = ic(read ? 'checks' : 'check') + esc(read ? T.seen : T.sent);
		n.appendChild(x);
	}

	/* Yazıyor / araç satırı */
	function showTyping(on, label) {
		if (!el.msgs) {
			return;
		}
		var t = el.msgs.querySelector('.m.typ');
		var tl = el.msgs.querySelector('.tool');
		if (!on) {
			t && t.remove();
			tl && tl.remove();
			return;
		}
		if (!t) {
			t = D.createElement('div');
			t.className = 'm typ gap';
			t.innerHTML = '<span class="avs-slot">' + avatar('sm') + '</span><div class="col"><span class="typing"><i></i><i></i><i></i></span></div>';
			el.msgs.appendChild(t);
		}
		if (label) {
			if (!tl) {
				tl = D.createElement('div');
				tl.className = 'tool';
				el.msgs.insertBefore(tl, t);
			}
			tl.innerHTML = '<span class="shine">' + esc(label) + '</span>';
		}
		toBottom();
	}

	/* Alt bölüm: çipler, formlar, puan */
	function updateExtras() {
		if (!el.extras) {
			return;
		}
		var c = S.conv;
		var h = '';
		var visitorMsgs = S.msgs.filter(function (m) {
			return m.role === 'visitor';
		}).length;
		var botMsgs = S.msgs.filter(function (m) {
			return m.role === 'bot';
		}).length;
		if (c && c.status === 'closed') {
			if (C.rating && !c.rating && !S.rated) {
				h = '<div class="fcard rate"><p style="text-align:center">' + esc(T.rateTitle) + '</p><div class="stars">' + [1, 2, 3, 4, 5].map(function (i) {
					return '<button type="button" data-star="' + i + '" aria-label="' + i + '">' + ic('star') + '</button>';
				}).join('') + '</div><textarea placeholder="' + esc(T.rateNote) + '" hidden></textarea><button class="btn" type="button" data-rate hidden>' + esc(T.rateSend) + '</button></div>';
			} else if (S.rated) {
				h = '<div class="thanks">' + esc(T.rateThanks) + '</div>';
			}
		} else if ((c && c.status === 'offline' && !c.email && !S.contactDone && !C.user) || S.askContact || (!c && C.mode === 'live' && !S.online && C.askEmail !== 'never' && !C.user)) {
			var withMsg = !c;
			h = '<form class="fcard contact" novalidate><p>' + esc(c && c.status === 'offline' ? T.contactIntro : (S.askContact ? T.contactIntro : C.offlineText)) + '</p>' +
				'<input name="name" autocomplete="name" placeholder="' + esc(T.name) + '">' +
				'<input name="email" type="email" autocomplete="email" required placeholder="' + esc(T.email) + '">' +
				(withMsg ? '<textarea name="message" placeholder="' + esc(T.message) + '"></textarea>' : '') +
				'<p class="ferr" hidden></p><button class="btn" type="submit">' + esc(withMsg ? T.leaveMsg : T.contactSend) + '</button></form>';
		} else {
			var chips = [];
			if (!visitorMsgs && (C.quick || []).length) {
				C.quick.forEach(function (q, i) {
					chips.push('<button class="chip" type="button" data-q="' + i + '">' + esc(q) + '</button>');
				});
			}
			if (C.live && c && c.status === 'bot' && botMsgs >= 2 && !S.busy) {
				chips.push('<button class="chip human" type="button" data-human>' + ic('headset') + esc(T.human) + '</button>');
			}
			if (chips.length) {
				h = '<div class="chips">' + chips.join('') + '</div>';
			}
		}
		el.extras.innerHTML = h;
		el.extras.querySelectorAll('[data-q]').forEach(function (b) {
			b.addEventListener('click', function () {
				send(C.quick[+b.getAttribute('data-q')]);
			});
		});
		var hb = el.extras.querySelector('[data-human]');
		if (hb) {
			hb.addEventListener('click', askHuman);
		}
		var stars = el.extras.querySelectorAll('[data-star]');
		var picked = 0;
		stars.forEach(function (b) {
			b.addEventListener('click', function () {
				picked = +b.getAttribute('data-star');
				stars.forEach(function (x) {
					x.classList.toggle('on', +x.getAttribute('data-star') <= picked);
				});
				el.extras.querySelector('.rate textarea').hidden = false;
				el.extras.querySelector('[data-rate]').hidden = false;
				toBottom(true);
			});
		});
		var rb = el.extras.querySelector('[data-rate]');
		if (rb) {
			rb.addEventListener('click', function () {
				rb.disabled = true;
				api('rate', { rating: picked, note: el.extras.querySelector('.rate textarea').value }).then(function (r) {
					S.rated = true;
					if (r.conv) {
						S.conv = r.conv;
					}
					updateExtras();
				}).catch(function () {
					rb.disabled = false;
				});
			});
		}
		var f = el.extras.querySelector('form.contact');
		if (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
				var em = f.email.value.trim();
				var err = f.querySelector('.ferr');
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
					err.hidden = false;
					err.textContent = T.badEmail;
					return;
				}
				var btn = f.querySelector('.btn');
				btn.disabled = true;
				api('contact', { name: f.name.value, email: em, message: f.message ? f.message.value : '', handoff: S.askContact ? 1 : 0 }).then(function (r) {
					S.contactDone = true;
					S.askContact = false;
					absorb(r);
				}).catch(function (er) {
					btn.disabled = false;
					err.hidden = false;
					err.textContent = er.message || T.error;
				});
			});
		}
		toBottom();
	}

	/* ---------------- Ağ ---------------- */
	function req(path, opts) {
		opts = opts || {};
		var h = {};
		if (C.nonce) {
			h['X-WP-Nonce'] = C.nonce;
		}
		var body = opts.body;
		if (body && !(body instanceof FormData)) {
			h['Content-Type'] = 'application/json';
			body = JSON.stringify(body);
		}
		var url = C.rest + path;
		if (opts.query) {
			var q = Object.keys(opts.query).map(function (k) {
				return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]);
			}).join('&');
			url += (url.indexOf('?') > -1 ? '&' : '?') + q;
		}
		return fetch(url, { method: opts.method || (body ? 'POST' : 'GET'), headers: h, body: body, credentials: 'same-origin', cache: 'no-store' });
	}
	function api(path, data, method) {
		var body = Object.assign({ t: S.token, after: S.lastId, page: location.href, title: D.title }, data || {});
		var o = method === 'GET' ? { method: 'GET', query: Object.assign(body, { _t: Date.now() }) } : { body: body };
		return req(path, o).then(function (r) {
			return r.json().catch(function () {
				return {};
			}).then(function (j) {
				if (!r.ok) {
					var e = new Error((j && j.message) || T.error);
					e.status = r.status;
					throw e;
				}
				return j;
			});
		});
	}
	function setToken(t) {
		if (!t) {
			return;
		}
		S.token = t;
		ls('flxcb_t', t);
		try {
			D.cookie = 'flxcb_t=' + encodeURIComponent(t) + ';path=/;max-age=' + 60 * 60 * 24 * 30 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
		} catch (e) {}
	}

	/* Sunucudan gelen durumu işle. */
	function absorb(r) {
		if (!r) {
			return;
		}
		if (r.token) {
			setToken(r.token);
		}
		if (typeof r.online === 'boolean') {
			S.online = r.online;
		}
		var prevStatus = S.conv && S.conv.status;
		if (r.conv !== undefined) {
			S.conv = r.conv;
			ls('flxcb_s', r.conv ? r.conv.status : null);
		}
		// Bekleyen (geçici) ziyaretçi mesajlarını kaldır.
		if (r.messages && r.messages.some(function (m) {
			return m.role === 'visitor';
		})) {
			S.msgs = S.msgs.filter(function (m) {
				if (m.tmp && el.msgs) {
					var n = el.msgs.querySelector('[data-id="' + m.id + '"]');
					n && n.remove();
				}
				return !m.tmp;
			});
		}
		var fresh = 0;
		(r.messages || []).forEach(function (m) {
			if (m.id <= S.lastId && S.msgs.some(function (x) {
				return x.id === m.id;
			})) {
				return;
			}
			S.msgs.push(m);
			S.lastId = Math.max(S.lastId, m.id);
			if (m.role !== 'visitor' && m.role !== 'system') {
				fresh++;
			}
			appendMsg(m);
		});
		if (fresh && (!S.open || S.view !== 'chat' || D.hidden)) {
			S.unread += fresh;
			ls('flxcb_unread', String(S.unread));
			updateBadge();
			ding();
		}
		if (prevStatus !== (S.conv && S.conv.status) && S.conv && S.conv.status !== 'closed') {
			S.rated = false;
		}
		receipts();
		updateHead();
		updateExtras();
		if (S.view === 'home' && S.open) {
			renderHome();
		}
		schedulePoll();
	}

	function session(openFlag) {
		if (!S.token) {
			S.loaded = true;
			return Promise.resolve();
		}
		return api('session', { open: openFlag ? 1 : 0, after: 0 }).then(function (r) {
			S.loaded = true;
			if (!r.conv) {
				setToken('');
				S.token = '';
				ls('flxcb_t', null);
				S.conv = null;
				return;
			}
			S.msgs = [];
			S.lastId = 0;
			S.conv = r.conv;
			if (typeof r.online === 'boolean') {
				S.online = r.online;
			}
			(r.messages || []).forEach(function (m) {
				S.msgs.push(m);
				S.lastId = Math.max(S.lastId, m.id);
			});
			if (S.view === 'chat') {
				renderMessages();
				updateHead();
				updateExtras();
			}
			schedulePoll();
		}).catch(function () {
			S.loaded = true;
		});
	}

	function livePolling() {
		return S.token && S.conv && ['queued', 'agent', 'offline'].indexOf(S.conv.status) > -1;
	}
	function schedulePoll() {
		clearTimeout(S.pollT);
		if (!livePolling() || C.preview && !S.open) {
			return;
		}
		var ms = D.hidden ? 30000 : (S.open && S.view === 'chat' ? (S.conv.status === 'offline' ? 15000 : 3500) : 15000);
		S.pollT = setTimeout(poll, ms);
	}
	function poll() {
		if (S.busy) {
			return schedulePoll();
		}
		api('poll', { open: S.open && S.view === 'chat' && !D.hidden ? 1 : 0 }, 'GET').then(absorb).catch(function (e) {
			if (e.status === 404) {
				S.conv = null;
				return;
			}
			schedulePoll();
		});
	}

	/* Sesli dikte: tarayıcının konuşma tanıması; metin yazma alanına düşer, gönderme ziyaretçide. */
	var SR = W.SpeechRecognition || W.webkitSpeechRecognition;
	var rec = null;
	function dictate(btn) {
		if (rec) {
			rec.stop();
			return;
		}
		var base = el.ta.value ? el.ta.value.replace(/\s*$/, ' ') : '';
		rec = new SR();
		rec.lang = String(C.locale || D.documentElement.lang || 'tr-TR').replace('_', '-');
		rec.interimResults = true;
		rec.continuous = true;
		rec.onresult = function (e) {
			var t = '';
			for (var i = 0; i < e.results.length; i++) {
				t += e.results[i][0].transcript;
			}
			el.ta.value = base + t;
			onInput();
		};
		var done = function () {
			rec = null;
			btn.classList.remove('rec');
			btn.setAttribute('aria-label', T.dictate);
			btn.title = T.dictate;
			el.ta && el.ta.focus();
		};
		rec.onend = done;
		rec.onerror = done;
		btn.classList.add('rec');
		btn.setAttribute('aria-label', T.dictateStop);
		btn.title = T.dictateStop;
		try {
			rec.start();
		} catch (err) {
			done();
		}
	}

	/* Gönderme */
	function onInput() {
		var ta = el.ta;
		ta.style.height = 'auto';
		ta.style.height = Math.min(120, ta.scrollHeight) + 'px';
		el.sendBtn.disabled = !ta.value.trim() || S.busy;
		if (livePolling() && Date.now() - S.lastTyping > 4000 && ta.value.trim()) {
			S.lastTyping = Date.now();
			api('typing', {}).catch(function () {});
		}
	}
	function sendText() {
		if (!el.ta) {
			return;
		}
		var t = el.ta.value.trim();
		if (!t || S.busy) {
			return;
		}
		if (rec) {
			rec.stop();
		}
		el.ta.value = '';
		onInput();
		send(t);
	}
	var tmpSeq = 0;
	function send(text) {
		text = String(text || '').trim();
		if (!text || S.busy) {
			return;
		}
		if (S.view !== 'chat') {
			renderChat();
		}
		S.busy = true;
		var tmp = { id: -(++tmpSeq), tmp: 1, role: 'visitor', body: text, ts: Date.now() };
		S.msgs.push(tmp);
		appendMsg(tmp);
		updateExtras();
		var thinking = C.ai && (!S.conv || S.conv.status === 'bot' || S.conv.status === 'closed') && C.mode !== 'live';
		if (thinking) {
			showTyping(true);
		}
		var stream = null;
		var streamText = '';
		var raf = 0;
		function paint() {
			raf = 0;
			if (stream) {
				var stick = nearBottom();
				stream.querySelector('.bubble').innerHTML = rich(streamText, false);
				toBottom(stick);
			}
		}
		function onEvent(ev) {
			if (ev.e === 'conv') {
				setToken(ev.token);
				S.conv = ev.conv;
				if (ev.msg) {
					var n = el.msgs && el.msgs.querySelector('[data-id="' + tmp.id + '"]');
					tmp.id = ev.msg.id;
					delete tmp.tmp;
					n && (n.dataset.id = ev.msg.id);
					S.lastId = Math.max(S.lastId, ev.msg.id);
				}
			} else if (ev.e === 'tool') {
				var lbl = ev.d === 'search_products' ? T.products : ev.d === 'my_orders' ? T.orders : ev.d === 'search_site' || ev.d === 'read_page' ? T.searching : T.thinking;
				showTyping(true, lbl);
			} else if (ev.e === 'text') {
				if (!stream) {
					showTyping(false);
					stream = D.createElement('div');
					stream.className = 'm gap';
					stream.innerHTML = '<span class="avs-slot">' + avatar('sm') + '</span><div class="col"><div class="bubble stream"></div></div>';
					el.msgs && el.msgs.appendChild(stream);
				}
				streamText += ev.d;
				if (!raf) {
					raf = requestAnimationFrame(paint);
				}
			} else if (ev.e === 'done') {
				showTyping(false);
				if (stream) {
					stream.remove();
					stream = null;
				}
				absorb({ conv: ev.conv, messages: ev.messages, online: ev.online });
			}
		}
		req('message', { body: { t: S.token, text: text, after: S.lastId, page: location.href, title: D.title } }).then(function (r) {
			var ct = r.headers.get('content-type') || '';
			if (!r.ok) {
				return r.json().catch(function () {
					return {};
				}).then(function (j) {
					var err = new Error((j && j.message) || T.error);
					err.status = r.status;
					throw err;
				});
			}
			if (ct.indexOf('ndjson') < 0) {
				return r.json().then(function (j) {
					showTyping(false);
					absorb(j);
				});
			}
			var buf = '';
			function lines(chunk, end) {
				buf += chunk;
				var parts = buf.split('\n');
				buf = end ? '' : parts.pop();
				parts.forEach(function (l) {
					if (l.trim()) {
						try {
							onEvent(JSON.parse(l));
						} catch (e) {}
					}
				});
			}
			if (!r.body || !r.body.getReader || !W.TextDecoder) {
				return r.text().then(function (t) {
					lines(t, true);
				});
			}
			var rd = r.body.getReader();
			var dec = new TextDecoder();
			function pump() {
				return rd.read().then(function (x) {
					if (x.done) {
						lines(dec.decode(), true);
						return;
					}
					lines(dec.decode(x.value, { stream: true }), false);
					return pump();
				});
			}
			return pump();
		}).catch(function (e) {
			// 409: bu sohbet zaten cevaplanıyor. Hata ve yeniden dene gösterme; metni kutuya geri koy.
			if (e.status === 409) {
				S.msgs = S.msgs.filter(function (m) {
					return m !== tmp;
				});
				var gone = el.msgs && el.msgs.querySelector('[data-id="' + tmp.id + '"]');
				if (gone) {
					gone.remove();
				}
				if (el.ta && !String(el.ta.value || '').trim()) {
					el.ta.value = text;
				}
				showTyping(false);
				return;
			}
			showTyping(false);
			if (stream) {
				stream.remove();
			}
			var n = el.msgs && el.msgs.querySelector('[data-id="' + tmp.id + '"] .col');
			if (n) {
				var f = D.createElement('div');
				f.className = 'meta';
				f.innerHTML = '<span>' + esc(e.message || T.error) + '</span> · <a href="#" data-retry>' + esc(T.retry) + '</a>';
				n.appendChild(f);
				f.querySelector('[data-retry]').addEventListener('click', function (ev) {
					ev.preventDefault();
					S.msgs = S.msgs.filter(function (m) {
						return m !== tmp;
					});
					n.parentNode.remove();
					S.busy = false;
					send(text);
				});
			}
		}).then(function () {
			S.busy = false;
			if (el.ta) {
				onInput();
			}
			updateExtras();
			schedulePoll();
		});
	}

	function upload(file) {
		if (!file) {
			return;
		}
		if (file.size > C.uploadMb * 1048576) {
			window.flxDialog.alert(T.tooBig);
			return;
		}
		var go = function () {
			var fd = new FormData();
			fd.append('t', S.token);
			fd.append('after', S.lastId);
			fd.append('file', file);
			showTyping(false);
			return req('upload', { body: fd }).then(function (r) {
				return r.json().then(function (j) {
					if (!r.ok) {
						throw new Error(j.message || T.error);
					}
					absorb(j);
				});
			}).catch(function (e) {
				window.flxDialog.alert(e.message || T.error);
			});
		};
		if (!S.token) {
			// Önce sohbeti açmak için dosya adını mesaj olarak gönder.
			send(file.name);
			var wait = setInterval(function () {
				if (!S.busy) {
					clearInterval(wait);
					if (S.token) {
						go();
					}
				}
			}, 300);
			return;
		}
		go();
	}

	function askHuman() {
		if (!S.conv) {
			return;
		}
		if (C.askEmail === 'always' && !S.conv.email && !C.user) {
			S.askContact = true;
			updateExtras();
			return;
		}
		api('handoff', {}).then(absorb).catch(function (e) {
			window.flxDialog.alert(e.message || T.error);
		});
	}
	function endChat() {
		if (!W.confirm(T.endAsk)) {
			return;
		}
		api('close', {}).then(absorb).catch(function () {});
	}

	/* Ses */
	var actx = null;
	function ding() {
		if (!C.sound || C.preview) {
			return;
		}
		try {
			actx = actx || new (W.AudioContext || W.webkitAudioContext)();
			var o = actx.createOscillator();
			var g = actx.createGain();
			o.type = 'sine';
			o.frequency.setValueAtTime(880, actx.currentTime);
			o.frequency.exponentialRampToValueAtTime(1320, actx.currentTime + 0.08);
			g.gain.setValueAtTime(0.0001, actx.currentTime);
			g.gain.exponentialRampToValueAtTime(0.12, actx.currentTime + 0.02);
			g.gain.exponentialRampToValueAtTime(0.0001, actx.currentTime + 0.35);
			o.connect(g);
			g.connect(actx.destination);
			o.start();
			o.stop(actx.currentTime + 0.4);
		} catch (e) {}
	}
	function updateBadge() {
		if (!el.badge) {
			return;
		}
		el.badge.hidden = !S.unread || S.open;
		el.badge.textContent = S.unread > 9 ? '9+' : S.unread;
	}

	/* ---------------- Aç / kapat ---------------- */
	function open(msg) {
		hideTeaser(true);
		if (!S.open) {
			S.open = true;
			root.classList.add('open');
			el.panel.hidden = false;
			el.launcher.setAttribute('aria-expanded', 'true');
			el.launcher.setAttribute('aria-label', T.close);
			ls('flxcb_open', '1');
			if (isMobile() && C.mobileFull && !C.preview) {
				D.documentElement.style.overflow = 'hidden';
			}
		}
		var after = function () {
			var goChat = typeof msg === 'string' || !C.home.enabled || (activeConv() && S.msgs.length && S.view === 'chat') || (activeConv() && ['queued', 'agent'].indexOf(S.conv.status) > -1);
			if (goChat) {
				renderChat();
			} else {
				renderHome();
			}
			if (typeof msg === 'string' && msg) {
				send(msg);
			}
		};
		updateBadge();
		if (!S.loaded) {
			renderHome();
			session(true).then(after);
		} else {
			after();
			if (S.token && S.view === 'chat') {
				poll();
			}
		}
	}
	function close() {
		if (!S.open) {
			return;
		}
		S.open = false;
		root.classList.remove('open');
		el.panel.hidden = true;
		el.launcher.setAttribute('aria-expanded', 'false');
		el.launcher.setAttribute('aria-label', T.open);
		ls('flxcb_open', null);
		D.documentElement.style.overflow = '';
		updateBadge();
		schedulePoll();
	}
	function toggle() {
		if (S.open) {
			close();
		} else {
			open();
		}
	}

	/* Tanıtım balonu */
	function showTeaser() {
		if (S.open || !C.teaser.text || (!C.preview && (ss('flxcb_teased') || S.conv))) {
			return;
		}
		ss('flxcb_teased', '1');
		el.teaser.innerHTML = avatar('') + '<div><b>' + esc(C.botName) + '</b><p>' + esc(C.teaser.text) + '</p></div><button class="x" type="button" aria-label="' + esc(T.close) + '">' + ic('x') + '</button>';
		el.teaser.hidden = false;
		el.teaser.addEventListener('click', function (e) {
			if (e.target.closest('.x')) {
				hideTeaser();
				return;
			}
			open();
		});
		ding();
	}
	function hideTeaser() {
		if (el.teaser) {
			el.teaser.hidden = true;
		}
	}

	/* ---------------- Başlat ---------------- */
	function mount() {
		var stage = C.preview ? D.querySelector('[data-flxcb-stage]') : null;
		if (stage) {
			host.setAttribute('data-preview', '');
			stage.appendChild(host);
		} else {
			D.body.appendChild(host);
		}
		setVars();
		build();
		/* Yüklenemeyen avatarlar (ör. Gravatar erişilemiyor) simgeye düşer. */
		root.addEventListener('error', function (e) {
			if (e.target && e.target.tagName === 'IMG' && e.target.parentNode && e.target.parentNode.classList.contains('av')) {
				e.target.parentNode.innerHTML = ic('user');
			}
		}, true);
		D.addEventListener('click', function (e) {
			var t = e.target && e.target.closest ? e.target.closest('[data-flxcb-open],a[href$="#flxcb-open"]') : null;
			if (t) {
				e.preventDefault();
				open(t.getAttribute('data-flxcb-message') || undefined);
			}
			if (el.panel && !e.composedPath().some(function (x) {
				return x && x.classList && x.classList.contains('menu');
			})) {
				var m = el.panel.querySelector('.menu');
				m && m.remove();
			}
		});
		D.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && S.open && !C.preview) {
				close();
			}
		});
		D.addEventListener('visibilitychange', function () {
			if (!D.hidden && livePolling()) {
				poll();
			} else {
				schedulePoll();
			}
		});
		if (W.matchMedia) {
			var mq = W.matchMedia('(prefers-color-scheme: dark)');
			mq.addEventListener && mq.addEventListener('change', applyTheme);
			var mm = W.matchMedia('(max-width: 768px)');
			mm.addEventListener && mm.addEventListener('change', applyDevice);
		}
		var st = ls('flxcb_s');
		var wantOpen = /[?&]flxcb=open/.test(location.search) || location.hash === '#flxcb-open' || (ls('flxcb_open') && !isMobile());
		if (C.preview) {
			S.loaded = true;
			open();
		} else if (wantOpen) {
			open();
		} else if (S.token && st && ['queued', 'agent', 'offline'].indexOf(st) > -1) {
			session(false);
		}
		if (C.teaser.enabled && !C.preview && !wantOpen) {
			setTimeout(showTeaser, Math.max(0, C.teaser.delay) * 1000);
		}
	}

	window.FluxChatbot = {
		open: open,
		close: close,
		toggle: toggle,
		/* Yönetim önizlemesi: ayarlar değişince yeniden çizer. */
		update: function (cfg) {
			Object.keys(cfg || {}).forEach(function (k) {
				C[k] = cfg[k];
			});
			T = C.i18n || T;
			if (!host.isConnected) {
				return; // henüz kurulmadı; mount() yeni ayarlarla çizer
			}
			var wasOpen = S.open;
			var view = S.view;
			S.open = false;
			setVars();
			build();
			if (wasOpen) {
				open();
				if (view === 'chat') {
					renderChat();
				}
			}
		},
		/* Önizleme: 'home' | 'chat' görünümü. */
		view: function (v) {
			open();
			if (v === 'chat') {
				renderChat();
			} else {
				renderHome();
			}
		},
		teaser: function () {
			ss('flxcb_teased', '');
			try {
				W.sessionStorage.removeItem('flxcb_teased');
			} catch (e) {}
			close();
			showTeaser();
		}
	};

	if (D.readyState === 'loading') {
		D.addEventListener('DOMContentLoaded', mount);
	} else {
		mount();
	}
})();
