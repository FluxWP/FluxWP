import { create } from 'zustand';

export type Route = { kind: 'nav'; id: string; sub?: string } | { kind: 'settings' } | { kind: 'add-account' };

interface RouteState {
  route: Route;
  /** Geri/ileri yığınları (tarayıcı gibi). */
  past: Route[];
  future: Route[];
  go(r: Route): void;
  back(): void;
  forward(): void;
}

const same = (a: Route, b: Route) => JSON.stringify(a) === JSON.stringify(b);
const LIMIT = 50;

export const useRoute = create<RouteState>((set, get) => ({
  route: { kind: 'nav', id: 'dashboard' },
  past: [],
  future: [],
  go: (route) => {
    const s = get();
    if (same(s.route, route)) return;
    set({ route, past: [...s.past, s.route].slice(-LIMIT), future: [] });
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
