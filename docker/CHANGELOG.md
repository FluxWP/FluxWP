# Changelog

## 2026.11.19.1

- Coolify Git deploys of a non-default stack: README now says to use `/coolify/<stack>/docker-compose.yml` as the Compose Location. `compose/<stack>/` (plain Docker) has no `SERVICE_URL_*`, so Coolify found no domain there. The plain files now carry a warning line saying so.

## 2026.11.18.1

- First open of a brand-new site: the FluxWP Docker mu-plugin (1.2.0) sends visitors to the WordPress login, where Flux Setup's wizard opens, until the wizard is done or skipped, a preset is applied or the site has content. Before the worker has installed WordPress, the closed install screen now shows a short "being set up" page (auto-refresh, points to the worker log) instead of a bare 403, in the Apache and nginx images.

## 2026.11.17.1

- New `wpapi` stack (`compose/wpapi`, `coolify/wpapi`): `mariadb-redis` plus the FluxWP WhatsApp API (flx-wpapi) as an internal `wpapi` service, image `ghcr.io/fluxwp/wordpress:wpapi-node22`. One shared `WPAPI_KEY` is used by both sides; the FluxWP Docker mu-plugin adds the WhatsApp connection in Flux Mail, so nothing is linked by hand. The default stack and the other stacks are unchanged.
- OPcache default raised from 192 MB to 256 MB (`PHP_OPCACHE_MEMORY`). A FluxWP site with WooCommerce and the Flux packages already fills ~155 MB after one pass over its screens; updates leave wasted memory on top, and a full OPcache stops caching new files.

## 2026.11.14.1

- `docker/docker-compose.local.yml` and `docker/.env.example`: the default stack (MariaDB + Redis) for plain Docker next to the Coolify `docker-compose.yml`. Run `cp .env.example .env && docker compose -f docker-compose.local.yml up -d` in `docker/`.

## 2026.11.13.1

- Moved from `FluxWP/docker-wordpress` into the FluxWP monorepo under `docker/`. Image names and tags (`ghcr.io/fluxwp/wordpress:*`) are unchanged; Coolify Git deploys use Base Directory `/docker` and Watch Paths `docker/**`.
- Image builds and checks run from `.github/workflows/docker-images.yml` and `docker-validate.yml`, path-filtered to `docker/` so they never start a plugin or theme release.

## 2026.11.10.2

- Close `/wp-admin/install.php` and `/wp-admin/setup-config.php` unless `WP_INSTALL_TOKEN` is presented. `WP_AUTO_INSTALL` now defaults to true.
- Download Flux Setup over HTTPS only, reject HTTP errors and redirects to a non-HTTPS URL, cap the archive size, and install it only when the zip contains `flx-setup/flx-setup.php` and no escaping paths. Optional `FLUXWP_SETUP_SHA256` pin.
- Store a generated admin password in a file only the worker can read (`/run/flx-private/admin-password`, mode 0600) instead of container logs.

## 2026.11.10.1

- Wake compatible Flux background jobs through due-time shared-volume signals, with lightweight configurable polling and the existing cron fallback.
- Expose per-plugin concurrency (default 3), total concurrency (default 9) and poll interval (default 2 seconds) in all generated deployment variants.
- Advertise wake capability in the worker image; older plugin/image combinations keep native cron behavior.
- Add wake behavior checks to validation.
- Wait for Redis readiness independently of Flux Setup activation in smoke validation.
