# Flux QR Menu — Değişiklikler

## 1.3.2
- **WooCommerce kuruluyken menü yöneticisi panele girebilir.** WooCommerce, yazı düzenleme yetkisi olmayan kullanıcıyı wp-admin'den "Hesabım" sayfasına gönderiyor ve yönetim çubuğunu gizliyordu; menü yöneticisi artık QR Menüler'e girer, yönetim çubuğu görünür, "Hesabım" girişinden de QR Menüler'e yönlenir.

## 1.3.1
- **Menü tarihe göre kapanmaz.** Ayarlar sekmesindeki "Kapanış" seçimi yerine **Menü aktif** kutusu: işaretliyken menü açık, kaldırılınca ziyaretçiler teşekkür sayfasını görür. Son etkinlik gününden sonra kendiliğinden teşekkür sayfasına geçiş kalktı; daha önce "Otomatik" bırakılmış menüler açık sayılır (menü sitelerine giden veride de `kapanis: false`). Geçmişte "Menü aktif edildi / Menü kapatıldı" olarak görünür.

## 1.3.0
- **Örnek menünün görselleri eklentiyle gelir.** "Yenikapı Tanıtım Günleri" örnek menüsünün 24 ürün görseli eklentinin içinde (`assets/sample/img/`, en çok 1000 px, ~2,4 MB). Örnek menüden yeni menü açılınca görseller ağdan indirilmeden **medya kütüphanesine eklenir** ve menüde tam adresle yazılır; menü sitesi bağlı olmasa da (ör. tek başına WordPress) görseller hemen görünür. Daha önce eklenmiş örnek görsel tekrar eklenmez, aynı ek kullanılır. Eklerde yalnız küçük resim ve orta boy üretilir (24 görsel ~2,5 sn). Dosya yükleme yetkisi olmayan kullanıcıda ya da eklenemeyen görselde göreli yol kalır.
- **Görselleri medya kütüphanesine aktar** önce eklentiyle gelen örnek görsele bakar: 1.1/1.2'de örnek menüden açılmış menülerin görselleri menü sitesi bağlı olmadan tek tıkla düzelir. Örnek görsel olmayan yollar eskisi gibi bağlı menü sitesinden indirilir. Uyarı metni görsellerin nereden geldiğine göre değişir; "menü sitesi bilinmiyor" notu yalnız gerçekten menü sitesinden indirilecek görsel varsa çıkar.
- Yayındaki menü sayfası ve canlı önizleme göreli örnek görselleri (img/…) aktarılmadan önce de eklentideki dosyadan gösterir; düzenleyicideki küçük resimler de.

## 1.2.0
- **Canlı önizleme:** menü düzenleyicisinin solunda telefon çerçevesinde menünün kendisi her zaman görünür ve kaydetmeden önceki değişiklikleri de (yazmayı bıraktıktan ~0,5 sn sonra) gösterir; kaydırma konumu korunur. Geniş ekranda sabit sol sütun, dar ekranda ve telefonda **Önizleme** düğmesiyle tam ekran açılır. Önizlemede teşekkür sayfasına geçilmez, bağlantılar tıklanmaz; menü yayındaysa "Yeni sekmede aç".
- **Ürün görseli satırdan seçilir:** ürün satırındaki görsel kutusuna tıklayınca ortam kütüphanesi açılır (boşken kamera simgesi), görsel kutudaki × ile kaldırılır. Dosya yükleme yetkisi olmayan kullanıcıya görsel adresi sorulur.

## 1.1.0
- **Menüler artık bu sitede de yayınlanır.** Menünün yeni **Paylaş** sekmesinden bir adres (ör. `/menu/`) ve isteğe bağlı bir alt alan adı (ör. `menu1.menu.example.org`) verilir; menü `/menu/`, teşekkür sayfası `/menu/tesekkur/` adresinde açılır. Sayfa, menü sitesiyle aynı şablondan üretilir (`?menu` ile teşekkür sayfasını atlama dahil). WordPress'in kendi adresleri ve başka menünün adresi verilemez; aynı adreste bir sayfa varsa uyarır.
- Yayından kaldırılan menünün adresinde "Menü yayında değil" sayfası (404) açılır.
- **Canlı güncelleme:** açık menü sayfası, sekme görünürken 20 saniyede bir menünün sürümüne bakar (hata olursa bekleme uzar); menü kaydedilince kaydırma konumu korunarak kendini yeniler. Menü sayfaları önbelleğe alınmaz (LiteSpeed/CDN dahil).
- **QR kod:** Paylaş sekmesinde menü adresinin QR kodu, **SVG indir** ve **PNG indir**; adresler kopyala düğmeli.
- **Ana sayfa ayarı** (QR Menüler › Ayarlar): temanın ana sayfası, seçili menüye yönlendirme ya da seçili menüyü doğrudan ana sayfada gösterme.
- **Menü yöneticisi rolü:** /wp-admin'e giren menü yöneticisi yalnız kendi açtığı ya da kendisine atanan menüleri görür ve düzenler (diğerleri 403); panelde yalnız Başlangıç (pano), Ortam, QR Menüler ve Profil kalır, girişte doğrudan QR Menüler açılır, ortam kütüphanesinde yalnız kendi yüklediklerini görür. "Menü açabilir" ve "Adres/alt alan adı değiştirebilir" yetkileri kullanıcı başına Ayarlar › Yetkiler'den ya da profilden verilir; menüye atama Paylaş sekmesindeki **Düzenleyenler**'den. Anahtar, bağlı siteler ve kalıcı silme yalnız yöneticide.
- **Görselleri medya kütüphanesine aktar:** menü sitesinin dosyalarına (`img/…`) bağlı ürün görselleri tek tıkla bağlı menü sitesinden indirilip ortam kütüphanesine eklenir. Aktarılmamış göreli görseller bu sitede gösterilmez.
- Geçmişe adres, düzenleyen ve görsel aktarma kayıtları; Sistem Kontrolü'ne "Menü adresleri" satırı; MCP araçları menü yöneticisinin yalnız kendi menülerinde çalışır.
- Menü sitesi bağlantısı (anahtar, veri ucu, anlık bildirim) değişmedi.

## 1.0.0
- İlk sürüm. Ayrı alan adlarındaki QR menü sitelerinin içeriği bu siteden yönetilir: sol menüde **QR Menüler**.
- Birden çok menü: yeni menü (boş, örnek menü ya da JSON / eski `index.html` içe aktarma), kopyala, yayından kaldır / yayına al, çöp kutusu.
- Menü düzenleyici: kategoriler (başlık, not, ikon, alt çubuk sekmesi), ürünler ve ara başlıklar; sürükle-bırak ya da ↑↓ ile sıralama, kategoriye taşıma, ürün kopyalama. Her satırda fiyat ve telefonda tek dokunuşla **Tükendi** / **Gizli**. Tüm alanlar (açıklama, eski fiyat, birim, ortam kütüphanesinden görsel, rozet, bağlantı, büyük görsel, tükendi notu); bilinmeyen alanlar korunur.
- Ayarlar sekmesi (etkinlik başlığı, slogan, günler, site, kapanış, genel bilgi), ham JSON sekmesi (doğrula + uygula).
- Yapışkan kaydet çubuğu, Ctrl/Cmd+S, sayfadan çıkarken uyarı. Başka biri aynı anda kaydettiyse üzerine yazılmaz: değişiklikleri gösterir, "sunucudakini yükle" ya da bilerek "yine de kaydet".
- Tüm değişikliklerin geçmişi (menü başına son 300 kayıt): kim, ne zaman, okunur Türkçe özet ("Samsa: tükendi", "Karkade: fiyat 150 → 100", "Künefe eklendi"…), sürümü görüntüleme, **Bu sürüme dön**.
- Bağlantı: menü başına anahtar (yalnız bir kez gösterilir, sitede yalnız özeti saklanır), bağlı menü siteleri (son görülme, anlık bildirim), menü sitesinin veri ucu `/wp-json/flx-qr-menu/v1/feed` (ETag/304, önbelleksiz, hatalı anahtar denemesine sınır) ve her kayıtta imzalı anlık bildirim.
- Sistem Kontrolü satırları, Flux SEO & MCP araçları (`flxq_list_menus`, `flxq_get_menu`, `flxq_update_item`), İngilizce çeviri.
