// Sunucudan gelen HTML'i (görev açıklaması vb.) güvenli alt kümeye indirger.
const ALLOWED = new Set(['P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'UL', 'OL', 'LI', 'A', 'CODE', 'PRE', 'BLOCKQUOTE', 'H3', 'H4', 'SPAN', 'IMG']);

export function sanitizeHtml(html: string): string {
  if (!html || typeof DOMParser === 'undefined') return '';
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
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
        if (!keep || /^\s*javascript:/i.test(attr.value)) child.removeAttribute(attr.name);
      }
      if (child.tagName === 'A') (child.setAttribute('target', '_blank'), child.setAttribute('rel', 'noreferrer'));
    }
  };
  const root = doc.body.firstElementChild!;
  walk(root);
  return root.innerHTML;
}
