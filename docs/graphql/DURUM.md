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
| flx-seo 1.11.0 | 👀 | `gql/flx-seo-1`: 3 servis, 5 sorgu + 5 mutation + ayar tipi (`flxSettings.flxseo`) + `flxSeo` alanı (her içerik/terim), 9 kayıtlı işlem, sözleşme testi 120/120. React ekranı `flxseo-seo` (5 sekme) klasikle eşit, wp-admin varsayılanı. Eşleme `ESLEME/flx-seo.md` |
| flx-roles 1.5.0 | 👀 | `gql/flx-roles-1`: servis katmanı, 4 sorgu + 6 mutation, 8 kayıtlı işlem, sözleşme testi 55/55. React ekranı (`flxr-roles`) klasikle eşit, wp-admin varsayılanı; klasik sayfadaki bağlantıyla. Kit 0.2.0 yetki modeli (`flx_gql_any_cap/all_caps`, `flx_gql_capabilities`, `flxViewer`, `useFlxCan`), KURALLAR §3.1. Eşleme `ESLEME/flx-roles.md` |
| FluxWP teması 0.8.0 | 👀 | `gql/theme-1`: servis katmanı, 5 sorgu + 3 mutation, 7 kayıtlı işlem, sözleşme testi 72/72. Tema Ayarları + Sistem Kontrolü React ekranı klasikle eşit, wp-admin varsayılanı. Codegen artık `themes/*` da tarar, test sitesi temayı etkinleştirir. Eşleme `ESLEME/fluxwp.md` |
| flx-crm 0.10.0 | 👀 | `gql/flx-crm-1`: servis katmanı, 28 işlem, `FlxContact` = ortak `FlxPerson`, sözleşme testi 121/121. React ekranı (`crm`) klasikle eşit, wp-admin varsayılanı. Açık borç (zorunlu, `gql/flx-crm-2`): Olaylar sayfası ve kişi kartı eklenti kancaları hâlâ klasik, Ahmet'in ekran kuralına aykırı. CRM'e özel FlxContact alanları `flxcrm_view` cap'li ve nullable; `flxcrm_gql_can_see_contact` filtresi. `flxcrm_sync` → `flxCrmSyncStep`. Belge `docs/flx-crm/GRAPHQL.md`, eşleme `ESLEME/flx-crm.md` |
| flx-blocks 0.3.0 | 👀 | `gql/flx-blocks-1` (Faz 4): `ContentNode.flxBlocks` (blok ağacı + işlenmiş HTML + CSS/stil/betik/modül/yazı tipi/şema, `headHtml`/`footerHtml`), `flxBlocksSiteStyles`, `flxBlocksRender`; tasarım kütüphanesi (4 sorgu + 3 mutation) ve pano (2 sorgu + 2 mutation); 13 kayıtlı işlem, sözleşme testi 65/65 (canlı sayfa CSS'i ile karşılaştırmalı). React ekranı (`flxbk-dashboard`) klasikle eşit + tasarım kütüphanesi, wp-admin varsayılanı; klasik sayfadaki bağlantıyla. Editör içi REST istisna. flx-forms bağlantısı `docs/flx-blocks/GRAPHQL.md`. Eşleme `ESLEME/flx-blocks.md` |
| flx-setup 1.4.0 | 👀 | `gql/flx-setup-1` (dal `claude/project-thread-hdhayh`): servis katmanı (`FLXK_Packages/Settings/Wizard/System_Service`), 10 sorgu + 12 mutation, 18 kayıtlı işlem, sözleşme testi 69/69. React ekranları Paketler (`flxk-packages`) ve Ayarlar (`flxk-settings`) klasikle eşit, wp-admin varsayılanı; Flux GraphQL yoksa klasik ekran + kurulum düğmesi. Kurulum sihirbazı GraphQL kullanır (admin-ajax yedek), logo ortak yükleme ucuyla. Presets ekranı kapsam dışı. Eşleme `ESLEME/flx-setup.md` |
| flx-app 0.6.0 | 👀 | `gql/flx-app-1`: servis katmanı, 8 sorgu + 4 mutation, 12 kayıtlı işlem, sözleşme testi 150/150. Belirteç + `flx` kapsamı + `X-Flux-Locale` GraphQL'de; OAuth/OTP/cihaz/ayar köprüsü REST'te, eski uygulama sürümleri REST'te. İstemci taşıyıcısı `@fluxwp/graphql/flx-app` (panel `mountApp`'te, REST yedekli). "Uygulama" ayar sayfası + "Uygulama erişimi" React ekranları 0.6.0'da (varsayılan, klasik bağlantıyla; 17 yeni işlem). Eşleme `ESLEME/flx-app.md` |
| flx-lang 0.3.0 | 👀 | `gql/flx-lang-1`: servis katmanı, 4 sorgu + 5 mutation, `flxLanguage`/`flxTranslations` her içerik tipinde, 10 kayıtlı işlem, sözleşme testi 107/107. React ekranı (`flxl-lang`) klasikle eşit, wp-admin varsayılanı. Tasarım `docs/flx-lang/GRAPHQL.md`, eşleme `ESLEME/flx-lang.md` |

## Faz 2: ticaret ve bağış

| Paket | Durum | PR / not |
|---|---|---|
| `flxCart*` köprüsü (Flux GraphQL 0.3.0) | 👀 | `gql/cart-bridge-1`: Flux GraphQL içinde WooCommerce modülü (karar ve gerekçe `SEPET.md` §1). `flxCart` + 8 mutation, Store API'yi `rest_do_request` ile sarar; Cart-Token / Nonce oturumu, no-store. Paket genişleme noktası `FLXGQL_Cart::add_item()` + `flx_gql_cart_item_kind`. İstemci `@fluxwp/graphql/flx-cart` 0.3.3. HTTP testi 31/31 (yerelde Store API yerine geçen fixture, CI'da gerçek WooCommerce işi). Ödeme (EsnekPOS) Store API'de, köprüye girmez |
| flx-commerce | ○ | Köprü merge edilince. Kullanacağı API `SEPET.md` §6 |
| flx-donate | ○ | Köprü merge edilince. Bağış satırı `FLXGQL_Cart::add_item()` ile (`SEPET.md` §6) |
