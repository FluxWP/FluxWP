# Background worker contract

The adapter in `tools/background/runner.php` is bundled into Flux Mail, Flux Certificates and Flux Backup. It requires only WordPress and PHP 7.4+. Consumers can run alone; the first bundle declares the shared functions and registers one `init` hook. Redis, WooCommerce, Action Scheduler and Flux Lang are not dependencies.

## Detection and fallback

- Worker context: `WP_CLI` is true and process environment `WP_CRON_MODE` equals `worker`. This matches the existing `FluxWP/docker-wordpress` worker image; no Compose changes are needed.
- `init` priority 99 renews the non-autoloaded `flx_background_worker` option containing `seen_at` and `expires_at`. Writes are throttled to once per 15 seconds.
- Lease duration: `max(300, 2 * interval + 120)` seconds; `WP_CRON_INTERVAL` defaults to 60 and is clamped to 5–3600 seconds.
- Web execution defers only while the lease is healthy. CLI execution remains allowed. Absent or expired leases allow existing native execution.
- The adapter never changes `DISABLE_WP_CRON`. If cron is disabled, maintain an external scheduler; fallback permission alone does not trigger jobs or restart containers.

The worker must share the web installation's database **and files**, run under a compatible filesystem user, load plugins, and execute due WordPress cron events. Certificate rendering needs its existing GD/FreeType support; backup needs its existing ZIP support. The published `ghcr.io/fluxwp/wordpress:worker-php8.4` image was verified with both.

## Shared functions

| Function | Contract |
| --- | --- |
| `flx_background_worker_context()` | Whether this process is the recognized CLI worker. |
| `flx_background_worker_heartbeat()` | Renew the lease only in worker context. |
| `flx_background_worker_active()` | Healthy lease or current worker context, then the filter below. |
| `flx_background_should_defer()` | Renew heartbeat as needed; true for non-CLI requests with an active worker. |
| `flx_background_schedule($hook, $args = [], $delay = 1)` | Native single cron event, at least one second later; retain an earlier equivalent event and replace a later one. Returns the cron scheduling result. |
| `flx_background_lock($name, $ttl)` | Owner token or false; non-autoloaded option lease with atomic stale-owner takeover. |
| `flx_background_unlock($name, $token)` | Release only the matching owner; an expired owner cannot delete its successor's lease. |

The `flx_background_worker_active` boolean filter may override worker preference, for example for deployment-specific diagnostics. It does not enable a scheduler. Do not expose lock tokens in public responses.

## Consumers

| Package | Durable work | Preserved fallback |
| --- | --- | --- |
| Flux Mail | Campaign rows and `flxi_run_queue` | Native cron and existing request-driven pump; quotas, batches and bounded retries remain. Single sends stay synchronous. |
| Flux Certificates | Message rows, `flxs_run_queue`, `flxs_render_files($id)` | Initialized Action Scheduler or native cron; browser previews/uploads remain. Render failures retry at most three attempts; busy deferrals do not consume retries. |
| Flux Backup | Existing checkpointed backup state and `flxy_job_tick` | Native cron, loopbacks and admin polling; restores and synchronous pre-update snapshots retain their execution path. |

Active workers drain backup steps for a roughly 40-second pass (the final bounded step may finish beyond that window), then leave a cron tick for continuation. Owner leases and atomic claims prevent simultaneous queue execution; they cannot promise exactly-once delivery after an external provider accepts a message and a process dies before recording success.

Source edits must be synchronized with `python3 tools/background/sync.py`; verify with `--check`. Integration fixtures and setup notes are in [tools/background](../../tools/background/README.md).

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
