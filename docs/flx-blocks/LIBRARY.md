# Flux Block Editor: tasarım kütüphanesi

Editörün üst çubuğundaki **Tasarım kütüphanesi** düğmesi tam ekran bir pencere açar. Pencerede hazır bölüm ve sayfa desenleri listelenir, tıklanan desen imlecin olduğu yere eklenir. Kadence'in bulut kütüphanesi kaldırıldı; kütüphaneler yalnız bu sitenin desenleri ve yöneticinin linkle eklediği başka Flux siteleridir.

## Kaynaklar

| Sekme | Nereden gelir |
|---|---|
| Bu site | Kayıtlı tüm blok desenleri (tema, ön ayarlar, eklentiler; `core/` desenleri hariç) ve eşitlenmemiş "Desenlerim". `flx_blocks_pattern_sources` filtresiyle öğe eklenir. |
| Bağlı kütüphaneler | Yöneticinin "Kütüphane ekle" ile eklediği Flux siteleri (adres + isteğe bağlı anahtar). İstek sunucudan gider, 30 dk önbellekte kalır. "Kütüphaneyi yenile" önbelleği atlar. |

Bir siteyi kütüphane olarak açmak için pencerede **Kütüphane ekle › Bu siteyi paylaş** açılır. Paylaşım açılınca 32 karakterlik bir anahtar üretilir; diğer site bu anahtarla bağlanır. "Yeni anahtar" eski anahtarı geçersiz kılar.

## REST

Ad alanı `flx-blocks/v1`.

| Uç | Yetki | Ne yapar |
|---|---|---|
| `GET /library` | `edit_posts` ya da `X-Flx-Library-Key` başlığında doğru anahtar (paylaşım açıkken) | Bu sitenin kataloğu |
| `GET /library/sources` | `edit_posts` | Bağlı kütüphaneler (anahtarlar dönmez, yalnız `has_key`) |
| `POST /library/sources` | `manage_options` | `{action:"add", url, name?, key?}` ekler (önce bağlantıyı dener), `{action:"remove", id}` siler |
| `GET /library/remote?source=<id>&refresh=1` | `edit_posts` | Bağlı kütüphanenin kataloğu (sunucu üzerinden) |
| `GET/POST /library/share` | `manage_options` | `{enabled, regenerate}` paylaşım ayarı |

Seçenekler: `flxbk_library_sources` (anahtarlar dahil, autoload kapalı), `flxbk_library_share`.

## Katalog biçimi (format 1)

```json
{
  "format": 1,
  "name": "Site adı",
  "url": "https://ornek.org/",
  "categories": [{ "slug": "hero", "label": "Hero" }],
  "items": [
    {
      "id": "flx-preset-yt/hero",
      "title": "Ana sayfa hero",
      "description": "",
      "categories": ["hero"],
      "keywords": ["giriş"],
      "viewportWidth": 1200,
      "content": "<!-- wp:flx/rowlayout ... -->"
    }
  ]
}
```

`content` blok işaretlemesidir; eklenince editörde ayrıştırılır, kaydedilirken WordPress'in normal kses süzgecinden geçer.

## Filtreler

- `flx_blocks_pattern_sources` (dizi): katalog öğelerini ekle/değiştir.
- `flxbk_library_skip_core_patterns` (bool, varsayılan `true`): `core/` desenlerini gizle.
- `flxbk_design_library_enabled` (bool): düğmeyi tamamen kapat. Ayarlar › Tasarım kütüphanesi'nden rol bazlı da kapatılır.
