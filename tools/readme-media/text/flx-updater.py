SLUG='flx-updater'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-updater'; WP='6.5'; PHP='7.4'; DOCS='guncelleme-sistemi'; API=False
ARCH='''```
FluxWP/fluxwp (push → GitHub Actions → <slug>-v<version> release + <slug>.zip)
        │ GitHub token (read-only, only on the server)
        ▼
wp-update-server   /v1/check · /v1/download/<slug>/<version>.zip · /admin
        ▲ X-FluxWP-Site-Key (one key per site)
WordPress sites (Flux Updater)
```'''
C={
'en':dict(name='Flux Updater',
 tagline='Private themes and plugins, updated like the ones from WordPress.org.',
 desc='Flux Updater brings our private FluxWP themes and plugins (GitHub releases of a private repository) into the normal WordPress update screens through our own update server. Install, update, roll back and check the site afterwards, with a backup taken before every change.',
 badges=['![Private updates](https://img.shields.io/badge/updates-private%20GitHub%20releases-2fbf8f)'],
 gifalt='Flux Updater tour: connection status, testing the connection, the FluxWP package list with available updates, settings and the standard WordPress Updates screen',
 why='''Our packages live in a private GitHub repository and are not on WordPress.org. Without Flux Updater, every update means downloading a zip and uploading it by hand on every site. With it:

- 🔄 **Normal WordPress updates.** FluxWP packages appear in **Dashboard › Updates** and the plugin list, with release notes, like any other plugin.
- 🔐 **No GitHub token on the site.** Sites talk only to our update server with their own key; the token stays on the server.
- 🛟 **Safe by default.** A backup is taken before every update (Flux Backup), and if the site returns HTTP 500 afterwards the package is rolled back automatically.
- ⏪ **Switch versions.** Install any of the last 20 releases, for example to roll back.
- 📦 **One list for everything:** what is installed, what is new and what can be installed with one click.''',
 features='''### Packages
- **Updates** in the left menu with a pending-count badge; FluxWP packages table with logo, type, installed and latest version and status.
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
 shots=[('packages','FluxWP packages'),('settings','Connection settings'),('wp-updates','In the standard Updates screen'),('mobile','Mobile',True)],
 first='''1. On the update server's admin panel, **Add site** and copy the key it shows once.
2. On the site, **Updates › Settings**: paste the key (the server address is pre-filled) and **Save and check**. The card turns green: **Connected**.
3. Open **Updates** to install or update FluxWP packages.''',
 dev='''How the pieces fit together:

'''+ARCH+'''

- The update server is a separate repository: [FluxWP/updater](https://github.com/FluxWP/updater).
- A new release is published by bumping a package's `Version:` header on `main`; see the repository [README](../../README.md).
- Package logos: filter `flxu_package_icons`, or ship `assets/icon.svg` in the package.''',
 roadmap=[(1,'Private updates through our own server, logos and release notes'),(1,'Bulk updates, version switch and automatic rollback'),(1,'Backups before every update (Flux Backup) and update history'),(0,'Staged roll-out (update one site first, others later)'),(0,'E-mail summary of updates')]),
'tr':dict(name='Flux Updater',
 tagline="Özel tema ve eklentiler, WordPress.org'dakiler gibi güncellenir.",
 desc="Flux Updater özel FluxWP tema ve eklentilerimizi (özel bir GitHub deposunun release'leri) kendi güncelleme sunucumuz üzerinden WordPress'in normal güncelleme ekranlarına getirir. Kurun, güncelleyin, geri alın ve sonrasında siteyi kontrol edin; her değişiklikten önce yedek alınır.",
 badges=['![Özel güncelleme](https://img.shields.io/badge/güncelleme-özel%20GitHub%20release-2fbf8f)'],
 gifalt='Flux Updater turu: bağlantı durumu, bağlantı testi, güncellemesi olan FluxWP paketleri listesi, ayarlar ve WordPress Güncellemeler ekranı',
 why='''Paketlerimiz özel bir GitHub deposunda duruyor, WordPress.org'da yok. Flux Updater olmadan her güncelleme, her sitede zip indirip elle yüklemek demek. Flux Updater ile:

- 🔄 **Normal WordPress güncellemesi.** FluxWP paketleri **Başlangıç › Güncellemeler**'de ve eklenti listesinde, sürüm notlarıyla, diğer eklentiler gibi görünür.
- 🔐 **Sitede GitHub token'ı yok.** Siteler yalnız kendi anahtarlarıyla güncelleme sunucusuna bağlanır; token sunucuda kalır.
- 🛟 **Varsayılan olarak güvenli.** Her güncellemeden önce yedek alınır (Flux Backup); güncellemeden sonra site HTTP 500 verirse paket kendiliğinden eski sürüme döner.
- ⏪ **Sürüm değiştirme.** Son 20 sürümden birini kurun; örneğin geri almak için.
- 📦 **Hepsi tek listede:** kurulu olan, yenisi çıkan ve tek tıkla kurulabilen paketler.''',
 features='''### Paketler
- Sol menüde bekleyen güncelleme rozetli **Güncellemeler**; logo, tür, kurulu ve son sürüm, durum sütunlu FluxWP paketleri tablosu.
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
 shots=[('packages','FluxWP paketleri'),('settings','Bağlantı ayarları'),('wp-updates','WordPress Güncellemeler ekranında'),('mobile','Mobil',True)],
 first='''1. Güncelleme sunucusunun yönetim panelinde **Site ekle** deyin ve bir kez gösterilen anahtarı kopyalayın.
2. Sitede **Güncellemeler › Ayarlar**: anahtarı yapıştırın (sunucu adresi hazır gelir) ve **Kaydet ve kontrol et**'e basın. Kart yeşile döner: **Bağlı**.
3. FluxWP paketlerini kurmak ya da güncellemek için **Güncellemeler**'i açın.''',
 dev='''Parçalar nasıl birleşiyor:

'''+ARCH.replace('WordPress sites (Flux Updater)','WordPress siteleri (Flux Updater)').replace('only on the server','yalnız sunucuda').replace('one key per site','her siteye bir anahtar')+'''

- Güncelleme sunucusu ayrı bir depoda: [FluxWP/updater](https://github.com/FluxWP/updater).
- Yeni sürüm, paketin `Version:` başlığı artırılıp `main`'e gönderilince yayınlanır; bkz. deponun [README](../../README.md) dosyası.
- Paket logoları: `flxu_package_icons` filtresi ya da paketteki `assets/icon.svg`.''',
 roadmap=[(1,'Kendi sunucumuz üzerinden özel güncelleme, logolar ve sürüm notları'),(1,'Toplu güncelleme, sürüm değiştirme ve otomatik geri alma'),(1,'Her güncellemeden önce yedek (Flux Backup) ve güncelleme geçmişi'),(0,'Kademeli yayın (önce bir site, sonra diğerleri)'),(0,'Güncellemelerin e-posta özeti')]),
}
