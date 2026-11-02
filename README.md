<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="plugins/flx-admin/assets/brand-icon.svg" alt="FluxWP Suite" width="96">

# FluxWP Suite

### All-in-one WordPress solutions for nonprofits and online stores

Donations, e-commerce, communication, AI, analytics and team tools: 21 plugins and 3 themes that share one design, one AI budget and one update server, so a single WordPress site can run the whole organisation.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org) [![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net) [![WooCommerce](https://img.shields.io/badge/WooCommerce-ready-7f54b3?logo=woocommerce&logoColor=white)](https://woocommerce.com) ![MCP](https://img.shields.io/badge/AI-MCP%20ready-7d64ff) ![Packages](https://img.shields.io/badge/packages-21%20plugins%20%C2%B7%203%20themes-2fbf8f) [![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)

[Why](#why-flx-suite) · [Solutions](#solutions) · [How it fits together](#how-it-fits-together) · [Packages](#packages-and-latest-versions) · [Get started](#get-started) · [For developers](#for-developers)

</div>

---

## Why FluxWP Suite?

Most WordPress sites end up as a patchwork: a donation plugin, a form plugin, an SMTP plugin, an analytics script, a chat widget, each with its own settings, its own upsell and its own idea of what the admin should look like. FluxWP Suite was built the other way round, for a real charity that needed all of it on shared hosting:

- 🧩 **Built to work together.** A donation made on WhatsApp shows up in Flux Donation, triggers a certificate, sends the e-mail through Flux Contact and lands on the office TV.
- 🤖 **AI where it helps, on one budget.** Every package uses Flux AI, so keys, models and daily spending limits live in one place, and assistants like Claude can work through MCP.
- 📱 **One calm admin, also on a phone.** Flux Admin gives every package the same look, a role-based menu and a mobile bottom bar.
- 🔒 **Your data stays on your site.** Cookie-free analytics, local backups, no external SaaS for the core work; GPL and self-hosted.
- 🌍 **Turkish and English everywhere.** Every package ships both languages; content goes multilingual with Flux Language.

## Solutions

### 🌱 Donations & e-commerce

Take gifts and payments on WooCommerce, with forms that look like your site and a certificate for every donor.

| | |
|---|---|
| [**Flux Donation**](plugins/flx-donate) | Sapling and campaign donations: card, bank transfer, monthly giving, donor accounts. |
| [**Flux Forms**](plugins/flx-forms) | Drag-and-drop forms with payments, conditions and automations. |
| [**Flux Certificate**](plugins/flx-certificates) | Personal certificates with PDF, share link and verification. |
| [**Flux QR Menu**](plugins/flx-qr-menu) | QR menus for stands and events, live on phones in seconds. |

### 💬 Communication

Every channel your supporters use, answered from one admin.

| | |
|---|---|
| [**Flux Call Center**](plugins/flx-chat) | WhatsApp inbox for the team, with an AI assistant that records donations. |
| [**Flux Contact**](plugins/flx-mail) | E-mail templates, mailbox, delivery logs; WhatsApp and SMS lines for all packages. |
| [**Flux Social**](plugins/flx-social) | Plan, approve and publish social posts with a shared calendar. |

### 🤖 AI & automation

One AI budget for the whole site, and a door for assistants like Claude.

| | |
|---|---|
| [**Flux AI**](plugins/flx-ai) | Keys, models, spending limits and a shared chat for every package. |
| [**Flux SEO**](plugins/flx-seo) | Lightweight SEO plus an MCP server so AI can manage content. |
| [**Flux Studio**](plugins/flx-studio) | AI image and video studio with references and a shared gallery. |

### 🧭 Team & admin

A calm admin that works on a phone, with the right access for every person.

| | |
|---|---|
| [**Flux Admin**](plugins/flx-admin) | Modern admin: role-based menu, mobile bottom bar, command palette, PWA. |
| [**Flux Roles**](plugins/flx-roles) | Staff and manager roles, every capability on one page, referral links. |
| [**Flux Tasks**](plugins/flx-tasks) | Lists, boards, calendars, meetings and docs with an AI assistant. |
| [**Flux App**](plugins/flx-app) | OAuth sign-in and API for the desktop and mobile app and MCP clients. |

### 📈 Growth & content

Know what works and reach people in their language.

| | |
|---|---|
| [**Flux Site Analytics**](plugins/flx-analytics) | Cookie-free analytics with donation funnel, journeys and live widgets. |
| [**Flux Language**](plugins/flx-lang) | Multilingual site with /en/ URLs, hreflang and one-click AI translation. |

### 🛠️ Operations

Private updates, backups and performance on shared hosting.

| | |
|---|---|
| [**Flux Updater**](plugins/flx-updater) | Private packages in the normal WordPress updates screen. |
| [**Flux Backup**](plugins/flx-backup) | A backup before every update, scheduled backups, selective restore. |
| [**Flux Performance**](plugins/flx-speed) | Slow queries, errors, cron and the System Check on one screen. |

### 📺 Screens

Put the day's numbers on the office TV.

| | |
|---|---|
| [**Flux TV Board**](plugins/flx-tv-board) | Live donations, tasks and prayer times on a Google TV board. |
| [**Flux TV Remote**](plugins/flx-tv-remote) | Android TV remote in the admin through a small home bridge. |

### 🎨 Themes

Full-site-editing block themes with one-click Site Setup.

| | |
|---|---|
| [**FluxWP**](themes/fluxwp) | A simple, fast WooCommerce block theme; presets turn it into any site (see FluxWP Extras). |
| [**Flux Menu Theme**](themes/flx-menu-theme) | Blank, hardened theme for a QR menu domain. |

## How it fits together

Packages talk to each other through small public filters and REST endpoints; nothing is required except where an arrow says so.

```mermaid
flowchart LR
  subgraph Shared AI
    AI["Flux AI"]
    MCP["Flux SEO"]
  end
  BAG["Flux Donation"] --> CERT["Flux Certificate"]
  FORM["Flux Forms"] --> BAG
  BAG --> WOO[("WooCommerce")]
  CC["Flux Call Center"] --> BAG
  CC --> COM["Flux Contact"]
  CERT --> COM
  SOC["Flux Social"] --> AI
  STU["Flux Studio"] --> AI
  CC --> AI
  TASK["Flux Tasks"] --> AI
  ADM["Flux Admin"] --- ROL["Flux Roles"]
  APP["Flux App"] --> MCP
  UPD["Flux Updater"] -.-> ALL(("all packages"))
  BAK["Flux Backup"] -.-> ALL
  PERF["Flux Performance"] -.-> ALL
```

## Packages and latest versions

<!-- paketler:start -->
| Package | Description | Type | Latest | Date | Download |
|---|---|---|---|---|---|
| [Flux Admin](plugins/flx-admin) | Redesigns the admin: role-based sidebar, mobile bottom bar, command palette, light and dark. Installs as a PWA. | Plugin | [1.4.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-admin-v1.4.1) | 2026-11-02 | [flx-admin.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-admin-v1.4.1/flx-admin.zip) |
| [Flux AI](plugins/flx-ai) | One AI hub for every package: keys, models, spending limits and a shared AI chat. | Plugin | [0.6.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-ai-v0.6.0) | 2026-11-02 | [flx-ai.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-ai-v0.6.0/flx-ai.zip) |
| [Flux Site Analytics](plugins/flx-analytics) | Cookie-free site analytics that keep visitor data on your site. Sources, donation funnel and journeys. | Plugin | [0.3.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-analytics-v0.3.1) | 2026-11-02 | [flx-analytics.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-analytics-v0.3.1/flx-analytics.zip) |
| [Flux App](plugins/flx-app) | Sign-in for the desktop and mobile app: OAuth 2.0 (PKCE), API keys and a role-based menu. | Plugin | [0.3.3](https://github.com/FluxWP/fluxwp/releases/tag/flx-app-v0.3.3) | 2026-11-02 | [flx-app.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-app-v0.3.3/flx-app.zip) |
| [Flux Backup](plugins/flx-backup) | Automatic backups before updates, scheduled full backups and selective restore. | Plugin | [1.2.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-backup-v1.2.1) | 2026-11-02 | [flx-backup.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-backup-v1.2.1/flx-backup.zip) |
| [Flux Certificate](plugins/flx-certificates) | Certificate templates, live PDF preview and /certificate/{code} verification. Sent by e-mail, SMS and WhatsApp. | Plugin | [1.8.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-certificates-v1.8.0) | 2026-11-02 | [flx-certificates.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-certificates-v1.8.0/flx-certificates.zip) |
| [Flux Call Center](plugins/flx-chat) | A WhatsApp inbox with canned replies, tags and assignment. An AI assistant that records donations and certificates. | Plugin | [1.35.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-chat-v1.35.0) | 2026-11-02 | [flx-chat.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-chat-v1.35.0/flx-chat.zip) |
| [Flux Donation](plugins/flx-donate) | Sapling and campaign donations on WooCommerce with card, bank transfer and monthly giving. A certificate for every gift. | Plugin | [2.11.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-donate-v2.11.1) | 2026-11-02 | [flx-donate.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-donate-v2.11.1/flx-donate.zip) |
| [Flux Forms](plugins/flx-forms) | Drag-and-drop form builder with multi-step forms, payments, conditional logic and automations. | Plugin | [2.1.3](https://github.com/FluxWP/fluxwp/releases/tag/flx-forms-v2.1.3) | 2026-11-02 | [flx-forms.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-forms-v2.1.3/flx-forms.zip) |
| [Flux Language](plugins/flx-lang) | Makes the site multilingual: /en/ addresses, per-post translations, hreflang and a language switcher. | Plugin | [0.1.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-lang-v0.1.0) | 2026-11-02 | [flx-lang.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-lang-v0.1.0/flx-lang.zip) |
| [Flux Contact](plugins/flx-mail) | Every e-mail the site sends, a mailbox and delivery logs in one place. WhatsApp and SMS lines shared with other packages. | Plugin | [1.12.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-mail-v1.12.0) | 2026-11-02 | [flx-mail.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-mail-v1.12.0/flx-mail.zip) |
| [Flux Migrate](plugins/flx-migrate) | Eski paket adlarıyla kaydedilmiş site verilerini (ayarlar, tablolar, meta, içerik, tema, yükleme klasörleri) FluxWP adlarına taşır. | Plugin | [0.1.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-migrate-v0.1.0) | 2026-11-02 | [flx-migrate.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-migrate-v0.1.0/flx-migrate.zip) |
| [Flux QR Menu](plugins/flx-qr-menu) | Manage and publish QR menus from the site. Menu manager role, QR code and change history. | Plugin | [1.3.2](https://github.com/FluxWP/fluxwp/releases/tag/flx-qr-menu-v1.3.2) | 2026-11-02 | [flx-qr-menu.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-qr-menu-v1.3.2/flx-qr-menu.zip) |
| [Flux Roles](plugins/flx-roles) | Staff, Manager and Admin roles; every package's capabilities on one page. Personal referral links. | Plugin | [1.2.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-roles-v1.2.0) | 2026-11-02 | [flx-roles.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-roles-v1.2.0/flx-roles.zip) |
| [Flux SEO](plugins/flx-seo) | Meta, Open Graph, schema, sitemap and SEO checks. An MCP server for AI assistants. | Plugin | [1.2.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-seo-v1.2.1) | 2026-11-02 | [flx-seo.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-seo-v1.2.1/flx-seo.zip) |
| [Flux Setup](plugins/flx-setup) | Preset engine: one file sets up the organisation profile, settings, header/footer, templates, pages and menus. Edits the site through approved patches from AI chat and MCP. | Plugin | [0.2.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-setup-v0.2.0) | 2026-11-02 | [flx-setup.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-setup-v0.2.0/flx-setup.zip) |
| [Flux Social](plugins/flx-social) | Social media inside the site: accounts, scheduling, approval and calendar. AI writing help and MCP tools. | Plugin | [0.1.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-social-v0.1.0) | 2026-11-02 | [flx-social.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-social-v0.1.0/flx-social.zip) |
| [Flux Performance](plugins/flx-speed) | Queries, PHP errors, slow requests, server, database and cron on one screen. Home of the System Check. | Plugin | [0.2.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-speed-v0.2.0) | 2026-11-02 | [flx-speed.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-speed-v0.2.0/flx-speed.zip) |
| [Flux Studio](plugins/flx-studio) | An AI image and video studio. Generation, references and a foldered gallery through Flux AI. | Plugin | [0.3.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-studio-v0.3.0) | 2026-11-02 | [flx-studio.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-studio-v0.3.0/flx-studio.zip) |
| [Flux Tasks](plugins/flx-tasks) | Task tracking with projects, lists, boards and calendars. Meeting reports, notifications and MCP tools. | Plugin | [0.8.2](https://github.com/FluxWP/fluxwp/releases/tag/flx-tasks-v0.8.2) | 2026-11-02 | [flx-tasks.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-tasks-v0.8.2/flx-tasks.zip) |
| [Flux Updater](plugins/flx-updater) | Brings private themes and plugins from your own update server into the WordPress updates screen. | Plugin | [1.5.0](https://github.com/FluxWP/fluxwp/releases/tag/flx-updater-v1.5.0) | 2026-11-02 | [flx-updater.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-updater-v1.5.0/flx-updater.zip) |
| [FluxWP](themes/fluxwp) | A simple, fast WooCommerce block theme. Hero, header, footer and home patterns are fully replaceable by presets. | Theme | [0.6.0](https://github.com/FluxWP/fluxwp/releases/tag/fluxwp-v0.6.0) | 2026-11-02 | [fluxwp.zip](https://github.com/FluxWP/fluxwp/releases/download/fluxwp-v0.6.0/fluxwp.zip) |
| [Flux Menu Theme](themes/flx-menu-theme) | The quietest theme for a QR menu domain. Menus are served by Flux QR Menu. | Theme | [1.2.1](https://github.com/FluxWP/fluxwp/releases/tag/flx-menu-theme-v1.2.1) | 2026-11-02 | [flx-menu-theme.zip](https://github.com/FluxWP/fluxwp/releases/download/flx-menu-theme-v1.2.1/flx-menu-theme.zip) |

All versions: [Releases](https://github.com/FluxWP/fluxwp/releases). This table is updated by the release workflow.
<!-- paketler:end -->

## Get started

1. Install **Flux Updater** from the [latest `flx-updater` release](https://github.com/FluxWP/fluxwp/releases) (**Plugins › Add New › Upload Plugin**).
2. In **Dashboard › Updates** pick the packages you need and click **Install**; updates arrive the same way.
3. Recommended base: **Flux Admin**, **Flux Roles**, **Flux AI** and **Flux Performance** (System Check). Add solutions as you need them.
4. Each package README has screenshots, setup steps and a roadmap; detailed notes are in [`docs/`](docs/README.md).

## For developers

```
plugins/<slug>/      plugins (main file <slug>/<slug>.php)
themes/<slug>/       themes (style.css)
bin/                 build.sh, package-info.sh, release-notes.sh, test-update.sh, readme-table.sh
tools/readme-media/  screenshots, GIFs and README generator
.github/workflows/   release.yml, mirror.yml
docs/                setup and API notes per package (docs/README.md, Turkish)
.claude/skills/      Claude Code skills: flx-wp-eklenti, flx-wp-tema, flx-paket-yayin, flx-readme-tanitim
```

### Releasing a version

1. Bump the package's `Version:` header (and its version constant, e.g. `FLXB_VERSION`).
2. Optionally add `CHANGELOG.md`; the text under `## 1.2.3` becomes the release notes, otherwise the last commit message is used.
3. Push to `main`.

GitHub Actions builds `dist/<slug>.zip` for every package whose version changed, publishes a `<slug>-v<version>` release and clears the update server cache; sites see it under **Dashboard › Updates** within minutes. A version with `-` (`1.3.0-beta.1`) is a pre-release for sites on the beta channel. Local zip: `bin/build.sh plugins/flx-forms`. Files listed in a package's `.distignore` stay out of the zip.

### Adding a package

Create `plugins/<slug>/` or `themes/<slug>/` with a `Version:` header and push; the update server picks it up from the release.

### Update server

The update server lives in [FluxWP/updater](https://github.com/FluxWP/updater) (`update.yesilturkiyeist.org`); sites need `plugins/flx-updater`.

### Promo media

Screenshots, GIFs and package READMEs are generated by `tools/readme-media` (see its README): `bash tools/readme-media/run.sh <slug> [plugins|themes]`.

## FluxWP and shared TS libraries
The desktop/web app (React + Tauri) and the shared layer that plugin admin screens also use live at the root: `packages/` (core, tokens, ui, modules, panel), `apps/desktop`, `apps/wp-embed`, `brands/`. Package manager is Bun: `bun install`, `bun run dev`. Details: [docs/fluxapp/README.md](docs/fluxapp/README.md).

## License

All packages are released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).<br>Made by FluxWP.
