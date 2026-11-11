/* Flux Donate · "My donations" lookup page ([flx_bagislarim]). No dependencies. */
(function () {
	'use strict';
	var C = window.FLXB_LOOKUP || {};
	var T = C.i18n || {};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function safeUrl(u) {
		u = String(u || '');
		return /^https?:\/\//i.test(u) || u.charAt(0) === '/' ? u : '';
	}
	function fmt(s, v) { return String(s || '').replace('%s', v); }

	function api(path, body) {
		var h = { 'Content-Type': 'application/json' };
		if (C.nonce) { h['X-WP-Nonce'] = C.nonce; }
		var ctl = window.AbortController ? new AbortController() : null;
		var tm = ctl ? setTimeout(function () { ctl.abort(); }, 20000) : 0;
		return fetch(C.api + path + '?_t=' + Date.now(), {
			method: 'POST',
			credentials: 'same-origin',
			headers: h,
			body: JSON.stringify(body || {}),
			signal: ctl ? ctl.signal : undefined
		}).then(function (r) {
			clearTimeout(tm);
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : T.error); }
				return j;
			});
		});
	}

	var icons = {
		cert: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="5"/><path d="M9 13.5 7.5 21l4.5-2.5 4.5 2.5-1.5-7.5"/></svg>',
		dl: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 20h14"/></svg>',
		eye: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
	};

	function certCard(c) {
		var img = safeUrl(c.image_url);
		var view = safeUrl(c.url);
		var pdf = safeUrl(c.pdf_url);
		return '<li class="flxb-lk__cert">' +
			(img ? '<a class="flxb-lk__thumb" href="' + esc(view || img) + '" target="_blank" rel="noopener"><img src="' + esc(img) + '" alt="" loading="lazy" decoding="async"></a>' : '<span class="flxb-lk__thumb flxb-lk__thumb--none">' + icons.cert + '</span>') +
			'<div class="flxb-lk__certbody"><strong>' + esc(c.name || T.cert_for) + '</strong>' +
			'<span class="flxb-lk__muted">' + esc([c.title, c.number ? '#' + c.number : '', c.date_label].filter(Boolean).join(' · ')) + '</span>' +
			'<div class="flxb-lk__actions">' +
			(view ? '<a class="flxb-lk__chip" href="' + esc(view) + '" target="_blank" rel="noopener">' + icons.eye + esc(T.view) + '</a>' : '') +
			(pdf ? '<a class="flxb-lk__chip flxb-lk__chip--primary" href="' + esc(pdf) + '" target="_blank" rel="noopener" download>' + icons.dl + esc(T.pdf) + '</a>' : '') +
			'</div></div></li>';
	}

	function giftCard(g) {
		var lines = (g.lines || []).length > 1 ? '<ul class="flxb-lk__lines">' + g.lines.map(function (l) {
			return '<li><span>' + esc(l.title) + (l.quantity > 1 ? ' × ' + esc(l.quantity) : '') + '</span><span>' + esc(l.amount_label) + '</span></li>';
		}).join('') + '</ul>' : '';
		var certs = (g.certificate_list || []);
		var links = '';
		if (safeUrl(g.pay_url)) { links += '<a class="flxb-lk__chip flxb-lk__chip--warn" href="' + esc(safeUrl(g.pay_url)) + '">' + esc(T.pay) + '</a>'; }
		if (safeUrl(g.web_url)) { links += '<a class="flxb-lk__chip" href="' + esc(safeUrl(g.web_url)) + '">' + esc(T.order) + '</a>'; }
		return '<li class="flxb-lk__gift">' +
			'<div class="flxb-lk__gifthead"><div><strong class="flxb-lk__gifttitle">' + esc(g.title) + '</strong>' +
			'<span class="flxb-lk__muted">' + esc(g.date_label) + ' · ' + esc(fmt(T.no, g.order_id || g.id)) + (g.type === 'recurring' ? ' · ' + esc(g.type_label) : '') + '</span></div>' +
			'<div class="flxb-lk__amount"><strong>' + esc(g.amount_label) + '</strong><span class="flxb-lk__pill flxb-lk__pill--' + esc(g.status_tone) + '">' + esc(g.status_label) + '</span></div></div>' +
			lines +
			(certs.length ? '<ul class="flxb-lk__certs flxb-lk__certs--in">' + certs.map(certCard).join('') + '</ul>' : '') +
			(links ? '<div class="flxb-lk__actions">' + links + '</div>' : '') +
			'</li>';
	}

	function render(root, d) {
		var box = root.querySelector('.flxb-lk__result');
		var items = d.items || [];
		var certs = d.certificates || [];
		var head = '<header class="flxb-lk__head"><span class="flxb-lk__who">' +
			(d.who ? esc(d.account ? d.who : fmt(T.verified, d.who)) : '') + '</span>' +
			(!d.account ? '<button type="button" class="flxb-lk__link" data-act="logout">' + esc(T.logout) + '</button>' : '') + '</header>';
		if (!items.length) {
			box.innerHTML = head + '<div class="flxb-lk__empty"><p><strong>' + esc(T.none) + '</strong></p><p class="flxb-lk__muted">' + esc(T.none_hint) + '</p>' +
				'<a class="flxb-lk__btn flxb-lk__btn--inline" href="' + esc(safeUrl(d.donate_url) || '/') + '">' + esc(T.donate) + '</a></div>';
			return;
		}
		var stats = '<div class="flxb-lk__stats">' +
			'<div><span>' + esc(T.count) + '</span><strong>' + esc(d.count) + '</strong></div>' +
			(d.total_label ? '<div><span>' + esc(T.total) + '</span><strong>' + esc(d.total_label) + '</strong></div>' : '') +
			'<div><span>' + esc(T.ncerts) + '</span><strong>' + esc(certs.length) + '</strong></div></div>';
		var tabs = '<div class="flxb-lk__tabs flxb-lk__tabs--result" role="tablist">' +
			'<button type="button" role="tab" class="flxb-lk__tab is-on" data-view="gifts" aria-selected="true">' + esc(T.gifts) + ' <span class="flxb-lk__count">' + esc(items.length) + '</span></button>' +
			'<button type="button" role="tab" class="flxb-lk__tab" data-view="certs" aria-selected="false">' + esc(T.certs) + ' <span class="flxb-lk__count">' + esc(certs.length) + '</span></button></div>';
		box.innerHTML = head + stats + tabs +
			'<ul class="flxb-lk__gifts" data-pane="gifts">' + items.map(giftCard).join('') + '</ul>' +
			'<div data-pane="certs" hidden>' + (certs.length ? '<ul class="flxb-lk__certs">' + certs.map(certCard).join('') + '</ul>' : '<p class="flxb-lk__empty flxb-lk__muted">' + esc(T.no_certs) + '</p>') + '</div>';
	}

	function setup(root) {
		var start = root.querySelector('.flxb-lk__step--start');
		var codeF = root.querySelector('.flxb-lk__step--code');
		var mode = root.querySelector('[data-for="email"]') ? 'email' : 'phone';
		var login = '';
		var timer = 0;

		function state(s) { root.setAttribute('data-state', s); }
		function msg(form, text, ok) {
			var m = form.querySelector('.flxb-lk__msg');
			m.textContent = text || '';
			m.hidden = !text;
			m.classList.toggle('is-ok', !!ok);
		}
		function busy(btn, on, label) {
			if (!btn) { return; }
			if (on) { btn.dataset.label = btn.textContent; btn.textContent = label; } else if (btn.dataset.label) { btn.textContent = btn.dataset.label; }
			btn.disabled = on;
		}
		function showResult(d) {
			render(root, d);
			root.querySelector('.flxb-lk__result').hidden = false;
			state('result');
		}
		function countdown() {
			var b = codeF.querySelector('[data-act="resend"]');
			var n = C.resend || 60;
			clearInterval(timer);
			b.disabled = true;
			b.textContent = fmt(T.resend_in, n);
			timer = setInterval(function () {
				n -= 1;
				if (n <= 0) { clearInterval(timer); b.disabled = false; b.textContent = T.resend; return; }
				b.textContent = fmt(T.resend_in, n);
			}, 1000);
		}
		function value() {
			if (mode === 'email') {
				var e = start.querySelector('[name="flxb_lk_email"]');
				return e ? e.value.trim() : '';
			}
			var hv = start.querySelector('.flx-ph__val');
			var raw = start.querySelector('#flxb-lk-phone');
			return (hv && hv.value) || (raw ? raw.value.trim() : '');
		}
		function send(again) {
			var btn = again ? codeF.querySelector('[data-act="resend"]') : start.querySelector('[type="submit"]');
			var form = again ? codeF : start;
			msg(form, '');
			busy(btn, true, T.sending);
			return api('start', { login: login }).then(function (j) {
				busy(btn, false);
				codeF.querySelector('.flxb-lk__sent').textContent = j.message || '';
				start.hidden = true;
				codeF.hidden = false;
				state('code');
				countdown();
				var inp = codeF.querySelector('.flxb-lk__code');
				inp.value = '';
				inp.focus();
			}).catch(function (e) {
				busy(btn, false);
				if (again) { codeF.querySelector('[data-act="resend"]').disabled = false; }
				msg(form, e.message || T.error);
			});
		}

		root.querySelectorAll('.flxb-lk__step--start .flxb-lk__tab').forEach(function (t) {
			t.addEventListener('click', function () {
				mode = t.getAttribute('data-mode');
				root.querySelectorAll('.flxb-lk__step--start .flxb-lk__tab').forEach(function (o) {
					o.classList.toggle('is-on', o === t);
					o.setAttribute('aria-selected', o === t ? 'true' : 'false');
				});
				root.querySelectorAll('.flxb-lk__step--start [data-for]').forEach(function (f) { f.hidden = f.getAttribute('data-for') !== mode; });
				msg(start, '');
				var f = root.querySelector('[data-for="' + mode + '"] input:not([type="hidden"])');
				if (f) { f.focus(); }
			});
		});
		start.addEventListener('submit', function (ev) {
			ev.preventDefault();
			login = value();
			if (!login) { msg(start, mode === 'email' ? T.need_email : T.need_phone); return; }
			send(false);
		});
		codeF.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var code = codeF.querySelector('.flxb-lk__code').value.replace(/\D+/g, '');
			if (code.length !== 6) { msg(codeF, T.need_code); return; }
			var btn = codeF.querySelector('[type="submit"]');
			busy(btn, true, T.checking);
			msg(codeF, '');
			api('verify', { login: login, code: code }).then(function (j) {
				busy(btn, false);
				clearInterval(timer);
				showResult(j.data || {});
			}).catch(function (e) { busy(btn, false); msg(codeF, e.message || T.error); });
		});
		codeF.querySelector('.flxb-lk__code').addEventListener('input', function () {
			this.value = this.value.replace(/\D+/g, '').slice(0, 6);
			if (this.value.length === 6) { codeF.requestSubmit ? codeF.requestSubmit() : codeF.dispatchEvent(new Event('submit', { cancelable: true })); }
		});
		root.addEventListener('click', function (ev) {
			var a = ev.target.closest('[data-act]');
			if (a) {
				var act = a.getAttribute('data-act');
				if (act === 'change') { clearInterval(timer); codeF.hidden = true; start.hidden = false; state('start'); }
				if (act === 'resend') { send(true); }
				if (act === 'logout') {
					api('logout').finally(function () {
						root.querySelector('.flxb-lk__result').hidden = true;
						codeF.hidden = true; start.hidden = false; state('start');
					});
				}
				return;
			}
			var tab = ev.target.closest('.flxb-lk__tabs--result .flxb-lk__tab');
			if (tab) {
				var v = tab.getAttribute('data-view');
				root.querySelectorAll('.flxb-lk__tabs--result .flxb-lk__tab').forEach(function (o) {
					o.classList.toggle('is-on', o === tab);
					o.setAttribute('aria-selected', o === tab ? 'true' : 'false');
				});
				root.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== v; });
			}
		});

		/* A verified visitor (cookie) or a signed-in user sees the list at once. */
		state('loading');
		api('data').then(function (j) {
			if (j && j.ok && j.data) { showResult(j.data); } else { state('start'); }
		}).catch(function () { state('start'); });
	}

	function boot() { document.querySelectorAll('.flxb-lk').forEach(setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
