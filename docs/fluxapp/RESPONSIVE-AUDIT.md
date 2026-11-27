# Eklenti yönetim ekranları responsive incelemesi

Tarih: 27 Kasım 2026. Kaynak: FluxWP `3f8390b9` ve wp-extras `49007a44`; aşağıdaki düzeltmeler uygulanarak tekrar kontrol edildi.

## Kapsam ve yöntem

Gerçek WordPress 7.1.4, PHP 8.3, MariaDB 11.8, WooCommerce 11.3.1 ve FluxWP teması üzerinde 39 Flux eklentisi birlikte etkinleştirildi. Örnek içerik, bağışlar, sertifikalar, kullanıcılar ve diğer modül kayıtları eklendi. Envanterde 43 React modülü, 112 kayıtlı ekran ve **149 erişilebilir yönetim menüsü adresi** bulunuyor. Menüler gerçek `admin.php` / `themes.php` adresleriyle açıldı.

Her adres aynı sayfada 320 → 390 → 768 → 1024 → 1440 CSS piksel genişlikte ölçüldü: **745 temel görünüm**. Belge genişliği, ekran dışına çıkan kontroller, seçili sekmenin görünürlüğü, HTTP yanıtları ve yakalanmamış JavaScript hataları kontrol edildi. Başlangıç ölçümleriyle karşılaştırıldı; özel tam ekran sayfalar ve kurulum sihirbazı ayrıca incelendi. Ana modüllerde 390 ve 1440 piksel karanlık tema örneklemesi ve seçili ekranlarda sekme geçişleri de yapıldı.

Mobil ekran görüntüleri App ayarları, AI becerileri, Sertifikalar, Bağışlar, POS, Redis, Studio, Görevler ve kurulum akışında gözle incelendi. Tablo taşması sayfa taşmasından ayrıldı: geniş tablolar kendi alanında kayabilir; tüm belgenin yatay büyümesi hata sayılır. Kapalı yan paneller ve gizli WordPress kökleri ölçüme dahil edilmedi.

## Bulgular ve düzeltmeler

| Alan | Bulgu | Sonuç |
| --- | --- | --- |
| Ortak ekran katmanı | Bazı form alanları sabit genişlikleri nedeniyle dar ekran dışına taşıyordu. | Alanlar ve başlık eylemleri kendi kapsayıcılarına sığar; aksiyonlar gerektiğinde satıra geçer. |
| Bağış listeleri | Geniş tablodaki gizli ekran okuyucu etiketinin statik konumu 1024 piksel pencereyi 1445 piksele büyütüyordu. | Gizli etiketin başlangıç konumu sabitlendi; yerel tablo kaydırması ve erişilebilir metin korunur. |
| Ortak sekmeler | Daraltılan pencerede seçili sekme kaydırılabilir şeridin dışında kalıyordu. | İlk yükleme, seçim, boyut değişimi ve sayfa içi kök değişiminde seçili sekme görünür alana alınır. Dikey kaydırma ve klavye odağı korunur. |
| App ayarları | Sonradan gelen 200 piksel etiket sütunu mobil tek sütun kuralını eziyordu. | İki sütun kuralı yalnız masaüstünde uygulanır. |
| AI becerileri | 260/300 piksel asgari kart genişliği 320 piksel telefonda kartları kesiyordu. | Kartın asgari genişliği kullanılabilir alandan büyük olamaz. |
| Sertifikalar | Tarih aralığı ve şablon alanları dar ekrana sığmıyordu. | Tarihler üç sütunlu esnek satırda kalır; uzun tarih metni yılı gizlemeden satır kırar. |
| Redis + reCAPTCHA | Ortak PHP başlangıç sabitleri ve URL fonksiyonu Redis'i sessizce devre dışı bırakabiliyor veya yüklemeyi bozabiliyordu. | Redis kendi ad alanını kullanır. Her iki yükleme sırası ve üç Redis sayfası birlikte doğrulandı. |

Ortak değişiklikler Flux GraphQL ekran sunucusunda uygulanır; tüm eklentilerin paketlerini yeniden derlemek gerekmez. App ve AI kaynak CSS değişiklikleri kendi gömülü React paketlerine de işlendi. Tema renkleri, kartlar ve başlık düzeni incelenen örneklerde tutarlıydı; bu çalışma için ek bir görsel sistem gerekmedi.

## Doğrulama

- Son ölçümlerde 149 menü adresinin beş temel genişliğinde belge/kontrol taşması, görünmeyen seçili sekme veya yakalanmamış JavaScript hatası kalmadı.
- Ortak sekme davranışı için gerçek Chromium regresyon testi: daraltma, seçim değişikliği, kök değiştirme, odak ve dikey kaydırmanın korunması.
- Modül TypeScript kontrolü, panel derlemesi ve App/AI paket bağlantı doğrulaması geçti.
- Flux GraphQL 0.8.5, App 0.10.5, AI 0.37.1 ve Sertifikalar 2.4.1 paketleri: WordPress 7.1.4 ve ortak bağımlılıkların desteklediği WordPress 6.5 üzerinde temiz kurulum ve önceki sürümden güncelleme.
- Redis 0.1.2: temiz kurulum, 0.1.1'den güncelleme, Redis 8.8 üzerinde beş mevcut test dosyası ve reCAPTCHA ile her iki yükleme sırası.
- Paket testlerinde dış HTTP kapalı olduğundan WordPress'in güncelleme sunucusuna ulaşamama uyarıları oluştu. Fatal veya HTTP 500 kalmadı. Ayrı CLI güncelleyici / PHP geliştirme sunucusu testinde OPcache kapatıldı; iki ayrı süreç aksi halde eski PHP dosyalarını önbellekte tutabiliyor.

## Tekrar çalıştırma ve sınırlar

Komutlar ve ölçüm kuralları: [`tools/tests/responsive/README.md`](../../tools/tests/responsive/README.md). JSON sonuçları, ekran görüntüleri ve ZIP'ler teslim klasöründedir; kimlik doğrulama dosyaları teslim edilmez.

Bu inceleme etkin eklentilerin erişilebilir yönetim menülerini kapsar. Gizli/özellik koşullu rotalar, tüm açılır pencereler, her editör durumu, birbirini dışlayan ön yüz preset'leri ve gerçek telefon donanımı ayrı kapsamdır. Dış servisler ve e-posta kapatıldı; ödeme, mesaj gönderimi veya gerçek sağlayıcı işlevi bu taramada doğrulanmadı. Karanlık tema kontrolü tüm menülerin tüm durumları yerine 48 sayfada 96 görünümden oluşan ana modül örneklemesidir.

Yayın paketleri: `flx-graphql` 0.8.5, `flx-app` 0.10.5, `flx-ai` 0.37.1, `flx-certificates` 2.4.1; wp-extras tarafında `flx-redis` 0.1.2.
