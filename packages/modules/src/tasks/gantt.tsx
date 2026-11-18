// Flux Tasks Gantt görünümü: solda görev listesi, sağda zaman çizelgesi. Çubuk sürükle (taşı / boyutlandır),
// uçtaki noktadan başka göreve sürükleyerek bağımlılık, ölçek (gün / hafta / ay / çeyrek) ve görünüm başına özelleştirme.
import { useEffect, useLayoutEffect, useRef, useState, type CSSProperties, type MouseEvent as RMouseEvent, type PointerEvent as RPointerEvent, type ReactNode } from 'react';
import type { FlxgGanttConfig, FlxgTask } from '@fluxwp/core';
import { addDays, dayKey, parseDay, today, type FlxgEngine, type Group } from './engine';
import { flxgLocale, tt } from './i18n';
import { DateCalendar } from '@fluxwp/ui';
import { Avatars, cv, debounce, Ic, pickPeople, PrioFlag, StatusPill, useYtg, type Ctx, type MenuItem } from './ui';
import { saveViewPart } from './views';
import './gantt.css';

type Zoom = NonNullable<FlxgGanttConfig['zoom']>;
type G = Required<FlxgGanttConfig>;
type Row = { kind: 'group'; g: Group; open: boolean } | { kind: 'task'; t: FlxgTask; depth: number; kids: number; open: boolean };
type Span = { s: string; e: string };
type Drag = { id: number; mode: 'move' | 'start' | 'end' | 'create'; x0: number; y0: number; day0: string; s: string; e: string; moved: boolean; touch: boolean };
type Link = { from: number; side: 'out' | 'in'; x0: number; y0: number; x: number; y: number };

export const GANTT_DEFAULTS: G = {
  zoom: 'week', color: 'status', label: 'inside', cols: ['assignees', 'due'], side: 380,
  weekends: true, today: true, deps: true, resched: false, subtasks: true, undated: true, progress: true,
};
const PX: Record<Zoom, number> = { day: 44, week: 24, month: 8, quarter: 2.8 };
const PAD: Record<Zoom, number> = { day: 14, week: 28, month: 70, quarter: 160 };
const ROW = 34;
const COLW: Record<string, number> = { assignees: 64, start: 64, due: 64, duration: 52, status: 96, priority: 40, progress: 52 };

export const ganttCfg = (e: FlxgEngine): G => ({ ...GANTT_DEFAULTS, ...(e.viewCfg().gantt ?? {}) });
export function setGantt(e: FlxgEngine, patch: Partial<G>) {
  const c = e.viewCfg();
  c.gantt = { ...ganttCfg(e), ...patch };
  saveViewPart(e, ['gantt']);
  e.bump();
}

/* ---------------- Tarih yardımcıları ---------------- */
const diff = (a: string, b: string) => Math.round((parseDay(b)!.getTime() - parseDay(a)!.getTime()) / 864e5);
const withTime = (old: string, day: string) => day + (old && old.length > 10 ? old.slice(10) : '');
export function spanOf(t: Pick<FlxgTask, 'start' | 'due'>): Span | null {
  const a = t.start ? t.start.slice(0, 10) : '';
  const b = t.due ? t.due.slice(0, 10) : '';
  if (!a && !b) return null;
  const s = a || b;
  const e = b || a;
  return s <= e ? { s, e } : { s: e, e: s };
}
/** Görevi yeni aralığa taşıyan yama; saat kısmı korunur, tek tarihli görev tek tarihli kalır (taşırken). */
export function spanPatch(t: Pick<FlxgTask, 'start' | 'due'>, to: Span, mode: Drag['mode']): Record<string, string> {
  const p: Record<string, string> = {};
  if (mode === 'move') {
    if (t.start) p.start = withTime(t.start, to.s);
    if (t.due) p.due = withTime(t.due, to.e);
    if (!t.start && !t.due) Object.assign(p, { start: to.s, due: to.e });
    return p;
  }
  p.start = withTime(t.start, to.s);
  p.due = withTime(t.due, to.e);
  if (to.s === to.e && mode === 'create') delete p.start;
  return p;
}

/** A'nın yeni bitişine göre A'yı bekleyen görevleri ileri kaydırır (zincirleme). Dönen: id → yeni aralık. */
export function cascade(tasks: Record<number, FlxgTask>, id: number, span: Span): Record<number, Span> {
  const out: Record<number, Span> = { [id]: span };
  const waiting: Record<number, number[]> = {};
  for (const x of Object.values(tasks)) for (const d of x.deps ?? []) (waiting[d] ??= []).push(x.id);
  const queue = [id];
  for (let guard = 0; queue.length && guard < 500; guard++) {
    const a = queue.shift()!;
    const end = out[a]!.e;
    for (const b of waiting[a] ?? []) {
      const tb = tasks[b];
      const cur = out[b] ?? (tb ? spanOf(tb) : null);
      if (!tb || !cur || cur.s >= end) continue;
      const n = diff(cur.s, end);
      out[b] = { s: addDays(cur.s, n), e: addDays(cur.e, n) };
      queue.push(b);
    }
  }
  return out;
}

function barColor(e: FlxgEngine, t: FlxgTask, by: G['color']) {
  if (by === 'priority') return e.priority(t.priority)?.color ?? '#94a3b8';
  if (by === 'list') return e.list(t.list)?.color ?? '#94a3b8';
  if (by === 'assignee') return (t.assignees[0] && e.S.users[t.assignees[0]]?.color) || '#94a3b8';
  if (by === 'accent') return 'var(--accent)';
  return e.status(t).color;
}

function store(k: string, v?: string) {
  try {
    if (v === undefined) return localStorage.getItem('flxg-' + k);
    localStorage.setItem('flxg-' + k, v);
  } catch {
    /* yok */
  }
  return null;
}

/* ---------------- Ölçek ---------------- */
type Cell = { key: string; x: number; w: number; label: string; today?: boolean };
function scale(first: string, days: number, px: number, zoom: Zoom, ws: number) {
  const loc = flxgLocale();
  const top: Cell[] = [];
  const bot: Cell[] = [];
  const td = today();
  const push = (arr: Cell[], key: string, i: number, label: string, flag?: boolean) => {
    const last = arr[arr.length - 1];
    if (last && last.key === key) last.w += px;
    else arr.push({ key, x: i * px, w: px, label, today: flag });
  };
  for (let i = 0; i < days; i++) {
    const k = addDays(first, i);
    const d = parseDay(k)!;
    const y = d.getFullYear();
    const m = d.getMonth();
    if (zoom === 'quarter') {
      push(top, String(y), i, String(y));
      push(bot, y + '-' + m, i, d.toLocaleDateString(loc, { month: 'short' }));
    } else {
      push(top, y + '-' + m, i, d.toLocaleDateString(loc, { month: 'long', year: 'numeric' }));
      if (zoom === 'month') {
        const wk = addDays(k, -((d.getDay() - ws + 7) % 7));
        push(bot, wk, i, String(parseDay(wk)!.getDate()));
        if (k === td) bot[bot.length - 1]!.today = true;
      } else if (zoom === 'day') push(bot, k, i, d.getDate() + ' ' + d.toLocaleDateString(loc, { weekday: 'short' }), k === td);
      else push(bot, k, i, String(d.getDate()), k === td);
    }
  }
  return { top, bot };
}

/* ---------------- Görünüm ---------------- */
export function GanttView({ onQuick }: { onQuick: (extra: Record<string, string>) => void }) {
  const c = useYtg();
  const { e, ui } = c;
  const S = e.S;
  const g = ganttCfg(e);
  const cfg = e.viewCfg();
  const mobile = typeof window !== 'undefined' && window.innerWidth < 720;
  const [sideOn, setSideOn] = useState(() => store('gantt-side') !== '0');
  const [sideW, setSideW] = useState(g.side);
  const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set(JSON.parse(store('gantt-col') || '[]') as string[]));
  const [drag, setDrag] = useState<Drag | null>(null);
  const [link, setLink] = useState<Link | null>(null);
  const [vw, setVw] = useState(1000);
  const scrollRef = useRef<HTMLDivElement>(null);
  const bodyRef = useRef<HTMLDivElement>(null);
  const centerRef = useRef<string | null>(null);
  const placed = useRef('');
  useEffect(() => setSideW(g.side), [g.side]);

  const px = PX[g.zoom];
  const ws = S.config.weekStart ?? 1;
  const td = today();
  const side = sideOn ? (mobile ? Math.min(sideW, 170) : sideW) : 0;
  const cols = mobile ? [] : g.cols;

  /* Satırlar */
  const kidsIdx = e.childIndex();
  const top = e.filtered({ context: true });
  const keepRow = (t: FlxgTask) => g.undated || !!spanOf(t);
  const rows: Row[] = [];
  const addTask = (t: FlxgTask, depth: number) => {
    const kids = g.subtasks ? (kidsIdx[t.id] ?? []).filter((k) => k.kind === 'task' && (cfg.subFilter ? e.taskMatches(k) : true)) : [];
    if (!keepRow(t) && !kids.length) return;
    const open = !collapsed.has('t' + t.id);
    rows.push({ kind: 'task', t, depth, kids: kids.length, open });
    if (open) for (const k of kids) if (depth < 6) addTask(k, depth + 1);
  };
  const group = cfg.group && cfg.group !== 'none' ? cfg.group : '';
  if (group) {
    for (const gr of e.groups(top, group)) {
      if (!gr.tasks.length) continue;
      const open = !collapsed.has('g' + gr.key);
      rows.push({ kind: 'group', g: gr, open });
      if (open) for (const t of gr.tasks) addTask(t, 0);
    }
  } else for (const t of top) addTask(t, 0);

  /* Aralık */
  const live = (t: FlxgTask): Span | null => (drag && drag.id === t.id && drag.mode !== 'create' ? { s: drag.s, e: drag.e } : resched[t.id] ?? spanOf(t));
  let lo = td;
  let hi = td;
  for (const r of rows) {
    if (r.kind !== 'task') continue;
    const sp = spanOf(r.t);
    if (!sp) continue;
    if (sp.s < lo) lo = sp.s;
    if (sp.e > hi) hi = sp.e;
  }
  let first = addDays(lo, -PAD[g.zoom]);
  const fd = parseDay(first)!;
  if (g.zoom === 'quarter') first = dayKey(new Date(fd.getFullYear(), fd.getMonth(), 1));
  else first = addDays(first, -((fd.getDay() - ws + 7) % 7));
  let days = diff(first, hi) + PAD[g.zoom] + 1;
  days = Math.max(days, Math.ceil((vw - side) / px) + 7);
  const W = Math.round(days * px);
  const xOf = (k: string) => diff(first, k) * px;
  const { top: topCells, bot: botCells } = scale(first, days, px, g.zoom, ws);

  /* Bekleyenlerin kaydırılması (sürüklerken önizleme) */
  const resched: Record<number, Span> = drag && drag.moved && g.resched && drag.mode !== 'create' ? cascade(S.tasks, drag.id, { s: drag.s, e: drag.e }) : {};
  delete resched[drag?.id ?? 0];

  useEffect(() => {
    const el = scrollRef.current;
    if (!el || typeof ResizeObserver === 'undefined') return;
    const ro = new ResizeObserver(() => setVw(el.clientWidth));
    ro.observe(el);
    setVw(el.clientWidth);
    return () => ro.disconnect();
  }, []);
  // İlk açılışta / ölçek değişince: bugün (ya da önceki orta nokta) görünür.
  const placeKey = (S.view?.id ?? 0) + ':' + g.zoom;
  useLayoutEffect(() => {
    const el = scrollRef.current;
    if (!el || placed.current === placeKey) return;
    placed.current = placeKey;
    const center = centerRef.current;
    centerRef.current = null;
    const span = el.clientWidth - side;
    el.scrollLeft = Math.max(0, center ? xOf(center) - span / 2 : xOf(td) - span * 0.25);
  });
  const centerDay = () => {
    const el = scrollRef.current;
    if (!el) return td;
    return addDays(first, Math.round((el.scrollLeft + (el.clientWidth - side) / 2) / px));
  };
  const goToday = () => {
    const el = scrollRef.current;
    if (el) el.scrollTo({ left: Math.max(0, xOf(td) - (el.clientWidth - side) * 0.25), behavior: 'smooth' });
  };
  const toggle = (k: string) => {
    const n = new Set(collapsed);
    if (n.has(k)) n.delete(k);
    else n.add(k);
    setCollapsed(n);
    store('gantt-col', JSON.stringify([...n].slice(-300)));
  };

  /* Sürükleme */
  const dayAt = (clientX: number) => {
    const r = bodyRef.current!.getBoundingClientRect();
    return addDays(first, Math.floor((clientX - r.left - side) / px));
  };
  const canEdit = (t: FlxgTask) => e.canList(t.list, 'edit');
  const startDrag = (ev: RPointerEvent, t: FlxgTask, mode: Drag['mode']) => {
    if (ev.button !== 0 || !canEdit(t)) return;
    ev.stopPropagation();
    const sp = spanOf(t);
    const day0 = dayAt(ev.clientX);
    const touch = ev.pointerType === 'touch';
    if (mode !== 'create') ev.preventDefault();
    const d: Drag = { id: t.id, mode, x0: ev.clientX, y0: ev.clientY, day0, s: sp?.s ?? day0, e: sp?.e ?? day0, moved: false, touch };
    let cur = d;
    setDrag(d);
    const move = (m: PointerEvent) => {
      if (touch && mode === 'create' && Math.abs(m.clientY - d.y0) > 8) return end(null);
      const dx = m.clientX - d.x0;
      if (!cur.moved && Math.abs(dx) < 4) return;
      if (touch && mode === 'create') return end(null);
      const n = Math.round(dx / px);
      let s = sp?.s ?? day0;
      let en = sp?.e ?? day0;
      if (mode === 'move') ((s = addDays(s, n)), (en = addDays(en, n)));
      else if (mode === 'start') s = addDays(s, n) > en ? en : addDays(s, n);
      else if (mode === 'end') en = addDays(en, n) < s ? s : addDays(en, n);
      else {
        const at = dayAt(m.clientX);
        s = at < day0 ? at : day0;
        en = at < day0 ? day0 : at;
      }
      cur = { ...cur, s, e: en, moved: true };
      setDrag(cur);
    };
    const up = () => end(cur);
    const end = (fin: Drag | null) => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      window.removeEventListener('pointercancel', cancel);
      setDrag(null);
      if (!fin) return;
      if (!fin.moved) {
        if (mode === 'create') commit(t, { s: day0, e: day0 }, 'create');
        else if (mode === 'move') e.openTask(t.id);
        return;
      }
      if (sp && fin.s === sp.s && fin.e === sp.e) return;
      commit(t, { s: fin.s, e: fin.e }, mode);
    };
    const cancel = () => end(null);
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', cancel);
  };
  const commit = (t: FlxgTask, to: Span, mode: Drag['mode']) => {
    const shifts = g.resched ? cascade(S.tasks, t.id, to) : { [t.id]: to };
    for (const [id, sp] of Object.entries(shifts)) {
      const x = S.tasks[+id];
      if (!x || (+id !== t.id && !canEdit(x))) continue;
      const p = spanPatch(x, sp, +id === t.id ? mode : 'move');
      if (Object.keys(p).length) void e.patchTask(x.id, p).catch(() => {});
    }
    const moved = Object.keys(shifts).length - 1;
    if (moved > 0) ui.toast(tt("{n} waiting tasks moved forward.", { n: moved }));
  };

  /* Bağımlılık bağlama */
  const startLink = (ev: RPointerEvent, t: FlxgTask, sideK: Link['side']) => {
    if (ev.button !== 0) return;
    ev.stopPropagation();
    ev.preventDefault();
    const r = bodyRef.current!.getBoundingClientRect();
    const rel = (m: { clientX: number; clientY: number }) => ({ x: m.clientX - r.left - side, y: m.clientY - r.top });
    const p0 = rel(ev);
    let cur: Link = { from: t.id, side: sideK, x0: p0.x, y0: p0.y, ...p0 };
    setLink(cur);
    const move = (m: PointerEvent) => {
      cur = { ...cur, ...rel(m) };
      setLink(cur);
    };
    const up = (m: PointerEvent) => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      setLink(null);
      const hit = (document.elementFromPoint(m.clientX, m.clientY) as HTMLElement | null)?.closest('[data-gbar]') as HTMLElement | null;
      const other = hit ? +hit.dataset.gbar! : 0;
      if (!other || other === t.id) return;
      const waiter = sideK === 'out' ? other : t.id;
      const blocker = sideK === 'out' ? t.id : other;
      addDep(c, waiter, blocker);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  /* Satır konumları ve oklar */
  const yOf: Record<number, number> = {};
  rows.forEach((r, i) => r.kind === 'task' && (yOf[r.t.id] = i * ROW + ROW / 2));
  const bars: Record<number, { x1: number; x2: number }> = {};
  for (const r of rows) {
    if (r.kind !== 'task') continue;
    const sp = live(r.t);
    if (sp) bars[r.t.id] = { x1: xOf(sp.s), x2: xOf(sp.e) + px };
  }
  const H = rows.length * ROW;
  const arrows: ReactNode[] = [];
  if (g.deps) {
    for (const r of rows) {
      if (r.kind !== 'task') continue;
      const b = bars[r.t.id];
      for (const dep of r.t.deps ?? []) {
        const a = bars[dep];
        if (!a || !b) continue;
        const ay = yOf[dep]!;
        const by = yOf[r.t.id]!;
        const sa = live(S.tasks[dep]!)!;
        const sb = live(r.t)!;
        const bad = sb.s < sa.e;
        const x1 = a.x2;
        const x2 = b.x1;
        const dy = by > ay ? 1 : -1;
        const path =
          x2 - x1 >= 16
            ? `M${x1} ${ay}H${x1 + 8}V${by}H${x2 - 2}`
            : `M${x1} ${ay}H${x1 + 8}V${by - (dy * ROW) / 2}H${x2 - 10}V${by}H${x2 - 2}`;
        const k = dep + '-' + r.t.id;
        arrows.push(
          <g key={k} className={'flxg-gantt-link' + (bad ? ' is-bad' : '')} onClick={(ev) => depMenu(c, ev.currentTarget as unknown as HTMLElement, r.t.id, dep)}>
            <title>{bad ? tt("Conflict: starts before \"{n}\" ends", { n: S.tasks[dep]!.title }) : tt("\"{b}\" waits for \"{a}\"", { a: S.tasks[dep]!.title, b: r.t.title })}</title>
            <path className="flxg-gantt-link-hit" d={path} />
            <path className="flxg-gantt-link-line" d={path} markerEnd={bad ? 'url(#flxg-gantt-ah-bad)' : 'url(#flxg-gantt-ah)'} />
          </g>,
        );
      }
    }
  }

  const head = (
    <div className="flxg-gantt-head">
      <div className="flxg-cal-nav">
        <button type="button" className={'flxg-icon-btn' + (sideOn ? ' is-on' : '')} title={tt("Task list")} aria-pressed={sideOn} onClick={() => (setSideOn(!sideOn), store('gantt-side', sideOn ? '0' : '1'))}>
          <Ic n="sidebar" s={17} />
        </button>
        <button type="button" className="flxg-btn is-sm" onClick={goToday}>
          {tt("Today")}
        </button>
      </div>
      <div className="flxg-seg">
        {(
          [
            ['day', tt("Day")],
            ['week', tt("Week")],
            ['month', tt("Month")],
            ['quarter', tt("Quarter")],
          ] as const
        ).map((o) => (
          <button key={o[0]} type="button" className={g.zoom === o[0] ? 'is-on' : ''} onClick={() => ((centerRef.current = centerDay()), setGantt(e, { zoom: o[0] }))}>
            {o[1]}
          </button>
        ))}
      </div>
    </div>
  );

  const gridLines = botCells.map((b) => <span key={b.key} className={'flxg-gantt-vl' + (b.today ? ' is-today' : '')} style={{ left: b.x }} />);
  const weekend: ReactNode[] = [];
  if (g.weekends && g.zoom !== 'quarter') {
    for (let i = 0; i < days; i++) {
      const dd = parseDay(addDays(first, i))!.getDay();
      if (dd === 0 || dd === 6) weekend.push(<span key={i} className="flxg-gantt-we" style={{ left: i * px, width: px }} />);
    }
  }
  const linkLine = link && (
    <svg className="flxg-gantt-links is-live" style={{ width: W, height: H }}>
      <path className="flxg-gantt-link-live" d={`M${link.x0} ${link.y0}L${link.x} ${link.y}`} />
    </svg>
  );

  return (
    <div className="flxg-gantt-wrap">
      {head}
      <div ref={scrollRef} className={'flxg-gantt' + (drag?.moved ? ' is-dragging' : '') + (link ? ' is-linking' : '')} style={{ '--side': side + 'px', '--row': ROW + 'px' } as CSSProperties}>
        <div className="flxg-gantt-inner" style={{ width: side + W }}>
          <div className="flxg-gantt-hrow">
            {side > 0 && (
              <div className="flxg-gantt-corner" style={{ width: side }}>
                <span className="flxg-gantt-cname">{tt("Task name")}</span>
                {cols.map((k) => (
                  <span key={k} className="flxg-gantt-ccol" style={{ width: COLW[k] }}>
                    {colLabel(k)}
                  </span>
                ))}
                <span className="flxg-gantt-resize" role="separator" aria-orientation="vertical" onPointerDown={(ev) => resizeSide(ev, sideW, setSideW, (w) => setGantt(e, { side: w }))} />
              </div>
            )}
            <div className="flxg-gantt-scale" style={{ width: W }}>
              <div className="flxg-gantt-scale-top">
                {topCells.map((x) => (
                  <span key={x.key} style={{ left: x.x, width: x.w }}>
                    <b>{x.label}</b>
                  </span>
                ))}
              </div>
              <div className={'flxg-gantt-scale-bot is-' + g.zoom}>
                {botCells.map((x) => (
                  <span key={x.key} className={x.today ? 'is-today' : ''} style={{ left: x.x, width: x.w }}>
                    {x.label}
                  </span>
                ))}
              </div>
            </div>
          </div>
          <div ref={bodyRef} className="flxg-gantt-body" style={{ height: Math.max(H, 200) }}>
            <div className="flxg-gantt-bg" style={{ left: side, width: W }}>
              {weekend}
              {gridLines}
              {g.today && <span className="flxg-gantt-now" style={{ left: xOf(td) + px / 2 }} />}
            </div>
            {rows.map((r) =>
              r.kind === 'group' ? (
                <GroupRow key={'g' + r.g.key} r={r} side={side} W={W} span={groupSpan(r.g, spanOf)} xOf={xOf} px={px} onToggle={() => toggle('g' + r.g.key)} />
              ) : (
                <TaskRow
                  key={r.t.id}
                  r={r}
                  c={c}
                  g={g}
                  cols={cols}
                  side={side}
                  W={W}
                  px={px}
                  span={live(r.t)}
                  shifted={!!resched[r.t.id]}
                  active={drag?.id === r.t.id}
                  xOf={xOf}
                  kids={kidsIdx[r.t.id] ?? []}
                  onToggle={() => toggle('t' + r.t.id)}
                  onBar={(ev, mode) => startDrag(ev, r.t, mode)}
                  onLink={(ev, s) => startLink(ev, r.t, s)}
                  preview={drag?.id === r.t.id && drag.mode === 'create' && drag.moved ? { s: drag.s, e: drag.e } : null}
                />
              ),
            )}
            {!rows.length && (
              <div className="flxg-gantt-empty" style={{ left: side }}>
                <span>{tt("No tasks match.")}</span>
                {e.scopeLists().some((l) => e.canList(l.id, 'edit')) && (
                  <button type="button" className="flxg-btn is-sm" onClick={() => onQuick({ start: td, due: addDays(td, 2) })}>
                    <Ic n="plus" s={14} />
                    {tt("Task")}
                  </button>
                )}
              </div>
            )}
            {g.deps && arrows.length > 0 && (
              <svg className="flxg-gantt-links" style={{ left: side, width: W, height: H }}>
                <defs>
                  <marker id="flxg-gantt-ah" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto">
                    <path d="M0 0L8 4L0 8z" />
                  </marker>
                  <marker id="flxg-gantt-ah-bad" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto">
                    <path d="M0 0L8 4L0 8z" />
                  </marker>
                </defs>
                {arrows}
              </svg>
            )}
            {linkLine && <div className="flxg-gantt-live" style={{ left: side }}>{linkLine}</div>}
          </div>
        </div>
      </div>
    </div>
  );
}

function groupSpan(gr: Group, f: typeof spanOf): Span | null {
  let s = '';
  let en = '';
  for (const t of gr.tasks) {
    const sp = f(t);
    if (!sp) continue;
    if (!s || sp.s < s) s = sp.s;
    if (!en || sp.e > en) en = sp.e;
  }
  return s ? { s, e: en } : null;
}

const colLabel = (k: string) =>
  ({ assignees: tt("Assignee"), start: tt("Start"), due: tt("Due"), duration: tt("Duration"), status: tt("Status"), priority: tt("Priority"), progress: tt("Progress") } as Record<string, string>)[k] ?? k;

function resizeSide(ev: RPointerEvent, w0: number, set: (w: number) => void, save: (w: number) => void) {
  ev.preventDefault();
  const x0 = ev.clientX;
  let w = w0;
  const move = (m: PointerEvent) => {
    w = Math.max(180, Math.min(720, w0 + m.clientX - x0));
    set(w);
  };
  const up = () => {
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', up);
    if (w !== w0) save(w);
  };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
}

function GroupRow({ r, side, W, span, xOf, px, onToggle }: { r: Extract<Row, { kind: 'group' }>; side: number; W: number; span: Span | null; xOf: (k: string) => number; px: number; onToggle: () => void }) {
  return (
    <div className="flxg-gantt-row is-group">
      {side > 0 && (
        <div className="flxg-gantt-cell" style={{ width: side }}>
          <button type="button" className={'flxg-gantt-caret' + (r.open ? ' is-open' : '')} aria-label={r.open ? tt("Collapse") : tt("Expand")} onClick={onToggle}>
            <Ic n="chevR" s={14} />
          </button>
          <span className="flxg-dot" style={{ background: r.g.color }} />
          <b className="flxg-gantt-gname">{r.g.label}</b>
          <span className="flxg-gantt-count">{r.g.tasks.length}</span>
        </div>
      )}
      <div className="flxg-gantt-track" style={{ width: W }}>
        {span && <span className="flxg-gantt-gbar" style={{ left: xOf(span.s), width: xOf(span.e) + px - xOf(span.s), ...cv(r.g.color) }} />}
      </div>
    </div>
  );
}

function TaskRow(o: {
  r: Extract<Row, { kind: 'task' }>; c: Ctx; g: G; cols: string[]; side: number; W: number; px: number; span: Span | null; shifted: boolean; active: boolean;
  xOf: (k: string) => number; kids: FlxgTask[]; onToggle: () => void; preview: Span | null;
  onBar: (ev: RPointerEvent, mode: Drag['mode']) => void; onLink: (ev: RPointerEvent, side: Link['side']) => void;
}) {
  const { r, c, g, side, W, px, span, xOf } = o;
  const { e } = c;
  const t = r.t;
  const st = e.status(t);
  const done = st.type === 'done';
  const edit = e.canList(t.list, 'edit');
  const kidsT = o.kids.filter((k) => k.kind === 'task');
  const prog = done ? 1 : kidsT.length ? kidsT.filter((k) => e.status(k).type === 'done').length / kidsT.length : 0;
  const color = barColor(e, t, g.color);
  const days = span ? Math.round((parseDay(span.e)!.getTime() - parseDay(span.s)!.getTime()) / 864e5) + 1 : 0;
  const x1 = span ? xOf(span.s) : 0;
  const w = span ? Math.max(xOf(span.e) + px - x1, 6) : 0;
  const inside = g.label === 'inside' && w >= 56;
  const blockers = (t.deps ?? []).map((d) => e.S.tasks[d]).filter(Boolean) as FlxgTask[];
  const late = !!span && blockers.some((b) => {
    const bs = spanOf(b);
    return bs && span.s < bs.e;
  });
  const setDate = (ev: RMouseEvent<HTMLElement>, k: 'start' | 'due') => {
    ev.stopPropagation();
    if (!edit) return e.openTask(t.id);
    pickRange(c, ev.currentTarget, t, k);
  };
  const setPeople = (ev: RMouseEvent<HTMLElement>) => {
    ev.stopPropagation();
    if (!edit) return e.openTask(t.id);
    pickPeople(c, ev.currentTarget, t.assignees, debounce((ids: number[]) => void e.patchTask(t.id, { assignees: ids }).catch(() => {}), 400));
  };
  const short = (s: string) => (s ? parseDay(s)!.toLocaleDateString(flxgLocale(), { day: 'numeric', month: 'short' }) : '–');
  const cell = (k: string): ReactNode => {
    switch (k) {
      case 'assignees':
        return (
          <button type="button" className="flxg-gantt-date" aria-label={tt("Assignee")} onClick={setPeople}>
            {t.assignees.length ? <Avatars ids={t.assignees} size={20} max={2} /> : <span className="flxg-faint">–</span>}
          </button>
        );
      case 'start':
      case 'due':
        return (
          <button type="button" className="flxg-gantt-date" onClick={(ev) => setDate(ev, k)}>
            {short(t[k])}
          </button>
        );
      case 'duration':
        return days ? tt("{n}d", { n: days }) : '–';
      case 'status':
        return <StatusPill task={t} small />;
      case 'priority':
        return t.priority ? <PrioFlag n={t.priority} /> : <span className="flxg-faint">–</span>;
      case 'progress':
        return Math.round(prog * 100) + '%';
    }
    return null;
  };
  return (
    <div className={'flxg-gantt-row' + (o.active ? ' is-active' : '') + ((t as FlxgTask & { _ctx?: boolean })._ctx ? ' is-ctx' : '')} data-id={t.id}>
      {side > 0 && (
        <div className="flxg-gantt-cell" style={{ width: side }}>
          <span className="flxg-gantt-indent" style={{ width: r.depth * 16 }} />
          {r.kids > 0 ? (
            <button type="button" className={'flxg-gantt-caret' + (r.open ? ' is-open' : '')} aria-label={r.open ? tt("Collapse") : tt("Expand")} onClick={o.onToggle}>
              <Ic n="chevR" s={14} />
            </button>
          ) : (
            <span className="flxg-gantt-caret is-none" />
          )}
          <span className="flxg-dot" style={{ background: st.color }} title={st.label} />
          <button type="button" className={'flxg-gantt-title' + (done ? ' is-done' : '')} title={t.title} onClick={() => e.openTask(t.id)}>
            {t.title}
          </button>
          {o.cols.map((k) => (
            <span key={k} className={'flxg-gantt-col is-' + k} style={{ width: COLW[k] }}>
              {cell(k)}
            </span>
          ))}
        </div>
      )}
      <div className={'flxg-gantt-track' + (!span && edit ? ' is-empty' : '')} style={{ width: W }} onPointerDown={!span && edit ? (ev) => o.onBar(ev, 'create') : undefined}>
        {!span && edit && !o.preview && <span className="flxg-gantt-hint">{tt("Drag to set dates")}</span>}
        {o.preview && <span className="flxg-gantt-bar is-ghost" style={{ left: xOf(o.preview.s), width: xOf(o.preview.e) + px - xOf(o.preview.s), ...cv(color) }} />}
        {span && (
          <div
            className={'flxg-gantt-bar' + (done ? ' is-done' : '') + (o.active ? ' is-active' : '') + (o.shifted ? ' is-shifted' : '') + (late && g.deps ? ' is-late' : '') + (edit ? ' is-edit' : '') + (w < 14 ? ' is-tiny' : '')}
            data-gbar={t.id}
            style={{ left: x1, width: w, ...cv(color) } as CSSProperties}
            title={t.title + ' · ' + short(span.s) + (span.e !== span.s ? ' – ' + short(span.e) : '') + (days > 1 ? ' (' + tt("{n}d", { n: days }) + ')' : '')}
            onPointerDown={(ev) => o.onBar(ev, 'move')}
          >
            {g.progress && prog > 0 && <span className="flxg-gantt-prog" style={{ width: prog * 100 + '%' }} />}
            {inside && <span className="flxg-gantt-label">{t.title}</span>}
            {edit && (
              <>
                <span className="flxg-gantt-h is-s" onPointerDown={(ev) => o.onBar(ev, 'start')} />
                <span className="flxg-gantt-h is-e" onPointerDown={(ev) => o.onBar(ev, 'end')} />
                {g.deps && (
                  <>
                    <span className="flxg-gantt-dot is-in" title={tt("Drag to another task: this one waits for it")} onPointerDown={(ev) => o.onLink(ev, 'in')} />
                    <span className="flxg-gantt-dot is-out" title={tt("Drag to another task: it waits for this one")} onPointerDown={(ev) => o.onLink(ev, 'out')} />
                  </>
                )}
              </>
            )}
          </div>
        )}
        {span && !inside && g.label !== 'none' && (
          <span className="flxg-gantt-side-label" style={{ left: x1 + w + (g.deps && edit ? 14 : 6) }}>
            {t.title}
          </span>
        )}
      </div>
    </div>
  );
}

/* ---------------- Bağımlılık işlemleri ---------------- */
function reaches(tasks: Record<number, FlxgTask>, from: number, to: number) {
  const seen = new Set<number>();
  const q = [from];
  while (q.length) {
    const x = q.shift()!;
    if (x === to) return true;
    if (seen.has(x)) continue;
    seen.add(x);
    for (const d of tasks[x]?.deps ?? []) q.push(d);
  }
  return false;
}
export function addDep(c: Ctx, waiter: number, blocker: number) {
  const { e, ui } = c;
  const t = e.S.tasks[waiter];
  if (!t || waiter === blocker) return;
  if (!e.canList(t.list, 'edit')) return ui.toast(tt("You can't edit this task."), { error: true });
  const cur = t.deps ?? [];
  if (cur.includes(blocker)) return;
  if (reaches(e.S.tasks, blocker, waiter)) return ui.toast(tt("This dependency would create a loop."), { error: true });
  void e.patchTask(waiter, { deps: [...cur, blocker] }).catch(() => {});
}
function depMenu(c: Ctx, anchor: HTMLElement, waiter: number, blocker: number) {
  const { e, ui } = c;
  const a = e.S.tasks[blocker];
  const b = e.S.tasks[waiter];
  if (!a || !b) return;
  const items: MenuItem[] = [
    { head: tt("Dependency") },
    { label: tt("Open \"{n}\"", { n: a.title }), icon: 'external', onClick: () => e.openTask(a.id) },
    { label: tt("Open \"{n}\"", { n: b.title }), icon: 'external', onClick: () => e.openTask(b.id) },
  ];
  if (e.canList(b.list, 'edit')) {
    items.push({ sep: true }, { label: tt("Remove dependency"), icon: 'trash', danger: true, onClick: () => void e.patchTask(waiter, { deps: (b.deps ?? []).filter((d) => d !== blocker) }).catch(() => {}) });
  }
  ui.menu(anchor, items);
}

/* ---------------- Özelleştir ---------------- */
export function GanttPop() {
  const { e } = useYtg();
  const g = ganttCfg(e);
  const radio = <K extends 'color' | 'label'>(k: K, opts: [G[K], string][]) =>
    opts.map(([v, label]) => (
      <label key={String(v)} className="flxg-switch-row">
        <input type="radio" name={'flxg-gantt-' + k} checked={g[k] === v} onChange={() => setGantt(e, { [k]: v } as Partial<G>)} />
        <span>{label}</span>
      </label>
    ));
  const sw = (k: 'weekends' | 'today' | 'deps' | 'resched' | 'subtasks' | 'undated' | 'progress', label: string, hint?: string) => (
    <label key={k} className="flxg-switch-row">
      <input type="checkbox" checked={!!g[k]} onChange={(ev) => setGantt(e, { [k]: ev.target.checked })} />
      <span>
        {hint ? <b>{label}</b> : label}
        {hint && <small>{hint}</small>}
      </span>
    </label>
  );
  const all: G['cols'] = ['assignees', 'start', 'due', 'duration', 'status', 'priority', 'progress'];
  return (
    <div className="flxg-fieldspop flxg-gantt-pop">
      <div className="flxg-menu-head">{tt("Bar color")}</div>
      {radio('color', [
        ['status', tt("Status")],
        ['priority', tt("Priority")],
        ['list', tt("List")],
        ['assignee', tt("Assignee")],
        ['accent', tt("Single color")],
      ])}
      <div className="flxg-menu-head">{tt("Task name on the bar")}</div>
      {radio('label', [
        ['inside', tt("Inside the bar")],
        ['right', tt("Next to the bar")],
        ['none', tt("Don't show")],
      ])}
      <div className="flxg-menu-head">{tt("Task list columns")}</div>
      {all.map((k) => {
        const on = g.cols.includes(k);
        return (
          <label key={k} className="flxg-switch-row">
            <input type="checkbox" checked={on} onChange={() => setGantt(e, { cols: on ? g.cols.filter((x) => x !== k) : all.filter((x) => x === k || g.cols.includes(x)) })} />
            <span>{colLabel(k)}</span>
          </label>
        );
      })}
      <div className="flxg-menu-head">{tt("Display")}</div>
      {sw('weekends', tt("Shade weekends"))}
      {sw('today', tt("Today line"))}
      {sw('progress', tt("Progress on the bar"), tt("Done subtasks fill the bar; a done task is full."))}
      {sw('subtasks', tt("Show subtasks"))}
      {sw('undated', tt("Show tasks without dates"))}
      <div className="flxg-menu-head">{tt("Dependencies")}</div>
      {sw('deps', tt("Dependency arrows"), tt("Drag the dot at the end of a bar onto another task to link them."))}
      {sw('resched', tt("Move waiting tasks automatically"), tt("When a task ends later, the tasks waiting for it shift forward."))}
    </div>
  );
}

/* ---------------- Tarih aralığı ---------------- */
/** Başlangıç ve bitişi tek açılır kutuda seçtirir: üstte iki sekme, altta ortak tarih seçici (Uygula ile). */
export function pickRange(c: Ctx, anchor: HTMLElement, t: FlxgTask, first: 'start' | 'due') {
  c.ui.openPop(anchor, <RangePick c={c} t={t} first={first} />, { inline: true });
}
function RangePick({ c, t, first }: { c: Ctx; t: FlxgTask; first: 'start' | 'due' }) {
  const [v, setV] = useState({ start: t.start || '', due: t.due || '' });
  const [tab, setTab] = useState<'start' | 'due'>(first);
  const short = (s: string) => (s ? parseDay(s)!.toLocaleDateString(flxgLocale(), { day: 'numeric', month: 'short' }) : '–');
  const save = (n: { start: string; due: string }) => {
    const p: Record<string, string> = {};
    if (n.start !== (t.start || '')) p.start = n.start;
    if (n.due !== (t.due || '')) p.due = n.due;
    if (Object.keys(p).length) void c.e.patchTask(t.id, p).catch(() => {});
    c.ui.closePop();
  };
  return (
    <div className="flxg-gantt-range">
      <div className="flxg-seg flxg-gantt-range-tabs">
        {(['start', 'due'] as const).map((k) => (
          <button key={k} type="button" className={tab === k ? 'is-on' : ''} onClick={() => setTab(k)}>
            <small>{k === 'start' ? tt("Start") : tt("Due")}</small>
            <b>{short(v[k])}</b>
          </button>
        ))}
      </div>
      <DateCalendar
        key={tab}
        bare
        time
        quick
        locale={flxgLocale()}
        label={tab === 'start' ? tt("Start date") : tt("Due date")}
        value={v[tab] ? v[tab].slice(0, 16) : ''}
        min={tab === 'due' && v.start ? v.start : undefined}
        max={tab === 'start' && v.due ? v.due : undefined}
        onPick={(x) => {
          const n = { ...v, [tab]: x };
          setV(n);
          // Başlangıç seçilince bitiş sekmesine geçilir (Uygula ile mevcut bitiş korunur); bitiş seçilince ikisi birden kaydedilir.
          if (tab === 'start') setTab('due');
          else save(n);
        }}
        onCancel={() => c.ui.closePop()}
      />
    </div>
  );
}
