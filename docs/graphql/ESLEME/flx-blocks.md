# Flux Block Editor (flx-blocks): REST/AJAX → GraphQL eşlemesi

Paket sürümü: 0.3.0 · Önek: `flxbk_` · Sahip thread: Faz 4 / flx-blocks · Durum: bitti (PR `gql/flx-blocks-1`)

## Özet

- Kapsam (plan §5 Faz 4): içerik tiplerinde `flxBlocks` (blok ağacı + işlenmiş HTML + CSS/dosya listesi), tasarım
  kütüphanesi ve Block Editor panosu. Blok editörünün iç kodu (fork) kapsam dışı.
- REST: tasarım kütüphanesi 4 yol (6 yöntem) → GraphQL; editör içi 9 ad alanı kalıcı istisna. AJAX: 5 action
  (3 pano → GraphQL, 2 form gönderimi geçici istisna). Admin sayfası: 2 (pano → React ekranı; Stil Kütüphanesi
  kendi uygulaması, editör içi). MCP/AI aracı: 0.
- İstisna: `ISTISNALAR.md` → editör içi REST uçları, siteler arası kütüphane protokolü (kalıcı); form gönderimi
  AJAX (geçici, flx-forms).
- Servisler: `includes/services/` → `FLXBK_Render_Service`, `FLXBK_Library_Service`, `FLXBK_Dashboard_Service`,
  DTO'lar `class-flxbk-dto.php`.
- Node tipleri: yok (alan `ContentNode` üzerinde; desen ve kaynak kimlikleri dizgi).
- Belge: `docs/flx-blocks/GRAPHQL.md` (headless kullanım, flx-forms bağlantı noktaları).

## İçerik (Faz 4 çekirdeği)

| Alan | cap | Dönüş | Not |
|---|---|---|---|
| `ContentNode.flxBlocks` | içeriğin okuma kuralı (yayında + parolasız + görünür tip → herkes; değilse `read_post`) | `FlxBlocksContent` (parola korumalıda `null`) | `html`, `css`, `styles`, `scripts`, `fonts`, `schema`, `headHtml`, `footerHtml`, `blocks` |
| `flxBlocksSiteStyles` | herkese açık | `FlxBlocksSiteStyles!` | Palet değişkenleri, genel stiller, blok kütüphanesi CSS'i, `headHtml` |
| `flxBlocksRender(content, postId)` | `edit_posts` (+ `edit_post` postId) | `FlxBlocksContent!` | Taslak / desen önizlemesi, 1 MB sınırı |

Ön yüzde birebir görünüm riski (plan §5) şöyle kapatıldı: işleme sırasında blok CSS'i, block-supports CSS'i, kuyruğa
giren stil/betik/modüller, yazı tipleri ve FAQ şeması toplanır; WordPress'in kendi yazdırıcısıyla `headHtml` /
`footerHtml` üretilir. Durum her işlemeden önce saklanıp sonra geri yüklenir, aynı istekte birden çok sayfa tam CSS
alır. Sözleşme testi canlı sayfa çıktısıyla karşılaştırır (aynı CSS ve aynı stil dosyaları).

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Not |
|---|---|---|---|---|
| GET | `/flx-blocks/v1/library` | paylaşım anahtarı / `edit_posts` | İSTİSNA | Siteler arası protokol: diğer Flux siteleri çağırır (LIBRARY.md). Bu sitenin kendi okuması `flxBlocksLibrary` |
| GET | `/flx-blocks/v1/library/sources` | `edit_posts` | `flxBlocksLibrarySources` | |
| POST | `/flx-blocks/v1/library/sources` (add/remove) | `manage_options` | `flxBlocksLibrarySourceAdd`, `flxBlocksLibrarySourceRemove` | REST servisi çağırır |
| GET | `/flx-blocks/v1/library/remote` | `edit_posts` | `flxBlocksLibrary(source:)` | |
| GET/POST | `/flx-blocks/v1/library/share` | `manage_options` | `flxBlocksLibraryShare`, `flxBlocksLibraryShareUpdate` | |
| * | `fxb-design-tokens/v1/*` (presets, palettes, documents, feed, schema, projected-css, active-library, user primitives) | editör | İSTİSNA | Stil Kütüphanesi uygulaması ve editör içi (fork) |
| * | `kbp/v1/*`, `fxb-lottieanimation/v1`, `fxb-vector/v1` | editör | İSTİSNA | Editör içi |
| * | `fxb-mailerlite/v1`, `fxb-fluentcrm/v1`, `fxb-activecampaign/v1`, `fxb-getresponse/v1` | editör | İSTİSNA | Form bloğu entegrasyon ayarları, editör içi |
| POST | header / navigasyon / gelişmiş form otomatik taslak | editör | İSTİSNA | Editör içi |

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxbk_activate_deactivate` | pano cap'i / `flx-blocks-manage` | `flxBlocksBlockTypeSetActive` | Klasik işleyici `FLXBK_Dashboard_Service::toggle` |
| `flxbk_save_config` | pano cap'i | — | Blok varsayılanları editöre taşındı ("Flux Block Editor Controls"); klasik panoda da yok |
| `flxbk_post_default_width` | `edit_posts` | — | Editör içi (yazı başına genişlik düğmesi) |
| `fxb_process_ajax_submit`, `fxb_process_advanced_form_submit` (+ nopriv) | herkese açık / form nonce | İSTİSNA (geçici) | Form gönderimi; flx-forms GraphQL paketiyle `flxFormSubmit`'e taşınır |

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `admin.php?page=flx-blocks` | Blok kartları (aç/kapat, yönetim bağlantısı), ayarlar (yönetim çubuğu, editör genişlikleri), tasarım kütüphanesi (desenler, önizleme, bağlı kütüphaneler, paylaşım) | `FlxBlocksDashboard`, `FlxBlocksLibraryPatterns`, `FlxBlocksPatternPreview`, `FlxBlocksLibraryShare` + mutation'lar | `packages/modules/src/blocks/BlocksScreen.tsx` (`flxbk-dashboard`), wp-admin varsayılanı; "Klasik görünüm" kullanıcı başına (`flxbk_ui` meta). FluxApp modülü `flxbk-dashboard` |
| `admin.php?page=flx-blocks-style-library` | Stil Kütüphanesi (tasarım token'ları) | — | Kendi React uygulaması, `fxb-design-tokens/v1` (istisna); yeni ekrandan bağlantı |

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni |
|---|---|---|
| `FlxBlocksContent` | html, css, blocks, styles, scripts, fonts, schema, headHtml, footerHtml | — |
| `FlxBlock` | clientId, parentClientId, order, depth, name, attributes (FlxJSON), innerHtml, dynamic | parentClientId (üst düzey) |
| `FlxBlocksAsset` | kind (STYLE/SCRIPT/MODULE), handle, src, version, media, dependencies, inlineBefore, inlineAfter, data, footer, strategy | src (yalnız satır içi), version (WP eklemezse), media (betik) |
| `FlxBlocksSiteStyles` | css, globalStylesheet, headHtml, styles | — |
| `FlxBlocksLibrary` | name, url, source, categories, patterns (bağlantı) | source (bu site) |
| `FlxBlocksPattern` | id, title, description, categories, keywords, viewportWidth, content, source, rendered | source (bu site); `rendered` tek desende istenir |
| `FlxBlocksLibrarySource` / `FlxBlocksLibraryShare` | id, name, url, hasKey / enabled, key, url | — (anahtar istemciye verilmez) |
| `FlxBlocksBlockType` | slug, name, description, active, link, linkText | link, linkText |
| `FlxBlocksSettings` | showHeadersInAdminBar, showAdminBarSetting, editorWidth, styleLibraryUrl | editorWidth (tema kullanmıyorsa) |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-blocks:post:<id>` | `flxBlocks` | `save_post` / `deleted_post` (o yazı) |
| `flx-blocks:styles` | `flxBlocks`, `flxBlocksSiteStyles` | paylaşılan tip kaydı (wp_block, flxbk_header, flxbk_navigation, flxbk_form, wp_template_part, wp_global_styles), renk/yazı tipi/blok ayarları, `switch_theme` |
| `flx-blocks:library` | kütüphane sorguları | kaynak ekle/kaldır, paylaşım, paylaşılan tip kaydı |

## Açık sorular

- `flxRoute` / `flxSite` (flx-headless) `flxBlocks` + `flxBlocksSiteStyles`'ı birleştirecek; bu pakette yok.
