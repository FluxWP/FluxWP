import type { MetadataRoute } from 'next';
import { RENDER, SITE_URL } from '@/lib/env';
import { getSite } from '@/lib/flux';

export default async function robots(): Promise<MetadataRoute.Robots> {
  const rules = [{ userAgent: '*', allow: '/', disallow: ['/api/'] }];
  if (RENDER !== 'react') return { rules, sitemap: SITE_URL ? `${SITE_URL}/sitemap.xml` : undefined };
  const site = await getSite();
  return { rules: [{ userAgent: '*', allow: '/', disallow: ['/api/'] }], sitemap: `${site.url}/sitemap.xml` };
}
