# Flux Certificates eklentisi: kurulum notu

Sürüm 1.8.0. Temadan bağımsız, ayrı bir WordPress eklentisi. Sertifika sayfası temanın header ve footer'ını kullanır.

## Kurulum
1. **Eklentiler › Yeni ekle › Eklenti yükle** ile `flx-certificates.zip` yüklenir ve etkinleştirilir.
2. **Ayarlar › Kalıcı bağlantılar** sayfası bir kez kaydedilir. `/sertifika/{kod}` adresleri bundan sonra çalışır.
3. Menüde **Sertifikalar** bölümü açılır. Üç hazır şablon gelir; ilki örnek PDF'ten yapılan **Fidan Bağış Sertifikası**.

## Personel
- **Kullanıcılar › Yeni ekle** ekranında rol olarak **Sertifika Personeli** seçilir.
- Personel giriş yapınca doğrudan **Yeni sertifika** ekranına gelir. Ayarları, şablonları ve logları göremez.
- **Ayarlar › Personel yetkileri** kısmında iki seçenek var:
  - Personel toplu gönderim yapabilsin
  - Personel yalnızca kendi oluşturduklarını görsün
- **Flux Roles (v1.7.0):** Personel ve İdareci rolleri Kullanıcılar › Roller ve erişim'den yönetilir. `flxs_own_ref` yetkisi olan kullanıcı yalnız kendi oluşturduğu ya da referansı olduğu sertifikaları, bunların kişilerini ve mesajlarını görür (yukarıdaki ayardan bağımsız). Dashboard `flxs_dashboard` yetkisi ister; İdareci genel istatistiği görür. Sertifika Personeli rolüne bu yetki güncellemede bir kez eklenir. `flx_referans` yetkisi olan herkes referans olarak seçilebilir.

## Sertifika oluşturma (liste bazlı)
1. Şablon seçilir.
2. Kişiler satır satır eklenir ya da **Listeden yapıştır** ile toplu girilir. Biçim: `Ad Soyad; telefon; e-posta; fidan sayısı`.
3. Ortak alanlar (ör. fidan sayısı) bir kez yazılır, satırda farklı değer girilirse o geçerli olur.
   - Birden fazla kişi varsa sayı alanının altında **Kişilere dağıt** kutusu çıkar. İşaretlenince toplam kişilere bölünür: 3 fidan / 3 kişi → 1, 1, 1; 3 fidan / 2 kişi → 2, 1. Satıra elle yazılan sayı toplamdan düşülür, kalan diğerlerine bölünür. Birine 0 düşerse uyarı verir ve oluşturmaz.
   - Telefon ve e-posta yazmayı bırakınca altında doğru olup olmadığı yazar (✓ +90 535 011 37 64). Numara kayıtlı bir kişiye aitse adı görünür, dokununca o kişi seçilir.
4. Sağda canlı PDF önizlemesi görünür, ‹ › ile kişiler arasında gezilir. Mobilde **Önizleme** butonu kullanılır.
5. **Oluştur**'a basılınca her kişiye ayrı sertifika, link ve PDF oluşur.
6. Sonuç ekranında şunlar var:
   - Linkleri kopyala
   - Kişiye WhatsApp ile kendi telefonundan gönder
   - **Tek PDF indir** (hepsi tek dosyada)

Kişiler telefon numarasına, telefon yoksa e-postaya göre tanınır; aynı kişi tekrar kaydedilmez. Telefon ve e-posta zorunlu değil.

Telefon her biçimde yazılabilir, veritabanına tek biçimde (`905350113764`) kaydedilir: `+90 5350113764`, `05350113764`, `535 011 37 64`, `0090 535…`, `+90 0535…`, `(0535) 011-37-64`. Eksik ya da hatalı numara kaydedilmez.

**Başka birine gönder:** Sonuç ekranında ya da Tüm Sertifikalar › İşlemler menüsünde. "Kime?" alanına numara ya da e-posta yazılır; başına ad yazılabilir (`Hasan 0532…`), virgülle birden fazla alıcı girilebilir. Rehberdeki isimlere dokununca eklenir. Telefonlar WhatsApp ya da SMS ile, e-postalar e-postayla gider. Enter gönderir. Yeni numaralar rehbere kaydedilir.

**Referans personel:** Oluşturma ekranında listenin kimin adına/aracılığıyla geldiği seçilir (varsayılan: giriş yapan kişi, son seçim hatırlanır). Tüm Sertifikalar'da referansa göre filtre (sayılarıyla), "Referans / Oluşturan" sütunu , eski sertifikalar için toplu "Referans ata…" ve tek sertifika için İşlemler › "Referansı güncelle…" var.

### Kuyruk sunucuyu bekletmez (v1.2.4)
Mesajlar arası bekleme süresi olan sağlayıcıda (ör. WhatsApp) kuyruk, sıradaki mesajın saatini artık yalnızca arka plan cron'unda bekler (en fazla 25 sn). Panelden/tarayıcıdan tetiklenen işlemler beklemez, hemen döner; tarayıcı birkaç saniyede bir yeniden tetikler. Önceden her istek 15-45 sn bir PHP işçisini tutuyordu; paylaşımlı hostingde işçiler dolunca site ve wp-admin "loading"de kalabiliyordu.

### Sağlayıcılar kaldırıldı (v1.3.0)

Sertifikalar › Sağlayıcılar ekranı kaldırıldı; eski adres İletişim › Bağlantılar'a yönlenir. Ayrıntı: "Gönderim kanalları" bölümü. Eski Evolution/Netgsm kayıtları veritabanında kapalı durur (kullanılmaz, silinmez).

### Sistem kontrolü (v1.2.4)

Sertifikalar › Sistem kontrolü: kurulumun yerli yerinde olup olmadığını tek ekranda gösterir (✓ tamam, ! uyarı, ✕ hata, i bilgi; sorunlu satırda düzeltme sayfasına buton).

- Kurulum: veritabanı tabloları ve sürümü, uploads/flx-certificates yazılabilir mi, sertifika adres kuralı (kalıcı bağlantılar), aktif şablon, PDF çizici/yazı tipi dosyaları, personel rolü.
- Gönderim ve cron: kuyruk işleyicisi ve son çalışma zamanı, 10 dakikadan uzun bekleyen mesaj (kuyruk çalışmıyor uyarısı), DISABLE_WP_CRON varsa sunucu cron'u hatırlatması, son 24 saatte başarısız gönderimler, Flux Mail etkin mi, WhatsApp/SMS için etkin bağlantı ve son başarılı gönderim.
- WhatsApp/SMS satırları Flux Mail bağlantılarına bakar; bağlantı testi İletişim › Bağlantılar'dadır. Kontroller dış istek yapmaz.
- Site: tema uyumu, açık etkinliklerin sitede sayfası yayında mı, sorgulama kısa kodu bir sayfada var mı, Flux Forms yüklü mü.
- Ortak sözleşme: kontroller `flx_system_checks` filtresine de kaydedilir (source `flx-certificates`); tema tüm FluxWP eklentilerinin kontrollerini tek ekranda toplayabilir.

### Sertifika sayfası hızı (v1.2.3)

- Sertifikayı tarayıcıda çizen kütüphaneler (~2,7 MB) artık sayfayla birlikte yüklenmez. Görsel varsa sayfa yalnızca görseli ve küçük bir script'i yükler; eksik PDF/paylaşım görseli sayfa açıldıktan sonra arka planda üretilir. Ölçüm (yavaş mobil bağlantı): PDF'i eksik bir sertifikada açılış 12,6 sn → 3,4 sn.
- Eksik dosyalar tek tek yüklenir; biri sunucu yükleme sınırını aşarsa diğerleri yine kaydedilir. Başarısız denemeler 6 saatte en fazla 12 kez yapılır, aynı tarayıcı 6 saat tekrar denemez.
- Sayfa önbellekten eski haliyle gelirse önce sunucuya hâlâ eksik dosya var mı diye sorulur; yoksa hiçbir şey yüklenmez.
- PDF dosyası yokken "PDF indir" tıklanınca PDF tarayıcıda oluşturulup indirilir (önceden sayfaya geri dönüyordu). PDF adı sertifikadaki isimle verilir.
- Flux SEO eklentisinin varsayılan og:image etiketi sertifika sayfasında basılmaz (WhatsApp önizlemesi kişiye özel görseli alır).
- Sayfanın kendisinin hızı için Hostinger'da LiteSpeed Cache açılması önerilir; sertifika sayfası önbelleğe uygundur.

### Dashboard günlük adetler (v1.2.2)

Günlük/aylık fidan ve sertifika grafiğinde her barın üstünde adet yazar. Dar barlarda yazı küçülür ya da dikey yazılır; çok dar olduğunda (ör. mobilde 90 gün) yazı gizlenir, adet üzerine gelince görünür. Kümülatif görünümde etiket yoktur.

### Geçmiş tarihli sertifika (mutabakat, v1.2.1)
Yeni sertifika ekranında, Kişiler kartındaki **Geçmiş tarihli oluştur** bölümü açılır, kutu işaretlenip tarih seçilir (yalnızca yönetici görür). O listedeki sertifikaların tarihi, numaradaki yılı, şablondaki tarih alanı, sertifika sayfasındaki "Düzenlenme tarihi", liste ve Dashboard raporları bu tarihi kullanır. Gönderim her zamanki gibi yapılır. Listede "geçmiş tarihli" etiketi görünür (üzerine gelince gerçek oluşturma zamanı). Temizle'ye basınca seçenek kapanır.

## Bağışçının seçtiği tasarımlar (v1.4.0)
Şablon editöründeki "Bağış sayfası" kartında "Bağışçı bu tasarımı bağış sayfasında seçebilir" işaretlenen aktif şablonlar, bağış sayfasında başlığın (grup; boşsa "Sertifikalar") altında listelenir. Sıra alanı küçükten büyüğe dizer. Bağışçı şablonun metin/tarih/sayı/seçim alanlarını (alıcı adı, mesaj vb.) doldurur; fiyat bağış türünden gelir (1 fidan = 150 TL).
Diğer eklentiler için: `flxs_donor_templates()` → [{id, name, group, sort, thumb, fields:[{key,label,type,required,placeholder,options,default}]}]. Sertifika `flxs_create_certificate()` ile seçilen template_id ve bu alanların değerleriyle oluşturulur.

### Örnek Vakfı e-kartları (v1.5.0)
example.org/e-kartlar'daki 16 tasarım eklentiyle gelir; "ÖRNEK" filigranı ve örnek isimler temizlenmiştir. Kurulumda ve güncellemede Şablonlar listesine kendiliğinden pasif olarak eklenir (v1.6.1); aktif yaptığınız tasarım bağış sayfasında "Sertifikalar" altında seçilebilir. Silineni "Örnek Vakfı e-kartları › İçe aktar" yeniden ekler. İsim alanları örnek isimlerin yerinde: `ad_soyad` alıcı (Flux Donate doldurur), `gonderen` bağışlayan, Taziye'de `anilan`, rozetli iki tasarımda `fidan_sayisi` (Flux Donate doldurur). 1.4.1 ile indirilmiş filigranlı şablonlar 1.5.0'a güncellenince kendiliğinden düzelir. "Görselleri zip indir" temiz görselleri toplu verir.

## PDF ne zaman oluşur (v1.6.0)
PDF, sertifika görseli ve paylaşım görseli sunucuda üretilir (eklentiye gömülü TCPDF + FPDI, PHP GD yeterli; Hostinger'da var). Flux Forms / Flux Donate'ın oluşturduğu sertifikalarda dosyalar bir dakika içinde arka planda hazırlanır; sayfa ya da PDF linki daha önce açılırsa o anda üretilir. Yeni Sertifika ekranında PDF yine personelin tarayıcısında çizilir; tarayıcı yükleyemezse sunucu bir dakika sonra tamamlar. Eksik dosyalar Sistem Kontrolü'ndeki "Sunucuda PDF üretimi › Eksikleri oluştur" ile toplu üretilir. Panelden yüklenen PDF arka planlarda görüntü için Imagick gerekir; yoksa görselleri sertifika sayfasını açan tarayıcı tamamlar.

## Mobil
- Listeler (Sertifikalar, Kişiler, Kuyruk, Kayıtlar) Flux Admin 1.2.0+ kuruluysa ortak filtre çubuğu ve sayfalamayı kullanır: arama + Filtreler düğmesi, 2 temel filtre, diğerleri panelde; sayfa başı adet kullanıcı başına hatırlanır. Telefonda toplu işlem çubuğu bir sertifika seçilince görünür.
Yeni Sertifika ekranında canlı önizleme telefonda formun üstünde görünür.

## Excel'e aktarma (v1.3.1)
Sertifikalar listesinin sağ üstündeki "Excel indir (adet)" butonu, o an seçili filtrelere (arama, şablon, durum, etkinlik, referans, oluşturan) uyan tüm sertifikaları tek xlsx dosyası olarak indirir; sayfalama uygulanmaz. Sütunlar: sertifika no, tarih, ad soyad, telefon, e-posta, etkinlik, şablon, referans, oluşturan, durum, görüntülenme, gönderilen mesaj kanalları, sertifika linki. Başlık satırı sabit, filtre açık. Başlıklar kullanıcının diline göre (TR/EN). Personel yalnız kendi oluşturduklarını indirir. Her indirme Loglar'a "Sertifikalar Excel'e aktarıldı" olarak yazılır. Sunucuda PHP zip eklentisi gerekir (Hostinger'da var).

## Etkinlikler (Sertifikalar › Etkinlikler)
Fidanlar etkinlik bazlı toplanır (ör. 11 Kasım fidan dikimi).
1. **+ Yeni etkinlik**: ad, tarih, isteğe bağlı fidan hedefi ve not. İlk etkinlik oluşturulurken "etkinliksiz sertifikaları bu etkinliğe ekle" kutusu işaretliyse mevcut sertifikalar bu etkinliğe geçer.
2. **Yeni sertifika** ekranında **Etkinlik** seçilir. Varsayılan: son seçilen açık etkinlik.
3. Etkinlik kartında fidan, sertifika ve kişi sayısı ile hedef ilerlemesi görünür. Dashboard ve Sertifikalar butonları o etkinliğe süzülmüş açılır.
4. Etkinlik bitince **Kapat ve arşivle**. Arşiv sekmesine düşer, sertifikaları geçerli kalır, yeni sertifika eklenemez. **Yeniden aç** ile geri alınabilir. Arşivdeki etkinlik silinirse sertifikalar silinmez, etkinliksiz kalır.
5. Tüm Sertifikalar'da etkinlik filtresi ve sütunu var. Seçilenlere › **Etkinliğe ata…** ile toplu taşıma yapılır.

### Sitede etkinlikler (v1.1.0)
- Her açık etkinliğin sitede yayınlanmış sayfası olur: bağlı sayfa yoksa aynı adlı etkinlik sayfasına bağlanır, o da yoksa oluşturulur; taslaktaysa yayınlanır (v1.1.4, güncellemede bir kez otomatik). Etkinlik kartında sayfa yayında değilse **Sitede sayfa oluştur / Sitede yayınla** butonu çıkar. Ana sayfadaki kartın her yerine tıklayınca etkinlik sayfası açılır; "Fidan bağışla" bağış sayfasına gider.
- **Etkinlik formu:** tarih, saat, kısa açıklama, görsel, hedef, **ek fidan** (sertifikasız bağışlar), konum (yer adı, adres, harita linki ya da koordinat, konumun açıklanacağı tarih) ve **sitedeki sayfa**.
- **Sitedeki sayfa:** temanın Etkinlikler içerik tipine bağlanır. "Yeni etkinlik sayfası oluştur" işaretliyse sayfa otomatik açılır, görsel öne çıkan görsel olur. Uzun açıklama ve fotoğraflar sayfa editöründen eklenir.
- **Sitede görünen adet** = sertifikalardaki fidan + ek fidan.
- **Etkinlik sayfası:** sayfanın başına otomatik bir panel eklenir. Panelde fidan sayacı, katkı veren kişi sayısı, hedef çubuğu, tarih/saat ve konum yer alır. Harita tıklanınca yüklenir. Altında "Bu etkinlik için fidan bağışla" butonu çıkar.
  - Konum boşsa sitede "Konum yakında" ve açıklanacağı tarih yazar (boşsa etkinlikten 10 gün önce). Devlet konumu bildirince formdan girilir, sitede hemen görünür.
  - Kapatılan etkinlikte "fidan toprakla buluştu" yazar, bağış butonu çıkmaz.
- **Ana sayfa:** istatistik bandının hemen altında "Yaklaşan Etkinlikler" bölümü çıkar (açık etkinlikler). Etkinlikler sayfasının altındaki **Sitede görünüm** kısmından kapatılabilir, başlığı ve bağış adresi değiştirilebilir.
- **/etkinlikler/ listesi:** kartlarda fidan rozeti görünür.
- **Kısa kodlar:** `[flxs_etkinlikler]`, `[flxs_etkinlikler arsiv="1"]`, `[flxs_etkinlik id="3"]`, `[flxs_fidan_sayaci]` (toplam), `[flxs_fidan_sayaci etkinlik="3"]`.

Oluşturma ekranında **Temizle** sonrası fidan sayısı 1 gelir. Son kullanılan gönderim kanalı ve sağlayıcı hatırlanır ve hep seçili gelir.

## Dashboard (Sertifikalar › Dashboard)
Sol menüde Sertifikalar'a tıklanınca açılan ilk sayfa.
- **Filtreler:** Bugün, 7/30/90 gün, Bu ay, Bu yıl, Tümü ya da iki tarih arası. Ayrıca etkinlik (arşivdekiler dahil), referans personel ve şablon seçilebilir. Etkinlik seçilince büyük kartta o etkinliğin toplam fidanı ve hedef ilerlemesi görünür. Seçimler hatırlanır.
- **Rakamlar:** Bugüne kadar hediye edilen toplam fidan, dönemdeki fidan, sertifika ve kişi sayıları (önceki eşit dönemle % karşılaştırma), ilk kez gelen kişi sayısı, sertifika başına ortalama fidan, en yoğun gün ve gönderim başarı oranı.
- **Grafikler:**
  - Günlük fidan (90 günden uzun dönemlerde aylık). Fidan, Sertifika ve Kümülatif görünümleri var; çubuğun üzerine gelince o günün detayı çıkar. **Tablo** butonu grafiği tabloya çevirir.
  - Haftanın günlerine göre fidan.
  - Gönderimler: kanal ve durum bazında.
  - Referans personel ve şablonlara göre fidan/sertifika.
- **Listeler:** En çok fidan hediye eden 10 kişi.
- **CSV:** Günlük, personel ve şablon verilerini Excel'de açılabilir dosya olarak indirir.
- İptal edilen sertifikalar sayılmaz. Fidan sayısı sertifikadaki `fidan_sayisi` alanından okunur. "Personel yalnızca kendi oluşturduklarını görsün" açıksa personel yalnızca kendi rakamlarını görür.

## Gönderim kanalları (v1.3.0: Flux Mail)
WhatsApp, SMS ve e-posta **Flux Mail** eklentisi üzerinden gider; Flux Mail kurulu ve etkin olmalı. Bu eklentide ayrı sağlayıcı ekranı yoktur.
- WhatsApp (Evolution) ve SMS (Netgsm) bağlantıları, testleri ve gönderen e-posta adresi **İletişim › Bağlantılar / Ayarlar**'dadır. Şifre ve API key'ler orada saklanır, panelde ve loglarda gösterilmez.
- İletişim'deki her etkin bağlantı Yeni sertifika ve Toplu gönderim ekranlarında seçilebilir; seçilmezse İletişim'deki varsayılan bağlantı kullanılır.
- Sertifika e-postası İletişim'in kurumsal şablonuyla gider (logo, alt bilgi, tıklanabilir sertifika görseli); metni İletişim › E-postalar'da düzenlenir.
- Flux Mail etkin değilse Sistem kontrolü hata gösterir; WhatsApp/SMS gönderilemez, e-posta WordPress'in kendi gönderimiyle gider.

Mesaj metinleri **Ayarlar** sayfasında düzenlenir, istenirse şablon bazında ayrıca ayarlanabilir. Kullanılabilecek değişkenler: `{ad}`, `{ad_soyad}`, `{sertifika}`, `{link}`, `{sertifika_no}`, `{fidan_sayisi}`.

## Kuyruk ve loglar
- Gönderimler kuyruğa alınır ve arka planda işlenir. Hata olursa 1, 5, 15 ve 30 dakika arayla yeniden denenir.
- **Gönderim Kuyruğu** ekranında bekleyen, gönderilen ve başarısız mesajlar ayrı görünür. Detay, yeniden dene ve iptal butonları var.
- **Toplu Gönderim** ekranında önce kaç kişiye gideceği önizlenir, sonra gönderilir.
- **Kayıtlar (log)** ekranı kim neyi ne zaman yaptı, mesajın sonucu ve IP'yi gösterir. CSV olarak indirilebilir.
- **Cron:** WP-Cron ziyaretçi gelince çalışır. Sunucuda gerçek cron önerilir: `*/1 * * * * curl -s https://example.org/wp-cron.php >/dev/null`. Action Scheduler (WooCommerce) varsa otomatik olarak o kullanılır.

## Link önizlemesi
- Her sertifika için 1200x630 boyutunda bir paylaşım görseli üretilir. Görselde isim ve sertifika yer alır.
- Başlık örneği: "Doğan Ailesi adına 5 fidan bağışlandı 🌱". Başlık ve açıklama şablon ayarlarından değiştirilebilir.
- Sertifika sayfaları varsayılan olarak `noindex` (arama motorlarında çıkmaz).

## Eksik dosyaların tamamlanması (v1.2.0)
Sunucu tarafında (ör. Flux Forms otomasyonuyla) oluşturulan sertifikaların PDF'i, görseli ve link önizleme görseli yoktur; bunlar tarayıcıda çizilir. Sertifika sayfası ilk açıldığında eksik dosyalar çizilip bir kez yüklenir (REST `public-files/{kod}`, sayfaya özel imza ile; yalnızca eksik dosya yazılır). Link önizleme görseli bu ilk açılıştan sonra hazır olur.

## Sertifika sorgulama
`[flx_sertifika_sorgula]` kısa kodu herhangi bir sayfaya eklenir. Kişi telefonunu veya e-postasını yazar, link yalnızca kendi kayıtlı adresine gönderilir. Bu özellik Ayarlar'dan kapatılabilir.

## Şablon düzenleme
1. Canva'da tasarım yapılır, değişecek yazılar (isim, metin) silinir ve **PDF Baskı** olarak indirilir.
2. **Şablonlar › Düzenle › Arka plan › Dosya seç** ile PDF yüklenir. Sayfa boyutu otomatik alınır.
3. Alanlar eklenir: metin, çok satırlı metin, tarih, sayı, otomatik numara, QR, sabit metin. Alanlar önizlemede sürüklenip boyutlandırılır.
4. Metinlerde `{alan_adı}` kullanılabilir, ör. `{fidan_sayisi} adet fidan`.
5. Şablonlar JSON olarak dışa aktarılıp başka bir siteye içe aktarılabilir. Arka plan dosyası da JSON'un içinde taşınır.
6. Yeni font eklemek için **Ayarlar › Ek fontlar** kısmına TTF/OTF yüklenir.

## Dil desteği (i18n, v1.1.1)
- Kaynak metinler Türkçe, text domain `flx-certificates`. Sitenin/ziyaretçinin dili İngilizce olunca (en_US, en_GB vb.) sitedeki etkinlik bölümü, etkinlik paneli, sertifika sayfası, sorgulama formu ve sorgulama mesajları İngilizce görünür.
- Tarih ve sayılar site diline göre yazılır (`date_i18n`, `number_format_i18n`): TR'de "11 Kasım 2026, Çarşamba · 1.253", EN'de "Wednesday, November 11, 2026 · 1,253".
- Çeviri dosyası: `languages/flx-certificates-en_US.l10n.php`. Başka dil için `languages/flx-certificates.pot` Loco Translate / Poedit ile çevrilir.
- Etkinlik adı, özeti gibi panelden girilen içerikler veri olduğu için TranslatePress gibi çok dil eklentisiyle çevrilir. "Yaklaşan Etkinlikler" başlığı değiştirilmediyse otomatik çevrilir.
- Yönetim paneli metinleri Türkçe kalır.

## Taşıma (export/import)
- Tüm veriler `wp_flxs_*` tablolarında, dosyalar `wp-content/uploads/flx-certificates/` klasöründe durur.
- All-in-One WP Migration, Duplicator gibi tam site taşıma araçları her şeyi birlikte taşır. Linkler yeni alan adına göre otomatik oluşur.
- Eklenti silinince veriler **silinmez**. Silinmesi istenirse **Ayarlar › Veri** kısmındaki kutu işaretlenir.
