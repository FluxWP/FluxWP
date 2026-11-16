# Flux Analytics GraphQL API

Uç: `/graphql` (Flux GraphQL). İzleme (ziyaret toplama) GraphQL dışındadır; yalnız rapor ve ayarlar vardır.
Yetkiler: raporlar `flxan_view`; referans bağlantıları `edit_posts`; ayarlar, veri işlemleri `manage_options`; telemetri merkezi yalnız merkez sitede.
İşlemler `plugins/flx-analytics/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql`.

## Okuma

```graphql
query {
  flxAnalyticsConfig { ... }
  flxAnalyticsReport(range: { preset: LAST_30_DAYS }, filters: { seg: "", rc: "" }) {
    range { start end days } totals { visitors pageviews } visits { sessions avgDuration bounceRate pagesPer }
    change { visitors pageviews } chart { label visitors pageviews }
  }
  flxAnalyticsRealtime { online pages { label value } }
  flxAnalyticsPages(first: 20) { nodes { label value } pageInfo { hasNextPage endCursor } }
  flxAnalyticsJourneys(goal: "...") { ... }
}
```

Diğer: `flxAnalyticsReferrers`, `flxAnalyticsBreakdown(dim)`, `flxAnalyticsEvents`, `flxAnalyticsPage(path)`, `flxAnalyticsFunnel`, `flxAnalyticsRefs`, `flxAnalyticsSettings`, `flxAnalyticsTelemetry`, `flxAnalyticsTelemetryErrors`.
Tarih alanları `FlxDateTime` nesnesidir (`iso label relative`), alt alan seçilir.

## Yazma

`flxAnalyticsRefSave`, `flxAnalyticsSettingsSave`, `flxAnalyticsAggregate`, `flxAnalyticsReset`, `flxAnalyticsKokoImport`, `flxAnalyticsFastTest`, `flxAnalyticsTelemetryForget`. Hepsi tek `input` alır; hatalar `errors: [{ code message field }]`.

Örnek: `flxAnalyticsRefSave(input: { code: "yaz", name: "Yaz kampanyası" }) { ref { code name } errors { code field message } }`

## Notlar

- Rapor sorguları depolanmış günlük toplamdan okur; ham ziyaret tablosu taranmaz.
- Telemetri verisi yalnız hem cihaz hem site onayı varsa toplanır; IP adresi sorgulanmaz.
