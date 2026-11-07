/* Flux Social — çekirdek (Flux Studio tabanı): çeviri, API, DOM, simgeler, açılır pencereler, bildirim. */
(function () {
	'use strict';
	var B = window.FLXSM_BOOT || {};
	var S = (window.FLXSM = window.FLXSM || {});
	S.B = B;

	/* ---------------- Çeviri ---------------- */
	var I18N = B.i18n || {};
	S.t = function (s, vars) {
		var out = Object.prototype.hasOwnProperty.call(I18N, s) && I18N[s] ? I18N[s] : s;
		if (vars) {
			Object.keys(vars).forEach(function (k) {
				out = out.split('{' + k + '}').join(vars[k]);
			});
		}
		return out;
	};
	var t = S.t;

	/* ---------------- DOM ---------------- */
	function h(tag, attrs) {
		var m = /^([a-z0-9]+)?((?:[.#][\w-]+)*)$/i.exec(tag) || [];
		var el = document.createElement(m[1] || 'div');
		(m[2] || '').replace(/([.#])([\w-]+)/g, function (_, k, v) {
			if (k === '.') el.classList.add(v);
			else el.id = v;
		});
		var start = 1;
		if (attrs && typeof attrs === 'object' && !(attrs instanceof Node) && !Array.isArray(attrs)) {
			start = 2;
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) return;
				if (k === 'class') String(v).split(/\s+/).filter(Boolean).forEach(function (c) { el.classList.add(c); });
				else if (k === 'style' && typeof v === 'object') {
					Object.keys(v).forEach(function (p) {
						if (p.indexOf('--') === 0) el.style.setProperty(p, v[p]);
						else el.style[p] = v[p];
					});
				} else if (k.indexOf('on') === 0 && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
				else if (k === 'text') el.textContent = v;
				else if (k === 'dataset') Object.keys(v).forEach(function (d) { el.dataset[d] = v[d]; });
				else if (k === 'value') el.value = v;
				else if (v === true) el.setAttribute(k, '');
				else el.setAttribute(k, v);
			});
		}
		for (var i = start; i < arguments.length; i++) add(el, arguments[i]);
		return el;
	}
	function add(el, c) {
		if (c === null || c === undefined || c === false) return;
		if (Array.isArray(c)) return c.forEach(function (x) { add(el, x); });
		// Primitives always become text nodes; only real DOM nodes are appended as-is.
		if (typeof c !== 'object') { el.appendChild(document.createTextNode(String(c))); return; }
		if (c instanceof Node) el.appendChild(c);
		else el.appendChild(document.createTextNode(String(c)));
	}
	S.h = h;
	S.clear = function (el) {
		while (el && el.firstChild) el.removeChild(el.firstChild);
		return el;
	};
	S.$ = function (s, root) { return (root || document).querySelector(s); };

	/* Simgeler (çizgi, 24×24). */
	var IC = {
		image: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM21 15l-5-5L5 21',
		video: 'm22 8-6 4 6 4V8zM4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
		grid: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
		star: 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z',
		folder: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
		folderPlus: 'M12 10v6M9 13h6M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
		user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
		users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
		search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35',
		plus: 'M12 5v14M5 12h14',
		x: 'M18 6 6 18M6 6l12 12',
		check: 'M20 6 9 17l-5-5',
		sparkle: 'M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0zM20 3v4M22 5h-4',
		bulb: 'M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z',
		send: 'm22 2-7 20-4-9-9-4zM22 2 11 13',
		trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
		download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
		upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
		copy: 'M8 8h12v12H8zM16 8V4H4v12h4',
		edit: 'M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z',
		brush: 'M18.4 2.6a2 2 0 0 1 3 3L12 15l-3-3zM9 12c-2 0-4 1.5-4 4 0 1.5-1 3-3 3 1.5 1.5 3.5 2 5 2 3 0 5-2 5-5',
		square: 'M3 3h18v18H3z',
		eraser: 'm7 21-4.3-4.3a1 1 0 0 1 0-1.4l10-10a1 1 0 0 1 1.4 0l5.6 5.6a1 1 0 0 1 0 1.4L13 19M22 21H7M5 11l9 9',
		undo: 'M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13',
		compare: 'M12 3v18M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z',
		repeat: 'm17 2 4 4-4 4M3 11v-1a4 4 0 0 1 4-4h14M7 22l-4-4 4-4M21 13v1a4 4 0 0 1-4 4H3',
		link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
		library: 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z',
		move: 'M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20',
		dots: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
		chevD: 'm6 9 6 6 6-6',
		chevR: 'm9 18 6-6-6-6',
		chevL: 'm15 18-6-6 6-6',
		settings: 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
		alert: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
		back: 'M19 12H5M12 19l-7-7 7-7',
		menu: 'M4 6h16M4 12h16M4 18h16',
		sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41',
		moon: 'M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z',
		layers: 'm12 2 10 5-10 5L2 7zM2 17l10 5 10-5M2 12l10 5 10-5',
		template: 'M3 3h18v6H3zM3 13h8v8H3zM15 13h6v8h-6z',
		stop: 'M6 6h12v12H6z',
		at: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8',
		share: 'M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13',
		chart: 'M3 3v18h18M7 14l4-4 3 3 6-6',
		home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
		calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
		list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
		clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
		eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
		heart: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7z',
		msg: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
		refresh: 'M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8M3 3v5h5M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16M16 16h5v5',
		key: 'M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4',
		plug: 'M12 22v-5M9 8V2M15 8V2M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8z',
		ext: 'M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6',
		hash: 'M4 9h16M4 15h16M10 3 8 21M16 3l-2 18',
		wand: 'm15 4-1.5 1.5M20 9l-1.5 1.5M17.5 2.5v2M21.5 6.5h-2M3 21l11-11 2 2L5 23zM12 3l.5 1M19 12l1 .5',
		globe: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z',
		inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
		filter: 'M22 3H2l8 9.46V19l4 2v-8.54z',
		play: 'M6 4l14 8-14 8z',
		pause: 'M6 4h4v16H6zM14 4h4v16h-4z',
		log: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
		up: 'M7 17 17 7M7 7h10v10'
	};
	S.icon = function (name, size, cls) {
		var s = size || 18;
		var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('width', s);
		svg.setAttribute('height', s);
		svg.setAttribute('fill', 'none');
		svg.setAttribute('stroke', 'currentColor');
		svg.setAttribute('stroke-width', '1.8');
		svg.setAttribute('stroke-linecap', 'round');
		svg.setAttribute('stroke-linejoin', 'round');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('class', 'flxsm-i' + (cls ? ' ' + cls : ''));
		var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		p.setAttribute('d', IC[name] || IC.dots);
		svg.appendChild(p);
		return svg;
	};
	S.btn = function (icon, label, onClick, cls, title) {
		return h('button', { type: 'button', class: 'flxsm-btn ' + (cls || ''), onclick: onClick, title: title || null, 'aria-label': label ? null : title }, icon ? S.icon(icon, 16) : null, label ? h('span', label) : null);
	};
	S.iconBtn = function (icon, title, onClick, cls) {
		return h('button', { type: 'button', class: 'flxsm-ib ' + (cls || ''), title: title, 'aria-label': title, onclick: onClick }, S.icon(icon, 18));
	};

	/* ---------------- Biçim ---------------- */
	var LOC = B.locale || 'tr-TR';
	S.locale = LOC;
	S.money = function (n) {
		return '$' + (Math.round((n || 0) * 1000) / 1000).toLocaleString(LOC, { minimumFractionDigits: 2, maximumFractionDigits: 3 });
	};
	S.ago = function (ts) {
		var d = Math.max(0, Date.now() / 1000 - ts);
		if (d < 60) return t('az önce');
		if (d < 3600) return t('{n} dk önce', { n: Math.floor(d / 60) });
		if (d < 86400) return t('{n} sa önce', { n: Math.floor(d / 3600) });
		return new Date(ts * 1000).toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: 'numeric' });
	};
	S.norm = function (s) { return String(s || '').toLocaleLowerCase(LOC).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i'); };
	S.debounce = function (fn, ms) {
		var tm;
		return function () {
			var a = arguments, self = this;
			clearTimeout(tm);
			tm = setTimeout(function () { fn.apply(self, a); }, ms);
		};
	};
	S.pref = function (k, v) {
		try {
			if (arguments.length > 1) localStorage.setItem('flxsm.' + k, JSON.stringify(v));
			else return JSON.parse(localStorage.getItem('flxsm.' + k) || 'null');
		} catch (e) { return null; }
	};


	/* ---------------- Ağ rozetleri ---------------- */
	var NET = {
		facebook: ['#1877F2', 't', 'f'],
		instagram: ['#E1306C', 'p', 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17.5 6.5h.01'],
		youtube: ['#FF0000', 'f', 'M9 7.5v9l7.5-4.5z'],
		x: ['#111111', 'p', 'M5 4l14 16M19 4 5 20'],
		linkedin: ['#0A66C2', 't', 'in'],
		tiktok: ['#111111', 'p', 'M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5M14 4c.6 2.4 2.4 4 5 4'],
		pinterest: ['#E60023', 't', 'P'],
		gbp: ['#4285F4', 't', 'G'],
		whatsapp: ['#25D366', 'p', 'M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.3zM9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.6-2-1-1 1c-1.2-.5-2.4-1.7-2.9-2.9l1-1-1-2z']
	};
	S.netColor = function (p) { return (NET[p] || ['#5f6b64'])[0]; };
	S.net = function (p, size) {
		var d = NET[p] || ['#5f6b64', 't', '?'];
		var s = size || 18;
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('width', s);
		svg.setAttribute('height', s);
		svg.setAttribute('class', 'flxsm-net');
		svg.setAttribute('aria-hidden', 'true');
		var c = document.createElementNS(ns, 'circle');
		c.setAttribute('cx', 12); c.setAttribute('cy', 12); c.setAttribute('r', 12); c.setAttribute('fill', d[0]);
		svg.appendChild(c);
		var g = document.createElementNS(ns, 'g');
		g.setAttribute('transform', 'translate(4.2 4.2) scale(.65)');
		if (d[1] === 't') {
			var tx = document.createElementNS(ns, 'text');
			tx.setAttribute('x', 12); tx.setAttribute('y', 18.5); tx.setAttribute('text-anchor', 'middle');
			tx.setAttribute('fill', '#fff');
			tx.setAttribute('font-size', d[2].length > 1 ? 15 : 19);
			tx.setAttribute('font-weight', 800);
			tx.setAttribute('font-family', 'Arial, Helvetica, sans-serif');
			tx.textContent = d[2];
			g.appendChild(tx);
		} else {
			var pa = document.createElementNS(ns, 'path');
			pa.setAttribute('d', d[2]);
			if (d[1] === 'f') pa.setAttribute('fill', '#fff');
			else { pa.setAttribute('fill', 'none'); pa.setAttribute('stroke', '#fff'); pa.setAttribute('stroke-width', 2.4); pa.setAttribute('stroke-linecap', 'round'); pa.setAttribute('stroke-linejoin', 'round'); }
			g.appendChild(pa);
		}
		svg.appendChild(g);
		return svg;
	};
	/* Hesap avatarı + ağ rozeti. */
	S.avatar = function (acc, size) {
		var s = size || 32;
		var img = acc && acc.avatar ? h('img', { src: acc.avatar, alt: '', loading: 'lazy', referrerpolicy: 'no-referrer', onerror: function () { this.replaceWith(h('span.flxsm-av', { style: { width: s + 'px', height: s + 'px' } }, initials(acc.name))); } }) : h('span.flxsm-av', { style: { width: s + 'px', height: s + 'px' } }, initials(acc && acc.name));
		if (img.tagName === 'IMG') { img.style.width = s + 'px'; img.style.height = s + 'px'; }
		return h('span.flxsm-acc-av', { style: { width: s + 'px', height: s + 'px' } }, img, acc ? h('span.flxsm-acc-net', S.net(acc.provider, Math.max(14, Math.round(s * 0.45)))) : null);
	};
	function initials(n) {
		return String(n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toLocaleUpperCase(LOC);
	}

	/* ---------------- Zaman (site saat dilimi) ---------------- */
	var OFF = (B.tzOffset || 0);
	S.setTz = function (off) { OFF = off || 0; };
	function siteDate(ts) { return new Date((ts + OFF) * 1000); }
	S.fmt = function (ts, o) {
		if (!ts) return '';
		return siteDate(ts).toLocaleString(LOC, Object.assign({ timeZone: 'UTC', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }, o || {}));
	};
	S.fmtDay = function (ts, o) { return S.fmt(ts, Object.assign({ hour: undefined, minute: undefined }, o || {})); };
	S.fmtTime = function (ts) { return siteDate(ts).toLocaleTimeString(LOC, { timeZone: 'UTC', hour: '2-digit', minute: '2-digit' }); };
	/* <input type=datetime-local> değeri ↔ unix (site saati). */
	S.toLocalInput = function (ts) {
		var d = siteDate(ts);
		var p = function (n) { return (n < 10 ? '0' : '') + n; };
		return d.getUTCFullYear() + '-' + p(d.getUTCMonth() + 1) + '-' + p(d.getUTCDate()) + 'T' + p(d.getUTCHours()) + ':' + p(d.getUTCMinutes());
	};
	S.fromLocalInput = function (v) {
		var m = /^(\d{4})-(\d\d)-(\d\d)T(\d\d):(\d\d)/.exec(v || '');
		if (!m) return 0;
		return Math.round(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) / 1000) - OFF;
	};
	/* Site saatiyle gün başlangıcı (unix). */
	S.dayStart = function (ts) {
		var d = siteDate(ts);
		return Math.round(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()) / 1000) - OFF;
	};
	S.parts = function (ts) {
		var d = siteDate(ts);
		return { y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate(), w: d.getUTCDay(), h: d.getUTCHours(), i: d.getUTCMinutes() };
	};
	S.mk = function (y, m, d, hh, mm) { return Math.round(Date.UTC(y, m, d, hh || 0, mm || 0) / 1000) - OFF; };
	S.num = function (n) {
		n = +n || 0;
		if (Math.abs(n) < 1e4) return n.toLocaleString(LOC);
		try { return n.toLocaleString(LOC, { notation: 'compact', maximumFractionDigits: 1 }); } catch (e) { return n.toLocaleString(LOC); }
	};
	/* Karakter sayısı (emoji = 1, X'te bağlantı 23). */
	S.len = function (s, net) {
		s = String(s || '');
		if (net === 'x') s = s.replace(/https?:\/\/\S+/g, '12345678901234567890123');
		return Array.from(s).length;
	};

	/* ---------------- API ---------------- */
	/* Düz kalıcı bağlantıda kök "?rest_route=/…/" olur; yolun sorgusu "&" ile eklenir. */
	S.restUrl = function (path) {
		var parts = String(path).replace(/^\//, '').split('?');
		var url = (B.rest || '') + parts[0];
		return url + (url.indexOf('?') < 0 ? '?' : '&') + (parts[1] || '');
	};
	S.api = function (method, path, body, opts) {
		opts = opts || {};
		var url = S.restUrl(path);
		var init = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': B.nonce || '', Accept: 'application/json' } };
		if (body instanceof FormData) init.body = body;
		else if (body !== undefined && body !== null) {
			init.headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(body);
		}
		// REST dili: kullanıcının dili (WP varsayılanı site dilidir).
		url += '&_locale=user' + (method === 'GET' ? '&_=' + Date.now() : '');
		return fetch(url, init).then(function (r) {
			return r.text().then(function (txt) {
				var d = null;
				try { d = txt ? JSON.parse(txt) : null; } catch (e) { d = null; }
				if (!r.ok) {
					var err = new Error((d && d.message) || t('Sunucu hatası ({n}).', { n: r.status }));
					err.status = r.status;
					err.data = d && d.data;
					err.code = d && d.code;
					throw err;
				}
				if (d === null && txt) {
					var e2 = new Error(t('Sunucudan beklenmeyen cevap geldi.'));
					e2.status = r.status;
					throw e2;
				}
				return d;
			});
		});
	};

	/* ---------------- Bildirim ---------------- */
	var toastBox;
	S.toast = function (msg, kind) {
		if (!toastBox) {
			toastBox = h('.flxsm-toasts', { role: 'status', 'aria-live': 'polite' });
			document.body.appendChild(toastBox);
		}
		var el = h('.flxsm-toast' + (kind ? '.is-' + kind : ''), S.icon(kind === 'error' ? 'alert' : 'check', 16), h('span', msg));
		toastBox.appendChild(el);
		setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 300); }, kind === 'error' ? 6000 : 3000);
	};
	S.fail = function (e) {
		S.toast((e && e.message) || t('Bir hata oluştu.'), 'error');
	};

	/* ---------------- Açılır menü ---------------- */
	var openPop = null;
	S.closePop = function () {
		if (openPop) {
			openPop.el.remove();
			document.removeEventListener('mousedown', openPop.out, true);
			document.removeEventListener('keydown', openPop.key, true);
			openPop = null;
		}
	};
	/* Pencere ve menü katmanı: body'nin doğrudan çocuğu, Flux Admin üst barı ve sol menünün de üstünde (karartma her şeyi örter). */
	S.layer = function () {
		var l = document.getElementById('flxsm-layer');
		if (!l) {
			l = h('.flxsm.flxsm-layer', { id: 'flxsm-layer' });
			document.body.appendChild(l);
		}
		l.setAttribute('data-theme', S.root && S.root.getAttribute('data-theme') || 'dark');
		return l;
	};
	S.pop = function (anchor, content, opts) {
		opts = opts || {};
		S.closePop();
		var el = h('.flxsm-pop' + (opts.cls ? '.' + opts.cls : ''), content);
		S.layer().appendChild(el);
		var r = anchor.getBoundingClientRect();
		var w = el.offsetWidth, ht = el.offsetHeight;
		var x = opts.alignRight ? r.right - w : r.left;
		var y = r.bottom + 6;
		if (y + ht > window.innerHeight - 8) y = Math.max(8, r.top - ht - 6);
		x = Math.max(8, Math.min(x, window.innerWidth - w - 8));
		el.style.left = x + 'px';
		el.style.top = y + 'px';
		var out = function (e) { if (!el.contains(e.target) && !anchor.contains(e.target)) S.closePop(); };
		var key = function (e) { if (e.key === 'Escape') { e.stopPropagation(); S.closePop(); } };
		document.addEventListener('mousedown', out, true);
		document.addEventListener('keydown', key, true);
		openPop = { el: el, out: out, key: key };
		return el;
	};
	/* items: [{icon, label, onClick, danger, checked, sep}] */
	S.menu = function (anchor, items, opts) {
		var list = h('.flxsm-menu');
		items.filter(Boolean).forEach(function (it) {
			if (it.sep) return list.appendChild(h('.flxsm-menu-sep'));
			if (it.head) return list.appendChild(h('.flxsm-menu-head', it.head));
			list.appendChild(h('button', {
				type: 'button',
				class: 'flxsm-menu-item' + (it.danger ? ' is-danger' : '') + (it.checked ? ' is-checked' : ''),
				disabled: it.disabled,
				onclick: function () { S.closePop(); it.onClick && it.onClick(); }
			}, it.icon ? S.icon(it.icon, 16) : h('span.flxsm-menu-sp'), h('span', it.label), it.checked ? S.icon('check', 14, 'flxsm-menu-ck') : null, it.hint ? h('small', it.hint) : null));
		});
		return S.pop(anchor, list, opts);
	};

	/* ---------------- Modal ---------------- */
	var modals = [];
	S.modal = function (title, body, opts) {
		opts = opts || {};
		var shade = h('.flxsm-shade' + (opts.full ? '.is-full' : ''));
		var close = function () {
			if (opts.onClose && opts.onClose() === false) return;
			shade.remove();
			modals = modals.filter(function (m) { return m !== api; });
		};
		var foot = opts.foot ? h('.flxsm-modal-foot', opts.foot) : null;
		var box = h('.flxsm-modal' + (opts.wide ? '.is-wide' : '') + (opts.cls ? '.' + opts.cls : ''), { role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
			title ? h('.flxsm-modal-head', h('h2', title), S.iconBtn('x', t('Kapat'), close)) : null,
			h('.flxsm-modal-body', body),
			foot
		);
		shade.appendChild(box);
		shade.addEventListener('mousedown', function (e) { if (e.target === shade && !opts.sticky) close(); });
		S.layer().appendChild(shade);
		var api = { el: box, shade: shade, close: close };
		modals.push(api);
		setTimeout(function () {
			var f = box.querySelector('[autofocus], textarea, input:not([type=checkbox]):not([type=file])');
			if (f && !opts.noFocus) f.focus();
		}, 30);
		return api;
	};
	S.topModal = function () { return modals[modals.length - 1] || null; };
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !openPop && modals.length && !e.defaultPrevented) {
			e.preventDefault();
			modals[modals.length - 1].close();
		}
	});
	S.confirm = function (title, text, okLabel, danger) {
		return new Promise(function (res) {
			var done = false;
			var m = S.modal(title, h('p', text), {
				foot: [
					h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
					h('button.flxsm-btn' + (danger ? '.is-danger' : '.is-primary'), { type: 'button', onclick: function () { done = true; m.close(); } }, okLabel || t('Tamam'))
				],
				onClose: function () { res(done); }
			});
		});
	};
	S.prompt = function (title, value, okLabel) {
		return new Promise(function (res) {
			var val = null;
			var input = h('input.flxsm-input', { type: 'text', value: value || '' });
			var ok = function () { val = input.value.trim(); m.close(); };
			input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ok(); } });
			var m = S.modal(title, input, {
				foot: [
					h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')),
					h('button.flxsm-btn.is-primary', { type: 'button', onclick: ok }, okLabel || t('Kaydet'))
				],
				onClose: function () { res(val ? val : null); }
			});
			setTimeout(function () { input.select(); }, 40);
		});
	};

	/* ---------------- Dosya ---------------- */
	S.readFile = function (file) {
		return new Promise(function (res, rej) {
			var r = new FileReader();
			r.onload = function () { res(r.result); };
			r.onerror = rej;
			r.readAsDataURL(file);
		});
	};
	S.copy = function (text) {
		if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).then(function () { S.toast(t('Kopyalandı')); });
		var ta = h('textarea', { style: { position: 'fixed', opacity: '0' } }, text);
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); S.toast(t('Kopyalandı')); } catch (e) { /* yok */ }
		ta.remove();
		return Promise.resolve();
	};
	S.download = function (url, name) {
		var a = h('a', { href: url, download: name || '' });
		document.body.appendChild(a);
		a.click();
		a.remove();
	};
})();
