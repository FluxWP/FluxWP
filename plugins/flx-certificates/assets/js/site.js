/* Flux Certificates · sitedeki etkinlik bölümleri: harita tıklayınca yüklenir, sayaç yumuşak sayar */
(function () {
	'use strict';
	var L = window.FLXS_SITE || {};
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-map]');
		if (!b) return;
		var f = document.createElement('iframe');
		var src = b.getAttribute('data-map') || '';
		if (!/^https:\/\//i.test(src)) return;
		f.src = src;
		f.loading = 'lazy';
		f.title = L.mapTitle || 'Etkinlik konumu';
		f.referrerPolicy = 'no-referrer-when-downgrade';
		f.setAttribute('allowfullscreen', '');
		b.parentNode.replaceChild(f, b);
	});
	if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	var io = new IntersectionObserver(function (es) {
		es.forEach(function (en) {
			if (!en.isIntersecting) return;
			io.unobserve(en.target);
			var el = en.target, to = +el.getAttribute('data-count'), t0 = null;
			if (!to) return;
			var step = function (t) {
				if (!t0) t0 = t;
				var k = Math.min(1, (t - t0) / 1100), v = Math.round(to * (1 - Math.pow(1 - k, 3)));
				el.textContent = v.toLocaleString(L.locale || document.documentElement.lang || undefined);
				if (k < 1) requestAnimationFrame(step);
			};
			requestAnimationFrame(step);
		});
	}, { threshold: 0.4 });
	document.querySelectorAll('[data-count]').forEach(function (el) { io.observe(el); });
})();
