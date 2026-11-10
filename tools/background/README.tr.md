# Taşınabilir arka plan işleri

[English](README.md)

Flux Mail, Flux Certificates ve Flux Backup bu küçük ortak dosyayı kendi paketlerinde taşır. Ek eklenti, Redis veya kuyruk sunucusu gerekmez. İşler mevcut veritabanı kuyruklarında ve WordPress cron olaylarında kalır.

Mevcut FluxWP Docker worker, web konteyneriyle aynı dosyaları ve veritabanını kullanır; WP-CLI içinde `WP_CRON_MODE=worker` ile çalışır. Üç eklentiden biri yüklüyse worker çalıştıkça durumunu yeniler. Worker sağlıklıyken kampanyalar, sertifika gönderimleri, sunucuda üretilen PDF/görseller ve asenkron yedekler ona bırakılır. Tekil e-posta API’leri, SMTP testleri, tarayıcıdaki sertifika önizlemeleri, geri yükleme ve güncelleme öncesi senkron yedeklerin akışı korunur.

Worker olmayan WordPress kurulumları mevcut şekilde çalışır. Sertifikalarda hazır Action Scheduler varsa o, yoksa WP-Cron kullanılır. Varsayılan 60 saniyelik aralıkta worker durduktan beş dakika sonra normal işleyicilerin çalışmasına yeniden izin verilir. Bu dosya **`DISABLE_WP_CRON` ayarını değiştirmez**: cron kapalıysa harici zamanlayıcı veya mevcut istek/loopback tetiklemesi gerekir. Duran konteyneri yeniden başlatmaz.

## Geliştirme

`runner.php` dosyasını düzenledikten sonra depo kökünde:

```sh
python3 tools/background/sync.py
python3 tools/background/sync.py --check
```

Üç paketteki kopyalar aynı kalmalıdır. Fonksiyonlar koşullu tanımlandığı için eklentiler birlikte veya bağımsız çalışır. [API ve zamanlama sözleşmesi](../../docs/background-workers/API.md).

Mail ve sertifika kuyrukları ortak `flx_background_run()` çalıştırıcısını kullanır: web isteğini sağlıklı workera bırakır, işe özel kilidi alır, kilit doluysa yeniden zamanlar ve callback hata verse de kilidi bırakır. Eklenti yalnız kendi işini, sınırlı parti bütçesini ve yeniden deneme kuralını sağlar. Yedeklemede geri yüklemenin farklı kayıt düzeni nedeniyle mevcut dosya kilidi korunur; worker seçimi yine bu kütüphanedendir.

Yeni bir eklentiyi bağlamak için mevcut paket üzerinde `python3 tools/background/sync.py --add plugins/flx-example` çalıştırıp paketin `includes/flx-background.php` dosyasını yükleyin. Paket listesi sayesinde eksik ortak dosya GitHub kontrolünde yakalanır. API belgesinde tam hook örneği vardır. Ayrı eklenti etkinleştirmek gerekmez. Ortak API geriye uyumlu kalmalıdır; yeni yardımcılar kendi fonksiyon kontrolünü taşır, böylece önce eski paket yüklense de yeni API gizlenmez. GitHub kontrolü dosya eşitliğini, PHP sözdizimini, bağımsız çalışmayı ve yüklenme sırasını doğrular.

## Doğrulama

`tests/run.py`, **yalnız bize ait, atılabilir localhost WordPress ortamında** yayımlanmış worker imajıyla çalışır. E-posta ve dış istekler durdurulup test içinde karşılanır. Test geçici olarak etkin eklentileri, cron ve örnek kuyruk kayıtlarını değiştirir; gerçek siteye yönlendirilmemelidir. `tools/readme-media` ortamındaki depo bağlantıları ve `WPENV` dizini beklenir; önce veritabanı yedeği alınmalıdır. Kendi test kayıtları `finally` içinde temizlenir.

```sh
python3 tools/background/tests/run.py --container fluxwp-media-20261109-wordpress-1
```

Testler gerçek PDF/ZIP üretimini, Action Scheduler olan/olmayan normal WordPress’i, worker durmasını, kota, yeniden deneme ve eşzamanlı kilitleri kapsar. Harici SMTP teslimatını test etmez. Başka test konteyneri veya worker imajı için `--help` kullanılabilir.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)

## Paralel işler

Mevcut Docker cron worker bağımsız işleri sınırlı bir havuzda çalıştırır: varsayılan eklenti başına aynı anda 3 parti, değiştirilebilir site toplamı 9. Mail, sertifika PDF ve yedek işleri birbirini beklemeden ilerleyebilir; aynı kaynağa ait kilitler korunur. Ortak zamanlayıcı, süreçlerin sonraki işleri birbirinin üzerine yazmasını önler. Özel iş kayıtları ve cron watchdog’u başarısız işi korur; alt süreçler 120 saniyeyle ve sınırlı denemeyle çalışır. Docker Compose değişikliği gerekmez. Normal WordPress mevcut cron/istek akışını korur. Sayı ve süre filtreleri ile dış gönderim garantisinin sınırları API belgesindedir.

Dağıtım ayarları: `WP_BACKGROUND_WORKERS_PER_PLUGIN=3`, `WP_BACKGROUND_MAX_PROCESSES=9`. Güncel Docker worker hafif uyandırma kontrolü için `WP_BACKGROUND_POLL_INTERVAL=2`, yedek tetikleme için `WP_CRON_INTERVAL=60` kullanır. Hızlı uyandırma için worker imajını güncelleyip yeniden oluşturun; eski imajda normal cron devam eder. Bildirimlerde iş içeriği taşınmaz. Entegrasyon testi için kardeş docker-wordpress deposu veya `--worker-script` gerekir; atılabilir test ortamına aynı anda iki test giremez.

Entegrasyon testi ayrıca 1.000 yapay SMS alıcısını Netgsm REST v2 yanıtı test içinde karşılanarak, kalıcı/geçici sağlayıcı hatalarını ve WhatsApp aralık/limit davranışını doğrular. Gerçek SMS, WhatsApp veya e-posta gönderilmez.
