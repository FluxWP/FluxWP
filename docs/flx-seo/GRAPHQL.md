# Flux SEO GraphQL API

Uç: `/graphql` (Flux GraphQL). İşlemler `plugins/flx-seo/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxSeoSettingsScreen`, `FlxSeoAuditScreen` …).
Yönetim alanları `manage_options` ister; yalnız `flxSeoGuide` ve `flxSeoContentSet` `edit_posts` (içerik için ayrıca `edit_post`). `flxSeo` alanının sayfa başı verisi herkese açıktır, `focusKeyword` yalnız `edit_posts`.

## Okuma

```graphql
query {
  flxSettings { flxseo { siteName sep orgType auditFreq csAiTrain verifyGoogle … } }   # flx_settings_schema (otomatik)
  flxSeoSetup { engines { key field name url } sitemapUrl indexnowKey indexnowReady psiKeySaved guideSections … }
  flxSeoAudit { counts { critical high medium low } local { time { label relative } } pageSpeed { strategy scores { label score } metrics { key value } }
                agentic { id label passed } findings { id title priority fix fixId fixLabel sources } }
  flxSeoAuditHistory(limit: 30) { total running errors next { label } entries { time { label } trigger counts { critical } scores { strategy category score } added { id title } resolved { id title } } }
  flxSeoContentCheck(postType: "page", limit: 300) { checked total items { databaseId title url editUrl issues { level code message } } }
  flxSeoGuide(lang: TR) { markdown sections }
}
```

Her içerik türünde ve terimde: `post(id: …) { flxSeo { title description noindex ogImage effectiveTitle effectiveDescription focusKeyword } }`.

## Yazma

| Mutation | Girdi | Dönüş |
|---|---|---|
| `flxSettingsUpdate(input: { flxseo: {...} })` | yalnız gönderilen alanlar | `settings`, `errors` (hatalı bir alan varsa hiçbiri yazılmaz) |
| `flxSeoAuditRun` | `mode: LOCAL \| PAGESPEED`, `strategy`, `url` | `audit`, `history` (PageSpeed 15–40 sn; önce mobil, sonra masaüstü, sonra LOCAL çağrılır) |
| `flxSeoFixApply` | `id` (bulgunun `fixId`) | `audit` |
| `flxSeoPsiKeySave` | `key`, `clear` | `saved` (anahtar asla dönmez) |
| `flxSeoIndexNowSubmit` | — | `submitted` (int), `setup` |
| `flxSeoContentSet` | `databaseId`, `title`, `description`, `focusKeyword`, `noindex`, `ogImage` | `seo` |

Hatalar payload `errors` içinde: `INVALID_INPUT` (ör. kurulu olmayan IndexNow, ulaşılamayan adres, geçersiz seçenek), `NOT_FOUND`, `FORBIDDEN`.

## Notlar

- Doğrulama alanına yapıştırılan `<meta>` etiketi kit tarafından etiketsiz metne çevrilir; istemci içerik değerini kendisi ayıklar (klasik form ve her `flxseo` seçenek yazımı sunucuda da ayıklar).
- `flxseo_audit_tick` (arka plan denetim tetiği) ve REST `flx-seo/v1/audit` GraphQL dışındadır (docs/graphql/ISTISNALAR.md). ~20 MCP aracı `flx_mcp_tools`'ta kalır.
- Önbellek etiketleri: `flx-seo:audit`, içerik için `FLXSEO_Content_Service::cache_tag()`.
