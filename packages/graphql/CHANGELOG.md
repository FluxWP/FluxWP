# Changelog

## 0.4.0
- Ads: new `flxads-ads` screen for Flux Ads (report, campaigns, suggestions, Studio, season plan, tests, donor value, ad spend, audiences, send log, ad link, settings). Same look as the classic page on FluxWP tokens; data over the `flxAds*` GraphQL operations, texts from the plugin's language packs. Also available as a FluxApp module. Generated types and persisted operations for the 17 queries and 27 mutations.
- AI: the `flxai-admin` screen of Flux AI now also covers Skills (list, edit, folder files, trigger and functional tests, import, brand kit; operations `FlxAiSkills`, `FlxAiSkill`, `FlxAiSkillFile`, `FlxAiBrand`, `FlxAiSkillSave`, `FlxAiSkillToggle`, `FlxAiSkillDelete`, `FlxAiSkillsSettings`, `FlxAiSkillImport`, `FlxAiSkillFileSave`, `FlxAiSkillFileDelete`, `FlxAiSkillFileUpload`, `FlxAiSkillEvalsSave`, `FlxAiSkillTestRun`, `FlxAiBrandSave`), Scheduled tasks (list, form with recipients search, run now, pause, delete; operations `FlxAiTasks`, `FlxAiRecipients`, `FlxAiTaskSave`, `FlxAiTaskRun`, `FlxAiTaskSetActive`, `FlxAiTaskDelete`, `FlxAiTaskMarkRead`, `FlxAiTasksToggle`), Settings (providers and write-only API keys, models and tests, cache, spending limit, shared chat, image and video, web, visuals, logs) and Awaiting approval (actions and change requests, edit before approving). New operations `FlxAiApprovals`, `FlxAiApprovalDecide`, `FlxAiChangeDecide`.
- Screen shell (Flux GraphQL 0.4.0): page-wide result store (`flxStoreKey`, `flxStoreGet`, `flxStoreSet`, `flxStoreMarkStale`) shared by every bundle on the page and seeded from the server preload; `useFlxQuery` shows a stored result at once and refreshes it in the background; queries started in the same tick go out in one batch request (`batch: false` turns it off); a mutation marks stored results stale. Modules: `preloadScreen(id)` loads a screen's code before it mounts, so it paints without a loading flash.

## 0.3.6
- Flux Analytics operations (`FlxAnalyticsConfig`, `FlxAnalyticsReport`, `FlxAnalyticsRealtime`, `FlxAnalyticsPages`, `FlxAnalyticsReferrers`, `FlxAnalyticsBreakdown`, `FlxAnalyticsEvents`, `FlxAnalyticsPage`, `FlxAnalyticsFunnel`, `FlxAnalyticsRefs`, `FlxAnalyticsJourneys`, `FlxAnalyticsSettings`, `FlxAnalyticsTelemetry`, `FlxAnalyticsTelemetryErrors` and the write operations).

## 0.3.5
- Flux reCAPTCHA operations (`FlxRecaptchaScreen`, `FlxRecaptchaSettingsUpdate`, `FlxRecaptchaKeysTest`, `FlxCaptcha`).

## 0.3.4
- Flux Speed işlemleri (`FlxSpeedConfig`, `FlxSpeedSummary`, `FlxSpeedRecords`, `FlxSpeedRecord`, `FlxSpeedCache`, `FlxSpeedSettings` ve diğerleri, 20 işlem) ve tipleri.

## 0.3.3
- `@fluxwp/graphql/flx-cart`: `flxCart(gql, { store, nonce })` sepet istemcisi (Flux GraphQL 0.3.0 `flxCart*`); sepet belirtecini saklar ve `Cart-Token` başlığıyla geri gönderir, hatalı girdide `FlxCartInputError`, Store API checkout'u için `checkoutHeaders()`.
- Sepet işlemleri (`FlxCart`, `FlxCartItemAdd`, `FlxCartItemUpdate`, `FlxCartItemRemove`, `FlxCartClear`, `FlxCartCouponApply`, `FlxCartCouponRemove`, `FlxCartShippingRateSelect`, `FlxCartCustomerUpdate`) ve `FlxCartFields` fragment'ı.

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
