# Flux Backup (flx-backup): klasik → GraphQL eşlemesi

Paket sürümü: 1.5.0 · Önek: `flxy_` · Sahip thread: Geçir Flux Backup'ı GraphQL'e · Durum: bitti (PR `gql/flx-backup-1`)

## Özet

- REST uçları: 3 (`flx-backup/v1/backups`, eski uygulama sürümleri için kalır) · AJAX: 11 · admin-post işleyici: 2 · Admin sayfası: 3 · MCP/AI aracı: 0
- İstisna: yedek dosyası indirme, parçalı yükleme, `flxy_step` belirteç yedeği (ISTISNALAR.md).
- Servis: `includes/services/class-flxy-service.php` (`FLXY_Service`).
- Node tipleri: yok (yedek kimliği dize; `FlxBackup` kimlik alanı `id`).

## AJAX / admin-post işleyicileri

| Action | cap | → GraphQL | Not |
|---|---|---|---|
| `flxy_start` | `manage_options` | `flxBackupStart` | Meşgulse CONFLICT. |
| `flxy_restore_start` | `manage_options` | `flxBackupRestoreStart` | En yüksek yetki. |
| `flxy_delete` | `manage_options` | `flxBackupDelete` | En yüksek yetki. |
| `flxy_lock` | `manage_options` | `flxBackupLock` | |
| `flxy_cancel` | `manage_options` | `flxBackupCancel` | |
| `flxy_save_settings` (admin-post) | `manage_options` | `flxBackupSettingsSave` | |
| `flxy_step` (admin-ajax) | oturum + nonce ya da iş belirteci | `flxBackupStep` | Belirteç yolu istisna: DB geri yüklemesinden sonra oturum kapanır. |
| `flxy_upload_start/chunk/finish/abort` | `manage_options` + nonce | — (istisna) | Dosya akışı. |
| `flxy_download` (admin-post) | `manage_options` + imza | — (istisna) | Zip akışı; GraphQL `downloadUrl` verir. |
| `flxy_seen`, `flxy_tick` | oturum / belirteç | — | İç mekanizma (gösterge, loopback). |

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Sorgular | İstemci ekranı |
|---|---|---|---|
| `flx-backup` | Pano: depolama, sayılar, geçmiş grafiği, türe göre, son yedekler, olaylar | `FlxBackupDashboard` | `packages/modules/src/backup/Dashboard.tsx` |
| `flx-backup-list` | Liste, süzgeç, toplu silme, kilit, yükleme; `view=<id>` ayrıntı + boyut dökümü | `FlxBackupList`, `FlxBackupDetail` | `List.tsx` |
| `flx-backup-settings` | Ayarlar | `FlxBackupSettingsScreen` | `Settings.tsx` |

Diyaloglar (şimdi yedekle, geri yükle, silme onayı, iş ilerlemesi) `BackupScreen.tsx` içinde.

## Tipler

| Tip | Not |
|---|---|
| `FlxBackup` (+connection) | `restore` ve `breakdown` tembel çözülür (zip okur). |
| `FlxBackupOverview`, `FlxBackupHistory`, `FlxBackupCounts`, `FlxBackupSettings`, `FlxBackupHealth`, `FlxBackupJob` | Tek kayıtlı, kök alan başına bir sorgu. |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-backup:backups` | liste, ayrıntı, sayılar, pano | Tüm mutation'lar (`FLXY_Service::changed()`) |

## Açık sorular

- Klasik görünüm ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
