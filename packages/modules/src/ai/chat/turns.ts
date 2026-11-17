// Groups the stored messages into what the chat shows: user messages, assistant turns (reasoning, tool calls and
// the final answer) and errors.
import type { Msg, ToolCall } from './types';

export type Work =
  | { k: 'think'; text: string }
  | { k: 'note'; text: string }
  | { k: 'tool'; id: string; name: string; args: Record<string, unknown> | null; res: Msg | undefined };

export interface AiTurn {
  kind: 'ai';
  id: string;
  work: Work[];
  answer: string;
  ms: number;
  calls: number;
  failed: number;
  rejected: number;
  /** Change requests / commits of the workspace tools (flxfm_*) this turn made. */
  ch: Record<string, { id: number; pr: boolean; n: number }>;
  /** Visual reports (flxai_visual_report) this turn made. */
  rep: Record<string, { path: string; title: string }>;
}

export type Turn = { kind: 'user'; m: Msg } | { kind: 'err'; m: Msg } | AiTurn;

function json(s: string | null | undefined): unknown {
  try {
    return JSON.parse(s || 'null');
  } catch {
    return null;
  }
}

/** A half-written code fence in a streaming answer is cut off until it closes. */
function liveText(s: string): string {
  const parts = s.split('```');
  return parts.length % 2 === 0 ? parts.slice(0, -1).join('```') + '\n\n…' : s;
}

export function buildTurns(msgs: Msg[]): Turn[] {
  const out: Turn[] = [];
  const results = new Map<string, Msg>();
  for (const m of msgs) if (m.role === 'tool' && m.tool_call_id) results.set(m.tool_call_id, m);
  let cur: AiTurn | null = null;
  msgs.forEach((m, i) => {
    if (m.role === 'user') {
      cur = null;
      out.push({ kind: 'user', m });
      return;
    }
    if (m.role !== 'assistant') return;
    if (m._error) {
      out.push({ kind: 'err', m });
      cur = null;
      return;
    }
    if (!cur) {
      cur = { kind: 'ai', id: m._id || 'i' + i, work: [], answer: '', ms: 0, calls: 0, failed: 0, rejected: 0, ch: {}, rep: {} };
      out.push(cur);
    }
    cur.ms += Number(m._ms) || 0;
    if (m._thinking) cur.work.push({ k: 'think', text: m._thinking });
    if (m.tool_calls?.length) {
      if (m.content?.trim()) cur.work.push({ k: 'note', text: m.content });
      for (const c of m.tool_calls as ToolCall[]) {
        const r = results.get(c.id);
        const args = json(c.function.arguments || '{}') as Record<string, unknown> | null;
        cur.work.push({ k: 'tool', id: c.id, name: c.function.name, args, res: r });
        cur.calls++;
        if (r && /^flxfm_/.test(c.function.name || '')) {
          const rj = json(r.content) as { staged?: boolean; change?: number; commit?: number } | null;
          if (rj?.staged && rj.change) cur.ch['p' + rj.change] = { id: rj.change, pr: true, n: (cur.ch['p' + rj.change]?.n ?? 0) + 1 };
          else if (rj?.commit) cur.ch['c' + rj.commit] = { id: rj.commit, pr: false, n: (cur.ch['c' + rj.commit]?.n ?? 0) + 1 };
        }
        if (r && c.function.name === 'flxai_visual_report' && r._ok !== false) {
          const rr = json(r.content) as { path?: string; title?: string } | null;
          if (rr?.path) cur.rep[rr.path] = { path: rr.path, title: rr.title || rr.path };
        }
        if (r) {
          cur.ms += Number(r._ms) || 0;
          if (r._rejected) cur.rejected++;
          else if (r._ok === false) cur.failed++; // a rejection is not an error
        }
      }
    } else if (m.content || m._show) cur.answer = m._live ? liveText(m.content || '') : m._show != null ? m._show : m.content || '';
  });
  return out;
}

export function pretty(v: unknown): string {
  if (v == null || v === '') return '';
  if (typeof v === 'string') {
    try {
      return JSON.stringify(JSON.parse(v), null, 2);
    } catch {
      return v;
    }
  }
  try {
    return JSON.stringify(v, null, 2);
  } catch {
    return String(v);
  }
}

export function argLabel(a: Record<string, unknown> | null): string {
  if (!a || typeof a !== 'object') return '';
  const scalar = (v: unknown) => v != null && typeof v !== 'object';
  for (const k of ['title', 'search', 'query', 'q', 'id', 'name', 'slug', 'fix', 'url']) if (scalar(a[k])) return String(a[k]).slice(0, 60);
  for (const k of Object.keys(a)) if (scalar(a[k])) return String(a[k]).slice(0, 60);
  return '';
}
