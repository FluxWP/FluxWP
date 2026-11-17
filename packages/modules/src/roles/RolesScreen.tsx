// Flux Roles › Roles and access. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and
// write goes through the Flux Roles GraphQL operations (plugins/flx-roles/graphql/operations).
// It has everything the classic page has: role cards, new role, the permission table, screen after
// login, referral link lifetime, reset to defaults, people (WhatsApp number, referral link, add/remove).
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxRoleCreate,
  FlxRoleDelete,
  FlxRoleMemberAdd,
  FlxRoleMemberRemove,
  FlxRoleMembers,
  FlxRoleReset,
  FlxRolesSave,
  FlxRolesScreen,
} from '@fluxwp/graphql/operations';
import type { FlxRoleMemberRowFragment, FlxRolesScreenQuery } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './roles.css';

type Screen = FlxRolesScreenQuery;
type Role = Screen['flxRoles'][number];
type Notice = { kind: 'success' | 'error'; text: string };
type Confirm = { text: string; action: string; run: () => void };

interface Draft {
  caps: Record<string, Set<string>>;
  home: Record<string, string>;
  days: string;
}

const draftOf = (d: Screen): Draft => ({
  caps: Object.fromEntries(d.flxRoles.map((r) => [r.key, new Set(r.capabilities)])),
  home: Object.fromEntries(d.flxRoles.map((r) => [r.key, r.home])),
  days: String(d.flxRolesConfig.referralDays),
});

export function RolesScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Roles />
    </FlxGraphQLProvider>
  );
}

function Roles() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxRolesScreen, {});
  const [draft, setDraft] = useState<Draft | null>(() => (q.data ? draftOf(q.data) : null));
  const [dirty, setDirty] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);

  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data));
  }, [q.data, dirty]);

  const refresh = (keepDraft: boolean) => {
    if (!keepDraft) setDirty(false);
    return qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxRolesScreen) });
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
  const told = (text: string) => {
    setNotice({ kind: 'success', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };

  const save = useFlxMutation(FlxRolesSave, {
    onSuccess: (r) => {
      const p = r.flxRolesSave;
      if (!p) return fail(null);
      if (p.errors.length) return setNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      qc.setQueryData<Screen>(flxQueryKey(scope, FlxRolesScreen, {}), (old) =>
        old && { ...old, flxRoles: old.flxRoles.map((o) => ({ ...o, ...p.roles.find((n) => n.key === o.key) })), flxRolesConfig: p.config },
      );
      setDirty(false);
      told(t('Saved. Permission changes apply immediately.'));
    },
    onError: fail,
  });
  const reset = useFlxMutation(FlxRoleReset, {
    onSuccess: (r) => (r.flxRoleReset?.errors.length !== 0 ? fail(new Error(r.flxRoleReset?.errors[0]?.message)) : (void refresh(false), told(t('The role is back to its default permissions.')))),
    onError: fail,
  });
  const create = useFlxMutation(FlxRoleCreate, {
    onSuccess: (r) => (r.flxRoleCreate?.errors.length !== 0 ? fail(new Error(r.flxRoleCreate?.errors[0]?.message)) : (void refresh(false), told(t('Role added; it starts with admin panel access only. Turn on its permissions in the table.')))),
    onError: fail,
  });
  const remove = useFlxMutation(FlxRoleDelete, {
    onSuccess: (r) => (r.flxRoleDelete?.errors.length !== 0 ? fail(new Error(r.flxRoleDelete?.errors[0]?.message)) : (void refresh(false), told(t('Role deleted; its people moved to the default role.')))),
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxr-app"><div className="flxr-notice is-error">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const d = q.data;
  const roles = d.flxRoles;
  const busy = save.isPending || reset.isPending;
  const toggle = (role: string, cap: string, on: boolean) => {
    setDraft((old) => {
      if (!old) return old;
      const caps = new Set(old.caps[role]);
      if (on) caps.add(cap);
      else caps.delete(cap);
      return { ...old, caps: { ...old.caps, [role]: caps } };
    });
    setDirty(true);
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const days = Number.parseInt(draft.days, 10);
    save.mutate({
      input: {
        roles: roles.map((r) => ({ key: r.key, capabilities: [...(draft.caps[r.key] ?? [])], home: draft.home[r.key] ?? 'auto' })),
        referralDays: Number.isFinite(days) ? Math.max(1, Math.min(365, days)) : 30,
      },
    });
  };

  return (
    <div className="flxr-app">
      <div className="flxr-head">
        <h1>{t('Roles and access')}</h1>
        {host.classicUrl && (
          <a className="flxr-classic" href={host.classicUrl}>
            {t('Classic view')}
          </a>
        )}
      </div>
      {notice && (
        <div className={'flxr-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxr-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      <p className="flxr-lead">{t("Turn on and off what each role can access. A disabled action disappears from the menu and can't be done even by URL. The WordPress Administrator sees everything.")}</p>

      <div className="flxr-roles">
        {roles.map((r) => (
          <div key={r.key} className="flxr-card flxr-role">
            <h2>
              {r.label} <span className="flxr-count">{tn('%s person', '%s people', r.memberCount, r.memberCount.toLocaleString(i18n.language))}</span>
            </h2>
            <p>{r.description}</p>
            {r.custom && (
              <button
                type="button"
                className="flxr-remove"
                onClick={() => setConfirm({ text: t('Delete this role? Its people move to the default role.'), action: t('Delete role'), run: () => remove.mutate({ input: { key: r.key } }) })}
              >
                {t('Delete role')}
              </button>
            )}
          </div>
        ))}
        <NewRole t={t} busy={create.isPending} onAdd={(name) => create.mutate({ input: { name } })} />
      </div>

      <form onSubmit={submit}>
        <div className="flxr-card flxr-matrix-wrap">
          <table className="flxr-matrix">
            <thead>
              <tr>
                <th scope="col">{t('Access')}</th>
                {roles.map((r) => (
                  <th key={r.key} scope="col" className="flxr-col">
                    {r.label}
                  </th>
                ))}
              </tr>
            </thead>
            {d.flxRoleCapabilityGroups.map((g) => (
              <tbody key={g.key} className={g.active ? undefined : 'is-off'}>
                <tr className="flxr-group">
                  <th colSpan={roles.length + 1} scope="rowgroup">
                    {g.label}
                    {!g.active && g.plugin && <span className="flxr-badge"> {t('%s is not installed', g.plugin)}</span>}
                  </th>
                </tr>
                {g.capabilities.map((c) => (
                  <tr key={c.key}>
                    <th scope="row">
                      <span className="flxr-label">{c.label}</span>
                      {c.pending && (
                        <span className="flxr-badge is-wait" title={t('The setting is saved; it takes effect once the plugin is updated to a version that enforces this permission.')}>
                          {t('Waiting for %s update', g.plugin ?? '')}
                        </span>
                      )}
                      {c.broad && <span className="flxr-badge is-warn">{t('broad access')}</span>}
                      <code>{c.key}</code>
                    </th>
                    {roles.map((r) => (
                      <td key={r.key} className="flxr-col">
                        <label className="flxr-switch">
                          <input
                            type="checkbox"
                            checked={draft.caps[r.key]?.has(c.key) ?? false}
                            disabled={c.locked}
                            onChange={(e) => toggle(r.key, c.key, e.currentTarget.checked)}
                          />
                          <span aria-hidden="true" />
                          <span className="flxr-sr">{r.label + ': ' + c.label}</span>
                        </label>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            ))}
          </table>
        </div>

        <div className="flxr-card">
          <h2>{t('Settings')}</h2>
          <div className="flxr-form">
            {roles.map((r) => (
              <div key={r.key} className="flxr-field">
                <label htmlFor={'flxr-home-' + r.key}>{t('%s: screen after login', r.label)}</label>
                <select
                  id={'flxr-home-' + r.key}
                  value={draft.home[r.key] ?? 'auto'}
                  onChange={(e) => {
                    const v = e.currentTarget.value;
                    setDraft((o) => o && { ...o, home: { ...o.home, [r.key]: v } });
                    setDirty(true);
                  }}
                >
                  {d.flxRolesConfig.homeChoices.map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </div>
            ))}
            <div className="flxr-field">
              <label htmlFor="flxr-days">{t('Referral link lifetime')}</label>
              <div>
                <input
                  id="flxr-days"
                  type="number"
                  min={1}
                  max={365}
                  className="flxr-days"
                  value={draft.days}
                  onChange={(e) => {
                    const v = e.currentTarget.value;
                    setDraft((o) => o && { ...o, days: v });
                    setDirty(true);
                  }}
                />{' '}
                {t('days')}
                <p className="flxr-desc">{t('If a visitor who came through a referral link donates within this period, the donation is credited to that person. If they come through another referral link, the latest one wins.')}</p>
              </div>
            </div>
          </div>
        </div>

        <div className="flxr-actions">
          <button type="submit" className="flxr-btn is-primary" disabled={busy}>
            {t('Save')}
          </button>
          {dirty && <span className="flxr-dirty">{t('Unsaved changes')}</span>}
          {roles
            .filter((r) => !r.custom)
            .map((r) => (
              <button
                key={r.key}
                type="button"
                className="flxr-btn"
                disabled={busy}
                onClick={() => setConfirm({ text: t("Reset this role's permissions to the defaults?"), action: t('%s: reset to defaults', r.label), run: () => reset.mutate({ input: { key: r.key } }) })}
              >
                {t('%s: reset to defaults', r.label)}
              </button>
            ))}
        </div>
      </form>

      <h2 id="flxr-people" className="flxr-h2">
        {t('People')}
      </h2>
      <div className="flxr-roles">
        {roles.map((r) => (
          <People key={r.key} role={r} t={t} onNotice={setNotice} onConfirm={setConfirm} onChanged={() => refresh(true)} />
        ))}
      </div>
      <p className="flxr-muted">
        <Linked text={t('For a new person, first <a href="%s">create a user</a> and choose one of these roles.')} href={d.flxRolesConfig.newUserUrl} />
        {d.flxRolesConfig.permissionsUrl && (
          <>
            {' '}
            <Linked text={t('For every permission of every role, see <a href="%s">Admin theme › Permissions</a>.')} href={d.flxRolesConfig.permissionsUrl} />
          </>
        )}
      </p>

      {confirm && (
        <Modal title={confirm.action} onClose={() => setConfirm(null)}>
          <p className="flxr-confirm">{confirm.text}</p>
          <div className="flxr-modal-actions">
            <button type="button" className="flxr-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxr-btn is-danger"
              onClick={() => {
                confirm.run();
                setConfirm(null);
              }}
            >
              {confirm.action}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

/** A translated sentence with one <a href="%s">…</a> link. */
function Linked({ text, href }: { text: string; href: string }) {
  const m = /^(.*)<a href="%s">(.*)<\/a>(.*)$/s.exec(text);
  // A translation without the link markup is shown as text; React escapes it, nothing is parsed as HTML.
  if (!m) return <>{text.split('<a href="%s">').join('').split('</a>').join('')}</>;
  return (
    <>
      {m[1]}
      <a href={href}>{m[2]}</a>
      {m[3]}
    </>
  );
}

function NewRole({ t, busy, onAdd }: { t: Translate; busy: boolean; onAdd: (name: string) => void }) {
  const [name, setName] = useState('');
  return (
    <div className="flxr-card flxr-role flxr-newrole">
      <h2>{t('New role')}</h2>
      <p>{t('For example Volunteer or Accounting. Starts with admin panel access only.')}</p>
      <form
        className="flxr-add"
        onSubmit={(e) => {
          e.preventDefault();
          if (name.trim()) {
            onAdd(name.trim());
            setName('');
          }
        }}
      >
        <label className="flxr-sr" htmlFor="flxr-newrole">
          {t('Role name')}
        </label>
        <input id="flxr-newrole" type="text" maxLength={40} required placeholder={t('Role name')} value={name} onChange={(e) => setName(e.currentTarget.value)} />
        <button type="submit" className="flxr-btn" disabled={busy}>
          {t('Add role')}
        </button>
      </form>
    </div>
  );
}

function People({
  role,
  t,
  onNotice,
  onConfirm,
  onChanged,
}: {
  role: Role;
  t: Translate;
  onNotice: (n: Notice) => void;
  onConfirm: (c: Confirm) => void;
  onChanged: () => void;
}) {
  const [who, setWho] = useState('');
  const [more, setMore] = useState<{ nodes: FlxRoleMemberRowFragment[]; after: string | null; next: boolean } | null>(null);
  const { client } = useGraphQL();
  useEffect(() => setMore(null), [role.members]);
  const nodes = [...role.members.nodes, ...(more?.nodes ?? [])];
  const hasNext = more ? more.next : role.members.pageInfo.hasNextPage;
  const after = more ? more.after : role.members.pageInfo.endCursor;
  const [loading, setLoading] = useState(false);

  const add = useFlxMutation(FlxRoleMemberAdd, {
    onSuccess: (r) => {
      const p = r.flxRoleMemberAdd;
      if (!p) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      if (p.errors.length) return onNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      setWho('');
      onNotice({ kind: 'success', text: t('Person added to the role.') });
      onChanged();
    },
    onError: (e) => onNotice({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const drop = useFlxMutation(FlxRoleMemberRemove, {
    onSuccess: (r) => {
      if (r.flxRoleMemberRemove?.errors.length !== 0) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      onNotice({ kind: 'success', text: t('Person removed from the role.') });
      onChanged();
    },
    onError: (e) => onNotice({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const loadMore = async () => {
    setLoading(true);
    try {
      const r = await client.request(FlxRoleMembers, { key: role.key, after });
      const page = r.flxRole?.members;
      if (page) setMore({ nodes: [...(more?.nodes ?? []), ...page.nodes], after: page.pageInfo.endCursor, next: page.pageInfo.hasNextPage });
    } catch (e) {
      onNotice({ kind: 'error', text: e instanceof Error ? e.message : t('The action could not be completed.') });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flxr-card">
      <h3>{role.label}</h3>
      {nodes.length ? (
        <ul className="flxr-people">
          {nodes.map((m) => (
            <Person
              key={m.databaseId}
              m={m}
              t={t}
              onRemove={() =>
                onConfirm({ text: t('Remove this person from the role?'), action: t('Remove from role'), run: () => drop.mutate({ input: { role: role.key, userId: m.databaseId } }) })
              }
            />
          ))}
        </ul>
      ) : (
        <p className="flxr-muted">{t('Nobody yet.')}</p>
      )}
      {hasNext && (
        <button type="button" className="flxr-btn is-small flxr-more" disabled={loading} onClick={() => void loadMore()}>
          {t('Show more')}
        </button>
      )}
      <form
        className="flxr-add"
        onSubmit={(e) => {
          e.preventDefault();
          if (who.trim()) add.mutate({ input: { role: role.key, login: who.trim() } });
        }}
      >
        <label className="flxr-sr" htmlFor={'flxr-who-' + role.key}>
          {t('Username or email')}
        </label>
        <input id={'flxr-who-' + role.key} type="text" required placeholder={t('Username or email')} value={who} onChange={(e) => setWho(e.currentTarget.value)} />
        <button type="submit" className="flxr-btn" disabled={add.isPending}>
          {t('Add')}
        </button>
      </form>
    </div>
  );
}

function Person({ m, t, onRemove }: { m: FlxRoleMemberRowFragment; t: Translate; onRemove: () => void }): ReactNode {
  const [copied, setCopied] = useState(false);
  const copy = (v: string) => {
    void navigator.clipboard?.writeText(v).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };
  return (
    <li>
      <div className="flxr-person">
        <a href={m.editUrl}>
          <strong>{m.name}</strong>
        </a>
        <span>{m.email}</span>
        <span>{m.phone ? m.phone.e164 || m.phone.display : <a href={m.editUrl + '#flx-phone'}>{t('Add WhatsApp number')}</a>}</span>
        {m.referralLink && (
          <span className="flxr-ref">
            <input type="text" readOnly value={m.referralLink} aria-label={t('Referral link')} onFocus={(e) => e.currentTarget.select()} />
            <button type="button" className="flxr-btn is-small" onClick={() => copy(m.referralLink ?? '')}>
              {copied ? t('Copied') : t('Copy')}
            </button>
          </span>
        )}
      </div>
      <button type="button" className="flxr-remove" onClick={onRemove}>
        {t('Remove from role')}
      </button>
    </li>
  );
}
