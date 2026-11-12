# Flux Ads API (0.1.0)

Önek `flxads_` / `FLXADS_`. Yetkiler `flxads_view` (rapor), `flxads_manage` (ayar). Tablolar `{prefix}flxads_orders` (siparişin atfı, sipariş başına bir satır) ve `{prefix}flxads_conversions` (platform × sipariş gönderim kuyruğu, `UNIQUE(platform, event_id)`).

## Akış

1. Ön yüz `assets/front.js`: çerez onayı (FluxWP tema banner'ı `flx_cerez=kabul:<rev>`, WP Consent API `wp_has_consent('marketing')` ya da ayar "always") gelince `flxads_ok=1` ve URL'de UTM/tıklama kimliği varsa `flxads_t={f:ilk,l:son}` çerezi yazılır, açık pixel'ler yüklenir, sayfanın olayları gönderilir.
2. Ödeme adımı (`woocommerce_checkout_order_created`, `woocommerce_store_api_checkout_order_processed`, ön yüzde `woocommerce_new_order`): çerezler + `_fbp/_fbc/_ttp/_ga` siparişe `_flxads` meta olarak yazılır.
3. Ödendi (`woocommerce_payment_complete`, `processing`, `completed`): `flxads_orders` satırı, açık platformlara `Purchase` kuyruğu (onay yoksa `skipped/no_consent`), eylem `flxads_order_attributed`.
4. Gönderim: istek sonunda (`shutdown`, `fastcgi_finish_request` sonrası), saatlik `flxads_retry` ve Flux Ads ekranı açılınca. 6 deneme, artan bekleme.

Olay kimliği `order-<sipariş id>` (tarayıcı `eventID` / `event_id` ile aynı). GA4 sunucu olayı 30 dk bekler, teşekkür sayfası `purchase` gönderdiyse (`_flxads_browser`) atlanır.

## Fonksiyonlar

- `flxads_track( $name, $data )`: bu sayfada tarayıcıdan olay (ör. `Lead`). `$data`: `id`, `value`, `currency`, `items[{id,name,price,qty}]`, `title`.
- `flxads_channel( $touch )`: `meta|google|tiktok|microsoft|other|''`.
- `FLXADS_Report::grouped( $by, $filters )`: `$by` = `channel|campaign|content|source|landing|first`; filtre `from`, `to` (Y-m-d), `kind` (`all|donations|orders`), `channel`, `campaign`.
- `FLXADS_Orders::attribution( $order )`: `[ son dokunuş, ilk dokunuş, _flxads ]`.

## Filtre / eylem

- `flxads_default_settings`, `flxads_channel`, `flxads_is_donation`, `flxads_browser_events`, `flxads_front_active`.
- `flxads_order_attributed( $order_id, $row, $order )`.

## Flux CRM

- Olay türü `ad_conversion` (tutar 0: ömür boyu değer iki kez artmasın), kaynak `ads`, `uniq` `ads:order:<id>`.
- Kişi kartı yan sütunu `flxcrm_person_card_side` → "Reklam kaynağı".
- Kişide `utm_source/utm_campaign` boşsa ilk dokunuşla doldurulur. `flx_person_merged` atıf satırlarını taşır.

## MCP

`flxads_report` (by, from, to, kind, channel), `flxads_status`. Gizli anahtar ve kişisel veri dönmez.
