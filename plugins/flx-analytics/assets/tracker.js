/* Flux Analiz sayacı. Sayfa yolu, yönlendiren site, ekran genişliği, dil ve saat dilimi gönderilir.
   Çerez yalnız ?r=kod ile gelende (flxan_r): referans sonraki sayfalarda da tanınsın. */
(function () {
	'use strict';
	var C = window.flxanCfg;
	if (!C || !C.u) return;
	var n = navigator, d = document, l = location;
	if (n.webdriver || window._phantom || window.callPhantom || window.Cypress || /bot|crawl|spider|headless|lighthouse|preview/i.test(n.userAgent)) return;
	try { if (localStorage.getItem('flxan_ignore') === '1') return; } catch (e) {}
	if (C.dnt && (n.doNotTrack === '1' || window.doNotTrack === '1' || n.globalPrivacyControl === true)) return;

	// Referans kodu: ?r=kod → çerez (birinci taraf, SameSite=Lax), adres çubuğundan temizlenir.
	var RC = '';
	(function () {
		var q = new URLSearchParams(l.search), v = (q.get('r') || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 32);
		if (v) {
			RC = v;
			try { d.cookie = 'flxan_r=' + v + ';path=/;max-age=' + ((C.rd || 30) * 86400) + ';SameSite=Lax' + (l.protocol === 'https:' ? ';Secure' : ''); } catch (e) {}
			try {
				q.delete('r');
				var qs = q.toString();
				history.replaceState(history.state, '', l.pathname + (qs ? '?' + qs : '') + l.hash);
			} catch (e) {}
		} else {
			var m = d.cookie.match(/(?:^|;\s*)flxan_r=([a-z0-9_-]{1,32})/);
			if (m) RC = m[1];
		}
	})();
	var TZ = '';
	try { TZ = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

	function send(data) {
		if (C.t) data.t = C.t;
		if (C.g) data.g = C.g;
		if (RC) data.rc = RC;
		var body = new URLSearchParams(data);
		try {
			if (n.sendBeacon && n.sendBeacon(C.u, body)) return;
		} catch (e) {}
		try { fetch(C.u, { method: 'POST', body: body, keepalive: true, credentials: 'omit' }).catch(function () {}); } catch (e) {}
	}

	function utm() {
		var o = {}, q = new URLSearchParams(l.search), h = new URLSearchParams(l.hash.slice(1));
		[['utm_source', 'us'], ['utm_medium', 'um'], ['utm_campaign', 'uc']].forEach(function (p) {
			var v = q.get(p[0]) || h.get(p[0]);
			if (v) o[p[1]] = v.slice(0, 100);
		});
		return o;
	}

	function ref() {
		var r = d.referrer || '';
		if (!r) return '';
		try {
			var h = new URL(r).hostname.replace(/^www\./, '');
			if (h === l.hostname.replace(/^www\./, '')) return '';
		} catch (e) { return ''; }
		return r.slice(0, 255);
	}

	function path() {
		var p = C.p || l.pathname || '/';
		return p.slice(0, 255);
	}

	var tracked = false;
	function pageview() {
		var data = { k: 'p', p: path(), i: C.i || 0, r: ref(), w: (screen && screen.width) || 0, l: (n.language || '').slice(0, 2) };
		var u = utm(), k;
		for (k in u) data[k] = u[k];
		if (C.x) data.x = 1;
		if (TZ) data.z = TZ.slice(0, 64);
		send(data);
		if (C.s) event('search', C.s);
	}
	function first() {
		if (tracked) return;
		tracked = true;
		pageview();
		flush();
	}
	// Sunucunun sıraya koyduğu olaylar (sepete ekleme, kupon, sipariş): "flxan_pe" çerezinden okunur, gönderilir, silinir.
	function flush() {
		var m = d.cookie.match(/(?:^|;\s*)flxan_pe=([^;]*)/);
		if (!m) return;
		d.cookie = 'flxan_pe=; path=' + (C.cp || '/') + '; max-age=0; SameSite=Lax';
		var q;
		try { q = JSON.parse(decodeURIComponent(m[1].replace(/\+/g, ' '))); } catch (er) { try { q = JSON.parse(m[1]); } catch (er2) { return; } }
		if (!q || !q.length) return;
		for (var i = 0; i < q.length && i < 6; i++) if (q[i] && q[i][0]) event(q[i][0], q[i][1] || '');
	}

	function event(name, param) {
		send({ k: 'e', n: String(name).slice(0, 40), v: String(param || '').slice(0, 190), p: path() });
	}
	window.flxan = { track: event };

	if (d.prerendering) d.addEventListener('prerenderingchange', first, { once: true });
	else if (d.visibilityState === 'hidden') d.addEventListener('visibilitychange', function f() { if (d.visibilityState === 'visible') { d.removeEventListener('visibilitychange', f); first(); } });
	else first();
	window.addEventListener('pageshow', function (e) { if (e.persisted) pageview(); });

	var EXT = /\.(pdf|zip|rar|7z|docx?|xlsx?|pptx?|odt|ods|csv|txt|mp3|mp4|mov|apk|dmg|exe|epub)(\?|#|$)/i;
	function onClick(e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href],[data-flxan-event]') : null;
		if (!a) return;
		var ev = a.getAttribute('data-flxan-event');
		if (ev) { event(ev, a.getAttribute('data-flxan-param') || a.textContent.trim().slice(0, 80)); return; }
		var href = a.getAttribute('href') || '';
		if (C.ec && /^tel:/i.test(href)) return event('tel', decodeURIComponent(href.slice(4)).replace(/[^\d+]/g, ''));
		if (C.ec && /^mailto:/i.test(href)) return event('mail', decodeURIComponent(href.slice(7).split('?')[0]).toLowerCase());
		var url;
		try { url = new URL(a.href, l.href); } catch (er) { return; }
		if (!/^https?:$/.test(url.protocol)) return;
		var host = url.hostname.replace(/^www\./, '');
		if (C.ec && /(^|\.)(wa\.me|whatsapp\.com)$/i.test(host)) return event('whatsapp', path());
		if (C.ed && EXT.test(url.pathname)) return event('download', decodeURIComponent(url.pathname.split('/').pop()).slice(0, 190));
		if (C.eo && host !== l.hostname.replace(/^www\./, '')) return event('outbound', host);
	}
	// AJAX ile sepete ekleme / çıkarma / kupon (klasik ve blok sepet): çerezdeki olaylar hemen gönderilir.
	if (window.jQuery) window.jQuery(d.body).on('added_to_cart removed_from_cart applied_coupon updated_cart_totals updated_checkout', function () { setTimeout(flush, 50); });
	d.addEventListener('wc-blocks_added_to_cart', function () { setTimeout(flush, 300); });
	d.addEventListener('wc-blocks_removed_from_cart', function () { setTimeout(flush, 300); });
	// Ödeme yöntemi seçimi (klasik: payment_method, blok: radio-control-wc-payment-method-options).
	if (C.co) {
		var payLast = '';
		d.addEventListener('change', function (e) {
			var x = e.target;
			if (!x || x.type !== 'radio' || !/^(payment_method|radio-control-wc-payment-method-options)$/.test(x.name) || !x.value || x.value === payLast) return;
			payLast = x.value;
			event('payment', x.value);
		}, true);
	}
	// Ödeme sayfası adımları: forma ilk dokunuş ve "Ödeme yap" tıklaması (klasik + blok ödeme).
	if (C.co) {
		var started = false, submitted = false;
		d.addEventListener('focusin', function (e) {
			if (started || !e.target.closest || !e.target.closest('form.checkout,.wc-block-checkout,.wp-block-woocommerce-checkout,form.woocommerce-checkout')) return;
			started = true;
			event('checkout', 'form');
		}, true);
		d.addEventListener('click', function (e) {
			if (submitted || !e.target.closest || !e.target.closest('#place_order,.wc-block-components-checkout-place-order-button,[data-flxan-checkout-submit]')) return;
			submitted = true;
			if (!started) { started = true; event('checkout', 'form'); }
			event('checkout', 'submit');
		}, true);
	}

	if (C.eo || C.ed || C.ec || C.ev) {
		d.addEventListener('click', onClick, true);
		d.addEventListener('auxclick', function (e) { if (e.button === 1) onClick(e); }, true);
	}
})();
