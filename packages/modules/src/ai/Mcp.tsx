// Flux AI › MCP: switch the server and OAuth, create and revoke keys (shown once), connect an assistant, recent activity.
// Everything goes over the flxAiMcp* operations.
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiMcp, FlxAiMcpKeyCreate, FlxAiMcpKeyRevoke, FlxAiMcpServerUpdate } from '@fluxwp/graphql/operations';
import type { FlxAiMcpOverviewFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Check, Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, errText, useAi, type Notice } from './common';
import { firstError } from './Skills';

type Overview = FlxAiMcpOverviewFieldsFragment;
type Key = Overview['keys'][number];

export function Mcp() {
  const { t, tn, num } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const host = useHost();
  const adminUrl = host.adminUrl ?? '';
  const q = useFlxQuery(FlxAiMcp, {});
  const [notice, setNotice] = useState<Notice | null>(null);
  const [draft, setDraft] = useState<{ enabled: boolean; oauth: boolean } | null>(null);
  const [name, setName] = useState('');
  const [limit, setLimit] = useState<string[]>([]);
  const [fresh, setFresh] = useState('');
  const [asking, setAsking] = useState<Key | null>(null);
  const put = (o: Overview | null | undefined) => {
    if (o) qc.setQueryData(flxQueryKey(scope, FlxAiMcp, {}), { flxAiMcp: o });
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const toggle = useFlxMutation(FlxAiMcpServerUpdate, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiMcpServerUpdate?.errors);
      if (e) return setNotice({ kind: 'error', text: e.text });
      put(r.flxAiMcpServerUpdate?.overview);
      setDraft(null);
      setNotice({ kind: 'success', text: t('Saved.') });
    },
    onError: fail,
  });
  const create = useFlxMutation(FlxAiMcpKeyCreate, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiMcpKeyCreate?.errors);
      if (e) return setNotice({ kind: 'error', text: e.text });
      put(r.flxAiMcpKeyCreate?.overview);
      setFresh(r.flxAiMcpKeyCreate?.key ?? '');
      setName('');
      setLimit([]);
      setNotice(null);
    },
    onError: fail,
  });
  const revoke = useFlxMutation(FlxAiMcpKeyRevoke, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiMcpKeyRevoke?.errors);
      setAsking(null);
      if (e) return setNotice({ kind: 'error', text: e.text });
      put(r.flxAiMcpKeyRevoke?.overview);
      setNotice({ kind: 'success', text: t('Key revoked.') });
    },
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const o = q.data.flxAiMcp;
  const s = o.server;
  const cur = draft ?? { enabled: s.enabled, oauth: s.oauth };
  const dirty = !!draft && (draft.enabled !== s.enabled || draft.oauth !== s.oauth);
  const slug = (() => {
    try {
      return new URL(s.url).hostname.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'fluxwp';
    } catch {
      return 'fluxwp';
    }
  })();
  const keyShow = fresh || t('YOUR_KEY');
  const json = JSON.stringify({ mcpServers: { [slug]: { command: 'npx', args: ['-y', 'mcp-remote', s.url, '--header', 'Authorization:Bearer ' + keyShow] } } }, null, 2);
  const copy = (text: string) => {
    void navigator.clipboard?.writeText(text).then(() => setNotice({ kind: 'success', text: t('Copied.') }));
  };
  const pick = (n: string, on: boolean) => setLimit((l) => (on ? [...l, n] : l.filter((x) => x !== n)));

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t('AI assistants such as Claude connect to this site over MCP (Model Context Protocol). Tools come from the installed FluxWP plugins (SEO, CRM, Mail, Speed…); Flux AI chat uses the same tools.')}</p>
      {s.legacy && <div className="flxai-notice is-warn">{t('An old Flux SEO version is running its own MCP server. Update Flux SEO; keys and the setting move here automatically.')}</div>}
      <Card title={t('1. Server')}>
        <div className="flxai-mcp-switches">
          <Check checked={cur.enabled} onChange={(v) => setDraft({ ...cur, enabled: v })}>
            {t('MCP server enabled')}
          </Check>
          <Check checked={cur.oauth} onChange={(v) => setDraft({ ...cur, oauth: v })}>
            {t('Sign in with OAuth: assistants connect with only the URL; the user signs in to WordPress and approves each connection')}
          </Check>
        </div>
        <p>
          <button type="button" className="flxai-btn is-primary" disabled={!dirty || toggle.isPending} onClick={() => toggle.mutate({ input: cur })}>
            {t('Save')}
          </button>
        </p>
        <p>
          {t('Server URL')}: <code>{s.url}</code> <Pill kind={s.enabled ? 'ok' : undefined}>{s.enabled ? t('on') : t('off')}</Pill> {s.oauthActive && <Pill kind="ok">OAuth</Pill>}
        </p>
        {s.restUrl !== s.url && (
          <p className="flxai-hint">
            {t('The same server also answers at:')} <code>{s.restUrl}</code>
          </p>
        )}
        <p className="flxai-hint">
          {tn('%s tool is registered.', '%s tools are registered.', s.toolCount, num(s.toolCount))} <a href={`${adminUrl}admin.php?page=flx-ai-tools`}>{t('Tools')}</a>
        </p>
      </Card>

      <Card title={t('2. Connections and keys')}>
        <p className="flxai-hint">{t('The assistant works with the permissions of the user who created the key. New content is created as a draft; deleting only moves to trash.')}</p>
        <p className="flxai-hint">
          {t('Who can connect: users with the flxai_mcp capability. Roles that can edit posts have it by default, and administrators always do.')}{' '}
          {s.rolesUrl && <a href={s.rolesUrl}>{t('Roles and access')}</a>}
        </p>
        {fresh && (
          <div className="flxai-notice is-success flxai-mcp-fresh" role="status">
            <div>
              <strong>{t('New key (shown only now, copy it):')}</strong>
              <div>
                <code className="flxai-key">{fresh}</code>
              </div>
            </div>
            <span className="flxai-tc-acts">
              <button type="button" className="flxai-btn is-small" onClick={() => copy(fresh)}>
                {t('Copy')}
              </button>
              <button type="button" className="flxai-x" aria-label={t('Close')} onClick={() => setFresh('')}>
                ×
              </button>
            </span>
          </div>
        )}
        <div className="flxai-rcp-row">
          <input className="flxai-input" value={name} maxLength={80} placeholder={t('e.g. Claude – Alex')} aria-label={t('Name')} onChange={(e) => setName(e.target.value)} />
          <button type="button" className="flxai-btn" disabled={create.isPending} onClick={() => create.mutate({ input: { name, tools: limit } })}>
            {t('Create new key')}
          </button>
        </div>
        <details className="flxai-mcp-scope">
          <summary>{t('Limit tools (optional)')}</summary>
          <p className="flxai-hint">{t("If none is selected the key works with all of the user's permissions. If some are selected it only sees and runs those tools (e.g. only order_summary for a TV order board).")}</p>
          <div className="flxai-mcp-scope-list">
            {o.choices.map((c) => (
              <Check key={c.name} checked={limit.includes(c.name)} onChange={(v) => pick(c.name, v)}>
                <code>{c.name}</code> {c.title} {c.readonly && <Pill>{t('reads')}</Pill>}
              </Check>
            ))}
          </div>
        </details>
        {o.keys.length > 0 && (
          <div className="flxai-tscroll">
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Name')}</th>
                  <th>{t('User')}</th>
                  <th>{t('Tools')}</th>
                  <th>{t('Created')}</th>
                  <th>{t('Last used')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {o.keys.map((k) => (
                  <tr key={k.id}>
                    <td>
                      {k.name} {k.type === 'oauth' ? <Pill>OAuth</Pill> : <code>…{k.hint}</code>}
                    </td>
                    <td>
                      {k.userLogin || '?'} {!k.canUse && <Pill kind="err">{t('No MCP access')}</Pill>}
                    </td>
                    <td>{k.tools.length ? <code>{k.tools.join(', ')}</code> : t('All')}</td>
                    <td>{k.created.label}</td>
                    <td>{k.lastUsed?.label ?? '—'}</td>
                    <td>
                      <button type="button" className="flxai-link is-danger" onClick={() => setAsking(k)}>
                        {t('Revoke')}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <Card title={t('3. Connect an assistant')}>
        {s.oauthActive && (
          <>
            <p>
              <strong>Claude.ai, Claude Desktop, ChatGPT, Cursor, VS Code</strong> — {t('add a custom connector / MCP server with only this URL. You will sign in to WordPress and approve; no key needed:')}
            </p>
            <pre className="flxai-code">{s.url}</pre>
            <p className="flxai-hint">{t('With a key instead (clients without OAuth, devices, scripts):')}</p>
          </>
        )}
        <p>
          <strong>Claude Desktop / Cursor</strong> (claude_desktop_config.json › mcpServers):
        </p>
        <pre className="flxai-code">{json}</pre>
        <p>
          <strong>Claude Code</strong>:
        </p>
        <pre className="flxai-code">{`claude mcp add --transport http ${slug} ${s.url} --header "Authorization: Bearer ${keyShow}"`}</pre>
        <p>
          <strong>{t('Other clients')}</strong> — {t('Send the key in the Authorization: Bearer header, or in the X-MCP-Key header. Do not put the key in the URL.')}
        </p>
        <pre className="flxai-code">{'Authorization: Bearer ' + keyShow}</pre>
        <p className="flxai-hint">{t('On Apache or LiteSpeed, if you see "Invalid key", add SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1 to .htaccess.')}</p>
      </Card>

      {o.log.length > 0 && (
        <Card title={t('Recent activity')}>
          <div className="flxai-tscroll">
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Time')}</th>
                  <th>{t('User')}</th>
                  <th>{t('Tool')}</th>
                  <th>{t('Target')}</th>
                  <th>{t('Result')}</th>
                </tr>
              </thead>
              <tbody>
                {o.log.map((l, i) => (
                  <tr key={i}>
                    <td>{l.time.label}</td>
                    <td>{l.userLogin || '?'}</td>
                    <td>
                      <code>{l.tool}</code>
                    </td>
                    <td>{l.target}</td>
                    <td>
                      <Pill kind={l.ok ? 'ok' : 'err'}>{l.ok ? '✓' : '✗'}</Pill>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {asking && (
        <Modal title={t('Revoke')} onClose={() => setAsking(null)}>
          <p>{t('Revoke "%s"? Assistants using it stop working.', asking.name)}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxai-btn is-danger" disabled={revoke.isPending} onClick={() => revoke.mutate({ input: { id: asking.id } })}>
              {t('Revoke')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}
