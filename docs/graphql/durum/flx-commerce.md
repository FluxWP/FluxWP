# flx-commerce 0.6.0

- Faz: 1
- Durum: 👀
- PR: #339

`gql/flx-commerce-1`: servis katmanı, GraphQL şeması (30 kayıtlı işlem), sözleşme testi, beş yönetim ekranı (`flxcm-store`). wp-admin varsayılanı React; klasik görünüm kullanıcı metası `flxcm_ui` ile. Eşleme `docs/graphql/ESLEME/flx-commerce.md`. Müşteri alanı `FlxContact.flxCommerce`; CRM bölüm kaydı PR #333 gelince aynı alana bağlanacak.

İnceleme düzeltmeleri: React "Klasik görünüm" bağlantısı kaçışsız adres kullanır; havale ayarı kaydı WooCommerce ağ geçidi Ayarlar API'sinden geçer (WooCommerce yokken `INVALID_INPUT`); Hesabım bildirim türleri, sahiplik kümesi (`FLXCM_Sub_Account::mine()`), `from_input` Flux Donate korumaları ve dekont sayfa sorgusu klasik davranışa döndü; özet yenilemesi 60 sn.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Dekont yükleme `POST /wp-json/flx-commerce/v1/receipt` ve form POST `flxcm_action=receipt` | flx-commerce | Multipart; misafir sipariş anahtarıyla yükler | Liste `flxCommerceReceipts`, onay `flxCommerceReceiptApprove` |
| Dekont dosyası `GET /?flxcm_dekont=&i=` | flx-commerce | İkili dosya. `serve()` yalnız `edit_shop_orders` (onay yetkisi ayrıca `manage_woocommerce`) | `files.url` yalnız `edit_shop_orders` olana dolu |
| Ürün feed'i `GET /?flxcm_feed=google&key=` | flx-commerce | Google Merchant ve Meta katalog RSS XML bekler | `feedUrl` tam adres, yalnız `manage_woocommerce`; feed kapalıyken boş |
| `flxcm_subs_tick`, `flxcm_daily`, `shutdown` pompası | flx-commerce | Saatlik tick, günlük `flush_dirty` / `cancel_stale`, GET sonu pompası | `flxCommerceRenewalsRun` yalnız `tick()` |
| Shortcode `[flx_credit]` ve blok `flx/credit` | flx-commerce | Ortak FluxWP imzası | — |
| Havale teşekkür / Hesabım sipariş / sipariş e-postası ve `flxcm_bank` | flx-commerce | Checkout Store API'de kalır | Onay `flxCommerceReceiptApprove` |
| Yenileme ödeme adresi | flx-commerce | WooCommerce ödeme sayfası | `flxMySubscriptionRenew` adresi döndürür; sayfa WC'de |
| Gizli ürün `template_redirect`, `_flxcm_hidden`, `_flxcm_no_feed`, sepet `validate` / `individually` | flx-commerce | WooCommerce kancaları | Ayar `hiddenRedirect`; sepet köprüsü adet 1 |
| WP pano widget'ı `flxcm_today` | flx-commerce | Pano HTML'i | `flxCommerceSummary(days: 1)` |
| FluxApp manifest REST'i ve CSV admin-post | flx-commerce | Eski istemci ve dosya indirme | REST cevabı aynı; CSV admin-post |
