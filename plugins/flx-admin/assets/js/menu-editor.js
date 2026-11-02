/* Flux Admin · rol bazlı menü düzenleyici. Kayıt, formdaki gizli alana JSON olarak yazılır. */
/* global FLXA_ME */
(function () {
	'use strict';
	var B = window.FLXA_ME;
	var root = document.getElementById('flxa-menu-editor');
	var out = document.getElementById('flxa-menus-json');
	if (!B || !root || !out) return;
	var T = B.i18n;
	var d = document;
	var cat = {};
	B.catalog.forEach(function (it) { cat[it.slug] = it; });

	function clone(o) { return JSON.parse(JSON.stringify(o)); }
	function el(tag, attrs, kids) {
		var n = d.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			var v = attrs[k];
			if (v === null || v === undefined || v === false) return;
			if (k === 'html') n.innerHTML = v;
			else if (k === 'text') n.textContent = v;
			else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), v);
			else n.setAttribute(k, v === true ? '' : v);
		});
		(kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? d.createTextNode(c) : c); });
		return n;
	}

	/* Düzeni katalogla uzlaştır: olmayanları at, yenileri "Diğer" altına ekle. */
	function normalize(entries) {
		var seen = {};
		var list = [];
		(entries || []).forEach(function (e) {
			if (e.t === 'g') { list.push({ t: 'g', label: e.label || '', c: !!e.c }); return; }
			if (!cat[e.slug] || seen[e.slug]) return;
			seen[e.slug] = 1;
			list.push({ t: 'i', slug: e.slug, label: e.label || '', hidden: !!e.hidden, blocked: !!e.blocked, sub_hidden: (e.sub_hidden || []).slice(), pf_off: (e.pf_off || []).slice() });
		});
		var fresh = B.catalog.filter(function (it) { return !seen[it.slug]; });
		if (fresh.length) {
			list.push({ t: 'g', label: B.defaults.filter(function (e) { return e.t === 'g' && e.id === 'other'; }).map(function (e) { return e.label; })[0] || 'Diğer', c: true });
			fresh.forEach(function (it) { list.push({ t: 'i', slug: it.slug, label: '', hidden: false, blocked: false, sub_hidden: [], pf_off: [] }); });
		}
		return list;
	}

	var state = { role: Object.keys(B.roles).filter(function (r) { return r !== '_default'; })[0] || '_default', cfg: {}, custom: {}, open: {}, dirty: false };
	Object.keys(B.roles).forEach(function (r) {
		var s = B.saved[r];
		state.custom[r] = !!(s && s.entries && s.entries.length);
		state.cfg[r] = {
			entries: normalize(state.custom[r] ? s.entries : B.defaults),
			bottom: (s && s.bottom && s.bottom.length ? s.bottom : B.defBottom).filter(function (x) { return cat[x]; }).slice(0, 4)
		};
	});

	function cur() { return state.cfg[state.role]; }
	function touch() {
		state.custom[state.role] = true;
		state.dirty = true;
		serialize();
		render();
	}
	function serialize() {
		var data = {};
		Object.keys(state.custom).forEach(function (r) {
			if (!state.custom[r]) return;
			data[r] = {
				entries: state.cfg[r].entries.map(function (e) {
					return e.t === 'g' ? { t: 'g', label: e.label, c: e.c ? 1 : 0 } : { t: 'i', slug: e.slug, label: e.label, hidden: e.hidden ? 1 : 0, blocked: e.blocked ? 1 : 0, sub_hidden: e.sub_hidden, pf_off: e.pf_off };
				}),
				bottom: state.cfg[r].bottom.filter(Boolean)
			};
		});
		out.value = JSON.stringify(data);
	}
	var pfs = Object.keys(B.platforms || {});
	function can(slug) { var a = B.access[state.role]; return !a || a[slug] !== false; }
	function canBlock(slug) { return state.role !== 'administrator' && (B.unblockable || []).indexOf(slug) === -1; }

	/* ───────── Sürükle-bırak (fare + dokunmatik, tutamaçtan) ───────── */
	var drag = null;
	function rowIndexAt(x, y) {
		var n = d.elementFromPoint(x, y);
		var row = n && n.closest && n.closest('.flxa-me-row');
		return row && row.parentNode === listEl ? +row.getAttribute('data-i') : -1;
	}
	function onDown(e, i) {
		e.preventDefault();
		drag = { from: i, over: i };
		var rows = listEl.children;
		if (rows[i]) rows[i].classList.add('is-drag');
		d.addEventListener('pointermove', onMove);
		d.addEventListener('pointerup', onUp);
		d.addEventListener('pointercancel', onUp);
	}
	function onMove(e) {
		if (!drag) return;
		var i = rowIndexAt(e.clientX, e.clientY);
		if (i < 0 || i === drag.over) return;
		Array.prototype.forEach.call(listEl.children, function (r) { r.classList.remove('is-over'); });
		drag.over = i;
		if (listEl.children[i]) listEl.children[i].classList.add('is-over');
	}
	function onUp() {
		d.removeEventListener('pointermove', onMove);
		d.removeEventListener('pointerup', onUp);
		d.removeEventListener('pointercancel', onUp);
		if (!drag) return;
		var from = drag.from, to = drag.over;
		drag = null;
		if (from !== to) move(from, to);
		else render();
	}
	function move(from, to) {
		var list = cur().entries;
		var it = list.splice(from, 1)[0];
		list.splice(to, 0, it);
		touch();
	}

	var listEl;
	function render() {
		root.innerHTML = '';
		var c = cur();

		/* Roller */
		var roles = el('div', { 'class': 'flxa-me-roles', role: 'tablist' });
		Object.keys(B.roles).forEach(function (r) {
			var n = B.users[r];
			roles.appendChild(el('button', {
				type: 'button', 'class': 'flxa-me-role', role: 'tab', 'aria-selected': r === state.role ? 'true' : 'false',
				onclick: function () { state.role = r; render(); }
			}, [state.custom[r] ? el('i', { 'aria-hidden': 'true' }) : null, B.roles[r], n ? el('small', { text: ' · ' + n + ' ' + T.users }) : null]));
		});
		root.appendChild(roles);

		/* Liste */
		var main = el('section', { 'class': 'flxa-me-main' });
		var copy = el('select', { 'aria-label': T.copyFrom, onchange: function () {
			var from = this.value;
			if (!from) return;
			state.cfg[state.role] = clone(state.cfg[from]);
			touch();
		} }, [el('option', { value: '', text: T.copyFrom })].concat(Object.keys(B.roles).filter(function (r) { return r !== state.role; }).map(function (r) { return el('option', { value: r, text: B.roles[r] }); })));
		main.appendChild(el('div', { 'class': 'flxa-me-bar' }, [
			el('span', { 'class': 'flxa-me-state', text: state.custom[state.role] ? T.custom : T.inherit }),
			state.dirty ? el('span', { 'class': 'flxa-me-dirty', text: T.unsaved }) : null,
			el('button', { type: 'button', 'class': 'flxa-me-btn', text: T.addGroup, onclick: function () { c.entries.unshift({ t: 'g', label: T.newGroup }); touch(); } }),
			copy,
			state.custom[state.role] ? el('button', { type: 'button', 'class': 'flxa-me-btn', text: T.reset, onclick: function () {
				state.custom[state.role] = false;
				state.cfg[state.role] = { entries: normalize(B.defaults), bottom: B.defBottom.filter(function (x) { return cat[x]; }) };
				state.dirty = true;
				serialize();
				render();
			} }) : null
		]));

		listEl = el('ul', { 'class': 'flxa-me-list' });
		c.entries.forEach(function (e, i) {
			var handle = el('span', { 'class': 'flxa-me-handle', html: B.ui.drag, title: '', 'aria-hidden': 'true' });
			handle.addEventListener('pointerdown', function (ev) { onDown(ev, i); });
			var moves = el('span', { 'class': 'flxa-me-move' }, [
				el('button', { type: 'button', 'class': 'flxa-me-btn', 'aria-label': T.moveUp, text: B.ui.up, disabled: i === 0, onclick: function () { move(i, i - 1); } }),
				el('button', { type: 'button', 'class': 'flxa-me-btn', 'aria-label': T.moveDown, text: B.ui.down, disabled: i === c.entries.length - 1, onclick: function () { move(i, i + 1); } })
			]);
			if (e.t === 'g') {
				listEl.appendChild(el('li', { 'class': 'flxa-me-row is-group', 'data-i': i }, [
					handle,
					el('span', { 'class': 'flxa-me-name' }, [el('input', { type: 'text', value: e.label, 'aria-label': T.groupName, maxlength: '40', onchange: function () { e.label = this.value; touch(); } })]),
					el('label', { 'class': 'flxa-me-chk', title: T.collapsed }, [el('input', { type: 'checkbox', checked: !!e.c, onchange: function () { e.c = this.checked; touch(); } }), ' ' + T.collapsed]),
					moves,
					el('button', { type: 'button', 'class': 'flxa-me-btn', 'aria-label': T.removeGroup, title: T.removeGroup, html: B.ui.x, onclick: function () { c.entries.splice(i, 1); touch(); } })
				]));
				return;
			}
			var it = cat[e.slug];
			var subs = (it.sub || []).filter(function (s) { return s.slug !== it.slug || it.sub.length > 1; });
			var access = can(e.slug);
			var row = el('li', { 'class': 'flxa-me-row is-item' + (e.hidden ? ' is-hidden' : '') + (e.blocked ? ' is-blocked' : '') + (access ? '' : ' is-noaccess'), 'data-i': i }, [
				handle,
				el('span', { 'class': 'flxa-me-ico', html: B.icons[e.slug] || '' }),
				el('span', { 'class': 'flxa-me-name' }, [el('input', { type: 'text', value: e.label, placeholder: it.label, 'aria-label': T.rename, title: T.rename, maxlength: '40', onchange: function () { e.label = this.value.trim(); touch(); } })]),
				e.label ? el('span', { 'class': 'flxa-me-src', text: it.label }) : null,
				access ? null : el('span', { 'class': 'flxa-me-tag', text: T.noAccess }),
				e.blocked ? el('span', { 'class': 'flxa-me-tag is-block', text: T.block, title: T.blocked }) : null,
				moves,
				subs.length > 1 ? el('button', { type: 'button', 'class': 'flxa-me-btn' + (state.open[e.slug] ? ' is-on' : ''), 'aria-expanded': state.open[e.slug] ? 'true' : 'false', text: T.subs + ' (' + subs.length + ')', onclick: function () { state.open[e.slug] = !state.open[e.slug]; render(); } }) : null,
				el('button', { type: 'button', 'class': 'flxa-me-btn' + (e.hidden ? '' : ' is-on'), 'aria-pressed': e.hidden ? 'false' : 'true', html: (e.hidden ? B.ui.eyeOff : B.ui.eye) + '<span>' + (e.hidden ? T.hide : T.show) + '</span>', onclick: function () { e.hidden = !e.hidden; touch(); } }),
				pfs.length && !e.hidden && !e.blocked ? el('span', { 'class': 'flxa-me-pf', role: 'group', 'aria-label': T.platforms, title: T.platforms }, pfs.map(function (k) {
					var on = e.pf_off.indexOf(k) === -1;
					return el('button', { type: 'button', 'class': 'flxa-me-btn' + (on ? ' is-on' : ''), 'aria-pressed': on ? 'true' : 'false', title: on ? B.platforms[k] : T.platformOff.replace('%s', B.platforms[k]), text: B.platforms[k], onclick: function () {
						if (on) e.pf_off.push(k); else e.pf_off = e.pf_off.filter(function (x) { return x !== k; });
						touch();
					} });
				})) : null,
				access && canBlock(e.slug) ? el('button', { type: 'button', 'class': 'flxa-me-btn flxa-me-lock' + (e.blocked ? ' is-block' : ''), 'aria-pressed': e.blocked ? 'true' : 'false', title: e.blocked ? T.blocked : T.block, 'aria-label': T.block, html: B.ui.lock, onclick: function () { e.blocked = !e.blocked; touch(); } }) : null
			]);
			listEl.appendChild(row);
			if (state.open[e.slug] && subs.length > 1) {
				var box = el('li', { 'class': 'flxa-me-subs' });
				subs.forEach(function (s) {
					var on = e.sub_hidden.indexOf(s.slug) === -1;
					box.appendChild(el('label', {}, [el('input', { type: 'checkbox', checked: on, onchange: function () {
						if (this.checked) e.sub_hidden = e.sub_hidden.filter(function (x) { return x !== s.slug; });
						else e.sub_hidden.push(s.slug);
						touch();
					} }), ' ' + s.label]));
				});
				listEl.appendChild(box);
			}
		});
		main.appendChild(listEl);
		main.appendChild(el('p', { 'class': 'flxa-me-note', text: T.other }));
		if (state.role === 'administrator') main.appendChild(el('p', { 'class': 'flxa-me-note', text: T.adminNote }));
		main.appendChild(el('p', { 'class': 'flxa-me-note' }, [el('a', { href: B.capsUrl, text: T.capsLink })]));
		root.appendChild(main);

		/* Önizleme + alt bar */
		var side = el('aside', { 'class': 'flxa-me-side' });
		var prev = el('ul', { 'class': 'flxa-me-prev' });
		var pending = null;
		c.entries.forEach(function (e) {
			if (e.t === 'g') { pending = e.label; return; }
			if (e.hidden || e.blocked || !can(e.slug) || e.pf_off.indexOf('web') !== -1) return;
			if (pending !== null) { prev.appendChild(el('li', { 'class': 'is-g', text: pending })); pending = null; }
			prev.appendChild(el('li', { text: e.label || cat[e.slug].label }));
		});
		side.appendChild(el('div', { 'class': 'flxa-me-box' }, [el('h3', { text: T.preview + ' · ' + B.roles[state.role] }), prev]));

		var bottom = el('div', { 'class': 'flxa-me-box flxa-me-bottom' }, [el('h3', { text: T.bottom }), el('p', { 'class': 'flxa-me-note', text: T.bottomHelp })]);
		for (var k = 0; k < 4; k++) {
			(function (k) {
				var sel = el('select', { 'aria-label': T.bottom + ' ' + (k + 1), onchange: function () { c.bottom[k] = this.value; c.bottom = c.bottom.filter(function (x, j) { return x && c.bottom.indexOf(x) === j; }); touch(); } });
				sel.appendChild(el('option', { value: '', text: T.none }));
				B.catalog.forEach(function (it) {
					if (!can(it.slug)) return;
					sel.appendChild(el('option', { value: it.slug, text: it.label, selected: c.bottom[k] === it.slug }));
				});
				bottom.appendChild(sel);
			})(k);
		}
		side.appendChild(bottom);
		root.appendChild(side);
	}

	serialize();
	render();
	window.addEventListener('beforeunload', function (e) {
		if (state.dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
	});
	var submitting = false;
	var form = d.getElementById('flxa-form');
	if (form) form.addEventListener('submit', function () { submitting = true; serialize(); });
})();
