# Flux Wiki

A developer wiki inside WordPress at `/wiki`: Markdown pages grouped into sections, a page tree and table of contents,
detailed instant search (Ctrl/⌘ + K) and SEO-friendly output. Built for humans and LLMs alike.

- **Markdown first.** Pages are written in Markdown (GitHub flavour) with a Write / Preview / Side-by-side editor,
  `[[wiki links]]`, `> [!NOTE]` callouts, task lists and copyable code blocks. Rendered with Parsedown (MIT).
- **Search.** Ranked by title, headings and text, Turkish characters folded (ı = i, ş = s), light Turkish stemming,
  results jump to the best matching heading. Works without JS at `/wiki/?q=`. REST: `GET /wp-json/flx-wiki/v1/search?q=`.
- **LLM friendly.** Every page is available as Markdown at `/wiki/<path>.md` (also via `Accept: text/markdown`),
  plus `/wiki/llms.txt`, `/wiki/llms-full.txt` and `/wiki/index.md`. "Copy Markdown" and "Ask AI" (ChatGPT / Claude / Google AI /
  Perplexity) menus on every page. Adds a Wiki section to Flux SEO's `/llms.txt`.
- **AI powered.** With Flux AI, "Ask the wiki" answers questions only from wiki pages and lists its sources
  (logged-in users by default, hourly limit). MCP tools on the Flux AI server: `flxwk_search`, `flxwk_get_page`,
  `flxwk_list_pages`, `flxwk_save_page`; resources `flxwiki://llms.txt`, `flxwiki://llms-full.txt`; prompt `flxwk_build_plugin`.
- **SEO.** TechArticle + BreadcrumbList JSON-LD (merged into Flux SEO's graph when present), meta description,
  canonical, sitemap via core, search result pages are `noindex`.
- Uses the active theme's header, footer and colours (block and classic themes), follows FluxWP dark mode.
- Ships with guides: writing plugins and themes, settings schema, translation, Flux Admin, system checks,
  AI-enabled plugins, MCP tools, releases.

Developer notes: [docs/flx-wiki/API.md](../../docs/flx-wiki/API.md).

Türkçe: [README.tr.md](README.tr.md)

## Ask the wiki controls

Questions search all active content languages, including spelling correction. Weak matches can use one short query translation through Flux AI. Only published, password-free Wiki pages are sent as context; no tools are executed. Answers cite the supplied sources, with sanitized output and restricted clickable links. These controls reduce risk; they do not guarantee that a model answer is correct.

The default allowance is 20 questions per clock hour per user or salted IP identity, with one request in progress per visitor. Administrators are exempt from the hourly allowance. Flux AI spending limits still apply. Access can be disabled, limited to signed-in users (default), or opened to everyone in Wiki settings.
