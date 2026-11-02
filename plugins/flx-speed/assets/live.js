/* Flux Speed: canlı sunucu kutuları WordPress Heartbeat ile yenilenir (ek istek açılmaz). */
(function ($) {
	'use strict';
	var box = document.querySelector('[data-flxp-live]');
	if (!box || !window.wp || !wp.heartbeat) {
		return;
	}
	var cfg = window.flxpLive || {};
	// Hazır olunca: erken tick, çekirdeğin wp-auth-check'i kurulmadan önce gelmesin.
	$(function () {
		if (cfg.interval) {
			wp.heartbeat.interval(cfg.interval);
		}
	});
	$(document).on('heartbeat-send.flxp', function (e, data) {
		if (!document.hidden) {
			data.flxp_live = 1;
		}
	});
	$(document).on('heartbeat-tick.flxp', function (e, data) {
		var v = data && data.flxp_live;
		if (!v) {
			return;
		}
		Object.keys(v).forEach(function (k) {
			var c = v[k], el = box.querySelector('[data-k="' + k + '"]');
			if (!el) {
				return;
			}
			el.querySelector('b').textContent = c.text;
			el.querySelector('small').textContent = c.sub || '';
			var bar = el.querySelector('.flxp-bar em');
			if (bar && c.pct !== null) {
				bar.style.width = Math.max(0, Math.min(100, c.pct)) + '%';
			}
			el.classList.toggle('err', c.pct !== null && c.pct >= 90);
			el.classList.toggle('warn', c.pct !== null && c.pct >= 70 && c.pct < 90);
		});
	});
})(jQuery);
