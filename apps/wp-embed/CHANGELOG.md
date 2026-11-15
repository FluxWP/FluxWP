# Changelog

## 0.3.4
- Mount options carry the host extras a module screen can use: `siteName`, `adminUrl`, `appIcon`, `accent` (sets `--brand`), `aiEmbed`, `startPage` and `classicUrl`.
- CSS prefixing keeps `html.<class>` / `body.<class>` conditions (`body.flxg-dragging *` becomes `body.flxg-dragging .flux-root *`). Before, such rules applied all the time inside `.flux-root`.
- `react-i18next` and `i18next` are deduplicated. The screen chunks read the same i18n context as the mount, so the screen follows the WordPress user language.

## 0.3.3
- A chunk build mounts once from `window.FluxModulesOptions` whether the entry runs before or after DOMContentLoaded. `window.FluxModules.mount` remains the single-file entry point.

## 0.3.2
- The default build stays one IIFE file. `FLUX_EMBED_CHUNKS=1` emits ES chunks named `flux-<module>-<hash>.js` beside the script.

## 0.3.1
- Support all ten shared locales and isolate Arabic embed direction.
