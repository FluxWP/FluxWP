// Panel: web ve masaüstünde aynı kod. Kabuk (Tauri/tarayıcı) platformu verip mount eder.
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { setPlatform, type Platform } from '@fluxwp/core';
import { App } from './App';
import '@fluxwp/ui/src/styles.css';
import './styles.css';

export function mountApp(el: HTMLElement, p: Platform) {
  setPlatform(p);
  createRoot(el).render(createElement(App));
}

export { webPlatform } from './platform/web';
export { App };
export { usePrefs } from './state/prefs';
