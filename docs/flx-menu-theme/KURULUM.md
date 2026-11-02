# Flux Menu Theme (block theme)

menu.example.org için en sade tema. Ana sayfa tamamen boş ve beyazdır (üst/alt bilgi yok; cihaz koyu temada olsa da beyaz).
QR menüleri **Flux QR Menu** eklentisi yayınlar: `/menu/`, `/menu1/`, `menu1.menu.example.org` gibi adresler ve isteğe bağlı olarak ana sayfa
(QR Menüler › Ayarlar › Ana sayfa). Eklenti bu adreslerde sayfayı kendisi üretir; tema karışmaz.

## Kurulum
1. Görünüm › Temalar › Tema Yükle › `flx-menu-theme.zip` › Etkinleştir.
2. **Görünüm › Site Kurulumu** › Seçili adımları çalıştır (kalıcı bağlantılar, saat dilimi, tarih biçimi, dil, varsayılan içerik, yorumlar).
3. Tema güncellemesinden sonra panoda "hazır güncelleme" uyarısı çıkarsa Site Kurulumu › Hazır güncellemeler › Uygula.
4. Kurulumun geri kalanı (menü adresi, alt alan adı, menü yöneticisi, SSL) için Flux QR Menu'nün `KURULUM.md` dosyasına bakın.

## Tema Ayarları (Görünüm › Tema Ayarları)
Hepsi varsayılan işaretli (= özellik kapalı); işaret kaldırılınca WordPress'in varsayılan davranışı geri gelir.

| Anahtar | Kapalıyken |
|---|---|
| Yazılar | Yazılar menüsü ve "+ Yeni › Yazı" yok; tek yazı, kategori, etiket, tarih, yazar arşivleri 404; site haritasında yazı/kategori/kullanıcı yok |
| Yorumlar | Yorum ve geri bildirim her yerde kapalı; Yorumlar menüsü/pano kutusu yok; `wp-comments-post.php` 403; yorum beslemesi 404 |
| RSS beslemeleri | `/feed/` 404, başlıkta besleme bağlantısı yok |
| Arama | `?s=` 404 |
| Ek sayfaları | Ek adresi dosyanın kendisine 301 |
| XML-RPC | `xmlrpc.php` 403 |
| Emoji betiği | Emoji betiği/stili yüklenmez |
| oEmbed | Gömme bağlantıları yok, `/embed/` 404 |
| Kullanıcı listesi | Giriş yapmamışa `/wp/v2/users` 404 |

Site haritasında sayfalar ve Flux QR Menu'nün bu alan adındaki menü adresleri kalır. Flux QR Menu sayfaları, REST uçları ve menü yöneticisi paneli etkilenmez.

## Hazır güncellemeler (geliştirici)
Yeni bir site ayarı değişikliği `inc/kurulum.php` › `yf_kurulum_migrations()` dizisine bir kayıt (`id => title, desc, run`) eklenerek yayınlanır; tekrar çalıştırmak güvenli olmalı. Uygulananlar `yf_migrations` seçeneğinde (`id => zaman`).

## Şablonlar
| Şablon | İçerik |
|---|---|
| Ana sayfa (`front-page`) | Boş beyaz sayfa |
| Sayfa / tek yazı | Başlık + içerik, ortalanmış (680 px) |
| Liste (`index`) | Yazı başlıkları ve özetleri |
| 404 | "Sayfa bulunamadı" + ana sayfa bağlantısı |

Çeviri: Türkçe kaynak, `languages/en_US.*` İngilizce. Dizeler değişince `python3 tools/genstr.py` (İngilizceler `tools/en.json`).
