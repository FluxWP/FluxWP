# Flux SEO (flx-seo v1.0.12)

## Kurulum
1. Eklentiler › Yeni ekle › Eklenti yükle › `flx-seo.zip` › Etkinleştir.
2. sol menüde **SEO**. Varsayılanlar dolu gelir (site adı, ana sayfa başlık/açıklama, kurum bilgileri); kontrol edip kaydedin.
3. Yoast / Rank Math / AIOSEO / SEOPress etkinse meta ve schema çıktısı otomatik kapanır (çakışma olmaz). Favicon, SEO kontrolü ve yapay zekâ araçları yine çalışır.

## Neler yapıyor
- `<title>`, meta açıklama, canonical, robots (sepet/ödeme/hesabım, arama, 404, etiket sayfaları noindex; v1.0.11: RSS beslemeleri `X-Robots-Tag: noindex, follow`, etiketler sitemap'ten çıktı, ayar: Dizine ekleme › Etiket sayfaları), Open Graph, Twitter kartı. v1.0.12: SEO başlık/açıklamalarında İstanbul vurgusu kaldırıldı (değiştirilmemiş eski metinler kendiliğinden güncellenir), schema areaServed Türkiye.
- Schema (JSON-LD): NGO kurumu, WebSite (site adı + arama kutusu), WebPage, Breadcrumb, haberlerde NewsArticle.
- Sitemap: `/wp-sitemap.xml` (`/sitemap.xml` yönlenir), kullanıcı listesi çıkarıldı, noindex içerikler hariç, lastmod eklendi. robots.txt'ye sitemap ve gereksiz URL'ler için Disallow.
- Favicon: `/favicon.ico`, 48/96/192 PNG, Apple ikon, `site.webmanifest`. Google SVG favicon'u her zaman kullanmıyordu; asıl sorun buydu.
- Yazı/sayfa editöründe sağda **SEO** paneli: SEO başlığı, açıklama, odak kelime, Google önizlemesi, noindex. Kategori/etiketlerde de SEO alanları var.
- Yazı listelerinde SEO sütunu; SEO › **SEO kontrolü** sekmesinde tüm sitenin eksik listesi.
- Alt metni boş görsellere otomatik alt metin (ayarlardan kapatılabilir).
- **"Fidan bağışı" hedeflemesi (v1.0.6)**: Bağış, Fidan Dikim Videomuz ve Fidan Nasıl Dikilir sayfalarına SEO başlığı, açıklaması ve odak kelimesi yazılır (alan boşsa, bir kez; sayfanın SEO panelinden değiştirilebilir). /etkinlikler/ ve /projeler/ arşivlerine başlık ve açıklama. Schema: kuruma DonateAction (bağış sayfası), areaServed İstanbul, knowsAbout; sayfadaki "Ayrıntılar" (details) bloklarından otomatik FAQPage (en az 2 soru).
- **Sistem Kontrolü (v1.0.8)**: Örnek Vakfı temasının Görünüm › Sistem Kontrolü ekranına SEO satırları ekler (SEO çıktısı, site haritası, tarayıcı önbelleği kuralları, site simgesi, kurum bilgileri, Search Console doğrulaması, MCP durumu). Dış istek yapmaz.
- **Sistem Kontrolü MCP ile (v1.0.9)**: yapay zekâ `system_checks` ile Görünüm › Sistem Kontrolü'nün tüm satırlarını okur; izinli düzeltmeleri (`system_fixes`) `system_fix` ile uygular (saat dilimi, hazır güncellemeler, GA4, havale hesabı, SEO kurum bilgileri vb.). Yalnızca yönetici anahtarıyla; her değişiklik önceki/sonraki değerle kaydedilir (`system_fix_log`, Sistem Kontrolü ekranının altında). Diğer eklentiler için: MCP-API.md.
- Tarayıcı önbelleği: `.htaccess`'e görseller için 30 gün, CSS/JS/font için 1 yıl önbellek kuralı yazılır (admin paneline ilk girişte, Apache/LiteSpeed). Sayfa yenilenince görseller tekrar inmez. Eklenti devre dışı bırakılınca kurallar silinir. `.htaccess` yazılamıyorsa kurallar eklenmez.

## Google Search Console
1. https://search.google.com/search-console › Mülk ekle › URL ön eki: `https://example.org/`.
2. "HTML etiketi" yöntemi: verilen `<meta …>` etiketini SEO › Genel › Google Search Console alanına yapıştırın, kaydedin, Doğrula.
3. Site haritaları › `wp-sitemap.xml` gönderin.
4. URL denetimi › ana sayfa › "Dizine eklenmeyi iste" (favicon ve site adının yenilenmesini hızlandırır).

## Favicon ve sitelinks hakkında
- **Favicon**: Google favicon'u kendi takvimiyle yeniden tarar; birkaç gün–birkaç hafta sürebilir. Search Console'dan ana sayfa için dizine ekleme istemek hızlandırır.
- **Sitelinks** (sonuçların altındaki menü linkleri): Google bunları otomatik seçer, elle eklenemez. Eklentinin yaptığı: WebSite schema'da site adı, breadcrumb, temiz menü/sitemap. Site trafik ve yaş kazandıkça çıkar. Menüde net sayfa adları (Hakkımızda, Bağış Yap, Haberler, İletişim) tutmak yardımcı olur.

## MCP (yapay zekâ ile site yönetimi)
Flux SEO 1.5.0'dan beri MCP sunucusu **Flux AI**'da (0.7.0+); Flux SEO araçlarını oraya kaydeder. Eski anahtarlar kendiliğinden taşınır, eski "Yapay zekâ (MCP)" sekmesi Flux AI › MCP'ye yönlenir.

1. **Flux AI › MCP** › "MCP sunucusu etkin" › Kaydet.
2. Ad yazıp **Yeni anahtar oluştur**. Anahtar bir kez gösterilir; kopyalayın. Anahtar, oluşturan kullanıcının yetkileriyle çalışır.
3. Bağlantı (sayfadaki hazır kodları kopyalayabilirsiniz):
   - **Claude Code**: `claude mcp add --transport http sitem https://example.org/wp-json/flx-mcp/v1/mcp --header "Authorization: Bearer ANAHTAR"`
   - **Claude Desktop / Cursor**: `npx mcp-remote` ile (config sayfada hazır).
   - **Claude.ai** › Ayarlar › Bağlayıcılar › Özel bağlayıcı: `https://example.org/wp-json/flx-mcp/v1/mcp?key=ANAHTAR` (bu adresi kimseyle paylaşmayın).
4. Apache'de "Geçersiz anahtar" çıkarsa `.htaccess`'e: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` ya da `?key=` adresi.

Araçlar: site_info, list_content, get_content, create_content (markdown/HTML › blok, tema kalıpları), update_content, delete_content (çöpe), set_seo, seo_audit, list_patterns, get_pattern, upload_media, list_media, set_image_alt, list_terms, update_term, update_site_seo.
Hazır komutlar: `seo_duzelt`, `haber_yaz`, `sayfa_kur`.

Örnek istekler:
- "example.org'dan aktarılan haberlerin SEO eksiklerini bul ve düzelt."
- "Alt metni olmayan görsellere anlamlı alt metin yaz."
- "Tema kalıplarıyla Kurumsal İş Birlikleri sayfası kur, taslak olarak bırak."

Güvenlik: yeni içerikler varsayılan **taslak** açılır, silme yalnızca çöpe taşır, son işlemler Flux AI › MCP ekranında loglanır, anahtarlar hash'li saklanır ve tek tıkla iptal edilir.
