# @fluxwp/modules

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
