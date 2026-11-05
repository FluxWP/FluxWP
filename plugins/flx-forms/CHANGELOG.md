# Değişiklikler

## 2.1.12
- Güvenlik (CodeQL): form oluşturucu önizlemesinde etiket, açıklama, seçenek ve dosya adları `textContent` ile yazılır; bağlantı biçimi (`[metin](adres)`) her şeyi kaçışlar, yalnız http(s)/göreli adresi bağlantı yapar.
- E-posta şablonunda adres denetimi izin listesiyle (http, https, mailto, tel, data:image); genel ayarlar ve blok etiketleri kaçışlanır. Yanıt görüntüleyici metni DOMParser ile düz metne çevirir (çift kaçış yok).
- Regex ile etiket silme yerine döngüyle düz metin; CSS seçici kaçışına ters eğik çizgi eklendi; kullanılmayan `santize_string_efb` ve `saveLocalStorage_emsFormBuilder_view` kaldırıldı.
- Eklenti betikleri dosya değişim zamanıyla sürümlenir (sabit 4.2.0 yerine); güncellemeden sonra tarayıcı/LiteSpeed eski JS göstermez.

## 2.1.11
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 2.1.10
- Eski YT Form (veya Easy Form Builder) etkinken Flux Forms'u etkinleştirmek HTTP 500 veriyordu: iki eklenti aynı sınıf ve fonksiyon adlarını tanımladığı için "Cannot redeclare" fatal. Artık Flux Forms başka bir kopyayı görünce o istekte yüklenmez, eski eklentiyi istek sonunda kapatır ve bir bilgi notu gösterir. Tablolar ve ayarlar ortak olduğu için formlar, yanıtlar ve ayarlar yerinde kalır.
- Eklenti gövdesi `flx-forms-load.php` dosyasına taşındı; ana dosyada yalnız başlık ve bu kontrol var (PHP üst düzey fonksiyonları dosya derlenirken tanımladığı için kontrol aynı dosyada olamaz).

## 2.1.9
- Pano widget'ı koyu temada okunur: kart yazıları, sekmeler, kartlar ve hata paneli Flux Admin renklerini izliyor (açık temada görünüm aynı). Grafikte ızgara çizgileri, eksen ve gösterge yazıları temadan alınır; açık/koyu değişince grafik yeniden çizilir.
- Grafik göstergesinde "Visit" yerine çevrilmiş "Sayfa görüntüleme" yazıyor (kartla aynı metin).
- Widget CSS/JS dosyalarına fork sürümü eklendi; güncellemeden sonra tarayıcı eski dosyayı tutmaz.

## 2.1.8
- Eksik tablolar kendiliğinden oluşturuluyor. Flux Setup sihirbazı gibi sessiz etkinleştirmede activation hook çalışmadığı için `wp_emsfb_*` tabloları hiç oluşmuyor, her istekte "Table ... doesn't exist" hatası veriliyordu. Ayar tablosu yoksa ilk istekte kurulum tam olarak çalışır (varsayılan ayarlar dahil); DB sürümü 1.3 ile mevcut sitelerde eksik tablo kontrolü bir kez yapılır.

## 2.1.7
- Pano ve otomasyonlarda koyu modda açık nane rozet/düğmeler düzeltildi; yeşilimsi sabit çizgiler tema tonunda.

## 2.1.6
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

