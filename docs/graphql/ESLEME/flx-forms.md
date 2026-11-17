# Flux Forms (flx-forms): REST → GraphQL eşlemesi

Paket sürümü: 2.4.0 · Önek: `flxf_` / fork `Emsfb` · Dal: `gql/flx-forms-1` · Durum: taslak (envanter)

Bu dosya yalnız envanterdir. Satırlar koddan okundu (`register_rest_route`, `wp_ajax_`, `add_menu_page` / `add_submenu_page`, `add_shortcode`, `register_block_type`, `flx_mcp_tools`, `flx_settings_schema`). `vendor/stripe` graft indeksinde yoktu; orası ayrıca tarandı. Gizli anahtar değeri yok.

Sahip kararı (bağlayıcı): captcha = `flx_captcha_gql_verify( 'flxf_submit' )` (bağlam `flx_captcha_contexts`; fork `siteverify` REST'te kalır). `flxFormSubmit` yalnız `form` / `survey` / `subscribe`. Yükleme REST istisna kalır; GraphQL yalnız o ucun bu form (veya bilet) için ayırdığı dosyayı kabul eder. Yönetici sayfalarının hepsi React ekranı olur ve FluxApp masaüstünde de çalışır: Panel (yanıtlar + Ayarlar sekmesi), Pano, Ödemeler, Sistem kontrolü, Otomasyonlar, Eklentiler. Yalnız Oluştur (fork form oluşturucu) klasik kalır. İstisna yalnız oluşturucu içi, ödeme callback/webhook, ikili yükleme/indirme ve eski oturum/nonce uçlarıdır. Sırlar write-only (`plugins/flx-ads` gibi). `docs/graphql/ISTISNALAR.md` içindeki geçici "Blok formu gönderimi" satırı bu paketin dosyası değil; silinmesi ayrı küçük PR.

## Özet

| Kategori | Satır | GraphQL | İstisna | Not |
|---|---|---|---|---|
| REST kaydı | 24 çağrı / 25 yöntem | 9 | 16 | `register_rest_route` 24 kez. `blocks.php:262` tek çağrıda GET ve POST kaydeder, yöntem 25. Ad alanları: `Emsfb/v1` 17 çağrı (çekirdek 8 + ödeme 3 + Stripe 5 + webhook.php 1), `efb/v1` 2, `flx-forms/v1` 3, `flxf/v1` 2 çağrı / 3 yöntem. Brifteki "25 çağrı, 26 yöntem" bir fazla |
| AJAX | 38 | 31 | 7 | Hepsi `wp_ajax_` (oturumlu). `wp_ajax_nopriv_` yok. İstisna: oluşturucu (sil/kaydet/ekle/kopyala/önizle), `update_file_Emsfb` (ikili, nonce only), `heartbeat_Emsfb` (nonce) |
| admin-post | 4 | 1 | 3 | GraphQL: sistem kontrolü uyarı kapatma. İstisna: CSV baytı, blok editörüne yönlendirme, Stripe webhook sırrı |
| Admin sayfası | 1 menü + 8 alt menü | 6 ekran | 1 sayfa | React + FluxApp: Panel, Pano, Ödemeler, Sistem kontrolü, Otomasyonlar, Eklentiler. İstisna: Oluştur. Stripe alt menüsü kaydolup silinir. WP pano widget'ı PHP kalır, verisi GraphQL |
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
| POST | `/Emsfb/v1/forms/response/get` | `class-Emsfb-public.php:54` | nonce | `flxFormTrack` | `code: String!` | `FlxFormTrack` | Onay koduyla yanıt okuma. IP başına transient sınır (`efb_track_lookup_allowed`, `:3866`). API captcha doğrulamaz: `$captcha_success = []` ve `$not_captcha = true` sabit (`:4044`). `scaptcha` yalnız takip sayfasında widget basar (`EMS_Form_Builder_track`, `:1925`). GraphQL argümanına captcha token konmaz |
| POST | `/Emsfb/v1/forms/response/add` | `class-Emsfb-public.php:59` | nonce | `flxFormTrackReply` | `message`, `trackCode`, `attachments`, `adminCode` (`sc`) | `FlxFormSubmitPayload` | Ziyaretçi yanıt kutusu (`set_rMessage_id_Emsfb_api`, `:4995`). Ekler `response_attachments_are_reserved_efb` (`:4014`, çağrı `:5179`). `sc` = `md5(track + email_key)` (`:5148`); yanlış kod yükseltmez |
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
| GET | `/efb/v1/forms` | `class-Emsfb-gutenberg-block.php:91` | `edit_posts` | `flxFormPicker` | — | `FlxFormPickerItem` (`id`, `title`) | Blok seçici. `flxForms` (`Emsfb`) yazar/editörü daraltır. `flx_gql_any_cap( 'edit_posts', 'Emsfb', 'manage_options' )`. Yalnız id + başlık |
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
| GET | `/flxf/v1/block-forms` | `blocks.php:262` | `edit_posts` (`can_read`, `:283`) | `flxFormPicker` | Aynı hafif seçici. POST ile aynı `register_rest_route` çağrısı |
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
| `flxf_dash_stats` | `class-flxf-dashboard.php:44` | `flxf_dash` nonce; `FLXF_Dashboard::can()` (`:49`): giriş + (`manage_options` veya `Emsfb`). `flxf_dashboard_capability` filtresi yok | `flxFormsDashboard` | `from`, `to`, `form` (`:369`). Menü cap dizesi `Emsfb` (`:62`) |
| `efb_dashboard_stats` | `class-Emsfb-dashboard-widget.php:16` | `efb_dashboard_stats` nonce; `manage_options` (`:150`) | `flxFormsWidgetStats` | WP pano widget'ı. Girdi `period`: `day` veya `week`. Ziyaret, gönderim, e-posta |
| `efb_dashboard_email_errors` | `:17` | aynı (`:257`) | `flxFormsEmailErrors` | Widget hata listesi. Girdi `period`: `day` veya `week` |
| `remove_id_Emsfb` | `class-Emsfb-admin.php:33` | panel | **İSTİSNA** | Form sil. Fork editör |
| `update_form_Emsfb` | `:38` | panel | **İSTİSNA** | Form kaydet. Fork editör |
| `add_form_Emsfb` | `class-Emsfb-create.php:32` | `wp_rest` + `Emsfb` (`:378`) | **İSTİSNA** | Yeni form. Menü cap'i `Emsfb_create`, handler `Emsfb` ister. Fork editör |
| `dup_efb` | `class-Emsfb-admin.php:51` | panel | **İSTİSNA** | Form kopyala. Fork editör |
| `form_preview_efb` | `class-Emsfb-public.php:108` | `wp_rest` + `Emsfb` | **İSTİSNA** | Taslak sayfa yazar (`:7193`). Fork editör |
| `set_settings_Emsfb` | `class-Emsfb-admin.php:41` | panel | `flxFormsSettingsUpdate` | Ayarlar sekmesi (dilim 2). Sırlar write-only; okuma `flxFormsSettings` değeri dönmez |
| `clear_garbeg_Emsfb` | `:43` | panel | `flxFormsStorageClear` | Yanıt sayfası araç çubuğu. Yönetici dilimi (bu dalda yazılmaz) |
| `check_email_server_efb` | `:44` | panel | `flxFormsEmailServerTest` | Canlı SMTP denemesi. Mutation (sorgu değil) |
| `efb_save_onboarding_email` | `:45` | panel | `flxFormsOnboardingEmail` | Panel ilk kurulum |
| `efb_complete_onboarding` | `:46` | panel | `flxFormsOnboardingComplete` | Panel ilk kurulum |
| `add_addons_Emsfb` | `:47` | panel | `flxFormsAddonInstall` | Eklentiler ekranı (dilim 6). cap `Emsfb_addon` veya `Emsfb` |
| `remove_addons_Emsfb` | `:48` | panel | `flxFormsAddonRemove` | Aynı |
| `update_file_Emsfb` | `:49` | **nonce only, no cap** | **İSTİSNA** | `file_upload_public` (`:3058`). `check_ajax_referer('public-nonce','nonce')` veya `check_ajax_referer($vl,'nonce_msg')` (`:3083`). `current_user_can` yok. Yorum: eski, gönderilen JS çağırmaz. Multipart. `track_pending_upload` (`:3143`) |
| `send_sms_pnl_efb` | `:50` | panel (`:3159`) | `flxFormsSmsSend` | `vendor/smssended/smsefb.php` yoksa reddeder. `Emsfb_sms_efb` yalnız rol kurulumu (`:91`), handler bakmaz |
| `heartbeat_Emsfb` | `:55` | panel | **İSTİSNA** | Yeni `wp_rest` nonce (`:3338`) |
| `report_problem_Emsfb` | `:56` | panel | `flxFormsProblemReport` | Panel "sorun bildir" |
| `efb_save_plan_selection` | `:57` | panel | `flxFormsPlanSave` | Lisans. `activeCode` write-only |
| `emsfb_dismiss_delivery_notice` | `class-Emsfb-email-monitor.php:36` | nonce `emsfb_dismiss_delivery_notice` + yalnız `manage_options` (`:440`). Panel `Emsfb` yetmez | `flxFormsDeliveryNoticeDismiss` | Yanıt sayfası araç çubuğu. Dilim 2 |
| `emsfb_recover_addons` | `class-Emsfb.php:61` | panel | `flxFormsAddonRecover` | Kayıp eklenti dosyası (dilim 6) |
| `flxf_auto_save` | `class-flxf-automations-admin.php:21` | `flxf_automations` nonce; cap filtrelenir: `apply_filters( 'flxf_automations_capability', 'manage_options' )` (`class-flxf-automations.php:63`) | `flxFormsAutomationSave` | Dilim 5. Aynı cap |
| `flxf_auto_toggle` | `:21` | aynı | `flxFormsAutomationToggle` | |
| `flxf_auto_delete` | `:21` | aynı | `flxFormsAutomationDelete` | |
| `flxf_auto_duplicate` | `:21` | aynı | `flxFormsAutomationDuplicate` | |
| `flxf_auto_default` | `:21` | aynı | `flxFormsAutomationDefault` | |
| `flxf_auto_donation_form` | `:21` | aynı | `flxFormsAutomationDonationForm` | Şablon form basar. Oluşturucu kendisi klasik kalır |
| `flxf_auto_clear_runs` | `:21` | aynı | `flxFormsAutomationClearRuns` | Çalışma günlüğü |

## admin-post

| Action | Kayıt | cap | → GraphQL | Not |
|---|---|---|---|---|
| `flxf_block_edit` | `blocks.php:51` | `Emsfb` + `edit_pages` + nonce | **İSTİSNA** | "Edit with blocks": mevcut yazıya veya yeni taslağa yönlendirir (`:96`) |
| `flxf_dash_csv` | `class-flxf-dashboard.php:45` | `flxf_dash_csv` nonce; pano cap | **İSTİSNA** | CSV baytı. Ekran sorgu sonucundan üretir |
| `emsfb_stripe_save` | `vendor/stripe/class-Emsfb-stripe-payment.php:58` | `manage_options` + nonce | **İSTİSNA** | Webhook imza sırrı. GraphQL'e girmez |
| `flx_system_checks_dismiss` | `flx-system-checks.php:378` | `manage_options` | `flxFormsSystemNoticeDismiss` | Sistem kontrolü ekranı (dilim 3). Yalnız Flux Donate yokken bu dosya boot eder (`class-flxf-system-checks.php:45`) |

## Admin sayfaları

Menü kökü `Emsfb`, cap `Emsfb`, konum 27 (`class-Emsfb-admin.php:171`). Bildirim sayısı okunmamış yanıt.

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `Emsfb` Panel (`:180`, gövde `class-Emsfb-panel.php`) | Üst menü: Formlar, Ayarlar, Oluştur (ayrı sayfa), Yardım. Arama onay kodu (`data-eventform=searchCC`, `:192`) | `flxForms`, `flxFormResponses`, `flxFormResponse`, `flxFormResponseByTrack`, `flxFormsSettings` | Dilim 2. React, FluxApp'te de. Oluştur linki klasik sayfada kalır |
| `Emsfb` yanıt alt görünümü | Form satırından (`list_form-efb.js:266`). Sütunlar: seç, içerik, onay kodu, tarih, işlem. Toplu: sil (`deleteSelectedRow`), okundu (`readSelectedRow`) (`:578`). Tür düğmeleri: CSV (`subscribe`, `register`, `survey`, `form`, `payment`), anket grafiği (`:553`). Tekil: aç, sil. `read_`: 0 ve 3 okunmamış, 1 ve 4 okunmuş, 2 ödeme taslağı. Ayrı durum süzgeci yok | yukarı + `flxFormResponseRead`, `flxFormResponsesRead`, `flxFormResponseDelete`, `flxFormResponsesDelete`, `flxFormResponseReply`, `flxFormsStorageClear`, `flxFormsDeliveryNoticeDismiss` | Dilim 2. CSV/grafik istemcide, yüklü satırlardan |
| `Emsfb` ayarlar | `?state=setting&tab=`. Sekmeler (`list_form-efb.js:1806`): `general`, `responses`, `captchas`, `email`, `email-template`, `localization`, `payments`, `sms`. Takma adlar `:1808` | `flxFormsSettings`, `flxFormsSettingsUpdate`, `flxFormsEmailServerTest` | Dilim 2. Sırlar write-only. Sözlük (`text`) istisna kalır |
| `Emsfb_create` | Sürükle-bırak oluşturucu | — | **İSTİSNA** fork editör (`class-Emsfb-create.php:36`, cap `Emsfb_create`). Sahip aksini söyleyene kadar klasik |
| `Emsfb_addon` | Eklentiler | `flxFormsAddons`, `flxFormsAddonInstall`, `flxFormsAddonRemove`, `flxFormsAddonRecover` | Dilim 6. cap `Emsfb_addon` (`class-Emsfb-addon.php:28`) |
| `flxf-dashboard` | Formlar › Pano. Aralık 7/30/90/özel (en çok 731 gün), form süzgeci, CSV, kartlar, günlük seri, forma göre, ödeme durumu, ısı haritası, son 10, üst formlar | `flxFormsDashboard` | Dilim 3. CSV indirme admin-post istisna (bayt) |
| WP pano widget `efb_stats_dashboard_widget` | Gün/hafta: ziyaret, gönderim, e-posta oldu/olmadı, hata listesi (`class-Emsfb-dashboard-widget.php:85`) | `flxFormsWidgetStats`, `flxFormsEmailErrors` | Widget PHP kalır; veri aynı servis. cap `manage_options` |
| `flxf_automations` | Akışlar, `?flow=` editör, `?tab=runs` günlüğü (`class-flxf-automations-admin.php:231`) | `flxFormsAutomations`, `flxFormsAutomationSave` ve kardeş mutation'lar | Dilim 5. cap `flxf_automations_capability` |
| `flxf_payments` | Sağlayıcı kartları, webhook URL (gösterim), son 50 ödeme. Eski `Emsfb_stripe` buraya döner (`admin-page.php:36`) | `flxFormsPayments` | Dilim 4. cap `manage_options`. Başlatma, onay ve webhook istisna. Sır write-only |
| `flxf_system` | Sistem kontrolü. Flux Donate veya Flux Speed varsa menü açılmaz, oraya gider (`class-flxf-system-checks.php:37`) | `flxFormsSystemChecks`, `flxFormsSystemNoticeDismiss` | Dilim 3. Satırlar `flx_system_checks` |
| `Emsfb_stripe` | Stripe sayfası kaydı (`stripe-payment.php:224`, öncelik 30). `FLXF_Payments_Admin` menüyü siler (`admin-page.php:44`, öncelik 31) | — | **İSTİSNA** (yönlendirme + webhook sırrı kaydı). Canlı liste `flxf_payments` |

Panel form listesi satır işlemleri (`list_form-efb.js:266`): yanıtlar (GraphQL), düzenle / kopyala / sil (fork), "Edit with blocks" (`blocks.php:61`, istisna yönlendirme).

FluxApp manifesti (`app.php:232`): sekmeler `forms` ve `responses`, kaynaklar bugün `efb/v1/forms` ve `flx-forms/v1/responses`. Uygulama REST'i bu geçişte değişmez; GraphQL aynı servisi kullanır.

## MCP / AI araçları

Yok. `flx_mcp_tools` ve `flxseo_mcp_tools` kaydı yok. `mcp` geçen yerler çeviri anahtarı `mcplen` (sayı uzunluğu).

## Ayarlar (`flx_settings_schema`)

Şema kaydı yok. Okuma `Emsfb::get_setting_Emsfb` (`includes/class-Emsfb.php:894`); sarmalayıcı fonksiyon `flx-forms-load.php:372`. Yazma `set_setting_Emsfb` (`functions.php:7480`). Depo `{prefix}emsfb_setting` tek JSON satırı (`functions.php:7529`). Gönderilmeyen anahtar silinmez. Stripe ayrıca `emsfb_settings` seçeneğine bakar (`stripe-handler.php:114`). Paneldeki DOM id'leri (`apikey_map`, `email`, `showUpfile`, `sms_config_select`, `hostSupportSmtp`) JSON anahtarı değildir.

Kök `flxFormsSettings` / `flxFormsSettingsUpdate` (dilim 2), cap `flx_gql_any_cap( 'Emsfb', 'manage_options' )`. Sırlar yanıtta olmaz (KURALLAR §6); yazma write-only, boş gönderilirse eskisi kalır (`plugins/flx-ads` ile aynı). Bu dilim ayar yazmaz.

Varsayılanlar `Emsfb::get_default_settings_efb` (`class-Emsfb.php:1762`).

| Grup (`tab`) | JSON anahtarı | Tip | GraphQL'de | Not |
|---|---|---|---|---|
| general | `bootstrap`, `osLocationPicker` | String | öneri, karar 4 | `devMode` varsayılanda yok |
| general | `activeCode` | String | write-only | Lisans. Okumada yok |
| general | `efb_version` | String | okunur | Fork sürümü, sır değil |
| responses | `sessionDuration`, `trackCodeStyle` | String | öneri | |
| responses | `scaptcha`, `dsupfile`, `activeDlBtn`, `showIp`, `adminSN` | Boolean / String | öneri | DOM `showUpfile` → JSON `dsupfile` (varsayılan `true`) |
| responses | `respPrimary`, `respPrimaryDark`, `respAccent`, `respText`, `respTextMuted`, `respBgCard`, `respBgMeta`, `respBgTrack`, `respBgResp`, `respBgEditor`, `respEditorText`, `respEditorPh`, `respBtnText`, `respBrandColor`, `respFontFamily`, `respFontSize`, `respCustomFont`, `respPreset` | String | öneri | `respPreset` public yanıt kutusuna konmaz (`:1833`) |
| captchas | `siteKey` | String | öneri (herkese açık) | Fork v2. GraphQL doğrulaması `flx_captcha_gql_verify` |
| captchas | `secretKey` | String | write-only | Okumada yok |
| captchas | `shield_silent_captcha` | String | öneri | |
| captchas | `apiKeyMap` | String | write-only | Public payload'da `mapKey` adıyla gider (`class-Emsfb.php:1021`). GraphQL okuması dönmez |
| email | `emailSupporter`, `femail` | String | öneri | DOM `email` → `emailSupporter` |
| email | `email_key` | String | write-only | `md5(track + email_key)` yönetici kodu (`class-Emsfb-public.php:5149`). Okumada yok |
| email | `smtp` | Boolean | öneri | `wp_mail` var mı. Parola değil. DOM `hostSupportSmtp` |
| email | `weeklyEmailReport`, `emailStatsReport` | Boolean | öneri | |
| email-template | `emailTemp`, `emailBtnBgColor`, `emailBtnTextColor` | String | öneri | Şablon gövdesi büyük |
| localization | `act_local_efb`, `text` | String | **İSTİSNA** | Fork sözlüğü. i18n `.l10n.php` |
| payments | `flxfPayProvider` | String | öneri | `FLXF_PAY_PROVIDER_SETTING` (`payments.php:39`), varsayılan stripe. Ödeme başlatma REST'te kalır |
| payments | `stripePKey`, `paypalPKey` | String | öneri (publishable) | |
| payments | `stripeSKey`, `paypalSKey`, `payToken` | String | write-only | Okumada yok. Webhook sırrı bu JSON'da değil |
| sms | `sms_config`, `phnNo` | String | `sms_config` okunur; `phnNo` write-only | `sms_config` değeri `wpsms` / `ws.team` / `null` |
| eklenti | `AdnSPF`, `AdnOF`, `AdnPPF`, `AdnATC`, `AdnSS`, `AdnCPF`, `AdnESZ`, `AdnSE`, `AdnWHS`, `AdnPAP`, `AdnWSP`, `AdnSMF`, `AdnPLF`, `AdnMSF`, `AdnBEF`, `AdnPDP`, `AdnADP`, `AdnGoS`, `AdnTLG`, `AdnATF`, `AdnHSH` | `'0'` / `'1'` | öneri (bayrak) | Hepsi varsayılan `'0'` (`:1786`) |

JSON satırında olmayan sırlar: `emsfb_telegram_bot_token` (`class-Emsfb-admin.php:323`), `emsfb_telegram_chat_id` (`:324`), `emsfb_stripe_webhook_secret` (`StripePayment::SECRET_OPTION`, `vendor/stripe/class-Emsfb-stripe-payment.php:29`; `webhook_secret()` `:213`, sabit `EMSFB_STRIPE_WEBHOOK_SECRET` veya filtre `efb_stripe_webhook_secret`).

Başka seçenekler: `emsfb_pro`, `emsfb_addons_renew_required`, `emsfb_addons_dl_failures`, `emsfb_cache_plugins`, `emsfb_pending_uploads`.

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
5. Captcha bugün formun kendi `captcha` bayrağı + ayarlardaki Google reCAPTCHA v2 (`siteverify`, `class-Emsfb-public.php:3243`). İsteğe bağlı Shield Silent Captcha. `flx_captcha_*` çağrısı yok. GraphQL `flx_captcha_gql_verify( 'flxf_submit' )` kullanır; REST siteverify aynı kalır.
6. Honeypot alanı yok (yalnız tanıtım metni). Gönderimde `flx_rate_limit` yok; sınır onay kodu aramasında ve ödemede. GraphQL gönderimine honeypot ve `flx_rate_limit` eklenir; REST'e yeni davranış eklenmez.
7. `flx/form` ve `flx/advanced-form` (flx-blocks) gönderimi bu pakette değil. Geçici istisna `ISTISNALAR.md`; `flxFormSubmit` açılınca o AJAX'lar ayrı PR ile kalkar.

## Tipler

Ham `form_structer` GraphQL'e konmaz. Public `flxForm` allowlist'tir (aşağıda). `stateForm` true veya `1` ise anonim ziyaretçiye `null` (`class-Emsfb-public.php:1285`). Giriş yapmış kullanıcıya klasik sayfa formu gösterir; GraphQL de öyle.

### Public allowlist (`flxForm`)

Dönen: `id`, `title` (`form_name`), `formType`, adım sayısı, düğme metni (`button_single_text`), başarı metni (`thank_you_message.thankYou`, `thank_you_message.done`), alanlar. Alan: `id`, `type`, `label` (`name`), `required`, `placeholder`, seçeneklerde yalnız `value` + `label`. `hidden` (`types.js:488`) ve `disabled` (`:489`, salt okunur) alanlar listede yok. Ayrı bir admin-only anahtarı yok.

Sunucudan hiç çıkmayan `form_structer` anahtarları (adlar kodda):

| Anahtar | Yer |
|---|---|
| `email`, `email_to` | başlık. `convert.js:32`, `:48`. Public HTML `email`'i boşaltır (`class-Emsfb-public.php:1282`) |
| `notification_rules` | `recipient`, `cc`, `bcc`, `subject` (`functions.php:3571`, `sanitize_notification_rules` `:3688`) |
| `webhook_rules` | `url`, `method`, `payload_fields` (`sanitize_webhook_rules` `:3770`) |
| `confirmation_rules` | `url` ve kuralın kendisi (`sanitize_confirmation_rules` `:3715`). Teşekkür metni allowlist'te; kural değil |
| `rePage` | `functions.php` yönlendirme; public HTML `thank_you == rdrct` iken temizler (`class-Emsfb-public.php:1291`) |
| `sendEmail`, `email_temp` | başlık (`convert.js:53`, `:65`) |
| `smsnoti`, `sms_msg_new_noti`, `sms_msg_responsed_noti`, `sms_msg_recived_usr`, `sms_admins_phone_no` | başlık (`class-Emsfb-admin.php:308`) |
| `telegramnoti`, `telegram_msg_new_noti`, `telegram_msg_responsed_noti`, `telegram_msg_recived_usr`, `telegram_bot_token`, `telegram_admin_chat_ids` | başlık (`:319`). Seçenek yedeği `emsfb_telegram_bot_token`, `emsfb_telegram_chat_id` |
| `logic_rules` içinde `scope` = `notification`, `confirmation` veya `webhook` | izinli kapsamlar `functions.php:3612`. Alan kapsamı (`field`) çizim içindir; public listede yine yok (allowlist kural gövdesi taşımaz) |
| `registered_count`, `mlen`, `dateExp` | rezervasyon sayacı (`class-Emsfb-public.php:2691`, `:2699`) |

Seçenek satırından yalnız `value` ve görünen etiket kopyalanır; sayaçlar kopyalanmaz.

| Tip | Alanlar (kısa) | Nullable ve nedeni | Loader |
|---|---|---|---|
| `FlxForm` | `id`, `name`, `type: FlxFormType!`, `fields`, `updatedAt` | `updatedAt` yoksa null | form id |
| `FlxFormPublic` | `id`, `name`, `type`, `fields` (etiket, tür, zorunlu, seçenekler) | form yoksa veya gizliyse kök `null` | form id |
| `FlxFormType` | `FORM`, `SURVEY`, `SUBSCRIBE`, `PAYMENT`, `REGISTER`, `LOGIN` | — | `blocks.php:42` |
| `FlxFormField` | `id`, `type` (`String!`, açık tür listesi), `label`, `required`, `options` | `options` seçimsiz alanda boş liste | yapıdan |
| `FlxFormResponse` | `id`, `form`, `trackCode`, `status: FlxFormResponseStatus!`, `createdAt`, `fields`, `replies`, `preview` | `form` silinmişse null | msg id |
| `FlxFormResponseStatus` | `UNREAD` (0), `READ` (1), `PENDING_PAYMENT` (2), `UNREAD_FOLLOWUP` (3), `READ_FOLLOWUP` (4) | — | `read_` |
| `FlxFormReply` | `body`, `createdAt: FlxDateTime!`, `attachments: [String!]!` | `attachments` boş liste | rsp id |
| `FlxFormsDashboard` | aralık, kartlar (yanıt, okunmamış, görüntüleme, dönüşüm, ödeme), `series`, `byForm`, `payStatus`, `heat`, `recent`, `topForms` | görüntüleme tablosu yoksa ilgili kart null | — |
| `FlxFormsWidgetPeriod` | `DAY`, `WEEK` | — | widget `period` |
| `FlxFormsWidgetStats` | ziyaret, gönderim, gönderilen e-posta, başarısız e-posta | veri yoksa sayılar 0 | — |
| `FlxFormsEmailError` | `date`, `recipient`, `detail` | — | — |
| `FlxFormTrack` | `trackCode`, `formTitle`, `submittedAt: FlxDateTime!`, `fields`, `replies` | kod yoksa kök hata `NOT_FOUND` (payload değil) | track |
| `FlxFormTrackReply` | `id`, `body`, `createdAt` | — | rsp id |
| `FlxFormSubmitPayload` | `submission { id, trackCode }`, `errors: [FlxUserError!]!` | başarıda `submission`, hatada null | — |

Connection'lar `first` / `after`, üst sınır 50. `flx-forms/v1` bugün 100 ister; REST öyle kalır, GraphQL 50.

Kökler:

| Alan | cap | Not |
|---|---|---|
| `flxForm(id: ID!): FlxForm` | `public` | Allowlist. Gerekçe: shortcode zaten herkese aynı çizim alanlarını basar. Gizli form anonime `null`. `option`/`r_matrix` satırları sızmaz |
| `flxFormPicker: [FlxFormPickerItem!]!` | `flx_gql_any_cap( 'edit_posts', 'Emsfb', 'manage_options' )` | Blok seçici. Yalnız `id` + `title`. Bugünkü `edit_posts` |
| `flxForms: FlxFormConnection!` | `flx_gql_any_cap( 'Emsfb', 'manage_options' )` | Yönetici listesi. Seçici bu sorgu değildir |
| `flxFormSubmit(input: FlxFormSubmitInput!): FlxFormSubmitPayload!` | `public` | `form` / `survey` / `subscribe`. `captchaToken`, honeypot, `flx_rate_limit`, klasik `flxf_dispatch_submit_effects`. Dosya: aşağıda |
| `flxFormTrack(code: String!): FlxFormTrack` | `public` | Kod yoksa `NOT_FOUND`. Captcha token yok (klasik API doğrulamaz). Klasik `efb_track_lookup_allowed` / `efb_track_legacy_lookup_allowed` sınırı |
| `flxFormTrackReply(input: FlxFormTrackReplyInput!): FlxFormSubmitPayload!` | `public` | `message`, `attachments` (yalnız bu bilet için ayrılmış), `adminCode` (`sc`), klasik `flxf_dispatch_reply_effects`. Captcha token yok |
| `flxFormResponses(...): FlxFormResponseConnection!` | `Emsfb` veya `manage_options` | `formId`, `unread`, `search` |
| `flxFormResponse(id: ID!): FlxFormResponse` | aynı | Yoksa `NOT_FOUND` |
| `flxFormResponseByTrack(code: String!): FlxFormResponse` | aynı | |
| `flxFormResponseRead(id: ID!)` / `flxFormResponsesRead(ids: [ID!]!)` | aynı | `0→1`, `3→4` |
| `flxFormResponseDelete(id: ID!)` / `flxFormResponsesDelete(ids: [ID!]!)` | aynı | |
| `flxFormResponseReply(input: FlxFormReplyInput!)` | aynı | |
| `flxFormsDashboard(from: String!, to: String!, formId: ID): FlxFormsDashboard!` | aynı | Aralık en çok 731 gün |
| `flxFormsWidgetStats(period: FlxFormsWidgetPeriod!): FlxFormsWidgetStats!` | `manage_options` | Widget: ziyaret, gönderim, e-posta. `period` `DAY` veya `WEEK` |
| `flxFormsEmailErrors(period: FlxFormsWidgetPeriod!): FlxFormsEmailErrorConnection!` | `manage_options` | Widget hata listesi |

`FlxFormSubmitInput`: `formId: ID!`, `fields: [FlxFormFieldValueInput!]!`, `captchaToken: String`, `honeypot: String`. Honeypot doluysa `INVALID_INPUT` (alan `honeypot`), kayıt yazılmaz.

`FlxFormTrackReplyInput`: `trackCode: String!`, `message: String!`, `attachments: [String!]`, `adminCode: String`. `adminCode` klasik `sc`: `hash_equals( md5( track + email_key ), adminCode )` ve `email_key` uzunluğu > 3 (`class-Emsfb-public.php:5148`). Yoksa ziyaretçi yanıtı. Yanlış kod `INVALID_INPUT`, yetki yükselmez. `email_key` dönmez.

### Yükleme bağlama

Klasik gönderim `validate_submitted_upload_url_efb` (`class-Emsfb-public.php:4635`, çağrı `:2947`) dosyayı `wp_upload_dir()` altında, tür ve boyut `Upload_Guard::validate_stored_file` ve kaydedici kuralları geçiyorsa kabul eder. `emsfb_pending_uploads` (`Upload_Guard::PENDING_OPTION`, `class-Emsfb-upload-guard.php:58`) defter `basename => zaman` tutar; form id tutmaz. `claim_uploaded_files_efb` `insert_message_db` sonrasında eşleşen adları `release_pending_uploads` ile düşer.

Yanıt kutusu ayrı: `reserve_response_attachment` (`:875`) transient'a `message_id` + `sha256(track)` yazar. `response_attachments_are_reserved_efb` (`class-Emsfb-public.php:4014`) **çağrılır**.

GraphQL: `flxFormSubmit` URL'yi ancak basename `emsfb_pending_uploads` içindeyse VE klasik alan döngüsündeki `validate_submitted_upload_url_efb` geçiyorsa alır; satırı `@file@` + `url` biçiminde yazar. `flxFormTrackReply` yalnız `Upload_Guard::response_attachment_is_reserved` geçen ekleri alır (bilet bağı var).

Hatalar: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `INVALID_INPUT`, `CONFLICT`, `RATE_LIMITED`, `INTERNAL`. Yazmalarda `INVALID_INPUT` ve `CONFLICT` payload `errors` içine.

## Yetkiler (`flx_gql_capabilities`)

Yeni cap açılmaz. Katalogda mevcut cap'ler gerekir (`useFlxCan` için), ancak kit (`plugins/flx-graphql/includes/kit/caps.php:85`) şu an `^[a-z0-9_]+$` doğruladığı için büyük harfli `Emsfb*` cap'leri dilim 2'de kaydedilmeden önce kit izni / eşlemesi gerekir:

| Cap | Kimde | Ne için |
|---|---|---|
| `Emsfb` | yönetici (`class-Emsfb-admin.php:72`) | Panel, yanıt, pano |
| `Emsfb_create` | yönetici | Yalnız klasik oluşturucu (GraphQL kökü yok) |
| `Emsfb_addon` | yönetici | Eklentiler ekranı (dilim 6) |
| `manage_options` | yönetici | E-posta hataları, ödeme sayfası, otomasyon, sistem kontrolü |

`edit_posts` blok seçicisinde kalır: `flxFormPicker` `flx_gql_any_cap( 'edit_posts', 'Emsfb', 'manage_options' )` ve yalnız id + başlık. Tam liste `flxForms` `Emsfb` veya `manage_options`. Pano cap'i filtreli değil (`FLXF_Dashboard::can`). Otomasyon cap'i `flxf_automations_capability` filtresi.

## Servisler

| Sınıf | Ne taşır | Kim çağırır |
|---|---|---|
| `FLXF_Form_Service` | `public_form`, `load`, `choice_rows`, `purge`. Ham JSON'u alan listesine çevirir | `flxForm`, `FLXF_Submission_Service`, `update_form_cache_efb` / `clear_form_cache_efb` / `blocks.php::flush` |
| `FLXF_Submission_Service` | `submit` (form/survey/subscribe), `track`, `reply`, honeypot, `flx_captcha_gql_verify`, `flx_rate_limit`, klasik `flxf_dispatch_*_effects` | `flxFormSubmit`, `flxFormTrack`, `flxFormTrackReply` |
| `FLXF_Response_Service` | liste, tekil, yazışma, okundu, sil, yanıt, onay kodu (dilim 2) | Panel AJAX + `flx-forms/v1` + GraphQL |
| `FLXF_Dashboard_Service` | `FLXF_Dashboard::stats` ve widget'ın gün/hafta hesabı (dilim 3) | `flxf_dash_stats`, `efb_dashboard_stats`, `efb_dashboard_email_errors`, `flxFormsDashboard`, `flxFormsWidgetStats` |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-forms:form:{id}` | `flxForm` | form kaydı/silme (`update_form_cache_efb`, `clear_form_cache_efb`, `blocks.php::flush` → `FLXF_Form_Service::purge`) |
| `flx-forms:list` | `flxForms` | form kaydı / silme |
| `flx-forms:responses` | `flxFormResponses` | `flxFormSubmit`, sil, okundu, yanıt |
| `flx-forms:response:{id}` | `flxFormResponse` | aynı |
| `flx-forms:dashboard` | `flxFormsDashboard` | gönderim ve silme |

Yanıt ve pano girişli istek; önbelleğe alınmaz. `flxForm` (public alan listesi) persisted GET ile önbelleğe girer. Ödeme ve dosya uçları `no-store` kalır.

## Arka plan (GraphQL değil)

| Kanca | Ne | Yer |
|---|---|---|
| `emsfb_daily_maintenance` | günlük cron | `class-Emsfb.php:215`, action `:70` |
| `emsfb_revalidate_license` | günlük cron | aynı |
| `emsfb_refresh_ir_cdn_status` | saatlik cron | aynı |
| `emsfb_cleanup_orphan_uploads` | günlük cron | `class-Emsfb-upload-guard.php:75`, zaman `:1223` |
| `emsfb_email_monitor_weekly` | haftalık cron | `class-Emsfb-email-monitor.php:18`, zaman `:519` |
| `emsfb_email_monitor_poll`, `emsfb_email_monitor_lifecycle` | tek seferlik | `:873`, `:1887` |
| `emsfb_check_file_access_after_activation` | tek seferlik, aktivasyon | `flx-forms-load.php:161`. Action `emsfb_file_access_check_after_activation` ayrı (`class-Emsfb.php:54`) |
| `emsfb_addon_recovery_event` | tek seferlik | `functions.php:5467` |
| `emsfb_update_cache_plugins_list`, `emsfb_update_security_plugins_list` | action | `class-Emsfb.php:49`. Bu taramada `wp_schedule_*` yok |
| `flxf_automations_cleanup` | günlük cron | `class-flxf-automations.php:19`, zaman `:34` |
| `flxf_automations_resume` | tek seferlik | `class-flxf-automations-engine.php:29`, zaman `:423` |
| `delete_preview_page_efb` | tek seferlik | `class-Emsfb-public.php:7240`. Oluşturucu önizlemesi |

Başlık bugün `Requires PHP: 7.4` (`flx-forms.php`). Uygulama PR'ı KURALLAR §10 ile 8.1 yapar ve `Requires Plugins: flx-graphql` ekler. `flx-graphql` yoksa GraphQL kaydı atlanır, REST durur.

## PR bölünmesi (uygulama, bu tur değil)

1. Public okuma + `flxFormSubmit` + `flxFormTrack` + `flxFormTrackReply`. Servis çıkarımı, REST baytları aynı. Ekran yok.
2. Panel: yanıtlar + Ayarlar sekmesi (`flxFormsSettings`, sırlar write-only). Oluşturucu linki klasik sayfada kalır.
3. Pano (`flxFormsDashboard`) ve sistem kontrolü. Widget PHP kalır.
4. Ödemeler listesi (`flxFormsPayments`). Başlatma ve webhook REST'te kalır.
5. Otomasyonlar (`flxf_auto_*`).
6. Eklentiler.

Tek PR'a sığmaz: `get_form_public_efb` tek başına ~1600 satır (`:2233`–`:3854`). Bu dal yalnız 1.

## Kararlar

1. **Captcha.** Kapandı: **1A**. `flx_captcha_gql_verify( 'flxf_submit' )`. Bağlam `flx_captcha_contexts`. Formun kendi captcha'sı (`captcha == 1`, `secretKey`, Google siteverify) ve Human Shield `efb_submit_bot_decision` klasik `_Public::flxf_submit_gates` ile GraphQL'de de uygulanır.
2. **Gönderim türü.** Kapandı: **2A**. `form` / `survey` / `subscribe`. `login`, `register`, `logout`, parola kurtarma ve ödemenin son adımı REST istisnası.
3. **Dosya.** Kapandı: **3A**, yukarıdaki bağlama ile. Klasik `validate_submitted_upload_url_efb` zayıflığı bu PR'da düzeltilmez.
4. **Yönetici kapsamı.** Kapandı. Panel (yanıtlar + Ayarlar), Pano, Ödemeler listesi, Sistem kontrolü, Otomasyonlar ve Eklentiler React ekranı (FluxApp masaüstü dahil). Yalnız Oluştur klasik. Sırlar write-only. İstisna: oluşturucu içi, ödeme callback/webhook, ikili yükleme/indirme, eski nonce.

Plan: 1 public okuma + gönderim, 2 Panel yanıtlar + ayarlar, 3 Pano + sistem kontrolü, 4 Ödemeler, 5 Otomasyonlar, 6 Eklentiler. Bu dal yalnız 1.

Teknik: public `flxForm` ham yapı dönmez. Honeypot ve gönderim oran sınırı GraphQL'e eklenir, REST baytları aynı kalır. `response_attachments_are_reserved_efb` `:5179`'da çağrılır. `flxf_dashboard_capability` diye bir filtre yok. `get_setting_Emsfb` `functions.php` içinde değil.
