/* Çerez onayı: tercih saklanır, analiz/ek kodlar yalnızca "kabul" sonrası yüklenir. */
(function () {
	var cfg = window.flxConsent || {};
	var KEY = 'flx_cerez';
	var box = document.getElementById('flx-cookie');

	function read() {
		var m = document.cookie.match(/(?:^|; )flx_cerez=([^;]+)/);
		var v = m ? decodeURIComponent(m[1]) : '';
		try { v = v || localStorage.getItem(KEY) || ''; } catch (e) {}
		var p = v.split(':'); // "kabul:2"
		return p.length === 2 && +p[1] === cfg.rev ? p[0] : '';
	}

	function save(choice) {
		var v = choice + ':' + cfg.rev;
		document.cookie = KEY + '=' + encodeURIComponent(v) + ';path=/;max-age=' + 60 * 60 * 24 * 180 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
		try { localStorage.setItem(KEY, v); } catch (e) {}
	}

	var loaded = false;
	function load() {
		if (loaded) return;
		loaded = true;
		if (typeof window.gtag === 'function') {
			gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
		}
		if (cfg.ga && !cfg.gaHead) {
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga);
			document.head.appendChild(s);
			gtag('js', new Date());
			gtag('config', cfg.ga, { anonymize_ip: true });
		}
		var tpl = document.getElementById('flx-consent-scripts');
		if (tpl) {
			// <template> içindeki script'ler çalışmaz; yeniden oluşturulur.
			Array.prototype.forEach.call(tpl.content.childNodes, function (n) {
				if (n.nodeName === 'SCRIPT') {
					var s = document.createElement('script');
					Array.prototype.forEach.call(n.attributes, function (a) { s.setAttribute(a.name, a.value); });
					s.text = n.textContent;
					document.body.appendChild(s);
				} else {
					document.body.appendChild(n.cloneNode(true));
				}
			});
		}
	}

	function show() {
		if (!box) return;
		box.hidden = false;
		requestAnimationFrame(function () { box.classList.add('is-visible'); });
	}
	function hide() {
		if (!box) return;
		box.classList.remove('is-visible');
		setTimeout(function () { box.hidden = true; }, 350);
	}

	if (!cfg.active) { load(); return; }

	var choice = read();
	if (choice === 'kabul') load();
	else if (!choice) show();

	if (box) {
		box.addEventListener('click', function (e) {
			var b = e.target.closest('[data-flx-consent]');
			if (!b) return;
			var c = b.getAttribute('data-flx-consent');
			save(c);
			if (c === 'kabul') load();
			else if (loaded && typeof window.gtag === 'function') {
				gtag('consent', 'update', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
			}
			hide();
		});
	}

	// "Çerez tercihleri" bağlantıları banner'ı yeniden açar.
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href$="#cerez-tercihleri"]');
		if (!a) return;
		e.preventDefault();
		show();
	});
})();
