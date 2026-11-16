# Flux AI GraphQL (0.23.0)

Uç nokta `/graphql` (Flux GraphQL). Hepsi `manage_options` ister. Belgeler `plugins/flx-ai/graphql/operations/*.graphql`; kayıtlı (persisted) işlemler `plugins/flx-ai/graphql/persisted-queries.json`.

**Sorgular:** `flxAiBudget`, `flxAiReport(days, source)`, `flxAiBilling(month)`, `flxAiLogs(first, after, days, kind, source, errorsOnly)`, `flxAiLogBody(id)`, `flxAiLogSources`, `flxAiSystems`, `flxAiTools`, `flxAiSettings`, `flxAiSecrets`, `flxAiSettingsOptions`, `flxAiModels(search, first)`.

**Mutation'lar:** `flxAiSystemsUpdate`, `flxAiCreditsRefresh`, `flxAiToolsUpdate`, `flxAiToolPick`, `flxAiSettingsUpdate`, `flxAiModelTest`, `flxAiRenderTest`. Girdi hataları `errors[]` içinde (`INVALID_INPUT`, `field`).

**API anahtarları asla geri verilmez.** `flxAiSecrets` yalnız `configured`, `hint` (son 4 karakter) ve `fromConfig` döner; yazmak için `flxAiSettingsUpdate(input: {secrets: [{name, value}]})`, silmek için `clear: true`.

Testler: `tools/tests/wp-graphql/flx-ai/contract-test.php`. Gerçek sağlayıcı çağrılmaz.
