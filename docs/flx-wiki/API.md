# Flux Wiki — Geliştirici notları

**Sürüm 0.1.0.** Kod öneki `flxwk_` / `FLXWK_`, REST `flx-wiki/v1`, yazı türü `flx_wiki` (hiyerarşik), bölüm `flx_wiki_section`.

## Neden yerel CPT, hazır wiki değil?

Wiki.js (Node + ayrı veritabanı), BookStack (ayrı Laravel uygulaması) ve DokuWiki (ayrı PHP uygulaması) paylaşımlı
sunucuda ayrı servis, ayrı giriş ve ayrı SEO demek. Yerel yazı türü temayı, Flux SEO'yu (site haritası, schema,
llms.txt), Flux AI / MCP'yi, WordPress yetkilerini ve revizyonları hazır kullanır. İçerik Markdown saklandığı için
LLM'e olduğu gibi verilir.

## Veri

| Ne | Nerede |
|---|---|
| Markdown kaynağı | meta `_flxwk_md` (revizyonlu). kses Markdown'u bozmasın diye `post_content`'te değil. |
| İşlenmiş HTML | `post_content` (WP araması, RSS, SEO eklentileri) ve önbellek meta `_flxwk_html` (`key` = işleyici sürümü + `flxwk_gen` + md5) |
| Bölüm sırası | terim meta `flxwk_order` |
| Ayarlar | option `flxwk_settings`: `enabled`, `title`, `intro`, `base`, `ai_links`, `edit_link`, `accent`, `width` (`normal`/`wide`), `nav`, `toc`, `ask` (`off`/`users`/`all`), `ask_limit` |
| Arama dizini | transient `flxwk_index` (sayfa kaydedilince silinir) |
| Rehber sürümü | meta `_flxwk_seed_hash` (elle değiştirilmemiş sayfalar güncellenir) |

## Adresler

| Adres | Ne |
|---|---|
| `/wiki/` | Ana sayfa (yazı türü arşivi) |
| `/wiki/?q=` | Arama sonuçları (`noindex`) |
| `/wiki/<üst>/<alt>/` | Sayfa |
| `/wiki/section/<bölüm>/` | Bölüm |
| `/wiki/<yol>.md` | Sayfa Markdown'u (`text/markdown`) |
| `/wiki/index.md`, `/wiki/llms.txt` | Dizin |
| `/wiki/llms-full.txt` | Tüm wiki |

Sayfa adresine `Accept: text/markdown` (ve `text/html` olmadan) istek gelirse Markdown döner.

## Fonksiyonlar

| Fonksiyon | Ne |
|---|---|
| `flxwk_save_page( array $a )` | Sayfa oluşturur / günceller. `id, title, md, slug, parent (yol/id), section (ad/kısa ad), excerpt, status, menu_order`. `int\|WP_Error` |
| `flxwk_md( $post )` | Markdown kaynağı |
| `flxwk_render( $md )` | `[ html, toc[] ]` |
| `flxwk_page_html( $post )` | Önbellekli `[ html, toc ]` |
| `flxwk_page_markdown( $post )` | LLM'e verilecek Markdown (başlık, kaynak, tarih + içerik) |
| `flxwk_index_markdown()` / `flxwk_full_markdown()` | llms.txt / llms-full.txt |
| `flxwk_search( $q, [ limit, section ] )` | `[ total, results[ id, title, url, section, heading, snippet, score ] ]` |
| `flxwk_find_page( $ref )` | kısa ad, yol, başlık ya da kimlikle sayfa |
| `flxwk_tree()` / `flxwk_flat()` | bölümlere göre ağaç / düz sıra |
| `flxwk_ask( $q, $page_id )` | Flux AI ile cevap: `[ answer, html, sources[] ]` |
| `flxwk_seed_import( $update )` | `content/*.md` rehberlerini aktarır |

## Filtreler

| Filtre | Ne |
|---|---|
| `flxwk_base` | Adres kökü (varsayılan Ayarlar › Genel › Adres, `wiki`) |
| `flxwk_can_ask` | `( bool )` Wiki'ye sor bu ziyaretçiye açık mı |
| `flxwk_ai_links` | `( $links, $post, $md_url )` "AI'a sor" menüsündeki dış servisler (`etiket => adres`) |
| `flxwk_page_markdown` | `( $text, $post )` .md çıktısı |
| `flxwk_index_markdown` | `( $text )` llms.txt |
| `flxwk_stopwords` | Aramada atılan soru kelimeleri (katlanmış) |
| `flxwk_seed_dir` | Rehber klasörü (varsayılan `content/`); başka paket kendi rehberlerini getirebilir |

## Rehber dosyası biçimi (`content/*.md`)

```markdown
---
title: Yeni eklenti yazma
slug: eklenti-yazma
section: eklenti        # yalnız üst sayfalarda; sections.json'daki kısa ad
parent: eklenti-yazma   # alt sayfalarda üst sayfanın kısa adı
order: 1
excerpt: Tek cümle özet (SEO açıklaması ve llms.txt).
---
Gövde. Başlığı tekrar yazmayın; ## ile başlayın.
```

`content/sections.json`: `[ { slug, name, description, order } ]`.

## Ön ayardan wiki içeriği (Flux Setup)

Ön ayar eklentisi `preset.json` içinde (`content` ya da bir migration'ın `content` listesi) wiki yazabilir. Üst sayfalar alt sayfalardan önce gelmeli.

```json
{ "type": "wiki_section", "key": "kurulum", "slug": "kurulum", "name": "Kurulum", "description": "…", "order": -2 },
{ "type": "wiki", "key": "hizli-baslangic", "slug": "hizli-baslangic", "title": "Hızlı başlangıç",
  "section": "kurulum", "order": 1, "excerpt": "…", "md": { "$file": "wiki/kurulum/hizli-baslangic.md" } },
{ "type": "wiki", "key": "guncelleme-sunucusu-docker", "slug": "docker", "parent": "guncelleme-sunucusu", "title": "…", "md": { "$file": "…" } }
```

Sayfa `_flxk_key` ile bulunur; elle değiştirilen sayfa Flux Setup'ın çakışma kuralıyla korunur. Ön ayar kaldırılınca sayfalar taslağa alınır.

## MCP (Flux AI)

Araçlar `flxwk_search`, `flxwk_get_page`, `flxwk_list_pages` (yetki `read`, salt okunur) ve `flxwk_save_page`
(`edit_posts`, değiştirir; yayımlama yetkisi yoksa `pending`). Kaynaklar `flxwiki://llms.txt`, `flxwiki://llms-full.txt`.
İstem `flxwk_build_plugin` (`task`). `flx_mcp_instructions`'a bir satır eklenir.

## Flux AI

Kaynak `flx-wiki`, amaç `wiki_ask`. Bağlam: okunan sayfa + aramada ilk 5 sayfa (~28.000 karakter). Saatlik sınır
kullanıcı ya da IP başına (yöneticiler hariç).

## Uygulama

Sayfa listesi `wp/v2/flx_wiki`. Okuma alanı `html` (CPT editör desteği yok; HTML `post_content` kopyası). Düzenleme alanı `markdown` (`_flxwk_md`; kayıt HTML kopyasını da günceller). Arama `GET /wp-json/flx-wiki/v1/search?q=` — boş `q` boş sonuç döner.


## Multilingual preset content

With Flux Lang active, `flx_wiki` is a translatable post type. Wiki preset items accept `language` (e.g. `tr`, `en`) and `translation_of` (the source item's unique preset key). Import the source before its translations. Use distinct keys/slugs and translated parent paths for each language. Each translation contains its own `title`, `excerpt` and `md`. Omitting these language fields preserves single-language presets.

`wiki_section` accepts `translations: {"en": {"name": "Setup", "description": "..."}, "tr": {"name": "Kurulum"}}`. Sections keep a shared slug. Page lists and search caches follow the requested Flux Lang language; live REST search carries `lang`.

`flxwk_intro` filters the displayed introduction string. Presets can localize their supplied introduction while preserving administrator edits.

Translated items may set `source_language` explicitly (e.g. `tr`) when linking an existing source whose language was not previously assigned. This sets the language relationship without replacing manually edited source content.

## Ask Wiki retrieval and controls

Ask Wiki searches every configured Flux Lang language using the normal spelling-aware Wiki search. Weak or missing lexical matches may trigger one bounded English query rewrite through Flux AI (`source=flx-wiki`, `purpose=wiki_ask`, 120 output tokens, 12 seconds), followed by one answer call (1,200 tokens, 45 seconds). Source context is capped at five translation groups and 28,000 characters. Answers are instructed to follow the question's language. Model answer quality still depends on source coverage and the configured model; these controls do not guarantee factual correctness.

Only `flx_wiki` posts with `publish` status and no password can enter model context, including when an editor supplies the current page ID. Page content is untrusted user data rather than system instructions. No tools are supplied or executed. Rendered output is sanitized; clickable links are limited to supplied source URLs and their fragments. This is a read-only answer path, not a code execution sandbox.

The REST endpoint validates 3–1,000 character questions and nonnegative page IDs. Existing `ask=off|users|all` access settings apply. `ask_limit` defaults to 20 per clock hour per user or salted IP identity (administrators remain exempt). An atomic database-backed in-flight gate prevents simultaneous requests for one visitor and serializes quota updates; abandoned gates expire after 90 seconds. Raw IP addresses are not stored by these controls. Shared Flux AI budgets remain enforced by `flxai_chat`. These are application controls, not distributed abuse protection.

Regression checks: run `FLXWK_TEST_SITE=1 php plugins/flx-wiki/tests/integration.php /path/to/wordpress` on a disposable WordPress site with Wiki, Lang and Setup active, English default and seeded TR/EN guides; keep the real Flux AI plugin inactive because the test supplies a deterministic provider stub. Tests verify retrieval and boundary behavior without spending an API budget.
