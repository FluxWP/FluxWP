/* Flux Certificates · Yeni sertifika (liste bazlı) + canlı önizleme */
(function () {
	'use strict';
	var A = window.FLXSA, R = window.FLXSRender, C = A.C, esc = A.esc;
	var root = document.getElementById('flxs-create');
	if (!root || !R) return;
	var DATA = JSON.parse(document.getElementById('flxs-create-data').textContent);
	var NOINPUT = ['static', 'qr', 'number_auto'];

	var state = {
		tpl: null,
		rows: [],
		common: {},
		active: 0,
		edit: DATA.edit,
		results: [],
		busy: false,
		dist: {}
	};
	try { state.dist = JSON.parse(localStorage.getItem('flxs_dist') || '{}') || {}; } catch (e) {}
	var uid = 0;

	function $(id) { return document.getElementById(id); }
	function inputFields() {
		return (state.tpl ? state.tpl.config.fields : []).filter(function (f) {
			return NOINPUT.indexOf(f.type) === -1 && f.key !== 'ad_soyad';
		});
	}
	function nameField() {
		return (state.tpl ? state.tpl.config.fields : []).filter(function (f) { return f.key === 'ad_soyad'; })[0];
	}
	function newRow(d) {
		d = d || {};
		return { uid: ++uid, person_id: d.person_id || 0, name: d.name || '', phone: d.phone || '', email: d.email || '', values: d.values || {}, result: null };
	}
	/* "Kişilere dağıt": ortak toplam, kendi değeri olmayan satırlara bölünür.
	   Elle girilen değerler toplamdan düşülür, artan ilk satırlara gider (3/2 → 2,1). */
	function distOn(key) {
		var f = inputFields().filter(function (x) { return x.key === key; })[0];
		return !!(f && f.type === 'number' && state.dist[key] && state.rows.length > 1 && !state.edit);
	}
	function distMap(key) {
		if (!distOn(key)) return null;
		var total = parseInt(state.common[key], 10);
		if (isNaN(total) || total < 0) return null;
		var own = 0, free = [], m = {};
		state.rows.forEach(function (r) {
			var x = r.values[key];
			if (x === undefined || x === '') free.push(r.uid); else own += parseInt(x, 10) || 0;
		});
		var left = Math.max(0, total - own);
		var base = free.length ? Math.floor(left / free.length) : 0, rem = free.length ? left % free.length : 0;
		free.forEach(function (id, i) { m[id] = String(base + (i < rem ? 1 : 0)); });
		return { map: m, total: total, own: own, left: left, free: free.length, zero: free.length > left };
	}
	/* Geçmiş tarihli oluşturma (yalnız yönetici, nadiren): seçilen tarih tarih alanlarının varsayılanı olur */
	function backdated() { var c = $('flxs-backdate'); return !!(c && c.checked && $('flxs-issued').value && $('flxs-issued').value < DATA.today); }
	function issued() { return backdated() ? $('flxs-issued').value : DATA.today; }
	var lastIssued = DATA.today;
	function onIssued() {
		var box = $('flxs-backdate-box'), now = issued();
		$('flxs-issued').disabled = !$('flxs-backdate').checked;
		box.classList.toggle('is-on', backdated());
		inputFields().forEach(function (f) {
			if (f.type === 'date' && (!state.common[f.key] || state.common[f.key] === lastIssued)) state.common[f.key] = now;
		});
		lastIssued = now;
		renderCommon();
		preview();
	}
	if ($('flxs-backdate')) {
		$('flxs-backdate').addEventListener('change', onIssued);
		$('flxs-issued').addEventListener('change', onIssued);
	}
	function rowValues(row) {
		var v = {};
		inputFields().forEach(function (f) {
			var x = row.values[f.key];
			if (x === undefined || x === '') { var d = distMap(f.key); if (d) x = d.map[row.uid]; }
			if (x === undefined || x === '') x = state.common[f.key];
			if (x === undefined || x === '') x = f.default || '';
			if (f.type === 'date' && !x) x = issued();
			v[f.key] = x;
		});
		v.ad_soyad = row.name;
		return v;
	}

	/* ---------------- Şablon seçimi ---------------- */
	function renderTemplates() {
		var box = $('flxs-tpls');
		box.innerHTML = DATA.templates.map(function (t) {
			var on = state.tpl && state.tpl.id === t.id;
			return '<button type="button" class="flxs-tpl' + (on ? ' is-on' : '') + '" data-tpl="' + esc(t.id) + '"' + (state.edit && !on ? ' disabled' : '') + '>' +
				(t.thumb ? '<img src="' + esc(t.thumb) + '" alt="">' : '<span class="flxs-tpl__ph dashicons dashicons-awards"></span>') +
				'<span>' + esc(t.name) + '</span></button>';
		}).join('');
	}
	$('flxs-tpls').addEventListener('click', function (e) {
		var b = e.target.closest('[data-tpl]');
		if (!b || state.edit) return;
		selectTemplate(+b.getAttribute('data-tpl'));
	});
	function selectTemplate(id) {
		var t = DATA.templates.filter(function (x) { return x.id === id; })[0] || DATA.templates[0];
		state.tpl = t;
		try { localStorage.setItem('flxs_tpl', t.id); } catch (e) {}
		seedCommon();
		renderTemplates();
		renderCommon();
		renderRows();
		preview();
	}

	/* Ortak alanların başlangıç değerleri (fidan sayısı varsayılan 1) */
	function seedCommon() {
		inputFields().forEach(function (f) {
			if (state.common[f.key] !== undefined) return;
			var d = f.default || '';
			if (f.type === 'date') d = d || issued();
			if (!d && f.type === 'number' && /fidan/.test(f.key)) d = '1';
			state.common[f.key] = d;
		});
	}

	/* ---------------- Ortak alanlar ---------------- */
	function fieldInput(f, value, attrs) {
		var a = attrs || '';
		var ph = esc(f.placeholder || '');
		if (f.type === 'textarea') return '<textarea ' + a + ' rows="2" placeholder="' + ph + '">' + esc(value) + '</textarea>';
		if (f.type === 'select') {
			var opts = String(f.options || '').split('\n').map(function (o) { return o.trim(); }).filter(Boolean);
			return '<select ' + a + '><option value=""></option>' + opts.map(function (o) {
				return '<option' + (o === value ? ' selected' : '') + '>' + esc(o) + '</option>';
			}).join('') + '</select>';
		}
		var type = f.type === 'date' ? 'date' : (f.type === 'number' ? 'number' : 'text');
		return '<input type="' + type + '" ' + a + ' value="' + esc(value) + '" placeholder="' + ph + '"' + (type === 'number' ? ' min="0" inputmode="numeric"' : '') + '>';
	}
	function renderCommon() {
		var fs = inputFields();
		var box = $('flxs-common');
		if (!fs.length) { box.innerHTML = ''; return; }
		var multi = state.rows.length > 1 && !state.edit;
		box.innerHTML = '<div class="flxs-common__title">Ortak bilgiler <small>' + (state.rows.length > 1 ? 'tüm kişilere uygulanır; kişi satırında farklı değer yazarsanız o geçerli olur' : '') + '</small></div>' +
			'<div class="flxs-grid">' + fs.map(function (f) {
				var dist = multi && f.type === 'number';
				return '<div class="flxs-field' + (dist ? ' flxs-field--dist' : '') + '"><label><span>' + esc(f.label) + (f.required ? ' *' : '') + '</span>' + fieldInput(f, state.common[f.key] || '', 'data-common="' + esc(f.key) + '"') + '</label>' +
					(dist ? '<label class="flxs-dist"><input type="checkbox" data-dist="' + esc(f.key) + '"' + (state.dist[f.key] ? ' checked' : '') + '> Kişilere dağıt</label><small class="flxs-dist__info" data-dist-info="' + esc(f.key) + '"></small>' : '') +
					'</div>';
			}).join('') + '</div>';
		syncDist();
	}
	function syncDist() {
		document.querySelectorAll('#flxs-common [data-dist-info]').forEach(function (el) {
			var k = el.getAttribute('data-dist-info');
			var d = distMap(k);
			el.classList.remove('is-warn');
			if (!distOn(k)) { el.textContent = 'Toplam sayıyı kişilere böler.'; return; }
			if (!d) { el.textContent = 'Toplam sayıyı yazın.'; return; }
			if (!d.free) { el.textContent = 'Herkese elle değer girilmiş.'; return; }
			var groups = {};
			Object.keys(d.map).forEach(function (id) { groups[d.map[id]] = (groups[d.map[id]] || 0) + 1; });
			var txt = Object.keys(groups).sort(function (a, b) { return b - a; }).map(function (v) { return groups[v] + ' kişiye ' + v; }).join(', ');
			if (d.own) txt += ' (elle girilen ' + d.own + ' hariç)';
			if (d.own > d.total) txt = 'Elle girilenler (' + d.own + ') toplamı aşıyor. ' + txt;
			if (d.zero) { txt += '. Bazı kişilere 0 düşüyor!'; el.classList.add('is-warn'); }
			el.textContent = txt;
		});
	}
	$('flxs-common').addEventListener('input', function (e) {
		var k = e.target.getAttribute('data-common');
		if (!k) return;
		state.common[k] = e.target.value;
		syncRowPlaceholders();
		preview();
	});
	$('flxs-common').addEventListener('change', function (e) {
		var k = e.target.getAttribute('data-dist');
		if (!k) return;
		state.dist[k] = e.target.checked;
		try { localStorage.setItem('flxs_dist', JSON.stringify(state.dist)); } catch (er) {}
		syncRowPlaceholders();
		preview();
	});

	/* ---------------- Kişi satırları ---------------- */
	function renderRows() {
		var box = $('flxs-rows');
		var fs = inputFields();
		var nf = nameField();
		$('flxs-row-count').textContent = state.rows.length > 1 ? state.rows.length + ' kişi' : '';
		box.innerHTML = state.rows.map(function (r, i) {
			var res = r.result;
			return '<div class="flxs-row' + (i === state.active ? ' is-active' : '') + (res ? ' is-done' : '') + '" data-row="' + r.uid + '">' +
				'<div class="flxs-row__num">' + (i + 1) + '</div>' +
				'<div class="flxs-row__body">' +
				'<div class="flxs-row__main">' +
				'<label class="flxs-field flxs-field--name"><span>' + esc(nf ? nf.label : 'Ad Soyad') + ' *</span><input type="text" data-k="name" value="' + esc(r.name) + '" placeholder="' + esc(nf && nf.placeholder ? nf.placeholder : 'Ad Soyad') + '" autocomplete="off"></label>' +
				'<label class="flxs-field"><span>Telefon</span><input type="tel" data-k="phone" value="' + esc(r.phone) + '" placeholder="0532 123 45 67" autocomplete="off" inputmode="tel"><small class="flxs-hint" data-hint="phone"></small></label>' +
				'<label class="flxs-field"><span>E-posta</span><input type="email" data-k="email" value="' + esc(r.email) + '" placeholder="ornek@eposta.com" autocomplete="off" inputmode="email"><small class="flxs-hint" data-hint="email"></small></label>' +
				'</div>' +
				(fs.length && (state.rows.length > 1 || state.edit) ? '<div class="flxs-row__extra">' + fs.map(function (f) {
					return '<label class="flxs-field flxs-field--sm"><span>' + esc(f.label) + '</span>' + fieldInput(f, r.values[f.key] || '', 'data-v="' + esc(f.key) + '"') + '</label>';
				}).join('') + '</div>' : '') +
				(r.person_id ? '<div class="flxs-row__person"><span class="dashicons dashicons-id"></span> Kayıtlı kişi #' + r.person_id + ' <button type="button" class="button-link" data-unlink>bağlantıyı kaldır</button></div>' : '') +
				'<div class="flxs-suggest" hidden></div>' +
				'</div>' +
				(state.edit ? '' : '<button type="button" class="flxs-row__del" data-del title="Satırı sil" aria-label="Satırı sil"><span class="dashicons dashicons-no-alt"></span></button>') +
				'</div>';
		}).join('');
		syncRowPlaceholders();
		state.rows.forEach(function (r) { checkContact(r, 'phone', true); checkContact(r, 'email', true); });
		$('flxs-submit').textContent = state.edit ? 'Kaydet ve PDF\'i yenile' : (state.rows.length > 1 ? state.rows.length + ' sertifika oluştur' : 'Sertifikayı oluştur');
		updateNav();
	}
	function syncRowPlaceholders() {
		var dm = {};
		document.querySelectorAll('#flxs-rows [data-v]').forEach(function (inp) {
			var k = inp.getAttribute('data-v');
			if (inp.tagName !== 'INPUT' || inp.type === 'date') return;
			if (dm[k] === undefined) dm[k] = distMap(k);
			var r = rowByEl(inp);
			if (dm[k] && r && dm[k].map[r.row.uid] !== undefined) inp.placeholder = 'dağıtılan: ' + dm[k].map[r.row.uid];
			else inp.placeholder = state.common[k] ? 'ortak: ' + state.common[k] : '';
		});
		syncDist();
	}

	/* Telefon / e-posta: yazmayı bırakınca (500 ms) altında doğru mu yazar; kayıtlıysa kimin olduğunu gösterir. */
	var hintTimers = {}, lookCache = {};
	function checkContact(r, k, now) {
		var key = r.uid + k;
		clearTimeout(hintTimers[key]);
		var run = function () {
			var el = document.querySelector('#flxs-rows [data-row="' + r.uid + '"] [data-hint="' + k + '"]');
			if (!el) return;
			var v = String(r[k] || '').trim();
			el.className = 'flxs-hint';
			if (!v) { el.innerHTML = ''; return; }
			var norm = k === 'phone' ? A.normPhone(v) : (A.validEmail(v) ? v.toLowerCase() : '');
			if (!norm) {
				el.classList.add('is-err');
				el.textContent = k === 'phone' ? '✗ Numara eksik ya da hatalı' : '✗ E-posta hatalı';
				return;
			}
			el.classList.add('is-ok');
			el.textContent = '✓ ' + (k === 'phone' ? A.fmtPhone(norm) : 'Geçerli e-posta');
			if (r.person_id) return;
			var show = function (p) {
				if (!p || String(r[k] || '').trim() !== v) return;
				el.innerHTML = esc(el.textContent) + ' · Kayıtlı: <button type="button" class="button-link" data-person="' + esc(JSON.stringify(p)) + '" title="Kayıtlı kişiyi kullan">' + esc(p.name) + '</button>';
			};
			if (norm in lookCache) { show(lookCache[norm]); return; }
			if (now) return;
			A.api('GET', 'people?search=' + encodeURIComponent(norm)).then(function (list) {
				var p = (list || []).filter(function (x) { return k === 'phone' ? x.phone === norm : String(x.email).toLowerCase() === norm; })[0] || null;
				lookCache[norm] = p;
				show(p);
			}).catch(function () {});
		};
		if (now) run(); else hintTimers[key] = setTimeout(run, 500);
	}
	function rowByEl(el) {
		var r = el.closest('[data-row]');
		if (!r) return null;
		var id = +r.getAttribute('data-row');
		for (var i = 0; i < state.rows.length; i++) if (state.rows[i].uid === id) return { row: state.rows[i], index: i, el: r };
		return null;
	}
	function setActive(i, render) {
		if (i === state.active) return;
		state.active = i;
		document.querySelectorAll('#flxs-rows .flxs-row').forEach(function (el, j) { el.classList.toggle('is-active', j === i); });
		updateNav();
		if (render !== false) preview();
	}

	var rows = $('flxs-rows');
	rows.addEventListener('focusin', function (e) {
		var r = rowByEl(e.target);
		if (r) setActive(r.index);
	});
	rows.addEventListener('input', function (e) {
		var r = rowByEl(e.target);
		if (!r) return;
		var k = e.target.getAttribute('data-k');
		if (k) {
			r.row[k] = e.target.value;
			if (r.row.person_id) { r.row.person_id = 0; var p = r.el.querySelector('.flxs-row__person'); if (p) p.remove(); }
			if (k === 'name') suggest(r, e.target.value);
			else {
				var h = r.el.querySelector('[data-hint="' + k + '"]');
				if (h) { h.className = 'flxs-hint is-wait'; h.textContent = '…'; }
				checkContact(r.row, k);
			}
		}
		var v = e.target.getAttribute('data-v');
		if (v) { r.row.values[v] = e.target.value; syncRowPlaceholders(); }
		preview();
	});
	rows.addEventListener('click', function (e) {
		var r = rowByEl(e.target);
		if (!r) return;
		if (e.target.closest('[data-del]')) {
			state.rows.splice(r.index, 1);
			if (!state.rows.length) state.rows.push(newRow());
			state.active = Math.min(state.active, state.rows.length - 1);
			renderRows(); renderCommon(); preview();
			return;
		}
		if (e.target.closest('[data-unlink]')) {
			r.row.person_id = 0;
			renderRows();
			return;
		}
		var s = e.target.closest('[data-person]');
		if (s) {
			var p = JSON.parse(s.getAttribute('data-person'));
			r.row.person_id = p.id; r.row.name = p.name; r.row.phone = p.phone ? '0' + p.phone.replace(/^90/, '') : ''; r.row.email = p.email;
			if (p.phone && p.phone.indexOf('90') !== 0) r.row.phone = '+' + p.phone;
			renderRows();
			preview();
		}
	});
	document.addEventListener('click', function (e) {
		if (!e.target.closest('.flxs-suggest')) document.querySelectorAll('.flxs-suggest').forEach(function (s) { s.hidden = true; });
	});

	var suggest = A.debounce(function (r, term) {
		var box = r.el.querySelector('.flxs-suggest');
		if (!box) return;
		if (String(term).trim().length < 3) { box.hidden = true; return; }
		A.api('GET', 'people?search=' + encodeURIComponent(term)).then(function (list) {
			if (!list.length) { box.hidden = true; return; }
			box.innerHTML = '<div class="flxs-suggest__title">Kayıtlı kişiler</div>' + list.map(function (p) {
				return '<button type="button" data-person="' + esc(JSON.stringify(p)) + '"><strong>' + esc(p.name) + '</strong> <span>' + esc([p.phone_label, p.email].filter(Boolean).join(' · ')) + '</span></button>';
			}).join('');
			box.hidden = false;
		}).catch(function () {});
	}, 300);

	if ($('flxs-add-row')) {
		$('flxs-add-row').addEventListener('click', function () {
			state.rows.push(newRow());
			state.active = state.rows.length - 1;
			renderRows();
			renderCommon();
			preview();
			var last = rows.querySelector('.flxs-row:last-child [data-k=name]');
			if (last) last.focus();
		});
	}

	/* Listeden yapıştır: Ad Soyad; telefon; e-posta; ek alanlar... */
	if ($('flxs-paste-toggle')) {
		$('flxs-paste-toggle').addEventListener('click', function () { $('flxs-paste').hidden = !$('flxs-paste').hidden; });
		$('flxs-paste-apply').addEventListener('click', function () {
			var fs = inputFields();
			var added = 0;
			$('flxs-paste-text').value.split(/\r?\n/).forEach(function (line) {
				if (!line.trim()) return;
				var parts = line.split(/\t|;|,(?=\s*[^\s])/).map(function (s) { return s.trim(); });
				var d = { name: '', phone: '', email: '', values: {} };
				var rest = [];
				parts.forEach(function (p) {
					if (!p) return;
					if (!d.email && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(p)) d.email = p;
					else if (!d.phone && /^[+(]?[\d\s().-]+$/.test(p) && p.replace(/\D/g, '').length >= 7) d.phone = p;
					else if (!d.name) d.name = p;
					else rest.push(p);
				});
				fs.forEach(function (f, i) { if (rest[i] !== undefined) d.values[f.key] = rest[i]; });
				if (d.name) { state.rows.push(newRow(d)); added++; }
			});
			state.rows = state.rows.filter(function (r) { return r.name || r.phone || r.email; });
			if (!state.rows.length) state.rows.push(newRow());
			$('flxs-paste-text').value = '';
			$('flxs-paste').hidden = true;
			renderRows(); renderCommon(); preview();
			A.toast(added + ' kişi eklendi');
		});
	}

	/* ---------------- Gönderim seçenekleri ---------------- */
	function renderSend() {
		var box = $('flxs-send');
		box.innerHTML = ['whatsapp', 'sms', 'email'].map(function (ch) {
			var list = C.providers[ch] || [];
			var none = !list.length;
			return '<div class="flxs-send__item' + (none ? ' is-off' : '') + '">' +
				'<label><input type="checkbox" data-ch="' + ch + '"' + (none ? ' disabled' : '') + '> <span class="flxs-ch flxs-ch--' + ch + '"></span> ' + esc(C.channels[ch]) + '</label>' +
				(none ? '<small>' + esc(C.connNone || 'bağlantı yok') + (C.manage && C.connUrl ? ' · <a href="' + C.connUrl + '">' + esc(C.connAdd || 'ekle') + '</a>' : '') + '</small>' :
					(list.length > 1 ? '<select data-prov="' + ch + '"><option value="0">Varsayılan</option>' + list.filter(function (p) { return p.id; }).map(function (p) {
						return '<option value="' + p.id + '">' + esc(p.name) + '</option>';
					}).join('') + '</select>' : '<small>' + esc(list[0].name) + '</small>')) +
				'</div>';
		}).join('');
		// Son kullanılan kanal(lar) ve sağlayıcı hep seçili gelir; hiç seçim yoksa ilk tanımlı kanal açılır.
		var saved = null, prov = {};
		try { saved = JSON.parse(localStorage.getItem('flxs_send') || 'null'); prov = JSON.parse(localStorage.getItem('flxs_send_prov') || '{}') || {}; } catch (e) {}
		if (!saved || !saved.length) {
			var first = box.querySelector('[data-ch]:not([disabled])');
			saved = first && !state.edit ? [first.getAttribute('data-ch')] : [];
		}
		saved.forEach(function (ch) { var cb = box.querySelector('[data-ch=' + ch + ']'); if (cb && !cb.disabled) cb.checked = true; });
		box.querySelectorAll('[data-prov]').forEach(function (s) { var v = prov[s.getAttribute('data-prov')]; if (v && s.querySelector('option[value="' + v + '"]')) s.value = String(v); });
		box.addEventListener('change', function () {
			try {
				localStorage.setItem('flxs_send', JSON.stringify(sendChannels()));
				localStorage.setItem('flxs_send_prov', JSON.stringify(sendProviders()));
			} catch (e) {}
		});
	}
	function sendChannels() {
		return Array.prototype.map.call(document.querySelectorAll('#flxs-send [data-ch]:checked'), function (c) { return c.getAttribute('data-ch'); });
	}
	function sendProviders() {
		var o = {};
		document.querySelectorAll('#flxs-send [data-prov]').forEach(function (s) { o[s.getAttribute('data-prov')] = +s.value; });
		return o;
	}

	/* ---------------- Önizleme ---------------- */
	var canvas = $('flxs-canvas');
	var renderToken = 0;
	var lastBytes = null;
	function metaFor(row) {
		if (row && row.result) return { number: row.result.number, link: row.result.url, code: row.result.code, created: issued() };
		if (state.edit) return { number: state.edit.number, link: state.edit.url, code: state.edit.code, created: DATA.today };
		return {};
	}
	var lastSig = '';
	var preview = A.debounce(function () {
		if (!state.tpl) return;
		var row = state.rows[state.active] || newRow();
		var stageW = canvas.parentNode.clientWidth || 600;
		// Sertifikada görünen hiçbir şey değişmediyse (telefon, e-posta vb.) yeniden çizme
		var sig = JSON.stringify([state.tpl.id, rowValues(row), metaFor(row), !row.result && !state.edit, stageW]);
		if (sig === lastSig) return;
		lastSig = sig;
		var token = ++renderToken;
		$('flxs-spin').classList.add('is-on');
		var stage = canvas.parentNode;
		R.renderPdf(state.tpl.config, rowValues(row), metaFor(row), { preview: !row.result && !state.edit }).then(function (bytes) {
			if (token !== renderToken) return null;
			lastBytes = bytes;
			return R.renderToCanvas(bytes, canvas, stage.clientWidth || 600);
		}).then(function () {
			if (token !== renderToken) return;
			$('flxs-preview-err').hidden = true;
		}).catch(function (e) {
			lastSig = '';
			$('flxs-preview-err').hidden = false;
			$('flxs-preview-err').textContent = 'Önizleme hatası: ' + e.message;
		}).finally(function () {
			if (token === renderToken) $('flxs-spin').classList.remove('is-on');
		});
	}, 450);
	window.addEventListener('resize', A.debounce(preview, 300));

	function updateNav() {
		$('flxs-preview-pos').textContent = (state.active + 1) + ' / ' + state.rows.length;
	}
	document.querySelector('.flxs-preview__nav').addEventListener('click', function (e) {
		var b = e.target.closest('[data-nav]');
		if (!b) return;
		var n = state.active + (+b.getAttribute('data-nav'));
		if (n >= 0 && n < state.rows.length) setActive(n);
	});
	$('flxs-preview-pdf').addEventListener('click', function () {
		if (lastBytes) A.download(new Blob([lastBytes], { type: 'application/pdf' }), 'onizleme.pdf');
	});

	/* ---------------- Kaydet ---------------- */
	function validate() {
		var errs = [];
		state.rows.forEach(function (r, i) {
			var n = state.rows.length > 1 ? (i + 1) + '. kişi: ' : '';
			if (!r.name.trim()) errs.push(n + 'ad soyad boş');
			if (String(r.phone || '').trim() && !A.normPhone(r.phone)) errs.push(n + 'telefon hatalı');
			if (String(r.email || '').trim() && !A.validEmail(r.email)) errs.push(n + 'e-posta hatalı');
			var v = rowValues(r);
			inputFields().forEach(function (f) {
				if (f.required && !String(v[f.key] || '').trim()) errs.push(n + f.label + ' boş');
				else if (distOn(f.key) && String(v[f.key]) === '0') errs.push(n + f.label + ' 0 (dağıtılacak sayı yetmiyor)');
			});
		});
		return errs;
	}

	function imageWidth() {
		var p = state.tpl.config.page || {};
		return (+p.w >= +p.h) ? 1600 : 1100; // WhatsApp önizleme için ~200-300 KB
	}

	/* Bir kişinin sertifikası: kaydet → PDF çiz → görselleri yükle → gönderime al.
	   job.tpl ve row.snap gönderim anında alınır; form temizlense de arka planda doğru veriyle sürer. */
	function processRow(job, row, step) {
		var tpl = job.tpl;
		var values = row.snap;
		var body = { template_id: tpl.id, person: { id: row.person_id, name: row.name, phone: row.phone, email: row.email }, values: values, ref_user: job.ref, event: job.event };
		if (job.issued && !job.edit) body.issued = job.issued;
		var path = job.edit ? 'certificates/' + job.edit.id : 'certificates';
		var cert, bytes;
		step('save');
		return A.api('POST', path, body).then(function (r) {
			cert = r.certificate;
			row.person_id = r.person.id;
			if (r.notes && r.notes.length) A.toast(r.notes.join(' '), 'warn');
			step('pdf');
			return R.renderPdf(tpl.config, values, { number: cert.number, link: cert.url, code: cert.code, created: job.issued || DATA.today });
		}).then(function (b) {
			bytes = b;
			var pg = tpl.config.page || {};
			return R.toJpeg(b, (+pg.w >= +pg.h) ? 1600 : 1100, 0.82);
		}).then(function (img) {
			var fd = new FormData();
			fd.append('pdf', new Blob([bytes], { type: 'application/pdf' }), 'sertifika.pdf');
			fd.append('image', img.blob, 'sertifika.jpg');
			fd.append('w', img.w);
			fd.append('h', img.h);
			// 1200x630 link önizleme görseli (WhatsApp/Facebook/X için)
			return R.toShareImage(bytes, { title: cert.title, name: row.name, org: (window.FLXS_RENDER || {}).org }).then(function (og) {
				fd.append('og', og.blob, 'paylasim.jpg');
				return fd;
			}, function () { return fd; });
		}).then(function (fd) {
			step('upload');
			return A.api('POST', 'certificates/' + cert.id + '/files', fd);
		}).then(function (r) {
			cert = r.certificate;
			if (!job.channels.length) return { results: {} };
			step('send');
			return A.api('POST', 'certificates/' + cert.id + '/send', { channels: job.channels, providers: job.providers, source: 'create' });
		}).then(function (s) {
			row.result = cert;
			row.result.bytes = bytes;
			row.result.messages = s.results || {};
			row.result.row = { name: row.name, phone: row.phone };
			return row.result;
		});
	}

	/* ---------------- İşler (ilerleme ekranı + arka plan) ---------------- */
	var STEP = { wait: 'Sırada', save: 'Kaydediliyor', pdf: 'PDF hazırlanıyor', upload: 'Yükleniyor', send: 'Gönderime alınıyor', done: 'Hazır', err: 'Hata' };
	var jobs = [];
	var jobSeq = 0;
	var viewJob = null; // ilerleme penceresinde gösterilen iş

	var modal = document.createElement('div');
	modal.className = 'flxs-job';
	modal.hidden = true;
	modal.innerHTML = '<div class="flxs-job__box" role="dialog" aria-modal="true" aria-labelledby="flxs-job-title">' +
		'<div class="flxs-job__head"><span class="flxs-job__spin"></span><h2 id="flxs-job-title"></h2></div>' +
		'<div class="flxs-job__bar"><span></span></div>' +
		'<ol class="flxs-job__list"></ol>' +
		'<div class="flxs-job__foot">' +
		'<button type="button" class="button button-primary" data-job="bg">Arka planda devam et, yeni liste aç</button>' +
		'<button type="button" class="button" data-job="hide">Gizle</button>' +
		'<button type="button" class="button button-primary" data-job="close" hidden>Sonuçları gör</button>' +
		'</div><p class="flxs-job__note">Arka planda devam ederken bu sekmeyi kapatmayın. Mesajlar sıraya alındıktan sonra sunucudan gönderilir.</p></div>';
	document.body.appendChild(modal);

	var tray = document.createElement('div');
	tray.className = 'flxs-tray';
	document.body.appendChild(tray);

	function jobCounts(j) {
		var c = { done: 0, err: 0, total: j.rows.length };
		j.rows.forEach(function (r) { if (r.step === 'done') c.done++; else if (r.step === 'err') c.err++; });
		c.finished = c.done + c.err;
		return c;
	}
	function renderModal() {
		var j = viewJob;
		if (!j) { modal.hidden = true; return; }
		var c = jobCounts(j);
		var running = !j.finished;
		modal.hidden = false;
		modal.classList.toggle('is-done', !running);
		modal.querySelector('h2').textContent = running ?
			'Sertifikalar hazırlanıyor · ' + c.finished + ' / ' + c.total :
			c.done + ' sertifika hazır' + (c.err ? ' · ' + c.err + ' hata' : '');
		modal.querySelector('.flxs-job__bar span').style.width = Math.round((c.finished / c.total) * 100) + '%';
		modal.querySelector('.flxs-job__list').innerHTML = j.rows.map(function (r) {
			return '<li class="is-' + r.step + '"><span class="flxs-job__dot"></span><strong>' + esc(r.name) + '</strong><em>' + esc(STEP[r.step] + (r.error ? ': ' + r.error : '')) + '</em></li>';
		}).join('');
		modal.querySelector('[data-job="bg"]').hidden = !running || j.edit || j.detached;
		modal.querySelector('[data-job="hide"]').hidden = !running;
		modal.querySelector('[data-job="close"]').hidden = running;
	}
	function renderTray() {
		var live = jobs.filter(function (j) { return j.detached || j.hidden; });
		if (!live.length) { tray.innerHTML = ''; return; }
		tray.innerHTML = live.map(function (j) {
			var c = jobCounts(j);
			var txt = j.finished ? '✓ ' + c.done + ' sertifika hazır' + (c.err ? ' · ' + c.err + ' hata' : '') : 'Hazırlanıyor ' + c.finished + ' / ' + c.total;
			return '<div class="flxs-tray__item' + (j.finished ? ' is-done' : '') + (c.err && j.finished ? ' has-err' : '') + '">' +
				(j.finished ? '' : '<span class="flxs-job__spin"></span>') +
				'<button type="button" class="flxs-tray__open" data-tray="' + j.id + '">' + esc(txt) + '</button>' +
				(j.finished ? '<button type="button" class="flxs-tray__x" data-tray-x="' + j.id + '" aria-label="Kapat">×</button>' : '') + '</div>';
		}).join('');
	}
	function refreshJobs() { renderModal(); renderTray(); }
	tray.addEventListener('click', function (e) {
		var x = e.target.closest('[data-tray-x]');
		if (x) { jobs = jobs.filter(function (j) { return String(j.id) !== x.getAttribute('data-tray-x'); }); renderTray(); return; }
		var o = e.target.closest('[data-tray]');
		if (!o) return;
		var j = jobs.filter(function (k) { return String(k.id) === o.getAttribute('data-tray'); })[0];
		if (!j) return;
		if (j.finished && j.detached) {
			// Arka plandaki iş bitti: sonuçları ekranda göster
			jobs = jobs.filter(function (k) { return k !== j; });
			renderResults(true);
			renderTray();
			return;
		}
		j.hidden = false;
		viewJob = j;
		refreshJobs();
	});
	modal.addEventListener('click', function (e) {
		var b = e.target.closest('[data-job]');
		if (!b || !viewJob) return;
		var act = b.getAttribute('data-job');
		var j = viewJob;
		if (act === 'bg') {
			j.detached = true;
			viewJob = null;
			state.current = null;
			resetForm();
			A.toast('Arka planda devam ediyor. Yeni listeyi girebilirsiniz.');
		} else if (act === 'hide') {
			if (j.detached) { viewJob = null; } else { j.hidden = true; viewJob = null; }
		} else if (act === 'close') {
			viewJob = null;
			if (j.detached || j.hidden) { jobs = jobs.filter(function (k) { return k !== j; }); renderResults(true); }
			else jobs = jobs.filter(function (k) { return k !== j; });
		}
		refreshJobs();
	});
	window.addEventListener('beforeunload', function (e) {
		if (jobs.some(function (j) { return !j.finished; })) { e.preventDefault(); e.returnValue = ''; return ''; }
	});

	function runJob(job) {
		var chain = Promise.resolve();
		job.rows.forEach(function (row) {
			chain = chain.then(function () {
				if (!job.detached && state.current === job) setActive(state.rows.indexOf(row), false);
				return processRow(job, row, function (st) { row.step = st; refreshJobs(); }).then(function (res) {
					row.step = 'done'; row.error = '';
					job.done.push(res);
				}).catch(function (e) {
					row.step = 'err'; row.error = e.message;
				}).then(refreshJobs);
			});
		});
		return chain.then(function () {
			job.finished = true;
			state.results = state.results.concat(job.done);
			if (job.edit) {
				state.results = job.done;
				if (job.done.length) state.edit = Object.assign(state.edit, job.done[0]);
			}
			if (job.done.some(function (r) { return Object.keys(r.messages || {}).length; })) pump();
			if (state.current === job) {
				state.current = null;
				$('flxs-submit').disabled = false;
				var c = jobCounts(job);
				$('flxs-progress').textContent = c.done + ' sertifika hazır' + (c.err ? ' · ' + c.err + ' hata' : '');
				renderRows();
				preview();
				renderResults();
				// hatasızsa pencere kendiliğinden kapanır
				if (!c.err && viewJob === job) { viewJob = null; jobs = jobs.filter(function (k) { return k !== job; }); }
				else if (!c.err && job.hidden) { jobs = jobs.filter(function (k) { return k !== job; }); }
			}
			refreshJobs();
		});
	}

	$('flxs-submit').addEventListener('click', function () {
		if (state.current) { viewJob = state.current; state.current.hidden = false; refreshJobs(); return; }
		var errs = validate();
		if (errs.length) { A.toast(errs.slice(0, 3).join(' · '), 'err'); return; }
		var channels = sendChannels();
		var providers = sendProviders();
		if (channels.length && !state.edit && !window.confirm(state.rows.length + ' kişiye ' + channels.map(function (c) { return C.channels[c]; }).join(' + ') + ' ile gönderilecek. Devam?')) return;
		var todo = state.rows.filter(function (r) { return !r.result || state.edit; });
		if (!todo.length) { A.toast('Bu listedeki sertifikalar zaten oluşturuldu. Yeni liste için Temizle.', 'warn'); return; }
		todo.forEach(function (r) { r.snap = rowValues(r); r.step = 'wait'; r.error = ''; });
		if (backdated() && !window.confirm('Bu sertifikalar ' + issued().split('-').reverse().join('.') + ' tarihli oluşturulacak. Devam?')) return;
		var job = { id: ++jobSeq, issued: backdated() ? issued() : '', ref: +$('flxs-ref').value || 0, event: +$('flxs-event').value || 0, tpl: state.tpl, edit: state.edit, channels: channels, providers: providers, rows: todo, done: [], finished: false, detached: false, hidden: false };
		jobs.push(job);
		state.current = job;
		viewJob = job;
		$('flxs-submit').disabled = false; // tekrar basınca ilerleme penceresi açılır
		$('flxs-progress').textContent = '';
		refreshJobs();
		runJob(job);
	});

	/* ---------------- Sonuçlar ---------------- */
	function waLink(r) {
		var p = String(r.row.phone || '').replace(/\D/g, '');
		if (p.length === 11 && p[0] === '0') p = '90' + p.slice(1);
		else if (p.length === 10 && p[0] === '5') p = '90' + p;
		var text = 'Merhaba ' + r.row.name.split(' ')[0] + ', ' + r.title + ' hazır: ' + r.url;
		return 'https://wa.me/' + p + '?text=' + encodeURIComponent(text);
	}
	function msgChips(r) {
		var m = r.messages || {};
		return Object.keys(m).map(function (ch) {
			var x = m[ch];
			var st = x.status || (x.ok ? 'queued' : 'failed');
			return '<span class="flxs-pill flxs-pill--' + st + '" data-mid="' + (x.message_id || '') + '" title="' + esc(x.message || x.last_error || '') + '">' + esc(C.channels[ch]) + ': ' + esc(C.statuses[st] || st) + '</span>';
		}).join(' ');
	}
	function renderResults(scroll) {
		if (!state.results.length) { $('flxs-results').hidden = true; return; }
		$('flxs-results').hidden = false;
		$('flxs-merge').hidden = state.results.length < 2;
		$('flxs-result-list').innerHTML = state.results.map(function (r) {
			return '<div class="flxs-res">' +
				(r.image_url ? '<img src="' + esc(r.image_url) + '" alt="">' : '') +
				'<div class="flxs-res__info"><strong>' + esc(r.row.name) + '</strong><span>' + esc(r.number) + '</span>' +
				'<a href="' + esc(r.url) + '" target="_blank" class="flxs-res__link">' + esc(r.url.replace(/^https?:\/\//, '')) + '</a>' +
				'<div class="flxs-res__msgs">' + msgChips(r) + '</div></div>' +
				'<div class="flxs-res__actions">' +
				'<button type="button" class="button" data-flxs-copy="' + esc(r.url) + '">Kopyala</button>' +
				(r.row.phone ? '<a class="button" target="_blank" rel="noopener" href="' + esc(waLink(r)) + '">WhatsApp\'ta aç</a>' : '') +
				'<a class="button" target="_blank" href="' + esc(r.pdf_url) + '">PDF</a>' +
				'<button type="button" class="button" data-forward="' + esc(r.id) + '">Başka birine gönder</button>' +
				'</div></div>';
		}).join('');
	}
	$('flxs-copy-all').addEventListener('click', function () {
		A.copy(state.results.map(function (r) { return r.row.name + ': ' + r.url; }).join('\n'));
	});
	$('flxs-merge').addEventListener('click', function () {
		var out;
		R.ready().then(function () { return window.PDFLib.PDFDocument.create(); }).then(function (d) {
			out = d;
			return state.results.reduce(function (p, r) {
				return p.then(function () {
					return window.PDFLib.PDFDocument.load(r.bytes).then(function (src) {
						return out.copyPages(src, src.getPageIndices()).then(function (pages) { pages.forEach(function (pg) { out.addPage(pg); }); });
					});
				});
			}, Promise.resolve());
		}).then(function () { return out.save(); }).then(function (b) {
			A.download(new Blob([b], { type: 'application/pdf' }), 'sertifikalar-' + DATA.today + '.pdf');
		}).catch(function (e) { A.toast(e.message, 'err'); });
	});
	function resetAll() {
		if (state.edit) { var nu = A.safeUrl(DATA.newUrl); if (nu) window.location.href = nu; return; }
		if (state.current) { state.current.detached = true; state.current = null; refreshJobs(); }
		state.results = [];
		resetForm();
	}
	function resetForm() {
		if ($('flxs-backdate') && $('flxs-backdate').checked) { $('flxs-backdate').checked = false; $('flxs-issued').value = DATA.today; $('flxs-issued').disabled = true; $('flxs-backdate-box').classList.remove('is-on'); $('flxs-backdate-box').open = false; lastIssued = DATA.today; }
		state.rows = [newRow()];
		state.common = {};
		seedCommon();
		state.active = 0;
		$('flxs-progress').textContent = '';
		$('flxs-submit').disabled = false;
		var pt = $('flxs-paste-text'); if (pt) pt.value = '';
		var pb = $('flxs-paste'); if (pb) pb.hidden = true;
		renderResults(false);
		renderRows();
		renderCommon();
		updateNav();
		preview();
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
	$('flxs-new').addEventListener('click', resetAll);
	document.querySelectorAll('[data-flxs-reset]').forEach(function (b) { b.addEventListener('click', function () {
		var dirty = state.results.length || state.rows.some(function (r) { return r.name || r.phone || r.email || Object.keys(r.values).some(function (k) { return r.values[k]; }); });
		if (dirty && !window.confirm('Form temizlensin mi? Girilen kişiler ve sonuçlar ekrandan silinir (oluşturulan sertifikalar sistemde kalır).')) return;
		resetAll();
	}); });

	/* Gönderim durumunu canlı izle (kuyruğu tarayıcıdan da tetikler; WP-Cron beklemez) */
	function pump() {
		var ids = [];
		state.results.forEach(function (r) {
			Object.keys(r.messages || {}).forEach(function (ch) { if (r.messages[ch].message_id) ids.push(r.messages[ch].message_id); });
		});
		if (!ids.length) return;
		var tries = 0;
		var tick = function () {
			A.api('POST', 'queue/run').catch(function () {}).then(function () {
				return A.api('GET', 'queue/status?ids=' + ids.join(','));
			}).then(function (r) {
				var map = {};
				(r.messages || []).forEach(function (m) { map[m.id] = m; });
				var open = 0;
				state.results.forEach(function (res) {
					Object.keys(res.messages || {}).forEach(function (ch) {
						var x = res.messages[ch];
						var m = map[x.message_id];
						if (m) { x.status = m.status; x.last_error = m.last_error; }
						if (m && (m.status === 'queued' || m.status === 'processing')) open++;
					});
				});
				renderResults(false);
				if (open && ++tries < 40) setTimeout(tick, 4000);
			}).catch(function () {});
		};
		setTimeout(tick, 800);
	}

	/* ---------------- Başlat ---------------- */
	renderSend();
	// Referans: düzenlemede sertifikanınki, yoksa son seçilen, o da yoksa giriş yapan kişi
	(function () {
		var sel = $('flxs-ref');
		var v = DATA.me;
		if (state.edit) v = state.edit.ref_user_id || 0;
		else { try { var s = localStorage.getItem('flxs_ref'); if (s !== null) v = s; } catch (e) {} }
		if (sel.querySelector('option[value="' + v + '"]')) sel.value = String(v);
		sel.addEventListener('change', function () { if (!state.edit) { try { localStorage.setItem('flxs_ref', sel.value); } catch (e) {} } });
		// Etkinlik: düzenlemede sertifikanınki; yeni sertifikada son seçilen açık etkinlik, yoksa tek açık etkinlik
		var ev = $('flxs-event');
		var open = Array.prototype.filter.call(ev.options, function (o) { return +o.value && !o.disabled; });
		var w = 0;
		if (state.edit) w = state.edit.event_id || 0;
		else {
			try { var se = localStorage.getItem('flxs_event'); if (se !== null) w = se; } catch (e) {}
			if ((w === 0 || !ev.querySelector('option[value="' + w + '"]:not([disabled])')) && open.length) w = open[0].value;
		}
		if (ev.querySelector('option[value="' + w + '"]')) ev.value = String(w);
		ev.addEventListener('change', function () { if (!state.edit) { try { localStorage.setItem('flxs_event', ev.value); } catch (e) {} } });
	})();
	if (state.edit) {
		var e = state.edit;
		var p = e.person || {};
		var ph = p.phone ? (p.phone.indexOf('90') === 0 ? '0' + p.phone.slice(2) : '+' + p.phone) : '';
		state.rows = [newRow({ person_id: p.id, name: (e.values && e.values.ad_soyad) || p.name, phone: ph, email: p.email, values: Object.assign({}, e.values) })];
		delete state.rows[0].values.ad_soyad;
		selectTemplate(e.template_id);
	} else {
		state.rows = [newRow()];
		var last = 0;
		try { last = +localStorage.getItem('flxs_tpl'); } catch (er) {}
		selectTemplate(last || (DATA.templates[0] && DATA.templates[0].id));
	}
})();
