SLUG='flx-donate'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-donate'; WP='6.4'; PHP='7.4'; DOCS='bagis-eklentisi'; API=True
C={
'en':dict(name='Flux Donation',
 tagline='Sapling and campaign donations on WooCommerce, with a certificate for every gift.',
 desc='Flux Donation turns WooCommerce into a donation system: donation types and campaigns with fixed packages, quantities or free amounts, a short donation form, card, bank transfer and monthly giving, donor cards and a dashboard. When a gift is paid, Flux Certificate prepares the certificate and sends it on WhatsApp and e-mail.',
 badges=['![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-7f54b3)','![Payments](https://img.shields.io/badge/pay-iyzico%20%C2%B7%20bank%20transfer%20%C2%B7%20monthly-2fbf8f)'],
 gifalt='Flux Donation tour: the donation dashboard, the donation list and one donation in detail, donation types and campaigns, and the donation form on the site going on to the checkout',
 why='''A shop checkout is built for parcels: shipping address, company, postcode, "Place order". A donor wants to pick "5 saplings", write a name for the certificate and pay. Flux Donation keeps WooCommerce's payments and orders but makes the whole path about giving:

- 🌱 **Donation types, not products.** Packages (1, 5, 10 saplings), − / + quantities, free amounts with presets, campaigns with a target and dates. Prices always come from the type, never from the browser.
- 🧾 **A short checkout.** One contact block (phone or e-mail), no address, one terms checkbox and a fixed "Pay" bar at the bottom.
- 🏦 **Card, bank transfer and monthly.** Card fields on the page with instalments (iyzico), transfer with a personal reference code and receipt upload, monthly giving with a stored card.
- 🎓 **Certificates per person.** A gift on behalf of several people produces a certificate for each name, sent by WhatsApp and e-mail.
- 📊 **One place for the numbers:** totals, donors, recurring income, gifts per type and campaign, top donors.''',
 features='''### Donating
- **Donation options** block / `[flx_bagis_secenekleri]`: types as tabs, package cards with badges (Popular, Best value, Family), − / + quantity, free amount with preset buttons, "Repeat monthly".
- "For myself" or "on behalf of someone else" (the name goes on the certificate), WhatsApp and e-mail for the certificate, "send me a copy too", donor-selectable certificate designs from Flux Certificate.
- Classic and block checkout with a single contact section, required terms consent and a fixed total bar.
- **Bank transfer:** IBAN from one place, a personal reference code, receipt upload on the thank-you page or under **My account › Pending donations**; one click **Approve payment** sends the certificate.
- **Card (iyzico):** card fields on the checkout, instalments, 3D Secure, "Save my card". **Monthly giving** renews with the stored card and retries after 1 and 3 days.

### Managing
- **Dashboard:** total donations, donors, new donors, average gift, monthly recurring income, active recurring donations, daily chart, one-time vs recurring, per type and per form, top donors, CSV.
- **Donations:** filters, one row per basket, details with items and names, printable receipt, refund through WooCommerce, approval card for transfers with receipt preview and duplicate-receipt warning.
- **Donors:** one card per person (matched by phone or e-mail, never by name) with history, recurring gifts, certificates and consents.
- **Donation types and campaigns:** categories, options and prices, free amounts, quantities, recurring, dates, target with progress, open/closed/archived; sample sets to start from. Each type has a hidden WooCommerce product.
- **Add donation** for cash or transfers received elsewhere; **referrals** per donation with Flux Roles, staff see only their own.
- Product feed `bagis-urunleri.xml` for iyzico and Google Merchant Center; optional Qurbani module.''',
 shots=[('dashboard','Donation dashboard'),('form','Donation form on the site'),('donations','Donations'),('donation','Donation details'),('types','Donation types and campaigns'),('donor','Donor card'),('recurring','Recurring donations'),('checkout','Short checkout'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-form'],'Mobile: dashboard and donation form')],
 req='WooCommerce 8.0+. Optional: Flux Certificate 1.2+ (certificates), Flux Contact (e-mails, WhatsApp), Flux Forms 2.1+ (payment forms), the iyzico WooCommerce plugin.',
 first='''1. Install and activate WooCommerce (currency TRY), then Flux Donation.
2. **Donations › Donation types**: create a type (or **Load sample types**) and tick *Show in the Donation options block*.
3. **Donations › Settings › WooCommerce › Create the online donation page**, then enable *Bank transfer (donation)* and *Card (iyzico)* under **WooCommerce › Settings › Payments**.
4. Open **Settings › System check** (or **Performance › System Check** with Flux Performance) and fix any red rows.''',
 note='> ⚠️ Monthly giving needs WP-Cron to run from a real cron job. Flux WooCommerce Donation (`flx-wc-bagis`) is retired: everything it did is part of Flux Donation, so do not run both.',
 dev='''- Reading API for themes: `flxb_get_types()`, `flxb_get_type()`, `flxb_type_state()`, REST `/wp-json/flxb/v1/types`, pre-selection `?flxb_opt=…&flxb_qty=…`.
- Recording from other packages: `flxb_create_order()` (preferred) or `FLXB_Donations::record()`; `flxb_pending_count()` for approval badges.
- App endpoints `flxb/v1/me*` and `flxb/v1/app/*` for Flux App; capabilities `flxb_view_own`, `flxb_create_ref`, `flxb_donors_own`, `flxb_dashboard` for Flux Roles.
- Shortcodes: `[flx_bagis_secenekleri]`, `[flx_bagis_kampanya slug="fidan"]`.''',
 roadmap=[(1,'Donation types, campaigns and the donor dashboard'),(1,'WooCommerce checkout with card, transfer and monthly giving'),(1,'Certificates per person and donor-selectable designs'),(1,'Referrals and staff views with Flux Roles'),(1,'Approvals in the mobile app'),(0,'Printed certificates by post'),(0,'Donor self-service for changing a monthly amount')]),
'tr':dict(name='Flux Donate',
 tagline='WooCommerce üzerinde fidan ve kampanya bağışları; her bağışa sertifika.',
 desc="Flux Donate WooCommerce'i bir bağış sistemine çevirir: sabit paketli, adetli ya da serbest tutarlı bağış türleri ve kampanyalar, kısa bir bağış formu, kart, havale ve aylık bağış, bağışçı kartları ve Dashboard. Bağış ödenince Flux Certificates sertifikayı hazırlar, WhatsApp'tan ve e-postayla gönderir.",
 badges=['![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-7f54b3)','![Ödeme](https://img.shields.io/badge/ödeme-iyzico%20%C2%B7%20havale%20%C2%B7%20aylık-2fbf8f)'],
 gifalt='Flux Donate turu: bağış paneli, bağış listesi ve bir bağışın ayrıntısı, bağış türleri ve kampanyalar, sitedeki bağış formundan ödeme sayfasına geçiş',
 why='''Mağaza ödeme sayfası kargo için tasarlanmıştır: teslimat adresi, şirket, posta kodu, "Sipariş ver". Bağışçı ise "5 fidan" seçmek, sertifikaya bir isim yazmak ve ödemek ister. Flux Donate WooCommerce'in ödeme ve sipariş altyapısını korur, ama tüm yolu bağışa göre kurar:

- 🌱 **Ürün değil, bağış türü.** Paketler (1, 5, 10 fidan), − / + adet, hazır tutarlı serbest bağış, hedefli ve tarihli kampanyalar. Fiyat her zaman türden gelir, tarayıcıdan gelen tutara bakılmaz.
- 🧾 **Kısa ödeme sayfası.** Tek iletişim alanı (telefon ya da e-posta), adres yok, tek Şartlar onayı ve altta sabit "Ödeme yap" çubuğu.
- 🏦 **Kart, havale ve aylık bağış.** Sayfada taksitli kart alanları (iyzico), kişiye özel açıklama kodlu ve dekont yüklemeli havale, kayıtlı kartla aylık bağış.
- 🎓 **Kişi başına sertifika.** Birkaç kişi adına yapılan bağışta her isme ayrı sertifika; WhatsApp ve e-postayla gider.
- 📊 **Rakamlar tek yerde:** toplamlar, bağışçılar, düzenli gelir, tür ve kampanyaya göre bağışlar, en çok bağış yapanlar.''',
 features='''### Bağış
- **Bağış seçenekleri** bloğu / `[flx_bagis_secenekleri]`: sekme olarak türler, rozetli paket kartları (En popüler, Avantajlı, Aile paketi), − / + adet, hazır tutar düğmeli serbest tutar, "Her ay tekrarla".
- "Kendim için" ya da "Başkası adına" (isim sertifikaya yazılır), sertifika için WhatsApp ve e-posta, "Bana da gönder", Flux Certificates'dan bağışçının seçebileceği tasarımlar.
- Tek iletişim bölümlü, zorunlu Şartlar onaylı ve sabit toplam çubuklu klasik ve blok ödeme sayfası.
- **Havale:** IBAN tek yerden, kişiye özel açıklama kodu, teşekkür sayfasında ya da **Hesabım › Bekleyen bağışlarım**'da dekont yükleme; tek tıkla **Ödemeyi onayla** sertifikayı gönderir.
- **Kart (iyzico):** ödeme sayfasında kart alanları, taksit, 3D Secure, "Kartımı kaydet". **Aylık bağış** kayıtlı kartla yenilenir, olmazsa 1 ve 3 gün sonra tekrar dener.

### Yönetim
- **Dashboard:** toplam bağış, bağışçı, yeni bağışçı, ortalama bağış, aylık düzenli gelir, aktif düzenli bağış, günlük grafik, tek seferlik/düzenli, türe ve forma göre, en çok bağış yapanlar, CSV.
- **Bağışlar:** süzgeçler, sepet başına tek satır, kalem ve isimli ayrıntı, yazdırılabilir makbuz, WooCommerce üzerinden iade, dekont önizlemeli ve tekrar eden dekont uyarılı havale onay kartı.
- **Bağışçılar:** kişi başına tek kart (telefon ya da e-postayla eşleşir, adla asla); geçmiş, düzenli bağışlar, sertifikalar ve izinler.
- **Bağış türleri ve kampanyalar:** kategori, seçenek ve fiyat, serbest tutar, adet, düzenli bağış, tarihler, ilerlemeli hedef, açık/kapalı/arşiv; başlamak için örnek setler. Her türün gizli bir WooCommerce ürünü vardır.
- Başka yolla gelen bağışlar için **Bağış ekle**; Flux Roles ile bağış başına **referans**, personel yalnız kendisininkileri görür.
- iyzico ve Google Merchant Center için `bagis-urunleri.xml` ürün listesi; isteğe bağlı Kurban modülü.''',
 shots=[('dashboard','Bağış paneli'),('form','Sitedeki bağış formu'),('donations','Bağışlar'),('donation','Bağış ayrıntısı'),('types','Bağış türleri ve kampanyalar'),('donor','Bağışçı kartı'),('recurring','Düzenli bağışlar'),('checkout','Kısa ödeme sayfası'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-form'],'Mobil: panel ve bağış formu')],
 req="WooCommerce 8.0+. İsteğe bağlı: Flux Certificates 1.2+ (sertifika), Flux Mail (e-posta, WhatsApp), Flux Forms 2.1+ (ödeme formları), iyzico WooCommerce eklentisi.",
 first='''1. WooCommerce'i kurup etkinleştirin (para birimi TRY), ardından Flux Donate'ı.
2. **Bağışlar › Bağış türleri**: bir tür oluşturun (ya da **Örnek türleri yükle**) ve *Bağış seçenekleri bloğunda göster*'i işaretleyin.
3. **Bağışlar › Ayarlar › WooCommerce › Online bağış sayfasını oluştur**; sonra **WooCommerce › Ayarlar › Ödemeler**'de *Havale (bağış)* ve *Kart (iyzico)* yöntemlerini açın.
4. **Ayarlar › Sistem kontrolü**'nü (Flux Speed varsa **Performans › Sistem Kontrolü**) açıp kırmızı satırları düzeltin.''',
 note="> ⚠️ Aylık bağış için WP-Cron gerçek bir cron'la çalışmalı. Flux WooCommerce Bağış (`flx-wc-bagis`) emekli: yaptığı her şey Flux Donate'ın içinde, ikisini birlikte açmayın.",
 dev='''- Tema için okuma API'si: `flxb_get_types()`, `flxb_get_type()`, `flxb_type_state()`, REST `/wp-json/flxb/v1/types`, ön seçim `?flxb_opt=…&flxb_qty=…`.
- Diğer paketlerden kayıt: `flxb_create_order()` (önerilen) ya da `FLXB_Donations::record()`; onay rozetleri için `flxb_pending_count()`.
- Flux App için `flxb/v1/me*` ve `flxb/v1/app/*` uçları; Flux Roles için `flxb_view_own`, `flxb_create_ref`, `flxb_donors_own`, `flxb_dashboard` yetkileri.
- Kısa kodlar: `[flx_bagis_secenekleri]`, `[flx_bagis_kampanya slug="fidan"]`.''',
 roadmap=[(1,'Bağış türleri, kampanyalar ve bağış paneli'),(1,'Kart, havale ve aylık bağışlı WooCommerce ödeme sayfası'),(1,'Kişi başına sertifika ve bağışçının seçtiği tasarımlar'),(1,'Flux Roles ile referans ve personel görünümleri'),(1,'Mobil uygulamada onay'),(0,'Postayla basılı sertifika'),(0,'Bağışçının aylık tutarını kendisinin değiştirmesi')]),
}
