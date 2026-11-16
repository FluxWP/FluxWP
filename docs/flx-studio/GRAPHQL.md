# Flux Studio GraphQL API

Paket 0.5.0. Uç: `/graphql` (Flux GraphQL). Kayıtlı işlemler: `plugins/flx-studio/graphql/operations/*.graphql`. Tüm alanlar cap ister (fail closed): `flxst_use`, ayarlar için `flxst_manage`. Hatalar payload `errors` içinde.

## Sorgular

- `flxStudioWorkspace`: modeller, klasörler, kişiler, referanslar, şablonlar, ayarlar, yetkiler (tek açılış sorgusu).
- `flxStudioItems(first, after, kind, folderId, favorite, search, owner)`: offset connection; sayfa boyutu sunucuda sınırlanır.
- `flxStudioItem(id)`, `flxStudioStats(days)`, `flxStudioBudget`.

## Mutation'lar

Üretim: `flxStudioGenerate`, `flxStudioPoll`, `flxStudioCancel`, `flxStudioEnhance`, `flxStudioBrainstorm`.
Kayıt: `flxStudioItemUpdate`, `flxStudioItemsDelete`, `flxStudioItemsMove`, `flxStudioItemToLibrary`.
Düzen: `flxStudioFolder{Create,Update,Delete}`, `flxStudioPerson{Create,Rename,Delete}`, `flxStudioRef{Create,Update,Delete}`, `flxStudioTemplate{Create,Delete}`, `flxStudioSettingsUpdate`.

```graphql
mutation { flxStudioGenerate(input: { prompt: "kırmızı bisiklet", model: "..." }) { items { id state } errors { code message } } }
```

## Yükleme

1. `POST /flx/v1/upload` (`context` = `flx-studio/reference` veya `flx-studio/mask`, `file`) → `{uploadId}`.
2. `flxStudioRefCreate` / `flxStudioGenerate` girdisine `uploadId` ver.

## Poll

QUEUED/RUNNING öğeler için istemci `flxStudioPoll` çağırır (4 sn → 8 → 15, sekme gizliyken durur, en çok 8 iş). Üretim `shutdown`'da ertelenmiş başlar.

## React ekranı

`moduleScreens.studio`, wp-admin varsayılanı; klasik: `?flxst_ui=classic`. Anahtar alanı yok, anahtarlar Flux AI ayarlarında.
