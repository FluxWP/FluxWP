// Gömülü web görünümü HTML'in üstünde durur; açılır pencere varken gizlenmesi gerekir.
import { create } from 'zustand';

interface UiState {
  overlays: number;
  pushOverlay(): () => void;
}

export const useOverlay = create<UiState>((set) => ({
  overlays: 0,
  pushOverlay: () => {
    set((s) => ({ overlays: s.overlays + 1 }));
    return () => set((s) => ({ overlays: Math.max(0, s.overlays - 1) }));
  },
}));
