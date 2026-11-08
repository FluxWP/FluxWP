# Adding and maintaining a language

[Turkish](LANGUAGES.tr.md)

WordPress plugins and themes use their own native gettext files. **Flux Lang is optional:** it manages multilingual site content, URLs and the website language picker. It is not required for plugin translations. WordPress follows the site locale on the frontend and the admin user's language preference in the dashboard. Local regional fallbacks (for example `fr_CA` → `fr_FR`) respect existing user/official translations first. Runtime translation never calls Google or an AI service.

## Adding a feature

Write gettext source strings and translation keys in English. Keep technical option keys, API fields and placeholders stable:

```php
esc_html_e( 'Save changes', 'flx-example' );
printf( esc_html( _n( '%s item', '%s items', $count, 'flx-example' ) ), number_format_i18n( $count ) );
```

Use literal gettext strings so the static scanner can inventory them. JavaScript editor strings use `wp.i18n` and `wp_set_script_translations`; PHP/localized script data also follows the native locale. Shared React resources use English keys and the configured locale registry. Existing compatibility aliases accept older Turkish IDs; new source must use English.

From the repository root, update coverage after adding messages:

```sh
python3 tools/site-translations/coverage.py --repo . --scan-source --write-readme --report /tmp/translation-coverage.json
```

The one Python command updates both main README files between generated markers. Without `--scan-source`, it uses `languages/messages.en.json` and requires only Python. With `--scan-source`, it also inventories new source calls through the local Docker/PHP scanner, so a message added only in code is still counted. Nothing is sent over the network. Multiple `--repo` arguments can update separate repositories; each README lists only its own packages.

Coverage counts nonempty, structurally valid entries compared with English. A new English/Turkish message lowers other languages' coverage until translated. Missing, invalid, obsolete and source-identical keys are included in the JSON report. Identical product names can be correct; percentages do not certify linguistic quality. `--check` optionally fails for incomplete catalogs or stale README blocks; ordinary reporting does not block a release. Native gettext falls back to English source text for missing IDs.

## Translating and compiling

```sh
python3 tools/site-translations/package_translations.py --repo . --output /tmp/package-catalogs
# Explicit developer-side network opt-in; resumable cache:
python3 tools/site-translations/package_translations.py --repo . --output /tmp/package-catalogs --translate --build
# Or import completed translator JSON files and compile offline:
python3 tools/site-translations/package_translations.py --repo . --output /tmp/validated-catalogs --import-catalogs /path/to/returned-files
```

The source is `/tmp/package-catalogs/en.json`; target files group canonical English IDs by package. Preserve keys, contexts, placeholders, HTML/code and plural slots. Recheck grammar and terminology after machine translation. Returned files are validated before compilation. Packages contain editable `messages.<locale>.json`, PO/MO/PHP data and, where needed, editor Jed JSON. English/Turkish are retained, including original Turkish translations in adapters. Incomplete new locale packs are not compiled over an existing complete pack. Run generation commands sequentially for one cache directory.

## Adding a new locale

1. Add its code, WordPress locale, translation-service code, number of plural forms and GNU plural expression to `LANGS` in `package_translations.py`. For example Italian uses `it`, `it_IT`, `it`, two forms and `(n != 1)`.
2. Export, translate or supply the new catalog; validate and compile. The generated regional fallback loader automatically includes that primary locale. Installing a WordPress core language pack remains a normal WordPress setting; this tool does not install one.
3. If the shared WordPress React embed also needs the locale, add matching core/runtime/task resource JSON, imports and normalization entries. `react_translations.py --translate` can fill missing shared resource strings from the native cache; without that flag it stays offline. Preserve `{{name}}` interpolation. Rebuild `@fluxwp/wp-embed` and sync the bundle used by plugins.
4. For a multilingual website, separately add the locale to Flux Lang's catalog or settings. For the FluxWP site preset, add it to `translations/targets.json`, export/translate the site contract, generate localized content records and apply the preset. A complete content set is linked and activated after successful import. Plugin-only users do not need this step or Flux Lang.
5. Test source/target key parity, placeholders, plural counts, actual WordPress site/user locales and editor strings with Flux Lang disabled. Check mobile/desktop and light/dark; RTL languages also need layout and technical-text isolation checks. Refresh the README coverage table and use the normal version/changelog/PR workflow.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
