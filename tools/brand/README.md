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


1.6.2: in wp-admin the field takes the admin input look (Flux Admin: 36 px, `--flxa-r` corners, `--flxa-border-strong` border; plain WordPress: 30 px, 4 px, #8C8F94). Front-end size is unchanged (`--flx-ph-h` 48 px).

## Shared events (1.4.0)

One event table for every FluxWP package: `{prefix}flx_events` (`events.php`). Flux Certificates groups certificates under an event, other packages attach their own records the same way. The event screen lives in Flux CRM (Flux Certificates shows its own when Flux CRM is not installed) and is built only from filters, so a new package shows up on it without changing the screen:

- `flx_events_columns`: columns a package owns (Flux Certificates: `target`, `base_count`).
- `flx_event_fields`: form fields with section, translated label and type (`text|textarea|date|time|number|url|image|post`).
- `flx_event_people`: `[ person_id => [ label, … ] ]` per package. `flx_event_stats`: numbers on the card. `flx_event_actions`: buttons on the card.
- Actions `flx_event_saved`, `flx_event_status`, `flx_event_deleting` (detach your records there).

API: `flx_event_get()`, `flx_events_query()`, `flx_event_save()`, `flx_event_set_status()`, `flx_event_delete()`, `flx_event_people()`, `flx_event_stats()`, `flx_event_actions()`, `flx_event_ensure_post()`.

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

## Form fields (1.5.0)

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

## Dialogs and toasts (1.6.0)

Use these instead of the browser's `confirm()` / `alert()` / `prompt()` everywhere. Loaded on every admin page; on the front end call `flx_dialog_enqueue()`.

```js
if ( await flxDialog.confirm( { title: 'Delete?', message: 'This cannot be undone.', ok: 'Delete', danger: true } ) ) { … }
await flxDialog.confirm( { title: 'Delete contact', danger: true, type: 'Jane Doe' } ); // OK stays disabled until the name is typed
await flxDialog.alert( { title: 'Saved', message: '…' } );
const v = await flxDialog.prompt( { title: 'Rename', value: 'old' } );            // null = cancelled
flxDialog.toast( 'Saved.', { type: 'ok' } );                                      // ok | bad | warn | info, timeout ms (0 = stays)
```

Without code: `<form data-flx-confirm="Message" data-flx-confirm-title="…" data-flx-confirm-ok="Delete" data-flx-confirm-danger data-flx-confirm-type="Jane Doe">` (also on a button or link). After OK the form is submitted again with the same submit button, so other `submit` handlers still run. Native `<dialog>` (focus trap, Esc), backdrop click cancels, bottom sheet under 600 px, Flux Admin light/dark colours. Texts: `flx_dialog_strings` filter. One dialog at a time; later calls wait in a queue.
