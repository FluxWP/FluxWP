import { useCallback, useMemo } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { render, test, value, type ExprContext, type Manifest, type Navigate } from '@fluxwp/manifest';
import { useManifest, useMe } from '../hooks';
import { qk } from '../query';
import { resolveTarget, type ResolveEnv, type Route } from '../resolve';
import { useScope } from '../scope';

export function useResolveEnv(): ResolveEnv {
  const { renderer, app } = useScope();
  const me = useMe().data;
  const manifests = useManifest().data?.modules;
  return useMemo(() => ({ me, manifests, native: renderer.native, appVersion: app.version }), [me, manifests, renderer.native, app.version]);
}

/** Base template context for a manifest screen. */
export function useBaseContext(manifest: Manifest, params: Record<string, string>): ExprContext {
  const me = useMe().data;
  const locale = me?.user.locale?.replace('_', '-');
  return useMemo(() => ({ params, me: me?.user, site: me?.site, can: manifest.can ?? {}, locale }), [params, me, manifest.can, locale]);
}

/** `"$t:key"` → app string; anything else is server text. */
export function useText() {
  const { renderer } = useScope();
  return useCallback((s: string | undefined, ctx?: ExprContext) => {
    if (!s) return '';
    if (s.startsWith('$t:')) return renderer.t(s.slice(3));
    return ctx ? render(s, ctx) : s;
  }, [renderer]);
}

/** Navigate target with templated params → Route. */
export function useNavigate() {
  const env = useResolveEnv();
  const { renderer } = useScope();
  const toRoute = useCallback(
    (nav: Navigate, ctx: ExprContext): Route => {
      const target = render(nav.target, ctx);
      const params = Object.fromEntries(Object.entries(nav.params ?? {}).map(([k, v]) => [k, String(value(v, ctx) ?? '')]));
      return resolveTarget(target, env, params);
    },
    [env],
  );
  const go = useCallback((nav: Navigate, ctx: ExprContext) => renderer.nav.push(toRoute(nav, ctx)), [renderer, toRoute]);
  return { toRoute, go };
}

/** `after.invalidate: ["contacts"]` → every cached request of those sources in this module. */
export function useInvalidate(module: string) {
  const client = useQueryClient();
  return useCallback((sources: string[] | undefined) => Promise.all((sources ?? []).map((s) => client.invalidateQueries({ queryKey: qk.sourceRoot(module, s) }))), [client, module]);
}

export const visible = <T extends { when?: Parameters<typeof test>[0] }>(xs: T[] | undefined, ctx: ExprContext): T[] => (xs ?? []).filter((x) => test(x.when, ctx));
