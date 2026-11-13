/* Flux Chat — tüm kanalların (WhatsApp, site sohbeti…) tek gelen kutusu, temsilci arayüzü. Bağımlılık yok. */
(function () {
	'use strict';

	var B = window.FLXC_BOOT || {};
	var I18N = B.i18n || {};
	var root = document.getElementById('flxc-app');
	// Telefonda paneller yana kaydırılarak gizli; odak/menü kökü yatay kaydırırsa ekran kayık kalmasın (1.12.0).
	if (root) root.addEventListener('scroll', function () { if (root.scrollLeft) root.scrollLeft = 0; if (root.scrollTop) root.scrollTop = 0; });
	if (!root) return;

	/* ---------- yardımcılar ---------- */

	function t(s, vars) {
		var out = I18N[s] || s;
		if (vars) {
			Object.keys(vars).forEach(function (k) {
				out = out.split('{' + k + '}').join(vars[k]);
			});
		}
		return out;
	}

	function h(tag, attrs) {
		var el = document.createElement(tag);
		if (attrs) {
			for (var k in attrs) {
				if (!Object.prototype.hasOwnProperty.call(attrs, k)) continue;
				var v = attrs[k];
				if (v === null || v === undefined || v === false) continue;
				if (k === 'class') el.className = v;
				else if (k === 'text') el.textContent = v;
				else if (k === 'html') el.innerHTML = v;
				else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
				else if (k === 'style' && typeof v === 'object') {
					Object.keys(v).forEach(function (sk) {
						if (sk.indexOf('--') === 0) el.style.setProperty(sk, v[sk]); else el.style[sk] = v[sk];
					});
				}
				else el.setAttribute(k, v === true ? '' : v);
			}
		}
		for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
		return el;
	}
	function add(el, c) {
		if (c === null || c === undefined || c === false) return;
		if (Array.isArray(c)) { c.forEach(function (x) { add(el, x); }); return; }
		el.appendChild(typeof c === 'object' ? c : document.createTextNode(String(c)));
	}
	function $(sel, ctx) { return (ctx || root).querySelector(sel); }
	function clear(el) { while (el.firstChild) el.removeChild(el.firstChild); return el; }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function debounce(fn, ms) {
		var tm;
		return function () {
			var a = arguments, self = this;
			clearTimeout(tm);
			tm = setTimeout(function () { fn.apply(self, a); }, ms);
		};
	}
	function store(k, v) {
		try {
			if (v === undefined) return localStorage.getItem('flxc_' + k);
			localStorage.setItem('flxc_' + k, v);
		} catch (e) { return null; }
	}

	var P = {
		search: 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
		back: 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
		more: 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
		newchat: 'M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM17 11h-4v4h-2v-4H7V9h4V5h2v4h4v2z',
		bolt: 'M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.48 10.94 10.42 7.54 13 3h1l-1 7h3.5c.49 0 .56.33.47.51l-.07.15C12.96 17.55 11 21 11 21z',
		emoji: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z',
		attach: 'M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5a2.5 2.5 0 0 1 5 0v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5a2.5 2.5 0 0 0 5 0V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z',
		send: 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z',
		mic: 'M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z',
		bot: 'M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-1-4c-.83 0-1.5-.67-1.5-1.5S14.17 10 15 10s1.5.67 1.5 1.5S15.83 13 15 13z',
		link: 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z',
		close: 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
		done: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
		reopen: 'M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z',
		assign: 'M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
		person: 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
		reply: 'M10 9V5l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-1-5-4-10-11-11z',
		copy: 'M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z',
		image: 'M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z',
		doc: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z',
		play: 'M8 5v14l11-7z',
		campaign: 'M18 11v2h4v-2h-4zm-2 6.61c.96.71 2.21 1.65 3.2 2.39.4-.53.8-1.07 1.2-1.6-.99-.74-2.24-1.68-3.2-2.4-.4.54-.8 1.08-1.2 1.61zM20.4 5.6c-.4-.53-.8-1.07-1.2-1.6-.99.74-2.24 1.68-3.2 2.4.4.53.8 1.07 1.2 1.6.96-.72 2.21-1.65 3.2-2.4zM4 9c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2h1v4h2v-4h1l5 3V6L8 9H4zm11.5 3c0-1.33-.58-2.53-1.5-3.35v6.69c.92-.81 1.5-2.01 1.5-3.34z',
		pause: 'M6 19h4V5H6v14zm8-14v14h4V5h-4z',
		download: 'M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z',
		note: 'M19 3H4.99C3.89 3 3 3.9 3 5l.01 14c0 1.1.89 2 1.99 2h10l6-6V5c0-1.1-.9-2-2-2zM7 8h10v2H7V8zm5 6H7v-2h5v2zm2 5.5V14h5.5L14 19.5z',
		label: 'M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z',
		pin: 'M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z',
		moon: 'M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z',
		memory: 'M15 9H9v6h6V9zm-2 4h-2v-2h2v2zm8-2V9h-2V7c0-1.1-.9-2-2-2h-2V3h-2v2h-2V3H9v2H7c-1.1 0-2 .9-2 2v2H3v2h2v2H3v2h2v2c0 1.1.9 2 2 2h2v2h2v-2h2v2h2v-2h2c1.1 0 2-.9 2-2v-2h2v-2h-2v-2h2zm-4 6H7V7h10v10z',
		alarm: 'M22 5.72l-4.6-3.86-1.29 1.53 4.6 3.86L22 5.72zM7.88 3.39L6.6 1.86 2 5.71l1.29 1.53 4.59-3.85zM12.5 8H11v6l4.75 2.85.75-1.23-4-2.37V8zM12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9c4.97 0 9-4.03 9-9s-4.03-9-9-9zm0 16c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z',
		block: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z',
		bell: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z',
		gear: 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z',
		exit: 'M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5a2 2 0 0 0-2 2v4h2V5h14v14H5v-4H3v4a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z',
		ai: 'M19 9l1.25-2.75L23 5l-2.75-1.25L19 1l-1.25 2.75L15 5l2.75 1.25L19 9zm-7.5.5L9 4 6.5 9.5 1 12l5.5 2.5L9 20l2.5-5.5L17 12l-5.5-2.5zM19 15l-1.25 2.75L15 19l2.75 1.25L19 23l1.25-2.75L23 19l-2.75-1.25L19 15z',
		loc: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z',
		trash: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
		edit: 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41l-2.34-2.34a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z',
		plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
		down: 'M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6z',
		video: 'M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z',
		check: 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
		lock: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z',
		open: 'M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z',
		unread: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z',
		warn: 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
		phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
		cert: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z',
		gift: 'M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z',
		receipt: 'M18 17H6v-2h12v2zm0-4H6v-2h12v2zm0-4H6V7h12v2zM3 22l1.5-1.5L6 22l1.5-1.5L9 22l1.5-1.5L12 22l1.5-1.5L15 22l1.5-1.5L18 22l1.5-1.5L21 22V2l-1.5 1.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2 4.5 3.5 3 2v20z',
		records: 'M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z',
		clock: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z'
	};
	function icon(name, cls) {
		var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		s.setAttribute('viewBox', '0 0 24 24');
		s.setAttribute('aria-hidden', 'true');
		s.setAttribute('class', 'flxc-i' + (cls ? ' ' + cls : ''));
		var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		p.setAttribute('d', P[name] || '');
		s.appendChild(p);
		return s;
	}
	function btn(name, title, onclick, cls) {
		return h('button', { type: 'button', class: 'flxc-ib' + (cls ? ' ' + cls : ''), title: title, 'aria-label': title, onclick: onclick }, icon(name));
	}
	function ticks(status) {
		var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		s.setAttribute('class', 'flxc-tick flxc-tick-' + (status || 'sent'));
		s.setAttribute('aria-label', statusLabel(status));
		if (status === 'pending') {
			s.setAttribute('viewBox', '0 0 16 16');
			s.innerHTML = '<circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 4.6V8l2.2 1.4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>';
		} else if (status === 'failed') {
			s.setAttribute('viewBox', '0 0 16 16');
			s.innerHTML = '<circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M8 4.2v4.6M8 10.9v.9" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>';
		} else {
			s.setAttribute('viewBox', '0 0 18 11');
			var dbl = status === 'delivered' || status === 'read' || status === 'played';
			s.innerHTML = '<path d="M1.2 5.9l3.1 3.1L11.4 1.3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
				(dbl ? '<path d="M7.6 8.4l.6.6L15.4 1.3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' : '');
		}
		return s;
	}
	function statusLabel(s) {
		return { pending: t('Gönderiliyor'), sent: t('Gönderildi'), delivered: t('İletildi'), read: t('Okundu'), played: t('Dinlendi'), failed: t('Gönderilemedi') }[s] || '';
	}

	/* ---------- tarih / saat ---------- */

	var LOC = B.locale || 'tr-TR';
	var fmtTime = new Intl.DateTimeFormat(LOC, { hour: '2-digit', minute: '2-digit' });
	var fmtDate = new Intl.DateTimeFormat(LOC, { day: '2-digit', month: '2-digit', year: 'numeric' });
	var fmtLong = new Intl.DateTimeFormat(LOC, { day: 'numeric', month: 'long', year: 'numeric' });
	var fmtWeekday = new Intl.DateTimeFormat(LOC, { weekday: 'long' });
	function dayStart(ts) { var d = new Date(ts); d.setHours(0, 0, 0, 0); return d.getTime(); }
	function daysAgo(ts) { return Math.round((dayStart(Date.now()) - dayStart(ts)) / 86400000); }
	function listTime(ts) {
		if (!ts) return '';
		var d = daysAgo(ts);
		if (d === 0) return fmtTime.format(ts);
		if (d === 1) return t('Dün');
		if (d < 7) return fmtWeekday.format(ts);
		return fmtDate.format(ts);
	}
	function dayLabel(ts) {
		var d = daysAgo(ts);
		if (d === 0) return t('Bugün');
		if (d === 1) return t('Dün');
		if (d < 7) return fmtWeekday.format(ts);
		return fmtLong.format(ts);
	}
	function fmtSize(n) {
		if (!n) return '';
		var u = ['B', 'KB', 'MB', 'GB'], i = 0;
		while (n >= 1024 && i < 3) { n /= 1024; i++; }
		return (i ? n.toLocaleString(LOC, { maximumFractionDigits: 1 }) : n) + ' ' + u[i];
	}
	function fmtSecs(s) {
		s = Math.max(0, Math.round(s || 0));
		return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
	}

	/* WhatsApp biçimlendirmesi: *kalın* _italik_ ~üstü çizili~ ```kod``` ve linkler. Önce kaçış, sonra biçim. */
	function waFormat(text) {
		var s = esc(text);
		var codes = [];
		s = s.replace(/```([\s\S]+?)```/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
		s = s.replace(/(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?*_~])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
		s = s.replace(/(^|[\s(>])\*(?!\s)([^*\n]+?)\*(?=$|[\s).,!?:;<])/g, '$1<strong>$2</strong>');
		s = s.replace(/(^|[\s(>])_(?!\s)([^_\n]+?)_(?=$|[\s).,!?:;<])/g, '$1<em>$2</em>');
		s = s.replace(/(^|[\s(>])~(?!\s)([^~\n]+?)~(?=$|[\s).,!?:;<])/g, '$1<del>$2</del>');
		s = s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + codes[+i] + '</code>'; });
		return s.replace(/\n/g, '<br>');
	}

	function initials(name) {
		var parts = String(name || '?').replace(/[^\p{L}\p{N}\s]/gu, '').trim().split(/\s+/);
		var s = (parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '');
		return s.toLocaleUpperCase(LOC) || '#';
	}
	var AV_COLORS = ['#25a18e', '#5b8def', '#e57373', '#f0a04b', '#9c6ade', '#26a69a', '#ec7aa3', '#7cb342', '#ef8354', '#4fa3d1'];
	function avatar(chat, size) {
		var wrap = h('span', { class: 'flxc-av' + (size ? ' flxc-av-' + size : '') });
		var name = chat.title || chat.name || '';
		var col = AV_COLORS[Math.abs(hash(String(chat.id || name))) % AV_COLORS.length];
		var fallback = function () {
			clear(wrap);
			wrap.style.background = col;
			if (chat.unknownName && !chat.pushName) wrap.appendChild(icon('person'));
			else wrap.appendChild(h('span', { text: initials(name) }));
		};
		if (chat.avatar) {
			var img = h('img', { src: chat.avatar, alt: '', loading: 'lazy', referrerpolicy: 'no-referrer' });
			img.onerror = fallback;
			wrap.appendChild(img);
		} else fallback();
		return wrap;
	}
	/* 1.37.0: kanal (WhatsApp, site sohbeti, ileride Instagram, e-posta…). Birden çok kanal açıkken avatarda kanal ikonu görünür. */
	function chanById(id) {
		for (var i = 0; i < S.channels.length; i++) if (S.channels[i].id === id) return S.channels[i];
		return null;
	}
	function activeChannels() { return S.channels.filter(function (x) { return x.active; }); }
	function multiChannel() { return activeChannels().length > 1; }
	function chanIcon(ch, cls) {
		var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		s.setAttribute('viewBox', '0 0 24 24');
		s.setAttribute('aria-hidden', 'true');
		s.setAttribute('class', 'flxc-chi' + (cls ? ' ' + cls : ''));
		s.innerHTML = ch && ch.icon ? ch.icon : '';
		return s;
	}
	function avatarCh(chat, size) {
		var ch = chanById(chat.channel || 'whatsapp');
		var av = avatar(chat, size);
		if (!ch || (!multiChannel() && (chat.channel || 'whatsapp') === 'whatsapp')) return av;
		return h('span', { class: 'flxc-avw' }, av, h('span', { class: 'flxc-chb', title: ch.label, style: { '--chc': ch.color } }, chanIcon(ch)));
	}
	function hash(s) { var x = 0; for (var i = 0; i < s.length; i++) { x = ((x << 5) - x + s.charCodeAt(i)) | 0; } return x; }

	/* ---------- API ---------- */

	var nonce = B.nonce;
	function url(path, q) {
		var u = B.rest + String(path).replace(/^\//, '');
		if (q) {
			var qs = new URLSearchParams();
			Object.keys(q).forEach(function (k) { if (q[k] !== undefined && q[k] !== null && q[k] !== '') qs.append(k, q[k]); });
			var str = qs.toString();
			if (str) u += (u.indexOf('?') > -1 ? '&' : '?') + str;
		}
		return u;
	}
	function api(path, opts) {
		opts = opts || {};
		var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } };
		if (opts.timeout && window.AbortController) {
			var ac = new AbortController();
			setTimeout(function () { ac.abort(); }, opts.timeout);
			init.signal = ac.signal;
		}
		if (opts.form) init.body = opts.form;
		else if (opts.body) { init.body = JSON.stringify(opts.body); init.headers['Content-Type'] = 'application/json'; }
		var u = url(path, opts.query);
		// Sunucu önbelleği (LiteSpeed) eski veri vermesin.
		if (init.method === 'GET') u += (u.indexOf('?') < 0 ? '?' : '&') + '_t=' + Date.now();
		return fetch(u, init).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) {
					var e = new Error((j && j.message) || t('Bağlantı hatası'));
					e.status = r.status; e.code = j && j.code;
					throw e;
				}
				return j;
			});
		});
	}

	/* ---------- durum ---------- */

	var S = {
		me: {}, agents: [], labels: [], conns: [], channels: [], replies: [], ai: null,
		chats: new Map(), order: [], filter: store('filter') || 'all', label: '', channel: '', q: '',
		active: 0, msgs: new Map(), rev: 0, unread: 0, quoted: null, note: false, att: [],
		info: false, loadingList: false, listMore: false, notified: new Set(), sections: {}, fresh: {}, delOpen: new Set(), delHours: 60
	};

	/* ---------- iskelet ---------- */

	var theme = store('theme') || 'auto';
	function applyTheme() {
		root.setAttribute('data-theme', theme);
		document.documentElement.classList.toggle('flxc-dark-doc', theme === 'dark' || (theme === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches));
	}
	applyTheme();
	if (B.accent) root.style.setProperty('--flxc-accent', B.accent);

	var el = {};
	function buildShell() {
		clear(root);
		el.side = h('aside', { class: 'flxc-side' });
		el.main = h('main', { class: 'flxc-main' });
		el.info = h('section', { class: 'flxc-info', 'aria-hidden': 'true' });
		el.toast = h('div', { class: 'flxc-toasts', 'aria-live': 'polite' });
		root.appendChild(el.side);
		root.appendChild(el.main);
		root.appendChild(el.info);
		root.appendChild(el.toast);

		// Sol: başlık
		el.meAv = h('span', { class: 'flxc-me' });
		el.connDot = h('span', { class: 'flxc-conn-dot' });
		var head = h('header', { class: 'flxc-side-head' },
			h('div', { class: 'flxc-brand' }, el.meAv,
				h('div', { class: 'flxc-brand-t' },
					h('strong', { text: B.brand || t('Çağrı Merkezi') }),
					h('span', { class: 'flxc-brand-s' }, el.connDot, el.connText = h('span', { text: '' }))
				)
			),
			el.sideActs = h('div', { class: 'flxc-head-actions' },
				el.recsB = btn('records', t('Kayıtlar'), function () { openRecords(); }, 'flxc-recs-b'),
				btn('newchat', t('Yeni sohbet'), openNewChat),
				btn('bolt', t('Hazır cevaplar'), function () { openRepliesManager(); }),
				btn('more', t('Menü'), function (e) { sideMenu(e.currentTarget); })
			)
		);
		el.recsB.hidden = true;
		el.connWarn = h('div', { class: 'flxc-banner', hidden: true });
		el.search = h('input', { type: 'search', class: 'flxc-search-in', placeholder: t('Ara veya yeni sohbet başlat'), 'aria-label': t('Ara'), autocomplete: 'off' });
		el.search.addEventListener('input', debounce(function () { S.q = el.search.value.trim(); loadChats(); }, 280));
		el.search.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				var digits = el.search.value.replace(/\D+/g, '');
				if (digits.length >= 10 && !S.order.length) openNewChat(el.search.value);
			}
		});
		el.filters = h('div', { class: 'flxc-filters', role: 'tablist' });
		el.list = h('div', { class: 'flxc-list', role: 'list' });
		el.list.addEventListener('scroll', function () {
			if (el.list.clientHeight) S.listTop = el.list.scrollTop;
			if (S.listMore && !S.loadingList && el.list.scrollTop + el.list.clientHeight > el.list.scrollHeight - 200) loadChats(true);
		});
		el.side.appendChild(head);
		el.side.appendChild(el.connWarn);
		el.side.appendChild(h('div', { class: 'flxc-search' }, icon('search', 'flxc-search-i'), el.search));
		el.side.appendChild(el.filters);
		el.side.appendChild(el.list);

		renderEmptyMain();
		renderFilters();
		renderMe();
	}

	function renderSideActions() {
		if (el.recsB) el.recsB.hidden = !(S.rec && S.rec.search);
	}

	function renderMe() {
		clear(el.meAv).appendChild(avatar({ id: 'me' + S.me.id, title: S.me.name || '?' }, 'sm'));
	}

	function renderConn() {
		var conns = S.conns || [];
		var open = conns.filter(function (c) { return c.state === 'open'; }).length;
		var state = !conns.length ? 'none' : (open === conns.length ? 'open' : (open ? 'partial' : (conns.some(function (c) { return c.state; }) ? 'close' : 'unknown')));
		el.connDot.className = 'flxc-conn-dot flxc-conn-' + state;
		var others = activeChannels().filter(function (x) { return x.id !== 'whatsapp'; });
		if (state === 'none' && others.length) {
			// WhatsApp kurulmamış ama başka kanal açık: gelen kutusu çalışıyor, uyarı gösterilmez.
			el.connDot.className = 'flxc-conn-dot flxc-conn-open';
			el.connText.textContent = others.map(function (x) { return x.label; }).join(', ');
			clear(el.connWarn);
			el.connWarn.hidden = true;
			return;
		}
		var txt = {
			none: t('Bağlantı yok'),
			open: conns.length > 1 ? t('{n} hat bağlı', { n: open }) : t('WhatsApp bağlı'),
			partial: t('{n}/{m} hat bağlı', { n: open, m: conns.length }),
			close: t('WhatsApp bağlı değil'),
			unknown: t('Durum bilinmiyor')
		}[state];
		el.connText.textContent = txt + (state === 'open' && others.length ? ' · ' + others.map(function (x) { return x.label; }).join(', ') : '');
		clear(el.connWarn);
		if (state === 'none' || state === 'close') {
			el.connWarn.hidden = false;
			el.connWarn.appendChild(icon('warn'));
			el.connWarn.appendChild(h('div', null,
				h('strong', { text: state === 'none' ? t('Henüz iletişim kanalı kurulmadı') : t('WhatsApp bağlantısı koptu') }),
				h('span', { text: state === 'none' ? t('Mesaj almak için iletişim ayarlarından bir kanal kurun.') : t('Telefon çevrimdışı olabilir ya da QR yeniden okutulmalı.') }),
				S.me.manage ? h('a', { href: B.settingsUrl, text: state === 'none' ? t('İletişim ayarlarına git') : t('Ayarlara git') }) : null
			));
		} else el.connWarn.hidden = true;
	}

	var FILTERS = [['all', 'Tümü'], ['unread', 'Okunmamış'], ['mine', 'Bana atanan'], ['unassigned', 'Atanmamış'], ['ai', 'Asistan'], ['closed', 'Kapalı']];
	function renderFilters() {
		clear(el.filters);
		FILTERS.forEach(function (f) {
			el.filters.appendChild(h('button', {
				type: 'button', role: 'tab', class: 'flxc-chip' + (S.filter === f[0] && !S.label ? ' is-on' : ''), 'aria-selected': S.filter === f[0] && !S.label ? 'true' : 'false',
				onclick: function () { S.filter = f[0]; S.label = ''; store('filter', f[0]); renderFilters(); loadChats(); }
			}, t(f[1])));
		});
		if (multiChannel()) activeChannels().forEach(function (ch) {
			el.filters.appendChild(h('button', {
				type: 'button', class: 'flxc-chip flxc-chip-ch' + (S.channel === ch.id ? ' is-on' : ''), style: { '--chc': ch.color }, 'aria-pressed': S.channel === ch.id ? 'true' : 'false',
				onclick: function () { S.channel = S.channel === ch.id ? '' : ch.id; renderFilters(); loadChats(); }
			}, chanIcon(ch), ch.label));
		});
		S.labels.forEach(function (l) {
			el.filters.appendChild(h('button', {
				type: 'button', class: 'flxc-chip flxc-chip-label' + (S.label === l.id ? ' is-on' : ''), style: { '--lc': l.color },
				onclick: function () { S.label = S.label === l.id ? '' : l.id; renderFilters(); loadChats(); }
			}, h('i', { class: 'flxc-dot' }), l.name));
		});
	}

	function renderEmptyMain() {
		clear(el.main);
		el.main.classList.remove('has-chat');
		el.main.appendChild(h('div', { class: 'flxc-empty' },
			h('div', { class: 'flxc-empty-art', html: EMPTY_ART }),
			h('h2', { text: B.brand ? B.brand : t('Çağrı Merkezi') }),
			h('p', { text: multiChannel() ? t('WhatsApp, site sohbeti ve diğer kanallardan gelen tüm mesajlar burada canlı görünür. Soldan bir sohbet seçin.') : t('Gelen WhatsApp mesajları burada canlı görünür. Soldan bir sohbet seçin ya da yeni bir sohbet başlatın.') }),
			h('button', { type: 'button', class: 'flxc-btn', onclick: function () { openNewChat(); } }, icon('newchat'), t('Yeni sohbet')),
			h('p', { class: 'flxc-empty-foot' }, icon('lock'), t('Mesajlar yalnız yetkili temsilcilere görünür.'))
		));
	}
	var EMPTY_ART = '<svg viewBox="0 0 220 160" aria-hidden="true"><defs><linearGradient id="flxcg" x1="0" x2="1"><stop offset="0" stop-color="var(--flxc-accent)" stop-opacity=".18"/><stop offset="1" stop-color="var(--flxc-accent)" stop-opacity=".05"/></linearGradient></defs><circle cx="110" cy="80" r="74" fill="url(#flxcg)"/><rect x="48" y="40" width="96" height="58" rx="14" fill="var(--flxc-bubble-in)" stroke="var(--flxc-line)"/><path d="M62 98l-6 16 20-16z" fill="var(--flxc-bubble-in)"/><rect x="62" y="56" width="60" height="7" rx="3.5" fill="var(--flxc-muted)" opacity=".35"/><rect x="62" y="70" width="42" height="7" rx="3.5" fill="var(--flxc-muted)" opacity=".25"/><rect x="92" y="78" width="84" height="48" rx="14" fill="var(--flxc-bubble-out)"/><path d="M162 126l8 14-22-14z" fill="var(--flxc-bubble-out)"/><rect x="104" y="92" width="52" height="7" rx="3.5" fill="var(--flxc-accent)" opacity=".45"/><path d="M146 110l4 4 8-8M152 114l1 0 8-8" stroke="#53bdeb" stroke-width="2.2" fill="none" stroke-linecap="round"/><path d="M178 44c8-10 22-8 22-8s0 14-10 20c-6 3-12 0-12-12z" fill="var(--flxc-accent)" opacity=".55"/><path d="M178 56c4-6 8-10 14-14" stroke="var(--flxc-accent)" stroke-width="2" fill="none"/></svg>';

	/* ---------- sohbet listesi ---------- */

	function upsertChat(c) {
		var old = S.chats.get(c.id);
		S.chats.set(c.id, old ? Object.assign(old, c) : c);
	}

	function chatMatchesView(c) {
		if (S.q) return true; // arama sonuçlarını sunucu belirler
		if (S.label && c.labels.indexOf(S.label) < 0) return false;
		if (S.channel && (c.channel || 'whatsapp') !== S.channel) return false;
		switch (S.filter) {
			case 'unread': return c.unread > 0 && c.status !== 'closed';
			case 'mine': return c.assigned === S.me.id && c.status !== 'closed';
			case 'unassigned': return !c.assigned && c.status !== 'closed';
			case 'closed': return c.status === 'closed';
			case 'ai': return !!c.ai && c.status !== 'closed';
			default: return c.status !== 'closed';
		}
	}

	function sortOrder() {
		S.order.sort(function (a, b) {
			var x = S.chats.get(a), y = S.chats.get(b);
			if (!x || !y) return 0;
			if (x.pinned !== y.pinned) return y.pinned - x.pinned;
			return (y.lastTs - x.lastTs) || (b - a);
		});
	}

	function loadChats(more) {
		S.loadingList = true;
		if (!more) el.list.classList.add('is-loading');
		var offset = more ? S.order.length : 0;
		return api('chats', { query: { filter: S.filter, q: S.q, label: S.label, channel: S.channel, offset: offset, limit: 50 } }).then(function (r) {
			if (!more) S.order = [];
			r.chats.forEach(function (c) { upsertChat(c); if (S.order.indexOf(c.id) < 0) S.order.push(c.id); });
			S.listMore = r.chats.length >= 50;
			renderList();
		}).catch(function (e) { toast(e.message, 'err'); }).then(function () {
			S.loadingList = false;
			el.list.classList.remove('is-loading');
		});
	}

	function renderList() {
		var frag = document.createDocumentFragment();
		if (!S.order.length) {
			var digits = S.q.replace(/\D+/g, '');
			frag.appendChild(h('div', { class: 'flxc-list-empty' },
				h('p', { text: S.q ? t('Sonuç bulunamadı.') : (S.filter === 'unread' ? t('Okunmamış mesaj yok. 🎉') : t('Henüz sohbet yok.')) }),
				digits.length >= 10 ? h('button', { type: 'button', class: 'flxc-btn flxc-btn-sm', onclick: function () { openNewChat(S.q); } }, icon('newchat'), t('{p} ile sohbet başlat', { p: S.q })) : null
			));
		}
		S.order.forEach(function (id) {
			var c = S.chats.get(id);
			if (c) frag.appendChild(chatRow(c));
		});
		// Liste yeniden çizilirken kaydırma konumu korunur (sohbet seçince başa atlamasın).
		var top = el.list.clientHeight ? el.list.scrollTop : (S.listTop || 0);
		clear(el.list).appendChild(frag);
		if (top) el.list.scrollTop = top;
	}

	function chatRow(c) {
		var last = c.last;
		var prev = h('span', { class: 'flxc-row-prev' });
		if (c.typing) {
			prev.classList.add('is-typing');
			prev.textContent = c.typing === 2 ? t('ses kaydediyor…') : t('yazıyor…');
		} else if (c.botBusy) {
			prev.classList.add('is-typing');
			prev.textContent = t('Asistan yazıyor…');
		} else if (last) {
			if (last.dir === 'out') prev.appendChild(ticks(last.status));
			if (last.dir === 'out' && last.user && S.agents.length > 1) prev.appendChild(h('b', { text: last.user.split(' ')[0] + ': ' }));
			var tIcon = { image: 'image', video: 'video', audio: 'mic', document: 'doc', location: 'loc', contact: 'person' }[last.type];
			if (tIcon) prev.appendChild(icon(tIcon, 'flxc-prev-i'));
			prev.appendChild(document.createTextNode(last.body));
		} else {
			prev.textContent = c.phoneFmt;
		}
		var badges = h('span', { class: 'flxc-row-badges' });
		if (c.pinned) badges.appendChild(icon('pin', 'flxc-pin'));
		if (c.assigned) {
			var ag = agentName(c.assigned);
			if (ag) badges.appendChild(h('span', { class: 'flxc-row-ag' + (c.assigned === S.me.id ? ' is-me' : ''), title: t('Atanan: {n}', { n: ag }), text: initials(ag) }));
		}
		if (c.unread) badges.appendChild(h('span', { class: 'flxc-badge', text: c.unread > 99 ? '99+' : String(c.unread) }));
		var labels = h('span', { class: 'flxc-row-labels' });
		c.labels.forEach(function (id) {
			var l = labelById(id);
			if (l) labels.appendChild(h('i', { class: 'flxc-dot', style: { '--lc': l.color }, title: l.name }));
		});
		var row = h('div', {
			class: 'flxc-row' + (c.id === S.active ? ' is-active' : '') + (c.unread ? ' is-unread' : '') + (S.fresh[c.id] && Date.now() - S.fresh[c.id] < 1500 ? ' is-new' : '') + (c.status === 'closed' ? ' is-closed' : ''),
			role: 'listitem', tabindex: '0', 'data-id': c.id,
			onclick: function () { openChat(c.id); },
			onkeydown: function (e) { if (e.key === 'Enter') openChat(c.id); }
		},
			avatarCh(c),
			h('div', { class: 'flxc-row-main' },
				h('div', { class: 'flxc-row-top' },
					h('span', { class: 'flxc-row-name' }, h('span', { text: c.title }), labels),
					h('span', { class: 'flxc-row-time', text: listTime(c.lastTs) })
				),
				h('div', { class: 'flxc-row-bot' }, prev, badges)
			),
			h('button', {
				type: 'button', class: 'flxc-row-menu', 'aria-label': t('Sohbet menüsü'),
				onclick: function (e) { e.stopPropagation(); chatMenu(c, e.currentTarget); }
			}, icon('down'))
		);
		longPress(row, function () { chatMenu(c, row); });
		return row;
	}

	function agentName(id) {
		for (var i = 0; i < S.agents.length; i++) if (S.agents[i].id === id) return S.agents[i].name;
		return '';
	}
	function labelById(id) {
		for (var i = 0; i < S.labels.length; i++) if (S.labels[i].id === id) return S.labels[i];
		return null;
	}

	function longPress(node, fn) {
		var tm, moved;
		node.addEventListener('touchstart', function () { moved = false; tm = setTimeout(function () { if (!moved) { fn(); if (navigator.vibrate) navigator.vibrate(15); } }, 520); }, { passive: true });
		node.addEventListener('touchmove', function () { moved = true; clearTimeout(tm); }, { passive: true });
		node.addEventListener('touchend', function () { clearTimeout(tm); });
		node.addEventListener('contextmenu', function (e) { if (matchMedia('(hover: none)').matches) e.preventDefault(); });
	}

	/* ---------- açılır menüler ---------- */

	var openMenuEl = null, openMenuAnchor = null;
	function closeMenu() {
		if (openMenuEl) { openMenuEl.remove(); openMenuEl = null; openMenuAnchor = null; }
	}
	function menu(anchor, items, opts) {
		closeMenu();
		opts = opts || {};
		var m = h('div', { class: 'flxc-menu' + (opts.cls ? ' ' + opts.cls : ''), role: 'menu' });
		items.forEach(function (it) {
			if (!it) return;
			if (it.sep) { m.appendChild(h('hr')); return; }
			if (it.node) { m.appendChild(it.node); return; }
			m.appendChild(h('button', {
				type: 'button', role: 'menuitem', class: 'flxc-menu-i' + (it.danger ? ' is-danger' : '') + (it.on ? ' is-on' : ''),
				onclick: function (e) { e.stopPropagation(); closeMenu(); it.fn(); }
			}, it.icon ? icon(it.icon) : null, h('span', { text: it.label }), it.on ? icon('check', 'flxc-menu-check') : null));
		});
		root.appendChild(m);
		// 1.46.0: menü uygulama kutusuna göre konumlanır (önceden ekran koordinatı kullanılıyordu; sol menü kadar sağa kayıyordu).
		// opts.at = {x, y}: sağ tık / tıklanan nokta; menü oranın yanında açılır, kutudan taşmaz.
		var rr = root.getBoundingClientRect(), ra = anchor.getBoundingClientRect();
		var r = opts.at ? { left: opts.at.x, right: opts.at.x, top: opts.at.y, bottom: opts.at.y } : ra;
		r = { left: r.left - rr.left, right: r.right - rr.left, top: r.top - rr.top, bottom: r.bottom - rr.top };
		var mw = m.offsetWidth, mh = m.offsetHeight;
		var left = opts.at ? r.left : r.right - mw;
		if (opts.alignLeft) left = r.left;
		if (opts.at && left + mw > rr.width - 8) left = r.left - mw;
		left = Math.min(Math.max(8, left), rr.width - mw - 8);
		var top = r.bottom + (opts.at ? 2 : 4);
		if (top + mh > rr.height - 8) top = r.top - mh - (opts.at ? 2 : 4);
		top = Math.min(Math.max(8, top), Math.max(8, rr.height - mh - 8));
		m.style.left = left + 'px';
		m.style.top = top + 'px';
		openMenuEl = m;
		openMenuAnchor = anchor;
		setTimeout(function () { var f = m.querySelector('button'); if (f && !opts.noFocus) f.focus({ preventScroll: true }); }, 0);
		return m;
	}
	document.addEventListener('click', function (e) { if (openMenuEl && !openMenuEl.contains(e.target) && !(openMenuAnchor && openMenuAnchor.contains(e.target))) closeMenu(); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			if (openMenuEl) { closeMenu(); return; }
			if (closePopovers()) return;
			if (S.quoted) { setQuoted(null); return; }
			if (S.note) { setNoteMode(false); return; }
		}
	});

	function sideMenu(anchor) {
		var items = [
			{ icon: 'newchat', label: t('Yeni sohbet'), fn: function () { openNewChat(); } },
			{ icon: 'bolt', label: t('Hazır cevaplar'), fn: function () { openRepliesManager(); } },
			S.rec && S.rec.search ? { icon: 'records', label: t('Kayıtlar'), fn: function () { openRecords(); } } : null,
			{ icon: 'bolt', label: store('chips') === '0' ? t('Hazır cevap baloncuklarını göster') : t('Hazır cevap baloncuklarını gizle'), fn: function () { store('chips', store('chips') === '0' ? '1' : '0'); renderChips(); } },
			{ sep: 1 },
			{ icon: 'moon', label: t('Tema: Otomatik'), on: theme === 'auto', fn: function () { setTheme('auto'); } },
			{ icon: 'moon', label: t('Tema: Açık'), on: theme === 'light', fn: function () { setTheme('light'); } },
			{ icon: 'moon', label: t('Tema: Koyu'), on: theme === 'dark', fn: function () { setTheme('dark'); } },
			{ sep: 1 },
			('Notification' in window && Notification.permission !== 'granted') ? { icon: 'bell', label: t('Masaüstü bildirimlerini aç'), fn: askNotify } : null,
			{ icon: 'bell', label: soundOn() ? t('Bildirim sesini kapat') : t('Bildirim sesini aç'), fn: function () { store('sound', soundOn() ? '0' : '1'); toast(soundOn() ? t('Bildirim sesi açık') : t('Bildirim sesi kapalı')); } },
			S.me.manage ? { icon: 'download', label: t('Sohbetleri dışa aktar'), fn: function () { openExport(null); } } : null,
			S.me.manage ? { icon: 'alarm', label: t('Hatırlatıcılar'), fn: function () { openReminders(null); } } : null,
			S.me.manage ? { icon: 'block', label: t('Kara liste'), fn: function () { openBlocklist(); } } : null,
			S.me.manage ? { icon: 'gear', label: t('Ayarlar'), fn: function () { location.href = B.settingsUrl; } } : null,
			{ icon: 'exit', label: t('WordPress paneline dön'), fn: function () { location.href = B.adminUrl; } }
		];
		menu(anchor, items);
	}
	function setTheme(v) { theme = v; store('theme', v); applyTheme(); }

	function chatMenu(c, anchor) {
		menu(anchor, [
			{ icon: 'person', label: t('Kişi bilgisi'), fn: function () { openChat(c.id).then(function () { openInfo(); }); } },
			{ icon: 'pin', label: c.pinned ? t('Sabitlemeyi kaldır') : t('Sabitle'), fn: function () { patchChat(c.id, { pinned: c.pinned ? 0 : 1 }); } },
			c.unread ? null : { icon: 'unread', label: t('Okunmadı olarak işaretle'), fn: function () { patchChat(c.id, { unread: 1 }); } },
			{ icon: 'assign', label: c.assigned === S.me.id ? t('Atamamı kaldır') : t('Bana ata'), fn: function () { patchChat(c.id, { assigned: c.assigned === S.me.id ? 0 : S.me.id }); } },
			{ sep: 1 },
			S.rec.donations ? { icon: 'gift', label: t('Bağış kaydı oluştur'), fn: function () { openChat(c.id).then(function () { openDonation({ chat: c.id }); }); } } : null,
			recOn() && !c.group ? { icon: 'records', label: t('Bağışlar ve sertifikalar'), fn: function () { openChat(c.id).then(function () { openPersonRecords(c); }); } } : null,
			recOn() ? { sep: 1 } : null,
			{ icon: 'download', label: t('Sohbeti dışa aktar'), fn: function () { openExport(c); } },
			c.status === 'closed'
				? { icon: 'reopen', label: t('Sohbeti yeniden aç'), fn: function () { patchChat(c.id, { status: 'open' }); } }
				: { icon: 'done', label: t('Sohbeti kapat'), fn: function () { patchChat(c.id, { status: 'closed' }); } },
			!c.group ? { sep: 1 } : null,
			!c.group ? { icon: 'ai', label: t('Asistan kullanımı'), fn: function () { openUsage(c); } } : null,
			S.me.manage && !c.group ? { icon: 'memory', label: t('Kişi hafızası'), fn: function () { openMemory(c); } } : null,
			S.me.manage && !c.group ? { icon: 'alarm', label: t('Hatırlatıcılar'), fn: function () { openReminders(c); } } : null,
			S.me.manage && !c.group ? (c.optout
				? { icon: 'block', label: t('Kara listeden çıkar'), fn: function () { unblockChat(c); } }
				: { icon: 'block', danger: true, label: t('Kara listeye al (mesaj gönderme)'), fn: function () { openBlockChat(c); } }) : null,
			S.me.manage ? { icon: 'trash', danger: true, label: t('Sohbet geçmişini temizle'), fn: function () { openClearHistory(c); } } : null
		]);
	}

	/* Dışa aktarma: tek sohbet ya da (yönetici) toplu; .md yapay zekâya vermek için, .json işlemek için. */
	function openExport(c) {
		var fmt = sel([['md', t('Metin (.md)')], ['json', 'JSON']], store('exp_fmt') || 'md');
		var days = c ? null : sel([['7', t('Son 7 gün')], ['30', t('Son 30 gün')], ['90', t('Son 90 gün')], ['0', t('Tümü')]], store('exp_days') || '30');
		var who = c ? null : sel([['all', t('Tüm sohbetler')], ['bot', t('Asistanın yazdığı sohbetler')], ['team', t('Temsilcinin yazdığı sohbetler')]], store('exp_who') || 'all');
		var mask = check(t('Telefon ve e-postaları maskele'), store('exp_mask') !== '0');
		var notes = check(t('Dahili notları ekle'), store('exp_notes') !== '0');
		var go = h('button', { type: 'button', class: 'flxc-btn flxc-btn-block' }, icon('download'), t('İndir'));
		go.addEventListener('click', function () {
			var q = { format: fmt.value, mask: mask.input.checked ? 1 : 0, notes: notes.input.checked ? 1 : 0 };
			if (c) q.chat = c.id; else { q.days = days.value; q.who = who.value; store('exp_days', days.value); store('exp_who', who.value); }
			store('exp_fmt', fmt.value); store('exp_mask', q.mask ? '1' : '0'); store('exp_notes', q.notes ? '1' : '0');
			var u = B.exportUrl;
			Object.keys(q).forEach(function (k) { u += '&' + k + '=' + encodeURIComponent(q[k]); });
			var a = h('a', { href: u, download: '' });
			root.appendChild(a); a.click(); a.remove();
			toast(t('İndirme başladı'));
			dlg.close();
		});
		var dlg = modal({
			cls: 'flxc-exp', title: c ? t('Sohbeti dışa aktar') : t('Sohbetleri dışa aktar'),
			body: [
				c ? h('p', { class: 'flxc-help', text: c.title }) : null,
				days ? fld(t('Son mesajı şu aralıkta olanlar'), days) : null,
				who ? fld(t('Hangi sohbetler?'), who) : null,
				fld(t('Biçim'), fmt),
				mask, notes,
				go,
				h('p', { class: 'flxc-help', text: c ? t('Mesajlar saat ve yazanla (kişi, asistan, temsilci, otomatik) iner; dekont kontrol sonucu da eklenir.') : t('En yeni 1000 sohbet iner, gruplar hariç. Mesajlar saat ve yazanla (kişi, asistan, temsilci, otomatik) yer alır.') })
			]
		});
	}

	/* Yalnız yönetici: bizdeki mesajlar ve medya silinir; sohbet, kişi bilgisi ve notlar kalır. */
	function openClearHistory(c) {
		var word = t('TEMİZLE');
		var inp = h('input', { type: 'text', class: 'flxc-field', autocomplete: 'off', placeholder: word });
		var keep = check(t('Bağış kayıtlarına eklenmiş dekontları sakla'), true);
		var forget = check(t('Kişi hafızasını da sil'), false);
		var go = h('button', { type: 'button', class: 'flxc-btn flxc-btn-block flxc-btn-danger', disabled: true }, icon('trash'), t('Geçmişi kalıcı olarak sil'));
		var norm = function (v) { return String(v || '').trim().toLocaleUpperCase(LOC); };
		inp.addEventListener('input', function () { go.disabled = norm(inp.value) !== norm(word); });
		go.addEventListener('click', function () {
			if (go.disabled) return;
			go.disabled = true;
			api('chats/' + c.id + '/clear', { method: 'POST', body: { confirm: 'yes', keep_receipts: keep.input.checked ? 1 : 0, forget_memory: forget.input.checked ? 1 : 0 } }).then(function (r) {
				S.msgs.delete(c.id);
				upsertChat(r.chat);
				renderList();
				dlg.close();
				if (S.active === c.id) { S.active = null; openChat(c.id); }
				toast(t('{n} mesaj silindi', { n: r.deleted }));
			}).catch(function (e) { go.disabled = false; toast(e.message, 'err'); });
		});
		var dlg = modal({
			cls: 'flxc-exp', title: t('Sohbet geçmişini temizle'),
			body: [
				h('p', { class: 'flxc-warn', text: t('{c} sohbetindeki tüm mesajlar ve dosyalar bu panelden kalıcı olarak silinir. Kişinin telefonundaki ve bağlı WhatsApp telefonundaki mesajlar silinmez. Geri alınamaz.', { c: c.title }) }),
				h('p', { class: 'flxc-help', text: t('Evolution sunucusundaki kopya bir daha içe aktarılmaz; asistan bu sohbette sıfırdan başlar. İsterseniz önce sohbeti dışa aktarın.') }),
				keep, forget,
				fld(t('Onaylamak için {w} yazın', { w: word }), inp),
				go
			]
		});
		setTimeout(function () { inp.focus(); }, 50);
	}

	/* Yalnız yönetici: kişinin kalıcı bilgileri ve konuşma özetleri (asistan her cevapta okur). */
	var memFmt = new Intl.DateTimeFormat(LOC, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
	var MEM_SRC = { bot: t('asistan'), summary: t('özet'), agent: t('ekip') };
	function openMemory(c) {
		var M = null, list = h('div', { class: 'flxc-mem' }), info = h('p', { class: 'flxc-help' });
		var k = h('input', { type: 'text', class: 'flxc-field', placeholder: t('Anahtar (ör. eşinin adı)'), maxlength: '120' });
		var v = h('input', { type: 'text', class: 'flxc-field', placeholder: t('Değer'), maxlength: '1500' });
		var editing = 0;
		var save = h('button', { type: 'button', class: 'flxc-btn' }, t('Ekle'));
		function draw() {
			clear(list);
			info.textContent = M.enabled ? t('{n} kayıt · yaklaşık {t} token · asistan her cevapta okur', { n: M.items.length, t: M.tokens }) : t('Kişi hafızası ayarlardan kapalı; kayıtlar asistana gitmiyor.');
			if (M.job) info.textContent += ' · ' + t('Son özet: {s}', { s: M.job.status === 'done' ? memFmt.format(new Date(M.job.at)) : (M.job.status === 'queued' || M.job.status === 'running' ? t('sırada') : (M.job.note || M.job.status)) });
			if (!M.items.length) list.appendChild(h('div', { class: 'flxc-mem-empty', text: t('Bu kişi için kayıt yok.') }));
			M.items.forEach(function (it) {
				var sum = it.kind === 'summary';
				list.appendChild(h('div', { class: 'flxc-mem-i' + (sum ? ' is-sum' : '') },
					h('div', { class: 'flxc-mem-t' },
						h('strong', { text: sum ? t('Konuşma özeti') : it.key }),
						h('span', { class: 'flxc-help', text: memFmt.format(new Date(sum ? it.created : it.updated)) + ' · ' + (MEM_SRC[it.source] || it.source) })),
					h('div', { class: 'flxc-mem-v', text: it.value }),
					h('div', { class: 'flxc-mem-a' },
						h('button', { type: 'button', class: 'flxc-link', onclick: function () { editing = it.id; k.value = sum ? t('Konuşma özeti') : it.key; k.disabled = sum; v.value = it.value; save.textContent = t('Kaydet'); v.focus(); } }, t('Düzenle')),
						h('button', { type: 'button', class: 'flxc-link is-danger', onclick: function () {
							api('memory/item/' + it.id, { method: 'DELETE' }).then(function (r) { M = r; draw(); toast(t('Silindi')); }).catch(function (e) { toast(e.message, 'err'); });
						} }, t('Sil')))));
			});
		}
		save.addEventListener('click', function () {
			if (!k.value.trim() || !v.value.trim()) return;
			save.disabled = true;
			api('memory/chat/' + c.id, { method: 'POST', body: { item: editing || 0, key: k.value, value: v.value } }).then(function (r) {
				M = r; editing = 0; k.value = ''; v.value = ''; k.disabled = false; save.textContent = t('Ekle'); draw();
			}).catch(function (e) { toast(e.message, 'err'); }).then(function () { save.disabled = false; });
		});
		var sumBtn = h('button', { type: 'button', class: 'flxc-btn' }, t('Şimdi özetle'));
		sumBtn.addEventListener('click', function () {
			sumBtn.disabled = true;
			api('memory/chat/' + c.id + '/summarize', { method: 'POST' }).then(function (r) { M = r; draw(); toast(t('Özet sıraya alındı')); setTimeout(load, 8000); }).catch(function (e) { toast(e.message, 'err'); }).then(function () { sumBtn.disabled = false; });
		});
		var allBtn = h('button', { type: 'button', class: 'flxc-btn flxc-btn-danger' }, t('Tümünü sil'));
		allBtn.addEventListener('click', async function () {
			if (!(await window.flxDialog.confirm({ message: t('{c} için tüm hafıza kayıtları silinsin mi?', { c: c.title }), danger: true }))) return;
			api('memory/chat/' + c.id, { method: 'DELETE', query: { confirm: 'yes' } }).then(function (r) { M = r; draw(); }).catch(function (e) { toast(e.message, 'err'); });
		});
		function load() { api('memory/chat/' + c.id).then(function (r) { M = r; draw(); }).catch(function (e) { toast(e.message, 'err'); }); }
		modal({
			cls: 'flxc-exp flxc-mem-modal', title: t('Kişi hafızası'),
			body: [
				h('p', { class: 'flxc-help', text: c.title }), info, list,
				h('div', { class: 'flxc-mem-add' }, k, v, save),
				h('div', { class: 'flxc-mem-foot' }, sumBtn, allBtn),
				h('p', { class: 'flxc-help', text: t('Kişi isterse asistan bilgileri kendisi siler. Sohbet {h} saat sessiz kalınca konuşma özetlenir, önemli bilgiler tarih ve saatle buraya yazılır.', { h: (B.memHours || 24) }) })
			]
		});
		list.appendChild(h('div', { class: 'flxc-mem-empty', text: t('Yükleniyor…') }));
		load();
	}

	function openBlockChat(c) {
		var reason = h('input', { type: 'text', class: 'flxc-field', placeholder: t('Neden (ör. kişi mesaj istemedi)') });
		var go = h('button', { type: 'button', class: 'flxc-btn flxc-btn-block flxc-btn-danger' }, icon('block'), t('Kara listeye al'));
		go.addEventListener('click', function () {
			go.disabled = true;
			api('blocklist', { method: 'POST', body: { chat: c.id, reason: reason.value } }).then(function (r) {
				if (r.chat) { upsertChat(r.chat); renderList(); if (S.active === c.id) renderChatHead(); }
				dlg.close(); toast(t('Kara listeye alındı'));
			}).catch(function (e) { go.disabled = false; toast(e.message, 'err'); });
		});
		var dlg = modal({
			cls: 'flxc-exp', title: t('Kara listeye al'),
			body: [
				h('p', { class: 'flxc-warn', text: t('{c} kişisine bundan sonra WhatsApp, e-posta ve SMS gitmez; asistan bu kişiyle konuşmaz. Kişinin yazdıkları yine panele düşer.', { c: c.title }) }),
				fld(t('Neden'), reason), go
			]
		});
	}
	function unblockChat(c) {
		api('chats/' + c.id + '/unblock', { method: 'POST' }).then(function (r) {
			upsertChat(r.chat); renderList(); if (S.active === c.id) renderChatHead(); toast(t('Kara listeden çıkarıldı'));
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function openBlocklist() {
		var q = h('input', { type: 'search', class: 'flxc-field', placeholder: t('Telefon, e-posta ya da neden ara') });
		var list = h('div', { class: 'flxc-mem' });
		var ph = h('input', { type: 'text', class: 'flxc-field', placeholder: t('Telefon') });
		var em = h('input', { type: 'email', class: 'flxc-field', placeholder: t('E-posta') });
		var add = h('button', { type: 'button', class: 'flxc-btn' }, t('Ekle'));
		var tm;
		function load() {
			api('blocklist', { query: { q: q.value } }).then(function (r) {
				clear(list);
				if (!r.items.length) list.appendChild(h('div', { class: 'flxc-mem-empty', text: t('Kara liste boş.') }));
				r.items.forEach(function (it) {
					list.appendChild(h('div', { class: 'flxc-mem-i' },
						h('div', { class: 'flxc-mem-t' },
							h('strong', { text: [it.title, it.phone, it.email].filter(Boolean).join(' · ') || t('Gizli numara (WhatsApp)') }),
							h('span', { class: 'flxc-help', text: memFmt.format(new Date(it.at)) + ' · ' + (it.source === 'bot' ? t('asistan') : (it.by || t('ekip'))) })),
						it.reason ? h('div', { class: 'flxc-mem-v', text: it.reason }) : null,
						h('div', { class: 'flxc-mem-a' }, h('button', { type: 'button', class: 'flxc-link', onclick: function () {
							api('blocklist/' + it.id, { method: 'DELETE' }).then(function () { toast(t('Kara listeden çıkarıldı')); load(); syncSoon(); }).catch(function (e) { toast(e.message, 'err'); });
						} }, t('Listeden çıkar')))));
				});
				info.textContent = t('Toplam {n} kişi', { n: r.total });
			}).catch(function (e) { toast(e.message, 'err'); });
		}
		var info = h('p', { class: 'flxc-help' });
		q.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(load, 300); });
		add.addEventListener('click', function () {
			if (!ph.value.trim() && !em.value.trim()) return;
			api('blocklist', { method: 'POST', body: { phone: ph.value, email: em.value, reason: t('Elle eklendi') } }).then(function () { ph.value = ''; em.value = ''; load(); syncSoon(); }).catch(function (e) { toast(e.message, 'err'); });
		});
		modal({
			cls: 'flxc-exp flxc-mem-modal', title: t('Kara liste'),
			body: [
				h('p', { class: 'flxc-help', text: t('Bu kişilere WhatsApp, e-posta ve SMS gitmez, asistan konuşmaz. "Bana mesaj atmayın" diyen kişiyi asistan kendisi ekler. Listeden çıkarınca her şey yeniden çalışır.') }),
				q, info, list,
				h('div', { class: 'flxc-mem-add' }, ph, em, add)
			]
		});
		load();
	}
	function syncSoon() { setTimeout(sync, 300); }

	/* Kişilerin asistana kurdurduğu hatırlatıcılar: c verilirse yalnız o kişininkiler. */
	function openReminders(c) {
		var list = h('div', { class: 'flxc-mem' }), info = h('p', { class: 'flxc-help' });
		var ST = { active: t('Etkin'), paused: t('Durduruldu'), done: t('Bitti') };
		function load() {
			api('reminders', { query: c ? { chat: c.id } : {} }).then(function (r) {
				clear(list);
				info.textContent = r.enabled ? t('{n} hatırlatıcı', { n: r.items.length }) : t('Hatırlatıcılar ayarlardan kapalı; zamanı gelenler gönderilmez.');
				if (!r.items.length) list.appendChild(h('div', { class: 'flxc-mem-empty', text: t('Hatırlatıcı yok. Kişi asistana "her ay bana hatırlat" gibi bir şey yazınca burada görünür.') }));
				r.items.forEach(function (it) {
					var meta = [it.when, ST[it.status] || it.status];
					if (it.next) meta.push(t('Sonraki: {d}', { d: memFmt.format(new Date(it.next)) }));
					if (it.runs) meta.push(t('{n} kez gönderildi', { n: it.runs }));
					list.appendChild(h('div', { class: 'flxc-mem-i' },
						h('div', { class: 'flxc-mem-t' },
							h('strong', { text: (c ? '' : it.who + ' · ') + it.title }),
							h('span', { class: 'flxc-help', text: meta.join(' · ') })),
						it.task !== it.title ? h('div', { class: 'flxc-mem-v', text: it.task }) : null,
						it.note ? h('div', { class: 'flxc-help', text: it.note }) : null,
						h('div', { class: 'flxc-mem-a' },
							c ? null : h('button', { type: 'button', class: 'flxc-link', onclick: function () { location.href = it.url; } }, t('Sohbete git')),
							it.status === 'done' ? null : h('button', { type: 'button', class: 'flxc-link', onclick: function () {
								api('reminders/' + it.id, { method: 'POST', body: { status: it.status === 'active' ? 'paused' : 'active' } }).then(load).catch(function (e) { toast(e.message, 'err'); });
							} }, it.status === 'active' ? t('Durdur') : t('Yeniden başlat')),
							h('button', { type: 'button', class: 'flxc-link is-danger', onclick: function () {
								api('reminders/' + it.id, { method: 'DELETE' }).then(function () { toast(t('Silindi')); load(); }).catch(function (e) { toast(e.message, 'err'); });
							} }, t('Sil')))));
				});
			}).catch(function (e) { toast(e.message, 'err'); });
		}
		modal({
			cls: 'flxc-exp flxc-mem-modal', title: t('Hatırlatıcılar'),
			body: [
				c ? h('p', { class: 'flxc-help', text: c.title }) : null, info, list,
				h('p', { class: 'flxc-help', text: t('Kişi isteyince asistan özet gösterip onay alarak kurar; zamanı gelince asistan mesajı yazıp gönderir. Kişi "hatırlatıcıyı durdur" yazarak kapatabilir.') })
			]
		});
		list.appendChild(h('div', { class: 'flxc-mem-empty', text: t('Yükleniyor…') }));
		load();
	}

	/* Sohbetin yapay zekâ kullanımı (istek, token, maliyet) ve günlük cevap sınırı (1.12.0). */
	function openUsage(c) {
		var nf = new Intl.NumberFormat(LOC);
		var usd = function (n) { return '$' + Number(n || 0).toFixed(n >= 1 ? 2 : 4); };
		var dayFmt = new Intl.DateTimeFormat(LOC, { weekday: 'short', day: 'numeric', month: 'short' });
		var top = h('div', { class: 'flxc-use-top' }), tbl = h('div', { class: 'flxc-use-grid' }), days = h('div', { class: 'flxc-use-days' });
		var limIn = h('input', { type: 'number', min: '5', max: '500', class: 'flxc-field' });
		function row(label, a, b) { return [h('span', { text: label }), h('strong', { text: a }), h('strong', { text: b })]; }
		function draw(u) {
			clear(top);
			top.appendChild(h('div', { class: 'flxc-use-cnt' + (u.limited ? ' is-full' : '') },
				h('strong', { text: u.replies + ' / ' + u.limit }),
				h('span', { text: u.limited ? t('Bugünkü cevap sınırı doldu, sohbet ekipte.') : t('Asistanın bugünkü cevapları') })));
			top.appendChild(h('button', { type: 'button', class: 'flxc-btn' + (u.limited ? '' : ' flxc-btn-ghost'), disabled: !u.replies, onclick: function (e) {
				var b = e.currentTarget; b.disabled = true;
				api('chats/' + c.id + '/usage/reset', { method: 'POST' }).then(function (r) {
					upsertChat(r.chat); renderList(); draw(r.usage); toast(t('Günlük sınır sıfırlandı'));
				}).catch(function (er) { b.disabled = false; toast(er.message, 'err'); });
			} }, icon('reopen'), t('Günlük sınırı sıfırla')));
			clear(tbl);
			[[h('span'), h('span', { class: 'flxc-help', text: t('Bugün') }), h('span', { class: 'flxc-help', text: t('Toplam') })],
				row(t('İstek'), nf.format(u.today.calls), nf.format(u.all.calls)),
				row(t('Giriş token'), nf.format(u.today.in), nf.format(u.all.in)),
				row(t('Çıkış token'), nf.format(u.today.out), nf.format(u.all.out)),
				row(t('Maliyet'), usd(u.today.cost), usd(u.all.cost))
			].forEach(function (r) { r.forEach(function (x) { tbl.appendChild(x); }); });
			clear(days);
			u.days.forEach(function (d) {
				days.appendChild(h('div', { class: 'flxc-use-day' + (d.calls ? '' : ' is-empty') },
					h('span', { text: dayFmt.format(new Date(d.day + 'T12:00:00')) }),
					h('span', { text: t('{n} istek', { n: nf.format(d.calls) }) }),
					h('span', { text: nf.format(d.in) + ' / ' + nf.format(d.out) }),
					h('strong', { text: usd(d.cost) })));
			});
			limIn.value = u.limit;
		}
		var limBox = S.me.manage ? h('div', { class: 'flxc-use-lim' },
			fld(t('Sohbet başına günlük cevap sınırı'), limIn, t('Tüm sohbetler için geçerli (5-500). Sınır dolunca kişiye "mesajınızı aldık" gider, sohbet ekibe devredilir.')),
			h('button', { type: 'button', class: 'flxc-btn', onclick: function (e) {
				var b = e.currentTarget; b.disabled = true;
				api('bot/limit', { method: 'POST', body: { limit: parseInt(limIn.value, 10) || 0 } }).then(function () {
					b.disabled = false; toast(t('Kaydedildi')); load();
				}).catch(function (er) { b.disabled = false; toast(er.message, 'err'); });
			} }, t('Kaydet'))) : null;
		function load() { api('chats/' + c.id + '/usage').then(draw).catch(function (e) { toast(e.message, 'err'); }); }
		modal({
			cls: 'flxc-exp flxc-use', title: t('Asistan kullanımı'),
			body: [
				h('p', { class: 'flxc-help', text: c.title }), top, tbl,
				h('h5', { class: 'flxc-sec-t', text: t('Son 7 gün') }),
				h('div', { class: 'flxc-use-day is-head' }, h('span', { text: t('Gün') }), h('span', { text: t('İstek') }), h('span', { text: t('Giriş / çıkış token') }), h('strong', { text: t('Maliyet') })),
				days, limBox,
				h('p', { class: 'flxc-help', text: t('Asistan cevapları, konuşma özetleri, hatırlatıcılar ve dekont kontrolü sayılır; test sohbeti ve benchmark sayılmaz. Maliyet sağlayıcı fiyatıyla (USD).') })
			]
		});
		top.appendChild(h('div', { class: 'flxc-help', text: t('Yükleniyor…') }));
		load();
	}

	function patchChat(id, data) {
		return api('chats/' + id, { method: 'POST', body: data }).then(function (r) {
			upsertChat(r.chat);
			if (!chatMatchesView(r.chat)) {
				var i = S.order.indexOf(id);
				if (i > -1 && !S.q) S.order.splice(i, 1);
			} else if (S.order.indexOf(id) < 0) S.order.push(id);
			sortOrder();
			renderList();
			if (S.active === id) { renderChatHead(); if (S.info) { renderInfo(); refreshClog(id); } }
			return r.chat;
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	/* ---------- sohbet görünümü ---------- */

	function setView(v) {
		root.setAttribute('data-view', v);
	}

	function openChat(id, fromPop) {
		closeMenu();
		var c = S.chats.get(id);
		var p = c ? Promise.resolve(c) : api('chats/' + id).then(function (r) { upsertChat(r.chat); return r.chat; });
		return p.then(function (chat) {
			if (S.active !== id) {
				S.active = id;
				S.quoted = null;
				setNoteMode(false);
				buildChat(chat);
				loadMessages(id);
				loadChatRecs(id);
				if (S.info) renderInfo();
				refreshAvatar(chat);
			}
			renderList();
			setView('chat');
			if (!fromPop) chatUrl(id);
			store('last', String(id));
			setTimeout(function () { if (!isMobile()) el.input && el.input.focus({ preventScroll: true }); }, 30);
			return chat;
		}).catch(function (e) {
			toast(e.message, 'err');
			if (urlChat() === id) chatUrl(0, true); // silinmiş/yetkisiz sohbet adreste kalmasın
		});
	}

	/* 1.46.0: açık sohbet adreste (?page=flx-chat&chat=ID): yenileyince aynı sohbet açılır, geri/ileri sohbetler arasında gezer,
	   bağlantı paylaşılınca doğrudan o sohbet açılır. */
	function urlChat() { return +(new URLSearchParams(location.search).get('chat') || 0); }
	function urlFor(id) {
		var u = new URL(location.href);
		u.searchParams.delete('phone');
		if (id) u.searchParams.set('chat', String(id)); else u.searchParams.delete('chat');
		return u.pathname + u.search + u.hash;
	}
	function chatUrl(id, replace) {
		try {
			var cur = urlChat();
			if (replace || (cur === id && !id)) { history.replaceState({ flxc: id ? 'chat' : 'list', id: id }, '', urlFor(id)); return; }
			if (cur === id) {
				// Adresle açıldı: telefonda "geri" listeye dönsün diye altına liste girişi konur.
				if (isMobile() && !(history.state && history.state.flxc)) {
					history.replaceState({ flxc: 'list', id: 0 }, '', urlFor(0));
					history.pushState({ flxc: 'chat', id: id }, '', urlFor(id));
				} else history.replaceState({ flxc: 'chat', id: id }, '', urlFor(id));
				return;
			}
			// Masaüstünde ilk açılan sohbet sayfa girişinin yerine geçer; sonrakiler geçmişe eklenir.
			if (!cur && !isMobile()) history.replaceState({ flxc: 'chat', id: id }, '', urlFor(id));
			else history.pushState({ flxc: 'chat', id: id }, '', urlFor(id));
		} catch (e) { /* yok say */ }
	}

	function closeChat(fromPop) {
		if (!fromPop && urlChat()) chatUrl(0, true);
		S.active = 0;
		attReset();
		closeInfo(true);
		renderEmptyMain();
		setView('list');
		renderList();
		if (S.listTop) el.list.scrollTop = S.listTop;
	}

	function isMobile() { return window.matchMedia('(max-width: 899px)').matches; }

	window.addEventListener('popstate', function () {
		if (S.info && isMobile()) { closeInfo(true); return; }
		var id = urlChat();
		if (id && id !== S.active) openChat(id, true);
		else if (!id && S.active) closeChat(true);
	});

	/* 1.47.0: kişi fotoğrafı sunucuda önbelleklenir; tarayıcı yalnız sitenin adresini görür.
	 * Sohbet açılınca güncellik sunucuda kontrol edilir (sunucu sohbet başına 10 dk'da bir sorar); değiştiyse hemen yenilenir. */
	function setAvatar(c, url) {
		if (!c || url === undefined || url === c.avatar) return;
		c.avatar = url;
		renderList();
		if (S.active === c.id) { renderChatHead(); if (S.info) renderInfo(); }
	}
	function refreshAvatar(c) {
		if (c.group) return;
		api('chats/' + c.id + '/avatar', { method: 'POST' }).then(function (r) { setAvatar(c, r.avatar); }).catch(function () {});
	}
	/* Arka planda hafif tur: listedeki sohbetlerin id'leri sunucuya gider, sunucu en eskilerden birkaçını WhatsApp'a sorar.
	 * Tarayıcı WhatsApp'a hiç gitmez. Sayfa gizliyken beklenir, en fazla 20 tur. */
	var avSweepN = 0;
	function avatarSweep() {
		if (avSweepN >= 20) return;
		if (document.hidden) { setTimeout(avatarSweep, 60000); return; }
		var ids = S.order.slice(0, 200);
		if (!ids.length) return;
		avSweepN++;
		api('avatars/check', { method: 'POST', body: { ids: ids } }).then(function (r) {
			Object.keys(r.updated || {}).forEach(function (id) { setAvatar(S.chats.get(+id), r.updated[id]); });
			if (r.more) setTimeout(avatarSweep, (r.wait || 25) * 1000);
		}).catch(function () { setTimeout(avatarSweep, 120000); });
	}

	function buildChat(c) {
		clear(el.main);
		el.main.classList.add('has-chat');
		el.head = h('header', { class: 'flxc-chat-head' });
		el.msgs = h('div', { class: 'flxc-msgs', tabindex: '-1' });
		el.inner = h('div', { class: 'flxc-msgs-in' });
		el.msgs.appendChild(el.inner);
		el.down = h('button', { type: 'button', class: 'flxc-down', hidden: true, 'aria-label': t('En alta in'), onclick: function () { scrollBottom(true); } }, icon('down'), el.downN = h('span', { class: 'flxc-badge', hidden: true }));
		el.msgs.addEventListener('scroll', onMsgsScroll, { passive: true });
		el.pop = h('div', { class: 'flxc-pop', hidden: true });
		el.replyBar = h('div', { class: 'flxc-replybar', hidden: true });
		el.composer = buildComposer();
		el.main.appendChild(el.head);
		el.main.appendChild(h('div', { class: 'flxc-msgs-wrap' }, el.msgs, el.down));
		el.chips = h('div', { class: 'flxc-qchips', role: 'toolbar', 'aria-label': t('Hazır cevaplar') });
		el.lpBar = h('div', { class: 'flxc-lpbar', hidden: true });
		el.aiBar = h('div', { class: 'flxc-aibar', hidden: true });
		attReset();
		el.attBar = h('div', { class: 'flxc-attbar', hidden: true, role: 'list', 'aria-label': t('Ekler') });
		el.main.appendChild(h('div', { class: 'flxc-foot' }, el.pop, el.aiBar, el.chips, el.lpBar, el.replyBar, el.attBar, el.composer));
		renderChips();
		renderChatHead();
		bindDrop(el.main);
	}

	function renderChatHead() {
		var c = S.chats.get(S.active);
		if (!c || !el.head) return;
		var sub;
		if (c.typing) sub = h('span', { class: 'flxc-sub is-typing', text: c.typing === 2 ? t('ses kaydediyor…') : t('yazıyor…') });
		else if (c.botBusy) sub = h('span', { class: 'flxc-sub is-typing', text: t('Asistan yazıyor…') });
		else {
			var bits = [];
			if (c.title !== c.phoneFmt && c.phoneFmt) bits.push(c.phoneFmt);
			if (c.assigned) bits.push(t('Atanan: {n}', { n: agentName(c.assigned) || '?' }));
			if (c.status === 'closed') bits.push(t('Kapalı'));
			if (c.optout) bits.push(t('Kara listede: mesaj gönderilmez'));
			if (S.conns.length > 1) { var cn = connById(c.conn); if (cn) bits.push(cn.label); }
			sub = h('span', { class: 'flxc-sub', text: bits.join(' · ') || t('kişi bilgisi için dokunun') });
		}
		clear(el.head);
		el.head.appendChild(btn('back', t('Geri'), function () { if (isMobile()) history.back(); else closeChat(); }, 'flxc-back'));
		el.head.appendChild(h('button', { type: 'button', class: 'flxc-head-who', onclick: function () { S.info ? closeInfo() : openInfo(); } },
			avatarCh(c),
			h('span', { class: 'flxc-head-t' }, h('strong', { text: c.title }), sub)
		));
		var acts = h('div', { class: 'flxc-head-actions' });
		var bp = botPill(c);
		if (bp) acts.appendChild(bp);
		var pend = pendingFor(c.id).length;
		if (S.rec.donations) {
			var gb = btn('gift', t('Bağış kaydı'), function () { openDonation({ chat: c.id }); }, 'flxc-rec-hb');
			if (pend) gb.appendChild(h('span', { class: 'flxc-hb-n', title: t('Bekleyen bağış'), text: String(pend) }));
			acts.appendChild(gb);
		}
		// 1.15.0: kişinin bağış ve sertifikaları; sertifika oluşturma bağış kaydından yapılır.
		if (recOn() && !c.group) {
			var cr = S.chatRecs[c.id], nrec = cr ? cr.donations.length + cr.certificates.length : 0;
			var rb = btn('records', t('Bağışlar ve sertifikalar'), function () { openPersonRecords(c); }, 'flxc-rec-hb');
			if (nrec) rb.appendChild(h('span', { class: 'flxc-hb-n is-soft', text: String(nrec) }));
			acts.appendChild(rb);
		}
		acts.appendChild(btn('assign', t('Ata'), function (e) { assignMenu(c, e.currentTarget); }, 'flxc-hb-sec' + (c.assigned ? ' is-on' : '')));
		acts.appendChild(c.status === 'closed'
			? btn('reopen', t('Sohbeti yeniden aç'), function () { patchChat(c.id, { status: 'open' }); }, 'flxc-hb-sec')
			: btn('done', t('Sohbeti kapat'), function () { patchChat(c.id, { status: 'closed' }).then(function () { toast(t('Sohbet kapatıldı')); }); }, 'flxc-hb-sec'));
		acts.appendChild(btn('more', t('Menü'), function (e) { chatMenu(c, e.currentTarget); }));
		el.head.appendChild(acts);
	}

	function connById(id) {
		for (var i = 0; i < S.conns.length; i++) if (S.conns[i].id === id) return S.conns[i];
		return null;
	}

	function assignMenu(c, anchor) {
		var items = S.agents.map(function (a) {
			return { icon: 'person', label: a.id === S.me.id ? t('{n} (ben)', { n: a.name }) : a.name, on: c.assigned === a.id, fn: function () { patchChat(c.id, { assigned: a.id }); } };
		});
		if (c.assigned) { items.push({ sep: 1 }); items.push({ icon: 'close', label: t('Atamayı kaldır'), fn: function () { patchChat(c.id, { assigned: 0 }); } }); }
		menu(anchor, items);
	}

	/* ---------- mesajlar ---------- */

	function chatStore(id) {
		if (!S.msgs.has(id)) S.msgs.set(id, { list: [], byId: new Map(), more: false, loading: false, loaded: false });
		return S.msgs.get(id);
	}

	function loadMessages(id) {
		var st = chatStore(id);
		if (st.loaded) { renderMessages(); scrollBottom(); markRead(); aiAuto(id); return; }
		el.inner.appendChild(h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' })));
		api('chats/' + id + '/messages').then(function (r) {
			st.list = []; st.byId = new Map();
			r.messages.forEach(function (m) { st.byId.set(m.id, m); st.list.push(m); });
			st.more = r.more; st.loaded = true; st.note = r.note || '';
			if (S.active === id) { renderMessages(); scrollBottom(); markRead(); aiAuto(id); }
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	function loadOlder() {
		var id = S.active, st = chatStore(id);
		if (!st.more || st.loading || !st.list.length) return;
		st.loading = true;
		var first = st.list[0];
		var prevH = el.msgs.scrollHeight;
		el.inner.insertBefore(h('div', { class: 'flxc-loading flxc-loading-top' }, h('span', { class: 'flxc-spin' })), el.inner.firstChild);
		api('chats/' + id + '/messages', { query: { before: first.id } }).then(function (r) {
			var older = r.messages.filter(function (m) { return !st.byId.has(m.id); });
			older.forEach(function (m) { st.byId.set(m.id, m); });
			st.list = older.concat(st.list);
			st.more = r.more;
			if (S.active === id) {
				renderMessages();
				el.msgs.scrollTop = el.msgs.scrollHeight - prevH;
			}
		}).catch(function (e) { toast(e.message, 'err'); }).then(function () { st.loading = false; });
	}

	function nearBottom() { return el.msgs && el.msgs.scrollHeight - el.msgs.scrollTop - el.msgs.clientHeight < 120; }
	function scrollBottom(smooth) {
		if (!el.msgs) return;
		el.msgs.scrollTo({ top: el.msgs.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
		newBelow = 0; updateDown();
	}
	var newBelow = 0;
	function onMsgsScroll() {
		if (el.msgs.scrollTop < 80) loadOlder();
		if (nearBottom()) { newBelow = 0; markRead(); }
		// 1.36.1: the sticky day label shows only while scrolling, so it does not cover a bubble when the list rests.
		el.msgs.classList.add('is-scrolling');
		clearTimeout(daysT);
		daysT = setTimeout(function () { if (el.msgs) el.msgs.classList.remove('is-scrolling'); }, 1200);
		updateDown();
	}
	var daysT = 0, daysRaf = 0;
	function syncDays() {
		if (daysRaf || !el.inner) return;
		daysRaf = requestAnimationFrame(function () {
			daysRaf = 0;
			if (!el.inner) return;
			var days = el.inner.querySelectorAll('.flxc-day'), last = -1;
			Array.prototype.forEach.call(days, function (d, i) {
				var nx = d.nextElementSibling, sp = d.firstChild;
				var stuck = !!(nx && sp && nx.getBoundingClientRect().top < sp.getBoundingClientRect().bottom + 2);
				d.classList.toggle('is-stuck', stuck);
				if (stuck) last = i;
			});
			// Stuck labels pile up at the top: only the newest one is shown.
			Array.prototype.forEach.call(days, function (d, i) { d.classList.toggle('is-past', d.classList.contains('is-stuck') && i < last); });
		});
	}
	function updateDown() {
		syncDays();
		if (!el.down) return;
		el.down.hidden = nearBottom();
		el.downN.hidden = !newBelow;
		el.downN.textContent = newBelow;
	}

	function sameGroup(a, b) {
		return a && b && a.dir === b.dir && a.dir !== 'system' && (a.userId === b.userId || a.dir === 'in') && b.ts - a.ts < 5 * 60000 && dayStart(a.ts) === dayStart(b.ts) && a.sender === b.sender;
	}

	function renderMessages() {
		var st = chatStore(S.active);
		var frag = document.createDocumentFragment();
		if (st.note) frag.appendChild(h('div', { class: 'flxc-sys flxc-sys-warn' }, icon('warn'), t('Eski mesajlar WhatsApp hattından alınamadı: {e}', { e: st.note })));
		if (!st.more && st.loaded) {
			frag.appendChild(h('div', { class: 'flxc-sys flxc-sys-lock' }, icon('lock'), t('Bu sohbetteki mesajlar yalnız yetkili temsilcilere görünür. Dahili notları müşteri görmez.')));
		}
		var prev = null;
		st.list.forEach(function (m) {
			if (!prev || dayStart(prev.ts) !== dayStart(m.ts)) frag.appendChild(h('div', { class: 'flxc-day' }, h('span', { text: dayLabel(m.ts) })));
			frag.appendChild(bubble(m, !sameGroup(prev, m)));
			prev = m;
		});
		clear(el.inner).appendChild(frag);
	}

	/** Gelen değişiklikleri uygula: yeni mesaj sona eklenir, değişen mesajın balonu yenilenir. */
	function applyMessages(list) {
		if (!list.length) return;
		var byChat = {};
		list.forEach(function (m) { (byChat[m.chat] = byChat[m.chat] || []).push(m); });
		Object.keys(byChat).forEach(function (cid) {
			cid = +cid;
			var st = chatStore(cid);
			if (!st.loaded) return;
			var appended = [], changed = [];
			byChat[cid].forEach(function (m) {
				var old = st.byId.get(m.id);
				if (!old && m.tmp) return;
				if (!old && m.dir !== 'in') {
					// Gönderim yanıtı gelmeden eşitleme gerçek mesajı getirdiyse geçici balonun yerine geçer (çift görünmesin).
					var tmp = st.list.filter(function (x) { return x.tmp && x.dir === m.dir && x.type === m.type && (x.body || '') === (m.body || ''); })[0];
					if (tmp) {
						st.list.splice(st.list.indexOf(tmp), 1);
						st.byId.delete(tmp.id);
						tmp.adopted = m.id;
						st.byId.set(m.id, m); st.list.push(m);
						var tn = cid === S.active && el.inner && el.inner.querySelector('[data-mid="' + tmp.id + '"]');
						if (tn) tn.setAttribute('data-mid', m.id);
						changed.push(m);
						return;
					}
				}
				if (old) {
					if (old.rev !== m.rev) { Object.assign(old, m); changed.push(old); }
				} else {
					st.byId.set(m.id, m); st.list.push(m); appended.push(m);
				}
			});
			if (cid !== S.active) return;
			var atBottom = nearBottom();
			var lastShown = st.list[st.list.length - 1 - appended.length];
			var needFull = appended.some(function (m) { return lastShown && m.ts < lastShown.ts; });
			st.list.sort(function (a, b) { return (a.ts - b.ts) || (a.id - b.id); });
			if (needFull || appended.length > 20) { renderMessages(); }
			else {
				changed.forEach(function (m) {
					var node = el.inner.querySelector('[data-mid="' + m.id + '"]');
					if (!node) return;
					var playing = node.querySelector('audio:not([data-paused]), video');
					if (playing && !playing.paused) { var tk = node.querySelector('.flxc-meta'); if (tk) tk.replaceWith(metaLine(m)); return; }
					var i = st.list.indexOf(m);
					node.replaceWith(bubble(m, !sameGroup(st.list[i - 1], m)));
				});
				appended.forEach(function (m) {
					var i = st.list.indexOf(m), p = st.list[i - 1];
					if (!p || dayStart(p.ts) !== dayStart(m.ts)) el.inner.appendChild(h('div', { class: 'flxc-day' }, h('span', { text: dayLabel(m.ts) })));
					el.inner.appendChild(bubble(m, !sameGroup(p, m)));
				});
			}
			if (appended.length) {
				var lastNew = appended[appended.length - 1];
				if (lastNew.dir === 'in') aiAuto(cid, 2500);
				else if (lastNew.dir === 'out') { clearTimeout(aiAutoT); if (el.aiBar && !el.aiBar.hidden && !el.aiBar.querySelector('.is-loading')) aiHide(); }
				var mineNew = appended.some(function (m) { return m.dir !== 'in'; });
				if (atBottom || mineNew) scrollBottom(true);
				else { newBelow += appended.filter(function (m) { return m.dir === 'in'; }).length; updateDown(); }
				if (atBottom) markRead();
			}
		});
	}

	function sourceTag(m) {
		var map = { phone: t('Telefondan'), certificate: t('Sertifika'), record: t('Kayıt'), auto: t('Otomatik'), ai: t('Yapay zekâ önerisi'), bot: t('Asistan'), api: t('Sistem'), quick: '' };
		var s = map[m.source];
		if (m.dir === 'out' && m.user && m.userId !== S.me.id) return m.user + (s ? ' · ' + s : '');
		return s || '';
	}

	function metaLine(m) {
		var meta = h('span', { class: 'flxc-meta' });
		if (m.edited) meta.appendChild(h('span', { class: 'flxc-edited', text: t('düzenlendi') }));
		meta.appendChild(h('time', { text: fmtTime.format(m.ts), datetime: new Date(m.ts).toISOString(), title: fmtLong.format(m.ts) + ' ' + fmtTime.format(m.ts) }));
		if (m.dir === 'out') meta.appendChild(ticks(m.status));
		return meta;
	}

	function bubble(m, first) {
		if (m.dir === 'system') {
			return h('div', { class: 'flxc-sys', 'data-mid': m.id }, h('span', { text: m.body + ' · ' + fmtTime.format(m.ts) }));
		}
		var isNote = m.dir === 'note';
		// 1.38.0: silinen mesaj kapalı satır olarak görünür; içerik sunucuda saklı, tıklayınca açılır.
		if (m.deleted && (!S.delOpen.has(m.id) || (m.del && m.del.empty))) return deletedRow(m);
		var row = h('div', {
			class: 'flxc-m flxc-m-' + (isNote ? 'note' : m.dir) + (first ? ' is-first' : '') + (m.tmp ? ' is-tmp' : '') + (m.status === 'failed' ? ' is-failed' : '') + (m.type === 'sticker' ? ' is-sticker' : '') + (m.deleted ? ' is-deleted' : ''),
			'data-mid': m.id
		});
		var b = h('div', { class: 'flxc-b' });
		if (m.deleted) b.appendChild(h('button', { type: 'button', class: 'flxc-b-del', title: t('Gizle'), onclick: function () { toggleDeleted(m); } }, icon('block'), delLabel(m), h('span', { class: 'flxc-b-del-x' }, icon('down'))));
		var tag = isNote ? t('Dahili not · {n}', { n: m.user || '' }) : sourceTag(m);
		if (tag) b.appendChild(h('div', { class: 'flxc-b-tag' }, isNote ? icon('note') : null, tag));
		if (m.sender && m.dir === 'in') b.appendChild(h('div', { class: 'flxc-b-sender', text: m.sender }));
		if (m.quoted) {
			b.appendChild(h('button', {
				type: 'button', class: 'flxc-quote flxc-quote-' + m.quoted.dir,
				onclick: function () { jumpTo(m.quoted.id); }
			}, h('strong', { text: m.quoted.dir === 'in' ? (S.chats.get(S.active) || {}).title || '' : t('Siz') }), h('span', { text: m.quoted.body })));
		}
		// 1.48.0: toplu gönderim (kampanya / etkinlik) etiketi; tıklayınca gönderim detayı açılır.
		if (m.origin && m.origin.label && !m.deleted) {
			var oTitle = t('Toplu gönderim') + ': ' + m.origin.label;
			b.appendChild(m.origin.url
				? h('a', { class: 'flxc-origin', href: m.origin.url, target: '_blank', rel: 'noopener', title: oTitle + ' · ' + t('Gönderim detayını aç') }, icon('campaign'), h('span', { text: m.origin.label }), icon('open', 'flxc-origin-go'))
				: h('span', { class: 'flxc-origin', title: oTitle }, icon('campaign'), h('span', { text: m.origin.label })));
		}
		b.appendChild(content(m));
		// 1.45.0: fotoğraf/video varsa balon medya genişliğinde; açıklama metni medyanın genişliğine sarılır.
		if (!m.deleted && (m.type === 'image' || m.type === 'video') && m.media) b.classList.add('has-media');
		if (b.querySelector('.flxc-lp-slot')) b.classList.add('has-lp');
		var rb = receiptBar(m);
		if (rb) b.appendChild(rb);
		var react = m.react || {};
		var rx = Object.keys(react).map(function (k) { return react[k]; }).filter(Boolean);
		if (rx.length) row.classList.add('has-react');
		row.appendChild(b);
		if (rx.length) row.appendChild(h('span', { class: 'flxc-react', text: rx.join('') }));
		if (!m.tmp && !m.deleted) {
			var mb = h('button', { type: 'button', class: 'flxc-m-menu', 'aria-label': t('Mesaj menüsü'), onclick: function (e) { e.stopPropagation(); msgMenu(m, e.currentTarget); } }, icon('down'));
			b.appendChild(mb);
			longPress(b, function () { msgMenu(m, b); });
			// 1.46.0: sağ tık mesaj menüsünü imlecin yanında açar (metin seçiliyse ya da bağlantıdaysa tarayıcı menüsü kalır).
			b.addEventListener('contextmenu', function (e) {
				if (matchMedia('(hover: none)').matches) return;
				var sel = window.getSelection && String(window.getSelection());
				if (sel || e.target.closest('a[href]')) return;
				e.preventDefault();
				msgMenu(m, b, { x: e.clientX, y: e.clientY });
			});
			b.addEventListener('dblclick', function (e) { if (!e.target.closest('a,audio,video,button')) setQuoted(m); });
		}
		if (m.status === 'failed') {
			row.appendChild(h('button', { type: 'button', class: 'flxc-retry', title: m.error || '', onclick: function () { retry(m); } }, icon('reopen'), t('Tekrar dene')));
			if (m.error) row.appendChild(h('div', { class: 'flxc-err', text: m.error }));
		}
		return row;
	}

	function delLabel(m) {
		var d = m.del || {};
		var what = {
			contact: t('Kişi bu mesajı sildi'),
			phone: t('Telefondan silindi'),
			everyone: t('Herkesten silindi'),
			panel: t('Panelde gizlendi')
		}[d.scope] || t('Bu mesaj silindi');
		return [what, d.by, d.ago].filter(Boolean).join(' · ');
	}
	function deletedRow(m) {
		var d = m.del || {};
		var canOpen = !d.empty;
		var tip = [delLabel(m), d.atText, t('Yapay zekâ bu mesajı okumaz.')].filter(Boolean).join('\n');
		var row = h('div', { class: 'flxc-m flxc-m-' + (m.dir === 'note' ? 'note' : m.dir) + ' is-deleted is-folded', 'data-mid': m.id });
		var b = h(canOpen ? 'button' : 'div', { type: canOpen ? 'button' : null, class: 'flxc-b flxc-b-folded', title: tip, 'aria-expanded': canOpen ? 'false' : null, onclick: canOpen ? function () { toggleDeleted(m); } : null },
			h('span', { class: 'flxc-deleted' }, icon('block'), t('Bu mesaj silindi'), canOpen ? h('small', { class: 'flxc-del-show', text: t('Göster') }) : null),
			metaLine(m)
		);
		row.appendChild(b);
		return row;
	}
	function toggleDeleted(m) {
		if (S.delOpen.has(m.id)) S.delOpen.delete(m.id); else S.delOpen.add(m.id);
		var node = el.inner && el.inner.querySelector('[data-mid="' + m.id + '"]');
		if (!node) return;
		var first = node.classList.contains('is-first');
		var nb = bubble(m, first);
		node.replaceWith(nb);
	}

	function content(m) {
		var wrap = h('div', { class: 'flxc-c' });
		var meta = metaLine(m);
		var media = m.media;
		switch (m.type) {
			case 'deleted':
				wrap.appendChild(h('span', { class: 'flxc-deleted' }, icon('close'), t('Bu mesaj silindi')));
				break;
			case 'image':
			case 'sticker':
				var ratio = media && media.w && media.h ? Math.min(1.6, Math.max(0.5, media.h / media.w)) : 0.75;
				var fig = h('button', { type: 'button', class: 'flxc-img' + (m.type === 'sticker' ? ' flxc-sticker' : ''), style: m.type === 'image' ? { aspectRatio: '1 / ' + ratio } : null, onclick: function () { lightbox(media.url, 'image', m); } });
				if (media && media.thumb) fig.style.backgroundImage = 'url("' + media.thumb + '")';
				var img = h('img', { src: media ? media.url : '', alt: m.body || t('Fotoğraf'), loading: 'lazy' });
				img.onerror = function () { fig.classList.add('is-broken'); img.remove(); fig.appendChild(h('span', null, icon('image'), t('Görsel yüklenemedi'))); };
				fig.appendChild(img);
				wrap.appendChild(fig);
				break;
			case 'video':
				// 1.45.0: balonda oynatıcı yok; önizleme (ilk kare / küçük resim + oynat), tıklayınca önizleme penceresinde izlenir.
				var vratio = media && media.w && media.h ? Math.min(1.6, Math.max(0.5, media.h / media.w)) : 0.75;
				var vfig = h('button', { type: 'button', class: 'flxc-img flxc-vid', style: { aspectRatio: '1 / ' + vratio }, 'aria-label': t('Videoyu oynat'), onclick: function () { lightbox(media.url, 'video', m); } });
				if (media && media.thumb) vfig.style.backgroundImage = 'url("' + media.thumb + '")';
				var vdurT = h('em', { text: media.secs ? fmtSecs(media.secs) : '' });
				var vid = h('video', { src: media.url + '#t=0.1', preload: 'metadata', playsinline: true, tabindex: '-1', 'aria-hidden': 'true' });
				vid.muted = true;
				vid.addEventListener('loadedmetadata', function () {
					if (!(media.w && media.h) && vid.videoWidth) vfig.style.aspectRatio = '1 / ' + Math.min(1.6, Math.max(0.5, vid.videoHeight / vid.videoWidth));
					if (!media.secs && isFinite(vid.duration) && vid.duration > 0) vdurT.textContent = fmtSecs(Math.round(vid.duration));
				});
				vid.onerror = function () { vid.remove(); };
				vfig.appendChild(vid);
				vfig.appendChild(h('span', { class: 'flxc-vid-play' }, icon('play')));
				vfig.appendChild(h('span', { class: 'flxc-vid-dur' }, icon('video'), vdurT));
				wrap.appendChild(vfig);
				break;
			case 'audio':
				wrap.appendChild(audioPlayer(m));
				break;
			case 'document':
				wrap.appendChild(h('a', { class: 'flxc-doc', href: media.url + '&download=1', download: media.name || '' },
					h('span', { class: 'flxc-doc-i' }, icon('doc'), h('em', { text: ((media.name || '').split('.').pop() || 'file').slice(0, 4).toUpperCase() })),
					h('span', { class: 'flxc-doc-t' }, h('strong', { text: media.name || t('Belge') }), h('small', { text: [fmtSize(media.size), (media.mime || '').split('/').pop()].filter(Boolean).join(' · ') })),
					icon('download', 'flxc-doc-dl')
				));
				break;
			case 'location':
				var lq = m.loc ? m.loc.lat + ',' + m.loc.lng : '';
				wrap.appendChild(h('a', { class: 'flxc-loc', href: 'https://maps.google.com/?q=' + encodeURIComponent(lq), target: '_blank', rel: 'noopener' },
					h('span', { class: 'flxc-loc-map' }, icon('loc')),
					h('span', null, h('strong', { text: (m.loc && m.loc.name) || t('Konum') }), h('small', { text: lq }))
				));
				break;
			case 'contact':
				wrap.appendChild(h('div', { class: 'flxc-vcard' }, h('span', { class: 'flxc-av flxc-av-sm', style: { background: '#8696a0' } }, icon('person')),
					h('span', null, h('strong', { text: m.body || t('Kişi kartı') }), m.vcardPhone ? h('small', { text: '+' + m.vcardPhone }) : null),
					m.vcardPhone ? h('button', { type: 'button', class: 'flxc-link', onclick: function () { openNewChat(m.vcardPhone, m.body); } }, t('Mesaj gönder')) : null
				));
				break;
			case 'unknown':
				wrap.appendChild(h('span', { class: 'flxc-deleted' }, icon('warn'), t('Bu mesaj türü burada gösterilemiyor. Telefondan bakın.')));
				break;
		}
		var text = m.type === 'contact' || m.type === 'deleted' || m.type === 'location' ? '' : m.body;
		if (text) {
			var lps = lpSlot(m, text);
			if (lps) wrap.appendChild(lps);
			var tx = h('div', { class: 'flxc-text', html: waFormat(text) });
			if (/^(\p{Extended_Pictographic}|️|‍|\s){1,6}$/u.test(text) && m.type === 'text') tx.classList.add('is-jumbo');
			tx.appendChild(h('span', { class: 'flxc-meta-sp' + (m.edited ? ' is-edited' : '') }));
			wrap.appendChild(tx);
		}
		wrap.appendChild(meta);
		if (!text && (m.type === 'image' || m.type === 'video' || m.type === 'sticker')) meta.classList.add('is-over');
		return wrap;
	}

	function audioPlayer(m) {
		var media = m.media;
		var a = new Audio();
		a.preload = 'none';
		a.src = media.url;
		var playB = h('button', { type: 'button', class: 'flxc-au-play', 'aria-label': t('Oynat') }, icon('play'));
		var bar = h('input', { type: 'range', min: '0', max: '1000', value: '0', class: 'flxc-au-bar', 'aria-label': t('Konum') });
		var time = h('span', { class: 'flxc-au-time', text: media.secs ? fmtSecs(media.secs) : '0:00' });
		var speed = 1;
		var spB = h('button', { type: 'button', class: 'flxc-au-speed', text: '1×', onclick: function () { speed = speed === 1 ? 1.5 : speed === 1.5 ? 2 : 1; a.playbackRate = speed; spB.textContent = speed + '×'; } });
		playB.onclick = function () {
			document.querySelectorAll('.flxc-au audio').forEach(function (x) { if (x !== a) x.pause(); });
			if (a.paused) a.play().catch(function () { toast(t('Ses oynatılamadı'), 'err'); });
			else a.pause();
		};
		a.addEventListener('play', function () { clear(playB).appendChild(icon('pause')); });
		a.addEventListener('pause', function () { clear(playB).appendChild(icon('play')); });
		a.addEventListener('timeupdate', function () {
			if (a.duration && isFinite(a.duration)) bar.value = Math.round(a.currentTime / a.duration * 1000);
			time.textContent = fmtSecs(a.currentTime);
		});
		a.addEventListener('ended', function () { bar.value = 0; time.textContent = media.secs ? fmtSecs(media.secs) : fmtSecs(a.duration); });
		bar.addEventListener('input', function () { if (a.duration && isFinite(a.duration)) a.currentTime = bar.value / 1000 * a.duration; });
		var wrap = h('div', { class: 'flxc-au' + (media.ptt ? ' is-ptt' : '') },
			h('span', { class: 'flxc-au-mic' }, icon('mic')), playB, h('div', { class: 'flxc-au-mid' }, bar, time), spB);
		wrap.appendChild(a);
		return wrap;
	}

	function jumpTo(id) {
		var node = el.inner.querySelector('[data-mid="' + id + '"]');
		if (!node) { toast(t('Alıntılanan mesaj yukarıda, daha eski mesajları yükleyin.')); return; }
		node.scrollIntoView({ behavior: 'smooth', block: 'center' });
		node.classList.add('is-flash');
		setTimeout(function () { node.classList.remove('is-flash'); }, 1400);
	}

	var REACTS = ['👍', '❤️', '😂', '😮', '😢', '🤲'];
	function msgMenu(m, anchor, at) {
		var items = [];
		if (m.wa && m.dir !== 'note') {
			var rRow = h('div', { class: 'flxc-react-row' });
			REACTS.forEach(function (e) {
				rRow.appendChild(h('button', { type: 'button', class: (m.react && m.react.out === e) ? 'is-on' : '', text: e, onclick: function (ev) { ev.stopPropagation(); closeMenu(); react(m, (m.react && m.react.out === e) ? '' : e); } }));
			});
			var cur = m.react && m.react.out;
			if (cur && REACTS.indexOf(cur) < 0) rRow.appendChild(h('button', { type: 'button', class: 'is-on', text: cur, onclick: function (ev) { ev.stopPropagation(); closeMenu(); react(m, ''); } }));
			rRow.appendChild(h('button', { type: 'button', class: 'flxc-react-more', 'aria-label': t('Başka emoji'), title: t('Başka emoji'), onclick: function (ev) {
				ev.stopPropagation();
				menu(anchor, [{ node: emojiPicker(function (e) { closeMenu(); emoRecent(e); react(m, e); }) }], { cls: 'flxc-menu-emo', noFocus: true });
			} }, icon('plus')));
			items.push({ node: rRow });
		}
		if (m.dir !== 'note' && m.wa) items.push({ icon: 'reply', label: t('Yanıtla'), fn: function () { setQuoted(m); } });
		if (m.body) items.push({ icon: 'copy', label: t('Kopyala'), fn: function () { copyText(m.body); } });
		// 1.45.0: WhatsApp gibi düzenle; yalnız gönderen/yönetici, WhatsApp süresi (15 dk) içinde.
		if (m.dir === 'out' && m.editUntil && m.editUntil > Date.now() && (m.userId === S.me.id || S.me.manage)) items.push({ icon: 'edit', label: t('Düzenle'), fn: function () { openEdit(m); } });
		if (m.media && m.media.url) items.push({ icon: 'download', label: t('İndir'), fn: function () { dlFile(m.media.url + '&download=1', m.media.name); } });
		if (m.status === 'failed') items.push({ icon: 'reopen', label: t('Tekrar gönder'), fn: function () { retry(m); } });
		if (recOn() && m.dir === 'in' && (m.type === 'image' || m.type === 'document')) items.push({ icon: 'receipt', label: t('Dekont olarak eşleştir'), fn: function () { openReceipt(m); } });
		if (m.dir === 'note' && (m.userId === S.me.id || S.me.manage)) items.push({ icon: 'trash', label: t('Notu sil'), danger: true, fn: function () { deleteNote(m); } });
		if ((m.dir === 'in' || m.dir === 'out') && m.type !== 'deleted' && m.status !== 'pending') items.push({ icon: 'trash', label: t('Sil'), danger: true, fn: function () { openDelete(m); } });
		items.push({ sep: 1 });
		items.push({ node: h('div', { class: 'flxc-menu-info' }, fmtLong.format(m.ts) + ' ' + fmtTime.format(m.ts) + (m.dir === 'out' ? ' · ' + statusLabel(m.status) : '')) });
		menu(anchor, items, { noFocus: true, at: at || null });
	}

	function copyText(s) {
		(navigator.clipboard ? navigator.clipboard.writeText(s) : Promise.reject()).then(function () { toast(t('Kopyalandı')); }).catch(function () {
			var ta = h('textarea', { style: { position: 'fixed', opacity: 0 } }); ta.value = s; document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); toast(t('Kopyalandı')); } catch (e) { /* yok */ }
			ta.remove();
		});
	}

	function react(m, emoji) {
		api('messages/' + m.id + '/react', { method: 'POST', body: { emoji: emoji } }).then(function (r) { applyMessages([r.message]); }).catch(function (e) { toast(e.message, 'err'); });
	}
	function retry(m) {
		m.status = 'pending';
		applyMessages([Object.assign({}, m, { rev: m.rev + 1 })]);
		api('messages/' + m.id + '/retry', { method: 'POST' }).then(function (r) { applyMessages([r.message]); }).catch(function (e) { toast(e.message, 'err'); });
	}
	function deleteNote(m) {
		api('messages/' + m.id, { method: 'DELETE', query: { scope: 'panel' } }).then(function (r) {
			applyMessages([r.message]);
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	/* 1.45.0: giden metin mesajını düzenle (WhatsApp'ta da düzenlenir, kişide "Düzenlendi" görünür). */
	function openEdit(m) {
		var ta = h('textarea', { class: 'flxc-field flxc-edit-ta', rows: '5', 'aria-label': t('Mesaj') });
		ta.value = m.body || '';
		var left = h('small', { class: 'flxc-help flxc-edit-left' });
		var save = h('button', { type: 'button', class: 'flxc-btn' }, icon('check'), t('Kaydet'));
		var cancel = h('button', { type: 'button', class: 'flxc-btn flxc-btn-ghost' }, t('Vazgeç'));
		var timer = 0, busy = false;
		function tick() {
			if (!ta.isConnected && timer) { clearInterval(timer); return; }
			var ms = (m.editUntil || 0) - Date.now();
			if (ms <= 0) {
				left.textContent = t('Düzenleme süresi doldu. WhatsApp gönderimden sonra 15 dakika izin verir.');
				left.classList.add('flxc-warn');
				save.disabled = true;
				clearInterval(timer);
				return;
			}
			var mm = Math.floor(ms / 60000), ss = Math.floor(ms / 1000) % 60;
			left.textContent = t('Kalan süre: {t}', { t: mm + ':' + (ss < 10 ? '0' : '') + ss });
		}
		function sync() { save.disabled = busy || !ta.value.trim() || ta.value.trim() === (m.body || '').trim() || (m.editUntil || 0) <= Date.now(); }
		function go() {
			sync();
			if (save.disabled) return;
			busy = true; sync();
			api('messages/' + m.id + '/edit', { method: 'POST', body: { text: ta.value.trim() } }).then(function (r) {
				dlg.close();
				applyMessages([r.message]);
				toast(t('Mesaj düzenlendi'));
			}).catch(function (e) { busy = false; sync(); toast(e.message, 'err'); });
		}
		ta.addEventListener('input', sync);
		ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); go(); } });
		save.addEventListener('click', go);
		var dlg = modal({
			cls: 'flxc-exp flxc-edit-dlg', title: t('Mesajı düzenle'),
			body: [
				h('p', { class: 'flxc-help', text: t('Mesaj kişinin telefonunda da değişir ve altında "Düzenlendi" yazar.') }),
				ta, left
			],
			foot: [cancel, save]
		});
		var close0 = dlg.close;
		dlg.close = function () { clearInterval(timer); close0(); };
		cancel.addEventListener('click', dlg.close);
		timer = setInterval(tick, 1000);
		tick(); sync();
		setTimeout(function () { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }, 30);
	}

	/* 1.38.0: WhatsApp gibi "Herkesten sil" / "Yalnız panelde gizle". Satır ve dosya sunucuda kalır. */
	function openDelete(m) {
		var now = Date.now();
		var mine = m.userId === S.me.id || S.me.manage;
		var until = m.revokeUntil || 0;
		var canAll = m.dir === 'out' && until > now && mine;
		var why;
		if (m.dir === 'in') why = t('Gelen mesajı WhatsApp karşı taraftan silmeye izin vermez; mesaj kişinin telefonunda kalır, yalnız panelde gizlenir.');
		else if (!S.delHours) why = t('Herkesten silme ayarlarda kapalı; mesaj yalnız panelde gizlenir, kişinin telefonunda kalır.');
		else if (!until) why = t('Bu kanalda herkesten silme desteklenmiyor; mesaj yalnız panelde gizlenir, kişinin telefonunda kalır.');
		else if (until <= now) why = t('Herkesten silme süresi doldu (WhatsApp gönderimden sonra yaklaşık {h} saat izin verir). Mesaj kişinin telefonunda kalır, yalnız panelde gizlenir.', { h: S.delHours });
		else if (!mine) why = t('Bu mesajı yalnız gönderen ya da yönetici herkesten silebilir; siz panelde gizleyebilirsiniz.');
		else {
			var left = Math.max(1, Math.round((until - now) / 3600000));
			why = t('Herkesten silinirse mesaj kişinin telefonunda da "Bu mesaj silindi" olur. Kalan süre: yaklaşık {n} saat.', { n: left });
		}
		var busy = false;
		var go = function (scope, b) {
			if (busy) return;
			busy = true; b.disabled = true;
			api('messages/' + m.id, { method: 'DELETE', query: { scope: scope } }).then(function (r) {
				dlg.close();
				applyMessages([r.message]);
				toast(scope === 'everyone' ? t('Herkesten silindi') : t('Panelde gizlendi'));
			}).catch(function (e) { busy = false; b.disabled = false; toast(e.message, 'err'); });
		};
		var all = canAll ? h('button', { type: 'button', class: 'flxc-btn flxc-btn-block flxc-btn-danger' }, icon('trash'), t('Herkesten sil')) : null;
		var hide = h('button', { type: 'button', class: 'flxc-btn flxc-btn-block' + (canAll ? ' flxc-btn-ghost flxc-del-hide' : ' flxc-btn-danger') }, icon('block'), t('Yalnız panelde gizle'));
		if (all) all.addEventListener('click', function () { go('everyone', all); });
		hide.addEventListener('click', function () { go('panel', hide); });
		var dlg = modal({
			cls: 'flxc-exp flxc-del-dlg', title: t('Mesajı sil'),
			body: [
				h('p', { class: canAll ? 'flxc-help' : 'flxc-warn', text: why }),
				h('p', { class: 'flxc-help', text: t('Silinen mesaj sunucuda saklanır: sohbette "Bu mesaj silindi" satırı görünür, ekip tıklayıp içeriği açabilir. Yapay zekâ silinen mesajı okumaz.') }),
				h('div', { class: 'flxc-del-acts' }, all, hide)
			]
		});
	}

	function markRead() {
		var c = S.chats.get(S.active);
		if (!c || !c.unread || document.hidden || !nearBottom()) return;
		c.unread = 0;
		renderList();
		updateTitle();
		api('chats/' + c.id + '/read', { method: 'POST' }).catch(function () {});
	}

	/* ---------- link önizlemesi (WhatsApp kartı) ---------- */

	var RX_URL = /(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?*_~])/i;
	function firstUrl(text) { var m = RX_URL.exec(String(text || '')); return m ? m[1] : ''; }
	var lpCache = new Map(), lpQueue = [], lpBusy = 0;
	function fetchLP(u, fresh) {
		if (!fresh && lpCache.has(u)) return lpCache.get(u);
		var p = new Promise(function (resolve) {
			lpQueue.push(function () {
				return api('link-preview', { query: { url: u, fresh: fresh ? 1 : '' } })
					.then(resolve, function () { resolve({ ok: false }); });
			});
			lpPump();
		});
		lpCache.set(u, p);
		return p;
	}
	function lpPump() {
		while (lpBusy < 2 && lpQueue.length) {
			lpBusy++;
			lpQueue.shift()().then(function () { lpBusy--; lpPump(); });
		}
	}
	function lpDomain(d) {
		if (d.domain) return d.domain;
		try { return new URL(d.url).hostname.replace(/^www\./, ''); } catch (e) { return ''; }
	}
	/* small: mesaj kutusundaki gibi solda küçük görsel; değilse WhatsApp'taki gibi üstte geniş görsel. */
	function lpCard(d, small) {
		var img = d.image || d.thumb || '';
		var wide = !small && !!d.image;
		var a = h('a', { class: 'flxc-lp' + (wide ? ' is-wide' : '') + (small ? ' is-small' : ''), href: d.url, target: '_blank', rel: 'noopener noreferrer' });
		if (img) {
			var im = h('img', { src: img, alt: '', loading: 'lazy', referrerpolicy: 'no-referrer' });
			var box = h('span', { class: 'flxc-lp-img' }, im);
			im.onerror = function () { box.remove(); a.classList.remove('is-wide'); };
			a.appendChild(box);
		}
		a.appendChild(h('span', { class: 'flxc-lp-t' },
			d.title ? h('strong', { text: d.title }) : null,
			d.desc ? h('small', { text: d.desc }) : null,
			h('em', { text: lpDomain(d) })
		));
		return a;
	}
	var lpObs = 'IntersectionObserver' in window ? new IntersectionObserver(function (ents) {
		ents.forEach(function (e) { if (e.isIntersecting) { lpObs.unobserve(e.target); fillLP(e.target); } });
	}, { rootMargin: '400px 0px' }) : null;
	function lpSlot(m, text) {
		if (m.lp === false || m.type !== 'text') return null;
		var u = m.lp && m.lp.url ? m.lp.url : firstUrl(text);
		if (!u) return null;
		var slot = h('div', { class: 'flxc-lp-slot' });
		slot._u = u;
		if (m.lp && (m.lp.title || m.lp.desc)) {
			// WhatsApp'tan gelen önizleme: küçük görsel; geniş görsel için sitenin kendi kartı denenir.
			slot.appendChild(lpCard({ url: u, title: m.lp.title, desc: m.lp.desc, thumb: m.lp.thumb }, false));
			slot._have = true;
		}
		if (lpObs) lpObs.observe(slot); else fillLP(slot);
		return slot;
	}
	function fillLP(slot) {
		fetchLP(slot._u).then(function (d) {
			if (!slot.isConnected) return;
			if (!d || !d.ok) { if (!slot._have) { var bb = slot.closest('.flxc-b'); slot.remove(); if (bb) bb.classList.remove('has-lp'); } return; }
			var keep = nearBottom();
			clear(slot).appendChild(lpCard(d, false));
			if (keep) scrollBottom();
		});
	}

	/* Mesaj kutusu: link yazılınca biraz bekleyip önizleme kartı. */
	function lpCheck(now) {
		clearTimeout(lpCheck.tm);
		lpCheck.tm = setTimeout(function () {
			if (!el.lpBar) return;
			var u = S.note ? '' : firstUrl(el.input.value);
			if (!u) { S.lp = null; el.lpBar.hidden = true; clear(el.lpBar); return; }
			if (S.lp && S.lp.url === u) return;
			var cur = S.lp = { url: u, off: false, data: null };
			clear(el.lpBar);
			el.lpBar.hidden = false;
			el.lpBar.appendChild(h('div', { class: 'flxc-lp is-small is-loading' }, h('span', { class: 'flxc-lp-img' }, icon('link')), h('span', { class: 'flxc-lp-t' }, h('small', { text: t('Önizleme yükleniyor…') }), h('em', { text: lpDomain({ url: u }) }))));
			el.lpBar.appendChild(btn('close', t('Önizlemeyi kaldır'), lpOff));
			fetchLP(u).then(function (d) {
				if (S.lp !== cur) return;
				if (!d || !d.ok) { el.lpBar.hidden = true; clear(el.lpBar); return; }
				cur.data = d;
				clear(el.lpBar);
				el.lpBar.appendChild(lpCard(d, true));
				el.lpBar.appendChild(btn('close', t('Önizlemeyi kaldır'), lpOff));
			});
		}, now ? 0 : 700);
	}
	function lpOff() {
		if (S.lp) S.lp.off = true;
		el.lpBar.hidden = true;
		clear(el.lpBar);
		el.input.focus();
	}
	function lpTake() {
		var lp = S.lp;
		S.lp = null;
		if (el.lpBar) { el.lpBar.hidden = true; clear(el.lpBar); }
		return lp;
	}

	/* ---------- yazma alanı ---------- */

	function buildComposer() {
		var input = h('textarea', { class: 'flxc-input', rows: '1', placeholder: phMsg(), 'aria-label': t('Mesaj'), enterkeyhint: 'send' });
		el.input = input;
		var draftKey = function () { return 'draft_' + S.active; };
		input.value = store(draftKey()) || '';
		input.addEventListener('input', function () {
			autoGrow();
			store(draftKey(), input.value);
			updateSendBtn();
			checkSlash();
			lpCheck();
			if (input.value && !S.note) sendTyping();
		});
		input.addEventListener('keydown', function (e) {
			if (popKeys(e)) return;
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !(isMobile() && 'ontouchstart' in window)) { e.preventDefault(); if (S.att.length) sendAtt(); else sendText(); }
		});
		input.addEventListener('paste', function (e) {
			var files = Array.prototype.slice.call((e.clipboardData && e.clipboardData.files) || []);
			if (files.length) { e.preventDefault(); addFiles(files); }
		});
		el.sendB = h('button', { type: 'button', class: 'flxc-send', 'aria-label': t('Gönder'), onmousedown: function (e) { if (S.att.length) e.preventDefault(); }, onclick: function () { if (S.att.length) sendAtt(); else if (input.value.trim()) sendText(); else if (isMobile()) input.focus(); else startRecord(); } });
		el.fileIn = h('input', { type: 'file', hidden: true, multiple: true, onchange: function () { addFiles(Array.prototype.slice.call(el.fileIn.files)); el.fileIn.value = ''; } });
		var bar = h('div', { class: 'flxc-comp' },
			btn('emoji', t('Emoji'), function (e) { e.stopPropagation(); toggleEmoji(); }, 'flxc-emo-b'),
			btn('plus', t('Ekle'), function (e) { attachMenu(e.currentTarget); }, 'flxc-att-b'),
			S.ai ? btn('ai', t('Yapay zekâ önerisi'), function () { aiSuggest({ fresh: true }); }, 'flxc-ai-b') : null,
			// Telefonda (1.11.2, Ahmet): input üstte tam satır, ikonlar altında, mikrofon ayrı, gönder sağ altta.
			btn('mic', t('Sesli mesaj kaydet'), function () { startRecord(); }, 'flxc-mic-b'),
			h('div', { class: 'flxc-input-wrap' }, el.noteTag = h('span', { class: 'flxc-note-tag', hidden: true }, icon('note'), t('Dahili not'), h('button', { type: 'button', 'aria-label': t('Notu iptal et'), onclick: function () { setNoteMode(false); } }, icon('close'))), input),
			el.sendB,
			el.fileIn
		);
		S.lp = null;
		setTimeout(function () { autoGrow(); updateSendBtn(); lpCheck(true); }, 0);
		return bar;
	}

	function phMsg() { return isMobile() ? t('Mesaj') : t('Mesaj yazın — hazır cevap için /'); }
	function autoGrow() {
		var i = el.input;
		i.style.height = 'auto';
		i.style.height = Math.min(i.scrollHeight, 160) + 'px';
	}
	function updateSendBtn() {
		if (!el.sendB) return;
		var att = S.att && S.att.length;
		var has = el.input.value.trim().length > 0 || !!att;
		// Telefonda mikrofonun kendi düğmesi var: gönder hep gönder, boşken sönük.
		var send = has || S.note || isMobile();
		// 1.46.0: ekler yüklenirken / yüklenemediyse Gönder pasif.
		var wait = !!att && (attBusy() || S.att.some(function (a) { return a.err; }));
		el.sendB.disabled = wait;
		el.sendB.classList.toggle('is-wait', wait);
		el.sendB.title = wait ? t('Dosyalar yükleniyor, biraz bekleyin.') : '';
		clear(el.sendB).appendChild(icon(send ? 'send' : 'mic'));
		el.sendB.setAttribute('aria-label', send ? t('Gönder') : t('Sesli mesaj kaydet'));
		el.sendB.classList.toggle('is-mic', !send);
		el.sendB.classList.toggle('is-empty', send && !has);
	}
	if (window.matchMedia) {
		var mqComp = window.matchMedia('(max-width: 899px)');
		var onMq = function () { updateSendBtn(); };
		if (mqComp.addEventListener) mqComp.addEventListener('change', onMq); else if (mqComp.addListener) mqComp.addListener(onMq);
	}

	var lastTyping = 0;
	function sendTyping() {
		if (Date.now() - lastTyping < 4500) return;
		lastTyping = Date.now();
		api('chats/' + S.active + '/typing', { method: 'POST' }).catch(function () {});
	}

	function setNoteMode(on) {
		S.note = !!on;
		if (!el.composer) return;
		el.composer.classList.toggle('is-note', S.note);
		el.noteTag.hidden = !S.note;
		el.input.placeholder = S.note ? t('Yalnız temsilcilerin göreceği notu yazın') : phMsg();
		updateSendBtn();
		renderChips();
		lpCheck(true);
		if (on) el.input.focus();
	}

	function setQuoted(m) {
		S.quoted = m;
		if (!el.replyBar) return;
		clear(el.replyBar);
		el.replyBar.hidden = !m;
		if (!m) return;
		el.replyBar.appendChild(h('div', { class: 'flxc-quote flxc-quote-' + m.dir },
			h('strong', { text: m.dir === 'in' ? (S.chats.get(S.active) || {}).title || '' : t('Siz') }),
			h('span', { text: m.body || ({ image: t('Fotoğraf'), video: t('Video'), audio: t('Sesli mesaj'), document: (m.media && m.media.name) || t('Belge') }[m.type] || '') })
		));
		el.replyBar.appendChild(btn('close', t('Yanıtı iptal et'), function () { setQuoted(null); }));
		el.input.focus();
	}

	var tmpSeq = 0;
	function optimistic(c, data) {
		var st = chatStore(c.id);
		++tmpSeq;
		var m = Object.assign({
			id: -tmpSeq, tmp: true, chat: c.id, dir: data.type === 'note' ? 'note' : 'out', type: data.type === 'note' ? 'text' : data.type,
			body: data.body || '', status: 'pending', user: S.me.name, userId: S.me.id, source: 'agent', ts: Date.now(), rev: 0, react: {},
			quoted: S.quoted ? { id: S.quoted.id, dir: S.quoted.dir, type: S.quoted.type, body: S.quoted.body } : null
		}, data.extra || {});
		st.byId.set(m.id, m); st.list.push(m);
		if (S.active === c.id) {
			var p = st.list[st.list.length - 2];
			if (!p || dayStart(p.ts) !== dayStart(m.ts)) el.inner.appendChild(h('div', { class: 'flxc-day' }, h('span', { text: dayLabel(m.ts) })));
			el.inner.appendChild(bubble(m, !sameGroup(p, m)));
			scrollBottom(true);
		}
		return m;
	}
	function settle(c, tmp, real) {
		var st = chatStore(c.id);
		var i = st.list.indexOf(tmp);
		if (i > -1) st.list.splice(i, 1);
		st.byId.delete(tmp.id);
		var node = el.inner && el.inner.querySelector('[data-mid="' + tmp.id + '"]');
		if (real && !st.byId.has(real.id)) {
			st.byId.set(real.id, real);
			st.list.push(real);
			st.list.sort(function (a, b) { return (a.ts - b.ts) || (a.id - b.id); });
			if (node && S.active === c.id) node.replaceWith(bubble(real, !sameGroup(st.list[st.list.indexOf(real) - 1], real)));
		} else if (real && node) {
			node.remove();
			applyMessages([real]);
		} else if (node) node.remove();
		// Sohbet listesi önizlemesi
		if (real && real.dir !== 'note') {
			c.last = { dir: 'out', type: real.type, body: real.body || '', status: real.status, user: S.me.name };
			c.lastTs = real.ts;
			c.unread = 0;
			if (!c.assigned && real.source === 'agent') c.assigned = S.me.id;
			if (S.order.indexOf(c.id) < 0 && chatMatchesView(c)) S.order.unshift(c.id);
			sortOrder(); renderList(); renderChatHead();
		}
	}

	function sendText(textOverride, source) {
		var c = S.chats.get(S.active);
		var text = textOverride !== undefined ? textOverride : el.input.value;
		if (!c || !text.trim()) return;
		var type = S.note ? 'note' : 'text';
		var quoted = S.quoted;
		if (textOverride === undefined || source === 'ai') aiHide();
		var lp = textOverride === undefined ? lpTake() : null;
		var preview = !(lp && lp.off);
		var tmp = optimistic(c, { type: type, body: text, extra: preview ? (lp && lp.data ? { lp: { url: lp.url, title: lp.data.title, desc: lp.data.desc, thumb: lp.data.image } } : {}) : { lp: false } });
		if (textOverride === undefined) { el.input.value = ''; store('draft_' + c.id, ''); autoGrow(); updateSendBtn(); }
		setQuoted(null);
		closePopovers();
		if (type === 'note') setNoteMode(false);
		api('chats/' + c.id + '/messages', { method: 'POST', body: { type: type, body: text, quoted: quoted ? quoted.id : 0, source: source || '', preview: preview } })
			.then(function (r) { settle(c, tmp, r.message); if (r.message.status === 'failed') toast(r.message.error || t('Gönderilemedi'), 'err'); })
			.catch(function (e) { settle(c, tmp, null); toast(e.message, 'err'); el.input.value = text; autoGrow(); updateSendBtn(); });
	}

	function sendFile(file, caption, kind) {
		var c = S.chats.get(S.active);
		if (!c) return;
		var type = kind || (file.type.indexOf('image/') === 0 ? 'image' : file.type.indexOf('video/') === 0 ? 'video' : 'document');
		var localUrl = URL.createObjectURL(file);
		var tmp = optimistic(c, { type: type, body: caption || '', extra: { media: { url: localUrl, name: file.name, size: file.size, mime: file.type, ptt: type === 'audio' } } });
		var fd = new FormData();
		fd.append('file', file, file.name);
		fd.append('type', type);
		fd.append('body', caption || '');
		if (S.quoted) fd.append('quoted', S.quoted.id);
		setQuoted(null);
		api('chats/' + c.id + '/messages', { method: 'POST', form: fd })
			.then(function (r) { settle(c, tmp, r.message); if (r.message.status === 'failed') toast(r.message.error || t('Gönderilemedi'), 'err'); })
			.catch(function (e) { settle(c, tmp, null); toast(e.message, 'err'); })
			.then(function () { setTimeout(function () { URL.revokeObjectURL(localUrl); }, 60000); });
	}

	function attachMenu(anchor) {
		var items = [
			{ icon: 'image', label: t('Fotoğraf ve video'), fn: function () { el.fileIn.accept = 'image/*,video/*'; el.fileIn.click(); } },
			{ icon: 'doc', label: t('Belge'), fn: function () { el.fileIn.accept = ''; el.fileIn.click(); } },
			{ icon: 'bolt', label: t('Hazır cevap'), fn: function () { openSlash(''); } },
			{ icon: 'note', label: t('Dahili not'), fn: function () { setNoteMode(true); } }
		];
		if (S.ai) items.push({ icon: 'ai', label: t('Yapay zekâ önerisi'), fn: function () { aiSuggest({ fresh: true }); } });
		menu(anchor, items, { alignLeft: true });
	}

	/* ---------- yapay zekâ önerisi (mesaj kutusunun üstünde) ---------- */

	var aiSeq = 0;
	function aiHide() {
		aiSeq++;
		if (!el.aiBar) return;
		el.aiBar.hidden = true;
		clear(el.aiBar);
	}
	function aiSuggest(opts) {
		opts = opts || {};
		var c = S.chats.get(S.active);
		if (!c || !S.ai || !el.aiBar) return;
		if (c.optout) { aiHide(); if (!opts.auto) toast(t('Bu kişi kara listede; mesaj gönderilmez.'), 'err'); return; }
		var my = ++aiSeq;
		clear(el.aiBar);
		el.aiBar.hidden = false;
		el.aiBar.appendChild(h('div', { class: 'flxc-ai is-loading' },
			h('div', { class: 'flxc-ai-h' }, icon('ai'), h('span', { text: t('Öneri hazırlanıyor…') }), btn('close', t('Kapat'), aiHide)),
			h('div', { class: 'flxc-ai-sk' }, h('i'), h('i'), h('i'))
		));
		api('chats/' + c.id + '/ai', { method: 'POST', body: { fresh: !!opts.fresh, hint: opts.hint || '' } }).then(function (r) {
			if (my !== aiSeq || S.active !== c.id) return;
			aiShow(c, r.text || '');
		}).catch(function (e) {
			if (my !== aiSeq || S.active !== c.id) return;
			clear(el.aiBar);
			if (opts.auto) { el.aiBar.hidden = true; return; }
			el.aiBar.appendChild(h('div', { class: 'flxc-ai is-err' },
				h('div', { class: 'flxc-ai-h' }, icon('warn'), h('span', { text: e.message }), btn('reopen', t('Tekrar dene'), function () { aiSuggest({ fresh: true }); }), btn('close', t('Kapat'), aiHide))
			));
		});
	}
	function aiShow(c, text) {
		clear(el.aiBar);
		if (!text) { el.aiBar.hidden = true; return; }
		var hintIn = h('input', { type: 'text', class: 'flxc-ai-hint', placeholder: t('İstek yazın: daha kısa, fiyatı da söyle…'), 'aria-label': t('Öneri için istek'),
			onkeydown: function (e) { if (e.key === 'Enter' && hintIn.value.trim()) { e.preventDefault(); aiSuggest({ hint: hintIn.value.trim() }); } } });
		el.aiBar.appendChild(h('div', { class: 'flxc-ai' },
			h('div', { class: 'flxc-ai-h' }, icon('ai'), h('span', { text: t('Önerilen cevap') }), btn('close', t('Kapat'), aiHide)),
			h('div', { class: 'flxc-ai-t', html: waFormat(text) }),
			h('div', { class: 'flxc-ai-acts' },
				h('button', { type: 'button', class: 'flxc-btn flxc-btn-sm flxc-btn-primary', onclick: function () { aiHide(); sendText(text, 'ai'); } }, icon('send'), t('Gönder')),
				h('button', { type: 'button', class: 'flxc-btn flxc-btn-sm', onclick: function () {
					aiHide();
					el.input.value = text; el.input.dispatchEvent(new Event('input'));
					autoGrow(); updateSendBtn(); el.input.focus();
					try { el.input.setSelectionRange(text.length, text.length); } catch (e) { /* yok */ }
				} }, icon('edit'), t('Düzenle')),
				h('button', { type: 'button', class: 'flxc-btn flxc-btn-sm flxc-btn-ghost', title: t('Başka öneri'), onclick: function () { aiSuggest({ fresh: true }); } }, icon('reopen'), t('Yenile')),
				hintIn
			)
		));
	}
	/* Son mesaj kişiden geldiyse öneri kendiliğinden (ayar açıksa). */
	var aiAutoT = 0;
	function aiAuto(chatId, delay) {
		if (!S.ai || !S.ai.auto || S.note) return;
		clearTimeout(aiAutoT);
		aiAutoT = setTimeout(function () {
			if (S.active !== chatId || el.input.value.trim()) return;
			var c = S.chats.get(chatId);
			if (c && c.bot === 'on') return; // asistan cevaplıyor
			var st = chatStore(chatId), last = null;
			for (var i = st.list.length - 1; i >= 0; i--) { var m = st.list[i]; if (m.dir === 'in' || m.dir === 'out') { last = m; break; } }
			if (last && last.dir === 'in' && Date.now() - last.ts < 7 * 86400000) aiSuggest({ auto: true });
		}, delay || 600);
	}

	/* ---------- bağış asistanı: sohbet başına aç/kapat ---------- */

	function botPill(c) {
		if (!S.bot || !c.bot || c.group) return null;
		var lbl = { on: t('Asistan açık'), paused: t('Asistan beklemede'), off: t('Asistan kapalı') }[c.bot];
		var tip = { on: t('Asistan bu sohbete otomatik cevap veriyor. Kapatmak için tıklayın.'), paused: t('Bir temsilci yazdığı için asistan bir süre susuyor. Hemen açmak için tıklayın.'), off: t('Bu sohbette asistan kapalı. Açmak için tıklayın.') }[c.bot];
		return h('button', { type: 'button', class: 'flxc-botpill is-' + c.bot, title: tip, onclick: function () {
			api('chats/' + c.id + '/bot', { method: 'POST', body: { state: c.bot === 'on' ? 'off' : 'on' } }).then(function (r) {
				upsertChat(r.chat); renderChatHead(); renderList();
				toast(r.chat.bot === 'on' ? t('Asistan açıldı') : t('Asistan kapatıldı'));
			}).catch(function (e) { toast(e.message, 'err'); });
		} }, icon('bot'), h('span', { text: lbl }));
	}

	/* Sürükle bırak */
	function bindDrop(zone) {
		// Kaplama her sohbette yeniden eklenir (buildChat alanı temizler) ama dinleyiciler bir kez bağlanır.
		// 1.47.2: önceden her sohbet açılışında yeni dinleyici ekleniyordu; 3 sohbet açınca 1 dosya 3 kez gidiyordu.
		el.dropOver = h('div', { class: 'flxc-drop', hidden: true }, icon('attach'), h('span', { text: t('Eklemek için dosyayı bırakın') }));
		zone.appendChild(el.dropOver);
		if (zone.flxcDrop) return;
		zone.flxcDrop = true;
		var depth = 0;
		var show = function (on) { if (el.dropOver) el.dropOver.hidden = !on; };
		zone.addEventListener('dragenter', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') > -1) { depth++; show(true); e.preventDefault(); } });
		zone.addEventListener('dragover', function (e) { if (depth) e.preventDefault(); });
		zone.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) show(false); });
		zone.addEventListener('drop', function (e) {
			depth = 0; show(false);
			var files = Array.prototype.slice.call((e.dataTransfer && e.dataTransfer.files) || []);
			if (files.length) { e.preventDefault(); addFiles(files); }
		});
	}

	/* ---------- ekler: yazma alanının üstünde kartlar (1.46.0) ----------
	 * Sürüklenen / yapıştırılan / seçilen dosya hemen sunucuya yüklenir; kartta küçük önizleme,
	 * kaldır düğmesi ve köşede yükleme çubuğu var. Hepsi yüklenmeden Gönder çalışmaz.
	 * Yazma alanındaki metin ilk eke açıklama olarak gider. */

	var ATT_MAX = 10;
	// Yazma alanı odağını kaybetmesin: telefonda klavye kapanınca düzen kayar, dokunuş kaçar.
	function keepFocus(e) { e.preventDefault(); }
	function attKind(f) { return f.type.indexOf('image/') === 0 && f.type !== 'image/svg+xml' ? 'image' : f.type.indexOf('video/') === 0 ? 'video' : 'document'; }
	function attBusy() { return S.att.some(function (a) { return !a.token; }); }
	function addFiles(files) {
		if (!files.length || !S.active) return;
		if (S.note) setNoteMode(false);
		var room = ATT_MAX - S.att.length;
		if (files.length > room) toast(t('En fazla {n} dosya eklenebilir.', { n: ATT_MAX }), 'err');
		files.slice(0, Math.max(0, room)).forEach(function (f) {
			var a = { file: f, kind: attKind(f), url: URL.createObjectURL(f), pct: 0, token: '', err: '', xhr: null };
			S.att.push(a);
			attUpload(a);
		});
		renderAtt();
		el.input.focus();
	}
	function attUpload(a) {
		a.err = ''; a.pct = 0;
		var fd = new FormData();
		fd.append('file', a.file, a.file.name);
		var x = new XMLHttpRequest();
		a.xhr = x;
		x.open('POST', url('uploads'));
		x.setRequestHeader('X-WP-Nonce', nonce);
		x.upload.onprogress = function (e) { if (e.lengthComputable) { a.pct = Math.min(99, Math.round(e.loaded / e.total * 100)); attPaint(a); } };
		x.onload = function () {
			a.xhr = null;
			var j = {};
			try { j = JSON.parse(x.responseText); } catch (e) { /* boş */ }
			if (x.status >= 200 && x.status < 300 && j.token) { a.token = j.token; a.kind = j.type || a.kind; a.pct = 100; }
			else a.err = (j && j.message) || t('Yüklenemedi');
			attPaint(a); updateSendBtn();
		};
		x.onerror = function () { a.xhr = null; a.err = t('Bağlantı hatası'); attPaint(a); updateSendBtn(); };
		x.send(fd);
	}
	function attRemove(a, keepFile) {
		var i = S.att.indexOf(a);
		if (i > -1) S.att.splice(i, 1);
		if (a.xhr) { a.xhr.abort(); a.xhr = null; }
		if (a.token && !keepFile) api('uploads/' + a.token, { method: 'DELETE' }).catch(function () {});
		if (!keepFile) URL.revokeObjectURL(a.url);
	}
	function attReset() {
		(S.att || []).slice().forEach(function (a) { attRemove(a); });
		S.att = [];
	}
	function renderAtt() {
		if (!el.attBar) return;
		clear(el.attBar);
		el.attBar.hidden = !S.att.length;
		S.att.forEach(function (a) {
			var prev = a.kind === 'image' ? h('img', { src: a.url, alt: '' })
				: a.kind === 'video' ? h('video', { src: a.url + '#t=0.1', muted: true, preload: 'metadata', playsinline: true })
				: h('span', { class: 'flxc-att-doc' }, icon('doc'), h('small', { text: (a.file.name.split('.').pop() || '').slice(0, 5).toUpperCase() }));
			a.el = h('div', { class: 'flxc-att is-' + a.kind, title: a.file.name + ' · ' + fmtSize(a.file.size) },
				prev,
				a.kind === 'video' ? h('span', { class: 'flxc-att-play' }, icon('play')) : null,
				a.kind === 'document' ? h('span', { class: 'flxc-att-name', text: a.file.name }) : null,
				h('button', { type: 'button', class: 'flxc-att-x', 'aria-label': t('Kaldır'), title: t('Kaldır'), onmousedown: keepFocus, onclick: function () { attRemove(a); renderAtt(); updateSendBtn(); } }, icon('close')),
				h('span', { class: 'flxc-att-bar' }, h('i')),
				h('button', { type: 'button', class: 'flxc-att-err', title: t('Tekrar dene'), onmousedown: keepFocus, onclick: function () { attUpload(a); attPaint(a); updateSendBtn(); } }, icon('reopen'), h('span'))
			);
			el.attBar.appendChild(a.el);
			attPaint(a);
		});
		updateSendBtn();
	}
	function attPaint(a) {
		if (!a.el) return;
		a.el.classList.toggle('is-up', !a.token && !a.err);
		a.el.classList.toggle('is-done', !!a.token);
		a.el.classList.toggle('is-err', !!a.err);
		a.el.querySelector('.flxc-att-bar i').style.width = (a.token ? 100 : a.pct) + '%';
		var eb = a.el.querySelector('.flxc-att-err');
		eb.querySelector('span').textContent = a.err ? t('Yüklenemedi') : '';
		eb.setAttribute('aria-label', a.err ? a.err + ' · ' + t('Tekrar dene') : t('Tekrar dene'));
		if (a.err) eb.title = a.err + ' · ' + t('Tekrar dene');
	}
	function sendAtt() {
		var c = S.chats.get(S.active);
		if (!c || !S.att.length) return;
		if (attBusy()) { toast(t('Dosyalar yükleniyor, biraz bekleyin.')); return; }
		var caption = el.input.value;
		var quoted = S.quoted;
		S.att.slice().forEach(function (a, i) {
			var body = i === 0 ? caption : '';
			var tmp = optimistic(c, { type: a.kind, body: body, extra: { media: { url: a.url, name: a.file.name, size: a.file.size, mime: a.file.type } } });
			if (i === 0) setQuoted(null);
			var fd = new FormData();
			fd.append('upload', a.token);
			fd.append('type', a.kind);
			fd.append('body', body);
			if (quoted && i === 0) fd.append('quoted', quoted.id);
			attRemove(a, true);
			api('chats/' + c.id + '/messages', { method: 'POST', form: fd })
				.then(function (r) { settle(c, tmp, r.message); if (r.message.status === 'failed') toast(r.message.error || t('Gönderilemedi'), 'err'); })
				.catch(function (e) { settle(c, tmp, null); toast(e.message, 'err'); })
				.then(function () { setTimeout(function () { URL.revokeObjectURL(a.url); }, 60000); });
		});
		el.input.value = ''; store('draft_' + c.id, ''); autoGrow();
		lpTake(); aiHide(); closePopovers();
		renderAtt();
	}

	/* ---------- ses kaydı ---------- */

	var rec = null;
	function startRecord() {
		if (rec || !navigator.mediaDevices || !window.MediaRecorder) { toast(t('Bu tarayıcı ses kaydını desteklemiyor.'), 'err'); return; }
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			var types = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'];
			var type = types.filter(function (x) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(x); })[0] || '';
			var mr = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
			var chunks = [];
			var started = Date.now();
			rec = { mr: mr, stream: stream, cancel: false };
			mr.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
			mr.onstop = function () {
				stream.getTracks().forEach(function (tr) { tr.stop(); });
				var r = rec; rec = null; renderRecBar(false);
				if (r.cancel || Date.now() - started < 700) return;
				var mime = (mr.mimeType || type || 'audio/webm').split(';')[0];
				var ext = mime.indexOf('ogg') > -1 ? 'ogg' : mime.indexOf('mp4') > -1 ? 'm4a' : 'webm';
				var file = new File(chunks, 'sesli-mesaj.' + ext, { type: mime });
				sendFile(file, '', 'audio');
			};
			mr.start(250);
			renderRecBar(true, started);
			api('chats/' + S.active + '/typing', { method: 'POST', body: { state: 'recording' } }).catch(function () {});
		}).catch(function () { toast(t('Mikrofona erişilemedi.'), 'err'); });
	}
	var recTimer;
	function renderRecBar(on, started) {
		clearInterval(recTimer);
		var old = el.composer && el.composer.querySelector('.flxc-rec');
		if (old) old.remove();
		if (!on || !el.composer) return;
		var tm = h('span', { class: 'flxc-rec-t', text: '0:00' });
		var bar = h('div', { class: 'flxc-rec' },
			btn('trash', t('Kaydı sil'), function () { if (rec) { rec.cancel = true; rec.mr.stop(); } }, 'flxc-rec-del'),
			h('span', { class: 'flxc-rec-dot' }), tm, h('span', { class: 'flxc-rec-l', text: t('Kaydediliyor…') }),
			h('button', { type: 'button', class: 'flxc-send', 'aria-label': t('Gönder'), onclick: function () { if (rec) rec.mr.stop(); } }, icon('send'))
		);
		el.composer.appendChild(bar);
		recTimer = setInterval(function () { tm.textContent = fmtSecs((Date.now() - started) / 1000); }, 250);
	}

	/* ---------- emoji ---------- */

	var EMOJI = {
		'🕒': '',
		'😀': '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 😉 😍 🥰 😘 😋 😜 🤗 🤭 🤔 🤐 😐 😏 😌 😴 😷 🤒 🥳 😎 🤓 😕 😟 😮 😲 😳 🥺 😢 😭 😱 😤 😡',
		'👍': '👍 👎 👌 ✌️ 🤞 🤝 🤲 🙏 👏 🙌 👋 💪 👀 👉 👈 👆 👇 ☝️ ✍️ ✋ 🤚 🖐️ 🫶 🫡 🤙 💁 🙋 🙆 🙅 🤷 🤦 🧕 👨‍👩‍👧 👶 🧓',
		'❤️': '❤️ 🧡 💛 💚 💙 💜 🤎 🖤 🤍 💔 ❤️‍🩹 💕 💞 💓 💗 💖 💝 💯 ✨ ⭐ 🌟 🔥 🎉 🎊 🎁 🎈 🏆 🥇 ✅ ☑️ ❌ ⚠️ ❓ ❗ ‼️ 📌 📍 🔔 🆗 🆕 ➡️ ⬅️ 🔝',
		'🌱': '🌱 🌿 ☘️ 🍀 🌳 🌲 🌴 🌵 🌾 🍃 🍂 🍁 🌸 🌼 🌻 🌷 🌹 💐 🌍 🌎 🏞️ ⛰️ ☀️ 🌤️ ⛅ 🌧️ ❄️ 🌈 💧 🌊 🌙 ⭐ 🕊️ 🐝 🦋 🐦 🐑 🐄 🍎 🍞 🫖 ☕ 🍲 🥘 🍉 🥗',
		'📞': '📞 ☎️ 📱 💬 🗨️ 📧 📨 📅 🗓️ 🕒 ⏰ ⏳ 📝 📄 📃 📑 📎 🔗 🖨️ 💳 💰 💵 💸 🏦 🧾 🏠 🏢 🕌 🏫 🏥 🚚 🚗 🚌 ✈️ 📦 🔒 🔑 💡 📢 📣 📷 🎥 🎤 🔍 ⚙️ 🛠️'
	};
	var EMO_KEYS = Object.keys(EMOJI);
	function emoRecentList() { return (store('emo_recent') || '').split(' ').filter(Boolean); }
	function emoRecent(e) {
		var l = emoRecentList().filter(function (x) { return x !== e; });
		l.unshift(e);
		store('emo_recent', l.slice(0, 24).join(' '));
	}
	/* Sekmeli emoji seçici; mesaj kutusu ve tepkiler ortak kullanır. */
	function emojiPicker(onPick) {
		var wrap = h('div', { class: 'flxc-emo' });
		var grid = h('div', { class: 'flxc-emo-grid' });
		var tabs = h('div', { class: 'flxc-emo-tabs' });
		function showCat(k) {
			clear(grid);
			var list = k === '🕒' ? emoRecentList() : EMOJI[k].split(' ');
			if (!list.length) grid.appendChild(h('p', { class: 'flxc-emo-empty', text: t('Son kullanılan emoji yok.') }));
			list.forEach(function (e) {
				grid.appendChild(h('button', { type: 'button', text: e, onclick: function (ev) { ev.stopPropagation(); onPick(e); } }));
			});
			Array.prototype.forEach.call(tabs.children, function (b) { b.classList.toggle('is-on', b.dataset.k === k); });
		}
		EMO_KEYS.forEach(function (k) {
			tabs.appendChild(h('button', { type: 'button', text: k, 'data-k': k, title: k === '🕒' ? t('Son kullanılanlar') : '', onclick: function (ev) { ev.stopPropagation(); showCat(k); } }));
		});
		wrap.appendChild(tabs);
		wrap.appendChild(grid);
		showCat(emoRecentList().length ? '🕒' : EMO_KEYS[1]);
		return wrap;
	}
	function toggleEmoji() {
		if (!el.pop.hidden && el.pop.dataset.kind === 'emoji') { closePopovers(); return; }
		el.pop.dataset.kind = 'emoji';
		clear(el.pop);
		el.pop.appendChild(emojiPicker(function (e) { emoRecent(e); insertAtCursor(e); }));
		el.pop.hidden = false;
	}
	/* Sertifikayı mesaja ekleme: kayıtlı WhatsApp taslağı ya da "Ad · Sertifika" + link. */
	function certInsertItems(rec, after) {
		var put = function (text) {
			var cur = el.input.value;
			el.input.value = cur.trim() ? cur.replace(/\s+$/, '') + '\n\n' + text : text;
			el.input.dispatchEvent(new Event('input'));
			autoGrow(); updateSendBtn(); el.input.focus();
			if (after) after();
		};
		return [
			{ icon: 'send', label: t('Sertifika mesajını ekle'), fn: function () {
				var cid = S.active;
				api('records/certificates/' + rec.id, { query: { message: 1, chat: cid } }).then(function (r) {
					if (S.active === cid) put(r.message || rec.url);
				}).catch(function (e) { toast(e.message, 'err'); });
			} },
			{ icon: 'link', label: t('Yalnız linki ekle'), fn: function () {
				put([rec.name, rec.title].filter(Boolean).join(' · ') + '\n' + rec.url);
			} }
		];
	}

	function insertAtCursor(s) {
		var i = el.input, a = i.selectionStart || i.value.length, b = i.selectionEnd || a;
		i.value = i.value.slice(0, a) + s + i.value.slice(b);
		i.selectionStart = i.selectionEnd = a + s.length;
		i.dispatchEvent(new Event('input'));
		if (!isMobile()) i.focus();
	}
	function closePopovers() {
		if (el.pop && !el.pop.hidden) { el.pop.hidden = true; el.pop.dataset.kind = ''; return true; }
		return false;
	}
	document.addEventListener('click', function (e) {
		if (el.pop && !el.pop.hidden && !el.pop.contains(e.target) && !e.target.closest('.flxc-emo-b') && e.target !== el.input) closePopovers();
	});

	/* ---------- hazır cevaplar: "/" seçici ---------- */

	var slashSel = 0, slashItems = [];
	function checkSlash() {
		var v = el.input.value;
		var m = /^\/([^\s]*)$/.exec(v);
		if (m && !S.note) openSlash(m[1]);
		else if (el.pop.dataset.kind === 'slash') closePopovers();
	}
	function openSlash(q) {
		q = (q || '').toLocaleLowerCase(LOC);
		slashItems = S.replies.filter(function (r) {
			if (!q) return true;
			return (r.shortcut + ' ' + r.title + ' ' + r.body).toLocaleLowerCase(LOC).indexOf(q) > -1;
		}).sort(function (a, b) {
			var as = a.shortcut.indexOf(q) === 0 ? 0 : 1, bs = b.shortcut.indexOf(q) === 0 ? 0 : 1;
			return (as - bs) || (b.uses - a.uses);
		});
		slashSel = 0;
		el.pop.dataset.kind = 'slash';
		el.pop.hidden = false;
		renderSlash();
	}
	function renderSlash() {
		clear(el.pop);
		var head = h('div', { class: 'flxc-qr-head' }, icon('bolt'), h('strong', { text: t('Hazır cevaplar') }),
			h('button', { type: 'button', class: 'flxc-link', onclick: function () { closePopovers(); openRepliesManager(); } }, t('Düzenle')));
		el.pop.appendChild(head);
		var list = h('div', { class: 'flxc-qr-list', role: 'listbox' });
		if (!slashItems.length) list.appendChild(h('div', { class: 'flxc-qr-empty', text: S.replies.length ? t('Eşleşen hazır cevap yok.') : t('Henüz hazır cevap yok. "Düzenle" ile ekleyin.') }));
		slashItems.forEach(function (r, i) {
			list.appendChild(h('button', {
				type: 'button', role: 'option', class: 'flxc-qr-i' + (i === slashSel ? ' is-on' : ''), 'aria-selected': i === slashSel ? 'true' : 'false',
				onmouseenter: function () { slashSel = i; Array.prototype.forEach.call(list.children, function (n, j) { n.classList.toggle('is-on', j === i); }); },
				onclick: function () { useReply(r); }
			},
				h('span', { class: 'flxc-qr-top' }, r.shortcut ? h('code', { text: '/' + r.shortcut }) : null, h('strong', { text: r.title }), r.category ? h('em', { text: r.category }) : null),
				h('span', { class: 'flxc-qr-body', text: r.body })
			));
		});
		el.pop.appendChild(list);
		var on = list.querySelector('.is-on');
		if (on) on.scrollIntoView({ block: 'nearest' });
	}
	function popKeys(e) {
		if (el.pop.hidden || el.pop.dataset.kind !== 'slash') return false;
		if (e.key === 'ArrowDown') { slashSel = Math.min(slashItems.length - 1, slashSel + 1); renderSlash(); e.preventDefault(); return true; }
		if (e.key === 'ArrowUp') { slashSel = Math.max(0, slashSel - 1); renderSlash(); e.preventDefault(); return true; }
		if ((e.key === 'Enter' || e.key === 'Tab') && slashItems[slashSel]) { e.preventDefault(); useReply(slashItems[slashSel]); return true; }
		return false;
	}
	function useReply(r) {
		closePopovers();
		var chatId = S.active;
		api('replies/' + r.id + '/use', { method: 'POST', body: { chat: chatId } }).then(function (res) {
			if (S.active !== chatId) return;
			r.uses++;
			var text = res.text || r.body;
			var cur = el.input.value.replace(/^\/\S*$/, '');
			el.input.value = cur ? cur + (/\s$/.test(cur) ? '' : ' ') + text : text;
			el.input.dispatchEvent(new Event('input'));
			el.input.focus();
			el.input.selectionStart = el.input.selectionEnd = el.input.value.length;
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	/* ---------- hazır cevap yöneticisi ---------- */

	var VARS = [['{first_name}', 'Ad'], ['{name}', 'Ad soyad'], ['{phone}', 'Telefon'], ['{agent}', 'Temsilci'], ['{brand}', 'Kurum'], ['{site}', 'Web sitesi'], ['{date}', 'Tarih']];
	function openRepliesManager(editId) {
		var q = '';
		var listEl = h('div', { class: 'flxc-rm-list' });
		var formEl = h('div', { class: 'flxc-rm-form' });
		var search = h('input', { type: 'search', class: 'flxc-field', placeholder: t('Hazır cevaplarda ara'), oninput: function () { q = search.value.toLocaleLowerCase(LOC); renderL(); } });
		function renderL() {
			clear(listEl);
			var cats = {};
			S.replies.filter(function (r) { return !q || (r.shortcut + ' ' + r.title + ' ' + r.body).toLocaleLowerCase(LOC).indexOf(q) > -1; }).forEach(function (r) {
				(cats[r.category || t('Genel')] = cats[r.category || t('Genel')] || []).push(r);
			});
			Object.keys(cats).forEach(function (cat) {
				listEl.appendChild(h('h4', { text: cat }));
				cats[cat].forEach(function (r) {
					listEl.appendChild(h('button', { type: 'button', class: 'flxc-rm-i', onclick: function () { edit(r); } },
						h('span', { class: 'flxc-qr-top' }, r.shortcut ? h('code', { text: '/' + r.shortcut }) : null, h('strong', { text: r.title })),
						h('span', { class: 'flxc-qr-body', text: r.body }),
						r.uses ? h('small', { text: t('{n} kez kullanıldı', { n: r.uses }) }) : null
					));
				});
			});
			if (!listEl.children.length) listEl.appendChild(h('p', { class: 'flxc-muted', text: t('Hazır cevap bulunamadı.') }));
		}
		function edit(r) {
			r = r || { id: 0, title: '', shortcut: '', category: '', body: '' };
			dlg.el.classList.add('is-editing');
			clear(formEl);
			var title = h('input', { type: 'text', class: 'flxc-field', value: r.title, placeholder: t('Örn. Bağış bilgisi') });
			var shortcut = h('input', { type: 'text', class: 'flxc-field', value: r.shortcut, placeholder: t('ornek') });
			var cat = h('input', { type: 'text', class: 'flxc-field', value: r.category, placeholder: t('Genel'), list: 'flxc-cats' });
			var dl = h('datalist', { id: 'flxc-cats' });
			Array.from(new Set(S.replies.map(function (x) { return x.category; }).filter(Boolean))).forEach(function (c) { dl.appendChild(h('option', { value: c })); });
			var body = h('textarea', { class: 'flxc-field', rows: '6', placeholder: t('Mesaj metni') });
			body.value = r.body;
			var chips = h('div', { class: 'flxc-vars' }, h('small', { text: t('Değişken ekle:') }));
			VARS.forEach(function (v) {
				chips.appendChild(h('button', { type: 'button', class: 'flxc-chip flxc-chip-sm', title: v[0], onclick: function () {
					var a = body.selectionStart || body.value.length;
					body.value = body.value.slice(0, a) + v[0] + body.value.slice(body.selectionEnd || a);
					body.focus(); body.selectionStart = body.selectionEnd = a + v[0].length;
				} }, t(v[1])));
			});
			formEl.appendChild(h('div', { class: 'flxc-rm-form-head' }, btn('back', t('Listeye dön'), function () { dlg.el.classList.remove('is-editing'); }, 'flxc-rm-back'), h('strong', { text: r.id ? t('Hazır cevabı düzenle') : t('Yeni hazır cevap') })));
			formEl.appendChild(h('label', { class: 'flxc-lbl' }, t('Başlık'), title));
			formEl.appendChild(h('div', { class: 'flxc-2col' },
				h('label', { class: 'flxc-lbl' }, t('Kısayol'), h('span', { class: 'flxc-prefix' }, h('b', { text: '/' }), shortcut)),
				h('label', { class: 'flxc-lbl' }, t('Kategori'), cat, dl)
			));
			formEl.appendChild(h('label', { class: 'flxc-lbl' }, t('Mesaj'), body));
			formEl.appendChild(chips);
			formEl.appendChild(h('p', { class: 'flxc-help', text: t('Mesaj kutusuna / yazıp kısayolu yazınca bu cevap çıkar. Değişkenler gönderirken kişinin bilgisiyle dolar.') }));
			formEl.appendChild(h('div', { class: 'flxc-rm-actions' },
				r.id ? h('button', { type: 'button', class: 'flxc-btn flxc-btn-ghost is-danger', onclick: async function () {
					if (!(await window.flxDialog.confirm({ message: t('Bu hazır cevap silinsin mi?'), danger: true }))) return;
					api('replies/' + r.id, { method: 'DELETE' }).then(function (res) { S.replies = res.replies; dlg.el.classList.remove('is-editing'); renderL(); toast(t('Silindi')); });
				} }, icon('trash'), t('Sil')) : h('span'),
				h('button', { type: 'button', class: 'flxc-btn', onclick: function () {
					api('replies', { method: 'POST', body: { id: r.id, title: title.value, shortcut: shortcut.value, category: cat.value, body: body.value } }).then(function (res) {
						S.replies = res.replies; dlg.el.classList.remove('is-editing'); renderL(); toast(t('Kaydedildi'));
					}).catch(function (e) { toast(e.message, 'err'); });
				} }, icon('check'), t('Kaydet'))
			));
			setTimeout(function () { (r.id ? body : title).focus(); }, 30);
		}
		var dlg = modal({
			cls: 'flxc-rm', title: t('Hazır cevaplar'),
			head: h('button', { type: 'button', class: 'flxc-btn flxc-btn-sm', onclick: function () { edit(null); } }, icon('plus'), t('Yeni')),
			body: [h('div', { class: 'flxc-rm-left' }, search, listEl), formEl]
		});
		renderL();
		if (editId === 'new') edit(null);
	}

	/* ---------- yeni sohbet ---------- */

	function openNewChat(prefill, prefName) {
		var phone = h('input', { type: 'tel', class: 'flxc-field flxc-field-lg', placeholder: t('+90 5xx xxx xx xx'), autocomplete: 'tel', inputmode: 'tel' });
		var name = h('input', { type: 'text', class: 'flxc-field', placeholder: t('İsteğe bağlı') });
		var status = h('div', { class: 'flxc-nc-status' });
		var connSel = null;
		if (S.conns.length > 1) {
			connSel = h('select', { class: 'flxc-field' });
			S.conns.forEach(function (c) { connSel.appendChild(h('option', { value: c.id, text: c.label })); });
		}
		var go = h('button', { type: 'button', class: 'flxc-btn flxc-btn-block', disabled: true }, icon('newchat'), t('Sohbeti başlat'));
		var existing = 0;
		var check = debounce(function () {
			var v = phone.value.trim();
			clear(status);
			existing = 0;
			if (v.replace(/\D+/g, '').length < 10) { go.disabled = true; return; }
			status.appendChild(h('span', { class: 'flxc-muted' }, h('span', { class: 'flxc-spin flxc-spin-sm' }), t('Kontrol ediliyor…')));
			api('check-number', { method: 'POST', body: { phone: v, conn: connSel ? connSel.value : '' } }).then(function (r) {
				clear(status);
				if (!r.valid) { status.appendChild(h('span', { class: 'is-err' }, icon('warn'), t('Geçersiz numara'))); go.disabled = true; return; }
				go.disabled = r.exists === false;
				if (r.exists === true) status.appendChild(h('span', { class: 'is-ok' }, icon('check'), t('{p} WhatsApp kullanıyor', { p: r.phoneFmt })));
				else if (r.exists === false) status.appendChild(h('span', { class: 'is-err' }, icon('warn'), t('{p} numarasında WhatsApp yok', { p: r.phoneFmt })));
				else status.appendChild(h('span', { class: 'flxc-muted', text: t('{p} — WhatsApp kontrol edilemedi, yine de deneyebilirsiniz.', { p: r.phoneFmt }) }));
				if (r.chat) { existing = r.chat; status.appendChild(h('span', { class: 'flxc-muted', text: t('Bu kişiyle sohbet zaten var: {n}', { n: r.name }) })); go.lastChild.textContent = t('Sohbete git'); }
				else go.lastChild.textContent = t('Sohbeti başlat');
				if (r.name && !name.value) name.placeholder = r.name;
			}).catch(function () { clear(status); go.disabled = false; });
		}, 450);
		phone.addEventListener('input', check);
		if (connSel) connSel.addEventListener('change', check);
		go.addEventListener('click', function () {
			if (existing) { dlg.close(); openChat(existing); return; }
			go.disabled = true;
			api('chats', { method: 'POST', body: { phone: phone.value, name: name.value, conn: connSel ? connSel.value : '', check: 1 } }).then(function (r) {
				upsertChat(r.chat);
				if (S.order.indexOf(r.chat.id) < 0) S.order.unshift(r.chat.id);
				dlg.close();
				openChat(r.chat.id);
			}).catch(function (e) { go.disabled = false; toast(e.message, 'err'); });
		});
		[phone, name].forEach(function (i) { i.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !go.disabled) go.click(); }); });
		var dlg = modal({
			cls: 'flxc-nc', title: t('Yeni sohbet'),
			body: [
				h('div', { class: 'flxc-nc-art' }, icon('newchat')),
				h('label', { class: 'flxc-lbl' }, t('Telefon numarası'), phone),
				status,
				h('label', { class: 'flxc-lbl' }, t('Kişi adı'), name),
				connSel ? h('label', { class: 'flxc-lbl' }, t('Hangi hattan?'), connSel) : null,
				go,
				h('p', { class: 'flxc-help', text: t('Numarayı 0 ile, +90 ile ya da ülke koduyla yazabilirsiniz.') })
			]
		});
		if (prefill) { phone.value = prefill; check(); }
		if (prefName) name.value = prefName;
		setTimeout(function () { phone.focus(); }, 50);
	}

	/* ---------- kişi bilgisi ---------- */

	function openInfo() {
		if (!S.active) return;
		S.info = true;
		root.classList.add('is-info');
		el.info.setAttribute('aria-hidden', 'false');
		if (isMobile()) { try { history.pushState({ flxc: 'info' }, ''); } catch (e) { /* yok */ } }
		renderInfo();
		api('chats/' + S.active).then(function (r) {
			upsertChat(r.chat);
			S.sections[r.chat.id] = r.sections || [];
			S.clog[r.chat.id] = r.log || [];
			if (S.info && S.active === r.chat.id) renderInfo();
		}).catch(function () {});
	}
	// 1.41.0: kişi değişiklik geçmişi (kim, ne zaman, eski → yeni).
	S.clog = {};
	S.clogOpen = false;
	var refreshClog = debounce(function (id) {
		api('chats/' + id).then(function (r) {
			S.clog[id] = r.log || [];
			if (S.info && S.active === id) renderInfo();
		}).catch(function () {});
	}, 1200);
	var CLOG_FIELD = { name: 'Kişi adı', notes: 'Kişi notları', labels: 'Etiketler', assigned_to: 'Atanan temsilci', phone: 'Telefon', staff_user: 'Personel', blocked: 'Kara liste', memory: 'Asistan hafızası' };
	var CLOG_SRC = { staff: 'Personel', ai: 'Asistan', system: 'Sistem', contact: 'Kişi' };
	function clogSection(c) {
		var rows = S.clog[c.id] || [];
		if (!rows.length) return null;
		var list = h('div', { class: 'flxc-clog' });
		var max = S.clogOpen ? rows.length : 5;
		rows.slice(0, max).forEach(function (r) {
			var field = CLOG_FIELD[r.field] ? t(CLOG_FIELD[r.field]) : r.field;
			if (r.field === 'memory' && r.note) field += ' · ' + r.note;
			var val = r.field === 'notes'
				? h('span', { class: 'flxc-clog-v' }, t('Not güncellendi'))
				: r.field === 'blocked' ? h('span', { class: 'flxc-clog-v' }, r['new'] === '1' ? t('Kara listeye alındı') : t('Kara listeden çıkarıldı'))
				: h('span', { class: 'flxc-clog-v' },
					r.old ? h('del', { text: r.old }) : h('em', { text: t('(boş)') }),
					h('span', { class: 'flxc-clog-arrow', text: ' → ' }),
					r['new'] ? h('strong', { text: r['new'] }) : h('em', { text: t('(boş)') }));
			list.appendChild(h('div', { class: 'flxc-clog-row is-' + r.source, title: r.field === 'notes' ? (r['new'] || '') : null },
				h('div', { class: 'flxc-clog-top' }, h('b', { text: field }), h('time', { text: fmtDate.format(r.ts) + ' ' + fmtTime.format(r.ts), datetime: new Date(r.ts).toISOString() })),
				val,
				h('small', { class: 'flxc-clog-who' }, h('span', { class: 'flxc-clog-src', text: t(CLOG_SRC[r.source] || 'Sistem') }), r.source === 'staff' && r.user ? ' ' + r.user : '', r.note && r.field !== 'memory' ? ' · ' + r.note : '')
			));
		});
		var more = rows.length > 5 ? h('button', { type: 'button', class: 'flxc-link', onclick: function () { S.clogOpen = !S.clogOpen; renderInfo(); } },
			S.clogOpen ? t('Daha az göster') : t('Tümünü göster ({n})', { n: rows.length })) : null;
		return h('div', { class: 'flxc-info-sec' }, h('h4', null, icon('clock'), t('Değişiklik geçmişi')), list, more);
	}
	function closeInfo(silent) {
		if (!S.info) return;
		S.info = false;
		root.classList.remove('is-info');
		el.info.setAttribute('aria-hidden', 'true');
		if (!silent && isMobile()) history.back();
	}

	var saveNotes = debounce(function (id, v) { patchChat(id, { notes: v }).then(function () { toast(t('Not kaydedildi')); }); }, 900);
	function renderInfo() {
		var c = S.chats.get(S.active);
		clear(el.info);
		if (!c) return;
		var nameIn = h('input', { type: 'text', class: 'flxc-info-name-in', value: c.name, placeholder: c.pushName || c.phoneFmt, 'aria-label': t('Kişi adı') });
		nameIn.addEventListener('change', function () { patchChat(c.id, { name: nameIn.value.trim() }); });
		nameIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') nameIn.blur(); });
		var notes = h('textarea', { class: 'flxc-field', rows: '4', placeholder: t('Bu kişi hakkında yalnız temsilcilerin göreceği notlar') });
		notes.value = c.notes || '';
		notes.addEventListener('input', function () { saveNotes(c.id, notes.value); });
		var labels = h('div', { class: 'flxc-info-labels' });
		S.labels.forEach(function (l) {
			var on = c.labels.indexOf(l.id) > -1;
			labels.appendChild(h('button', { type: 'button', class: 'flxc-chip flxc-chip-label' + (on ? ' is-on' : ''), style: { '--lc': l.color }, onclick: function () {
				var ls = c.labels.slice();
				if (on) ls.splice(ls.indexOf(l.id), 1); else ls.push(l.id);
				patchChat(c.id, { labels: ls.length ? ls : [''] });
			} }, h('i', { class: 'flxc-dot' }), l.name));
		});
		if (!S.labels.length) labels.appendChild(h('small', { class: 'flxc-muted', text: t('Etiketler Ayarlar sayfasından tanımlanır.') }));
		var assign = h('select', { class: 'flxc-field', onchange: function () { patchChat(c.id, { assigned: +assign.value }); } },
			h('option', { value: '0', text: t('— Atanmadı —') }));
		S.agents.forEach(function (a) { assign.appendChild(h('option', { value: a.id, text: a.name, selected: a.id === c.assigned })); });
		// 1.36.0: personel bağlantısı (yönetici). WhatsApp numarayı gizlerse asistan personeli numaradan tanıyamaz.
		var staffSec = null;
		if (S.me && S.me.manage && S.staff && S.staff.length && !c.group) {
			var staff = h('select', { class: 'flxc-field', 'aria-label': t('Personel'), onchange: function () { patchChat(c.id, { staff_user: +staff.value }).then(function () { toast(+staff.value ? t('Sohbet personele bağlandı') : t('Personel bağlantısı kaldırıldı')); }); } },
				h('option', { value: '0', text: t('— Personel değil —') }));
			S.staff.forEach(function (u) { staff.appendChild(h('option', { value: u.id, text: u.name, selected: u.id === c.staffUser })); });
			var hidden = !c.phone && /@lid$/.test(c.jid || '');
			staffSec = h('div', { class: 'flxc-info-sec' }, h('h4', null, icon('person'), t('Personel')), staff,
				h('small', { class: 'flxc-help', text: hidden
					? t('WhatsApp bu kişinin numarasını gizliyor, asistan personeli numaradan tanıyamaz. Bu kişi ekibinizdense buradan bağlayın; asistan personel araçlarını açar.')
					: t('Asistan personeli Flux Roles profilindeki numaradan tanır. Numara eşleşmiyorsa buradan bağlayabilirsiniz; numarası bilinen sohbette numara önceliklidir.') }));
		}

		var sec = h('div');
		(S.sections[c.id] || []).forEach(function (s) {
			var box = h('div', { class: 'flxc-info-sec' }, h('h4', null, s.id === 'flxs' ? icon('cert') : s.id === 'flxb' ? icon('gift') : null, s.title));
			s.items.forEach(function (it) {
				box.appendChild(h('div', { class: 'flxc-info-item' },
					h('span', null, h('strong', { text: it.label }), it.sub ? h('small', { class: it.tone ? 'flxc-tone-t-' + it.tone : null, text: it.sub }) : null),
					it.url ? h('a', { class: 'flxc-ib', href: it.url, target: '_blank', rel: 'noopener', title: t('Aç') }, icon('open')) : null,
					it.cert ? btn('send', t('Mesaja ekle'), function (e) { menu(e.currentTarget, certInsertItems(it.cert, function () { if (isMobile()) closeInfo(); })); })
						: it.insert ? btn('send', t('Mesaja ekle'), function () { insertAtCursor((el.input.value && !/\s$/.test(el.input.value) ? ' ' : '') + it.insert); if (isMobile()) closeInfo(); }) : null
				));
			});
			sec.appendChild(box);
		});

		el.info.appendChild(h('header', { class: 'flxc-info-head' }, btn('close', t('Kapat'), function () { closeInfo(); }), h('strong', { text: t('Kişi bilgisi') })));
		el.info.appendChild(h('div', { class: 'flxc-info-body' },
			h('div', { class: 'flxc-info-hero' },
				// 1.47.0: fotoğrafa basınca büyük önizleme.
				c.avatar ? h('button', { type: 'button', class: 'flxc-info-av', title: t('Büyüt'), 'aria-label': t('Fotoğrafı büyüt'), onclick: function () { lightbox(c.avatar, 'image', { dir: 'in', sender: c.title }); } }, avatar(c, 'xl')) : avatar(c, 'xl'),
				h('label', { class: 'flxc-info-name' }, nameIn, icon('edit')),
				c.pushName && c.pushName !== c.name && (c.channel || 'whatsapp') === 'whatsapp' ? h('small', { class: 'flxc-muted', text: t('WhatsApp adı: {n}', { n: c.pushName }) }) : null,
				(c.channel || 'whatsapp') !== 'whatsapp' && chanById(c.channel) ? h('span', { class: 'flxc-chtag', style: { '--chc': chanById(c.channel).color } }, chanIcon(chanById(c.channel)), chanById(c.channel).label) : null,
				h('div', { class: 'flxc-info-phone' }, h('span', { text: c.phoneFmt || c.jid }),
					c.phone ? btn('copy', t('Numarayı kopyala'), function () { copyText('+' + c.phone); }) : null,
					c.phone ? h('a', { class: 'flxc-ib', href: 'tel:+' + c.phone, title: t('Ara') }, icon('phone')) : null
				)
			),
			h('div', { class: 'flxc-info-sec' }, h('h4', null, icon('label'), t('Etiketler')), labels),
			h('div', { class: 'flxc-info-sec' }, h('h4', null, icon('assign'), t('Atanan temsilci')), assign),
			staffSec,
			h('div', { class: 'flxc-info-sec' }, h('h4', null, icon('note'), t('Kişi notları')), notes),
			recOn() ? h('div', { class: 'flxc-info-sec flxc-info-actions' },
				S.rec.donations ? h('button', { type: 'button', class: 'flxc-info-act', onclick: function () { openDonation({ chat: c.id }); } }, icon('gift'), t('Bağış kaydı oluştur')) : null,
				S.rec.certificates ? h('button', { type: 'button', class: 'flxc-info-act', onclick: function () { openCertificate({ chat: c.id }); } }, icon('cert'), t('Sertifika oluştur')) : null
			) : null,
			sec,
			h('div', { class: 'flxc-info-sec flxc-info-actions' },
				h('button', { type: 'button', class: 'flxc-info-act', onclick: function () { patchChat(c.id, { pinned: c.pinned ? 0 : 1 }); } }, icon('pin'), c.pinned ? t('Sabitlemeyi kaldır') : t('Sohbeti sabitle')),
				c.status === 'closed'
					? h('button', { type: 'button', class: 'flxc-info-act', onclick: function () { patchChat(c.id, { status: 'open' }); } }, icon('reopen'), t('Sohbeti yeniden aç'))
					: h('button', { type: 'button', class: 'flxc-info-act is-danger', onclick: function () { patchChat(c.id, { status: 'closed' }); } }, icon('done'), t('Sohbeti kapat'))
			),
			clogSection(c),
			h('p', { class: 'flxc-help flxc-center', text: t('İlk kayıt: {d}', { d: fmtLong.format(c.created) }) })
		));
	}

	/* ---------- kayıtlar: bağış, sertifika, dekont, arama ---------- */

	var RX_RECEIPT = /dekont|makbuz|receipt|havale|eft|fatura|ödeme|odeme|transfer/i;
	S.rec = {};
	S.chatRecs = {};

	function recOn() { return S.rec && (S.rec.donations || S.rec.certificates); }

	function loadChatRecs(id) {
		if (!recOn()) return Promise.resolve(null);
		return api('records/chat/' + id).then(function (r) {
			S.chatRecs[id] = r;
			if (S.active === id) { renderChatHead(); refreshReceiptChips(); }
			return r;
		}).catch(function () { return null; });
	}
	function pendingFor(id) {
		var r = S.chatRecs[id];
		return r ? r.donations.filter(function (d) { return d.status === 'pending'; }) : [];
	}

	function money(n, cur) {
		try { return new Intl.NumberFormat(LOC, { style: 'currency', currency: cur || S.rec.currency || 'TRY', maximumFractionDigits: n % 1 ? 2 : 0 }).format(n); }
		catch (e) { return n + ' ' + (S.rec.symbol || ''); }
	}

	/** Mesaj içinde dekont rozeti ya da "eşleştir" çipi. */
	function receiptBar(m) {
		if (m.dir !== 'in' || (m.type !== 'image' && m.type !== 'document')) return null;
		var ai = m.rcheck ? h('div', { class: 'flxc-rai is-' + m.rcheck.v, title: t('Yapay zekâ ön kontrolü. Son kontrol ekipte.') }, icon(m.rcheck.v === 'match' ? 'check' : 'warn'), h('span', { text: 'AI · ' + m.rcheck.s })) : null;
		if (!recOn()) return ai;
		if (m.receipts && m.receipts.length) {
			var done = h('div', { class: 'flxc-rcpt is-done' }, icon('check'),
				h('span', { text: t('Dekont · {r}', { r: m.receipts.map(function (r) { return r.l; }).join(', ') }) }));
			return ai ? h('div', { class: 'flxc-rwrap' }, ai, done) : done;
		}
		if (ai && m.rcheck.v !== 'not_receipt') {
			return h('div', { class: 'flxc-rwrap' }, ai, h('button', { type: 'button', class: 'flxc-rcpt', onclick: function (e) { e.stopPropagation(); openReceipt(m); } }, icon('receipt'), t('Dekont olarak eşleştir')));
		}
		if (ai) return ai;
		var hint = RX_RECEIPT.test((m.body || '') + ' ' + ((m.media && m.media.name) || '')) || m.type === 'document' || pendingFor(m.chat).length;
		if (!hint || Date.now() - m.ts > 30 * 86400000) return null;
		return h('button', { type: 'button', class: 'flxc-rcpt', onclick: function (e) { e.stopPropagation(); openReceipt(m); } }, icon('receipt'), t('Dekont olarak eşleştir'));
	}
	function refreshReceiptChips() {
		if (!el.inner) return;
		var st = chatStore(S.active);
		st.list.forEach(function (m) {
			if (m.dir !== 'in' || (m.type !== 'image' && m.type !== 'document')) return;
			var node = el.inner.querySelector('[data-mid="' + m.id + '"]');
			if (!node) return;
			var old = node.querySelector('.flxc-rwrap') || node.querySelector('.flxc-rai') || node.querySelector('.flxc-rcpt');
			var nb = receiptBar(m);
			if (old) old.remove();
			if (nb) node.querySelector('.flxc-b').appendChild(nb);
		});
	}

	/* --- form yardımcıları --- */
	function fld(label, input, help) {
		return h('label', { class: 'flxc-lbl' }, label, input, help ? h('small', { class: 'flxc-help', text: help }) : null);
	}
	function sel(opts, val) {
		var s = h('select', { class: 'flxc-field' });
		opts.forEach(function (o) { s.appendChild(h('option', { value: o[0], text: o[1], selected: String(o[0]) === String(val) })); });
		return s;
	}
	function seg(opts, val, onchange) {
		var wrap = h('div', { class: 'flxc-seg', role: 'radiogroup' });
		wrap.value = val;
		opts.forEach(function (o) {
			var b = h('button', { type: 'button', role: 'radio', class: o[0] === val ? 'is-on' : '', 'aria-checked': o[0] === val ? 'true' : 'false', onclick: function () {
				wrap.value = o[0];
				Array.prototype.forEach.call(wrap.children, function (n) { n.classList.toggle('is-on', n === b); n.setAttribute('aria-checked', n === b ? 'true' : 'false'); });
				if (onchange) onchange(o[0]);
			} }, o[2] ? icon(o[2]) : null, o[1]);
			wrap.appendChild(b);
		});
		return wrap;
	}
	function check(label, on) {
		var i = h('input', { type: 'checkbox', checked: !!on });
		var l = h('label', { class: 'flxc-check' }, i, h('span', { text: label }));
		l.input = i;
		return l;
	}
	function stepper(val, min, max, onchange) {
		var inp = h('input', { type: 'number', class: 'flxc-field flxc-step-in', min: String(min), value: String(val), inputmode: 'numeric' });
		if (max) inp.max = String(max);
		var setv = function (v) { v = Math.max(min, max ? Math.min(max, v) : v); inp.value = v; onchange(v); };
		inp.addEventListener('input', function () { var v = parseInt(inp.value, 10); if (v >= min) onchange(v); });
		var w = h('div', { class: 'flxc-step' },
			h('button', { type: 'button', 'aria-label': '-', onclick: function () { setv((parseInt(inp.value, 10) || min) - 1); } }, '−'),
			inp,
			h('button', { type: 'button', 'aria-label': '+', onclick: function () { setv((parseInt(inp.value, 10) || 0) + 1); } }, '+'));
		w.input = inp;
		return w;
	}

	/* --- bağış kaydı --- */
	function openDonation(o) {
		o = o || {};
		var c = S.chats.get(o.chat || S.active);
		if (!c || !S.rec.donations) return;
		var types = S.rec.types || [];
		if (!types.length) { toast(t('Açık bağış türü yok. Bağışlar › Bağış türleri sayfasından açın.'), 'err'); return; }
		var st = { type: types[0], opt: null, qty: 1, status: o.receipt ? 'completed' : 'pending' };
		var typeSel = sel(types.map(function (x) { return [x.id, x.title]; }), types[0].id);
		var optWrap = h('div');
		var qtyWrap = h('div');
		var amountIn = h('input', { type: 'number', class: 'flxc-field', min: '0', step: '0.01', inputmode: 'decimal', placeholder: '0' });
		var amountLbl = fld(t('Tutar'), amountIn);
		var total = h('div', { class: 'flxc-total' });
		var nameIn = h('input', { type: 'text', class: 'flxc-field', value: (S.chatRecs[c.id] && S.chatRecs[c.id].donor && S.chatRecs[c.id].donor.name) || (c.name || c.pushName || '') });
		var phoneIn = h('input', { type: 'tel', class: 'flxc-field', value: c.phone ? '+' + c.phone : '' });
		var mailIn = h('input', { type: 'email', class: 'flxc-field', value: (S.chatRecs[c.id] && S.chatRecs[c.id].donor && S.chatRecs[c.id].donor.email) || '', placeholder: t('İsteğe bağlı') });
		var methods = S.rec.methods || {};
		var methodSel = sel(Object.keys(methods).map(function (k) { return [k, methods[k]]; }), 'bank_transfer');
		var refIn = h('input', { type: 'text', class: 'flxc-field', placeholder: t('İsteğe bağlı') });
		var noteIn = h('textarea', { class: 'flxc-field', rows: '2', placeholder: t('Yalnız panelde görünür') });
		var benIn = h('textarea', { class: 'flxc-field', rows: '3', placeholder: t('Her satıra bir isim') });
		var proxyChk = check(t('Bağışçı vekâlet metnini onayladı'), false);
		var benBox = h('div', { hidden: true }, fld(t('Kimin adına'), benIn, t('Her adet için bir isim. Boş kalanlar bağışçı adına sayılır.')), proxyChk);
		var msgTa = h('textarea', { class: 'flxc-field', rows: '5' });
		var msgEdited = false;
		msgTa.addEventListener('input', function () { msgEdited = true; });
		var sendChk = check(t('Kişiye WhatsApp\'tan bilgi gönder'), true);
		sendChk.input.addEventListener('change', function () { msgTa.hidden = !sendChk.input.checked; });
		var statusSeg = seg([['pending', t('Ödeme bekleniyor'), 'clock'], ['completed', t('Ödendi'), 'check']], st.status, function (v) {
			st.status = v;
			if (!msgEdited) msgTa.value = v === 'pending' ? S.rec.msgPending : S.rec.msgDone;
		});
		msgTa.value = st.status === 'pending' ? S.rec.msgPending : S.rec.msgDone;

		var certChk = null, certBox = null, certTpl = null, certFields = {};
		if (S.rec.certificates && (S.rec.templates || []).length) {
			certChk = check(t('Sertifika da oluştur ve gönder'), false);
			certTpl = sel(S.rec.templates.map(function (x) { return [x.id, x.name]; }), S.rec.templates[0].id);
			certBox = h('div', { class: 'flxc-sub-box', hidden: true }, fld(t('Sertifika şablonu'), certTpl), h('small', { class: 'flxc-help', text: t('Ad soyad ve adet bağıştan alınır.') }));
			certChk.input.addEventListener('change', function () { certBox.hidden = !certChk.input.checked; });
		}

		function renderType() {
			var x = st.type;
			clear(optWrap); clear(qtyWrap);
			st.opt = x.options.length ? x.options[0] : null;
			if (x.options.length > 1 || (x.options.length && x.free)) {
				var os = sel((x.free ? [['', t('Serbest tutar')]] : []).concat(x.options.map(function (op) { return [op.key, op.label + (op.price ? ' · ' + money(op.price, x.currency) : '')]; })), st.opt ? st.opt.key : '');
				os.addEventListener('change', function () { st.opt = x.options.filter(function (op) { return op.key === os.value; })[0] || null; upd(); });
				optWrap.appendChild(fld(t('Seçenek'), os));
			}
			qtyWrap.appendChild(fld(t('Adet'), stepper(1, 1, x.maxQty || 0, function (v) { st.qty = v; upd(); })));
			st.qty = 1;
			benBox.hidden = !x.proxy;
			upd();
		}
		function upd() {
			var x = st.type, op = st.opt;
			var priced = op && op.price;
			qtyWrap.hidden = !op || !x.qty;
			amountLbl.hidden = !!op;
			var sum = priced ? op.price * st.qty : parseFloat(amountIn.value || '0');
			total.textContent = sum > 0 ? t('Toplam: {a}', { a: money(sum, x.currency) }) : '';
			if (op && !op.price) total.textContent = t('Bu seçeneğin fiyatı yok.');
		}
		typeSel.addEventListener('change', function () { st.type = types.filter(function (x) { return String(x.id) === typeSel.value; })[0]; renderType(); });
		amountIn.addEventListener('input', upd);

		var save = h('button', { type: 'button', class: 'flxc-btn' }, icon('check'), t('Kaydet'));
		save.addEventListener('click', function () {
			save.disabled = true;
			var body = {
				chat: c.id, type_id: st.type.id, option_key: st.opt ? st.opt.key : '', quantity: st.qty,
				amount: amountIn.value, method: methodSel.value, status: st.status,
				name: nameIn.value.trim(), phone: phoneIn.value.trim(), email: mailIn.value.trim(),
				reference: refIn.value.trim(), note: noteIn.value.trim(),
				beneficiaries: benIn.value, proxy_consent: proxyChk.input.checked ? 1 : 0,
				receipt: o.receipt ? o.receipt.id : 0,
				message: sendChk.input.checked ? msgTa.value : ''
			};
			var wantCert = certChk && certChk.input.checked;
			if (wantCert) {
				var tpl = S.rec.templates.filter(function (x) { return String(x.id) === certTpl.value; })[0];
				var vals = { ad_soyad: body.name };
				tpl.fields.forEach(function (f) { if (f.type === 'number' && f.key !== 'ad_soyad') vals[f.key] = String(st.qty); else if (!(f.key in vals) && f.default) vals[f.key] = f.default; });
				body.certificate = { template_id: tpl.id, values: vals };
			}
			api('records/donations', { method: 'POST', body: body }).then(function (r) {
				dlg.close();
				toast(t('Bağış kaydedildi: {d}', { d: r.donation.title + ' · ' + r.donation.money }));
				if (r.sendError) toast(r.sendError, 'err');
				if (r.certError) toast(r.certError, 'err');
				if (r.sent) applyMessages([r.sent]);
				if (r.receipt) applyMessages([r.receipt.message]);
				loadChatRecs(c.id);
				if (r.certificate && wantCert) sendCertificate(r.certificate, c.id);
				schedule(300);
			}).catch(function (e) { save.disabled = false; toast(e.message, 'err'); });
		});

		var dlg = modal({
			cls: 'flxc-rec-dlg', title: o.receipt ? t('Dekonttan bağış kaydı') : t('Bağış kaydı'),
			body: [
				o.receipt ? receiptPreview(o.receipt) : null,
				h('div', { class: 'flxc-grid2' }, fld(t('Bağış türü'), typeSel), optWrap),
				h('div', { class: 'flxc-grid2' }, qtyWrap, amountLbl),
				total,
				benBox,
				h('h5', { class: 'flxc-sec-t', text: t('Ödeme') }),
				statusSeg,
				h('div', { class: 'flxc-grid2' }, fld(t('Ödeme yolu'), methodSel), fld(t('Dekont / işlem no'), refIn)),
				h('h5', { class: 'flxc-sec-t', text: t('Bağışçı') }),
				fld(t('Ad soyad'), nameIn),
				h('div', { class: 'flxc-grid2' }, fld(t('Telefon'), phoneIn), fld(t('E-posta'), mailIn)),
				fld(t('Not'), noteIn),
				certChk, certBox,
				h('h5', { class: 'flxc-sec-t', text: t('Mesaj') }),
				sendChk, msgTa,
				h('small', { class: 'flxc-help', text: t('{donation}, {amount}, {bank} gibi değişkenler gönderirken doldurulur.') })
			],
			foot: save
		});
		renderType();
	}

	function receiptPreview(m) {
		var img = m.type === 'image' && m.media ? h('img', { src: m.media.url, alt: '' }) : h('span', { class: 'flxc-doc-i' }, icon('doc'));
		return h('div', { class: 'flxc-rc-prev' }, img, h('span', null, h('strong', { text: (m.media && m.media.name) || m.body || t('Fotoğraf') }), h('small', { text: fmtLong.format(m.ts) + ' ' + fmtTime.format(m.ts) })));
	}

	/* --- sertifika --- */
	function openCertificate(o) {
		o = o || {};
		var c = S.chats.get(o.chat || S.active);
		var tpls = S.rec.templates || [];
		if (!c || !S.rec.certificates) return;
		if (!tpls.length) { toast(t('Aktif sertifika şablonu yok.'), 'err'); return; }
		var tplSel = sel(tpls.map(function (x) { return [x.id, x.name]; }), tpls[0].id);
		var fieldsBox = h('div');
		var inputs = {};
		var donor = S.chatRecs[c.id] && S.chatRecs[c.id].donor;
		var defName = (donor && donor.name) || c.name || c.pushName || '';
		function renderFields() {
			var tpl = tpls.filter(function (x) { return String(x.id) === tplSel.value; })[0];
			var keep = {};
			Object.keys(inputs).forEach(function (k) { keep[k] = inputs[k].value; });
			inputs = {};
			clear(fieldsBox);
			tpl.fields.forEach(function (f) {
				var inp;
				var v = keep[f.key] !== undefined ? keep[f.key] : (f.key === 'ad_soyad' ? defName : f.default);
				if (f.type === 'textarea') inp = h('textarea', { class: 'flxc-field', rows: '3' });
				else if (f.type === 'select') inp = sel(f.options.split(/\r?\n/).filter(Boolean).map(function (x) { return [x, x]; }), v);
				else inp = h('input', { type: f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text', class: 'flxc-field', min: f.type === 'number' ? '1' : null });
				inp.value = v || '';
				inputs[f.key] = inp;
				fieldsBox.appendChild(fld(f.label + (f.required ? ' *' : ''), inp));
			});
		}
		tplSel.addEventListener('change', renderFields);
		var events = S.rec.events || [];
		var evSel = events.length ? sel([[0, t('Etkinlik yok')]].concat(events.map(function (e) { return [e.id, e.name + (e.date ? ' · ' + e.date : '')]; })), 0) : null;
		var sendChk = check(t('Kişiye WhatsApp\'tan gönder'), true);
		var save = h('button', { type: 'button', class: 'flxc-btn' }, icon('cert'), t('Oluştur'));
		save.addEventListener('click', function () {
			var vals = {};
			Object.keys(inputs).forEach(function (k) { vals[k] = inputs[k].value; });
			if (!String(vals.ad_soyad || '').trim()) { toast(t('Ad soyad gerekli.'), 'err'); return; }
			save.disabled = true;
			api('records/certificates', { method: 'POST', body: { chat: c.id, template_id: +tplSel.value, values: vals, event_id: evSel ? +evSel.value : 0, name: vals.ad_soyad, receipt: o.receipt ? o.receipt.id : 0, donation_id: o.donation || 0 } }).then(function (r) {
				dlg.close();
				toast(t('Sertifika oluşturuldu: {n}', { n: r.certificate.number }));
				loadChatRecs(c.id);
				if (sendChk.input.checked) sendCertificate(r.certificate, c.id);
				schedule(300);
			}).catch(function (e) { save.disabled = false; toast(e.message, 'err'); });
		});
		var dlg = modal({
			cls: 'flxc-rec-dlg', title: t('Sertifika oluştur'),
			body: [
				tpls.length > 1 ? fld(t('Şablon'), tplSel) : null,
				fieldsBox,
				evSel ? fld(t('Etkinlik'), evSel) : null,
				sendChk,
				h('small', { class: 'flxc-help', text: t('Mesaj metni Flux Certificates\'daki WhatsApp şablonundan gelir.') })
			],
			foot: save
		});
		renderFields();
	}

	/** Sertifika sayfası bir kez açılınca PDF ve önizleme görseli üretilir; link önizlemesi düzgün çıksın diye göndermeden önce bekle. */
	function warmCertificate(cert) {
		if (cert.files) return Promise.resolve(cert);
		return new Promise(function (resolve) {
			var fr = h('iframe', { src: cert.url, class: 'flxc-warm', 'aria-hidden': 'true', tabindex: '-1' });
			document.body.appendChild(fr);
			var tries = 0;
			var done = function (c) { fr.remove(); resolve(c || cert); };
			var poll = function () {
				tries++;
				api('records/certificates/' + cert.id).then(function (r) {
					if (r.certificate.files || tries > 16) done(r.certificate);
					else setTimeout(poll, 2500);
				}).catch(function () { if (tries > 16) done(); else setTimeout(poll, 2500); });
			};
			setTimeout(poll, 2500);
		});
	}
	function sendCertificate(cert, chatId) {
		toast(t('Sertifika hazırlanıyor…'));
		return warmCertificate(cert).then(function () {
			return api('records/certificates/' + cert.id + '/send', { method: 'POST', body: { chat: chatId } });
		}).then(function (r) {
			if (r.message) applyMessages([r.message]);
			toast(t('Sertifika gönderildi'));
			schedule(300);
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	/* --- dekont eşleştirme --- */
	function openReceipt(m) {
		var c = S.chats.get(m.chat);
		if (!c) return;
		var chosen = null;
		var listEl = h('div', { class: 'flxc-rc-list' });
		var paidChk = check(t('Bekleyen bağışı "Ödendi" yap'), true);
		var replyChk = check(t('Kişiye onay mesajı gönder'), true);
		var replyTa = h('textarea', { class: 'flxc-field', rows: '2' });
		replyTa.value = S.rec.msgReceipt || '';
		replyChk.input.addEventListener('change', function () { replyTa.hidden = !replyChk.input.checked; });
		var search = h('input', { type: 'search', class: 'flxc-field', placeholder: t('Başka kayıtta ara: ad, telefon, sertifika no') });
		var go = h('button', { type: 'button', class: 'flxc-btn', disabled: true }, icon('check'), t('Eşleştir'));
		function item(rec) {
			var b = h('button', { type: 'button', class: 'flxc-rc-i', onclick: function () {
				chosen = rec;
				Array.prototype.forEach.call(listEl.querySelectorAll('.flxc-rc-i'), function (n) { n.classList.toggle('is-on', n === b); });
				go.disabled = false;
				paidChk.hidden = !(rec.kind === 'donation' && rec.status === 'pending');
				replyChk.hidden = replyTa.hidden = rec.kind !== 'donation';
				if (rec.kind === 'donation') replyTa.hidden = !replyChk.input.checked;
			} },
				h('span', { class: 'flxc-rc-ic' }, icon(rec.kind === 'donation' ? 'gift' : 'cert')),
				h('span', { class: 'flxc-rc-t' },
					h('strong', { text: rec.kind === 'donation' ? rec.title + (rec.qty > 1 ? ' × ' + rec.qty : '') : rec.number }),
					h('small', { text: [rec.kind === 'donation' ? rec.money : rec.name, rec.date, rec.receipts ? t('{n} dekont', { n: rec.receipts }) : ''].filter(Boolean).join(' · ') })),
				rec.statusL ? h('em', { class: 'flxc-tone flxc-tone-' + (rec.tone || 'muted'), text: rec.statusL }) : null
			);
			return b;
		}
		function fill(recs, title) {
			if (!recs.length) return;
			listEl.appendChild(h('h5', { class: 'flxc-sec-t', text: title }));
			recs.forEach(function (r) { listEl.appendChild(item(r)); });
		}
		function renderOwn() {
			clear(listEl);
			var r = S.chatRecs[c.id] || { donations: [], certificates: [] };
			var pend = r.donations.filter(function (d) { return d.status === 'pending'; });
			fill(pend, t('Bekleyen bağışlar'));
			fill(r.donations.filter(function (d) { return d.status !== 'pending'; }), t('Bu kişinin bağışları'));
			if (S.rec.certificates) fill(r.certificates, t('Bu kişinin sertifikaları'));
			if (!listEl.children.length) listEl.appendChild(h('p', { class: 'flxc-muted flxc-center', text: t('Bu kişiye ait kayıt yok. Aşağıdan arayın ya da yeni bağış kaydı oluşturun.') }));
			var first = listEl.querySelector('.flxc-rc-i');
			if (first && pend.length) first.click();
		}
		search.addEventListener('input', debounce(function () {
			var q = search.value.trim();
			if (q.length < 2) { renderOwn(); return; }
			clear(listEl).appendChild(h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' })));
			var jobs = [];
			if (S.rec.donations) jobs.push(api('records/search', { query: { kind: 'donation', q: q } }));
			if (S.rec.certificates) jobs.push(api('records/search', { query: { kind: 'certificate', q: q } }));
			Promise.all(jobs).then(function (rs) {
				clear(listEl);
				rs.forEach(function (r) { fill(r.items, r.items[0] && r.items[0].kind === 'certificate' ? t('Sertifikalar') : t('Bağışlar')); });
				if (!listEl.children.length) listEl.appendChild(h('p', { class: 'flxc-muted flxc-center', text: t('Kayıt bulunamadı.') }));
			}).catch(function (e) { toast(e.message, 'err'); });
		}, 350));
		go.addEventListener('click', function () {
			if (!chosen) return;
			go.disabled = true;
			api('messages/' + m.id + '/receipt', { method: 'POST', body: {
				kind: chosen.kind, ref: chosen.id,
				paid: chosen.kind === 'donation' && !paidChk.hidden && paidChk.input.checked ? 1 : 0,
				reply: chosen.kind === 'donation' && replyChk.input.checked ? replyTa.value : ''
			} }).then(function (r) {
				dlg.close();
				applyMessages([r.message].concat(r.sent ? [r.sent] : []));
				toast(t('Dekont kayda eklendi'));
				loadChatRecs(c.id);
				schedule(300);
			}).catch(function (e) { go.disabled = false; toast(e.message, 'err'); });
		});
		var newBtn = S.rec.donations ? h('button', { type: 'button', class: 'flxc-btn flxc-btn-ghost', onclick: function () { dlg.close(); openDonation({ chat: c.id, receipt: m }); } }, icon('plus'), t('Yeni bağış kaydı')) : null;
		var dlg = modal({
			cls: 'flxc-rec-dlg', title: t('Dekontu kayıtla eşleştir'),
			body: [receiptPreview(m), search, listEl, paidChk, replyChk, replyTa],
			foot: [newBtn, go]
		});
		loadChatRecs(c.id).then(renderOwn);
		renderOwn();
	}

	/* --- kayıtlarda arama paneli --- */
	function openRecords(kind) {
		if (el.recs) { el.recs.remove(); el.recs = null; }
		var st = { kind: kind || (S.rec.donations ? 'donation' : 'certificate'), q: '', status: '', page: 1, more: false, loading: false };
		var listEl = h('div', { class: 'flxc-list flxc-recs-list' });
		var input = h('input', { type: 'search', class: 'flxc-search-in', placeholder: t('Ad, telefon, sertifika no ile ara'), autocomplete: 'off' });
		var filt = h('div', { class: 'flxc-filters' });
		function renderFilt() {
			clear(filt);
			var tabs = [];
			if (S.rec.donations || S.rec.search) tabs.push(['donation', t('Bağışlar')]);
			if (S.rec.certificates || S.rec.search) tabs.push(['certificate', t('Sertifikalar')]);
			tabs.forEach(function (x) {
				filt.appendChild(h('button', { type: 'button', class: 'flxc-chip' + (st.kind === x[0] ? ' is-on' : ''), onclick: function () { st.kind = x[0]; st.status = ''; renderFilt(); load(); } }, x[1]));
			});
			if (st.kind === 'donation') {
				filt.appendChild(h('span', { class: 'flxc-filt-sep' }));
				[['', t('Tümü')], ['pending', t('Bekleyen')], ['completed', t('Tamamlanan')]].forEach(function (x) {
					filt.appendChild(h('button', { type: 'button', class: 'flxc-chip flxc-chip-sm' + (st.status === x[0] ? ' is-on' : ''), onclick: function () { st.status = x[0]; renderFilt(); load(); } }, x[1]));
				});
			}
		}
		function load(more) {
			if (st.loading) return;
			st.loading = true;
			if (!more) { st.page = 1; clear(listEl).appendChild(h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' }))); }
			api('records/search', { query: { kind: st.kind, q: st.q, status: st.status, page: st.page } }).then(function (r) {
				if (!more) clear(listEl);
				r.items.forEach(function (x) { listEl.appendChild(recRow(x)); });
				st.more = r.more;
				if (!more && !r.items.length) listEl.appendChild(h('div', { class: 'flxc-empty-list', text: st.q ? t('Kayıt bulunamadı.') : t('Henüz kayıt yok.') }));
			}).catch(function (e) { toast(e.message, 'err'); }).then(function () { st.loading = false; });
		}
		listEl.addEventListener('scroll', function () {
			if (st.more && !st.loading && listEl.scrollTop + listEl.clientHeight > listEl.scrollHeight - 160) { st.page++; load(true); }
		});
		input.addEventListener('input', debounce(function () { st.q = input.value.trim(); load(); }, 300));
		var close = function () { panel.classList.remove('is-open'); setTimeout(function () { panel.remove(); if (el.recs === panel) el.recs = null; }, 200); };
		var panel = h('div', { class: 'flxc-recs' },
			h('header', { class: 'flxc-recs-head' }, btn('back', t('Geri'), close), h('strong', { text: t('Kayıtlar') })),
			h('div', { class: 'flxc-search' }, icon('search', 'flxc-search-i'), input),
			filt, listEl);
		panel.close = close;
		el.side.appendChild(panel);
		el.recs = panel;
		requestAnimationFrame(function () { panel.classList.add('is-open'); });
		renderFilt();
		load();
		setTimeout(function () { input.focus(); }, 220);
	}

	function recRow(rec, onPick) {
		var isD = rec.kind === 'donation';
		// 1.39.0: a donation opens its detail window (the menu's actions are inside it).
		// 1.46.0: a certificate opens its own detail window too (no far-away menu).
		var open = function () { if (isD) openDonationDetail(rec, onPick); else openCertificateDetail(rec, onPick); };
		var sn = !isD && rec.sends ? rec.sends : null, dn = !isD ? rec.donation : null;
		return h('div', { class: 'flxc-row flxc-rec-row', role: 'listitem', tabindex: '0', onclick: open, onkeydown: function (e) { if (e.key === 'Enter') open(e); } },
			h('span', { class: 'flxc-av flxc-rec-av flxc-rec-av-' + rec.kind }, icon(isD ? 'gift' : 'cert')),
			h('span', { class: 'flxc-row-main' },
				h('span', { class: 'flxc-row-top' }, h('strong', { class: 'flxc-row-name', text: rec.name || rec.phone || '—' }), h('time', { class: 'flxc-row-time', text: rec.date.split(' ').slice(0, 3).join(' ') })),
				h('span', { class: 'flxc-row-bot' },
					h('span', { class: 'flxc-row-prev', text: isD ? rec.title + (rec.qty > 1 ? ' × ' + rec.qty : '') + ' · ' + rec.money : rec.number + (rec.qty ? ' · ' + t('{n} adet', { n: rec.qty }) : '') + (rec.event ? ' · ' + rec.event : '') }),
					rec.statusL ? h('em', { class: 'flxc-tone flxc-tone-' + (rec.tone || 'muted'), text: rec.statusL }) : null,
					rec.receipts ? h('span', { class: 'flxc-rc-n', title: t('Dekont') }, icon('receipt'), String(rec.receipts)) : null,
					sn ? h('span', { class: 'flxc-rc-n flxc-cert-sent' + (sn.failed && !sn.sent ? ' is-bad' : sn.sent ? ' is-ok' : ''), title: sn.sent ? t('{n} kez gönderildi', { n: sn.sent }) : sn.failed ? t('Gönderilemedi') : sn.queued ? t('Sırada') : t('Henüz gönderilmedi') }, icon('send'), sn.sent ? String(sn.sent) : (sn.failed ? '!' : sn.queued ? '…' : '0')) : null,
					isD && rec.ref ? h('span', { class: 'flxc-ref' + (rec.refDonor ? ' is-donor' : ''), title: rec.refDonor ? t('Bağışçının önceki referansı') : t('Referans') }, icon('person'), rec.ref) : null
				),
				dn ? h('span', { class: 'flxc-row-bot flxc-cert-don' }, icon('gift'), h('span', { class: 'flxc-row-prev', text: [dn.title + (dn.qty > 1 ? ' × ' + dn.qty : ''), dn.money].filter(Boolean).join(' · ') }), dn.statusL ? h('em', { class: 'flxc-tone flxc-tone-' + (dn.tone || 'muted'), text: dn.statusL }) : null) : null
			)
		);
	}

	/** 1.39.0: one donation in full: amount and status, lines and names, receipts, certificates, donor, referral, actions. */
	function openDonationDetail(rec, onPick) {
		var body = h('div', { class: 'flxc-dd' }, h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' })));
		var foot = h('div', { class: 'flxc-dd-foot' });
		var dlg = modal({ cls: 'flxc-dd-dlg', title: t('Bağış'), body: body, foot: foot });
		function kv(k, v) { return v ? h('div', { class: 'flxc-dd-kv' }, h('span', { text: k }), h('strong', { text: v })) : null; }
		function sec(title, kids) { kids = kids.filter(Boolean); return kids.length ? h('section', { class: 'flxc-dd-sec' }, h('h3', { text: title }), kids) : null; }
		function person(av, name, lines, cls) {
			return h('div', { class: 'flxc-dd-person' + (cls ? ' ' + cls : '') }, h('span', { class: 'flxc-dd-av', text: (name || '?').charAt(0).toUpperCase() }),
				h('span', { class: 'flxc-dd-pmain' }, h('strong', { text: name || '—' }), lines.filter(Boolean).map(function (x) { return h('small', { text: x }); })));
		}
		function done() { if (onPick) { onPick(); if (S.active) loadChatRecs(S.active); } else if (el.recs) { el.recs.remove(); el.recs = null; openRecords('donation'); } }
		api('records/donations/' + rec.id).then(function (d) {
			clear(body); clear(foot);
			add(body, h('div', { class: 'flxc-dd-hero' },
				h('div', { class: 'flxc-dd-amt' }, h('strong', { text: d.total || d.money }), h('small', { text: [d.title + (d.qty > 1 ? ' × ' + d.qty : ''), d.method].filter(Boolean).join(' · ') })),
				h('em', { class: 'flxc-tone flxc-tone-' + (d.tone || 'muted'), text: d.statusL })));
			add(body, h('div', { class: 'flxc-dd-grid' },
				kv(t('Tarih'), [d.date, d.ago].filter(Boolean).join(' · ')),
				kv(t('Sipariş'), d.order ? '#' + d.order : ''),
				kv(t('Onay kodu'), d.track),
				kv(t('Kayıt kaynağı'), d.source),
				kv(t('Kaydı açan'), d.by)));
			add(body, sec(t('Kalemler'), (d.lines || []).map(function (l) {
				return h('div', { class: 'flxc-dd-line' }, h('span', { class: 'flxc-dd-lmain' }, h('strong', { text: l.title + (l.qty > 1 ? ' × ' + l.qty : '') }), l.type && l.type !== l.title ? h('small', { text: l.type }) : null, l.names && l.names.length ? h('small', { text: t('Adına: {n}', { n: l.names.join(', ') }) }) : null), h('span', { class: 'flxc-dd-lamt', text: l.money }));
			})));
			add(body, sec(t('Dekontlar'), (d.receiptFiles || []).map(function (r) {
				return h('a', { class: 'flxc-dd-file', href: r.url, target: '_blank', rel: 'noopener' }, icon('receipt'), h('span', null, h('strong', { text: r.name }), r.meta ? h('small', { text: r.meta }) : null, r.warn ? h('small', { class: 'flxc-dd-warn', text: r.warn }) : null), icon('open'));
			})));
			add(body, sec(t('Sertifikalar'), (d.certs || []).map(function (c) {
				return h('a', { class: 'flxc-dd-file', href: c.url, target: '_blank', rel: 'noopener' }, icon('cert'), h('span', null, h('strong', { text: c.number }), h('small', { text: [c.name, c.revoked ? t('İptal') : ''].filter(Boolean).join(' · ') })), icon('open'));
			})));
			add(body, h('div', { class: 'flxc-dd-people' },
				sec(t('Bağışçı'), [d.donor ? person('', d.donor.name, [d.donor.phone, d.donor.email, t('{n} ödenmiş bağış · {m}', { n: d.donor.gifts, m: d.donor.total })]) : h('p', { class: 'flxc-muted', text: t('İletişim bilgisi yok.') })]),
				sec(t('Referans'), [d.refInfo ? person('', d.refInfo.name, [d.refInfo.phone], 'is-ref') : (d.ref ? person('', d.ref, [t('Bağışçının önceki referansı')], 'is-ref is-donor') : h('p', { class: 'flxc-muted', text: t('Referans yok.') }))])));
			if (d.note) add(body, sec(t('Not'), [h('p', { class: 'flxc-dd-note', text: d.note })]));
			var phone = (d.phone || '').replace(/\D+/g, '');
			if (phone && !onPick) add(foot, h('button', { type: 'button', class: 'flxc-btn flxc-btn-ghost', onclick: function () { dlg.close(); goChat(phone, d.name); } }, icon('unread'), t('Sohbeti aç')));
			if (d.admin) add(foot, h('a', { class: 'flxc-btn flxc-btn-ghost', href: d.admin, target: '_blank', rel: 'noopener' }, icon('gear'), t('Bağış kaydını aç')));
			if (d.canPay) add(foot, h('button', { type: 'button', class: 'flxc-btn', onclick: async function () {
				if (!(await window.flxDialog.confirm({ message: t('Ödeme hesaba geldi mi? Onaylarsan sertifika oluşturulup bağışçıya gönderilir.') }))) return;
				api('records/donations/' + d.id + '/status', { method: 'POST', body: { status: 'completed' } }).then(function () { toast(t('Bağış onaylandı')); dlg.close(); done(); }).catch(function (e) { toast(e.message, 'err'); });
			} }, icon('check'), t('Ödendi olarak işaretle')));
		}).catch(function (e) { clear(body); add(body, h('div', { class: 'flxc-empty-list', text: e.message })); });
	}

	/** 1.46.0: one certificate in full (like a donation): preview, person, sends, linked donation, referral, actions. */
	function openCertificateDetail(rec, onPick) {
		var body = h('div', { class: 'flxc-dd flxc-cd' }, h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' })));
		var foot = h('div', { class: 'flxc-dd-foot flxc-cd-foot' });
		var dlg = modal({ cls: 'flxc-dd-dlg', title: t('Sertifika'), body: body, foot: foot });
		function kv(k, v) { return v ? h('div', { class: 'flxc-dd-kv' }, h('span', { text: k }), h('strong', { text: v })) : null; }
		function sec(title, kids) { kids = kids.filter(Boolean); return kids.length ? h('section', { class: 'flxc-dd-sec' }, h('h3', { text: title }), kids) : null; }
		function person(name, lines, cls) {
			return h('div', { class: 'flxc-dd-person' + (cls ? ' ' + cls : '') }, h('span', { class: 'flxc-dd-av', text: (name || '?').charAt(0).toUpperCase() }),
				h('span', { class: 'flxc-dd-pmain' }, h('strong', { text: name || '—' }), lines.filter(Boolean).map(function (x) { return h('small', { text: x }); })));
		}
		api('records/certificates/' + rec.id, { query: { detail: 1 } }).then(function (r) {
			var d = r.certificate;
			clear(body); clear(foot);
			var img = d.image ? h('button', { type: 'button', class: 'flxc-cd-img', title: t('Büyüt'), onclick: function () { lightbox(d.image, 'image', { body: d.name + ' · ' + d.number }); } },
				h('img', { src: d.image, alt: d.number, loading: 'lazy' })) : h('span', { class: 'flxc-cd-img is-empty' }, icon('cert'));
			add(body, h('div', { class: 'flxc-dd-hero flxc-cd-hero' }, img,
				h('div', { class: 'flxc-dd-amt' },
					h('strong', { text: d.number || d.code }),
					h('small', { text: d.name }),
					h('small', { text: [d.title, d.qty ? t('{n} adet', { n: d.qty }) : '', d.event].filter(Boolean).join(' · ') }),
					d.statusL ? h('em', { class: 'flxc-tone flxc-tone-failed', text: d.statusL }) : h('em', { class: 'flxc-tone flxc-tone-ok', text: t('Geçerli') }))));
			var sent = (d.sendList || []).filter(function (x) { return x.status === 'sent'; }).length;
			add(body, h('div', { class: 'flxc-dd-grid' },
				kv(t('Tarih'), [d.date, d.ago].filter(Boolean).join(' · ')),
				kv(t('Sertifika gönderimi'), sent ? t('{n} kez gönderildi', { n: sent }) : t('Henüz gönderilmedi')),
				kv(t('Görüntülenme'), String(d.views || 0)),
				kv(t('Kaydı açan'), d.by)));
			if (d.donation) {
				var dn = d.donation;
				add(body, sec(t('Bağlı bağış'), [h('button', { type: 'button', class: 'flxc-dd-file flxc-cd-don', onclick: function () { openDonationDetail(dn, onPick); } },
					icon('gift'), h('span', null, h('strong', { text: [dn.title + (dn.qty > 1 ? ' × ' + dn.qty : ''), dn.money].join(' · ') }), h('small', { text: [dn.date, dn.method, dn.receipts ? t('{n} dekont', { n: dn.receipts }) : ''].filter(Boolean).join(' · ') })),
					dn.statusL ? h('em', { class: 'flxc-tone flxc-tone-' + (dn.tone || 'muted'), text: dn.statusL }) : null)]));
			}
			add(body, sec(t('Gönderimler'), (d.sendList || []).map(function (x) {
				var tone = x.status === 'sent' ? 'ok' : x.status === 'failed' ? 'failed' : 'pending';
				return h('div', { class: 'flxc-dd-line' }, h('span', { class: 'flxc-dd-lmain' }, h('strong', { text: [x.channelL, x.to].filter(Boolean).join(' · ') }), h('small', { text: [x.date, x.by].filter(Boolean).join(' · ') }), x.error ? h('small', { class: 'flxc-dd-warn', text: x.error }) : null), h('em', { class: 'flxc-tone flxc-tone-' + tone, text: x.statusL }));
			})));
			add(body, h('div', { class: 'flxc-dd-people' },
				sec(t('Kişi'), [d.person ? person(d.person.name, [d.person.phone, d.person.email]) : h('p', { class: 'flxc-muted', text: t('İletişim bilgisi yok.') })]),
				sec(t('Referans'), [d.refInfo ? person(d.refInfo.name, [d.refInfo.phone], 'is-ref') : (d.donation && d.donation.ref ? person(d.donation.ref, [t('Bağıştaki referans')], 'is-ref') : h('p', { class: 'flxc-muted', text: t('Referans yok.') }))])));
			if (d.values && d.values.length) add(body, sec(t('Sertifika bilgileri'), [h('div', { class: 'flxc-dd-grid' }, d.values.map(function (v) { return kv(v.k, v.v); }))]));
			// Actions: insert into the open chat, open the chat, open the certificate / list.
			if (S.active) certInsertItems(d, function () { dlg.close(); if (onPick) onPick(); else if (isMobile() && el.recs) el.recs.close(); }).forEach(function (it, i) {
				add(foot, h('button', { type: 'button', class: 'flxc-btn' + (i ? ' flxc-btn-ghost' : ''), onclick: it.fn }, icon(it.icon), it.label));
			});
			var phone = (d.phone || '').replace(/\D+/g, '');
			if (phone && !onPick) add(foot, h('button', { type: 'button', class: 'flxc-btn flxc-btn-ghost', onclick: function () { dlg.close(); goChat(phone, d.name); } }, icon('unread'), t('Sohbeti aç')));
			if (d.url) add(foot, h('a', { class: 'flxc-btn flxc-btn-ghost', href: d.url, target: '_blank', rel: 'noopener' }, icon('open'), t('Sertifikayı aç')));
			if (d.admin) add(foot, h('a', { class: 'flxc-btn flxc-btn-ghost', href: d.admin, target: '_blank', rel: 'noopener' }, icon('gear'), t('Sertifika listesinde aç')));
		}).catch(function (e) { clear(body); add(body, h('div', { class: 'flxc-empty-list', text: e.message })); });
	}

	/** 1.15.0: sohbetteki kişinin bağış ve sertifikaları (sohbet üstündeki düğme). */
	function openPersonRecords(c) {
		var st = { kind: 'donation' }, filt = h('div', { class: 'flxc-filters' }), list = h('div', { class: 'flxc-list flxc-prec-list', role: 'list' });
		var dlg;
		function draw() {
			var r = S.chatRecs[c.id];
			clear(filt); clear(list);
			if (!r) { list.appendChild(h('div', { class: 'flxc-loading' }, h('span', { class: 'flxc-spin' }))); return; }
			[['donation', t('Bağışlar'), r.donations], ['certificate', t('Sertifikalar'), r.certificates]].forEach(function (x) {
				filt.appendChild(h('button', { type: 'button', class: 'flxc-chip' + (st.kind === x[0] ? ' is-on' : ''), onclick: function () { st.kind = x[0]; draw(); } }, x[1] + ' (' + x[2].length + ')'));
			});
			var items = st.kind === 'donation' ? r.donations : r.certificates;
			items.forEach(function (x) { list.appendChild(recRow(x, function () { dlg.close(); })); });
			if (!items.length) list.appendChild(h('div', { class: 'flxc-empty-list', text: st.kind === 'donation' ? t('Bu kişinin bağış kaydı yok.') : t('Bu kişinin sertifikası yok.') }));
		}
		dlg = modal({
			cls: 'flxc-prec-dlg', title: t('{n}: bağışlar ve sertifikalar', { n: c.title }),
			body: h('div', null, filt, list),
			foot: S.rec.donations ? h('button', { type: 'button', class: 'flxc-btn', onclick: function () { dlg.close(); openDonation({ chat: c.id }); } }, icon('gift'), t('Bağış kaydı oluştur')) : null
		});
		draw();
		loadChatRecs(c.id).then(function () { if (dlg.el.isConnected) draw(); });
	}

	function recMenu(rec, anchor, onPick) {
		var phone = (rec.phone || '').replace(/\D+/g, '');
		var items = [];
		if (phone && !onPick) items.push({ icon: 'unread', label: t('Sohbeti aç'), fn: function () { goChat(phone, rec.name); } });
		if (rec.kind === 'certificate' && S.active) certInsertItems(rec, function () { if (onPick) onPick(); else if (isMobile() && el.recs) el.recs.close(); }).forEach(function (it) { items.push(it); });
		if (rec.kind === 'certificate') items.push({ icon: 'open', label: t('Sertifikayı aç'), fn: function () { window.open(rec.url, '_blank', 'noopener'); } });
		if (rec.kind === 'donation' && rec.status === 'pending' && S.rec.donations) items.push({ icon: 'check', label: t('Ödendi olarak işaretle'), fn: function () {
			api('records/donations/' + rec.id + '/status', { method: 'POST', body: { status: 'completed' } }).then(function () { toast(t('Bağış onaylandı')); if (onPick) { onPick(); if (S.active) loadChatRecs(S.active); } else if (el.recs) { el.recs.remove(); el.recs = null; openRecords('donation'); } }).catch(function (e) { toast(e.message, 'err'); });
		} });
		if (rec.admin) items.push({ icon: 'gear', label: rec.kind === 'donation' ? t('Bağış kaydını aç') : t('Sertifika listesinde aç'), fn: function () { window.open(rec.admin, '_blank', 'noopener'); } });
		menu(anchor, items);
	}

	/** Telefon numarasıyla sohbeti bul ya da başlat. */
	function goChat(phone, name) {
		api('check-number', { method: 'POST', body: { phone: '+' + phone } }).then(function (r) {
			if (r.chat) { if (el.recs && isMobile()) el.recs.close(); openChat(r.chat); }
			else openNewChat('+' + phone, name);
		}).catch(function () { openNewChat('+' + phone, name); });
	}

	/* --- hazır cevap baloncukları --- */
	function renderChips() {
		if (!el.chips) return;
		clear(el.chips);
		var show = S.rec.chips !== false && S.replies.length && !S.note && store('chips') !== '0';
		el.chips.hidden = !show;
		if (!show) return;
		var list = S.replies.slice().sort(function (a, b) { return (b.uses - a.uses) || 0; }).slice(0, 14);
		list.forEach(function (r) {
			el.chips.appendChild(h('button', { type: 'button', class: 'flxc-qchip', title: r.body, onclick: function () { useReply(r); } }, r.title || ('/' + r.shortcut)));
		});
		el.chips.appendChild(h('button', { type: 'button', class: 'flxc-qchip flxc-qchip-edit', title: t('Hazır cevapları düzenle'), 'aria-label': t('Hazır cevapları düzenle'), onclick: function () { openRepliesManager(); } }, icon('edit')));
	}

	/* ---------- modal / ışık kutusu / bildirim ---------- */

	function modal(o) {
		var back = h('div', { class: 'flxc-modal-back' });
		var box = h('div', { class: 'flxc-modal ' + (o.cls || ''), role: 'dialog', 'aria-modal': 'true', 'aria-label': o.title || '' });
		var foot = o.foot ? h('footer', { class: 'flxc-modal-foot' }, o.foot) : null;
		var api2 = {
			el: box, foot: foot,
			close: function () { back.remove(); document.removeEventListener('keydown', onKey, true); }
		};
		function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); api2.close(); } }
		document.addEventListener('keydown', onKey, true);
		box.appendChild(h('header', { class: 'flxc-modal-head' }, btn('close', t('Kapat'), api2.close), h('strong', { text: o.title || '' }), o.head || null));
		box.appendChild(h('div', { class: 'flxc-modal-body' }, o.body));
		if (foot) box.appendChild(foot);
		back.appendChild(box);
		back.addEventListener('mousedown', function (e) { if (e.target === back) api2.close(); });
		root.appendChild(back);
		return api2;
	}

	function lightbox(src, kind, m) {
		var back = h('div', { class: 'flxc-lb', role: 'dialog', 'aria-modal': 'true' });
		var close = function () { back.remove(); document.removeEventListener('keydown', onKey, true); };
		function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
		document.addEventListener('keydown', onKey, true);
		back.appendChild(h('div', { class: 'flxc-lb-bar' },
			h('span', { class: 'flxc-lb-who' }, h('strong', { text: !m ? '' : m.dir === 'out' ? t('Siz') : (m.sender || (S.chats.get(S.active) || {}).title || '') }), m && m.ts ? h('small', { text: fmtLong.format(m.ts) + ' ' + fmtTime.format(m.ts) }) : null),
			h('a', { class: 'flxc-ib', href: src + '&download=1', download: (m && m.media && m.media.name) || '', title: t('İndir') }, icon('download')),
			btn('close', t('Kapat'), close)
		));
		if (kind === 'video') {
			var lv = h('video', { src: src, controls: true, autoplay: true, playsinline: true, preload: 'auto' });
			back.appendChild(lv);
			var pp = lv.play && lv.play();
			if (pp && pp.catch) pp.catch(function () {});
		} else {
			back.appendChild(h('img', { src: src, alt: (m && m.body) || '' }));
		}
		var cap = m && m.body ? h('div', { class: 'flxc-lb-cap', html: waFormat(m.body) }) : null;
		if (cap) back.appendChild(cap);
		back.addEventListener('click', function (e) { if (e.target === back || e.target.tagName === 'IMG') close(); });
		root.appendChild(back);
	}

	/* 1.47.1: indirme yeni sekme açmadan arka planda başlar (sunucu attachment döner, sayfa yerinde kalır). */
	function dlFile(u, name) {
		var a = h('a', { href: u, download: name || '', hidden: true });
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { a.remove(); }, 1000);
	}

	function toast(msg, kind) {
		if (!msg) return;
		var n = h('div', { class: 'flxc-toast' + (kind ? ' is-' + kind : ''), role: kind === 'err' ? 'alert' : 'status', text: msg });
		el.toast.appendChild(n);
		setTimeout(function () { n.classList.add('is-out'); setTimeout(function () { n.remove(); }, 300); }, kind === 'err' ? 5000 : 2400);
	}

	function soundOn() { return store('sound') !== '0'; }
	var actx = null;
	function ding() {
		if (!soundOn()) return;
		try {
			actx = actx || new (window.AudioContext || window.webkitAudioContext)();
			var now = actx.currentTime;
			[[880, 0], [1318.5, 0.11]].forEach(function (n) {
				var o = actx.createOscillator(), g = actx.createGain();
				o.type = 'sine'; o.frequency.value = n[0];
				g.gain.setValueAtTime(0.0001, now + n[1]);
				g.gain.exponentialRampToValueAtTime(0.18, now + n[1] + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, now + n[1] + 0.28);
				o.connect(g); g.connect(actx.destination);
				o.start(now + n[1]); o.stop(now + n[1] + 0.3);
			});
		} catch (e) { /* ses yok */ }
	}
	function askNotify() {
		Notification.requestPermission().then(function (p) { toast(p === 'granted' ? t('Bildirimler açıldı') : t('Bildirim izni verilmedi')); });
	}
	function notify(list) {
		var fresh = list.filter(function (n) { return !S.notified.has(n.id); });
		fresh.forEach(function (n) { S.notified.add(n.id); });
		if (!fresh.length) return;
		var visibleActive = !document.hidden && fresh.every(function (n) { return n.chat === S.active && nearBottom(); });
		if (visibleActive) return;
		ding();
		if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
			var n = fresh[0];
			try {
				var no = new Notification(n.title, { body: n.body, icon: n.avatar || B.icon || undefined, tag: 'flxc-' + n.chat });
				no.onclick = function () { window.focus(); openChat(n.chat); no.close(); };
			} catch (e) { /* yok */ }
		}
	}
	function updateTitle() {
		var base = B.title || document.title.replace(/^\(\d+\)\s*/, '');
		B.title = base;
		document.title = (S.unread ? '(' + S.unread + ') ' : '') + base;
	}

	/* ---------- canlı güncelleme ---------- */

	var syncTimer = null, syncFails = 0, syncing = false, syncQuiet = 0;
	function schedule(ms) {
		clearTimeout(syncTimer);
		syncTimer = setTimeout(sync, ms);
	}
	function interval() {
		var configured = Math.max(3, +B.poll || 10) * 1000;
		var fast = botActive() ? Math.min(configured, 3000) : configured;
		// Hata ve 429: katlanarak en çok 60 sn.
		if (syncFails) return Math.min(60000, fast * Math.pow(2, Math.min(6, syncFails)));
		// Arka plandaki sekme dakikada bir bakar (yeni mesaj bildirimi için); sekmeye dönünce hemen eşitlenir.
		if (document.hidden) return Math.max(configured, 60000);
		// 3 sn yalnız cevap akarken ve son yanıt değişmişken. Yoksa 15 sn tabanına adım adım çık.
		if (botActive() && !syncQuiet) return fast;
		var floor = Math.max(configured, 15000);
		if (fast >= floor) return fast;
		return Math.min(floor, fast + Math.min(syncQuiet, 3) * Math.ceil((floor - fast) / 3));
	}
	/** Asistan cevap hazırlıyor ya da az önce gelen mesaja cevap verecek: kısa aralıkla bakılır. */
	function botActive() {
		var now = Date.now(), on = false;
		S.chats.forEach(function (c) {
			if (c.botBusy || (c.bot === 'on' && c.last && c.last.dir === 'in' && now - c.lastInTs < 90000)) on = true;
		});
		return on;
	}
	function sync() {
		if (syncing) return;
		syncing = true;
		var ast = S.active && chatStore(S.active), after = 0;
		if (ast && ast.loaded) ast.list.forEach(function (m) { if (!m.tmp && m.id > after) after = m.id; });
		// Geniş pencere: yavaş sunucuda geç yazılan satırlar da kaçmasın (aynı rev'li satırlar istemcide atlanır).
		api('sync', { timeout: 20000, query: { since: S.rev ? S.rev - 15000 : 0, chat: S.active || '', after: after || '' } }).then(function (r) {
			syncFails = 0;
			var prevUnread = S.unread;
			S.rev = r.rev;
			if (r.nonce) nonce = r.nonce;
			S.conns = r.connections || S.conns;
			S.unread = r.unread;
			renderConn();
			var listChanged = false;
			(r.chats || []).forEach(function (c) {
				var old = S.chats.get(c.id);
				if (old && old.rev === c.rev) return;
				if (old && c.lastInTs > old.lastInTs && c.id !== S.active) S.fresh[c.id] = Date.now();
				upsertChat(c);
				listChanged = true;
				var inView = S.order.indexOf(c.id) > -1;
				if (!S.q && chatMatchesView(c) && !inView) S.order.push(c.id);
				if (!S.q && !chatMatchesView(c) && inView && c.id !== S.active) S.order.splice(S.order.indexOf(c.id), 1);
				if (c.id === S.active) { renderChatHead(); if (S.info) renderInfo(); }
			});
			if (listChanged) { sortOrder(); renderList(); }
			if (r.messages && r.messages.length) applyMessages(r.messages);
			if (r.incoming && r.incoming.length) notify(r.incoming);
			updateTitle();
			if (offlineBanner) { offlineBanner.remove(); offlineBanner = null; }
			var changed = listChanged || (r.messages && r.messages.length) || (r.incoming && r.incoming.length) || r.unread !== prevUnread;
			syncQuiet = changed ? 0 : Math.min(6, syncQuiet + 1);
		}).catch(function (e) {
			syncFails++;
			if (e.status === 403 || e.code === 'rest_cookie_invalid_nonce') showOffline(t('Oturum süresi doldu. Sayfayı yenileyin.'), true);
			else if (syncFails > 2) showOffline(t('Sunucuya ulaşılamıyor, yeniden deneniyor…'));
		}).then(function () {
			syncing = false;
			schedule(interval());
		});
	}
	var offlineBanner = null;
	function showOffline(msg, reload) {
		if (offlineBanner) offlineBanner.remove();
		offlineBanner = h('div', { class: 'flxc-offline' }, icon('warn'), h('span', { text: msg }), reload ? h('button', { type: 'button', class: 'flxc-link', onclick: function () { location.reload(); } }, t('Yenile')) : null);
		el.side.insertBefore(offlineBanner, el.side.children[1]);
	}
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { if (!syncing) schedule(interval()); return; }
		syncQuiet = 0;
		if (!syncing) schedule(0);
		markRead();
	});
	function wake() {
		if (!syncQuiet || syncFails || syncing || document.hidden) return;
		syncQuiet = 0;
		schedule(interval());
	}
	root.addEventListener('pointerdown', wake, true);
	root.addEventListener('keydown', wake, true);
	window.addEventListener('focus', function () { markRead(); });

	/* ---------- başlat ---------- */

	/* iOS'ta klavye açılınca yazma alanı görünür kalsın. */
	if (window.visualViewport) {
		var fitVV = function () {
			var vv = window.visualViewport;
			// Yalnız klavye açıkken (görünür alan küçülünce) sığdır; yoksa CSS (panel teması üst çubuğu vb.) karar verir.
			if (vv.height >= window.innerHeight - 60) {
				root.style.height = root.style.top = root.style.bottom = '';
				return;
			}
			root.style.top = vv.offsetTop + 'px';
			root.style.bottom = 'auto';
			root.style.height = Math.max(200, vv.offsetTop + vv.height - root.getBoundingClientRect().top) + 'px';
		};
		window.visualViewport.addEventListener('resize', fitVV);
		window.visualViewport.addEventListener('scroll', fitVV);
		fitVV();
	}

	buildShell();
	setView('list');
	api('bootstrap').then(function (b) {
		S.me = b.me; S.agents = b.agents; S.labels = b.labels; S.conns = b.connections; S.channels = b.channels || []; S.replies = b.replies; S.ai = b.ai; S.bot = !!b.bot; S.unread = b.unread; S.rev = b.rev; S.rec = b.records || {}; S.staff = b.staff || []; S.delHours = b.delHours == null ? 60 : +b.delHours;
		renderSideActions();
		renderMe(); renderConn(); renderFilters();
		if (!S.active && multiChannel()) renderEmptyMain(); // kanal bilgisiyle boş ekran metni
		return loadChats();
	}).then(function () {
		var qp = new URLSearchParams(location.search), want = +(qp.get('chat') || 0), wantPh = (qp.get('phone') || '').replace(/\D+/g, '');
		if (want) openChat(want);
		else if (wantPh) goChat(wantPh); // 1.39.0: Flux Donate "Open chat" (admin.php?page=flx-chat&phone=90...)
		updateTitle();
		schedule(interval());
		setTimeout(avatarSweep, 8000);
	}).catch(function (e) { toast(e.message, 'err'); });
})();
