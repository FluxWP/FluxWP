<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Performance logo" width="120">

# Flux Performance

**Find out why your WordPress site is slow, even on shared hosting.**<br>
A developer panel for every request (queries, PHP errors, HTTP calls, hooks), a log of slow and failing requests, live server health, database, autoload and cron views, and the shared System Check for all FluxWP packages. AI assistants can read all of it over MCP.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Query Monitor style](https://img.shields.io/badge/dev%20panel-queries%20%C2%B7%20errors%20%C2%B7%20HTTP-2fbf8f)
![MCP](https://img.shields.io/badge/AI-MCP%20tools-7d64ff)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Installation](#installation) · [Use it with AI assistants (MCP)](#use-it-with-ai-assistants-mcp) · [Roadmap](#roadmap)

</div>

---

## Why Flux Performance?

Query Monitor tells you what happened on the page you are looking at; it forgets everything else. On shared hosting the slow request is usually the one nobody was watching. Flux Performance keeps the useful part of both worlds:

- 📝 **It records.** Slow and failing requests are saved with their queries, errors and HTTP calls, so you can look at them later.
- 🔍 **A full developer panel** in the admin bar for logged-in staff: `266 ms · 49q · ⚠ 1`, one click away.
- 🩺 **Server health at a glance:** CPU load, RAM, disk, PHP memory and uptime, refreshed live, with suggestions.
- 🧹 **Safe clean-ups:** expired transients, old auto-drafts, spam and trash comments, table optimisation.
- ✅ **One System Check** that every FluxWP package reports into.
- 🤖 **AI ready:** "why was the site slow yesterday?" works in Flux AI and any MCP client.

## Features

### Developer panel
- Admin-bar summary (time, queries, warnings) and a resizable bottom panel: queries (duplicates, by component, slow), PHP errors, HTTP API calls, logs, template, request, hooks, timeline and capability checks.
- **Profile page:** open any address of this site like a visitor (no cache) and record it in full.

### Records and overview
- Request log with filters (time, flag, type, address) and "slowest first"; click a row to see its full panel.
- Overview: p95, average and longest time, slowest addresses, query time by plugin, grouped slow queries, PHP errors and failing outgoing requests for the last hour, day or week.
- Suggestions such as "autoload is large", "cron is not running", "disk is almost full", "OPcache is off".

### Server and site
- **Server:** OS, web server, PHP limits and disabled functions, extensions, OPcache, caches, WordPress constants and active plugins. Read from `/proc` and PHP, no `shell_exec` and no external services.
- **Database:** version, key settings, status, table sizes and a clean-up section (admin only, with confirmation).
- **Autoload:** total size and the 40 largest options with their owner. **Cron:** every scheduled event, overdue ones and when cron last ran.
- **System Check:** checks and one-click fixes from every FluxWP package (`flx_system_checks`, `flx_system_fixes`).
- Dashboard widget with live boxes, p95, failing and slow requests and the top suggestions.

### Light on shared hosting
- Visitors only trigger cheap hooks (errors, HTTP calls, timing). Query capture runs only on sampled requests, for logged-in staff and when profiling a page.
- One INSERT at the end of a request; old rows are pruned on the fly. Emails, phone numbers, IBANs, tokens and secrets are masked before anything is stored.

## Installation

Requirements: WordPress 6.4+, PHP 7.4+.

**From a zip**

1. Download `flx-speed.zip` from the latest `flx-speed-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With Flux Updater** (recommended on our sites)

**Dashboard › Updates** lists every FluxWP package; click **Install** next to *Flux Performance*. Updates arrive the same way.

**First steps**

1. Activate it; the `wp_flxp_requests` table is created automatically.
2. **Performance › Settings** comes with values that suit Hostinger (2 % sampling, 1500 ms slow request, 100 ms slow query, 7 days).
3. Give staff the **flxp_view** capability in Flux Roles if they should see it. Administrators have it already.

> ℹ️ On shared hosting CPU load and RAM belong to the whole machine, not just your site. To switch the plugin off completely, add `define( 'FLXP_DISABLED', true );` to `wp-config.php`.

Full setup notes (Turkish): [KURULUM.md](../../docs/flx-speed/KURULUM.md) · API reference: [API.md](../../docs/flx-speed/API.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## Use it with AI assistants (MCP)

Flux Performance adds read-only tools to the site's MCP server (**Flux SEO & MCP**) and to the **Flux AI** chat:

| Tool | What it answers |
|---|---|
| `flxp_summary`, `flxp_requests`, `flxp_request` | Overview, recorded requests and one request in full |
| `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls` | Grouped slow queries, PHP errors, outgoing HTTP calls |
| `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron` | Server, PHP, database, autoload size and scheduled events |
| `flxp_profile_url` | Measure one page of this site with full detail |

With Flux SEO & MCP, the `system_fix` tool can also run `flxp_expired_transients` (delete expired transients).

Example prompts:
- *"Why was the site slow in the last 24 hours?"*
- *"Measure /bagis/ and tell me which plugin runs the slowest query."*
- *"Which plugin bloats autoload the most? Is cron running?"*

## Roadmap

| | |
|---|---|
| ✅ | Developer panel and request recording |
| ✅ | Server, database, autoload and cron screens with suggestions |
| ✅ | Shared System Check for all FluxWP packages |
| ✅ | MCP and Flux AI tools |
| ⬜ | Block editor requests in the panel |
| ⬜ | Multisite network panel |
| ⬜ | Email alert when error or slow-request rates jump |

## License

Flux Performance Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
