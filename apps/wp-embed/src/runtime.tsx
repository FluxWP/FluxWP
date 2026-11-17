// Shared runtime for plugin screens (Flux GraphQL assets/runtime/, built with FLUX_EMBED_RUNTIME=1).
//
// Loaded once per page. It carries React, React Query, i18next and the open @fluxwp packages, and exposes them
// as globalThis.FluxRuntime.modules. A plugin screen bundle (@fluxwp/plugin-kit) does not ship those: its
// imports of 'react', '@fluxwp/graphql/react' … read this object. The plugin entry registers its screens:
//
//   globalThis.FluxRuntime.register({ 'acme-orders': () => import('./Orders') }, { resources: { tr: { acme: {…} } } })
//
// Mounting is the same as the FluxWP bundle (embed.tsx): FluxModulesOptions on the first load, mount/unmount
// from the Flux GraphQL screen navigator after that.
import * as React from 'react';
import * as JsxRuntime from 'react/jsx-runtime';
import * as ReactDOM from 'react-dom';
import * as ReactDOMClient from 'react-dom/client';
import * as ReactQuery from '@tanstack/react-query';
import * as I18next from 'i18next';
import * as ReactI18next from 'react-i18next';
import * as FluxCore from '@fluxwp/core';
import * as FluxTokens from '@fluxwp/tokens';
import * as FluxUi from '@fluxwp/ui';
import * as FluxGraphql from '@fluxwp/graphql';
import * as FluxGraphqlReact from '@fluxwp/graphql/react';
import * as FluxHost from '@fluxwp/modules/src/host';
import { lazy, Suspense, createElement, type ComponentType } from 'react';
import { Loading } from '@fluxwp/ui';
import { createEmbed, type MountOptions } from './embed';
import '@fluxwp/ui/src/styles.css';
import './embed.css';

export type { MountOptions } from './embed';

/** Bump when a module is removed or changes incompatibly; plugins check it (plugin-kit). */
export const RUNTIME_API = 1;

type ScreenLoader = () => Promise<{ default: ComponentType }>;
/** lang → namespace → keys, e.g. { tr: { acme: { title: 'Siparişler' } } }. */
type Resources = Record<string, Record<string, Record<string, unknown>>>;
type I18nInstance = ReturnType<typeof FluxCore.createI18n>;

/** Module specifiers a plugin bundle may import from the runtime (and nothing else from these packages). */
export const modules = {
  react: React,
  'react/jsx-runtime': JsxRuntime,
  'react-dom': ReactDOM,
  'react-dom/client': ReactDOMClient,
  '@tanstack/react-query': ReactQuery,
  i18next: I18next,
  'react-i18next': ReactI18next,
  '@fluxwp/core': FluxCore,
  '@fluxwp/tokens': FluxTokens,
  '@fluxwp/ui': FluxUi,
  '@fluxwp/graphql': FluxGraphql,
  '@fluxwp/graphql/react': FluxGraphqlReact,
  '@fluxwp/host': FluxHost,
} as const;

type PreloadableScreen = ComponentType & { preload?: () => Promise<void> };
const screens = new Map<string, PreloadableScreen>();
const bundles: Resources[] = [];
const instances: I18nInstance[] = [];

function addResources(inst: I18nInstance, res: Resources): void {
  for (const [lng, namespaces] of Object.entries(res)) {
    for (const [ns, keys] of Object.entries(namespaces)) inst.addResourceBundle(lng, ns, keys, true, false);
  }
}

function lazyScreen(load: ScreenLoader): PreloadableScreen {
  const Screen = lazy(load);
  let ready: ComponentType | null = null;
  const Bound: PreloadableScreen = () =>
    ready ? createElement(ready) : createElement(Suspense, { fallback: createElement(Loading) }, createElement(Screen));
  Bound.preload = () =>
    load().then((m) => {
      ready = m.default;
    });
  return Bound;
}

const api = createEmbed({
  get: (id) => screens.get(id),
  preload: (id) => screens.get(id)?.preload?.().catch(() => undefined) ?? Promise.resolve(),
  i18n: (inst) => {
    instances.push(inst);
    for (const b of bundles) addResources(inst, b);
  },
});
export const { mount, unmount, preload, setNonce } = api;

/**
 * Registers a plugin's screens (ids are flx_admin_screen modules, package prefixed). A repeated id keeps the
 * first one and is reported. Mounts the page's pending screen when it is one of them.
 */
export function register(map: Record<string, ScreenLoader>, opts: { resources?: Resources } = {}): void {
  if (opts.resources) {
    bundles.push(opts.resources);
    for (const inst of instances) addResources(inst, opts.resources);
  }
  for (const [id, load] of Object.entries(map)) {
    if (screens.has(id)) {
      console.error(`[FluxRuntime] screen "${id}" is registered twice; the first one is used.`);
      continue;
    }
    screens.set(id, lazyScreen(load));
  }
  mountPending();
}

export interface FluxRuntimeGlobal {
  api: number;
  modules: typeof modules;
  register: typeof register;
  mount: typeof mount;
  unmount: typeof unmount;
  preload: typeof preload;
  setNonce: typeof setNonce;
  has: (id: string) => boolean;
}

interface FluxModulesPending {
  id: string;
  opts: MountOptions;
  done?: boolean;
}

declare global {
  // eslint-disable-next-line no-var
  var FluxRuntime: FluxRuntimeGlobal | undefined;
  interface Window {
    FluxModulesOptions?: FluxModulesPending;
  }
}

/** The page's screen (FluxModulesOptions, written by flx_admin_screen_render), once its plugin registered it. */
function mountPending(): void {
  const slot = window.FluxModulesOptions;
  if (!slot || slot.done || !screens.has(slot.opts.module)) return;
  const el = document.getElementById(slot.id);
  if (!el) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountPending, { once: true });
    return;
  }
  slot.done = true;
  const go = () => {
    try {
      mount(el, slot.opts);
    } catch (e) {
      el.textContent = e instanceof Error ? e.message : String(e);
    }
  };
  void preload(slot.opts.module).then(go, go);
}

if (typeof window !== 'undefined' && !globalThis.FluxRuntime) {
  globalThis.FluxRuntime = { api: RUNTIME_API, modules, register, mount, unmount, preload, setNonce, has: (id) => screens.has(id) };
}
