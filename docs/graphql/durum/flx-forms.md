# flx-forms 2.5.0

- Faz: 1
- Durum: 👀
- PR: #334

Plan: 1 public okuma + gönderim (`flxForm`, `flxFormSubmit`, `flxFormTrack`, `flxFormTrackReply`), 2 Panel yanıtlar + ayarlar, 3 Pano + sistem kontrolü, 4 Ödemeler, 5 Otomasyonlar, 6 Eklentiler. Bu dal 1'i yazar; ekran yok; paylaşım etkileri (mail/SMS/webhook/anket önbelleği), klasik kayıt biçimi ve form önbellek temizliği (`flx-forms:form:{id}`) eklendi (`Emsfb*` cap kataloğu kit isteğiyle dilim 2'ye bırakıldı). Gönderim klasik hattan geçer (`_Public::flxf_*` ortak yöntemleri: koşullu mantık, zorunlu ve alan doğrulaması, form captcha'sı, Human Shield, `insert_message_db`, alıcılar). Takip yanıtı klasik izleme sınırlarını ve başarısız arama sayacını kullanır. Klasik açık: `femail` gönderen adresi klasik gönderimde `$setttting` yazım hatası yüzünden hiç atanmaz; GraphQL aynı davranır ve düzeltilmedi. Kit notu: karışık harfli `Emsfb*` yetkileri `plugins/flx-graphql/includes/kit/caps.php:85` düzenli ifadesi yüzünden kit kataloğundan düşer (alan denetimleri çalışır); yönetici dilimlerinden önce kit kararı gerekir. Oluştur (fork form oluşturucu) klasik kalır. Eşleme `docs/graphql/ESLEME/flx-forms.md`. Ayrıntı `docs/flx-forms/GRAPHQL.md`. Blok formu AJAX istisnası (`fxb_process_ajax_submit`, `fxb_process_advanced_form_submit`) `ISTISNALAR.md` ortak dosyasında; silinmesi ayrı PR.

## 2.6.0: yönetim ekranı (dilim 1)

- Pano, Tüm formlar, Yanıtlar React ekranı (`packages/modules/src/forms`, `flxf-forms`), kabuk `includes/flx/flux-ui.php` (sayfalar `flxf-dashboard`, `flxf-forms`, `flxf-responses`; preload başlangıç rotasına göre).
- Sorgular: `flxFormsDashboard` (klasik pano yükü, FlxJSON), `flxFormsAdminForms`, `flxFormsFormOptions`, `flxFormsEntries`, `flxFormsEntry`. Mutasyonlar: `flxFormsEntriesMark`, `flxFormsEntriesDelete`, `flxFormsEntryReply` (boş metinle yalnız kapat/aç), `flxFormsFormDelete`, `flxFormsFormDuplicate`. Hepsi `manage_options` ya da `Emsfb`.
- Servis `includes/services/class-flxf-admin-service.php`; sözleşme testi `tools/tests/wp-graphql/flx-forms/contract-test.php` (admin bölümü).
- Sıradaki dilimler: 2 Ayarlar, 3 Ödemeler/Otomasyonlar/Sistem/Eklentiler, 4 Form oluşturucu. O zamana kadar bu sayfalar sekmeden kendi sayfası olarak açılır.

## 2.7.0: ayarlar (dilim 2)

- Ayarlar sekmesi (`flxf-settings`, `packages/modules/src/forms/Settings.tsx`). Sorgu `flxFormsSettings`, mutasyonlar `flxFormsSettingsUpdate` (yalnız gelen alanlar; `secretKey` yalnız yazılır, `clearSecretKey`), `flxFormsClearFiles`.
- Servis `includes/services/class-flxf-settings-service.php`: klasik `emsfb_setting` satırına birleştirir (`set_setting_Emsfb`), klasik kayıt kuralları (şablon temizliği, haftalık rapor yetkisi, SMTP durumu, `emsfb_dev_mode`).
- Ekranda yok: ödeme anahtarları (Stripe/PayPal/payToken, dilim 3 Ödemeler), eklenti bayrakları `Adn*` (dilim 3), `text` / `act_local_efb` (istisna), lisans alanları (Flux Forms'ta lisans yok).

## 2.8.0: ödemeler, otomasyonlar, sistem, eklentiler (dilim 3)

- Sekmeler `flxf_payments`, `flxf_automations` (liste, `?flow=N|new` düzenleyici, `?tab=runs` çalışma günlüğü), `flxf_system`, `Emsfb_addon` React ekranı (`Payments.tsx`, `Automations.tsx`, `FlowEditor.tsx`, `System.tsx`, `Addons.tsx`). Klasik render, `payments/assets/admin.css`, `automations/admin.js|css` silindi; menü kayıtları `flxf_screen_page`.
- Sorgular: `flxFormsPayments` (sağlayıcılar + son 50 ödeme; Flux Donate ödemeleri üstlenince `available: false`), `flxFormsSystem` (`flx_system_checks` satırları; Flux Donate/Speed ekranı taşıyınca `available: false`), `flxFormsAddons`, `flxFormsAutomations`, `flxFormsAutomationRuns`, `flxFormsAutomationEditor(id)` (FlxJSON). Mutasyonlar: `flxFormsStripeWebhookSecret` (yalnız yazılır, `whsec_`), `flxFormsAutomationSave/Toggle/Delete/Duplicate/Default/DonationForm/RunsClear`.
- Servisler `class-flxf-tools-service.php`, `class-flxf-automations-service.php`. Eklentiler salt okunur: paketle gelen add-on'lar (`AdnSPF`, `AdnOF`, `AdnSMF`) `get_setting_Emsfb` tarafından hep açık tutulur, kapatma düğmesi yok.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Fork form oluşturucu: `Emsfb_create`, form kaydet/sil/kopyala/önizle AJAX, `POST /flxf/v1/block-forms`, `GET /efb/v1/preview/{id}`, `Emsfb/v1/test`, `admin_post_flxf_block_edit` | flx-forms | Oluştur sayfası klasik kalır (sahip) | Okuma `flxForm` / `flxFormPicker` |
| Ödeme callback ve webhook: `forms/payment/start`, `confirm`, `quote`, `stripe/card/add`, `stripe/confirm`, `stripe/pkey`, `forms/payment/webhook/{provider}`, `forms/payment/stripe/webhook`, `admin_post_emsfb_stripe_save` | flx-forms | Sağlayıcı sabit URL'e POST atar veya tarayıcı dönüşünü kendi imzasıyla doğrular | Liste `flxFormsPayments` (dilim 3). Sır write-only |
| `login` / `register` / `logout` / `forms/recovery/efb_set_password` aynı `message/add` callback'inde | flx-forms | `flxFormSubmit` yalnız form, anket, abonelik | REST aynı kalır |
| Dosya: `POST /Emsfb/v1/forms/file/upload`, `wp_ajax_update_file_Emsfb` (nonce only, no cap), `admin_post_flxf_dash_csv` | flx-forms | Multipart veya CSV baytı | Gönderim yalnız bekleyen / bu bilete ayrılmış adresi kabul eder |
| `GET /Emsfb/v1/nonce/refresh`, `wp_ajax_heartbeat_Emsfb` | flx-forms | Eski istemcinin `wp_rest` nonce protokolü | GraphQL WP oturum çerezini kullanır |
| `POST /Emsfb/v1/autofill/get` | flx-forms | Vendor autofill dosyası | — |
| Ayar sözlüğü `text` / `act_local_efb` | flx-forms | Fork çeviri sözlüğü; i18n `.l10n.php` | Ayar ekranı diğer anahtarları taşır |
