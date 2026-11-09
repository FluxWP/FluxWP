<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux SEO logosu" width="120">

# Flux SEO

**WordPress için hafif SEO, ayrıca Claude ve diğer yapay zekâ asistanlarının MCP ile kullandığı içerik ve SEO araçları.**<br>
Flux SEO bir dernek sitesinin bulunması için gerekenleri ekler: meta başlık ve açıklama, canonical, keskin WhatsApp önizlemeli Open Graph, schema (Organization, WebSite, Breadcrumb, Article), sitemap düzenlemeleri, IndexNow, favicon ve tarayıcı önbelleği; her sayfa için SEO kontrolüyle. Yapay zekâ araçları Flux AI'daki ortak FluxWP MCP sunucusuna bağlanır; Claude Desktop, Claude Code, Cursor ya da claude.ai kullanıcıya özel anahtarla içeriği listeler, yazar ve düzeltir.

[![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![MCP](https://img.shields.io/badge/MCP-sunucu-7d64ff)
![Schema](https://img.shields.io/badge/schema-Organization%20%C2%B7%20Article%20%C2%B7%20Breadcrumb-2fbf8f)
![IndexNow](https://img.shields.io/badge/IndexNow-Bing%20%C2%B7%20Yandex%20%C2%B7%20Naver-0a7cff)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Ekran görüntüleri](#ekran-görüntüleri) · [Kurulum](#kurulum) · [Yapay zekâ asistanlarıyla kullanım (MCP)](#yapay-zekâ-asistanlarıyla-kullanım-mcp) · [Yol haritası](#yol-haritası)

<img src="docs/demo-tr.gif" alt="Flux SEO turu: genel ayarlar, editörde Google önizlemeli SEO paneli, site geneli SEO kontrolü ve MCP anahtarları" width="900">

</div>

---

## Neden Flux SEO?

Büyük SEO eklentileri onlarca ekran, satış teklifleri ve kendi veritabanı tablolarıyla gelir; küçük bir ekibin ihtiyacı temellerin doğru yapılması ve öyle kalmasıdır. Flux SEO kısa tutar:

- 🔎 **Temeller doğru.** Başlık, açıklama, canonical, Open Graph, schema ve sitemap makul varsayılanlarla; kurulum sihirbazı yok.
- ✅ **Düzeltilecekler listesi.** SEO kontrolü her sayfayı okur, eksikleri söyler: uzun başlık, boş açıklama, öne çıkan görsel yok, kısa içerik, iç bağlantı yok.
- 🤖 **Asistan yapsın.** Claude'u (ya da herhangi bir MCP istemcisini) anahtarla bağlayın, "SEO eksiklerini düzelt" deyin: aynı listeyi okur, alanları doldurur; anahtarı oluşturan kullanıcının yetkileriyle.
- 📣 **Arama motorlarına anında haber.** IndexNow, sayfa yayınlanınca ya da güncellenince Bing, Yandex, Seznam ve Naver'e bildirir.

## Özellikler

### Site denetimi (PageSpeed)
- PageSpeed Insights / Lighthouse'un tüm başlıklarını bilir: Performans, Erişilebilirlik, En iyi uygulamalar, SEO ve Ajan uyumu. Bulgular **kritik, yüksek, orta, düşük** sırasıyla; nasıl düzeltilir notu, sorunlu öğeler ve düzeltme yeri (tema, içerik, sunucu, tek tık).
- Anahtarsız site içi denetim; her gün ya da haftada bir seçilen gün ve saatte otomatik (isteğe bağlı PageSpeed dahil, yeni kritik/yüksek öneride e-posta). **Rapor geçmişi**: eğilim grafiği, yeni ve çözülen öneriler, MCP aracı `site_audit_history`; isteğe bağlı PageSpeed Insights API ölçümü (mobil + masaüstü, anahtar panelden).
- Ajan uyumu: otomatik `/llms.txt`, arama formunda WebMCP, isteğe bağlı X-Frame-Options, COOP, temel CSP, HSTS.
- **Site yapım rehberi** (TR + EN): yapay zekâ ajanlarına MCP aracı `site_build_guide` ve kaynak `flxseo://guide/site-build` olarak; `site_audit` aracı ajanlar ve Flux AI için.

### SEO
- Yazı, sayfa ve terim başına meta başlık ve açıklama; ayıraç ve ana sayfa başlığı; boşsa içerikten otomatik açıklama.
- **Editör paneli:** Google önizlemesi, karakter sayaçları, kontrollü odak anahtar kelime (başlıkta, açıklamada, içerikte, H2, uzunluk, görsel alt metni), noindex anahtarı.
- Canonical, robots meta, Open Graph ve Twitter kartları; WhatsApp önizlemesi keskin kalsın diye `og:image` 1200×630 JPEG kopyası.
- Schema: Organization ya da NGO (ad, logo, adres, iletişim, kuruluş yılı, sosyal profiller), site adlı WebSite, BreadcrumbList, Article.
- Sitemap: noindex içerik, mağaza özel sayfaları, yazarlar ve (isteğe bağlı) etiketler çıkarılır; `lastmod` eklenir; `/sitemap.xml` → `/wp-sitemap.xml`.
- Sepet/ödeme/hesap, arama sonuçları, yazar arşivleri ve etiketler için noindex; Google, Bing ve Yandex doğrulama.
- Anahtar dosyası kendiliğinden sunulan **IndexNow** ve "Tüm siteyi şimdi bildir" düğmesi.
- Tek görselden favicon seti, otomatik görsel alt metni, Apache ve LiteSpeed için tarayıcı önbelleği kuralları.
- **SEO kontrolü:** yayındaki her sayfa sorunları ve önerileriyle.

### Yapay zekâ araçları (MCP)
- MCP sunucusu, anahtarlar ve kayıt **Flux AI & MCP › MCP** ekranında (`/wp-json/flx-mcp/v1/mcp`, Flux SEO 1.5.0 / Flux AI 0.7.0'dan beri). Flux SEO araçlarını oraya kaydeder; Flux AI sohbeti de aynı araçları kullanır.
- Araçlar: site bilgisi, içerik listele/getir/oluştur/güncelle/sil, SEO ayarla, SEO denetimi, desenler, medya yükleme ve alt metin, terimler, site SEO ayarları; "SEO eksiklerini düzelt", "haber yaz" ve "sayfa kur" istemleri.
- Diğer FluxWP eklentileri kendi araçlarını `flx_mcp_tools` ile ekler (eski ad `flxseo_mcp_tools` de çalışır).
- Yeni içerik taslak açılır; silme yalnız çöpe taşır. Her çağrı kayda geçer.

## Ekran görüntüleri

| Genel ayarlar | Editörde SEO paneli |
|---|---|
| <img src="docs/images/general-tr.jpg" alt="Genel ayarlar" width="440"> | <img src="docs/images/editor-tr.jpg" alt="Editörde SEO paneli" width="440"> |
| **Kurum ve sosyal profiller** | **SEO kontrolü** |
| <img src="docs/images/org-tr.jpg" alt="Kurum ve sosyal profiller" width="440"> | <img src="docs/images/audit-tr.jpg" alt="SEO kontrolü" width="440"> |
| **MCP sunucusu ve anahtarlar** | **Flux Admin ile koyu tema** |
| <img src="docs/images/mcp-tr.jpg" alt="MCP sunucusu ve anahtarlar" width="440"> | <img src="docs/images/dark-tr.jpg" alt="Flux Admin ile koyu tema" width="440"> |
| **Mobil SEO kontrolü** |   |
| <img src="docs/images/mobile-tr.jpg" alt="Mobil SEO kontrolü" width="260"> |   |

## Kurulum

Gereksinimler: WordPress 6.5+, PHP 7.4+. Yapay zekâ erişimi isterseniz bir MCP istemcisi (Claude Desktop, Claude Code, Cursor, claude.ai).

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-seo-v*` sürümünden `flx-seo.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**FluxWP Panel ile** (sitelerimizde önerilen)

**FluxWP Panel › Paketler** tüm FluxWP paketlerini listeler; *Flux SEO* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Flux SEO'yu etkinleştirip **SEO › Genel**'i açın: site adını, ana sayfa başlığını ve açıklamasını kontrol edin.
2. Google bilgi paneli için **Kurum ve sosyal**'i doldurun.
3. **SEO kontrolü**'nü açıp listeyi elle ya da asistanla bitirin.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/seo-mcp/KURULUM.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Yapay zekâ asistanlarıyla kullanım (MCP)

1. Flux AI 0.7.0+ kurun, **Flux AI & MCP › MCP**'yi açın: MCP sunucusunu açın, bir anahtar oluşturun (isterseniz bazı araçlarla sınırlı).
2. İstemcinize ekleyin, örneğin Claude Code:

```bash
claude mcp add --transport http sitem https://example.org/wp-json/flx-mcp/v1/mcp --header "Authorization: Bearer ANAHTARINIZ"
```

3. Sorun: *"SEO eksiği olan yazıları listele ve düzelt."* Asistan anahtarı oluşturan kullanıcının yetkileriyle çalışır.

Kendi eklentinizden araç eklemek: [Flux AI API.md › MCP](../../docs/flx-ai/API.md#mcp-sunucusu-070) ve [MCP-API.md](../../docs/seo-mcp/MCP-API.md) (Flux SEO araçları ve sistem düzeltmeleri).

## Yol haritası

| | |
|---|---|
| ✅ | Meta, Open Graph, schema ve sitemap |
| ✅ | SEO kontrolü ve editör paneli |
| ✅ | MCP ile yapay zekâ araçları (sunucu Flux AI'da) |
| ✅ | IndexNow ve keskin WhatsApp önizlemesi |
| ⬜ | İngilizce ayar ekranları |
| ⬜ | Değişen adresler için yönlendirme yöneticisi |

## Lisans

Flux SEO, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.

`/category-sitemap.xml` gibi alışılmış alt sitemap adresleri, yalnız etkin ve dolu WordPress sitemap’ine yönlendirilir. Bilinmeyen, hariç tutulan ve aralık dışı adresler 404 kalır.
