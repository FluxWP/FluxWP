/**
 * Ortak model seçici: input[data-flxai-model] alanına aranabilir liste ekler.
 * Diğer eklentiler: flxai_enqueue_model_picker() + <input data-flxai-model> ya da FLXAIModelPicker.attach(input).
 */
(function () {
	'use strict';
	var C = window.FLXAI_MP || window.FLXAI_ADMIN || {};
	var L = C.i18n || {};
	function t(s) { return L[s] || s; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	var cache = null, loading = null;

	function load() {
		if (cache) return Promise.resolve(cache);
		if (loading) return loading;
		loading = fetch(C.rest + 'models' + (C.rest.indexOf('?') < 0 ? '?' : '&') + '_locale=user&_t=' + Date.now(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || ('HTTP ' + r.status)); return j; }); })
			.then(function (j) { cache = j.models || []; return cache; })
			.catch(function (e) { loading = null; throw e; });
		return loading;
	}
	function price(m) { return '$' + (+m.in).toFixed(m.in < 1 ? 2 : 2) + ' / $' + (+m.out).toFixed(2); }

	function attach(inp) {
		if (inp.__flxaiMp) return;
		inp.__flxaiMp = true;
		var wrap = document.createElement('span');
		wrap.className = 'flxai-mp';
		inp.parentNode.insertBefore(wrap, inp);
		wrap.appendChild(inp);
		var list = document.createElement('div');
		list.className = 'flxai-mp-list'; list.hidden = true;
		wrap.appendChild(list);
		var sel = -1, rows = [];
		function render() {
			var q = inp.value.trim().toLowerCase();
			rows = (cache || []).filter(function (m) { return !q || (m.id + ' ' + m.name + ' ' + (m.family || '')).toLowerCase().indexOf(q) >= 0; }).slice(0, 80);
			if (!rows.length) { list.innerHTML = '<div class="flxai-mp-msg">' + esc(t('Eşleşen model yok; kimliği elle yazabilirsiniz.')) + '</div>'; return; }
			list.innerHTML = rows.map(function (m, i) {
				return '<div class="flxai-mp-item' + (i === sel ? ' is-on' : '') + '" data-id="' + esc(m.id) + '"><span>' + esc(m.name) + ' <code>' + esc(m.id) + '</code>' + (m.tools ? '' : ' <small>' + esc(t('araç yok')) + '</small>') + '</span><small>' + esc(price(m)) + '</small></div>';
			}).join('');
		}
		function show() {
			list.hidden = false;
			if (cache) { render(); return; }
			list.innerHTML = '<div class="flxai-mp-msg">' + esc(t('Modeller yükleniyor…')) + '</div>';
			load().then(render).catch(function (e) { list.innerHTML = '<div class="flxai-mp-msg">' + esc(e.message) + '</div>'; });
		}
		function pick(id) { inp.value = id; list.hidden = true; inp.dispatchEvent(new Event('change', { bubbles: true })); }
		inp.addEventListener('focus', show);
		inp.addEventListener('input', function () { sel = -1; show(); });
		inp.addEventListener('keydown', function (e) {
			if (list.hidden) return;
			if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(rows.length - 1, sel + 1); render(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); render(); }
			else if (e.key === 'Enter' && sel >= 0 && rows[sel]) { e.preventDefault(); pick(rows[sel].id); }
			else if (e.key === 'Escape') list.hidden = true;
		});
		list.addEventListener('mousedown', function (e) { var it = e.target.closest('.flxai-mp-item'); if (it) { e.preventDefault(); pick(it.getAttribute('data-id')); } });
		inp.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 150); });
	}

	window.FLXAIModelPicker = { attach: attach, load: load };
	function init() { document.querySelectorAll('input[data-flxai-model]').forEach(attach); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
