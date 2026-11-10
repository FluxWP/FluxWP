# Flux Mail API

The existing public sending, campaign and mailbox API is documented in [the package API](../../plugins/flx-mail/API.md).

Campaign delivery now uses the [shared background worker contract](../background-workers/API.md). `flxi_run_queue($budget = 20)` returns its normal processed/sent/failed/left counts; when a healthy worker owns execution, a web request returns zero processed items and `deferred: true` while retaining a native cron event. Daily mailbox quotas, reserved capacity, batch sizes and retry limits still apply. Single sends and SMTP diagnostic sends remain synchronous.

No worker is required. An absent or expired heartbeat permits the existing WordPress cron/request pump. The shared adapter is bundled in Flux Mail independently of other FluxWP plugins.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)

## Bulk SMS and delivery reconciliation

Netgsm connections expose an optional `queue_batch` provider callback. The default packet has 1,000 ready recipients (connection setting `batch_size`, clamped 1–50,000). Text remains personalized per recipient. Provider `jobid` is saved in a private batch record before updating recipient rows; queue `vars.netgsm` links each row to its batch and receipt. No additional plugin or schema migration is required.

A successful send response means `submitted`, not `delivered`. `flxi_netgsm_poll` uses REST v2 `/report`, at most once per job per minute and one request per two seconds per account. Each page contains up to 1,000 recipients; pagination is persisted. Missing/unrecognized receipts remain pending, delivered rows never regress, and polling never resends SMS. Explicit rejection codes retain bounded retry/backoff; ambiguous transport acceptance moves to `unknown` / campaign `review`, requiring a Netgsm check before any manual resend. After 48 hours without a final receipt, remaining rows require review.

`flxi_create_campaign` optionally accepts `send_at` (for example `2026-11-10T18:30` in the WordPress time zone, at least a minute ahead). Other channels wait locally. Netgsm future sends are submitted with `startdate` in Istanbul time (`ddMMyyyyHHmm`); only those future sends expose provider cancellation. Cancellation is confirmed only on Netgsm code `00`; delivered recipients remain delivered. Stopping queued messages affects local queued rows and does not recall already accepted messages.

Authorized management endpoints:
- `GET /wp-json/flxi/v1/queue?campaign=ID&page=1`: read-only snapshot, 50 rows/page, recent 30 campaigns, provider batches and bounded operational history. It never sends or polls the provider.
- `POST /wp-json/flxi/v1/queue/action`: `action=stop` + `campaign`, or `action=report|cancel` + batch UUID. Uses the existing management capability and WordPress REST authentication/nonce.
- Existing `POST /queue/run`: guarded fallback pump on ordinary WordPress, including due receipt checks. Active Docker workers retain ownership; a read-only snapshot is polled every five seconds, paused in hidden tabs and backed off on errors.

Provider metadata uses non-autoload options. Terminal batch records and delivered queue rows follow Mail log retention (minimum two days for batch diagnostics); active or uncertain delivery metadata is preserved for reconciliation. Credentials and payloads are excluded from the shared operational journal. See [Netgsm official documentation](https://www.netgsm.com.tr/dokuman/) for account access, approved headers, delivery status codes and future-send cancellation limitations. The `referansID` UUID is for correlation; it is not treated as a documented idempotency guarantee.
