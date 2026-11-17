// Workspace panel beside the chat: this chat's folder, the assistant's changes (commits and change requests with diffs), access settings.
import { Check, ChevronRight, ExternalLink, File as FileIcon, Folder, GitCommit, GitPullRequest, RefreshCw, Shield, X, ArrowLeft } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactElement } from 'react';
import { Loading, Modal } from '@fluxwp/ui';
import { useAi } from '../common';
import type { ChatApi } from './api';
import { Markdown } from './Markdown';
import type { Boot, Change, ChangeFile, WsFile, WsItem, WsState } from './types';

export type WsTab = 'files' | 'changes' | 'settings';

/** Line diff: common head/tail trimmed, the middle by LCS (huge middles: all removed / all added). */
export function diffLines(a: string | null | undefined, b: string | null | undefined): Array<[string, string]> {
  const A = a == null ? [] : String(a).split('\n');
  const B = b == null ? [] : String(b).split('\n');
  if (a == null) return B.map((l) => ['+', l]);
  if (b == null) return A.map((l) => ['-', l]);
  let s = 0;
  let ea = A.length;
  let eb = B.length;
  const out: Array<[string, string]> = [];
  const tail: Array<[string, string]> = [];
  while (s < ea && s < eb && A[s] === B[s]) {
    out.push([' ', A[s] ?? '']);
    s++;
  }
  while (ea > s && eb > s && A[ea - 1] === B[eb - 1]) {
    tail.unshift([' ', A[ea - 1] ?? '']);
    ea--;
    eb--;
  }
  const n = ea - s;
  const m = eb - s;
  if (n * m > 4000000) {
    for (let i = s; i < ea; i++) out.push(['-', A[i] ?? '']);
    for (let j = s; j < eb; j++) out.push(['+', B[j] ?? '']);
    return out.concat(tail);
  }
  const L: Uint32Array[] = [];
  for (let x = n; x >= 0; x--) L[x] = new Uint32Array(m + 1);
  for (let x = n - 1; x >= 0; x--)
    for (let y = m - 1; y >= 0; y--) (L[x] as Uint32Array)[y] = A[s + x] === B[s + y] ? ((L[x + 1] as Uint32Array)[y + 1] ?? 0) + 1 : Math.max((L[x + 1] as Uint32Array)[y] ?? 0, (L[x] as Uint32Array)[y + 1] ?? 0);
  let x = 0;
  let y = 0;
  while (x < n && y < m) {
    if (A[s + x] === B[s + y]) {
      out.push([' ', A[s + x] ?? '']);
      x++;
      y++;
    } else if (((L[x + 1] as Uint32Array)[y] ?? 0) >= ((L[x] as Uint32Array)[y + 1] ?? 0)) out.push(['-', A[s + x++] ?? '']);
    else out.push(['+', B[s + y++] ?? '']);
  }
  while (x < n) out.push(['-', A[s + x++] ?? '']);
  while (y < m) out.push(['+', B[s + y++] ?? '']);
  return out.concat(tail);
}

function Diff({ f }: { f: ChangeFile }) {
  const { t } = useAi();
  const rows = useMemo(() => {
    if (f.big) return null;
    const d = diffLines(f.before, f.after);
    const keep: boolean[] = [];
    d.forEach((r, i) => {
      if (r[0] !== ' ') for (let k = Math.max(0, i - 3); k <= Math.min(d.length - 1, i + 3); k++) keep[k] = true;
    });
    const out: Array<{ k: string; op: string; a: number | ''; b: number | ''; text: string }> = [];
    let na = 0;
    let nb = 0;
    let gap = false;
    d.forEach((r, i) => {
      const op = r[0];
      if (op !== '+') na++;
      if (op !== '-') nb++;
      if (!keep[i]) {
        gap = true;
        return;
      }
      if (gap && out.length) out.push({ k: 'g' + i, op: 'gap', a: '', b: '', text: '⋯' });
      gap = false;
      out.push({ k: String(i), op, a: op === '+' ? '' : na, b: op === '-' ? '' : nb, text: r[1] });
    });
    return out;
  }, [f]);
  if (!rows) return <div className="flxai-ws-empty">{t('The file is too large to show a diff.')}</div>;
  if (!rows.length) return <div className="flxai-ws-empty">{t('No difference.')}</div>;
  return (
    <div className="flxai-diff">
      {rows.map((r) => (
        <div key={r.k} className={'flxai-dln' + (r.op === '+' ? ' is-add' : r.op === '-' ? ' is-del' : r.op === 'gap' ? ' is-gap' : '')}>
          <i>{r.a}</i>
          <i>{r.b}</i>
          <b>{r.op === 'gap' ? '' : r.op}</b>
          <code>{r.text}</code>
        </div>
      ))}
    </div>
  );
}

/* WordPress' CodeMirror (same as Flux Files), loaded when first needed. */
type CMInst = { getValue: () => string; on: (ev: string, fn: () => void) => void; addKeyMap: (m: Record<string, () => void>) => void; refresh: () => void; getWrapperElement: () => HTMLElement; focus: () => void };
type CMLib = (el: HTMLElement, opts: Record<string, unknown>) => CMInst;
let cmPromise: Promise<CMLib | null> | null = null;
const cmLib = (): CMLib | null => ((window as unknown as { wp?: { CodeMirror?: CMLib } }).wp?.CodeMirror ?? null);
function loadCm(boot: Boot | null): Promise<CMLib | null> {
  const lib = cmLib();
  if (lib) return Promise.resolve(lib);
  const cm = boot?.cm;
  if (!cm?.js) return Promise.resolve(null);
  if (!cmPromise) {
    cmPromise = new Promise<CMLib | null>((res) => {
      const w = window as unknown as { wp?: object };
      w.wp = w.wp || {};
      if (cm.css && !document.querySelector('link[data-flxai-cm]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = cm.css;
        l.setAttribute('data-flxai-cm', '1');
        document.head.appendChild(l);
      }
      const sc = document.createElement('script');
      sc.src = cm.js;
      sc.onload = () => res(cmLib());
      sc.onerror = () => res(null);
      document.head.appendChild(sc);
    });
  }
  return cmPromise;
}
const CM_MODES: Record<string, string> = { php: 'application/x-httpd-php', js: 'text/javascript', mjs: 'text/javascript', jsx: 'text/jsx', ts: 'text/typescript', json: 'application/json', css: 'text/css', scss: 'text/x-scss', html: 'text/html', htm: 'text/html', svg: 'application/xml', xml: 'application/xml', md: 'text/x-gfm', markdown: 'text/x-gfm', sql: 'text/x-mysql', yml: 'text/x-yaml', yaml: 'text/x-yaml', sh: 'text/x-sh', diff: 'text/x-diff' };

function Code({ boot, path, value, edit, onChange, onSave }: { boot: Boot | null; path: string; value: string; edit: boolean; onChange: (v: string) => void; onSave: () => void }) {
  const slot = useRef<HTMLDivElement>(null);
  const [lib, setLib] = useState<CMLib | null>(cmLib());
  const cb = useRef({ onChange, onSave });
  cb.current = { onChange, onSave };
  useEffect(() => {
    let live = true;
    void loadCm(boot).then((l) => live && setLib(() => l));
    return () => {
      live = false;
    };
  }, [boot]);
  useEffect(() => {
    if (!lib || !slot.current) return;
    const ext = (path.split('.').pop() ?? '').toLowerCase();
    const cm = lib(document.createElement('div'), { value, mode: CM_MODES[ext] ?? 'text/plain', lineNumbers: true, lineWrapping: true, readOnly: !edit, indentWithTabs: true, tabSize: 4, indentUnit: 4, styleActiveLine: edit });
    cm.on('change', () => cb.current.onChange(cm.getValue()));
    const save = () => cb.current.onSave();
    cm.addKeyMap({ 'Ctrl-S': save, 'Cmd-S': save });
    const wrap = cm.getWrapperElement();
    slot.current.appendChild(wrap);
    cm.refresh();
    if (edit) cm.focus();
    return () => wrap.remove();
    // The editor is re-created per file and mode; typing does not rebuild it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lib, path, edit]);
  if (lib) return <div ref={slot} className="flxai-cm" />;
  if (edit)
    return (
      <textarea
        className="flxai-ws-edit"
        spellCheck={false}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={(e) => {
          if ((e.metaKey || e.ctrlKey) && e.key === 's') {
            e.preventDefault();
            onSave();
          }
        }}
      />
    );
  return (
    <div className="flxai-diff is-view">
      {value.split('\n').map((l, i) => (
        <div key={i} className="flxai-dln">
          <i>{i + 1}</i>
          <code>{l}</code>
        </div>
      ))}
    </div>
  );
}

interface View {
  path: string;
  file?: WsFile;
  err?: string;
  mode: 'preview' | 'code' | 'edit';
  draft: string | null;
  stamp: number;
}

export interface WsFocus {
  change?: number;
  report?: string;
  n: number;
}

export function Workspace({ api, boot, chatId, tab, setTab, focus, reloadKey, onClose, onDecided, rest }: { api: ChatApi; boot: Boot | null; chatId: number; tab: WsTab; setTab: (t: WsTab) => void; focus: WsFocus; reloadKey: number; onClose: () => void; onDecided: () => void; rest: boolean }) {
  const { t, tn, num, md } = useAiWs();
  const [data, setData] = useState<WsState | null>(null);
  const [err, setErr] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [kids, setKids] = useState<Record<string, WsItem[] | 'load' | { err: string }>>({});
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [view, setView] = useState<View | null>(null);
  const [openCh, setOpenCh] = useState<Record<number, boolean>>({});
  const [detail, setDetail] = useState<Record<number, Change | { err: string }>>({});
  const [asking, setAsking] = useState<number | null>(null);
  const [access, setAccess] = useState<'workspace' | 'site'>('workspace');
  const [folders, setFolders] = useState('');
  const noteT = useRef(0);
  const flash = (m: string, ms = 0) => {
    setNote(m);
    window.clearTimeout(noteT.current);
    if (ms) noteT.current = window.setTimeout(() => setNote(''), ms);
  };
  const msg = (e: unknown) => (e instanceof Error ? e.message : String(e));

  const loadDetail = useCallback(
    (id: number) => {
      api.change(id).then((r) => setDetail((d) => ({ ...d, [id]: r }))).catch((e: unknown) => setDetail((d) => ({ ...d, [id]: { err: msg(e) } })));
    },
    [api],
  );
  const loadKids = useCallback(
    (path: string) => {
      setKids((k) => ({ ...k, [path]: k[path] ?? 'load' }));
      api.wsList(chatId, path).then((r) => setKids((k) => ({ ...k, [path]: r.items }))).catch((e: unknown) => setKids((k) => ({ ...k, [path]: { err: msg(e) } })));
    },
    [api, chatId],
  );

  // Chat switched: everything starts over.
  useEffect(() => {
    setData(null);
    setKids({});
    setOpen({});
    setView(null);
    setOpenCh({});
    setDetail({});
    setErr('');
    setNote('');
  }, [chatId]);

  // Load / refresh (after every turn).
  useEffect(() => {
    if (!chatId) return;
    let live = true;
    api
      .ws(chatId)
      .then((d) => {
        if (!live) return;
        setData(d);
        setAccess(d.access);
        setFolders(d.folders.join('\n'));
        setDetail({});
        setOpenCh((o) => {
          Object.keys(o).forEach((k) => o[+k] && loadDetail(+k));
          return o;
        });
        setOpen((o) => {
          Object.keys(o).forEach((p) => o[p] && loadKids(p));
          return o;
        });
      })
      .catch((e: unknown) => live && setErr(msg(e)));
    return () => {
      live = false;
    };
  }, [api, chatId, reloadKey, loadDetail, loadKids]);

  const openFile = useCallback(
    (path: string, mode: View['mode']) => {
      setView({ path, mode, draft: null, stamp: Date.now() });
      api.wsFile(chatId, path).then((file) => setView((v) => (v && v.path === path ? { ...v, file } : v))).catch((e: unknown) => setView((v) => (v && v.path === path ? { ...v, err: msg(e) } : v)));
    },
    [api, chatId],
  );

  // A chat link (change / report) was clicked.
  useEffect(() => {
    if (!focus.n) return;
    if (focus.change) {
      setTab('changes');
      setOpenCh((o) => ({ ...o, [focus.change as number]: true }));
      loadDetail(focus.change);
    } else if (focus.report) {
      setTab('files');
      openFile(focus.report, 'preview');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [focus.n]);

  const decide = (id: number, merge: boolean, path?: string) => {
    if (busy) return;
    setBusy(true);
    flash('');
    api
      .decideChange(id, merge, path)
      .then((r) => {
        setBusy(false);
        const n: string[] = [];
        if (r.conflicts?.length) n.push(t('The file changed in the meantime and was not applied: %s', r.conflicts.join(', ')));
        if (r.errors) Object.entries(r.errors).forEach(([p, m]) => n.push(p + ': ' + m));
        flash(n.join(' · '));
        setOpenCh((o) => ({ ...o, [id]: true }));
        void api.ws(chatId).then((d) => {
          setData(d);
          setDetail({});
          loadDetail(id);
        });
        if (r.change.status !== 'open') onDecided();
      })
      .catch((e: unknown) => {
        setBusy(false);
        flash(msg(e));
      });
  };

  const save = () => {
    const v = view;
    if (!v?.file || v.draft == null || busy) return;
    const txt = v.draft;
    setBusy(true);
    api
      .wsSave(chatId, v.path, txt)
      .then(() => {
        setBusy(false);
        setView((x) => (x && x.file ? { ...x, file: { ...x.file, content: txt }, draft: null } : x));
        flash(t('Saved.'), 2000);
      })
      .catch((e: unknown) => {
        setBusy(false);
        flash(msg(e));
      });
  };

  const saveSettings = () => {
    setBusy(true);
    api
      .wsSettings(chatId, access, folders)
      .then((d) => {
        setBusy(false);
        setData(d);
        flash(t('Saved.'), 2500);
      })
      .catch((e: unknown) => {
        setBusy(false);
        flash(msg(e));
      });
  };

  const pending = data ? data.changes.filter((c) => c.kind === 'pr' && c.status === 'open') : [];

  const tree = (items: WsItem[] | 'load' | { err: string } | undefined, depth: number): ReactElement | null => {
    if (items === 'load' || !items) return <Loading />;
    if ('err' in items) return <div className="flxai-ws-empty">{items.err}</div>;
    if (!items.length) return depth ? <div className="flxai-ws-row is-none" style={{ ['--d' as string]: depth }}>{t('Empty folder')}</div> : null;
    return (
      <>
        {items.map((it) => {
          const dir = it.type === 'dir';
          const on = dir && open[it.path];
          return (
            <div key={it.path}>
              <button
                type="button"
                className={'flxai-ws-row' + (dir ? ' is-dir' : '') + (on ? ' is-on' : '')}
                style={{ ['--d' as string]: depth }}
                onClick={() => {
                  if (dir) {
                    setOpen((o) => ({ ...o, [it.path]: !o[it.path] }));
                    if (!open[it.path]) loadKids(it.path);
                  } else openFile(it.path, /\.(md|markdown|html?)$/i.test(it.path) ? 'preview' : 'code');
                }}
              >
                <span className="car">{dir ? <ChevronRight size={12} /> : null}</span>
                {dir ? <Folder size={14} /> : <FileIcon size={14} />}
                <span className="nm">{it.name}</span>
                <small>{dir ? (it.count != null ? it.count : '') : it.ago}</small>
              </button>
              {on && tree(kids[it.path], depth + 1)}
            </div>
          );
        })}
      </>
    );
  };

  const opLabel = (op: string) => ({ add: t('added'), edit: t('changed'), delete: t('deleted') })[op] ?? op;
  const status = (c: Change) => {
    const m: Record<string, [string, string]> = { open: [t('Waiting for approval'), 'warn'], merged: [t('Merged'), 'ok'], rejected: [t('Rejected'), ''], partial: [t('Partly merged'), 'ok'], done: [t('Saved'), ''] };
    const x = m[c.status] ?? [c.status, ''];
    return <span className={'flxai-pill ' + x[1]}>{x[0]}</span>;
  };

  const filesTab = () => {
    if (!data) return null;
    if (view) {
      const f = view.file;
      const isHtml = /\.html?$/i.test(view.path);
      const isMd = isHtml || /\.(md|markdown)$/i.test(view.path);
      const modes: View['mode'][] = [...(isMd ? (['preview'] as const) : []), ...(f?.editable ? (['edit'] as const) : isMd ? (['code'] as const) : [])];
      const dirty = view.draft != null && f && view.draft !== f.content;
      const viewUrl = boot?.view && rest ? `${boot.view.url.replace('%d', String(chatId))}${boot.view.url.includes('?') ? '&' : '?'}path=${encodeURIComponent(view.path)}&_wpnonce=${encodeURIComponent(boot.view.nonce)}&v=${view.stamp}` : '';
      return (
        <>
          <div className="flxai-ws-path">
            <button type="button" className="flxai-ibtn" title={t('Back')} aria-label={t('Back')} onClick={() => setView(null)}>
              <ArrowLeft size={15} />
            </button>
            <code>{view.path}</code>
            {f?.files && (
              <a className="flxai-ibtn" href={f.files} target="_blank" rel="noopener" title={t('Open in Flux Files')} aria-label={t('Open in Flux Files')}>
                <ExternalLink size={14} />
              </a>
            )}
          </div>
          {view.err ? (
            <div className="flxai-ws-empty">{view.err}</div>
          ) : !f ? (
            <Loading />
          ) : (
            <>
              {(modes.length > 1 || f.editable) && (
                <div className="flxai-ws-vbar">
                  <div className="flxai-seg">
                    {modes.map((m) => (
                      <button key={m} type="button" className={view.mode === m ? 'is-on' : ''} onClick={() => setView({ ...view, mode: m })}>
                        {{ preview: t('Preview'), code: t('Code'), edit: t('Edit') }[m]}
                      </button>
                    ))}
                  </div>
                  {view.mode === 'edit' && (
                    <>
                      <span className="sp" />
                      {dirty && (
                        <button type="button" className="flxai-btn" onClick={() => setView({ ...view, draft: null })}>
                          {t('Discard')}
                        </button>
                      )}
                      <button type="button" className="flxai-btn is-primary" disabled={!dirty || busy} onClick={save}>
                        {t('Save')}
                      </button>
                    </>
                  )}
                </div>
              )}
              {view.mode === 'preview' && isHtml ? (
                viewUrl ? (
                  <div className="flxai-ws-html">
                    <iframe sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" referrerPolicy="no-referrer" title={view.path} src={viewUrl} />
                    <a className="flxai-btn" href={viewUrl} target="_blank" rel="noopener">
                      {t('Open full screen')}
                    </a>
                  </div>
                ) : (
                  <Code boot={boot} path={view.path} value={f.content} edit={false} onChange={() => {}} onSave={() => {}} />
                )
              ) : view.mode === 'preview' ? (
                <div className="flxai-ws-md">{md(f.content)}</div>
              ) : (
                <Code key={view.path + view.mode} boot={boot} path={view.path} value={view.draft ?? f.content} edit={view.mode === 'edit'} onChange={(v) => setView((x) => (x ? { ...x, draft: v } : x))} onSave={save} />
              )}
            </>
          )}
        </>
      );
    }
    return (
      <>
        <div className="flxai-ws-path">
          <Folder size={14} />
          <code title={data.dir}>{data.dir}</code>
          {data.files && (
            <a className="flxai-ibtn" href={data.files} target="_blank" rel="noopener" title={t('Open in Flux Files')} aria-label={t('Open in Flux Files')}>
              <ExternalLink size={14} />
            </a>
          )}
        </div>
        {data.items && data.items.length ? <div className="flxai-ws-tree">{tree(data.items, 0)}</div> : <div className="flxai-ws-empty">{t('The folder is empty. Notes, reports and drafts the assistant writes show up here.')}</div>}
        {data.access === 'workspace' && data.folders.length > 0 && (
          <>
            <div className="flxai-ws-sub">{t('Allowed folders')}</div>
            <div className="flxai-ws-tree">
              {data.folders.map((f) => (
                <div key={f}>
                  <button
                    type="button"
                    className={'flxai-ws-row is-dir' + (open[f] ? ' is-on' : '')}
                    style={{ ['--d' as string]: 0 }}
                    onClick={() => {
                      setOpen((o) => ({ ...o, [f]: !o[f] }));
                      if (!open[f]) loadKids(f);
                    }}
                  >
                    <span className="car">
                      <ChevronRight size={12} />
                    </span>
                    <Folder size={14} />
                    <span className="nm">{f}</span>
                  </button>
                  {open[f] && tree(kids[f], 1)}
                </div>
              ))}
            </div>
          </>
        )}
      </>
    );
  };

  const changeCard = (c: Change) => {
    const pr = c.kind === 'pr';
    const live = pr && c.status === 'open';
    const add = c.files.reduce((a, f) => a + f.add, 0);
    const del = c.files.reduce((a, f) => a + f.del, 0);
    const det = detail[c.id];
    return (
      <article key={c.id} className={'flxai-ch' + (live ? ' is-open' : '')}>
        <button
          type="button"
          className="flxai-ch-h"
          onClick={() => {
            const on = !openCh[c.id];
            setOpenCh({ ...openCh, [c.id]: on });
            if (on) loadDetail(c.id);
          }}
        >
          {pr ? <GitPullRequest size={15} /> : <GitCommit size={15} />}
          <span className="flxai-ch-t">
            <b>{c.title || (pr ? t('Change request') : t('Change'))}</b>
            <small>
              {(pr ? '#' + c.id + ' · ' : '') + tn('1 file', '%s files', c.files.length, num(c.files.length))} · {c.ago} <span className="n is-add">+{add}</span> <span className="n is-del">−{del}</span>
            </small>
          </span>
          {status(c)}
        </button>
        {live && (
          <div className="flxai-ch-acts">
            <button type="button" className="flxai-btn is-primary" disabled={busy} onClick={() => decide(c.id, true)}>
              <Check size={14} /> {t('Merge')}
            </button>
            <button type="button" className="flxai-btn" disabled={busy} onClick={() => setAsking(c.id)}>
              {t('Reject')}
            </button>
          </div>
        )}
        {openCh[c.id] && (
          <div className="flxai-ch-files">
            {!det ? (
              <Loading />
            ) : 'err' in det ? (
              <div className="flxai-ws-empty">{det.err}</div>
            ) : (
              det.files.map((f) => (
                <div key={f.path} className="flxai-cf">
                  <div className="flxai-cf-h">
                    <span className={'op ' + f.op}>{opLabel(f.op)}</span>
                    <code title={f.path}>
                      <bdi>{f.path}</bdi>
                    </code>
                    {f.state && <span className={'flxai-pill ' + (f.state === 'merged' ? 'ok' : '')}>{f.state === 'merged' ? t('Merged') : t('Rejected')}</span>}
                    {live && !f.state && (
                      <span className="flxai-cf-a">
                        <button type="button" className="flxai-link" disabled={busy} onClick={() => decide(c.id, true, f.path)}>
                          {t('Merge this one')}
                        </button>
                        <button type="button" className="flxai-link" disabled={busy} onClick={() => decide(c.id, false, f.path)}>
                          {t('Reject')}
                        </button>
                      </span>
                    )}
                  </div>
                  <Diff f={f} />
                </div>
              ))
            )}
          </div>
        )}
      </article>
    );
  };

  const settingsTab = () => {
    if (!data) return null;
    return (
      <>
        <form
          className="flxai-ws-set"
          onSubmit={(e) => {
            e.preventDefault();
            saveSettings();
          }}
        >
          <p className="flxai-ws-sub">{data.task > 0 ? t("This task's file access") : t("This chat's file access")}</p>
          <label className="flxai-opt">
            <input type="radio" name="access" checked={access === 'workspace'} onChange={() => setAccess('workspace')} />
            <span>
              <b>{t('Its own folder only')}</b>
              <small>{t('The assistant sees only this chat\'s folder and the folders you allow below.')}</small>
            </span>
          </label>
          <label className="flxai-opt">
            <input type="radio" name="access" checked={access === 'site'} onChange={() => setAccess('site')} />
            <span>
              <b>{t('Whole site readable')}</b>
              <small>{t('The assistant can read the site\'s files; every file it wants to change comes to you as a change request.')}</small>
            </span>
          </label>
          <label className="flxai-fl">
            <b>{t('Allowed folders')}</b>
            <textarea rows={3} placeholder="wp-content/themes/fluxwp" value={folders} onChange={(e) => setFolders(e.target.value)} />
            <small>{t('One folder per line (relative to the site root). Changes in these folders come to you for approval too.')}</small>
          </label>
          <button type="submit" className="flxai-btn is-primary" disabled={busy}>
            {t('Save')}
          </button>
        </form>
        <div className="flxai-ws-rules">
          <Shield size={16} />
          <div>
            <b>{t('Rules that always apply')}</b>
            <ul>
              <li>{t('The assistant cannot change any file outside its own folder directly.')}</li>
              <li>{t('Other chats\' and tasks\' folders are closed; skill files are read-only.')}</li>
              <li>{t('Files with passwords or keys (wp-config, .env, .sql) and WordPress core are never opened.')}</li>
              <li>{t('At most 50 files per turn, at most 1 MB per file.')}</li>
            </ul>
            <p>
              <code>{data.dir}</code>
            </p>
          </div>
        </div>
      </>
    );
  };

  return (
    <aside className="flxai-ws">
      <div className="flxai-ws-head">
        <div className="flxai-ws-tabs" role="tablist">
          {(['files', 'changes', 'settings'] as const).map((k) => (
            <button key={k} type="button" role="tab" aria-selected={tab === k} className={tab === k ? 'is-on' : ''} onClick={() => { setTab(k); flash(''); }}>
              {{ files: t('Files'), changes: t('Changes'), settings: t('Settings') }[k]}
              {k === 'changes' && pending.length > 0 && <b>{pending.length}</b>}
            </button>
          ))}
        </div>
        <button type="button" className="flxai-ibtn" title={t('Refresh')} aria-label={t('Refresh')} onClick={() => chatId && api.ws(chatId).then(setData).catch((e: unknown) => setErr(msg(e)))}>
          <RefreshCw size={14} />
        </button>
        <button type="button" className="flxai-ibtn" title={t('Close')} aria-label={t('Close')} onClick={onClose}>
          <X size={15} />
        </button>
      </div>
      <div className="flxai-ws-body">
        {note && <div className="flxai-ws-note">{note}</div>}
        {!chatId ? (
          <div className="flxai-ws-empty is-big">
            <Folder size={26} />
            <p>{t("Once the chat starts, this chat's folder and the assistant's changes show up here.")}</p>
          </div>
        ) : err && !data ? (
          <div className="flxai-ws-empty">{err}</div>
        ) : !data ? (
          <Loading />
        ) : !data.ready ? (
          <div className="flxai-ws-empty is-big">
            <Folder size={26} />
            <p>{t('The workspace needs the Flux Files plugin. Install it from FluxWP Panel › Packages.')}</p>
          </div>
        ) : tab === 'files' ? (
          filesTab()
        ) : tab === 'changes' ? (
          data.changes.length ? (
            data.changes.map(changeCard)
          ) : (
            <div className="flxai-ws-empty is-big">
              <GitCommit size={26} />
              <p>{t("No changes yet. What the assistant writes to its own folder shows up as a commit, what it proposes for the site's files as a change request.")}</p>
            </div>
          )
        ) : (
          settingsTab()
        )}
      </div>
      {asking != null && (
        <Modal title={t('Reject this change request?')} onClose={() => setAsking(null)}>
          <p>{t('The files stay as they are; the assistant is told it was rejected.')}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxai-btn is-danger"
              onClick={() => {
                const id = asking;
                setAsking(null);
                decide(id, false);
              }}
            >
              {t('Reject')}
            </button>
          </div>
        </Modal>
      )}
    </aside>
  );
}

/** `t`, `tn`, `num` plus a markdown renderer for file previews. */
function useAiWs() {
  const ai = useAi();
  return { ...ai, md: (text: string) => <Markdown text={text} mermaid="" widgets={false} running={false} /> };
}
