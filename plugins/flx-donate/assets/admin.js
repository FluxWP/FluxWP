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
		var inner = e.target.closest('a,button,input,select,textarea,label,form');
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
			if (!count || (q && !window.confirm(q.replace('%s', count)))) e.preventDefault();
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
			el.src = url;
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

	var app = document.getElementById('flxb-app');
	if (!app) return;
	/* Chart colours follow the brand colour from the settings (CSS custom property). */
	var C1 = (getComputedStyle(app).getPropertyValue('--flxb-primary') || '').trim() || '#1F2937';
	if (!/^#[0-9a-f]{3,8}$|^rgb/i.test(C1)) C1 = '#1F2937';
	var C2 = '#2F6FA3';
	(function () { /* a blue brand colour would hide the recurring series: switch it to amber */
		var m = C1.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i);
		if (!m) return;
		var r = parseInt(m[1], 16) / 255, g = parseInt(m[2], 16) / 255, b = parseInt(m[3], 16) / 255;
		var mx = Math.max(r, g, b), mn = Math.min(r, g, b), d = mx - mn, h = 0;
		if (d) h = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
		h = (h * 60 + 360) % 360;
		if (d > 0.08 && h >= 180 && h <= 260) C2 = '#E0A526';
	})();

	var state = { range: '30', from: '', to: '' };
	var data = null;
	var locale = cfg.locale || 'tr-TR';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function num(n, d) {
		try { return new Intl.NumberFormat(locale, { maximumFractionDigits: d || 0, minimumFractionDigits: d || 0 }).format(n); } catch (e) { return String(n); }
	}
	function money(n) {
		var d = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2;
		var sym = data ? data.symbol : '₺';
		return data && data.currency !== 'TRY' && sym.length === 1 ? sym + num(n, d) : num(n, d) + ' ' + sym;
	}
	function short(n) {
		try { return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(n); } catch (e) { return num(n, 0); }
	}
	function fmt(s) {
		var a = Array.prototype.slice.call(arguments, 1), i = 0;
		return String(s).replace(/%(\d\$)?s/g, function () { return a[i++]; });
	}

	function loader() {
		return '<div class="flxf-loader" role="status"><span class="flxf-loader__ring" aria-hidden="true"></span><span class="flxf-loader__text">' + esc(t.loading) + '</span></div>';
	}

	function load() {
		app.classList.add('is-busy');
		if (!data) app.innerHTML = loader();
		var fd = new FormData();
		fd.append('action', 'flxb_stats');
		fd.append('nonce', cfg.nonce);
		fd.append('range', state.range);
		fd.append('from', state.from);
		fd.append('to', state.to);
		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				app.classList.remove('is-busy');
				if (!r || !r.success) throw new Error('bad');
				data = r.data;
				render();
			})
			.catch(function () {
				app.classList.remove('is-busy');
				app.innerHTML = '<div class="flxb-card flxb-empty"><p>' + esc(t.error) + '</p><button type="button" class="flxb-btn" id="flxb-retry">' + esc(t.retry) + '</button></div>';
				var b = document.getElementById('flxb-retry');
				if (b) b.addEventListener('click', load);
			});
	}

	function delta(now, prev) {
		if (!prev) return '<span class="flxb-delta"><span class="flxb-muted">' + esc(t.noPrev) + '</span></span>';
		var p = Math.round(((now - prev) / prev) * 100);
		var cls = p > 0 ? 'is-up' : (p < 0 ? 'is-down' : '');
		return '<span class="flxb-delta ' + cls + '"><span>' + (p > 0 ? '▲ +' : (p < 0 ? '▼ ' : '')) + num(p) + '%</span> <span class="flxb-muted">' + esc(t.vsPrev) + '</span></span>';
	}

	function kpi(label, value, sub, accent) {
		return '<div class="flxb-kpi"' + (accent ? ' style="--accent:' + accent + '"' : '') + '><div class="flxb-kpi__label">' + esc(label) + '</div><div class="flxb-kpi__value" title="' + esc(value) + '">' + esc(value) + '</div><div class="flxb-kpi__sub">' + sub + '</div></div>';
	}

	function card(title, hint, body, extra) {
		return '<section class="flxb-card' + (extra || '') + '"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' + esc(title) + '</h2>' + (hint ? '<p class="flxb-card__hint">' + esc(hint) + '</p>' : '') + '</div></div>' + body + '</section>';
	}

	function render() {
		var d = data;
		updateCsv();
		if (!d.has_any) {
			app.innerHTML = '<div class="flxb-card flxb-emptystate"><svg class="flxb-ico" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg><h2>' + esc(t.emptyTitle) + '</h2><p>' + esc(t.emptyText) + '</p><a class="flxb-btn flxb-btn--primary" href="' + esc(cfg.settingsUrl + '&tab=import') + '">' + esc(t.emptyBtn) + '</a></div>';
			return;
		}
		var n = d.now, p = d.prev, s = d.subs;
		var h = '<div class="flxb-body">';
		h += '<div class="flxb-kpis">';
		h += kpi(t.total, money(n.total), delta(n.total, p.total) + '<br>' + esc(num(n.gifts)) + ' ' + esc(n.gifts === 1 ? t.gift1 : t.gifts));
		h += kpi(t.donors, num(n.donors), esc(fmt(t.allTime, num(d.all_donors))), '#2F6FA3');
		h += kpi(t.newDonors, num(n.new_donors), esc(t.firstGift) + '<br>' + delta(n.new_donors, p.new_donors), '#5BA88A');
		h += kpi(t.average, money(n.average), esc(t.perGift) + '<br>' + delta(n.average, p.average), '#E0A526');
		h += kpi(t.mrr, money(s.mrr), esc(t.mrrHint), '#234E73');
		h += kpi(t.activeSubs, num(s.active), s.past_due ? esc(fmt(t.pastDue, num(s.past_due))) : '&nbsp;', '#7A8C96');
		h += '</div>';
		h += '<div class="flxb-grid flxb-grid--2-1">';
		h += card(t.daily, t.dailyHint, '<div class="flxb-legend" style="margin-bottom:10px"><span><i class="flxb-sw" style="background:' + C1 + '"></i>' + esc(t.oneTime) + '</span><span><i class="flxb-sw" style="background:' + C2 + '"></i>' + esc(t.recurring) + '</span></div><div class="flxb-chart" id="flxb-daily"></div>');
		h += card(t.split, d.fmt.from + ' – ' + d.fmt.to, splitChart(d.split));
		h += '</div>';
		h += '<div class="flxb-grid flxb-grid--1-1" style="margin-top:16px">';
		h += card(t.byType, '', bars(d.types, function (r) { return !cfg.listUrl ? '' : cfg.listUrl + '&dtype=' + r.id + '&from=' + d.range.from + '&to=' + d.range.to; }));
		h += card(t.byForm, '', bars(d.forms, function (r) { return r.id && cfg.listUrl ? cfg.listUrl + '&form=' + r.id + '&from=' + d.range.from + '&to=' + d.range.to : ''; }));
		h += '</div>';
		h += '<div style="margin-top:16px">' + card(t.topDonors, '', topTable(d.top)) + '</div>';
		h += '</div>';
		app.innerHTML = h;
		daily(document.getElementById('flxb-daily'), d.series);
	}

	function bars(rows, link) {
		if (!rows.length) return '<p class="flxb-empty">' + esc(t.nothing) + '</p>';
		var max = rows.reduce(function (m, r) { return Math.max(m, r.amount); }, 0) || 1;
		var h = '<ul class="flxb-bars">';
		rows.forEach(function (r) {
			var url = link(r);
			var label = url ? '<a href="' + esc(url) + '">' + esc(r.label) + '</a>' : esc(r.label);
			h += '<li><span class="flxb-bars__label" title="' + esc(r.label) + '">' + label + '</span><span class="flxb-bars__val">' + esc(money(r.amount)) + '<small>' + esc(num(r.gifts)) + ' ' + esc(r.gifts === 1 ? t.gift1 : t.gifts) + '</small></span><span class="flxb-bars__track"><span style="width:' + Math.max(2, Math.round(100 * r.amount / max)) + '%"></span></span></li>';
		});
		return h + '</ul>';
	}

	function topTable(rows) {
		if (!rows.length) return '<p class="flxb-empty">' + esc(t.nothing) + '</p>';
		var h = '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr><th scope="col">#</th><th scope="col">' + esc(t.donor) + '</th><th scope="col" class="num">' + esc(t.giftsCol) + '</th><th scope="col" class="num">' + esc(t.amount) + '</th></tr></thead><tbody>';
		rows.forEach(function (r, i) {
			h += '<tr><td class="flxb-muted flxb-col-rank" data-label="#"><span>' + (i + 1) + '</span></td><td class="flxb-col-main">' + (r.url ? '<a href="' + esc(r.url) + '">' + esc(r.name) + '</a>' : esc(r.name)) + (r.contact && r.contact !== r.name ? '<span class="flxb-sub2">' + esc(r.contact) + '</span>' : '') + '</td><td class="num" data-label="' + esc(t.giftsCol) + '"><span>' + esc(num(r.gifts)) + '</span></td><td class="num" data-label="' + esc(t.amount) + '"><span>' + esc(money(r.amount)) + '</span></td></tr>';
		});
		return h + '</tbody></table></div>';
	}

	function splitChart(sp) {
		var a = sp.one_time[0], b = sp.recurring[0], total = a + b;
		var R = 52, C = 2 * Math.PI * R;
		var svg = '<svg viewBox="0 0 140 140" role="img" aria-label="' + esc(t.split) + '"><circle cx="70" cy="70" r="' + R + '" fill="none" stroke="#EEF3F0" stroke-width="18"/>';
		if (total > 0) {
			var la = C * a / total;
			svg += '<circle cx="70" cy="70" r="' + R + '" fill="none" stroke="' + C1 + '" stroke-width="18" stroke-dasharray="' + la + ' ' + (C - la) + '" transform="rotate(-90 70 70)"/>';
			if (b > 0) svg += '<circle cx="70" cy="70" r="' + R + '" fill="none" stroke="' + C2 + '" stroke-width="18" stroke-dasharray="' + (C - la) + ' ' + la + '" stroke-dashoffset="' + (-la) + '" transform="rotate(-90 70 70)"/>';
		}
		svg += '<text x="70" y="68" text-anchor="middle" class="flxb-donut__center">' + esc(total ? num(Math.round(100 * b / total)) + '%' : '–') + '</text><text x="70" y="86" text-anchor="middle" class="flxb-donut__centersub">' + esc(t.recurring) + '</text></svg>';
		var li = function (color, label, v) {
			return '<li><i class="flxb-sw" style="background:' + color + '"></i><span class="flxb-paylegend__label">' + esc(label) + '</span><span class="flxb-paylegend__n">' + esc(num(v[1])) + ' ' + esc(v[1] === 1 ? t.gift1 : t.gifts) + '</span><span class="flxb-paylegend__amt">' + esc(money(v[0])) + (total ? ' · ' + num(Math.round(100 * v[0] / total)) + '%' : '') + '</span></li>';
		};
		return '<div class="flxb-donut">' + svg + '<ul class="flxb-paylegend">' + li(C1, t.oneTime, sp.one_time) + li(C2, t.recurring, sp.recurring) + '</ul></div>';
	}

	/* Stacked daily columns with a tooltip. */
	var tip = null;
	function showTip(html, x, y) {
		if (!tip) {
			tip = document.createElement('div');
			tip.className = 'flxb-tip';
			tip.setAttribute('role', 'tooltip');
			document.body.appendChild(tip);
		}
		tip.innerHTML = html;
		tip.hidden = false;
		var w = tip.offsetWidth, hgt = tip.offsetHeight;
		var left = Math.min(window.innerWidth - w - 8, Math.max(8, x + 14));
		var top = y - hgt - 12 < 8 ? y + 16 : y - hgt - 12;
		tip.style.left = left + 'px';
		tip.style.top = top + 'px';
	}
	function hideTip() { if (tip) tip.hidden = true; }

	function daily(el, series) {
		if (!el) return;
		var W = Math.max(320, el.clientWidth || 640), H = W < 500 ? 220 : 260;
		var pl = 44, pr = 8, pt = 10, pb = 26;
		var iw = W - pl - pr, ih = H - pt - pb;
		var max = series.reduce(function (m, r) { return Math.max(m, r.one_time + r.recurring); }, 0);
		var step = niceStep(max / 4 || 1);
		var top = Math.max(step * 4, step);
		var n = series.length, bw = iw / n, gap = Math.min(6, bw * 0.25);
		var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + esc(t.daily) + '"><g class="flxb-gl">';
		for (var i = 0; i <= 4; i++) {
			var v = step * i, y = pt + ih - (v / top) * ih;
			s += '<line x1="' + pl + '" x2="' + (W - pr) + '" y1="' + y + '" y2="' + y + '"' + (i === 0 ? ' class="is-base"' : '') + '/><text x="' + (pl - 6) + '" y="' + (y + 4) + '" text-anchor="end">' + esc(short(v)) + '</text>';
		}
		s += '</g><g>';
		var every = Math.ceil(n / (W < 500 ? 5 : 10));
		series.forEach(function (r, i) {
			var x = pl + i * bw + gap / 2, w = Math.max(1, bw - gap);
			var h1 = (r.one_time / top) * ih, h2 = (r.recurring / top) * ih;
			var y1 = pt + ih - h1;
			if (h1 > 0) s += '<rect x="' + x + '" y="' + y1 + '" width="' + w + '" height="' + h1 + '" rx="' + Math.min(3, w / 3) + '" fill="' + C1 + '"/>';
			if (h2 > 0) s += '<rect x="' + x + '" y="' + (y1 - h2) + '" width="' + w + '" height="' + h2 + '" rx="' + Math.min(3, w / 3) + '" fill="' + C2 + '"/>';
			if (i % every === 0 || i === n - 1) s += '<text class="flxb-axis-t" x="' + (x + w / 2) + '" y="' + (H - 8) + '" text-anchor="middle" fill="#6B7C86" font-size="11">' + esc(r.label) + '</text>';
			s += '<rect class="flxb-colhit" data-i="' + i + '" x="' + (pl + i * bw) + '" y="' + pt + '" width="' + bw + '" height="' + ih + '"/>';
		});
		s += '</g></svg>';
		el.innerHTML = s;
		el.querySelectorAll('.flxb-colhit').forEach(function (r) {
			var on = function (e) {
				var d = series[+r.getAttribute('data-i')];
				var pt2 = e.touches ? e.touches[0] : e;
				showTip('<strong>' + esc(d.label) + '</strong><div class="flxb-tip__row"><i class="flxb-sw" style="background:' + C1 + '"></i>' + esc(t.oneTime) + '<b>' + esc(money(d.one_time)) + '</b></div><div class="flxb-tip__row"><i class="flxb-sw" style="background:' + C2 + '"></i>' + esc(t.recurring) + '<b>' + esc(money(d.recurring)) + '</b></div><div class="flxb-tip__row">' + esc(t.total2) + '<b>' + esc(money(d.one_time + d.recurring)) + ' · ' + esc(num(d.gifts)) + '</b></div>', pt2.clientX, pt2.clientY);
			};
			r.addEventListener('mousemove', on);
			r.addEventListener('touchstart', on, { passive: true });
			r.addEventListener('mouseleave', hideTip);
		});
	}
	function niceStep(x) {
		var p = Math.pow(10, Math.floor(Math.log10(x))), f = x / p;
		return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
	}

	function updateCsv() {
		var a = document.getElementById('flxb-csv');
		if (a && data) a.href = safeUrl(a.getAttribute('data-base')) + '&from=' + encodeURIComponent(data.range.from) + '&to=' + encodeURIComponent(data.range.to);
	}

	/* Range controls */
	var seg = document.getElementById('flxb-range');
	var custom = document.getElementById('flxb-custom');
	if (seg) seg.addEventListener('click', function (e) {
		var b = e.target.closest('button[data-range]');
		if (!b) return;
		seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
		var r = b.getAttribute('data-range');
		custom.classList.toggle('is-open', r === 'custom');
		if (r !== 'custom') { state.range = r; load(); }
	});
	var apply = document.getElementById('flxb-apply');
	if (apply) apply.addEventListener('click', function () {
		state.range = 'custom';
		state.from = document.getElementById('flxb-from').value;
		state.to = document.getElementById('flxb-to').value;
		load();
	});
	var rt;
	window.addEventListener('resize', function () {
		clearTimeout(rt);
		rt = setTimeout(function () { if (data && data.has_any) daily(document.getElementById('flxb-daily'), data.series); }, 150);
	});
	load();
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
