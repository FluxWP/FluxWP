# Flux Social GraphQL API

Uç: `/graphql` (Flux GraphQL). Her alan bir yetki ister: okuma/gönderi işleri `flxsm_post`, yayın ve onay `flxsm_publish`,
hesap ve ayar `flxsm_manage` (hepsi `manage_options` sahibine de açık). Ortam alanları `upload_files`.
İşlemler `plugins/flx-social/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxSocialBoot`, `FlxSocialPostCreate` …).
Belirteç, uygulama anahtarı ve hesap meta'sı hiçbir alanda dönmez.

## Okuma

```graphql
query {
  flxSocialConfig { canPublish canManage canUpload ai studio timezone tzOffset networks { id label color caps { maxText options } } }
  flxSocialAccounts { id databaseId provider name username status caps { maxText image video } }
  flxSocialCounts { pending scheduled failed due }
  flxSocialPosts(first: 20, status: [SCHEDULED, PENDING], search: "kampanya") {
    nodes { id databaseId status content scheduledAt { timestamp label } targets { account status error } }
    pageInfo { hasNextPage endCursor } totalCount
  }
  flxSocialStats(days: 30) { totals { published engagement views followers } byDay { day networks { provider count } } }
}
```

Diğer sorgular: `flxSocialPost(id)`, `flxSocialSettings`, `flxSocialApps`, `flxSocialLog`, `flxSocialWhatsAppTargets`,
`flxSocialConnectUrl(for)`, `flxSocialMedia(ids, search, page)`, `flxSocialStudioMedia`.

## Yazma

```graphql
mutation {
  flxSocialPostCreate(input: {
    content: "Yeni ürünümüz yayında", scheduledAt: 1795000000, action: SCHEDULE,
    targets: [{ accountId: 3, options: { firstComment: "Bağlantı yorumda" } }, { accountId: 5, content: "Kısa metin" }]
  }) { post { id status } errors { code field message } }
}
```

| Mutation | Girdi (özet) | Not |
|---|---|---|
| `flxSocialPostCreate`, `…PostUpdate` | metin, ortam, etiket, `scheduledAt` (Unix), `repeatDays`, `targets`, `action` | Paylaşım yapmış hesaplara dokunulmaz. Yayın yetkisi yoksa `SCHEDULE`/`NOW` onaya düşer |
| `flxSocialPostAct` | `id`, `action` (APPROVE, REJECT, CANCEL, DRAFT, NOW, SCHEDULE, SUBMIT), `note` | |
| `flxSocialPostDelete` | `id`, `remote` | `remote` ağda da siler |
| `flxSocialPostBulkAct` | `ids`, `action` | `done` + `issues` |
| `flxSocialPostDuplicate` | `id` | taslak kopya |
| `flxSocialTargetRetry`, `…RemoteEdit`, `…RemoteDelete` | hedef `id` | |
| `flxSocialAccountUpdate`, `…Reorder`, `…Delete`, `…Refresh` | hesap `id` | |
| `flxSocialWhatsAppAdd` | `items` | |
| `flxSocialSettingsUpdate` | verilmeyen alan olduğu gibi kalır | |
| `flxSocialAppUpdate` | `id`, `values: [{ key, value }]` | boş gizli değer eskisini korur, `__clear__` siler |
| `flxSocialTickKeyReveal` | `regenerate` | dış zamanlayıcının tam adresi (yalnız yönetici) |
| `flxSocialTickRun` | | zamanlayıcıyı bir an çalıştırır; açık panel zamanı gelen gönderi varken çağırır |
| `flxSocialStatsRefresh`, `flxSocialAiWrite`, `flxSocialMediaImport`, `flxSocialMediaAdd` | | |

Hatalar payload `errors` içinde: `INVALID_INPUT` (`field` ile), `CONFLICT`; `FORBIDDEN`, `NOT_FOUND`, `UNAUTHENTICATED` üst düzey.

## REST'te kalanlar

OAuth (`/wp-json/flx-social/v1/oauth/*`; başlangıç adresini `flxSocialConnectUrl` verir), dış cron `/tick`, dosya yükleme
`/wp-json/flx/v1/upload` (bağlam `flx-social/media`, sonra `flxSocialMediaAdd(uploadId)`), eski `/calendar`.

## PHP

Servisler: `FLXSM_Posts_Service`, `FLXSM_Accounts_Service`, `FLXSM_Admin_Service`. Çıktı tipleri readonly DTO'lardır (`FLXSM_Post`, `FLXSM_Account` …).
