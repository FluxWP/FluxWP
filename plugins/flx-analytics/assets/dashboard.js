/* Flux Analiz: Başlangıç ekranı widget'larını sekme açıkken yeniler: Canlı 5 sn'de, diğerleri 30 sn'de bir. */
(function () {
	'use strict';
	var C = window.FLXAN_DW;
	if (!C || !window.fetch) return;
	var boxes = document.querySelectorAll('.flxan-dw[data-w]');
	if (!boxes.length) return;
	var LIVE = (C.live || 5) * 1000, ALL = (C.every || 30) * 1000;
	var timer = 0, lastAll = Date.now(), failed = false;
	function open(b) { var pb = b.closest('.postbox'); return !pb || !pb.classList.contains('closed'); }
	function tick() {
		clearTimeout(timer);
		if (document.hidden) return;
		var full = Date.now() - lastAll >= ALL, ids = [];
		boxes.forEach(function (b) {
			var id = b.getAttribute('data-w');
			if (open(b) && (full || id === 'live')) ids.push(id);
		});
		if (full) lastAll = Date.now();
		if (!ids.length) { timer = setTimeout(tick, LIVE); return; }
		fetch(C.rest + '?_locale=user&w=' + ids.join(',') + '&_t=' + Date.now(), { headers: { 'X-WP-Nonce': C.nonce, 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
			.then(function (j) {
				failed = false;
				boxes.forEach(function (b) {
					var id = b.getAttribute('data-w');
					if (typeof j[id] === 'string' && j[id] !== b.innerHTML) {
						b.innerHTML = j[id];
						b.classList.remove('flxan-dw--fresh'); void b.offsetWidth; b.classList.add('flxan-dw--fresh');
					}
				});
			})
			.catch(function () { failed = true; })
			.then(function () { timer = setTimeout(tick, failed ? 300000 : LIVE); });
	}
	document.addEventListener('visibilitychange', function () { clearTimeout(timer); if (!document.hidden) timer = setTimeout(tick, 1000); });
	timer = setTimeout(tick, LIVE);
})();
