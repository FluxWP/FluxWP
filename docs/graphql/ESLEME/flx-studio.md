# Flux Studio (flx-studio): klasik → GraphQL eşlemesi

Paket sürümü: 0.5.0 · Önek: `flxst_` · Sahip thread: Faz 1 / flx-studio · Durum: bitti (PR `gql/flx-studio-1`)

## Özet

- REST uçları (`flx-studio/v1`): 27, hepsi durur ve `FLXST_Service`'e delege eder · Admin sayfası: 1 · MCP/AI aracı: değişmedi.
- Servis: `includes/services/class-flxst-service.php`, DTO `class-flxst-dto.php`. Şema `includes/graphql/schema.php`.
- Yetki: `flxst_use` (okuma/üretim), `flxst_manage` (ayarlar), yükleme `flxst_use` + `upload_files`.

## REST → GraphQL

| REST | GraphQL |
|---|---|
| GET `/boot` | `flxStudioWorkspace` |
| GET `/items`, `/items/{id}` | `flxStudioItems` (offset connection), `flxStudioItem` |
| POST `/items/{id}` | `flxStudioItemUpdate` |
| POST `/items/delete`, `/items/move` | `flxStudioItemsDelete`, `flxStudioItemsMove` |
| POST `/items/{id}/library` | `flxStudioItemToLibrary` |
| POST `/generate` | `flxStudioGenerate` |
| GET `/poll` | `flxStudioPoll` (mutation: iş durumunu ilerletir) |
| POST `/jobs/{job}/cancel` | `flxStudioCancel` |
| POST `/enhance`, `/brainstorm` | `flxStudioEnhance`, `flxStudioBrainstorm` |
| POST `/folders`, `/folders/{id}`, `/folders/{id}/delete` | `flxStudioFolderCreate/Update/Delete` |
| POST `/people`, `/people/{id}`, `/people/{id}/delete` | `flxStudioPersonCreate/Rename/Delete` |
| GET/POST `/refs`, `/refs/{id}`, `/refs/{id}/delete` | `flxStudioWorkspace` (refs), `flxStudioRefCreate/Update/Delete` |
| POST `/templates`, `/templates/{id}/delete` | `flxStudioTemplateCreate/Delete` |
| GET `/stats` | `flxStudioStats` |
| POST `/settings` | `flxStudioSettingsUpdate` |
| (bütçe) | `flxStudioBudget` |

## İstisnalar

- Dosya yükleme GraphQL'de değil: ortak `/flx/v1/upload` (bağlamlar `flx-studio/reference`, `flx-studio/mask`), dönen `uploadId` mutation'a verilir. GraphQL yüklemesi yalnız görsel kabul eder; klasik REST ayrıca video/ses referansı kabul eder.
- Mobil manifest REST'te kalır.
