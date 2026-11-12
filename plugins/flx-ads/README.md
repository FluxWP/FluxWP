<p align="center"><img src="docs/logo.svg" width="96" alt=""></p>

# Flux Ads

[Türkçe](README.tr.md)

Which ad brought which donation. Flux Ads sends donation and order conversions to **Meta (Pixel + Conversions API)**, **TikTok (Pixel + Events API)** and **Google Analytics 4 (Measurement Protocol)**, and shows on your own site how much each channel, campaign and ad brought in.

- **Server events** with the same event ID as the browser, so ad blockers and bank transfers approved days later are still counted, without double counting.
- **Bank transfer aware**: the conversion goes when the transfer is approved, not when the thank-you page opens.
- **Consent first**: pixels start only after the visitor accepts the cookie banner. E-mail and phone go only hashed, and by default only for contacts with ad consent.
- **Report**: channel, campaign, ad (utm_content), source/medium, landing page, first ad touch, new donors. Ad link (UTM) builder included.
- **Ad spend and ROAS**: Meta spend per campaign and ad is read from your ad account; other spend can be entered by hand. The report shows spend, ROAS, cost per payment and cost per new donor.
- **Donor value (LTV)**: each person belongs to the campaign of their first payment; their later payments count for it too. Per campaign: new donors, value at 90 days and so far, repeat rate, cost to win one (CAC), value / cost and payback time, plus a monthly cohort table.
- **Meta audiences**: send a Flux CRM group (everyone with ad consent, donors, a value group or a saved segment) to Meta as a custom audience, hashed and only with ad consent. Updated daily; withdrawn consent is removed at once. Lookalike audiences and exclusion lists (e.g. recent donors) included.
- **Campaigns**: Meta campaigns, ad sets and ads with spend next to the donations they brought, age/gender and placement breakdowns. Pause, turn on or change a daily budget from the panel, each after confirmation and kept in a change history.
- **Studio**: write a brief and Flux AI drafts a campaign (ad sets, audiences, budget split, text variants). You edit it; "Send to Meta" creates it paused, nothing runs until you turn it on.
- **Flux CRM**: ad conversions on the contact timeline and an "Ad source" card.
- **Flux AI / MCP**: read the report, donor value, spend, audiences and send log; change settings, add or delete spend, update Meta spend, create, update or delete audiences, test connections and resend conversions, each change after your approval. Secrets are never exposed.

Requires WooCommerce. Works with Flux Donate; Flux CRM is optional.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
