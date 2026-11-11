# Flux Mail – kurulum

Sitenin bütün e-postalarını, WhatsApp ve SMS bağlantılarını tek yerden yönetir. Menü: **İletişim**.

## 1. Eklentiyi kur

Eklentiler › Yeni ekle › Eklenti yükle › `flx-mail.zip` › Etkinleştir.
(FluxWP Panel kuruluysa Setup › Paketler'den **Kur** de olur.)

Etkinleşir etkinleşmez:
- `wordpress@…` adresi yerine **bilgi@example.org** kullanılır; parola/üyelik/yorum bildirimleri **no-reply@example.org** adresinden gider.
- WordPress, WooCommerce ve FluxWP eklentilerinin e-postaları logolu, mobil uyumlu kurumsal şablonla gider.
- Google ile kayıt olan kişiye "Oturum açma bilgileri / parolanızı belirleyin" e-postası gitmez.

## 2. E-posta hesapları (hPanel)

hPanel › E-postalar › E-posta hesapları:
1. `bilgi@example.org` hesabını oluşturun.
2. `no-reply@example.org`: ya ayrı hesap açın ya da bilgi@ hesabına **takma ad (alias)** ekleyin.

## 3. SMTP (önerilen)

İletişim › Ayarlar › Gönderim yöntemi › **SMTP** › **Hostinger ayarlarını doldur**:
- Sunucu `smtp.hostinger.com`, port `465`, `SSL`, kullanıcı `bilgi@example.org`
- Şifre: bilgi@ hesabının şifresi (şifreli saklanır, panelde/MCP'de görünmez).
- no-reply@ ayrı hesapsa "No-reply için ayrı kullanıcı" alanlarını doldurun.

İsterseniz şifreyi wp-config.php'ye yazın (panelde alan kilitlenir):
```php
define( 'FLXI_SMTP_PASS', '…' );
define( 'FLXI_SMTP_NOREPLY_PASS', '…' ); // isteğe bağlı
```

Kaydet › **Test e-postası gönder**. Gmail'de artık "via srv….main-hosting.eu" yazmamalı.

Başka bir SMTP eklentisi (WP Mail SMTP, LiteSpeed vb.) varsa ikisinden birini kullanın; Sistem kontrolü uyarır.

SMTP girişi başarısız olursa İletişim › Ayarlar › Gönderim yöntemi'nde hangi hesap (bilgi@ / no-reply@) ve neden yazar.
no-reply@ hesabına girilemiyorsa e-postalar kaybolmaz, şifre düzelene kadar bilgi@ ile gider. Şifreyi düzeltince no-reply@ ile test e-postası gönderin.

## 4. DNS (spama düşmemek için)

Ayarlar › Test › **DNS'i kontrol et**. Eksikse hPanel › Alan adları › DNS:
- SPF: `v=spf1 include:_spf.mail.hostinger.com ~all` (Hostinger genelde kendisi ekler)
- DKIM: hPanel › E-postalar › Bağlantı ayarları'ndaki kayıt
- DMARC (öneri): `_dmarc` TXT `v=DMARC1; p=none; rua=mailto:bilgi@example.org`

## 5. Görünüm

Ayarlar › Görünüm: logo (açık/koyu zemin), renkler, slogan, adres, telefon, sosyal medya, alt not.
Boş alanlar Flux SEO'daki kurum bilgilerinden doldurulur. Sağda canlı önizleme vardır.
WooCommerce varsa **Görünümü WooCommerce'e uygula** düğmesi logo/renk/alt yazıyı oraya da yazar.

E-postaya eklediğiniz görseller (WebP dahil) gönderimde JPG/PNG'ye çevrilip e-postaya gömülür; alıcı her programda görür.
Ayarlar › Görünüm › Görseller'den kapatılabilir.

Önizlemelerdeki **PDF indir** düğmesi e-postayı PDF yapar. Flux AI & MCP › Ayarlar › Görseller'de Gotenberg adresi varsa dosya doğrudan iner; yoksa yazdırma penceresinde "PDF olarak kaydet" seçin.

E-postaların en altındaki ağaç silüeti Ayarlar › Görünüm › Alt kısım çizimi'nden seçilir (Tepeler varsayılan; Orman, Çizgi, Yok). Koyu modu destekleyen programlarda (Apple Mail, iOS) koyu renkli çizim görünür.

## 6. E-postalar

İletişim › E-postalar: sitedeki bütün e-postalar gruplu listelenir (WordPress, Flux Certificates, WooCommerce,
diğer eklentiler). Anahtarla aç/kapat, **Düzenle** ile konu/başlık/metin/düğme ve gönderen (bilgi@ / no-reply@).
Önizleme masaüstü, telefon ve koyu mod; **Bana test gönder**.
Listede olmayan bir eklentinin e-postası ilk gönderildiğinde listeye kendiliğinden eklenir.

Blog yorum e-postaları (site e-posta adresi'a gelenler) "Yazıya yeni yorum" ve "Onay bekleyen yorum" şablonlarıdır.

## 7. Flux Certificates ile birlikte

Flux Certificates (1.3.0+) WhatsApp ve SMS'i İletişim › Bağlantılar'daki etkin bağlantılarla gönderir; ayrıca taşıma gerekmez.
Evolution / Flux WPAPI (WhatsApp) ve Netgsm (SMS) bilgilerini yalnız burada girin.

### WhatsApp numarası eklemek

1. İletişim › Bağlantılar › WhatsApp › **+ Evolution API** ya da **+ Flux WPAPI**.
2. Adres, genel API key ve bu numaraya bir instance adı (ör. `bilgi`) girin › Kaydet. İkinci numarada "Sunucu: Aynı sunucu" seçiliyse yalnız instance adı yeterli.
3. Açılan pencerede QR'ı telefonda WhatsApp › Bağlı cihazlar › Cihaz bağla ile okutun (ya da "telefon numarasıyla kod al"). "Bağlı · +90…" görünce bitti.
4. Numara bağlı değilken satırda **Bağlan** görünür; bağlıyken gizlenir. Durum ve QR her zaman **⋯** › Durum / yeniden bağla'da; aynı menüde yeniden başlat, oturumu kapat, sunucudan sil ve (Flux WPAPI) sunucu ayarları var.
5. **Test et** penceresinde alıcı numarayı yazıp Metin, Resim, Sesli mesaj, Belge (PDF) (Cloud API'de Şablon) seçin; önizlemeye bakıp **Gönder**. Her mesajın sonucu balonun altında görünür.
6. Flux Chat'nde hattı bu bağlantıdan seçin; webhook'u Çağrı Merkezi kurar.

### Resmi WhatsApp Cloud API (Meta)

1. Meta for Developers'ta bir uygulama açın, WhatsApp ürününü ekleyin, numarayı WhatsApp Manager'da ekleyip doğrulayın.
2. Business ayarları › Sistem kullanıcıları › kalıcı erişim anahtarı (whatsapp_business_messaging + whatsapp_business_management izinleri).
3. İletişim › Bağlantılar › **+ Cloud API (Meta)**: Phone Number ID, WABA ID, token, App Secret (zorunlu) › Kaydet.
4. Açılan **Kurulum** penceresindeki adresi ve doğrulama anahtarını Meta › WhatsApp › Yapılandırma › Webhook'a girin, "messages" alanına abone olun. Pencerede "Meta doğrulaması" tarihi görünür. App Secret girilmemişse gelen webhook istekleri reddedilir.
5. Kişi son 24 saatte yazmadıysa yalnız onaylı şablon gider; şablonları WhatsApp Manager'da açın, "Onaylı şablonlar"da görün.

Sertifika e-postası artık sertifika görselini gösterir (tıklayınca sertifika sayfası: indirme/paylaşma orada).
Görsel, sertifika dosyası henüz yoksa sunucuda GD ile çizilir (Hostinger'da GD+FreeType vardır).

## 8. Toplu gönderim

İletişim › Gönder: e-posta, WhatsApp ya da SMS; alıcılar elle (satır başına bir kişi), kullanıcı rolleri
veya Flux Certificates kişileri. Önce **Bana test gönder**. Gönderim kuyrukla, dakikada sınırlı sayıda gider;
sayfa açıkken hızlanır. Toplu e-postalara "Listeden çık" bağlantısı eklenir.

Gerçek cron önerilir (hPanel › Gelişmiş › Cron işleri, 15 dakikada bir):
```
wget -q -O /dev/null https://example.org/wp-cron.php?doing_wp_cron
```
ve wp-config.php: `define( 'DISABLE_WP_CRON', true );`

### Posta kutusu sınırları

İletişim › Ayarlar › **Posta kutusu sınırları**: Hostinger paketinin sınırları (varsayılan: günde 100 alıcı / posta kutusu,
ileti başına 100 alıcı, 35 MB e-posta, 25 MB ek, 1 GB depolama, 15.000 e-posta, 5 takma ad, 10 yönlendirici).
- Sayaç her posta kutusu için son 24 saatte giden alıcıyı sayar (Kime + Bilgi + Gizli). Kullanım çubuğu Ayarlar'da ve Gönder sayfasında.
- Toplu e-posta günlük sınırı aşmaz: "Tekil e-postalara ayrılan pay" (varsayılan 20) parola, bağış ve sertifika e-postalarına bırakılır,
  sınıra gelince gönderim duraklar ve hak açıldıkça kendiliğinden devam eder. Alıcı seçerken kaç günde biteceği yazar.
- Sistem kontrolü: %80'de uyarı, sınır dolunca hata; son 30 günde 3+ gün sınıra dayanıldıysa "üst pakete geçin" uyarısı.
- Paket yükseltince yalnız buradaki değerleri güncelleyin.

### Kara liste

Flux Chat'nin kara listesindeki kişilere (WhatsApp'ta "mesaj atmayın" diyen ya da yöneticinin eklediği) e-posta, WhatsApp ve SMS gitmez;
kayıtlarda "Kara listede: gönderilmedi" görünür. Kişinin kendi istediği parola sıfırlama e-postası gider. Listeden çıkarılınca gönderim yeniden başlar.

## 9. Posta kutusu

1. İletişim › Ayarlar › **Posta kutusu**: "Posta kutusunu aç"ı işaretleyin. Hostinger için hazır: `imap.hostinger.com`, 993, SSL.
2. Kullanıcı ve şifre boşsa SMTP'deki bilgi@ hesabı kullanılır. Şifre şifreli saklanır, ekranda görünmez.
   İsterseniz wp-config.php'ye `define( 'FLXI_IMAP_PASS', '…' );` yazabilirsiniz.
3. **Bağlantıyı dene** → gelen kutusundaki e-posta sayısı ve Gönderilmiş klasörü görünür. Kaydedin.
4. Sol menüde **Posta kutusu**: ilk açılışta son 30 günün e-postaları parça parça alınır (ekran açıkken kendiliğinden devam eder).
   Sonra birkaç dakikada bir yeni e-postalar alınır; WP-Cron çalışmıyorsa ekran açıkken 2 dakikada bir eşitlenir.
5. İmza: Ayarlar › Posta kutusu › İmza (`{ad_soyad}` yazanın adı olur).
6. AI cevapları için Flux AI kurulu ve anahtarı girili olmalı. AI'nın yazdığı her taslak "Onay bekleyen"de durur, siz Gönder demeden gitmez.
7. Şablonlar klasöründen "Örnek Vakfı × Firma" alt şablonu: firma adı, logo (Ortam kütüphanesinden), web sitesi, vurgu rengi, hazır konu/içerik.

Sunucudaki e-postalar silinmez. Sitedeki kopya "Sitede kaç gün saklansın" süresi sonunda silinir. Ekler sitede saklanmaz.
Posta kutusundan gönderilen e-postalar da günlük 100 gönderim sınırına sayılır.

## 10. Google ile giriş

Ayarlar › Google ile giriş: varsayılan olarak açık. Desteklenen eklentilerin (Nextend Social Login, Site Kit,
Super Socializer, miniOrange, Heateor vb.) kullanıcıya ait Google giriş kaydı (veya `flxi_user_is_social` filtresi) varsa kullanıcı "doğrulanmış" işaretlenir ve parola belirleme e-postası gönderilmez.
Kullanıcı profilinde "Google ile giriş" satırı görünür.

## 11. Kontrol

İletişim › Sistem kontrolü (ya da Görünüm › Sistem Kontrolü). Kayıtlar ekranında her gönderimin içeriği ve hatası görünür.
MCP'den: `flxi_email_log`, `flxi_templates`, `flxi_update_template`, `flxi_settings`, `flxi_send_test`, `flxi_campaigns`.

## Kaldırma

Devre dışı bırakmak verileri silmez. **Sil** ise tabloları ve ayarları kaldırır, Flux Certificates sağlayıcılarını eski haline getirir.
