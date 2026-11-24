# flx-donate 2.43.0

- Faz: 2
- Durum: 👀
- PR: #340 (bu PR'ye katıldı), Donate ekranları PR'si

2.37.0: Pano ve Bağışlar React ekranı (`packages/modules/src/donate`, modül `flxb-donate`), Flux GraphQL kabuğu + preload. Admin GraphQL: `flxDonationDashboard`, `flxDonationList` (connection, `flx_gql_paginate`), `flxDonationListInfo`, `flxDonationsBulk`; servis `FLXB_Admin_Donations_Service`. Yetki: liste `can_list` (own-only personel yalnız kendi `ref_user_id`'si, filtre genişletemez), pano `can_dashboard`, toplu işlem `!own_only && flxb_can_manage`, kalıcı silme `manage_options`. Liste filtresi URL'de klasik anahtarlarla (s, from, to, status, type, dtype, form, donor_id, ref, paged); PHP preload filtresi `FLXB_Admin::flux_list_filter()` = TS `filterFromQuery()`. Bağış detayı (`?donation=`) ve diğer sayfalar hâlâ klasik (sıradaki PR).

2.38.0: Bağış detayı React ekranı (`Detail.tsx`, startPage `detail?id=N`, preload `FlxDonateDetail {id}`). Query `flxDonationDetail(id)` (cap `can_list`, görünmeyen bağış null), servis `FLXB_Admin_Detail_Service`. Mutation'lar: SetStatus, SetReferral, SaveNames, Approve, Reject, Certificates (REMAKE/SEND), Refund, NoteAdd, NoteDelete, LayoutSave; çöp/geri al/sil `flxDonationsBulk`. Başka eklentilerin kutuları "ada": PHP sayfayla gizli basar (`#flxdn-islands`, WC kutuları + kayıt formu, `flxb_detail_boxes`, Flux POS iade formu, Detail_Tabs), GraphQL yalnız id/başlık/sütun verir, React DOM düğümünü kutusuna taşır; bu sayfada klasik `admin.css/admin.js` yüklü kalır. Klasik `detail()` kaldırıldı; admin-post işleyicileri (durum, sertifika, referans, adlar) geriye uyum için duruyor. Sıradaki: bağışçılar, abonelikler, türler, ortaklar, takip, içe aktarma, raporlar, ayarlar.

2.39.0: Bağışçılar (`Donors.tsx`, start `donors`, adres s/kind/orderby/paged), bağışçı kartı (`Donor.tsx`, startPage `donor?id=N`) ve düzenli bağışlar (`Subscriptions.tsx`, start `subscriptions`, adres s/status/paged) React ekranı. Servis `FLXB_Admin_People_Service`; query `flxDonorList` (connection), `flxDonorListInfo`, `flxDonor(id)` (görünmeyen bağışçı null), `flxSubscriptionList` (connection), `flxSubscriptionTotals`; mutation `flxDonorSave`, `flxSubscriptionCancel`. Yetki: bağışçılar `FLXB_Refs::can_donors` (flxb_donors_own personeli yalnız kendi referans olduğu bağışçılar, o bağışların rakamları; kartta düzenli bağış/sertifika/not yok), kayıt `flxb_can_manage`, düzenli bağışlar `flxb_can_view`, iptal `flxb_owns_payments && flxb_can_manage`. Flux CRM kişi kartı varken `?donor=` hâlâ CRM kartı (`FLXB_Page_Donors::crm_page()` ekranı kapatır; CRM taşınınca kalkar). `FLXB_Donors::query` / `FLXB_Subscriptions::query` `offset` alır (kit limit+1 ister).

2.40.0: Bağış türleri listesi (`Types.tsx`, start `types`, adres `view=archived`; klasik form silme bildirimi `notice=<ok|error>:<metin>` ile startPage'e geçer). Servis `FLXB_Admin_Types_Service`; query `flxDonationTypeAdminList(archived)`; mutation `flxDonationTypeSetStatus` (açma çakışması CONFLICT + `FLXB_Types::$notices`), `flxDonationTypeMove(up)`, `flxDonationTypeSamples(key)`. Yetki: liste `flxb_can_view`, diğerleri `flxb_can_manage`. Klasik status/move/samples admin-post işleyicileri silindi.

2.41.0: Tür formu (`TypeForm.tsx`, startPage `type?id=N` (0 = yeni), preload `FlxDonateTypeForm {id}`). Query `flxDonationTypeForm(id)` (yoksa null; seçim listeleri dahil), mutation `flxDonationTypeSave` (tipli alanlar + `extra`: adaların klasik form gövdesi `meta[…]`, `form_map[…]`; yeni tür / bağlı form / seçenek / para birimi değişince sayfa yeniden yüklenir ki adalar güncellensin), `flxDonationTypeDelete` (bağışı olan CONFLICT). Adalar: form eşleştirme (`FLXB_Page_Types::mapping`), çeviriler (`i18n_card`), `flxb_type_fields` eklenti kartları, kısa kod önizlemesi; `FLXB_Admin_Types_Service::want_islands/print_islands`, uygulamada (GraphQL dışı sayfa) yok. Klasik form ve admin-post işleyicileri silindi; `relink` servise taşındı.

2.42.0: Bağış ekle (`Add.tsx`, start `add`, startPage `add?donor=N`, preload `FlxDonateAdd {donorId}`). Servis `FLXB_Admin_Add_Service` (klasik `FLXB_Page_Add::save/save_order/save_request` buraya taşındı); query `flxDonationAddForm(donorId)` (yönetici formu ya da `request: true` personel talep formu; türler, seçenekler, hesaplar, referanslar, para birimleri, vekâlet metni sürümü, bağışçı ön dolgusu), mutation `flxDonationAdd` (INVALID_INPUT hataları), `flxDonationRequest` (ödeme bağlantısı + WhatsApp bağlantısı döner). Yetki: form `FLXB_Refs::can_create`, kayıt `flxb_can_manage`, talep `can_create` (referans çağıran). Telefon alanları flx-brand telefon bileşeni (`flx_phone_field` işaretlemesi, phone.js; numara gizli alandan E.164). Kayıt sonrası `flxb-donations&donation=ID&flxb_msg=saved` → detay startPage'ine `notice=ok:…`. Klasik form, admin.js ekleme kodu ve admin-post işleyicileri silindi.

2.43.0: Ortaklar (`Partners.tsx`; start `partners`, `settle?kind=&period=&partner=`, `partner?id=N` (0 = yeni); adres klasik `tab`, `kind`, `month` / `from`+`to`, `partner`, `id` anahtarlarıyla; PHP `FLXB_Admin::partner_start()` = preload). Servis `FLXB_Admin_Partners_Service`; query `flxDonationPartners`, `flxDonationPartnerSettlement(kind, period, partnerId)` (period boş = bu ay; ay kartları, ortak satırları, seçili ortağın kayıtları, CSV bağlantısı), `flxDonationPartner(id)` (yoksa null); mutation `flxDonationPartnerSave`, `PriceAdd`, `PriceToggle`, `PriceDelete`, `PaymentAdd`, `PaymentDelete`, `Reprice`. Yetki: okuma `flxb_can_view`, yazma `flxb_can_manage`. CSV admin-post `flxb_partner_settle_csv` kaldı; diğer klasik işleyiciler ve sayfa silindi.

(a) public bu PR'de: `flxDonationTypes`, `flxDonationType`, `flxDonationCartAdd`, `flxMyDonations`, `flxDonationLookupStart` / `Verify` / `End`, `flxDonationBankAccount`. Eşleme `ESLEME/flx-donate.md`: 102 GraphQL, 32 istisna. (a) 16, (b) 24, (c) 11, (d) 17, (e) 34. Sıra (a) → (b) → (c) → (d) → (e). Tek `schema.graphql`. React ekranı yok.

Kararlar: (e) takip, iş ortağı, rapor, Excel, tür, talep; detay sayfasının kalem adı, sertifika ve sekmeleri (b). `flxb_iyzico_renewals` aynı kalır, tahsilat mutation'ı yok, cron istisna; Forms siparişsiz, WC checkout `flxb-renewal` siparişi açar. CSV ekranı connection, indirme admin-post. `FlxPerson` #333'ten önce nullable.

`flxb_donors_own`: toplam ve tarihler `own_figures`, bağışlar `ref_user_id`, abonelik/sertifika/onay formu yok. İki personel testi (b)'de.

IBAN: `flxDonationBankAccount` `flxb_bank_account()` çağırır. Havale sınıfı yüklüyse birinci kaynak `woocommerce_flxb_havale_settings`; boşsa BACS. Sahip kuralı tek kaynak BACS diyordu; kod iki kaynak. Havale alanları (d).

Gizli yazma (d): sahip onayladı. Yaz-yalnız girdi; boş saklananı tutar, `"__clear__"` siler; değer dönmez; okuma `set`, varsa maske, `fromConstant`. Kalıp flx-ads ve flx-social. `flxb_public_token` EsnekPOS kimliği (`class-flxb-wc-esnekpos-direct.php:89`), hiç dönmez. (a) değişmez.

Lookup (a): HttpOnly çerez `flxb_lk` (adı `flxb_lookup` değil), kod 10 dk, oturum 30 dk, oran `flx_rate_limit('flxb_lk|…')`. `flxMyDonations` WP oturumu veya bu çerez; `no-store` (reddi de: `cap` içinde çağrılır). Sorgulama kapalıysa (`on`) çerez oturumu sayılmaz, `Verify` reddeder.

İnceleme düzeltmeleri (PR #340): `flxDonationCartAdd` klasik iletişim kapısı, `remember_contact` ve ücret tercihi; para birimi hatası; Store API adet filtresi klasik `fixed_quantity` koşuluyla; `flx-donate:types` temizliği; flx-admin testi Flux Donate açıkken (`wp-args` silindi, #345).

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
