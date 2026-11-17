# Flux Headless: Next.js example

A Next.js 16 App Router front end for a WordPress site served by **Flux Headless**. It is meant as a
starting point: copy the folder, point it at your WordPress, then add your own design.

What it shows:

| Piece | File |
|---|---|
| Every WordPress address renders through one catch-all page with `flxRoute`. It handles 301s, 404s, pages, posts, archives and languages. | `app/[[...slug]]/page.tsx` |
| SEO comes from WordPress: title, description, canonical, robots, Open Graph, hreflang and JSON-LD, mapped into `generateMetadata`. | same |
| The site frame uses `flxSite`: logo, menus from menu locations or navigation blocks, language links, palette as CSS variables, and footer contact and legal links. | `app/layout.tsx`, `components/menu.tsx` |
| Cache Components ISR. Each answer is cached with `'use cache'` under the tags WordPress returns (`cacheTag(...route.cacheTags)`). | `lib/flux.ts` |
| A signed revalidate webhook. It checks the HMAC, then refreshes those tags and paths. Changes show within seconds. | `app/api/revalidate/route.ts`, `lib/verify.ts` |
| Preview. The editor's *Preview* button opens a signed link, and Draft Mode then shows the draft through an application password. | `app/api/preview/route.ts` |
| Sitemap proxy. `/sitemap.xml` comes from WordPress with front-end addresses. | `next.config.ts` |
| Trailing slashes match WordPress. | `next.config.ts`, `proxy.ts` |

Requests use persisted query ids from `plugins/flx-headless/graphql/persisted-queries.json`, so the
example also works when Flux GraphQL runs only registered queries. Outside this monorepo, copy that
file and change the import in `lib/flux.ts`.

## Run it

1. In WordPress, open **FluxWP › Headless › Add front end** and fill in:
   - **Address**: `http://localhost:3000`.
   - **Webhook**: `http://localhost:3000/api/revalidate`.
   - **Secret**: press *Generate*.
   - **Preview path**: `/api/preview`.
2. Copy `.env.example` to `.env.local` and fill it in:
   - `FLUX_GRAPHQL_URL`.
   - `FLUX_FRONTEND_ID`, shown on the front end's card.
   - `FLUX_REVALIDATE_SECRET`.
   - For previews, also `FLUX_PREVIEW_USER` and `FLUX_PREVIEW_APP_PASSWORD`. Create the application password in *Users › Profile* for a user who can edit posts.
3. Run these commands:

```sh
bun install
bun run --filter @fluxwp/headless-next dev            # http://localhost:3000
bun run --filter @fluxwp/headless-next build:site     # production build (WordPress must be reachable)
bun run --filter @fluxwp/headless-next start
```

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
