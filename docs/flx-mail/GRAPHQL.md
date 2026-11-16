# Flux Mail GraphQL (part 1)

Settings, Templates, the send log and the system check read and write through GraphQL. The classic admin screens stay one link away (`flxi_ui=classic`). Connections, WhatsApp Cloud, Send, the queue, the blocklist and the mailbox are later parts.

Registration runs on `flx_gql_register` and is skipped when Flux GraphQL is not loaded. REST keeps working either way.

## Settings

`flxMailSettings` returns the form. Passwords are `FlxMailSecret` (`set`, `mask`, `fromConfig`) and the value is never selected. `flxMailSettingsUpdate` takes the same fields. An empty secret is left as stored; `"__clear__"` removes it. `FLXI_SMTP_PASS`, `FLXI_SMTP_NOREPLY_PASS` and `FLXI_IMAP_PASS` come back as `fromConfig: true` and a write is ignored.

The kit still exposes `tagline` and `footer_art` on the generated `flxiSettings` field. `FlxMailSettings` is the screen: it has the whole form, the write-only secrets and the read-only status (last test, WooCommerce brand time, another SMTP plugin, account notes, active social-login plugins, quota, DNS, opt-out counts).

Logos go through the shared `POST /flx/v1/upload` with context `flx-mail/logo`, then `logoUploadId` / `logoWhiteUploadId` on the settings input.

## Other operations

Templates are a connection (`first` at most 50): `flxMailTemplates`, `flxMailTemplate`, `flxMailTemplateUpdate`, `flxMailTemplateReset`. WooCommerce rows only toggle and link to WooCommerce’s own editor.

`flxMailPreview` renders saved or unsaved content. `flxMailPreviewPdf` exports that render. `flxMailTestSend` sends a test (settings, a template, or the send screen’s unsaved `free` content). `flxMailDnsCheck` stores SPF, DMARC and MX. `flxMailWcBrandApply` copies the brand onto WooCommerce emails. `flxMailImapTest` checks the saved mailbox login and does not return the password.

`flxMailLogs` filters by status, channel, template, campaign and search. `flxMailLog` includes the body only when “store the content” is on. `person` is the shared `FlxPerson` when Flux CRM is active and `flxcrm_gql_can_see_contact` allows it; otherwise null.

`flxMailSystemChecks` is the `flxi.*` rows. It is an empty list when Flux Speed owns the system screen.

Every root field requires `manage_options` or `flxi_manage`.

Dates are `FlxDateTime` from `flx_date()` / `flx_gql_datetime()`.
