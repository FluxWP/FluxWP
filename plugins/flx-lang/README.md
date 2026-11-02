<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Language logo" width="120">

# Flux Language

**A light multilingual layer for WordPress: Turkish by default, English at /en/, AI translation through Flux AI.**<br>
Flux Language makes the site multilingual without a heavy plugin. Each post, page, project or event can have its own English (or German…) version with its own slug, image and SEO; menus and links follow the language, and the language switcher sits in the footer. Visitors from outside Turkey land on English right away, and Flux AI can translate a page in one click.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Languages](https://img.shields.io/badge/languages-TR%20%C2%B7%20EN%20%C2%B7%20%2B-0a7cff)
![SEO](https://img.shields.io/badge/SEO-hreflang%20%C2%B7%20sitemap-2fbf8f)
![AI](https://img.shields.io/badge/AI-one--click%20translation-7d64ff)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Installation](#installation) · [For developers](#for-developers) · [Roadmap](#roadmap)

</div>

---

## Why Flux Language?

Multilingual plugins tend to be the heaviest thing on a WordPress site: extra tables, rewrite rules, a string translation screen with thousands of rows. A charity with Turkish content and an international audience needs less:

- 🌍 **English where it matters.** Translate the pages that count; anything not yet translated still opens in Turkish under /en/ instead of a 404.
- 🔗 **Links that follow the language.** Menus, buttons and links in content point to the English version automatically, without touching the theme.
- 🧭 **The right language first time.** Visitors from abroad get English before the page is drawn; their choice in the switcher is remembered.
- 🤖 **Translation in one click.** With Flux AI the title, excerpt, SEO fields and body are translated piece by piece and opened in the editor for a check.

## Features

### Content
- Separate language version per post, page, project and event: own title, body, slug, featured image and SEO.
- Language badges in the lists: own language, translated, draft, or **+EN** to create the translation.
- Language box in the editor: language, translations, **Copy and translate** or **Translate with AI**.
- Category and tag names and descriptions per language; per-language image alt text and "show this image in English".
- Home page, posts page and WooCommerce pages mapped per language; cart, checkout and account are shared; orders store the visitor's language.

### Site
- Default language without prefix, others under `/en/…`; no rewrite flush needed. Theme and plugin texts come from their own translations.
- Site name, tagline and Flux SEO home title per language.
- Language switcher in the footer (or header / mobile menu), a block and the `[flx_dil_secici]` shortcode.
- Visitors outside Turkey go straight to English (Cloudflare country header or time zone), bots excluded; optional suggestion bar instead.

### SEO and AI
- `hreflang` + `x-default`, `og:locale:alternate`, canonical, all languages in the sitemap; fallback pages point canonical to Turkish.
- Tools for Flux AI and MCP: `flxl_languages`, `flxl_untranslated`, `flxl_save_translation`, `flxl_set_language`, `flxl_image_language`, `flxl_term_translation`, `flxl_site_texts`; `lang` on Flux SEO content tools.
- REST: `lang` and `translations` fields, `?lang=en` filter.

## Installation

Requirements: WordPress 6.4+, PHP 7.4+. Optional: Flux AI for one-click translation, Flux SEO for per-language SEO, WooCommerce.

**From a zip**

1. Download `flx-lang.zip` from the latest `flx-lang-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With Flux Updater** (recommended on our sites)

**Dashboard › Updates** lists every FluxWP package; click **Install** next to *Flux Language*. Updates arrive the same way.

**First steps**

1. Activate Flux Language and open **Settings › Languages**: English is on with recommended settings; save.
2. Under **Site texts by language › English** set the site name, tagline and home title.
3. Open **Pages**, click **+EN** next to a page, translate (or **Translate with AI**) and publish.
4. Purge the page cache and open `/en/`.

> ℹ️ Existing content counts as Turkish; nothing is migrated. A draft translation is not shown on the site.

Full setup notes (Turkish): [KURULUM.md](../../docs/flx-lang/KURULUM.md) · API reference: [API.md](../../docs/flx-lang/API.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## For developers

- `flxl_post_language()`, `flxl_get_translations()`, `flxl_get_translation()`, `flxl_link_translations()`; filter `flxseo_indexnow_urls` adds the other-language URLs.
- REST fields `lang` / `translations` on posts; see [API.md](../../docs/flx-lang/API.md).

## Roadmap

| | |
|---|---|
| ✅ | Per-language content with own slug, image and SEO |
| ✅ | Language links, switcher and visitor language |
| ✅ | hreflang and multilingual sitemap |
| ✅ | One-click AI translation through Flux AI |
| ⬜ | German and Arabic interface strings |
| ⬜ | Translation status report per section |

## License

Flux Language Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
