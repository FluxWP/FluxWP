<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux AI logosu" width="120">

# Flux AI

**Tüm FluxWP eklentileri için tek yapay zekâ merkezi: anahtarlar, modeller, maliyet, sınır ve ortak sohbet.**<br>
Flux AI, FluxWP paketlerinin ortak yapay zekâ katmanıdır. OpenRouter ve Anthropic anahtarları, model kataloğu ve fiyatlar, prompt önbelleği, araç kaydı, kullanım kayıtları, faturalandırma ve harcama sınırları tek yerde durur; Flux Chat, Flux Mail, Flux Social, Flux Lang, Flux Studio ve diğerleri AI sağlayıcılarıyla kendisi konuşmak yerine bunu çağırır. Yetkili kullanıcılar panelde bu araçları kullanabilen akışlı bir AI sohbeti de alır.

[![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Sağlayıcılar](https://img.shields.io/badge/sağlayıcı-OpenRouter%20%C2%B7%20Anthropic-7d64ff)
![Medya](https://img.shields.io/badge/medya-fal.ai%20%C2%B7%20OpenAI%20%C2%B7%20Gemini-2fbf8f)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Geliştiriciler için](#geliştiriciler-için) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux AI?

Her eklenti kendi AI bağlantısını getirince anahtarlar beş ayar sayfasına dağılır, ayın neye mal olduğu bilinmez, gece 3'te dönen bir döngüyü durduracak bir şey olmaz. Flux AI araya tek bir katman koyar:

- 🔑 **Anahtarlar tek yerde.** OpenRouter ve Anthropic anahtarı bir kez girilir, şifreli saklanır, tarayıcıya hiç gitmez.
- 💸 **Görünen ve sınırlanan maliyet.** Uyarı eşikli günlük, haftalık ve aylık sınır; her istek token, önbellek, süre ve maliyetiyle kayıtlı.
- 🧩 **Sistem başına tek anahtar.** Hangi eklentinin hangi modeli kullandığını, ne harcadığını, ne sıklıkla hata aldığını görün; ayarlarına dokunmadan kapatın.
- 💬 **İş yapabilen sohbet.** Ortak sohbet diğer eklentilerin kaydettiği araçları görür, bir şeyi değiştirmeden önce onay ister, kullanıcıya yalnız yetkisi olan araçları gösterir.

## Özellikler

### Diğer eklentiler için merkez
- Araçlı, akışlı, prompt önbellekli (Claude) ve yedek modelli `flxai_chat()` / `flxai_complete()`; `flxai_ready()`, `flxai_model_for()`, `flxai_budget_status()`.
- **Bağlı sistemler:** her eklentinin durumu, modeli, 30 günlük isteği, token'ı, maliyeti, hatası ve son kullanımı; sistem başına aç/kapa; Ayarlar'da eklenti başına model.
- **Araçlar:** `flxai_tools` ya da Flux SEO ile kaydedilen her araç; kaynak, okur/yazar, onay, yetki, 30 günlük çağrı ve ortalama süre; araç başına aç/kapa.
- Cevapta görsel: ```widget / ```mermaid blokları Gotenberg ya da Imagick ile PNG'ye çevrilir.
- Flux Studio için fal.ai, EvoLink, OpenAI, Gemini ve OpenRouter ile görsel ve video üretimi (`flxai_media_submit`).

### Maliyet ve sınırlar
- **Genel bakış:** sınırlara karşı günlük, haftalık ve aylık harcama; maliyet, istek, token (giriş, çıkış, önbellekten), model ve araç diye bölünmüş toplam süre, hatalar; 7 ile 180 gün arası günlük grafikler, eklenti süzgeci.
- **Faturalandırma:** önbellek tasarruflu aylık döküm; sağlayıcıya, eklentiye ve modele göre; CSV indirme.
- **Kayıtlar:** her model isteği ve araç çağrısı (nerede, kim, model ya da araç, token, önbellek, süre, maliyet); içerik maskeli saklanır (e-posta, telefon, IBAN, anahtar), varsayılan 30 gün.
- Harcama sınırında engelle ya da yalnız uyar; uyarı yüzdesi.

### Ortak sohbet
- Paneldeki AI düğmesi yanaşık ya da yüzen bir sohbet açar: sohbet geçmişi, arama, model seçici, sayfaya göre öneriler, akışlı cevaplar.
- Değişiklikler sohbette kullanıcı onayı ister; her an durdurulur; mesaj düzenlenir ya da önceki bir noktaya dönülür.
- Sohbet sayfa geçişlerinde kaldığı yerden sürer.

## Kurulum

Gereksinimler: WordPress 6.5+, PHP 7.4+. OpenRouter ya da Anthropic API anahtarı. İsteğe bağlı: cevapta görsel için Gotenberg ya da Imagick, Flux Studio için fal.ai / EvoLink / OpenAI / Gemini anahtarları.

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-ai-v*` sürümünden `flx-ai.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux AI* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Flux AI'yi etkinleştirip **Flux AI › Ayarlar**'ı açın.
2. OpenRouter anahtarını (ya da Anthropic anahtarını) girin, varsayılan modeli seçip **Test**'e basın.
3. Günlük, haftalık ve aylık sınırları girin.
4. **Bağlı sistemler**'de hangi eklentilerin Flux AI'yi kullandığını görün; isterseniz eklenti başına model seçin.

> ⚠️ AI kullanan her FluxWP paketi için Flux AI zorunludur; hiçbiri kendi AI bağlantısını tutmaz. Flux Chat'nde kayıtlı eski anahtarlar buraya kendiliğinden taşınır.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/flx-ai/KURULUM.md) · API başvurusu: [API.md](../../docs/flx-ai/API.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Geliştiriciler için

- `flxai_chat( $messages, [ 'source' => 'eklentim', 'tools' => …, 'stream' => true ] )`, `flxai_complete( $prompt )`.
- Sistemi `flxai_systems` ile, sohbet araçlarını `flxai_tools` ile kaydedin; kendi çağrılarınızı `flxai_log()` ile yazın.
- Medya: `flxai_media_submit()`, `flxai_media_status()`, `flxai_media_cancel()`; REST `/wp-json/flx-ai/v1` altında.

## Yol haritası

| | |
|---|---|
| ✅ | Tek anahtar deposu, model kataloğu ve fiyatlar |
| ✅ | Kullanım kayıtları, faturalandırma ve harcama sınırları |
| ✅ | Bağlı sistemler ve araç kaydı |
| ✅ | Onaylı, akışlı ortak sohbet |
| ✅ | Flux Studio için görsel ve video üretimi |
| ⬜ | Kullanıcı ve rol başına bütçe |
| ⬜ | Yerel model desteği (Ollama) |

## Lisans

Flux AI, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.
