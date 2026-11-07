[Türkçe](README.tr.md) · **English**

# FluxWP mobile app

iOS and Android app for FluxWP sites (Expo SDK 58, React Native 0.88, expo-router). One app, many WordPress sites and accounts: tabs, drawer and top bar come from the site (`flxapp/v1/config`), screens from package manifests (`flxapp/v1/manifest`), and every admin page without a native screen opens in an embedded, signed-in web view.

## What is in this foundation

- **Sites and accounts:** type the site address → discovery (`/wp-json/`, `flxapp/v1/app/bootstrap`) → sign in. OAuth 2.0 + PKCE in the in-app browser (client `flx-mobil`, redirect `fluxwp://oauth/callback`, `prompt=login` for a second account on the same site), sign-in code (OTP), API key, or WordPress Application Passwords on sites without Flux App (limited mode). Tokens live in the Keychain / Android Keystore (`expo-secure-store`); the account list and per-account caches in MMKV. Quick switcher from the top bar and the drawer.
- **Configurable shell:** up to five tab slots, side drawer (pins, groups, footer) and top bar, all from `flx-app-config/1`. Targets (`screen:`, `module:`, `menu:`, `web:`, `url:`, `native:`) resolve manifest → native screen → web view.
- **Theme:** `@fluxwp/tokens` palette + brand colors + the site theme (`config.theme`, derived from the WordPress theme on the server) + white-label overrides; light/dark follows the device or the user's choice. Unistyles swaps the theme per account without re-rendering.
- **Manifest screens:** list (FlashList, search, filters, pull-to-refresh, paging, row actions, FAB), detail (fields, stats, chips, timeline, content, actions), form (react-hook-form + valibot), dashboard; empty / error / loading / offline states. Unknown types and render errors fall back to the web view.
- **Web view fallback:** one-time SSO address (`flxapp/v1/session`), `FLXApp/<version> (mobile; <brand>; <os>)` user agent (flx-app hides the admin bar and menu), link capture (manifest `routes` open natively, other hosts in the browser, a dropped session re-signs in once), bridge messages from a whitelist.
- **Cache:** TanStack Query, one persisted client per account (own MMKV instance), cache-first with background refresh; reads work offline.
- **White-label hook:** `FLUX_BRAND=<slug>` picks `brands/<slug>/brand.json` (name, icon, bundle id, scheme, colors, `allowedSites`, `locked`, theme). The FluxWP brand leaves all of these open.

Shared logic is not here: it lives in platform-neutral packages the desktop app uses too — `@fluxwp/core` (API, auth, accounts), `@fluxwp/tokens` (theme), `@fluxwp/manifest` (contracts, JSON Schemas, templates, targets) and `@fluxwp/runtime` (headless React: account scope, hooks, controllers, shell model, web session). This folder only draws.

## Develop

```bash
bun install                      # from the repo root
bun run --filter @fluxwp/mobile start      # Expo dev server (dev client; Expo Go is not a target)
bun run --filter @fluxwp/mobile web        # web preview (development only)
```

"Try the demo account" on the first screen runs without a server: the contract fixtures in `packages/manifest/fixtures` plus sample data.

Checks: `bun run typecheck`, `bun run lint`, `bunx expo-doctor`, `bunx expo export --platform all` (all in `apps/mobile`); behaviour tests are in `packages/manifest` and `packages/runtime` (`bun run test` at the root). CI: `.github/workflows/mobile-ci.yml`.

Screenshots of the web preview: `bunx expo export --platform web --output-dir dist && bunx serve -s dist -l 8090`, then `bun scripts/screenshots.ts`.

## Brand and icons

`scripts/brand.cjs` (run by `postinstall` and `app.config.ts`) writes `src/brand/current.json`, `src/brand/logo.json` and `assets/brand/*` from `brands/<slug>/`. Store icons are PNGs in `brands/<slug>/mobile/`, rendered from the brand SVG with `bun run mobile:icons` (root).

## Maintainers

FluxWP is led and maintained by **Ahmet Yılmaz** ([@sharkpaw](https://github.com/sharkpaw)).
