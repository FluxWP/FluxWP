// Flux Files › file manager. Same screen in wp-admin (wp-embed); every read and write goes through the
// Flux Files GraphQL operations (plugins/flx-files/graphql/operations). Chunked upload stays on REST
// (flx-files/v1/upload): see docs/graphql/ISTISNALAR.md. It has every feature of the classic screen:
// list and grid, sort, search, selection and shortcuts, context menu, drag and drop, clipboard,
// new / rename / duplicate / archive / extract, permissions, info, preview, version history, trash,
// activity, settings and the code editor with find and AI.
import { useCallback, useEffect, useMemo, useRef, useState, type DragEvent, type MouseEvent as RMouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxFilesBootstrap,
  FlxFilesCopy,
  FlxFilesDisk,
  FlxFilesDuplicate,
  FlxFilesFolderCreate,
  FlxFilesMove,
  FlxFilesRename,
  FlxFilesSearch,
  FlxFilesShare,
  FlxFilesTrash,
  FlxFilesUnzip,
  FlxFilesWrite,
  FlxFilesZip,
} from '@fluxwp/graphql/operations';
import type { FlxFilesOrder, FlxFilesSort } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import { Ic } from './icons';
import { DialogProvider, Menu, useDialogs, type MenuAt, type MenuItem } from './ui';
import { copyText, download, EnvProvider, errCode, errText, FileIcon, loadFolder, ok, publicUrl, useEnv, type Cfg, type Settings } from './data';
import { InfoDialog, PermsDialog, PickFolder, Preview, SettingsDialog, VersionsDialog } from './FilesDialogs';
import { FilesEditor, type EditorApi } from './FilesEditor';
import { LogView, TrashView, UploadPanel, type UpJob } from './FilesViews';
import { base, canEdit, dir, extOf, join, lower, size, type Item, type Match, type SearchItem } from './util';
import './files.css';

type Mode = 'browse' | 'editor' | 'trash' | 'log';
type SortKey = 'name' | 'mtime' | 'size' | 'ext';
interface Sort { key: SortKey; dir: 1 | -1 }
const SORTS: Array<[SortKey, 1 | -1, string]> = [
  ['name', 1, 'sort_name_az'], ['name', -1, 'sort_name_za'], ['mtime', -1, 'sort_new'], ['mtime', 1, 'sort_old'], ['size', -1, 'sort_big'], ['size', 1, 'sort_small'], ['ext', 1, 'sort_type'],
];
const SORT_ARG: Record<SortKey, FlxFilesSort> = { name: 'NAME', mtime: 'MODIFIED', size: 'SIZE', ext: 'TYPE' };
const MOD = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl+';
const MEDIA = ['IMAGE', 'VIDEO', 'AUDIO', 'PDF'];

const ls = {
  get<T>(k: string, d: T): T {
    try {
      const v = localStorage.getItem('flxfm-' + k);
      return v ? (JSON.parse(v) as T) : d;
    } catch {
      return d;
    }
  },
  set(k: string, v: unknown): void {
    try {
      localStorage.setItem('flxfm-' + k, JSON.stringify(v));
    } catch {
      /* ignore */
    }
  },
};

export function FilesScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Boot />
    </FlxGraphQLProvider>
  );
}

function Boot() {
  const host = useHost();
  const { client, scope } = useGraphQL();
  const { i18n } = useTranslation();
  const t = useMemo(() => translator(i18n.language), [i18n.language]);
  const q = useFlxQuery(FlxFilesBootstrap, {});
  if (q.isPending) return <Loading />;
  if (q.isError) return <div className="flxfm"><p className="flxfm-err">{errText(q.error, t('error'))}</p></div>;
  const cfg: Cfg = { ...q.data.flxFilesConfig };
  const settings: Settings = { ...q.data.flxFilesSettings, shared: [...q.data.flxFilesSettings.shared] };
  return (
    <EnvProvider value={{ cfg, settings, client, scope }}>
      <DialogProvider t={t}>
        <Files t={t} classicUrl={host.classicUrl} onSettings={() => void q.refetch()} />
      </DialogProvider>
    </EnvProvider>
  );
}

function Files({ t, classicUrl, onSettings }: { t: Translate; classicUrl: string | undefined; onSettings: () => void }) {
  const host = useHost();
  const { cfg, settings, client, scope } = useEnv();
  const qc = useQueryClient();
  const d = useDialogs();
  const root = useRef<HTMLDivElement>(null);
  const editor = useRef<EditorApi>(null);

  /* ----------------------------------------------------------------- state */
  const start = useMemo(() => {
    const m = /^#path=(.*)$/.exec(location.hash);
    let p = '';
    if (m) {
      try {
        p = decodeURIComponent(m[1] ?? '');
      } catch {
        p = '';
      }
    }
    return p;
  }, []);
  const [mode, setMode] = useState<Mode>('browse');
  const [cwd, setCwd] = useState(start);
  const [back, setBack] = useState<string[]>([]);
  const [fwd, setFwd] = useState<string[]>([]);
  const [sort, setSortState] = useState<Sort>(() => {
    const s = ls.get<{ key?: string; dir?: number }>('sort', {});
    return { key: (['name', 'mtime', 'size', 'ext'] as const).find((k) => k === s.key) ?? 'name', dir: s.dir === -1 ? -1 : 1 };
  });
  const [view, setViewState] = useState<'list' | 'grid'>(() => (ls.get<string>('view', 'list') === 'grid' ? 'grid' : 'list'));
  const [sel, setSel] = useState<Set<string>>(new Set());
  const anchor = useRef<string | null>(null);
  const [clip, setClip] = useState<{ op: 'cut' | 'copy'; paths: string[] } | null>(null);
  const [search, setSearch] = useState<{ q: string; results: SearchItem[]; partial: boolean } | null>(null);
  const [qText, setQText] = useState('');
  const [qContent, setQContent] = useState(false);
  const [qOpen, setQOpen] = useState(false);
  const [menu, setMenu] = useState<MenuAt | null>(null);
  const [modal, setModal] = useState<
    | null
    | { k: 'info'; it: Item }
    | { k: 'perms'; it: Item }
    | { k: 'pick'; title: string; run: (dest: string) => void }
    | { k: 'preview'; list: Item[]; index: number }
    | { k: 'versions'; path: string }
    | { k: 'settings' }
  >(null);
  const [treeOff, setTreeOff] = useState(window.innerWidth <= 782);
  const [logPath, setLogPath] = useState('');
  const [count, setCount] = useState<number | null>(null);
  const [editorPath, setEditorPath] = useState<string | null>(null);
  const [jobs, setJobs] = useState<UpJob[]>([]);
  const [dragging, setDragging] = useState(false);
  const [dropOn, setDropOn] = useState<string | null>(null);
  const [diskTick, setDiskTick] = useState(0);
  const dragInternal = useRef<string[] | null>(null);
  const dragDepth = useRef(0);
  const bodyRef = useRef<HTMLDivElement>(null);
  const fileIn = useRef<HTMLInputElement>(null);
  const dirIn = useRef<HTMLInputElement>(null);

  const setSort = (s: Sort) => {
    setSortState(s);
    ls.set('sort', s);
  };

  /* ------------------------------------------------------------------ fit */
  useEffect(() => {
    const fit = () => {
      const el = root.current;
      if (!el) return;
      const top = el.getBoundingClientRect().top + (window.scrollY || 0);
      const wb = document.getElementById('wpbody-content');
      const pb = wb ? parseFloat(getComputedStyle(wb).paddingBottom) || 0 : 0;
      el.style.setProperty('--flxfm-h', Math.max(460, Math.floor(window.innerHeight - top - pb)) + 'px');
    };
    fit();
    const later = () => setTimeout(fit, 200);
    window.addEventListener('resize', fit);
    window.addEventListener('orientationchange', later);
    return () => {
      window.removeEventListener('resize', fit);
      window.removeEventListener('orientationchange', later);
    };
  }, []);

  /* --------------------------------------------------------------- folder */
  const folder = useQuery({
    queryKey: [scope, 'files-folder', cwd, sort.key, sort.dir, settings.showHidden],
    queryFn: ({ signal }) => loadFolder(client, cwd, { sort: SORT_ARG[sort.key], order: (sort.dir > 0 ? 'ASC' : 'DESC') as FlxFilesOrder, hidden: settings.showHidden, countItems: true }, signal),
    placeholderData: keepPreviousData,
    retry: false,
  });
  const items: Item[] = folder.data?.items ?? [];
  const visible: Item[] = search ? search.results : items;
  const dirInfo = folder.data?.dir ?? null;

  useEffect(() => {
    if (folder.isError && cwd) {
      d.toast(errText(folder.error, t('error')), 'bad');
      setCwd(dir(cwd));
    }
  }, [folder.isError]); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => {
    try {
      history.replaceState(null, '', '#path=' + encodeURIComponent(cwd));
    } catch {
      /* ignore */
    }
  }, [cwd]);

  const disk = useQuery({
    queryKey: [scope, 'files-disk', diskTick],
    queryFn: () => client.request(FlxFilesDisk, { refresh: diskTick > 0 }).then((r) => r.flxFilesDisk),
    refetchInterval: (q) => (q.state.data?.scanning && !document.hidden ? 4000 : false),
  });

  const refreshAll = useCallback(() => {
    void qc.invalidateQueries({ queryKey: [scope, 'files-folder'] });
    void qc.invalidateQueries({ queryKey: [scope, 'files-tree'] });
    void qc.invalidateQueries({ queryKey: [scope, 'files-trash'] });
    void qc.invalidateQueries({ queryKey: [scope, 'files-log'] });
    void qc.invalidateQueries({ queryKey: [scope, 'files-disk'] });
  }, [qc, scope]);

  const searchSeq = useRef(0);
  const runSearch = useCallback(
    async (text = qText, content = qContent) => {
      const s = text.trim();
      const my = ++searchSeq.current;
      if (s.length < 2) {
        if (!s) setSearch(null);
        return;
      }
      try {
        const r = (await client.request(FlxFilesSearch, { path: cwd, query: s, content })).flxFilesSearch;
        if (my !== searchSeq.current) return; // a newer search or a clear came first
        setSearch({ q: s, results: r.results as SearchItem[], partial: r.partial });
        setSel(new Set());
      } catch (e) {
        d.toast(errText(e, t('error')), 'bad');
      }
    },
    [client, cwd, d, qContent, qText, t],
  );
  const refresh = useCallback(() => {
    if (search) void runSearch(search.q);
    refreshAll();
  }, [refreshAll, runSearch, search]);

  /* ------------------------------------------------------------ navigation */
  const go = useCallback(
    (path: string, hist = false) => {
      if (!hist && path !== cwd) {
        setBack((b) => [...b, cwd]);
        setFwd([]);
      }
      searchSeq.current++;
      setSearch(null);
      setQText('');
      setQOpen(false);
      setSel(new Set());
      setCwd(path);
      setMode('browse');
    },
    [cwd],
  );
  const goBack = () => {
    const p = back[back.length - 1];
    if (p === undefined) return;
    setBack(back.slice(0, -1));
    setFwd([...fwd, cwd]);
    go(p, true);
  };
  const goFwd = () => {
    const p = fwd[fwd.length - 1];
    if (p === undefined) return;
    setFwd(fwd.slice(0, -1));
    setBack([...back, cwd]);
    go(p, true);
  };

  const failToast = (e: unknown) => d.toast(errText(e, t('error')), 'bad');
  const report = async (failed: ReadonlyArray<{ path: string; message: string }>) => {
    if (failed.length) await d.alert({ title: t('some_failed'), message: failed.map((e) => base(e.path) + ': ' + e.message).join('\n') });
  };

  /* --------------------------------------------------------------- actions */
  const retarget = (from: string, to: string) => editor.current?.retarget(from, to);
  const transfer = async (op: 'move' | 'copy', paths: string[], dest: string, overwrite = false): Promise<boolean> => {
    try {
      const input = { paths, destination: dest, overwrite };
      const r = op === 'move' ? ok((await client.request(FlxFilesMove, { input })).flxFilesMove) : ok((await client.request(FlxFilesCopy, { input })).flxFilesCopy);
      if (op === 'move') paths.forEach((p) => r.items.some((i) => i.path === join(dest, base(p))) && retarget(p, join(dest, base(p))));
      const conflicts = r.failed.filter((e) => /exist/i.test(e.message));
      if (r.failed.length && op === 'move' && !overwrite && conflicts.length === r.failed.length) {
        if (await d.confirm({ title: t('conflict_title'), message: t('conflict_msg', conflicts.length), ok: t('overwrite') })) return transfer(op, conflicts.map((e) => e.path), dest, true);
        refresh();
        return false;
      }
      await report(r.failed);
      if (r.items.length) d.toast(t(op === 'move' ? 'moved_n' : 'copied_done_n', r.items.length));
      refresh();
      if (dest === cwd) setSel(new Set(r.items.map((i) => i.path)));
      return !r.failed.length;
    } catch (e) {
      failToast(e);
      return false;
    }
  };

  const openEditor = (path: string) => {
    setMode('editor');
    editor.current?.open(path);
  };
  const selItems = (): Item[] => visible.filter((i) => sel.has(i.path));

  const createNew = async (where: string, isDir: boolean) => {
    const v = await d.prompt({ title: isDir ? t('new_folder') : t('new_file'), placeholder: isDir ? t('folder_name') : 'index.html', ok: t('create') });
    if (!v || !v.trim()) return;
    const p = join(where, v.trim());
    try {
      if (isDir) ok((await client.request(FlxFilesFolderCreate, { input: { path: p } })).flxFilesFolderCreate);
      else ok((await client.request(FlxFilesWrite, { input: { path: p, content: '', createOnly: true } })).flxFilesWrite);
      d.toast(t('created'));
      refresh();
      if (mode === 'editor') {
        if (!isDir) openEditor(p);
        return;
      }
      if (where !== cwd) go(where);
      setSel(new Set([p]));
      if (!isDir) openEditor(p);
    } catch (e) {
      failToast(e);
    }
  };
  const archive = async (its: Item[]) => {
    const name = its.length === 1 ? (its[0]?.name ?? '') + '.zip' : (cwd ? base(cwd) : 'archive') + '.zip';
    const v = await d.prompt({ title: t('archive'), message: t('archive_hint'), value: name, ok: t('create') });
    if (!v) return;
    d.toast(t('working'), 'info');
    const where = search ? dir(its[0]?.path ?? '') : cwd;
    try {
      const r = ok((await client.request(FlxFilesZip, { input: { paths: its.map((i) => i.path), name: v.trim(), folder: where } })).flxFilesZip);
      d.toast(t('archived'));
      refresh();
      if (where === cwd && r.item) setSel(new Set([r.item.path]));
    } catch (e) {
      failToast(e);
    }
  };
  const extract = async (it: Item) => {
    if (!(await d.confirm({ title: t('extract'), message: t('extract_msg', it.name), ok: t('extract') }))) return;
    d.toast(t('working'), 'info');
    try {
      const r = ok((await client.request(FlxFilesUnzip, { input: { path: it.path } })).flxFilesUnzip);
      d.toast(t('extracted', r.fileCount ?? 0));
      refresh();
      if (r.path) setSel(new Set([r.path]));
    } catch (e) {
      failToast(e);
    }
  };
  const share = async (it: Item) => {
    const on = !it.shared;
    try {
      const r = ok((await client.request(FlxFilesShare, { input: { path: it.path, on } })).flxFilesShare);
      if (on && r.shortcode) {
        copyText(r.shortcode, () => undefined);
        await d.alert({ title: t('shared_title'), message: t('shared_msg') + '\n\n' + r.shortcode });
      } else d.toast(t('unshared'));
      refresh();
      onSettings();
    } catch (e) {
      failToast(e);
    }
  };
  const act = async (a: 'cut' | 'copy' | 'zipdl' | 'duplicate' | 'rename' | 'trash', its: Item[]) => {
    const paths = its.map((i) => i.path);
    if (!paths.length) return;
    if (a === 'cut' || a === 'copy') {
      setClip({ op: a, paths });
      d.toast(t(a === 'cut' ? 'cut_n' : 'copied_n', paths.length), 'info');
      return;
    }
    if (a === 'zipdl') return download(cfg, paths);
    try {
      if (a === 'duplicate') {
        const r = ok((await client.request(FlxFilesDuplicate, { input: { paths } })).flxFilesDuplicate);
        await report(r.failed);
        refresh();
        setSel(new Set(r.items.map((i) => i.path)));
      } else if (a === 'rename') {
        const it = its[0];
        if (!it) return;
        const v = await d.prompt({ title: t('rename'), value: it.name, ok: t('rename') });
        if (v == null || v === it.name || !v.trim()) return;
        const r = ok((await client.request(FlxFilesRename, { input: { path: it.path, name: v.trim() } })).flxFilesRename);
        d.toast(t('renamed'));
        if (r.item) {
          retarget(it.path, r.item.path);
          setSel(new Set([r.item.path]));
        }
        refresh();
      } else if (a === 'trash') {
        const msg = paths.length === 1 ? t('trash_one', its[0]?.name ?? '') : t('trash_many', paths.length);
        if (!(await d.confirm({ title: t('delete'), message: msg + ' ' + t('trash_hint'), ok: t('delete'), danger: true }))) return;
        const r = ok((await client.request(FlxFilesTrash, { input: { paths } })).flxFilesTrash);
        await report(r.failed);
        if (r.trashed.length) d.toast(t('moved_to_trash', r.trashed.length));
        editor.current?.closeTabs(paths);
        setSel(new Set());
        refresh();
      }
    } catch (e) {
      failToast(e);
    }
  };
  const paste = (dest: string) => {
    if (!clip) return;
    const c = clip;
    void transfer(c.op === 'cut' ? 'move' : 'copy', c.paths, dest).then((good) => good && c.op === 'cut' && setClip(null));
  };
  const open = (it: Item | undefined) => {
    if (!it) return;
    if (it.isDir) return go(it.path);
    if (MEDIA.includes(it.kind)) {
      const list = visible.filter((i) => MEDIA.includes(i.kind));
      return setModal({ k: 'preview', list, index: Math.max(0, list.findIndex((i) => i.path === it.path)) });
    }
    if (canEdit(it)) return openEditor(it.path);
    if (it.kind === 'ARCHIVE' && /\.zip$/i.test(it.name) && cfg.writable) return void extract(it);
    download(cfg, [it.path]);
  };

  /* ---------------------------------------------------------------- menus */
  const showMenu = (items: MenuItem[], x: number, y: number, right = false) => setMenu({ items, x, y, right });
  const newItems = (where: string): MenuItem[] => [
    { icon: 'fileplus', label: t('new_file'), run: () => void createNew(where, false) },
    { icon: 'folderplus', label: t('new_folder'), run: () => void createNew(where, true) },
  ];
  const pathItems = (path: string, isDir: boolean): MenuItem[] => [
    { label: t('path_relative'), run: () => copyText(path || '/', () => d.toast(t('copied'))) },
    { label: t('path_server'), run: () => copyText(cfg.rootPath + (path ? '/' + path : ''), () => d.toast(t('copied'))) },
    !isDir || path ? { label: t('path_url'), run: () => copyText(publicUrl(cfg, path), () => d.toast(t('copied'))) } : null,
  ];
  const pick = (title: string, run: (dest: string) => void) => setModal({ k: 'pick', title, run });
  const isUpload = (p: string) => !!cfg.uploadsPath && p.startsWith(cfg.uploadsPath + '/');
  const bgItems = (): MenuItem[] => {
    const w = cfg.writable;
    return [
      w ? { icon: 'plus', label: t('new'), sub: newItems(cwd) } : null,
      w ? { icon: 'upload', label: t('upload_files'), run: () => fileIn.current?.click() } : null,
      w ? { icon: 'paste', label: t('paste'), key: MOD + 'V', disabled: !clip, run: () => paste(cwd) } : null,
      w ? '-' : null,
      { icon: 'download', label: t('download_zip'), run: () => download(cfg, [cwd]) },
      { icon: 'link', label: t('copy_path'), sub: pathItems(cwd, true) },
      dirInfo ? { icon: 'info', label: t('folder_info'), run: () => setModal({ k: 'info', it: dirInfo }) } : null,
      '-',
      { icon: 'refresh', label: t('refresh'), run: refresh },
    ];
  };
  const ctxItems = (its: Item[]): MenuItem[] => {
    const one = its.length === 1 ? its[0] : undefined;
    const w = cfg.writable;
    const anyLocked = its.some((i) => i.locked);
    const list: MenuItem[] = [];
    if (one) {
      list.push({ icon: one.isDir ? 'folder' : canEdit(one) ? 'code' : 'eye', label: one.isDir ? t('open') : canEdit(one) ? t('edit') : t('preview'), run: () => open(one) });
      if (one.isDir && w) list.push({ icon: 'plus', label: t('new'), sub: newItems(one.path) });
      list.push('-');
    }
    list.push({ icon: 'download', label: one && !one.isDir ? t('download') : t('download_zip'), run: () => download(cfg, its.map((i) => i.path)) });
    if (w) {
      list.push({ icon: 'archive', label: t('archive'), run: () => void archive(its) });
      if (one && /\.zip$/i.test(one.name)) list.push({ icon: 'unarchive', label: t('extract'), run: () => void extract(one) });
    }
    list.push('-');
    if (w) list.push({ icon: 'cut', label: t('cut'), key: MOD + 'X', disabled: anyLocked, run: () => void act('cut', its) });
    list.push({ icon: 'copy', label: t('copy'), key: MOD + 'C', run: () => void act('copy', its) });
    if (w) list.push({ icon: 'paste', label: t('paste'), key: MOD + 'V', disabled: !clip, run: () => paste(one?.isDir ? one.path : search ? dir(its[0]?.path ?? '') : cwd) });
    if (w) {
      list.push('-');
      list.push({ icon: 'dup', label: t('duplicate'), run: () => void act('duplicate', its) });
      list.push({
        icon: 'folder',
        label: t('copy_or_move'),
        sub: [
          { label: t('move_to'), disabled: anyLocked, run: () => pick(t('move_to'), (dest) => void transfer('move', its.map((i) => i.path), dest)) },
          { label: t('copy_to'), run: () => pick(t('copy_to'), (dest) => void transfer('copy', its.map((i) => i.path), dest)) },
        ],
      });
    }
    if (one) list.push({ icon: 'link', label: t('copy_path'), sub: pathItems(one.path, one.isDir) });
    if (search && one) list.push({ icon: 'folder', label: t('show_in_folder'), run: () => { go(dir(one.path)); setSel(new Set([one.path])); } });
    list.push('-');
    if (one) {
      list.push({ icon: 'info', label: one.isDir ? t('folder_info') : t('file_info'), run: () => setModal({ k: 'info', it: one }) });
      if (w) list.push({ icon: 'lock', label: t('permissions'), run: () => setModal({ k: 'perms', it: one }) });
      if (one.isDir && w && isUpload(one.path)) list.push({ icon: 'share', label: one.shared ? t('unshare') : t('share_on_site'), run: () => void share(one) });
      if (!one.isDir) list.push({ icon: 'history', label: t('versions'), run: () => setModal({ k: 'versions', path: one.path }) });
      list.push({ icon: 'activity', label: t('activity'), run: () => { setLogPath(one.path); setMode('log'); } });
    }
    if (w) {
      list.push('-');
      if (one) list.push({ icon: 'pencil', label: t('rename'), key: 'F2', disabled: one.locked, run: () => void act('rename', its) });
      list.push({ icon: 'trash', label: t('delete'), key: 'Del', danger: true, disabled: anyLocked, run: () => void act('trash', its) });
    }
    return list;
  };

  /* ------------------------------------------------------------ selection */
  const coarse = () => !!window.matchMedia?.('(pointer:coarse)').matches;
  const longPressed = useRef(false);
  const touchTimer = useRef<number | undefined>(undefined);
  const rowClick = (e: RMouseEvent, it: Item) => {
    const p = it.path;
    if ((e.target as HTMLElement).closest('[data-rowmenu]')) {
      if (!sel.has(p)) setSel(new Set([p]));
      const r = (e.target as HTMLElement).closest('button')?.getBoundingClientRect();
      if (r) showMenu(ctxItems(sel.has(p) ? selItems() : [it]), r.right, r.bottom, true);
      return;
    }
    if (longPressed.current) {
      longPressed.current = false;
      return;
    }
    if (coarse() && !sel.size) return open(it);
    if (coarse() || e.metaKey || e.ctrlKey) {
      const s = new Set(sel);
      if (s.has(p)) s.delete(p);
      else s.add(p);
      anchor.current = p;
      return setSel(s);
    }
    if (e.shiftKey && anchor.current) {
      const ps = visible.map((i) => i.path);
      const a = ps.indexOf(anchor.current);
      const b = ps.indexOf(p);
      if (a >= 0 && b >= 0) return setSel(new Set(ps.slice(Math.min(a, b), Math.max(a, b) + 1)));
    }
    anchor.current = p;
    setSel(new Set([p]));
  };
  const rowMenu = (e: RMouseEvent, it: Item) => {
    e.preventDefault();
    e.stopPropagation();
    const s = sel.has(it.path) ? sel : new Set([it.path]);
    if (!sel.has(it.path)) {
      anchor.current = it.path;
      setSel(s);
    }
    showMenu(ctxItems(visible.filter((i) => s.has(i.path))), e.clientX, e.clientY);
  };

  /* ------------------------------------------------------------- keyboard */
  useEffect(() => {
    if (mode !== 'browse') return;
    const key = (e: KeyboardEvent) => {
      const el = e.target as HTMLElement;
      const tag = (el.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable || document.querySelector('dialog[open]') || document.querySelector('.flxfm-menu')) return;
      const mod = e.metaKey || e.ctrlKey;
      const its = visible.filter((i) => sel.has(i.path));
      const k = e.key;
      const w = cfg.writable;
      if (mod && k.toLowerCase() === 'a') {
        e.preventDefault();
        setSel(new Set(visible.map((i) => i.path)));
      } else if (mod && k.toLowerCase() === 'c' && its.length) {
        e.preventDefault();
        void act('copy', its);
      } else if (mod && k.toLowerCase() === 'x' && its.length && w) {
        e.preventDefault();
        void act('cut', its);
      } else if (mod && k.toLowerCase() === 'v' && clip && w) {
        e.preventDefault();
        paste(cwd);
      } else if ((k === 'Delete' || (k === 'Backspace' && mod)) && its.length && w) {
        e.preventDefault();
        void act('trash', its);
      } else if (k === 'F2' && its.length === 1 && w) {
        e.preventDefault();
        void act('rename', its);
      } else if (k === 'Enter' && its.length === 1) {
        e.preventDefault();
        open(its[0]);
      } else if (k === 'Backspace' && !mod) {
        e.preventDefault();
        if (search) clearSearch();
        else if (cwd) go(dir(cwd));
      } else if (k === 'Escape') {
        if (search) clearSearch();
        else setSel(new Set());
      } else if (k === '/') {
        e.preventDefault();
        setQOpen(true);
        setTimeout(() => document.querySelector<HTMLInputElement>('.flxfm-q')?.focus(), 0);
      } else if (k === 'ArrowDown' || k === 'ArrowUp' || ((k === 'ArrowLeft' || k === 'ArrowRight') && view === 'grid' && !e.altKey)) {
        e.preventDefault();
        const ps = visible.map((i) => i.path);
        if (!ps.length) return;
        const cur = anchor.current && ps.includes(anchor.current) ? ps.indexOf(anchor.current) : -1;
        let step = 1;
        if (view === 'grid' && (k === 'ArrowDown' || k === 'ArrowUp')) {
          const g = bodyRef.current?.querySelector<HTMLElement>('.flxfm-grid');
          const tile = bodyRef.current?.querySelector<HTMLElement>('.flxfm-tile');
          step = g ? Math.max(1, Math.round(g.clientWidth / (tile ? tile.offsetWidth + 12 : 150))) : 1;
        }
        const nx = Math.max(0, Math.min(ps.length - 1, cur < 0 ? 0 : cur + (k === 'ArrowDown' || k === 'ArrowRight' ? step : -step)));
        const to = ps[nx] ?? '';
        anchor.current = to;
        setSel(new Set([to]));
        bodyRef.current?.querySelector(`[data-path="${CSS.escape(to)}"]`)?.scrollIntoView({ block: 'nearest' });
      } else if (e.altKey && k === 'ArrowLeft') {
        e.preventDefault();
        goBack();
      } else if (e.altKey && k === 'ArrowRight') {
        e.preventDefault();
        goFwd();
      }
    };
    document.addEventListener('keydown', key);
    return () => document.removeEventListener('keydown', key);
  });

  const clearSearch = () => {
    searchSeq.current++;
    setSearch(null);
    setQText('');
    setQOpen(false);
  };

  /* -------------------------------------------------------------- uploads */
  const upCtl = useRef({ queue: [] as Array<{ id: number; file: File; name: string; dest: string }>, active: 0, choice: '' as '' | 'over' | 'skip', seq: 0, total: 0, done: 0 });
  const patchJob = (id: number, v: Partial<UpJob>) => setJobs((l) => l.map((j) => (j.id === id ? { ...j, ...v } : j)));
  const uploadOne = async (job: { id: number; file: File; name: string; dest: string }, overwrite = false): Promise<void> => {
    const rnd = crypto.getRandomValues(new Uint8Array(8));
    const uid = Date.now().toString(36) + Array.from(rnd, (b) => b.toString(16).padStart(2, '0')).join('');
    const f = job.file;
    let off = 0;
    patchJob(job.id, { state: 'run' });
    try {
      do {
        const end = Math.min(f.size, off + cfg.chunkSize);
        const fd = new FormData();
        fd.append('file', f.slice(off, end), 'blob');
        fd.append('dir', job.dest);
        fd.append('name', job.name);
        fd.append('id', uid);
        fd.append('offset', String(off));
        fd.append('total', String(f.size));
        if (overwrite) fd.append('overwrite', '1');
        const from = off;
        await host.client('flx-files/v1/upload', { method: 'POST', formData: fd, onUploadProgress: (r) => patchJob(job.id, { pct: f.size ? Math.round(((from + r * (end - from)) / f.size) * 100) : 100 }) });
        off = end;
      } while (off < f.size);
      patchJob(job.id, { pct: 100, state: 'ok' });
    } catch (e) {
      if (errCode(e) === 'flxfm_exists' && !overwrite) {
        const c = upCtl.current;
        if (!c.choice) c.choice = (await d.confirm({ title: t('conflict_title'), message: t('upload_conflict', job.name), ok: t('overwrite'), cancel: t('skip') })) ? 'over' : 'skip';
        if (c.choice === 'over') return uploadOne(job, true);
        patchJob(job.id, { state: 'skip' });
        return;
      }
      patchJob(job.id, { state: 'bad', msg: errText(e, t('upload_failed')) });
    }
  };
  const pump = () => {
    const c = upCtl.current;
    while (c.active < 2 && c.queue.length) {
      const job = c.queue.shift();
      if (!job) break;
      c.active++;
      void uploadOne(job).then(() => {
        c.active--;
        if (!c.queue.length && !c.active) {
          c.choice = '';
          refresh();
        }
        pump();
      });
    }
  };
  const enqueue = (files: Array<{ file: File; name: string }>, dest: string) => {
    if (!files.length) return;
    const c = upCtl.current;
    c.choice = '';
    const add: UpJob[] = files.map((f) => ({ id: ++c.seq, name: f.name, size: f.file.size, pct: 0, state: 'queue', msg: '' }));
    setJobs((l) => (l.every((j) => j.state !== 'queue' && j.state !== 'run') ? add : [...l, ...add]));
    files.forEach((f, i) => c.queue.push({ id: add[i]?.id ?? 0, file: f.file, name: f.name, dest }));
    pump();
  };
  const collectDrop = async (dt: DataTransfer): Promise<Array<{ file: File; name: string }>> => {
    const entries = Array.from(dt.items ?? []).map((i) => (i.webkitGetAsEntry ? i.webkitGetAsEntry() : null)).filter((x): x is FileSystemEntry => !!x);
    if (!entries.length) return Array.from(dt.files).map((f) => ({ file: f, name: f.name }));
    const out: Array<{ file: File; name: string }> = [];
    const walk = async (en: FileSystemEntry, pre: string): Promise<void> => {
      if (en.isFile) {
        await new Promise<void>((res) => (en as FileSystemFileEntry).file((f) => (out.push({ file: f, name: pre + f.name }), res()), () => res()));
      } else if (en.isDirectory) {
        const rd = (en as FileSystemDirectoryEntry).createReader();
        const all: FileSystemEntry[] = [];
        for (;;) {
          const batch = await new Promise<FileSystemEntry[]>((res) => rd.readEntries(res, () => res([])));
          if (!batch.length) break;
          all.push(...batch);
        }
        await Promise.all(all.map((c) => walk(c, pre + en.name + '/')));
      }
    };
    await Promise.all(entries.map((en) => walk(en, '')));
    return out;
  };

  /* ---------------------------------------------------------- drag & drop */
  const hasFiles = (e: DragEvent) => Array.from(e.dataTransfer.types ?? []).includes('Files');
  const dropTarget = (e: DragEvent): string | null => {
    const el = (e.target as HTMLElement).closest<HTMLElement>('.is-dir[data-path], [data-drop]');
    if (!el) return null;
    return el.getAttribute('data-path') ?? el.getAttribute('data-drop');
  };
  const card = {
    onDragStart: (e: DragEvent) => {
      const row = (e.target as HTMLElement).closest<HTMLElement>('[data-path]');
      if (!row) return;
      const p = row.getAttribute('data-path') ?? '';
      const paths = sel.has(p) ? [...sel] : [p];
      if (!sel.has(p)) setSel(new Set([p]));
      dragInternal.current = paths;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', paths.join('\n'));
    },
    onDragEnd: () => {
      dragInternal.current = null;
      setDropOn(null);
    },
    onDragEnter: (e: DragEvent) => {
      if (dragInternal.current || !cfg.writable || !hasFiles(e)) return;
      e.preventDefault();
      dragDepth.current++;
      setDragging(true);
    },
    onDragLeave: () => {
      if (dragInternal.current) return;
      dragDepth.current = Math.max(0, dragDepth.current - 1);
      if (!dragDepth.current) setDragging(false);
    },
    onDragOver: (e: DragEvent) => {
      if (dragInternal.current) {
        const tp = dropTarget(e);
        if (tp !== null && !dragInternal.current.includes(tp)) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          setDropOn(tp);
        } else setDropOn(null);
        return;
      }
      if (cfg.writable && hasFiles(e)) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
      }
    },
    onDrop: (e: DragEvent) => {
      if (dragInternal.current) {
        const dest = dropTarget(e);
        const paths = dragInternal.current;
        dragInternal.current = null;
        setDropOn(null);
        if (dest === null) return;
        e.preventDefault();
        if (!paths.includes(dest) && dest !== cwd) void transfer('move', paths, dest);
        return;
      }
      if (!cfg.writable || !hasFiles(e)) return;
      e.preventDefault();
      dragDepth.current = 0;
      setDragging(false);
      const row = (e.target as HTMLElement).closest<HTMLElement>('.is-dir[data-path]');
      const dest = row?.getAttribute('data-path') ?? cwd;
      void collectDrop(e.dataTransfer).then((files) => enqueue(files, dest));
    },
  };

  /* ----------------------------------------------------------- hash modes */
  useEffect(() => {
    const h = location.hash;
    if (h === '#settings') setModal({ k: 'settings' });
    if (h === '#trash') setMode('trash');
    if (h === '#activity') setMode('log');
    const open = new URLSearchParams(location.search).get('open');
    if (open) setTimeout(() => openEditor(open), 0);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  /* ---------------------------------------------------------------- render */
  const changeMode = (m: Mode) => {
    setMenu(null);
    if (m === 'log' && mode !== 'log') setLogPath('');
    setMode(m);
  };
  const parts = (mode === 'editor' && editorPath ? editorPath : '').split('/').filter(Boolean);
  const crumbParts = cwd ? cwd.split('/') : [];
  const startAt = crumbParts.length > 4 ? crumbParts.length - 3 : 0;
  const sortLabel = t((SORTS.find((s) => s[0] === sort.key && s[1] === sort.dir) ?? SORTS[0])?.[2] ?? 'sort_name_az');
  const nSel = sel.size;
  const showSelbar = mode === 'browse' && nSel > 0 && (nSel >= 2 || coarse());
  const footCount = mode === 'trash' || mode === 'log' ? count : visible.length;

  const trailBtn = (label: string, to: string, cur?: boolean, site?: boolean) => (
    <button type="button" className={'flxfm-tr' + (cur ? ' is-cur' : '') + (site ? ' flxfm-tr-site' : '')} onClick={() => go(to)}>
      {site ? <Ic n="globe" /> : null}
      <span>{label}</span>
    </button>
  );
  const sep = (k: string) => <span key={k} className="flxfm-sep"><Ic n="right" /></span>;
  let acc = '';

  const rowFor = (it: SearchItem, grid: boolean) => {
    const cls = (sel.has(it.path) ? ' is-sel' : '') + (clip?.op === 'cut' && clip.paths.includes(it.path) ? ' is-cut' : '') + (it.isDir ? ' is-dir' : '') + (dropOn === it.path ? ' is-dropt' : '');
    const flags = it.locked ? (
      <span className="flxfm-flag" title={t('locked_hint')}><Ic n="lock" /></span>
    ) : it.sensitive ? (
      <span className="flxfm-flag flxfm-flag--warn" title={t('sensitive_hint')}><Ic n="shield" /></span>
    ) : null;
    const common = {
      'data-path': it.path,
      draggable: cfg.writable && !it.locked,
      'aria-selected': sel.has(it.path),
      tabIndex: -1,
      onClick: (e: RMouseEvent) => rowClick(e, it),
      onDoubleClick: (e: RMouseEvent) => !(e.target as HTMLElement).closest('button') && open(it),
      onContextMenu: (e: RMouseEvent) => rowMenu(e, it),
      onTouchStart: (e: React.TouchEvent) => {
        const tt = e.touches[0];
        if (!tt) return;
        touchTimer.current = window.setTimeout(() => {
          longPressed.current = true;
          if (!sel.has(it.path)) setSel(new Set([it.path]));
          navigator.vibrate?.(10);
          showMenu(ctxItems(sel.has(it.path) ? selItems() : [it]), tt.clientX, tt.clientY);
        }, 500);
      },
      onTouchMove: () => window.clearTimeout(touchTimer.current),
      onTouchEnd: () => window.clearTimeout(touchTimer.current),
    };
    const menuBtn = (
      <button type="button" className="flxfm-rowmenu" data-rowmenu aria-label={t('actions')}>
        <Ic n="more" />
      </button>
    );
    const meta = it.isDir ? (it.itemCount == null ? '' : t('items_n', it.itemCount)) : size(it.size);
    if (grid) {
      return (
        <div key={it.path} className={'flxfm-tile' + cls} role="option" {...common}>
          <FileIcon it={it} big cfg={cfg} />
          <span className="flxfm-tname" title={it.name}>{it.name}{flags}</span>
          <span className="flxfm-tmeta">{meta}</span>
          {menuBtn}
        </div>
      );
    }
    const matches: Match[] | undefined = it.matches;
    return (
      <div key={it.path} className={'flxfm-row' + cls} role="row" {...common}>
        <span className="flxfm-c-name">
          <FileIcon it={it} />
          <span className="flxfm-nm">
            <span className="flxfm-nm-t" title={it.name}>{it.name}</span>
            {flags}
            {matches?.length ? (
              <span className="flxfm-match">
                {matches.map((m, i) => <span key={i}><code>{m.line}</code> {m.text}<br /></span>)}
              </span>
            ) : null}
          </span>
        </span>
        {search ? <span className="flxfm-c-where" title={dir(it.path) || '/'}>{dir(it.path) || '/'}</span> : null}
        <span className="flxfm-c-mod" title={it.modified.label}>{it.modified.relative}</span>
        <span className="flxfm-c-size">{meta || '—'}</span>
        <span className="flxfm-c-perm"><code>{it.permissions}</code></span>
        <span className="flxfm-c-menu">{menuBtn}</span>
      </div>
    );
  };
  const th = (k: SortKey, label: string, cls: string) => (
    <button type="button" className={'flxfm-th flxfm-c-' + cls + (sort.key === k ? ' is-on' : '')} onClick={() => setSort({ key: k, dir: sort.key === k ? (sort.dir === 1 ? -1 : 1) : k === 'name' ? 1 : -1 })}>
      {label}
      {sort.key === k ? <Ic n={sort.dir > 0 ? 'up' : 'down'} cls="flxfm-i-sm" /> : null}
    </button>
  );

  const dk = disk.data;
  const quota = settings.quotaGb ? settings.quotaGb * 1024 ** 3 : 0;
  const pct = dk && dk.usedBytes != null && quota ? Math.min(100, Math.max(1, Math.round((dk.usedBytes / quota) * 100))) : 0;

  return (
    <div ref={root} className={'flxfm' + (treeOff ? ' flxfm-tree-off' : '')} data-mode={mode}>
      <header className="flxfm-top">
        <nav className="flxfm-trail" aria-label={t('location')}>
          <button type="button" className="flxfm-tr" aria-label={t('root')} onClick={() => go('')}><Ic n="home" /></button>
          {sep('s0')}
          {trailBtn(cfg.host, '', false, true)}
          {mode === 'trash' || mode === 'log' ? (
            <>
              {sep('s1')}
              <span className="flxfm-tr is-cur">{t(mode === 'trash' ? 'trash' : 'activity')}</span>
            </>
          ) : (
            parts.map((p, i) => {
              acc = join(acc, p);
              const last = i === parts.length - 1;
              const to = mode === 'editor' && last ? dir(acc) : acc;
              return (
                <span key={acc} style={{ display: 'contents' }}>
                  {sep('s' + acc)}
                  {trailBtn(p, to, last)}
                </span>
              );
            })
          )}
        </nav>
        <div className="flxfm-top-actions">
          <div className="flxfm-seg" role="tablist">
            <button type="button" role="tab" className={mode === 'editor' ? '' : 'is-on'} aria-selected={mode !== 'editor'} onClick={() => changeMode('browse')}><Ic n="browser" /><span>{t('browser')}</span></button>
            <button type="button" role="tab" className={mode === 'editor' ? 'is-on' : ''} aria-selected={mode === 'editor'} onClick={() => changeMode('editor')}><Ic n="code" /><span>{t('editor')}</span></button>
          </div>
          {cfg.writable ? (
            <>
              <button type="button" className="flxfm-btn" onClick={(e) => { const r = e.currentTarget.getBoundingClientRect(); showMenu([{ icon: 'upload', label: t('upload_files'), run: () => fileIn.current?.click() }, { icon: 'folder', label: t('upload_folder'), run: () => dirIn.current?.click() }], r.right, r.bottom + 6, true); }}>
                <Ic n="upload" /><span>{t('upload')}</span><Ic n="down" cls="flxfm-i-sm" />
              </button>
              <button type="button" className="flxfm-btn" onClick={(e) => { const r = e.currentTarget.getBoundingClientRect(); showMenu(newItems(mode === 'editor' && editorPath ? dir(editorPath) : cwd), r.right, r.bottom + 6, true); }}>
                <Ic n="plus" /><span>{t('new')}</span><Ic n="down" cls="flxfm-i-sm" />
              </button>
            </>
          ) : null}
          <button type="button" className="flxfm-btn flxfm-btn--ic" aria-label={t('more')} onClick={(e) => {
            const r = e.currentTarget.getBoundingClientRect();
            showMenu([
              { icon: 'trash', label: t('trash'), run: () => changeMode('trash') },
              { icon: 'activity', label: t('activity'), run: () => changeMode('log') },
              '-',
              { icon: 'settings', label: t('settings'), run: () => setModal({ k: 'settings' }) },
              classicUrl ? '-' : null,
              classicUrl ? { icon: 'browser', label: t('classic_view'), run: () => { window.location.href = classicUrl; } } : null,
            ], r.right, r.bottom + 6, true);
          }}>
            <Ic n="more" />
          </button>
        </div>
      </header>
      {!cfg.writable ? <div className="flxfm-banner"><Ic n="lock" />{t('readonly_mode')}</div> : null}

      <section className={'flxfm-card flxfm-browse' + (dragging ? ' is-dragging' : '')} hidden={mode === 'editor'} {...card}>
        <div className="flxfm-bar">
          <div className="flxfm-nav" hidden={mode === 'trash' || mode === 'log'}>
            <button type="button" className="flxfm-ib" disabled={!back.length} onClick={goBack} aria-label={t('back')}><Ic n="left" /></button>
            <button type="button" className="flxfm-ib" disabled={!fwd.length} onClick={goFwd} aria-label={t('forward')}><Ic n="right" /></button>
            <button type="button" className="flxfm-ib" disabled={!cwd} onClick={() => go(dir(cwd))} aria-label={t('up_level')}><Ic n="up" /></button>
          </div>
          <nav className="flxfm-crumbs" aria-label={t('folder')}>
            {mode === 'trash' || mode === 'log' ? (
              <span className="flxfm-crumb is-cur"><Ic n={mode === 'trash' ? 'trash' : 'activity'} />{t(mode === 'trash' ? 'trash' : 'activity')}</span>
            ) : (
              <>
                <button type="button" className={'flxfm-crumb' + (crumbParts.length ? '' : ' is-cur') + (dropOn === '' ? ' is-dropt' : '')} data-drop="" onClick={() => go('')}>{cfg.rootName}</button>
                {startAt ? <><span className="flxfm-csep"><Ic n="right" /></span><button type="button" className="flxfm-crumb" onClick={() => go(crumbParts.slice(0, startAt).join('/'))}>…</button></> : null}
                {crumbParts.map((p, i) => {
                  const to = crumbParts.slice(0, i + 1).join('/');
                  return i < startAt ? null : (
                    <span key={to} style={{ display: 'contents' }}>
                      <span className="flxfm-csep"><Ic n="right" /></span>
                      <button type="button" className={'flxfm-crumb' + (i === crumbParts.length - 1 ? ' is-cur' : '') + (dropOn === to ? ' is-dropt' : '')} data-drop={to} onClick={() => go(to)}>{p}</button>
                    </span>
                  );
                })}
                {search ? <><span className="flxfm-csep"><Ic n="right" /></span><span className="flxfm-crumb is-cur">{t('search_results', search.q)}</span></> : null}
              </>
            )}
          </nav>
          <div className="flxfm-tools" hidden={mode === 'trash' || mode === 'log'}>
            <button type="button" className="flxfm-sortbtn" onClick={(e) => {
              const r = e.currentTarget.getBoundingClientRect();
              showMenu(SORTS.map((s) => ({ label: t(s[2]), check: sort.key === s[0] && sort.dir === s[1], run: () => setSort({ key: s[0], dir: s[1] }) })), r.right, r.bottom + 6, true);
            }}>
              <Ic n="sort" /><span>{sortLabel}</span><Ic n="down" cls="flxfm-i-sm" />
            </button>
            <div className={'flxfm-searchbox' + (qOpen || qText ? ' is-open' : '')}>
              <button type="button" className="flxfm-ib" aria-label={t('search')} onClick={() => { setQOpen(true); setTimeout(() => document.querySelector<HTMLInputElement>('.flxfm-q')?.focus(), 0); }}><Ic n="search" /></button>
              <input
                type="search"
                className="flxfm-q"
                placeholder={t('search_here')}
                aria-label={t('search')}
                value={qText}
                onChange={(e) => { setQText(e.target.value); if (!e.target.value) setSearch(null); }}
                onBlur={() => !qText && setQOpen(false)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') { e.preventDefault(); void runSearch(); }
                  if (e.key === 'Escape') { e.preventDefault(); clearSearch(); (e.target as HTMLElement).blur(); }
                }}
              />
              <label className="flxfm-chip" title={t('search_content_hint')}>
                <input type="checkbox" className="flxfm-qc" checked={qContent} onChange={(e) => { setQContent(e.target.checked); if (qText.trim().length >= 2) void runSearch(qText, e.target.checked); }} /> {t('in_content')}
              </label>
            </div>
            <button type="button" className="flxfm-ib" aria-label={t('toggle_view')} onClick={() => { const v = view === 'grid' ? 'list' : 'grid'; setViewState(v); ls.set('view', v); }}>
              <Ic n={view === 'grid' ? 'list' : 'grid'} />
            </button>
            <button type="button" className="flxfm-ib" aria-label={t('refresh')} onClick={() => { setDiskTick((n) => n + 1); refresh(); }}><Ic n="refresh" /></button>
          </div>
        </div>
        <div
          ref={bodyRef}
          className={'flxfm-body' + (folder.isFetching && !folder.isPlaceholderData ? '' : folder.isFetching ? ' is-loading' : '')}
          tabIndex={0}
          onClick={(e) => {
            const el = e.target as HTMLElement;
            if (mode === 'browse' && (el === e.currentTarget || el.closest('.flxfm-empty') || el.classList.contains('flxfm-grid') || el.classList.contains('flxfm-table'))) setSel(new Set());
          }}
          onContextMenu={(e) => {
            if (mode !== 'browse' || search) return;
            e.preventDefault();
            setSel(new Set());
            showMenu(bgItems(), e.clientX, e.clientY);
          }}
        >
          {mode === 'trash' ? <TrashView t={t} onChanged={refresh} onCount={setCount} /> : null}
          {mode === 'log' ? <LogView t={t} path={logPath} onClearPath={() => setLogPath('')} onCount={setCount} onGo={(p) => { go(dir(p)); setSel(new Set([p])); }} /> : null}
          {mode === 'browse' && folder.isPending ? <p className="flxfm-muted" style={{ padding: 16 }}>{t('loading')}</p> : null}
          {mode === 'browse' && folder.data && !visible.length ? (
            <div className="flxfm-empty">
              <Ic n={search ? 'search' : 'folder'} />
              <p>{search ? t('no_results') : t('empty_folder')}</p>
              {!search && cfg.writable ? <p className="flxfm-muted">{t('empty_hint')}</p> : null}
            </div>
          ) : null}
          {mode === 'browse' && visible.length ? (
            view === 'grid' ? (
              <div className="flxfm-grid" role="listbox" aria-multiselectable="true">{visible.map((it) => rowFor(it, true))}</div>
            ) : (
              <div className="flxfm-table" role="grid" aria-multiselectable="true">
                <div className="flxfm-thead" role="row">
                  {th('name', t('col_name'), 'name')}
                  {search ? <span className="flxfm-c-where">{t('col_where')}</span> : null}
                  {th('mtime', t('col_modified'), 'mod')}
                  {th('size', t('col_size'), 'size')}
                  <span className="flxfm-c-perm">{t('col_perms')}</span>
                  <span className="flxfm-c-menu" />
                </div>
                {visible.map((it) => rowFor(it, false))}
              </div>
            )
          ) : null}
          {mode === 'browse' && search?.partial ? <p className="flxfm-note">{t('search_partial')}</p> : null}
        </div>
        <footer className="flxfm-foot">
          <div className="flxfm-count">
            {footCount != null ? <span>{mode === 'log' ? t('events_n', footCount) : t('items_n', footCount)}</span> : null}
            {mode === 'browse' && nSel ? <span>{t('selected_n', nSel)}</span> : null}
            {mode === 'browse' && clip ? <span className="flxfm-clipinfo"><Ic n={clip.op === 'cut' ? 'cut' : 'copy'} />{t('clip_n', clip.paths.length)}</span> : null}
          </div>
          <div className="flxfm-disk">
            {dk ? (
              <>
                <span>{t('storage')}</span>
                {pct ? <span className={'flxfm-meter' + (pct >= 90 ? ' is-full' : '')}><i style={{ width: pct + '%' }} /></span> : null}
                <b title={dk.usedBytes == null ? '' : t('storage_tip', dk.fileCount == null ? '' : dk.fileCount.toLocaleString(), dk.measured)}>
                  {dk.usedBytes == null ? t('storage_measuring') : dk.totalBytes ? size(dk.usedBytes) + ' / ' + size(dk.totalBytes) : t('storage_used', size(dk.usedBytes))}
                </b>
                {dk.scanning ? <span className="flxfm-disk-spin" title={t('storage_measuring')}><Ic n="refresh" cls="flxfm-i-sm" /></span> : null}
              </>
            ) : null}
          </div>
        </footer>
        <div className="flxfm-drop"><div><Ic n="upload" /><b>{t('drop_here')}</b><span className="flxfm-drop-to">/{cwd}</span></div></div>
      </section>

      <FilesEditor
        ref={editor}
        t={t}
        active={mode === 'editor'}
        treeOff={treeOff}
        onTreeOff={setTreeOff}
        onActivePath={setEditorPath}
        onMenu={(m) => setMenu(m)}
        newItems={newItems}
        pathItems={pathItems}
        onShowInBrowser={(p, isDir) => { go(isDir ? p : dir(p)); if (!isDir) setSel(new Set([p])); }}
        onChanged={refresh}
      />

      {showSelbar ? (
        <div className="flxfm-selbar">
          <b>{t('selected_n', nSel)}</b>
          {cfg.writable ? <button type="button" onClick={() => void act('cut', selItems())}><Ic n="cut" /><span>{t('cut')}</span></button> : null}
          <button type="button" onClick={() => void act('copy', selItems())}><Ic n="copy" /><span>{t('copy')}</span></button>
          <button type="button" onClick={() => void act('zipdl', selItems())}><Ic n="download" /><span>{t('download')}</span></button>
          {cfg.writable ? <button type="button" className="is-danger" onClick={() => void act('trash', selItems())}><Ic n="trash" /><span>{t('delete')}</span></button> : null}
          <button type="button" aria-label={t('clear_selection')} onClick={() => setSel(new Set())}><Ic n="x" /></button>
        </div>
      ) : null}
      <UploadPanel jobs={jobs} onClose={() => setJobs([])} t={t} />
      <input ref={fileIn} type="file" multiple hidden onChange={(e) => { enqueue(Array.from(e.target.files ?? []).map((f) => ({ file: f, name: f.name })), cwd); e.target.value = ''; }} />
      <input ref={dirIn} type="file" multiple hidden {...({ webkitdirectory: '' } as object)} onChange={(e) => { enqueue(Array.from(e.target.files ?? []).map((f) => ({ file: f, name: (f as File & { webkitRelativePath?: string }).webkitRelativePath || f.name })), cwd); e.target.value = ''; }} />

      {menu ? <Menu at={menu} onClose={() => setMenu(null)} /> : null}
      {modal?.k === 'info' ? <InfoDialog it={modal.it} t={t} onClose={() => setModal(null)} /> : null}
      {modal?.k === 'perms' ? <PermsDialog it={modal.it} t={t} onClose={() => setModal(null)} onDone={refresh} /> : null}
      {modal?.k === 'pick' ? <PickFolder title={modal.title} start={cwd} t={t} onClose={() => setModal(null)} onPick={modal.run} /> : null}
      {modal?.k === 'preview' ? <Preview list={modal.list} index={modal.index} t={t} onClose={() => setModal(null)} /> : null}
      {modal?.k === 'versions' ? <VersionsDialog path={modal.path} settings={settings} t={t} onClose={() => setModal(null)} onRestored={refresh} /> : null}
      {modal?.k === 'settings' ? <SettingsDialog t={t} onClose={() => setModal(null)} onSaved={() => { onSettings(); setDiskTick((n) => n + 1); refreshAll(); }} /> : null}
    </div>
  );
}

void extOf;
void lower;
void errCode;
