# Flux Social kurulum (0.1.0)

Sitede çalışan sosyal medya planlayıcı. Mixpost/Postiz gerekmez.

## 1. Kur
1. Güncellemeler'den ya da flx-social.zip ile kur, etkinleştir. Sol menüde **Sosyal Medya** çıkar.
2. Gerekli: PHP sodium (açık), HTTPS. Önerilen: Flux AI (metin yardımı), Flux Studio (görsel), Flux Mail veya Flux Chat (WhatsApp).
3. Sistem kontrolü'nde Flux Social satırları yeşil olmalı.

## 2. Zamanlayıcı (önemli)
Hostinger'da WP-Cron güvenilmez. **Ayarlar › Genel › Dış tetikleme adresi**ni kopyala, cron-job.org'da her 1 dakikaya kur. Panel açıkken de gönderiler kendiliğinden yollanır.

## 3. Uygulama anahtarları (Ayarlar › Uygulamalar)
Her ağ için geliştirici uygulaması aç, anahtarları gir, ekrandaki **yönlendirme adresini** uygulamaya ekle. Adres biçimi: `https://example.org/wp-json/flx-social/v1/oauth/callback/<uygulama>`.

| Ağ | Konsol | Not |
|---|---|---|
| Facebook + Instagram | developers.facebook.com/apps | İşletme türü uygulama, Facebook Girişi. Kendi sayfaların için App Review gerekmez (uygulamada yönetici ol). Sayfaya bağlı Instagram aynı girişle gelir. |
| Instagram girişi | aynı Meta uygulaması › Instagram ürünü | Facebook sayfası olmadan; hesap İşletme/İçerik üretici. |
| YouTube + Google İşletme | console.cloud.google.com | YouTube Data API v3 (+ İşletme API'leri). Denetimsiz projede videolar "Özel" yüklenir. İşletme API'si için Google onayı gerekir. |
| X | developer.x.com | Read and write, Web App. Katmanı Ayarlar › Ağlar'dan seç: Free (düşük yazma sınırı, metrik yok), Basic/Pro (ücretli), Pay-as-you-go. Aylık sınır girilebilir. |
| LinkedIn | linkedin.com/developers | Sign In with LinkedIn (OIDC) + Share on LinkedIn. Şirket sayfası için Community Management API (onay). |
| TikTok | developers.tiktok.com | Login Kit + Content Posting API. Audit yoksa **Taslak** modu: video TikTok uygulamasına düşer. Doğrudan modda audit'siz yalnız "Sadece ben". |
| Pinterest | developers.pinterest.com | Trial erişimde sandbox seçeneğini aç; Standard için başvur. |
| WhatsApp | (anahtar yok) | Evolution bağlantısı Flux Mail/Çağrı Merkezi'nden. Durum ve gruplar. |

Gizli alanlar şifreli saklanır, ekranda gösterilmez. Değiştirmek için yeniden yaz.

## 4. Hesap bağla
**Hesaplar › Hesap ekle** › ağı seç › izin ver › sayfa/kanal seç. Süresi dolan hesap sarı uyarı gösterir; **Yeniden bağlan**.

## 5. Kullanım
- **Yeni gönderi**: hesapları seç, ortak metin yaz, gerekirse hesap sekmesinde ayrı metin/medya/seçenek. Taslak, Onaya gönder, Zamanla, Şimdi paylaş.
- **Onay**: `flxsm_post` yetkisi olan yazar onaya gönderir, `flxsm_publish` onaylar. Ayarlar › Genel'de onay zorunlu yapılabilir. Yetkiler Flux Roles'den.
- **Takvim**: sürükle-bırak ile saat değiştir.
- **Pano**: paylaşım, etkileşim, takipçi, en iyi gönderiler (metrikler günlük çekilir; X Free'de yok).
- **Otomatik paylaşım** (Ayarlar): sitede yazı yayınlanınca şablonla gönderi hazırlar.
- **MCP**: Flux AI & MCP üzerinden AI ile gönderi oluştur/zamanla/gönder (bkz. API.md).

## Test durumu
0.1.0 yerel WordPress'te sahte verilerle denendi (arayüz, kuyruk, onay, MCP, otomatik paylaşım). **Gerçek ağ API'leri anahtarsız denenmedi**; ilk bağlantıda hata olursa Ayarlar › Günlük ekranını gönder.
