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

	/* 2.16.0: the number of a phone field (E.164 from the shared FluxWP phone field, else as typed). */
	function phoneOf(el) {
		var w = el && el.closest ? el.closest('.flx-ph') : null, v = w ? w.querySelector('.flx-ph__val') : null;
		return v ? v.value.trim() : (el ? String(el.value || '').trim() : '');
	}
	function phoneBad(scope) {
		var bad = null;
		(scope || document).querySelectorAll('.flx-ph.is-bad').forEach(function (w) { if (!bad && w.offsetParent !== null) bad = w.querySelector('.flx-ph__num'); });
		return bad;
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
		/* Units of the picked option (1 for options without a − / + quantity). */
		function unitsOf(P, T, opt) {
			if (!opt || opt === '__free' || !T.opts || !T.opts[opt] || !T.opts[opt].qty) return 1;
			var rr = P.querySelector('input[name="o[' + tid() + ']"][value="' + opt + '"]');
			var cc = rr && rr.closest('label') ? rr.closest('label').querySelector('[data-flxwb-count]') : null;
			return Math.max(1, Math.min(+T.max || 1000, parseInt(cc && cc.value, 10) || 1));
		}
		/* 2.16.0: share cards (proxy types): one per unit, "Apply to all" on the first when there are more. */
		function syncShares(T, opt) {
			form.querySelectorAll('[data-flxwb-proxy]').forEach(function (fs) { fs.hidden = fs.getAttribute('data-flxwb-proxy') !== String(tid()); });
			var fs = form.querySelector('[data-flxwb-proxy="' + tid() + '"]');
			if (!fs) return;
			var other = (fs.querySelector('[data-flxwb-owner-mode]:checked') || {}).value === 'other';
			var box = fs.querySelector('[data-flxwb-shares]'), help = fs.querySelector('[data-flxwb-self-help]');
			if (help) help.hidden = other;
			if (!box) return;
			box.hidden = !other;
			var want = unitsOf(panel(), T, opt), cards = box.querySelectorAll('[data-flxwb-share]'), tpl = fs.querySelector('[data-flxwb-share-tpl]');
			var last = cards.length ? cards[cards.length - 1] : null;
			for (var i = cards.length; i < want && tpl; i++) {
				var n = String(i + 1), wrap = document.createElement('div');
				wrap.innerHTML = tpl.innerHTML.replace(/__NO__/g, (i + 1).toLocaleString(document.documentElement.lang || undefined)).replace(/__N__/g, n);
				var card = wrap.firstElementChild;
				card.querySelectorAll('[id]').forEach(function (x) {
					var nid = x.id + '-' + Math.random().toString(36).slice(2, 7), lab = card.querySelector('label[for="' + x.id + '"]');
					if (lab) lab.setAttribute('for', nid);
					x.id = nid;
				});
				if (last) last.after(card); else box.prepend(card);
				last = card;
			}
			cards = box.querySelectorAll('[data-flxwb-share]');
			for (var j = cards.length - 1; j >= want; j--) cards[j].remove();
			var first = box.querySelector('[data-flxwb-apply-all]');
			if (first) first.hidden = want < 2;
		}

		/* 2.23.0: a hand-written amount as pieces ("3 Saplings") when the type is sold by the piece. */
		function pieceCount(T, sum) {
			return T.unit && T.unit.price > 0 && sum > 0 ? Math.max(1, Math.floor((sum + 0.001) / T.unit.price)) * (T.unit.n || 1) : 0;
		}
		function pieces(T, sum) {
			var n = pieceCount(T, sum);
			return n ? (n === 1 ? T.unit.one : String(T.unit.many || '%s').replace('%s', n.toLocaleString(document.documentElement.lang || undefined))) : '';
		}
		function fieldError(el, msg) {
			if (window.flxField && el && el.closest('.flx-fld')) { window.flxField.setError(el, msg); return true; }
			return false;
		}

		/* 2.16.0: summary lines (steps layout): what is picked, how many, the price. */
		function summary(T, P, opt, sum) {
			var box = form.querySelector('[data-flxwb-sum-lines]');
			if (!box) return;
			var tab = form.querySelector('[data-flxwb-tab][aria-selected="true"] .flxwb-tab__title');
			var title = tab ? tab.textContent : '';
			var rows = [];
			if (opt && opt !== '__free' && T.opts && T.opts[opt]) {
				var r = P.querySelector('input[name="o[' + tid() + ']"][value="' + opt + '"]');
				var lb = r && r.closest('label') ? r.closest('label').querySelector('.flxwb-pkg__count') : null;
				var u = unitsOf(P, T, opt);
				var name = lb ? lb.textContent.trim() : '';
				if (!title && !name) name = pieces(T, T.opts[opt].price * u) || '';
				rows.push([title + (title && name ? ' · ' : '') + name, (u > 1 ? u + ' × ' : '') + money(T.opts[opt].price)]);
			} else if (title || T.unit) {
				var pc = pieces(T, sum);
				rows.push([(title || '') + (pc ? (title ? ' · ' : '') + pc : ''), sum > 0 ? money(sum) : '—']);
			}
			box.innerHTML = '';
			rows.forEach(function (x) {
				var dt = document.createElement('dt'), dd = document.createElement('dd');
				dt.textContent = x[0]; dd.textContent = x[1];
				box.appendChild(dt); box.appendChild(dd);
			});
		}

		/* 2.17.0: option view "select" / "smart": a drop-down mirrors the radios; "smart" folds the list into one
		   row after the first pick and opens the rest of the form. */
		function pickSync() {
			form.querySelectorAll('[data-flxwb-optview]').forEach(function (fs) {
				if (fs.getAttribute('data-flxwb-optview') === 'list') return;
				var r = fs.querySelector('input[type="radio"]:checked'), sel = fs.querySelector('[data-flxwb-pick]');
				if (sel && r && sel.value !== r.value) sel.value = r.value;
				var was = fs.classList.contains('is-picked');
				fs.classList.toggle('is-picked', !!r);
				if (r && !was) {
					fs.classList.add('is-anim');
					setTimeout(function () { fs.classList.remove('is-anim'); }, 700);
				}
			});
			var P = panel(), sm = P && P.querySelector('[data-flxwb-optview="smart"]');
			var wait = !!(sm && !sm.querySelector('input[type="radio"]:checked'));
			var waited = root.classList.contains('is-await');
			root.classList.toggle('is-await', wait);
			if (waited && !wait) {
				root.classList.add('is-reveal');
				setTimeout(function () { root.classList.remove('is-reveal'); }, 900);
			}
		}
		form.querySelectorAll('[data-flxwb-pick]').forEach(function (sel) {
			var pick = function () {
				var fs = sel.closest('[data-flxwb-optview]');
				var r = fs && fs.querySelector('input[type="radio"][value="' + sel.value + '"]');
				if (r) r.checked = true;
			};
			sel.addEventListener('input', pick);
			sel.addEventListener('change', pick);
		});

		function update() {
			var T = data.types[tid()] || {}, P = panel(), sum = 0, label = '', pcs = 1;
			if (!P) return;
			pickSync();
			var opt = checked(P, 'o[' + tid() + ']');
			var free = P.querySelector('[data-flxwb-free]');
			if (free && !free.hasAttribute('data-flxwb-free-open')) free.hidden = !!(P.querySelector('input[type="radio"][name="o[' + tid() + ']"]')) && opt !== '__free';
			if (opt === '__free' || opt === '') {
				var ai = P.querySelector('[data-flxwb-amount-input]');
				sum = parseAmount(ai && ai.value);
				P.querySelectorAll('[data-flxwb-amount]').forEach(function (c) { c.classList.toggle('is-active', sum > 0 && Math.abs(parseFloat(c.getAttribute('data-flxwb-amount')) - sum) < 0.001); });
				if (T.unit && T.unit.unit === 'fidan' && pieceCount(T, sum)) label = pieceCount(T, sum).toLocaleString(document.documentElement.lang || undefined) + ' 🌱';
				pcs = pieceCount(T, sum) || 1;
			} else if (T.opts && T.opts[opt]) {
				var o = T.opts[opt], units = 1;
				if (o.qty) {
					var radio = P.querySelector('input[name="o[' + tid() + ']"][value="' + opt + '"]');
					var cnt = radio && radio.closest('label') ? radio.closest('label').querySelector('[data-flxwb-count]') : null;
					units = Math.max(1, Math.min(+T.max || 1000, parseInt(cnt && cnt.value, 10) || 1));
				}
				sum = o.price * units;
				var n = units * (o.n || 1);
				pcs = n;
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
			/* 2.23.0: "One-time / Monthly" tabs drive the monthly boxes; a type without monthly giving stays one-time. */
			var pt = form.querySelector('[data-flxwb-periods]');
			if (pt) {
				var wantM = !!pt.querySelector('[data-flxwb-period="monthly"][aria-selected="true"]');
				form.querySelectorAll('input[name^="m["]').forEach(function (m) { m.checked = wantM; });
				var pn = form.querySelector('[data-flxwb-period-note]');
				if (pn) pn.hidden = !(wantM && monthly);
			}
			syncShares(T, opt);
			summary(T, P, opt, sum);
			if (cert) cert.hidden = !T.cert;
			var print = T.cert && checked(form, 'cert') === 'print';
			if (addr) addr.hidden = !print;
			/* 2.17.0: "I will cover the transaction fee": the highest fee of the payment methods. */
			var fb = form.querySelector('[data-flxwb-fee]');
			if (fb) {
				var tx = data.txfee || [], hi = 0;
				tx.forEach(function (r) { hi = Math.max(hi, sum * (+r.pct || 0) / 100 + (+r.fixed || 0)); });
				fb.hidden = !(sum > 0) || !(hi > 0);
				var fa = fb.querySelector('[data-flxwb-fee-amount]');
				if (fa) fa.textContent = String(L.feeUpTo || '(%s)').replace('%s', money(hi));
			}
			if (print && +data.fee > 0) sum += +data.fee;
			if (total) total.textContent = money(sum) + (monthly && monthly.checked ? ' ' + (L.monthly || '') : '');
			if (meta) meta.textContent = label;

			/* On behalf of someone else: labels, account defaults, "send me a copy". */
			var other = forOther();
			form.querySelectorAll('[data-flxwb-name-label],[data-flxwb-name-help]').forEach(function (el) {
				var k = other ? (!T.cert && el.hasAttribute('data-nocert') ? 'data-nocert' : 'data-other') : 'data-self';
				el.textContent = el.getAttribute(k) || el.textContent;
			});
			/* Proxy types ask for names per share in the panel; types without a certificate have nothing to send. */
			var who = form.querySelector('[data-flxwb-who]'), dl = form.querySelector('[data-flxwb-deliver]'), nf = form.querySelector('[data-flxwb-name]');
			if (who) who.hidden = !!T.proxy;
			if (dl) dl.hidden = !T.cert;
			/* 2.24.0: with a certificate the name sits under "Where should the certificate go?"; without one, above it. */
			if (nf && dl) {
				var dt = dl.querySelector('.flxwb-deliver__title');
				if (T.cert && nf.parentNode !== dl) { if (dt) dt.after(nf); else dl.prepend(nf); }
				else if (!T.cert && nf.parentNode === dl) dl.before(nf);
			}
			/* 2.0.7: certificate designs and the fields of the picked one. */
			var ds = form.querySelector('[data-flxwb-designs]');
			if (ds) {
				ds.hidden = !T.cert;
				var pk = ds.querySelector('input[name="tpl"]:checked');
				ds.querySelectorAll('[data-flxwb-dfields]').forEach(function (b) { b.hidden = !pk || b.getAttribute('data-flxwb-dfields') !== pk.value; });
			}
			/* 2.23.0: for myself the checkout name is used; the name field is only asked for someone else. */
			if (nf) {
				nf.hidden = !other;
				var ni = nf.querySelector('input[name="ben_name"]');
				if (ni) ni.disabled = !other;
			}
			var ph = form.querySelector('[data-flxwb-phone]'), em = form.querySelector('[data-flxwb-email]');
			var sm = form.querySelector('[data-flxwb-sendme]');
			if (sm) sm.hidden = !(T.cert && other && ((ph && phoneOf(ph)) || (em && em.value.trim())));
			syncPeople(T, pcs, other);
		}

		/* 2.24.0: more saplings for someone else: other people, each with their own certificate (one sapling each). */
		var peopleMax = 1;
		function syncPeople(T, pcs, other) {
			var box = form.querySelector('[data-flxwb-people]');
			if (!box) return;
			var on = !!T.cert && !T.proxy && other && pcs > 1;
			box.hidden = !on;
			peopleMax = Math.max(0, pcs - 1);
			var cards = box.querySelectorAll('[data-flxwb-person]');
			cards.forEach(function (c, i) {
				c.querySelectorAll('input').forEach(function (x) { x.disabled = !on || i >= peopleMax; });
				c.hidden = i >= peopleMax;
				var no = c.querySelector('[data-flxwb-person-no]');
				if (no) no.textContent = String(L.person || '%s').replace('%s', (i + 2).toLocaleString(document.documentElement.lang || undefined));
				var ct = c.querySelector('[data-flxwb-person-count]');
				if (ct) ct.textContent = String(L.sapling1 || '%s').replace('%s', (1).toLocaleString(document.documentElement.lang || undefined));
			});
			var shown = Math.min(cards.length, peopleMax);
			var add = box.querySelector('[data-flxwb-people-add]'), note = box.querySelector('[data-flxwb-people-note]');
			if (add) add.hidden = shown >= peopleMax;
			if (note) note.hidden = !shown;
		}
		form.addEventListener('click', function (e) {
			var add = e.target.closest && e.target.closest('[data-flxwb-people-add]');
			var del = e.target.closest && e.target.closest('[data-flxwb-person-del]');
			if (!add && !del) return;
			e.preventDefault();
			var box = form.querySelector('[data-flxwb-people]');
			if (!box) return;
			if (del) {
				var c = del.closest('[data-flxwb-person]');
				if (c) c.remove();
				update();
				return;
			}
			var list = box.querySelector('[data-flxwb-people-list]'), tpl = box.querySelector('[data-flxwb-people-tpl]');
			if (!list || !tpl || list.querySelectorAll('[data-flxwb-person]').length >= peopleMax) return;
			var wrap = document.createElement('div');
			wrap.innerHTML = tpl.innerHTML;
			var card = wrap.firstElementChild;
			card.querySelectorAll('[id]').forEach(function (x) {
				var nid = x.id + '-' + Math.random().toString(36).slice(2, 7), lab = card.querySelector('label[for="' + x.id + '"]');
				if (lab) lab.setAttribute('for', nid);
				x.id = nid;
			});
			list.appendChild(card);
			update();
			var nm = card.querySelector('[data-flxwb-person-name]');
			if (nm) nm.focus();
		});

		/* 2.23.1: for myself, the account's own phone / e-mail sit in the fields read-only. */
		function lockMine() {
			var ph = form.querySelector('[data-flxwb-phone]'), em = form.querySelector('[data-flxwb-email]');
			var other = forOther();
			[[ph, me.phone], [em, me.email]].forEach(function (p) {
				if (!p[0]) return;
				var dg = function (x) { return String(x || '').replace(/\D+/g, '').replace(/^0+/, '').slice(-9); };
				var on = !other && !!p[1] && (p[0] === ph ? (!!phoneOf(ph) && dg(phoneOf(ph)) === dg(p[1])) : p[0].value.trim() === String(p[1]).trim());
				p[0].readOnly = on;
				var card = p[0].closest('.flxwb-dcard');
				if (card) card.classList.toggle('is-locked', on);
				var cc = p[0] === ph && ph.closest('.flx-ph') ? ph.closest('.flx-ph').querySelector('.flx-ph__cc') : null;
				if (cc) cc.disabled = on;
			});
		}

		/* Switching "for myself" / "someone else": account defaults in or out of the delivery fields. */
		form.querySelectorAll('input[name="for_other"]').forEach(function (r) {
			r.addEventListener('change', function () {
				var ph = form.querySelector('[data-flxwb-phone]'), em = form.querySelector('[data-flxwb-email]');
				var other = forOther();
				[[ph, me.phone], [em, me.email]].forEach(function (p) {
					if (!p[0]) return;
					var dg = function (x) { return String(x || '').replace(/\D+/g, '').replace(/^0+/, ''); };
					var same = p[0] === ph ? (p[1] && dg(phoneOf(ph)).slice(-9) === dg(p[1]).slice(-9)) : p[0].value === p[1];
					if (other && p[1] && same) { p[0].value = ''; if (p[0] === ph && ph.closest('.flx-ph') && ph.closest('.flx-ph').flxPhone) ph.closest('.flx-ph').flxPhone.check(); }
					else if (!other && !p[0].value && p[1]) { p[0].value = p[1]; if (p[0] === ph && ph.closest('.flx-ph') && ph.closest('.flx-ph').flxPhone) ph.closest('.flx-ph').flxPhone.check(); }
				});
				lockMine();
			});
		});

		form.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-flxwb-apply-all]');
			if (!b) return;
			e.preventDefault();
			var box = b.closest('[data-flxwb-shares]'), cards = box ? box.querySelectorAll('[data-flxwb-share]') : [];
			if (cards.length < 2) return;
			var nm = cards[0].querySelector('[data-flxwb-owner-name]'), ph = cards[0].querySelector('.flx-ph');
			var pv = ph ? ph.querySelector('.flx-ph__num').value : '', iso = ph && ph.flxPhone ? ph.flxPhone.iso : '';
			for (var i = 1; i < cards.length; i++) {
				var n2 = cards[i].querySelector('[data-flxwb-owner-name]');
				if (n2 && nm) n2.value = nm.value;
				var p2 = cards[i].querySelector('.flx-ph');
				if (p2 && p2.flxPhone) {
					if (iso) p2.flxPhone.setCountry(iso, true);
					p2.flxPhone.num.value = pv;
					p2.flxPhone.check();
				} else {
					var t2 = cards[i].querySelector('[data-flxwb-owner-phone]'), t1 = cards[0].querySelector('[data-flxwb-owner-phone]');
					if (t2 && t1) t2.value = t1.value;
				}
			}
			b.classList.add('is-done');
			setTimeout(function () { b.classList.remove('is-done'); }, 1200);
		});

		/* 2.23.0: amount buttons: writing an amount picks "Other amount"; emptying the field goes back to the buttons. */
		form.addEventListener('input', function (e) {
			var ai = e.target && e.target.matches && e.target.matches('[data-flxwb-amount-input]') ? e.target : null;
			if (!ai) return;
			fieldError(ai, '');
			var fs = ai.closest('[data-flxwb-amounts]');
			if (!fs) return;
			var has = String(ai.value || '').trim() !== '';
			var rf = fs.querySelector('input[type="radio"][value="__free"]'), ro = fs.querySelector('input[type="radio"]:not([value="__free"])');
			if (has && rf) rf.checked = true; else if (!has && ro) ro.checked = true;
		}, true);
		form.querySelectorAll('[data-flxwb-period]').forEach(function (b, i, all) {
			b.addEventListener('click', function () {
				all.forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
				update();
			});
			b.addEventListener('keydown', function (e) {
				if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
				var n = all[(i + 1) % all.length];
				n.focus(); n.click();
			});
		});
		/* Currency menu closes on a click outside. */
		document.addEventListener('click', function (e) {
			form.querySelectorAll('.flxwb-curdd[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
		});

		form.addEventListener('change', update);
		form.addEventListener('input', update);

		/* 2.21.0: the photo band opens the page; the theme's "Have an account?" line moves under it. */
		var hero = form.closest('.flxwb') && form.closest('.flxwb').querySelector('.flxwb-hero');
		var acct = hero ? document.querySelector('.flx-hesap-satiri') : null;
		if (acct && (acct.compareDocumentPosition(hero) & Node.DOCUMENT_POSITION_FOLLOWING)) { hero.parentNode.insertBefore(acct, hero.nextSibling); acct.classList.add('flxwb-acct'); }

		/* 2.21.0: quick quantity buttons (1 / 5 / 10 …) under a quantity option. */
		var quick = function () {
			form.querySelectorAll('.flxwb-quick, .flxwb-amts').forEach(function (g) {
				var b0 = g.querySelector('[data-flxwb-quick]'), c = b0 ? document.getElementById(b0.getAttribute('aria-controls')) : null;
				if (!c) return;
				var rq = c.closest('label') && c.closest('label').querySelector('input[type="radio"]');
				g.querySelectorAll('[data-flxwb-quick]').forEach(function (b) { b.setAttribute('aria-pressed', String((!rq || rq.checked) && +b.getAttribute('data-flxwb-quick') === (parseInt(c.value, 10) || 0))); });
			});
		};
		form.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-flxwb-quick]');
			if (!b) return;
			var c = document.getElementById(b.getAttribute('aria-controls'));
			if (!c) return;
			var r = c.closest('label') && c.closest('label').querySelector('input[type="radio"]');
			if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
			/* 2.23.0: amount buttons: picking one clears the hand-written amount and its warning. */
			var fo = b.closest('[data-flxwb-amounts]'), fa = fo && fo.querySelector('[data-flxwb-amount-input]');
			if (fa) { fa.value = ''; fieldError(fa, ''); }
			c.value = b.getAttribute('data-flxwb-quick');
			c.dispatchEvent(new Event('input', { bubbles: true }));
			c.dispatchEvent(new Event('change', { bubbles: true }));
		});
		form.addEventListener('input', quick);
		form.addEventListener('change', quick);
		form.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-flxwb-step]')) setTimeout(quick, 0); });

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
			if (P && opt === '' && P.querySelector('input[type="radio"][name="o[' + tid() + ']"]')) {
				msg = L.needOption || '';
				focus = P.querySelector('[data-flxwb-pick]') || P.querySelector('input[type="radio"][name="o[' + tid() + ']"]');
			} else if (P && (opt === '__free' || opt === '')) {
				var ai = P.querySelector('[data-flxwb-amount-input]');
				var a = parseAmount(ai && ai.value);
				if (!(a > 0) || a < (+T.min || 0)) {
					/* 2.23.0: the lowest amount is said only now, under the field. */
					msg = a > 0 && T.minMsg ? T.minMsg : (L.needAmount || '');
					focus = ai;
					if (fieldError(ai, msg)) { e.preventDefault(); ai.focus(); var oe = form.querySelector('.flxwb-note--err'); if (oe) oe.remove(); return; }
				}
			}
			var nm = form.querySelector('input[name="ben_name"]');
			if (!msg && !T.proxy && forOther() && nm && !nm.value.trim()) { msg = L.needName || ''; focus = nm; }
			if (!msg && T.proxy && P) {
				var pfs = form.querySelector('[data-flxwb-proxy="' + tid() + '"]');
				var ok = pfs ? pfs.querySelector('[data-flxwb-consent]') : null;
				var badp = pfs ? phoneBad(pfs) : null;
				if (badp) { msg = L.badPhone || ''; focus = badp; }
				else if (!ok || !ok.checked) { msg = L.needConsent || ''; focus = ok; }
			}
			/* 2.24.0: every other person needs a name; their e-mail / phone must look right. */
			if (!msg) {
				var pb = form.querySelector('[data-flxwb-people]');
				if (pb && !pb.hidden) {
					pb.querySelectorAll('[data-flxwb-person]').forEach(function (c) {
						if (msg || c.hidden) return;
						var pn = c.querySelector('[data-flxwb-person-name]'), pe = c.querySelector('[data-flxwb-person-email]'), pp = c.querySelector('[data-flxwb-person-phone]');
						var pv = pp ? phoneOf(pp) : '', pev = pe ? pe.value.trim() : '';
						if (pn && !pn.value.trim()) { msg = L.needPersonName || L.needName || ''; focus = pn; }
						else if (pev && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(pev)) { msg = L.badEmail || ''; focus = pe; }
						else if (pv && pv.replace(/\D+/g, '').length < 10) { msg = L.badPhone || ''; focus = pp; }
					});
				}
			}
			/* 2.0.5: at least one of WhatsApp / e-mail (the account's own count when donating for yourself). */
			var dv = form.querySelector('[data-flxwb-deliver]'), who = form.querySelector('[data-flxwb-who]');
			if (!msg && dv && !dv.hidden && !(who && who.hidden)) {
				var ph = dv.querySelector('[data-flxwb-phone]'), em = dv.querySelector('[data-flxwb-email]');
				var pv = phoneOf(ph), ev = em ? em.value.trim() : '';
				var mine = !forOther() && ((data.me && (data.me.phone || data.me.email)) ? true : false);
				if (ev && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(ev)) { msg = L.badEmail || ''; focus = em; }
				else if ((pv && pv.replace(/\D+/g, '').length < 10) || phoneBad(dv)) { msg = L.badPhone || ''; focus = ph; }
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
					window.sessionStorage.setItem('flxb_contact', JSON.stringify({ p: phoneOf(p2), e: e2 ? e2.value.trim() : '' }));
				}
			} catch (x) {}
			form.querySelectorAll('[data-flxwb-dfields]').forEach(function (b) {
				if (b.hidden) b.querySelectorAll('input,select,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
			});
			form.querySelectorAll('[data-flxwb-panel]').forEach(function (p) {
				if (p.hidden) p.querySelectorAll('input,select,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
			});
			form.querySelectorAll('[data-flxwb-proxy], [data-flxwb-shares]').forEach(function (f) {
				if (f.hidden) f.querySelectorAll('input,select,textarea').forEach(function (x) { if (!x.disabled) { x.disabled = true; x.setAttribute('data-flxwb-off', ''); } });
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
		lockMine();
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
		/* Kopyalanamazsa metni seçilebilir kutuda göster (flx-brand modal). */
		var manual = function () { if (window.flxDialog) window.flxDialog.prompt({ title: L.copyFail || '', value: text }); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { manual(); });
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { if (document.execCommand('copy')) done(); else manual(); } catch (x) { manual(); }
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
