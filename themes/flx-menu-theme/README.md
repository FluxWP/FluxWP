<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Menu Theme logo" width="120">

# Flux Menu Theme

**The quietest possible block theme for a QR menu domain: a blank white home page and nothing WordPress doesn't need.**<br>
Flux Menu Theme is the theme of menu.example.org, the domain behind our event QR menus. The home page is completely blank and white, other pages are plain centred text, and the menus themselves are served by Flux QR Menu at addresses like `/menu/` or `menu1.menu.example.org`. Theme Settings switch off what a menu site never uses (posts, comments, feeds, search, XML-RPC…), and Site Setup prepares the site in one click.

[![WordPress 6.6+](https://img.shields.io/badge/WordPress-6.6%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Minimal](https://img.shields.io/badge/home%20page-blank%20white-lightgrey)
![Hardening](https://img.shields.io/badge/switches-9%20features%20off-2fbf8f)
![QR](https://img.shields.io/badge/works%20with-FluxWP%20QR%20Menu-0a7cff)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Installation](#installation) · [For developers](#for-developers) · [Roadmap](#roadmap)

</div>

---

## Why Flux Menu Theme?

A QR menu domain should load the menu and nothing else. A normal theme brings a header, blog archives, comment forms and feeds that only invite spam and confuse search engines:

- ⬜ **Nothing to see at the root.** The home page is white in light and dark mode; visitors arrive through a QR code straight to the menu.
- 🔒 **Less surface.** Posts, comments, feeds, search, attachment pages, XML-RPC, emoji, oEmbed and the public users endpoint can be switched off one by one, and back on.
- 🗺️ **Clean sitemap.** Only pages and the QR menu addresses on this domain are listed.
- 🧩 **Out of the menu's way.** Flux QR Menu renders its pages itself; the theme never touches them.

## Features

### Theme
- Blank white home page (no header or footer), plain centred page, post, list and 404 templates with the system font.
- Turkish source with an English translation.

### Hidden mode
- On by default: robots.txt `Disallow: /`, `noindex` on every response (QR menus included), all sitemaps 404, REST API only for allowed namespaces (default `flx-qr-menu/v1`), `?author=` 404, no version tags, directory listing off via `.htaccess`.
- "Only menus open": logged-out visitors get 404 everywhere except the home page, QR menus, the login page and allowed paths. With WooCommerce, its pages, Store API, wc-ajax and wc-api are closed too; payment callbacks are opened via "Paths that stay open".
- Logged-in users see the site normally.

#### What is closed (logged-out visitor, hidden mode on)

| What | Result |
|---|---|
| Sitemaps (`wp-sitemap*.xml`, `sitemap.xml`, `*-sitemap.xml`, `?sitemap=`) | 404 (even when logged in) |
| REST API: `/wp-json/`, `?rest_route=`, `/index.php/wp-json/`, namespace indexes, `wp/v2`, `oembed`, `wc/store`, `wc/v1-3`, `wc-analytics`, `batch/v1`… | 404; only routes in **Open REST namespaces** (default `flx-qr-menu/v1`) work, JSONP (`?_jsonp=`) is off |
| `admin-ajax.php` (heartbeat, WooCommerce and every plugin's `nopriv` action), `admin-post.php` | 404 (open one by one with `admin-ajax:<action>`) |
| `xmlrpc.php`, `wp-trackback.php`, `wp-links-opml.php`, `wp-comments-post.php`, `wp-mail.php`, `wp-signup.php`, `wp-activate.php`, `wp-admin/maint/repair.php` | 404 |
| Feeds, `/embed/`, `/trackback/`, search, author (`?author=`) and date archives, attachment pages, previews, every not-found address | Plain 404 without the theme template |
| "Only menus open": other pages, `?p=`, `?page_id=`, `?name=`, `?cat=`… | 404; the home page shows nothing else with query parameters (`?utm_*` and the like are fine) |
| WooCommerce: shop, product, category, cart, checkout, account (lost password too), `?wc-ajax=`, `/wc-api/`, `?wc-api=`, `/wc-auth/`, `?add-to-cart=`, `?download_file=`, form handlers | 404; no WooCommerce scripts, styles or fonts on the home page |
| wp-login | Open (for the owner). Registration off; one message instead of "username not registered" / "wrong password"; lost password always says "check your email" |
| Version info | No `generator` tag, RSD/WLW, shortlink, REST/oEmbed/feed links, emoji script, `X-Pingback`, `X-Powered-By`, `X-Redirect-By` or REST `Link` header; `?ver=` values become a site-specific hash (wp-login too) |
| `.htaccess` (Apache/LiteSpeed) | Directory listing off; `readme.*`, `changelog.*`, `license.*`, `*.md`, `*.log`, `wp-config-sample.php`, `composer.json` unreachable (plugin versions too); `wp-admin/install.php`, `setup-config.php` always, `upgrade.php` and `maint/` without a login cookie get 403 |

Still open: the home page, QR menus (path, subdomain, `/tesekkur/`), `flx-qr-menu/v1` (live refresh, keyed feed), wp-login, `robots.txt`, `wp-cron.php`, the two allow-lists. WP-CLI and requests with an application password / Flux MCP key count as logged in. On nginx the `.htaccess` lines do not apply; turn off directory listing and these files in the server configuration.

### Theme Settings
- Nine switches, all on by default: posts, comments, RSS feeds, search, attachment pages, XML-RPC, emoji script, oEmbed, anonymous `/wp/v2/users`.
- Switched-off routes return 404 or 403, menus and admin items disappear; Flux QR Menu pages, REST endpoints and the menu manager panel are not affected.

### Site Setup
- Selectable steps: permalinks, İstanbul time zone, `d.m.Y` dates, Turkish language if installed, clean-up of untouched default content, comments off, blank home page check.
- Ready-made updates with dates; a notice when one is pending.
- System checks: features off, published posts while posts are off, permalinks, pending updates, Flux QR Menu inactive.

## Installation

Requirements: WordPress 6.6+, PHP 7.4+. Flux QR Menu for the menus.

**From a zip**

1. Download `flx-menu-theme.zip` from the latest `flx-menu-theme-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Appearance › Themes › Add New › Upload Theme**, choose the zip, then **Activate**.

**With Flux Updater** (recommended on our sites)

**Dashboard › Updates** lists every FluxWP package; click **Install** next to *Flux Menu Theme*. Updates arrive the same way.

**First steps**

1. Activate Flux Menu Theme and run **Appearance › Site Setup** with the selected steps.
2. Keep the switches in **Appearance › Theme Settings** on unless the site needs posts or comments.
3. Create menus in **QR Menus** and give each an address (`/menu/`) or a subdomain; see the Flux QR Menu setup notes.

> ℹ️ After a theme update, apply any pending item under **Site Setup › Ready-made updates**.

Full setup notes (Turkish): [KURULUM.md](../../docs/flx-menu-theme/KURULUM.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## For developers

- Menu addresses, subdomains, the menu manager role and SSL: [Flux QR Menu setup](../../docs/qr-menu/KURULUM.md).
- Ready-made updates are stored with their dates in the `yf_migrations` option.

## Roadmap

| | |
|---|---|
| ✅ | Blank white home page and plain templates |
| ✅ | Nine feature switches |
| ✅ | Site Setup and ready-made updates |
| ✅ | System checks |
| ⬜ | Custom favicon per menu subdomain |

## License

Flux Menu Theme Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
