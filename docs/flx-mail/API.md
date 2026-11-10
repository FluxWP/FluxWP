# Flux Mail API

The existing public sending, campaign and mailbox API is documented in [the package API](../../plugins/flx-mail/API.md).

Campaign delivery now uses the [shared background worker contract](../background-workers/API.md). `flxi_run_queue($budget = 20)` returns its normal processed/sent/failed/left counts; when a healthy worker owns execution, a web request returns zero processed items and `deferred: true` while retaining a native cron event. Daily mailbox quotas, reserved capacity, batch sizes and retry limits still apply. Single sends and SMTP diagnostic sends remain synchronous.

No worker is required. An absent or expired heartbeat permits the existing WordPress cron/request pump. The shared adapter is bundled in Flux Mail independently of other FluxWP plugins.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
