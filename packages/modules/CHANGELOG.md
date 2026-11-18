# @fluxwp/modules

## 0.5.0
- Tasks screen: when it is shown again after being kept hidden (FluxApp desktop), it resumes polling instead of booting again, so the loaded list stays on screen.
- AI: workspace panel in the Flux AI Chat screen (files, changes with diffs and merge/reject, access settings).
- Certificates: records, new certificate and people screens (`flxs-certificates`) on the Flux GraphQL operations, with the classic pages one click away. Public verification and lookup stay on the same fields and limits as the public page.
- WhatsApp screen reads conversations, messages and live updates through Flux Chat GraphQL. Polling waits at least 10 seconds and stops while the tab is hidden.
- Tasks: removed the rules that forced the browser's own check boxes and radios, so they use the shared Flux look.
- Commerce: Flux Commerce store screens (overview, customers, subscriptions, sales report, settings) on the `flxCommerce*` GraphQL operations. React is the wp-admin default; the classic pages stay one link away. Guest receipt upload and the product feed stay on their existing URLs.
- CRM: Events screen (`events`, `event/:id`) with the same tabs as the classic page (open, archived, on the site, dashboard, people, messages, send detail) over the `flxGathering*` operations. Person-card sections from other packages render from `FlxContact.sections`. Generated types and persisted operations; 12 new operations, 40 in total.
- Shared date picker: `DatePicker` and `DateCalendar` in `@fluxwp/ui` (same look and CSS as flx-brand `date/`: quick picks, month/year menus, typed date and time, Apply, light/dark, bottom sheet on phones, site language). Flux Tasks, Ads, Analytics, AI and Social screens use it instead of the browser's date input.
- Headless: new `flxhl-headless` screen (front ends with their webhooks and secrets, WordPress pages mode, CORS and connection details, webhook log). Saving a new secret shows the front end's connection (`FLUX_WP_URL` + `FLUX_KEY`) once.
  Headless: a new front end needs only a name and an address; the webhook address, preview path and secret are filled in and sit under "Advanced".
- Mail: new `flxi-mail` screen for Settings, Templates (including the template editor), the send log and the system check. Same fields as the classic pages, on FluxWP tokens; data over the `flxMail*` GraphQL operations (write-only SMTP and IMAP secrets). Also available as a FluxApp module.
- wp-admin first load: `flxStoreNote` / `flxStoreRecord` record the queries a screen sends first (the wp-admin shell sends them to the server so the next open preloads them). Settings screens (roles, languages, reCAPTCHA, chatbot, SEO, analytics, theme, app settings) start their form from preloaded data, so no loading frame shows before the content.
- FluxWP Panel › Packages: the table opens from the data wp-admin prints into the page (no "Checking…"), the background update check no longer restarts the table request; one top bar with a prominent "Update all", a selection bar only while rows are selected, card rows on phones, no "Classic view" link.
- Tasks: compact date picker popover that fits the screen, theme-aware grey quick-pick panel.
- Tasks: document comments (text range or block, right panel, replies, edit/delete, resolve) and page protection (lock with unlock warning, restricted access) in the docs screen.
- Tasks: file upload in documents returns the file (was `{ file }`, shown as "undefined"); document files and images open through the client (no nonce-less REST link), and the shared preview window shows PDF, video, audio and text, or "Preview not available" with a Download button.
- Tasks: inline add row has no list picker; document page uses less outer spacing in narrow areas.
- Tasks: Gantt view (timeline with drag to move / resize, dependency arrows, day / week / month / quarter scale, per-view customization).
- Tasks: touch drag on the board works (mouse/pen sensor separated from touch) and auto-scrolls at the edges without scroll-snap.
- Tasks: document files as cards with a hover menu, YouTube link preview card (setting), white preview-window icons, light-theme background fix.
- Tasks: flatter list view, pill toolbar buttons, new inline add row with assignee, due date, priority and tag buttons plus Cancel and Save.
- Flux Tasks: the profile page no longer offers the classic view (the classic app is removed).
- Flux Tasks: the file preview starts below the WordPress admin bar, so its close button is not covered.
- Tasks: list row hover actions overlay the title instead of narrowing it.
- Tasks: compact, minimal list view (smaller rows and gaps, group header with select-all, "…" and "+" on hover, drag handle on rows).
- npm: README with install and usage, description and keywords for `@fluxwp/graphql` and `@fluxwp/host`.
- Classic screens removed: the "Classic view" links (AI, Analytics, Admin, Ads, App settings, Backup, Blocks, Chatbot, Files, Lang, reCAPTCHA, Roles, SEO, Setup, Social, Speed, Studio, Theme, Wiki) and the `classicUrl` prop of the app-settings `ScreenProvider` are gone. The wp-embed ScreenGuard error box no longer offers a classic link; it shows "could not be loaded" with a reload button.

## 0.4.0
- Ads: new `flxads-ads` screen for Flux Ads (report, campaigns, suggestions, Studio, season plan, tests, donor value, ad spend, audiences, send log, ad link, settings). Same look as the classic page on FluxWP tokens; data over the `flxAds*` GraphQL operations, texts from the plugin's language packs. Also available as a FluxApp module. Generated types and persisted operations for the 17 queries and 27 mutations.
- AI: the `flxai-admin` screen of Flux AI now also covers Skills (list, edit, folder files, trigger and functional tests, import, brand kit; operations `FlxAiSkills`, `FlxAiSkill`, `FlxAiSkillFile`, `FlxAiBrand`, `FlxAiSkillSave`, `FlxAiSkillToggle`, `FlxAiSkillDelete`, `FlxAiSkillsSettings`, `FlxAiSkillImport`, `FlxAiSkillFileSave`, `FlxAiSkillFileDelete`, `FlxAiSkillFileUpload`, `FlxAiSkillEvalsSave`, `FlxAiSkillTestRun`, `FlxAiBrandSave`), Scheduled tasks (list, form with recipients search, run now, pause, delete; operations `FlxAiTasks`, `FlxAiRecipients`, `FlxAiTaskSave`, `FlxAiTaskRun`, `FlxAiTaskSetActive`, `FlxAiTaskDelete`, `FlxAiTaskMarkRead`, `FlxAiTasksToggle`), Settings (providers and write-only API keys, models and tests, cache, spending limit, shared chat, image and video, web, visuals, logs) and Awaiting approval (actions and change requests, edit before approving). New operations `FlxAiApprovals`, `FlxAiApprovalDecide`, `FlxAiChangeDecide`.
- Check boxes, radios and switches share one look: new `Check` component in `@fluxwp/ui` (native input kept, `switch`, `indeterminate`, `error`), shared input styles in the React screens; Flux AI's switch uses it.
- Screen shell (Flux GraphQL 0.4.0): page-wide result store (`flxStoreKey`, `flxStoreGet`, `flxStoreSet`, `flxStoreMarkStale`) shared by every bundle on the page and seeded from the server preload; `useFlxQuery` shows a stored result at once and refreshes it in the background; queries started in the same tick go out in one batch request (`batch: false` turns it off); a mutation marks stored results stale. Modules: `preloadScreen(id)` loads a screen's code before it mounts, so it paints without a loading flash.

## 0.3.19
- Studio: new `studio` screen for Flux Studio (gallery, folders, favorites, composer with references/people/@mention, enhance, brainstorm, templates, viewer, compare, mask editor, dashboard, settings). Data over GraphQL, uploads over `/flx/v1/upload`.

## 0.3.18
- Backup: new `flxy-backup` screen for Flux Backup (dashboard with history chart, backup list with filters/bulk delete/lock/upload, detail with size breakdown, settings, back up now, restore, job progress). Same look as the classic pages on FluxWP tokens, light and dark; data over GraphQL (the job is advanced with `flxBackupStep`); texts from the plugin's language packs (`plugins/flx-backup/tools/i18n.py`).

## 0.3.17
- Wiki: new `flxwk-wiki` screen for Flux Wiki (page list with filters and trash, sections, Markdown editor with preview, General / Appearance / AI settings, addresses and guide import). Same look as the classic page on FluxWP tokens, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-wiki/tools/i18n.py`).

## 0.3.16
- Analytics: `flxan-stats`, `flxan-journeys`, `flxan-settings`, `flxan-telemetry` screens for Flux Analytics (KPIs, chart, live panel, world map, funnels, referral links, journeys, settings with the visual funnel editor, telemetry hub). Same look as the classic page on FluxWP tokens; data over GraphQL; texts from the plugin's language packs (`plugins/flx-analytics/tools/i18n.py`).

## 0.3.15
- reCAPTCHA: new `flxrc-recaptcha` screen for Flux reCAPTCHA (status, keys with Google test, protected forms, settings, allowed IPs). Data over GraphQL; texts from the plugin's language packs (`plugins/flx-recaptcha/tools/i18n.py`).

## 0.3.14
- Speed: new `flxp-speed` screen for Flux Speed (summary, records with detail and request panel, server, database, cron, system check, PageSpeed, cache, settings). Same look as the classic page on FluxWP tokens, light and dark; data over GraphQL; texts from the plugin's language packs (`plugins/flx-speed/tools/i18n.py`).

## 0.3.13
- AI: new `flxai-admin` screen for Flux AI (Overview, Logs, Billing, Systems, Tools). Same look as the classic pages; data over GraphQL; texts from the module's own en/tr packs.

## 0.3.12
- Admin theme: new `flxa-admin` screen for Flux Admin (General, Appearance, Menu and bottom bar, Permissions, Dashboard, App (PWA)). Same look and features as the classic page, including the role menu editor (drag and drop, groups, rename, hide, block, platforms, copy from role, preview, bottom bar) and the permissions table with backup/restore; data over GraphQL; texts from the plugin's language packs (`plugins/flx-admin/tools/i18n.py`).

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
