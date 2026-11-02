SLUG='flx-forms'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-forms'; WP='6.4'; PHP='7.4'; DOCS='form-builder'; API=False
C={
'en':dict(name='Flux Forms',
 tagline='Drag-and-drop forms that look like your site, with payments and automations built in.',
 desc='Flux Forms is our fork of Easy Form Builder with every feature unlocked and nothing phoning home. Build contact, survey, support and sapling donation forms with 50+ field types, take payments, follow responses on a dashboard and let automations create and send a certificate when a donation is paid.',
 badges=['![Fork](https://img.shields.io/badge/fork%20of-Easy%20Form%20Builder%204.2-2fbf8f)','![No telemetry](https://img.shields.io/badge/telemetry-none-333)'],
 gifalt='Flux Forms tour: the dashboard with responses and conversion, the response list of the sapling donation form, the drag-and-drop builder, the certificate automation and the form on the site',
 why='''Form plugins usually lock the useful parts (payments, conditional logic, file uploads) behind a licence and call home to check it. We needed one that is fully ours, runs on shared hosting and works with our donation and certificate packages. Flux Forms is that:

- 🔓 **Everything unlocked.** No licence checks, remote add-on downloads or telemetry; Stripe, conditional logic and offline country/city lists rewritten.
- 🎨 **Looks like the site.** Forms use the theme's colours, fonts and dark mode automatically; the builder shows the form exactly as it will appear.
- 📈 **Know what works.** Responses, form views, conversion rate and payments per form, compared with the previous period.
- 🌱 **Donation to certificate, automatically.** A paid sapling donation creates a certificate (Flux Certificate), sends it by WhatsApp and e-mail and shows it on the thank-you screen.
- 🔐 **Hardened:** stored XSS, tracking-code IDOR, upload folder and IP detection fixes on top of the original.''',
 features='''### Builder
- 50+ elements: text, name, e-mail, phone with country picker, number, date, file and drag-and-drop upload, address and country/province/district lists, map location picker (OpenStreetMap), signature, rating, NPS, image picker, terms (KVKK), payment options, price and total fields, audio/video/screen recording and more.
- Element panel with search and categories, settings panel that opens when you click a field, multi-step forms with a custom last-step title.
- **Conditional logic**: show/hide fields or steps, make required, disable, block submission; rules are enforced on the server too.
- Ready templates: Contact, Support, Survey, Payment, **Sapling donation**, multi-step contact, customer feedback, login and registration.

### Responses
- Panel with every form and its shortcode `[EMS_Form_Builder id=3]`; Elementor and WPBakery widgets.
- Response list with unread markers, a detail view with payment info and totals, tracking codes, replies and CSV export.
- E-mails go through Flux Contact's shared design when it is active (editable templates under **Contact › E-mails**).

### Dashboard
- Responses, unread, form views, conversion rate and payments received, each compared with the previous period.
- Daily responses chart, payment status, responses per form, a day-and-hour heat map and the latest responses; filter by form and period, CSV export.

### Payments and automations
- With Flux Donation, the **Pay** step puts the chosen amount into the WooCommerce checkout (card, bank transfer, monthly donation); without it, built-in Stripe with server-side amount checks and webhooks.
- **Automations**: when a form is submitted or a payment completes, run steps such as *Create certificate*, *Send certificate*, *Show on thank-you screen*, *Send e-mail* and *Webhook*. A ready-made "Sapling donation → Certificate" flow is one click away.''',
 shots=[('dashboard','Dashboard'),('builder','Drag-and-drop builder'),('responses','Responses'),('automations','Automations'),('templates','Form templates'),('public','The form on the site'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-public'],'Mobile: dashboard and form')],
 first='''1. If the original Easy Form Builder is installed, **deactivate it (don't delete it)** before activating Flux Forms: both use the same tables, so your forms and responses come over as they are.
2. **Forms › Create**: pick a template, adjust it and **Save**.
3. Copy the shortcode from **Forms › Panel** into a page with a *Shortcode* block.
4. For donations: **Forms › Automations › Create default flow** connects the sapling donation form to Flux Certificate.''',
 note='> ℹ️ The interface follows the site language: Turkish on a Turkish site, English otherwise.',
 dev='''- Theme styling can be turned off with `add_filter( 'flxf_theme_style', '__return_false' )`; the original builder look with `flxf_builder_skin`.
- External payments (used by Flux Donation): `do_action( 'flxf_external_payment', $ctx )` runs a response's "payment completed" flows once per tracking code; `flxf_automations_form_issues_certificate( $form_id )` prevents duplicate certificates.
- Sapling price: `flxf_sapling_unit_price` filter (default 150 ₺; Flux Donation's price wins when installed).
- Payment provider guide: [docs/PAYMENT-PROVIDERS.md](docs/PAYMENT-PROVIDERS.md).''',
 roadmap=[(1,'Fork of Easy Form Builder with every feature unlocked and no telemetry'),(1,'Theme-aware forms and the new builder skin'),(1,'Dashboard with views, conversion and payments'),(1,'Automations with Flux Certificate'),(1,'Payments through WooCommerce with Flux Donation'),(0,'Saved drafts for long multi-step forms'),(0,'Spam scoring without reCAPTCHA')],
 licextra='Flux Forms is a fork of **Easy Form Builder 4.2.0** by [WhiteStudio](https://whitestudio.team) (GPL v2+); the original copyright and licence notices are kept. Third-party: Leaflet (BSD-2), Leaflet.FullScreen (MIT), country-state-city data (GPL-3.0).'),
'tr':dict(name='Flux Forms',
 tagline='Sitenize benzeyen, ödeme ve otomasyonları içinde sürükle-bırak formlar.',
 desc="Flux Forms, Easy Form Builder'ın tüm özellikleri açık ve dışarıya hiçbir şey göndermeyen fork'udur. 50'den fazla alan türüyle iletişim, anket, destek ve fidan bağışı formları kurun, ödeme alın, yanıtları Dashboard'da izleyin; bağış ödenince otomasyonlar sertifikayı oluşturup göndersin.",
 badges=['![Fork](https://img.shields.io/badge/fork-Easy%20Form%20Builder%204.2-2fbf8f)','![Telemetri yok](https://img.shields.io/badge/telemetri-yok-333)'],
 gifalt='Flux Forms turu: yanıt ve dönüşümlü Dashboard, fidan bağışı formunun yanıtları, sürükle-bırak builder, sertifika otomasyonu ve sitedeki form',
 why='''Form eklentileri genelde işe yarayan kısımları (ödeme, koşullu mantık, dosya yükleme) lisansa bağlar ve bunu kontrol etmek için dışarıya bağlanır. Bize tamamen bizim olan, paylaşımlı sunucuda çalışan ve bağış ve sertifika paketlerimizle birlikte çalışan bir form gerekiyordu. Flux Forms bu:

- 🔓 **Her şey açık.** Lisans kontrolü, uzaktan eklenti indirme ve telemetri yok; Stripe, koşullu mantık ve çevrimdışı ülke/il listeleri yeniden yazıldı.
- 🎨 **Siteye benzer.** Formlar temanın renklerini, yazı tiplerini ve koyu modunu kendiliğinden kullanır; builder formu sitede görüneceği gibi gösterir.
- 📈 **Neyin işe yaradığını görün.** Form başına yanıt, görüntülenme, dönüşüm oranı ve ödeme; önceki dönemle karşılaştırmalı.
- 🌱 **Bağıştan sertifikaya, kendiliğinden.** Ödenen fidan bağışı sertifikayı oluşturur (Flux Certificates), WhatsApp ve e-postayla gönderir ve teşekkür ekranında gösterir.
- 🔐 **Sağlamlaştırıldı:** aslının üstüne stored XSS, takip kodu IDOR, yükleme klasörü ve IP tespiti düzeltmeleri.''',
 features='''### Builder
- 50'den fazla eleman: metin, ad, e-posta, ülke seçicili telefon, sayı, tarih, dosya ve sürükle-bırak yükleme, adres ve ülke/il/ilçe listeleri, haritadan konum seçici (OpenStreetMap), imza, puanlama, NPS, görsel seçici, şartlar (KVKK), ödeme seçenekleri, tutar ve toplam alanları, ses/video/ekran kaydı ve dahası.
- Aramalı ve kategorili eleman paneli, alana tıklayınca açılan ayar paneli, son adım başlığı değiştirilebilen çok adımlı formlar.
- **Koşullu mantık:** alan/adım göster-gizle, zorunlu yap, devre dışı bırak, gönderimi engelle; kurallar sunucuda da uygulanır.
- Hazır şablonlar: İletişim, Destek, Anket, Ödeme, **Fidan Bağışı**, çok adımlı iletişim, müşteri geri bildirimi, giriş ve kayıt.

### Yanıtlar
- Tüm formlar ve kısa kodları `[EMS_Form_Builder id=3]` Panel'de; Elementor ve WPBakery widget'ları.
- Okunmamış işaretli yanıt listesi; ödeme bilgisi ve toplamla yanıt ayrıntısı, takip kodu, yanıtlama ve CSV.
- Flux Mail etkinse e-postalar onun ortak tasarımıyla gider (**İletişim › E-postalar**'da düzenlenebilir şablonlar).

### Dashboard
- Yanıt, okunmamış, form görüntülenmesi, dönüşüm oranı ve alınan ödeme; her biri önceki dönemle karşılaştırmalı.
- Günlük yanıt grafiği, ödeme durumu, forma göre yanıtlar, gün-saat ısı haritası ve son yanıtlar; form ve döneme göre süzgeç, CSV.

### Ödeme ve otomasyonlar
- Flux Donate varsa **Öde** adımı seçilen tutarı WooCommerce ödeme sayfasına taşır (kart, havale, aylık bağış); yoksa sunucuda tutar doğrulamalı ve webhook'lu yerleşik Stripe.
- **Otomasyonlar:** form gönderilince ya da ödeme tamamlanınca *Sertifika oluştur*, *Sertifika gönder*, *Teşekkür ekranında göster*, *E-posta gönder*, *Webhook* gibi adımlar çalışır. Hazır "Fidan bağışı → Sertifika" akışı tek tıkla kurulur.''',
 shots=[('dashboard','Dashboard'),('builder','Sürükle-bırak builder'),('responses','Yanıtlar'),('automations','Otomasyonlar'),('templates','Form şablonları'),('public','Sitedeki form'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-public'],'Mobil: Dashboard ve form')],
 first='''1. Orijinal Easy Form Builder kuruluysa Flux Forms'u etkinleştirmeden önce onu **devre dışı bırakın (silmeyin)**: ikisi aynı tabloları kullanır, formlar ve yanıtlar olduğu gibi gelir.
2. **Formlar › Oluştur**: bir şablon seçin, düzenleyin ve **Kaydet**'e basın.
3. **Formlar › Panel**'deki kısa kodu bir sayfaya *Kısa kod* bloğuyla ekleyin.
4. Bağış için: **Formlar › Otomasyonlar › Varsayılan akışı oluştur** fidan bağışı formunu Flux Certificates'ya bağlar.''',
 note='> ℹ️ Arayüz site diline uyar: Türkçe sitede Türkçe, diğerlerinde İngilizce.',
 dev='''- Tema stili `add_filter( 'flxf_theme_style', '__return_false' )` ile kapatılır; eski builder görünümü `flxf_builder_skin` ile.
- Dış ödeme (Flux Donate kullanır): `do_action( 'flxf_external_payment', $ctx )` bir yanıtın "ödeme tamamlandı" akışlarını takip kodu başına bir kez çalıştırır; `flxf_automations_form_issues_certificate( $form_id )` çift sertifikayı önler.
- Fidan fiyatı: `flxf_sapling_unit_price` filtresi (varsayılan 150 ₺; Flux Donate kuruluysa onun fiyatı geçerli).
- Ödeme sağlayıcısı rehberi: [docs/PAYMENT-PROVIDERS.md](docs/PAYMENT-PROVIDERS.md).''',
 roadmap=[(1,"Tüm özellikleri açık, telemetrisiz Easy Form Builder fork'u"),(1,'Temaya uyan formlar ve yeni builder görünümü'),(1,'Görüntülenme, dönüşüm ve ödemeli Dashboard'),(1,'Flux Certificates ile otomasyonlar'),(1,'Flux Donate ile WooCommerce üzerinden ödeme'),(0,'Uzun çok adımlı formlarda taslak kaydetme'),(0,"reCAPTCHA'sız spam puanlama")],
 licextra="Flux Forms, [WhiteStudio](https://whitestudio.team)'nun **Easy Form Builder 4.2.0** eklentisinin fork'udur (GPL v2+); orijinal telif ve lisans bildirimleri korunmuştur. Üçüncü taraf: Leaflet (BSD-2), Leaflet.FullScreen (MIT), country-state-city verisi (GPL-3.0)."),
}
