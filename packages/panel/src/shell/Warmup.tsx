// Site warm-up: on entering a site (app start or switch) the loading screen stays up while the menu's
// screens load their code and first data, so later clicks open from memory. The rest of the menu warms
// in the background after the loading screen is gone. Kept screens live in <Stage>.
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { platform, type Account } from '@fluxwp/core';
import { preloadScreen } from '@fluxwp/modules';
import { hideSplash, showSplash, updateSplash } from '../boot/splash';
import { hasAccountCache } from '../boot/cacheStore';
import { waitForIdle } from '../state/net';
import { useRoute, type Route } from '../state/route';
import { keepable, routeKey } from './Stage';
import { useWarm } from './warm';
import type { SideGroup, SideLink } from './links';

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
const visible = () => {
  try {
    return platform().visibility?.visible() ?? document.visibilityState !== 'hidden';
  } catch {
    return true;
  }
};
async function whenVisible(alive: () => boolean) {
  while (alive() && !visible()) await sleep(1000);
}

function targetsOf(pins: SideLink[], groups: SideGroup[]): { front: Route[]; rest: Route[] } {
  const seen = new Set<string>();
  const pick = (links: SideLink[]) => {
    const out: Route[] = [];
    for (const l of links) {
      const k = routeKey(l.route);
      if (!keepable(l.route) || seen.has(k)) continue;
      seen.add(k);
      out.push(l.route);
    }
    return out;
  };
  seen.add(routeKey(useRoute.getState().route));
  return { front: pick(pins), rest: pick(groups.flatMap((g) => g.items)) };
}

export function Warmup({ account, ready, failed, logo, pins, groups }: {
  account: Account;
  /** /me settled (from cache or network), the menu is known. */
  ready: boolean;
  /** /me failed (needs login, server down): drop the loading screen, nothing to warm. */
  failed: boolean;
  logo: string;
  pins: SideLink[];
  groups: SideGroup[];
}) {
  const { t } = useTranslation();
  const epoch = useWarm((s) => s.epoch);
  const menu = useRef({ pins, groups });
  menu.current = { pins, groups };
  const alive = useRef(true);
  const ran = useRef(-1);

  useEffect(() => {
    alive.current = true;
    showSplash({ logo, title: account.siteName, status: t('boot.site'), progress: undefined });
    return () => {
      alive.current = false;
      useWarm.getState().setWarming([]);
    };
  }, [account.id]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => void (logo && updateSplash({ logo })), [logo]);

  useEffect(() => {
    if (failed) hideSplash();
  }, [failed]);

  useEffect(() => {
    if (!ready || failed || ran.current === epoch) return;
    const first = ran.current === -1;
    ran.current = epoch;
    const isAlive = () => alive.current;
    const warm = async (batch: Route[], capMs: number) => {
      if (!batch.length || !isAlive()) return;
      useWarm.getState().setWarming(batch);
      await waitForIdle({ capMs, quietMs: 400, minMs: 300 });
      if (isAlive()) useWarm.getState().setWarming([]);
    };

    void (async () => {
      const cached = first ? await hasAccountCache(account.id).catch(() => false) : true;
      // A warm cache only needs a short wait; a new site gets the time it needs, within limits.
      const deadline = Date.now() + (cached ? 4000 : 12000);
      // Contract first (me, config, manifest, prefs): the menu is final once the network goes quiet.
      if (first) await waitForIdle({ capMs: Math.max(0, deadline - Date.now()), quietMs: 300, minMs: 200 });
      if (!isAlive()) return;
      const { front, rest } = targetsOf(menu.current.pins, menu.current.groups);
      // Screen code: every chunk the menu can open, so no click waits on a lazy import.
      const ids = [...front, ...rest].flatMap((r) => (r.kind === 'native' ? [r.component] : []));
      await Promise.race([Promise.all(ids.map((id) => preloadScreen(id))), sleep(3000)]);

      if (first) {
        for (let i = 0; i < front.length && isAlive() && Date.now() < deadline; i += 2) {
          updateSplash({ status: t('boot.screens', { n: Math.min(i + 2, front.length), total: front.length }), progress: (i + 1) / (front.length + 1) });
          await warm(front.slice(i, i + 2), Math.min(cached ? 2500 : 6000, Math.max(500, deadline - Date.now())));
        }
        if (!isAlive()) return;
        updateSplash({ status: t('boot.ready'), progress: 1 });
        hideSplash();
      }

      // Background: the rest of the menu, one at a time and only while the window is in front.
      const queue = first ? rest : [...front, ...rest];
      for (const r of queue) {
        await whenVisible(isAlive);
        if (!isAlive() || ran.current !== epoch) return;
        await warm([r], 6000);
        await sleep(150);
      }
    })();
  }, [ready, failed, epoch, account.id, t]);

  return null;
}
