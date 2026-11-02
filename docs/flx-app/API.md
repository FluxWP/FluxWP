# Flux App API

Masaüstü uygulaması (Flux Masaüstü), MCP istemcileri ve betikler için giriş ve uygulama uçları. Prefix `flxapp_`, REST kökü `flxapp/v1`.

## Giriş yolları

| Yol | Kim için | Nasıl |
|---|---|---|
| OAuth 2.0 + PKCE | Flux Masaüstü, MCP istemcileri (Claude vb.) | Tarayıcıda WordPress girişi + onay, uygulamaya kod döner |
| Kişisel API anahtarı | Betikler, elle yapılandırılan MCP | Kullanıcılar › Uygulama erişimi › Anahtar oluştur |
| Kodla giriş (OTP) | Bağışçılar, mobil uygulama | E-posta ya da telefona 6 haneli kod |
| Çerez + `X-WP-Nonce` | wp-admin içindeki ekranlar | WordPress'in kendi yolu |

Belirteç her REST ucunda çalışır (yalnız `flxapp/v1` değil): `Authorization: Bearer flxat_…` ya da `flxak_…`. Sunucu `Authorization` başlığını düşürüyorsa `X-FluxWP-Token: <belirteç>`. Kullanıcı, belirtecin sahibidir; yetkiler onun rolünden gelir. Geçersiz/süresi dolmuş belirteç `401 flxapp_invalid_token` döner (sessizce anonim olmaz).

Önekler: `flxat_` erişim (varsayılan 60 dk), `flxar_` yenileme (varsayılan 60 gün, her kullanımda döner), `flxak_` API anahtarı. Veritabanında yalnız sha256 durur.

## OAuth uçları

Metadata: `GET /.well-known/oauth-authorization-server` (RFC 8414). MCP için `GET /.well-known/oauth-protected-resource/wp-json/flx-mcp/v1/mcp` (RFC 9728); MCP ucu 401'de `WWW-Authenticate: Bearer resource_metadata="…"` başlığı verir.

1. Yetkilendirme: `GET /wp-login.php?action=flxapp_authorize&response_type=code&client_id=flx-masaustu&redirect_uri=…&state=…&code_challenge=<S256>&code_challenge_method=S256&scope=flx`
   Giriş yoksa WordPress giriş sayfasına gider, sonra onay ekranı. Dönüş: `redirect_uri?code=flxcd_…&state=…&iss=<site>`; ret: `error=access_denied`.
2. Belirteç: `POST /wp-json/flxapp/v1/oauth/token` (form): `grant_type=authorization_code&code&redirect_uri&client_id&code_verifier` → `{access_token, token_type, expires_in, refresh_token, scope}`. Kod tek kullanımlık, 10 dk.
3. Yenileme: `grant_type=refresh_token&refresh_token&client_id` → yeni çift; eski yenileme belirteci iptal. Kullanılmış yenileme belirteci tekrar gelirse oturumun tamamı kapanır.
4. İptal: `POST /wp-json/flxapp/v1/oauth/revoke` `token&client_id` (RFC 7009; yenileme belirteci oturumu kapatır).
5. Dinamik kayıt: `POST /wp-json/flxapp/v1/oauth/register` JSON `{client_name, redirect_uris[], token_endpoint_auth_method?}` (RFC 7591; ayardan kapatılabilir, saatte 10/IP).

İstemciler: yerleşik `flx-masaustu` (genel, gizli anahtar yok). Yönlendirme: `flxmasaustu://oauth/callback` ya da `http://127.0.0.1:<herhangi port>/callback` (RFC 8252 loopback). Yönetici başka istemci ekleyebilir (gizli anahtarlı ya da değil). Filtre: `flxapp_builtin_clients`.

## Kodla giriş (bağışçı)

Mobil/masaüstü uygulamada personel olmayan kullanıcılar (bağışçı, müşteri) için. Ayar: Uygulama erişimi › Kodla giriş.

1. `POST /wp-json/flxapp/v1/otp/start` `{login, client_id}`: `login` e-posta ya da telefon (her biçim; TR için 90 eklenir). Cevap her zaman `{sent:true, via, via_label, ttl}`; hesabın olup olmadığı belli edilmez.
2. `POST /wp-json/flxapp/v1/otp/verify` `{login, code, client_id}` → OAuth belirteç cevabı (`access_token`, `refresh_token`…), yenileme normal `oauth/token` ile.

- Kod 6 hane, 10 dk, 5 deneme, tek kullanımlık. Sınır: IP başına 10 dk'da 6, giriş değeri başına 3 istek.
- Kullanıcı bulma: e-posta; telefon için Flux Roles `flxr_user_by_phone` varsa o; yoksa usermeta `billing_phone`, `flxapp_phone`, `flxc_phone`, `flx_phone`, `phone` (son 10 hane). Filtre `flxapp_otp_find_user( $user|null, $type, $value )`.
- Hesap yoksa ama aynı e-posta/telefonla WooCommerce siparişi (bağış) varsa müşteri hesabı açılır, eski siparişler bağlanır (ayar; aksiyon `flxapp_otp_customer_created( $user_id, $order )`).
- Gönderim: filtre `flxapp_otp_send( null, $user, $type, $value, $code, $text )` bir kanal adı dönerse o kullanılır; yoksa telefonda Flux Chat `flxc_send_private_text` (WhatsApp, panelde görünmez; eski sürümde `flxc_send_text`), olmazsa e-posta.
- Personel (`edit_posts`) varsayılan olarak kodla giremez (filtre `flxapp_otp_allowed`). Başarılı giriş: aksiyon `flxapp_otp_login( $user_id, $type )`.

## Uygulama uçları

| Uç | Açıklama |
|---|---|
| `GET /flxapp/v1/me` | `user` (id, ad, e-posta, avatar, roller, dil, is_admin), `site` (ad, adresler, ikon, dil, saat dilimi), `auth` (tür, istemci, bitiş), `menu`, `modules`, `counts`, `web_session` |
| `GET /flxapp/v1/counts` | Yalnız sayaçlar (Flux Admin `flxa_counts` + `flxapp_counts` filtresi). Hafif yoklama için. |
| `POST /flxapp/v1/session` | `{path: "admin.php?page=flx-tasks"}` → `{url, expires_in: 60}`: tek seferlik panel girişi. Uygulama yerel ekranı olmayan menüyü web görünümünde bu adresle açar. |
| `POST /flxapp/v1/logout` | Bu belirtecin oturumunu kapatır. |

### Menü (`menu`)
Kullanıcının wp-admin'de gördüğü sol menünün aynısı: `[{id, slug, title, url, icon (dashicons-* ya da boş), badge, items: [{slug, title, url, badge}]}]`.
Kaynak: kullanıcı wp-admin'i her açtığında kendi menüsü saklanır; hiç açmamışsa yöneticinin son menüsü yetkilere göre süzülür. Filtre `flxapp_user_menu( $items, $user )`.

### Yerel modüller (`modules`)
Uygulamada yerel ekranı olan paketler kaydolur; uygulama o menü öğesini web görünümü yerine kendi ekranıyla açar:

```php
add_filter( 'flxapp_modules', function ( $m ) {
	$m['studio'] = array(
		'title'   => __( 'Stüdyo', 'flx-studio' ),
		'cap'     => 'flxst_use',               // yoksa listede çıkmaz
		'menu'    => 'flx-studio',              // wp-admin menü slug'ı
		'api'     => rest_url( 'flx-studio/v1' ),
		'icon'    => 'image',
		'version' => FLXST_VERSION,
	);
	return $m;
} );
```

## MCP
Flux SEO & MCP ucu (`/wp-json/flx-mcp/v1/mcp`) değişiklik gerektirmeden OAuth belirteci ve `flxak_` anahtarıyla da çalışır (kullanıcıyı bu eklenti belirler). Claude gibi istemciler sunucu adresini verince metadata'dan kendini kaydeder, kullanıcı onay ekranında izin verir.
