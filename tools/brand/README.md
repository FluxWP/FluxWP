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

Date/time in the user's language (TR + EN built in, independent of core language packs; other languages fall back to WordPress): `flx_time_ago( $time, $now = null )` ("31 dakika önce" / "31 minutes ago", "dün"), `flx_date( $time, 'date'|'short'|'datetime'|'time'|php-format )`, `flx_lang()`, `flx_ts()`. `$time` is a Unix timestamp, DateTimeInterface or a GMT `Y-m-d H:i:s` string. The core loads on `after_setup_theme`, so call them from screens/hooks after that (or guard with `function_exists`).

API: `flx_brand_credit( $context, $args )`, `flx_brand_mark( $size )`, shortcode `[flx_credit]`, block `flx/credit`.
Filters: `flx_brand_show` (false hides it, e.g. white-label), `flx_brand_is_screen`, `flx_brand_url`, `flx_brand_strings`.

## Phone field (1.2.0)

One phone input for every package: flag country picker with search, preferred countries on top, debounced validation with a small green tick / red cross and a warning. Country data and validation from intl-tel-input 25.14.0 (MIT, `phone/LICENSE-intl-tel-input.txt`); the validation utils load lazily.

```php
flx_phone_enqueue();
echo flx_phone_field( array( 'name' => 'phone', 'value' => '+4915123456789', 'required' => true, 'label' => __( 'Phone', 'my-domain' ) ) );
$e164 = flx_phone_normalize( $_POST['phone'], 'de' ); // '' when not a number
```

- The form posts the E.164 value (`+4915123456789`) under `name`; without JS the typed text is posted.
- Filters: `flx_phone_country` (default country; WooCommerce base country, then locale), `flx_phone_preferred` (array of ISO2 on top), `flx_phone_strings`, `flx_phone_dial_codes`.
- JS: `window.flxPhone.init( el )`, `.validate( el )`, `.toE164( el )`; event `flx-phone:change` `{ valid, e164, country }`. New `[data-flx-phone]` nodes are picked up automatically.
- `sync.py` copies `phone/` into every package next to `flx-brand`.
- Dark colours (1.2.1): `body.flx-dark` only means the theme allows dark mode, so the field turns dark while the device is dark (`prefers-color-scheme`), like the theme. `html.flxfw-dark` (fluxwp.com switch) and `html.flxa-dark` (Flux Admin) force it; `html[data-flxfw-theme]` turns the device rule off.

## Shared people (1.3.0)

One person table for every FluxWP package: `{prefix}flx_people` + `{prefix}flx_people_links` (`people.php`). No package owns it; the first one that calls the API creates it, so packages work alone and in any install order. Flux CRM is the management layer on the same rows and takes over matching and writing through the `flx_person_pre_*` filters when active.

```php
$id = flx_person_ensure( array( 'name' => $n, 'phone' => $p, 'email' => $e, 'roles' => array( 'donor' ), 'source' => 'donate', 'link' => array( 'donate', 'donor:12' ) ) );
$id = flx_person_find( array( 'link' => array( 'donate', 'donor:12' ), 'email' => $e ) );
$ok = flx_person_update( $id, array( 'name' => 'New name' ) ); // true | WP_Error
$rows = flx_people_query( array( 'search' => 'ali', 'role' => 'donor', 'per_page' => 50 ) );
```

- Matching: package link, site user, e-mail, phone (exact). `ensure` only fills empty fields, adds roles and switches consents on; `update` overwrites and refuses an e-mail or phone that belongs to someone else (`flx_person_taken`).
- Phone is stored as digits (E.164 without `+`, `flx_person_phone()`), roles packed as `,donor,customer,` (`flx_person_roles()`).
- A package adds its own columns with `flx_people_columns` / `flx_people_keys`; the table is extended, never reduced.
- Actions: `flx_person_created`, `flx_person_updated`, and from Flux CRM `flx_person_merged( $kept, $removed )`, `flx_person_deleting`.
- Loads with the core on `after_setup_theme`; guard earlier calls with `function_exists`.
