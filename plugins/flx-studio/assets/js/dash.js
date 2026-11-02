/* Flux Studio — yönetici panosu: üretimler, harcama, modeller, sağlayıcılar, kişiler. Veri: GET stats (Flux AI kayıtları + Stüdyo). */
(function () {
	'use strict';
	var S = window.FLXST;
	var h = S.h, t = S.t;
	var SVG = 'http://www.w3.org/2000/svg';

	function svg(tag, attrs) {
		var el = document.createElementNS(SVG, tag);
		Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); });
		return el;
	}
	/* Büyük tutarlarda 2 basamak yeter ($41,72); küçükte S.money'nin 3 basamağı. */
	function money(v) {
		return v >= 10 ? '$' + v.toLocaleString(S.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : S.money(v);
	}
	function num(n) { return (n || 0).toLocaleString(S.locale); }
	function bytes(b) {
		if (!b) return '0 MB';
		if (b >= 1073741824) return (b / 1073741824).toLocaleString(S.locale, { maximumFractionDigits: 1 }) + ' GB';
		return Math.max(1, Math.round(b / 1048576)).toLocaleString(S.locale) + ' MB';
	}
	function dayLabel(iso, long) {
		var d = new Date(iso + 'T12:00:00');
		return d.toLocaleDateString(S.locale, long ? { day: 'numeric', month: 'long', weekday: 'short' } : { day: 'numeric', month: 'short' });
	}

	/* Tek ipucu kutusu (grafiklerin üzerinde gezinince). */
	var tip;
	function showTip(e, lines) {
		if (!tip) tip = h('.flxst-dtip');
		S.layer().appendChild(tip);
		S.clear(tip);
		lines.forEach(function (l, i) { tip.appendChild(h(i ? 'div' : 'strong', l)); });
		var x = e.clientX + 14, y = e.clientY + 14;
		tip.style.left = Math.min(x, window.innerWidth - tip.offsetWidth - 8) + 'px';
		tip.style.top = Math.min(y, window.innerHeight - tip.offsetHeight - 8) + 'px';
	}
	function hideTip() { if (tip) tip.remove(); }

	/* Günlük sütun grafiği. series: [{key, label, cls}] üst üste; fmt: değer biçimi. */
	function bars(daily, series, fmt) {
		var W = 640, H = 168, PL = 36, PB = 22, PT = 8;
		var n = daily.length, max = 0;
		daily.forEach(function (d) {
			var s = 0;
			series.forEach(function (x) { s += d[x.key] || 0; });
			max = Math.max(max, s);
		});
		var step = niceStep(max);
		var top = Math.max(step, Math.ceil(max / step) * step);
		var el = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, class: 'flxst-chart', role: 'img' });
		var ih = H - PB - PT, iw = W - PL;
		for (var g = 0; g <= top + 1e-9; g += step) {
			var y = PT + ih - (g / top) * ih;
			el.appendChild(svg('line', { x1: PL, x2: W, y1: y, y2: y, class: 'flxst-grid-l' }));
			var tx = svg('text', { x: PL - 6, y: y + 3.5, class: 'flxst-axis', 'text-anchor': 'end' });
			tx.textContent = fmt(g, true);
			el.appendChild(tx);
		}
		var bw = iw / n, gap = Math.max(2, bw * 0.22), w = Math.max(2, bw - gap);
		var every = Math.ceil(n / 8);
		daily.forEach(function (d, i) {
			var x = PL + i * bw + gap / 2, base = PT + ih;
			series.forEach(function (s, si) {
				var v = d[s.key] || 0;
				if (!v) return;
				var hgt = Math.max(1.5, (v / top) * ih);
				// Üst üste parçalar arasında 2px yüzey boşluğu; en üstteki ucu yuvarlak.
				var r = svg('rect', { x: x, y: base - hgt, width: w, height: Math.max(1, hgt - (si ? 2 : 0)), rx: Math.min(3, w / 2), class: 'flxst-bar-' + s.cls });
				el.appendChild(r);
				base -= hgt;
			});
			if (i % every === 0 || (i === n - 1 && i % every >= every / 2)) {
				var lx = svg('text', { x: x + w / 2, y: H - 6, class: 'flxst-axis', 'text-anchor': 'middle' });
				lx.textContent = dayLabel(d.date);
				el.appendChild(lx);
			}
			// Gezinme alanı sütundan geniş.
			var hit = svg('rect', { x: PL + i * bw, y: PT, width: bw, height: ih, class: 'flxst-hit' });
			hit.addEventListener('mousemove', function (e) {
				showTip(e, [dayLabel(d.date, true)].concat(series.map(function (s) { return s.label + ': ' + fmt(d[s.key] || 0); })));
			});
			hit.addEventListener('mouseleave', hideTip);
			el.appendChild(hit);
		});
		return el;
	}
	function niceStep(max) {
		if (max <= 0) return 1;
		var raw = max / 4, p = Math.pow(10, Math.floor(Math.log10(raw))), m = raw / p;
		return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 5 ? 5 : 10) * p;
	}

	function tile(label, value, sub, icon) {
		return h('.flxst-tile', h('.flxst-tile-h', S.icon(icon, 15), h('span', label)), h('.flxst-tile-v', value), sub ? h('.flxst-tile-s', sub) : null);
	}
	function card(title, body, extra) {
		return h('section.flxst-card', h('.flxst-card-h', h('h3', title), extra || null), body);
	}
	/* Satır tablosu; ilk sayısal sütun yatay çubukla gösterilir. */
	function table(cols, rows, barKey) {
		var max = 0;
		rows.forEach(function (r) { max = Math.max(max, r[barKey] || 0); });
		var tb = h('table.flxst-dtable', h('thead', h('tr', cols.map(function (c) { return h('th' + (c.num ? '.is-num' : ''), c.label); }))));
		var body = h('tbody');
		rows.forEach(function (r) {
			body.appendChild(h('tr', cols.map(function (c, i) {
				var v = c.get(r);
				if (i === 0 && barKey) return h('td', h('.flxst-dcell', v, h('.flxst-dbar', h('i', { style: { width: (max ? (r[barKey] || 0) / max * 100 : 0) + '%' } }))));
				return h('td' + (c.num ? '.is-num' : ''), v);
			})));
		});
		tb.appendChild(body);
		return tb;
	}
	function empty(msg) { return h('p.flxst-muted.flxst-dempty', msg); }

	/* Panoyu el içine çizer. */
	S.dash = function (el) {
		var days = +(S.pref('dashDays') || 30);
		function draw() {
			S.clear(el).appendChild(h('.flxst-boot', h('.flxst-spin')));
			S.api('GET', 'stats?days=' + days).then(render, function (e) {
				S.clear(el).appendChild(h('.flxst-empty', S.icon('alert', 28), h('p', e.message), h('button.flxst-btn', { type: 'button', onclick: draw }, t('Yeniden dene'))));
			});
		}
		function render(d) {
			S.clear(el);
			var seg = h('.flxst-seg.is-small');
			[[7, t('7 gün')], [30, t('30 gün')], [90, t('90 gün')]].forEach(function (o) {
				seg.appendChild(h('button', { type: 'button', class: 'flxst-seg-b' + (days === o[0] ? ' is-on' : ''), onclick: function () { days = o[0]; S.pref('dashDays', days); draw(); } }, o[1]));
			});
			el.appendChild(h('.flxst-dhead', seg, h('span.flxst-muted.is-small', d.spend.from === 'flx-ai' ? t('Harcama Flux AI kayıtlarından') : t('Flux AI kayıtları okunamadı; Stüdyo tutarları')),
				S.iconBtn('repeat', t('Yenile'), draw)));

			var T = d.totals, out = T.image + T.video, tried = out + T.failed;
			var est = d.spend.est ? ' ' + t('(tahmini)') : '';
			el.appendChild(h('.flxst-tiles',
				tile(t('Üretim'), num(out), t('{i} görsel · {v} video', { i: num(T.image), v: num(T.video) }), 'sparkle'),
				tile(t('Harcama'), money(d.spend.total), t('Görsel/video {m} · metin {x}', { m: money(d.spend.media), x: money(d.spend.text) }) + est, 'chart'),
				tile(t('Ortalama'), out ? S.money(d.spend.media / out) : '—', t('üretim başına'), 'layers'),
				tile(t('Başarısız'), num(T.failed), tried ? t('%{p} hata oranı', { p: Math.round(T.failed / tried * 100) }) : t('henüz deneme yok'), 'alert'),
				tile(t('Kişiler'), num(T.users), t('Depolama {b}', { b: bytes(T.bytes) }), 'users')
			));

			var bud = d.budget ? Object.keys(d.budget).filter(function (k) { return d.budget[k].limit > 0; }) : [];
			var legend = h('.flxst-legend', h('span', h('i.flxst-bar-img'), t('Görsel')), h('span', h('i.flxst-bar-vid'), t('Video')), T.failed ? h('span', h('i.flxst-bar-err'), t('Başarısız')) : null);
			var hasAny = d.daily.some(function (x) { return x.image || x.video || x.failed; });
			el.appendChild(h('.flxst-dgrid',
				card(t('Günlük üretim'), hasAny ? bars(d.daily, [{ key: 'image', label: t('Görsel'), cls: 'img' }, { key: 'video', label: t('Video'), cls: 'vid' }, { key: 'failed', label: t('Başarısız'), cls: 'err' }], function (v) { return num(v); }) : empty(t('Bu dönemde üretim yok.')), legend),
				card(t('Günlük harcama'), d.spend.total > 0 ? bars(d.daily, [{ key: 'cost', label: t('Harcama'), cls: 'cost' }], function (v, axis) { return axis ? '$' + (Math.round(v * 100) / 100) : S.money(v); }) : empty(t('Bu dönemde harcama yok.'))),
				bud.length ? card(t('Flux AI harcama sınırı'), h('.flxst-dbudget', bud.map(function (k) {
					var x = d.budget[k];
					return h('.flxst-budget' + (x.over ? '.is-over' : ''),
						h('.flxst-budget-t', h('span', x.label), h('span', S.money(x.spent) + ' / ' + S.money(x.limit))),
						h('.flxst-bar', h('i', { style: { width: Math.min(100, x.pct) + '%' } })));
				})), d.log_url ? h('a.flxst-link', { href: d.log_url }, t('Flux AI kayıtları')) : null) : null
			));

			el.appendChild(h('.flxst-dgrid.is-3',
				card(t('Modeller'), d.models.length ? table([
					{ label: t('Model'), get: function (r) { return h('span', S.icon(r.kind === 'video' ? 'video' : 'image', 13), ' ', r.label); } },
					{ label: t('Üretim'), num: true, get: function (r) { return num(r.done); } },
					{ label: t('Hata'), num: true, get: function (r) { return r.failed ? num(r.failed) : '—'; } },
					{ label: t('Tutar'), num: true, get: function (r) { return money(r.cost); } }
				], d.models, 'done') : empty(t('Kayıt yok.'))),
				card(t('Sağlayıcılar'), d.providers.length ? table([
					{ label: t('Sağlayıcı'), get: function (r) { return r.label; } },
					{ label: t('Üretim'), num: true, get: function (r) { return num(r.done); } },
					{ label: t('Hata'), num: true, get: function (r) { return r.failed ? num(r.failed) : '—'; } },
					{ label: t('Tutar'), num: true, get: function (r) { return money(r.cost); } }
				], d.providers, 'done') : empty(t('Kayıt yok.'))),
				card(t('Kişiler'), d.users.length ? table([
					{ label: t('Kişi'), get: function (r) { return h('span.flxst-duser', h('img', { src: r.avatar, alt: '' }), h('span', r.name)); } },
					{ label: t('Görsel'), num: true, get: function (r) { return num(r.image); } },
					{ label: t('Video'), num: true, get: function (r) { return num(r.video); } },
					{ label: t('Tutar'), num: true, get: function (r) { return money(r.cost); } }
				], d.users.map(function (u) { u.total = u.image + u.video; return u; }), 'total') : empty(t('Kayıt yok.')))
			));

			if (d.errors.length) {
				el.appendChild(card(t('Son hatalar'), h('ul.flxst-derr', d.errors.map(function (e) {
					return h('li', h('.flxst-derr-h', h('strong', e.model), e.provider ? h('span.flxst-muted', ' · ' + e.provider) : null, h('small.flxst-muted', S.ago(e.created))), h('p', e.error));
				}))));
			}
		}
		draw();
		return { refresh: draw };
	};
	S.dashHideTip = hideTip;
})();
