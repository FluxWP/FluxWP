// Flux AI › Connections: external MCP servers this site uses (add, change, sign in, test, per-tool permissions).
// Same features as the classic page; everything goes over the flxAiConnection* operations. Secrets are write-only:
// the form only shows whether one is saved, and an empty field keeps it.
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import {
  FlxAiConnectionDelete,
  FlxAiConnectionPermissions,
  FlxAiConnectionRefresh,
  FlxAiConnections,
  FlxAiConnectionSave,
  FlxAiConnectionSignIn,
  FlxAiConnectionSignOut,
} from '@fluxwp/graphql/operations';
import type { FlxAiConnectionFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, NoticeBar, Pill, errText, useAi, type AiT, type Notice } from './common';
import { Field, firstError } from './Skills';

type Conn = FlxAiConnectionFieldsFragment;
type Screen = { roles: ReadonlyArray<{ id: string; label: string }>; callbackUrl: string; connections: ReadonlyArray<Conn> };

/** The OAuth return and some classic links pass the screen and a message in the address. */
function fromUrl(t: AiT['t']): { edit: string | null; notice: Notice | null } {
  const p = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
  const edit = p.get('edit');
  const err = p.get('flxai_err');
  const ok = p.get('flxai_ok');
  const msgs: Record<string, string> = {
    saved: t('Saved.'),
    deleted: t('Connection deleted.'),
    refreshed: t('Tool list updated.'),
    connected: t("Signed in. The server's tools are listed below."),
    signedout: t('Signed out.'),
    needs_signin: t('Saved. Now sign in with "Connect".'),
  };
  let text = '';
  if (err) {
    try {
      text = decodeURIComponent(err);
    } catch {
      text = err;
    }
  }
  return { edit: edit || null, notice: err ? { kind: 'error', text } : ok && msgs[ok] ? { kind: 'success', text: msgs[ok] } : null };
}

export function Connections() {
  const { t } = useAi();
  const [init] = useState(() => fromUrl(t));
  const [view, setView] = useState<string | null>(init.edit); // null = list, 'new' = add form, else an id
  const [notice, setNotice] = useState<Notice | null>(init.notice);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAiConnections, {});
  const key = flxQueryKey(scope, FlxAiConnections, {});
  const upsert = (c: Conn | null | undefined) => {
    if (!c) return;
    qc.setQueryData(key, (old: { flxAiConnections: Screen } | undefined) => {
      if (!old) return old;
      const list = old.flxAiConnections.connections;
      const has = list.some((x) => x.id === c.id);
      return { flxAiConnections: { ...old.flxAiConnections, connections: has ? list.map((x) => (x.id === c.id ? c : x)) : [...list, c] } };
    });
  };
  const drop = (id: string) =>
    qc.setQueryData(key, (old: { flxAiConnections: Screen } | undefined) => (old ? { flxAiConnections: { ...old.flxAiConnections, connections: old.flxAiConnections.connections.filter((x) => x.id !== id) } } : old));

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const o = q.data.flxAiConnections;
  const conn = view && view !== 'new' ? o.connections.find((c) => c.id === view) : undefined;
  const common = { screen: o, notice, setNotice, upsert, drop, go: setView };
  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t('Connect external MCP servers (for example a CRM, a project tool or another site). Their tools become available in chat, scheduled tasks and agents. Only remote servers over HTTPS work here; local (stdio) servers need a computer that runs them.')}</p>
      {view === null ? (
        <List {...common} />
      ) : view === 'new' || conn ? (
        <Edit key={view} conn={conn} {...common} />
      ) : (
        <Card>
          <p>{t('Connection not found.')}</p>
          <button type="button" className="flxai-btn" onClick={() => setView(null)}>
            {t('Back')}
          </button>
        </Card>
      )}
    </>
  );
}

interface Common {
  screen: Screen;
  notice: Notice | null;
  setNotice: (n: Notice | null) => void;
  upsert: (c: Conn | null | undefined) => void;
  drop: (id: string) => void;
  go: (v: string | null) => void;
}

function StatusPill({ c }: { c: Conn }) {
  const { t } = useAi();
  if (c.status === 'off') return <Pill>{t('Off')}</Pill>;
  if (c.status === 'error') return <span title={c.error}><Pill kind="err">{t('Error')}</Pill></span>;
  if (c.status === 'signin') return <Pill kind="warn">{t('Sign-in needed')}</Pill>;
  return <Pill kind="ok">{t('Connected')}</Pill>;
}

function authLabels(t: AiT['t']): Record<string, string> {
  return { none: t('None'), bearer: t('API key / token (Bearer)'), header: t('Custom header'), oauth: t('Sign in (OAuth 2.1)') };
}

function List({ screen, go }: Common) {
  const { t, num } = useAi();
  const auth = authLabels(t);
  return (
    <Card
      title={
        <span className="flxai-card-head">
          {t('MCP servers')}
          <span className="flxai-card-acts">
            <button type="button" className="flxai-btn is-primary" onClick={() => go('new')}>
              {t('Add connection')}
            </button>
          </span>
        </span>
      }
    >
      {screen.connections.length === 0 ? (
        <p className="flxai-hint">{t('No connections yet.')}</p>
      ) : (
        <div className="flxai-tscroll">
          <table className="flxai-table flxai-mcpc-list">
            <thead>
              <tr>
                <th>{t('Name')}</th>
                <th>{t('Address')}</th>
                <th>{t('Sign-in')}</th>
                <th>{t('Tools')}</th>
                <th>{t('Status')}</th>
              </tr>
            </thead>
            <tbody>
              {screen.connections.map((c) => (
                <tr key={c.id}>
                  <td>
                    <button type="button" className="flxai-link" onClick={() => go(c.id)}>
                      <strong>{c.name}</strong>
                    </button>
                    <br />
                    <code>{c.prefix}</code> <span className="flxai-hint">{c.scope === 'shared' ? t('Shared') : t('Personal')}</span>
                  </td>
                  <td>
                    <code>{c.url}</code>
                  </td>
                  <td>{auth[c.auth] ?? c.auth}</td>
                  <td>{t('%1$s of %2$s', num(c.toolsOn), num(c.tools.length))}</td>
                  <td>
                    <StatusPill c={c} />
                    {c.checked && (
                      <>
                        <br />
                        <span className="flxai-hint">{c.checked.label}</span>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function Edit({ conn, screen, setNotice, upsert, drop, go }: Common & { conn?: Conn }) {
  const { t } = useAi();
  const [f, setF] = useState({
    name: conn?.name ?? '',
    url: conn?.url ?? '',
    auth: conn?.auth ?? 'none',
    header: conn?.header ?? '',
    secret: '',
    scope: conn?.scope ?? 'personal',
    roles: [...(conn?.roles ?? [])],
    enabled: conn?.enabled ?? true,
    clientId: conn?.clientId ?? '',
    clientSecret: '',
  });
  const [errField, setErrField] = useState('');
  const [asking, setAsking] = useState(false);
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((x) => ({ ...x, [k]: v }));
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const readonly = !!conn && !conn.editable;
  const done = (c: Conn | null | undefined, errors: ReadonlyArray<{ message: string; field?: string | null }> | undefined, ok: string) => {
    const e = firstError(errors);
    if (e) {
      setErrField(e.field ?? '');
      return setNotice({ kind: 'error', text: e.text });
    }
    setErrField('');
    upsert(c);
    if (c && c.error) setNotice({ kind: 'error', text: c.error });
    else setNotice({ kind: 'success', text: ok });
    return c;
  };
  const save = useFlxMutation(FlxAiConnectionSave, {
    onSuccess: (r) => {
      const c = done(r.flxAiConnectionSave?.connection, r.flxAiConnectionSave?.errors, t('Saved.'));
      if (!c) return;
      if (c.status === 'signin' && !c.error) setNotice({ kind: 'success', text: t('Saved. Now sign in with "Connect".') });
      setF((x) => ({ ...x, secret: '', clientSecret: '' }));
      if (!conn) go(c.id);
    },
    onError: fail,
  });
  const refresh = useFlxMutation(FlxAiConnectionRefresh, { onSuccess: (r) => done(r.flxAiConnectionRefresh?.connection, r.flxAiConnectionRefresh?.errors, t('Tool list updated.')), onError: fail });
  const signOut = useFlxMutation(FlxAiConnectionSignOut, { onSuccess: (r) => done(r.flxAiConnectionSignOut?.connection, r.flxAiConnectionSignOut?.errors, t('Signed out.')), onError: fail });
  const signIn = useFlxMutation(FlxAiConnectionSignIn, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiConnectionSignIn?.errors);
      if (e) return setNotice({ kind: 'error', text: e.text });
      const url = r.flxAiConnectionSignIn?.url;
      if (url) window.location.href = url; // the provider's sign-in page; it returns to this screen
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxAiConnectionDelete, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiConnectionDelete?.errors);
      setAsking(false);
      if (e) return setNotice({ kind: 'error', text: e.text });
      if (conn) drop(conn.id);
      setNotice({ kind: 'success', text: t('Connection deleted.') });
      go(null);
    },
    onError: fail,
  });
  const busy = save.isPending || refresh.isPending || signOut.isPending || signIn.isPending;
  const auth = authLabels(t);
  const submit = () =>
    save.mutate({
      input: {
        ...(conn ? { id: conn.id } : {}),
        name: f.name,
        url: f.url,
        auth: f.auth,
        header: f.header,
        secret: f.secret,
        scope: f.scope,
        roles: f.roles,
        enabled: f.enabled,
        clientId: f.clientId,
        clientSecret: f.clientSecret,
      },
    });

  return (
    <>
      <Card title={conn ? conn.name : t('Add connection')}>
        {readonly && <div className="flxai-notice is-warn">{t("This is another user's personal connection.")}</div>}
        {conn && conn.serverName && <p className="flxai-hint">{t('Server: %1$s %2$s · protocol %3$s', conn.serverName, conn.serverVersion, conn.serverProtocol)}</p>}
        <Field label={t('Name')}>
          <input className="flxai-input" value={f.name} placeholder="Linear" disabled={readonly} onChange={(e) => set('name', e.target.value)} />
        </Field>
        <Field label={t('Server address')} hint={t('The Streamable HTTP address the provider gives for MCP clients.')}>
          <input className={'flxai-input code' + (errField === 'url' ? ' is-error' : '')} type="url" value={f.url} placeholder="https://mcp.example.com/mcp" disabled={readonly} onChange={(e) => set('url', e.target.value)} />
        </Field>
        <Field label={t('Sign-in')}>
          <div className="flxai-mcpc-auth">
            {Object.entries(auth).map(([k, label]) => (
              <label key={k} className="flxai-check">
                <input type="radio" name="auth" checked={f.auth === k} disabled={readonly} onChange={() => set('auth', k)} /> {label}
              </label>
            ))}
          </div>
        </Field>
        {f.auth === 'header' && (
          <Field label={t('Header name')}>
            <input className="flxai-input code" value={f.header} placeholder="X-API-Key" disabled={readonly} onChange={(e) => set('header', e.target.value)} />
          </Field>
        )}
        {(f.auth === 'bearer' || f.auth === 'header') && (
          <Field label={t('Key / token')} hint={t('Stored encrypted; never shown to the AI.')}>
            <input className="flxai-input code" type="password" autoComplete="off" value={f.secret} placeholder={conn?.hasSecret ? t('Saved (leave empty to keep)') : ''} disabled={readonly} onChange={(e) => set('secret', e.target.value)} />
          </Field>
        )}
        {f.auth === 'oauth' && (
          <Field
            label={t('OAuth client')}
            hint={t('Leave empty: Flux AI registers itself automatically when the server allows it. Otherwise create an OAuth app at the provider with this redirect address:') + ' ' + screen.callbackUrl}
          >
            <input className="flxai-input code" value={f.clientId} placeholder={t('Client ID (optional)')} disabled={readonly} onChange={(e) => set('clientId', e.target.value)} />
            <input className="flxai-input code" type="password" autoComplete="off" value={f.clientSecret} placeholder={conn?.hasClientSecret ? t('Saved (leave empty to keep)') : t('Client secret (optional)')} disabled={readonly} onChange={(e) => set('clientSecret', e.target.value)} />
          </Field>
        )}
        <Field label={t('Who can use it')} hint={t('Agents and scheduled tasks run as their owner, so they can use the connections that user can use.')}>
          <div className="flxai-mcpc-auth">
            <label className="flxai-check">
              <input type="radio" name="scope" checked={f.scope === 'personal'} disabled={readonly} onChange={() => set('scope', 'personal')} /> {t('Only me (personal)')}
            </label>
            <label className="flxai-check">
              <input type="radio" name="scope" checked={f.scope === 'shared'} disabled={readonly} onChange={() => set('scope', 'shared')} /> {t('Shared: administrators and these roles')}
            </label>
            {f.scope === 'shared' && (
              <div className="flxai-mcpc-roles">
                {screen.roles.map((r) => (
                  <label key={r.id} className="flxai-check">
                    <input type="checkbox" checked={f.roles.includes(r.id)} disabled={readonly} onChange={(e) => set('roles', e.target.checked ? [...f.roles, r.id] : f.roles.filter((x) => x !== r.id))} /> {r.label}
                  </label>
                ))}
              </div>
            )}
          </div>
        </Field>
        <Field label={t('Active')}>
          <label className="flxai-check">
            <input type="checkbox" checked={f.enabled} disabled={readonly} onChange={(e) => set('enabled', e.target.checked)} /> {t("Offer this server's tools")}
          </label>
        </Field>
        <div className="flxai-appr-acts">
          {!readonly && (
            <button type="button" className="flxai-btn is-primary" disabled={busy} onClick={submit}>
              {t('Save')}
            </button>
          )}
          {conn && !readonly && conn.auth === 'oauth' && (
            <button type="button" className="flxai-btn" disabled={busy} onClick={() => signIn.mutate({ input: { id: conn.id } })}>
              {conn.signedIn ? t('Sign in again') : t('Connect')}
            </button>
          )}
          {conn && !readonly && conn.auth === 'oauth' && conn.signedIn && (
            <button type="button" className="flxai-btn" disabled={busy} onClick={() => signOut.mutate({ input: { id: conn.id } })}>
              {t('Sign out')}
            </button>
          )}
          {conn && !readonly && (
            <button type="button" className="flxai-btn" disabled={busy} onClick={() => refresh.mutate({ input: { id: conn.id } })}>
              {t('Test and update tools')}
            </button>
          )}
          {conn && !readonly && (
            <button type="button" className="flxai-btn is-danger" onClick={() => setAsking(true)}>
              {t('Delete')}
            </button>
          )}
          <button type="button" className="flxai-btn" onClick={() => go(null)}>
            {t('Back')}
          </button>
        </div>
      </Card>
      {conn && <ToolsCard conn={conn} readonly={readonly} upsert={upsert} setNotice={setNotice} />}
      {asking && conn && (
        <Modal title={t('Delete')} onClose={() => setAsking(false)}>
          <p>{t('Delete the connection "%s"? Its tools disappear from chats, tasks and agents.', conn.name)}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxai-btn is-danger" disabled={del.isPending} onClick={() => del.mutate({ input: { id: conn.id } })}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function ToolsCard({ conn, readonly, upsert, setNotice }: { conn: Conn; readonly: boolean; upsert: Common['upsert']; setNotice: Common['setNotice'] }) {
  const { t } = useAi();
  const [perms, setPerms] = useState<Record<string, string>>(() => Object.fromEntries(conn.tools.map((x) => [x.name, x.permission])));
  const save = useFlxMutation(FlxAiConnectionPermissions, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiConnectionPermissions?.errors);
      if (e) return setNotice({ kind: 'error', text: e.text });
      upsert(r.flxAiConnectionPermissions?.connection);
      setNotice({ kind: 'success', text: t('Saved.') });
    },
    onError: (e) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  return (
    <Card title={t('Tools')}>
      {conn.error && <div className="flxai-notice is-warn">{conn.error}</div>}
      {conn.tools.length === 0 ? (
        <p className="flxai-hint">{t('No tools listed yet. Save the connection (and sign in) to load them.')}</p>
      ) : (
        <>
          <p className="flxai-hint">{t('Allowed: runs without asking (only tools the server marks read-only). Ask first: shows an approval card. Off: never offered. Tools that change data always ask.')}</p>
          <div className="flxai-tscroll">
            <table className="flxai-table flxai-mcpc-perm">
              <thead>
                <tr>
                  <th>{t('Tool')}</th>
                  <th>{t('Allowed')}</th>
                  <th>{t('Ask first')}</th>
                  <th>{t('Off')}</th>
                </tr>
              </thead>
              <tbody>
                {conn.tools.map((x) => (
                  <tr key={x.name}>
                    <td>
                      <strong>{x.title}</strong> <code>{x.toolName}</code>{' '}
                      {x.readonly ? <Pill kind="ok">{t('Read-only')}</Pill> : x.destructive ? <Pill kind="err">{t('Destructive')}</Pill> : <Pill kind="warn">{t('Changes data')}</Pill>}
                      <div className="flxai-mcpc-tool-desc">{x.description.length > 220 ? x.description.slice(0, 220) + '…' : x.description}</div>
                    </td>
                    {(['allow', 'ask', 'off'] as const).map((k) => (
                      <td key={k}>
                        <input type="radio" name={'perm-' + x.name} aria-label={k} checked={(perms[x.name] ?? 'ask') === k} disabled={readonly || (k === 'allow' && !x.readonly)} onChange={() => setPerms((p) => ({ ...p, [x.name]: k }))} />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!readonly && (
            <p>
              <button type="button" className="flxai-btn is-primary" disabled={save.isPending} onClick={() => save.mutate({ input: { id: conn.id, permissions: conn.tools.map((x) => ({ tool: x.name, permission: perms[x.name] ?? 'ask' })) } })}>
                {t('Save permissions')}
              </button>
            </p>
          )}
        </>
      )}
    </Card>
  );
}
