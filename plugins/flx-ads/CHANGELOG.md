# Changelog

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
