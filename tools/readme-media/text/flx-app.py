SLUG='flx-app'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-app'; WP='6.4'; PHP='7.4'; DOCS='flx-app'; API=True
C={
'en':dict(name='Flux App',
 tagline='Sign-in and API layer for the FluxWP desktop and mobile app, and OAuth for AI assistants.',
 desc='Flux App lets the FluxWP desktop and mobile app (and MCP clients such as Claude) sign in to the site with "Log in": OAuth 2.0 with PKCE, personal API keys, and one-time codes for donors. It also tells the app what to show: a role-based menu, native screens registered by the other FluxWP plugins, counters and shortcuts, all managed from one Application page.',
 badges=['![OAuth](https://img.shields.io/badge/OAuth%202.0-PKCE%20%C2%B7%20RFC%208414%2F9728-0a7cff)','![Platforms](https://img.shields.io/badge/app-macOS%20%C2%B7%20Windows%20%C2%B7%20iOS%20%C2%B7%20Android-2fbf8f)'],
 gifalt='Flux App tour: general settings, native modules, the menu per platform, all active sessions and the consent screen',
 why='''A phone or desktop app for a WordPress site usually means application passwords pasted by hand, or a separate backend. Flux App keeps WordPress the single source:

- 🔐 **Real sign-in.** The app opens the site's own login and a clear consent card; no passwords stored in the app, tokens can be revoked one by one.
- 🧭 **The right menu for each person.** The app receives the admin menu for the user's role and platform, so staff, managers and donors each see their own.
- 📱 **Native where it matters.** Plugins like Flux Call Center, Flux Donation and Flux Studio register native screens; everything else opens as an embedded admin page without the admin chrome.
- 🤖 **AI assistants included.** MCP clients discover the OAuth server and register themselves, so Claude can connect to Flux SEO with "Log in" instead of a copied key.''',
 features='''### Sign-in
- OAuth 2.0 authorisation code + PKCE (S256) with rotating refresh tokens and revoke; built-in client "Flux Desktop" (`flxmasaustu://` and loopback redirects).
- RFC 8414 / 9728 metadata and dynamic client registration for MCP clients; the Flux SEO endpoint accepts these tokens.
- Personal API keys (`Bearer flxak_…`), with or without expiry.
- **Donor sign-in** with a one-time code by WhatsApp (Flux Call Center) or e-mail; a customer account is created for donors who have orders.
- One-time admin session from the app (`/session`), and `X-FluxWP-Token` fallback for hosts that drop the `Authorization` header.

### What the app shows
- `/flxapp/v1/me`: user, role-based admin menu, native modules, counters and the mobile bottom-bar shortcuts from Flux Admin.
- **Modules:** native screens registered with `flxapp_modules`, each one can be switched off.
- **Menu per platform:** Flux Admin's menu editor marks items for Web, Mobile and Desktop; the app sends `X-FluxWP-Platform` and gets only its own.
- Admin pages opened in the app hide the top bar, side menu and footer.

### Application page
- Tabs: General, Modules, Menu, Connected apps, All access; people see and revoke their own sessions and keys under Users › App access.''',
 shots=[('general','General settings'),('modules','Native modules'),('menu','Menu per role and platform'),('apps','Connected apps'),('access','All sessions and keys'),('consent','Consent screen'),('dark','Dark mode with Flux Admin'),('mobile','Consent on a phone',True)],
 req='The FluxWP desktop/mobile app or an MCP client. Optional: Flux Admin (menu per platform, bottom bar), Flux Call Center (codes by WhatsApp), Flux Roles.',
 first='''1. Activate Flux App; **Application** appears in the admin menu. The defaults work: access 60 min, session 60 days, MCP self-registration on.
2. In the app, enter the site address and press **Log in**: the site's login and consent screen open, press **Allow**.
3. For Claude and other MCP clients, give them `https://your-site/wp-json/flx-mcp/v1/mcp` and sign in, or create an API key.''',
 note='> ℹ️ Resetting a password or deleting a user closes all of that person\'s app sessions and keys. If LiteSpeed caches the `.well-known/oauth-*` URLs, purge the cache.',
 dev='''- Register a native screen: `add_filter( 'flxapp_modules', … )` with `title`, `menu`, `api`, `icon`, `version`.
- Endpoints `/flxapp/v1/me`, `/counts`, `/session`, `/logout`, `/otp/start`, `/otp/verify`; see [API.md](../../docs/flx-app/API.md).''',
 roadmap=[(1,'OAuth 2.0 + PKCE, API keys and MCP discovery'),(1,'Donor sign-in with one-time codes'),(1,'Role-based menu and native modules'),(1,'Menu per platform and the Application page'),(0,'Push notifications'),(0,'Passkey sign-in')]),
'tr':dict(name='Flux App',
 tagline='FluxWP masaüstü ve mobil uygulaması için giriş ve API katmanı; yapay zekâ asistanları için OAuth.',
 desc="Flux App, FluxWP masaüstü ve mobil uygulamasının (ve Claude gibi MCP istemcilerinin) siteye \"Giriş yap\" ile bağlanmasını sağlar: PKCE'li OAuth 2.0, kişisel API anahtarları ve bağışçılar için tek kullanımlık kod. Uygulamaya ne göstereceğini de söyler: rol bazlı menü, diğer FluxWP eklentilerinin kaydettiği yerel ekranlar, sayaçlar ve kısayollar; hepsi tek Uygulama sayfasından yönetilir.",
 badges=['![OAuth](https://img.shields.io/badge/OAuth%202.0-PKCE%20%C2%B7%20RFC%208414%2F9728-0a7cff)','![Platformlar](https://img.shields.io/badge/uygulama-macOS%20%C2%B7%20Windows%20%C2%B7%20iOS%20%C2%B7%20Android-2fbf8f)'],
 gifalt='Flux App turu: genel ayarlar, yerel modüller, platforma göre menü, tüm oturumlar ve izin ekranı',
 why='''Bir WordPress sitesi için telefon ya da masaüstü uygulaması genelde elle yapıştırılan uygulama parolaları ya da ayrı bir arka uç demektir. Flux App tek kaynağı WordPress'te tutar:

- 🔐 **Gerçek giriş.** Uygulama sitenin kendi giriş ekranını ve açık bir izin kartını açar; uygulamada parola saklanmaz, belirteçler tek tek iptal edilir.
- 🧭 **Herkese doğru menü.** Uygulama kullanıcının rolüne ve platformuna göre panel menüsünü alır; personel, idareci ve bağışçı kendi ekranını görür.
- 📱 **Gereken yerde yerel ekran.** Flux Chat, Flux Donate ve Flux Studio gibi eklentiler yerel ekran kaydeder; geri kalan her şey panel çerçevesi olmadan gömülü sayfa olarak açılır.
- 🤖 **Yapay zekâ asistanları dahil.** MCP istemcileri OAuth sunucusunu bulur ve kendini kaydeder; Claude, Flux SEO'ya kopyalanmış anahtar yerine "Giriş yap" ile bağlanır.''',
 features='''### Giriş
- Dönen yenileme belirteçli ve iptalli OAuth 2.0 yetkilendirme kodu + PKCE (S256); yerleşik "Flux Masaüstü" istemcisi (`flxmasaustu://` ve loopback yönlendirme).
- MCP istemcileri için RFC 8414 / 9728 metadata ve dinamik istemci kaydı; Flux SEO uç noktası bu belirteçleri kabul eder.
- Süreli ya da süresiz kişisel API anahtarları (`Bearer flxak_…`).
- **Bağışçı girişi:** WhatsApp (Flux Chat) ya da e-postayla tek kullanımlık kod; siparişi olan bağışçıya müşteri hesabı açılır.
- Uygulamadan tek seferlik panel oturumu (`/session`); `Authorization` başlığını düşüren sunucular için `X-FluxWP-Token`.

### Uygulama ne gösterir
- `/flxapp/v1/me`: kullanıcı, rol bazlı panel menüsü, yerel modüller, sayaçlar ve Flux Admin'deki mobil alt bar kısayolları.
- **Modüller:** `flxapp_modules` ile kaydedilen yerel ekranlar; her biri kapatılabilir.
- **Platforma göre menü:** Flux Admin menü düzenleyicisinde öğeler Web, Mobil ve Masaüstü için işaretlenir; uygulama `X-FluxWP-Platform` gönderir, yalnız kendisininkini alır.
- Uygulamada açılan panel sayfalarında üst çubuk, sol menü ve altlık gizlenir.

### Uygulama sayfası
- Sekmeler: Genel, Modüller, Menü, Bağlı uygulamalar, Tüm erişimler; kişiler kendi oturum ve anahtarlarını Kullanıcılar › Uygulama erişimi'nde görür ve kaldırır.''',
 shots=[('general','Genel ayarlar'),('modules','Yerel modüller'),('menu','Rol ve platforma göre menü'),('apps','Bağlı uygulamalar'),('access','Tüm oturumlar ve anahtarlar'),('consent','İzin ekranı'),('dark','Flux Admin ile koyu tema'),('mobile','Telefonda izin ekranı',True)],
 req="FluxWP masaüstü/mobil uygulaması ya da bir MCP istemcisi. İsteğe bağlı: Flux Admin (platforma göre menü, alt bar), Flux Chat (WhatsApp ile kod), Flux Roles.",
 first='''1. Flux App'yı etkinleştirin; panel menüsünde **Uygulama** çıkar. Varsayılanlar yeterli: erişim 60 dk, oturum 60 gün, MCP kendini kaydedebilir.
2. Uygulamada site adresini girip **Giriş yap**'a basın: sitenin giriş ve izin ekranı açılır, **İzin ver** deyin.
3. Claude ve diğer MCP istemcilerine `https://siteniz/wp-json/flx-mcp/v1/mcp` adresini verip giriş yapın ya da bir API anahtarı oluşturun.''',
 note="> ℹ️ Parola sıfırlanınca ya da kullanıcı silinince o kişinin tüm uygulama oturumları ve anahtarları kapanır. LiteSpeed `.well-known/oauth-*` adreslerini önbelleğe alırsa önbelleği temizleyin.",
 dev='''- Yerel ekran kaydı: `title`, `menu`, `api`, `icon`, `version` ile `add_filter( 'flxapp_modules', … )`.
- Uçlar `/flxapp/v1/me`, `/counts`, `/session`, `/logout`, `/otp/start`, `/otp/verify`; bkz. [API.md](../../docs/flx-app/API.md).''',
 roadmap=[(1,'OAuth 2.0 + PKCE, API anahtarları ve MCP keşfi'),(1,'Tek kullanımlık kodla bağışçı girişi'),(1,'Rol bazlı menü ve yerel modüller'),(1,'Platforma göre menü ve Uygulama sayfası'),(0,'Anlık bildirimler'),(0,'Passkey ile giriş')]),
}
