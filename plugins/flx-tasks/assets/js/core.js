/* Flux Tasks — çekirdek: çeviri, API, DOM yardımcıları, biçimlendirme, açılır pencereler, sürükle-bırak. */
(function () {
	'use strict';
	var B = window.FLXG_BOOT || {};
	var Y = (window.FLXG = window.FLXG || {});
	Y.B = B;

	/* ---------------- Çeviri ---------------- */
	var I18N = B.i18n || {};
	Y.t = function (s, vars) {
		var out = Object.prototype.hasOwnProperty.call(I18N, s) && I18N[s] ? I18N[s] : s;
		if (vars) {
			Object.keys(vars).forEach(function (k) {
				out = out.split('{' + k + '}').join(vars[k]);
			});
		}
		return out;
	};
	var t = Y.t;

	/* ---------------- DOM ---------------- */
	function h(tag, attrs) {
		var el;
		var m = /^([a-z0-9]+)?((?:[.#][\w-]+)*)$/i.exec(tag) || [];
		el = document.createElement(m[1] || 'div');
		(m[2] || '').replace(/([.#])([\w-]+)/g, function (_, k, v) {
			if (k === '.') el.classList.add(v);
			else el.id = v;
		});
		var start = 1;
		if (attrs && typeof attrs === 'object' && !(attrs instanceof Node) && !Array.isArray(attrs)) {
			start = 2;
			// HTML yalnız açık 'html' anahtarından girer; diğer öznitelik değerleri hiçbir zaman HTML olarak yorumlanmaz.
			if (attrs.html !== null && attrs.html !== undefined && attrs.html !== false) el.innerHTML = attrs.html;
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) return;
				if (k === 'class') {
					String(v).split(/\s+/).filter(Boolean).forEach(function (c) { el.classList.add(c); });
				} else if (k === 'style' && typeof v === 'object') {
					Object.keys(v).forEach(function (p) {
						if (p.indexOf('--') === 0) el.style.setProperty(p, v[p]);
						else el.style[p] = v[p];
					});
				} else if (k.indexOf('on') === 0 && typeof v === 'function') {
					el.addEventListener(k.slice(2).toLowerCase(), v);
				} else if (k === 'html') {
					return;
				} else if (k === 'text') {
					el.textContent = v;
				} else if (k === 'dataset') {
					Object.keys(v).forEach(function (d) { el.dataset[d] = v[d]; });
				} else if (v === true) {
					el.setAttribute(k, '');
				} else {
					el.setAttribute(k, v);
				}
			});
		}
		for (var i = start; i < arguments.length; i++) add(el, arguments[i]);
		return el;
	}
	function add(el, c) {
		if (c === null || c === undefined || c === false) return;
		if (Array.isArray(c)) return c.forEach(function (x) { add(el, x); });
		if (c instanceof Node) el.appendChild(c);
		else el.appendChild(document.createTextNode(String(c)));
	}
	Y.h = h;
	Y.clear = function (el) {
		while (el && el.firstChild) el.removeChild(el.firstChild);
		return el;
	};
	Y.$ = function (s, root) { return (root || document).querySelector(s); };
	Y.$$ = function (s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); };

	/* Simgeler (Lucide tarzı, satır içi SVG). */
	var IC = {
		check: 'M20 6 9 17l-5-5',
		alert: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
		x: 'M18 6 6 18M6 6l12 12',
		plus: 'M12 5v14M5 12h14',
		search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35',
		filter: 'M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z',
		list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
		board: 'M4 4h5v16H4zM10 4h5v10h-5zM16 4h4v13h-4z',
		calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
		bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
		home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
		user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
		users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
		flag: 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7',
		tag: 'M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4zM7.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z',
		clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
		msg: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
		clip: 'm21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48',
		image: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM21 15l-5-5L5 21',
		file: 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2zM14 2v6h6',
		dots: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
		chevD: 'm6 9 6 6 6-6',
		chevR: 'm9 18 6-6-6-6',
		chevL: 'm15 18-6-6 6-6',
		folder: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
		settings: 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
		chart: 'M3 3v18h18M18 17V9M13 17V5M8 17v-3',
		sparkle: 'M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0zM20 3v4M22 5h-4',
		trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
		edit: 'M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z',
		link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
		eye: 'M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
		eyeOff: 'M9.88 9.88a3 3 0 1 0 4.24 4.24M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20',
		menu: 'M4 6h16M4 12h16M4 18h16',
		sidebar: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 3v18',
		sort: 'M3 6h18M7 12h10M10 18h4',
		group: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
		columns: 'M12 3v18M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
		send: 'm22 2-7 20-4-9-9-4zM22 2 11 13',
		star: 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z',
		sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41',
		moon: 'M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z',
		inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
		back: 'M19 12H5M12 19l-7-7 7-7',
		upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
		download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
		subtask: 'M6 3v12a3 3 0 0 0 3 3h9M14 14l4 4-4 4',
		key: 'M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4zM16.5 7.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z',
		copy: 'M8 8h12v12H8zM16 8V4H4v12h4',
		archive: 'M21 8v13H3V8M1 3h22v5H1zM10 12h4',
		move: 'M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20',
		grip: 'M9 6h.01M9 12h.01M9 18h.01M15 6h.01M15 12h.01M15 18h.01',
		lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
		bold: 'M6 12h9a4 4 0 0 1 0 8H6zM6 4h7a4 4 0 0 1 0 8H6z',
		italic: 'M19 4h-9M14 20H5M15 4 9 20',
		ul: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
		ol: 'M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1',
		checklist: 'm3 7 2 2 4-4M3 17l2 2 4-4M13 6h8M13 12h8M13 18h8',
		heading: 'M6 12h12M6 20V4M18 20V4',
		quote: 'M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1zM15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z',
		code: 'm16 18 6-6-6-6M8 6l-6 6 6 6',
		at: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8',
		underline: 'M6 4v6a6 6 0 0 0 12 0V4M4 20h16',
		strike: 'M16 4H9a3 3 0 0 0-2.83 4M14 12a4 4 0 0 1 0 8H6M4 12h16',
		table: 'M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18',
		minus: 'M5 12h14',
		alignL: 'M21 6H3M15 12H3M17 18H3',
		alignC: 'M21 6H3M17 12H7M19 18H5',
		alignR: 'M21 6H3M21 12H9M21 18H7',
		undo: 'M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13',
		redo: 'M21 7v6h-6M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7',
		doc: 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2zM14 2v6h6M16 13H8M16 17H8M10 9H8',
		printer: 'M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z',
		hash: 'M4 9h16M4 15h16M10 3 8 21M16 3l-2 18',
		meeting: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM9 16a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM15 16h2',
		gavel: 'm14 13-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10M16 16l6-6M8 8l6-6M9 7l8 8M21 11l-8-8'
	};
	Y.icon = function (name, size, cls) {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('width', size || 16);
		svg.setAttribute('height', size || 16);
		svg.setAttribute('fill', name === 'star-fill' ? 'currentColor' : 'none');
		svg.setAttribute('stroke', 'currentColor');
		svg.setAttribute('stroke-width', '2');
		svg.setAttribute('stroke-linecap', 'round');
		svg.setAttribute('stroke-linejoin', 'round');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('class', 'flxg-ic' + (cls ? ' ' + cls : ''));
		var p = document.createElementNS(ns, 'path');
		p.setAttribute('d', IC[name === 'star-fill' ? 'star' : name] || IC.dots);
		svg.appendChild(p);
		return svg;
	};

	/* ---------------- Biçimlendirme ---------------- */
	var LOC = B.locale || 'tr-TR';
	Y.locale = LOC;
	var nf = new Intl.NumberFormat(LOC);
	Y.num = function (n) { return nf.format(n || 0); };
	Y.money = function (n, cur) {
		try {
			return new Intl.NumberFormat(LOC, { style: 'currency', currency: cur || 'TRY', maximumFractionDigits: 2 }).format(n);
		} catch (e) {
			return nf.format(n) + ' ' + (cur || '');
		}
	};
	Y.bytes = function (b) {
		if (b === null || b === undefined) return '—';
		var u = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = 0;
		b = Number(b);
		while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
		return new Intl.NumberFormat(LOC, { maximumFractionDigits: i > 1 ? 1 : 0 }).format(b) + ' ' + u[i];
	};
	/* Görev tarihi: "YYYY-MM-DD" veya "YYYY-MM-DDTHH:MM" (site saati). */
	Y.parseDay = function (s) {
		if (!s) return null;
		var m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(s);
		if (!m) return null;
		return new Date(+m[1], +m[2] - 1, +m[3], m[4] ? +m[4] : 0, m[5] ? +m[5] : 0);
	};
	Y.dayKey = function (d) {
		return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	};
	Y.today = function () {
		return (Y.S && Y.S.config && Y.S.config.today && Y.S.todayFixed) ? Y.S.config.today : Y.dayKey(new Date());
	};
	Y.addDays = function (key, n) {
		var d = Y.parseDay(key);
		d.setDate(d.getDate() + n);
		return Y.dayKey(d);
	};
	Y.fmtDue = function (s, opts) {
		var d = Y.parseDay(s);
		if (!d) return '';
		var key = s.slice(0, 10);
		var today = Y.dayKey(new Date());
		var time = s.length > 10 ? ' ' + d.toLocaleTimeString(LOC, { hour: '2-digit', minute: '2-digit' }) : '';
		if (!(opts && opts.full)) {
			if (key === today) return t('Bugün') + time;
			if (key === Y.addDays(today, 1)) return t('Yarın') + time;
			if (key === Y.addDays(today, -1)) return t('Dün') + time;
		}
		var sameYear = d.getFullYear() === new Date().getFullYear();
		return d.toLocaleDateString(LOC, sameYear && !(opts && opts.full) ? { day: 'numeric', month: 'short' } : { day: 'numeric', month: 'short', year: 'numeric' }) + time;
	};
	Y.dueState = function (s, done) {
		if (!s || done) return '';
		var key = s.slice(0, 10);
		var today = Y.dayKey(new Date());
		if (key < today) return 'overdue';
		if (key === today) return 'today';
		if (key === Y.addDays(today, 1)) return 'soon';
		return '';
	};
	var rtf = window.Intl && Intl.RelativeTimeFormat ? new Intl.RelativeTimeFormat(LOC, { numeric: 'auto' }) : null;
	Y.ago = function (iso) {
		if (!iso) return '';
		var d = new Date(iso);
		var diff = (d.getTime() - Date.now()) / 1000;
		var a = Math.abs(diff);
		if (!rtf) return d.toLocaleString(LOC);
		if (a < 45) return t('az önce');
		if (a < 3600) return rtf.format(Math.round(diff / 60), 'minute');
		if (a < 86400) return rtf.format(Math.round(diff / 3600), 'hour');
		if (a < 86400 * 7) return rtf.format(Math.round(diff / 86400), 'day');
		return d.toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: d.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
	};
	Y.fullTime = function (iso) {
		return iso ? new Date(iso).toLocaleString(LOC, { dateStyle: 'medium', timeStyle: 'short' }) : '';
	};
	Y.monthName = function (y, m) {
		return new Date(y, m, 1).toLocaleDateString(LOC, { month: 'long', year: 'numeric' });
	};
	Y.weekdayNames = function (start) {
		var out = [];
		// 2023-01-01 Pazar.
		for (var i = 0; i < 7; i++) {
			out.push(new Date(2023, 0, 1 + ((start + i) % 7)).toLocaleDateString(LOC, { weekday: 'short' }));
		}
		return out;
	};
	Y.cmpStr = function (a, b) { return String(a || '').localeCompare(String(b || ''), LOC, { sensitivity: 'base' }); };
	Y.norm = function (s) { return String(s || '').toLocaleLowerCase(LOC).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i'); };

	/* ---------------- API ---------------- */
	/** Tek deneme. Y.api bunu iş takibi ve yoğunlukta yeniden deneme ile sarar. */
	function request(method, path, body, opts) {
		opts = opts || {};
		var url = B.rest + path.replace(/^\//, '');
		// Sunucu metinleri (öncelik adları, hatalar) kullanıcının dilinde gelsin.
		url += (url.indexOf('?') > -1 ? '&' : '?') + '_locale=user';
		var headers = { 'X-WP-Nonce': B.nonce, Accept: 'application/json' };
		var m = method.toUpperCase();
		// no-store + _t: sunucu/CDN önbelleği eski görev verisi döndürmesin.
		var init = { method: m === 'GET' ? 'GET' : 'POST', headers: headers, credentials: 'same-origin', cache: 'no-store' };
		if (m === 'GET') url += '&_t=' + Date.now();
		// Güncellemeler düz POST; yalnız silme için yöntem başlığı (bazı güvenlik duvarları DELETE'i engelliyor).
		if (m === 'DELETE') headers['X-HTTP-Method-Override'] = m;
		if (m === 'GET' && body) {
			var q = Object.keys(body).filter(function (k) { return body[k] !== undefined && body[k] !== null && body[k] !== ''; })
				.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]); }).join('&');
			if (q) url += (url.indexOf('?') > -1 ? '&' : '?') + q;
		} else if (body instanceof FormData) {
			init.body = body;
		} else if (body !== undefined) {
			headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(body);
		}
		if (opts.signal) init.signal = opts.signal;
		return fetch(url, init).then(function (r) {
			return r.text().then(function (txt) {
				var data = null;
				try { data = txt ? JSON.parse(txt) : null; } catch (e) { data = null; }
				// 200 ama JSON değil: güvenlik duvarı/önbellek sayfası; başarı sayılmaz.
				if (r.ok && txt && data === null) {
					var bad = new Error(t('Sunucu beklenmeyen bir yanıt verdi. Sayfayı yenileyip tekrar deneyin.'));
					bad.status = r.status;
					throw bad;
				}
				if (!r.ok) {
					var err = new Error((data && data.message) || (r.status === 429 || r.status === 503 ? t('Sunucu şu an yoğun, biraz sonra yeniden deneyin.') : t('Bağlantı hatası')) + (data && data.message ? '' : ' (' + r.status + ')'));
					err.status = r.status;
					err.data = data;
					throw err;
				}
				return data;
			});
		});
	}
	Y.api = function (method, path, body, opts) {
		opts = opts || {};
		var m = method.toUpperCase();
		if (m === 'GET' || opts.quiet) return request(method, path, body, opts);
		// Yazma işlemleri arka planda sürer: sol alttaki göstergede görünür, çekmece kapansa da devam eder.
		// Sunucu yoğun (429/503) derse istek işlenmemiştir; biraz bekleyip yeniden denenir.
		var job = Y.job(opts.label || jobLabel(m, path));
		var tries = 0;
		function attempt() {
			return request(method, path, body, opts).catch(function (e) {
				if ((e.status === 429 || e.status === 503) && tries < 3) {
					tries++;
					job.wait();
					return new Promise(function (res) { setTimeout(res, [4000, 10000, 20000][tries - 1]); }).then(function () { job.run(); return attempt(); });
				}
				throw e;
			});
		}
		return attempt().then(function (d) { job.done(); return d; }, function (e) {
			job.fail(e, function () { return Y.api(method, path, body, opts); });
			throw e;
		});
	};
	function jobLabel(m, path) {
		if (/comments/.test(path)) return m === 'DELETE' ? t('Yorum siliniyor') : t('Yorum gönderiliyor');
		if (/^files/.test(path)) return m === 'DELETE' ? t('Dosya siliniyor') : t('Dosya kaydediliyor');
		if (/^tasks/.test(path)) return m === 'DELETE' ? t('Görev siliniyor') : t('Görev kaydediliyor');
		if (/^ai/.test(path)) return t('Yapay zekâ çalışıyor');
		return t('Kaydediliyor');
	}

	/* ---------------- İskelet (skeleton) yükleme ----------------
	 * Dönen döner yerine içeriğin şeklini gösterir; her sayfada aynı dil.
	 */
	function sk(w, hgt, extra) { return h('span.flxg-sk' + (extra || ''), { style: { width: w, height: (hgt || 12) + 'px' } }); }
	Y.sk = sk;
	Y.skeleton = function (kind, opts) {
		opts = opts || {};
		var i;
		var box = h('.flxg-skel.is-' + kind, { 'aria-busy': 'true', 'aria-label': t('Yükleniyor') });
		if (kind === 'task') {
			var main = h('.flxg-skel-main');
			main.appendChild(opts.title ? h('.flxg-skel-title', opts.title) : sk('62%', 26, '.is-lg'));
			main.appendChild(h('.flxg-skel-row', sk('92px', 28, '.is-pill'), sk('96px', 28, '.is-pill')));
			var grid = h('.flxg-skel-grid');
			for (i = 0; i < 6; i++) grid.appendChild(h('.flxg-skel-attr', sk('70px', 12), sk((40 + (i * 17) % 45) + '%', 12)));
			main.appendChild(grid);
			main.appendChild(sk('90px', 14, '.is-h'));
			['96%', '88%', '72%', '80%'].forEach(function (w) { main.appendChild(sk(w, 12)); });
			main.appendChild(sk('90px', 14, '.is-h'));
			main.appendChild(sk('100%', 40, '.is-box'));
			var side = h('.flxg-skel-side');
			side.appendChild(sk('120px', 14, '.is-h'));
			for (i = 0; i < 4; i++) side.appendChild(h('.flxg-skel-cmt', sk('28px', 28, '.is-av'), h('.flxg-skel-lines', sk('40%', 11), sk(['90%', '70%', '84%', '60%'][i], 11))));
			box.appendChild(main);
			box.appendChild(side);
		} else if (kind === 'list') {
			box.appendChild(h('.flxg-skel-bar', sk('220px', 32, '.is-box'), sk('80px', 32, '.is-box'), sk('80px', 32, '.is-box')));
			for (i = 0; i < (opts.rows || 8); i++) box.appendChild(h('.flxg-skel-line', sk('16px', 16, '.is-av'), sk((35 + (i * 23) % 40) + '%', 12), h('span.flxg-grow'), sk('24px', 24, '.is-av'), sk('64px', 12)));
		} else if (kind === 'kpi') {
			var row = h('.flxg-skel-kpis');
			for (i = 0; i < 4; i++) row.appendChild(h('.flxg-skel-card', sk('50%', 11), sk('60%', 24)));
			box.appendChild(row);
			box.appendChild(h('.flxg-skel-card.is-wide', sk('30%', 14), sk('100%', 140, '.is-box')));
		} else {
			for (i = 0; i < (opts.rows || 3); i++) box.appendChild(h('.flxg-skel-card.is-wide', sk('30%', 14), sk('100%', 36, '.is-box'), sk('70%', 12)));
		}
		return box;
	};

	/* ---------------- Arka plan işleri (sol alt gösterge) ---------------- */
	var jobs = [];
	var jobSeq = 0;
	var jobEl = null;
	var doneTimer = null;
	var batch = { total: 0, done: 0 };
	Y.job = function (label) {
		var j = { id: ++jobSeq, label: label, state: 'run', error: '', retry: null, progress: 0 };
		if (!jobs.some(function (x) { return x.state !== 'error'; })) batch = { total: 0, done: 0 };
		batch.total++;
		jobs.push(j);
		drawJobs();
		j.run = function () { j.state = 'run'; drawJobs(); };
		j.wait = function () { j.state = 'wait'; drawJobs(); };
		j.step = function (p) { j.progress = Math.max(0, Math.min(1, p)); drawJobs(); };
		j.done = function () { jobs = jobs.filter(function (x) { return x !== j; }); batch.done++; drawJobs(true); };
		j.fail = function (e, retry) { j.state = 'error'; j.error = e && e.message ? e.message : String(e); j.retry = retry || null; batch.done++; drawJobs(); };
		return j;
	};
	/* Sol altta küçük halka: dolan kısım bu turdaki işlerin ilerlemesi, ortada süren iş sayısı. */
	var R_ = 9;
	var C_ = 2 * Math.PI * R_;
	function drawJobs(justDone) {
		var app = document.getElementById('flxg-app');
		if (!app) return;
		if (!jobEl) {
			jobEl = h('button.flxg-jobs', { type: 'button', onclick: function (e) { openJobs(e.currentTarget); } });
			jobEl.innerHTML = '<svg class="flxg-gauge" viewBox="0 0 24 24" aria-hidden="true"><circle class="flxg-gauge-bg" cx="12" cy="12" r="' + R_ + '"/><circle class="flxg-gauge-fg" cx="12" cy="12" r="' + R_ + '" stroke-dasharray="' + C_.toFixed(2) + '" stroke-dashoffset="' + C_.toFixed(2) + '"/></svg><b class="flxg-gauge-n"></b>';
			app.appendChild(jobEl);
		}
		var run = jobs.filter(function (x) { return x.state !== 'error'; });
		var bad = jobs.filter(function (x) { return x.state === 'error'; });
		var fg = jobEl.querySelector('.flxg-gauge-fg');
		var n = jobEl.querySelector('.flxg-gauge-n');
		clearTimeout(doneTimer);
		jobEl.classList.remove('is-done', 'is-error', 'is-wait');
		var frac = batch.total ? (batch.done + run.reduce(function (a, x) { return a + x.progress * 0.9; }, 0)) / batch.total : 0;
		if (bad.length) {
			jobEl.classList.add('is-on', 'is-error');
			frac = 1;
			n.textContent = '!';
		} else if (run.length) {
			jobEl.classList.add('is-on');
			if (run.some(function (x) { return x.state === 'wait'; })) jobEl.classList.add('is-wait');
			// Hiç ilerleme yokken de halka biraz dolu görünsün (çalıştığı belli olsun).
			frac = Math.max(0.08, frac);
			n.textContent = run.length > 1 ? Y.num(run.length) : '';
		} else if (justDone) {
			jobEl.classList.add('is-on', 'is-done');
			frac = 1;
			n.textContent = '';
			doneTimer = setTimeout(function () { jobEl.classList.remove('is-on'); }, 1200);
		} else {
			jobEl.classList.remove('is-on');
		}
		fg.setAttribute('stroke-dashoffset', (C_ * (1 - frac)).toFixed(2));
		var tip = bad.length ? t('{n} işlem başarısız', { n: Y.num(bad.length) }) : (run.length ? (run.length === 1 ? run[0].label : t('{n} işlem sürüyor', { n: Y.num(run.length) })) + (jobEl.classList.contains('is-wait') ? ' · ' + t('sunucu yoğun, bekleniyor') : '') : t('Kaydedildi'));
		jobEl.title = tip;
		jobEl.setAttribute('aria-label', tip);
	}
	function openJobs(anchor) {
		if (!jobs.length) return;
		var box = h('.flxg-pick.flxg-jobs-list', h('.flxg-menu-head', t('Arka plan işleri')));
		jobs.forEach(function (j) {
			box.appendChild(h('.flxg-job' + (j.state === 'error' ? '.is-error' : ''),
				j.state === 'error' ? Y.icon('alert', 15) : h('span.flxg-spin.is-xs'),
				h('.flxg-job-txt', h('b', j.label), j.error ? h('span.flxg-muted', j.error) : (j.state === 'wait' ? h('span.flxg-muted', t('Sunucu yoğun, bekleniyor')) : null)),
				j.state === 'error' && j.retry ? h('button.flxg-btn.is-sm', { type: 'button', onclick: function () {
					jobs = jobs.filter(function (x) { return x !== j; });
					Y.closePop();
					drawJobs();
					j.retry().then(function () { Y.emit('job-retried'); }, function () {});
				} }, t('Tekrar dene')) : null,
				j.state === 'error' ? h('button.flxg-icon-btn.is-sm', { type: 'button', 'aria-label': t('Kapat'), onclick: function () {
					jobs = jobs.filter(function (x) { return x !== j; });
					Y.closePop();
					drawJobs();
				} }, Y.icon('x', 14)) : null
			));
		});
		Y.pop(anchor, box);
	}
	Y.busy = function () { return jobs.some(function (x) { return x.state !== 'error'; }); };
	window.addEventListener('beforeunload', function (e) {
		if (!Y.busy()) return;
		e.preventDefault();
		e.returnValue = '';
	});
	/* Dosya yükleme (ilerleme çubuğu için XHR). */
	Y.upload = function (file, params, onProgress) {
		var job = Y.job(t('Yükleniyor: {n}', { n: file.name || t('Dosya') }));
		return upload(file, params, function (p) { job.step(p); if (onProgress) onProgress(p); }).then(function (f) { job.done(); return f; }, function (e) { job.fail(e); throw e; });
	};
	function upload(file, params, onProgress) {
		return new Promise(function (resolve, reject) {
			var fd = new FormData();
			fd.append('file', file, file.name || 'dosya');
			Object.keys(params || {}).forEach(function (k) { fd.append(k, params[k]); });
			var x = new XMLHttpRequest();
			x.open('POST', B.rest + 'files' + (B.rest.indexOf('?') > -1 ? '&' : '?') + '_locale=user');
			x.setRequestHeader('X-WP-Nonce', B.nonce);
			x.setRequestHeader('Accept', 'application/json');
			x.upload.onprogress = function (e) { if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total); };
			x.onload = function () {
				var d = null;
				try { d = JSON.parse(x.responseText); } catch (e) { d = null; }
				if (x.status >= 200 && x.status < 300 && d && d.file) resolve(d.file);
				else reject(new Error((d && d.message) || t('Dosya yüklenemedi.')));
			};
			x.onerror = function () { reject(new Error(t('Bağlantı hatası'))); };
			x.timeout = 180000;
			x.ontimeout = function () { reject(new Error(t('Yükleme zaman aşımına uğradı.'))); };
			x.send(fd);
		});
	}
	Y.fileUrl = function (id, size, download) {
		// Yalnız sayısal dosya kimliği; DOM'dan okunan değer adrese ham girmez.
		var u = B.rest + 'files/' + (parseInt(id, 10) || 0);
		return u + (u.indexOf('?') > -1 ? '&' : '?') + '_wpnonce=' + encodeURIComponent(B.nonce) + (size ? '&size=' + size : '') + (download ? '&download=1' : '');
	};

	/* ---------------- Bildirim balonu ---------------- */
	Y.toast = function (msg, opts) {
		opts = opts || {};
		var box = Y.$('.flxg-toasts');
		if (!box) {
			box = h('.flxg-toasts', { role: 'status', 'aria-live': 'polite' });
			document.body.appendChild(box);
		}
		var el = h('.flxg-toast' + (opts.error ? '.is-error' : ''), h('span', msg));
		if (opts.action) {
			el.appendChild(h('button.flxg-toast-btn', { type: 'button', onclick: function () { opts.action.fn(); el.remove(); } }, opts.action.label));
		}
		box.appendChild(el);
		setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 300); }, opts.ms || (opts.error ? 6000 : 3500));
	};
	Y.fail = function (e) {
		if (e && e.shown) return;
		Y.toast(e && e.message ? e.message : String(e), { error: true });
		if (e && typeof e === 'object') e.shown = true;
	};
	/* Kullanıcıya zaten gösterilmiş hatalar konsolu kirletmesin. */
	window.addEventListener('unhandledrejection', function (ev) {
		if (ev.reason && ev.reason.shown) ev.preventDefault();
	});

	/* ---------------- Açılır menü / popover ---------------- */
	var openPop = null;
	Y.closePop = function () {
		if (openPop) {
			var p = openPop;
			openPop = null;
			p.el.remove();
			if (p.onClose) p.onClose();
		}
	};
	/**
	 * anchor yanında açılan kutu. content: Node. Mobilde alttan çıkan sayfa olur.
	 */
	Y.pop = function (anchor, content, opts) {
		opts = opts || {};
		Y.closePop();
		var el = h('.flxg-pop' + (opts.cls ? '.' + opts.cls : ''), { role: 'dialog' }, content);
		var mobile = window.matchMedia('(max-width: 720px)').matches && !opts.inline;
		if (mobile) el.classList.add('is-sheet');
		document.body.appendChild(el);
		var root = Y.$('#flxg-app');
		if (root && root.dataset.theme) el.dataset.theme = root.dataset.theme;
		if (mobile) {
			var shade = h('.flxg-sheet-shade', { onclick: Y.closePop });
			document.body.appendChild(shade);
			var oldOnClose = opts.onClose;
			opts.onClose = function () { shade.remove(); if (oldOnClose) oldOnClose(); };
		} else {
			var r = anchor.getBoundingClientRect();
			var w = el.offsetWidth;
			var hh = el.offsetHeight;
			var left = opts.alignRight ? r.right - w : r.left;
			left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
			var top = r.bottom + 4;
			if (top + hh > window.innerHeight - 8) top = Math.max(8, r.top - hh - 4);
			el.style.left = left + 'px';
			el.style.top = top + 'px';
		}
		openPop = { el: el, anchor: anchor, onClose: opts.onClose };
		var first = el.querySelector('input:not([type=checkbox]):not([type=hidden]),textarea');
		if (first && !opts.noFocus && !mobile) setTimeout(function () { first.focus(); }, 10);
		return el;
	};
	document.addEventListener('mousedown', function (e) {
		if (openPop && !openPop.el.contains(e.target) && !(openPop.anchor && openPop.anchor.contains(e.target)) && !e.target.closest('.flxg-sheet-shade')) Y.closePop();
	}, true);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && openPop) { Y.closePop(); e.stopPropagation(); }
	}, true);
	Y.isPopOpen = function () { return !!openPop; };

	/** Basit menü: items = [{label, icon, onClick, danger, checked, sep}] */
	Y.menu = function (anchor, items, opts) {
		var list = h('.flxg-menu');
		items.forEach(function (it) {
			if (!it) return;
			if (it.sep) { list.appendChild(h('.flxg-menu-sep')); return; }
			if (it.head) { list.appendChild(h('.flxg-menu-head', it.head)); return; }
			list.appendChild(h('button.flxg-menu-item' + (it.danger ? '.is-danger' : '') + (it.checked ? '.is-on' : ''), {
				type: 'button',
				disabled: it.disabled,
				onclick: function () { Y.closePop(); it.onClick && it.onClick(); }
			}, it.swatch ? h('span.flxg-swatch', { style: { background: it.swatch } }) : (it.icon ? Y.icon(it.icon) : null), h('span', it.label), it.checked ? Y.icon('check', 14, 'flxg-menu-check') : null));
		});
		return Y.pop(anchor, list, opts);
	};

	/* ---------------- Modal ---------------- */
	Y.modal = function (title, body, opts) {
		opts = opts || {};
		Y.closePop();
		var shade = h('.flxg-modal-shade');
		var close = function () {
			shade.remove();
			document.removeEventListener('keydown', onKey, true);
			if (opts.onClose) opts.onClose();
		};
		var onKey = function (e) {
			if (e.key === 'Escape' && !Y.isPopOpen()) { e.stopPropagation(); close(); }
		};
		var foot = opts.actions ? h('.flxg-modal-foot', opts.actions.map(function (a) {
			return h('button.flxg-btn' + (a.primary ? '.is-primary' : '') + (a.danger ? '.is-danger' : ''), {
				type: 'button',
				onclick: function (e) {
					if (a.onClick) {
						var r = a.onClick(e);
						if (r && r.then) {
							e.currentTarget.disabled = true;
							var btn = e.currentTarget;
							r.then(function (ok) { if (ok !== false) close(); else btn.disabled = false; }, function (er) { btn.disabled = false; Y.fail(er); });
							return;
						}
						if (r === false) return;
					}
					close();
				}
			}, a.label);
		})) : null;
		var box = h('.flxg-modal' + (opts.wide ? '.is-wide' : ''), { role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
			h('.flxg-modal-head', h('h2', title), h('button.flxg-icon-btn', { type: 'button', 'aria-label': t('Kapat'), onclick: close }, Y.icon('x', 18))),
			h('.flxg-modal-body', body),
			foot
		);
		shade.appendChild(box);
		shade.addEventListener('mousedown', function (e) { if (e.target === shade) close(); });
		(Y.$('#flxg-app') || document.body).appendChild(shade);
		document.addEventListener('keydown', onKey, true);
		var first = box.querySelector('input:not([type=checkbox]):not([type=radio]),textarea,select');
		if (first) setTimeout(function () { first.focus(); }, 30);
		return { close: close, el: box };
	};
	Y.confirm = function (title, text, okLabel, danger) {
		return new Promise(function (resolve) {
			var done = false;
			Y.modal(title, h('p.flxg-muted', text), {
				actions: [
					{ label: t('Vazgeç'), onClick: function () { done = true; resolve(false); } },
					{ label: okLabel || t('Tamam'), primary: !danger, danger: danger, onClick: function () { done = true; resolve(true); } }
				],
				onClose: function () { if (!done) resolve(false); }
			});
		});
	};
	Y.prompt = function (title, value, okLabel) {
		return new Promise(function (resolve) {
			var inp = h('input.flxg-input', { type: 'text', value: value || '' });
			var done = false;
			var m = Y.modal(title, inp, {
				actions: [
					{ label: t('Vazgeç') },
					{ label: okLabel || t('Kaydet'), primary: true, onClick: function () { done = true; resolve(inp.value.trim()); } }
				],
				onClose: function () { if (!done) resolve(null); }
			});
			inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { done = true; resolve(inp.value.trim()); m.close(); } });
		});
	};

	/* ---------------- Avatarlar ---------------- */
	Y.avatar = function (u, size) {
		size = size || 24;
		if (!u) return h('span.flxg-av.is-empty', { style: { width: size + 'px', height: size + 'px' } }, Y.icon('user', Math.round(size * 0.6)));
		var el = h('span.flxg-av', { title: u.name, style: { width: size + 'px', height: size + 'px', background: u.color, fontSize: Math.round(size * 0.42) + 'px' } }, u.initials);
		if (u.avatar) {
			var img = h('img', { src: u.avatar, alt: '', loading: 'lazy' });
			img.onerror = function () { img.remove(); };
			el.appendChild(img);
		}
		return el;
	};
	Y.avatars = function (ids, size, max) {
		max = max || 3;
		var users = (Y.S && Y.S.users) || {};
		var wrap = h('span.flxg-avs');
		ids.slice(0, max).forEach(function (id) { wrap.appendChild(Y.avatar(users[id], size)); });
		if (ids.length > max) wrap.appendChild(h('span.flxg-av.is-more', { style: { width: (size || 24) + 'px', height: (size || 24) + 'px' } }, '+' + (ids.length - max)));
		return wrap;
	};

	/* ---------------- Sürükle-bırak (fare + dokunmatik uzun basma) ---------------- */
	/**
	 * Y.dnd(root, { item: '.sel', zone: '[data-drop]', onDrop(info), canStart(el), mode: 'list'|'cell' })
	 * info = { id, el, zone, before (sonraki öğe id), after (önceki öğe id) }
	 */
	Y.dnd = function (root, o) {
		var st = null;
		var swallow = false;
		function begin(el, x, y) {
			var r = el.getBoundingClientRect();
			var ghost = el.cloneNode(true);
			ghost.classList.add('flxg-ghost');
			// Satır kalıbı (--cols, --depth) kapsayıcıdan gelir; kopya uygulama köküne taşınınca kaybolmasın.
			var cs = getComputedStyle(el);
			if (cs.display === 'grid') ghost.style.gridTemplateColumns = cs.gridTemplateColumns;
			['--depth', '--indent'].forEach(function (v) { var x = cs.getPropertyValue(v); if (x) ghost.style.setProperty(v, x.trim()); });
			ghost.style.width = r.width + 'px';
			ghost.style.left = r.left + 'px';
			ghost.style.top = r.top + 'px';
			(Y.$('#flxg-app') || document.body).appendChild(ghost);
			el.classList.add('is-dragging');
			var ph = h('.flxg-ph' + (o.mode === 'cell' ? '.is-cell' : ''));
			if (o.mode !== 'cell') ph.style.height = Math.min(r.height, 120) + 'px';
			st = { el: el, ghost: ghost, dx: x - r.left, dy: y - r.top, ph: ph, zone: null };
			document.body.classList.add('flxg-dragging');
			if (o.onStart) o.onStart(el);
			if (navigator.vibrate) try { navigator.vibrate(15); } catch (e) { /* yok */ }
			move(x, y);
		}
		function scrollParents(node) {
			var out = [];
			while (node && node !== document.body) {
				var cs = getComputedStyle(node);
				if (/(auto|scroll)/.test(cs.overflowY + cs.overflowX)) out.push(node);
				node = node.parentElement;
			}
			return out;
		}
		function autoscroll(x, y) {
			scrollParents(st.zone || root).forEach(function (sc) {
				var r = sc.getBoundingClientRect();
				var edge = 48;
				if (y < r.top + edge) sc.scrollTop -= 14;
				else if (y > r.bottom - edge) sc.scrollTop += 14;
				if (x < r.left + edge) sc.scrollLeft -= 14;
				else if (x > r.right - edge) sc.scrollLeft += 14;
			});
		}
		function move(x, y) {
			st.ghost.style.left = x - st.dx + 'px';
			st.ghost.style.top = y - st.dy + 'px';
			st.lastX = x;
			st.lastY = y;
			var under = document.elementFromPoint(x, y);
			var zone = under && under.closest(o.zone);
			if (zone && !root.contains(zone)) zone = null;
			if (zone && zone.dataset.nodrop) zone = null;
			Y.$$('.is-drop-over', root).forEach(function (z) { if (z !== zone) z.classList.remove('is-drop-over'); });
			st.zone = zone;
			if (!zone) { st.ph.remove(); return; }
			zone.classList.add('is-drop-over');
			if (o.mode === 'cell') return;
			var box = zone.querySelector('[data-drop-items]') || zone;
			var items = Y.$$(o.item, box).filter(function (it) { return it !== st.el; });
			var before = null;
			for (var i = 0; i < items.length; i++) {
				var r = items[i].getBoundingClientRect();
				if (y < r.top + r.height / 2) { before = items[i]; break; }
			}
			if (before) box.insertBefore(st.ph, before);
			else {
				var tail = box.querySelector('[data-drop-tail]');
				if (tail && tail.parentNode === box) box.insertBefore(st.ph, tail);
				else box.appendChild(st.ph);
			}
		}
		function finish(cancel) {
			if (!st) return;
			var s = st;
			st = null;
			document.body.classList.remove('flxg-dragging');
			s.ghost.remove();
			s.el.classList.remove('is-dragging');
			Y.$$('.is-drop-over', root).forEach(function (z) { z.classList.remove('is-drop-over'); });
			var info = null;
			if (!cancel && s.zone) {
				var prev = s.ph.previousElementSibling;
				while (prev && (!prev.matches(o.item) || prev === s.el)) prev = prev.previousElementSibling;
				var next = s.ph.nextElementSibling;
				while (next && (!next.matches(o.item) || next === s.el)) next = next.nextElementSibling;
				info = { id: s.el.dataset.id, el: s.el, zone: s.zone, after: prev ? prev.dataset.id : null, before: next ? next.dataset.id : null };
			}
			s.ph.remove();
			if (o.onEnd) o.onEnd(s.el, !!info);
			swallow = true;
			setTimeout(function () { swallow = false; }, 50);
			if (info) o.onDrop(info);
		}
		var tick = null;
		function loop() {
			if (!st) { tick = null; return; }
			autoscroll(st.lastX, st.lastY);
			tick = requestAnimationFrame(loop);
		}
		// Fare / kalem.
		root.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'touch' || e.button !== 0) return;
			var el = e.target.closest(o.item);
			if (!el || !root.contains(el) || e.target.closest('input,textarea,button,a,select,[contenteditable],.flxg-nodrag')) return;
			if (o.canStart && !o.canStart(el)) return;
			var sx = e.clientX;
			var sy = e.clientY;
			var started = false;
			function mv(ev) {
				if (!started) {
					if (Math.abs(ev.clientX - sx) + Math.abs(ev.clientY - sy) < 6) return;
					started = true;
					begin(el, sx, sy);
					tick = requestAnimationFrame(loop);
				}
				move(ev.clientX, ev.clientY);
				ev.preventDefault();
			}
			function up() {
				document.removeEventListener('pointermove', mv);
				document.removeEventListener('pointerup', up);
				if (started) finish(false);
			}
			document.addEventListener('pointermove', mv);
			document.addEventListener('pointerup', up);
		});
		// Dokunmatik: uzun basınca sürükler, kısa kaydırma sayfayı kaydırır.
		root.addEventListener('touchstart', function (e) {
			if (e.touches.length !== 1) return;
			var el = e.target.closest(o.item);
			if (!el || !root.contains(el) || e.target.closest('input,textarea,button,a,select,[contenteditable],.flxg-nodrag')) return;
			if (o.canStart && !o.canStart(el)) return;
			var t0 = e.touches[0];
			var sx = t0.clientX;
			var sy = t0.clientY;
			var timer = setTimeout(function () {
				begin(el, sx, sy);
				tick = requestAnimationFrame(loop);
			}, 380);
			function mv(ev) {
				var p = ev.touches[0];
				if (!st) {
					if (Math.abs(p.clientX - sx) + Math.abs(p.clientY - sy) > 10) { clearTimeout(timer); cleanup(); }
					return;
				}
				ev.preventDefault();
				move(p.clientX, p.clientY);
			}
			function end() {
				clearTimeout(timer);
				cleanup();
				if (st) finish(false);
			}
			function cleanup() {
				document.removeEventListener('touchmove', mv);
				document.removeEventListener('touchend', end);
				document.removeEventListener('touchcancel', end);
			}
			document.addEventListener('touchmove', mv, { passive: false });
			document.addEventListener('touchend', end);
			document.addEventListener('touchcancel', end);
		}, { passive: true });
		root.addEventListener('contextmenu', function (e) { if (st) e.preventDefault(); });
		root.addEventListener('click', function (e) { if (swallow) { e.stopPropagation(); e.preventDefault(); } }, true);
	};

	/** Yeni konum: iki komşunun ortası. */
	Y.between = function (a, b) {
		if (a === null && b === null) return 1024;
		if (a === null) return b - 1024;
		if (b === null) return a + 1024;
		return (a + b) / 2;
	};

	/** Tarayıcıda kullanıcıya özel küçük tercihler (localStorage; erişilemezse sessizce geçer). */
	Y.store = function (k, v) {
		try {
			if (v === undefined) return localStorage.getItem('flxg-' + k);
			if (v === null) localStorage.removeItem('flxg-' + k);
			else localStorage.setItem('flxg-' + k, v);
		} catch (e) { return null; }
		return null;
	};
	Y.storeJSON = function (k, v) {
		if (v === undefined) { try { return JSON.parse(Y.store(k) || 'null'); } catch (e) { return null; } }
		return Y.store(k, v === null ? null : JSON.stringify(v));
	};

	/** Ekran tercihleri hesapta saklanır (her cihazda aynı); hesapta yoksa tarayıcıdaki eski değer kullanılır. */
	var prefQueue = {};
	var prefTimer = null;
	Y.pref = function (k) {
		var p = Y.S && Y.S.me && Y.S.me.prefs;
		if (p && Object.prototype.hasOwnProperty.call(p, k)) return p[k];
		return Y.storeJSON(k);
	};
	Y.setPref = function (k, v) {
		var me = Y.S && Y.S.me;
		if (me) {
			me.prefs = me.prefs || {};
			if (v === null) delete me.prefs[k]; else me.prefs[k] = v;
		}
		Y.storeJSON(k, v);
		prefQueue[k] = v;
		clearTimeout(prefTimer);
		prefTimer = setTimeout(function () {
			var q = prefQueue;
			prefQueue = {};
			Y.api('POST', 'me', { prefs: q }, { quiet: true }).catch(function () {});
		}, 800);
	};

	Y.debounce = function (fn, ms) {
		var tm = null;
		return function () {
			var args = arguments;
			var self = this;
			clearTimeout(tm);
			tm = setTimeout(function () { fn.apply(self, args); }, ms);
		};
	};
	Y.autosize = function (ta) {
		var fit = function () { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
		ta.addEventListener('input', fit);
		setTimeout(fit, 0);
		return fit;
	};
	Y.copy = function (text) {
		if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).then(function () { Y.toast(t('Kopyalandı')); });
		var ta = h('textarea', { style: { position: 'fixed', opacity: '0' } }, text);
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); Y.toast(t('Kopyalandı')); } catch (e) { /* yok */ }
		ta.remove();
		return Promise.resolve();
	};

	/** Yorum gövdesi: kaçış + @bahsetme + bağlantı + **kalın** + satır sonu. */
	Y.richText = function (body) {
		var users = (Y.S && Y.S.users) || {};
		var esc = String(body || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
		esc = esc.replace(/@\{(\d+)\}/g, function (_, id) {
			var u = users[id];
			return '<span class="flxg-mention' + (Y.S && +id === Y.S.me.id ? ' is-me' : '') + '">@' + (u ? u.name.replace(/[&<>]/g, '') : t('Silinmiş kullanıcı')) + '</span>';
		});
		esc = esc.replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
		esc = esc.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
		esc = esc.replace(/(^|\s)_([^_\n]+)_(?=\s|$)/g, '$1<em>$2</em>');
		esc = esc.replace(/`([^`\n]+)`/g, '<code>$1</code>');
		return esc.replace(/\n/g, '<br>');
	};

	Y.fileKind = function (mime, name) {
		if (/^image\//.test(mime)) return 'image';
		if (/pdf/.test(mime)) return 'pdf';
		if (/^video\//.test(mime)) return 'video';
		if (/^audio\//.test(mime)) return 'audio';
		if (/sheet|excel|csv/.test(mime) || /\.(xlsx?|csv|ods)$/i.test(name || '')) return 'sheet';
		if (/word|document|text/.test(mime)) return 'doc';
		if (/zip|rar|7z|tar/.test(mime)) return 'zip';
		return 'other';
	};

	/** Görsel önizleme (lightbox). */
	Y.lightbox = function (files, index) {
		var i = index || 0;
		var shade = h('.flxg-lightbox');
		var img = h('img', { alt: '' });
		var cap = h('.flxg-lb-cap');
		function show() {
			var f = files[i];
			img.src = Y.fileUrl(f.id);
			Y.clear(cap).appendChild(h('span', f.name + ' · ' + Y.bytes(f.size)));
			cap.appendChild(h('a.flxg-btn.is-ghost', { href: Y.fileUrl(f.id, '', true), download: f.name }, Y.icon('download'), t('İndir')));
		}
		function close() { shade.remove(); document.removeEventListener('keydown', key, true); }
		function key(e) {
			if (e.key === 'Escape') { e.stopPropagation(); close(); }
			if (e.key === 'ArrowRight' && files.length > 1) { i = (i + 1) % files.length; show(); }
			if (e.key === 'ArrowLeft' && files.length > 1) { i = (i - 1 + files.length) % files.length; show(); }
		}
		shade.appendChild(h('button.flxg-lb-close', { type: 'button', 'aria-label': t('Kapat'), onclick: close }, Y.icon('x', 22)));
		if (files.length > 1) {
			shade.appendChild(h('button.flxg-lb-nav.is-prev', { type: 'button', onclick: function () { i = (i - 1 + files.length) % files.length; show(); } }, Y.icon('chevL', 26)));
			shade.appendChild(h('button.flxg-lb-nav.is-next', { type: 'button', onclick: function () { i = (i + 1) % files.length; show(); } }, Y.icon('chevR', 26)));
		}
		shade.appendChild(img);
		shade.appendChild(cap);
		shade.addEventListener('click', function (e) { if (e.target === shade) close(); });
		document.body.appendChild(shade);
		document.addEventListener('keydown', key, true);
		show();
	};
})();
