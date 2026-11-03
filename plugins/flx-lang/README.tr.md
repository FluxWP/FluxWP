<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Lang logosu" width="120">

# Flux Lang

**WordPress için hafif çok dil katmanı: varsayılan Türkçe, /en/ altında İngilizce, Flux AI ile çeviri.**<br>
Flux Lang siteyi ağır bir eklenti olmadan çok dilli yapar. Her yazı, sayfa, proje ya da etkinliğin kendi slug'ı, görseli ve SEO'su olan İngilizce (ya da Almanca…) sürümü olabilir; menüler ve bağlantılar dile uyar, dil seçici alt bilgidedir. Türkiye dışından gelen ziyaretçi doğrudan İngilizce açar; Flux AI bir sayfayı tek tıkla çevirir.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Diller](https://img.shields.io/badge/dil-TR%20%C2%B7%20EN%20%C2%B7%20%2B-0a7cff)
![SEO](https://img.shields.io/badge/SEO-hreflang%20%C2%B7%20sitemap-2fbf8f)
![AI](https://img.shields.io/badge/AI-tek%20tık%20çeviri-7d64ff)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Ekran görüntüleri](#ekran-görüntüleri) · [Kurulum](#kurulum) · [Geliştiriciler için](#geliştiriciler-için) · [Yol haritası](#yol-haritası)

<img src="docs/demo-tr.gif" alt="Flux Lang turu: Diller ayarları, yazı listesinde dil rozetleri, editörde Dil kutusu ve alt bilgide dil seçicili İngilizce ana sayfa" width="900">

</div>

---

## Neden Flux Lang?

Çok dil eklentileri genelde bir WordPress sitesinin en ağır parçasıdır: ek tablolar, rewrite kuralları, binlerce satırlık metin çeviri ekranı. Türkçe içerikli ve uluslararası kitlesi olan bir derneğin ihtiyacı daha azdır:

- 🌍 **Gereken yerde İngilizce.** Önemli sayfaları çevirin; henüz çevrilmeyen her şey /en/ altında 404 yerine Türkçesiyle açılır.
- 🔗 **Dile uyan bağlantılar.** Menüler, düğmeler ve içerikteki bağlantılar temaya dokunmadan İngilizce sürüme gider.
- 🧭 **İlk seferde doğru dil.** Yurt dışından gelen ziyaretçi sayfa çizilmeden İngilizce görür; dil seçicideki tercihi hatırlanır.
- 🤖 **Tek tıkla çeviri.** Flux AI ile başlık, özet, SEO alanları ve gövde parça parça çevrilir, kontrol için editörde açılır.

## Özellikler

### İçerik
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
- REST: `lang` ve `translations` alanları, `?lang=en` süzgeci.

## Ekran görüntüleri

| Diller ayarları | Yazı listesinde dil rozetleri |
|---|---|
| <img src="docs/images/settings-tr.jpg" alt="Diller ayarları" width="440"> | <img src="docs/images/posts-tr.jpg" alt="Yazı listesinde dil rozetleri" width="440"> |
| **Editörde Dil kutusu** | **İngilizce ana sayfa** |
| <img src="docs/images/editor-tr.jpg" alt="Editörde Dil kutusu" width="440"> | <img src="docs/images/front-en-tr.jpg" alt="İngilizce ana sayfa" width="440"> |
| **Alt bilgide dil seçici** | **Flux Admin ile koyu tema** |
| <img src="docs/images/switcher-tr.jpg" alt="Alt bilgide dil seçici" width="440"> | <img src="docs/images/dark-tr.jpg" alt="Flux Admin ile koyu tema" width="440"> |
| **Mobil, İngilizce** |   |
| <img src="docs/images/mobile-tr.jpg" alt="Mobil, İngilizce" width="260"> |   |

## Kurulum

Gereksinimler: WordPress 6.4+, PHP 7.4+. İsteğe bağlı: tek tık çeviri için Flux AI, dile göre SEO için Flux SEO, WooCommerce.

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-lang-v*` sürümünden `flx-lang.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**FluxWP Panel ile** (sitelerimizde önerilen)

**FluxWP Panel › Paketler** tüm FluxWP paketlerini listeler; *Flux Lang* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Flux Lang'i etkinleştirip **Ayarlar › Diller**'i açın: İngilizce önerilen ayarlarla açık gelir; kaydedin.
2. **Dile göre site metinleri › English**'te site adı, slogan ve ana sayfa başlığını girin.
3. **Sayfalar**'da bir sayfanın yanındaki **+EN**'e basın, çevirin (ya da **AI ile çevir**) ve yayınlayın.
4. Sayfa önbelleğini temizleyip `/en/` adresini açın.

> ℹ️ Mevcut içerik Türkçe sayılır, taşıma yapılmaz. Taslak çeviri sitede görünmez.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/flx-lang/KURULUM.md) · API başvurusu: [API.md](../../docs/flx-lang/API.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Geliştiriciler için

- `flxl_post_language()`, `flxl_get_translations()`, `flxl_get_translation()`, `flxl_link_translations()`; `flxseo_indexnow_urls` süzgeci diğer dil adreslerini ekler.
- Yazılarda REST `lang` / `translations` alanları; bkz. [API.md](../../docs/flx-lang/API.md).

## Yol haritası

| | |
|---|---|
| ✅ | Kendi slug'ı, görseli ve SEO'su olan dil sürümleri |
| ✅ | Dil bağlantıları, seçici ve ziyaretçi dili |
| ✅ | hreflang ve çok dilli sitemap |
| ✅ | Flux AI ile tek tık çeviri |
| ⬜ | Almanca ve Arapça arayüz metinleri |
| ⬜ | Bölüm bazında çeviri durumu raporu |

## Lisans

Flux Lang, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.
