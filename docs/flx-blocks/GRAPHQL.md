# Flux Block Editor GraphQL API

Uç: `/graphql` (Flux GraphQL). İşlemler `plugins/flx-blocks/graphql/operations/*.graphql`, tipleri
`@fluxwp/graphql` (`FlxBlocksContentByUri`, `FlxBlocksSiteStyles` …). Eşleme: `docs/graphql/ESLEME/flx-blocks.md`.

## Headless sayfa: `flxBlocks`

Her içerik tipinde (`ContentNode`: sayfa, yazı, özel tipler) `flxBlocks` alanı var. Sayfayı sitedeki gibi
göstermek için gereken her şeyi tek yanıtta verir: işlenmiş HTML, bu blokların ürettiği CSS, stil ve betik
dosyaları (bağımlılık sırasıyla), yazı tipleri, JSON-LD şema ve blok ağacı.

```graphql
query ($uri: String!) {
  flxBlocksSiteStyles { headHtml }          # site geneli, sayfada bir kez
  nodeByUri(uri: $uri) {
    ... on ContentNode {
      flxBlocks { html headHtml footerHtml }
    }
  }
}
```

İki kullanım yolu var, ikisi birden kullanılmaz:

1. **Hazır kod (önerilen):** `flxBlocksSiteStyles.headHtml` + `flxBlocks.headHtml` `<head>`'e,
   `flxBlocks.html` gövdeye, `flxBlocks.footerHtml` `<body>` sonuna. WordPress'in kendi yazdığı etiketler
   (sürüm sorgusu, satır içi CSS/JS, import map dahil).
2. **Kendin yükle:** `css` (satır içi `<style>`), `styles` / `scripts` (`FlxBlocksAsset`: `src`, `version`,
   `dependencies`, `inlineBefore`, `inlineAfter`, `data`, `footer`, `strategy`), `fonts` (stil URL'leri),
   `schema` (JSON-LD). `handle` ile her dosyayı sayfada bir kez yükle; liste bağımlılıklar önce gelecek şekilde
   sıralı. `kind: MODULE` dosyalar `type="module"`, import map'i `footerHtml` içinde.

Kurallar:

- Erişim içeriğin kendi kuralıyla: yayında, parolasız ve herkese açık tip ise herkes; değilse `read_post`.
  Parola korumalı içerikte alan `null` (parola girilmeden HTML verilmez).
- Her yanıt ayrı işlenir: aynı istekte birden çok sayfa istenebilir, her biri kendi CSS'ini tam alır.
- Önbellek etiketleri: `flx-blocks:post:<id>` (yazı kaydı/silinmesi) ve `flx-blocks:styles` (renk paleti,
  yazı tipi, blok ayarları, tema değişimi, paylaşılan header/navigasyon/form/şablon parçası/genel stiller).
- `blocks` düz liste, belge sırasında: `clientId` konum yolu (`"0.2.1"`), `parentClientId`, `order`, `depth`,
  `name`, `attributes` (FlxJSON), `innerHtml`, `dynamic` (sunucuda işlenen blok). Kendi bileşenleriyle çizmek
  isteyen ön yüzler içindir; birebir görünüm için `html` kullanılır.

## Önizleme: `flxBlocksRender`

Kaydedilmemiş blok kodunu ya da bir deseni sitenin işleyeceği gibi işler (`edit_posts`; `postId` verilirse o
yazıyı düzenleme yetkisi). Dönüş `FlxBlocksContent`. 1 MB üstü içerik `INVALID_INPUT` (`content`).

## Tasarım kütüphanesi

| Alan | cap | Not |
|---|---|---|
| `flxBlocksLibrary(source, refresh)` | `edit_posts` | `categories`, `patterns(category, search, first, after)` (bağlantı) |
| `flxBlocksPattern(id, source)` | `edit_posts` | Tek desen; `rendered` alanı önizleme (`FlxBlocksContent`). Bilinmeyen id → `null` |
| `flxBlocksLibrarySources` | `edit_posts` | Bağlı kütüphaneler; anahtar sunucudan çıkmaz (`hasKey`) |
| `flxBlocksLibraryShare` | `manage_options` | Paylaşım durumu, anahtar, adres |
| `flxBlocksLibrarySourceAdd(url, name, key)` | `manage_options` | Uzak site bir kez doğrulanır; hata `INVALID_INPUT` (`url`) |
| `flxBlocksLibrarySourceRemove(id)` | `manage_options` | |
| `flxBlocksLibraryShareUpdate(enabled, regenerateKey)` | `manage_options` | |

Siteler arası protokol (`GET /wp-json/flx-blocks/v1/library`) REST'te kalır: diğer Flux siteleri onu çağırır
(`LIBRARY.md`, `ISTISNALAR.md`).

## Pano (Block Editor ekranı)

| Alan | cap | Not |
|---|---|---|
| `flxBlocksBlockTypes` | pano cap'i (`flxbk_admin_settings_capability`, varsayılan `edit_pages`) | Bloklar, açık/kapalı, yönetim bağlantısı |
| `flxBlocksSettings` | aynı | Yönetim çubuğu ayarı, editör genişlikleri (tema kullanıyorsa), Stil Kütüphanesi adresi |
| `flxBlocksBlockTypeSetActive(slug, active)` | aynı | Bilinmeyen blok → `INVALID_INPUT` (`slug`) |
| `flxBlocksSettingsUpdate(showHeadersInAdminBar, editorWidth)` | aynı | Genişlik 200–4000 px; gönderilmeyen alan değişmez |

Klasik pano (AJAX) ve tasarım kütüphanesi REST uçları aynı servisleri çağırır, davranış aynı.

## Flux Forms nereden bağlanır

flx-forms bu paketten sonra GraphQL'e geçer. Bağlantı noktaları:

- **Görünüm:** `flx/form` ve `flx/advanced-form` blokları (ve flx-forms'un kendi form blokları) `flxBlocks`
  içinden işlenir; form HTML'i, CSS'i ve betikleri (`flxbk-form` vb.) `html` / `styles` / `scripts` içinde
  gelir. Ek bir alan gerekmez.
- **Gönderim:** bugün admin-ajax (`fxb_process_ajax_submit`, `fxb_process_advanced_form_submit`). Geçici
  istisna; flx-forms kendi `flxFormSubmit` mutation'ını açınca form betiği oraya yönlenir, bu iki AJAX ucu
  kalkar (`ISTISNALAR.md`).
- **Form kayıtları:** `flxbk_form` yazı tipi paylaşılan tip sayılır; kaydı `flx-blocks:styles` etiketini
  temizler. flx-forms kendi kayıt tipini eklerse `flxbk_render_post_content` filtresi ve aynı temizleme kancası
  kullanılır.
- **Servis:** `FLXBK_Render_Service::render_markup( $markup, $post_id )` herhangi bir blok kodunu aynı çıktıyla
  işler (ör. form önizlemesi).

## Filtreler

- `flxbk_render_post_content( string $content, WP_Post $post )`: `flxBlocks` işlenmeden önceki içerik.
- `flxbk_admin_settings_capability`: pano cap'i (klasik ve yeni ekran aynı).
