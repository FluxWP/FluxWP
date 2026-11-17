// Small, safe Markdown for chat answers: headings, lists, tables, quotes, code, links, bold/italic.
// Output is React elements (no raw HTML); links only to http(s), relative, anchors and mailto.
import { Fragment, type ReactNode } from 'react';
import { Widget } from './Widget';

const INLINE = /`([^`\n]+)`|\[([^\]]+)\]\(([^)\s]+)\)|(https?:\/\/[^\s<)]+)|\*\*([^*]+)\*\*|\*([^*\n]+)\*/g;

function Link({ href, children }: { href: string; children: ReactNode }) {
  if (!/^(https?:\/\/|\/|#|mailto:)/i.test(href)) return <>{children}</>;
  let ext = false;
  try {
    ext = /^https?:\/\//i.test(href) && new URL(href).host !== window.location.host;
  } catch {
    ext = true;
  }
  return (
    <a href={href} {...(ext ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
      {children}
    </a>
  );
}

function inline(s: string): ReactNode[] {
  const out: ReactNode[] = [];
  let last = 0;
  let n = 0;
  for (const m of s.matchAll(INLINE)) {
    const at = m.index ?? 0;
    if (at > last) out.push(s.slice(last, at));
    if (m[1] !== undefined) out.push(<code key={n++}>{m[1]}</code>);
    else if (m[2] !== undefined && m[3] !== undefined) out.push(<Link key={n++} href={m[3]}>{m[2]}</Link>);
    else if (m[4] !== undefined) out.push(<Link key={n++} href={m[4]}>{m[4]}</Link>);
    else if (m[5] !== undefined) out.push(<strong key={n++}>{m[5]}</strong>);
    else if (m[6] !== undefined) out.push(<em key={n++}>{m[6]}</em>);
    last = at + m[0].length;
  }
  if (last < s.length) out.push(s.slice(last));
  return out;
}

const LIST = /^\s*([-*+]|\d+[.)])\s+/;
const ROW = /^\s*\|.*\|\s*$/;
const cells = (r: string) => r.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());

export function Markdown({ text, mermaid, widgets, running }: { text: string; mermaid: string; widgets: boolean; running: boolean }) {
  const lines = text.replace(/\r/g, '').split('\n');
  const out: ReactNode[] = [];
  let i = 0;
  let k = 0;
  while (i < lines.length) {
    const l = lines[i] ?? '';
    if (/^```/.test(l)) {
      const lang = l.slice(3).trim().toLowerCase().split(/\s/)[0] ?? '';
      const buf: string[] = [];
      i++;
      while (i < lines.length && !/^```\s*$/.test(lines[i] ?? '')) buf.push(lines[i++] ?? '');
      const closed = i < lines.length;
      i++;
      const code = buf.join('\n');
      if (widgets && (lang === 'widget' || lang === 'mermaid')) {
        // A half-written block stays a placeholder while the answer streams.
        out.push(closed ? <Widget key={k++} type={lang} code={code} mermaid={mermaid} /> : running ? <div key={k++} className="flxai-wg"><div className="flxai-wg-ph">…</div></div> : <pre key={k++}><code>{code}</code></pre>);
      } else
        out.push(
          <pre key={k++}>
            <code>{code}</code>
          </pre>,
        );
      continue;
    }
    if (/^\s*$/.test(l)) {
      i++;
      continue;
    }
    const h = /^(#{1,4})\s+(.*)$/.exec(l);
    if (h) {
      const Tag = `h${h[1]!.length}` as 'h1' | 'h2' | 'h3' | 'h4';
      out.push(<Tag key={k++}>{inline(h[2] ?? '')}</Tag>);
      i++;
      continue;
    }
    if (/^(-{3,}|\*{3,})\s*$/.test(l)) {
      out.push(<hr key={k++} />);
      i++;
      continue;
    }
    if (ROW.test(l) && i + 1 < lines.length && /^\s*\|?\s*:?-+/.test(lines[i + 1] ?? '')) {
      const head = cells(l);
      const rows: string[][] = [];
      i += 2;
      while (i < lines.length && ROW.test(lines[i] ?? '')) rows.push(cells(lines[i++] ?? ''));
      out.push(
        <table key={k++}>
          <thead>
            <tr>{head.map((c, x) => <th key={x}>{inline(c)}</th>)}</tr>
          </thead>
          <tbody>
            {rows.map((r, y) => (
              <tr key={y}>{r.map((c, x) => <td key={x}>{inline(c)}</td>)}</tr>
            ))}
          </tbody>
        </table>,
      );
      continue;
    }
    if (LIST.test(l)) {
      const ol = /^\s*\d+[.)]/.test(l);
      const items: string[] = [];
      while (i < lines.length && LIST.test(lines[i] ?? '')) {
        let it = (lines[i++] ?? '').replace(LIST, '');
        while (i < lines.length && /^\s{2,}\S/.test(lines[i] ?? '') && !LIST.test(lines[i] ?? '')) it += ' ' + (lines[i++] ?? '').trim();
        items.push(it);
      }
      const li = items.map((x, y) => <li key={y}>{inline(x)}</li>);
      out.push(ol ? <ol key={k++}>{li}</ol> : <ul key={k++}>{li}</ul>);
      continue;
    }
    if (/^>\s?/.test(l)) {
      const q: string[] = [];
      while (i < lines.length && /^>\s?/.test(lines[i] ?? '')) q.push((lines[i++] ?? '').replace(/^>\s?/, ''));
      out.push(<blockquote key={k++}>{inline(q.join(' '))}</blockquote>);
      continue;
    }
    const p = [l];
    i++;
    while (i < lines.length && !/^\s*$/.test(lines[i] ?? '') && !/^(```|#{1,4}\s|\s*([-*+]|\d+[.)])\s+|>|\s*\|)/.test(lines[i] ?? '')) p.push(lines[i++] ?? '');
    out.push(
      <p key={k++}>
        {p.map((x, y) => (
          <Fragment key={y}>
            {y > 0 && <br />}
            {inline(x)}
          </Fragment>
        ))}
      </p>,
    );
  }
  return <div className="flxai-md">{out}</div>;
}
