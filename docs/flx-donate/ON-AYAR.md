# Flux Donate ön ayar içerik türleri (flx-preset/1)

Flux Donate 2.11.0, Flux Setup ön ayarlarının `content` listesinde dört tür tanır. Motor anahtarla (`key`) bulur; var olan kayıt sahiplenilir, kopya açılmaz. Ön ayar kaldırılınca kayıt silinmez: tür kapanır, iş ortağı pasif olur, rapor ve sekme sitenin listesinden çıkar.

## `flxb_type`: bağış türü
`key` = türün slug'ı (sahiplenilen türün adresi değişmez).
```json
{ "type": "flxb_type", "key": "fidan", "title": "Fidan bağışı", "category": "", "description": "",
  "currency": "TRY", "status": "active", "goal_amount": 0, "start_date": "", "end_date": "",
  "allow_free": 0, "min_amount": 0, "allow_quantity": 1, "max_quantity": 0, "allow_recurring": 0,
  "options": [ { "key": "f1", "label": "1 fidan", "price": 150, "unit": "", "active": 1, "prices": { "EUR": 5, "USD": 5 } } ],
  "meta": { "track_items": true, "file_completes": false,
            "labels": { "partner": "Dikim alanı", "st_done": "Dikildi" },
            "requires_proxy": false, "intention": "", "meat_to_needy": false, "grouping": "none" } }
```
2.15.1: meta `i18n` `{ "en": { "title": "Sapling donation", "description": "", "options": { "f1": "1 sapling" } } }` (Flux Lang dilleri). Seçenekte `units_per` (1'den büyükse) ve `badge` de taşınır.
2.15: seçenekte `prices` (türün kendi para birimi dışındaki fiyatlar, kur çevrimi yok) ve meta'da `min_by_currency` `{ "EUR": 10 }`, `presets_by_currency` `{ "EUR": [10, 25] }` isteğe bağlı.
`labels` anahtarları: `menu, title, item, partner, files, st_pending, st_approved, st_sent, st_done, st_delivered, st_canceled, do_assign, do_revoke, do_done, caption`. Form bağlantıları (`form_ids`) ön ayarla taşınmaz, sitede kalır.

## `flxb_partner`: iş ortağı ve anlaşmaları
`key` = `pkey` (yoksa ada göre bulunur). Türler slug ile verilir, siteler arası taşınır. `prices` listesi anlaşmaların tamamıdır (uygulanınca eskiler silinip bunlar yazılır).
```json
{ "type": "flxb_partner", "key": "aydos", "name": "Aydos Ormanı", "group_name": "İstanbul",
  "is_service": 1, "is_source": 0, "types": ["fidan"], "contact_name": "", "phone": "", "email": "",
  "currency": "TRY", "active": 1, "note": "",
  "prices": [ { "type": "fidan", "option": "f1", "price": 45, "currency": "TRY", "valid_from": "2026-01-01", "active": 1 } ] }
```

## `flxb_report`: JSON rapor
`key` = rapor id'si. Gövde `flxb-report/1` (bkz. plugins/flx-donate/API.md › JSON raporlar). Tür filtresi için `filter.type_slugs` kullanın (id'ler siteden siteye değişir).
```json
{ "type": "flxb_report", "key": "fidan-alan", "title": { "tr": "Alana göre fidanlar", "en": "Saplings by area" },
  "source": "items", "filter": { "type_slugs": ["fidan"] }, "group": ["partner"], "metrics": ["count", "done", "cost"], "period": "this_year" }
```

## `flxb_detail_tab`: ek detay sekmesi
`key` = sekme id'si. Gövde `flxb-detail-tab/1`.
```json
{ "type": "flxb_detail_tab", "key": "teslim", "title": { "tr": "Teslim", "en": "Delivery" }, "on": "item",
  "type_slugs": ["kurban"], "fields": [ { "key": "kisi", "label": "Teslim alan", "type": "text" } ] }
```

## Dışa aktarım
"Bu siteden şablon çıkar" türleri, iş ortaklarını, sitenin kendi raporlarını ve sekmelerini ekler; option'lardan `flxb_track_labels`, `flxb_track_early_days`, `flxb_categories`.

### Metadata comparison

`FLXB_Preset::type_data()` and `type_read()` both use `FLXB_Types::clean_meta()` with the current `flxb_type_meta_clean` extensions. New supported metadata fields must not be maintained in a second importer whitelist. Runtime fields such as `wc_product_id` remain excluded by the sanitizer. This preserves icons, subtitles, form visibility, currency settings and extension fields on repeated apply.

## Görünüm ayarları (`flxb_look`, 2.23.0)

Ön ayar `settings.flxb_look` ile yalnız değiştirmek istediği alanları yazar; alanların tamamı `flx_settings_schema`'dadır.

| Alan | Değer | Ne yapar |
|---|---|---|
| `opt_view` | `amounts` | Adet seçenekleri büyük tutar düğmeleri olur |
| `quick` | `[1, 3, 5, 10]` | Düğmelerdeki adetler |
| `quick_star` | `3` | "En çok seçilen" rozetinin duracağı adet (0 = yok) |
| `other_amount` | bool | Bağışçı kendi tutarını yazabilir ("Diğer tutar") |
| `min_amount` | sayı | Serbest tutarın alt sınırı (mağaza para birimi). Baştan gösterilmez, alan altında uyarı olarak çıkar |
| `min_amounts` | `{ "USD": 5, "EUR": 5 }` | Diğer para birimlerinde alt sınır |
| `period_tabs` | bool | "Tek seferlik / Aylık düzenli" sekmeleri (aylık ödeme alabilen yöntem varsa görünür) |
| `card_first` | bool | Ödemede kart havaleden önce gelir |
| `coupons` | bool | Yalnız bağış olan sepette kupon kutusu |
| `addresses` | bool | Adres defteri ve Hesabım > Adresler |

Filtreler: `flxb_takes_free( bool, $type )`, `flxb_min_free( float, $type, $currency )`.
