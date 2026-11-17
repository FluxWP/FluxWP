import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import { RENDER } from '@/lib/env';
import { draftMode } from 'next/headers';
import { getPosts, getPreview, getRoute } from '@/lib/flux';
import type { Route } from '@/lib/types';

type Props = { params: Promise<{ slug?: string[] }> };

/** /__preview/<id> is where the preview route sends the editor (Draft Mode on). */
const PREVIEW = '__preview';

function pathOf(slug: string[] | undefined): string {
  return slug?.length ? `/${slug.map(encodeURIComponent).join('/')}/` : '/';
}

async function load(props: Props): Promise<Route> {
  const { slug } = await props.params;
  if (slug?.[0] === PREVIEW) {
    const { isEnabled } = await draftMode();
    const id = Number(slug[1]);
    if (!isEnabled || !id) notFound();
    return getPreview(id);
  }
  return getRoute(pathOf(slug));
}

// The front page is built ahead; every other address renders on its first visit and is then cached
// (Cache Components ISR) until the webhook names one of its tags.
export function generateStaticParams() {
  // Whole-page mode never reaches this page (proxy.ts sends pages to app/wp-page); nothing to prerender.
  return RENDER === 'react' ? [{ slug: [] }] : [{ slug: ['__react'] }];
}

export async function generateMetadata(props: Props): Promise<Metadata> {
  if (RENDER !== 'react') return {};
  const r = await load(props);
  const h = r.head;
  const meta = (key: 'name' | 'property', v: string) => h.meta.find((m) => m[key] === v)?.content;
  const other: Record<string, string> = {};
  for (const m of h.meta) {
    const k = m.name || m.property;
    if (k && !k.startsWith('og:') && !k.startsWith('twitter:') && k !== 'description' && k !== 'robots') other[k] = m.content;
  }
  return {
    title: h.title,
    description: h.description || undefined,
    robots: h.robots.join(', ') || undefined,
    alternates: {
      canonical: h.canonical || undefined,
      languages: Object.fromEntries(r.alternates.map((a) => [a.hreflang, a.url])),
    },
    openGraph: {
      title: meta('property', 'og:title') ?? h.title,
      description: meta('property', 'og:description') ?? (h.description || undefined),
      url: h.canonical || undefined,
      siteName: meta('property', 'og:site_name'),
      type: (meta('property', 'og:type') as 'website' | 'article' | undefined) ?? 'website',
      locale: meta('property', 'og:locale'),
      images: h.image ? [h.image] : undefined,
    },
    twitter: { card: (meta('name', 'twitter:card') as 'summary_large_image' | 'summary' | undefined) ?? 'summary_large_image' },
    other,
  };
}

export default async function Page(props: Props) {
  if (RENDER !== 'react') notFound();
  const r = await load(props);
  if (r.kind === 'REDIRECT' && r.redirect) permanentRedirect(r.redirect);
  if (r.kind === 'NOT_FOUND') notFound();

  const listing = (r.kind === 'FRONT_PAGE' && !r.node?.content) || r.kind === 'HOME' || r.kind === 'TERM' || r.kind === 'AUTHOR' || (r.kind === 'POST_TYPE' && r.postType === 'post');
  const posts = listing
    ? await getPosts({
        categoryId: r.taxonomy === 'category' ? (r.databaseId ?? undefined) : undefined,
        authorId: r.kind === 'AUTHOR' ? (r.databaseId ?? undefined) : undefined,
      })
    : [];
  const n = r.node;

  const front = r.kind === 'FRONT_PAGE' && !!n?.content;

  return (
    <>
      {r.head.jsonLd.map((j, i) => (
        <script key={i} type="application/ld+json" dangerouslySetInnerHTML={{ __html: j.replace(/</g, '\\u003c') }} />
      ))}
      {front ? (
        // The front page is built from blocks (hero, columns …): full width, as the theme lays it out.
        <div className="wp-content front" dangerouslySetInnerHTML={{ __html: n.content ?? '' }} />
      ) : (
        <div className="wrap page">
          <article className="entry">
            {r.kind !== 'FRONT_PAGE' && <h1>{n?.title ?? n?.name ?? r.title}</h1>}
            {n?.date && r.kind === 'SINGULAR' && n.contentTypeName === 'post' && (
              <p className="meta">
                {new Date(n.date).toLocaleDateString(r.head.locale || undefined, { dateStyle: 'long' })}
                {n.author?.node.name ? ` · ${n.author.node.name}` : ''}
              </p>
            )}
            {n?.featuredImage && r.kind === 'SINGULAR' && (
              // eslint-disable-next-line @next/next/no-img-element
              <img className="hero" src={n.featuredImage.node.sourceUrl} alt={n.featuredImage.node.altText} />
            )}
            {n?.description && <p className="lead">{n.description}</p>}
            {n?.content && <div className="wp-content" dangerouslySetInnerHTML={{ __html: n.content }} />}
          </article>
          {posts.length > 0 && (
            <ul className="cards">
              {posts.map((p) => (
                <li key={p.databaseId}>
                  <Link href={p.uri} prefetch>
                    {p.featuredImage && (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={p.featuredImage.node.sourceUrl} alt={p.featuredImage.node.altText} loading="lazy" />
                    )}
                    <h2>{p.title}</h2>
                  </Link>
                  {p.excerpt && <div className="excerpt" dangerouslySetInnerHTML={{ __html: p.excerpt }} />}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </>
  );
}
