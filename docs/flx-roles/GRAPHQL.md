# Flux Roles GraphQL API

Uç: `/graphql` (Flux GraphQL). Tüm alanlar `manage_options` ister; kişi ekleme/çıkarma ayrıca `promote_users`.
İşlemler `plugins/flx-roles/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxRolesScreen`, `FlxRolesSave` …).

## Okuma

```graphql
query {
  flxRoles { key label custom memberCount capabilities home
    members(first: 50) { nodes { databaseId name email phone { digits display } referralLink } pageInfo { hasNextPage endCursor } } }
  flxRoleCapabilityGroups { key label plugin active capabilities { key label locked broad pending } }
  flxRolesConfig { referralDays homeChoices { key label } newUserUrl permissionsUrl }
}
```

Giriş yapan kişinin rolü ve referans linki: `flxViewer { roleKey roleLabel referralLink capabilities }` (herkese açık; ziyaretçide null/boş).

## Yazma

```graphql
mutation {
  flxRolesSave(input: { roles: [{ key: "flx_personel", capabilities: ["read", "flx_referans", "flxg_use"], home: "auto" }], referralDays: 30 }) {
    roles { key capabilities } errors { code field message }
  }
}
```

| Mutation | Girdi | Dönüş |
|---|---|---|
| `flxRolesSave` | `roles: [FlxRolesSaveRoleInput!]`, `referralDays` | `roles`, `config` |
| `flxRoleCreate` | `name` | `role` |
| `flxRoleDelete` | `key` (yalnız panelden eklenen rol) | `deletedKey` |
| `flxRoleReset` | `key` | `role` |
| `flxRoleMemberAdd` | `role`, `login` (kullanıcı adı ya da e-posta) | `member`, `roles` |
| `flxRoleMemberRemove` | `role`, `userId` | `removedUserId`, `roles` |

Hatalar payload `errors` içinde: `INVALID_INPUT` (`field`: `roles.N.key`, `roles.N.capabilities`, `roles.N.home`, `referralDays`, `key`, `login`), `NOT_FOUND`.
`flxRolesSave` önce her şeyi doğrular; bir hata varsa hiçbir şey yazılmaz.

## PHP

Servis: `FLXR_Roles_Service::roles()`, `save()`, `create_role()`, `delete_role()`, `reset_role()`, `add_member()`, `remove_member()`.
Paketler yetki için yine `user_can()` kullanır (API.md); rol adına bakılmaz.
