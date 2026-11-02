# Flux Lang: kurulum ve kullanım

Siteyi çok dilli yapar. Şimdilik Türkçe (varsayılan) + İngilizce; Almanca vb. panelden eklenir.

## Kurulum

1. Güncellemeler ekranından **Flux Lang**'i kurup etkinleştirin (ya da `flx-lang.zip` yükleyin).
2. **Ayarlar › Diller**: İngilizce etkin gelir. Önerilen ayarlar hazır, kaydetmeniz yeterli.
3. **Ayarlar › Diller › Dile göre site metinleri › English**: site adı, slogan, Flux SEO ana sayfa başlığı/açıklaması.
4. LiteSpeed Cache › **Purge All**.
5. Deneyin: `example.org/en/`. Menü, düğmeler ve tema metinleri İngilizce gelir; henüz çevrilmemiş sayfalar Türkçe içerikle açılır.

Migration yok: var olan her içerik Türkçe sayılır.

## İçerik çevirmek

- **Sayfalar / Yazılar listesi**: Başlığın yanındaki dil rozetleri. Koyu = içeriğin kendi dili, yeşil = çevirisi var (sarı: taslak), kesikli **+EN** = çevirisi yok, tıklayınca oluşturulur.
- **Düzenleme ekranı › Dil kutusu**: içeriğin dili, çevirileri, **Kopyala ve çevir** (kopya taslak açılır, metni siz çevirirsiniz) ya da Flux AI kuruluysa **AI ile çevir** (başlık, özet, SEO alanları ve gövde parça parça çevrilir, sonra düzenleyici açılır).
- Çeviri ayrı bir sayfadır: kendi adresi (ör. `/en/about-us/`), kendi öne çıkan görseli ve SEO alanları olur. Slug'ı İngilizce yapın.
- Çeviriyi **yayınlayın**; taslak çeviri sitede görünmez.
- **Kategoriler** her dilde ortak; adını kategori düzenleme ekranındaki "Ad (English)" alanından çevirin.
- **Dile özgü görsel**: Medya › görseli açın › "Alternatif metin (English)" ve "Görsel (English)" (o dilde yerine gösterilecek görselin ID'si; üstünde Türkçe yazı olan afişin İngilizcesi gibi). Şablondaki görseller dahil her yerde uygulanır.

## Yapay zekâ ile

Flux AI sohbetinde ya da MCP ile bağlı Claude'da şöyle isteyebilirsiniz:
"Çevrilmemiş sayfaları listele, Hakkımızda'yı İngilizceye çevir ve yayınla", "Haberler kategorisini İngilizcede News yap",
"Ana sayfa görselinin İngilizce sürümü şu URL olsun". Araçlar: `flxl_languages`, `flxl_untranslated`, `flxl_save_translation`,
`flxl_set_language`, `flxl_image_language`, `flxl_term_translation`, `flxl_site_texts`; Flux SEO araçlarında `lang` parametresi.

## Türkiye dışından gelen ziyaretçi

Türkiye dışından gelen ziyaretçi siteyi direkt İngilizce açar (tarayıcı dili etkin bir dilse o dil). Bant, soru yok.
- Karar sayfa çizilmeden verilir; Türkçe sayfa bir an görünmez. Sonraki girişlerde de aynı dilde açılır.
- Dil seçicide Türkçe seçen Türkçe kalır.
- Arama motoru botları yönlendirilmez (Google kuralı); her dil kendi adresinde dizinlenir.
- Ülke: sunucu ülke başlığı gönderiyorsa (Cloudflare `CF-IPCountry`) o, yoksa tarayıcı saat dilimi (Türkiye saati = Türkçe).
- Giriş yapmış kullanıcıya uygulanmaz; denemek için gizli pencere.
- Ayarlar › Diller'de "öneri bandı göster" ya da "kapalı" seçilebilir.

## Dil seçici

Dil seçici (TR · EN) şimdilik alt bilgide (footer), her ekranda görünür; başlıkta ve mobil menüde yok. Ayarlar › Diller › Dil seçicinin yeri: alt bilgi / başlık / ikisi. Başka yere: "Flux Lang Seçici" bloğu ya da `[flx_dil_secici]`.

## SEO

- Her sayfada `hreflang` (tr, en, x-default) yalnız gerçek çeviriler için.
- Türkçesiyle gösterilen (çevrilmemiş) sayfanın kanonik adresi Türkçe sayfadır; arama motoru kopya içerik görmez.
- Sitemap (`/wp-sitemap.xml`) tüm dillerin adreslerini içerir.
- Search Console'a ayrıca bir şey eklemek gerekmez.

## Yeni dil (ör. Almanca)

1. **Ayarlar › Genel › Site dili**'nden bir kez Deutsch seçip kaydedin (WordPress dil paketi iner), sonra Türkçeye geri alın.
2. **Ayarlar › Diller › Dil ekle › Deutsch** (pasif eklenir).
3. İçerikleri çevirin (AI ile), sonra **Etkin** işaretleyin. Tema ve FluxWP eklentilerinin Almanca arayüz çevirisi ayrıca gerekir.

## Sistem kontrolü

Diller, WordPress dil paketleri, sayfa çeviri sayısı, ziyaretçi ülkesi kaynağı, kalıcı bağlantılar.

Geliştirici API'si: `API.md`.
