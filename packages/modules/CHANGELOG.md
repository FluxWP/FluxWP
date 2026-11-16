# @fluxwp/modules

## 0.3.12
- Ads: new `flxads-ads` screen for Flux Ads (report, campaigns, suggestions, Studio, season plan, tests, donor value, ad spend, audiences, send log, ad link, settings). Same look as the classic page on FluxWP tokens; data over GraphQL; texts from the plugin's language packs (`plugins/flx-ads/tools/screen-i18n.py`).

## 0.3.11
- CRM: new `crm` screen for Flux CRM (overview, contacts, contact card, segments, journeys, duplicates, settings). Same look as the classic pages on FluxWP tokens, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-crm/tools/i18n-screen.py`).

## 0.3.10
- Blocks: new `flxbk-dashboard` screen for the Flux Block Editor (block cards with activate, settings, design library with pattern browser, preview, linked libraries and sharing). Same look as the classic dashboard on FluxWP tokens, light and dark; data over GraphQL; English and Turkish.

## 0.3.9
- SEO: new `flxseo-seo` screen for Flux SEO (General, Organization and social, SEO check, Site audit with PageSpeed, agent readiness, recommendations and report history charts, Settings). Same features as the classic tabs on FluxWP tokens; data over GraphQL; texts from the plugin's language packs (`plugins/flx-seo/tools/i18n.py`).

## 0.3.8
- FluxApp: new `flxapp-settings` (Application settings, 7 tabs) and `flxapp-access` (App access) screens. Same look as the classic pages on FluxWP tokens, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-app/tools/i18n.py`).

## 0.3.7
- Lang: new `flxl-lang` screen for Flux Lang (languages table, content types, fallback, switcher, visitor suggestion, site texts per language, AI translation info). Same look as the classic page, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-lang/tools/i18n.py`).

## 0.3.6
- Roles: new `flxr-roles` screen for Flux Roles (role cards, new role, permission table, screen after login, referral link lifetime, people). Same look as the classic page on FluxWP tokens, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-roles/tools/i18n.py`).

## 0.3.5
- Tasks: the screen has every page of the classic Flux Tasks app: documents (the classic editor code, loaded through a bridge), usage dashboard, settings, profile, project/folder/list management with menus and drag and drop, list settings (statuses, fields, access), theme menu and the AI panel.
- Tasks: styles are generated from the plugin's `assets/css/app.css` (`plugins/flx-tasks/tools/gen-react-css.py`), dictionaries from its translations (`gen-react-i18n.py`), so both screens look and read the same in 10 languages.
- Tasks: keyboard shortcuts `n` (new task) and `/` (search).

## 0.3.4
- Each module screen loads with `import()` when it opens, so the shell chunk does not include every screen.

## 0.3.3
- Task descriptions, comments and AI previews are sanitised before they are rendered as HTML.

## 0.3.2
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.

## 0.3.0

- WhatsApp and Tasks engines moved to `@fluxwp/runtime` (visibility and storage go through platform ports). This package re-exports them, so the desktop screens keep the same imports.
