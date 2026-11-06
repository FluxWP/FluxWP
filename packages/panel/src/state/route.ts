import { create } from 'zustand';
import type { Route as TargetRoute } from '@fluxwp/runtime';

/** Runtime route, plus the signed-out "add a site" screen. */
export type Route = TargetRoute | { kind: 'add-account'; siteUrl?: string };

export const homeRoute = (): Route => ({ kind: 'native', component: 'dashboard', params: {} });

interface RouteState {
  route: Route;
  past: Route[];
  future: Route[];
  go(r: Route): void;
  replace(r: Route): void;
  back(): void;
  forward(): void;
}

const same = (a: Route, b: Route) => JSON.stringify(a) === JSON.stringify(b);
const LIMIT = 50;

export const useRoute = create<RouteState>((set, get) => ({
  route: homeRoute(),
  past: [],
  future: [],
  go: (route) => {
    const s = get();
    if (same(s.route, route)) return;
    set({ route, past: [...s.past, s.route].slice(-LIMIT), future: [] });
  },
  replace: (route) => {
    if (same(get().route, route)) return;
    set({ route, future: [] });
  },
  back: () => {
    const { past, route, future } = get();
    const prev = past[past.length - 1];
    if (prev) set({ route: prev, past: past.slice(0, -1), future: [route, ...future] });
  },
  forward: () => {
    const { past, route, future } = get();
    const next = future[0];
    if (next) set({ route: next, past: [...past, route], future: future.slice(1) });
  },
}));
