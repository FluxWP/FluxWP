/* Flux Speed paneli: veri #flxp-data, ayar #flxp-cfg, çeviri #flxp-i18n (JSON). Bağımlılık yok. */
(function () {
	'use strict';

	function readJSON(id) {
		var el = document.getElementById(id);
		if (!el) return null;
		try { return JSON.parse(el.textContent); } catch (e) { return null; }
	}
	var D = readJSON('flxp-data');
	var C = readJSON('flxp-cfg') || {};
	var I = readJSON('flxp-i18n') || {};
	var root = document.getElementById('flxp-panel');
	if (!D || !root) return;

	function t(s) { return I[s] || s; }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmtMs(n) {
		n = +n || 0;
		if (n >= 1000) return (n / 1000).toFixed(2) + ' s';
		return (n < 10 ? n.toFixed(2) : Math.round(n)) + ' ms';
	}
	function fmtKb(n) {
		n = +n || 0;
		return n >= 1024 ? (n / 1024).toFixed(1) + ' MB' : Math.round(n) + ' KB';
	}
	function fmtNum(n) { return (+n || 0).toLocaleString(); }
	function compKey(c) { return c ? c.type + ':' + c.slug : ''; }
	function compLabel(c) { return c ? ((C.labels || {})[compKey(c)] || c.slug) : ''; }
	function lsGet(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
	function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* özel pencere */ } }

	var O = D.overview || {};
	var DB = D.db || {};

	/* ------------------------------------------------------------ Tablo */

	/**
	 * cols: [{k, label, num, filter, render(row) -> html, val(row) -> değer, cls(row)}]
	 */
	function table(el, opt) {
		var rows = opt.rows || [];
		var cols = opt.cols;
		var state = { q: '', f: {}, sort: opt.sort || null, dir: opt.dir || -1 };
		var bar = document.createElement('div');
		bar.className = 'flxp-tools';
		var html = '<input type="search" class="flxp-search" placeholder="' + esc(t('Ara…')) + '">';
		cols.forEach(function (c, i) {
			if (!c.filter) return;
			var seen = {};
			rows.forEach(function (r) { var v = c.val(r); if (v !== '' && v != null) seen[v] = (seen[v] || 0) + 1; });
			var keys = Object.keys(seen).sort();
			if (keys.length < 2) return;
			html += '<select data-col="' + i + '"><option value="">' + esc(c.label) + ': ' + esc(t('Tümü')) + '</option>' +
				keys.map(function (k) { return '<option value="' + esc(k) + '">' + esc(c.fmt ? c.fmt(k) : k) + ' (' + seen[k] + ')</option>'; }).join('') + '</select>';
		});
		html += '<span class="flxp-count"></span>';
		bar.innerHTML = html;
		var wrap = document.createElement('div');
		wrap.className = 'flxp-tw';
		el.appendChild(bar);
		el.appendChild(wrap);

		function draw() {
			var q = state.q.toLowerCase();
			var list = rows.filter(function (r) {
				for (var k in state.f) {
					if (state.f[k] !== '' && String(cols[k].val(r)) !== state.f[k]) return false;
				}
				if (!q) return true;
				return cols.some(function (c) { return String(c.val(r)).toLowerCase().indexOf(q) > -1; });
			});
			if (state.sort !== null) {
				var c = cols[state.sort];
				list = list.slice().sort(function (a, b) {
					var x = c.val(a), y = c.val(b);
					if (c.num) return ((+x || 0) - (+y || 0)) * state.dir;
					return String(x).localeCompare(String(y)) * state.dir;
				});
			}
			var total = list.length;
			var more = total > 1500;
			if (more) list = list.slice(0, 1500);
			var h = '<table class="flxp-t"><thead><tr>' + cols.map(function (c, i) {
				var s = state.sort === i ? (state.dir > 0 ? ' ▲' : ' ▼') : '';
				return '<th' + (c.num ? ' class="n"' : '') + ' data-i="' + i + '"' + (c.num || c.sortable ? ' role="button" tabindex="0"' : '') + '>' + esc(c.label) + s + '</th>';
			}).join('') + '</tr></thead><tbody>';
			if (!total) {
				h += '<tr><td colspan="' + cols.length + '" class="flxp-empty">' + esc(t('Kayıt yok.')) + '</td></tr>';
			}
			list.forEach(function (r) {
				var cls = opt.rowCls ? opt.rowCls(r) : '';
				h += '<tr' + (cls ? ' class="' + cls + '"' : '') + '>' + cols.map(function (c) {
					return '<td' + (c.num ? ' class="n"' : '') + ' data-l="' + esc(c.label) + '">' + (c.render ? c.render(r) : esc(c.val(r))) + '</td>';
				}).join('') + '</tr>';
			});
			h += '</tbody></table>';
			if (more) h += '<p class="flxp-note">' + esc(t('İlk 1500 satır gösteriliyor; aramayla daraltın.')) + '</p>';
			wrap.innerHTML = h;
			bar.querySelector('.flxp-count').textContent = total === rows.length ? fmtNum(total) : fmtNum(total) + ' / ' + fmtNum(rows.length);
		}
		bar.addEventListener('input', function (e) {
			if (e.target.classList.contains('flxp-search')) state.q = e.target.value;
			else if (e.target.dataset.col) state.f[e.target.dataset.col] = e.target.value;
			draw();
		});
		bar.addEventListener('change', function (e) {
			if (e.target.dataset.col) { state.f[e.target.dataset.col] = e.target.value; draw(); }
		});
		wrap.addEventListener('click', function (e) {
			var th = e.target.closest('th[role=button]');
			if (th) {
				var i = +th.dataset.i;
				if (state.sort === i) state.dir = -state.dir; else { state.sort = i; state.dir = cols[i].num ? -1 : 1; }
				draw();
				return;
			}
			var tg = e.target.closest('.flxp-toggle');
			if (tg) {
				var d = tg.nextElementSibling;
				if (d) d.hidden = !d.hidden;
			}
		});
		draw();
	}

	function stackHtml(stack) {
		if (!stack || !stack.length) return '';
		return '<button type="button" class="flxp-toggle">' + esc(t('Çağrı yığını')) + '</button><ol class="flxp-stack" hidden>' +
			stack.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ol>';
	}
	function kv(pairs) {
		return '<table class="flxp-t flxp-kv"><tbody>' + pairs.filter(function (p) { return p[1] !== undefined; }).map(function (p) {
			return '<tr><th>' + esc(p[0]) + '</th><td>' + (p[2] ? p[1] : esc(p[1] === '' || p[1] === null ? '—' : p[1])) + '</td></tr>';
		}).join('') + '</tbody></table>';
	}
	function h3(s) { return '<h3>' + esc(s) + '</h3>'; }
	function compCol(label) {
		return { k: 'comp', label: label || t('Bileşen'), filter: true, val: function (r) { return compLabel(r.comp); } };
	}
	var LEVELS = { fatal: t('Ölümcül'), warning: t('Uyarı'), notice: t('Bildirim'), deprecated: t('Eskimiş') };
	var TYPES = { front: t('Ön yüz'), admin: t('Yönetim'), ajax: 'Ajax', rest: 'REST', cron: 'Cron', xmlrpc: 'XML-RPC' };

	/* ------------------------------------------------------------ Bölümler */

	var phpErrors = (D.php || []).filter(function (e) { return e.level === 'fatal' || e.level === 'warning'; }).length;
	var httpFail = (D.http || []).filter(function (h) { return h.error || h.status >= 400; }).length;

	var missingAssets = [].concat((D.assets || {}).scripts || [], (D.assets || {}).styles || []).filter(function (a) { return a.missing && a.missing.length; }).length;
	var langMissing = (D.lang || []).filter(function (l) { return !l.found; }).length;
	var logErr = (D.logs || []).filter(function (l) { return ['emergency', 'alert', 'critical', 'error'].indexOf(l.level) > -1; }).length;

	var sections = [
		{ id: 'overview', label: t('Genel bakış'), render: renderOverview },
		{ id: 'queries', label: t('Sorgular'), n: DB.detailed ? (DB.queries || []).length : O.queries, warn: DB.slow || DB.errors, render: renderQueries },
		{ id: 'dupes', label: t('Tekrar eden sorgular'), n: (DB.dupes || []).length, hide: !DB.detailed, render: renderDupes },
		{ id: 'components', label: t('Bileşenlere göre'), hide: !DB.detailed, render: renderComponents },
		{ id: 'php', label: t('PHP hataları'), n: (D.php || []).length, err: phpErrors, render: renderPhp },
		{ id: 'wrong', label: t('Yanlış kullanım'), n: (D.wrong || []).length, warn: (D.wrong || []).length, render: renderWrong },
		{ id: 'cron', label: t('Cron görevleri'), n: (D.cron || []).length, warn: (D.cron || []).filter(function (c) { return c.cut; }).length, hide: !(D.cron || []).length, render: renderCron },
		{ id: 'http', label: t('Dış istekler'), n: (D.http || []).length, err: httpFail, render: renderHttp },
		{ id: 'logs', label: t('Loglar'), n: (D.logs || []).length + (D.timers || []).length, err: logErr, render: renderLogs },
		{ id: 'template', label: t('Şablon'), hide: !D.template || !D.template.file && !D.template.theme, render: renderTemplate },
		{ id: 'request', label: t('İstek'), render: renderRequest },
		{ id: 'caps', label: t('Yetki kontrolleri'), n: (D.caps || []).length, hide: !(D.caps || []).length, render: renderCaps },
		{ id: 'assets', label: t('Script ve stiller'), n: ((D.assets || {}).scripts || []).length + ((D.assets || {}).styles || []).length, err: missingAssets, hide: !((D.assets || {}).scripts || []).length && !((D.assets || {}).styles || []).length, render: renderAssets },
		{ id: 'lang', label: t('Diller'), n: (D.lang || []).length, hide: !(D.lang || []).length, render: renderLang },
		{ id: 'transients', label: t('Transient güncellemeleri'), n: (D.transients || []).length, hide: !(D.transients || []).length, render: renderTransients },
		{ id: 'hooks', label: t('Hook\'lar'), n: (D.hooks || {}).total, render: renderHooks }
	].filter(function (s) { return !s.hide; });

	function card(label, value, sub, cls) {
		return '<div class="flxp-card' + (cls ? ' ' + cls : '') + '"><span>' + esc(label) + '</span><b>' + esc(value) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</div>';
	}

	function renderOverview(el) {
		var slow = O.ms >= (C.slowMs || 1500);
		var h = '<div class="flxp-cards">' +
			card(t('Süre'), fmtMs(O.ms), slow ? t('Yavaş') : '', slow ? 'warn' : '') +
			card(t('En yüksek bellek'), fmtKb(O.mem_kb), t('Sınır') + ': ' + (O.mem_limit || '—')) +
			card(t('Sorgular'), fmtNum(O.queries), DB.detailed ? fmtMs(O.query_ms) : t('Süre ölçülmedi'), DB.errors ? 'err' : (DB.slow ? 'warn' : '')) +
			card(t('Dış istekler'), fmtNum(O.http), O.http ? fmtMs(O.http_ms) : '', O.http_fail ? 'err' : '') +
			card(t('PHP hataları'), fmtNum(O.errors), O.notices ? t('Toplam') + ': ' + fmtNum(O.notices) : '', O.fatal || O.errors ? 'err' : '') +
			card(t('Yanıt'), O.status || '—', (TYPES[O.type] || O.type) + ' · ' + String(O.method || '').toUpperCase(), O.status >= 500 ? 'err' : '') +
			'</div>';
		if (!DB.detailed) {
			h += '<p class="flxp-note">' + esc(t('Bu istekte sorgu ayrıntısı toplanmadı (örneklenmeyen istek). Sorgu listesi için sayfayı yönetici girişiyle açın ya da örnekleme oranını artırın.')) + '</p>';
		}
		var ms = D.milestones || [];
		if (ms.length) {
			var max = Math.max(O.ms || 1, ms[ms.length - 1].ms || 1);
			h += h3(t('Zaman çizelgesi'));
			h += '<div class="flxp-tl">' + ms.map(function (m) {
				return '<div class="flxp-tl-r"><span class="flxp-tl-n">' + esc(m.hook) + '</span><span class="flxp-tl-b"><i style="width:' + Math.max(0.5, Math.min(100, m.ms / max * 100)).toFixed(1) + '%"></i></span><span class="flxp-tl-v">' + fmtMs(m.ms) + ' · ' + fmtKb(m.mem_kb) + '</span></div>';
			}).join('') + '</div>';
		}
		h += h3(t('İstek'));
		h += kv([
			[t('Adres'), O.url],
			[t('Tür'), TYPES[O.type] || O.type],
			[t('Kullanıcı'), O.user ? '#' + O.user : t('Ziyaretçi')],
			[t('Toplama kipi'), O.mode === 'full' ? t('Ayrıntılı') : t('Hafif')],
			['PHP / WordPress', (O.php || '') + ' / ' + (O.wp || '')]
		]);
		el.innerHTML = h;
	}

	function renderQueries(el) {
		if (!DB.detailed) {
			el.innerHTML = '<p class="flxp-note">' + esc(t('Bu istekte sorgu ayrıntısı toplanmadı (örneklenmeyen istek). Sorgu listesi için sayfayı yönetici girişiyle açın ya da örnekleme oranını artırın.')) + '</p>';
			return;
		}
		var slowQ = C.slowQ || 100;
		var info = document.createElement('div');
		info.className = 'flxp-cards';
		info.innerHTML = card(t('Toplam'), fmtNum(DB.total), fmtMs(DB.ms)) +
			card(t('Yavaş'), fmtNum(DB.slow), '≥ ' + slowQ + ' ms', DB.slow ? 'warn' : '') +
			card(t('Hatalı'), fmtNum(DB.errors), '', DB.errors ? 'err' : '') +
			card(t('Tekrar eden'), fmtNum((DB.dupes || []).length), '', (DB.dupes || []).length ? 'warn' : '');
		el.appendChild(info);
		if (DB.trimmed) {
			var p = document.createElement('p');
			p.className = 'flxp-note';
			p.textContent = t('Kayıtta yalnız en yavaş sorgular saklandı.');
			el.appendChild(p);
		}
		table(el, {
			rows: DB.queries || [],
			rowCls: function (r) { return r.error ? 'err' : (r.ms >= slowQ ? 'warn' : ''); },
			cols: [
				{ k: 'i', label: '#', num: true, val: function (r) { return r.i; } },
				{ k: 'sql', label: 'SQL', val: function (r) { return r.sql; }, render: function (r) {
					return '<code class="flxp-sql">' + esc(r.sql) + '</code>' + (r.error ? '<div class="flxp-errmsg">' + esc(r.error) + '</div>' : '') + stackHtml(r.stack);
				} },
				{ k: 'type', label: t('Tür'), filter: true, val: function (r) { return r.type; } },
				{ k: 'caller', label: t('Çağıran'), filter: true, val: function (r) { return r.caller; }, render: function (r) { return '<code>' + esc(r.caller) + '</code>'; } },
				compCol(),
				{ k: 'ms', label: t('Süre'), num: true, val: function (r) { return r.ms; }, render: function (r) { return fmtMs(r.ms); } }
			]
		});
	}

	function renderDupes(el) {
		table(el, {
			rows: DB.dupes || [], sort: 1, dir: -1,
			cols: [
				{ k: 'sql', label: 'SQL', val: function (r) { return r.sql; }, render: function (r) { return '<code class="flxp-sql">' + esc(r.sql) + '</code>'; } },
				{ k: 'count', label: t('Adet'), num: true, val: function (r) { return r.count; } },
				{ k: 'ms', label: t('Süre'), num: true, val: function (r) { return r.ms; }, render: function (r) { return fmtMs(r.ms); } },
				{ k: 'callers', label: t('Çağıranlar'), val: function (r) { return Object.keys(r.callers || {}).join(' '); }, render: function (r) {
					return Object.keys(r.callers || {}).map(function (k) { return '<code>' + esc(k) + '</code> ×' + r.callers[k]; }).join('<br>');
				} }
			]
		});
	}

	function renderComponents(el) {
		var rows = DB.by_component || [];
		var max = rows.reduce(function (m, r) { return Math.max(m, r.ms); }, 0) || 1;
		var types = Object.keys(DB.by_type || {});
		table(el, {
			rows: rows, sort: 2, dir: -1,
			cols: [
				compCol(),
				{ k: 'count', label: t('Sorgu'), num: true, val: function (r) { return r.count; } },
				{ k: 'ms', label: t('Süre'), num: true, val: function (r) { return r.ms; }, render: function (r) {
					return '<span class="flxp-bar"><i style="width:' + (r.ms / max * 100).toFixed(1) + '%"></i></span>' + fmtMs(r.ms);
				} }
			]
		});
		if (types.length) {
			var d = document.createElement('div');
			d.innerHTML = h3(t('Sorgu türleri')) + kv(types.map(function (k) { return [k, fmtNum(DB.by_type[k])]; }));
			el.appendChild(d);
		}
	}

	function renderPhp(el) {
		table(el, {
			rows: D.php || [],
			rowCls: function (r) { return r.level === 'fatal' || r.level === 'warning' ? 'err' : ''; },
			cols: [
				{ k: 'level', label: t('Seviye'), filter: true, val: function (r) { return LEVELS[r.level] || r.level; } },
				{ k: 'message', label: t('Mesaj'), val: function (r) { return r.message; }, render: function (r) { return esc(r.message) + stackHtml(r.stack); } },
				{ k: 'file', label: t('Dosya'), val: function (r) { return r.file + ':' + r.line; }, render: function (r) { return '<code>' + esc(r.file + ':' + r.line) + '</code>'; } },
				compCol(),
				{ k: 'count', label: t('Adet'), num: true, val: function (r) { return r.count; } }
			]
		});
	}

	function renderWrong(el) {
		table(el, {
			rows: D.wrong || [],
			cols: [
				{ k: 'kind', label: t('Tür'), filter: true, val: function (r) { return r.kind === 'deprecated' ? t('Eskimiş') : t('Yanlış kullanım'); } },
				{ k: 'subject', label: t('Konu'), val: function (r) { return r.subject; }, render: function (r) { return '<code>' + esc(r.subject) + '</code>'; } },
				{ k: 'message', label: t('Mesaj'), val: function (r) { return r.message; }, render: function (r) { return esc(r.message) + stackHtml(r.stack); } },
				compCol(),
				{ k: 'count', label: t('Adet'), num: true, val: function (r) { return r.count; } }
			]
		});
	}

	function renderHttp(el) {
		table(el, {
			rows: D.http || [],
			rowCls: function (r) { return r.error || r.status >= 400 ? 'err' : ''; },
			cols: [
				{ k: 'method', label: t('Yöntem'), filter: true, val: function (r) { return r.method; } },
				{ k: 'url', label: 'URL', val: function (r) { return r.url; }, render: function (r) {
					return '<code class="flxp-sql">' + esc(r.url) + '</code>' + (r.error ? '<div class="flxp-errmsg">' + esc(r.error) + '</div>' : '') + stackHtml(r.stack);
				} },
				{ k: 'status', label: t('Durum'), num: true, val: function (r) { return r.status; }, render: function (r) { return r.status ? esc(r.status) : '—'; } },
				compCol(),
				{ k: 'size', label: t('Boyut'), num: true, val: function (r) { return r.size; }, render: function (r) { return fmtKb(r.size / 1024); } },
				{ k: 'ms', label: t('Süre'), num: true, val: function (r) { return r.ms; }, render: function (r) { return fmtMs(r.ms) + (r.timeout ? ' <small>/ ' + esc(r.timeout) + ' s</small>' : ''); } }
			]
		});
	}

	function renderCron(el) {
		table(el, {
			rows: D.cron || [],
			rowCls: function (r) { return r.cut ? 'err' : (r.ms >= 5000 ? 'warn' : ''); },
			cols: [
				{ k: 'hook', label: t('Kanca'), val: function (r) { return r.hook; }, render: function (r) { return '<code>' + esc(r.hook) + '</code>' + (r.cut ? ' <span class="flxp-err">' + esc(t('Kesildi')) + '</span>' : ''); } },
				compCol(),
				{ k: 'runs', label: t('Çalışma'), num: true, val: function (r) { return r.runs; } },
				{ k: 'ms', label: t('Süre'), num: true, val: function (r) { return r.ms; }, render: function (r) { return fmtMs(r.ms); } }
			]
		});
	}

	function renderLogs(el) {
		if (!(D.logs || []).length && !(D.timers || []).length) {
			el.innerHTML = '<p class="flxp-note">' + esc(t('Bu istekte log yok.')) + ' ' + esc(t('Paketler do_action( \'flx_log\', \'warning\', \'mesaj\' ) ile buraya yazabilir.')) + '</p>';
			return;
		}
		if ((D.logs || []).length) {
			table(el, {
				rows: D.logs,
				rowCls: function (r) { return ['emergency', 'alert', 'critical', 'error'].indexOf(r.level) > -1 ? 'err' : (r.level === 'warning' ? 'warn' : ''); },
				cols: [
					{ k: 'level', label: t('Seviye'), filter: true, val: function (r) { return r.level; } },
					{ k: 'message', label: t('Mesaj'), val: function (r) { return r.message; }, render: function (r) { return '<pre class="flxp-pre">' + esc(r.message) + '</pre>'; } },
					compCol(),
					{ k: 'ms', label: t('Zaman'), num: true, val: function (r) { return r.ms; }, render: function (r) { return fmtMs(r.ms); } }
				]
			});
		}
		if ((D.timers || []).length) {
			var d = document.createElement('div');
			d.innerHTML = h3(t('Zamanlayıcılar')) + kv(D.timers.map(function (x) { return [x.name, x.ms === null ? t('Durdurulmadı') : fmtMs(x.ms) + ' · ' + fmtKb(x.mem_kb)]; }));
			el.appendChild(d);
		}
	}

	function renderTemplate(el) {
		var T = D.template || {};
		var h = kv([
			[t('Şablon dosyası'), T.file || ''],
			[t('Tema'), T.theme || ''],
			[t('Ana tema'), T.parent || undefined],
			[t('Blok teması'), T.block ? t('Evet') : t('Hayır')],
			[t('Blok şablonu'), T.block_id || undefined],
			[t('Body sınıfları'), T.body_class || undefined]
		]);
		if ((T.parts || []).length) {
			h += h3(t('Şablon parçaları'));
			h += '<table class="flxp-t"><tbody>' + T.parts.map(function (p) {
				return '<tr class="' + (p.found ? '' : 'warn') + '"><td><code>' + esc(p.slug) + '</code></td><td>' + (p.found ? '<code>' + esc(p.file) + '</code>' : esc(t('Bulunamadı'))) + '</td></tr>';
			}).join('') + '</tbody></table>';
		}
		el.innerHTML = h;
	}

	function renderRequest(el) {
		var R = D.request || {};
		var h = kv([
			[t('Adres'), O.url],
			[t('Yöntem'), String(O.method || '').toUpperCase()],
			[t('İstek yolu'), R.request],
			[t('Eşleşen kural'), R.matched_rule],
			[t('Eşleşen sorgu'), R.matched_query],
			[t('Sorgulanan nesne'), R.queried ? R.queried.type + ' #' + R.queried.id + (R.queried.name ? ' — ' + R.queried.name : '') : undefined]
		]);
		var qv = R.query_vars || {};
		if (Object.keys(qv).length) {
			h += h3(t('Sorgu değişkenleri')) + kv(Object.keys(qv).map(function (k) { return [k, qv[k]]; }));
		}
		var cond = D.cond || {};
		var keys = Object.keys(cond);
		if (keys.length) {
			h += h3(t('Koşullar'));
			h += '<div class="flxp-conds">' + keys.map(function (k) {
				return '<code class="' + (cond[k] ? 'on' : 'off') + '">' + esc(k) + '()</code>';
			}).join('') + '</div>';
		}
		el.innerHTML = h;
	}

	function renderCaps(el) {
		table(el, {
			rows: D.caps || [],
			rowCls: function (r) { return r.result ? '' : 'warn'; },
			cols: [
				{ k: 'cap', label: t('Yetki'), filter: true, val: function (r) { return r.cap; }, render: function (r) { return '<code>' + esc(r.cap) + '</code>' + (r.arg ? ' <small>' + esc(r.arg) + '</small>' : ''); } },
				{ k: 'user', label: t('Kullanıcı'), val: function (r) { return '#' + r.user; } },
				{ k: 'result', label: t('Sonuç'), filter: true, val: function (r) { return r.result ? t('Var') : t('Reddedildi'); } },
				{ k: 'caller', label: t('Çağıran'), val: function (r) { return r.caller; }, render: function (r) { return '<code>' + esc(r.caller) + '</code>'; } },
				compCol(),
				{ k: 'count', label: t('Adet'), num: true, val: function (r) { return r.count; } }
			]
		});
	}

	function renderAssets(el) {
		[['scripts', t('Scriptler')], ['styles', t('Stiller')]].forEach(function (p) {
			var rows = (D.assets || {})[p[0]] || [];
			if (!rows.length) return;
			var d = document.createElement('div');
			d.innerHTML = h3(p[1]);
			el.appendChild(d);
			table(el, {
				rows: rows,
				rowCls: function (r) { return r.missing && r.missing.length ? 'err' : ''; },
				cols: [
					{ k: 'handle', label: 'Handle', val: function (r) { return r.handle; }, render: function (r) { return '<code>' + esc(r.handle) + '</code>'; } },
					{ k: 'src', label: t('Kaynak'), val: function (r) { return r.src; }, render: function (r) { return '<code class="flxp-sql">' + esc(r.src || '—') + '</code>' + (r.ver ? ' <small>' + esc(r.ver) + '</small>' : ''); } },
					compCol(),
					{ k: 'deps', label: t('Bağımlılıklar'), val: function (r) { return (r.deps || []).join(', '); }, render: function (r) {
						return esc((r.deps || []).join(', ')) + (r.missing && r.missing.length ? '<div class="flxp-errmsg">' + esc(t('Eksik')) + ': ' + esc(r.missing.join(', ')) + '</div>' : '');
					} },
					{ k: 'pos', label: t('Konum'), filter: true, val: function (r) { return r.footer ? t('Alt') : t('Üst'); } }
				]
			});
		});
	}

	function renderLang(el) {
		table(el, {
			rows: D.lang || [],
			rowCls: function (r) { return r.found ? '' : 'muted'; },
			cols: [
				{ k: 'domain', label: 'Text domain', filter: true, val: function (r) { return r.domain; } },
				{ k: 'file', label: t('Dosya'), val: function (r) { return r.file; }, render: function (r) { return '<code>' + esc(r.file) + '</code>'; } },
				{ k: 'found', label: t('Durum'), filter: true, val: function (r) { return r.found ? t('Yüklendi') : t('Dosya yok'); } }
			]
		});
	}

	function renderTransients(el) {
		table(el, {
			rows: D.transients || [],
			cols: [
				{ k: 'name', label: t('Ad'), val: function (r) { return r.name; }, render: function (r) { return '<code>' + esc(r.name) + '</code>' + (r.site ? ' <small>site</small>' : ''); } },
				{ k: 'exp', label: t('Süre'), num: true, val: function (r) { return r.exp; }, render: function (r) { return r.exp ? fmtNum(r.exp) + ' s' : t('Süresiz'); } },
				{ k: 'size', label: t('Boyut'), num: true, val: function (r) { return r.size; }, render: function (r) { return fmtKb(r.size / 1024); } },
				compCol()
			]
		});
	}

	function renderHooks(el) {
		var H = D.hooks || {};
		var p = document.createElement('p');
		p.className = 'flxp-note';
		p.textContent = t('Bu istekte çalışan farklı action sayısı') + ': ' + fmtNum(H.total) + '. ' + t('En çok çalışanlar:');
		el.appendChild(p);
		table(el, {
			rows: H.top || [], sort: 1, dir: -1,
			cols: [
				{ k: 'hook', label: 'Hook', val: function (r) { return r.hook; }, render: function (r) { return '<code>' + esc(r.hook) + '</code>'; } },
				{ k: 'count', label: t('Adet'), num: true, val: function (r) { return r.count; } }
			]
		});
	}

	/* ------------------------------------------------------------ Kabuk */

	var embed = C.mode === 'embed';
	var current = lsGet('flxp-sec', 'overview');
	if (!sections.some(function (s) { return s.id === current; })) current = 'overview';

	function badge(s) {
		var cls = s.err ? 'err' : (s.warn ? 'warn' : '');
		var n = s.err || s.n;
		if (n === undefined || n === null || n === '') return '';
		return '<em class="' + cls + '">' + esc(fmtNum(n)) + '</em>';
	}

	function build() {
		root.classList.add(embed ? 'flxp-embed' : 'flxp-bar');
		var title = fmtMs(O.ms) + ' · ' + fmtKb(O.mem_kb) + ' · ' + fmtNum(O.queries) + ' ' + t('sorgu');
		root.innerHTML =
			(embed ? '' : '<div class="flxp-resize" title="' + esc(t('Boyutlandır')) + '"></div>') +
			'<header class="flxp-head"><strong>' + esc(t('Flux Speed')) + '</strong><span class="flxp-sum">' + esc(title) + '</span>' +
			(embed ? '' : '<a class="flxp-link" href="' + esc(C.adminUrl || '#') + '">' + esc(t('Kayıtlar')) + '</a><button type="button" class="flxp-close" aria-label="' + esc(t('Kapat')) + '">×</button>') +
			'</header><div class="flxp-body"><nav class="flxp-nav" role="tablist">' +
			sections.map(function (s) {
				return '<button type="button" role="tab" data-s="' + s.id + '" aria-selected="' + (s.id === current) + '">' + esc(s.label) + badge(s) + '</button>';
			}).join('') + '</nav><div class="flxp-main"></div></div>';
		root.querySelector('.flxp-nav').addEventListener('click', function (e) {
			var b = e.target.closest('button[data-s]');
			if (b) show(b.dataset.s);
		});
		if (!embed) {
			root.querySelector('.flxp-close').addEventListener('click', function () { toggle(false); });
			resizer(root.querySelector('.flxp-resize'));
			var hgt = parseInt(lsGet('flxp-h', '0'), 10);
			if (hgt > 120) root.style.height = Math.min(hgt, window.innerHeight - 40) + 'px';
		}
		show(current);
	}

	function show(id) {
		current = id;
		lsSet('flxp-sec', id);
		root.querySelectorAll('.flxp-nav button').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.s === id ? 'true' : 'false'); });
		var main = root.querySelector('.flxp-main');
		main.innerHTML = '';
		main.scrollTop = 0;
		var s = sections.filter(function (x) { return x.id === id; })[0];
		var el = document.createElement('section');
		el.innerHTML = '<h2>' + esc(s.label) + '</h2>';
		main.appendChild(el);
		try { s.render(el); } catch (e) { el.innerHTML += '<p class="flxp-errmsg">' + esc(e.message) + '</p>'; }
	}

	function resizer(h) {
		var startY, startH;
		function move(e) {
			var y = e.touches ? e.touches[0].clientY : e.clientY;
			var nh = Math.max(160, Math.min(window.innerHeight - 40, startH + (startY - y)));
			root.style.height = nh + 'px';
		}
		function up() {
			document.removeEventListener('mousemove', move);
			document.removeEventListener('mouseup', up);
			document.removeEventListener('touchmove', move);
			document.removeEventListener('touchend', up);
			lsSet('flxp-h', String(root.offsetHeight));
		}
		function down(e) {
			startY = e.touches ? e.touches[0].clientY : e.clientY;
			startH = root.offsetHeight;
			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
			document.addEventListener('touchmove', move, { passive: true });
			document.addEventListener('touchend', up);
			e.preventDefault();
		}
		h.addEventListener('mousedown', down);
		h.addEventListener('touchstart', down, { passive: false });
	}

	var built = false;
	function toggle(open) {
		if (open && !built) { build(); built = true; }
		root.hidden = !open;
		document.documentElement.classList.toggle('flxp-open', !!open);
		lsSet('flxp-open', open ? '1' : '0');
	}

	function adminBar() {
		var node = document.getElementById('wp-admin-bar-flxp');
		if (!node) return;
		var lab = node.querySelector('.flxp-ab-label');
		if (lab) lab.textContent = fmtMs(O.ms) + ' · ' + fmtNum(O.queries) + 'q' + (O.errors ? ' · ⚠ ' + O.errors : '');
		node.classList.toggle('flxp-ab-err', !!(O.errors || O.fatal || O.status >= 500 || httpFail || DB.errors));
		node.classList.toggle('flxp-ab-warn', !(O.errors || O.fatal) && (O.ms >= (C.slowMs || 1500) || !!DB.slow));
		var a = node.querySelector('a');
		if (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				toggle(root.hidden);
			});
		}
	}

	function start() {
		if (embed) {
			root.hidden = false;
			build();
			return;
		}
		adminBar();
		if (lsGet('flxp-open', '0') === '1' || location.hash === '#flxp-panel') toggle(true);
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !root.hidden) toggle(false);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
