SLUG='flx-setup'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-setup'; WP='6.6'; PHP='7.4'; DOCS='guncelleme-sistemi'; API=False
ARCH='''```
FluxWP/fluxwp (push → GitHub Actions → <slug>-v<version> release + <slug>.zip)
        │ GitHub token (read-only, only on the server)
        ▼
wp-update-server   /v1/check · /v1/download/<slug>/<version>.zip · /admin
        ▲ X-FluxWP-Site-Key (one key per site)
WordPress sites (Flux Setup)
```'''
C={
'en':dict(name='Flux Setup',
 tagline='Set up a FluxWP site in three steps, then keep it updated.',
 desc='Flux Setup is the first plugin you install. A short wizard picks the plugins, a preset and a colour and installs everything. After that it brings our FluxWP themes and plugins into the normal WordPress update screens through our own update server, with a backup before every change.',
 badges=['![Private updates](https://img.shields.io/badge/updates-private%20GitHub%20releases-2fbf8f)'],
 gifalt='Flux Setup tour: connection status, testing the connection, the FluxWP package list with available updates, settings and the standard WordPress Updates screen',
 why='''Our packages live in a private GitHub repository and are not on WordPress.org. Without Flux Setup, a new site means uploading a dozen zips by hand and every update means doing it again. With it:

- 🧭 **A three-step setup wizard.** Opens after activation: intro, plugin cards with screenshots, preset and colour, then a live install log. It can be skipped and opened again later.
- 🔄 **Normal WordPress updates.** FluxWP packages appear in **Dashboard › Updates** and the plugin list, with release notes, like any other plugin.
- 🔐 **No GitHub token on the site.** Sites talk only to our update server with their own key; the token stays on the server.
- 🛟 **Safe by default.** A backup is taken before every update (Flux Backup), and if the site returns HTTP 500 afterwards the package is rolled back automatically.
- ⏪ **Switch versions.** Install any of the last 20 releases, for example to roll back.
- 📦 **One list for everything:** what is installed, what is new and what can be installed with one click.''',
 features='''### Setup wizard
- Presentation-style: every step fits one landscape screen, no scrolling; steps slide sideways. Steps: intro › theme and site type (store or nonprofit, pick one) › plugins by category, one screen each › appearance › install › done. **Skip** at any time; open it again from **Setup › Setup wizard**.
- Appearance step in tabs with a fixed live preview on the right: **Identity** (site name, tagline, logo upload), **Preset**, **Colour** (each style shown as a small site) and **Pages** (Blog, About us, Contact, FAQ).
- **What should we start with?** The FluxWP theme and Flux Commerce on their own screen, both recommended, with a quiet **Continue without these**. Then the other plugins with a screenshot and one line each; recommended ones are ticked.
- Appearance: preset (FluxWP demo, installed preset packages or Blank), one of the theme's colour styles with a live preview, and the site name.
- Install: one row per package (finished rows sharp, upcoming ones blurred), a console log and progress. Required plugins such as WooCommerce come from WordPress.org. Failed steps are listed at the end.

### Presets
- One `preset.json` sets up the organisation profile, plugin settings, colours and fonts, header/footer, templates, pages, menus and media.
- Preview, apply and undo; your manual edits are kept; preset migrations are applied one by one. Several presets on one site.
- Export the site as a preset; AI and MCP tools (`preset_schema`, `preset_export`, `preset_patch_preview`, `preset_patch_apply`, `preset_revert`).

### Packages
- **Setup** in the left menu with a pending-count badge; FluxWP packages table with logo, type, installed and latest version and status.
- **Install** packages that aren't on the site yet, **Update** one, several or all of them (WordPress's own bulk updater).
- Version switch: choose one of the last 20 releases from the server. A backup is taken first.
- Optional automatic updates for all FluxWP packages; when it is off, WordPress's per-plugin auto-update setting applies.

### Safety
- Post-update check: the front end and the admin are requested after each update; on HTTP 500 the previous version is restored.
- History: who updated what, from which version to which, the result and a link to the backup. Backups of updates are removed automatically after 1–90 days.

### Connection
- Server address and site key are entered once in the panel (or with `FLX_UPDATE_SERVER` / `FLX_UPDATE_KEY` in `wp-config.php`).
- Connection card: connected / error / not connected, the site's name on the server, package and update counts, and **Test connection**.
- Pre-release versions (`1.3.0-beta.1`) reach only sites set to the beta channel on the server.''',
 shots=[('wizard-base','Setup wizard: what to start with'),('wizard-plugins','Setup wizard: plugins'),('wizard-appearance','Setup wizard: appearance'),('wizard-install','Setup wizard: install'),('packages','FluxWP packages'),('settings','Connection settings'),('wp-updates','In the standard Updates screen'),('mobile','Mobile',True)],
 first='''1. Activate Flux Setup; the setup wizard opens. Pick the plugins, a preset and a colour, then **Install**.
2. Public packages need no key. For private ones: on the update server's admin panel, **Add site**, copy the key, and paste it in **Setup › Settings**.
3. Install or update FluxWP packages any time from **Setup › Packages**.''',
 dev='''How the pieces fit together:

'''+ARCH+'''

- The update server is a separate repository: [FluxWP/updater](https://github.com/FluxWP/updater).
- A new release is published by bumping a package's `Version:` header on `main`; see the repository [README](../../README.md).
- Package logos: filter `flxu_package_icons`, or ship `assets/icon.svg` in the package.
- Wizard filters: `flxk_onboarding_intro`, `flxk_onboarding_core`, `flxk_onboarding_presets`, `flxk_onboarding_cap`.
- Preset format: [docs/flx-setup/FORMAT.md](../../docs/flx-setup/FORMAT.md) · For packages: [API.md](API.md).
- Upgrading from Flux Updater: the old plugin is deactivated on activation; settings (`flxu_*`) are kept.''',
 roadmap=[(1,'Setup wizard with plugin cards, presets and a live install log'),(1,'Private updates through our own server, logos and release notes'),(1,'Bulk updates, version switch and automatic rollback'),(1,'Backups before every update (Flux Backup) and update history'),(0,'Staged roll-out (update one site first, others later)'),(0,'E-mail summary of updates')]),
'tr':dict(name='Flux Setup',
 tagline="FluxWP sitesini üç adımda kurun, sonra güncel tutun.",
 desc="Flux Setup ilk kurulan eklentidir. Kısa bir sihirbazla eklentileri, ön ayarı ve rengi seçersiniz, gerisini o kurar. Sonrasında FluxWP tema ve eklentilerini kendi güncelleme sunucumuz üzerinden WordPress'in normal güncelleme ekranlarına getirir; her değişiklikten önce yedek alınır.",
 badges=['![Özel güncelleme](https://img.shields.io/badge/güncelleme-özel%20GitHub%20release-2fbf8f)'],
 gifalt='Flux Setup turu: bağlantı durumu, bağlantı testi, güncellemesi olan FluxWP paketleri listesi, ayarlar ve WordPress Güncellemeler ekranı',
 why='''Paketlerimiz özel bir GitHub deposunda duruyor, WordPress.org'da yok. Flux Setup olmadan yeni bir site düzinelerce zip'i elle yüklemek, her güncelleme de aynısını tekrar yapmak demek. Flux Setup ile:

- 🧭 **Üç adımlı kurulum sihirbazı.** Etkinleştirince açılır: tanıtım, ekran görüntülü eklenti kartları, ön ayar ve renk, sonra canlı kurulum kaydı. Atlanabilir, sonra yeniden açılır.
- 🔄 **Normal WordPress güncellemesi.** FluxWP paketleri **Başlangıç › Güncellemeler**'de ve eklenti listesinde, sürüm notlarıyla, diğer eklentiler gibi görünür.
- 🔐 **Sitede GitHub token'ı yok.** Siteler yalnız kendi anahtarlarıyla güncelleme sunucusuna bağlanır; token sunucuda kalır.
- 🛟 **Varsayılan olarak güvenli.** Her güncellemeden önce yedek alınır (Flux Backup); güncellemeden sonra site HTTP 500 verirse paket kendiliğinden eski sürüme döner.
- ⏪ **Sürüm değiştirme.** Son 20 sürümden birini kurun; örneğin geri almak için.
- 📦 **Hepsi tek listede:** kurulu olan, yenisi çıkan ve tek tıkla kurulabilen paketler.''',
 features='''### Kurulum sihirbazı
- Sunum düzeni: her adım tek yatay ekrana sığar, kaydırma yok; adımlar yana kayar. Adımlar: tanıtım › tema ve site türü (mağaza ya da dernek, biri seçilir) › kategori kategori eklentiler, her biri ayrı ekran › görünüm › kurulum › bitti. İstediğiniz an **Atla**; sonra **Setup › Kurulum sihirbazı**'ndan yeniden açılır.
- Görünüm adımı sekmeli, sağda sabit canlı önizleme: **Kimlik** (site adı, slogan, logo yükleme), **Ön ayar**, **Renk** (her stil küçük bir site olarak) ve **Sayfalar** (Blog, Hakkımızda, İletişim, SSS).
- **Neyle başlayalım?** FluxWP teması ve Flux Commerce kendi ekranında, ikisi de önerilen; altta sade bir **Seçmeden devam et**. Sonra ekran görüntüsü ve tek satır açıklamalı diğer eklentiler; önerilenler işaretli.
- Görünüm: ön ayar (FluxWP demo, kurulu ön ayar paketleri ya da Boş), canlı önizlemeli tema renk stili ve site adı.
- Kurulum: paket başına bir satır (bitenler net, sıradakiler bulanık), konsol kaydı ve ilerleme. WooCommerce gibi gerekli eklentiler WordPress.org'dan gelir. Hatalı adımlar sonda listelenir.

### Ön ayarlar
- Tek `preset.json` kurum profilini, eklenti ayarlarını, renk ve yazı tiplerini, header/footer'ı, şablonları, sayfaları, menüleri ve medyayı kurar.
- Önizle, uygula, geri al; elle yaptığınız değişiklikler korunur; ön ayar geçişleri tek tek uygulanır. Bir sitede birden fazla ön ayar.
- Siteyi ön ayar olarak dışa aktarma; AI ve MCP araçları (`preset_schema`, `preset_export`, `preset_patch_preview`, `preset_patch_apply`, `preset_revert`).

### Paketler
- Sol menüde bekleyen güncelleme rozetli **Setup**; logo, tür, kurulu ve son sürüm, durum sütunlu FluxWP paketleri tablosu.
- Sitede olmayan paketi **Kur**, bir, birkaç ya da tüm paketi **Güncelle** (WordPress'in kendi toplu güncelleyicisi).
- Sürüm değiştirme: sunucudaki son 20 sürümden birine geçiş. Önce yedek alınır.
- Tüm FluxWP paketleri için isteğe bağlı otomatik güncelleme; kapalıyken WordPress'in eklenti başına otomatik güncelleme ayarı geçerli.

### Güvenlik
- Güncelleme sonrası kontrol: her güncellemeden sonra ön yüz ve panel istenir; HTTP 500 gelirse önceki sürüm geri kurulur.
- Geçmiş: kim neyi hangi sürümden hangisine güncelledi, sonuç ve yedek bağlantısı. Güncelleme yedekleri 1–90 gün sonra kendiliğinden silinir.

### Bağlantı
- Sunucu adresi ve site anahtarı panelden bir kez girilir (ya da `wp-config.php`'de `FLX_UPDATE_SERVER` / `FLX_UPDATE_KEY`).
- Bağlantı kartı: bağlı / hata / bağlı değil, sunucudaki site adı, paket ve güncelleme sayısı, **Bağlantıyı test et**.
- Ön sürümler (`1.3.0-beta.1`) yalnız sunucuda beta kanalına alınmış sitelere gelir.''',
 shots=[('wizard-base','Kurulum sihirbazı: neyle başlayalım'),('wizard-plugins','Kurulum sihirbazı: eklentiler'),('wizard-appearance','Kurulum sihirbazı: görünüm'),('wizard-install','Kurulum sihirbazı: kurulum'),('packages','FluxWP paketleri'),('settings','Bağlantı ayarları'),('wp-updates','WordPress Güncellemeler ekranında'),('mobile','Mobil',True)],
 first='''1. Flux Setup'ı etkinleştirin; kurulum sihirbazı açılır. Eklentileri, ön ayarı ve rengi seçip **Kur**'a basın.
2. Public paketler anahtar istemez. Özel paketler için güncelleme sunucusunun panelinde **Site ekle** deyin, anahtarı **Setup › Ayarlar**'a yapıştırın.
3. FluxWP paketlerini istediğiniz zaman **Setup › Paketler**'den kurun ya da güncelleyin.''',
 dev='''Parçalar nasıl birleşiyor:

'''+ARCH.replace('WordPress sites (Flux Setup)','WordPress siteleri (Flux Setup)').replace('only on the server','yalnız sunucuda').replace('one key per site','her siteye bir anahtar')+'''

- Güncelleme sunucusu ayrı bir depoda: [FluxWP/updater](https://github.com/FluxWP/updater).
- Yeni sürüm, paketin `Version:` başlığı artırılıp `main`'e gönderilince yayınlanır; bkz. deponun [README](../../README.md) dosyası.
- Paket logoları: `flxu_package_icons` filtresi ya da paketteki `assets/icon.svg`.
- Sihirbaz filtreleri: `flxk_onboarding_intro`, `flxk_onboarding_core`, `flxk_onboarding_presets`, `flxk_onboarding_cap`.
- Ön ayar biçimi: [docs/flx-setup/FORMAT.md](../../docs/flx-setup/FORMAT.md) · Paketler için: [API.md](API.md).
- Flux Updater'dan geçiş: etkinleştirince eski eklenti kapatılır; ayarlar (`flxu_*`) korunur.''',
 roadmap=[(1,'Eklenti kartları, ön ayar ve canlı kurulum kaydıyla kurulum sihirbazı'),(1,'Kendi sunucumuz üzerinden özel güncelleme, logolar ve sürüm notları'),(1,'Toplu güncelleme, sürüm değiştirme ve otomatik geri alma'),(1,'Her güncellemeden önce yedek (Flux Backup) ve güncelleme geçmişi'),(0,'Kademeli yayın (önce bir site, sonra diğerleri)'),(0,'Güncellemelerin e-posta özeti')]),
}
