# Flux Wiki GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma alanları wiki'yi okuyabilen herkese açıktır (wiki kapalıysa yalnız sayfa yazabilenler görür);
taslaklar, sayfa listesi ve düzenleme alanları `edit_posts`; bölüm yönetimi `manage_categories`; ayarlar ve genel bakış `manage_options`.
İşlemler `plugins/flx-wiki/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxWikiScreen`, `FlxWikiPageSave` …).

## Okuma

```graphql
query {
  flxWikiTree { section { name slug } pages { depth page { databaseId title path url readMinutes } } }
  flxWikiSections { databaseId slug name order pageCount }
  flxWikiPage(path: "plugins/ai-enabled") { title summary markdown html toc { level id text } canEdit }
  flxWikiSearch(query: "mcp araci", limit: 5) { total fixed hits { title url heading snippet score } }
  flxWikiPages(first: 20, search: "mcp", statuses: [DRAFT, PENDING]) { nodes { databaseId title status } pageInfo { hasNextPage endCursor } totalCount }
  flxWikiOverview { homeUrl base llmsUrl mcpEnabled aiReady guideCount published }
}
```

`flxWikiPage` kimlikle (`id`: genel ya da veritabanı kimliği) ya da `path` (yol, kısa ad ya da başlık) ile çağrılır; yoksa ya da okunamıyorsa `null`.

## Yazma

```graphql
mutation {
  flxWikiPageSave(input: { title: "Yeni rehber", markdown: "## Giriş", section: "Rehber", status: PUBLISH }) {
    page { databaseId url status } errors { code field message }
  }
}
```

| Mutation | Girdi | Dönüş |
|---|---|---|
| `flxWikiPageSave` | `id?`, `title`, `markdown`, `slug`, `parentId`, `section`, `summary`, `status`, `menuOrder` | `page` |
| `flxWikiPageTrash` | `id` | `trashedId` |
| `flxWikiSectionSave` | `id?` (Int), `name`, `order` | `section` |
| `flxWikiSectionDelete` | `id` (Int) | `deletedId` |
| `flxWikiPreview` | `markdown` | `rendered { html toc }` (kayıt yok) |
| `flxWikiAsk` | `question`, `pageId?` | `answer { answer html sources }` |
| `flxWikiGuidesImport` | — | `result { created updated skipped }` |

Boş bırakılan alanlar olduğu gibi kalır. Yayımlama yetkisi olmayan editörün `PUBLISH` isteği `PENDING` olarak kaydedilir.
Ayarlar: `flxSettings { wiki { … } }` / `flxSettingsUpdate(input: { wiki: { … } })`.

Hatalar: `INVALID_INPUT` / `CONFLICT` payload `errors` içinde (`field`: `title`, `parentId`, `status`, `name`, `question`); `NOT_FOUND`, `FORBIDDEN` üst düzey hata.

## PHP

Servisler: `FLXWK_Page_Service::get()`, `find()`, `tree()`, `save()`, `trash()`, `section_save()`; `FLXWK_Search_Service::search()`; `FLXWK_Ask_Service::ask()`.
Önbellek etiketi `FLXWK_Page_Service::CACHE_TAG`; içerik değişince `flxwk_bump()` temizler.
