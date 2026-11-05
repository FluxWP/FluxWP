// Config layers (§8.1): app default → brand (build) → site+role (server) → user prefs → device.
// Brand `locked` paths win over the site and user layers (white-label).
import { isUnsafeKey } from '@fluxwp/core';
import type { AppConfig, ConfigLayer, ShellConfig } from './types/config';

/** Layer 1: works on any site; tabs that the site cannot serve resolve to nothing and hide. */
export const DEFAULT_CONFIG: AppConfig = {
  schema: 'flx-app-config/1',
  theme: { mode: 'system', radius: 'medium', density: 'comfortable' },
  shell: {
    nav: 'tabs+drawer',
    home: 'screen:core/dashboard',
    tabs: [
      { id: 'home', target: 'screen:core/dashboard', icon: 'home', label: '$t:shell.tabs.home' },
      { id: 'posts', target: 'screen:core/posts', icon: 'post', label: '$t:shell.tabs.posts' },
      { id: 'comments', target: 'screen:core/comments', icon: 'comment', label: '$t:shell.tabs.comments' },
      { id: 'more', target: 'more', icon: 'menu', label: '$t:shell.tabs.more' },
    ],
    drawer: {
      enabled: true,
      header: 'account',
      pins: [],
      groups: [
        { id: 'content', label: '$t:shell.groups.content', items: ['screen:core/posts', 'screen:core/pages', 'menu:upload.php', 'screen:core/comments'] },
      ],
      footer: ['accounts', 'settings'],
    },
    topbar: { style: 'compact', show_logo: false, site_switcher: true, actions: [] },
  },
  features: { web_session: true, search: false },
  transport: { method_override: true, poll_min: 10 },
};

const isObj = (v: unknown): v is Record<string, unknown> => !!v && typeof v === 'object' && !Array.isArray(v);

/** Objects merge, arrays and scalars replace. */
export function deepMerge<T>(base: T, patch: unknown): T {
  if (!isObj(base) || !isObj(patch)) return (patch === undefined ? base : patch) as T;
  const out: Record<string, unknown> = { ...base };
  for (const [k, v] of Object.entries(patch)) out[k] = v === undefined ? out[k] : isObj(v) && isObj(out[k]) ? deepMerge(out[k], v) : v;
  return out as T;
}

const getAt = (o: unknown, path: string) => path.split('.').reduce<unknown>((c, k) => (isObj(c) ? c[k] : undefined), o);
function setAt(o: Record<string, unknown>, path: string, v: unknown) {
  const keys = path.split('.');
  if (keys.some(isUnsafeKey)) return;
  let cur = o;
  for (const k of keys.slice(0, -1)) {
    if (!isObj(cur[k])) cur[k] = {};
    cur = cur[k] as Record<string, unknown>;
  }
  const last = keys[keys.length - 1]!;
  cur[last] = v;
}

export interface ConfigLayers {
  brand?: ConfigLayer & { locked?: string[] };
  site?: AppConfig | null;
  user?: ConfigLayer;
  device?: ConfigLayer;
}

export function mergeConfig(layers: ConfigLayers, base: AppConfig = DEFAULT_CONFIG): AppConfig {
  const { locked = [], ...brand } = layers.brand ?? {};
  let c = deepMerge(base, brand);
  if (layers.site) c = deepMerge(c, { ...layers.site, schema: undefined });
  if (layers.user) c = deepMerge(c, layers.user);
  if (layers.device) c = deepMerge(c, layers.device);
  if (locked.length) {
    c = JSON.parse(JSON.stringify(c)) as AppConfig;
    for (const p of locked) {
      const v = getAt(brand, p);
      if (v !== undefined) setAt(c as unknown as Record<string, unknown>, p, v);
    }
  }
  return c;
}

/** Tabs ≤ 5; with no drawer, "more" is guaranteed so everything stays reachable (§8.3). */
export function normalizeShell(shell: ShellConfig): ShellConfig {
  let tabs = shell.tabs.slice(0, 5);
  const drawer = shell.nav !== 'tabs' && shell.drawer.enabled;
  if (!drawer && !tabs.some((t) => t.target === 'more')) {
    tabs = [...tabs.slice(0, 4), { id: 'more', target: 'more', icon: 'menu', label: '$t:shell.tabs.more' }];
  }
  return { ...shell, tabs: shell.nav === 'drawer' ? [] : tabs, drawer: { ...shell.drawer, enabled: drawer || shell.nav === 'drawer' } };
}
