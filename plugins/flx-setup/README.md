# Flux Setup

**Preset engine.** Builds a site in one step from a preset file (`preset.json`): organisation profile, plugin settings, colours and fonts, header/footer, templates, pages, menus and media. The same format changes an existing site through approved patches from the Flux AI chat or an external MCP client.

- **Preview, apply, undo.** Every row shows current / preset / status. Every run is in the history and can be undone.
- **Your manual edits are kept.** Preset updates never overwrite a value you changed in the admin.
- **Migrations.** Preset updates are previewed and applied (or skipped) one by one.
- **Several presets** on one site (e.g. saplings + qurban).
- **Export this site as a preset**, import JSON. Secrets are never written to the file.
- **Organisation profile** (`flx_profile()`): packages read the name, signature, WhatsApp, IBAN from here.
- **AI and MCP**: `preset_schema`, `preset_export`, `preset_patch_preview`, `preset_patch_apply`, `preset_revert`.

Format: [docs/flx-setup/FORMAT.md](../../docs/flx-setup/FORMAT.md) · For packages: [API.md](API.md)

[Türkçe](README.tr.md)
