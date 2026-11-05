// Checklist lives in the task description: <ul data-checklist="1"><li data-checked="0">…</li></ul>
import { stripAngles } from '@fluxwp/core';

export interface CheckItem {
  text: string;
  done: boolean;
}

/** One left-to-right pass. `&amp;lt;` stays `&lt;`; it is not decoded twice into `<`. */
function unescapeOnce(s: string): string {
  let out = '';
  for (let i = 0; i < s.length; i++) {
    if (s.startsWith('&amp;', i)) { out += '&'; i += 4; continue; }
    if (s.startsWith('&lt;', i)) { out += '<'; i += 3; continue; }
    out += s[i];
  }
  return out;
}

function itemText(raw: string): string {
  return stripAngles(unescapeOnce(raw)).trim();
}

const UL = /<ul\b[^>]*\bdata-checklist=(['"])1\1[^>]*>([\s\S]*?)<\/ul>/i;

export function parseChecklist(html: string): CheckItem[] {
  const block = UL.exec(html);
  if (!block) return [];
  const items: CheckItem[] = [];
  const li = /<li\b([^>]*)>([\s\S]*?)<\/li>/gi;
  let m: RegExpExecArray | null;
  while ((m = li.exec(block[2] ?? ''))) {
    const done = /data-checked=(['"])(?:1|true)\1/i.test(m[1] ?? '');
    const text = itemText(m[2] ?? '');
    if (text) items.push({ text, done });
  }
  return items;
}

/** Returns the description with item `index` toggled, or the original html when there is no checklist. */
export function toggleChecklistItem(html: string, index: number): string {
  const block = UL.exec(html);
  if (!block || block.index === undefined) return html;
  let i = 0;
  const next = (block[2] ?? '').replace(/<li\b([^>]*)>/gi, (full, attrs: string) => {
    if (i++ !== index) return full;
    const done = /data-checked=(['"])(?:1|true)\1/i.test(attrs);
    const cleaned = attrs.replace(/\s*data-checked=(['"]).*?\1/i, '');
    return `<li${cleaned} data-checked="${done ? '0' : '1'}">`;
  });
  return html.slice(0, block.index) + block[0].replace(block[2] ?? '', next) + html.slice(block.index + block[0].length);
}
