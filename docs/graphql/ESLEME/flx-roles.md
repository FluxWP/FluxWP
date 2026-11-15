# Flux Roles (flx-roles): klasik → GraphQL eşlemesi

Paket sürümü: 1.5.0 · Önek: `flxr_` · Sahip thread: Faz 1 / flx-roles · Durum: bitti (PR `gql/flx-roles-1`)

## Özet

- REST uçları: 0 · AJAX: 0 · admin-post işleyici: 4 · Admin sayfası: 1 · MCP/AI aracı: 0
- İstisna: yok. Profil sayfasındaki telefon ve referans linki alanları WordPress profil formunda kalır (WP'nin kendi formu, ayrı ekran değil).
- Servisler: `includes/services/class-flxr-roles-service.php` (`FLXR_Roles_Service`), DTO'lar `class-flxr-dto.php`.
- Node tipleri: yok (rol anahtarı kimlik; kullanıcı `databaseId`).
- Kit katkısı: cap modeli (`flx_gql_any_cap`, `flx_gql_all_caps`, `flx_gql_capabilities`, `flxViewer`, `flxCapabilities`), KURALLAR §3.1.

## admin-post işleyicileri

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxr_save` | `manage_options` / `flxr_save` | `flxRolesSave(roles, referralDays)` | Tümü doğrulanır, sonra yazılır (yarım kayıt yok). Kilitli yetki (`read`, `*_manage` dışı geniş olanlar) atlanır. |
| `flxr_reset` | `manage_options` / `flxr_reset` | `flxRoleReset(key)` | |
| `flxr_role` (add/delete) | `manage_options` / `flxr_role` | `flxRoleCreate(name)`, `flxRoleDelete(key)` | Yalnız panelden eklenen rol silinir; kişileri varsayılan role geçer. |
| `flxr_user` (add/remove) | `manage_options` + `promote_users` / `flxr_user` | `flxRoleMemberAdd(role, login)`, `flxRoleMemberRemove(role, userId)` | Yönetici eklenemez (INVALID_INPUT `login`). |

Klasik işleyiciler servis katmanını çağırır; davranış iki ekranda aynı (sözleşme testi karşılaştırır).

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `users.php?page=flx-roles` | Rol kartları, yeni rol, yetki tablosu, giriş sonrası ekran, referans süresi, kişiler | `FlxRolesScreen`, `FlxRoleMembers` (devamı) | `packages/modules/src/roles/RolesScreen.tsx` (`flxr-roles`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxr_ui` meta). FluxApp modülü `flxr-roles`. |

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxRole` | key, label, description, custom, memberCount, capabilities, home, members | — | — |
| `FlxRoleCapabilityGroup` | key, label, plugin, active, capabilities | `plugin`: grup bir eklentiye bağlı değilse | — |
| `FlxRoleCapability` | key, label, locked, broad, pending | — | — |
| `FlxRoleMember` | databaseId, name, email, phone, editUrl, referralLink | `phone`: numara yoksa; `referralLink`: `flx_referans` yoksa | — |
| `FlxRolesConfig` | referralDays, homeChoices, newUserUrl, permissionsUrl | `permissionsUrl`: Flux Admin yoksa | — |
| `FlxViewer` eki | roleKey, roleLabel, referralLink | ziyaretçide null | — |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-roles:roles` | `flxRoles`, `flxRole`, `flxRoleCapabilityGroups`, `flxRolesConfig` | Tüm mutation'lar (`FLXR_Roles_Service::changed()`) |

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
