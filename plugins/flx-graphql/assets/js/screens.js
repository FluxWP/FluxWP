/*
 * Flux screens navigator (includes/screens.php).
 *
 * A click on a link to another registered Flux screen does not reload wp-admin: the screen's package
 * bundle is imported (once per page), its first queries are fetched in one batch (already on hover), the
 * screen is mounted out of sight and swapped in when it has painted. The address, title, body classes and
 * menu highlight follow; back / forward work. Everything else loads as a normal page.
 *
 * Results go to the page-wide store (globalThis.__flxGqlStore, packages/graphql/src/store.ts), shared with
 * every bundle on the page. Key: "<persisted id>:<variables JSON, keys sorted>".
 */
(function (w, d) {
	'use strict';
	var cfg = w.FlxScreensConfig;
	if (!cfg || !cfg.routes || !w.history || !w.history.pushState || !w.fetch || !w.URL || !w.Map) {
		return;
	}
	var routes = cfg.routes;
	var FRESH = 30000;
	var adminPath = new URL(cfg.admin, location.href).pathname;

	/* ---------- page-wide store (same contract as store.ts) ---------- */
	function store() {
		var s = w.__flxGqlStore;
		if (!s) {
			s = new Map();
			w.__flxGqlStore = s;
			var el = d.getElementById('flx-gql-preload');
			if (el) {
				try {
					var seed = JSON.parse(el.textContent || '{}');
					var at = typeof seed.at === 'number' ? seed.at : Date.now();
					Object.keys(seed.entries || {}).forEach(function (k) {
						s.set(k, { data: seed.entries[k], at: at });
					});
				} catch (e) { /* the preload is only a head start */ }
				el.parentNode.removeChild(el);
			}
		}
		return s;
	}
	store();

	/* ---------- routes ---------- */
	function match(href) {
		var u;
		try {
			u = new URL(href, location.href);
		} catch (e) {
			return null;
		}
		if (u.origin !== location.origin || u.pathname.indexOf(adminPath) !== 0) {
			return null;
		}
		var file = u.pathname.slice(adminPath.length) || 'index.php';
		var page = u.searchParams.get('page');
		var r = page && Object.prototype.hasOwnProperty.call(routes, page) ? routes[page] : null;
		// pageLoad: the page changes wp-admin's own chrome (Flux Admin's app rail on Chats / Tasks), so it loads as a page.
		if (!r || r.file !== file || r.pageLoad) {
			return null;
		}
		// Links with other arguments (actions, nonces, record ids) are pages of their own. Arguments of the
		// menu address itself (edit.php?post_type=…) must be there, with the same value.
		var own = r.args || {};
		var plain = true;
		u.searchParams.forEach(function (v, k) {
			if (k !== 'page' && k !== r.startParam && own[k] !== v) {
				plain = false;
			}
		});
		Object.keys(own).forEach(function (k) {
			if (u.searchParams.get(k) !== own[k]) {
				plain = false;
			}
		});
		if (!plain) {
			return null;
		}
		return { page: page, route: r, url: u, start: (r.startParam && u.searchParams.get(r.startParam)) || r.start || '' };
	}

	function needsMet(r) {
		// A global, or a dotted path such as "wp.media".
		return (r.needs || []).every(function (n) {
			return !!n.split('.').reduce(function (o, k) { return o ? o[k] : undefined; }, w);
		});
	}

	/* ---------- bundles ---------- */
	var runtimes = {};
	var screenBundles = {};
	function runtime(r) {
		var id = r.bundle.id;
		if (!runtimes[id]) {
			runtimes[id] = import(r.bundle.entry).then(function (ns) {
				// The shared plugin runtime (mode "screens") lives on globalThis; a FluxWP bundle exports its API.
				var api = r.bundle.screens ? w.FluxRuntime : ns && typeof ns.mount === 'function' ? ns : w.FluxModules;
				if (!api || typeof api.mount !== 'function' || typeof api.unmount !== 'function') {
					throw new Error('flx-screens: bundle without mount/unmount');
				}
				return api;
			});
			runtimes[id].catch(function () {
				delete runtimes[id];
			});
		}
		if (!r.bundle.screens) {
			return runtimes[id];
		}
		// A plugin's screens bundle registers its screens with the runtime when it runs (once per page).
		return runtimes[id].then(function (api) {
			var sid = r.bundle.screensId;
			if (!screenBundles[sid]) {
				screenBundles[sid] = api.has && api.has(r.module) ? Promise.resolve() : import(r.bundle.screens);
				screenBundles[sid].catch(function () {
					delete screenBundles[sid];
				});
			}
			return screenBundles[sid].then(function () {
				return api;
			});
		});
	}

	/** A plugin screens bundle's own stylesheet (beside the runtime's), loaded and on. */
	function screensCss(r) {
		if (!r.bundle.screensCss) {
			return Promise.resolve(null);
		}
		return ensureCss({ bundle: { css: r.bundle.screensCss, id: r.bundle.screensId } });
	}

	function cssLink(r) {
		var base = r.bundle.css.split('?')[0];
		var links = d.querySelectorAll('link[rel="stylesheet"]');
		for (var i = 0; i < links.length; i++) {
			if (links[i].href.split('?')[0] === base || links[i].dataset.flxBundle === r.bundle.id) {
				links[i].dataset.flxBundle = r.bundle.id;
				return links[i];
			}
		}
		return null;
	}

	/** The bundle's stylesheet, loaded and on; other bundles' sheets go off once the new screen shows. */
	function ensureCss(r) {
		var l = cssLink(r);
		if (l) {
			l.disabled = false;
			return Promise.resolve(l);
		}
		return new Promise(function (resolve) {
			l = d.createElement('link');
			l.rel = 'stylesheet';
			l.href = r.bundle.css;
			l.dataset.flxBundle = r.bundle.id;
			l.onload = l.onerror = function () {
				resolve(l);
			};
			d.head.appendChild(l);
		});
	}

	function onlyCss(r) {
		var links = d.querySelectorAll('link[rel="stylesheet"]');
		Object.keys(routes).forEach(function (p) {
			cssLink(routes[p]);
			if (routes[p].bundle.screensCss) {
				cssLink({ bundle: { css: routes[p].bundle.screensCss, id: routes[p].bundle.screensId } });
			}
		});
		for (var i = 0; i < links.length; i++) {
			var b = links[i].dataset.flxBundle;
			if (b) {
				links[i].disabled = b !== r.bundle.id && b !== r.bundle.screensId;
			}
		}
	}

	var scripts = {};
	/** Classic scripts a screen needs (world map data…), each once per page. */
	function loadScripts(r) {
		return Promise.all((r.scripts || []).map(function (src) {
			var base = src.split('?')[0];
			if (!scripts[base]) {
				var found = [].some.call(d.scripts, function (s) {
					return s.src && s.src.split('?')[0] === base;
				});
				scripts[base] = found ? Promise.resolve() : new Promise(function (resolve, reject) {
					var s = d.createElement('script');
					s.src = src;
					s.onload = resolve;
					s.onerror = function () {
						delete scripts[base];
						reject(new Error('flx-screens: ' + base));
					};
					d.body.appendChild(s);
				});
			}
			return scripts[base];
		}));
	}

	/* ---------- data ---------- */
	function fresh(key) {
		var e = store().get(key);
		return !!e && Date.now() - e.at < FRESH;
	}

	var inflight = {};
	function prefetchData(r) {
		var waiting = [];
		var items = (r.preload || []).filter(function (p) {
			if (inflight[p.key]) {
				waiting.push(inflight[p.key]);
				return false;
			}
			return !fresh(p.key);
		});
		if (!items.length) {
			return Promise.all(waiting);
		}
		var url = new URL(cfg.graphql, location.href);
		url.searchParams.set('_locale', 'user');
		var p = fetch(url.toString(), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': api.nonce,
				'X-Flux-GraphQL-Operation': items.map(function (i) { return i.name; }).join(',')
			},
			body: JSON.stringify(items.map(function (i) {
				return i.vars ? { queryId: i.id, operationName: i.name, variables: i.vars } : { queryId: i.id, operationName: i.name };
			}))
		})
			.then(function (res) {
				return res.ok ? res.json() : null;
			})
			.then(function (list) {
				if (!Array.isArray(list)) {
					return;
				}
				list.forEach(function (res, n) {
					if (res && res.data && !(res.errors && res.errors.length)) {
						store().set(items[n].key, { data: res.data, at: Date.now() });
					}
				});
			})
			.catch(function () { /* the screen asks again itself */ })
			.then(function () {
				items.forEach(function (i) {
					delete inflight[i.key];
				});
			});
		items.forEach(function (i) {
			inflight[i.key] = p;
		});
		return Promise.all(waiting.concat([p]));
	}

	function prefetch(m) {
		if (!needsMet(m.route)) {
			return;
		}
		runtime(m.route).then(function (rt) {
			return typeof rt.preload === 'function' ? rt.preload(m.route.module) : null;
		}).catch(function () {});
		loadScripts(m.route).catch(function () {});
		prefetchData(m.route);
	}

	/* ---------- page chrome ---------- */
	function swapClass(el, off, on) {
		if (off) {
			el.classList.remove(off);
		}
		if (on) {
			el.classList.add(on);
		}
	}

	function markMenu(m) {
		var menu = d.getElementById('adminmenu');
		if (!menu) {
			return;
		}
		var best = null;
		var anyLink = null;
		// Submenu items first: a top-level item links to the same page as its first submenu item.
		var links = [].slice.call(menu.querySelectorAll('.wp-submenu a[href]')).concat([].slice.call(menu.querySelectorAll('a.menu-top[href]')));
		for (var i = 0; i < links.length; i++) {
			// "#" links are in-page toggles (e.g. the "Other" row); they resolve to the current page and must never count as its menu link.
			if ((links[i].getAttribute('href') || '').charAt(0) === '#') {
				continue;
			}
			var x = match(links[i].href);
			if (!x || x.page !== m.page) {
				continue;
			}
			anyLink = anyLink || links[i];
			if (x.start === m.start) {
				best = links[i];
				break;
			}
		}
		best = best || anyLink;
		if (!best) {
			return;
		}
		[].forEach.call(menu.querySelectorAll('li.current, a.current'), function (el) {
			el.classList.remove('current');
			el.removeAttribute('aria-current');
		});
		[].forEach.call(menu.querySelectorAll('li.wp-has-current-submenu, a.wp-has-current-submenu'), function (el) {
			el.classList.remove('wp-has-current-submenu', 'wp-menu-open');
			el.classList.add('wp-not-current-submenu');
		});
		var top = best.closest('li.menu-top');
		var inSub = !!best.closest('.wp-submenu');
		if (inSub) {
			best.classList.add('current');
			best.setAttribute('aria-current', 'page');
			best.parentNode.classList.add('current');
		}
		if (top) {
			var topA = top.querySelector(':scope > a');
			if (top.classList.contains('wp-has-submenu')) {
				[top, topA].forEach(function (el) {
					if (el) {
						el.classList.remove('wp-not-current-submenu');
						el.classList.add('wp-has-current-submenu', 'wp-menu-open');
					}
				});
			} else {
				top.classList.add('current');
				if (topA) {
					topA.classList.add('current');
					topA.setAttribute('aria-current', 'page');
				}
			}
		}
		// Lets the admin chrome (Flux Admin) repaint, e.g. open the group of the new current item.
		try {
			d.dispatchEvent(new CustomEvent('flx:menu-marked'));
		} catch (e) {}
	}

	var bar = null;
	var barTimer = 0;
	function progress(on) {
		if (!bar) {
			bar = d.createElement('div');
			bar.className = 'flx-screens-bar';
			bar.setAttribute('role', 'progressbar');
			bar.setAttribute('aria-label', cfg.i18n.loading);
			d.body.appendChild(bar);
		}
		clearTimeout(barTimer);
		if (on) {
			barTimer = setTimeout(function () {
				bar.style.transition = 'none';
				bar.style.width = '0';
				void bar.offsetWidth;
				bar.style.transition = '';
				bar.classList.add('is-on');
				bar.style.width = '70%';
			}, 120);
		} else if (bar.classList.contains('is-on')) {
			bar.style.width = '100%';
			barTimer = setTimeout(function () {
				bar.classList.remove('is-on');
			}, 200);
		}
	}

	var live = null;
	function announce(text) {
		if (!live) {
			live = d.createElement('div');
			live.className = 'flx-screens-live';
			live.setAttribute('aria-live', 'polite');
			d.body.appendChild(live);
		}
		live.textContent = text;
	}

	/* ---------- navigation ---------- */
	var current = null;
	var seq = 0;

	function mountOptions(m) {
		var o = {};
		var src = m.route.opts || {};
		Object.keys(src).forEach(function (k) {
			o[k] = src[k];
		});
		o.nonce = api.nonce;
		o.startPage = m.start;
		// Another start page (tab) than the route's default: its first queries are learned under its own key.
		if (o.learn && m.start !== m.route.start) {
			o.learn = { url: o.learn.url, key: m.route.module + '|' + m.start, known: [] };
		}
		return o;
	}

	/** Resolves when the screen has painted something that is not a spinner, or after `max` ms. */
	function settled(el, max) {
		return new Promise(function (resolve) {
			var t0 = Date.now();
			var calm = 0;
			(function check() {
				var ok = el.children.length > 0 && !el.querySelector('.loading') && (el.textContent || '').trim().length > 0;
				calm = ok ? calm + 1 : 0;
				if (calm >= 2 || Date.now() - t0 > max) {
					resolve();
					return;
				}
				requestAnimationFrame(check);
			})();
		});
	}

	function theme(m, el) {
		var t = m.route.opts && m.route.opts.theme ? m.route.opts.theme : 'admin';
		if (m.route.themeStorage) {
			try {
				t = localStorage.getItem(m.route.themeStorage) || 'auto';
			} catch (e) {
				t = 'auto';
			}
		}
		if (t === 'admin') {
			t = d.documentElement.classList.contains('flxa-dark') ? 'dark' : 'light';
		}
		if (t === 'auto') {
			t = w.matchMedia && w.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
		}
		el.dataset.theme = t;
	}

	function navigate(m, push) {
		var token = ++seq;
		var from = current;
		progress(true);
		if (push) {
			// Screens read their own arguments from the address when they mount.
			w.history.pushState({ flxScreen: 1 }, '', m.url.href);
		}
		var code = runtime(m.route).then(function (rt) {
			// The screen's code before it mounts: it paints once, with content.
			return typeof rt.preload === 'function' ? rt.preload(m.route.module).then(function () { return rt; }) : rt;
		});
		return Promise.all([code, ensureCss(m.route), prefetchData(m.route), from.rt, loadScripts(m.route), screensCss(m.route)]).then(function (res) {
			if (token !== seq) {
				return;
			}
			var rt = res[0];
			var el = d.createElement('div');
			el.id = 'flx-screen-' + m.page.replace(/[^\w-]/g, '');
			el.className = 'flx-screen is-incoming';
			el.dataset.layout = 'inline';
			el.tabIndex = -1;
			theme(m, el);
			var wrap = from.el.parentNode;
			wrap.appendChild(el);
			rt.mount(el, mountOptions(m));
			return settled(el, 1500).then(function () {
				if (token !== seq) {
					rt.unmount(el);
					el.parentNode.removeChild(el);
					return;
				}
				onlyCss(m.route);
				el.classList.remove('is-incoming');
				try {
					res[3].unmount(from.el);
				} catch (e) { /* the old screen is removed anyway */ }
				if (from.el.parentNode) {
					from.el.parentNode.removeChild(from.el);
				}
				swapClass(d.body, from.route.hook, m.route.hook);
				swapClass(d.body, from.route.bodyClass, m.route.bodyClass);
				d.title = m.route.title;
				markMenu(m);
				w.scrollTo(0, 0);
				try {
					el.focus({ preventScroll: true });
				} catch (e) { /* old browsers */ }
				announce(m.route.title);
				current = { page: m.page, start: m.start, route: m.route, el: el, rt: Promise.resolve(rt) };
				progress(false);
				var ev;
				try {
					ev = new CustomEvent('flx-screens:navigate', { detail: { page: m.page, route: m.route, el: el } });
				} catch (e) {
					ev = null;
				}
				if (ev) {
					d.dispatchEvent(ev);
				}
			});
		});
	}

	function go(href, push) {
		// Leaving a pageLoad screen also loads the page: its body classes (app rail) must not stay behind.
		if (!cfg.on || !current || current.route.pageLoad) {
			return false;
		}
		var m = match(href);
		if (!m || !needsMet(m.route)) {
			return false;
		}
		var here = new URL(location.href);
		if (m.url.pathname === here.pathname && m.url.search === here.search && m.page === current.page) {
			// Same screen: a hash link, or a click on the item already open.
			return m.url.hash !== here.hash ? false : true;
		}
		navigate(m, push !== false).catch(function () {
			location.assign(m.url.href);
		});
		return true;
	}

	var api = {
		nonce: cfg.nonce,
		go: function (href) {
			return go(href, true);
		},
		setNonce: function (n) {
			if (n) {
				api.nonce = n;
			}
		},
		prefetch: function (href) {
			var m = match(href);
			if (m) {
				prefetch(m);
			}
		}
	};
	w.FlxScreens = api;

	function start() {
		var r = routes[cfg.current];
		var el = d.getElementById(cfg.el);
		if (!r || !el) {
			return;
		}
		var here = match(location.href);
		var rt = Promise.resolve(r.bundle.screens ? w.FluxRuntime : w.FluxModules).then(function (x) {
			if (!x || typeof x.unmount !== 'function') {
				throw new Error('flx-screens: current bundle cannot unmount');
			}
			return x;
		});
		rt.catch(function () {
			cfg.on = false;
		});
		runtimes[r.bundle.id] = runtimes[r.bundle.id] || rt;
		current = { page: cfg.current, start: here ? here.start : r.start || '', route: r, el: el, rt: rt };
		w.history.replaceState({ flxScreen: 1 }, '', location.href);
		cssLink(r);
		if (r.bundle.screensCss) {
			cssLink({ bundle: { css: r.bundle.screensCss, id: r.bundle.screensId } });
		}
	}

	d.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}
		var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) {
			return;
		}
		if (go(a.href, true)) {
			e.preventDefault();
		}
	});

	var hoverTimer = 0;
	function onHover(e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || !cfg.on || !current) {
			return;
		}
		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function () {
			var m = match(a.href);
			if (m && m.page !== current.page) {
				prefetch(m);
			}
		}, 60);
	}
	d.addEventListener('pointerover', onHover, { passive: true });
	d.addEventListener('focusin', onHover);
	d.addEventListener('touchstart', onHover, { passive: true });

	w.addEventListener('popstate', function () {
		if (!current) {
			return;
		}
		var m = match(location.href);
		if (!m) {
			location.reload();
			return;
		}
		if (m.page === current.page && m.start === current.start) {
			return; // hash-only step inside the screen
		}
		if (!cfg.on || current.route.pageLoad || !needsMet(m.route)) {
			location.reload();
			return;
		}
		navigate(m, false).catch(function () {
			location.reload();
		});
	});

	// Module scripts (the bundle) run before DOMContentLoaded, after this classic script.
	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(window, document);
