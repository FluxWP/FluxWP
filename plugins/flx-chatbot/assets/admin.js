/* Flux AI Chatbot · ayarlar: sekmeler, canlı önizleme, renk/medya seçiciler. GPL-2.0-or-later. */
(function () {
	'use strict';
	var A = window.flxcbAdmin || {};
	var DEF = A.defaults || {};
	var form = document.querySelector('[data-flxcb-form]');
	if (!form) return;
	var base = JSON.parse(JSON.stringify(window.flxcbConfig || {}));
	var dirty = false;
	var pv = 'home';

	/* ---------- Sekmeler ---------- */
	var tabInput = form.querySelector('[data-tab-input]');
	function showTab(t) {
		document.querySelectorAll('[data-tab]').forEach(function (a) {
			a.classList.toggle('is-on', a.getAttribute('data-tab') === t);
			a.setAttribute('aria-selected', a.getAttribute('data-tab') === t ? 'true' : 'false');
		});
		form.querySelectorAll('[data-pane]').forEach(function (p) {
			p.hidden = p.getAttribute('data-pane') !== t;
		});
		if (tabInput) tabInput.value = t;
		try {
			var u = new URL(location.href);
			u.searchParams.set('tab', t);
			u.searchParams.delete('msg');
			history.replaceState(null, '', u.toString());
		} catch (e) {}
	}
	document.querySelectorAll('[data-tab]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			showTab(a.getAttribute('data-tab'));
		});
	});

	/* ---------- Değer okuma ---------- */
	function val(k) {
		var els = form.querySelectorAll('[data-k="' + k + '"]');
		if (!els.length) return undefined;
		var el = els[0];
		if (el.type === 'radio') {
			for (var i = 0; i < els.length; i++) if (els[i].checked) return els[i].value;
			return '';
		}
		if (el.type === 'checkbox') return el.checked;
		return el.value;
	}
	function txt(k) {
		var v = (val(k) || '').trim();
		return v || DEF[k] || '';
	}
	function num(k, d) {
		var n = parseInt(val(k), 10);
		return isNaN(n) ? d : n;
	}
	function lines(k) {
		return (val(k) || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
	}
	function links() {
		return lines('links').map(function (l) {
			var p = l.split('|');
			var label = p[0].trim();
			var url = (p[1] || '').trim();
			if (!url && /^(https?:\/\/|mailto:|tel:)/i.test(label)) url = label;
			if (!/^(https?:\/\/|mailto:|tel:|whatsapp:)/i.test(url)) return null;
			var kind = /^mailto:/i.test(url) ? 'mail' : /^tel:/i.test(url) ? 'phone' : /(wa\.me|whatsapp\.com|^whatsapp:)/i.test(url) ? 'whatsapp' : 'link';
			return { label: label && label !== url ? label : url.replace(/^(https?:\/\/|mailto:|tel:)/i, ''), url: url, kind: kind };
		}).filter(Boolean).slice(0, 6);
	}
	function hex(c) {
		return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c || '') ? c : '';
	}
	/* PHP flxcb_contrast() ile aynı eşik. */
	function contrast(c) {
		c = c.replace('#', '');
		if (c.length === 3) c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
		var f = function (x) { x = parseInt(x, 16) / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); };
		var l = 0.2126 * f(c.substr(0, 2)) + 0.7152 * f(c.substr(2, 2)) + 0.0722 * f(c.substr(4, 2));
		return l > 0.45 ? '#111827' : '#ffffff';
	}

	/* ---------- Önizleme ---------- */
	function cfg() {
		var mode = val('mode') || base.mode;
		var color = hex(val('color'));
		var tcol = hex(val('color_text'));
		var av = form.querySelector('[data-k="avatar_id"]');
		return {
			mode: mode,
			ai: mode !== 'live' && !!base.ai,
			live: mode !== 'ai' && !!base.live,
			botName: txt('bot_name'),
			avatar: av && parseInt(av.value, 10) ? av.getAttribute('data-url') || '' : '',
			avatarIcon: val('avatar_icon') || 'bot',
			launcher: { icon: val('launcher_icon') || 'chat', label: (val('launcher_label') || '').trim(), size: num('launcher_size', 56) },
			color: color,
			colorText: tcol || (color ? contrast(color) : ''),
			theme: val('theme') || 'auto',
			position: val('position') === 'left' ? 'left' : 'right',
			offsetX: num('offset_x', 20),
			offsetY: num('offset_y', 20),
			radius: num('radius', 18),
			width: num('width', 380),
			header: val('header_style') === 'solid' ? 'solid' : 'gradient',
			font: val('font') === 'system' ? 'system' : 'inherit',
			mobileFull: !!val('mobile_full'),
			branding: !!val('show_branding'),
			sound: false,
			home: {
				enabled: !!val('home_enabled'),
				title: txt('home_title'),
				text: txt('home_text'),
				team: val('show_team') && mode !== 'ai' ? (base.home && base.home.team) || [] : [],
				links: links()
			},
			quick: lines('quick_replies').slice(0, 6),
			welcome: mode === 'live' ? '' : txt('welcome'),
			placeholder: txt('placeholder'),
			teaser: { enabled: !!val('teaser_enabled'), text: txt('teaser_text'), delay: num('teaser_delay', 12) },
			offlineText: txt('offline_text'),
			askEmail: val('ask_email') || 'offline',
			rating: !!val('rating'),
			uploads: !!val('uploads'),
			uploadMb: num('upload_mb', 5),
			privacy: (val('privacy_text') || '').trim()
		};
	}
	var rT;
	function refresh() {
		clearTimeout(rT);
		rT = setTimeout(function () {
			if (!window.FluxChatbot) return;
			window.FluxChatbot.update(cfg());
			applyView();
		}, 120);
	}
	function applyView() {
		var F = window.FluxChatbot;
		if (!F) return;
		if (pv === 'closed') F.close();
		else if (pv === 'teaser') F.teaser();
		else F.view && F.view(pv);
	}
	document.querySelectorAll('[data-pv]').forEach(function (b) {
		b.addEventListener('click', function () {
			pv = b.getAttribute('data-pv');
			document.querySelectorAll('[data-pv]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
			applyView();
		});
	});

	function markDirty() {
		if (dirty) return;
		dirty = true;
		var d = form.querySelector('[data-dirty]');
		if (d) d.hidden = false;
	}
	form.addEventListener('input', function (e) {
		var t = e.target;
		if (t.type === 'range') {
			var o = t.closest('label') && t.closest('label').querySelector('output');
			if (o) o.textContent = t.value + (t.getAttribute('data-unit') || '');
		}
		markDirty();
		if (t.hasAttribute('data-k')) refresh();
	});
	form.addEventListener('change', function (e) {
		var t = e.target;
		markDirty();
		if (t.getAttribute('data-k') === 'hours_mode') {
			var h = form.querySelector('[data-hours]');
			if (h) h.hidden = t.value !== 'custom';
		}
		if (t.hasAttribute('data-k')) refresh();
	});
	form.addEventListener('submit', function () { dirty = false; });
	window.addEventListener('beforeunload', function (e) {
		if (dirty) { e.preventDefault(); e.returnValue = A.i18n && A.i18n.unsaved || ''; }
	});

	/* ---------- Renk ---------- */
	function setColor(k, v) {
		var t = form.querySelector('[data-k="' + k + '"]');
		var p = form.querySelector('[data-color-for="' + k + '"]');
		if (t) t.value = v;
		if (p && hex(v)) p.value = v.length === 4 ? '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3] : v;
		markDirty();
		refresh();
	}
	form.querySelectorAll('[data-color-for]').forEach(function (p) {
		p.addEventListener('input', function () { setColor(p.getAttribute('data-color-for'), p.value); });
	});
	form.querySelectorAll('[data-k="color"],[data-k="color_text"]').forEach(function (t) {
		t.addEventListener('input', function () {
			var p = form.querySelector('[data-color-for="' + t.getAttribute('data-k') + '"]');
			if (p && /^#[0-9a-f]{6}$/i.test(t.value)) p.value = t.value;
		});
	});
	form.querySelectorAll('[data-clear]').forEach(function (b) {
		b.addEventListener('click', function () { setColor(b.getAttribute('data-clear'), ''); });
	});
	form.querySelectorAll('[data-swatch]').forEach(function (b) {
		b.addEventListener('click', function () { setColor('color', b.getAttribute('data-swatch')); });
	});

	/* ---------- Avatar ---------- */
	var frame;
	var mBtn = form.querySelector('[data-media]');
	var mClr = form.querySelector('[data-media-clear]');
	var mInp = form.querySelector('[data-k="avatar_id"]');
	var mImg = form.querySelector('[data-avatar-img]');
	var emptyImg = mImg ? mImg.innerHTML : '';
	function setAvatar(id, url) {
		mInp.value = id || 0;
		mInp.setAttribute('data-url', url || '');
		mImg.innerHTML = url ? '<img src="' + url.replace(/"/g, '&quot;') + '" alt="">' : emptyImg;
		mClr.hidden = !url;
		markDirty();
		refresh();
	}
	if (mBtn && window.wp && wp.media) {
		mBtn.addEventListener('click', function () {
			if (!frame) {
				frame = wp.media({ title: A.i18n && A.i18n.pick, button: { text: A.i18n && A.i18n.use }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var u = a.sizes && (a.sizes.thumbnail || a.sizes.medium) ? (a.sizes.thumbnail || a.sizes.medium).url : a.url;
					setAvatar(a.id, u);
				});
			}
			frame.open();
		});
	}
	if (mClr) mClr.addEventListener('click', function () { setAvatar(0, ''); });

	/* Önizleme sahnesinin ölçüsü pencereye CSS değişkeniyle geçer (yükseklik ve genişlik sınırı). */
	var stage = document.querySelector('[data-flxcb-stage]');
	function stageSize() {
		if (!stage) return;
		stage.style.setProperty('--flxcb-stage-h', stage.clientHeight + 'px');
		stage.style.setProperty('--flxcb-stage-w', stage.clientWidth + 'px');
	}
	stageSize();
	window.addEventListener('resize', stageSize);

	/* İlk çizim: widget yüklendikten sonra form durumunu uygula. */
	function boot() {
		/* Pencere DOMContentLoaded'da kurulur; kurulmadan güncelleme yapılmaz. */
		if (window.FluxChatbot && document.querySelector('[data-flxcb-stage] #flxcb')) {
			window.FluxChatbot.update(cfg());
			applyView();
		} else {
			setTimeout(boot, 60);
		}
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
