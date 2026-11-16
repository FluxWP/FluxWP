# FluxWP Panel (flx-setup): klasik → GraphQL eşlemesi

Paket sürümü: 1.4.0 · Önek: `flxk_` (güncelleyici `flxu_`) · Sahip thread: Faz 1 / flx-setup · Durum: PR bekliyor

## Özet

- Servisler: `includes/services/` (`FLXK_Packages_Service`, `FLXK_Settings_Service`, `FLXK_Wizard_Service`, `FLXK_System_Service`, `FLXK_Val`). Klasik işleyiciler ve GraphQL aynı servisi çağırır.
- Kapsam dışı: **Ön ayarlar (Presets)** ekranı ve ön ayar motoru (ayrı iş), güncelleme sunucusu protokolü (REST), depo anahtarları (hiçbir alanda dönmez).
- REST uçları: yok (güncelleme sunucusu istemcisi dışarıya konuşur). Yeni: ortak yükleme ucu bağlamı `flx-setup/logo`.

## AJAX / admin-post → GraphQL

| Klasik | → GraphQL | cap |
|---|---|---|
| `flxu_check` (admin-post + ajax) | `flxSetupCheck` | `update_plugins` |
| `flxu_plan` (ajax) | `flxSetupPlan(slugs, wpKeys, all, activate)` | `update_plugins` ya da `install_plugins` |
| `flxu_step` (ajax) | `flxSetupStepRun(kind, slug, id, version)` | aynı |
| `flxu_manage` (admin-post) | `flxSetupManage(op, slugs, wpKeys)` | `update_plugins` |
| `flxu_auto` (ajax) | `flxSetupAutoUpdateSet(type, id, on)` | `update_plugins`/`update_themes` |
| `flxu_save` | `flxSetupSettingsSave` | `manage_options` |
| `flxu_source` (add/remove/toggle/up) | `flxSetupSourceAdd`, `flxSetupSourceChange` | `manage_options` |
| `flxu_backups` (sil / saklama) | `flxSetupBackupsDelete`, `flxSetupRetentionSave` | `manage_options` |
| `flxk_onboarding_step` / `_updates` / `_logo` | `flxSetupWizardStep`, `flxSetupWizardUpdates`, `flxSetupWizardLogo` (+ yükleme ucu) | `install_plugins` (filtreli) |

## Okuma

`flxSetupOverview`, `flxSetupPackages` (connection, tab/search/onlyUpdates), `flxSetupPackage`, `flxSetupHistory`, `flxSetupSettings`, `flxSetupSources`, `flxSetupConnection`, `flxSetupSystemChecks`, `flxSetupGraphqlStatus`.

## Ekranlar

| Klasik sayfa | React modül | Not |
|---|---|---|
| Paketler (`flx-setup`) | `flxk-packages` | Varsayılan; kullanıcı meta `flxk_ui=classic` klasiğe alır. |
| Ayarlar (`flx-setup-settings`) | `flxk-settings` | Aynı. |
| Kurulum sihirbazı | vanilla JS (GraphQL istemcisi içinde) | Ayrı tam sayfa; GraphQL yoksa admin-ajax. |
| Presets | — | Kapsam dışı. |
