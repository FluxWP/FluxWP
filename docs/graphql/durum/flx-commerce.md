# flx-commerce 0.5.0

- Faz: 2
- Durum: ✱
- PR: #339

Envanter (`gql/flx-commerce-1`). Servis, şema ve ekran yok. Eşleme `docs/graphql/ESLEME/flx-commerce.md`. REST 7 (1 istisna), AJAX 2, admin-post 8, menü 5, ayar 31 (`feed_key` gizli). Kişi kartı `FlxContact` alanı olarak planlandı; `origin/gql/flx-crm-2` bölüm kaydı henüz yok. Sepete ekleme GraphQL'de `FLXGQL_Cart::add_item`. Checkout Store API'de kalır.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Dekont yükleme `POST /wp-json/flx-commerce/v1/receipt` ve form POST `flxcm_action=receipt` | flx-commerce | Multipart; misafir sipariş anahtarıyla yükler, sayfa önbellekli olabilir | Liste `flxCommerceReceipts`, onay `flxCommerceReceiptApprove` |
| Dekont dosyası `GET /?flxcm_dekont=&i=` | flx-commerce | İkili dosya; yönetici nonce veya sipariş anahtarı | Liste `files.url` bu adresi verir |
| Ürün feed'i `GET /?flxcm_feed=google&key=` | flx-commerce | Google Merchant ve Meta katalog RSS XML bekler | `feedEnabled` / `feedBrand`; `feed_key` yalnız set/mask |
| `flxcm_subs_tick` (saatlik), `flxcm_daily` (günlük), `shutdown` pompası | flx-commerce | Hostinger WP cron tetiklemez; pompa GET sonunda saatte bir döner. PHP ~60 sn, adım 50/25 | `flxCommerceRenewalStatus`, tek adım `flxCommerceRenewalsRun` |
| Shortcode `[flx_credit]` ve blok `flx/credit` | flx-commerce | Ortak FluxWP imzası (footer), mağaza verisi değil | — |
| Havale teşekkür / Hesabım sipariş / sipariş e-postası paneli ve `flxcm_bank` gateway | flx-commerce | Checkout WooCommerce Store API'de kalır; HTML ödeme talimatıdır | Onay `flxCommerceReceiptApprove` |
| Yenileme ödeme adresi (`pay_url`: WC ödeme veya teşekkür) | flx-commerce | WooCommerce ödeme sayfası, sipariş anahtarı | `flxMySubscriptionRenew` siparişi açar; adres WC'de kalır |
| Gizli ürün `template_redirect` | flx-commerce | Herkese açık ürün adresini tarayıcıda yönlendirir | Ayar `hiddenRedirect` |
| WP pano widget'ı `flxcm_today` | flx-commerce | Pano HTML'i sunucuda basılır | `flxCommerceSummary(range: TODAY)` |
| FluxApp manifest kaynakları (`summary`, `customers`, `receipts`) | flx-commerce | Eski FluxApp REST okur (ortak geçici istisna) | Aynı servisler; REST cevabı değişmez |
