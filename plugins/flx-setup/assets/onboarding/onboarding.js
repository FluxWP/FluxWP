/* FluxWP Panel kurulum sihirbazı. Veri: window.FLXK_OB (includes/onboarding.php). */
(function () {
	'use strict';
	var D = window.FLXK_OB || {};
	var T = D.i18n || {};
	var root = document.getElementById('flxk-ob');
	if (!root) { return; }

	var S = {
		step: 0,
		theme: !!D.theme,
		sel: {},
		preset: '',
		style: '',
		name: D.siteName || '',
		tagline: D.tagline || '',
		logo: null,
		tab: 0,
		pages: { about: 1, blog: 1, contact: 1 }
	};
	var pageDefs = D.pageDefs || [];
	(D.plugins || []).forEach(function (p) { if (p.rec || p.active) { S.sel[p.slug] = 1; } });
	var presets = D.presets || [];
	S.preset = presets.length ? presets[0].id : 'blank';
	var styles = D.styles || [];
	// Adımlar: 0 tanıtım, 1 başlangıç (tema + mağaza/dernek), her eklenti kategorisi bir ekran, görünüm, kurulum, bitti.
	var GROUPS = (D.groups || []).filter(function (g) { return (D.plugins || []).some(function (p) { return p.group === g.key && !p.core; }); });
	var I_G = 2;
	var I_APP = I_G + GROUPS.length;
	var I_INST = I_APP + 1;
	var LAST = I_INST + 1;
	function useTheme() { return !!(D.theme && S.theme); }
	S.style = styles.length ? styles[0].key : '';

	/* ---------- helpers ---------- */
	function $(s, el) { return (el || document).querySelector(s); }
	function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }
	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmt(s) { var a = arguments; var i = 0; return String(s).replace(/%(\d\$)?[sd]/g, function (m, n) { i++; return a[n ? parseInt(n, 10) : i]; }); }
	function count(o) { return Object.keys(o).length; }
	function post(data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
		});
		return fetch(D.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
			return r.json().catch(function () { throw new Error(T.netErr); });
		}).then(function (j) {
			if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || T.netErr); }
			return j.data;
		});
	}
	var ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
	var OUT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>';
	var TICK = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
	var plugins = D.plugins || [];
	var bySlug = {};
	plugins.forEach(function (p) { bySlug[p.slug] = p; });
	function icon(slug) {
		if (slug === 'fluxwp') { return D.themeIcon || D.setupIcon; }
		if (slug === 'flx-setup') { return D.setupIcon; }
		return (bySlug[slug] && bySlug[slug].icon) || D.setupIcon;
	}

	// Sunucudan gelen görsel açılmazsa: önizleme yerine ikonlu yer tutucu, ikon yerine FluxWP Panel logosu.
	root.addEventListener('error', function (e) {
		var img = e.target;
		if (!img || img.tagName !== 'IMG' || img.getAttribute('data-fb')) { return; }
		img.setAttribute('data-fb', '1');
		var shot = img.parentNode && img.parentNode.classList && img.parentNode.classList.contains('shot') ? img.parentNode : null;
		var ic = shot && shot.parentNode ? shot.parentNode.querySelector('.body .ic') : null;
		if (shot) {
			shot.classList.remove('art');
			shot.innerHTML = '<div class="ph-shot"><img src="' + esc((ic && ic.getAttribute('src')) || D.setupIcon) + '" alt=""></div>';
		} else if (D.setupIcon && img.getAttribute('src') !== D.setupIcon) {
			img.src = D.setupIcon;
		}
	}, true);

	/* ---------- skeleton ---------- */
	root.innerHTML =
		'<div class="wrap">' +
		'<header class="top"><div class="top-in"><img class="logo" src="' + esc(root.getAttribute('data-logo')) + '" alt="FluxWP">' +
		'<div class="prog" id="prog"><span id="progT"></span><div class="track"><i id="progI"></i></div></div>' +
		'<div class="right"><button class="link" id="skipBtn">' + esc(T.skip) + '</button></div></div></header>' +
		'<main>' +
		(function () { var h = ''; for (var i = 0; i <= LAST; i++) { h += '<section class="step" id="step' + i + '"></section>'; } return h; }()) +
		'</main>' +
		'<div class="bar" id="bar1"><div class="bar-in"><div class="bar-info"><div class="stack" id="stack"></div><span class="t" id="barText"></span></div>' +
		'<button class="btn btn-ghost" id="backBtn">' + esc(T.back) + '</button>' +
		'<button class="btn btn-primary" id="nextBtn"><span id="nextLbl"></span>' + ARROW + '</button></div></div>' +
		'</div>' +
		'<div class="modal" id="skipModal" role="dialog" aria-modal="true" aria-labelledby="skipT"><div class="box"><h3 id="skipT">' + esc(T.skipT) + '</h3><p>' + esc(T.skipP) + '</p>' +
		'<div class="acts"><button class="btn btn-ghost" id="skipNo">' + esc(T.stay) + '</button><button class="btn btn-primary" id="skipYes">' + esc(T.skipYes) + '</button></div></div></div>';

	/* ---------- step 0: intro ---------- */
	function legalLink(k, label) {
		var u = (D.legal || {})[k];
		return u ? '<a href="' + esc(u) + '" target="_blank" rel="noopener">' + esc(label) + '</a>' : esc(label);
	}
	var mosaicSlugs = plugins.slice(0, 15).map(function (p) { return p.slug; });
	if (D.theme) { mosaicSlugs.push('fluxwp'); }
	$('#step0').innerHTML =
		'<div class="intro"><div>' +
		'<p class="kicker">FluxWP Panel</p><h1>' + esc(T.i_h) + '</h1><p class="lead">' + esc(T.i_p) + '</p>' +
		(D.offline ? '<p class="notice">' + esc(T.offline) + (D.connError ? ' (' + esc(D.connError) + ')' : '') + '</p>' : '') +
		'<ul class="facts">' +
		(plugins.length ? '<li><b>' + esc(fmt(T.f1b, plugins.length)) + '</b><span>' + esc(T.f1s) + '</span></li>' : '') +
		'<li><b>' + esc(T.f2b) + '</b><span>' + esc(T.f2s) + '</span></li>' +
		'<li><b>' + esc(T.f3b) + '</b><span>' + esc(T.f3s) + '</span></li></ul>' +
		'<div class="consent">' +
		'<label class="chk"><input type="checkbox" id="cTerms"' + (D.terms ? ' checked' : '') + '><span>' + fmt(esc(T.cTerms), legalLink('terms', T.cTermsL), legalLink('privacy', T.cPrivL), legalLink('kvkk', T.cKvkkL)) + '</span></label>' +
		'<label class="chk"><input type="checkbox" id="cUsage"' + (D.usage ? ' checked' : '') + '><span>' + esc(T.cUsage) + '<small>' + esc(T.cUsageS) + '</small></span></label>' +
		'</div>' +
		'<div class="acts"><button class="btn btn-primary" id="startBtn"' + (D.terms ? '' : ' disabled') + ' title="' + esc(D.terms ? '' : T.cNeed) + '">' + esc(T.start) + ARROW + '</button>' +
		'<button class="btn btn-ghost" id="skip2">' + esc(T.skipSelf) + '</button><small>' + esc(T.time) + '</small></div>' +
		'</div><div class="mosaic" aria-hidden="true">' +
		mosaicSlugs.map(function (s) { return '<div><img src="' + esc(icon(s)) + '" alt=""></div>'; }).join('') +
		'</div></div>';

	/* ---------- step 1: base (theme + core packages) ---------- */
	var core = (D.core || []).filter(function (c) { return c.slugs.some(function (sl) { return bySlug[sl]; }); });
	var others = plugins.filter(function (p) { return !p.core; });
	function hasBase() { return core.length > 0; }
	function coreOn(c) { return c.slugs.every(function (sl) { return !bySlug[sl] || S.sel[sl]; }); }
	// Site türü (Mağaza, Dernek) tema gibi: aynı anda yalnız biri seçili (önce kurulu olan, yoksa ilk seçili olan). Solo kart (Yönetim teması) bağımsız.
	function setCore(c, on) { c.slugs.forEach(function (sl) { if (bySlug[sl]) { if (on) { S.sel[sl] = 1; } else { delete S.sel[sl]; } } }); }
	var kinds = core.filter(function (c) { return !c.solo; });
	var core0 = kinds.filter(function (c) { return c.slugs.some(function (sl) { return bySlug[sl] && bySlug[sl].active; }); })[0] ||
		kinds.filter(function (c) { return c.slugs.some(function (sl) { return S.sel[sl]; }); })[0];
	kinds.forEach(function (c) { setCore(c, c === core0); });
	function bigCard(k, on, shot, ic, name, desc, badge) {
		return '<button type="button" class="big' + (on ? ' on' : '') + '" data-b="' + esc(k) + '" aria-pressed="' + on + '">' +
			'<div class="shot' + (/\.svg(\?|$)/.test(shot || '') ? ' art' : '') + '">' + (shot ? '<img src="' + esc(shot) + '" alt="">' : '<div class="ph-shot"><img src="' + esc(ic) + '" alt=""></div>') + '</div>' +
			(badge ? '<span class="badge">' + esc(badge) + '</span>' : '') +
			'<span class="tick">' + TICK + '</span>' +
			'<div class="body"><img class="ic" src="' + esc(ic) + '" alt=""><div><h3>' + esc(name) + '</h3><p>' + esc(desc) + '</p></div></div></button>';
	}
	function renderBase() {
		var cards = core.map(function (c) {
				var main = bySlug[c.slugs.filter(function (sl) { return bySlug[sl]; })[0]];
				var active = c.slugs.every(function (sl) { return !bySlug[sl] || bySlug[sl].active; });
				return bigCard(c.key, coreOn(c), c.art || main.thumb, icon(main.slug), c.name, c.desc, active ? T.installedB : T.recB);
			}).join('');
		$('#step1').innerHTML =
			'<div class="center-head"><p class="kicker">' + esc(T.coreK) + '</p><h1>' + esc(T.hBase) + '</h1><p class="lead">' + esc(T.leadBase) + '</p></div>' +
			'<div class="bigs" style="--n:' + core.length + '">' + cards + '</div>' +
			'<div class="noneRow"><button type="button" class="link quiet" id="noneBtn">' + esc(T.noneBtn) + '</button></div>';
		$$('.big', $('#step1')).forEach(function (c) {
			c.addEventListener('click', function () {
				var k = c.getAttribute('data-b');
				var on;
				var c0 = core.filter(function (c) { return c.key === k; })[0];
				on = !coreOn(c0);
				if (c0.solo) { setCore(c0, on); } else { kinds.forEach(function (x) { setCore(x, x === c0 && on); }); }
				$$('.big', $('#step1')).forEach(function (b) {
					var bk = b.getAttribute('data-b');
					var bo = coreOn(core.filter(function (x) { return x.key === bk; })[0]);
					b.classList.toggle('on', bo);
					b.setAttribute('aria-pressed', bo);
				});
				renderAppearance();
				bar();
			});
		});
		$('#noneBtn').addEventListener('click', function () {
			core.forEach(function (c) { c.slugs.forEach(function (sl) { delete S.sel[sl]; }); });
			renderBase();
			renderAppearance();
			go(I_G);
		});
	}

	/* ---------- eklentiler: her kategori bir ekran ---------- */
	function card(p) {
		var on = !!S.sel[p.slug];
		return '<button type="button" class="card' + (on ? ' on' : '') + '" data-p="' + esc(p.slug) + '" aria-pressed="' + on + '">' +
			'<div class="shot">' + (p.thumb ? '<img loading="lazy" src="' + esc(p.thumb) + '" alt="">' : '<div class="ph-shot"><img src="' + esc(icon(p.slug)) + '" alt=""></div>') + '</div>' +
			(p.active ? '<span class="badge">' + esc(T.installedB) + '</span>' : '') +
			'<span class="tick">' + TICK + '</span>' +
			'<div class="body"><img class="ic" src="' + esc(icon(p.slug)) + '" alt=""><div><h3>' + esc(p.name) + '</h3><p>' + esc(p.desc) + '</p></div></div></button>';
	}
	function groupItems(g) { return others.filter(function (p) { return p.group === g.key; }); }
	function renderGroup(gi) {
		var g = GROUPS[gi];
		var items = groupItems(g);
		var el = $('#step' + (I_G + gi));
		var allOn = items.every(function (p) { return S.sel[p.slug]; });
		el.innerHTML =
			'<div class="head-row"><div><p class="kicker">' + esc(fmt(T.groupK, gi + 1, GROUPS.length)) + '</p><h1>' + esc(g.name) + '</h1><p class="lead">' + esc(g.lead) + '</p></div>' +
			'<button class="link sel-all">' + esc(allOn ? T.selNone : T.selAll) + '</button></div>' +
			'<div class="grid cat" style="--cols:' + Math.min(Math.max(items.length, 2), 4) + '">' + items.map(card).join('') + '</div>';
		$$('button.card', el).forEach(function (c) {
			c.addEventListener('click', function () {
				var k = c.getAttribute('data-p');
				if (S.sel[k]) { delete S.sel[k]; } else { S.sel[k] = 1; }
				c.classList.toggle('on', !!S.sel[k]);
				c.setAttribute('aria-pressed', !!S.sel[k]);
				$('.sel-all', el).textContent = items.every(function (p) { return S.sel[p.slug]; }) ? T.selNone : T.selAll;
				bar();
			});
		});
		$('.sel-all', el).addEventListener('click', function () {
			var on = items.every(function (p) { return S.sel[p.slug]; });
			items.forEach(function (p) { if (on) { delete S.sel[p.slug]; } else { S.sel[p.slug] = 1; } });
			renderGroup(gi);
			bar();
		});
	}
	function renderGrid() {
		GROUPS.forEach(function (g, gi) { renderGroup(gi); });
	}

	/* ---------- appearance: solda sekmeler, sağda sabit önizleme ---------- */
	function curStyle() {
		for (var i = 0; i < styles.length; i++) { if (styles[i].key === S.style) { return styles[i]; } }
		return { p: '#1F2937', pd: '#111827', l: '#3B82F6', m: '#F2EEE7', ms: '#F8F5F0', sl: '#E6E0D6', ink: '#111827', name: '' };
	}
	function shownPresets() {
		return presets.filter(function (p) { return useTheme() || !p.theme; });
	}
	function curPreset() {
		var list = shownPresets();
		for (var i = 0; i < list.length; i++) { if (list[i].id === S.preset) { return list[i]; } }
		return list.length ? list[0] : { id: 'blank', name: '' };
	}
	function tabs() {
		var t = [{ k: 'id', n: T.tabId }, { k: 'preset', n: T.tabPreset }];
		if (styles.length && useTheme()) { t.push({ k: 'color', n: T.tabColor }); }
		if (pageDefs.length) { t.push({ k: 'pages', n: T.tabPages }); }
		return t;
	}
	function hasSel(slug) { return !!S.sel[slug] || !!(bySlug[slug] && bySlug[slug].active); }
	/* Renk kartındaki küçük site: başlık, kahraman alanı, kartlar. */
	function mini(st, blank) {
		return '<div class="mini" style="--p:' + esc(st.p) + ';--pd:' + esc(st.pd) + ';--l:' + esc(st.l) + ';--m:' + esc(st.m) + ';--ms:' + esc(st.ms) + ';--sl:' + esc(st.sl) + ';--ink:' + esc(st.ink || '#111827') + '">' +
			'<div class="mh"><i class="lg"></i><i class="t"></i><i class="n"></i><i class="n"></i><i class="c"></i></div>' +
			(blank ? '<div class="mx blank"><i></i><i></i></div>' :
			'<div class="mx"><i class="hh"></i><i class="hp"></i><i class="hb"></i></div><div class="mc"><i></i><i></i><i></i></div>') + '</div>';
	}
	function paneId() {
		return '<div class="fld"><label for="siteName">' + esc(T.siteName) + '</label><input type="text" id="siteName" value="' + esc(S.name) + '"><small>' + esc(T.nameHint) + '</small></div>' +
			'<div class="fld"><label for="tagline">' + esc(T.tagline) + '</label><input type="text" id="tagline" value="' + esc(S.tagline) + '"><small>' + esc(T.taglineHint) + '</small></div>' +
			(D.canUpload ? '<div class="fld"><label>' + esc(T.logo) + '</label><div class="drop' + (S.logo ? ' has' : '') + '" id="drop">' +
				'<div class="lgbox">' + (S.logo ? '<img src="' + esc(S.logo.url) + '" alt="">' : '<span>' + esc((S.name.trim().charAt(0) || 'S').toLocaleUpperCase()) + '</span>') + '</div>' +
				'<div class="dt"><b id="dropT">' + esc(S.logo ? T.logoChange : T.logoBtn) + '</b><small>' + esc(T.logoHint) + '</small></div>' +
				(S.logo ? '<button type="button" class="link" id="logoX">' + esc(T.logoRemove) + '</button>' : '') +
				'<input type="file" id="logoF" accept="image/png,image/jpeg,image/webp,image/gif" hidden></div></div>' : '');
	}
	function panePreset() {
		var st = curStyle();
		return '<p class="hint">' + esc(T.presetHint) + '</p><div class="presets">' + shownPresets().map(function (p) {
			return '<button type="button" class="preset' + (S.preset === p.id ? ' on' : '') + '" data-k="' + esc(p.id) + '" aria-pressed="' + (S.preset === p.id) + '">' +
				mini(st, p.id === 'blank') + '<b>' + esc(p.name) + '</b>' + (p.desc ? '<small>' + esc(p.desc) + '</small>' : '') + '</button>';
		}).join('') + '</div>';
	}
	function paneColor() {
		return '<p class="hint">' + esc(T.colorHint) + '</p><div class="palettes">' + styles.map(function (s) {
			return '<button type="button" class="pal' + (S.style === s.key ? ' on' : '') + '" data-k="' + esc(s.key) + '" aria-pressed="' + (S.style === s.key) + '">' +
				mini(s, false) + '<span class="pn"><b>' + esc(s.name) + '</b><span class="dots"><i style="background:' + esc(s.p) + '"></i><i style="background:' + esc(s.l) + '"></i><i style="background:' + esc(s.m) + '"></i></span></span>' +
				(s.desc ? '<small>' + esc(s.desc) + '</small>' : '') + '</button>';
		}).join('') + '</div>';
	}
	function panePages() {
		return '<p class="hint">' + esc(T.pagesHint) + '</p><div class="pgs">' + pageDefs.map(function (p) {
			var on = !!S.pages[p.key];
			return '<button type="button" class="pg' + (on ? ' on' : '') + '" data-k="' + esc(p.key) + '" aria-pressed="' + on + '"><span class="sw2"><i></i></span><b>' + esc(p.title) + '</b></button>';
		}).join('') + '</div>';
	}
	function renderAppearance() {
		var el = $('#step' + I_APP);
		var tl = tabs();
		if (S.tab >= tl.length) { S.tab = tl.length - 1; }
		S.preset = curPreset().id;
		var k = tl[S.tab].k;
		el.innerHTML =
			'<div class="app"><div class="app-l">' +
			'<p class="kicker">' + esc(T.k2) + '</p><h1>' + esc(T.h2) + '</h1><p class="lead">' + esc(T.lead2) + '</p>' +
			'<div class="tabs" role="tablist">' + tl.map(function (t, i) {
				return '<button type="button" role="tab" class="tab' + (i === S.tab ? ' on' : '') + (i < S.tab ? ' done' : '') + '" data-i="' + i + '" aria-selected="' + (i === S.tab) + '"><em>' + (i + 1) + '</em>' + esc(t.n) + '</button>';
			}).join('') + '</div>' +
			'<div class="pane" role="tabpanel">' + (k === 'id' ? paneId() : k === 'preset' ? panePreset() : k === 'color' ? paneColor() : panePages()) + '</div>' +
			'</div><div class="preview"><div class="browser"><div class="chrome"><i></i><i></i><i></i><span>' + esc(D.host) + '</span></div><div class="site" id="site"></div></div></div></div>';
		$$('.tab', el).forEach(function (b) {
			b.addEventListener('click', function () { S.tab = parseInt(b.getAttribute('data-i'), 10); renderAppearance(); bar(); });
		});
		$$('.preset', el).forEach(function (b) {
			b.addEventListener('click', function () { S.preset = b.getAttribute('data-k'); renderAppearance(); bar(); });
		});
		$$('.pal', el).forEach(function (b) {
			b.addEventListener('click', function () { S.style = b.getAttribute('data-k'); renderAppearance(); bar(); });
		});
		$$('.pg', el).forEach(function (b) {
			b.addEventListener('click', function () {
				var key = b.getAttribute('data-k');
				if (S.pages[key]) { delete S.pages[key]; } else { S.pages[key] = 1; }
				renderAppearance();
			});
		});
		if (k === 'id') { bindId(el); }
		site();
	}
	function bindId(el) {
		$('#siteName', el).addEventListener('input', function (e) {
			S.name = e.target.value;
			var s = $('.lgbox span', el);
			if (s) { s.textContent = (S.name.trim().charAt(0) || 'S').toLocaleUpperCase(); }
			site();
		});
		$('#tagline', el).addEventListener('input', function (e) { S.tagline = e.target.value; site(); });
		var drop = $('#drop', el);
		if (!drop) { return; }
		var f = $('#logoF', el);
		drop.addEventListener('click', function (e) { if (e.target.id !== 'logoX') { f.click(); } });
		drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('over'); });
		drop.addEventListener('dragleave', function () { drop.classList.remove('over'); });
		drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('over'); if (e.dataTransfer.files[0]) { upload(e.dataTransfer.files[0]); } });
		f.addEventListener('change', function () { if (f.files[0]) { upload(f.files[0]); } });
		var x = $('#logoX', el);
		if (x) { x.addEventListener('click', function () { S.logo = null; renderAppearance(); }); }
	}
	function upload(file) {
		var t = $('#dropT');
		if (t) { t.textContent = T.uploading; }
		$('#drop').classList.add('busy');
		var body = new FormData();
		body.append('action', 'flxk_onboarding_logo');
		body.append('nonce', D.nonce);
		body.append('logo', file);
		fetch(D.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || T.netErr); }
			S.logo = { id: j.data.id, url: j.data.url };
			renderAppearance();
		}).catch(function (e) {
			renderAppearance();
			var s = $('#drop .dt small');
			if (s) { s.textContent = e.message; s.classList.add('err'); }
		});
	}
	function pageTitle(k) { var d = pageDefs.filter(function (p) { return p.key === k; })[0]; return d ? d.title : ''; }
	/* Sağdaki önizleme: seçilen ad, logo, slogan, renk, sayfalar ve paketlere göre. */
	function site() {
		var el = $('#site');
		if (!el) { return; }
		var st = curStyle();
		[['--p', st.p], ['--pd', st.pd], ['--l', st.l], ['--m', st.m], ['--ms', st.ms], ['--sl', st.sl], ['--ink', st.ink || '#111827']].forEach(function (kv) { el.style.setProperty(kv[0], kv[1]); });
		var name = S.name || 'Site';
		var pr = curPreset();
		var blank = pr.id === 'blank';
		var donate = hasSel('flx-donate');
		var shop = hasSel('flx-commerce');
		var nav = [T.home].concat(pageDefs.filter(function (p) { return S.pages[p.key]; }).map(function (p) { return p.title; }));
		if (shop) { nav.splice(1, 0, T.shop); }
		var logo = S.logo ? '<img class="s-img" src="' + esc(S.logo.url) + '" alt="">' : '<div class="s-logo">' + esc((name.trim().charAt(0) || 'S').toLocaleUpperCase()) + '</div>';
		var cards = '';
		if (!blank) {
			cards = '<div class="s-cards">' + [72, 45, 88].map(function (pc, i) {
				if (donate) { return '<div class="s-card"><div class="ph"></div><b class="ln" style="width:' + (70 - i * 8) + '%"></b><div class="bar2"><i style="width:' + pc + '%"></i></div><small class="ln" style="width:40%"></small></div>'; }
				if (shop) { return '<div class="s-card"><div class="ph tall"></div><b class="ln" style="width:' + (78 - i * 10) + '%"></b><span class="price">' + ['₺249', '₺480', '₺129'][i] + '</span></div>'; }
				return '<div class="s-card"><div class="ph"></div><b class="ln" style="width:' + (72 - i * 9) + '%"></b><small class="ln" style="width:88%"></small><small class="ln" style="width:60%"></small></div>';
			}).join('') + '</div>';
		}
		var blog = S.pages.blog ? '<div class="s-sec"><h6>' + esc(T.latest) + '</h6><div class="s-blog">' + [0, 1, 2].map(function (i) {
			return '<div><i class="th"></i><span><b class="ln" style="width:' + (86 - i * 12) + '%"></b><small class="ln" style="width:94%"></small><small class="ln" style="width:' + (52 + i * 9) + '%"></small></span></div>';
		}).join('') + '</div></div>' : '';
		el.innerHTML =
			'<div class="s-head">' + logo + '<div class="s-name">' + esc(name) + '</div><div class="s-nav">' + nav.slice(0, 5).map(function (n) { return '<span>' + esc(n) + '</span>'; }).join('') + '</div>' +
			(donate ? '<span class="s-cta">' + esc(T.donate) + '</span>' : '') + '</div>' +
			'<div class="s-hero"><div><h5>' + esc(blank ? name : (S.tagline || pr.name || name)) + '</h5><p>' + esc(blank ? (S.tagline || '') : (pr.desc || S.tagline || '')) + '</p>' +
			(blank ? '' : '<span class="b">' + esc(donate ? T.donate : (shop ? T.shop : T.viewSite)) + '</span>' + (S.pages.about ? '<span class="b2">' + esc(pageTitle('about')) + '</span>' : '')) + '</div>' +
			(blank ? '' : '<i class="art"></i>') + '</div>' +
			cards + blog +
			'<div class="s-foot"><div><b>' + esc(name) + '</b></div><div class="fl">' +
			nav.slice(1, 5).map(function (n) { return '<span>' + esc(n) + '</span>'; }).join('') + '</div><small>© ' + new Date().getFullYear() + '</small></div>';
	}

	/* ---------- chrome ---------- */
	function bar() {
		var n = count(S.sel);
		var stack = (useTheme() ? ['fluxwp'] : []).concat(plugins.filter(function (p) { return S.sel[p.slug]; }).map(function (p) { return p.slug; })).slice(0, 8);
		$('#stack').innerHTML = stack.map(function (s) { return '<img src="' + esc(icon(s)) + '" alt="">'; }).join('');
		var sel = fmt(useTheme() ? T.selected : T.selectedNT, n);
		$('#barText').innerHTML = S.step < I_APP ? '<b>' + esc(sel) + '</b>' : '<b>' + esc(curPreset().name) + '</b>' + (useTheme() && S.style ? ' · ' + esc(curStyle().name) : '') + ' · ' + esc(sel);
		$('#nextLbl').textContent = S.step === I_APP && S.tab >= tabs().length - 1 ? T.install : T.next;
		$('#bar1').hidden = !(S.step >= 1 && S.step <= I_APP);
	}
	function chrome() {
		$$('section.step').forEach(function (s) {
			var i = parseInt(s.id.slice(4), 10);
			s.classList.toggle('on', i === S.step);
			s.classList.toggle('past', i < S.step);
		});
		$('#prog').style.visibility = S.step === 0 ? 'hidden' : '';
		$('#progT').textContent = fmt(T.step, Math.min(S.step, LAST), LAST);
		$('#progI').style.width = (S.step / LAST * 100) + '%';
		$('#skipBtn').style.visibility = (S.step >= 1 && S.step <= I_APP) ? '' : 'hidden';
	}
	function go(n) {
		if (n === 1 && !hasBase()) { n = S.step > 1 ? 0 : I_G; }
		S.step = n;
		chrome();
		bar();
		window.scrollTo(0, 0);
		if (n === I_INST) { install(); }
	}

	/* ---------- step 4: install ---------- */
	var log;
	/* Çark: son satır ortada, öncekiler yukarı doğru dönerek uzaklaşır. */
	function line(html) {
		var d = document.createElement('div');
		d.className = 'wl';
		d.innerHTML = html;
		log.appendChild(d);
		var items = log.children;
		while (items.length > 8) { log.removeChild(log.firstChild); }
		var k = items.length - 1;
		for (var i = 0; i < items.length; i++) {
			var off = i - k;
			items[i].style.transform = 'translateY(' + (off * 36) + 'px) rotateX(' + (-off * 11) + 'deg)';
			items[i].style.opacity = String(Math.max(0, 1 - Math.abs(off) * 0.22));
			items[i].classList.toggle('cur', off === 0);
		}
	}
	var msgWant = '';
	var msgTimer = 0;
	function setMsg(m) {
		var e = $('#msg');
		msgWant = m;
		if (msgTimer || e.textContent === m) { return; }
		e.classList.add('fade');
		msgTimer = setTimeout(function () { msgTimer = 0; e.textContent = msgWant; e.classList.remove('fade'); }, 300);
	}

	function install() {
		var errors = [];
		var stats = { pk: 0, pages: 0 };
		$('#step' + I_INST).innerHTML =
			'<div class="install" id="stage"><div>' +
			'<div class="mark" aria-hidden="true"><i></i><i></i><i></i><i></i></div>' +
			'<div class="msg" id="msg" aria-live="polite">' + esc(T.msgs[0]) + '</div><p class="sub" id="sub">' + esc(T.keep) + '</p>' +
			'<div class="meter"><span id="pctL"></span><b id="pctR"></b></div><div class="ptrack"><i id="pbar"></i></div><ul class="queue" id="queue"></ul></div>' +
			'<div class="wheel-wrap"><div class="wheel-card"><div class="wh"><span>flux-setup</span><span id="clock">00:00</span></div><div class="wheel" id="log"></div></div></div></div>';
		log = $('#log');
		var sec = 0;
		var clock = setInterval(function () { sec++; $('#clock').textContent = ('0' + (sec / 60 | 0)).slice(-2) + ':' + ('0' + sec % 60).slice(-2); }, 1000);
		window.onbeforeunload = function () { return T.keep; };

		var slugs = Object.keys(S.sel);
		if (useTheme()) { slugs.unshift('fluxwp'); }
		var pr = curPreset();
		if (pr.package) { slugs.push(pr.package); }
		line('<span class="y">' + esc(fmt(T.wQueued, slugs.length)) + '</span>');

		var planP = slugs.length ? post({ action: 'flxu_plan', nonce: D.flxuNonce, slugs: slugs, activate: 1 }).then(function (r) { return r.steps || []; }) : Promise.resolve([]);
		planP.catch(function (e) {
			line('<span class="r">✕</span> ' + esc(e.message));
			errors.push(e.message);
			return [];
		}).then(function (steps) {
			// Kuyruk: paket başına bir satır (kur + etkinleştir aynı satır), sonra tema/renk/ön ayar/ad.
			var rows = [];
			var rowOf = {};
			steps.forEach(function (s) {
				if (!(s.slug in rowOf)) {
					rowOf[s.slug] = rows.length;
					rows.push({ icon: icon(s.slug), name: s.slug === 'fluxwp' ? T.theme : s.name, sub: s.slug + (s.why ? ' · ' + s.why : ''), jobs: [] });
				}
				rows[rowOf[s.slug]].jobs.push({ type: 'flxu', kind: s.kind, slug: s.slug, pkg: s.kind === 'install' || s.kind === 'wporg' });
			});
			var extra = [];
			if (useTheme()) { extra.push({ icon: icon('fluxwp'), name: T.activate, sub: 'switch_theme fluxwp', jobs: [{ type: 'ob', kind: 'theme' }] }); }
			if (useTheme() && S.style) { extra.push({ icon: icon('fluxwp'), name: T.styleStep + ': ' + curStyle().name, sub: 'styles/' + S.style + '.json', jobs: [{ type: 'ob', kind: 'style', value: S.style }] }); }
			// Logo temadan sonra: custom_logo her temanın kendi ayarı.
			if (S.logo) { extra.push({ icon: S.logo.url, name: T.logoStep, sub: 'custom_logo', jobs: [{ type: 'ob', kind: 'logo', value: String(S.logo.id) }] }); }
			if (pr.id !== 'blank') { extra.push({ icon: D.setupIcon, name: T.presetStep + ': ' + pr.name, sub: 'flx-preset/1 · ' + pr.id, jobs: [{ type: 'ob', kind: 'preset', value: pr.id }] }); }
			var pk = pageDefs.filter(function (p) { return S.pages[p.key]; });
			if (pk.length) { extra.push({ icon: D.setupIcon, name: T.pagesStep, sub: pk.map(function (p) { return p.title; }).join(', '), jobs: [{ type: 'ob', kind: 'pages', value: pk.map(function (p) { return p.key; }).join(',') }] }); }
			extra.push({ icon: D.setupIcon, name: T.nameStep, sub: S.name, jobs: [{ type: 'ob', kind: 'name', value: S.name }] });
			if (S.tagline !== (D.tagline || '')) { extra.push({ icon: D.setupIcon, name: T.tagStep, sub: S.tagline, jobs: [{ type: 'ob', kind: 'tagline', value: S.tagline }] }); }
			rows = rows.concat(extra);

			$('#queue').innerHTML = rows.map(function (r, i) {
				return '<li class="wait" data-i="' + i + '"><img src="' + esc(r.icon) + '" alt=""><span class="n">' + esc(r.name) + '<small>' + esc(r.sub) + '</small></span><span class="st">' + esc(T.waiting) + '</span></li>';
			}).join('');
			var total = rows.reduce(function (a, r) { return a + r.jobs.length; }, 0);
			var done = 0;

			function progress() {
				var f = total ? done / total : 1;
				$('#pbar').style.width = (f * 100).toFixed(1) + '%';
				$('#pctR').textContent = Math.round(f * 100) + '%';
				setMsg(T.msgs[Math.min(4, Math.floor(f * 5))]);
			}
			progress();

			var ri = 0;
			function nextRow() {
				if (ri >= rows.length) { return finish(); }
				var r = rows[ri];
				var li = $('#queue li[data-i="' + ri + '"]');
				li.className = 'now';
				var q = $('#queue');
				q.scrollTop = Math.max(0, li.offsetTop - q.clientHeight / 3);
				li.querySelector('.st').innerHTML = '<span class="spin"></span>' + esc(r.jobs[0].type === 'ob' ? T.applying : T.installing);
				$('#pctL').textContent = (ri + 1) + ' / ' + rows.length;
				var rowOk = true;
				var ji = 0;
				function nextJob() {
					if (ji >= r.jobs.length) {
						li.className = rowOk ? 'ok' : 'fail';
						li.querySelector('.st').textContent = rowOk ? (r.jobs[0].type === 'ob' ? T.applied : T.installed) : T.failed;
						if (rowOk && r.jobs.some(function (j) { return j.pkg; }) && r.jobs[0].slug !== 'fluxwp') { stats.pk++; }
						ri++;
						return nextRow();
					}
					var j = r.jobs[ji];
					var req = j.type === 'flxu'
						? { action: 'flxu_step', nonce: D.flxuNonce, kind: j.kind, slug: j.slug }
						: { action: 'flxk_onboarding_step', nonce: D.nonce, kind: j.kind, value: j.value || '' };
					line('<span class="w">' + esc(r.name) + '</span> <span class="y">' + esc(j.kind === 'activate' ? T.activating : (j.type === 'ob' ? T.applying : T.installing)) + '…</span>');
					post(req).then(function (res) {
						(res.lines || []).forEach(function (l) {
							line((l.ok ? '<span class="g">✓</span> ' : '<span class="r">✕</span> ') + esc(l.text));
							if (!l.ok) { errors.push(l.text); }
						});
						if (!res.ok) { rowOk = false; }
						if (res.pages) { stats.pages = res.pages; }
					}).catch(function (e) {
						rowOk = false;
						errors.push(e.message);
						line('<span class="r">✕</span> ' + esc(e.message));
					}).then(function () {
						done++;
						ji++;
						progress();
						nextJob();
					});
				}
				nextJob();
			}
			nextRow();

			function finish() {
				clearInterval(clock);
				$('#stage').classList.add('done');
				setMsg(T.doneMsg);
				$('#sub').textContent = T.doneSub;
				line('<span class="g">✓</span> <span class="w">' + esc(T.doneMsg) + '</span>');
				post({ action: 'flxk_onboarding_step', nonce: D.nonce, kind: 'done' }).catch(function () {}).then(function () {
					window.onbeforeunload = null;
					setTimeout(function () { renderDone(errors, stats); go(LAST); }, 1200);
				});
			}
		});
	}

	/* ---------- step 5: done ---------- */
	function renderDone(errors, stats) {
		var warn = errors.length > 0;
		var next = (D.next || []).filter(function (n) { return S.sel[n.slug]; });
		$('#step' + LAST).innerHTML =
			'<div class="donebox"><div class="okmark' + (warn ? ' warn' : '') + '">' +
			(warn ? '<svg viewBox="0 0 24 24" fill="none" stroke="#B54708" stroke-width="2.4" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>') +
			'</div><h1>' + esc(warn ? T.h4warn : T.h4) + '</h1><p class="lead">' + esc(warn ? T.lead4warn : T.lead4) + '</p>' +
			(warn ? '<ul class="errs">' + errors.slice(0, 8).map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>' : '') +
			'<div class="sum"><div><b>' + stats.pk + '</b><span>' + esc(T.sumPk) + '</span></div><div><b>' + stats.pages + '</b><span>' + esc(T.sumPg) + '</span></div><div><b>' + esc(useTheme() && S.style ? curStyle().name : '-') + '</b><span>' + esc(T.sumSt) + '</span></div></div>' +
			'<div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-primary" href="' + esc(D.home) + '" style="text-decoration:none">' + esc(T.viewSite) + OUT + '</a>' +
			'<a class="btn btn-ghost" href="' + esc(warn ? D.packages : D.dashboard) + '" style="text-decoration:none;color:inherit">' + esc(T.toDash) + '</a></div>' +
			(next.length ? '<ul class="nexts">' + next.map(function (n) {
				return '<li><a href="' + esc(n.url) + '"><img src="' + esc(n.icon) + '" alt=""><span>' + esc(n.title) + '<small>' + esc(n.text) + '</small></span><em>' + esc(n.where) + '</em></a></li>';
			}).join('') + '</ul>' : '') +
			'</div>';
	}

	/* ---------- events ---------- */
	function openSkip() { $('#skipModal').classList.add('on'); $('#skipNo').focus(); }
	function closeSkip() { $('#skipModal').classList.remove('on'); }
	$('#cTerms').addEventListener('change', function (e) {
		$('#startBtn').disabled = !e.target.checked;
		$('#startBtn').title = e.target.checked ? '' : T.cNeed;
	});
	$('#startBtn').addEventListener('click', function () {
		if (!$('#cTerms').checked) { return; }
		// Şartlar onayı + kullanım verisi izni (varsayılan kapalı). Kayıt hatası kurulumu durdurmaz.
		post({ action: 'flxk_onboarding_step', nonce: D.nonce, kind: 'consent', value: $('#cUsage').checked ? '1' : '0' }).catch(function () {});
		go(1);
	});
	$('#skip2').addEventListener('click', openSkip);
	$('#skipBtn').addEventListener('click', openSkip);
	$('#skipNo').addEventListener('click', closeSkip);
	$('#skipYes').addEventListener('click', function () {
		$('#skipYes').disabled = true;
		post({ action: 'flxk_onboarding_step', nonce: D.nonce, kind: 'skip' }).catch(function () {}).then(function () { window.location.href = D.packages; });
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSkip(); } });
	// Görünüm adımında Devam/Geri önce sekmeler arasında gezer.
	$('#nextBtn').addEventListener('click', function () {
		if (S.step === I_APP && S.tab < tabs().length - 1) { S.tab++; renderAppearance(); bar(); return; }
		go(S.step + 1);
	});
	$('#backBtn').addEventListener('click', function () {
		if (S.step === I_APP && S.tab > 0) { S.tab--; renderAppearance(); bar(); return; }
		go(S.step - 1);
	});

	renderBase();
	renderGrid();
	renderAppearance();
	go(0);
}());
