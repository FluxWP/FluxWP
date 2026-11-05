# Flux Analytics (flx-analytics) — API

Önek `flxan_`, sınıflar `FLXAN_*`, text domain `flx-analytics`, yetki `flxan_view` (ayarlar `manage_options`).

## PHP

| Fonksiyon | Açıklama |
|---|---|
| `flxan_track_event( $name, $param = '', $amount = 0, $uniq = '', $ref = '' )` | Sunucu tarafı olay. `$name` `a-z0-9_-` (≤40). `$uniq` (ör. `order-123`) aynı olayın günlük tekilliği için. `$ref` referans kodu. |
| `flxan_ref_names() / flxan_ref_label( $code ) / flxan_ref_save( $code, $name|null )` | Referans kodu → ad (seçenek `flxan_ref_names`). |
| `flxan_funnels() / flxan_funnels_from_text() / flxan_funnels_to_text() / flxan_funnels_sanitize()` | Huniler (seçenek `flxan_funnels`; boşsa varsayılan bağış hunisi). |
| `FLXAN_Journeys::report( $s, $e, $ps, $pe, $opts )` | Yolculuk raporu (summary, flow, paths). |
| `FLXAN_Stats::set_filter( $seg, $rc )` | Sonraki sorgular için süzgeç: seg `all|guest|members|<rol>`, rc `''|*|<kod>`. |
| `FLXAN_Stats::range( $preset, $start, $end )` | `today, yesterday, 7d, 30d, 90d, this_month, last_month, 12m, this_year, last_year, all, custom`. |
| `FLXAN_Stats::totals / series / pages / referrers / dim / events / event_names / realtime / page_stats / post_views / popular_posts / visit_metrics / segments / refs / funnel / report` | Okuma katmanı (tarih `Y-m-d`, sitenin saat dilimi). |
| `FLXAN_Aggregator::run( $budget )` | Kuyruğu hemen işler. |

## Olaylar (WooCommerce)

`add_to_cart` (ürün adı), `remove_from_cart`, `coupon` (kod), `payment` (ödeme yöntemi kimliği; ödeme sayfasında seçim), `order` (sipariş oluştu, ödeme öncesi), `checkout:form`, `checkout:submit`, `donation` (ödendi; tutarlı, sunucudan). Sunucu olayları `flxan_queue_event( $ad, $ayrinti )` ile `flxan_pe` çerezine yazılır, tracker.js gönderir (huni/yolculuğa bağlanır).
Filtreler: `flxan_cart_event_param( $ad, $urun_id, $kalem )`, `flxan_journey_events` (yolculukta ⚡ adım olarak görünen olay adları).

## Filtreler

| Ad | Açıklama |
|---|---|
| `flxan_exclude_request` | `( false )` true dönerse bu sayfada sayaç basılmaz. |
| `flxan_order_event_param` | `( $name, $order )` bağış olayının ayrıntısı (varsayılan ilk kalemin adı). |
| `flxan_ref_cookie_days` | `( 30 )` referans çerezinin ömrü. |
| `flxan_can_manage_refs` | `( bool )` referans adlarını kim düzenler (varsayılan yönetici ya da `flxan_view` + `edit_posts`). |

Kaydolduğu ortak filtreler: `flx_system_checks`, `flx_system_fixes` (`flxan_aggregate_now`), `flxseo_mcp_tools` (+ `flxai_tools` yedeği),
`flx_role_caps_supported`, `flxa_dashboard_stats` (`flxan_visitors`), `flxai_chat_suggestions`, `flxu_package_icons`.

## JS (ön yüz)

`window.flxan.track( name, param )`; HTML: `data-flxan-event`, `data-flxan-param`, `data-flxan-checkout-submit` (özel "Ödeme yap" düğmesi).
`?r=kod` → çerez `flxan_r` (30 gün); her kayıtta `rc`, giriş yapmışsa `g` (rol), sayfa kaydında `z` (saat dilimi → ülke).
Ödeme sayfasında `checkout:form` ve `checkout:submit` olayları. Sayaç `localStorage.flxan_ignore = '1'` ise çalışmaz
(dışlanan roldeki kişi İstatistikler ekranını açınca konur).

## REST `/wp-json/flxan/v1` (flxan_view)

`GET report`, `GET list?type=pages|notfound|referrers|dim|events&kind&name&q&limit&offset`, `GET realtime?minutes`, `GET page?target=`,
`GET funnel?id&by=channel|ref|rc|seg|country`, `GET journeys?steps=2-6&from=/yol/&to=/yol/&goal=huni:adım|auto|''&group=1&top`, `GET refs`, `GET dashboard?w=live,visitors,sources,funnel` (widget HTML'i);
hepsi `range|start|end` ve `seg|rc` alır. `POST refs/save {code, name, delete}` (flxan_can_manage_refs). `POST c` (toplama yedeği; yalnız aynı kökenden), `POST fast-test` (manage_options).

## MCP araçları

| Araç | Girdi |
|---|---|
| hepsi | range/start/end, seg (all/guest/members/rol), rc (''/*/kod) |
| `flxan_summary` | — |
| `flxan_timeseries` | + group day/week/month/auto |
| `flxan_pages` | + q, only404, limit, offset |
| `flxan_referrers` | + q, limit, offset |
| `flxan_breakdown` | kind: channel, device, browser, os, lang, country, utm_source, utm_medium, utm_campaign |
| `flxan_events` | + name (outbound, download, tel, mail, whatsapp, search, donation, kendi adlarınız) |
| `flxan_realtime` | minutes (1-60) |
| `flxan_page` | target: yazı kimliği, yol ya da adres |
| `flxan_compare` | a_start, a_end, b_start, b_end |
| `flxan_funnel` | id, by: channel/ref/rc/seg/country |
| `flxan_journeys` | steps (2-6), from, to (yol), goal ("1:3" = 1. huninin 3. adımı; boş = hedefsiz; verilmezse ödeme sayfası), group, top |
| `flxan_refs` | limit |
| `flxan_segments` | — |
| `flxan_ref_save` | code, name, delete (yazar) |

## Tablolar

`wp_flxan_paths, refs, site, pages, referrers, dims, events` (günlük özet; anahtarda `seg` rol ve `rc` referans kodu),
`visits` (ziyaret başına: kanal, giriş sayfası, ülke, sayfa sayısı, ilk/son zaman, `seq` ilk 8 sayfa kimliği virgüllü, `exit_id` çıkış sayfası), `fsteps` (huni: ziyaretin ulaştığı en ileri adım),
`seen` (günlük tekillik, 2 gün), `recent` (son 1 saat).

## Uygulama (`flx-app-manifest/1`)

Modül `analytics`. Ekranlar mevcut `flxan/v1` uçlarını kullanır: `report` (genel bakış ve grafik), `realtime` (canlı, 15 sn), `list` (sayfalar, yönlendirenler, kanallar).
