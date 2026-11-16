# Flux Files (flx-files): klasik → GraphQL eşlemesi

Paket sürümü: 0.2.0 · Önek: `flxfm_` · Sahip thread: Faz 1 / flx-files · Durum: bitti (PR `claude/project-thread-lerwuf`)

## Özet

- REST uçları: 2 (`/op` 27 işlem, `/upload`) · AJAX: 0 · admin-post işleyici: 1 (`flxfm_file`) · Admin sayfası: 1 · MCP/AI aracı: 13
- İstisna: `flx-files/v1/upload` (parçalı yükleme), `admin-post flxfm_file` (indirme/önizleme). `/op` geçici (klasik ekran + eski FluxApp).
- Servisler: `includes/services/class-flxfm-files-service.php` (`FLXFM_Files_Service`), DTO'lar `class-flxfm-dto.php`. REST `/op` da servisi çağırır, çıktısı bayt bayt aynı.
- Tüm alanlar `flxfm_manage` ister.

## REST uçları (`/flx-files/v1/op`, POST, `{op, …}`)

| op | → GraphQL | Not |
|---|---|---|
| `list`, `tree` | `flxFilesFolder` (sorgu, `first/after`, sunucu 50'şer) | Ekran sayfaları döngüyle toplar (üst sınır 5000) |
| `read` | `flxFilesFile` | |
| `write` | `flxFilesWrite` | `expectedMtime` çakışmada payload `CONFLICT` |
| `mkdir` | `flxFilesFolderCreate` | |
| `rename` | `flxFilesRename` | |
| `move`, `copy` | `flxFilesMove`, `flxFilesCopy` | |
| `duplicate` | `flxFilesDuplicate` | |
| `trash` | `flxFilesTrash` | Çöpe taşır |
| `chmod` | `flxFilesChmod` | |
| `size` | `flxFilesSize` | |
| `search` | `flxFilesSearch` | Ad + içerik |
| `zip`, `unzip` | `flxFilesZip`, `flxFilesUnzip` | |
| `versions`, `version` | `flxFilesVersions`, `flxFilesVersionContent` | |
| `revert` | `flxFilesRevert` | |
| `usage` | `flxFilesDisk` | |
| `trash_list` | `flxFilesTrash` (sorgu) | Operasyon adı `FlxFilesTrashList` |
| `restore`, `purge` | `flxFilesTrashRestore`, `flxFilesTrashPurge` | |
| `log` | `flxFilesLog` | Sayfalı, `via` (PANEL/AI/MCP) |
| `settings` | `flxFilesSettings` (sorgu), `flxFilesSettingsUpdate` | |
| `share` | `flxFilesShare` | |
| `ai_explain`, `ai_edit` | `flxFilesAiExplain`, `flxFilesAiEdit` | AI Flux AI üzerinden |
| (açılış verisi) | `flxFilesConfig` | Ayar + ortam + kök |

## Yükleme

`POST /wp-json/flx-files/v1/upload`: parçalı, devam ettirilebilir, klasör yükleme, kota/sınır denetimi. İSTİSNA (ISTISNALAR.md): ortak `/flx/v1/upload` tek parça `FlxFile` üretir, klasör yapısı ve parça sırası yok.

## admin-post

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxfm_file` | `flxfm_manage` / `flxfm_file` | İSTİSNA | Dosya baytı akışı (indirme, satır içi önizleme, ZIP). SVG ve güvenli olmayan türler sandbox CSP ile. GraphQL JSON döner, bayt akıtamaz. |

## Admin sayfası

| Sayfa | Ne gösterir | İstemci ekranı |
|---|---|---|
| `admin.php?page=flx-files` | Liste/ızgara, ağaç, editör, çöp, etkinlik, ayarlar, sürümler, paylaşım | `packages/modules/src/files/FilesScreen.tsx` (`flxfm-files`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxfm_ui` meta) |

## MCP / AI araçları

13 araç (`flxfm_list` … `flxfm_restore`) `FLXFM_FS` üzerinde kalır: onay akışı ve harici istemci salt-okunur kuralı orada. Servis katmanı ile aynı `FLXFM_FS` çekirdeğini paylaşır, davranış farkı yok.

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni |
|---|---|---|
| `FlxFilesEntry` | path, name, type, size, mode, modified, mime, protected, shared | `mime`: klasörde; `modified`: okunamazsa |
| `FlxFilesFile` | entry, content, mtime, editable, binary | `content`: ikili/büyük dosyada |
| `FlxFilesVersion` | id, modified, size, author | `author`: bilinmiyorsa |
| `FlxFilesLogEntry` | when (`FlxDateTime`), user, action, path, via | `user`: sistem/MCP |
| `FlxFilesSettings` | trashDays, versions, diskQuotaGb, … | — |

## Açık sorular

- FluxApp yerel modülü ertelendi: indirme/önizleme çerez + nonce istiyor; jeton ile doğrulanan dosya uç noktası ayrı iş.
