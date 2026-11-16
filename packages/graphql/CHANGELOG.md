# Changelog

## 0.3.3
- Flux Speed işlemleri (`FlxSpeedConfig`, `FlxSpeedSummary`, `FlxSpeedRecords`, `FlxSpeedRecord`, `FlxSpeedCache`, `FlxSpeedSettings` ve diğerleri, 20 işlem) ve tipleri.

## 0.3.2
- Flux SEO işlemleri (`FlxSeoSettingsScreen`, `FlxSeoSettingsSave`, `FlxSeoAuditScreen`, `FlxSeoAuditRun`, `FlxSeoFixApply`, `FlxSeoPsiKeySave`, `FlxSeoIndexNowSubmit`, `FlxSeoContentCheck`, `FlxSeoGuide`).

## 0.3.1
- flx-app: admin operations (`FlxAppAdmin*`, `FlxAppAccess`, `FlxAppKeyCreate`, `FlxAppAccessRevoke`, `FlxAppDeviceRevoke`) and their generated types.

## 0.3.0
- `@fluxwp/graphql/flx-app`: `flxappTransport(api)` ve `installFlxAppGraphQL()`. FluxApp paneli ve istemcisi bootstrap, `me`, sayaçlar, `sync`, config, manifest, pano, tercihler ve oturum çağrılarını GraphQL ile yapar; belge uçları `ifHash` ile ETag/304 davranışını korur. GraphQL'i olmayan sitelerde kalıcı REST yedeği.

## 0.2.0
- `useFlxViewer()` ve `useFlxCan(capability)`: giriş yapan kişi ve FluxWP yetkileri (Flux GraphQL 0.2.0 `flxViewer`).
- Flux Roles işlemleri (`FlxRolesScreen`, `FlxRoleMembers`, `FlxRolesSave`, `FlxRoleCreate`, `FlxRoleDelete`, `FlxRoleReset`, `FlxRoleMemberAdd`, `FlxRoleMemberRemove`).

## 0.1.0
- `@fluxwp/graphql/flx-tasks`: `flxgGraphQL(api)`, `flxg(api)` yerine geçer; görev ekranı ve yazma işlemleri GraphQL, eski sitelerde kalıcı REST yedeği.
- Codegen yalnız `typescript-operations` eklentisiyle (çift tip tanımı yok).
- First version: GraphQL client on the `@fluxwp/core` API client (persisted GET for reads, POST for writes, document fallback, typed `FlxGraphQLError`), React hooks `useFlxQuery` / `useFlxMutation`, `payloadErrors()`.
- Codegen: `plugins/<slug>/graphql/operations/*.graphql` → typed `FlxOperation` constants + `plugins/<slug>/graphql/persisted-queries.json`; schema snapshot and breaking change check.
