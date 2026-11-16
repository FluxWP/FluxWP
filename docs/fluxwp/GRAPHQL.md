# FluxWP theme: GraphQL

Source: `themes/fluxwp/inc/services` (typed DTOs), `inc/graphql/schema.php`, operations in `themes/fluxwp/graphql/operations/`.
REST, classic pages, the React screens and Flux AI all go through the same `FLX_Theme_Service`.

## Queries

| Field | Cap | Returns |
|---|---|---|
| `flxThemeSettings` | `manage_options` | Theme Settings plus screen context (WooCommerce active, home page card available, campaigns, presets URL). `consentScripts` is `null` without `unfiltered_html`. |
| `flxThemeGlobalStyles` | public (design tokens only, cached by tag `flx-theme:settings`) | `palette`, `fonts`, `darkMode`, `tableHead`, `pattern`. Source order: Site Editor changes, `theme.json`, WordPress defaults. |
| `flxThemePayLogos` | `manage_options` | `automatic`, `current`, `builtin`, `automaticList`. |
| `flxThemeSystem` | `manage_options` | Runs the System Check: `checkedAt`, `checks`, `fixLogAvailable`, `fixLog`. |
| `flxThemeMigrationSources` | `switch_themes` | Previous themes whose data can be copied. |

## Mutations

All payloads carry `errors: [FlxUserError!]!`.

- `flxThemeSettingsSave(input)`: partial input; nothing is written when any field is invalid, errors name the field (`INVALID_INPUT`). `resetConsents: true` raises the consent version. Visibility `STORE` needs WooCommerce and also updates WooCommerce site visibility.
- `flxThemePayLogosSave(input: {keys, automatic})`: `keys` hand-picks logos (`iyzico`, `a:<attachment id>`), `automatic: true` goes back to the automatic list.
- `flxThemeMigrate(input: {source})`: copies settings, templates, parts and styles from a previous theme; safe to repeat.

## Example

```graphql
query { flxThemeGlobalStyles { darkMode palette { slug color } fonts { slug fontFamily } } }
mutation { flxThemeSettingsSave(input: { ga4Id: "G-ABCD1234" }) { settings { ga4Id } errors { field code message } } }
```

The generic `flxSettings { themeSettings }` from `flx_settings_schema` stays for AI and presets; the screens use the typed fields above.

## Screens

`flxt-theme-settings` and `flxt-theme-system` (`packages/modules/src/theme`), default in wp-admin (Appearance), the classic page stays one click away ("Classic view", per-user choice). FluxApp registers both through `flxapp_modules`. Logo upload uses `wp.media`, so it works in wp-admin only.

## Tests

`tools/tests/wp-graphql/theme/contract-test.php` (run with `tools/tests/wp-graphql/run.sh`; the test site activates the theme, `THEME=` overrides).
