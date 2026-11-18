// Flux Studio › image and video generation. Same screen in wp-admin (wp-embed) and the FluxWP app; every read
// and write goes through the Flux Studio GraphQL operations (plugins/flx-studio/graphql/operations).
// Gallery with folders and favorites, generation bar with references,
// people (@name), Enhance, Brainstorm, templates, viewer, area edit, compare, dashboard, settings.
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type DragEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxStudioFolderCreate, FlxStudioFolderDelete, FlxStudioFolderUpdate, FlxStudioGenerate, FlxStudioItemToLibrary, FlxStudioItemUpdate, FlxStudioItemsDelete, FlxStudioItemsMove, FlxStudioCancel, FlxStudioWorkspace } from '@fluxwp/graphql/operations';
import { useHost } from '../host';
import { translator, numberLocale, type Translate } from './i18n';
import { Brainstorm, People, SettingsDialog } from './Dialogs';
import { Composer } from './Composer';
import { Dashboard } from './Dashboard';
import { MaskEditor, Viewer } from './Viewer';
import { useGallery } from './useGallery';
import { Btn, Ic, IconBtn, TProvider, UiProvider, useT, useUi, type IconName, type MenuItem } from './ui';
import { call, dataUrlToBlob, DEFAULT_CFG, isPending, loadPref, modelById, modelsOf, normalizeCfg, providerLabel, ratioOf, refPayload, savePref, storedCfg, toKind, uploadFile, type Cfg, type CRef, type Filter, type Folder, type Item, type Template, type Workspace } from './lib';
import './studio.css';

type ThemePref = 'auto' | 'light' | 'dark';
const ThemeCtx = createContext<{ pref: ThemePref; theme: 'light' | 'dark'; cycle: () => void }>({ pref: 'auto', theme: 'light', cycle: () => undefined });

export function StudioScreen() {
  const host = useHost();
  const { i18n } = useTranslation();
  const t = useMemo(() => translator(i18n.language), [i18n.language]);
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  const rootRef = useRef<HTMLDivElement>(null);
  const [pref, setPref] = useState<ThemePref>(() => loadPref<ThemePref>('theme') ?? 'auto');
  const [hostTheme, setHostTheme] = useState<'light' | 'dark'>('light');
  useEffect(() => {
    const el = rootRef.current?.parentElement?.closest<HTMLElement>('[data-theme]');
    if (!el) return;
    const read = () => setHostTheme(el.dataset.theme === 'dark' ? 'dark' : 'light');
    read();
    const mo = new MutationObserver(read);
    mo.observe(el, { attributes: true, attributeFilter: ['data-theme'] });
    return () => mo.disconnect();
  }, []);
  const theme = pref === 'auto' ? hostTheme : pref;
  const cycle = () => {
    const next: ThemePref = pref === 'auto' ? 'light' : pref === 'light' ? 'dark' : 'auto';
    setPref(next);
    savePref('theme', next);
  };
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <TProvider value={{ t }}>
        <ThemeCtx.Provider value={{ pref, theme, cycle }}>
          <div ref={rootRef} className="flxst is-embedded" data-theme={theme} dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>
            <UiProvider t={t}>
              <Boot locale={numberLocale(i18n.language)} />
            </UiProvider>
          </div>
        </ThemeCtx.Provider>
      </TProvider>
    </FlxGraphQLProvider>
  );
}

function Boot({ locale }: { locale: string }) {
  const t = useT();
  const q = useFlxQuery(FlxStudioWorkspace, {}, { staleTime: 30_000, refetchOnWindowFocus: false });
  if (q.isError && !q.data)
    return (
      <div className="flxst-empty">
        <Ic n="alert" size={28} />
        <p>{q.error.message || t('Something went wrong.')}</p>
        <Btn label={t('Try again')} onClick={() => void q.refetch()} />
      </div>
    );
  if (!q.data)
    return (
      <div className="flxst-boot">
        <div className="flxst-spin" />
        <span>{t('Opening Studio…')}</span>
      </div>
    );
  return <Studio ws={q.data.flxStudio} locale={locale} />;
}

function Studio({ ws, locale }: { ws: Workspace; locale: string }) {
  const t = useT();
  const ui = useUi();
  const host = useHost();
  const qc = useQueryClient();
  const { client, scope } = useGraphQL();
  const theme = useContext(ThemeCtx);
  const [f, setF] = useState<Filter>({ folder: 'all', kind: '', fav: false, q: '', owner: 'me' });
  const [dash, setDash] = useState(false);
  const [nav, setNav] = useState(false);
  const [c, setC] = useState<Cfg>(storedCfg);
  const [refs, setRefs] = useState<CRef[]>([]);
  const [selecting, setSelecting] = useState(false);
  const [sel, setSel] = useState<Set<number>>(new Set());
  const [viewing, setViewing] = useState<number | null>(null);
  const [editing, setEditing] = useState<Item | null>(null);
  const [dialog, setDialog] = useState<'people' | 'people-new' | 'settings' | 'brainstorm' | null>(null);
  const [generating, setGenerating] = useState(false);
  const [search, setSearch] = useState('');
  const gal = useGallery(f);
  const galleryEl = useRef<HTMLDivElement>(null);
  const sentinel = useRef<HTMLDivElement>(null);
  const mainEl = useRef<HTMLElement>(null);
  const [composerH, setComposerH] = useState(150);
  const { cfg, model } = useMemo(() => normalizeCfg(c, ws), [c, ws]);

  const refreshWs = useCallback(() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxStudioWorkspace) }), [qc, scope]);
  const setCfg = useCallback((patch: Partial<Cfg>) => {
    setC((prev) => {
      const next = { ...prev, ...patch };
      const { prompt: _p, enhanced: _e, draft: _d, ...keep } = next;
      void _p;
      void _e;
      void _d;
      savePref('composer', keep);
      return next;
    });
  }, []);

  // Search with a short delay.
  useEffect(() => {
    const id = window.setTimeout(() => setF((x) => (x.q === search.trim() ? x : { ...x, q: search.trim() })), 350);
    return () => window.clearTimeout(id);
  }, [search]);
  // Endless gallery.
  useEffect(() => {
    const el = sentinel.current;
    if (!el || !('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((en) => en[0]?.isIntersecting && gal.hasMore && !gal.loading && gal.items.length && gal.loadMore(), { root: galleryEl.current, rootMargin: '600px' });
    io.observe(el);
    return () => io.disconnect();
  });
  // The selection bar floats above the generation bar.
  useEffect(() => {
    const el = mainEl.current?.querySelector<HTMLElement>('.flxst-composer');
    if (!el || !('ResizeObserver' in window)) return;
    const ro = new ResizeObserver(() => setComposerH(el.offsetHeight));
    ro.observe(el);
    setComposerH(el.offsetHeight);
    return () => ro.disconnect();
  }, [dash]);
  useEffect(() => {
    setSel(new Set());
  }, [f.folder, f.kind, f.fav, f.q, f.owner]);

  // Notices when generations finish.
  gal.follow(({ done, failed }) => {
    if (done) ui.toast(done > 1 ? t('{n} items ready', { n: done }) : t('Generation ready'));
    if (failed) ui.toast(t('A generation failed'), 'error');
    window.setTimeout(refreshWs, 1500);
  });

  const setFilter = (o: Partial<Filter>) => {
    setDash(false);
    setNav(false);
    setSelecting(false);
    setF((x) => ({ ...x, ...o }));
  };

  // ---- Items ----
  const byId = (id: number): Item | undefined => gal.items.find((x) => x.databaseId === id);
  const addRef = useCallback(
    (r: CRef) => {
      const max = model.maxRefs || 14;
      setRefs((l) => {
        if (l.some((x) => x.type === r.type && x.id === r.id)) {
          ui.toast(t('This reference is already added'));
          return l;
        }
        if (l.length >= max) {
          ui.toast(t('This model accepts at most {n} references', { n: max }), 'error');
          return l;
        }
        return [...l, r];
      });
    },
    [model.maxRefs, ui, t],
  );
  const toggleFav = (it: Item) => {
    const fav = !it.favorite;
    gal.patch(it.databaseId, { favorite: fav });
    void call(client.request(FlxStudioItemUpdate, { input: { id: it.databaseId, favorite: fav } }).then((x) => x.flxStudioItemUpdate)).catch((e: unknown) => {
      gal.patch(it.databaseId, { favorite: !fav });
      ui.fail(e);
    });
  };
  const deleteItems = async (ids: number[]): Promise<boolean> => {
    const ok = await ui.confirm(ids.length > 1 ? t('Delete {n} items?', { n: ids.length }) : t('Delete item?'), t('Files are deleted from the server. Copies added to the Media Library stay.'), t('Delete'), true);
    if (!ok) return false;
    try {
      const r = await call(client.request(FlxStudioItemsDelete, { input: { ids } }).then((x) => x.flxStudioItemsDelete));
      gal.remove(r.deletedIds ?? ids);
      setSel((s) => new Set([...s].filter((x) => !ids.includes(x))));
      return true;
    } catch (e) {
      ui.fail(e);
      return false;
    }
  };
  const moveItems = async (ids: number[], folderId: number): Promise<boolean> => {
    try {
      const r = await call(client.request(FlxStudioItemsMove, { input: { ids, folderId } }).then((x) => x.flxStudioItemsMove));
      const moved = r.movedIds ?? ids;
      for (const id of moved) gal.patch(id, { folderId: folderId || null });
      ui.toast(t('{n} items moved', { n: moved.length }));
      if (f.folder !== 'all') gal.reload();
      return true;
    } catch (e) {
      ui.fail(e);
      return false;
    }
  };
  const cancelJob = (it: Item) => {
    if (!it.job) return;
    const job = it.job;
    void call(client.request(FlxStudioCancel, { input: { job } }).then((x) => x.flxStudioCancel)).then((r) => (r.items ?? []).forEach((x) => gal.upsert(x)), ui.fail);
  };
  const toLibrary = async (it: Item) => {
    if (it.attachmentId) return ui.toast(t('Already in the Media Library'));
    try {
      const r = await call(client.request(FlxStudioItemToLibrary, { input: { id: it.databaseId } }).then((x) => x.flxStudioItemToLibrary));
      gal.patch(it.databaseId, { attachmentId: r.library?.attachmentId ?? 1 });
      ui.toast(t('Added to the Media Library'));
    } catch (e) {
      ui.fail(e);
    }
  };
  const setTitle = (it: Item, title: string) =>
    void call(client.request(FlxStudioItemUpdate, { input: { id: it.databaseId, title } }).then((x) => x.flxStudioItemUpdate)).then((r) => r.item && gal.upsert(r.item), ui.fail);

  // ---- Folders ----
  const folderMenuItems = (fo: Folder): MenuItem[] => [
    {
      icon: 'edit',
      label: t('Rename'),
      onClick: () =>
        void ui.prompt(t('Folder name'), fo.name).then((n) => {
          if (n) void call(client.request(FlxStudioFolderUpdate, { input: { id: fo.databaseId, name: n } }).then((x) => x.flxStudioFolderUpdate)).then(refreshWs, ui.fail);
        }),
    },
    {
      icon: 'users',
      label: fo.shared ? t('Stop sharing') : t('Share with team'),
      onClick: () =>
        void call(client.request(FlxStudioFolderUpdate, { input: { id: fo.databaseId, shared: !fo.shared } }).then((x) => x.flxStudioFolderUpdate)).then(() => {
          refreshWs();
          ui.toast(fo.shared ? t('Folder is private') : t('Folder is shared with the team'));
        }, ui.fail),
    },
    { icon: 'folderPlus', label: t('Subfolder'), onClick: () => newFolder(fo.databaseId) },
    { sep: true },
    {
      icon: 'trash',
      label: t('Delete folder'),
      danger: true,
      onClick: () =>
        void ui.confirm(t('Delete folder?'), t('Its items and subfolders move up one level; no files are deleted.'), t('Delete'), true).then((ok) => {
          if (!ok) return;
          void call(client.request(FlxStudioFolderDelete, { input: { id: fo.databaseId } }).then((x) => x.flxStudioFolderDelete)).then(() => {
            if (f.folder === fo.databaseId) setFilter({ folder: 'all' });
            refreshWs();
          }, ui.fail);
        }),
    },
  ];
  const newFolder = async (parentId: number): Promise<number | null> => {
    const name = await ui.prompt(parentId ? t('Subfolder') : t('New folder'), '', t('Create'));
    if (!name) return null;
    try {
      const r = await call(client.request(FlxStudioFolderCreate, { input: { name, parentId: parentId || null } }).then((x) => x.flxStudioFolderCreate));
      refreshWs();
      ui.toast(t('Folder created'));
      return r.folder?.databaseId ?? null;
    } catch (e) {
      ui.fail(e);
      return null;
    }
  };
  const folderPicker = (anchor: HTMLElement, ids: number[]) => {
    const items: MenuItem[] = [{ icon: 'home', label: t('Unfiled'), onClick: () => void moveItems(ids, 0).then(() => setSelecting(false)) }];
    const walk = (parent: number, depth: number) => {
      for (const fo of ws.folders.filter((x) => (x.parentId ?? 0) === parent && (x.ownerId === ws.userId || x.shared || ws.canManage))) {
        items.push({ icon: 'folder', label: '  '.repeat(depth) + fo.name, onClick: () => void moveItems(ids, fo.databaseId).then(() => setSelecting(false)) });
        walk(fo.databaseId, depth + 1);
      }
    };
    walk(0, 0);
    items.push({ sep: true }, { icon: 'folderPlus', label: t('New folder…'), onClick: () =>
        void newFolder(0).then((id) => {
          if (id) void moveItems(ids, id).then(() => setSelecting(false));
        }),
    });
    ui.menu(anchor, items);
  };

  // ---- Generating ----
  interface Extra {
    prompt?: string;
    title?: string;
    idea?: string;
    batch?: string;
    parentId?: number;
    maskUploadId?: string;
    layers?: string[];
    model?: string;
    count?: number;
  }
  const generate = async (extra: Extra = {}): Promise<boolean> => {
    const prompt = (extra.prompt ?? cfg.prompt).trim();
    if (!prompt) {
      ui.toast(t('Describe what to generate.'), 'error');
      return false;
    }
    if (refs.some((r) => typeof r.id !== 'number')) {
      ui.toast(t('References are uploading, one moment…'));
      return false;
    }
    const own = extra.prompt === undefined;
    const edit = !!extra.maskUploadId;
    setGenerating(true);
    try {
      const mdl = extra.model ? modelById(ws, extra.model) ?? model : model;
      const video = !edit && cfg.kind === 'video';
      const r = await call(
        client
          .request(FlxStudioGenerate, {
            input: {
              kind: toKind(edit ? 'image' : cfg.kind),
              model: edit ? mdl.id : cfg.model,
              provider: edit ? 'auto' : cfg.provider || 'auto',
              prompt,
              count: extra.count ?? cfg.count,
              aspect: cfg.aspect,
              resolution: cfg.resolution,
              quality: cfg.quality,
              refs: refPayload(refs),
              folderId: f.folder !== 'all' ? f.folder : 0,
              enhanced: own && cfg.enhanced,
              draft: own && cfg.enhanced ? cfg.draft : '',
              duration: video ? cfg.duration : null,
              audio: video ? cfg.audio : null,
              batch: extra.batch ?? null,
              title: extra.title ?? null,
              idea: extra.idea ?? null,
              parentId: extra.parentId ?? null,
              maskUploadId: extra.maskUploadId ?? null,
              layers: extra.layers ?? null,
            },
          })
          .then((x) => x.flxStudioGenerate),
      );
      (r.items ?? []).slice().reverse().forEach((it) => gal.upsert(it, true));
      galleryEl.current?.scrollTo({ top: 0, behavior: 'smooth' });
      if (own) setCfg({ prompt: '', enhanced: false, draft: '' });
      gal.kick(3000);
      return true;
    } catch (e) {
      ui.fail(e);
      return false;
    } finally {
      setGenerating(false);
    }
  };

  const applyParams = (kind: 'image' | 'video', modelId: string, p: Partial<Cfg>, prompt: string, r: CRef[]) => {
    setCfg({ kind, model: modelId, ...p, prompt, enhanced: false, draft: '' });
    setRefs(r);
  };
  const reuse = (it: Item) => {
    const p = it.params;
    applyParams(
      it.kind === 'VIDEO' ? 'video' : 'image',
      it.model,
      { ...(p.aspect ? { aspect: p.aspect } : {}), ...(p.resolution ? { resolution: p.resolution } : {}), ...(p.quality ? { quality: p.quality } : {}), ...(p.duration ? { duration: p.duration } : {}), ...(p.count ? { count: p.count } : {}), ...(p.audio !== null ? { audio: p.audio } : {}) },
      it.prompt,
      p.refs.filter((r) => r.role !== 'edit').map((r) => ({ type: r.type, id: r.id, role: r.role ?? '', name: r.name ?? '', thumb: r.thumb ?? '' })),
    );
  };
  const applyTemplate = (tp: Template) => {
    const p = tp.params;
    applyParams(
      p.kind === 'VIDEO' ? 'video' : 'image',
      p.model ?? '',
      { ...(p.aspect ? { aspect: p.aspect } : {}), ...(p.resolution ? { resolution: p.resolution } : {}), ...(p.quality ? { quality: p.quality } : {}), ...(p.duration ? { duration: p.duration } : {}), ...(p.count ? { count: p.count } : {}), ...(p.audio !== null ? { audio: p.audio } : {}) },
      tp.prompt,
      p.refs.map((r) => ({ type: r.type, id: r.id, role: r.role ?? '', name: r.name ?? '', thumb: r.thumb ?? '' })),
    );
  };
  const editArea = (it: Item) => {
    const own = modelById(ws, it.model);
    const target = own?.mask ? own.id : modelsOf(ws, 'image').find((x) => x.mask && x.providers.length)?.id;
    if (!target) return ui.toast(t('No model with area editing and a configured key.'), 'error');
    setEditing(it);
  };
  const submitEdit = async (it: Item, mask: string, prompt: string, layers: string[]) => {
    const own = modelById(ws, it.model);
    const target = own?.mask ? own.id : modelsOf(ws, 'image').find((x) => x.mask && x.providers.length)?.id;
    const uploadId = await uploadFile(host.client, 'flx-studio/mask', dataUrlToBlob(mask), 'mask.png');
    const ok = await generate({ prompt, parentId: it.databaseId, maskUploadId: uploadId, layers, count: 1, ...(target ? { model: target } : {}) });
    if (!ok) throw new Error('');
    ui.toast(t('Edit started; the result will appear in the gallery'));
  };

  // ---- Selection ----
  const toggleSel = (id: number) => {
    setSelecting(true);
    setSel((s) => {
      const n = new Set(s);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  };
  const selIds = [...sel];

  // ---- Titles ----
  const folder = f.folder === 'all' ? null : ws.folders.find((x) => x.databaseId === f.folder);
  const title = dash ? t('Dashboard') : f.fav ? t('Favorites') : f.folder === 0 ? t('Unfiled') : f.folder !== 'all' ? folder?.name ?? t('Folder') : f.kind === 'image' ? t('Images') : f.kind === 'video' ? t('Videos') : t('All');
  const allBrowse = f.folder === 'all' && !f.fav && !dash;
  const budget = ws.budget.find((b) => b.limit > 0);
  const mediaReady = ws.aiMedia && ws.catalog.models.some((m) => m.providers.length);

  const navItem = (icon: IconName, label: string, on: boolean, onClick: () => void, extra?: ReactNode, style?: React.CSSProperties, drop?: number) => (
    <FolderNav key={label + String(drop)} icon={icon} label={label} on={on} onClick={onClick} extra={extra} style={style} dropFolder={drop} onDropItems={(ids, fid) => void moveItems(ids, fid)} />
  );
  const tree = (parent: number, depth: number): ReactNode[] =>
    ws.folders
      .filter((x) => (x.parentId ?? 0) === parent)
      .sort((a, b) => a.name.localeCompare(b.name, locale))
      .flatMap((fo) => {
        const mine = fo.ownerId === ws.userId || ws.canManage;
        return [
          <FolderNav
            key={fo.databaseId}
            icon="folder"
            label={fo.name}
            on={f.folder === fo.databaseId && !dash}
            onClick={() => setFilter({ folder: fo.databaseId, fav: false, kind: '' })}
            style={{ paddingLeft: 10 + depth * 14 }}
            dropFolder={fo.databaseId}
            onDropItems={(ids, fid) => void moveItems(ids, fid)}
            extra={
              <span className="flxst-nav-x">
                {fo.shared && (
                  <span className="flxst-tag" title={t('Shared with the team')}>
                    <Ic n="users" size={12} />
                  </span>
                )}
                {mine && (
                  <IconBtn
                    icon="dots"
                    title={t('Folder actions')}
                    cls="is-xs"
                    onClick={(e) => {
                      e.stopPropagation();
                      ui.menu(e.currentTarget, folderMenuItems(fo));
                    }}
                  />
                )}
              </span>
            }
          />,
          ...tree(fo.databaseId, depth + 1),
        ];
      });

  return (
    <div className={'flxst-app' + (nav ? ' is-nav' : '') + (selecting ? ' is-selecting' : '') + (dash ? ' is-dash' : '')} style={{ display: 'contents' }}>
      <aside className="flxst-side">
        <div className="flxst-brand">
          <span className="flxst-logo">
            <Ic n="sparkle" size={18} />
          </span>
          <strong>Flux Studio</strong>
          <span className="flxst-ver">v{ws.version}</span>
        </div>
        <nav className="flxst-navs">
          {navItem('grid', t('All'), allBrowse && !f.kind, () => setFilter({ folder: 'all', fav: false, kind: '' }))}
          {navItem('star', t('Favorites'), f.fav && !dash, () => setFilter({ folder: 'all', fav: true, kind: '' }))}
          {navItem('image', t('Images'), allBrowse && f.kind === 'image', () => setFilter({ folder: 'all', fav: false, kind: 'image' }))}
          {navItem('video', t('Videos'), allBrowse && f.kind === 'video', () => setFilter({ folder: 'all', fav: false, kind: 'video' }))}
        </nav>
        {ws.canManage && (
          <nav className="flxst-navs is-admin">
            {navItem('chart', t('Dashboard'), dash, () => {
              setDash(true);
              setNav(false);
              setSelecting(false);
            })}
          </nav>
        )}
        <div className="flxst-side-h">
          <span>{t('Folders')}</span>
          <IconBtn icon="folderPlus" title={t('New folder')} onClick={() => void newFolder(0)} />
        </div>
        <div className="flxst-tree">
          {navItem('home', t('Unfiled'), f.folder === 0 && !dash, () => setFilter({ folder: 0, fav: false, kind: '' }), undefined, undefined, 0)}
          {tree(0, 0)}
        </div>
        <div className="flxst-side-h">
          <span>{t('People')}</span>
          <IconBtn icon="plus" title={t('Add person')} onClick={() => setDialog('people-new')} />
        </div>
        <div className="flxst-people">
          {ws.people.slice(0, 12).map((p) => {
            const photo = p.photos[0];
            return (
              <button key={p.databaseId} type="button" className="flxst-person" title={t('{name} — add to generation', { name: p.name })} onClick={() => addRef({ type: 'PERSON', id: p.databaseId, role: '', name: p.name, thumb: photo?.thumb ?? '' })}>
                {photo ? <img src={photo.thumb} alt="" /> : <span className="flxst-av">{p.name.charAt(0).toUpperCase()}</span>}
                <span>{p.name}</span>
              </button>
            );
          })}
          {!ws.people.length && <p className="flxst-muted is-small">{t('Add people for face references; use them by typing @name in the prompt.')}</p>}
          <button type="button" className="flxst-link" onClick={() => setDialog('people')}>
            {t('Manage people')}
          </button>
        </div>
        <div className="flxst-side-foot">
          {budget && (
            <div className={'flxst-budget' + (budget.over ? ' is-over' : '')} title={t('Flux AI spending limit')}>
              <div className="flxst-budget-t">
                <span>{budget.label}</span>
                <span>
                  ${budget.spent.toFixed(2)} / ${budget.limit.toFixed(2)}
                </span>
              </div>
              <div className="flxst-bar">
                <i style={{ width: Math.min(100, budget.pct) + '%' }} />
              </div>
            </div>
          )}
          <div className="flxst-row">
            {host.adminUrl && (
              <a className="flxst-ib" href={host.adminUrl} title={t('Back to dashboard')} aria-label={t('Back to dashboard')}>
                <Ic n="back" size={18} />
              </a>
            )}
            <IconBtn
              icon={theme.theme === 'dark' ? 'moon' : 'sun'}
              title={t('Theme')}
              onClick={() => {
                theme.cycle();
                ui.toast(theme.pref === 'auto' ? t('Theme: light') : theme.pref === 'light' ? t('Theme: dark') : t('Theme: system'));
              }}
            />
            {ws.canManage && <IconBtn icon="settings" title={t('Studio settings')} onClick={() => setDialog('settings')} />}
          </div>
        </div>
      </aside>
      <main className="flxst-main" ref={mainEl}>
        <header className="flxst-top">
          <IconBtn icon="menu" title={t('Menu')} cls="flxst-only-m" onClick={() => setNav(!nav)} />
          <h1 className="flxst-title">{title}</h1>
          {!dash && (
            <>
              <div className="flxst-searchbox">
                <Ic n="search" size={16} />
                <input className="flxst-search" type="search" placeholder={t('Search prompt, title or model')} aria-label={t('Search')} value={search} onChange={(e) => setSearch(e.target.value)} />
              </div>
              {ws.canManage && (
                <div className="flxst-seg is-small">
                  {(['me', 'all'] as const).map((o) => (
                    <button key={o} type="button" className={'flxst-seg-b' + (f.owner === o ? ' is-on' : '')} onClick={() => setF((x) => ({ ...x, owner: o }))}>
                      {o === 'me' ? t('Mine') : t('Everyone')}
                    </button>
                  ))}
                </div>
              )}
              <IconBtn
                icon="check"
                title={t('Select')}
                cls="flxst-selbtn"
                onClick={() => {
                  setSelecting(!selecting);
                  setSel(new Set());
                }}
              />
            </>
          )}
        </header>
        {!mediaReady && !dash && (
          <div className="flxst-banner">
            <Ic n="alert" size={16} />
            <span>{ws.aiMedia ? t('To generate images and videos, enter at least one key (fal.ai, EvoLink…) in Flux AI & MCP › Settings › Images and video.') : t('Flux AI (0.6.0+) is not installed. Generation runs through Flux AI.')}</span>
            {ws.setupUrl && (
              <a className="flxst-btn is-ghost" href={ws.setupUrl}>
                {t('Flux AI settings')}
              </a>
            )}
          </div>
        )}
        {dash ? (
          <Dashboard locale={locale} />
        ) : (
          <>
            <div className="flxst-gallery" ref={galleryEl}>
              <div className="flxst-grid">
                {gal.loading && !gal.items.length && Array.from({ length: 8 }, (_, i) => <div key={i} className="flxst-card is-sk" style={{ aspectRatio: [1, 0.75, 1.33, 1][i % 4] ?? 1 }} />)}
                {!gal.loading && !gal.items.length && (
                  <div className="flxst-empty flxst-grid-empty">
                    <Ic n={gal.error || f.q || f.fav || f.kind || f.folder !== 'all' ? 'search' : 'sparkle'} size={30} />
                    <h3>{gal.error ? t('Something went wrong.') : f.q || f.fav || f.kind || f.folder !== 'all' ? t('Nothing here') : t('Create your first image')}</h3>
                    <p>{gal.error || (f.q || f.fav || f.kind || f.folder !== 'all' ? t('Change the filter or start a new generation below.') : t('Describe what you want below. Drag in reference images or add people with @.'))}</p>
                  </div>
                )}
                {gal.items.map((it) => (
                  <Card
                    key={it.databaseId}
                    it={it}
                    ws={ws}
                    selected={sel.has(it.databaseId)}
                    selecting={selecting}
                    onOpen={() => setViewing(it.databaseId)}
                    onToggleSel={() => toggleSel(it.databaseId)}
                    onFav={() => toggleFav(it)}
                    onCancel={() => cancelJob(it)}
                    onReuse={() => reuse(it)}
                    onDelete={() => void deleteItems([it.databaseId])}
                    dragIds={() => (sel.has(it.databaseId) ? selIds : [it.databaseId])}
                  />
                ))}
              </div>
              <div ref={sentinel} className="flxst-sentinel" />
            </div>
            {selecting && (
              <div className="flxst-selbar" style={{ bottom: composerH + 28 }}>
                <strong>{sel.size ? t('{n} selected', { n: sel.size }) : t('Tap items to select')}</strong>
                <span className="flxst-grow" />
                {sel.size > 0 && (
                  <>
                    <Btn
                      icon="layers"
                      label={t('Use as reference')}
                      cls="is-ghost"
                      onClick={() => {
                        for (const id of selIds) {
                          const it = byId(id);
                          if (it && it.state === 'DONE' && it.kind === 'IMAGE') addRef({ type: 'ITEM', id, role: '', name: it.title, thumb: it.thumb ?? '' });
                        }
                        setSelecting(false);
                        setSel(new Set());
                      }}
                    />
                    <Btn icon="move" label={t('Move')} cls="is-ghost" onClick={(e) => folderPicker(e.currentTarget, selIds)} />
                    <Btn
                      icon="trash"
                      label={t('Delete')}
                      cls="is-ghost is-danger"
                      onClick={() =>
                        void deleteItems(selIds).then((ok) => {
                          if (ok) {
                            setSelecting(false);
                            setSel(new Set());
                          }
                        })
                      }
                    />
                  </>
                )}
                <IconBtn
                  icon="x"
                  title={t('Exit selection')}
                  onClick={() => {
                    setSelecting(false);
                    setSel(new Set());
                  }}
                />
              </div>
            )}
            <Composer ws={ws} cfg={cfg} model={model} setCfg={setCfg} refs={refs} setRefs={setRefs} addRef={addRef} items={gal.items} generating={generating} locale={locale} onGenerate={() => void generate()} onBrainstorm={() => setDialog('brainstorm')} onApplyTemplate={applyTemplate} onChanged={refreshWs} />
          </>
        )}
      </main>
      <div className="flxst-scrim" onClick={() => setNav(false)} />
      {viewing !== null && (
        <Viewer
          ws={ws}
          items={gal.items}
          startId={viewing}
          locale={locale}
          onClose={() => setViewing(null)}
          onFav={toggleFav}
          onReuse={reuse}
          onAsRef={(it) => {
            addRef({ type: 'ITEM', id: it.databaseId, role: '', name: it.title, thumb: it.thumb ?? '' });
            ui.toast(t('Added to references'));
          }}
          onEditArea={editArea}
          onMove={(a, ids) => folderPicker(a, ids)}
          onDelete={deleteItems}
          onTitle={setTitle}
          onLibrary={toLibrary}
        />
      )}
      {editing && <MaskEditor item={editing} onClose={() => setEditing(null)} onSubmit={(m, p, l) => submitEdit(editing, m, p, l)} />}
      {dialog === 'people' || dialog === 'people-new' ? <People ws={ws} addNew={dialog === 'people-new'} onClose={() => setDialog(null)} onChanged={refreshWs} /> : null}
      {dialog === 'settings' && <SettingsDialog ws={ws} onClose={() => setDialog(null)} onChanged={refreshWs} />}
      {dialog === 'brainstorm' && (
        <Brainstorm
          ws={ws}
          cfg={cfg}
          model={model}
          refs={refs}
          brief0={cfg.prompt}
          locale={locale}
          onClose={() => setDialog(null)}
          onRun={(idea) => generate({ prompt: idea.prompt, title: idea.title, idea: idea.title, batch: idea.batch, count: 1 })}
        />
      )}
    </div>
  );
}

function FolderNav({ icon, label, on, onClick, extra, style, dropFolder, onDropItems }: { icon: IconName; label: string; on: boolean; onClick: () => void; extra?: ReactNode; style?: React.CSSProperties | undefined; dropFolder?: number | undefined; onDropItems: (ids: number[], folder: number) => void }) {
  const [over, setOver] = useState(false);
  const droppable = dropFolder !== undefined;
  return (
    <div
      className={'flxst-nav' + (on ? ' is-on' : '') + (over ? ' is-drop' : '')}
      role="button"
      tabIndex={0}
      style={style}
      onClick={onClick}
      onKeyDown={(e) => e.key === 'Enter' && onClick()}
      onDragOver={(e) => {
        if (!droppable || !e.dataTransfer.types.includes('application/x-flxst-items')) return;
        e.preventDefault();
        setOver(true);
      }}
      onDragLeave={() => setOver(false)}
      onDrop={(e) => {
        setOver(false);
        const raw = e.dataTransfer.getData('application/x-flxst-items');
        if (!droppable || !raw) return;
        e.preventDefault();
        try {
          onDropItems(JSON.parse(raw) as number[], dropFolder);
        } catch {
          /* not ours */
        }
      }}
    >
      <Ic n={icon} size={17} />
      <span className="flxst-nav-l">{label}</span>
      {extra}
    </div>
  );
}

function Card({ it, ws, selected, selecting, onOpen, onToggleSel, onFav, onCancel, onReuse, onDelete, dragIds }: { it: Item; ws: Workspace; selected: boolean; selecting: boolean; onOpen: () => void; onToggleSel: () => void; onFav: () => void; onCancel: () => void; onReuse: () => void; onDelete: () => void; dragIds: () => number[] }) {
  const t: Translate = useT();
  const video = useRef<HTMLVideoElement>(null);
  const pr = it.params;
  const label = (modelById(ws, it.model)?.label ?? it.model) + (it.provider ? ' · ' + providerLabel(ws, it.provider) : '');
  const done = it.state === 'DONE';
  const click = (e: React.MouseEvent) => {
    if ((e.target as HTMLElement).closest('button')) return;
    if (selecting || e.shiftKey || e.metaKey || e.ctrlKey) return onToggleSel();
    if (done) onOpen();
  };
  return (
    <div
      className={'flxst-card is-' + it.state.toLowerCase() + (selected ? ' is-sel' : '')}
      style={{ aspectRatio: ratioOf(it) }}
      tabIndex={0}
      draggable={done}
      onClick={click}
      onKeyDown={(e) => e.key === 'Enter' && done && onOpen()}
      onDragStart={(e: DragEvent) => {
        e.dataTransfer.setData('application/x-flxst-items', JSON.stringify(dragIds()));
        if (it.url) e.dataTransfer.setData('text/uri-list', it.url);
        e.dataTransfer.effectAllowed = 'copyMove';
      }}
      onMouseEnter={() => void video.current?.play().catch(() => undefined)}
      onMouseLeave={() => video.current?.pause()}
    >
      {done ? (
        <>
          {it.kind === 'VIDEO' ? (
            <>
              <video ref={video} src={it.url ?? ''} muted loop playsInline preload="metadata" />
              <span className="flxst-badge">
                <Ic n="video" size={12} />
                {pr.duration ? pr.duration + 's' : ''}
              </span>
            </>
          ) : (
            <img src={it.thumb || it.url || ''} alt={it.title || it.prompt.slice(0, 80)} loading="lazy" draggable={false} />
          )}
          <div className="flxst-card-over">
            <div className="flxst-card-top">
              <span className="flxst-check">
                <Ic n="check" size={14} />
              </span>
              <span className="flxst-grow" />
              <button type="button" className={'flxst-ib is-xs is-fav' + (it.favorite ? ' is-on' : '')} title={it.favorite ? t('Remove from favorites') : t('Add to favorites')} onClick={onFav}>
                <Ic n="star" size={15} />
              </button>
            </div>
            <div className="flxst-card-cap">
              {it.title && <strong>{it.title}</strong>}
              <span>{it.prompt}</span>
            </div>
          </div>
        </>
      ) : isPending(it) ? (
        <div className="flxst-pend">
          <div className="flxst-spin" />
          <strong>{it.state === 'QUEUED' ? t('Queued') : t('Generating')}</strong>
          <small>{label}</small>
          {pr.progress > 0 && (
            <div className="flxst-bar">
              <i style={{ width: Math.round(pr.progress * 100) + '%' }} />
            </div>
          )}
          <button type="button" className="flxst-link" onClick={onCancel}>
            {t('Cancel')}
          </button>
        </div>
      ) : (
        <div className="flxst-pend is-err">
          <Ic n={it.state === 'CANCELLED' ? 'stop' : 'alert'} size={22} />
          <strong>{it.state === 'CANCELLED' ? t('Cancelled') : t('Failed')}</strong>
          <small>{label}</small>
          <small className="flxst-err">{it.error ?? ''}</small>
          <div className="flxst-row">
            <button type="button" className="flxst-link" onClick={onReuse}>
              {t('Reuse settings')}
            </button>
            <button type="button" className="flxst-link" onClick={onDelete}>
              {t('Delete')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export { DEFAULT_CFG };
