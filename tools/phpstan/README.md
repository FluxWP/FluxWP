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

## Adding a package to the strict config (GraphQL work package)

1. Add the package's new folders under `paths` in `phpstan.neon`:
   `- ../../plugins/flx-tasks/includes/services` and `- ../../plugins/flx-tasks/includes/graphql`.
2. Add the package's constants (`FLXG_VERSION` …) to `bootstrap.php` if the new code reads them.
3. Fix every error. No `@phpstan-ignore` without a reason in the same comment; no baseline for strict.
4. Shared array shapes go under `typeAliases` in `types.neon` (`FlxGqlSettings` is an example). Both configs include it, so legacy code that calls a kit function resolves the alias too.

Legacy does not analyse `plugins/*/includes/services` and `plugins/*/includes/graphql` (the strict folders), so the runtime guards strict asks for (`is_string()` on a PHPDoc `string`) are not reported there as "always true". Keep strict code in those two folders.

## Baseline

- New errors in old code fail CI. Fix them; do not add them to the baseline.
- After fixing old errors, regenerate so they cannot come back:
  `vendor/bin/phpstan analyse -c legacy.neon --generate-baseline legacy-baseline.neon`
- Plugin constants (`define( 'FLXB_URL', … )`) are discovered by `legacy-constants.php`; no list to keep.
- `stubs/optional-classes.php` declares parents of optional integrations (Elementor, FPDF).
