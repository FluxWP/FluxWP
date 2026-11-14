---
name: kapak-tasarimi
description: Designs ready-to-share visuals in the brand style as PNG/JPEG - event covers, campaign and donation announcements, social media posts, stories, WhatsApp status, link previews, quote and thank-you cards, step/tip infographics and carousel pages - in several sizes from one brief. Use when the user says "kapak", "afiş", "etkinlik görseli", "duyuru görseli", "post görseli", "hikâye/story", "WhatsApp durumu", "Instagram/LinkedIn görseli", "karusel", "banner", "cover", "poster" or asks for a visual with text on it.
license: GPL-2.0-or-later
allowed-tools: flxai_brand_kit flxai_brand_kit_save flxai_design_render flxai_image_generate flxai_image_import flxai_image_edit flxai_image_view flxai_skill_file flxai_sh flxai_visual_report flxai_web_search flxai_web_fetch flxfm_*
metadata:
  title: Cover design
  version: "2"
---

# Kapak tasarımı

Text-on-image visuals: an original design for every brief, built on a fixed, tested quality base (brand, fonts, type scale, safe areas, legibility rules and the check loop). The 4 templates in `assets/` are tested references for that base, not the design to repeat.

## Critical rules
- **Original by default.** Design a new composition for each brief (`references/stil.md` § Original design): your own layout, shapes, crop and type treatment, in the brand colours and with Figtree/Caveat. Read the templates in `assets/` (flxai_skill_file) to reuse their tested mechanics (format classes and safe areas, `--u` em scale, `.main` + `data-fit`, `data-qr`, scrim), not their look. Reference images or examples the user gives are inspiration: take the mood, hierarchy or idea, never copy the layout.
- **Same when asked.** If the user says "aynısı", "bu şablonla", "bu tarzda", "öncekinin aynısı", "same as …" or names a template, use that template (or the referenced earlier HTML in the workspace / the `_flxai_design_html` of the earlier image) exactly and change only texts, colours and images. A series (carousel pages, the formats of one design, a weekly post series) keeps one look across its pieces.
- Do not repeat yourself: variants differ in composition, not only colour; a new brief in the same chat gets a fresh idea unless the user asks for the same look.
- Every text must be real: dates, places, amounts, links come from the user or tools. Never invent. Missing a required fact (date, place) → ask once.
- Never hand a visual to the user that you have not looked at. Renders and generations are shown to you automatically; check them (§ Workflow step 9).
- Text is never generated inside the AI photo for a template. flxai_image_generate prompts ask for a photo with NO text, letters, logos or watermarks, with empty calm space on the side where the text goes.
- Turkish typography: correct İ/ı, ğ, ş; uppercase tags use the template (CSS uppercase handles İ with lang="tr"). Dates like "14 Kasım 2026", money "1.250 TL". Write in the language the user writes in.
- Stay inside the safe area of the format (`references/formatlar.md`): key text, logo and QR inside it; only photos, shapes and colour may bleed to the edges.

## Workflow (the visual loop)
Work in the chat workspace: `gorseller/` (inputs), `tasarim/` (HTML source), `cikti/` (renders). Every render and generation is shown to you automatically: **look at it before the user does.**

1. **Brief.** From the message get: purpose, format(s), title (max ~5 words, one highlight word), tag/kicker, date-time, place, short text (max ~25 words), CTA, link for QR. Default formats: event → yatay + dikey + kare; site event card → kart; announcement → kare + dikey; link preview → og. Ask at most 3 questions only if a required fact is missing.
2. **Brand.** Call flxai_brand_kit. Colors: --dark = color_dark (else the darkest theme palette color), --accent = color_accent (else primary), --on-accent = dark readable color on the accent (check contrast ≥ 4.5:1; else use #111 or #fff). --scrim is --dark as "r,g,b". Logo on dark backgrounds: logo_light (else logo); on light (v-acik): logo. If there is no logo, remove the `<img class="logo">` and write the brand name as text in its place (font-weight 800). Partner logos only if the user asks.
3. **Concept.** Decide the idea before coding, in one line for yourself: the main visual (photo, big number, illustration, pure type), the composition (`references/stil.md` § Original design) and what the eye sees first. Same-look request → pick the template or earlier HTML instead.
4. **Collect.** Put every image the design needs into the workspace with flxai_image_import (logo, logo_light, partner logos from the brand kit; the user's photos by attachment_id; images found with flxai_web_search only if the user allows and the licence permits). You see each imported image: note where the subject/face is and which side is calm (text side).
5. **Prepare.** Fix inputs with flxai_image_edit before using them: `cover` to the target ratio with the focus on the subject (x/y), `darken` 20-40 if the photo is busy under the text, `trim` logos with empty margins, `resize` very large photos to ≤ 2400 px. Missing photo → step 6.
6. **Generate (optional, costs money).** Ask once before the first paid generation unless the user already asked for it. flxai_image_generate with the best model for the job: Nano Banana (Gemini) for fast photo-real scenes and edits with references, GPT Image (ChatGPT) for illustration, product shots and precise instructions, Nano Banana Pro for the hardest compositions. Photo for a layout: prompt from `references/stil.md` § photo prompt, NO text/letters/logos, calm empty area on the text side, aspect close to the format. Pass references in `refs` (place photo, product, a style example) and say the role of each in the prompt. Save with `library:false`, then continue from the returned path.
7. **Code.** Write the full HTML for your concept. Start from the closest template's skeleton for the mechanics (format classes, `.safe`, `--u`, `.main[data-fit]`, `data-qr`) and write your own layout CSS; for a same-look request copy the template and change only content. Images by the names used in the HTML: `images: [{name:"bg.jpg",path:"…/gorseller/foto-duzen.jpg"},{name:"logo.svg",path:"…/gorseller/logo.svg"}]`.
8. **Render the first format as a draft:** flxai_design_render with `html`, width/height, `title` (names the source), `draft:true`. The source is saved to the workspace (`source`, e.g. `tasarim/kis-kampanyasi.html`); from now on send only changes: `html_path` + `edits: [{find, replace}]`.
9. **Check (you see the render).** Go through this list honestly:
   - every text fully visible, nothing cut or overflowing, no awkward line breaks or a single orphan word;
   - spelling, Turkish letters, date/amount format, the right facts;
   - contrast and legibility at phone size (squint test), the highlight word visible;
   - logo present, sharp, not distorted, right variant for the background;
   - photo: subject/face not hidden behind text or cut by the crop; no AI artefacts (hands, faces, fake text);
   - safe area respected, spacing even, nothing touching the edges;
   - QR present when a link was given.
   Fix the cause (shorter text, `--bg-pos`, another crop with flxai_image_edit, darker scrim, smaller title) with `html_path` + `edits` and render again. **At most 3 fix rounds**; if it is still not right, show the best version and say what is left.
10. **Show and confirm.** Show the draft (`![title](url)`: render once with `draft:false` to get the media URL, or show the path) and ask for changes in one line.
11. **All sizes.** After approval render the other formats from the same source: `html_path` + `body_class` (e.g. `f-dikey`) + width/height, `draft:false` (fix `--bg-pos` or the `cover` focus for portrait with edits if needed); check each one the same way (you see them). Name them "<title> - <format>".

## Direct generation path
When the user wants a picture rather than a text layout (illustration, poster art, product/scene image, a "Gemini/ChatGPT style" visual) or asks to use the image generator directly:
1. Collect references as above (logo PNG, product, place, extra images the user gave). SVG logos: render the logo to PNG first (flxai_design_render with a transparent page: `<body style="background:transparent"><img src="logo.svg" style="width:100%;height:100%;object-fit:contain"></body>`) or use the PNG logo from the brand kit.
2. flxai_image_generate with `refs` and a precise prompt: subject, composition, style, brand colours as hex, where the logo goes ("place the logo from image 1 unchanged in the top-left corner, small"). Image models misspell text: keep on-image text to a few big words or none.
3. Check the result (you see it). Then either **finish** (good as is), **edit** it again with flxai_image_generate `refs:[{path:"<previous result>"}]` and a change instruction ("keep everything, only make the sky warmer"), or **finish in a template**: use it as `bg.jpg` and add the exact texts, logo and QR with flxai_design_render. Exact text, dates, QR and the real logo are always safer in the template.

## Variants ("3 revizyon", "3 seçenek", "alternatif")
Make the variants really different, not three copies: e.g. A = full-bleed photo with scrim, B = split or framed composition, C = typographic / big-number idea; for generated images use `n: 3` with one prompt, or three prompts with different compositions. Render each as a draft, check each one, then show all of them with one line each ("A: …, B: …, C: …") and ask which to continue with. flxai_image_view can show you up to 4 side by side to compare. Continue only with the chosen one.

## If rendering is not available
flxai_design_render missing or "No visual renderer" → tell the user that a Gotenberg address is needed (Flux AI & MCP › Settings › Visual renderer), save the HTML to the workspace and show it with flxai_visual_report as a preview. Do not claim an image was produced.

## Revisions
Keep the same design, HTML file and brief; edit the file in the workspace and change only what was asked. Text too long → shorten the text first (the template shrinks it with data-fit, but short copy is better design). Re-render only the affected formats.

## Output
Images inline (![...](url)), one line per format with its use (e.g. "Dikey 1080×1920: WhatsApp durumu, Instagram hikâye"), and the attachment ids. Say in one line what you checked and fixed (e.g. "başlık 2 satıra indirildi, fotoğraf odağı sağa alındı"). No HTML in the reply unless asked. The workspace keeps the sources (gorseller/, tasarim/, cikti/) for later revisions.
