# FluxWP signature (flx-brand)

Small FluxWP mark + "Powered by FluxWP" / "Altyapı: FluxWP" link to https://fluxwp.com/, shown:

- **Admin**: footer text of every FluxWP screen (`?page=flx*`, `flx*` post types / taxonomies).
- **FluxWP theme**: `flx/credit` block in the footer bottom row (presets too). If the footer template part renders without it, a thin signature bar is appended to the footer (`add_theme_support( 'flx-credit' )`).
- **Flux QR Menu**: bottom of the public menu page.

Every package ships an identical copy in `<pkg>/includes|inc/flx-brand/`; `loader.php` registers the copy and only the newest `core.php` is loaded (once, on `after_setup_theme`).

Edit only `tools/brand/flx-brand/`, then copy to all packages (also bump `version` in loader.php):

```sh
python3 tools/brand/sync.py . ../wp-extras
```

`--check` writes nothing. It compares `loader.php`, `core.php` and `phone/` with this source (extra files inside a copy's `phone/` included) and a missing `require_once`, prints the differing paths and exits 1:

```sh
python3 tools/brand/sync.py --check . ../wp-extras
```

Date/time in the user's language (TR + EN built in, independent of core language packs; other languages fall back to WordPress): `flx_time_ago( $time, $now = null )` ("31 dakika önce" / "31 minutes ago", "dün"), `flx_date( $time, 'date'|'short'|'datetime'|'time'|php-format )`, `flx_lang()`, `flx_ts()`. `$time` is a Unix timestamp, DateTimeInterface or a GMT `Y-m-d H:i:s` string. The core loads on `after_setup_theme`, so call them from screens/hooks after that (or guard with `function_exists`).

Signed loopback job (1.2.8): `flx_job_sig( $action, $id )` is `hash_hmac( 'sha256', $action . '|' . $id, wp_salt( 'auth' ) )`. `flx_job_kick( $action, $id )` posts `action`, `id` and `sig` to `admin-ajax.php` without waiting (`blocking` false, `timeout` 0.5, `sslverify` from the `https_local_ssl_verify` filter). `flx_job_check( $action )` returns the posted id when that signature matches, otherwise `0`.

API: `flx_brand_credit( $context, $args )`, `flx_brand_mark( $size )`, shortcode `[flx_credit]`, block `flx/credit`.
Filters: `flx_brand_show` (false hides it, e.g. white-label), `flx_brand_is_screen`, `flx_brand_url`, `flx_brand_strings`.

## Phone field (1.2.0)

One phone input for every package: flag country picker with search, preferred countries on top, debounced validation with a small green tick / red cross and a warning. Country data and validation from intl-tel-input 25.14.0 (MIT, `phone/LICENSE-intl-tel-input.txt`); the validation utils load lazily.

```php
flx_phone_enqueue();
echo flx_phone_field( array( 'name' => 'phone', 'value' => '+4915123456789', 'required' => true, 'label' => __( 'Phone', 'my-domain' ) ) );
$e164 = flx_phone_normalize( $_POST['phone'], 'de' ); // '' when not a number
$digits = flx_phone_digits( $e164, 'de' );          // 4915123456789, '' when not a number
flx_phone_equal( '0532 123 45 67', '+90 532 123 45 67', 'tr' ); // both valid and the same E.164
```

- The form posts the E.164 value (`+4915123456789`) under `name`; without JS the typed text is posted.
- Filters: `flx_phone_country` (default country; WooCommerce base country, then locale), `flx_phone_preferred` (array of ISO2 on top), `flx_phone_strings`, `flx_phone_dial_codes`.
- JS: `window.flxPhone.init( el )`, `.validate( el )`, `.toE164( el )`; event `flx-phone:change` `{ valid, e164, country }`. New `[data-flx-phone]` nodes are picked up automatically.
- `sync.py` copies `phone/` into every package next to `flx-brand`.
- Dark colours (1.2.1): `body.flx-dark` only means the theme allows dark mode, so the field turns dark while the device is dark (`prefers-color-scheme`), like the theme. `html.flxfw-dark` (fluxwp.com switch) and `html.flxa-dark` (Flux Admin) force it; `html[data-flxfw-theme]` turns the device rule off.

## Form fields (1.3.0)

One look and one error system for inputs, selects, text areas, check boxes and radios in every package: an icon, a small label over a large, vertically centred value, an optional suffix (`TL`), a green tick / red mark, and the error or help line under the box. Formatting while typing and validation live in `field/field.js`.

```php
echo flx_field( array( 'name' => 'email', 'type' => 'email', 'label' => __( 'E-mail', 'my-domain' ), 'icon' => 'mail', 'required' => true, 'rules' => 'email' ) );
echo flx_field( array( 'name' => 'amount', 'label' => __( 'Amount', 'my-domain' ), 'icon' => 'edit', 'format' => 'money', 'rules' => 'min:120', 'suffix' => 'TL', 'messages' => array( 'min' => __( 'At least 120 TL.', 'my-domain' ) ) ) );
echo flx_field( array( 'name' => 'phone', 'type' => 'phone', 'label' => __( 'Phone', 'my-domain' ) ) ); // the shared phone field inside the box
echo flx_check( array( 'name' => 'terms', 'label_html' => __( 'I accept the <a href="/terms/">terms</a>', 'my-domain' ), 'required' => true ) );
```

- `flx_field()` arguments: `name, id, type (text|email|tel|number|password|search|url|textarea|select|phone), label, value, placeholder, icon, suffix, hint, help, error, required, readonly, disabled, format, rules, messages, options, rows, tick, size (sm), wide, hide_label, class, attrs, wrap_attrs, phone`. `flx_check()`: `name, id, value, label | label_html, note, checked, required, disabled, radio, error, messages, class, attrs, wrap_attrs`.
- Formats (`format`): `card, exp, digits, money, upper, name`. Rules (`rules`, space separated): `email luhn exp phone min:N max:N minlen:N maxlen:N digits:3-4`, plus `required`. Texts: `messages` per rule, else the built-in TR / EN texts (`flx_field_strings` filter).
- Icons (`flx_field_icons` filter): `user, mail, phone, card, calendar, lock, edit, coins, pin, home, building, gift, search, note, key, globe, hash`.
- A `<form data-flx-validate>` is checked on submit and the first wrong field gets the focus. Fields are checked on blur once touched.
- JS: `window.flxField.validate( el | form )`, `.setError( el, msg )`, `.clear( el )`, `.html( args )` (same markup from JS), `.icon( name )`, `.addRule()`, `.addFormat()`; event `flx-field:change` `{ valid, value }`.
- `data-flx-manual` on the wrapper: markup and look only; the owner (a React form, an own script) formats and validates itself.
- Sizes and colours are CSS variables on the wrapper or a parent: `--flx-f-h` (60px), `--flx-f-r`, `--flx-f-fs`, `--flx-f-p` (accent), `--flx-f-line`, `--flx-f-bg`, `--flx-f-ink`, `--flx-f-err`. Dark mode follows the same rules as the phone field.
- `sync.py` copies `field/` into every package next to `flx-brand`.
