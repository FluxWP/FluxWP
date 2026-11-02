// Sol menü görünüm tercihleri (cihaza özel): genişlik, kapalı/açık, açık alt menüler.
import { create } from 'zustand';

const KEY = 'flux.sidebar';
export const SIDEBAR_MIN = 200;
export const SIDEBAR_MAX = 420;
const DEFAULT = 260;

interface SidebarState {
  width: number;
  collapsed: boolean;
  /** Alt menüsü açık öğeler (id). */
  open: string[];
  /** Kapalı menü grupları (id). */
  closedGroups: string[];
  setWidth(w: number): void;
  toggle(): void;
  toggleOpen(id: string, force?: boolean): void;
  toggleGroup(id: string): void;
}

function read(): Partial<SidebarState> {
  try {
    return JSON.parse(localStorage.getItem(KEY) ?? '{}');
  } catch {
    return {};
  }
}

export const useSidebar = create<SidebarState>((set, get) => {
  const init = read();
  const save = () => {
    const { width, collapsed, open, closedGroups } = get();
    try {
      localStorage.setItem(KEY, JSON.stringify({ width, collapsed, open, closedGroups }));
    } catch {
      /* depolama yok */
    }
  };
  return {
    width: Math.min(SIDEBAR_MAX, Math.max(SIDEBAR_MIN, Number(init.width) || DEFAULT)),
    collapsed: !!init.collapsed,
    open: Array.isArray(init.open) ? init.open : [],
    closedGroups: Array.isArray(init.closedGroups) ? init.closedGroups : [],
    toggleGroup: (id) => {
      const c = get().closedGroups;
      set({ closedGroups: c.includes(id) ? c.filter((x) => x !== id) : [...c, id] });
      save();
    },
    setWidth: (w) => (set({ width: Math.round(Math.min(SIDEBAR_MAX, Math.max(SIDEBAR_MIN, w))) }), save()),
    toggle: () => (set({ collapsed: !get().collapsed }), save()),
    toggleOpen: (id, force) => {
      const has = get().open.includes(id);
      const want = force ?? !has;
      if (want === has) return;
      set({ open: want ? [...get().open, id] : get().open.filter((x) => x !== id) });
      save();
    },
  };
});
