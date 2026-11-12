# Changelog

## 0.5.0

- Studio: new page. Write a short brief and Flux AI drafts a Meta campaign from it and this site's numbers (last 90 days of campaigns, your audiences, donor counts; no personal data): objective, daily budget, ad sets with ages, countries, audiences in/out and budget share, and ad text variants with a short reason.
- The draft stays here until you send it: edit every field, add or remove ad sets and variants, see a live ad preview, duplicate or delete. A draft can also start empty.
- "Send to Meta" creates the campaign, ad sets, creatives and ads all paused, after confirmation. Ad links carry UTM tags (campaign ID, variant), so donations show up in the report and under Campaigns. If a step fails, the next try reuses what was already made.
- New setting: Facebook Page ID (Settings › Meta), the page the ads are published as.
- Flux AI / MCP: `flxads_studio_context`, `flxads_drafts` (read), `flxads_draft_save` (draft here only), `flxads_draft_send`, `flxads_draft_delete` (need approval).

## 0.4.0

- Campaigns: new page with your Meta campaigns for the last 7, 30 or 90 days. Each shows its status, objective, budget, spend, impressions, link clicks and CTR next to what it brought on this site: income, payments and ROAS, matched by the campaign name or ID in utm_campaign.
- Campaign details: ad sets, with a targeting summary (ages, gender, places, audiences in/out), and their ads with the creative title. Spend broken down by age + gender and by placement.
- Pause or turn on a campaign, ad set or ad, and change a daily budget, from the panel. Each change asks for confirmation, goes to Meta at once and is written to the change history (who, from where, old → new). Nothing is deleted or archived from here.
- Meta numbers are cached for 10 minutes; a change clears the cache. Reading needs ads_read, changes need ads_management.
- Flux AI / MCP: `flxads_campaigns`, `flxads_campaign_detail` (read), `flxads_ad_status`, `flxads_ad_budget` (need approval; the budget preview warns above a 30% change).

## 0.3.0

- Donor value (LTV): new page. Each person belongs to the campaign of their first payment; all their later payments count for it. Per campaign or channel: new people, first payment, value at 90 days and so far, repeat rate, cost to win one (CAC), value / cost and payback days. Monthly cohort table of value per person.
- Meta audiences: new page. A Flux CRM group (everyone with ad consent, donors, an RFM value group or a saved segment) becomes a Meta custom audience. Only people with ad consent, hashed email, phone, name and country. Daily delta sync; withdrawn consent or a deleted contact is removed from Meta at once. Lookalike audiences and exclusion audiences.
- Flux AI / MCP: `flxads_value_report`, `flxads_audiences_list`, `flxads_audience_create`, `flxads_audience_lookalike`, `flxads_audience_sync`, `flxads_audience_delete` (creating and deleting asks for approval first).
- Fix: the hourly job did not refresh Meta spend (only opening the screen did).
- System check row for audiences. The Marketing API key now explains that audiences need ads_management.

## 0.2.2

- Report, Ad spend, Send log, Ad link and Settings are now submenus in the left menu (own pages: `flx-ads`, `flx-ads-spend`, `flx-ads-log`, `flx-ads-links`, `flx-ads-settings`). Old `?page=flx-ads&tab=…` links still open the right page.
- Package catalog entry: icon, description and screenshots show in FluxWP Panel › Packages before install; setup wizard preview.

## 0.2.1

- Shared FluxWP library (flx-brand) brought up to date: the new shared dialog files are included, so CI verify passes.

## 0.2.0

- Ad spend: Meta spend per campaign and ad is read daily from the ad account (Marketing API, read only), every 6 hours. Other spend (Google Ads, TikTok, posters) can be entered by hand on the new Ad spend tab.
- Report: ad spend, ROAS, cost per payment and cost per new donor; Spend and ROAS columns by channel, campaign and ad. Campaigns with spend but no payment are listed too.
- Campaign and ad names match regardless of case, spaces and Turkish letters ("Fidan Kasım 2026" = "fidan-kasim-2026").
- MCP: `flxads_report` returns spend, ROAS and cost; `flxads_status` the spend sync state. System check for the spend sync.
- Flux AI and MCP can now change things too: `flxads_settings_get` / `flxads_settings_update`, `flxads_test_connection`, `flxads_send_log` / `flxads_resend`, `flxads_spend_list` / `flxads_spend_add` / `flxads_spend_delete` / `flxads_spend_sync`. In Flux AI chat every change waits for the approval card; over MCP, settings changes and deleting spend first return a preview and a `confirm_token`. Access tokens and secrets can never be read or set by AI. Changes are logged (`flxads_tool_log`, last 100).

## 0.1.0

- New plugin: ad conversion tracking for donations and orders. Every paid order (donation or shop order) is a conversion; the report filters by kind.
- Meta Pixel + Conversions API, TikTok Pixel + Events API, Google Analytics 4 + Measurement Protocol. Browser and server events share one event ID (`order-<id>`), so Meta and TikTok count a payment once; GA4 server events wait 30 minutes and are skipped when the thank-you page already sent the purchase.
- Bank transfer (Havale/EFT): no browser conversion while the payment is pending; the server sends Purchase when the donation is approved (Flux Donate calls `payment_complete`).
- Attribution: UTM (source, medium, campaign, content, term, id) and click IDs (fbclid, gclid, gbraid, wbraid, ttclid, msclkid) in a first-party cookie with first and last touch; falls back to WooCommerce order attribution.
- Cookie consent: pixels and the ad cookie start only after the FluxWP theme banner (or WP Consent API) is accepted; declining removes the ad cookies. Payments of visitors without consent are counted in the report but not sent to ad platforms.
- E-mail and phone go only as SHA-256 hashes, by default only for contacts with ad consent in Flux CRM.
- Report: income by ad channel, campaign, ad (utm_content), source/medium, landing page and first ad touch; new donors; latest payments from ads. Send log with retry, ad link (UTM) builder.
- Flux CRM: "Ad conversion" on the timeline, "Ad source" card on the contact, empty "First campaign" filled from the first ad touch.
- MCP / Flux AI tools: `flxads_report`, `flxads_status`. System checks for platforms, queue and cookie banner.
- Secrets stored encrypted (sodium), write-only in the panel; optional wp-config overrides `FLXADS_META_TOKEN`, `FLXADS_TIKTOK_TOKEN`, `FLXADS_GA4_SECRET`.
