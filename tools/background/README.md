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

## Integration verification

`tests/run.py` exercises the three plugins in an **owned, disposable localhost WordPress fixture**, with delivery intercepted, using the published worker image. It temporarily changes plugin activation, cron and sample queue state; do not point it at a real site. The fixture expects the repository bind mounts and `WPENV` capture environment used by `tools/readme-media`; take a database snapshot first. It restores its own fixtures in `finally`.

```sh
python3 tools/background/tests/run.py --container fluxwp-media-20261109-wordpress-1
```

The report covers real PDF/ZIP files, ordinary WordPress with and without Action Scheduler, stale-worker fallback, quotas, retries and competing worker leases. Transport calls are intercepted; it does not verify external SMTP delivery. Arguments accept another fixture container or worker image; see `--help`.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
