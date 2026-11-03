# flx-preset/1: site ön ayarı biçimi

Bir WordPress sitesini kurum profili, ayarlar, görünüm ve içerikle birlikte tek dosyadan kurmak (ya da onaylı yamalarla değiştirmek) için açık biçim. Motor: [Flux Setup](../../plugins/flx-setup). JSON Schema: [`flx-preset.schema.json`](../../plugins/flx-setup/schema/flx-preset.schema.json). Lisans önerisi: şablonlar CC-BY-4.0, kod GPL-2.0-or-later.

## Dosya düzeni

```
flx-preset-<id>/                 ön ayar eklentisi (ince)
  flx-preset-<id>.php            başlık + "Requires Plugins: flx-setup"
  preset/
    preset.json                  şablon
    assets/                      logo, görsel, sayfa gövdeleri (.html), bölümler (.json)
```

Ön ayar üç yoldan bulunur: etkin `flx-preset-*` eklentisi (`preset/preset.json`), bir eklentinin `flx_presets` filtresi (`id => preset.json yolu`; ör. Flux Kurban kendi içinde taşır), panelden içe aktarılan JSON.

Herhangi bir yerde `{ "$file": "assets/x.html" }` o dosyanın içeriğiyle değişir (`.json` ise çözülmüş JSON). Yollar şablon klasörünün dışına çıkamaz. İçe aktarılan JSON'da `$file` olmaz.

## Bölümler

| Alan | Ne yapar | Hedef |
|---|---|---|
| `format` | `"flx-preset/1"` (zorunlu) | |
| `id`, `name`, `version`, `description`, `license` | kimlik (yamada gerekmez) | |
| `requires` | `theme`, `plugins` (`"flx-donate>=2.9"`); eksikse ilgili kısım atlanır, panelde uyarı | |
| `profile` | kurum kimliği | option `flx_profile` |
| `media` | `key => "assets/logo.svg"` ya da `{ file \| url, alt, title }` | medya kütüphanesi (bir kez) |
| `wp` | çekirdek option'lar: `blogname`, `blogdescription`, `timezone_string`, `WPLANG`, `show_on_front`, `page_on_front`… | `wp_options` |
| `settings` | `option adı => alanlar`; mevcut ayarla **derin birleşir** | eklenti option'ları |
| `theme.styles` | theme.json biçimi (`settings`, `styles`) | kullanıcı global stili |
| `theme.templates` | `slug => gövde` (front-page, page, single, archive, single-product…) | `wp_template` |
| `theme.parts` | `slug => gövde` ya da `{ title, area, body }` (header, footer) | `wp_template_part` |
| `theme.patterns` | `slug => { title, categories, body }` | kayıtlı pattern (çalışma anı) |
| `content` | `[{ type, key, … }]` | türü kaydeden paket |
| `migrations` | sonraki sürümlerin değişiklikleri | bkz. Migration'lar |

### Profil alanları
`name`, `legal_name`, `tagline`, `about`, `signature`, `phone`, `whatsapp`, `email`, `address`, `map_url`, `iban`, `bank_name`, `bank_holder`, `tax_office`, `tax_no`, `founded`, `logo`, `logo_light`, `colors{}`, `social{}`. Paketler `flx_profile( 'whatsapp' )` ile okur. Yeni alan `flx_profile_fields` filtresiyle eklenir.

### Birleşme kuralları
- Nesneler anahtar anahtar birleşir. Boş nesne `{}` bir şey değiştirmez.
- Elemanları `key` alanı taşıyan listeler `key` ile birleşir (var olan güncellenir, yeni eklenir, diğerleri kalır).
- Diğer listeler ve skaler değerler olduğu gibi yazılır.
- Gizli alanlar (adında password, secret, token, api_key, key… geçen) şablonda olamaz, dışa aktarımda yazılmaz.
- `active_plugins`, `siteurl`, `home`, `users_can_register`, `default_role` gibi hassas option'lar yazılamaz.

### İçerik türleri
Motorun türleri: `page`, `post` (`post_type` ile özel tür de), `term`, `navigation` (`items: [{ label, url | page, children }]` ya da `body`), `template`, `part`, `styles`, `product` (WooCommerce). Paketler `flx_preset_importers` ile tür ekler; planlananlar: Flux Donate `flxb_type`, `flxb_partner`, `flxb_report`, `flxb_detail_tab` (şemalar docs/flx-donate/), Flux Certificates `flxs_template`, Flux Forms `flxf_form`.

Her kayıt `key` ile idempotenttir: ikinci uygulamada kopya oluşmaz, mevcut kayıt güncellenir. Sayfada `key` yoksa `slug` ile mevcut sayfa bulunur (var olan sitelere uyum).

### Gövde: markup ya da blok ağacı
```json
"body": [
  { "name": "core/heading", "attrs": { "level": 2 }, "html": "<h2 class=\"wp-block-heading\">{profile.name}</h2>" },
  { "name": "core/group", "attrs": { "layout": { "type": "constrained" } },
    "html": "<div class=\"wp-block-group\">{{inner}}</div>",
    "inner": [ { "name": "core/paragraph", "html": "<p>Merhaba</p>" } ] },
  { "name": "core/template-part", "attrs": { "slug": "footer" } }
]
```
`html` bloğun kaydedilmiş HTML'idir, iç bloklar `{{inner}}` yerine girer. Metin düğüm olduğu gibi markup parçasıdır. Gövde düz markup metni ya da `$file` da olabilir.

### Yer tutucular
`{profile.<alan>}`, `{domain}`, `{home}`, `{site_name}`, `{year}`; tam değer olarak `"@page:key"` (ID), `"@media:key"`, `"@term:key"`, `"@navigation:key"`; metin içinde `{url:@page:key}`, `{url:@media:logo}`, `{id:@page:key}`.

## Uygulama kuralları

| Durum | Anlamı |
|---|---|
| `add` | sitede yok, eklenir |
| `change` | değişir (ön ayarın daha önce yazdığı ve dokunulmamış değer ya da "Uygula" modunda dolu değer) |
| `same` | zaten aynı; artık bu ön ayarın sayılır |
| `keep` | "Yalnız boşları doldur" modunda dolu değer, dokunulmaz |
| `manual` | ön ayarın yazdığı değer sonradan panelden değiştirilmiş: **atlanır**, istenirse satır satır zorla |

Motor her hedefe son yazdığı değerin hash'ini tutar; sitedeki değer farklıysa elle değiştirilmiştir. İki ön ayar aynı hedefi yazarsa önizlemede çakışma görünür, son uygulanan kazanır.

**İlk kurulum**: boş sitede ön ayar eklentisi etkinleşince kendiliğinden uygulanır. İçerikli sitede panelde önizlenir; "Yalnız boşları doldur" mevcut değerlere dokunmaz.

## Ön ayar güncellenince ayarlar
Ön ayarın `version` alanı değişince (eklenti güncellemesi) Flux Setup bir sonraki yönetim isteğinde ayar bölümlerini (`profile`, `wp`, `settings`) siteyle **eşitler**: ön ayarın yazdığı ve kullanıcının değiştirmediği değerler yeni haline gelir, yeni alanlar eklenir, elle değiştirilenler (`manual`) atlanır. "Yalnız boşları doldur" ile kurulan sitede dolu ve sahipsiz değerler yine korunur. Geçmişte **Eşitleme** kaydı olur, geri alınabilir. İçerik, şablon ve parçalar eşitlenmez: onlar migration'la gelir. Mevcut bir değeri (sahipsiz ya da "doldur" modunda korunan) bilerek değiştirmek için de migration yazın; migration her zaman uygular.

Ön ayar bir option'ın yalnız bildirdiği alanlarını değiştirir. Eklentinin sanitize callback'i bildirilmeyen alanları bozarsa (ör. formda olmayan onay kutusunu kapalı sayarsa) motor onları eski haline getirir.

## Ayar şeması
Hangi option'da hangi alanın olduğu, türü ve varsayılanı `flx_settings_schema` filtresinden gelir (bkz. plugins/flx-setup/API.md). AI `preset_schema` çıktısındaki `settings` ile okur. Şemada olmayan alan yazılırsa önizlemede uyarı çıkar.

## Migration'lar
```json
"migrations": [
  { "id": "2026-11-20-kampanya", "title": "2027 kampanya sayfası", "desc": "…", "auto": false,
    "content": [ { "type": "page", "key": "kampanya-2027", "title": "…", "body": "…" } ] }
]
```
İlk kurulumda hepsi sırayla uygulanır. Sonra eklenenler panelde "bekleyen güncelleme" olarak listelenir (Önizle, Uygula, Atla). `auto: true` olanlar güncellemeden sonra kendiliğinden uygulanır. Elle değiştirilmiş değerler yine korunur. `id` bir kez yayınlandıktan sonra değişmez.

## Yamalar (AI ve MCP)
Yama, `id`/`name` taşımayan, yalnız değişecek alanları içeren flx-preset/1 parçasıdır. Akış: `preset_patch_preview` → kullanıcıya özet → `preset_patch_apply`. Flux AI sohbetinde yazan araçlar çalışmadan önce onay ister. Dış MCP'den gelen yama varsayılan olarak "onay bekleyen yama" olur. Uygulanan her yama geçmişte bir kayıttır, `preset_revert` ile geri alınır. Bir ön ayarın değerini değiştiren yama o ön ayar için "elle değiştirilmiş" sayılır.

## Geri alma ve kaldırma
Her uygulama bir kayıttır (eski değerler + oluşturulan içerik). Geri almada sonradan elle değiştirilen değerlere dokunulmaz. Ön ayarı kaldırmak kayıtlarını tersten geri alır: ayarlar eski haline döner, oluşturulan sayfa/menü/ürünler taslağa alınır (silinmez), şablon ve parçaların kullanıcı katmanı silinir (temanın dosyası geri gelir). Medya kütüphanede kalır.
