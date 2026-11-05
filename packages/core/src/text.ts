/** Linear-time string helpers (no backtracking regex on untrusted input). */

/** Removes every trailing `ch` (default `/`). */
export function trimEnd(s: string, ch = '/'): string {
  let i = s.length;
  while (i > 0 && s[i - 1] === ch) i--;
  return s.slice(0, i);
}

/** Site address without trailing slashes. */
export function siteBase(url: string): string {
  return trimEnd(String(url || ''));
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
