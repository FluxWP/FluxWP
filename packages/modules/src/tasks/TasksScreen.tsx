// Flux Tasks ana ekranı (WP assets/js/pages.js karşılığı): sol menü (Benim görevlerim, Bildirimler, proje ağacı),
// görünüm sekmeleri, araç çubuğu, liste/pano/takvim, bildirim kutusu, görev penceresi. WP ile aynı tasarım (flxg.css).
import { useEffect, useMemo, useRef, useState, useSyncExternalStore, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { flxg, trimEnd, type FlxgList, type FlxgNotif, type FlxgView } from '@fluxwp/core';
import { useHost } from '../host';
import { addDays, dayKey, rank, store, FlxgEngine, type Route } from './engine';
import { setYtgLang, tt, flxgLocale } from './i18n';
import { ago, Avatar, fullTime, Ic, Layer, Ui, FlxgCtx, useYtg, type Ctx } from './ui';
import { BoardView, BulkBar, CalendarView, EmptyState, ListView, quickCreate, Toolbar } from './views';
import { TaskDrawer } from './TaskModal';
import './flxg.css';

const cls = (...a: (string | false | null | undefined | 0)[]) => a.filter(Boolean).join(' ');

/* ---------------- Adres ⇄ yönlendirme (WP'de #/list/3/v/5/t/9, uygulamada yerel kayıt) ---------------- */
function parseHash(h: string): Partial<Route> {
  const p = h.replace(/^#\/?/, '').split('/');
  const r: Partial<Route> = { page: 'home', id: 0, view: 0, task: 0 };
  let i = 0;
  if (p[0] === 'list' || p[0] === 'space') {
    r.page = p[0];
    r.id = +(p[1] ?? 0);
    i = 2;
  } else if (p[0] === 'inbox' || p[0] === 'home') {
    r.page = p[0];
    i = 1;
  } else if (p[0] === 'task') {
    r.task = +(p[1] ?? 0);
    return r;
  }
  while (i < p.length) {
    if (p[i] === 'v') r.view = +(p[i + 1] ?? 0);
    if (p[i] === 't') r.task = +(p[i + 1] ?? 0);
    i += 2;
  }
  return r;
}
function toHash(r: Route) {
  let h = '#/' + (r.page === 'list' || r.page === 'space' ? r.page + '/' + r.id : r.page);
  if (r.view && r.page !== 'inbox') h += '/v/' + r.view;
  if (r.task) h += '/t/' + r.task;
  return h;
}

function useTheme(ref: React.RefObject<HTMLElement | null>) {
  const read = () => {
    const el = ref.current?.parentElement?.closest<HTMLElement>('[data-theme]') ?? document.documentElement;
    const v = el.dataset.theme;
    if (v === 'dark' || v === 'light') return v;
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  };
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  useEffect(() => {
    setTheme(read());
    const mo = new MutationObserver(() => setTheme(read()));
    mo.observe(document.documentElement, { attributes: true, subtree: true, attributeFilter: ['data-theme'] });
    const mq = window.matchMedia?.('(prefers-color-scheme: dark)');
    const f = () => setTheme(read());
    mq?.addEventListener('change', f);
    return () => {
      mo.disconnect();
      mq?.removeEventListener('change', f);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return theme;
}

export function TasksScreen() {
  const host = useHost();
  const { i18n } = useTranslation();
  setYtgLang(i18n.language);
  const rootRef = useRef<HTMLDivElement>(null);
  const theme = useTheme(rootRef);
  const c = useMemo<Ctx>(() => {
    const initial = host.mode === 'wp' ? parseHash(location.hash) : (store.json<Partial<Route>>('route-' + host.scope) ?? {});
    const e = new FlxgEngine(flxg(host.client), initial);
    const ui = new Ui();
    e.onError = ui.fail;
    e.onRoute = (r) => {
      if (host.mode === 'wp') {
        const h = toHash(r);
        if (location.hash !== h) history.replaceState(null, '', h);
      } else store.set('route-' + host.scope, JSON.stringify({ ...r, task: 0 }));
    };
    e.onNotify = (list: FlxgNotif[]) => {
      for (const n of list.slice(0, 3))
        host.notify?.({ title: notifText(e, n), body: n.title, onClick: () => void e.go({ page: 'inbox', task: n.task }) });
      host.refreshCounts?.();
    };
    return { e, ui };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [host.client, host.scope]);
  useEffect(() => {
    void c.e.boot();
    const onHash = () => host.mode === 'wp' && void c.e.go(parseHash(location.hash));
    window.addEventListener('hashchange', onHash);
    const vis = () => !document.hidden && c.e.schedule(500);
    document.addEventListener('visibilitychange', vis);
    return () => {
      window.removeEventListener('hashchange', onHash);
      document.removeEventListener('visibilitychange', vis);
      c.e.dispose();
    };
  }, [c, host.mode]);

  return (
    <FlxgCtx.Provider value={c}>
      <Shell rootRef={rootRef} theme={theme} />
    </FlxgCtx.Provider>
  );
}

function Shell({ rootRef, theme }: { rootRef: React.RefObject<HTMLDivElement | null>; theme: string }) {
  const c = useYtg();
  const { e } = c;
  const S = e.S;
  const [sideCol, setSideCol] = useState(() => store.get('side-col') === '1');
  const [navOpen, setNavOpen] = useState(false);
  const [sideW, setSideW] = useState(() => {
    const w = Number(store.get('side-w'));
    return w >= 200 && w <= 480 ? w : 0;
  });
  const [resizing, setResizing] = useState(false);
  const mobile = useSyncExternalStore(
    (f) => (window.addEventListener('resize', f), () => window.removeEventListener('resize', f)),
    () => window.innerWidth <= 720,
  );
  const toggleSide = () => {
    if (mobile) return setNavOpen(true);
    store.set('side-col', sideCol ? null : '1');
    setSideCol(!sideCol);
  };
  const setCol = (v: boolean) => (store.set('side-col', v ? '1' : null), setSideCol(v));
  const closeNav = () => setNavOpen(false);
  useEffect(closeNav, [S.route.page, S.route.id]);

  let main: ReactNode;
  if (S.error && !S.ready)
    main = (
      <div className="flxg-empty">
        <div className="flxg-empty-ic">
          <Ic n="x" s={34} />
        </div>
        <p>{S.error}</p>
        <button type="button" className="flxg-btn" onClick={() => ((S.error = ''), void e.boot())}>
          {tt("Try again")}
        </button>
      </div>
    );
  else if (!S.ready) main = <Skeleton />;
  else if (S.route.page === 'inbox') main = <Inbox toggleSide={toggleSide} mobile={mobile} />;
  else main = <ScopePage toggleSide={toggleSide} mobile={mobile} />;

  return (
    <div
      ref={rootRef}
      className={cls('flxg', 'flxg-flux', sideCol && 'side-collapsed', navOpen && 'nav-open', resizing && 'is-side-resizing')}
      data-theme={theme}
      style={sideW ? ({ ['--side-w' as string]: sideW + 'px' } as React.CSSProperties) : undefined}
      onPointerDown={() => e.touchInput()}
      onKeyDown={() => e.touchInput()}
    >
      <div className="flxg-shell">
        {S.ready && <Side closeNav={closeNav} hide={() => setCol(true)} setW={setSideW} setResizing={setResizing} collapse={() => setCol(true)} />}
        <div className="flxg-nav-shade" onClick={closeNav} />
        <main className="flxg-main">{main}</main>
      </div>
      {S.ready && <TaskDrawer />}
      <Layer theme={theme} />
    </div>
  );
}

function Skeleton() {
  return (
    <div className="flxg-content">
      <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 14 }}>
        {['35%', '80%', '72%', '86%', '64%', '78%'].map((w, i) => (
          <span key={i} className="flxg-sk" style={{ display: 'block', width: w, height: i ? 16 : 24 }} />
        ))}
      </div>
    </div>
  );
}

/* ---------------- Sol menü ---------------- */
function useCollapsedMap(key: string) {
  const [m, setM] = useState<Record<string, 1>>(() => store.json<Record<string, 1>>(key) ?? {});
  const set = (k: string | number, v: boolean) => {
    const n = { ...m };
    if (v) n[k] = 1;
    else delete n[k];
    store.set(key, JSON.stringify(n));
    setM(n);
  };
  return [m, set] as const;
}
function Side({ closeNav, hide, setW, setResizing, collapse }: { closeNav: () => void; hide: () => void; setW: (w: number) => void; setResizing: (b: boolean) => void; collapse: () => void }) {
  const c = useYtg();
  const { e } = c;
  const S = e.S;
  const host = useHost();
  const r = S.route;
  const sideRef = useRef<HTMLElement>(null);
  const [spOpen, setSpOpen] = useCollapsedMap('sp-open');
  const [col, setColl] = useCollapsedMap('collapsed');
  // O an açık listenin projesi (ve klasörü) açılır.
  const auto = useRef('');
  useEffect(() => {
    const key = r.page + r.id;
    if (auto.current === key) return;
    auto.current = key;
    const l = r.page === 'list' ? e.list(r.id) : null;
    const sid = r.page === 'space' ? r.id : l ? l.space_id : 0;
    if (sid && !spOpen[sid]) setSpOpen(sid, true);
    if (l?.folder_id && col['f' + l.folder_id]) setColl('f' + l.folder_id, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [r.page, r.id]);
  const go = (route: Partial<Route>) => {
    closeNav();
    void e.go(route);
  };
  const site = host.siteUrl ? trimEnd(host.siteUrl) + '/wp-admin/admin.php?page=flx-tasks' : '';
  const webMenu = (anchor: HTMLElement, hash: string) =>
    c.ui.menu(anchor, [{ label: tt("Open in web panel (settings, members)"), icon: 'settings', onClick: () => host.openUrl(site + hash) }]);
  const listRow = (l: FlxgList) => (
    <div key={l.id} className={cls('flxg-tree-row', 'is-list', r.page === 'list' && r.id === l.id && 'is-active')}>
      <a className="flxg-tree-link" href={'#/list/' + l.id} onClick={(ev) => (ev.preventDefault(), go({ page: 'list', id: l.id }))}>
        {l.kind === 'meeting' ? <Ic n="meeting" s={14} /> : <span className="flxg-dot" style={{ background: l.color }} />}
        <span className="flxg-tree-name">{l.name}</span>
        {l.access === 'members' && <Ic n="lock" s={12} cls="flxg-lock" />}
      </a>
      {rank(l.role) >= 4 && site && (
        <button type="button" className="flxg-icon-btn is-sm flxg-tree-more" aria-label={tt("List menu")} onClick={(ev) => webMenu(ev.currentTarget, '#/list/' + l.id)}>
          <Ic n="dots" s={15} />
        </button>
      )}
    </div>
  );
  const unread = S.unread;
  return (
    <aside ref={sideRef} className="flxg-side">
      <div className="flxg-brand">
        <span className="flxg-brand-mark">
          <Ic n="check" s={16} />
        </span>
        <div className="flxg-brand-text">
          <b>{tt("Tasks")}</b>
          <span>{host.siteName ?? ''}</span>
        </div>
        <button type="button" className="flxg-icon-btn flxg-only-mobile" aria-label={tt("Close")} onClick={closeNav}>
          <Ic n="x" s={18} />
        </button>
        <button type="button" className="flxg-icon-btn is-sm flxg-side-hide" title={tt("Hide sidebar")} aria-label={tt("Hide sidebar")} onClick={hide}>
          <Ic n="sidebar" s={17} />
        </button>
      </div>
      <nav className="flxg-nav">
        <a className={cls('flxg-nav-item', r.page === 'home' && 'is-active')} href="#/home" onClick={(ev) => (ev.preventDefault(), go({ page: 'home' }))}>
          <Ic n="home" s={17} />
          <span className="flxg-nav-label">{tt("My tasks")}</span>
        </a>
        <a className={cls('flxg-nav-item', r.page === 'inbox' && 'is-active')} href="#/inbox" onClick={(ev) => (ev.preventDefault(), go({ page: 'inbox' }))}>
          <Ic n="inbox" s={17} />
          <span className="flxg-nav-label">{tt("Notifications")}</span>
          {unread > 0 && <span className="flxg-count">{unread > 99 ? '99+' : unread}</span>}
        </a>
      </nav>
      <div className="flxg-tree">
        <div className="flxg-tree-head">
          <span>{tt("Projects")}</span>
        </div>
        {S.spaces.map((sp) => {
          const isCol = !spOpen[sp.id];
          const folders = S.folders.filter((f) => f.space_id === sp.id);
          return (
            <div key={sp.id} className={cls('flxg-space', isCol && 'is-collapsed')} data-space={sp.id}>
              <div className={cls('flxg-tree-row', 'is-space', r.page === 'space' && r.id === sp.id && 'is-active')}>
                <button type="button" className="flxg-caret" aria-label={tt("Collapse")} onClick={() => setSpOpen(sp.id, isCol)}>
                  <Ic n={isCol ? 'chevR' : 'chevD'} s={14} />
                </button>
                <a className="flxg-tree-link" href={'#/space/' + sp.id} onClick={(ev) => (ev.preventDefault(), go({ page: 'space', id: sp.id }))}>
                  <span className="flxg-space-ic" style={{ background: sp.color }}>
                    {sp.name.slice(0, 1).toLocaleUpperCase('tr')}
                  </span>
                  <span className="flxg-tree-name">{sp.name}</span>
                  {sp.access === 'members' && <Ic n="lock" s={12} cls="flxg-lock" />}
                </a>
                {rank(sp.role) >= 4 && site && (
                  <button type="button" className="flxg-icon-btn is-sm flxg-tree-more" aria-label={tt("Project menu")} onClick={(ev) => webMenu(ev.currentTarget, '#/space/' + sp.id)}>
                    <Ic n="dots" s={15} />
                  </button>
                )}
              </div>
              {!isCol && (
                <div className="flxg-tree-kids">
                  {folders.map((f) => {
                    const fk = 'f' + f.id;
                    const fCol = !!col[fk];
                    const lists = S.lists.filter((l) => l.folder_id === f.id);
                    return (
                      <div key={f.id} className="flxg-folder">
                        <div className="flxg-tree-row is-folder">
                          <button type="button" className="flxg-caret" onClick={() => setColl(fk, !fCol)}>
                            <Ic n={fCol ? 'chevR' : 'chevD'} s={14} />
                          </button>
                          <span className="flxg-tree-link" onClick={() => setColl(fk, !fCol)}>
                            <Ic n="folder" s={15} />
                            <span className="flxg-tree-name">{f.name}</span>
                          </span>
                        </div>
                        {!fCol && <div className="flxg-tree-kids">{lists.length ? lists.map(listRow) : <div className="flxg-tree-empty">{tt("Empty folder")}</div>}</div>}
                      </div>
                    );
                  })}
                  {S.lists.filter((l) => l.space_id === sp.id && (!l.folder_id || !e.folder(l.folder_id))).map(listRow)}
                </div>
              )}
            </div>
          );
        })}
        {!S.spaces.length && <div className="flxg-tree-empty">{tt("You have no visible projects.")}</div>}
      </div>
      <div className="flxg-side-foot">
        <span className="flxg-me">
          <Avatar u={S.me} size={28} />
          <span>{S.me?.name}</span>
        </span>
      </div>
      <div
        className="flxg-side-resize"
        title={tt("Drag to resize, double-click to reset")}
        onPointerDown={(ev) => {
          if (ev.button !== 0) return;
          ev.preventDefault();
          const sx = ev.clientX;
          const sw = sideRef.current!.getBoundingClientRect().width;
          let raw = sw;
          let w = sw;
          document.body.classList.add('flxg-col-resizing');
          setResizing(true);
          const mv = (m: PointerEvent) => {
            raw = sw + m.clientX - sx;
            w = Math.round(Math.max(200, Math.min(480, raw)));
            setW(w);
          };
          const up = () => {
            document.removeEventListener('pointermove', mv);
            document.removeEventListener('pointerup', up);
            document.body.classList.remove('flxg-col-resizing');
            setResizing(false);
            // Çok daraltılırsa tamamen gizle.
            if (raw < 130) {
              setW(Math.round(sw));
              collapse();
              return;
            }
            store.set('side-w', String(w));
          };
          document.addEventListener('pointermove', mv);
          document.addEventListener('pointerup', up);
        }}
        onDoubleClick={() => (setW(0), store.set('side-w', null))}
      />
    </aside>
  );
}

/* ---------------- Üst şerit ---------------- */
function TopBar({ title, extra, toggleSide, mobile }: { title: ReactNode; extra?: ReactNode; toggleSide: () => void; mobile: boolean }) {
  const { e } = useYtg();
  const unread = e.S.unread;
  return (
    <header className="flxg-top">
      <button type="button" className="flxg-icon-btn flxg-menu-btn" title={tt("Show sidebar")} aria-label={tt("Menu")} onClick={toggleSide}>
        <Ic n={mobile ? 'menu' : 'sidebar'} s={20} />
      </button>
      <div className="flxg-top-title">{title}</div>
      <div className="flxg-top-actions">
        {extra}
        <a className="flxg-icon-btn flxg-only-mobile flxg-bell" href="#/inbox" aria-label={tt("Notifications")} onClick={(ev) => (ev.preventDefault(), void e.go({ page: 'inbox' }))}>
          <Ic n="bell" s={19} />
          {unread > 0 && <span className="flxg-dotbadge">{unread > 9 ? '9+' : unread}</span>}
        </a>
      </div>
    </header>
  );
}

function ScopeTitle() {
  const { e } = useYtg();
  const sc = e.S.scope;
  if (!sc || sc.type === 'home')
    return (
      <div className="flxg-crumbs">
        <h1>{tt("My tasks")}</h1>
      </div>
    );
  if (sc.type === 'space') {
    const sp = e.space(sc.id);
    return (
      <div className="flxg-crumbs">
        <span className="flxg-space-ic" style={{ background: sp?.color }}>
          {sp?.name.slice(0, 1).toLocaleUpperCase('tr')}
        </span>
        <h1>{sp?.name}</h1>
        <span className="flxg-muted flxg-hide-sm">{tt("All lists")}</span>
      </div>
    );
  }
  const l = e.list(sc.id);
  const s2 = l ? e.space(l.space_id) : null;
  const f = l?.folder_id ? e.folder(l.folder_id) : null;
  return (
    <div className="flxg-crumbs">
      {s2 && (
        <a className="flxg-crumb flxg-hide-sm" href={'#/space/' + s2.id} onClick={(ev) => (ev.preventDefault(), void e.go({ page: 'space', id: s2.id }))}>
          {s2.name}
        </a>
      )}
      {s2 && <span className="flxg-crumb-sep flxg-hide-sm">/</span>}
      {f && <span className="flxg-crumb flxg-hide-sm">{f.name}</span>}
      {f && <span className="flxg-crumb-sep flxg-hide-sm">/</span>}
      <span className="flxg-dot is-lg" style={{ background: l?.color }} />
      <h1>{l?.name}</h1>
      {l?.access === 'members' && (
        <span className="flxg-pill-sm" title={tt("Visible to members only")}>
          <Ic n="lock" s={12} />
          {tt("Private")}
        </span>
      )}
    </div>
  );
}

/* ---------------- Liste / proje / benim görevlerim sayfası ---------------- */
const viewIcon = (type: string) => (type === 'board' ? 'board' : type === 'calendar' ? 'calendar' : 'list');
function ScopePage({ toggleSide, mobile }: { toggleSide: () => void; mobile: boolean }) {
  const c = useYtg();
  const { e, ui } = c;
  const S = e.S;
  const host = useHost();
  const sc = S.scope;
  const v = S.view;
  const scopeId = () => (sc!.type === 'home' ? S.me!.id : sc!.id);
  const editableView = (x: FlxgView) => (x.user_id ? x.user_id === S.me?.id : rank(sc?.role) >= 3);
  const viewMenu = (anchor: HTMLElement, x: FlxgView) => {
    const editable = editableView(x);
    ui.menu(anchor, [
      editable && {
        label: tt("Rename"),
        icon: 'edit',
        onClick: () =>
          void ui.prompt(tt("View name"), x.name).then((n) => {
            if (n)
              e.api.updateView(x.id, { name: n }).then((r) => {
                x.name = r.view.name;
                e.bump();
              }, ui.fail);
          }),
      },
      {
        label: tt("Create a copy"),
        icon: 'copy',
        onClick: () =>
          e.api.createView({ scope: sc!.type, scope_id: scopeId(), name: x.name + ' 2', type: x.type, config: S.draft ?? x.config, shared: rank(sc!.role) >= 3 && sc!.type !== 'home' }).then((r) => {
            S.views = r.views;
            void e.go({ ...S.route, view: r.view.id });
          }, ui.fail),
      },
      editable &&
        sc!.type !== 'home' &&
        rank(sc!.role) >= 3 && {
          label: x.user_id ? tt("Share with everyone") : tt("Make private to me"),
          icon: x.user_id ? 'users' : 'lock',
          onClick: () =>
            e.api.updateView(x.id, { shared: !!x.user_id }).then((r) => {
              x.user_id = r.view.user_id;
              e.bump();
            }, ui.fail),
        },
      editable && S.views.length > 1 && { sep: true },
      editable &&
        S.views.length > 1 && {
          label: tt("Delete view"),
          icon: 'trash',
          danger: true,
          onClick: () =>
            void ui.confirm(tt("Delete view"), tt("The view \"{n}\" will be deleted. Tasks are not affected.", { n: x.name }), tt("Delete"), true).then((ok) => {
              if (ok)
                e.api.deleteView(x.id).then((r) => {
                  S.views = r.views;
                  void e.go({ ...S.route, view: 0 });
                }, ui.fail);
            }),
        },
    ]);
  };
  const addView = (anchor: HTMLElement) => {
    const mk = (type: string, name: string) =>
      e.api
        .createView({ scope: sc!.type, scope_id: scopeId(), name, type, shared: sc!.type !== 'home' && rank(sc!.role) >= 3, config: { fields: ['assignees', 'due', 'priority', 'tags'], cover: type !== 'list', group: type === 'calendar' ? 'none' : 'status' } })
        .then((r) => {
          S.views = r.views;
          void e.go({ ...S.route, view: r.view.id });
        }, ui.fail);
    ui.menu(anchor, [
      { head: tt("New view") },
      { label: tt("List"), icon: 'list', onClick: () => void mk('list', tt("List")) },
      { label: tt("Board"), icon: 'board', onClick: () => void mk('board', tt("Board")) },
      { label: tt("Calendar"), icon: 'calendar', onClick: () => void mk('calendar', tt("Calendar")) },
    ]);
  };
  const site = host.siteUrl ? trimEnd(host.siteUrl) + '/wp-admin/admin.php?page=flx-tasks' : '';
  const extra =
    sc?.type === 'list' && rank(sc.role) >= 4 && site ? (
      <button type="button" className="flxg-icon-btn" title={tt("List settings")} onClick={() => host.openUrl(site + '#/list/' + sc.id)}>
        <Ic n="settings" s={18} />
      </button>
    ) : null;

  let content: ReactNode;
  if (S.loading || !sc) content = <Skeleton />;
  else if (!v) content = <EmptyState />;
  else if (v.type === 'board') content = <BoardView />;
  else if (v.type === 'calendar') content = <CalendarView onQuick={(x) => quickCreate(c, x)} />;
  else content = <ListView />;

  return (
    <>
      <TopBar title={<ScopeTitle />} extra={extra} toggleSide={toggleSide} mobile={mobile} />
      <nav className="flxg-viewbar">
        <div className="flxg-tabs">
          {S.views.map((x) => {
            const on = v?.id === x.id;
            return (
              <a key={x.id} className={cls('flxg-tab', on && 'is-on')} href="#" onClick={(ev) => (ev.preventDefault(), void e.go({ ...S.route, view: x.id }))}>
                <Ic n={viewIcon(x.type)} s={15} />
                <span>{x.name}</span>
                {x.user_id > 0 && <Ic n="lock" s={11} cls="flxg-lock" />}
                {on && (
                  <button type="button" className="flxg-tab-more" aria-label={tt("View menu")} onClick={(ev) => (ev.preventDefault(), ev.stopPropagation(), viewMenu(ev.currentTarget, x))}>
                    <Ic n="chevD" s={13} />
                  </button>
                )}
              </a>
            );
          })}
          {sc && (
            <button type="button" className="flxg-tab is-add" onClick={(ev) => addView(ev.currentTarget)}>
              <Ic n="plus" s={15} />
              <span>{tt("View")}</span>
            </button>
          )}
        </div>
      </nav>
      {sc && !S.loading && <Toolbar onQuick={() => quickCreate(c)} />}
      <div className={'flxg-content is-' + (v ? v.type : 'list')}>{content}</div>
      <BulkBar />
    </>
  );
}

/* ---------------- Bildirimler ---------------- */
function notifText(e: FlxgEngine, n: FlxgNotif) {
  const who = n.actor ? (e.S.users[n.actor]?.name ?? tt("Someone")) : '';
  switch (n.type) {
    case 'assigned': return tt("{n} assigned you to a task", { n: who });
    case 'mention': return tt("{n} mentioned you in a comment", { n: who });
    case 'comment': return tt("{n} wrote a comment", { n: who });
    case 'status': return tt("{n} changed the status to \"{s}\"", { n: who, s: n.data?.label ?? '' });
    case 'due': return tt("Due tomorrow");
    case 'overdue': return tt("Overdue");
  }
  return n.type;
}
const notifIcon: Record<string, string> = { assigned: 'user', mention: 'at', comment: 'msg', status: 'check', due: 'clock', overdue: 'clock' };
function Inbox({ toggleSide, mobile }: { toggleSide: () => void; mobile: boolean }) {
  const c = useYtg();
  const { e, ui } = c;
  const S = e.S;
  const host = useHost();
  const [filter, setFilter] = useState(() => store.get('inbox-filter') || 'all');
  const [items, setItems] = useState<FlxgNotif[]>([]);
  const [more, setMore] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const fetchMore = (reset: boolean, cur: FlxgNotif[]) => {
    const before = !reset && cur.length ? cur[cur.length - 1]!.id : undefined;
    return e.api.notifications({ before, unread: filter === 'unread' ? 1 : undefined }).then((r) => {
      const next = reset ? r.items : [...cur, ...r.items];
      setItems(next);
      setMore(r.items.length >= 30);
      setLoaded(true);
      if (next.length) S.lastNotif = Math.max(S.lastNotif, next[0]!.id);
      S.unread = r.unread;
      e.bump();
    }, ui.fail);
  };
  useEffect(() => {
    void fetchMore(true, []);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter]);
  const td = dayKey(new Date());
  let lastDay = '';
  const rows: ReactNode[] = [];
  for (const n of items) {
    const d = new Date(n.created);
    const dk = dayKey(d);
    if (dk !== lastDay) {
      lastDay = dk;
      rows.push(
        <div key={'d' + dk} className="flxg-inbox-day">
          {dk === td ? tt("Today") : dk === addDays(td, -1) ? tt("Yesterday") : d.toLocaleDateString(flxgLocale(), { dateStyle: 'medium' })}
        </div>,
      );
    }
    const l = e.list(n.list);
    rows.push(
      <button
        key={n.id}
        type="button"
        className={cls('flxg-notif', !n.read && 'is-unread')}
        onClick={() => {
          if (!n.read) {
            n.read = true;
            e.api.read({ ids: [n.id] }).then((r) => {
              S.unread = r.unread;
              e.bump();
              host.refreshCounts?.();
            }, () => {});
            setItems([...items]);
          }
          e.openTask(n.task);
        }}
      >
        {n.actor ? (
          <Avatar u={S.users[n.actor]} size={34} />
        ) : (
          <span className="flxg-notif-ic">
            <Ic n={notifIcon[n.type] ?? 'bell'} s={18} />
          </span>
        )}
        <div className="flxg-notif-main">
          <div className="flxg-notif-line">
            <span className="flxg-notif-type">
              <Ic n={notifIcon[n.type] ?? 'bell'} s={13} />
            </span>
            <span>{notifText(e, n)}</span>
          </div>
          <div className="flxg-notif-title">{n.title || tt("(deleted task)")}</div>
          {n.data?.excerpt && <div className="flxg-notif-ex">{n.data.excerpt}</div>}
          <div className="flxg-notif-meta">
            {l && (
              <span>
                <span className="flxg-dot" style={{ background: l.color }} />
                {l.name}
              </span>
            )}
            <span title={fullTime(n.created)}>{ago(n.created)}</span>
          </div>
        </div>
        {!n.read && <span className="flxg-unread-dot" />}
      </button>,
    );
  }
  return (
    <>
      <TopBar
        title={
          <div className="flxg-crumbs">
            <h1>{tt("Notifications")}</h1>
          </div>
        }
        toggleSide={toggleSide}
        mobile={mobile}
        extra={
          <>
            <div className="flxg-seg">
              {(
                [
                  ['all', tt("All")],
                  ['unread', tt("Unread")],
                ] as const
              ).map(([k, label]) => (
                <button key={k} type="button" className={filter === k ? 'is-on' : ''} onClick={() => (store.set('inbox-filter', k), setFilter(k))}>
                  {label}
                </button>
              ))}
            </div>
            <button
              type="button"
              className="flxg-btn is-sm"
              onClick={() =>
                e.api.read({ all: true }).then((r) => {
                  S.unread = r.unread;
                  setItems(items.map((x) => ({ ...x, read: true })));
                  e.bump();
                  host.refreshCounts?.();
                }, ui.fail)
              }
            >
              <Ic n="check" s={14} />
              <span className="flxg-hide-sm">{tt("Mark all as read")}</span>
            </button>
          </>
        }
      />
      <div className="flxg-content is-page">
        <div className="flxg-page-inner">
          <div className="flxg-inbox-list">
            {loaded && !items.length ? (
              <div className="flxg-empty">
                <div className="flxg-empty-ic">
                  <Ic n="bell" s={34} />
                </div>
                <p>{filter === 'unread' ? tt("No unread notifications. 🎉") : tt("No notifications yet.")}</p>
              </div>
            ) : (
              rows
            )}
            {more && (
              <button type="button" className="flxg-btn flxg-more" onClick={() => void fetchMore(false, items)}>
                {tt("More")}
              </button>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
