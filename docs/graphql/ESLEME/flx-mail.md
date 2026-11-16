# Flux Mail (flx-mail): REST → GraphQL eşlemesi

Paket sürümü: 1.22.0 · Önek: `flxi_` · REST: `flxi/v1` · Durum: envanter (kod yok)

Cap (bu dosyadaki her yönetici satırı): `flx_gql_any_cap( 'manage_options', 'flxi_manage' )`. Kaynak `flxi_can_manage()` ([helpers.php:476](plugins/flx-mail/includes/helpers.php)). Kök alanda `'public'` yok. Menü aynı kapıyı kullanır ([admin.php:22](plugins/flx-mail/includes/admin/admin.php)).

Gizli değer (SMTP/IMAP parolası, API anahtarı, token, app secret, webhook verify token, `FLXI_SMTP_PASS` / `FLXI_IMAP_PASS`) GraphQL sorgusuna ve mutation girdisine girmez. Sorguda yalnız `*Set: Boolean`. Yazma bu turda admin-post'ta kalır (aşağıda İSTİSNA).

## Özet

Sayım tablo satırıdır (başlık değil). Ayar ve bağlantı sayıları form alanı sayısıdır; o iki tabloda alanlar gruplanmıştır.

| Kategori | Satır | GraphQL | İstisna |
|---|---:|---:|---:|
| REST (yöntem + yol) | 42 | 39 | 3 |
| AJAX `wp_ajax_` (priv) | 0 | 0 | 0 |
| AJAX `wp_ajax_nopriv` | 0 | 0 | 0 |
| Form POST (`admin-post.php`) | 15 | 15 (gizli olmayan alanlar mutation'da) | 2 satır gizli alanı dışarıda bırakır |
| Admin sayfa kaydı | 9 | 9 | 0 |
| Admin alt görünüm / filtre / işlem | 56 | 54 | 2 (ek indirme, WooCommerce'in kendi e-posta ekranı) |
| MCP `flxseo_mcp_tools` | 9 | 9 | 0 (araç kalır, servisi çağırır) |
| Posta kutusu AI `flxai_tools` | 5 | ekran aynı servisi çağırır | araç Flux AI'da kalır, paketin AI bağlantısı yok |
| Ayar alanı (`flxi_settings`) | 70 | 67 | 3 gizli |
| Bağlantı formu alanı (4 tür) | 28 | 23 | 5 gizli |
| Cron kancası | 4 | durum + bütçeli çalıştırma | kanca kendisi |
| Herkese açık uç | 2 | çıkanların sayısı ayarda | URL'ler kalır |
| Shortcode / blok | 1 + 1 | — | gömülü marka, bu şemada yok |

`wp_ajax_` / `wp_ajax_nopriv` grep'i `plugins/flx-mail` altında sıfır. Açılma veya tıklama pikseli yok ([campaign-engagement.php:9](plugins/flx-mail/includes/campaign-engagement.php): e-postada okundu `null`). Nopriv loopback tick yok (`flxseo_audit_tick` karşılığı yok).

## REST uçları

İzin: hepsi `flxi_can_manage`, webhook hariç.

| Yöntem | Yol | Kaynak | → GraphQL | Girdi | Çıktı |
|---|---|---|---|---|---|
| POST | `/flxi/v1/preview` | [rest.php:17](plugins/flx-mail/includes/rest.php) | `flxMailPreview` | `FlxMailPreviewInput` (key veya serbest alanlar, dark) | `{ subject, html }` |
| POST | `/flxi/v1/preview-pdf` | [rest.php:18](plugins/flx-mail/includes/rest.php) | `flxMailPreviewPdf` | aynı + `dark` | `FlxMailPdf` (name, base64). Büyük gövde riski; ekran önce HTML kullanır |
| POST | `/flxi/v1/test-email` | [rest.php:19](plugins/flx-mail/includes/rest.php) | `flxMailTestSend` | `to`, `key`, `sender` | `{ ok, to, error, at, errors }` |
| POST | `/flxi/v1/queue/run` | [rest.php:20](plugins/flx-mail/includes/rest.php) | `flxMailQueueRun` | `campaign: ID` isteğe bağlı, sunucu bütçesi 15 | `FlxMailQueueRunPayload` (processed, sent, failed, left, pausedUntil). Kimlikli yönetici ucu; nopriv tick değil |
| POST | `/flxi/v1/recipients` | [rest.php:21](plugins/flx-mail/includes/rest.php) | `flxMailRecipientsPreview` | `channel`, `sources`, `manual`, `sender` | `{ count, sample, why, quotaNote }` |
| POST | `/flxi/v1/connection-test` | [rest.php:22](plugins/flx-mail/includes/rest.php) | `flxMailConnectionTest` | `id`, `to`, `scenario` (text, image, audio, document, template, check) | `{ ok, error }` |
| GET | `/flxi/v1/log` | [rest.php:23](plugins/flx-mail/includes/rest.php) | `flxMailLogs` | `status`, `channel`, `template`, `search`, `first`, `after` | `FlxMailLogConnection!` |
| GET | `/flxi/v1/log/{id}` | [rest.php:24](plugins/flx-mail/includes/rest.php) | `flxMailLog` | `id: ID!` | `FlxMailLog`. Gövde `log_body` kapalıysa null |
| POST | `/flxi/v1/dns` | [rest.php:25](plugins/flx-mail/includes/rest.php) | `flxMailDnsCheck` | — | `FlxMailDns` (spf, dmarc, mx, checkedAt) |
| GET | `/flxi/v1/queue` | [queue-status.php:11](plugins/flx-mail/includes/queue-status.php) | `flxMailQueue` | `campaign`, `first`, `after` | `FlxMailQueue` (campaigns, counts, rows, batches, events, worker). Satırda job id var, parola yok |
| POST | `/flxi/v1/queue/action` | [queue-status.php:12](plugins/flx-mail/includes/queue-status.php) | `flxMailCampaignStop` / `Pause` / `Resume`, `flxMailNetgsmCancel`, `flxMailNetgsmReport` | `action` + `campaign` veya batch UUID | `{ ok, errors }` |
| GET | `/flxi/v1/wa/{id}/state` | [whatsapp.php:392](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaState` | `id`, `fresh` | `FlxMailWaState` (state, me, name). `fresh` dış çağrıdır, varsayılan önbellek |
| POST | `/flxi/v1/wa/{id}/connect` | [whatsapp.php:395](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaConnect` | `id`, `number` | QR / eşleşme durumu. QR metni sır değil, oturum anahtarı dönmez |
| POST | `/flxi/v1/wa/{id}/restart` | [whatsapp.php:398](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaRestart` | `id` | `FlxMailWaState` |
| POST | `/flxi/v1/wa/{id}/logout` | [whatsapp.php:401](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaLogout` | `id` | `{ ok, errors }` |
| GET | `/flxi/v1/wa/{id}/instances` | [whatsapp.php:404](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaInstances` | `id` | `[FlxMailWaInstance!]!` |
| GET | `/flxi/v1/wa/{id}/groups` | [whatsapp.php:407](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaGroups` | `id`, `fresh` | `[FlxMailWaGroup!]!` |
| GET | `/flxi/v1/wa/{id}/server` | [whatsapp.php:410](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaServer` | `id` | `FlxMailWaServer`. apiKey, password, token silinir ([whatsapp.php:295](plugins/flx-mail/includes/whatsapp.php)) |
| POST | `/flxi/v1/wa/{id}/server` | [whatsapp.php:414](plugins/flx-mail/includes/whatsapp.php) | `flxMailWaServerUpdate` | sunucu alanları. `db.password` yazma girdisi de yok (köprüye gider, WP'de durmaz, [whatsapp.php:353](plugins/flx-mail/includes/whatsapp.php)) | `FlxMailWaServer` |
| GET | `/flxi/v1/wacloud/{id}/webhook` | [wacloud.php:485](plugins/flx-mail/includes/providers/wacloud.php) | İSTİSNA | | Meta `hub.verify_token` el sıkışması. `__return_true` ([wacloud.php:491](plugins/flx-mail/includes/providers/wacloud.php)) |
| POST | `/flxi/v1/wacloud/{id}/webhook` | [wacloud.php:485](plugins/flx-mail/includes/providers/wacloud.php) | İSTİSNA | | Meta imzalı POST (`X-Hub-Signature-256`). App secret boşsa 403 |
| GET | `/flxi/v1/wacloud/{id}/setup` | [wacloud.php:497](plugins/flx-mail/includes/providers/wacloud.php) | `flxMailWacloudSetup` | `id` | `FlxMailWacloudSetup`: webhook URL, `signed`, `hasWaba`, phone info, son olay özeti, açık 24s pencere sayısı. `verify` token ve app secret yok ([wacloud.php:465](plugins/flx-mail/includes/providers/wacloud.php) bugün token döndürür; GraphQL döndürmez) |
| GET | `/flxi/v1/wacloud/{id}/templates` | [wacloud.php:500](plugins/flx-mail/includes/providers/wacloud.php) | `flxMailWacloudTemplates` | `id` | `[FlxMailWacloudTemplate!]!` |
| GET | `/flxi/v1/mailbox/list` | [mailbox/rest.php:20](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailbox` | `folder`, `search`, `system`, `unread`, `first`, `after` | `FlxMailMailboxConnection!` |
| GET | `/flxi/v1/mailbox/item/{id}` | [mailbox/rest.php:21](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxMessage` | `id` | `FlxMailMailboxMessage` (html, thread, ek meta). Okundu işaretler |
| POST | `/flxi/v1/mailbox/item/{id}/mark` | [mailbox/rest.php:22](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxMark` | `id`, `field: READ\|STARRED`, `value` | `{ ok, status }` |
| GET | `/flxi/v1/mailbox/item/{id}/attachment` | [mailbox/rest.php:23](plugins/flx-mail/includes/mailbox/rest.php) | İSTİSNA | | Ham bayt, `Content-Disposition` ([mailbox/rest.php:224](plugins/flx-mail/includes/mailbox/rest.php)) |
| POST | `/flxi/v1/mailbox/sync` | [mailbox/rest.php:24](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxSync` | — (sunucu dilimi, bugün 20 ileti, `set_time_limit(60)`) | `FlxMailMailboxSyncPayload` |
| POST | `/flxi/v1/mailbox/test` | [mailbox/rest.php:25](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxTest` | — | `{ ok, message }` parola yok |
| GET | `/flxi/v1/mailbox/drafts` | [mailbox/rest.php:26](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDrafts` | `status: DRAFT\|REVIEW\|FAILED\|SENT` | `FlxMailDraftConnection!` |
| POST | `/flxi/v1/mailbox/draft` | [mailbox/rest.php:27](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftSave` | `FlxMailDraftInput` | `FlxMailDraft` |
| POST | `/flxi/v1/mailbox/new-draft` | [mailbox/rest.php:28](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftCreate` | subject, body, to, layout, note | `FlxMailDraft` |
| GET | `/flxi/v1/mailbox/draft/{id}` | [mailbox/rest.php:29](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraft` | `id` | `FlxMailDraft` |
| POST | `/flxi/v1/mailbox/draft/{id}/delete` | [mailbox/rest.php:30](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftDelete` | `id` | `{ ok, errors }` |
| POST | `/flxi/v1/mailbox/draft/{id}/send` | [mailbox/rest.php:31](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftSend` | `id` + kaydedilmemiş alanlar | `{ ok, mailId, errors }` |
| POST | `/flxi/v1/mailbox/reply` | [mailbox/rest.php:32](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxReply` | `mailId`, `mode: REPLY\|REPLY_ALL\|FORWARD` | `FlxMailDraft` (göndermez) |
| POST | `/flxi/v1/mailbox/item/{id}/reply` | [mailbox/rest.php:33](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailMailboxSendReply` | `id`, `body` | gönderir. FluxApp manifesti bu yolu kullanır ([app-manifest.php:120](plugins/flx-mail/includes/app-manifest.php)) |
| POST | `/flxi/v1/mailbox/preview` | [mailbox/rest.php:34](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftPreview` | taslak alanları veya `layoutData` | `{ html }` |
| POST | `/flxi/v1/mailbox/ai` | [mailbox/rest.php:35](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailDraftAi` | `mailId` / `draftId` / `instruction` | `FlxMailDraft` (onay bekler, göndermez). Flux AI `flxai_chat`; anahtar alanı yok. AI yoksa `INTERNAL` değil, kullanıcı dilinde hata |
| GET | `/flxi/v1/mailbox/layouts` | [mailbox/rest.php:36](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailLayouts` | — | `[FlxMailLayout!]!` |
| POST | `/flxi/v1/mailbox/layout` | [mailbox/rest.php:37](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailLayoutSave` | `FlxMailLayoutInput` | `FlxMailLayout` |
| POST | `/flxi/v1/mailbox/layout/{id}/delete` | [mailbox/rest.php:38](plugins/flx-mail/includes/mailbox/rest.php) | `flxMailLayoutDelete` | `id` | `{ ok, errors }` |

REST: 42 satır, 39 GraphQL, 3 istisna.

## AJAX

`wp_ajax_` ve `wp_ajax_nopriv` kaydı yok. Kuyruk, posta kutusu ve bağlantı ekranları `admin-ajax.php` değil, `flxi/v1` REST çağırır.

Gömülü `flx_job_kick` ([flx-brand/core.php:876](plugins/flx-mail/includes/flx-brand/core.php)) imzalı admin-ajax loopback'idir. `plugins/flx-mail` içinde `flxi_` kuyruğu veya posta kutusu bu fonksiyonu çağırmaz. Kuyruk planı `flx_background_schedule( 'flxi_run_queue' )` ([campaigns.php:274](plugins/flx-mail/includes/campaigns.php)).

## Form POST (`admin-post.php`)

Hepsi `flxi_check()`: cap + nonce ([admin.php:302](plugins/flx-mail/includes/admin/admin.php)).

| Action | Kaynak | → GraphQL | Not |
|---|---|---|---|
| `flxi_toggle_template` | [page-templates.php:242](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplateUpdate` | `enabled` |
| `flxi_save_template` | [page-templates.php:261](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplateUpdate` / `flxMailTemplateReset` | `reset=1` sıfırlar |
| `flxi_create_campaign` | [page-send.php:175](plugins/flx-mail/includes/admin/page-send.php) | `flxMailCampaignCreate` | Kuyruğa yazar, tek istekte bütün listeyi göndermez |
| `flxi_cancel_campaign` | [page-send.php:213](plugins/flx-mail/includes/admin/page-send.php) | `flxMailCampaignStop` | |
| `flxi_pause_campaign` | [campaign-control.php:297](plugins/flx-mail/includes/campaign-control.php) | `flxMailCampaignPause` | |
| `flxi_resume_campaign` | [campaign-control.php:307](plugins/flx-mail/includes/campaign-control.php) | `flxMailCampaignResume` | |
| `flxi_save_settings` | [page-settings.php:264](plugins/flx-mail/includes/admin/page-settings.php) | `flxMailSettingsUpdate` + İSTİSNA | Parola alanları (`smtp_pass`, `smtp_nr_pass`, `imap_pass`) GraphQL girdisinde yok. Boş POST eskisini korur. İstisna satırı durum dosyasında |
| `flxi_wc_brand` | [page-settings.php:327](plugins/flx-mail/includes/admin/page-settings.php) | `flxMailWcBrandApply` | WooCommerce e-posta görünümüne logo/renk basar |
| `flxi_save_connection` | [page-connections.php:178](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnectionSave` + İSTİSNA | `api_key`, `password`, `token`, `app_secret` GraphQL'e girmez |
| `flxi_delete_connection` | [page-connections.php:205](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnectionDelete` | `remote=1` sunucudaki numarayı da siler |
| `flxi_default_connection` | [page-connections.php:221](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnectionSetDefault` | |
| `flxi_blocklist_add` | [page-blocklist.php:330](plugins/flx-mail/includes/admin/page-blocklist.php) | `flxMailBlockAdd` / `flxMailOptoutAdd` | `kind=block` Flux Chat `flxc_block_add`; yoksa opt-out |
| `flxi_blocklist_remove` | [page-blocklist.php:355](plugins/flx-mail/includes/admin/page-blocklist.php) | `flxMailBlockRemove` / `flxMailOptoutRemove` | |
| `flxi_optout_add` | [optout.php:421](plugins/flx-mail/includes/optout.php) | `flxMailOptoutAdd` | |
| `flxi_optout_remove` | [optout.php:437](plugins/flx-mail/includes/optout.php) | `flxMailOptoutRemove` | |

15 satır. Gömülü sistem kontrolü `admin_post_flx_system_checks_dismiss` ([flx-system-checks.php:378](plugins/flx-mail/includes/flx-system-checks.php)) bu paketin işlemi değil; Flux Speed / ortak sistem ekranında kalır, sayıma girmedi.

## Admin sayfaları

Sekme çubuğu ayrı veri değil; sayfa slug'larının kendisi ([admin.php:245](plugins/flx-mail/includes/admin/admin.php)). `flxi-sistem`, `flxp_system_checks_url` varsa menüden düşer ve eski adres Performans ekranına gider ([admin.php:34](plugins/flx-mail/includes/admin/admin.php), [admin.php:47](plugins/flx-mail/includes/admin/admin.php)).

| Sayfa (`page=`) | Kaynak | Ne gösterir | Sorgular / mutation | İstemci |
|---|---|---|---|---|
| `flxi-posta` | [admin.php:25](plugins/flx-mail/includes/admin/admin.php), [page-mailbox.php:130](plugins/flx-mail/includes/admin/page-mailbox.php) | Posta kutusu SPA | mailbox sorguları | `mail/mailbox` |
| `flxi` | [admin.php:27](plugins/flx-mail/includes/admin/admin.php) | E-posta listesi ve `?key=` düzenleyici | `flxMailTemplates`, `flxMailTemplate` | `mail/templates` |
| `flxi-gonder` | [admin.php:28](plugins/flx-mail/includes/admin/admin.php) | Tekil / toplu gönderim | `flxMailCampaignCreate`, önizleme, alıcı önizleme | `mail/send` |
| `flxi-kuyruk` | [admin.php:29](plugins/flx-mail/includes/admin/admin.php) | Canlı kuyruk | `flxMailQueue`, `flxMailQueueRun` | `mail/queue` |
| `flxi-kayitlar` | [admin.php:30](plugins/flx-mail/includes/admin/admin.php) | Giden kayıt | `flxMailLogs`, `flxMailLog` | `mail/log` |
| `flxi-kara-liste` | [admin.php:31](plugins/flx-mail/includes/admin/admin.php) | Tam engel + IPTAL | `flxMailBlocklist` | `mail/blocklist` |
| `flxi-baglantilar` | [admin.php:32](plugins/flx-mail/includes/admin/admin.php) | WhatsApp / SMS | `flxMailConnections` | `mail/connections` |
| `flxi-ayarlar` | [admin.php:33](plugins/flx-mail/includes/admin/admin.php) | Ayarlar, 10 bölüm | `flxMailSettings` | `mail/settings` |
| `flxi-sistem` | [admin.php:35](plugins/flx-mail/includes/admin/admin.php), [admin.php:332](plugins/flx-mail/includes/admin/admin.php) | Yalnız `flxi.*` sistem satırları | `flxMailSystemChecks` | `mail/system` (Speed varken gizli) |

9 sayfa kaydı, hepsi GraphQL ekranı. Klasik görünüm bağlantısı (kullanıcı meta) uygulama turunda; bu dosya yalnız eşleme.

### Alt görünüm, filtre, işlem

Toplu seçim (checkbox + apply) hiçbir sayfada yok. Satır işlemi var.

| Sayfa | Parça | Kaynak | → GraphQL |
|---|---|---|---|
| Posta kutusu | Gelen | [page-mailbox.php:32](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailMailbox(folder: INBOX)` |
| Posta kutusu | Onay bekleyen | [page-mailbox.php:33](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailDrafts(status: REVIEW)` |
| Posta kutusu | Taslaklar | [page-mailbox.php:34](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailDrafts(status: DRAFT)` (+ FAILED aynı grupta, [mailbox/rest.php:64](plugins/flx-mail/includes/mailbox/rest.php)) |
| Posta kutusu | Gönderilen | [page-mailbox.php:35](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailMailbox(folder: SENT)` |
| Posta kutusu | Şablonlar (layout) | [page-mailbox.php:36](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailLayouts` |
| Posta kutusu | Arama | [page-mailbox.php:38](plugins/flx-mail/includes/admin/page-mailbox.php) | `search` |
| Posta kutusu | Yalnız okunmamış | [page-mailbox.php:40](plugins/flx-mail/includes/admin/page-mailbox.php) | `unread: true` |
| Posta kutusu | Sistem e-postaları | [page-mailbox.php:39](plugins/flx-mail/includes/admin/page-mailbox.php) | `system: true` |
| Posta kutusu | `?id=` `?taslak=` `?view=` | [page-mailbox.php:17](plugins/flx-mail/includes/admin/page-mailbox.php) | aynı sorgular, argümanla açılır |
| Posta kutusu | Eşitle | [page-mailbox.php:45](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailMailboxSync` |
| Posta kutusu | Yeni e-posta | [page-mailbox.php:37](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailDraftCreate` |
| Posta kutusu | Yanıtla / tümüne / ilet | [page-mailbox.php:52](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailMailboxReply` |
| Posta kutusu | Okunmadı / yıldız | [page-mailbox.php:63](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailMailboxMark` |
| Posta kutusu | Kaydet, gönder, sil, önizle | [page-mailbox.php:73](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailDraftSave`, `flxMailDraftSend`, `flxMailDraftDelete`, `flxMailDraftPreview` |
| Posta kutusu | AI yanıt / yaz / düzelt | [page-mailbox.php:55](plugins/flx-mail/includes/admin/page-mailbox.php) | `flxMailDraftAi` (Flux AI; anahtar yok) |
| Posta kutusu | Ek aç | [mailbox/rest.php:224](plugins/flx-mail/includes/mailbox/rest.php) | İSTİSNA (bayt) |
| E-postalar | Grup listesi + arama | [page-templates.php:44](plugins/flx-mail/includes/admin/page-templates.php), [:97](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplates(search:)` connection. Gruplar kayıtlı, WooCommerce, keşfedilen |
| E-postalar | 7 gün sayaçları (sent / failed / blocked) | [page-templates.php:22](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailLogStats` |
| E-postalar | Satırda aç/kapa | [page-templates.php:104](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplateUpdate`. `can_disable=false` kilitli |
| E-postalar | `?key=` düzenleyici | [page-templates.php:11](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplate` |
| E-postalar | Alanlar: enabled, sender, subject, heading, body, button, wrap | [page-templates.php:172](plugins/flx-mail/includes/admin/page-templates.php) | `FlxMailTemplateUpdateInput`. WooCommerce `wc/*` yalnız `enabled` |
| E-postalar | Varsayılana dön | [page-templates.php:224](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTemplateReset` |
| E-postalar | Bana test gönder | [page-templates.php:223](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailTestSend` |
| E-postalar | Önizleme masaüstü / telefon / koyu | [page-templates.php:230](plugins/flx-mail/includes/admin/page-templates.php) | `flxMailPreview` |
| E-postalar | WooCommerce'de düzenle | [page-templates.php:127](plugins/flx-mail/includes/admin/page-templates.php) | İSTİSNA: WooCommerce › Ayarlar › E-postalar. Bu paket yalnız aç/kapa yazar |
| Gönder | Kanal email / whatsapp / sms | [page-send.php:25](plugins/flx-mail/includes/admin/page-send.php) | `FlxMailChannel` |
| Gönder | Elle alıcı + kaynak kutuları (rol, `flx_people`, sertifika) | [page-send.php:33](plugins/flx-mail/includes/admin/page-send.php), [campaigns.php:12](plugins/flx-mail/includes/campaigns.php) | `flxMailRecipientsPreview` |
| Gönder | İleri tarih | [page-send.php:45](plugins/flx-mail/includes/admin/page-send.php) | `sendAt` |
| Gönder | E-posta alanları, değişkenler, listeden çık | [page-send.php:46](plugins/flx-mail/includes/admin/page-send.php) | `FlxMailCampaignCreateInput` |
| Gönder | WhatsApp/SMS metin, medya, tanıtım anahtarı, bağlantı seçimi | [page-send.php:75](plugins/flx-mail/includes/admin/page-send.php) | aynı input. Medya `uploadId` (ortak `flx/v1/upload`), URL serbest metin olarak kalmaz |
| Gönder | Önizleme (e-posta cihaz/koyu; WA/SMS sayaç) | [page-send.php:95](plugins/flx-mail/includes/admin/page-send.php) | `flxMailPreview` + `flxMailMessagePreview` (parola yok, [page-send.php:143](plugins/flx-mail/includes/admin/page-send.php)) |
| Gönder | Bana test | [page-send.php:92](plugins/flx-mail/includes/admin/page-send.php) | `flxMailTestSend` |
| Gönder | Son 20 kampanya | [page-send.php:108](plugins/flx-mail/includes/admin/page-send.php) | `flxMailCampaigns(first: 20)` |
| Gönder | Duraklat / sürdür | [campaign-control.php:317](plugins/flx-mail/includes/campaign-control.php) | `flxMailCampaignPause` / `Resume` |
| Kuyruk | Kampanya seçici, `?campaign=` | [page-queue.php:10](plugins/flx-mail/includes/admin/page-queue.php) | `flxMailQueue(campaign:)` |
| Kuyruk | Duraklat, sürdür, kuyruğu durdur | [page-queue.php:11](plugins/flx-mail/includes/admin/page-queue.php) | pause / resume / stop |
| Kuyruk | Alıcı sayfası (50) | [queue-status.php:25](plugins/flx-mail/includes/queue-status.php) | `rows` connection |
| Kuyruk | Netgsm toplu: raporu çek, ileri tarihi iptal | [queue-status.php:55](plugins/flx-mail/includes/queue-status.php) | `flxMailNetgsmReport`, `flxMailNetgsmCancel` |
| Kuyruk | İşletim günlüğü | [queue-status.php:36](plugins/flx-mail/includes/queue-status.php) | `events` (içerik ve kimlik bilgisi yok) |
| Kayıtlar | `status` `channel` `s` `template` `campaign` `paged` | [page-log.php:22](plugins/flx-mail/includes/admin/page-log.php) | `flxMailLogs` argümanları |
| Kayıtlar | Satır içeriği | [page-log.php:96](plugins/flx-mail/includes/admin/page-log.php) | `flxMailLog` |
| Kara liste | `type` block\|optout, `channel`, `s`, `paged` | [page-blocklist.php:182](plugins/flx-mail/includes/admin/page-blocklist.php) | `flxMailBlocklist` |
| Kara liste | Ekle (tam engel / WA IPTAL / SMS IPTAL) | [page-blocklist.php:220](plugins/flx-mail/includes/admin/page-blocklist.php) | `flxMailBlockAdd`, `flxMailOptoutAdd` |
| Kara liste | Satırdan çıkar | [page-blocklist.php:355](plugins/flx-mail/includes/admin/page-blocklist.php) | remove mutation |
| Kara liste | Kişi kartı | [page-blocklist.php:116](plugins/flx-mail/includes/admin/page-blocklist.php) | `contact: FlxContact` nullable |
| Kara liste | Sohbet linki | [page-blocklist.php:139](plugins/flx-mail/includes/admin/page-blocklist.php) | `chatId` nullable. Sohbet ekranı flx-chat; bu paket sohbet açmaz |
| Bağlantılar | WhatsApp listesi / SMS listesi | [page-connections.php:84](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnections(channel:)` |
| Bağlantılar | Yeni/düzenle: evolution, flxwpapi, wacloud, netgsm | [page-connections.php:16](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnection` + `flxMailConnectionSave` |
| Bağlantılar | Cloud kurulum | [page-connections.php:129](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailWacloudSetup`, `flxMailWacloudTemplates` |
| Bağlantılar | Evolution bağlan / QR, yeniden başlat, çıkış, sunucu | [page-connections.php:133](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailWa*` |
| Bağlantılar | Test senaryoları | [test-send.php:22](plugins/flx-mail/includes/test-send.php) | `flxMailConnectionTest` |
| Bağlantılar | Varsayılan yap, sil, sunucudan da sil | [page-connections.php:146](plugins/flx-mail/includes/admin/page-connections.php) | `flxMailConnectionSetDefault`, `flxMailConnectionDelete(remote:)` |
| Ayarlar | Bölümler: gönderen, smtp, posta, test, görünüm, google, woocommerce, kayıt, iptal, sınırlar | [page-settings.php:22](plugins/flx-mail/includes/admin/page-settings.php) | `flxMailSettings` tek tip, bölüm istemcide |
| Ayarlar | SPF / DMARC / MX | [page-settings.php:118](plugins/flx-mail/includes/admin/page-settings.php) | `flxMailDns`, `flxMailDnsCheck` |
| Ayarlar | WooCommerce'e görünümü bas | [page-settings.php:190](plugins/flx-mail/includes/admin/page-settings.php) | `flxMailWcBrandApply` |
| Sistem | `flxi.*` satırları + yeniden kontrol | [system-checks.php:12](plugins/flx-mail/includes/system-checks.php), [admin.php:332](plugins/flx-mail/includes/admin/admin.php) | `flxMailSystemChecks`. Düzeltmeler (`flx_system_fixes`) Flux AI MCP'de kalır; ekranda aynı altı düzeltme `flxMailSettingsUpdate` / `flxMailTestSend` / `flxMailDnsCheck` |

56 alt satır: 54 GraphQL, 2 istisna (ek baytı, WooCommerce'in kendi düzenleme ekranı).

## MCP / AI araçları

Paket `flx_mcp_tools` filtresine yazmaz. Yönetici araçları `flxseo_mcp_tools` ([mcp.php:17](plugins/flx-mail/includes/mcp.php)). Posta kutusu araçları `flxai_tools` ([mailbox/ai.php:172](plugins/flx-mail/includes/mailbox/ai.php)). İkisi de Flux AI üzerinden kalır. Pakete AI anahtarı eklenmez.

| Araç | Kaynak | Cap | GraphQL karşılığı |
|---|---|---|---|
| `flxi_email_log` | [mcp.php:47](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailLogs` |
| `flxi_templates` | [mcp.php:98](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailTemplates` |
| `flxi_update_template` | [mcp.php:127](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailTemplateUpdate` / `Reset` |
| `flxi_settings` | [mcp.php:175](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailSettings` / `Update`. MCP de parolayı okumaz/yazmaz (`flxi_mcp_public_settings`, [mcp.php:19](plugins/flx-mail/includes/mcp.php)) |
| `flxi_send_test` | [mcp.php:206](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailTestSend` |
| `flxi_mail_quota` | [mcp.php:229](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailQuota` |
| `flxi_campaigns` | [mcp.php:246](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailCampaigns` |
| `flxi_optouts` | [mcp.php:259](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailBlocklist(type: OPTOUT)` |
| `flxi_wa_guard` | [mcp.php:280](plugins/flx-mail/includes/mcp.php) | `manage_options` | `flxMailWaGuard` |
| `flxi_mail_list` | [mailbox/ai.php:180](plugins/flx-mail/includes/mailbox/ai.php) | `flxi_manage` | `flxMailMailbox`. Araç `flxai_tools`'ta kalır |
| `flxi_mail_read` | [mailbox/ai.php:213](plugins/flx-mail/includes/mailbox/ai.php) | `flxi_manage` | `flxMailMailboxMessage` |
| `flxi_mail_layouts` | [mailbox/ai.php:244](plugins/flx-mail/includes/mailbox/ai.php) | `flxi_manage` | `flxMailLayouts` |
| `flxi_mail_draft` | [mailbox/ai.php:266](plugins/flx-mail/includes/mailbox/ai.php) | `flxi_manage` | `flxMailDraftSave` (status REVIEW, origin AI) |
| `flxi_mail_send_draft` | [mailbox/ai.php:322](plugins/flx-mail/includes/mailbox/ai.php) | `flxi_manage`, `confirm` | `flxMailDraftSend` |

`flxai_systems` / `flxai_sources` / `flxai_purpose_labels` ([mailbox/ai.php:346](plugins/flx-mail/includes/mailbox/ai.php)) etiket kaydıdır, uç değildir.

Sistem düzeltmeleri `flx_system_fixes` ([system-checks.php:223](plugins/flx-mail/includes/system-checks.php)): `flxi.from_email`, `flxi.from_name`, `flxi.noreply_email`, `flxi.reply_to`, `flxi.smtp_server` (parolasız), `flxi.mailer`, `flxi.social_block`, `flxi.send_test`, `flxi.dns_check`. Parola düzeltmesi yok. GraphQL'de ayrı araç açılmaz.

## Ayarlar

Otomatik şema yalnız iki alan: `tagline`, `footer_art` ([helpers.php:562](plugins/flx-mail/includes/helpers.php)). Kit bunları `flxiSettings` diye üretmez; seçenek adı `flxi_settings` → `FlxSettingsFlxiSettings`. Ekran bunu kullanmaz. Asıl kayıt `flxi_defaults()` ([helpers.php:27](plugins/flx-mail/includes/helpers.php)), 70 anahtar. GraphQL tipi elle `FlxMailSettings` olur; preset'in iki alanı durur.

Gizli (GraphQL yok, `*Set: Boolean`): `smtp_pass`, `smtp_nr_pass`, `imap_pass`. `FLXI_SMTP_PASS` / `FLXI_IMAP_PASS` tanımlıysa panel de yazamaz ([page-settings.php:69](plugins/flx-mail/includes/admin/page-settings.php), [:83](plugins/flx-mail/includes/admin/page-settings.php)).

`smtp_auth` bir boolean'dır (kimlik doğrulama açık mı). Kit `auth` kelimesini gizli sayar; GraphQL alan adı `smtpAuthentication` olur, anahtar yeniden adlandırılmaz, değer gizlenmez.

| Grup | Alan | GraphQL | Not |
|---|---|---|---|
| Gönderen | `from_name`, `from_email`, `noreply_name`, `noreply_email`, `reply_to`, `force_from`, `set_sender` | `flxMailSettings` | 7 alan |
| SMTP | `mailer` (PHP\|SMTP), `smtp_host`, `smtp_port`, `smtp_secure` (SSL\|TLS\|NONE), `smtp_user`, `smtp_nr_user`, `smtpAuthentication` | aynı | 7 alan. `smtp_pass`, `smtp_nr_pass` İSTİSNA |
| Posta kutusu | `mailbox_enabled`, `imap_host`, `imap_port`, `imap_secure`, `imap_user`, `mailbox_days`, `mailbox_keep_days`, `mailbox_signature` | aynı | 8 alan. `imap_pass` İSTİSNA |
| Şablon / görünüm | `wrap_plain`, `wrap_html`, `email_images_fix`, `embed_images`, `footer_art`, `logo`, `logo_white`, `logo_width`, dört renk, `org_name`, `tagline`, `address`, `phone`, `contact_email`, `website`, `social` (instagram, facebook, x, youtube, linkedin, whatsapp), `footer_note` | aynı | Logo ortak upload `uploadId`. `social` URL listesi |
| Google | `social_block_core`, `social_block_wc`, `social_verified` | aynı | 3 alan |
| Kayıt | `log_enabled`, `log_body`, `log_days`, `bulk_per_run`, `bulk_unsubscribe` | aynı | 5 alan. Çıkan e-posta sayısı `unsubscribedCount` (liste GraphQL'de connection değil; ham adres listesi `flxi_unsubscribed` seçeneği ekranda yalnız sayılır, [page-settings.php:208](plugins/flx-mail/includes/admin/page-settings.php)) |
| IPTAL | `optout_enabled`, `optout_footer_wa`, `optout_footer_sms`, `optout_text`, `optout_keywords`, `optin_keywords`, `optout_reply`, `optin_reply` | aynı | 8 alan |
| Sınır | `limit_daily` (100), `limit_recipients` (100), `limit_reserve`, `limit_size_mb`, `limit_attach_mb`, `limit_storage_gb`, `limit_mailbox_emails`, `limit_aliases`, `limit_forwarders` | `flxMailQuota` ile birlikte okunur | 9 alan. Günlük kullanım `flxMailQuota` |

70 ayar anahtarı: 67 GraphQL, 3 gizli.

### Bağlantı alanları

Sorgu config'ten gizli anahtarı çıkarır. Kayıtlı mı: `secretSet` / `appSecretSet`.

| Tür | Kaynak | GraphQL'de | Gizli |
|---|---|---|---|
| evolution | [evolution.php:17](plugins/flx-mail/includes/providers/evolution.php) | `baseUrl`, `instance`, `version`, `linkPreview`, `delay`, `typing` | `api_key` |
| flxwpapi | [evolution.php:31](plugins/flx-mail/includes/providers/evolution.php) | evolution ile aynı, `version` yok | `api_key` |
| netgsm | [netgsm.php:17](plugins/flx-mail/includes/providers/netgsm.php) | `username`, `header`, `iysfilter`, `encoding`, `partnercode`, `delay`, `batchSize` | `password` |
| wacloud | [wacloud.php:22](plugins/flx-mail/includes/providers/wacloud.php) | `phoneId`, `wabaId`, `graph`, `linkPreview`, `delay` | `token`, `app_secret`. Verify token (`flxi_wac_verify_{id}`) de yok |

28 form alanı: 23 GraphQL, 5 gizli (`api_key` evolution ve flxwpapi'de ayrı ayrı). Verify token bağlantı formu alanı değildir; kurulum cevabında da yoktur.

## Cron / tetik

Nopriv tick yok. Dördü de WP-Cron. Hostinger cron'u çalıştırmadığı için ekran, kimlikli mutation ile bir dilim ilerletir.

| Kanca | Kaynak | Ne yapar | GraphQL |
|---|---|---|---|
| `flxi_run_queue` | [campaigns.php:278](plugins/flx-mail/includes/campaigns.php) | Bütçe 25 sn, en az 10 sn kalmadıysa satır almaz | `flxMailQueue` + `flxMailQueueRun` (REST bugün 15 iş). Kanca kalır |
| `flxi_netgsm_poll` | [netgsm-jobs.php:5](plugins/flx-mail/includes/netgsm-jobs.php) | Teslim raporu. Göndermez. Aktif batch varsa tek olay | `flxMailQueue` batch alanları + `flxMailNetgsmReport` |
| `flxi_mailbox_sync` | [mailbox/sync.php:428](plugins/flx-mail/includes/mailbox/sync.php) | 5 dakikada bir, posta kutusu açıksa | `flxMailMailboxStatus` + `flxMailMailboxSync` (dilim) |
| `flxi_daily` | [install.php:32](plugins/flx-mail/includes/install.php), [install.php:187](plugins/flx-mail/includes/install.php), [netgsm-jobs.php:250](plugins/flx-mail/includes/netgsm-jobs.php), [mailbox/store.php:335](plugins/flx-mail/includes/mailbox/store.php) | Log, kuyruk, batch ve posta kutusu kopyası temizliği | Mutation yok. Saklama süresi ayardan okunur |

`flx_background_wake` dosya dokunuşudur, HTTP değildir ve yalnız Docker worker kirası varken çalışır ([flx-background.php:164](plugins/flx-mail/includes/flx-background.php)).

## Herkese açık uçlar

| Uç | Kaynak | → GraphQL |
|---|---|---|
| `GET`/`POST /?flxi_unsub=&k=` ve eski `yti_unsub` | [campaigns.php:550](plugins/flx-mail/includes/campaigns.php) | İSTİSNA. HMAC, onay formu, RFC 8058 gövdesiz POST. GraphQL'de yalnız `unsubscribedCount` |
| Cloud webhook | [wacloud.php:485](plugins/flx-mail/includes/providers/wacloud.php) | İSTİSNA (REST tablosunda) |

Shortcode `[flx_credit]` ve marka bloğu gömülü `flx-brand` ([flx-brand/core.php:133](plugins/flx-mail/includes/flx-brand/core.php)). Posta şemasına girmez.

FluxApp manifesti ([app-manifest.php:17](plugins/flx-mail/includes/app-manifest.php)) gelen kutusu, ileti, yanıt ve kayıt listesini bugünkü REST'e bağlar. Manifest protokolü flx-app'te kalır; veri yukarıdaki sorgulardadır.

## Tipler (taslak)

| Tip | Alanlar | Nullable | Loader |
|---|---|---|---|
| `FlxMailTemplate` | id, label, group, kind, enabled, editable, canDisable, sender, subject, heading, body, buttonText, buttonUrl, vars | `body` düzenlenemeyen keşifte | — |
| `FlxMailLog` | id, createdAt, channel, template, recipient, subject, status, error, sender, campaignId, body | `body` (log_body kapalı), `error`, `contact` | `FlxContact` |
| `FlxMailCampaign` | id, name, channel, status, total, sent, failed, skipped, sendAt, createdAt | `sendAt`, `contact` satırda | — |
| `FlxMailQueueRow` | id, recipient, channel, status, attempts, error, jobId, scheduledAt, sentAt | `error`, `jobId`, `sentAt`, `contact` | `FlxContact` |
| `FlxMailConnection` | id, name, type, channel, active, isDefault, secretSet, appSecretSet + gizli olmayan config | `contact` yok | — |
| `FlxMailMailboxMessage` | id, folder, from, to, subject, snippet, html, attachments (name, size, mime), thread | `html`, `contact` | `FlxContact` |
| `FlxMailDraft` | id, to, cc, subject, body, layout, status, origin | `replyTo` | — |
| `FlxMailSettings` | yukarıdaki 67 alan + üç `*Set` boolean + `unsubscribedCount` | `replyTo`, boş renkler (tema) | — |
| `FlxMailWacloudSetup` | webhookUrl, signed, hasWaba, phone, lastEventAt, openChats | `phone`, `lastEventAt` | — |
| `FlxMailDns` | spf, dmarc, mx, checkedAt | kayıt yoksa null | — |
| `FlxMailQuota` | limits, usage, pausedUntil, fullDays | `pausedUntil` | — |

Enum: `FlxMailChannel`, `FlxMailLogStatus`, `FlxMailCampaignStatus`, `FlxMailQueueStatus`, `FlxMailConnectionType`, `FlxMailSender`, `FlxMailMailer`, `FlxMailSecure`, `FlxMailDraftStatus`, `FlxMailBlockKind`.

Connection (ilk 50): logs, templates, campaigns, queue rows, blocklist, mailbox, drafts.

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-mail:settings` | `flxMailSettings`, `flxMailQuota` | `flxMailSettingsUpdate` |
| `flx-mail:templates` | `flxMailTemplates` | şablon mutation |
| `flx-mail:log` | `flxMailLogs` | gönderim (kuyruk dilimi, test) |
| `flx-mail:queue` | `flxMailQueue` | `flxMailQueueRun`, kampanya mutation |
| `flx-mail:connections` | `flxMailConnections` | bağlantı mutation |
| `flx-mail:mailbox` | mailbox sorguları | sync, mark, draft send |
| `flx-mail:blocklist` | `flxMailBlocklist` | block/optout mutation |

Kuyruk ve posta kutusu `Cache-Control: no-store` kalır (bugün mailbox listesi böyle). Polling persisted GET ise tag yine yazılır; ekran aralığı 10 sn altına inmez.

## Açık sorular

Ürün kararları uygulayıcı turunda koordinatöre gider. Seçenekler PR metninde ve teslim notunda. Burada yalnız başlık:

- Cloud kurulumunda verify token klasik REST'te mi kalsın (öneri: evet, GraphQL'de yok).
- Parola yazma admin-post istisnası mı kalsın (öneri: evet; kit değişmez).
- Kuyruk `flxMailQueueRun` ekranı 10 sn mi (bugün kuyruk 5 sn, kampanya 1,5 sn).
- Posta kutusu AI düğmesi `flxMailDraftAi` mi, yoksa düğme Flux AI sohbetine mi gitsin.
