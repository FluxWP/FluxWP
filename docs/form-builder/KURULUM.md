# Flux Forms 2.1.0 · Kurulum ve Kullanım

Easy Form Builder'ın (WhiteStudio, GPL) fork'u. Builder arayüzü aynı kaldı; lisans/Pro kilitleri, dış sunucu bağlantıları ve telemetri kaldırıldı, eksik eklentiler (Stripe, koşullu mantık, çevrimdışı ülke listeleri) yeniden yazıldı. Üstüne Dashboard, Otomasyonlar (sertifika entegrasyonu) ve tema uyumu eklendi.

## Sürüm 2.1.3
Formlar › Otomasyonlar'daki "Yeni otomasyon" düğmesinin yazısı Flux Admin açıkken yeşil üstüne yeşil kalıyordu; artık beyaz.

## Sürüm 2.1.2
Flux Speed kuruluysa Sistem kontrolü ekranı Performans › Sistem Kontrolü'nde; Formlar › Sistem kontrolü menüsü eklenmez, eski adres oraya yönlenir. Flux Speed yoksa eskisi gibi.

## Sürüm 2.1.0

Flux Donate 2.0 (WooCommerce) ve Flux Mail ile birlikte çalışmak için.

- **Ödeme WooCommerce'te:**
  - Flux Donate 2.0 etkinse bir bağış türüne bağlı formun **Öde** adımı seçimi WooCommerce sepetine koyar ve ödeme sayfasına götürür. Kart, havale ve aylık bağış orada alınır.
  - Formun yanıtı ve ödeme satırı sipariş ödenince "tamamlandı" olur.
  - Ödemesiz formlar eskisi gibi çalışır.
- **"Ödeme tamamlandı" otomasyonları:**
  - Sipariş ödenince Flux Donate sunucu tarafında `do_action( 'flxf_external_payment', $ctx )` çağırır. Flux Forms bununla o yanıtın akışlarını, form içinde ödenmiş gibi çalıştırır.
  - `$ctx['verified_payment']`, tutarı sunucunun hesapladığı haliyle taşır.
  - Her akış aynı takip kodu için bir kez çalışır.
  - Bu bir PHP aksiyonudur; istekten gelen bir parametreyle tetiklenemez.
- **Çift sertifika olmaz:**
  - `flxf_automations_form_issues_certificate( $form_id )` formda "Sertifika oluştur" adımı olan etkin bir akış olup olmadığını söyler.
  - Varsa Flux Donate o form için ayrıca sertifika üretmez.
- **WooCommerce teşekkür sayfasında sertifika kartı:**
  - Akışın "Teşekkür ekranında göster" kartı sipariş teşekkür sayfasında görünür.
  - Stil ve betik `do_action( 'flxf_enqueue_certificate_card' )` ile yüklenir.
- **E-postalar Flux Mail'den:**
  - Flux Mail etkinse formların e-postaları **İletişim › E-postalar**'da "Flux Forms" grubunda düzenlenebilir şablonlar olur ve ortak tasarımla gider. Şablonlar:
    - `flx-forms/new-message`: yöneticiye yeni mesaj;
    - `flx-forms/form-received`: ziyaretçiye, takip kodlu;
    - `flx-forms/reply-received`;
    - `flx-forms/subscribed`;
    - `flx-forms/survey-done`;
    - `flx-forms/register`;
    - `flx-forms/new-user`;
    - `flx-forms/password-recovery`;
    - `flx-forms/automation-email`: otomasyonların "E-posta gönder" adımının çerçevesi.
  - Değişkenler: `{konu}`, `{mesaj_html}` (takip kodu ve gönderilen değerler tablosu), `{takip_kodu}`, `{link}`, `{kullanici}`.
  - Flux Mail yoksa ya da `add_filter( 'flxf_use_iletisim', '__return_false' )` kullanılırsa Flux Forms eski e-postalarını kendisi gönderir (`wp_mail`).
  - Test e-postası, sorun bildirimi ve haftalık rapor her zaman Flux Forms'un kendi göndericisinden gider.
  - Şablon listesi `flxf_email_types` filtresiyle değiştirilebilir.
- **Menü simgesi:**
  - Formlar menüsünde ortak FluxWP logosu kullanılır (`assets/icon.svg`).
  - Değiştirmek için `flxf_menu_icon_url` filtresi.

## Sürüm 2.0.9

- Sistem kontrolü (Flux Donate yoksa Formlar › Sistem kontrolü): rozetlere tıklayınca filtreleme, `#error` ile filtreli açılış; Türkçe sitede saat 24 saat biçiminde.

## Sürüm 2.0.8

- **Sistem kontrolü:** Flux Forms kendi kontrollerini ortak ekrana ekler: ödeme devri (Flux Donate etkinse ödemeler onda; değilse Stripe anahtarları), Flux Certificates sürümü (sertifika otomasyonları için en az 1.2.0; sertifika adımı olan açık otomasyon varken Flux Certificates kapalıysa Hata), açık otomasyon sayısı ve son 7 gündeki başarısız çalışmalar, reCAPTCHA açık form varken anahtarlar, yüklemeler klasörünün yazılabilirliği.
- Ekran Flux Donate etkinse **Bağışlar › Ayarlar › Sistem kontrolü**'nde (tüm FluxWP eklentilerinin satırlarıyla), Flux Donate kapalıysa **Formlar › Sistem kontrolü**'ndedir. Hata varken Başlangıç ekranında uyarı ve üst çubukta "Sistem: N hata" görünür; "Sonraki güncellemeye kadar gizle" ile gizlenir.
- Ortak sözleşme (`flx_system_checks` filtresi, `flx_system_checks_run()`, `flx_system_checks_render()`): Flux Donate'ın API.md dosyasında.

## Sürüm 2.0.7

- Ödeme sağlayıcısı ayarlı değilse formda ham hata yerine "Online ödeme şu anda kullanılamıyor" mesajı.
- Koyu mod temayla birebir (yalnız tema koyu moddayken); filtre flxf_front_dark_mode.

## Sürüm 2.0.6

- Flux Donate kuruluysa fiyatların tek kaynağı Flux Donate: türdeki fiyat değişince bağlı formun seçenekleri, tahsilat ve sertifikadaki fidan sayısı ona göre güncellenir. Flux Donate yoksa fidan 150 ₺.
- URL ön-doldurmadaki (vefb) indeks hatası düzeltildi.

## Sürüm 2.0.5

- Flux Donate eklentisiyle birlikte çalışır: Flux Donate etkinken ödeme altyapısı ona devredilir.
- Builder: sürüklerken fazladan boşluk kalktı, bırakma yeri kesikli alanla gösteriliyor.
- Ödeme kart alanları ve Ayarlar sayfası düzeltmeleri; fidan fiyatı 150 ₺.

## Sürüm 2.0.4 yenilikleri

- Ayarlar sayfası yeniden tasarlandı: sol sekme menüsü, kart bölümler, anahtar (switch) düğmeler, yapışkan Kaydet. Lisans/plan kalıntıları gizlendi.
- Tüm yükleniyor ekranları (panel, ayarlar, modal, form gönderimi) tek tip halka animasyon.
- Fidan Bağışı formu 2 adım: ödeme 2. adımda.
- Ödeme sağlayıcı altyapısı (bkz. bölüm 4).
- Düzeltme: boş e-posta alanı Ayarlar'ın kaydedilmesini sessizce engelliyordu.

## 1. Kurulum

1. **Önce eski sürümleri kaldırın:** Flux Forms v1.0 (sıfırdan yazılmış ilk sürüm) ya da orijinal Easy Form Builder kuruluysa devre dışı bırakıp silin. İkisi aynı anda açık olmamalı.
   - Orijinal Easy Form Builder'da formlarınız varsa: silmeden önce Flux Forms'u kurmayın; EFB'yi **devre dışı bırakın (silmeyin)**, sonra Flux Forms'u etkinleştirin. Aynı veritabanı tablolarını kullandığı için formlar ve yanıtlar olduğu gibi gelir. Ardından EFB'yi silebilirsiniz.
2. **Eklentiler › Yeni Ekle › Eklenti Yükle** › `flx-forms.zip` › Kur › Etkinleştir.
3. Sol menüde **Formlar**: Dashboard, Panel, Oluştur, Eklentiler, Stripe Ödemeleri, Otomasyonlar.

Gereksinim: WordPress 6.4+, PHP 7.4+. Dil: site dili Türkçe ise arayüz Türkçe, değilse İngilizce (`languages/flx-forms-tr_TR.*`).

## 2. Form oluşturma

**Formlar › Oluştur** › şablon seçin (Yeni Form, İletişim, Ödeme, **Fidan Bağışı**, Anket…). Builder yeni görünümde:
- **Solda eleman paneli:** arama kutusu + kategori sekmeleri (Tümü / Temel / Ödeme / Gelişmiş); kaydırırken sabit kalır.
- **Ortada form:** sitedeki temanın renkleri ve yazı tipleriyle görünür, önizlemeyle birebir aynı.
- **Sağda ayar paneli:** bir alana tıklayınca açılır (dişli simgesine gerek yok); Esc ya da dışarı tıklama kapatır. Form ayarlarında üstte Genel / Bildirimler / SMS / Koşullu mantık / Teşekkür ve gelişmiş kısayolları var.
- **Panel listesi, yanıtlar ve yanıt detayı** da yenilendi: detay penceresinde ödeme bilgisi, alanlar ve toplam tek sayfada; okunmamış yanıtlar kalın.

Eski EFB görünümüne dönmek için: `add_filter( 'flxf_builder_skin', '__return_false' );`

Eklenen alanlar dahil tüm elemanlar açık: Metin, Ad, Şifre, E-posta, Sayı, Uzun Metin, Adım, Onay Kutusu, Radyo, Açılır Liste, Çoklu Seçim, Telefon, Cep Telefonu, Aralık, Tarih, Dosya Yükleme, Sürükle-Bırak Dosya, Adres, Ülke/İl/İlçe listeleri, Konum Seçici (OpenStreetMap), İmza, Puanlama, NPS, Görsel Seçici, Şartlar (KVKK), Ödeme Çoklu/Tek Seçim, Tutar Alanı, Toplam Tutar, Stripe, ses/video/ekran kaydı ve daha fazlası.

**Son adım başlığı:** adım çubuğundaki son adımda "Bitir" yazar. Formun ayarlarında (teşekkür mesajı alanlarının üstünde) **Son adım başlığı** kutusuna örneğin "Fidan bağışınız alındı" yazarak değiştirebilirsiniz. Boş bırakılırsa varsayılan metin kullanılır. Fidan Bağışı şablonu bu metinle gelir.

**Koşullu mantık:** builder'da form ayarlarındaki "Koşullu mantık" düğmesi. Alan/adım göster-gizle, zorunlu yap, devre dışı bırak, gönderimi engelle. Kurallar sunucuda da uygulanır (gizli alanın değeri kaydedilmez, görünür zorunlu alan boş geçilemez).

## 3. Formu sayfaya ekleme

Panel'deki kısa kodu kopyalayın: `[EMS_Form_Builder id=3]` — sayfaya/yazıya "Kısa kod" bloğu ile ekleyin. Elementor/WPBakery widget'ları da mevcut.

Formlar temanın renklerini, yazı tiplerini ve karanlık modunu otomatik kullanır. Kapatmak isterseniz: `add_filter( 'flxf_theme_style', '__return_false' );`

## 4. Ödeme (Stripe ve diğer sağlayıcılar)

**Flux Donate eklentisi kuruluysa** ödemeler, sağlayıcılar, webhook'lar ve Ödemeler sayfası Flux Donate'a geçer (bkz. Flux Donate KURULUM). Flux Donate 2.0 ile türe bağlı formlar WooCommerce ödeme sayfasında ödenir (bkz. Sürüm 2.1.0). Aşağıdakiler Flux Donate yokken geçerlidir.

Ödemeler sağlayıcıdan bağımsız bir katmandan geçer. Şu an çalışan tek sağlayıcı Stripe; yeni sağlayıcı (iyzico, PayTR vb.) çekirdeğe dokunmadan bir sınıf + bir filtreyle eklenir. Geliştirici rehberi: eklenti içinde `docs/PAYMENT-PROVIDERS.md`.

1. Stripe › Developers › API keys: `pk_…` ve `sk_…`.
2. **Formlar › Panel › Ayarlar › Ödeme** sekmesinde anahtarları girin; "Etkin ödeme sağlayıcısı" Stripe kalsın, kaydedin.
3. **Webhook (önerilir):** **Formlar › Ödemeler** sayfasındaki adresi Stripe › Webhooks'a ekleyin, olaylar: `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`, `charge.refunded`, `customer.subscription.*`. Verilen `whsec_…` anahtarını aynı sayfaya yapıştırın.
4. Test kartı `4242 4242 4242 4242`. Sonra canlı anahtarlara geçin.

Güvenlik: tutar her zaman sunucuda formdan hesaplanır. Ziyaretçi seçim yaptıkça form, tutarı sunucudan alır ve kutuda onu gösterir; Öde düğmesi ancak sunucu tutarı onaylayıp kart bilgileri tamamlanınca açılır. Ödeme doğrulanmadan yanıt tamamlanmaz. Varsayılan para birimi TRY. Ödeme listesi: **Formlar › Ödemeler** (eski "Stripe Ödemeleri" adresi buraya yönlenir).

**Fidan Bağışı formu 2 adımlı:** 1. adım bağış bilgileri (ad, telefon, e-posta, tutar), 2. adım ödeme (KVKK onayı + kart). Mevcut Fidan Bağışı formu güncellemede otomatik bu düzene geçer; düğmeler "Sonraki / Önceki / Gönder".

Henüz yok: iade, yönlendirmeli (3D sayfasına giden) sağlayıcılar için dönüş ekranı, form bazlı sağlayıcı seçimi.

## 5. Dashboard

Formlar › Dashboard: yanıt, okunmamış, görüntülenme, dönüşüm oranı, tahsilat; günlük grafik, formlara göre dağılım, ödeme durumları, gün/saat yoğunluğu, son yanıtlar ve en çok yanıt alan formlar. 7/30/90 gün veya özel aralık, form filtresi, **CSV indir** (Excel uyumlu, formül enjeksiyonuna karşı korumalı). "Aç" bağlantısı yanıtı Panel'de açar.

## 6. Otomasyonlar ve sertifika

Formlar › Otomasyonlar. Her akış: **tetikleyici** (form gönderildi / ödeme tamamlandı) + isteğe bağlı **koşullar** + sıralı **adımlar**:

- **Sertifika oluştur** (Flux Certificates şablonu; ad, telefon, e-posta alanları otomatik eşlenir; şablon değişkenleri form alanından, sabit değerden ya da "ödeme tutarı ÷ birim fiyat" ile)
- **Sertifika gönder** (e-posta / WhatsApp / SMS, Flux Certificates sağlayıcıları ile)
- **Teşekkür ekranında göster**: ödeme biter bitmez "Sertifikanız hazır" kartında sertifikanın kendisi görünür. Altında **PDF indir** ve **Paylaş** butonları (telefonda sertifika görseliyle paylaşım menüsü açılır, bilgisayarda WhatsApp), ayrıca "Sertifikayı görüntüle" bağlantısı vardır. Her biri adımdan açılıp kapatılabilir, metinleri değiştirilebilir. İstenirse sertifika sayfasına otomatik yönlendirme de yapılır. Kart açıldığında sertifikanın PDF'i, görseli ve paylaşım (OG) görseli sunucuya da kaydedilir.
- **E-posta gönder**, **Webhook**

Adımlar "+" ile eklenir, sürükleyerek sıralanır. Çalıştırma günlüğü sekmesinde her adımın sonucu görünür. Aynı gönderim için akış iki kez çalışmaz.

**Hazır kurulum (fidan bağışı):**
1. Otomasyonlar sayfasında **"Fidan Bağışı formunu oluştur"** (veya Oluştur › Fidan Bağışı şablonu): Ad Soyad, Cep telefonu, E-posta, 1/5/10 fidan seçenekleri, farklı tutar, Toplam, Stripe, KVKK.
2. **"Varsayılan akışı oluştur"**: ödeme tamamlanınca "Fidan Bağış Sertifikası" oluşturur (fidan sayısı = tutar ÷ 150 ₺; fidan fiyatı otomasyon adımından ya da `flxf_sapling_unit_price` filtresiyle değiştirilir), WhatsApp + e-posta ile gönderir, teşekkür ekranında gösterir.
3. Formun kısa kodunu bağış sayfasına ekleyin, akışı **Etkin** yapın.

Not: Flux Certificates **v1.2.0 veya üstü** etkin olmalı (eski sürümlerde otomatik sertifikanın sayfası boş görünebilir); WhatsApp/SMS için Flux Certificates › Sağlayıcılar'da Evolution/Netgsm bilgileri girilmeli. Teşekkür kartı formun teşekkür ayarı "mesaj" iken görünür (yönlendirme modunda değil).

## 7. Güvenlik ve gizlilik

- Dış sunucuya hiçbir istek gitmez (lisans, eklenti indirme, telemetri e-postası kaldırıldı). Harita döşemeleri OpenStreetMap'ten, Stripe yalnızca ödeme formlarında yüklenir.
- Admin yanıt görüntüleyicide XSS açığı kapatıldı; takip kodları tahmin edilemez (12 karakter, rastgele); eski kodlarda deneme sınırı.
- Yüklenen dosyalar `wp-content/uploads/flxf-uploads/` altında rastgele adlarla; listeleme ve script çalıştırma kapalı (.htaccess, LiteSpeed uyumlu).
- IP tespiti `REMOTE_ADDR`; Cloudflare/proxy arkasındaysanız: `add_filter( 'flxf_trusted_proxy', '__return_true' );`
- Bildirim e-postaları varsayılan açık; teslim için SMTP eklentisi (WP Mail SMTP + Hostinger e-posta) önerilir.

## 8. Önbellek (LiteSpeed)

Form sayfaları önbelleklenebilir. "JS geciktir/birleştir" açıksa ve form çalışmazsa `flx-forms/` altındaki JS dosyalarını hariç tutun.

## 9. Lisans ve kredi

GPL v2+. Easy Form Builder © WhiteStudio (whitestudio.team); kredi ve lisans bildirimleri `readme.txt` ve `README.md` içinde korunur.
