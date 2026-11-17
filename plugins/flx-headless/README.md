# Flux Headless

Serves this WordPress to one or more headless front ends (Next.js, Nuxt, Astro or any framework) over
GraphQL. WordPress stays the CMS and the admin; the front ends render the site.

- `flxRoute(path)`: what an address shows: kind, HTTP status, redirects (trailing slash, old slugs),
  language, the WordPress object, the same SEO head Flux SEO prints (canonical, Open Graph, JSON-LD) with
  front-end addresses, hreflang versions and cache tags. Signed previews of drafts.
- `flxSite`: name, logo, languages, menus (menu locations and header/footer navigation blocks), palette,
  contact details, currencies, legal pages and public captcha settings.
- Many front ends from one admin: each with its own address, languages, revalidate webhook and secret.
  Their origins are allowed by CORS automatically.
- HMAC-signed revalidate webhook when content changes, with a Test button and a delivery log.
- WordPress pages can stay, step aside for search engines (noindex + canonical) or redirect to the front end.
  Sitemaps list front-end addresses.

Integration guide: [docs/flx-headless/README.md](../../docs/flx-headless/README.md). Next.js example:
`apps/headless-next`.

Requires PHP 8.1, WordPress 6.5 and Flux GraphQL.

Türkçe: [README.tr.md](README.tr.md)

## Maintainers

Ahmet Yılmaz (@sharkpaw)
