<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Forms logosu" width="120">

# Flux Forms

**Sitenize benzeyen, ödeme ve otomasyonları içinde sürükle-bırak formlar.**<br>
Flux Forms, Easy Form Builder'ın tüm özellikleri açık ve dışarıya hiçbir şey göndermeyen fork'udur. 50'den fazla alan türüyle iletişim, anket, destek ve fidan bağışı formları kurun, ödeme alın, yanıtları Dashboard'da izleyin; bağış ödenince otomasyonlar sertifikayı oluşturup göndersin.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Fork](https://img.shields.io/badge/fork-Easy%20Form%20Builder%204.2-2fbf8f)
![Telemetri yok](https://img.shields.io/badge/telemetri-yok-333)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Geliştiriciler için](#geliştiriciler-için) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux Forms?

Form eklentileri genelde işe yarayan kısımları (ödeme, koşullu mantık, dosya yükleme) lisansa bağlar ve bunu kontrol etmek için dışarıya bağlanır. Bize tamamen bizim olan, paylaşımlı sunucuda çalışan ve bağış ve sertifika paketlerimizle birlikte çalışan bir form gerekiyordu. Flux Forms bu:

- 🔓 **Her şey açık.** Lisans kontrolü, uzaktan eklenti indirme ve telemetri yok; Stripe, koşullu mantık ve çevrimdışı ülke/il listeleri yeniden yazıldı.
- 🎨 **Siteye benzer.** Formlar temanın renklerini, yazı tiplerini ve koyu modunu kendiliğinden kullanır; builder formu sitede görüneceği gibi gösterir.
- 📈 **Neyin işe yaradığını görün.** Form başına yanıt, görüntülenme, dönüşüm oranı ve ödeme; önceki dönemle karşılaştırmalı.
- 🌱 **Bağıştan sertifikaya, kendiliğinden.** Ödenen fidan bağışı sertifikayı oluşturur (Flux Certificates), WhatsApp ve e-postayla gönderir ve teşekkür ekranında gösterir.
- 🔐 **Sağlamlaştırıldı:** aslının üstüne stored XSS, takip kodu IDOR, yükleme klasörü ve IP tespiti düzeltmeleri.

## Özellikler

### Builder
- 50'den fazla eleman: metin, ad, e-posta, ülke seçicili telefon, sayı, tarih, dosya ve sürükle-bırak yükleme, adres ve ülke/il/ilçe listeleri, haritadan konum seçici (OpenStreetMap), imza, puanlama, NPS, görsel seçici, şartlar (KVKK), ödeme seçenekleri, tutar ve toplam alanları, ses/video/ekran kaydı ve dahası.
- Aramalı ve kategorili eleman paneli, alana tıklayınca açılan ayar paneli, son adım başlığı değiştirilebilen çok adımlı formlar.
- **Koşullu mantık:** alan/adım göster-gizle, zorunlu yap, devre dışı bırak, gönderimi engelle; kurallar sunucuda da uygulanır.
- Hazır şablonlar: İletişim, Destek, Anket, Ödeme, **Fidan Bağışı**, çok adımlı iletişim, müşteri geri bildirimi, giriş ve kayıt.

### Yanıtlar
- Tüm formlar ve kısa kodları `[EMS_Form_Builder id=3]` Panel'de; Elementor ve WPBakery widget'ları.
- Okunmamış işaretli yanıt listesi; ödeme bilgisi ve toplamla yanıt ayrıntısı, takip kodu, yanıtlama ve CSV.
- Flux Mail etkinse e-postalar onun ortak tasarımıyla gider (**İletişim › E-postalar**'da düzenlenebilir şablonlar).

### Dashboard
- Yanıt, okunmamış, form görüntülenmesi, dönüşüm oranı ve alınan ödeme; her biri önceki dönemle karşılaştırmalı.
- Günlük yanıt grafiği, ödeme durumu, forma göre yanıtlar, gün-saat ısı haritası ve son yanıtlar; form ve döneme göre süzgeç, CSV.

### Ödeme ve otomasyonlar
- Flux Donate varsa **Öde** adımı seçilen tutarı WooCommerce ödeme sayfasına taşır (kart, havale, aylık bağış); yoksa sunucuda tutar doğrulamalı ve webhook'lu yerleşik Stripe.
- **Otomasyonlar:** form gönderilince ya da ödeme tamamlanınca *Sertifika oluştur*, *Sertifika gönder*, *Teşekkür ekranında göster*, *E-posta gönder*, *Webhook* gibi adımlar çalışır. Hazır "Fidan bağışı → Sertifika" akışı tek tıkla kurulur.

## Kurulum

Gereksinimler: WordPress 6.4+, PHP 7.4+.

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-forms-v*` sürümünden `flx-forms.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux Forms* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Orijinal Easy Form Builder kuruluysa Flux Forms'u etkinleştirmeden önce onu **devre dışı bırakın (silmeyin)**: ikisi aynı tabloları kullanır, formlar ve yanıtlar olduğu gibi gelir.
2. **Formlar › Oluştur**: bir şablon seçin, düzenleyin ve **Kaydet**'e basın.
3. **Formlar › Panel**'deki kısa kodu bir sayfaya *Kısa kod* bloğuyla ekleyin.
4. Bağış için: **Formlar › Otomasyonlar › Varsayılan akışı oluştur** fidan bağışı formunu Flux Certificates'ya bağlar.

> ℹ️ Arayüz site diline uyar: Türkçe sitede Türkçe, diğerlerinde İngilizce.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/form-builder/KURULUM.md)

## Geliştiriciler için

- Tema stili `add_filter( 'flxf_theme_style', '__return_false' )` ile kapatılır; eski builder görünümü `flxf_builder_skin` ile.
- Dış ödeme (Flux Donate kullanır): `do_action( 'flxf_external_payment', $ctx )` bir yanıtın "ödeme tamamlandı" akışlarını takip kodu başına bir kez çalıştırır; `flxf_automations_form_issues_certificate( $form_id )` çift sertifikayı önler.
- Fidan fiyatı: `flxf_sapling_unit_price` filtresi (varsayılan 150 ₺; Flux Donate kuruluysa onun fiyatı geçerli).
- Ödeme sağlayıcısı rehberi: [docs/PAYMENT-PROVIDERS.md](docs/PAYMENT-PROVIDERS.md).

## Yol haritası

| | |
|---|---|
| ✅ | Tüm özellikleri açık, telemetrisiz Easy Form Builder fork'u |
| ✅ | Temaya uyan formlar ve yeni builder görünümü |
| ✅ | Görüntülenme, dönüşüm ve ödemeli Dashboard |
| ✅ | Flux Certificates ile otomasyonlar |
| ✅ | Flux Donate ile WooCommerce üzerinden ödeme |
| ⬜ | Uzun çok adımlı formlarda taslak kaydetme |
| ⬜ | reCAPTCHA'sız spam puanlama |

## Lisans

Flux Forms, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

Flux Forms, [WhiteStudio](https://whitestudio.team)'nun **Easy Form Builder 4.2.0** eklentisinin fork'udur (GPL v2+); orijinal telif ve lisans bildirimleri korunmuştur. Üçüncü taraf: Leaflet (BSD-2), Leaflet.FullScreen (MIT), country-state-city verisi (GPL-3.0).

FluxWP tarafından geliştirilmiştir.
