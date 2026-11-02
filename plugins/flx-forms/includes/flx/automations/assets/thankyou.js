/* Flux Forms · Automations: shows the certificate itself on the thank-you card.
   A certificate created a moment ago has no image yet, so it is drawn here with Flux Certificates's
   own renderer (loaded on demand) and its missing files are uploaded once, the same way the
   certificate page does it. Download and share then use that drawing. */
(function () {
	'use strict';
	var L = window.FLXF_TY || {};
	var loading = null;

	function loadRenderer(d) {
		if (window.FLXSRender) return Promise.resolve();
		if (loading) return loading;
		if (d.config && !window.FLXS_RENDER) window.FLXS_RENDER = d.config;
		loading = (d.scripts || []).reduce(function (p, src) {
			return p.then(function () {
				return new Promise(function (resolve, reject) {
					var s = document.createElement('script');
					s.src = src;
					s.async = false;
					s.onload = resolve;
					s.onerror = function () { reject(new Error(src)); };
					document.head.appendChild(s);
				});
			});
		}, Promise.resolve()).then(function () {
			if (!window.FLXSRender) throw new Error('FLXSRender');
		});
		loading.catch(function () { loading = null; });
		return loading;
	}

	function upload(d, bytes) {
		var u = d.upload, R = window.FLXSRender;
		if (!u || !u.need || !u.need.length || !window.FormData) return;
		var fd = new FormData(), jobs = [], pg = (d.render.template && d.render.template.page) || {};
		fd.append('token', u.token);
		if (u.need.indexOf('pdf') > -1) fd.append('pdf', new Blob([bytes], { type: 'application/pdf' }), 'sertifika.pdf');
		if (u.need.indexOf('image') > -1) {
			jobs.push(R.toJpeg(bytes, (+pg.w >= +pg.h) ? 1600 : 1100, 0.82).then(function (img) { fd.append('image', img.blob, 'sertifika.jpg'); }));
		}
		if (u.need.indexOf('og') > -1 && R.toShareImage) {
			jobs.push(R.toShareImage(bytes, { title: d.title, name: d.name, org: (window.FLXS_RENDER || {}).org }).then(function (og) { fd.append('og', og.blob, 'paylasim.jpg'); }));
		}
		Promise.all(jobs.map(function (j) { return j.catch(function () {}); })).then(function () {
			return fetch(u.endpoint, { method: 'POST', body: fd, credentials: 'omit' });
		}).catch(function () {});
	}

	function toast(card, msg) {
		var t = document.createElement('div');
		t.className = 'flxf-cert-toast';
		t.setAttribute('role', 'status');
		t.textContent = msg;
		card.appendChild(t);
		setTimeout(function () { t.remove(); }, 2200);
	}

	function copy(card, text) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(function () { toast(card, L.copied || 'Link copied'); });
			return;
		}
		window.prompt('', text);
	}

	function setup(card) {
		if (card.getAttribute('data-flxf-ready')) return;
		card.setAttribute('data-flxf-ready', '1');
		var d;
		try { d = JSON.parse(card.getAttribute('data-flxf-cert') || '{}'); } catch (e) { return; }
		var fig = card.querySelector('.flxf-cert-card__figure');
		var pdfBtn = card.querySelector('[data-flxf-pdf]');
		var shareBtn = card.querySelector('[data-flxf-share]');
		var imageBlob = null;

		if (pdfBtn && d.pdf) {
			pdfBtn.href = d.pdf;
			pdfBtn.setAttribute('download', d.filename || 'sertifika.pdf');
			pdfBtn.removeAttribute('target');
		}

		if (fig && !d.image && d.render) {
			var cv = fig.querySelector('canvas');
			fig.setAttribute('aria-busy', 'true');
			fig.title = L.loading || '';
			loadRenderer(d).then(function () {
				return window.FLXSRender.renderPdf(d.render.template, d.render.values || {}, d.render.meta || {}, {});
			}).then(function (bytes) {
				upload(d, bytes);
				if (pdfBtn) {
					pdfBtn.href = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
					pdfBtn.setAttribute('download', d.filename || 'sertifika.pdf');
					pdfBtn.removeAttribute('target');
				}
				var w = Math.min(1600, Math.max(700, Math.round((fig.clientWidth || 600) * (window.devicePixelRatio || 1))));
				return window.FLXSRender.renderToCanvas(bytes, cv, w);
			}).then(function () {
				/* An <img> can be long-pressed and saved on a phone; a canvas cannot. */
				cv.toBlob(function (blob) {
					if (!blob) return;
					imageBlob = blob;
					var img = document.createElement('img');
					img.className = 'flxf-cert-card__img';
					img.alt = d.title || '';
					img.src = URL.createObjectURL(blob);
					img.onload = function () { cv.replaceWith(img); };
				}, 'image/jpeg', 0.9);
			}).catch(function () {
				var p = document.createElement('p');
				p.className = 'flxf-cert-card__err';
				p.textContent = L.failed || '';
				fig.replaceWith(p);
				fig = null;
			}).then(function () {
				if (fig) {
					fig.classList.remove('is-loading');
					fig.removeAttribute('aria-busy');
					fig.removeAttribute('title');
				}
			});
		}

		if (shareBtn) {
			shareBtn.addEventListener('click', function (e) {
				if (!navigator.share) return; /* desktop: the link opens WhatsApp */
				e.preventDefault();
				var data = { title: d.title || document.title, text: d.title || '', url: d.url };
				var withText = function () { data.text = (d.title ? d.title + '\n' : '') + d.url; delete data.url; };
				var get = imageBlob ? Promise.resolve(imageBlob)
					: (d.image ? fetch(d.image).then(function (r) { return r.ok ? r.blob() : null; }).catch(function () { return null; }) : Promise.resolve(null));
				get.then(function (blob) {
					if (blob && window.File) {
						var file = new File([blob], (d.filename || 'sertifika').replace(/\.pdf$/i, '') + '.jpg', { type: blob.type || 'image/jpeg' });
						/* Some apps drop the url when a file is attached, so the link goes in the text too. */
						if (navigator.canShare && navigator.canShare({ files: [file] })) { data.files = [file]; withText(); }
					}
					return navigator.share(data);
				}).catch(function (err) {
					if (err && err.name === 'AbortError') return;
					if (data.files) { return navigator.share({ title: data.title, url: d.url }).catch(function () { copy(card, d.url); }); }
					copy(card, d.url);
				});
			});
		}
	}

	function scan(root) {
		var list = (root || document).querySelectorAll('.flxf-cert-card[data-flxf-cert]');
		for (var i = 0; i < list.length; i++) setup(list[i]);
	}

	document.addEventListener('flxf:thankyou', function (e) { scan(e.detail && e.detail.root); });
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(); });
	else scan();
})();
