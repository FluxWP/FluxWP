# Flux Mail GraphQL (parts 1 and 2)

Settings, Templates, the send log, the system check and (part 2) Connections with the WhatsApp Cloud setup read and write through GraphQL. The classic admin screens stay one link away (`flxi_ui=classic`). Send, the queue, the blocklist and the mailbox are later parts.

Registration runs on `flx_gql_register` and is skipped when Flux GraphQL is not loaded. REST keeps working either way.

## Settings

`flxMailSettings` returns the form. Passwords are `FlxMailSecret` (`set`, `mask`, `fromConfig`) and the value is never selected. `flxMailSettingsUpdate` takes the same fields. An empty secret is left as stored; `"__clear__"` removes it. `FLXI_SMTP_PASS`, `FLXI_SMTP_NOREPLY_PASS` and `FLXI_IMAP_PASS` come back as `fromConfig: true`; a write to one of them is refused with `INVALID_INPUT`. `mask` is always the fixed `••••••••` when a value is set (empty otherwise), never part of the password. An invalid email in `flxMailSettingsUpdate` is a field error; the classic form and the MCP tool keep dropping an invalid address to the default.

The kit’s generated `flxiSettings` field is only `tagline` and `footerArt` (the `flxi_settings` option slice). The screen does not use it. `FlxMailSettings` is the screen: the whole form, the write-only secrets and the read-only status (last test, WooCommerce brand time, another SMTP plugin, account notes, active social-login plugins, quota, DNS, opt-out counts).

`flxMailSettingsUpdate` and the classic settings form both call `FLXI_Settings_Service::save()`, which calls `flxi_sanitize_setting()` and `flxi_update_settings()`. A non-empty address that is not an email is rejected there for every writer. An empty address keeps the stored default, the same as a blank classic field. A settings test (`flxMailTestSend` with no template) writes `flxi_last_test` once.

Logos go through the shared `POST /flx/v1/upload` with context `flx-mail/logo`, then `logoUploadId` / `logoWhiteUploadId` on the settings input.

## Other operations

Templates are a connection (`first` at most 50): `flxMailTemplates`, `flxMailTemplate`, `flxMailTemplateUpdate`, `flxMailTemplateReset`. WooCommerce rows only toggle and link to WooCommerce’s own editor.

`flxMailPreview` renders saved or unsaved content. `flxMailPreviewPdf` exports that render. `flxMailTestSend` sends a test (settings, a template, or the send screen’s unsaved `free` content). `flxMailDnsCheck` stores SPF, DMARC and MX. `flxMailWcBrandApply` copies the brand onto WooCommerce emails. `flxMailImapTest` checks the saved mailbox login and does not return the password.

`flxMailLogs` filters by status, channel, template, campaign and search. `flxMailLog` includes the body only when “store the content” is on. `person` is the shared `FlxPerson` when Flux CRM is active and `flxcrm_gql_can_see_contact` allows it; otherwise null.

`flxMailSystemChecks` is the `flxi.*` rows. It is an empty list when Flux Speed owns the system screen.

Every root field requires `manage_options` or `flxi_manage`. `flxi_manage` is registered on `flx_gql_capabilities` and is present on `flxViewer.capabilities` for a user who has it.

Dates are `FlxDateTime` through `flx_gql_datetime()`. The send log stores `created_at` as GMT (`flxi_now()`, `current_time( 'mysql', true )`). The field is that GMT instant; `label` follows the site timezone. Last test, DNS `checkedAt`, WooCommerce brand time and quota `next` are Unix timestamps. Template and settings writes go through `flxi_save_template()` (`flxi_template_saved` once) and `flxi_update_settings()`, which the classic screens read back with `flxi_template()` and `flxi_setting()`.

## Connections and WhatsApp Cloud (part 2)

The classic Connections page (`admin-post`), the REST routes (`/flxi/v1/wa/{id}/*`, `/wacloud/{id}/setup`, `/wacloud/{id}/templates`, `/connection-test`) and the GraphQL fields all call `FLXI_Connection_Service`. REST output is unchanged. The service reads and writes the same tables and options as before (`flxi_connections`, `flxi_conn_tests`, `flxi_wa_state`, the per-connection `flxi_wac_*` options), so a connection saved by the classic form shows up in GraphQL and the other way round.

Reads, all `manage_options` / `flxi_manage`:

| Field | Returns |
|---|---|
| `flxMailConnectionMeta` | Types with their form fields (the same definitions the classic form renders), test scenarios, sample file URLs, Flux Certificates flag |
| `flxMailConnections(channel, activeOnly, first, after)` | `FlxMailConnectionConnection`, at most 50 per page |
| `flxMailConnection(id)` | One connection or null |
| `flxMailWaState(id, fresh)` | Cached state (60 s); `fresh` asks the server |
| `flxMailWaInstances(id)`, `flxMailWaGroups(id, fresh)` | Evolution / Flux WPAPI server data |
| `flxMailWaServer(id)` | Flux WPAPI server info; no key and no password |
| `flxMailWacloudSetup(id)` | Webhook URL, verify value, signed / WABA flags, phone details from Meta, last verify / event / bad signature / missing secret times, open 24 h windows. Null for a connection that is not Cloud API |
| `flxMailWacloudTemplates(id, first, after)` | Cloud API templates as a connection |

Writes: `flxMailConnectionSave`, `flxMailConnectionDelete(remote)`, `flxMailConnectionSetDefault`, `flxMailConnectionTest`, `flxMailWaConnect`, `flxMailWaRestart`, `flxMailWaLogout`, `flxMailWaServerUpdate`.

Secrets: `apiKey`, `token`, `appSecret` and `password` are `FlxMailSecret` on read (`set`, `mask`, `fromConfig`) and `String` on write. An empty value keeps the stored one and `"__clear__"` removes it; the built-in types mark every secret required, so clearing one is refused with a field error. The Flux WPAPI database password (`flxMailWaServerUpdate.db.password`) is sent to the bridge only and is not kept in WordPress. The verify token Meta needs is a handshake string, not a credential; it is returned only by `flxMailWacloudSetup` (manager only), never by the connection list. The App Secret is never returned.

The Cloud webhook `GET` + `POST /wp-json/flxi/v1/wacloud/{id}/webhook` stays REST because Meta posts to that fixed address. `flxMailWacloudSetup.webhookUrl` shows it and the React screen offers a copy button.

Validation errors come back in the mutation payload with the input field name (`username`, `password`, `copyFrom` …). An unknown id is `NOT_FOUND`. `copyFrom` copies the server address and API key of an existing Evolution / Flux WPAPI connection of the same type on create without the key reaching the browser. `type` is a plain string, not an enum, because other plugins can add connection types through `flxi_connection_types`; `flxMailConnectionMeta.types` lists what is offered.

`flxMailConnectionTest` runs the same scenarios as the classic dialog (`check`, `text`, `image`, `audio`, `document`, `template`) and stores the result once in `flxi_conn_tests`; the returned connection carries the new `lastTest`. Dates are `FlxDateTime`: `lastTest.at`, `waState.at`, the webhook times and `lastKeepAlive` are Unix times.
