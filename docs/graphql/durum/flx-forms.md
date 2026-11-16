# flx-forms 2.5.0

- Faz: 1
- Durum: 👀
- PR: #334

Plan: 1 public okuma + gönderim (`flxForm`, `flxFormSubmit`, `flxFormTrack`, `flxFormTrackReply`), 2 Panel yanıtlar + ayarlar, 3 Pano + sistem kontrolü, 4 Ödemeler, 5 Otomasyonlar, 6 Eklentiler. Bu dal 1'i yazar; ekran yok; paylaşım etkileri (mail/SMS/webhook/anket önbelleği), klasik kayıt biçimi ve form önbellek temizliği (`flx-forms:form:{id}`) eklendi (`Emsfb*` cap kataloğu kit isteğiyle dilim 2'ye bırakıldı). Oluştur (fork form oluşturucu) klasik kalır. Eşleme `docs/graphql/ESLEME/flx-forms.md`. Ayrıntı `docs/flx-forms/GRAPHQL.md`. Blok formu AJAX istisnası (`fxb_process_ajax_submit`, `fxb_process_advanced_form_submit`) `ISTISNALAR.md` ortak dosyasında; silinmesi ayrı PR.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Fork form oluşturucu: `Emsfb_create`, form kaydet/sil/kopyala/önizle AJAX, `POST /flxf/v1/block-forms`, `GET /efb/v1/preview/{id}`, `Emsfb/v1/test`, `admin_post_flxf_block_edit` | flx-forms | Oluştur sayfası klasik kalır (sahip) | Okuma `flxForm` / `flxFormPicker` |
| Ödeme callback ve webhook: `forms/payment/start`, `confirm`, `quote`, `stripe/card/add`, `stripe/confirm`, `stripe/pkey`, `forms/payment/webhook/{provider}`, `forms/payment/stripe/webhook`, `admin_post_emsfb_stripe_save` | flx-forms | Sağlayıcı sabit URL'e POST atar veya tarayıcı dönüşünü kendi imzasıyla doğrular | Liste `flxFormsPayments` (dilim 4). Sır write-only |
| `login` / `register` / `logout` / `forms/recovery/efb_set_password` aynı `message/add` callback'inde | flx-forms | `flxFormSubmit` yalnız form, anket, abonelik | REST aynı kalır |
| Dosya: `POST /Emsfb/v1/forms/file/upload`, `wp_ajax_update_file_Emsfb` (nonce only, no cap), `admin_post_flxf_dash_csv` | flx-forms | Multipart veya CSV baytı | Gönderim yalnız bekleyen / bu bilete ayrılmış adresi kabul eder |
| `GET /Emsfb/v1/nonce/refresh`, `wp_ajax_heartbeat_Emsfb` | flx-forms | Eski istemcinin `wp_rest` nonce protokolü | GraphQL WP oturum çerezini kullanır |
| `POST /Emsfb/v1/autofill/get` | flx-forms | Vendor autofill dosyası | — |
| Ayar sözlüğü `text` / `act_local_efb` | flx-forms | Fork çeviri sözlüğü; i18n `.l10n.php` | Ayar ekranı diğer anahtarları taşır |
