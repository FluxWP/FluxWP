/* Flux Analytics: Ayarlar › Huniler görsel düzenleyicisi. Kaydedilince funnels_json alanıyla gönderilir. */
(function () {
	'use strict';
	var C = window.FLXAN || {};
	var L = C.i18n || {};
	var box = document.getElementById('flxan-fed');
	if (!box || !C.funnels) return;
	function t(s) { return L[s] || s; }
	function fmt(s) { var a = arguments; return String(s).replace(/%(\d)\$s|%s/g, function (m, i) { return i ? a[+i] : a[1]; }); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function clone(x) { return JSON.parse(JSON.stringify(x)); }

	var MAXF = 5, MAXS = 10;
	var F = clone(C.funnels);
	var form = box.closest('form');
	var text = form.querySelector('.flxan-ftext');
	if (text) { text.hidden = true; text.disabled = true; }
	var hidden = document.createElement('input');
	hidden.type = 'hidden'; hidden.name = 'funnels_json';
	form.appendChild(hidden);
	form.addEventListener('submit', function () { hidden.value = JSON.stringify(F); });

	var pages = C.fpages || [], events = C.fevents || {};
	var pageTitle = {};
	pages.forEach(function (p) { pageTitle[p.path] = p.title; });
	var dl = document.createElement('datalist');
	dl.id = 'flxan-fpages';
	dl.innerHTML = pages.map(function (p) { return '<option value="' + esc(p.path) + '">' + esc(p.title) + '</option>'; }).join('');
	box.appendChild(dl);

	// Hazır adımlar (tek tıkla eklenir).
	var PRESETS = [];
	(C.fdefault && C.fdefault[0] ? C.fdefault[0].steps : []).forEach(function (s) { PRESETS.push(s); });

	var TYPES = [
		['any', t('Siteye giriş'), '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>'],
		['path', t('Sayfa'), '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
		['event', t('Olay'), '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>']
	];
	function ico(p) { return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; }

	function valueField(st, fi, si) {
		if (st.type === 'any') return '<div class="flxan-fval flxan-fval--any">' + esc(t('Her ziyaretin ilk sayfası sayılır')) + '</div>';
		if (st.type === 'event') {
			var parts = String(st.value || '').split(':');
			var name = parts.shift() || '', param = parts.join(':');
			var full = name + (param ? ':' + param : '');
			var opts = '', known = false;
			Object.keys(events).forEach(function (k) {
				if (k === full) known = true;
				opts += '<option value="' + esc(k) + '"' + (k === full ? ' selected' : '') + '>' + esc(events[k]) + ' (' + esc(k) + ')</option>';
			});
			if (!known && full) opts = '<option value="' + esc(full) + '" selected>' + esc(full) + '</option>' + opts;
			return '<div class="flxan-fval"><select data-f="value" data-fi="' + fi + '" data-si="' + si + '">' + (full ? '' : '<option value="">' + esc(t('Olay seçin…')) + '</option>') + opts + '<option value="__custom">' + esc(t('Başka olay yaz…')) + '</option></select></div>';
		}
		var title = pageTitle[st.value] || pageTitle[String(st.value).replace(/\/$/, '')] || '';
		return '<div class="flxan-fval"><input data-f="value" data-fi="' + fi + '" data-si="' + si + '" list="flxan-fpages" value="' + esc(st.value) + '" placeholder="/bagis/" spellcheck="false" autocomplete="off">' +
			'<span class="flxan-fval__hint">' + (title ? esc(title) + ' · ' : '') + esc(t('bu yolla başlayan tüm sayfalar')) + '</span></div>';
	}

	function render() {
		var h = '';
		F.forEach(function (f, fi) {
			h += '<div class="flxan-fcard" data-fi="' + fi + '">' +
				'<div class="flxan-fcard__head"><span class="flxan-fcard__badge">' + (fi + 1) + '</span>' +
				'<input class="flxan-fcard__name" data-f="name" data-fi="' + fi + '" value="' + esc(f.name) + '" aria-label="' + esc(t('Huni adı')) + '" placeholder="' + esc(t('Huni adı')) + '">' +
				'<span class="flxan-muted">' + esc(fmt(t('%s adım'), f.steps.length)) + '</span>' +
				(F.length > 1 ? '<button type="button" class="flxan-ico" data-act="delf" data-fi="' + fi + '" title="' + esc(t('Huniyi sil')) + '" aria-label="' + esc(t('Huniyi sil')) + '">🗑</button>' : '') +
				'</div><ol class="flxan-fsteps">';
			f.steps.forEach(function (st, si) {
				var w = Math.max(28, 100 - si * (60 / Math.max(1, f.steps.length - 1)));
				h += '<li class="flxan-fs flxan-fs--' + st.type + '" draggable="false" data-fi="' + fi + '" data-si="' + si + '">' +
					'<span class="flxan-fs__grip" title="' + esc(t('Sürükleyip taşıyın')) + '" aria-hidden="true">⋮⋮</span>' +
					'<span class="flxan-fs__num" style="--w:' + w + '%">' + (si + 1) + '</span>' +
					'<div class="flxan-fs__body">' +
					'<input class="flxan-fs__label" data-f="label" data-fi="' + fi + '" data-si="' + si + '" value="' + esc(st.label) + '" placeholder="' + esc(t('Adımın adı')) + '" aria-label="' + esc(t('Adımın adı')) + '">' +
					'<div class="flxan-fs__row"><div class="flxan-seg" role="radiogroup" aria-label="' + esc(t('Adım türü')) + '">' +
					TYPES.map(function (ty) { return '<button type="button" role="radio" aria-checked="' + (st.type === ty[0]) + '" data-act="type" data-type="' + ty[0] + '" data-fi="' + fi + '" data-si="' + si + '">' + ico(ty[2]) + esc(ty[1]) + '</button>'; }).join('') +
					'</div>' + valueField(st, fi, si) + '</div></div>' +
					'<div class="flxan-fs__acts">' +
					'<button type="button" class="flxan-ico" data-act="up" data-fi="' + fi + '" data-si="' + si + '"' + (si ? '' : ' disabled') + ' aria-label="' + esc(t('Yukarı taşı')) + '" title="' + esc(t('Yukarı taşı')) + '">↑</button>' +
					'<button type="button" class="flxan-ico" data-act="down" data-fi="' + fi + '" data-si="' + si + '"' + (si < f.steps.length - 1 ? '' : ' disabled') + ' aria-label="' + esc(t('Aşağı taşı')) + '" title="' + esc(t('Aşağı taşı')) + '">↓</button>' +
					'<button type="button" class="flxan-ico flxan-ico--del" data-act="dels" data-fi="' + fi + '" data-si="' + si + '"' + (f.steps.length > 1 ? '' : ' disabled') + ' aria-label="' + esc(t('Adımı sil')) + '" title="' + esc(t('Adımı sil')) + '">✕</button>' +
					'</div></li>';
			});
			h += '</ol><div class="flxan-fcard__foot">';
			if (f.steps.length < MAXS) {
				h += '<button type="button" class="flxan-btn" data-act="adds" data-fi="' + fi + '">+ ' + esc(t('Adım ekle')) + '</button>';
				h += '<select class="flxan-fpreset" data-fi="' + fi + '" aria-label="' + esc(t('Hazır adım ekle')) + '"><option value="">' + esc(t('Hazır adım ekle…')) + '</option>' +
					PRESETS.map(function (p, i) { return '<option value="' + i + '">' + esc(p.label) + '</option>'; }).join('') + '</select>';
			} else {
				h += '<span class="flxan-muted">' + esc(fmt(t('En fazla %s adım.'), MAXS)) + '</span>';
			}
			h += '</div></div>';
		});
		h += '<div class="flxan-fed__foot">';
		if (F.length < MAXF) h += '<button type="button" class="flxan-btn" data-act="addf">+ ' + esc(t('Yeni huni')) + '</button>';
		h += '<button type="button" class="flxan-btn" data-act="reset">' + esc(t('Varsayılan bağış hunisine dön')) + '</button>';
		h += '<button type="button" class="flxan-btn flxan-btn--ghost" data-act="text">' + esc(t('Metin olarak düzenle')) + '</button></div>';
		var keep = document.activeElement && box.contains(document.activeElement) ? [document.activeElement.getAttribute('data-f'), document.activeElement.getAttribute('data-fi'), document.activeElement.getAttribute('data-si')] : null;
		Array.prototype.slice.call(box.children).forEach(function (c) { if (c !== dl) c.remove(); });
		var wrap = document.createElement('div');
		wrap.innerHTML = h;
		while (wrap.firstChild) box.appendChild(wrap.firstChild);
		if (keep && keep[0]) {
			var q = '[data-f="' + keep[0] + '"][data-fi="' + keep[1] + '"]' + (keep[2] !== null ? '[data-si="' + keep[2] + '"]' : '');
			var x = box.querySelector(q); if (x) x.focus();
		}
	}

	box.addEventListener('input', function (e) {
		var x = e.target, f = x.getAttribute('data-f');
		if (!f) return;
		var fi = +x.getAttribute('data-fi');
		if (f === 'name') { F[fi].name = x.value; return; }
		var st = F[fi].steps[+x.getAttribute('data-si')];
		if (f === 'label') { st.label = x.value; return; }
		if (f === 'value' && x.tagName === 'INPUT') {
			st.value = x.value.trim();
			var hint = x.parentNode.querySelector('.flxan-fval__hint');
			var title = pageTitle[st.value] || '';
			if (hint) hint.textContent = (title ? title + ' · ' : '') + t('bu yolla başlayan tüm sayfalar');
			if (title && (!st.label || st._auto)) { st.label = title; st._auto = 1; var li = x.closest('.flxan-fs'); li.querySelector('.flxan-fs__label').value = title; }
		}
	});
	box.addEventListener('change', async function (e) {
		var x = e.target;
		if (x.classList.contains('flxan-fpreset')) {
			if (x.value === '') return;
			var p = clone(PRESETS[+x.value]);
			F[+x.getAttribute('data-fi')].steps.push(p);
			render();
			return;
		}
		if (x.getAttribute('data-f') === 'value' && x.tagName === 'SELECT') {
			var st = F[+x.getAttribute('data-fi')].steps[+x.getAttribute('data-si')];
			var v = x.value;
			if (v === '__custom') {
				v = (await window.flxDialog.prompt({ title: t('Olay adı (ör. bagis_formu ya da checkout:submit):'), value: st.value || '' })) || st.value || '';
				v = v.trim().replace(/^olay:/, '');
			}
			if (v && (!st.label || st._auto)) { st.label = events[v] || v; st._auto = 1; }
			st.value = v;
			render();
		}
	});
	box.addEventListener('click', async function (e) {
		var b = e.target.closest('[data-act]');
		if (!b) return;
		var act = b.getAttribute('data-act'), fi = +b.getAttribute('data-fi'), si = +b.getAttribute('data-si');
		var steps = F[fi] ? F[fi].steps : null;
		if (act === 'type') {
			var st = steps[si], ty = b.getAttribute('data-type');
			if (st.type === ty) return;
			st.type = ty;
			st.value = ty === 'path' ? (st.value && st.value[0] === '/' ? st.value : '') : '';
			if (ty === 'any' && (!st.label || st._auto)) { st.label = t('Siteye giriş'); st._auto = 1; }
		} else if (act === 'up' && si > 0) {
			steps.splice(si - 1, 0, steps.splice(si, 1)[0]);
		} else if (act === 'down' && si < steps.length - 1) {
			steps.splice(si + 1, 0, steps.splice(si, 1)[0]);
		} else if (act === 'dels' && steps.length > 1) {
			steps.splice(si, 1);
		} else if (act === 'adds') {
			steps.push({ label: '', type: 'path', value: '' });
			render();
			var inps = box.querySelectorAll('.flxan-fcard[data-fi="' + fi + '"] .flxan-fval input');
			if (inps.length) inps[inps.length - 1].focus();
			return;
		} else if (act === 'delf') {
			if (!(await window.flxDialog.confirm({ message: fmt(t('"%s" hunisi silinsin mi?'), F[fi].name), danger: true }))) return;
			F.splice(fi, 1);
		} else if (act === 'addf') {
			F.push({ id: F.length + 1, name: fmt(t('Huni %s'), F.length + 1), steps: [{ label: t('Siteye giriş'), type: 'any', value: '' }, { label: '', type: 'path', value: '' }] });
		} else if (act === 'reset') {
			if (!(await window.flxDialog.confirm({ message: t('Tüm huniler varsayılan bağış hunisiyle değiştirilsin mi? (Kaydet\'e basınca uygulanır.)'), danger: true }))) return;
			F = clone(C.fdefault || []);
		} else if (act === 'text') {
			if (!text) return;
			var on = text.hidden;
			if (on) text.value = F.map(function (f) { return '# ' + f.name + '\n' + f.steps.map(function (s) { return s.label + ' | ' + (s.type === 'any' ? '*' : s.type === 'event' ? 'olay:' + s.value : s.value); }).join('\n'); }).join('\n\n');
			text.hidden = !on; text.disabled = !on;
			hidden.disabled = on;
			box.classList.toggle('flxan-fed--off', on);
			b.textContent = on ? t('Görsel düzenleyiciye dön') : t('Metin olarak düzenle');
			box.appendChild(b.parentNode);
			return;
		} else {
			return;
		}
		render();
	});

	// Sürükle-bırak (tutamaçtan).
	var drag = null;
	box.addEventListener('pointerdown', function (e) {
		var g = e.target.closest('.flxan-fs__grip');
		if (!g) return;
		var li = g.closest('.flxan-fs');
		li.setAttribute('draggable', 'true');
	});
	box.addEventListener('dragstart', function (e) {
		var li = e.target.closest && e.target.closest('.flxan-fs');
		if (!li) return;
		drag = { fi: +li.getAttribute('data-fi'), si: +li.getAttribute('data-si') };
		li.classList.add('dragging');
		try { e.dataTransfer.setData('text/plain', ''); e.dataTransfer.effectAllowed = 'move'; } catch (x) {}
	});
	box.addEventListener('dragover', function (e) {
		if (!drag) return;
		var li = e.target.closest('.flxan-fs');
		if (!li || +li.getAttribute('data-fi') !== drag.fi) return;
		e.preventDefault();
		box.querySelectorAll('.flxan-fs.over').forEach(function (x) { if (x !== li) x.classList.remove('over'); });
		li.classList.add('over');
	});
	box.addEventListener('drop', function (e) {
		if (!drag) return;
		var li = e.target.closest('.flxan-fs');
		if (li && +li.getAttribute('data-fi') === drag.fi) {
			e.preventDefault();
			var to = +li.getAttribute('data-si'), steps = F[drag.fi].steps;
			steps.splice(to, 0, steps.splice(drag.si, 1)[0]);
		}
		drag = null;
		render();
	});
	box.addEventListener('dragend', function () { if (drag) { drag = null; render(); } });

	render();
})();
