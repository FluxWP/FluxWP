# flx-donate 2.33.0

- Faz: 2
- Durum: 👀
- PR: #340

(a) public bu PR'de: `flxDonationTypes`, `flxDonationType`, `flxDonationCartAdd`, `flxMyDonations`, `flxDonationLookupStart` / `Verify` / `End`, `flxDonationBankAccount`. Eşleme `ESLEME/flx-donate.md`: 102 GraphQL, 32 istisna. (a) 16, (b) 24, (c) 11, (d) 17, (e) 34. Sıra (a) → (b) → (c) → (d) → (e). Tek `schema.graphql`. React ekranı yok.

Kararlar: (e) takip, iş ortağı, rapor, Excel, tür, talep; detay sayfasının kalem adı, sertifika ve sekmeleri (b). `flxb_iyzico_renewals` aynı kalır, tahsilat mutation'ı yok, cron istisna; Forms siparişsiz, WC checkout `flxb-renewal` siparişi açar. CSV ekranı connection, indirme admin-post. `FlxPerson` #333'ten önce nullable.

`flxb_donors_own`: toplam ve tarihler `own_figures`, bağışlar `ref_user_id`, abonelik/sertifika/onay formu yok. İki personel testi (b)'de.

IBAN: `flxDonationBankAccount` `flxb_bank_account()` çağırır. Havale sınıfı yüklüyse birinci kaynak `woocommerce_flxb_havale_settings`; boşsa BACS. Sahip kuralı tek kaynak BACS diyordu; kod iki kaynak. Havale alanları (d).

Gizli yazma (d): sahip onayladı. Yaz-yalnız girdi; boş saklananı tutar, `"__clear__"` siler; değer dönmez; okuma `set`, varsa maske, `fromConstant`. Kalıp flx-ads ve flx-social. `flxb_public_token` EsnekPOS kimliği (`class-flxb-wc-esnekpos-direct.php:89`), hiç dönmez. (a) değişmez.

Lookup (a): HttpOnly çerez `flxb_lk` (adı `flxb_lookup` değil), kod 10 dk, oturum 30 dk, oran `flx_rate_limit('flxb_lk|…')`. `flxMyDonations` WP oturumu veya bu çerez; `no-store`.

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
| `POST flxb-wc/v1/receipt` | flx-donate | Dosya: MIME, boyut, `uploads/flxb-dekont/`. Medya yok | `flxDonationReceiptAdd(orderId, token)` |
| `form_post` `flxb_action=receipt` | flx-donate | JS'siz teşekkür formu | Aynı REST |
| `/?flxb_3ds=<key>` | flx-donate | Tek kullanımlık 3DS banka sayfası | Checkout |
| Hesabım adres defteri `flxb_addr_action` | flx-donate | WC hesap şablonu; bağış sepeti adres sormaz. İleride yalnız sahip | form kalır |
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
| cron `flxb_iyzico_renewals` | flx-donate | Forms siparişsiz tahsil eder. WC checkout önce `flxb-renewal` siparişi açar | `flxSubscriptions`; tahsilat mutation yok |
| `?flxb_wc_handoff=` | flx-donate | Tek kullanımlık sepet yönlendirmesi | `flxDonationCartAdd` |
| `/bagis-urunleri.xml` | flx-donate | iyzico/Google XML | `flxDonationTypes` |
| Hesabım `bekleyen-bagislar` | flx-donate | WooCommerce hesap şablonu | `flxMyDonations` |
| `admin_post_flx_system_checks_dismiss` | flx-donate | Ortak sistem çubuğu | Flux Speed / Admin ekranı |
| `[flx_credit]` + blok `flx/credit` | flx-donate | Marka kopyası, bağış verisi değil | Bu paketin şemasına girmez |
