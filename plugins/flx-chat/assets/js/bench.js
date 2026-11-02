/* Flux Chat — Asistan benchmark'ı: senaryolar, çalıştırma, sonuçlar. studio.js içinden çağrılır. */
(function () {
	'use strict';
	var S = { data: null, view: 'runs', run: null, results: [], sel: null, onlyFail: false, live: null, cfg: null };
	// Çalıştırma sürerken sayfadan çıkılırsa uyar (kalınan yerden "Devam et" ile sürer).
	function liveRun() { return S.data ? S.data.runs.filter(function (r) { return r.status === 'running'; })[0] : null; }

	/*
	 * Çalıştırma sunucuda arka planda ilerler; sayfa yalnız izler. Açıkken birkaç saniyede bir hafif istekle
	 * durumu çeker. Sunucu uzun süre ilerlemezse (loopback engelli vb.) tarayıcı bir adımı kendisi tetikler.
	 */
	function ensurePoll() { if (!S.timer && S.K) S.timer = setTimeout(tick, 3000); }
	function tick() {
		S.timer = null;
		var K = S.K, lr = liveRun();
		if (!lr && !(S.run && S.run.status === 'running')) return;
		var visible = function () { return !!document.querySelector('.flxbb-sub'); };
		var jobs = [K.api('bot/bench', { query: { light: 1 } }).then(function (r) {
			S.data.runs = r.runs;
			if (r.totals) S.data.totals = r.totals;
			if (r.month) K.D().month = r.month;
		})];
		if (S.view === 'run' && S.run) {
			var since = 0;
			S.results.forEach(function (x) { if (x.status !== 'running' && x.id > since) since = x.id; });
			jobs.push(K.api('bot/bench/runs/' + S.run.id, { query: { since: since } }).then(function (r) {
				if (!S.run || S.run.id !== r.run.id) return;
				r.results.forEach(function (x) {
					var i = S.results.findIndex(function (y) { return y.id === x.id; });
					if (i < 0) S.results.push(x); else S.results[i] = x;
					if (S.follow) S.sel = x.id;
				});
				S.run = Object.assign(S.run, r.run);
			}));
		}
		Promise.all(jobs).then(function () {
			lr = liveRun();
			if (lr && !lr.busy && lr.beat && Date.now() - lr.beat > 20000) {
				// Yedek: sunucu kendi kendine ilerlemiyorsa bir adımı buradan tetikle (sonrası yine arka planda).
				K.api('bot/bench/runs/' + lr.id + '/step', { method: 'POST', body: {} }).catch(function () {});
			}
			if (visible()) { var y = window.scrollY; K.render(); window.scrollTo(0, y); }
		}).catch(function () {}).then(function () {
			if (liveRun() || (S.run && S.run.status === 'running')) S.timer = setTimeout(tick, document.hidden ? 20000 : 4000);
		});
	}

	window.FLXC_BENCH_RENDER = function (K, body) {
		S.K = K;
		var h = K.h, t = K.t, api = K.api, clear = K.clear, toast = K.toast;
		var STATUS = { running: t('Çalışıyor'), done: t('Bitti'), stopped: t('Durduruldu'), limit: t('Ücret limiti doldu'), paused: t('Durdu: yapay zekâ hesabı') };
		var CHECK = { handoff: 'Ekibe devir', donation: 'Bağış kaydı', price_list: 'Fiyat listesi', order_qty: 'Adet', contains: 'İçermeli', not_contains: 'İçermemeli', judge: 'Değerlendirici', tools: 'Araç çağrılmalı', no_tools: 'Araç çağrılmamalı', state: 'Durum' };
		function groupLabel(g) { return (S.data && S.data.groups && S.data.groups[g]) || g || t('Grupsuz'); }
		function pct(p, n) { return n ? Math.round(100 * p / n) + '%' : '–'; }
		function rerender() { K.render(); }

		if (!S.data) {
			body.appendChild(h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') }));
			api('bot/bench').then(function (r) { S.data = r; rerender(); ensurePoll(); }).catch(function (e) { toast(e.message, 1); });
			return;
		}
		body.appendChild(K.banner() || h('span'));
		body.appendChild(totalsBox());
		body.appendChild(h('div', { class: 'flxbb-sub' },
			h('button', { type: 'button', class: S.view !== 'scenarios' ? 'is-on' : '', onclick: function () { S.view = 'runs'; rerender(); } }, t('Çalıştırmalar')),
			h('button', { type: 'button', class: S.view === 'scenarios' ? 'is-on' : '', onclick: function () { S.view = 'scenarios'; rerender(); } }, t('Senaryolar') + ' (' + S.data.scenarios.length + ')')));
		if (S.view === 'scenarios') return renderScenarios();
		if (S.view === 'run' && S.run) return renderRun();
		renderRuns();

		/* ---------- Tüm benchmark'ların toplamı (canlı) ---------- */
		function totalsBox() {
			var T = S.data.totals || { runs: 0, scenarios: 0, passed: 0, cost: 0, in: 0, out: 0, models: [] };
			var names = {};
			T.models.forEach(function (m) { names[m.model] = 1; });
			var box = h('section', { class: 'flxbs-card flxbb-totals' },
				h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('Tüm benchmark\'lar') }),
					T.running ? h('span', { class: 'flxbs-pill', text: t('Canlı · {n} çalışıyor', { n: T.running }) }) : null,
					h('button', { type: 'button', class: 'flxbs-link flxbs-right', onclick: function () { S.detail = !S.detail; rerender(); } }, S.detail ? t('Detayı gizle') : t('Detay'))),
				h('div', { class: 'flxbs-stats' },
					K.stat(K.money(T.cost), t('Toplam harcama')),
					K.stat(K.nf.format(T.in), t('Giriş token')),
					K.stat(K.nf.format(T.out), t('Çıkış token')),
					K.stat(T.runs, t('Çalıştırma')),
					K.stat(T.scenarios ? pct(T.passed, T.scenarios) : '–', t('{n} senaryo sonucu', { n: T.scenarios })),
					K.stat(Object.keys(names).length, t('Model'))));
			if (S.detail) {
				if (!T.models.length) box.appendChild(h('div', { class: 'flxbs-empty', text: t('Henüz çalıştırma yok.') }));
				else {
					var ROLE = { bot: t('Asistan'), judge: t('Değerlendirici'), mixed: t('Asistan + değerlendirici') };
					var tb = h('tbody');
					T.models.forEach(function (m) {
						tb.appendChild(h('tr', null, h('td', { class: 'flxbb-mono', text: m.model || '–' }), h('td', { text: ROLE[m.role] || m.role }),
							h('td', { text: m.runs }), h('td', { text: m.scenarios ? pct(m.passed, m.scenarios) + ' (' + m.passed + '/' + m.scenarios + ')' : '–' }),
							h('td', { text: m.calls || '–' }), h('td', { text: K.nf.format(m.in) }), h('td', { text: K.nf.format(m.out) }), h('td', null, h('b', { text: K.money(m.cost) })),
							h('td', { text: m.scenarios ? K.money(m.cost / m.scenarios) : '–' })));
					});
					box.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' },
						h('thead', null, h('tr', null, h('th', { text: t('Model') }), h('th', { text: t('Rol') }), h('th', { text: t('Çalıştırma') }), h('th', { text: t('Başarı') }), h('th', { text: t('İstek') }), h('th', { text: t('Giriş token') }), h('th', { text: t('Çıkış token') }), h('th', { text: t('Maliyet') }), h('th', { text: t('Senaryo başına') }))),
						tb)));
					box.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: t('Başarı asistan modeline yazılır. 1.6.3 öncesi çalıştırmalarda asistan ve değerlendirici ayrılmadığı için "Asistan + değerlendirici" görünür.') }));
				}
			}
			return box;
		}
		function report(r, fmt) {
			api('bot/bench/runs/' + r.id + '/report', { query: { format: fmt } }).then(function (x) {
				var a = h('a', { href: URL.createObjectURL(new Blob([x.content], { type: x.mime + ';charset=utf-8' })), download: x.filename });
				document.body.appendChild(a); a.click(); a.remove();
				setTimeout(function () { URL.revokeObjectURL(a.href); }, 5000);
			}).catch(function (e) { toast(e.message, 1); });
		}

		/* ---------- Çalıştırmalar ---------- */
		function renderRuns() {
			var D = K.D();
			var cfg = S.cfg || (S.cfg = { model: '', judge: '', prompt: 'live', limit: '0.50', groups: null, cache: '' });
			var groups = {};
			S.data.scenarios.forEach(function (s) { groups[s.group] = (groups[s.group] || 0) + 1; });
			if (!cfg.groups) { cfg.groups = {}; Object.keys(groups).forEach(function (g) { cfg.groups[g] = true; }); }
			var psel = h('select', { class: 'flxbs-in' });
			psel.appendChild(h('option', { value: 'live', text: t('Yayındaki talimat') }));
			psel.appendChild(h('option', { value: 'builtin', text: t('Yerleşik metin') }));
			D.prompt.versions.forEach(function (v) { psel.appendChild(h('option', { value: v.id, text: K.versionLabel(v) })); });
			psel.value = cfg.prompt;
			psel.addEventListener('change', function () { cfg.prompt = psel.value; });
			var lim = h('input', { type: 'number', class: 'flxbs-in', min: '0.01', step: '0.05', value: cfg.limit });
			lim.addEventListener('input', function () { cfg.limit = lim.value; updateEst(); });
			var checks = h('div', { class: 'flxbb-groups' });
			var startBtn = h('button', { type: 'button', class: 'flxbs-btn is-primary' });
			var estEl = h('p', { class: 'flxbs-small flxbb-est' }), gEst = {};
			function count() {
				var n = 0;
				S.data.scenarios.forEach(function (s) { if (cfg.groups[s.group]) n++; });
				startBtn.textContent = t('{n} senaryoyu çalıştır', { n: n });
				startBtn.disabled = !n || !!liveRun();
				updateEst();
				return n;
			}
			/* 1.16.0: ücret tahmini: geçmiş ortalama token × seçilen modelin fiyatı (kategori ve tüm çalıştırma). */
			function updateEst() {
				var E = S.data.est, M = S.data.models || {};
				if (!E) return;
				var price = function (id) { return (S.models || []).filter(function (m) { return m.id === id; })[0]; };
				var pb = price(cfg.model || M.bot), pj = price(cfg.judge || M.judge);
				var usd = function (x) { return '$' + (x < 0.01 ? x.toFixed(4) : x.toFixed(3)); };
				var per = {}, tot = 0, bot = 0, jud = 0;
				S.data.scenarios.forEach(function (s) {
					var hst = E.by[s.id], script = s.turns.some(function (m) { return m.role === 'assistant'; });
					var u = script ? 1 : s.turns.filter(function (m) { return m.role === 'user'; }).length;
					var bi = hst ? hst.bi : u * E.turn.in, bo = hst ? hst.bo : u * E.turn.out;
					var ji = s.expect.judge ? (hst && hst.ji ? hst.ji : E.judge.in) : 0, jo = s.expect.judge ? (hst && hst.jo ? hst.jo : E.judge.out) : 0;
					var cb = pb ? (bi * pb.in + bo * pb.out) / 1e6 : 0, cj = pj ? (ji * pj.in + jo * pj.out) / 1e6 : 0;
					per[s.group] = (per[s.group] || 0) + cb + cj;
					if (cfg.groups[s.group]) { tot += cb + cj; bot += cb; jud += cj; }
				});
				Object.keys(gEst).forEach(function (g) { gEst[g].textContent = pb ? ' · ~' + usd(per[g] || 0) : ''; });
				clear(estEl);
				if (!S.models) { estEl.appendChild(document.createTextNode(t('Ücret tahmini için model fiyatları yükleniyor…'))); return; }
				if (!pb) { estEl.appendChild(document.createTextNode(t('Seçilen modelin fiyatı bilinmiyor; tahmin yapılamadı.'))); return; }
				estEl.appendChild(h('strong', { text: t('Tahmini ücret: ~{c}', { c: usd(tot) }) }));
				estEl.appendChild(document.createTextNode(' (' + t('asistan {a} + değerlendirici {j}', { a: usd(bot), j: usd(jud) }) + '). '));
				estEl.appendChild(document.createTextNode(E.history
					? t('Geçmiş {n} senaryo sonucunun ortalama token kullanımına göre; senaryo başına gerçek değer değişebilir.', { n: E.samples })
					: t('Henüz çalıştırma yok; varsayılan tahmin: müşteri mesajı başına ~{i} giriş / {o} çıkış token.', { i: E.turn.in, o: E.turn.out })));
				if (tot > (parseFloat(cfg.limit) || 0.5)) estEl.appendChild(h('span', { class: 'flxbb-est-warn', text: ' ' + t('Tahmin ücret limitini aşıyor; limit dolunca çalıştırma durur.') }));
			}
			if (!S.models && K.models) K.models(function (list) { S.models = list; updateEst(); });
			Object.keys(groups).forEach(function (g) {
				var c = h('input', { type: 'checkbox', checked: cfg.groups[g] !== false });
				c.addEventListener('change', function () { cfg.groups[g] = c.checked; count(); });
				gEst[g] = h('span', { class: 'flxbs-muted' });
				checks.appendChild(h('label', { class: 'flxbs-check' }, c, groupLabel(g) + ' (' + groups[g] + ')', gEst[g]));
			});
			startBtn.addEventListener('click', function () {
				var ids = S.data.scenarios.filter(function (s) { return cfg.groups[s.group]; }).map(function (s) { return s.id; });
				start({ model: cfg.model, judge: cfg.judge, prompt: cfg.prompt, limit: parseFloat(cfg.limit) || 0.5, cache: cfg.cache || '', scenarios: ids });
			});
			count();
			body.appendChild(h('section', { class: 'flxbs-card' },
				h('h2', { text: t('Yeni çalıştırma') }),
				h('p', { class: 'flxbs-muted flxbs-small', text: t('Senaryolar test sohbetindeki gibi oynatılır: WhatsApp\'a mesaj gitmez, kayıt açılmaz. Model gerçekten çağrılır; ücret yapay zekâ hesabınızdan (OpenRouter ya da Anthropic) düşer ve limit dolunca çalıştırma durur.') }),
				h('div', { class: 'flxbs-grid flxbb-form' },
					h('label', null, h('span', { text: t('Asistan modeli') }), K.modelSelect(D.model, function () { return cfg.model; }, function (v) { cfg.model = v; updateEst(); })),
					h('label', null, h('span', { text: t('Talimat') }), psel),
					h('label', null, h('span', { text: t('Değerlendirici model') }), K.modelSelect(D.modelEdit, function () { return cfg.judge; }, function (v) { cfg.judge = v; updateEst(); })),
					h('label', null, h('span', { text: t('Ücret limiti (USD)') }), lim),
					D.claude ? h('label', null, h('span', { text: t('Claude önbelleği') }), K.cacheSelect(function () { return cfg.cache; }, function (v) { cfg.cache = v; })) : null),
				h('h2', { class: 'flxbs-mt', text: t('Gruplar') }), checks, estEl,
				h('div', { class: 'flxbs-row' }, startBtn)));
			if (liveRun()) body.appendChild(liveBox(liveRun()));
			var runs = S.data.runs;
			var sec = h('section', { class: 'flxbs-card' }, h('h2', { text: t('Geçmiş çalıştırmalar') }));
			if (!runs.length) sec.appendChild(h('div', { class: 'flxbs-empty', text: t('Henüz çalıştırma yok.') }));
			else {
				var gkeys = Object.keys(groups);
				var table = h('table', { class: 'flxbb-table' });
				var head = h('tr', null, h('th', { text: t('Tarih') }), h('th', { text: t('Model') }), h('th', { text: t('Talimat') }), h('th', { text: t('Başarı') }));
				gkeys.forEach(function (g) { head.appendChild(h('th', { class: 'flxbb-g', title: groupLabel(g), text: groupLabel(g) })); });
				head.appendChild(h('th', { text: t('Maliyet') })); head.appendChild(h('th', { text: t('Durum') })); head.appendChild(h('th'));
				table.appendChild(h('thead', null, head));
				var tb = h('tbody');
				runs.forEach(function (r) {
					var tr = h('tr', { class: 'flxbb-tr', onclick: function (e) { if (e.target.closest('button')) return; openRun(r.id); } },
						h('td', { text: K.when(r.at) }), h('td', { class: 'flxbb-mono', text: r.model + (r.cache && r.cache.mode && r.cache.mode !== 'off' ? ' ⚡' : '') }), h('td', { text: r.prompt }),
						h('td', null, h('b', { text: pct(r.passed, r.passed + r.failed) }), h('span', { class: 'flxbs-muted', text: ' ✓' + r.passed + ' ✕' + r.failed + ' / ' + r.total })));
					gkeys.forEach(function (g) { var x = r.groups[g]; tr.appendChild(h('td', { class: 'flxbb-g' }, x ? h('span', { class: 'flxbb-pct ' + pctClass(x[0], x[1]), text: pct(x[0], x[1]) }) : '–')); });
					tr.appendChild(h('td', { text: K.money(r.cost) }));
					tr.appendChild(h('td', null, h('span', { class: 'flxbs-pill' + (r.status === 'done' ? ' is-on' : r.status === 'running' ? '' : ' is-paused'), text: STATUS[r.status] || r.status })));
					var acts = h('td', { class: 'flxbb-acts' });
					if (r.status === 'running') acts.appendChild(h('button', { type: 'button', class: 'flxbs-link', onclick: function () { stop(r); } }, t('Durdur')));
					else if (r.status !== 'done') acts.appendChild(h('button', { type: 'button', class: 'flxbs-link', onclick: function () { resume(r); } }, t('Devam et')));
					acts.appendChild(h('button', { type: 'button', class: 'flxbs-link', title: t('Tüm sohbetler, araç çağrıları ve değerlendirici logu'), onclick: function () { report(r, 'md'); } }, t('Rapor')));
					acts.appendChild(h('button', { type: 'button', class: 'flxbs-link is-danger', onclick: function () {
						if (!confirm(t('Bu çalıştırma ve sonuçları silinsin mi?'))) return;
						api('bot/bench/runs/' + r.id, { method: 'DELETE' }).then(function (x) { S.data.runs = x.runs; rerender(); }).catch(function (e) { toast(e.message, 1); });
					} }, t('Sil')));
					tr.appendChild(acts);
					tb.appendChild(tr);
				});
				table.appendChild(tb);
				sec.appendChild(h('div', { class: 'flxbb-scroll' }, table));
			}
			body.appendChild(sec);
		}
		function pctClass(p, n) { var x = n ? p / n : 0; return x >= 0.8 ? 'is-good' : x >= 0.5 ? 'is-mid' : 'is-bad'; }
		function liveBox(r) {
			var doneN = (r.passed || 0) + (r.failed || 0);
			var w = r.total ? Math.round(100 * doneN / r.total) : 0;
			return h('section', { class: 'flxbs-card flxbb-live' },
				h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('Çalışıyor…') }), h('span', { class: 'flxbs-muted', text: r.model }),
					h('button', { type: 'button', class: 'flxbs-btn flxbs-right', onclick: function () { stop(r); } }, t('Durdur'))),
				h('div', { class: 'flxbb-bar' }, h('i', { style: 'width:' + w + '%' })),
				h('div', { class: 'flxbs-muted' }, doneN + ' / ' + r.total + ' · ✓' + (r.passed || 0) + ' ✕' + (r.failed || 0) + ' · ' + K.money(r.cost) + ' / ' + K.money(r.limit)),
				h('p', { class: 'flxbs-muted flxbs-small', text: t('Sunucuda arka planda çalışıyor; sayfayı kapatsanız da devam eder.') }),
				!(S.view === 'run' && S.run && S.run.id === r.id) ? h('button', { type: 'button', class: 'flxbs-link', onclick: function () { openRun(r.id); } }, t('Sonuçları canlı izle')) : null);
		}

		function start(p) {
			api('bot/bench/runs', { method: 'POST', body: p }).then(function (r) {
				S.data.runs.unshift(Object.assign({ groups: {} }, r.run));
				S.view = 'runs';
				rerender();
				ensurePoll();
			}).catch(function (e) { toast(e.message, 1); });
		}
		function setRow(run) {
			var i = S.data.runs.findIndex(function (x) { return x.id === run.id; });
			if (i >= 0) S.data.runs[i] = Object.assign(S.data.runs[i], run);
			if (S.run && S.run.id === run.id) S.run = Object.assign(S.run, run);
		}
		function resume(r) {
			var lim = prompt(t('Ücret limiti (USD)'), String(Math.max(r.limit, r.cost + 0.25).toFixed(2)));
			if (lim === null) return;
			api('bot/bench/runs/' + r.id + '/state', { method: 'POST', body: { action: 'resume', limit: parseFloat(lim) || r.limit } }).then(function (x) {
				setRow(x.run); rerender(); ensurePoll();
			}).catch(function (e) { toast(e.message, 1); });
		}
		function stop(r) {
			api('bot/bench/runs/' + r.id + '/state', { method: 'POST', body: { action: 'stop' } }).then(function (x) { setRow(x.run); rerender(); }).catch(function (e) { toast(e.message, 1); });
		}

		/* ---------- Tek çalıştırmanın sonuçları ---------- */
		function openRun(id) {
			api('bot/bench/runs/' + id).then(function (r) {
				S.run = r.run; S.results = r.results; S.view = 'run'; S.follow = r.run.status === 'running';
				S.sel = r.results.length ? (S.follow ? r.results[r.results.length - 1].id : (r.results.filter(function (x) { return x.status === 'fail' || x.status === 'error'; })[0] || r.results[0]).id) : null;
				rerender();
				ensurePoll();
			}).catch(function (e) { toast(e.message, 1); });
		}
		function renderRun() {
			var R = S.run, res = S.results;
			if (R.status === 'running') body.appendChild(liveBox(R));
			var by = {}, order = [], hc = { tp: 0, fp: 0, fn: 0, tn: 0 };
			res.forEach(function (x) {
				if (!by[x.group]) { by[x.group] = []; order.push(x.group); }
				by[x.group].push(x);
				if (hc[x.hclass] !== undefined) hc[x.hclass]++;
			});
			var fin = R.passed + R.failed;
			var head = h('section', { class: 'flxbs-card' },
				h('div', { class: 'flxbs-chat-top' },
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { S.view = 'runs'; S.run = null; rerender(); } }, '← ' + t('Çalıştırmalar')),
					h('span', { class: 'flxbs-muted flxbs-right', text: K.when(R.at) + ' · ' + (STATUS[R.status] || R.status) }),
					h('button', { type: 'button', class: 'flxbs-btn', title: t('Tüm sohbetler, araç çağrıları ve değerlendirici logu'), onclick: function () { report(R, 'md'); } }, t('Rapor indir (.md)')),
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { report(R, 'json'); } }, 'JSON')),
				h('div', { class: 'flxbs-stats flxbb-stats' },
					K.stat(pct(R.passed, fin), t('Başarı')), K.stat('✓ ' + R.passed + '  ✕ ' + R.failed, t('{n} senaryodan', { n: R.total })),
					K.stat(K.money(R.cost), t('Maliyet')), K.stat(K.nf.format(R.in) + ' / ' + K.nf.format(R.out), t('Giriş / çıkış token')),
					R.cache && (R.cache.cr || R.cache.cw) ? K.stat(K.money(R.cache.save), t('Önbellek tasarrufu')) : null),
				h('div', { class: 'flxbs-muted flxbs-small' }, t('Asistan') + ': ' + R.model + ' · ' + t('Değerlendirici') + ': ' + R.judge + ' · ' +
					(R.cache && R.cache.mode ? t('Claude önbelleği') + ': ' + K.cacheLabel(R.cache.mode) + (R.cache.cr || R.cache.cw ? ' (' + t('{r} okundu, {w} yazıldı', { r: K.nf.format(R.cache.cr), w: K.nf.format(R.cache.cw) }) + ')' : '') + ' · ' : '') + t('Talimat') + ': ',
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { K.modal(R.prompt, h('pre', { class: 'flxbs-pre flxbs-pre-lg', text: R.promptText || '' })); } }, R.prompt)),
				R.status === 'paused' ? h('div', { class: 'flxbs-note is-warn flxbb-paused' },
					h('div', null, h('strong', { text: t('Yapay zekâ servisi istekleri reddetti; benchmark durdu.') }), ' ', t('Kalan senaryolar hatalı sayılmadı. Sağlayıcı bütçesini, kredisini ya da anahtarını kontrol edip Devam et ile kaldığı yerden sürdürün.')),
					R.reason ? h('div', { class: 'flxbs-small', text: R.reason }) : null,
					h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { resume(R); } }, t('Devam et'))) : null);
			var gb = h('div', { class: 'flxbb-gbars' });
			order.forEach(function (g) {
				var p = 0, n = 0;
				by[g].forEach(function (x) { if (x.status === 'pass') p++; if (x.status !== 'running') n++; });
				gb.appendChild(h('div', { class: 'flxbb-gbar' }, h('span', { text: groupLabel(g) }), h('div', { class: 'flxbb-bar' }, h('i', { class: pctClass(p, n), style: 'width:' + (n ? 100 * p / n : 0) + '%' })), h('b', { text: p + '/' + n })));
			});
			head.appendChild(gb);
			if (hc.tp + hc.fp + hc.fn + hc.tn) {
				head.appendChild(h('div', { class: 'flxbb-matrix' },
					h('strong', { text: t('Ekibe devir doğruluğu') }),
					h('span', { class: 'is-good', title: t('Devretmesi gerekirken devretti') }, t('Doğru devir') + ': ' + hc.tp),
					h('span', { class: 'is-good', title: t('Devretmemesi gerekirken devretmedi') }, t('Doğru devretmeme') + ': ' + hc.tn),
					h('span', { class: 'is-bad', title: t('Devretmemesi gerekirken devretti') }, t('Gereksiz devir') + ': ' + hc.fp),
					h('span', { class: 'is-bad', title: t('Devretmesi gerekirken devretmedi') }, t('Kaçan devir') + ': ' + hc.fn)));
			}
			body.appendChild(head);

			var list = h('div', { class: 'flxbb-list' });
			var only = h('input', { type: 'checkbox', checked: S.onlyFail });
			only.addEventListener('change', function () { S.onlyFail = only.checked; rerender(); });
			list.appendChild(h('label', { class: 'flxbs-check flxbb-only' }, only, t('Yalnız kalanlar')));
			order.forEach(function (g) {
				var items = by[g].filter(function (x) { return !S.onlyFail || x.status === 'fail' || x.status === 'error'; });
				if (!items.length) return;
				list.appendChild(h('div', { class: 'flxbb-lg', text: groupLabel(g) }));
				items.forEach(function (x) {
					var icon = x.status === 'pass' ? '✓' : x.status === 'running' ? '…' : '✕';
					list.appendChild(h('button', { type: 'button', class: 'flxbb-item is-' + x.status + (S.sel === x.id ? ' is-sel' : ''), 'data-id': x.id, onclick: function () { select(x); } },
						h('span', { class: 'flxbb-ic', text: icon }), h('span', { class: 'flxbb-it', text: x.title }), h('span', { class: 'flxbs-muted', text: K.money(x.cost) })));
				});
			});
			var cur = res.filter(function (x) { return x.id === S.sel; })[0];
			var right = h('section', { class: 'flxbs-card flxbb-right' }, cur ? preview(cur) : h('div', { class: 'flxbs-empty', text: t('Soldan bir senaryo seçin.') }));
			body.appendChild(h('div', { class: 'flxbb-cols' }, h('section', { class: 'flxbs-card flxbb-left' }, list), right));
			// Seçim sayfayı yeniden çizmeden yalnız sağ paneli değiştirir ve WhatsApp önizlemesine kaydırır.
			function select(x) {
				S.sel = x.id; S.follow = false;
				list.querySelectorAll('.flxbb-item').forEach(function (b) { b.classList.toggle('is-sel', String(b.getAttribute('data-id')) === String(x.id)); });
				clear(right); right.appendChild(preview(x));
				var top = right.getBoundingClientRect().top;
				var bar = document.getElementById('wpadminbar');
				var off = (bar && getComputedStyle(bar).position === 'fixed' ? bar.offsetHeight : 0) + 12;
				if (top < off || top > window.innerHeight * 0.35) window.scrollTo({ top: window.scrollY + top - off, behavior: 'smooth' });
			}
		}
		function preview(x) {
			var log = h('div', { class: 'flxbs-chatlog flxbs-wa flxbb-log' });
			x.transcript.forEach(function (m) {
				if (m.role === 'note' || m.role === 'error') { log.appendChild(h('div', { class: 'flxbb-note' + (m.role === 'error' ? ' is-err' : ''), text: m.content })); return; }
				log.appendChild(h('div', { class: 'flxbs-msg is-' + (m.role === 'user' ? 'in' : 'out') + (m.scripted ? ' is-scripted' : ''), title: m.scripted ? t('Senaryoda yazılı geçmiş') : null },
					m.scripted ? h('div', { class: 'flxbb-scr', text: t('Yazılı geçmiş') }) : null,
					m.auto ? h('div', { class: 'flxbb-scr', text: t('Sistem mesajı') }) : null,
					m.content ? h('div', { class: 'flxbs-bub', text: m.content }) : null,
					m.tools ? h('div', { class: 'flxbs-tools', text: '⚙ ' + m.tools }) : null,
					m.usage ? h('div', { class: 'flxbs-usage', text: K.usageLine(m.usage) }) : null));
			});
			var cl = h('ul', { class: 'flxbb-checks' });
			x.checks.forEach(function (c) {
				cl.appendChild(h('li', { class: c.pass ? 'is-pass' : 'is-fail' }, h('b', { text: (c.pass ? '✓ ' : '✕ ') + t(CHECK[c.key] || c.key) }), c.detail ? ' · ' + c.detail : '', c.score ? ' (' + c.score + '/5)' : ''));
			});
			var st = x.status === 'pass' ? t('Geçti') : x.status === 'running' ? t('Çalışıyor') : x.status === 'error' ? t('Hata') : t('Kaldı');
			// 1.18.0: test sohbetindeki gibi senaryonun izole durumu (bağışlar, doğrulama, hafıza, hatırlatıcı).
			var side = h('aside', { class: 'flxbb-side' },
				h('h3', { text: t('Asistanın bildikleri') }), h('pre', { class: 'flxbs-pre', text: K.stateText ? K.stateText(x.state || {}) : '' }),
				h('h3', { class: 'flxbs-mt', text: t('Arka plan') }), K.bgBox ? K.bgBox(x.bg || {}, true) : null);
			return h('div', null,
				h('div', { class: 'flxbs-chat-top' }, h('h2', { text: x.title }), h('span', { class: 'flxbs-pill ' + (x.status === 'pass' ? 'is-on' : x.status === 'running' ? '' : 'is-off'), text: st }),
					h('span', { class: 'flxbs-muted flxbs-right', text: K.money(x.cost) + ' · ' + (x.ms / 1000).toFixed(1) + ' sn' })),
				x.checks.length ? cl : null,
				x.expect && x.expect.judge ? h('p', { class: 'flxbs-muted flxbs-small', text: t('Ölçüt') + ': ' + x.expect.judge }) : null,
				h('div', { class: 'flxbb-pv' }, log, side));
		}

		/* ---------- Senaryolar ---------- */
		function renderScenarios() {
			var bar = h('div', { class: 'flxbs-row flxbb-tools' },
				h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function () { editScenario(null); } }, t('Yeni senaryo')),
				h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { aiScenario(null); } }, t('AI ile senaryo yaz')),
				h('button', { type: 'button', class: 'flxbs-link flxbs-right', onclick: function () {
					if (!confirm(t('Hazır senaryolar ilk hâline dönsün mü? Sizin eklediğiniz senaryolar kalır.'))) return;
					api('bot/bench/scenarios/reset', { method: 'POST', body: {} }).then(function (r) { S.data.scenarios = r.scenarios; rerender(); }).catch(function (e) { toast(e.message, 1); });
				} }, t('Hazır senaryoları geri yükle')));
			body.appendChild(bar);
			var f = S.data.fill || {};
			body.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: t('Mesajlarda {tur} = "{a}", {birim} = "{b}", {brand} = "{c}" olur.', { a: f.tur || '', b: f.birim || '', c: f.brand || '' }) }));
			var by = {}, order = [];
			S.data.scenarios.forEach(function (s) { if (!by[s.group]) { by[s.group] = []; order.push(s.group); } by[s.group].push(s); });
			order.forEach(function (g) {
				body.appendChild(h('h2', { class: 'flxbb-gh', text: groupLabel(g) + ' (' + by[g].length + ')' }));
				var list = h('div', { class: 'flxbs-list' });
				by[g].forEach(function (s) { list.appendChild(scenarioCard(s)); });
				body.appendChild(list);
			});
		}
		function chips(e) {
			var out = [], yn = function (v) { return v === 'yes' ? t('evet') : t('hayır'); };
			if (e.handoff) out.push(t('Ekibe devir') + ': ' + yn(e.handoff));
			if (e.donation) out.push(t('Bağış kaydı') + ': ' + yn(e.donation));
			if (e.price_list) out.push(t('Fiyat listesi') + ': ' + yn(e.price_list));
			if (e.order_qty) out.push(t('Adet') + ': ' + e.order_qty);
			if (e.contains.length) out.push(t('İçermeli') + ': ' + e.contains.join(', '));
			if (e.not_contains.length) out.push(t('İçermemeli') + ': ' + e.not_contains.join(', '));
			if ((e.tools || []).length) out.push(t('Araç çağrılmalı') + ': ' + e.tools.join(', '));
			if ((e.no_tools || []).length) out.push(t('Araç çağrılmamalı') + ': ' + e.no_tools.join(', '));
			var SK = (S.data && S.data.stateKeys) || {};
			(e.state || []).forEach(function (k) { var neg = k.charAt(0) === '!'; out.push((neg ? t('Olmamalı') : t('Olmalı')) + ': ' + (SK[k.replace('!', '')] || k)); });
			if (e.judge) out.push(t('Değerlendirici'));
			return h('div', { class: 'flxbb-chips' }, out.map(function (x) { return h('span', { class: 'flxbs-pill', text: x }); }));
		}
		function scenarioCard(s) {
			var script = s.turns.some(function (m) { return m.role === 'assistant'; });
			return h('div', { class: 'flxbs-card flxbb-sc' },
				h('div', { class: 'flxbs-chat-top' }, h('strong', { text: s.title }), s.builtin ? h('span', { class: 'flxbs-pill', text: t('Hazır') }) : null,
					script ? h('span', { class: 'flxbs-pill', title: t('Yazılı geçmişten sonra asistanın cevabı ölçülür.'), text: t('Devam') }) : null,
					s.as === 'staff' ? h('span', { class: 'flxbs-pill', title: t('Flux Roles\'de personel aracı açık ilk kullanıcı olarak yazar.'), text: t('Personel yazar') }) : null,
					(s.setup || []).length ? h('span', { class: 'flxbs-pill', title: (s.setup || []).map(function (k) { return (S.data.setups || {})[k] || k; }).join(', '), text: t('Başlangıç: {n}', { n: s.setup.length }) }) : null,
					h('span', { class: 'flxbs-muted flxbs-right', text: t('{n} mesaj', { n: s.turns.length }) })),
				h('ol', { class: 'flxbb-turns' }, s.turns.map(function (m) { return h('li', { class: 'is-' + m.role }, h('b', { text: m.role === 'assistant' ? t('Asistan') : t('Müşteri') }), ' ' + m.text); })),
				chips(s.expect),
				s.expect.judge ? h('p', { class: 'flxbs-muted flxbs-small flxbs-clip', text: s.expect.judge }) : null,
				h('div', { class: 'flxbs-ver-acts flxbs-mt' },
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { editScenario(s); } }, t('Düzenle')),
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { aiScenario(s); } }, t('AI ile düzenle')),
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () {
						S.view = 'runs'; var c = S.cfg || {};
						start({ model: c.model || '', judge: c.judge || '', prompt: c.prompt || 'live', limit: parseFloat(c.limit) || 0.5, cache: c.cache || '', scenarios: [s.id] });
					} }, t('Yalnız bunu çalıştır')),
					h('button', { type: 'button', class: 'flxbs-link is-danger', onclick: function () {
						if (!confirm(t('Senaryo silinsin mi?'))) return;
						api('bot/bench/scenarios/' + s.id, { method: 'DELETE' }).then(function (r) { S.data.scenarios = r.scenarios; rerender(); }).catch(function (e) { toast(e.message, 1); });
					} }, t('Sil'))));
		}
		function scenarioForm(s) {
			s = s || { id: '', group: '', title: '', desc: '', turns: [], expect: { handoff: '', donation: '', price_list: '', order_qty: 0, contains: [], not_contains: [], judge: '', tools: [], no_tools: [], state: [] }, as: '', setup: [] };
			var dl = h('datalist', { id: 'flxbb-groups' });
			Object.keys(S.data.groups).forEach(function (g) { dl.appendChild(h('option', { value: g, text: S.data.groups[g] })); });
			function yn(v) {
				var sel = h('select', { class: 'flxbs-in' });
				[['', t('Bakılmaz')], ['yes', t('Evet')], ['no', t('Hayır')]].forEach(function (o) { sel.appendChild(h('option', { value: o[0], text: o[1] })); });
				sel.value = v || '';
				return sel;
			}
			var f = {
				title: h('input', { type: 'text', class: 'flxbs-in', value: s.title }),
				group: h('input', { type: 'text', class: 'flxbs-in', list: 'flxbb-groups', value: s.group }),
				desc: h('textarea', { class: 'flxbs-in', rows: '2' }),
				turns: turnEditor(s.turns),
				handoff: yn(s.expect.handoff), donation: yn(s.expect.donation), price_list: yn(s.expect.price_list),
				order_qty: h('input', { type: 'number', class: 'flxbs-in', min: '0', value: s.expect.order_qty || 0 }),
				contains: h('textarea', { class: 'flxbs-in', rows: '2', placeholder: t('Her satıra bir ifade; seçenek için a|b') }),
				not_contains: h('textarea', { class: 'flxbs-in', rows: '2', placeholder: t('Her satıra bir ifade') }),
				judge: h('textarea', { class: 'flxbs-in', rows: '3', placeholder: t('Ör. İsim sormamalı, kısa ve sıcak karşılamalı.') }),
				tools: h('textarea', { class: 'flxbs-in', rows: '2', placeholder: t('Her satıra bir araç adı; seçenek için a|b') }),
				no_tools: h('textarea', { class: 'flxbs-in', rows: '2', placeholder: t('Her satıra bir araç adı') }),
				as: h('select', { class: 'flxbs-in' }, h('option', { value: '', text: t('Bağışçı (müşteri)') }), h('option', { value: 'staff', text: t('Personel (Flux Roles)') }))
			};
			// 1.18.0: başlangıç durumu (işaret kutuları) ve sonda durum beklentileri (Bakılmaz / Olmalı / Olmamalı).
			var setupEl = h('div', { class: 'flxbb-setup' }), setupBoxes = {};
			Object.keys(S.data.setups || {}).forEach(function (k) {
				var c = h('input', { type: 'checkbox', checked: (s.setup || []).indexOf(k) > -1 });
				setupBoxes[k] = c;
				setupEl.appendChild(h('label', { class: 'flxbs-check' }, c, S.data.setups[k]));
			});
			var stateEl = h('div', { class: 'flxbb-stexp' }), stateSel = {};
			Object.keys(S.data.stateKeys || {}).forEach(function (k) {
				var cur = (s.expect.state || []).indexOf(k) > -1 ? 'yes' : (s.expect.state || []).indexOf('!' + k) > -1 ? 'no' : '';
				var sel = h('select', { class: 'flxbs-in' });
				[['', t('Bakılmaz')], ['yes', t('Olmalı')], ['no', t('Olmamalı')]].forEach(function (o) { sel.appendChild(h('option', { value: o[0], text: o[1] })); });
				sel.value = cur;
				stateSel[k] = sel;
				stateEl.appendChild(h('label', null, h('span', { text: S.data.stateKeys[k] }), sel));
			});
			f.tools.value = (s.expect.tools || []).join('\n'); f.no_tools.value = (s.expect.no_tools || []).join('\n'); f.as.value = s.as || '';
			f.desc.value = s.desc || ''; f.contains.value = s.expect.contains.join('\n'); f.not_contains.value = s.expect.not_contains.join('\n'); f.judge.value = s.expect.judge || '';
			function L(label, el, hint, tag) { return h(tag || 'label', { class: tag ? 'flxbs-field' : null }, h('span', { text: label }), el, hint ? h('small', { class: 'flxbs-muted', text: hint }) : null); }
			var el = h('div', { class: 'flxbs-form' }, dl,
				h('div', { class: 'flxbs-grid' }, L(t('Başlık'), f.title), L(t('Grup'), f.group)),
				L(t('Açıklama'), f.desc),
				L(t('Mesajlar (sırayla)'), f.turns, t('{tur}, {birim} ve {brand} kullanılabilir. Yalnız müşteri mesajı varsa asistan her birine sırayla cevap verir. Asistan mesajı eklerseniz devam senaryosu olur: yazılan geçmiş hazır verilir, asistan yalnız en sondaki müşteri mesajına cevap verir.'), 'div'),
				h('div', { class: 'flxbb-grid4' }, L(t('Ekibe devir'), f.handoff), L(t('Bağış kaydı'), f.donation), L(t('Fiyat listesi'), f.price_list), L(t('Adet'), f.order_qty)),
				h('div', { class: 'flxbs-grid' }, L(t('İçermeli'), f.contains), L(t('İçermemeli'), f.not_contains)),
				h('div', { class: 'flxbs-grid' }, L(t('Araç çağrılmalı'), f.tools, t('Ör. payment_info, cancel_donation, receipt_check. Bu sitede kapalı araç bakılmaz.')), L(t('Araç çağrılmamalı'), f.no_tools)),
				L(t('Kim yazıyor?'), f.as),
				L(t('Başlangıç durumu (test)'), setupEl, t('Senaryo test sohbetindeki gibi izole oynar; burada seçilenler yalnız bu testte vardır. Müşteri mesajında {kod} test panelindeki doğrulama kodu olur.'), 'div'),
				L(t('Sonda durum'), stateEl, t('Konuşma bitince test durumuna bakılır (bağış açıldı mı, iptal edildi mi, kod gitti mi…). Bu sitede kapalı araç bakılmaz.'), 'div'),
				L(t('Değerlendirici ölçütü'), f.judge, t('Boşsa değerlendirici çağrılmaz. Kurala dökülemeyen kalite beklentileri buraya.')));
			el.value = function () {
				var sp = function (v) { return v.split('\n').map(function (x) { return x.trim(); }).filter(Boolean); };
				return { id: s.id, group: f.group.value.trim(), title: f.title.value.trim(), desc: f.desc.value.trim(), turns: f.turns.value(),
					expect: { handoff: f.handoff.value, donation: f.donation.value, price_list: f.price_list.value, order_qty: parseInt(f.order_qty.value, 10) || 0, contains: sp(f.contains.value), not_contains: sp(f.not_contains.value), judge: f.judge.value.trim(), tools: sp(f.tools.value), no_tools: sp(f.no_tools.value),
						state: Object.keys(stateSel).filter(function (k) { return stateSel[k].value; }).map(function (k) { return (stateSel[k].value === 'no' ? '!' : '') + k; }) },
					as: f.as.value, setup: Object.keys(setupBoxes).filter(function (k) { return setupBoxes[k].checked; }) };
			};
			return el;
		}
		/* WhatsApp görünümlü mesaj düzenleyici: ekle/sil, sürükle-bırak sırala, yazanı değiştir. */
		function turnEditor(turns) {
			var arr = (turns || []).map(function (m) { return typeof m === 'string' ? { role: 'user', text: m } : { role: m.role === 'assistant' ? 'assistant' : 'user', text: m.text || '' }; });
			if (!arr.length) arr.push({ role: 'user', text: '' });
			var box = h('div', { class: 'flxbs-wa flxbb-wa' });
			var listEl = h('div', { class: 'flxbb-wa-list' });
			function grow(ta) { ta.style.height = 'auto'; ta.style.height = Math.min(220, ta.scrollHeight) + 'px'; }
			function draw(focus) {
				clear(listEl);
				arr.forEach(function (m, i) {
					var ta = h('textarea', { class: 'flxbb-wa-ta', rows: '1', placeholder: m.role === 'assistant' ? t('Asistanın yazdığı mesaj') : t('Müşterinin yazdığı mesaj') });
					ta.value = m.text;
					ta.addEventListener('input', function () { m.text = ta.value; grow(ta); });
					var handle = h('span', { class: 'flxbb-wa-drag', title: t('Sürükleyip sırala'), 'aria-label': t('Sürükleyip sırala'), text: '⋮⋮' });
					var row = h('div', { class: 'flxbb-wa-row is-' + (m.role === 'assistant' ? 'out' : 'in') },
						h('div', { class: 'flxbb-wa-bub' },
							h('div', { class: 'flxbb-wa-top' }, handle,
								h('button', { type: 'button', class: 'flxbb-wa-role', title: t('Yazanı değiştir'), onclick: function () { m.role = m.role === 'assistant' ? 'user' : 'assistant'; draw(); } }, (m.role === 'assistant' ? t('Asistan') : t('Müşteri')) + ' ⇄'),
								h('button', { type: 'button', class: 'flxbb-wa-del', title: t('Mesajı sil'), 'aria-label': t('Mesajı sil'), onclick: function () { arr.splice(i, 1); draw(); } }, '−')),
							ta));
					drag(handle, row, i);
					listEl.appendChild(row);
					setTimeout(function () { grow(ta); }, 0);
					if (focus === i) setTimeout(function () { ta.focus(); }, 0);
				});
			}
			// Pointer ile sürükleme (dokunmatikte de çalışır): bırakılan yer satırların orta çizgisine göre bulunur.
			function drag(handle, row, from) {
				handle.addEventListener('pointerdown', function (e) {
					e.preventDefault();
					var rows = Array.prototype.slice.call(listEl.children), to = from;
					row.classList.add('is-drag');
					try { handle.setPointerCapture(e.pointerId); } catch (x) {}
					function move(ev) {
						var k = 0;
						while (k < rows.length && ev.clientY >= rows[k].getBoundingClientRect().top + rows[k].getBoundingClientRect().height / 2) k++;
						to = k > from ? k - 1 : k;
						rows.forEach(function (x, j) {
							x.classList.toggle('is-over-up', to !== from && j === k && j !== from);
							x.classList.toggle('is-over-down', to !== from && k === rows.length && j === rows.length - 1);
						});
					}
					function up() {
						handle.removeEventListener('pointermove', move);
						handle.removeEventListener('pointerup', up);
						handle.removeEventListener('pointercancel', up);
						if (to !== from) { var m = arr.splice(from, 1)[0]; arr.splice(to, 0, m); }
						draw();
					}
					handle.addEventListener('pointermove', move);
					handle.addEventListener('pointerup', up);
					handle.addEventListener('pointercancel', up);
				});
			}
			function add(role) { arr.push({ role: role, text: '' }); draw(arr.length - 1); }
			box.appendChild(listEl);
			box.appendChild(h('div', { class: 'flxbb-wa-add' },
				h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { add('user'); } }, '+ ' + t('Müşteri mesajı')),
				h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { add('assistant'); } }, '+ ' + t('Asistan mesajı'))));
			draw();
			box.value = function () { return arr.map(function (m) { return { role: m.role, text: m.text.trim() }; }).filter(function (m) { return m.text; }); };
			return box;
		}
		function saveScenario(v, close) {
			api('bot/bench/scenarios', { method: 'POST', body: v }).then(function (r) { S.data.scenarios = r.scenarios; close(); toast(t('Senaryo kaydedildi ✓')); rerender(); }).catch(function (e) { toast(e.message, 1); });
		}
		function editScenario(s, prefill) {
			var form = scenarioForm(prefill || s);
			K.modal(s ? t('Senaryoyu düzenle') : t('Yeni senaryo'), form, function (close) {
				return h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function () { saveScenario(form.value(), close); } }, t('Kaydet'));
			});
		}
		function aiScenario(s) {
			var input = h('textarea', { class: 'flxbs-in', rows: '4', placeholder: s ? t('Ör. Bir mesaj daha ekle: kişi e-postasını vermek istemesin.') : t('Ör. Kişi kurban bağışı sorsun, asistan bilgi uydurmadan ekibe devretsin.') });
			var out = h('div');
			var btn = h('button', { type: 'button', class: 'flxbs-btn is-primary' }, t('Yaz'));
			var draft = null;
			var closeFn = K.modal(s ? t('Senaryoyu AI ile düzenle') : t('AI ile senaryo yaz'),
				h('div', { class: 'flxbs-form' },
					h('label', null, h('span', { text: t('Ne test edilsin?') }), input),
					h('div', { class: 'flxbs-row' }, K.modelSelect(K.D().modelEdit, function () { return S.aiModel; }, function (v) { S.aiModel = v; }), btn),
					out),
				function (close) {
					return h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function () { if (draft) saveScenario(draft.value(), close); else toast(t('Önce senaryoyu yazdırın.'), 1); } }, t('Kaydet'));
				});
			btn.addEventListener('click', function () {
				if (!input.value.trim()) return;
				btn.disabled = true; btn.textContent = t('Yazıyor…');
				api('bot/bench/scenarios/ai', { method: 'POST', body: { text: input.value, current: s, model: S.aiModel } }).then(function (r) {
					if (r.month) K.D().month = r.month;
					clear(out);
					if (r.reply) out.appendChild(h('div', { class: 'flxbs-note', text: r.reply }));
					if (r.usage) out.appendChild(h('div', { class: 'flxbs-usage', text: K.usageLine(r.usage) }));
					draft = scenarioForm(r.scenario);
					out.appendChild(draft);
				}).catch(function (e) { toast(e.message, 1); }).then(function () { btn.disabled = false; btn.textContent = t('Yeniden yaz'); });
			});
			return closeFn;
		}
	};
})();
