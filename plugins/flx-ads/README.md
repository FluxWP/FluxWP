<p align="center"><img src="docs/logo.svg" width="96" alt=""></p>

# Flux Ads

[Türkçe](README.tr.md)

Which ad brought which donation. Flux Ads sends donation and order conversions to **Meta (Pixel + Conversions API)**, **TikTok (Pixel + Events API)** and **Google Analytics 4 (Measurement Protocol)**, and shows on your own site how much each channel, campaign and ad brought in.

- **Server events** with the same event ID as the browser, so ad blockers and bank transfers approved days later are still counted, without double counting.
- **Bank transfer aware**: the conversion goes when the transfer is approved, not when the thank-you page opens.
- **Consent first**: pixels start only after the visitor accepts the cookie banner. E-mail and phone go only hashed, and by default only for contacts with ad consent.
- **Report**: channel, campaign, ad (utm_content), source/medium, landing page, first ad touch, new donors. Ad link (UTM) builder included.
- **Ad spend and ROAS**: Meta spend per campaign and ad is read from your ad account; other spend can be entered by hand. The report shows spend, ROAS, cost per payment and cost per new donor.
- **Flux CRM**: ad conversions on the contact timeline and an "Ad source" card.
- **Flux AI / MCP**: read the report, spend and send log; change settings, add or delete spend, update Meta spend, test connections and resend conversions, each change after your approval. Secrets are never exposed.

Requires WooCommerce. Works with Flux Donate; Flux CRM is optional.

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
