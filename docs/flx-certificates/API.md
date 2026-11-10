# Flux Certificates background API

Queued message delivery and server PDF/image generation use the [shared background worker contract](../background-workers/API.md). No other FluxWP plugin or Action Scheduler is required for scheduling.

- `flxs_kick_queue($when = 0)`: healthy worker selects native `flxs_run_queue` cron; otherwise retain initialized Action Scheduler or native cron.
- `flxs_run_queue($budget = null)`: web calls defer to a healthy worker with zero processed/sent/failed counts, `pending: true`, and `deferred: true`; CLI and no-worker calls process normally.
- `flxs_render_schedule($certificate, $delay = 60)`: deduplicated `flxs_render_files($id)` event; use native cron for worker delivery, otherwise initialized Action Scheduler or native cron.
- `flxs_render_certificate_files($certificate, $types = null)`: returns generated file metadata, an empty array if nothing remains, or `WP_Error`. A healthy worker causes web calls to schedule work and return the existing `flxs_render_busy` error. The renderer re-reads the certificate under its owner lease before generating missing files.
- `flxs_render_missing($limit = 25)`: returns `[rendered, failed, queued]`; the first two positions retain their existing meaning. Web bulk repairs report queued counts when handed to the worker.

Temporary render failures retry up to three attempts, with increasing delay; busy deferrals do not consume retries. Browser live preview and uploaded render files retain their existing path. When no worker is healthy, the original WordPress scheduler/render path remains available.

## Maintainers

Ahmet Yılmaz — [@sharkpaw](https://github.com/sharkpaw)
