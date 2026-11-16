// Flux Files: small helpers shared by the screen parts (paths, sizes, file icons, line diff).
import type { FlxFilesFolderQuery } from '@fluxwp/graphql/types';

export type Item = FlxFilesFolderQuery['flxFilesFolder']['dir'];
export type Match = { line: number; text: string };
export type SearchItem = Item & { matches?: Match[] };

export const base = (p: string): string => p.slice(p.lastIndexOf('/') + 1);
export const dir = (p: string): string => (p.includes('/') ? p.slice(0, p.lastIndexOf('/')) : '');
export const join = (a: string, b: string): string => (a ? a + '/' + b : b);

export function size(n: number | null | undefined): string {
  if (n == null) return '—';
  const u = ['B', 'KB', 'MB', 'GB', 'TB'];
  let i = 0;
  let v = n;
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024;
    i++;
  }
  return (i ? v.toFixed(v < 10 ? 1 : 0) : String(v)) + ' ' + u[i];
}

export const extOf = (name: string): string => (name.includes('.') ? (name.split('.').pop() ?? '').toLowerCase() : '');

const EXT_COLORS: Record<string, string> = {
  php: 'violet', js: 'amber', mjs: 'amber', ts: 'blue', tsx: 'blue', jsx: 'amber', css: 'blue', scss: 'pink', html: 'orange', htm: 'orange',
  json: 'teal', xml: 'teal', md: 'slate', txt: 'slate', log: 'slate', sql: 'teal', yml: 'teal', yaml: 'teal', zip: 'amber', pdf: 'red',
  svg: 'green', png: 'green', jpg: 'green', jpeg: 'green', gif: 'green', webp: 'green', mp4: 'pink', mp3: 'pink',
};
const KIND_COLORS: Record<string, string> = { image: 'green', video: 'pink', audio: 'pink', archive: 'amber', pdf: 'red', doc: 'blue', font: 'slate', code: 'violet' };

/** Badge colour and text of a file icon: null text = plain file glyph, gear = server config file. */
export function badge(it: { name: string; extension: string; kind: string }): { color: string; text: string | null; gear: boolean } {
  const name = it.name.toLowerCase();
  if (name === '.htaccess' || name === '.user.ini' || name === 'php.ini') return { color: 'orange', text: null, gear: true };
  const ext = it.extension.length <= 4 && name !== '.' + it.extension ? it.extension : '';
  const color = EXT_COLORS[it.extension] ?? KIND_COLORS[it.kind.toLowerCase()] ?? 'slate';
  return { color: ext ? color : 'slate', text: ext || null, gear: false };
}

export const canEdit = (it: Item): boolean => {
  const kind = it.kind.toLowerCase();
  return !it.isDir && (kind === 'code' || kind === 'text' || it.extension === 'svg' || (kind === 'file' && (it.size ?? 0) < 1048576));
};

export const lower = (k: string): string => k.toLowerCase();

export type DiffRow = ['+' | '-' | ' ' | '@', string, number?];

/** Simple line diff (LCS, trimmed on big input); long unchanged runs collapse to a "@" row with the count. */
export function diffLines(a: string, b: string): DiffRow[] {
  const A = a.split('\n');
  const B = b.split('\n');
  let s = 0;
  while (s < A.length && s < B.length && A[s] === B[s]) s++;
  let ea = A.length;
  let eb = B.length;
  while (ea > s && eb > s && A[ea - 1] === B[eb - 1]) {
    ea--;
    eb--;
  }
  const X = A.slice(s, ea);
  const Y = B.slice(s, eb);
  const out: DiffRow[] = [];
  for (let i0 = Math.max(0, s - 3); i0 < s; i0++) out.push([' ', A[i0] ?? '', i0 + 1]);
  if (s > 3) out.unshift(['@', String(s - 3)]);
  if (X.length * Y.length > 4_000_000) {
    X.forEach((l) => out.push(['-', l]));
    Y.forEach((l) => out.push(['+', l]));
  } else {
    const n = X.length;
    const m = Y.length;
    const T: Uint32Array[] = [];
    for (let i = 0; i <= n; i++) T.push(new Uint32Array(m + 1));
    for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) T[i]![j] = X[i] === Y[j] ? T[i + 1]![j + 1]! + 1 : Math.max(T[i + 1]![j]!, T[i]![j + 1]!);
    let i = 0;
    let j = 0;
    while (i < n || j < m) {
      if (i < n && j < m && X[i] === Y[j]) {
        out.push([' ', X[i]!, s + i + 1]);
        i++;
        j++;
      } else if (j < m && (i >= n || T[i]![j + 1]! >= T[i + 1]![j]!)) {
        out.push(['+', Y[j]!]);
        j++;
      } else {
        out.push(['-', X[i]!]);
        i++;
      }
    }
  }
  const tail = A.length - ea;
  for (let k = ea; k < Math.min(A.length, ea + 3); k++) out.push([' ', A[k] ?? '', k + 1]);
  if (tail > 3) out.push(['@', String(tail - 3)]);
  const res: DiffRow[] = [];
  let run: DiffRow[] = [];
  const flush = () => {
    if (run.length > 8) {
      res.push(...run.slice(0, 3), ['@', String(run.length - 6)], ...run.slice(-3));
    } else res.push(...run);
    run = [];
  };
  for (const o of out) {
    if (o[0] === ' ') run.push(o);
    else {
      flush();
      res.push(o);
    }
  }
  flush();
  return res;
}

/** Very small markdown for AI answers; the text is escaped first. */
export function mdLite(s: string): string {
  const esc = s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c);
  return esc
    .replace(/```[a-z0-9]*\n([\s\S]*?)```/gi, (_m, c: string) => `<pre><code>${c}</code></pre>`)
    .replace(/`([^`\n]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*\n]+)\*\*/g, '<b>$1</b>')
    .replace(/^#{1,4} (.+)$/gm, '<h4>$1</h4>')
    .replace(/^(?:[-*] .+(?:\n|$))+/gm, (m) => '<ul>' + m.trim().split('\n').map((l) => '<li>' + l.replace(/^[-*] /, '') + '</li>').join('') + '</ul>')
    .replace(/\n{2,}/g, '<br><br>')
    .replace(/([^>])\n([^<])/g, '$1<br>$2');
}
