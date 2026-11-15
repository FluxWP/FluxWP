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
| 0.9 | `docs/graphql/` KURALLAR, ISTISNALAR, IS-PAKETI-SABLONU, ESLEME şablonu | ○ | PR E |
| 0.10 | Pilot: Flux Tasks uçtan uca | ○ | PR F |
| 0.11 | PHPStan + baseline + CI | 👀 | #300 (`tools/phpstan`: flx-graphql level max 0 hata; diğer kod level 5 + baseline 3871; `phpstan.yml`) |
| 0.12 | Strict TS ayarları + lint | 👀 | #300 (`tsconfig.strict.json` + `tools/ts-strict/no-any.ts`; core, graphql, manifest, tokens; diğerleri paket PR'larında) |
| 0.13 | PHP 8.1+ ve polyfill seti | 👀 | PR A (`includes/kit/polyfills.php`, 66 test); paket başlıkları paket PR'larında |

## Notlar

- WPGraphQL Smart Cache gömülmedi: önbellek etiketleri kit'te (`X-LiteSpeed-Tag: flxgql_*`, `X-GraphQL-Keys`), temizleme `flx_gql_purge()`.
- Node arayüzü (`node(id:)`) loader'larla kurulu; ilk gerçek kullanım pilot Flux Tasks'ta test edilecek.
- Haftalık WPGraphQL güncellemesi PR açar, kendisi merge etmez. Depo ayarında "Allow GitHub Actions to create and approve pull requests" açık olmalı.

## Faz 1+

Faz 0'da 0.4, 0.5 ve 0.6 merge edilince başlar (plan §5).
