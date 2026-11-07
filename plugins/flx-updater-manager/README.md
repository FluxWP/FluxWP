# Flux Updater Manager

Manage your FluxWP update server ([Flux Update Server](https://github.com/FluxWP/updater) 1.12.0+) from a WordPress admin, with the same data as the server's /admin panel.

- **Overview:** active and keyed sites, update checks, downloads, data sent, daily chart, packages, country map and connected sites (7/30/90 days). Issue a key to a site that connected without one.
- **Countries:** traffic per country with blocked requests and per-country packages.
- **Sites:** add a site key (shown once), edit access (all private packages or selected repositories/packages), channel, disable, delete, test a key.
- **Packages:** catalog with versions, access and release date; reload from GitHub.
- **GitHub:** private/public repositories, public packages, app and desktop repositories, token (write-only), instant-publish webhook secret.
- **Security:** emergency stop, keyless distribution, country access rule, country database (download, delete, IP lookup).
- **Resources:** disk, data breakdown, GitHub API quota, PHP; zip retention and cleanup, access log.
- **Connection:** server address and manager key (stored encrypted), other manager keys, server panel password.

## Connect

1. On the server: /admin › **Updater Manager** › name it › *Generate manager key*. Copy the `flxm_…` key (shown once).
2. Here: **Updater › Connection**, server address (defaults to the FluxWP Panel server on this site, else `https://updater.fluxwp.com`) + key › *Save and connect*.

`FLXUM_SERVER` and `FLXUM_KEY` in wp-config.php override the fields. Creating manager keys and uploading a country database file stay on the server panel. Deleting the key on the server cuts access at once; every change is logged on the server as `manager:<key name>`.

Requires WordPress 6.4+, PHP 7.4+. Only users with `manage_options`.

Türkçe: [README.tr.md](README.tr.md)

## AI tools

With **Flux AI & MCP** active, the plugin adds `flxum_*` tools to the shared chat and the MCP server (Flux AI SDK). Read: `overview`, `connected_sites`, `keyed_sites`, `packages`, `security`, `resources`, `test_key`. Change (approval every time): `add_site`, `update_site`, `toggle_site`, `delete_site`, `issue_key`, `set_switch`, `set_country_access`, `set_public_packages`, `refresh_packages`, `set_zip_retention`, `update_country_db`. New site keys are never sent to the model; they are shown once on Updater › Sites. Manager keys, the panel password, the GitHub token and the webhook secret are not exposed.
