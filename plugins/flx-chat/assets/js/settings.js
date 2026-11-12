/* Flux Chat — ayarlar sayfası */
(function () {
	'use strict';
	var C = window.FLXC_SET || {};
	var T = C.i18n || {};

	function api(path, body) {
		return fetch(C.rest + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json' },
			body: JSON.stringify(body || {})
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) throw new Error((j && j.message) || ('HTTP ' + r.status));
				return j;
			});
		});
	}
	function flxcsVerOld(v) {
		var p = String(v).split('.');
		return (parseInt(p[0], 10) || 0) < 2 || ((parseInt(p[0], 10) || 0) === 2 && (parseInt(p[1], 10) || 0) < 3);
	}
	function fmt(s) {
		var args = Array.prototype.slice.call(arguments, 1), i = 0;
		return String(s).replace(/%(\d\$)?s/g, function (m, n) { return n ? args[parseInt(n, 10) - 1] : args[i++]; });
	}
	function out(box, html, kind) {
		box.className = 'flxcs-out' + (kind ? ' is-' + kind : '');
		box.innerHTML = '';
		if (typeof html === 'string') box.textContent = html; else box.appendChild(html);
	}

	/* Hat ekle / sil / kaynak seçimi */
	var conns = document.getElementById('flxcs-conns');
	var tpl = document.getElementById('flxcs-conn-tpl');
	var addBtn = document.getElementById('flxcs-add-conn');
	function syncSource(row) {
		var sel = row.querySelector('.flxcs-source');
		if (!sel) return;
		var own = sel.value === 'own';
		var flxi = sel.value.indexOf('flxi:') === 0;
		row.querySelectorAll('.flxcs-own').forEach(function (n) { n.hidden = !own; });
		// Hesap Flux Mail'deyse QR orada okutulur (1.34.0).
		row.querySelectorAll('.flxcs-not-flxi').forEach(function (n) { n.hidden = flxi; });
		row.querySelectorAll('.flxcs-flxi-only').forEach(function (n) { n.hidden = !flxi; });
	}
	if (conns) {
		conns.querySelectorAll('.flxcs-conn').forEach(syncSource);
		conns.addEventListener('change', function (e) {
			if (e.target.classList.contains('flxcs-source')) syncSource(e.target.closest('.flxcs-conn'));
		});
		if (addBtn && tpl) {
			addBtn.addEventListener('click', function () {
				var i = 'n' + Date.now();
				var wrap = document.createElement('div');
				wrap.innerHTML = tpl.innerHTML.replace(/__i__/g, i);
				var row = wrap.firstElementChild;
				conns.appendChild(row);
				syncSource(row);
				row.querySelector('.flxcs-conn-label').focus();
			});
			if (!conns.children.length) addBtn.click();
		}
		conns.addEventListener('click', async function (e) {
			var row = e.target.closest('.flxcs-conn');
			if (!row) return;
			if (e.target.classList.contains('flxcs-del')) {
				if (!(await window.flxDialog.confirm({ message: T.confirmDel }))) return;
				if (row.dataset.conn) { row.querySelector('.flxcs-del-flag').value = '1'; row.hidden = true; }
				else row.remove();
				return;
			}
			if (e.target.classList.contains('flxcs-copy')) {
				var u = row.querySelector('.flxcs-wh-url').textContent;
				navigator.clipboard && navigator.clipboard.writeText(u).then(function () { e.target.textContent = T.copied; });
				return;
			}
			var act = e.target.dataset && e.target.dataset.act;
			if (!act) return;
			var id = row.dataset.conn;
			var box = row.querySelector('.flxcs-out');
			if (!id) { out(box, T.saveFirst, 'err'); return; }
			e.target.disabled = true;
			out(box, act === 'import' ? T.importing : T.testing);
			api('admin/connections/' + id + '/' + act, act === 'import' ? { chats: 30 } : {}).then(function (r) {
				var st = row.querySelector('.flxcs-state');
				if (act === 'test') {
					var lines = document.createElement('div');
					var a = document.createElement('div');
					a.textContent = r.state === 'open' ? T.open : (r.error ? T.error + ' ' + r.error : fmt(T.notOpen, r.state || '?'));
					var b = document.createElement('div');
					b.textContent = r.webhook_ok ? T.whOk : T.whMissing;
					lines.appendChild(a); lines.appendChild(b);
					if (r.version) {
						var v = document.createElement('div');
						v.textContent = fmt(T.evoVer, r.version) + (flxcsVerOld(r.version) ? ' ' + T.evoOld : '');
						lines.appendChild(v);
					}
					out(box, lines, r.state === 'open' && r.webhook_ok ? 'ok' : 'warn');
					if (st) st.className = 'flxcs-state flxcs-state-' + (r.state || 'unknown');
				} else if (act === 'webhook') {
					out(box, r.ok ? T.whSet : T.error + ' ' + r.error, r.ok ? 'ok' : 'err');
				} else if (act === 'import') {
					out(box, r.ok ? fmt(T.imported, r.chats, r.messages) : T.error + ' ' + r.error, r.ok ? 'ok' : 'err');
				} else if (act === 'qr') {
					if (r.state === 'open') { out(box, T.alreadyOpen, 'ok'); if (st) st.className = 'flxcs-state flxcs-state-open'; return; }
					if (!r.ok && r.error) { out(box, T.error + ' ' + r.error, 'err'); return; }
					var w = document.createElement('div');
					w.className = 'flxcs-qr';
					if (r.qr) { var img = document.createElement('img'); img.src = r.qr.indexOf('data:') === 0 ? r.qr : 'data:image/png;base64,' + r.qr; img.alt = 'QR'; w.appendChild(img); }
					var p = document.createElement('p'); p.textContent = T.qrScan; w.appendChild(p);
					if (r.code) { var c = document.createElement('p'); c.innerHTML = '<strong></strong>'; c.firstChild.textContent = T.pairCode + ' ' + r.code; w.appendChild(c); }
					out(box, w);
				}
			}).catch(function (err) { out(box, T.error + ' ' + err.message, 'err'); }).then(function () { e.target.disabled = false; });
		});
	}

	/* Etiketler */
	var labels = document.getElementById('flxcs-labels');
	var ltpl = document.getElementById('flxcs-label-tpl');
	var addL = document.getElementById('flxcs-add-label');
	if (labels && ltpl && addL) {
		addL.addEventListener('click', function () {
			var wrap = document.createElement('div');
			wrap.innerHTML = ltpl.innerHTML.replace(/__i__/g, 'n' + Date.now());
			labels.appendChild(wrap.firstElementChild);
			labels.lastElementChild.querySelector('input[type=text]').focus();
		});
		labels.addEventListener('click', function (e) {
			if (e.target.classList.contains('flxcs-del-label')) e.target.closest('.flxcs-label').remove();
		});
	}

	/* Yapay zekâ: model seçici (model-picker.js) ve test */
	if (window.FLXCModelPicker) {
		document.querySelectorAll('.flxcs-mp').forEach(function (box) {
			box.innerHTML = '';
			box.appendChild(window.FLXCModelPicker.create({
				name: box.dataset.name,
				value: box.dataset.value || '',
				empty: box.dataset.empty || '',
				def: box.dataset.def || '',
				tools: box.dataset.tools === '1',
				vision: box.dataset.vision === '1'
			}));
		});
	}
	var aiTest = document.getElementById('flxcs-ai-test');
	if (aiTest) {
		aiTest.addEventListener('click', function () {
			var o = document.getElementById('flxcs-ai-out');
			aiTest.disabled = true;
			out(o, T.aiTesting);
			api('admin/ai/test').then(function (r) { out(o, fmt(T.aiOk, r.text, r.ms), 'ok'); })
				.catch(function (e) { out(o, T.error + ' ' + e.message, 'err'); })
				.then(function () { aiTest.disabled = false; });
		});
	}
})();
