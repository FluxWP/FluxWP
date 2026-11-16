// Application › OAuth clients: list, add (with an optional client secret shown once), delete, connection details.
import { useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAdminClientCreate, FlxAppAdminClientDelete, FlxAppAdminClients } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { Check, payloadError, Secret, useScreen } from './common';

export function ClientsTab() {
  const { t, told, fail, ask } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAppAdminClients, {});
  const [name, setName] = useState('');
  const [uris, setUris] = useState('');
  const [secretOn, setSecretOn] = useState(false);
  const [shown, setShown] = useState<string | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAppAdminClients) });

  const create = useFlxMutation(FlxAppAdminClientCreate, {
    onSuccess: (r) => {
      const p = r.flxAppAdminClientCreate;
      const err = payloadError(p?.errors);
      if (!p || err) return fail(err ? new Error(err) : null);
      setName('');
      setUris('');
      setSecretOn(false);
      setShown(p.secret && p.client ? p.client.id + '\n' + p.secret : null);
      void refresh();
      told(t('Saved.'));
    },
    onError: fail,
  });
  const remove = useFlxMutation(FlxAppAdminClientDelete, {
    onSuccess: (r) => {
      const err = payloadError(r.flxAppAdminClientDelete?.errors);
      if (err || !r.flxAppAdminClientDelete?.ok) return fail(err ? new Error(err) : null);
      void refresh();
      told(t('Saved.'));
    },
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const d = q.data.flxAppAdminClients;
  const kindLabel = (k: string, secret: boolean) => {
    const base = k === 'BUILTIN' ? t('Built-in') : k === 'DYNAMIC' ? t('Self-registered (MCP)') : t('Added manually');
    return secret ? base + ' · ' + t('with secret') : base;
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    create.mutate({ input: { name: name.trim(), redirectUris: uris.split(/[\r\n]+/).map((u) => u.trim()).filter(Boolean), confidential: secretOn } });
  };

  return (
    <>
      {shown && <Secret title={t('Copy it now; it will not be shown again.')} value={shown} onClose={() => setShown(null)} />}
      <div className="flxs-card flxs-table-wrap">
        <table className="flxs-table">
          <thead>
            <tr>
              <th>{t('Application')}</th>
              <th>client_id</th>
              <th>{t('Redirect URIs')}</th>
              <th>{t('Type')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {d.clients.map((c) => (
              <tr key={c.id}>
                <td data-label={t('Application')}>
                  <strong>{c.name}</strong>
                </td>
                <td data-label="client_id">
                  <code>{c.id}</code>
                </td>
                <td data-label={t('Redirect URIs')}>
                  {c.redirectUris.map((u) => (
                    <div key={u}>{u}</div>
                  ))}
                </td>
                <td data-label={t('Type')}>{kindLabel(c.kind, c.hasSecret)}</td>
                <td>
                  {c.kind !== 'BUILTIN' && (
                    <button
                      type="button"
                      className="flxs-link is-danger"
                      onClick={() => ask({ text: t('Delete the application and all of its sessions?'), action: t('Delete'), run: () => remove.mutate({ input: { id: c.id } }) })}
                    >
                      {t('Delete')}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2>{t('Add application')}</h2>
      <form className="flxs-card" onSubmit={submit}>
        <p>
          <label htmlFor="flxs-cn">{t('Name')}</label>
          <br />
          <input id="flxs-cn" type="text" required maxLength={80} className="flxs-wide" value={name} onChange={(e) => setName(e.currentTarget.value)} />
        </p>
        <p>
          <label htmlFor="flxs-cu">{t('Redirect URIs (one per line)')}</label>
          <br />
          <textarea id="flxs-cu" rows={3} required className="flxs-wide" placeholder="https://ornek.com/oauth/callback" value={uris} onChange={(e) => setUris(e.currentTarget.value)} />
        </p>
        <p>
          <Check checked={secretOn} onChange={setSecretOn}>
            {t('Generate a client secret (for server-side apps; not needed for desktop/mobile)')}
          </Check>
        </p>
        <button type="submit" className="flxs-btn is-primary" disabled={create.isPending}>
          {t('Add')}
        </button>
      </form>

      <h2>{t('Connection details')}</h2>
      <div className="flxs-card">
        <dl className="flxs-meta">
          {d.connection.map((c) => (
            <div key={c.key}>
              <dt>{c.key}</dt>
              <dd>
                <code>{c.label}</code>
              </dd>
            </div>
          ))}
        </dl>
      </div>
    </>
  );
}
