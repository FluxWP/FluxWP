/* Flux Ads: atıf çerezi + Meta / TikTok / GA4 pixel'leri, yalnız çerez onayından sonra. */
(function () {
	'use strict';
	var c = window.flxAds;
	if (!c) return;
	var started = false;

	function cookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : '';
	}
	function setCookie(name, value, days) {
		document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;max-age=' + days * 86400 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
	}

	/* Onay: FluxWP teması banner'ı (flx_cerez = "kabul:<rev>"), WP Consent API ya da "her zaman". */
	function consentOk() {
		if (c.consent === 'always') return true;
		if (typeof window.wp_has_consent === 'function') return window.wp_has_consent('marketing');
		var f = window.flxConsent;
		if (!f) return !c.theme;
		if (!f.active) return true;
		var v = cookie('flx_cerez');
		try { v = v || localStorage.getItem('flx_cerez') || ''; } catch (e) {}
		return v === 'kabul:' + f.rev;
	}

	/* URL'deki UTM / tıklama kimliği → flxads_t çerezi { f: ilk dokunuş, l: son dokunuş }. */
	function capture() {
		var p = new URLSearchParams(location.search), t = {}, any = false;
		c.keys.forEach(function (k) {
			var v = (p.get(k) || '').trim();
			if (v) { t[k] = v.slice(0, 150); any = true; }
		});
		setCookie('flxads_ok', '1', c.days);
		if (!any) return;
		t.lp = location.pathname.slice(0, 200);
		t.ts = Math.floor(Date.now() / 1000);
		var cur = {};
		try { cur = JSON.parse(cookie('flxads_t') || '{}') || {}; } catch (e) { cur = {}; }
		if (!cur.f) cur.f = t;
		cur.l = t;
		var s = JSON.stringify(cur);
		if (s.length > 3000) { cur.f = { lp: cur.f.lp, ts: cur.f.ts, utm_source: cur.f.utm_source, utm_campaign: cur.f.utm_campaign }; s = JSON.stringify(cur); }
		setCookie('flxads_t', s, c.days);
	}

	function loadMeta(id) {
		/* Meta Pixel temel kodu */
		!function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
		window.fbq('init', id);
		window.fbq('track', 'PageView');
	}

	function loadTikTok(id) {
		/* TikTok Pixel temel kodu */
		!function (w, d, t) { w.TiktokAnalyticsObject = t; var ttq = w[t] = w[t] || []; ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent']; ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; }; for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]); ttq.instance = function (t) { for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]); return e; }; ttq.load = function (e, n) { var r = 'https://analytics.tiktok.com/i18n/pixel/events.js'; ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r; ttq._t = ttq._t || {}; ttq._t[e] = +new Date(); ttq._o = ttq._o || {}; ttq._o[e] = n || {}; var o = d.createElement('script'); o.type = 'text/javascript'; o.async = !0; o.src = r + '?sdkid=' + e + '&lib=' + t; var a = d.getElementsByTagName('script')[0]; a.parentNode.insertBefore(o, a); }; }(window, document, 'ttq');
		window.ttq.load(id);
		window.ttq.page();
	}

	function loadGa(id) {
		window.dataLayer = window.dataLayer || [];
		if (typeof window.gtag !== 'function') window.gtag = function () { window.dataLayer.push(arguments); };
		if (!c.loadGa) return; // tema etiketi zaten yüklüyor
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
		document.head.appendChild(s);
		window.gtag('js', new Date());
		window.gtag('config', id);
	}

	var TT = { ViewContent: 'ViewContent', InitiateCheckout: 'InitiateCheckout', Purchase: 'CompletePayment', Lead: 'SubmitForm' };
	var GA = { ViewContent: 'view_item', InitiateCheckout: 'begin_checkout', Purchase: 'purchase', Lead: 'generate_lead' };

	function fire(ev) {
		var p = {};
		if (ev.value !== undefined) p.value = ev.value;
		if (ev.currency) p.currency = ev.currency;
		var items = ev.items || [];
		if (c.meta && window.fbq) {
			var mp = Object.assign({ content_type: 'product' }, p);
			if (items.length) mp.content_ids = items.map(function (i) { return i.id; });
			if (ev.title) mp.content_name = ev.title;
			window.fbq('track', ev.name, mp, ev.id ? { eventID: ev.id } : undefined);
		}
		if (c.tiktok && window.ttq) {
			var tp = Object.assign({ content_type: 'product' }, p);
			if (items.length) tp.contents = items.map(function (i) { return { content_id: i.id, content_name: i.name, quantity: i.qty, price: i.price }; });
			window.ttq.track(TT[ev.name] || ev.name, tp, ev.id ? { event_id: ev.id } : undefined);
		}
		if (c.ga4 && typeof window.gtag === 'function') {
			var gp = Object.assign({}, p);
			if (ev.tx) gp.transaction_id = ev.tx;
			if (items.length) gp.items = items.map(function (i) { return { item_id: i.id, item_name: i.name, price: i.price, quantity: i.qty }; });
			window.gtag('event', GA[ev.name] || ev.name, gp);
		}
	}

	function start() {
		if (started) return;
		started = true;
		capture();
		if (c.meta) loadMeta(c.meta);
		if (c.tiktok) loadTikTok(c.tiktok);
		if (c.ga4) loadGa(c.ga4);
		(c.events || []).forEach(fire);
	}

	if (consentOk()) {
		start();
	} else {
		/* Ziyaretçi banner'da "Kabul et" derse aynı sayfada başlar. */
		document.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-flx-consent="kabul"]');
			if (b) setTimeout(start, 0);
		});
	}
	/* Onay geri alınırsa atıf çerezleri silinir. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-flx-consent]');
		if (b && b.getAttribute('data-flx-consent') !== 'kabul' && c.consent !== 'always') {
			setCookie('flxads_ok', '', 0);
			setCookie('flxads_t', '', 0);
		}
	});
})();
