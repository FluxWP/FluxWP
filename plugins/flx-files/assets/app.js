/* Flux Files – dosya yöneticisi arayüzü. Bağımlılık yok; editör WordPress'in CodeMirror'ı (wp.CodeMirror). */
(function () {
	'use strict';
	var C = window.FLXFM;
	var root = document.getElementById('flxfm-app');
	if (!C || !root) return;

	/*
	 * Uygulama pencereye sığar: sayfa kaymaz, yalnız liste, dosya ağacı ve dosya içeriği kendi içinde kayar.
	 * Yükseklik = pencere − uygulamanın üst kenarı − alt boşluk (Flux Admin mobil alt çubuğu dahil).
	 */
	function fit() {
		var top = root.getBoundingClientRect().top + (window.scrollY || 0);
		var wb = document.getElementById('wpbody-content');
		var pb = wb ? parseFloat(getComputedStyle(wb).paddingBottom) || 0 : 0;
		root.style.setProperty('--flxfm-h', Math.max(460, Math.floor(window.innerHeight - top - pb)) + 'px');
	}
	fit();
	if (window.innerWidth <= 782) root.classList.add('flxfm-tree-off'); // mobilde ağaç kapalı başlar, düğmeyle açılır
	window.addEventListener('resize', fit);
	window.addEventListener('orientationchange', function () { setTimeout(fit, 200); });

	/* ------------------------------------------------------------ yardımcılar */
	var L = C.i18n || {};
	function t(k, a, b) {
		var s = (a === 1 && L[k + '_1']) || L[k] || k;
		if (a !== undefined) s = s.replace('%1$s', a).replace('%s', a).replace('%d', a);
		if (b !== undefined) s = s.replace('%2$s', b);
		return s;
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function $(sel, el) { return (el || root).querySelector(sel); }
	function $$(sel, el) { return Array.prototype.slice.call((el || root).querySelectorAll(sel)); }
	function h(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstChild; }
	function base(p) { var i = p.lastIndexOf('/'); return i < 0 ? p : p.slice(i + 1); }
	function dir(p) { var i = p.lastIndexOf('/'); return i < 0 ? '' : p.slice(0, i); }
	function join(a, b) { return a ? a + '/' + b : b; }
	function size(n) {
		if (n == null) return '—';
		var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
		while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
		return (i ? n.toFixed(n < 10 ? 1 : 0) : n) + ' ' + u[i];
	}
	var D = window.flxDialog || {
		confirm: function (o) { return Promise.resolve(window.confirm((o.title || '') + '\n' + (o.message || ''))); }, // eslint-disable-line no-alert
		alert: function (o) { window.alert((o.title || '') + '\n' + (o.message || '')); return Promise.resolve(); }, // eslint-disable-line no-alert
		prompt: function (o) { return Promise.resolve(window.prompt(o.title || '', o.value || '')); }, // eslint-disable-line no-alert
		toast: function () {}
	};
	function toast(msg, type) { if (D.toast) D.toast(msg, { type: type || 'ok' }); }
	function fail(e) { toast(e && e.message ? e.message : t('error'), 'bad'); }
	function copyText(s) {
		var done = function () { toast(t('copied')); };
		if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(s).then(done, function () { legacy(); }); }
		else legacy();
		function legacy() { var a = document.createElement('textarea'); a.value = s; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); done(); } catch (e) { /* yoksay */ } a.remove(); }
	}

	/* İkonlar (Lucide tarzı çizgi). */
	var P = {
		folder: '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
		file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
		globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
		left: '<path d="m15 18-6-6 6-6"/>', right: '<path d="m9 18 6-6-6-6"/>', down: '<path d="m6 9 6 6 6-6"/>', up: '<path d="m18 15-6-6-6 6"/>',
		upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
		download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
		search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		list: '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
		more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
		trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/>',
		restore: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
		activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
		settings: '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
		ai: '<path d="M9.94 14.5A2 2 0 0 0 8.5 13.06l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 8.94A2 2 0 0 0 9.94 7.5l1.58-6.13a.5.5 0 0 1 .96 0L14.06 7.5A2 2 0 0 0 15.5 8.94l6.13 1.58a.5.5 0 0 1 0 .96L15.5 13.06a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/>',
		copy: '<rect x="8" y="8" width="14" height="14" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		cut: '<circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/>',
		paste: '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
		dup: '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M15 12v6"/><path d="M12 15h6"/><path d="M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2"/>',
		pencil: '<path d="M21.17 6.81a1 1 0 0 0-3.98-3.98L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/>',
		info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		archive: '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
		unarchive: '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="m9 15 3-3 3 3"/><path d="M12 12v6"/>',
		link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		eye: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		code: '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
		browser: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/>',
		x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		refresh: '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/><path d="M3 12A9 9 0 0 1 18.5 5.8L21 8"/><path d="M21 3v5h-5"/>',
		folderplus: '<path d="M12 10v6"/><path d="M9 13h6"/><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
		fileplus: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M9 15h6"/><path d="M12 18v-6"/>',
		share: '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/>',
		history: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
		sidebar: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
		save: '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
		sort: '<path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="m21 8-4-4-4 4"/><path d="M17 4v16"/>',
		image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
		shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'
	};
	function ic(n, cls) { return '<svg class="flxfm-i' + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || P.file) + '</svg>'; }

	/* Dosya simgesi: klasör, resim küçük resmi ya da renkli uzantı rozeti. */
	var EXT_COLORS = { php: 'violet', js: 'amber', mjs: 'amber', ts: 'blue', tsx: 'blue', jsx: 'amber', css: 'blue', scss: 'pink', html: 'orange', htm: 'orange', json: 'teal', xml: 'teal', md: 'slate', txt: 'slate', log: 'slate', sql: 'teal', yml: 'teal', yaml: 'teal', zip: 'amber', gz: 'amber', pdf: 'red', svg: 'green', po: 'slate', mo: 'slate', pot: 'slate' };
	var KIND_COLORS = { image: 'green', video: 'pink', audio: 'pink', archive: 'amber', pdf: 'red', doc: 'blue', font: 'slate', code: 'violet' };
	function fileIcon(it, big) {
		if (it.type === 'dir') return '<span class="flxfm-fi flxfm-fi--dir">' + ic('folder') + (it.shared ? '<span class="flxfm-fi-badge">' + ic('globe') + '</span>' : '') + '</span>';
		if (big && it.kind === 'image' && it.size < 8 * 1024 * 1024) return '<span class="flxfm-fi flxfm-fi--thumb"><img loading="lazy" alt="" src="' + esc(rawUrl(it.path)) + '"></span>';
		var name = it.name.toLowerCase();
		if (name === '.htaccess' || name === '.user.ini' || name === 'php.ini') return '<span class="flxfm-fi flxfm-fi--c-orange">' + ic('settings') + '</span>';
		// Uzun ya da yalnız noktayla başlayan adlar (.default_theme) rozet yerine düz dosya simgesi alır.
		var ext = (it.ext || '').length <= 4 && name !== '.' + (it.ext || '') ? (it.ext || '') : '';
		var col = EXT_COLORS[it.ext] || KIND_COLORS[it.kind] || 'slate';
		if (!ext) return '<span class="flxfm-fi flxfm-fi--c-slate">' + ic('file') + '</span>';
		return '<span class="flxfm-fi flxfm-fi--ext flxfm-fi--c-' + col + '">' + esc(ext) + '</span>';
	}

	/* ------------------------------------------------------------------ API */
	// Düz kalıcı bağlantılarda rest kökü "?rest_route=/…/" olur; sorgu ayırıcısını ona göre seç.
	function restUrl(route) {
		var u = C.rest + route;
		return u + (u.indexOf('?') > -1 ? '&' : '?') + '_locale=user&';
	}
	function api(op, data) {
		var body = Object.assign({ op: op }, data || {});
		return fetch(restUrl('op') + '_t=' + Date.now(), {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
			body: JSON.stringify(body)
		}).then(function (r) {
			return r.json().catch(function () { return { ok: false, message: t('error') + ' (HTTP ' + r.status + ')' }; });
		}).then(function (j) {
			if (!j || !j.ok) { var e = new Error((j && j.message) || t('error')); e.code = j && j.code; throw e; }
			return j;
		});
	}
	function rawUrl(p, dl) {
		if (!dl && C.uploads && (p === C.uploads.rel || p.indexOf(C.uploads.rel + '/') === 0)) {
			return C.uploads.url + p.slice(C.uploads.rel.length).split('/').map(encodeURIComponent).join('/');
		}
		return C.file + '&path=' + encodeURIComponent(encodeURIComponent(p)) + (dl ? '' : '&inline=1');
	}
	function download(paths) {
		var u = paths.length === 1 ? C.file + '&path=' + encodeURIComponent(encodeURIComponent(paths[0])) : C.file + '&paths=' + encodeURIComponent(encodeURIComponent(paths.join('\n')));
		var a = document.createElement('a'); a.href = u; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove();
	}
	function publicUrl(p) { return C.home + p.split('/').map(encodeURIComponent).join('/'); }

	/* ---------------------------------------------------------------- durum */
	var S = {
		mode: 'browse', cwd: '', items: [], dirInfo: null, sel: new Set(), anchor: null,
		sort: lsGet('sort', { key: 'name', dir: 1 }), view: lsGet('view', 'list'),
		back: [], fwd: [], clip: null, search: null, disk: null, loading: false
	};
	function lsGet(k, d) { try { var v = localStorage.getItem('flxfm-' + k); return v ? JSON.parse(v) : d; } catch (e) { return d; } }
	function lsSet(k, v) { try { localStorage.setItem('flxfm-' + k, JSON.stringify(v)); } catch (e) { /* yoksay */ } }

	/* --------------------------------------------------------------- iskelet */
	root.innerHTML =
		'<header class="flxfm-top">' +
			'<nav class="flxfm-trail" aria-label="' + esc(t('location')) + '"></nav>' +
			'<div class="flxfm-top-actions">' +
				'<div class="flxfm-seg" role="tablist">' +
					'<button type="button" role="tab" data-mode="browse">' + ic('browser') + '<span>' + esc(t('browser')) + '</span></button>' +
					'<button type="button" role="tab" data-mode="editor">' + ic('code') + '<span>' + esc(t('editor')) + '</span></button>' +
				'</div>' +
				(C.writable ? '<button type="button" class="flxfm-btn" data-menu="upload">' + ic('upload') + '<span>' + esc(t('upload')) + '</span>' + ic('down', 'flxfm-i-sm') + '</button>' +
				'<button type="button" class="flxfm-btn" data-menu="new">' + ic('plus') + '<span>' + esc(t('new')) + '</span>' + ic('down', 'flxfm-i-sm') + '</button>' : '') +
				'<button type="button" class="flxfm-btn flxfm-btn--ic" data-menu="more" aria-label="' + esc(t('more')) + '">' + ic('more') + '</button>' +
			'</div>' +
		'</header>' +
		(C.writable ? '' : '<div class="flxfm-banner">' + ic('lock') + esc(t('readonly_mode')) + '</div>') +
		'<section class="flxfm-card flxfm-browse">' +
			'<div class="flxfm-bar">' +
				'<div class="flxfm-nav"><button type="button" class="flxfm-ib" data-act="back" aria-label="' + esc(t('back')) + '">' + ic('left') + '</button><button type="button" class="flxfm-ib" data-act="fwd" aria-label="' + esc(t('forward')) + '">' + ic('right') + '</button></div>' +
				'<nav class="flxfm-crumbs" aria-label="' + esc(t('folder')) + '"></nav>' +
				'<div class="flxfm-tools">' +
					'<button type="button" class="flxfm-sortbtn" data-menu="sort">' + ic('sort') + '<span></span>' + ic('down', 'flxfm-i-sm') + '</button>' +
					'<div class="flxfm-searchbox"><button type="button" class="flxfm-ib" data-act="search" aria-label="' + esc(t('search')) + '">' + ic('search') + '</button>' +
						'<input type="search" class="flxfm-q" placeholder="' + esc(t('search_here')) + '" aria-label="' + esc(t('search')) + '">' +
						'<label class="flxfm-chip" title="' + esc(t('search_content_hint')) + '"><input type="checkbox" class="flxfm-qc"> ' + esc(t('in_content')) + '</label></div>' +
					'<button type="button" class="flxfm-ib" data-act="view" aria-label="' + esc(t('toggle_view')) + '"></button>' +
					'<button type="button" class="flxfm-ib" data-act="refresh" aria-label="' + esc(t('refresh')) + '">' + ic('refresh') + '</button>' +
				'</div>' +
			'</div>' +
			'<div class="flxfm-body" tabindex="0"></div>' +
			'<footer class="flxfm-foot"><div class="flxfm-count"></div><div class="flxfm-disk"></div></footer>' +
			'<div class="flxfm-drop"><div>' + ic('upload') + '<b>' + esc(t('drop_here')) + '</b><span class="flxfm-drop-to"></span></div></div>' +
		'</section>' +
		'<section class="flxfm-card flxfm-editor" hidden>' +
			'<div class="flxfm-ebar">' +
				'<button type="button" class="flxfm-ib" data-act="tree" aria-label="' + esc(t('toggle_tree')) + '">' + ic('sidebar') + '</button>' +
				'<div class="flxfm-tabs" role="tablist"></div>' +
				'<div class="flxfm-etools"></div>' +
			'</div>' +
			'<div class="flxfm-ewrap">' +
				'<aside class="flxfm-tree"><div class="flxfm-tree-head"><b></b><button type="button" class="flxfm-ib" data-act="tree-refresh" aria-label="' + esc(t('refresh')) + '">' + ic('refresh') + '</button></div><div class="flxfm-tree-body"></div></aside>' +
				'<div class="flxfm-emain"><div class="flxfm-empty-editor">' + ic('code') + '<p>' + esc(t('editor_empty')) + '</p></div></div>' +
			'</div>' +
		'</section>' +
		'<div class="flxfm-selbar" hidden></div>' +
		'<div class="flxfm-uploads" hidden><div class="flxfm-up-head"><b></b><button type="button" class="flxfm-ib" data-act="up-close" aria-label="' + esc(t('close')) + '">' + ic('x') + '</button></div><ul></ul></div>' +
		'<input type="file" class="flxfm-in-files" multiple hidden><input type="file" class="flxfm-in-dir" webkitdirectory multiple hidden>';

	var body = $('.flxfm-body');

	/* ---------------------------------------------------------------- iz yolu */
	function renderTrail() {
		var parts = [];
		if (S.mode === 'editor' && E.active) {
			parts = E.active.path.split('/');
		}
		// Gezginde yol kartın içindeki gezinme çubuğunda; üstte tekrar etmesin.
		var html = '<button type="button" class="flxfm-tr" data-go="" aria-label="' + esc(t('root')) + '">' + ic('home') + '</button><span class="flxfm-sep">' + ic('right') + '</span>' +
			'<button type="button" class="flxfm-tr flxfm-tr-site" data-go="">' + ic('globe') + '<span>' + esc(C.host) + '</span></button>';
		if (S.mode === 'trash') html += '<span class="flxfm-sep">' + ic('right') + '</span><span class="flxfm-tr is-cur">' + esc(t('trash')) + '</span>';
		else if (S.mode === 'log') html += '<span class="flxfm-sep">' + ic('right') + '</span><span class="flxfm-tr is-cur">' + esc(t('activity')) + '</span>';
		else {
			var acc = '';
			parts.forEach(function (p, i) {
				acc = join(acc, p);
				var last = i === parts.length - 1;
				html += '<span class="flxfm-sep">' + ic('right') + '</span><button type="button" class="flxfm-tr' + (last ? ' is-cur' : '') + '" data-go="' + esc(S.mode === 'editor' && last ? dir(acc) : acc) + '">' + esc(p) + '</button>';
			});
		}
		$('.flxfm-trail').innerHTML = html;
	}
	$('.flxfm-trail').addEventListener('click', function (e) {
		var b = e.target.closest('[data-go]');
		if (!b) return;
		setMode('browse');
		go(b.getAttribute('data-go'));
	});

	function renderCrumbs() {
		var c = $('.flxfm-crumbs');
		if (S.mode === 'trash' || S.mode === 'log') {
			c.innerHTML = '<span class="flxfm-crumb is-cur">' + ic(S.mode === 'trash' ? 'trash' : 'activity') + esc(t(S.mode === 'trash' ? 'trash' : 'activity')) + '</span>';
			return;
		}
		var parts = S.cwd ? S.cwd.split('/') : [];
		var html = '<button type="button" class="flxfm-crumb' + (parts.length ? '' : ' is-cur') + '" data-go="" data-drop="">' + esc(C.rootName) + '</button>';
		var acc = '';
		// Uzun yolda baştaki parçalar "…" olur.
		var startAt = parts.length > 4 ? parts.length - 3 : 0;
		if (startAt) html += '<span class="flxfm-csep">' + ic('right') + '</span><button type="button" class="flxfm-crumb" data-go="' + esc(parts.slice(0, startAt).join('/')) + '">…</button>';
		parts.forEach(function (p, i) {
			acc = join(acc, p);
			if (i < startAt) return;
			html += '<span class="flxfm-csep">' + ic('right') + '</span><button type="button" class="flxfm-crumb' + (i === parts.length - 1 ? ' is-cur' : '') + '" data-go="' + esc(acc) + '" data-drop="' + esc(acc) + '">' + esc(p) + '</button>';
		});
		if (S.search) html += '<span class="flxfm-csep">' + ic('right') + '</span><span class="flxfm-crumb is-cur">' + esc(t('search_results', S.search.q)) + '</span>';
		c.innerHTML = html;
	}
	$('.flxfm-crumbs').addEventListener('click', function (e) {
		var b = e.target.closest('[data-go]');
		if (b) { if (S.mode !== 'browse') setMode('browse'); go(b.getAttribute('data-go')); }
	});

	/* ------------------------------------------------------------- klasör aç */
	function go(path, opts) {
		opts = opts || {};
		if (!opts.hist && path !== S.cwd) { S.back.push(S.cwd); S.fwd = []; }
		S.search = null;
		$('.flxfm-q').value = '';
		return load(path, opts);
	}
	function load(path, opts) {
		opts = opts || {};
		S.loading = true;
		if (!opts.keepSel) S.sel.clear();
		if (!opts.quiet) body.classList.add('is-loading');
		return api('list', { path: path, disk: !S.disk }).then(function (r) {
			S.cwd = r.path;
			S.items = r.items;
			S.dirInfo = r.dir;
			if (r.disk) S.disk = r.disk;
			if (opts.select) { S.sel = new Set(opts.select.filter(function (p) { return S.items.some(function (i) { return i.path === p; }); })); }
			try { history.replaceState(null, '', '#path=' + encodeURIComponent(S.cwd)); } catch (e) { /* yoksay */ }
			render();
		}).catch(function (e) {
			fail(e);
			if (path && opts.fallback !== false) return load(dir(path), { fallback: false, hist: true });
		}).then(function () { S.loading = false; body.classList.remove('is-loading'); });
	}
	function refresh() { if (S.mode === 'trash') return showTrash(); if (S.mode === 'log') return showLog(); if (S.search) return runSearch(); return load(S.cwd, { keepSel: true, quiet: true }); }

	/* ------------------------------------------------------------- sıralama */
	var SORTS = [
		['name', 1, 'sort_name_az'], ['name', -1, 'sort_name_za'],
		['mtime', -1, 'sort_new'], ['mtime', 1, 'sort_old'],
		['size', -1, 'sort_big'], ['size', 1, 'sort_small'], ['ext', 1, 'sort_type']
	];
	function sortLabel() { var f = SORTS.filter(function (s) { return s[0] === S.sort.key && s[1] === S.sort.dir; })[0]; return f ? t(f[2]) : ''; }
	function sorted(list) {
		var k = S.sort.key, d = S.sort.dir;
		return list.slice().sort(function (a, b) {
			if (a.type !== b.type) return a.type === 'dir' ? -1 : 1;
			var x = a[k], y = b[k], r = 0;
			if (k === 'name' || k === 'ext') r = String(x || '').localeCompare(String(y || ''), undefined, { numeric: true, sensitivity: 'base' });
			else r = (x || 0) - (y || 0);
			if (!r && k !== 'name') r = a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' });
			return r * d;
		});
	}

	/* ------------------------------------------------------------- liste çiz */
	function visible() { return S.search ? S.search.results : sorted(S.items); }
	function render() {
		renderTrail();
		renderCrumbs();
		$('.flxfm-sortbtn span').textContent = sortLabel();
		$('[data-act="view"]').innerHTML = ic(S.view === 'grid' ? 'list' : 'grid');
		$('[data-act="back"]').disabled = !S.back.length;
		$('[data-act="fwd"]').disabled = !S.fwd.length;
		var list = visible();
		var grid = S.view === 'grid';
		var html = '';
		if (!list.length) {
			html = '<div class="flxfm-empty">' + ic(S.search ? 'search' : 'folder') + '<p>' + esc(S.search ? t('no_results') : t('empty_folder')) + '</p>' +
				(!S.search && C.writable ? '<p class="flxfm-muted">' + esc(t('empty_hint')) + '</p>' : '') + '</div>';
		} else if (grid) {
			html = '<div class="flxfm-grid" role="listbox" aria-multiselectable="true">' + list.map(function (it) {
				return '<div class="flxfm-tile' + cls(it) + '" role="option" tabindex="-1" data-path="' + esc(it.path) + '" draggable="' + (C.writable && !it.locked) + '" aria-selected="' + S.sel.has(it.path) + '">' +
					fileIcon(it, true) + '<span class="flxfm-tname" title="' + esc(it.name) + '">' + esc(it.name) + lockMark(it) + '</span>' +
					'<span class="flxfm-tmeta">' + esc(it.type === 'dir' ? (it.count == null ? '' : t('items_n', it.count)) : size(it.size)) + '</span>' +
					'<button type="button" class="flxfm-rowmenu" data-act="rowmenu" aria-label="' + esc(t('actions')) + '">' + ic('more') + '</button></div>';
			}).join('') + '</div>';
		} else {
			html = '<div class="flxfm-table" role="grid" aria-multiselectable="true">' +
				'<div class="flxfm-thead" role="row">' + th('name', t('col_name')) + (S.search ? '<span class="flxfm-c-where">' + esc(t('col_where')) + '</span>' : '') + th('mtime', t('col_modified')) + th('size', t('col_size')) + '<span class="flxfm-c-perm">' + esc(t('col_perms')) + '</span><span class="flxfm-c-menu"></span></div>' +
				list.map(function (it) {
					var match = it.matches ? '<span class="flxfm-match">' + it.matches.map(function (m) { return '<code>' + m.line + '</code> ' + esc(m.text); }).join('<br>') + '</span>' : '';
					return '<div class="flxfm-row' + cls(it) + '" role="row" tabindex="-1" data-path="' + esc(it.path) + '" draggable="' + (C.writable && !it.locked) + '" aria-selected="' + S.sel.has(it.path) + '">' +
						'<span class="flxfm-c-name">' + fileIcon(it) + '<span class="flxfm-nm"><span class="flxfm-nm-t" title="' + esc(it.name) + '">' + esc(it.name) + '</span>' + lockMark(it) + match + '</span></span>' +
						(S.search ? '<span class="flxfm-c-where" title="' + esc(dir(it.path) || '/') + '">' + esc(dir(it.path) || '/') + '</span>' : '') +
						'<span class="flxfm-c-mod" title="' + esc(it.date) + '">' + esc(it.ago) + '</span>' +
						'<span class="flxfm-c-size" data-ago="' + esc(it.ago) + '">' + esc(it.type === 'dir' ? (it.count == null ? '—' : t('items_n', it.count)) : size(it.size)) + '</span>' +
						'<span class="flxfm-c-perm"><code>' + esc(it.perms) + '</code></span>' +
						'<span class="flxfm-c-menu"><button type="button" class="flxfm-rowmenu" data-act="rowmenu" aria-label="' + esc(t('actions')) + '">' + ic('more') + '</button></span></div>';
				}).join('') + '</div>';
		}
		if (S.search && S.search.partial) html += '<p class="flxfm-note">' + esc(t('search_partial')) + '</p>';
		body.innerHTML = html;
		renderFoot();
	}
	function th(key, label) {
		var on = S.sort.key === key;
		return '<button type="button" class="flxfm-th flxfm-c-' + (key === 'name' ? 'name' : key === 'mtime' ? 'mod' : 'size') + (on ? ' is-on' : '') + '" data-sort="' + key + '">' + esc(label) + (on ? ic(S.sort.dir > 0 ? 'up' : 'down', 'flxfm-i-sm') : '') + '</button>';
	}
	function cls(it) {
		return (S.sel.has(it.path) ? ' is-sel' : '') + (S.clip && S.clip.op === 'cut' && S.clip.paths.indexOf(it.path) >= 0 ? ' is-cut' : '') + (it.type === 'dir' ? ' is-dir' : '');
	}
	function lockMark(it) {
		var m = '';
		if (it.locked) m += '<span class="flxfm-flag" title="' + esc(t('locked_hint')) + '">' + ic('lock') + '</span>';
		else if (it.sensitive) m += '<span class="flxfm-flag flxfm-flag--warn" title="' + esc(t('sensitive_hint')) + '">' + ic('shield') + '</span>';
		return m;
	}
	function renderFoot() {
		var n = visible().length;
		$('.flxfm-count').innerHTML = '<span>' + esc(t('items_n', n)) + '</span>' + (S.sel.size ? '<span>' + esc(t('selected_n', S.sel.size)) + '</span>' : '') + (S.clip ? '<span class="flxfm-clipinfo">' + ic(S.clip.op === 'cut' ? 'cut' : 'copy') + esc(t('clip_n', S.clip.paths.length)) + '</span>' : '');
		renderDisk();
		renderSelbar();
	}
	/* Depolama: hesabın kullandığı alan / Ayarlar'daki kota. Ölçüm sürerken birkaç saniyede bir ilerletilir. */
	function renderDisk() {
		var d = S.disk, el = $('.flxfm-disk');
		if (!d) { el.innerHTML = ''; return; }
		var txt = d.used == null ? t('storage_measuring') : (d.total ? size(d.used) + ' / ' + size(d.total) : t('storage_used', size(d.used)));
		var pct = d.used != null && d.total ? Math.min(100, Math.max(1, Math.round(d.used / d.total * 100))) : 0;
		var tip = d.used == null ? '' : t('storage_tip', d.files == null ? '' : d.files.toLocaleString(), d.ago || '');
		el.innerHTML = '<span>' + esc(t('storage')) + '</span>' +
			(pct ? '<span class="flxfm-meter' + (pct >= 90 ? ' is-full' : '') + '"><i style="width:' + pct + '%"></i></span>' : '') +
			'<b title="' + esc(tip) + '">' + esc(txt) + '</b>' +
			(d.scanning ? '<span class="flxfm-disk-spin" title="' + esc(t('storage_measuring')) + '">' + ic('refresh', 'flxfm-i-sm') + '</span>' : '');
		if (d.scanning && !S.diskTimer) {
			S.diskTimer = setTimeout(function () {
				S.diskTimer = null;
				if (document.hidden) { S.disk.scanning = true; renderDisk(); return; }
				api('usage').then(function (r) { S.disk = r.disk; renderDisk(); }).catch(function () { /* sonra yeniden denenir */ });
			}, 4000);
		}
	}
	function selItems() { var all = visible(); return all.filter(function (i) { return S.sel.has(i.path); }); }
	function renderSelbar() {
		var bar = $('.flxfm-selbar');
		var n = S.sel.size;
		var coarse = window.matchMedia && window.matchMedia('(pointer:coarse)').matches;
		if (S.mode !== 'browse' || !n || (n < 2 && !coarse)) { bar.hidden = true; return; }
		bar.hidden = false;
		var w = C.writable;
		bar.innerHTML = '<b>' + esc(t('selected_n', n)) + '</b>' +
			(w ? '<button type="button" data-a="cut">' + ic('cut') + '<span>' + esc(t('cut')) + '</span></button>' : '') +
			'<button type="button" data-a="copy">' + ic('copy') + '<span>' + esc(t('copy')) + '</span></button>' +
			'<button type="button" data-a="zipdl">' + ic('download') + '<span>' + esc(t('download')) + '</span></button>' +
			(w ? '<button type="button" data-a="trash" class="is-danger">' + ic('trash') + '<span>' + esc(t('delete')) + '</span></button>' : '') +
			'<button type="button" data-a="clear" aria-label="' + esc(t('clear_selection')) + '">' + ic('x') + '</button>';
	}
	$('.flxfm-selbar').addEventListener('click', function (e) {
		var b = e.target.closest('[data-a]');
		if (!b) return;
		var a = b.getAttribute('data-a');
		if (a === 'clear') { S.sel.clear(); render(); return; }
		act(a, selItems());
	});
	function setSel(paths) { S.sel = new Set(paths); $$('[data-path]', body).forEach(function (el) { var on = S.sel.has(el.getAttribute('data-path')); el.classList.toggle('is-sel', on); el.setAttribute('aria-selected', on); }); renderFoot(); }
	function itemOf(path) { return visible().filter(function (i) { return i.path === path; })[0] || (S.search ? null : S.items.filter(function (i) { return i.path === path; })[0]); }

	/* --------------------------------------------------------- tıklama/klavye */
	var touchTimer = null, touchMoved = false, longPressed = false;
	body.addEventListener('click', function (e) {
		var th2 = e.target.closest('[data-sort]');
		if (th2) {
			var k = th2.getAttribute('data-sort');
			S.sort = { key: k, dir: S.sort.key === k ? -S.sort.dir : (k === 'name' ? 1 : -1) };
			lsSet('sort', S.sort); render(); return;
		}
		var row = e.target.closest('[data-path]');
		if (!row) { if (e.target === body || e.target.closest('.flxfm-empty') || e.target.classList.contains('flxfm-grid') || e.target.classList.contains('flxfm-table')) setSel([]); return; }
		var p = row.getAttribute('data-path');
		if (e.target.closest('[data-act="rowmenu"]')) {
			if (!S.sel.has(p)) setSel([p]);
			var r = e.target.closest('button').getBoundingClientRect();
			menu(ctxItems(selItems()), r.right, r.bottom, true);
			return;
		}
		if (longPressed) { longPressed = false; return; }
		var coarse = window.matchMedia && window.matchMedia('(pointer:coarse)').matches;
		if (coarse && !S.sel.size) { open(itemOf(p)); return; }
		if (coarse && S.sel.size) { var s = new Set(S.sel); if (s.has(p)) s.delete(p); else s.add(p); setSel(Array.from(s)); return; }
		if (e.metaKey || e.ctrlKey) { var s2 = new Set(S.sel); if (s2.has(p)) s2.delete(p); else s2.add(p); S.anchor = p; setSel(Array.from(s2)); return; }
		if (e.shiftKey && S.anchor) {
			var ps = visible().map(function (i) { return i.path; });
			var a = ps.indexOf(S.anchor), b = ps.indexOf(p);
			if (a >= 0 && b >= 0) { setSel(ps.slice(Math.min(a, b), Math.max(a, b) + 1)); return; }
		}
		S.anchor = p;
		setSel([p]);
	});
	body.addEventListener('dblclick', function (e) {
		var row = e.target.closest('[data-path]');
		if (row && !e.target.closest('button')) open(itemOf(row.getAttribute('data-path')));
	});
	body.addEventListener('contextmenu', function (e) {
		var row = e.target.closest('[data-path]');
		e.preventDefault();
		if (row) {
			var p = row.getAttribute('data-path');
			if (!S.sel.has(p)) { S.anchor = p; setSel([p]); }
			menu(ctxItems(selItems()), e.clientX, e.clientY);
		} else {
			if (S.mode !== 'browse' || S.search) return;
			setSel([]);
			menu(bgItems(), e.clientX, e.clientY);
		}
	});
	body.addEventListener('touchstart', function (e) {
		var row = e.target.closest('[data-path]');
		if (!row) return;
		touchMoved = false;
		var tt = e.touches[0];
		touchTimer = setTimeout(function () {
			if (touchMoved) return;
			longPressed = true;
			var p = row.getAttribute('data-path');
			if (!S.sel.has(p)) setSel([p]);
			if (navigator.vibrate) navigator.vibrate(10);
			menu(ctxItems(selItems()), tt.clientX, tt.clientY, false, true);
		}, 500);
	}, { passive: true });
	body.addEventListener('touchmove', function () { touchMoved = true; clearTimeout(touchTimer); }, { passive: true });
	body.addEventListener('touchend', function () { clearTimeout(touchTimer); });

	document.addEventListener('keydown', function (e) {
		if (S.mode === 'editor') return editorKeys(e);
		if (S.mode !== 'browse') return;
		var tag = (e.target.tagName || '').toLowerCase();
		if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable || document.querySelector('dialog[open]') || $('.flxfm-menu', document.body)) return;
		var mod = e.metaKey || e.ctrlKey;
		var items = selItems();
		var k = e.key;
		if (mod && k.toLowerCase() === 'a') { e.preventDefault(); setSel(visible().map(function (i) { return i.path; })); }
		else if (mod && k.toLowerCase() === 'c' && items.length) { e.preventDefault(); act('copy', items); }
		else if (mod && k.toLowerCase() === 'x' && items.length && C.writable) { e.preventDefault(); act('cut', items); }
		else if (mod && k.toLowerCase() === 'v' && S.clip && C.writable) { e.preventDefault(); paste(S.cwd); }
		else if ((k === 'Delete' || (k === 'Backspace' && mod)) && items.length && C.writable) { e.preventDefault(); act('trash', items); }
		else if (k === 'F2' && items.length === 1 && C.writable) { e.preventDefault(); act('rename', items); }
		else if (k === 'Enter' && items.length === 1) { e.preventDefault(); open(items[0]); }
		else if (k === 'Backspace' && !mod) { e.preventDefault(); if (S.search) clearSearch(); else if (S.cwd) go(dir(S.cwd)); }
		else if (k === 'Escape') { if (S.search) clearSearch(); else setSel([]); }
		else if (k === '/') { e.preventDefault(); openSearch(); }
		else if (k === 'ArrowDown' || k === 'ArrowUp' || ((k === 'ArrowLeft' || k === 'ArrowRight') && S.view === 'grid' && !e.altKey)) {
			e.preventDefault();
			var ps = visible().map(function (i) { return i.path; });
			if (!ps.length) return;
			var cur = S.anchor && ps.indexOf(S.anchor) >= 0 ? ps.indexOf(S.anchor) : -1;
			var step = 1;
			if (S.view === 'grid' && (k === 'ArrowDown' || k === 'ArrowUp')) { var g = $('.flxfm-grid'); step = g ? Math.max(1, Math.round(g.clientWidth / ($('.flxfm-tile') ? $('.flxfm-tile').offsetWidth + 12 : 150))) : 1; }
			var nx = cur + ((k === 'ArrowDown' || k === 'ArrowRight') ? step : -step);
			nx = Math.max(0, Math.min(ps.length - 1, cur < 0 ? 0 : nx));
			S.anchor = ps[nx]; setSel([ps[nx]]);
			var el = body.querySelector('[data-path="' + CSS.escape(ps[nx]) + '"]'); if (el) el.scrollIntoView({ block: 'nearest' });
		}
		else if (e.altKey && k === 'ArrowLeft') { e.preventDefault(); goBack(); }
		else if (e.altKey && k === 'ArrowRight') { e.preventDefault(); goFwd(); }
	});
	function goBack() { if (!S.back.length) return; S.fwd.push(S.cwd); go(S.back.pop(), { hist: true }); }
	function goFwd() { if (!S.fwd.length) return; S.back.push(S.cwd); go(S.fwd.pop(), { hist: true }); }

	/* Araç çubuğu düğmeleri. */
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b || b.closest('.flxfm-body')) return;
		var a = b.getAttribute('data-act');
		if (a === 'back') goBack();
		else if (a === 'fwd') goFwd();
		else if (a === 'refresh') { S.disk = null; refresh(); }
		else if (a === 'view') { S.view = S.view === 'grid' ? 'list' : 'grid'; lsSet('view', S.view); render(); }
		else if (a === 'search') openSearch();
		else if (a === 'tree') root.classList.toggle('flxfm-tree-off');
		else if (a === 'tree-refresh') { var tb = $('[data-act="tree-refresh"]'); tb.classList.add('is-spin'); treeLoad('', $('.flxfm-tree-body')).then(function () { tb.classList.remove('is-spin'); }); }
		else if (a === 'up-close') { $('.flxfm-uploads').hidden = true; }
	});
	$$('.flxfm-seg [data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); }); });
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-menu]');
		if (!b) return;
		var r = b.getBoundingClientRect();
		var m = b.getAttribute('data-menu');
		if (m === 'upload') menu([
			{ icon: 'upload', label: t('upload_files'), run: function () { $('.flxfm-in-files').click(); } },
			{ icon: 'folder', label: t('upload_folder'), run: function () { $('.flxfm-in-dir').click(); } }
		], r.right, r.bottom + 6, true);
		else if (m === 'new') menu(newItems(S.mode === 'editor' && E.active ? dir(E.active.path) : S.cwd), r.right, r.bottom + 6, true);
		else if (m === 'sort') menu(SORTS.map(function (s) {
			return { label: t(s[2]), check: S.sort.key === s[0] && S.sort.dir === s[1], run: function () { S.sort = { key: s[0], dir: s[1] }; lsSet('sort', S.sort); render(); } };
		}), r.right, r.bottom + 6, true);
		else if (m === 'more') menu([
			{ icon: 'trash', label: t('trash'), run: function () { setMode('trash'); } },
			{ icon: 'activity', label: t('activity'), run: function () { setMode('log'); } },
			'-',
			{ icon: 'settings', label: t('settings'), run: openSettings }
		], r.right, r.bottom + 6, true);
	});

	/* ----------------------------------------------------------------- arama */
	function openSearch() { var q = $('.flxfm-q'); $('.flxfm-searchbox').classList.add('is-open'); q.focus(); q.select(); }
	function clearSearch() { S.search = null; $('.flxfm-q').value = ''; $('.flxfm-searchbox').classList.remove('is-open'); render(); }
	function runSearch() {
		var q = $('.flxfm-q').value.trim();
		if (q.length < 2) { if (!q) clearSearch(); return Promise.resolve(); }
		body.classList.add('is-loading');
		return api('search', { path: S.cwd, q: q, content: $('.flxfm-qc').checked }).then(function (r) {
			S.search = { q: q, results: r.results, partial: r.partial };
			S.sel.clear(); render();
		}).catch(fail).then(function () { body.classList.remove('is-loading'); });
	}
	$('.flxfm-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
		if (e.key === 'Escape') { e.preventDefault(); clearSearch(); body.focus(); }
	});
	$('.flxfm-q').addEventListener('search', function () { if (!this.value) clearSearch(); });
	$('.flxfm-qc').addEventListener('change', function () { if ($('.flxfm-q').value.trim().length >= 2) runSearch(); });
	$('.flxfm-q').addEventListener('blur', function () { if (!this.value) $('.flxfm-searchbox').classList.remove('is-open'); });

	/* ------------------------------------------------------------ bağlam menü */
	var menuEl = null;
	function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; document.removeEventListener('mousedown', outside, true); } }
	function outside(e) { if (menuEl && !menuEl.contains(e.target)) closeMenu(); }
	function menu(items, x, y, alignRight, sheet) {
		closeMenu();
		var mob = window.innerWidth <= 600;
		var el = document.createElement('div');
		el.className = 'flxfm-menu' + (mob ? ' is-sheet' : '') + (document.documentElement.classList.contains('flxa-dark') ? ' is-dark' : '');
		el.setAttribute('role', 'menu');
		el.innerHTML = build(items);
		document.body.appendChild(el);
		menuEl = el;
		if (!mob) {
			var w = el.offsetWidth, hh = el.offsetHeight;
			var left = alignRight ? x - w : x, top = y;
			if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
			if (left < 8) left = 8;
			if (top + hh > window.innerHeight - 8) top = Math.max(8, y - hh);
			el.style.left = left + 'px'; el.style.top = top + 'px';
		}
		el.addEventListener('click', function (e) {
			var b = e.target.closest('[data-i]');
			if (!b || b.disabled) return;
			var path = b.getAttribute('data-i').split('.').map(Number);
			var it = items[path[0]];
			if (path.length > 1) it = it.sub[path[1]];
			if (it.sub && path.length === 1) { b.parentNode.classList.toggle('is-open'); return; }
			closeMenu();
			if (it.run) it.run();
		});
		el.addEventListener('mouseover', function (e) {
			if (mob) return;
			var li = e.target.closest('.flxfm-mi');
			$$('.flxfm-mi.is-open', el).forEach(function (o) { if (o !== li && !o.contains(li)) o.classList.remove('is-open'); });
			if (li && li.querySelector('.flxfm-sub')) {
				li.classList.add('is-open');
				var sub = li.querySelector('.flxfm-sub');
				var r = li.getBoundingClientRect();
				sub.classList.toggle('is-left', r.right + sub.offsetWidth > window.innerWidth - 8);
			}
		});
		setTimeout(function () { document.addEventListener('mousedown', outside, true); }, 0);
		var first = el.querySelector('button:not([disabled])'); if (first && !sheet) first.focus({ preventScroll: true });
		el.addEventListener('keydown', function (e) {
			var bs = $$('button:not([disabled])', el).filter(function (b) { return b.offsetParent; });
			var i = bs.indexOf(document.activeElement);
			if (e.key === 'Escape') { closeMenu(); }
			else if (e.key === 'ArrowDown') { e.preventDefault(); (bs[i + 1] || bs[0]).focus(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); (bs[i - 1] || bs[bs.length - 1]).focus(); }
		});
		function build(list, pre) {
			return '<div class="flxfm-mlist">' + list.map(function (it, i) {
				if (!it) return '';
				if (it === '-') return '<hr>';
				var id = (pre != null ? pre + '.' : '') + i;
				return '<div class="flxfm-mi' + (it.sub ? ' has-sub' : '') + '"><button type="button" role="menuitem" data-i="' + id + '"' + (it.disabled ? ' disabled' : '') + (it.danger ? ' class="is-danger"' : '') + '>' +
					(it.check !== undefined ? '<span class="flxfm-mcheck">' + (it.check ? ic('check') : '') + '</span>' : (it.icon ? ic(it.icon) : '<span class="flxfm-mcheck"></span>')) +
					'<span class="flxfm-ml">' + esc(it.label) + '</span>' + (it.key ? '<kbd>' + esc(it.key) + '</kbd>' : '') + (it.sub ? ic('right', 'flxfm-i-sm') : '') + '</button>' +
					(it.sub ? '<div class="flxfm-sub">' + build(it.sub, i) + '</div>' : '') + '</div>';
			}).join('') + '</div>';
		}
	}
	window.addEventListener('resize', closeMenu);
	root.addEventListener('scroll', closeMenu, true);
	var MOD = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl+';

	function newItems(where) {
		return [
			{ icon: 'fileplus', label: t('new_file'), run: function () { createNew(where, false); } },
			{ icon: 'folderplus', label: t('new_folder'), run: function () { createNew(where, true); } }
		];
	}
	function bgItems() {
		var w = C.writable;
		return [
			w ? { icon: 'plus', label: t('new'), sub: newItems(S.cwd) } : null,
			w ? { icon: 'upload', label: t('upload_files'), run: function () { $('.flxfm-in-files').click(); } } : null,
			w ? { icon: 'paste', label: t('paste'), key: MOD + 'V', disabled: !S.clip, run: function () { paste(S.cwd); } } : null,
			w ? '-' : null,
			{ icon: 'download', label: t('download_zip'), run: function () { download([S.cwd]); } },
			{ icon: 'link', label: t('copy_path'), sub: pathItems(S.dirInfo || { path: S.cwd, type: 'dir' }) },
			{ icon: 'info', label: t('folder_info'), run: function () { infoDialog(S.dirInfo); } },
			'-',
			{ icon: 'refresh', label: t('refresh'), run: refresh }
		];
	}
	function pathItems(it) {
		var abs = C.abs + (it.path ? '/' + it.path : '');
		var list = [
			{ label: t('path_relative'), run: function () { copyText(it.path || '/'); } },
			{ label: t('path_server'), run: function () { copyText(abs); } }
		];
		if (it.type !== 'dir' || it.path) list.push({ label: t('path_url'), run: function () { copyText(publicUrl(it.path)); } });
		return list;
	}
	function ctxItems(items) {
		var one = items.length === 1 ? items[0] : null;
		var w = C.writable;
		var anyLocked = items.some(function (i) { return i.locked; });
		var list = [];
		if (one) {
			list.push({ icon: one.type === 'dir' ? 'folder' : (canEdit(one) ? 'code' : 'eye'), label: one.type === 'dir' ? t('open') : (canEdit(one) ? t('edit') : t('preview')), run: function () { open(one); } });
			if (one.type === 'dir' && w) list.push({ icon: 'plus', label: t('new'), sub: newItems(one.path) });
			list.push('-');
		}
		list.push({ icon: 'download', label: one && one.type === 'file' ? t('download') : t('download_zip'), run: function () { download(items.map(function (i) { return i.path; })); } });
		if (w) {
			list.push({ icon: 'archive', label: t('archive'), run: function () { archive(items); } });
			if (one && /\.zip$/i.test(one.name)) list.push({ icon: 'unarchive', label: t('extract'), run: function () { extract(one); } });
		}
		list.push('-');
		if (w) list.push({ icon: 'cut', label: t('cut'), key: MOD + 'X', disabled: anyLocked, run: function () { act('cut', items); } });
		list.push({ icon: 'copy', label: t('copy'), key: MOD + 'C', run: function () { act('copy', items); } });
		if (w) list.push({ icon: 'paste', label: t('paste'), key: MOD + 'V', disabled: !S.clip, run: function () { paste(one && one.type === 'dir' ? one.path : (S.search ? dir(items[0].path) : S.cwd)); } });
		if (w) {
			list.push('-');
			list.push({ icon: 'dup', label: t('duplicate'), run: function () { act('duplicate', items); } });
			list.push({ icon: 'folder', label: t('copy_or_move'), disabled: false, sub: [
				{ label: t('move_to'), disabled: anyLocked, run: function () { pickFolder(t('move_to'), function (d) { transfer('move', items.map(function (i) { return i.path; }), d); }); } },
				{ label: t('copy_to'), run: function () { pickFolder(t('copy_to'), function (d) { transfer('copy', items.map(function (i) { return i.path; }), d); }); } }
			] });
		}
		if (one) list.push({ icon: 'link', label: t('copy_path'), sub: pathItems(one) });
		if (S.search && one) list.push({ icon: 'folder', label: t('show_in_folder'), run: function () { go(dir(one.path)).then(function () { setSel([one.path]); }); } });
		list.push('-');
		if (one) {
			list.push({ icon: 'info', label: one.type === 'dir' ? t('folder_info') : t('file_info'), run: function () { infoDialog(one); } });
			if (w) list.push({ icon: 'lock', label: t('permissions'), run: function () { permsDialog(one); } });
			if (one.type === 'dir' && w && isUpload(one.path)) list.push({ icon: 'share', label: one.shared ? t('unshare') : t('share_on_site'), run: function () { share(one); } });
			if (one.type === 'file') list.push({ icon: 'history', label: t('versions'), run: function () { versionsDialog(one); } });
			list.push({ icon: 'activity', label: t('activity'), run: function () { setMode('log', { path: one.path }); } });
		}
		if (w) {
			list.push('-');
			if (one) list.push({ icon: 'pencil', label: t('rename'), key: 'F2', disabled: one.locked, run: function () { act('rename', items); } });
			list.push({ icon: 'trash', label: t('delete'), key: 'Del', danger: true, disabled: anyLocked, run: function () { act('trash', items); } });
		}
		while (list.length && list[list.length - 1] === '-') list.pop();
		return list.filter(function (x, i, a) { return !(x === '-' && a[i - 1] === '-'); });
	}
	function isUpload(p) { return C.uploads && p.indexOf(C.uploads.rel + '/') === 0; }
	function canEdit(it) { return it.type === 'file' && (it.kind === 'code' || it.kind === 'text' || it.ext === 'svg' || it.kind === 'file' && it.size < 1048576); }

	/* ---------------------------------------------------------------- işlemler */
	function open(it) {
		if (!it) return;
		if (it.type === 'dir') { if (S.search) S.search = null; go(it.path); return; }
		if (it.kind === 'image' || it.kind === 'video' || it.kind === 'audio' || it.kind === 'pdf') { preview(it); return; }
		if (canEdit(it)) { openEditor(it.path); return; }
		if (it.kind === 'archive' && /\.zip$/i.test(it.name) && C.writable) { extract(it); return; }
		download([it.path]);
	}
	function act(a, items) {
		var paths = items.map(function (i) { return i.path; });
		if (!paths.length) return;
		if (a === 'cut' || a === 'copy') {
			S.clip = { op: a, paths: paths };
			toast(t(a === 'cut' ? 'cut_n' : 'copied_n', paths.length), 'info');
			render(); return;
		}
		if (a === 'zipdl') { download(paths); return; }
		if (a === 'duplicate') {
			api('duplicate', { paths: paths }).then(function (r) { report(r); load(S.cwd, { select: r.done.map(function (d) { return d.path; }) }); }).catch(fail);
			return;
		}
		if (a === 'rename') {
			var it = items[0];
			D.prompt({ title: t('rename'), value: it.name, ok: t('rename') }).then(function (v) {
				if (v == null || v === it.name || !v.trim()) return;
				return api('rename', { path: it.path, name: v.trim() }).then(function (r) {
					toast(t('renamed'));
					retarget(it.path, r.path);
					if (S.search) runSearch(); else load(S.cwd, { select: [r.path] });
				});
			}).catch(fail);
			return;
		}
		if (a === 'trash') {
			var msg = paths.length === 1 ? t('trash_one', items[0].name) : t('trash_many', paths.length);
			D.confirm({ title: t('delete'), message: msg + ' ' + t('trash_hint'), ok: t('delete'), danger: true }).then(function (ok) {
				if (!ok) return;
				return api('trash', { paths: paths }).then(function (r) {
					report(r);
					if (r.done.length) toastUndo(r.done);
					closeTabs(paths);
					refresh();
				});
			}).catch(fail);
		}
	}
	function toastUndo(done) {
		var ids = done.map(function (d) { return d.trash_id; });
		toast(t('moved_to_trash', done.length), 'ok');
		S.lastTrash = ids;
	}
	function report(r) {
		if (r && r.errors && r.errors.length) {
			D.alert({ title: t('some_failed'), message: r.errors.map(function (e) { return base(e.path) + ': ' + e.message; }).join('\n') });
		}
	}
	function paste(dest) {
		if (!S.clip) return;
		var c = S.clip;
		transfer(c.op === 'cut' ? 'move' : 'copy', c.paths, dest).then(function (ok) { if (ok && c.op === 'cut') { S.clip = null; renderFoot(); } });
	}
	function transfer(op, paths, dest, overwrite) {
		return api(op, { paths: paths, dest: dest, overwrite: !!overwrite }).then(function (r) {
			var conflicts = (r.errors || []).filter(function (e) { return /exists|var/i.test(e.message) || false; });
			if (op === 'move') r.done.forEach(function (d, i) { retarget(paths[i], d.path); });
			if (r.errors && r.errors.length && op === 'move' && !overwrite && conflicts.length === r.errors.length) {
				return D.confirm({ title: t('conflict_title'), message: t('conflict_msg', conflicts.length), ok: t('overwrite') }).then(function (ok) {
					if (ok) return transfer(op, conflicts.map(function (e) { return e.path; }), dest, true);
					return load(S.cwd);
				});
			}
			report(r);
			if (r.done.length) toast(t(op === 'move' ? 'moved_n' : 'copied_done_n', r.done.length));
			load(S.cwd, { select: dest === S.cwd ? r.done.map(function (d) { return d.path; }) : null });
			treeRefreshSoft();
			return !r.errors.length;
		}).catch(function (e) { fail(e); return false; });
	}
	function createNew(where, isDir) {
		D.prompt({ title: isDir ? t('new_folder') : t('new_file'), placeholder: isDir ? t('folder_name') : 'index.html', ok: t('create') }).then(function (v) {
			if (!v || !v.trim()) return;
			var p = join(where, v.trim());
			return (isDir ? api('mkdir', { path: p }) : api('write', { path: p, content: '', create_only: true })).then(function () {
				toast(t('created'));
				if (S.mode === 'editor') { treeRefreshSoft(); if (!isDir) openEditor(p); return; }
				if (where === S.cwd) load(S.cwd, { select: [p] }).then(function () { if (!isDir) openEditor(p); });
				else go(where).then(function () { setSel([p]); });
			});
		}).catch(fail);
	}
	function archive(items) {
		var name = items.length === 1 ? items[0].name + '.zip' : (S.cwd ? base(S.cwd) : 'archive') + '.zip';
		D.prompt({ title: t('archive'), message: t('archive_hint'), value: name, ok: t('create') }).then(function (v) {
			if (!v) return;
			toast(t('working'), 'info');
			var where = S.search ? dir(items[0].path) : S.cwd;
			return api('zip', { paths: items.map(function (i) { return i.path; }), name: v.trim(), dir: where }).then(function (r) {
				toast(t('archived'));
				if (where === S.cwd) load(S.cwd, { select: [r.item.path] });
			});
		}).catch(fail);
	}
	function extract(it) {
		D.confirm({ title: t('extract'), message: t('extract_msg', it.name), ok: t('extract') }).then(function (ok) {
			if (!ok) return;
			toast(t('working'), 'info');
			return api('unzip', { path: it.path }).then(function (r) {
				toast(t('extracted', r.files));
				load(dir(it.path) === S.cwd ? S.cwd : dir(it.path), { select: [r.path] });
			});
		}).catch(fail);
	}
	function share(it) {
		var on = !it.shared;
		api('share', { path: it.path, on: on }).then(function (r) {
			if (on) {
				D.alert({ title: t('shared_title'), message: t('shared_msg') + '\n\n' + r.shortcode });
				copyText(r.shortcode);
			} else toast(t('unshared'));
			refresh();
		}).catch(fail);
	}
	function retarget(from, to) {
		E.tabs.forEach(function (tb) {
			if (tb.path === from || tb.path.indexOf(from + '/') === 0) { tb.path = to + tb.path.slice(from.length); tb.name = base(tb.path); }
		});
		renderTabs();
	}

	/* ------------------------------------------------------------ küçük modal */
	function modal(opts) {
		var d = document.createElement('dialog');
		d.className = 'flxfm-modal' + (opts.wide ? ' is-wide' : '') + (opts.full ? ' is-full' : '') + (document.documentElement.classList.contains('flxa-dark') ? ' is-dark' : '');
		d.innerHTML = '<div class="flxfm-mhead"><h2>' + esc(opts.title) + '</h2><button type="button" class="flxfm-ib" data-close aria-label="' + esc(t('close')) + '">' + ic('x') + '</button></div><div class="flxfm-mbody"></div>' + (opts.foot ? '<div class="flxfm-mfoot"></div>' : '');
		document.body.appendChild(d);
		var mb = d.querySelector('.flxfm-mbody');
		if (typeof opts.body === 'string') mb.innerHTML = opts.body; else if (opts.body) mb.appendChild(opts.body);
		if (opts.foot) d.querySelector('.flxfm-mfoot').innerHTML = opts.foot;
		d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) close(); });
		d.addEventListener('close', function () { d.remove(); if (opts.onClose) opts.onClose(); });
		function close() { if (d.open) d.close(); }
		d.showModal();
		return { el: d, body: mb, close: close };
	}

	function infoDialog(it) {
		if (!it) return;
		var rows = [
			[t('col_name'), esc(it.name)],
			[t('location'), '<code>' + esc(it.path || '/') + '</code>'],
			[t('type'), esc(it.type === 'dir' ? t('folder') : (it.ext ? it.ext.toUpperCase() + ' · ' : '') + t('kind_' + it.kind))],
			[t('col_size'), it.type === 'dir' ? '<span class="flxfm-dsize"><button type="button" class="flxfm-link" data-calc>' + esc(t('calculate')) + '</button></span>' : esc(size(it.size)) + ' <span class="flxfm-muted">(' + esc(Number(it.size).toLocaleString()) + ' B)</span>'],
			[t('col_modified'), esc(it.date) + ' <span class="flxfm-muted">· ' + esc(it.ago) + '</span>'],
			[t('col_perms'), '<code>' + esc(it.perms) + '</code>' + (it.writable ? '' : ' <span class="flxfm-tag">' + esc(t('not_writable')) + '</span>')]
		];
		var flags = [];
		if (it.locked) flags.push('<span class="flxfm-tag">' + ic('lock') + esc(t('locked_hint')) + '</span>');
		if (it.sensitive) flags.push('<span class="flxfm-tag is-warn">' + ic('shield') + esc(t('sensitive_hint')) + '</span>');
		if (it.core) flags.push('<span class="flxfm-tag">' + esc(t('core_hint')) + '</span>');
		if (it.shared) flags.push('<span class="flxfm-tag is-ok">' + ic('globe') + esc(t('shared_title')) + ' · <code>[flx_files folder="' + esc(it.path) + '"]</code></span>');
		if (flags.length) rows.push([t('notes'), flags.join(' ')]);
		var m = modal({ title: it.type === 'dir' ? t('folder_info') : t('file_info'), body: '<dl class="flxfm-dl">' + rows.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + r[1] + '</dd>'; }).join('') + '</dl>' });
		var calc = m.body.querySelector('[data-calc]');
		if (calc) calc.addEventListener('click', function () {
			calc.parentNode.textContent = t('calculating');
			api('size', { path: it.path }).then(function (r) {
				m.body.querySelector('.flxfm-dsize').textContent = size(r.size) + ' · ' + t('files_n', r.files) + (r.partial ? ' · ' + t('partial') : '');
			}).catch(fail);
		});
	}

	function permsDialog(it) {
		var who = [['owner', t('perm_owner')], ['group', t('perm_group')], ['public', t('perm_public')]];
		var what = [[4, t('perm_read')], [2, t('perm_write')], [1, t('perm_exec')]];
		var mode = it.perms.slice(-3);
		var html = '<p class="flxfm-muted">' + esc(it.path) + '</p><table class="flxfm-perm"><thead><tr><th></th>' + what.map(function (w) { return '<th>' + esc(w[1]) + '</th>'; }).join('') + '</tr></thead><tbody>' +
			who.map(function (w, i) { return '<tr><th>' + esc(w[1]) + '</th>' + what.map(function (x) { return '<td><input type="checkbox" data-w="' + i + '" data-v="' + x[0] + '"' + (parseInt(mode[i], 10) & x[0] ? ' checked' : '') + (i === 2 && x[0] === 2 ? ' disabled title="' + esc(t('perm_public_write')) + '"' : '') + '></td>'; }).join('') + '</tr>'; }).join('') +
			'</tbody></table><label class="flxfm-field"><span>' + esc(t('perm_numeric')) + '</span><input type="text" class="flxfm-octal" inputmode="numeric" maxlength="3" value="' + esc(mode) + '"></label>' +
			'<p class="flxfm-muted">' + esc(t('perm_hint')) + '</p>';
		var m = modal({ title: t('permissions'), body: html, foot: '<button type="button" class="flxfm-btn" data-close>' + esc(t('cancel')) + '</button><button type="button" class="flxfm-btn flxfm-btn--pri" data-save>' + esc(t('save')) + '</button>' });
		var oct = m.el.querySelector('.flxfm-octal');
		function fromBoxes() { var d = [0, 0, 0]; $$('input[data-w]', m.el).forEach(function (c) { if (c.checked) d[+c.dataset.w] += +c.dataset.v; }); oct.value = d.join(''); }
		function toBoxes() { if (!/^[0-7]{3}$/.test(oct.value)) return; $$('input[data-w]', m.el).forEach(function (c) { c.checked = !!(parseInt(oct.value[+c.dataset.w], 10) & +c.dataset.v); }); }
		m.el.addEventListener('change', function (e) { if (e.target.dataset.w) fromBoxes(); });
		oct.addEventListener('input', toBoxes);
		m.el.querySelector('[data-save]').addEventListener('click', function () {
			api('chmod', { path: it.path, mode: oct.value }).then(function () { toast(t('saved')); m.close(); refresh(); }).catch(fail);
		});
	}

	/* Klasör seçici (taşı / kopyala). */
	function pickFolder(title, cb) {
		var cur = S.cwd;
		var m = modal({ title: title, body: '<div class="flxfm-pick"><div class="flxfm-pick-crumbs"></div><ul class="flxfm-pick-list"></ul></div>', foot: '<button type="button" class="flxfm-btn" data-close>' + esc(t('cancel')) + '</button><button type="button" class="flxfm-btn flxfm-btn--pri" data-ok>' + esc(t('choose_here')) + '</button>' });
		function show(p) {
			cur = p;
			var parts = p ? p.split('/') : [];
			var acc = '';
			m.el.querySelector('.flxfm-pick-crumbs').innerHTML = '<button type="button" data-p="">' + esc(C.rootName) + '</button>' + parts.map(function (x) { acc = join(acc, x); return ic('right', 'flxfm-i-sm') + '<button type="button" data-p="' + esc(acc) + '">' + esc(x) + '</button>'; }).join('');
			var ul = m.el.querySelector('.flxfm-pick-list');
			ul.innerHTML = '<li class="flxfm-muted">' + esc(t('loading')) + '</li>';
			api('tree', { path: p }).then(function (r) {
				var dirs = r.items.filter(function (i) { return i.type === 'dir'; });
				ul.innerHTML = dirs.length ? dirs.map(function (d) { return '<li><button type="button" data-p="' + esc(d.path) + '">' + ic('folder') + esc(d.name) + '</button></li>'; }).join('') : '<li class="flxfm-muted">' + esc(t('no_subfolders')) + '</li>';
			}).catch(fail);
		}
		m.el.addEventListener('click', function (e) { var b = e.target.closest('[data-p]'); if (b) show(b.getAttribute('data-p')); });
		m.el.querySelector('[data-ok]').addEventListener('click', function () { m.close(); cb(cur); });
		show(cur);
	}

	/* ------------------------------------------------------------- önizleme */
	function preview(it) {
		var list = visible().filter(function (i) { return ['image', 'video', 'audio', 'pdf'].indexOf(i.kind) >= 0; });
		var idx = Math.max(0, list.map(function (i) { return i.path; }).indexOf(it.path));
		var m = modal({ title: it.name, full: true, body: '<div class="flxfm-pv"></div>', foot: '<span class="flxfm-pv-meta"></span><button type="button" class="flxfm-btn" data-prev aria-label="' + esc(t('previous')) + '">' + ic('left') + '</button><button type="button" class="flxfm-btn" data-next aria-label="' + esc(t('next')) + '">' + ic('right') + '</button><button type="button" class="flxfm-btn flxfm-btn--pri" data-dl>' + ic('download') + esc(t('download')) + '</button>' });
		function show() {
			var x = list[idx];
			m.el.querySelector('h2').textContent = x.name;
			var u = rawUrl(x.path);
			var pv = m.el.querySelector('.flxfm-pv');
			pv.innerHTML = x.kind === 'image' ? '<img src="' + esc(u) + '" alt="">' :
				x.kind === 'video' ? '<video src="' + esc(u) + '" controls autoplay playsinline></video>' :
				x.kind === 'audio' ? '<audio src="' + esc(u) + '" controls autoplay></audio>' :
				'<iframe src="' + esc(u) + '" title="' + esc(x.name) + '"></iframe>';
			m.el.querySelector('.flxfm-pv-meta').textContent = size(x.size) + ' · ' + x.ago + (list.length > 1 ? ' · ' + (idx + 1) + '/' + list.length : '');
			m.el.querySelector('[data-prev]').disabled = idx <= 0;
			m.el.querySelector('[data-next]').disabled = idx >= list.length - 1;
		}
		m.el.querySelector('[data-prev]').addEventListener('click', function () { if (idx > 0) { idx--; show(); } });
		m.el.querySelector('[data-next]').addEventListener('click', function () { if (idx < list.length - 1) { idx++; show(); } });
		m.el.querySelector('[data-dl]').addEventListener('click', function () { download([list[idx].path]); });
		m.el.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft' && idx > 0) { idx--; show(); } if (e.key === 'ArrowRight' && idx < list.length - 1) { idx++; show(); } });
		show();
	}

	/* --------------------------------------------------------------- sürümler */
	function versionsDialog(it, tab) {
		var m = modal({ title: t('versions') + ' · ' + it.name, wide: true, body: '<p class="flxfm-muted">' + esc(t('versions_hint', C.s.versions)) + '</p><div class="flxfm-vers"><ul class="flxfm-vlist"><li class="flxfm-muted">' + esc(t('loading')) + '</li></ul><div class="flxfm-vview"></div></div>' });
		var sel = null;
		api('versions', { path: it.path }).then(function (r) {
			var ul = m.el.querySelector('.flxfm-vlist');
			if (!r.versions.length) { ul.innerHTML = '<li class="flxfm-muted">' + esc(t('no_versions')) + '</li>'; return; }
			ul.innerHTML = r.versions.map(function (v) {
				return '<li><button type="button" data-v="' + esc(v.id) + '"><b title="' + esc(v.date) + '">' + esc(v.ago) + '</b><span>' + esc(v.user) + ' · ' + viaBadge(v.via) + ' · ' + esc(size(v.size)) + '</span></button></li>';
			}).join('');
			ul.querySelector('button').click();
		}).catch(fail);
		m.el.addEventListener('click', function (e) {
			var b = e.target.closest('[data-v]');
			if (b) {
				sel = b.getAttribute('data-v');
				$$('[data-v]', m.el).forEach(function (x) { x.classList.toggle('is-on', x === b); });
				var view = m.el.querySelector('.flxfm-vview');
				view.innerHTML = '<p class="flxfm-muted">' + esc(t('loading')) + '</p>';
				Promise.all([api('version', { path: it.path, id: sel }), tab ? Promise.resolve({ content: tab.cm.getValue() }) : api('read', { path: it.path })]).then(function (rs) {
					view.innerHTML = '<div class="flxfm-vact"><span class="flxfm-muted">' + esc(t('diff_vs_current')) + '</span><button type="button" class="flxfm-btn flxfm-btn--pri" data-revert>' + ic('restore') + esc(t('restore_version')) + '</button></div>' + diffHtml(rs[0].content, rs[1].content);
				}).catch(function (er) { view.innerHTML = '<p class="flxfm-err">' + esc(er.message) + '</p>'; });
			}
			if (e.target.closest('[data-revert]') && sel) {
				D.confirm({ title: t('restore_version'), message: t('restore_version_msg'), ok: t('restore_version') }).then(function (ok) {
					if (!ok) return;
					return api('revert', { path: it.path, id: sel }).then(function () {
						toast(t('restored'));
						m.close();
						var tb = E.tabs.filter(function (x) { return x.path === it.path; })[0];
						if (tb) reloadTab(tb, true);
						if (S.mode === 'browse') refresh();
					});
				}).catch(fail);
			}
		});
	}
	function viaBadge(v) { return '<span class="flxfm-via flxfm-via--' + esc(v) + '">' + esc(t('via_' + v)) + '</span>'; }

	/* Basit satır farkı (LCS; büyük dosyada kırpılır). */
	function diffLines(a, b) {
		var A = a.split('\n'), B = b.split('\n');
		var s = 0; while (s < A.length && s < B.length && A[s] === B[s]) s++;
		var ea = A.length, eb = B.length; while (ea > s && eb > s && A[ea - 1] === B[eb - 1]) { ea--; eb--; }
		var X = A.slice(s, ea), Y = B.slice(s, eb), out = [];
		for (var i0 = Math.max(0, s - 3); i0 < s; i0++) out.push([' ', A[i0], i0 + 1]);
		if (s > 3) out.unshift(['@', t('lines_same', s - 3)]);
		if (X.length * Y.length > 4000000) {
			X.forEach(function (l) { out.push(['-', l]); }); Y.forEach(function (l) { out.push(['+', l]); });
		} else {
			var n = X.length, m2 = Y.length, T = [];
			for (var i = 0; i <= n; i++) { T.push(new Uint32Array(m2 + 1)); }
			for (i = n - 1; i >= 0; i--) for (var j = m2 - 1; j >= 0; j--) T[i][j] = X[i] === Y[j] ? T[i + 1][j + 1] + 1 : Math.max(T[i + 1][j], T[i][j + 1]);
			i = 0; j = 0;
			while (i < n || j < m2) {
				if (i < n && j < m2 && X[i] === Y[j]) { out.push([' ', X[i], s + i + 1]); i++; j++; }
				else if (j < m2 && (i >= n || T[i][j + 1] >= T[i + 1][j])) { out.push(['+', Y[j]]); j++; }
				else { out.push(['-', X[i]]); i++; }
			}
		}
		var tail = A.length - ea;
		for (var k = ea; k < Math.min(A.length, ea + 3); k++) out.push([' ', A[k], k + 1]);
		if (tail > 3) out.push(['@', t('lines_same', tail - 3)]);
		// Uzun değişmeyen bloklar daraltılır.
		var res = [], run = [];
		out.forEach(function (o) {
			if (o[0] === ' ') { run.push(o); return; }
			flush(); res.push(o);
		});
		flush();
		function flush() { if (run.length > 8) { res.push.apply(res, run.slice(0, 3)); res.push(['@', t('lines_same', run.length - 6)]); res.push.apply(res, run.slice(-3)); } else res.push.apply(res, run); run = []; }
		return res;
	}
	function diffHtml(oldText, newText) {
		if (oldText === newText) return '<p class="flxfm-muted">' + esc(t('no_changes')) + '</p>';
		var d = diffLines(oldText, newText);
		var add = d.filter(function (x) { return x[0] === '+'; }).length, del = d.filter(function (x) { return x[0] === '-'; }).length;
		return '<div class="flxfm-diffstat"><span class="is-add">+' + add + '</span> <span class="is-del">−' + del + '</span></div><pre class="flxfm-diff">' + d.map(function (x) {
			return '<span class="flxfm-d flxfm-d--' + (x[0] === '+' ? 'add' : x[0] === '-' ? 'del' : x[0] === '@' ? 'gap' : 'eq') + '">' + esc(x[0] === '@' ? '⋯ ' + x[1] : x[0] + ' ' + x[1]) + '</span>';
		}).join('') + '</pre>';
	}

	/* ------------------------------------------------------------- yükleme */
	var UP = { queue: [], active: 0, choice: null, total: 0, done: 0 };
	$('.flxfm-in-files').addEventListener('change', function () { enqueue(Array.from(this.files).map(function (f) { return { file: f, name: f.name }; }), S.cwd); this.value = ''; });
	$('.flxfm-in-dir').addEventListener('change', function () { enqueue(Array.from(this.files).map(function (f) { return { file: f, name: f.webkitRelativePath || f.name }; }), S.cwd); this.value = ''; });

	var dragDepth = 0, dragInternal = null;
	var card = $('.flxfm-browse');
	card.addEventListener('dragstart', function (e) {
		var row = e.target.closest('[data-path]');
		if (!row) return;
		var p = row.getAttribute('data-path');
		if (!S.sel.has(p)) setSel([p]);
		dragInternal = Array.from(S.sel);
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', dragInternal.join('\n'));
	});
	card.addEventListener('dragend', function () { dragInternal = null; $$('.is-dropt').forEach(function (x) { x.classList.remove('is-dropt'); }); });
	card.addEventListener('dragenter', function (e) {
		if (dragInternal || !C.writable || !hasFiles(e)) return;
		e.preventDefault(); dragDepth++;
		card.classList.add('is-dragging');
		$('.flxfm-drop-to').textContent = '/' + S.cwd;
	});
	card.addEventListener('dragleave', function () { if (dragInternal) return; dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) card.classList.remove('is-dragging'); });
	card.addEventListener('dragover', function (e) {
		if (dragInternal) {
			var tg = e.target.closest('.is-dir[data-path], [data-drop]');
			$$('.is-dropt').forEach(function (x) { if (x !== tg) x.classList.remove('is-dropt'); });
			if (tg) { var tp = tg.getAttribute('data-path') !== null ? tg.getAttribute('data-path') : tg.getAttribute('data-drop'); if (dragInternal.indexOf(tp) < 0) { e.preventDefault(); tg.classList.add('is-dropt'); } }
			return;
		}
		if (C.writable && hasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; }
	});
	card.addEventListener('drop', function (e) {
		if (dragInternal) {
			var tg = e.target.closest('.is-dir[data-path], [data-drop]');
			if (!tg) return;
			e.preventDefault();
			var dest = tg.getAttribute('data-path') !== null ? tg.getAttribute('data-path') : tg.getAttribute('data-drop');
			var paths = dragInternal; dragInternal = null;
			$$('.is-dropt').forEach(function (x) { x.classList.remove('is-dropt'); });
			if (paths.indexOf(dest) < 0 && dest !== S.cwd) transfer('move', paths, dest);
			return;
		}
		if (!C.writable || !hasFiles(e)) return;
		e.preventDefault(); dragDepth = 0; card.classList.remove('is-dragging');
		var row = e.target.closest('.is-dir[data-path]');
		var dest2 = row ? row.getAttribute('data-path') : S.cwd;
		collectDrop(e.dataTransfer).then(function (files) { enqueue(files, dest2); });
	});
	function hasFiles(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0; }
	function collectDrop(dt) {
		var items = dt.items ? Array.from(dt.items) : [];
		var entries = items.map(function (i) { return i.webkitGetAsEntry ? i.webkitGetAsEntry() : null; }).filter(Boolean);
		if (!entries.length) return Promise.resolve(Array.from(dt.files).map(function (f) { return { file: f, name: f.name }; }));
		var out = [];
		function walk(en, pre) {
			if (en.isFile) return new Promise(function (res) { en.file(function (f) { out.push({ file: f, name: pre + f.name }); res(); }, function () { res(); }); });
			if (en.isDirectory) {
				var rd = en.createReader();
				return new Promise(function (res) {
					var all = [];
					(function more() { rd.readEntries(function (batch) { if (!batch.length) { Promise.all(all.map(function (c) { return walk(c, pre + en.name + '/'); })).then(res); } else { all = all.concat(Array.from(batch)); more(); } }, function () { res(); }); })();
				});
			}
			return Promise.resolve();
		}
		return Promise.all(entries.map(function (en) { return walk(en, ''); })).then(function () { return out; });
	}
	function enqueue(files, dest) {
		if (!files.length) return;
		var panel = $('.flxfm-uploads');
		panel.hidden = false;
		UP.choice = null;
		files.forEach(function (f) {
			var li = h('<li><span class="flxfm-up-n" title="' + esc(f.name) + '">' + esc(f.name) + '</span><span class="flxfm-up-s">' + esc(size(f.file.size)) + '</span><span class="flxfm-up-bar"><i></i></span></li>');
			panel.querySelector('ul').appendChild(li);
			UP.queue.push({ file: f.file, name: f.name, dest: dest, li: li });
			UP.total++;
		});
		upHead();
		pump();
	}
	function upHead() { $('.flxfm-up-head b').textContent = UP.done < UP.total ? t('uploading_n', UP.done, UP.total).replace('%2$s', UP.total) : t('uploaded_n', UP.total); }
	function pump() {
		while (UP.active < 2 && UP.queue.length) { UP.active++; uploadOne(UP.queue.shift()).then(function () { UP.active--; UP.done++; upHead(); if (!UP.queue.length && !UP.active) { refresh(); UP.choice = null; } pump(); }); }
	}
	function uploadOne(job, overwrite) {
		var id = (Date.now().toString(36) + Math.random().toString(36).slice(2, 10)).replace(/[^a-z0-9]/g, '');
		var chunk = C.chunk, f = job.file, off = 0;
		var bar = job.li.querySelector('i');
		function send() {
			return new Promise(function (resolve, reject) {
				var fd = new FormData();
				var end = Math.min(f.size, off + chunk);
				fd.append('file', f.slice(off, end), 'blob');
				fd.append('dir', job.dest); fd.append('name', job.name); fd.append('id', id);
				fd.append('offset', off); fd.append('total', f.size); if (overwrite) fd.append('overwrite', '1');
				var x = new XMLHttpRequest();
				x.open('POST', restUrl('upload'));
				x.setRequestHeader('X-WP-Nonce', C.nonce);
				x.upload.onprogress = function (ev) { if (ev.lengthComputable && f.size) bar.style.width = Math.round((off + ev.loaded) / f.size * 100) + '%'; };
				x.onload = function () {
					var j = null; try { j = JSON.parse(x.responseText); } catch (e) { /* yoksay */ }
					if (x.status === 200 && j && j.ok) { off = end; resolve(j); }
					else { var er = new Error((j && j.message) || t('upload_failed') + ' (HTTP ' + x.status + ')'); er.code = j && j.code; reject(er); }
				};
				x.onerror = function () { reject(new Error(t('upload_failed'))); };
				x.send(fd);
			}).then(function (j) { if (off < f.size) return send(); return j; });
		}
		return send().then(function () {
			bar.style.width = '100%'; job.li.classList.add('is-ok');
		}).catch(function (e) {
			if (e.code === 'flxfm_exists' && !overwrite) {
				var ask = UP.choice ? Promise.resolve(UP.choice) : D.confirm({ title: t('conflict_title'), message: t('upload_conflict', job.name), ok: t('overwrite'), cancel: t('skip') }).then(function (ok) { UP.choice = ok ? 'over' : 'skip'; return UP.choice; });
				return ask.then(function (c) { if (c === 'over') return uploadOne(job, true); job.li.classList.add('is-skip'); job.li.querySelector('.flxfm-up-s').textContent = t('skipped'); });
			}
			job.li.classList.add('is-bad');
			job.li.querySelector('.flxfm-up-s').textContent = e.message;
		});
	}

	/* ----------------------------------------------------------------- mod */
	function setMode(m, opts) {
		if (m === 'editor' && !C.writable && !E.tabs.length) { /* salt okunur da açılabilir */ }
		S.mode = m;
		root.dataset.mode = m;
		$$('.flxfm-seg [data-mode]').forEach(function (b) { var on = b.getAttribute('data-mode') === m; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); });
		$('.flxfm-browse').hidden = m === 'editor';
		$('.flxfm-editor').hidden = m !== 'editor';
		$('.flxfm-tools').hidden = m === 'trash' || m === 'log';
		$('.flxfm-nav').hidden = m === 'trash' || m === 'log';
		closeMenu();
		if (m === 'browse') { render(); }
		else if (m === 'editor') { editorShow(); }
		else if (m === 'trash') showTrash();
		else if (m === 'log') { LOG.path = opts && opts.path || ''; LOG.via = ''; LOG.page = 1; showLog(); }
		renderTrail();
		$('.flxfm-selbar').hidden = m !== 'browse' || !S.sel.size;
	}

	/* ------------------------------------------------------------- çöp kutusu */
	function showTrash() {
		renderCrumbs(); renderTrail();
		body.classList.add('is-loading');
		return api('trash_list').then(function (r) {
			var html = '<div class="flxfm-subhead"><p>' + esc(r.days ? t('trash_days', r.days) : t('trash_forever')) + '</p>' + (r.items.length && C.writable ? '<button type="button" class="flxfm-btn is-danger" data-tr="empty">' + ic('trash') + esc(t('empty_trash')) + '</button>' : '') + '</div>';
			if (!r.items.length) html += '<div class="flxfm-empty">' + ic('trash') + '<p>' + esc(t('trash_empty')) + '</p></div>';
			else html += '<div class="flxfm-table flxfm-table--trash"><div class="flxfm-thead"><span class="flxfm-c-name">' + esc(t('col_name')) + '</span><span class="flxfm-c-where">' + esc(t('col_origin')) + '</span><span class="flxfm-c-mod">' + esc(t('col_deleted')) + '</span><span class="flxfm-c-act"></span></div>' +
				r.items.map(function (it) {
					return '<div class="flxfm-row" data-id="' + esc(it.id) + '"><span class="flxfm-c-name">' + fileIcon({ type: it.type, name: it.name, ext: (it.name.split('.').length > 1 ? it.name.split('.').pop().toLowerCase() : ''), kind: it.kind }) + '<span class="flxfm-nm"><span class="flxfm-nm-t">' + esc(it.name) + '</span></span></span>' +
						'<span class="flxfm-c-where" title="' + esc(it.path) + '">' + esc(dir(it.path) || '/') + '</span>' +
						'<span class="flxfm-c-mod" title="' + esc(it.date) + '">' + esc(it.ago) + ' · ' + esc(it.user) + ' ' + viaBadge(it.via) + (it.until ? '<small>' + esc(t('until', it.until)) + '</small>' : '') + '</span>' +
						'<span class="flxfm-c-act">' + (C.writable ? '<button type="button" class="flxfm-btn" data-tr="restore">' + ic('restore') + '<span>' + esc(t('restore')) + '</span></button><button type="button" class="flxfm-ib is-danger" data-tr="purge" aria-label="' + esc(t('delete_forever')) + '" title="' + esc(t('delete_forever')) + '">' + ic('trash') + '</button>' : '') + '</span></div>';
				}).join('') + '</div>';
			body.innerHTML = html;
			$('.flxfm-count').innerHTML = '<span>' + esc(t('items_n', r.items.length)) + '</span>';
		}).catch(fail).then(function () { body.classList.remove('is-loading'); });
	}
	body.addEventListener('click', function (e) {
		var b = e.target.closest('[data-tr]');
		if (!b || S.mode !== 'trash') return;
		var a = b.getAttribute('data-tr');
		var row = b.closest('[data-id]');
		var id = row && row.getAttribute('data-id');
		if (a === 'restore') api('restore', { ids: [id] }).then(function (r) { report(r); if (r.done.length) toast(t('restored_to', r.done[0].path)); showTrash(); }).catch(fail);
		if (a === 'purge') D.confirm({ title: t('delete_forever'), message: t('delete_forever_msg'), ok: t('delete_forever'), danger: true }).then(function (ok) { if (ok) return api('purge', { ids: [id] }).then(showTrash); }).catch(fail);
		if (a === 'empty') D.confirm({ title: t('empty_trash'), message: t('empty_trash_msg'), ok: t('empty_trash'), danger: true }).then(function (ok) { if (ok) return api('purge', { all: true }).then(function (r) { toast(t('removed_n', r.removed)); showTrash(); }); }).catch(fail);
	});

	/* --------------------------------------------------------------- etkinlik */
	var LOG = { path: '', via: '', page: 1, rows: [] };
	function showLog(more) {
		renderCrumbs(); renderTrail();
		if (!more) { LOG.page = 1; LOG.rows = []; body.classList.add('is-loading'); }
		return api('log', { filter_path: LOG.path, via: LOG.via, page: LOG.page }).then(function (r) {
			LOG.rows = LOG.rows.concat(r.rows);
			var chips = ['', 'panel', 'ai', 'mcp'].map(function (v) { return '<button type="button" class="flxfm-chipbtn' + (LOG.via === v ? ' is-on' : '') + '" data-lv="' + v + '">' + esc(v ? t('via_' + v) : t('all')) + '</button>'; }).join('');
			var html = '<div class="flxfm-subhead"><div class="flxfm-chips">' + chips + '</div>' + (LOG.path ? '<span class="flxfm-tag">' + esc(LOG.path) + ' <button type="button" class="flxfm-link" data-lv-clear>' + ic('x', 'flxfm-i-sm') + '</button></span>' : '') + '</div>';
			if (!LOG.rows.length) html += '<div class="flxfm-empty">' + ic('activity') + '<p>' + esc(t('log_empty')) + '</p></div>';
			else html += '<ol class="flxfm-log">' + LOG.rows.map(function (e) {
				var target = e.p ? '<button type="button" class="flxfm-link" data-lp="' + esc(e.p) + '">' + esc(e.p) + '</button>' : '';
				var to = e.to ? ' <span class="flxfm-muted">→</span> ' + (e.a === 'chmod' ? '<code>' + esc(e.to) + '</code>' : '<button type="button" class="flxfm-link" data-lp="' + esc(e.to) + '">' + esc(e.to) + '</button>') : '';
				return '<li><span class="flxfm-log-dot flxfm-log-' + esc(e.a) + '"></span><div><div class="flxfm-log-line"><b>' + esc(t('a_' + e.a)) + '</b> ' + target + to + '</div><div class="flxfm-log-meta"><span title="' + esc(e.date) + '">' + esc(e.ago) + '</span> · ' + esc(e.user) + ' · ' + viaBadge(e.via) + '</div></div></li>';
			}).join('') + '</ol>' + (r.page < r.pages ? '<div class="flxfm-more"><button type="button" class="flxfm-btn" data-lmore>' + esc(t('load_more')) + '</button></div>' : '');
			body.innerHTML = html;
			$('.flxfm-count').innerHTML = '<span>' + esc(t('events_n', r.total)) + '</span>';
		}).catch(fail).then(function () { body.classList.remove('is-loading'); });
	}
	body.addEventListener('click', function (e) {
		if (S.mode !== 'log') return;
		var c = e.target.closest('[data-lv]');
		if (c) { LOG.via = c.getAttribute('data-lv'); showLog(); return; }
		if (e.target.closest('[data-lv-clear]')) { LOG.path = ''; showLog(); return; }
		if (e.target.closest('[data-lmore]')) { LOG.page++; showLog(true); return; }
		var p = e.target.closest('[data-lp]');
		if (p) {
			var path = p.getAttribute('data-lp');
			setMode('browse');
			go(dir(path)).then(function () { if (S.items.some(function (i) { return i.path === path; })) setSel([path]); else if (S.cwd !== path) go(path); });
		}
	});

	/* ---------------------------------------------------------------- ayarlar */
	function openSettings() {
		var s = C.s;
		var shared = (s.shared || []).map(function (p) { return '<li><code>[flx_files folder="' + esc(p) + '"]</code><button type="button" class="flxfm-ib" data-copy="' + esc('[flx_files folder="' + p + '"]') + '" aria-label="' + esc(t('copy')) + '">' + ic('copy') + '</button><button type="button" class="flxfm-ib is-danger" data-unshare="' + esc(p) + '" aria-label="' + esc(t('unshare')) + '">' + ic('x') + '</button></li>'; }).join('');
		var html =
			'<h3>' + esc(t('set_view')) + '</h3>' +
			sw('show_hidden', t('set_hidden'), t('set_hidden_d')) +
			'<label class="flxfm-field flxfm-field--row"><span><b>' + esc(t('set_quota')) + '</b><small>' + esc(t('set_quota_d')) + '</small></span><input type="number" min="0" step="1" name="quota_gb" value="' + esc(s.quota_gb || 0) + '"></label>' +
			'<h3>' + esc(t('set_safety')) + '</h3>' +
			'<label class="flxfm-field flxfm-field--row"><span><b>' + esc(t('set_trash')) + '</b><small>' + esc(t('set_trash_d')) + '</small></span><input type="number" min="0" max="365" name="trash_days" value="' + esc(s.trash_days) + '"></label>' +
			'<label class="flxfm-field flxfm-field--row"><span><b>' + esc(t('set_versions')) + '</b><small>' + esc(t('set_versions_d')) + '</small></span><input type="number" min="0" max="50" name="versions" value="' + esc(s.versions) + '"></label>' +
			'<h3>' + esc(t('set_ai')) + '</h3>' +
			'<p class="flxfm-muted">' + esc(t('set_ai_d')) + '</p>' +
			sw('mcp_write', t('set_mcp'), t('set_mcp_d')) +
			sw('ai_code', t('set_code'), t('set_code_d')) +
			'<h3>' + esc(t('set_shared')) + '</h3>' +
			'<p class="flxfm-muted">' + esc(t('set_shared_d')) + '</p>' +
			(shared ? '<ul class="flxfm-shared">' + shared + '</ul>' : '<p class="flxfm-muted"><i>' + esc(t('set_shared_none')) + '</i></p>');
		function sw(k, label, d) { return '<label class="flxfm-switch"><input type="checkbox" name="' + k + '"' + (+s[k] ? ' checked' : '') + '><span class="flxfm-sw"></span><span><b>' + esc(label) + '</b><small>' + esc(d) + '</small></span></label>'; }
		var m = modal({ title: t('settings'), body: '<form class="flxfm-settings">' + html + '</form>', foot: '<button type="button" class="flxfm-btn" data-close>' + esc(t('cancel')) + '</button><button type="button" class="flxfm-btn flxfm-btn--pri" data-save>' + esc(t('save')) + '</button>', onClose: function () { try { if (location.hash === '#settings') history.replaceState(null, '', '#path=' + encodeURIComponent(S.cwd)); } catch (e) { /* yoksay */ } } });
		m.el.addEventListener('click', function (e) {
			var c = e.target.closest('[data-copy]'); if (c) copyText(c.getAttribute('data-copy'));
			var u = e.target.closest('[data-unshare]');
			if (u) api('share', { path: u.getAttribute('data-unshare'), on: false }).then(function () { C.s.shared = (C.s.shared || []).filter(function (p) { return p !== u.getAttribute('data-unshare'); }); u.closest('li').remove(); refresh(); }).catch(fail);
		});
		m.el.querySelector('[data-save]').addEventListener('click', function () {
			var f = m.el.querySelector('form');
			var data = { show_hidden: f.show_hidden.checked, mcp_write: f.mcp_write.checked, ai_code: f.ai_code.checked, trash_days: f.trash_days.value, versions: f.versions.value, quota_gb: f.quota_gb.value };
			var risky = (data.mcp_write && !+s.mcp_write) || (data.ai_code && !+s.ai_code);
			(risky ? D.confirm({ title: t('set_ai'), message: t('set_risky'), ok: t('save'), danger: true }) : Promise.resolve(true)).then(function (ok) {
				if (!ok) return;
				return api('settings', { settings: data }).then(function (r) { C.s = r.settings; toast(t('saved')); m.close(); S.disk = null; refresh(); });
			}).catch(fail);
		});
	}

	/* =================================================================== EDİTÖR */
	var E = { tabs: [], active: null, treeOpen: {}, treeLoaded: false };
	var CM = window.wp && window.wp.CodeMirror;
	var MODES = { php: 'application/x-httpd-php', phtml: 'application/x-httpd-php', js: 'text/javascript', mjs: 'text/javascript', cjs: 'text/javascript', jsx: 'text/jsx', ts: 'text/typescript', tsx: 'text/typescript-jsx', json: 'application/json', css: 'text/css', scss: 'text/x-scss', sass: 'text/x-sass', less: 'text/x-less', html: 'text/html', htm: 'text/html', svg: 'application/xml', xml: 'application/xml', md: 'text/x-gfm', markdown: 'text/x-gfm', sql: 'text/x-mysql', yml: 'text/x-yaml', yaml: 'text/x-yaml', sh: 'text/x-sh', conf: 'text/x-nginx-conf', twig: 'text/html' };

	function editorShow() {
		if (!E.treeLoaded) { E.treeLoaded = true; $('.flxfm-tree-head b').textContent = C.rootName; treeLoad('', $('.flxfm-tree-body')); }
		renderTabs();
		if (E.active && E.active.cm) setTimeout(function () { E.active.cm.refresh(); E.active.cm.focus(); }, 0);
	}
	function treeKey(it) { return it.type + '|' + it.name + '|' + (it.ext || '') + '|' + (it.shared ? 1 : 0); }
	function treeNode(it, path) {
		var open = !!E.treeOpen[it.path];
		return h('<div class="flxfm-tn' + (it.type === 'dir' ? ' is-dir' : '') + (open ? ' is-open' : '') + '" data-tp="' + esc(it.path) + '" data-tt="' + it.type + '" data-tk="' + esc(treeKey(it)) + '">' +
			'<button type="button" class="flxfm-tn-row' + (E.active && E.active.path === it.path ? ' is-on' : '') + '" style="--d:' + (path ? path.split('/').length : 0) + '">' +
			(it.type === 'dir' ? '<span class="flxfm-tn-caret">' + ic('right', 'flxfm-i-sm') + '</span>' : '<span class="flxfm-tn-caret"></span>') +
			fileIcon(it) + '<span class="flxfm-tn-name">' + esc(it.name) + '</span></button>' +
			(it.type === 'dir' ? '<div class="flxfm-tn-kids"></div>' : '') + '</div>');
	}
	/*
	 * Klasörün çocuklarını yükler. Doluysa yeniden çizmez: var olan düğümler yerinde kalır (açık klasörler açık kalır),
	 * yalnız eklenen eklenir, silinen çıkar, sıra düzeltilir; açık alt klasörler aynı yolla tazelenir.
	 */
	function treeLoad(path, ul) {
		var had = !!ul.querySelector(':scope > .flxfm-tn');
		if (!had) ul.innerHTML = '<div class="flxfm-tree-loading">' + esc(t('loading')) + '</div>';
		return api('tree', { path: path }).then(function (r) {
			var old = {};
			$$(':scope > .flxfm-tn', ul).forEach(function (n) { old[n.getAttribute('data-tp')] = n; });
			$$(':scope > .flxfm-tree-loading', ul).forEach(function (n) { ul.removeChild(n); });
			var prev = null, jobs = [];
			r.items.forEach(function (it) {
				var n = old[it.path];
				if (n && n.getAttribute('data-tk') === treeKey(it)) delete old[it.path];
				else n = treeNode(it, path);
				var at = prev ? prev.nextSibling : ul.firstChild;
				if (n !== at) ul.insertBefore(n, at);
				prev = n;
				if (it.type === 'dir' && n.classList.contains('is-open')) jobs.push(treeLoad(it.path, n.querySelector('.flxfm-tn-kids')));
			});
			Object.keys(old).forEach(function (k) { if (old[k].parentNode === ul) ul.removeChild(old[k]); });
			if (!r.items.length) ul.innerHTML = '<div class="flxfm-tree-loading">' + esc(t('empty_folder')) + '</div>';
			return Promise.all(jobs);
		}).catch(function (e) { if (!had) ul.innerHTML = '<div class="flxfm-tree-loading">' + esc(e.message) + '</div>'; else toast(e.message, 'error'); });
	}
	function treeRefreshSoft() { if (E.treeLoaded) treeLoad('', $('.flxfm-tree-body')); }
	$('.flxfm-tree-body').addEventListener('click', function (e) {
		var row = e.target.closest('.flxfm-tn-row');
		if (!row) return;
		var n = row.parentNode, p = n.getAttribute('data-tp');
		if (n.getAttribute('data-tt') === 'dir') {
			var open = !n.classList.contains('is-open');
			n.classList.toggle('is-open', open);
			E.treeOpen[p] = open;
			if (open) treeLoad(p, n.querySelector('.flxfm-tn-kids'));
		} else {
			openEditor(p);
			if (window.innerWidth <= 782) root.classList.add('flxfm-tree-off');
		}
	});
	$('.flxfm-tree-body').addEventListener('contextmenu', function (e) {
		var row = e.target.closest('.flxfm-tn');
		if (!row) return;
		e.preventDefault();
		var p = row.getAttribute('data-tp'), isDir = row.getAttribute('data-tt') === 'dir';
		var items = [
			isDir ? null : { icon: 'code', label: t('edit'), run: function () { openEditor(p); } },
			isDir && C.writable ? { icon: 'plus', label: t('new'), sub: newItems(p) } : null,
			{ icon: 'browser', label: t('show_in_browser'), run: function () { setMode('browse'); go(isDir ? p : dir(p)).then(function () { if (!isDir) setSel([p]); }); } },
			{ icon: 'download', label: isDir ? t('download_zip') : t('download'), run: function () { download([p]); } },
			{ icon: 'link', label: t('copy_path'), sub: pathItems({ path: p, type: isDir ? 'dir' : 'file' }) }
		];
		menu(items, e.clientX, e.clientY);
	});

	function openEditor(path) {
		setMode('editor');
		var ex = E.tabs.filter(function (x) { return x.path === path; })[0];
		if (ex) { activate(ex); return; }
		var tab = { path: path, name: base(path), dirty: false, cm: null, el: null, loading: true };
		E.tabs.push(tab);
		activate(tab);
		api('read', { path: path }).then(function (r) {
			tab.loading = false;
			tab.info = r;
			tab.hash = r.hash;
			mountTab(tab, r.content);
			if (E.active === tab) activate(tab);
		}).catch(function (e) {
			tab.loading = false;
			tab.error = e.message;
			if (E.active === tab) activate(tab);
		});
	}
	function mountTab(tab, content) {
		var wrap = document.createElement('div');
		wrap.className = 'flxfm-cm';
		$('.flxfm-emain').appendChild(wrap);
		tab.el = wrap;
		var ext = (tab.name.split('.').length > 1 ? tab.name.split('.').pop() : '').toLowerCase();
		var mode = MODES[ext] || (tab.name === '.htaccess' ? 'text/plain' : 'text/plain');
		var ro = !C.writable || (tab.info && !tab.info.writable);
		if (CM) {
			tab.cm = CM(wrap, Object.assign({}, C.cm || {}, {
				value: content, mode: mode, lineNumbers: true, lineWrapping: false, indentUnit: 4, tabSize: 4, indentWithTabs: true,
				styleActiveLine: true, matchBrackets: true, autoCloseBrackets: true, readOnly: ro, viewportMargin: 40,
				extraKeys: { 'Ctrl-S': function () { save(tab); }, 'Cmd-S': function () { save(tab); }, 'Ctrl-F': function () { findBar(tab); }, 'Cmd-F': function () { findBar(tab); }, 'Tab': function (cm) { if (cm.somethingSelected()) cm.indentSelection('add'); else cm.replaceSelection(cm.getOption('indentWithTabs') ? '\t' : ' '.repeat(cm.getOption('indentUnit'))); } }
			}));
			tab.cm.on('change', function () { var d = tab.cm.getValue() !== tab.saved; if (d !== tab.dirty) { tab.dirty = d; renderTabs(); } });
			tab.cm.on('cursorActivity', function () { status(tab); });
		} else {
			var ta = document.createElement('textarea');
			ta.className = 'flxfm-ta'; ta.value = content; ta.readOnly = ro; ta.spellcheck = false;
			wrap.appendChild(ta);
			tab.cm = { getValue: function () { return ta.value; }, setValue: function (v) { ta.value = v; }, refresh: function () {}, focus: function () { ta.focus(); }, getSelection: function () { return ta.value.slice(ta.selectionStart, ta.selectionEnd); }, getCursor: function () { return { line: 0, ch: 0 }; }, lineCount: function () { return ta.value.split('\n').length; } };
			ta.addEventListener('input', function () { var d = ta.value !== tab.saved; if (d !== tab.dirty) { tab.dirty = d; renderTabs(); } });
			ta.addEventListener('keydown', function (e) { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(tab); } });
		}
		tab.saved = content;
	}
	function activate(tab) {
		E.active = tab;
		$$('.flxfm-cm', $('.flxfm-emain')).forEach(function (x) { x.hidden = x !== tab.el; });
		$('.flxfm-empty-editor').hidden = !!tab;
		var main = $('.flxfm-emain');
		$$('.flxfm-emsg, .flxfm-ebanner, .flxfm-find, .flxfm-aip', main).forEach(function (x) { x.remove(); });
		if (tab.loading) main.insertAdjacentHTML('afterbegin', '<div class="flxfm-emsg">' + esc(t('loading')) + '</div>');
		else if (tab.error) main.insertAdjacentHTML('afterbegin', '<div class="flxfm-emsg is-err">' + ic('info') + '<p>' + esc(tab.error) + '</p><button type="button" class="flxfm-btn" data-edl>' + ic('download') + esc(t('download')) + '</button></div>');
		else if (tab.info) {
			var notes = [];
			if (tab.info.sensitive) notes.push(t('sensitive_banner'));
			if (tab.info.core) notes.push(t('core_banner'));
			if (!tab.info.writable) notes.push(t('not_writable_banner'));
			if (notes.length) main.insertAdjacentHTML('afterbegin', '<div class="flxfm-ebanner">' + ic('shield') + '<span>' + notes.map(esc).join(' ') + '</span></div>');
		}
		renderTabs(); renderEtools(); renderTrail(); status(tab);
		if (tab.cm) setTimeout(function () { tab.cm.refresh(); tab.cm.focus(); }, 0);
		$$('.flxfm-tn-row').forEach(function (r) { r.classList.toggle('is-on', r.parentNode.getAttribute('data-tp') === tab.path); });
	}
	$('.flxfm-emain').addEventListener('click', function (e) { if (e.target.closest('[data-edl]') && E.active) download([E.active.path]); });
	function renderTabs() {
		var bar = $('.flxfm-tabs');
		if (!bar) return;
		bar.innerHTML = E.tabs.map(function (tb, i) {
			var ext = (tb.name.split('.').length > 1 ? tb.name.split('.').pop() : '').toLowerCase();
			return '<div class="flxfm-tab' + (tb === E.active ? ' is-on' : '') + (tb.dirty ? ' is-dirty' : '') + '" role="tab" aria-selected="' + (tb === E.active) + '" data-ti="' + i + '" title="' + esc(tb.path) + '">' +
				fileIcon({ type: 'file', name: tb.name, ext: ext, kind: 'code' }) + '<span>' + esc(tb.name) + '</span><button type="button" class="flxfm-tab-x" data-tx="' + i + '" aria-label="' + esc(t('close')) + '">' + (tb.dirty ? '<i class="flxfm-dot"></i>' : '') + ic('x', 'flxfm-i-sm') + '</button></div>';
		}).join('');
	}
	$('.flxfm-tabs').addEventListener('click', function (e) {
		var x = e.target.closest('[data-tx]');
		if (x) { closeTab(E.tabs[+x.getAttribute('data-tx')]); return; }
		var tb = e.target.closest('[data-ti]');
		if (tb) activate(E.tabs[+tb.getAttribute('data-ti')]);
	});
	$('.flxfm-tabs').addEventListener('auxclick', function (e) { var tb = e.target.closest('[data-ti]'); if (tb && e.button === 1) closeTab(E.tabs[+tb.getAttribute('data-ti')]); });
	function closeTab(tab, force) {
		if (!tab) return Promise.resolve();
		var ask = tab.dirty && !force ? D.confirm({ title: t('unsaved_title'), message: t('unsaved_msg', tab.name), ok: t('close_anyway'), danger: true }) : Promise.resolve(true);
		return ask.then(function (ok) {
			if (!ok) return;
			var i = E.tabs.indexOf(tab);
			E.tabs.splice(i, 1);
			if (tab.el) tab.el.remove();
			if (E.active === tab) {
				E.active = E.tabs[i] || E.tabs[i - 1] || null;
				if (E.active) activate(E.active);
				else { $('.flxfm-empty-editor').hidden = false; $$('.flxfm-emsg, .flxfm-ebanner, .flxfm-find, .flxfm-aip', $('.flxfm-emain')).forEach(function (x) { x.remove(); }); renderEtools(); renderTrail(); status(null); }
			}
			renderTabs();
		});
	}
	function closeTabs(paths) {
		E.tabs.slice().forEach(function (tb) { if (paths.some(function (p) { return tb.path === p || tb.path.indexOf(p + '/') === 0; })) closeTab(tb, true); });
	}
	function renderEtools() {
		var tb = E.active;
		var el = $('.flxfm-etools');
		if (!tb || tb.loading || tb.error) { el.innerHTML = ''; return; }
		var w = C.writable && tb.info && tb.info.writable;
		el.innerHTML = '<span class="flxfm-esize">' + esc(size(tb.info ? tb.info.size : null)) + '</span>' +
			'<button type="button" class="flxfm-btn flxfm-btn--ghost" data-e="find">' + ic('search') + '<span>' + esc(t('find')) + '</span></button>' +
			(C.ai && !tb.info.sensitive ? '<button type="button" class="flxfm-btn flxfm-btn--ghost flxfm-btn--ai" data-e="ai">' + ic('ai') + '<span>AI</span>' + ic('down', 'flxfm-i-sm') + '</button>' : '') +
			'<button type="button" class="flxfm-ib" data-e="versions" aria-label="' + esc(t('versions')) + '" title="' + esc(t('versions')) + '">' + ic('history') + '</button>' +
			'<button type="button" class="flxfm-ib" data-e="download" aria-label="' + esc(t('download')) + '" title="' + esc(t('download')) + '">' + ic('download') + '</button>' +
			'<button type="button" class="flxfm-ib" data-e="copy" aria-label="' + esc(t('copy_content')) + '" title="' + esc(t('copy_content')) + '">' + ic('copy') + '</button>' +
			(w ? '<button type="button" class="flxfm-btn flxfm-btn--pri" data-e="save"' + (tb.dirty ? '' : ' disabled') + '>' + ic('save') + '<span>' + esc(t('save')) + '</span></button>' : '');
	}
	$('.flxfm-etools').addEventListener('click', function (e) {
		var b = e.target.closest('[data-e]');
		var tb = E.active;
		if (!b || !tb) return;
		var a = b.getAttribute('data-e');
		if (a === 'save') save(tb);
		else if (a === 'find') findBar(tb);
		else if (a === 'download') download([tb.path]);
		else if (a === 'copy') copyText(tb.cm.getValue());
		else if (a === 'versions') versionsDialog({ path: tb.path, name: tb.name }, tb);
		else if (a === 'ai') {
			var r = b.getBoundingClientRect();
			menu([
				{ icon: 'info', label: tb.cm.getSelection && tb.cm.getSelection() ? t('ai_explain_sel') : t('ai_explain'), run: function () { aiExplain(tb); } },
				C.writable && tb.info.writable ? { icon: 'pencil', label: t('ai_edit'), run: function () { aiEdit(tb); } } : null
			], r.right, r.bottom + 6, true);
		}
	});
	function status(tb) {
		var el = $('.flxfm-estatus');
		if (!el) { el = h('<div class="flxfm-estatus"></div>'); $('.flxfm-editor').appendChild(el); }
		if (!tb || !tb.cm || !tb.cm.getCursor) { el.innerHTML = ''; return; }
		var c = tb.cm.getCursor();
		var ext = (tb.name.split('.').length > 1 ? tb.name.split('.').pop() : '').toUpperCase();
		el.innerHTML = '<span>' + esc(t('line_col', c.line + 1).replace('%2$s', c.ch + 1)) + '</span><span>' + esc(ext || t('kind_text')) + '</span><span>' + esc(t('lines_n', tb.cm.lineCount())) + '</span><span class="' + (tb.dirty ? 'is-dirty' : 'is-ok') + '">' + esc(tb.dirty ? t('unsaved') : t('saved_state')) + '</span>';
	}
	function save(tab) {
		if (!tab || tab.saving || !C.writable) return;
		var go2 = function () {
			tab.saving = true;
			var content = tab.cm.getValue();
			return api('write', { path: tab.path, content: content, hash: tab.hash }).then(function (r) {
				tab.hash = r.hash; tab.saved = content; tab.dirty = false; tab.info = r; tab.confirmed = true;
				toast(t('saved'));
				renderTabs(); renderEtools(); status(tab);
			}).catch(function (e) {
				if (e.code === 'flxfm_conflict') {
					return D.confirm({ title: t('conflict_title'), message: e.message, ok: t('overwrite'), danger: true }).then(function (ok) { if (ok) { tab.hash = ''; tab.saving = false; return save(tab); } });
				}
				fail(e);
			}).then(function () { tab.saving = false; });
		};
		if (!tab.confirmed && tab.info && (tab.info.sensitive || tab.info.core || /\.htaccess$|\.user\.ini$/.test(tab.path))) {
			D.confirm({ title: t('save'), message: tab.info.core ? t('core_save') : t('sensitive_save'), ok: t('save'), danger: true }).then(function (ok) { if (ok) { tab.confirmed = true; go2(); } });
			return;
		}
		go2();
	}
	function reloadTab(tab, force) {
		return api('read', { path: tab.path }).then(function (r) {
			tab.info = r; tab.hash = r.hash; tab.saved = r.content;
			if (force || !tab.dirty) { tab.cm.setValue(r.content); tab.dirty = false; }
			renderTabs(); renderEtools(); status(tab);
		}).catch(fail);
	}
	function editorKeys(e) {
		if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (E.active) save(E.active); }
		if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'f' && !e.target.closest('.CodeMirror')) { e.preventDefault(); if (E.active) findBar(E.active); }
	}
	window.addEventListener('beforeunload', function (e) { if (E.tabs.some(function (x) { return x.dirty; })) { e.preventDefault(); e.returnValue = ''; } });

	/* Bul / değiştir. */
	function findBar(tab) {
		var main = $('.flxfm-emain');
		var bar = $('.flxfm-find', main);
		if (!bar) {
			bar = h('<div class="flxfm-find"><input type="text" class="flxfm-f-q" placeholder="' + esc(t('find')) + '"><span class="flxfm-f-n"></span><button type="button" class="flxfm-ib" data-f="prev" aria-label="' + esc(t('previous')) + '">' + ic('up') + '</button><button type="button" class="flxfm-ib" data-f="next" aria-label="' + esc(t('next')) + '">' + ic('down') + '</button>' +
				(C.writable ? '<input type="text" class="flxfm-f-r" placeholder="' + esc(t('replace_with')) + '"><button type="button" class="flxfm-btn" data-f="rep">' + esc(t('replace')) + '</button><button type="button" class="flxfm-btn" data-f="all">' + esc(t('replace_all')) + '</button>' : '') +
				'<button type="button" class="flxfm-ib" data-f="close" aria-label="' + esc(t('close')) + '">' + ic('x') + '</button></div>');
			main.insertBefore(bar, main.firstChild);
			var q = bar.querySelector('.flxfm-f-q');
			var hits = [], cur = -1;
			var run = function () {
				var tb = E.active; if (!tb || !tb.cm.posFromIndex) return;
				var txt = tb.cm.getValue(), s = q.value; hits = [];
				if (s) { var low = txt.toLowerCase(), ls = s.toLowerCase(), i = low.indexOf(ls); while (i >= 0 && hits.length < 5000) { hits.push(i); i = low.indexOf(ls, i + ls.length); } }
				bar.querySelector('.flxfm-f-n').textContent = s ? (hits.length ? (Math.max(0, cur) + 1) + '/' + hits.length : t('no_results')) : '';
			};
			var jump = function (d) {
				var tb = E.active; run(); if (!hits.length) return;
				var at = tb.cm.indexFromPos(tb.cm.getCursor('to'));
				if (d > 0) { cur = hits.findIndex(function (x) { return x >= at; }); if (cur < 0) cur = 0; }
				else { var from = tb.cm.indexFromPos(tb.cm.getCursor('from')); cur = -1; for (var i = hits.length - 1; i >= 0; i--) if (hits[i] < from) { cur = i; break; } if (cur < 0) cur = hits.length - 1; }
				tb.cm.setSelection(tb.cm.posFromIndex(hits[cur]), tb.cm.posFromIndex(hits[cur] + q.value.length));
				tb.cm.scrollIntoView(null, 120);
				bar.querySelector('.flxfm-f-n').textContent = (cur + 1) + '/' + hits.length;
			};
			q.addEventListener('input', function () { cur = -1; run(); });
			q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); jump(e.shiftKey ? -1 : 1); } if (e.key === 'Escape') { bar.remove(); E.active && E.active.cm.focus(); } });
			bar.addEventListener('click', function (e) {
				var b = e.target.closest('[data-f]'); if (!b) return;
				var f = b.getAttribute('data-f'), tb = E.active;
				if (f === 'close') { bar.remove(); tb && tb.cm.focus(); }
				if (f === 'next') jump(1);
				if (f === 'prev') jump(-1);
				if (f === 'rep' && tb && q.value) { if (tb.cm.getSelection().toLowerCase() === q.value.toLowerCase()) tb.cm.replaceSelection(bar.querySelector('.flxfm-f-r').value); jump(1); }
				if (f === 'all' && tb && q.value) {
					var re = new RegExp(q.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
					var n = (tb.cm.getValue().match(re) || []).length;
					if (n) { var c = tb.cm.getCursor(); tb.cm.setValue(tb.cm.getValue().replace(re, function () { return bar.querySelector('.flxfm-f-r').value; })); tb.cm.setCursor(c); toast(t('replaced_n', n)); }
					run();
				}
			});
		}
		var sel = tab.cm.getSelection ? tab.cm.getSelection() : '';
		var qi = bar.querySelector('.flxfm-f-q');
		if (sel && sel.indexOf('\n') < 0) qi.value = sel;
		qi.focus(); qi.select();
		qi.dispatchEvent(new Event('input'));
	}

	/* AI: açıkla / düzenle. */
	function mdLite(s) {
		var out = esc(s);
		out = out.replace(/```[a-z0-9]*\n([\s\S]*?)```/gi, function (m, c) { return '<pre><code>' + c + '</code></pre>'; });
		out = out.replace(/`([^`\n]+)`/g, '<code>$1</code>');
		out = out.replace(/\*\*([^*\n]+)\*\*/g, '<b>$1</b>');
		out = out.replace(/^#{1,4} (.+)$/gm, '<h4>$1</h4>');
		out = out.replace(/^(?:[-*] .+(?:\n|$))+/gm, function (m) { return '<ul>' + m.trim().split('\n').map(function (l) { return '<li>' + l.replace(/^[-*] /, '') + '</li>'; }).join('') + '</ul>'; });
		out = out.replace(/\n{2,}/g, '<br><br>').replace(/([^>])\n([^<])/g, '$1<br>$2');
		return out;
	}
	/*
	 * AI paneli: modal değil, araç çubuğunun hemen altında, dosyanın üstünde açılır (dosya görünür kalır).
	 * Sekme değişince ya da X ile kapanır.
	 */
	function aiPanel(tab, title) {
		var main = $('.flxfm-emain');
		$$('.flxfm-aip', main).forEach(function (x) { x.parentNode.removeChild(x); });
		var el = h('<section class="flxfm-aip" aria-label="' + esc(title) + '"><header class="flxfm-aip-head">' + ic('ai') + '<b>' + esc(title) + '</b><button type="button" class="flxfm-ib" data-aip-x aria-label="' + esc(t('close')) + '">' + ic('x') + '</button></header><div class="flxfm-aip-body"></div><footer class="flxfm-aip-foot" hidden></footer></section>');
		main.insertBefore(el, tab.el && tab.el.parentNode === main ? tab.el : null);
		var p = { el: el, body: el.querySelector('.flxfm-aip-body'), foot: el.querySelector('.flxfm-aip-foot'), close: function () { if (el.parentNode) el.parentNode.removeChild(el); if (tab.cm && tab.cm.refresh) tab.cm.refresh(); } };
		el.querySelector('[data-aip-x]').addEventListener('click', p.close);
		el.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); p.close(); if (tab.cm) tab.cm.focus(); } });
		if (tab.cm && tab.cm.refresh) setTimeout(function () { tab.cm.refresh(); }, 0);
		return p;
	}
	function aiExplain(tab) {
		var sel = tab.cm.getSelection ? tab.cm.getSelection() : '';
		var p = aiPanel(tab, t('ai_explain') + ' · ' + tab.name);
		p.body.innerHTML = '<div class="flxfm-ai-wait">' + ic('ai') + esc(t('ai_thinking')) + '</div>';
		api('ai_explain', { path: tab.path, content: tab.cm.getValue(), selection: sel }).then(function (r) {
			p.body.innerHTML = '<div class="flxfm-md">' + mdLite(r.answer) + '</div>';
		}).catch(function (e) { p.body.innerHTML = '<p class="flxfm-err">' + esc(e.message) + '</p>'; });
	}
	function aiEdit(tab) {
		var p = aiPanel(tab, t('ai_edit') + ' · ' + tab.name);
		p.body.innerHTML = '<div class="flxfm-aip-ask"><textarea rows="2" class="flxfm-ai-q" placeholder="' + esc(t('ai_edit_ph')) + '" aria-label="' + esc(t('ai_edit_q')) + '"></textarea>' +
			'<button type="button" class="flxfm-btn flxfm-btn--ai" data-go>' + ic('ai') + esc(t('ai_suggest')) + '</button></div>' +
			'<p class="flxfm-muted flxfm-aip-hint">' + esc(t('ai_edit_hint')) + '</p><div class="flxfm-ai-out"></div>';
		p.foot.innerHTML = '<button type="button" class="flxfm-btn" data-aip-cancel>' + esc(t('cancel')) + '</button><button type="button" class="flxfm-btn flxfm-btn--pri" data-apply>' + ic('check') + esc(t('ai_apply')) + '</button>';
		var q = p.el.querySelector('.flxfm-ai-q'), out = p.el.querySelector('.flxfm-ai-out'), go = p.el.querySelector('[data-go]'), proposal = null;
		q.focus();
		q.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) go.click(); });
		go.addEventListener('click', function () {
			if (!q.value.trim()) { q.focus(); return; }
			go.disabled = true;
			p.foot.hidden = true;
			out.innerHTML = '<div class="flxfm-ai-wait">' + ic('ai') + esc(t('ai_thinking')) + '</div>';
			var cur = tab.cm.getValue();
			api('ai_edit', { path: tab.path, content: cur, prompt: q.value }).then(function (r) {
				proposal = r.content;
				out.innerHTML = (r.note ? '<p class="flxfm-ai-note">' + ic('ai') + esc(r.note) + '</p>' : '') + (proposal != null ? diffHtml(cur, proposal) : '');
				p.foot.hidden = proposal == null || proposal === cur;
			}).catch(function (e) { out.innerHTML = '<p class="flxfm-err">' + esc(e.message) + '</p>'; }).then(function () { go.disabled = false; });
		});
		p.foot.querySelector('[data-aip-cancel]').addEventListener('click', p.close);
		p.foot.querySelector('[data-apply]').addEventListener('click', function () {
			if (proposal == null) return;
			var c = tab.cm.getCursor ? tab.cm.getCursor() : null;
			tab.cm.setValue(proposal);
			if (c && tab.cm.setCursor) tab.cm.setCursor(c);
			p.close();
			toast(t('ai_applied'), 'info');
		});
	}

	/* --------------------------------------------------------------- başlat */
	var hash = location.hash || '';
	var start = '';
	var mm = hash.match(/^#path=(.*)$/);
	if (mm) { try { start = decodeURIComponent(mm[1]); } catch (e) { start = ''; } }
	setMode('browse');
	load(start, { fallback: true }).then(function () {
		if (hash === '#settings') openSettings();
		if (hash === '#trash') setMode('trash');
		if (hash === '#activity') setMode('log');
	});
	if (C.open) openEditor(C.open);
})();
