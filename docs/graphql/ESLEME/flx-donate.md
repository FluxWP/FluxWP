# Flux Donate (flx-donate): REST → GraphQL eşlemesi

Paket sürümü: 2.31.0 · Önek: `flxb_` · Dal: `gql/flx-donate-1` · Durum: taslak (envanter)

Bu dosya envanterdir. Servis, şema ve ekran bu turda yok. Her satır ya bir GraphQL adına ya da İSTİSNA'ya bağlıdır. Satırdaki `dosya:satır` kodda görülen kayıttır.

Dört alt paket (sahip kararı): **(a)** public türler, sepete ekleme, Bağışlarım · **(b)** admin liste/detay (bağış, bağışçı, ödeme kaydı, abonelik) · **(c)** onay, dekont, durum · **(d)** ayarlar ve görünüm. Bu dörde girmeyen ekranlar **atanmamış**. Banka/protokol uçları **istisna**.

## Özet sayım

Bir satır = aşağıdaki tablolardaki bir kayıt. Aynı GraphQL adı birden çok kaynaktan gelebilir (üç onay formu, tek `flxDonationApprove`). WC kancası ve dış paket kancası bu iki sayıya girmez.

| Grup | GraphQL | İstisna |
|---|---:|---:|
| (a) public | 16 | 0 |
| (b) admin liste/detay | 19 | 0 |
| (c) onay, dekont, durum | 11 | 0 |
| (d) ayarlar ve görünüm | 17 | 0 |
| atanmamış | 39 | 0 |
| istisna | 0 | 29 |
| **Toplam** | **102** | **29** |

| Kategori | GraphQL | İstisna | Toplam |
|---|---:|---:|---:|
| REST `register_rest_route` (19 + 12) | 19 | 12 | 31 |
| REST legacy yakalama (`payments.php:178`) | 0 | 4 | 4 |
| AJAX (`wp_ajax_`, nopriv yok) | 2 | 0 | 2 |
| admin-post | 50 | 4 | 54 |
| Admin sayfası / alt görünüm / filtre | 15 | 0 | 15 |
| MCP `flxb_donation_summary` | 1 | 0 | 1 |
| Ayar alanı (gizli olanlar işaretli) | 8 | 0 | 8 |
| Ön yüz (kısa kod, blok, IBAN, hesap, XML) | 6 | 5 | 11 |
| Ödeme dönüşü (REST dışı: `PaymentContext`, `wc-api`) | 0 | 2 | 2 |
| Cron | 0 | 2 | 2 |
| Dekont mutation'ı (`flxDonationReceiptAdd`; rota istisna) | 1 | 0 | 1 |
| **Toplam** | **102** | **29** | **131** |

WC iş kancası: 28 (serviste kalır). Dış paketin güvendiği kanca: 26 satır (imza değişmez).

## Yetki

Katalog `flx_gql_capabilities`. Sunucu her kök alanda kendi `cap`'ini uygular.

| Anahtar | Bugün | Kim |
|---|---|---|
| `flx_bagis_view` | `FLXB_CAP_VIEW` (`includes/helpers.php:13`) | Liste, detay, CSV, makbuz. `manage_options` de geçer (`flxb_can_view`, `helpers.php:28`) |
| `flxb_manage` | `flxb_manage_capability` filtresi, varsayılan `manage_options` (`helpers.php:32`) | Ayar, tür yazma, çöp, kalıcı silme, MCP özeti |
| `flxb_dashboard` | `FLXB_Refs::CAP_DASH` (`class-flxb-refs.php:28`) | Pano toplamları; iletişim alanları yok |
| `flxb_view_own` | `class-flxb-refs.php:27` | Yalnız kendi referansı |
| `flxb_create_ref` | `:29` | Personel bağış talebi |
| `flxb_donors_own` | `:30` | Kendi bağışçıları; CRM kartı yok, klasik kart |
| `flxb_ref_notify` | `:31` | Referans bildirimi (kanca, kök alan değil) |
| `flxb_track` | `FLXB_Track::can` (`class-flxb-track.php:52`) | Takip yazma |
| `edit_shop_orders` | onay kartı (`class-flxb-approval.php:28`) | WooCommerce bağışını onaylama |

`'public'` gerekçesi her satırda. Girişli bağışçı kaydı `owner` ile: `flx_bagis_view` yoksa yalnız kendi bağışı.

## (a) Public — 16 GraphQL

Sepete ekleme `FLXGQL_Cart::add_item()` (`docs/graphql/SEPET.md` §6). Checkout Store API'de kalır (istisna). Çoklu sertifika: `beneficiaries` bir satırda N ad; tek bağış, tek ödeme, ad başına sertifika (`API.md` kişi başına sertifika; `FLXB_WC_Certificates::recipients`).

| Kaynak | Ne | cap | GraphQL | Girdi → çıktı |
|---|---|---|---|---|
| `includes/class-flxb-rest.php:24` | `GET flxb/v1/types` | `'public'` — bağış sayfası anonim; uç bugün `__return_true`, arşiv dönmez | `flxDonationTypes` | `status: FlxDonationTypeListStatus`, `category` → `FlxDonationTypeConnection!` (≤50) |
| `class-flxb-rest.php:39` | `GET flxb/v1/types/{ref}` | `'public'` — aynı | `flxDonationType` | `id: ID` → `FlxDonationType` (yoksa `NOT_FOUND`) |
| `includes/class-flxb-app.php:187` | `GET flxb/v1/me` | `logged_in` + owner | `flxMyDonationSummary` | — → `FlxMyDonationSummary!` |
| `class-flxb-app.php:188` | `GET flxb/v1/me/donations` | `logged_in` + owner | `flxMyDonations` | connection → `FlxDonationConnection!` |
| `class-flxb-app.php:201` | `GET …/me/donations/{id}` | `logged_in` + owner | `flxMyDonation` | `id` → `FlxDonation` (başkası `NOT_FOUND`) |
| `class-flxb-app.php:202` | `GET flxb/v1/me/certificates` | `logged_in` + owner | `flxMyCertificates` | — → `[FlxDonationCertificate!]!` |
| `includes/class-flxb-lookup.php:224` | `POST flxb/v1/lookup/start` | `'public'` + oran sınırı (`lookup.php:208`) — hesap şart değil; cevap bağış varlığını sızdırmaz | `flxDonationLookupStart` | `login` → `{ ok, via, ttl }` (`errors`) |
| `class-flxb-lookup.php:225` | `POST …/lookup/verify` | `'public'` + oran sınırı | `flxDonationLookupVerify` | `login, code` → `FlxDonationLookupPayload` |
| `class-flxb-lookup.php:226` | `POST …/lookup/data` | oturum çerezi veya `logged_in` | `flxDonationLookup` | — → `FlxDonationLookupPayload` |
| `class-flxb-lookup.php:227` | `POST …/lookup/logout` | `'public'` | `flxDonationLookupEnd` | — → `Boolean!` |
| `includes/wc/class-flxb-wc-form.php:27` ve `:62` | `[flx_bagis_secenekleri]` + blok `flx-wc-bagis/secenekler` | `'public'` — form herkese açık | `flxDonationTypes` (okuma). Blok PHP'de kalır | |
| `class-flxb-wc-form.php:158` | Form POST `wp_loaded` (nonce yok, önbellekli sayfa) | `'public'` | `flxDonationCartAdd` | `FlxDonationCartAddInput!` → `{ cart: FlxCart, itemKey, errors }` |
| `includes/wc/class-flxb-wc-currency.php:38` | `[flx_bagis_para_birimi]` | `'public'` — para seçici vitrinde | `flxDonationCurrencies` | — → `[FlxDonationCurrency!]!` |
| `includes/class-flxb-shortcode.php:16` | `[flx_bagis_kampanya]` | `'public'` — yayımlanmış türün hedef çubuğu | `flxDonationType.progress` | |
| `class-flxb-lookup.php:40` | `[flx_bagislarim]` / `[flx_my_donations]` | lookup sorguları | kısa kod kalır | |
| `includes/api.php:249` | Tek IBAN (`flxb_bank_account`) | `'public'` — bağış sayfası ve teşekkür zaten gösterir; kaynak WooCommerce BACS, Donate kopyası yok | `flxDonationBankAccount` | — → `FlxBankAccount` (yoksa null) |

`flxDonationCartAdd` çözümleyicisi işi yazmaz: `FLXB_WC_Cart::build_line()` (`class-flxb-wc-cart.php:154`) sonra `FLXGQL_Cart::add_item( FLXB_WC_Products::product_id, 1, [], [ FLXB_WC_Cart::KEY => $line ] )`. Para birimi `add_item`'dan önce `FLXB_WC_Currency::set`. `flx_gql_cart_item_kind` → `donation`. `FlxCartItem.flxDonation: FlxDonationLine`. Adet Store API'de düzenlenemez. Klasik POST durur.

`FlxDonationCartAddInput`: `typeId`, `option`, `units`, `amount`, `currency`, `beneficiaries: [String!]`, `proxyConsent`, `certificate`, `monthly`. N ad = bir satır.

## (b) Admin liste ve detay — 19 GraphQL

Bağışçı detayı CRM kartı: `page-donors.php:215` `flxcrm_person_card()`. GraphQL'de `FlxDonor.person: FlxPerson` **nullable**. Görünürlük `flxcrm_gql_can_see_contact`; Donate yalnız bu bağışçıyı görmeye yetkili kullanıcı için true döner (`flx_bagis_view` veya `flxb_donors_own` ve kayıt onun). `flxb_donors_own` klasik kartta kalır (`flxb_use_crm_card`). Bölüm kaydı `gql/flx-crm-2` (PR #333) gelene kadar kişi kartı bölümleri PHP kancasıdır; (b) liste ve bağış alanlarını onsuz açar, `person` null kalabilir.

| Kaynak | Ne | cap | GraphQL | Girdi → çıktı |
|---|---|---|---|---|
| `includes/class-flxb-app-admin.php:40` | `GET flxb/v1/admin/summary` | `flx_bagis_view` | `flxDonationSummary` | — → `FlxDonationSummary!` |
| `class-flxb-app-admin.php:41` | `GET …/admin/donations` | `flx_bagis_view` veya owner `flxb_view_own` | `flxDonations` | connection + `FlxDonationFilter` → `FlxDonationConnection!` |
| `class-flxb-app-admin.php:42` | `GET …/admin/donations/{id}` | aynı | `flxDonation` | `id` → `FlxDonation` |
| `class-flxb-app-admin.php:43` | `GET …/admin/donors` | `flx_bagis_view` veya `flxb_donors_own` | `flxDonors` | connection + `search, kind` → `FlxDonorConnection!` |
| `class-flxb-app-admin.php:44` | `GET …/admin/donors/{id}` | aynı | `flxDonor` | `id` → `FlxDonor` |
| `class-flxb-app-admin.php:45` | `GET …/admin/subscriptions` | `flx_bagis_view` | `flxSubscriptions` | connection + `status` → `FlxSubscriptionConnection!` |
| `includes/admin/class-flxb-admin.php:37` → `page-dashboard.php:63` | `wp_ajax_flxb_stats` | `flxb_dashboard` (iletişim yok) / `flx_bagis_view` | `flxDonationDashboard` | `range` → `FlxDonationDashboard!` |
| `class-flxb-admin.php:45` | `admin_post_flxb_donor_save` | `flx_bagis_view` yazma: `flxb_manage` | `flxDonorUpdate` | `FlxDonorUpdateInput` → `{ donor, errors }` |
| `class-flxb-admin.php:54` | `admin_post_flxb_bulk` (`trash` / `restore` / `delete`) | `flxb_manage`; `delete` için `manage_options` (`page-donations.php:384`) | `flxDonationsBulk` | `ids, do: FlxDonationBulk` → `{ donations, errors }` |
| `class-flxb-admin.php:39` | `admin_post_flxb_receipt` (yazdırılan makbuz, dekont değil) | `flx_bagis_view` veya owner | `flxDonationReceiptDocument` | `id` → `FlxDonationReceiptDocument` |
| `class-flxb-admin.php:59` | `admin_post_flxb_ref_save` | `flxb_manage` | `flxDonationReferralSet` | `id, userId` → `{ donation, errors }` |
| `includes/admin/page-payments.php:22` | `admin_post_flxb_sub_cancel` | `flxb_manage` | `flxSubscriptionCancel` | `id` → `{ subscription, errors }` |
| `includes/admin/class-flxb-admin.php:128` | `page=flxb-dashboard` | `flxb_dashboard` | `flxDonationDashboard` | Aralık 7/30/90/özel (`page-dashboard.php:34`). CSV linki istisna `flxb_csv` |
| `class-flxb-admin.php:130` | `page=flxb-donations` liste | `flx_bagis_view` / `flxb_view_own` | `flxDonations` | Filtre `s, from, to, status` (+ `receipt`/`payment` aşaması), `type, dtype, form, donor_id, ref` (`page-donations.php:17`). Toplu: çöp / geri al / kalıcı sil. Çöp görünümü `status=trash` |
| `page-donations.php:80` | `page=flxb-donations&donation=` detay (okuma) | aynı | `flxDonation` | Kardeş satırlar bir bağış. `person` yok; bağışçı `FlxDonor` |
| `class-flxb-admin.php:135` | `page=flxb-donors` + `&donor=` | `flx_bagis_view` / `flxb_donors_own` | `flxDonors`, `flxDonor` | Filtre `s, kind` (all/recurring/consent), sıralama (`page-donors.php:39`) |
| `class-flxb-admin.php:136` | `page=flxb-subscriptions` | `flx_bagis_view` | `flxSubscriptions` | Filtre `status, s` (`page-subscriptions.php:20`) |
| `class-flxb-admin.php:139` | `page=flxb-payments` kayıt tablosu | `manage_options` | `flxPayments` | Sayfalı kayıt; filtre yok (`page-payments.php:149`). Sağlayıcı kartları (d) |
| `includes/mcp.php:28` | `flx_mcp_tools` → `flxb_donation_summary` | `flxb_manage` | `flxDonationPeriodSummary` | `period \| date \| from/to`, `compare` → `FlxDonationPeriodSummary!`. Araç kalır; kişisel veri yok |

Admin REST `per_page` üst sınırı 100 (`class-flxb-app-admin.php:38`). GraphQL connection ≤ 50; REST cevabı değişmez.

## (c) Onay, dekont, durum — 11 GraphQL

Dekont baytı `flx/v1/upload` ile gider (ortak istisna); mutation `uploadId` alır. Mevcut `POST flxb-wc/v1/receipt` istisnadır (önbellekli teşekkür sayfası, sipariş anahtarı).

| Kaynak | Ne | cap | GraphQL | Girdi → çıktı |
|---|---|---|---|---|
| `includes/class-flxb-app-review.php:57` | `GET flxb/v1/app/pending` | `flx_gql_any_cap( 'edit_shop_orders', 'flxb_manage' )` | `flxDonationApprovals` | connection → `FlxDonationApprovalConnection!` |
| `class-flxb-app-review.php:70` | `POST …/app/donations/{id}/approve` | aynı + `FLXB_Approval::can` | `flxDonationApprove` | `id` → `{ donation, errors }` |
| `class-flxb-app-review.php:71` | `POST …/reject` | aynı | `flxDonationReject` | `id, reason!, notify` → `{ donation, sent, errors }` |
| `includes/admin/class-flxb-approval.php:22` | `admin_post_flxb_pay_approve` | `class-flxb-approval.php:28` | `flxDonationApprove` | |
| `class-flxb-approval.php:23` | `admin_post_flxb_pay_reject` | aynı | `flxDonationReject` | |
| `includes/wc/class-flxb-wc-admin.php:31` | `admin_post_flxb_approve` (sipariş kutusu) | `edit_shop_orders` | `flxDonationApprove` | |
| `includes/admin/class-flxb-admin.php:55` | `admin_post_flxb_status` | `flxb_manage` | `flxDonationStatus` | `id, status: FlxDonationStatus!` → `{ donation, errors }` |
| `class-flxb-wc-admin.php:33` | `admin_post_flxb_wc_refund` | `flxb_manage` + WC iade yetkisi | `flxDonationRefund` | `id, amount` → `{ donation, errors }` |
| `page-payments.php:21` | `admin_post_flxb_refund` (form ödeme kaydı) | `manage_options` | `flxPaymentRefund` | `id, amount` → `{ payment, errors }` |
| `class-flxb-approval.php` kartı + `page-donations.php:141` | Onay alt görünümü | onay cap'i | `flxDonationApprovals` | Dekont önizleme `?flxb_dekont` istisna |
| `includes/wc/class-flxb-wc-receipts.php:281` | `rest_upload` işi (rota istisna) | owner (sipariş anahtarı) veya `edit_shop_orders` | `flxDonationReceiptAdd` | `orderId, uploadId` → `{ donation, errors }` |

## (d) Ayarlar ve görünüm — 17 GraphQL

Gizli alan yazılır, okunmaz. Boş gönderim saklananı tutar, `"__clear__"` siler (`plugins/flx-ads/includes/graphql/types.php:212`). Okuma `{ set, mask }` döner. Bu bir istisna değildir.

| Kaynak | Ne | cap | GraphQL | Not |
|---|---|---|---|---|
| `page-settings.php:14` | `page=flxb-settings` sekmeler: general, organisation, categories, kurban (takip açıksa), tabs, import, system (Flux Speed yoksa), artı look / woocommerce / lookup | `flxb_manage` | `flxDonateSettings` | Sekmeler tek sorgunun alanları |
| `class-flxb-admin.php:43` | `admin_post_flxb_settings_save` | `flxb_manage` | `flxDonateSettingsSave` | `currency, module_track, module_reports, module_kurban, org, categories, track labels` (`page-settings.php:249`) |
| `class-flxb-wc-look.php:30` | `admin_post_flxb_look_save` | `flxb_manage` | `flxDonateAppearanceSave` | Seçenek `flxb_look` |
| `class-flxb-wc-look.php:349` | `flx_settings_schema` anahtarı `flxb_look` | okuma ayar cap'i | `flxSettings` içinde zaten | Paket şeması aynı alanları `flxDonateAppearance` olarak da sunar (ekran). Gizli alan yok |
| `class-flxb-wc-admin.php:26` | `admin_post_flxb_wc_save` | `flxb_manage` | `flxDonateWooSettingsSave` | `flxb_wc` (`class-flxb-wc-admin.php:224`). Gizli alan yok |
| `class-flxb-wc-admin.php:27` | `admin_post_flxb_wc_setup_page` | `flxb_manage` + `publish_pages` | `flxDonatePageCreate` | Bağış sayfası açar |
| `class-flxb-lookup.php:46` | `admin_post_flxb_lookup_save` | `flxb_manage` | `flxDonateLookupSettingsSave` | `flxb_lookup`: `on, page_id, channels`. Gizli alan yok |
| `class-flxb-admin.php:44` | `admin_post_flxb_backfill` | `flxb_manage` | `flxDonatePaymentImport` | Adım adım (saatlik cron'un elle hali). Uzun iş |
| `page-payments.php:20` | `admin_post_flxb_pay_save` | `manage_options` | `flxDonatePaymentSettingsSave` | Yaz-yalnız gizliler aşağıda |
| `class-flxb-system-checks.php:47` | `admin_post_flxb_syscheck_test` | `flxb_manage` | `flxDonateSystemTest` | Dış HTTP yalnız bu mutation'da |
| `providers/class-flxb-payment-iyzico.php:65` | `iyzicoApiKey` | yaz-yalnız | gizli | maske |
| `class-flxb-payment-iyzico.php:66` | `iyzicoSecretKey` **gizli** | yaz-yalnız | gizli | |
| `providers/class-flxb-payment-stripe.php:43` | `stripeSKey` **gizli** | yaz-yalnız | gizli | |
| `class-flxb-payment-stripe.php:44` | `stripeWebhookSecret` **gizli** | yaz-yalnız | gizli | |
| `class-flxb-payment-stripe.php:42` | `stripePKey` | okunur | `FlxDonatePaymentSettings.publishableKey` | Yayın anahtarı; tarayıcıya gider, gizli değil |
| `includes/wc/class-flxb-wc-esnekpos.php:100` | `esnekpos_password` (merchant key) **gizli** | yaz-yalnız | `flxDonateGatewaySettingsSave` | WC formunda durur; GraphQL düz metin döndürmez |
| `class-flxb-wc-esnekpos-direct.php:89` | `flxb_public_token` **gizli** (adı public, kullanım kimlik) | yaz-yalnız | `flxDonateGatewaySettingsSave` | Taksit oranı |

Kart `cardUserKey` / `cardToken` abonelik metasında mühürlü (`providers/class-flxb-iyzico-recurring.php:112`). Hiçbir sorguda dönmez. GraphQL'de yalnız marka + son 4 (varsa meta'daki etiket).

`flxDonateAppearance` alanları `class-flxb-wc-look.php:350` şemasındaki alanlar (gizli yok): `formStyle, payStyle, primary, accent, radius, order, typeLabel, optionLabel, sharePhone, optView, feeOn, feeChecked, feeRates, checkout, signup, hero, notes, quick, quickStar, periodTabs, otherAmount, minAmount, minAmounts, coupons, stickyBar, cardFirst, addresses`.

## İstisnalar — 29

GraphQL tarafı durum dosyasıyla aynı gerekçe. Ortak listedeki ödeme webhook / Store API satırları burada pakete indirilir.

| Kaynak | Uç | Neden | GraphQL tarafı |
|---|---|---|---|
| `providers/class-flxb-payment-iyzico.php:402` | `POST\|GET flxb/v1/iyzico/callback` | iyzico token POST | `flxDonation` durumu |
| `providers/class-flxb-iyzico-recurring.php:356` | `GET\|POST flxb/v1/iyzico/cancel?s&k` | E-postadaki imzalı link | `flxSubscriptionCancel` (girişli) |
| `providers/class-flxb-iyzico-notify.php:39` | `POST flxb/v1/iyzico/notify` | İşyeri bildirim URL'si, imza | durum sorgusu |
| `includes/wc/class-flxb-wc-iyzico.php:174` | `GET\|POST flxb/v1/wc/iyzico/callback` | Banka dönüşü | durum sorgusu |
| `includes/wc/class-flxb-wc-iyzico-card.php:102` | `POST\|GET flxb/v1/wc/iyzico/3ds` | Banka 3DS POST | durum sorgusu |
| `class-flxb-wc-iyzico-card.php:111` | `POST flxb/v1/wc/iyzico/installments` | Checkout BIN; ödeme GraphQL'de değil | Checkout Store API |
| `includes/wc/class-flxb-wc-esnekpos-direct.php:168` | `GET flxb/v1/esnekpos/installments` | Checkout BIN | Checkout |
| `class-flxb-wc-esnekpos-direct.php:181` | `POST flxb/v1/esnekpos/status` | 3DS penceresi sonucu | Checkout |
| `class-flxb-wc-esnekpos-direct.php:33` | `woocommerce_rest_checkout_process_payment_with_context` | EsnekPOS `PaymentContext`; kart saklanmaz | Checkout `POST /wc/store/v1/checkout` |
| `includes/wc/class-flxb-wc-esnekpos.php:41` | `woocommerce_api_flxb_esnekpos` ve eski `WC_Gateway_Esnekpospos` | EsnekPOS dönüş POST'u (`callback` `:386`) | durum sorgusu |
| `includes/wc/class-flxb-wc-receipts.php:270` | `POST flxb-wc/v1/receipt` | Multipart + sipariş anahtarı, önbellekli sayfa | `flxDonationReceiptAdd(uploadId)` |
| `class-flxb-wc-receipts.php:326` | `?flxb_dekont=` | Dosya baytı | metadata + aynı imzalı URL |
| `providers/payments.php:197` | `POST Emsfb/v1/forms/payment/start` | Flux Forms ödeme kutusu | Form ödemesi flx-forms işi |
| `providers/payments.php:204` | `POST …/confirm` | Aynı | |
| `providers/payments.php:209` | `POST …/webhook/{provider}` | Sağlayıcı imzası | durum sorgusu |
| `providers/payments.php:178` | `…/stripe/card/add` yakalama | Eski Forms adresi | |
| `providers/payments.php:180` | `…/stripe/confirm` | Aynı | |
| `providers/payments.php:181` | `…/stripe/pkey` | Yayın anahtarı bu protokolden | `publishableKey` (d) |
| `providers/payments.php:182` | `…/stripe/webhook` | Stripe imzası | durum sorgusu |
| `includes/admin/class-flxb-admin.php:38` | `admin_post_flxb_csv` | CSV akışı, PHP süresi | `flxDonations` sayfaları |
| `includes/admin/page-reports.php:18` | `admin_post_flxb_report_csv` | Aynı | `flxDonationReport` sayfaları (atanmamış) |
| `includes/admin/page-partners.php:19` | `admin_post_flxb_partner_settle_csv` | Aynı | `flxPartnerSettlement` (atanmamış) |
| `includes/class-flxb-install.php:17` | cron `flxb_sync_payments` | Hostinger WP cron tetiklemez. İş `class-flxb-flxform-bridge.php:645` | `flxDonatePaymentImport` adım adım |
| `providers/class-flxb-iyzico-recurring.php:32` | cron `flxb_iyzico_renewals` (`:48` saatlik) | Aynı | durum `flxSubscriptions`; tahsilat loopback ayrı karar |
| `includes/wc/class-flxb-wc-flxform.php:124` | `?flxb_wc_handoff=` | Tek kullanımlık sepet yönlendirmesi | `flxDonationCartAdd` |
| `includes/wc/class-flxb-wc-feed.php:21` | `/bagis-urunleri.xml` | iyzico/Google XML | `flxDonationTypes` |
| `includes/wc/class-flxb-wc-account.php:15` | Hesabım `bekleyen-bagislar` | WooCommerce hesap şablonu | `flxMyDonations` |
| `includes/flx-system-checks.php:378` | `admin_post_flx_system_checks_dismiss` | Ortak sistem çubuğu | Flux Speed / Admin ekranı |
| `includes/flx-brand/core.php:134` | `[flx_credit]` + blok `flx/credit` (`:149`) | Marka kopyası, bağış verisi değil | Bu paketin şemasına girmez |

Nopriv AJAX yok. `flx_job_kick` (`includes/flx-brand/core.php:878`) tanımlı, Donate işi çağırmıyor.

## Atanmamış — 39 GraphQL

Sahip dörtlüsünde yok. Öneri: beşinci PR, (b) ve (c)'den sonra. Kök adlar bağlayıcı değil, envanter önerisi.

| Kaynak | Ne | cap | GraphQL |
|---|---|---|---|
| `class-flxb-admin.php:137` | `page=flxb-types` liste + düzenleme | `flx_bagis_view` / yazma `flxb_manage` | `flxDonationTypeAdmin` |
| `class-flxb-admin.php:40` | `admin_post_flxb_type_save` | `flxb_manage` | `flxDonationTypeSave` |
| `class-flxb-admin.php:40` | `admin_post_flxb_type_status` | `flxb_manage` | `flxDonationTypeStatus` |
| `class-flxb-admin.php:40` | `admin_post_flxb_type_move` | `flxb_manage` | `flxDonationTypeMove` |
| `class-flxb-admin.php:40` | `admin_post_flxb_type_delete` | `flxb_manage` | `flxDonationTypeDelete` |
| `class-flxb-admin.php:40` | `admin_post_flxb_type_samples` | `flxb_manage` | `flxDonationTypeSamples` |
| `class-flxb-admin.php:134` | `page=flxb-add` | `flxb_manage` veya `flxb_create_ref` | `flxDonationCreate` / `flxDonationRequestCreate` |
| `class-flxb-admin.php:46` | `admin_post_flxb_add_donation` | `flxb_manage` | `flxDonationCreate` |
| `class-flxb-admin.php:47` | `admin_post_flxb_ref_request` | `flxb_create_ref` | `flxDonationRequestCreate` |
| `class-flxb-admin.php:132` | `page=flxb-track` liste + `&item=` | görüntü `flx_bagis_view`, yazma `flxb_track` | `flxTrackItems`, `flxTrackItem` |
| `page-track.php:110` | Filtre `status, place, type, paid, due, s` | `flx_bagis_view` | `flxTrackItems` argümanları |
| `page-track.php:19` | `admin_post_flxb_track_bulk` | `flxb_track` | `flxTrackApply` |
| `page-track.php:19` | `admin_post_flxb_track_step` | `flxb_track` | `flxTrackStep` |
| `page-track.php:19` | `admin_post_flxb_track_edit` | `flxb_track` | `flxTrackUpdate` |
| `page-track.php:19` | `admin_post_flxb_track_file_upload` | `flxb_track` | `flxTrackFileAdd` (`uploadId`) |
| `page-track.php:19` | `admin_post_flxb_track_file_send` | `flxb_track` | `flxTrackFileSend` |
| `page-track.php:19` | `admin_post_flxb_track_file_delete` | `flxb_track` | `flxTrackFileDelete` |
| `page-track.php:19` | `admin_post_flxb_track_note` | `flxb_track` | `flxTrackNote` |
| `class-flxb-admin.php:142` | `page=flxb-partners` sekmeler list / edit / settle | `flx_bagis_view` | `flxPartners`, `flxPartner`, `flxPartnerSettlement` |
| `page-partners.php:19` | `admin_post_flxb_partner_save` | `flxb_manage` | `flxPartnerSave` |
| `page-partners.php:19` | `admin_post_flxb_partner_price_save` | `flxb_manage` | `flxPartnerPriceSave` |
| `page-partners.php:19` | `admin_post_flxb_partner_price_delete` | `flxb_manage` | `flxPartnerPriceDelete` |
| `page-partners.php:19` | `admin_post_flxb_partner_pay_save` | `flxb_manage` | `flxPartnerPaymentSave` |
| `page-partners.php:19` | `admin_post_flxb_partner_pay_delete` | `flxb_manage` | `flxPartnerPaymentDelete` |
| `page-partners.php:19` | `admin_post_flxb_partner_reprice` | `flxb_manage` | `flxPartnerReprice` |
| `class-flxb-admin.php:149` | `page=flxb-reports` görüntü + JSON editör; dönem `from/to` | `flx_bagis_view` / yazma `flxb_manage` | `flxDonationReport` |
| `page-reports.php:18` | `admin_post_flxb_report_save` | `flxb_manage` | `flxDonationReportSave` |
| `page-reports.php:18` | `admin_post_flxb_report_delete` | `flxb_manage` | `flxDonationReportDelete` |
| `class-flxb-admin.php:146` | `page=flxb-import` önizleme | `flxb_manage` | `flxDonationImport` |
| `page-import.php:17` | `admin_post_flxb_import_upload` | `flxb_manage` | `flxDonationImportStart` (`uploadId`) |
| `page-import.php:17` | `admin_post_flxb_import_save` | `flxb_manage` | `flxDonationImportMap` |
| `page-import.php:17` | `admin_post_flxb_import_delete` | `flxb_manage` | `flxDonationImportDelete` |
| `page-import.php:20` | `wp_ajax_flxb_import_step` (12 satır) | `flxb_manage` | `flxDonationImportStep` |
| `class-flxb-admin.php:56` | `admin_post_flxb_cert_remake` | `flxb_manage` | `flxDonationCertificatesRemake` |
| `class-flxb-admin.php:56` | `admin_post_flxb_cert_send` | `flxb_manage` | `flxDonationCertificatesSend` |
| `class-flxb-wc-admin.php:32` | `admin_post_flxb_issue` | `flxb_manage` | `flxDonationCertificatesIssue` |
| `class-flxb-admin.php:58` | `admin_post_flxb_items_save` | `flxb_track` | `flxDonationItemsSave` |
| `includes/class-flxb-detail-tabs.php:26` | `admin_post_flxb_tab_save` | `flxb_manage` | `flxDonateDetailTabsSave` |
| `providers/class-flxb-iyzico-recurring.php:41` | `admin_post_flxb_iyzico_notice` | `flxb_manage` | `flxDonateNoticeDismiss` |

Eski `page=flxb-kurban` `flxb-track`'e yönlenir (`page-track.php:26`). `page=flxf_payments` ve `emsfb_stripe` `flxb-payments`'a (`page-payments.php:28`).

Personel menüsü aynı sayfaların alt kümesi (`class-flxb-admin.php:88`): pano, kendi bağışları, talep, kendi bağışçıları, takip. Ayrı sayfa değil; `owner` / ayrı cap.

## WooCommerce iş kancaları — kök değil

Hepsi serviste kalır. Checkout kancaları Store API'de çalışmaya devam eder (`SEPET.md` §6).

| Kaynak | Kanca | İş | Sahip servis |
|---|---|---|---|
| `class-flxb-wc-cart.php:30` | `woocommerce_before_calculate_totals` | Tutar türden | (a) sepet |
| `class-flxb-wc-cart.php:35` | `woocommerce_checkout_create_order_line_item` | Satır meta, N sertifika adı | (a) |
| `class-flxb-wc-cart.php:44` | `woocommerce_store_api_checkout_update_order_from_request` | Adres | (a) checkout istisna |
| `class-flxb-wc-cart.php:46` | `woocommerce_check_cart_items` | Sepet doğrulama | (a) |
| `class-flxb-wc-contact.php:34` | aynı (öncelik 5) | İletişim | (a) |
| `class-flxb-wc-terms.php:33` | aynı (öncelik 6) | Şart onayı | (a) |
| `class-flxb-wc-checkout-layout.php:26` | aynı (öncelik 4) | Hesap e-postası | (a) |
| `class-flxb-wc-fee.php:22` | `woocommerce_cart_calculate_fees` | İşlem ücreti | (d) görünüm |
| `class-flxb-wc-fee.php:30` | Store API sipariş | Ücret meta | (d) |
| `class-flxb-wc-ledger.php:25` | `woocommerce_order_status_changed` | Kayıt defteri | (b)(c) |
| `class-flxb-wc-ledger.php:26` | `woocommerce_payment_complete` | Kayıt | (c) |
| `class-flxb-wc-ledger.php:27` | `woocommerce_order_refunded` | İade durumu | (c) |
| `class-flxb-wc-ledger.php:30` | `woocommerce_trash_order` / untrash / delete | Çöp birlikte | (b) |
| `class-flxb-wc-certificates.php:25` | `woocommerce_payment_complete` | Sertifika, ad başına | (c) sonrası certificates |
| `class-flxb-wc-recurring.php:26` | `woocommerce_order_status_changed` | Abonelik başlangıcı | (b); tahsilat Commerce |
| `class-flxb-wc-flxform.php:37` | durum + `payment_complete` | Form ödemesi bildirimi | istisna Forms |
| `class-flxb-wc-havale.php:25` | `woocommerce_thankyou_flxb_havale` | IBAN + dekont | (c) |
| `class-flxb-wc-addresses.php:32` | `woocommerce_store_api_checkout_order_processed` | Adres defteri | (d) `addresses` |
| `class-flxb-wc-look.php:37` | `woocommerce_coupons_enabled` | Bağış sepetinde kupon | (d) |
| `class-flxb-wc-look.php:38` | `option_woocommerce_gateway_order` | Kart önce | (d) |
| `class-flxb-wc-currency.php:35` | `woocommerce_currency` | Ön yüz para birimi | (a) |
| `class-flxb-wc-gateways.php:45` | blok ödeme kaydı | Gateway | istisna checkout |
| `class-flxb-wc-esnekpos.php:43` | `template_redirect` order-pay | EsnekPOS sayfasına gidiş | istisna |
| `class-flxb-wc-products.php:25` | gizli ürün yönlendirmesi | Tür sayfası | (a) |
| `includes/wc/gateways/class-flxb-wc-gateway-havale.php:95` | `flxb_wc_preregistered` | Havale siparişi | (c) |
| `class-flxb-wc-receipts.php:132` | `flxb_wc_receipt_uploaded` | Dekont kancası | (c) |
| `class-flxb-wc-certificates.php:172` | `flxb_wc_certificates_issued` | Sertifika kancası | atanmamış / certificates |
| `class-flxb-wc-esnekpos.php:476` | `flxb_wc_esnekpos_paid` | Doğrulanmış ödeme | istisna |

## Dış paketin güvendiği kancalar — imza değişmez

flx-zakat ve flx-qurban Donate 2.11+ ile buna bağlanır. GraphQL bunları silmez; servis aynı `do_action` / `apply_filters` noktasında bırakır.

| Kanca | Kaynak | Ne |
|---|---|---|
| `flxb_create_order()` | `includes/api.php:295` | Sipariş açma (fonksiyon) |
| `flxb_type_public` | `includes/api.php:142` | Public tür şekli |
| `flxb_type_meta_clean` | `includes/class-flxb-types.php:439` | Tür meta |
| `flxb_type_saved` | `class-flxb-types.php:386` | |
| `flxb_wc_line` | `class-flxb-wc-cart.php:344` | Sepet satırı |
| `flxb_takes_free` / `flxb_min_free` | `class-flxb-wc-cart.php:90`, `:102` | Serbest tutar |
| `flxb_donation_recorded` | `class-flxb-donations.php:159` | |
| `flxb_donation_updated` | `class-flxb-donations.php:183` | |
| `flxb_donation_deleted` | `class-flxb-donations.php:449` | |
| `flxb_payment_approved` / `flxb_payment_rejected` | `class-flxb-approval.php:361`, `:406` | |
| `flxb_track_item_changed` | `class-flxb-track.php:422` | 2.11; `flxb_kurban_item_changed` `:421` durur |
| `flxb_track_assign_block` | `class-flxb-track.php:489` | |
| `flxb_track_labels` | `class-flxb-track.php:127` | |
| `flxb_track_places` | `class-flxb-track.php:222` | `flxb_kurban_places` `:221` durur |
| `flxb_track_columns` / `flxb_track_cell` | `page-track.php:178` | `flxb_kurban_columns` `:177` durur |
| `flxb_track_can` | `class-flxb-track.php:52` | |
| `flxb_partner_saved` | `class-flxb-partners.php:165` | |
| `flxb_reports` | `class-flxb-reports.php:271` | |
| `flxb_detail_tabs` | `class-flxb-detail-tabs.php:99` | |
| `flxb_detail_tab_saved` | `class-flxb-detail-tabs.php:264` | |
| `flxb_import_order_args` / `flxb_import_row_done` | `class-flxb-import.php:809`, `:853` | |
| `flxb_import_fields` | `class-flxb-import.php` `fields()` | Sütun takma adları |
| `flx_silent` | `class-flxb-import.php:37` | İçe aktarma sessiz |
| `flxb_wc_certificate_values` | `class-flxb-wc-certificates.php:306` | |
| `flxb_admin_menu` | `class-flxb-admin.php:144` | Zakat menüsü buraya eklenir |
| `flxb_kurban_enabled` | `includes/helpers.php:72` | Eski ad; `flxb_track_enabled` `:77` |

Bağışçıya görünen varsayılan takip metinlerinde "kes-/kesim" yok (`class-flxb-track.php:63`). İçe aktarma sütun takma adları `class-flxb-import.php:193` elektronik tablodaki eski başlıkları tanır; ekrana yazılmaz.

## Tipler (özet)

| Tip | Alanlar | Nullable |
|---|---|---|
| `FlxDonationType` | id, slug, title, category, state, options, progress, currency | `progress` hedefsizse null; `image` yoksa null |
| `FlxDonation` | id, status, amount (`FlxMoney`), donor, lines, certificates, bankAccount | `donor.person` CRM yetkisi yoksa null; `bankAccount` havale değilse null |
| `FlxDonationLine` | option, units, beneficiaries, certificate mode | `beneficiaries` boş olabilir (tek ad bağışçıda) |
| `FlxDonationCertificate` | code, name, url | url sertifika paketi yoksa null |
| `FlxDonor` | id, name, phone, email, totals, person | `person: FlxPerson` — `flxcrm_gql_can_see_contact` |
| `FlxSubscription` | id, status, interval, nextCharge, cardLabel | `cardLabel` kart yoksa null; token yok |
| `FlxPayment` | id, status, amount, providerRef | |
| `FlxDonationApproval` | donation, receipt{url, type, warn} | `receipt` dekont yoksa null |

Enum: `FlxDonationStatus` (`class-flxb-donations.php:16`), `FlxDonationTypeState`, `FlxSubscriptionStatus`. Connection'lar ≤ 50.

`FlxDonor` telefon/e-posta alan düzeyinde `flx_bagis_view` (pano `flxb_dashboard` bu alanları almaz).

## Önbellek

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-donate:types` | `flxDonationTypes`, `flxDonationType` | tür mutation'ları (atanmamış) |
| `flx-donate:donation:{id}` | detay | onay, durum, iade, dekont |
| `flx-donate:donor:{id}` | bağışçı | `flxDonorUpdate`, yeni bağış |
| `flx-donate:settings` | ayar sorguları | (d) kayıt mutation'ları |

Liste sorguları `no-store` (bugün `class-flxb-app-admin.php:48`, lookup `class-flxb-lookup.php:231`). Pano poll edilmez; açılışta bir sorgu.

## Açık ürün soruları

1. Atanmamış dilim (takip, iş ortağı, rapor, Excel, tür yönetimi, personel talebi, sertifika yeniden üretimi) beşinci PR mı, (b)'ye mi? Öneri: beşinci PR. (b) onsuz da bağış listesini bitirir; takip 2.11'in yarısıdır ve kurban/zakat'ı kırar.
2. `flxb_iyzico_renewals` Donate tablosunda kart token'ıyla tahsil ediyor; abonelik ürün kararı Flux Commerce. Bu cron Commerce'e devredilene kadar Donate'te mi kalsın? Öneri: bu geçişte davranış aynı kalsın, tahsilat mutation'ı yazılmasın, cron istisna kalsın. Taşıma ayrı iş.
3. CSV üç yerde akış. React ekranı connection mı kullansın, indirme istisna mı kalsın? Öneri: ekran connection; indirme admin-post istisna (60 sn). Tam dışa aktarma isteği adım adım dosya olarak ayrı karar.
4. Bağışçı detayında `FlxPerson` #333'ten önce null mu, klasik karta link mi? Öneri: alan nullable, #333 merge olunca bölüm kaydı; ekran onsuz da listeyi açar.

## Kodun brief ile farkı

- REST: brief "~16 `flxb/v1`". Kodda `register_rest_route` 31 (flxb/v1 27, `flxb-wc/v1` 1, Donate'in kaydettiği `Emsfb/v1` 3) + 4 Stripe legacy yakalama (`providers/payments.php:178`).
- Admin sayfası: brief "8". Menüde 12 slug (`class-flxb-admin.php:128`): pano, bağışlar, takip, ekle, bağışçılar, abonelik, türler, ödemeler, iş ortakları, içe aktarma, raporlar, ayarlar. Takip, iş ortağı, içe aktarma, raporlar koşullu.
- AJAX: brief "2". Kodda 2 (`flxb_stats`, `flxb_import_step`), nopriv yok. Doğru.
- Abonelik: brief Flux Commerce. Kodda `FLXB_Subscriptions` + `flxb_iyzico_renewals` WooCommerce siparişi açmadan tahsil eder (`class-flxb-iyzico-recurring.php:9`). WC aboneliği `class-flxb-wc-recurring.php`.
- "kes-/kesim": bağışçı metninde yok. Yalnız içe aktarma takma adları (`class-flxb-import.php:193`).
- PHPStan: `FLXB_WC_READY` `includes/wc/class-flxb-wc.php:181`. Strict bootstrap yalnız ana dosya ve `includes/*.php` görür. Sonraki PR `tools/phpstan/packages/flx-donate.bootstrap.php` ister; bu turda yazılmaz.

## Servisler (sonraki PR'lar, bu turda yok)

- (a) `FLXB_Type_Public_Service`, `FLXB_Cart_Service`, `FLXB_My_Donations_Service`, `FLXB_Lookup_Service`
- (b) `FLXB_Donation_Query_Service`, `FLXB_Donor_Service`, `FLXB_Subscription_Query_Service`, `FLXB_Payment_Query_Service`, `FLXB_Dashboard_Service`
- (c) `FLXB_Approval_Service`, `FLXB_Receipt_Service`, `FLXB_Refund_Service`
- (d) `FLXB_Settings_Service`, `FLXB_Appearance_Service`, `FLXB_Woo_Settings_Service`, `FLXB_Payment_Settings_Service`
- atanmamış: `FLXB_Type_Admin_Service`, `FLXB_Track_Service`, `FLXB_Partner_Service`, `FLXB_Report_Service`, `FLXB_Import_Service`

PR sırası: (a) → (b) → (c) → (d) → atanmamış. Aynı `plugins/flx-donate/graphql/schema.graphql` olduğu için paralel merge çakışır. (a) `FlxDonationType` ve sepet alanının sahibi. (b) `FlxDonation` / `FlxDonor` / `FlxPerson`. (c) (b)'nin düğümünü döner. (d) ayar tipleri, (a)'nın PHP görünümüne muhtaç değil. (b) kişi kartı bölümü #333'e muhtaç; liste değil.
