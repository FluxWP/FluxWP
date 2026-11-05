// Checklist lives in the task description: <ul data-checklist="1"><li data-checked="0">…</li></ul>
export interface CheckItem {
  text: string;
  done: boolean;
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
    const text = (m[2] ?? '').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').trim();
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
