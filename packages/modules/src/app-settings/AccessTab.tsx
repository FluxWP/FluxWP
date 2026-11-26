// "All access" (administrators: every device, session and key) and "App access" (own devices, sessions, API keys,
// desktop app download). One component, `all` picks the scope.
import { useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAccess, FlxAppAccessRevoke, FlxAppDeviceRevoke, FlxAppKeyCreate } from '@fluxwp/graphql/operations';
import type { FlxAppAccessQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { payloadError, Secret, useScreen } from './common';

type Access = FlxAppAccessQuery['flxAppAccess'];
type Row = Access['rows'][number];

export function AccessTab({ all }: { all: boolean }) {
  const { t, told, fail, ask, when } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const vars = { all };
  const q = useFlxQuery(FlxAppAccess, vars);
  const [name, setName] = useState('');
  const [days, setDays] = useState('0');
  const [shown, setShown] = useState<string | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAppAccess) });

  const create = useFlxMutation(FlxAppKeyCreate, {
    onSuccess: (r) => {
      const p = r.flxAppKeyCreate;
      const err = payloadError(p?.errors);
      if (!p?.key || err) return fail(err ? new Error(err) : null);
      setName('');
      setShown(p.key);
      void refresh();
    },
    onError: fail,
  });
  const revoke = useFlxMutation(FlxAppAccessRevoke, {
    onSuccess: (r) => (payloadError(r.flxAppAccessRevoke?.errors) ? fail(null) : (void refresh(), told(t('Removed.')))),
    onError: fail,
  });
  const signOut = useFlxMutation(FlxAppDeviceRevoke, {
    onSuccess: (r) => (payloadError(r.flxAppDeviceRevoke?.errors) ? fail(null) : (void refresh(), told(t('Removed.')))),
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxas-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const d = q.data.flxAppAccess;
  const sessions = d.rows.filter((r) => r.kind === 'SESSION');
  const keys = d.rows.filter((r) => r.kind === 'KEY');
  const win = typeof navigator !== 'undefined' && /Windows/i.test(navigator.userAgent);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (name.trim()) create.mutate({ input: { name: name.trim(), days: Number.parseInt(days, 10) || 0 } });
  };

  const tokens = (rows: Row[]) =>
    !rows.length ? (
      <p className="flxas-empty">{t('Nothing here yet.')}</p>
    ) : (
      <div className="flxas-card flxas-table-wrap">
        <table className="flxas-table">
          <thead>
            <tr>
              {all && <th>{t('User')}</th>}
              <th>{t('Name')}</th>
              <th>{t('Type')}</th>
              <th>{t('Created')}</th>
              <th>{t('Last used')}</th>
              <th>{t('Expires')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                {all && <td data-label={t('User')}>{r.user}</td>}
                <td data-label={t('Name')}>
                  <strong>{r.name}</strong> {r.kind === 'KEY' && <code>{'flxak_…' + r.hint}</code>}
                </td>
                <td data-label={t('Type')}>{r.kind === 'KEY' ? t('API key') : t('App session')}</td>
                <td data-label={t('Created')}>{when(r.created)}</td>
                <td data-label={t('Last used')}>
                  {when(r.lastUsed)} {r.lastIp && <span className="flxas-muted">{r.lastIp}</span>}
                </td>
                <td data-label={t('Expires')}>{r.expires ? when(r.expires) : t('Never')}</td>
                <td>
                  <button type="button" className="flxas-link is-danger" onClick={() => ask({ text: t('Remove this access?'), action: t('Remove'), run: () => revoke.mutate({ input: { id: r.id } }) })}>
                    {t('Remove')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );

  const devices = !d.devices.length ? (
    <p className="flxas-empty">{t('No device is signed in to the app.')}</p>
  ) : (
    <div className="flxas-card flxas-table-wrap">
      <table className="flxas-table">
        <thead>
          <tr>
            {all && <th>{t('User')}</th>}
            <th>{t('Device')}</th>
            <th>{t('Application')}</th>
            <th>{t('Notifications')}</th>
            <th>{t('Last seen')}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {d.devices.map((v) => (
            <tr key={v.id}>
              {all && <td data-label={t('User')}>{v.user}</td>}
              <td data-label={t('Device')}>
                <strong>{v.name}</strong> <span className="flxas-muted">{v.platform}</span>
              </td>
              <td data-label={t('Application')}>
                {v.app} {v.brand && <code>{v.brand}</code>}
              </td>
              <td data-label={t('Notifications')}>
                <span className={'flxas-pf' + (v.push ? ' is-on' : '')}>{v.push ? t('On') : t('Off')}</span>
              </td>
              <td data-label={t('Last seen')}>{v.lastSeenAgo}</td>
              <td>
                <button type="button" className="flxas-link is-danger" onClick={() => ask({ text: t('Sign this device out?'), action: t('Sign out'), run: () => signOut.mutate({ input: { id: v.id } }) })}>
                  {t('Sign out')}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  if (all)
    return (
      <>
        <h2>{t('Devices')}</h2>
        {devices}
        <h2>{t('Sessions and keys')}</h2>
        {tokens(d.rows)}
      </>
    );

  return (
    <>
      {shown && <Secret title={t('Copy it now; it will not be shown again.')} value={shown} onClose={() => setShown(null)} />}
      <p className="flxas-lead">
        {t('In the Flux Desktop app, press "Sign in": you sign in to this site in the browser and approve; the app then works with your permissions. For other tools (MCP clients, scripts) you can create a personal API key.')}
      </p>
      <h2>{t('Desktop app')}</h2>
      <div className="flxas-card">
        <p>{t('Use the panel as a separate app on your Mac or Windows computer. New versions install themselves.')}</p>
        <p className="flxas-row">
          {[
            { os: 'macos', label: t('Download for macOS'), url: d.downloads.macos, primary: !win },
            { os: 'windows', label: t('Download for Windows'), url: d.downloads.windows, primary: win },
          ]
            .sort((a, b) => Number(b.primary) - Number(a.primary))
            .map((b) => (
              <a key={b.os} className={'flxas-btn' + (b.primary ? ' is-primary' : '')} href={b.url}>
                {b.label}
              </a>
            ))}
        </p>
        <p className="flxas-desc">
          {t('macOS 11 or later (Apple Silicon and Intel). Windows 10 and 11 (64-bit).')} <a href={d.downloads.windowsMsi}>{t('Windows MSI package (managed deployment)')}</a>
        </p>
      </div>
      <h2>{t('My devices')}</h2>
      {devices}
      <h2>{t('Connected apps')}</h2>
      {tokens(sessions)}
      <h2>{t('API keys')}</h2>
      {tokens(keys)}
      {d.canCreateKeys && (
        <>
          <form className="flxas-card flxas-row flxas-keyform" onSubmit={submit}>
            <label>
              {t('Name')} <input type="text" required maxLength={100} placeholder={t('e.g. Claude MCP')} value={name} onChange={(e) => setName(e.currentTarget.value)} />
            </label>
            <label>
              {t('Valid for')}{' '}
              <select value={days} onChange={(e) => setDays(e.currentTarget.value)}>
                <option value="0">{t('Never')}</option>
                {[30, 90, 365].map((n) => (
                  <option key={n} value={n}>
                    {t('%d days', n)}
                  </option>
                ))}
              </select>
            </label>
            <button type="submit" className="flxas-btn is-primary" disabled={create.isPending}>
              {t('Create key')}
            </button>
          </form>
          <p className="flxas-desc">{t('Usage: send Authorization: Bearer flxak_… to any REST endpoint and MCP (/wp-json/flx-mcp/v1/mcp). The key acts with your permissions.')}</p>
        </>
      )}
    </>
  );
}
