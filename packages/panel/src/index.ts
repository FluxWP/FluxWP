// Panel: web ve masaüstünde aynı kod. Kabuk (Tauri/tarayıcı) platformu verip mount eder.
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { setPlatform, type Platform } from '@fluxwp/core';
import { installFlxAppGraphQL } from '@fluxwp/graphql/flx-app';
import { App } from './App';
import { countingFetch } from './state/net';
import '@fluxwp/ui/src/styles.css';
import './styles.css';

export function mountApp(el: HTMLElement, p: Platform) {
  // Count requests (site warm-up waits for the network to go quiet).
  setPlatform({ ...p, fetch: countingFetch(p.fetch) });
  // The site contract (me, config, manifest, sync, prefs …) goes over GraphQL; sites without it stay on REST.
  installFlxAppGraphQL();
  createRoot(el).render(createElement(App));
}

export { webPlatform } from './platform/web';
export { App };
export { usePrefs } from './state/prefs';
export { APP_VERSION } from './version';
