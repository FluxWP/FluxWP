<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Social logosu" width="120">

# Flux Social

**Sosyal medya gönderilerini WordPress'ten planlayın, onaylayın, paylaşın.**<br>
Flux Social sitenin içinde çalışan bir sosyal medya planlayıcıdır: Facebook, Instagram, YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme ve WhatsApp hesaplarını bağlayın; bir kez yazıp hesap başına ayrı metin verin, takvimde zamanlayın, gönderileri onaydan geçirin, paylaşım, etkileşim ve takipçileri tek panoda görün. Mixpost ya da Postiz sunucusu gerekmez.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Ağlar](https://img.shields.io/badge/ağ-9-1877f2)
![MCP](https://img.shields.io/badge/yapay%20zekâ-MCP%20araçları-7d64ff)
![Mixpost esinli](https://img.shields.io/badge/esin-Mixpost%20(MIT)-lightgrey)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Yapay zekâ asistanlarıyla kullanım (MCP)](#yapay-zekâ-asistanlarıyla-kullanım-mcp) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux Social?

Mixpost ve Postiz gibi kendi sunucunuzda çalışan planlayıcılar ayrı sunucu, Redis ve kuyruk işçisi ister; paylaşımlı hostingde bunlar yok. Hazır servisler derneğin hesaplarını ve taslaklarını başkasının aracına koyar, kişi başı ücret alır. Flux Social işe yarayan kısmı WordPress içinde yapar:

- 🗓️ **Tek düzenleyici, her ağ.** Hesap başına değiştirilebilen ortak metin, karakter sayaçları, medya kuralları ve her ağ için canlı önizleme.
- ✅ **Onay hazır.** Gönüllüler hazırlar, yönetici onaylar; yetkiler Flux Roles'den gelir.
- 📊 **Rakamlar tek yerde.** Ağ başına günlük paylaşım, etkileşim, görüntülenme, takipçi artışı ve en iyi gönderiler.
- ⏱️ **Paylaşımlı hostingde çalışır.** Dış tetikleme adresi (cron-job.org) ya da açık panel zamanlanmış gönderileri yollar; işçi gerekmez.
- 🤖 **Yazdığınız yerde AI.** Flux AI metni yazmaya ve ağlara uyarlamaya yardım eder; asistanlar MCP ile gönderi planlar.

## Özellikler

### Yazma ve zamanlama
- **Yeni gönderi:** hesapları seçin, ortak metin ya da hesap başına ayrı metin yazın; ortam kütüphanesinden ya da Flux Studio'dan görsel veya video ekleyin; imza, etiket, bağlantılara UTM.
- Ağ başına canlı önizleme, paylaşmadan önce karakter ve medya sınırı kontrolü, **AI ile yaz** ve **Ağlara uyarla** (Flux AI).
- Taslak kaydet, onaya gönder, zamanla, şimdi paylaş; gönderiyi N gün sonra yeniden paylaş.
- **Takvim:** ay ve liste görünümü, sürükle-bırak ile taşıma, hesaba göre süzme.

### Ekip ve onay
- **Onay bekleyenler** listesi: paylaşma yetkisi olmayanların gönderileri onaylayanı bekler.
- Flux Roles üzerinden `flxsm_post`, `flxsm_publish`, `flxsm_manage` yetkileri; gönderi listesinde *Yalnız benimkiler* süzgeci.

### Pano ve hesaplar
- 7, 30 ya da 90 günlük **Pano**: paylaşım, etkileşim, görüntülenme, takipçi, zamanlanmış, hata; ağ başına günlük paylaşım, ağ tablosu, takipçi eğrileri, en iyi gönderiler, yaklaşan gönderiler.
- **Hesaplar:** Facebook sayfaları, Instagram (Facebook sayfalı ya da sayfasız), YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme Profili, WhatsApp durum ve grupları (Flux Mail ya da Flux Chat üzerinden).
- API izin veriyorsa paylaşılmış gönderiyi ağda düzenleme ya da silme; metrikler günde bir çekilir.

### Ayarlar
- Ağ başına uygulama anahtarları (şifreli), X katmanı ve aylık sınır, sitede yazı yayınlanınca otomatik paylaşım, dış tetikleme adresi, günlük.

## Kurulum

Gereksinimler: WordPress 6.4+, PHP 7.4+. PHP sodium ve HTTPS. Bağlayacağınız her ağ için bir geliştirici uygulaması (Meta, Google, X, LinkedIn, TikTok, Pinterest). İsteğe bağlı: Flux AI (yazım yardımı), Flux Studio (görsel), Flux Mail ya da Flux Chat (WhatsApp), Flux Roles (yetkiler).

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-social-v*` sürümünden `flx-social.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux Social* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Flux Social'i etkinleştirin; menüde **Sosyal Medya** çıkar.
2. **Ayarlar › Genel**: dış tetikleme adresini kopyalayıp cron-job.org'a dakikada bir ekleyin.
3. **Ayarlar › Uygulamalar**: her ağın anahtarlarını girin, ekrandaki yönlendirme adresini geliştirici uygulamasına ekleyin.
4. **Hesaplar › Hesap bağla**, ağı seçin, izin verin, sayfayı ya da kanalı seçin.
5. **Yeni gönderi**, hesapları seçin, yazın, **Zamanla**.

> ⚠️ Gerçek ağ API'leri her ağın geliştirici uygulamasını, bazen de inceleme onayını ister (LinkedIn şirket sayfası, TikTok doğrudan paylaşım, Google İşletme). X Free'de yazma sınırı düşük, metrik yok. Flux Social'in veri modeli [Mixpost](https://github.com/inovector/mixpost)'tan (MIT) esinlenmiştir; kod WordPress için baştan yazıldı, bkz. [NOTICE.md](NOTICE.md).

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/sosyal-medya/KURULUM.md) · API başvurusu: [API.md](../../docs/sosyal-medya/API.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Yapay zekâ asistanlarıyla kullanım (MCP)

Flux Social sitenin MCP sunucusuna (**Flux SEO**) ve **Flux AI** sohbetine araçlar ekler:

- `flxsm_accounts`, `flxsm_posts`, `flxsm_post_get`, `flxsm_stats`, `flxsm_time`
- `flxsm_post_create`, `flxsm_post_update`, `flxsm_post_delete`, `flxsm_remote_edit`, `flxsm_ai_write`

> "11 Kasım için Instagram ve Facebook'a bir gönderi yaz, saat 10:00'a zamanla." · "Bu ay en çok etkileşim alan gönderi hangisi?"

Asistanın hazırladığı gönderiler de insanlarla aynı onay kurallarına uyar.

## Yol haritası

| | |
|---|---|
| ✅ | Hesap başına metinli ve canlı önizlemeli düzenleyici |
| ✅ | Takvim, onay ve pano |
| ✅ | WhatsApp durum dahil dokuz ağ |
| ✅ | Flux AI yazım yardımı ve MCP araçları |
| ⬜ | Yorum ve mesaj gelen kutusu |
| ⬜ | En iyi paylaşım saati önerisi |
| ⬜ | İlk yorum ve hikâye zamanlama |

## Lisans

Flux Social, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.
