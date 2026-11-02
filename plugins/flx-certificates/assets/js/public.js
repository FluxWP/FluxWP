/* Flux Certificates · herkese açık sayfa: paylaş, kopyala, tarayıcıda çizim (yedek), sorgulama formu */
(function () {
	'use strict';
	var L = window.FLXS_PUB || {};
	function t(k, d) { return L[k] || d; }

	function toast(msg) {
		var t = document.createElement('div');
		t.className = 'flxs-toast-pub';
		t.textContent = msg;
		document.body.appendChild(t);
		setTimeout(function () { t.remove(); }, 2200);
	}

	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text).then(function () { toast(t('copied', 'Link kopyalandı')); });
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); toast(t('copied', 'Link kopyalandı')); } catch (e) { window.prompt(t('copyHint', 'Linki kopyalayın:'), text); }
		ta.remove();
		return Promise.resolve();
	}

	document.addEventListener('click', function (e) {
		var c = e.target.closest('[data-flxs-copy]');
		if (c) {
			e.preventDefault();
			copy(c.getAttribute('data-flxs-copy'));
			return;
		}
		var s = e.target.closest('[data-flxs-share]');
		if (s) {
			e.preventDefault();
			var url = s.getAttribute('data-url');
			if (navigator.share) {
				navigator.share({ title: s.getAttribute('data-title') || document.title, url: url }).catch(function () {});
			} else {
				copy(url);
			}
		}
	});

	// Çizici (pdf-lib, fontkit, pdf.js ~2,7 MB) sayfayla yüklenmez; yalnızca gerektiğinde, sırayla eklenir
	var G = window.FLXS_GEN;
	var rendererP = null;
	function loadRenderer() {
		if (window.FLXSRender) return Promise.resolve(window.FLXSRender);
		if (!G || !G.scripts) return Promise.reject(new Error('renderer'));
		if (!rendererP) {
			window.FLXS_RENDER = window.FLXS_RENDER || G.cfg || {};
			rendererP = new Promise(function (res, rej) {
				var left = G.scripts.length;
				G.scripts.forEach(function (src) {
					var el = document.createElement('script');
					el.src = src;
					el.async = false; // paralel indir, sırayla çalıştır
					el.onload = function () { if (--left === 0) { if (window.FLXSRender) res(window.FLXSRender); else rej(new Error('renderer')); } };
					el.onerror = function () { rendererP = null; rej(new Error('renderer')); };
					document.body.appendChild(el);
				});
			});
		}
		return rendererP;
	}

	function post(fd) {
		return fetch(G.endpoint, { method: 'POST', body: fd, credentials: 'omit' }).then(function (r) { return r.json().catch(function () { return {}; }); });
	}
	// Hâlâ eksik olan dosyalar (sayfa önbellekten eski haliyle gelmiş olabilir)
	function stillNeeded() {
		var fd = new FormData();
		fd.append('token', G.token);
		return post(fd).then(function (j) { return (j && j.need) || []; });
	}
	// Eksik dosyaları üret ve her birini ayrı istekle yükle (biri büyük gelirse diğerleri yine kaydedilir)
	function uploadMissing(bytes, need) {
		var R = window.FLXSRender;
		if (!R || !need || !need.length || !window.FormData) return Promise.resolve();
		var pg = (G.render && G.render.template && G.render.template.page) || {};
		var make = {
			pdf: function () { return Promise.resolve({ blob: new Blob([bytes], { type: 'application/pdf' }), name: 'sertifika.pdf' }); },
			image: function () { return R.toJpeg(bytes, (+pg.w >= +pg.h) ? 1600 : 1100, 0.82).then(function (x) { return { blob: x.blob, name: 'sertifika.jpg' }; }); },
			og: function () { return R.toShareImage(bytes, { title: G.title, name: G.name, org: (window.FLXS_RENDER || {}).org }).then(function (x) { return { blob: x.blob, name: 'paylasim.jpg' }; }); }
		};
		return need.reduce(function (chain, type) {
			return chain.then(function () {
				if (!make[type]) return;
				return make[type]().then(function (f) {
					if (G.max && f.blob.size > G.max) { if (window.console) console.warn('flxs: ' + type + ' sunucu yükleme sınırını aşıyor', f.blob.size, G.max); return; }
					var fd = new FormData();
					fd.append('token', G.token);
					fd.append(type, f.blob, f.name);
					return post(fd);
				}).catch(function (err) { if (window.console) console.warn(err); });
			});
		}, Promise.resolve());
	}
	function pdfBytes() {
		return loadRenderer().then(function (R) {
			if (G.pdf) return fetch(G.pdf, { credentials: 'omit' }).then(function (r) { if (!r.ok) throw new Error('pdf'); return r.arrayBuffer(); }).then(function (b) { return new Uint8Array(b); });
			return R.renderPdf(G.render.template, G.render.values || {}, G.render.meta || {}, {});
		});
	}
	// Aynı tarayıcı kısa sürede tekrar denemesin
	function triedRecently() {
		try { return Date.now() - (+localStorage.getItem('flxs-gen-' + G.code) || 0) < 6 * 3600 * 1000; } catch (e) { return false; }
	}
	function markTried() {
		try { localStorage.setItem('flxs-gen-' + G.code, String(Date.now())); } catch (e) {}
	}
	function whenIdle(fn) {
		var go = function () { (window.requestIdleCallback || function (f) { setTimeout(f, 1500); })(fn, { timeout: 4000 }); };
		if (document.readyState === 'complete') go(); else window.addEventListener('load', go);
	}

	var dl = document.querySelector('[data-flxs-download]');
	var cv = document.querySelector('canvas[data-flxs-render]');
	if (cv && G) {
		// Sertifika görseli henüz yok: tarayıcıda çiz, indirme butonu da bu PDF'i versin
		var d;
		try { d = JSON.parse(cv.getAttribute('data-flxs-render')); } catch (err) { d = null; }
		if (d) {
			var fig = cv.parentNode;
			fig.classList.add('is-loading');
			loadRenderer().then(function (R) {
				return R.renderPdf(d.template, d.values || {}, d.meta || {}, {}).then(function (bytes) {
					var blobUrl = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
					if (dl) {
						dl.href = blobUrl;
						dl.setAttribute('download', d.filename || 'sertifika.pdf');
					}
					var w = Math.min(1600, Math.max(600, fig.clientWidth || 900));
					return R.renderToCanvas(bytes, cv, w).then(function () {
						cv.style.width = '100%';
						cv.style.height = 'auto';
						fig.classList.remove('is-loading');
						if (G.need && G.need.length && !triedRecently()) {
							markTried();
							whenIdle(function () { uploadMissing(bytes, G.need); });
						}
					});
				});
			}).catch(function (err) {
				fig.classList.remove('is-loading');
				if (window.console) console.error(err);
			});
		}
	} else if (G && G.need && G.need.length) {
		// Görsel var, PDF ya da paylaşım görseli eksik: sayfa açıldıktan sonra boşta üret
		if (dl && G.need.indexOf('pdf') > -1) {
			// PDF dosyası yokken indirme butonu sayfaya geri dönmesin: tıklanınca tarayıcıda oluştur
			dl.addEventListener('click', function (e) {
				if (dl.getAttribute('href').indexOf('blob:') === 0) return;
				e.preventDefault();
				dl.classList.add('is-busy');
				pdfBytes().then(function (bytes) {
					dl.href = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
					dl.setAttribute('download', G.filename || 'sertifika.pdf');
					dl.classList.remove('is-busy');
					dl.click();
				}).catch(function (err) { dl.classList.remove('is-busy'); if (window.console) console.error(err); });
			});
		}
		if (!triedRecently()) {
			whenIdle(function () {
				stillNeeded().then(function (need) {
					if (!need.length) return;
					markTried();
					return pdfBytes().then(function (bytes) { return uploadMissing(bytes, need); });
				}).catch(function (err) { if (window.console) console.warn(err); });
			});
		}
	}

	// Sertifika sorgulama formu
	document.querySelectorAll('form.flxs-lookup').forEach(function (f) {
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var out = f.querySelector('.flxs-lookup__result');
			var btn = f.querySelector('button[type=submit]');
			out.classList.remove('is-err');
			out.textContent = t('sending', 'Gönderiliyor…');
			btn.disabled = true;
			fetch(f.getAttribute('data-endpoint'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'omit',
				body: JSON.stringify({ contact: f.contact.value, website: f.website ? f.website.value : '', lang: L.lang || '' })
			}).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok && j && j.ok !== false, j: j }; });
			}).then(function (res) {
				out.textContent = (res.j && res.j.message) || (res.ok ? t('ok', 'Tamam.') : t('error', 'Bir hata oluştu.'));
				if (!res.ok) out.classList.add('is-err');
				else f.contact.value = '';
			}).catch(function () {
				out.textContent = t('network', 'Bağlantı hatası. Lütfen tekrar deneyin.');
				out.classList.add('is-err');
			}).then(function () { btn.disabled = false; });
		});
	});
})();
