SLUG='flx-commerce'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-commerce'; WP='6.4'; PHP='7.4'; DOCS='flx-commerce'; API=True
C={
'en':dict(name='Flux Commerce',
 tagline='A sales dashboard, customers and a better checkout for any WooCommerce store.',
 desc='Flux Commerce sits on top of WooCommerce and adds what a small shop checks every day: revenue and orders at a glance, the customers who keep coming back, a sales report you can read, and bank transfers that customers can pay and prove on the site. It works for any store; nothing in it is about donations.',
 badges=['![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-ea580c)','![HPOS](https://img.shields.io/badge/HPOS-compatible-ea580c)'],
 gifalt='Flux Commerce tour: the store overview with daily revenue, the customer list with segments, and the sales report',
 why='''WooCommerce's own analytics are powerful but slow to read and need background jobs. Turkish shops also live on bank transfers that end up as screenshots in WhatsApp. Flux Commerce covers both:

- 📊 **One screen for the day:** net revenue, orders, average order and customers, compared with the previous period.
- 🏦 **Bank transfer that closes itself:** an order-specific reference, copyable IBAN, receipt upload on the site, one-click "Confirm payment".
- 👥 **Customers, not just users:** guests and members per e-mail, with repeat buyers, top spenders and lapsed customers.
- 🚚 **Small nudges that sell:** a free-shipping bar and a leaner checkout.
- 🛒 **Feeds and messages:** a Google Merchant feed and WhatsApp notifications through Flux Mail.''',
 features='''### Overview
- Today / 7 / 30 / 90 days: net revenue, orders, average order, customers (new ones too) with change against the previous period.
- Daily revenue chart, best sellers, orders awaiting payment (with "receipt in"), low stock, payment methods. A "Store · today" widget on the Dashboard.

### Customers
- One row per e-mail for members and guests: orders, total spent, first and last order, phone (WhatsApp link), city, an inline note.
- Segments: New (30 days), Repeat buyers, Top 10% spenders, Lapsed (90 days). Search, sort, CSV export.
- Kept up to date on every order status change; older orders are scanned once in chunks of 100.

### Sales report
- Presets or any date range: net revenue, orders, items, average order, refunds, shipping income with change.
- Products, categories and payment methods; CSV with summary, daily rows and every breakdown.

### Bank transfer with receipt upload
- Order-specific reference (`{kod} {ad}` → `SP1042 Ayşe Yılmaz`), copy buttons for recipient, IBAN, amount and reference on the thank-you page and in My account.
- Receipt upload (PDF/JPG/PNG/WEBP/HEIC) into a protected folder; the admin gets an e-mail (and WhatsApp).
- "Confirm payment" on the order screen and as an order action; a Transfer column in the orders list; optional auto-cancel after N days.
- Classic and block checkout. Hides itself when Flux Donate's bank transfer is on.

### Cart and checkout
- Hide company and address line 2; no address for virtual-only carts (classic); order notes on/off; sticky "Place order" on mobile.
- Free-shipping bar in the cart, checkout and mini cart, updated live in the cart block. Threshold from the settings or WooCommerce's Free shipping method.

### Product feed and notifications
- Google Merchant RSS with variations, sale prices, stock, GTIN/MPN and categories at a keyed address.
- WhatsApp through Flux Mail: bank details and payment confirmation to the customer, receipt and new-order alerts to the admin.''',
 shots=[('overview','Store overview'),('customers','Customers and segments'),('report','Sales report'),('settings','Settings'),('dark','Dark mode'),('mobile','Mobile',True),('mobile-customers','Customers on mobile',True)],
 req='WooCommerce 8.0+. Optional: Flux Mail with a WhatsApp connection, Flux Admin.',
 first='''1. Open **Store** in the admin menu.
2. **Store › Customers › Rebuild** once to bring in older orders.
3. Turn on **WooCommerce › Settings › Payments › Bank transfer (with receipt upload)**; the IBAN comes from "Direct bank transfer" if left empty.
4. Pick checkout options, the free-shipping amount and the feed under **Store › Settings**.''',
 dev='''- `FLXCM_Stats::summary( $from, $to )` returns the whole report as an array; `flxcm_count_order` filters orders out of the statistics.
- Hooks: `flxcm_bank_placed`, `flxcm_receipt_uploaded`, `flxcm_bank_confirmed`; REST `POST flx-commerce/v1/receipt`.
- Full list: [API.md](../../docs/flx-commerce/API.md).''',
 roadmap=[(1,'Overview, customers, sales report'),(1,'Bank transfer with receipt upload, checkout options, free-shipping bar'),(1,'Google Merchant feed, WhatsApp notifications'),(0,'Abandoned cart reminders over WhatsApp and e-mail'),(0,'Customer detail page with order timeline'),(0,'Stock alerts and reorder list')]),
'tr':dict(name='Flux Commerce',
 tagline='Her WooCommerce mağazası için satış paneli, müşteriler ve daha iyi bir ödeme sayfası.',
 desc="Flux Commerce WooCommerce'in üzerine, küçük bir mağazanın her gün baktığı şeyleri ekler: bir bakışta ciro ve siparişler, tekrar gelen müşteriler, okunur bir satış raporu ve müşterinin sitede ödeyip dekontunu yükleyebildiği Havale/EFT. Her mağaza kullanabilir; içinde bağışa özgü bir şey yoktur.",
 badges=['![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-ea580c)','![HPOS](https://img.shields.io/badge/HPOS-uyumlu-ea580c)'],
 gifalt='Flux Commerce turu: günlük cirolu mağaza özeti, bölümlü müşteri listesi ve satış raporu',
 why='''WooCommerce'in kendi analizi güçlü ama okuması yavaş ve arka plan işi ister. Türkiye'de mağazalar da havaleyle yaşar; dekontlar WhatsApp'ta ekran görüntüsü olarak kalır. Flux Commerce ikisini de çözer:

- 📊 **Günün tek ekranı:** net ciro, sipariş, ortalama sepet ve müşteri; önceki döneme göre.
- 🏦 **Kendini kapatan havale:** siparişe özel açıklama kodu, kopyalanır IBAN, sitede dekont yükleme, tek tıkla "Ödemeyi onayla".
- 👥 **Kullanıcı değil müşteri:** üye ve misafirler e-posta başına; tekrar alanlar, en çok harcayanlar, uzaklaşanlar.
- 🚚 **Satışa küçük dokunuşlar:** ücretsiz kargo çubuğu ve sade ödeme sayfası.
- 🛒 **Feed ve mesajlar:** Google Merchant feed'i ve Flux Mail üzerinden WhatsApp bildirimleri.''',
 features='''### Genel bakış
- Bugün / 7 / 30 / 90 gün: net ciro, sipariş, ortalama sepet, müşteri (yeniler dahil); önceki döneme göre değişim.
- Günlük ciro grafiği, en çok satanlar, ödeme bekleyenler ("dekont geldi" rozetiyle), stok azalanlar, ödeme yöntemleri. Başlangıç'ta "Mağaza · bugün" kartı.

### Müşteriler
- Üye ve misafir müşteriler e-posta başına tek satır: sipariş, toplam harcama, ilk/son sipariş, telefon (WhatsApp bağlantısı), şehir, satır içi not.
- Bölümler: Yeni (30 gün), Tekrar alan, En çok harcayan %10, Uzaklaşan (90 gün). Arama, sıralama, CSV.
- Her sipariş durumu değişiminde güncellenir; eski siparişler bir kez 100'lük parçalarla taranır.

### Satış raporu
- Hazır dönemler ya da tarih aralığı: net ciro, sipariş, adet, ortalama sepet, iade, kargo geliri ve değişim.
- Ürün, kategori ve ödeme yöntemi kırılımı; özet, günlük satırlar ve tüm kırılımlarla CSV.

### Dekont yüklemeli Havale/EFT
- Siparişe özel açıklama (`{kod} {ad}` → `SP1042 Ayşe Yılmaz`); teşekkür sayfasında ve Hesabım'da alıcı, IBAN, tutar ve açıklama için Kopyala düğmeleri.
- Korumalı klasöre dekont yükleme (PDF/JPG/PNG/WEBP/HEIC); yöneticiye e-posta (ve WhatsApp).
- Sipariş ekranında ve sipariş işlemlerinde "Ödemeyi onayla"; siparişler listesinde Havale sütunu; isteğe bağlı N gün sonra iptal.
- Klasik ve blok ödeme. Flux Donate'in havalesi açıksa kendini gizler.

### Sepet ve ödeme sayfası
- Şirket ve adres 2 gizleme; yalnız sanal ürünlü sepette adres sormama (klasik); sipariş notu aç/kapa; mobilde sabit "Siparişi ver".
- Sepet, ödeme ve mini sepette ücretsiz kargo çubuğu; blok sepette adet değişince canlı güncellenir. Eşik ayardan ya da WooCommerce "Ücretsiz kargo" yönteminden.

### Ürün feed'i ve bildirimler
- Varyasyon, indirimli fiyat, stok, GTIN/MPN ve kategorili Google Merchant RSS; anahtarlı adres.
- Flux Mail üzerinden WhatsApp: müşteriye havale bilgisi ve ödeme onayı, yöneticiye dekont ve yeni sipariş bildirimi.''',
 shots=[('overview','Mağaza genel bakış'),('customers','Müşteriler ve bölümler'),('report','Satış raporu'),('settings','Ayarlar'),('dark','Koyu mod'),('mobile','Mobil',True),('mobile-customers','Mobilde müşteriler',True)],
 req='WooCommerce 8.0+. İsteğe bağlı: WhatsApp bağlantılı Flux Mail, Flux Admin.',
 first='''1. Yönetim menüsünde **Mağaza**'yı açın.
2. Eski siparişler için bir kez **Mağaza › Müşteriler › Yeniden oluştur**.
3. **WooCommerce › Ayarlar › Ödemeler › Havale/EFT (dekont yüklemeli)**'yi açın; IBAN boşsa "Doğrudan banka havalesi"nden gelir.
4. Ödeme sayfası seçeneklerini, ücretsiz kargo tutarını ve feed'i **Mağaza › Ayarlar**'dan seçin.''',
 dev='''- `FLXCM_Stats::summary( $from, $to )` raporun tamamını dizi olarak verir; `flxcm_count_order` siparişi istatistikten çıkarır.
- Kancalar: `flxcm_bank_placed`, `flxcm_receipt_uploaded`, `flxcm_bank_confirmed`; REST `POST flx-commerce/v1/receipt`.
- Tam liste: [API.md](../../docs/flx-commerce/API.md).''',
 roadmap=[(1,'Genel bakış, müşteriler, satış raporu'),(1,'Dekont yüklemeli Havale/EFT, ödeme seçenekleri, ücretsiz kargo çubuğu'),(1,"Google Merchant feed'i, WhatsApp bildirimleri"),(0,'WhatsApp ve e-postayla terk edilen sepet hatırlatması'),(0,'Sipariş zaman çizelgeli müşteri sayfası'),(0,'Stok uyarıları ve yeniden sipariş listesi')]),
}
