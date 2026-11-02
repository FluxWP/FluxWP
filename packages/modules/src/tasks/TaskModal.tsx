// Flux Tasks görev penceresi (WP assets/js/task.js karşılığı): özellikler, açıklama, alt görevler, toplantı gündemi,
// ekler, yorumlar ve etkinlik, yapay zekâ. Aynı sınıf adları, aynı davranış.
import { createContext, useContext, useEffect, useLayoutEffect, useReducer, useRef, useState, type ReactNode, type DragEvent as RDragEvent } from 'react';
import type { FlxgActivity, FlxgComment, FlxgField, FlxgFile, FlxgTask, FlxgTaskDetail, FlxgUser } from '@fluxwp/core';
import { useHost } from '../host';
import { between, norm, rank, store } from './engine';
import { tt } from './i18n';
import {
  ago, Avatar, bytes, cv, debounce, downloadFile, fileKind, FileImg, fileSrc, fmtDue, dueState, fullTime, Ic, pickDate, pickList, pickPeople, pickPriority,
  pickStatus, pickTags, PrioFlag, richText, TagChips, useYtg, type Ctx,
} from './ui';
import { builtinCols, Cell, editFieldPop, FieldView, fieldTypeIcon } from './views';
import { DndArea, useDragItem, useDropZone } from './dnd';

type Detail = FlxgTaskDetail;
type Child = Detail['children'][number];
type Pending = { file: File; preview?: string; id?: number; error?: string };
type Up = { key: string; name: string; preview: string };
type AiSt = { task: number; state: 'menu' | 'loading' | 'result' | 'error'; action?: string; hint?: string; label?: string; result?: { text?: string; html?: string; items?: string[] } | null; error?: string; applied?: string | null; undo?: (() => void) | null };
type SubCol = { key: string; name: string; w: string; field?: { key: string; id: number; ids: number[]; name: string; type: string; options: Record<string, unknown> } };
type SubOpts = { kind: string; key: string; title: string; head: string; ph: string; def: string[] };

/* Görev değişse de kalan bellek: açılmış görevler, gönderilmekte olan yorum/alt görevler, yüklemeler. */
const mem = {
  cache: new Map<number, Detail>(),
  inflight: {} as Record<number, { comments_list: FlxgComment[]; children: Child[] }>,
  uploads: {} as Record<number, Up[]>,
};
const inflight = (id: number) => (mem.inflight[id] ??= { comments_list: [], children: [] });
function mergeInflight(task: Detail) {
  const f = mem.inflight[task.id];
  if (!f) return;
  task.comments_list ??= [];
  task.children ??= [];
  for (const x of f.comments_list) if (!task.comments_list.some((y) => y._tmp === x._tmp)) task.comments_list.push(x);
  for (const x of f.children) if (!task.children.some((y) => y._tmp === x._tmp)) task.children.push(x);
}
function remember(task: Detail) {
  mem.cache.delete(task.id);
  mem.cache.set(task.id, task);
  if (mem.cache.size > 30) mem.cache.delete(mem.cache.keys().next().value!);
}
const clone = <T,>(x: T): T => JSON.parse(JSON.stringify(x)) as T;
const hasFiles = (ev: RDragEvent | DragEvent) => !!ev.dataTransfer && Array.from(ev.dataTransfer.types || []).includes('Files');
const cls = (...a: (string | false | null | undefined | 0)[]) => a.filter(Boolean).join(' ');
function plain(html?: string) {
  if (!html) return '';
  const doc = new DOMParser().parseFromString(String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|li|h\d)>/gi, '\n'), 'text/html');
  return (doc.body.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
}
function useMobile() {
  const [m, setM] = useState(() => typeof window !== 'undefined' && window.innerWidth <= 720);
  useEffect(() => {
    const f = () => setM(window.innerWidth <= 720);
    window.addEventListener('resize', f);
    return () => window.removeEventListener('resize', f);
  }, []);
  return m;
}
function autosize(el: HTMLTextAreaElement | null) {
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
}
function copyText(c: Ctx, s: string) {
  void navigator.clipboard?.writeText(s).then(() => c.ui.toast(tt('Kopyalandı')), () => c.ui.toast(s));
}
function openLightbox(c: Ctx, files: FlxgFile[], index: number) {
  if (!files.length) return;
  c.ui.lightbox = { files, index: Math.max(0, index) };
  c.ui.bump();
}

/* ---------------- Pencere durumu ---------------- */
interface DS {
  task: Detail | null;
  error: string;
  tab: 'details' | 'comments';
  editing: boolean;
  pend: Record<string, number>;
  saving: number;
  remoteStale: boolean;
  subPend: Record<number, number>;
  subOpen: Record<number, boolean>;
  agOpen: Record<number, boolean>;
  mtgCustom: boolean;
  ai: AiSt | null;
  pending: Pending[];
  mentions: { name: string; id: number }[];
  draft: string;
  draftFor: number;
  focusSubAdd: boolean;
  seq: number;
  composerText: () => string;
}
interface DCtx {
  c: Ctx;
  D: DS;
  render: () => void;
  load: (id: number) => Promise<void>;
  save: (patch: Record<string, unknown>) => Promise<FlxgTask | Detail>;
  close: () => void;
  canEdit: boolean;
  canComment: boolean;
  role: string;
  mobile: boolean;
  link: (id: number) => string;
  uploadTaskFiles: (task: Detail, list: File[], o?: { cover?: boolean; noReload?: boolean }) => Promise<FlxgFile[]>;
  subPatch: (taskId: number) => (id: number, patch: Record<string, unknown>) => Promise<unknown>;
}
const DrCtx = createContext<DCtx | null>(null);
const useDr = () => useContext(DrCtx)!;

/** Açık görev varsa pencereyi gösterir. */
export function TaskDrawer() {
  const c = useYtg();
  const id = c.e.S.openTask;
  return id ? <Drawer id={id} /> : null;
}

function Drawer({ id }: { id: number }) {
  const c = useYtg();
  const { e, ui } = c;
  const host = useHost();
  const mobile = useMobile();
  const [, force] = useReducer((x: number) => x + 1, 0);
  const ref = useRef<DS>({
    task: null, error: '', tab: 'details', editing: false, pend: {}, saving: 0, remoteStale: false, subPend: {}, subOpen: {}, agOpen: {}, mtgCustom: false,
    ai: null, pending: [], mentions: [], draft: '', draftFor: 0, focusSubAdd: false, seq: 0, composerText: () => '',
  });
  const D = ref.current;
  const [shown, setShown] = useState(false);
  const elRef = useRef<HTMLElement>(null);
  const close = () => e.openTask(0);

  const load = async (tid: number) => {
    const seq = ++D.seq;
    try {
      const r = await e.api.task(tid);
      if (seq !== D.seq || e.S.openTask !== tid) return;
      D.task = r.task;
      D.error = '';
      mergeInflight(D.task);
      remember(r.task);
      D.remoteStale = false;
      if (typeof r.unread === 'number' && r.unread !== e.S.unread) e.S.unread = r.unread;
      force();
    } catch (er) {
      if (seq !== D.seq) return;
      D.error = er instanceof Error ? er.message : String(er);
      force();
    }
  };

  useEffect(() => {
    D.tab = 'details';
    D.pending = [];
    D.mentions = [];
    D.error = '';
    const cached = mem.cache.get(id);
    D.task = cached ?? null;
    if (cached) mergeInflight(cached);
    force();
    void load(id);
    e.emit('open-task', id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);
  useEffect(() => {
    const raf = requestAnimationFrame(() => setShown(true));
    return () => cancelAnimationFrame(raf);
  }, []);
  useEffect(() => {
    const off = e.on('task-remote', () => {
        if (!D.task) return;
        if (D.editing || D.composerText().trim() || D.pending.length) {
          D.remoteStale = true;
          return;
        }
        void load(D.task.id);
      });
    return () => void off();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    const key = (ev: KeyboardEvent) => {
      if (ev.key !== 'Escape' || ui.pop || ui.modals.length || ui.lightbox) return;
      const a = document.activeElement as HTMLElement | null;
      if (a && (a.isContentEditable || /INPUT|TEXTAREA/.test(a.tagName))) {
        a.blur();
        return;
      }
      close();
    };
    document.addEventListener('keydown', key);
    return () => document.removeEventListener('keydown', key);
  });

  const task = D.task;
  const role = task?.role ?? 'view';
  const canEdit = rank(role) >= 3;
  const canComment = rank(role) >= 2;
  const page = !!task && e.isMeetingList(task.list);
  useEffect(() => {
    const root = elRef.current?.closest('.flxg');
    root?.classList.toggle('is-task-page', page && !mobile);
    return () => root?.classList.remove('is-task-page');
  }, [page, mobile]);

  const save = (patch: Record<string, unknown>) => {
    const t0 = D.task!;
    const tid = t0.id;
    const before = clone(t0);
    const keys: string[] = [];
    for (const k of Object.keys(patch)) {
      if (k === 'fields') keys.push(...Object.keys(patch.fields as object).map((f) => 'f' + f));
      else if (k !== 'detail') keys.push(k);
    }
    // İyimser arayüz: değişiklik hemen ekranda, kaydedilene kadar alan soluk; hata olursa geri alınır.
    for (const [k, v] of Object.entries(patch)) {
      if (k === 'fields') {
        t0.fields = { ...t0.fields };
        for (const [f, fv] of Object.entries(v as Record<string, unknown>)) {
          if (fv === null || fv === '') delete t0.fields[f];
          else t0.fields[f] = fv;
        }
      } else if (k === 'list') t0.list = Number(v);
      else (t0 as unknown as Record<string, unknown>)[k] = v;
    }
    for (const k of keys) D.pend[k] = (D.pend[k] ?? 0) + 1;
    const unpend = () => {
      for (const k of keys) {
        D.pend[k] = (D.pend[k] ?? 1) - 1;
        if (D.pend[k]! <= 0) delete D.pend[k];
      }
    };
    D.saving++;
    e.keepTask(tid);
    force();
    return e.api.update(tid, { ...patch, detail: true }).then(
      (r) => {
        D.saving = Math.max(0, D.saving - 1);
        unpend();
        const nt = r.task as Detail;
        if (D.task && D.task.id === tid && nt.comments_list) {
          D.task = nt;
          mergeInflight(nt);
          remember(nt);
        }
        e.upsertTask(clone(nt) as unknown as FlxgTask & Record<string, unknown>);
        e.bump();
        force();
        return nt;
      },
      (er) => {
        D.saving = Math.max(0, D.saving - 1);
        unpend();
        if (D.task && D.task.id === tid) D.task = before;
        force();
        ui.fail(er);
        throw er;
      },
    );
  };

  const uploadTaskFiles: DCtx['uploadTaskFiles'] = async (t0, list, o = {}) => {
    const max = e.S.config.maxUpload;
    list = list.filter((f) => {
      if (f.size > max) {
        ui.toast(tt('{n} çok büyük (en fazla {s}).', { n: f.name, s: bytes(max) }), { error: true });
        return false;
      }
      return true;
    });
    if (!list.length) return [];
    const tid = t0.id;
    const ups = (mem.uploads[tid] ??= []);
    const mine = list.map((file, i): Up => ({ key: 'u' + Date.now() + '-' + i, name: file.name || tt('Dosya'), preview: /^image\//.test(file.type) ? URL.createObjectURL(file) : '' }));
    ups.push(...mine);
    if (!o.noReload) force();
    const res = await Promise.all(
      list.map((file, i) =>
        e.api.upload(file, { task: tid, context: 'task', ...(o.cover ? { cover: 1 } : {}) }).then(
          (f) => {
            mem.uploads[tid] = (mem.uploads[tid] ?? []).filter((x) => x !== mine[i]);
            if (D.task?.id === tid && !D.task.attachments.some((x) => x.id === f.id)) D.task.attachments.push(f);
            return f;
          },
          (er) => {
            mem.uploads[tid] = (mem.uploads[tid] ?? []).filter((x) => x !== mine[i]);
            ui.fail(er);
            return null;
          },
        ),
      ),
    );
    if (!o.noReload && D.task?.id === tid) void load(tid);
    return res.filter(Boolean) as FlxgFile[];
  };

  /** Alt görevde değişiklik: tabloda hemen görünür, kaydedilince detay tazelenir. */
  const subPatch = (taskId: number) => (sid: number, patch: Record<string, unknown>) => {
    const objs = D.task ? [...(D.task.children ?? []), ...(D.task.subtree ?? [])] : [];
    for (const o of objs) {
      if (o.id !== sid) continue;
      for (const [k, v] of Object.entries(patch)) {
        if (k === 'fields') o.fields = { ...o.fields, ...(v as object) };
        else if (k === 'position') o.pos = Number(v);
        else (o as unknown as Record<string, unknown>)[k] = v;
      }
    }
    D.subPend[sid] = (D.subPend[sid] ?? 0) + 1;
    const un = () => {
      D.subPend[sid]!--;
      if (D.subPend[sid]! <= 0) delete D.subPend[sid];
    };
    force();
    return e.patchTask(sid, patch, { silent: true }).then(
      (r) => {
        un();
        if (D.task?.id === taskId) void load(taskId);
        return r;
      },
      () => {
        un();
        if (D.task?.id === taskId) void load(taskId);
      },
    );
  };

  const link = (tid: number) => (host.siteUrl ? host.siteUrl.replace(/\/+$/, '') + '/wp-admin/admin.php?page=flx-tasks' : '') + '#/task/' + tid;
  const ctx: DCtx = { c, D, render: force, load, save, close, canEdit, canComment, role, mobile, link, uploadTaskFiles, subPatch };
  const sideOpen = store.get('task-side') !== '0';

  let body: ReactNode;
  if (!task) {
    const known = e.S.tasks[id];
    body = D.error ? (
      <div className="flxg-empty">
        <p>{D.error}</p>
        <button type="button" className="flxg-btn" onClick={close}>
          {tt('Kapat')}
        </button>
      </div>
    ) : (
      <div className="flxg-dr-main">
        <div className="flxg-dr-sticky">
          <div className="flxg-dr-title" style={{ minHeight: 40 }}>
            {known?.title ?? <span className="flxg-sk" style={{ display: 'block', width: '60%', height: 22 }} />}
          </div>
        </div>
        {['40%', '70%', '55%', '85%', '62%'].map((w, i) => (
          <span key={i} className="flxg-sk" style={{ display: 'block', width: w, height: 14, margin: '14px 0' }} />
        ))}
      </div>
    );
  } else if (mobile) {
    body = (
      <>
        <div className="flxg-seg flxg-dr-tabs">
          <button type="button" className={D.tab === 'details' ? 'is-on' : ''} onClick={() => ((D.tab = 'details'), force())}>
            {tt('Detaylar')}
          </button>
          <button type="button" className={D.tab === 'comments' ? 'is-on' : ''} onClick={() => ((D.tab = 'comments'), force())}>
            {tt('Yorumlar')}
            {task.comments_list.length > 0 && <span className="flxg-badge">{task.comments_list.length}</span>}
          </button>
        </div>
        {D.tab === 'details' ? <Main task={task} /> : <Side task={task} />}
      </>
    );
  } else {
    body = (
      <>
        <Main task={task} />
        {sideOpen ? <Side task={task} /> : <SideRail task={task} />}
      </>
    );
  }

  return (
    <DrCtx.Provider value={ctx}>
      <div className="flxg-drawer-shade" onClick={close} />
      <aside
        ref={elRef}
        className={cls('flxg-drawer', shown && 'is-open', mobile && 'is-mobile', page && 'is-page', !mobile && !sideOpen && 'is-side-closed', D.saving > 0 && 'is-saving')}
        role="dialog"
        aria-modal="true"
        aria-label={tt('Görev')}
      >
        <header className="flxg-dr-head">{task && <Head task={task} />}</header>
        <div className="flxg-dr-body">{body}</div>
      </aside>
    </DrCtx.Provider>
  );
}

/* ---------------- Başlık şeridi ---------------- */
function Head({ task }: { task: Detail }) {
  const d = useDr();
  const { c, canEdit, save, close, D } = d;
  const { e, ui } = c;
  const l = e.list(task.list);
  const sp = l ? e.space(l.space_id) : null;
  const more = (anchor: HTMLElement) =>
    ui.menu(anchor, [
      { label: tt('Bağlantıyı kopyala'), icon: 'link', onClick: () => copyText(c, d.link(task.id)) },
      canEdit && {
        label: tt('Başka listeye taşı'),
        icon: 'move',
        onClick: () => pickList(c, anchor, (lid) => void save({ list: lid }).then(() => ui.toast(tt('Görev taşındı'))), { current: task.list }),
      },
      canEdit && {
        label: tt('Alt görev ekle'),
        icon: 'subtask',
        onClick: () => {
          D.tab = 'details';
          D.focusSubAdd = true;
          d.render();
        },
      },
      canEdit && {
        label: tt('Kopyasını oluştur'),
        icon: 'copy',
        onClick: () => {
          const x = task;
          e.createTask({ list: x.list, title: x.title + ' ' + tt('(kopya)'), description: x.description, status: x.status, priority: x.priority, start: x.start, due: x.due, assignees: x.assignees, tags: x.tags, fields: x.fields, parent: x.parent || 0 }).then((n) => {
            ui.toast(tt('Kopya oluşturuldu'));
            e.openTask(n.id);
          }, ui.fail);
        },
      },
      canEdit && {
        label: task.archived ? tt('Arşivden çıkar') : tt('Arşivle'),
        icon: 'archive',
        onClick: () =>
          void save({ archived: !task.archived }).then(() => {
            if (task.archived) {
              delete e.S.tasks[task.id];
              e.bump();
            }
          }),
      },
      canEdit && { sep: true },
      canEdit && {
        label: tt('Görevi sil'),
        icon: 'trash',
        danger: true,
        onClick: () =>
          void ui.confirm(tt('Görevi sil'), tt('"{n}" görevi, yorum ve dosyalarıyla birlikte kalıcı olarak silinecek.', { n: task.title }), tt('Sil'), true).then((ok) => {
            if (ok)
              e.deleteTask(task.id).then(() => {
                close();
                ui.toast(tt('Görev silindi'));
              }, ui.fail);
          }),
      },
    ]);
  const isSub = task.kind === 'agenda' || task.kind === 'decision';
  return (
    <>
      {task.parentInfo && (
        <button type="button" className="flxg-back-btn" title={task.parentInfo.title} onClick={() => e.openTask(task.parentInfo!.id)}>
          <Ic n="back" s={16} />
          <span className="flxg-back-k">{isSub ? tt('Toplantıya dön') : tt('Üst göreve dön')}</span>
          <span className="flxg-back-t">{task.parentInfo.title}</span>
        </button>
      )}
      <div className="flxg-dr-crumbs">
        {sp && <span>{sp.name}</span>}
        {sp && <Ic n="chevR" s={13} />}
        {l && (
          <a
            href={'#/list/' + l.id}
            onClick={(ev) => {
              ev.preventDefault();
              void e.go({ page: 'list', id: l.id });
            }}
          >
            <span className="flxg-dot" style={{ background: l.color }} />
            {l.name}
          </a>
        )}
        <button type="button" className="flxg-idchip" title={tt('Bağlantıyı kopyala')} onClick={() => copyText(c, d.link(task.id))}>
          #{task.id}
        </button>
      </div>
      <div className="flxg-dr-actions">
        <button
          type="button"
          className={cls('flxg-icon-btn', task.watching && 'is-on')}
          title={task.watching ? tt('Takibi bırak') : tt('Takip et (bildirim al)')}
          onClick={() =>
            e.api.watch(task.id, !task.watching).then((r) => {
              task.watchers = r.watchers;
              task.watching = !task.watching;
              d.render();
              ui.toast(task.watching ? tt('Bu görevin bildirimlerini alacaksınız') : tt('Bu görevin bildirimleri kapatıldı'));
            }, ui.fail)
          }
        >
          <Ic n="bell" s={17} />
          <span className="flxg-watch-n">{task.watchers.length}</span>
        </button>
        <button type="button" className="flxg-icon-btn" title={tt('Diğer')} onClick={(ev) => more(ev.currentTarget)}>
          <Ic n="dots" s={18} />
        </button>
        <button type="button" className="flxg-icon-btn" title={tt('Kapat')} onClick={close}>
          <Ic n="x" s={19} />
        </button>
      </div>
    </>
  );
}

/* ---------------- Özellik satırı ---------------- */
function Attr({ icon, ftype, label, k, onEdit, title, children }: { icon?: string; ftype?: string; label: string; k?: string; onEdit?: ((a: HTMLElement) => void) | null; title?: string; children: ReactNode }) {
  const { D } = useDr();
  const pending = k && k.split(' ').some((x) => D.pend[x]);
  return (
    <div className="flxg-attr" title={title}>
      <div className="flxg-attr-label">
        {ftype ? <span className="flxg-ftype">{fieldTypeIcon(ftype)}</span> : <Ic n={icon!} s={15} />}
        <span>{label}</span>
      </div>
      <div
        className={cls('flxg-attr-val', onEdit && 'is-edit', pending && 'is-pending')}
        data-k={k}
        tabIndex={onEdit ? 0 : undefined}
        onClick={onEdit ? (ev) => !(ev.target as HTMLElement).closest('a') && onEdit(ev.currentTarget) : undefined}
        onKeyDown={onEdit ? (ev) => ev.key === 'Enter' && onEdit(ev.currentTarget) : undefined}
      >
        {children}
      </div>
    </div>
  );
}
const ATTR_MIN = 4;
function LimitAttrs({ rows, cls: extra }: { rows: ReactNode[]; cls?: string }) {
  const { c, render } = useDr();
  const all = !!c.e.pref('attrs-all');
  const over = rows.length > ATTR_MIN;
  return (
    <div className={cls('flxg-attrs', extra)}>
      {over && !all ? rows.slice(0, ATTR_MIN) : rows}
      {over && (
        <button type="button" className="flxg-attrs-more" onClick={() => (c.e.setPref('attrs-all', all ? null : 1), render())}>
          <Ic n={all ? 'chevU' : 'chevD'} s={14} />
          {all ? tt('Daha az göster') : tt('{n} alan daha', { n: rows.length - ATTR_MIN })}
        </button>
      )}
    </div>
  );
}
function People({ ids, empty }: { ids: number[]; empty: string }) {
  const { c } = useDr();
  if (!ids.length) return <span className="flxg-muted">{empty}</span>;
  return (
    <span className="flxg-people">
      {ids.map((id) => {
        const u = c.e.S.users[id];
        return (
          <span key={id} className="flxg-person">
            <Avatar u={u} size={22} />
            <span>{u ? u.name : '?'}</span>
          </span>
        );
      })}
    </span>
  );
}

/* ---------------- Katlanan bölüm ---------------- */
function Sec({ k, cls: boxCls, head, headCls, children }: { k: string; cls: string; head: ReactNode; headCls?: string; children?: ReactNode }) {
  const { c, render } = useDr();
  const map = c.e.pref<Record<string, number>>('sec-col') ?? {};
  const open = !map[k];
  return (
    <div className={cls(boxCls, 'flxg-sec', !open && 'is-collapsed')}>
      <h3
        className={cls('flxg-dr-h', 'is-tog', headCls)}
        onClick={(ev) => {
          if ((ev.target as HTMLElement).closest('button:not(.flxg-sec-tog), a, input, label')) return;
          const m = { ...map };
          if (open) m[k] = 1;
          else delete m[k];
          c.e.setPref('sec-col', m);
          render();
        }}
      >
        <button type="button" className="flxg-sec-tog" aria-expanded={open} title={open ? tt('Daralt') : tt('Genişlet')}>
          <Ic n="chevD" s={15} />
        </button>
        {head}
      </h3>
      {children}
    </div>
  );
}

/* ---------------- Ana sütun ---------------- */
function Main({ task }: { task: Detail }) {
  const d = useDr();
  const { c, canEdit: edit, save, D } = d;
  const { e } = c;
  const l = e.list(task.list);
  const mtg = e.isMeetingList(task.list) && (task.kind || 'task') === 'task' && !task.parent;
  const mainRef = useRef<HTMLDivElement>(null);
  const dd = useRef(0);
  const [dropping, setDropping] = useState(false);
  const titleRef = useRef<HTMLTextAreaElement>(null);
  const st = e.status(task);
  const doneKey = l?.statuses.find((s) => s.type === 'done')?.key;

  useLayoutEffect(() => {
    const t = titleRef.current;
    if (t && document.activeElement !== t) t.value = task.title;
    autosize(t);
  });
  useEffect(() => {
    if (!D.focusSubAdd) return;
    D.focusSubAdd = false;
    setTimeout(() => {
      const i = mainRef.current?.querySelector<HTMLInputElement>('.flxg-subtable-wrap:not(.is-decision) .flxg-sub-add input');
      i?.scrollIntoView({ block: 'center' });
      i?.focus();
    }, 30);
  });
  // Liste dışı kapsamda alanlar yüklenmemiş olabilir.
  const fieldsAsked = useRef(0);
  useEffect(() => {
    if (e.fieldsFor(task.list).length || fieldsAsked.current === task.list) return;
    fieldsAsked.current = task.list;
    e.api.list(task.list).then((r) => {
      const known = new Set(e.S.fields.map((f) => f.id));
      const add = (r.fields ?? []).filter((f) => !known.has(f.id));
      if (add.length) {
        e.S.fields = [...e.S.fields, ...add];
        e.bump();
      }
    }, () => {});
  }, [task.list, e]);

  const dropProps = edit
    ? {
        onDragEnter: (ev: RDragEvent) => {
          if (!hasFiles(ev)) return;
          ev.preventDefault();
          dd.current++;
          setDropping(true);
        },
        onDragOver: (ev: RDragEvent) => hasFiles(ev) && ev.preventDefault(),
        onDragLeave: () => {
          dd.current = Math.max(0, dd.current - 1);
          if (!dd.current) setDropping(false);
        },
        onDrop: (ev: RDragEvent) => {
          dd.current = 0;
          setDropping(false);
          if (!hasFiles(ev)) return;
          ev.preventDefault();
          void d.uploadTaskFiles(task, Array.from(ev.dataTransfer.files || []));
        },
      }
    : {};

  const coverPick = (anchor: HTMLElement) => {
    const imgs = task.attachments.filter((f) => f.image);
    c.ui.openPop(
      anchor,
      <div className="flxg-pick">
        <div className="flxg-menu-head">{tt('Kapak görseli')}</div>
        {imgs.length ? (
          <div className="flxg-cover-grid">
            {imgs.map((f) => (
              <button key={f.id} type="button" className={cls('flxg-cover-opt', task.cover === f.id && 'is-on')} onClick={() => (c.ui.closePop(), void save({ cover: f.id }))}>
                <FileImg id={f.id} size="thumb" alt={f.name} />
              </button>
            ))}
          </div>
        ) : (
          <div className="flxg-empty-sm">{tt('Görevde henüz görsel yok.')}</div>
        )}
        <div className="flxg-row-btns">
          <label className="flxg-btn is-sm">
            <Ic n="upload" s={14} />
            {tt('Yeni görsel yükle')}
            <input
              type="file"
              accept="image/*"
              hidden
              onChange={(ev) => {
                c.ui.closePop();
                void d.uploadTaskFiles(task, Array.from(ev.target.files ?? []).slice(0, 1), { cover: true });
              }}
            />
          </label>
        </div>
      </div>,
    );
  };

  const statusbar = (
    <div className="flxg-dr-statusbar">
      <button
        type="button"
        className={cls('flxg-status', 'is-btn', D.pend.status && 'is-pending')}
        style={cv(st.color)}
        data-k="status"
        disabled={!edit}
        onClick={(ev) => pickStatus(c, ev.currentTarget, task.list, task.status, (k) => void save({ status: k }))}
      >
        {st.label}
        {edit && <Ic n="chevD" s={14} />}
      </button>
      {edit && doneKey && st.type !== 'done' && (
        <button type="button" className="flxg-btn is-sm is-done-btn" onClick={() => void save({ status: doneKey })}>
          <Ic n="check" s={14} />
          {mtg ? tt('Yapıldı') : tt('Tamamla')}
        </button>
      )}
      {st.type === 'done' && task.completed && !mtg && <span className="flxg-muted">{tt('Tamamlandı: {d}', { d: ago(task.completed) })}</span>}
      {!edit && (
        <span className="flxg-readonly">
          <Ic n="lock" s={13} />
          {rank(d.role) >= 2 ? tt('Yalnız yorum yapabilirsiniz') : tt('Yalnız görüntüleme')}
        </span>
      )}
      {mtg && <span className="flxg-grow" />}
      {mtg && (
        <button type="button" className="flxg-btn is-sm is-ghost" title={tt('Toplantı raporunu yazdır ya da PDF olarak kaydet')} onClick={() => meetingReport(c, task)}>
          <Ic n="printer" s={14} />
          <span>{tt('PDF rapor')}</span>
        </button>
      )}
      {mtg && (
        <button type="button" className={cls('flxg-icon-btn', 'is-sm', D.mtgCustom && 'is-on')} title={D.mtgCustom ? tt('Özelleştirmeyi gizle') : tt('Ekranı özelleştir')} onClick={() => ((D.mtgCustom = !D.mtgCustom), d.render())}>
          <Ic n="dots" s={16} />
        </button>
      )}
    </div>
  );

  const dates = (
    <span className="flxg-dates">
      <button type="button" className="flxg-date-btn" disabled={!edit} onClick={(ev) => (ev.stopPropagation(), pickDate(c, ev.currentTarget, task.start, (v) => void save({ start: v }), { label: tt('Başlangıç') }))}>
        {task.start ? fmtDue(task.start) : tt('Başlangıç')}
      </button>
      <Ic n="chevR" s={13} />
      <button
        type="button"
        className={cls('flxg-date-btn', dueState(task.due, st.type === 'done') && 'is-' + dueState(task.due, st.type === 'done'))}
        disabled={!edit}
        onClick={(ev) => (ev.stopPropagation(), pickDate(c, ev.currentTarget, task.due, (v) => void save({ due: v }), { label: tt('Bitiş') }))}
      >
        {task.due ? fmtDue(task.due) : tt('Bitiş')}
      </button>
    </span>
  );
  const peopleEdit = edit ? (a: HTMLElement) => pickPeople(c, a, task.assignees, debounce((ids: number[]) => void save({ assignees: ids }), 700)) : null;
  const tagsEdit = edit ? (a: HTMLElement) => pickTags(c, a, l ? l.space_id : 0, task.tags, debounce((ids: number[]) => void save({ tags: ids }), 700)) : null;
  const prioEdit = edit ? (a: HTMLElement) => pickPriority(c, a, task.priority, (n) => void save({ priority: n })) : null;

  return (
    <div ref={mainRef} className={cls('flxg-dr-main', mtg && 'is-meeting', dropping && 'is-dropping')} {...dropProps}>
      {task.cover > 0 && (
        <div className="flxg-dr-cover">
          <FileImg
            id={task.cover}
            size="thumb"
            onClick={() => {
              const imgs = task.attachments.filter((f) => f.image);
              openLightbox(c, imgs, imgs.findIndex((f) => f.id === task.cover));
            }}
          />
          {edit && (
            <div className="flxg-dr-cover-btns">
              <button type="button" className="flxg-btn is-sm" onClick={(ev) => coverPick(ev.currentTarget)}>
                <Ic n="image" s={14} />
                {tt('Kapağı değiştir')}
              </button>
              <button type="button" className="flxg-btn is-sm" onClick={() => void save({ cover: 0 })}>
                {tt('Kaldır')}
              </button>
            </div>
          )}
        </div>
      )}
      <div className="flxg-dr-sticky">
        <textarea
          ref={titleRef}
          className={cls('flxg-dr-title', D.pend.title && 'is-pending')}
          rows={1}
          readOnly={!edit}
          aria-label={tt('Görev adı')}
          defaultValue={task.title}
          onInput={(ev) => autosize(ev.currentTarget)}
          onFocus={() => (D.editing = true)}
          onKeyDown={(ev) => {
            if (ev.key === 'Enter') {
              ev.preventDefault();
              ev.currentTarget.blur();
            }
          }}
          onBlur={(ev) => {
            D.editing = false;
            const v = ev.currentTarget.value.replace(/\s+/g, ' ').trim();
            if (v && v !== task.title) void save({ title: v });
            else ev.currentTarget.value = task.title;
          }}
        />
        {statusbar}
      </div>
      {mtg ? (
        <MeetingBody task={task} />
      ) : (
        <>
          <LimitAttrs
            rows={[
              <Attr key="a" icon="users" label={tt('Atananlar')} k="assignees" onEdit={peopleEdit}>
                <People ids={task.assignees} empty={tt('Boş')} />
              </Attr>,
              <Attr key="d" icon="calendar" label={tt('Tarihler')} k="start due">
                {dates}
              </Attr>,
              <Attr key="p" icon="flag" label={tt('Öncelik')} k="priority" onEdit={prioEdit}>
                {task.priority ? <PrioFlag n={task.priority} label /> : <span className="flxg-muted">{tt('Boş')}</span>}
              </Attr>,
              <Attr key="t" icon="tag" label={tt('Etiketler')} k="tags" onEdit={tagsEdit}>
                {task.tags.length ? <TagChips ids={task.tags} /> : <span className="flxg-muted">{tt('Boş')}</span>}
              </Attr>,
              ...e.fieldsFor(task.list).map((f) => <FieldRow key={'f' + f.id} task={task} f={f} />),
            ]}
          />
          <Sec
            k="desc"
            cls="flxg-sec-box"
            headCls="has-tools"
            head={
              <>
                <span>{tt('Açıklama')}</span>
                {e.S.me?.can.ai && d.canComment && (
                  <button
                    type="button"
                    className={cls('flxg-ai-btn', D.ai?.task === task.id && 'is-on')}
                    onClick={() => {
                      D.ai = D.ai?.task === task.id ? null : { task: task.id, state: 'menu' };
                      d.render();
                    }}
                  >
                    <Ic n="sparkle" s={14} />
                    <span>{tt('Yapay zekâ')}</span>
                  </button>
                )}
              </>
            }
          >
            <div className="flxg-ai-inline-wrap">{D.ai?.task === task.id && <AiPanel task={task} />}</div>
            <DescEditor task={task} />
          </Sec>
          <SubTable task={task} />
          <FilesSection task={task} />
        </>
      )}
    </div>
  );
}

/* ---------------- Özel alan satırı ---------------- */
function FieldRow({ task, f }: { task: Detail; f: FlxgField }) {
  const d = useDr();
  const { c, canEdit: edit, save } = d;
  const v = task.fields?.[f.id];
  const opts = (f.options ?? {}) as Record<string, unknown>;
  if (f.type === 'file') {
    const ids = Array.isArray(v) ? (v as number[]) : [];
    const fl = task.attachments.filter((x) => ids.includes(x.id));
    return (
      <Attr ftype={f.type} label={f.name} k={'f' + f.id}>
        <div className="flxg-field-files">
          {fl.map((x) => (
            <FileChip key={x.id} f={x} task={task} onRemove={() => void save({ fields: { [f.id]: ids.filter((z) => z !== x.id) } })} />
          ))}
          {edit && (
            <label className="flxg-chip-btn">
              <Ic n="upload" s={14} />
              {tt('Dosya ekle')}
              <input
                type="file"
                multiple
                hidden
                onChange={(ev) => {
                  const list = Array.from(ev.target.files ?? []);
                  ev.target.value = '';
                  Promise.all(list.map((file) => c.e.api.upload(file, { task: task.id, context: 'pending' })))
                    .then((up) => save({ fields: { [f.id]: [...ids, ...up.map((u) => u.id)] } }))
                    .catch(c.ui.fail);
                }}
              />
            </label>
          )}
        </div>
      </Attr>
    );
  }
  if (f.type === 'checkbox') {
    return (
      <Attr ftype={f.type} label={f.name} k={'f' + f.id}>
        <label className="flxg-check-inline">
          <input type="checkbox" checked={!!v} disabled={!edit} onChange={(ev) => void save({ fields: { [f.id]: ev.target.checked } })} />
        </label>
      </Attr>
    );
  }
  const view = <FieldView field={f} v={v} />;
  const empty = v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length);
  return (
    <Attr
      ftype={f.type}
      label={f.name}
      k={'f' + f.id}
      title={(opts.hint as string) || undefined}
      onEdit={edit ? (a) => editFieldPop(c, a, task, { id: f.id, name: f.name, type: f.type, options: opts }, () => void d.load(task.id), (id, p) => save(p).then((r) => r)) : null}
    >
      {empty ? <span className="flxg-muted">{tt('Boş')}</span> : view}
    </Attr>
  );
}

/* ---------------- Açıklama düzenleyici ---------------- */
function DescEditor({ task }: { task: Detail }) {
  const d = useDr();
  const { c, canEdit: edit, save, D } = d;
  const host = useHost();
  const ed = useRef<HTMLDivElement>(null);
  const original = useRef('');
  const [focus, setFocus] = useState(false);

  const clean = () => {
    const cl = ed.current!.cloneNode(true) as HTMLElement;
    cl.querySelectorAll('img').forEach((img) => {
      if (!img.getAttribute('data-file')) {
        img.remove();
        return;
      }
      img.removeAttribute('src');
      img.removeAttribute('class');
      img.removeAttribute('style');
    });
    return cl.innerHTML.trim();
  };
  // Dışarıdan gelen açıklama (yazılmıyorsa) içeriğe yerleşir; görseller belirteçle blob olarak çekilir.
  useLayoutEffect(() => {
    const el = ed.current!;
    if (document.activeElement === el) return;
    const html = task.description || '';
    if (html === original.current && el.innerHTML) return;
    el.innerHTML = html;
    original.current = clean();
    el.querySelectorAll<HTMLImageElement>('img[data-file]').forEach((img) => {
      img.alt = img.alt || '';
      fileSrc(c.e, host.scope, Number(img.getAttribute('data-file'))).then((s) => (img.src = s), () => {});
    });
  });
  const commit = () => {
    const el = ed.current;
    if (!el) return;
    el.querySelectorAll('ul[data-checklist] > li').forEach((li) => !li.hasAttribute('data-checked') && li.setAttribute('data-checked', '0'));
    let html = clean();
    if (html === '<br>' || html === '<p><br></p>') html = '';
    if (html === original.current) return;
    original.current = html;
    void save({ description: html });
  };
  const cmd = (name: string, v?: string) => {
    ed.current!.focus();
    document.execCommand(name, false, v);
  };
  const insertFiles = (files: File[]) => {
    const imgs = files.filter((f) => /^image\//.test(f.type));
    const others = files.filter((f) => !/^image\//.test(f.type));
    if (others.length) void d.uploadTaskFiles(task, others);
    for (const file of imgs) {
      const img = document.createElement('img');
      img.className = 'is-uploading';
      img.src = URL.createObjectURL(file);
      img.alt = file.name || '';
      const sel = window.getSelection();
      if (sel && sel.rangeCount && ed.current!.contains(sel.anchorNode)) {
        const rg = sel.getRangeAt(0);
        rg.deleteContents();
        rg.insertNode(img);
        rg.setStartAfter(img);
        rg.collapse(true);
        sel.removeAllRanges();
        sel.addRange(rg);
      } else ed.current!.appendChild(img);
      void d.uploadTaskFiles(task, [file], { noReload: true }).then((up) => {
        const f = up[0];
        if (!f) {
          img.remove();
          return;
        }
        img.setAttribute('data-file', String(f.id));
        img.classList.remove('is-uploading');
        if (D.task?.id === task.id) commit();
      });
    }
  };
  const tools: [string, string, string][] = [
    ['bold', 'bold', tt('Kalın')],
    ['italic', 'italic', tt('İtalik')],
    ['heading', 'formatBlock:h3', tt('Başlık')],
    ['ul', 'insertUnorderedList', tt('Madde listesi')],
    ['ol', 'insertOrderedList', tt('Numaralı liste')],
    ['checklist', 'checklist', tt('Kontrol listesi')],
    ['quote', 'formatBlock:blockquote', tt('Alıntı')],
    ['code', 'formatBlock:pre', tt('Kod')],
    ['link', 'link', tt('Bağlantı')],
  ];
  const tool = (b: string) => {
    if (b === 'link') {
      void c.ui.prompt(tt('Bağlantı adresi'), 'https://').then((url) => url && cmd('createLink', url));
      return;
    }
    if (b === 'checklist') {
      cmd('insertUnorderedList');
      const node = window.getSelection()?.anchorNode;
      const ul = node && (node.nodeType === 1 ? (node as Element) : node.parentElement)?.closest('ul');
      if (ul) {
        ul.setAttribute('data-checklist', '1');
        ul.querySelectorAll('li').forEach((li) => !li.hasAttribute('data-checked') && li.setAttribute('data-checked', '0'));
      }
      return;
    }
    if (b.startsWith('formatBlock:')) {
      const tag = b.split(':')[1]!;
      const cur = document.queryCommandValue('formatBlock');
      cmd('formatBlock', cur && cur.toLowerCase() === tag ? 'p' : tag);
      return;
    }
    cmd(b);
  };
  return (
    <div className={cls('flxg-desc', focus && 'is-focus')}>
      {edit && (
        <div className="flxg-desc-tb">
          {tools.map(([ic, b, label]) => (
            <button key={ic} type="button" className="flxg-icon-btn is-sm" title={label} onMouseDown={(ev) => (ev.preventDefault(), tool(b))}>
              <Ic n={ic} s={15} />
            </button>
          ))}
        </div>
      )}
      <div
        ref={ed}
        className={cls('flxg-desc-ed', 'flxg-prose', D.pend.description && 'is-pending')}
        contentEditable={edit}
        suppressContentEditableWarning
        data-k="description"
        data-ph={edit ? tt('Açıklama ekleyin… (liste, kontrol listesi, bağlantı)') : ''}
        onFocus={() => ((D.editing = true), setFocus(true))}
        onBlur={() => {
          D.editing = false;
          setFocus(false);
          commit();
        }}
        onClick={(ev) => {
          const li = (ev.target as HTMLElement).closest('ul[data-checklist] > li');
          if (!li || !edit) return;
          const r = li.getBoundingClientRect();
          if (ev.clientX - r.left > 26) return;
          li.setAttribute('data-checked', li.getAttribute('data-checked') === '1' ? '0' : '1');
          commit();
        }}
        onDragOver={(ev) => {
          if (edit && hasFiles(ev)) {
            ev.preventDefault();
            ev.stopPropagation();
          }
        }}
        onDrop={(ev) => {
          if (!edit || !hasFiles(ev)) return;
          ev.preventDefault();
          ev.stopPropagation();
          const doc = document as Document & { caretRangeFromPoint?: (x: number, y: number) => Range | null };
          const r = doc.caretRangeFromPoint?.(ev.clientX, ev.clientY);
          if (r && ed.current!.contains(r.startContainer)) {
            const sl = window.getSelection()!;
            sl.removeAllRanges();
            sl.addRange(r);
          }
          insertFiles(Array.from(ev.dataTransfer.files || []));
        }}
        onPaste={(ev) => {
          const pf = Array.from(ev.clipboardData?.files ?? []);
          if (pf.length && edit) {
            ev.preventDefault();
            insertFiles(pf.map((f, i) => (f.name && f.name !== 'image.png' ? f : new File([f], 'gorsel-' + Date.now() + '-' + i + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }))));
            return;
          }
          // Biçimli yapıştırmada yalnız metin.
          if (ev.clipboardData?.getData('text/html')) {
            ev.preventDefault();
            document.execCommand('insertText', false, ev.clipboardData.getData('text/plain'));
          }
        }}
      />
    </div>
  );
}

/* ---------------- Alt görevler tablosu ---------------- */
const SUB_DEFAULT = ['assignees'];
const subDefault = (): SubOpts => ({ kind: 'task', key: 'sub-cols', title: tt('Alt görevler'), head: tt('Görev adı'), ph: tt('Alt görev ekle…'), def: SUB_DEFAULT });
function subColDefs(c: Ctx, task: Detail, o: SubOpts): SubCol[] {
  const defs: SubCol[] = builtinCols(c.e)
    .filter((x) => !['list', 'comments'].includes(x.key))
    .map((x) => ({ ...x }));
  if (o.kind === 'decision')
    for (const x of defs) {
      if (x.key === 'assignees') x.name = tt('Sorumlu');
      if (x.key === 'due') x.name = tt('Son tarih');
    }
  for (const f of c.e.fieldsFor(task.list))
    defs.push({ key: 'f' + f.id, name: f.name, w: f.type === 'textarea' ? '200px' : '140px', field: { key: 'f' + f.id, id: f.id, ids: [f.id], name: f.name, type: f.type, options: (f.options ?? {}) as Record<string, unknown> } });
  return defs;
}
function SubTable({ task, o: oIn }: { task: Detail; o?: SubOpts }) {
  const d = useDr();
  const { c, canEdit: edit, D } = d;
  const { e } = c;
  const o = oIn ?? subDefault();
  const l = e.list(task.list);
  const tableRef = useRef<HTMLDivElement>(null);
  const inRef = useRef<HTMLInputElement>(null);
  const all = [...(task.children ?? []), ...(task.subtree ?? [])] as Child[];
  const kids: Record<number, Child[]> = {};
  for (const ch of all) (kids[ch.parent || task.id] ??= []).push(ch);
  const direct = (task.children ?? []).filter((x) => (x.kind || 'task') === o.kind);
  const defs = subColDefs(c, task, o);
  const want = e.pref<string[]>(o.key) ?? o.def;
  const cols = want.map((k) => defs.find((x) => x.key === k)).filter(Boolean) as SubCol[];
  const limit = task.depthLimit || e.depthLimit(task.list);
  const depth0 = task.depth || 0;
  const canAdd = edit && depth0 < limit;
  const narrow = d.mobile;
  const wkey = o.key.replace('-cols', '-w');
  const widths = useRef<Record<string, number>>({});
  widths.current = { ...(e.pref<Record<string, number>>(wkey) ?? {}), ...widths.current };
  const tplOf = () =>
    (widths.current.title ? widths.current.title + 'px' : 'minmax(' + (narrow ? 150 : 220) + 'px, 1fr)') +
    ' ' +
    cols.map((x) => (widths.current[x.key] || Math.min(parseInt(x.w, 10) || 140, narrow ? 110 : 170)) + 'px').join(' ') +
    ' 36px';
  const done = direct.filter((x) => e.isDone(x)).length;
  const patch = d.subPatch(task.id);

  const colPick = (anchor: HTMLElement) => {
    const Pick = () => {
      const [w, setW] = useState<string[]>((e.pref<string[]>(o.key) ?? o.def).slice());
      return (
        <div className="flxg-fieldspop">
          <div className="flxg-menu-head">{o.kind === 'decision' ? tt('Karar sütunları') : tt('Alt görev sütunları')}</div>
          {defs.map((x) => {
            const on = w.includes(x.key);
            return (
              <label key={x.key} className="flxg-switch-row">
                <input
                  type="checkbox"
                  checked={on}
                  onChange={() => {
                    const n = on ? w.filter((k) => k !== x.key) : [...w, x.key];
                    setW(n);
                    e.setPref(o.key, n);
                  }}
                />
                {x.field && <span className="flxg-ftype">{fieldTypeIcon(x.field.type)}</span>}
                <span>{x.name}</span>
              </label>
            );
          })}
        </div>
      );
    };
    c.ui.openPop(anchor, <Pick />, { alignRight: true, inline: true });
  };
  const headCell = (key: string, name: string) => (
    <span key={key} className="flxg-subhead">
      {name}
      {!narrow && (
        <span
          className="flxg-col-resize"
          title={tt('Genişliği değiştirmek için sürükleyin, sıfırlamak için çift tıklayın')}
          aria-hidden="true"
          onPointerDown={(ev) => {
            if (ev.button !== 0) return;
            ev.preventDefault();
            const sx = ev.clientX;
            const sw = (ev.currentTarget.parentElement as HTMLElement).getBoundingClientRect().width;
            document.body.classList.add('flxg-col-resizing');
            const mv = (m: PointerEvent) => {
              widths.current[key] = Math.round(Math.max(key === 'title' ? 140 : 60, Math.min(700, sw + m.clientX - sx)));
              tableRef.current?.style.setProperty('--subcols', tplOf());
            };
            const up = () => {
              document.removeEventListener('pointermove', mv);
              document.removeEventListener('pointerup', up);
              document.body.classList.remove('flxg-col-resizing');
              e.setPref(wkey, { ...widths.current });
            };
            document.addEventListener('pointermove', mv);
            document.addEventListener('pointerup', up);
          }}
          onDoubleClick={() => {
            delete widths.current[key];
            const p = { ...(e.pref<Record<string, number>>(wkey) ?? {}) };
            delete p[key];
            tableRef.current?.style.setProperty('--subcols', tplOf());
            e.setPref(wkey, Object.keys(p).length ? p : null);
          }}
        />
      )}
    </span>
  );

  const rows: ReactNode[] = [];
  const addRow = (x: Child, depth: number) => {
    const cs = e.status(x);
    const cl = e.list(x.list);
    const doneK = cl?.statuses.find((s) => s.type === 'done')?.key;
    const openK = cl?.statuses[0]?.key;
    const ch = kids[x.id] ?? [];
    const isOpen = ch.length > 0 && D.subOpen[x.id] !== false;
    const canEditC = edit && !!x.id;
    rows.push(
      <div key={x.id || x._tmp} className={cls('flxg-subrow', cs.type === 'done' && 'is-done', (x.sending || D.subPend[x.id]) && 'is-sending', depth > 0 && 'is-nested')}>
        <div className="flxg-subcell flxg-subname">
          <span className="flxg-subindent" style={{ ['--depth' as string]: depth }} />
          {ch.length ? (
            <button type="button" className={cls('flxg-subcaret', isOpen && 'is-open')} onClick={() => ((D.subOpen[x.id] = !isOpen), d.render())}>
              <Ic n="chevR" s={14} />
            </button>
          ) : (
            <span className="flxg-subcaret is-empty" />
          )}
          <button type="button" className={cls('flxg-sdot', cs.type === 'done' && 'is-done')} style={cv(cs.color)} disabled={!canEditC} title={cs.label} onClick={() => void patch(x.id, { status: cs.type === 'done' ? openK : doneK })}>
            {cs.type === 'done' && <Ic n="check" s={11} />}
          </button>
          <button type="button" className="flxg-sub-title" onClick={() => x.id && e.openTask(x.id)}>
            {x.title}
          </button>
          {x.subtasks > 0 && !ch.length && (
            <span className="flxg-meta" title={tt('Alt görev')}>
              <Ic n="subtask" s={13} />
              {x.subtasks}
            </span>
          )}
          {canEditC && depth0 + depth + 1 < limit && (
            <button type="button" className="flxg-row-addsub" title={tt('Alt görev ekle')} onClick={() => ((D.focusSubAdd = true), e.openTask(x.id))}>
              <Ic n="plus" s={14} />
            </button>
          )}
        </div>
        {cols.map((col) => (
          <div key={col.key} className="flxg-subcell">
            {x.id ? <Cell task={x} col={col} patch={patch} after={() => void d.load(task.id)} /> : null}
          </div>
        ))}
        <span />
      </div>,
    );
    if (isOpen) for (const k of ch) addRow(k, depth + 1);
  };
  for (const x of direct) addRow(x, 0);

  const add = () => {
    const v = inRef.current!.value.trim();
    if (!v) return;
    inRef.current!.value = '';
    const tid = task.id;
    const tmp = { id: 0, _tmp: 's' + Date.now(), kind: o.kind, title: v, list: task.list, status: l?.statuses[0]?.key ?? '', assignees: [], tags: [], fields: {}, priority: 0, due: '', start: '', sending: true, parent: tid } as unknown as Child;
    task.children.push(tmp);
    inflight(tid).children.push(tmp);
    d.render();
    e.createTask({ list: task.list, parent: tid, title: v, kind: o.kind }).then(
      (nt) => {
        settle(D, tid, 'children', tmp, nt as Child);
        const lt = e.S.tasks[tid];
        if (lt && o.kind === 'task') lt.subtasks = (lt.subtasks || 0) + 1;
        if (lt && o.kind === 'decision') lt.decisions = (lt.decisions || 0) + 1;
        e.keepTask(tid);
        e.bump();
        d.render();
        setTimeout(() => inRef.current?.focus(), 0);
      },
      (er) => {
        settle(D, tid, 'children', tmp, null);
        d.render();
        c.ui.fail(er);
      },
    );
  };

  const head = (
    <>
      {o.title}
      {direct.length > 0 && <span className="flxg-gcount">{done + '/' + direct.length}</span>}
      {direct.length > 0 && (
        <span className="flxg-subprog">
          <span style={{ width: Math.round((done / direct.length) * 100) + '%' }} />
        </span>
      )}
    </>
  );
  return (
    <Sec k={o.kind === 'decision' ? 'decisions' : 'subtasks'} cls={cls('flxg-subtable-wrap', o.kind !== 'task' && 'is-' + o.kind)} head={head}>
      {(direct.length > 0 || canAdd) && (
        <div className="flxg-subtable-scroll">
          <div ref={tableRef} className="flxg-subtable" style={{ ['--subcols' as string]: tplOf() }}>
            <div className="flxg-subrow is-head">
              {headCell('title', o.head)}
              {cols.map((x) => headCell(x.key, x.name))}
              <span className="flxg-subpick">
                <button type="button" className="flxg-icon-btn is-sm" title={tt('Sütunları göster / gizle')} onClick={(ev) => colPick(ev.currentTarget)}>
                  <Ic n="plus" s={15} />
                </button>
              </span>
            </div>
            {rows}
            {canAdd && (
              <div className="flxg-subrow flxg-sub-add">
                <div className="flxg-subcell flxg-subname">
                  <Ic n="plus" s={14} />
                  <input ref={inRef} className="flxg-input is-sm" type="text" placeholder={o.ph} onKeyDown={(ev) => ev.key === 'Enter' && add()} />
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </Sec>
  );
}
/** Geçici kaydı sunucudan gelen gerçeğiyle değiştirir (real null ise siler). */
function settle<K extends 'children' | 'comments_list'>(D: DS, taskId: number, k: K, tmp: Detail[K][number], real: Detail[K][number] | null) {
  const f = mem.inflight[taskId];
  if (f) (f[k] as unknown[]) = (f[k] as unknown[]).filter((x) => x !== tmp);
  const objs = new Set<Detail>();
  if (D.task?.id === taskId) objs.add(D.task);
  const cached = mem.cache.get(taskId);
  if (cached) objs.add(cached);
  for (const o of objs) {
    const arr = (o[k] ??= [] as never) as unknown as { _tmp?: string; id: unknown }[];
    const i = arr.findIndex((x) => x._tmp === (tmp as { _tmp?: string })._tmp);
    if (i > -1) {
      if (real) arr.splice(i, 1, real as never);
      else arr.splice(i, 1);
    } else if (real && !arr.some((x) => x.id === (real as { id: unknown }).id)) arr.push(real as never);
  }
  return D.task?.id === taskId;
}

/* ---------------- Ekler ---------------- */
function FilesSection({ task }: { task: Detail }) {
  const files = task.attachments.filter((f) => f.context !== 'field');
  return (
    <Sec k="files" cls="flxg-sec-box" head={<>{tt('Ekler')}{files.length > 0 && <span className="flxg-gcount">{files.length}</span>}</>}>
      <Attachments task={task} files={files} />
    </Sec>
  );
}
function FileChip({ f, task, onRemove }: { f: FlxgFile; task: Detail; onRemove?: () => void }) {
  const d = useDr();
  const { c, canEdit, save } = d;
  const kind = fileKind(f.mime, f.name);
  const dl = () => void downloadFile(c.e, f).catch(c.ui.fail);
  return (
    <div className={cls('flxg-file', f.image && 'is-image')}>
      {f.image ? (
        <button
          type="button"
          className="flxg-file-thumb"
          onClick={() => {
            const imgs = task.attachments.filter((x) => x.image);
            openLightbox(c, imgs, imgs.indexOf(f));
          }}
        >
          <FileImg id={f.id} size="thumb" alt={f.name} />
        </button>
      ) : (
        <button type="button" className={'flxg-file-icon is-' + kind} onClick={dl}>
          <Ic n="file" s={22} />
          <span>{(f.name.split('.').pop() || '').slice(0, 4).toUpperCase()}</span>
        </button>
      )}
      <div className="flxg-file-info">
        <a
          className="flxg-file-name"
          href="#"
          title={f.name}
          onClick={(ev) => {
            ev.preventDefault();
            dl();
          }}
        >
          {f.name}
        </a>
        <span className="flxg-muted">{bytes(f.size)}</span>
      </div>
      {(canEdit || f.user === c.e.S.me?.id) && (
        <button
          type="button"
          className="flxg-icon-btn is-sm flxg-file-more"
          onClick={(ev) =>
            c.ui.menu(ev.currentTarget, [
              { label: tt('İndir'), icon: 'download', onClick: dl },
              f.image && canEdit && task.cover !== f.id && { label: tt('Kapak görseli yap'), icon: 'image', onClick: () => void save({ cover: f.id }) },
              f.image && canEdit && task.cover === f.id && { label: tt('Kapaktan kaldır'), icon: 'image', onClick: () => void save({ cover: 0 }) },
              { sep: true },
              {
                label: tt('Sil'),
                icon: 'trash',
                danger: true,
                onClick: () =>
                  void c.ui.confirm(tt('Dosyayı sil'), f.name, tt('Sil'), true).then((ok) => {
                    if (!ok) return;
                    onRemove?.();
                    c.e.api.deleteFile(f.id).then(() => void d.load(task.id), c.ui.fail);
                  }),
              },
            ])
          }
        >
          <Ic n="dots" s={16} />
        </button>
      )}
    </div>
  );
}
function Attachments({ task, files }: { task: Detail; files: FlxgFile[] }) {
  const d = useDr();
  const [over, setOver] = useState(false);
  return (
    <div className="flxg-attach">
      <div className="flxg-files">
        {files.map((f) => (
          <FileChip key={f.id} f={f} task={task} />
        ))}
        {(mem.uploads[task.id] ?? []).map((u) => (
          <div key={u.key} className="flxg-file is-uploading">
            {u.preview ? (
              <span className="flxg-file-thumb">
                <img src={u.preview} alt="" />
              </span>
            ) : (
              <span className="flxg-file-icon">
                <Ic n="file" s={22} />
              </span>
            )}
            <div className="flxg-file-info">
              <span className="flxg-file-name">{u.name}</span>
              <span className="flxg-pfile-bar">
                <span style={{ width: '60%' }} />
              </span>
            </div>
          </div>
        ))}
      </div>
      {d.canEdit && (
        <label
          className={cls('flxg-dropzone', over && 'is-over')}
          onDragEnter={(ev) => (ev.preventDefault(), setOver(true))}
          onDragOver={(ev) => (ev.preventDefault(), setOver(true))}
          onDragLeave={(ev) => (ev.preventDefault(), setOver(false))}
          onDrop={(ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            setOver(false);
            void d.uploadTaskFiles(task, Array.from(ev.dataTransfer.files || []));
          }}
        >
          <input
            type="file"
            multiple
            hidden
            onChange={(ev) => {
              void d.uploadTaskFiles(task, Array.from(ev.target.files ?? []));
              ev.target.value = '';
            }}
          />
          <Ic n="upload" s={18} />
          <span>{tt('Dosyaları buraya sürükleyin ya da seçin')}</span>
          <span className="flxg-muted">{tt('En fazla {s}', { s: bytes(d.c.e.S.config.maxUpload) })}</span>
        </label>
      )}
    </div>
  );
}

/* =====================================================================
 * Toplantı: görev altyapısı, sade ekran (tarih, katılımcılar, gündem, kararlar, rapor)
 * ================================================================== */
const mtgSections = () => [
  ['date', tt('Tarih')], ['people', tt('Katılımcılar')], ['tags', tt('Etiketler')], ['fields', tt('Özel alanlar')], ['priority', tt('Öncelik')],
  ['desc', tt('Açıklama')], ['agenda', tt('Gündem')], ['decisions', tt('Kararlar')], ['subtasks', tt('Alt görevler')], ['files', tt('Ekler')],
] as const;
const mtgHidden = (c: Ctx) => c.e.pref<Record<string, number>>('mtg-hide') ?? { priority: 1, subtasks: 1 };
function MeetingBody({ task }: { task: Detail }) {
  const d = useDr();
  const { c, canEdit: edit, save, D } = d;
  const { e } = c;
  const l = e.list(task.list);
  const hid = mtgHidden(c);
  const on = (k: string) => !hid[k];
  const meta: ReactNode[] = [];
  if (on('date'))
    meta.push(
      <Attr key="date" icon="calendar" label={tt('Tarih')} k="due">
        <button type="button" className="flxg-date-btn" disabled={!edit} onClick={(ev) => (ev.stopPropagation(), pickDate(c, ev.currentTarget, task.due, (v) => void save({ due: v }), { label: tt('Tarih') }))}>
          {task.due ? fmtDue(task.due, { full: true }) : tt('Tarih seçin')}
        </button>
      </Attr>,
    );
  if (on('people'))
    meta.push(
      <Attr key="people" icon="users" label={tt('Katılımcılar')} k="assignees" onEdit={edit ? (a) => pickPeople(c, a, task.assignees, debounce((ids: number[]) => void save({ assignees: ids }), 700)) : null}>
        <People ids={task.assignees} empty={tt('Kişi ekleyin')} />
      </Attr>,
    );
  if (on('tags'))
    meta.push(
      <Attr key="tags" icon="tag" label={tt('Etiketler')} k="tags" onEdit={edit ? (a) => pickTags(c, a, l ? l.space_id : 0, task.tags, debounce((ids: number[]) => void save({ tags: ids }), 700)) : null}>
        {task.tags.length ? <TagChips ids={task.tags} /> : <span className="flxg-muted">{tt('Boş')}</span>}
      </Attr>,
    );
  if (on('priority'))
    meta.push(
      <Attr key="prio" icon="flag" label={tt('Öncelik')} k="priority" onEdit={edit ? (a) => pickPriority(c, a, task.priority, (n) => void save({ priority: n })) : null}>
        {task.priority ? <PrioFlag n={task.priority} label /> : <span className="flxg-muted">{tt('Boş')}</span>}
      </Attr>,
    );
  if (on('fields')) for (const f of e.fieldsFor(task.list)) meta.push(<FieldRow key={'f' + f.id} task={task} f={f} />);
  return (
    <>
      {D.mtgCustom && (
        <div className="flxg-mtg-custom">
          <div className="flxg-mtg-custom-head">
            <b>{tt('Ekranda görünenler')}</b>
            <span className="flxg-muted">{tt('Bu tarayıcıda saklanır.')}</span>
            <span className="flxg-grow" />
            <button type="button" className="flxg-icon-btn is-sm" title={tt('Kapat')} onClick={() => ((D.mtgCustom = false), d.render())}>
              <Ic n="x" s={15} />
            </button>
          </div>
          <div className="flxg-mtg-chips">
            {mtgSections().map(([k, label]) => (
              <button
                key={k}
                type="button"
                className={cls('flxg-fchip', on(k) && 'is-on')}
                onClick={() => {
                  const m = { ...mtgHidden(c) };
                  if (m[k]) delete m[k];
                  else m[k] = 1;
                  e.setPref('mtg-hide', m);
                }}
              >
                <Ic n={on(k) ? 'eye' : 'eyeOff'} s={13} />
                {label}
              </button>
            ))}
          </div>
        </div>
      )}
      {meta.length > 0 && <LimitAttrs rows={meta} cls="flxg-mtg-meta" />}
      {on('desc') && (
        <Sec k="desc" cls="flxg-sec-box" head={tt('Açıklama')}>
          <DescEditor task={task} />
        </Sec>
      )}
      {on('agenda') && <Agenda task={task} />}
      {on('decisions') && <SubTable task={task} o={{ kind: 'decision', key: 'dec-cols', title: tt('Kararlar'), head: tt('Karar'), ph: tt('Karar ekle… (görev gibi atanır, tarih ve etiket alır)'), def: ['assignees', 'due', 'tags'] }} />}
      {on('subtasks') && <SubTable task={task} />}
      {on('files') && <FilesSection task={task} />}
    </>
  );
}

/* ---------------- Gündem ---------------- */
function Agenda({ task }: { task: Detail }) {
  const d = useDr();
  const { c, canEdit: edit, D } = d;
  const { e } = c;
  const l = e.list(task.list);
  const inRef = useRef<HTMLInputElement>(null);
  const items = (task.children ?? []).filter((x) => x.kind === 'agenda').sort((a, b) => (a.pos || 0) - (b.pos || 0) || a.id - b.id);
  const done = items.filter((x) => e.isDone(x)).length;
  const doneK = l?.statuses.find((s) => s.type === 'done')?.key;
  const openK = l?.statuses[0]?.key;
  const patch = d.subPatch(task.id);
  const add = () => {
    const v = inRef.current!.value.trim();
    if (!v) return;
    inRef.current!.value = '';
    const tid = task.id;
    const tmp = { id: 0, _tmp: 'a' + Date.now(), kind: 'agenda', title: v, list: task.list, status: openK ?? '', assignees: [], tags: [], fields: {}, priority: 0, due: '', start: '', pos: 1e12, sending: true, parent: tid } as unknown as Child;
    task.children.push(tmp);
    inflight(tid).children.push(tmp);
    d.render();
    e.createTask({ list: task.list, parent: tid, title: v, kind: 'agenda' }).then(
      (nt) => {
        settle(D, tid, 'children', tmp, nt as Child);
        e.keepTask(tid);
        d.render();
        setTimeout(() => inRef.current?.focus(), 0);
      },
      (er) => {
        settle(D, tid, 'children', tmp, null);
        d.render();
        c.ui.fail(er);
      },
    );
  };
  const byId = new Map(items.map((x) => [x.id, x]));
  return (
    <Sec k="agenda" cls="flxg-agenda-wrap" head={<>{tt('Gündem')}{items.length > 0 && <span className="flxg-gcount">{done + '/' + items.length}</span>}</>}>
      <DndArea
        canStart={(id) => edit && !!id && !D.agOpen[id]}
        overlay={(id) => <div className="flxg-ag-item"><div className="flxg-ag-line"><span className="flxg-ag-t">{byId.get(id)?.title}</span></div></div>}
        onDrop={(info) => {
          const a = info.after ? byId.get(info.after) : null;
          const b = info.before ? byId.get(info.before) : null;
          const x = byId.get(info.id);
          if (!x) return;
          const pos = between(a ? a.pos || 0 : null, b ? b.pos || 0 : null);
          x.pos = pos;
          void patch(x.id, { position: pos });
        }}
      >
        <AgendaList task={task} items={items} doneK={doneK} openK={openK} />
      </DndArea>
      {!items.length && !edit && <div className="flxg-muted flxg-ag-empty">{tt('Gündem maddesi yok.')}</div>}
      {edit && (
        <div className="flxg-ag-add">
          <Ic n="plus" s={14} />
          <input ref={inRef} className="flxg-input is-sm" type="text" placeholder={tt('Gündem maddesi ekle…')} onKeyDown={(ev) => ev.key === 'Enter' && add()} />
        </div>
      )}
    </Sec>
  );
}
function AgendaList({ task, items, doneK, openK }: { task: Detail; items: Child[]; doneK?: string; openK?: string }) {
  const zone = useDropZone('agenda-' + task.id);
  return (
    <div className="flxg-mtg-agenda" ref={zone.ref} {...zone.props}>
      {items.map((x, i) => (
        <AgendaItem key={x.id || x._tmp} task={task} x={x} i={i} doneK={doneK} openK={openK} />
      ))}
    </div>
  );
}
function AgendaItem({ task, x, i, doneK, openK }: { task: Detail; x: Child; i: number; doneK?: string; openK?: string }) {
  const d = useDr();
  const { c, canEdit: edit, D } = d;
  const { e } = c;
  const drag = useDragItem(x.id);
  const files = task.childFiles?.[x.id] ?? [];
  const imgs = files.filter((f) => f.image);
  const isDone = e.isDone(x);
  const exp = !!D.agOpen[x.id] && !!x.id;
  const note = plain(x.description);
  const toggle = () => {
    if (!x.id) return;
    D.agOpen[x.id] = !exp;
    d.render();
  };
  const patch = d.subPatch(task.id);
  return (
    <div ref={drag.ref} className={cls('flxg-ag-item', isDone && 'is-done', exp && 'is-open', (x.sending || D.subPend[x.id]) && 'is-sending', drag.dragging && 'is-drag-hidden')} data-id={x.id || ''} {...drag.props}>
      <div className="flxg-ag-line">
        <span className="flxg-ag-num">{i + 1}.</span>
        <button type="button" className="flxg-ag-check" disabled={!edit || !x.id} title={isDone ? tt('Görüşülmedi olarak işaretle') : tt('Görüşüldü olarak işaretle')} onClick={() => void patch(x.id, { status: isDone ? openK : doneK })}>
          {isDone && <Ic n="check" s={12} />}
        </button>
        <button type="button" className="flxg-ag-title" onClick={toggle}>
          <span className="flxg-ag-t">{x.title}</span>
          {note && !exp && <span className="flxg-ag-note1">{note}</span>}
        </button>
        {imgs.length > 0 && !exp && (
          <span className="flxg-ag-thumbs">
            {imgs.slice(0, 3).map((f) => (
              <FileImg key={f.id} id={f.id} size="thumb" onClick={() => openLightbox(c, imgs, imgs.indexOf(f))} />
            ))}
            {imgs.length > 3 && <span className="flxg-ag-more">+{imgs.length - 3}</span>}
          </span>
        )}
        {files.length > imgs.length && !exp && (
          <span className="flxg-meta">
            <Ic n="clip" s={13} />
            {files.length - imgs.length}
          </span>
        )}
        {x.comments > 0 && (
          <span className="flxg-meta">
            <Ic n="msg" s={13} />
            {x.comments}
          </span>
        )}
        <button type="button" className="flxg-icon-btn is-sm flxg-ag-chev" aria-label={exp ? tt('Daralt') : tt('Aç')} onClick={toggle}>
          <Ic n={exp ? 'chevD' : 'chevR'} s={14} />
        </button>
      </div>
      {exp && <AgendaDetail task={task} x={x} files={files} />}
    </div>
  );
}
function AgendaDetail({ task, x, files }: { task: Detail; x: Child; files: FlxgFile[] }) {
  const d = useDr();
  const { c, canEdit: edit, D } = d;
  const ta = useRef<HTMLTextAreaElement>(null);
  const imgs = files.filter((f) => f.image);
  useLayoutEffect(() => autosize(ta.current));
  const initial = plain(x.description);
  return (
    <div className="flxg-ag-detail" onPointerDown={(ev) => ev.stopPropagation()}>
      {(edit || initial) && (
        <textarea
          ref={ta}
          className="flxg-input flxg-ag-notein"
          rows={2}
          placeholder={edit ? tt('Not ekleyin…') : ''}
          readOnly={!edit}
          defaultValue={initial}
          onInput={(ev) => autosize(ev.currentTarget)}
          onFocus={() => (D.editing = true)}
          onBlur={(ev) => {
            D.editing = false;
            const v = ev.currentTarget.value.trim();
            if (v === plain(x.description)) return;
            x.description = v ? '<p>' + v.replace(/[&<>]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[ch]!).replace(/\n/g, '<br>') + '</p>' : '';
            void d.subPatch(task.id)(x.id, { description: x.description });
          }}
        />
      )}
      {files.length > 0 && (
        <div className="flxg-ag-files">
          {files.map((f) =>
            f.image ? (
              <button key={f.id} type="button" className="flxg-ag-img" title={f.name} onClick={() => openLightbox(c, imgs, imgs.indexOf(f))}>
                <FileImg id={f.id} size="thumb" alt={f.name} />
              </button>
            ) : (
              <a key={f.id} className="flxg-ag-file" href="#" onClick={(ev) => (ev.preventDefault(), void downloadFile(c.e, f).catch(c.ui.fail))}>
                <Ic n="file" s={14} />
                <span>{f.name}</span>
              </a>
            ),
          )}
        </div>
      )}
      <div className="flxg-ag-actions">
        {edit && (
          <label className="flxg-chip-btn">
            <Ic n="upload" s={14} />
            {tt('Görsel / dosya ekle')}
            <input
              type="file"
              multiple
              hidden
              onChange={(ev) => {
                const list = Array.from(ev.target.files ?? []);
                ev.target.value = '';
                Promise.all(list.map((file) => c.e.api.upload(file, { task: x.id, context: 'task' }))).then(
                  () => void d.load(task.id),
                  (er) => (c.ui.fail(er), void d.load(task.id)),
                );
              }}
            />
          </label>
        )}
        <button type="button" className="flxg-chip-btn" onClick={() => c.e.openTask(x.id)}>
          <Ic n="eye" s={14} />
          {tt('Ayrıntı ve yorumlar')}
        </button>
        {edit && (
          <button
            type="button"
            className="flxg-chip-btn is-danger"
            onClick={() =>
              void c.ui.confirm(tt('Gündem maddesini sil'), tt('"{n}" silinecek.', { n: x.title }), tt('Sil'), true).then((ok) => {
                if (ok) c.e.deleteTask(x.id).then(() => void d.load(task.id), c.ui.fail);
              })
            }
          >
            <Ic n="trash" s={14} />
            {tt('Sil')}
          </button>
        )}
      </div>
    </div>
  );
}

/* ---------------- Toplantı raporu (yazdır / PDF) ---------------- */
const REPORT_CSS =
  'body{font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:32px}.rp{max-width:800px;margin:auto;color:#1f2937;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
  '.rp-head{display:flex;align-items:center;gap:12px;padding-bottom:14px;border-bottom:3px solid var(--ac);margin-bottom:22px}.rp-org{font-weight:700;font-size:15px;line-height:1.2}.rp-org small{display:block;font-weight:500;color:#6b7280;font-size:12px}' +
  '.rp-kind{margin-left:auto;text-align:right;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--ac);font-weight:700}.rp-kind small{display:block;letter-spacing:0;text-transform:none;color:#6b7280;font-weight:500;font-size:12px}' +
  '.rp h1{font-size:28px;line-height:1.25;margin:0 0 10px}.pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11.5px;font-weight:600;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff)}' +
  '.rp-info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.rp-info>div{border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;background:#fafafa}.rp-info .k{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;font-weight:600;margin-bottom:3px}.rp-info .wide{grid-column:1/-1}' +
  '.rp-stats{display:flex;gap:10px}.rp-stats div{flex:1;border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--ac) 8%,#fff)}.rp-stats b{display:block;font-size:22px;color:var(--ac)}.rp-stats span{font-size:12px;color:#4b5563}' +
  '.rp h2{display:flex;gap:10px;font-size:17px;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid #e5e7eb}.rp h2 .n{font-size:12px;color:#6b7280}' +
  '.ag{display:flex;gap:12px;padding:10px 0;border-bottom:1px dashed #e5e7eb}.ag-no{flex:none;width:26px;height:26px;border-radius:50%;background:var(--ac);color:#fff;font-weight:700;font-size:12.5px;display:flex;align-items:center;justify-content:center}.ag.is-done .ag-no{background:#16a34a}.ag-t{font-weight:600}.ag-note{margin-top:3px;color:#374151}.ok{color:#16a34a;font-size:12px;font-weight:600;margin-left:6px}' +
  'table.t{border-collapse:collapse;width:100%;border:1px solid #e5e7eb}table.t th{background:#f3f4f6;font-size:11px;text-transform:uppercase;color:#4b5563;padding:7px 10px;text-align:left}table.t td{border-bottom:1px solid #f0f0f0;padding:8px 10px}td.c{white-space:nowrap;font-size:12.5px}.muted{color:#6b7280}.desc{font-size:12.5px;color:#4b5563}' +
  '.rp-foot{margin-top:36px;padding-top:10px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:11px;color:#9ca3af}';
function meetingReport(c: Ctx, task: Detail) {
  const { e } = c;
  const esc = (s: unknown) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]!);
  const l = e.list(task.list);
  const sp = l ? e.space(l.space_id) : null;
  const ac = sp?.color || '#2e7d32';
  const user = (id: number): FlxgUser => e.S.users[id] ?? ({ id, name: '?', color: '#64748b', initials: '?', avatar: '' } as FlxgUser);
  const pill = (t: FlxgTask) => {
    const s = e.status(t);
    return '<span class="pill" style="--c:' + esc(s.color) + '">' + esc(s.label) + '</span>';
  };
  const due = (dd: string) => (dd ? esc(fmtDue(dd, { full: true })) : '<span class="muted">—</span>');
  const kids = task.children ?? [];
  const agenda = kids.filter((x) => x.kind === 'agenda' && x.id).sort((a, b) => (a.pos || 0) - (b.pos || 0) || a.id - b.id);
  const dec = kids.filter((x) => x.kind === 'decision' && x.id);
  const subs = kids.filter((x) => (x.kind || 'task') === 'task' && x.id);
  let out =
    '<div class="rp-head"><div class="rp-org">' + esc(sp?.name ?? '') + '<small>' + esc(l?.name ?? '') + '</small></div><div class="rp-kind">' + esc(tt('Toplantı raporu')) + '<small>' + due(task.due) + '</small></div></div>';
  out += '<h1>' + esc(task.title) + '</h1>' + pill(task);
  const info: [string, string, number?][] = [[tt('Tarih'), due(task.due)], [tt('Proje'), esc((sp ? sp.name + ' / ' : '') + (l?.name ?? ''))]];
  if (task.assignees.length) info.push([tt('Katılımcılar'), esc(task.assignees.map((id) => user(id).name).join(', ')), 1]);
  if (task.tags.length) info.push([tt('Etiketler'), esc(task.tags.map((id) => e.tag(id)?.name ?? '').join(', ')), 1]);
  out += '<div class="rp-info">' + info.map((r) => '<div' + (r[2] ? ' class="wide"' : '') + '><div class="k">' + esc(r[0]) + '</div>' + r[1] + '</div>').join('') + '</div>';
  const stats: [string, string][] = [];
  if (agenda.length) stats.push([agenda.filter((x) => e.isDone(x)).length + '/' + agenda.length, tt('gündem maddesi görüşüldü')]);
  if (dec.length) stats.push([String(dec.length), tt('karar alındı')]);
  if (subs.length) stats.push([subs.filter((x) => e.isDone(x)).length + '/' + subs.length, tt('alt görev tamamlandı')]);
  if (stats.length) out += '<div class="rp-stats">' + stats.map((x) => '<div><b>' + esc(x[0]) + '</b><span>' + esc(x[1]) + '</span></div>').join('') + '</div>';
  if (plain(task.description)) out += '<h2>' + esc(tt('Açıklama')) + '</h2><p>' + esc(plain(task.description)).replace(/\n/g, '<br>') + '</p>';
  if (agenda.length) {
    out += '<h2>' + esc(tt('Gündem')) + ' <span class="n">' + agenda.length + '</span></h2>';
    agenda.forEach((x, i) => {
      const ok = e.isDone(x);
      const note = plain(x.description);
      out += '<div class="ag' + (ok ? ' is-done' : '') + '"><span class="ag-no">' + (i + 1) + '</span><div><span class="ag-t">' + esc(x.title) + '</span>' + (ok ? '<span class="ok">✓ ' + esc(tt('Görüşüldü')) + '</span>' : '') + (note ? '<div class="ag-note">' + esc(note).replace(/\n/g, '<br>') + '</div>' : '') + '</div></div>';
    });
  }
  const table = (title: string, head: string, rows: Child[]) => {
    out += '<h2>' + esc(title) + ' <span class="n">' + rows.length + '</span></h2><table class="t"><thead><tr><th>' + esc(head) + '</th><th>' + esc(tt('Sorumlu')) + '</th><th>' + esc(tt('Son tarih')) + '</th><th>' + esc(tt('Durum')) + '</th></tr></thead><tbody>';
    for (const x of rows) out += '<tr><td><b>' + esc(x.title) + '</b>' + (plain(x.description) ? '<div class="desc">' + esc(plain(x.description)) + '</div>' : '') + '</td><td class="c">' + esc(x.assignees.map((id) => user(id).name).join(', ')) + '</td><td class="c">' + due(x.due) + '</td><td class="c">' + pill(x) + '</td></tr>';
    out += '</tbody></table>';
  };
  if (dec.length) table(tt('Kararlar'), tt('Karar'), dec);
  if (subs.length) table(tt('Alt görevler'), tt('Görev'), subs);
  out += '<div class="rp-foot"><span>' + esc(sp?.name ?? '') + '</span><span>' + esc(tt('Rapor: {d} · {n}', { d: new Date().toLocaleString(), n: e.S.me?.name ?? '' })) + '</span></div>';
  // Gizli çerçevede yazdır (tarayıcı ve masaüstü uygulamasında "PDF olarak kaydet").
  const fr = document.createElement('iframe');
  fr.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
  document.body.appendChild(fr);
  const doc = fr.contentDocument!;
  doc.open();
  doc.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(task.title) + '</title><style>' + REPORT_CSS + '</style></head><body><div class="rp" style="--ac:' + esc(ac) + '">' + out + '</div></body></html>');
  doc.close();
  setTimeout(() => {
    try {
      fr.contentWindow!.focus();
      fr.contentWindow!.print();
    } catch (er) {
      c.ui.fail(er);
    }
    setTimeout(() => fr.remove(), 60000);
  }, 200);
}

/* =====================================================================
 * Yorumlar ve etkinlik
 * ================================================================== */
function SideRail({ task }: { task: Detail }) {
  const d = useDr();
  const n = task.comments_list.length;
  return (
    <button type="button" className="flxg-dr-rail" title={tt('Yorumları ve etkinliği göster')} onClick={() => (store.set('task-side', null), d.render())}>
      <Ic n="chevL" s={16} />
      <Ic n="msg" s={17} />
      {n > 0 && <span className="flxg-badge">{n}</span>}
      <span className="flxg-dr-rail-label">{tt('Yorumlar ve etkinlik')}</span>
    </button>
  );
}
function ActivityText({ a, task }: { a: FlxgActivity; task: Detail }) {
  const { c } = useDr();
  const { e } = c;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const dt = a.data as Record<string, any>;
  const who = <b>{e.S.users[a.user]?.name ?? (a.user ? tt('Silinmiş kullanıcı') : tt('Sistem'))}</b>;
  const l = e.list(task.list);
  const stNode = (k: string) => {
    const s = l?.statuses.find((x) => x.key === k);
    return (
      <span className="flxg-status is-sm" style={cv(s ? s.color : '#999')}>
        {s ? s.label : k}
      </span>
    );
  };
  const uName = (id: number) => e.S.users[id]?.name ?? tt('Silinmiş kullanıcı');
  const r = (...rest: ReactNode[]) => (
    <>
      {who} {rest}
    </>
  );
  switch (a.action) {
    case 'created': return r(tt('görevi oluşturdu'));
    case 'status': return r(tt('durumu değiştirdi:'), ' ', stNode(dt.from), ' → ', stNode(dt.to));
    case 'title': return r(tt('adı değiştirdi:'), ' ', <i key="i">{dt.to}</i>);
    case 'description': return r(tt('açıklamayı güncelledi'));
    case 'priority': return r(dt.to ? tt('önceliği "{p}" yaptı', { p: e.priority(dt.to)?.label ?? '' }) : tt('önceliği kaldırdı'));
    case 'due': return r(dt.to ? tt('bitiş tarihini {d} yaptı', { d: fmtDue(dt.to, { full: true }) }) : tt('bitiş tarihini kaldırdı'));
    case 'start': return r(dt.to ? tt('başlangıç tarihini {d} yaptı', { d: fmtDue(dt.to, { full: true }) }) : tt('başlangıç tarihini kaldırdı'));
    case 'assign': return r(dt.user === a.user ? tt('görevi üstlendi') : tt('{n} kişisini atadı', { n: uName(dt.user) }));
    case 'unassign': return r(tt('{n} atamasını kaldırdı', { n: uName(dt.user) }));
    case 'tag': return r(tt('etiket ekledi:'), ' ', <TagChips key="t" ids={[dt.tag]} />);
    case 'untag': return r(tt('etiketi kaldırdı:'), ' ', <TagChips key="t" ids={[dt.tag]} />);
    case 'field': return r(tt('"{f}" alanını güncelledi', { f: dt.name || '' }));
    case 'moved': return r(tt('görevi taşıdı:'), ' ', e.list(dt.from)?.name ?? '…', ' → ', e.list(dt.to)?.name ?? '…');
    case 'cover': return r(dt.to ? tt('kapak görselini değiştirdi') : tt('kapak görselini kaldırdı'));
    case 'attach': return r(tt('dosya ekledi:'), ' ', <i key="i">{dt.name || ''}</i>);
    case 'detach': return r(tt('dosya sildi:'), ' ', <i key="i">{dt.name || ''}</i>);
    case 'archived': return r(tt('görevi arşivledi'));
    case 'unarchived': return r(tt('görevi arşivden çıkardı'));
  }
  return r(a.action);
}
function bumpCount(c: Ctx, id: number, n: number) {
  const t = c.e.S.tasks[id];
  if (t) {
    t.comments = Math.max(0, (t.comments || 0) + n);
    c.e.bump();
  }
}
function CommentNode({ cm, task }: { cm: FlxgComment; task: Detail }) {
  const d = useDr();
  const { c } = d;
  const { e } = c;
  const u = e.S.users[cm.user];
  const mine = cm.user === e.S.me?.id;
  const [editing, setEditing] = useState(false);
  const ta = useRef<HTMLTextAreaElement>(null);
  useLayoutEffect(() => {
    if (editing) {
      autosize(ta.current);
      ta.current?.focus();
    }
  }, [editing]);
  return (
    <div className={cls('flxg-cmt', mine && 'is-mine', cm.sending && 'is-sending')} id={'flxg-c' + cm.id}>
      <Avatar u={u} size={30} />
      <div className="flxg-cmt-main">
        <div className="flxg-cmt-head">
          <b>{u ? u.name : tt('Silinmiş kullanıcı')}</b>
          <span className="flxg-muted" title={fullTime(cm.created)}>
            {cm.sending ? tt('Gönderiliyor…') : ago(cm.created)}
          </span>
          {cm.edited && <span className="flxg-muted">· {tt('düzenlendi')}</span>}
          <span className="flxg-grow" />
          {!cm.sending && (mine || rank(d.role) >= 4) && (
            <button
              type="button"
              className="flxg-icon-btn is-sm"
              onClick={(ev) =>
                c.ui.menu(ev.currentTarget, [
                  mine && { label: tt('Düzenle'), icon: 'edit', onClick: () => setEditing(true) },
                  {
                    label: tt('Sil'),
                    icon: 'trash',
                    danger: true,
                    onClick: () =>
                      void c.ui.confirm(tt('Yorumu sil'), tt('Yorum ve ekleri silinecek.'), tt('Sil'), true).then((ok) => {
                        if (ok)
                          e.api.deleteComment(Number(cm.id)).then(() => {
                            void d.load(task.id);
                            bumpCount(c, task.id, -1);
                          }, c.ui.fail);
                      }),
                  },
                ])
              }
            >
              <Ic n="dots" s={15} />
            </button>
          )}
        </div>
        {editing ? (
          <div className="flxg-cmt-edit">
            <textarea ref={ta} className="flxg-input" rows={3} defaultValue={cm.body} onInput={(ev) => autosize(ev.currentTarget)} />
            <div className="flxg-row-btns">
              <button type="button" className="flxg-btn is-sm" onClick={() => setEditing(false)}>
                {tt('Vazgeç')}
              </button>
              <button type="button" className="flxg-btn is-primary is-sm" onClick={() => e.api.editComment(Number(cm.id), ta.current!.value).then(() => (setEditing(false), void d.load(task.id)), c.ui.fail)}>
                {tt('Kaydet')}
              </button>
            </div>
          </div>
        ) : (
          cm.body && <div className="flxg-cmt-body flxg-prose" dangerouslySetInnerHTML={{ __html: richText(cm.body, e.S.users, e.S.me?.id ?? 0) }} />
        )}
        {cm.files?.length > 0 && (
          <div className="flxg-cmt-files">
            {cm.files.map((f) =>
              f.image ? (
                <button
                  key={f.id}
                  type="button"
                  className="flxg-cmt-img"
                  onClick={() => {
                    const imgs = cm.files.filter((x) => x.image);
                    openLightbox(c, imgs, imgs.indexOf(f));
                  }}
                >
                  <FileImg id={f.id} size="thumb" alt={f.name} />
                </button>
              ) : (
                <a key={f.id} className="flxg-cmt-file" href="#" onClick={(ev) => (ev.preventDefault(), void downloadFile(e, f).catch(c.ui.fail))}>
                  <Ic n="file" s={16} />
                  <span>{f.name}</span>
                  <span className="flxg-muted">{bytes(f.size)}</span>
                </a>
              ),
            )}
          </div>
        )}
      </div>
    </div>
  );
}
function Side({ task }: { task: Detail }) {
  const d = useDr();
  const { c, D } = d;
  const [showAct, setShowAct] = useState(() => store.get('task-act') !== '0');
  const stream = useRef<HTMLDivElement>(null);
  const [dropping, setDropping] = useState(false);
  const depth = useRef(0);
  const addFiles = useRef<(f: File[]) => void>(() => {});
  type It = { time: string; c?: FlxgComment; a?: FlxgActivity };
  let items: It[] = task.comments_list.map((x) => ({ time: x.created, c: x }));
  if (showAct) items = items.concat(task.activity.map((a) => ({ time: a.created, a })));
  items.sort((a, b) => (a.time < b.time ? -1 : a.time > b.time ? 1 : 0));
  const n = items.length;
  useLayoutEffect(() => {
    const s = stream.current;
    if (s) s.scrollTop = s.scrollHeight;
  }, [n, task.id]);
  return (
    <div
      className={cls('flxg-dr-side', dropping && 'is-dropping')}
      onDragEnter={(ev) => {
        if (!hasFiles(ev)) return;
        ev.preventDefault();
        depth.current++;
        setDropping(true);
      }}
      onDragOver={(ev) => hasFiles(ev) && ev.preventDefault()}
      onDragLeave={() => {
        depth.current = Math.max(0, depth.current - 1);
        if (!depth.current) setDropping(false);
      }}
      onDrop={(ev) => {
        if (!hasFiles(ev)) return;
        ev.preventDefault();
        depth.current = 0;
        setDropping(false);
        if (d.canComment) addFiles.current(Array.from(ev.dataTransfer.files || []));
      }}
    >
      <div className="flxg-side-head">
        <h3>{tt('Yorumlar ve etkinlik')}</h3>
        <label className="flxg-switch-sm">
          <input
            type="checkbox"
            checked={showAct}
            onChange={(ev) => {
              setShowAct(ev.target.checked);
              store.set('task-act', ev.target.checked ? null : '0');
            }}
          />
          <span>{tt('Etkinliği göster')}</span>
        </label>
        {!d.mobile && (
          <button type="button" className="flxg-icon-btn is-sm" title={tt('Yorumları ve etkinliği gizle')} onClick={() => (store.set('task-side', '0'), d.render())}>
            <Ic n="chevR" s={16} />
          </button>
        )}
      </div>
      <div ref={stream} className="flxg-stream">
        {!items.length && <div className="flxg-empty-sm">{tt('Henüz yorum yok. İlk yorumu siz yazın.')}</div>}
        {items.map((it) =>
          it.c ? (
            <CommentNode key={'c' + (it.c._tmp ?? it.c.id)} cm={it.c} task={task} />
          ) : (
            <div key={'a' + it.a!.id} className="flxg-act">
              <span className="flxg-act-dot" />
              <span className="flxg-act-text">
                <ActivityText a={it.a!} task={task} />
              </span>
              <span className="flxg-muted" title={fullTime(it.a!.created)}>
                {ago(it.a!.created)}
              </span>
            </div>
          ),
        )}
      </div>
      {d.canComment ? (
        <Composer task={task} addFilesRef={addFiles} onSent={() => setTimeout(() => stream.current && (stream.current.scrollTop = stream.current.scrollHeight), 0)} />
      ) : (
        <div className="flxg-readonly is-block">
          <Ic n="lock" s={14} />
          {tt('Bu listede yorum yazma yetkiniz yok.')}
        </div>
      )}
      <div className="flxg-drop-over">
        <Ic n="upload" s={28} />
        <span>{tt('Yoruma eklemek için bırakın')}</span>
      </div>
      {D.pending.length > 0 && null}
    </div>
  );
}

/* ---------------- Yorum yazma kutusu ---------------- */
function Composer({ task, addFilesRef, onSent }: { task: Detail; addFilesRef: { current: (f: File[]) => void }; onSent: () => void }) {
  const d = useDr();
  const { c, D } = d;
  const { e, ui } = c;
  const ta = useRef<HTMLTextAreaElement>(null);
  const [, force] = useReducer((x: number) => x + 1, 0);
  const ms = useRef({ open: false, users: [] as FlxgUser[], active: 0, len: 0 });
  D.composerText = () => ta.current?.value ?? '';
  useLayoutEffect(() => {
    if (ta.current) {
      ta.current.value = D.draftFor === task.id ? D.draft : '';
      autosize(ta.current);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [task.id]);

  const addFiles = (list: File[]) => {
    for (const file of list) {
      if (file.size > e.S.config.maxUpload) {
        ui.toast(tt('{n} çok büyük (en fazla {s}).', { n: file.name, s: bytes(e.S.config.maxUpload) }), { error: true });
        continue;
      }
      const p: Pending = { file };
      if (/^image\//.test(file.type)) p.preview = URL.createObjectURL(file);
      D.pending.push(p);
      e.api.upload(file, { task: task.id, context: 'pending' }).then(
        (f) => ((p.id = f.id), force()),
        (er) => ((p.error = er instanceof Error ? er.message : String(er)), force()),
      );
    }
    force();
  };
  addFilesRef.current = addFiles;

  const closeMention = () => {
    if (ms.current.open) {
      ms.current.open = false;
      ui.closePop();
    }
  };
  const pickMention = (u: FlxgUser) => {
    const el = ta.current!;
    const p = el.selectionStart;
    const start = p - ms.current.len - 1;
    const label = '@' + u.name + ' ';
    el.value = el.value.slice(0, start) + label + el.value.slice(p);
    el.selectionStart = el.selectionEnd = start + label.length;
    D.mentions.push({ name: u.name, id: u.id });
    closeMention();
    el.focus();
  };
  const showMention = () => {
    const m = ms.current;
    ui.openPop(
      ta.current!,
      <div className="flxg-pick is-mention">
        <div className="flxg-pick-list">
          {m.users.map((u, i) => (
            <button key={u.id} type="button" className={cls('flxg-pick-item', i === m.active && 'is-active')} onMouseDown={(ev) => (ev.preventDefault(), pickMention(u))}>
              <Avatar u={u} size={22} />
              <span>{u.name}</span>
            </button>
          ))}
        </div>
      </div>,
      { inline: true, onClose: () => (m.open = false) },
    );
    m.open = true;
  };
  const mentionCheck = () => {
    const el = ta.current!;
    const mm = /(^|\s)@([^\s@]{0,30})$/.exec(el.value.slice(0, el.selectionStart));
    if (!mm) return closeMention();
    const q = norm(mm[2]);
    const users = e.S.userList.filter((u) => !q || norm(u.name).includes(q)).slice(0, 8);
    if (!users.length) return closeMention();
    ms.current.users = users;
    ms.current.active = 0;
    ms.current.len = mm[2]!.length;
    showMention();
  };
  const insertAt = (s: string) => {
    const el = ta.current!;
    const p = el.selectionStart;
    el.value = el.value.slice(0, p) + s + el.value.slice(el.selectionEnd);
    el.focus();
    el.selectionStart = el.selectionEnd = p + s.length;
  };

  const send = () => {
    const el = ta.current!;
    let body = el.value;
    for (const m of [...D.mentions].sort((a, b) => b.name.length - a.name.length)) body = body.split('@' + m.name).join('@{' + m.id + '}');
    const ready = D.pending.filter((p) => p.id);
    const files = ready.map((p) => p.id!);
    if (!body.trim() && !files.length) return;
    const tid = task.id;
    const tmp: FlxgComment = {
      id: 'tmp' + Date.now(),
      _tmp: 'c' + Date.now(),
      user: e.S.me!.id,
      body,
      created: new Date().toISOString(),
      files: ready.map((p) => ({ id: p.id!, name: p.file.name, size: p.file.size, mime: p.file.type, image: /^image\//.test(p.file.type) }) as FlxgFile),
      sending: true,
    };
    const draft = { text: el.value, pending: D.pending, mentions: D.mentions };
    task.comments_list.push(tmp);
    inflight(tid).comments_list.push(tmp);
    el.value = '';
    autosize(el);
    D.draft = '';
    D.pending = [];
    D.mentions = [];
    bumpCount(c, tid, 1);
    e.keepTask(tid);
    d.render();
    onSent();
    if (!d.mobile) el.focus();
    e.api.comment(tid, body, files).then(
      (cm) => {
        if (!cm || !cm.id) throw new Error(tt('Yorum kaydedilemedi.'));
        if (settle(D, tid, 'comments_list', tmp, cm)) {
          d.render();
          if (D.remoteStale && !D.pending.length && !ta.current?.value.trim()) void d.load(tid);
        }
      },
      (er) => {
        const open = settle(D, tid, 'comments_list', tmp, null);
        bumpCount(c, tid, -1);
        // Yazılanı geri koy ki kaybolmasın.
        D.draft = draft.text;
        D.draftFor = tid;
        if (open) {
          D.pending = draft.pending;
          D.mentions = draft.mentions;
          if (ta.current) ta.current.value = draft.text;
          d.render();
        }
        ui.fail(er);
      },
    );
  };

  const busy = D.pending.some((p) => !p.id && !p.error);
  return (
    <div className="flxg-composer">
      <div className="flxg-pending">
        {D.pending.map((p, i) => (
          <div key={i} className={cls('flxg-pfile', p.error && 'is-error')}>
            {p.preview ? <img src={p.preview} alt="" /> : <Ic n="file" s={18} />}
            <span className="flxg-pfile-name">{p.file.name}</span>
            {!p.id && !p.error && (
              <span className="flxg-pfile-bar">
                <span style={{ width: '60%' }} />
              </span>
            )}
            {p.error && <span className="flxg-pfile-err">{p.error}</span>}
            <button
              type="button"
              className="flxg-icon-btn is-sm"
              aria-label={tt('Kaldır')}
              onClick={() => {
                D.pending = D.pending.filter((x) => x !== p);
                if (p.id) e.api.deleteFile(p.id).catch(() => {});
                force();
              }}
            >
              <Ic n="x" s={14} />
            </button>
          </div>
        ))}
      </div>
      <textarea
        ref={ta}
        className="flxg-composer-input"
        rows={2}
        placeholder={tt('Yorum yazın… @ ile birinden bahsedin, dosya sürükleyin')}
        onInput={(ev) => {
          autosize(ev.currentTarget);
          D.draft = ev.currentTarget.value;
          D.draftFor = task.id;
          mentionCheck();
        }}
        onFocus={() => (D.editing = true)}
        onBlur={() => (D.editing = false)}
        onPaste={(ev) => {
          const files = Array.from(ev.clipboardData?.files ?? []);
          if (files.length) {
            ev.preventDefault();
            addFiles(files.map((f, i) => (f.name && f.name !== 'image.png' ? f : new File([f], 'ekran-' + Date.now() + '-' + i + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }))));
          }
        }}
        onKeyDown={(ev) => {
          const m = ms.current;
          if (m.open) {
            if (ev.key === 'Enter' || ev.key === 'Tab') {
              ev.preventDefault();
              pickMention(m.users[m.active]!);
              return;
            }
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
              ev.preventDefault();
              m.active = (m.active + (ev.key === 'ArrowDown' ? 1 : -1) + m.users.length) % m.users.length;
              showMention();
              return;
            }
          }
          if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
            ev.preventDefault();
            send();
          }
        }}
      />
      <div className="flxg-composer-bar">
        <label className="flxg-icon-btn" title={tt('Dosya ekle')}>
          <Ic n="clip" s={17} />
          <input
            type="file"
            multiple
            hidden
            onChange={(ev) => {
              addFiles(Array.from(ev.target.files ?? []));
              ev.target.value = '';
            }}
          />
        </label>
        <button type="button" className="flxg-icon-btn" title={tt('Birinden bahset')} onClick={() => (insertAt('@'), mentionCheck())}>
          <Ic n="at" s={17} />
        </button>
        <span className="flxg-grow" />
        <span className="flxg-muted flxg-hint">{tt('Ctrl+Enter ile gönder')}</span>
        <button type="button" className="flxg-btn is-primary is-sm" disabled={busy} onClick={send}>
          <Ic n="send" s={14} />
          <span>{tt('Gönder')}</span>
        </button>
      </div>
    </div>
  );
}

/* =====================================================================
 * Yapay zekâ (görev için)
 * ================================================================== */
const aiPresets = () => [
  { action: 'description', label: tt('Açıklama yaz / geliştir'), hint: '' },
  { action: 'description', label: tt('Kısalt'), hint: 'Make it shorter and clearer, keep every important point.' },
  { action: 'description', label: tt('Kontrol listesine çevir'), hint: 'Turn it into a checklist: <ul data-checklist="1"> with <li data-checked="0"> items.' },
  { action: 'description', label: tt('Yazımı düzelt'), hint: 'Only fix spelling, grammar and punctuation. Keep the meaning, wording and structure.' },
  { action: 'description', label: tt('Daha detaylı yaz'), hint: 'Make it more detailed: goal, steps and done criteria.' },
  { action: 'title', label: tt('Görev adını iyileştir'), hint: '' },
  { action: 'subtasks', label: tt('Alt görevlere böl'), hint: '' },
  { action: 'summary', label: tt('Yorumları özetle'), hint: '' },
];
function AiPanel({ task }: { task: Detail }) {
  const d = useDr();
  const { c, D, canEdit: edit, save } = d;
  const { e, ui } = c;
  const st = D.ai!;
  const ask = useRef<HTMLInputElement>(null);
  const titleIn = useRef<HTMLInputElement>(null);
  const [subs, setSubs] = useState<{ on: boolean; t: string }[]>([]);
  useEffect(() => {
    if (st.state === 'result' && st.action === 'subtasks') setSubs((st.result?.items ?? []).map((t) => ({ on: true, t })));
  }, [st.state, st.action, st.result]);
  useEffect(() => {
    if (edit && st.state === 'menu' && !d.mobile) setTimeout(() => ask.current?.focus(), 30);
  }, [st.state, edit, d.mobile]);
  const run = (action: string, hint: string, label: string) => {
    Object.assign(st, { state: 'loading', action, hint, label, result: null, applied: null, error: '' });
    d.render();
    e.api.ai(task.id, action, hint).then(
      (r) => {
        if (D.ai !== st) return;
        st.state = 'result';
        st.result = r;
        d.render();
      },
      (er) => {
        if (D.ai !== st) return;
        st.state = 'error';
        st.error = er instanceof Error ? er.message : String(er);
        d.render();
      },
    );
  };
  const go = () => {
    const v = ask.current?.value.trim();
    if (!v) return;
    const action = st.result && st.action !== 'summary' ? st.action! : 'description';
    run(action, (st.result && st.hint ? st.hint + '\n' : '') + v, v);
  };
  const applied = (msg: string, undo?: () => void) => {
    st.applied = msg;
    st.undo = undo ?? null;
    d.render();
  };
  const askRow = (
    <div className="flxg-ai-ask">
      <Ic n="sparkle" s={15} />
      <input
        ref={ask}
        className="flxg-input is-sm"
        type="text"
        placeholder={st.result ? tt('Değiştirmek istediğini yaz… (örn. daha resmi olsun)') : tt('Ne yapılsın? Örn: hedef kitleyi ekle, maddeler halinde yaz')}
        onKeyDown={(ev) => ev.key === 'Enter' && (ev.preventDefault(), go())}
      />
      <button type="button" className="flxg-btn is-primary is-sm" onClick={go}>
        <Ic n="send" s={13} />
      </button>
    </div>
  );
  const head = (
    <div className="flxg-ai-inline-head">
      <Ic n="sparkle" s={15} />
      <b>{tt('Yapay zekâ')}</b>
      {st.label && <span className="flxg-muted flxg-ai-label">{st.label}</span>}
      <span className="flxg-grow" />
      <button type="button" className="flxg-icon-btn is-sm" aria-label={tt('Kapat')} onClick={() => ((D.ai = null), d.render())}>
        <Ic n="x" s={14} />
      </button>
    </div>
  );
  if (st.state === 'menu' || st.state === 'error')
    return (
      <div className="flxg-ai-inline">
        {head}
        {st.state === 'error' && st.error && <p className="flxg-error">{st.error}</p>}
        <div className="flxg-ai-chips">
          {aiPresets()
            .filter((p) => p.action === 'summary' || edit)
            .map((p, i) => (
              <button key={i} type="button" className="flxg-chip-btn" onClick={() => run(p.action, p.hint, p.label)}>
                {p.label}
              </button>
            ))}
        </div>
        {edit && askRow}
      </div>
    );
  if (st.state === 'loading')
    return (
      <div className="flxg-ai-inline">
        {head}
        <div className="flxg-ai-thinking">
          <span className="flxg-ai-dots">
            <i />
            <i />
            <i />
          </span>
          {tt('Yapay zekâ düşünüyor…')}
        </div>
        <div className="flxg-ai-sk">
          {['92%', '80%', '86%', '54%'].map((w) => (
            <span key={w} className="flxg-sk" style={{ display: 'block', width: w, height: 11 }} />
          ))}
        </div>
      </div>
    );
  const r = st.result ?? {};
  const oldD = task.description || '';
  let body: ReactNode = null;
  let foot: ReactNode = null;
  if (st.action === 'title') {
    body = <input ref={titleIn} className="flxg-input" type="text" defaultValue={r.text} />;
    if (!st.applied)
      foot = (
        <button
          type="button"
          className="flxg-btn is-primary is-sm"
          onClick={() => {
            const old = task.title;
            void save({ title: titleIn.current!.value.trim() }).then(() => applied(tt('Görev adı güncellendi.'), () => void save({ title: old })));
          }}
        >
          {tt('Uygula')}
        </button>
      );
  } else if (st.action === 'description') {
    if (!st.applied) {
      body = <div className="flxg-prose flxg-ai-preview" dangerouslySetInnerHTML={{ __html: r.html ?? '' }} />;
      foot = (
        <>
          <button type="button" className="flxg-btn is-primary is-sm" onClick={() => void save({ description: r.html }).then(() => applied(tt('Açıklama güncellendi.'), () => void save({ description: oldD })))}>
            {oldD ? tt('Değiştir') : tt('Uygula')}
          </button>
          {oldD && (
            <button type="button" className="flxg-btn is-sm" onClick={() => void save({ description: oldD + (r.html ?? '') }).then(() => applied(tt('Açıklamanın sonuna eklendi.'), () => void save({ description: oldD })))}>
              {tt('Sona ekle')}
            </button>
          )}
        </>
      );
    }
  } else if (st.action === 'subtasks') {
    body = (
      <div className="flxg-ai-subs">
        {subs.map((s, i) => (
          <label key={i} className="flxg-ai-sub">
            <input type="checkbox" checked={s.on} onChange={(ev) => setSubs(subs.map((x, j) => (j === i ? { ...x, on: ev.target.checked } : x)))} />
            <input className="flxg-input is-sm" type="text" value={s.t} onChange={(ev) => setSubs(subs.map((x, j) => (j === i ? { ...x, t: ev.target.value } : x)))} />
          </label>
        ))}
      </div>
    );
    if (!st.applied)
      foot = (
        <button
          type="button"
          className="flxg-btn is-primary is-sm"
          onClick={async (ev) => {
            ev.currentTarget.disabled = true;
            const list = subs.filter((s) => s.on && s.t.trim());
            try {
              for (const s of list) await e.api.create({ list: task.list, parent: task.id, title: s.t.trim() });
              st.applied = tt('{n} alt görev oluşturuldu', { n: list.length });
              void d.load(task.id);
              void e.reloadTasks();
            } catch (er) {
              ui.fail(er);
            }
          }}
        >
          {tt('Alt görevleri oluştur')}
        </button>
      );
  } else {
    body = <div className="flxg-prose flxg-ai-preview" dangerouslySetInnerHTML={{ __html: richText(r.text ?? '', e.S.users, e.S.me?.id ?? 0) }} />;
    foot = (
      <>
        <button type="button" className="flxg-btn is-sm" onClick={() => copyText(c, r.text ?? '')}>
          <Ic n="copy" s={13} />
          {tt('Kopyala')}
        </button>
        {d.canComment && !st.applied && (
          <button
            type="button"
            className="flxg-btn is-primary is-sm"
            onClick={() =>
              e.api.comment(task.id, '✨ ' + tt('Özet') + '\n' + (r.text ?? ''), []).then(() => {
                bumpCount(c, task.id, 1);
                applied(tt('Yorum olarak eklendi.'));
                void d.load(task.id);
              }, ui.fail)
            }
          >
            {tt('Yorum olarak ekle')}
          </button>
        )}
      </>
    );
  }
  return (
    <div className="flxg-ai-inline">
      {head}
      <div className="flxg-ai-body">
        {st.applied && (
          <div className="flxg-ai-done">
            <Ic n="check" s={15} />
            <span>{st.applied}</span>
            {st.undo && (
              <button
                type="button"
                className="flxg-link-btn"
                onClick={() => {
                  const u = st.undo!;
                  st.undo = null;
                  st.applied = null;
                  u();
                }}
              >
                {tt('Geri al')}
              </button>
            )}
          </div>
        )}
        {body}
      </div>
      <div className="flxg-ai-foot-row">
        {foot}
        <span className="flxg-grow" />
        <button type="button" className="flxg-btn is-ghost is-sm" onClick={() => (Object.assign(st, { state: 'menu', result: null, applied: null, label: '' }), d.render())}>
          {tt('Diğer seçenekler')}
        </button>
        <button type="button" className="flxg-btn is-ghost is-sm" onClick={() => run(st.action!, st.hint ?? '', st.label ?? '')}>
          <Ic n="sparkle" s={13} />
          {tt('Yeniden üret')}
        </button>
      </div>
      {edit && st.action !== 'summary' && askRow}
    </div>
  );
}
