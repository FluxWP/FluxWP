SLUG='flx-menu-theme'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'themes/flx-menu-theme'; WP='6.6'; PHP='7.4'; DOCS='flx-menu-theme'; API=False; THEME=True
C={
'en':dict(name='Flux Menu Theme',
 tagline='The quietest possible block theme for a QR menu domain: a blank white home page and nothing WordPress doesn\'t need.',
 desc='Flux Menu Theme is the theme of menu.example.org, the domain behind our event QR menus. The home page is completely blank and white, other pages are plain centred text, and the menus themselves are served by Flux QR Menu at addresses like `/menu/` or `menu1.menu.example.org`. Theme Settings switch off what a menu site never uses (posts, comments, feeds, search, XML-RPC…), and Site Setup prepares the site in one click.',
 badges=['![Minimal](https://img.shields.io/badge/home%20page-blank%20white-lightgrey)','![Hardening](https://img.shields.io/badge/switches-9%20features%20off-2fbf8f)','![QR](https://img.shields.io/badge/works%20with-FluxWP%20QR%20Menu-0a7cff)'],
 gifalt='Flux Menu Theme tour: Site Setup, Theme Settings with the feature switches and a QR menu served on the same domain',
 why='''A QR menu domain should load the menu and nothing else. A normal theme brings a header, blog archives, comment forms and feeds that only invite spam and confuse search engines:

- ⬜ **Nothing to see at the root.** The home page is white in light and dark mode; visitors arrive through a QR code straight to the menu.
- 🔒 **Less surface.** Posts, comments, feeds, search, attachment pages, XML-RPC, emoji, oEmbed and the public users endpoint can be switched off one by one, and back on.
- 🗺️ **Clean sitemap.** Only pages and the QR menu addresses on this domain are listed.
- 🧩 **Out of the menu\'s way.** Flux QR Menu renders its pages itself; the theme never touches them.''',
 features='''### Theme
- Blank white home page (no header or footer), plain centred page, post, list and 404 templates with the system font.
- Turkish source with an English translation.

### Theme Settings
- Nine switches, all on by default: posts, comments, RSS feeds, search, attachment pages, XML-RPC, emoji script, oEmbed, anonymous `/wp/v2/users`.
- Switched-off routes return 404 or 403, menus and admin items disappear; Flux QR Menu pages, REST endpoints and the menu manager panel are not affected.

### Site Setup
- Selectable steps: permalinks, İstanbul time zone, `d.m.Y` dates, Turkish language if installed, clean-up of untouched default content, comments off, blank home page check.
- Ready-made updates with dates; a notice when one is pending.
- System checks: features off, published posts while posts are off, permalinks, pending updates, Flux QR Menu inactive.''',
 shots=[('setup','Site Setup'),('settings','Theme Settings'),('menu','A QR menu on the same domain'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-home'],'Mobile: QR menu and the blank home page')],
 req='Flux QR Menu for the menus.',
 first='''1. Activate Flux Menu Theme and run **Appearance › Site Setup** with the selected steps.
2. Keep the switches in **Appearance › Theme Settings** on unless the site needs posts or comments.
3. Create menus in **QR Menus** and give each an address (`/menu/`) or a subdomain; see the Flux QR Menu setup notes.''',
 note='> ℹ️ After a theme update, apply any pending item under **Site Setup › Ready-made updates**.',
 dev='''- Menu addresses, subdomains, the menu manager role and SSL: [Flux QR Menu setup](../../docs/qr-menu/KURULUM.md).
- Ready-made updates are stored with their dates in the `yf_migrations` option.''',
 roadmap=[(1,'Blank white home page and plain templates'),(1,'Nine feature switches'),(1,'Site Setup and ready-made updates'),(1,'System checks'),(0,'Custom favicon per menu subdomain')]),
'tr':dict(name='Flux Menu Theme',
 tagline="QR menü alan adı için olabilecek en sessiz blok tema: bomboş beyaz ana sayfa ve WordPress'in gerekmeyen hiçbir parçası.",
 desc="Flux Menu Theme, etkinlik QR menülerimizin alan adı menu.example.org'un temasıdır. Ana sayfa tamamen boş ve beyazdır, diğer sayfalar sade ortalanmış metindir; menüleri ise Flux QR Menu `/menu/` ya da `menu1.menu.example.org` gibi adreslerde kendisi sunar. Tema Ayarları bir menü sitesinin hiç kullanmadığı şeyleri (yazılar, yorumlar, beslemeler, arama, XML-RPC…) kapatır, Site Kurulumu siteyi tek tıkla hazırlar.",
 badges=['![Sade](https://img.shields.io/badge/ana%20sayfa-boş%20beyaz-lightgrey)','![Sıkılaştırma](https://img.shields.io/badge/anahtar-9%20özellik%20kapalı-2fbf8f)','![QR](https://img.shields.io/badge/birlikte-FluxWP%20QR%20Menü-0a7cff)'],
 gifalt='Flux Menu Theme turu: Site Kurulumu, özellik anahtarlı Tema Ayarları ve aynı alan adında sunulan bir QR menü',
 why='''Bir QR menü alan adı menüyü açmalı, başka hiçbir şeyi değil. Sıradan bir tema header, blog arşivleri, yorum formları ve beslemeler getirir; bunlar yalnız spam çeker ve arama motorlarının kafasını karıştırır:

- ⬜ **Kökte görülecek bir şey yok.** Ana sayfa açık ve koyu modda beyazdır; ziyaretçi QR koddan doğrudan menüye gelir.
- 🔒 **Daha az yüzey.** Yazılar, yorumlar, beslemeler, arama, ek sayfaları, XML-RPC, emoji, oEmbed ve herkese açık kullanıcı ucu tek tek kapatılır, geri açılır.
- 🗺️ **Temiz site haritası.** Yalnız sayfalar ve bu alan adındaki QR menü adresleri listelenir.
- 🧩 **Menünün yolundan çekilir.** Flux QR Menu sayfalarını kendisi üretir; tema onlara hiç dokunmaz.''',
 features='''### Tema
- Header ve footer'sız bomboş beyaz ana sayfa; sistem yazı tipiyle sade, ortalanmış sayfa, yazı, liste ve 404 şablonları.
- Türkçe kaynak, İngilizce çeviri dahil.

### Tema Ayarları
- Hepsi varsayılan açık dokuz anahtar: yazılar, yorumlar, RSS beslemeleri, arama, ek sayfaları, XML-RPC, emoji betiği, oEmbed, anonim `/wp/v2/users`.
- Kapatılan adresler 404 ya da 403 döner, menü ve panel öğeleri kaybolur; Flux QR Menu sayfaları, REST uçları ve menü yöneticisi paneli etkilenmez.

### Site Kurulumu
- Seçilebilir adımlar: kalıcı bağlantılar, İstanbul saat dilimi, `d.m.Y` tarih, yüklüyse Türkçe dil, değiştirilmemiş varsayılan içeriğin temizlenmesi, yorumların kapatılması, boş ana sayfa denetimi.
- Tarihli hazır güncellemeler; bekleyen varsa bildirim.
- Sistem kontrolleri: kapalı özellikler, yazılar kapalıyken yayındaki yazılar, kalıcı bağlantılar, bekleyen güncellemeler, Flux QR Menu etkin değil.''',
 shots=[('setup','Site Kurulumu'),('settings','Tema Ayarları'),('menu','Aynı alan adında bir QR menü'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-home'],'Mobil: QR menü ve boş ana sayfa')],
 req='Menüler için Flux QR Menu.',
 first='''1. Flux Menu Theme'u etkinleştirip **Görünüm › Site Kurulumu**'nu seçili adımlarla çalıştırın.
2. Site yazı ya da yorum gerektirmiyorsa **Görünüm › Tema Ayarları**'ndaki anahtarları açık bırakın.
3. **QR Menüler**'de menüleri oluşturup her birine adres (`/menu/`) ya da alt alan adı verin; Flux QR Menu kurulum notlarına bakın.''',
 note="> ℹ️ Tema güncellemesinden sonra **Site Kurulumu › Hazır güncellemeler**'de bekleyen maddeyi uygulayın.",
 dev='''- Menü adresleri, alt alan adları, menü yöneticisi rolü ve SSL: [Flux QR Menu kurulumu](../../docs/qr-menu/KURULUM.md).
- Hazır güncellemeler tarihleriyle `yf_migrations` seçeneğinde tutulur.''',
 roadmap=[(1,'Boş beyaz ana sayfa ve sade şablonlar'),(1,'Dokuz özellik anahtarı'),(1,'Site Kurulumu ve hazır güncellemeler'),(1,'Sistem kontrolleri'),(0,'Menü alt alan adına özel site simgesi')]),
}
