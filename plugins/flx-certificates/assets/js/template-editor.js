/* Flux Certificates · Şablon editörü: arka plan + sürüklenebilir alanlar + canlı PDF */
(function () {
	'use strict';
	var A = window.FLXSA, R = window.FLXSRender, esc = A.esc;
	var root = document.getElementById('flxs-te');
	if (!root || !R) return;
	var D = JSON.parse(document.getElementById('flxs-tpl-data').textContent);
	var T = D.template;
	var cfg = T.config;
	var sel = null;
	var RESERVED = ['ad', 'soyad', 'link', 'kod', 'site', 'kurum', 'sertifika', 'buton'];
	function $(id) { return document.getElementById(id); }
	function field(id) { return cfg.fields.filter(function (f) { return f.id === id; })[0]; }
	function uidf() { return 'f' + Math.random().toString(36).slice(2, 8); }

	/* ---------- Genel ayarlar ---------- */
	$('te-name').value = T.name;
	$('te-title').value = cfg.title || '';
	$('te-status').value = T.status || 'active';
	$('te-datefmt').value = cfg.date_format || 'd.m.Y';
	$('te-bgtype').value = cfg.background.type || 'none';
	$('te-bgcolor').value = cfg.background.color || '#ffffff';
	$('te-w').value = cfg.page.w;
	$('te-h').value = cfg.page.h;
	cfg.donor = cfg.donor || { selectable: false, group: '', sort: 0 };
	$('te-donor').checked = !!cfg.donor.selectable;
	$('te-donor-group').value = cfg.donor.group || '';
	$('te-donor-sort').value = cfg.donor.sort || 0;
	$('te-donor-opts').style.display = cfg.donor.selectable ? '' : 'none';
	$('te-ogt').value = cfg.share.og_title || '';
	$('te-ogd').value = cfg.share.og_description || '';
	['sms', 'whatsapp', 'email_subject', 'email_body'].forEach(function (k) {
		var el = $('te-msg-' + k);
		el.value = cfg.messages[k] || '';
		el.placeholder = D.defaults[k] || '';
	});
	function syncBg() {
		var t = $('te-bgtype').value;
		$('te-color-wrap').hidden = t !== 'none';
		$('te-bgfile').hidden = t === 'none';
		$('te-bgname').textContent = cfg.background.url ? decodeURIComponent(cfg.background.url.split('/').pop()) : 'Dosya seçilmedi';
		syncPreset();
	}
	function syncPreset() {
		var v = (+cfg.page.w) + 'x' + (+cfg.page.h);
		var p = $('te-preset');
		p.value = Array.prototype.some.call(p.options, function (o) { return o.value === v; }) ? v : '';
	}
	syncBg();

	root.addEventListener('input', function (e) {
		var id = e.target.id;
		if (id === 'te-name') T.name = e.target.value;
		else if (id === 'te-title') { cfg.title = e.target.value; render(); }
		else if (id === 'te-status') T.status = e.target.value;
		else if (id === 'te-datefmt') { cfg.date_format = e.target.value; render(); }
		else if (id === 'te-bgcolor') { cfg.background.color = e.target.value; render(); }
		else if (id === 'te-w' || id === 'te-h') { cfg.page[id === 'te-w' ? 'w' : 'h'] = +e.target.value || 100; syncPreset(); render(); }
		else if (id === 'te-donor') { cfg.donor.selectable = e.target.checked; $('te-donor-opts').style.display = e.target.checked ? '' : 'none'; }
		else if (id === 'te-donor-group') cfg.donor.group = e.target.value;
		else if (id === 'te-donor-sort') cfg.donor.sort = parseInt(e.target.value, 10) || 0;
		else if (id === 'te-ogt') cfg.share.og_title = e.target.value;
		else if (id === 'te-ogd') cfg.share.og_description = e.target.value;
		else if (id.indexOf('te-msg-') === 0) cfg.messages[id.slice(7)] = e.target.value;
		else if (id === 'te-sample') render();
	});
	root.addEventListener('change', function (e) {
		if (e.target.id === 'te-bgtype') { cfg.background.type = e.target.value; syncBg(); render(); }
		if (e.target.id === 'te-preset' && e.target.value) {
			var p = e.target.value.split('x');
			cfg.page.w = +p[0]; cfg.page.h = +p[1];
			$('te-w').value = cfg.page.w; $('te-h').value = cfg.page.h;
			render();
		}
		if (e.target.id === 'te-sample') render();
	});

	/* Arka plan: WordPress ortam kütüphanesi */
	var frame;
	$('te-bgpick').addEventListener('click', function () {
		var type = $('te-bgtype').value;
		frame = wp.media({
			title: type === 'pdf' ? 'Sertifika PDF\'i seçin' : 'Arka plan görseli seçin',
			library: { type: type === 'pdf' ? 'application/pdf' : 'image' },
			button: { text: 'Kullan' },
			multiple: false
		});
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var isPdf = a.mime === 'application/pdf';
			cfg.background = { type: isPdf ? 'pdf' : 'image', source: 'attachment', id: a.id, file: '', color: cfg.background.color || '#ffffff', url: a.url };
			$('te-bgtype').value = cfg.background.type;
			var p = isPdf ? R.loadPdfInfo(a.url) : Promise.resolve(a.width && a.height ? { w: a.width, h: a.height } : null);
			p.then(function (info) {
				if (info) {
					var w = info.w, h = info.h;
					if (!isPdf) { var k = 841.89 / Math.max(w, h); w = Math.round(w * k * 100) / 100; h = Math.round(h * k * 100) / 100; }
					var sx = w / cfg.page.w, sy = h / cfg.page.h;
					// Alanları yeni sayfaya oranla taşı (boyut değiştiyse)
					if (Math.abs(sx - 1) > 0.01 || Math.abs(sy - 1) > 0.01) {
						cfg.fields.forEach(function (f) { f.x = +(f.x * sx).toFixed(2); f.y = +(f.y * sy).toFixed(2); f.w = +(f.w * sx).toFixed(2); f.size = +(f.size * Math.min(sx, sy)).toFixed(1); });
					}
					cfg.page.w = w; cfg.page.h = h;
					$('te-w').value = w; $('te-h').value = h;
					if (isPdf && info.pages > 1) A.toast('PDF ' + info.pages + ' sayfa; alanlar varsayılan olarak 1. sayfaya yazılır', 'warn');
				}
				syncBg();
				render();
			}).catch(function (e) { A.toast(e.message, 'err'); });
		});
		frame.open();
	});

	/* ---------- Alan listesi ---------- */
	var addSel = $('te-add');
	Object.keys(D.types).forEach(function (k) {
		var o = document.createElement('option');
		o.value = k; o.textContent = D.types[k];
		addSel.appendChild(o);
	});
	addSel.addEventListener('change', function () {
		var type = addSel.value;
		addSel.value = '';
		if (!type) return;
		var keys = cfg.fields.map(function (f) { return f.key; });
		var key = { date: 'tarih', number_auto: 'sertifika_no', qr: 'qr' }[type] || 'alan';
		if (keys.indexOf(key) !== -1 || key === 'alan') { var i = 2; while (keys.indexOf(key + '_' + i) !== -1) i++; key = key + '_' + i; }
		if (keys.indexOf('ad_soyad') === -1 && type === 'text') key = 'ad_soyad';
		var f = {
			id: uidf(), key: key, label: key === 'ad_soyad' ? 'Ad Soyad' : D.types[type], type: type,
			default: type === 'static' ? 'Metin {ad_soyad}' : '', placeholder: '', options: '', required: false, draw: true,
			x: cfg.page.w / 2 - 150, y: cfg.page.h / 2, w: type === 'qr' ? 70 : 300, size: 18, font: 'nunito', color: '#111827',
			align: 'center', lines: type === 'textarea' || type === 'static' ? 3 : 1, lineHeight: 1.3, fit: true, upper: false, letter: 0, page: 0
		};
		if (type === 'qr') f.x = cfg.page.w / 2 - 35;
		cfg.fields.push(f);
		select(f.id);
		render();
	});

	function renderList() {
		$('te-fields').innerHTML = cfg.fields.map(function (f, i) {
			return '<li class="' + (f.id === sel ? 'is-sel' : '') + (f.draw === false ? ' is-hidden' : '') + '" data-id="' + esc(f.id) + '">' +
				'<span class="flxs-fields__lbl"><strong>' + esc(f.label) + '</strong> <code>{' + esc(f.key) + '}</code><small>' + esc(D.types[f.type] || f.type) + (f.draw === false ? ' · sayfada yok, formda var' : '') + '</small></span>' +
				'<span class="flxs-fields__acts">' +
				'<button type="button" data-act="up" title="Yukarı"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
				'<button type="button" data-act="eye" title="Sayfada göster/gizle">' + (f.draw === false ? '◌' : '●') + '</button>' +
				'<button type="button" data-act="del" title="Sil">✕</button></span></li>';
		}).join('');
	}
	$('te-fields').addEventListener('click', function (e) {
		var li = e.target.closest('li');
		if (!li) return;
		var id = li.getAttribute('data-id');
		var f = field(id);
		var act = e.target.closest('[data-act]');
		if (act) {
			var a = act.getAttribute('data-act');
			var i = cfg.fields.indexOf(f);
			if (a === 'del') {
				if (!window.confirm('"' + f.label + '" alanı silinsin mi?')) return;
				cfg.fields.splice(i, 1);
				if (sel === id) sel = null;
			} else if (a === 'up' && i > 0) {
				cfg.fields.splice(i - 1, 0, cfg.fields.splice(i, 1)[0]);
			} else if (a === 'eye') {
				f.draw = f.draw === false;
			}
			renderList(); renderProps(); render();
			return;
		}
		select(id);
	});

	function select(id) {
		sel = id;
		renderList();
		renderProps();
		renderOverlay();
	}

	/* ---------- Seçili alan özellikleri ---------- */
	function renderProps() {
		var box = $('te-props');
		var f = field(sel);
		if (!f) { box.hidden = true; return; }
		box.hidden = false;
		var isQr = f.type === 'qr';
		var isText = !isQr;
		var input = ['text', 'textarea', 'date', 'number', 'select'].indexOf(f.type) !== -1;
		var fontOpts = D.fonts.map(function (o) { return '<option value="' + esc(o.id) + '"' + (o.id === f.font ? ' selected' : '') + '>' + esc(o.label) + '</option>'; }).join('');
		var h = '<div class="flxs-card__head"><h2 class="flxs-card__title">' + esc(f.label) + ' <small>' + esc(D.types[f.type]) + '</small></h2><button type="button" class="button-link" data-p-close>Kapat</button></div>';
		h += '<div class="flxs-grid">' +
			p('label', 'Etiket (formda)', 'text') +
			p('key', 'Anahtar {…}', 'text', f.type === 'qr' || f.type === 'number_auto') + '</div>';
		if (f.type === 'static') h += p('default', 'Metin (yer tutucu kullanılabilir, satır için Enter)', 'textarea');
		if (input) {
			h += '<div class="flxs-grid">' + p('default', 'Varsayılan değer', f.type === 'date' ? 'date' : 'text') + p('placeholder', 'İpucu (placeholder)', 'text') + '</div>';
			if (f.type === 'select') h += p('options', 'Seçenekler (her satıra bir)', 'textarea');
			h += '<div class="flxs-checks">' + c('required', 'Zorunlu') + c('draw', 'Sayfaya yaz (kapalıysa yalnızca formda; metinlerde {' + esc(f.key) + '} ile kullanılır)') + '</div>';
		}
		if (isText) {
			h += '<div class="flxs-grid">' +
				'<label class="flxs-field"><span>Font</span><select data-p="font">' + fontOpts + '</select></label>' +
				p('size', 'Boyut (pt)', 'number') + p('color', 'Renk', 'color') + '</div>';
			h += '<div class="flxs-grid">' +
				'<label class="flxs-field"><span>Hizalama</span><select data-p="align"><option value="left">Sol</option><option value="center">Orta</option><option value="right">Sağ</option></select></label>' +
				p('lines', 'En çok satır', 'number') + p('lineHeight', 'Satır aralığı', 'number') + p('letter', 'Harf aralığı', 'number') + '</div>';
			h += '<div class="flxs-checks">' + c('upper', 'BÜYÜK HARF') + c('fit', 'Sığmazsa küçült') + '</div>';
		} else {
			h += '<div class="flxs-grid">' + p('color', 'Renk', 'color') + '</div><div class="flxs-checks">' + c('draw', 'Sayfaya yaz') + '</div>';
		}
		h += '<div class="flxs-grid flxs-grid--4">' + p('x', 'X (pt)', 'number') + p('y', 'Y (pt)', 'number') + p('w', isQr ? 'Boyut (pt)' : 'Genişlik (pt)', 'number') + p('page', 'Sayfa (0=ilk)', 'number') + '</div>';
		h += '<p><button type="button" class="button button-small" data-p-center>Yatayda ortala</button></p>';
		box.innerHTML = h;
		var al = box.querySelector('[data-p=align]');
		if (al) al.value = f.align;

		function p(k, label, type, ro) {
			var v = f[k] == null ? '' : f[k];
			if (type === 'textarea') return '<label class="flxs-field"><span>' + label + '</span><textarea data-p="' + k + '" rows="3">' + esc(v) + '</textarea></label>';
			return '<label class="flxs-field"><span>' + label + '</span><input type="' + type + '" data-p="' + k + '" value="' + esc(v) + '"' + (type === 'number' ? ' step="any"' : '') + (ro ? ' readonly' : '') + '></label>';
		}
		function c(k, label) {
			return '<label><input type="checkbox" data-p="' + k + '"' + (f[k] !== false && f[k] ? ' checked' : '') + '> ' + label + '</label>';
		}
	}
	$('te-props').addEventListener('input', onProp);
	$('te-props').addEventListener('change', onProp);
	$('te-props').addEventListener('click', function (e) {
		if (e.target.closest('[data-p-close]')) { sel = null; renderList(); renderProps(); renderOverlay(); }
		if (e.target.closest('[data-p-center]')) {
			var f = field(sel);
			f.x = +((cfg.page.w - f.w) / 2).toFixed(2);
			renderProps(); renderOverlay(); render();
		}
	});
	function onProp(e) {
		var k = e.target.getAttribute('data-p');
		var f = field(sel);
		if (!k || !f) return;
		var v = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
		if (e.target.type === 'number') v = parseFloat(v) || 0;
		if (k === 'key') {
			v = String(v).toLowerCase().replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c').replace(/[^a-z0-9_]/g, '_');
			if (RESERVED.indexOf(v) !== -1) { A.toast('"' + v + '" sistem yer tutucusu, başka anahtar seçin', 'warn'); return; }
		}
		f[k] = v;
		if (e.type === 'change' || ['label', 'key'].indexOf(k) === -1) renderList();
		renderOverlay();
		render();
	}

	/* ---------- Önizleme + sürükle bırak ---------- */
	var canvas = $('te-canvas'), overlay = $('te-overlay'), stage = $('te-stage');
	var token = 0;
	function sampleValues() {
		var v = {};
		if (!$('te-sample').checked) return v;
		cfg.fields.forEach(function (f) {
			if (f.key === 'ad_soyad') v.ad_soyad = f.placeholder || 'Ayşe Yılmaz';
			else if (f.type === 'date') v[f.key] = f.default || R.today();
			else if (f.type === 'number') v[f.key] = f.default || '10';
			else if (f.type === 'select') v[f.key] = (f.options || '').split('\n')[0] || '';
			else if (['text', 'textarea'].indexOf(f.type) !== -1) v[f.key] = f.default || f.placeholder || f.label;
		});
		return v;
	}
	var render = A.debounce(function () {
		var tk = ++token;
		$('te-spin').classList.add('is-on');
		var sample = $('te-sample').checked;
		R.renderPdf(cfg, sampleValues(), sample ? { number: 'YT-' + new Date().getFullYear() + '-00042', link: location.origin + '/sertifika/ornekkod12345', code: 'ornekkod12345' } : {}, { preview: !sample }).then(function (b) {
			if (tk !== token) return null;
			return R.renderToCanvas(b, canvas, stage.clientWidth - 2);
		}).then(function () {
			if (tk !== token) return;
			$('te-err').hidden = true;
			renderOverlay();
		}).catch(function (e) {
			$('te-err').hidden = false;
			$('te-err').textContent = 'Önizleme hatası: ' + e.message;
		}).finally(function () { if (tk === token) $('te-spin').classList.remove('is-on'); });
	}, 200);
	window.addEventListener('resize', A.debounce(render, 300));

	function boxH(f) {
		if (f.type === 'qr') return f.w;
		var lines = Math.max(1, +f.lines || 1);
		if (f.type === 'static' && f.default) lines = Math.min(lines, String(f.default).split('\n').length > 1 ? lines : lines);
		return f.size * (lines > 1 ? (+f.lineHeight || 1.25) * lines : 1.2);
	}
	function renderOverlay() {
		var cw = canvas.clientWidth, ch = canvas.clientHeight;
		overlay.style.width = cw + 'px';
		overlay.style.height = ch + 'px';
		var s = cw / cfg.page.w;
		overlay.innerHTML = cfg.fields.filter(function (f) { return f.draw !== false && (f.page || 0) === 0; }).map(function (f) {
			return '<div class="flxs-box' + (f.id === sel ? ' is-sel' : '') + (f.type === 'qr' ? ' is-qr' : '') + '" data-id="' + esc(f.id) + '" style="left:' + (Number(f.x) * s || 0) + 'px;top:' + (Number(f.y) * s || 0) + 'px;width:' + (Number(f.w) * s || 0) + 'px;height:' + (Number(boxH(f)) * s || 0) + 'px">' +
				'<span class="flxs-box__tag">' + esc(f.label) + '</span><span class="flxs-box__rs" data-rs></span></div>';
		}).join('') + '<div class="flxs-guide" hidden></div>';
	}

	var drag = null;
	overlay.addEventListener('pointerdown', function (e) {
		var b = e.target.closest('.flxs-box');
		if (!b) { if (sel) { sel = null; renderList(); renderProps(); renderOverlay(); } return; }
		e.preventDefault();
		var f = field(b.getAttribute('data-id'));
		if (sel !== f.id) select(f.id);
		b = overlay.querySelector('[data-id="' + f.id + '"]');
		drag = { f: f, el: b, sx: e.clientX, sy: e.clientY, x: f.x, y: f.y, w: f.w, rs: !!e.target.closest('[data-rs]'), s: canvas.clientWidth / cfg.page.w };
		b.setPointerCapture(e.pointerId);
	});
	overlay.addEventListener('pointermove', function (e) {
		if (!drag) return;
		var dx = (e.clientX - drag.sx) / drag.s, dy = (e.clientY - drag.sy) / drag.s;
		var f = drag.f, guide = overlay.querySelector('.flxs-guide');
		if (drag.rs) {
			var nw = Math.max(10, drag.w + (f.align === 'center' ? dx * 2 : dx));
			if (f.align === 'center') f.x = +(drag.x - (nw - drag.w) / 2).toFixed(2);
			f.w = +nw.toFixed(2);
		} else {
			f.x = +(drag.x + dx).toFixed(2);
			f.y = +(drag.y + dy).toFixed(2);
			var cx = f.x + f.w / 2;
			if (Math.abs(cx - cfg.page.w / 2) < 4) { f.x = +((cfg.page.w - f.w) / 2).toFixed(2); guide.hidden = false; guide.style.left = (cfg.page.w / 2 * drag.s) + 'px'; } else guide.hidden = true;
		}
		drag.el.style.left = (f.x * drag.s) + 'px';
		drag.el.style.top = (f.y * drag.s) + 'px';
		drag.el.style.width = (f.w * drag.s) + 'px';
		if (f.type === 'qr') drag.el.style.height = (f.w * drag.s) + 'px';
	});
	overlay.addEventListener('pointerup', function () {
		if (!drag) return;
		drag = null;
		var g = overlay.querySelector('.flxs-guide');
		if (g) g.hidden = true;
		renderProps();
		render();
	});
	document.addEventListener('keydown', function (e) {
		if (!sel || /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
		var f = field(sel), st = e.shiftKey ? 10 : 1;
		var m = { ArrowLeft: [-st, 0], ArrowRight: [st, 0], ArrowUp: [0, -st], ArrowDown: [0, st] }[e.key];
		if (!m) return;
		e.preventDefault();
		f.x = +(f.x + m[0]).toFixed(2);
		f.y = +(f.y + m[1]).toFixed(2);
		renderOverlay(); renderProps(); render();
	});

	/* ---------- Kaydet ---------- */
	$('te-save').addEventListener('click', function () {
		var btn = this;
		if (!T.name.trim()) { A.toast('Şablon adı gerekli', 'err'); $('te-name').focus(); return; }
		if (!cfg.fields.some(function (f) { return f.key === 'ad_soyad'; })) {
			if (!window.confirm('Şablonda "ad_soyad" anahtarlı alan yok; sertifikada kişinin adı yazmayacak. Yine de kaydedilsin mi?')) return;
		}
		btn.disabled = true;
		A.api('POST', 'templates', { id: T.id, name: T.name, status: T.status, config: cfg }).then(function (r) {
			var isNew = !T.id;
			T.id = r.template.id;
			if (isNew) history.replaceState(null, '', A.C.admin + '?page=flxs-sablonlar&edit=' + T.id);
			// Liste için küçük önizleme görseli
			return R.renderPdf(cfg, sampleValues(), { number: 'FLX-2026-00042', link: location.origin + '/sertifika/ornek' }).then(function (b) {
				return R.toJpeg(b, 700, 0.8);
			}).then(function (img) {
				var fd = new FormData();
				fd.append('image', img.blob, 'thumb.jpg');
				return A.api('POST', 'templates/' + T.id + '/thumb', fd);
			});
		}).then(function () {
			A.toast('Şablon kaydedildi');
		}).catch(function (e) { A.toast(e.message, 'err'); }).finally(function () { btn.disabled = false; });
	});

	renderList();
	render();
})();
