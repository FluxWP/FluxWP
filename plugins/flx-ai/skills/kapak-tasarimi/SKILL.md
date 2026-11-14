---
name: kapak-tasarimi
description: Designs ready-to-share visuals in the brand style as PNG/JPEG - event covers, campaign and donation announcements, social media posts, stories, WhatsApp status, link previews, quote and thank-you cards, step/tip infographics and carousel pages - in several sizes from one brief. Use when the user says "kapak", "afiş", "etkinlik görseli", "duyuru görseli", "post görseli", "hikâye/story", "WhatsApp durumu", "Instagram/LinkedIn görseli", "karusel", "banner", "cover", "poster" or asks for a visual with text on it.
license: GPL-2.0-or-later
allowed-tools: flxai_brand_kit flxai_brand_kit_save flxai_design_render flxai_image_generate flxai_skill_file flxai_sh flxai_visual_report
metadata:
  title: Cover design
  version: "1"
---

# Kapak tasarımı

Text-on-image visuals with a fixed, tested design system: the same brand, type scale and safe areas every time. Never design from scratch when a template fits; adapt a template.

## Critical rules
- Use only the templates in `assets/` (read with flxai_skill_file). Keep their CSS and class names; change texts, colors in `:root`, the body class and remove blocks you do not need. New layouts only when the user explicitly asks for something none of the 4 templates can do; then follow `references/stil.md`.
- Every text must be real: dates, places, amounts, links come from the user or tools. Never invent. Missing a required fact (date, place) → ask once.
- Text is never generated inside the AI photo. flxai_image_generate prompts ask for a photo with NO text, letters, logos or watermarks, with empty calm space on the side where the text goes.
- Turkish typography: correct İ/ı, ğ, ş; uppercase tags use the template (CSS uppercase handles İ with lang="tr"). Dates like "14 Kasım 2026", money "1.250 TL". Write in the language the user writes in.
- Stay inside the safe area of the format (`references/formatlar.md`); the templates already do this, do not move blocks outside `.safe`.

## Workflow
1. **Brief.** From the message get: purpose, format(s), title (max ~5 words, one highlight word), tag/kicker, date-time, place, short text (max ~25 words), CTA, link for QR. Default formats: event → yatay + dikey + kare; site event card → kart; announcement → kare + dikey; link preview → og. Ask at most 3 questions only if a required fact is missing.
2. **Brand.** Call flxai_brand_kit. Colors: --dark = color_dark (else the darkest theme palette color), --accent = color_accent (else primary), --on-accent = dark readable color on the accent (check contrast ≥ 4.5:1; else use #111 or #fff). --scrim is --dark as "r,g,b". Logo on dark backgrounds: logo_light (else logo); on light (v-acik): logo. If there is no logo, remove the `<img class="logo">` and write the brand name as text in its place (font-weight 800). Partner logos only if the user asks.
3. **Layout.** Pick by content (see `references/stil.md` § choosing):
   - `etkinlik.html`: event with date/place, photo with scrim.
   - `duyuru.html`: campaign, donation call, announcement with a number or CTA; photo beside the panel.
   - `tipografik.html`: no photo; thanks, statement, news, quote (`v-alinti`), light version (`v-acik`).
   - `liste.html`: steps, tips, facts, 2-6 items; carousel pages.
4. **Photo** (etkinlik/duyuru): use the user's image (attachment_id) if given; otherwise ask whether to generate one (it costs money) or use the no-photo variant. Generate with flxai_image_generate, aspect 16:9 for landscape formats, 3:4 or 9:16 for portrait; prompt template in `references/stil.md` § photo prompt.
5. **Draft.** Fill the template, render ONE format first with flxai_design_render (images: `[{name:"bg.jpg",attachment_id:…},{name:"logo.svg",…},{name:"partner.png",…}]`: use exactly the file names the template uses, whatever the real file type). Show it: `![title](url)`. Ask for changes in one line.
6. **All sizes.** After approval change only the body class (and --bg-pos for portrait crops) and render the other formats. Name them "<title> - <format>".
7. **Save the source.** If a workspace is available, write the final HTML to `tasarimlar/<slug>.html` with flxai_sh so it can be revised later.

## If rendering is not available
flxai_design_render missing or "No visual renderer" → tell the user that a Gotenberg address is needed (Flux AI & MCP › Settings › Visual renderer), save the HTML to the workspace and show it with flxai_visual_report as a preview. Do not claim an image was produced.

## Revisions
Keep the same template and brief; change only what was asked. Text too long → shorten the text first (the template shrinks it with data-fit, but short copy is better design). Re-render only the affected formats.

## Output
Images inline (![...](url)), one line per format with its use (e.g. "Dikey 1080×1920: WhatsApp durumu, Instagram hikâye"), and the attachment ids. No HTML in the reply unless asked.
