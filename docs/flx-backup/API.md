# Flux Backup — uygulama REST

Yetki: `manage_options`. Önbellek yok.

| Uç | Ne |
|---|---|
| `GET /wp-json/flx-backup/v1/backups` | `{items, total, page, pages}`. `type`, `status`, `page`, `per_page`. Sunucu dosya yolu dönmez. |
| `POST /wp-json/flx-backup/v1/backups` | `{type: full\|db\|files\|manual, label?}`. Arka planda başlar (`201`). |
| `GET /wp-json/flx-backup/v1/backups/<id>` | Durum, boyut, ilerleme (`percent`, `progress`). |
| `POST /wp-json/flx-backup/v1/backups/<id>/delete` | Kaydı siler; süren işse iptal ister. |

Geri yükleme panelde (`admin.php?page=flx-backup`) kalır.


## Portable background execution

See the [shared worker contract](../background-workers/API.md) for automatic Docker worker detection, heartbeat expiry and ordinary WordPress fallback. Asynchronous backups prefer the worker. Restores and synchronous pre-update snapshots retain their existing execution path.
