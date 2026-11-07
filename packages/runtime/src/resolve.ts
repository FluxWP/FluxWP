// §6 resolution: a target → what the app opens (manifest screen, native screen, webview, URL, shell screen).
import { adminPath, newerVersion, type Me } from '@fluxwp/core';
import { matchRoute, parseTarget, type Manifest, type ManifestBundle, type Screen, type ShellName, type Target } from '@fluxwp/manifest';

export type Route =
  | { kind: 'manifest'; module: string; screen: string; params: Record<string, string> }
  | { kind: 'native'; component: string; params: Record<string, string> }
  | { kind: 'web'; path: string; title?: string }
  | { kind: 'url'; url: string }
  | { kind: 'shell'; name: ShellName }
  | { kind: 'none' };

export interface ResolveEnv {
  manifests?: ManifestBundle['modules'];
  me?: Pick<Me, 'menu' | 'modules'> | null;
  /** Component ids with a native screen in this app (native/registry). */
  native?: ReadonlySet<string>;
  /** This app's version, compared with manifest `min_app`. */
  appVersion: string;
  /** Module ids the user wants on the web (layout prefs `forceWeb`). */
  forceWeb?: ReadonlySet<string>;
}

const usable = (m: Manifest | undefined, env: ResolveEnv): boolean =>
  !!m && !env.forceWeb?.has(m.id) && (!m.min_app || !newerVersion(m.min_app, env.appVersion)) && !!m.screens[m.entry];

function menuPath(slug: string, env: ResolveEnv): string | null {
  for (const item of env.me?.menu ?? []) {
    if (item.slug === slug) return adminPath(item.url);
    const sub = item.items?.find((c) => c.slug === slug);
    if (sub) return adminPath(sub.url);
  }
  return null;
}

/** A manifest screen of type `native`/`web` opens that component/page directly. */
function screenRoute(m: Manifest, screen: string, env: ResolveEnv, params: Record<string, string>): Route {
  const s = m.screens[screen]!;
  if (s.type === 'web') return { kind: 'web', path: s.path };
  if (s.type === 'native') {
    if (env.native?.has(s.component)) return { kind: 'native', component: s.component, params };
    const web = s.fallback?.web ?? m.fallback?.web;
    return web ? { kind: 'web', path: web } : { kind: 'none' };
  }
  return { kind: 'manifest', module: m.id, screen, params };
}

function moduleRoute(id: string, env: ResolveEnv, params: Record<string, string>, fallbackPath?: string | null): Route {
  const m = env.manifests?.[id];
  if (m && usable(m, env)) return screenRoute(m, m.entry, env, params);
  if (env.native?.has(id)) return { kind: 'native', component: id, params };
  const web = m?.fallback?.web ?? fallbackPath ?? (m?.menu ? menuPath(m.menu, env) : null) ?? (env.me?.modules[id]?.menu ? menuPath(env.me.modules[id]!.menu!, env) : null);
  return web ? { kind: 'web', path: web } : { kind: 'none' };
}

export function resolveTarget(target: string | Target | null | undefined, env: ResolveEnv, params: Record<string, string> = {}): Route {
  const t = typeof target === 'string' ? parseTarget(target) : target;
  if (!t) return { kind: 'none' };
  switch (t.kind) {
    case 'shell':
      return { kind: 'shell', name: t.name };
    case 'url':
      return { kind: 'url', url: t.url };
    case 'web':
      return { kind: 'web', path: t.path };
    case 'native':
      return env.native?.has(t.component) ? { kind: 'native', component: t.component, params } : { kind: 'none' };
    case 'screen': {
      const m = env.manifests?.[t.module];
      if (m && usable(m, env) && m.screens[t.screen]) return screenRoute(m, t.screen, env, params);
      return moduleRoute(t.module, env, params);
    }
    case 'module':
      return moduleRoute(t.module, env, params);
    case 'menu': {
      const byManifest = Object.values(env.manifests ?? {}).find((m) => m.menu === t.slug);
      const byModule = Object.entries(env.me?.modules ?? {}).find(([, m]) => m.menu === t.slug)?.[0];
      const id = byManifest?.id ?? byModule;
      const path = menuPath(t.slug, env) ?? (t.slug.includes('.php') ? t.slug : `admin.php?page=${t.slug}`);
      if (id) return moduleRoute(id, env, params, path);
      return routeForAdminPath(path, env) ?? { kind: 'web', path };
    }
  }
}

/** Webview link capture: a same-site admin URL that a manifest `routes` entry claims opens natively. */
export function routeForAdminPath(path: string, env: ResolveEnv): Route | null {
  for (const m of Object.values(env.manifests ?? {})) {
    if (!usable(m, env)) continue;
    for (const [pattern, r] of Object.entries(m.routes ?? {})) {
      const caps = matchRoute(pattern, path);
      if (!caps || !m.screens[r.screen]) continue;
      const params = Object.fromEntries(Object.entries(r.params ?? {}).map(([k, v]) => [k, v.replace(/\{(\w+)\}/g, (_, n: string) => caps[n] ?? '')]));
      return screenRoute(m, r.screen, env, params);
    }
  }
  return null;
}

export function screenOf(bundle: ManifestBundle['modules'] | undefined, module: string, screen: string): { manifest: Manifest; screen: Screen } | null {
  const m = bundle?.[module];
  const s = m?.screens[screen];
  return m && s ? { manifest: m, screen: s } : null;
}
