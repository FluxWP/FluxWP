/* Flux Chat — Asistan stüdyosu: AI sohbetleri, talimat sürümleri, belgeler, düzenleyici ve test sohbeti. */
(function () {
	'use strict';
	var B = window.FLXC_STUDIO || {};
	var root = document.getElementById('flxbs-app');
	if (!root) return;
	var L = B.i18n || {};
	function t(s, v) {
		var o = L[s] || s;
		if (v) Object.keys(v).forEach(function (k) { o = o.split('{' + k + '}').join(v[k]); });
		return o;
	}
	function h(tag, attrs) {
		var n = document.createElement(tag);
		if (attrs) Object.keys(attrs).forEach(function (k) {
			var v = attrs[k];
			if (v === null || v === undefined || v === false) return;
			if (k === 'text') n.textContent = v;
			else if (k === 'html') n.innerHTML = v;
			else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), v);
			else if (k === 'value') n.value = v;
			else n.setAttribute(k, v === true ? '' : v);
		});
		for (var i = 2; i < arguments.length; i++) {
			var c = arguments[i];
			if (c === null || c === undefined || c === false) continue;
			(Array.isArray(c) ? c : [c]).forEach(function (x) { if (x !== null && x !== undefined && x !== false) n.appendChild(typeof x === 'string' ? document.createTextNode(x) : x); });
		}
		return n;
	}
	function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); return n; }
	function api(path, opt) {
		opt = opt || {};
		var url = B.rest + path;
		if (opt.query) url += (url.indexOf('?') < 0 ? '?' : '&') + new URLSearchParams(opt.query).toString();
		// Sunucu önbelleği eski veri vermesin.
		if (!opt.method || opt.method === 'GET') url += (url.indexOf('?') < 0 ? '?' : '&') + '_t=' + Date.now();
		return fetch(url, {
			method: opt.method || 'GET', credentials: 'same-origin',
			headers: { 'X-WP-Nonce': B.nonce, 'Content-Type': 'application/json' },
			body: opt.body ? JSON.stringify(opt.body) : undefined
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) throw new Error(j && j.message ? j.message : t('Bağlantı hatası'));
				return j;
			});
		});
	}
	var toastEl = null;
	function toast(msg, err) {
		if (toastEl) toastEl.remove();
		toastEl = h('div', { class: 'flxbs-toast' + (err ? ' is-err' : ''), role: 'status', text: msg });
		document.body.appendChild(toastEl);
		setTimeout(function () { if (toastEl) { toastEl.remove(); toastEl = null; } }, 3500);
	}
	var fmt = new Intl.DateTimeFormat(B.locale || undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
	function when(ms) { return ms ? fmt.format(new Date(ms)) : '–'; }

	/* Satır bazlı fark (LCS). */
	function diff(a, b) {
		var x = a.split('\n'), y = b.split('\n'), n = x.length, m = y.length, i, j;
		var d = [];
		for (i = 0; i <= n; i++) { d.push(new Array(m + 1).fill(0)); }
		for (i = n - 1; i >= 0; i--) for (j = m - 1; j >= 0; j--) d[i][j] = x[i] === y[j] ? d[i + 1][j + 1] + 1 : Math.max(d[i + 1][j], d[i][j + 1]);
		var out = [];
		i = 0; j = 0;
		while (i < n && j < m) {
			if (x[i] === y[j]) { out.push([' ', x[i]]); i++; j++; }
			else if (d[i + 1][j] >= d[i][j + 1]) { out.push(['-', x[i]]); i++; }
			else { out.push(['+', y[j]]); j++; }
		}
		while (i < n) out.push(['-', x[i++]]);
		while (j < m) out.push(['+', y[j++]]);
		return out;
	}
	function diffView(a, b) {
		var lines = diff(a, b), box = h('div', { class: 'flxbs-diff' }), add = 0, del = 0, gap = 0;
		lines.forEach(function (l, k) {
			if (l[0] === '+') add++;
			if (l[0] === '-') del++;
			// Değişmeyen uzun bölümler kısaltılır.
			var near = lines.slice(Math.max(0, k - 2), k + 3).some(function (z) { return z[0] !== ' '; });
			if (l[0] === ' ' && !near) { if (!gap) box.appendChild(h('div', { class: 'flxbs-diff-gap', text: '⋯' })); gap = 1; return; }
			gap = 0;
			box.appendChild(h('div', { class: 'flxbs-diff-l is-' + (l[0] === '+' ? 'add' : l[0] === '-' ? 'del' : 'eq') }, h('span', { class: 'flxbs-diff-s', text: l[0] }), h('span', { text: l[1] || ' ' })));
		});
		return h('div', null, h('p', { class: 'flxbs-diff-sum', text: t('{a} satır eklendi, {d} satır çıkarıldı', { a: add, d: del }) }), box);
	}

	var D = null, tab = (location.hash || '').replace('#', '') || 'chats';
	var TABS = [['chats', 'Sohbetler'], ['prompt', 'Talimat'], ['docs', 'Belgeler'], ['editor', 'AI ile düzenle'], ['test', 'Test sohbeti'], ['bench', 'Benchmark'], ['memory', 'Hafıza'], ['jobs', 'Arka plan']];
	var nav = h('nav', { class: 'flxbs-tabs', role: 'tablist' });
	var body = h('div', { class: 'flxbs-body' });
	root.appendChild(nav);
	root.appendChild(body);

	function renderNav() {
		clear(nav);
		TABS.forEach(function (x) {
			nav.appendChild(h('button', { type: 'button', role: 'tab', class: tab === x[0] ? 'is-on' : '', 'aria-selected': tab === x[0] ? 'true' : 'false', onclick: function () { tab = x[0]; history.replaceState(null, '', '#' + tab); render(); } }, t(x[1])));
		});
	}
	function render() {
		renderNav();
		clear(body);
		if (!D) { body.appendChild(h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') })); return; }
		switch (tab) {
			case 'prompt': renderPrompt(); break;
			case 'docs': renderDocs(); break;
			case 'editor': renderEditor(); break;
			case 'test': renderTest(); break;
			case 'bench': renderBench(); break;
			case 'memory': renderMemory(); break;
			case 'jobs': renderJobs(); break;
			default: renderChats();
		}
	}
	function banner() {
		if (!D.ready) return h('div', { class: 'flxbs-note is-warn' }, t('Yapay zekâ anahtarı ya da modeli girilmemiş.'), ' ', h('a', { href: B.settingsUrl + '&tab=ai', text: t('Ayarlar') }));
		if (!D.enabled) return h('div', { class: 'flxbs-note is-warn' }, t('Asistan kapalı; gelen mesajlara cevap vermiyor.') + (D.problem ? ' ' + D.problem : ''), ' ', h('a', { href: B.settingsUrl + '&tab=ai', text: t('Ayarlar') }));
		if (D.testOnly) return h('div', { class: 'flxbs-note' }, t('Test modu açık: asistan yalnız test numaralarına cevap veriyor.'), ' ', h('a', { href: B.settingsUrl + '&tab=ai', text: t('Ayarlar') }));
		return null;
	}

	/* ---------- Sohbetler ---------- */
	var STATUS = { on: ['Açık', 'on'], paused: ['Beklemede', 'paused'], off: ['Kapalı', 'off'], '': ['Kapsam dışı', 'none'] };
	/* ---------- Asistanın çalışma özeti (canlı) ---------- */
	var ST = { range: '30', data: null, detail: false, timer: null, busy: false };
	var RANGES = [['today', 'Bugün'], ['7', '7 gün'], ['30', '30 gün'], ['month', 'Bu ay'], ['all', 'Tümü']];
	function loadStats(quiet) {
		if (ST.busy) return;
		ST.busy = true;
		api('bot/stats', { query: { range: ST.range } }).then(function (r) {
			ST.data = r;
			if (tab === 'chats') { var y = window.scrollY; render(); window.scrollTo(0, y); }
		}).catch(function (e) { if (!quiet) toast(e.message, 1); }).then(function () { ST.busy = false; });
	}
	function statsPoll() {
		clearTimeout(ST.timer);
		ST.timer = setTimeout(function () {
			if (tab === 'chats' && !document.hidden) loadStats(true);
			statsPoll();
		}, 30000);
	}
	function statsBox() {
		var box = h('section', { class: 'flxbs-card flxbs-sum' });
		var seg = h('div', { class: 'flxbs-seg' });
		RANGES.forEach(function (r) {
			seg.appendChild(h('button', { type: 'button', class: ST.range === r[0] ? 'is-on' : '', onclick: function () { ST.range = r[0]; ST.data = null; render(); loadStats(); } }, t(r[1])));
		});
		box.appendChild(h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('Asistanın çalışma özeti') }), seg,
			h('button', { type: 'button', class: 'flxbs-link flxbs-right', onclick: function () { ST.detail = !ST.detail; render(); } }, ST.detail ? t('Detayı gizle') : t('Detay'))));
		var R = ST.data;
		if (!R) { box.appendChild(h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') })); if (!ST.busy) loadStats(); return box; }
		var rc = R.receipts;
		box.appendChild(h('div', { class: 'flxbs-stats flxbs-sum-grid' },
			stat(money(R.cost), t('Harcama (USD)')),
			stat(nf.format(R.turns || R.messages), t('Cevaplanan soru')),
			stat(nf.format(R.people), t('Cevap verilen kişi')),
			stat(nf.format(R.price), t('Bağışa yönlendirilen kişi')),
			stat(nf.format(R.donations), t('Açılan bağış kaydı')),
			stat(nf.format(R.confirmed), t('Onaylanan bağış · {a}', { a: R.amount })),
			stat(nf.format(rc.n), t('Okunan dekont · ✓{a} ✕{b}', { a: rc.match, b: rc.bad })),
			stat(nf.format(R.handoffs), t('Ekibe devir')),
			R.memory ? stat(nf.format(R.memory.tokens), t('Hafıza token · {p} kişi', { p: nf.format(R.memory.people) })) : null,
			R.optouts ? stat(nf.format(R.optouts), t('Mesaj istemedi (kara liste)')) : null,
			R.reminders ? stat(nf.format(R.reminders), t('Gönderilen hatırlatıcı')) : null,
			R.vc && (R.vc.email + R.vc.whatsapp + R.vc.sms) ? stat(nf.format(R.vc.email + R.vc.whatsapp + R.vc.sms), t('Gönderilen kod · e-posta {e} · WhatsApp {w} · SMS {s}', { e: R.vc.email, w: R.vc.whatsapp, s: R.vc.sms })) : null,
			R.verified ? stat(nf.format(R.verified), t('Doğrulanan · telefon {p} · e-posta {e}', { p: R.vc ? R.vc.okPhone : 0, e: R.vc ? R.vc.okEmail : 0 })) : null,
			R.vc && (R.vc.wrong + R.vc.expired + R.vc.fail) ? stat(nf.format(R.vc.wrong + R.vc.expired + R.vc.fail), t('Kod sorunu · yanlış {w} · süresi doldu {x} · gitmedi {f}', { w: R.vc.wrong, x: R.vc.expired, f: R.vc.fail })) : null,
			R.vc && R.vc.locked ? stat(nf.format(R.vc.locked), t('Doğrulanamadı, ekibe bildirildi')) : null,
			R.certResent ? stat(nf.format(R.certResent), t('Yeniden gönderilen sertifika')) : null,
			R.canceled ? stat(nf.format(R.canceled), t('Asistanın iptal ettiği bağış')) : null,
			R.paylinks ? stat(nf.format(R.paylinks), t('Yeniden gönderilen ödeme linki')) : null));
		box.appendChild(h('div', { class: 'flxbs-muted flxbs-small' },
			t('Token: {i} giriş / {o} çıkış · {c} istek · {m} mesaj gönderildi', { i: nf.format(R.in), o: nf.format(R.out), c: nf.format(R.calls), m: nf.format(R.messages) }) +
			(R.pending ? ' · ' + t('{n} bağış ödeme bekliyor', { n: R.pending }) : '') + (R.errors ? ' · ' + t('{n} hata', { n: R.errors }) : '') + (R.summaries ? ' · ' + t('{n} sohbet özetlendi', { n: R.summaries }) : '') +
			(R.since ? ' · ' + t('Sayaçlar {d} tarihinden beri', { d: R.since }) : '') + ' · ' + t('30 sn\'de bir yenilenir')));
		if (ST.detail) {
			if (R.models.length) {
				var mb = h('tbody');
				R.models.forEach(function (m) { mb.appendChild(h('tr', null, h('td', { class: 'flxbb-mono', text: m.model }), h('td', { text: nf.format(m.n) }), h('td', { text: nf.format(m.in) }), h('td', { text: nf.format(m.out) }), h('td', null, h('b', { text: money(m.cost) })))); });
				box.appendChild(h('h3', { class: 'flxbs-mt', text: t('Modeller') }));
				box.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' }, h('thead', null, h('tr', null, h('th', { text: t('Model') }), h('th', { text: t('İstek') }), h('th', { text: t('Giriş token') }), h('th', { text: t('Çıkış token') }), h('th', { text: t('Maliyet') }))), mb)));
			}
			if (R.memory && R.memory.top.length) {
				var tb2 = h('tbody');
				R.memory.top.forEach(function (r) { tb2.appendChild(h('tr', null, h('td', null, h('a', { href: r.url, text: r.title })), h('td', { text: nf.format(r.items) }), h('td', null, h('b', { text: nf.format(r.tokens) })))); });
				box.appendChild(h('h3', { class: 'flxbs-mt', text: t('En çok hafızası olan kişiler') }));
				box.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' }, h('thead', null, h('tr', null, h('th', { text: t('Kişi') }), h('th', { text: t('Bilgi') }), h('th', { text: t('Token') }))), tb2)));
			}
			box.appendChild(h('h3', { class: 'flxbs-mt', text: t('Günlük') }));
			if (!R.days.length) box.appendChild(h('div', { class: 'flxbs-empty', text: t('Bu aralıkta kayıt yok.') }));
			else {
				var db = h('tbody');
				R.days.forEach(function (d) { db.appendChild(h('tr', null, h('td', { text: d.day }), h('td', { text: d.turns }), h('td', { text: d.price }), h('td', { text: d.donations }), h('td', { text: d.rc }), h('td', { text: d.handoffs }), h('td', { text: nf.format(d.in) + ' / ' + nf.format(d.out) }), h('td', null, h('b', { text: money(d.cost) })))); });
				box.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' }, h('thead', null, h('tr', null, h('th', { text: t('Gün') }), h('th', { text: t('Cevap') }), h('th', { text: t('Fiyat listesi') }), h('th', { text: t('Bağış kaydı') }), h('th', { text: t('Dekont') }), h('th', { text: t('Devir') }), h('th', { text: t('Token') }), h('th', { text: t('Maliyet') }))), db)));
			}
			box.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: t('Yalnız gerçek sohbetler sayılır; test sohbeti ve benchmark hariç. "Onaylanan" asistanın açtığı ve ödendi işaretlenen bağışlardır.') }));
		}
		if (!ST.timer) statsPoll();
		return box;
	}

	function renderChats() {
		body.appendChild(banner() || h('span'));
		body.appendChild(statsBox());
		var on = 0, off = 0, don = 0;
		D.chats.forEach(function (c) { if (c.status === 'on') on++; if (c.status === 'off' || c.status === 'paused') off++; if (c.donation) don++; });
		body.appendChild(h('div', { class: 'flxbs-stats' },
			stat(D.chats.length, t('Asistanın yazdığı sohbet')), stat(on, t('Asistan açık')), stat(off, t('Ekipte / beklemede')), stat(don, t('Bağış kaydı açılan'))));
		if (!D.chats.length) { body.appendChild(h('div', { class: 'flxbs-empty', text: t('Asistan henüz bir sohbete cevap vermedi.') })); return; }
		var list = h('div', { class: 'flxbs-list' });
		D.chats.forEach(function (c) { list.appendChild(chatCard(c)); });
		body.appendChild(list);
		if (D.chats.length % 40 === 0) body.appendChild(h('button', { type: 'button', class: 'flxbs-btn', onclick: function (e) {
			e.target.disabled = true;
			api('bot/chats', { query: { offset: D.chats.length } }).then(function (r) { D.chats = D.chats.concat(r.chats); render(); }).catch(function (er) { toast(er.message, 1); });
		} }, t('Daha fazla')));
	}
	function stat(n, label) { return h('div', { class: 'flxbs-stat' }, h('b', { text: String(n) }), h('span', { text: label })); }
	function chatCard(c) {
		var s = STATUS[c.status] || STATUS[''];
		return h('a', { class: 'flxbs-card flxbs-chat', href: c.url },
			h('div', { class: 'flxbs-chat-top' },
				h('strong', { text: c.title }),
				h('span', { class: 'flxbs-pill is-' + s[1], text: t(s[0]) }),
				c.chat === 'closed' ? h('span', { class: 'flxbs-pill is-none', text: t('Sohbet kapalı') }) : null,
				h('span', { class: 'flxbs-muted flxbs-right', text: when(c.lastTs) })
			),
			h('div', { class: 'flxbs-muted', text: c.phone + (c.hitap ? ' · ' + t('Hitap: {h}', { h: c.hitap }) : '') + (c.full ? ' · ' + c.full : '') }),
			c.order ? h('div', { class: 'flxbs-order', text: c.order.replace(/\*/g, '') }) : null,
			h('div', { class: 'flxbs-chat-foot' },
				h('span', { text: t('{n} asistan mesajı', { n: c.botMsgs }) }),
				c.donation ? h('span', { text: t('Bağış #{id}', { id: c.donation }) }) : null,
				c.note ? h('span', { class: 'flxbs-muted', text: c.note }) : null
			)
		);
	}

	/* ---------- Talimat ---------- */
	function activeText() {
		var v = D.prompt.versions.filter(function (x) { return x.id === D.prompt.active; })[0];
		return v ? v.text : D.prompt.default;
	}
	function versionLabel(v) { return v ? (v.note || t('Sürüm {id}', { id: v.id })) : t('Yerleşik metin'); }
	function renderPrompt() {
		body.appendChild(banner() || h('span'));
		var ta = h('textarea', { class: 'flxbs-code', rows: '22', spellcheck: 'false' });
		ta.value = activeText();
		var note = h('input', { type: 'text', class: 'flxbs-in', placeholder: t('Değişiklik notu (ör. Kurban sorusu eklendi)') });
		var act = h('input', { type: 'checkbox', checked: true });
		var main = h('section', { class: 'flxbs-card' },
			h('h2', { text: t('Asistan talimatı') }),
			h('p', { class: 'flxbs-muted', text: t('Asistanın nasıl konuşacağını ve akışı anlatan ana metin. Fiyatlar, bağış türleri, banka bilgisi ve kişinin bilinen bilgileri her cevapta otomatik eklenir. {brand} kurum adına, {{belge:kisa-ad}} o belgenin içeriğine dönüşür.') }),
			ta,
			h('div', { class: 'flxbs-row' }, note,
				h('label', { class: 'flxbs-check' }, act, h('span', { text: t('Hemen yayına al') })),
				h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function (e) {
					var b = e.currentTarget; b.disabled = true;
					api('bot/prompt', { method: 'POST', body: { text: ta.value, note: note.value, activate: act.checked } }).then(function (r) {
						D.prompt = r.prompt; toast(t('Yeni sürüm kaydedildi')); render();
					}).catch(function (er) { toast(er.message, 1); b.disabled = false; });
				} }, t('Yeni sürüm olarak kaydet'))
			),
			h('div', { class: 'flxbs-row' },
				h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { showFull(ta.value); } }, t('Modele giden tam metni gör')),
				h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { testDraft = ta.value; testLabel = t('Kaydedilmemiş metin'); tab = 'test'; render(); } }, t('Bu metinle test et')),
				ta.value !== activeText() ? null : null
			)
		);
		var vers = h('section', { class: 'flxbs-card' }, h('h2', { text: t('Sürümler') }));
		var all = D.prompt.versions.concat([null]);
		all.forEach(function (v) {
			var id = v ? v.id : '', live = id === D.prompt.active;
			vers.appendChild(h('div', { class: 'flxbs-ver' + (live ? ' is-live' : '') },
				h('div', null,
					h('strong', { text: versionLabel(v) }),
					live ? h('span', { class: 'flxbs-pill is-on', text: t('Yayında') }) : null,
					h('div', { class: 'flxbs-muted', text: v ? (v.by + ' · ' + when(v.at)) : t('Eklentiyle gelen metin') })
				),
				h('div', { class: 'flxbs-ver-acts' },
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { ta.value = v ? v.text : D.prompt.default; ta.focus(); } }, t('Düzenle')),
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { modal(t('Fark: yayındaki → {v}', { v: versionLabel(v) }), diffView(activeText(), v ? v.text : D.prompt.default)); } }, t('Farkı gör')),
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { testDraft = v ? v.text : D.prompt.default; testLabel = versionLabel(v); tab = 'test'; render(); } }, t('Test et')),
					live ? null : h('button', { type: 'button', class: 'flxbs-link', onclick: function () {
						api('bot/prompt/activate', { method: 'POST', body: { id: id } }).then(function (r) { D.prompt = r.prompt; toast(t('Yayına alındı')); render(); }).catch(function (er) { toast(er.message, 1); });
					} }, t('Yayına al'))
				)
			));
		});
		var tpl = h('section', { class: 'flxbs-card' },
			h('h2', { text: t('Sunucunun gönderdiği hazır mesajlar') }),
			h('p', { class: 'flxbs-muted' }, t('Bu mesajları model yazmaz; ayarlardaki bilgilerden üretilir.'), ' ', h('a', { href: B.settingsUrl + '&tab=ai', text: t('Ayarlarda değiştir') })));
		D.templates.forEach(function (x) {
			tpl.appendChild(h('details', { class: 'flxbs-det' }, h('summary', { text: x.label }), h('pre', { class: 'flxbs-pre', text: x.text || t('(boş)') })));
		});
		body.appendChild(h('div', { class: 'flxbs-cols' }, h('div', null, main, tpl), vers));
	}
	function showFull(text) {
		api('bot/prompt/preview', { method: 'POST', body: { text: text } }).then(function (r) {
			modal(t('Modele giden tam metin (örnek sohbet)'), h('pre', { class: 'flxbs-pre flxbs-pre-lg', text: r.full }));
		}).catch(function (er) { toast(er.message, 1); });
	}
	function modal(title, content, foot) {
		var bg = h('div', { class: 'flxbs-modal-bg', onclick: function (e) { if (e.target === bg) close(); } });
		function close() { bg.remove(); document.removeEventListener('keydown', esc); }
		function esc(e) { if (e.key === 'Escape') close(); }
		document.addEventListener('keydown', esc);
		bg.appendChild(h('div', { class: 'flxbs-modal', role: 'dialog', 'aria-modal': 'true' },
			h('header', null, h('h3', { text: title }), h('button', { type: 'button', class: 'flxbs-x', 'aria-label': t('Kapat'), onclick: close }, '×')),
			h('div', { class: 'flxbs-modal-b' }, content),
			foot ? h('footer', null, foot(close)) : null));
		root.appendChild(bg);
		return close;
	}

	/* ---------- Belgeler ---------- */
	var MODES = [['always', 'Her cevapta talimata eklenir'], ['tool', 'Asistan gerektiğinde okur'], ['off', 'Kapalı']];
	function renderDocs() {
		body.appendChild(h('section', { class: 'flxbs-card' },
			h('h2', { text: t('Ek belgeler') }),
			h('p', { class: 'flxbs-muted', text: t('Sık sorulan sorular, kampanya, dikim yerleri gibi bilgileri ayrı belgeler olarak ekleyin. Talimatta {{belge:kisa-ad}} yazarsanız belge oraya konur. "Gerektiğinde okur" seçilen belgeyi asistan yalnız konu açılınca okur; uzun belgeler için daha az maliyetlidir.') }),
			h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function () { docForm({ mode: 'always' }); } }, t('Yeni belge'))
		));
		if (!D.docs.length) { body.appendChild(h('div', { class: 'flxbs-empty', text: t('Henüz belge yok.') })); return; }
		var list = h('div', { class: 'flxbs-list' });
		D.docs.forEach(function (d) {
			var m = MODES.filter(function (x) { return x[0] === d.mode; })[0] || MODES[0];
			list.appendChild(h('div', { class: 'flxbs-card flxbs-doc' },
				h('div', { class: 'flxbs-chat-top' }, h('strong', { text: d.title }), h('span', { class: 'flxbs-pill is-' + (d.mode === 'off' ? 'none' : 'on'), text: t(m[1]) })),
				h('code', { class: 'flxbs-ref', title: t('Talimatta kullanmak için kopyalayın'), onclick: function () { copy('{{belge:' + d.slug + '}}'); } }, '{{belge:' + d.slug + '}}'),
				h('p', { class: 'flxbs-muted flxbs-clip', text: d.body.slice(0, 220) }),
				h('div', { class: 'flxbs-row' },
					h('button', { type: 'button', class: 'flxbs-link', onclick: function () { docForm(d); } }, t('Düzenle')),
					h('button', { type: 'button', class: 'flxbs-link is-danger', onclick: async function () {
						if (!(await window.flxDialog.confirm({ message: t('“{t}” belgesi silinsin mi?', { t: d.title }), danger: true }))) return;
						api('bot/docs/' + d.id, { method: 'DELETE' }).then(function (r) { D.docs = r.docs; render(); }).catch(function (er) { toast(er.message, 1); });
					} }, t('Sil')))
			));
		});
		body.appendChild(list);
	}
	function copy(s) {
		(navigator.clipboard ? navigator.clipboard.writeText(s) : Promise.reject()).then(function () { toast(t('Kopyalandı')); }, async function () { (await window.flxDialog.prompt({ title: '', value: s })); });
	}
	function docForm(d) {
		var title = h('input', { type: 'text', class: 'flxbs-in', value: d.title || '' });
		var slug = h('input', { type: 'text', class: 'flxbs-in', value: d.slug || '', placeholder: t('otomatik') });
		var mode = h('select', { class: 'flxbs-in' });
		MODES.forEach(function (x) { mode.appendChild(h('option', { value: x[0], selected: x[0] === d.mode, text: t(x[1]) })); });
		var txt = h('textarea', { class: 'flxbs-code', rows: '16' });
		txt.value = d.body || '';
		modal(d.id ? t('Belgeyi düzenle') : t('Yeni belge'), h('div', { class: 'flxbs-form' },
			h('label', null, h('span', { text: t('Başlık') }), title),
			h('div', { class: 'flxbs-grid' }, h('label', null, h('span', { text: t('Kısa ad') }), slug), h('label', null, h('span', { text: t('Kullanım') }), mode)),
			h('label', null, h('span', { text: t('İçerik (Markdown)') }), txt)
		), function (close) {
			return h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function (e) {
				e.currentTarget.disabled = true;
				api('bot/docs', { method: 'POST', body: { id: d.id || '', title: title.value, slug: slug.value, mode: mode.value, body: txt.value } }).then(function (r) {
					D.docs = r.docs; close(); toast(t('Belge kaydedildi')); render();
				}).catch(function (er) { toast(er.message, 1); e.target.disabled = false; });
			} }, t('Kaydet'));
		});
		title.focus();
	}

	/* ---------- Kullanım paneli ve model seçimi (düzenleyici + test) ---------- */
	function zeroUsage() { return { in: 0, out: 0, cost: 0, calls: 0, est: false, cr: 0, cw: 0, full: 0 }; }
	var nf = new Intl.NumberFormat(B.locale || undefined);
	function money(c, est) {
		c = +c || 0;
		var s = '$' + (c === 0 ? '0' : c < 0.01 ? c.toFixed(5) : c.toFixed(4));
		return (est ? '≈ ' : '') + s;
	}
	function usageLine(u) {
		return '↑ ' + nf.format(u.in) + ' ' + t('giriş') + ' · ↓ ' + nf.format(u.out) + ' ' + t('çıkış') + ' · ' + money(u.cost, u.est) + (u.calls > 1 ? ' · ' + t('{n} istek', { n: u.calls }) : '') + (u.model ? ' · ' + u.model : '') + cacheLine(u);
	}
	/* 1.19.0: Claude prompt önbelleği: okunan/yazılan token ve önbelleksiz olsaydı farkı. */
	function cacheLine(u) {
		if (!u || !(u.cr || u.cw)) return '';
		var save = (u.full || 0) - (u.cost || 0);
		return ' · ⚡ ' + t('önbellek {r} okundu, {w} yazıldı', { r: nf.format(u.cr || 0), w: nf.format(u.cw || 0) }) + (Math.abs(save) > 0.000001 ? ' · ' + (save > 0 ? t('tasarruf {c}', { c: money(save) }) : t('ilk yazma +{c}', { c: money(-save) })) : '');
	}
	function cacheLabel(m) {
		return { off: t('Kapalı'), '5m': t('Açık, 5 dakika'), '1h': t('Açık, 1 saat') }[m] || m || '';
	}
	/* Yalnız bu test/çalıştırma için önbellek seçimi; boş = Ayarlar'daki. */
	function cacheSelect(get, set) {
		var sel = h('select', { class: 'flxbs-in flxbs-csel', title: t('Claude prompt önbelleği (yalnız Anthropic anahtarıyla Claude modellerinde)') });
		sel.appendChild(h('option', { value: '', text: t('Önbellek: ayardaki ({m})', { m: cacheLabel(D.cache) }) }));
		['5m', '1h', 'off'].forEach(function (m) { sel.appendChild(h('option', { value: m, text: t('Önbellek: {m}', { m: cacheLabel(m) }) })); });
		sel.value = get() || '';
		sel.addEventListener('change', function () { set(sel.value); });
		return sel;
	}
	function addUsage(U, u) { if (u) { U.in += u.in; U.out += u.out; U.cost += u.cost; U.calls += u.calls; U.est = U.est || u.est; U.cr = (U.cr || 0) + (u.cr || 0); U.cw = (U.cw || 0) + (u.cw || 0); U.full = (U.full || 0) + (u.full != null ? u.full : u.cost); } }
	/* Sağ üst panel: bu konuşma + bu ay toplam. getU() o anki konuşma toplamını verir. */
	function usagePanel(getU) {
		var dash = h('div', { class: 'flxbs-dash', 'aria-live': 'polite' }), month = h('div', { class: 'flxbs-month' });
		function stat2(v, l) { return h('div', { class: 'flxbs-dash-i' }, h('b', { text: v }), h('span', { text: l })); }
		function paint() {
			var U = getU(), M = D.month || zeroUsage();
			clear(dash);
			dash.appendChild(stat2(nf.format(U.in), t('Giriş token')));
			dash.appendChild(stat2(nf.format(U.out), t('Çıkış token')));
			dash.appendChild(stat2(money(U.cost, U.est), t('Maliyet')));
			dash.appendChild(stat2(nf.format(U.calls), t('İstek')));
			if (U.cr || U.cw) dash.appendChild(stat2(money((U.full || 0) - U.cost), t('Önbellek tasarrufu')));
			month.textContent = t('Bu ay toplam (tüm AI)') + ': ↑ ' + nf.format(M.in) + ' · ↓ ' + nf.format(M.out) + ' · ' + money(M.cost) + ' · ' + t('{n} istek', { n: nf.format(M.calls) });
		}
		paint();
		return { dash: dash, month: month, paint: paint };
	}
	/* 1.17.0: ortak model seçici (model-picker.js): sağlayıcı, arama, aile sekmeleri, fiyat süzgeci. */
	function modelSelect(def, get, set) {
		var w = window.FLXCModelPicker.create({ value: get() || '', empty: t('Ana model'), def: def || '', tools: true, onChange: set });
		w.classList.add('flxbs-msel');
		return w;
	}
	/** 1.16.0: model listesi (fiyatlarıyla) yüklenince cb(list). */
	function models(cb) {
		window.FLXCModelPicker.load(function (d) { cb(d.models || []); });
	}
	function usageHead(title, onReset, panel, sel) {
		return h('div', null,
			h('div', { class: 'flxbs-chat-top' }, h('h2', { text: title }),
				h('button', { type: 'button', class: 'flxbs-link', onclick: onReset }, t('Sıfırla')),
				h('div', { class: 'flxbs-right flxbs-dash-wrap' }, panel.dash, sel)),
			h('p', { class: 'flxbs-muted flxbs-small' }, panel.month, h('span', { text: t('Fiyatlar 1M token başına giriş / çıkış (USD). Seçilen model yalnız burada kullanılır.') })));
	}

	/* ---------- AI ile düzenle: kayıtlı oturumlar, onaylı sohbet okuma, ajanın kendi testi ---------- */
	var edModel = '';
	// Sayfa her açılışta boş (kaydedilmemiş) oturumla başlar; eski oturumlar soldaki listeden açılır.
	var ED = { list: null, filter: 'active', q: '', cur: null, busy: false, loading: false };
	function edList() {
		if (ED.loading) return;
		ED.loading = true;
		api('bot/editor/sessions', { query: { status: ED.filter, q: ED.q } }).then(function (r) { ED.list = r.sessions || []; }).catch(function (e) { ED.list = ED.list || []; toast(e.message, 1); }).then(function () { ED.loading = false; if (tab === 'editor') edPaint(); });
	}
	var edPaint = function () {};
	function edUpdate(body, msg) {
		return api('bot/editor/sessions/' + ED.cur.id, { method: 'POST', body: body }).then(function (r) { ED.cur = r.session; if (msg) toast(msg); edList(); edPaint(); }).catch(function (e) { toast(e.message, 1); });
	}
	function renderEditor() {
		body.appendChild(banner() || h('span'));
		if (ED.list === null) edList();
		var side = h('aside', { class: 'flxbs-card flxbs-ed-side' });
		var log = h('div', { class: 'flxbs-chatlog flxbs-ed-log' });
		var tlog = h('div', { class: 'flxbs-chatlog flxbs-wa flxbs-ed-tlog' });
		var head = h('div', { class: 'flxbs-ed-head' });
		var input = h('textarea', { class: 'flxbs-in', rows: '2', placeholder: t('Ör. Ahmet\'in sohbetine bak, fiyat sorusuna daha kısa cevap versin. / Kurban bağışı sorulursa ekibe devretsin.') });
		var btn = h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: send }, t('Gönder'));
		var panel = usagePanel(function () { var u = ED.cur ? ED.cur.usage : null; return u ? { in: u.in || 0, out: u.out || 0, cost: u.cost || 0, calls: u.calls || 0 } : zeroUsage(); });
		var msel = modelSelect(D.modelEdit, function () { return edModel; }, function (v) { edModel = v; });
		var search = h('input', { type: 'search', class: 'flxbs-in', placeholder: t('Oturum ara…'), value: ED.q });
		var sTimer = null;
		search.addEventListener('input', function () { clearTimeout(sTimer); sTimer = setTimeout(function () { ED.q = search.value.trim(); edList(); }, 300); });
		var seg = h('div', { class: 'flxbs-seg' });
		[['active', 'Aktif'], ['archived', 'Arşiv'], ['all', 'Tümü']].forEach(function (f) {
			seg.appendChild(h('button', { type: 'button', class: ED.filter === f[0] ? 'is-on' : '', onclick: function () { ED.filter = f[0]; render(); edList(); } }, t(f[1])));
		});
		var items = h('div', { class: 'flxbs-ed-items' });
		side.appendChild(h('button', { type: 'button', class: 'flxbs-btn is-primary flxbs-ed-new', onclick: function () { if (ED.busy) return; ED.cur = null; edPaint(); input.focus(); } }, t('+ Yeni oturum')));
		side.appendChild(search);
		side.appendChild(seg);
		side.appendChild(items);

		function paintSide() {
			clear(items);
			if (ED.list === null) { items.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: t('Yükleniyor…') })); return; }
			if (!ED.list.length) { items.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: ED.q ? t('Eşleşen oturum yok.') : t('Henüz kayıtlı oturum yok. İlk mesajınızla oturum kendiliğinden kaydedilir.') })); return; }
			ED.list.forEach(function (s) {
				items.appendChild(h('button', { type: 'button', class: 'flxbs-ed-item' + (ED.cur && ED.cur.id === s.id ? ' is-on' : ''), onclick: function () { open(s.id); } },
					h('span', { class: 'flxbs-ed-it', text: s.title || t('Adsız oturum') }),
					h('span', { class: 'flxbs-ed-meta' }, s.updated, s.draft ? h('span', { class: 'flxbs-pill is-paused', text: t('Taslak') }) : null, s.status === 'archived' ? h('span', { class: 'flxbs-pill', text: t('Arşiv') }) : null)));
			});
		}
		function open(id) {
			if (ED.busy) return;
			api('bot/editor/sessions/' + id).then(function (r) { ED.cur = r.session; edPaint(); }).catch(function (e) { toast(e.message, 1); });
		}
		function paintHead() {
			clear(head);
			var S = ED.cur;
			head.appendChild(h('div', { class: 'flxbs-chat-top' },
				h('h2', { text: S ? S.title : t('Yeni oturum') }),
				S ? h('button', { type: 'button', class: 'flxbs-link', onclick: async function () { var v = (await window.flxDialog.prompt({ title: t('Oturum adı'), value: S.title })); if (v && v.trim()) edUpdate({ title: v.trim() }); } }, t('Yeniden adlandır')) : null,
				S ? h('button', { type: 'button', class: 'flxbs-link', onclick: function () { edUpdate({ status: S.status === 'archived' ? 'active' : 'archived' }, S.status === 'archived' ? t('Arşivden çıkarıldı') : t('Arşive alındı')); } }, S.status === 'archived' ? t('Arşivden çıkar') : t('Arşive al')) : null,
				S ? h('button', { type: 'button', class: 'flxbs-link is-danger', onclick: async function () {
					if (!(await window.flxDialog.confirm({ message: t('Bu oturum silinsin mi? Geri alınamaz.'), danger: true }))) return;
					api('bot/editor/sessions/' + S.id, { method: 'DELETE' }).then(function () { ED.cur = null; edList(); edPaint(); toast(t('Silindi')); }).catch(function (e) { toast(e.message, 1); });
				} }, t('Sil')) : null,
				h('div', { class: 'flxbs-right flxbs-dash-wrap' }, panel.dash, msel)));
			head.appendChild(h('p', { class: 'flxbs-muted flxbs-small' }, panel.month, h('span', { text: t('Fiyatlar 1M token başına giriş / çıkış (USD). Seçilen model yalnız burada kullanılır.') })));
			if (S && S.base !== activeText() && !S.draft) head.appendChild(h('div', { class: 'flxbs-note is-warn flxbs-small', text: t('Bu oturumdan sonra yayındaki talimat değişti; yeni bir oturum açmanız önerilir.') }));
		}
		var ICON = { tool: '⚙', proposal: '📝', note: '✓', error: '⚠' };
		function paintLog() {
			clear(log);
			var S = ED.cur;
			if (!S || !S.view.length) {
				log.appendChild(h('div', { class: 'flxbs-hint', text: t('Neyi değiştirmek istediğinizi yazın. Bir kişinin ya da numaranın sohbetinden bahsederseniz yapay zekâ sohbeti bulur, okumak için sizden izin ister, talimatı günceller ve sağdaki test sohbetinde kendisi dener. Yayına almak sizin onayınızla olur.') }));
			} else S.view.forEach(function (v) {
				if (v.t === 'user' || v.t === 'ai') log.appendChild(h('div', { class: 'flxbs-msg is-' + (v.t === 'user' ? 'user' : 'assistant') }, h('div', { class: 'flxbs-bub', text: v.text })));
				else log.appendChild(h('div', { class: 'flxbs-ed-ev is-' + v.t }, (ICON[v.t] || '•') + ' ', v.t === 'proposal' ? t('Taslak güncellendi') + (v.text ? ': ' + v.text : '') : v.text));
			});
			if (S && S.pending) {
				log.appendChild(h('div', { class: 'flxbs-proposal flxbs-ed-ask' },
					h('strong', { text: t('Sohbet okuma izni') }),
					h('p', { text: S.pending.label }),
					h('div', { class: 'flxbs-row' },
						h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function () { run({ session_id: S.id, approve: { ok: true } }); } }, t('İzin ver')),
						h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { run({ session_id: S.id, approve: { ok: true, always: true } }); } }, t('Bu oturumda hep izin ver')),
						h('button', { type: 'button', class: 'flxbs-link', onclick: function () { run({ session_id: S.id, approve: { ok: false } }); } }, t('İzin verme')))));
			}
			if (S && S.draft && S.draft !== S.base && !ED.busy && !S.pending) {
				log.appendChild(h('div', { class: 'flxbs-proposal' },
					h('strong', { text: t('Onayınızı bekleyen taslak') }),
					diffView(S.base, S.draft),
					h('div', { class: 'flxbs-row' },
						h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: function (e) { saveDraft(e, true); } }, t('Onayla ve yayına al')),
						h('button', { type: 'button', class: 'flxbs-btn', onclick: function (e) { saveDraft(e, false); } }, t('Taslak sürüm kaydet')),
						h('button', { type: 'button', class: 'flxbs-btn', onclick: function () { testDraft = S.draft; testLabel = t('Önerilen değişiklik'); tab = 'test'; render(); } }, t('Test sohbetinde aç')),
						h('button', { type: 'button', class: 'flxbs-link', onclick: function () { edUpdate({ draft: 'discarded' }); } }, t('Vazgeç')))));
			}
			if (ED.busy) log.appendChild(h('div', { class: 'flxbs-msg is-assistant' }, h('div', { class: 'flxbs-bub is-wait', text: t('Çalışıyor…') })));
			log.scrollTop = log.scrollHeight;
		}
		function paintTest() {
			clear(tlog);
			var T = ED.cur ? ED.cur.test : null;
			if (!T || !T.history.length) { tlog.appendChild(h('div', { class: 'flxbs-hint', text: t('Yapay zekâ taslağı burada kendisi dener: kişi gibi mesaj yazar, asistanın cevabını görür. WhatsApp\'a bir şey gitmez.') })); }
			else T.history.forEach(function (m) { tlog.appendChild(h('div', { class: 'flxbs-msg is-' + (m.role === 'user' ? 'in' : 'out') }, h('div', { class: 'flxbs-bub', text: m.content }))); });
			if (T && T.bg) tlog.appendChild(bgBox(T.bg, true));
			tlog.scrollTop = tlog.scrollHeight;
		}
		edPaint = function () {
			if (tab !== 'editor') return;
			paintSide(); paintHead(); paintLog(); paintTest(); panel.paint();
			btn.disabled = ED.busy;
		};
		function saveDraft(e, live) {
			var S = ED.cur;
			e.currentTarget.disabled = true;
			api('bot/prompt', { method: 'POST', body: { text: S.draft, note: S.title.slice(0, 120), activate: live } }).then(function (r) {
				D.prompt = r.prompt;
				return edUpdate({ draft: live ? 'published' : 'saved' }, live ? t('Yeni sürüm yayına alındı ✓') : t('Taslak sürüm kaydedildi ✓ (Talimat sekmesinden yayına alabilirsiniz)'));
			}).catch(function (er) { toast(er.message, 1); });
		}
		var guard = 0;
		function run(payload) {
			ED.busy = true;
			edPaint();
			payload.model = edModel;
			api('bot/editor/step', { method: 'POST', body: payload }).then(function (r) {
				var isNew = !ED.cur || ED.cur.id !== r.session.id;
				ED.cur = r.session;
				if (r.month) D.month = r.month;
				if (isNew) edList();
				// Sunucu her istekte tek iş yapar; bitene ya da izin sorulana kadar devam.
				if (!r.done && !r.session.pending && ++guard < 40) { edPaint(); run({ session_id: r.session.id }); return; }
				ED.busy = false; guard = 0; edList(); edPaint();
			}).catch(function (er) { ED.busy = false; guard = 0; toast(er.message, 1); edPaint(); });
		}
		function send() {
			var v = input.value.trim();
			if (!v || ED.busy) return;
			input.value = '';
			if (ED.cur) ED.cur.view.push({ t: 'user', text: v });
			guard = 0;
			run({ session_id: ED.cur ? ED.cur.id : 0, text: v });
		}
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
		body.appendChild(h('div', { class: 'flxbs-ed' },
			side,
			h('section', { class: 'flxbs-card flxbs-chatbox' }, head, log, h('div', { class: 'flxbs-composer' }, input, btn)),
			h('section', { class: 'flxbs-card flxbs-chatbox flxbs-ed-test' },
				h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('Yapay zekânın testi') }),
					h('button', { type: 'button', class: 'flxbs-link flxbs-right', onclick: function () { if (ED.cur && !ED.busy) edUpdate({ test_reset: true }); } }, t('Sıfırla'))),
				h('p', { class: 'flxbs-muted flxbs-small', text: t('Taslakla yapılan deneme; yalnız okunur.') }),
				tlog)));
		edPaint();
	}

	/* "Asistanın bildikleri" (Test sohbeti ve benchmark sonucu). */
	function stateText(s) {
		return [
			t('Hitap') + ': ' + (s.name || '–'), t('Ad soyad') + ': ' + (s.full || '–'), t('E-posta') + ': ' + (s.email || '–'),
			t('Seçili bağış') + ': ' + (s.order ? (s.order.qty + ' × ' + (s.order.label || s.order.option || '')) : '–'),
			t('Bağış kaydı') + ': ' + (s.donation ? '#' + s.donation + ' · ' + t('test') : '–')
		].join('\n');
	}
	/* Test durumundaki hafıza, zamanlanmış işler ve doğrulamalar. */
	var DON_ST = { pending: ['Ödeme bekleniyor', 'paused'], completed: ['Onaylandı', 'on'], canceled: ['İptal', 'off'] };
	/* onDon(i, status): yalnız Test sohbetinde; bağışın durumu test durumunda değişir, gerçek kayda dokunmaz. */
	function bgBox(bg, compact, onDon) {
		var box = h('div', { class: 'flxbs-bg' + (compact ? ' is-compact' : '') });
		function sec(title, list, empty) {
			box.appendChild(h('h3', { text: title }));
			if (!list.length) box.appendChild(h('div', { class: 'flxbs-muted flxbs-small', text: empty }));
			else list.forEach(function (x) { box.appendChild(x); });
		}
		if (bg.code) {
			box.appendChild(h('div', { class: 'flxbs-bg-code' },
				h('span', { class: 'flxbs-small', text: t('Doğrulama kodu (gönderilmedi, yalnız burada)') }),
				h('b', { text: bg.code.code }),
				h('span', { class: 'flxbs-muted flxbs-small', text: bg.code.to + ' · ' + bg.code.channel + ' · ' + t('{h} saatine kadar', { h: bg.code.until }) })));
		}
		sec(t('Test bağışları'), (bg.dons || []).map(function (d) {
			var st = DON_ST[d.status] || [d.status, 'none'];
			return h('div', { class: 'flxbs-bg-i' },
				h('div', { class: 'flxbs-row flxbs-bg-don' }, h('b', { text: '#' + d.id }), h('span', { class: 'flxbs-pill is-' + st[1], text: t(st[0]) }), h('span', { class: 'flxbs-right', text: d.amount })),
				h('div', { class: 'flxbs-muted flxbs-small', text: (d.seed ? t('Örnek geçmiş bağış') + ' · ' : '') + d.title + ' · ' + d.method + ' · ' + d.date }),
				onDon ? h('div', { class: 'flxbs-row flxbs-bg-act' },
					d.status !== 'completed' ? h('button', { type: 'button', class: 'flxbs-link', onclick: function () { onDon(d.i, 'completed'); } }, t('Ödendi say')) : null,
					d.status !== 'pending' ? h('button', { type: 'button', class: 'flxbs-link', onclick: function () { onDon(d.i, 'pending'); } }, t('Beklemeye al')) : null,
					d.status !== 'canceled' ? h('button', { type: 'button', class: 'flxbs-link is-danger', onclick: function () { onDon(d.i, 'canceled'); } }, t('İptal et')) : null) : null);
		}), t('Bu testte bağış açılmadı.'));
		sec(t('Hafıza'), (bg.mem || []).map(function (m) { return h('div', { class: 'flxbs-bg-i' }, h('b', { text: m.k }), ' ', h('span', { text: m.v })); }), t('Henüz bir şey hatırlamadı.'));
		var rems = (bg.rems || []).map(function (r) { return h('div', { class: 'flxbs-bg-i' }, h('b', { text: r.title || r.task }), h('div', { class: 'flxbs-muted flxbs-small', text: r.when + (r.next ? ' · ' + t('Sıradaki: {d}', { d: r.next }) : '') })); });
		if (bg.remDraft) rems.push(h('div', { class: 'flxbs-bg-i is-draft' }, h('b', { text: t('Onay bekleyen hatırlatıcı') }), h('div', { class: 'flxbs-pre', text: bg.remDraft })));
		sec(t('Zamanlanmış'), rems, t('Kurulu hatırlatıcı yok.'));
		var ver = [].concat((bg.phones || []).map(function (p) { return '📱 ' + p; }), (bg.emails || []).map(function (p) { return '✉ ' + p; })).map(function (s) { return h('div', { class: 'flxbs-bg-i', text: s }); });
		if (bg.locked) ver.push(h('div', { class: 'flxbs-bg-i', text: '🔒 ' + t('Kod doğrulaması 24 saat kilitli') }));
		sec(t('Doğrulananlar'), ver, t('Doğrulanmış numara ya da e-posta yok.'));
		return box;
	}

	/* ---------- Test sohbeti ---------- */
	var testDraft = null, testLabel = '', testModel = '', testCache = '';
	function freshTest() { return { history: [], state: {}, logs: [], calls: [], turn: 0, usage: zeroUsage(), bg: null }; }
	var tst = freshTest();
	function renderTest() {
		body.appendChild(banner() || h('span'));
		var src = h('select', { class: 'flxbs-in' });
		src.appendChild(h('option', { value: 'live', text: t('Yayındaki talimat') }));
		if (testDraft !== null) src.appendChild(h('option', { value: 'draft', selected: true, text: testLabel || t('Taslak') }));
		D.prompt.versions.forEach(function (v) { src.appendChild(h('option', { value: 'v:' + v.id, text: versionLabel(v) })); });
		var log = h('div', { class: 'flxbs-chatlog flxbs-wa' });
		var stateBox = h('pre', { class: 'flxbs-pre' });
		var bgWrap = h('div');
		var input = h('textarea', { class: 'flxbs-in', rows: '1', placeholder: t('Bağışçı gibi yazın…') });
		// 1.13.0: personel gibi yazmak (o kullanıcının yetkilerindeki personel araçları açılır).
		var who = null;
		if ((D.staff || []).length) {
			who = h('select', { class: 'flxbs-in' });
			who.appendChild(h('option', { value: '', text: t('Bağışçı (müşteri)') }));
			D.staff.forEach(function (u) { who.appendChild(h('option', { value: String(u.id), text: u.name, selected: String((tst.state || {}).as_user || '') === String(u.id) })); });
			who.addEventListener('change', function () {
				tst = freshTest();
				if (who.value) tst.state.as_user = +who.value;
				input.placeholder = who.value ? t('Personel gibi yazın…') : t('Bağışçı gibi yazın…');
				paint();
			});
		}
		function textFor() {
			if (src.value === 'draft') return testDraft;
			if (src.value.indexOf('v:') === 0) { var id = src.value.slice(2); var v = D.prompt.versions.filter(function (x) { return x.id === id; })[0]; return v ? v.text : ''; }
			return '';
		}
		function paint() {
			clear(log);
			if (!tst.history.length) log.appendChild(h('div', { class: 'flxbs-hint', text: t('Burada yazdıklarınız WhatsApp\'a gitmez, kayıt açılmaz. Asistan gerçek modelle ve seçilen talimatla cevap verir.') }));
			tst.history.forEach(function (m) {
				var bub = h('div', { class: 'flxbs-bub' });
				if (m.files && m.files.length) bub.appendChild(h('div', { class: 'flxbs-att-list' }, m.files.map(attView)));
				var cap = m.show != null ? m.show : m.content;
				if (m.img && m.img.indexOf('data:image/png;base64,') === 0) bub.appendChild(h('img', { class: 'flxbs-widget', src: m.img, alt: t('Görsel') }));
				else if (cap) bub.appendChild(document.createTextNode(cap));
				var tl = null;
				if (m.calls && m.calls.length) {
					tl = h('div', { class: 'flxbs-tools' }, m.calls.map(function (c) { return h('button', { type: 'button', class: 'flxbs-tool-chip' + (c.bad ? ' is-bad' : ''), title: t('Ayrıntı'), onclick: function () { callModal(c); } }, '⚙ ' + toolName(c.tool) + (c.bad ? ' ✕' : '')); }));
				} else if (m.tools) tl = h('div', { class: 'flxbs-tools', text: m.tools });
				log.appendChild(h('div', { class: 'flxbs-msg is-' + (m.role === 'user' ? 'in' : 'out') }, m.auto ? h('div', { class: 'flxbb-scr', text: t('Sistem mesajı') }) : null, bub, tl,
					m.usage ? h('div', { class: 'flxbs-usage', text: usageLine(m.usage) }) : null));
			});
			paintCalls();
			paintDash();
			log.scrollTop = log.scrollHeight;
			stateBox.textContent = stateText(tst.state || {});
			clear(bgWrap).appendChild(bgBox(tst.bg || {}, false, function (i, st) {
				// Yalnız test durumu: asistan sonraki mesajda bağışı bu durumda görür.
				if (tst.state && tst.state.tdons && tst.state.tdons[i]) tst.state.tdons[i].status = st;
				if (tst.bg && tst.bg.dons) tst.bg.dons.forEach(function (d) { if (d.i === i) d.status = st; });
				paint();
			}));
		}
		function send() {
			var v = input.value.trim();
			if (!v && !pend.length) return;
			if (pend.some(function (f) { return f.busy; })) { toast(t('Dosyalar hazırlanıyor, bir saniye…')); return; }
			var files = pend.slice(); pend = []; paintPend();
			var um = { role: 'user', content: v, show: v, files: files.map(function (f) { return { name: f.name, mime: f.mime, thumb: f.thumb }; }) };
			tst.history.push(um);
			input.value = '';
			paint();
			var wait = h('div', { class: 'flxbs-msg is-out' }, h('div', { class: 'flxbs-bub is-wait', text: t('yazıyor…') }));
			log.appendChild(wait); log.scrollTop = log.scrollHeight;
			btn.disabled = true;
			api('bot/test', { method: 'POST', body: { history: tst.history.map(function (m) { return { role: m.role, content: m.content }; }), state: tst.state, text: textFor(), model: testModel, cache: testCache, files: files.map(function (f) { return { name: f.name, data: f.data }; }) } }).then(function (r) {
				// Dosya yer tutucusu ve dekont notu sonraki turlarda modele bu metinle gider.
				if (r.userText != null) um.content = r.userText;
				tst.turn++;
				var calls = (r.log || []).map(function (l) { return { turn: tst.turn, tool: l.tool, args: l.args, result: l.result, bad: !!(l.result && l.result.ok === false) }; });
				tst.calls = tst.calls.concat(calls);
				tst.state = r.state;
				if (r.bg) tst.bg = r.bg;
				var u = r.usage || null;
				addUsage(tst.usage, u);
				if (r.month) D.month = r.month;
				var tools = (r.log || []).map(function (l) { return '⚙ ' + l.tool + (l.result && l.result.ok === false ? ' ✕' : ''); }).join('  ');
				var n0 = tst.history.length;
				if (!r.replies.length) tst.history.push({ role: 'assistant', content: r.reaction ? t('(metin yok, mesaja tepki: {e})', { e: r.reaction }) : (r.state && r.state.closed ? t('(metin yok: sohbet tamamlandı, sessiz kalındı)') : t('(cevap yok)')), tools: tools, calls: calls });
				else if (r.reaction) tst.history.push({ role: 'assistant', content: t('(mesaja tepki: {e})', { e: r.reaction }), tools: '', calls: null, auto: true });
				r.replies.forEach(function (x, i) { tst.history.push({ role: 'assistant', content: x, tools: i === 0 ? tools : '', calls: i === 0 ? calls : null, auto: !!(r.auto && r.auto[i]), img: (r.images && r.images[i]) || '' }); });
				// Turun kullanımı son cevabın altında.
				if (u && tst.history.length > n0) tst.history[tst.history.length - 1].usage = u;
				if (r.handoff) tst.history.push({ role: 'assistant', content: t('— Sohbet ekibe devredildi; gerçekte asistan burada susar. —') });
			}).catch(function (er) {
				// Gönderilemeyen dosyalar geri alınır, yeniden denenebilir.
				if (files.length) { tst.history.splice(tst.history.indexOf(um), 1); pend = files.concat(pend); input.value = v; paintPend(); }
				tst.history.push({ role: 'assistant', content: '⚠ ' + er.message });
			}).then(function () { btn.disabled = false; paint(); input.focus(); });
		}
		/* 1.19.0: dosya ekleme (çoklu, sürükle-bırak, yapıştır). Görseller tarayıcıda küçültülür. */
		var pend = [], pendBox = h('div', { class: 'flxbs-att-pend' });
		var fileIn = h('input', { type: 'file', multiple: true, hidden: true, accept: 'image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv' });
		fileIn.addEventListener('change', function () { addFiles(fileIn.files); fileIn.value = ''; });
		var clip = h('button', { type: 'button', class: 'flxbs-btn flxbs-attbtn', title: t('Dosya ekle (fotoğraf, PDF, belge)'), 'aria-label': t('Dosya ekle'), onclick: function () { fileIn.click(); } }, '📎');
		function addFiles(list) {
			Array.prototype.slice.call(list || []).forEach(function (file) {
				if (pend.length >= 5) { toast(t('En fazla 5 dosya eklenebilir.'), 1); return; }
				var isImg = /^image\//.test(file.type), isPdf = file.type === 'application/pdf';
				var max = isImg ? 20 : isPdf ? 6 : 3;
				if (file.size > max * 1048576) { toast(t('{f} çok büyük (en fazla {n} MB).', { f: file.name, n: max }), 1); return; }
				var f = { name: file.name, mime: file.type || 'application/octet-stream', busy: true, thumb: '' };
				pend.push(f); paintPend();
				var rd = new FileReader();
				rd.onload = function () {
					if (!isImg || file.type === 'image/gif') { f.data = rd.result; f.thumb = isImg ? rd.result : ''; f.busy = false; paintPend(); return; }
					var img = new Image();
					img.onload = function () {
						var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
						c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
						c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
						f.data = c.toDataURL('image/jpeg', 0.85); f.mime = 'image/jpeg'; f.thumb = f.data; f.busy = false; paintPend();
					};
					img.onerror = function () { f.data = rd.result; f.busy = false; paintPend(); };
					img.src = rd.result;
				};
				rd.readAsDataURL(file);
			});
		}
		function paintPend() {
			clear(pendBox);
			pendBox.hidden = !pend.length;
			pend.forEach(function (f, i) {
				pendBox.appendChild(h('div', { class: 'flxbs-att is-pend' + (f.busy ? ' is-busy' : '') }, attInner(f),
					h('button', { type: 'button', class: 'flxbs-att-x', 'aria-label': t('Kaldır'), onclick: function () { pend.splice(i, 1); paintPend(); } }, '×')));
			});
		}
		function attInner(f) {
			return f.thumb && /^image\//.test(f.mime) ? h('img', { src: f.thumb, alt: f.name }) : h('span', { class: 'flxbs-att-doc' }, h('b', { text: /pdf/.test(f.mime) ? 'PDF' : '📄' }), h('span', { text: f.name }));
		}
		function attView(f) { return h('div', { class: 'flxbs-att' }, attInner(f)); }
		function toolName(x) { return x === 'receipt_ai' ? t('dekont ön kontrolü') : x; }
		function callModal(c) {
			var j = function (v) { if (Array.isArray(v) && !v.length) return '{}'; try { return JSON.stringify(v, null, 2); } catch (e) { return String(v); } };
			modal(t('{n}. tur · {t}', { n: c.turn, t: toolName(c.tool) }), h('div', null,
				h('h4', { class: 'flxbs-mt0', text: c.tool === 'receipt_ai' ? t('Okunan dosya') : t('Yapay zekânın gönderdiği (argümanlar)') }), h('pre', { class: 'flxbs-pre flxbs-pre-lg', text: j(c.args) }),
				h('h4', { class: 'flxbs-mt', text: c.tool === 'receipt_ai' ? t('Ön kontrol sonucu') : t('Sistemin döndürdüğü (sonuç)') }), h('pre', { class: 'flxbs-pre flxbs-pre-lg', text: j(c.result) })));
		}
		var callsBox = h('div', { class: 'flxbs-calls' }), callsHead = h('h2', { class: 'flxbs-mt' });
		function paintCalls() {
			clear(callsBox);
			callsHead.textContent = t('Araç çağrıları') + (tst.calls.length ? ' (' + tst.calls.length + ')' : '');
			if (!tst.calls.length) { callsBox.appendChild(h('p', { class: 'flxbs-muted flxbs-small', text: t('Henüz araç çağrılmadı. Çağrılınca burada listelenir; tıklayınca ne gönderildiği ve ne döndüğü görünür.') })); return; }
			var cnt = {};
			tst.calls.forEach(function (c) { cnt[c.tool] = (cnt[c.tool] || 0) + 1; });
			callsBox.appendChild(h('div', { class: 'flxbs-calls-sum' }, Object.keys(cnt).map(function (k) { return h('span', { class: 'flxbs-pill', text: toolName(k) + ' ×' + cnt[k] }); })));
			tst.calls.slice().reverse().forEach(function (c) {
				callsBox.appendChild(h('button', { type: 'button', class: 'flxbs-call' + (c.bad ? ' is-bad' : ''), onclick: function () { callModal(c); } },
					h('span', { class: 'flxbs-muted', text: '#' + c.turn }), h('b', { text: toolName(c.tool) }), h('span', { class: 'flxbs-right', text: c.bad ? '✕' : '✓' })));
			});
		}
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
		var btn = h('button', { type: 'button', class: 'flxbs-btn is-primary', onclick: send }, t('Gönder'));
		/* Canlı kullanım paneli + model seçimi (yalnız bu test için; ana ayar değişmez). */
		var panel = usagePanel(function () { return tst.usage; });
		function paintDash() { panel.paint(); }
		var msel = modelSelect(D.model, function () { return testModel; }, function (v) { testModel = v; });
		if (D.claude) msel = h('div', { class: 'flxbs-msel-wrap' }, msel, cacheSelect(function () { return testCache; }, function (v) { testCache = v; }));
		body.appendChild(h('div', { class: 'flxbs-cols' },
			h('section', { class: 'flxbs-card flxbs-chatbox' },
				usageHead(t('Test sohbeti'), function () { tst = freshTest(); if (who && who.value) tst.state.as_user = +who.value; paint(); }, panel, msel),
				log, pendBox, h('div', { class: 'flxbs-composer' }, clip, fileIn, input, btn)),
			h('section', { class: 'flxbs-card' },
				who ? h('h2', { text: t('Kim gibi yazıyorsunuz?') }) : null, who,
				h('h2', { class: who ? 'flxbs-mt' : '', text: t('Talimat') }), src,
				h('button', { type: 'button', class: 'flxbs-link', onclick: function () { showFull(textFor() || null); } }, t('Tam metni gör')),
				h('h2', { class: 'flxbs-mt', text: t('Asistanın bildikleri') }), stateBox,
				h('h2', { class: 'flxbs-mt', text: t('Arka plan') }), bgWrap,
				callsHead, callsBox)));
		/* Sürükle-bırak ve yapıştırma: sohbet kutusunun her yerine. */
		var box = log.parentNode, depth = 0;
		box.addEventListener('dragenter', function (e) { if (e.dataTransfer && [].indexOf.call(e.dataTransfer.types, 'Files') >= 0) { e.preventDefault(); depth++; box.classList.add('is-drop'); } });
		box.addEventListener('dragover', function (e) { if (box.classList.contains('is-drop')) e.preventDefault(); });
		box.addEventListener('dragleave', function () { if (--depth <= 0) { depth = 0; box.classList.remove('is-drop'); } });
		box.addEventListener('drop', function (e) { depth = 0; box.classList.remove('is-drop'); if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); addFiles(e.dataTransfer.files); input.focus(); } });
		input.addEventListener('paste', function (e) { var fl = e.clipboardData && e.clipboardData.files; if (fl && fl.length) { e.preventDefault(); addFiles(fl); } });
		box.setAttribute('data-drop', t('Dosyaları buraya bırakın'));
		paintPend();
		paint();
		input.focus();
	}

	/* ---------- Kişi hafızası: toplamlar ve en çok hafızası olanlar ---------- */
	var MS = { data: null, busy: false };
	var memFmt = new Intl.DateTimeFormat(B.locale || undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
	function renderMemory() {
		var M = MS.data;
		if (!M) {
			body.appendChild(h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') }));
			if (!MS.busy) { MS.busy = true; api('memory/stats').then(function (r) { MS.data = r; if (tab === 'memory') render(); }).catch(function (e) { toast(e.message, 1); }).then(function () { MS.busy = false; }); }
			return;
		}
		body.appendChild(h('div', { class: 'flxbs-stats' },
			stat(nf.format(M.people), t('Hafızası olan kişi')), stat(nf.format(M.items - M.summaries), t('Kalıcı bilgi')),
			stat(nf.format(M.summaries), t('Konuşma özeti')), stat(nf.format(M.tokens), t('Toplam token (yaklaşık)'))));
		var card = h('section', { class: 'flxbs-card' },
			h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('En çok hafızası olan kişiler') }),
				h('button', { type: 'button', class: 'flxbs-link flxbs-right', onclick: function () { MS.data = null; render(); } }, t('Yenile'))),
			h('p', { class: 'flxbs-muted', text: t('Token, asistanın her cevapta bu kişi için okuduğu hafıza boyutudur (yaklaşık; 3,5 karakter = 1 token).') }));
		if (!M.top.length) card.appendChild(h('div', { class: 'flxbs-empty', text: t('Henüz hafıza kaydı yok.') }));
		else {
			var tb = h('tbody');
			M.top.forEach(function (r) {
				tb.appendChild(h('tr', null,
					h('td', null, h('a', { href: r.url, text: r.title })),
					h('td', { text: r.phone }),
					h('td', { text: nf.format(r.items) }),
					h('td', { text: nf.format(r.summaries) }),
					h('td', null, h('b', { text: nf.format(r.tokens) })),
					h('td', { text: memFmt.format(new Date(r.updated)) }),
					h('td', null, h('button', { type: 'button', class: 'flxbs-link', onclick: function () { showMemory(r); } }, t('Gör')))));
			});
			card.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' }, h('thead', null, h('tr', null,
				h('th', { text: t('Kişi') }), h('th', { text: t('Telefon') }), h('th', { text: t('Bilgi') }), h('th', { text: t('Özet') }), h('th', { text: t('Token') }), h('th', { text: t('Son güncelleme') }), h('th'))), tb)));
		}
		body.appendChild(card);
	}
	function showMemory(r) {
		var box = h('div', null, h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') }));
		modal(t('Kişi hafızası: {c}', { c: r.title }), box);
		api('memory/chat/' + r.chat).then(function (M) {
			clear(box);
			box.appendChild(h('p', { class: 'flxbs-muted', text: t('{n} kayıt · yaklaşık {t} token', { n: M.items.length, t: M.tokens }) }));
			M.items.forEach(function (it) {
				box.appendChild(h('div', { class: 'flxbs-ver' },
					h('div', null, h('strong', { text: it.kind === 'summary' ? t('Konuşma özeti') : it.key }),
						h('div', { class: 'flxbs-muted', text: memFmt.format(new Date(it.kind === 'summary' ? it.created : it.updated)) }),
						h('div', { class: 'flxbs-mem-v', text: it.value }))));
			});
			box.appendChild(h('p', { class: 'flxbs-muted flxbs-small' }, t('Düzenlemek ve silmek için sohbette ⋮ › Kişi hafızası.'), ' ', h('a', { href: r.url, text: t('Sohbeti aç') })));
		}).catch(function (e) { clear(box).appendChild(h('div', { class: 'flxbs-note is-warn', text: e.message })); });
	}

	/* ---------- Arka plan işleri (canlı) ---------- */
	var JS = { data: null, timer: null, busy: false };
	var JOB_ST = { queued: ['Sırada', 'none'], running: ['Çalışıyor', 'on'], done: ['Bitti', 'on'], skipped: ['Atlandı', 'none'], error: ['Hata', 'off'], stale: ['Yarıda kaldı', 'paused'] };
	function loadJobs(post) {
		if (JS.busy) return;
		JS.busy = true;
		(post ? api('jobs/scan', { method: 'POST' }) : api('jobs')).then(function (r) {
			JS.data = r;
			if (post) toast(t('{n} sohbet sıraya alındı', { n: r.queued || 0 }));
			if (tab === 'jobs') { var y = window.scrollY; render(); window.scrollTo(0, y); }
		}).catch(function (e) { toast(e.message, 1); }).then(function () { JS.busy = false; });
	}
	function jobsPoll() {
		clearTimeout(JS.timer);
		JS.timer = setTimeout(function () {
			if (tab === 'jobs' && !document.hidden) loadJobs();
			if (tab === 'jobs') jobsPoll(); else JS.timer = null;
		}, 5000);
	}
	function dur(ms) { return ms < 1000 ? ms + ' ms' : (ms / 1000).toFixed(1) + ' sn'; }
	function renderJobs() {
		var J = JS.data;
		if (!J) { body.appendChild(h('p', { class: 'flxbs-muted', text: t('Yükleniyor…') })); loadJobs(); if (!JS.timer) jobsPoll(); return; }
		if (!JS.timer) jobsPoll();
		if (!J.enabled) body.appendChild(h('div', { class: 'flxbs-note is-warn' }, t('Kişi hafızası kapalı; özet işi çalışmaz.'), ' ', h('a', { href: B.settingsUrl + '&tab=ai', text: t('Ayarlar') })));
		if (J.paused) body.appendChild(h('div', { class: 'flxbs-note is-warn', text: t('Özetler 30 dakika durduruldu: {r}', { r: J.paused }) }));
		var c = J.counts;
		body.appendChild(h('div', { class: 'flxbs-stats' },
			stat(nf.format(c.running + c.bench) + ' / ' + J.max, t('Çalışan işçi')),
			stat(nf.format(c.reply), t('Cevap yazılan sohbet')),
			stat(nf.format(c.summary), t('Özetlenen sohbet')),
			stat(nf.format(c.queued), t('Sırada bekleyen')),
			stat(nf.format(J.waiting), t('Süresi dolmamış sohbet')),
			stat(nf.format(J.today.n) + ' · ' + money(J.today.cost), t('Bugün özet · USD'))));
		var card = h('section', { class: 'flxbs-card' },
			h('div', { class: 'flxbs-chat-top' }, h('h2', { text: t('Son işler') }),
				h('button', { type: 'button', class: 'flxbs-btn flxbs-right', onclick: function () { loadJobs(true); } }, t('Şimdi tara'))),
			h('p', { class: 'flxbs-muted', text: t('Önce yazan kişilere cevap verilir; cevap yazılan ya da sırada bekleyen kişi varken özet başlamaz. {h} saat sessiz kalan sohbetler yalnız {w} arasında özetlenir. Aynı anda en fazla {m} iş çalışır, {r} işçi yalnız cevaplara ayrılır (benchmark bir iş sayılır). 5 sn\'de bir yenilenir.', { h: J.hours, m: J.max, r: J.reserved, w: J.window }) }));
		if (!J.jobs.length) card.appendChild(h('div', { class: 'flxbs-empty', text: t('Henüz iş yok.') }));
		else {
			var tb = h('tbody');
			J.jobs.forEach(function (j) {
				var st = JOB_ST[j.status] || [j.status, 'none'];
				tb.appendChild(h('tr', null,
					h('td', { text: j.kind === 'reply' ? t('Cevap') : (j.kind === 'reminder' ? t('Hatırlatıcı') : t('Özet') + (j.manual ? ' · ' + t('elle') : '')) }),
					h('td', null, h('a', { href: j.url, text: j.title })),
					h('td', null, h('span', { class: 'flxbs-pill is-' + st[1], text: t(st[0]) })),
					h('td', { text: when(j.started || j.created) }),
					h('td', { text: j.finished ? dur(j.ms) : '–' }),
					h('td', { text: j.tokens ? nf.format(j.tokens) : '–' }),
					h('td', { text: j.cost ? money(j.cost) : '–' }),
					h('td', { class: 'flxbs-muted', text: (j.attempts > 1 ? t('{n}. deneme', { n: j.attempts }) + ' · ' : '') + (j.note || '') })));
			});
			card.appendChild(h('div', { class: 'flxbb-scroll' }, h('table', { class: 'flxbb-table' }, h('thead', null, h('tr', null,
				h('th', { text: t('İş') }), h('th', { text: t('Sohbet') }), h('th', { text: t('Durum') }), h('th', { text: t('Başladı') }), h('th', { text: t('Süre') }), h('th', { text: t('Token') }), h('th', { text: t('Maliyet') }), h('th', { text: t('Not') }))), tb)));
		}
		body.appendChild(card);
	}

	/* Benchmark sekmesi bench.js'te; ortak yardımcılar buradan verilir. */
	function renderBench() {
		if (!window.FLXC_BENCH_RENDER) return;
		window.FLXC_BENCH_RENDER({
			h: h, t: t, api: api, clear: clear, toast: toast, when: when, modal: modal, stat: stat, banner: banner, render: render,
			versionLabel: versionLabel, modelSelect: modelSelect, cacheSelect: cacheSelect, cacheLabel: cacheLabel, stateText: stateText, bgBox: bgBox, models: models, money: money, nf: nf, usageLine: usageLine, D: function () { return D; }
		}, body);
	}

	render();
	api('bot/studio').then(function (r) { D = r; render(); }).catch(function (e) { clear(body).appendChild(h('div', { class: 'flxbs-note is-warn', text: e.message })); });
})();
