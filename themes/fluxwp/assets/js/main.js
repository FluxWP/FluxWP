/* Çeviriler: functions'tan window.flxI18n ile gelir (bkz. inc/dil.php). */
var flxT = (function (d) {
	return function (k, f) { return (d && d[k]) || f; };
})(window.flxI18n);

/* Kopyala butonları: .flx-copy sınıflı her paragrafın yanına buton ekler. IBAN'da boşluklar kopyalanmaz. */
(function () {
	document.querySelectorAll('.flx-copy').forEach(function (el) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'flx-copy-btn';
		b.textContent = flxT('copy', 'Kopyala');
		b.setAttribute('aria-label', flxT('copy', 'Kopyala') + ': ' + el.textContent.trim());
		b.addEventListener('click', function () {
			var text = el.textContent.trim();
			if (el.classList.contains('flx-copy--iban')) text = text.replace(/\s+/g, '');
			var done = function () {
				b.textContent = flxT('copied', 'Kopyalandı ✓');
				b.classList.add('is-done');
				setTimeout(function () { b.textContent = flxT('copy', 'Kopyala'); b.classList.remove('is-done'); }, 2000);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done, function () { fallback(text); done(); });
			} else { fallback(text); done(); }
		});
		el.insertAdjacentElement('afterend', b);
	});
	function fallback(text) {
		var t = document.createElement('textarea');
		t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
		document.body.appendChild(t); t.select();
		try { document.execCommand('copy'); } catch (e) {}
		document.body.removeChild(t);
	}
})();

/* Arama modalı: header'daki arama simgesine basınca tam ekran arama açılır. */
(function () {
	var search = document.querySelector('.flx-header .wp-block-search');
	if (!search) return;
	var form = search.closest('form') || search.querySelector('form') || search;
	var action = (form.getAttribute && form.getAttribute('action')) || '/';
	var btn = search.querySelector('.wp-block-search__button');
	if (!btn) return;

	var dlg = document.createElement('dialog');
	dlg.className = 'flx-search-modal';
	dlg.setAttribute('aria-label', flxT('searchSite', 'Sitede ara'));
	dlg.innerHTML =
		'<div class="flx-search-modal__inner">' +
		'<button type="button" class="flx-search-modal__close" aria-label="' + flxT('close', 'Kapat') + '"></button>' +
		'<p class="flx-search-modal__title">' + flxT('searchTitle', 'Ne aramıştınız?') + '</p>' +
		'<form role="search" method="get" action="' + action + '" class="flx-search-modal__form">' +
		'<label class="screen-reader-text" for="flx-modal-s">' + flxT('search', 'Ara') + '</label>' +
		'<input id="flx-modal-s" type="search" name="s" placeholder="' + flxT('searchPh', 'Ara…') + '" autocomplete="off" required>' +
		'<button type="submit">' + flxT('search', 'Ara') + '</button></form>' +
		(flxT('chips', []).length ? '<div class="flx-search-modal__chips"><span>' + flxT('popular', 'Popüler:') + '</span>' +
		flxT('chips', []).map(function (t) {
			return '<a href="' + action + (action.indexOf('?') > -1 ? '&' : '?') + 's=' + encodeURIComponent(t) + '">' + t + '</a>';
		}).join('') +
		'</div>' : '') + '</div>';
	document.body.appendChild(dlg);

	function open(e) {
		e.preventDefault(); e.stopImmediatePropagation();
		if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
		document.documentElement.classList.add('flx-modal-open');
		setTimeout(function () { dlg.querySelector('input').focus(); }, 30);
	}
	function close() { dlg.close ? dlg.close() : dlg.removeAttribute('open'); }
	btn.addEventListener('click', open, true);
	dlg.addEventListener('close', function () { document.documentElement.classList.remove('flx-modal-open'); btn.focus(); });
	dlg.querySelector('.flx-search-modal__close').addEventListener('click', close);
	dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
})();

/* YouTube: tıklayınca oynatıcıyı yükle. */
document.addEventListener('click', function (e) {
	var btn = e.target.closest('.flx-player__btn');
	if (!btn) return;
	var box = btn.parentNode, id = box.getAttribute('data-flx-id');
	if (!/^[\w-]{6,20}$/.test(id || '')) return;
	var f = document.createElement('iframe');
	f.src = 'https://www.youtube.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0&playsinline=1&origin=' + encodeURIComponent(location.origin);
	f.title = 'YouTube video';
	f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
	f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
	f.allowFullscreen = true;
	box.innerHTML = '';
	box.appendChild(f);
	box.classList.add('is-playing');
});

/* Kaydırma animasyonları: ekranın altında kalan başlık, kart ve görseller görünür alana girince belirir.
   İlk ekrandakiler hiç gizlenmez (titreme olmaz); "hareketi azalt" açıksa çalışmaz. */
(function () {
	var b = document.body;
	if (!b || !b.classList.contains('flx-anim') || !('IntersectionObserver' in window)) return;
	if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	var main = document.querySelector('main') || b;
	var sel = [
		'h1.wp-block-heading', 'h2.wp-block-heading', 'h3.wp-block-heading', '.wp-block-post-title',
		'.flx-card', '.wp-block-post', '.wp-block-column', '.wp-block-image', '.wp-block-media-text',
		'.wp-block-quote', '.wp-block-buttons', '.wp-block-embed', '.flx-player', '.flxs-ev-card',
		'.flxs-ev-section__head', '.flx-stats .wp-block-group', '.wp-block-group.is-style-flx-card'
	].join(',');
	var skip = '.flx-slider, .swiper, .flx-header-part, header, footer, .flx-band, .flx-search-modal, .woocommerce, .wc-block-components-sidebar-layout';
	var vh = window.innerHeight || 800;
	var list = [];
	main.querySelectorAll(sel).forEach(function (el) {
		if (el.closest(skip)) return;
		for (var p = el.parentElement; p && p !== main; p = p.parentElement) {
			if (p.classList.contains('flx-rv')) return;
		}
		if (el.getBoundingClientRect().top < vh * 0.92) return;
		el.classList.add('flx-rv');
		var sibs = el.parentElement ? Array.prototype.filter.call(el.parentElement.children, function (c) { return c.classList.contains('flx-rv'); }) : [];
		var i = sibs.indexOf(el);
		if (i > 0) el.style.transitionDelay = (Math.min(i, 5) * 90) + 'ms';
		list.push(el);
	});
	if (!list.length) return;
	var io = new IntersectionObserver(function (ents) {
		ents.forEach(function (en) {
			if (!en.isIntersecting) return;
			en.target.classList.add('flx-rv-in');
			io.unobserve(en.target);
			var t = en.target;
			setTimeout(function () { t.classList.remove('flx-rv', 'flx-rv-in'); t.style.transitionDelay = ''; }, 1400);
		});
	}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
	list.forEach(function (el) { io.observe(el); });
})();

/* Hesabım: Giriş Yap / Kayıt Ol sekmeleri, Google ile giriş butonu formun altında. */
(function () {
	var H = window.flxHesap || {};
	var box = document.getElementById('customer_login');
	if (box && box.querySelector('.u-column2')) {
		var tabs = document.createElement('div');
		tabs.className = 'flx-auth-tabs';
		tabs.setAttribute('role', 'tablist');
		var mk = function (lbl, reg) {
			var b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('role', 'tab');
			b.textContent = lbl;
			b.addEventListener('click', function () { set(reg); });
			tabs.appendChild(b);
			return b;
		};
		var bL = mk(H.tabLogin || 'Giriş Yap', false), bR = mk(H.tabReg || 'Kayıt Ol', true);
		var set = function (reg) {
			box.classList.toggle('is-register', reg);
			bL.setAttribute('aria-selected', reg ? 'false' : 'true');
			bR.setAttribute('aria-selected', reg ? 'true' : 'false');
		};
		box.parentNode.insertBefore(tabs, box);
		box.classList.add('flx-tabbed');
		var regEmail = box.querySelector('#reg_email');
		set(location.hash === '#kayit' || /[?&]action=register/.test(location.search) || !!(regEmail && regEmail.value));
	}
	/* Sosyal giriş butonlarını (Site Kit "Sign in with Google" vb.) formun en altına "veya" ayracıyla taşı. */
	var sel = '[class*="sign-in-with-google"],[class*="googlesitekit-sign-in"],.nsl-container,.wc-social-login,[class*="google-login"],[class*="login-with-google"]';
	document.querySelectorAll('form.woocommerce-form-login, form.woocommerce-form-register').forEach(function (form) {
		var items = Array.prototype.filter.call(form.querySelectorAll(sel), function (el) { return !el.parentElement.closest(sel); });
		if (!items.length) return;
		var wrap = document.createElement('div');
		wrap.className = 'flx-social-login';
		wrap.innerHTML = '<div class="flx-or"><span>' + (H.or || 'veya') + '</span></div>';
		items.forEach(function (el) { wrap.appendChild(el); });
		form.appendChild(wrap);
	});
})();

/* Giriş penceresi: "Giriş yap" / Hesabım bağlantısı sayfadan ayrılmadan ortada Hesabım'ın giriş/kayıt formunu açar
   (Google ile giriş ve kayıt dahil). Form başka adrese yönlenince (giriş tamam, şifre sıfırlama) sayfa oraya gider. */
(function () {
	var A = (window.flxHesap || {}).auth;
	if (!A || !A.url || !window.URL || window.top !== window) return;
	var trim = function (p) { return (p || '').replace(/\/+$/, ''); };
	var target = function (a) {
		if (!a.href || a.target === '_blank' || a.hasAttribute('download')) return null;
		var u;
		try { u = new URL(a.href, location.href); } catch (e) { return null; }
		if (u.origin !== location.origin) return null;
		var p = trim(u.pathname), act = u.searchParams.get('action') || '';
		var P = trim(A.path), acc = !!P && p.slice(-P.length) === P; // Dil öneki olsa da (/tr/my-account).
		var login = !!A.login && p === trim(A.login) && (act === '' || act === 'login' || act === 'register');
		if (!a.hasAttribute('data-flx-auth') && !acc && !login) return null;
		var f = new URL(acc ? u.href : A.url, location.href);
		var back = u.searchParams.get('redirect_to') || u.searchParams.get('flx_donus') || (location.pathname + location.search);
		f.searchParams.set('flx_auth', '1');
		if (!f.searchParams.get('flx_donus')) f.searchParams.set('flx_donus', back);
		var reg = act === 'register' || /^#(kayit|register)$/.test(u.hash) || a.getAttribute('data-flx-auth') === 'register';
		f.hash = reg ? 'kayit' : '';
		return f.href;
	};

	var dlg, frame, wrap, fallback;
	function build() {
		dlg = document.createElement('dialog');
		dlg.className = 'flx-auth-modal';
		dlg.setAttribute('aria-label', A.title || 'Giriş');
		dlg.innerHTML = '<div class="flx-auth-modal__inner"><button type="button" class="flx-auth-modal__close" aria-label="' + flxT('close', 'Kapat') + '"></button>' +
			'<div class="flx-auth-modal__loading" role="status" aria-label="' + flxT('loading', 'Yükleniyor…') + '"><i class="flx-sk flx-sk--tabs"></i><i class="flx-sk flx-sk--label"></i><i class="flx-sk flx-sk--field"></i><i class="flx-sk flx-sk--label"></i><i class="flx-sk flx-sk--field"></i><i class="flx-sk flx-sk--btn"></i><i class="flx-sk flx-sk--line"></i><i class="flx-sk flx-sk--google"></i></div><iframe class="flx-auth-modal__frame" title="' + (A.title || '') + '"></iframe></div>';
		document.body.appendChild(dlg);
		wrap = dlg.querySelector('.flx-auth-modal__inner');
		frame = dlg.querySelector('iframe');
		var close = function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); };
		dlg.querySelector('.flx-auth-modal__close').addEventListener('click', close);
		dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
		dlg.addEventListener('close', function () { document.documentElement.classList.remove('flx-modal-open'); });
		frame.addEventListener('load', function () {
			var href;
			try { href = frame.contentWindow.location.href; } catch (e) { href = null; }
			if (!href || href === 'about:blank') { if (href === null && fallback) location.href = fallback; return; }
			if (!/[?&]flx_auth=1/.test(href)) { location.href = href; return; } // Pencere dışına çıkan her yönlendirme: sayfa o adrese gider.
			wrap.classList.add('is-ready');
		});
		window.addEventListener('message', function (e) {
			if (e.origin !== location.origin || !e.data || typeof e.data.flxAuthH !== 'number' || e.source !== frame.contentWindow) return;
			frame.style.height = Math.ceil(e.data.flxAuthH) + 'px';
		});
	}
	function load(url) {
		if (!dlg) build();
		if (frame.getAttribute('src') !== url) { wrap.classList.remove('is-ready'); frame.style.height = ''; frame.setAttribute('src', url); }
	}
	function open(url, back) {
		load(url);
		fallback = back;
		if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
		document.documentElement.classList.add('flx-modal-open');
	}
	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a || a.closest('.flx-auth-modal')) return;
		var url = target(a);
		if (!url) return;
		e.preventDefault();
		var nav = a.closest('.wp-block-navigation__responsive-container.is-menu-open');
		if (nav) { var c = nav.querySelector('.wp-block-navigation__responsive-container-close'); if (c) c.click(); }
		open(url, a.href);
	}, true);
	// Niyet görünce (fare üstüne gelince, dokununca, klavyeyle odaklanınca) pencere arkada önden yüklenir: tıklayınca form hazır gelir.
	var intent = function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a || a.closest('.flx-auth-modal')) return;
		var url = target(a);
		if (url) load(url);
	};
	['pointerover', 'touchstart', 'focusin'].forEach(function (t) { document.addEventListener(t, intent, { passive: true, capture: true }); });
})();

/* Bağış modunda boş sepet: "Mağazaya geri dön" → "Bağışlar Sayfasına dön" (bağış sayfası). Mini sepet sonradan çizildiği için izlenir. */
(function () {
	var H = window.flxHesap || {};
	if (!H.donateUrl || !H.backToDon) return;
	var fix = function (root) {
		(root || document).querySelectorAll('.wp-block-woocommerce-mini-cart-shopping-button-block a, a.wc-block-mini-cart__shopping-button, .wc-block-cart__empty-cart__title ~ * a.wp-block-button__link[href*="/shop"], a.button.wc-backward').forEach(function (a) {
			if (a.dataset.flxFixed) return;
			a.dataset.flxFixed = '1';
			a.href = H.donateUrl;
			var t = a.querySelector('.wc-block-components-button__text') || a;
			t.textContent = H.backToDon || 'Bağışlar Sayfasına dön';
		});
	};
	fix();
	if ('MutationObserver' in window) {
		var q = false;
		new MutationObserver(function () { if (q) return; q = true; requestAnimationFrame(function () { q = false; fix(); }); }).observe(document.body, { childList: true, subtree: true });
	}
})();

/* Mini sepet çekmecesi kapalıyken (aria-hidden="true") odaklanamasın: inert, aria-hidden ile eşitlenir. */
(function () {
	if (!('MutationObserver' in window)) return;
	var sync = function (d) {
		var hidden = d.getAttribute('aria-hidden') === 'true';
		if (hidden !== d.hasAttribute('inert')) d.toggleAttribute('inert', hidden);
	};
	var seen = new WeakSet();
	var watch = function () {
		document.querySelectorAll('.wc-block-mini-cart__drawer').forEach(function (d) {
			sync(d);
			if (seen.has(d)) return;
			seen.add(d);
			new MutationObserver(function () { sync(d); }).observe(d, { attributes: true, attributeFilter: ['aria-hidden'] });
		});
	};
	watch();
	new MutationObserver(function (m) {
		for (var i = 0; i < m.length; i++) if (m[i].addedNodes.length) { watch(); return; }
	}).observe(document.body, { childList: true, subtree: true });
})();

/* Mobil menü butonlarında sepet sayısı: header'daki mini sepet rozetinden (sayfa önbelleğinden bağımsız). */
(function () {
	function sync() {
		var badge = document.querySelector('.flx-hs .wc-block-mini-cart__badge');
		var n = badge ? parseInt(badge.textContent, 10) || 0 : 0;
		document.querySelectorAll('.flx-mnav-count').forEach(function (b) {
			b.textContent = n;
			b.hidden = n < 1;
		});
	}
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('.wp-block-navigation__responsive-container-open')) sync();
	});
	document.body && document.body.addEventListener('wc-blocks_added_to_cart', sync);
	if (document.readyState !== 'loading') sync(); else document.addEventListener('DOMContentLoaded', sync);
})();

/* Sonsuz kaydırma (Tema Ayarları › Liste sayfaları): sayfa numaralı Query Loop'larda sonraki sayfa,
   liste sonuna yaklaşınca arka planda çekilip eklenir. Numaralar HTML'de kalır (Google, JS kapalı). */
(function () {
	var b = document.body;
	if (!b || !b.classList.contains('flx-infinite') || !window.fetch || !window.DOMParser) return;
	document.querySelectorAll('.wp-block-query').forEach(function (q, qi) {
		var pag = q.querySelector('.wp-block-query-pagination');
		var list = q.querySelector('.wp-block-post-template');
		var nextA = pag && pag.querySelector('a.wp-block-query-pagination-next');
		if (!pag || !list || !nextA) return;
		var next = nextA.href, busy = false;
		q.classList.add('flx-inf');
		var foot = document.createElement('div');
		foot.className = 'flx-inf-foot';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'flx-inf-more';
		btn.textContent = flxT('more', 'Daha fazla göster');
		foot.appendChild(btn);
		pag.parentNode.insertBefore(foot, pag);
		function done() { foot.remove(); q.classList.add('flx-inf-done'); }
		function load() {
			if (busy || !next) return;
			busy = true;
			foot.classList.add('is-loading');
			btn.textContent = flxT('loading', 'Yükleniyor…');
			fetch(next, { credentials: 'same-origin' }).then(function (r) {
				if (!r.ok) throw new Error(r.status);
				return r.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var qs = Array.prototype.filter.call(doc.querySelectorAll('.wp-block-query'), function (x) { return x.querySelector('.wp-block-query-pagination'); });
				var nq = qs.length > 1 ? qs[Math.min(qi, qs.length - 1)] : qs[0];
				var nl = nq && nq.querySelector('.wp-block-post-template');
				if (!nl) { next = ''; return; }
				Array.prototype.slice.call(nl.children).forEach(function (li) {
					li.classList.add('flx-inf-new');
					list.appendChild(document.importNode(li, true));
				});
				var na = nq.querySelector('a.wp-block-query-pagination-next');
				next = na ? na.href : '';
				var np = nq.querySelector('.wp-block-query-pagination');
				if (np) pag.innerHTML = np.innerHTML;
			}).catch(function () {
				io && io.disconnect();
				io = null;
			}).then(function () {
				busy = false;
				foot.classList.remove('is-loading');
				btn.textContent = flxT('more', 'Daha fazla göster');
				if (!next) done();
				else if (io) { io.unobserve(foot); io.observe(foot); }
			});
		}
		btn.addEventListener('click', load);
		var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
			if (es[0] && es[0].isIntersecting) load();
		}, { rootMargin: '0px 0px 600px 0px' }) : null;
		if (io) io.observe(foot);
	});
})();

/* Mobil menü: alt başlıklı öğeler akordeon gibi açılıp kapanır (açılır menü gibi).
   Açık panelde üst başlığa basınca sayfaya gitmez, altındakiler açılır; masaüstünde normal link. */
(function () {
	document.addEventListener('click', function (e) {
		var a = e.target.closest('.is-menu-open .wp-block-navigation-item.has-child > .wp-block-navigation-item__content, .is-menu-open .wp-block-navigation-item.has-child > .wp-block-navigation-submenu__toggle');
		if (!a) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var li = a.parentElement, open = !li.classList.contains('flx-open');
		li.parentElement.querySelectorAll(':scope > .flx-open').forEach(function (o) {
			if (o !== li) {
				o.classList.remove('flx-open');
				o.firstElementChild.setAttribute('aria-expanded', 'false');
			}
		});
		li.classList.toggle('flx-open', open);
		a.setAttribute('aria-expanded', open ? 'true' : 'false');
	}, true);
})();

/* Masaüstü menü: alt çizgi hover'daki öğeye kayar, çıkınca aktif öğeye döner (öğe yoksa kaybolur).
   JS yoksa ya da mobil menüde öğe başına ::after çizgisi kalır. prefers-reduced-motion CSS'te geçişi kapatır. */
(function () {
	function init(ul) {
		if (ul.querySelector(':scope > .flx-nav-ind')) {
			return;
		}
		var ind = document.createElement('li');
		ind.className = 'flx-nav-ind';
		ind.setAttribute('aria-hidden', 'true');
		ul.appendChild(ind);
		ul.classList.add('flx-nav-slide');
		var placed = false;
		function items() {
			return ul.querySelectorAll(':scope > .wp-block-navigation-item');
		}
		function current() {
			var c = ul.querySelector(':scope > .current-menu-item, :scope > .current-menu-ancestor, :scope > .current_page_item');
			return c || ul.querySelector(':scope > .wp-block-navigation-item > [aria-current="page"]');
		}
		function show(li) {
			if (!li) {
				ind.style.opacity = '0';
				return;
			}
			var a = li.classList.contains('wp-block-navigation-item') ? li : li.closest('.wp-block-navigation-item');
			var link = a.querySelector(':scope > .wp-block-navigation-item__content') || a;
			var r = link.getBoundingClientRect(), u = ul.getBoundingClientRect();
			if (!r.width) {
				return;
			}
			if (!placed) {
				ind.style.transition = 'none';
			}
			ind.style.width = r.width + 'px';
			ind.style.transform = 'translate(' + Math.round(r.left - u.left) + 'px,' + Math.round(r.bottom - u.top) + 'px)';
			ind.style.opacity = '1';
			if (!placed) {
				void ind.offsetWidth;
				ind.style.transition = '';
				placed = true;
			}
		}
		function rest() {
			show(current());
		}
		items().forEach(function (li) {
			li.addEventListener('mouseenter', function () { show(li); });
			li.addEventListener('focusin', function () { show(li); });
		});
		ul.addEventListener('mouseleave', rest);
		ul.addEventListener('focusout', function (e) {
			if (!ul.contains(e.relatedTarget)) {
				rest();
			}
		});
		window.addEventListener('resize', function () { rest(); });
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(rest);
		}
		rest();
	}
	function boot() {
		document.querySelectorAll('.flx-header .wp-block-navigation__responsive-container:not(.is-menu-open) .wp-block-navigation__container').forEach(function (ul) {
			if (window.matchMedia('(min-width:601px)').matches) {
				init(ul);
			}
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
