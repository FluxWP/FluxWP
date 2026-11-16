# Flux Files GraphQL API

Uç: `/graphql` (Flux GraphQL). Tüm alanlar `flxfm_manage` ister. Yollar WordPress site klasörüne göredir.
İşlemler `plugins/flx-files/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxFilesFolder`, `FlxFilesWrite` …).

## Okuma

```graphql
query {
  flxFilesFolder(path: "wp-content/uploads") {
    path parent
    items(first: 50, sort: NAME, order: ASC) { totalCount pageInfo { hasNextPage endCursor } nodes { path name isDir size } }
  }
  flxFilesFile(path: "wp-content/themes/x/style.css") { hash content }
  flxFilesSearch(path: "wp-content", query: "TODO", content: true) { partial results { path matches { line text } } }
  flxFilesDisk { usedBytes totalBytes fileCount measured scanning }
  flxFilesTrash { days items(first: 50) { nodes { id path deleted { iso } until } } }
  flxFilesLog(first: 50) { nodes { via } }
}
```

Diğer okumalar: `flxFilesConfig`, `flxFilesSettings`, `flxFilesSize`, `flxFilesVersions`, `flxFilesVersionContent`.
Sunucu sayfa boyutu 50; ekran `endCursor` ile döngüde toplar.

## Yazma

```graphql
mutation { flxFilesWrite(input: { path: "a.txt", content: "…", hash: "<açılan sürümün hash'i>" }) { file { hash } errors { code field message } } }
```

| Mutation | Girdi | Not |
|---|---|---|
| `flxFilesWrite` | `path, content, hash?, createOnly?` | Önceki sürümü saklar; `hash` uyuşmazsa `CONFLICT` |
| `flxFilesFolderCreate` | `path` | |
| `flxFilesRename` | `path, name` | |
| `flxFilesMove`, `flxFilesCopy` | `paths, destination, overwrite?` | `items` + `failed` döner |
| `flxFilesDuplicate` | `paths` | |
| `flxFilesTrash` | `paths` | Silme çöpe gider |
| `flxFilesTrashRestore` / `flxFilesTrashPurge` | `ids` / `ids` ya da `all` | |
| `flxFilesChmod` | `path, mode` (üç sekizlik hane) | |
| `flxFilesZip` / `flxFilesUnzip` | `paths, folder?, name?` / `path, destination?` | |
| `flxFilesRevert` | `path, versionId` | |
| `flxFilesShare` | `path, on` | Uploads içindeki klasör; `shortcode` döner |
| `flxFilesSettingsUpdate` | `settings` | |
| `flxFilesAiExplain`, `flxFilesAiEdit` | `path, content?, prompt` | Flux AI gerekir; gizli dosyalar gönderilmez |

Hatalar payload `errors` içinde (`INVALID_INPUT`, `CONFLICT`, `NOT_FOUND`, …); toplu işlemlerde tek tek hatalar `failed` listesinde.

## İstisnalar

- Yükleme: `POST /wp-json/flx-files/v1/upload` (parçalı, devam ettirilebilir, klasör).
- İndirme/önizleme: `admin-post.php?action=flxfm_file` (çerez + nonce).
