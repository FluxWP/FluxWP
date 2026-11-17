// Uygulama güncellemesi: açılışta ve 6 saatte bir denetlenir. Yeni sürüm bulununca kullanıcıya sorulur
// ("Yeni sürüm X · İndir"); indirme yalnız kullanıcı onaylayınca başlar, bitince "Yeniden başlat" çıkar.
import { create } from 'zustand';
import { platform, type AppUpdate } from '@fluxwp/core';

type Status = 'idle' | 'checking' | 'none' | 'available' | 'downloading' | 'ready' | 'installing' | 'error';

interface UpdateState {
  status: Status;
  update: AppUpdate | null;
  progress: number;
  error: string;
  /** Hata hangi adımda: denetleme mi indirme/kurulum mu. */
  failedAt: 'check' | 'download' | '';
  /** manual: Ayarlar'daki düğme (sonuç her durumda gösterilir). */
  check(manual?: boolean): Promise<void>;
  /** Kullanıcı onayıyla indirir (kurulabilir sürüm); kurulamayan sürümde indirme sayfasını açar. */
  download(): Promise<void>;
  install(): Promise<void>;
}

const EVERY = 6 * 60 * 60 * 1000;

export const useUpdate = create<UpdateState>((set, get) => ({
  status: 'idle',
  update: null,
  progress: 0,
  error: '',
  failedAt: '',
  async check(manual = false) {
    const p = platform();
    const s = get().status;
    if (!p.checkUpdate || s === 'checking' || s === 'downloading' || s === 'ready' || s === 'installing') return;
    // Kullanıcıya zaten sorulan sürüm varken arka plan denetimi durumu sıfırlamaz.
    if (s === 'available' && !manual) return;
    set({ status: 'checking', error: '', failedAt: '' });
    try {
      const u = await p.checkUpdate();
      if (!u) return set({ status: 'none', update: null });
      set({ update: u, status: 'available', progress: 0 });
    } catch (e) {
      set({ status: 'error', failedAt: 'check', error: errorText(e) });
    }
  },
  async download() {
    const u = get().update;
    const s = get().status;
    if (!u || s === 'downloading' || s === 'installing' || s === 'ready') return;
    if (!u.installable) return void (await u.install());
    set({ status: 'downloading', progress: 0, error: '', failedAt: '' });
    try {
      await u.download((progress) => set({ progress }));
      set({ status: 'ready' });
    } catch (e) {
      set({ status: 'error', failedAt: 'download', error: errorText(e) });
    }
  },
  async install() {
    const u = get().update;
    if (!u) return;
    if (!u.installable) return void (await u.install());
    if (get().status !== 'ready') return get().download();
    set({ status: 'installing' });
    try {
      await u.install();
    } catch (e) {
      set({ status: 'error', failedAt: 'download', error: errorText(e) });
    }
  },
}));

function errorText(e: unknown): string {
  return (e as Error)?.message ?? String(e);
}

let started = false;
/** Masaüstünde bir kez çağrılır. */
export function startUpdateChecks(): void {
  if (started || !platform().checkUpdate) return;
  started = true;
  setTimeout(() => void useUpdate.getState().check(), 8000);
  setInterval(() => void useUpdate.getState().check(), EVERY);
}
