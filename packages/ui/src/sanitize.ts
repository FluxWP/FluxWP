// Sunucudan gelen HTML'i (görev açıklaması vb.) güvenli alt kümeye indirger.
const ALLOWED = new Set(['P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'UL', 'OL', 'LI', 'A', 'CODE', 'PRE', 'BLOCKQUOTE', 'H3', 'H4', 'SPAN', 'IMG']);

// Bağlantı/görsel adresi: yalnız http(s), mailto, tel ve göreli adres; javascript:, data:, vbscript: vb. düşer.
function safeUrl(v: string): boolean {
  // eslint-disable-next-line no-control-regex
  const u = v.replace(/[\u0000-\u0020]+/g, '').toLowerCase();
  const m = /^([a-z][a-z0-9+.-]*):/.exec(u);
  return !m || m[1] === 'http' || m[1] === 'https' || m[1] === 'mailto' || m[1] === 'tel';
}

export function sanitizeHtml(html: string): string {
  if (!html || typeof DOMParser === 'undefined') return '';
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const walk = (el: Element) => {
    for (const child of Array.from(el.children)) {
      walk(child);
      if (child.tagName === 'SCRIPT' || child.tagName === 'STYLE') {
        child.remove();
        continue;
      }
      if (!ALLOWED.has(child.tagName)) {
        child.replaceWith(...Array.from(child.childNodes));
        continue;
      }
      for (const attr of Array.from(child.attributes)) {
        const keep = (child.tagName === 'A' && attr.name === 'href') || (child.tagName === 'IMG' && (attr.name === 'src' || attr.name === 'alt'));
        if (!keep || !safeUrl(attr.value)) child.removeAttribute(attr.name);
      }
      if (child.tagName === 'A') (child.setAttribute('target', '_blank'), child.setAttribute('rel', 'noreferrer'));
    }
  };
  walk(doc.body);
  return doc.body.innerHTML;
}
