// Settings › Admin theme › Permissions: a role × permission table. A disabled permission is a real
// restriction (not only a hidden menu item). The Administrator role is shown but cannot be changed.
import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAdminCapsRestore, FlxAdminCapsSave, FlxAdminCapsScreen } from '@fluxwp/graphql/operations';
import type { FlxAdminCapsScreenQuery } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import type { PanelProps } from './MenuEditor';

type Caps = FlxAdminCapsScreenQuery['flxAdminCaps'];
type Draft = Record<string, Set<string>>;
const draftOf = (c: Caps): Draft => Object.fromEntries(c.roles.map((r) => [r.key, new Set(r.granted)]));
const same = (a: ReadonlySet<string>, b: ReadonlyArray<string>) => a.size === b.length && b.every((x) => a.has(x));

export function CapsTable(p: Omit<PanelProps, 'capsHref'>) {
  const q = useFlxQuery(FlxAdminCapsScreen, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxad-notice is-error">{q.error?.message || p.t('The action could not be completed.')}</div>;
  return <Table {...p} caps={q.data.flxAdminCaps} />;
}

function Table({ t, caps, onDirty, onNotice }: Omit<PanelProps, 'capsHref'> & { caps: Caps }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [draft, setDraft] = useState(() => draftOf(caps));
  const [dirty, setDirty] = useState(false);
  const [filter, setFilter] = useState('');
  const [confirm, setConfirm] = useState(false);

  useEffect(() => {
    if (!dirty) setDraft(draftOf(caps));
  }, [caps, dirty]);
  useEffect(() => onDirty(dirty), [dirty, onDirty]);

  const done = (r: { caps: Caps | null; errors: { message: string }[] } | null | undefined, text: string) => {
    if (!r) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
    if (r.errors.length || !r.caps) return onNotice({ kind: 'error', text: r.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
    qc.setQueryData<FlxAdminCapsScreenQuery>(flxQueryKey(scope, FlxAdminCapsScreen, {}), { flxAdminCaps: r.caps });
    setDirty(false);
    onNotice({ kind: 'success', text });
  };
  const fail = (e: Error) => onNotice({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const save = useFlxMutation(FlxAdminCapsSave, { onSuccess: (r) => done(r.flxAdminCapsSave, t('Saved.')), onError: fail });
  const restore = useFlxMutation(FlxAdminCapsRestore, { onSuccess: (r) => done(r.flxAdminCapsRestore, t('Saved.')), onError: fail });

  const q = filter.toLowerCase().trim();
  const groups = useMemo(
    () => caps.groups.map((g) => ({ ...g, capabilities: g.capabilities.filter((c) => !q || (c.key + ' ' + c.label.toLowerCase()).includes(q)) })).filter((g) => g.capabilities.length > 0),
    [caps.groups, q],
  );
  const toggle = (role: string, cap: string, on: boolean) => {
    setDraft((old) => {
      const next = new Set(old[role]);
      if (on) next.add(cap);
      else next.delete(cap);
      return { ...old, [role]: next };
    });
    setDirty(true);
  };
  const submit = () => {
    const roles = caps.roles
      .filter((r) => !r.isAdministrator && !same(draft[r.key] ?? new Set(), r.granted))
      .map((r) => ({ role: r.key, capabilities: [...(draft[r.key] ?? [])] }));
    if (roles.length) save.mutate({ input: { roles } });
    else setDirty(false);
  };

  return (
    <div>
      <p className="flxad-lead">{t("Changes here are real permissions: a disabled action is not just hidden from the menu, it can't be done even by URL. The Administrator role can't be changed.")}</p>
      {caps.backupAt && (
        <div className="flxad-notice is-info" role="status">
          <span>
            {t('Permissions before the first change are backed up (%s).', caps.backupAt.label)}{' '}
            <button type="button" className="flxad-linkbtn" onClick={() => setConfirm(true)}>
              {t('Restore backup')}
            </button>
          </span>
        </div>
      )}
      <div className="flxad-card flxad-caps-wrap">
        <div className="flxad-caps-scroll">
          <table className="flxad-caps">
            <thead>
              <tr>
                <th className="flxad-caps-name">
                  <input type="search" className="flxad-caps-filter" placeholder={t('Search permissions…')} aria-label={t('Search permissions…')} value={filter} onChange={(e) => setFilter(e.currentTarget.value)} />
                </th>
                {caps.roles.map((r) => (
                  <th key={r.key} className={'flxad-caps-role' + (r.isAdministrator ? ' is-admin' : '')}>
                    <span>{r.label}</span>
                    <small>
                      {r.userCount} {t('users')}
                    </small>
                  </th>
                ))}
              </tr>
            </thead>
            {groups.map((g) => (
              <tbody key={g.key}>
                <tr className="flxad-caps-group">
                  <th colSpan={caps.roles.length + 1}>{g.label}</th>
                </tr>
                {g.capabilities.map((c) => (
                  <tr key={c.key}>
                    <th className="flxad-caps-name" scope="row">
                      {c.label}
                      <code>{c.key}</code>
                    </th>
                    {caps.roles.map((r) => (
                      <td key={r.key} className={r.isAdministrator ? 'is-admin' : undefined}>
                        <input
                          type="checkbox"
                          checked={draft[r.key]?.has(c.key) ?? false}
                          disabled={r.isAdministrator}
                          aria-label={r.label + ': ' + c.key}
                          onChange={(e) => toggle(r.key, c.key, e.currentTarget.checked)}
                        />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            ))}
          </table>
        </div>
      </div>
      <div className="flxad-actions">
        <button type="button" className="flxad-btn is-primary" disabled={save.isPending || !dirty} onClick={submit}>
          {t('Save')}
        </button>
        {dirty && <span className="flxad-dirty">{t('You have unsaved changes.')}</span>}
      </div>
      {confirm && (
        <Modal title={t('Restore backup')} onClose={() => setConfirm(false)}>
          <p className="flxad-confirm">{t('Restore all roles to the backup?')}</p>
          <div className="flxad-modal-actions">
            <button type="button" className="flxad-btn" onClick={() => setConfirm(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxad-btn is-danger"
              onClick={() => {
                restore.mutate({ input: { confirm: true } });
                setConfirm(false);
              }}
            >
              {t('Restore backup')}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}
