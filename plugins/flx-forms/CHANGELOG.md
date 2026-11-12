# Değişiklikler

## 2.4.0
- Formlar tek yerde: Flux Block Editor'ın kendi "Form" ve "Form (gelişmiş)" blokları, Flux Forms kuruluyken ekleyiciden kalkar. Sayfalardaki mevcut formlar olduğu gibi çalışmaya devam eder.
- Eski form bloklarında "Flux Forms'a taşı" (araç çubuğu ve blok ayarları): alanlar Flux Forms bloklarına dönüşür (metin, e-posta, telefon, sayı, tarih, saat, uzun metin, açılır liste, çoklu seçim, onay kutuları, radyo, onay, dosya, gizli alan), düğme metni, başarı mesajı ve bildirim e-postası da taşınır. Sayfa kaydedilince form Flux Forms'ta oluşur. Eski gelişmiş form kaydı silinmez.
- İstenirse eski bloklar ekleyicide tutulabilir: `add_filter( 'flxf_hide_legacy_form_blocks', '__return_false' );`

## 2.3.0
- Flux Forms'taki tüm alan türleri artık blok: metin, e-posta, telefon, cep telefonu, uzun metin, sayı, parola, web adresi, tarih, kaydırıcı, renk, açılır liste, tekli/çoklu seçim, çoklu seçim listesi, görselli seçim, adetli seçimler, evet/hayır, anahtar, şart onayı, ülke, il, ilçe, dosya yükleme ve bırakma alanı, yıldız/1-5/NPS puanı, puanlama tablosu, imza, konum seçici, ses/video/ekran kaydı, ürün seçimleri, tutar, toplam, kartla ödeme (Stripe), başlık, bağlantı ve HTML. Her türün paneldeki ayarları (uzunluk sınırları, varsayılan değer, sütun, dosya türü ve boyutu, kayıt süresi, harita konumu, fiyatlar, görseller...) blok ayarlarında.
- Adım bloğu: formu adımlara böler; çok adımlı formlar artık blokta düzenlenir.
- Koşullu gösterim: her alanın ayarlarında "Yalnız şu durumda / Şu durumda gizle" ve bir ya da birkaç koşul (eşittir, içerir, büyüktür, boştur...). Panelde kurulan basit kurallar blokta görünür, karmaşık kurallar olduğu gibi korunur.
- Ödeme formları: ürün/fiyat alanları ve kartla ödeme bloğu; formda para birimi ve ödeme türü (tek seferlik ya da abonelik).
- Her alanda gizli alan, salt okunur, etiketi gizle, boş bırakılınca mesaj ve CSS sınıfı ayarları.
- Ülke, il ve ilçe listeleri Flux Forms'un kendi dosyalarından dolar.

## 2.2.0
- Formlar artık sayfada Gutenberg bloklarıyla kurulabilir: "Flux Form" bloğu ve metin, e-posta, telefon, uzun metin, sayı, tarih, açılır liste, tekli ve çoklu seçim alan blokları. Sayfa kaydedilince form Flux Forms'a yazılır; panelde aynı form görünür, yanıtlar, e-postalar ve otomasyonlar Flux Forms'ta çalışır.
- Panelde yapılmış bir form blokta seçilebilir. Blokta karşılığı olmayan alan türleri "Flux Forms alanı" olarak bozulmadan korunur, "Panelde düzenle" bağlantısıyla açılır.
- Form panelde değişmişse editör "Paneldeki sürümü yükle / Benimkini koru" uyarısı gösterir; sayfa kaydı panel değişikliğinin üstüne sessizce yazmaz.
- Panel form listesinde her forma "Blokla düzenle" düğmesi: formun bulunduğu sayfayı açar, yoksa formla yeni bir taslak sayfa oluşturur.
- Blok ayarları: düğme metni, teşekkür mesajı, onay kodu, bildirim e-postası, captcha, renkler, köşe yuvarlaklığı, alan boşluğu, form başlığını gösterme.
- Eski "Flux Forms" form seçici bloğu ekleyiciden kalktı; sayfalardaki eski bloklar çalışmaya devam eder ve "Flux Form" bloğuna dönüştürülebilir. Editörde aynı betiğin iki kez yüklenmesi düzeldi.

## 2.1.23
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 2.1.22
- Form listesi yanıtları ortak önbelleksiz başlıkları kullanır. CDN işareti durur.

## 2.1.21
- Ödeme deneme sınırı (10 dakikada 20 başlatma, 60 onay) ortak sayacı kullanır. Adres yalnız güvenilen vekilden okunur; istemci kendi kovasını seçemez.

## 2.1.20
- Yanıt CSV'sinde formül gibi açılacak hücreler tek tırnakla korunur. Sütunlar, ayırıcı ve satır sonları aynı kalır.

## 2.1.19
- İngilizce kaynak metinlerde 154 yer tutucu boşluğu geri geldi ("Version %s is active", "The latest %d payments are shown", "%s settings" …); toplu çeviri öncesindeki metinler esas alındı.
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 2.1.18
- Survey charts read submissions 500 at a time and keep the finished result for 120 seconds, one cache entry per form. Storing a new submission drops that form's cache. A second request does not recompute while the first still holds the 60 second lock; it reuses the cached result when there is one.
- New admin e-mail keys are 128 bits from a CSPRNG. Keys already stored, and the links sent with them, keep working.

## 2.1.17
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Preserve singular/plural message boundaries and printf arguments in English source IDs and all native language packs; prevent count labels from breaking populated screens.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 2.1.16
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 2.1.15
- Güvenlik (CodeQL): modal başlığı düz metin; uyarı, e-posta önizlemesi ve HTML blok içeriği izin listeli temizleyiciden (`efb_safe_html_efb`, script/on* yok) geçer. Seçenek değerleri, ayarlar ve dosya adları HTML'e kaçışlanır; resim önizlemesi yalnız `data:image`, font bağlantısı yalnız https. Kısa kod etiketleri `switch` ile; e-posta şablonunda şifre etiketi anahtarla okunur.

## 2.1.14
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 2.1.13
- Uygulama manifesti: form listesi (`efb/v1/forms`) ve yanıt listesi. Form oluşturucu web görünümünde açılır.
- Yeni uçlar `flx-forms/v1/responses`, `responses/{id}`, `responses/{id}/read`. Yetki `Emsfb` ya da yönetici.

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

