// Contract hooks: me, config, manifest, sync (badges), dashboard, merged config, shell model, theme.
// Hostinger budget (plan §1.3): at most these four requests on open, polling ≥ poll_min and only in front.
import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError, flxapp, type Dashboard, type Me } from '@fluxwp/core';
import { DEFAULT_CONFIG, mergeConfig, type AppConfig, type ManifestBundle, type ThemeMode } from '@fluxwp/manifest';
import { themeFromConfig, type BrandColors, type ThemeInput } from '@fluxwp/tokens';
import { loadConfig, loadManifests, withCore } from './api';
import { qk } from './query';
import { useScope } from './scope';
import { resolveShell, type ResolvedShell } from './shell';

export function useMe() {
  const { api } = useScope();
  return useQuery<Me>({ queryKey: qk.me(), queryFn: () => flxapp(api).me(), staleTime: 60_000 });
}

/** Site config (layers 3+4) as sent by the server; null on sites without the endpoint. */
export function useSiteConfig() {
  const { api } = useScope();
  return useQuery<AppConfig | null>({ queryKey: qk.config(), queryFn: () => loadConfig(api), staleTime: 5 * 60_000 });
}

/** All layers merged: default → brand → site → user → device. */
export function useConfig(): AppConfig {
  const { app } = useScope();
  const site = useSiteConfig().data;
  return useMemo(() => mergeConfig({ brand: app.brand, site, device: app.device }, DEFAULT_CONFIG), [app.brand, app.device, site]);
}

export function useManifest() {
  const { api } = useScope();
  return useQuery<ManifestBundle>({ queryKey: qk.manifest(), queryFn: () => loadManifests(api), staleTime: 5 * 60_000, placeholderData: withCore({ modules: {} }) });
}

export interface SyncState {
  counts: Record<string, number>;
  hashes?: Record<string, string>;
}

/** Badges. `flxapp/v1/sync` when the site has it, else the existing `/counts`. */
export function useSync(enabled = true) {
  const { api } = useScope();
  const config = useConfig();
  const every = Math.max(10, config.transport?.poll_min ?? 15) * 1000;
  return useQuery<SyncState>({
    queryKey: qk.sync(),
    enabled,
    refetchInterval: every,
    refetchIntervalInBackground: false,
    queryFn: async () => {
      try {
        return await api<SyncState>('flxapp/v1/sync');
      } catch (e) {
        if (!(e instanceof ApiError && e.status === 404)) throw e;
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
  const counts = useSync(!!me).data?.counts;
  return useMemo(
    () => resolveShell(config, { me, counts, manifests: manifests?.modules, native: renderer.native, appVersion: app.version }),
    [config, me, counts, manifests, renderer.native, app.version],
  );
}

/** Theme for the account's site in the given mode (site theme + white-label overrides). */
export function useSiteTheme(mode: Exclude<ThemeMode, 'system'>, brand: BrandColors, overrides?: ThemeInput['overrides']) {
  const config = useConfig();
  return useMemo(() => themeFromConfig({ mode, brand, config: config.theme, overrides }), [mode, brand, config.theme, overrides]);
}
