# GraphQL geçişi: durum

Plan: `/mnt/project-files/fluxwp/headless/GRAPHQL-GECIS-PLANI.md` (tek kaynak). Her PR bu tabloyu günceller.
Faz 0'ın tamamı tek PR'da: #300 (Ahmet kararı).
Durum: ○ başlamadı · ✱ sürüyor · 👀 PR açık ("al" bekliyor) · ✓ merge edildi

## Faz 0: temel altyapı

| # | İş | Durum | PR / not |
|---|---|---|---|
| 0.1 | Test ortamı (`tools/tests/wp-graphql/`: WP + SQLite + Flux GraphQL + paketler; `run.sh`) | 👀 | PR A. CI: `.github/workflows/flx-graphql.yml` (PHP 8.1–8.5) |
| 0.4 | `flx-graphql` 0.1.0: gömülü WPGraphQL 2.28.0 + çakışma koruması + kit v0.1 + haftalık güncelleme CI (`wpgraphql-update.yml`) | 👀 | PR A |
| 0.5 | `@fluxwp/graphql`: codegen, persisted manifest, fetcher + hook'lar, `core/api` entegrasyonu | 👀 | PR B (`packages/graphql`, core 0.4.4 `throwOnError`) |
| 0.6 | `graphql.yml`: şema snapshot → diff → graphql-inspector → codegen → typecheck | 👀 | PR B (kırıcı değişiklik `graphql-breaking` etiketiyle geçer) |
| 0.7 | Flux Setup: flx-graphql kurulumu, sistem kontrolü, katalog | 👀 | flx-setup 1.3.0 (`flxk_graphql_notice()`, PHP 8.1 satırı) + `catalog/packages.json` |
| 0.8 | `/flx/v1/upload` + `FlxFile` | 👀 | PR A (kit içinde) |
| 0.9 | `docs/graphql/` KURALLAR, ISTISNALAR, IS-PAKETI-SABLONU, ESLEME şablonu | 👀 | #300 (`KURALLAR.md`, `ISTISNALAR.md`, `IS-PAKETI-SABLONU.md`, `ESLEME/_SABLON.md`) |
| 0.10 | Pilot: Flux Tasks uçtan uca | ✱ | #300: servis katmanı, şema, 9 kayıtlı işlem, TS adaptörü (REST yedekli), sözleşme testi 43/43, e2e (görev + belge yazma + pano/ayarlar/profil, 4 düzen). React ekranı klasikle eşit ve wp-admin varsayılanı; klasik görünüm profilden. Eşleme `ESLEME/flx-tasks.md` |
| 0.11 | PHPStan + baseline + CI | 👀 | #300 (`tools/phpstan`: flx-graphql level max 0 hata; diğer kod level 5 + baseline 3871; `phpstan.yml`) |
| 0.12 | Strict TS ayarları + lint | 👀 | #300 (`tsconfig.strict.json` + `tools/ts-strict/no-any.ts`; core, graphql, manifest, tokens; diğerleri paket PR'larında) |
| 0.13 | PHP 8.1+ ve polyfill seti | 👀 | PR A (`includes/kit/polyfills.php`, 66 test); paket başlıkları paket PR'larında; flx-tasks 0.9.0 Requires PHP 8.1 |

## Notlar

- WPGraphQL Smart Cache gömülmedi: önbellek etiketleri kit'te (`X-LiteSpeed-Tag: flxgql_*`, `X-GraphQL-Keys`), temizleme `flx_gql_purge()`.
- Node arayüzü (`node(id:)`) loader'larla kurulu; pilot Flux Tasks'ta HTTP üzerinden test edildi.
- Haftalık WPGraphQL güncellemesi PR açar, kendisi merge etmez. Depo ayarında "Allow GitHub Actions to create and approve pull requests" açık olmalı.
- Pilot bulguları: (1) WPGraphQL AppContext loader sınıflarını şemadan önce okur; Node loader'ı `flx_gql_loaders` aksiyonunda adlandırılır (kit + KURALLAR). (2) Codegen'de `typescript` + `typescript-operations` birlikte çift tip üretiyordu; yalnız operations. (3) Pilot dışındaki uçlar (sync, yapı, belgeler, ayarlar, pano) geçici istisna, Faz 1'de taşınır. (4) wp-embed parça derlemesinde `react-i18next` iki kopya yükleniyordu, ekran kullanıcı dilini görmüyordu; dedupe ile düzeldi. (5) Önekleyici `body.<sınıf>` kurallarını koşulsuz yapıyordu; düzeldi.

## Faz 1+

Faz 0'da 0.4, 0.5 ve 0.6 merge edilince başlar (plan §5).

| Paket | Durum | PR / not |
|---|---|---|
| flx-roles 1.5.0 | 👀 | `gql/flx-roles-1`: servis katmanı, 4 sorgu + 6 mutation, 8 kayıtlı işlem, sözleşme testi 55/55. React ekranı (`flxr-roles`) klasikle eşit, wp-admin varsayılanı; klasik sayfadaki bağlantıyla. Kit 0.2.0 yetki modeli (`flx_gql_any_cap/all_caps`, `flx_gql_capabilities`, `flxViewer`, `useFlxCan`), KURALLAR §3.1. Eşleme `ESLEME/flx-roles.md` |
| flx-ai 0.23.0 | 👀 | `gql/flx-ai-1`: servis katmanı, 25 alan/mutation (anahtarlar yalnız yazılır), sözleşme testi 147/147. React: Genel bakış, Kayıtlar, Faturalama, Sistemler, Araçlar (`flxai-admin`, wp-admin varsayılanı). **Kalan (gql/flx-ai-2):** Ayarlar React ekranı, Onaylar, Skill'ler, Görevler, MCP. Eşleme `ESLEME/flx-ai.md` |
