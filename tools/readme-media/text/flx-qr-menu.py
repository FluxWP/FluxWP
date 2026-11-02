SLUG='flx-qr-menu'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-qr-menu'; WP='6.4'; PHP='7.4'; DOCS='qr-menu'; API=True
C={
'en':dict(name='Flux QR Menu',
 tagline='QR menus for events and stands, edited in WordPress and live on phones within seconds.',
 desc='Flux QR Menu lets you build menus for a charity food stand or event in the WordPress admin: categories, items with photos, prices, badges and "sold out". A live phone preview sits next to the editor. Menus are published on the same site (for example `/menu/` or `menu1.example.org`) with a printable QR code, or fed to separate menu sites. Every change is kept in a history you can restore.',
 badges=['![Live](https://img.shields.io/badge/updates-live%20in%20~20s-2fbf8f)','![QR](https://img.shields.io/badge/QR-SVG%20%C2%B7%20PNG-0a7cff)'],
 gifalt='Flux QR Menu tour: the menu list, the editor with live phone preview, marking an item sold out, the share tab with the QR code and the change history',
 why='''At a fundraising stand the menu changes by the hour: a dish runs out, a price changes, a new tray arrives. Printed menus can't keep up and a PDF on a link is unreadable on a phone. Flux QR Menu keeps one QR code on the table and the menu always right:

- 📱 **Made for phones.** A fast, app-like menu with category tabs, photos and prices; no PDF, no zoom.
- ⚡ **Changes in seconds.** Mark something sold out on your phone; open menus refresh themselves within about 20 seconds.
- 👥 **Delegate safely.** Menu managers edit only the menus they are given, without access to the rest of the site.
- 🕘 **Nothing is lost.** Every save is in the history with a readable summary and one-click restore.''',
 features='''### Editing
- Categories with icon, note and bottom-bar tab; items with name, price, old price, unit, description, photo, badge, link and large image.
- **Sold out** and **Hidden** buttons on every row (big on phones), drag to reorder, move between categories, copy, search.
- **Live preview** in a phone frame next to the editor, including unsaved changes.
- Event settings: title, slogan, days with dates, general info lines, **Menu active** switch with a thank-you page.
- Start empty, from the sample menu (24 items with photos) or by importing JSON.

### Publishing
- Publish on this site at a path (`/menu/`) and/or a subdomain; optionally as the home page.
- **Share** tab: QR code with **SVG** (print) and **PNG** download, copyable addresses.
- Open menus check for a new version every ~20 seconds and reload in place; menu pages are never cached.
- Separate menu sites (the `qr-menu` app) connect with a key and are notified on every save.

### Control
- **History:** every change with a readable diff summary, view any version, restore.
- **Menu manager** role with per-menu access; capability `flxq_manage_menus` for all menus.
- REST data endpoint and MCP tools; system checks.''',
 shots=[('editor','Editor with live phone preview'),('share','Share: address and QR code'),('history','Change history'),('settings','Event settings'),('list','Menus'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-menu'],'The menu on a phone')],
 req='Permalinks other than "Plain" if menus are published on this site.',
 first='''1. Activate Flux QR Menu; **QR Menus** appears in the admin menu.
2. **QR Menus › New menu**: name it and start from the sample menu, empty, or JSON.
3. Edit categories and items; the preview on the left updates as you type. **Save**.
4. **Share** tab: set the address (for example `menu`), save, then download the QR code.''',
 note='> ℹ️ Menu pages send no-cache headers so LiteSpeed never serves an old menu. To use a subdomain, point it to the same folder as the site and make sure the SSL certificate covers it.',
 dev='''- Data endpoint and connection flow for separate menu sites, plus the REST API: [API.md](../../docs/qr-menu/API.md).
- Theme for a dedicated menu domain: [`themes/flx-menu-theme`](../../themes/flx-menu-theme).''',
 roadmap=[(1,'Editor with live phone preview'),(1,'Publishing on this site with QR code'),(1,'History and restore'),(1,'Menu manager role'),(0,'Multiple languages per menu'),(0,'Allergen icons')]),
'tr':dict(name='Flux QR Menu',
 tagline="Etkinlik ve stantlar için QR menüler: WordPress'te düzenlenir, saniyeler içinde telefonlarda.",
 desc="Flux QR Menu bir dernek yemek standı ya da etkinlik için menüyü WordPress panelinde kurmanızı sağlar: kategoriler, fotoğraflı ürünler, fiyat, rozet ve \"tükendi\". Düzenleyicinin yanında canlı telefon önizlemesi durur. Menüler aynı sitede (ör. `/menu/` ya da `menu1.ornek.org`) basılabilir QR koduyla yayınlanır ya da ayrı menü sitelerine beslenir. Her değişiklik geri yüklenebilir bir geçmişte tutulur.",
 badges=['![Canlı](https://img.shields.io/badge/güncelleme-~20%20sn-2fbf8f)','![QR](https://img.shields.io/badge/QR-SVG%20%C2%B7%20PNG-0a7cff)'],
 gifalt='Flux QR Menu turu: menü listesi, canlı telefon önizlemeli düzenleyici, ürünü tükendi yapma, QR kodlu Paylaş sekmesi ve değişiklik geçmişi',
 why='''Bir bağış standında menü saat saat değişir: bir yemek biter, fiyat değişir, yeni tepsi gelir. Basılı menü yetişemez, bağlantıdaki PDF telefonda okunmaz. Flux QR Menu masada tek bir QR kod tutar, menüyü hep doğru tutar:

- 📱 **Telefon için.** Kategori sekmeli, fotoğraflı, fiyatlı, uygulama gibi hızlı bir menü; PDF yok, yakınlaştırma yok.
- ⚡ **Saniyeler içinde değişiklik.** Telefondan bir ürünü tükendi yapın; açık menüler yaklaşık 20 saniyede kendini yeniler.
- 👥 **Güvenle devret.** Menü yöneticileri sitenin geri kalanına erişmeden yalnız kendilerine verilen menüleri düzenler.
- 🕘 **Hiçbir şey kaybolmaz.** Her kayıt okunur özetiyle geçmişte, tek tıkla geri yüklenir.''',
 features='''### Düzenleme
- İkonlu, notlu, alt çubuk sekmeli kategoriler; ad, fiyat, eski fiyat, birim, açıklama, fotoğraf, rozet, bağlantı ve büyük görselli ürünler.
- Her satırda **Tükendi** ve **Gizli** düğmeleri (telefonda büyük), sürükleyerek sıralama, kategoriler arası taşıma, kopyalama, arama.
- Düzenleyicinin yanında telefon çerçevesinde **canlı önizleme**; kaydedilmemiş değişiklikler dahil.
- Etkinlik ayarları: başlık, slogan, tarihli günler, genel bilgi satırları, teşekkür sayfalı **Menü aktif** anahtarı.
- Boş, örnek menüden (24 fotoğraflı ürün) ya da JSON içe aktararak başlama.

### Yayın
- Bu sitede bir adreste (`/menu/`) ve/veya alt alan adında; istenirse ana sayfa olarak.
- **Paylaş** sekmesi: **SVG** (baskı) ve **PNG** indirmeli QR kod, kopyalanabilir adresler.
- Açık menüler ~20 saniyede bir yeni sürüme bakar, yerinde yenilenir; menü sayfaları hiç önbelleğe alınmaz.
- Ayrı menü siteleri (`qr-menu` uygulaması) anahtarla bağlanır, her kayıtta bildirim alır.

### Kontrol
- **Geçmiş:** okunur fark özetli her değişiklik, herhangi bir sürümü görme, geri yükleme.
- Menü başına erişimli **Menü yöneticisi** rolü; tüm menüler için `flxq_manage_menus` yetkisi.
- REST veri ucu ve MCP araçları; sistem kontrolleri.''',
 shots=[('editor','Canlı telefon önizlemeli düzenleyici'),('share','Paylaş: adres ve QR kod'),('history','Değişiklik geçmişi'),('settings','Etkinlik ayarları'),('list','Menüler'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-menu'],'Telefonda menü')],
 req='Menüler bu sitede yayınlanacaksa kalıcı bağlantılar "Düz" olmamalı.',
 first='''1. Flux QR Menu'yü etkinleştirin; panel menüsünde **QR Menüler** çıkar.
2. **QR Menüler › Yeni menü**: ad verin, örnek menüden, boş ya da JSON ile başlayın.
3. Kategori ve ürünleri düzenleyin; soldaki önizleme yazdıkça güncellenir. **Kaydet**.
4. **Paylaş** sekmesi: adresi girin (ör. `menu`), kaydedin, QR kodu indirin.''',
 note="> ℹ️ Menü sayfaları önbelleksiz başlıklar gönderir, LiteSpeed eski menü göstermez. Alt alan adı için alan adını sitenin klasörüne yönlendirin ve SSL sertifikasının onu kapsadığından emin olun.",
 dev='''- Ayrı menü siteleri için veri ucu ve bağlantı akışı, REST API: [API.md](../../docs/qr-menu/API.md).
- Ayrı menü alan adı için tema: [`themes/flx-menu-theme`](../../themes/flx-menu-theme).''',
 roadmap=[(1,'Canlı telefon önizlemeli düzenleyici'),(1,'QR kodlu, bu sitede yayın'),(1,'Geçmiş ve geri yükleme'),(1,'Menü yöneticisi rolü'),(0,'Menü başına çok dil'),(0,'Alerjen ikonları')]),
}
