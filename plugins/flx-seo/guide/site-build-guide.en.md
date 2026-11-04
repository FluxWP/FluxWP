# FluxWP site build guide: SEO, performance, accessibility, security and agent readiness

This guide collects the rules to follow when building a new WordPress site, theme, preset (flx-preset-*), block pattern or page. The target is **100** in all four PageSpeed Insights categories and a **full score** in Agentic Browsing. AI agents get this guide from the Flux SEO MCP server with the `flxseo_site_build_guide` tool (or the `flxseo://guide/site-build` resource); Flux AI chat uses the same tool.

<!-- topic:workflow -->
## 1. Workflow (for agents)

1. Read this guide before you start: `flxseo_site_build_guide` (topic: `all` or the relevant section).
2. Use `flxseo_site_info` to learn the site and `flxseo_list_patterns` to see the theme's ready-made sections. Reuse before writing new ones.
3. Build the page/theme with the rules below. Content is created as a draft; publishing needs the user's approval.
4. When done, run `flxseo_site_audit` with `refresh: true`. Findings come sorted **critical → high → medium → low**; fix them in that order.
5. If the user wants, run `flxseo_site_audit` with `pagespeed: "mobile"` (then `"desktop"`) for the real Lighthouse score. One run takes 15–40 s.
   Every run is saved; `flxseo_site_audit_history` shows past reports, the trend and what was resolved (useful to check that a fix stuck).
6. Findings with a `fix_id` can be fixed in one step: pass that id to `flxseo_system_fix` (tell the user what will be turned on first).
7. After fixing, purge the page cache (LiteSpeed › Purge All) and run the audit again.

### Priorities

| Priority | Meaning | Examples |
|---|---|---|
| Critical | The site is invisible to Google or does not load | noindex, robots.txt `Disallow: /`, HTTP 500, no viewport, LCP > 4 s |
| High | Directly lowers a score or blocks users | user-scalable=no, broken ARIA, images without alt, no meta description |
| Medium | Measurable loss, quick to fix | render-blocking CSS, duplicate page, no CSP/XFO, no llms.txt |
| Low | Fine tuning | JS minify, HSTS preload, Trusted Types |

<!-- topic:performance -->
## 2. Performance

**Targets (mobile, Slow 4G):** LCP < 2.5 s · CLS < 0.1 · TBT < 200 ms · FCP < 1.8 s · Speed Index < 3.4 s · HTML < 100 KB · total page < 500 KB · DOM < 1,500 elements.

### CSS
- Inline the critical CSS needed for the first screen (in `<head>`); defer the rest: `<link rel="stylesheet" href="…" media="print" onload="this.media='all'">` plus a `<noscript>` fallback.
- Split one big stylesheet by template/block (`wp_enqueue_block_style`, `theme.json` styles). Keep unused CSS under 20 KB.
- No `@import`; it creates request chains.

### Fonts
- At most 2 families and only the weights used above the fold. Prefer one variable font file.
- Always `@font-face { font-display: swap; }`. Preload the first-screen font: `<link rel="preload" href="…/font.woff2" as="font" type="font/woff2" crossorigin>`.
- Separate latin and latin-ext subsets with `unicode-range`.
- Avoid shifts when the font swaps: tune the fallback with `size-adjust`, `ascent-override`.
- Self-host fonts (Google Fonts adds a request chain).

### The LCP element
- LCP is usually the hero heading or hero image. If it is an image: `loading="eager"`, `fetchpriority="high"`, correct `srcset`/`sizes`, WebP/AVIF. **LiteSpeed lazy load must exclude the first image** (`data-no-lazy="1"` or "Lazy Load Image Excludes").
- If it is text: do not run an entrance animation (word-by-word reveal, starting at opacity 0) on the first frame; LCP waits until the element is visible. If you need motion, start from the final state or move it with `transform` only.

### Images
- Every `<img>` has `width` and `height` (or CSS `aspect-ratio`). `wp_get_attachment_image()` adds them; do not hand-write `<img>`.
- Images below the fold use `loading="lazy"` and `decoding="async"`.
- Never ship images larger than displayed; use `srcset`/`sizes`. Decorative images become CSS backgrounds or SVG.

### JavaScript
- Every script is `defer` or `type="module"`; no synchronous scripts in `<head>`. `wp_enqueue_script( …, array( 'strategy' => 'defer', 'in_footer' => true ) )`.
- Ship `.min.js`. No single long task at startup (> 50 ms); split work with `requestIdleCallback`/`IntersectionObserver`.
- Avoid forced reflow: read first (`getBoundingClientRect`), then write; batch writes inside `requestAnimationFrame`.
- Do not load jQuery unless needed. Remove the emoji script.

### Animation
- Animate only `transform` and `opacity` (composited on the GPU). Do not animate `stroke-dashoffset`, `top/left`, `width/height`, `filter`, `box-shadow`; for SVG line drawing try `transform: scaleX()` + `clip-path`.
- Turn all motion off inside `@media (prefers-reduced-motion: reduce)`.
- Infinite marquees: keep DOM copies to a minimum; CSS `animation` with one copy plus an `aria-hidden` duplicate.

### DOM
- Under 1,500 elements, nesting depth under 32, at most 60 children per element.
- Put inline SVG icons into one `<svg><symbol>` sprite (`<use href="#i-x">`); never repeat the full icon.
- Build hidden menus/mega menus when they are opened on mobile.

### Server and caching
- Page cache on (LiteSpeed Cache). TTFB < 600 ms. Watch slow plugins in the Flux Speed report.
- gzip/brotli on. Static files cached for 1 year (Flux SEO writes the `.htaccess` rules), refreshed with a version query (`?ver=`).
- Load third-party scripts (chat, analytics, maps) on interaction or after `load`.

<!-- topic:accessibility -->
## 3. Accessibility

### Viewport and language
- `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`. **Never use `user-scalable=no` or `maximum-scale=1`**; it lowers the score directly. To stop iOS zooming into inputs, make input font size at least 16px.
- `<html <?php language_attributes(); ?>>`; add `lang` to blocks written in another language.

### Structure
- One `<header>`, `<nav aria-label>`, `<main id="main">`, `<footer>`. A "Skip to content" link is the first focusable element.
- Headings in order: one H1 per page, then H2 → H3. Never skip levels for looks; size with CSS.
- Content that looks like a list is `<ul>/<ol>` + `<li>`. **Never give a list a non-list role** (`<ol role="tablist">` is wrong).

### ARIA patterns (correct examples)
Tabs:
```html
<div role="tablist" aria-label="Setup steps">
  <button role="tab" id="step-1" aria-selected="true" aria-controls="scene-1">1. Download</button>
  <button role="tab" id="step-2" aria-selected="false" aria-controls="scene-2" tabindex="-1">2. Upload</button>
</div>
<div role="tabpanel" id="scene-1" aria-labelledby="step-1">…</div>
```
For a numbered steps look, either drop the tab roles (plain `<ol>` + `<li>` + `<button aria-pressed>`) or use `<li role="presentation">` inside `<ol role="tablist">`.
- `role="tab"` only inside `role="tablist"`; `role="option"` only inside `role="listbox"`; `role="menuitem"` only inside `role="menu"`.
- Broken ARIA is worse than no ARIA. Use the native element when there is one (button, a, details, dialog).
- `aria-controls`, `aria-labelledby`, `label for` must point to an existing `id`; ids are unique.

### Names and forms
- Every link and button has a name. Icon button: `<button aria-label="Open menu"><svg aria-hidden="true">…</svg></button>`.
- Links with the same text go to the same place ("Read more about projects" instead of ten "Read more" links to different targets).
- Every field has a `<label for>`; a placeholder is not a label. Error messages are linked with `aria-describedby` and announced with `aria-live="polite"`.
- Meaningful `alt` on images; decorative images `alt=""`. SVG icons `aria-hidden="true"`.

### Visuals
- Contrast: normal text ≥ 4.5:1, large text and icons ≥ 3:1 (in dark mode too).
- Touch targets ≥ 24×24 px (44×44 preferred) with spacing.
- Visible focus: `:focus-visible { outline: 2px solid; outline-offset: 2px }`; never `outline: none`.
- Do not convey information by colour alone; links inside text are underlined.
- Everything works with the keyboard; no focus traps; focus moves into a modal and back on close.

<!-- topic:seo -->
## 4. SEO

- `<title>`: ≤ 60 characters, "Page topic – Site name". The home title is set in Flux SEO › General.
- Meta description: 120–160 characters, unique per page, with a call to action.
- Exactly one `rel=canonical` per page (Flux SEO adds it; the theme or another plugin must not add a second one).
- Multilingual: Flux Lang outputs hreflang and `x-default`; language versions link to each other.
- Schema (JSON-LD): Organization/NGO + WebSite (Flux SEO), BreadcrumbList, Article/NewsArticle, Product, Event, FAQPage. Valid JSON matching the visible content.
- `robots.txt` generated by WordPress, with a `Sitemap:` line. Cart, checkout, my account, internal search and tag pages are noindex (Flux SEO settings).
- No duplicate content: never leave two posts with the same title (a "…-2" copy); delete and redirect.
- Short, meaningful, transliterated slugs (`/tree-donation/`).
- Internal links: 2–5 descriptive links to related pages in every post. No "click here", "read more".
- Images: meaningful file names, `alt` filled, share image 1200×630 (Flux SEO creates a JPEG copy).
- New or updated pages are pinged to Bing/Yandex via IndexNow; for Google add the sitemap in Search Console.
- Content: 300+ words with H2 subheadings, focus keyword in the first paragraph, an FAQ section (FAQPage) that answers search intent.

<!-- topic:best-practices -->
## 5. Best practices and security

- HTTPS everywhere; no mixed content (http:// images/scripts).
- `<!DOCTYPE html>` and `<meta charset="UTF-8">` in the first lines.
- No console errors, no deprecated APIs, no third-party cookies, no geolocation/notification prompts on load, paste allowed in password fields.
- Security headers (one click in Flux SEO › Site audit › Settings):
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (add `; preload` if every subdomain is HTTPS).
  - `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` (unless the site is embedded in other sites).
  - `Cross-Origin-Opener-Policy: same-origin-allow-popups` (payment/login popups keep working).
  - `Content-Security-Policy: object-src 'none'; base-uri 'self'; upgrade-insecure-requests`. A full `script-src` policy (nonce/strict-dynamic) is tried first with `Content-Security-Policy-Report-Only` on custom-theme sites with few plugins.
  - `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (Flux SEO sends them by default).
- Do not expose the WordPress version with `meta generator`.
- Images have the right aspect ratio and a resolution that matches the screen density.

<!-- topic:agentic -->
## 6. Agent readiness (Agentic browsing)

AI agents (browser agents, search assistants) understand a site through the accessibility tree, structured data and `llms.txt`.

- **llms.txt**: Flux SEO generates `/llms.txt` automatically. Format: first line `# Site name`, then `> short summary`, an optional paragraph, `- [Title](url): description` lists under `## Section` headings, and a final `## Optional` section. If an old hand-made `llms.txt` sits in the web root, delete it or keep it current.
- **A well-formed accessibility tree**: the ARIA rules above also decide the agent score (a broken `tablist` drops Agentic Browsing from 3/3 to 2/3).
- **Stable layout**: CLS < 0.1. Elements must not move while an agent clicks.
- **WebMCP**: browser agents can call forms as tools. Declarative form:
  ```html
  <form action="/" toolname="search_site" tooldescription="Searches the site">
    <input name="s" toolparamdescription="Words to search for">
  </form>
  ```
  Flux SEO adds this to the search form automatically. Give contact, donation and newsletter forms a short action-style `toolname` (snake_case) and description. For payment and personal-data forms, say in the description that the agent must not submit without the user's confirmation.
- **Semantic HTML**: buttons are `<button>`, links are `<a href>`; no `div onclick`. The visible text matches the accessible name.
- **Structured data**: Organization, product, event and FAQ schema let agents answer correctly.
- **robots.txt**: keep GPTBot, ClaudeBot, Google-Extended, PerplexityBot and similar crawlers allowed unless you block them on purpose.
- **MCP**: site management uses the Flux SEO MCP server (with a key); keys never go into public files.

<!-- topic:wordpress -->
## 7. WordPress and FluxWP rules

- Theme: block theme, palette/typography in `theme.json`; anything organisation- or brand-specific lives in a preset plugin (flx-preset-*), not in the theme.
- Styles and scripts via `wp_enqueue_*`; per-block styles with `wp_enqueue_block_style`; never print `<link>` into `<head>` by hand.
- Patterns (`patterns/`): strings through `__()` / `esc_html__()`, Turkish default + English translation; no hard-coded text. Images with `width/height`.
- Dates and "… ago" texts with `flx_date()` / `flx_time_ago()`.
- Settings live in the admin panel, not wp-config; presets write them through `flx_settings_schema` fields (Flux SEO fields: `flxseo.*`).
- All AI code goes through the shared Flux AI & MCP plugin.
- Reuse existing patterns for new pages; new CSS goes only into that block's stylesheet.
- Accessible component library: tabs, accordion (`<details>`), modal (`<dialog>`), slider (with buttons, no autoplay or pausable).

<!-- topic:checklist -->
## 8. Pre-launch checklist

- [ ] Flux SEO › Site audit: no critical or high findings.
- [ ] PageSpeed mobile and desktop: Performance ≥ 90 (target 100), Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing full.
- [ ] No `user-scalable=no` / `maximum-scale` in the viewport.
- [ ] ARIA roles correct (tablist › tab, listbox › option); `<li>` only inside lists.
- [ ] No two posts with the same title; "-2" copies deleted/redirected.
- [ ] First-screen image not lazy, `fetchpriority="high"`; width/height and alt on every image.
- [ ] Critical CSS inline, the rest deferred; JS `defer`; fonts preload + swap.
- [ ] Animations use transform/opacity only; off with reduced motion.
- [ ] DOM < 1,500 elements; icons in a sprite.
- [ ] Title ≤ 60, description 120–160 characters; one canonical; valid schema; hreflang (if multilingual).
- [ ] `/llms.txt` loads and is current; the search form has a WebMCP `toolname`.
- [ ] HTTPS, HSTS, X-Frame-Options/frame-ancestors, COOP, basic CSP on (unless iframe embedding is needed).
- [ ] Contrast and visible focus in dark mode too.
- [ ] LiteSpeed › Purge All, then run the audit again.
