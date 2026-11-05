// User prefs (GET/POST flxapp/v1/prefs): shared across the user's devices. Applied on top of the site
// config (pins, hidden targets, tab order) and as resolveTarget's forceWeb. Push toggles live here too.
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ApiError, type ApiClient } from '@fluxwp/core';
import type { AppConfig, TargetString } from '@fluxwp/manifest';
import { qk } from './query';
import { useScope } from './scope';

export interface PrefsEvent {
  id: string;
  title: string;
  source?: string;
  default?: boolean;
  on?: boolean;
}

export interface UserPrefs {
  order?: string[];
  hidden?: string[];
  pinned?: string[];
  forceWeb?: string[];
  tabs?: string[];
  cardOrder?: string[];
  cardHidden?: string[];
  push?: Record<string, boolean>;
  events?: PrefsEvent[];
  updated?: number;
}

const strings = (v: unknown): string[] | undefined => (Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : undefined);

export function parsePrefs(body: unknown): UserPrefs {
  const o = body && typeof body === 'object' ? (body as Record<string, unknown>) : {};
  const push: Record<string, boolean> = {};
  if (o.push && typeof o.push === 'object') {
    for (const [k, v] of Object.entries(o.push as Record<string, unknown>)) if (typeof v === 'boolean') push[k] = v;
  }
  const events = Array.isArray(o.events)
    ? o.events.flatMap((e) => {
        if (!e || typeof e !== 'object') return [];
        const x = e as Record<string, unknown>;
        return typeof x.id === 'string' ? [{ id: x.id, title: typeof x.title === 'string' ? x.title : x.id, source: typeof x.source === 'string' ? x.source : undefined, default: typeof x.default === 'boolean' ? x.default : undefined, on: typeof x.on === 'boolean' ? x.on : undefined }] : [];
      })
    : undefined;
  return {
    order: strings(o.order),
    hidden: strings(o.hidden),
    pinned: strings(o.pinned),
    forceWeb: strings(o.forceWeb),
    tabs: strings(o.tabs),
    cardOrder: strings(o.cardOrder),
    cardHidden: strings(o.cardHidden),
    push,
    events,
    updated: typeof o.updated === 'number' ? o.updated : undefined,
  };
}

/** Empty object when the site has no prefs endpoint yet (flx-app before 0.4.0). */
export async function loadPrefs(api: ApiClient): Promise<UserPrefs> {
  try {
    return parsePrefs(await api('flxapp/v1/prefs'));
  } catch (e) {
    if (e instanceof ApiError && (e.status === 404 || e.status === 403)) return {};
    throw e;
  }
}

/** Partial update: keys left out stay as they are on the server. */
export async function savePrefs(api: ApiClient, patch: Partial<UserPrefs>): Promise<UserPrefs> {
  return parsePrefs(await api('flxapp/v1/prefs', { method: 'POST', body: patch }));
}

/** Pins, hidden targets and tab order from prefs. Empty lists change nothing (a fresh account). */
export function applyUserPrefs(config: AppConfig, prefs: UserPrefs | null | undefined): AppConfig {
  if (!prefs) return config;
  let shell = config.shell;
  if (prefs.pinned?.length) shell = { ...shell, drawer: { ...shell.drawer, pins: prefs.pinned as TargetString[] } };
  if (prefs.hidden?.length) {
    const hide = new Set(prefs.hidden);
    shell = {
      ...shell,
      drawer: {
        ...shell.drawer,
        pins: (shell.drawer.pins ?? []).filter((p) => !hide.has(p)),
        groups: shell.drawer.groups.map((g) => ({ ...g, items: g.items.filter((item) => !hide.has(item)) })),
      },
    };
  }
  if (prefs.tabs?.length) {
    const tabs = prefs.tabs.flatMap((target) => shell.tabs.filter((t) => t.target === target)).slice(0, 5);
    if (tabs.length) shell = { ...shell, tabs };
  }
  return shell === config.shell ? config : { ...config, shell };
}

export function useUserPrefs() {
  const { api } = useScope();
  return useQuery({ queryKey: qk.prefs(), queryFn: () => loadPrefs(api), staleTime: 60_000 });
}

export function useSavePrefs() {
  const { api } = useScope();
  const client = useQueryClient();
  return useMutation({
    mutationFn: (patch: Partial<UserPrefs>) => savePrefs(api, patch),
    onSuccess: (data) => client.setQueryData(qk.prefs(), data),
  });
}
