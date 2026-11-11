# Flux App API

FluxWP masaüstü (Tauri), mobil (Expo, `apps/mobile`), MCP istemcileri ve betikler için giriş ve uygulama uçları. Masaüstü ve mobil **aynı sözleşmeyi** kullanır; fark yalnız `X-FluxWP-Platform: mobile | desktop` başlığıyla seçilen kabuk düzenidir. Prefix `flxapp_`, REST kökü `flxapp/v1`.

Sözleşme kaynağı: `docs/mobile-app-plan.md` (proje deposu) ve kanonik şemalar `packages/manifest/schema/*.schema.json` (`@fluxwp/manifest`). Bu sürüm (0.4.0) çıktıları bu şemalardan geçer: `bootstrap`, `config` (mobil ve masaüstü), `manifest` paketi ve her modül. Plandan sapmalar en altta.

## Uç listesi

| Uç | Kim | Ne |
|---|---|---|
| `GET /app/bootstrap` | herkes (60/dk/IP, 5 dk önbellek) | Keşif: site, giriş yolları, istemciler, tema önizlemesi, etkin FluxWP paketleri, preset, marka istemcileri, `min_app` |
| `GET /config` | giriş | Uygulama görünümü `flx-app-config/1`; platforma göre kabuk; `ETag` / `If-None-Match` → 304 |
| `GET /manifest` | giriş | `{schema, hash, platform, modules: {id: manifest}}`; ETag / 304 |
| `GET /manifest/<id>` | giriş | Tek modül; ETag / 304 |
| `GET /me` | giriş | Kullanıcı, site, menü, modüller (`manifest: bool`), sayaçlar, `caps`, `hashes`, `versions` |
| `GET /counts` | giriş | Yalnız sayaçlar |
| `GET /dashboard` | giriş | Başlangıç kartları/listeleri; `open` §6 hedefi |
| `GET /sync?since=<ts>` | giriş | `{counts, hashes: {me, config, manifest}, events[], server_time}` |
| `GET /prefs`, `POST /prefs` | giriş | Kullanıcı tercihleri (cihazlar arası) |
| `GET /settings/<option>`, `POST …` | manifestte `settings` ekranı olan | Ayar köprüsü (`flx_settings_schema`) |
| `GET /devices`, `POST /devices`, `DELETE /devices/<id>` | giriş (POST: OAuth oturumu) | Cihaz listesi, kayıt/push belirteci, oturum kapatma |
| `POST /session` | giriş | Tek seferlik webview oturum adresi (60 sn) |
| `POST /session/end` | giriş | Bu oturumun açtığı webview çerez oturumlarını bitirir |
| `POST /logout` | giriş | Bu oturumu (grant) kapatır |
| `GET /app/brand` | `manage_options` | White-label taslağı (`brand.json` önerisi) |
| `POST /oauth/token`, `/oauth/revoke`, `/oauth/register`, `GET /oauth/metadata` | herkes (oran sınırlı) | OAuth 2.0 |
| `POST /otp/start`, `/otp/verify` | herkes (oran sınırlı) | Kodla giriş (bağışçı) |
| `wp-login.php?action=flxapp_authorize` | tarayıcı | Yetkilendirme + onay ekranı |
| `wp-login.php?action=flxapp_sso` (eşdeğeri `flxapp_session`) | tarayıcı | Oturum devri iniş noktası |
| `/.well-known/oauth-authorization-server`, `/.well-known/oauth-protected-resource[/…]` | herkes | RFC 8414 / 9728 |

`/wp-json/` dizini `flxapp: {version, bootstrap}` alanını taşır; uygulama tek istekte Flux App'i tanır.

Değişken veri dönen tüm `flxapp/` uçları `Cache-Control: no-store`, `X-LiteSpeed-Cache-Control: no-cache` verir. Hosting `DELETE` kesiyorsa `POST` + `X-HTTP-Method-Override: DELETE` (ya da `?_method=DELETE`) kullanılır.

## İstek başlıkları

| Başlık | Anlam |
|---|---|
| `Authorization: Bearer flxat_…` / `flxak_…` | Kullanıcı. Her REST ucunda geçerli (yalnız `flxapp/v1` değil). |
| `X-FluxWP-Token: <belirteç>` | Sunucu `Authorization`'ı düşürüyorsa yedek (eski `X-Flx-Token` da okunur). |
| `X-FluxWP-Platform: mobile \| desktop` | Kabuk ve platform süzmesi. `ios/android` → mobile, `macos/windows/linux` → desktop. `?platform=` da olur. Başlık yoksa süzme yok, config iki kabuğu da döner. |
| `X-FluxWP-App: <bundle id>` | İsteğe bağlı uygulama kimliği (oturuma yazılır). |

Geçersiz/süresi dolmuş belirteç `401 flxapp_invalid_token` döner (sessizce anonim olmaz). Önekler: `flxat_` erişim (60 dk), `flxar_` yenileme (60 gün, her kullanımda döner), `flxak_` API anahtarı. Veritabanında yalnız sha256.

## Giriş (OAuth 2.0 + PKCE)

### İstemciler

| client_id | Uygulama | Dönüş adresleri |
|---|---|---|
| `flx-mobil` | Ana FluxWP mobil (`com.fluxwp.app`) | `fluxwp://oauth/callback`; ayrıca `fluxapp://oauth/callback` ve loopback |
| `flx-masaustu` | Ana FluxWP masaüstü (`app.fluxapp.desktop`) | `http://127.0.0.1:<port>/callback`, `http://localhost:<port>/callback`, `http://[::1]:<port>/callback` (RFC 8252, port serbest), `fluxapp://oauth/callback`, eski `flxmasaustu://oauth/callback`; ayrıca `fluxwp://` |
| `flx-mobil-<marka>`, `flx-masaustu-<marka>` | Markalı derleme | `app_clients` ayarında ne yazılıysa (özel şema ya da loopback) |

İki genel istemci de deep link ve loopback'i kabul eder; istemci platforma kilitli değildir. Geliştirici modu (Uygulama › Uygulamalar) açıksa yerel istemciler `exp://<host>[:port]/--/oauth/callback` da kabul eder (Expo dev client).

Marka istemcileri `flxapp_settings.app_clients` (preset yazar; `flx_settings_schema` kayıtlı) + `flxapp_app_clients` filtresi:

```json
[{ "id": "flx-mobil-yesilturkiye", "name": "Yeşil Türkiye", "platform": "mobile", "redirect_uris": ["yesilturkiyeist://oauth/callback"], "icon": "https://…/icon.png" }]
```

Genel şemalar (`fluxwp`, `fluxapp`, `flxmasaustu`) ve `javascript:`/`data:` vb. reddedilir. Markalı derleme yalnız kendisini tanıyan siteye bağlanır. Site ana uygulamayı kapatabilir (`generic_app = 0`): `flx-mobil`/`flx-masaustu` yetkilendirmede `error=unauthorized_client&error_description=app_not_allowed` alır, açık oturumları bir sonraki yenilemede kapanır, bootstrap `auth.clients` boş döner.

### Akış

1. `GET /wp-login.php?action=flxapp_authorize&response_type=code&client_id=flx-mobil&redirect_uri=fluxwp://oauth/callback&state=…&code_challenge=<S256>&code_challenge_method=S256&scope=flx offline_access[&prompt=login][&app_id=com.fluxwp.app]`
   - Giriş yoksa WordPress girişine gider (Flux Speed özel adresi `/fluxadmin` dahil; `flxapp_authorize`, `flxapp_sso`, `flxapp_session` aksiyonları `flxp_login_passthrough_actions` ile açıktır), sonra onay ekranı (marka istemcisinde `icon`).
   - `prompt=login`: aynı siteye ikinci hesap. Açık WordPress oturumu kapatılır, giriş ekranı istenir.
   - Dönüş: `redirect_uri?code=flxcd_…&state=…&iss=<site>`; ret: `error=access_denied`. İstemci `iss`'i doğrular.
2. `POST /flxapp/v1/oauth/token` (form): `grant_type=authorization_code&code&redirect_uri&client_id&code_verifier` → `{access_token, token_type, expires_in, refresh_token, scope}`. Kod tek kullanımlık, 10 dk.
3. Yenileme: `grant_type=refresh_token&refresh_token&client_id` → yeni çift. Kullanılmış yenileme belirteci tekrar gelirse oturumun tamamı kapanır (cihaz kaydı da silinir).
4. İptal: `POST /oauth/revoke` `token&client_id` (RFC 7009).
5. Dinamik kayıt: `POST /oauth/register` (RFC 7591, MCP). Varsayılan kapalı (`flxapp_settings.dynamic_clients`, Uygulama ayarları). Açıkken dönüş adresleri eskisi gibi doğrulanır, saatte 10/IP. Kodla gelen istemciler (`flx-mobil`, `flx-masaustu`, marka `app_clients`) kayıt kapalıyken de yetkilendirilir.

İstenen `scope` bilinen listeyle süzülür (`flx`, `mcp`, `offline_access`; bilinmeyen atılır, hiçbiri kalmazsa `flx`) ve onay ekranında eskisi gibi gösterilir. Kayıtlı kapsamı olan erişim belirteci: `flx` uygulama uçlarını (`/me`, `/counts`, `/config`, `/manifest`, `/dashboard`, `/sync`, `/prefs`, `/devices`, `/session`, `/session/end`, `/logout`, `/settings`, `/app/brand`) ve sitenin diğer REST uçlarını açar; `mcp` yalnız `/flx-mcp/`; `offline_access` yenileme içindir, tek başına uç açmaz. Kayıtlı kapsamı boş olan belirteç kısıtlanmaz. Yetmeyen kapsam `403` `insufficient_scope` ve `WWW-Authenticate: Bearer error="insufficient_scope", scope="<gerekli>"`.

Flux App olmayan site için uygulama WordPress Application Passwords (`authorize-application.php`) ile sınırlı moda düşer (plan §4.3); bu eklentinin işi değildir.

### Kodla giriş (bağışçı)

`POST /otp/start {login, client_id}` → `{sent, via, via_label, ttl}` (hesap varlığı belli edilmez). `POST /otp/verify {login, code, client_id[, app_id]}` → token cevabı. Kod 6 hane, 10 dk, 5 başarısız deneme. Özet `hash_hmac` ile saklanır, `hash_equals` ile karşılaştırılır.

Giriş yalnız mevcut hesaba yapılır. E-posta: kodun gittiği adres hesabın adresidir. Telefon: kodun gittiği numara, hesabın kayıtlı WhatsApp numarasına (`flx_phone`, ülke kodlu tam rakam; Flux Roles `flxr_normalize_phone`) eşittir. Fatura telefonu, sipariş e-postası ve eski siparişler hesap açmaz ve hesaba bağlanmaz. `flxapp_otp_find_user` filtresi de bu kuralın dışındaki kullanıcıyı yok sayar.

`otp_create` varsayılan kapalı. Açıkken hesap, e-postaya giden kod doğrulandıktan sonra yalnız o adres için açılır (telefonla hesap açılmaz, `wc_update_new_customer_past_orders` çağrılmaz, `flxapp_otp_customer_created` ateşlenmez). `otp_enabled` varsayılan açıktır.

Gönderim sınırı: çağıranın IP'si (10 dk'da 6) ve hedef e-posta/telefon (10 dk'da 3, IP'den bağımsız); hesap bulunursa hesaba da 3. Doğrulama sınırı: IP (10 dk'da 20) ve hedef (10 dk'da 5); kod bir hesaba yazılıysa o hesaba da 5. Başarısız deneme hedefe ve koda yazılır, yeni kod isteği sayacı sıfırlamaz. Personel varsayılan olarak kodla giremez (`flxapp_otp_allowed`). Ayrıntı: aşağıdaki "Filtreler".

## `GET /app/bootstrap` (herkese açık)

```json
{
  "schema": "flx-app-bootstrap/1",
  "site": { "name": "FluxWP Demo", "description": "", "url": "https://site", "admin_url": "https://site/wp-admin/", "rest_url": "https://site/wp-json/", "icon": "", "locale": "tr_TR", "timezone": "Europe/Istanbul", "https": true },
  "auth": {
    "methods": ["oauth", "otp", "apikey"],
    "clients": { "mobile": "flx-mobil", "desktop": "flx-masaustu" },
    "redirects": { "mobile": ["fluxwp://oauth/callback"], "desktop": ["http://127.0.0.1/callback", "fluxapp://oauth/callback"], "loopback_any_port": true },
    "authorize": "https://site/wp-login.php?action=flxapp_authorize",
    "login_url": "https://site/fluxadmin/",
    "token": "…/flxapp/v1/oauth/token", "revoke": "…/flxapp/v1/oauth/revoke",
    "scopes": ["flx", "mcp", "offline_access"], "pkce": "S256", "prompt_login": true,
    "otp": { "start": "…", "verify": "…" }
  },
  "apps": { "generic": true, "clients": [{ "id": "flx-mobil-yt", "name": "YT", "platform": "mobile" }], "preset": "flx-preset-yesilturkiye", "store": { "ios": "https://apps.apple.com/…" } },
  "brand": { "name": "…", "short_name": "…", "logo": "…", "logo_dark": "", "icon": "…" },
  "theme": { "mode": "system", "light": { "primary": "#1F2937", "onPrimary": "#FFFFFF", "accent": "#F59E0B", "bg": "#FBF9F6", "surface": "#FFFFFF", "text": "#16181D", "onAccent": "#12141B" }, "dark": { … } },
  "packages": [{ "slug": "flx-donate", "name": "Flux Donate", "type": "plugin" }, { "slug": "fluxwp", "name": "FluxWP", "type": "theme" }],
  "min_app": "1.0.0", "min_app_desktop": "0.3.0",
  "flx_app": "0.4.0", "api": "flxapp/v1",
  "endpoints": { "me": "…", "config": "…", "manifest": "…", "dashboard": "…", "sync": "…" }
}
```

`packages`: etkin FluxWP eklentileri (`flx-*`) ve FluxWP teması. Sürümler herkese açık değildir; girişli `/me.versions`'ta. Gizli bilgi yoktur (filtre `flxapp_bootstrap` de gizli bilgi eklememeli). Önbellek ayar, profil, eklenti ve tema değişince boşalır.

## `GET /config` (`flx-app-config/1`)

Katmanlar: site (`flxapp_app` seçeneği; Uygulama › Görünüm sekmesi ya da preset `settings.flxapp_app`) + rol (Flux Admin rol menüsü, mobil alt bar, platform gizlemesi) sunucuda birleşir. Marka, kullanıcı (`/prefs`) ve cihaz katmanlarını istemci uygular.

```json
{
  "schema": "flx-app-config/1", "hash": "5f7bd3059597", "platform": "mobile",
  "brand": { "name": "…", "short_name": "…", "logo": "…", "logo_dark": "…", "icon": "…" },
  "theme": { "mode": "system", "light": { "primary", "onPrimary", "accent", "onAccent", "bg", "surface", "text" }, "dark": { … }, "radius": "medium", "density": "comfortable", "font": { "body": "Nunito Sans", "heading": "Nunito", "files": null } },
  "shell": { … platforma göre … },
  "features": { "web_session": true, "push": true, "push_hide": false, "otp": true, "search": true, "biometric_lock": false },
  "transport": { "method_override": true, "poll_min": 10, "token_header": "authorization" },
  "webview": { "allow_hosts": ["site"], "external": "in_app_browser" },
  "links": { "privacy": "…", "support": "mailto:…", "ios": "…", "android": "…" },
  "versions": { "flx-app": "0.4.0", "min_app": "1.0.0", "min_app_mobile": "1.0.0", "min_app_desktop": "0.3.0" }
}
```

**Tema** (§8.2): `primary` = override → `flxa_accent()` (Flux Admin ayarı → `theme.json` palette `primary`) → preset `flx_profile.colors.primary` → `#4338CA`. Koyu `primary` = override → `flxa_accent_dark()` / `theme.json settings.custom.dark.primary`. `onPrimary`, `onAccent` WCAG kontrastıyla. `bg/surface/text` = override → Flux Admin tema renkleri → `theme.json` `base` (açık zemin) → varsayılan. `radius` = override → Flux Admin → `theme.json custom.radius.card` (`none` → `small`). Font: override → `theme.json typography.fontFamilies` (body, heading) → Flux Admin → `System`; yalnız `Inter, Figtree, Nunito, Nunito Sans, System`.

**Mobil kabuk** (`X-FluxWP-Platform: mobile`):

```json
{
  "nav": "tabs+drawer",
  "home": "screen:core/dashboard",
  "tabs": [{ "id": "home", "target": "screen:core/dashboard", "icon": "home", "label": "Başlangıç" }, { "id": "flxg-tasks", "target": "module:flxg-tasks", "icon": "task", "label": "Görevler" }],
  "drawer": { "enabled": true, "header": "account", "pins": ["menu:index.php"], "groups": [{ "id": "content", "label": "İçerik", "collapsed": false, "items": ["menu:edit.php", "menu:upload.php"] }], "footer": ["accounts", "settings"] },
  "topbar": { "style": "large", "show_logo": true, "site_switcher": true, "actions": [{ "target": "search", "icon": "search" }, { "target": "notifications", "icon": "bell" }] }
}
```

Sekmeler: site ayarı yoksa başlangıç + Flux Admin mobil alt bar (`bottom`) slug'ları; slug bir manifestin `menu`'süyle eşleşirse `module:`, `routes` anahtarıyla eşleşirse `screen:` hedefi olur, tekrarlar atılır. En çok 5; drawer kapalıysa ya da `nav: tabs` ise 5. yuva `more`. Kullanıcının açamadığı hedef (menüsünde, manifestinde, modüllerinde olmayan) hiçbir listede çıkmaz.

**Masaüstü kabuk** (`X-FluxWP-Platform: desktop`): ortak `ShellConfig` alanları dolu gelir (`nav: "drawer"`, `tabs: []`, `drawer` = kenar çubuğu), ek olarak:

```json
{
  "sidebar": { "pins": ["menu:index.php", "menu:flx-tasks"], "groups": [ … ], "footer": ["accounts", "settings"] },
  "topbar": { "style": "compact", "show_logo": true, "site_switcher": true, "actions": [ … ] },
  "commands": ["screen:core/post_form", "screen:core/page_form", "screen:core/media", "screen:core/comments"]
}
```

Başlık yoksa `platform: null`, `shell` mobil ve `shells: {mobile, desktop}` ikisi birden döner.

`flxapp_app` seçeneği (site katmanı; `flxapp_app_sanitize()` ile temizlenir): `theme {mode, radius, density, font{body, heading}, light{primary, accent, bg, surface, text}, dark{…}}`, `shell.mobile {nav, home, tabs[{id, target, icon, label, badge}], drawer{enabled, groups|null, pins}, topbar{style, show_logo, site_switcher, actions|null}}`, `shell.desktop {home, sidebar{groups|null, pins}, topbar{site_switcher, actions|null}, commands[]}`, `features {search, biometric_lock}`, `links {privacy, support}`, `brand {name, short_name, logo, logo_dark, icon}`. `groups: null` = Flux Admin rol düzeni.

## Hedef sözdizimi (§6)

`screen:<modül>/<ekran>`, `module:<modül>`, `menu:<wp-slug>`, `web:<admin yolu>`, `url:<https|tel|mailto>`, `native:<bileşen>`, kabuk adları `more | accounts | settings | search | notifications`.

## Manifest (`flx-app-manifest/1`)

Kayıt (PHP), filtre `flxapp_manifest( array $modules, WP_User $user )`:

```php
add_filter( 'flxapp_manifest', function ( array $m, WP_User $user ) {
	$m['crm'] = flxapp_manifest_file( FLXCRM_DIR . 'app/manifest.json', 'flx-crm', array(
		'version' => FLXCRM_VERSION,
		'caps'    => array(
			'view'   => array( 'flxcrm_view', 'flxcrm_manage' ),          // dizi = herhangi biri
			'manage' => fn( $u ) => user_can( $u, 'flxcrm_manage' ),      // callable yalnız PHP'de
		),
	) );
	return $m;
}, 10, 2 );
```

- `flxapp_manifest_file( $path, $domain, $extra )`: statik JSON'u okur; `title, label, placeholder, help, body, ok, toast, message` anahtarlarındaki Türkçe metinleri `translate( $s, $domain )` ile kullanıcı diline çevirir (`{{…}}` şablonları çevrilmez).
- Sunucu: kullanıcı diline geçer, `caps` → `can` (`{ad: bool}`) hesaplar ve çıktıdan `caps`'ı siler. `can.view` false modül gönderilmez. Her düğümde (`screens`, `actions`, alanlar, bölümler, filtreler, sütunlar, rozetler, prompt alanları) `can: "ad"` (ya da `"!ad"`, dizi = hepsi) false ise ve `platforms: ["mobile"|"desktop"]` istek platformunu içermiyorsa düğüm çıkar. Görünmeyen ekranı açan `navigate` aksiyonu, ekranlardaki aksiyon referansları (`header, row, bulk, fab, footer, quick`, `actions` bölümü, chips `add/remove`), `tabs`, `routes` ve `entry` buna göre budanır. Bağlı `menu`'sü Flux Admin'de o platforma kapalı modül gönderilmez. Yönetici kapattığı modül (`modules_off`) ve ekran (`screens_off`, `"modül/ekran"`) çıkar.
- `schema`, `can`, `entry` (yoksa ilk sekme/ekran) sunucu tarafından yazılır. `hash` = içerik özeti; değişmeyen manifest 304 döner.
- Yetki yine her REST ucunda ayrıca denetlenir; manifest yalnız arayüzü belirler, kod çalıştıramaz.

Şemanın tamamı (alanlar, kaynak, ekran türleri, bölümler, form alanları, aksiyonlar, ifade/koşul dili) kanonik olarak `packages/manifest/src/types/manifest.ts` ve `schema/manifest.schema.json`'dadır; özet:

| Alan | Açıklama |
|---|---|
| `id, plugin, version, min_app, title, icon, icon_url, menu, entry, tabs, fallback{web}, search, push{events[]}` | Üst düzey |
| `routes` | `{"<admin yolu deseni>": {screen, params}}`, `{id}` yer tutucu; webview gezintisi ve `menu:` çözümlemesi |
| `sources` | `{id: {path (wp-json'a göreli), method, query, body, paging{mode: page|wp|cursor|none, param, size_param, size, items, total, pages}, key, cache{stale, persist}, poll (0 ya da ≥10)}}` |
| `screens` | `{id: Screen}`; tür: `list, detail, form, dashboard, settings, content, tabs, native, web` |
| `actions` | `{id: Action}`; tür alanı tam biri: `request, navigate, web, open, share, copy, native, refresh`; `confirm, prompt, after{toast, back, navigate, refresh, invalidate, merge}, optimistic, when, can, tone, haptic` |

Ton kümesi: `default, accent, ok, warn, danger, muted, info`. Şablon `{{yol|filtre:arg}}`, koşul `{path, op, value}` (`eq, ne, in, nin, gt, gte, lt, lte, filled, empty, truthy, falsy`, `all/any/not`).

**Doğrulama:** `flxapp_manifest_validate( $m )` kanonik şemanın zorunlu alanlarını denetler (tür, `sources`, kaynak/ekran/aksiyon referansları, alan `label`, bölüm zorunlu alanı, form `submit.path`, ton). Hatalar Uygulama › Modüller sekmesinde ve `flxapp.manifest` sistem kontrolü satırında görünür.

### Flux App'in gönderdiği modüller (§13 öncelik sırası)

| id | Ne | Uçlar | Koşul |
|---|---|---|---|
| `core` | Başlangıç (`native: dashboard`, kaynak `flxapp/v1/dashboard`), yazılar ve sayfalar (liste, detay, hızlı düzen, yayımla, çöp, editörde aç), ortam (ızgara, yükle `native: core-media`, alt metin, sil), yorumlar (onay/bekleme/spam/çöp/yanıt, toplu), kullanıcılar (liste, detay, düzen, yeni), ayarlar (genel, okuma, tartışma), eklentiler (etkinleştir/kapat) | `wp/v2/*` | her zaman; ekranlar yetkiye göre |
| `woo` | Siparişler (liste, detay, durum, not, notlar), ürünler (fiyat/stok hızlı düzen), müşteriler | `wc/v3/*` | WooCommerce etkin |
| `flxb-donations` | Bağışlarım (özet, liste, detay), Sertifikalarım | Flux Donate `flxb/v1/me*` | Flux Donate etkin ve paket kendi `flxb-donations` manifestini kaydetmemişse |

`flxb-donations` Flux Donate'in `flxapp_modules` anahtarıyla aynıdır; paket aynı kimlikle kendi manifestini kaydedince bu kayıt kendiliğinden geri çekilir (sahiplik pakete geçer). CRM (plan §7.9) kendi paketinde (c3) yazılacak.

### Ayar köprüsü: `GET/POST /settings/<option>`

Yalnız kullanıcının gördüğü bir manifest `settings` ekranında (`option`, `fields[].name`) bildirilen seçenek ve alanlar açılır. Türler `flx_settings_schema`'dan (`bool, int, string, text, html, enum, color, url, page, object, list`). Gizli alanlar (`type: secret` ya da adında secret/password/token/key) asla dönmez (`saved: {alan: bool}`), boş gönderilirse eski değer kalır. Yazma `manage_options` (ekran `write_cap` ile değiştirebilir). Yanıt `{option, values, saved, schema}`; geçersiz değer `400 rest_invalid_param` + `data.params`.

## `GET /me` (değişen)

Eski alanlar (`user, site, auth, menu, modules, counts, bottom, web_session, layout`) aynen + `caps` (`read, edit_posts, publish_posts, edit_pages, upload_files, moderate_comments, list_users, edit_users, manage_options, activate_plugins, manage_woocommerce, edit_shop_orders, edit_products`; filtre `flxapp_me_caps`), `hashes {me, config, manifest}`, `modules[id].manifest`, `versions` (flx-app + etkin FluxWP paketleri).

## `GET /dashboard` (değişen)

Kart ve liste öğelerinde `open` §6 hedefidir (`screen:core/posts`, `screen:woo/orders`, `web:update-core.php`…), öğelerde `params` (`{id}`). Eski `mod:<id>` → `module:<id>`, çıplak slug → `menu:<slug>`; `open` yoksa `url`'den `web:<admin yolu>`. `url` korunur (hedefi olmayan uygulama web görünümünde açar).

## `GET /sync?since=<unix>`

```json
{ "counts": { "flxg": 2 }, "hashes": { "me": "…", "config": "…", "manifest": "…" }, "events": [{ "event": "core.comment", "title": "Onay bekleyen yorum", "body": "Ayşe: Harika yazı", "target": "screen:core/comments", "params": { "status": "hold" }, "at": 1793904600 }], "server_time": 1793904700 }
```

Masaüstü push almaz: açıkken `/sync`'i yoklar (15 sn, simge durumunda 60 sn) ve `events`'i yerel bildirim olarak gösterir. `events` kullanıcının son 20 olayıdır (`since`'ten sonrakiler), tercihte kapalı türler yazılmaz, "içeriği gizle" açıksa `body` boştur. `hashes` değişince uygulama ilgili ucu yeniden ister.

## `GET/POST /prefs`

```json
{ "order": [], "hidden": [], "pinned": ["menu:flx-crm"], "forceWeb": [], "tabs": ["screen:core/dashboard", "more"], "cardOrder": [], "cardHidden": [], "push": { "core.comment": false }, "events": [{ "id": "core.comment", "title": "…", "source": "core", "default": true, "on": false }], "updated": 1793904700 }
```

POST kısmi günceller (gönderilmeyen anahtar değişmez). `push` yalnız `flxapp_push_events`'te kayıtlı türleri kabul eder. Masaüstü ve mobil ortak kullanır.

## Cihazlar ve push

`POST /devices` (OAuth oturumu şart; API anahtarı/çerez `403 flxapp_no_grant`):

```json
{ "token": "ExponentPushToken[…]", "provider": "expo", "platform": "ios", "brand": "flux", "app_version": "1.0.0", "locale": "tr_TR", "name": "iPhone 16", "model": "iPhone17,1", "device_id": "…", "events": { "core.comment": false } }
```

→ `{id, name, platform, platform_label, model, brand, app, app_version, locale, push, events, created, last_seen, last_seen_label, current}`. Aynı oturumda tekrar çağrı günceller (her açılışta çağrılabilir). `token` boş = bildirim kapalı; gönderilmezse eski belirteç kalır. `platform`: `ios, android, macos, windows, linux, web`. Masaüstü belirteçsiz kaydolur (cihaz listesinde görünür). Aynı `device_id` ile yeni giriş eski oturumu kapatır; aynı belirteç başka oturumdaysa oradan silinir.

`GET /devices` kullanıcının oturumu açık cihazları. `DELETE /devices/<id>` cihazın oturumunu (grant) kapatır ve kaydı siler (kullanıcı kendi cihazını, yönetici herkesinkini). Oturum kapanınca (çıkış, iptal, çalıntı yenileme, parola sıfırlama, kullanıcı silme, istemci silme) cihaz kaydı ve push belirteci kendiliğinden silinir. Belirteç veritabanında sodium ile şifreli (`token_enc`) + sha256 (`token_hash`) durur, hiçbir uçta dönmez.

Gönderim (paketler):

```php
do_action( 'flxapp_push', $user_ids, array(
	'event'  => 'flxg.assigned',            // flxapp_push_events / manifest push.events
	'title'  => __( 'Yeni görev', 'flx-tasks' ),
	'body'   => $task_title,
	'target' => 'module:flxg-tasks',
	'params' => array( 'id' => 12 ),
) );
```

Yanıt kullanıcıya gittikten sonra (`fastcgi_finish_request` / `litespeed_finish_request`) Expo Push API'ye 100'lük paketlerle gider; cron yok. Veri: `{v: 1, site, user, event, target, params}`. `DeviceNotRegistered` dönen belirteç silinir. Son sonuç `flxapp_push_last` seçeneğinde ve `flxapp.push` sistem kontrolü satırında. Expo erişim belirteci (isteğe bağlı) Uygulama › Uygulamalar'dan girilir, şifreli saklanır. FCM/APNs: `flxapp_push_send` filtresi. Çekirdek olay: onay bekleyen yorum (`core.comment`, yorum moderatörlerine).

## Webview (gömülü panel) ve oturum devri

`POST /session {path}` → `{url, expires_in: 60}`. `path`: wp-admin göreli yolu (`edit.php?post_type=page`). Ön yüz için `{path: "/hesabim/", front: true}` ya da `{url: "https://site/…"}` (yalnız aynı host; başka host `400 flxapp_bad_path`). `embed` (varsayılan true) gömülü görünüm çerezini yazar. Adres tek kullanımlıktır; Flux Speed açıksa `/fluxadmin/?action=flxapp_sso&t=…` olarak döner. Açılan WP çerez oturumu `flxapp` + grant etiketlidir: `POST /session/end` bu grant'ınkileri bitirir; grant kapanınca da biter.

Gömülü görünüm (`embed.php`) şunlardan biriyle açılır: UA `FLXApp/<sürüm> (mobile|desktop; <marka>; <os>)`, oturum devrinin `flxapp_embed` çerezi, `?flxapp_embed=1` (`=0` kapatır). Gizlenir: admin bar, sol menü, altlık, Flux Admin alt barı ve ilerleme çubuğu, ekran seçenekleri; ön yüzde admin bar (ayar `embed_front`). Mobilde safe-area boşlukları, bildirim yığınları daralır (en çok 2), blok editörü tam ekran, giriş sayfasında site bağlantıları gizli. `flxapp_mode=dark|light` çerezi Flux Admin temasını seçer. Gövde sınıfları: `flxapp-embed flxapp-embed-mobile|desktop`.

Köprü (yalnız gömülü görünümde, `admin_footer` + `wp_footer`): `{v: 1, type: "title", title}`, `{type: "route", path, url}`, `{type: "toast", text, tone}`; sayfa betikleri `window.FLXAppBridge.send('counts'|'close'|…, data)`. Kanal: `window.ReactNativeWebView.postMessage` (mobil), Tauri olay `flxapp://bridge` (masaüstü), yoksa `window.parent.postMessage`. Uygulama JS enjekte etmez.

## White-label

- Sitedeki marka istemcileri (`app_clients`) markalı derlemenin bu siteye bağlanmasını sağlar; tanımlı değilse markalı derleme `client_id` hatası alır.
- Site ana uygulamayı kapatabilir (`generic_app`); keşif ucunda `apps.generic` ve `apps.clients` yayınlanır, preset kimliği `apps.preset` (ilk `flx-preset-*` eklentisi; filtre `flxapp_site_preset`).
- `GET /app/brand` (yönetici): `brand_json` taslağı (`slug, name, shortName, scheme, oauthClientId, colors, defaultSite, allowedSites, lockSite, loginMethods, defaultLocale, locales, mobile{scheme, oauthClientId}, supportUrl`), `assets`, `flxapp_app`, `app_clients`, `preset`, `warnings`. `tools/fluxapp` `brand:pull` bunu kullanır.
- Uygulama tarafındaki kilit (`allowedSites`, `lockSite`) uygulamanındır; sunucu kilidi istemci kimliğidir.

## Yönetici ekranı (Uygulama)

Sekmeler: Genel, **Görünüm** (tema: mod, köşe, yoğunluk, yazı tipleri, açık/koyu renk override'ları; mobil: gezinme, açılış, 5 alt bar yuvası, yan menü, üst bar; masaüstü: açılış, site değiştirici, ⌘K kısayolları; ekranlar aç/kapa; iki platform önizlemesi), **Uygulamalar** (ana uygulama, mağaza linkleri, push ve Expo belirteci, web görünümü, geliştirici modu, marka istemcileri, bağlantı bilgileri, deneme bildirimi), Modüller (manifest / yerel ekran / web sütunu, manifest hataları), Menü, OAuth istemcileri, Tüm erişimler (cihazlar + oturumlar). Kullanıcılar › Uygulama erişimi: Cihazlarım (oturumu kapat), bağlı uygulamalar, API anahtarları.

## Filtreler ve aksiyonlar

| Ad | Tür | Ne |
|---|---|---|
| `flxapp_manifest( $modules, $user )` | filtre | Manifest kaydı |
| `flxapp_modules` | filtre | Yerel (native) modül kaydı (geriye uyumlu) |
| `flxapp_app_clients( $clients )` | filtre | Marka OAuth istemcileri |
| `flxapp_builtin_clients`, `flxapp_redirect_ok( $ok, $client, $uri )` | filtre | İstemciler, dönüş kararı |
| `flxapp_bootstrap`, `flxapp_config( $cfg, $user, $platform )`, `flxapp_theme`, `flxapp_min_app`, `flxapp_webview_hosts` | filtre | Keşif ve görünüm |
| `flxapp_push_events`, `flxapp_push_send( $res, $provider, $devices, $msg )`, `flxapp_push_expo_url` | filtre | Bildirim türleri ve taşıyıcı |
| `flxapp_push( $user_ids, $msg )` | aksiyon | Bildirim gönder (masaüstü için `/sync` olayı da yazar) |
| `flxapp_grant_revoked( $grant )`, `flxapp_user_revoked( $user_id )`, `flxapp_device_registered( $id, $user_id )` | aksiyon | Oturum ve cihaz olayları |
| `flxapp_dashboard( $d, $user )`, `flxapp_counts`, `flxapp_user_menu`, `flxapp_menu_icon_url`, `flxapp_me_caps`, `flxapp_site_preset` | filtre | `/me`, başlangıç |
| `flxapp_otp_*`, `flxapp_brand_logo`, `flxapp_oauth_metadata`, `flxapp_desktop_*` | filtre | Önceki sürümlerden |

## Plandan sapmalar (0.4.0)

1. **Tek PR:** plan b2/b3/b4'ü 0.4.0/0.5.0/0.6.0 diye ayırıyordu; iş tek PR olarak istendiği için hepsi 0.4.0'da.
2. **Masaüstü kabuğu:** plan masaüstünde yalnız `sidebar/topbar/commands` gösteriyordu; kanonik `ShellConfig` `nav, tabs, drawer, topbar.style/show_logo` istediği için masaüstü yanıtı bunları da taşır (`nav: drawer`, `tabs: []`, `drawer` = `sidebar`). Platform yoksa `shells: {mobile, desktop}`.
3. **Çift yönlü yönlendirme:** Ahmet'in isteğiyle `flx-mobil` ve `flx-masaustu` ikisi de deep link + loopback kabul eder (plan: mobil yalnız `fluxwp://`).
4. **Bootstrap ekleri:** `packages` (etkin FluxWP paketleri), `apps` (genel uygulama, marka istemcileri, preset, mağaza), `auth.redirects`, `auth.login_url` (özel giriş adresi: oturum düşmesi bunun üzerinden anlaşılır), `endpoints`, `min_app_desktop`. `min_app` ve `flx_app` kanonik şemaya uygun olarak metin.
5. **Çekirdek başlangıç** `type: native, component: dashboard` (`@fluxwp/manifest` `CORE_MANIFEST` ile aynı); kartlar `flxapp/v1/dashboard`'dan.
6. **Ek alanlar** (şema `additionalProperties`'e izin verir): form alanında `load` (okuma yolu, ör. `title.raw`), `upload`, `preview`, `multiple`, `currency`, `create` (yeni etiket isteği); detay başlığında `image`, `badges`; `push_hide` özelliği; tema `onAccent`; config `links.ios/android`.
7. **`screens_off`:** plan yalnız modül kapatmayı anıyordu; ekran düzeyinde kapatma eklendi (kapatılan ekran webview'de açılır).
8. **Oturum devri aksiyonu** `flxapp_sso` (masaüstü 0.2.x bunu kullanıyor); runtime'ın beklediği `flxapp_session` eşdeğer ad olarak eklendi. Runtime SSO adresini birebir eşleştirdiği için ikisi de çalışır.
9. **Gönderilmedi:** App Links / Universal Links (gap 15, d aşaması), `login_hint`. CRM manifesti kendi paketinde (c3).

## MCP

Flux AI & MCP ucu (`/wp-json/flx-mcp/v1/mcp`) `mcp` kapsamlı OAuth belirteci ve `flx mcp` kapsamlı `flxak_` anahtarıyla çalışır. Kapsamı boş eski belirteç de girer. MCP ucu 401'de `WWW-Authenticate: Bearer resource_metadata="…"` verir. Dinamik kayıt kapalıyken istemci elle eklenir ya da API anahtarı kullanılır; açıkken metadata'dan kendini kaydeder, kullanıcı onay ekranında izin verir.
