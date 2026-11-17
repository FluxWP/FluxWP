import { create } from 'zustand';
import type { Route } from '../state/route';

interface WarmState {
  /** Screens mounted in the background right now so they load their data before the first click. */
  warming: Route[];
  /** Bumped when the site's plugins or preset changed: kept screens remount. */
  epoch: number;
  setWarming(r: Route[]): void;
  bump(): void;
}

export const useWarm = create<WarmState>((set) => ({
  warming: [],
  epoch: 0,
  setWarming: (warming) => set({ warming }),
  bump: () => set((s) => ({ epoch: s.epoch + 1 })),
}));
