# Flux App GraphQL API

Uç: `/graphql` (Flux GraphQL gerekir). FluxApp'in giriş sonrası bağlantısı: bootstrap, `me`, sayaçlar, `sync`, config, manifest, pano, tercihler, oturum.
İşlemler `plugins/flx-app/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxAppBootstrap`, `FlxAppMe` …), istemci taşıyıcısı `@fluxwp/graphql/flx-app`.
OAuth, OTP/telefon, cihaz ve ayar köprüsü uçları REST'te kalır ([ISTISNALAR](../graphql/ISTISNALAR.md)); mağazadaki eski uygulama sürümleri REST'i kullandığı için REST uçları aynen çalışır.

## Giriş ve yetki

- Uygulama belirteci: `Authorization: Bearer flxat_…` / `flxak_…` ya da `X-FluxWP-Token`. Belirteç gelmişse çerez/nonce denetimi yoktur.
- Kapsam: `flx` kapsamı olmayan belirteç (yalnız `mcp`) → HTTP 403. Geçersiz/iptal belirteç → HTTP 401. Kayıtlı kapsamı boş belirteç kısıtlanmaz (REST ile aynı).
- Dil: `X-Flux-Locale` (`tr_TR`, `tr-TR`, `tr`) GraphQL'de de dili belirler.
- `flxAppBootstrap` herkese açıktır (giriş öncesi, gizli bilgi yok) ve dile göre değiştiği için paylaşımlı önbellek başlığı almaz. Diğer tüm alanlar `logged_in`.

## Okuma

```graphql
query {
  flxAppBootstrap          # flx-app-bootstrap/1 (FlxJSON)
  flxAppMe { user { id name roles { key label } } site { name url } auth { kind scopes } menu { slug title } modules { id title } counts { name value } hashes { config manifest me } }
  flxAppCounts { name value }
  flxAppSync(since: 1731700000) { time counts { name value } hashes { config manifest me } events { type title target } }
  flxAppConfig(ifHash: "ab12cd") { hash notModified document }
  flxAppManifest(ifHash: "ef34ab") { hash notModified modules { id manifest } }
  flxAppDashboard { cards { key value } lists { key rows { title } } }
  flxAppPrefs { menuOrder pinned events { id label enabled } updated }
}
```

`document` (config, bootstrap) ve modül `manifest` alanı **açık sürümlü belgelerdir** (`FlxJSON`, `flx-app-config/1`, `flx-app-manifest/1`, `flx-app-bootstrap/1`). Alanları istemcide doğrulanır; REST yanıtıyla birebir aynıdır. `ifHash` önceki `hash` ile eşleşirse `notModified: true` döner ve belge gelmez (REST'in ETag/304 davranışı).

## Yazma

| Mutation | Girdi | Dönüş | REST karşılığı |
|---|---|---|---|
| `flxAppPrefsUpdate` | `FlxAppPrefsUpdateInput` (gönderilen tercihler değişir) | `prefs`, `errors` | `POST /flxapp/v1/prefs` |
| `flxAppSessionCreate` | `path`, `site`, `bare` | `url`, `expiresIn`, `errors` (`FORBIDDEN`, `RATE_LIMITED`, `INVALID_INPUT`) | `POST /flxapp/v1/session` |
| `flxAppSessionEnd` | — | `ended`, `errors` | `POST /flxapp/v1/session/end` |
| `flxAppLogout` | — | `ok`, `errors` | `POST /flxapp/v1/logout` |

## Admin sayfaları (0.6.0)

"Uygulama" ayarları ve "Uygulama erişimi" React ekranları bu işlemleri kullanır. Yetki: ayar/yönetim işlemleri `manage_options`, erişim işlemleri `logged_in` (kullanıcı yalnız kendi kayıtlarını görür/iptal eder; anahtar oluşturma `keys_cap` ayarına uyar).

| İşlem | Tür | Cap |
|---|---|---|
| `flxAppAdminSettings` / `flxAppAdminSettingsSave` | query / mutation | `manage_options` |
| `flxAppAdminModules` / `flxAppAdminModulesSave` | query / mutation | `manage_options` |
| `flxAppAdminMenu` | query | `manage_options` |
| `flxAppAdminClients` / `flxAppAdminClientCreate` / `flxAppAdminClientDelete` | query / mutation | `manage_options` |
| `flxAppAdminMobile` / `flxAppAdminMobileSave` / `flxAppAdminPushTest` | query / mutation | `manage_options` (deneme bildirimi `logged_in`) |
| `flxAppAdminAppearance` / `flxAppAdminAppearanceSave` | query / mutation | `manage_options` |
| `flxAppAccess` | query | `logged_in` |
| `flxAppKeyCreate` / `flxAppAccessRevoke` / `flxAppDeviceRevoke` | mutation | `logged_in` |

Klasik `admin-post` işleyicileri ile resolver'lar aynı `FLXAPP_Admin_Service` metotlarını çağırır. Ekran: `packages/modules/src/app-settings/`, paket `plugins/flx-app/assets/flux/` (`tools/fluxapp/embed-sync.sh flx-app`). Kullanıcı başına görünüm `flxapp_ui` user meta (`classic`); bağlantılar `flxapp_ui_switch_url()`.

## İstemci

`installFlxAppGraphQL()` (panel `mountApp` içinde, web + masaüstü) hesap istemcisini `flxappTransport` ile sarar: `/me`, `/counts`, `/sync`, `/config`, `/manifest`, `/dashboard`, `/prefs`, `/session`, `/session/end`, `/logout`, `/app/bootstrap` çağrıları GraphQL'e gider, REST biçiminde sonuç döner (`config`/`manifest` için ETag ve 304 korunur). GraphQL'i olmayan sitede (404, `QUERY_NOT_ALLOWED`, "Cannot query field") kalıcı REST yedeğine düşer. Diğer yollar dokunulmadan REST'e gider.

## Önbellek

Bootstrap `flx-app:bootstrap` tag'iyle; ön yükleme belgesi değişince (`flxapp_bootstrap_flushed`) `flx_gql_purge` çağrılır.

## Test

`tools/tests/wp-graphql/flx-app/contract-test.php` (150 kontrol): ziyaretçi her işlemde UNAUTHENTICATED, bootstrap/me/counts/sync/config/manifest REST ile aynı, `ifHash`, tercihler, oturum, Bearer ve `X-FluxWP-Token`, bilinmeyen belirteç 401, `mcp` belirteci 403, `X-Flux-Locale`. İstemci taşıyıcısı: `packages/graphql/test/flx-app.test.ts`.
