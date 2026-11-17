// Footer social icons. `site.social` is a list of profile addresses (Flux Headless, from the organization profile);
// the network is recognised from the host, addresses of other sites are skipped.
import type { ReactNode } from 'react';

const GLYPHS: Record<string, ReactNode> = {
  instagram: <><rect x="3.5" y="3.5" width="17" height="17" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor"/></>,
  facebook: <><path d="M14.5 21v-8h2.8l.5-3.2h-3.3V7.9c0-.9.4-1.7 1.8-1.7h1.6V3.4S16.6 3.2 15.3 3.2c-2.6 0-4.3 1.6-4.3 4.4v2.2H8.2V13H11v8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></>,
  x: <><path d="M4.5 4h4l11 16h-4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4.8 20l6.2-7M19.2 4l-6 6.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></>,
  tiktok: <><path d="M14 3.5v11.2a3.8 3.8 0 1 1-3.8-3.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 3.5c.3 2.5 2 4.3 4.8 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></>,
  linkedin: <><rect x="3.5" y="3.5" width="17" height="17" rx="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V16M8 7.8v.1M11.5 16v-5.5M11.5 13c0-1.6 1-2.6 2.4-2.6s2.1 1 2.1 2.6V16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></>,
  youtube: <><rect x="2.8" y="5.5" width="18.4" height="13" rx="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10.2 9.4v5.2l4.5-2.6Z" fill="currentColor"/></>
};

const HOSTS: [RegExp, string, string][] = [
  [/(^|\.)instagram\.com$/, 'instagram', 'Instagram'],
  [/(^|\.)facebook\.com$/, 'facebook', 'Facebook'],
  [/^(www\.)?(x|twitter)\.com$/, 'x', 'X'],
  [/(^|\.)tiktok\.com$/, 'tiktok', 'TikTok'],
  [/(^|\.)linkedin\.com$/, 'linkedin', 'LinkedIn'],
  [/(^|\.)youtube\.com$|^youtu\.be$/, 'youtube', 'YouTube'],
];

function network(url: string): { key: string; label: string } | null {
  try {
    const host = new URL(url).hostname.toLowerCase();
    const hit = HOSTS.find(([re]) => re.test(host));
    return hit ? { key: hit[1], label: hit[2] } : null;
  } catch {
    return null;
  }
}

export function SocialLinks({ urls }: { urls: string[] }) {
  const items = urls.flatMap((url) => {
    const n = network(url);
    return n ? [{ url, ...n }] : [];
  });
  if (!items.length) return null;
  return (
    <ul className="footer-social">
      {items.map((s) => (
        <li key={s.url}>
          <a href={s.url} target="_blank" rel="me noopener" aria-label={s.label}>
            <svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true">{GLYPHS[s.key]}</svg>
          </a>
        </li>
      ))}
    </ul>
  );
}
