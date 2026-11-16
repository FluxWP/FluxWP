# Flux Ads GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma `flxads_view`, yazma `flxads_manage` ister (`manage_options` her ikisini de sağlar); `flxAdsSettings` yönetici gerektirir.
İşlemler `plugins/flx-ads/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxAdsReport`, `FlxAdsCampaigns` …).

## Okuma

```graphql
query {
  flxAdsContext { canManage metaReady aiReady crmReady openSuggestions baseCurrency tabs }
  flxAdsReport(filter: { range: "30", kind: "all", by: "channel" }) {
    totals { adsAmount { formatted } adsCount roas }
    rows { key label channel count amount { formatted } }
  }
  flxAdsCampaigns(range: "7") { error rows { id name effective spend { formatted } roas } }
}
```

Diğer sorgular: `flxAdsSpend`, `flxAdsValue`, `flxAdsSendLog`, `flxAdsLinkBuilder`, `flxAdsAudiences`, `flxAdsCampaign(id)`, `flxAdsChangeHistory`, `flxAdsSuggestions`, `flxAdsExperiments`, `flxAdsExperimentChoices`, `flxAdsSeasons`, `flxAdsStudio`, `flxAdsDraft(id)`, `flxAdsSettings`.

Rapor filtresi: `range` (7, 30, 90, month, last_month, year, custom), `from`/`to` (custom için), `kind` (all, donations, orders), `channel`, `by`.

## Yazma

| Mutation | Girdi | Not |
|---|---|---|
| `flxAdsSettingsSave` | `settings` | Boş sır mevcut olanı korur, `"__clear__"` siler |
| `flxAdsConnectionTest` | `platform` (META, TIKTOK, GA4), `settings?` | Önce kaydeder; test olayı gönderir, kayıt oluşmaz |
| `flxAdsHeartbeat` | — | Kuyruğu işler, harcama okumasını zamanlar |
| `flxAdsSpendAdd` / `flxAdsSpendDelete` / `flxAdsSpendSync` | | El girişi ve Meta okuması |
| `flxAdsSendRetry` | `id` | Başarısız dönüşümü yeniden gönderir |
| `flxAdsAudienceCreate` / `Lookalike` / `Sync` / `Delete` | | Meta'da kitle |
| `flxAdsCampaignStatus` / `flxAdsCampaignBudget` | | Meta'da durum ve bütçe; değişiklik geçmişine yazılır |
| `flxAdsSuggestionApply` / `Dismiss` / `flxAdsSuggestionsRefresh` | | |
| `flxAdsDraftSave` / `Generate` / `Duplicate` / `Delete` / `Send` | | `Send` her zaman duraklatılmış oluşturur |
| `flxAdsSeasonAdd` / `flxAdsSeasonDelete` | | Kendi sezon tarihin |
| `flxAdsExperimentCreate` / `Finish` / `Delete` / `PauseLoser` | | |

Hatalar payload `errors` içinde (`INVALID_INPUT`, `NOT_FOUND`, `CONFLICT`); Meta'nın reddi `error` ya da `errors[].message` olarak döner.

## Test

`tools/tests/wp-graphql/flx-ads/contract-test.php`: 44 kayıtlı işlem, yetkiler (ziyaretçi, abone, görüntüleyici, yönetici), sırların asla dönmemesi, bağlantı testi (dış HTTP engelli), harcama/sezon/taslak yazma yolları.
