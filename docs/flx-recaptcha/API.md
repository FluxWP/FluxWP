# Flux reCAPTCHA · API

Flux reCAPTCHA başka eklentilerin koduna dokunmaz. Formlara WordPress/WooCommerce kancaları, korunan REST yolları ve
aşağıdaki ortak sözleşmeyle bağlanır. Kendi formunu korutmak isteyen paket bu sözleşmeyi kullanır; Flux reCAPTCHA kurulu
değilse filtre ve eylem biçimleri sessizce hiçbir şey yapmaz.

## 1. Formu kaydet: `flx_captcha_contexts`

Kaydedilen form panelde (reCAPTCHA › Korunan formlar) aç/kapa satırı olur ve son 30 gün sayıları tutulur.

```php
add_filter( 'flx_captcha_contexts', function ( $c ) {
	$c['flxi_contact'] = array(
		'label'     => __( 'İletişim formu', 'flx-mail' ),
		'desc'      => __( 'Flux Mail iletişim formu', 'flx-mail' ),
		'group'     => 'fluxwp',          // wordpress | woocommerce | fluxwp | other
		'available' => true,              // form sahibi kurulu mu
		'default'   => 1,                 // ayar hiç kaydedilmediyse açık mı
		'action'    => 'flxi_contact',    // v3 eylem adı (A-Z a-z 0-9 / _)
		// İsteğe bağlı:
		'selector'  => 'form.flxi-contact', // PHP'den alan basmıyorsanız: alan JS ile bu forma eklenir
		'before'    => 'button[type=submit]', // v2 kutucuğu bu öğenin önüne
		'routes'    => array( '#^/flx-mail/v1/contact$#' ), // REST ile gönderiyorsanız
		'rest_error'=> null,              // callable( $message ) → WP_REST_Response|WP_Error (varsayılan 403)
		'users'     => false,             // true: giriş yapmış kullanıcıya da sor
	);
	return $c;
} );
```

## 2. Klasik form (POST)

```php
// Form içinde, gönder düğmesinden önce:
do_action( 'flx_captcha_field', 'flxi_contact' );

// Gönderimi işlerken:
$ok = apply_filters( 'flx_captcha_verify', true, 'flxi_contact' );
if ( is_wp_error( $ok ) ) {
	// $ok->get_error_message() kullanıcıya gösterilir (TR/EN çevrili).
}
```

Doğrudan çağrı (kurulu olduğundan eminseniz): `flx_captcha_enabled( $ctx )`, `flx_captcha_field( $ctx )` (HTML döner),
`flx_captcha_verify( $ctx, $token = null )` (true|WP_Error). Belirteç POST alanı `flx-captcha-token`.

## 3. JS ile gönderen form

- `routes` verdiyseniz başka bir şey gerekmez: ön yüz betiği `fetch` ile bu yola giden POST isteğine `X-Flx-Captcha`
  başlığını ekler, sunucu `rest_pre_dispatch`'te doğrular.
- jQuery/XHR ile gönderiyorsanız: `window.flxCaptcha.token( 'flxi_contact' ).then( t => … )` ile belirteci alıp
  `X-Flx-Captcha` başlığına ya da `flx-captcha-token` alanına koyun, sunucuda `flx_captcha_verify` çağırın.
- Sayfada alan basmıyorsanız betiği yükletmek için: `do_action( 'flx_captcha_enqueue', 'flxi_contact' );`
  (ya da blok/kısa kod adını `flxrc_block_contexts` / `flxrc_shortcode_contexts` filtresine ekleyin).

## 4. Filtreler ve eylemler

| Ad | Ne işe yarar |
|---|---|
| `flx_captcha_required( $on, $ctx )` | Bu istekte doğrulama yapılsın mı (ör. belirli rol muaf) |
| `flx_captcha_threshold( $min, $ctx )` | v3 eşiği bağlama göre |
| `flx_captcha_result( true|WP_Error, $ctx )` | Sonucu değiştir |
| `flx_captcha_failed( $ctx, WP_Error )` | Engellenen gönderim (log, Flux Speed güvenlik kaydı vb.) |
| `flxrc_client_ip( $ip )` | Vekil arkasında gerçek IP |
| `flxrc_block_contexts`, `flxrc_shortcode_contexts` | Betiği yükleten blok / kısa kod → bağlam |

## 5. Mevcut bağlamlar

`login`, `register`, `lostpassword`, `comments`, `wc_login`, `wc_register`, `checkout` (klasik + Store API `/wc/store/v1/checkout`),
`donate` (Flux Donate `form.flxwb-form`, `flxb_action=add`), `forms` (Flux Forms `/Emsfb/v1/forms/(message|response)/add`).

### Diğer paketlere notlar
- **Flux Donate**: bağış formu bugün Flux Donate'e dokunmadan korunuyor (wp_loaded 24'te `flxb_action=add` doğrulanır; hata
  Flux Donate'in kendi `flxb_form_error` kutusunda görünür, girilen değerler korunur). İleride `FLXB_WC_Form::handle_add`
  içinde `apply_filters( 'flx_captcha_verify', true, 'donate' )` çağrılırsa bu köprü gereksiz olur; `$GLOBALS['flxb_form_error']`
  / `flxb_form_input` adları değişirse haber verin.
- **Flux Forms**: yanıt biçimi `{success:true,data:{success:false,m}}` (kendi reCAPTCHA hatasıyla aynı). Formun kendi
  reCAPTCHA seçeneği ile aynı anda açılmamalı.
- **Flux Speed**: özel giriş adresi (`/fluxadmin`) wp-login.php'yi yüklediği için ek iş gerekmez. `flx_captcha_failed` ile
  engellenen girişler güvenlik kaydına yazılabilir.

## Uygulama

Ayarlar `flxrc_settings` köprüsünden. `site_key` ve `secret` write-only. `secret` düz metin gelirse `flxrc_seal` ile mühürlenir (`s1:` / `p1:` olan değer tekrar mühürlenmez). Korunan formlar paneldedir.
