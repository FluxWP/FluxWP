/*
 * Kurulum sihirbazı kullanımı (anonim). onboarding.js'e dokunmaz: DOM'u izler. Veri tarayıcıda tutulur;
 * çıkışta siteye bir kez gönderilir, site de yalnız "Ürünü geliştirmemize yardımcı olun" işaretliyse saklar.
 * Veri: window.FLXK_TM (FLXK_Telemetry::onboarding_script).
 */
(function () {
	'use strict';
	var C = window.FLXK_TM || {};
	var D = window.FLXK_OB || {};
	var root = document.getElementById('flxk-ob');
	if (!root || !C.ajax || !window.FormData) { return; }

	var t0 = Date.now();
	var sent = false;
	var outcome = 'left';
	var maxStep = 0;
	var path = [];
	var initial = null; // ilk taramada açık olan kartlar (öneri ya da kurulu)
	var ever = {};      // en az bir kez açık görülen kartlar
	var touched = {};   // kullanıcının tıkladığı kartlar

	function $$(s) { return Array.prototype.slice.call(document.querySelectorAll(s)); }
	function sections() {
		return $$('#flxk-ob section.step').sort(function (a, b) { return parseInt(a.id.slice(4), 10) - parseInt(b.id.slice(4), 10); });
	}
	/* Adım adları: 0 intro, 1 base, sondan: done, install, appearance; aradakiler eklenti kategorileri. */
	function names() {
		var n = sections().length;
		var groups = (D.groups || []).filter(function (g) {
			return (D.plugins || []).some(function (p) { return p.group === g.key && !p.core; });
		});
		var out = [];
		for (var i = 0; i < n; i++) {
			if (i === 0) { out.push('intro'); } else if (i === 1) { out.push('base'); } else if (i === n - 1) { out.push('done'); } else if (i === n - 2) { out.push('install'); } else if (i === n - 3) { out.push('appearance'); } else {
				var g = groups[i - 2];
				out.push('group:' + (g ? g.key : String(i - 2)));
			}
		}
		return out;
	}
	function current() {
		var s = sections();
		for (var i = 0; i < s.length; i++) { if (s[i].classList.contains('on')) { return i; } }
		return 0;
	}
	function onStep() {
		var i = current();
		var nm = names()[i] || String(i);
		if (path[path.length - 1] !== nm) { path.push(nm); }
		if (i > maxStep) { maxStep = i; }
		if (nm === 'done') { outcome = 'done'; }
	}
	function key(el) {
		var p = el.getAttribute('data-p');
		if (p) { return p; }
		var b = el.getAttribute('data-b');
		return b === 'fluxwp' ? 'fluxwp' : 'core:' + b;
	}
	function state() {
		var on = {};
		$$('#flxk-ob [data-p][aria-pressed], #flxk-ob [data-b][aria-pressed]').forEach(function (el) {
			if (el.getAttribute('aria-pressed') === 'true') { on[key(el)] = 1; }
		});
		return on;
	}
	function scan() {
		var on = state();
		if (!initial && Object.keys(on).length) { initial = on; }
		Object.keys(on).forEach(function (k) { ever[k] = 1; });
	}
	function sel(s) {
		var el = document.querySelector('#flxk-ob ' + s);
		return el ? String(el.getAttribute('data-k') || '').slice(0, 64) : '';
	}
	function report() {
		if (sent) { return; }
		sent = true;
		scan();
		var on = state();
		var init = initial || {};
		var picked = Object.keys(on);
		var dropped = Object.keys(ever).filter(function (k) { return !on[k] && touched[k]; });
		var recOff = Object.keys(init).filter(function (k) { return !on[k]; });
		var data = {
			out: outcome,
			max: maxStep,
			total: sections().length,
			at: names()[maxStep] || '',
			path: path.slice(0, 30),
			picked: picked.slice(0, 60),
			dropped: dropped.slice(0, 60),
			rec_off: recOff.slice(0, 60),
			preset: sel('.preset.on'),
			style: sel('.pal.on'),
			secs: Math.round((Date.now() - t0) / 10000) * 10
		};
		var f = new FormData();
		f.append('action', 'flxk_tm_funnel');
		f.append('nonce', C.nonce || '');
		f.append('data', JSON.stringify(data));
		if (navigator.sendBeacon && navigator.sendBeacon(C.ajax, f)) { return; }
		try { fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: f, keepalive: true }); } catch (e) { /* yok say */ }
	}

	document.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target.closest('[data-p],[data-b],.sel-all,#noneBtn,#skipYes') : null;
		if (!t) { return; }
		if (t.id === 'skipYes') { outcome = 'skip'; report(); return; }
		if (t.hasAttribute('data-p') || t.hasAttribute('data-b')) { touched[key(t)] = 1; }
		if (t.classList.contains('sel-all') || t.id === 'noneBtn') {
			Object.keys(state()).forEach(function (k) { touched[k] = 1; });
		}
		setTimeout(function () {
			if (t.classList.contains('sel-all')) { Object.keys(state()).forEach(function (k) { touched[k] = 1; }); }
			scan();
		}, 0);
	}, true);

	new MutationObserver(function () { onStep(); }).observe(root, { subtree: true, attributes: true, attributeFilter: ['class'] });
	setTimeout(function () { scan(); onStep(); }, 0);

	// Bitti ekranı görününce raporla; yoksa sayfadan çıkarken.
	var poll = setInterval(function () { if (outcome === 'done') { clearInterval(poll); report(); } }, 1000);
	window.addEventListener('pagehide', report);
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden' && outcome !== 'left') { report(); }
	});
})();
