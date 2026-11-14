# Design system (from the tested cover set)

## Look
- Photo covers: full-bleed photo, brand-dark scrim from the reading side (≈ .93 → .8 at 38% → 0 at 80%), plus a light dark gradient at the bottom/top for the footer and logo. Text never sits on a busy, unscrimmed photo.
- One accent color only (brand accent), used for: tag pill, one highlight word in the title, the big day number, icons. Everything else white (on dark) or brand dark (on light).
- Type: Figtree only (embedded). Title 900 weight, line-height 1, tracking -0.02em, max 2-3 lines, one `<span>`/`<em>` highlight. Labels 800 weight, uppercase, tracking 0.12em. Body 500, line-height 1.4, max ~25 words, max 3 lines. Caveat (handwritten) only for a whiteboard look.
- Hierarchy (event): tag → title → date (huge day + month/weekday/time) → place (pin icon) → one sentence → website pill bottom-left, QR card bottom-right.
- Spacing comes from the em scale (`--u` per format). Do not set pixel font sizes on single elements; change `--u` if the whole design must be bigger/smaller.
- Logos: white logo on dark, separated from a partner logo with a thin vertical white line. Logo top-left inside the safe area.
- QR: white rounded card, dark modules (--qr-color), 1-line caption ("Konum için okutun", "Bağış için okutun"). Only if there is a real URL. Prefer a short URL.
- Corners: pills fully round; cards .55-.7em radius. Shadows only on the QR card.

## Choosing a layout
| Content | Template | Notes |
|---|---|---|
| Event, meeting, planting day, webinar | etkinlik | needs date + place; QR to location/registration |
| Campaign, donation call, goal progress, new product, sale | duyuru | stat block for a real number (%, amount, count); CTA pill = the action |
| Thanks, statement, news headline, holiday greeting, hiring, quote | tipografik | v-alinti for quotes (needs author), v-acik for light |
| Steps, tips, checklist, facts, comparison, carousel page | liste | 2-6 items, title max 8 words, item title 2-4 words, text ≤ 14 words |

## Colors
- --dark: deep brand color (scrim, panels). Must pass 4.5:1 with white text.
- --accent: bright brand color. Text on it uses --on-accent (dark) and must pass 4.5:1.
- No brand kit colors: use the theme palette (darkest = --dark, most saturated = --accent). Otherwise default #063022 / #BED600 / #0A5A40.
- Light variant (v-acik): background --light (#F4F1EA), text brand dark.

## Photo prompt (flxai_image_generate)
"Photorealistic [scene: who, doing what, where], [season, time of day, light: e.g. warm golden-hour backlight], shallow depth of field, natural colors, documentary style. Main subject on the [right] third, the [left] side calm and darker (soft background) for text overlay. No text, no letters, no logos, no watermarks, no borders."
- Landscape formats: aspect 16:9, subject right. Portrait formats: 3:4/9:16, subject bottom, calm top.
- Real people/places of the organisation: prefer the user's own photos; AI photos are illustrative.

## Quality checklist (before showing)
- All facts from the brief; dates/weekday consistent (check with the calendar if unsure).
- Title ≤ 3 lines, body ≤ 3 lines, nothing cut (if data-fit shrank a lot, shorten the copy).
- Contrast: white text only on the scrim/dark panel; accent text only large.
- Nothing important outside the safe area; QR scannable size (≥ 150 px on 1080 wide).
- Turkish characters correct; no English leftovers from the template.
