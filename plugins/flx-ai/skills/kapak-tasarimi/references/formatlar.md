# Formats and safe areas

| Class | Size (px) | Use | Safe area (all text, logos, QR inside) |
|---|---|---|---|
| f-yatay | 1920×1080 | YouTube/screen, Facebook event, presentation, TV | 110 left/right, 80 top, 70 bottom |
| f-kart | 1600×1000 | Event image on the website (FluxWP theme / Flux Certificates cards) | x 200-1400, y 120-880 |
| f-dikey | 1080×1920 | Instagram/Facebook story, WhatsApp status, Reels cover | top 230-250 and bottom 250 kept clear (app UI) |
| f-kare | 1080×1080 | Instagram/Facebook/X post, WhatsApp image | 64-80 all sides |
| f-portre | 1080×1350 | Instagram/LinkedIn feed (takes more screen), carousel page | 72-90 all sides |
| f-og | 1200×630 | Link preview (Open Graph), blog featured image, e-mail header | 48-64; no long text, no QR |

## Why the website card has a big safe area (f-kart)
The same event image is cropped with `object-fit: cover` in three places:
- event detail page 2:1 → cuts 100 px top and bottom,
- single card on desktop ~1.4:1 → cuts ~150 px left and right,
- card grid and mobile 16:10 → full image, but the date badge covers the top-left ~23% × 34%.
So keep text and logos in x 200-1400, y 120-880 and keep faces/objects near the centre.

## Other common sizes (use the closest class and set width/height)
- LinkedIn link/post landscape 1200×627 → f-og. YouTube thumbnail 1280×720 → f-yatay (scale is the same ratio).
- A4 poster for print 2480×3508 (300 dpi) → f-portre layout with width 2480, height 3508 and `--u` ×2.3; tell the user to print at "actual size".
- X/Twitter header 1500×500 and Facebook cover 1640×624: f-og layout, keep content in the centre 60% (profile photo covers the bottom left).

## Format choice
- Story/status: always f-dikey; nothing important in the top 250 px and bottom 250 px.
- Feed: prefer f-portre (more screen) unless the user asks for square.
- One message, several channels: render f-kare + f-dikey + f-yatay from the same HTML (only the body class changes).
