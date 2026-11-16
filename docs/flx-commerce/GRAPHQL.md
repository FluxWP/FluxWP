# Flux Commerce GraphQL API

Uç: `/graphql` (Flux GraphQL). Eklenti `flx-graphql` yokken şema kaydı atlanır; REST aynı kalır.

Yetkiler mevcut WooCommerce yetkileridir (yeni yetki yok):

- Okuma (`flxCommerceSummary`, müşteri, abonelik, rapor): `view_woocommerce_reports` veya `manage_woocommerce`
- Ayarlar ve mağaza yazmaları: `manage_woocommerce`
- Dekont listesi ve onay: `edit_shop_orders` veya `manage_woocommerce`
- Hesabım abonelikleri: `read`, artı satırın sahibi (kullanıcı ya da e-posta)
- `flxCommerceSubscriptionOffer` ve `flxSubscriptionCartAdd`: herkese açık. Ürün sayfası zaten misafire dönemi seçtirir. Ekleme `FLXGQL_Cart::add_item()` üzerinden gider; ödeme WooCommerce Store API'de kalır.

İşlemler `plugins/flx-commerce/graphql/operations/*.graphql` (30 işlem). Tarihler `FlxDateTime` (`flx_gql_datetime` / `flx_date`), paralar `FlxMoney`.

## Okuma

```graphql
query {
  flxCommerceSummary(days: 30) {
    orders
    revenue { formatted }
    awaiting
  }
  flxCommerceCustomers(first: 25, segment: REPEAT) {
    nodes { id name email guest orders }
  }
  flxCommerceSettings {
    feed
    feedUrl
    cardProviders
    whatsappReady
    freeShipThreshold { formatted }
    donateCart
    donateHavale
    orderStatuses { slug label }
  }
}
```

`days` 1–90 arasındadır. `feedUrl` klasik ayar ekranının mağaza yöneticisine gösterdiği tam adrestir (feed kapalıyken boş; okuma anahtar üretmez). `manage_woocommerce` yoksa ayar sorgusu `FORBIDDEN` döner; adres başkasına açılmaz.

`FlxCommerceCustomer` CRM olmasa da kişi kartının yerine geçer: `userId`, `guest`, açık siparişler, adresler, çok alınan ürünler, zaman çizelgesi. `FlxContact.flxCommerce` aynı özeti, `view` yetkisiyle ve kişi yoksa `null` olarak ekler. Flux CRM bölüm kaydı (`gql/flx-crm-2`, PR #333) geldiğinde bu alan aynı kayda bağlanacak; kayıt bu sürümde yok.

## Yazma

| Mutation | Yetki | Not |
|---|---|---|
| `flxCommerceReceiptApprove` | dekont | Onay. Bekleyen değilse `CONFLICT` |
| `flxCommerceCustomerNote` | yönet | 2000 karakter üstü payload `INVALID_INPUT`; yoksa `NOT_FOUND` |
| `flxCommerceCustomersRebuild` | yönet | 100 siparişlik bir sayfa |
| `flxCommerceSubscriptionRenew` / `Cancel` / `End` / `Resume` | yönet | |
| `flxCommerceSettingsSave` | yönet | Eksik anahtar durur. `feed_key` kabul edilmez |
| `flxCommerceBankSettingsSave` | yönet | WooCommerce havale ayarı |
| `flxSubscriptionCartAdd` | herkese açık | Abonelik ürününde satır tektir, adet 1 |
| `flxMySubscriptionCancel` / `Resume` / `Renew` / `ForgetCard` | `read` + sahip | Başkasının satırı `FORBIDDEN` |
| `flxCommerceRenewalsRun` | yönet | Yalnız `FLXCM_Subscriptions::tick()`. `flush_dirty` ve `cancel_stale` günlük işte kalır |

`tick()` kendi kilidini (`flxcm_subs_lock`, 5 dakika) ve dönem başına tek yenileme siparişi korumasını (`renewal_order_id`, `_renewal_for`, çekim öncesi `_flxb_iyz_uncertain`, `_flxcm_renewal_done`) kullanır. Mutation ikinci bir koruma eklemez.

`INVALID_INPUT` ve `CONFLICT` payload `errors` içindedir. `NOT_FOUND`, `FORBIDDEN` ve `UNAUTHENTICATED` üst düzey hatadır.

## Kart bloğu

Sağlayıcının `d` alanı abonelik metasında `_card` altında durur. iyzico sağlayıcısında bu `{ k, t }` şeklindedir: `flx_secret_seal` ile mühürlenmiş kart kullanıcı anahtarı ve kart token'ı. PAN, CVV ve son kullanma tarihi yoktur. Marka ve son dört hane `label` olarak `d` nin yanında durur. `export()` bloğu siler. GraphQL, REST, günlük ve dışa aktarma `d` yi taşımaz. Depolama bu sürümde değişmedi.

## Bilerek duran uçlar

- Misafir dekont yükleme: `POST /wp-json/flx-commerce/v1/receipt` ve form `flxcm_action=receipt` (multipart).
- Dekont dosyası: `GET /?flxcm_dekont=`. `FLXCM_Receipts::serve()` yalnız `edit_shop_orders` (veya sipariş anahtarı) kabul eder. Onay yetkisi ayrıca `manage_woocommerce` içerir. GraphQL dosya adresini yalnız dosyayı açabilene verir; yalnız `manage_woocommerce` olan rol listede dekontu görür, `files.url` boştur. `serve()` nin `manage_woocommerce` kabul edip etmeyeceği ürün kararıdır; klasik davranış değiştirilmedi.
- Ürün feed XML'i, checkout, havale ödeme sayfası, yenileme ödeme adresi, `[flx_credit]`, gizli ürün yönlendirmesi, WP pano widget'ı, saatlik cron ve `shutdown` pompası.
- CSV indirme (`flxcm_customers_csv`, `flxcm_report_csv`) admin-post olarak kalır.
- Ürün metaları WooCommerce kancasında kalır: `_flxcm_hidden`, `_flxcm_no_feed`, sepette aynı abonelik satırını değiştiren `validate`, adedi 1 yapan `individually`.

`docs/graphql/ISTISNALAR.md` flx-commerce için bir webhook/3DS satırı listeler. Bu pakette o kod yok; paylaşılan dosya bu PR'da değiştirilmedi.
