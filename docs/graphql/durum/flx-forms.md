# flx-forms 2.4.0

- Faz: 1
- Durum: ✱
- PR: #334

Envanter (`gql/flx-forms-1`). Servis, şema ve ekran yok. Taşınacak: form tanımı okuma, `flxFormSubmit`, yanıtlar ekranı, pano. Fork oluşturucu ve ödeme uçları kalır. Eşleme `docs/graphql/ESLEME/flx-forms.md`. Blok formu AJAX istisnası (`fxb_process_ajax_submit`, `fxb_process_advanced_form_submit`) `ISTISNALAR.md` ortak dosyasında; silinmesi ayrı PR.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Fork form oluşturucu: `Emsfb_create`, form kaydet/sil/kopyala/önizle AJAX, `POST /flxf/v1/block-forms`, `GET /efb/v1/preview/{id}`, `Emsfb/v1/test` | flx-forms | Fork editör (ve blok editörü yazması); sahip kararı, içine dokunulmaz | Okuma `flxForm` / `flxForms` |
| Ödeme: `forms/payment/start`, `confirm`, `quote`, `stripe/card/add`, `stripe/confirm`, `stripe/pkey`, webhook `forms/payment/webhook/{provider}` ve `forms/payment/stripe/webhook`, `admin_post_emsfb_stripe_save` | flx-forms | Sağlayıcı sabit URL'e POST atar veya tarayıcı dönüşünü kendi imzasıyla doğrular; tutar sunucuda | Ödeme toplamları `flxFormsDashboard` |
| Dosya: `POST /Emsfb/v1/forms/file/upload`, `wp_ajax_update_file_Emsfb` | flx-forms | Multipart | Gönderim kaydı yüklenmiş dosyanın adresini taşır |
| `GET /Emsfb/v1/nonce/refresh`, `wp_ajax_heartbeat_Emsfb` | flx-forms | Eski istemcinin `wp_rest` nonce protokolü | GraphQL WP oturum çerezini kullanır |
| `POST /Emsfb/v1/forms/recovery/efb_set_password`, `POST /Emsfb/v1/autofill/get` | flx-forms | E-posta jetonuyla WP parolası; vendor autofill dosyası | — |
| Otomasyonlar `page=flxf_automations` ve `wp_ajax_flxf_auto_*` | flx-forms | Bu dalganın kapsamında değil (ayrı Flux ekranı) | — |
| `admin_post_flxf_dash_csv`, `admin_post_flxf_block_edit`, `admin_post_flx_system_checks_dismiss` | flx-forms | CSV baytı, editöre yönlendirme, ortak sistem kontrolü | Pano verisi `flxFormsDashboard` |
