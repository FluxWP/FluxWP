// Flux Files editor: file tree, tabs, CodeMirror (WordPress's bundled one; a plain textarea without it),
// find and replace, versions, AI explain / edit. Reads and writes through the Flux Files GraphQL operations.
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState, type ReactNode } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FlxFilesAiEdit, FlxFilesAiExplain, FlxFilesFile, FlxFilesWrite } from '@fluxwp/graphql/operations';
import { Ic } from './icons';
import type { Translate } from './i18n';
import { useDialogs, type MenuAt, type MenuItem } from './ui';
import { copyText, download, errCode, errText, FileIcon, loadFolder, ok, useEnv, type Cfg } from './data';
import { DiffView, VersionsDialog } from './FilesDialogs';
import { base, dir, extOf, mdLite, size, type Item } from './util';

/* -------------------------------------------------------------- CodeMirror */
interface Pos { line: number; ch: number }
interface CM {
  getValue(): string;
  setValue(v: string): void;
  refresh(): void;
  focus(): void;
  getSelection(): string;
  replaceSelection(s: string): void;
  getCursor(w?: string): Pos;
  setCursor(p: Pos): void;
  setSelection(a: Pos, b: Pos): void;
  lineCount(): number;
  posFromIndex(i: number): Pos;
  indexFromPos(p: Pos): number;
  scrollIntoView(p: null, margin: number): void;
  on(ev: string, fn: () => void): void;
  toTextArea?: () => void;
}
type CMFactory = (el: HTMLElement, opts: Record<string, unknown>) => CM;
interface WpGlobal { wp?: { CodeMirror?: CMFactory; codeEditor?: { defaultSettings?: { codemirror?: Record<string, unknown> } } } }
const wpg = (): WpGlobal['wp'] => (window as unknown as WpGlobal).wp;

const MODES: Record<string, string> = {
  php: 'application/x-httpd-php', phtml: 'application/x-httpd-php', js: 'text/javascript', mjs: 'text/javascript', cjs: 'text/javascript', jsx: 'text/jsx',
  ts: 'text/typescript', tsx: 'text/typescript-jsx', json: 'application/json', css: 'text/css', scss: 'text/x-scss', sass: 'text/x-sass', less: 'text/x-less',
  html: 'text/html', htm: 'text/html', svg: 'application/xml', xml: 'application/xml', md: 'text/x-markdown', yml: 'text/x-yaml', yaml: 'text/x-yaml',
  sql: 'text/x-sql', sh: 'text/x-sh', py: 'text/x-python', ini: 'text/x-properties', conf: 'text/x-properties',
};

function makeCm(host: HTMLElement, value: string, ro: boolean, name: string, onSave: () => void, onFind: () => void): CM {
  const f = wpg()?.CodeMirror;
  if (f) {
    const conf = { ...(wpg()?.codeEditor?.defaultSettings?.codemirror ?? {}) };
    delete conf.mode;
    delete conf.lint;
    delete conf.gutters;
    const cm = f(host, {
      ...conf,
      value,
      mode: MODES[extOf(name)] ?? 'text/plain',
      lineNumbers: true,
      lineWrapping: false,
      indentUnit: 4,
      tabSize: 4,
      indentWithTabs: true,
      styleActiveLine: true,
      matchBrackets: true,
      autoCloseBrackets: true,
      readOnly: ro,
      viewportMargin: 40,
      extraKeys: { 'Ctrl-S': onSave, 'Cmd-S': onSave, 'Ctrl-F': onFind, 'Cmd-F': onFind },
    });
    return cm;
  }
  const ta = document.createElement('textarea');
  ta.className = 'flxfm-ta';
  ta.value = value;
  ta.readOnly = ro;
  ta.spellcheck = false;
  host.appendChild(ta);
  const fns: Array<() => void> = [];
  ta.addEventListener('input', () => fns.forEach((fn) => fn()));
  ta.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      onSave();
    }
  });
  const posOf = (i: number): Pos => {
    const before = ta.value.slice(0, i).split('\n');
    return { line: before.length - 1, ch: (before[before.length - 1] ?? '').length };
  };
  return {
    getValue: () => ta.value,
    setValue: (v) => void (ta.value = v),
    refresh: () => undefined,
    focus: () => ta.focus(),
    getSelection: () => ta.value.slice(ta.selectionStart, ta.selectionEnd),
    replaceSelection: (s) => ta.setRangeText(s, ta.selectionStart, ta.selectionEnd, 'end'),
    getCursor: () => posOf(ta.selectionStart),
    setCursor: () => undefined,
    setSelection: () => undefined,
    lineCount: () => ta.value.split('\n').length,
    posFromIndex: posOf,
    indexFromPos: () => ta.selectionEnd,
    scrollIntoView: () => undefined,
    on: (_ev, fn) => void fns.push(fn),
  };
}

/* ------------------------------------------------------------------- tabs */
interface Tab {
  id: number;
  path: string;
  name: string;
  loading: boolean;
  error: string | null;
  info: Item | null;
  hash: string;
  initial: string;
  saved: string;
  dirty: boolean;
  confirmed: boolean;
  saving: boolean;
  cursor: string;
}

export interface EditorApi {
  open: (path: string) => void;
  closeTabs: (paths: string[]) => void;
  retarget: (from: string, to: string) => void;
  reload: (path: string, force: boolean) => void;
  dirty: () => boolean;
  tabValue: (path: string) => string | undefined;
}

interface Props {
  t: Translate;
  active: boolean;
  treeOff: boolean;
  onActivePath: (p: string | null) => void;
  onMenu: (m: MenuAt) => void;
  newItems: (where: string) => MenuItem[];
  pathItems: (path: string, isDir: boolean) => MenuItem[];
  onShowInBrowser: (path: string, isDir: boolean) => void;
  onTreeOff: (off: boolean) => void;
  onChanged: () => void;
}

let tabSeq = 0;

export const FilesEditor = forwardRef<EditorApi, Props>(function FilesEditor(p, ref) {
  const { t } = p;
  const { cfg, settings, client } = useEnv();
  const d = useDialogs();
  const [tabs, setTabs] = useState<Tab[]>([]);
  const [activeId, setActiveId] = useState<number | null>(null);
  const cms = useRef(new Map<number, CM>());
  const tabsRef = useRef<Tab[]>([]);
  tabsRef.current = tabs;
  const [find, setFind] = useState(false);
  const [ai, setAi] = useState<null | 'explain' | 'edit'>(null);
  const [versions, setVersions] = useState<string | null>(null);
  const [, bump] = useState(0);
  const cur = tabs.find((x) => x.id === activeId) ?? null;
  const patch = useCallback((id: number, v: Partial<Tab>) => setTabs((l) => l.map((x) => (x.id === id ? { ...x, ...v } : x))), []);

  useEffect(() => p.onActivePath(cur ? cur.path : null), [cur?.path]); // eslint-disable-line react-hooks/exhaustive-deps

  const load = useCallback(
    (id: number, path: string) =>
      client
        .request(FlxFilesFile, { path })
        .then((r) => {
          const f = r.flxFilesFile;
          patch(id, { loading: false, info: f.item, hash: f.hash, initial: f.content ?? '', saved: f.content ?? '' });
        })
        .catch((e) => patch(id, { loading: false, error: errText(e, t('error')) })),
    [client, patch, t],
  );

  const open = useCallback(
    (path: string) => {
      const ex = tabsRef.current.find((x) => x.path === path);
      if (ex) {
        setActiveId(ex.id);
        return;
      }
      const tab: Tab = { id: ++tabSeq, path, name: base(path), loading: true, error: null, info: null, hash: '', initial: '', saved: '', dirty: false, confirmed: false, saving: false, cursor: '' };
      setTabs((l) => [...l, tab]);
      setActiveId(tab.id);
      void load(tab.id, path);
    },
    [load],
  );

  const writeTab = useCallback(
    async (tab: Tab, force = false): Promise<void> => {
      const cm = cms.current.get(tab.id);
      if (!cm || tab.saving || !cfg.writable) return;
      const content = cm.getValue();
      patch(tab.id, { saving: true });
      try {
        const r = ok((await client.request(FlxFilesWrite, { input: { path: tab.path, content, hash: force ? null : tab.hash } })).flxFilesWrite);
        const f = r.file;
        patch(tab.id, { saving: false, hash: f?.hash ?? tab.hash, saved: content, dirty: false, info: f?.item ?? tab.info, confirmed: true });
        d.toast(t('saved'));
        p.onChanged();
      } catch (e) {
        patch(tab.id, { saving: false });
        if (errCode(e) === 'CONFLICT') {
          if (await d.confirm({ title: t('conflict_title'), message: errText(e, ''), ok: t('overwrite'), danger: true })) await writeTab({ ...tab, saving: false }, true);
        } else d.toast(errText(e, t('error')), 'bad');
      }
    },
    [cfg.writable, client, d, p, patch, t],
  );

  const save = useCallback(
    async (tab: Tab | null) => {
      if (!tab || !tab.info || tab.saving) return;
      if (!tab.confirmed && (tab.info.sensitive || tab.info.core || /\.htaccess$|\.user\.ini$/.test(tab.path))) {
        if (!(await d.confirm({ title: t('save'), message: tab.info.core ? t('core_save') : t('sensitive_save'), ok: t('save'), danger: true }))) return;
        patch(tab.id, { confirmed: true });
      }
      await writeTab(tab);
    },
    [d, patch, t, writeTab],
  );

  const closeTab = useCallback(
    async (tab: Tab, force = false) => {
      if (tab.dirty && !force && !(await d.confirm({ title: t('unsaved_title'), message: t('unsaved_msg', tab.name), ok: t('close_anyway'), danger: true }))) return;
      cms.current.delete(tab.id);
      const l = tabsRef.current;
      const i = l.findIndex((x) => x.id === tab.id);
      const next = l.filter((x) => x.id !== tab.id);
      setTabs(next);
      setActiveId((a) => (a === tab.id ? (next[i] ?? next[i - 1] ?? null)?.id ?? null : a));
    },
    [d, t],
  );

  useImperativeHandle(
    ref,
    () => ({
      open,
      closeTabs: (paths) => tabsRef.current.filter((tb) => paths.some((x) => tb.path === x || tb.path.startsWith(x + '/'))).forEach((tb) => void closeTab(tb, true)),
      retarget: (from, to) => setTabs((l) => l.map((tb) => (tb.path === from || tb.path.startsWith(from + '/') ? { ...tb, path: to + tb.path.slice(from.length), name: base(to + tb.path.slice(from.length)) } : tb))),
      reload: (path, force) => {
        const tb = tabsRef.current.find((x) => x.path === path);
        if (!tb) return;
        void client.request(FlxFilesFile, { path }).then((r) => {
          const f = r.flxFilesFile;
          const cm = cms.current.get(tb.id);
          if (cm && (force || !tb.dirty)) cm.setValue(f.content ?? '');
          patch(tb.id, { info: f.item, hash: f.hash, saved: f.content ?? '', dirty: force || !tb.dirty ? false : tb.dirty });
        });
      },
      dirty: () => tabsRef.current.some((x) => x.dirty),
      tabValue: (path) => {
        const tb = tabsRef.current.find((x) => x.path === path);
        return tb ? cms.current.get(tb.id)?.getValue() : undefined;
      },
    }),
    [open, closeTab, client, patch],
  );

  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => {
      if (tabsRef.current.some((x) => x.dirty)) {
        e.preventDefault();
        e.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, []);
  useEffect(() => {
    if (!p.active) return;
    const key = (e: KeyboardEvent) => {
      const m = e.metaKey || e.ctrlKey;
      if (m && e.key.toLowerCase() === 's') {
        e.preventDefault();
        void save(tabsRef.current.find((x) => x.id === activeId) ?? null);
      }
      if (m && e.key.toLowerCase() === 'f' && !(e.target as HTMLElement).closest('.CodeMirror')) {
        e.preventDefault();
        setFind(true);
      }
    };
    document.addEventListener('keydown', key);
    return () => document.removeEventListener('keydown', key);
  }, [p.active, activeId, save]);
  useEffect(() => {
    if (p.active && activeId != null) setTimeout(() => cms.current.get(activeId)?.refresh(), 0);
  }, [p.active, activeId]);
  useEffect(() => setAi(null), [activeId]);

  const writable = cfg.writable && !!cur?.info?.writable;
  const banners = cur?.info ? [cur.info.sensitive && t('sensitive_banner'), cur.info.core && t('core_banner'), !cur.info.writable && t('not_writable_banner')].filter(Boolean) : [];
  const cm = cur ? cms.current.get(cur.id) : undefined;

  const aiMenu = (e: React.MouseEvent) => {
    if (!cur || !cm) return;
    const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
    p.onMenu({
      x: r.right,
      y: r.bottom + 6,
      right: true,
      items: [
        { icon: 'info', label: cm.getSelection() ? t('ai_explain_sel') : t('ai_explain'), run: () => setAi('explain') },
        writable ? { icon: 'pencil', label: t('ai_edit'), run: () => setAi('edit') } : null,
      ],
    });
  };

  return (
    <section className={'flxfm-card flxfm-editor'} hidden={!p.active}>
      <div className="flxfm-ebar">
        <button type="button" className="flxfm-ib" onClick={() => p.onTreeOff(!p.treeOff)} aria-label={t('toggle_tree')}>
          <Ic n="sidebar" />
        </button>
        <div className="flxfm-tabs" role="tablist">
          {tabs.map((tb) => (
            <div
              key={tb.id}
              className={'flxfm-tab' + (tb.id === activeId ? ' is-on' : '') + (tb.dirty ? ' is-dirty' : '')}
              role="tab"
              aria-selected={tb.id === activeId}
              title={tb.path}
              onClick={() => setActiveId(tb.id)}
              onAuxClick={(e) => e.button === 1 && void closeTab(tb)}
            >
              <FileIcon it={{ name: tb.name, extension: extOf(tb.name), kind: 'CODE', isDir: false, shared: false }} />
              <span>{tb.name}</span>
              <button
                type="button"
                className="flxfm-tab-x"
                aria-label={t('close')}
                onClick={(e) => {
                  e.stopPropagation();
                  void closeTab(tb);
                }}
              >
                {tb.dirty ? <i className="flxfm-dot" /> : null}
                <Ic n="x" cls="flxfm-i-sm" />
              </button>
            </div>
          ))}
        </div>
        <div className="flxfm-etools">
          {cur && !cur.loading && !cur.error ? (
            <>
              <span className="flxfm-esize">{size(cur.info?.size ?? null)}</span>
              <button type="button" className="flxfm-btn flxfm-btn--ghost" onClick={() => setFind(true)}>
                <Ic n="search" />
                <span>{t('find')}</span>
              </button>
              {cfg.aiReady && cur.info && !cur.info.sensitive ? (
                <button type="button" className="flxfm-btn flxfm-btn--ghost flxfm-btn--ai" onClick={aiMenu}>
                  <Ic n="ai" />
                  <span>AI</span>
                  <Ic n="down" cls="flxfm-i-sm" />
                </button>
              ) : null}
              <button type="button" className="flxfm-ib" aria-label={t('versions')} title={t('versions')} onClick={() => setVersions(cur.path)}>
                <Ic n="history" />
              </button>
              <button type="button" className="flxfm-ib" aria-label={t('download')} title={t('download')} onClick={() => download(cfg, [cur.path])}>
                <Ic n="download" />
              </button>
              <button type="button" className="flxfm-ib" aria-label={t('copy_content')} title={t('copy_content')} onClick={() => copyText(cm?.getValue() ?? '', () => d.toast(t('copied')))}>
                <Ic n="copy" />
              </button>
              {writable ? (
                <button type="button" className="flxfm-btn flxfm-btn--pri" disabled={!cur.dirty} onClick={() => void save(cur)}>
                  <Ic n="save" />
                  <span>{t('save')}</span>
                </button>
              ) : null}
            </>
          ) : null}
        </div>
      </div>
      <div className="flxfm-ewrap">
        <aside className="flxfm-tree">
          <div className="flxfm-tree-head">
            <b>{cfg.rootName}</b>
            <RefreshButton t={t} />
          </div>
          <div className="flxfm-tree-body">
            <TreeLevel
              path=""
              depth={0}
              active={cur?.path ?? null}
              t={t}
              onOpen={(it) => {
                open(it.path);
                if (window.innerWidth <= 782) p.onTreeOff(true);
              }}
              onMenu={(e, it) => {
                e.preventDefault();
                p.onMenu({
                  x: e.clientX,
                  y: e.clientY,
                  items: [
                    it.isDir ? null : { icon: 'code', label: t('edit'), run: () => open(it.path) },
                    it.isDir && cfg.writable ? { icon: 'plus', label: t('new'), sub: p.newItems(it.path) } : null,
                    { icon: 'browser', label: t('show_in_browser'), run: () => p.onShowInBrowser(it.path, it.isDir) },
                    { icon: 'download', label: it.isDir ? t('download_zip') : t('download'), run: () => download(cfg, [it.path]) },
                    { icon: 'link', label: t('copy_path'), sub: p.pathItems(it.path, it.isDir) },
                  ],
                });
              }}
            />
          </div>
        </aside>
        <div className="flxfm-emain">
          {!tabs.length ? (
            <div className="flxfm-empty-editor">
              <Ic n="code" />
              <p>{t('editor_empty')}</p>
            </div>
          ) : null}
          {cur?.loading ? <div className="flxfm-emsg">{t('loading')}</div> : null}
          {cur?.error ? (
            <div className="flxfm-emsg is-err">
              <Ic n="info" />
              <p>{cur.error}</p>
              <button type="button" className="flxfm-btn" onClick={() => download(cfg, [cur.path])}>
                <Ic n="download" />
                {t('download')}
              </button>
            </div>
          ) : null}
          {banners.length ? (
            <div className="flxfm-ebanner">
              <Ic n="shield" />
              <span>{banners.join(' ')}</span>
            </div>
          ) : null}
          {find && cm ? <FindBar cm={cm} writable={writable} t={t} onClose={() => { setFind(false); cm.focus(); }} /> : null}
          {ai && cur && cm ? <AiPanel kind={ai} tab={cur} cm={cm} t={t} onClose={() => { setAi(null); cm.refresh(); }} /> : null}
          {tabs.map((tb) =>
            tb.info && !tb.loading ? (
              <Pane
                key={tb.id}
                tab={tb}
                visible={tb.id === activeId}
                ro={!cfg.writable || !tb.info.writable}
                register={(c) => cms.current.set(tb.id, c)}
                onDirty={(dirtyNow) => patch(tb.id, { dirty: dirtyNow })}
                onCursor={(s) => {
                  patch(tb.id, { cursor: s });
                  bump((n) => n + 1);
                }}
                onSave={() => void save(tabsRef.current.find((x) => x.id === tb.id) ?? null)}
                onFind={() => setFind(true)}
              />
            ) : null,
          )}
        </div>
      </div>
      {p.active ? (
        <div className="flxfm-estatus">
          {cur && cm ? (
            <>
              <span>{cur.cursor || t('line_col', 1, 1)}</span>
              <span>{extOf(cur.name).toUpperCase() || t('kind_text')}</span>
              <span>{t('lines_n', cm.lineCount())}</span>
              <span className={cur.dirty ? 'is-dirty' : 'is-ok'}>{cur.dirty ? t('unsaved') : t('saved_state')}</span>
            </>
          ) : null}
        </div>
      ) : null}
      {versions ? (
        <VersionsDialog
          path={versions}
          current={() => cms.current.get(tabsRef.current.find((x) => x.path === versions)?.id ?? -1)?.getValue() ?? ''}
          settings={settings}
          t={t}
          onClose={() => setVersions(null)}
          onRestored={() => {
            const path = versions;
            const tb = tabsRef.current.find((x) => x.path === path);
            if (tb) void load(tb.id, tb.path).then(() => cms.current.get(tb.id)?.setValue(''));
            p.onChanged();
          }}
        />
      ) : null}
    </section>
  );
});

function RefreshButton({ t }: { t: Translate }) {
  const { scope } = useEnv();
  const qc = useQueryClient();
  const [spin, setSpin] = useState(false);
  return (
    <button
      type="button"
      className={'flxfm-ib' + (spin ? ' is-spin' : '')}
      aria-label={t('refresh')}
      onClick={() => {
        setSpin(true);
        void qc.invalidateQueries({ queryKey: [scope, 'files-tree'] }).then(() => setSpin(false));
      }}
    >
      <Ic n="refresh" />
    </button>
  );
}

/* ------------------------------------------------------------ editor pane */
function Pane({ tab, visible, ro, register, onDirty, onCursor, onSave, onFind }: { tab: Tab; visible: boolean; ro: boolean; register: (c: CM) => void; onDirty: (d: boolean) => void; onCursor: (s: string) => void; onSave: () => void; onFind: () => void }) {
  const host = useRef<HTMLDivElement>(null);
  const savedRef = useRef(tab.saved);
  savedRef.current = tab.saved;
  const cbs = useRef({ onDirty, onCursor, onSave, onFind });
  cbs.current = { onDirty, onCursor, onSave, onFind };
  const dirty = useRef(false);
  const { t } = { t: (k: string) => k };
  void t;
  useEffect(() => {
    const el = host.current;
    if (!el) return;
    const cm = makeCm(el, tab.initial, ro, tab.name, () => cbs.current.onSave(), () => cbs.current.onFind());
    register(cm);
    cm.on('change', () => {
      const dn = cm.getValue() !== savedRef.current;
      if (dn !== dirty.current) {
        dirty.current = dn;
        cbs.current.onDirty(dn);
      }
    });
    cm.on('cursorActivity', () => {
      const c = cm.getCursor();
      cbs.current.onCursor(`${c.line + 1}:${c.ch + 1}`);
    });
    if (visible) setTimeout(() => (cm.refresh(), cm.focus()), 0);
    return () => {
      el.innerHTML = '';
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  // Saved content changed (after a save or a reload): recompute dirty.
  useEffect(() => {
    dirty.current = tab.dirty;
  }, [tab.dirty]);
  useEffect(() => {
    if (visible) setTimeout(() => host.current?.querySelector<HTMLElement>('.CodeMirror, textarea')?.dispatchEvent(new Event('focus')), 0);
  }, [visible]);
  return <div className="flxfm-cm" ref={host} hidden={!visible} />;
}

/* ------------------------------------------------------------------- find */
function FindBar({ cm, writable, t, onClose }: { cm: CM; writable: boolean; t: Translate; onClose: () => void }) {
  const d = useDialogs();
  const [q, setQ] = useState(() => {
    const s = cm.getSelection();
    return s && !s.includes('\n') ? s : '';
  });
  const [r, setR] = useState('');
  const [state, setState] = useState({ cur: -1, n: 0 });
  const input = useRef<HTMLInputElement>(null);
  useEffect(() => {
    input.current?.focus();
    input.current?.select();
  }, []);
  const hits = useCallback(() => {
    const txt = cm.getValue().toLowerCase();
    const s = q.toLowerCase();
    const out: number[] = [];
    if (s) {
      let i = txt.indexOf(s);
      while (i >= 0 && out.length < 5000) {
        out.push(i);
        i = txt.indexOf(s, i + s.length);
      }
    }
    return out;
  }, [cm, q]);
  useEffect(() => setState({ cur: -1, n: hits().length }), [hits]);
  const jump = (dirn: number) => {
    const h = hits();
    if (!h.length) return;
    const at = cm.indexFromPos(cm.getCursor('to'));
    let cur: number;
    if (dirn > 0) {
      cur = h.findIndex((x) => x >= at);
      if (cur < 0) cur = 0;
    } else {
      const from = cm.indexFromPos(cm.getCursor('from'));
      cur = -1;
      for (let i = h.length - 1; i >= 0; i--) if ((h[i] ?? 0) < from) { cur = i; break; }
      if (cur < 0) cur = h.length - 1;
    }
    cm.setSelection(cm.posFromIndex(h[cur] ?? 0), cm.posFromIndex((h[cur] ?? 0) + q.length));
    cm.scrollIntoView(null, 120);
    setState({ cur, n: h.length });
  };
  const replaceAll = () => {
    if (!q) return;
    const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    const n = (cm.getValue().match(re) ?? []).length;
    if (!n) return;
    const c = cm.getCursor();
    cm.setValue(cm.getValue().replace(re, () => r));
    cm.setCursor(c);
    d.toast(t('replaced_n', n));
  };
  return (
    <div className="flxfm-find">
      <input
        ref={input}
        type="text"
        className="flxfm-f-q"
        placeholder={t('find')}
        value={q}
        onChange={(e) => setQ(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            jump(e.shiftKey ? -1 : 1);
          }
          if (e.key === 'Escape') onClose();
        }}
      />
      <span className="flxfm-f-n">{q ? (state.n ? `${Math.max(0, state.cur) + 1}/${state.n}` : t('no_results')) : ''}</span>
      <button type="button" className="flxfm-ib" aria-label={t('previous')} onClick={() => jump(-1)}><Ic n="up" /></button>
      <button type="button" className="flxfm-ib" aria-label={t('next')} onClick={() => jump(1)}><Ic n="down" /></button>
      {writable ? (
        <>
          <input type="text" className="flxfm-f-r" placeholder={t('replace_with')} value={r} onChange={(e) => setR(e.target.value)} />
          <button type="button" className="flxfm-btn" onClick={() => { if (q && cm.getSelection().toLowerCase() === q.toLowerCase()) cm.replaceSelection(r); jump(1); }}>{t('replace')}</button>
          <button type="button" className="flxfm-btn" onClick={replaceAll}>{t('replace_all')}</button>
        </>
      ) : null}
      <button type="button" className="flxfm-ib" aria-label={t('close')} onClick={onClose}><Ic n="x" /></button>
    </div>
  );
}

/* --------------------------------------------------------------------- AI */
function AiPanel({ kind, tab, cm, t, onClose }: { kind: 'explain' | 'edit'; tab: Tab; cm: CM; t: Translate; onClose: () => void }) {
  const { client } = useEnv();
  const d = useDialogs();
  const [busy, setBusy] = useState(kind === 'explain');
  const [answer, setAnswer] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [q, setQ] = useState('');
  const [prop, setProp] = useState<{ content: string | null; note: string | null; base: string } | null>(null);
  useEffect(() => {
    if (kind !== 'explain') return;
    client
      .request(FlxFilesAiExplain, { input: { path: tab.path, content: cm.getValue(), selection: cm.getSelection() } })
      .then((r) => setAnswer(ok(r.flxFilesAiExplain).answer ?? ''))
      .catch((e) => setErr(errText(e, t('error'))))
      .finally(() => setBusy(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const suggest = () => {
    if (!q.trim()) return;
    const baseText = cm.getValue();
    setBusy(true);
    setErr(null);
    setProp(null);
    client
      .request(FlxFilesAiEdit, { input: { path: tab.path, content: baseText, prompt: q } })
      .then((r) => {
        const x = ok(r.flxFilesAiEdit);
        setProp({ content: x.content ?? null, note: x.note ?? null, base: baseText });
      })
      .catch((e) => setErr(errText(e, t('error'))))
      .finally(() => setBusy(false));
  };
  const title = (kind === 'explain' ? t('ai_explain') : t('ai_edit')) + ' · ' + tab.name;
  const apply = () => {
    if (prop?.content == null) return;
    const c = cm.getCursor();
    cm.setValue(prop.content);
    cm.setCursor(c);
    onClose();
    d.toast(t('ai_applied'), 'info');
  };
  return (
    <section className="flxfm-aip" aria-label={title} onKeyDown={(e) => e.key === 'Escape' && (e.stopPropagation(), onClose())}>
      <header className="flxfm-aip-head">
        <Ic n="ai" />
        <b>{title}</b>
        <button type="button" className="flxfm-ib" onClick={onClose} aria-label={t('close')}><Ic n="x" /></button>
      </header>
      <div className="flxfm-aip-body">
        {kind === 'edit' ? (
          <>
            <div className="flxfm-aip-ask">
              <textarea
                rows={2}
                className="flxfm-ai-q"
                placeholder={t('ai_edit_ph')}
                aria-label={t('ai_edit_q')}
                value={q}
                autoFocus
                onChange={(e) => setQ(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) suggest();
                }}
              />
              <button type="button" className="flxfm-btn flxfm-btn--ai" disabled={busy} onClick={suggest}><Ic n="ai" />{t('ai_suggest')}</button>
            </div>
            <p className="flxfm-muted flxfm-aip-hint">{t('ai_edit_hint')}</p>
          </>
        ) : null}
        {busy ? <div className="flxfm-ai-wait"><Ic n="ai" />{t('ai_thinking')}</div> : null}
        {err ? <p className="flxfm-err">{err}</p> : null}
        {answer != null ? <div className="flxfm-md" dangerouslySetInnerHTML={{ __html: mdLite(answer) }} /> : null}
        {prop ? (
          <div className="flxfm-ai-out">
            {prop.note ? <p className="flxfm-ai-note"><Ic n="ai" />{prop.note}</p> : null}
            {prop.content != null ? <DiffView a={prop.base} b={prop.content} t={t} /> : null}
          </div>
        ) : null}
      </div>
      {kind === 'edit' && prop && prop.content != null && prop.content !== prop.base ? (
        <footer className="flxfm-aip-foot">
          <button type="button" className="flxfm-btn" onClick={onClose}>{t('cancel')}</button>
          <button type="button" className="flxfm-btn flxfm-btn--pri" onClick={apply}><Ic n="check" />{t('ai_apply')}</button>
        </footer>
      ) : null}
    </section>
  );
}

/* ------------------------------------------------------------------- tree */
function TreeLevel({ path, depth, active, t, onOpen, onMenu }: { path: string; depth: number; active: string | null; t: Translate; onOpen: (it: Item) => void; onMenu: (e: React.MouseEvent, it: Item) => void }) {
  const { client, scope, settings } = useEnv();
  const q = useQuery({ queryKey: [scope, 'files-tree', path], queryFn: ({ signal }) => loadFolder(client, path, { hidden: settings.showHidden }, signal) });
  if (q.isPending) return <div className="flxfm-tree-loading">{t('loading')}</div>;
  if (q.isError) return <div className="flxfm-tree-loading">{errText(q.error, t('error'))}</div>;
  if (!q.data.items.length) return <div className="flxfm-tree-loading">{t('empty_folder')}</div>;
  return (
    <>
      {q.data.items.map((it) => (
        <TreeNode key={it.path} it={it} depth={depth} active={active} t={t} onOpen={onOpen} onMenu={onMenu} />
      ))}
    </>
  );
}

function TreeNode({ it, depth, active, t, onOpen, onMenu }: { it: Item; depth: number; active: string | null; t: Translate; onOpen: (it: Item) => void; onMenu: (e: React.MouseEvent, it: Item) => void }): ReactNode {
  const [open, setOpen] = useState(false);
  return (
    <div className={'flxfm-tn' + (it.isDir ? ' is-dir' : '') + (open ? ' is-open' : '')} onContextMenu={(e) => { e.stopPropagation(); onMenu(e, it); }}>
      <button
        type="button"
        className={'flxfm-tn-row' + (active === it.path ? ' is-on' : '')}
        style={{ '--d': depth } as React.CSSProperties}
        onClick={() => (it.isDir ? setOpen(!open) : onOpen(it))}
      >
        {it.isDir ? <span className="flxfm-tn-caret"><Ic n="right" cls="flxfm-i-sm" /></span> : <span className="flxfm-tn-caret" />}
        <FileIcon it={it} />
        <span className="flxfm-tn-name">{it.name}</span>
      </button>
      {it.isDir && open ? (
        <div className="flxfm-tn-kids">
          <TreeLevel path={it.path} depth={depth + 1} active={active} t={t} onOpen={onOpen} onMenu={onMenu} />
        </div>
      ) : null}
    </div>
  );
}

export type { Cfg };
export { dir as _dir };
