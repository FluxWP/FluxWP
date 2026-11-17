import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { SocialLinks } from '@/components/social';
import { footerMenus, MenuList, pick } from '@/components/menu';
import { RENDER } from '@/lib/env';
import { getSite } from '@/lib/flux';
import type { Site } from '@/lib/types';
import './globals.css';

export async function generateMetadata(): Promise<Metadata> {
  if (RENDER !== 'react') return {};
  const site = await getSite();
  return {
    metadataBase: new URL(site.url),
    title: { default: site.name, template: `%s` },
    description: site.description,
    icons: site.icon ? { icon: site.icon } : undefined,
  };
}

function Logo({ site, onDark }: { site: Site; onDark?: boolean }) {
  const logo = onDark ? (site.logoOnDark ?? site.logo) : site.logo;
  if (!logo) return <span className="brand-name">{site.name}</span>;
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={logo.url} alt={logo.alt || site.name} width={logo.width || 168} height={logo.height || 50} fetchPriority={onDark ? undefined : 'high'} />
  );
}

export default async function RootLayout({ children }: { children: ReactNode }) {
  // Whole-page mode: pages come from WordPress (app/wp-page); this frame only wraps Next's own 404 and errors.
  if (RENDER !== 'react') {
    return (
      <html lang="en">
        <body>{children}</body>
      </html>
    );
  }
  const site = await getSite();
  // Theme palette as CSS variables, same names as the WordPress theme (--wp--preset--color--<slug>).
  const palette = site.palette.map((c) => `--wp--preset--color--${c.slug}:${c.color};`).join('');
  const header = pick(site.menus, ['primary', 'header', 'header-2']);
  const footer = footerMenus(site.menus);
  return (
    <html lang={site.locale}>
      <body>
        <style>{`:root{${palette}}`}</style>
        <header className="site-header">
          <div className="wrap">
            <Link href="/" className="brand" prefetch>
              <Logo site={site} />
            </Link>
            {/* A checkbox opens the menu on phones without client JavaScript. */}
            <input type="checkbox" id="nav-toggle" className="nav-toggle" />
            <label htmlFor="nav-toggle" className="nav-button" aria-label="Menu">
              <span />
            </label>
            <nav aria-label="Main" className="nav">
              <MenuList menu={header} className="menu" />
            </nav>
            {site.languages.length > 1 && (
              <ul className="langs">
                {site.languages.map((l) => (
                  <li key={l.code}>
                    <a href={l.path} hrefLang={l.hreflang} aria-current={l.code === site.language ? 'true' : undefined}>
                      {l.code.toUpperCase()}
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </header>
        <main>{children}</main>
        <footer className="site-footer">
          <div className="wrap">
            <div className="footer-cols">
              <div className="footer-brand">
                <Link href="/" className="brand" prefetch>
                  <Logo site={site} onDark />
                </Link>
                {site.description && <p>{site.description}</p>}
                {site.contact.email && <p><a href={`mailto:${site.contact.email}`}>{site.contact.email}</a></p>}
              </div>
              {footer.map((m, i) => (
                // Each footer menu is a column; on phones its title opens and closes it.
                <nav key={m.location} className="footer-col" aria-label={m.name || undefined}>
                  <input type="checkbox" id={`fc-${i}`} className="fc-toggle" />
                  <label htmlFor={`fc-${i}`} className="fc-title">{m.name || site.name}</label>
                  <MenuList menu={m} className="footer-menu" />
                </nav>
              ))}
            </div>
            <div className="footer-bottom">
              <span>© {site.name}</span>
              {site.legalPages.length > 0 && (
                <span className="legal">
                  {site.legalPages.map((p) => (
                    <Link key={p.kind} href={p.path}>{p.title}</Link>
                  ))}
                </span>
              )}
              <SocialLinks urls={site.social} />
            </div>
          </div>
        </footer>
      </body>
    </html>
  );
}
