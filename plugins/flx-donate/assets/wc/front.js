/*!
 * Flux Donate · WooCommerce front end: donation options form, copy buttons, bank receipt upload.
 * Ported from Flux WooCommerce Bağış. GPL v2 or later.
 */
(function () {
	'use strict';
	var L = window.FLXWB || {};
	var M = L.money || { symbol: '₺', format: '%2$s %1$s', decimals: 2, dec: ',', thou: '.' };

	function money(n) {
		var d = +M.decimals || 0;
		var s = (Math.round(n * Math.pow(10, d)) / Math.pow(10, d)).toFixed(d);
		var parts = s.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, M.thou);
		return M.format.replace('%1$s', M.symbol).replace('%2$s', parts.join(M.dec)).replace(/&nbsp;/g, ' ');
	}

	function parseAmount(raw) {
		var s = String(raw || '').replace(/[^\d,.]/g, '');
		if (!s) return 0;
		var c = s.lastIndexOf(','), d = s.lastIndexOf('.');
		if (c > -1 && (d === -1 || c > d)) s = s.replace(/\./g, '').replace(',', '.');
		else if (d > -1 && c === -1 && /\.\d{3}$/.test(s)) s = s.replace(/\./g, '');
		else s = s.replace(/,/g, '');
		return parseFloat(s) || 0;
	}

	/* ---------- Donation form ---------- */
	function setupForm(root) {
		var data;
		try { data = JSON.parse(root.getAttribute('data-flxwb') || '{}'); } catch (e) { return; }
		var form = root.querySelector('.flxwb-form');
		if (!form || !data.types) return;
		var typeInput = form.querySelector('[data-flxwb-type]');
		var total = form.querySelector('[data-flxwb-total]');
		var meta = form.querySelector('[data-flxwb-meta]');
		var cert = form.querySelector('[data-flxwb-cert]');
		var addr = form.querySelector('[data-flxwb-address]');
		var me = data.me || {};

		function tid() { return typeInput ? typeInput.value : Object.keys(data.types)[0]; }
		function panel() { return form.querySelector('[data-flxwb-panel="' + tid() + '"]'); }
		function checked(scope, name) { var el = scope.querySelector('input[name="' + name + '"]:checked') || scope.querySelector('input[type="hidden"][name="' + name + '"]'); return el ? el.value : ''; }
		function forOther() { return checked(form, 'for_other') === '1'; }

		function update() {
			var T = data.types[tid()] || {}, P = panel(), sum = 0, label = '';
			if (!P) return;
			var opt = checked(P, 'o[' + tid() + ']');
			var free = P.querySelector('[data-flxwb-free]');
			if (free) free.hidden = !!(P.querySelector('input[type="radio"][name="o[' + tid() + ']"]')) && opt !== '__free';
			if (opt === '__free' || opt === '') {
				var ai = P.querySelector('[data-flxwb-amount-input]');
				sum = parseAmount(ai && ai.value);
				P.querySelectorAll('[data-flxwb-amount]').forEach(function (c) { c.classList.toggle('is-active', sum > 0 && Math.abs(parseFloat(c.getAttribute('data-flxwb-amount')) - sum) < 0.001); });
			} else if (T.opts && T.opts[opt]) {
				var o = T.opts[opt], units = 1;
				if (o.qty) {
					var radio = P.querySelector('input[name="o[' + tid() + ']"][value="' + opt + '"]');
					var cnt = radio && radio.closest('label') ? radio.closest('label').querySelector('[data-flxwb-count]') : null;
					units = Math.max(1, Math.min(+T.max || 1000, parseInt(cnt && cnt.value, 10) || 1));
				}
				sum = o.price * units;
				var n = units * (o.n || 1);
				if (o.unit === 'fidan') label = n.toLocaleString(document.documentElement.lang || undefined) + ' 🌱';
			}
			/* Cards with a − / + quantity show their own live total; the buttons stop at 1 and at the maximum. */
			P.querySelectorAll('[data-flxwb-line]').forEach(function (el) {
				var oo = T.opts && T.opts[el.getAttribute('data-flxwb-line')];
				var card = el.closest('label'), c = card && card.querySelector('[data-flxwb-count]');
				if (!oo || !c) return;
				var u = Math.max(1, Math.min(+T.max || 1000, parseInt(c.value, 10) || 1));
				el.textContent = money(oo.price * u);
				var cl = card.querySelector('[data-flxwb-count-label]');
				if (cl) { var nn = u * (parseInt(cl.getAttribute('data-flxwb-count-label'), 10) || 1); cl.textContent = String(nn === 1 ? (L.sapling1 || '%s') : (L.saplingN || '%s')).replace('%s', nn.toLocaleString(document.documentElement.lang || undefined)); }
				card.querySelectorAll('[data-flxwb-step]').forEach(function (b) {
					var off = +b.getAttribute('data-flxwb-step') < 0 ? u <= 1 : u >= (+T.max || 1000);
					if (off) b.setAttribute('aria-disabled', 'true'); else b.removeAttribute('aria-disabled');
				});
			});
			var monthly = form.querySelector('input[name="m[' + tid() + ']"]');
			if (cert) cert.hidden = !T.cert;
			var print = T.cert && checked(form, 'cert') === 'print';
			if (addr) addr.hidden = !print;
			if (print && +data.fee > 0) sum += +data.fee;
			if (total) total.textContent = money(sum) + (monthly && monthly.checked ? ' ' + (L.monthly || '') : '');
			if (meta) meta.textContent = label;

			/* On behalf of someone else: labels, account defaults, "send me a copy". */
			var other = forOther();
			form.querySelectorAll('[data-flxwb-name-label],[data-flxwb-name-help],[data-flxwb-deliver-help]').forEach(function (el) {
				var k = other ? (!T.cert && el.hasAttribute('data-nocert') ? 'data-nocert' : 'data-other') : 'data-self';
				el.textContent = el.getAttribute(k) || el.textContent;
			});
			/* Proxy types ask for names per share in the panel; types without a certificate have nothing to send. */
			var who = form.querySelector('[data-flxwb-who]'), dl = form.querySelector('[data-flxwb-deliver]'), nf = form.querySelector('[data-flxwb-name]');
			if (who) who.hidden = !!T.proxy;
			if (dl) dl.hidden = !T.cert;
			/* 2.0.7: certificate designs and the fields of the picked one. */
			var ds = form.querySelector('[data-flxwb-designs]');
			if (ds) {
				ds.hidden = !T.cert;
				var pk = ds.querySelector('input[name="tpl"]:checked');
				ds.querySelectorAll('[data-flxwb-dfields]').forEach(function (b) { b.hidden = !pk || b.getAttribute('data-flxwb-dfields') !== pk.value; });
			}
			if (nf) nf.hidden = !T.cert && !other;
			var ph = form.querySelector('[data-flxwb-phone]'), em = form.querySelector('[data-flxwb-email]');
			var sm = form.querySelector('[data-flxwb-sendme]');
			if (sm) sm.hidden = !(T.cert && other && ((ph && ph.value.trim()) || (em && em.value.trim())));
		}

		/* Switching "for myself" / "someone else": account defaults in or out of the delivery fields. */
		form.querySelectorAll('input[name="for_other"]').forEach(function (r) {
			r.addEventListener('change', function () {
				var ph = form.querySelector('[data-flxwb-phone]'), em = form.querySelector('[data-flxwb-email]');
				var other = forOther();
				[[ph, me.phone], [em, me.email]].forEach(function (p) {
					if (!p[0]) return;
					if (other && p[1] && p[0].value === p[1]) p[0].value = '';
					else if (!other && !p[0].value && p[1]) p[0].value = p[1];
				});
			});
		});

		form.addEventListener('change', update);
		form.addEventListener('input', update);

		form.querySelectorAll('[data-flxwb-tab]').forEach(function (b, i, all) {
			b.addEventListener('click', function () {
				var t = b.getAttribute('data-flxwb-tab');
				all.forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
				form.querySelectorAll('[data-flxwb-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-flxwb-panel') !== t; });
				form.querySelectorAll('[data-flxwb-monthly]').forEach(function (m) { m.hidden = m.getAttribute('data-flxwb-monthly') !== t; });
				if (typeInput) typeInput.value = t;
				update();
			});
			b.addEventListener('keydown', function (e) {
				if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
				var n = all[(i + (e.key === 'ArrowRight' ? 1 : all.length - 1)) % all.length];
				n.focus(); n.click();
			});
		});

		form.querySelectorAll('[data-flxwb-step]').forEach(function (b) {
			b.addEventListener('click', function (e) {
				e.preventDefault();
				var card = b.closest('label');
				var radio = card && card.querySelector('input[type="radio"]');
				var count = card && card.querySelector('[data-flxwb-count]');
				if (radio) radio.checked = true;
				if (!count) return;
				var T = data.types[tid()] || {};
				count.value = Math.max(1, Math.min(+T.max || 1000, (parseInt(count.value, 10) || 0) + parseInt(b.getAttribute('data-flxwb-step'), 10)));
				update();
			});
		});
		form.querySelectorAll('[data-flxwb-count]').forEach(function (c) {
			/* Typed numbers are kept between 1 and the maximum (the server checks again). */
			c.addEventListener('change', function () {
				var T = data.types[tid()] || {};
				c.value = Math.max(1, Math.min(+T.max || 1000, parseInt(c.value, 10) || 1));
				update();
			});
			c.addEventListener('focus', function () {
				var radio = c.closest('label') && c.closest('label').querySelector('input[type="radio"]');
				if (radio && !radio.checked) { radio.checked = true; update(); }
			});
		});
		form.querySelectorAll('[data-flxwb-amount]').forEach(function (c) {
			c.addEventListener('click', function () {
				var P = c.closest('[data-flxwb-panel]');
				var ai = P && P.querySelector('[data-flxwb-amount-input]');
				if (ai) ai.value = String(parseFloat(c.getAttribute('data-flxwb-amount'))).replace('.', M.dec);
				update();
			});
		});

		/* Only the visible cause is sent. */
		form.addEventListener('submit', function (e) {
			var err = form.querySelector('.flxwb-note--err'), msg = '', focus = null;
			var T = data.types[tid()] || {}, P = panel();
			var opt = P ? checked(P, 'o[' + tid() + ']') : '';
			if (P && (opt === '__free' || opt === '')) {
				var ai = P.querySelector('[data-flxwb-amount-input]');
				var a = parseAmount(ai && ai.value);
				if (!(a > 0) || a < (+T.min || 0)) {
					var help = ai && ai.closest('.flxwb-field') ? ai.closest('.flxwb-field').querySelector('.flxwb-field__help') : null;
					msg = help ? help.textContent : (ai ? ai.getAttribute('placeholder') : '');
					focus = ai;
				}
			}
			var nm = form.querySelector('input[name="ben_name"]');
			if (!msg && !T.proxy && forOther() && nm && !nm.value.trim()) { msg = L.needName || ''; focus = nm; }
			if (!msg && T.proxy && P) {
				var own = P.querySelector('textarea[name^="owners"]'), ok = P.querySelector('[data-flxwb-consent]');
				var names = own ? own.value.split(/[\r\n;]+/).filter(function (x) { return x.trim(); }).length : 0;
				var shares = 1;
				if (opt && opt !== '__free' && T.opts && T.opts[opt] && T.opts[opt].qty) {
					var rr = P.querySelector('input[name="o[' + tid() + ']"][value="' + opt + '"]');
					var cc = rr && rr.closest('label') ? rr.closest('label').querySelector('[data-flxwb-count]') : null;
					shares = Math.max(1, parseInt(cc && cc.value, 10) || 1);
				}
				if (names > shares) { msg = L.tooMany || ''; focus = own; }
				else if (!ok || !ok.checked) { msg = L.needConsent || ''; focus = ok; }
			}
			/* 2.0.5: at least one of WhatsApp / e-mail (the account's own count when donating for yourself). */
			var dv = form.querySelector('[data-flxwb-deliver]'), who = form.querySelector('[data-flxwb-who]');
			if (!msg && dv && !dv.hidden && !(who && who.hidden)) {
				var ph = dv.querySelector('[data-flxwb-phone]'), em = dv.querySelector('[data-flxwb-email]');
				var pv = ph ? ph.value.trim() : '', ev = em ? em.value.trim() : '';
				var mine = !forOther() && ((data.me && (data.me.phone || data.me.email)) ? true : false);
				if (ev && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(ev)) { msg = L.badEmail || ''; focus = em; }
				else if (pv && pv.replace(/\D+/g, '').length < 10) { msg = L.badPhone || ''; focus = ph; }
				else if (!pv && !ev && !mine) { msg = L.needContact || ''; focus = ph || em; }
			}
			if (!msg) {
				var dsb = form.querySelector('[data-flxwb-designs]');
				var box = dsb && !dsb.hidden ? dsb.querySelector('[data-flxwb-dfields]:not([hidden])') : null;
				if (box) {
					box.querySelectorAll('[data-flxwb-req]').forEach(function (x) {
						if (!msg && !String(x.value || '').trim()) { var lb = x.closest('.flxwb-field'); msg = (L.fillField || '%s').replace('%s', lb ? lb.querySelector('.flxwb-field__label').textContent.replace(/\s*\*$/, '') : ''); focus = x; }
					});
				}
			}
			if (!msg && addr && !addr.hidden) {
				var ta = addr.querySelector('textarea');
				if (ta && ta.value.trim().length < 10) { msg = addr.querySelector('.flxwb-field__label').textContent; focus = ta; }
			}
			if (msg) {
				e.preventDefault();
				if (!err) { err = document.createElement('div'); err.className = 'flxwb-note flxwb-note--err'; err.setAttribute('role', 'alert'); form.querySelector('.flxwb-bar').before(err); }
				err.textContent = msg;
				if (focus) focus.focus();
				return;
			}
			/* 2.0.6: hand the donor's own WhatsApp / e-mail to the checkout's contact fields. */
			try {
				var dv2 = form.querySelector('[data-flxwb-deliver]');
				if (dv2 && !dv2.hidden && !forOther()) {
					var p2 = dv2.querySelector('[data-flxwb-phone]'), e2 = dv2.querySelector('[data-flxwb-email]');
					window.sessionStorage.setItem('flxb_contact', JSON.stringify({ p: p2 ? p2.value.trim() : '', e: e2 ? e2.value.trim() : '' }));
				}
			} catch (x) {}
			form.querySelectorAll('[data-flxwb-dfields]').forEach(function (b) {
				if (b.hidden) b.querySelectorAll('input,select,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
			});
			form.querySelectorAll('[data-flxwb-panel]').forEach(function (p) {
				if (p.hidden) p.querySelectorAll('input,select,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
			});
			[form.querySelector('[data-flxwb-who]')].forEach(function (f) {
				if (f && f.hidden) f.querySelectorAll('input,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
			});
			var btn = form.querySelector('button[type="submit"]');
			if (btn) setTimeout(function () { btn.disabled = true; }, 0);
		});
		window.addEventListener('pageshow', function () {
			/* Only what the submit disabled (the "coming soon" choice stays disabled). */
			form.querySelectorAll('[data-flxwb-off]').forEach(function (x) { x.disabled = false; x.removeAttribute('data-flxwb-off'); });
			var b = form.querySelector('button[type="submit"]'); if (b) b.disabled = false;
		});

		update();
	}

	/* ---------- Copy ---------- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-flxwb-copy]');
		if (!b) return;
		e.preventDefault();
		var text = b.getAttribute('data-flxwb-copy');
		var done = function () {
			b.classList.add('is-done');
			var label = b.querySelector('span'), old = label ? label.textContent : '';
			if (label) label.textContent = L.copied || 'OK';
			setTimeout(function () { b.classList.remove('is-done'); if (label) label.textContent = old; }, 1600);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { window.prompt(L.copyFail || '', text); });
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { if (document.execCommand('copy')) done(); else window.prompt(L.copyFail || '', text); } catch (x) { window.prompt(L.copyFail || '', text); }
			ta.remove();
		}
	});

	/* ---------- Bank receipt upload ---------- */
	function setupUpload(box) {
		var form = box.querySelector('.flxwb-drop');
		if (!form || !window.FormData || !window.fetch || !L.rest) return;
		var input = form.querySelector('[data-flxwb-file]');
		var zone = form.querySelector('.flxwb-drop__zone');
		var title = form.querySelector('.flxwb-drop__text strong');
		var btn = form.querySelector('[data-flxwb-upload-btn]');
		var err = form.querySelector('[data-flxwb-upload-err]');
		var list = box.querySelector('[data-flxwb-files]');
		var titleText = title ? title.textContent : '';

		function showErr(m) { if (err) { err.textContent = m; err.hidden = !m; } }

		input.addEventListener('change', function () {
			var f = input.files && input.files[0];
			form.classList.toggle('is-picked', !!f);
			if (title) title.textContent = f ? f.name : titleText;
			showErr('');
			if (f && L.maxMb && f.size > L.maxMb * 1048576) showErr(L.tooBig);
		});
		['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var f = input.files && input.files[0];
			if (!f) { input.click(); return; }
			if (L.maxMb && f.size > L.maxMb * 1048576) { showErr(L.tooBig); return; }
			var fd = new FormData();
			fd.append('order', box.getAttribute('data-order'));
			fd.append('key', box.getAttribute('data-key'));
			fd.append('dekont', f);
			btn.disabled = true;
			var label = btn.querySelector('span'), old = label ? label.textContent : '';
			if (label) label.textContent = L.uploading || '…';
			showErr('');
			fetch(L.rest + 'receipt', { method: 'POST', body: fd, credentials: 'omit' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok || !res.j || !res.j.ok) throw new Error((res.j && res.j.message) || L.error);
					var li = document.createElement('li');
					var a = document.createElement('a'); a.href = res.j.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = res.j.name;
					var s = document.createElement('small'); s.textContent = res.j.date;
					li.appendChild(a); li.appendChild(s);
					if (list) { list.appendChild(li); list.hidden = false; }
					if (!box.querySelector('[data-flxwb-upload-ok]')) {
						var ok = document.createElement('p');
						ok.className = 'flxwb-note flxwb-note--ok'; ok.setAttribute('data-flxwb-upload-ok', ''); ok.setAttribute('role', 'status');
						ok.textContent = L.uploaded;
						form.before(ok);
					}
					input.value = ''; form.classList.remove('is-picked');
					if (res.j.full) form.remove();
					if (title && L.another) { title.textContent = titleText = L.another; }
					var pill = box.closest('.flxwb-pcard') && box.closest('.flxwb-pcard').querySelector('.flxwb-pill');
					if (pill && L.reviewLabel) { pill.className = 'flxwb-pill flxwb-pill--review'; pill.textContent = L.reviewLabel; }
				})
				.catch(function (x) { showErr(x && x.message ? x.message : L.error); })
				.then(function () { btn.disabled = false; if (label) label.textContent = old; });
		});
	}

	function init() {
		document.querySelectorAll('[data-flxwb]').forEach(setupForm);
		document.querySelectorAll('[data-flxwb-upload]').forEach(setupUpload);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
