SLUG='flx-lang'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-lang'; WP='6.4'; PHP='7.4'; DOCS='flx-lang'; API=True
C={
'en':dict(name='Flux Language',
 tagline='A light multilingual layer for WordPress: Turkish by default, English at /en/, AI translation through Flux AI.',
 desc='Flux Language makes the site multilingual without a heavy plugin. Each post, page, project or event can have its own English (or German…) version with its own slug, image and SEO; menus and links follow the language, and the language switcher sits in the footer. Visitors from outside Turkey land on English right away, and Flux AI can translate a page in one click.',
 badges=['![Languages](https://img.shields.io/badge/languages-TR%20%C2%B7%20EN%20%C2%B7%20%2B-0a7cff)','![SEO](https://img.shields.io/badge/SEO-hreflang%20%C2%B7%20sitemap-2fbf8f)','![AI](https://img.shields.io/badge/AI-one--click%20translation-7d64ff)'],
 gifalt='Flux Language tour: the Languages settings, language badges in the post list, the language box in the editor and the English front page with the footer switcher',
 why='''Multilingual plugins tend to be the heaviest thing on a WordPress site: extra tables, rewrite rules, a string translation screen with thousands of rows. A charity with Turkish content and an international audience needs less:

- 🌍 **English where it matters.** Translate the pages that count; anything not yet translated still opens in Turkish under /en/ instead of a 404.
- 🔗 **Links that follow the language.** Menus, buttons and links in content point to the English version automatically, without touching the theme.
- 🧭 **The right language first time.** Visitors from abroad get English before the page is drawn; their choice in the switcher is remembered.
- 🤖 **Translation in one click.** With Flux AI the title, excerpt, SEO fields and body are translated piece by piece and opened in the editor for a check.''',
 features='''### Content
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
- REST: `lang` and `translations` fields, `?lang=en` filter.''',
 shots=[('settings','Languages settings'),('posts','Language badges in the post list'),('editor','Language box in the editor'),('front-en','English front page'),('switcher','Footer language switcher'),('dark','Dark mode with Flux Admin'),('mobile','Mobile, English',True)],
 req='Optional: Flux AI for one-click translation, Flux SEO for per-language SEO, WooCommerce.',
 first='''1. Activate Flux Language and open **Settings › Languages**: English is on with recommended settings; save.
2. Under **Site texts by language › English** set the site name, tagline and home title.
3. Open **Pages**, click **+EN** next to a page, translate (or **Translate with AI**) and publish.
4. Purge the page cache and open `/en/`.''',
 note='> ℹ️ Existing content counts as Turkish; nothing is migrated. A draft translation is not shown on the site.',
 dev='''- `flxl_post_language()`, `flxl_get_translations()`, `flxl_get_translation()`, `flxl_link_translations()`; filter `flxseo_indexnow_urls` adds the other-language URLs.
- REST fields `lang` / `translations` on posts; see [API.md](../../docs/flx-lang/API.md).''',
 roadmap=[(1,'Per-language content with own slug, image and SEO'),(1,'Language links, switcher and visitor language'),(1,'hreflang and multilingual sitemap'),(1,'One-click AI translation through Flux AI'),(0,'German and Arabic interface strings'),(0,'Translation status report per section')]),
'tr':dict(name='Flux Lang',
 tagline="WordPress için hafif çok dil katmanı: varsayılan Türkçe, /en/ altında İngilizce, Flux AI ile çeviri.",
 desc="Flux Lang siteyi ağır bir eklenti olmadan çok dilli yapar. Her yazı, sayfa, proje ya da etkinliğin kendi slug'ı, görseli ve SEO'su olan İngilizce (ya da Almanca…) sürümü olabilir; menüler ve bağlantılar dile uyar, dil seçici alt bilgidedir. Türkiye dışından gelen ziyaretçi doğrudan İngilizce açar; Flux AI bir sayfayı tek tıkla çevirir.",
 badges=['![Diller](https://img.shields.io/badge/dil-TR%20%C2%B7%20EN%20%C2%B7%20%2B-0a7cff)','![SEO](https://img.shields.io/badge/SEO-hreflang%20%C2%B7%20sitemap-2fbf8f)','![AI](https://img.shields.io/badge/AI-tek%20tık%20çeviri-7d64ff)'],
 gifalt='Flux Lang turu: Diller ayarları, yazı listesinde dil rozetleri, editörde Dil kutusu ve alt bilgide dil seçicili İngilizce ana sayfa',
 why='''Çok dil eklentileri genelde bir WordPress sitesinin en ağır parçasıdır: ek tablolar, rewrite kuralları, binlerce satırlık metin çeviri ekranı. Türkçe içerikli ve uluslararası kitlesi olan bir derneğin ihtiyacı daha azdır:

- 🌍 **Gereken yerde İngilizce.** Önemli sayfaları çevirin; henüz çevrilmeyen her şey /en/ altında 404 yerine Türkçesiyle açılır.
- 🔗 **Dile uyan bağlantılar.** Menüler, düğmeler ve içerikteki bağlantılar temaya dokunmadan İngilizce sürüme gider.
- 🧭 **İlk seferde doğru dil.** Yurt dışından gelen ziyaretçi sayfa çizilmeden İngilizce görür; dil seçicideki tercihi hatırlanır.
- 🤖 **Tek tıkla çeviri.** Flux AI ile başlık, özet, SEO alanları ve gövde parça parça çevrilir, kontrol için editörde açılır.''',
 features='''### İçerik
- Yazı, sayfa, proje ve etkinlik başına ayrı dil sürümü: kendi başlık, gövde, slug, öne çıkan görsel ve SEO.
- Listelerde dil rozetleri: kendi dili, çevrildi, taslak ya da çeviri oluşturmak için **+EN**.
- Editörde Dil kutusu: dil, çeviriler, **Kopyala ve çevir** ya da **AI ile çevir**.
- Dile göre kategori/etiket adı ve açıklaması; dile göre görsel alt metni ve "İngilizcede bu görseli göster".
- Ana sayfa, yazılar sayfası ve WooCommerce sayfalarının dildeki karşılığı; sepet, ödeme ve hesabım ortak; siparişe ziyaretçinin dili yazılır.

### Site
- Varsayılan dil öneksiz, diğerleri `/en/…` altında; rewrite flush gerekmez. Tema ve eklenti metinleri kendi çevirilerinden gelir.
- Dile göre site adı, slogan ve Flux SEO ana sayfa başlığı.
- Alt bilgide (ya da başlık / mobil menüde) dil seçici, blok ve `[flx_dil_secici]` kısa kodu.
- Türkiye dışındaki ziyaretçi doğrudan İngilizceye (Cloudflare ülke başlığı ya da saat dilimi), botlar hariç; istenirse yerine öneri bandı.

### SEO ve AI
- `hreflang` + `x-default`, `og:locale:alternate`, kanonik, sitemap'te tüm diller; çevirisiz sayfaların kanoniği Türkçe.
- Flux AI ve MCP araçları: `flxl_languages`, `flxl_untranslated`, `flxl_save_translation`, `flxl_set_language`, `flxl_image_language`, `flxl_term_translation`, `flxl_site_texts`; Flux SEO içerik araçlarında `lang`.
- REST: `lang` ve `translations` alanları, `?lang=en` süzgeci.''',
 shots=[('settings','Diller ayarları'),('posts','Yazı listesinde dil rozetleri'),('editor','Editörde Dil kutusu'),('front-en','İngilizce ana sayfa'),('switcher','Alt bilgide dil seçici'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil, İngilizce',True)],
 req="İsteğe bağlı: tek tık çeviri için Flux AI, dile göre SEO için Flux SEO, WooCommerce.",
 first='''1. Flux Lang'i etkinleştirip **Ayarlar › Diller**'i açın: İngilizce önerilen ayarlarla açık gelir; kaydedin.
2. **Dile göre site metinleri › English**'te site adı, slogan ve ana sayfa başlığını girin.
3. **Sayfalar**'da bir sayfanın yanındaki **+EN**'e basın, çevirin (ya da **AI ile çevir**) ve yayınlayın.
4. Sayfa önbelleğini temizleyip `/en/` adresini açın.''',
 note="> ℹ️ Mevcut içerik Türkçe sayılır, taşıma yapılmaz. Taslak çeviri sitede görünmez.",
 dev='''- `flxl_post_language()`, `flxl_get_translations()`, `flxl_get_translation()`, `flxl_link_translations()`; `flxseo_indexnow_urls` süzgeci diğer dil adreslerini ekler.
- Yazılarda REST `lang` / `translations` alanları; bkz. [API.md](../../docs/flx-lang/API.md).''',
 roadmap=[(1,"Kendi slug'ı, görseli ve SEO'su olan dil sürümleri"),(1,'Dil bağlantıları, seçici ve ziyaretçi dili'),(1,'hreflang ve çok dilli sitemap'),(1,'Flux AI ile tek tık çeviri'),(0,'Almanca ve Arapça arayüz metinleri'),(0,'Bölüm bazında çeviri durumu raporu')]),
}
