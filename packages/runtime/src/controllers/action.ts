// Action flow (§7.6): allowed? → confirm → prompt → one effect → after (toast, invalidate, refresh, back, navigate).
import { useCallback, useState } from 'react';
import { ApiError } from '@fluxwp/core';
import { render, test, type Action, type ExprContext, type Manifest } from '@fluxwp/manifest';
import { runRequest } from '../api';
import { useConfig } from '../hooks';
import { safeAdminPath } from '@fluxwp/manifest';
import { actionAllowed, actionRequest } from '../source';
import { useScope } from '../scope';
import { useInvalidate, useNavigate, useText } from './context';

export interface ActionView {
  id: string;
  label: string;
  icon?: string;
  tone?: Action['tone'];
}

export function useActions(manifest: Manifest, opts: { onRefresh?: () => void } = {}) {
  const { api, renderer } = useScope();
  const config = useConfig();
  const text = useText();
  const invalidate = useInvalidate(manifest.id);
  const { go } = useNavigate();
  const [running, setRunning] = useState<string | null>(null);

  const list = useCallback(
    (ids: string[] | undefined, ctx: ExprContext): ActionView[] =>
      (ids ?? [])
        .map((id) => [id, manifest.actions?.[id]] as const)
        .filter((x): x is readonly [string, Action] => actionAllowed(x[1], ctx, test))
        .map(([id, a]) => ({ id, label: text(a.label, ctx), icon: a.icon, tone: a.tone })),
    [manifest, text],
  );

  const run = useCallback(
    async (id: string, base: ExprContext): Promise<boolean> => {
      const a = manifest.actions?.[id];
      if (!actionAllowed(a, base, test)) return false;
      let ctx = base;
      if (a.confirm) {
        const ok = await renderer.ui.confirm({ title: text(a.confirm.title, ctx), body: text(a.confirm.body, ctx) || undefined, ok: text(a.confirm.ok, ctx) || undefined, tone: a.tone === 'danger' ? 'danger' : 'default' });
        if (!ok) return false;
      }
      if (a.prompt) {
        const input = await renderer.ui.prompt({ title: text(a.prompt.title, ctx), fields: a.prompt.fields.map((f) => ({ ...f, label: text(f.label, ctx) })) });
        if (!input) return false;
        ctx = { ...ctx, input };
      }
      setRunning(id);
      try {
        if (a.request) await runRequest(api, actionRequest(a.request, ctx, config.transport?.method_override !== false));
        else if (a.navigate) go(a.navigate, ctx);
        else if (a.web) {
          const path = safeAdminPath(render(a.web, ctx));
          if (path) renderer.nav.push({ kind: 'web', path });
        } else if (a.open) renderer.nav.openUrl(render(a.open, ctx));
        else if (a.copy) await renderer.ui.copy(render(a.copy, ctx));
        else if (a.share) await renderer.ui.share({ text: render(a.share.text, ctx) || undefined, url: render(a.share.url, ctx) || undefined });
        else if (a.native) renderer.nav.push({ kind: 'native', component: a.native.component, params: {} });
        else if (a.refresh) opts.onRefresh?.();

        const after = a.after;
        if (a.haptic) renderer.ui.haptic?.(a.haptic);
        if (after?.toast) renderer.ui.toast(text(after.toast, ctx), 'ok');
        await invalidate(after?.invalidate);
        if (after?.refresh) opts.onRefresh?.();
        if (after?.back) renderer.nav.back();
        else if (after?.navigate) go(after.navigate, ctx);
        return true;
      } catch (e) {
        renderer.ui.haptic?.('error');
        renderer.ui.toast(e instanceof ApiError && e.message ? e.message : renderer.t('action.failed'), 'error');
        return false;
      } finally {
        setRunning(null);
      }
    },
    [manifest, renderer, text, api, config.transport?.method_override, go, invalidate, opts],
  );

  return { list, run, running };
}
