/** Linear-time string helpers (no backtracking regex on untrusted input). */

/** Removes every trailing `ch` (default `/`). */
export function trimEnd(s: string, ch = '/'): string {
  let i = s.length;
  while (i > 0 && s[i - 1] === ch) i--;
  return s.slice(0, i);
}

/** Removes every leading `ch` (default `/`). */
export function trimStart(s: string, ch = '/'): string {
  let i = 0;
  while (i < s.length && s[i] === ch) i++;
  return s.slice(i);
}

/** Site address without trailing slashes. */
export function siteBase(url: string): string {
  return trimEnd(String(url || ''));
}

/**
 * Install path under the host: `""` at the root, otherwise `/blog` (no trailing slash).
 * `wp-admin`, `wp-json` and `wp-login.php` are not part of the install path.
 */
export function siteBasePath(siteUrl: string): string {
  const u = new URL(siteUrl);
  const cut = u.pathname.search(/\/(?:wp-admin|wp-json|wp-login\.php)/);
  return trimEnd(cut < 0 ? u.pathname : u.pathname.slice(0, cut));
}

/** Drops `<` and `>` so a fragment such as `<script` cannot survive in plain text. */
export function stripAngles(s: string): string {
  let out = '';
  for (let i = 0; i < s.length; i++) {
    const c = s[i];
    if (c !== '<' && c !== '>') out += c;
  }
  return out;
}

/** Plain text of an HTML fragment (tags dropped, nothing parsed or executed). */
export function stripTags(html: string): string {
  let out = '';
  let inTag = false;
  for (let i = 0; i < html.length; i++) {
    const c = html[i];
    if (inTag) {
      if (c === '>') inTag = false;
    } else if (c === '<') inTag = true;
    else out += c;
  }
  return out;
}

const NAMED: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0', hellip: '\u2026', ndash: '\u2013', mdash: '\u2014', lsquo: '\u2018', rsquo: '\u2019', ldquo: '\u201c', rdquo: '\u201d', laquo: '\u00ab', raquo: '\u00bb', copy: '\u00a9', reg: '\u00ae', trade: '\u2122', euro: '\u20ac', bull: '\u2022', middot: '\u00b7', times: '\u00d7' };

/** Decodes HTML character references (`&amp;`, `&#8217;`, `&#x2019;`) for display as plain text. */
export function decodeEntities(s: string): string {
  if (!s.includes('&')) return s;
  return s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, e: string) => {
    if (e[0] === '#') {
      const n = e[1] === 'x' || e[1] === 'X' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
      return n > 0 && n <= 0x10ffff ? String.fromCodePoint(n) : m;
    }
    return NAMED[e.toLowerCase()] ?? m;
  });
}

/** Readable plain text of an HTML fragment: block ends become line breaks, entities decoded. */
export function htmlToText(html: string): string {
  const spaced = html.replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|div|li|h[1-6]|blockquote|tr|figure|ul|ol)>/gi, '\n\n');
  return decodeEntities(stripTags(spaced)).replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
}
