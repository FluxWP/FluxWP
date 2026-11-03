import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, ChevronRight, Eye, EyeOff, GripVertical, RotateCcw, Search } from 'lucide-react';
import { brand, layoutStore, moveItem, type Account, type NavItem } from '@fluxwp/core';
import { MenuIcon } from '@fluxwp/ui';
import { buildMenu } from '../lib/menuGroups';
import { useMe } from '../state/hooks';
import { AccountCard } from './AccountCard';
import { BrandLogo } from './BrandLogo';
import { TitleBar } from './TitleBar';
import { UpdateBanner } from './UpdateBanner';
import { useRoute, type Route } from '../state/route';
import { useSidebar } from '../state/sidebar';

// Kısayol tuşu adı: macOS'ta ⌘, Windows'ta Ctrl.
const MOD_KEY = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/i.test(navigator.userAgent) ? '⌘' : 'Ctrl ';

export function Sidebar({ account, nav, activeId, onSearch, editing = false, onEditDone }: {
  account: Account;
  nav: NavItem[];
  activeId: string | null;
  onSearch: () => void;
  /** Menü düzenleme (sırala/gizle). Arayüzde düğmesi şimdilik yok; altyapı duruyor. */
  editing?: boolean;
  onEditDone?: () => void;
}) {
  const { t } = useTranslation();
  const { route, go } = useRoute();
  const { width, collapsed, open, closedGroups, setWidth, toggleOpen, toggleGroup } = useSidebar();
  const layoutData = useMe(account).data?.layout;
  const menu = useMemo(() => buildMenu(nav, layoutData), [nav, layoutData]);
  const [drag, setDrag] = useState<number | null>(null);
  const [resizing, setResizing] = useState(false);
  const layout = layoutStore.getState();
  const edit = editing;
  const items = edit ? nav : nav.filter((n) => !n.hidden);

  // Etkin öğenin alt menüsü kendiliğinden açılır.
  useEffect(() => {
    const a = nav.find((n) => n.id === activeId);
    if (a && a.children.length > 1) toggleOpen(a.id, true);
  }, [activeId]); // eslint-disable-line react-hooks/exhaustive-deps

  const drop = (to: number) => {
    if (drag === null || drag === to) return;
    void layout.set(account.id, { order: moveItem(nav.map((n) => n.id), drag, to) });
    setDrag(null);
  };
  const toggleHidden = (id: string) => {
    const hidden = new Set(nav.filter((n) => n.hidden).map((n) => n.id));
    hidden.has(id) ? hidden.delete(id) : hidden.add(id);
    void layout.set(account.id, { hidden: [...hidden] });
  };

  const startX = useRef(0);
  const startW = useRef(0);
  const onResizeDown = (e: React.PointerEvent) => {
    e.preventDefault();
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
    startX.current = e.clientX;
    startW.current = width;
    setResizing(true);
    document.documentElement.classList.add('flux-resizing');
  };
  const onResizeMove = (e: React.PointerEvent) => resizing && setWidth(startW.current + e.clientX - startX.current);
  const onResizeUp = () => (setResizing(false), document.documentElement.classList.remove('flux-resizing'));

  return (
    <aside className={'sidebar' + (resizing ? ' resizing' : '') + (collapsed ? ' collapsed' : '')} style={{ width: collapsed ? 0 : width }} aria-hidden={collapsed}>
      <div className="sidebar-in" style={{ width }}>
      <div className="sidebar-top" data-tauri-drag-region>
        <TitleBar />
        <div className="brand-logo" data-tauri-drag-region>
          <BrandLogo />
        </div>
        <button className="search-btn" onClick={onSearch}>
          <Search size={15} />
          <span className="grow">{t('nav.search')}</span>
          <kbd>{MOD_KEY}K</kbd>
        </button>
      </div>
      <nav className="nav" aria-label={t('nav.menu')}>
        {edit ? (
          items.map((n, i) => (
            <div
              key={n.id}
              className={'nav-row' + (n.hidden ? ' is-hidden' : '') + (drag === i ? ' is-drag' : '')}
              draggable
              onDragStart={() => setDrag(i)}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => drop(i)}
              onDragEnd={() => setDrag(null)}
            >
              <span className="nav-item">
                <GripVertical size={14} className="muted grip" />
                <MenuIcon slug={n.slug} icon={n.icon} iconUrl={n.iconUrl} size={22} />
                <span className="grow ellipsis-1">{n.title}</span>
              </span>
              <button className="icon-btn" onClick={() => toggleHidden(n.id)} aria-label={n.hidden ? t('nav.show') : t('nav.hide')}>
                {n.hidden ? <EyeOff size={15} /> : <Eye size={15} />}
              </button>
            </div>
          ))
        ) : (
          <>
            {menu.pins.length > 0 && <Pins pins={menu.pins} activeId={activeId} sub={route.kind === 'nav' ? route.sub : undefined} go={go} />}
            {menu.groups.map((g) => {
              // Sunucuda kapalı gelen grup ilk tıklamada açılır (kişinin seçimi tersine çevirir).
              const shut = g.collapsed ? !closedGroups.includes(g.id) : closedGroups.includes(g.id);
              return (
                <section key={g.id} className={'nav-group' + (shut ? ' shut' : '')}>
                  <button className="nav-group-head" onClick={() => toggleGroup(g.id)} aria-expanded={!shut}>
                    <span className="grow">{g.label || t(`nav.groups.${g.id}`, { defaultValue: g.id })}</span>
                    <ChevronDown size={15} />
                  </button>
                  <div className="nav-group-body">
                    <div className="nav-group-inner">
                      {g.items.map((n) => {
                        const active = n.id === activeId;
                        const hasSub = n.children.length > 1;
                        const isOpen = hasSub && open.includes(n.id);
                        return (
                          <div key={n.id} className="nav-row">
                            <button
                              className={'nav-item' + (active ? ' active' : '')}
                              tabIndex={shut ? -1 : 0}
                              onClick={() => {
                                go({ kind: 'nav', id: n.id });
                                if (hasSub) toggleOpen(n.id, true);
                              }}
                              aria-current={active ? 'page' : undefined}
                            >
                              <MenuIcon slug={n.slug} icon={n.icon} iconUrl={n.iconUrl} size={28} />
                              <span className="grow ellipsis-1">{n.title}</span>
                              {n.badge ? <span className="badge">{n.badge}</span> : null}
                            </button>
                            {hasSub && (
                              <button className={'icon-btn nav-caret' + (isOpen ? ' open' : '')} tabIndex={shut ? -1 : 0} onClick={() => toggleOpen(n.id)} aria-expanded={isOpen} aria-label={t('nav.submenu')}>
                                <ChevronRight size={16} />
                              </button>
                            )}
                            {hasSub && (
                              <div className={'nav-children' + (isOpen ? ' open' : '')} aria-hidden={!isOpen}>
                                <div className="nav-children-inner">
                                  {n.children.map((c) => (
                                    <button
                                      key={c.id}
                                      tabIndex={isOpen ? 0 : -1}
                                      className={'nav-child' + (active && route.kind === 'nav' && route.sub === c.id ? ' active' : '')}
                                      onClick={() => go({ kind: 'nav', id: n.id, sub: c.id })}
                                    >
                                      {c.title}
                                    </button>
                                  ))}
                                </div>
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </section>
              );
            })}
          </>
        )}
      </nav>
      <div className="sidebar-foot">
        {edit ? (
          <div className="row grow" style={{ justifyContent: 'space-between' }}>
            <button className="btn-ghost small" onClick={() => void layout.reset(account.id)}>
              <RotateCcw size={14} /> {t('nav.reset')}
            </button>
            <button className="btn-primary small" onClick={onEditDone}>
              {t('common.done')}
            </button>
          </div>
        ) : (
          <>
            <UpdateBanner />
            <AccountCard active={account} />
          </>
        )}
      </div>
      <div
        className="sidebar-resizer"
        role="separator"
        aria-orientation="vertical"
        aria-label={t('nav.resize')}
        onPointerDown={onResizeDown}
        onPointerMove={onResizeMove}
        onPointerUp={onResizeUp}
        onPointerCancel={onResizeUp}
        onDoubleClick={() => setWidth(260)}
      />
      </div>
    </aside>
  );
}

/**
 * Sabit kutucuklar (Flux Admin menüsü gibi): iki sütun; açık sayfanın alt menüsü varsa o kutucuk
 * tam genişliğe çıkıp en sona kayar ve alt menüsü aşağı doğru açılır. Başka kutucuğa geçince
 * eskisi yerine döner, yenisi aşağı kayar (FLIP animasyonu).
 */
function Pins({ pins, activeId, sub, go }: {
  pins: NavItem[];
  activeId: string | null;
  sub?: string;
  go: (r: Route) => void;
}) {
  const { t } = useTranslation();
  const wideId = pins.find((n) => n.id === activeId && n.children.length > 1)?.id ?? null;
  const ordered = wideId ? [...pins.filter((n) => n.id !== wideId), pins.find((n) => n.id === wideId)!] : pins;
  const box = useRef<HTMLDivElement>(null);
  const els = useRef(new Map<string, HTMLElement>());
  const rects = useRef<{ w: number; at: Map<string, { x: number; y: number }> } | null>(null);
  const prevWide = useRef<string | null>(wideId);

  useLayoutEffect(() => {
    const root = box.current;
    if (!root) return;
    const base = root.getBoundingClientRect();
    const at = new Map<string, { x: number; y: number }>();
    els.current.forEach((el, id) => {
      const r = el.getBoundingClientRect();
      at.set(id, { x: r.left - base.left, y: r.top - base.top });
    });
    const prev = rects.current;
    const changed = prevWide.current !== wideId;
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (changed && prev && Math.abs(prev.w - base.width) < 1 && !reduce) {
      els.current.forEach((el, id) => {
        const a = prev.at.get(id);
        const b = at.get(id);
        if (!a || !b) return;
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return;
        el.animate([{ transform: `translate(${dx}px,${dy}px)` }, { transform: 'none' }], { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' });
      });
      const subEl = wideId ? els.current.get(wideId)?.querySelector<HTMLElement>('.nav-pin-sub') : null;
      if (subEl) {
        const h = subEl.offsetHeight;
        subEl.animate([{ height: '0px', opacity: 0 }, { height: `${h}px`, opacity: 1 }], { duration: 320, delay: 60, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' });
      }
    }
    rects.current = { w: base.width, at };
    prevWide.current = wideId;
  }, [wideId, pins]);

  const ref = (id: string) => (el: HTMLElement | null) => {
    if (el) els.current.set(id, el);
    else els.current.delete(id);
  };

  return (
    <div className="nav-pins" ref={box}>
      {ordered.map((n) => {
        const active = n.id === activeId;
        if (n.id !== wideId) {
          return (
            <button key={n.id} ref={ref(n.id)} className={'nav-pin' + (active ? ' active' : '')} onClick={() => go({ kind: 'nav', id: n.id })} aria-current={active ? 'page' : undefined}>
              <MenuIcon slug={n.slug} icon={n.icon} iconUrl={n.iconUrl} size={34} />
              {n.badge ? <span className="badge">{n.badge}</span> : null}
              <span className="ellipsis-1">{n.title}</span>
            </button>
          );
        }
        const cur = sub && n.children.some((c) => c.id === sub) ? sub : n.children[0]?.id;
        return (
          <div key={n.id} ref={ref(n.id)} className="nav-pin wide active">
            <button className="nav-pin-head" onClick={() => go({ kind: 'nav', id: n.id })} aria-current="page">
              <MenuIcon slug={n.slug} icon={n.icon} iconUrl={n.iconUrl} size={34} />
              <span className="ellipsis-1">{n.title}</span>
              {n.badge ? <span className="badge">{n.badge}</span> : null}
            </button>
            <div className="nav-pin-sub" role="group" aria-label={t('nav.submenu')}>
              {n.children.map((c) => (
                <button key={c.id} className={'nav-child' + (c.id === cur ? ' active' : '')} onClick={() => go({ kind: 'nav', id: n.id, sub: c.id })} aria-current={c.id === cur ? 'page' : undefined}>
                  <span className="grow ellipsis-1">{c.title}</span>
                  {c.id === cur ? <ChevronRight size={15} /> : null}
                </button>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}
