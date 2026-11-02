SLUG='flx-admin'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-admin'; WP='6.4'; PHP='7.4'; DOCS='flx-admin'; API=False
C={
'en':dict(name='Flux Admin',
 tagline='A calm, modern WordPress admin that works as well on a phone as on a desktop.',
 desc='Flux Admin redesigns the WordPress dashboard: a grouped sidebar you can edit per role, a mobile bottom bar, automatic light and dark themes, a command palette, live counters and an installable app (PWA). Turn it off and the standard WordPress admin comes back; no content or permission changes.',
 badges=['![PWA](https://img.shields.io/badge/PWA-installable-2fbf8f)','![Dark mode](https://img.shields.io/badge/theme-light%20%7C%20dark%20%7C%20auto-333)'],
 gifalt='Flux Admin tour: dashboard modules, the Ctrl+K command palette, moving between screens without a white flash, switching to dark mode and the menu editor',
 why='''Our volunteers and staff use WordPress mostly from their phones, and the stock admin shows them dozens of menus they never need. Flux Admin keeps WordPress underneath but changes how it feels:

- 🧭 **Only what each role needs.** Edit the sidebar per role: reorder, rename, hide, group. Hiding never changes permissions.
- 📱 **Phone first.** A bottom bar with four shortcuts and a slide-in side panel; the bar hides while the keyboard is open.
- 🌗 **Light, dark or automatic**, chosen by each person.
- ⌨️ **Ctrl+K / ⌘K** jumps to any screen, action or post.
- ⚡ **No white flashes.** The page is held until it is ready, so the menu and top bar never jump.
- 📲 **Install it as an app** on phones and computers (PWA) with a fast splash screen.''',
 features='''### Navigation
- Grouped sidebar (General, Apps, Donations, Content, Site, Other) with line icons; collapses to an icon strip.
- **Menu editor** per role with a live preview: drag to reorder, rename, hide, add groups, copy from another role. With Flux App installed, each item can be shown on Web, Mobile or Desktop separately.
- Panel / WhatsApp / Tasks switch above the menu with unread badges, and a small switch on the edge of the Call Center and Tasks screens (Alt+1…9).
- Mobile bottom bar (four shortcuts you choose plus "More") and a side panel that opens from the left.

### Dashboard
- Editable modules: everyone can rearrange their own start screen with "Edit modules".
- Summary tiles from other FluxWP packages (system check, visitors today, donations) and quick actions (`Label|address`).
- Live badges ride on WordPress Heartbeat, so no extra requests are opened.

### Look and feel
- Automatic light and dark themes, brand name, subtitle, logo and accent colour.
- Command palette with search across menus, apps, actions and content.
- Shared building blocks for other packages: list filters and pagination (`flxa_list_*`), safe-area variables for full-screen screens, modal dimming.

### App (PWA)
- "Add to Home Screen" on phones and the install icon on desktop browsers. Only CSS, JS, fonts and images are cached, never admin pages.
- Logins made inside the app stay signed in; browser logins behave as usual.''',
 shots=[('dashboard','Dashboard'),('dark','Dark mode'),('command-palette','Command palette (Ctrl+K)'),('menu-editor','Menu editor per role'),('settings','Settings'),(['mobile','mobile-menu'],'Mobile: bottom bar and side panel')],
 first='''1. **Settings › Admin theme** opens with sensible defaults; nothing has to be filled in.
2. Under **Menu and bottom bar**, pick a role, arrange its menu and choose the four bottom-bar shortcuts, then **Save**.
3. On a phone, open the admin and use **Add to Home Screen** to install it as an app.''',
 note='> ℹ️ Before removing the plugin, turn off the PWA under **Settings › Admin theme › App** so installed apps unregister their service worker.',
 dev='''- Count badges in the menu: filter `flxa_counts`. Dashboard tiles: `flxa_dashboard_stats`.
- Full-screen apps that want the switch button: `flxa_app_screens`.
- Shared list UI: `flxa_list_form_attrs()`, `flxa_list_per_page()`, `flxa_list_pagination()` ([LISTE-API.md](../../docs/flx-admin/LISTE-API.md)).
- Safe-area contract for full-screen screens and modals: [GUVENLI-ALAN.md](../../docs/flx-admin/GUVENLI-ALAN.md). In JS: `FLXA.safeArea()`, `FLXA.modal(true|false)`.''',
 roadmap=[(1,'Grouped, role-based sidebar and menu editor'),(1,'Mobile bottom bar, side panel and PWA'),(1,'Automatic light and dark themes, command palette'),(1,'Shared list filters and pagination for all FluxWP packages'),(1,'Per-platform menu items for the FluxWP desktop and mobile apps'),(0,'Dark theme for the block editor'),(0,'Saved searches in the command palette')]),
'tr':dict(name='Flux Admin',
 tagline='Telefonda da bilgisayarda da rahat kullanılan, sade ve modern bir WordPress paneli.',
 desc='Flux Admin WordPress yönetim panelini baştan tasarlar: role göre düzenlenebilen gruplu sol menü, mobil alt bar, otomatik açık/koyu tema, komut paleti, canlı sayaçlar ve kurulabilir uygulama (PWA). Kapatınca standart WordPress paneli geri gelir; içerik ve yetkiler değişmez.',
 badges=['![PWA](https://img.shields.io/badge/PWA-kurulabilir-2fbf8f)','![Tema](https://img.shields.io/badge/tema-açık%20%7C%20koyu%20%7C%20otomatik-333)'],
 gifalt='Flux Admin turu: Başlangıç modülleri, Ctrl+K komut paleti, beyaz ekran olmadan sayfa geçişi, koyu temaya geçiş ve menü düzenleyicisi',
 why='''Gönüllülerimiz ve personelimiz WordPress'i çoğunlukla telefondan kullanıyor; standart panel onlara hiç gerekmeyen onlarca menü gösteriyor. Flux Admin WordPress'i olduğu gibi bırakır, ama kullanım hissini değiştirir:

- 🧭 **Her role yalnız gerekeni.** Sol menüyü role göre düzenleyin: sıralayın, yeniden adlandırın, gizleyin, gruplayın. Gizlemek yetkiyi değiştirmez.
- 📱 **Önce telefon.** Dört kısayollu alt bar ve soldan açılan yan panel; klavye açıkken alt bar gizlenir.
- 🌗 **Açık, koyu ya da otomatik**, herkes kendisi seçer.
- ⌨️ **Ctrl+K / ⌘K** ile her ekrana, işleme ya da yazıya atlayın.
- ⚡ **Beyaz ekran yok.** Sayfa hazır olana kadar eskisi ekranda kalır; menü ve üst çubuk yerinden oynamaz.
- 📲 **Uygulama olarak kurun:** telefonda ve bilgisayarda (PWA), hızlı açılış ekranıyla.''',
 features='''### Gezinme
- Gruplu sol menü (Genel, Uygulamalar, Bağış, İçerik, Site, Diğer), çizgi simgeler; daraltılınca simge şeridine döner.
- Role göre **menü düzenleyicisi** ve canlı önizleme: sürükleyip sıralama, yeniden adlandırma, gizleme, grup ekleme, başka rolden kopyalama. Flux App kuruluysa her öğe Web, Mobil ve Masaüstü için ayrı açılıp kapanır.
- Menünün üstünde okunmamış rozetli Panel / WhatsApp / Görevler geçişi; Çağrı Merkezi ve Görev ekranlarının kenarında küçük geçiş düğmesi (Alt+1…9).
- Mobil alt bar (seçtiğiniz dört kısayol + "Daha") ve soldan açılan yan panel.

### Başlangıç
- Düzenlenebilir modüller: herkes kendi Başlangıç ekranını "Modülleri düzenle" ile düzenler.
- Diğer FluxWP paketlerinden özet kutucukları (sistem kontrolü, bugünkü ziyaretçi, bağışlar) ve hızlı işlemler (`Etiket|adres`).
- Canlı rozetler WordPress Heartbeat'e eklenir; ayrı istek açılmaz.

### Görünüm
- Otomatik açık/koyu tema, kurum adı, alt satır, logo ve vurgu rengi.
- Menülerde, uygulamalarda, işlemlerde ve içerikte arayan komut paleti.
- Diğer paketler için ortak yapı taşları: liste süzgeçleri ve sayfalama (`flxa_list_*`), tam ekran sayfalar için güvenli alan değişkenleri, modal karartma.

### Uygulama (PWA)
- Telefonda "Ana Ekrana Ekle", bilgisayarda tarayıcının yükle simgesi. Yalnız CSS, JS, yazı tipi ve görseller önbelleğe alınır; panel sayfaları alınmaz.
- Uygulama içinden yapılan girişler açık kalır; tarayıcı girişleri her zamanki gibi davranır.''',
 shots=[('dashboard','Başlangıç'),('dark','Koyu tema'),('command-palette','Komut paleti (Ctrl+K)'),('menu-editor','Role göre menü düzenleyicisi'),('settings','Ayarlar'),(['mobile','mobile-menu'],'Mobil: alt bar ve yan panel')],
 first='''1. **Ayarlar › Yönetim teması** hazır varsayılanlarla açılır; bir şey girmeniz gerekmez.
2. **Menü ve alt bar** sekmesinde bir rol seçin, menüsünü düzenleyin, alt barın dört kısayolunu seçin ve **Kaydet**'e basın.
3. Telefonda paneli açıp **Ana Ekrana Ekle** ile uygulama olarak kurun.''',
 note="> ℹ️ Eklentiyi kaldırmadan önce **Ayarlar › Yönetim teması › Uygulama**'dan PWA'yı kapatın; böylece kurulu uygulamalar service worker'ı siler.",
 dev='''- Menüde sayı rozetleri: `flxa_counts` filtresi. Başlangıç kutucukları: `flxa_dashboard_stats`.
- Geçiş düğmesi isteyen tam ekran uygulamalar: `flxa_app_screens`.
- Ortak liste arayüzü: `flxa_list_form_attrs()`, `flxa_list_per_page()`, `flxa_list_pagination()` ([LISTE-API.md](../../docs/flx-admin/LISTE-API.md)).
- Tam ekran sayfa ve modallar için güvenli alan sözleşmesi: [GUVENLI-ALAN.md](../../docs/flx-admin/GUVENLI-ALAN.md). JS'te `FLXA.safeArea()`, `FLXA.modal(true|false)`.''',
 roadmap=[(1,'Gruplu, role göre sol menü ve menü düzenleyicisi'),(1,'Mobil alt bar, yan panel ve PWA'),(1,'Otomatik açık/koyu tema, komut paleti'),(1,'Tüm FluxWP paketleri için ortak liste süzgeci ve sayfalama'),(1,'FluxWP masaüstü ve mobil uygulamaları için platforma göre menü öğeleri'),(0,'Blok editöründe koyu tema'),(0,'Komut paletinde kayıtlı aramalar')]),
}
