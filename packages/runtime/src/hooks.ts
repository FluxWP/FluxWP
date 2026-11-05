// Contract hooks: me, config, manifest, sync (badges), dashboard, merged config, shell model, theme.
// Hostinger budget (plan §1.3): at most these four requests on open, polling ≥ poll_min and only in front.
import { useMemo, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError, flxapp, platform, type Dashboard, type Me } from '@fluxwp/core';
import { DEFAULT_CONFIG, mergeConfig, type AppConfig, type Bootstrap, type ManifestBundle, type ThemeMode } from '@fluxwp/manifest';
import { themeFromConfig, type BrandColors, type ThemeInput } from '@fluxwp/tokens';
import { loadBootstrap, loadConfig, loadManifests, rememberedEtag, rememberEtag, withCore } from './api';
import { applyUserPrefs, useUserPrefs } from './prefs';
import { demoShellFetch } from './demo/fetch';
import { qk } from './query';
import { useScope } from './scope';
import { resolveShell, type ResolvedShell } from './shell';

/** Public discovery (login methods, clients, login_url…). Null on sites before flx-app 0.4.0. */
export function useBootstrap() {
  const { account } = useScope();
  return useQuery<Bootstrap | null>({
    queryKey: qk.bootstrap(),
    queryFn: () => loadBootstrap(account.siteUrl, account.authKind === 'demo' ? demoShellFetch : undefined),
    staleTime: 60 * 60_000,
  });
}

export function useMe() {
  const { api } = useScope();
  return useQuery<Me>({ queryKey: qk.me(), queryFn: () => flxapp(api).me(), staleTime: 60_000 });
}

/** Site config (layers 3+4) as sent by the server; null on sites without the endpoint. Sends `If-None-Match`. */
export function useSiteConfig() {
  const { api, account, client } = useScope();
  return useQuery<AppConfig | null>({
    queryKey: qk.config(),
    queryFn: async () => {
      const res = await loadConfig(api, undefined, await rememberedEtag(account.id, 'config'));
      if (res.notModified) return client.getQueryData<AppConfig | null>(qk.config()) ?? null;
      await rememberEtag(account.id, 'config', res.etag);
      return res.value;
    },
    staleTime: 5 * 60_000,
  });
}

/** All layers merged: default → brand → site → user prefs → device. */
export function useConfig(): AppConfig {
  const { app } = useScope();
  const site = useSiteConfig().data;
  const prefs = useUserPrefs().data;
  return useMemo(() => applyUserPrefs(mergeConfig({ brand: app.brand, site, device: app.device }, DEFAULT_CONFIG), prefs), [app.brand, app.device, site, prefs]);
}

export function useManifest() {
  const { api, account, client } = useScope();
  return useQuery<ManifestBundle>({
    queryKey: qk.manifest(),
    queryFn: async () => {
      const res = await loadManifests(api, undefined, await rememberedEtag(account.id, 'manifest'));
      if (res.notModified) return client.getQueryData<ManifestBundle>(qk.manifest()) ?? withCore({ modules: {} });
      await rememberEtag(account.id, 'manifest', res.etag);
      return res.value;
    },
    staleTime: 5 * 60_000,
    placeholderData: withCore({ modules: {} }),
  });
}

/** One desktop notification row from `GET /sync` (`events`, since the previous poll, at most 20). */
export interface SyncEvent {
  event: string;
  title: string;
  body?: string;
  target?: string;
  params?: Record<string, string>;
  at?: number;
}

export interface SyncState {
  counts: Record<string, number>;
  hashes?: Record<string, string>;
  events?: SyncEvent[];
  server_time?: number;
}

const hiddenInterval = (every: number) => {
  try {
    const v = platform().visibility;
    if (v && !v.visible()) return Math.max(every, 60_000);
  } catch {
    /* platform not set (tests) */
  }
  return every;
};

/** Badges and, on desktop, notification events. `flxapp/v1/sync` when the site has it, else `/counts`. */
export function useSync(enabled = true) {
  const { api } = useScope();
  const config = useConfig();
  const every = Math.max(10, config.transport?.poll_min ?? 15) * 1000;
  const since = useRef(0);
  return useQuery<SyncState>({
    queryKey: qk.sync(),
    enabled,
    refetchInterval: () => hiddenInterval(every),
    refetchIntervalInBackground: false,
    queryFn: async () => {
      try {
        const data = await api<SyncState>('flxapp/v1/sync', { query: { since: since.current } });
        since.current = typeof data.server_time === 'number' ? data.server_time : Math.floor(Date.now() / 1000);
        return data;
      } catch (e) {
        if (!(e instanceof ApiError && (e.status === 404 || e.code === 'rest_no_route'))) throw e;
        return { counts: await flxapp(api).counts() };
      }
    },
  });
}

export function useDashboard() {
  const { api } = useScope();
  return useQuery<Dashboard>({ queryKey: qk.dashboard(), queryFn: () => flxapp(api).dashboard(), staleTime: 60_000 });
}

/** Tabs, drawer and top bar for this account, resolved against its manifests and menu. */
export function useShellModel(): ResolvedShell {
  const { app, renderer } = useScope();
  const config = useConfig();
  const me = useMe().data;
  const manifests = useManifest().data;
  const prefs = useUserPrefs().data;
  const counts = useSync(!!me).data?.counts;
  return useMemo(
    () => resolveShell(config, { me, counts, manifests: manifests?.modules, native: renderer.native, appVersion: app.version, forceWeb: new Set(prefs?.forceWeb ?? []) }),
    [config, me, counts, manifests, renderer.native, app.version, prefs?.forceWeb],
  );
}

/** Theme for the account's site in the given mode (site theme + white-label overrides). */
export function useSiteTheme(mode: Exclude<ThemeMode, 'system'>, brand: BrandColors, overrides?: ThemeInput['overrides']) {
  const config = useConfig();
  return useMemo(() => themeFromConfig({ mode, brand, config: config.theme, overrides }), [mode, brand, config.theme, overrides]);
}
