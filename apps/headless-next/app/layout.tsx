import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { MenuList, pick } from '@/components/menu';
import { getSite } from '@/lib/flux';
import './globals.css';

export async function generateMetadata(): Promise<Metadata> {
  const site = await getSite();
  return {
    metadataBase: new URL(site.url),
    title: { default: site.name, template: `%s` },
    description: site.description,
    icons: site.icon ? { icon: site.icon } : undefined,
  };
}

export default async function RootLayout({ children }: { children: ReactNode }) {
  const site = await getSite();
  // Theme palette as CSS variables, same names as the WordPress theme (--wp--preset--color--<slug>).
  const palette = site.palette.map((c) => `--wp--preset--color--${c.slug}:${c.color};`).join('');
  const header = pick(site.menus, ['primary', 'header', 'header-2']);
  const footer = pick(site.menus, ['footer', 'footer-2', 'secondary']);
  return (
    <html lang={site.locale}>
      <body>
        <style>{`:root{${palette}}`}</style>
        <header className="site-header">
          <div className="wrap">
            <Link href="/" className="brand" prefetch>
              {site.logo ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={site.logo.url} alt={site.logo.alt || site.name} width={site.logo.width || undefined} height={site.logo.height || undefined} />
              ) : (
                site.name
              )}
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
                    <a href={l.path} hrefLang={l.hreflang}>{l.code.toUpperCase()}</a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </header>
        <main className="wrap">{children}</main>
        <footer className="site-footer">
          <div className="wrap">
            <MenuList menu={footer} className="menu" />
            <p>
              © {site.name}
              {site.contact.email ? <> · <a href={`mailto:${site.contact.email}`}>{site.contact.email}</a></> : null}
              {site.legalPages.map((p) => (
                <span key={p.kind}> · <Link href={p.path}>{p.title}</Link></span>
              ))}
            </p>
          </div>
        </footer>
      </body>
    </html>
  );
}
