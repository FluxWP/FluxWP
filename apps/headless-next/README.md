# Flux Headless: Next.js example

A Next.js 16 App Router front end for a WordPress site served by **Flux Headless**. It has two ways
to draw pages, chosen with `FLUX_RENDER`:

- **`wordpress` (default): looks exactly like the WordPress site.** Each page is the one WordPress
  renders with its theme, template parts, blocks, styles and scripts. Flux Headless rewrites its links
  to this site (whole-page mode, `?flxhl_page=<front end id>`), Next caches it under the tags WordPress
  sends, and the revalidate webhook refreshes it. Theme files, uploads, REST and AJAX are proxied, so
  fonts, search and forms work same-origin. Cart, checkout, account and visitors with a WordPress
  session (signed in, a cart, add-to-cart links) are passed through live with their cookies, never
  cached. Files: `lib/page.ts`, `app/wp-page/[[...path]]/route.ts`, `proxy.ts`, `next.config.ts`.
- **`react`: your own design from GraphQL data.** The pieces below build every page in React. Use it
  as a starting point when the front end should look different from WordPress.

The `react` mode shows:

| Piece | File |
|---|---|
| Every WordPress address renders through one catch-all page with `flxRoute`. It handles 301s, 404s, pages, posts, archives and languages. | `app/[[...slug]]/page.tsx` |
| SEO comes from WordPress: title, description, canonical, robots, Open Graph, hreflang and JSON-LD, mapped into `generateMetadata`. | same |
| The site frame uses `flxSite`: logo, menus from menu locations or navigation blocks, language links, palette as CSS variables, and footer contact and legal links. | `app/layout.tsx`, `components/menu.tsx` |
| The look follows the FluxWP theme: its fonts and palette, a dark footer with one column per footer menu (at the bottom of the window on short pages, folding into an accordion on phones), and sub-menus that drop down on desktop and open on tap on phones, without client JavaScript. A front page built from blocks (hero, columns, products) is drawn full width. | `app/globals.css` |
| Cache Components ISR. Each answer is cached with `'use cache'` under the tags WordPress returns (`cacheTag(...route.cacheTags)`). | `lib/flux.ts` |
| A signed revalidate webhook. It checks the HMAC, then refreshes those tags and paths. Changes show within seconds. | `app/api/revalidate/route.ts`, `lib/verify.ts` |
| Preview. The editor's *Preview* button opens a signed link, and Draft Mode then fetches the draft with a request signed by the connection key. | `app/api/preview/route.ts`, `lib/flux.ts` |
| Two settings: `FLUX_WP_URL` and `FLUX_KEY`. The separate older variables still work. | `lib/env.ts` |
| Sitemap proxy. `/sitemap.xml` comes from WordPress with front-end addresses. | `next.config.ts` |
| Trailing slashes match WordPress. | `next.config.ts`, `proxy.ts` |

Requests use persisted query ids from `plugins/flx-headless/graphql/persisted-queries.json`, so the
example also works when Flux GraphQL runs only registered queries. Outside this monorepo, copy that
file and change the import in `lib/flux.ts`.

## Run it

To see it as the screenshots show it, set WordPress up with the FluxWP theme and its demo store
preset (*Theme settings › Presets*), which adds the products, pages and a post.

1. In WordPress, open **FluxWP › Headless › Add front end** and fill in **Name** and **Address**
   (`http://localhost:3000`). The webhook (`/api/revalidate`), preview path (`/api/preview`) and
   secret are filled in under *Advanced*.
2. Save. WordPress shows the connection once. Put its two lines in `.env.local` (or your host's
   environment variables):
   - `FLUX_WP_URL`: the WordPress address.
   - `FLUX_KEY`: the connection key. It also signs previews, so no application password is needed.
3. Run these commands:

```sh
cd apps/headless-next
bun install        # or npm install: this app is not part of the monorepo workspace
bun run dev        # http://localhost:3000
bun run build      # production build (WordPress must be reachable)
bun run start      # PORT sets the port, default 3000
```

**Hosting (Node.js, e.g. Hostinger).** Use the repository and set the root directory to
`apps/headless-next`, build command `npm run build`, output `.next`, Node 22, and add the two
environment variables (plus `FLUX_RENDER=react` for the React design). The WordPress address is
written into the proxy rules at build time, so changing `FLUX_WP_URL` needs a new build. The app stays outside the monorepo workspace on purpose, so a plain
`npm install` works there. It still needs the whole repository, because it reads the
`persisted-queries.json` manifest from `plugins/flx-headless/graphql/`.

4. In WordPress, press **Test webhook**. The log should show `HTTP 200`. Edit a post, reload its page
   on the front end, and the change is there.

Before going live, set **WordPress pages** to *Show, but hide from search* or *Send visitors to the
front end*.

## Notes

- **Body HTML.** The page prints `node.content`, which is the HTML WordPress renders. To show block
  designs exactly as the theme does, add the `FlxHeadlessBlocks` operation (Flux Blocks). It returns
  `flxBlocks { html headHtml }`.
- **Listings.** Listings show the latest 12 posts. Cursor paging (`FlxHeadlessPosts`, `after`) is
  left to your design.
- **Search.** Search is not wired up. `flxRoute(path: "/?s=word")` returns the search page head. Read
  the words from `searchParams` inside `<Suspense>`.
- **404 status.** Partial Prefetching is left off on purpose. The first visit to an unknown address
  then waits for WordPress, so redirects and 404s get real HTTP status codes.

The full integration guide is in `docs/flx-headless/README.md`.
