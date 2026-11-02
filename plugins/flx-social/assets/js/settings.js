/* Flux Social — ayarlar (yönetici): genel, uygulama anahtarları, ağ ayarları, otomatik paylaşım, kayıt. */
(function () {
	'use strict';
	var S = window.FLXSM;
	var h = S.h, t = S.t;

	var TABS = [
		['general', t('Genel'), 'settings'],
		['apps', t('Uygulamalar'), 'key'],
		['networks', t('Ağ ayarları'), 'globe'],
		['auto', t('Otomatik paylaşım'), 'repeat'],
		['log', t('Kayıt'), 'log']
	];

	function field(label, input, hint) {
		return h('.flxsm-sfield', h('label', label), input, hint ? h('small.flxsm-muted', hint) : null);
	}
	function toggle(label, on, onChange, hint) {
		return h('.flxsm-sfield.is-check', h('label.flxsm-chk', h('input', { type: 'checkbox', checked: !!on, onchange: function (e) { onChange(e.target.checked); } }), h('span', label)), hint ? h('small.flxsm-muted', hint) : null);
	}
	function save(patch, msg) {
		return S.api('POST', 'settings', patch).then(function (d) {
			S.st.boot = Object.assign(S.st.boot, d);
			S.accounts = d.accounts;
			S.toast(msg || t('Kaydedildi.'));
			return d;
		}, S.fail);
	}

	S.settingsView = function (el, q) {
		var cur = q.tab || S.pref('setTab') || 'general';
		S.setTop(t('Ayarlar'));
		var nav = h('nav.flxsm-tabs.flxsm-stabs');
		var body = h('.flxsm-sbody');
		TABS.forEach(function (tb) {
			nav.appendChild(h('button', { type: 'button', class: 'flxsm-tab' + (cur === tb[0] ? ' is-on' : ''), onclick: function (e) {
				cur = tb[0]; S.pref('setTab', cur);
				Array.prototype.forEach.call(nav.children, function (c) { c.classList.toggle('is-on', c === e.currentTarget); });
				draw();
			} }, S.icon(tb[2], 15), h('span', tb[1])));
		});
		el.appendChild(nav);
		el.appendChild(body);
		function draw() {
			S.clear(body);
			var a = S.st.boot.admin;
			if (cur === 'general') general(body, a);
			else if (cur === 'apps') apps(body, q.app);
			else if (cur === 'networks') networks(body, a);
			else if (cur === 'auto') auto(body, a);
			else if (cur === 'log') logView(body);
		}
		draw();
	};

	function general(box, a) {
		var beat = a.last_tick || {};
		var ago = function (k) { return beat[k] ? S.ago(beat[k]) : t('hiç'); };
		var sig = h('textarea.flxsm-input', { rows: 2, value: a.signature || '' });
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', t('Onay ve imza'))),
			toggle(t('Yayın yetkisi olmayanların gönderisi onaya düşsün'), a.approval, function (v) { save({ approval: v }); }, t('Yetkiler Flux Roles’den: Sosyal medya: gönderi hazırla / yayınla / yönet.')),
			toggle(t('Site bağlantılarına UTM ekle (utm_source=ağ)'), a.utm, function (v) { save({ utm: v }); }, t('Hangi ağdan ne kadar ziyaret geldiğini Analytics’te görürsünüz.')),
			field(t('Hazır imza (oluşturucuda tek tıkla eklenir)'), sig),
			h('.flxsm-row', S.btn('check', t('İmzayı kaydet'), function () { save({ signature: sig.value }); }))
		));
		var url = h('input.flxsm-input', { type: 'text', readonly: true, value: a.tick_url });
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', t('Zamanlanmış gönderiler'))),
			h('p', t('Hostinger’da WP-Cron güvenilir çalışmadığı için zamanlanmış gönderiler dışarıdan dakikada bir tetiklenmeli. cron-job.org’da (ücretsiz) bu adresi her dakika çağıran bir görev açın. Panel açıkken ve gönderi kaydedince ayrıca kendiliğinden tetiklenir.')),
			field(t('Tetik adresi (gizli tutun)'), h('.flxsm-row.flxsm-copy', url, S.btn('copy', t('Kopyala'), function () { S.copy(a.tick_url); }), S.btn('refresh', t('Yeni anahtar'), function () {
				S.confirm(t('Anahtar yenilensin mi?'), t('Eski adres çalışmaz; cron-job.org’daki adresi de değiştirmeniz gerekir.'), t('Yenile')).then(function (ok) { if (ok) save({ regen_key: 1 }, t('Yeni anahtar oluşturuldu.')).then(function () { S.rerender(); }); });
			}))),
			h('ul.flxsm-beats',
				h('li', h('span', t('Dış tetik')), h('strong.' + (beat.external && beat.external > Date.now() / 1000 - 600 ? 'flxsm-up' : 'flxsm-down'), ago('external'))),
				h('li', h('span', 'WP-Cron'), h('strong', ago('cron'))),
				h('li', h('span', t('Arka plan isteği')), h('strong', ago('loopback'))),
				h('li', h('span', t('Panel')), h('strong', ago('panel')))
			)
		));
	}

	function apps(box, focus) {
		var list = S.st.boot.apps || [];
		box.appendChild(h('p.flxsm-muted.flxsm-lead', t('Her ağ için kendi geliştirici uygulamanızı açıp anahtarlarını buraya girin. Gizli anahtarlar şifreli saklanır ve bir daha gösterilmez; değiştirmek için yenisini yazın.')));
		list.forEach(function (app) {
			var vals = {};
			var fields = h('.flxsm-copts-g');
			app.fields.forEach(function (f) {
				var inp = h('input.flxsm-input', { type: f.secret ? 'password' : 'text', autocomplete: 'off', value: f.secret ? '' : f.value, placeholder: f.secret ? (f.set ? t('Kayıtlı (değiştirmek için yazın)') : t('Girilmedi')) : (f.placeholder || '') });
				inp.addEventListener('input', function () { vals[f.key] = inp.value; });
				fields.appendChild(h('.flxsm-copt', h('label', f.label + (f.optional && f.label.indexOf('(') < 0 ? ' (' + t('isteğe bağlı') + ')' : '')), inp,
					f.secret && f.set ? h('button.flxsm-link', { type: 'button', onclick: function () {
						S.confirm(t('Anahtar silinsin mi?'), t('Bu ağdaki hesaplar yeniden bağlanana kadar paylaşım yapamaz.'), t('Sil'), true).then(function (ok) {
							if (!ok) return;
							var p = {};
							p[f.key] = '__clear__';
							S.api('POST', 'settings/app/' + app.id, p).then(function (r) { S.st.boot.apps = r; S.toast(t('Silindi.')); S.rerender(); }, S.fail);
						});
					} }, t('Kayıtlı anahtarı sil')) : null));
			});
			var sec = h('details.flxsm-app' + (app.ready ? '.is-ready' : ''), { open: focus === app.id || (!app.ready && !focus && app.fields.length > 0 && list.filter(function (x) { return x.ready; }).length === 0 && app.id === 'meta'), id: 'app-' + app.id },
				h('summary', h('span.flxsm-app-nets', app.connect.map(function (c) { return S.net(c.provider, 22); })), h('strong', app.label), h('span.flxsm-grow'), app.ready ? h('span.flxsm-badge.is-published', t('Hazır')) : h('span.flxsm-badge.is-draft', app.fields.length ? t('Anahtar yok') : t('Bağlantı yok')), S.icon('chevD', 16)),
				h('.flxsm-app-b',
					h('p.flxsm-app-help', app.help),
					app.console ? h('p', h('a', { href: app.console, target: '_blank', rel: 'noopener' }, S.icon('ext', 13), ' ', t('Geliştirici konsolunu aç'))) : null,
					app.redirect ? field(t('Yönlendirme (redirect/callback) adresi'), h('.flxsm-row.flxsm-copy', h('input.flxsm-input', { type: 'text', readonly: true, value: app.redirect }), S.btn('copy', t('Kopyala'), function () { S.copy(app.redirect); }))) : null,
					app.fields.length ? fields : null,
					h('.flxsm-row',
						app.fields.length ? S.btn('check', t('Kaydet'), function (e) {
							var btn = e.currentTarget;
							btn.classList.add('is-busy');
							S.api('POST', 'settings/app/' + app.id, vals).then(function (r) { btn.classList.remove('is-busy'); S.st.boot.apps = r; S.toast(t('Kaydedildi.')); S.rerender(); }, function (er) { btn.classList.remove('is-busy'); S.fail(er); });
						}, 'is-primary') : null,
						app.ready ? S.btn('plus', t('Hesap bağla'), function () { S.go('accounts'); setTimeout(S.connectModal, 200); }) : null
					)
				)
			);
			box.appendChild(sec);
			if (focus === app.id) setTimeout(function () { sec.scrollIntoView({ block: 'start' }); }, 50);
		});
	}

	function networks(box, a) {
		var tiers = a.x_tiers || {};
		var tierSel = h('select.flxsm-input', { onchange: function (e) { save({ x_tier: e.target.value }); } }, Object.keys(tiers).map(function (k) { return h('option', { value: k, selected: a.x_tier === k }, tiers[k]); }));
		var lim = h('input.flxsm-input', { type: 'number', min: 0, value: a.x_month_limit || 0, style: { maxWidth: '140px' } });
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('x', 18), ' X (Twitter)')),
			field(t('API katmanı'), tierSel, t('Ücretsiz katmanda ayda az sayıda gönderi yazılabilir ve metrikler okunmaz. Ücretli katmanlarda (Basic/Pro ya da kullandıkça öde) metrikler de gelir.')),
			toggle(t('X Premium hesap (uzun gönderi, 25.000 karakter)'), a.x_premium, function (v) { save({ x_premium: v }); }),
			field(t('Aylık gönderi sınırı (0 = sınırsız)'), h('.flxsm-row', lim, S.btn('check', t('Kaydet'), function () { save({ x_month_limit: +lim.value || 0 }); })), t('Bu ay gönderilen: {n}. Sınıra gelince yeni X gönderisi reddedilir (katmanın kotasını aşmamak için).', { n: a.x_month || 0 }))
		));
		var tk = h('.flxsm-seg');
		[['inbox', t('Taslak (TikTok uygulamasına düşer)')], ['direct', t('Doğrudan yayın')]].forEach(function (o) {
			tk.appendChild(h('button', { type: 'button', class: 'flxsm-seg-b' + (a.tiktok_mode === o[0] ? ' is-on' : ''), onclick: function (e) {
				Array.prototype.forEach.call(tk.children, function (c) { c.classList.toggle('is-on', c === e.currentTarget); });
				save({ tiktok_mode: o[0] });
			} }, o[1]));
		});
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('tiktok', 18), ' TikTok')),
			field(t('Paylaşım kipi'), tk, t('Doğrudan yayın için TikTok uygulamanızın denetimden (audit) geçmesi gerekir; geçmeden yayınlanan videolar yalnız size görünür. Taslak kipi denetim gerektirmez.'))
		));
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('pinterest', 18), ' Pinterest')),
			toggle(t('Sandbox (deneme erişimi)'), a.pin_sandbox, function (v) { save({ pin_sandbox: v }); }, t('Pinterest uygulamanız henüz Standard erişim almadıysa açın; pinler yalnız sandbox’ta oluşur.'))
		));
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('linkedin', 18), ' LinkedIn')),
			toggle(t('Yönettiğim şirket sayfalarını da getir'), a.li_org, function (v) { save({ li_org: v }); }, t('Community Management API onayı gerekir. Onay yoksa kapatın; yalnız kişisel profil bağlanır.'))
		));
		var mv = h('input.flxsm-input', { type: 'text', value: a.meta_version, style: { maxWidth: '120px' } });
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('facebook', 18), ' ', S.net('instagram', 18), ' Meta')),
			field(t('Graph API sürümü'), h('.flxsm-row', mv, S.btn('check', t('Kaydet'), function () { save({ meta_version: mv.value.trim() }); })), t('Meta eski sürümleri iki yılda kapatır; gerekirse buradan yükseltin (ör. v25.0).'))
		));
		var choices = a.wa_choices || [];
		var waSel = h('select.flxsm-input', { onchange: function (e) { save({ wa_conn: +e.target.value }); } }, h('option', { value: 0 }, t('Varsayılan bağlantı')), choices.map(function (c) { return h('option', { value: c.id, selected: +a.wa_conn === +c.id }, c.name + (c.default ? ' (' + t('varsayılan') + ')' : '')); }));
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', S.net('whatsapp', 18), ' WhatsApp')),
			choices.length ? field(t('Evolution bağlantısı (Flux Mail)'), waSel) : h('p.flxsm-muted', t('Flux Mail’de WhatsApp (Evolution) bağlantısı bulunamadı; Flux Chat ayarları kullanılır.'))
		));
	}

	function auto(box, a) {
		var c = Object.assign({ enabled: 0, post_types: ['post'], accounts: [], template: '', mode: 'draft', delay: 10, image: 1 }, a.auto || {});
		var accs = h('.flxsm-apick');
		S.st.boot.accounts.forEach(function (acc) {
			var on = c.accounts.indexOf(acc.id) >= 0;
			var bt = h('button.flxsm-apick-b' + (on ? '.is-on' : ''), { type: 'button', title: acc.network + ' › ' + acc.name, onclick: function () {
				var i = c.accounts.indexOf(acc.id);
				if (i >= 0) c.accounts.splice(i, 1); else c.accounts.push(acc.id);
				bt.classList.toggle('is-on', i < 0);
			} }, S.avatar(acc, 36), h('span.flxsm-apick-ck', S.icon('check', 11)));
			accs.appendChild(bt);
		});
		var types = h('.flxsm-row');
		(a.post_types || []).forEach(function (pt) {
			types.appendChild(h('label.flxsm-chk', h('input', { type: 'checkbox', checked: c.post_types.indexOf(pt.id) >= 0, onchange: function (e) {
				var i = c.post_types.indexOf(pt.id);
				if (e.target.checked && i < 0) c.post_types.push(pt.id);
				if (!e.target.checked && i >= 0) c.post_types.splice(i, 1);
			} }), h('span', pt.label)));
		});
		var tpl = h('textarea.flxsm-input', { rows: 5, value: c.template });
		var mode = h('select.flxsm-input', { style: { maxWidth: '320px' } }, h('option', { value: 'draft', selected: c.mode === 'draft' }, t('Taslak oluştur (gözden geçirip gönderirim)')), h('option', { value: 'schedule', selected: c.mode === 'schedule' }, t('Kendiliğinden zamanla')));
		var delay = h('input.flxsm-input', { type: 'number', min: 0, max: 1440, value: c.delay, style: { maxWidth: '120px' } });
		var en = h('input', { type: 'checkbox', checked: !!c.enabled });
		var img = h('input', { type: 'checkbox', checked: !!c.image });
		box.appendChild(h('section.flxsm-card',
			h('.flxsm-card-h', h('h3', t('Sitede yazı yayınlanınca paylaş'))),
			h('.flxsm-sfield.is-check', h('label.flxsm-chk', en, h('span', t('Açık')))),
			field(t('Hangi içerik türleri'), types),
			field(t('Hangi hesaplar'), accs.children.length ? accs : h('p.flxsm-muted', t('Önce hesap bağlayın.'))),
			field(t('Metin şablonu'), tpl, t('Kullanılabilir: {title} {excerpt} {link} {site} {author} {category} {tags}. Bağlantı ağın önizleme kartını oluşturur.')),
			h('.flxsm-sfield.is-check', h('label.flxsm-chk', img, h('span', t('Öne çıkan görseli ekle')))),
			field(t('Ne yapılsın'), mode),
			field(t('Kaç dakika sonra paylaşılsın (zamanla seçiliyse)'), delay),
			h('.flxsm-row', S.btn('check', t('Kaydet'), function () {
				save({ auto: { enabled: en.checked, post_types: c.post_types, accounts: c.accounts, template: tpl.value, mode: mode.value, delay: +delay.value || 0, image: img.checked } });
			}, 'is-primary'))
		));
		box.appendChild(h('p.flxsm-muted.is-small', t('Her yazı için bir kez çalışır. Ağ kuralına uymayan hesaplar (ör. görselsiz Instagram) taslakta kalır.')));
	}

	function logView(box) {
		var list = h('.flxsm-log', h('.flxsm-boot', h('.flxsm-spin')));
		box.appendChild(h('.flxsm-row', h('p.flxsm-muted', t('Son 200 kayıt (45 gün saklanır).')), h('span.flxsm-grow'), S.btn('refresh', t('Yenile'), load)));
		box.appendChild(list);
		function load() {
			S.api('GET', 'log').then(function (rows) {
				S.clear(list);
				if (!rows.length) { list.appendChild(h('p.flxsm-muted', t('Kayıt yok.'))); return; }
				rows.forEach(function (r) {
					var acc = r.account_id ? S.account(r.account_id) : null;
					list.appendChild(h('.flxsm-logrow.is-' + r.level, h('time', S.fmt(+r.ts)), h('span.flxsm-loglv', r.level), acc ? h('span.flxsm-logacc', S.net(acc.provider, 13), acc.name) : null, h('span.flxsm-logmsg', r.msg)));
				});
			}, function (e) { S.clear(list).appendChild(h('p.flxsm-err', e.message)); });
		}
		load();
	}
})();
