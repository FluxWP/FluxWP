/* Flux Commerce · Havale/EFT paneli: kopyala ve dekont yükleme. GPL-2.0-or-later. */
(function () {
	'use strict';
	var cfg = window.flxcmFront || {};

	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-flxcm-copy]');
		if (!b) return;
		var v = b.getAttribute('data-flxcm-copy');
		var done = function () {
			var s = b.querySelector('span');
			if (!s) return;
			var o = s.textContent;
			s.textContent = cfg.copied || 'OK';
			b.classList.add('is-done');
			setTimeout(function () { s.textContent = o; b.classList.remove('is-done'); }, 1500);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(v).then(done);
		} else {
			var t = document.createElement('textarea');
			t.value = v; t.style.position = 'fixed'; t.style.opacity = '0';
			document.body.appendChild(t); t.select();
			try { document.execCommand('copy'); done(); } catch (err) {}
			document.body.removeChild(t);
		}
	});

	document.querySelectorAll('[data-flxcm-upload]').forEach(function (box) {
		var form = box.querySelector('form');
		if (!form || !window.fetch || !window.FormData) return;
		var input = form.querySelector('[data-flxcm-file]');
		var send = form.querySelector('[data-flxcm-send]');
		var err = form.querySelector('[data-flxcm-err]');
		var pick = form.querySelector('[data-flxcm-pick]');
		var list = box.querySelector('[data-flxcm-files]');
		var ok = box.querySelector('[data-flxcm-ok]');
		var zone = form.querySelector('.flxcm-drop__zone');
		var pickText = pick ? pick.textContent : '';
		input.addEventListener('change', function () {
			if (pick) pick.textContent = input.files[0] ? input.files[0].name : pickText;
			zone.classList.toggle('has-file', !!input.files[0]);
		});
		['dragenter', 'dragover'].forEach(function (n) { zone.addEventListener(n, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (n) { zone.addEventListener(n, function () { zone.classList.remove('is-over'); }); });
		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); }
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!input.files[0]) { input.click(); return; }
			err.hidden = true;
			send.disabled = true;
			var label = send.querySelector('span'), old = label.textContent;
			label.textContent = cfg.busy;
			var fd = new FormData();
			fd.append('order', box.getAttribute('data-order'));
			fd.append('key', box.getAttribute('data-key'));
			fd.append('dekont', input.files[0]);
			fetch(cfg.rest, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok || !res.j.ok) throw new Error((res.j && res.j.message) || cfg.error);
					var li = document.createElement('li');
					var a = document.createElement('a');
					a.href = res.j.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = res.j.name;
					var sm = document.createElement('small'); sm.textContent = res.j.date;
					li.appendChild(a); li.appendChild(sm);
					list.appendChild(li); list.hidden = false; ok.hidden = false;
					input.value = ''; if (pick) pick.textContent = pickText; zone.classList.remove('has-file');
					if (res.j.full) form.remove();
				})
				.catch(function (x) { err.textContent = x.message || cfg.error; err.hidden = false; })
				.then(function () { send.disabled = false; label.textContent = old; });
		});
	});
})();
