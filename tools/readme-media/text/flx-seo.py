SLUG='flx-seo'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-seo'; WP='6.5'; PHP='7.4'; DOCS='seo-mcp'; API=False
C={
'en':dict(name='Flux SEO',
 tagline='Lightweight SEO for WordPress, plus an MCP server so Claude and other AI assistants can manage your content.',
 desc='Flux SEO adds what a charity site needs to be found: meta titles and descriptions, canonical, Open Graph with sharp WhatsApp previews, schema (Organization, WebSite, Breadcrumb, Article), sitemap fixes, IndexNow, favicon and browser caching, with an SEO check for every page. It also runs an MCP server, so Claude Desktop, Claude Code, Cursor or claude.ai can list, write and fix content with a per-user key.',
 badges=['![MCP](https://img.shields.io/badge/MCP-server-7d64ff)','![Schema](https://img.shields.io/badge/schema-Organization%20%C2%B7%20Article%20%C2%B7%20Breadcrumb-2fbf8f)','![IndexNow](https://img.shields.io/badge/IndexNow-Bing%20%C2%B7%20Yandex%20%C2%B7%20Naver-0a7cff)'],
 gifalt='Flux SEO tour: general settings, the SEO panel in the editor with a Google preview, the site-wide SEO check and the MCP keys',
 why='''Big SEO plugins bring dozens of screens, upsells and their own database tables; a small team only needs the basics done right and a way to keep them done. Flux SEO keeps it short:

- 🔎 **The basics, correct.** Titles, descriptions, canonical, Open Graph, schema and sitemap with sensible defaults, no setup wizard.
- ✅ **A list of what to fix.** The SEO check reads every page and says what is missing: long titles, empty descriptions, no featured image, thin content, no internal links.
- 🤖 **Let the assistant do it.** Connect Claude (or any MCP client) with a key and say "fix the SEO gaps": it reads the same list and fills the fields, with the permissions of the user who made the key.
- 📣 **Search engines told at once.** IndexNow pings Bing, Yandex, Seznam and Naver when a page is published or updated.'''.replace('\n \n','\n\n'),
 features='''### SEO
- Meta title and description per post, page and term, with a separator and home page title; automatic description from the content when empty.
- **Editor panel:** Google preview, character counters, focus keyword with checks (in title, description, content, H2, length, image alt), noindex toggle.
- Canonical, robots meta, Open Graph and Twitter cards; `og:image` saved as a 1200×630 JPEG copy so WhatsApp previews stay sharp.
- Schema: Organization or NGO (name, logo, address, contact, founding year, social profiles), WebSite with site name, BreadcrumbList, Article.
- Sitemap: noindex content, shop-only pages, authors and (optionally) tags removed; `lastmod` added; `/sitemap.xml` redirects to `/wp-sitemap.xml`.
- Noindex for cart/checkout/account, search results, author archives and tags; search engine verification for Google, Bing and Yandex.
- **IndexNow** with its key file served automatically and a "Notify the whole site" button.
- Favicon set from one image, automatic image alt text, browser caching rules for Apache and LiteSpeed.
- **SEO check:** every published page with its problems and suggestions.

### MCP server
- `/wp-json/flx-mcp/v1/mcp` with per-user keys; keys can be limited to chosen tools; last use shown.
- Tools: site info, list/get/create/update/delete content, set SEO, SEO audit, patterns, media upload and alt text, terms, site SEO settings; prompts for "fix SEO gaps", "write a news post" and "build a page".
- Other FluxWP plugins add their own tools through `flxseo_mcp_tools` (orders, system checks and fixes are already there).
- New content is created as a draft; delete only moves to trash. Every call is logged.''',
 shots=[('general','General settings'),('editor','SEO panel in the editor'),('org','Organisation and social profiles'),('audit','SEO check'),('mcp','MCP server and keys'),('dark','Dark mode with Flux Admin'),('mobile','Mobile SEO check',True)],
 req='An MCP client (Claude Desktop, Claude Code, Cursor, claude.ai) if you want AI access.',
 first='''1. Activate Flux SEO and open **SEO & MCP › General**: check the site name, home title and description.
2. Fill **Organisation and social** so Google can show your knowledge panel.
3. Open **SEO check** and work through the list, by hand or with the assistant.''',
 note='> ℹ️ The settings screens are in Turkish for now; front-end output follows the site language.',
 mcp='''1. **SEO & MCP › AI (MCP)**: turn on the MCP server and create a key (optionally limited to some tools).
2. Add it to your client, for example Claude Code:

```bash
claude mcp add --transport http yesil-turkiye https://example.org/wp-json/flx-mcp/v1/mcp --header "Authorization: Bearer YOUR_KEY"
```

3. Ask: *"List posts with SEO gaps and fix them."* The assistant works with the permissions of the user who created the key.

Adding tools from your own plugin: [MCP-API.md](../../docs/seo-mcp/MCP-API.md).''',
 roadmap=[(1,'Meta, Open Graph, schema and sitemap'),(1,'SEO check and editor panel'),(1,'MCP server with scoped keys'),(1,'IndexNow and sharp WhatsApp previews'),(0,'English settings screens'),(0,'Redirect manager for changed slugs')]),
'tr':dict(name='Flux SEO',
 tagline='WordPress için hafif SEO, ayrıca Claude ve diğer yapay zekâ asistanlarının içeriği yönetebilmesi için MCP sunucusu.',
 desc="Flux SEO bir dernek sitesinin bulunması için gerekenleri ekler: meta başlık ve açıklama, canonical, keskin WhatsApp önizlemeli Open Graph, schema (Organization, WebSite, Breadcrumb, Article), sitemap düzenlemeleri, IndexNow, favicon ve tarayıcı önbelleği; her sayfa için SEO kontrolüyle. Ayrıca bir MCP sunucusu çalıştırır; Claude Desktop, Claude Code, Cursor ya da claude.ai kullanıcıya özel anahtarla içeriği listeler, yazar ve düzeltir.",
 badges=['![MCP](https://img.shields.io/badge/MCP-sunucu-7d64ff)','![Schema](https://img.shields.io/badge/schema-Organization%20%C2%B7%20Article%20%C2%B7%20Breadcrumb-2fbf8f)','![IndexNow](https://img.shields.io/badge/IndexNow-Bing%20%C2%B7%20Yandex%20%C2%B7%20Naver-0a7cff)'],
 gifalt='Flux SEO turu: genel ayarlar, editörde Google önizlemeli SEO paneli, site geneli SEO kontrolü ve MCP anahtarları',
 why='''Büyük SEO eklentileri onlarca ekran, satış teklifleri ve kendi veritabanı tablolarıyla gelir; küçük bir ekibin ihtiyacı temellerin doğru yapılması ve öyle kalmasıdır. Flux SEO kısa tutar:

- 🔎 **Temeller doğru.** Başlık, açıklama, canonical, Open Graph, schema ve sitemap makul varsayılanlarla; kurulum sihirbazı yok.
- ✅ **Düzeltilecekler listesi.** SEO kontrolü her sayfayı okur, eksikleri söyler: uzun başlık, boş açıklama, öne çıkan görsel yok, kısa içerik, iç bağlantı yok.
- 🤖 **Asistan yapsın.** Claude'u (ya da herhangi bir MCP istemcisini) anahtarla bağlayın, "SEO eksiklerini düzelt" deyin: aynı listeyi okur, alanları doldurur; anahtarı oluşturan kullanıcının yetkileriyle.
- 📣 **Arama motorlarına anında haber.** IndexNow, sayfa yayınlanınca ya da güncellenince Bing, Yandex, Seznam ve Naver'e bildirir.''',
 features='''### SEO
- Yazı, sayfa ve terim başına meta başlık ve açıklama; ayıraç ve ana sayfa başlığı; boşsa içerikten otomatik açıklama.
- **Editör paneli:** Google önizlemesi, karakter sayaçları, kontrollü odak anahtar kelime (başlıkta, açıklamada, içerikte, H2, uzunluk, görsel alt metni), noindex anahtarı.
- Canonical, robots meta, Open Graph ve Twitter kartları; WhatsApp önizlemesi keskin kalsın diye `og:image` 1200×630 JPEG kopyası.
- Schema: Organization ya da NGO (ad, logo, adres, iletişim, kuruluş yılı, sosyal profiller), site adlı WebSite, BreadcrumbList, Article.
- Sitemap: noindex içerik, mağaza özel sayfaları, yazarlar ve (isteğe bağlı) etiketler çıkarılır; `lastmod` eklenir; `/sitemap.xml` → `/wp-sitemap.xml`.
- Sepet/ödeme/hesap, arama sonuçları, yazar arşivleri ve etiketler için noindex; Google, Bing ve Yandex doğrulama.
- Anahtar dosyası kendiliğinden sunulan **IndexNow** ve "Tüm siteyi şimdi bildir" düğmesi.
- Tek görselden favicon seti, otomatik görsel alt metni, Apache ve LiteSpeed için tarayıcı önbelleği kuralları.
- **SEO kontrolü:** yayındaki her sayfa sorunları ve önerileriyle.

### MCP sunucusu
- Kullanıcıya özel anahtarlı `/wp-json/flx-mcp/v1/mcp`; anahtar seçilen araçlarla sınırlanabilir; son kullanım görünür.
- Araçlar: site bilgisi, içerik listele/getir/oluştur/güncelle/sil, SEO ayarla, SEO denetimi, desenler, medya yükleme ve alt metin, terimler, site SEO ayarları; "SEO eksiklerini düzelt", "haber yaz" ve "sayfa kur" istemleri.
- Diğer FluxWP eklentileri kendi araçlarını `flxseo_mcp_tools` ile ekler (siparişler, sistem kontrolleri ve düzeltmeleri hazır).
- Yeni içerik taslak açılır; silme yalnız çöpe taşır. Her çağrı kayda geçer.''',
 shots=[('general','Genel ayarlar'),('editor','Editörde SEO paneli'),('org','Kurum ve sosyal profiller'),('audit','SEO kontrolü'),('mcp','MCP sunucusu ve anahtarlar'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil SEO kontrolü',True)],
 req='Yapay zekâ erişimi isterseniz bir MCP istemcisi (Claude Desktop, Claude Code, Cursor, claude.ai).',
 first='''1. Flux SEO'yu etkinleştirip **SEO & MCP › Genel**'i açın: site adını, ana sayfa başlığını ve açıklamasını kontrol edin.
2. Google bilgi paneli için **Kurum ve sosyal**'i doldurun.
3. **SEO kontrolü**'nü açıp listeyi elle ya da asistanla bitirin.''',
 mcp='''1. **SEO & MCP › Yapay zekâ (MCP)**: MCP sunucusunu açın, bir anahtar oluşturun (isterseniz bazı araçlarla sınırlı).
2. İstemcinize ekleyin, örneğin Claude Code:

```bash
claude mcp add --transport http yesil-turkiye https://example.org/wp-json/flx-mcp/v1/mcp --header "Authorization: Bearer ANAHTARINIZ"
```

3. Sorun: *"SEO eksiği olan yazıları listele ve düzelt."* Asistan anahtarı oluşturan kullanıcının yetkileriyle çalışır.

Kendi eklentinizden araç eklemek: [MCP-API.md](../../docs/seo-mcp/MCP-API.md).''',
 roadmap=[(1,'Meta, Open Graph, schema ve sitemap'),(1,'SEO kontrolü ve editör paneli'),(1,'Kapsamlı anahtarlı MCP sunucusu'),(1,'IndexNow ve keskin WhatsApp önizlemesi'),(0,'İngilizce ayar ekranları'),(0,'Değişen adresler için yönlendirme yöneticisi')]),
}
