// Page area with keep-alive. Native and manifest screens stay mounted (hidden with <Activity>) after the first
// visit, so going back to one is instant: its state and data are still there, and its effects (polling) pause
// while it is hidden. The site warm-up mounts screens here ahead of the first click.
import { Activity, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ManifestScreen } from '@fluxwp/render-web';
import { screens } from '../modules/registry';
import { Dashboard } from '../screens/Dashboard';
import { Notifications } from '../screens/Notifications';
import { Settings } from '../screens/Settings';
import { WebPanel } from '../screens/WebPanel';
import { useRoute, type Route } from '../state/route';
import type { SideGroup } from './links';
import { useWarm } from './warm';

/** Kept screens at most (least recently used one is dropped). */
const KEEP = 24;

export const routeKey = (r: Route) => JSON.stringify(r);

/** Can this route stay mounted in the background? */
export function keepable(r: Route): boolean {
  if (r.kind === 'manifest') return true;
  if (r.kind === 'native') return r.component === 'dashboard' || !!screens[r.component];
  return false;
}

export function Stage({ route, groups }: { route: Route; groups: SideGroup[] }) {
  const current = routeKey(route);
  const keep = keepable(route);
  const warming = useWarm((s) => s.warming);
  const epoch = useWarm((s) => s.epoch);
  const [slots, setSlots] = useState<{ key: string; route: Route }[]>([]);
  const used = useRef(new Map<string, number>());

  useEffect(() => {
    const want = [...(keep ? [{ key: current, route }] : []), ...warming.map((r) => ({ key: routeKey(r), route: r }))];
    // Visited screens rank by last visit; warmed but never opened ones go first when the list is full.
    for (const w of want) used.current.set(w.key, w.key === current ? Date.now() : (used.current.get(w.key) ?? 0));
    setSlots((prev) => {
      const map = new Map(prev.map((s) => [s.key, s]));
      let added = false;
      for (const w of want) if (!map.has(w.key)) (map.set(w.key, w), (added = true));
      if (!added && map.size <= KEEP) return prev;
      const busy = new Set(want.map((w) => w.key));
      const list = [...map.values()];
      if (list.length > KEEP) {
        const drop = list
          .filter((s) => !busy.has(s.key))
          .sort((a, b) => (used.current.get(a.key) ?? 0) - (used.current.get(b.key) ?? 0))
          .slice(0, list.length - KEEP)
          .map((s) => s.key);
        drop.forEach((k) => used.current.delete(k));
        return list.filter((s) => !drop.includes(s.key));
      }
      return list;
    });
  }, [current, keep, route, warming]);

  // A plugin or preset changed on the site: remount kept screens so their code paths start fresh.
  const firstEpoch = useRef(epoch);
  useEffect(() => {
    if (epoch === firstEpoch.current) return;
    firstEpoch.current = epoch;
    setSlots((prev) => prev.filter((s) => s.key === current));
  }, [epoch, current]);

  const warmKeys = useMemo(() => new Set(warming.map(routeKey)), [warming]);

  return (
    <>
      {slots.map((s) => {
        const shown = s.key === current;
        return (
          <div key={s.key} className="stage-slot" style={shown ? undefined : { display: 'none' }} data-route={s.key}>
            <Activity mode={shown || warmKeys.has(s.key) ? 'visible' : 'hidden'}>
              <Screen route={s.route} groups={groups} />
            </Activity>
          </div>
        );
      })}
      {!keep && <Screen route={route} groups={groups} />}
    </>
  );
}

function Screen({ route, groups }: { route: Route; groups: SideGroup[] }) {
  if (route.kind === 'shell') {
    if (route.name === 'settings' || route.name === 'accounts') return <Settings />;
    if (route.name === 'notifications') return <Notifications />;
    if (route.name === 'more') return <More groups={groups} />;
    if (route.name === 'search') return null;
  }
  if (route.kind === 'native') {
    const id = route.component === 'dashboard' ? 'core-dashboard' : route.component;
    const S = screens[id];
    if (S) return <S />;
  }
  if (route.kind === 'manifest') return <ManifestScreen module={route.module} screen={route.screen} params={route.params} />;
  if (route.kind === 'web') return <WebPanel path={route.path} title={route.title ?? route.path} />;
  return <Dashboard />;
}

function More({ groups }: { groups: SideGroup[] }) {
  const { t } = useTranslation();
  const go = useRoute((s) => s.go);
  return (
    <div className="page">
      <header className="page-head"><h1>{t('nav.menu')}</h1></header>
      {groups.map((g) => (
        <section key={g.id} className="panel">
          {g.label && <h2>{g.label}</h2>}
          {g.items.map((item) => (
            <button key={item.id} className="btn-ghost" onClick={() => go(item.route)}>{item.title}</button>
          ))}
        </section>
      ))}
    </div>
  );
}
