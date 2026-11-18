// Uygulama güncellemesi: açılışta, saatte bir ve uygulama öne gelince denetlenir. Yeni sürüm bulununca kullanıcıya sorulur
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

const EVERY = 60 * 60 * 1000;

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
let lastOk = 0;
let retries = 0;
const RETRY = 60 * 1000;
const STALE = 30 * 60 * 1000;

/** Arka plan denetimi: hata olursa birkaç kez kısa aralıkla tekrar dener, başarılıysa zamanı kaydeder. */
async function autoCheck(): Promise<void> {
  const st = useUpdate.getState();
  await st.check();
  const s = useUpdate.getState().status;
  if (s === 'error' && useUpdate.getState().failedAt === 'check') {
    if (retries++ < 5) setTimeout(() => void autoCheck(), RETRY * retries);
    return;
  }
  if (s !== 'checking') {
    retries = 0;
    lastOk = Date.now();
  }
}

/**
 * Masaüstünde bir kez çağrılır. Açılışta hemen, sonra saatte bir ve uygulama öne gelince
 * (son denetim 30 dakikadan eskiyse) denetler. macOS arka plandaki pencerenin zamanlayıcılarını
 * kıstığı için yalnız setTimeout'a güvenilmez; öne gelme olayı denetimi garanti eder.
 */
export function startUpdateChecks(): void {
  const p = platform();
  if (started || !p.checkUpdate) return;
  started = true;
  setTimeout(() => void autoCheck(), 1500);
  setInterval(() => void autoCheck(), EVERY);
  const onVisible = (visible: boolean) => {
    if (visible && Date.now() - lastOk > STALE && useUpdate.getState().status !== 'checking') void autoCheck();
  };
  if (p.visibility) p.visibility.subscribe(onVisible);
  else document.addEventListener('visibilitychange', () => onVisible(document.visibilityState === 'visible'));
}
