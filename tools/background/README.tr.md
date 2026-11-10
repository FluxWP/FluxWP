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

## Doğrulama

`tests/run.py`, **yalnız bize ait, atılabilir localhost WordPress ortamında** yayımlanmış worker imajıyla çalışır. E-posta ve dış istekler durdurulup test içinde karşılanır. Test geçici olarak etkin eklentileri, cron ve örnek kuyruk kayıtlarını değiştirir; gerçek siteye yönlendirilmemelidir. `tools/readme-media` ortamındaki depo bağlantıları ve `WPENV` dizini beklenir; önce veritabanı yedeği alınmalıdır. Kendi test kayıtları `finally` içinde temizlenir.

```sh
python3 tools/background/tests/run.py --container fluxwp-media-20261109-wordpress-1
```

Testler gerçek PDF/ZIP üretimini, Action Scheduler olan/olmayan normal WordPress’i, worker durmasını, kota, yeniden deneme ve eşzamanlı kilitleri kapsar. Harici SMTP teslimatını test etmez. Başka test konteyneri veya worker imajı için `--help` kullanılabilir.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
