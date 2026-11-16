# FluxWP Panel GraphQL API

Uç: `/graphql` (Flux GraphQL 0.2.0+). İşlemler `plugins/flx-setup/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxSetupPackages`, `FlxSetupStepRun` …).
Yetkiler: paket okuma/çalıştırma `update_plugins` (kurulum adımları `install_plugins`), ayarlar ve kaynaklar `manage_options`. Site anahtarı ve kaynak anahtarları hiçbir alanda dönmez (`hasKey`, `keyFromConfig`).
Mutation çıktı alanları boş olabilir: INVALID_INPUT/CONFLICT durumunda yalnız `errors` dolar.

## Paketler

```graphql
query { flxSetupOverview { connection { state title message checkedLabel } tabs { tab count updates } updateCount autoCheck backup { ready } } }
query { flxSetupPackages(tab: ALL, search: "tasks", onlyUpdates: false, first: 50) { nodes { key name state installed latest autoUpdate { mode id } requires { name state } } pageInfo { hasNextPage endCursor } } }
```

`first` en çok 50 (Flux GraphQL `max_page`); ekran sayfaları art arda çeker.

Kurma/güncelleme: önce `flxSetupPlan(slugs, wpKeys, all, activate)` adımları verir (önce gerekli eklentiler), sonra her adım `flxSetupStepRun(input: {kind, slug, id, version})` ile tek tek çalışır (yedek alınır). `flxSetupManage(op, slugs, wpKeys)` etkinleştirir / kapatır / siler / otomatik güncellemeyi açıp kapatır. Anahtarlar: FluxWP paketi için slug, diğerleri için `p:<eklenti dosyası>`, `t:<tema klasörü>`, `lang`, `core`.

## Geçmiş ve yedekler

`flxSetupHistory(page)`, `flxSetupBackupsDelete(mode: ONE|SELECTED|ALL, ids)`, `flxSetupRetentionSave(days)` (Flux Backup gerekir).

## Ayarlar ve kaynaklar

`flxSetupSettings`, `flxSetupSources`, `flxSetupSettingsSave(server, key, clearKey, auto, health, usage)`, `flxSetupSourceAdd(url, name, key)`, `flxSetupSourceChange(op: REMOVE|TOGGLE|UP, id)`.

## Sistem

`flxSetupSystemChecks` (sistem kontrolüne eklenen satırlar), `flxSetupGraphqlStatus`.

## Sihirbaz

`flxSetupWizardStep(kind, value)`, `flxSetupWizardUpdates`, `flxSetupWizardLogo(uploadId)`; logo `POST /wp-json/flx/v1/upload` (`context=flx-setup/logo`, `file`) ile yüklenir.
