# Flux Donate 2.0: Kurulum

Flux Donate 2.0 bağışları **WooCommerce** üzerinden alır. Flux WooCommerce Bağış (flx-wc-bagis) eklentisinin bütün özellikleri artık Flux Donate'ın içinde; ikinci bir bağış eklentisi yok.

## Gereksinimler

- WordPress 6.4+, PHP 7.4+
- **WooCommerce 8.0+** (canlı sitede 11.2.1 var; 9.6.2 ile test edildi). Eklenti başlığında `Requires Plugins: woocommerce` yazar: WooCommerce kurulmadan Flux Donate etkinleşmez.
- Tek seferlik kart ödemesi için resmî **iyzico WooCommerce** eklentisi (yoksa Flux Donate'ın kendi iyzico yöntemi kullanılır).
- Flux Certificates 1.2+ (ödemeden sonra sertifika), Flux Mail 1.0+ (e-posta şablonları; isteğe bağlı), Flux Forms **2.1.0+** (ödeme formları WooCommerce ödeme sayfasında ödenecekse).

## Temiz kurulum (2.0)

Site henüz canlı değil, bu yüzden 2.0 **veri taşımadan** kurulur (Ahmet, 2026-10-16).

1. **Flux WooCommerce Bağış'ı devre dışı bırakıp silin.** İkisi birlikte açık kalırsa her siparişe iki sertifika ve iki mesaj gider; Sistem kontrolü bunu kırmızı satırla gösterir.
2. WooCommerce'i kurup etkinleştirin (para birimi TRY, ödeme sayfası blok ya da klasik olabilir).
3. `flx-donate.zip` dosyasını **Eklentiler › Yeni ekle › Eklenti yükle** ile yükleyin ve etkinleştirin. Eski bir Flux Donate varsa "Yüklenenle değiştir" deyin; 1.x tabloları korunur, veritabanı yalnızca sütun ekler.
4. **Bağışlar › Bağış türleri**'nde türleri oluşturun (örnek: "Fidan Bağışı" ve "Bağış"). Her türün "Online bağış sayfası" kartında:
   - **"Bağış seçenekleri" bloğunda göster**: tür bağış sayfasında ve ürün XML'inde listelenir.
   - **Sertifika ver**, isterseniz farklı bir sertifika şablonu.
   - **− / + adetli seçenek**: bu seçeneğin kartında − / + düğmeleri olur (ör. "1 fidan" 150 ₺ → 3 fidan 450 ₺). Diğer seçenekler sabit paket kalır (5 fidan 700 ₺, 7 fidan 1000 ₺). Üst sınır türdeki "En fazla adet"tir; adet sunucuda tekrar kontrol edilir.
   - Serbest tutarlı türler için **hazır tutarlar** (ör. `250 500 1000`) ve en az tutar.
   - Seçeneklerde **rozet** (En popüler, Avantajlı, Aile paketi).
   Her tür kaydedilince arkada **gizli bir WooCommerce ürünü** oluşur (mağazada görünmez, sanal, vergisiz; ürün sayfası bağış sayfasına yönlenir). Fiyat her zaman türden gelir, tarayıcıdan gelen tutara bakılmaz.
5. **Bağışlar › Ayarlar › WooCommerce**: "Online bağış sayfasını oluştur" düğmesiyle sayfayı açın ya da kendi sayfanıza **Bağış seçenekleri** bloğunu (`flx-wc-bagis/secenekler`) veya `[flx_bagis_secenekleri]` kısa kodunu koyun ve sayfayı seçin. Temanın `/bagis/` kalıbı aynı bloğu kullanır.
6. **WooCommerce › Ayarlar › Ödemeler**:
   - **Havale (bağış)** (`flxb_havale`): IBAN, alıcı, banka, açıklama kodu şablonu. WooCommerce'in kendi "Doğrudan banka havalesi" (BACS) yöntemini kapatın. IBAN boş bırakılırsa temanın banka hesaplarından (`flxb_bank_accounts`) mağaza para birimindeki ilki kullanılır; alıcı ve banka da o hesaptan gelir (2.0.1), böylece IBAN tek yerde girilir.
   - **Kart (iyzico) - Flux Donate** (`flxb_iyzico`): 2.1.0'dan beri tüm kart ödemeleri (tek seferlik ve aylık) varsayılan olarak bununla, ödeme sayfasındaki kendi kart alanlarımızla alınır (bkz. "Kart ödemesi"). Etkin olmalı.
   - **iyzico** (resmî eklenti): yalnız anahtarlar için gerekir; bağışçıya gösterilmez. **Bağışlar › Ayarlar › WooCommerce › Kart ödeme ekranı** = "Tek seferlik bağışlarda resmi iyzico eklentisi" seçilirse tek seferlik bağışlar onunla alınır. İki kart yöntemi aynı anda gösterilmez.
   - **Stripe yok:** 2.0'da Stripe WooCommerce yöntemi olarak taşınmadı. Gerekirse resmî "WooCommerce Stripe" eklentisi kurulabilir (sertifika ve kayıt defteri her WooCommerce yöntemiyle çalışır; aylık bağış yalnızca Flux Donate iyzico ile).
7. **Bağışlar › Ayarlar › Sistem kontrolü**'nü açın; kırmızı ve sarı satırları düzeltin.

## Bağışçı ne görür

- **Bağış seçenekleri bloğu:** türler sekme olarak; sabit paket kartları, − / + adetli kart (canlı toplam), serbest tutar ve hazır tutar düğmeleri, "Her ay tekrarla" (yalnız düzenli bağışa açık türlerde ve Flux Donate iyzico hazırsa).
- **Kimin için:** "Kendim için" ya da "Başkası adına" (adı sertifikaya yazılır). Sertifikanın gideceği **WhatsApp** ve **e-posta** tam genişlikte kartlarda; boş bırakılırsa hesaptaki `billing_phone` ve hesap e-postası (yoksa ödeme sayfasındakiler) kullanılır. Başkası adına bağışta **"Bana da gönder"** ile bağışçı da kopyasını alır.
- **Sertifika:** "Online sertifika" (ücretsiz). **"Online + basılı sertifika" şimdilik "Yakında" rozetiyle kapalı gösterilir** ve seçilemez; elle gönderilse de sunucu reddeder. Açmak için **Ayarlar › WooCommerce › "Online + basılı sertifika"** = "Seçilebilir" (ücret ve posta adresi alanı o zaman devreye girer). "Gizli" seçeneği satırı hiç göstermez.
- **Sepet ve ödeme:** klasik ve blok ödeme sayfası. Sepette yalnızca bağış varsa şirket, adres 2. satır ve posta kodu gizlenir.
- **Tek iletişim bölümü (2.0.4):** sepette yalnız bağış varsa ödeme sayfasında "Fatura adresi" başlığı, ülke ve ayrı telefon alanı yok; tek bölümde **E-posta** ve **Telefon**, en az biri zorunlu (ikisi de yazılabilir), ad/soyad kalır. Yalnız telefon yazılırsa siparişe görünmeyen bir yer tutucu adres (`bagisci-<numara>@<site alan adı>`, 2.0.6'dan önce `@telefon.invalid`; iyzico onu hatalı format sayıyordu) yazılır; o adrese e-posta gitmez, bağışçı telefonla kaydedilir. Filtre: `flxb_wc_contact_either`.
- **Ödeme sayfası düzeni (2.0.4):** mobilde üstteki açılır sipariş özeti yok (alttaki kalır), başlığı **"Bağış özeti"**; "Sipariş ver" butonu altta sabit (basınca eksik alan gösterilir). Header'ın sabit olması tema 0.5.23'te.
- **Destek WhatsApp (2.0.4):** ödeme sayfasında ve havale bilgilerinin altında "Bir sorun mu var? WhatsApp'tan yazın: +90 555 000 00 00". Numara **Ayarlar › WooCommerce › Destek WhatsApp numarası**; boş bırakılırsa gizlenir.
- **2.0.5:** bağış formunda (sertifikalı türlerde) WhatsApp ya da e-postadan en az biri zorunlu; kendisi için bağışta bunlar ödeme sayfasına dolu gelir. "Her ay tekrarla" seçimlerin altında tek satır. Toplam ve **Devam et** altta sabit. Ödeme sayfasında header üstte sabit; altta yalnız ince bar (Toplam + **Ödemeye geç**), Bağış özeti normal akışta. Güncellemeden sonra LiteSpeed (ve WP Rocket / WP Super Cache) önbelleği bir kez temizlenir, eski sayfa eski CSS'i göstermez.
- **2.0.6:** bağış formunda yazılan WhatsApp/e-posta ödeme sayfasına kesin aktarılır (tarayıcıda da taşınır); ödeme sayfasında önce Telefon, sonra E-posta; yalnız telefonlu bağışta gizli adres site alan adında (filtre `flxb_wc_contact_domain`).
- **Sertifika tasarımı seçimi (2.0.7, Flux Certificates 1.4.0+):** Sertifikalar › Şablonlar'da "Bağışçı bu tasarımı bağış sayfasında seçebilir" işaretli aktif şablonlar bağış formunda grup adıyla (varsayılan "Sertifikalar") kart olarak çıkar. Seçilen tasarımın alanları (ör. mesaj, kimden) açılır; ad ve fidan sayısını Flux Donate kendisi doldurur. Seçim siparişte "Tasarım" olarak görünür, ödeme sonrası sertifika o şablonla hazırlanır (şablon listeden kalktıysa bağış türünün şablonu). Tutar değişmez. Liste boşsa bölüm görünmez.
- **2.2.1:** Flux Chat'nden (WhatsApp asistanı ya da temsilci) gelen bağış siparişlerinde havale onaylanınca sertifika artık hazırlanıp gönderilir (önceden bu siparişlerde "sertifika" seçimi olmadığı için hiçbir kanaldan gitmiyordu). Daha önce onaylanmış böyle bir sipariş için siparişin sağındaki Flux Donate kutusunda **"Sertifikayı hazırla"** düğmesi çıkar. Bağışa zaten sertifika bağlıysa ikincisi yapılmaz.
- **2.2.0 (Flux Roles ile):** Her bağışın bir **Referans**'ı olabilir: personelin kişisel linkiyle (`?ref=`) gelen bağış, Çağrı Merkezi'nde personelin kaydettiği bağış ya da "Bağış ekle"de seçilen kişi. Aylık bağışın sonraki tahsilatları ilk bağışın referansını taşır. Bağışlar listesinde Referans sütunu ve filtresi, ayrıntıda yönetici için "Değiştir" (siparişin tüm kalemleri, aylık bağış ve sertifika birlikte değişir). **Personel** (`flxb_view_own`) Bağışlar menüsünde yalnız kendi referanslarını görür ("Referanslarım": liste, ayrıntı, CSV, makbuz); bağışçılar, türler ve ayarlar kapalı. **İdareci** (`flxb_dashboard`) ayrıca Dashboard'daki genel toplamları görür. Yetkiler Kullanıcılar › Roller ve erişim'den değişir.
- **2.1.0:** Kart bilgileri doğrudan ödeme sayfasında, kendi alanlarımızda sorulur (ayrı iyzico sayfası yok). Kart numarası yazılınca taksit seçenekleri gelir, banka 3D Secure ekranı açılır, giriş yapmış bağışçı "Kartımı kaydet" diyebilir (kartı iyzico saklar). Ayrıntı: "Kart ödemesi". Tema 0.5.29 ile tekrar deneme sayfasında ödeme yöntemi kutuları düzenli.
- **2.0.9:** Ödeme sayfasındaki "Şartlar ve koşullar / Gizlilik politikası" metni zorunlu onay kutusu oldu (işaretlenmeden sipariş verilmez, sunucu da reddeder; onay zamanı siparişte `_flxb_terms_accepted`). Linkler gerçek sayfalara gider: WooCommerce şartlar sayfası yoksa "Bağış Koşulları" sayfası (düzenlenebilir taslak metin) oluşturulur; gizlilik sayfası yayında değilse `/kvkk/`, o da yoksa kısa bir "Gizlilik Politikası" sayfası kullanılır. Bağışlar listesinde bir sepet (tek WooCommerce siparişi) tek satırdır: toplam tutar ve "3 kalem"; Ayrıntılar'da "Bağış sepeti" kartı tüm kalemleri (fidan, sertifika, sertifikadaki ad) gösterir ve havale bekleyen siparişte tek "Ödemeyi onayla" düğmesi vardır. Yeni API: `flxb_pending_count()` (onay bekleyen, sipariş başına 1). CSV kalem bazlı kalır. Tema 0.5.25 ile: büyük, hizalı onay kutuları; kart ödeme sayfasında hizalı özet, geniş ekranda sağda sabit.
- **2.0.8:** e-kart tasarımlarındaki `gonderen` (Gönderen) alanı giriş yapmış bağışçının adıyla dolu gelir; boş bırakılırsa ödeme sayfasındaki ad soyad yazılır (formda zorunlu sorulmaz).
- **Kartla ödeme:** sipariş ödenince sertifika hazırlanır ve seçilen kanallardan gönderilir; teşekkür sayfasında sertifika kartı (önizleme, PDF indir, paylaş) çıkar.
- **Havale:** teşekkür sayfasında IBAN, tutar ve **size özel açıklama kodu** (ör. "Fidan bağışı YT84 Ayşe Yılmaz"), dekont yükleme alanı. Dekont **Hesabım › Bekleyen bağışlarım** (`/hesabim/bekleyen-bagislar/`) sayfasından ve e-postadaki bağlantıdan da yüklenir. Yönetici siparişte **Ödemeyi onayla** deyince sipariş tamamlanır, sertifika gider.
- **Kısa bildirim:** havale kaydedilince bağışçının telefonuna kısa WhatsApp/SMS (Flux Mail ya da Flux Certificates sağlayıcısı varsa; metinler Ayarlar › WooCommerce'te).

## Aylık bağış

- İlk ödeme **Flux Donate iyzico** ile alınır, kart iyzico'da saklanır (kart numarası siteye gelmez). Kart saklanmazsa bağış tek seferlik kalır ve siparişe not düşülür.
- Her ay Flux Donate'ın saatlik görevi (`flxb_iyzico_renewals`) bir **yenileme siparişi** açar (`created_via = flxb-renewal`) ve kayıtlı kartı çeker. Başarılıysa sipariş tamamlanır ve bağış kaydına "yenileme" olarak yazılır; başarısızsa 1 ve 3 gün sonra tekrar denenir, üçüncüsünde düzenli bağış **Gecikmiş** olur ve bağışçıya e-posta gider. Zaman aşımında aynı ay ikinci kez çekilmez.
- İptal: **Bağışlar › Düzenli bağışlar** ya da bağışçının e-postasındaki iptal bağlantısı.
- **WP-Cron gerçek cron'la çalışmalı** (Hostinger: `define( 'DISABLE_WP_CRON', true );` ve hPanel › Cron Jobs'ta 15 dakikada bir `wget -q -O - https://ALAN-ADI/wp-cron.php?doing_wp_cron >/dev/null 2>&1`).

## Kart ödemesi (2.1.0)

- **Varsayılan: kendi kart alanlarımız.** Ödeme sayfasında "Kredi / banka kartı" seçilince kart numarası, kart üzerindeki isim, son kullanma (AA/YY) ve CVC alanları açılır. Kart numarası 8 haneye ulaşınca iyzico'dan o kartın **taksit seçenekleri** gelir (tek çekim + varsa 2-12 taksit, vade farkı iyzico'dan; çekilecek tutar sunucuda iyzico'ya tekrar sorulur). "Sipariş ver" sonrası bankanın **3D Secure** ekranı açılır, onaylanınca teşekkür sayfasına dönülür. Banka reddederse sipariş "başarısız" olur ve aynı siparişin ödeme sayfası hata mesajıyla açılır; bağışçı tekrar dener.
- **Kartımı kaydet:** giriş yapmış bağışçıda kutucuk çıkar. Kart **iyzico kart saklama** altyapısında tutulur; sitede yalnız iyzico'nun bağışçı anahtarı (şifreli, `_flxb_iyz_cuk`) durur. Sonraki bağışta "Kayıtlı kartlarınız" listesinden seçilir, kart bilgisi tekrar sorulmaz. Aylık bağışta kart her zaman iyzico'da saklanır (bağışçıya not olarak yazılır).
- **Kart verisi saklanmaz:** kart numarası, son kullanma ve CVC tarayıcıdan yalnız sipariş anında sunucuya, oradan doğrudan iyzico'ya gider; veritabanına, siparişe ve günlüklere yazılmaz.
- **Ayarlar › WooCommerce › Kart ödeme ekranı:** "Ödeme sayfasında kendi kart alanlarımız" (varsayılan) · "iyzico ödeme formu (önceki gibi)" · "Tek seferlik bağışlarda resmi iyzico eklentisi". **En fazla taksit:** 1 (yalnız tek çekim) ile 12 arası, varsayılan 12.
- **Test:** iyzico ayarlarında sandbox açıkken kart alanlarının altında test kartı notu görünür (ör. 5528 7900 0000 0008, 12/30, 123; 3D şifresi iyzico'nun test ekranında). Canlıya geçince not kaybolur. iyzico kapalıyken yöntem görünmez.

## Sistem kontrolü Flux Speed'ta (2.9.0)
Flux Speed kuruluysa Sistem kontrolü ekranı Performans › Sistem Kontrolü'nde açılır; Ayarlar'daki "Sistem kontrolü" sekmesi gizlenir, eski adres oraya yönlenir. Test düğmelerinin sonucu orada bildirim olarak çıkar, iyzico bildirim adresleri Ayarlar › Genel'in altına geçer. Flux Speed yoksa eski sekme aynen çalışır.

## Mobil uygulamada bağış onayı (2.8.0)
Sipariş yönetme yetkisi olan personel uygulamada "Onay bekleyenler" ekranını görür: dekontu açar, Onayla ya da gerekçe yazıp Reddet. Paneldeki onay kartıyla aynı işi yapar (onayda sertifika gider, retde bağışçıya bildirim). Ayar yok.

## Mobil uygulama (2.7.0)
Flux App 0.2.0+ kuruluysa bağışçı mobil uygulamaya kodla girer ve "Bağışlarım" ile "Sertifikalarım" ekranlarını görür (bağışlar, durumları, sertifika PDF'leri). Ayar yok. Siparişi olup hesabı olmayana Flux App hesap açar, Flux Donate bağışçı kartını hesaba bağlar. Uç ayrıntıları API.md'de.

## Liste filtreleri ve sayfalama (2.6.0)
Bağışlar, Bağışçılar ve Düzenli bağışlar listeleri Flux Admin'in ortak liste kütüphanesini kullanır (Flux Admin 1.2.0+): üstte tek satır arama, yanında 2 temel filtre (Bağışlar: Durum + Bağış türü), kalanlar "Filtreler" düğmesinin altında. Seçim değişince liste kendiliğinden yenilenir. Altta sayfalama ve "Sayfa başı" (10/20/50/100, kullanıcıya göre hatırlanır). Çöp kutusu bağlantısı tablonun altında. Flux Admin yoksa ya da eski sürümse eski filtre görünümü kalır.

## Silme ve toplu işlemler (2.5.0)
WooCommerce'te bir sipariş çöpe atılınca bağışları da Bağışlar listesinden çöpe gider, geri alınınca döner, kalıcı silinince bağış kayıtları da silinir (HPOS ve eski sipariş ekranı). 2.5.0'dan önce silinmiş siparişlerin bağışları Bağışlar listesi açılınca kendiliğinden temizlenir (günde en fazla iki kez kontrol). Listede soldaki kutularla bağış seçilir; üstte çıkan çubuktan **Çöp kutusuna taşı** (bağış yöneticisi) ya da **Kalıcı olarak sil** (yalnız site yöneticisi) seçilir, onay sorulur. WooCommerce siparişi olan bağışta işlem siparişe de uygulanır. Çöpteki bağışlar arama satırındaki **Çöp kutusu (N)** ile görülür, oradan **Geri al** ya da kalıcı silinir.

## Kişi başına sertifika (2.4.1)
Bir bağışta adet birden fazla kişi adınaysa (ör. 2 fidan: Kader Subaşı, Ferhat Subaşı) bağış ve ödeme tek kalır, sertifika her isim için ayrı oluşur (her birinde kendi adı ve fidan sayısı). Aynı isim iki kez geçerse tek sertifikada 2 fidan yazar; isim verilmeyen adetler bağışçı adına tek sertifikada toplanır. Bağış ayrıntısında Sertifika satırında tüm sertifikalar listelenir. **Sertifikaları yeniden oluştur**: mevcutları iptal eder, her isme yenisini yapar, göndermez. **Sertifikaları gönder**: canlı sertifikaları bağışçıya gönderir.

## Ödeme onayı (2.4.0)
Havale bekleyen ya da dekontu olan bağışın ayrıntısında en üstte "Ödeme onayı bekliyor" kartı çıkar. Dekontlar (siteden ve WhatsApp'tan gelenler) **Önizleme** ile pencerede açılır (PDF, resim). **Onayla** ödemeyi tamamlar, sertifika hazırlanıp gönderilir. **Reddet** açıklama ister: bağış iptal edilir, açıklama bağışçıya gider (WhatsApp: Çağrı Merkezi kuruluysa sohbetten, değilse Flux Mail; e-posta varsa "Ödeme reddedildi" şablonu). Kara listedeki kişiye gönderilmez. Yetki: WooCommerce siparişi düzenleyebilen. Bağışçı kartında "Tüm bağışlar" artık ödenmiş bağışları ve bekleyenleri ayrı gösterir.

## Dekont defteri (2.3.2)
Sitede yüklenen havale dekontu, Flux Chat 1.15+ kuruluysa ortak dekont defterine yazılır (gönderen, tutar, IBAN, dosya özeti). Aynı dosya daha önce WhatsApp'tan ya da başka bir bağış için siteden geldiyse siparişe not düşülür ve bağış ayrıntısında dekontun altında kırmızı uyarı çıkar ("bu dekont daha önce yüklenmiş"). Aynı bağışa aynı dosyanın tekrar yüklenmesi uyarı sayılmaz. Çağrı Merkezi yoksa hiçbir şey değişmez.

## Mobil liste (2.3.1)

Bağışlar listesi telefonda kart görünümünde (bağışçı + tutar üstte, tür, durum, tarih); karta dokununca bağış açılır, sağ altta "Ayrıntılar". Bağış ayrıntısındaki bilgiler telefonda iki sütun.

## Personel (2.3.0, Flux Roles 1.1.0)

Yetkiler Kullanıcılar › Roller ve erişim'den açılır; Flux Donate yalnız yetkiye bakar, rol adına bakmaz.

- `flxb_view_own` — Bağışlar › Referanslarım: yalnız referansı kendisi olan bağışlar.
- `flxb_create_ref` — Bağışlar › Yeni talep: bağışçı adına bağış (tür, adet, adına isimler, bağışçı adı + telefon/e-posta). Bekleyen sipariş açılır, referans personeldir; ödeme linki kopyalanır, WhatsApp'tan gönderilir, e-posta varsa otomatik gider. Bağışçı kartla ya da havaleyle öder; ödenince sertifika her zamanki gibi gider.
- `flxb_donors_own` — Bağışlar › Bağışçılarım: yalnız kendi getirdiği bağışçılar, toplamlar yalnız kendi bağışlarından.
- `flxb_ref_notify` — bağış ödenince/iptal olunca haber (WhatsApp'ı Flux Chat gönderir).
- Yönetici "Bağış ekle"de alınmış bağışı kaydeder ve referans seçer (değişmedi).

## Ödeme sayfası ve liste (2.2.3)

- Bağışlar listesi canlı MySQL'de boş görünüyordu (sepet gruplama sorgusu hata veriyordu); düzeldi. Onay bekleyen sayısı da aynı sebeple 0 görünebiliyordu.
- Liste filtresi: üstte yalnız arama; tarih, durum, tür, form, referans "Detaylı filtre"de.
- Tekrar ödeme sayfası (order-pay): tek ve zorunlu Şartlar onayı, WooCommerce'in İngilizce gizlilik metni yok, buton "Ödeme yap" ve ekranın altında sabit (tema 0.5.31).
- Test modunda yalnız iyzico test kartları kabul edilir; "Test kartını doldur" düğmesi kartı doldurur. Kart başlatılamazsa siparişe not düşer (iyzico hatası, kartın ilk 6 ve son 4 hanesi).

## Havale hesabı (2.2.2)

IBAN tek yerden gelir: **WooCommerce > Ayarlar > Ödemeler > Doğrudan banka havalesi** içindeki ilk hesap (alıcı, IBAN, banka). Bağış sayfası, ödeme ve teşekkür sayfası, Çağrı Merkezi aynı hesabı gösterir.

- Flux Donate "Havale/EFT" yönteminde IBAN yazılırsa o öne geçer (boş bırakın).
- WooCommerce'te hesap yoksa Tema Ayarları > Banka hesabı yedek olarak kullanılır.
- Değişiklik görünmezse LiteSpeed Cache > Purge All.

## iyzico anahtarları ve bildirim adresi

- Anahtarlar resmî iyzico WooCommerce eklentisinin ayarlarından okunur; o eklenti yoksa **Bağışlar › Ayarlar › Ödemeler › iyzico** alanlarından. Anahtarlar hiçbir ekranda ve günlükte gösterilmez.
- iyzico panelinde **İşyeri Bildirimleri Url** = `https://ALAN-ADI/wp-json/flxb/v1/iyzico/notify`. Bu tek adres Flux Donate ödemelerini kendisi işler, diğerlerini resmî iyzico eklentisinin webhook'una aynı istek içinde iletir. Abonelik bildirim adresleri kapalı kalmalı.
- Ödeme ancak iyzico'dan tekrar sorgulanıp tutar, para birimi ve sepet siparişle aynı çıkınca "ödendi" sayılır.

## İade

- WooCommerce'te sipariş üzerinden yapılan tam veya kısmi iade bağış kaydına yansır ("İade edildi" / "Kısmen iade edildi").
- **Bağışlar › Bağış ayrıntısı**'ndaki iade düğmesi WooCommerce iadesi oluşturur; ödeme yöntemi destekliyorsa (iyzico) para karta geri gider, havalede yalnızca kaydedilir (parayı siz gönderirsiniz).

## Flux Forms ödeme formları

- **Ayarlar › WooCommerce › "Flux Forms ödeme formları WooCommerce ödeme sayfasında devam eder"** açıkken (varsayılan) formdaki "Öde" seçimi sepete koyar ve ödeme sayfasına götürür; bağışçı orada kart ya da havaleyi seçer.
- Bir türe bağlı formda satırlar türün seçenekleridir; bağlı olmayan formlar tutarı, "Bağış seçenekleri bloğunda göster" işaretli serbest tutarlı türe tek satır olarak yazar (filtre `flxb_wc_form_fallback_type`).
- Sipariş ödenince form kaydı tamamlanır ve Flux Forms 2.1.0'ın **"Ödeme tamamlandı"** otomasyonları çalışır (sertifika kartı WooCommerce teşekkür sayfasında görünür). Formun kendi sertifika akışı varsa Flux Donate ayrıca sertifika üretmez.

## Ürün listesi (XML)

`https://ALAN-ADI/bagis-urunleri.xml` (kalıcı bağlantı kapalıysa `/?flxb_feed=google`). iyzico "ürünlerinizi XML ile yükleyin" ve Google Merchant Center için. "Bağış seçenekleri bloğunda göster" işaretli ya da sayfa adresi girilmiş türlerin satıştaki sabit fiyatlı seçenekleri listelenir; − / + adetli seçenek 1 adet fiyatıyla listelenir, serbest tutarlar listelenmez. Ürün kimliği `flxb-<tür-slug>-<seçenek>`, bağlantı `…/bagis/?sekme=<tür-slug>&flxb_opt=<seçenek>`.

## E-postalar (Flux Mail)

Flux Mail etkinse Flux Donate'ın e-postaları **İletişim › E-postalar**'da "Flux Donate" grubunda düzenlenir: **Düzenli bağış başladı**, **Düzenli bağış tahsil edilemedi**, **Havale dekontu yüklendi** (yöneticiye). Flux Mail yoksa aynı e-postalar varsayılan metinleriyle `wp_mail` üzerinden gider. WooCommerce'in sipariş e-postaları (havale bilgileri ve dekont bağlantısı "beklemede" e-postasına eklenir) WooCommerce › Ayarlar › E-postalar'da kalır. Sertifika e-postalarını Flux Certificates gönderir.

## Flux Chat

Çağrı merkezinin `FLXB_Donations::record` ile yazdığı her kayıt için Flux Donate bir WooCommerce siparişi açar (bekleyen kayıtlar Havale ile "Ödeme bekleniyor"); kayıt tekrar yazılmaz, dekontlar ve `beneficiary_contacts` korunur, kaydın durumu değişince sipariş de değişir. Yeni kod için `flxb_create_order()` önerilir (API.md).

## Menü simgesi

Yönetim menüsünde ortak FluxWP logosu (`assets/icon.svg`) kullanılır; değiştirmek için `flxb_menu_icon_url` filtresi.

## WooCommerce sürümleri

Yalnızca kararlı, herkese açık WooCommerce API'leri kullanılır (HPOS uyumlu sipariş API'si; HPOS ve sepet/ödeme blokları uyumluluğu bildirilir). Büyük WooCommerce güncellemelerinden sonra denenmesi gereken yerler API.md'deki "Sürüme duyarlı yerler" bölümünde.

## Sistem kontrolü

**Bağışlar › Ayarlar › Sistem kontrolü** sekmesi kurulumun yerinde olup olmadığını tek ekranda gösterir. Her satırda durum (simge + yazı: Tamam / Uyarı / Hata / Bilgi), ne kontrol edildiği, ayrıntı, kaynağı (Flux Donate, Flux Forms, Flux Certificates…) ve varsa düzeltme bağlantısı vardır. **Yeniden kontrol et** sayfayı baştan hesaplar.

- **Zamanlanmış görevler:** DISABLE_WP_CRON durumu; Flux Donate'ın saatlik görevinin son çalışma zamanı (2 saatten eskiyse uyarı); 15 dakikadan fazla geciken görevler; **Cron'u test et** (sunucunun kendi wp-cron.php adresine bir istek atar).
- **Ödemeler:** etkin sağlayıcı ve anahtarları; iyzico anahtarlarının kaynağı (iyzico WooCommerce eklentisi / Flux Donate), sandbox mı canlı mı; **Bağlantıyı test et** (iyzico'ya imzalı, zararsız bir BIN sorgusu atar; anahtar yanlışsa satır Hata olur); callback ve bildirim adresleri; 3 kez reddedilip duran düzenli bağışlar; Stripe anahtarları ve webhook imza anahtarı.
- **Eklentiler:** WooCommerce (yoksa ya da 8.0'dan eskiyse hata), eski Flux WooCommerce Bağış hâlâ açıksa hata, Flux Forms, Flux Certificates, iyzico eklentileri.
- **WooCommerce (2.0):** sürüm, kart / aylık / havale yöntemleri, çift havale (BACS), para birimi uyumu, türlerin gizli ürünleri, bağış sayfası, sertifika şablonu, ürün XML'i, Flux Forms 2.1.0, Flux Mail.
- **Ayarlar:** fiyatlı açık bağış türü, her açık türün formu, para birimi, havale banka hesapları, kurum adı, kalıcı bağlantılar, veritabanı sürümü.
- **E-posta:** **Test e-postası gönder** yönetici adresine bir ileti yollar.
- **Güvenlik:** HTTPS ve WordPress güvenlik anahtarları (kayıtlı iyzico kartları bunlarla şifrelenir; değiştirmeyin).

Test düğmeleri dışında hiçbir kontrol dışarıya istek atmaz. Hata varken Başlangıç (Dashboard) ekranında kırmızı bir uyarı ve üst çubukta "Sistem: N hata" görünür; "Sonraki güncellemeye kadar gizle" ile gizlenir (yeni bir hata çıkarsa ya da eklenti güncellenirse tekrar görünür).

Flux Donate kapalıyken aynı ekran **Formlar › Sistem kontrolü**'nde açılır (Flux Forms'un kontrolleriyle).

## Bağış türleri

1. **Bağışlar › Bağış türleri › Yeni bağış türü** ile bir tür oluşturun. Kategori, seçenekler ve fiyatlar, serbest tutar, adet ve düzenli bağış ayarlarını yapın.
2. **Bağlı formlar** kısmında Flux Forms'daki ödeme formunu seçip kaydedin. Ardından formdaki her fiyatlı seçimi bir seçenekle eşleştirin. Eşleşen seçimlerde tahsil edilen tutar sunucuda türün fiyatından hesaplanır.
3. Hazır başlangıç için **Örnek türleri yükle** (fidan seti veya kurban/ramazan/genel seti) kullanılabilir. Örnek fiyatı 0 olan türler, fiyatları girilene kadar kapalı başlar. Hiçbir şey kendiliğinden eklenmez.
4. Türü sayfada göstermek için kısa kodu kullanın: `[flx_bagis_kampanya slug="tur-kisa-adi"]`.
5. Bir form aynı tarihlerde yalnızca bir açık türe bağlanabilir; çakışan bağlantı kaydedilmez ve uyarı gösterilir. Formun para birimi türünkiyle aynı olmalıdır, değilse ödemeler durdurulur.

## Fiyatlar ve Flux Forms

- Fiyatların tek kaynağı bağış türüdür. Türde bir fiyat değiştiğinde bağlı formdaki **eşleştirilmiş** seçimlerin fiyatı ve etiketindeki tutar ("1 fidan — 200 ₺") güncellenir; formun sertifika akışındaki birim fiyat da eşitlenir.
- Flux Forms'un fidan birim fiyatı (`flxf_sapling_unit_price`) ve sertifikadaki "tutar ÷ birim fiyat" hesabı (`flxf_automation_unit_price`) Flux Donate etkinken türden gelir; bağışçının ödediği birim fiyat ödeme kaydında saklanır.
- Flux Donate kapalıyken Flux Forms 150 ₺ varsayılanına döner; formda en son eşitlenen fiyatlar kalır.
- Fiyatı 0 olan ya da pasif seçenek bağlı formda gizlenir ve seçilemez; tekrar aktif edilip fiyat girildiğinde kendiliğinden görünür.
- Birim seçilmeyen seçenekte birim boş kalır (API'de `unit: null`, `unit_set: false`). `flxb_units` listesinde olmayan bir birim ("koli" gibi) kendi adıyla gösterilir.

## Koyu mod

Form ve kampanya kutusu yalnızca cihaz koyu moddayken koyulaşır. Varsayılan (`class`): tema `body.flx-dark` sınıfıyla izin veriyorsa. Sınıfı olmayan temalarda `add_filter( 'flxb_front_dark_mode', fn() => 'auto' );` ve `add_filter( 'flxf_front_dark_mode', fn() => 'auto' );` ile cihaz ayarı izlenir (`--flxb-dark-*` / `--flxf-dark-*` değişkenleriyle renkler değiştirilebilir); `off` her zaman açık tema demektir.

## Elle bağış ekleme

**Bağışlar › Bağış ekle** ile havale/EFT, nakit veya başka yolla gelen bir bağış kaydedilir: tür, seçenek, adet, (gerekirse gerekçeli) farklı birim fiyat, bağışçı ve izinleri, yöntem, hesap, dekont numarası, tarih ve not. Banka hesapları listesi `flxb_bank_accounts` filtresiyle verilir. Kaydı yapan kullanıcı bağışta saklanır.

## Kurban modülü

**Bağışlar › Ayarlar › Genel › Kurban modülü** açıldığında:

- **Ayarlar › Kurban** sekmesinde vekâlet metni girilir. Her değişiklik yeni sürüm olarak saklanır; her bağış kabul ettiği sürümü tutar.
- Tür düzenleme ekranında "Vekâlet gerekli", niyet, hayvan grubu ve "etin tamamı ihtiyaç sahiplerine" ayarları görünür.
- Form eşleştirmesinde "Adına (isim alanı)" ve "Vekâlet onay alanı" seçilir. Vekâlet gerektiren türde onay kutusu işaretlenmeden ödeme yapılamaz. Form ödemeyi WooCommerce'e devrettiğinde adlar ve onay siparişe taşınır (2.0.1).
- **Bağış seçenekleri** bloğu da vekâlet isteyen türü alır (2.0.1): "Adlar, her hisse için bir" alanı ve vekâlet metni (sürümüyle) ile onay kutusu çıkar. Onay olmadan ya da hisseden fazla adla sepete eklenmez; adı yazılmayan hisseler ödeme sayfasındaki ad adına verilir.
- Her hisse, adına kesilecek kişinin adıyla ayrı bir kalem olur; isimler bağış ayrıntısında düzeltilebilir (değişiklikler kayda geçer).

## Tema için okuma API'si

`function_exists( 'flxb_api_version' )` ile kontrol edin. 1.x boyunca imzalar ve dönen alanlar yalnızca genişler.

- `flxb_get_types( array( 'status' => 'visible'|'open'|'all', 'category' => '', 'slugs' => array(), 'ids' => array(), 'progress' => true ) )`
- `flxb_get_type( $id_or_slug )` → dizi veya `null`
- `flxb_type_state( $type_or_id_or_slug )` → `open` | `upcoming` | `ended` | `closed` | `archived`
- `flxb_form_element_for_option( $type_id, $form_id, $option_key, $qty )`
- REST: `GET /wp-json/flxb/v1/types` (`status`, `category`) ve `GET /wp-json/flxb/v1/types/<id|slug>`
- Ön seçim: sayfa adresine `?flxb_opt=<seçenek>&flxb_qty=<adet>` ekleyin ya da `FLXB.preselect( { option, qty, beneficiaries, lock } )` çağırın (sayfa yenilenmez; `flxb:ready`, `flxb:change` ve `flxb:unlock` olayları yayılır). `lock: true` ya da türdeki "Ön seçim geldiyse formdaki seçimi gizle" ayarı seçim listesini gizleyip "Seçiminiz: … · Değiştir" özetini gösterir; `FLXB.unlock()` listeyi geri açar.

## Kurum ve marka

**Bağışlar › Ayarlar › Kurum** sayfasında kurum adı, logo, marka rengi, makbuz metni ve alt bilgiyi girin. Boş bırakılan alanlarda site adı, tema logosu ve tema renkleri kullanılır.

## Yetki

Sayfalar varsayılan olarak `manage_options` yetkisi ister. Bu yetki `flxb_manage_capability` filtresiyle değiştirilebilir.

## Sürüm notları

- **2.0.1:** Kurban istekleri. Form → WooCommerce devrinde kurban sahibi adları hisse başına ayrı kalem olarak yazılır (birleşmez), vekâlet onayı (sürüm, zaman) sepet satırına, sipariş kalemine ve bağış kaydına taşınır. Bağış seçenekleri bloğu vekâlet isteyen türde adları ve onayı alır, sunucu onaysız isteği reddeder. Sertifikası olmayan türde sertifika soruları ve teslim kartları gizlenir; havale teşekkür sayfasında "Sertifikanızı alın" adımı yerine "Bağışınızı onaylıyoruz". Türün sayfa adresi göreli yol + sayfa kimliği olarak saklanır, site taşınınca bozulmaz. Havale yöntemi IBAN'ı boşsa `flxb_bank_accounts` hesabını kullanır. Geri dönüşte "Yakında" basılı sertifika seçeneği yanlışlıkla seçilebilir hale gelmiyor.

- **2.0.0:** WooCommerce'e geçiş (yukarıdaki her şey). Flux WooCommerce Bağış'tan veri taşınmaz (site canlı değil); eski `flxwb_*` adlarının çoğu `flxb_*` oldu (havale yöntemi `flxb_havale`, dekont REST `flxb-wc/v1/receipt`, dekont bağlantısı `?flxb_dekont=`). Aynı kalanlar: `/bagis-urunleri.xml`, blok adı `flx-wc-bagis/secenekler`, `[flx_bagis_secenekleri]`, `bekleyen-bagislar` uç noktası.
- **1.2.4:** iyzico tek bildirim adresi (`flxb/v1/iyzico/notify`), WooCommerce bildirimlerinin iletilmesi.
- **1.2.3:** SEO & MCP sistem düzeltmeleri. **1.2.2:** Sistem kontrolü filtreleri, 24 saat biçimi. **1.2.1:** Sistem kontrolü. **1.2:** iyzico. **1.1:** kalemler ve kurban modülü.

## Kurban hisse takibi (2.10.0)
Yalnız Ayarlar › Genel'de **Kurban modülü** açıksa görünür; ana sitede kapalı kalır, hiçbir şey değişmez.
1. Ayarlar › Kurban: vekâlet metni ve **Kesim yerleri** (her satıra bir tane). Qurban kuruluysa kesim yerleri oradan gelir.
2. Bağışlar › **Kurban hisseleri**: her hisse 7 haneli kodla; durum sekmeleri, ödeme ve kesim yeri süzgeci. Seçip toplu **Onayla**, **Kesim yerine vekâlet ver**, **Vekâleti geri al**, **Kesildi**, **Sahibine iletildi**.
3. Hisseyi açınca bütün akış: adımlar, bağış/ödeme/dekont, vekâleti başka yere devretme, kesim videosu yükleme, **Sahibine gönder** (WhatsApp, Flux Chat) ve geçmiş.
4. Personel: Flux Roles'de `flxb_kurban` yetkisi verilen kişi yalnız bu ekranı görür.

## Takip ve iş ortakları (2.11.0)
Ana sitede kapalı kalır. Açmak için Ayarlar › Genel › **Takip modülü**.
1. Bağış türleri › tür › **Takip**: "Her birimi ayrı takip et", "İlk dosya tamamlar" ve **Bu türün sözcükleri** (ör. fidan için "Dikim alanı / Dikildi", kurban için "Kesim yeri / Kesildi"). Boş kalan sözcük site varsayılanını kullanır.
2. Bağışlar › **İş ortakları**: işi yapan (hizmet) ya da bağış getiren (kaynak) firma/kişi. Her ortağa tür (ve seçenek) başına tarihli **anlaşma** fiyatı, yapılan/alınan **ödemeler**.
3. Bağışlar › **Takip**: kayıtlar tür, ortak ve "Şimdi yapılacaklar" süzgeciyle; toplu atama, tamamlandı, sahibine iletildi. Kayıtta hedef tarih, maliyet, dosyalar (video/resim/PDF) ve WhatsApp ile gönderim.
4. İş ortakları › **Mutabakat**: ay ya da yıl seç; ortak başına kayıt, tutar, ödeme ve bakiye; CSV indir.
5. Ayarlar › **Takip**: erken uyarı gün sayısı ve site geneli sözcükler.

## Raporlar ve ek sekmeler (2.11.0)
1. Ayarlar › Genel › **Raporlar** (takip modülü açıksa zaten açık). Bağışlar › **Raporlar**: hazır raporlar (türe, aya, ödeme yöntemine, referansa göre; takipte iş ortağına, getirene, duruma göre). Dönem seç, **CSV** indir.
2. **JSON'u düzenle** ile raporu değiştir, **Yeni rapor** ile ekle. Sağdaki liste kullanılabilecek alanları gösterir. Hazır raporu bozarsan **Aslına döndür**.
3. Ayarlar › **Ek sekmeler**: kayıt ya da bağış sayfasına kendi alanlarını ekle (örnek kutuda). Boş bırakılırsa hiçbir şey görünmez.

## Excel'den yükle (2.11.0)
Takip modülü açıkken Bağışlar › **Excel'den yükle**.
1. Dosyayı seç (ilk satır sütun adları: Tarih, Bağışçı, Telefon, Kimin adına, Bağış türü, Adet, Tutar, Ödeme, Durum, Kesim yeri / Dikim alanı, Kurum, Hedef tarih, Referans, Not).
2. Önizlemede sütunları ve eşlemeleri kontrol et; tanınmayan tür/ortak için seç ya da "Yeni olarak ekle". **Uygula ve yeniden kontrol et**.
3. **N satırı yükle**: sayfa açık kalsın; kapanırsa yeniden açınca devam eder. Aynı dosya yeniden yüklenirse satırlar "Zaten yüklü" olur.
