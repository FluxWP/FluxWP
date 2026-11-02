// WhatsApp metin biçimi (*kalın* _italik_ ~üstü çizili~ ```kod``` `satır içi`), linkler, zaman etiketleri.
import { Fragment, type ReactNode } from 'react';
type TFunction = (key: string, opts?: Record<string, unknown>) => string;

const RX_URL = /(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?*_~])/gi;

export function firstUrl(text: string): string {
  RX_URL.lastIndex = 0;
  const m = RX_URL.exec(text || '');
  return m ? m[1]! : '';
}

/** Yalnız 1-6 emojiden oluşan mesaj büyük gösterilir. */
export function emojiOnly(text: string): boolean {
  const s = text.trim();
  if (!s || s.length > 48) return false;
  const seg = [...new Intl.Segmenter('tr', { granularity: 'grapheme' }).segment(s.replace(/\s+/g, ''))];
  return seg.length <= 6 && seg.every((g) => /\p{Extended_Pictographic}|\p{Regional_Indicator}/u.test(g.segment));
}

function links(s: string, key: string): ReactNode[] {
  const out: ReactNode[] = [];
  let last = 0;
  RX_URL.lastIndex = 0;
  for (let m = RX_URL.exec(s); m; m = RX_URL.exec(s)) {
    if (m.index > last) out.push(s.slice(last, m.index));
    out.push(<a key={key + m.index} href={m[1]} target="_blank" rel="noreferrer noopener">{m[1]}</a>);
    last = m.index + m[1]!.length;
  }
  if (last < s.length) out.push(s.slice(last));
  return out;
}

const INLINE: [RegExp, (c: ReactNode, k: string) => ReactNode][] = [
  [/`([^`\n]+)`/, (c, k) => <code key={k}>{c}</code>],
  [/(?<![\p{L}\p{N}])\*([^*\n]+)\*(?![\p{L}\p{N}])/u, (c, k) => <strong key={k}>{c}</strong>],
  [/(?<![\p{L}\p{N}])_([^_\n]+)_(?![\p{L}\p{N}])/u, (c, k) => <em key={k}>{c}</em>],
  [/(?<![\p{L}\p{N}])~([^~\n]+)~(?![\p{L}\p{N}])/u, (c, k) => <s key={k}>{c}</s>],
];

function inline(s: string, key: string, depth = 0): ReactNode[] {
  if (depth > 4) return links(s, key);
  let best: { i: number; m: RegExpExecArray; f: (c: ReactNode, k: string) => ReactNode; code: boolean } | null = null;
  INLINE.forEach(([rx, f], idx) => {
    const m = rx.exec(s);
    if (m && (!best || m.index < best.i)) best = { i: m.index, m, f, code: idx === 0 };
  });
  if (!best) return links(s, key);
  const b = best as { i: number; m: RegExpExecArray; f: (c: ReactNode, k: string) => ReactNode; code: boolean };
  const inner = b.code ? b.m[1] : inline(b.m[1]!, key + 'i', depth + 1);
  return [
    ...links(s.slice(0, b.i), key + 'a'),
    b.f(inner, key + 'b'),
    ...inline(s.slice(b.i + b.m[0].length), key + 'c', depth),
  ];
}

export function WaText({ text }: { text: string }) {
  const parts = text.split(/```([\s\S]*?)```/);
  return (
    <>
      {parts.map((p, i) =>
        i % 2 ? (
          <pre key={i}>{p}</pre>
        ) : (
          <Fragment key={i}>
            {p.split('\n').map((line, j, arr) => (
              <Fragment key={j}>
                {inline(line, `${i}-${j}-`)}
                {j < arr.length - 1 && <br />}
              </Fragment>
            ))}
          </Fragment>
        ),
      )}
    </>
  );
}

const dayStart = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();

/** Listedeki zaman: bugün saat, dün "Dün", bu hafta gün adı, daha eski tarih. */
export function listTime(ts: number, t: TFunction, lang: string): string {
  if (!ts) return '';
  const d = new Date(ts);
  const today = dayStart(new Date());
  const diff = Math.round((today - dayStart(d)) / 86_400_000);
  if (diff <= 0) return d.toLocaleTimeString(lang, { hour: '2-digit', minute: '2-digit' });
  if (diff === 1) return t('wa.yesterday');
  if (diff < 7) return d.toLocaleDateString(lang, { weekday: 'long' });
  return d.toLocaleDateString(lang, { day: '2-digit', month: '2-digit', year: '2-digit' });
}

export function clock(ts: number, lang: string): string {
  return new Date(ts).toLocaleTimeString(lang, { hour: '2-digit', minute: '2-digit' });
}

/** Gün ayracı: Bugün / Dün / tarih. */
export function dayLabel(ts: number, t: TFunction, lang: string): string {
  const diff = Math.round((dayStart(new Date()) - dayStart(new Date(ts))) / 86_400_000);
  if (diff <= 0) return t('wa.today');
  if (diff === 1) return t('wa.yesterday');
  return new Date(ts).toLocaleDateString(lang, { day: 'numeric', month: 'long', year: diff > 300 ? 'numeric' : undefined, weekday: diff < 7 ? 'long' : undefined });
}

export const sameDay = (a: number, b: number) => dayStart(new Date(a)) === dayStart(new Date(b));

export function fileSize(n: number): string {
  if (!n) return '';
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

export function duration(secs: number): string {
  const s = Math.max(0, Math.round(secs));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
