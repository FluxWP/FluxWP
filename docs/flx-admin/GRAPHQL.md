# Flux Admin GraphQL API

Uç: `/graphql` (Flux GraphQL). Ayar, menü düzeni ve yetki alanları `manage_options` ister; kişisel alanlar (`flxAdminMode`, `flxAdminUserMenu`, `flxAdminWidgetPrefs`) giriş yapmış herkese açıktır.
İşlemler `plugins/flx-admin/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql`.

## Okuma

```graphql
query {
  flxAdminSettings { enabled brand subtitle logo { id url } accent mode lineIcons logoStyle bottomBar heartbeat
    theme { radius font fontSize density light { bg side surface text } dark { bg side surface text } }
    dashHeader dashStats quickLinks pwa pwaName pwaShort }
  flxAdminSettingsConfig { siteName defaultAccent colorDefaults { light { bg } } dashStatChoices { key label } pwaPossible systemUrl }
  flxAdminMenuEditor { roles { key label userCount } catalog { slug label iconSvg submenu { slug label } }
    layouts { role custom entries { type label slug hidden blocked subHidden platformsOff collapsed } bottom access }
    defaults { bottom } platforms { key label } unblockable }
  flxAdminCaps { roles { key label userCount isAdministrator granted } groups { key label capabilities { key label } } backupAt { iso label } }
}
```

Kişisel: `flxAdminMode`, `flxAdminUserMenu { pins away subAway subKeep }`, `flxAdminWidgetPrefs { tasksAvailable donationsAvailable tasksScope tasksList tasksChart donationsDays donationsSplit }`.

## Yazma

```graphql
mutation {
  flxAdminSettingsUpdate(input: { brand: "Panelim", heartbeat: 60, theme: { radius: LARGE, light: { bg: "#F5F3EF" } } }) {
    settings { brand } errors { code field message }
  }
}
```

| Mutation | Girdi | Not |
|---|---|---|
| `flxAdminSettingsUpdate` | Kısmi: verilmeyen alan değişmez. `logoId` (0 = kaldır) ya da `logoUploadId` (`POST /wp-json/flx/v1/upload`, bağlam `flx-admin/logo`) | Vurgu rengi tema rengine eşitse "tema rengini izle" olarak saklanır |
| `flxAdminMenuLayoutsSave` | `layouts: [{role, entries, bottom}]`, `reset: [role]` | Rol `_default` = diğer tüm roller; menüde olmayan bağlantılar atılır |
| `flxAdminCapsSave` | `roles: [{role, capabilities}]` | Yalnız tablodaki yetkiler; Yönetici atlanır; ilk değişiklik yedek alır |
| `flxAdminCapsRestore` | `confirm: true` | Yedeği geri yükler ve siler |
| `flxAdminModeSet` | `mode: AUTO / LIGHT / DARK` | Kullanıcı başına |
| `flxAdminUserMenuSet` | `op: PIN / UNPIN / AWAY / BACK / SUB_AWAY / SUB_BACK / ORDER`, `slug`, `order` | Kullanıcı başına |
| `flxAdminWidgetPrefsSet` | `widget: TASKS / DONATIONS` + filtreler | Modülün HTML'ini döndürür |

Hatalar payload `errors` içinde: `INVALID_INPUT` (`field` girdi yolu), `NOT_FOUND` (geri yüklenecek yedek yok), `FORBIDDEN`.

## PHP

Servisler: `FLXA_Settings_Service`, `FLXA_Menu_Service`, `FLXA_Caps_Service`, `FLXA_User_Service`. Eski AJAX işleyicileri bunları çağırır; yeni kod doğrudan servisi kullanır.
