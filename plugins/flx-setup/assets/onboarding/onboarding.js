/* Flux Setup kurulum sihirbazı. Veri: window.FLXK_OB (includes/onboarding.php). */
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
		name: D.siteName || ''
	};
	(D.plugins || []).forEach(function (p) { if (p.rec || p.active) { S.sel[p.slug] = 1; } });
	var presets = D.presets || [];
	S.preset = presets.length ? presets[0].id : 'blank';
	var styles = D.styles || [];
	var LAST = 5; // 0 tanıtım, 1 temel, 2 eklentiler, 3 görünüm, 4 kurulum, 5 bitti
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

	/* ---------- skeleton ---------- */
	root.innerHTML =
		'<div class="wrap">' +
		'<header class="top"><div class="top-in"><img class="logo" src="' + esc(root.getAttribute('data-logo')) + '" alt="FluxWP">' +
		'<div class="prog" id="prog"><span id="progT"></span><div class="track"><i id="progI"></i></div></div>' +
		'<div class="right"><button class="link" id="skipBtn">' + esc(T.skip) + '</button></div></div></header>' +
		'<main>' +
		'<section class="step" id="step0"></section>' +
		'<section class="step" id="step1"></section>' +
		'<section class="step" id="step2"></section>' +
		'<section class="step" id="step3"></section>' +
		'<section class="step" id="step4"></section>' +
		'<section class="step" id="step5"></section>' +
		'</main>' +
		'<div class="bar" id="bar1"><div class="bar-in"><div class="bar-info"><div class="stack" id="stack"></div><span class="t" id="barText"></span></div>' +
		'<button class="btn btn-ghost" id="backBtn">' + esc(T.back) + '</button>' +
		'<button class="btn btn-primary" id="nextBtn"><span id="nextLbl"></span>' + ARROW + '</button></div></div>' +
		'</div>' +
		'<div class="modal" id="skipModal" role="dialog" aria-modal="true" aria-labelledby="skipT"><div class="box"><h3 id="skipT">' + esc(T.skipT) + '</h3><p>' + esc(T.skipP) + '</p>' +
		'<div class="acts"><button class="btn btn-ghost" id="skipNo">' + esc(T.stay) + '</button><button class="btn btn-primary" id="skipYes">' + esc(T.skipYes) + '</button></div></div></div>';

	/* ---------- step 0: intro ---------- */
	var mosaicSlugs = plugins.slice(0, 15).map(function (p) { return p.slug; });
	if (D.theme) { mosaicSlugs.push('fluxwp'); }
	$('#step0').innerHTML =
		'<div class="intro"><div>' +
		'<p class="kicker">Flux Setup</p><h1>' + esc(T.i_h) + '</h1><p class="lead">' + esc(T.i_p) + '</p>' +
		(D.offline ? '<p class="notice">' + esc(T.offline) + (D.connError ? ' (' + esc(D.connError) + ')' : '') + '</p>' : '') +
		'<ul class="facts">' +
		(plugins.length ? '<li><b>' + esc(fmt(T.f1b, plugins.length)) + '</b><span>' + esc(T.f1s) + '</span></li>' : '') +
		'<li><b>' + esc(T.f2b) + '</b><span>' + esc(T.f2s) + '</span></li>' +
		'<li><b>' + esc(T.f3b) + '</b><span>' + esc(T.f3s) + '</span></li></ul>' +
		'<div class="acts"><button class="btn btn-primary" id="startBtn">' + esc(T.start) + ARROW + '</button>' +
		'<button class="btn btn-ghost" id="skip2">' + esc(T.skipSelf) + '</button><small>' + esc(T.time) + '</small></div>' +
		'</div><div class="mosaic" aria-hidden="true">' +
		mosaicSlugs.map(function (s) { return '<div><img src="' + esc(icon(s)) + '" alt=""></div>'; }).join('') +
		'</div></div>';

	/* ---------- step 1: base (theme + core packages) ---------- */
	var core = plugins.filter(function (p) { return p.core; });
	var others = plugins.filter(function (p) { return !p.core; });
	function hasBase() { return !!D.theme || core.length > 0; }
	function bigCard(k, on, shot, ic, name, desc, badge) {
		return '<button type="button" class="big' + (on ? ' on' : '') + '" data-b="' + esc(k) + '" aria-pressed="' + on + '">' +
			'<div class="shot">' + (shot ? '<img src="' + esc(shot) + '" alt="">' : '<div class="ph-shot"><img src="' + esc(ic) + '" alt=""></div>') + '</div>' +
			(badge ? '<span class="badge">' + esc(badge) + '</span>' : '') +
			'<span class="tick">' + TICK + '</span>' +
			'<div class="body"><img class="ic" src="' + esc(ic) + '" alt=""><div><h3>' + esc(name) + '</h3><p>' + esc(desc) + '</p></div></div></button>';
	}
	function renderBase() {
		var cards = (D.theme ? bigCard('fluxwp', S.theme, D.themeThumb, icon('fluxwp'), T.theme, T.themeDesc, T.recB) : '') +
			core.map(function (p) { return bigCard(p.slug, !!S.sel[p.slug], p.thumb, icon(p.slug), p.name, p.desc, p.active ? T.installedB : T.recB); }).join('');
		$('#step1').innerHTML =
			'<div class="center-head"><p class="kicker">' + esc(T.coreK) + '</p><h1>' + esc(T.hBase) + '</h1><p class="lead">' + esc(T.leadBase) + '</p></div>' +
			'<div class="bigs">' + cards + '</div>' +
			'<div class="noneRow"><button type="button" class="link quiet" id="noneBtn">' + esc(T.noneBtn) + '</button></div>';
		$$('.big', $('#step1')).forEach(function (c) {
			c.addEventListener('click', function () {
				var k = c.getAttribute('data-b');
				var on;
				if (k === 'fluxwp') { S.theme = !S.theme; on = S.theme; } else { if (S.sel[k]) { delete S.sel[k]; } else { S.sel[k] = 1; } on = !!S.sel[k]; }
				c.classList.toggle('on', on);
				c.setAttribute('aria-pressed', on);
				renderAppearance();
				bar();
			});
		});
		$('#noneBtn').addEventListener('click', function () {
			S.theme = false;
			core.forEach(function (p) { delete S.sel[p.slug]; });
			renderBase();
			renderAppearance();
			go(2);
		});
	}

	/* ---------- step 2: plugins ---------- */
	function card(p) {
		var on = !!S.sel[p.slug];
		return '<button type="button" class="card' + (on ? ' on' : '') + '" data-p="' + esc(p.slug) + '" aria-pressed="' + on + '">' +
			'<div class="shot">' + (p.thumb ? '<img loading="lazy" src="' + esc(p.thumb) + '" alt="">' : '<div class="ph-shot"><img src="' + esc(icon(p.slug)) + '" alt=""></div>') + '</div>' +
			(p.active ? '<span class="badge">' + esc(T.installedB) + '</span>' : '') +
			'<span class="tick">' + TICK + '</span>' +
			'<div class="body"><img class="ic" src="' + esc(icon(p.slug)) + '" alt=""><div><h3>' + esc(p.name) + '</h3><p>' + esc(p.desc) + '</p></div></div></button>';
	}
	/* Tüm kartlar tek ekrana sığsın: sütun sayısı kart sayısına göre, satırlar eşit. */
	function gridStyle(n) {
		var cols = n > 20 ? 6 : n > 12 ? 5 : n > 6 ? 4 : 3;
		return '--cols:' + cols + ';--rows:' + Math.ceil(n / cols);
	}
	function renderGrid() {
		$('#step2').innerHTML =
			'<div class="head-row"><div><p class="kicker">' + esc(T.k1) + '</p><h1>' + esc(T.h1) + '</h1><p class="lead">' + esc(T.lead1) + '</p></div>' +
			'<button class="link" id="selAll"></button></div>' +
			(others.length ? '<div class="grid" style="' + gridStyle(others.length) + '">' + others.map(card).join('') + '</div>' : '') +
			(plugins.length ? '' : '<p class="empty">' + esc(T.offline) + '</p>');
		$$('button.card', $('#step2')).forEach(function (c) {
			c.addEventListener('click', function () {
				var k = c.getAttribute('data-p');
				if (S.sel[k]) { delete S.sel[k]; } else { S.sel[k] = 1; }
				c.classList.toggle('on', !!S.sel[k]);
				c.setAttribute('aria-pressed', !!S.sel[k]);
				bar();
			});
		});
		$('#selAll').addEventListener('click', function () {
			var allOn = others.every(function (p) { return S.sel[p.slug]; });
			others.forEach(function (p) { if (allOn) { delete S.sel[p.slug]; } else { S.sel[p.slug] = 1; } });
			renderGrid();
			bar();
		});
		$('#selAll').hidden = !others.length;
	}

	/* ---------- step 3: appearance ---------- */
	var SITE = {
		h: T.i_h,
		cards: 3
	};
	function curStyle() {
		for (var i = 0; i < styles.length; i++) { if (styles[i].key === S.style) { return styles[i]; } }
		return { p: '#1F2937', pd: '#111827', l: '#3B82F6', m: '#F2EEE7', ms: '#F8F5F0', sl: '#E6E0D6', name: '' };
	}
	function shownPresets() {
		return presets.filter(function (p) { return useTheme() || !p.theme; });
	}
	function curPreset() {
		var list = shownPresets();
		for (var i = 0; i < list.length; i++) { if (list[i].id === S.preset) { return list[i]; } }
		return list.length ? list[0] : { id: 'blank', name: '' };
	}
	function thumb(p, st) {
		var h = '<i class="h" style="--pc:' + esc(st.p) + '"></i>';
		if (p.id === 'blank') { return '<i class="h" style="--pc:' + esc(st.p) + ';opacity:.25"></i>'; }
		return h + '<div class="row"><i></i><i></i><i></i></div>';
	}
	function renderAppearance() {
		var st = curStyle();
		S.preset = curPreset().id;
		$('#step3').innerHTML =
			'<div class="app"><div>' +
			'<p class="kicker">' + esc(T.k2) + '</p><h1>' + esc(T.h2) + '</h1><p class="lead">' + esc(T.lead2) + '</p>' +
			'<div class="group"><h3>' + esc(T.preset) + '</h3><p>' + esc(T.presetHint) + '</p><div class="presets">' +
			shownPresets().map(function (p) {
				return '<button type="button" class="preset' + (S.preset === p.id ? ' on' : '') + '" data-k="' + esc(p.id) + '" aria-pressed="' + (S.preset === p.id) + '">' +
					'<div class="thumb">' + thumb(p, st) + '</div><b>' + esc(p.name) + '</b>' + (p.desc ? '<small>' + esc(p.desc) + '</small>' : '') + '</button>';
			}).join('') + '</div></div>' +
			(styles.length && useTheme() ? '<div class="group"><h3>' + esc(T.color) + '</h3><p>' + esc(T.colorHint) + '</p><div class="swatches">' +
				styles.map(function (s) {
					return '<button type="button" class="sw' + (S.style === s.key ? ' on' : '') + '" data-k="' + esc(s.key) + '" aria-pressed="' + (S.style === s.key) + '"><i style="background:' + esc(s.p) + ';--leaf:' + esc(s.l) + '"></i>' + esc(s.name) + '</button>';
				}).join('') + '</div></div>' : '') +
			'<div class="group"><h3><label for="siteName">' + esc(T.siteName) + '</label></h3><p>' + esc(T.nameHint) + '</p><input type="text" id="siteName" value="' + esc(S.name) + '"></div>' +
			'</div><div class="preview"><div class="browser"><div class="chrome"><i></i><i></i><i></i><span>' + esc(D.host) + '</span></div>' +
			'<div class="site" id="site"><div class="s-head"><div class="s-logo" id="sLogo"></div><div class="s-name" id="sName"></div><div class="s-nav"><span></span></div></div>' +
			'<div class="s-hero"><h5 id="sH"></h5><p id="sP"></p><span class="b" id="sB"></span></div>' +
			'<div class="s-cards" id="sCards"></div><div class="s-foot" id="sFoot"></div></div></div></div></div>';
		$$('.preset', $('#step3')).forEach(function (b) {
			b.addEventListener('click', function () { S.preset = b.getAttribute('data-k'); renderAppearance(); bar(); });
		});
		$$('.sw', $('#step3')).forEach(function (b) {
			b.addEventListener('click', function () { S.style = b.getAttribute('data-k'); renderAppearance(); bar(); });
		});
		$('#siteName').addEventListener('input', function (e) { S.name = e.target.value; site(); });
		site();
	}
	function site() {
		var st = curStyle();
		var el = $('#site');
		if (!el) { return; }
		[['--p', st.p], ['--pd', st.pd], ['--l', st.l], ['--m', st.m], ['--ms', st.ms], ['--sl', st.sl]].forEach(function (kv) { el.style.setProperty(kv[0], kv[1]); });
		var name = S.name || 'Site';
		var pr = curPreset();
		$('#sName').textContent = name;
		$('#sLogo').textContent = (name.trim().charAt(0) || 'S').toLocaleUpperCase();
		$('#sH').textContent = pr.id === 'blank' ? name : (pr.name || name);
		$('#sP').textContent = pr.desc || T.lead2;
		$('#sB').textContent = T.viewSite;
		$('#sCards').innerHTML = pr.id === 'blank' ? '' : [1, 2, 3].map(function () { return '<div class="s-card"><div class="ph"></div><b>&nbsp;</b><small>&nbsp;</small></div>'; }).join('');
		$('#sFoot').textContent = '© ' + new Date().getFullYear() + ' ' + name;
	}

	/* ---------- chrome ---------- */
	function bar() {
		var n = count(S.sel);
		var stack = (useTheme() ? ['fluxwp'] : []).concat(plugins.filter(function (p) { return S.sel[p.slug]; }).map(function (p) { return p.slug; })).slice(0, 8);
		$('#stack').innerHTML = stack.map(function (s) { return '<img src="' + esc(icon(s)) + '" alt="">'; }).join('');
		var sel = fmt(useTheme() ? T.selected : T.selectedNT, n);
		$('#barText').innerHTML = S.step < 3 ? '<b>' + esc(sel) + '</b>' : '<b>' + esc(curPreset().name) + '</b>' + (useTheme() && S.style ? ' · ' + esc(curStyle().name) : '') + ' · ' + esc(sel);
		var all = $('#selAll');
		if (all) { all.textContent = others.every(function (p) { return S.sel[p.slug]; }) ? T.selNone : T.selAll; }
		$('#nextLbl').textContent = S.step === 3 ? T.install : T.next;
		$('#bar1').hidden = !(S.step >= 1 && S.step <= 3);
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
		$('#skipBtn').style.visibility = (S.step >= 1 && S.step <= 3) ? '' : 'hidden';
	}
	function go(n) {
		if (n === 1 && !hasBase()) { n = S.step > 1 ? 0 : 2; }
		S.step = n;
		chrome();
		bar();
		window.scrollTo(0, 0);
		if (n === 4) { install(); }
	}

	/* ---------- step 4: install ---------- */
	var log;
	function line(html) {
		var cur = log.querySelector('.cursor');
		if (cur) { cur.parentNode.remove(); }
		var d = document.createElement('div');
		d.innerHTML = html;
		log.appendChild(d);
		var c = document.createElement('div');
		c.innerHTML = '<span class="cursor"></span>';
		log.appendChild(c);
		while (log.children.length > 60) { log.removeChild(log.firstChild); }
	}
	function setMsg(m) {
		var e = $('#msg');
		if (e.textContent === m) { return; }
		e.classList.add('fade');
		setTimeout(function () { e.textContent = m; e.classList.remove('fade'); }, 300);
	}

	function install() {
		var errors = [];
		var stats = { pk: 0, pages: 0 };
		$('#step4').innerHTML =
			'<div class="install" id="stage"><div>' +
			'<div class="mark" aria-hidden="true"><i></i><i></i><i></i><i></i></div>' +
			'<div class="msg" id="msg" aria-live="polite">' + esc(T.msgs[0]) + '</div><p class="sub" id="sub">' + esc(T.keep) + '</p>' +
			'<div class="meter"><span id="pctL"></span><b id="pctR"></b></div><div class="ptrack"><i id="pbar"></i></div><ul class="queue" id="queue"></ul></div>' +
			'<div class="console"><div class="ch"><span>flux-setup</span><span id="clock">00:00</span></div><div class="log" id="log"></div></div></div>';
		log = $('#log');
		var sec = 0;
		var clock = setInterval(function () { sec++; $('#clock').textContent = ('0' + (sec / 60 | 0)).slice(-2) + ':' + ('0' + sec % 60).slice(-2); }, 1000);
		window.onbeforeunload = function () { return T.keep; };

		var slugs = Object.keys(S.sel);
		if (useTheme()) { slugs.unshift('fluxwp'); }
		var pr = curPreset();
		if (pr.package) { slugs.push(pr.package); }
		line('<span class="d">$</span> <span class="w">flux-setup install</span> <span class="d">' + esc(slugs.join(' ')) + '</span>');

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
			if (pr.id !== 'blank') { extra.push({ icon: D.setupIcon, name: T.presetStep + ': ' + pr.name, sub: 'flx-preset/1 · ' + pr.id, jobs: [{ type: 'ob', kind: 'preset', value: pr.id }] }); }
			extra.push({ icon: D.setupIcon, name: T.nameStep, sub: S.name, jobs: [{ type: 'ob', kind: 'name', value: S.name }] });
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
					line('<span class="y">' + esc(j.kind) + '</span> <span class="w">' + esc(j.slug || j.value || '') + '</span>');
					post(req).then(function (res) {
						(res.lines || []).forEach(function (l) {
							line('  ' + (l.ok ? '<span class="g">✓</span> ' : '<span class="r">✕</span> ') + esc(l.text));
							if (!l.ok) { errors.push(l.text); }
						});
						if (!res.ok) { rowOk = false; }
						if (res.pages) { stats.pages = res.pages; }
					}).catch(function (e) {
						rowOk = false;
						errors.push(e.message);
						line('  <span class="r">✕</span> ' + esc(e.message));
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
				line('<span class="g">done</span> <span class="d">' + rows.length + '</span>');
				post({ action: 'flxk_onboarding_step', nonce: D.nonce, kind: 'done' }).catch(function () {}).then(function () {
					window.onbeforeunload = null;
					setTimeout(function () { renderDone(errors, stats); go(5); }, 1200);
				});
			}
		});
	}

	/* ---------- step 5: done ---------- */
	function renderDone(errors, stats) {
		var warn = errors.length > 0;
		var next = (D.next || []).filter(function (n) { return S.sel[n.slug]; });
		$('#step5').innerHTML =
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
	$('#startBtn').addEventListener('click', function () { go(1); });
	$('#skip2').addEventListener('click', openSkip);
	$('#skipBtn').addEventListener('click', openSkip);
	$('#skipNo').addEventListener('click', closeSkip);
	$('#skipYes').addEventListener('click', function () {
		$('#skipYes').disabled = true;
		post({ action: 'flxk_onboarding_step', nonce: D.nonce, kind: 'skip' }).catch(function () {}).then(function () { window.location.href = D.packages; });
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSkip(); } });
	$('#nextBtn').addEventListener('click', function () { go(S.step + 1); });
	$('#backBtn').addEventListener('click', function () { go(S.step - 1); });

	renderBase();
	renderGrid();
	renderAppearance();
	go(0);
}());
