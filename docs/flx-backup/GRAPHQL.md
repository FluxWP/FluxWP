# Flux Backup GraphQL API

Uç: `/graphql` (Flux GraphQL). Tüm alanlar `manage_options` ister; geri yükleme ve silme mutation'ları en yüksek yetkiyi ister (`manage_options`, çok siteli kurulumda ayrıca süper yönetici).
İşlemler `plugins/flx-backup/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxBackupDashboard`, `FlxBackupStart` …).

## Okuma

```graphql
query {
  flxBackups(first: 20, type: FULL) { nodes { id title type status sizeLabel created { label relative } locked keepLabel downloadUrl } pageInfo { hasNextPage endCursor } totalCount }
  flxBackup(id: "20261116-062813-sb2r") { title packages { label } restore { database groups { key label items { path label } } } breakdown { raw zip categories { key label raw zip count items { name raw zip } } } }
  flxBackupCounts { all usedLabel types { type label count } }
  flxBackupOverview { usedLabel quotaLabel percent level last { title } nextRun { label } byType { label bytesLabel share } recent { title } events { message } }
  flxBackupHistory { days { key values totalLabel } series { key name color } ranges { days max ticks { value label } } quota }
  flxBackupSettings { quotaGb preScope schedule scheduleHour scheduleKeep includeCore exclude safetyRestore deleteOnUninstall }
  flxBackupHealth { zipAvailable writable folder chunkSize lastRestore }
  flxBackupJob { status kind percent text elapsedLabel etaLabel }
}
```

`breakdown` zip'i okur; ilk açılışta zaman alır, yalnız ayrıntı sayfasında isteyin.

## İş akışı (adım adım)

Yedek ve geri yükleme tek HTTP isteğine sığmaz (PHP ~60 sn'de kesilir). Mutation işi **başlatır**, durum sorguyla okunur:

1. `flxBackupStart` / `flxBackupRestoreStart` → `job` (RUNNING). Başka iş çalışıyorsa `errors[CONFLICT]`.
2. İstemci yaklaşık 1,5 sn'de bir `flxBackupStep` çağırır (bir zaman bütçesi, ~8 sn ilerletir) ya da yalnız `flxBackupJob` okur.
3. `status` DONE/FAILED olunca bitti. `flxBackupCancel` iptal eder (süren adım önce biter: `pending`).

Eski akış (sunucu döngüsü, loopback, cron, admin çubuğu göstergesi) aynen çalışır; GraphQL yalnız onu sürer.
Veritabanı geri yüklemesinden sonra oturum kapanabilir: ekran bu durumda `job.token` ile `admin-ajax flxy_step` üzerinden sürer (ISTISNALAR).

## Yazma

| Mutation | Girdi | Dönüş | Not |
|---|---|---|---|
| `flxBackupStart` | `type` (FULL/DB/FILES), `label`, `includeCore`, `lock` | `job`, `errors` | |
| `flxBackupRestoreStart` | `id`, `database`, `paths`, `safety` | `job`, `errors` | En yüksek yetki. Boş seçim `INVALID_INPUT` (`paths`). |
| `flxBackupStep` | — | `job` | İş yoksa bir şey yapmaz. |
| `flxBackupCancel` | — | `job`, `pending` | |
| `flxBackupDelete` | `ids` | `deletedIds`, `counts` | En yüksek yetki; çalışan yedek önce iptal edilir. |
| `flxBackupLock` | `id`, `locked` | `backup` | |
| `flxBackupSettingsSave` | bırakılan alan değişmez | `settings`, `errors` | Aralık dışı değer sınırlanır; zamanlama yeniden kurulur. |

Hatalar payload `errors` içinde: `INVALID_INPUT` (`field`), `CONFLICT`, `NOT_FOUND`.

## GraphQL dışında kalanlar

Yedek dosyası indirme (`admin-post flxy_download`, imzalı bağlantı `downloadUrl`), parçalı yükleme (`admin-ajax flxy_upload_*`) ve `flxy_step` belirteç yedeği: dosya akışı ve oturumsuz sürme için, `docs/graphql/ISTISNALAR.md`.

## PHP

Servis: `FLXY_Service::overview()`, `backups()`, `backup()`, `breakdown()`, `history()`, `settings()`, `save_settings()`, `start_backup()`, `start_restore()`, `step()`, `cancel()`, `delete()`, `set_lock()`. Klasik admin işleyicileri (`flxy_start`, `flxy_restore_start`, `flxy_delete`, `flxy_lock`, `flxy_cancel`, `flxy_save_settings`) bu servise delege eder; REST `flx-backup/v1/backups` değişmedi.
