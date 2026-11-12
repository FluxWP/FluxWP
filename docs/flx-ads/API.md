# Flux Ads API (0.2.0)

Önek `flxads_` / `FLXADS_`. Yetkiler `flxads_view` (rapor), `flxads_manage` (ayar). Tablolar `{prefix}flxads_orders` (siparişin atfı, sipariş başına bir satır) ve `{prefix}flxads_conversions` (platform × sipariş gönderim kuyruğu, `UNIQUE(platform, event_id)`) ve `{prefix}flxads_spend` (günlük reklam harcaması: `platform`, `day`, `ad_key`, `campaign_id`, `campaign`, `adset`, `ad`, `spend`, `currency`, `impressions`, `clicks`, `manual`; `UNIQUE(platform, day, ad_key)`).

Ödenen her sipariş (bağış ya da mağaza) dönüşümdür; rapor ve MCP `kind` ile ayırır.

## Akış

1. Ön yüz `assets/front.js`: çerez onayı (FluxWP tema banner'ı `flx_cerez=kabul:<rev>`, WP Consent API `wp_has_consent('marketing')` ya da ayar "always") gelince `flxads_ok=1` ve URL'de UTM/tıklama kimliği varsa `flxads_t={f:ilk,l:son}` çerezi yazılır, açık pixel'ler yüklenir, sayfanın olayları gönderilir.
2. Ödeme adımı (`woocommerce_checkout_order_created`, `woocommerce_store_api_checkout_order_processed`, ön yüzde `woocommerce_new_order`): çerezler + `_fbp/_fbc/_ttp/_ga` siparişe `_flxads` meta olarak yazılır.
3. Ödendi (`woocommerce_payment_complete`, `processing`, `completed`): `flxads_orders` satırı, açık platformlara `Purchase` kuyruğu (onay yoksa `skipped/no_consent`), eylem `flxads_order_attributed`.
4. Gönderim: istek sonunda (`shutdown`, `fastcgi_finish_request` sonrası), saatlik `flxads_retry` ve Flux Ads ekranı açılınca. 6 deneme, artan bekleme.

Olay kimliği `order-<sipariş id>` (tarayıcı `eventID` / `event_id` ile aynı). GA4 sunucu olayı 30 dk bekler, teşekkür sayfası `purchase` gönderdiyse (`_flxads_browser`) atlanır.

## Harcama (0.2.0)

- Meta: `GET /v21.0/act_<hesap>/insights?level=ad&time_increment=1`, sayfalı (en çok 20 sayfa, 40 sn). İlk eşitleme son 30 gün, sonra son 7 gün; başarılı okumada aralıktaki eski API satırları silinip yazılır. Anahtar `meta_ads_token` (ads_read), boşsa `meta_token`; wp-config `FLXADS_META_ADS_TOKEN`.
- Ne zaman: saatlik `flxads_retry` ve Flux Ads ekranı açılınca (6 saatten eskiyse, yanıt gönderildikten sonra). Durum `flxads_spend_sync` seçeneğinde.
- Elle harcama: Harcama sekmesi, `manual = 1`, kanal + gün + kampanya + isteğe bağlı reklam. Tutar "1.200,50" / "1.200" / "1200.5" okunur.
- Eşleştirme: kampanya `FLXADS_Spend::norm()` ile (`Fidan Kasım 2026` = `fidan-kasim-2026`) ya da utm_campaign = Meta kampanya kimliği; reklam utm_content = reklam adı; kanal platform. Harcaması olup ödemesi olmayan kampanya da satır olur (ROAS 0).
- ROAS paydası: harcaması bilinen kanalların geliri.

## Fonksiyonlar

- `flxads_track( $name, $data )`: bu sayfada tarayıcıdan olay (ör. `Lead`). `$data`: `id`, `value`, `currency`, `items[{id,name,price,qty}]`, `title`.
- `flxads_channel( $touch )`: `meta|google|tiktok|microsoft|other|''`.
- `FLXADS_Report::grouped( $by, $filters )`: `$by` = `channel|campaign|content|source|landing|first`; filtre `from`, `to` (Y-m-d), `kind` (`all|donations|orders`), `channel`, `campaign`. Harcama varsa dönüşte `spend` true, satırlarda `spend`, `roas`, `cpa`; toplamlarda `spend`, `spend_income`, `roas`, `cpa`, `cpn` (yeni bağışçı başı).
- `FLXADS_Spend::sync_meta( $days = 0 )`, `FLXADS_Spend::totals( $from, $to, $channel )`, `FLXADS_Spend::add_manual( $in )`.
- `FLXADS_Orders::attribution( $order )`: `[ son dokunuş, ilk dokunuş, _flxads ]`.

## Filtre / eylem

- `flxads_default_settings`, `flxads_channel`, `flxads_is_donation`, `flxads_browser_events`, `flxads_front_active`.
- `flxads_order_attributed( $order_id, $row, $order )`.

## Flux CRM

- Olay türü `ad_conversion` (tutar 0: ömür boyu değer iki kez artmasın), kaynak `ads`, `uniq` `ads:order:<id>`.
- Kişi kartı yan sütunu `flxcrm_person_card_side` → "Reklam kaynağı".
- Kişide `utm_source/utm_campaign` boşsa ilk dokunuşla doldurulur. `flx_person_merged` atıf satırlarını taşır.

## MCP

`flxads_report` (by, from, to, kind, channel; harcama, ROAS, maliyet dahil), `flxads_status` (harcama eşitleme durumu dahil). Gizli anahtar ve kişisel veri dönmez.
