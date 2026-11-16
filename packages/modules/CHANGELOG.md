# @fluxwp/modules

## 0.3.8
- reCAPTCHA: new `flxrc-recaptcha` screen for Flux reCAPTCHA (status, keys with Google test, protected forms, settings, allowed IPs). Data over GraphQL; texts from the plugin's language packs (`plugins/flx-recaptcha/tools/i18n.py`).

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
