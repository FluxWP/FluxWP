import type { NextConfig } from 'next';
import { FRONTEND_ID, GRAPHQL_URL } from './lib/env';

const cms = GRAPHQL_URL ? new URL(GRAPHQL_URL) : null;

const config: NextConfig = {
  // 'use cache' + cacheTag: every page is cached under the tags WordPress gives (flxRoute.cacheTags)
  // and refreshed when the signed revalidate webhook names one of them.
  // WordPress addresses end with a slash (/about/); keep them identical so canonical and links match.
  trailingSlash: true,
  // The webhook and preview routes are called without a slash; proxy.ts adds it to pages only.
  skipTrailingSlashRedirect: true,
  cacheComponents: true,
  cacheLife: {
    // Safety net only; the webhook refreshes changed pages within seconds.
    wordpress: { stale: 300, revalidate: 3600, expire: 86400 },
  },
  images: cms ? { remotePatterns: [{ protocol: cms.protocol.replace(':', '') as 'http' | 'https', hostname: cms.hostname }] } : undefined,
  async rewrites() {
    if (!cms) return [];
    const origin = cms.origin;
    const id = FRONTEND_ID ?? '';
    const q = id ? `?flxhl=${encodeURIComponent(id)}` : '';
    // Sitemaps: WordPress lists front-end addresses when ?flxhl=<id> is set (Flux Headless).
    return [
      { source: '/sitemap.xml', destination: `${origin}/wp-sitemap.xml${q}` },
      { source: '/wp-sitemap.xsl', destination: `${origin}/wp-sitemap.xsl` },
      { source: '/wp-sitemap-:rest.xml', destination: `${origin}/wp-sitemap-:rest.xml${q}` },
    ];
  },
};

export default config;
