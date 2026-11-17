import type { NextConfig } from 'next';
import { FRONTEND_ID, GRAPHQL_URL, WP_URL } from './lib/env';

const cms = GRAPHQL_URL ? new URL(GRAPHQL_URL) : null;
const wp = WP_URL || cms?.origin || '';

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
    if (!wp) return [];
    const origin = wp;
    const id = FRONTEND_ID ?? '';
    const q = id ? `?flxhl=${encodeURIComponent(id)}` : '';
    // Sitemaps: WordPress lists front-end addresses when ?flxhl=<id> is set (Flux Headless).
    return [
      { source: '/sitemap.xml', destination: `${origin}/wp-sitemap.xml${q}` },
      { source: '/wp-sitemap.xsl', destination: `${origin}/wp-sitemap.xsl` },
      { source: '/wp-sitemap-:rest.xml', destination: `${origin}/wp-sitemap-:rest.xml${q}` },
      // Theme and plugin files, uploads, REST and AJAX of WordPress pages (whole-page mode) stay on this origin,
      // so fonts and requests need no CORS.
      { source: '/wp-content/:path*', destination: `${origin}/wp-content/:path*` },
      { source: '/wp-includes/:path*', destination: `${origin}/wp-includes/:path*` },
      { source: '/wp-json/:path*', destination: `${origin}/wp-json/:path*` },
      { source: '/wp-admin/admin-ajax.php', destination: `${origin}/wp-admin/admin-ajax.php` },
    ];
  },
};

export default config;
