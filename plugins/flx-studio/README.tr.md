<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Studio logosu" width="120">

# Flux Studio

**WordPress içinde tüm ekip için, tek bütçeyle yapay zekâ görsel ve video stüdyosu.**<br>
Flux Studio ekibinizin panelden yapay zekâyla görsel ve video üretmesini sağlar: ne istediğinizi yazın, model seçin, referans görsel ya da kişi ekleyin, üretin. Sonuçlar klasörlü, favorili, şablonlu ortak bir galeriye düşer ve doğrudan Medya kitaplığına gidebilir. Üretim Flux AI üzerinden yapılır; anahtarlar, maliyet ve harcama sınırı tek yerde kalır.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Modeller](https://img.shields.io/badge/model-Nano%20Banana%20%C2%B7%20Seedream%20%C2%B7%20Imagen%20%C2%B7%20GPT%20Image-7d64ff)
![Video](https://img.shields.io/badge/video-var-2fbf8f)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Geliştiriciler için](#geliştiriciler-için) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux Studio?

Etkinlik afişi, haber görseli, video küçük resmi: küçük bir ekibin her hafta görsele ihtiyacı olur ve herkes farklı bir AI sitesinde, farklı bir kartla çalışır. Flux Studio bunu WordPress'e taşır:

- 🎨 **Ekip için tek yer.** Yetkisi olan herkes aynı stüdyoda üretir; ortak klasörler ve şablonlar tarzı tutarlı tutar.
- 💸 **Tek bütçe.** Her üretim Flux AI'den geçer, günlük, haftalık ve aylık sınıra sayılır; pano kimin hangi modelde ne harcadığını gösterir.
- 🧠 **Zahmetsiz daha iyi istem.** *Geliştir* kaba bir fikri seçili modele uygun ayrıntılı isteme çevirir; *Beyin fırtınası* seçmek için birkaç yön verir.
- 🖼️ **Doğrudan siteye.** Sonucu tek tıkla Medya kitaplığına ekleyip yazıda, sayfada, kampanyada kullanın.

## Özellikler

### Üret
- Görsel ve video; sağlayıcı ve modele göre model seçici (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter), en-boy, çözünürlük, kalite, adet, süre ve ses; düğmede tahmini fiyat.
- **Referanslar:** yükle, yapıştır, sürükle ya da galeriden seç; her birine rol ver (stil, ürün, arka plan, kişi, ilk/son kare); sık kullanılanları kaydet.
- **Kişiler:** kişi başına 1–8 yüz fotoğrafı; istemde `@ad` yazınca kimlik referansı olur.
- **Geliştir** ve **Beyin fırtınası** (bir fikirden 2–8 istem, genel ya da YouTube küçük resmi), tek seferde üretim.
- **Alan düzenleme:** fırça, dikdörtgen ve silgiyle maske; yalnız maskeli yer değişir; önce/sonra kaydırıcısı.
- Anında kuyruk: "Sırada" kartı hemen görünür, uzun işler sayfa kapansa da sürer.

### Düzenle
- Klasör ağaçlı galeri (alt klasör, ekiple paylaşım, sürükleyerek taşıma), favoriler, arama, çoklu seçim, sonsuz kaydırma.
- Görüntüleyici: indir, ayarları tekrar kullan, referans yap, Medya kitaplığına ekle, taşı, sil.
- **Şablonlar:** istem, ayar ve referanslar kaydedilir, ekiple paylaşılır.

### Yönet
- **Pano:** üretim, harcama (görsel/video ve metin), üretim başına ortalama, hata oranı, kişi ve depolama; günlük grafikler; model, sağlayıcı ve kişi tabloları; Flux AI sınırı ve son hatalar.
- Flux Roles'de listelenen `flxst_use` ve `flxst_manage` yetkileri; Flux Admin ile açık ve koyu tema; telefonda ve Flux App'da yerel ekran olarak çalışır.

## Kurulum

Gereksinimler: WordPress 6.4+, PHP 7.4+. Flux AI 0.6.0+ ve en az bir görsel/video anahtarı (fal.ai ya da EvoLink önerilir; OpenAI, Gemini ya da OpenRouter da olur).

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-studio-v*` sürümünden `flx-studio.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux Studio* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. **Flux AI › Ayarlar › Görsel ve video**'ya bir sağlayıcı anahtarı girin.
2. Flux Studio'yu etkinleştirin; panel menüsünde **Stüdyo** çıkar.
3. Personel kullanacaksa Flux Roles'de **Stüdyo: görsel/video üret** yetkisini verin.
4. Bir istem yazıp **Üret**'e basın (Ctrl/⌘+Enter).

> ⚠️ Video dakikalar sürebilir; sayfa kapansa da iş devam eder. Harcama Flux AI sınırına sayılır. Bu ekranlardaki demo galeride sitedeki mevcut fotoğraflar kullanıldı.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/flx-studio/KURULUM.md) · API başvurusu: [API.md](../../docs/flx-studio/API.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Geliştiriciler için

- `/wp-json/flx-studio/v1` altında REST API (boot, items, generate, poll, enhance, brainstorm, folders, people, refs, templates); masaüstü ve mobil uygulama için Flux App belirteçleriyle çalışır.
- Flux AI'ye (`flxai_systems`), Flux Roles'e, Flux Admin uygulama moduna ve sistem kontrollerine kayıtlı.

## Yol haritası

| | |
|---|---|
| ✅ | Flux AI üzerinden görsel ve video üretimi |
| ✅ | Referans, kişi, geliştir ve beyin fırtınası |
| ✅ | Klasör, şablon ve ekip paylaşımı |
| ✅ | Model ve kişi başına harcamalı pano |
| ✅ | Flux App'da yerel ekran |
| ⬜ | Referans olarak marka kiti (renk, logo, font) |
| ⬜ | Sosyal medya boyutlarına toplu kırpma |

## Lisans

Flux Studio, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

İş akışı ve istem kuralları [Banana Studio](https://github.com/orkunisitmak/banana-studio)'dan (MIT) uyarlandı. Bkz. [NOTICE.md](NOTICE.md).

FluxWP tarafından geliştirilmiştir.
