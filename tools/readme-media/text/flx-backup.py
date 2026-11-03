SLUG='flx-backup'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-backup'; WP='6.4'; PHP='7.4'; DOCS='yedek-eklentisi'; API=True
C={
'en':dict(name='Flux Backup',
 tagline='A backup before every update, and a clear way back when something breaks.',
 desc='Flux Backup takes an automatic backup of the database and the package before every plugin or theme update, runs scheduled full backups, keeps them inside a quota you set and lets you restore just the parts you need. Built for shared hosting: no exec, no mysqldump, small steps that survive timeouts.',
 badges=['![Shared hosting](https://img.shields.io/badge/works%20on-shared%20hosting-2fbf8f)','![Restore](https://img.shields.io/badge/restore-selective-333)'],
 gifalt='Flux Backup tour: storage dashboard with history chart and types, the backup list, one full backup with its size breakdown, and the settings',
 why='''Hosting backups are daily at best, and restoring one rolls back everything, including today's donations. The risky moment is an update, so Flux Backup protects exactly that moment:

- 🛟 **Automatic pre-update backups** of the database and the updated package, for manual, bulk and automatic updates.
- 🗓️ **Scheduled full backups**, daily or weekly at night.
- 🎯 **Selective restore:** the database, a single plugin or theme folder, or both.
- 📊 **You always know the cost:** quota bar, storage history and space per type.
- 🔒 **Lock** important backups so quota and retention never delete them.
- 🐢 **Shared-hosting friendly:** ~20-second steps continued by WP-Cron; nothing to install on the server.''',
 features='''### Dashboard
- Storage against quota, number of backups and locked ones, last successful backup and the next scheduled one.
- Storage history chart (30/90/180 days) by type, a breakdown by type and recent backups and events.

### Backups
- List with filters by type (full, pre-update, database, files, manual), download, restore, lock, delete and bulk delete.
- Backup details: size breakdown (database, plugins, themes, uploads, other), original and archived size, WordPress version and retention.
- Upload a backup archive and restore from it.

### Settings
- Quota (default 10 GB); when it fills up the oldest unlocked backup goes first.
- Pre-update scope: all packages, only FluxWP packages, or off; retention in days.
- Scheduled full backup: frequency, time and how many to keep; optionally WordPress core files too.
- Excluded paths (caches, large media folders…).

### Storage
- Backups are stored in `wp-content/wp-snapshots/flx-backup/`, closed to the web, with random file names; downloads are admin-only.''',
 shots=[('dashboard','Dashboard'),('backups','Backups'),('restore','Backup details and restore'),('settings','Settings'),('dark','Dark mode with Flux Admin'),('mobile','Mobile',True)],
 req='The PHP `zip` extension.',
 first='''1. **Backups › Settings**: set the quota, the pre-update scope and, if you like, a scheduled full backup.
2. **Dashboard › Back up now** for the first full backup.
3. From now on every plugin and theme update is preceded by a backup that is kept for 7 days.''',
 note='> ⚠️ Hostinger does not include the `wp-snapshots` folder in its own backups. Download important backups to your computer, and keep the hosting backups switched on: Flux Backup works next to them, not instead of them. If `DISABLE_WP_CRON` is set, call `wp-cron.php` from a real cron job every 5–15 minutes.',
 dev='''Other packages (for example FluxWP Panel) use the PHP API:

```php
$backup = flxy_backup_create( array(
    'type'    => 'pre_update',
    'paths'   => array( 'wp-content/plugins/flx-forms' ),
    'context' => array( 'source' => 'update' ),
) );
flxy_backup_list( array( 'type' => 'full' ) );
flxy_backup_restore( $id, array( 'db', 'wp-content/plugins/flx-forms' ) );
```

Full reference: [API.md](../../docs/yedek-eklentisi/API.md).''',
 roadmap=[(1,'Pre-update and scheduled full backups'),(1,'Quota, retention, locking and storage dashboard'),(1,'Selective restore and uploaded archives'),(0,'Off-site copies (S3-compatible storage, Google Drive)'),(0,'E-mail when a scheduled backup fails')]),
'tr':dict(name='Flux Backup',
 tagline='Her güncellemeden önce yedek; bir şey bozulursa net bir dönüş yolu.',
 desc='Flux Backup her eklenti ya da tema güncellemesinden önce veritabanını ve paketi kendiliğinden yedekler, zamanlanmış tam yedekler alır, bunları belirlediğiniz kotada tutar ve yalnız gereken parçayı geri yüklemenizi sağlar. Paylaşımlı sunucu için yazıldı: exec yok, mysqldump yok; zaman aşımına dayanıklı küçük adımlar.',
 badges=['![Paylaşımlı sunucu](https://img.shields.io/badge/çalışır-paylaşımlı%20sunucuda-2fbf8f)','![Geri yükleme](https://img.shields.io/badge/geri%20yükleme-seçerek-333)'],
 gifalt='Flux Backup turu: geçmiş grafiği ve türleriyle depolama paneli, yedek listesi, boyut dağılımıyla bir tam yedek ve ayarlar',
 why='''Hosting yedekleri en iyi ihtimalle günlüktür ve geri yüklemek her şeyi, bugünkü bağışlar dahil, geri alır. Riskli an güncellemedir; Flux Backup tam o anı korur:

- 🛟 **Güncelleme öncesi otomatik yedek:** elle, toplu ve otomatik güncellemelerde veritabanı ve güncellenen paket.
- 🗓️ **Zamanlanmış tam yedek:** günlük ya da haftalık, gece.
- 🎯 **Seçerek geri yükleme:** veritabanı, tek bir eklenti/tema klasörü ya da ikisi birden.
- 📊 **Maliyeti hep görürsünüz:** kota çubuğu, depolama geçmişi ve türlere göre alan.
- 🔒 Önemli yedekleri **kilitleyin**; kota ve saklama süresi onları silmez.
- 🐢 **Paylaşımlı sunucu dostu:** ~20 saniyelik adımlar, WP-Cron ile sürer; sunucuya bir şey kurmak gerekmez.''',
 features='''### Panel
- Kotaya göre kullanılan alan, yedek ve kilitli yedek sayısı, son başarılı yedek ve sıradaki zamanlanmış yedek.
- Türe göre depolama geçmişi grafiği (30/90/180 gün), türlere göre dağılım, son yedekler ve olaylar.

### Yedekler
- Türe göre süzülen liste (tam, güncelleme öncesi, veritabanı, dosyalar, elle); indir, geri yükle, kilitle, sil, toplu sil.
- Yedek ayrıntısı: boyut dağılımı (veritabanı, eklentiler, temalar, yüklemeler, diğer), asıl ve arşiv boyutu, WordPress sürümü ve saklama süresi.
- Yedek arşivi yükleyip ondan geri yükleme.

### Ayarlar
- Kota (varsayılan 10 GB); dolunca önce en eski kilitsiz yedek silinir.
- Güncelleme öncesi kapsam: tüm paketler, yalnız FluxWP paketleri ya da kapalı; saklama süresi (gün).
- Zamanlanmış tam yedek: sıklık, saat ve kaç tane saklanacağı; isterseniz WordPress çekirdek dosyaları da.
- Hariç tutulan yollar (önbellekler, büyük medya klasörleri…).

### Depolama
- Yedekler `wp-content/wp-snapshots/flx-backup/` klasöründe, web'e kapalı ve rastgele adlarla durur; indirme yalnız yöneticiye açık.''',
 shots=[('dashboard','Panel'),('backups','Yedekler'),('restore','Yedek ayrıntısı ve geri yükleme'),('settings','Ayarlar'),('dark','Flux Admin ile koyu tema'),('mobile','Mobil',True)],
 req='PHP `zip` uzantısı.',
 first='''1. **Yedekler › Ayarlar**: kotayı, güncelleme öncesi kapsamı ve isterseniz zamanlanmış tam yedeği ayarlayın.
2. İlk tam yedek için **Panel › Şimdi yedek al**.
3. Bundan sonra her eklenti ve tema güncellemesinden önce yedek alınır ve 7 gün saklanır.''',
 note="> ⚠️ Hostinger `wp-snapshots` klasörünü kendi yedeklerine almaz. Önemli yedekleri bilgisayarınıza indirin ve hosting yedeklerini açık tutun: Flux Backup onların yerine değil, yanında çalışır. `DISABLE_WP_CRON` tanımlıysa `wp-cron.php`'yi gerçek bir cron ile 5–15 dakikada bir çağırın.",
 dev='''Diğer paketler (ör. FluxWP Panel) PHP API'sini kullanır:

```php
$backup = flxy_backup_create( array(
    'type'    => 'pre_update',
    'paths'   => array( 'wp-content/plugins/flx-forms' ),
    'context' => array( 'source' => 'update' ),
) );
flxy_backup_list( array( 'type' => 'full' ) );
flxy_backup_restore( $id, array( 'db', 'wp-content/plugins/flx-forms' ) );
```

Tam başvuru: [API.md](../../docs/yedek-eklentisi/API.md).''',
 roadmap=[(1,'Güncelleme öncesi ve zamanlanmış tam yedekler'),(1,'Kota, saklama süresi, kilit ve depolama paneli'),(1,'Seçerek geri yükleme ve yüklenen arşivler'),(0,'Site dışı kopya (S3 uyumlu depolama, Google Drive)'),(0,'Zamanlanmış yedek başarısız olunca e-posta')]),
}
