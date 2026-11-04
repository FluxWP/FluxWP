# Changelog

## 1.8.5
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.8.4
- Koyu modda açık zeminli seçimler (WhatsApp/e-posta kutuları) ve sönük ara bilgiler düzeltildi; seçili sekme vurgu renginde.

## 1.8.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.8.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 1.8.1
- Sabit yeşiller kaldırıldı: arayüz tema/Flux Admin rengini, e-posta şablonu tema paletini kullanıyor. Hazır şablonların varsayılan rengi FluxWP indigo.

## 1.8.0
- Sertifikalar, Kişiler, Gönderim kuyruğu ve Kayıtlar listeleri Flux Admin 1.2.0+ ortak liste lib'ini kullanır: tek satır arama + Filtreler düğmesi, 2 temel filtre (Sertifikalar'da durum + etkinlik), diğerleri açılır panelde, otomatik filtreleme, sayfa başı adet (10/20/50/100) ve "1–20 / 160" bilgisi. Flux Admin yoksa eski görünüm.
- Sertifikalar mobilde kompakt kart: etiket sütunu yok, boş satırlar gizli, durum ve İşlemler yan yana; toplu işlem çubuğu yalnız seçim varken.
- Sertifikalar ekranının metinleri çevrilebilir (EN).

## 1.7.0
- Flux Roles uyumu: `flxs_own_ref` yetkisi olan (yönetici olmayan) kullanıcı sertifika listesi, sayımlar, Excel, Kişiler ve Gönderim kuyruğunda yalnız kendi oluşturduğu ya da referansı olduğu kayıtları görür (staff_own_only ayarından bağımsız).
- Dashboard ve /stats artık `flxs_manage` ya da `flxs_dashboard` ister; mevcut Sertifika Personeli rolüne `flxs_dashboard` bir kez eklenir (davranış aynı kalır).
- Referans seçilebilen kişiler `flx_referans` yetkisi olanları da kapsar.
- `flx_role_caps_supported` filtresine `flxs_own_ref`, `flxs_dashboard` bildirildi.

## 1.6.5
- Etkinlik görseli önizlemesinde güvenli alan: kesikli çerçeve (soldan/sağdan %12,5, üstten/alttan %12), 2:1 sayfada kesilen gölgeli şeritler ve tarih rozeti alanı; önerilen ölçü 1600 × 1000 px.
- Sitede etkinlik kartındaki tarih rozeti sağ üste alındı (görselin sol üstündeki logoyu kapatmasın).

## 1.6.4
- Mesaj imzaları, gönderen adı ve paylaşım açıklaması yeniden kurum adıyla ("Örnek Vakfı"); 1.6.3 ile değişen kayıtlı metinler geri alınır. Katılım belgesinde "doğanın yeşil geleceğine" kalır (migration 6).

## 1.6.3
- Hazır metinlerde İstanbul vurgusu kalktı: katılım belgesi "doğanın yeşil geleceğine", mesaj imzaları ve paylaşım açıklaması "Örnek Vakfı". Kullanıcının değiştirmediği kayıtlı metinler otomatik güncellenir (migration 5).

## 1.6.2
- Sistem kontrolü: Flux Mail'de hiç kurulmamış WhatsApp/SMS kanalı artık uyarı değil bilgi ("SMS bağlantısı kurulmamış; SMS ile sertifika gönderilmez."). Uyarı yalnız bağlantı var ama hiçbiri etkin değilse.

## 1.6.1
- 16 Örnek Vakfı e-kartı kurulumda ve güncellemede Şablonlar listesine kendiliğinden pasif olarak eklenir (migration 4); aktif yapılan tasarım bağış sayfasında seçilebilir. "İçe aktar" yalnız silinenleri yeniden (pasif) ekler; eski şablonları düzeltirken durumlarına dokunmaz.

## 1.6.0
- PDF, sertifika görseli ve 1200x630 paylaşım görseli artık sunucuda da üretilir (TCPDF 6.11 + FPDI 2.6 eklentiye gömülü, `includes/vendor/`; GD/FreeType yeterli). Yerleşim tarayıcıdaki çiziciyle aynı kurallarla hesaplanır, çıktı birebir aynı.
- Flux Forms / Flux Donate gibi arka planda oluşan sertifikaların dosyaları bir dakika içinde arka planda (Action Scheduler ya da WP-Cron) hazırlanır; sertifika sayfası ya da PDF linki ondan önce açılırsa o anda üretilir (WhatsApp önizlemesi ilk açılışta görseli bulur). Tarayıcıdaki çizici canlı önizleme ve yedek olarak kalır.
- Sistem kontrolü: "Sunucuda PDF üretimi" satırı, eksik dosyalar için "Eksikleri oluştur" ve `flxs.render_missing` düzeltmesi. Loglar: "Dosyalar sunucuda oluşturuldu" / "Sunucuda dosya oluşturulamadı".
- Hazır PDF arka planlarının sunucu için görüntüleri `assets/backgrounds/raster/`; Fidan Bağış PDF'i FPDI'ın okuyabileceği biçime çevrildi (görünüm aynı). Panelden yüklenen PDF arka planlarda görüntü için Imagick gerekir, yoksa görselleri tarayıcı tamamlar.

## 1.5.0
- E-kartlar artık eklentiyle geliyor: 16 tasarımın "ÖRNEK" filigranı ve örnek isimleri (Alıcı Adı, Adınız Soyadınız…) temizlendi, görseller `assets/backgrounds/e-kart-{cid}.jpg`. Fonzip'ten indirme kalktı.
- Her tasarımda isim alanları örnek isimlerin yerinde, aynı boyut ve renkte: `ad_soyad` (alıcı), `gonderen` (bağışlayan), taziyede `anilan`, rozetli iki tasarımda `fidan_sayisi` ("Özel Mesajınız" rozeti temizlendi, sayı rozetin ortasına yazılır).
- "İçe aktar" eksik tasarımları aktif ve bağış sayfasında seçilebilir ekler. 1.4.1 ile içe aktarılmış filigranlı şablonlar güncellemede kendiliğinden yeni görsel ve alanlarla değişir (grup/sıra korunur), eski ortam dosyaları silinir.
- Mobil: Yeni Sertifika ekranında canlı önizleme artık formun üstünde hep görünür (alttan açılan panel ve "Önizleme" butonu kaldırıldı; Flux Admin alt çubuğunun arkasında kalıyordu).

## 1.4.2
- Düzeltme: sertifika oluştururken "Cannot read properties of undefined (reading 'PDFDocument')" hatası. PDF kütüphanesi (pdf-lib) sayfa açılışında yüklenmemişse (mobil ağ kesintisi, önbellek/optimizasyon eklentisi, sayfadaki AMD yükleyici) çizici artık eksik kütüphaneyi kendisi yeniden yükler; yine yüklenemezse "PDF kütüphanesi yüklenemedi, sayfayı yenileyin" der. Kütüphaneler yüklenirken define gizlenir.

## 1.4.1
- Şablonlar sayfasına "Hazır e-kartlar" kartı: "İçe aktar" example.org/e-kartlar'daki 16 tasarımı sitenin sunucusundan indirip ortam kütüphanesine ekler ve her birini pasif, "bağışçı seçebilir" işaretli şablon yapar (alıcı adı alanı ortada). Tekrar basınca yalnız eksikler eklenir. "Görselleri zip indir" içe aktarılan görselleri tek zip verir.
- `flxs_donor_templates()`: önizleme yoksa görsel arka plan thumb olarak döner.

## 1.4.0
- Şablon editörüne "Bağış sayfası" kartı: "Bağışçı bu tasarımı bağış sayfasında seçebilir", başlık (grup, boşsa "Sertifikalar") ve sıra. Şablonlar listesinde "Bağış sayfasında" etiketi.
- Yeni API `flxs_donor_templates()`: seçilebilir aktif şablonlar (id, name, group, sort, thumb, bağışçıdan istenecek alanlar). `flxs_donor_templates` filtresiyle değiştirilebilir. Flux Donate'ın tasarım seçimi bunu kullanır.

## 1.3.1
- Sertifikalar listesine "Excel indir" butonu: seçili filtrelere uyan tüm sertifikalar xlsx olarak iner (no, tarih, ad, telefon, e-posta, etkinlik, şablon, referans, oluşturan, durum, görüntülenme, gönderilen mesaj, link). Personel yalnız kendi oluşturduklarını alır. Başlıklar site diline göre (TR/EN). İndirme işlem kaydına yazılır.

## 1.3.0

- Sağlayıcılar ekranı kaldırıldı: WhatsApp, SMS ve e-posta yalnızca Flux Mail üzerinden gider. Eski adres İletişim › Bağlantılar'a yönlenir.
- Flux Mail'deki her etkin WhatsApp/SMS bağlantısı sertifika gönderiminde kendiliğinden seçilebilir (varsayılan = İletişim'deki varsayılan bağlantı).
- Evolution/Netgsm gönderim kodu bu eklentiden çıkarıldı; eski satırlar kapalı kalır, silinmez.
- Flux Mail etkin değilse Sistem kontrolünde hata; WhatsApp/SMS gönderilemez, e-posta wp_mail ile gider.
- Sistem kontrolündeki "Bağlantıları test et" kaldırıldı (test İletişim › Bağlantılar'da).

## 1.2.7

- Yenilenen Flux Certificates logosu (menü ikonu).

## 1.2.6

- Admin menüsünde renkli Flux Certificates logosu (assets/icon.svg, ortak FluxWP logo seti).

## 1.2.5

- Flux Mail etkinse Sağlayıcılar ekranında "ayarlar Flux Mail'e taşındı" notu ve İletişim › Bağlantılar / E-postalar bağlantıları.
- Sistem kontrolü Flux Mail'i tanır: WhatsApp/SMS satırları İletişim bağlantılarına bakar ("Flux Mail üzerinden gönderiliyor"), e-posta satırı kurumsal şablonu gösterir; yanlış "aktif sağlayıcı yok" uyarısı düzeldi.
- Gönderim tarafı değişmedi (köprü flxs_provider_types filtresiyle Flux Mail'de).

## 1.2.4

- Sertifikalar › Sistem kontrolü ve ortak `flx_system_checks` kaydı; "Bağlantıları test et" butonu.

## 1.2.3

- Sertifika sayfası hızlandı: PDF çizici kütüphaneleri yalnızca gerektiğinde yüklenir, eksik dosyalar arka planda tek tek yüklenir.
