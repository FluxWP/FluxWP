// Settings, content and tabs. Settings talks to flxapp/v1/settings/<option> (404 → empty form).
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError } from '@fluxwp/core';
import { getPath, type ContentScreen, type Manifest, type SettingsScreen, type TabsScreen } from '@fluxwp/manifest';
import { runRequest, sourceRequest, useApi, useBaseContext, useRenderer, useText } from '@fluxwp/runtime';
import { sanitizeHtml } from '@fluxwp/ui';
import { ManifestScreen } from './ManifestScreen';

export function SettingsScreenView({ manifest, screen }: { manifest: Manifest; screen: SettingsScreen }) {
  const api = useApi();
  const { t, ui } = useRenderer();
  const q = useQuery({
    queryKey: ['settings', screen.option],
    queryFn: async () => {
      try {
        return await api<Record<string, unknown>>(`flxapp/v1/settings/${screen.option}`);
      } catch (e) {
        if (e instanceof ApiError && (e.status === 404 || e.code === 'rest_no_route')) return {};
        throw e;
      }
    },
  });
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [busy, setBusy] = useState(false);
  const data = { ...(q.data ?? {}), ...values };
  const save = async () => {
    setBusy(true);
    try {
      await api(`flxapp/v1/settings/${screen.option}`, { method: 'POST', body: values });
      ui.toast(t('form.saved'), 'ok');
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="fx-screen">
      <header className="fx-head">
        <h1>{screen.title || manifest.title}</h1>
        <button type="button" className="fx-btn primary" disabled={busy || !Object.keys(values).length} onClick={() => void save()}>{t('form.submit')}</button>
      </header>
      <div className="fx-fields">
        {screen.fields.filter((f) => f.type !== 'hidden').map((f) => (
          <label key={f.name} className="fx-field">
            <span>{f.label}</span>
            {f.type === 'switch' ? (
              <input type="checkbox" checked={!!getPath(data, f.name)} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.checked }))} />
            ) : f.type === 'secret' ? (
              <input type="password" placeholder="••••" onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))} />
            ) : (
              <input value={String(getPath(data, f.name) ?? '')} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))} />
            )}
            {f.help && <small className="fx-muted">{f.help}</small>}
          </label>
        ))}
      </div>
    </div>
  );
}

export function ContentScreenView({ manifest, screen, params }: { manifest: Manifest; screen: ContentScreen; params: Record<string, string> }) {
  const api = useApi();
  const text = useText();
  const base = useBaseContext(manifest, params);
  const { t } = useRenderer();
  const src = manifest.sources[screen.source];
  const req = src ? sourceRequest(src, base) : null;
  const q = useQuery({
    queryKey: req ? ['content', manifest.id, screen.source, req.path] : ['content-none'],
    enabled: !!req,
    queryFn: ({ signal }) => runRequest<Record<string, unknown>>(api, req!, signal),
  });
  const raw = q.data ? (screen.path ? getPath(q.data, screen.path) : q.data) : '';
  const html = typeof raw === 'string' ? raw : '';
  return (
    <div className="fx-screen">
      <header className="fx-head"><h1>{text(screen.title ?? manifest.title, base)}</h1></header>
      <div className="fx-body">
        {q.isPending && <div className="fx-skel" />}
        {q.error && <p className="fx-error">{t('states.errorTitle')}</p>}
        {screen.format === 'html' ? <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) }} /> : <p className="fx-pre">{html}</p>}
      </div>
    </div>
  );
}

export function TabsScreenView({ manifest, screen, params }: { manifest: Manifest; screen: TabsScreen; params: Record<string, string> }) {
  const [i, setI] = useState(0);
  const tab = screen.tabs[i] ?? screen.tabs[0];
  return (
    <div className="fx-screen">
      <div className="fx-tabs" role="tablist">
        {screen.tabs.map((item, n) => (
          <button key={item.screen} type="button" role="tab" aria-selected={n === i} className={n === i ? 'on' : ''} onClick={() => setI(n)}>{item.title}</button>
        ))}
      </div>
      {tab && <ManifestScreen key={tab.screen} module={manifest.id} screen={tab.screen} params={params} />}
    </div>
  );
}
