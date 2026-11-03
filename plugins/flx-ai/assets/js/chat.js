/**
 * Flux AI ortak sohbet: yüzen baloncuk ya da sağa yanaşan panel.
 * Sayfa yüklendikten sonra (boşta) başlar; durum localStorage'da tutulur, sayfa geçişinde aynı yerde açılır.
 * Bir tur kısa adımlarla ilerler (/step); değişiklik yapan araçlar onay kartı ister.
 * Panel kapalıyken ve sayfa yenilense de süren tur kaldığı yerden devam eder; baloncuk son durumu canlı gösterir.
 */
(function () {
	'use strict';
	var C = window.FLXAI_CHAT;
	if (!C || window.__flxaiChat) return;
	window.__flxaiChat = true;

	var L = C.i18n || {};
	function t(s) { return L[s] || s; }
	function tf(s) { var a = arguments, i = 1; return t(s).replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); }

	var KEY = 'flxai-ui';
	var DOCK_MIN = 340, FLOAT_MIN_W = 320, FLOAT_MIN_H = 380;
	var ui = loadUi();
	var S = {
		boot: null, chat: null, msgs: [], optimistic: null, running: false, pending: [],
		list: [], q: '', archived: false, pop: null, menu: null, editing: 0, models: null, err: null,
		openWork: {}, openTool: {}, decisions: {}, busyReq: false, tools: C.tools || {}
	};
	var host, root, els = {};

	function loadUi() {
		var d = { open: false, mode: C.mode === 'dock' ? 'dock' : 'float', w: 420, fw: 400, fh: 640, chat: 0, draft: '', unread: false, model: '' };
		try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); for (var k in s) if (k in d) d[k] = s[k]; } catch (e) {}
		return d;
	}
	function saveUi() { try { localStorage.setItem(KEY, JSON.stringify(ui)); } catch (e) {} }

	/* ---------- Simgeler ---------- */
	var P = {
		spark: '<path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
		x: '<path d="M18 6 6 18M6 6l12 12"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		down: '<path d="m6 9 6 6 6-6"/>',
		dock: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M14 4v16"/>',
		float: '<rect x="3" y="4" width="18" height="16" rx="2"/><rect x="12" y="11" width="7" height="7" rx="1"/>',
		more: '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
		pencil: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
		archive: '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>',
		unarchive: '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M12 17v-5m-2 2 2-2 2 2"/>',
		trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
		brain: '<path d="M9 4a3 3 0 0 0-3 3v.2A3 3 0 0 0 4 10a3 3 0 0 0 1 2.2A3 3 0 0 0 6 17a3 3 0 0 0 3 3V4zM15 4a3 3 0 0 1 3 3v.2A3 3 0 0 1 20 10a3 3 0 0 1-1 2.2A3 3 0 0 1 18 17a3 3 0 0 1-3 3V4z"/>',
		wrench: '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
		edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
		check: '<path d="m5 12 5 5L20 7"/>',
		up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
		stop: '<rect x="7" y="7" width="10" height="10" rx="1.5" fill="currentColor"/>',
		cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
		rewind: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
		retry: '<path d="M4 4v6h6"/><path d="M20 12a8 8 0 0 0-14.9-4L4 10m16 10v-6h-6"/><path d="M4 12a8 8 0 0 0 14.9 4l1.1-2"/>'
	};
	function ic(n) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || '') + '</svg>'; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	/* ---------- Markdown (güvenli, küçük) ---------- */
	function inline(s) {
		var codes = [];
		s = s.replace(/`([^`\n]+)`/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
		s = esc(s);
		s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, txt, url) {
			var u = url.replace(/&amp;/g, '&');
			if (!/^(https?:\/\/|\/|#|mailto:)/i.test(u)) return txt;
			var ext = /^https?:\/\//i.test(u) && u.indexOf(location.host) < 0;
			return '<a href="' + esc(u) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' + txt + '</a>';
		});
		s = s.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, function (m, pre, url) { return pre + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>'; });
		s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
		return s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + esc(codes[+i]) + '</code>'; });
	}
	function md(src) {
		var lines = String(src || '').replace(/\r/g, '').split('\n'), out = [], i = 0;
		while (i < lines.length) {
			var l = lines[i];
			if (/^```/.test(l)) {
				var lang = l.slice(3).trim().toLowerCase().split(/\s/)[0], buf = []; i++;
				while (i < lines.length && !/^```\s*$/.test(lines[i])) buf.push(lines[i++]);
				var closed = i < lines.length; i++;
				if (C.widgets && (lang === 'widget' || lang === 'mermaid')) { out.push(W.slot(lang, buf.join('\n'), closed)); continue; }
				out.push('<pre><code>' + esc(buf.join('\n')) + '</code></pre>'); continue;
			}
			if (/^\s*$/.test(l)) { i++; continue; }
			var h = l.match(/^(#{1,4})\s+(.*)$/);
			if (h) { out.push('<h' + h[1].length + '>' + inline(h[2]) + '</h' + h[1].length + '>'); i++; continue; }
			if (/^(-{3,}|\*{3,})\s*$/.test(l)) { out.push('<hr>'); i++; continue; }
			if (/^\s*\|.*\|\s*$/.test(l) && i + 1 < lines.length && /^\s*\|?\s*:?-+/.test(lines[i + 1])) {
				var row = function (r) { return r.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return c.trim(); }); };
				var head = row(l), tb = '<table><thead><tr>' + head.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
				i += 2;
				while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) { tb += '<tr>' + row(lines[i]).map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>'; i++; }
				out.push(tb + '</tbody></table>'); continue;
			}
			if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) {
				var ol = /^\s*\d+[.)]/.test(l), items = [];
				while (i < lines.length && /^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) {
					var it = lines[i].replace(/^\s*([-*+]|\d+[.)])\s+/, ''); i++;
					while (i < lines.length && /^\s{2,}\S/.test(lines[i]) && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) it += ' ' + lines[i++].trim();
					items.push('<li>' + inline(it) + '</li>');
				}
				out.push((ol ? '<ol>' : '<ul>') + items.join('') + (ol ? '</ol>' : '</ul>')); continue;
			}
			if (/^>\s?/.test(l)) {
				var q = [];
				while (i < lines.length && /^>\s?/.test(lines[i])) q.push(lines[i++].replace(/^>\s?/, ''));
				out.push('<blockquote>' + inline(q.join(' ')) + '</blockquote>'); continue;
			}
			var p = [l]; i++;
			while (i < lines.length && !/^\s*$/.test(lines[i]) && !/^(```|#{1,4}\s|\s*([-*+]|\d+[.)])\s+|>|\s*\|)/.test(lines[i])) p.push(lines[i++]);
			out.push('<p>' + p.map(inline).join('<br>') + '</p>');
		}
		return out.join('');
	}

	/* ---------- Görseller: ```widget (HTML/SVG) ve ```mermaid ----------
	 * Kod hiçbir zaman sayfanın DOM'una basılmaz: sandbox="allow-scripts" (allow-same-origin yok) iframe + CSP.
	 * Yükseklik iframe içinden postMessage ile gelir; yalnız o iframe'den, yalnız sayı, üst sınırla kabul edilir.
	 */
	var W = (function () {
		var codes = {}, heights = {}, failed = {}, svgs = {}, frames = {}, mm = null, seq = 0;
		var FONT = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif';
		var MAX_H = 2400, CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; script-src 'unsafe-inline'";
		function key(type, code) { var h = 5381; for (var i = 0; i < code.length; i++) h = ((h << 5) + h + code.charCodeAt(i)) | 0; return type[0] + (h >>> 0).toString(36) + code.length.toString(36); }
		function codeBlock(code) { return '<pre><code>' + esc(code) + '</code></pre>'; }
		function slot(type, code, closed) {
			if (!closed) return S.running ? '<div class="wg"><div class="wg-ph"><span class="spin"></span>' + esc(t('Görsel hazırlanıyor…')) + '</div></div>' : codeBlock(code);
			var k = key(type, code);
			codes[k] = { type: type, code: code };
			if (failed[k]) return '<div class="wg-fail">' + esc(t('Görsel çizilemedi; kodu:')) + '</div>' + codeBlock(code);
			return '<div class="wg' + (heights[k] ? ' is-ready' : '') + '" data-wk="' + k + '"><div class="wg-ph"><span class="spin"></span>' + esc(t('Görsel hazırlanıyor…')) + '</div></div>';
		}
		function vars() {
			var cs = getComputedStyle(host), m = { bg: '--bg', surface: '--bg2', text: '--text', muted: '--muted', accent: '--brand', 'accent-soft': '--brand-soft', border: '--line', warn: '--warn', danger: '--danger' }, out = '';
			Object.keys(m).forEach(function (n) {
				var v = (cs.getPropertyValue(m[n]) || '').trim().replace(/[;{}<>]/g, '');
				if (v) out += '--' + n + ':' + v + ';';
			});
			return out + 'color-scheme:' + (host.classList.contains('dark') ? 'dark' : 'light') + ';';
		}
		function doc(k, body) {
			// İçerideki betik yalnız yüksekliği ve hatayı bildirir; dışarıya başka bir şey göndermez.
			var js = "(function(){var K=" + JSON.stringify(k) + ",last=0;function s(t,v){parent.postMessage({flxaiw:1,k:K,t:t,v:v},'*')}" +
				"window.addEventListener('error',function(){s('err',0)});" +
				"function h(){var r=document.getElementById('flxaiw-r');var v=Math.ceil(r?r.getBoundingClientRect().height:document.documentElement.scrollHeight);if(v!==last){last=v;s('h',v)}}" +
				"window.addEventListener('load',function(){var r=document.getElementById('flxaiw-r');if(!r||(!r.children.length&&!r.textContent.trim())){s('err',0);return}h();if(window.ResizeObserver)new ResizeObserver(h).observe(r)});})();";
			return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="' + CSP + '">' +
				'<style>:root{' + vars() + '}*{box-sizing:border-box}html,body{margin:0;padding:0;background:transparent;color:var(--text);font:14px/1.5 ' + FONT + ';overflow:hidden}' +
				'#flxaiw-r{display:flow-root;width:100%}svg{max-width:100%;height:auto}table{border-collapse:collapse}</style>' +
				'<script>' + js + '<\/script></head><body><div id="flxaiw-r">' + body + '</div></body></html>';
		}
		function fail(k) {
			failed[k] = 1;
			delete heights[k];
			(frames[k] || []).forEach(function (f) {
				var wrap = f.parentNode;
				if (wrap) wrap.outerHTML = '<div class="wg-fail">' + esc(t('Görsel çizilemedi; kodu:')) + '</div>' + codeBlock(codes[k].code);
			});
			frames[k] = [];
		}
		function mount(wrap, k, body) {
			var f = document.createElement('iframe');
			f.setAttribute('sandbox', 'allow-scripts');
			f.setAttribute('referrerpolicy', 'no-referrer');
			f.setAttribute('title', t('Görsel'));
			f.className = 'wg-frame';
			f.style.colorScheme = host.classList.contains('dark') ? 'dark' : 'light'; // iframe ile içi aynı olmazsa tarayıcı opak zemin çizer
			if (heights[k]) f.style.height = heights[k] + 'px';
			f.srcdoc = doc(k, body);
			(frames[k] = frames[k] || []).push(f);
			wrap.appendChild(f);
			setTimeout(function () { if (!heights[k] && !failed[k] && f.isConnected) fail(k); }, 8000);
		}
		function loadMermaid() {
			if (mm) return mm;
			mm = new Promise(function (ok, no) {
				if (window.mermaid) { ok(window.mermaid); return; }
				var s = document.createElement('script');
				s.src = C.mermaid; s.async = true;
				s.onload = function () { window.mermaid ? ok(window.mermaid) : no(new Error('mermaid')); };
				s.onerror = function () { mm = null; no(new Error('mermaid')); };
				document.head.appendChild(s);
			});
			return mm;
		}
		function mermaidSvg(k, code) {
			if (svgs[k]) return Promise.resolve(svgs[k]);
			return loadMermaid().then(function (m) {
				var dark = host.classList.contains('dark');
				// Yazı ölçüsü sayfada alınır, çizim iframe'de: aynı yazı tipi ve SVG metni (htmlLabels kapalı) kesilmeyi önler.
				m.initialize({ startOnLoad: false, securityLevel: 'strict', theme: dark ? 'dark' : 'neutral', fontFamily: FONT, htmlLabels: false, flowchart: { htmlLabels: false } });
				return m.render('flxaim' + (++seq), code).then(function (r) { svgs[k] = r.svg; return r.svg; });
			});
		}
		function hydrate(rootEl) {
			rootEl.querySelectorAll('.wg[data-wk]').forEach(function (wrap) {
				var k = wrap.getAttribute('data-wk'), c = codes[k];
				if (!c || wrap.querySelector('iframe')) return;
				frames[k] = (frames[k] || []).filter(function (f) { return f.isConnected; });
				if (c.type === 'mermaid') mermaidSvg(k, c.code).then(function (svg) { if (wrap.isConnected) mount(wrap, k, svg); }, function () { fail(k); });
				else mount(wrap, k, c.code);
			});
		}
		window.addEventListener('message', function (e) {
			var d = e.data;
			if (!d || d.flxaiw !== 1 || typeof d.k !== 'string' || !frames[d.k]) return;
			var f = frames[d.k].filter(function (x) { return x.contentWindow === e.source; })[0];
			if (!f) return;
			if (d.t === 'err') { fail(d.k); return; }
			if (d.t !== 'h' || typeof d.v !== 'number' || !isFinite(d.v)) return;
			var hgt = Math.max(24, Math.min(MAX_H, Math.round(d.v)));
			heights[d.k] = hgt;
			f.style.height = hgt + 'px';
			if (f.parentNode) f.parentNode.classList.add('is-ready');
		});
		function reset() { svgs = {}; heights = {}; }
		return { slot: slot, hydrate: hydrate, reset: reset };
	})();

	/* ---------- Yardımcılar ---------- */
	function ms(v) {
		v = +v || 0;
		if (v < 1000) return tf('%s ms', Math.round(v));
		var s = v / 1000;
		if (s < 60) return tf('%s sn', s.toLocaleString(C.locale, { maximumFractionDigits: 1 }));
		return tf('%1$s dk %2$s sn', Math.floor(s / 60), Math.round(s % 60));
	}
	function rel(ts) {
		var d = Math.round(ts - Date.now() / 1000), a = Math.abs(d);
		try {
			var f = new Intl.RelativeTimeFormat(C.locale, { numeric: 'auto' });
			if (a < 60) return f.format(0, 'second').replace(/^./, function (c) { return c.toUpperCase(); });
			if (a < 3600) return f.format(Math.round(d / 60), 'minute');
			if (a < 86400) return f.format(Math.round(d / 3600), 'hour');
			if (a < 86400 * 7) return f.format(Math.round(d / 86400), 'day');
		} catch (e) {}
		return new Date(ts * 1000).toLocaleDateString(C.locale);
	}
	function pretty(v) {
		if (v == null || v === '') return '';
		if (typeof v === 'string') { try { return JSON.stringify(JSON.parse(v), null, 2); } catch (e) { return v; } }
		try { return JSON.stringify(v, null, 2); } catch (e) { return String(v); }
	}
	function argLabel(a) {
		if (!a || typeof a !== 'object') return '';
		var keys = ['title', 'search', 'query', 'q', 'id', 'name', 'slug', 'fix', 'url'];
		for (var i = 0; i < keys.length; i++) if (a[keys[i]] != null && typeof a[keys[i]] !== 'object') return String(a[keys[i]]).slice(0, 60);
		for (var k in a) if (a[k] != null && typeof a[k] !== 'object') return String(a[k]).slice(0, 60);
		return '';
	}
	function toolTitle(n) { return (S.tools[n] && S.tools[n].title) || n; }
	function isWrite(n) { return !!(S.tools[n] && S.tools[n].write); }

	/* ---------- API ---------- */
	function api(method, path, body, timeout) {
		var url = C.rest + (C.rest.indexOf('?') < 0 ? path + (path.indexOf('?') < 0 ? '?' : '&') : path.replace('?', '&') + '&') + '_locale=user' + (method === 'GET' ? '&_t=' + Date.now() : '');
		var ctl = window.AbortController ? new AbortController() : null;
		var timer = ctl ? setTimeout(function () { ctl.abort(); }, timeout || 70000) : 0;
		return fetch(url, {
			method: method, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined,
			headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json', Accept: 'application/json' },
			body: body ? JSON.stringify(body) : undefined
		}).then(function (r) {
			clearTimeout(timer);
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { var e = new Error((j && j.message) || ('HTTP ' + r.status)); e.status = r.status; e.code = j && j.code; throw e; }
				return j;
			});
		}, function (e) { clearTimeout(timer); var er = new Error(t('Bağlantı kurulamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.')); er.net = true; throw er; });
	}

	/* ---------- Kurulum ---------- */
	/* Sayfa geçişi: ayrılırken panelin görüntüsü ve durumu sekmeye (sessionStorage) yazılır; yeni sayfada
	   dock.php bunu ilk boyamada çizer, burada aynı kap devralınır (panel yeniden belirmez). */
	var SNAP = 'flxai-snap';
	function snapshot() {
		try {
			if (!els.wrap || !root) return;
			var css = '';
			var st = root.querySelector('style'), ln = root.querySelector('link');
			if (st && st.getAttribute('data-v') === C.css) css = st.textContent;
			else if (ln && ln.sheet) { try { css = [].map.call(ln.sheet.cssRules, function (r) { return r.cssText; }).join('\n'); } catch (e) {} }
			if (!css) return;
			var clone = els.wrap.cloneNode(true);
			[].forEach.call(clone.querySelectorAll('iframe'), function (f) { f.removeAttribute('srcdoc'); f.removeAttribute('src'); });
			var pw = clone.querySelector('.popwrap'); if (pw) pw.innerHTML = '';
			var b = els.wrap.querySelector('.body');
			var msgs = S.msgs.filter(function (m) { return m !== S.liveMsg; });
			sessionStorage.setItem(SNAP, JSON.stringify({
				v: C.css, css: css, html: clone.innerHTML, cls: host.className, scroll: b ? b.scrollTop : 0,
				state: S.chat ? { chat: S.chat, msgs: msgs, pending: S.pending, running: S.running } : null, boot: S.boot
			}));
		} catch (e) { try { sessionStorage.removeItem(SNAP); } catch (x) {} }
	}
	function readSnap() { try { return JSON.parse(sessionStorage.getItem(SNAP) || 'null'); } catch (e) { return null; } }
	function mount() {
		var early = document.getElementById('flxai-host'), snap = readSnap(), oldWrap = null;
		if (early && early.shadowRoot && early.getAttribute('data-early')) {
			host = early; root = early.shadowRoot; host.removeAttribute('data-early');
			oldWrap = root.lastElementChild;
			var est = root.querySelector('style'); if (est) est.setAttribute('data-v', snap && snap.v || '');
		} else {
			host = document.createElement('div');
			host.id = 'flxai-host';
			host.setAttribute('data-nosnippet', '');
			document.body.appendChild(host);
			root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
		}
		if (!(snap && snap.v === C.css && oldWrap)) {
			var link = document.createElement('link');
			link.rel = 'stylesheet'; link.href = C.css;
			root.appendChild(link);
		}
		var wrap = document.createElement('div');
		els.wrap = wrap;
		if (snap && snap.boot) S.boot = snap.boot;
		if (snap && snap.state && snap.state.chat && ui.chat === snap.state.chat.id) {
			S.chat = snap.state.chat; S.msgs = snap.state.msgs || []; S.pending = snap.state.pending || []; S.running = !!snap.state.running;
		}
		window.addEventListener('pagehide', snapshot);
		theme();
		if (window.MutationObserver) {
			new MutationObserver(theme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
			new MutationObserver(theme).observe(document.body, { attributes: true, attributeFilter: ['class'] });
		}
		if (window.matchMedia) { var mq = matchMedia('(prefers-color-scheme: dark)'); if (mq.addEventListener) mq.addEventListener('change', theme); }
		window.addEventListener('resize', function () { if (ui.open) layout(); });
		document.addEventListener('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && (e.key === 'j' || e.key === 'J')) { e.preventDefault(); ui.open ? close() : open(); }
		});
		renderShell();
		if (S.chat) { els.title.textContent = S.chat.title || t('Yeni sohbet'); renderHint(); renderNotice(); }
		if (ui.open) open(true);
		if (oldWrap) { root.insertBefore(wrap, oldWrap); oldWrap.remove(); var nb = wrap.querySelector('.body'); if (nb && snap) nb.scrollTop = snap.scroll || 0; }
		else root.appendChild(wrap);
		if (S.boot) loadBoot();
		if (ui.chat) loadChat(ui.chat, true);
		renderBubble();
	}
	function theme() {
		var de = document.documentElement;
		// Ön yüzde sitenin kendi Açık / Koyu / Sistem seçicisi varsa (ön ayar: data-flxfw-theme) onu izle; yoksa sistem teması.
		var dark = C.admin ? de.classList.contains('flxa-dark')
			: de.hasAttribute('data-flxfw-theme') ? de.classList.contains('flxfw-dark')
			: (!C.lightOnly && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
		var changed = host.classList.contains('dark') !== !!dark;
		host.classList.toggle('dark', !!dark);
		host.classList.toggle('has-bottom-bar', document.body.classList.contains('flxa-has-bottom'));
		// Görseller tema renklerini içlerine alır; tema değişince yeniden çizilir.
		if (changed && S.bodyHtml) { W.reset(); S.bodyHtml = ''; if (els.body) els.body.innerHTML = ''; renderBody(); }
	}

	function renderShell() {
		S.bodyHtml = '';
		els.wrap.innerHTML =
			'<div class="bwrap"><button class="bubble" type="button" title="' + esc(t('AI\'ya sor')) + '" aria-label="' + esc(t('Yapay zekâ asistanı')) + '"><span class="bico">' + ic('spark') + '</span><span class="lbl">' + esc(t('AI\'ya sor')) + '</span>' +
			'<span class="live-t" aria-live="polite"><b></b><span class="tick"></span></span></button>' +
			'<button class="bstop" type="button" title="' + esc(t('Durdur')) + '" hidden>' + ic('stop') + '</button></div>' +
			'<section class="panel" role="dialog" aria-label="' + esc(t('Yapay zekâ asistanı')) + '" hidden>' +
			'<div class="resize-l" title="' + esc(t('Genişliği değiştir')) + '"></div><div class="resize-c" title="' + esc(t('Boyutu değiştir')) + '"></div>' +
			'<header class="head"><span class="logo">' + ic('spark') + '</span>' +
			'<button class="title-btn" type="button" data-a="list" aria-haspopup="true"><span></span>' + ic('down') + '</button>' +
			'<button class="ib" type="button" data-a="search" title="' + esc(t('Sohbetlerde ara')) + '">' + ic('search') + '</button>' +
			'<button class="ib" type="button" data-a="new" title="' + esc(t('Yeni sohbet')) + '">' + ic('plus') + '</button>' +
			'<button class="ib" type="button" data-a="mode"></button>' +
			'<button class="ib" type="button" data-a="close" title="' + esc(t('Kapat')) + ' (Ctrl+J)">' + ic('x') + '</button></header>' +
			'<div class="popwrap"></div>' +
			'<div class="body" aria-live="polite"></div>' +
			'<div class="notice-wrap"></div>' +
			'<footer class="foot"><div class="composer"><textarea rows="1" placeholder="' + esc(t('Bir şey sorun ya da bir iş verin…')) + '"></textarea>' +
			'<button class="send" type="button" aria-label="' + esc(t('Gönder')) + '">' + ic('up') + '</button></div>' +
			'<div class="hint"></div></footer></section>';
		els.bwrap = els.wrap.querySelector('.bwrap');
		els.bubble = els.wrap.querySelector('.bubble');
		els.bstop = els.wrap.querySelector('.bstop');
		els.bstop.addEventListener('click', function () { stop(); });
		els.panel = els.wrap.querySelector('.panel');
		els.title = els.wrap.querySelector('.title-btn span');
		els.pop = els.wrap.querySelector('.popwrap');
		els.body = els.wrap.querySelector('.body');
		els.notice = els.wrap.querySelector('.notice-wrap');
		els.ta = els.wrap.querySelector('textarea');
		els.send = els.wrap.querySelector('.send');
		els.hint = els.wrap.querySelector('.hint');
		els.mode = els.wrap.querySelector('[data-a="mode"]');
		els.bubble.addEventListener('click', function () { open(); });
		els.wrap.querySelector('.head').addEventListener('click', onHead);
		els.ta.value = ui.draft || '';
		els.ta.addEventListener('input', function () { autosize(); ui.draft = els.ta.value; saveUi(); updateSend(); });
		els.ta.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
			if (e.key === 'Escape') { if (S.pop) closePop(); else if (ui.mode === 'float') close(); }
		});
		els.send.addEventListener('click', function () { S.running ? stop() : submit(); });
		els.wrap.querySelector('.composer').addEventListener('mousedown', function (e) {
			if (e.target === els.ta || (e.target.closest('.send') && !els.send.disabled)) return;
			e.preventDefault(); els.ta.focus();
		});
		els.body.addEventListener('click', onBody);
		els.body.addEventListener('input', function (e) { if (e.target.classList.contains('edit-ta')) { S.editText = e.target.value; e.target.style.height = 'auto'; e.target.style.height = Math.min(240, e.target.scrollHeight) + 'px'; } });
		els.body.addEventListener('keydown', function (e) {
			if (!e.target.classList.contains('edit-ta')) return;
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); editSend(); }
			if (e.key === 'Escape') { e.stopPropagation(); S.editId = ''; renderBody(); }
		});
		els.pop.addEventListener('click', onPop);
		els.pop.addEventListener('input', onPopInput);
		els.pop.addEventListener('keydown', onPopKey);
		els.hint.addEventListener('click', function (e) { if (e.target.closest('.model') && S.boot && S.boot.canManage) openModels(); });
		els.panel.addEventListener('keydown', function (e) { if (e.key === 'Escape' && S.pop) { e.stopPropagation(); closePop(); } });
		setupResize();
	}

	/* ---------- Aç / kapat / yerleşim ---------- */
	function open(restoring) {
		ui.open = true; ui.unread = false; saveUi();
		els.panel.hidden = false;
		els.bwrap.hidden = true;
		layout();
		if (!S.boot) loadBoot();
		if (ui.chat && (!S.chat || S.chat.id !== ui.chat)) loadChat(ui.chat);
		else renderBody();
		if (!restoring) setTimeout(function () { els.ta.focus(); }, 60);
		autosize();
	}
	function close() {
		ui.open = false; saveUi();
		els.panel.hidden = true;
		els.bwrap.hidden = false;
		closePop();
		layout();
		renderBubble();
	}
	function wide() { return window.innerWidth >= 1024; }
	function layout() {
		var html = document.documentElement, docked = ui.open && ui.mode === 'dock' && wide();
		var w = Math.round(Math.max(DOCK_MIN, Math.min(ui.w, Math.min(960, window.innerWidth - 480))));
		html.classList.toggle('flxai-docked', docked);
		if (docked) html.style.setProperty('--flxai-w', w + 'px'); else html.style.removeProperty('--flxai-w');
		var p = els.panel;
		p.classList.toggle('dock', ui.mode === 'dock');
		p.classList.toggle('float', ui.mode !== 'dock');
		if (ui.mode === 'dock') {
			p.style.width = (wide() ? w : Math.min(w, window.innerWidth)) + 'px';
			p.style.height = '';
			p.style.setProperty('--top', '0px');
		} else {
			var fw = Math.max(FLOAT_MIN_W, Math.min(ui.fw, window.innerWidth - 24));
			var fh = Math.max(FLOAT_MIN_H, Math.min(ui.fh, window.innerHeight - 24));
			p.style.width = fw + 'px'; p.style.height = fh + 'px';
		}
		els.mode.innerHTML = ui.mode === 'dock' ? ic('float') : ic('dock');
		els.mode.title = ui.mode === 'dock' ? t('Yüzen pencere') : t('Sağa yerleştir');
		els.mode.hidden = !wide();
		scrollLock(ui.open && window.innerWidth <= 782);
		window.dispatchEvent(new CustomEvent('flxai:layout', { detail: { docked: docked, width: docked ? w : 0 } }));
	}

	/* ---------- Mobil: panel ekranı kaplarken arka plan kaymasın ---------- */
	// overscroll-behavior iOS Safari'de kaydırılamayan alanda (boş sohbet, başlık, alt çubuk) işe yaramaz;
	// sayfa body position:fixed ile sabitlenir, panel içinde de kaydırılacak yer kalmayan dokunuş sayfaya geçmez.
	var lock = null;
	function scrollLock(on) {
		if (on === !!lock) return;
		var de = document.documentElement, b = document.body;
		if (on) {
			var y = window.pageYOffset || de.scrollTop || 0;
			lock = { y: y, html: de.style.cssText, body: b.style.cssText };
			de.style.overflow = 'hidden';
			b.style.position = 'fixed'; b.style.top = -y + 'px'; b.style.left = '0'; b.style.right = '0'; b.style.width = '100%'; b.style.overflow = 'hidden';
			document.addEventListener('touchstart', touchStart, { passive: true });
			document.addEventListener('touchmove', touchMove, { passive: false });
		} else {
			de.style.cssText = lock.html; b.style.cssText = lock.body;
			var sb = de.style.scrollBehavior; de.style.scrollBehavior = 'auto';
			window.scrollTo(0, lock.y);
			de.style.scrollBehavior = sb;
			lock = null;
			document.removeEventListener('touchstart', touchStart);
			document.removeEventListener('touchmove', touchMove);
		}
	}
	var touch = null;
	function touchStart(e) { touch = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null; }
	function touchMove(e) {
		if (!touch || e.touches.length !== 1 || !e.cancelable) return;
		var dx = touch.x - e.touches[0].clientX, dy = touch.y - e.touches[0].clientY;
		var path = e.composedPath ? e.composedPath() : [e.target];
		if (path.indexOf(host) < 0) { e.preventDefault(); return; } // panelin dışı (kaplanmış sayfa)
		var vert = Math.abs(dy) >= Math.abs(dx);
		for (var i = 0; i < path.length && path[i] !== host; i++) {
			var el = path[i];
			if (!el || el.nodeType !== 1) continue;
			var cs = getComputedStyle(el);
			if (vert) {
				if (!/(auto|scroll)/.test(cs.overflowY) || el.scrollHeight <= el.clientHeight + 1) continue;
				if (dy < 0 ? el.scrollTop > 0 : el.scrollTop + el.clientHeight < el.scrollHeight - 1) return;
			} else {
				if (!/(auto|scroll)/.test(cs.overflowX) || el.scrollWidth <= el.clientWidth + 1) continue;
				if (dx < 0 ? el.scrollLeft > 0 : el.scrollLeft + el.clientWidth < el.scrollWidth - 1) return;
			}
		}
		e.preventDefault();
	}
	function setupResize() {
		var l = els.wrap.querySelector('.resize-l'), c = els.wrap.querySelector('.resize-c'), drag = null;
		function down(kind) {
			return function (e) {
				e.preventDefault();
				drag = { kind: kind, x: e.clientX, y: e.clientY, w: ui.mode === 'dock' ? ui.w : ui.fw, h: ui.fh };
				e.target.setPointerCapture && e.target.setPointerCapture(e.pointerId);
				e.target.classList.add('on');
			};
		}
		function move(e) {
			if (!drag) return;
			var dx = drag.x - e.clientX, dy = drag.y - e.clientY;
			if (ui.mode === 'dock') ui.w = Math.max(DOCK_MIN, Math.min(960, window.innerWidth - 480, drag.w + dx));
			else { ui.fw = Math.max(FLOAT_MIN_W, drag.w + dx); if (drag.kind === 'c') ui.fh = Math.max(FLOAT_MIN_H, drag.h + dy); }
			layout();
		}
		function up(e) { if (!drag) return; drag = null; e.target.classList.remove('on'); saveUi(); }
		[l, c].forEach(function (el, i) {
			el.addEventListener('pointerdown', down(i ? 'c' : 'l'));
			el.addEventListener('pointermove', move);
			el.addEventListener('pointerup', up);
			el.addEventListener('pointercancel', up);
		});
		l.addEventListener('dblclick', function () { if (ui.mode === 'dock') ui.w = 420; else ui.fw = 400; layout(); saveUi(); });
	}
	/* Baloncuk: panel kapalıyken süren ya da yeni biten sohbetin son durumu canlı akar. */
	function plain(s) {
		return String(s || '').replace(/```[\s\S]*?(```|$)/g, ' ').replace(/[#*_`>|~\[\]]+/g, '').replace(/\(https?:[^)]*\)/g, '').replace(/\s+/g, ' ').trim();
	}
	function liveInfo() {
		if (ui.open || (!S.chat && !S.running)) return null;
		var title = (S.chat && S.chat.title) || t('Yapay zekâ asistanı');
		if (S.pending.length && !S.running) return { title: title, text: tf('Onayınız gerekiyor: %s', S.pending[0].title), cls: 'need' };
		var last = null, results = {};
		S.msgs.forEach(function (m) { if (m.role === 'tool') results[m.tool_call_id] = 1; });
		for (var i = S.msgs.length - 1; i >= 0; i--) { if (S.msgs[i].role === 'user') break; if (S.msgs[i].role === 'assistant') { last = S.msgs[i]; break; } }
		if (S.running) {
			var txt = '';
			if (last && last.tool_calls && last.tool_calls.length) {
				var open = last.tool_calls.filter(function (c) { return !results[c.id]; })[0] || last.tool_calls[last.tool_calls.length - 1];
				txt = tf('%s çalışıyor…', toolTitle(open.function.name));
				if (last.content && plain(last.content)) txt = plain(last.content).slice(-90) + ' · ' + txt;
			} else if (last && last.content && plain(last.content)) txt = plain(last.content).slice(-120);
			else if (last && last._thinking) txt = plain(last._thinking).slice(-120);
			return { title: title, text: txt || t('Düşünüyor…'), cls: 'run' };
		}
		if (ui.unread && last && last.content) return { title: title, text: plain(last.content).slice(0, 160), cls: 'done' };
		return null;
	}
	var typeTimer = 0;
	function typeTo(el, text) {
		if (el.getAttribute('data-full') === text) return;
		var prev = el.getAttribute('data-full') || '';
		el.setAttribute('data-full', text);
		clearInterval(typeTimer);
		// Ortak başlangıç korunur, yalnız yeni kısım yazılır (canlı akış hissi).
		var i = 0; while (i < prev.length && i < text.length && prev[i] === text[i]) i++;
		if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = text; return; }
		var pos = i, stepN = Math.max(1, Math.ceil((text.length - i) / 60));
		el.textContent = text.slice(0, pos);
		typeTimer = setInterval(function () {
			pos = Math.min(text.length, pos + stepN);
			el.textContent = text.slice(0, pos);
			if (pos >= text.length) clearInterval(typeTimer);
		}, 18);
	}
	function renderBubble() {
		if (!els.bubble) return;
		var info = liveInfo();
		els.bwrap.classList.toggle('live', !!info);
		els.bwrap.classList.toggle('run', !!info && info.cls === 'run');
		els.bwrap.classList.toggle('need', !!info && info.cls === 'need');
		els.bubble.classList.toggle('unread', !!ui.unread);
		els.bstop.hidden = !(info && info.cls === 'run');
		var lt = els.bubble.querySelector('.live-t');
		if (info) {
			lt.querySelector('b').textContent = info.title;
			typeTo(lt.querySelector('.tick'), info.text);
			els.bubble.setAttribute('aria-label', info.title + ': ' + info.text);
		} else {
			lt.querySelector('.tick').removeAttribute('data-full');
			els.bubble.setAttribute('aria-label', t('Yapay zekâ asistanı'));
		}
	}

	/* ---------- Veri ---------- */
	function loadBoot() {
		api('GET', 'boot').then(function (b) {
			S.boot = b;
			if (b.tools) S.tools = b.tools;
			renderHint(); renderNotice();
		}).catch(function () {});
	}
	function loadChat(id, quiet) {
		S.err = null;
		api('GET', 'chats/' + id).then(function (c) {
			S.running = false;
			setChat(c, true);
			// Sayfa yenilendiyse ya da panel kapalıysa da süren tur kaldığı yerden sürer.
			if (c.status === 'running') { S.running = true; renderBody(); step(); }
			else updateSend();
			renderBubble();
		}).catch(function (e) {
			if (e.status === 404) { ui.chat = 0; saveUi(); S.chat = null; S.msgs = []; renderBody(); return; }
			if (!quiet) { S.err = e.message; renderBody(); }
		});
	}
	function setChat(c, replace) {
		var first = !S.chat || S.chat.id !== c.id;
		if (replace || first || !c.since) S.msgs = c.messages || [];
		else S.msgs = S.msgs.slice(0, c.since).concat(c.messages || []);
		S.chat = c;
		S.pending = c.pending || [];
		S.optimistic = null;
		if (ui.chat !== c.id) { ui.chat = c.id; saveUi(); }
		if (first) { S.openWork = {}; S.toBottom = true; }
		els.title.textContent = c.title || t('Yeni sohbet');
		renderBody();
		renderBubble();
	}
	function newChat() {
		if (S.running) return;
		S.chat = null; S.msgs = []; S.pending = []; S.err = null; S.optimistic = null;
		ui.chat = 0; saveUi();
		els.title.textContent = t('Yeni sohbet');
		closePop(); renderBody(); renderHint(); els.ta.focus();
	}
	function page() { return { title: document.title.slice(0, 160), url: location.href.slice(0, 400), admin: !!C.admin }; }

	function submit() {
		var text = els.ta.value.trim();
		if (!text || S.running) return;
		send(text);
	}
	function send(text) {
		S.running = true; S.err = null; S.optimistic = text; S.toBottom = true;
		els.ta.value = ''; ui.draft = ''; saveUi(); autosize();
		renderBody(); updateSend();
		var id = S.chat ? S.chat.id : 0;
		var body = { text: text, page: page(), since: S.msgs.length };
		if (!id && ui.model) body.model = ui.model;
		api('POST', 'chats/' + id + '/send', body).then(handle).catch(fail);
	}
	function handle(r) {
		if (r.chat) setChat(r.chat, false);
		if (r.state === 'continue') { S.running = true; renderBody(); step(); return; }
		if (r.state === 'busy') { S.running = true; setTimeout(step, 2500); return; }
		S.running = false;
		if (r.state === 'approval') S.decisions = {};
		renderBody(); updateSend();
		if (!ui.open && (r.state === 'done' || r.state === 'approval')) { ui.unread = true; saveUi(); }
		renderBubble();
		if (S.pop === 'list') loadList();
	}
	function fail(e) {
		S.running = false;
		dropLive();
		if (S.optimistic && (!S.chat || !S.msgs.length || S.msgs[S.msgs.length - 1].content !== S.optimistic)) {
			// Gönderilemeyen mesaj taslağa geri döner.
			els.ta.value = S.optimistic; ui.draft = S.optimistic; saveUi(); autosize();
		}
		S.optimistic = null;
		if (e.code === 'flxai_not_ready') {
			// Kurulum uyarısı zaten altta; aynı mesaj ikinci kez gösterilmez.
			if (S.boot) S.boot.ready = false;
			renderNotice(); renderBody(); updateSend();
			if (els.notice.firstChild) { els.notice.firstChild.classList.remove('shake'); void els.notice.firstChild.offsetWidth; els.notice.firstChild.classList.add('shake'); }
			return;
		}
		S.err = e.message || String(e);
		S.errRetry = !!(S.chat && e.net);
		renderBody(); updateSend(); renderBubble();
	}
	/* Adım akışla gelir (NDJSON): düşünme, metin ve araçlar oluştukça görünür. Akış kesilirse adım sunucuda
	   sürer; istemci "busy" gibi davranıp kısa süre sonra yeniden ister. */
	function step() {
		if (!S.chat) return;
		var since = S.msgs.length;
		if (!window.ReadableStream || !window.TextDecoder) {
			api('POST', 'chats/' + S.chat.id + '/step', { since: since }).then(handle).catch(fail);
			return;
		}
		streamApi('chats/' + S.chat.id + '/step', { since: since, stream: 1 }, onLive).then(function (r) {
			dropLive();
			handle(r || { state: 'busy' });
		}).catch(function (e) { dropLive(); fail(e); });
	}
	function streamApi(path, body, onEv) {
		var url = C.rest + (C.rest.indexOf('?') < 0 ? path + '?' : path.replace('?', '&') + '&') + '_locale=user';
		var ctl = window.AbortController ? new AbortController() : null;
		var timer = 0, arm = function () { clearTimeout(timer); if (ctl) timer = setTimeout(function () { ctl.abort(); }, 75000); };
		arm();
		return fetch(url, {
			method: 'POST', credentials: 'same-origin', signal: ctl ? ctl.signal : undefined,
			headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json', Accept: 'application/x-ndjson, application/json' },
			body: JSON.stringify(body)
		}).then(function (r) {
			if (!r.ok || !r.body) {
				clearTimeout(timer);
				return r.json().catch(function () { return {}; }).then(function (j) {
					if (!r.ok) { var e = new Error((j && j.message) || ('HTTP ' + r.status)); e.status = r.status; e.code = j && j.code; throw e; }
					return j;
				});
			}
			var reader = r.body.getReader(), dec = new TextDecoder(), buf = '', result = null;
			function line(l) {
				l = l.trim(); if (!l) return;
				var ev; try { ev = JSON.parse(l); } catch (e) { return; }
				if (ev.t === 'result') result = ev; else if (!ev.t && ev.state) result = ev; else onEv(ev);
			}
			function pump() {
				return reader.read().then(function (x) {
					if (x.done) { line(buf); clearTimeout(timer); return result; }
					arm();
					buf += dec.decode(x.value, { stream: true });
					var i; while ((i = buf.indexOf('\n')) >= 0) { line(buf.slice(0, i)); buf = buf.slice(i + 1); }
					return pump();
				});
			}
			return pump();
		}, function () { clearTimeout(timer); var er = new Error(t('Bağlantı kurulamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.')); er.net = true; throw er; })
			.catch(function (e) { clearTimeout(timer); if (e && (e.status || e.net)) throw e; return null; });
	}
	var liveTimer = 0;
	function liveRender() {
		if (liveTimer) return;
		liveTimer = setTimeout(function () { liveTimer = 0; renderBody(); renderBubble(); }, 60);
	}
	function dropLive() {
		if (S.liveMsg) { var i = S.msgs.indexOf(S.liveMsg); if (i >= 0) S.msgs.splice(i, 1); S.liveMsg = null; }
	}
	function onLive(ev) {
		if (ev.t === 'chat') { dropLive(); if (ev.chat) setChat(ev.chat, false); return; }
		if (ev.t === 'model') { dropLive(); S.liveMsg = { role: 'assistant', content: '', _live: 1, tool_calls: [] }; S.msgs.push(S.liveMsg); liveRender(); return; }
		var m = S.liveMsg; if (!m) return;
		if (ev.t === 'text') m.content += ev.d || '';
		else if (ev.t === 'think') m._thinking = (m._thinking || '') + (ev.d || '');
		else if (ev.t === 'tool') {
			var c = m.tool_calls.filter(function (x) { return ev.id && x.id === ev.id; })[0];
			if (c) c.function.name = ev.name || c.function.name;
			else m.tool_calls.push({ id: ev.id || ('live' + m.tool_calls.length), type: 'function', function: { name: ev.name || '', arguments: '{}' } });
		}
		liveRender();
	}
	function stop() {
		if (!S.chat) { S.running = false; updateSend(); return; }
		api('POST', 'chats/' + S.chat.id + '/stop', { since: S.msgs.length }).then(handle).catch(fail);
		S.running = false; updateSend(); renderBubble();
	}
	function decide() {
		if (!S.chat) return;
		var d = S.decisions;
		S.running = true; S.pending = []; renderBody(); updateSend();
		api('POST', 'chats/' + S.chat.id + '/decide', { decisions: d, since: S.msgs.length }).then(handle).catch(fail);
	}

	/* ---------- Düzenle / geri sar ---------- */
	function rewind(mid, mode) {
		if (!S.chat || S.running) return Promise.reject(new Error(t('Asistan şu an çalışıyor; önce durdurun.')));
		return api('POST', 'chats/' + S.chat.id + '/rewind', { msg: mid, mode: mode }).then(function (c) {
			S.pending = []; S.decisions = {}; S.err = null; S.editId = '';
			setChat(c, true);
			return c;
		});
	}
	function editSend() {
		var text = (S.editText || '').trim(), mid = S.editId;
		if (!text || !mid) return;
		rewind(mid, 'edit').then(function () { send(text); }).catch(function (e) { S.err = e.message; renderBody(); });
	}

	/* ---------- Çizim: mesajlar ---------- */
	// Akan cevapta yarım kod bloğu (görsel, tablo kodu) bitene kadar gösterilmez.
	function liveText(s) {
		var parts = s.split('```');
		return parts.length % 2 === 0 ? parts.slice(0, -1).join('```') + '\n\n…' : s;
	}
	function turns() {
		var out = [], cur = null, results = {};
		S.msgs.forEach(function (m) { if (m.role === 'tool') results[m.tool_call_id] = m; });
		S.msgs.forEach(function (m, i) {
			if (m.role === 'user') { cur = null; out.push({ kind: 'user', m: m }); return; }
			if (m.role === 'tool') return;
			if (m.role !== 'assistant') return;
			if (m._error) { out.push({ kind: 'err', m: m }); cur = null; return; }
			if (!cur) { cur = { kind: 'ai', id: m._id || ('i' + i), work: [], answer: '', ms: 0, calls: 0, failed: 0 }; out.push(cur); }
			cur.ms += +m._ms || 0;
			if (m._thinking) cur.work.push({ k: 'think', text: m._thinking });
			if (m.tool_calls && m.tool_calls.length) {
				if (m.content && m.content.trim()) cur.work.push({ k: 'note', text: m.content });
				m.tool_calls.forEach(function (c) {
					var r = results[c.id], args = null;
					try { args = JSON.parse(c.function.arguments || '{}'); } catch (e) {}
					cur.work.push({ k: 'tool', id: c.id, name: c.function.name, args: args, res: r });
					cur.calls++;
					if (r) { cur.ms += +r._ms || 0; if (r._ok === false) cur.failed++; }
				});
			} else if (m.content) cur.answer = m._live ? liveText(m.content) : m.content;
		});
		return out;
	}
	/* Gövde yeniden kurulmaz, farkı yamanır: akışta yalnız değişen metin güncellenir; animasyonlar, açık
	   ayrıntılar, görsel çerçeveleri ve kaydırma yerinde kalır (titreme olmaz). */
	function sameNode(a, b) {
		if (a.nodeType !== b.nodeType) return false;
		if (a.nodeType !== 1) return true;
		return a.tagName === b.tagName && a.getAttribute('data-wk') === b.getAttribute('data-wk') && a.getAttribute('data-k') === b.getAttribute('data-k');
	}
	function patchNode(a, b) {
		if (a.nodeType !== 1) { if (a.nodeValue !== b.nodeValue) a.nodeValue = b.nodeValue; return; }
		if (a.classList.contains('wg') && a.querySelector('iframe')) return; // çizilmiş görsel
		if (a.tagName === 'TEXTAREA' && a === (root.activeElement || document.activeElement)) return;
		var i, at;
		for (i = a.attributes.length - 1; i >= 0; i--) {
			at = a.attributes[i].name;
			if (!b.hasAttribute(at) && !(at === 'open' && a.tagName === 'DETAILS') && !(at === 'class' && a.classList.contains('wg'))) a.removeAttribute(at);
		}
		for (i = 0; i < b.attributes.length; i++) {
			at = b.attributes[i];
			if (at.name === 'class' && a.classList.contains('wg')) continue;
			if (a.getAttribute(at.name) !== at.value) a.setAttribute(at.name, at.value);
		}
		if (!a.isEqualNode(b)) morph(a, b);
	}
	function morph(from, to) {
		var a = from.firstChild, b = to.firstChild, nb;
		while (b) {
			nb = b.nextSibling;
			if (!a) { from.appendChild(b); }
			else if (sameNode(a, b)) { patchNode(a, b); a = a.nextSibling; }
			else { from.insertBefore(b, a); }
			b = nb;
		}
		while (a) { nb = a.nextSibling; from.removeChild(a); a = nb; }
	}
	function renderBody() {
		if (!els.body) return;
		var nearBottom = els.body.scrollHeight - els.body.scrollTop - els.body.clientHeight < 120;
		var html = '';
		if (!S.msgs.length && !S.optimistic && !S.running) {
			html = '<div class="empty"><span class="logo">' + ic('spark') + '</span><h3>' + esc(t('Size nasıl yardımcı olabilirim?')) + '</h3>' +
				'<p>' + esc(C.admin ? t('İçerik, ayarlar, raporlar: yetkinizin açtığı her şey. Değişiklik yapmadan önce onayınızı isterim.') : t('Bu sayfa ve site hakkında sorabilir, iş verebilirsiniz.')) + '</p>' +
				'<div class="sugs">' + (C.suggestions || []).map(function (s) { return '<button class="sug" type="button" data-sug="' + esc(s) + '">' + esc(s) + '</button>'; }).join('') + '</div></div>';
		} else {
			var ts = turns(), last = ts[ts.length - 1], lastUser = null;
			ts.forEach(function (x) { if (x.kind === 'user') lastUser = x; });
			ts.forEach(function (x) {
				if (x.kind === 'user') html += renderUser(x.m, x === lastUser);
				else if (x.kind === 'err') html += '<div class="msg err"><div class="b">⚠️ ' + esc(x.m.content) + '</div></div>';
				else html += renderTurn(x, x === last && S.running && !S.optimistic);
			});
			if (S.optimistic) html += '<div class="msg user"><div class="b">' + esc(S.optimistic) + '</div></div>';
			if (S.running && (S.optimistic || !last || last.kind !== 'ai')) html += '<div class="msg ai"><div class="live"><span class="spin"></span><span class="dots">' + esc(t('Düşünüyor')) + '</span></div></div>';
			if (S.pending.length && !S.running) html += renderApproval();
		}
		if (S.err) html += '<div class="msg err"><div class="b">⚠️ ' + esc(S.err) + (S.errRetry ? ' <button class="btn" type="button" data-a="retry" style="margin-left:6px">' + ic('retry') + esc(t('Tekrar dene')) + '</button>' : '') + '</div></div>';
		if (html !== S.bodyHtml) {
			S.bodyHtml = html;
			var tmp = document.createElement('div');
			tmp.innerHTML = html;
			morph(els.body, tmp);
			if (C.widgets) W.hydrate(els.body);
		}
		if (nearBottom || S.toBottom) { els.body.scrollTop = els.body.scrollHeight; S.toBottom = false; }
		updateSend();
	}
	function renderUser(m, isLast) {
		var id = m._id || '';
		if (id && S.editId === id) {
			return '<div class="msg user editing"><div class="edit"><textarea class="edit-ta" rows="2" aria-label="' + esc(t('Mesajı düzenle')) + '">' + esc(S.editText) + '</textarea>' +
				'<div class="edit-f"><span>' + esc(t('Bu mesaj ve sonrası yeniden yazılır.')) + '</span><button class="btn" type="button" data-a="edit-cancel">' + esc(t('İptal')) + '</button>' +
				'<button class="btn primary" type="button" data-a="edit-send">' + ic('up') + esc(t('Gönder')) + '</button></div></div></div>';
		}
		var acts = id && !S.running ? '<div class="acts">' +
			'<button class="ab" type="button" data-edit="' + esc(id) + '" title="' + esc(t('Düzenle')) + '">' + ic('pencil') + '</button>' +
			(isLast ? '' : '<button class="ab" type="button" data-rewind="' + esc(id) + '" title="' + esc(t('Bu noktaya geri dön')) + '">' + ic('rewind') + '</button>') + '</div>' : '';
		return '<div class="msg user">' + acts + '<div class="b">' + esc(m.content) + '</div></div>';
	}
	function renderTurn(x, live) {
		var h = '<div class="msg ai"><div class="b">';
		if (x.work.length || live) {
			var open = !!S.openWork[x.id] || (live && !x.answer);
			var label = live ? '<span class="dots">' + esc(x.calls ? t('Çalışıyor') : t('Düşünüyor')) + '</span>' : esc(tf('%s düşündü', ms(x.ms)));
			if (x.calls) label += ' · ' + esc(tf(x.calls === 1 ? '%s araç' : '%s araç', x.calls));
			if (x.failed) label += ' <span class="fail">· ' + esc(tf('%s hata', x.failed)) + '</span>';
			h += '<div class="work"><button class="work-btn" type="button" data-work="' + esc(x.id) + '" aria-expanded="' + (open ? 'true' : 'false') + '">' +
				(live ? '<span class="spin"></span>' : ic('brain')) + '<span>' + label + '</span><span class="chev">' + ic('down') + '</span></button>';
			if (open) {
				h += '<div class="work-list">';
				x.work.forEach(function (w) {
					if (w.k === 'think') h += '<div class="think">' + esc(w.text) + '</div>';
					else if (w.k === 'note') h += '<div class="md">' + md(w.text) + '</div>';
					else h += renderTool(w);
				});
				if (live && !x.work.length) h += '<div class="think">' + esc(t('Model cevap hazırlıyor…')) + '</div>';
				h += '</div>';
			}
			h += '</div>';
		}
		if (x.answer) h += '<div class="md">' + md(x.answer) + '</div>';
		return h + '</div></div>';
	}
	function renderTool(w) {
		var r = w.res, state = !r ? 'run' : (r._ok === false ? 'err' : 'ok');
		var cls = 'chip' + (state === 'err' ? ' err' : '') + (isWrite(w.name) ? ' write' : '');
		var ico = state === 'run' ? '<span class="spin" style="width:12px;height:12px"></span>' : ic(isWrite(w.name) ? 'edit' : 'wrench');
		var info = r ? (r._rejected ? t('reddedildi') : ms(r._ms)) : (S.pending.some(function (p) { return p.id === w.id; }) ? t('onay bekliyor') : '');
		var h = '<div><button class="' + cls + '" type="button" data-tool="' + esc(w.id) + '"><span class="ico">' + ico + '</span><b>' + esc(toolTitle(w.name)) + '</b>' +
			(argLabel(w.args) ? '<small>' + esc(argLabel(w.args)) + '</small>' : '') + (info ? '<span class="ms">' + esc(info) + '</span>' : '') + '</button>';
		if (S.openTool[w.id]) {
			var res = r ? pretty(r.content) : '';
			if (res.length > 6000) res = res.slice(0, 6000) + '\n…';
			h += '<div class="detail"><div><label>' + esc(t('Araç')) + '</label><pre>' + esc(w.name) + '</pre></div>' +
				(w.args && Object.keys(w.args).length ? '<div><label>' + esc(t('Girdi')) + '</label><pre>' + esc(pretty(w.args)) + '</pre></div>' : '') +
				'<div><label>' + esc(t('Sonuç')) + '</label><pre>' + esc(res || t('Sonuç yok')) + '</pre></div></div>';
		}
		return h + '</div>';
	}
	function renderApproval() {
		var n = S.pending.length, done = S.pending.filter(function (p) { return S.decisions[p.id]; }).length;
		var h = '<div class="approve"><div class="approve-h">' + ic('alert') + '<div><b>' + esc(t('Onayınız gerekiyor')) + '</b><p>' + esc(t('Asistan aşağıdaki değişiklikleri yapmak istiyor. Siz onaylamadan hiçbiri çalışmaz.')) + '</p></div></div>';
		S.pending.forEach(function (p) {
			var d = S.decisions[p.id] || '', a = p.args || {}, keys = Object.keys(a);
			h += '<div class="call"><div class="call-h"><div class="t">' + esc(p.title) + '<small>' + esc(p.name) + (p.source ? ' · ' + esc(p.source) : '') + '</small></div>' +
				'<span class="seg"><button class="btn no" type="button" data-dec="reject" data-id="' + esc(p.id) + '" aria-pressed="' + (d === 'reject') + '">' + ic('x') + esc(t('Reddet')) + '</button>' +
				'<button class="btn yes" type="button" data-dec="approve" data-id="' + esc(p.id) + '" aria-pressed="' + (d === 'approve') + '">' + ic('check') + esc(t('Onayla')) + '</button></span></div>';
			var simple = keys.filter(function (k) { return a[k] == null || typeof a[k] !== 'object'; }).slice(0, 6);
			if (simple.length) h += '<dl class="kv">' + simple.map(function (k) { return '<dt>' + esc(k) + '</dt><dd title="' + esc(a[k]) + '">' + esc(String(a[k]).slice(0, 140)) + '</dd>'; }).join('') + '</dl>';
			if (keys.length) h += '<details class="args"><summary>' + ic('down') + esc(t('Gönderilecek veri')) + '</summary><pre>' + esc(pretty(a)) + '</pre></details>';
			h += '</div>';
		});
		h += '<div class="approve-f"><span class="n">' + esc(tf('%1$s / %2$s karar verildi', done, n)) + '</span>';
		if (n > 1) h += '<button class="btn" type="button" data-all="reject">' + esc(t('Tümünü reddet')) + '</button><button class="btn" type="button" data-all="approve">' + esc(t('Tümünü onayla')) + '</button>';
		h += '<button class="btn primary" type="button" data-a="decide"' + (done < n ? ' disabled' : '') + '>' + ic('check') + esc(t('Devam et')) + '</button></div></div>';
		return h;
	}
	function onBody(e) {
		var b;
		if ((b = e.target.closest('[data-sug]'))) { send(b.getAttribute('data-sug')); return; }
		if ((b = e.target.closest('[data-work]'))) { var id = b.getAttribute('data-work'); S.openWork[id] = !S.openWork[id]; renderBody(); return; }
		if ((b = e.target.closest('[data-tool]'))) { var tid = b.getAttribute('data-tool'); S.openTool[tid] = !S.openTool[tid]; renderBody(); return; }
		if ((b = e.target.closest('[data-dec]'))) { S.decisions[b.getAttribute('data-id')] = b.getAttribute('data-dec'); renderBody(); return; }
		if ((b = e.target.closest('[data-all]'))) { var v = b.getAttribute('data-all'); S.pending.forEach(function (p) { S.decisions[p.id] = v; }); renderBody(); return; }
		if ((b = e.target.closest('[data-a="decide"]'))) { decide(); return; }
		if ((b = e.target.closest('[data-a="retry"]'))) { S.err = null; S.running = true; renderBody(); step(); return; }
		if ((b = e.target.closest('[data-edit]'))) {
			var mid = b.getAttribute('data-edit'), m = S.msgs.filter(function (x) { return x._id === mid; })[0];
			S.editId = mid; S.editText = m ? m.content : ''; renderBody();
			var ta = els.body.querySelector('.edit-ta'); if (ta) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); ta.style.height = Math.min(240, ta.scrollHeight) + 'px'; }
			return;
		}
		if ((b = e.target.closest('[data-a="edit-cancel"]'))) { S.editId = ''; renderBody(); return; }
		if ((b = e.target.closest('[data-a="edit-send"]'))) { editSend(); return; }
		if ((b = e.target.closest('[data-rewind]'))) {
			if (!window.confirm(t('Bu mesajdan sonraki konuşma silinecek. Devam edilsin mi?'))) return;
			rewind(b.getAttribute('data-rewind'), 'keep').catch(function (er) { S.err = er.message; renderBody(); });
		}
	}

	/* ---------- Alt kısım ---------- */
	function autosize() {
		if (!els.ta.value) { els.ta.style.height = ''; return; }
		els.ta.style.height = '0px';
		var h = els.ta.scrollHeight;
		els.ta.style.height = h ? Math.min(200, Math.max(30, h)) + 'px' : '';
	}
	function updateSend() {
		if (!els.send) return;
		els.send.classList.toggle('stop', S.running);
		els.send.innerHTML = S.running ? ic('stop') : ic('up');
		els.send.setAttribute('aria-label', S.running ? t('Durdur') : t('Gönder'));
		els.send.disabled = !S.running && !els.ta.value.trim();
	}
	function renderHint() {
		var label = (S.chat && S.chat.model) ? (S.modelLabels && S.modelLabels[S.chat.model]) || S.chat.model : (!S.chat && ui.model ? (S.modelLabels && S.modelLabels[ui.model]) || ui.model : (S.boot ? S.boot.modelLabel : ''));
		var mg = S.boot && S.boot.canManage;
		els.hint.innerHTML = (label ? (mg ? '<button class="model" type="button" title="' + esc(t('Model seç')) + '">' : '<span class="model">') + ic('cpu') + '<span>' + esc(label) + '</span>' + (mg ? ic('down') + '</button>' : '</span>') : '') +
			'<span class="k">' + esc(t('Enter gönderir, Shift+Enter yeni satır')) + '</span>';
	}
	function renderNotice() {
		if (!S.boot || S.boot.ready) { els.notice.innerHTML = ''; return; }
		els.notice.innerHTML = '<div class="notice">' + esc(t('Flux AI henüz ayarlanmadı: bir API anahtarı ve model girilmeli.')) +
			(S.boot.canManage && C.settingsUrl ? ' <a href="' + esc(C.settingsUrl) + '">' + esc(t('Ayarlar')) + '</a>' : '') + '</div>';
	}

	/* ---------- Başlık düğmeleri ve açılır listeler ---------- */
	function onHead(e) {
		var b = e.target.closest('[data-a]');
		if (!b) return;
		var a = b.getAttribute('data-a');
		if (a === 'close') close();
		else if (a === 'new') newChat();
		else if (a === 'mode') { ui.mode = ui.mode === 'dock' ? 'float' : 'dock'; saveUi(); layout(); }
		else if (a === 'list') S.pop === 'list' && !S.searchMode ? closePop() : openList(false);
		else if (a === 'search') S.pop === 'list' && S.searchMode ? closePop() : openList(true);
	}
	function closePop() { S.pop = null; S.menu = null; S.editing = 0; els.pop.innerHTML = ''; }
	function openList(search) {
		S.pop = 'list'; S.searchMode = search; S.menu = null; S.editing = 0;
		els.pop.innerHTML = '<div class="pop"><div class="pop-search"><label class="field">' + ic('search') + '<input type="search" placeholder="' + esc(t('Başlıkta ya da içerikte ara…')) + '" value="' + esc(S.q) + '"></label>' +
			'<label class="chk"><input type="checkbox"' + (S.archived ? ' checked' : '') + '> ' + esc(t('Arşivdekileri de göster')) + '</label></div>' +
			'<div class="pop-list"><div class="pop-empty"><span class="spin"></span></div></div><div class="pop-foot">' + esc(t('Enter ilk sonucu açar, Esc kapatır.')) + '</div></div>';
		var inp = els.pop.querySelector('input[type=search]');
		if (search) setTimeout(function () { inp.focus(); }, 30);
		loadList();
	}
	var listTimer;
	function loadList() {
		clearTimeout(listTimer);
		listTimer = setTimeout(function () {
			var q = S.q;
			api('GET', 'chats?q=' + encodeURIComponent(q) + '&archived=' + (S.archived ? -1 : 0)).then(function (r) {
				if (q !== S.q || S.pop !== 'list') return;
				S.list = r.chats || [];
				renderList();
			}).catch(function (e) { var l = els.pop.querySelector('.pop-list'); if (l) l.innerHTML = '<div class="pop-empty">' + esc(e.message) + '</div>'; });
		}, S.q ? 250 : 0);
	}
	function hl(text, q) {
		var s = esc(text);
		if (!q) return s;
		var i = text.toLocaleLowerCase(C.locale).indexOf(q.toLocaleLowerCase(C.locale));
		if (i < 0) return s;
		return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
	}
	function renderList() {
		var box = els.pop.querySelector('.pop-list');
		if (!box) return;
		if (!S.list.length) { box.innerHTML = '<div class="pop-empty">' + esc(S.q ? t('Sonuç yok.') : t('Henüz sohbet yok.')) + '</div>'; return; }
		box.innerHTML = S.list.map(function (c) {
			var cur = S.chat && S.chat.id === c.id;
			if (S.editing === c.id) {
				return '<div class="ci cur" data-id="' + c.id + '"><input type="text" class="ren" value="' + esc(c.title) + '" maxlength="120"><button class="ib" type="button" data-ok="' + c.id + '" title="' + esc(t('Kaydet')) + '">' + ic('check') + '</button></div>';
			}
			return '<div class="ci' + (cur ? ' cur' : '') + '" data-open="' + c.id + '" tabindex="0"><div class="ct"><b>' + hl(c.title || t('Adsız sohbet'), S.q) +
				(c.archived ? '<span class="badge">' + esc(t('Arşiv')) + '</span>' : '') + (c.status === 'approval' ? '<span class="badge warn">' + esc(t('Onay bekliyor')) + '</span>' : '') + '</b>' +
				'<small>' + (c.snippet ? hl(c.snippet, S.q) : esc(rel(c.updated))) + '</small></div>' +
				'<button class="ib' + (S.menu === c.id ? ' on' : '') + '" type="button" data-menu="' + c.id + '" aria-label="' + esc(t('Seçenekler')) + '">' + ic('more') + '</button></div>';
		}).join('');
		if (S.menu) {
			var c = S.list.filter(function (x) { return x.id === S.menu; })[0], row = box.querySelector('[data-menu="' + S.menu + '"]');
			if (c && row) {
				var top = row.getBoundingClientRect().bottom - els.panel.getBoundingClientRect().top + 4;
				els.pop.insertAdjacentHTML('beforeend', '<div class="menu" style="right:18px;top:' + Math.round(top) + 'px">' +
					'<button type="button" data-do="rename" data-id="' + c.id + '">' + ic('pencil') + esc(t('Yeniden adlandır')) + '</button>' +
					'<button type="button" data-do="archive" data-id="' + c.id + '">' + ic(c.archived ? 'unarchive' : 'archive') + esc(c.archived ? t('Arşivden çıkar') : t('Arşive al')) + '</button>' +
					'<button type="button" class="danger" data-do="delete" data-id="' + c.id + '">' + ic('trash') + esc(t('Sil')) + '</button></div>');
			}
		} else {
			var m = els.pop.querySelector('.menu'); if (m) m.remove();
		}
		var ren = box.querySelector('.ren');
		if (ren) { ren.focus(); ren.select(); }
	}
	function onPopInput(e) {
		if (S.pop !== 'list') return; // model aramasının kendi dinleyicisi var
		if (e.target.type === 'search') { S.q = e.target.value.trim(); loadList(); }
		else if (e.target.type === 'checkbox') { S.archived = e.target.checked; loadList(); }
	}
	function onPopKey(e) {
		if (e.target.classList.contains('ren')) {
			if (e.key === 'Enter') { e.preventDefault(); rename(S.editing, e.target.value); }
			if (e.key === 'Escape') { e.stopPropagation(); S.editing = 0; renderList(); }
			return;
		}
		if (e.target.type === 'search' && e.key === 'Enter') {
			e.preventDefault();
			if (S.pop === 'models') { var f = els.pop.querySelector('.ci[data-model]:not([data-model=""])'); if (f) chooseModel(f.getAttribute('data-model')); }
			else if (S.list[0]) pick(S.list[0].id);
			return;
		}
		if (e.target.classList.contains('ci') && e.key === 'Enter') pick(+e.target.getAttribute('data-open'));
		if (e.target.classList.contains('mi') && e.key === 'Enter') e.target.click();
	}
	function onPop(e) {
		var b;
		if ((b = e.target.closest('[data-menu]'))) { e.stopPropagation(); var id = +b.getAttribute('data-menu'); S.menu = S.menu === id ? null : id; renderList(); return; }
		if ((b = e.target.closest('[data-do]'))) {
			var cid = +b.getAttribute('data-id'), act = b.getAttribute('data-do'), c = S.list.filter(function (x) { return x.id === cid; })[0];
			S.menu = null;
			if (act === 'rename') { S.editing = cid; renderList(); }
			else if (act === 'archive' && c) update(cid, { archived: c.archived ? 0 : 1 });
			else if (act === 'delete' && window.confirm(t('Bu sohbet kalıcı olarak silinsin mi?'))) {
				api('POST', 'chats/' + cid + '/delete', {}).then(function () { if (S.chat && S.chat.id === cid) newChat(); else loadList(); });
			} else renderList();
			return;
		}
		if ((b = e.target.closest('[data-ok]'))) { var inp = els.pop.querySelector('.ren'); rename(+b.getAttribute('data-ok'), inp ? inp.value : ''); return; }
		if ((b = e.target.closest('[data-open]'))) { pick(+b.getAttribute('data-open')); return; }
		if ((b = e.target.closest('[data-model]'))) { chooseModel(b.getAttribute('data-model')); return; }
		if (S.menu && !e.target.closest('.menu')) { S.menu = null; renderList(); }
	}
	function pick(id) {
		closePop();
		if (S.running) return;
		if (S.chat && S.chat.id === id) return;
		S.chat = null; S.msgs = []; S.pending = []; S.err = null;
		ui.chat = id; saveUi(); renderBody(); loadChat(id);
	}
	function update(id, data) {
		return api('POST', 'chats/' + id, data).then(function (c) {
			if (S.chat && S.chat.id === id) { S.chat.title = c.title; S.chat.archived = c.archived; S.chat.model = c.model; els.title.textContent = c.title || t('Yeni sohbet'); renderHint(); }
			if (S.pop === 'list') loadList();
		});
	}
	function rename(id, title) {
		title = (title || '').trim();
		S.editing = 0;
		if (!title) { renderList(); return; }
		S.list.forEach(function (c) { if (c.id === id) c.title = title; });
		renderList();
		update(id, { title: title });
	}

	/* Model seçimi (yalnız yönetici). */
	function openModels() {
		S.pop = 'models';
		els.pop.innerHTML = '<div class="pop" style="top:auto;bottom:96px"><div class="pop-search"><label class="field">' + ic('search') + '<input type="search" class="msearch" placeholder="' + esc(t('Model ara…')) + '"></label></div><div class="pop-list"><div class="pop-empty"><span class="spin"></span></div></div></div>';
		var inp = els.pop.querySelector('.msearch');
		inp.addEventListener('input', renderModels);
		setTimeout(function () { inp.focus(); }, 30);
		if (S.models) { renderModels(); return; }
		api('GET', 'models').then(function (r) {
			S.models = (r.models || []).filter(function (m) { return m.tools; });
			S.modelLabels = {};
			S.models.forEach(function (m) { S.modelLabels[m.id] = m.name; });
			renderModels();
		}).catch(function (e) { var l = els.pop.querySelector('.pop-list'); if (l) l.innerHTML = '<div class="pop-empty">' + esc(e.message) + '</div>'; });
	}
	function renderModels() {
		var box = els.pop.querySelector('.pop-list'), q = (els.pop.querySelector('.msearch') || {}).value || '';
		if (!box || !S.models) return;
		// Her kelime adda ya da kimlikte geçmeli; "-", "/", ":" boşluk sayılır ("deepseek v4.1" → deepseek/deepseek-v4.1).
		var norm = function (x) { return String(x).toLowerCase().replace(/[-_/:()]+/g, ' '); };
		var words = norm(q).split(/\s+/).filter(Boolean);
		var list = S.models.filter(function (m) {
			var h = norm(m.id + ' ' + m.name), hc = h.replace(/\s+/g, '');
			return words.every(function (w) { return h.indexOf(w) >= 0 || hc.indexOf(w) >= 0; });
		}).slice(0, 60);
		var cur = (S.chat && S.chat.model) || ui.model || '';
		box.innerHTML = '<div class="ci' + (!cur ? ' cur' : '') + '" data-model=""><div class="ct"><b>' + esc(t('Varsayılan')) + '</b><small>' + esc(S.boot ? S.boot.modelLabel : '') + '</small></div></div>' +
			list.map(function (m) {
				return '<div class="ci' + (m.id === cur ? ' cur' : '') + '" data-model="' + esc(m.id) + '"><div class="ct"><b>' + esc(m.name) + '</b><small>' + esc(m.id) + ' · $' + (+m.in).toFixed(2) + ' / $' + (+m.out).toFixed(2) + '</small></div></div>';
			}).join('') + (!list.length && q ? '<div class="pop-empty">' + esc(t('Sonuç yok.')) + '</div>' : '');
	}
	function chooseModel(id) {
		closePop();
		if (S.chat) update(S.chat.id, { model: id });
		else { ui.model = id; saveUi(); renderHint(); }
	}

	/* ---------- Başlat: sayfa yüklendikten sonra, boşta ---------- */
	function start() {
		if (!document.body) return;
		mount();
	}
	function later() { (window.requestIdleCallback || function (f) { setTimeout(f, 200); })(start, { timeout: 2000 }); }
	var earlyHost = document.getElementById('flxai-host');
	if (earlyHost && earlyHost.getAttribute('data-early')) start();
	else if (document.readyState === 'complete') later(); else window.addEventListener('load', later);
})();
