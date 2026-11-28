import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxPosAccountDelete, FlxPosAccountTest, FlxPosAccounts, FlxPosMethods } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { FlaskConical, Landmark, LockKeyhole, Pencil, Plus, Trash2 } from 'lucide-react';
import { AccountEditor } from './AccountEditor';
import { ProviderPicker, providerIcon } from './ProviderPicker';
import { Badge, Dialog, EmptyState, Failed, InlineNotice, Panel, Tile, msg } from './components';
import type { Account, AccountsData, Provider, TabProps } from './types';

export function AccountsTab({ t, say }: TabProps) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosAccounts, {});
  const [picking, setPicking] = useState(false);
  const [edit, setEdit] = useState<{ account: Account | null; provider: Provider } | null>(null);
  const [del, setDel] = useState<Account | null>(null);
  const [deleteError, setDeleteError] = useState('');
  const [tests, setTests] = useState<Record<string, { ok: boolean; text: string } | 'busy'>>({});
  const put = (accounts: Account[]) => {
    const old = qc.getQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}));
    if (old) qc.setQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}), { ...old, flxPosAccounts: accounts });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxPosMethods, {}) });
  };
  const remove = useFlxMutation(FlxPosAccountDelete, {
    onSuccess: (r) => {
      const p = r.flxPosAccountDelete;
      if (!p || p.errors.length)
        return setDeleteError(p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.'));
      put(p.accounts);
      setDel(null);
      say({ kind: 'ok', text: t('The POS account was deleted.') });
    },
    onError: (e) => setDeleteError(msg(e, t)),
  });
  const test = useFlxMutation(FlxPosAccountTest);
  const runTest = async (a: Account) => {
    setTests((current) => ({ ...current, [a.id]: 'busy' }));
    let result: { ok: boolean; text: string };
    try {
      const { flxPosAccountTest: response } = await test.mutateAsync({ input: { id: a.id } });
      const connection = response?.result;
      result = connection
        ? { ok: connection.ok, text: connection.message || (connection.ok ? t('Connection works.') : t('Connection failed.')) }
        : { ok: false, text: response?.errors[0]?.message || t('The action could not be completed.') };
    } catch (error) {
      result = { ok: false, text: msg(error, t) };
    }
    setTests((current) => ({ ...current, [a.id]: result }));
  };

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <Failed t={t} error={q.error} />;
  const d = q.data;
  const prov = new Map(d.flxPosProviders.map((p) => [p.id, p]));

  return (
    <>
      <Panel
        icon={Landmark}
        title={t('POS accounts')}
        description={t('Manage bank and payment provider connections.')}
        action={
          <button type="button" className="btn-primary" onClick={() => setPicking(true)}>
            <Plus size={17} aria-hidden="true" />
            {t('Add POS')}
          </button>
        }
      >
        {d.flxPosAccounts.length === 0 ? (
          <EmptyState
            icon={Landmark}
            title={t('No POS accounts')}
            body={t('No POS account yet. Pick your bank or payment institution and enter the keys it gave you.')}
          />
        ) : (
          <ul className="flxpos-accounts">
            {d.flxPosAccounts.map((a) => {
              const p = prov.get(a.provider);
              const r = tests[a.id];
              const missing = a.creds.filter((c) => p?.fields.some((f) => f.key === c.key && f.secret) && !c.isSet).length;
              return (
                <li key={a.id} className="flxpos-account">
                  <Tile icon={providerIcon(p?.group)} />
                  <div className="flxpos-account__main">
                    <strong>{a.name}</strong>
                    <span className="flxpos-muted">{a.providerName}</span>
                    <div className="flxpos-badges">
                      <Badge tone={a.enabled ? 'is-ok' : ''}>{a.enabled ? t('on') : t('off')}</Badge>
                      <Badge tone={a.test ? 'is-warn' : ''}>{a.test ? t('test mode') : t('live')}</Badge>
                      {a.takesCard && <Badge>{t('card form')}</Badge>}
                      {missing > 0 && <Badge tone="is-bad">{t('%d keys missing', missing)}</Badge>}
                    </div>
                    {r && r !== 'busy' && <InlineNotice kind={r.ok ? 'ok' : 'bad'}>{r.text}</InlineNotice>}
                  </div>
                  <div className="flxpos-account__tools">
                    <button type="button" className="btn-ghost small" disabled={r === 'busy'} onClick={() => void runTest(a)}>
                      <FlaskConical size={15} aria-hidden="true" />
                      {r === 'busy' ? t('Testing…') : t('Test connection')}
                    </button>
                    <button
                      type="button"
                      className="btn-ghost small"
                      disabled={!p}
                      onClick={() => p && setEdit({ account: a, provider: p })}
                    >
                      <Pencil size={14} aria-hidden="true" />
                      {t('Edit')}
                    </button>
                    <button
                      type="button"
                      className="icon-btn flxpos-danger"
                      aria-label={t('Delete')}
                      title={t('Delete')}
                      onClick={() => {
                        setDeleteError('');
                        setDel(a);
                      }}
                    >
                      <Trash2 size={16} aria-hidden="true" />
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
        <p className="flxpos-muted flxpos-note">
          <LockKeyhole size={16} aria-hidden="true" />
          {t(
            'Keys are stored encrypted and never shown again; leave a key empty to keep the saved one. Try every account in test mode before going live.',
          )}
        </p>
      </Panel>
      {picking && (
        <ProviderPicker
          t={t}
          providers={d.flxPosProviders}
          onClose={() => setPicking(false)}
          onPick={(p) => {
            setPicking(false);
            setEdit({ account: null, provider: p });
          }}
        />
      )}
      {edit && (
        <AccountEditor
          t={t}
          account={edit.account}
          provider={edit.provider}
          settings={d.flxPosSettings}
          onClose={() => setEdit(null)}
          onSaved={(accounts, isNew) => {
            put(accounts);
            setEdit(null);
            say({ kind: 'ok', text: isNew ? t('The POS account was added. Test the connection before going live.') : t('Saved.') });
          }}
        />
      )}
      {del && (
        <Dialog
          t={t}
          title={t('Delete the POS account?')}
          onClose={() => !remove.isPending && setDel(null)}
          actions={
            <>
              <button type="button" className="btn-ghost" disabled={remove.isPending} onClick={() => setDel(null)}>
                {t('Cancel')}
              </button>
              <button
                type="button"
                className="btn-primary danger-bg"
                disabled={remove.isPending}
                onClick={() => remove.mutate({ input: { id: del.id } })}
              >
                <Trash2 size={16} aria-hidden="true" />
                {t('Delete')}
              </button>
            </>
          }
        >
          <p>{t('%s will be deleted with its keys. Its transactions stay in the list.', del.name)}</p>
          {deleteError && <InlineNotice kind="bad">{deleteError}</InlineNotice>}
        </Dialog>
      )}
    </>
  );
}
