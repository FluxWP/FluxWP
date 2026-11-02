SLUG='flx-certificates'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-certificates'; WP='6.4'; PHP='7.4'; DOCS='sertifika-sistemi'; API=False
C={
'en':dict(name='Flux Certificate',
 tagline='A personal certificate, a share link and a PDF for every sapling gifted.',
 desc='Flux Certificate creates sapling donation certificates, e-cards and participation documents from your own designs, gives each one a verified page with a PDF and a share image, and sends it by WhatsApp, SMS or e-mail. Events, referral staff and a live dashboard show where every sapling came from.',
 badges=['![PDF](https://img.shields.io/badge/PDF-server%20%2B%20browser-2fbf8f)','![Channels](https://img.shields.io/badge/send-WhatsApp%20%C2%B7%20SMS%20%C2%B7%20e--mail-333)'],
 gifalt='Flux Certificate tour: the dashboard, the certificate list, typing a name on New certificate with a live PDF preview, the template gallery and the public certificate page',
 why='''A thank-you certificate is often the only thing a donor keeps, and the one thing they share. Making them by hand in Canva and sending them one by one doesn't scale to a planting event with hundreds of people. Flux Certificate turns it into a list:

- 📝 **Type names, get certificates.** One row per person (or paste a list); each gets their own certificate, link and PDF, with a live preview while you type.
- 🔗 **A page people want to share.** `/sertifika/{code}` shows the certificate with a "verified" badge, PDF download and WhatsApp, LinkedIn, Facebook and X buttons; the link preview shows the person's own certificate.
- 🎨 **Your designs.** Upload a PDF from Canva and place the fields on it; 3 certificate templates and 16 Örnek Vakfı e-cards come ready.
- 🌳 **Events and targets.** Collect saplings per event (for example 11 November planting) with a target bar on the site.
- 📊 **Know who brought what:** referral staff, templates, busiest days and delivery success on one dashboard.''',
 features='''### Creating certificates
- **New certificate:** pick a template, add people row by row or **Paste from list** (`Name; phone; e-mail; saplings`), set shared fields once, split a sapling total across people. Live PDF preview on the right.
- People are recognised by phone (or e-mail) and never saved twice; phone numbers are accepted in any format and stored in one.
- Result screen: copy links, send with WhatsApp from your own phone, or download everything as **one PDF**.
- Back-dated certificates for reconciliation (admins only), referral staff per list.

### Delivery
- WhatsApp, SMS and e-mail go through **Flux Contact**; messages are queued and retried after 1, 5, 15 and 30 minutes.
- **Send to someone else** from the result screen or the list (several recipients, address book suggestions).
- Bulk send with a preview of how many people it reaches; message texts with `{name}`, `{link}`, `{saplings}` placeholders.

### Certificate page
- Verified page with the certificate image, details, PDF download and share buttons; 1200×630 share image per certificate.
- PDF, image and share image are produced on the server (TCPDF + FPDI, PHP GD is enough) and, as a fallback, in the browser.
- `[flx_sertifika_sorgula]` lookup form: visitors receive their link on their own registered phone or e-mail.

### Templates
- PDF background (e.g. exported from Canva) with text, multi-line text, date, number, auto number, QR and fixed text fields you drag into place.
- Donor-selectable designs for the donation page (used by Flux Donation); JSON export/import between sites; extra fonts.

### Events and reports
- Events with date, target, extra (certificate-less) saplings, location and a page on the site with a live counter; close and archive when done.
- Dashboard: total saplings gifted, period figures compared with the previous period, daily/weekday charts, deliveries, referral staff and templates, top donors, CSV export. Excel export of the certificate list.
- Staff role that lands straight on **New certificate**; with Flux Roles, staff see only their own records.''',
 shots=[('public','Public certificate page'),('new','New certificate with live preview'),('dashboard','Dashboard'),('certificates','All certificates'),('templates','Templates and e-cards'),('events','Events'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-public'],'Mobile: dashboard and certificate page')],
 req='Pretty permalinks. For WhatsApp/SMS/e-mail delivery: Flux Contact.',
 first='''1. Save **Settings › Permalinks** once so `/sertifika/{code}` addresses work.
2. **Certificates › Templates**: the sapling donation certificate is ready; activate the e-cards you want donors to choose from.
3. **Certificates › New certificate**: pick a template, add a person and press **Create**.''',
 note='> ℹ️ The certificate page, the event sections on the site and the lookup form switch to English with the site language; most admin screens stay in Turkish. Uninstalling keeps the data unless you tick the box under **Settings › Data**.',
 dev='''- Create certificates from other packages: `flxs_create_certificate( $template_id, $person_id, $values, $ref, $date )`, people with `flxs_upsert_person()`.
- Donor-selectable designs: `flxs_donor_templates()` (filter `flxs_donor_templates`).
- Shortcodes: `[flxs_etkinlikler]`, `[flxs_etkinlik id="3"]`, `[flxs_fidan_sayaci]`, `[flx_sertifika_sorgula]`.
- Capabilities `flxs_manage`, `flxs_dashboard`, `flxs_own_ref` are registered with Flux Roles; checks are registered with `flx_system_checks`.''',
 roadmap=[(1,'List-based certificates with live PDF preview'),(1,'WhatsApp, SMS and e-mail delivery with a queue'),(1,'Events with targets and site pages'),(1,'Server-side PDF and share images, 16 e-cards'),(1,'Shared list filters (Flux Admin) and Excel export'),(0,'English admin screens'),(0,'Apple Wallet / Google Wallet pass')]),
'tr':dict(name='Flux Certificates',
 tagline='Hediye edilen her fidan için kişiye özel sertifika, paylaşım linki ve PDF.',
 desc="Flux Certificates kendi tasarımlarınızdan fidan bağış sertifikası, e-kart ve katılım belgesi üretir; her birine PDF'i ve paylaşım görseliyle doğrulanmış bir sayfa açar ve WhatsApp, SMS ya da e-postayla gönderir. Etkinlikler, referans personel ve canlı Dashboard her fidanın nereden geldiğini gösterir.",
 badges=['![PDF](https://img.shields.io/badge/PDF-sunucu%20%2B%20tarayıcı-2fbf8f)','![Kanallar](https://img.shields.io/badge/gönderim-WhatsApp%20%C2%B7%20SMS%20%C2%B7%20e--posta-333)'],
 gifalt='Flux Certificates turu: Dashboard, sertifika listesi, Yeni sertifika ekranında isim yazınca canlı PDF önizlemesi, şablon galerisi ve sitedeki sertifika sayfası',
 why='''Teşekkür sertifikası çoğu zaman bağışçının sakladığı ve paylaştığı tek şeydir. Canva'da tek tek hazırlayıp tek tek göndermek, yüzlerce kişilik bir fidan dikimine yetişmez. Flux Certificates bunu bir listeye çevirir:

- 📝 **İsmi yaz, sertifika hazır.** Her kişi bir satır (ya da listeden yapıştır); herkese ayrı sertifika, link ve PDF; yazarken canlı önizleme.
- 🔗 **Paylaşılası bir sayfa.** `/sertifika/{kod}` "doğrulanmış" rozetiyle sertifikayı, PDF indirmeyi ve WhatsApp, LinkedIn, Facebook, X düğmelerini gösterir; link önizlemesinde kişinin kendi sertifikası çıkar.
- 🎨 **Kendi tasarımlarınız.** Canva'dan PDF yükleyip alanları üstüne yerleştirin; 3 sertifika şablonu ve 16 Örnek Vakfı e-kartı hazır gelir.
- 🌳 **Etkinlik ve hedef.** Fidanları etkinlik bazında toplayın (ör. 11 Kasım fidan dikimi), sitede hedef çubuğuyla.
- 📊 **Kim ne getirdi, belli:** referans personel, şablonlar, en yoğun günler ve gönderim başarısı tek Dashboard'da.''',
 features='''### Sertifika oluşturma
- **Yeni sertifika:** şablon seçin, kişileri satır satır ekleyin ya da **Listeden yapıştır** (`Ad Soyad; telefon; e-posta; fidan sayısı`), ortak alanları bir kez yazın, toplam fidanı kişilere dağıtın. Sağda canlı PDF önizlemesi.
- Kişiler telefona (yoksa e-postaya) göre tanınır, iki kez kaydedilmez; telefon her biçimde yazılır, tek biçimde saklanır.
- Sonuç ekranı: linkleri kopyala, kendi telefonundan WhatsApp'la gönder ya da hepsini **tek PDF** indir.
- Mutabakat için geçmiş tarihli sertifika (yalnız yönetici), liste başına referans personel.

### Gönderim
- WhatsApp, SMS ve e-posta **Flux Mail** üzerinden gider; mesajlar kuyruğa alınır, hata olursa 1, 5, 15 ve 30 dakika sonra yeniden denenir.
- Sonuç ekranından ya da listeden **Başka birine gönder** (birden çok alıcı, rehberden öneri).
- Kaç kişiye gideceğini önizleyerek toplu gönderim; `{ad}`, `{link}`, `{fidan_sayisi}` değişkenli mesaj metinleri.

### Sertifika sayfası
- Sertifika görseli, bilgiler, PDF indirme ve paylaşım düğmeli doğrulanmış sayfa; her sertifikaya 1200×630 paylaşım görseli.
- PDF, görsel ve paylaşım görseli sunucuda üretilir (TCPDF + FPDI, PHP GD yeterli); yedek olarak tarayıcıda.
- `[flx_sertifika_sorgula]` sorgulama formu: ziyaretçi linkini yalnız kendi kayıtlı telefonuna ya da e-postasına alır.

### Şablonlar
- PDF arka plan (ör. Canva'dan) üzerine sürükleyip yerleştirilen metin, çok satırlı metin, tarih, sayı, otomatik numara, QR ve sabit metin alanları.
- Bağış sayfasında bağışçının seçebileceği tasarımlar (Flux Donate kullanır); siteler arası JSON dışa/içe aktarma; ek yazı tipleri.

### Etkinlikler ve raporlar
- Tarih, hedef, ek (sertifikasız) fidan, konum ve sitede canlı sayaçlı sayfası olan etkinlikler; bitince kapat ve arşivle.
- Dashboard: bugüne kadar hediye edilen fidan, önceki dönemle karşılaştırmalı rakamlar, günlük ve haftanın günü grafikleri, gönderimler, referans personel ve şablonlar, en çok fidan hediye edenler, CSV. Sertifika listesinin Excel'e aktarımı.
- Girişte doğrudan **Yeni sertifika**'ya düşen personel rolü; Flux Roles ile personel yalnız kendi kayıtlarını görür.''',
 shots=[('public','Sitedeki sertifika sayfası'),('new','Canlı önizlemeli yeni sertifika'),('dashboard','Dashboard'),('certificates','Tüm sertifikalar'),('templates','Şablonlar ve e-kartlar'),('events','Etkinlikler'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-public'],'Mobil: Dashboard ve sertifika sayfası')],
 req='Kalıcı bağlantılar açık. WhatsApp/SMS/e-posta gönderimi için Flux Mail.',
 first='''1. `/sertifika/{kod}` adresleri çalışsın diye **Ayarlar › Kalıcı bağlantılar**'ı bir kez kaydedin.
2. **Sertifikalar › Şablonlar**: fidan bağış sertifikası hazır; bağışçının seçmesini istediğiniz e-kartları etkinleştirin.
3. **Sertifikalar › Yeni sertifika**: şablon seçin, bir kişi ekleyin ve **Oluştur**'a basın.''',
 note="> ℹ️ Sertifika sayfası, sitedeki etkinlik bölümleri ve sorgulama formu site diline göre İngilizceye geçer; yönetim ekranlarının çoğu Türkçe kalır. Eklenti silinince veriler, **Ayarlar › Veri**'deki kutu işaretlenmedikçe silinmez.",
 dev='''- Diğer paketlerden sertifika oluşturma: `flxs_create_certificate( $template_id, $person_id, $values, $ref, $date )`, kişi için `flxs_upsert_person()`.
- Bağışçının seçebileceği tasarımlar: `flxs_donor_templates()` (filtre `flxs_donor_templates`).
- Kısa kodlar: `[flxs_etkinlikler]`, `[flxs_etkinlik id="3"]`, `[flxs_fidan_sayaci]`, `[flx_sertifika_sorgula]`.
- `flxs_manage`, `flxs_dashboard`, `flxs_own_ref` yetkileri Flux Roles'e, kontroller `flx_system_checks`'e kayıtlıdır.''',
 roadmap=[(1,'Canlı PDF önizlemeli liste bazlı sertifika'),(1,'Kuyruklu WhatsApp, SMS ve e-posta gönderimi'),(1,'Hedefli ve site sayfalı etkinlikler'),(1,'Sunucuda PDF ve paylaşım görseli, 16 e-kart'),(1,'Ortak liste süzgeçleri (Flux Admin) ve Excel'),(0,'İngilizce yönetim ekranları'),(0,'Apple Wallet / Google Wallet kartı')]),
}
