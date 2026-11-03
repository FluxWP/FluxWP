<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Backup logo" width="120">

# Flux Backup

**A backup before every update, and a clear way back when something breaks.**<br>
Flux Backup takes an automatic backup of the database and the package before every plugin or theme update, runs scheduled full backups, keeps them inside a quota you set and lets you restore just the parts you need. Built for shared hosting: no exec, no mysqldump, small steps that survive timeouts.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Shared hosting](https://img.shields.io/badge/works%20on-shared%20hosting-2fbf8f)
![Restore](https://img.shields.io/badge/restore-selective-333)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Screenshots](#screenshots) · [Installation](#installation) · [For developers](#for-developers) · [Roadmap](#roadmap)

<img src="docs/demo.gif" alt="Flux Backup tour: storage dashboard with history chart and types, the backup list, one full backup with its size breakdown, and the settings" width="900">

</div>

---

## Why Flux Backup?

Hosting backups are daily at best, and restoring one rolls back everything, including today's donations. The risky moment is an update, so Flux Backup protects exactly that moment:

- 🛟 **Automatic pre-update backups** of the database and the updated package, for manual, bulk and automatic updates.
- 🗓️ **Scheduled full backups**, daily or weekly at night.
- 🎯 **Selective restore:** the database, a single plugin or theme folder, or both.
- 📊 **You always know the cost:** quota bar, storage history and space per type.
- 🔒 **Lock** important backups so quota and retention never delete them.
- 🐢 **Shared-hosting friendly:** ~20-second steps continued by WP-Cron; nothing to install on the server.

## Features

### Dashboard
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
- Backups are stored in `wp-content/wp-snapshots/flx-backup/`, closed to the web, with random file names; downloads are admin-only.

## Screenshots

| Dashboard | Backups |
|---|---|
| <img src="docs/images/dashboard-en.jpg" alt="Dashboard" width="440"> | <img src="docs/images/backups-en.jpg" alt="Backups" width="440"> |
| **Backup details and restore** | **Settings** |
| <img src="docs/images/restore-en.jpg" alt="Backup details and restore" width="440"> | <img src="docs/images/settings-en.jpg" alt="Settings" width="440"> |
| **Dark mode with Flux Admin** | **Mobile** |
| <img src="docs/images/dark-en.jpg" alt="Dark mode with Flux Admin" width="440"> | <img src="docs/images/mobile-en.jpg" alt="Mobile" width="260"> |

## Installation

Requirements: WordPress 6.4+, PHP 7.4+. The PHP `zip` extension.

**From a zip**

1. Download `flx-backup.zip` from the latest `flx-backup-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With Flux Setup** (recommended on our sites)

**Setup › Packages** lists every FluxWP package; click **Install** next to *Flux Backup*. Updates arrive the same way.

**First steps**

1. **Backups › Settings**: set the quota, the pre-update scope and, if you like, a scheduled full backup.
2. **Dashboard › Back up now** for the first full backup.
3. From now on every plugin and theme update is preceded by a backup that is kept for 7 days.

> ⚠️ Hostinger does not include the `wp-snapshots` folder in its own backups. Download important backups to your computer, and keep the hosting backups switched on: Flux Backup works next to them, not instead of them. If `DISABLE_WP_CRON` is set, call `wp-cron.php` from a real cron job every 5–15 minutes.

Full setup notes (Turkish): [KURULUM.md](../../docs/yedek-eklentisi/KURULUM.md) · API reference: [API.md](../../docs/yedek-eklentisi/API.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## For developers

Other packages (for example Flux Setup) use the PHP API:

```php
$backup = flxy_backup_create( array(
    'type'    => 'pre_update',
    'paths'   => array( 'wp-content/plugins/flx-forms' ),
    'context' => array( 'source' => 'update' ),
) );
flxy_backup_list( array( 'type' => 'full' ) );
flxy_backup_restore( $id, array( 'db', 'wp-content/plugins/flx-forms' ) );
```

Full reference: [API.md](../../docs/yedek-eklentisi/API.md).

## Roadmap

| | |
|---|---|
| ✅ | Pre-update and scheduled full backups |
| ✅ | Quota, retention, locking and storage dashboard |
| ✅ | Selective restore and uploaded archives |
| ⬜ | Off-site copies (S3-compatible storage, Google Drive) |
| ⬜ | E-mail when a scheduled backup fails |

## License

Flux Backup Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
