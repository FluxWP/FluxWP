<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Donation logo" width="120">

# Flux Donation

**Sapling and campaign donations on WooCommerce, with a certificate for every gift.**<br>
Flux Donation turns WooCommerce into a donation system: donation types and campaigns with fixed packages, quantities or free amounts, a short donation form, card, bank transfer and monthly giving, donor cards and a dashboard. When a gift is paid, Flux Certificate prepares the certificate and sends it on WhatsApp and e-mail.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-7f54b3)
![Payments](https://img.shields.io/badge/pay-iyzico%20%C2%B7%20bank%20transfer%20%C2%B7%20monthly-2fbf8f)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Installation](#installation) · [For developers](#for-developers) · [Roadmap](#roadmap)

</div>

---

## Why Flux Donation?

A shop checkout is built for parcels: shipping address, company, postcode, "Place order". A donor wants to pick "5 saplings", write a name for the certificate and pay. Flux Donation keeps WooCommerce's payments and orders but makes the whole path about giving:

- 🌱 **Donation types, not products.** Packages (1, 5, 10 saplings), − / + quantities, free amounts with presets, campaigns with a target and dates. Prices always come from the type, never from the browser.
- 🧾 **A short checkout.** One contact block (phone or e-mail), no address, one terms checkbox and a fixed "Pay" bar at the bottom.
- 🏦 **Card, bank transfer and monthly.** Card fields on the page with instalments (iyzico), transfer with a personal reference code and receipt upload, monthly giving with a stored card.
- 🎓 **Certificates per person.** A gift on behalf of several people produces a certificate for each name, sent by WhatsApp and e-mail.
- 📊 **One place for the numbers:** totals, donors, recurring income, gifts per type and campaign, top donors.

## Features

### Donating
- **Donation options** block / `[flx_bagis_secenekleri]`: types as tabs, package cards with badges (Popular, Best value, Family), − / + quantity, free amount with preset buttons, "Repeat monthly".
- "For myself" or "on behalf of someone else" (the name goes on the certificate), WhatsApp and e-mail for the certificate, "send me a copy too", donor-selectable certificate designs from Flux Certificate.
- Classic and block checkout with a single contact section, required terms consent and a fixed total bar.
- **Bank transfer:** IBAN from one place, a personal reference code, receipt upload on the thank-you page or under **My account › Pending donations**; one click **Approve payment** sends the certificate.
- **Card (iyzico):** card fields on the checkout, instalments, 3D Secure, "Save my card". **Monthly giving** renews with the stored card and retries after 1 and 3 days.

### Managing
- **Dashboard:** total donations, donors, new donors, average gift, monthly recurring income, active recurring donations, daily chart, one-time vs recurring, per type and per form, top donors, CSV.
- **Donations:** filters, one row per basket, details with items and names, printable receipt, refund through WooCommerce, approval card for transfers with receipt preview and duplicate-receipt warning.
- **Donors:** one card per person (matched by phone or e-mail, never by name) with history, recurring gifts, certificates and consents.
- **Donation types and campaigns:** categories, options and prices, free amounts, quantities, recurring, dates, target with progress, open/closed/archived; sample sets to start from. Each type has a hidden WooCommerce product.
- **Add donation** for cash or transfers received elsewhere; **referrals** per donation with Flux Roles, staff see only their own.
- Product feed `bagis-urunleri.xml` for iyzico and Google Merchant Center; optional Qurbani module.

## Installation

Requirements: WordPress 6.4+, PHP 7.4+. WooCommerce 8.0+. Optional: Flux Certificate 1.2+ (certificates), Flux Contact (e-mails, WhatsApp), Flux Forms 2.1+ (payment forms), the iyzico WooCommerce plugin.

**From a zip**

1. Download `flx-donate.zip` from the latest `flx-donate-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With Flux Updater** (recommended on our sites)

**Dashboard › Updates** lists every FluxWP package; click **Install** next to *Flux Donation*. Updates arrive the same way.

**First steps**

1. Install and activate WooCommerce (currency TRY), then Flux Donation.
2. **Donations › Donation types**: create a type (or **Load sample types**) and tick *Show in the Donation options block*.
3. **Donations › Settings › WooCommerce › Create the online donation page**, then enable *Bank transfer (donation)* and *Card (iyzico)* under **WooCommerce › Settings › Payments**.
4. Open **Settings › System check** (or **Performance › System Check** with Flux Performance) and fix any red rows.

> ⚠️ Monthly giving needs WP-Cron to run from a real cron job. Flux WooCommerce Donation (`flx-wc-bagis`) is retired: everything it did is part of Flux Donation, so do not run both.

Full setup notes (Turkish): [KURULUM.md](../../docs/bagis-eklentisi/KURULUM.md) · API reference: [API.md](../../docs/bagis-eklentisi/API.md)

## For developers

- Reading API for themes: `flxb_get_types()`, `flxb_get_type()`, `flxb_type_state()`, REST `/wp-json/flxb/v1/types`, pre-selection `?flxb_opt=…&flxb_qty=…`.
- Recording from other packages: `flxb_create_order()` (preferred) or `FLXB_Donations::record()`; `flxb_pending_count()` for approval badges.
- App endpoints `flxb/v1/me*` and `flxb/v1/app/*` for Flux App; capabilities `flxb_view_own`, `flxb_create_ref`, `flxb_donors_own`, `flxb_dashboard` for Flux Roles.
- Shortcodes: `[flx_bagis_secenekleri]`, `[flx_bagis_kampanya slug="fidan"]`.

## Roadmap

| | |
|---|---|
| ✅ | Donation types, campaigns and the donor dashboard |
| ✅ | WooCommerce checkout with card, transfer and monthly giving |
| ✅ | Certificates per person and donor-selectable designs |
| ✅ | Referrals and staff views with Flux Roles |
| ✅ | Approvals in the mobile app |
| ⬜ | Printed certificates by post |
| ⬜ | Donor self-service for changing a monthly amount |

## License

Flux Donation Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
