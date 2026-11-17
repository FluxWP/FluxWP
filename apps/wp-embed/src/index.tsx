// WordPress admin gömmesi: eklenti admin sayfası modül ekranını FluxWP ile aynı kodla açar.
//   <div id="flxc-app"></div>
// Tek dosya (IIFE): FluxModules.mount(el, { module: 'flxc-inbox', rest: '/wp-json/', nonce: '…', locale: 'tr_TR' }).
// Parça (ES modül): sayfa seçenekleri window.FluxModulesOptions'a yazar; giriş onları bir kez bağlar.
import { moduleScreens, preloadScreen } from '@fluxwp/modules';
import { createEmbed, type MountOptions } from './embed';
import { mountManifest, type ManifestMountOptions } from './manifest';
import '@fluxwp/ui/src/styles.css';
import './embed.css';

export type { MountOptions } from './embed';

const api = createEmbed({ get: (id) => moduleScreens[id], preload: preloadScreen });
export const { mount, unmount, preload, setNonce } = api;

export const modules = Object.keys(moduleScreens);
export { mountManifest, type ManifestMountOptions };

interface FluxModulesPending {
  id: string;
  opts: MountOptions;
  done?: boolean;
}

declare global {
  interface Window {
    FluxModules?: {
      mount: typeof mount;
      unmount: typeof unmount;
      preload: typeof preload;
      setNonce: typeof setNonce;
      modules: typeof modules;
      mountManifest: typeof mountManifest;
    };
    /** Parça derlemesi: PHP bağlama seçeneklerini giriş çalışmadan yazar. */
    FluxModulesOptions?: FluxModulesPending;
  }
}

/** Seçenekler girişten önce veya sonra gelse de, DOM hazır olsun olmasın, bir kez bağla. */
function mountPending(): void {
  const slot = window.FluxModulesOptions;
  // A plugin screen on the shared runtime (runtime.tsx) is not this bundle's to mount.
  if (!slot || slot.done || !moduleScreens[slot.opts.module]) return;
  const el = document.getElementById(slot.id);
  if (!el) return;
  slot.done = true;
  const go = () => {
    try {
      mount(el, slot.opts);
    } catch (e) {
      el.textContent = e instanceof Error ? e.message : String(e);
    }
  };
  // The screen's code first, then one paint with content (no spinner in between).
  void preloadScreen(slot.opts.module).then(go, go);
}

if (typeof window !== 'undefined') {
  window.FluxModules = { mount, unmount, preload, setNonce, modules, mountManifest };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountPending);
  mountPending();
}
