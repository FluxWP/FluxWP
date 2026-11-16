# Flux reCAPTCHA GraphQL API

Uç: `/graphql` (Flux GraphQL). Ayar, form listesi, durum ve anahtar testi `manage_options` ister. `flxCaptcha` herkese açıktır (site anahtarı zaten herkese açık).
İşlemler `plugins/flx-recaptcha/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql`.
**Gizli anahtar hiçbir yanıtta dönmez**: yalnız `secretSaved`, `secretHint` (`••••ab12`) ve `secretLocked` (wp-config sabiti) gelir.

## Okuma

```graphql
query {
  flxRecaptchaSettings { version siteKey siteKeyLocked secretSaved secretHint secretLocked threshold domain
    v2Theme v2Size hideBadge skipLoggedIn failOpen allowIps currentIp keysVerified disabledByConst formsOverlap }
  flxRecaptchaForms { key label description group groupLabel available enabled passed blocked }
  flxRecaptchaStatus { state version formsOn formsAvailable passed blocked
    lastTest { time ok message } lastError { time kind reason letPass } }
}
```

## Yazma

```graphql
mutation {
  flxRecaptchaSettingsUpdate(input: { threshold: 0.6, failOpen: true, forms: [{ key: "login", enabled: true }] }) {
    settings { threshold keysVerified } status { state } errors { code field message }
  }
}
```

| Mutation | Girdi | Dönüş |
|---|---|---|
| `flxRecaptchaSettingsUpdate` | `version`, `siteKey`, `secret`, `threshold`, `domain`, `v2Theme`, `v2Size`, `hideBadge`, `skipLoggedIn`, `failOpen`, `allowIps`, `forms: [{key, enabled}]` (hepsi isteğe bağlı) | `settings`, `forms`, `status` |
| `flxRecaptchaKeysTest` | `version`, `siteKey`, `secret` (boş = kayıtlı), `token` (tarayıcıdan Google'ın verdiği) | `result {ok message score}`, `settings`, `forms`, `status` |

- Anahtar ya da sürüm değişirse koruma, anahtarlar test edilene kadar kapalı kalır (yanlış anahtarla kilitlenme olmaz). Test geçince anahtarlar kaydedilir ve koruma açılır.
- Google reddederse hata değil `result.ok = false` döner, hiçbir şey değişmez.
- Hatalar payload `errors` içinde: `INVALID_INPUT` (`field`: `version`, `siteKey`, `secret`, `threshold`, `domain`, `allowIps`, `forms.N.key` …). Doğrulama başarısızsa hiçbir şey yazılmaz.

## Ziyaretçi: `flxCaptcha(context)`

```graphql
query FlxCaptcha($context: String!) {
  flxCaptcha(context: $context) { enabled version siteKey action scriptUrl theme size hideBadge tokenField tokenHeader }
}
```

Headless form ya da mobil uygulama bunu okur, Google betiğini `scriptUrl` ile yükler, token'ı mutation'a `captchaToken` olarak (ya da `X-Flx-Captcha` başlığıyla) gönderir. `enabled: false` ise korumasızdır (anahtar yok, form kapalı ya da eklenti yok). Yanıt ziyaretçi düzeyindedir (IP ve oturuma bağlı değil), bu yüzden önbelleğe alınır; ayar değişince `flx-recaptcha:config` tag'i temizlenir. Kayıtlı olmayan bağlam `null` döner.

## Başka paketler için doğrulama

Herkese açık bir mutation kendi işini yapmadan önce token'ı doğrulatır:

```php
// 1) Formu kaydet (API.md §1)
add_filter( 'flx_captcha_contexts', function ( $c ) {
	$c['my_form'] = array( 'label' => 'My form', 'description' => 'My public form', 'group' => 'other', 'action' => 'my_form' );
	return $c;
} );

// 2) Mutation çözümleyicisinde (Flux reCAPTCHA kurulu değilse hiçbir şey yapmaz)
flx_captcha_gql_verify( 'my_form', $input['captchaToken'] ?? null );   // başarısızsa INVALID_INPUT (field captchaToken) fırlatır
```

- Mutation girdisine `captchaToken: String` ekleyin; istemci `flxCaptcha('my_form')` ile ne yapacağını öğrenir.
- Eşdeğeri: `do_action( 'flx_captcha_gql_verify', 'my_form', $token )`. Servis sınıfı: `FLXRC_Captcha_Service::config()`, `::verify()` (true | WP_Error), `::guard()` (fırlatır).
- Doğrulama; koruma kapalıysa, form kapalıysa, izinli IP'de, giriş yapana sorulmuyorsa ya da Google'a ulaşılamayıp "geçir" açıksa geçer.
- `INVALID_INPUT` `extensions.reason`: `flxrc_missing` (token yok), `flxrc_score` (v3 puanı düşük), `flxrc_failed` (Google reddetti).
