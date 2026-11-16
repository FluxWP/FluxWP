# Flux Forms (flx-forms): REST → GraphQL eşlemesi

Paket sürümü: 2.4.0 · Önek: `flxf_` / fork `Emsfb` · Dal: `gql/flx-forms-1` · Durum: taslak (envanter)

Bu dosya yalnız envanterdir. Satırlar koddan okundu (`register_rest_route`, `wp_ajax_`, `add_menu_page` / `add_submenu_page`, `add_shortcode`, `register_block_type`, `flx_mcp_tools`, `flx_settings_schema`). `vendor/stripe` graft indeksinde yoktu; orası ayrıca tarandı. Gizli anahtar değeri yok.

Sahip kararı (bağlayıcı): fork form oluşturucu kendi REST/AJAX'ıyla kalır. GraphQL'e taşınan: form tanımı okuma, public `flxFormSubmit`, yanıtlar/mesajlar ekranı, pano istatistikleri. Ödeme başlatma ve webhook kalıcı istisna. `docs/graphql/ISTISNALAR.md` içindeki geçici "Blok formu gönderimi" satırı (`fxb_process_ajax_submit`, `fxb_process_advanced_form_submit`) bu paketin dosyası değil; silinmesi ayrı küçük PR. Bağlantı `docs/flx-blocks/GRAPHQL.md`.

## Özet

| Kategori | Satır | GraphQL | İstisna | Not |
|---|---|---|---|---|
| REST kaydı | 25 | 9 | 16 | 4 ad alanı: `Emsfb/v1` (20), `efb/v1` (2), `flx-forms/v1` (3), `flxf/v1` (3 kayıt / 2 yol) |
| AJAX | 38 | 12 | 26 | Hepsi `wp_ajax_` (oturumlu). `wp_ajax_nopriv_` yok |
| admin-post | 4 | 0 | 4 | Dosya, yönlendirme, webhook sırrı, sistem kontrolü |
| Admin sayfası | 1 menü + 8 alt menü | 2 ekran | 6 sayfa | Stripe alt menüsü kaydolup silinir. Panel karışık (yanıtlar GraphQL, ayarlar klasik). WP pano widget'ı ayrı |
| MCP/AI aracı | 0 | 0 | 0 | `flx_mcp_tools` / `flxseo_mcp_tools` yok |
| `flx_settings_schema` | 0 | — | — | Ayarlar `{prefix}emsfb_setting` satırı + `emsfb_settings` seçeneği |
| Shortcode | 6 | 0 | 6 | HTML basar; tanım okuma `flxForm` |
| Blok | 50 | 0 | 50 | `flxf/form` + 47 alan bloğu, `easy-form-builder/form`, `flx/credit` |

"GraphQL" satırı REST'i kapatmaz. REST aynı kalır, servisi çağırır.

## REST uçları

İzin: `check_nonce_permission_efb` (`plugins/flx-forms/includes/class-Emsfb-public.php:128`) `X-WP-Nonce` (`wp_rest`) ister; OPTIONS'ta `exit()` yapar. Aşağıda "nonce" bu demektir.

### `Emsfb/v1` — çekirdek (fork + public)

| Yöntem | Yol | Kayıt | cap | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|---|
| POST | `/Emsfb/v1/test/{name}/{id}` | `class-Emsfb-public.php:41` | nonce | **İSTİSNA** | | | Editör/deneme. Fork |
| GET | `/Emsfb/v1/test/{name}/{id}` | `class-Emsfb-webhook.php:18` | `manage_options` | **İSTİSNA** | | | Aynı yol, ikinci kayıt. Anahtar `method` (WP `methods` bekler); pratikte boş kalabilir. Fork |
| POST | `/Emsfb/v1/forms/message/add` | `class-Emsfb-public.php:46` | nonce, herkese açık nonce ile | `flxFormSubmit` | `FlxFormSubmitInput` | `FlxFormSubmitPayload` | `get_form_public_efb` (`:2233`). İlk dilim: `form` / `survey` / `subscribe`. `login`, `register`, `logout`, `payment` aynı callback'te kalır (istisna alt kümesi, REST değişmez) |
| POST | `/Emsfb/v1/forms/response/get` | `class-Emsfb-public.php:54` | nonce | `flxFormTrack` | `code: String!` | `FlxFormTrackResult` | Onay koduyla yanıt okuma. IP başına transient sınır (`efb_track_lookup_allowed`, `:3866`) |
| POST | `/Emsfb/v1/forms/response/add` | `class-Emsfb-public.php:59` | nonce | `flxFormTrackReply` | `FlxFormTrackReplyInput` | `FlxFormSubmitPayload` | Ziyaretçi yanıt kutusu (`set_rMessage_id_Emsfb_api`, `:4995`). `captchaToken` |
| POST | `/Emsfb/v1/autofill/get` | `class-Emsfb-public.php:64` | nonce | **İSTİSNA** | | | `vendor/autofill/autofillefb.php` (`:5354`). Pro eklenti dosyası |
| POST | `/Emsfb/v1/forms/file/upload` | `class-Emsfb-public.php:69` | nonce | **İSTİSNA** | | | Multipart (`file_upload_api`, `:4818`). `pl` = `msg` veya `resp` |
| POST | `/Emsfb/v1/forms/recovery/efb_set_password` | `class-Emsfb-public.php:75` | nonce | **İSTİSNA** | | | E-postadaki jetonla WP parolası (`set_password_efb_api`, `:7816`) |
| GET | `/Emsfb/v1/nonce/refresh` | `class-Emsfb-public.php:81` | `__return_true` | **İSTİSNA** | | | Çerezden `wp_rest` nonce (`efb_nonce_refresh_api`, `:207`) |

### `Emsfb/v1` — ödeme (kalıcı istisna)

Kayıt `efb_register_payment_rest_routes` (`class-Emsfb-public.php:52`) üzerinden. Tutar gövdeden okunmaz (`payments.php:192`).

| Yöntem | Yol | Kayıt | cap | → GraphQL | Not |
|---|---|---|---|---|---|
| POST | `/Emsfb/v1/forms/payment/start` | `payments.php:149` | nonce | **İSTİSNA** | Aktif sağlayıcıya yönlendirir. Banka/sağlayıcı akışı |
| POST | `/Emsfb/v1/forms/payment/confirm` | `payments.php:156` | nonce | **İSTİSNA** | Tarayıcı dönüşü; sağlayıcı `verify()`. Sabit banka URL'i değil, aynı akış |
| POST | `/Emsfb/v1/forms/payment/webhook/{provider}` | `payments.php:161` | `__return_true` | **İSTİSNA** | Sağlayıcı imzası. Sabit URL |
| POST | `/Emsfb/v1/forms/payment/stripe/card/add` | `vendor/stripe/class-Emsfb-stripe-handler.php:61` | nonce | **İSTİSNA** | PaymentIntent. Fork Stripe |
| POST | `/Emsfb/v1/forms/payment/quote` | `stripe-handler.php:69` | nonce | **İSTİSNA** | Sunucu fiyatı. Ödeme |
| POST | `/Emsfb/v1/forms/payment/stripe/confirm` | `stripe-handler.php:76` | nonce | **İSTİSNA** | Stripe tutar/para birimi kontrolü |
| GET | `/Emsfb/v1/forms/payment/stripe/pkey` | `stripe-handler.php:82` | `__return_true` | **İSTİSNA** | Publishable key. Yalnız `get_stripe_public_key_Emsfb_api` varsa (`class-Emsfb-public.php:6110`, var) |
| POST | `/Emsfb/v1/forms/payment/stripe/webhook` | `stripe-handler.php:89` | `__return_true` | **İSTİSNA** | `Stripe-Signature`. `EMSFB_STRIPE_WEBHOOK_SECRET` veya `emsfb_stripe_webhook_secret` |

### `efb/v1` — yerleştirme (editör okuması + public tanım)

| Yöntem | Yol | Kayıt | cap | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|---|
| GET | `/efb/v1/forms` | `class-Emsfb-gutenberg-block.php:91` | `edit_posts` | `flxForms` | `first`, `after` | `FlxFormConnection!` | Blok seçici listesi. Editör REST'i kalır |
| GET | `/efb/v1/preview/{id}` | `class-Emsfb-gutenberg-block.php:97` | `edit_posts` | **İSTİSNA** | | | `id` = `tracking` veya sayı. Editör önizlemesi |

### `flx-forms/v1` — FluxApp yanıtları

Hepsi `flxf_app_can`: `manage_options` veya `Emsfb` (`app.php:42`).

| Yöntem | Yol | Kayıt | → GraphQL | Girdi | Çıktı | Not |
|---|---|---|---|---|---|---|
| GET | `/flx-forms/v1/responses` | `app.php:52` | `flxFormResponses` | `formId`, `unread`, `search`, `first`, `after` | `FlxFormResponseConnection!` | Bugün `page` / `per_page` (en çok 100). GraphQL connection, en çok 50 |
| GET | `/flx-forms/v1/responses/{id}` | `app.php:53` | `flxFormResponse` | `id: ID!` | `FlxFormResponse` | `read_ = 2` (ödeme taslağı) 404 |
| POST | `/flx-forms/v1/responses/{id}/read` | `app.php:54` | `flxFormResponseRead` | `id: ID!` | `FlxFormResponse` | `0→1`, `3→4` |

### `flxf/v1` — blok editörü

| Yöntem | Yol | Kayıt | cap | → GraphQL | Not |
|---|---|---|---|---|---|
| GET | `/flxf/v1/block-forms` | `blocks.php:262` | `edit_posts` | `flxForms` | Liste. Editör REST'i kalır |
| GET | `/flxf/v1/block-forms/{id}` | `blocks.php:253` | `edit_posts` | `flxForm` | Yapı + `hash`. Ham JSON GraphQL'e konmaz; alan listesi |
| POST | `/flxf/v1/block-forms` | `blocks.php:271` | `Emsfb` veya `manage_options` | **İSTİSNA** | Blok editörü yazar, 409 `hash`. Fork/ikinci editör; dokunulmaz |

`flxf/v1` yönetici brifindeki üç ad alanında yok. Kodda var.

## AJAX

Nonce aksi yazılmadıkça `check_ajax_referer('wp_rest', 'nonce')`. Panel cap'i `user_permission_efb_admin_dashboard` (`functions.php:7472`): giriş + (`manage_options` veya `Emsfb`). nopriv kaydı yok; public iş REST'te.

| Action | Kayıt | cap / nonce | → GraphQL | Not |
|---|---|---|---|---|
| `get_messages_id_Emsfb` | `class-Emsfb-admin.php:36` | panel | `flxFormResponses` | Forma göre liste (`:1348`). `read_` süzgeci yok |
| `get_all_response_id_Emsfb` | `:37` | panel | `flxFormResponse` | Yazışma (`emsfb_rsp_`, `:1385`); açılınca `read_ 0→1` |
| `update_message_state_Emsfb` | `:39` | panel | `flxFormResponseRead` | Tekil okundu (`:1233`, `read_=1`) |
| `read_list_Emsfb` | `:54` | panel | `flxFormResponsesRead` | Toplu okundu (`:3280`) |
| `remove_message_id_Emsfb` | `:34` | panel | `flxFormResponseDelete` | Tekil sil (`:240`) |
| `remove_messages_Emsfb` | `:53` | panel | `flxFormResponsesDelete` | Toplu sil; `state=msg` (`:3230`). Yanıt satırları da silinir |
| `set_replyMessage_id_Emsfb` | `:40` | panel | `flxFormResponseReply` | Yönetici yanıtı (`set_replyMessage_id_Emsfb`, `:1453`) |
| `get_track_id_Emsfb` | `:42` | panel | `flxFormResponseByTrack` | Panel arama kutusu (`get_ajax_track_admin`, `:1767`) |
| `get_form_id_Emsfb` | `:35` | panel | `flxForm` | Editör formu böyle yükler. AJAX kalır; GraphQL okuma aynı satırı servisten okur |
| `flxf_dash_stats` | `class-flxf-dashboard.php:44` | `flxf_dash` nonce; `Emsfb` veya `manage_options` | `flxFormsDashboard` | `from`, `to`, `form` (`:369`) |
| `efb_dashboard_stats` | `class-Emsfb-dashboard-widget.php:16` | `efb_dashboard_stats` nonce; `manage_options` (`:150`) | `flxFormsWidgetStats` | WP pano widget'ı. Girdi `period`: `day` veya `week`. Ziyaret, gönderim, e-posta |
| `efb_dashboard_email_errors` | `:17` | aynı (`:257`) | `flxFormsEmailErrors` | Widget hata listesi. Girdi `period`: `day` veya `week` |
| `remove_id_Emsfb` | `class-Emsfb-admin.php:33` | panel | **İSTİSNA** | Form sil. Fork editör |
| `update_form_Emsfb` | `:38` | panel | **İSTİSNA** | Form kaydet. Fork editör |
| `add_form_Emsfb` | `class-Emsfb-create.php:32` | `wp_rest` + `Emsfb` (`:378`) | **İSTİSNA** | Yeni form. Menü cap'i `Emsfb_create`, handler `Emsfb` ister. Fork editör |
| `dup_efb` | `class-Emsfb-admin.php:51` | panel | **İSTİSNA** | Form kopyala. Fork editör |
| `form_preview_efb` | `class-Emsfb-public.php:108` | `wp_rest` + `Emsfb` | **İSTİSNA** | Taslak sayfa yazar (`:7193`). Fork editör |
| `set_settings_Emsfb` | `class-Emsfb-admin.php:41` | panel | **İSTİSNA** (bu dalga) | Ayarlar paneli. Karar 4. Sırlar gizli anahtar taşır |
| `clear_garbeg_Emsfb` | `:43` | panel | **İSTİSNA** | Dosya temizliği. Fork panel |
| `check_email_server_efb` | `:44` | panel | **İSTİSNA** | Canlı SMTP denemesi |
| `efb_save_onboarding_email` | `:45` | panel | **İSTİSNA** | Kurulum. Fork |
| `efb_complete_onboarding` | `:46` | panel | **İSTİSNA** | Kurulum. Fork |
| `add_addons_Emsfb` | `:47` | panel | **İSTİSNA** | Eklenti. Fork |
| `remove_addons_Emsfb` | `:48` | panel | **İSTİSNA** | Eklenti. Fork |
| `update_file_Emsfb` | `:49` | panel | **İSTİSNA** | Yönetici dosya yükleme (multipart) |
| `send_sms_pnl_efb` | `:50` | panel (`:3159`) | **İSTİSNA** | `vendor/smssended/smsefb.php` yoksa reddeder. `Emsfb_sms_efb` yalnız rol kurulumu (`:91`) |
| `heartbeat_Emsfb` | `:55` | panel | **İSTİSNA** | Yeni `wp_rest` nonce (`:3338`) |
| `report_problem_Emsfb` | `:56` | panel | **İSTİSNA** | Fork "sorun bildir" |
| `efb_save_plan_selection` | `:57` | panel | **İSTİSNA** | Lisans planı. Fork |
| `emsfb_dismiss_delivery_notice` | `class-Emsfb-email-monitor.php:36` | panel | **İSTİSNA** | Teslim uyarısını kapat |
| `emsfb_recover_addons` | `class-Emsfb.php:61` | panel | **İSTİSNA** | Kayıp eklenti dosyasını kurtar |
| `flxf_auto_save` | `class-flxf-automations-admin.php:21` | `flxf_automations` nonce; `manage_options` (filtre `flxf_automations_capability`) | **İSTİSNA** | Otomasyon. Bu dalga dışı |
| `flxf_auto_toggle` | `:21` | aynı | **İSTİSNA** | |
| `flxf_auto_delete` | `:21` | aynı | **İSTİSNA** | |
| `flxf_auto_duplicate` | `:21` | aynı | **İSTİSNA** | |
| `flxf_auto_default` | `:21` | aynı | **İSTİSNA** | |
| `flxf_auto_donation_form` | `:21` | aynı | **İSTİSNA** | Fidan bağış şablonu. Fork oluşturucuya şablon basar |
| `flxf_auto_clear_runs` | `:21` | aynı | **İSTİSNA** | Çalışma günlüğü |

## admin-post

| Action | Kayıt | cap | → GraphQL | Not |
|---|---|---|---|---|
| `flxf_block_edit` | `blocks.php:51` | `Emsfb` + `edit_pages` + nonce | **İSTİSNA** | "Edit with blocks": mevcut yazıya veya yeni taslağa yönlendirir (`:96`) |
| `flxf_dash_csv` | `class-flxf-dashboard.php:45` | `flxf_dash_csv` nonce; pano cap | **İSTİSNA** | CSV baytı. Ekran sorgu sonucundan üretir |
| `emsfb_stripe_save` | `vendor/stripe/class-Emsfb-stripe-payment.php:58` | `manage_options` + nonce | **İSTİSNA** | Webhook imza sırrı. GraphQL'e girmez |
| `flx_system_checks_dismiss` | `flx-system-checks.php:378` | `manage_options` | **İSTİSNA** | Ortak sistem kontrolü. Yalnız Flux Donate yokken bu dosya boot eder (`class-flxf-system-checks.php:45`) |

## Admin sayfaları

Menü kökü `Emsfb`, cap `Emsfb`, konum 27 (`class-Emsfb-admin.php:171`). Bildirim sayısı okunmamış yanıt.

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `Emsfb` Panel (`:180`, gövde `class-Emsfb-panel.php`) | Üst menü: Formlar, Ayarlar, Oluştur (ayrı sayfa), Yardım. Arama onay kodu (`data-eventform=searchCC`, `:192`) | `flxForms`, `flxFormResponses`, `flxFormResponse`, `flxFormResponseByTrack` | `forms` (liste + yanıtlar). Ayarlar ve yardım bu dalgada klasik |
| `Emsfb` yanıt alt görünümü | Form satırından (`list_form-efb.js:266`). Sütunlar: seç, içerik, onay kodu, tarih, işlem. Toplu: sil (`deleteSelectedRow`), okundu (`readSelectedRow`) (`:578`). Tür düğmeleri: CSV (`subscribe`, `register`, `survey`, `form`, `payment`), anket grafiği (`:553`). Tekil: aç, sil. `read_`: 0 ve 3 okunmamış, 1 ve 4 okunmuş, 2 ödeme taslağı. Ayrı durum süzgeci yok | yukarı + `flxFormResponseRead`, `flxFormResponsesRead`, `flxFormResponseDelete`, `flxFormResponsesDelete`, `flxFormResponseReply` | Aynı `forms` ekranının yanıt görünümü. CSV/grafik istemcide, yüklü satırlardan (bugün JS) |
| `Emsfb` ayarlar | `?state=setting&tab=`. Sekmeler (`list_form-efb.js:1806`): `general`, `responses`, `captchas`, `email`, `email-template`, `localization`, `payments`, `sms`. Takma adlar `:1808` | bu dalgada yok | klasik. Karar 4 |
| `Emsfb_create` | Sürükle-bırak oluşturucu | — | **İSTİSNA** fork editör (`class-Emsfb-create.php:36`, cap `Emsfb_create`) |
| `Emsfb_addon` | Eklentiler | — | **İSTİSNA** (`class-Emsfb-addon.php:28`, cap `Emsfb_addon`) |
| `flxf-dashboard` | Formlar › Pano. Aralık 7/30/90/özel (en çok 731 gün), form süzgeci, CSV, kartlar, günlük seri, forma göre, ödeme durumu, ısı haritası, son 10, üst formlar | `flxFormsDashboard` | `forms/dashboard` |
| WP pano widget `efb_stats_dashboard_widget` | Gün/hafta: ziyaret, gönderim, e-posta oldu/olmadı, hata listesi (`class-Emsfb-dashboard-widget.php:85`) | `flxFormsWidgetStats`, `flxFormsEmailErrors` | Widget PHP kalır; veri aynı servis. cap `manage_options` |
| `flxf_automations` | Akışlar, `?flow=` editör, `?tab=runs` günlüğü (`class-flxf-automations-admin.php:231`) | — | **İSTİSNA** bu dalga. cap `manage_options` |
| `flxf_payments` | Sağlayıcı kartları, webhook URL, son 50 ödeme. Eski `Emsfb_stripe` buraya döner (`admin-page.php:36`) | — | **İSTİSNA** ödeme. cap `manage_options` |
| `flxf_system` | Sistem kontrolü. Flux Donate veya Flux Speed varsa menü açılmaz, oraya gider (`class-flxf-system-checks.php:37`) | — | **İSTİSNA** ortak ekran. Satırlar `flx_system_checks` |
| `Emsfb_stripe` | Stripe sayfası kaydı (`stripe-payment.php:224`, öncelik 30). `FLXF_Payments_Admin` menüyü siler (`admin-page.php:44`, öncelik 31) | — | **İSTİSNA**. Canlı sayfa `flxf_payments` |

Panel form listesi satır işlemleri (`list_form-efb.js:266`): yanıtlar (GraphQL), düzenle / kopyala / sil (fork), "Edit with blocks" (`blocks.php:61`, istisna yönlendirme).

FluxApp manifesti (`app.php:232`): sekmeler `forms` ve `responses`, kaynaklar bugün `efb/v1/forms` ve `flx-forms/v1/responses`. Uygulama REST'i bu geçişte değişmez; GraphQL aynı servisi kullanır.

## MCP / AI araçları

Yok. `flx_mcp_tools` ve `flxseo_mcp_tools` kaydı yok. `mcp` geçen yerler çeviri anahtarı `mcplen` (sayı uzunluğu).

## Ayarlar (`flx_settings_schema`)

Şema kaydı yok. Tek JSON satır `{prefix}emsfb_setting` (`functions.php:7529`). Stripe ayrıca `emsfb_settings` seçeneğine bakar (`stripe-handler.php:114`). `set_setting_Emsfb` (`:7480`) gönderilmeyen anahtarı silmez.

GraphQL bu dalgada ayar taşımaz (karar 4). Taşınırsa kök `flxFormsSettings` / `flxFormsSettingsUpdate`, cap `flx_gql_any_cap( 'Emsfb', 'manage_options' )`. Gizli alanlar yanıtta olmaz (KURALLAR §6): `secretKey`, `stripeSKey`, `paypalSKey`, `payToken`, `apikey_map` (harita), `activeCode` (lisans). Yazma: boş gönderilirse eskisi kalır.

| Grup (`tab`) | Alan (ayar JSON anahtarı) | Tip | GraphQL'de | Not |
|---|---|---|---|---|
| general | `bootstrap`, `osLocationPicker`, `devMode` | Boolean | öneri, karar 4 | `list_form-efb.js:1368`, `:1389`, `:1401` |
| general | `activeCode` | String | **yok** | Lisans. `:1344` |
| responses | `sessionDuration`, `trackCodeStyle` | String | öneri | `:1446`, `:1468` |
| responses | `scaptcha`, `showUpfile`, `activeDlBtn`, `showIp`, `adminSN` | Boolean | öneri | Yanıt kutusu. `:1484`–`:1510` |
| responses | `respPrimary`, `respPrimaryDark`, `respAccent`, `respText`, `respTextMuted`, `respBgCard`, `respBgMeta`, `respBgTrack`, `respBgResp`, `respBgEditor`, `respEditorText`, `respEditorPh`, `respBtnText`, `respBrandColor`, `respFontFamily`, `respFontSize`, `respCustomFont`, `respPreset` | String | öneri | `:1528`–`:1545` |
| captchas | `siteKey` | String | öneri (herkese açık anahtar) | `:1564`. Asıl doğrulama karar 1 |
| captchas | `secretKey` | String | **yok** | `:1567` |
| captchas | `shield_silent_captcha` | Boolean | öneri | Shield eklentisi (`class-Emsfb-shield-silentcaptcha.php:8`) |
| captchas | `apikey_map` | String | **yok** | `:1591` |
| email | `email`, `femail` | String | öneri | Yönetici ve gönderen (`:1607`, `:1613`) |
| email | `smtp` / `hostSupportSmtp`, `weeklyEmailReport`, `emailStatsReport` | Boolean | öneri | `:1627`, `:1638`, `:1649`. Sunucu denemesi AJAX istisna |
| email-template | `emailTemp`, `emailBtnBgColor`, `emailBtnTextColor` | String | öneri | `:1727` |
| localization | `act_local` + metin sözlüğü | Boolean + JSON | **İSTİSNA** | Fork sözlüğü (`:1277`, `:1672`). i18n paketin `.l10n.php` dosyalarında |
| payments | `stripePKey`, `paypalPKey` | String | öneri (publishable) | `:1695`, `:1709`. Karar 4 ile bile ödeme başlatma REST'te |
| payments | `stripeSKey`, `paypalSKey`, `payToken` | String | **yok** | `:1698`, `:1712`, `:1267` |
| sms | `sms_config_select` = `wp_sms_plugin` | String | öneri | `:1749`. Sağlayıcı sırrı Flux Mail'de kalır; bu JSON'a anahtar konmaz |

Başka seçenekler (GraphQL ayarı değil): `emsfb_pro`, `emsfb_addons_renew_required`, `emsfb_addons_dl_failures`, `emsfb_cache_plugins`, Stripe webhook sırrı.

## Shortcode, blok, public akış

Shortcode ve blok HTML basar. GraphQL onların yerine geçmez. Tanım okuma `flxForm` / `flxFormPublic`.

| Kayıt | Yer | → GraphQL | Not |
|---|---|---|---|
| `[EMS_Form_Builder]`, `[ems_form_builder]`, `[Easy_Form_Builder_confirmation_code_finder]` | `class-Emsfb-public.php:89` | **İSTİSNA** (HTML) | Üçü de `render_form_shortcode_efb` (`:576`) → `EFB_Form_Builder` (`:811`). Onay kodu shortcode'u arama formu |
| `integrate-wpb.php:4` | yorum | — | `add_shortcode` kapalı yorumun içinde. Canlı kayıt değil |
| `[efb_vc_form]` | `class-Emsfb-visual-composer.php:27` | **İSTİSNA** (HTML) | Eski görsel oluşturucu |
| `[efb_wpbakery_form]` | `class-Emsfb-wpbakery.php:90` | **İSTİSNA** (HTML) | WPBakery |
| `[flx_credit]` | `flx-brand/core.php:134` | **İSTİSNA** (HTML) | "FluxWP ile" satırı. Form değil |
| `easy-form-builder/form` | `block.json:3`, kayıt `class-Emsfb-gutenberg-block.php:36` | **İSTİSNA** (HTML) | `inserter: false` |
| `flxf/form` | `blocks.php:205` | **İSTİSNA** (HTML) | `render_form`. Ön yüz fork çizicisini kullanır (`blocks.php:9`) |
| `flxf/{alan}` × 47 | `blocks.php:229`, liste `:30` | **İSTİSNA** | Yalnız editör. `render_callback` boş. Alanlar: text, email, tel, mobile, textarea, number, password, url, date, range, color, select, radio, checkbox, multiselect, image-choice, checklist, choice-quantity, yes-no, switch, terms, country, state, city, file, file-drop, rating, score-5, nps, matrix, signature, location, audio, video, screen, pay-checkbox, pay-radio, pay-select, pay-multiselect, amount, total, stripe, heading, link, html, step, raw |
| `flx/credit` | `flx-brand/core.php:149` | **İSTİSNA** (HTML) | Form değil |
| Elementor `emsfb_form` | `class-Emsfb-elementor-widget.php:20` | **İSTİSNA** (HTML) | Aynı çizici |

Public akış (bugün):

1. Shortcode/blok HTML + betik (`core-efb.js`). Gönderim `POST …/forms/message/add`.
2. Dosya alanı önce `POST …/forms/file/upload`, sonra gönderim URL'yi yazar.
3. Ödeme: `quote` / `stripe/card/add` veya `payment/start`, tarayıcı onayı, `confirm` veya webhook, en son `message/add` (Stripe `guard_final_submit`, `stripe-handler.php:17`).
4. Onay kodu: shortcode veya `POST …/forms/response/get`. Yanıt: `POST …/forms/response/add`.
5. Captcha bugün formun kendi `captcha` bayrağı + ayarlardaki Google reCAPTCHA v2 (`siteverify`, `class-Emsfb-public.php:3243`). İsteğe bağlı Shield Silent Captcha. `flx_captcha_*` çağrısı yok.
6. Honeypot alanı yok (yalnız tanıtım metni). Gönderimde `flx_rate_limit` yok; sınır onay kodu aramasında ve ödemede.
7. `flx/form` ve `flx/advanced-form` (flx-blocks) gönderimi bu pakette değil. Geçici istisna `ISTISNALAR.md`; `flxFormSubmit` açılınca o AJAX'lar ayrı PR ile kalkar.

## Tipler

Ham `form_structer` GraphQL'e konmaz (bildirim e-postası, mantık, ödeme sırrı). Public tip yalnız çizilecek alanları taşır. `private` form public sorguda `null`.

| Tip | Alanlar (kısa) | Nullable ve nedeni | Loader |
|---|---|---|---|
| `FlxForm` | `id`, `name`, `type: FlxFormType!`, `fields`, `updatedAt` | `updatedAt` yoksa null | form id |
| `FlxFormPublic` | `id`, `name`, `type`, `fields` (etiket, tür, zorunlu, seçenekler) | form yoksa veya gizliyse kök `null` | form id |
| `FlxFormType` | `FORM`, `SURVEY`, `SUBSCRIBE`, `PAYMENT`, `REGISTER`, `LOGIN` | — | `blocks.php:42` |
| `FlxFormField` | `id`, `type`, `label`, `required`, `options` | `options` seçimsiz alanda null | yapıdan |
| `FlxFormResponse` | `id`, `form`, `trackCode`, `status: FlxFormResponseStatus!`, `createdAt`, `fields`, `replies`, `preview` | `form` silinmişse null | msg id |
| `FlxFormResponseStatus` | `UNREAD` (0), `READ` (1), `PENDING_PAYMENT` (2), `UNREAD_FOLLOWUP` (3), `READ_FOLLOWUP` (4) | — | `read_` |
| `FlxFormReply` | `id`, `body`, `authorName`, `createdAt` | yazar yoksa `authorName` null | rsp id |
| `FlxFormsDashboard` | aralık, kartlar (yanıt, okunmamış, görüntüleme, dönüşüm, ödeme), `series`, `byForm`, `payStatus`, `heat`, `recent`, `topForms` | görüntüleme tablosu yoksa ilgili kart null | — |
| `FlxFormsWidgetPeriod` | `DAY`, `WEEK` | — | widget `period` |
| `FlxFormsWidgetStats` | ziyaret, gönderim, gönderilen e-posta, başarısız e-posta | veri yoksa sayılar 0 | — |
| `FlxFormsEmailError` | `date`, `recipient`, `detail` | — | — |
| `FlxFormSubmitPayload` | `submission { id, trackCode }`, `errors: [FlxUserError!]!` | başarıda `submission`, hatada null | — |

Connection'lar `first` / `after`, üst sınır 50. `flx-forms/v1` bugün 100 ister; REST öyle kalır, GraphQL 50.

Kökler:

| Alan | cap | Not |
|---|---|---|
| `flxForm(id: ID!): FlxFormPublic` | `public` | Çizim alanları. Gerekçe: shortcode zaten herkese aynı alanları basar |
| `flxForms: FlxFormConnection!` | `flx_gql_any_cap( 'Emsfb', 'manage_options' )` | Yönetici listesi |
| `flxFormSubmit(input: FlxFormSubmitInput!): FlxFormSubmitPayload!` | `public` | `form` / `survey` / `subscribe`. `captchaToken`, honeypot, oran sınırı |
| `flxFormTrack(code: String!): FlxFormTrackResult` | `public` | Kod yoksa `NOT_FOUND`. Aynı IP sınırı |
| `flxFormTrackReply(input: FlxFormTrackReplyInput!): FlxFormSubmitPayload!` | `public` | `captchaToken` |
| `flxFormResponses(...): FlxFormResponseConnection!` | `Emsfb` veya `manage_options` | `formId`, `unread`, `search` |
| `flxFormResponse(id: ID!): FlxFormResponse` | aynı | Yoksa `NOT_FOUND` |
| `flxFormResponseByTrack(code: String!): FlxFormResponse` | aynı | |
| `flxFormResponseRead(id: ID!)` / `flxFormResponsesRead(ids: [ID!]!)` | aynı | `0→1`, `3→4` |
| `flxFormResponseDelete(id: ID!)` / `flxFormResponsesDelete(ids: [ID!]!)` | aynı | |
| `flxFormResponseReply(input: FlxFormReplyInput!)` | aynı | |
| `flxFormsDashboard(from: String!, to: String!, formId: ID): FlxFormsDashboard!` | aynı | Aralık en çok 731 gün |
| `flxFormsWidgetStats(period: FlxFormsWidgetPeriod!): FlxFormsWidgetStats!` | `manage_options` | Widget: ziyaret, gönderim, e-posta. `period` `DAY` veya `WEEK` |
| `flxFormsEmailErrors(period: FlxFormsWidgetPeriod!): FlxFormsEmailErrorConnection!` | `manage_options` | Widget hata listesi |

`FlxFormSubmitInput`: `formId: ID!`, `fields: [FlxFormFieldValueInput!]!`, `captchaToken: String`, `honeypot: String`, `pageId: ID`. Honeypot doluysa `INVALID_INPUT` (alan `honeypot`), kayıt yazılmaz.

Hatalar: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `INVALID_INPUT`, `CONFLICT`, `RATE_LIMITED`, `INTERNAL`. Yazmalarda `INVALID_INPUT` ve `CONFLICT` payload `errors` içine.

## Yetkiler (`flx_gql_capabilities`)

Yeni cap açılmaz. Katalogya mevcut cap'ler yazılır ki `useFlxCan` çalışsın:

| Cap | Kimde | Ne için |
|---|---|---|
| `Emsfb` | yönetici (`class-Emsfb-admin.php:72`) | Panel, yanıt, pano |
| `Emsfb_create` | yönetici | Yalnız klasik oluşturucu (GraphQL kökü yok) |
| `Emsfb_addon` | yönetici | Yalnız klasik eklenti sayfası |
| `manage_options` | yönetici | E-posta hataları, ödeme sayfası, otomasyon, sistem kontrolü |

`edit_posts` blok seçicisinin REST'inde kalır; GraphQL listesi `Emsfb` ister (seçici REST'i durur).

## Servisler (sonraki PR, bu turda yazılmayacak)

| Sınıf | Ne taşır | Kim çağırır |
|---|---|---|
| `FLXF_Form_Service` | `get`, `list`. Ham JSON'u alan listesine çevirir | `flxForm`, `flxForms`, Gutenberg/blok GET (REST gövdesi aynı kalır) |
| `FLXF_Submission_Service` | `submit` (form/survey/subscribe), honeypot, `flx_captcha_gql_verify`, `flx_rate_limit` | `flxFormSubmit`. REST `get_form_public_efb` baytları değişmeden servise delege edilir; login/ödeme dalları REST'te kalır |
| `FLXF_Response_Service` | liste, tekil, yazışma, okundu, sil, yanıt, onay kodu | Panel AJAX + `flx-forms/v1` + GraphQL |
| `FLXF_Dashboard_Service` | `FLXF_Dashboard::stats` ve widget'ın gün/hafta hesabı | `flxf_dash_stats`, `efb_dashboard_stats`, `efb_dashboard_email_errors`, `flxFormsDashboard`, `flxFormsWidgetStats` |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-forms:form:{id}` | `flxForm` | form kaydı (REST editör, servis çağrılırsa) |
| `flx-forms:list` | `flxForms` | form kaydı / silme |
| `flx-forms:responses` | `flxFormResponses` | `flxFormSubmit`, sil, okundu, yanıt |
| `flx-forms:response:{id}` | `flxFormResponse` | aynı |
| `flx-forms:dashboard` | `flxFormsDashboard` | gönderim ve silme |

Yanıt ve pano girişli istek; önbelleğe alınmaz. `flxForm` (public alan listesi) persisted GET ile önbelleğe girer. Ödeme ve dosya uçları `no-store` kalır.

## PR bölünmesi (uygulama, bu tur değil)

1. Bu envanter.
2. Public okuma + `flxFormSubmit` + `flxFormTrack` + `flxFormTrackReply`. Servis çıkarımı, REST baytları aynı. Ekran yok.
3. Yanıtlar ekranı (`packages/modules/src/forms`): form listesi, yanıt listesi, yazışma, toplu okundu/sil, yanıt, onay kodu arama. `page=Emsfb` varsayılan React; klasik bağlantı. Oluşturucu linki klasik sayfada kalır.
4. Pano (`flxFormsDashboard`) ve `page=flxf-dashboard`. Widget PHP kalır; `flxFormsWidgetStats` ve `flxFormsEmailErrors` aynı servisi okur.

Ayarlar ve otomasyonlar karar 4'e bağlı. Tek PR'a sığmaz: `get_form_public_efb` tek başına ~1600 satır (`:2233`–`:3854`).

## Açık sorular

1. **Captcha.** A) GraphQL yalnız `flx_captcha_gql_verify( 'flxf_submit', $token )`; fork `siteverify` REST'te kalır. B) GraphQL fork'un `secretKey`'i ile Google v2 doğrular. C) Fork anahtarları kapatılır, formlar Flux reCAPTCHA'ya taşınır. Öneri: **A**. REST baytları değişmez; yeni mutation ortak servisi kullanır. Formda `captcha` açıksa token zorunlu, kapalıysa doğrulama yok.
2. **`flxFormSubmit` hangi türleri alır?** A) `form`, `survey`, `subscribe`. B) ayrıca `login` / `register` / `logout` / parola. C) ayrıca ödemenin son `message/add` adımı. Öneri: **A**. Kullanıcı açmak ve ödeme onayı ayrı risk; REST o türleri bugünkü gibi işler.
3. **Public dosya.** A) Yükleme REST istisna kalır; mutation yalnız o ucun yazdığı URL'yi kabul eder. B) Ortak `POST /flx/v1/upload` için public bağlam (kit PR'ı) ve mutation `uploadId` alır. C) İlk mutation dosya alanı taşımaz. Öneri: **A** bu pakette, headless dosya gerekirse **B** ayrı kit işi. Kit bu PR'da değişmez.
4. **Ayarlar ve otomasyonlar.** A) İkisi de sonraki PR (ayarlarda sırlar write-only). B) Ayarlar pano PR'ına girer; otomasyonlar klasik kalır. C) İkisi de klasik kalır. Öneri: **B**. Ayarlar `page=Emsfb` üzerindedir; otomasyon ayrı Flux sayfası ve sahip listesinde yok.

Teknik karar (soru değil): public `flxForm` ham yapıyı dönmez. Bildirim e-postası ve anahtar sızmaz. Honeypot ve gönderim oran sınırı kodda yok; mutation'a eklenir, REST'e yeni davranış eklenmez.
