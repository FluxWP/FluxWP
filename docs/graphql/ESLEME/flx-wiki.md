# Flux Wiki (flx-wiki): klasik → GraphQL eşlemesi

Paket sürümü: 0.3.0 · Önek: `flxwk_` · Sahip thread: Faz 1 / flx-wiki · Durum: bitti (PR `gql/flx-wiki-1`)

## Özet

- REST uçları: 3 (`flx-wiki/v1/search|ask|preview`) + `wp/v2/flx_wiki` üstünde `markdown` alanı · AJAX: 0 · admin-post işleyici: 2 · Admin sayfası: 1 (Wiki › Ayarlar) · MCP aracı: 4 (+ 2 kaynak, 1 prompt)
- İstisna: yok. `wp/v2/flx_wiki` alanı ve FluxApp'in JSON manifest ekranı (`app/manifest.json`) olduğu gibi kalır (eklemeli geçiş); MCP araçları servis katmanını çağırır.
- Servisler: `includes/services/` → `FLXWK_Page_Service`, `FLXWK_Search_Service`, `FLXWK_Ask_Service`, `FLXWK_Admin_Service`; DTO'lar `class-flxwk-dto.php`.
- Node tipleri: `FlxWikiPage` (loader `FLXWK_Page_Loader`).
- Ayarlar: elle tip yok; `flx_settings_schema` girdisi `'graphql' => 'Wiki'` → `flxSettings.wiki` / `flxSettingsUpdate(input:{wiki:…})`. Yan etkiler (önbellek, rewrite flush) `update_option_flxwk_settings` kancasında, hangi yoldan yazılırsa yazılsın çalışır.

## REST uçları

| Uç | cap | → GraphQL | Not |
|---|---|---|---|
| `GET flx-wiki/v1/search` | wiki okunabilir | `flxWikiSearch(query, section, limit)` | Aynı servis (`FLXWK_Search_Service`), REST JSON biçimi değişmez. |
| `POST flx-wiki/v1/ask` | `FLXWK_Ask_Service::allowed()` | `flxWikiAsk(input)` | Kilit + saatlik sınır aynı. |
| `POST flx-wiki/v1/preview` | `edit_posts` | `flxWikiPreview(input)` | Mutation: metin uzun olabilir, kayıt yok. |
| `wp/v2/flx_wiki` `markdown` | WP | `FlxWikiPage.markdown` | REST alanı kalır. |

## admin-post işleyicileri

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxwk_save` | `manage_options` / `flxwk_save` | `flxSettingsUpdate(input:{wiki:{…}})` | Aynı temizleme `FLXWK_Admin_Service::save_tab()`. |
| `flxwk_seed` | `manage_options` / `flxwk_seed` | `flxWikiGuidesImport` | Paketle gelen kılavuzları içe aktarır. |

## MCP araçları

| Araç | → servis | GraphQL karşılığı |
|---|---|---|
| `flxwk_search` | `FLXWK_Search_Service::run()` | `flxWikiSearch` |
| `flxwk_get_page` | `FLXWK_Page_Service` | `flxWikiPage` |
| `flxwk_list_pages` | `FLXWK_Page_Service::page_list()` | `flxWikiPages` |
| `flxwk_save_page` | `FLXWK_Page_Service::save()` | `flxWikiPageSave` |

Kaynaklar `wiki-index`, `wiki-full` ve prompt `flxwk_build_plugin` olduğu gibi kalır.

## Admin sayfaları

| Sayfa | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `edit.php?post_type=flx_wiki&page=flx-wiki` | Sayfa listesi (filtre, sayfalama, çöp), bölümler, sayfa düzenleyici (Markdown, canlı önizleme), Genel / Görünüm / Yapay zekâ ayarları, adresler ve kılavuz içe aktarma | `FlxWikiScreen`, `FlxWikiPages`, `FlxWikiPageEdit`, `FlxWikiTree`, `FlxWikiPreview` … | `packages/modules/src/wiki/WikiScreen.tsx` (`flxwk-wiki`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxwk_ui` meta). Tam sayfa düzenleme WordPress'in kendi editöründe de açılır. |

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxWikiPage` (Node) | id, databaseId, title, slug, path, status, summary, excerpt, parent, menuOrder, section, url, markdownUrl, modified, readMinutes, editUrl, canEdit, markdown, html, toc | `slug`/`path`: kısa adı olmayan taslak; `markdownUrl`: taslak; `editUrl`: izin yok; `parent`/`section`: yok | `FLXWK_Page_Loader` |
| `FlxWikiSection` | databaseId, slug, name, description, order, pageCount, url | — | — |
| `FlxWikiTreeGroup` / `FlxWikiTreeEntry` | section, pages(page, depth) | `section`: bölümsüz sayfalar | — |
| `FlxWikiSearchResult` / `FlxWikiSearchHit` | total, hits, fixed; pageId, title, url, section, heading, snippet, score | `fixed`: yazım düzeltilmediyse | — |
| `FlxWikiRendered` / `FlxWikiTocItem` | html, toc | — | — |
| `FlxWikiAnswer` / `FlxWikiAnswerSource` | answer, html, sources | — | — |
| `FlxWikiImportResult`, `FlxWikiOverview` | sayılar; adresler, MCP/AI durumu | — | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-wiki:content` | sayfa, ağaç, bölüm, arama sorguları | `flxwk_bump()` (sayfa/bölüm kaydı, ayar yazımı, içe aktarma) |

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
- `app/manifest.json` (FluxApp'in JSON ekranı) yerine `flxapp_modules` ile `flxwk-wiki` kaydı: FluxApp sürümlerinin ekranı tanımasına bağlı, sonraki iş.
