/**
 * Flux Certificates · PDF çizici
 * Şablon (arka plan PDF/görsel + alanlar) + değerler => PDF (pdf-lib), PDF => canvas/JPEG (pdf.js).
 * Aynı kod hem panelde canlı önizleme hem kayıt sırasında son PDF için kullanılır; çıktı birebir aynıdır.
 */
(function (global) {
	'use strict';

	var CFG = global.FLXS_RENDER || {};
	var PDFLib = global.PDFLib;
	var cache = {};
	var readyP = null;

	/* Sayfadaki başka bir eklenti AMD (define.amd) yüklüyse kütüphaneler window'a değil define'a kaydolur
	   ya da bir önbellek/optimizasyon eklentisi dosyayı yüklemeyebilir. Eksik olanı define gizlenmiş
	   halde yeniden yükler; yine yoksa anlaşılır bir hata verir. */
	var LIBS = { PDFLib: 'pdfLib', fontkit: 'fontkit', qrcode: 'qrcode', pdfjsLib: 'pdfjs' };
	function loadScript(src) {
		return new Promise(function (res, rej) {
			var el = document.createElement('script');
			el.src = src;
			el.async = false;
			el.onload = res;
			el.onerror = function () { rej(new Error(src)); };
			document.head.appendChild(el);
		});
	}
	function ready() {
		if (readyP) return readyP;
		var missing = Object.keys(LIBS).filter(function (g) { return !global[g]; });
		var urls = CFG.libs || {};
		var p = Promise.resolve();
		if (missing.length) {
			var amd = global.define;
			p = Promise.all(missing.filter(function (g) { return urls[LIBS[g]]; }).map(function (g) {
				global.define = undefined;
				return loadScript(urls[LIBS[g]]).catch(function () {});
			})).then(function () { if (global.define === undefined) global.define = amd; });
		}
		readyP = p.then(function () {
			var still = ['PDFLib', 'fontkit', 'pdfjsLib'].filter(function (g) { return !global[g]; });
			if (still.length) {
				readyP = null;
				throw new Error((CFG.i18n && CFG.i18n.libs ? CFG.i18n.libs : 'PDF kütüphanesi yüklenemedi (%s). Sayfayı yenileyip tekrar deneyin.').replace('%s', still.join(', ')));
			}
			PDFLib = global.PDFLib;
			if (CFG.worker) global.pdfjsLib.GlobalWorkerOptions.workerSrc = CFG.worker;
		});
		return readyP;
	}
	var MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
	var DAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

	if (global.pdfjsLib && CFG.worker) {
		global.pdfjsLib.GlobalWorkerOptions.workerSrc = CFG.worker;
	}

	function fetchBytes(url) {
		if (!url) return Promise.reject(new Error('Adres yok'));
		if (!cache[url]) {
			cache[url] = fetch(url, { credentials: 'same-origin' }).then(function (r) {
				if (!r.ok) throw new Error('Dosya yüklenemedi (' + r.status + '): ' + url);
				return r.arrayBuffer();
			}).catch(function (e) { delete cache[url]; throw e; });
		}
		return cache[url];
	}

	function pad(n) { return (n < 10 ? '0' : '') + n; }

	/** PHP date() biçiminin sık kullanılan kısmı: d j m n Y y F M l */
	function formatDate(ymd, fmt) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
		if (!m) return ymd || '';
		var d = new Date(+m[1], +m[2] - 1, +m[3]);
		fmt = fmt || 'd.m.Y';
		var out = '';
		for (var i = 0; i < fmt.length; i++) {
			var c = fmt[i];
			if (c === '\\') { out += fmt[++i] || ''; continue; }
			switch (c) {
				case 'd': out += pad(d.getDate()); break;
				case 'j': out += d.getDate(); break;
				case 'm': out += pad(d.getMonth() + 1); break;
				case 'n': out += d.getMonth() + 1; break;
				case 'Y': out += d.getFullYear(); break;
				case 'y': out += String(d.getFullYear()).slice(2); break;
				case 'F': out += MONTHS[d.getMonth()]; break;
				case 'M': out += MONTHS[d.getMonth()].slice(0, 3); break;
				case 'l': out += DAYS[d.getDay()]; break;
				default: out += c;
			}
		}
		return out;
	}

	function today() {
		var d = new Date();
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
	}

	function firstName(full) {
		var p = String(full || '').trim().split(/\s+/);
		if (p.length > 2) { p.pop(); return p.join(' '); }
		return p[0] || '';
	}

	/** Yer tutucu değişkenleri (PHP flxs_certificate_vars ile aynı mantık) */
	function buildVars(config, values, meta) {
		meta = meta || {};
		values = values || {};
		var vars = {};
		(config.fields || []).forEach(function (f) {
			if (f.type === 'static' || f.type === 'qr' || f.type === 'number_auto') return;
			var v = values[f.key] != null && values[f.key] !== '' ? values[f.key] : (f.default || '');
			if (f.type === 'date') v = formatDate(v, config.date_format);
			vars[f.key] = v;
		});
		var name = values.ad_soyad || '';
		vars.ad_soyad = name;
		vars.ad = firstName(name);
		var parts = name.trim().split(/\s+/);
		vars.soyad = parts.length > 1 ? parts[parts.length - 1] : '';
		vars.sertifika = config.title || '';
		vars.sertifika_no = meta.number || '';
		vars.kod = meta.code || '';
		vars.link = meta.link || '';
		vars.site = CFG.site || '';
		vars.kurum = CFG.org || '';
		if (!vars.tarih) vars.tarih = formatDate(meta.created || today(), config.date_format);
		return vars;
	}

	function fill(text, vars) {
		return String(text || '').replace(/\{([a-z0-9_]+)\}/gi, function (_, k) {
			k = k.toLowerCase();
			return vars[k] != null ? String(vars[k]) : '';
		});
	}

	function hexToRgb(hex) {
		var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
		if (!m) return PDFLib.rgb(0.08, 0.2, 0.29);
		return PDFLib.rgb(parseInt(m[1], 16) / 255, parseInt(m[2], 16) / 255, parseInt(m[3], 16) / 255);
	}

	function isJpeg(b) { return b[0] === 0xff && b[1] === 0xd8; }
	function isPng(b) { return b[0] === 0x89 && b[1] === 0x50; }

	/** pdf-lib JPG/PNG dışını desteklemiyor: WebP/GIF'i tarayıcıda PNG'ye çevir */
	function toPngBytes(buf) {
		return createImageBitmap(new Blob([buf])).then(function (bmp) {
			var c = document.createElement('canvas');
			c.width = bmp.width; c.height = bmp.height;
			c.getContext('2d').drawImage(bmp, 0, 0);
			return new Promise(function (res) { c.toBlob(res, 'image/png'); });
		}).then(function (blob) { return blob.arrayBuffer(); });
	}

	function textWidth(font, text, size, letter) {
		return font.widthOfTextAtSize(text, size) + (letter || 0) * Math.max(0, text.length - 1);
	}

	function wrap(font, text, size, maxW, letter) {
		var out = [];
		String(text).split('\n').forEach(function (para) {
			var words = para.split(/\s+/).filter(Boolean);
			if (!words.length) { out.push(''); return; }
			var line = words[0];
			for (var i = 1; i < words.length; i++) {
				var test = line + ' ' + words[i];
				if (textWidth(font, test, size, letter) <= maxW) line = test;
				else { out.push(line); line = words[i]; }
			}
			out.push(line);
		});
		return out;
	}

	/** Tek alanı çizer; kutu: (x, y) sol üst köşe, w genişlik, pt cinsinden, sayfanın üstünden ölçülür */
	function drawText(page, f, text, font, H) {
		if (!text) return;
		if (f.upper) text = text.toLocaleUpperCase('tr-TR');
		var size = +f.size || 14;
		var letter = +f.letter || 0;
		var maxLines = Math.max(1, +f.lines || 1);
		var lines;
		if (maxLines === 1) {
			text = text.replace(/\s*\n\s*/g, ' ');
			var w = textWidth(font, text, size, letter);
			if (f.fit !== false && w > f.w) size = Math.max(4, size * f.w / w);
			lines = [text];
		} else {
			lines = wrap(font, text, size, f.w, letter);
			var guard = 0;
			while (f.fit !== false && lines.length > maxLines && size > 5 && guard++ < 40) {
				size *= 0.95;
				lines = wrap(font, text, size, f.w, letter);
			}
			lines = lines.slice(0, maxLines);
		}
		var ascent = font.heightAtSize(size, { descender: false });
		var lh = size * (+f.lineHeight || 1.25);
		var color = hexToRgb(f.color);
		if (letter) page.pushOperators(PDFLib.setCharacterSpacing(letter));
		lines.forEach(function (ln, i) {
			var lw = textWidth(font, ln, size, letter);
			var x = f.x;
			if (f.align === 'center') x = f.x + (f.w - lw) / 2;
			else if (f.align === 'right') x = f.x + f.w - lw;
			page.drawText(ln, { x: x, y: H - f.y - ascent - i * lh, size: size, font: font, color: color });
		});
		if (letter) page.pushOperators(PDFLib.setCharacterSpacing(0));
	}

	function drawQr(page, f, link, H) {
		if (!global.qrcode || !link) return;
		var qr = global.qrcode(0, 'M');
		qr.addData(link);
		qr.make();
		var n = qr.getModuleCount();
		var path = '';
		for (var r = 0; r < n; r++) {
			for (var c = 0; c < n; c++) {
				if (qr.isDark(r, c)) path += 'M' + c + ' ' + r + 'h1v1h-1z';
			}
		}
		page.drawSvgPath(path, { x: f.x, y: H - f.y, scale: f.w / n, color: hexToRgb(f.color), borderWidth: 0 });
	}

	/**
	 * @param {object} config şablon ayarı
	 * @param {object} values form değerleri ({ad_soyad, ...})
	 * @param {object} meta {number, link, code, created}
	 * @param {object} opts {preview: bool} boş alanlara gri örnek metin yazar
	 * @returns {Promise<Uint8Array>}
	 */
	function renderPdf(config, values, meta, opts) {
		return ready().then(function () { return renderPdfNow(config, values, meta, opts); });
	}

	function renderPdfNow(config, values, meta, opts) {
		opts = opts || {};
		meta = meta || {};
		var bg = config.background || {};
		var W = (config.page && +config.page.w) || 841.89;
		var H = (config.page && +config.page.h) || 595.28;
		var docP;

		if (bg.type === 'pdf' && bg.url) {
			docP = fetchBytes(bg.url).then(function (b) { return PDFLib.PDFDocument.load(b.slice(0), { ignoreEncryption: true }); });
		} else {
			docP = PDFLib.PDFDocument.create().then(function (doc) {
				var page = doc.addPage([W, H]);
				if (bg.type === 'image' && bg.url) {
					return fetchBytes(bg.url).then(function (buf) {
						var u8 = new Uint8Array(buf);
						if (isJpeg(u8)) return doc.embedJpg(buf);
						if (isPng(u8)) return doc.embedPng(buf);
						return toPngBytes(buf).then(function (p) { return doc.embedPng(p); });
					}).then(function (img) {
						page.drawImage(img, { x: 0, y: 0, width: W, height: H });
						return doc;
					});
				}
				page.drawRectangle({ x: 0, y: 0, width: W, height: H, color: hexToRgb(bg.color || '#ffffff') });
				return doc;
			});
		}

		return docP.then(function (doc) {
			doc.registerFontkit(global.fontkit);
			doc.setTitle(fill('{sertifika} · {ad_soyad}', buildVars(config, values, meta)));
			doc.setCreator('Flux Certificates');
			doc.setProducer(CFG.site || 'WordPress');
			var pages = doc.getPages();
			var vars = buildVars(config, values, meta);
			var fontCache = {};
			function font(id) {
				if (!fontCache[id]) {
					if (!(CFG.fonts || {})[id]) id = 'nunito';
					var url = CFG.fonts[id];
					var subset = (CFG.nosubset || []).indexOf(id) === -1;
					fontCache[id] = fetchBytes(url).then(function (b) {
						return doc.embedFont(b, { subset: subset });
					});
				}
				return fontCache[id];
			}

			var jobs = (config.fields || []).filter(function (f) { return f.draw !== false; }).map(function (f) {
				var page = pages[Math.min(f.page || 0, pages.length - 1)];
				var PH = page.getHeight();
				if (f.type === 'qr') {
					drawQr(page, f, meta.link || (opts.preview ? 'https://ornek.org/sertifika/onizleme' : ''), PH);
					return null;
				}
				var text, ghost = false;
				if (f.type === 'static') text = fill(f.default, vars);
				else if (f.type === 'number_auto') text = meta.number || (opts.preview ? 'YT-' + new Date().getFullYear() + '-•••••' : '');
				else {
					text = f.key === 'ad_soyad' ? vars.ad_soyad : vars[f.key];
					if (!text && opts.preview) { text = f.placeholder || f.label; ghost = true; }
				}
				return font(f.font).then(function (fnt) {
					var ff = ghost ? Object.assign({}, f, { color: '#b9c6cd' }) : f;
					drawText(page, ff, String(text || ''), fnt, PH);
				});
			});
			return Promise.all(jobs).then(function () { return doc.save(); });
		});
	}

	/** PDF'in ilk sayfasını canvas'a çizer. width = CSS piksel genişliği */
	function renderToCanvas(bytes, canvas, width, pageNo) {
		return ready().then(function () { return renderToCanvasNow(bytes, canvas, width, pageNo); });
	}

	function renderToCanvasNow(bytes, canvas, width, pageNo) {
		var task = global.pdfjsLib.getDocument({ data: bytes.slice(0), isEvalSupported: false });
		return task.promise.then(function (pdf) {
			return pdf.getPage(pageNo || 1).then(function (page) {
				var v1 = page.getViewport({ scale: 1 });
				var ratio = global.devicePixelRatio || 1;
				var scale = (width / v1.width) * ratio;
				var vp = page.getViewport({ scale: scale });
				canvas.width = Math.round(vp.width);
				canvas.height = Math.round(vp.height);
				var ctx = canvas.getContext('2d');
				ctx.fillStyle = '#fff';
				ctx.fillRect(0, 0, canvas.width, canvas.height);
				return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
					pdf.destroy();
					return { w: v1.width, h: v1.height };
				});
			});
		});
	}

	/** Link önizlemesi ve sayfa görseli için JPEG (WhatsApp için ~1600px, ~200KB) */
	function toJpeg(bytes, width, quality) {
		var c = document.createElement('canvas');
		var dpr = global.devicePixelRatio || 1;
		return renderToCanvas(bytes, c, (width || 1600) / dpr).then(function () {
			return new Promise(function (res) {
				c.toBlob(function (blob) { res({ blob: blob, w: c.width, h: c.height }); }, 'image/jpeg', quality || 0.85);
			});
		});
	}

	/* Link önizlemesi için 1200x630 paylaşım görseli: solda başlık + isim, sağda sertifika */
	var shareFontP = null;
	function shareFont() {
		if (shareFontP) return shareFontP;
		var fonts = (global.FLXS_RENDER && global.FLXS_RENDER.fonts) || {};
		var jobs = [['FLXSShareB', fonts['montserrat-bold'] || fonts['nunito-bold']], ['FLXSShareR', fonts['montserrat'] || fonts['nunito']]].map(function (f) {
			if (!f[1] || typeof FontFace === 'undefined') return Promise.resolve();
			var ff = new FontFace(f[0], 'url(' + f[1] + ')');
			return ff.load().then(function (l) { document.fonts.add(l); }).catch(function () {});
		});
		shareFontP = Promise.all(jobs);
		return shareFontP;
	}

	function wrapLines(ctx, text, maxW) {
		var words = String(text || '').split(/\s+/), lines = [], cur = '';
		words.forEach(function (w) {
			var t = cur ? cur + ' ' + w : w;
			if (cur && ctx.measureText(t).width > maxW) { lines.push(cur); cur = w; } else { cur = t; }
		});
		if (cur) lines.push(cur);
		return lines;
	}

	function toShareImage(bytes, o) {
		o = o || {};
		var W = 1200, H = 630;
		var cc = document.createElement('canvas');
		return Promise.all([shareFont(), renderToCanvas(bytes, cc, 900 / (global.devicePixelRatio || 1))]).then(function () {
			var c = document.createElement('canvas');
			c.width = W; c.height = H;
			var x = c.getContext('2d');
			var g = x.createLinearGradient(0, 0, W, H);
			g.addColorStop(0, o.color1 || '#0e7a56');
			g.addColorStop(1, o.color2 || '#0a4a35');
			x.fillStyle = g;
			x.fillRect(0, 0, W, H);
			// dekor: büyük yarı saydam daireler
			x.fillStyle = 'rgba(255,255,255,.06)';
			x.beginPath(); x.arc(W - 120, -60, 260, 0, Math.PI * 2); x.fill();
			x.beginPath(); x.arc(80, H + 40, 200, 0, Math.PI * 2); x.fill();
			// sertifika
			var ratio = cc.width / cc.height;
			var maxH = 560, maxW = 640;
			var ch = maxH, cw = ch * ratio;
			if (cw > maxW) { cw = maxW; ch = cw / ratio; }
			var cx = W - cw - 40, cy = (H - ch) / 2;
			x.save();
			x.shadowColor = 'rgba(0,0,0,.35)'; x.shadowBlur = 40; x.shadowOffsetY = 14;
			x.fillStyle = '#fff';
			x.fillRect(cx - 8, cy - 8, cw + 16, ch + 16);
			x.restore();
			x.drawImage(cc, cx, cy, cw, ch);
			// metinler
			var tx = 56, tw = cx - tx - 40;
			x.fillStyle = 'rgba(255,255,255,.16)';
			var badge = '✓  ' + (o.badge || 'Doğrulanmış sertifika');
			x.font = '700 20px FLXSShareB, Montserrat, Arial, sans-serif';
			var bw = x.measureText(badge).width + 36;
			roundRect(x, tx, 70, bw, 44, 22); x.fill();
			x.fillStyle = '#fff';
			x.textBaseline = 'middle';
			x.fillText(badge, tx + 18, 93);
			x.textBaseline = 'alphabetic';
			x.fillStyle = 'rgba(255,255,255,.85)';
			x.font = '600 26px FLXSShareB, Montserrat, Arial, sans-serif';
			var ty = 175;
			wrapLines(x, o.title || '', tw).slice(0, 2).forEach(function (l) { x.fillText(l, tx, ty); ty += 34; });
			// isim: sığana kadar küçült (en fazla 3 satır)
			var size = 64, lines;
			do {
				x.font = '800 ' + size + 'px FLXSShareB, Montserrat, Arial, sans-serif';
				lines = wrapLines(x, o.name || '', tw);
				size -= 4;
			} while ((lines.length > 3 || lines.some(function (l) { return x.measureText(l).width > tw; })) && size > 26);
			size += 4;
			ty += size * 0.55;
			x.fillStyle = '#fff';
			lines.slice(0, 3).forEach(function (l) { ty += size * 1.1; x.fillText(l, tx, ty); });
			x.fillStyle = o.accent || '#a8e07a';
			x.fillRect(tx, ty + 26, 72, 6);
			x.fillStyle = 'rgba(255,255,255,.85)';
			x.font = '500 22px FLXSShareR, Montserrat, Arial, sans-serif';
			wrapLines(x, o.org || '', tw).slice(0, 2).forEach(function (l, i) { x.fillText(l, tx, H - 70 + i * 28 - (o.org && wrapLines(x, o.org, tw).length > 1 ? 28 : 0)); });
			return new Promise(function (res) {
				c.toBlob(function (blob) { res({ blob: blob, w: W, h: H }); }, 'image/jpeg', 0.86);
			});
		});
	}

	function roundRect(x, l, t, w, h, r) {
		x.beginPath();
		x.moveTo(l + r, t); x.arcTo(l + w, t, l + w, t + h, r); x.arcTo(l + w, t + h, l, t + h, r);
		x.arcTo(l, t + h, l, t, r); x.arcTo(l, t, l + w, t, r); x.closePath();
	}

	function loadPdfInfo(url) {
		return ready().then(function () { return fetchBytes(url); }).then(function (b) {
			return global.pdfjsLib.getDocument({ data: b.slice(0) }).promise.then(function (pdf) {
				return pdf.getPage(1).then(function (p) {
					var v = p.getViewport({ scale: 1 });
					var info = { w: v.width, h: v.height, pages: pdf.numPages };
					pdf.destroy();
					return info;
				});
			});
		});
	}

	global.FLXSRender = {
		ready: ready,
		renderPdf: renderPdf,
		renderToCanvas: renderToCanvas,
		toJpeg: toJpeg,
		toShareImage: toShareImage,
		buildVars: buildVars,
		fill: fill,
		formatDate: formatDate,
		today: today,
		fetchBytes: fetchBytes,
		loadPdfInfo: loadPdfInfo,
		forget: function (url) { delete cache[url]; }
	};
})(window);
