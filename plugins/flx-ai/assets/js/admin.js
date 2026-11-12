/**
 * Flux AI yönetim ekranları: dikey bar grafikler (SVG), kredi yenileme, model testi.
 */
(function () {
	'use strict';
	var A = window.FLXAI_ADMIN || {};
	var L = A.i18n || {};
	function t(s) { return L[s] || s; }
	function tf(s) { var a = arguments, i = 1; return t(s).replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	var loc = A.locale || 'tr-TR';
	function num(v, d) { return (+v || 0).toLocaleString(loc, { maximumFractionDigits: d == null ? 0 : d }); }
	function usd(v) { v = +v || 0; return '$' + v.toLocaleString(loc, { minimumFractionDigits: v >= 1 ? 2 : 4, maximumFractionDigits: v >= 1 ? 2 : 4 }); }
	function dur(ms) { ms = +ms || 0; if (ms < 1000) return tf('%s ms', ms < 10 ? num(ms, 1) : Math.round(ms)); var s = ms / 1000; return s < 60 ? tf('%s sn', num(s, 1)) : tf('%1$s dk %2$s sn', Math.floor(s / 60), Math.round(s % 60)); }
	function day(d) { try { return new Date(d + 'T12:00:00').toLocaleDateString(loc, { day: 'numeric', month: 'short' }); } catch (e) { return d; } }
	function nice(max) {
		if (max <= 0) return 1;
		var p = Math.pow(10, Math.floor(Math.log10(max))), n = max / p;
		return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
	}

	/**
	 * Dikey bar grafik. series: [{name, cls, values}], stacked: üst üste.
	 * Bar ≤24px, üst uç 4px yuvarlak, taban kare; dilimler arasında 2px boşluk; ızgara silik.
	 */
	function bars(el, o) {
		var n = o.labels.length;
		var total = 0;
		o.series.forEach(function (s) { s.values.forEach(function (v) { total += +v || 0; }); });
		if (!n || !total) { el.innerHTML = '<div class="flxai-chart-empty">' + esc(t('Bu aralıkta veri yok.')) + '</div>'; return; }
		var W = Math.max(280, el.clientWidth || 600), H = 240, pl = 56, pr = 8, pt = 10, pb = 26;
		var iw = W - pl - pr, ih = H - pt - pb, band = iw / n;
		var bw = Math.max(2, Math.min(24, band * 0.68));
		var sums = o.labels.map(function (_, i) {
			return o.stacked ? o.series.reduce(function (a, s) { return a + (+s.values[i] || 0); }, 0) : Math.max.apply(null, o.series.map(function (s) { return +s.values[i] || 0; }));
		});
		var max = nice(Math.max.apply(null, sums)), y = function (v) { return pt + ih - (v / max) * ih; };
		var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(o.title || '') + '">';
		for (var g = 0; g <= 4; g++) {
			var gv = max * g / 4, gy = y(gv);
			svg += '<line class="gl" x1="' + pl + '" x2="' + (W - pr) + '" y1="' + gy + '" y2="' + gy + '"/>';
			svg += '<text class="ax" x="' + (pl - 8) + '" y="' + (gy + 4) + '" text-anchor="end">' + esc(o.fmtAxis(gv)) + '</text>';
		}
		var every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 64))));
		for (var i = 0; i < n; i++) {
			var cx = pl + band * i + band / 2, x0 = cx - bw / 2, base = pt + ih;
			if (o.stacked) {
				var acc = 0, segs = [];
				o.series.forEach(function (s, si) { var v = +s.values[i] || 0; if (v > 0) segs.push({ v: v, cls: s.cls, si: si }); });
				segs.forEach(function (sg, k) {
					var y0 = y(acc), y1 = y(acc + sg.v), top = k === segs.length - 1;
					var hgt = y0 - y1 - (k > 0 ? 2 : 0);
					if (hgt > 0.5) svg += rect(x0, y1, bw, hgt, top ? 4 : 0, sg.cls);
					acc += sg.v;
				});
			} else {
				var m = o.series.length, w = bw / m;
				o.series.forEach(function (s, si) { var v = +s.values[i] || 0; if (v > 0) svg += rect(x0 + si * w + (si ? 1 : 0), y(v), w - (si ? 1 : 0), base - y(v), 4, s.cls); });
			}
			if (i % every === 0) svg += '<text class="ax" x="' + cx + '" y="' + (H - 8) + '" text-anchor="middle">' + esc(o.short ? o.short(o.labels[i]) : o.labels[i]) + '</text>';
			svg += '<rect class="hit" data-i="' + i + '" x="' + (pl + band * i) + '" y="' + pt + '" width="' + band + '" height="' + ih + '"/>';
		}
		svg += '</svg>';
		var legend = o.series.length > 1 ? '<div class="flxai-legend">' + o.series.map(function (s) { return '<span><i class="' + s.cls + '" style="background:var(--' + s.cls + ')"></i>' + esc(s.name) + '</span>'; }).join('') + '</div>' : '';
		var table = '<details><summary>' + esc(t('Tablo olarak göster')) + '</summary><div class="flxai-tscroll"><table class="flxai-table"><thead><tr><th></th>' +
			o.series.map(function (s) { return '<th class="num">' + esc(s.name) + '</th>'; }).join('') + '</tr></thead><tbody>' +
			o.labels.map(function (l, i) { return '<tr><td>' + esc(o.long ? o.long(l) : l) + '</td>' + o.series.map(function (s) { return '<td class="num">' + esc(o.fmt(s.values[i])) + '</td>'; }).join('') + '</tr>'; }).join('') +
			'</tbody></table></div></details>';
		el.innerHTML = legend + svg + table;
		var tip = document.createElement('div');
		tip.className = 'flxai-tip'; tip.hidden = true;
		el.appendChild(tip);
		var svgEl = el.querySelector('svg');
		svgEl.addEventListener('mousemove', function (e) {
			var r = e.target.closest && e.target.closest('.hit');
			if (!r) { tip.hidden = true; return; }
			var i = +r.getAttribute('data-i'), box = svgEl.getBoundingClientRect(), sc = box.width / W;
			tip.innerHTML = '<b>' + esc(o.long ? o.long(o.labels[i]) : o.labels[i]) + '</b>' + o.series.map(function (s) {
				return '<div><i style="background:var(--' + s.cls + ')"></i>' + esc(s.name) + ': <strong>' + esc(o.fmt(s.values[i])) + '</strong></div>';
			}).join('') + (o.extra ? o.extra(i) : '');
			tip.hidden = false;
			tip.style.left = ((pl + band * i + band / 2) * sc) + 'px';
			tip.style.top = (y(sums[i]) * sc + (legend ? 26 : 0)) + 'px';
		});
		svgEl.addEventListener('mouseleave', function () { tip.hidden = true; });
	}
	function rect(x, y, w, h, r, cls) {
		r = Math.min(r, w / 2, h);
		if (!r) return '<rect class="' + cls + '" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '"/>';
		return '<path class="' + cls + '" d="M' + x + ',' + (y + h) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y + 'H' + (x + w - r) + 'Q' + (x + w) + ',' + y + ' ' + (x + w) + ',' + (y + r) + 'V' + (y + h) + 'Z"/>';
	}

	function charts() {
		var node = document.getElementById('flxai-chart-data');
		if (!node) return;
		var d;
		try { d = JSON.parse(node.textContent); } catch (e) { return; }
		var labels = (d.daily || []).map(function (r) { return r.date; });
		var col = function (k) { return d.daily.map(function (r) { return r[k]; }); };
		var defs = {
			cost: { title: t('Günlük maliyet (USD)'), labels: labels, short: day, long: day, series: [{ name: t('Maliyet'), cls: 's1', values: col('cost') }], fmt: usd, fmtAxis: function (v) { return '$' + num(v, v < 1 ? 3 : 2); },
				extra: function (i) { var r = d.daily[i]; return '<div>' + esc(tf('%1$s istek · %2$s araç', num(r.calls), num(r.tools))) + '</div>'; } },
			time: { title: t('Süre'), labels: labels, short: day, long: day, stacked: true, series: [{ name: t('Model / düşünme'), cls: 's1', values: col('model_ms') }, { name: t('Araçlar'), cls: 's2', values: col('tool_ms') }], fmt: dur, fmtAxis: function (v) { return v >= 60000 ? num(v / 60000, 1) + ' dk' : num(v / 1000, 0) + ' sn'; } },
			tokens: { title: t('Token'), labels: labels, short: day, long: day, stacked: true, series: [{ name: t('Giriş'), cls: 's1', values: col('in') }, { name: t('Çıkış'), cls: 's2', values: col('out') }], fmt: function (v) { return num(v); }, fmtAxis: function (v) { return v >= 1e6 ? num(v / 1e6, 1) + 'M' : v >= 1e3 ? num(v / 1e3, 0) + 'K' : num(v); } },
			tools: { title: t('Araç başına ortalama süre'), labels: (d.tools || []).map(function (r) { return r.label; }), short: function (l) { return l.length > 10 ? l.slice(0, 9) + '…' : l; },
				series: [{ name: t('Ortalama süre'), cls: 's1', values: (d.tools || []).map(function (r) { return r.avg; }) }], fmt: dur, fmtAxis: function (v) { return v >= 1000 ? num(v / 1000, 1) + ' sn' : num(v) + ' ms'; },
				extra: function (i) { var r = d.tools[i]; return '<div>' + esc(tf('%1$s çağrı · en uzun %2$s', num(r.n), dur(r.max))) + (r.err ? ' · ' + esc(tf('%s hata', r.err)) : '') + '</div>'; } }
		};
		var draw = function () { document.querySelectorAll('[data-chart]').forEach(function (el) { var o = defs[el.getAttribute('data-chart')]; if (o) bars(el, o); }); };
		draw();
		var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(draw, 200); });
	}

	function api(method, path, body) {
		return fetch(A.rest + (A.rest.indexOf('?') < 0 ? path + (path.indexOf('?') < 0 ? '?' : '&') : path.replace('?', '&') + '&') + '_locale=user&_t=' + Date.now(), {
			method: method, credentials: 'same-origin',
			headers: { 'X-WP-Nonce': A.nonce, 'Content-Type': 'application/json' },
			body: body ? JSON.stringify(body) : undefined
		}).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || ('HTTP ' + r.status)); return j; }); });
	}

	function credits() {
		var b = document.querySelector('[data-flxai-credits]');
		if (!b) return;
		b.addEventListener('click', function () {
			b.disabled = true;
			api('POST', 'credits').then(function () { location.reload(); }).catch(function (e) { b.disabled = false; document.getElementById('flxai-credits').innerHTML = '<p class="flxai-err">' + esc(e.message) + '</p>'; });
		});
	}

	function tests() {
		document.querySelectorAll('[data-flxai-test]').forEach(function (b) {
			b.addEventListener('click', function () {
				var inp = document.querySelector('input[name="' + b.getAttribute('data-flxai-test') + '"]'), out = b.nextElementSibling;
				var model = inp ? (inp.value || inp.placeholder) : '';
				out.className = 'flxai-test-out'; out.textContent = t('Deneniyor…'); b.disabled = true;
				api('POST', 'test', { model: model }).then(function (r) {
					out.className = 'flxai-test-out is-ok';
					out.textContent = '✓ ' + dur(r.ms) + ' · ' + usd(r.usage && r.usage.cost) + ' · ' + r.text;
				}).catch(function (e) { out.className = 'flxai-test-out is-err'; out.textContent = e.message; }).then(function () { b.disabled = false; });
			});
		});
	}

	// Görsel çevirici: formdaki adres/kullanıcı/şifre ile (kaydetmeden) örnek PNG.
	function renderTest() {
		var b = document.querySelector('[data-flxai-render-test]');
		if (!b) return;
		var out = b.nextElementSibling, prev = document.querySelector('.flxai-render-prev');
		function val(n) { var i = document.querySelector('input[name="' + n + '"]'); return i ? i.value.trim() : ''; }
		b.addEventListener('click', function () {
			out.className = 'flxai-test-out'; out.textContent = t('Deneniyor…'); b.disabled = true; prev.hidden = true; prev.textContent = '';
			api('POST', 'render-test', { url: val('render_url'), user: val('render_user'), pass: val('key_render') }).then(function (r) {
				out.className = 'flxai-test-out is-ok';
				out.textContent = '✓ ' + r.via + ' · ' + dur(r.ms) + ' · ' + tf('%s KB', num(r.bytes / 1024, 1));
				var img = document.createElement('img'); img.src = r.img; img.alt = ''; prev.appendChild(img); prev.hidden = false;
			}).catch(function (e) { out.className = 'flxai-test-out is-err'; out.textContent = e.message; }).then(function () { b.disabled = false; });
		});
	}

	// Kayıtlar: satırın giden/gelen içeriği (maskeli). İçerik yalnız textContent ile basılır.
	function bodies() {
		var roles = { system: t('Sistem'), user: t('Giden · kullanıcı'), assistant: t('Giden · asistan'), tool: t('Giden · araç sonucu') };
		document.querySelectorAll('[data-flxai-body]').forEach(function (b) {
			b.addEventListener('click', function () {
				var tr = b.closest('tr'), next = tr.nextElementSibling;
				if (next && next.classList.contains('flxai-body')) { next.remove(); return; }
				b.disabled = true;
				api('GET', 'log/' + b.getAttribute('data-flxai-body')).then(function (r) {
					var row = document.createElement('tr'), td = document.createElement('td');
					row.className = 'flxai-body'; td.colSpan = tr.children.length; row.appendChild(td);
					function block(label, text, cls) {
						var d = document.createElement('div'), h = document.createElement('b'), pre = document.createElement('pre');
						d.className = 'flxai-body-msg' + (cls ? ' ' + cls : ''); h.textContent = label; pre.textContent = text; d.appendChild(h); d.appendChild(pre); td.appendChild(d);
					}
					(r.req || []).forEach(function (m) { block(roles[m[0]] || m[0], m[1] || ''); });
					var res = r.res || {};
					if (res.error) block(t('Gelen · hata'), res.error, 'is-res');
					else block(t('Gelen · cevap'), (res.text || '') + (res.tools && res.tools.length ? '\n\n→ ' + res.tools.join('\n→ ') : ''), 'is-res');
					tr.parentNode.insertBefore(row, tr.nextSibling);
				}).catch(function (e) { b.title = e.message; b.textContent = '!'; }).then(function () { b.disabled = false; });
			});
		});
	}

	// Araçlar: arama, süzgeçler (eklenti, tür, durum), eklenti çipleri, toplu seçim. Süzgeç adres çubuğunda (#src=…&q=…).
	function toolsUi() {
		var form = document.querySelector('[data-flxai-tools]');
		if (!form) return;
		var rows = [].slice.call(form.querySelectorAll('tr[data-tool]'));
		var none = form.querySelector('.flxai-nomatch');
		var count = form.querySelector('[data-count]');
		var q = form.querySelector('[data-f="q"]'), src = form.querySelector('[data-f="src"]');
		var chips = [].slice.call(form.querySelectorAll('.flxai-chip'));
		var bulk = form.querySelector('[data-bulk]'), selCount = form.querySelector('[data-sel-count]');
		var all = form.querySelector('[data-sel-all]');
		var f = { q: '', src: '', kind: '', state: '' };
		try {
			var h = new URLSearchParams(location.hash.slice(1));
			['q', 'src', 'kind', 'state'].forEach(function (k) { if (h.get(k)) f[k] = h.get(k); });
		} catch (e) { /* eski tarayıcı */ }
		function seg(key) { return form.querySelector('.flxai-seg[data-f="' + key + '"]'); }
		function paint() {
			q.value = f.q; src.value = f.src;
			['kind', 'state'].forEach(function (k) {
				[].forEach.call(seg(k).querySelectorAll('button'), function (b) { var on = b.getAttribute('data-v') === f[k]; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
			});
			chips.forEach(function (c) { c.classList.toggle('is-on', c.getAttribute('data-src') === f.src); });
		}
		function match(r) {
			var d = r.dataset;
			if (f.src && d.src !== f.src) return false;
			if (f.kind && d.kind !== f.kind) return false;
			if (f.state === 'on' && d.state !== 'on') return false;
			if (f.state === 'off' && d.state !== 'off') return false;
			if (f.state === 'conflict' && d.conflict !== '1') return false;
			if (f.state === 'noprefix' && (d.noprefix !== '1' || d.state === 'dupe')) return false;
			if (f.q) {
				var words = f.q.toLowerCase().split(/\s+/).filter(Boolean);
				for (var i = 0; i < words.length; i++) if (d.q.indexOf(words[i]) < 0) return false;
			}
			return true;
		}
		function apply() {
			var n = 0;
			rows.forEach(function (r) { var ok = match(r); r.hidden = !ok; if (ok && r.dataset.state !== 'dupe') n++; });
			none.hidden = rows.some(function (r) { return !r.hidden; });
			count.textContent = tf('%s araç gösteriliyor', num(n));
			var parts = [];
			Object.keys(f).forEach(function (k) { if (f[k]) parts.push(k + '=' + encodeURIComponent(f[k])); });
			try { history.replaceState(null, '', parts.length ? '#' + parts.join('&') : location.pathname + location.search); } catch (e) { /* yok */ }
			sel();
		}
		function boxes(visibleOnly) { return [].slice.call(form.querySelectorAll('[data-sel]')).filter(function (b) { return !visibleOnly || !b.closest('tr').hidden; }); }
		function sel() {
			var v = boxes(true), checked = boxes(false).filter(function (b) { return b.checked; });
			bulk.hidden = !checked.length;
			selCount.textContent = tf('%s seçili', num(checked.length));
			var vc = v.filter(function (b) { return b.checked; }).length;
			all.checked = v.length > 0 && vc === v.length;
			all.indeterminate = vc > 0 && vc < v.length;
			rows.forEach(function (r) { var b = r.querySelector('[data-sel]'); r.classList.toggle('is-sel', !!(b && b.checked)); });
		}
		q.addEventListener('input', function () { f.q = q.value.trim(); apply(); });
		src.addEventListener('change', function () { f.src = src.value; paint(); apply(); });
		chips.forEach(function (c) { c.addEventListener('click', function () { f.src = c.getAttribute('data-src'); paint(); apply(); }); });
		['kind', 'state'].forEach(function (k) {
			seg(k).addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; f[k] = b.getAttribute('data-v'); paint(); apply(); });
		});
		// Seçim: görünmeyen satırlar seçimden çıkar (gizli satır yanlışlıkla kapanmasın).
		all.addEventListener('change', function () { boxes(true).forEach(function (b) { b.checked = all.checked; }); sel(); });
		form.addEventListener('change', function (e) {
			if (e.target.matches('[data-sel]')) sel();
			if (e.target.name === 'on[]') { e.target.closest('tr').classList.toggle('is-off', !e.target.checked); form.querySelector('[data-dirty]').hidden = false; }
		});
		form.querySelector('[data-sel-clear]').addEventListener('click', function () { boxes(false).forEach(function (b) { b.checked = false; }); sel(); });
		form.addEventListener('submit', function () { boxes(false).forEach(function (b) { if (b.closest('tr').hidden) b.checked = false; }); });
		// Satıra tıklayınca seç (bağlantı, düğme ve girişler hariç).
		rows.forEach(function (r) {
			r.addEventListener('click', function (e) {
				if (e.target.closest('a,button,input,label,select,code')) return;
				var b = r.querySelector('[data-sel]'); if (!b) return;
				b.checked = !b.checked; sel();
			});
		});
		paint(); apply();
	}

	/* Zamanlanmış görev formu: seçilen tekrara göre alanlar. */
	function taskForm() {
		var f = document.querySelector('[data-flxai-task-form]');
		if (!f) return;
		var sel = f.querySelector('[name="freq"]');
		function apply() {
			f.querySelectorAll('[data-for]').forEach(function (r) {
				var on = r.getAttribute('data-for').split(' ').indexOf(sel.value) >= 0;
				r.hidden = !on;
				r.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
			});
		}
		sel.addEventListener('change', apply);
		apply();
		var nt = f.querySelector('[name="notify"]'), ch = f.querySelector('[data-for-notify]');
		if (nt && ch) {
			var applyN = function () { ch.hidden = nt.value === 'none'; };
			nt.addEventListener('change', applyN);
			applyN();
		}
		var wa = f.querySelector('[name="notify_wa"]'), num = f.querySelector('[name="notify_whatsapp"]');
		if (wa && num) {
			var box = num.closest('.flx-ph') || num;
			var applyW = function () { box.hidden = !wa.checked; };
			wa.addEventListener('change', applyW);
			applyW();
		}
		recipients(f.querySelector('[data-rcp]'));
	}

	/* Görev bildirimi ek alıcıları: CRM kişisi, kullanıcı, WhatsApp grubu; alıcı başına kanal. */
	function recipients(box) {
		if (!box) return;
		var inp = box.querySelector('input[type=search]'), res = box.querySelector('.flxai-rcp-res'), listEl = box.querySelector('.flxai-rcp-list'), hid = box.querySelector('[name="notify_to"]');
		var chOk = {}; try { chOk = JSON.parse(box.getAttribute('data-channels') || '{}'); } catch (e) {}
		var list = []; try { list = JSON.parse(hid.value || '[]') || []; } catch (e) {}
		var found = [], timer = 0, seq = 0;
		var CH = { email: t('E-posta'), sms: 'SMS', whatsapp: 'WhatsApp' };
		var KIND = { contact: t('Kişi'), user: t('Kullanıcı'), group: t('WhatsApp grubu'), email: t('E-posta'), phone: t('Telefon') };
		function can(r, c) {
			if (r.kind === 'group') return c === 'whatsapp';
			if (c === 'email') return !!r.email;
			return !!r.phone && !!chOk[c];
		}
		function save() { hid.value = JSON.stringify(list); }
		function draw() {
			listEl.innerHTML = list.map(function (r, i) {
				return '<div class="flxai-rcp-row"><span class="flxai-rcp-who"><b>' + esc(r.name) + '</b><small>' + esc(KIND[r.kind] || '') + (r.email || r.phone ? ' · ' + esc(r.email || ('+' + r.phone)) : '') + '</small></span>' +
					'<span class="flxai-rcp-ch">' + ['email', 'sms', 'whatsapp'].map(function (c) {
						var dis = !can(r, c), on = (r.channels || []).indexOf(c) >= 0;
						return '<label class="flxai-chip' + (on ? ' is-on' : '') + (dis ? ' is-dis' : '') + '"><input type="checkbox" data-i="' + i + '" data-c="' + c + '"' + (on ? ' checked' : '') + (dis ? ' disabled' : '') + '> ' + esc(CH[c]) + '</label>';
					}).join('') + '</span><button type="button" class="button-link flxai-rcp-rm" data-rm="' + i + '" aria-label="' + esc(t('Kaldır')) + '">×</button></div>';
			}).join('');
			save();
		}
		function search() {
			var q = inp.value.trim(), my = ++seq;
			fetch(box.getAttribute('data-rest') + (box.getAttribute('data-rest').indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(q) + '&_t=' + Date.now(), { headers: { 'X-WP-Nonce': box.getAttribute('data-nonce') }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); }).then(function (d) {
					if (my !== seq) return;
					if (d.channels) chOk = d.channels;
					found = d.items || [];
					res.hidden = false;
					res.innerHTML = found.length ? found.map(function (r, i) {
						return '<button type="button" data-pick="' + i + '"><b>' + esc(r.name) + '</b><small>' + esc(KIND[r.kind] || '') + (r.sub ? ' · ' + esc(r.sub) : '') + '</small></button>';
					}).join('') : '<div class="flxai-rcp-none">' + esc(t('Sonuç yok.')) + '</div>';
				}).catch(function () {});
		}
		inp.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 220); });
		inp.addEventListener('focus', function () { if (!res.innerHTML) search(); else res.hidden = false; });
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var b = res.querySelector('[data-pick]'); if (b) b.click(); } if (e.key === 'Escape') res.hidden = true; });
		document.addEventListener('click', function (e) { if (!box.contains(e.target)) res.hidden = true; });
		res.addEventListener('click', function (e) {
			var b = e.target.closest('[data-pick]'); if (!b) return;
			var r = Object.assign({}, found[+b.getAttribute('data-pick')]);
			delete r.sub;
			r.channels = r.kind === 'group' ? ['whatsapp'] : (r.email ? ['email'] : (can(r, 'whatsapp') ? ['whatsapp'] : (can(r, 'sms') ? ['sms'] : [])));
			if (!r.channels.length) return;
			if (!list.some(function (x) { return x.kind === r.kind && (x.id ? x.id === r.id : (x.email + x.phone + x.jid) === (r.email + r.phone + r.jid)); })) list.push(r);
			inp.value = ''; res.hidden = true; draw();
		});
		listEl.addEventListener('change', function (e) {
			var c = e.target.getAttribute('data-c'); if (!c) return;
			var r = list[+e.target.getAttribute('data-i')], ch = (r.channels || []).filter(function (x) { return x !== c; });
			if (e.target.checked) ch.push(c);
			if (!ch.length) { e.target.checked = true; return; }
			r.channels = ch; draw();
		});
		listEl.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { list.splice(+b.getAttribute('data-rm'), 1); draw(); } });
		draw();
	}

	/* Görev geçmişi: cevaplar AI sohbetindeki gibi Markdown (güvenli, küçük). */
	function mdInline(s) {
		var codes = [];
		s = s.replace(/`([^`\n]+)`/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
		s = esc(s);
		s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, txt, url) {
			var u = url.replace(/&amp;/g, '&');
			return /^(https?:\/\/|\/|#|mailto:)/i.test(u) ? '<a href="' + esc(u) + '"' + (/^https?:/i.test(u) ? ' target="_blank" rel="noopener"' : '') + '>' + txt + '</a>' : txt;
		});
		s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
		return s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + esc(codes[+i]) + '</code>'; });
	}
	function md(src) {
		var lines = String(src || '').replace(/\r/g, '').split('\n'), out = [], i = 0, li = /^\s*([-*+]|\d+[.)])\s+/;
		while (i < lines.length) {
			var l = lines[i];
			if (/^```/.test(l)) { var b = []; i++; while (i < lines.length && !/^```\s*$/.test(lines[i])) b.push(lines[i++]); i++; out.push('<pre><code>' + esc(b.join('\n')) + '</code></pre>'); continue; }
			if (/^\s*$/.test(l)) { i++; continue; }
			var h = l.match(/^(#{1,4})\s+(.*)$/);
			if (h) { out.push('<h' + h[1].length + '>' + mdInline(h[2]) + '</h' + h[1].length + '>'); i++; continue; }
			if (/^\s*\|.*\|\s*$/.test(l) && i + 1 < lines.length && /^\s*\|?\s*:?-+/.test(lines[i + 1])) {
				var row = function (r) { return r.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return mdInline(c.trim()); }); };
				var tb = '<table><thead><tr><th>' + row(l).join('</th><th>') + '</th></tr></thead><tbody>'; i += 2;
				while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) tb += '<tr><td>' + row(lines[i++]).join('</td><td>') + '</td></tr>';
				out.push(tb + '</tbody></table>'); continue;
			}
			if (li.test(l)) {
				var ol = /^\s*\d+[.)]/.test(l), items = [];
				while (i < lines.length && li.test(lines[i])) items.push('<li>' + mdInline(lines[i++].replace(li, '')) + '</li>');
				out.push((ol ? '<ol>' : '<ul>') + items.join('') + (ol ? '</ol>' : '</ul>')); continue;
			}
			if (/^>\s?/.test(l)) { var q = []; while (i < lines.length && /^>\s?/.test(lines[i])) q.push(lines[i++].replace(/^>\s?/, '')); out.push('<blockquote>' + mdInline(q.join(' ')) + '</blockquote>'); continue; }
			var p = [l]; i++;
			while (i < lines.length && !/^\s*$/.test(lines[i]) && !/^(```|#{1,4}\s|\s*([-*+]|\d+[.)])\s+|>|\s*\|)/.test(lines[i])) p.push(lines[i++]);
			out.push('<p>' + p.map(mdInline).join('<br>') + '</p>');
		}
		return out.join('');
	}
	function mdBlocks() { document.querySelectorAll('.flxai-md[data-md]').forEach(function (el) { el.innerHTML = md(el.getAttribute('data-md')); }); }

	/* Genel bakış sohbet kutusu: gönderince ortak sohbet paneli yeni sohbetle açılır (chat.js window.flxaiChat). */
	function home() {
		var box = document.querySelector('[data-flxai-home]');
		if (!box) return;
		var form = box.querySelector('form'), ta = form.querySelector('textarea'), send = form.querySelector('.flxai-home-send');
		var inp = form.querySelector('[data-file]'), list = form.querySelector('.flxai-home-files'), mic = form.querySelector('[data-mic]');
		var files = [];
		function size() { ta.style.height = 'auto'; ta.style.height = Math.min(260, Math.max(56, ta.scrollHeight)) + 'px'; }
		function upd() { send.disabled = !ta.value.trim() && !files.length; }
		function draw() {
			list.hidden = !files.length;
			list.innerHTML = files.map(function (f, i) {
				var img = /^image\//.test(f.type) && window.URL ? '<img src="' + URL.createObjectURL(f) + '" alt="">' : '<span class="fi">' + esc(f.name.split('.').pop().toUpperCase().slice(0, 4)) + '</span>';
				return '<span class="flxai-hfile">' + img + '<span class="fn">' + esc(f.name) + '</span><button type="button" data-rm="' + i + '" aria-label="×">×</button></span>';
			}).join('');
			upd();
		}
		function add(fl) { Array.prototype.forEach.call(fl || [], function (f) { if (files.length < 5) files.push(f); }); draw(); ta.focus(); }
		form.querySelector('[data-attach]').addEventListener('click', function () { inp.click(); });
		inp.addEventListener('change', function () { add(inp.files); inp.value = ''; });
		list.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { files.splice(+b.getAttribute('data-rm'), 1); draw(); } });
		ta.addEventListener('input', function () { size(); upd(); });
		ta.addEventListener('paste', function (e) { var f = e.clipboardData && e.clipboardData.files; if (f && f.length) { e.preventDefault(); add(f); } });
		ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true })); } });
		form.addEventListener('dragover', function (e) { e.preventDefault(); form.classList.add('is-drop'); });
		form.addEventListener('dragleave', function () { form.classList.remove('is-drop'); });
		form.addEventListener('drop', function (e) { e.preventDefault(); form.classList.remove('is-drop'); if (e.dataTransfer) add(e.dataTransfer.files); });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = ta.value.trim();
			if ((!text && !files.length) || !window.flxaiChat) return;
			if (window.flxaiChat.start(text, files.slice()) !== false) { ta.value = ''; files = []; draw(); size(); }
		});
		box.querySelectorAll('[data-fill]').forEach(function (b) {
			b.addEventListener('click', function () { ta.value = b.getAttribute('data-fill'); size(); upd(); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); });
		});
		function micSetup() {
			if (!mic || !window.flxaiChat || !window.flxaiChat.canDictate || mic.getAttribute('data-on')) return;
			mic.hidden = false; mic.setAttribute('data-on', '1');
			window.flxaiChat.dictate(ta, mic, function () { size(); upd(); });
		}
		micSetup();
		window.addEventListener('load', micSetup);
		size(); upd();
	}

	document.addEventListener('DOMContentLoaded', function () { charts(); credits(); tests(); renderTest(); bodies(); toolsUi(); taskForm(); mdBlocks(); home(); });
})();
