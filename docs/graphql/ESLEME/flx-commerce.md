# Flux Commerce (flx-commerce): REST → GraphQL eşlemesi

Paket sürümü: 0.5.0 (uygulama 0.6.0) · Önek: `flxcm_` · Dal: `gql/flx-commerce-1` · Durum: uygulanıyor

Envanter koddan. `flx_settings_schema`, `flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools` ve `wp_ajax_nopriv_*` kaydı yok. Toplu işlem (bulk) yok.

## Özet

| Kategori | Satır | GraphQL | İstisna | Kanca kalır |
|---|---:|---:|---:|---:|
| REST | 7 | 6 | 1 | 0 |
| AJAX (priv; nopriv 0) | 2 | 2 | 0 | 0 |
| admin-post | 8 | 8 | 0 | 0 |
| Admin menü sayfası | 5 | 5 | 0 | 0 |
| Alt görünüm / filtre | 10 | 10 | 0 | 0 |
| CRM kart bölümü | 3 | 3 | 0 | 0 |
| WP pano widget | 1 | 0 | 1 | 0 |
| MCP / AI aracı | 0 | 0 | 0 | 0 |
| Ayar alanı | 31 | 31 | 0 | 0 |
| Cron / pompa | 4 | 0 | 3 | 1 |
| Webhook / ödeme callback | 0 | 0 | 0 | 0 |
| Shortcode / blok / public akış | 12 | 4 | 8 | 0 |
| WooCommerce iş kancası | 13 | 4 | 2 | 7 |

Aynı akış iki tabloda görünebilir (düğme ve onu karşılayan admin-post). Satır sayıları tablo başınadır.

- İstisna: `docs/graphql/durum/flx-commerce.md`. Ortak `ISTISNALAR.md` satırına dokunulmaz. Bu pakette banka webhook'u veya 3DS dönüşü yok (ortak listedeki "flx-commerce" webhook satırının kod karşılığı yok; iyzico Flux Donate).
- Servisler (sonraki PR, bu dosyada yok): `FLXCM_Stats_Service`, `FLXCM_Customers_Service`, `FLXCM_Subscriptions_Service`, `FLXCM_Receipts_Service`, `FLXCM_Settings_Service`, `FLXCM_Account_Service`, `FLXCM_Cart_Service`.
- Node tipleri: `FlxCommerceCustomer`, `FlxCommerceSubscription`, `FlxCommerceReceipt`, `FlxCommerceSummary`, `FlxCommerceReport`.
- Feed adresi karar B: `feedUrl` düz `String`, yalnız `manage_woocommerce` (ayar sorgusu). Feed kapalıyken klasik ekran adresi göstermez; GraphQL de boş döner. Anahtar ayrı alan değildir; kitte ortak `FlxSecret` tipi yok. Kart bloğu `meta._card.d` GraphQL, REST, log ve `export()` dışına çıkmaz; dışarıda yalnız `label` (marka + son 4).
- Kişi: `FlxCommerceCustomer.person` nullable `FlxContact`. Görünürlük `flxcrm_gql_can_see_contact`. Bölüm kaydı `origin/gql/flx-crm-2` (`0ace6abf`) üzerinde henüz yok; `docs/flx-crm/GRAPHQL.md` kancaların PHP kaldığını yazar.

## Yetki

Bugünkü kapılar. Yeni `flxcm_*` yetkisi açılmaz; mevcut WooCommerce yetkileri `flx_gql_capabilities` kataloğuna etiketle yazılır. `useFlxCan` bu üç anahtarı kullanır.

| Kapı | Kim | Nerede |
|---|---|---|
| Görüntüleme | `flx_gql_any_cap( 'view_woocommerce_reports', 'manage_woocommerce' )` | `FLXCM_REST::can_view` `includes/class-flxcm-rest.php:27`, menü `FLXCM_Admin::CAP_VIEW` `includes/admin/class-flxcm-admin.php:19` |
| Yönetim | `manage_woocommerce` | `FLXCM_Admin::CAP_MANAGE` `includes/admin/class-flxcm-admin.php:20` |
| Havale onayı | `flx_gql_any_cap( 'edit_shop_orders', 'manage_woocommerce' )` | REST `can_approve` `includes/class-flxcm-rest.php:31`. admin-post `flxcm_confirm` yalnız `edit_shop_orders` bakar (`includes/class-flxcm-bank.php:335`); GraphQL REST kuralını kullanır |
| Hesabım | `read` + servis sahiplik kontrolü | `includes/class-flxcm-sub-account.php:191` |
| Sepete ekleme, ürün fiyatı | `public` | Ürün sayfası zaten herkese açık (`includes/class-flxcm-sub-product.php:136`) |

## REST uçları

NS `flx-commerce/v1`. FluxApp manifesti bu yolları okur (`includes/app-manifest.php:65`); REST cevapları aynı kalır (eski uygulama, ortak geçici istisna).

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Dosya |
|---|---|---|---|---|---|---|
| GET | `/summary` | görüntüleme | `flxCommerceSummary` | `range: FlxCommerceRange` (`days` 1–365, `includes/class-flxcm-rest.php:84`) | `FlxCommerceSummary!` | `includes/class-flxcm-rest.php:41` |
| GET | `/customers` | görüntüleme | `flxCommerceCustomers` | `search, segment, orderBy, first ≤ 50, after` (REST `per_page` en çok 100, `includes/class-flxcm-rest.php:39`) | `FlxCommerceCustomerConnection!` | `includes/class-flxcm-rest.php:42` |
| GET | `/customers/{id}` | görüntüleme | `flxCommerceCustomer` | `id: ID!` | `FlxCommerceCustomer` (yoksa `NOT_FOUND`) | `includes/class-flxcm-rest.php:43` |
| GET | `/receipts` | onay | `flxCommerceReceipts` | `first ≤ 50, after` | `FlxCommerceReceiptConnection!` | `includes/class-flxcm-rest.php:44` |
| GET | `/receipts/{id}` | onay | `flxCommerceReceipt` | `id: ID!` | `FlxCommerceReceipt` | `includes/class-flxcm-rest.php:45` |
| POST | `/receipts/{id}/approve` | onay | `flxCommerceReceiptApprove` | `id: ID!` | `{ receipt, errors }` (`409` → `CONFLICT`, `includes/class-flxcm-rest.php:214`) | `includes/class-flxcm-rest.php:46` |
| POST | `/receipt` | sipariş anahtarı, `permission_callback` `__return_true` | İSTİSNA | `order`, `key`, dosya `dekont` | — | `includes/class-flxcm-receipts.php:157` |

`/receipt` istisna: multipart, misafir, önbellekli sayfa, yetki sipariş anahtarı (`includes/class-flxcm-receipts.php:162`). Onay ve liste GraphQL'de.

## AJAX

Nopriv kaydı yok. Nonce `flxcm_admin` (`includes/admin/class-flxcm-admin.php:77`). İstemci `assets/admin.js:22` ve `:38`.

| Action | cap / nonce | → GraphQL | Girdi | Çıktı | Dosya |
|---|---|---|---|---|---|
| `flxcm_rebuild` (priv) | `manage_woocommerce` / `flxcm_admin` | `flxCommerceCustomersRebuild` | `page: Int!` (100'lük adım, `FLXCM_Customers::BATCH` `includes/class-flxcm-customers.php:15`) | `{ done, page, scanned, total, errors }` | `includes/class-flxcm-customers.php:24` |
| `flxcm_note` (priv) | `manage_woocommerce` / `flxcm_admin` | `flxCommerceCustomerNote` | `id: ID!`, `note: String!` (en çok 2000, `:312`) | `{ customer, errors }` | `includes/class-flxcm-customers.php:25` |

## admin-post

| Action | cap / nonce | → GraphQL | Girdi | Çıktı | Dosya |
|---|---|---|---|---|---|
| `flxcm_save` | `manage_woocommerce` / `flxcm_save` | `flxCommerceSettingsSave` | `FlxCommerceSettingsInput` | `{ settings, errors }` | `includes/admin/class-flxcm-admin.php:28` |
| `flxcm_report_csv` | görüntüleme / `flxcm_report_csv` | `flxCommerceReport` + `flxCommerceReportDays` / `Products` / `Categories` / `Methods` | `from`, `to` | React dosyayı sorgudan üretir; klasik admin-post kalır | `includes/admin/class-flxcm-admin.php:29` |
| `flxcm_customers_csv` | `manage_woocommerce` / `flxcm_customers_csv` | `flxCommerceCustomers` | `segment`, `search` | aynı | `includes/class-flxcm-customers.php:26` |
| `flxcm_confirm` | `edit_shop_orders` / `flxcm_confirm_{id}` | `flxCommerceReceiptApprove` | `id` | `{ receipt, errors }` | `includes/class-flxcm-bank.php:28` |
| `flxcm_sub` `do=renewal` | `manage_woocommerce` / `flxcm_sub_renewal_{id}` | `flxCommerceSubscriptionRenew` | `id: ID!` | `{ subscription, order, errors }` | `includes/admin/page-subscriptions.php:149` |
| `flxcm_sub` `do=cancel` | aynı kalıp | `flxCommerceSubscriptionCancel` | `id: ID!` | `{ subscription, errors }` (dönem sonu) | `includes/admin/page-subscriptions.php:155` |
| `flxcm_sub` `do=end` | aynı kalıp | `flxCommerceSubscriptionEnd` | `id: ID!` | `{ subscription, errors }` (hemen biter) | `includes/admin/page-subscriptions.php:158` |
| `flxcm_sub` `do=resume` | aynı kalıp | `flxCommerceSubscriptionResume` | `id: ID!` | `{ subscription, errors }` | `includes/admin/page-subscriptions.php:161` |

`flxcm_sub` kaydı `includes/admin/page-subscriptions.php:13`.

## Admin sayfaları

Sekmeler ayrı sayfa değil; `FLXCM_Admin::tabs()` beş menü slug'ını basar (`includes/admin/class-flxcm-admin.php:122`). Ayarlar `manage_woocommerce` ister (`:130`).

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı | Dosya |
|---|---|---|---|---|
| `flx-commerce` | Genel bakış: ciro, sipariş, ortalama, müşteri, günlük grafik, bekleyen ödeme, çok satanlar, düşük stok, ödeme yöntemleri. WC kısayolları (sipariş, ürün, kupon, kargo) WC admin'de kalır | `flxCommerceSummary` | `packages/modules/src/commerce` `flxcm-overview` | `includes/admin/class-flxcm-admin.php:43` |
| `flx-commerce-customers` | Müşteri listesi, KPI, CSV, yeniden tara | `flxCommerceCustomers`, `flxCommerceCustomerCounts` | `flxcm-customers` | `includes/admin/class-flxcm-admin.php:45` |
| `flx-commerce-subscriptions` | Abonelik listesi, KPI, satır işlemleri | `flxCommerceSubscriptions`, `flxCommerceSubscriptionCounts` | `flxcm-subscriptions` | `includes/admin/class-flxcm-admin.php:46` |
| `flx-commerce-reports` | Tarih aralığı, özet, grafik, ürün / kategori / yöntem, CSV | `flxCommerceReport` ve kırılım connection'ları | `flxcm-reports` | `includes/admin/class-flxcm-admin.php:47` |
| `flx-commerce-settings` | Altı bölüm, tek kayıt (aşağıda) | `flxCommerceSettings`, `flxCommerceSettingsSave` | `flxcm-settings` | `includes/admin/class-flxcm-admin.php:48` |

### Alt görünüm, filtre, satır işlemi

Toplu işlem yok. Satır düğmeleri admin-post tablosundaki dört mutation.

| Kontrol | Sayfa | → GraphQL | Dosya |
|---|---|---|---|
| Dönem `days` = 1 / 7 / 30 / 90 | Genel bakış | `flxCommerceSummary(range:)` enum `TODAY`, `DAYS_7`, `DAYS_30`, `DAYS_90` | `includes/admin/page-dashboard.php:16` |
| Segment `all` / `new` / `repeat` / `vip` / `lapsed` | Müşteriler | `segment: FlxCommerceCustomerSegment` | `includes/admin/page-customers.php:55` |
| Arama `s` (ad, e-posta, telefon, şehir) | Müşteriler | `search: String` | `includes/admin/page-customers.php:67` |
| Sıra `name` / `orders` / `total` / `last_at` + `asc`/`desc` | Müşteriler | `orderBy: FlxCommerceCustomerOrder` | `includes/admin/page-customers.php:77` |
| `?customer=` veya `?email=` müşteri kartı | Müşteriler | `flxCommerceCustomer`; CRM varsa `person` + `FlxContact.flxCommerce` | `includes/admin/page-customers.php:17` |
| CRM kartı (`flxcrm_person_card`) yoksa yedek sayfa | Müşteri | Aynı sorgu. Zaman çizelgesi bu pakette | `includes/admin/page-customer.php:46`, `:207` |
| Zaman çizelgesi `tl` = tümü / sipariş / ödeme / not | Yedek müşteri | `timeline(filter:)` connection, `first ≤ 50` | `includes/admin/page-customer.php:198` |
| Durum `all` / `active` / `past_due` / `cancelled` / `pending` / `expired` | Abonelikler | `status: FlxCommerceSubscriptionStatus` | `includes/admin/page-subscriptions.php:59` |
| Arama `s` (ad, e-posta, ürün, no) | Abonelikler | `search: String` | `includes/admin/page-subscriptions.php:72` |
| Hazır aralık + `from`/`to` | Rapor | `FlxCommerceReportPreset` + `from`/`to` (`Date`) | `includes/admin/page-reports.php:51`, `:64` |

CRM kartına eklenen bölümler (PHP kanca, kayıt gelene kadar alan olarak planlanır):

| Kanca | Bölüm | Dosya |
|---|---|---|
| `flxcrm_person_card_actions` | "Orders" düğmesi | `includes/admin/page-customer.php:23`, `:137` |
| `flxcrm_person_card_main` | Son 10 sipariş | `includes/admin/page-customer.php:24`, `:145` |
| `flxcrm_person_card_side` | Mağaza özeti, not, adresler, sık ürünler | `includes/admin/page-customer.php:25`, `:169` |

### WP pano widget

| Widget | → | Dosya |
|---|---|---|
| `flxcm_today` ("Store · today") | İSTİSNA: WP panosunda sunucu HTML. Veri `flxCommerceSummary(range: TODAY)` | `includes/admin/class-flxcm-admin.php:88` |

## MCP / AI araçları

Yok. `flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools` ve başka bir araç kaydı bu pakette yok.

## Ayarlar

`flx_settings_schema` yok. Tek seçenek `flxcm_settings` (`FLXCM_OPTION`, `includes/helpers.php:10`). Okuma/yazma `flxCommerceSettings` / `flxCommerceSettingsSave`, cap yönetim. Boş gizli alan eskisini korur, `"__clear__"` siler (`plugins/flx-ads/includes/graphql/types.php` ile aynı kural).

| Grup | Alan | Tip | GraphQL | Gizli | Dosya |
|---|---|---|---|---|---|
| Panel | `paid_statuses` | durum listesi | `paidStatuses: [FlxCommerceOrderStatus!]!` | hayır | `includes/helpers.php:22` |
| Panel | `exclude_donations` | bool (yalnız `FLXB_WC_Cart` varken formda) | `excludeDonations: Boolean!` | hayır | `includes/helpers.php:23` |
| Panel | `low_stock` | int 0–9999 | `lowStock: Int!` | hayır | `includes/helpers.php:24` |
| Ödeme | `hide_company` | bool | `hideCompany: Boolean!` | hayır | `includes/helpers.php:26` |
| Ödeme | `hide_address_2` | bool | `hideAddress2: Boolean!` | hayır | `includes/helpers.php:27` |
| Ödeme | `virtual_no_address` | bool | `virtualNoAddress: Boolean!` | hayır | `includes/helpers.php:28` |
| Ödeme | `order_notes` | bool | `orderNotes: Boolean!` | hayır | `includes/helpers.php:29` |
| Ödeme | `sticky_pay` | bool | `stickyPay: Boolean!` | hayır | `includes/helpers.php:30` |
| Kargo | `free_ship_bar` | bool | `freeShipBar: Boolean!` | hayır | `includes/helpers.php:32` |
| Kargo | `free_ship_amount` | decimal | `freeShipAmount: Float!` | hayır | `includes/helpers.php:33` |
| Havale | `receipt_max_mb` | int 1–20 | `receiptMaxMb: Int!` | hayır | `includes/helpers.php:35` |
| Havale | `receipt_notify` | e-posta | `receiptNotify: String!` | hayır | `includes/helpers.php:36` |
| Havale | `defer_to_donate` | bool (yalnız `FLXB_WC_Havale` varken) | `deferToDonate: Boolean!` | hayır | `includes/helpers.php:37` |
| Havale | `cancel_after_days` | int 0–60 | `cancelAfterDays: Int!` | hayır | `includes/helpers.php:38` |
| Feed | `feed` | bool | `feed: Boolean!` | hayır | `includes/helpers.php:40` |
| Feed | `feed_brand` | metin | `feedBrand: String!` | hayır | `includes/helpers.php:41` |
| Feed | `feed_key` | 20 karakter, otomatik; formda yazılmaz | `feedUrl: String!` tam adres, feed açıkken, yalnız `manage_woocommerce`. Anahtarın kendi alanı yok | hayır (adın kendisi gizli değil; sorgu yönetim yetkisi) | `includes/helpers.php:42`, `includes/class-flxcm-feed.php:28`, ekran `includes/admin/page-settings.php:107` |
| Bildirim | `notify_customer_wa` | bool | `notifyCustomerWa: Boolean!` | hayır | `includes/helpers.php:44` |
| Bildirim | `notify_admin_phone` | telefon | `notifyAdminPhone: String!` | hayır | `includes/helpers.php:45` |
| Bildirim | `notify_admin_order` | bool | `notifyAdminOrder: Boolean!` | hayır | `includes/helpers.php:46` |
| Abonelik | `sub_renew_days` | int 0–60 | `subRenewDays: Int!` | hayır | `includes/helpers.php:48` |
| Abonelik | `sub_grace_days` | int 0–60 | `subGraceDays: Int!` | hayır | `includes/helpers.php:49` |
| Abonelik | `hidden_redirect` | URL | `hiddenRedirect: String!` | hayır | `includes/helpers.php:50` |

Havale yöntemi WooCommerce › Ödemeler ekranında, seçenek `woocommerce_flxcm_bank_settings`. Klasik Mağaza › Ayarlar yalnız oraya link verir (`includes/admin/page-settings.php:83`). React aynı linki korur; alanlar yine `flxCommerceBankSettings` / `flxCommerceBankSettingsSave` ile okunup yazılır (cap yönetim). IBAN müşteriye gösterilir, gizli alan değildir.

| Alan | Tip | GraphQL | Gizli | Dosya |
|---|---|---|---|---|
| `enabled` | checkbox | `enabled: Boolean!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:28` |
| `title` | text | `title: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:34` |
| `description` | textarea | `description: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:41` |
| `holder` | text | `holder: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:48` |
| `iban` | text | `iban: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:54` |
| `bank` | text | `bank: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:60` |
| `ref_format` | text | `refFormat: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:65` |
| `ref_prefix` | text | `refPrefix: String!` | hayır | `includes/gateways/class-flxcm-gateway-bank.php:72` |

Klasik ayar ekranının salt okunur / ortama bağlı verisi de `FlxCommerceSettings` üzerindedir (cap yönetim). Yazma girdisinde yoktur:

| Veri | GraphQL | Dosya |
|---|---|---|
| Seçilebilir sipariş durumları (pending, cancelled, failed, checkout-draft, refunded hariç) | `orderStatuses: [FlxCommerceOrderStatus!]!` `{ slug, label }` | `includes/admin/page-settings.php:47` |
| Kart sağlayıcı etiketleri (çağrılabilir `card` / `charge` yok) | `cardProviders: [String!]!` | `includes/class-flxcm-sub-cards.php:35`, ekran `:97` |
| WhatsApp hazır mı | `whatsappReady: Boolean!` | `includes/class-flxcm-notify.php:28`, ekran `:115` |
| Ücretsiz kargo eşiği (ayar ya da WC yöntemi) | `freeShipThreshold: FlxMoney!` | `includes/class-flxcm-checkout.php:95`, ekran `:69` |
| Flux Donate sepeti var mı | `donateCart: Boolean!` | `class_exists( 'FLXB_WC_Cart' )`, ekran `:55` |
| Flux Donate havalesi var mı | `donateHavale: Boolean!` | `class_exists( 'FLXB_WC_Havale' )`, ekran `:86` |

## Cron ve pompa

Hostinger'da WP cron tetiklenmez. Saatlik iş, GET isteklerinin `shutdown`'ında saatte bir kez de döner (`includes/class-flxcm-subscriptions.php:749`). Adımlar sınırlı: yenileme 50, kart çekimi 25 (`:695`, `includes/class-flxcm-sub-cards.php:201`).

| Tetik | Ne yapar | → | Dosya |
|---|---|---|---|
| `flxcm_subs_tick` saatlik | Yenileme siparişi, son gün hatırlatması, kart çekimi, ek süre, bitiş | İSTİSNA (cron + shutdown pompası durur). Durum `flxCommerceRenewalStatus` cap görüntüleme. `flxCommerceRenewalsRun` cap `manage_woocommerce`, girdi boş, çıktı `{ status { ran, at } }`. Yalnız `FLXCM_Subscriptions::tick()` (`:682`): 5 dk kilit, dönem başına `renewal_order_id` / `_renewal_for`. `flush_dirty` ve `cancel_stale` bu mutation'da yok | `includes/class-flxcm-subscriptions.php:76` |
| `flxcm_daily` günlük | Aynı `tick` (`:73`) + müşteri `flush_dirty` (`includes/class-flxcm-customers.php:23`) + dekontsuz havale iptali (`includes/class-flxcm-bank.php:35`) | İSTİSNA. Günlük iş GraphQL'e taşınmaz | `includes/class-flxcm-install.php:51` |
| `shutdown` pompası | Cron yoksa `tick()` | İSTİSNA | `includes/class-flxcm-subscriptions.php:74` |
| `shutdown` `flush_dirty` | Kirli müşteri satırlarını yazar | Servis; ayrı uç yok. Yazma mutation'ları aynı flush'ı çağırır | `includes/class-flxcm-customers.php:48` |

Kapatmada iki cron silinir (`includes/class-flxcm-install.php:65`).

## Webhook ve ödeme callback

Yok. iyzico bu pakette yok. Kart çekimi, Donate'in `flxcm_card_providers` süzgecine kaydettiği `charge` çağrısıdır (`includes/class-flxcm-sub-cards.php:12`, `:152`). Dönüş URL'si yok. Kart meta'sı `p`, `d` (sağlayıcının mühürlü verisi), `label`, `gateway` (`:97`). `export()` `d` alanını dışarı vermez (`includes/class-flxcm-subscriptions.php:111`).

## Shortcode, blok, public akış

| Akış | → GraphQL | Girdi / çıktı | Dosya |
|---|---|---|---|
| Shortcode `[flx_credit]` | İSTİSNA: ortak FluxWP imzası (`tools/brand`), mağaza verisi değil | — | `includes/flx-brand/core.php:134` |
| Blok `flx/credit` | İSTİSNA: aynı imza | — | `includes/flx-brand/core.php:149` |
| Hesabım `subscriptions` uç noktası | `flxMySubscriptions` | cap `read`, yalnız kendi satırları → `FlxMySubscriptionConnection!` | `includes/class-flxcm-sub-account.php:15` |
| Hesabım `cancel` / `resume` / `renew` / `forget_card` | `flxMySubscriptionCancel`, `Resume`, `Renew`, `ForgetCard` | cap `read` + sahiplik (`:200`) → `{ subscription, errors }`; `renew` ödeme adresine gider | `includes/class-flxcm-sub-account.php:191` |
| Yenileme ödeme adresi | İSTİSNA: WC ödeme sayfası veya havale teşekkür sayfası, sipariş anahtarı | — | `includes/class-flxcm-subscriptions.php:672` |
| Form POST `flxcm_action=receipt` | İSTİSNA: JS'siz dekont yükleme, sipariş anahtarı | — | `includes/class-flxcm-receipts.php:19` |
| `?flxcm_dekont=&i=` dosya | İSTİSNA: ikili yanıt. Yönetici yolu nonce + yalnız `edit_shop_orders` (`includes/class-flxcm-receipts.php:220`); sipariş anahtarı sahibi de açar. Onay yetkisi ise `edit_shop_orders \|\| manage_woocommerce` (`includes/class-flxcm-rest.php:31`). Yalnız `manage_woocommerce` olan rol listeyi görür, dosyada 404 alır. Klasik `serve()` bu PR'da değişmez. GraphQL `files.url` yalnız `edit_shop_orders` varsa dolu | `includes/class-flxcm-receipts.php:211` |
| `?flxcm_feed=google&key=` | İSTİSNA: Merchant / Meta RSS XML bekler | Ayar `feed` / `feedBrand`; adres `feedUrl` (düz metin, yönetim) | `includes/class-flxcm-feed.php:41` |
| Teşekkür, Hesabım sipariş, sipariş e-postası havale paneli | İSTİSNA: checkout Store API'de kalır; HTML talimat | Onay `flxCommerceReceiptApprove` | `includes/class-flxcm-bank.php:22`, `:23`, `:24` |
| Ürün sayfası dönem seçici | `flxSubscriptionCartAdd` | `productId`, `period: MONTH\|YEAR` → `{ cart, itemKey, errors }`, cap `public` | `includes/class-flxcm-sub-product.php:19`, `:282` |
| Gizli ürün `template_redirect` | İSTİSNA: tarayıcı yönlendirmesi | Adres ayarı `hiddenRedirect` | `includes/class-flxcm-hidden-product.php:19` |
| Ücretsiz kargo çubuğu | `FlxCart.flxFreeShipping` | `{ threshold, remaining, enabled }`, cap `public` | `includes/class-flxcm-checkout.php:23` |

Sepete ekleme bugün `WC()->cart->add_to_cart` (`includes/class-flxcm-sub-product.php:293`). GraphQL yolu `FLXGQL_Cart::add_item( $id, 1, [], array( 'flxcm_sub' => array( 'period' => $period ) ) )` olur. Satır türü `flx_gql_cart_item_kind` → `subscription`. Alan `FlxCartItem.flxSubscription`. Checkout `woocommerce_store_api_checkout_order_processed` üzerinde kalır (`includes/class-flxcm-subscriptions.php:69`, `docs/graphql/SEPET.md` §6).

`flx_job_kick` (`includes/flx-brand/core.php:876`) ortak imza yardımcısıdır; bu paket onu çağırmaz ve AJAX action kaydetmez.

## WooCommerce iş kancaları

Görüntü filtresi (fiyat HTML, sütun, meta kutusu) bu tabloda yok.

| Kanca | Ne yapar | → | Dosya |
|---|---|---|---|
| `woocommerce_checkout_order_processed`, `woocommerce_store_api_checkout_order_processed` | Siparişten abonelik (`pending`) | İSTİSNA: checkout Store API. Servis `from_order` | `includes/class-flxcm-subscriptions.php:68` |
| `woocommerce_payment_gateways` | `flxcm_bank` yöntemini ekler | İSTİSNA: WC gateway, Store API | `includes/class-flxcm-bank.php:21` |
| `woocommerce_add_cart_item_data`, `woocommerce_get_cart_item_from_session`, `woocommerce_before_calculate_totals` | Dönem fiyatı, satır `flxcm_sub` | `flxSubscriptionCartAdd` + `flx_gql_cart_item_kind`. Aynı üründen tek satır: `validate` (`:157`) eskisini çıkarır. Adet 1: `individually` (`:226`) ve `woocommerce_store_api_product_quantity_editable` → false (`docs/graphql/SEPET.md` §6) | `includes/class-flxcm-sub-product.php:21` |
| `woocommerce_product_options_general_product_data` (gizli ürün), katalog / site haritası | `_flxcm_hidden` | Kanca kalır. Yönlendirme istisna | `includes/class-flxcm-hidden-product.php:17` |
| Ürün meta `_flxcm_no_feed` | Feed'den çıkar (düzenleme kutusu yok; meta okunur) | Kanca / meta kalır. Feed istisna | `includes/class-flxcm-feed.php:80` |
| `woocommerce_product_options_general_product_data`, `woocommerce_admin_process_product_object` | `_flxcm_sub`, `_flxcm_sub_month`, `_flxcm_sub_year` | `Product.flxSubscription` okuma cap `public`. Yazma WC ürün ekranında kalır | `includes/class-flxcm-sub-product.php:16` |
| `woocommerce_get_query_vars`, `woocommerce_account_subscriptions_endpoint` | Hesabım listesi | `flxMySubscriptions` | `includes/class-flxcm-sub-account.php:15` |
| `woocommerce_order_action_flxcm_confirm` | Sipariş ekranından havale onayı | `flxCommerceReceiptApprove` | `includes/class-flxcm-bank.php:30` |
| `woocommerce_order_status_changed`, `woocommerce_payment_complete` | Aboneliği açar / dönemi uzatır | Kanca kalır, servisi çağırır | `includes/class-flxcm-subscriptions.php:70` |
| `woocommerce_checkout_create_order_line_item` | Satıra `_flxcm_period` yazar | Kanca kalır | `includes/class-flxcm-sub-product.php:27` |
| `woocommerce_checkout_registration_required`, `woocommerce_checkout_registration_enabled` | Abonelik sepetinde hesap zorunlu | Kanca kalır (checkout) | `includes/class-flxcm-sub-product.php:31` |
| `woocommerce_order_status_changed`, `woocommerce_order_refunded`, `woocommerce_new_order` | Müşteri özeti ve istatistik | Kanca kalır, servisi çağırır | `includes/class-flxcm-customers.php:21`, `includes/class-flxcm-stats.php:17` |
| `woocommerce_checkout_fields` ve ücretsiz kargo eşiği | Alan gizleme, kargo çubuğu | Kanca kalır. Ayar GraphQL'de; çubuk verisi `FlxCart.flxFreeShipping` | `includes/class-flxcm-checkout.php:18` |

## Tipler

| Tip | Alanlar (kısa) | Nullable ve nedeni |
|---|---|---|
| `FlxCommerceSummary` | `revenue`, `orders`, `aov`, `customers`, `newCustomers`, `series` (en çok 90 gün), `awaiting`, `lowStock` | `*Delta` önceki dönem 0 ise null. Uzun seri `flxCommerceReportDays` |
| `FlxCommerceCustomer` | `id`, `name`, `email`, `phone`, `city`, `userId`, `guest`, `orders`, `openOrders`, `total`, `note`, `firstAt`, `lastAt`, `addresses`, `products`, `recentOrders`, `timeline(filter)`, `person` | CRM yokken yedek sayfa bu alanlardan kurulur. `person: FlxContact` CRM yoksa veya `flxcrm_gql_can_see_contact` false ise null |
| `FlxCommerceCustomerCounts` | `all`, `repeat`, `vip`, `lapsed`, `new`, `spent`, `ltv`, `built`, `builtAt` | `built` false ise klasik "eski siparişler taranmadı" uyarısı. `builtAt` hiç taranmadıysa null |
| `FlxCommerceSubscription` | `id`, `status`, `period`, `amount`, `name`, `email`, `productId`, `productName`, `productUrl`, `parentOrderId`, `lastOrderId`, `renewalOrderId`, `renewals`, `startedAt`, `periodEnd`, `endedAt`, `cardLabel`, `autoCharge`, `nextChargeAt`, `renewNow`, `payUrl`, `lastOrderUrl` | `cardLabel` boş string, kart yoksa. `nextChargeAt` / `payUrl` otomatik çekim veya açık ödeme yoksa null. Token yok. Yönetici düzenleme adresi yalnız `edit_shop_orders` |
| `FlxCommerceSubscriptionCounts` | `all`, `pending`, `active`, `pastDue`, `cancelled`, `expired`, `mrr`, `due` | — |
| `FlxCommerceReceipt` | `id`, `number`, `name`, `email`, `total`, `reference`, `status`, `files { name url }` | `url` yalnız `edit_shop_orders` varsa dolu (dosyayı açamayan yöneticiye boş) |
| `FlxCommerceReport` | toplamlar. Gün / ürün / kategori / yöntem ayrı connection (`first ≤ 50`) | Satış yoksa connection boş, toplamlar 0 |
| `FlxCommerceSettings` | kayıtlı alanlar + `feedUrl` + yukarıdaki salt okunur altılı | `feedUrl` feed kapalıyken `""`. `FlxSecret` yok |
| `FlxCommerceBankSettings` | 8 gateway alanı | IBAN boşsa yöntem kasada görünmez |
| `FlxCommerceRenewalStatus` | `ran`, `at` | `at` tick hiç çalışmadıysa null. Sorgu cap görüntüleme. `flxCommerceRenewalsRun` cap yönetim, girdi yok, çıktı `status` |
| `flxMySubscriptions` | `[FlxCommerceSubscription!]!` (ayrı tip yok; Hesabım alanları aynı tipte) | cap `read`, yalnız erişimi açık satırlar |
| `flxCommerceSubscriptionOffer` | `productId`, `name`, `month`, `year` | Bu şemada `Product` tipi yok. Ay veya yıl yoksa o para alanı null. cap `public` |
| `FlxCartItem.flxSubscription` | `period`, `amount` | Abonelik satırı değilse null |
| `FlxCart.flxFreeShipping` | `enabled`, `threshold`, `remaining`, `reached` | — |
| `FlxContact.flxCommerce` | `FlxCommerceCustomer` | Kişi mağaza müşterisi değilse null. Cap görüntüleme. CRM bölüm kaydı (PR #333) gelince aynı alan bölüme bağlanır |

Connection'lar `first` en çok 50. Raporun günlük serisi sayfalanır; ekran bir yılı parça parça ister (karmaşıklık sınırı 1000).

Hatalar: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `INVALID_INPUT`, `CONFLICT`. Mutation'da `INVALID_INPUT` ve `CONFLICT` payload `errors`.

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-commerce:summary` | `flxCommerceSummary` | ayar, onay, sipariş kancası (`FLXCM_Stats::bump`) |
| `flx-commerce:customers` | liste ve sayılar | not, rebuild adımı, sipariş kancası |
| `flx-commerce:customer:{id}` | `flxCommerceCustomer` | not, sipariş |
| `flx-commerce:subscriptions` | liste ve sayılar | yenile / iptal / bitir / sürdür, tick |
| `flx-commerce:subscription:{id}` | tek abonelik, Hesabım | aynı yazmalar |
| `flx-commerce:receipts` | dekont listesi | onay, yükleme kancası |
| `flx-commerce:settings` | ayarlar | `flxCommerceSettingsSave`, banka ayarı |

Okuma `flx_gql_cache_tag`. Yazma `flx_gql_purge`. Dekont listesi canlı izlenmez; sekme gizliyken duran bir yoklama gerekirse aralık en az 10 sn (manifest bugün 60 sn, `includes/app-manifest.php:69`).

## Kararlar

Dört ürün sorusu kapandı. Sahibin ayrıca bakacağı uyuşmazlık aşağıda.

1. Feed adresi B. `manage_woocommerce` tam adresi görür (`feedUrl`). Başkası ayar sorgusunu hiç okuyamaz. Alan adı anahtar/token içermez.
2. Müşteri kartı C. `FlxContact.flxCommerce` bu PR'da, cap görüntüleme, nullable. CRM bölüm kaydı `gql/flx-crm-2` (PR #333) gelince aynı alan bölüme bağlanır. CRM yokken yedek sayfa `FlxCommerceCustomer` üzerindedir.
3. Hesabım A. GraphQL bu PR'da. WooCommerce hesap sayfası aynı servisi çağırır; HTML değişmez.
4. Misafir dekont A. `POST /receipt` ve form POST kalıcı istisna (multipart).

Dekont dosyası: `serve()` yalnız `edit_shop_orders` (veya sipariş anahtarı). Onay listesi `edit_shop_orders || manage_woocommerce`. Klasik davranış bu PR'da değişmez; GraphQL dosya adresini yalnız dosyayı açabilene verir. Sahip `serve()`'in `manage_woocommerce` kabul edip etmeyeceğine karar verecek.

`d` içinde PAN, CVV veya son kullanma yoktur. iyzico sağlayıcısı mühürlü `k` (cardUserKey) ve `t` (cardToken) yazar; `label` marka ve son 4'tür ve `d`'nin dışındadır. Depolama bu PR'da değişmez.
