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

	var Rec = window.SpeechRecognition || window.webkitSpeechRecognition || null;
	var KEY = 'flxai-ui';
	var DOCK_MIN = 340, FLOAT_MIN_W = 320, FLOAT_MIN_H = 380;
	var ui = loadUi();
	// ?flxai_chat=ID (ör. Zamanlanmış görevler › Raporu aç): o sohbeti açar; &flxai_ws=PR o değişikliği gösterir.
	var QWS = 0;
	try {
		var qp = new URLSearchParams(location.search), qc = parseInt(qp.get('flxai_chat'), 10);
		if (qc > 0) {
			ui.chat = qc; ui.open = true;
			QWS = parseInt(qp.get('flxai_ws'), 10) || 0;
			if (QWS) { ui.ws = true; ui.wsTab = 'changes'; }
			saveUi();
			qp.delete('flxai_chat'); qp.delete('flxai_ws');
			history.replaceState(null, '', location.pathname + (qp.toString() ? '?' + qp.toString() : '') + location.hash);
		}
	} catch (e) {}
	var S = {
		boot: null, chat: null, msgs: [], optimistic: null, running: false, pending: [],
		list: [], q: '', archived: false, pop: null, menu: null, editing: 0, models: null, err: null,
		openWork: {}, openTool: {}, decisions: {}, busyReq: false, tools: C.tools || {}, files: []
	};
	var host, root, els = {};

	function loadUi() {
		var d = { open: false, mode: C.mode === 'dock' ? 'dock' : 'float', w: 420, fw: 400, fh: 640, chat: 0, draft: '', unread: false, model: '', page: false, ws: false, wsTab: 'files' };
		try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); for (var k in s) if (k in d) d[k] = s[k]; } catch (e) {}
		return d;
	}
	// Tam sayfa sohbette panel hep açık; diğer sayfalardaki açık/kapalı durumu değişmez.
	var keepOpen = ui.open;
	if (C.page) { ui.open = true; ui.ws = !!QWS || window.innerWidth >= 1024; } // dar ekranda dosya paneli sohbeti örtmesin, düğmeyle açılır
	function saveUi() { try { localStorage.setItem(KEY, JSON.stringify(C.page ? Object.assign({}, ui, { open: keepOpen }) : ui)); } catch (e) {} }

	/* ---------- Simgeler ---------- */
	var P = {
		spark: '<path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
		x: '<path d="M18 6 6 18M6 6l12 12"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
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
		clip: '<path d="m21 12-8.6 8.6a5 5 0 0 1-7-7L14 5a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4L15.5 8"/>',
		mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
		doc: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
		stop: '<rect x="7" y="7" width="10" height="10" rx="1.5" fill="currentColor"/>',
		cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
		rewind: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
		retry: '<path d="M4 4v6h6"/><path d="M20 12a8 8 0 0 0-14.9-4L4 10m16 10v-6h-6"/><path d="M4 12a8 8 0 0 0 14.9 4l1.1-2"/>',
		side: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/><path d="M18 8h-1M18 11h-1"/>',
		expand: '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
		shrink: '<path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/>',
		folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
		pr: '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M6 8.5v7M18 15.5V9a3 3 0 0 0-3-3h-4m2-2-2 2 2 2"/>',
		commit: '<circle cx="12" cy="12" r="3.5"/><path d="M3 12h5.5M15.5 12H21"/>',
		back: '<path d="m15 18-6-6 6-6"/>',
		right: '<path d="m9 18 6-6-6-6"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>'
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
			'<div class="main"><header class="head"><span class="logo">' + ic('spark') + '</span>' +
			'<button class="title-btn" type="button" data-a="list" aria-haspopup="true"><span></span>' + ic('down') + '</button>' +
			'<button class="ib" type="button" data-a="search" title="' + esc(t('Sohbetlerde ara')) + '">' + ic('search') + '</button>' +
			'<button class="ib" type="button" data-a="new" title="' + esc(t('Yeni sohbet')) + '">' + ic('plus') + '</button>' +
			'<button class="ib" type="button" data-a="ws" title="' + esc(t('Çalışma alanı')) + '">' + ic('side') + '</button>' +
			'<button class="ib" type="button" data-a="page"></button>' +
			'<button class="ib" type="button" data-a="mode"></button>' +
			'<button class="ib" type="button" data-a="close" title="' + esc(t('Kapat')) + ' (Ctrl+J)">' + ic('x') + '</button></header>' +
			'<div class="popwrap"></div>' +
			'<div class="body" aria-live="polite"></div>' +
			'<div class="notice-wrap"></div>' +
			'<footer class="foot"><div class="composer"><div class="files" hidden></div><div class="crow">' +
			'<button class="cb attach" type="button" title="' + esc(t('Dosya ekle')) + '" aria-label="' + esc(t('Dosya ekle')) + '">' + ic('clip') + '</button>' +
			'<input class="file-in" type="file" multiple hidden accept="' + esc(C.accept || '') + '">' +
			'<textarea rows="1" placeholder="' + esc(t('Bir şey sorun ya da bir iş verin…')) + '"></textarea>' +
			(Rec ? '<button class="cb mic" type="button" title="' + esc(t('Sesle yaz')) + '" aria-label="' + esc(t('Sesle yaz')) + '">' + ic('mic') + '</button>' : '') +
			'<button class="send" type="button" aria-label="' + esc(t('Gönder')) + '">' + ic('up') + '</button></div></div>' +
			'<div class="hint"></div></footer></div><aside class="ws" hidden></aside></section>';
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
		els.files = els.wrap.querySelector('.files');
		els.fileIn = els.wrap.querySelector('.file-in');
		els.wrap.querySelector('.attach').addEventListener('click', function () { els.fileIn.click(); });
		els.fileIn.addEventListener('change', function () { addFiles(els.fileIn.files); els.fileIn.value = ''; });
		els.files.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { S.files.splice(+b.getAttribute('data-rm'), 1); renderFiles(); } });
		els.ta.addEventListener('paste', function (e) { var f = e.clipboardData && e.clipboardData.files; if (f && f.length) { e.preventDefault(); addFiles(f); } });
		els.panel.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) { e.preventDefault(); els.panel.classList.add('drop'); } });
		els.panel.addEventListener('dragleave', function (e) { if (e.target === els.panel || !els.panel.contains(e.relatedTarget)) els.panel.classList.remove('drop'); });
		els.panel.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); els.panel.classList.remove('drop'); addFiles(e.dataTransfer.files); } });
		var micB = els.wrap.querySelector('.mic');
		if (micB) dictate(els.ta, micB, function () { autosize(); ui.draft = els.ta.value; saveUi(); updateSend(); });
		els.hint = els.wrap.querySelector('.hint');
		els.mode = els.wrap.querySelector('[data-a="mode"]');
		els.page = els.wrap.querySelector('[data-a="page"]');
		els.wsBtn = els.wrap.querySelector('[data-a="ws"]');
		els.ws = els.wrap.querySelector('.ws');
		els.ws.addEventListener('click', onWs);
		els.ws.addEventListener('submit', function (e) { e.preventDefault(); wsSave(); });
		if (C.page) { ['close', 'page', 'mode'].forEach(function (a) { var b = els.wrap.querySelector('[data-a="' + a + '"]'); if (b) b.remove(); }); els.page = null; }
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
			if (e.target === els.ta || e.target.closest('.cb') || e.target.closest('.files') || (e.target.closest('.send') && !els.send.disabled)) return;
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
		if (ui.ws) wsLoad();
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
		var paged = ui.open && (ui.page || C.page) && wide();
		var html = document.documentElement, docked = ui.open && ui.mode === 'dock' && wide() && !paged;
		var w = Math.round(Math.max(DOCK_MIN, Math.min(ui.w, Math.min(960, window.innerWidth - 480))));
		html.classList.toggle('flxai-docked', docked);
		if (docked) html.style.setProperty('--flxai-w', w + 'px'); else html.style.removeProperty('--flxai-w');
		var p = els.panel;
		html.classList.toggle('flxai-paged', paged);
		p.classList.toggle('page', paged);
		p.classList.toggle('dock', !paged && ui.mode === 'dock');
		p.classList.toggle('float', !paged && ui.mode !== 'dock');
		if (paged) {
			var bar = document.getElementById('wpadminbar'), menu = document.getElementById('adminmenuback');
			var top = bar ? bar.getBoundingClientRect().height : 0, left = menu && getComputedStyle(menu).display !== 'none' ? menu.getBoundingClientRect().width : 0;
			p.style.width = ''; p.style.height = '';
			p.style.top = top + 'px'; p.style.left = left + 'px';
		} else if (ui.mode === 'dock') {
			p.style.left = ''; p.style.top = '';
			p.style.width = (wide() ? w : Math.min(w, window.innerWidth)) + 'px';
			p.style.height = '';
			p.style.setProperty('--top', '0px');
		} else {
			p.style.left = ''; p.style.top = '';
			var fw = Math.max(FLOAT_MIN_W, Math.min(ui.fw, window.innerWidth - 24));
			var fh = Math.max(FLOAT_MIN_H, Math.min(ui.fh, window.innerHeight - 24));
			p.style.width = fw + 'px'; p.style.height = fh + 'px';
		}
		els.mode.innerHTML = ui.mode === 'dock' ? ic('float') : ic('dock');
		els.mode.title = ui.mode === 'dock' ? t('Yüzen pencere') : t('Sağa yerleştir');
		els.mode.hidden = !wide() || paged;
		if (els.page) {
			els.page.innerHTML = paged ? ic('shrink') : ic('expand');
			els.page.title = paged ? t('Küçült') : t('Tam ekran');
			els.page.hidden = !wide();
		}
		els.ws.hidden = !ui.ws;
		p.classList.toggle('ws-on', !!ui.ws);
		els.wsBtn.classList.toggle('on', !!ui.ws);
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
		S.optimistic = null; S.optFiles = null;
		if (ui.chat !== c.id) { ui.chat = c.id; saveUi(); }
		if (first) { S.openWork = {}; S.toBottom = true; WS.data = null; WS.chat = 0; WS.view = null; if (ui.ws) wsLoad(); else renderWs(); }
		els.title.textContent = c.title || t('Yeni sohbet');
		renderBody();
		renderBubble();
	}
	function newChat() {
		if (S.running) return;
		S.chat = null; S.msgs = []; S.pending = []; S.err = null; S.optimistic = null; WS.data = null; WS.chat = 0; WS.view = null; renderWs();
		ui.chat = 0; saveUi();
		els.title.textContent = t('Yeni sohbet');
		closePop(); renderBody(); renderHint(); els.ta.focus();
	}
	function page() { return { title: document.title.slice(0, 160), url: location.href.slice(0, 400), admin: !!C.admin }; }

	function submit() {
		var text = els.ta.value.trim();
		if ((!text && !S.files.length) || S.running) return;
		send(text);
	}
	function send(text) {
		var files = S.files.slice();
		S.files = []; renderFiles();
		S.running = true; S.err = null; S.optimistic = text; S.optFiles = files; S.toBottom = true;
		els.ta.value = ''; ui.draft = ''; saveUi(); autosize();
		renderBody(); updateSend();
		var id = S.chat ? S.chat.id : 0;
		upload(files).then(function (up) {
			var body = { text: text, page: page(), since: S.msgs.length };
			if (up.length) { body.files = up.map(function (u) { return u.key; }); body.names = up.map(function (u) { return u.name; }); }
			if (!id && ui.model) body.model = ui.model;
			return api('POST', 'chats/' + id + '/send', body);
		}).then(handle).catch(function (e) { S.files = files; renderFiles(); fail(e); });
	}

	/* ---------- Ekler ---------- */
	var MAXF = 5;
	function addFiles(list) {
		Array.prototype.forEach.call(list || [], function (f) {
			if (S.files.length >= MAXF) { S.err = tf('En fazla %s dosya eklenebilir.', MAXF); return; }
			if (C.maxBytes && f.size > C.maxBytes) { S.err = tf('%s çok büyük.', f.name); return; }
			S.files.push({ file: f, name: f.name, type: f.type || '', size: f.size, url: /^image\//.test(f.type) && window.URL ? URL.createObjectURL(f) : '' });
		});
		renderFiles(); renderBody(); updateSend();
		if (els.ta) els.ta.focus();
	}
	function fileChip(f, i, rm) {
		var img = f.url ? '<img src="' + esc(f.url) + '" alt="">' : '<span class="fi">' + ic('doc') + '</span>';
		return '<span class="fchip' + (f.url ? ' img' : '') + '" title="' + esc(f.name) + '">' + img + '<span class="fn">' + esc(f.name) + '</span>' +
			(rm ? '<button type="button" class="frm" data-rm="' + i + '" aria-label="' + esc(t('Kaldır')) + '">' + ic('x') + '</button>' : '') + '</span>';
	}
	function renderFiles() {
		if (!els.files) return;
		els.files.hidden = !S.files.length;
		els.files.innerHTML = S.files.map(function (f, i) { return fileChip(f, i, true); }).join('');
		updateSend();
	}
	function fileUrl(key) { return C.rest + (C.rest.indexOf('?') < 0 ? 'files/' + encodeURIComponent(key) + '?' : 'files/' + encodeURIComponent(key) + '&') + '_wpnonce=' + encodeURIComponent(C.nonce); }
	function upload(files) {
		var out = [], p = Promise.resolve();
		files.forEach(function (f) {
			p = p.then(function () {
				var fd = new FormData(); fd.append('file', f.file, f.name);
				var url = C.rest + (C.rest.indexOf('?') < 0 ? 'files?' : 'files&') + '_locale=user';
				return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce, Accept: 'application/json' }, body: fd })
					.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw new Error((j && j.message) || ('HTTP ' + r.status)); out.push({ key: j.key, name: f.name }); }); });
			});
		});
		return p.then(function () { return out; });
	}
	/* Sesle yazma: tarayıcının konuşma tanıma özelliği (Chrome, Edge, Safari). Metin imlecin olduğu yere eklenir. */
	function dictate(ta, btn, onChange) {
		if (!Rec || !ta || !btn) return;
		var rec = null, base = '';
		btn.addEventListener('click', function () {
			if (rec) { rec.stop(); return; }
			rec = new Rec();
			rec.lang = C.locale || document.documentElement.lang || 'tr-TR';
			rec.interimResults = true; rec.continuous = true;
			base = ta.value ? ta.value.replace(/\s*$/, ' ') : '';
			rec.onresult = function (e) {
				var fin = '', tmp = '';
				for (var i = 0; i < e.results.length; i++) { if (e.results[i].isFinal) fin += e.results[i][0].transcript; else tmp += e.results[i][0].transcript; }
				ta.value = base + fin + tmp;
				if (onChange) onChange();
			};
			rec.onend = function () { rec = null; btn.classList.remove('on'); btn.setAttribute('aria-pressed', 'false'); if (onChange) onChange(); };
			rec.onerror = function (e) { if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { S.err = t('Mikrofon izni verilmedi.'); if (els.body) renderBody(); } };
			try { rec.start(); btn.classList.add('on'); btn.setAttribute('aria-pressed', 'true'); ta.focus(); } catch (er) { rec = null; }
		});
	}
	function handle(r) {
		if (r.chat) setChat(r.chat, false);
		if (r.state === 'continue') { S.running = true; renderBody(); step(); return; }
		if (r.state === 'busy') { S.running = true; setTimeout(step, 2500); return; }
		S.running = false;
		if (r.state === 'approval') S.decisions = {};
		renderBody(); updateSend();
		if (ui.ws || usedFiles()) wsLoad(true);
		if (!ui.open && (r.state === 'done' || r.state === 'approval')) { ui.unread = true; saveUi(); }
		renderBubble();
		if (S.pop === 'list') loadList();
	}
	function fail(e) {
		S.running = false;
		dropLive();
		if ((S.optimistic || (S.optFiles && S.optFiles.length)) && (!S.chat || !S.msgs.length || S.msgs[S.msgs.length - 1].content !== S.optimistic)) {
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
					if (r && /^flxfm_/.test(c.function.name || '')) {
						var rj = null; try { rj = JSON.parse(r.content || 'null'); } catch (e) {}
						if (rj && rj.staged && rj.change) { cur.ch = cur.ch || {}; cur.ch['p' + rj.change] = { id: rj.change, pr: 1, n: ((cur.ch['p' + rj.change] || {}).n || 0) + 1 }; }
						else if (rj && rj.commit) { cur.ch = cur.ch || {}; cur.ch['c' + rj.commit] = { id: rj.commit, pr: 0, n: ((cur.ch['c' + rj.commit] || {}).n || 0) + 1 }; }
					}
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
		if (!S.msgs.length && !S.optimistic && !S.running && !(S.optFiles && S.optFiles.length)) {
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
			if (S.optimistic || (S.optFiles && S.optFiles.length && S.running)) html += '<div class="msg user">' + (S.optFiles && S.optFiles.length ? '<div class="mfiles">' + S.optFiles.map(function (f) { return fileChip(f, 0, false); }).join('') + '</div>' : '') + (S.optimistic ? '<div class="b">' + esc(S.optimistic) + '</div>' : '') + '</div>';
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
		var task = m._task ? '<div class="task" title="' + esc(t('Zamanlanmış görev')) + '">' + ic('clock') + '<span>' + esc(tf('Zamanlanmış görev · %s', m._task.title || '')) + '</span></div>' : '';
		var files = (m._files || []).length ? '<div class="mfiles">' + m._files.map(function (f) {
			var isImg = /^image\//.test(f.type || ''), u = fileUrl(f.key);
			return '<a class="fchip' + (isImg ? ' img' : '') + '" href="' + esc(u) + '" target="_blank" rel="noopener" title="' + esc(f.name) + '">' + (isImg ? '<img src="' + esc(u) + '" alt="" loading="lazy">' : '<span class="fi">' + ic('doc') + '</span>') + '<span class="fn">' + esc(f.name) + '</span></a>';
		}).join('') + '</div>' : '';
		return '<div class="msg user' + (task ? ' is-task' : '') + '">' + acts + files + (task || m.content ? '<div class="b">' + task + esc(m.content) + '</div>' : '') + '</div>';
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
		if (x.ch) {
			h += '<div class="chlinks">' + Object.keys(x.ch).map(function (k) {
				var c = x.ch[k], known = WS.data && WS.chat === (S.chat && S.chat.id) ? WS.data.changes.filter(function (z) { return z.id === c.id; })[0] : null;
				var st = known ? known.status : 'open', wait = c.pr && st === 'open';
				var lbl = !c.pr ? t('Asistanın klasöründe değişiklik') : wait ? tf('Değişiklik isteği #%s: onayınızı bekliyor', c.id)
					: st === 'rejected' ? tf('Değişiklik isteği #%s: reddedildi', c.id) : tf('Değişiklik isteği #%s: birleştirildi', c.id);
				return '<button type="button" class="chlink' + (wait ? ' pr' : '') + '" data-showch="' + c.id + '">' + ic(c.pr ? 'pr' : 'commit') +
					'<span>' + esc(lbl) + '</span><b>' + esc(t('Farkı gör')) + '</b></button>';
			}).join('') + '</div>';
		}
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
	async function onBody(e) {
		var b;
		if ((b = e.target.closest('[data-sug]'))) { send(b.getAttribute('data-sug')); return; }
		if ((b = e.target.closest('[data-showch]'))) { showChanges(+b.getAttribute('data-showch')); return; }
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
			if (!(await window.flxDialog.confirm({ message: t('Bu mesajdan sonraki konuşma silinecek. Devam edilsin mi?'), danger: true }))) return;
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
		els.send.disabled = !S.running && !els.ta.value.trim() && !S.files.length;
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
		else if (a === 'page') { ui.page = !ui.page; saveUi(); layout(); }
		else if (a === 'ws') { ui.ws = !ui.ws; if (ui.ws && wide() && !C.page) ui.page = true; saveUi(); layout(); if (ui.ws) wsLoad(); }
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
	async function onPop(e) {
		var b;
		if ((b = e.target.closest('[data-menu]'))) { e.stopPropagation(); var id = +b.getAttribute('data-menu'); S.menu = S.menu === id ? null : id; renderList(); return; }
		if ((b = e.target.closest('[data-do]'))) {
			var cid = +b.getAttribute('data-id'), act = b.getAttribute('data-do'), c = S.list.filter(function (x) { return x.id === cid; })[0];
			S.menu = null;
			if (act === 'rename') { S.editing = cid; renderList(); }
			else if (act === 'archive' && c) update(cid, { archived: c.archived ? 0 : 1 });
			else if (act === 'delete' && (await window.flxDialog.confirm({ message: t('Bu sohbet kalıcı olarak silinsin mi?'), danger: true }))) {
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

	/* ---------- Çalışma alanı: Dosyalar, Değişiklikler (commit / PR), Ayarlar ---------- */
	var WS = { data: null, chat: 0, kids: {}, open: {}, view: null, detail: {}, openCh: {}, busy: false, err: '', note: '' };
	function usedFiles() {
		for (var i = S.msgs.length - 1; i >= 0 && i > S.msgs.length - 40; i--) {
			var m = S.msgs[i];
			if (m.role === 'tool' && /^flxfm_/.test(m._name || '')) return true;
			if (m.role === 'user') break;
		}
		return false;
	}
	function wsLoad(force) {
		if (!els.ws) return;
		if (!S.chat) { WS.data = null; renderWs(); return; }
		var id = S.chat.id;
		if (!force && WS.chat === id && WS.data) { renderWs(); return; }
		if (WS.chat !== id) { WS.kids = {}; WS.open = {}; WS.detail = {}; WS.openCh = {}; WS.view = null; }
		WS.chat = id; WS.err = '';
		if (!WS.data) renderWs();
		api('GET', 'chats/' + id + '/ws').then(function (d) {
			if (!S.chat || S.chat.id !== id) return;
			var had = !!WS.data; WS.data = d; WS.detail = {};
			Object.keys(WS.openCh).forEach(function (k) { if (WS.openCh[k]) wsDetail(+k, true); });
			if (!had || d.changes.length) renderBody();
			Object.keys(WS.open).forEach(function (p) { if (WS.open[p]) wsKids(p, true); });
			if (QWS) { WS.openCh[QWS] = true; wsDetail(QWS); QWS = 0; }
			renderWs();
		}).catch(function (e) { WS.err = e.message; renderWs(); });
	}
	function wsPending() {
		var d = WS.data;
		return d ? d.changes.filter(function (c) { return c.kind === 'pr' && c.status === 'open'; }) : [];
	}
	function wsKids(path, force) {
		if (WS.kids[path] && !force) return;
		if (!WS.kids[path]) WS.kids[path] = 'load';
		api('GET', 'chats/' + WS.chat + '/ws/list?path=' + encodeURIComponent(path)).then(function (r) { WS.kids[path] = r.items || []; renderWs(); })
			.catch(function (e) { WS.kids[path] = { err: e.message }; renderWs(); });
	}
	function wsTree(items, depth) {
		if (items === 'load' || !items) return '<div class="ws-empty"><span class="spin"></span></div>';
		if (items.err) return '<div class="ws-empty">' + esc(items.err) + '</div>';
		if (!items.length) return depth ? '<div class="ws-row ws-none" style="--d:' + depth + '">' + esc(t('Boş klasör')) + '</div>' : '';
		return items.map(function (it) {
			var dir = it.type === 'dir', on = dir && WS.open[it.path];
			var row = '<button type="button" class="ws-row' + (dir ? ' dir' : '') + (on ? ' on' : '') + '" style="--d:' + depth + '" data-' + (dir ? 'dir' : 'file') + '="' + esc(it.path) + '">' +
				(dir ? '<span class="car">' + ic('right') + '</span>' + ic('folder') : '<span class="car"></span>' + ic('file')) +
				'<span class="nm">' + esc(it.name) + '</span><small>' + esc(dir ? (it.count != null ? it.count : '') : it.ago) + '</small></button>';
			return row + (on ? wsTree(WS.kids[it.path], depth + 1) : '');
		}).join('');
	}
	/* Satır farkı: ortak baş/son kırpılır, ortası LCS (büyükse tamamı silindi/eklendi). */
	function diffLines(a, b) {
		var A = a == null ? [] : String(a).split('\n'), B = b == null ? [] : String(b).split('\n');
		if (a == null) return B.map(function (l) { return ['+', l]; });
		if (b == null) return A.map(function (l) { return ['-', l]; });
		var s = 0, ea = A.length, eb = B.length, out = [], tail = [];
		while (s < ea && s < eb && A[s] === B[s]) { out.push([' ', A[s]]); s++; }
		while (ea > s && eb > s && A[ea - 1] === B[eb - 1]) { tail.unshift([' ', A[ea - 1]]); ea--; eb--; }
		var n = ea - s, m = eb - s;
		if (n * m > 4000000) {
			for (var i = s; i < ea; i++) out.push(['-', A[i]]);
			for (var j = s; j < eb; j++) out.push(['+', B[j]]);
			return out.concat(tail);
		}
		var L = [], x, y;
		for (x = n; x >= 0; x--) { L[x] = new Uint32Array(m + 1); }
		for (x = n - 1; x >= 0; x--) for (y = m - 1; y >= 0; y--) L[x][y] = A[s + x] === B[s + y] ? L[x + 1][y + 1] + 1 : Math.max(L[x + 1][y], L[x][y + 1]);
		x = 0; y = 0;
		while (x < n && y < m) {
			if (A[s + x] === B[s + y]) { out.push([' ', A[s + x]]); x++; y++; }
			else if (L[x + 1][y] >= L[x][y + 1]) { out.push(['-', A[s + x]]); x++; }
			else { out.push(['+', B[s + y]]); y++; }
		}
		while (x < n) out.push(['-', A[s + x++]]);
		while (y < m) out.push(['+', B[s + y++]]);
		return out.concat(tail);
	}
	function diffHtml(f) {
		if (f.big) return '<div class="ws-empty">' + esc(t('Dosya fark göstermek için çok büyük.')) + '</div>';
		var d = diffLines(f.before, f.after), keep = [], CTX = 3, i;
		for (i = 0; i < d.length; i++) if (d[i][0] !== ' ') for (var k = Math.max(0, i - CTX); k <= Math.min(d.length - 1, i + CTX); k++) keep[k] = 1;
		var html = '', na = 0, nb = 0, gap = false;
		for (i = 0; i < d.length; i++) {
			var op = d[i][0];
			if (op !== '+') na++;
			if (op !== '-') nb++;
			if (!keep[i]) { gap = true; continue; }
			if (gap && html) html += '<div class="dl gap">⋯</div>';
			gap = false;
			html += '<div class="dl ' + (op === '+' ? 'add' : op === '-' ? 'del' : '') + '"><i>' + (op === '+' ? '' : na) + '</i><i>' + (op === '-' ? '' : nb) + '</i><b>' + esc(op === ' ' ? ' ' : op) + '</b><code>' + esc(d[i][1]) + '</code></div>';
		}
		return '<div class="diff">' + (html || '<div class="ws-empty">' + esc(t('Fark yok.')) + '</div>') + '</div>';
	}
	function chStatus(c) {
		var m = { open: [t('Onay bekliyor'), 'warn'], merged: [t('Birleştirildi'), 'ok'], rejected: [t('Reddedildi'), ''], partial: [t('Kısmen birleştirildi'), 'ok'], done: [t('Kaydedildi'), ''] }[c.status] || [c.status, ''];
		return '<span class="pill ' + m[1] + '">' + esc(m[0]) + '</span>';
	}
	function opLbl(op) { return { add: t('eklendi'), edit: t('değişti'), 'delete': t('silindi') }[op] || op; }
	function wsChange(c) {
		var add = 0, del = 0, open = WS.openCh[c.id], det = WS.detail[c.id], pr = c.kind === 'pr', live = pr && c.status === 'open';
		c.files.forEach(function (f) { add += f.add; del += f.del; });
		var h = '<article class="ch' + (live ? ' is-open' : '') + '"><button type="button" class="ch-h" data-ch="' + c.id + '">' + ic(pr ? 'pr' : 'commit') +
			'<span class="ch-t"><b>' + esc(c.title || (pr ? t('Değişiklik isteği') : t('Değişiklik'))) + '</b><small>' + esc((pr ? '#' + c.id + ' · ' : '') + tf('%s dosya', c.files.length) + ' · ' + c.ago) +
			' <span class="n add">+' + add + '</span> <span class="n del">−' + del + '</span></small></span>' + chStatus(c) + '</button>';
		if (live) h += '<div class="ch-acts"><button type="button" class="btn primary" data-merge="' + c.id + '">' + ic('check') + esc(t('Birleştir')) + '</button><button type="button" class="btn" data-reject="' + c.id + '">' + esc(t('Reddet')) + '</button></div>';
		if (open) {
			h += '<div class="ch-files">';
			if (!det) h += '<div class="ws-empty"><span class="spin"></span></div>';
			else if (det.err) h += '<div class="ws-empty">' + esc(det.err) + '</div>';
			else det.files.forEach(function (f) {
				h += '<div class="cf"><div class="cf-h"><span class="op ' + f.op + '">' + esc(opLbl(f.op)) + '</span><code title="' + esc(f.path) + '"><bdi>' + esc(f.path) + '</bdi></code>' +
					(f.state ? '<span class="pill ' + (f.state === 'merged' ? 'ok' : '') + '">' + esc(f.state === 'merged' ? t('Birleştirildi') : t('Reddedildi')) + '</span>' : '') +
					(live && !f.state ? '<span class="cf-a"><button type="button" class="lnk" data-merge="' + c.id + '" data-path="' + esc(f.path) + '">' + esc(t('Bunu birleştir')) + '</button><button type="button" class="lnk" data-reject="' + c.id + '" data-path="' + esc(f.path) + '">' + esc(t('Reddet')) + '</button></span>' : '') +
					'</div>' + diffHtml(f) + '</div>';
			});
			h += '</div>';
		}
		return h + '</article>';
	}
	function renderWs() {
		if (!els.ws) return;
		var pend = wsPending().length;
		if (els.wsBtn) els.wsBtn.classList.toggle('dot', pend > 0);
		if (els.ws.hidden) return;
		var d = WS.data, tab = ui.wsTab || 'files';
		var h = '<div class="ws-head"><div class="ws-tabs" role="tablist">' +
			['files', 'changes', 'settings'].map(function (k) {
				var lbl = { files: t('Dosyalar'), changes: t('Değişiklikler'), settings: t('Ayarlar') }[k];
				return '<button type="button" role="tab" data-wt="' + k + '" class="' + (tab === k ? 'on' : '') + '">' + esc(lbl) + (k === 'changes' && pend ? ' <b>' + pend + '</b>' : '') + '</button>';
			}).join('') + '</div>' +
			'<button type="button" class="ib" data-wa="refresh" title="' + esc(t('Yenile')) + '">' + ic('retry') + '</button>' +
			'<button type="button" class="ib" data-wa="close" title="' + esc(t('Kapat')) + '">' + ic('x') + '</button></div><div class="ws-body">';
		if (WS.note) h += '<div class="ws-note">' + esc(WS.note) + '</div>';
		if (!S.chat) h += '<div class="ws-empty big">' + ic('folder') + '<p>' + esc(t('Sohbet başlayınca bu sohbetin klasörü ve asistanın değişiklikleri burada görünür.')) + '</p></div>';
		else if (WS.err && !d) h += '<div class="ws-empty">' + esc(WS.err) + '</div>';
		else if (!d) h += '<div class="ws-empty"><span class="spin"></span></div>';
		else if (!d.ready) h += '<div class="ws-empty big">' + ic('folder') + '<p>' + esc(t('Çalışma alanı için Flux Files eklentisi gerekli. FluxWP Panel › Paketler\'den kurabilirsiniz.')) + '</p></div>';
		else if (tab === 'files') {
			if (WS.view) {
				var v = WS.view;
				h += '<div class="ws-path"><button type="button" class="ib" data-wa="back" title="' + esc(t('Geri')) + '">' + ic('back') + '</button><code>' + esc(v.path) + '</code>' +
					(v.files ? '<a class="ib" href="' + esc(v.files) + '" target="_blank" rel="noopener" title="' + esc(t('Flux Files\'ta aç')) + '">' + ic('ext') + '</a>' : '') + '</div>';
				h += v.err ? '<div class="ws-empty">' + esc(v.err) + '</div>' : v.content == null ? '<div class="ws-empty"><span class="spin"></span></div>'
					: '<div class="diff view">' + String(v.content).split('\n').map(function (l, i) { return '<div class="dl"><i>' + (i + 1) + '</i><code>' + esc(l) + '</code></div>'; }).join('') + '</div>';
			} else {
				h += '<div class="ws-path">' + ic('folder') + '<code title="' + esc(d.dir) + '">' + esc(d.dir) + '</code>' +
					(d.files ? '<a class="ib" href="' + esc(d.files) + '" target="_blank" rel="noopener" title="' + esc(t('Flux Files\'ta aç')) + '">' + ic('ext') + '</a>' : '') + '</div>';
				h += d.items && d.items.length ? '<div class="ws-tree">' + wsTree(d.items, 0) + '</div>'
					: '<div class="ws-empty">' + esc(t('Klasör henüz boş. Asistan not, rapor ya da taslak yazınca burada görünür.')) + '</div>';
				if (d.access === 'workspace' && d.folders.length) {
					h += '<div class="ws-sub">' + esc(t('İzin verilen klasörler')) + '</div><div class="ws-tree">' + d.folders.map(function (f) {
						var on = WS.open[f];
						return '<button type="button" class="ws-row dir' + (on ? ' on' : '') + '" style="--d:0" data-dir="' + esc(f) + '"><span class="car">' + ic('right') + '</span>' + ic('folder') + '<span class="nm">' + esc(f) + '</span></button>' + (on ? wsTree(WS.kids[f], 1) : '');
					}).join('') + '</div>';
				}
			}
		} else if (tab === 'changes') {
			h += d.changes.length ? d.changes.map(wsChange).join('') : '<div class="ws-empty big">' + ic('commit') + '<p>' + esc(t('Henüz değişiklik yok. Asistanın kendi klasörüne yazdıkları commit, sitedeki dosyalara önerdikleri değişiklik isteği olarak burada görünür.')) + '</p></div>';
		} else {
			var task = d.task > 0;
			h += '<form class="ws-set"><p class="ws-sub">' + esc(task ? t('Bu görevin dosya erişimi') : t('Bu sohbetin dosya erişimi')) + '</p>' +
				'<label class="opt"><input type="radio" name="access" value="workspace"' + (d.access === 'workspace' ? ' checked' : '') + '><span><b>' + esc(t('Yalnız kendi klasörü')) + '</b><small>' + esc(t('Asistan yalnız bu sohbetin klasörünü ve aşağıda izin verdiğiniz klasörleri görür.')) + '</small></span></label>' +
				'<label class="opt"><input type="radio" name="access" value="site"' + (d.access === 'site' ? ' checked' : '') + '><span><b>' + esc(t('Tüm site okunabilir')) + '</b><small>' + esc(t('Asistan sitenin dosyalarını okuyabilir; değiştirmek istediği her dosya değişiklik isteği olarak onayınıza gelir.')) + '</small></span></label>' +
				'<label class="fl"><b>' + esc(t('İzin verilen klasörler')) + '</b><textarea name="folders" rows="3" placeholder="wp-content/themes/fluxwp">' + esc(d.folders.join('\n')) + '</textarea><small>' + esc(t('Her satıra bir klasör (site köküne göre). Bu klasörlerde de değişiklikler onayınıza gelir.')) + '</small></label>' +
				'<button type="submit" class="btn primary"' + (WS.busy ? ' disabled' : '') + '>' + esc(t('Kaydet')) + '</button></form>' +
				'<div class="ws-rules">' + ic('shield') + '<div><b>' + esc(t('Her zaman geçerli kurallar')) + '</b><ul>' +
				[t('Asistan kendi klasörü dışında hiçbir dosyayı doğrudan değiştiremez.'), t('Başka sohbet ve görevlerin klasörleri kapalıdır; skill dosyaları yalnız okunur.'), t('Şifre ve anahtar içeren dosyalar (wp-config, .env, .sql) ve WordPress çekirdeği hiçbir zaman açılmaz.'), t('Bir turda en fazla 50 dosya, dosya başına en fazla 1 MB yazılır.')].map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') +
				'</ul><p><code>' + esc(d.dir) + '</code></p></div></div>';
		}
		h += '</div>';
		els.ws.innerHTML = h;
	}
	function wsDetail(id, force) {
		if (WS.detail[id] && !force) return;
		api('GET', 'changes/' + id).then(function (r) { WS.detail[id] = r; renderWs(); }).catch(function (e) { WS.detail[id] = { err: e.message }; renderWs(); });
	}
	function wsDecide(id, merge, path) {
		if (WS.busy) return;
		var go = function () {
			WS.busy = true; WS.note = '';
			api('POST', 'changes/' + id + '/' + (merge ? 'merge' : 'reject'), path ? { paths: [path] } : {}).then(function (r) {
				WS.busy = false;
				var n = [];
				if (r.conflicts && r.conflicts.length) n.push(tf('Dosya bu arada değişmiş, uygulanmadı: %s', r.conflicts.join(', ')));
				if (r.errors) Object.keys(r.errors).forEach(function (p) { n.push(p + ': ' + r.errors[p]); });
				WS.note = n.join(' · ');
				WS.openCh[id] = true;
				wsLoad(true);
				if (S.chat && r.change && r.change.status !== 'open') loadChat(S.chat.id, true);
			}).catch(function (e) { WS.busy = false; WS.note = e.message; renderWs(); });
		};
		if (merge || path) { go(); return; }
		if (window.flxDialog && window.flxDialog.confirm) {
			window.flxDialog.confirm({ title: t('Değişiklik isteği reddedilsin mi?'), message: t('Dosyalar değişmez; asistana reddedildiği bildirilir.'), ok: t('Reddet'), danger: true }).then(function (ok) { if (ok) go(); });
		} else go();
	}
	function wsSave() {
		var f = els.ws.querySelector('.ws-set');
		if (!f || !S.chat) return;
		var acc = f.querySelector('input[name=access]:checked');
		WS.busy = true; renderWs();
		api('POST', 'chats/' + S.chat.id + '/ws', { access: acc ? acc.value : 'workspace', folders: f.querySelector('textarea').value })
			.then(function (d) { WS.busy = false; WS.data = d; WS.note = t('Kaydedildi.'); renderWs(); setTimeout(function () { WS.note = ''; renderWs(); }, 2500); })
			.catch(function (e) { WS.busy = false; WS.note = e.message; renderWs(); });
	}
	function onWs(e) {
		var b;
		if ((b = e.target.closest('[data-wt]'))) { ui.wsTab = b.getAttribute('data-wt'); saveUi(); WS.note = ''; renderWs(); return; }
		if ((b = e.target.closest('[data-wa]'))) {
			var a = b.getAttribute('data-wa');
			if (a === 'close') { ui.ws = false; saveUi(); layout(); }
			else if (a === 'refresh') wsLoad(true);
			else if (a === 'back') { WS.view = null; renderWs(); }
			return;
		}
		if ((b = e.target.closest('[data-dir]'))) {
			var p = b.getAttribute('data-dir');
			WS.open[p] = !WS.open[p];
			if (WS.open[p]) wsKids(p);
			renderWs(); return;
		}
		if ((b = e.target.closest('[data-file]'))) {
			var fp = b.getAttribute('data-file');
			WS.view = { path: fp, content: null };
			renderWs();
			api('GET', 'chats/' + WS.chat + '/ws/file?path=' + encodeURIComponent(fp)).then(function (r) {
				if (WS.view && WS.view.path === fp) { WS.view = { path: fp, content: r.content, files: r.files }; renderWs(); }
			}).catch(function (er) { if (WS.view && WS.view.path === fp) { WS.view.err = er.message; renderWs(); } });
			return;
		}
		if ((b = e.target.closest('[data-merge]'))) { wsDecide(+b.getAttribute('data-merge'), true, b.getAttribute('data-path')); return; }
		if ((b = e.target.closest('[data-reject]'))) { wsDecide(+b.getAttribute('data-reject'), false, b.getAttribute('data-path')); return; }
		if ((b = e.target.closest('[data-ch]'))) {
			var id = +b.getAttribute('data-ch');
			WS.openCh[id] = !WS.openCh[id];
			if (WS.openCh[id]) wsDetail(id);
			renderWs();
		}
	}
	/* Sohbetteki değişiklik bağlantısı (araç sonucu) çalışma alanını açar. */
	function showChanges(id) {
		ui.ws = true; ui.wsTab = 'changes'; if (wide() && !C.page) ui.page = true; saveUi(); layout();
		if (id) { WS.openCh[id] = true; wsDetail(id); }
		wsLoad(true);
	}

	/* ---------- Dış API: Genel bakıştaki sohbet kutusu gibi yerlerden yeni sohbet başlatır ---------- */
	window.flxaiChat = {
		/* Yeni sohbet açar, metni ve dosyaları gönderir; panel açılır, konuşma orada sürer. */
		start: function (text, files) {
			if (!els.ta) start();
			if (!els.ta) return false;
			if (S.running) { open(); return false; }
			S.chat = null; S.msgs = []; S.pending = []; S.err = null; S.optimistic = null; WS.data = null; WS.chat = 0; WS.view = null; renderWs();
			ui.chat = 0; saveUi();
			els.title.textContent = t('Yeni sohbet');
			S.files = []; addFiles(files || []);
			open();
			send(String(text || '').trim());
			return true;
		},
		dictate: function (ta, btn, onChange) { dictate(ta, btn, onChange); },
		canDictate: !!Rec
	};

	/* ---------- Başlat: sayfa yüklendikten sonra, boşta ---------- */
	function start() {
		if (!document.body || els.ta) return;
		mount();
	}
	function later() { (window.requestIdleCallback || function (f) { setTimeout(f, 200); })(start, { timeout: 2000 }); }
	var earlyHost = document.getElementById('flxai-host');
	if (earlyHost && earlyHost.getAttribute('data-early')) start();
	else if (document.readyState === 'complete') later(); else window.addEventListener('load', later);
})();
