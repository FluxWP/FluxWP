# Flux Chat (flx-chat): REST → GraphQL eşlemesi

Paket sürümü: 1.48.0 (`plugins/flx-chat/flx-chat.php`) · Önek: `flxc_` · Dal: `gql/flx-chat-1` · Durum: taslak (envanter)

Bu dosya kod taramasıdır. REST cevapları bu geçişte değişmez. GraphQL eklenir.

## Özet

Satır = bir yöntem + yol, ya da bir kayıt. GraphQL adı yeni istemcinin kullanacağı alan; REST durur.

| Kategori | Satır | GraphQL | İstisna |
|---|---:|---:|---:|
| REST `flx-chat/v1` | 101 | 94 | 7 |
| AJAX (`wp_ajax_` / `wp_ajax_nopriv_`) | 0 | 0 | 0 |
| admin-post | 4 | 2 | 2 |
| Admin menü sayfası | 4 | 4 | 0 |
| MCP filtresi (`flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools`) | 0 | 0 | 0 |
| Asistan function-call aracı (GraphQL kökü değil) | 34 | 0 | 0 |
| Ayar grubu (`flxc_settings`; `flx_settings_schema` yok) | 7 sekme | 7 | 0 |
| Gizli alan (ayrı işaretli) | 3 | 3 (yaz-only / maske) | 0 |
| Cron / tek seferlik kanca | 5 | durum sorgusu | gönderim döngüsü mevcut loopback'te |
| SSE / `text/event-stream` | 0 | — | bu pakette uç yok |
| Shortcode + blok | 2 | — | vendored imza, sohbet verisi değil |

İstisna satırları `docs/graphql/durum/flx-chat.md`. Ortak `ISTISNALAR.md` satırı "AI sohbet akışı (SSE)" bu pakette bir uca denk gelmiyor: AI `flxai_chat()` ile tek JSON cevap (`includes/ai.php:98`).

Menü 4 sayfa. Brief'teki beşinci "stüdyo" ayrı `add_submenu_page` değil; `page=flx-chat-asistan` sayfasının JS uygulaması (`flxc_render_studio`, `assets/js/studio.js`).

## Yetki

| cap | Kim | Nerede |
|---|---|---|
| `flxc_use` | Temsilci. `manage_options` de geçer. | `includes/helpers.php:243`, rol `install.php:320` |
| `flxc_manage` | Yönetici (ayar, asistan, hafıza, kara liste, temizlik). `manage_options` de geçer. | `includes/helpers.php:247`, `install.php:328` |
| `flxc_user_can_see_chat` | `restrict_agents` açıksa temsilci yalnız atandığı sohbeti görür. | `includes/store.php:220` |
| `flx_reports_manage` | Rapor sayfası. Yoksa menü `flxc_manage` ister. Cap Flux Roles'ta. | `includes/reports.php:346` |
| `flxc_can_record_donation` / `flxc_can_record_certificate` | REST `permission_callback` yine `flxc_can_use`; asıl kontrol callback içinde. | `includes/integrations.php:21`, `:26` |

Kataloga yazılacaklar: `flxc_use`, `flxc_manage`. `flx_reports_*` bu paketin kataloğuna tekrar yazılmaz.

## REST uçları

Namespace `flx-chat/v1`. Kim sütunu: **klasik** = `assets/js/app.js`, **React** = `packages/core/src/modules/flxc.ts` (wp-admin `ui_flux`, FluxApp, `apps/mobile` gelen kutusu hep `WaEngine`), **stüdyo** = `assets/js/studio.js` + `bench.js`, **ayar** = `assets/js/settings.js`.

### Sohbet paneli (`includes/rest.php:44-86`)

Hepsi `flxc_can_use`, son dört `flxc_can_manage`.

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Kim |
|---|---|---|---|---|---|---|
| GET | `/bootstrap` | `flxc_use` | `flxChatInbox` | — | `FlxChatInbox!` | klasik, React |
| GET | `/sync` | `flxc_use` | `flxChatUpdates(since:)` | `since`, `chat`, `after` | `FlxChatUpdates!` | klasik, React |
| GET | `/chats` | `flxc_use` | `flxChats` | `filter`, `q`, `label`, `channel`, `first`/`after` | `FlxChatConnection!` | klasik, React |
| POST | `/chats` | `flxc_use` | `flxChatCreate` | `FlxChatCreateInput` | `{ chat, errors }` | klasik, React |
| GET | `/chats/{id}` | `flxc_use` | `flxChat` | `id` | `FlxChat` | klasik, React |
| POST | `/chats/{id}` | `flxc_use` | `flxChatUpdate` | `FlxChatUpdateInput` | `{ chat, errors }` | klasik, React |
| GET | `/chats/{id}/messages` | `flxc_use` | `flxChatMessages` | `chatId`, `before`, `first` ≤ 50 | `FlxChatMessageConnection!` | klasik, React |
| POST | `/chats/{id}/messages` | `flxc_use` | `flxChatMessageSend` | metin veya `uploadId` | `{ message, errors }` | klasik, React (multipart dahil) |
| POST | `/chats/{id}/read` | `flxc_use` | `flxChatMarkRead` | `id` | `{ chat, errors }` | klasik, React |
| POST | `/chats/{id}/typing` | `flxc_use` | `flxChatTyping` | `state` | `{ ok, errors }` | klasik, React |
| POST | `/chats/{id}/avatar` | `flxc_use` | `flxChatAvatarRefresh` | `id` | `{ chat, errors }` | klasik |
| POST | `/chats/{id}/ai` | `flxc_use` | `flxChatSuggest` | `fresh`, `hint` | `{ text, errors }` | klasik, React. Tek JSON; akış yok |
| POST | `/chats/{id}/export` | `flxc_use` | `flxChatExport` | `format`, `mask`, `notes` | `{ body, filename, errors }` | yeni arayüz (`export.php:232`). Dosya gövdesi REST'te kalır |
| POST | `/messages/{id}/retry` | `flxc_use` | `flxChatMessageRetry` | `id` | `{ message, errors }` | klasik, React |
| POST | `/messages/{id}/react` | `flxc_use` | `flxChatMessageReact` | `emoji` | `{ message, errors }` | klasik, React |
| POST | `/messages/{id}/edit` | `flxc_use` | `flxChatMessageEdit` | `text` | `{ message, errors }` | klasik |
| DELETE | `/messages/{id}` | `flxc_use` | `flxChatMessageDelete` | `scope` | `{ message, errors }` | klasik, React |
| GET | `/media/{id}` | `flxc_use` | İSTİSNA | | | Binary + `Range` (`rest.php:782`, `flxc_stream_file` `:808`). GraphQL `FlxFile.url` bu adresi verir |
| POST | `/uploads` | `flxc_use` | `flxChatMessageSend.uploadId` | ortak `POST /flx/v1/upload` bağlam `flx-chat/outbound` | | klasik composer. Ayrı şema tipi yok |
| DELETE | `/uploads/{token}` | `flxc_use` | aynı | iptal, ortak yükleme | | klasik |
| POST | `/check-number` | `flxc_use` | `flxChatCheckNumber` | `phone`, `conn` | `{ result, errors }` | klasik, React |
| GET | `/link-preview` | `flxc_use` | `flxChatLinkPreview` | `url` | `FlxChatLinkPreview` | klasik, React |
| GET | `/replies` | `flxc_use` | `flxChatReplies` | `first` ≤ 50 | `FlxChatReplyConnection!` | klasik. React listeyi bootstrap'tan okur |
| POST | `/replies` | `flxc_use` | `flxChatReplySave` | `FlxChatReplyInput` | `{ reply, errors }` | klasik |
| DELETE | `/replies/{id}` | `flxc_use` | `flxChatReplyDelete` | `id` | `{ errors }` | klasik |
| POST | `/replies/{id}/use` | `flxc_use` | `flxChatReplyUse` | `chatId` | `{ text, errors }` | klasik, React |
| POST | `/admin/connections/{conn}/test` | `flxc_manage` | `flxChatConnectionTest` | `id` | `{ ok, errors }` | ayar |
| POST | `/admin/connections/{conn}/webhook` | `flxc_manage` | `flxChatConnectionWebhook` | `id` | `{ ok, errors }` | ayar. Evolution'a URL yazar |
| POST | `/admin/connections/{conn}/qr` | `flxc_manage` | `flxChatConnectionQr` | `id` | `{ qr, errors }` | ayar |
| POST | `/admin/connections/{conn}/import` | `flxc_manage` | `flxChatConnectionImport` | `id` | `{ ok, errors }` | ayar. Uzun iş; adım adım |

`/sync` bugün `limit: 200` sohbet döner (`rest.php:176`). GraphQL deltası en çok 50 + `truncated`.

### Gelen webhook ve Meta dosyası

| Yöntem | Yol | cap | → GraphQL | Kim | Not |
|---|---|---|---|---|---|
| POST | `/webhook/{conn}` ve `/{ev}` | `__return_true`; gövde `?t=` ile `hash_equals` (`webhook.php:11-27`) | İSTİSNA | Evolution API | Dış sistem sabit URL'e POST atar |
| GET | `/wacloud-media/{key}` | `__return_true`; 32 karakterlik transient (`wacloud.php:140`) | İSTİSNA | Meta Cloud API dosyayı indirir | 1 saatlik geçici dosya |

Cloud olayının kendisi bu paketin REST'i değil: Flux Mail `flxi_whatsapp_event` yayar, chat dinler (`wacloud.php:23`).

### Hatırlatıcı, hafıza, kuyruk

| Yöntem | Yol | cap | → GraphQL | Dosya |
|---|---|---|---|---|
| GET | `/reminders` | `flxc_can_manage` | `flxChatReminders` | `reminders.php:668` |
| POST | `/reminders/{id}` | `flxc_can_manage` | `flxChatReminderSetStatus` | `:688` |
| DELETE | `/reminders/{id}` | `flxc_can_manage` | `flxChatReminderDelete` | `:703` |
| GET | `/memory/chat/{id}` | `flxc_manage` | `flxChatMemory` | `memory.php:472` |
| POST | `/memory/chat/{id}` | `flxc_manage` | `flxChatMemorySave` | `:484` |
| DELETE | `/memory/chat/{id}` | `flxc_manage` | `flxChatMemoryForget` | `:519` (`confirm=yes`) |
| POST | `/memory/chat/{id}/summarize` | `flxc_manage` | `flxChatMemorySummarize` | `:536` kuyruğa alır |
| DELETE | `/memory/item/{id}` | `flxc_manage` | `flxChatMemoryItemDelete` | `:553` |
| GET | `/memory/stats` | `flxc_manage` | `flxChatMemoryStats` | `:575` |
| GET | `/jobs` | `flxc_manage` | `flxChatJobs` | `jobs.php:451` |
| POST | `/jobs/scan` | `flxc_manage` | `flxChatJobsScan` | `:460` |
| POST | `/jobs/bg/{id}` | `hash_equals(flxc_bench_key())` (`:479`) | İSTİSNA | Loopback. Oturum yok |

### Kara liste (`includes/blocklist.php:214-272`)

Hepsi `flxc_manage`. Klasik sohbet menüsü (`app.js:810`, `:824`, `:836`).

| Yöntem | Yol | → GraphQL |
|---|---|---|
| GET | `/blocklist` | `flxChatBlocklist` (`q`) |
| POST | `/blocklist` | `flxChatBlockAdd` (sohbet id ya da telefon/e-posta) |
| DELETE | `/blocklist/{id}` | `flxChatBlockRemove` |
| POST | `/chats/{id}/unblock` | `flxChatUnblock` |

### Kayıtlar (bağış / sertifika / dekont)

`permission_callback` hepsi `flxc_can_use` (`integrations.php:36-57`). Bağış/sertifika yazma içeride `flxc_can_record_*`.

| Yöntem | Yol | → GraphQL | Not |
|---|---|---|---|
| GET | `/records/search` | `flxChatRecords` | Donate/Certificates sorgusuna delege. Bağımlı paketler GraphQL'e geçince ekran onların sorgusunu çağırır |
| GET | `/records/chat/{id}` | `flxChat.records` | Açık sohbet |
| POST | `/records/donations` | `flxChatDonationRecord` | Sohbetten kayıt. Donate mutation'ı hazır olunca ince sarmalayıcı |
| GET | `/records/donations/{id}` | `flxChatDonation` | |
| POST | `/records/donations/{id}/status` | `flxChatDonationStatus` | |
| POST | `/records/certificates` | `flxChatCertificateRecord` | |
| GET | `/records/certificates/{id}` | `flxChatCertificate` | |
| POST | `/records/certificates/{id}/send` | `flxChatCertificateSend` | WhatsApp'a gönderir |
| POST | `/messages/{id}/receipt` | `flxChatReceiptAttach` | |
| DELETE | `/messages/{id}/receipt` | `flxChatReceiptDetach` | |

### Asistan sayfası

Hepsi `flxc_can_manage`, kullanım/limit satırları `flxc_can_use`.

| Yöntem | Yol | → GraphQL | Dosya |
|---|---|---|---|
| POST | `/chats/{id}/bot` | `flxChatBotSet` | `bot.php:2442` · klasik, React |
| POST | `/chats/{id}/clear` | `flxChatClear` | `export.php:320` · `confirm=yes` |
| GET | `/bot/studio` | `flxChatStudio` | `bot-studio.php:597` |
| GET | `/bot/chats` | `flxChatBotChats` | aynı dizi |
| POST | `/bot/prompt` | `flxChatPromptSave` | |
| POST | `/bot/prompt/activate` | `flxChatPromptActivate` | |
| POST | `/bot/prompt/preview` | `flxChatPromptPreview` | |
| POST | `/bot/docs` | `flxChatDocSave` | |
| DELETE | `/bot/docs/{id}` | `flxChatDocDelete` | |
| POST | `/bot/editor` | `flxChatPromptTurn` | Eski tek dönüş. Stüdyo bunu çağırmaz; oturum ucu var. Yine de taşınır |
| POST | `/bot/test` | `flxChatTestTurn` | Sandbox. Kayıt açmaz |
| GET | `/bot/stats` | `flxChatBotStats` | `bot-stats.php:286` · `range`: today, 7, 30, month, all |
| GET | `/chats/{id}/usage` | `flxChatUsage` | `:308` |
| POST | `/chats/{id}/usage/reset` | `flxChatUsageReset` | `:320` |
| POST | `/bot/limit` | `flxChatDailyLimit` | `:345` |
| GET | `/bot/editor/sessions` | `flxChatEditorSessions` | `bot-editor.php:514` · `status`, `q` |
| GET | `/bot/editor/sessions/{id}` | `flxChatEditorSession` | `:515` |
| POST | `/bot/editor/sessions/{id}` | `flxChatEditorSessionUpdate` | aynı |
| DELETE | `/bot/editor/sessions/{id}` | `flxChatEditorSessionDelete` | aynı |
| POST | `/bot/editor/step` | `flxChatEditorStep` | `:524` · tek JSON, SSE değil |

### Benchmark (`includes/bench.php:1597-1796`)

Panel uçları `flxc_manage`. Arka plan ucu anahtarlı.

| Yöntem | Yol | → GraphQL |
|---|---|---|
| GET | `/bot/bench` | `flxChatBench` (`light` argümanı) |
| POST | `/bot/bench/scenarios` | `flxChatBenchScenarioSave` |
| DELETE | `/bot/bench/scenarios/{id}` | `flxChatBenchScenarioDelete` |
| POST | `/bot/bench/scenarios/reset` | `flxChatBenchScenariosReset` |
| POST | `/bot/bench/scenarios/ai` | `flxChatBenchScenarioDraft` |
| POST | `/bot/bench/runs` | `flxChatBenchRunStart` |
| GET | `/bot/bench/runs/{id}` | `flxChatBenchRun` (`since`) |
| GET | `/bot/bench/runs/{id}/report` | `flxChatBenchReport` |
| DELETE | `/bot/bench/runs/{id}` | `flxChatBenchRunDelete` |
| POST | `/bot/bench/runs/{id}/step` | `flxChatBenchStep` |
| POST | `/bot/bench/runs/{id}/state` | `flxChatBenchRunState` |
| POST | `/bot/bench/bg/{id}` | İSTİSNA · `hash_equals(flxc_bench_key())` (`:1785`) |

### Avatar, eski uygulama sözleşmesi, model listesi

| Yöntem | Yol | cap | → GraphQL | Kim |
|---|---|---|---|---|
| GET | `/chats/{id}/photo` | `flxc_can_use` | İSTİSNA | Binary (`avatar.php:201`). `FlxChat.avatarUrl` bu adres |
| POST | `/avatars/check` | `flxc_can_use` | `flxChatAvatarsCheck` | klasik |
| GET | `/app/chats` | `flxc_can_use` | `flxChats` | Eski sözleşme `packages/core/src/modules/cagri.ts:40`. Güncel mobil bunu çağırmaz |
| GET | `/app/chats/{id}` | `flxc_can_use` | `flxChat` | `cagri.ts:41` |
| GET | `/app/chats/{id}/messages` | `flxc_can_use` | `flxChatMessages` | `cagri.ts:42` |
| POST | `/app/chats/{id}/send` | `flxc_can_use` | `flxChatMessageSend` | `cagri.ts:44` |
| POST | `/app/chats/{id}/read` | `flxc_can_use` | `flxChatMarkRead` | `cagri.ts:45` |
| POST | `/app/chats/{id}/bot` | `flxc_can_use` | `flxChatBotSet` | `cagri.ts:46` |
| GET | `/app/media/{id}` | `__return_true`; `u`,`e`,`s` imza (`app.php:193`) | İSTİSNA | `<img>` / `<audio>` başlık gönderemez |
| GET | `/admin/ai/models` | `flxc_manage` | `flxChatModels` | ayar + stüdyo model seçici |
| POST | `/admin/ai/test` | `flxc_manage` | `flxChatAiTest` | ayar (`ai.php:641`) |

## AJAX

`wp_ajax_` / `wp_ajax_nopriv_` kaydı yok. `includes/flx-brand/core.php:876` `flx_job_kick` ortak imza yardımcısı; bu paket ona `wp_ajax_` bağlamıyor.

## admin-post

| Action | cap / nonce | → GraphQL | Dosya |
|---|---|---|---|
| `flxc_save_settings` | `flxc_can_manage` / `flxc_save_settings` | `flxChatSettingsSave` | `settings-page.php:4`, `:670` |
| `flxc_reports` | `flxc_reports_can_manage` / `flxc_reports` | `flxChatReportSave`, `flxChatReportSend`, `flxChatReportDelete` | `reports.php:361` · `do`: save, add, test, sendnow, delete |
| `flxc_export` | tek sohbet `flxc_can_use`, toplu `flxc_can_manage` / `flxc_export` | İSTİSNA (dosya indir) | `export.php:166`. Tek sohbetin metni ayrıca `flxChatExport`. Toplu (en çok 1000 sohbet, `:163`) 60 sn sınırına sığmaz |
| `flxc_receipt` | `flxc_can_use` veya `flxb_can_view` | İSTİSNA (binary) | `integrations.php:1032`. GraphQL aynı `FlxFile.url` |

## Admin sayfaları

Toplu seçim (checkbox) yok. Sohbette eylemler satır menüsünde.

| Sayfa (`page=`) | cap | Ne gösterir | İstemci |
|---|---|---|---|
| `flx-chat` | `flxc_use` | Sohbetler. `admin.php:30` | React `packages/modules/src/whatsapp` wp-admin varsayılanı olur. Klasik `app.js` kullanıcı meta anahtarıyla kalır |
| `flx-chat-asistan` | `flxc_manage` | AI asistan = stüdyo. `admin.php:40`, `flxc_render_studio:253` | Yeni React ekranı |
| `flx-chat-ayarlar` | `flxc_manage` | Ayarlar. `admin.php:41` | Yeni React ekranı |
| `flx-chat-raporlar` | `flx_reports_manage` yoksa `flxc_manage` | WhatsApp raporları. `reports.php:347` | Yeni React ekranı |

### `flx-chat` alt görünümleri (klasik `app.js`; React bugün bunların bir kısmını yapar)

Filtre çipleri (`app.js:411`): `all`, `unread`, `mine`, `unassigned`, `ai`, `closed`. Üstüne kanal çipi (`flxc_channels`, `connections.php:84`) ve etiket çipi. Arama `q`.

Yan menü (`app.js:631`): yeni sohbet, hazır cevaplar, kayıt arama, çip görünürlüğü, tema, bildirim, ses, toplu dışa aktarma (yönetici), hatırlatıcılar, kara liste, ayarlar.

Sohbet menüsü (`app.js:654`): kişi bilgisi, sabitle, okunmadı, ata/bırak, bağış kaydı, bağışlar ve sertifikalar, dışa aktar, kapat/aç, asistan kullanımı, kişi hafızası, hatırlatıcılar, kara liste, geçmişi temizle.

Açık sohbet: mesajlar, yanıtla, tepki, yeniden dene, düzenle, sil (panel / herkesten), not, medya, ses, AI öneri, yazıyor, hazır cevap, link önizleme, dekont eşleştir, kişi paneli (`sections`, not, ad, personel bağı).

React motorunun bugün çağırdığı uçlar `packages/runtime/src/modules/whatsapp/engine.ts` ve `packages/modules/src/whatsapp/*`: bootstrap, sync, chats, chat, messages, send, read, typing, bot, retry, react, delete, update, checkNumber, newChat, linkPreview, useReply, ai, media. Klasikte olup React'te olmayanlar (hazır cevap CRUD, hafıza, hatırlatıcı, kara liste, temizlik, kullanım, dışa aktarma, kayıt diyalogları, mesaj düzenleme, avatar yenileme) ekran kuralı gereği aynı React sohbet ekranına girer.

### `flx-chat-asistan` sekmeleri (`studio.js:91`)

`chats` (istatistik aralığı today/7/30/month/all), `prompt`, `docs`, `editor` (filtre `active`/`archived` + arama, `:437`), `test`, `bench`, `memory`, `jobs` (sekme açıkken poll).

### `flx-chat-ayarlar` sekmeleri (`settings-page.php:53`)

`baglantilar`, `genel`, `otomatik`, `etiketler`, `kayitlar`, `ai`, `kontrol`. `ai` ve `kontrol` ana formun kaydet düğmesini gizler; AI sekmesinin kendi kaydet'i var (`:97`, `:666`). Kontrol sekmesi `flxc_system_checks()` tablosu, yazma yok.

Bağlantı satırı eylemleri: test, QR, webhook kur, son sohbetleri içe aktar, gizli anahtarı yenile (`settings-page.php:251-270`).

### `flx-chat-raporlar`

Sekme yok. Her rapor kartı (`reports.php:465`): başlık, aktif, kime (`receive` / `view` / `users`), içerik (`own` / `general`), dönem, sıklık, haftanın günü, ayın günü, saat, seçili kullanıcılar, motivasyon satırları, önizleme. Eylemler: kaydet, yeni, bana test, herkese şimdi gönder, sil.

### Yazı editörü kutusu

`add_meta_box` `flxc-bot-hide` (`site-search.php:266`): "asistan bu içeriği paylaşmasın", `save_post` `:281`, cap `flxc_manage`. → `ContentNode.flxChatHidden` (cap `flxc_manage`). Sohbet sayfası değil.

## MCP / AI araçları

`flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools` filtresi yok. Anahtar bu pakette tutulmaz; `flxc_ai_chat` `flxai_chat()` çağırır (`ai.php:98`, ayar metni `settings-page.php:445`).

Aşağıdakiler modelin function-call araçları. GraphQL kökü olmazlar; serviste kalırlar. Başka paket `flxc_staff_tool_list` ve `flxc_bot_info_tools` ile ekler.

| Araç | Dosya |
|---|---|
| `save_details`, `send_price_list`, `set_order`, `create_donation`, `payment_link`, `cancel_donation`, `handoff`, `opt_out`, `my_records`, `certificate_resend`, `read_doc` | `bot.php:1184-1282` |
| `verify_send`, `verify_check` | `verify.php:349` |
| `receipt_check` | `dekont.php:158` |
| `donation_options`, `payment_info`, `current_time` | `bot-info.php:29` |
| `close_chat`, `react` | `bot-close.php:146` |
| `reminder_set`, `reminder_list`, `reminder_cancel` | `reminders.php:364` |
| `search_site` | `site-search.php:214` |
| `remember`, `forget` | `memory.php:391` |
| `staff_create_donation`, `staff_payment_link`, `staff_send_certificate`, `staff_my_donations`, `staff_report` | `staff.php:184-254` |
| `find_chats`, `read_chat`, `propose_prompt`, `test_message` | `bot-editor.php:142` (düzenleyici oturumu) |

## Ayarlar

`flx_settings_schema` yok. Tek seçenek `flxc_settings` (`helpers.php:108`), anahtarlar `flxc_default_settings` (`:9-105`). GraphQL'de elle `FlxChatSettings` / `FlxChatSettingsInput`. `flxSettings` altına kendiliğinden düşmez. Gizli alanlar ads kalıbı: okumada yalnız "kayıtlı mı", yazmada boş = kalsın, `"__clear__"` = sil.

| Grup | Alanlar | GraphQL | Not |
|---|---|---|---|
| Bağlantı (gizli) | `connections[].api_key` | `apiKey: String` yaz-only | Password input, boşsa eskisi kalır (`settings-page.php:243`, `:712`). `flx_secret_seal` (`helpers.php:129`) |
| Bağlantı (gizli) | `connections[].secret` | okunmaz; `rotateSecret: Boolean` | Üretilir (`:716`). Webhook URL `?t=` ile gömer (`connections.php:311`). URL yalnız `flxChatSettings` içinde, cap `flxc_manage` |
| Seçenek (gizli) | `flxc_bench_key` | okunmaz | Loopback anahtarı (`bench.php:1530`). Ayar formu değil |
| Bağlantı (açık) | `id`, `label`, `source`, `provider_id`, `base_url`, `instance` | `FlxChatConnection` | `source`: own / `flxi:` / `flxs:` |
| Genel | `brand_name`, `accent`, `send_read`, `agent_signature`, `restrict_agents`, `show_groups`, `ui_flux`, `delete_data`, `poll_interval`, `delete_everyone_hours` | `FlxChatSettings` | `poll_interval` formda 3–60 (`settings-page.php:731`). Yeni istemci en az 10 sn kullanır |
| Otomatik yanıt | `welcome_*`, `away_*` | aynı | |
| Etiketler | `labels[]` id, name, color | `FlxChatLabel` düz liste (küçük küme; 50'yi aşarsa connection) | |
| Kayıtlar | `agents_donations`, `agents_certificates`, `chips_bar`, `msg_donation_*`, `msg_receipt`, `bank_text` | aynı | `bank_iban` da AI sekmesinde; bağışçıya giden metin, gizli değil |
| AI ve asistan | `ai_provider`, `ai_model`, `ai_model_bot`, `ai_model_vision`, `ai_model_audio`, `ai_temperature`, `ai_auto_suggest`, `ai_instructions`, `bot_*`, `mem_*`, `workers_*`, `mirror_*`, `rem_*`, `email_verify`, `verify_*`, `staff_*`, `ref_*`, `benef_*`, `notify_to_group`, `bank_holder`, `bank_iban`, `bank_desc`, `donate_url` | aynı | AI anahtarı yok. Model boşsa Flux AI'daki paket modeli |

Klasik `ui_flux` (`settings-page.php:300`) React'i deneysel açar. Geçişten sonra wp-admin varsayılanı React'tir; klasik ayrı kullanıcı meta anahtarı (`flxc_ui`).

## Cron ve loopback

WP-Cron bu sunucuda tetiklenmez. Asıl iş loopback + panel/webhook dürtmesi.

| Kanca | Ne | GraphQL |
|---|---|---|
| `flxc_jobs_tick` saatlik (`jobs.php:415`) | `flxc_jobs_pump` | Durum `flxChatJobs`. Çalıştırma `flxChatJobsScan`. İşin kendisi İSTİSNA `/jobs/bg/{id}` |
| `flxc_rem_tick` tek sefer (`reminders.php:517`) | hatırlatıcı saati | Aynı kuyruk |
| `flxc_reports_tick` (`reports.php:304`, `:320`) | raporu WhatsApp'tan gönderir. `flxc_jobs_poked` `spawn_cron()` çağırır (`:321`) | Tanım `flxChatReports`. Gönderim bu kancada kalır |
| `flxc_bench_tick` (`bench.php:1543`) | benchmark yedeği | Adım İSTİSNA `/bot/bench/bg/{id}` |
| `flxc_wacloud_temp_clean` (`wacloud.php:122`) | 1 saatlik dosyayı siler | Uç İSTİSNA |

`flxc_loopback` (`helpers.php:317`) 3 sn timeout ile kendi REST'ine POST atar. `GET /flx-chat/v1/*` dönüşünde `flxc_jobs_maybe_pump` (`jobs.php:430`).

## SSE

Uç yok. "Cevap akarken 3 sn" metni (`settings-page.php:307`) bot meşgulken poll aralığıdır, stream değil. Öneri, test turu ve düzenleyici adımı tek JSON döner.

## Medya

| Akış | GraphQL |
|---|---|
| Giden dosya `POST /uploads` | Ortak `flx/v1/upload`, bağlam `flx-chat/outbound`, sonra `uploadId` |
| Gelen/giden dosya `GET /media/{id}` | İSTİSNA binary. Mesajda `FlxFile` |
| Avatar `GET /chats/{id}/photo` | İSTİSNA binary |
| Uygulama `GET /app/media/{id}?u&e&s` | İSTİSNA imzalı |
| Meta `GET /wacloud-media/{key}` | İSTİSNA |
| Dekont `admin-post flxc_receipt` | İSTİSNA binary |
| Dışa aktarma dosyası | İSTİSNA admin-post. Tek sohbet metni `flxChatExport` |

## Shortcode, blok, herkese açık widget

| Kayıt | Dosya | Not |
|---|---|---|
| `[flx_credit]` | `includes/flx-brand/core.php:134` | Vendored FluxWP imzası. Sohbet verisi değil, GraphQL tipi yok |
| blok `flx/credit` | aynı dosya `:149` | Başka kopya kayıtlıysa atlanır (`:139`) |

Sohbetin kendi ön yüz shortcode'u veya herkese açık formu yok. "Site chat" kanalı `flxc_channels` ile başka paketin kaydı (`connections.php:103`, filtre `:111`).

## React ve mobilin bugün çağırdığı uçlar

`flxc()` (`packages/core/src/modules/flxc.ts:177-218`):

| Uç | Ekran |
|---|---|
| `GET /bootstrap` | gelen kutusu açılışı |
| `GET /sync` | canlı yenileme |
| `GET /chats`, `POST /chats` | liste, yeni sohbet |
| `GET/POST /chats/{id}` | başlık, ata, sabitle, kapat, ad, not |
| `GET/POST /chats/{id}/messages` | geçmiş, gönder (JSON veya multipart) |
| `POST …/read`, `…/typing`, `…/bot` | okundu, yazıyor, asistan |
| `POST /messages/{id}/retry`, `/react`, `DELETE /messages/{id}` | balon |
| `POST /check-number` | yeni sohbet |
| `GET /link-preview` | kart |
| `POST /replies/{id}/use` | hazır cevap |
| `POST /chats/{id}/ai` | öneri |
| medya URL'si blob | `packages/modules/src/whatsapp/media.tsx` |

`apps/mobile` ayrıca `flx-chat/v1` yazmıyor. Gelen kutusu `WaEngine` (`apps/mobile/src/modules/inbox/InboxScreen.tsx`). `cagri.ts` `/app/*` istemcisini export eder; mobil ekran onu çağırmaz. O uçların cevabı da değişmez (eski sözleşme, `docs/fluxapp/MODUL-API.md`).

Poll bugün: sekme gizliyken durmaz, taban 60 sn; bot meşgulken 3 sn (`packages/runtime/src/modules/whatsapp/engine.ts:307-310`). GraphQL istemcisi KURALLAR §8: en az 10 sn, sekme gizliyken durur. REST istemcisi olduğu gibi kalır.

## Tipler

| Tip | Alanlar (kısa) | Nullable | Loader |
|---|---|---|---|
| `FlxChat` | id, title, phone (`FlxPhone`), channel, status, labels, unread, pinned, lastMessage, bot, assignedTo, rev | `person: FlxPerson` — CRM kaydı yoksa veya `flxcrm_gql_can_see_contact` false ise null. Liste sorgusunda istenmezse yüklenmez | `FLXC_Chat_Loader` (`can_see` = `flxc_user_can_see_chat`) |
| `FlxChatMessage` | id, dir, type, body, status, ts (`FlxDateTime`), media (`FlxFile`), quoted | `media` metinde null. `person` yok | mesaj, sohbet loader'ından |
| `FlxChatUpdates` | rev, chats (≤ 50), messages (açık sohbet, ≤ 50), incoming, connectionStates, unread, truncated | `truncated` true ise istemci `since`'i yeni rev ile bir kez daha sorar | — |
| `FlxChatInbox` | me, agents, labels, channels, replies, unread, ai, bot | `ai` Flux AI hazır değilse null | — |
| `FlxChatSettings` | yukarıdaki açık alanlar | webhook URL yalnız yönetici sorgusunda | — |
| enum | `FlxChatStatus` OPEN/CLOSED, `FlxChatFilter`, `FlxChatDir`, `FlxChatMsgType`, `FlxChatBot` ON/OFF/PAUSED | | |

`since` milisaniye `rev` (`helpers.php:175`), `FlxDateTime` değil.

Önbellek: `flx-chat:chat:{id}`, `flx-chat:list`, `flx-chat:settings`. Yazma purge eder. Poll sorgusu cache'lenmez (girişli POST/GET kuralı). LiteSpeed için bugünkü no-store (`rest.php:9`) REST'te kalır.

## Açık sorular

Ürün kararları uygulayıcı turunda sorulacak. Teknik varsayılanlar KURALLAR'da.

- CRM kişi kartına sohbet bölümü: bu dalda `flxcrm_person_card_*` kaydı yok. Kayıt `gql/flx-crm-2` merge'ünden sonra.
- `FlxPerson` görünürlüğü: temsilcide `flxcrm_view` yok. Öneri: filtre, yalnız kullanıcının görebildiği sohbetin telefonu için true.
- Rapor gönderimi `spawn_cron()` (`reports.php:321`). Hostinger cron'u çalıştırmıyor. Öneri: jobs ile aynı loopback; şimdi istisna satırı cron'a bağlı kaldığı için ayrıca yazıldı.
- Bağış/sertifika mutation'ları Donate ve Certificates GraphQL'i gelene kadar chat sarmalayıcısı. Sonra ekran onların mutation'ını çağırır.
