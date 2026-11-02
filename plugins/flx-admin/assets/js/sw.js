/* Flux Admin service worker. FLXA_SW (cache, offline, pre, static) is prepended by the server. */
/* global FLXA_SW */
'use strict';

self.addEventListener('install', function (e) {
	e.waitUntil(
		caches.open(FLXA_SW.cache).then(function (c) {
			return c.addAll([FLXA_SW.offline, FLXA_SW.splash].concat(FLXA_SW.pre)).catch(function () {});
		}).then(function () { return self.skipWaiting(); })
	);
});

self.addEventListener('activate', function (e) {
	e.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (k) { return k.indexOf('flxa-') === 0 && k !== FLXA_SW.cache; }).map(function (k) { return caches.delete(k); }));
		}).then(function () {
			// Navigation preload: işçi uyanırken sayfa isteği beklemeden yola çıkar (soğuk açılış hızlanır).
			if (self.registration.navigationPreload) return self.registration.navigationPreload.enable().catch(function () {});
		}).then(function () { return self.clients.claim(); })
	);
});

function isStatic(url) {
	if (!/\.(css|js|woff2?|ttf|svg|png|jpe?g|webp|gif|ico)$/i.test(url.pathname)) return false;
	return FLXA_SW['static'].some(function (p) { return url.pathname.indexOf(p) !== -1; });
}

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') return;
	var url = new URL(req.url);
	if (url.origin !== self.location.origin) return;

	/* Sayfalar: her zaman ağdan (önbelleğe yazılmaz); ağ yoksa çevrimdışı sayfası. */
	if (req.mode === 'navigate') {
		/* Uygulama simgesinden açılış: hafif açılış ekranı önbellekten anında, o da asıl sayfaya geçer. */
		if (url.searchParams.get('flxa-start') === '1') {
			e.respondWith(caches.match(FLXA_SW.splash).then(function (hit) {
				if (hit) return hit;
				url.searchParams.delete('flxa-start');
				return Response.redirect(url.toString(), 302);
			}));
			return;
		}
		e.respondWith(Promise.resolve(e.preloadResponse).then(function (pre) { return pre || fetch(req); }).catch(function () {
			return caches.match(FLXA_SW.offline, { ignoreSearch: false });
		}));
		return;
	}

	/* Sürümlü statik dosyalar: önbellekten hemen, arkada tazele. REST, admin-ajax ve diğer her şey dokunulmadan ağa. */
	if (isStatic(url)) {
		e.respondWith(caches.open(FLXA_SW.cache).then(function (c) {
			return c.match(req).then(function (hit) {
				var net = fetch(req).then(function (res) {
					if (res && res.ok && res.type === 'basic') c.put(req, res.clone());
					return res;
				}).catch(function () { return hit; });
				return hit || net;
			});
		}));
	}
});

/* Bildirim tıklaması (ileride Web Push için): ilgili adresi açar. */
self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var url = (e.notification.data && e.notification.data.url) || self.registration.scope;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
		for (var i = 0; i < list.length; i++) {
			if (list[i].url === url && 'focus' in list[i]) return list[i].focus();
		}
		return self.clients.openWindow(url);
	}));
});
