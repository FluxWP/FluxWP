# Flux Backup: kurulum

1. **Eklentiler › Yeni ekle › Eklenti yükle** ile `flx-backup.zip`'i yükleyip etkinleştirin. (WordPress 6.4+, PHP 7.4+, PHP `zip` uzantısı.)
2. Sol menüde **Yedekler** çıkar. **Ayarlar**'da kotayı (varsayılan 10 GB), güncelleme öncesi yedek kapsamını ve isterseniz zamanlanmış tam yedeği (günlük/haftalık, gece saati) ayarlayın.
3. **Panel › Şimdi yedek al** ile ilk tam yedeği alın. Bundan sonra her eklenti/tema güncellemesinden (elle, toplu, otomatik) önce veritabanı + güncellenen paketin klasörü kendiliğinden yedeklenir ve 7 gün saklanır.

## Günlük kullanım

- **Panel**: kota çubuğu, depolama geçmişi grafiği, türlere göre dağılım, son yedekler ve olaylar.
- **Yedekler**: indir, geri yükle (veritabanı ve/veya tek tek eklenti/tema klasörleri), sil, toplu sil. Kilit simgesi yedeği kota ve saklama süresinden korur.
- Kota dolarsa yeni yedekten önce en eski **kilitsiz** yedek silinir. Kilitli yedekler yüzünden yer açılamazsa yedek alınmaz (güncelleme yine de yapılır, olaylarda hata görünür).

## Hostinger notları

- Yedekler `wp-content/wp-snapshots/flx-backup/` klasöründedir. Hostinger `wp-snapshots` klasörünü **kendi yedeklerine almaz** (yedeğin yedeği şişmesin diye). Bu yüzden:
  - Önemli yedekleri (ör. büyük bir değişiklikten önceki tam yedek) **İndir** ile bilgisayarınıza alın.
  - Hostinger'ın kendi günlük/haftalık yedeklerini kapatmayın; Flux Backup onların yerine değil, yanında çalışır.
  - Hosting hesabı silinir veya disk arızalanırsa bu klasördeki yedekler de gider.
- Klasör `.htaccess` ile web'e kapalıdır (LiteSpeed uyar); dosya adlarında rastgele ek vardır. İndirme yalnız yöneticiye açıktır.
- `exec`/`mysqldump` kullanılmaz; yedek ~20 saniyelik adımlarla alınır, sayfayı kapatsanız da WP-Cron ile sürer. Sitede `DISABLE_WP_CRON` varsa hPanel › Gelişmiş › Cron Jobs'ta `wp-cron.php`'yi 5–15 dakikada bir çağırın.
- Disk kotanızı düşünün: Flux Backup kotası hosting diskinizin yarısını geçmesin (ör. 50 GB planda 10–20 GB).

## Kaldırma

Eklentiyi silmek ayarları siler; zip dosyaları **kalır**. Dosyalar da silinsin istiyorsanız önce Ayarlar › "Yedek dosyalarını da sil"i işaretleyin.
