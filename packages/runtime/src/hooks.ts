// Contract hooks: me, config, manifest, sync (badges), dashboard, merged config, shell model, theme.
// Hostinger budget (plan §1.3): at most these four requests on open, polling ≥ poll_min and only in front.
import { useEffect, useMemo, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError, accountsStore, flxapp, getDemoLocale, platform, type Dashboard, type Me } from '@fluxwp/core';
import { DEFAULT_CONFIG, mergeConfig, type AppConfig, type Bootstrap, type ManifestBundle, type ThemeMode } from '@fluxwp/manifest';
import { themeFromConfig, type BrandColors, type ThemeInput } from '@fluxwp/tokens';
import { loadBootstrap, loadConfig, loadContractValue, loadManifests, rememberedEtag, rememberEtag, withCore } from './api';
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

/** Raster mark for the account avatar. Skips SVG (the image view cannot draw it). */
export function siteMark(...urls: (string | null | undefined)[]): string {
  const list = urls.filter((u): u is string => typeof u === 'string' && u.length > 0);
  return list.find((u) => !/\.svg(?:$|\?)/i.test(u)) ?? '';
}

/** Writes the site mark from /me or bootstrap onto the account, so a logo added later shows up without deleting the account. */
export function useRememberSiteMark() {
  const { account } = useScope();
  const me = useMe();
  const boot = useBootstrap();
  const icon = siteMark(me.data?.site.icon, boot.data?.brand?.icon, boot.data?.site?.icon, boot.data?.brand?.logo, boot.data?.brand?.logo_dark);
  const name = me.data?.site.name || boot.data?.site.name || '';
  useEffect(() => {
    const patch: { siteIcon?: string; siteName?: string } = {};
    if (icon && icon !== account.siteIcon) patch.siteIcon = icon;
    if (name && name !== account.siteName) patch.siteName = name;
    if (patch.siteIcon || patch.siteName) void accountsStore.getState().update(account.id, patch);
  }, [account.id, account.siteIcon, account.siteName, icon, name]);
}

export function useMe() {
  const { api } = useScope();
  return useQuery<Me>({ queryKey: qk.me(), queryFn: () => flxapp(api).me(), staleTime: 60_000 });
}

/** Site config (layers 3+4) as sent by the server; null on sites without the endpoint. Sends `If-None-Match`. */
export function useSiteConfig() {
  const { api, account, client } = useScope();
  const queryKey = account.authKind === 'demo' ? [...qk.config(), getDemoLocale()] : qk.config();
  return useQuery<AppConfig | null>({
    queryKey,
    queryFn: async () => {
      const res = await loadContractValue(etag => loadConfig(api, undefined, etag), await rememberedEtag(account.id, 'config'), () => client.getQueryData<AppConfig | null>(queryKey));
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
  const queryKey = account.authKind === 'demo' ? [...qk.manifest(), getDemoLocale()] : qk.manifest();
  return useQuery<ManifestBundle>({
    queryKey,
    queryFn: async () => {
      const res = await loadContractValue(etag => loadManifests(api, undefined, etag), await rememberedEtag(account.id, 'manifest'), () => client.getQueryData<ManifestBundle>(queryKey));
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
