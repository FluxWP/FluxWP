<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux CRM logo" width="120">

# Flux CRM

**Donors, customers, volunteers and leads in one contact card, with a timeline, RFM scoring and segments.**<br>
Flux CRM turns the people already on your site into contacts: every donation, order, form and WhatsApp chat lands on one card per person. It scores everyone by recency, frequency and value, shows who is drifting away, and keeps live segments you can send to from Flux Mail.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![RFM](https://img.shields.io/badge/RFM-scoring-db2777)
![Segments](https://img.shields.io/badge/segments-live-db2777)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Screenshots](#screenshots) · [Installation](#installation) · [For developers](#for-developers) · [Roadmap](#roadmap)

<img src="docs/demo.gif" alt="Flux CRM tour: the overview with RFM segments and the reach-out list, the contact list, a contact timeline and the segment rule editor" width="900">

</div>

---

## Why Flux CRM?

Donations live in Flux Donate, orders in WooCommerce, messages in Flux Chat and sign-ups in Flux Forms. The same person shows up four times and nobody sees the whole picture. Flux CRM ties them together:

- 👤 **One card per person:** matched by source, account, e-mail and phone; what you type by hand is never overwritten.
- 🕒 **The whole story:** donations, orders, forms, WhatsApp, notes and calls on one timeline.
- 📉 **Who is drifting away:** RFM segments and a churn risk based on each person's own rhythm.
- 🎯 **Segments you can send to:** rule-based live lists that appear as recipients in Flux Mail, consent checked at send time.

## Features

### Contacts
- Roles (Donor, Customer, Lead, Volunteer, Member, Partner, Beneficiary and your own), tags, owner, short note.
- Consents per channel (e-mail, SMS, WhatsApp, ad audiences); every change is written to the timeline.
- Search, filters by role, RFM segment, saved segment, tag and consent; bulk tag, role, archive and delete; CSV export.

### Timeline
- Donations, orders (minus refunds), form answers, WhatsApp (one line per day), notes, calls, meetings, role and segment changes.
- Filters for payments, messages, forms and notes, with links back to the original record.

### Sources
- Flux Donate, WooCommerce (donation-only orders skipped), Flux Chat, Flux Forms and site members.
- New records arrive instantly; "Sync everything" imports the past in chunks of 100. Every event has a unique key, so syncing twice never duplicates anything.

### RFM scoring
- Recency, frequency (distinct paid days) and value in quintiles: New, Champion, Loyal, Promising, At risk, Hibernating, Lost.
- Churn risk follows the person's own payment gap: a monthly donor is at risk after 45 days, a yearly one is not. Recalculated daily, about 20 seconds at a time, continuing from the last contact.

### Segments
- Rule editor with a live count and sample contacts: role, tag, RFM, value, paid days, last payment, last activity, churn, consent, source, city.
- Use a segment as recipients in Flux Mail; only people who allowed that channel receive it.

### Journeys
- A journey starts when a contact enters a value group, gets a tag or role, has an event, or is added; steps wait, send e-mail / WhatsApp / SMS, tag, or continue only if a condition holds.
- New journeys are drafts until you turn them on. Only people who allowed the channel get messages, never in quiet hours, at most 3 per week; a donation can end the journey early.
- AI and MCP can draft and edit journeys; turning one on needs your approval.

## Screenshots

| Overview | Contacts |
|---|---|
| <img src="docs/images/overview-en.jpg" alt="Overview" width="440"> | <img src="docs/images/contacts-en.jpg" alt="Contacts" width="440"> |
| **Contact card and timeline** | **Segments** |
| <img src="docs/images/contact-en.jpg" alt="Contact card and timeline" width="440"> | <img src="docs/images/segments-en.jpg" alt="Segments" width="440"> |
| **Segment rule editor** | **Settings** |
| <img src="docs/images/segment-editor-en.jpg" alt="Segment rule editor" width="440"> | <img src="docs/images/settings-en.jpg" alt="Settings" width="440"> |
| **Dark mode** | **Contact card in dark mode** |
| <img src="docs/images/dark-en.jpg" alt="Dark mode" width="440"> | <img src="docs/images/dark-contact-en.jpg" alt="Contact card in dark mode" width="440"> |
| **Mobile** | **Contact on mobile** |
| <img src="docs/images/mobile-en.jpg" alt="Mobile" width="260"> | <img src="docs/images/mobile-contact-en.jpg" alt="Contact on mobile" width="260"> |

## Installation

Requirements: WordPress 6.4+, PHP 7.4+. Optional sources: Flux Donate, WooCommerce, Flux Chat, Flux Forms. Optional: Flux Mail (sending), Flux AI, Flux Admin.

**From a zip**

1. Download `flx-crm.zip` from the latest `flx-crm-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With FluxWP Panel** (recommended on our sites)

**FluxWP Panel › Packages** lists every FluxWP package; click **Install** next to *Flux CRM*. Updates arrive the same way.

**First steps**

1. Open **CRM** in the admin menu.
2. **CRM › Settings › Sync everything** once to bring in past donations, orders, forms and chats.
3. Pick which role each source gives new contacts, and the churn threshold.
4. Build a segment under **CRM › Segments** and send to it from **Flux Mail › Send**.

Full setup notes (Turkish): [KURULUM.md](../../docs/flx-crm/KURULUM.md) · API reference: [API.md](../../docs/flx-crm/API.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## For developers

- `FLXCRM_Contacts::upsert()`, `FLXCRM_Events::add()` with a `uniq` key, `FLXCRM_Contacts::ids( array( 'rules' => … ) )`.
- Actions: `flxcrm_event`, `flxcrm_contact_created`, `flxcrm_role_added`, `flxcrm_segment_changed`, `flxcrm_consent_changed`; filters `flxcrm_roles`, `flxcrm_segment_fields`, `flxcrm_fx_rate`, `flxcrm_sync_sources`.
- REST `flx-crm/v1` and 25 AI & MCP tools (one registry, `flxcrm_tools`): read and edit contacts, roles, tags, consents, timeline, segments and settings from Flux AI or any MCP client; delete, merge, bulk and send need approval. Full list: [API.md](../../docs/flx-crm/API.md).

## Roadmap

| | |
|---|---|
| ✅ | Contacts, roles, tags, consents, timeline |
| ✅ | Sources: Flux Donate, WooCommerce, Flux Chat, Flux Forms, site members |
| ✅ | RFM scoring, churn risk, segments for Flux Mail |
| ⬜ | Pipelines (donation follow-up, partnership, volunteering) |
| ✅ | Journeys: welcome, win-back and your own flows with frequency caps and quiet hours |
| ⬜ | Flux Ads: ad audiences and conversions as a separate plugin |

## License

Flux CRM Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
