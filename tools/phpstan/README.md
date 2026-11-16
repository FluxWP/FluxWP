# PHPStan

Two configurations, both PHP 8.1–8.5 (`phpVersion` 80100–80500), WordPress + WooCommerce + WP-CLI stubs.

| Config | Scope | Level | CI |
|---|---|---|---|
| `phpstan.neon` | Flux GraphQL, and each package's `services/` and `graphql/` folders once migrated | max | `strict` job |
| `legacy.neon` | every other plugin, theme and `tools/brand` | 5 + `legacy-baseline.neon` | `legacy` job |

```sh
cd tools/phpstan && composer install
php -d memory_limit=4G vendor/bin/phpstan analyse -c phpstan.neon
php -d memory_limit=4G vendor/bin/phpstan analyse -c legacy.neon      # ~4 min cold, seconds with .cache
```

## Packages in the strict config (GraphQL work package)

Nothing to list: `strict-packages.php` finds every plugin with `includes/services` or `includes/graphql`
(a theme: `inc/services`, `inc/graphql`), analyses those folders at level max and scans the rest of
`includes/` for symbols. So parallel package pull requests do not edit `phpstan.neon` or `bootstrap.php`.

1. Put the strict code in those two folders.
2. Constants: `strict-bootstrap.php` defines every `define( 'X', … )` of the main plugin file and
   `includes/*.php` with a stand-in (literal → its value, `*_URL`, `*_DIR`, `*_FILE`, `*_VERSION` → a
   placeholder). A constant whose value matters, or one defined elsewhere, goes in
   `packages/<slug>.bootstrap.php`.
3. Package specifics (`scanFiles` such as the main plugin file, `ignoreErrors`, extra `paths`) go in
   `packages/<slug>.php`, which returns an array of those keys with absolute paths (`dirname( __DIR__, 3 ) . '/plugins/<slug>/…'`).
4. Fix every error. No `@phpstan-ignore` without a reason in the same comment; no baseline for strict.
5. Shared array shapes go under `typeAliases` in `types.neon` (`FlxGqlSettings` is an example). Both configs include it, so legacy code that calls a kit function resolves the alias too.

Legacy does not analyse `plugins/*/includes/services` and `plugins/*/includes/graphql` (the strict folders), so the runtime guards strict asks for (`is_string()` on a PHPDoc `string`) are not reported there as "always true". Keep strict code in those two folders.

## Baseline

- New errors in old code fail CI. Fix them; do not add them to the baseline.
- After fixing old errors, regenerate so they cannot come back:
  `vendor/bin/phpstan analyse -c legacy.neon --generate-baseline legacy-baseline.neon`
- Plugin constants (`define( 'FLXB_URL', … )`) are discovered by `legacy-constants.php`; no list to keep.
- `stubs/optional-classes.php` declares parents of optional integrations (Elementor, FPDF).
