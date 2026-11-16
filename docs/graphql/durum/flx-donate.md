# flx-donate 2.31.0

- Faz: 2
- Durum: ✱
- PR:

Envanter. Eşleme `ESLEME/flx-donate.md`: 102 GraphQL satırı, 29 istisna. Alt paketler (a) 16, (b) 19, (c) 11, (d) 17, atanmamış 39. Servis, şema ve ekran bu turda yok. Sıra (a) → (b) → (c) → (d) → atanmamış; tek `schema.graphql`. `FlxPerson` #333'e bağlı, liste değil.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| `POST\|GET flxb/v1/iyzico/callback` | flx-donate | iyzico token POST | `flxDonation` durumu |
| `GET\|POST flxb/v1/iyzico/cancel?s&k` | flx-donate | E-postadaki imzalı link | `flxSubscriptionCancel` (girişli) |
| `POST flxb/v1/iyzico/notify` | flx-donate | İşyeri bildirim URL'si, imza | durum sorgusu |
| `GET\|POST flxb/v1/wc/iyzico/callback` | flx-donate | Banka dönüşü | durum sorgusu |
| `POST\|GET flxb/v1/wc/iyzico/3ds` | flx-donate | Banka 3DS POST | durum sorgusu |
| `POST flxb/v1/wc/iyzico/installments` | flx-donate | Checkout BIN | Checkout Store API |
| `GET flxb/v1/esnekpos/installments` | flx-donate | Checkout BIN | Checkout |
| `POST flxb/v1/esnekpos/status` | flx-donate | 3DS penceresi sonucu | Checkout |
| `woocommerce_rest_checkout_process_payment_with_context` | flx-donate | EsnekPOS `PaymentContext`; kart saklanmaz | `POST /wc/store/v1/checkout` |
| `woocommerce_api_flxb_esnekpos` | flx-donate | EsnekPOS dönüş POST'u | durum sorgusu |
| `POST flxb-wc/v1/receipt` | flx-donate | Multipart + sipariş anahtarı, önbellekli sayfa | `flxDonationReceiptAdd(uploadId)` |
| `?flxb_dekont=` | flx-donate | Dosya baytı | metadata + aynı imzalı URL |
| `POST Emsfb/v1/forms/payment/start` | flx-donate | Flux Forms ödeme kutusu | Form ödemesi flx-forms işi |
| `POST Emsfb/v1/forms/payment/confirm` | flx-donate | Aynı protokol | Form ödemesi flx-forms işi |
| `POST Emsfb/v1/forms/payment/webhook/{provider}` | flx-donate | Sağlayıcı imzası | durum sorgusu |
| Stripe legacy `…/stripe/card/add` | flx-donate | Eski Forms adresi, `rest_dispatch_request` | Form ödemesi flx-forms işi |
| Stripe legacy `…/stripe/confirm` | flx-donate | Aynı | Form ödemesi flx-forms işi |
| Stripe legacy `…/stripe/pkey` | flx-donate | Yayın anahtarı bu protokolden | `publishableKey` |
| Stripe legacy `…/stripe/webhook` | flx-donate | Stripe imzası | durum sorgusu |
| `admin_post_flxb_csv` | flx-donate | CSV akışı, ~60 sn PHP | `flxDonations` sayfaları |
| `admin_post_flxb_report_csv` | flx-donate | Aynı | `flxDonationReport` sayfaları |
| `admin_post_flxb_partner_settle_csv` | flx-donate | Aynı | `flxPartnerSettlement` |
| cron `flxb_sync_payments` | flx-donate | Hostinger WP cron tetiklemez | `flxDonatePaymentImport` adım adım |
| cron `flxb_iyzico_renewals` | flx-donate | Aynı; WC siparişi açmadan tahsil | `flxSubscriptions`; tahsilat mutation yok |
| `?flxb_wc_handoff=` | flx-donate | Tek kullanımlık sepet yönlendirmesi | `flxDonationCartAdd` |
| `/bagis-urunleri.xml` | flx-donate | iyzico/Google XML | `flxDonationTypes` |
| Hesabım `bekleyen-bagislar` | flx-donate | WooCommerce hesap şablonu | `flxMyDonations` |
| `admin_post_flx_system_checks_dismiss` | flx-donate | Ortak sistem çubuğu | Flux Speed / Admin ekranı |
| `[flx_credit]` + blok `flx/credit` | flx-donate | Marka kopyası, bağış verisi değil | Bu paketin şemasına girmez |
