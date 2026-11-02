/* Flux Donate · thank-you page certificate preview (ported from Flux WooCommerce Bağış). A new certificate has no image yet:
   Flux Certificates's renderer is loaded on demand and draws it in the browser; missing files are uploaded once, as on the certificate page. GPL v2 or later. */
(function () {
	'use strict';
	var L = window.FLXB_WC_CERT || {};
	var loading = null;

	function loadRenderer(d) {
		if (window.FLXSRender) return Promise.resolve();
		if (loading) return loading;
		if (d.config && !window.FLXS_RENDER) window.FLXS_RENDER = d.config;
		loading = (d.scripts || []).reduce(function (p, src) {
			return p.then(function () {
				return new Promise(function (resolve, reject) {
					var s = document.createElement('script');
					s.src = src; s.async = false; s.onload = resolve;
					s.onerror = function () { reject(new Error(src)); };
					document.head.appendChild(s);
				});
			});
		}, Promise.resolve()).then(function () { if (!window.FLXSRender) throw new Error('FLXSRender'); });
		loading.catch(function () { loading = null; });
		return loading;
	}

	function upload(d, bytes) {
		var u = d.upload, R = window.FLXSRender;
		if (!u || !u.need || !u.need.length || !window.FormData) return;
		var fd = new FormData(), jobs = [], pg = (d.render.template && d.render.template.page) || {};
		fd.append('token', u.token);
		if (u.need.indexOf('pdf') > -1) fd.append('pdf', new Blob([bytes], { type: 'application/pdf' }), 'sertifika.pdf');
		if (u.need.indexOf('image') > -1) jobs.push(R.toJpeg(bytes, (+pg.w >= +pg.h) ? 1600 : 1100, 0.82).then(function (img) { fd.append('image', img.blob, 'sertifika.jpg'); }));
		if (u.need.indexOf('og') > -1 && R.toShareImage) jobs.push(R.toShareImage(bytes, { title: d.title, name: d.name, org: (window.FLXS_RENDER || {}).org }).then(function (og) { fd.append('og', og.blob, 'paylasim.jpg'); }));
		Promise.all(jobs.map(function (j) { return j.catch(function () {}); })).then(function () {
			return fetch(u.endpoint, { method: 'POST', body: fd, credentials: 'omit' });
		}).catch(function () {});
	}

	function toast(msg) {
		var t = document.createElement('div');
		t.className = 'flxwb-toast'; t.setAttribute('role', 'status'); t.textContent = msg;
		document.body.appendChild(t);
		setTimeout(function () { t.remove(); }, 2000);
	}

	function setup(card) {
		var d;
		try { d = JSON.parse(card.getAttribute('data-flxwb-cert') || '{}'); } catch (e) { return; }
		var fig = card.querySelector('.flxwb-cert__figure');
		var pdfBtn = card.querySelector('[data-flxwb-pdf]');
		var imageBlob = null;
		if (pdfBtn && d.pdf) { pdfBtn.href = d.pdf; pdfBtn.setAttribute('download', d.filename || 'sertifika.pdf'); pdfBtn.removeAttribute('target'); }
		if (!fig || d.image || !d.render) return;
		var cv = fig.querySelector('canvas');
		fig.setAttribute('aria-busy', 'true');
		loadRenderer(d).then(function () {
			return window.FLXSRender.renderPdf(d.render.template, d.render.values || {}, d.render.meta || {}, {});
		}).then(function (bytes) {
			upload(d, bytes);
			if (pdfBtn) {
				pdfBtn.href = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
				pdfBtn.setAttribute('download', d.filename || 'sertifika.pdf'); pdfBtn.removeAttribute('target');
			}
			var w = Math.min(1600, Math.max(700, Math.round((fig.clientWidth || 600) * (window.devicePixelRatio || 1))));
			return window.FLXSRender.renderToCanvas(bytes, cv, w);
		}).then(function () {
			cv.toBlob(function (blob) {
				if (!blob) return;
				imageBlob = blob; card._flxwbImage = blob;
				var img = document.createElement('img');
				img.alt = d.title || ''; img.src = URL.createObjectURL(blob);
				img.onload = function () { cv.replaceWith(img); };
			}, 'image/jpeg', 0.9);
		}).catch(function () {
			var p = document.createElement('p');
			p.className = 'flxwb-cert__err'; p.textContent = L.failed || '';
			fig.replaceWith(p); fig = null;
		}).then(function () {
			if (fig) { fig.classList.remove('is-loading'); fig.removeAttribute('aria-busy'); }
		});
	}

	/* Share: the system share sheet on phones (with the image), a WhatsApp link on desktops. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-flxwb-share]');
		if (!b || !navigator.share) return;
		e.preventDefault();
		var url = b.getAttribute('data-url'), title = b.getAttribute('data-title') || document.title;
		var card = b.closest('.flxwb-cert');
		var blob = card && card._flxwbImage;
		var data = { title: title, text: title + '\n' + url };
		if (blob && window.File) {
			var f = new File([blob], 'sertifika.jpg', { type: 'image/jpeg' });
			if (navigator.canShare && navigator.canShare({ files: [f] })) data.files = [f];
		}
		if (!data.files) { data = { title: title, url: url }; }
		navigator.share(data).catch(function (err) {
			if (err && err.name === 'AbortError') return;
			if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { toast(L.copied || 'OK'); });
		});
	});

	function init() { document.querySelectorAll('.flxwb-cert[data-flxwb-cert]').forEach(setup); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
