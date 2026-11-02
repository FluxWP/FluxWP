/* Flux Studio — alan düzenleyici (fırça/dikdörtgen maske) ve karşılaştırma kaydırıcısı. */
(function () {
	'use strict';
	var S = window.FLXST;
	var h = S.h, t = S.t;

	var MAX_SIDE = 2048; // Çizim tuvali; dışa aktarırken görselin gerçek boyutuna büyütülür.

	/**
	 * Maske düzenleyici. opts: {item, onSubmit(maskDataUrl, prompt, layers) → Promise}
	 * Maske: boyanan alan saydam (değişecek), kalan opak siyah.
	 */
	S.maskEditor = function (item, opts) {
		opts = opts || {};
		var nw = item.width || 1024, nh = item.height || 1024;
		var sc = Math.min(1, MAX_SIDE / Math.max(nw, nh));
		var cw = Math.round(nw * sc), ch = Math.round(nh * sc);
		var ops = [], cur = null, tool = 'brush', size = Math.round(Math.max(cw, ch) / 18);

		var img = h('img.flxst-me-img', { src: item.url, alt: '', draggable: 'false' });
		var cv = h('canvas.flxst-me-cv', { width: cw, height: ch });
		var ctx = cv.getContext('2d');
		var stage = h('.flxst-me-stage', { style: { aspectRatio: nw + ' / ' + nh } }, img, cv);
		var wrap = h('.flxst-me-wrap', stage);

		function redraw() {
			ctx.clearRect(0, 0, cw, ch);
			ops.concat(cur ? [cur] : []).forEach(function (o) {
				ctx.globalCompositeOperation = o.tool === 'eraser' ? 'destination-out' : 'source-over';
				ctx.fillStyle = ctx.strokeStyle = 'rgba(92,201,139,0.55)';
				if (o.tool === 'rect') {
					ctx.fillRect(Math.min(o.x, o.x2), Math.min(o.y, o.y2), Math.abs(o.x2 - o.x), Math.abs(o.y2 - o.y));
				} else {
					ctx.lineWidth = o.size;
					ctx.lineCap = ctx.lineJoin = 'round';
					ctx.beginPath();
					o.pts.forEach(function (p, i) { if (i) ctx.lineTo(p[0], p[1]); else ctx.moveTo(p[0], p[1]); });
					if (o.pts.length === 1) ctx.lineTo(o.pts[0][0] + 0.1, o.pts[0][1]);
					ctx.stroke();
				}
			});
			ctx.globalCompositeOperation = 'source-over';
			undoB.disabled = !ops.length;
			clearB.disabled = !ops.length;
			sendB.disabled = !ops.length;
		}
		function pos(e) {
			var r = cv.getBoundingClientRect();
			return [(e.clientX - r.left) * (cw / r.width), (e.clientY - r.top) * (ch / r.height)];
		}
		cv.addEventListener('pointerdown', function (e) {
			e.preventDefault();
			cv.setPointerCapture(e.pointerId);
			var p = pos(e);
			cur = tool === 'rect' ? { tool: 'rect', x: p[0], y: p[1], x2: p[0], y2: p[1] } : { tool: tool, size: size, pts: [p] };
			redraw();
		});
		cv.addEventListener('pointermove', function (e) {
			var p = pos(e);
			cursor.style.width = cursor.style.height = (size * cv.getBoundingClientRect().width / cw) + 'px';
			cursor.style.left = e.offsetX + 'px';
			cursor.style.top = e.offsetY + 'px';
			if (!cur) return;
			if (cur.tool === 'rect') { cur.x2 = p[0]; cur.y2 = p[1]; } else cur.pts.push(p);
			redraw();
		});
		function end() {
			if (!cur) return;
			if (cur.tool !== 'rect' || (Math.abs(cur.x2 - cur.x) > 4 && Math.abs(cur.y2 - cur.y) > 4)) ops.push(cur);
			cur = null;
			redraw();
		}
		cv.addEventListener('pointerup', end);
		cv.addEventListener('pointercancel', end);
		var cursor = h('.flxst-me-cursor');
		stage.appendChild(cursor);
		cv.addEventListener('pointerenter', function () { cursor.hidden = tool === 'rect'; });
		cv.addEventListener('pointerleave', function () { cursor.hidden = true; });
		cursor.hidden = true;

		var tools = h('.flxst-seg');
		function toolBtn(id, icon, label) {
			var b = h('button', { type: 'button', class: 'flxst-seg-b' + (tool === id ? ' is-on' : ''), title: label, onclick: function () {
				tool = id;
				Array.prototype.forEach.call(tools.children, function (c) { c.classList.toggle('is-on', c === b); });
			} }, S.icon(icon, 16), h('span', label));
			return b;
		}
		tools.appendChild(toolBtn('brush', 'brush', t('Fırça')));
		tools.appendChild(toolBtn('rect', 'square', t('Dikdörtgen')));
		tools.appendChild(toolBtn('eraser', 'eraser', t('Silgi')));
		var range = h('input', { type: 'range', min: 4, max: Math.round(Math.max(cw, ch) / 4), value: size, 'aria-label': t('Fırça boyutu'), oninput: function () { size = +range.value; } });
		var undoB = S.iconBtn('undo', t('Geri al'), function () { ops.pop(); redraw(); });
		var clearB = S.btn(null, t('Temizle'), function () { ops = []; redraw(); }, 'is-ghost');

		var prompt = h('textarea.flxst-input', { rows: 3, placeholder: t('Seçili alanda ne değişsin? Örn: tişörtü yeşil yap, yazıyı "Fidan Dik" olarak değiştir') });
		var enhB = S.btn('sparkle', t('Geliştir'), function () {
			if (!prompt.value.trim()) return S.toast(t('Önce kısa bir taslak yazın.'), 'error');
			enhB.disabled = true;
			S.api('POST', 'enhance', { prompt: prompt.value, mode: 'edit', refs: [] }).then(function (d) {
				prompt.value = d.prompt;
			}, S.fail).then(function () { enhB.disabled = false; });
		}, 'is-ghost');
		var sendB = S.btn('send', t('Düzenle'), function () {
			if (!prompt.value.trim()) return S.toast(t('Ne değişeceğini yazın.'), 'error');
			sendB.disabled = true;
			var layers = ops.map(function (o) { return o.tool; });
			Promise.resolve(opts.onSubmit(exportMask(), prompt.value.trim(), layers)).then(function () { m.close(); }, function (e) { S.fail(e); sendB.disabled = false; });
		}, 'is-primary');

		function exportMask() {
			var out = document.createElement('canvas');
			out.width = nw;
			out.height = nh;
			var c = out.getContext('2d');
			c.fillStyle = '#000';
			c.fillRect(0, 0, nw, nh);
			c.globalCompositeOperation = 'destination-out';
			c.drawImage(cv, 0, 0, nw, nh);
			return out.toDataURL('image/png');
		}

		var side = h('.flxst-me-side',
			h('.flxst-field', h('label', t('Araç')), tools),
			h('.flxst-field', h('label', t('Fırça boyutu')), range),
			h('.flxst-row', undoB, clearB),
			h('p.flxst-muted', t('Değişecek alanı boyayın. Boyanmayan yerler korunur.')),
			h('.flxst-field', h('label', t('Değişiklik')), prompt),
			h('.flxst-row', enhB, h('span.flxst-grow'), sendB)
		);
		var m = S.modal(t('Alanı düzenle'), h('.flxst-me', wrap, side), { wide: true, cls: 'flxst-me-modal', sticky: true, noFocus: true });
		redraw();
		return m;
	};

	/** Önce/sonra karşılaştırma kaydırıcısı. */
	S.compare = function (beforeUrl, afterUrl, w, hgt) {
		var after = h('img', { src: afterUrl, alt: '', draggable: 'false' });
		var before = h('img', { src: beforeUrl, alt: '', draggable: 'false' });
		var clip = h('.flxst-cmp-clip', before);
		var bar = h('.flxst-cmp-bar', h('span', S.icon('chevL', 14), S.icon('chevR', 14)));
		var box = h('.flxst-cmp', { style: { aspectRatio: (w || 1) + ' / ' + (hgt || 1) } }, after, clip, bar,
			h('span.flxst-cmp-l', t('Önce')), h('span.flxst-cmp-r', t('Sonra')));
		function set(x) {
			var r = box.getBoundingClientRect();
			var p = Math.max(0, Math.min(1, (x - r.left) / r.width)) * 100;
			clip.style.clipPath = 'inset(0 ' + (100 - p) + '% 0 0)';
			bar.style.left = p + '%';
		}
		var drag = false;
		box.addEventListener('pointerdown', function (e) { drag = true; box.setPointerCapture(e.pointerId); set(e.clientX); });
		box.addEventListener('pointermove', function (e) { if (drag) set(e.clientX); });
		box.addEventListener('pointerup', function () { drag = false; });
		clip.style.clipPath = 'inset(0 50% 0 0)';
		bar.style.left = '50%';
		return box;
	};
})();
