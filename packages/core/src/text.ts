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
