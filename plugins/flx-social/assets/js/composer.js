/* Flux Social — gönderi oluşturucu (hesap başına metin/medya/seçenek, önizleme, AI, medya seçici) ve gönderi ayrıntısı. */
(function () {
	'use strict';
	var S = window.FLXSM;
	var h = S.h, t = S.t, B = S.B;

	/* ---------------- Ağ seçenekleri ---------------- */
	function optDefs(acc) {
		var pv = acc.provider;
		var titleLabel = { youtube: t('Video başlığı'), pinterest: t('Pin başlığı'), linkedin: t('Bağlantı başlığı'), tiktok: t('Başlık (fotoğraf gönderisi)') }[pv] || t('Başlık');
		return {
			title: { type: 'text', label: titleLabel, max: pv === 'youtube' ? 100 : 100 },
			link: { type: 'url', label: pv === 'pinterest' ? t('Pin bağlantısı') : t('Bağlantı (önizleme kartı)') },
			first_comment: { type: 'area', label: pv === 'x' ? t('Yanıt dizisi (ilk yanıt)') : t('İlk yorum'), hint: t('Yayından hemen sonra yorum olarak eklenir (ör. bağlantı ya da etiketler).') },
			privacy: { type: 'select', label: t('Görünürlük'), opts: [['public', t('Herkese açık')], ['unlisted', t('Liste dışı')], ['private', t('Özel')]] },
			tags: { type: 'tags', label: t('Video etiketleri (virgülle)') },
			flx_category: { type: 'select', label: t('Kategori'), opts: [['', t('Varsayılan')], ['29', t('Kâr amacı gütmeyen ve aktivizm')], ['22', t('Kişiler ve bloglar')], ['27', t('Eğitim')], ['19', t('Seyahat ve etkinlikler')], ['25', t('Haberler ve politika')], ['24', t('Eğlence')]] },
			playlist: { type: 'select', label: t('Oynatma listesi'), opts: [['', t('Yok')]].concat((acc.playlists || []).map(function (p) { return [p.id, p.name]; })) },
			made_for_kids: { type: 'check', label: t('Çocuklara özel') },
			notify: { type: 'check', label: t('Abonelere bildir'), def: true },
			shorts: { type: 'check', label: t('Shorts (dikey, 60 sn altı)') },
			ai_generated: { type: 'check', label: t('AI ile üretilmiş içerik') },
			ig_type: { type: 'select', label: t('Paylaşım türü'), opts: [['feed', t('Gönderi (akış)')], ['reel', t('Reels')], ['story', t('Hikâye')]] },
			share_to_feed: { type: 'check', label: t('Reels akışta da görünsün'), def: true, when: { ig_type: ['reel'] } },
			thumb_url: { type: 'url', label: t('Kapak görseli adresi (Reels)'), when: { ig_type: ['reel'] } },
			li_visibility: { type: 'select', label: t('Görünürlük'), opts: [['PUBLIC', t('Herkese açık')], ['CONNECTIONS', t('Yalnız bağlantılar')]] },
			alt: { type: 'text', label: t('Görsel açıklaması (alt metin)') },
			reply_settings: { type: 'select', label: t('Kimler yanıtlayabilir'), opts: [['', t('Herkes')], ['following', t('Takip ettiklerim')], ['mentionedUsers', t('Andıklarım')], ['verified', t('Onaylı hesaplar')]] },
			board: { type: 'select', label: t('Pano'), opts: [['', t('Pano seçin')]].concat((acc.boards || []).map(function (b) { return [b.id, b.name]; })), req: true },
			tiktok_privacy: { type: 'select', label: t('Kimler görebilir'), opts: [['PUBLIC_TO_EVERYONE', t('Herkes')], ['MUTUAL_FOLLOW_FRIENDS', t('Arkadaşlar')], ['FOLLOWER_OF_CREATOR', t('Takipçiler')], ['SELF_ONLY', t('Yalnız ben')]] },
			disable_comment: { type: 'check', label: t('Yorumları kapat') },
			disable_duet: { type: 'check', label: t('Düet kapalı') },
			disable_stitch: { type: 'check', label: t('Birleştirme (stitch) kapalı') },
			brand_content: { type: 'check', label: t('Ücretli iş birliği (marka içeriği)') },
			brand_organic: { type: 'check', label: t('Kendi markamızın tanıtımı') },
			auto_music: { type: 'check', label: t('Fotoğraflara otomatik müzik') },
			gbp_topic: { type: 'select', label: t('Gönderi türü'), opts: [['STANDARD', t('Güncelleme')], ['EVENT', t('Etkinlik')], ['OFFER', t('Teklif')]] },
			gbp_cta: { type: 'select', label: t('Düğme'), opts: [['', t('Yok')], ['LEARN_MORE', t('Daha fazla bilgi')], ['BOOK', t('Rezervasyon')], ['ORDER', t('Sipariş')], ['SHOP', t('Alışveriş')], ['SIGN_UP', t('Kaydol')], ['CALL', t('Ara')]] },
			gbp_cta_url: { type: 'url', label: t('Düğme bağlantısı') },
			event_title: { type: 'text', label: t('Etkinlik/teklif başlığı'), when: { gbp_topic: ['EVENT', 'OFFER'] } },
			event_start: { type: 'date', label: t('Başlangıç'), when: { gbp_topic: ['EVENT', 'OFFER'] } },
			event_end: { type: 'date', label: t('Bitiş'), when: { gbp_topic: ['EVENT', 'OFFER'] } },
			offer_code: { type: 'text', label: t('Kupon kodu'), when: { gbp_topic: ['OFFER'] } }
		};
	}

	/* ---------------- Kurallar (sunucu da denetler) ---------------- */
	function check(acc, text, media, opts) {
		var c = acc.caps || {};
		var out = [];
		var imgs = media.filter(function (m) { return m.kind === 'image'; }).length;
		var gifs = media.filter(function (m) { return m.kind === 'gif'; }).length;
		var vids = media.filter(function (m) { return m.kind === 'video'; }).length;
		var len = S.len(text, acc.provider);
		if (!String(text || '').trim() && !media.length) out.push(t('Metin ya da medya gerekli.'));
		if (c.max_text && len > c.max_text) out.push(t('Metin {n} karakter uzun (sınır {max}).', { n: len - c.max_text, max: c.max_text }));
		if (c.needs_media && !media.length) out.push(t('Bu ağ görsel ya da video ister.'));
		if (imgs && !c.image) out.push(t('Bu ağ görsel kabul etmiyor.'));
		if (vids && !c.video) out.push(t('Bu ağ video kabul etmiyor.'));
		if (gifs && !c.gif && !c.image) out.push(t('GIF desteklenmiyor.'));
		if (imgs + gifs > (c.max_images || 0) && c.image) out.push(t('En çok {n} görsel.', { n: c.max_images }));
		if (vids > (c.max_videos || 0) && c.video) out.push(t('En çok {n} video.', { n: c.max_videos }));
		if (vids && imgs && !c.mix) out.push(t('Görsel ve video birlikte gönderilemez.'));
		if (acc.provider === 'youtube' && !vids) out.push(t('YouTube için video gerekli.'));
		if (acc.provider === 'pinterest' && !(opts && opts.board)) out.push(t('Pinterest panosu seçin.'));
		if (acc.provider === 'instagram' && opts && opts.ig_type === 'reel' && !vids) out.push(t('Reels için video gerekli.'));
		if (acc.status === 'expired') out.push(t('Hesabın bağlantısı düşmüş; Hesaplar’dan yeniden bağlayın.'));
		return out;
	}

	/* ---------------- Oluşturucu ---------------- */
	S.compose = function (id, o) {
		o = o || {};
		var b = S.st.boot;
		var active = b.accounts.filter(function (a) { return a.status !== 'disabled'; });
		var d = {
			id: 0, title: '', content: '', media: [], tags: [], scheduled_at: o.at || 0, repeat_days: 0, status: 'draft',
			sel: [], tg: {}
		};
		var tab = 'all';
		var dirty = false;
		var shell = h('.flxsm-cmp', h('.flxsm-boot', h('.flxsm-spin')));
		var m = S.modal('', shell, {
			full: true,
			cls: 'flxsm-cmp-modal',
			sticky: true,
			noFocus: !!id,
			onClose: function () {
				if (dirty && !window.confirm(t('Kaydedilmemiş değişiklikler var. Kapatılsın mı?'))) return false;
			}
		});
		if (!active.length) {
			S.clear(shell).appendChild(h('.flxsm-empty', S.icon('users', 30), h('h3', t('Bağlı hesap yok')), h('p', b.user.manage ? t('Önce bir sosyal medya hesabı bağlayın.') : t('Yöneticiden hesap bağlamasını isteyin.')),
				h('.flxsm-row', b.user.manage ? S.btn('plus', t('Hesap bağla'), function () { m.close(); S.go('accounts'); setTimeout(S.connectModal, 300); }, 'is-primary') : null, S.btn('', t('Kapat'), m.close))));
			return;
		}
		if (id) {
			S.api('GET', 'posts/' + id).then(function (p) {
				d.id = p.id; d.title = p.title; d.content = p.content; d.media = p.media || []; d.tags = p.tags || [];
				d.scheduled_at = p.scheduled_at; d.repeat_days = p.repeat_days; d.status = p.status; d.note = p.note; d.user = p.user;
				p.targets.forEach(function (x) {
					if (['published', 'processing', 'deleted'].indexOf(x.status) >= 0) { d.locked = d.locked || []; d.locked.push(x); return; }
					d.sel.push(x.account_id);
					d.tg[x.account_id] = { content: x.content, media: x.media, options: Object.assign({}, x.options || {}) };
				});
				draw();
			}, function (e) { m.close(); S.fail(e); });
		} else {
			var last = S.pref('lastAccounts') || [];
			d.sel = last.filter(function (x) { return active.some(function (a) { return a.id === x; }); });
			if (o.preset) Object.assign(d, o.preset);
			draw();
		}

		function tg(aid) {
			if (!d.tg[aid]) d.tg[aid] = { content: null, media: null, options: {} };
			return d.tg[aid];
		}
		function textOf(aid) { var x = tg(aid); return x.content !== null && x.content !== undefined ? x.content : d.content; }
		function mediaOf(aid) { var x = tg(aid); return x.media ? x.media : d.media; }
		function acc(aid) { return S.account(aid); }
		function touch() { dirty = true; }

		var left, right, foot, tabsEl, editor, preview, warnBox;
		function draw() {
			S.clear(shell);
			m.el.querySelector('.flxsm-modal-body').classList.add('flxsm-cmp-body');
			var head = h('.flxsm-cmp-head', h('h2', d.id ? t('Gönderiyi düzenle') : t('Yeni gönderi')), d.id ? S.badge(d.status) : null, h('span.flxsm-grow'), S.iconBtn('x', t('Kapat'), m.close));
			left = h('.flxsm-cmp-left');
			right = h('.flxsm-cmp-right');
			foot = h('.flxsm-cmp-foot');
			shell.appendChild(head);
			shell.appendChild(h('.flxsm-cmp-cols', left, right));
			shell.appendChild(foot);
			if (d.note && d.status === 'draft') left.appendChild(h('.flxsm-banner', S.icon('msg', 16), h('span', t('Onaylayan not bıraktı: {n}', { n: d.note }))));
			if (d.locked && d.locked.length) left.appendChild(h('.flxsm-banner.is-info', S.icon('check', 16), h('span', t('{n} hesapta yayınlandı; oradaki hali Ayrıntılar › Ağda düzenle ile değişir.', { n: d.locked.length }))));
			left.appendChild(accountPicker());
			tabsEl = h('.flxsm-ctabs');
			editor = h('.flxsm-ceditor');
			warnBox = h('.flxsm-cwarn');
			left.appendChild(tabsEl);
			left.appendChild(editor);
			left.appendChild(extras());
			preview = h('.flxsm-preview');
			right.appendChild(h('.flxsm-prev-h', S.icon('eye', 15), h('span', t('Önizleme'))));
			right.appendChild(preview);
			drawTabs();
			drawEditor();
			drawFoot();
		}

		function accountPicker() {
			var box = h('.flxsm-apick');
			var draw2 = function () {
				S.clear(box);
				active.forEach(function (a) {
					var on = d.sel.indexOf(a.id) >= 0;
					box.appendChild(h('button.flxsm-apick-b' + (on ? '.is-on' : '') + (a.status === 'expired' ? '.is-bad' : ''), { type: 'button', title: a.network + ' › ' + a.name, 'aria-pressed': on ? 'true' : 'false', onclick: function () {
						if (on) { d.sel = d.sel.filter(function (x) { return x !== a.id; }); if (tab === a.id) tab = 'all'; }
						else d.sel.push(a.id);
						S.pref('lastAccounts', d.sel);
						touch();
						draw2();
						drawTabs();
						drawEditor();
					} }, S.avatar(a, 38), on ? h('span.flxsm-apick-ck', S.icon('check', 11)) : null));
				});
				if (b.user.manage) box.appendChild(h('a.flxsm-apick-add', { href: '#/accounts', title: t('Hesap bağla'), onclick: function () { m.close(); } }, S.icon('plus', 16)));
			};
			draw2();
			return h('.flxsm-cblock', h('.flxsm-clabel', t('Hesaplar'), h('small', t('Seçili hesapların hepsine paylaşılır; her birine ayrı metin yazabilirsiniz.'))), box);
		}

		function drawTabs() {
			S.clear(tabsEl);
			tabsEl.appendChild(h('button', { type: 'button', class: 'flxsm-ctab' + (tab === 'all' ? ' is-on' : ''), onclick: function () { tab = 'all'; drawTabs(); drawEditor(); } }, S.icon('layers', 15), h('span', t('Ortak metin'))));
			d.sel.forEach(function (aid) {
				var a = acc(aid);
				if (!a) return;
				var errs = check(a, textOf(aid), mediaOf(aid), tg(aid).options);
				var custom = tg(aid).content !== null && tg(aid).content !== undefined || tg(aid).media;
				tabsEl.appendChild(h('button', { type: 'button', class: 'flxsm-ctab' + (tab === aid ? ' is-on' : '') + (errs.length ? ' is-err' : ''), title: errs.join('\n') || null, onclick: function () { tab = aid; drawTabs(); drawEditor(); } },
					S.avatar(a, 20), h('span', a.name), custom ? h('i.flxsm-ctab-dot', { title: t('Özel metin') }) : null, errs.length ? S.icon('alert', 13) : null));
			});
		}

		function counters(text, list) {
			var box = h('.flxsm-counters');
			var seen = {};
			list.forEach(function (aid) {
				var a = acc(aid);
				if (!a || seen[a.provider]) return;
				seen[a.provider] = 1;
				var max = (a.caps || {}).max_text;
				if (!max) return;
				var n = S.len(text, a.provider);
				box.appendChild(h('span.flxsm-counter' + (n > max ? '.is-over' : n > max * 0.9 ? '.is-near' : ''), { title: a.network }, S.net(a.provider, 14), h('span', n + '/' + max.toLocaleString(S.locale))));
			});
			return box;
		}

		function textArea(val, onInput, ph) {
			var ta = h('textarea.flxsm-input.flxsm-ctextarea', { rows: 7, placeholder: ph || t('Ne paylaşmak istersiniz?'), value: val || '' });
			ta.addEventListener('input', function () { onInput(ta.value); touch(); });
			return ta;
		}

		function drawEditor() {
			S.clear(editor);
			if (tab === 'all') {
				var cnt = h('div');
				var redraw = S.debounce(function () { S.clear(cnt).appendChild(counters(d.content, d.sel.filter(function (x) { return tg(x).content === null || tg(x).content === undefined; }))); drawTabs(); drawPreview(); drawWarn(); }, 120);
				var ta = textArea(d.content, function (v) { d.content = v; redraw(); });
				editor.appendChild(ta);
				var sig = b.settings.signature;
				editor.appendChild(h('.flxsm-ctext-foot', cnt, sig ? h('button.flxsm-btn.is-ghost.is-sm', { type: 'button', title: sig, onclick: function () { d.content = d.content.replace(/\s+$/, '') + (d.content ? '\n\n' : '') + sig; ta.value = d.content; touch(); redraw(); } }, S.icon('edit', 14), h('span', t('İmza ekle'))) : null, aiBar(ta, function (v) { d.content = v; ta.value = v; redraw(); })));
				S.clear(cnt).appendChild(counters(d.content, d.sel.filter(function (x) { return tg(x).content === null || tg(x).content === undefined; })));
				editor.appendChild(mediaBlock(d.media, function (list) { d.media = list; touch(); drawTabs(); drawPreview(); drawWarn(); }));
			} else {
				var aid = tab, a = acc(aid), x = tg(aid);
				var own = x.content !== null && x.content !== undefined;
				var cnt2 = h('div');
				var redraw2 = S.debounce(function () { S.clear(cnt2).appendChild(counters(textOf(aid), [aid])); drawTabs(); drawPreview(); drawWarn(); }, 120);
				var ta2 = textArea(own ? x.content : d.content, function (v) { x.content = v; redraw2(); });
				ta2.disabled = !own;
				var ownCk = h('input', { type: 'checkbox', checked: own, onchange: function (e) {
					x.content = e.target.checked ? textOf(aid) : null;
					touch();
					drawEditor(); drawTabs(); drawPreview();
				} });
				editor.appendChild(h('.flxsm-row.flxsm-cown', S.avatar(a, 28), h('strong', a.network + ' › ' + a.name), h('span.flxsm-grow'), h('label.flxsm-chk', ownCk, h('span', t('Bu hesaba özel metin')))));
				editor.appendChild(ta2);
				editor.appendChild(h('.flxsm-ctext-foot', cnt2, own ? aiBar(ta2, function (v) { x.content = v; ta2.value = v; redraw2(); }, a.provider) : null));
				S.clear(cnt2).appendChild(counters(textOf(aid), [aid]));
				var ownMedia = !!x.media;
				editor.appendChild(h('label.flxsm-chk.flxsm-cown-m', h('input', { type: 'checkbox', checked: ownMedia, onchange: function (e) {
					x.media = e.target.checked ? d.media.slice() : null;
					touch();
					drawEditor(); drawTabs(); drawPreview();
				} }), h('span', t('Bu hesaba ayrı görsel/video'))));
				if (ownMedia) editor.appendChild(mediaBlock(x.media, function (list) { x.media = list; touch(); drawTabs(); drawPreview(); drawWarn(); }, a));
				editor.appendChild(optionsBlock(a, x));
			}
			drawPreview();
			drawWarn();
		}

		/* Ağ seçenekleri */
		function optionsBlock(a, x) {
			var keys = (a.caps && a.caps.options) || [];
			if (!keys.length) return null;
			var defs = optDefs(a);
			var box = h('.flxsm-copts');
			var drawO = function () {
				S.clear(box);
				box.appendChild(h('.flxsm-clabel', t('{net} seçenekleri', { net: a.network })));
				var grid = h('.flxsm-copts-g');
				keys.forEach(function (k) {
					var df = defs[k];
					if (!df) return;
					if (df.when && !Object.keys(df.when).every(function (w) {
						var cur = x.options[w];
						if (cur === undefined || cur === '') cur = defs[w] && defs[w].opts ? defs[w].opts[0][0] : '';
						return df.when[w].indexOf(cur) >= 0;
					})) return;
					var v = x.options[k];
					var set = function (val) { if (val === '' || val === null || val === undefined) delete x.options[k]; else x.options[k] = val; touch(); drawTabs(); drawPreview(); drawWarn(); };
					var input;
					if (df.type === 'check') {
						var on = v === undefined ? !!df.def : !!v;
						input = h('label.flxsm-chk', h('input', { type: 'checkbox', checked: on, onchange: function (e) { set(e.target.checked); } }), h('span', df.label));
						grid.appendChild(h('.flxsm-copt.is-check', input));
						return;
					}
					if (df.type === 'select') {
						input = h('select.flxsm-input', { onchange: function (e) { set(e.target.value); if (k === 'gbp_topic' || k === 'ig_type') drawO(); } }, df.opts.map(function (op) { return h('option', { value: op[0], selected: String(v || '') === String(op[0]) }, op[1]); }));
					} else if (df.type === 'area') {
						input = h('textarea.flxsm-input', { rows: 2, value: v || '', oninput: function (e) { set(e.target.value); } });
					} else if (df.type === 'date') {
						input = h('input.flxsm-input', { type: 'date', value: v || '', onchange: function (e) { set(e.target.value); } });
					} else if (df.type === 'tags') {
						input = h('input.flxsm-input', { type: 'text', value: (v || []).join(', '), oninput: function (e) { set(e.target.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)); } });
					} else {
						input = h('input.flxsm-input', { type: df.type === 'url' ? 'url' : 'text', value: v || '', maxlength: df.max || null, oninput: function (e) { set(e.target.value); } });
					}
					grid.appendChild(h('.flxsm-copt' + (df.type === 'area' ? '.is-wide' : ''), h('label', df.label + (df.req ? ' *' : '')), input, df.hint ? h('small.flxsm-muted', df.hint) : null));
				});
				box.appendChild(grid);
				if (a.provider === 'tiktok') box.appendChild(h('p.flxsm-muted.is-small', b.settings.tiktok_mode === 'direct' ? t('Doğrudan yayın: video TikTok’ta seçilen görünürlükle yayınlanır.') : t('Taslak modu: video TikTok uygulamanızın gelen kutusuna düşer, oradan yayınlarsınız.')));
				if (a.provider === 'pinterest' && !(a.boards || []).length) box.appendChild(h('p.flxsm-muted.is-small', t('Pano listesi boş. Hesaplar › Bağlantıyı sına ile yenileyin.')));
			};
			drawO();
			return box;
		}

		/* AI */
		function aiBar(ta, set, provider) {
			if (!b.ai) return null;
			var run = function (mode, extra) {
				return function (e) {
					var btn = e.currentTarget;
					var text = ta.value;
					if (mode !== 'write' && !text.trim()) { S.toast(t('Önce bir metin yazın.'), 'error'); return; }
					btn.classList.add('is-busy');
					var provs = provider ? [provider] : d.sel.map(function (x) { var a = acc(x); return a && a.provider; }).filter(Boolean);
					S.api('POST', 'ai', Object.assign({ mode: mode, text: text, providers: provs }, extra || {})).then(function (r) {
						btn.classList.remove('is-busy');
						if (mode === 'hashtags') set(text.replace(/\s+$/, '') + '\n\n' + r.result);
						else set(r.result);
						touch();
					}, function (er) { btn.classList.remove('is-busy'); S.fail(er); });
				};
			};
			var bar = h('.flxsm-aibar',
				h('button.flxsm-btn.is-ghost.is-ai', { type: 'button', onclick: function (e) {
					var anchor = e.currentTarget;
					S.prompt(t('AI ne yazsın?'), '', t('Yaz')).then(function (brief) {
						if (!brief) return;
						anchor.classList.add('is-busy');
						var provs = provider ? [provider] : d.sel.map(function (x) { var a = acc(x); return a && a.provider; }).filter(Boolean);
						S.api('POST', 'ai', { mode: 'write', brief: brief, text: ta.value, providers: provs }).then(function (r) { anchor.classList.remove('is-busy'); set(r.result); touch(); }, function (er) { anchor.classList.remove('is-busy'); S.fail(er); });
					});
				} }, S.icon('sparkle', 15), h('span', t('AI ile yaz'))),
				S.iconBtn('wand', t('Düzelt ve güzelleştir'), run('improve')),
				S.iconBtn('compare', t('Kısalt'), function (e) {
					var lim = 280;
					var provs = provider ? [provider] : d.sel.map(function (x) { var a = acc(x); return a && a.provider; });
					provs.forEach(function (p) { var c = b.providers[p] && b.providers[p].caps; if (c && c.max_text) lim = Math.min(lim, c.max_text); });
					run('shorten', { limit: lim })(e);
				}),
				S.iconBtn('hash', t('Etiket öner'), run('hashtags')),
				S.iconBtn('globe', t('İngilizceye çevir'), run('translate', { to: 'English' })),
				!provider && d.sel.length > 1 ? h('button.flxsm-btn.is-ghost', { type: 'button', title: t('Her hesaba kendi ağının üslubunda ayrı metin yazar'), onclick: function (e) {
					var btn = e.currentTarget;
					if (!ta.value.trim()) { S.toast(t('Önce bir metin yazın.'), 'error'); return; }
					var provs = [];
					d.sel.forEach(function (x) { var a = acc(x); if (a && provs.indexOf(a.provider) < 0) provs.push(a.provider); });
					btn.classList.add('is-busy');
					S.api('POST', 'ai', { mode: 'adapt', text: ta.value, providers: provs }).then(function (r) {
						btn.classList.remove('is-busy');
						d.sel.forEach(function (x) { var a = acc(x); if (a && r.result[a.provider]) tg(x).content = r.result[a.provider]; });
						touch();
						drawTabs();
						drawPreview();
						S.toast(t('Her hesaba ayrı metin yazıldı; sekmelerden bakın.'));
					}, function (er) { btn.classList.remove('is-busy'); S.fail(er); });
				} }, S.icon('layers', 15), h('span', t('Ağlara uyarla'))) : null
			);
			return bar;
		}

		/* Medya */
		function mediaBlock(list, onChange, forAcc) {
			var box = h('.flxsm-cmedia');
			var drawM = function () {
				S.clear(box);
				list.forEach(function (it, i) {
					box.appendChild(h('.flxsm-mtile', { title: it.name || '' },
						it.kind === 'video' ? (it.thumb ? h('img', { src: it.thumb, alt: '' }) : h('video', { src: it.url, muted: true, preload: 'metadata' })) : h('img', { src: it.thumb || it.url, alt: '' }),
						it.kind === 'video' ? h('span.flxsm-mkind', S.icon('video', 12), it.duration ? Math.round(it.duration) + ' sn' : '') : null,
						h('.flxsm-mtile-acts',
							i > 0 ? S.iconBtn('chevL', t('Sola al'), function () { list.splice(i - 1, 0, list.splice(i, 1)[0]); onChange(list); drawM(); }, 'is-xs') : null,
							S.iconBtn('x', t('Kaldır'), function () { list.splice(i, 1); onChange(list); drawM(); }, 'is-xs'))
					));
				});
				box.appendChild(h('button.flxsm-madd', { type: 'button', onclick: function () {
					S.pickMedia(function (items) { items.forEach(function (x) { if (!list.some(function (y) { return y.id === x.id; })) list.push(x); }); onChange(list); drawM(); });
				} }, S.icon('image', 20), h('span', t('Görsel / video'))));
			};
			drawM();
			return h('.flxsm-cblock', box, forAcc ? null : h('p.flxsm-muted.is-small', t('İpucu: Instagram, TikTok ve YouTube görsel/video ister; X’te 4 görsele kadar.')));
		}

		/* Başlık, etiket, zaman */
		function extras() {
			var open = !!(d.title || d.tags.length || d.repeat_days);
			var box = h('details.flxsm-cextra', { open: open },
				h('summary', t('Ek bilgiler (iç başlık, etiket, tekrar)')),
				h('.flxsm-copts-g',
					h('.flxsm-copt', h('label', t('İç başlık (yalnız listede görünür)')), h('input.flxsm-input', { type: 'text', value: d.title, maxlength: 190, oninput: function (e) { d.title = e.target.value; touch(); } })),
					h('.flxsm-copt', h('label', t('Etiketler (virgülle; filtrelemek için)')), h('input.flxsm-input', { type: 'text', value: d.tags.join(', '), oninput: function (e) { d.tags = e.target.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean); touch(); } })),
					h('.flxsm-copt', h('label', t('Yayından kaç gün sonra tekrar paylaşılsın (0 = hayır)')), h('input.flxsm-input', { type: 'number', min: 0, max: 365, value: d.repeat_days || 0, oninput: function (e) { d.repeat_days = Math.max(0, +e.target.value || 0); touch(); } }))
				));
			return box;
		}

		/* Önizleme */
		function drawPreview() {
			if (!preview) return;
			S.clear(preview);
			var ids = tab === 'all' ? d.sel.slice(0, 3) : [tab];
			if (!ids.length) { preview.appendChild(h('p.flxsm-muted', t('Hesap seçince önizleme burada görünür.'))); return; }
			ids.forEach(function (aid) { var a = acc(aid); if (a) preview.appendChild(card(a, textOf(aid), mediaOf(aid), tg(aid).options)); });
			if (tab === 'all' && d.sel.length > 3) preview.appendChild(h('p.flxsm-muted.is-small', t('+{n} hesap daha; sekmelerden bakın.', { n: d.sel.length - 3 })));
		}
		function linkify(text) {
			var out = h('.flxsm-pv-text');
			String(text || '').split(/(https?:\/\/\S+|#[\p{L}\p{N}_]+|@[\w.]+)/u).forEach(function (part, i) {
				if (i % 2) out.appendChild(h('span.flxsm-pv-link', part));
				else out.appendChild(document.createTextNode(part));
			});
			return out;
		}
		function mediaGrid(list, square) {
			if (!list.length) return null;
			var g = h('.flxsm-pv-media.is-n' + Math.min(list.length, 4) + (square ? '.is-square' : ''));
			list.slice(0, 4).forEach(function (it, i) {
				g.appendChild(h('.flxsm-pv-m', it.kind === 'video' ? (it.thumb ? h('img', { src: it.thumb, alt: '' }) : h('video', { src: it.url, muted: true, preload: 'metadata' })) : h('img', { src: it.thumb || it.url, alt: '' }),
					it.kind === 'video' ? h('span.flxsm-pv-play', S.icon('play', 18)) : null,
					i === 3 && list.length > 4 ? h('span.flxsm-pv-more', '+' + (list.length - 4)) : null));
			});
			return g;
		}
		function card(a, text, media, opts) {
			var pv = a.provider;
			var errs = check(a, text, media, opts);
			var shown = text;
			var max = (a.caps || {}).max_text;
			var head = h('.flxsm-pv-head', S.avatar(a, 34), h('.flxsm-pv-n', h('strong', a.name), h('small', pv === 'x' || pv === 'instagram' || pv === 'tiktok' ? '@' + String(a.username || a.name).replace(/^@/, '') : a.network)), S.net(pv, 18));
			var body;
			if (pv === 'instagram' || pv === 'tiktok' || pv === 'pinterest') {
				body = [mediaGrid(media, pv === 'instagram') || h('.flxsm-pv-nomedia', S.icon('image', 22), h('span', t('Görsel/video yok'))), pv === 'pinterest' && opts.title ? h('strong.flxsm-pv-title', opts.title) : null, shown ? linkify(shown) : null];
			} else if (pv === 'youtube') {
				body = [mediaGrid(media.filter(function (x) { return x.kind === 'video'; }).slice(0, 1)) || h('.flxsm-pv-nomedia', S.icon('video', 22), h('span', t('Video yok'))), h('strong.flxsm-pv-title', opts.title || String(text || '').split('\n')[0].slice(0, 100) || t('(başlıksız)')), shown ? linkify(shown) : null];
			} else {
				body = [shown ? linkify(shown) : null, mediaGrid(media), opts.link && !media.length ? h('.flxsm-pv-linkcard', S.icon('link', 14), h('span', opts.link)) : null];
			}
			return h('.flxsm-pv.is-' + pv + (errs.length ? '.has-err' : ''),
				head, h('.flxsm-pv-body', body),
				opts.first_comment ? h('.flxsm-pv-comment', S.icon('msg', 13), h('span', opts.first_comment)) : null,
				max && S.len(text, pv) > max ? h('.flxsm-pv-cut', t('Sınır aşıldı: {n} karakter fazla', { n: S.len(text, pv) - max })) : null,
				errs.length ? h('ul.flxsm-pv-errs', errs.map(function (e) { return h('li', e); })) : null
			);
		}

		function allErrors() {
			var out = [];
			d.sel.forEach(function (aid) {
				var a = acc(aid);
				if (!a) return;
				check(a, textOf(aid), mediaOf(aid), tg(aid).options).forEach(function (e) { out.push(a.name + ': ' + e); });
			});
			return out;
		}
		function drawWarn() {
			if (!foot) return;
			var errs = allErrors();
			S.clear(warnBox);
			if (errs.length) warnBox.appendChild(h('span', { title: errs.join('\n') }, S.icon('alert', 14), t('{n} sorun', { n: errs.length })));
		}

		/* Alt çubuk */
		function drawFoot() {
			S.clear(foot);
			var u = b.user;
			var now = Math.floor(Date.now() / 1000);
			var when = h('input.flxsm-input.flxsm-when', { type: 'datetime-local', value: d.scheduled_at > now ? S.toLocalInput(d.scheduled_at) : '', min: S.toLocalInput(now), 'aria-label': t('Paylaşım zamanı') });
			when.addEventListener('change', function () { d.scheduled_at = S.fromLocalInput(when.value); touch(); drawFoot(); });
			var quick = h('button.flxsm-btn.is-ghost', { type: 'button', title: t('Hızlı zaman'), onclick: function (e) {
				var p = S.parts(now);
				var mk = function (dd, hh) { return S.mk(p.y, p.m, p.d + dd, hh, 0); };
				var set = function (ts) { return function () { d.scheduled_at = ts; touch(); drawFoot(); }; };
				S.menu(e.currentTarget, [
					{ icon: 'clock', label: t('1 saat sonra'), onClick: set(now + 3600 - (now % 300)) },
					p.h < 18 ? { icon: 'clock', label: t('Bugün 19:00'), onClick: set(mk(0, 19)) } : null,
					{ icon: 'clock', label: t('Yarın 10:00'), onClick: set(mk(1, 10)) },
					{ icon: 'clock', label: t('Yarın 19:00'), onClick: set(mk(1, 19)) },
					{ icon: 'clock', label: t('Hafta sonu 11:00'), onClick: set(mk((6 - p.w + 7) % 7 || 7, 11)) },
					d.scheduled_at ? { sep: true } : null,
					d.scheduled_at ? { icon: 'x', label: t('Zamanı kaldır'), onClick: set(0) } : null
				].filter(Boolean));
			} }, S.icon('clock', 15));
			var save = function (action, btn) {
				var payload = {
					title: d.title, content: d.content, media: d.media.map(function (x) { return x.id; }), tags: d.tags,
					scheduled_at: d.scheduled_at || 0, repeat_days: d.repeat_days || 0, action: action,
					targets: d.sel.map(function (aid) {
						var x = tg(aid);
						return { account_id: aid, content: x.content === undefined ? null : x.content, media: x.media ? x.media.map(function (y) { return y.id; }) : null, options: x.options };
					})
				};
				if (!d.sel.length && action !== 'draft') { S.toast(t('En az bir hesap seçin.'), 'error'); return; }
				if (action === 'schedule' && (!d.scheduled_at || d.scheduled_at < now)) { S.toast(t('Gelecekte bir zaman seçin.'), 'error'); return; }
				btn.classList.add('is-busy');
				S.api('POST', d.id ? 'posts/' + d.id : 'posts', payload).then(function (p) {
					dirty = false;
					d.id = p.id;
					btn.classList.remove('is-busy');
					var msg = { draft: t('Taslak kaydedildi.'), submit: t('Onaya gönderildi.'), schedule: t('Zamanlandı: {t}', { t: S.fmt(p.scheduled_at) }), now: t('Sıraya alındı, birazdan yayında.'), approve: t('Onaylandı.') }[action] || t('Kaydedildi.');
					if (p.status === 'pending' && (action === 'schedule' || action === 'now')) msg = t('Yayın yetkiniz olmadığı için onaya gönderildi.');
					S.toast(msg);
					if (action === 'now' || action === 'approve') S.nudge();
					m.close();
					S.reloadBoot();
					if (o.onSaved) o.onSaved(p);
					else if (S.onTick) S.onTick();
				}, function (e) { btn.classList.remove('is-busy'); S.fail(e); });
			};
			var mk2 = function (cls, icon, label, action) {
				var bt = h('button.flxsm-btn' + cls, { type: 'button' }, icon ? S.icon(icon, 15) : null, h('span', label));
				bt.addEventListener('click', function () { save(action, bt); });
				return bt;
			};
			var future = d.scheduled_at && d.scheduled_at > now;
			foot.appendChild(h('.flxsm-row.flxsm-cfoot-l', S.icon('calendar', 16), when, quick, warnBox, h('span.flxsm-muted.is-small.flxsm-tz', st0())));
			var acts = h('.flxsm-row.flxsm-cfoot-r', mk2('.is-ghost', 'edit', t('Taslak kaydet'), 'draft'));
			if (u.publish) {
				if (d.status === 'pending') acts.appendChild(mk2('', 'check', t('Onayla'), 'approve'));
				if (future) acts.appendChild(mk2('.is-primary', 'clock', t('Zamanla'), 'schedule'));
				else acts.appendChild(mk2('.is-primary', 'send', t('Şimdi paylaş'), 'now'));
			} else {
				acts.appendChild(mk2('.is-primary', 'send', t('Onaya gönder'), 'submit'));
			}
			foot.appendChild(acts);
			drawWarn();
		}
		function st0() { return b.tz; }
	};

	/* ---------------- Medya seçici ---------------- */
	S.pickMedia = function (done) {
		var b = S.st.boot;
		var chosen = [];
		var tabs = [['lib', t('Ortam kütüphanesi')]];
		if (b.studio) tabs.push(['studio', t('Flux Studio')]);
		var cur = 'lib';
		var body = h('.flxsm-mp');
		var seg = h('.flxsm-seg.is-small');
		var grid = h('.flxsm-mp-grid');
		var q = h('input.flxsm-input', { type: 'search', placeholder: t('Ara'), style: { maxWidth: '220px' } });
		var file = h('input', { type: 'file', accept: 'image/*,video/*', multiple: true, hidden: true });
		var page = 1;
		var count = h('span.flxsm-muted.is-small');
		tabs.forEach(function (tb) {
			seg.appendChild(h('button', { type: 'button', class: 'flxsm-seg-b' + (cur === tb[0] ? ' is-on' : ''), onclick: function (e) {
				cur = tb[0];
				Array.prototype.forEach.call(seg.children, function (c) { c.classList.toggle('is-on', c === e.currentTarget); });
				page = 1; load();
			} }, tb[1]));
		});
		q.addEventListener('input', S.debounce(function () { page = 1; load(); }, 350));
		var upBtn = b.user.upload ? S.btn('upload', t('Yükle'), function () { file.click(); }) : null;
		var urlBtn = b.user.upload ? S.btn('link', t('Adresten ekle'), function () {
			S.prompt(t('Görsel ya da video adresi (https://…)'), '', t('Ekle')).then(function (u) {
				if (!u) return;
				S.toast(t('İndiriliyor…'));
				S.api('POST', 'media', { url: u }).then(function (it) { chosen.push(it); finish(); }, S.fail);
			});
		}) : null;
		file.addEventListener('change', function () {
			var files = Array.prototype.slice.call(file.files || []);
			if (!files.length) return;
			var n = files.length;
			S.toast(t('{n} dosya yükleniyor…', { n: n }));
			var chain = Promise.resolve();
			files.forEach(function (f) {
				chain = chain.then(function () {
					var fd = new FormData();
					fd.append('file', f);
					return S.api('POST', 'media', fd).then(function (it) { chosen.push(it); });
				});
			});
			chain.then(finish, S.fail);
		});
		body.appendChild(h('.flxsm-row.flxsm-mp-top', tabs.length > 1 ? seg : null, q, h('span.flxsm-grow'), upBtn, urlBtn, file));
		body.appendChild(grid);
		body.appendChild(h('.flxsm-row', h('button.flxsm-link', { type: 'button', onclick: function () { page++; load(true); } }, t('Daha fazla'))));
		var m = S.modal(t('Görsel / video seç'), body, {
			wide: true,
			foot: [count, h('span.flxsm-grow'), h('button.flxsm-btn', { type: 'button', onclick: function () { m.close(); } }, t('Vazgeç')), h('button.flxsm-btn.is-primary', { type: 'button', onclick: finish }, t('Ekle'))]
		});
		function finish() {
			var need = chosen.filter(function (x) { return !x.id && x._studio; });
			if (!need.length) { m.close(); done(chosen.filter(function (x) { return x.id; })); return; }
			S.toast(t('Stüdyo dosyaları kütüphaneye alınıyor…'));
			Promise.all(need.map(function (x) { return S.api('POST', 'media', { url: x.url }).then(function (it) { x.id = it.id; Object.assign(x, it); }); })).then(function () { m.close(); done(chosen.filter(function (x) { return x.id; })); }, S.fail);
		}
		function tile(it) {
			var on = chosen.some(function (x) { return (x.id && x.id === it.id) || (x._key && x._key === it._key); });
			var el = h('button.flxsm-mp-i' + (on ? '.is-on' : ''), { type: 'button', title: it.name || '', onclick: function () {
				var idx = -1;
				chosen.forEach(function (x, i) { if ((x.id && x.id === it.id) || (x._key && x._key === it._key)) idx = i; });
				if (idx >= 0) chosen.splice(idx, 1); else chosen.push(it);
				el.classList.toggle('is-on', idx < 0);
				count.textContent = chosen.length ? t('{n} seçili', { n: chosen.length }) : '';
			} }, it.kind === 'video' && !it.thumb ? h('video', { src: it.url, muted: true, preload: 'metadata' }) : h('img', { src: it.thumb || it.url, alt: '', loading: 'lazy' }), it.kind === 'video' ? h('span.flxsm-mkind', S.icon('video', 12)) : null, h('span.flxsm-mp-ck', S.icon('check', 12)));
			return el;
		}
		function load(append) {
			if (!append) S.clear(grid).appendChild(h('.flxsm-boot', h('.flxsm-spin')));
			var p = cur === 'lib' ? S.api('GET', 'media?page=' + page + '&q=' + encodeURIComponent(q.value)) : S.api('GET', 'media/studio');
			p.then(function (r) {
				if (!append) S.clear(grid);
				var items = r.items || [];
				if (cur === 'studio') items = items.map(function (x) { return { id: +x.attachment_id || 0, url: x.url, thumb: x.thumb, kind: x.kind === 'video' ? 'video' : 'image', name: x.title || x.prompt, _studio: 1, _key: 'st' + x.id }; });
				if (!items.length && !append) grid.appendChild(h('p.flxsm-muted', t('Dosya yok.')));
				items.forEach(function (it) { grid.appendChild(tile(it)); });
			}, function (e) { S.clear(grid).appendChild(h('p.flxsm-err', e.message)); });
		}
		load();
	};

	/* ---------------- Gönderi ayrıntısı ---------------- */
	var detail = null;
	S.closeDetail = function () { if (detail) { detail.close(); detail = null; } };
	S.postDetail = function (id, after) {
		var b = S.st.boot;
		var body = h('.flxsm-detail', h('.flxsm-boot', h('.flxsm-spin')));
		var m = S.modal(t('Gönderi'), body, { wide: true, onClose: function () { detail = null; } });
		detail = m;
		var refresh = function () { load(); after && after(); };
		function load() {
			S.api('GET', 'posts/' + id).then(function (p) { draw(p); }, function (e) { S.clear(body).appendChild(h('p.flxsm-err', e.message)); });
		}
		function draw(p) {
			S.clear(body);
			var tm = S.postTime(p);
			body.appendChild(h('.flxsm-row.flxsm-detail-h', S.badge(p.status), tm ? h('span.flxsm-muted', S.icon('clock', 14), ' ', S.fmt(tm, { year: 'numeric' })) : null, p.user ? h('span.flxsm-muted', S.icon('user', 14), ' ', p.user) : null, h('span.flxsm-grow'),
				S.iconBtn('dots', t('İşlemler'), function (e) { S.postMenu(e.currentTarget, p, refresh); })));
			if (p.title) body.appendChild(h('h3', p.title));
			if (p.content) body.appendChild(h('.flxsm-detail-text', p.content));
			if (p.media && p.media.length) body.appendChild(h('.flxsm-detail-media', p.media.map(function (x) { return h('a', { href: x.url, target: '_blank', rel: 'noopener' }, h('img', { src: x.thumb || x.url, alt: '' })); })));
			if (p.note) body.appendChild(h('.flxsm-banner', S.icon('msg', 15), h('span', p.note)));
			var tbl = h('.flxsm-tlist');
			p.targets.forEach(function (x) {
				var a = S.account(x.account_id) || { name: x.account, provider: x.provider, avatar: x.avatar };
				var mt = x.metrics || {};
				var stats = [];
				if (mt.views || mt.impressions || mt.reach) stats.push([S.icon('eye', 13), S.num(mt.views || mt.impressions || mt.reach)]);
				if (mt.likes) stats.push([S.icon('heart', 13), S.num(mt.likes)]);
				if (mt.comments) stats.push([S.icon('msg', 13), S.num(mt.comments)]);
				if (mt.shares) stats.push([S.icon('share', 13), S.num(mt.shares)]);
				var acts = [];
				if (x.remote_url) acts.push(h('a.flxsm-btn.is-ghost', { href: x.remote_url, target: '_blank', rel: 'noopener' }, S.icon('ext', 14), h('span', t('Aç'))));
				if (b.user.publish && x.status === 'failed') acts.push(S.btn('refresh', t('Tekrar dene'), function () { S.api('POST', 'targets/' + x.id + '/retry').then(function () { S.toast(t('Yeniden sıraya alındı.')); S.nudge(); refresh(); }, S.fail); }));
				if (b.user.publish && x.status === 'published' && x.caps && x.caps.edit) acts.push(S.btn('edit', t('Ağda düzenle'), function () { remoteEdit(x, p); }));
				if (b.user.publish && x.status === 'published' && x.caps && x.caps.delete) acts.push(S.btn('trash', t('Ağdan sil'), function () {
					S.confirm(t('{n} üzerindeki gönderi silinsin mi?', { n: a.name }), t('Ağdaki gönderi kalıcı olarak silinir.'), t('Sil'), true).then(function (ok) {
						if (ok) S.api('POST', 'targets/' + x.id + '/remote-delete').then(function () { S.toast(t('Ağdan silindi.')); refresh(); }, S.fail);
					});
				}, 'is-danger'));
				tbl.appendChild(h('.flxsm-trow',
					S.avatar(a, 34),
					h('.flxsm-trow-b',
						h('.flxsm-row', h('strong', a.name), S.badge(x.status), x.published_at ? h('small.flxsm-muted', S.fmt(x.published_at)) : null),
						x.content !== null && x.content !== undefined ? h('.flxsm-trow-text', x.content) : null,
						x.error ? h('p.flxsm-err', x.error) : null,
						stats.length ? h('.flxsm-trow-stats', stats.map(function (s) { return h('span', s); })) : null
					),
					h('.flxsm-trow-acts', acts)
				));
			});
			body.appendChild(h('h4.flxsm-sub', t('Hesaplar')));
			body.appendChild(tbl);
			if (['published', 'partial', 'publishing'].indexOf(p.status) >= 0) body.appendChild(h('p.flxsm-muted.is-small', t('Etkileşim sayıları günde iki kez güncellenir (son 30 gün).')));
		}
		function remoteEdit(x, p) {
			var ta = h('textarea.flxsm-input', { rows: 8, value: x.content !== null && x.content !== undefined ? x.content : p.content });
			var mm = S.modal(t('Ağdaki gönderiyi düzenle'), [h('p.flxsm-muted', t('Değişiklik doğrudan ağdaki gönderiye yazılır.')), ta], {
				foot: [
					h('button.flxsm-btn', { type: 'button', onclick: function () { mm.close(); } }, t('Vazgeç')),
					h('button.flxsm-btn.is-primary', { type: 'button', onclick: function (e) {
						var btn = e.currentTarget;
						btn.classList.add('is-busy');
						S.api('POST', 'targets/' + x.id + '/remote-edit', { content: ta.value }).then(function () { mm.close(); S.toast(t('Ağda güncellendi.')); refresh(); }, function (er) { btn.classList.remove('is-busy'); S.fail(er); });
					} }, t('Kaydet'))
				]
			});
		}
		load();
	};
})();
