# Background worker contract

The adapter in `tools/background/runner.php` is bundled into Flux Mail, Flux Certificates and Flux Backup. It requires only WordPress and PHP 7.4+. Consumers can run alone; the first bundle declares the shared functions and registers one `init` hook. Redis, WooCommerce, Action Scheduler and Flux Lang are not dependencies.

## Detection and fallback

- Worker context: `WP_CLI` is true and process environment `WP_CRON_MODE` equals `worker`. This matches the existing `FluxWP/docker-wordpress` worker image; no Compose changes are needed.
- `init` priority 99 renews the non-autoloaded `flx_background_worker` option containing `seen_at`, `expires_at` and the worker's `wake` capability. Writes are throttled to once per 15 seconds.
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
| `flx_background_unschedule($hook, $args)` | Serialized removal of matching cron events. |
| `flx_background_run($name, callable $callback, $options = [])` | Shared bounded-batch execution guard; return callback result or the deferred/locked result below. |

### Register another background task

Add an existing package with `python3 tools/background/sync.py --add plugins/flx-example`; require its bundled `includes/flx-background.php`. The manifest is a build-time list, not a runtime dependency. Use a package-prefixed action and a resource-specific lock name:

```php
FLX_Background_Pool::register( 'flx_example_export', 'flx-example' );
add_action( 'flx_example_export', static function ( $id ) {
    return flx_background_run( 'example-export-' . (int) $id, static function () use ( $id ) {
        // Process a bounded, checkpointed batch from the package's durable state.
        // Schedule the next batch when needed; retain domain-specific retry limits.
        return flx_example_process_batch( $id );
    }, array(
        'ttl' => 120,
        'hook' => 'flx_example_export',
        'args' => array( (int) $id ),
        'delay' => 30,
    ) );
} );
flx_background_schedule( 'flx_example_export', array( 42 ) );
```

`hook`, `args` and `delay` are used for worker handoff or lock contention. `ttl` defaults to 120 seconds and must exceed the batch's maximum execution time. Return defaults are `['processed' => 0, 'deferred' => true]` or `['processed' => 0, 'locked' => true]`; optional `on_defer`/`on_locked` callbacks supply package-compatible results. CLI cron calls handed to the parallel pool return `['processed' => 0, 'deferred' => true, 'parallel' => true]`. Inline callback exceptions propagate after releasing the owner lease; domain retries must handle uncertain external outcomes rather than blindly repeating sends. This helper does not preempt a blocked callback: consumers must bound batches and external transport timeouts and persist checkpoints. Backup retains its filesystem lock and restore-specific state, while sharing worker selection.

Do not introduce a second queue for already durable campaign/message/backup records. Shared APIs remain backwards-compatible, and additive functions use independent declaration guards. Older compatible functions may win load order; consumers must not assume that upgrading one package upgrades every other bundle's baseline functions. Parallel dispatch requires the serialized-scheduler capability marker; an older baseline loaded first safely retains serial execution until the consumers are updated. CI loads each current bundle alone and in different orders, including after a simulated legacy baseline guard. Malformed abandoned lock values can be replaced atomically; valid unexpired owners are preserved.

The `flx_background_worker_active` boolean filter may override worker preference, for example for deployment-specific diagnostics. It does not enable a scheduler. Do not expose lock tokens in public responses.

## Consumers

| Package | Durable work | Preserved fallback |
| --- | --- | --- |
| Flux Mail | Campaign rows and `flxi_run_queue` | Native cron and existing request-driven pump; quotas, batches and bounded retries remain. Single sends stay synchronous. |
| Flux Certificates | Message rows, `flxs_run_queue`, `flxs_render_files($id)` | Initialized Action Scheduler or native cron; browser previews/uploads remain. Render failures retry at most three attempts; busy deferrals do not consume retries. |
| Flux Backup | Existing checkpointed backup state and `flxy_job_tick` | Native cron, loopbacks and admin polling; restores and synchronous pre-update snapshots retain their execution path. |

Active workers drain backup steps for a roughly 40-second pass (the final bounded step may finish beyond that window), then leave a cron tick for continuation. Owner leases and atomic claims prevent simultaneous queue execution; they cannot promise exactly-once delivery after an external provider accepts a message and a process dies before recording success.

## Parallel worker execution and capacity

The Docker stack keeps **one cron coordinator per site**. Its existing `wp cron event run --due-now` invocation needs no changes: registered Flux callbacks hand work to the shared pool. After the coordinator finishes consuming native cron events, short-lived WP-CLI child processes execute independent jobs concurrently. The default is **3 active background batches per plugin**, controlled by `WP_BACKGROUND_WORKERS_PER_PLUGIN` (clamp 1–16) and `flx_background_plugin_concurrency($limit, $plugin)`. `WP_BACKGROUND_MAX_PROCESSES` defaults to **9 total** (clamp 1–64), with the `flx_background_concurrency` filter for integrations. Per-plugin and site-wide owner-bound slot leases enforce both limits across overlapping coordinators. Setting the global limit to 1, missing subprocess support or ordinary WordPress execution retains the inline path. Set deployment values in the Coolify environment panel or Compose `.env`; child processes inherit them. No wp-config constants or extra plugins are required.

Each hook must register its owning plugin; the parent schedules fairly across plugin queues instead of applying one three-job cap to the whole site. Register a task with `FLX_Background_Pool::register('flx_example_export', 'flx-example')` before using `flx_background_run()`. Other cron hooks remain under their existing scheduler. Each consumer carries both library files; separate daemons, Redis and WooCommerce are not required. Normal shared hosting does not spawn this CLI pool.

| Work | Parallel boundary |
| --- | --- |
| Campaign mail | One batch per site; can run alongside PDF/backup work. |
| Certificate delivery | One message batch per site; independent of campaign mail. |
| PDF/image generation | One lease per certificate; different certificates may run concurrently. |
| Backup/restore | Existing one-active-job rule and filesystem lock; backup can overlap mail/PDF. Restore keeps its existing path. |

Dispatch tickets are private non-autoloaded WordPress options, not another copy of domain queues. A separate native watchdog event is armed before handoff and checks after 210 seconds. Successful jobs remove their ticket/watchdog. Exceptions and killed subprocesses retain the ticket; retries use the same ticket and stop after three started attempts, emitting `flx_background_failed($hook, $ticket_id)`. A child has a 120-second wall-time limit controlled downward by `flx_background_timeout`; dispatch and process-slot leases expire after 180 seconds. Package item retries, quotas and uncertain external-delivery outcomes retain their own rules. Mail and certificate queues retain a recovery event for interrupted processing claims, wait ten minutes before taking them back, and mark exhausted claims failed instead of leaving them stuck or retrying indefinitely. Raw task arguments and child output are not printed to logs or passed in command-line arguments.

Shared scheduling/unscheduling serializes short cron-option updates and invalidates autoloaded cron caches so concurrent children retain each other's next batches. The coordinator starts children only after its native cron scheduling pass to avoid racing that pass. This protects registered Flux tasks; it does not make arbitrary third-party cron handlers safe to parallelize. More containers are not a substitute for this bounded pool, and the current deployment remains one coordinator.

### Fast wake without an idle PHP loop

Compatible workers advertise `FLX_BACKGROUND_WAKE=1`; the heartbeat stores that capability. Scheduling a native event creates an empty `WP_CONTENT_DIR/.flx-background-wake/<due_timestamp>` signal only for a healthy compatible worker. Older images do not accumulate notifications and retain periodic cron. The updated docker-wordpress worker checks due signals every `WP_BACKGROUND_POLL_INTERVAL` seconds (default 2), removes them **before** starting cron so arrivals during a pass survive, and keeps `WP_CRON_INTERVAL` (default 60) as fallback. Idle checks are shell/filesystem operations, not a WordPress boot. Shared volume and compatible web/worker filesystem users are required.

A ready SMS/message submitted just after a pass can therefore start after the short poll/bootstrap delay when capacity is free; this is not a delivery-time guarantee. Scheduled future work, full plugin/global slots, transport quotas and backoff still apply. Backup ticks also use an immediate worker handoff, while no-worker scheduling retains its previous interval and loopback behavior. Fast wake needs the updated worker image; parallel execution already works with the previous image's native cron command.

Hostinger PHP workers are concurrent PHP request slots shared by visitors, admin requests and HTTP-driven background work. They are not a count of Docker queue workers. Capacity above the default requires RAM/CPU and queue-duration measurements; no server-specific safe maximum is claimed by the library.

Source edits must be synchronized with `python3 tools/background/sync.py`; verify with `--check`. Integration fixtures and setup notes are in [tools/background](../../tools/background/README.md).

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
