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

/* 2.35.0: movable boxes (FLXB_Boxes). Up/down walk the page order (main column, then side), so a box
   can cross columns on a phone too; on a wide screen the handle drags it anywhere. The layout is
   saved per user; the page prints it in that order next time. */
(function () {
	var root = document.querySelector('[data-flxb-boxes]');
	if (!root) return;
	var cols = Array.prototype.slice.call(root.querySelectorAll('.flxb-boxes__col'));
	var boxes = function () { return Array.prototype.slice.call(root.querySelectorAll('.flxb-box')); };
	var timer = null;
	var save = function () {
		clearTimeout(timer);
		timer = setTimeout(function () {
			if (!window.flxbAdmin || !window.fetch) return;
			var body = new URLSearchParams();
			body.append('action', 'flxb_boxes');
			body.append('nonce', root.getAttribute('data-nonce'));
			body.append('screen', root.getAttribute('data-flxb-boxes'));
			cols.forEach(function (c) {
				body.append(c.getAttribute('data-col'), Array.prototype.map.call(c.querySelectorAll(':scope > .flxb-box'), function (b) { return b.getAttribute('data-box'); }).join(','));
			});
			body.append('closed', boxes().filter(function (b) { return b.classList.contains('is-closed'); }).map(function (b) { return b.getAttribute('data-box'); }).join(','));
			fetch(window.flxbAdmin.ajax, { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
		}, 250);
	};
	var refresh = function () {
		var all = boxes();
		all.forEach(function (b, i) {
			var up = b.querySelector('[data-box-move="up"]');
			var down = b.querySelector('[data-box-move="down"]');
			if (up) up.disabled = i === 0;
			if (down) down.disabled = i === all.length - 1;
		});
	};
	var flash = function (b) {
		b.classList.remove('is-moved');
		void b.offsetWidth;
		b.classList.add('is-moved');
	};
	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-box-move],[data-box-fold]');
		if (!btn || !root.contains(btn)) return;
		var box = btn.closest('.flxb-box');
		if (btn.hasAttribute('data-box-fold')) {
			var closed = box.classList.toggle('is-closed');
			btn.setAttribute('aria-expanded', closed ? 'false' : 'true');
			save();
			return;
		}
		var all = boxes();
		var i = all.indexOf(box);
		var up = btn.getAttribute('data-box-move') === 'up';
		var col = box.parentNode;
		if (up) {
			var prev = box.previousElementSibling;
			if (prev) col.insertBefore(box, prev);
			else if (i > 0) all[i - 1].parentNode.appendChild(box); // end of the column above
		} else {
			var next = box.nextElementSibling;
			if (next) col.insertBefore(next, box);
			else if (i < all.length - 1) { var nc = all[i + 1].parentNode; nc.insertBefore(box, nc.firstChild); }
		}
		refresh();
		flash(box);
		btn.focus();
		if (box.getBoundingClientRect().top < 60) box.scrollIntoView({ block: 'nearest' });
		save();
	});

	/* Drag by the handle (wide screens; the handle is hidden elsewhere). */
	var dragged = null;
	root.addEventListener('pointerdown', function (e) {
		var grip = e.target.closest('[data-box-grip]');
		if (grip) grip.closest('.flxb-box').setAttribute('draggable', 'true');
	});
	root.addEventListener('pointerup', function () {
		boxes().forEach(function (b) { if (b !== dragged) b.removeAttribute('draggable'); });
	});
	root.addEventListener('dragstart', function (e) {
		var b = e.target.closest && e.target.closest('.flxb-box[draggable]');
		if (!b) return;
		dragged = b;
		b.classList.add('is-dragging');
		root.classList.add('is-dragging');
		if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', b.getAttribute('data-box')); } catch (x) {} }
	});
	root.addEventListener('dragover', function (e) {
		if (!dragged) return;
		var col = e.target.closest('.flxb-boxes__col');
		if (!col) return;
		e.preventDefault();
		var after = null;
		Array.prototype.some.call(col.querySelectorAll(':scope > .flxb-box:not(.is-dragging)'), function (b) {
			var r = b.getBoundingClientRect();
			if (e.clientY < r.top + r.height / 2) { after = b; return true; }
			return false;
		});
		if (after) { if (after.previousElementSibling !== dragged) col.insertBefore(dragged, after); }
		else if (col.lastElementChild !== dragged) col.appendChild(dragged);
	});
	root.addEventListener('drop', function (e) { if (dragged) e.preventDefault(); });
	root.addEventListener('dragend', function () {
		if (!dragged) return;
		dragged.classList.remove('is-dragging');
		dragged.removeAttribute('draggable');
		root.classList.remove('is-dragging');
		flash(dragged);
		dragged = null;
		refresh();
		save();
	});
	refresh();

	/* The WooCommerce order's boxes: one "Save" posts the fields of all of them, like the order screen. */
	var xform = document.getElementById('flxb-xform');
	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-flxb-xsave]');
		if (!btn || !xform) return;
		xform.querySelectorAll('[data-copied]').forEach(function (n) { n.remove(); });
		var add = function (name, value) {
			var inp = document.createElement('input');
			inp.type = 'hidden';
			inp.name = name;
			inp.value = value;
			inp.setAttribute('data-copied', '');
			xform.appendChild(inp);
		};
		root.querySelectorAll('[data-flxb-wcbox]').forEach(function (box) {
			box.querySelectorAll('input[name],select[name],textarea[name]').forEach(function (el) {
				if (el.disabled || el.type === 'file' || el.type === 'button' || el.type === 'submit') return;
				if (/^(action|_flxb_nonce|flxb_order|flxb_back|post_ID|woocommerce_meta_nonce)$/.test(el.name)) return;
				if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
				if (el.tagName === 'SELECT' && el.multiple) {
					Array.prototype.forEach.call(el.options, function (o) { if (o.selected) add(el.name, o.value); });
					return;
				}
				add(el.name, el.value);
			});
		});
		btn.disabled = true;
		xform.submit();
	});
})();
