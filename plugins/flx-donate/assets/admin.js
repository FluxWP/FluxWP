/* Flux Donate · admin: dashboard (plain SVG charts, no external libraries) and small form helpers. */
(function () {
	'use strict';
	var cfg = window.flxbAdmin || {};
	var t = cfg.i18n || {};
	/* Only http(s) or same-site paths are followed (no javascript:, data: ...); the result starts with a fixed prefix. */
	var safeUrl = function (u) {
		u = String(u == null ? '' : u).trim();
		var l = u.toLowerCase();
		if (!(l.indexOf('https://') === 0 || l.indexOf('http://') === 0 || (u.charAt(0) === '/' && u.charAt(1) !== '/' && u.charAt(1) !== '\\'))) return '';
		// Normalised with encodeURI: quotes, spaces and angle brackets are escaped as well.
		try { return encodeURI(decodeURI(u)); } catch (e) { return ''; }
	};

	/* Buttons that submit long-running forms show a spinner. */
	document.addEventListener('submit', function (e) {
		var b = e.target && e.target.querySelector('[data-flxb-busy]');
		if (b) {
			b.disabled = true;
			b.innerHTML = '<span class="flxf-spin" aria-hidden="true"></span> ' + esc(b.getAttribute('data-flxb-busy'));
		}
	});

	/* Copy buttons: [data-flxb-copy="<input id>"]. Clipboard API, else select + execCommand. */
	function copied(btn, ok) {
		var box = btn.closest('.flxb-copy');
		var msg = box && box.querySelector('.flxb-copy__ok');
		if (msg) {
			msg.textContent = ok ? (t.copied || 'Copied.') : (t.copyFail || 'Press Ctrl+C to copy.');
			msg.classList.toggle('is-fail', !ok);
			clearTimeout(btn._flxbT);
			btn._flxbT = setTimeout(function () { msg.textContent = ''; }, 4000);
		}
	}
	function fallbackCopy(input) {
		input.focus();
		input.select();
		try { input.setSelectionRange(0, input.value.length); } catch (e) { /* old browser */ }
		try { return document.execCommand('copy'); } catch (e) { return false; }
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-flxb-copy]');
		if (btn) {
			var input = document.getElementById(btn.getAttribute('data-flxb-copy'));
			if (!input) return;
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(input.value).then(function () { copied(btn, true); }, function () { copied(btn, fallbackCopy(input)); });
			} else {
				copied(btn, fallbackCopy(input));
			}
			return;
		}
		var inp = e.target.closest && e.target.closest('.flxb-copy__input');
		if (inp) inp.select();
	});

	/* Repeater: [data-flxb-repeater] holds [data-rows], a <template data-template> with __i__ and [data-add]. */
	document.querySelectorAll('[data-flxb-repeater]').forEach(function (rep) {
		var rows = rep.querySelector('[data-rows]');
		var tpl = rep.querySelector('template[data-template]');
		var n = rows ? rows.children.length : 0;
		rep.addEventListener('click', function (e) {
			if (e.target.closest('[data-add]') && tpl && rows) {
				var html = tpl.innerHTML.replace(/__i__/g, 'n' + (n++));
				rows.insertAdjacentHTML('beforeend', html);
				var f = rows.lastElementChild && rows.lastElementChild.querySelector('input[type=text]');
				if (f) f.focus();
				return;
			}
			var rm = e.target.closest('[data-remove]');
			if (rm) {
				var row = rm.closest('[data-row]');
				if (row && rows.children.length > 1) row.remove();
				else if (row) row.querySelectorAll('input:not([type=hidden]):not([type=checkbox])').forEach(function (i) { i.value = ''; });
			}
		});
	});

	/* Add donation: options, currency and the proxy block follow the chosen donation type. */
	document.querySelectorAll('form[data-flxb-add]').forEach(function (form) {
		var data = {};
		try { data = JSON.parse(form.getAttribute('data-flxb-add')) || {}; } catch (e) { data = {}; }
		var type = form.querySelector('[name=type_id]');
		var opt = form.querySelector('[name=option_key]');
		var cur = form.querySelector('[name=currency]');
		var proxy = form.querySelector('[data-flxb-proxy]');
		var free = opt ? opt.options[0].textContent : '';
		var sync = function () {
			var d = data[type.value] || null;
			opt.innerHTML = '';
			if (!d || d.free || !d.options.length) opt.add(new Option(free, ''));
			(d ? d.options : []).forEach(function (o) {
				var label = o.label + (o.price > 0 ? ' — ' + o.price.toLocaleString(cfg.locale || undefined) + ' ' + d.currency : '');
				opt.add(new Option(label, o.key));
			});
			if (d && cur) cur.value = d.currency;
			if (proxy) proxy.hidden = !(d && d.proxy);
		};
		if (type && opt) { type.addEventListener('change', sync); sync(); }
	});

	/* 2.29.0: Add donation. One "type|option" choice, live total, per-person certificate rows and a summary. */
	document.querySelectorAll('form[data-flxb-add2]').forEach(function (form) {
		var data = {}, t = {};
		try { data = JSON.parse(form.getAttribute('data-flxb-add2')) || {}; } catch (e) { data = {}; }
		try { t = JSON.parse(form.getAttribute('data-i18n')) || {}; } catch (e) { t = {}; }
		var loc = form.getAttribute('data-locale') || undefined;
		var q = function (s) { return form.querySelector(s); };
		var pick = q('[name=pick]'), qty = q('[name=quantity]'), unitIn = q('[name=unit_price]'), amountIn = q('[name=amount]'), curIn = q('[name=currency]');
		var priceBox = q('[data-flxb-price]'), freeBox = q('[data-flxb-free]'), qtyBox = q('[data-flxb-qty]'), proxyBox = q('[data-flxb-proxy]');
		var priceBtn = q('[data-flxb-price-toggle]'), card = q('[data-flxb-people-card]'), forOther = q('[data-flxb-for-other]');
		var peopleBox = q('[data-flxb-people]'), rows = q('[data-flxb-people-rows]'), tpl = q('[data-flxb-person-tpl]'), addBtn = q('[data-flxb-person-add]');
		var acc = q('[data-flxb-account]'), donorName = q('[name=donor_name]');
		var money = function (v, c) {
			var sym = c || '';
			try { new Intl.NumberFormat(loc, { style: 'currency', currency: c || 'TRY', currencyDisplay: 'narrowSymbol' }).formatToParts(0).forEach(function (x) { if (x.type === 'currency') sym = x.value; }); } catch (e) {}
			try { return new Intl.NumberFormat(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + ' ' + sym; } catch (e) { return v.toFixed(2) + ' ' + sym; }
		};
		var fmt = function (s, a, b) { return String(s).replace('%1$s', a).replace('%2$s', b); };
		var esc = function (s) { var d = document.createElement('span'); d.textContent = s; return d.innerHTML; };
		var current = function () {
			var parts = (pick.value || '0|').split('|'), d = data[parts[0]] || null, o = null;
			if (d) d.options.forEach(function (x) { if (x.key === parts[1]) o = x; });
			return { d: d, o: o };
		};
		var unitWord = function (o, n) {
			var u = o && o.unit ? o.unit : t.piece;
			return n + ' ' + (u ? u[n === 1 ? 0 : 1] : '');
		};
		var val = function (row, sel) { var i = row.querySelector(sel); return i ? i.value.trim() : ''; };
		var renumber = function () {
			Array.prototype.forEach.call(rows.children, function (row, i) {
				row.querySelector('[data-flxb-person-n]').textContent = i + 1;
				row.querySelectorAll('input[name^="people["], input[data-name^="people["]').forEach(function (inp) {
					['name', 'data-name'].forEach(function (a) {
						var v = inp.getAttribute(a);
						if (v && v.indexOf('people[') === 0) inp.setAttribute(a, v.replace(/^people\[\d+\]/, 'people[' + i + ']'));
					});
				});
			});
		};
		var sync = function () {
			var c = current(), d = c.d, o = c.o;
			var free = !o, n = free ? 1 : Math.max(1, parseInt(qty.value, 10) || 1);
			var cur = d && o ? d.currency : (curIn ? curIn.value : 'TRY');
			var unit = o ? (unitIn.value !== '' ? parseFloat(unitIn.value) || 0 : o.price) : 0;
			var total = free ? (parseFloat(amountIn.value) || 0) : unit * n;
			freeBox.hidden = !free;
			qtyBox.hidden = free;
			priceBtn.hidden = free;
			if (free) priceBox.hidden = true;
			if (o) unitIn.placeholder = o.price;
			if (proxyBox) proxyBox.hidden = !(d && d.proxy);
			card.hidden = !!(d && d.proxy);
			if (acc) acc.hidden = (q('[name=method]:checked') || {}).value !== 'bank_transfer';
			/* People: up to one row per unit. */
			var on = forOther.checked && !card.hidden;
			peopleBox.hidden = !on;
			var all = rows.children.length;
			while (rows.children.length > Math.max(1, n)) rows.removeChild(rows.lastElementChild);
			if (rows.children.length !== all) renumber();
			addBtn.hidden = rows.children.length >= n;
			var units = o ? o.per : 1, k = rows.children.length;
			Array.prototype.forEach.call(rows.children, function (row, i) {
				var b = row.querySelector('[data-flxb-person-units]');
				b.textContent = unitWord(o, (i === 0 ? n - (k - 1) : 1) * units);
				row.querySelector('[data-flxb-person-remove]').hidden = i === 0;
			});
			q('[data-flxb-people-count]').textContent = on ? fmt(t.used, n * units, unitWord(o, n * units)) : '';
			/* Summary */
			q('[data-flxb-total]').textContent = total > 0 ? money(total, cur) : '—';
			q('[data-flxb-sum-type]').textContent = d ? d.title + (o && d.options.length > 1 ? ' · ' + o.label : '') : t.free;
			q('[data-flxb-sum-calc]').textContent = o ? fmt(t.times, n, money(unit, cur)) : '';
			var list = q('[data-flxb-sum-people]'), items = [];
			var how = function (ph, em) { return ph && em ? t.both : ph ? t.whatsapp : em ? t.email : t.toDonor; };
			if (on) {
				Array.prototype.forEach.call(rows.children, function (row, i) {
					var nm = val(row, 'input[name$="[name]"]');
					var ph = val(row, '.flx-ph__num') || val(row, 'input[type=tel]');
					if (!nm) return;
					items.push('<li><span>' + esc(nm) + '</span><small>' + esc(unitWord(o, (i === 0 ? n - (k - 1) : 1) * units)) + ' · ' + esc(how(ph, val(row, 'input[type=email]'))) + '</small></li>');
				});
			}
			if (!items.length && d && !d.proxy && donorName.value.trim()) {
				items.push('<li><span>' + esc(donorName.value.trim()) + '</span><small>' + esc(unitWord(o, n * units)) + '</small></li>');
			}
			list.innerHTML = items.join('');
			list.hidden = !items.length || !(d && d.cert) && !on;
		};
		priceBtn.addEventListener('click', function () {
			priceBox.hidden = !priceBox.hidden;
			if (!priceBox.hidden) unitIn.focus();
			else { unitIn.value = ''; q('[name=price_reason]').value = ''; sync(); }
		});
		addBtn.addEventListener('click', function () {
			var node = tpl.content.firstElementChild.cloneNode(true);
			rows.appendChild(node);
			renumber();
			sync();
			var f = node.querySelector('input[type=text]');
			if (f) f.focus();
		});
		rows.addEventListener('click', function (e) {
			var b = e.target.closest('[data-flxb-person-remove]');
			if (!b) return;
			var row = b.closest('[data-flxb-person]');
			if (row && rows.children.length > 1) { row.remove(); renumber(); sync(); }
		});
		form.addEventListener('input', sync);
		form.addEventListener('change', sync);
		sync();
	});

	/* Quantity stepper: − / + buttons around a number input. */
	document.querySelectorAll('.flxb-stepper').forEach(function (box) {
		var input = box.querySelector('input[type=number]');
		box.querySelectorAll('[data-flxb-step]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var min = input.min !== '' ? +input.min : -Infinity, max = input.max !== '' ? +input.max : Infinity;
				var v = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + (+btn.getAttribute('data-flxb-step'))));
				input.value = v;
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.dispatchEvent(new Event('change', { bubbles: true }));
			});
		});
	});

	/* Media picker: [data-flxb-media] with a hidden input, an <img>, pick and clear buttons (wp.media). */
	document.querySelectorAll('[data-flxb-media]').forEach(function (box) {
		var input = box.querySelector('input[type=hidden]');
		var img = box.querySelector('img');
		var pick = box.querySelector('[data-flxb-media-pick]');
		var clear = box.querySelector('[data-flxb-media-clear]');
		var frame;
		if (pick) pick.addEventListener('click', function () {
			if (!window.wp || !wp.media) return;
			if (!frame) {
				frame = wp.media({ title: pick.getAttribute('data-title') || '', library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
					input.value = a.id;
					img.src = url;
					img.hidden = false;
					if (clear) clear.hidden = false;
				});
			}
			frame.open();
		});
		if (clear) clear.addEventListener('click', function () {
			input.value = '0';
			img.removeAttribute('src');
			img.hidden = true;
			clear.hidden = true;
		});
	});

	/* 2.3.1: a tap anywhere on a donation row opens it (links and buttons inside keep their own target). */
	document.addEventListener('click', function (e) {
		var tr = e.target.closest && e.target.closest('tr[data-href]');
		if (!tr || e.defaultPrevented) return;
		var chk = e.target.closest('.flxb-c-check');
		if (chk) {
			if (e.target.tagName !== 'INPUT') { var cb = chk.querySelector('input'); if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true })); } }
			return;
		}
		// 2.19.0: only controls inside the row count; the list sits in the bulk-action <form>, which
		// used to swallow every row click.
		var inner = e.target.closest('a,button,input,select,textarea,label');
		if (inner && !tr.contains(inner)) inner = null;
		if (inner && !(window.matchMedia('(max-width: 782px)').matches && inner.closest('.flxb-col-main'))) return;
		if (window.getSelection && String(window.getSelection())) return;
		var href = safeUrl(tr.getAttribute('data-href'));
		if (!href) return;
		if (e.metaKey || e.ctrlKey) { window.open(href, '_blank'); return; }
		window.location.href = href;
	});

	/* 2.2.3: donation list, "Detailed filter" opens the other filters. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('.flxb-filters__toggle');
		if (!b) return;
		var p = document.getElementById(b.getAttribute('aria-controls'));
		if (!p) return;
		var open = p.hidden;
		p.hidden = !open;
		b.setAttribute('aria-expanded', open ? 'true' : 'false');
	});

	/* 2.5.0: donation list, selection and bulk actions. */
	(function () {
		var form = document.querySelector('[data-flxb-bulk]');
		if (!form) return;
		var bar = form.querySelector('.flxb-bulkbar'), n = form.querySelector('[data-flxb-bulk-n]'), all = form.querySelector('[data-flxb-check-all]');
		var boxes = function () { return Array.prototype.slice.call(form.querySelectorAll('input[name="ids[]"]')); };
		var refresh = function () {
			var on = boxes().filter(function (b) { return b.checked; });
			bar.hidden = !on.length;
			n.textContent = (window.flxbAdmin && flxbAdmin.i18n.selected || '%s selected').replace('%s', on.length);
			if (all) { all.checked = on.length && on.length === boxes().length; all.indeterminate = on.length > 0 && on.length < boxes().length; }
			boxes().forEach(function (b) { var tr = b.closest('tr'); if (tr) tr.classList.toggle('is-selected', b.checked); });
		};
		form.addEventListener('change', function (e) {
			if (e.target === all) boxes().forEach(function (b) { b.checked = all.checked; });
			refresh();
		});
		form.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('[data-flxb-bulk-clear]')) { boxes().forEach(function (b) { b.checked = false; }); refresh(); }
		});
		form.addEventListener('submit', function (e) {
			var count = boxes().filter(function (b) { return b.checked; }).length, act = form.querySelector('select[name="do"]').value;
			var t = (window.flxbAdmin && flxbAdmin.i18n) || {};
			var q = act === 'delete' ? (t.confirmDelete || 'Delete %s donations permanently? This cannot be undone.') : act === 'trash' ? (t.confirmTrash || 'Move %s donations to the trash?') : '';
			if (form.flxbOk) { form.flxbOk = false; return; }
			if (!count) { e.preventDefault(); return; }
			if (!q || !window.flxDialog) return;
			e.preventDefault();
			var sub = e.submitter;
			window.flxDialog.confirm({ message: q.replace('%s', count), danger: true }).then(function (yes) {
				if (!yes) return;
				form.flxbOk = true;
				if (form.requestSubmit) form.requestSubmit(sub && sub.form === form ? sub : undefined); else form.submit();
			});
		});
		refresh();
	})();

	/* 2.4.0: payment approval, receipt preview and reject windows. */
	function flxbDialog(id) {
		var d = document.getElementById(id);
		if (!d) return null;
		if (typeof d.showModal !== 'function') { d.setAttribute('open', ''); return d; }
		if (!d.open) d.showModal();
		return d;
	}
	function flxbCloseDialog(d) {
		if (!d) return;
		if (typeof d.close === 'function') d.close(); else d.removeAttribute('open');
	}
	document.addEventListener('click', function (e) {
		if (!e.target.closest) return;
		var pv = e.target.closest('[data-flxb-preview]');
		if (pv) {
			var d = flxbDialog('flxb-preview');
			if (!d) return;
			var url = safeUrl(pv.getAttribute('data-flxb-preview')), kind = pv.getAttribute('data-kind');
			if (!url) return;
			var body = d.querySelector('[data-flxb-preview-body]');
			d.querySelector('.flxb-modal__title').textContent = pv.getAttribute('data-name') || '';
			d.querySelector('[data-flxb-preview-open]').href = url;
			body.textContent = '';
			var el;
			if (kind === 'image') {
				el = document.createElement('img');
				el.alt = pv.getAttribute('data-name') || '';
			} else {
				el = document.createElement('iframe');
				el.title = pv.getAttribute('data-name') || '';
			}
			/* Dekontlar bu sitenin yüklemeleri: önizleme yalnız aynı kaynaktan, göreli yolla açılır. */
			var src = '';
			try {
				var pu = new URL(url, window.location.href);
				if (pu.origin === window.location.origin) src = pu.pathname + pu.search;
			} catch (err) { src = ''; }
			if (!src) return;
			el.src = src;
			body.appendChild(el);
			return;
		}
		var op = e.target.closest('[data-flxb-open]');
		if (op) {
			var cur = op.closest('dialog');
			if (cur) flxbCloseDialog(cur);
			var r = flxbDialog(op.getAttribute('data-flxb-open'));
			var ta = r && r.querySelector('textarea');
			if (ta) ta.focus();
			return;
		}
		if (e.target.closest('[data-flxb-approve]')) {
			var f = document.querySelector('.flxb-approve__actions form');
			if (f && (!f.onsubmit || f.onsubmit() !== false)) f.submit();
			return;
		}
		var cl = e.target.closest('[data-flxb-close]');
		if (cl) { flxbCloseDialog(cl.closest('dialog')); return; }
		if (e.target.tagName === 'DIALOG' && e.target.classList.contains('flxb-modal')) flxbCloseDialog(e.target);
	});
	document.addEventListener('close', function (e) {
		if (e.target.id !== 'flxb-preview') return;
		var b = e.target.querySelector('[data-flxb-preview-body]');
		if (b) b.textContent = '';
	}, true);
})();

/* 2.10.0: Kurban shares — bulk step shows the place picker for "give proxy"; select all. */
(function () {
	var step = document.querySelector('[data-flxb-kstep]');
	var place = document.querySelector('[data-flxb-kplace]');
	if (step && place) {
		var sync = function () { place.hidden = step.value !== 'assign'; };
		step.addEventListener('change', sync);
		sync();
	}
	var all = document.querySelector('[data-flxb-kall]');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('input[name="ids[]"][form="flxb-kbulk"]').forEach(function (c) { c.checked = all.checked; });
		});
	}
})();

/* 2.25.0: donation detail: sortable tables (click a column header), the "⋯" menu and its refund prompt. */
(function () {
	document.querySelectorAll('table[data-flxb-sortable]').forEach(function (table) {
		var body = table.tBodies[0];
		if (!body) return;
		var heads = table.querySelectorAll('thead th');
		heads.forEach(function (th, col) {
			var btn = th.querySelector('[data-flxb-sort]');
			if (!btn) return;
			btn.addEventListener('click', function () {
				var num = btn.getAttribute('data-flxb-sort') === 'num';
				var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
				heads.forEach(function (h) { h.setAttribute('aria-sort', 'none'); });
				th.setAttribute('aria-sort', dir);
				var val = function (tr) {
					var td = tr.children[col];
					var v = td ? (td.getAttribute('data-v') || td.textContent) : '';
					return num ? parseFloat(v) || 0 : String(v).trim();
				};
				var rows = Array.prototype.slice.call(body.rows);
				rows.sort(function (a, b) {
					var x = val(a), y = val(b);
					var r = num ? x - y : String(x).localeCompare(String(y), document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
					return dir === 'ascending' ? r : -r;
				});
				rows.forEach(function (tr) { body.appendChild(tr); });
			});
		});
	});

	var menus = document.querySelectorAll('[data-flxb-more]');
	if (!menus.length) return;
	document.addEventListener('click', function (e) {
		menus.forEach(function (m) { if (m.open && !m.contains(e.target)) m.open = false; });
	});
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		menus.forEach(function (m) {
			if (m.open) { m.open = false; var s = m.querySelector('summary'); if (s) s.focus(); }
		});
	});
	document.querySelectorAll('[data-flxb-refund]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var form = document.getElementById(btn.getAttribute('data-flxb-refund'));
			var menu = btn.closest('[data-flxb-more]');
			if (menu) menu.open = false;
			if (!form || !window.flxDialog) return;
			var max = parseFloat(btn.getAttribute('data-max')) || 0;
			window.flxDialog.prompt({
				title: btn.getAttribute('data-title'),
				message: btn.getAttribute('data-msg'),
				label: btn.getAttribute('data-label'),
				value: btn.getAttribute('data-value')
			}).then(function (v) {
				if (v === null || v === undefined) return;
				var s = String(v).trim().replace(/\s/g, '');
				/* "1.234,50" and "1234.50" both mean the same amount */
				if (s.indexOf(',') > -1) s = s.replace(/\./g, '').replace(',', '.');
				var n = parseFloat(s);
				if (!(n > 0) || n > max + 0.004) {
					window.flxDialog.alert({ message: btn.getAttribute('data-err') });
					return;
				}
				form.querySelector('input[name="amount"]').value = n.toFixed(2);
				if (form.requestSubmit) form.requestSubmit(); else form.submit();
			});
		});
	});
})();
