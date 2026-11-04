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
