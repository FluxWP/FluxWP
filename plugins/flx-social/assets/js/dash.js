/* Flux Social — paylaşım panosu: özet kutular, günlük paylaşım grafiği (ağ başına), ağ tablosu, takipçiler, en iyi gönderiler, yaklaşanlar, hatalar. */
(function () {
	'use strict';
	var S = window.FLXSM;
	var h = S.h, t = S.t;
	var SVG = 'http://www.w3.org/2000/svg';

	function sv(tag, attrs) {
		var el = document.createElementNS(SVG, tag);
		Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); });
		return el;
	}
	var tip;
	function showTip(e, html) {
		if (!tip) { tip = h('.flxsm-dtip'); document.body.appendChild(tip); }
		S.clear(tip);
		tip.appendChild(html);
		tip.style.display = 'block';
		var x = e.clientX + 14, y = e.clientY + 14;
		if (x + tip.offsetWidth > window.innerWidth - 8) x = e.clientX - tip.offsetWidth - 14;
		if (y + tip.offsetHeight > window.innerHeight - 8) y = e.clientY - tip.offsetHeight - 14;
		tip.style.left = x + 'px';
		tip.style.top = y + 'px';
	}
	S.dashHideTip = function () { if (tip) tip.style.display = 'none'; };

	function tile(icon, label, value, sub, cls) {
		return h('.flxsm-tile' + (cls ? '.' + cls : ''), h('.flxsm-tile-h', S.icon(icon, 15), h('span', label)), h('.flxsm-tile-v', value), sub ? h('.flxsm-tile-s', sub) : null);
	}
	function card(title, body, extra) {
		return h('section.flxsm-card', h('.flxsm-card-h', h('h3', title), extra || null), body);
	}

	/* Günlük paylaşım: ağlara göre yığılmış sütun. */
	function dayChart(byDay, provs) {
		var W = 720, H = 200, P = { l: 28, r: 8, t: 10, b: 22 };
		var n = byDay.length;
		var max = 1;
		byDay.forEach(function (d) { var s = 0; Object.keys(d[1]).forEach(function (k) { s += d[1][k]; }); max = Math.max(max, s); });
		var step = max <= 4 ? 1 : Math.ceil(max / 4);
		max = Math.ceil(max / step) * step;
		var svg = sv('svg', { viewBox: '0 0 ' + W + ' ' + H, class: 'flxsm-chart', role: 'img', 'aria-label': t('Günlük paylaşım') });
		var iw = W - P.l - P.r, ih = H - P.t - P.b;
		for (var g = 0; g <= max; g += step) {
			var y = P.t + ih - (g / max) * ih;
			svg.appendChild(sv('line', { x1: P.l, x2: W - P.r, y1: y, y2: y, class: 'flxsm-grid-l' }));
			var tx = sv('text', { x: P.l - 6, y: y + 3, 'text-anchor': 'end', class: 'flxsm-axis' });
			tx.textContent = g;
			svg.appendChild(tx);
		}
		var bw = iw / n;
		var gap = Math.min(4, bw * 0.25);
		byDay.forEach(function (d, i) {
			var x = P.l + i * bw;
			var yb = P.t + ih;
			var keys = Object.keys(d[1]);
			keys.forEach(function (k) {
				var hh = (d[1][k] / max) * ih;
				yb -= hh;
				svg.appendChild(sv('rect', { x: x + gap / 2, y: yb, width: Math.max(1, bw - gap), height: hh, rx: 2, fill: S.netColor(k) }));
			});
			if (n <= 31 ? (i % Math.ceil(n / 8) === 0) : (i % 15 === 0)) {
				var lb = sv('text', { x: x + bw / 2, y: H - 6, 'text-anchor': 'middle', class: 'flxsm-axis' });
				var dt = d[0].split('-');
				lb.textContent = +dt[2] + '.' + dt[1];
				svg.appendChild(lb);
			}
			var hit = sv('rect', { x: x, y: P.t, width: bw, height: ih, class: 'flxsm-hit' });
			hit.addEventListener('mousemove', function (e) {
				var tot = 0;
				var rows = keys.map(function (k) { tot += d[1][k]; return h('div', h('span.flxsm-legend-i', { style: { background: S.netColor(k) } }), ' ', (provs[k] && provs[k].label) || k, ': ', d[1][k]); });
				var dt2 = d[0].split('-');
				showTip(e, h('div', h('strong', new Date(Date.UTC(+dt2[0], +dt2[1] - 1, +dt2[2])).toLocaleDateString(S.locale, { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short' })), tot ? rows : h('div', t('Paylaşım yok')), tot > 1 ? h('div', h('b', t('Toplam: {n}', { n: tot }))) : null));
			});
			hit.addEventListener('mouseleave', S.dashHideTip);
			svg.appendChild(hit);
		});
		return svg;
	}
	function spark(series) {
		if (!series || series.length < 2) return null;
		var W = 90, H = 26;
		var vals = series.map(function (s) { return s[1]; });
		var mn = Math.min.apply(null, vals), mx = Math.max.apply(null, vals);
		var rng = mx - mn || 1;
		var pts = vals.map(function (v, i) { return (i / (vals.length - 1) * W).toFixed(1) + ',' + (H - 2 - (v - mn) / rng * (H - 4)).toFixed(1); }).join(' ');
		var svg = sv('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, class: 'flxsm-spark' });
		svg.appendChild(sv('polyline', { points: pts, fill: 'none', stroke: 'currentColor', 'stroke-width': 1.6, 'stroke-linejoin': 'round' }));
		return svg;
	}

	S.dashView = function (el) {
		var b = S.st.boot;
		var days = S.pref('dashDays') || 30;
		var seg = h('.flxsm-seg.is-small');
		[7, 30, 90].forEach(function (d) {
			seg.appendChild(h('button', { type: 'button', class: 'flxsm-seg-b' + (days === d ? ' is-on' : ''), onclick: function (e) {
				days = d; S.pref('dashDays', d);
				Array.prototype.forEach.call(seg.children, function (c) { c.classList.toggle('is-on', c === e.currentTarget); });
				load();
			} }, t('{n} gün', { n: d })));
		});
		var refreshBtn = b.user.manage ? S.iconBtn('refresh', t('Metrikleri şimdi güncelle'), function (e) {
			var btn = e.currentTarget;
			btn.classList.add('is-busy');
			S.api('POST', 'stats/refresh').then(function (r) { btn.classList.remove('is-busy'); S.toast(t('{n} kayıt güncellendi.', { n: r.updated })); load(); }, function (er) { btn.classList.remove('is-busy'); S.fail(er); });
		}) : null;
		S.setTop(t('Pano'), [seg, refreshBtn]);
		var box = h('.flxsm-dash');
		el.appendChild(box);

		function load() {
			S.clear(box).appendChild(h('.flxsm-boot', h('.flxsm-spin')));
			S.api('GET', 'stats?days=' + days).then(draw, function (e) { S.clear(box).appendChild(h('.flxsm-empty', S.icon('alert', 24), h('p', e.message))); });
		}
		function draw(d) {
			S.clear(box);
			var T = d.totals;
			if (!b.accounts.length) {
				box.appendChild(h('.flxsm-banner', S.icon('users', 16), h('span', t('Henüz hesap bağlı değil. Önce uygulama anahtarlarını girip hesap bağlayın.')), h('a.flxsm-btn.is-ghost', { href: '#/accounts' }, t('Hesaplar'))));
			}
			box.appendChild(h('.flxsm-tiles',
				tile('send', t('Yayınlanan'), S.num(T.published), T.success !== null ? t('Başarı %{n}', { n: T.success }) : t('son {n} gün', { n: d.days })),
				tile('heart', t('Etkileşim'), S.num(T.engagement), t('beğeni + yorum + paylaşım')),
				tile('eye', t('Görüntülenme'), S.num(T.views), t('erişim / gösterim')),
				tile('users', t('Takipçi'), S.num(T.followers), T.followers_delta ? (T.followers_delta > 0 ? '+' : '') + S.num(T.followers_delta) + ' ' + t('bu dönemde') : t('değişim yok'), T.followers_delta > 0 ? 'is-up' : ''),
				tile('clock', t('Zamanlanmış'), S.num(T.scheduled), T.pending ? t('{n} onay bekliyor', { n: T.pending }) : T.drafts ? t('{n} taslak', { n: T.drafts }) : ''),
				tile('alert', t('Hatalı'), S.num(T.failed), T.failed ? t('aşağıda ayrıntı') : t('sorun yok'), T.failed ? 'is-bad' : '')
			));

			var nets = {};
			d.by_day.forEach(function (x) { Object.keys(x[1]).forEach(function (k) { nets[k] = 1; }); });
			var legend = h('.flxsm-legend', Object.keys(nets).map(function (k) { return h('span', h('i', { style: { background: S.netColor(k) } }), (b.providers[k] && b.providers[k].label) || k); }));
			box.appendChild(card(t('Günlük paylaşım'), dayChart(d.by_day, b.providers), legend));

			var g = h('.flxsm-dgrid');
			/* Ağ başına */
			if (d.by_net.length) {
				var tb = h('table.flxsm-dtable', h('thead', h('tr', h('th', t('Ağ')), h('th.is-num', t('Paylaşım')), h('th.is-num', t('Hata')), h('th.is-num', t('Etkileşim')), h('th.is-num', t('Görüntülenme')))));
				var tbody = h('tbody');
				var maxE = Math.max.apply(null, d.by_net.map(function (x) { return x.engagement; }).concat([1]));
				d.by_net.sort(function (a, c) { return c.published - a.published; }).forEach(function (x) {
					tbody.appendChild(h('tr',
						h('td', h('.flxsm-dcell', h('span', S.net(x.provider, 16), (b.providers[x.provider] && b.providers[x.provider].label) || x.provider), h('.flxsm-dbar', h('i', { style: { width: (x.engagement / maxE * 100) + '%', background: S.netColor(x.provider) } })))),
						h('td.is-num', S.num(x.published)), h('td.is-num' + (x.failed ? '.flxsm-errc' : ''), S.num(x.failed)), h('td.is-num', S.num(x.engagement)), h('td.is-num', S.num(x.views))));
				});
				tb.appendChild(tbody);
				g.appendChild(card(t('Ağlara göre'), tb));
			} else {
				g.appendChild(card(t('Ağlara göre'), h('p.flxsm-muted.flxsm-dempty', t('Bu dönemde paylaşım yok.'))));
			}
			/* Takipçiler */
			var fl = h('.flxsm-flist');
			d.followers.sort(function (a, c) { return c.now - a.now; }).forEach(function (f) {
				var a = S.account(f.account_id) || { name: f.name, provider: f.provider, avatar: f.avatar };
				fl.appendChild(h('.flxsm-frow', S.avatar(a, 28), h('span.flxsm-frow-n', a.name), h('span.flxsm-spark-w', { style: { color: S.netColor(f.provider) } }, spark(f.series)), h('strong', S.num(f.now)), h('small.' + (f.delta > 0 ? 'flxsm-up' : f.delta < 0 ? 'flxsm-down' : 'flxsm-muted'), (f.delta > 0 ? '+' : '') + S.num(f.delta))));
			});
			g.appendChild(card(t('Takipçiler'), d.followers.length ? fl : h('p.flxsm-muted.flxsm-dempty', t('Takipçi sayıları günde bir toplanır; ilk veri yarın görünür.'))));
			box.appendChild(g);

			var g2 = h('.flxsm-dgrid');
			/* En iyi gönderiler */
			var top = h('ol.flxsm-best');
			d.top.forEach(function (x) {
				var m = x.metrics || {};
				top.appendChild(h('li', { onclick: function () { S.postDetail(x.post_id, load); } },
					S.net(x.provider, 18),
					h('.flxsm-best-b', h('span.flxsm-best-t', x.title || t('(metinsiz)')), h('small.flxsm-muted', x.account + ' · ' + S.fmtDay(x.at))),
					h('.flxsm-best-m', m.likes ? h('span', S.icon('heart', 12), S.num(m.likes)) : null, m.comments ? h('span', S.icon('msg', 12), S.num(m.comments)) : null, x.views ? h('span', S.icon('eye', 12), S.num(x.views)) : null)));
			});
			g2.appendChild(card(t('En çok etkileşim alanlar'), d.top.length ? top : h('p.flxsm-muted.flxsm-dempty', t('Metrikler gönderi yayınlandıktan sonra günde iki kez toplanır.'))));
			/* Yaklaşanlar */
			var up = h('.flxsm-uplist');
			d.upcoming.forEach(function (p) {
				up.appendChild(h('.flxsm-uprow', { onclick: function () { S.postDetail(p.id, load); } }, h('span.flxsm-up-t', S.fmt(p.scheduled_at)), h('span.flxsm-up-x', S.postText(p)), S.targetsStrip(p, 20)));
			});
			g2.appendChild(card(t('Yaklaşan paylaşımlar'), d.upcoming.length ? up : h('.flxsm-dempty', h('p.flxsm-muted', t('Zamanlanmış gönderi yok.')), S.btn('plus', t('Yeni gönderi'), function () { S.compose(null, { onSaved: load }); }, 'is-primary')), h('a.flxsm-link', { href: '#/calendar' }, t('Takvim'))));
			box.appendChild(g2);

			if (d.errors.length) {
				var er = h('ul.flxsm-derr');
				d.errors.forEach(function (x) {
					er.appendChild(h('li', { onclick: function () { S.postDetail(x.post_id, load); } }, h('.flxsm-derr-h', S.net(x.provider, 15), h('strong', x.account), h('small.flxsm-muted', S.fmt(x.at))), h('p', x.error)));
				});
				box.appendChild(card(t('Son hatalar'), er, h('a.flxsm-link', { href: '#/posts?status=failed,partial' }, t('Hepsi'))));
			}
			box.appendChild(h('p.flxsm-muted.is-small.flxsm-dfoot', d.last_metrics ? t('Metrikler son güncelleme: {t}', { t: S.fmt(d.last_metrics) }) : t('Metrikler henüz toplanmadı (günde bir, gece).')));
		}
		S.onTick = load;
		load();
	};
})();
