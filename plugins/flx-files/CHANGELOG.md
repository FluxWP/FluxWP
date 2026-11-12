# Changelog

## 0.1.1
- The screen fits the window: the page no longer scrolls; the file list, the editor's folder tree and the file content scroll on their own. Works on mobile too.
- AI explain and AI edit open as a panel right under the editor toolbar instead of a modal; the file stays visible.
- Refreshing the folder tree updates it in place: open folders stay open and only changed rows are added or removed.
- Storage shows what the hosting account uses (measured in the background, cached for 12 hours) against the new "Disk quota (GB)" setting, not the whole server disk.
- The "⋯" button uses the normal button style in the light theme.

## 0.1.0

- New plugin: modern file manager for WordPress administrators.
- Browser: list and grid views, sorting, breadcrumbs with history, name and content search, right-click menu (open, new, download as ZIP, archive, extract, cut/copy/paste, duplicate, copy or move to, copy path, info, permissions, share, versions, rename, delete), keyboard shortcuts, drag and drop moving.
- Uploads: drag and drop files and whole folders, chunked uploads that work past the hosting upload limit, conflict prompt.
- Editor: file tree, tabs, WordPress CodeMirror with syntax highlighting, find and replace, Ctrl/⌘ + S, conflict check when the file changed on the server.
- Safety: never leaves the WordPress folder (no `..`, no symlinks outside), protected paths (wp-config.php, .htaccess, core folders, the plugin itself) cannot be moved or deleted, deletes go to a trash (30 days by default), every save keeps the previous version (10 by default) with diff and restore, Activity log of every change (panel, AI chat, MCP).
- Flux AI: "Explain with AI" and "Edit with AI" in the editor (shows a diff, nothing is saved until you apply and save). Secret files (wp-config.php, .env, keys) are never sent.
- MCP and AI chat tools: `flxfm_list`, `flxfm_read`, `flxfm_search`, `flxfm_info`, `flxfm_activity`, `flxfm_trash_list`, `flxfm_write`, `flxfm_replace`, `flxfm_mkdir`, `flxfm_move`, `flxfm_copy`, `flxfm_delete`, `flxfm_restore`. Changes ask for approval in the chat; external MCP clients are read-only unless allowed in Settings; code files need a separate setting.
- Document library on the site: share an uploads folder and show it with `[flx_files folder="…"]` (subfolders, search, download).
- System check rows, Turkish and English translations.
