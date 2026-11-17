# Flux Headless: integration guide

Flux Headless lets one WordPress serve one or more headless front ends. They can use Next.js, Nuxt,
Astro, SvelteKit, Remix or a plain server. WordPress stays the CMS and admin. The front end asks
GraphQL two things:

| Query | What it answers |
|---|---|
| `flxRoute(path)` | What an address shows. It returns the kind (page, post, archive, search, 404 or redirect), the HTTP status, the WordPress object, a ready SEO `<head>`, language versions and cache tags. |
| `flxSite` | The frame drawn around every page. It returns the name, logo, languages, menus, palette, contact details, currencies, legal pages and public captcha settings. |

When content changes, WordPress calls the front end's **revalidate webhook** with an HMAC signature.
The call lists the cache tags and paths that changed, so the front end shows the change within
seconds without rebuilding the site.

A working Next.js App Router example lives in `apps/headless-next`. This guide is framework
independent; the examples are at the end.

---

## 1. Set up WordPress

1. Install **Flux GraphQL** and **Flux Headless**. Flux SEO and Flux Lang are optional but
   recommended: they give the SEO head and the languages.
2. Move WordPress to its own address, such as `cms.example.com`. The front end takes the public
   address, such as `www.example.com`.
3. Go to **FluxWP › Headless › Add front end** and fill in the fields:
   - **Name** and **Address**, for example `https://www.example.com`. Use `http://` only for
     `localhost`.
   - **Languages**: leave this empty to serve every language, or pick some to run one front end per
     language or domain.
   - **Webhook address**: for example `https://www.example.com/api/revalidate`.
   - **Secret**: press *Generate*, then copy it into the front end as `FLUX_REVALIDATE_SECRET`. It is
     stored encrypted and never shown again.
   - **Preview path** (optional): for example `/api/preview`.
4. Press **Test webhook**. The log at the bottom shows the HTTP status your front end answered.
5. Once the front end is live, set **WordPress pages** to either of these:
   - *Show, but hide from search*: noindex plus a canonical link to the front end.
   - *Send visitors to the front end*: a 301 redirect. Cart, checkout, account, previews and editors
     always stay on WordPress.

You can add up to 20 front ends, each with its own address, languages, webhook and secret. Settings
are stored per site, so a multisite network can serve different front ends from each site.

### CORS

Every enabled front end's origin is added to Flux GraphQL's CORS list automatically. Add other
origins, such as a staging site, in **Flux GraphQL › Settings**. Server-side fetching (SSR, ISR, SSG)
needs no CORS.

---

## 2. Talk to GraphQL

The endpoint is `https://cms.example.com/graphql`.

### Persisted queries

Flux GraphQL can run in **strict** mode, where only registered documents run. This is recommended for
production. The front end then sends a `queryId` instead of the document text. The id is the
SHA-256 of the document. Every document and its id are in
`wp-content/plugins/flx-headless/graphql/persisted-queries.json`. Copy that file into your front end,
or read the id for `FlxRoute` and `FlxSite` from it.

```
GET /graphql?queryId=<sha256>&variables={"path":"/about/"}
```

- **Reads should be GETs.** A visitor's persisted GET is cacheable. It gets
  `Cache-Control: public, max-age=<cache_ttl>` and LiteSpeed tags, so a CDN in front of WordPress
  can cache it too.
- In **open** mode you may also POST your own documents, such as one query that asks for
  `flxRoute { node { ... on Page { flxBlocks { html } } } }`.
- To run your own documents in strict mode, register them from a small plugin:

```php
add_filter( 'flx_gql_persisted_manifests', function ( $m ) {
	$m['my-front'] = __DIR__ . '/persisted-queries.json'; // { "<sha256 of doc>": "<doc>" }
	return $m;
} );
```

### Which front end am I?

`flxRoute` and `flxSite` take an optional `frontend: ID`. Without it, WordPress uses the request's
`Origin`, or the default front end when the request has none. Server-side calls have no `Origin`, so
pass `frontend` when more than one front end exists. The id is shown in the panel. Every address in
the answer points at that front end: canonical, Open Graph, menu links, alternates and JSON-LD.

---

## 3. Render a page: `flxRoute`

```graphql
query FlxRoute($path: String!, $lang: String, $frontend: ID, $previewId: Int) {
  flxRoute(path: $path, lang: $lang, frontend: $frontend, previewId: $previewId) {
    kind status redirect lang title page databaseId postType taxonomy
    head { title description canonical robots noindex image locale meta { name property content } jsonLd }
    alternates { lang hreflang url path }
    cacheTags
    node { __typename uri ... on NodeWithTitle { title } ... on NodeWithContentEditor { content } }
  }
}
```

`path` is the visitor's path with its query string, such as `/about/`, `/en/blog/page/2/` or
`/?s=word`. A full front-end address also works. An address on any other domain answers
`NOT_FOUND`.

| `kind` | `status` | What to do |
|---|---|---|
| `FRONT_PAGE`, `HOME`, `SINGULAR`, `TERM`, `POST_TYPE`, `AUTHOR`, `DATE`, `SEARCH` | 200 | Render it. `node` is the WordPress object for the first five kinds. |
| `REDIRECT` | 301 | Redirect to `redirect`, which is a front-end path. This covers trailing slashes, old slugs and the canonical address. |
| `NOT_FOUND` | 404 | Render your 404 page with status 404. `head.noindex` is true. |

- **SEO head.** Print `head.title`, `<meta name="description">`, `<link rel="canonical">`,
  `<meta name="robots" content="robots.join(',')">`, every `meta` entry (Open Graph, Twitter,
  verification) and each `jsonLd` string in `<script type="application/ld+json">`. Add the
  `alternates` as `<link rel="alternate" hreflang>`. Use `head.locale` for `<html lang>`. The values
  are the same ones Flux SEO prints on the theme, with front-end addresses.
- **Body.** Use `node.content` for the classic HTML. The `FlxHeadlessBlocks` operation, which needs Flux
  Blocks, gives `flxBlocks { html headHtml footerHtml }` and `flxBlocksSiteStyles` to show the page
  exactly as the theme does.
- **Listings.** `FlxHeadlessPosts` (`posts(first, after, categoryId, tagId, authorId, search)`) gives
  the posts of an archive or search page. Use `databaseId` from the route.
- **Languages.** With Flux Lang, `/en/about/` gives `lang: "en"`, and the path is matched without the
  prefix. A front end that serves one language on its own domain passes `lang` and uses unprefixed
  paths.

---

## 4. Draw the frame: `flxSite`

`flxSite(lang, frontend)` returns the following:

- `name`, `description`, `url` (the front end), `cmsUrl` and `graphqlUrl`.
- `languages` for the language picker. Each has `code`, `name`, `hreflang`, `path`, `url` and
  `isDefault`.
- `logo` and `icon`.
- `menus`, a flat list where each item points at its `parentId`. They come from the menu locations
  and from the navigation blocks of the header and footer. `location` is `primary`, `header`,
  `footer` and so on. Site links carry a front-end `path` for your router.
- `palette`, `contact`, `currencies` (with WooCommerce), `legalPages`, `captcha` (Flux reCAPTCHA) and
  `social`.
- `sitemapUrl` and `searchPath`.
- `parts(names: ["header","footer"])`: rendered theme template parts, for front ends that reuse the
  theme markup.

Cache it under its `cacheTags`.

---

## 5. Cache and revalidate

Every answer carries `cacheTags`:

| Tag | Changes when |
|---|---|
| `flx-headless:post:<id>` | That post or page is published, updated, trashed or deleted. |
| `flx-headless:term:<id>` | That term changes. |
| `flx-headless:author:<id>` | That author changes. |
| `flx-headless:archive:<post type>` | A post of that type changes. |
| `flx-headless:menus` | A menu or navigation block changes. |
| `flx-headless:site` | The site name, logo, front page or settings change. |
| `flx-headless:routes` | The permalink structure changes. |
| `flx-headless:all` | The panel's *Refresh all* button. Purge everything. |

Tags from other FluxWP packages, such as `flx-blocks:post:<id>`, are forwarded too.

### The webhook call

```
POST https://www.example.com/api/revalidate
Content-Type: application/json
X-Flx-Event: purge            # purge | all | test
X-Flx-Signature: t=1794920896,v1=5f3c…   # hex HMAC-SHA256(secret, "<t>.<raw body>")

{"event":"purge","site":"https://cms.example.com","frontend":"main",
 "tags":["flx-headless:post:42","flx-headless:archive:post"],"paths":["/hello-world/"],"time":1794920896}
```

The front end should do four things:

1. Read the **raw** body, before any JSON parsing.
2. Compute `HMAC-SHA256(secret, t + "." + body)` and compare it with `v1` in constant time.
3. Reject the call when `t` is more than 5 minutes away from now.
4. Purge each tag. Paths are given too, for frameworks that revalidate by path. Answer with a 2xx.

Calls are queued during a request and sent once, at its end. Content saves do not wait for the
front end. The panel's **Test webhook** waits and shows the status. The last 30 calls are in the log.

---

## 6. Preview

When a front end has a **Preview path**, the editor's *Preview* button opens it with this link:

```
https://www.example.com/api/preview?id=42&type=post&exp=1794921496&sig=<hex>
sig = HMAC-SHA256(secret, "preview|<id>|<exp>")   # valid for 10 minutes
```

The front end checks `sig` and `exp`, turns on its draft mode, and then fetches the page with
`flxRoute(path: "/", previewId: 42)`. That fetch must be authenticated as a user who can edit the
post. Create an **application password** for an editor in *Users › Profile* and send
`Authorization: Basic base64(user:app-password)` from the server only. Preview answers are never
cached and are always `noindex`.

---

## 7. Sitemap and robots

`flxSite.sitemapUrl` is `https://cms.example.com/wp-sitemap.xml?flxhl=<id>`. Its `<loc>` entries
already point at the front end. Proxy `/sitemap.xml` and `/wp-sitemap*.xml` on the front end to it,
keeping the query string. In `robots.txt`, list `https://www.example.com/sitemap.xml`.

---

## 8. Examples

### Plain fetch (any server)

```ts
const GQL = process.env.FLUX_GRAPHQL_URL!;        // https://cms.example.com/graphql
const FRONTEND = process.env.FLUX_FRONTEND_ID;     // optional with one front end
import ids from './persisted-queries.json';       // copied from the plugin
const idOf = (name: string) => Object.keys(ids).find((k) => ids[k].includes(`query ${name}(`))!;

export async function gql<T>(name: string, variables: Record<string, unknown>, init?: RequestInit): Promise<T> {
  const url = new URL(GQL);
  url.searchParams.set('queryId', idOf(name));
  url.searchParams.set('variables', JSON.stringify({ frontend: FRONTEND, ...variables }));
  const res = await fetch(url, init);
  const json = await res.json();
  if (json.errors?.length) throw new Error(json.errors[0].message);
  return json.data as T;
}
```

```ts
// Webhook check (Node / Bun / Deno / edge, Web Crypto).
export async function verify(body: string, header: string | null, secret: string): Promise<boolean> {
  const m = /^t=(\d+),v1=([0-9a-f]{64})$/.exec(header ?? '');
  if (!m || Math.abs(Date.now() / 1000 - Number(m[1])) > 300) return false;
  const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const sig = new Uint8Array(await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(`${m[1]}.${body}`)));
  const hex = [...sig].map((b) => b.toString(16).padStart(2, '0')).join('');
  let diff = hex.length ^ m[2].length;
  for (let i = 0; i < hex.length; i++) diff |= hex.charCodeAt(i) ^ m[2].charCodeAt(i);
  return diff === 0;
}
```

### Next.js (App Router)

```ts
// app/[[...slug]]/page.tsx: ISR with tags
const data = await gql('FlxRoute', { path }, { next: { tags: ['flx-headless:all'], revalidate: 3600 } });
// Then call revalidateTag for each of route.cacheTags (unstable_cacheTag / fetch tags).
if (route.kind === 'REDIRECT') permanentRedirect(route.redirect);
if (route.kind === 'NOT_FOUND') notFound();

// app/api/revalidate/route.ts
export async function POST(req: Request) {
  const body = await req.text();
  if (!(await verify(body, req.headers.get('x-flx-signature'), process.env.FLUX_REVALIDATE_SECRET!))) return new Response('bad signature', { status: 401 });
  const { tags } = JSON.parse(body);
  tags.forEach((t: string) => revalidateTag(t));
  return Response.json({ ok: true });
}
```

The full project, with `generateMetadata` from `head`, draft mode preview, the sitemap proxy and
menus, is in `apps/headless-next`.

### Nuxt 3

```ts
// server/api/revalidate.post.ts
export default defineEventHandler(async (e) => {
  const body = (await readRawBody(e)) ?? '';
  if (!(await verify(body, getHeader(e, 'x-flx-signature') ?? null, useRuntimeConfig().fluxSecret))) throw createError({ statusCode: 401 });
  const { paths } = JSON.parse(body);
  const storage = useStorage('cache');          // Nitro route cache
  for (const p of paths) await storage.removeItem(`nitro:routes:_:${p.replace(/\//g, ':')}`);
  return { ok: true };
});
// pages/[...slug].vue: useAsyncData(() => gql('FlxRoute', { path: useRoute().fullPath }))
// useHead({ title: head.title, meta: head.meta, link: [{ rel: 'canonical', href: head.canonical }] })
```

### Astro (SSR)

```astro
---
// src/pages/[...slug].astro
const { flxRoute: r } = await gql('FlxRoute', { path: Astro.url.pathname + Astro.url.search });
if (r.kind === 'REDIRECT') return Astro.redirect(r.redirect, 301);
if (r.kind === 'NOT_FOUND') Astro.response.status = 404;
Astro.response.headers.set('Cache-Tag', r.cacheTags.join(','));   // e.g. Cloudflare / Netlify tag purge
---
<html lang={r.head.locale}><head><title>{r.head.title}</title>
  {r.head.meta.map((m) => <meta name={m.name || undefined} property={m.property || undefined} content={m.content} />)}
  {r.head.jsonLd.map((j) => <script type="application/ld+json" set:html={j} />)}
</head><body set:html={r.node?.content} /></html>
```

The webhook endpoint then calls your CDN's purge-by-tag API with `tags`.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| `PERSISTED_QUERY_NOT_FOUND` | The `queryId` comes from an older plugin version. Copy `persisted-queries.json` again. |
| Browser CORS error | Is the front end enabled, and does its address match the browser origin exactly (scheme and port included)? |
| Webhook 401 in the log | The front end's secret differs. Generate a new one and copy it. Check that the raw body is used, not re-serialised JSON. |
| Webhook `0`, "could not connect" | WordPress cannot reach the front end. A firewall or `localhost` from a remote server is a common cause. |
| Canonical points at WordPress | Pass `frontend`, or make the front end the default. |
| Draft shows 404 | The preview fetch is not authenticated (application password), or the user cannot edit the post. |
