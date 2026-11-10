# Portable background workers

[Türkçe](README.tr.md)

Flux Mail, Flux Certificates and Flux Backup bundle this small adapter independently. No additional plugin, Redis or queue server is required. Their existing database queues and WordPress cron events remain the source of work.

The existing FluxWP Docker worker runs WP-CLI with `WP_CRON_MODE=worker` and shares the web container's database and files. Loading any consumer plugin renews a heartbeat. While it is healthy, web requests schedule queued campaigns, certificate messages/PDF files and asynchronous backups for that worker. Single-email APIs, SMTP tests, browser certificate previews, restores and synchronous pre-update snapshots retain their existing behavior.

Without a worker, native WordPress execution continues. Certificates use initialized Action Scheduler when available, otherwise WP-Cron. A stopped worker's lease expires after five minutes with the default 60-second interval; ordinary execution is then allowed again. The adapter does **not** change `DISABLE_WP_CRON`: a site with cron disabled still needs an external scheduler or an existing request/loopback trigger. It does not restart stopped containers.

## Development

Edit `runner.php`, then run from the repository root:

```sh
python3 tools/background/sync.py
python3 tools/background/sync.py --check
```

The bundles must remain byte-identical. Shared functions are conditionally declared so all three plugins can coexist or run independently. See [API and scheduling contract](../../docs/background-workers/API.md).

`flx_background_run()` is the reusable execution guard used by the mail and certificate queues: it defers web work to a healthy worker, claims an owner lease, requeues lock contention, executes the batch and releases the lease even when the callback throws. The package supplies its actual work, bounded batch budget and domain retry policy. Backup retains its existing filesystem lock because restores have different persistence requirements; worker selection still uses this library.

To adopt it in another package, run `python3 tools/background/sync.py --add plugins/flx-example` for an existing package and require its bundled `includes/flx-background.php`. The consumer manifest makes missing bundles fail CI. See the complete hook example in the API document. No separate plugin needs to be activated. Keep shared APIs backwards-compatible: additive helpers need their own function guards so an older bundle loaded first cannot hide them. CI checks synchronization, PHP syntax, standalone bundles and load order.

## Integration verification

`tests/run.py` exercises the three plugins in an **owned, disposable localhost WordPress fixture**, with delivery intercepted, using the published worker image. It temporarily changes plugin activation, cron and sample queue state; do not point it at a real site. The fixture expects the repository bind mounts and `WPENV` capture environment used by `tools/readme-media`; take a database snapshot first. It restores its own fixtures in `finally`.

```sh
python3 tools/background/tests/run.py --container fluxwp-media-20261109-wordpress-1
```

The report covers real PDF/ZIP files, ordinary WordPress with and without Action Scheduler, stale-worker fallback, quotas, retries and competing worker leases. Transport calls are intercepted; it does not verify external SMTP delivery. Arguments accept another fixture container or worker image; see `--help`.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)

## Parallel jobs

The existing Docker cron coordinator now runs registered independent jobs in a bounded pool: 3 active batches per plugin by default, with a configurable site ceiling of 9. Mail, certificate PDF files and backup work can overlap; same-resource leases still prevent duplicate work. Shared scheduling protects concurrent next-batch updates. Private dispatch tickets and native watchdogs retain failed work, with a 120-second child wall limit and bounded attempts. No Docker Compose changes are needed. Ordinary WordPress keeps its inline/native scheduler path. See the API contract for concurrency and timeout filters and the limits of external-delivery guarantees.

Deployment controls: `WP_BACKGROUND_WORKERS_PER_PLUGIN=3`, `WP_BACKGROUND_MAX_PROCESSES=9`; the updated Docker worker uses `WP_BACKGROUND_POLL_INTERVAL=2` for low-cost due-time wake checks and retains `WP_CRON_INTERVAL=60` as fallback. Update/recreate the worker image for fast wake; older images keep native cron. Signals contain no job payload. The integration test also needs the sibling docker-wordpress checkout (or `--worker-script`) and serializes access to its disposable fixture.

The integration suite also exercises 1,000 synthetic SMS recipients against an intercepted Netgsm REST v2 response, permanent/transient provider errors and WhatsApp spacing/rate limits. No real SMS, WhatsApp or email messages are sent.

## Operational history

The shared journal correlates dispatch/start/finish, duration, timeouts, retries and safe error metadata. Backup phase transitions use the backup ID. Communication › Queue displays shared history and campaign-specific events. Operational entries exclude message content, recipients and credentials; existing detailed delivery logs remain separate.

Default retention is the latest 2,000 events / 14 days, pruned at most once per minute and stored without autoload. `flx_background_log_limit` and `flx_background_log_days` adjust these limits. This is bounded diagnostic history rather than a permanent audit archive. Logging errors never interrupt delivery. See the API contract for the whitelist and optional sink hook.
