// CRM › Possible duplicates: pick the card to keep, merge the others into it.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmContactMerge, FlxCrmDuplicates } from '@fluxwp/graphql/operations';
import type { FlxCrmDuplicatesQuery } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Empty, Head, errText, href, useCrm } from './ui';

type Group = FlxCrmDuplicatesQuery['flxContactDuplicates'][number];

export function Duplicates(_props: { go: (to: string) => void }) {
  const crm = useCrm();
  const { t, can } = crm;
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxCrmDuplicates, {});
  const merge = useFlxMutation(FlxCrmContactMerge);
  const [keep, setKeep] = useState<Record<number, string>>({});
  const [skip, setSkip] = useState<Record<string, boolean>>({});
  const [ask, setAsk] = useState<{ gi: number; group: Group } | null>(null);

  if (q.isLoading) return <Loading />;
  const groups = q.data?.flxContactDuplicates ?? [];
  const keptOf = (gi: number, g: Group) => keep[gi] ?? g.items[0]?.contact.id ?? '';
  const run = async () => {
    if (!ask) return;
    const kept = keptOf(ask.gi, ask.group);
    const dups = ask.group.items.map((i) => i.contact.id).filter((id) => id !== kept && !skip[id]);
    setAsk(null);
    try {
      if (!kept || dups.length === 0) throw new Error(t('Pick the card to keep and at least one other card.'));
      const r = (await merge.mutateAsync({ input: { keepId: kept, duplicateIds: dups } })).flxContactMerge;
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      crm.tell('ok', t('Cards merged.'));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmDuplicates) });
      void qc.invalidateQueries({ queryKey: [scope, 'gql'] });
    } catch (e) {
      crm.fail(e);
    }
  };

  return (
    <>
      <Head
        title={t('Possible duplicates')}
        lead={t('The same person can arrive twice, e.g. once with only a phone and later with only an e-mail. Pick the card to keep: the others are merged into it, and every package moves its records.')}
        back={{ to: 'contacts', label: t('Contacts') }}
      />
      {groups.length === 0 ? (
        <Empty icon="check" title={t('No likely duplicates.')} />
      ) : (
        groups.map((g, gi) => (
          <div key={gi} className="flxcrm-card flxcrm-card--flush">
            <p className="flxcrm-muted" style={{ padding: '12px 18px 0', margin: 0 }}>
              {g.why === 'phone' ? t('Same phone number') : t('Same name')}
            </p>
            <table className="flxcrm-table">
              <thead>
                <tr>
                  <th>{t('Keep')}</th>
                  <th>{t('Merge')}</th>
                  <th>{t('Name')}</th>
                  <th>{t('Phone')}</th>
                  <th>{t('E-mail')}</th>
                  <th>{t('Used by')}</th>
                </tr>
              </thead>
              <tbody>
                {g.items.map((i) => (
                  <tr key={i.contact.id}>
                    <td>
                      <input type="radio" name={'keep' + gi} disabled={!can} checked={keptOf(gi, g) === i.contact.id} onChange={() => setKeep({ ...keep, [gi]: i.contact.id })} aria-label={t('Keep')} />
                    </td>
                    <td>
                      <input type="checkbox" disabled={!can || keptOf(gi, g) === i.contact.id} checked={keptOf(gi, g) === i.contact.id ? false : !skip[i.contact.id]} onChange={(e) => setSkip({ ...skip, [i.contact.id]: !e.currentTarget.checked })} aria-label={t('Merge')} />
                    </td>
                    <td>
                      <a href={href('contact/' + i.contact.databaseId)}>
                        <strong>{i.contact.name || '#' + i.contact.databaseId}</strong>
                      </a>
                    </td>
                    <td>{i.contact.phone?.display ?? '—'}</td>
                    <td>{i.contact.email ?? '—'}</td>
                    <td>{i.usedBy.length ? i.usedBy.join(', ') : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {can && (
              <p style={{ padding: '0 18px 14px' }}>
                <button type="button" className="button button-primary" disabled={merge.isPending} onClick={() => setAsk({ gi, group: g })}>
                  {t('Merge into the kept card')}
                </button>
              </p>
            )}
          </div>
        ))
      )}
      {ask && (
        <Modal title={t('Merge into the kept card')} onClose={() => setAsk(null)}>
          <p>{t('The other cards are merged into the kept one. This cannot be undone.')}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="button button-primary" onClick={() => void run()}>
              {t('Merge')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}
