// Full-screen viewer (prev / next, side panel with prompt, meta and actions), before / after slider and
// the area-edit (mask) editor.
import { useEffect, useRef, useState, type PointerEvent as RPointerEvent } from 'react';
import { useGraphQL } from '@fluxwp/graphql/react';
import { FlxStudioEnhance } from '@fluxwp/graphql/operations';
import { Btn, Ic, IconBtn, Modal, useT, useUi } from './ui';
import { ago, call, money, providerLabel, roleLabel, modelById, type Item, type Workspace } from './lib';

export interface ViewerProps {
  ws: Workspace;
  items: Item[];
  startId: number;
  locale: string;
  onClose: () => void;
  onFav: (it: Item) => void;
  onReuse: (it: Item) => void;
  onAsRef: (it: Item) => void;
  onEditArea: (it: Item) => void;
  onMove: (anchor: HTMLElement, ids: number[]) => void;
  onDelete: (ids: number[]) => Promise<boolean>;
  onTitle: (it: Item, title: string) => void;
  onLibrary: (it: Item) => Promise<void>;
}

export function Viewer(p: ViewerProps) {
  const { ws, items, locale } = p;
  const t = useT();
  const ui = useUi();
  const [id, setId] = useState(p.startId);
  const [cmp, setCmp] = useState(false);
  const [busy, setBusy] = useState(false);
  const idx = items.findIndex((x) => x.databaseId === id);
  const it = items[idx];

  const step = (d: number) => {
    let j = idx;
    do j += d;
    while (items[j] && items[j]?.state !== 'DONE');
    const n = items[j];
    if (n) setId(n.databaseId);
  };
  const stepRef = useRef(step);
  stepRef.current = step;
  useEffect(() => {
    const key = (e: KeyboardEvent) => {
      if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName ?? '') || document.querySelector('.flxst-me-modal, .flxst-pop')) return;
      if (e.key === 'ArrowRight') stepRef.current(1);
      else if (e.key === 'ArrowLeft') stepRef.current(-1);
    };
    document.addEventListener('keydown', key);
    return () => document.removeEventListener('keydown', key);
  }, []);
  useEffect(() => {
    if (!it) p.onClose();
  }, [it, p]);
  if (!it) return null;

  const mod = modelById(ws, it.model);
  const pr = it.params;
  const parent = it.parentId ? items.find((x) => x.databaseId === it.parentId) : undefined;
  const compare = cmp && !!parent && it.kind === 'IMAGE';
  const url = it.url ?? '';
  const stage =
    it.kind === 'VIDEO' ? <video key={it.databaseId} src={url} controls autoPlay loop playsInline /> : compare && parent ? <Compare before={parent.url ?? ''} after={url} w={it.width} h={it.height} /> : <img src={url} alt={it.title} />;

  return (
    <Modal full noFocus cls="flxst-view-modal" onClose={p.onClose}>
      <div className="flxst-view">
        <div className="flxst-view-stage">
          {stage}
          <IconBtn icon="chevL" title={t('Previous')} cls="flxst-view-prev" onClick={() => step(-1)} />
          <IconBtn icon="chevR" title={t('Next')} cls="flxst-view-next" onClick={() => step(1)} />
          <IconBtn icon="x" title={t('Close')} cls="flxst-view-close" onClick={p.onClose} />
        </div>
        <aside className="flxst-view-side">
          <input
            key={'t' + it.databaseId}
            className="flxst-view-title"
            type="text"
            defaultValue={it.title}
            placeholder={t('Add title')}
            aria-label={t('Title')}
            onBlur={(e) => e.target.value !== it.title && p.onTitle(it, e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
          />
          <div className="flxst-view-prompt">
            <p>{it.prompt}</p>
            <IconBtn
              icon="copy"
              title={t('Copy prompt')}
              cls="is-xs"
              onClick={() => {
                void navigator.clipboard?.writeText(it.prompt).then(() => ui.toast(t('Copied')), () => undefined);
              }}
            />
          </div>
          {pr.draft && (
            <details className="flxst-draft">
              <summary>{t('Original draft')}</summary>
              <p>{pr.draft}</p>
            </details>
          )}
          {pr.refs.length > 0 && (
            <div className="flxst-view-refs">
              {pr.refs.map((r, i) => (
                <span key={i} className="flxst-ref is-sm" title={r.type === 'PERSON' ? '@' + (r.name ?? '') : roleLabel(t, r.role ?? '')}>
                  {r.thumb ? <img src={r.thumb} alt="" /> : <span className="flxst-av">{(r.name || '?').charAt(0)}</span>}
                </span>
              ))}
            </div>
          )}
          <dl className="flxst-meta">
            <dt>{t('Model')}</dt>
            <dd>{mod?.label ?? it.model}</dd>
            {it.provider && (
              <>
                <dt>{t('Provider')}</dt>
                <dd>{providerLabel(ws, it.provider)}</dd>
              </>
            )}
            {it.width > 0 && (
              <>
                <dt>{t('Size')}</dt>
                <dd>
                  {it.width}×{it.height}
                  {pr.resolution ? ' · ' + pr.resolution : ''}
                </dd>
              </>
            )}
            {!!pr.duration && (
              <>
                <dt>{t('Duration')}</dt>
                <dd>{pr.duration} s</dd>
              </>
            )}
            {it.cost > 0 && (
              <>
                <dt>{t('Cost')}</dt>
                <dd>{money(it.cost, locale)}</dd>
              </>
            )}
            <dt>{t('Date')}</dt>
            <dd>{ago(t, it.createdAt?.timestamp ?? 0, locale)}</dd>
            {it.ownerId !== ws.userId && (
              <>
                <dt>{t('Created by')}</dt>
                <dd>{it.ownerName}</dd>
              </>
            )}
          </dl>
          {it.error && (
            <p className="flxst-warn">
              <Ic n="alert" size={14} />
              {it.error}
            </p>
          )}
          <div className="flxst-view-acts">
            <Btn icon="star" label={it.favorite ? t('Favorited') : t('Favorite')} cls={'is-ghost' + (it.favorite ? ' is-on' : '')} onClick={() => p.onFav(it)} />
            <Btn
              icon="download"
              label={t('Download')}
              cls="is-ghost"
              onClick={() => {
                const a = document.createElement('a');
                a.href = url;
                a.download = (it.title || 'flx-studio-' + it.databaseId) + (it.kind === 'VIDEO' ? '.mp4' : '');
                document.body.appendChild(a);
                a.click();
                a.remove();
              }}
            />
            <Btn
              icon="repeat"
              label={t('Reuse settings')}
              cls="is-ghost"
              onClick={() => {
                p.onClose();
                p.onReuse(it);
              }}
            />
            {it.kind === 'IMAGE' && <Btn icon="layers" label={t('Use as reference')} cls="is-ghost" onClick={() => p.onAsRef(it)} />}
            {it.kind === 'IMAGE' && <Btn icon="brush" label={t('Edit area')} cls="is-ghost" title={mod?.mask ? '' : t('This model does not support area editing')} onClick={() => p.onEditArea(it)} />}
            {parent && it.kind === 'IMAGE' && <Btn icon="compare" label={compare ? t('Close comparison') : t('Compare with original')} cls="is-ghost" onClick={() => setCmp(!cmp)} />}
            {ws.canLibrary && (
              <Btn
                icon="library"
                label={it.attachmentId ? t('In library') : t('Add to Media Library')}
                cls="is-ghost"
                disabled={busy}
                onClick={() => {
                  setBusy(true);
                  void p.onLibrary(it).finally(() => setBusy(false));
                }}
              />
            )}
            <Btn icon="move" label={t('Move')} cls="is-ghost" onClick={(e) => p.onMove(e.currentTarget, [it.databaseId])} />
            <Btn icon="trash" label={t('Delete')} cls="is-ghost is-danger" onClick={() => void p.onDelete([it.databaseId])} />
          </div>
        </aside>
      </div>
    </Modal>
  );
}

/** Before / after slider. */
export function Compare({ before, after, w, h }: { before: string; after: string; w: number; h: number }) {
  const t = useT();
  const box = useRef<HTMLDivElement>(null);
  const [pct, setPct] = useState(50);
  const drag = useRef(false);
  const set = (x: number) => {
    const r = box.current?.getBoundingClientRect();
    if (r && r.width) setPct(Math.max(0, Math.min(1, (x - r.left) / r.width)) * 100);
  };
  return (
    <div
      ref={box}
      className="flxst-cmp"
      style={{ aspectRatio: `${w || 1} / ${h || 1}` }}
      onPointerDown={(e: RPointerEvent) => {
        drag.current = true;
        e.currentTarget.setPointerCapture(e.pointerId);
        set(e.clientX);
      }}
      onPointerMove={(e) => drag.current && set(e.clientX)}
      onPointerUp={() => (drag.current = false)}
    >
      <img src={after} alt="" draggable={false} />
      <div className="flxst-cmp-clip" style={{ clipPath: `inset(0 ${100 - pct}% 0 0)` }}>
        <img src={before} alt="" draggable={false} />
      </div>
      <div className="flxst-cmp-bar" style={{ left: pct + '%' }}>
        <span>
          <Ic n="chevL" size={14} />
          <Ic n="chevR" size={14} />
        </span>
      </div>
      <span className="flxst-cmp-l">{t('Before')}</span>
      <span className="flxst-cmp-r">{t('After')}</span>
    </div>
  );
}

type Tool = 'brush' | 'rect' | 'eraser';
interface Op {
  tool: Tool;
  size: number;
  pts: Array<[number, number]>;
  x: number;
  y: number;
  x2: number;
  y2: number;
}

const MAX_SIDE = 2048;

/** Area edit: paint the part to change; unpainted pixels stay. Exports a PNG mask (painted = transparent). */
export function MaskEditor({ item, onSubmit, onClose }: { item: Item; onSubmit: (mask: string, prompt: string, layers: string[]) => Promise<void>; onClose: () => void }) {
  const t = useT();
  const ui = useUi();
  const { client } = useGraphQL();
  const nw = item.width || 1024;
  const nh = item.height || 1024;
  const sc = Math.min(1, MAX_SIDE / Math.max(nw, nh));
  const cw = Math.round(nw * sc);
  const ch = Math.round(nh * sc);
  const cv = useRef<HTMLCanvasElement>(null);
  const cursor = useRef<HTMLDivElement>(null);
  const ops = useRef<Op[]>([]);
  const cur = useRef<Op | null>(null);
  const [count, setCount] = useState(0);
  const [tool, setTool] = useState<Tool>('brush');
  const [size, setSize] = useState(Math.round(Math.max(cw, ch) / 18));
  const [prompt, setPrompt] = useState('');
  const [busy, setBusy] = useState(false);
  const [enh, setEnh] = useState(false);

  const redraw = () => {
    const ctx = cv.current?.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, cw, ch);
    for (const o of cur.current ? [...ops.current, cur.current] : ops.current) {
      ctx.globalCompositeOperation = o.tool === 'eraser' ? 'destination-out' : 'source-over';
      ctx.fillStyle = ctx.strokeStyle = 'rgba(92,201,139,0.55)';
      if (o.tool === 'rect') ctx.fillRect(Math.min(o.x, o.x2), Math.min(o.y, o.y2), Math.abs(o.x2 - o.x), Math.abs(o.y2 - o.y));
      else {
        ctx.lineWidth = o.size;
        ctx.lineCap = ctx.lineJoin = 'round';
        ctx.beginPath();
        o.pts.forEach((pt, i) => (i ? ctx.lineTo(pt[0], pt[1]) : ctx.moveTo(pt[0], pt[1])));
        const first = o.pts[0];
        if (o.pts.length === 1 && first) ctx.lineTo(first[0] + 0.1, first[1]);
        ctx.stroke();
      }
    }
    ctx.globalCompositeOperation = 'source-over';
    setCount(ops.current.length);
  };
  const pos = (e: RPointerEvent<HTMLCanvasElement>): [number, number] => {
    const r = e.currentTarget.getBoundingClientRect();
    return [(e.clientX - r.left) * (cw / r.width), (e.clientY - r.top) * (ch / r.height)];
  };
  const end = () => {
    const c = cur.current;
    if (!c) return;
    if (c.tool !== 'rect' || (Math.abs(c.x2 - c.x) > 4 && Math.abs(c.y2 - c.y) > 4)) ops.current.push(c);
    cur.current = null;
    redraw();
  };
  const exportMask = (): string => {
    const out = document.createElement('canvas');
    out.width = nw;
    out.height = nh;
    const c = out.getContext('2d');
    if (!c || !cv.current) return '';
    c.fillStyle = '#000';
    c.fillRect(0, 0, nw, nh);
    c.globalCompositeOperation = 'destination-out';
    c.drawImage(cv.current, 0, 0, nw, nh);
    return out.toDataURL('image/png');
  };

  const send = async () => {
    if (!prompt.trim()) return ui.toast(t('Describe the change.'), 'error');
    setBusy(true);
    try {
      await onSubmit(exportMask(), prompt.trim(), ops.current.map((o) => o.tool));
      onClose();
    } catch (e) {
      ui.fail(e);
      setBusy(false);
    }
  };
  const enhance = async () => {
    if (!prompt.trim()) return ui.toast(t('Write a short draft first.'), 'error');
    setEnh(true);
    try {
      const r = await call(client.request(FlxStudioEnhance, { input: { prompt, mode: 'EDIT', refs: [] } }).then((x) => x.flxStudioEnhance));
      if (r.prompt) setPrompt(r.prompt);
    } catch (e) {
      ui.fail(e);
    } finally {
      setEnh(false);
    }
  };
  const tools: Array<[Tool, 'brush' | 'square' | 'eraser', string]> = [
    ['brush', 'brush', t('Brush')],
    ['rect', 'square', t('Rectangle')],
    ['eraser', 'eraser', t('Eraser')],
  ];
  return (
    <Modal title={t('Edit area')} wide sticky noFocus cls="flxst-me-modal" onClose={onClose}>
      <div className="flxst-me">
        <div className="flxst-me-wrap">
          <div className="flxst-me-stage" style={{ aspectRatio: `${nw} / ${nh}` }}>
            <img className="flxst-me-img" src={item.url ?? ''} alt="" draggable={false} />
            <canvas
              ref={cv}
              className="flxst-me-cv"
              width={cw}
              height={ch}
              onPointerDown={(e) => {
                e.preventDefault();
                e.currentTarget.setPointerCapture(e.pointerId);
                const [x, y] = pos(e);
                cur.current = { tool, size, pts: [[x, y]], x, y, x2: x, y2: y };
                redraw();
              }}
              onPointerMove={(e) => {
                const cu = cursor.current;
                if (cu) {
                  cu.style.width = cu.style.height = (size * e.currentTarget.getBoundingClientRect().width) / cw + 'px';
                  cu.style.left = e.nativeEvent.offsetX + 'px';
                  cu.style.top = e.nativeEvent.offsetY + 'px';
                  cu.hidden = tool === 'rect';
                }
                const c = cur.current;
                if (!c) return;
                const [x, y] = pos(e);
                if (c.tool === 'rect') {
                  c.x2 = x;
                  c.y2 = y;
                } else c.pts.push([x, y]);
                redraw();
              }}
              onPointerUp={end}
              onPointerCancel={end}
              onPointerLeave={() => cursor.current && (cursor.current.hidden = true)}
            />
            <div ref={cursor} className="flxst-me-cursor" hidden />
          </div>
        </div>
        <div className="flxst-me-side">
          <div className="flxst-field">
            <label>{t('Tool')}</label>
            <div className="flxst-seg">
              {tools.map(([id, icon, label]) => (
                <button key={id} type="button" className={'flxst-seg-b' + (tool === id ? ' is-on' : '')} title={label} onClick={() => setTool(id)}>
                  <Ic n={icon} size={16} />
                  <span>{label}</span>
                </button>
              ))}
            </div>
          </div>
          <div className="flxst-field">
            <label>{t('Brush size')}</label>
            <input type="range" min={4} max={Math.round(Math.max(cw, ch) / 4)} value={size} aria-label={t('Brush size')} onChange={(e) => setSize(+e.target.value)} />
          </div>
          <div className="flxst-row">
            <button
              type="button"
              className="flxst-ib"
              title={t('Undo')}
              aria-label={t('Undo')}
              disabled={!count}
              onClick={() => {
                ops.current.pop();
                redraw();
              }}
            >
              <Ic n="undo" size={18} />
            </button>
            <Btn
              label={t('Clear')}
              cls="is-ghost"
              disabled={!count}
              onClick={() => {
                ops.current = [];
                redraw();
              }}
            />
          </div>
          <p className="flxst-muted">{t('Paint the area to change. Unpainted areas are kept.')}</p>
          <div className="flxst-field">
            <label>{t('Change')}</label>
            <textarea className="flxst-input" rows={3} value={prompt} placeholder={t('What should change in the selected area? E.g. make the T-shirt green, change the text to "Plant a Tree"')} onChange={(e) => setPrompt(e.target.value)} />
          </div>
          <div className="flxst-row">
            <Btn icon="sparkle" label={t('Enhance')} cls="is-ghost" disabled={enh} onClick={() => void enhance()} />
            <span className="flxst-grow" />
            <Btn icon="send" label={t('Edit')} cls="is-primary" disabled={busy || !count} onClick={() => void send()} />
          </div>
        </div>
      </div>
    </Modal>
  );
}
