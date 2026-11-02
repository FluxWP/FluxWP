// Uygulama güncellemesi: açılışta ve 6 saatte bir denetlenir. Kurulabilir sürüm arka planda indirilir,
// hazır olunca sol altta "Yeniden başlat" çıkar.
import { create } from 'zustand';
import { platform, type AppUpdate } from '@fluxwp/core';

type Status = 'idle' | 'checking' | 'none' | 'available' | 'downloading' | 'ready' | 'installing' | 'error';

interface UpdateState {
  status: Status;
  update: AppUpdate | null;
  progress: number;
  error: string;
  /** manual: Ayarlar'daki düğme (sonuç her durumda gösterilir). */
  check(manual?: boolean): Promise<void>;
  install(): Promise<void>;
}

const EVERY = 6 * 60 * 60 * 1000;

export const useUpdate = create<UpdateState>((set, get) => ({
  status: 'idle',
  update: null,
  progress: 0,
  error: '',
  async check() {
    const p = platform();
    const s = get().status;
    if (!p.checkUpdate || s === 'checking' || s === 'downloading' || s === 'ready' || s === 'installing') return;
    set({ status: 'checking', error: '' });
    try {
      const u = await p.checkUpdate();
      if (!u) return set({ status: 'none', update: null });
      set({ update: u, status: u.installable ? 'downloading' : 'available', progress: 0 });
      if (u.installable) {
        await u.download((progress) => set({ progress }));
        set({ status: 'ready' });
      }
    } catch (e) {
      set({ status: 'error', error: (e as Error)?.message ?? String(e) });
    }
  },
  async install() {
    const u = get().update;
    if (!u) return;
    if (!u.installable) return void (await u.install());
    set({ status: 'installing' });
    try {
      await u.install();
    } catch (e) {
      set({ status: 'error', error: (e as Error)?.message ?? String(e) });
    }
  },
}));

let started = false;
/** Masaüstünde bir kez çağrılır. */
export function startUpdateChecks(): void {
  if (started || !platform().checkUpdate) return;
  started = true;
  setTimeout(() => void useUpdate.getState().check(), 8000);
  setInterval(() => void useUpdate.getState().check(), EVERY);
}
