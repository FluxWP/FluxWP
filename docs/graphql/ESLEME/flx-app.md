# Flux App (flx-app): REST → GraphQL eşlemesi

Paket sürümü: 0.5.0 · Önek: `flxapp_` · Sahip thread: Faz 1 / flx-app · Durum: bitti (PR `gql/flx-app-1`)

## Özet

- REST uçları: hepsi duruyor (bootstrap, me, counts, sync, config, manifest, dashboard, prefs, session, session/end, logout GraphQL'e de açıldı) · AJAX: 0 · admin-post işleyici: 4 · Admin sayfası: 1 · MCP/AI aracı: 0
- Servis: `includes/services/class-flxapp-service.php` (`FLXAPP_Service`). REST oturum/çıkış/oturum sonlandırma da bu servisi çağırır.
- Açık belgeler (`FlxJSON`): `flx-app-config/1`, `flx-app-manifest/1`, `flx-app-bootstrap/1` (sürümlü, istemcide doğrulanır).
- Kit katkısı: yok. Pakete özgü: belirteç kimliği + `flx` kapsamı kapısı + CSRF atlama GraphQL'de (`includes/tokens.php`).

## REST uçları

| Uç | Yöntem | → GraphQL | Not |
|---|---|---|---|
| `/app/bootstrap` | GET | `flxAppBootstrap` | Herkese açık, dile göre değişir |
| `/me` | GET | `flxAppMe` | |
| `/counts` | GET | `flxAppCounts` | |
| `/sync` | GET | `flxAppSync(since)` | |
| `/config` | GET | `flxAppConfig(ifHash)` | ETag/304 → `ifHash`/`notModified` |
| `/manifest`, `/manifest/<id>` | GET | `flxAppManifest(ifHash)` | |
| `/dashboard` | GET | `flxAppDashboard` | |
| `/prefs` | GET / POST | `flxAppPrefs` / `flxAppPrefsUpdate` | |
| `/session` | POST | `flxAppSessionCreate` | |
| `/session/end` | POST | `flxAppSessionEnd` | |
| `/logout` | POST | `flxAppLogout` | |
| `/oauth/*`, `/otp/*`, `/me/phone*`, `/devices*`, `/settings*`, `/app/brand` | — | REST'te kalır | [ISTISNALAR](../ISTISNALAR.md) |

## Admin sayfaları

| Sayfa | Durum |
|---|---|
| `admin.php?page=flxapp-settings` ("Uygulama" ayarları, 7 sekme) | React ekranı `flxapp-settings`, varsayılan; klasik sayfa "Klasik görünüm" bağlantısıyla (0.6.0). `flxAppAdmin*` işlemleri. |
| `users.php?page=flx-app` / `profile.php?page=flx-app` ("Uygulama erişimi") | React ekranı `flxapp-access`, varsayılan; klasik bağlantıyla. `flxAppAccess`, `flxAppKeyCreate`, `flxAppAccessRevoke`, `flxAppDeviceRevoke`. |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-app:bootstrap` | `flxAppBootstrap` | `flxapp_bootstrap_flushed` |

## Açık sorular

Yok.
