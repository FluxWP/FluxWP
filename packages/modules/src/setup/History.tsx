// FluxWP Panel › Packages › History: the update records with the backup of each, pagination, the
// retention of update backups and deleting them (Flux Backup).
import { useState } from 'react';
import { keepPreviousData } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSetupBackupsDelete, FlxSetupHistory, FlxSetupRetentionSave } from '@fluxwp/graphql/operations';
import { Modal } from '@fluxwp/ui';
import { useSetup, errorText, type Notice } from './shared';

type Tr = ReturnType<typeof useSetup>['t'];
type Tn = ReturnType<typeof useSetup>['tn'];

export function History({ t, tn, locale, notify }: { t: Tr; tn: Tn; locale: string; notify: (n: Notice) => void }) {
  const { refresh } = useSetup();
  const [page, setPage] = useState(1);
  const [picked, setPicked] = useState<Set<string>>(new Set());
  const [confirm, setConfirm] = useState<{ text: string; run: () => void } | null>(null);
  const [days, setDays] = useState<number | null>(null);
  const q = useFlxQuery(FlxSetupHistory, { page }, { placeholderData: keepPreviousData });
  const h = q.data?.flxSetupHistory;
  const fail = (e: unknown) => notify({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });

  const del = useFlxMutation(FlxSetupBackupsDelete, {
    onSuccess: (r) => {
      const p = r.flxSetupBackupsDelete;
      if (!p || p.errors.length) return fail(new Error(p?.errors[0]?.message));
      setPicked(new Set());
      const n = p.deleted ?? 0;
      notify({ kind: 'success', text: tn('%d backup deleted.', '%d backups deleted.', n, n) });
      void refresh();
    },
    onError: fail,
  });
  const keep = useFlxMutation(FlxSetupRetentionSave, {
    onSuccess: (r) => {
      const p = r.flxSetupRetentionSave;
      if (!p || p.errors.length) return fail(new Error(p?.errors[0]?.message));
      const n = p.deleted ?? 0;
      notify({ kind: 'success', text: t('Retention saved and applied to existing update backups.') + (n ? ' ' + tn('%d expired backup deleted.', '%d expired backups deleted.', n, n) : '') });
      void refresh();
    },
    onError: fail,
  });

  if (!h) return null;
  const ret = h.retention;
  const choices = [...new Set([...ret.choices, ...(ret.days ? [ret.days] : [])])].sort((a, b) => a - b);
  const sel = days ?? ret.days;
  const seen = new Set<string>();

  return (
    <section className="flxk-history" id="flxu-history">
      <h2>{t('History')}</h2>
      {ret.available && (
        <div className="flxk-keep">
          <span className="dashicons dashicons-backup" aria-hidden="true" />
          <span className="flxk-keep__text">
            {ret.days ? (
              <>
                <label htmlFor="flxk-keep-days">{t('Update backups are deleted automatically after')}</label>
                <select id="flxk-keep-days" value={sel} onChange={(e) => setDays(Number(e.target.value))}>
                  {choices.map((c) => (
                    <option key={c} value={c}>
                      {tn('%d day', '%d days', c, c)}
                    </option>
                  ))}
                </select>
                {t('.')}
                <button type="button" className="flxk-btn is-small" disabled={keep.isPending} onClick={() => keep.mutate({ input: { days: sel } })}>
                  {t('Save')}
                </button>
              </>
            ) : (
              <span>
                {t('Update backup retention is set on the Flux Backup settings page (with Flux Backup 1.2.0 you can also change it here).')} {ret.settingsUrl && <a href={ret.settingsUrl}>{t('Flux Backup settings')}</a>}
              </span>
            )}
            <small className="flxk-muted">{tn('Currently %1$d update backup, %2$s.', 'Currently %1$d update backups, %2$s.', ret.count, ret.count, ret.size)}</small>
          </span>
          {ret.count > 0 && (
            <button
              type="button"
              className="flxk-btn is-small is-danger-outline"
              onClick={() => setConfirm({ text: t('Delete all unlocked update backups? This cannot be undone.'), run: () => del.mutate({ input: { mode: 'ALL' } }) })}
            >
              {t('Delete all')}
            </button>
          )}
        </div>
      )}

      {!h.rows.length ? (
        <p className="flxk-muted">{t('No updates yet.')}</p>
      ) : (
        <>
          <div className="flxk-tablenav">
            {h.canDelete && (
              <button
                type="button"
                className="flxk-btn is-small"
                disabled={!picked.size}
                onClick={() => setConfirm({ text: t('Delete the selected update backups? This cannot be undone.'), run: () => del.mutate({ input: { mode: 'SELECTED', ids: [...picked] } }) })}
              >
                {t('Delete selected backups')}
              </button>
            )}
            <span className="flxk-spacer" />
            <span className="flxk-muted">{tn('%s record', '%s records', h.total, h.total.toLocaleString(locale))}</span>
            {h.pages > 1 && (
              <span className="flxk-pager">
                <button type="button" className="flxk-btn is-small" disabled={page <= 1} aria-label={t('First page')} onClick={() => setPage(1)}>
                  «
                </button>
                <button type="button" className="flxk-btn is-small" disabled={page <= 1} aria-label={t('Previous page')} onClick={() => setPage(page - 1)}>
                  ‹
                </button>
                <span>{t('%1$d of %2$d', h.page, h.pages)}</span>
                <button type="button" className="flxk-btn is-small" disabled={page >= h.pages} aria-label={t('Next page')} onClick={() => setPage(page + 1)}>
                  ›
                </button>
                <button type="button" className="flxk-btn is-small" disabled={page >= h.pages} aria-label={t('Last page')} onClick={() => setPage(h.pages)}>
                  »
                </button>
              </span>
            )}
          </div>
          <div className="flxk-card flxk-table-wrap">
            <table className="flxk-table">
              <thead>
                <tr>
                  {h.canDelete && <td className="flxk-c-check" />}
                  <th>{t('Date')}</th>
                  <th>{t('Package')}</th>
                  <th>{t('Action')}</th>
                  <th>{t('Result')}</th>
                  <th>{t('By')}</th>
                  <th>{t('Backup')}</th>
                </tr>
              </thead>
              <tbody>
                {h.rows.map((e, i) => {
                  const b = e.backup;
                  const first = !!b && !seen.has(b.id);
                  if (b) seen.add(b.id);
                  return (
                    <tr key={i}>
                      {h.canDelete && (
                        <th scope="row" className="flxk-c-check">
                          {b && b.deletable && first && b.selectable && (
                            <input
                              type="checkbox"
                              aria-label={e.name}
                              checked={picked.has(b.id)}
                              onChange={(ev) =>
                                setPicked((old) => {
                                  const n = new Set(old);
                                  if (ev.target.checked) n.add(b.id);
                                  else n.delete(b.id);
                                  return n;
                                })
                              }
                            />
                          )}
                        </th>
                      )}
                      <td>{e.time.label}</td>
                      <td>
                        <strong>{e.name}</strong>
                      </td>
                      <td>{e.action}</td>
                      <td>
                        <span className={'flxk-badge ' + (e.success ? 'is-ok' : 'is-err')}>{e.success ? t('Success') : t('Error')}</span>
                        {e.message && (
                          <>
                            <br />
                            <small>{e.message}</small>
                          </>
                        )}
                      </td>
                      <td>{e.by}</td>
                      <td>
                        {!b ? (
                          '—'
                        ) : b.deleted ? (
                          <span className="flxk-muted">{t('Deleted')}</span>
                        ) : (
                          <>
                            <strong>{b.size}</strong>{' '}
                            {b.locked ? (
                              <span className="flxk-badge" title={t('Locked backups are never deleted automatically.')}>
                                {t('Locked')}
                              </span>
                            ) : (
                              b.keepUntil && <small className="flxk-muted">{t('Deleted on %s', b.keepUntil.label)}</small>
                            )}
                            <br />
                            {b.url && <a href={b.url}>{t('Open')}</a>}
                            {b.deletable && (
                              <>
                                {b.url && ' · '}
                                <button
                                  type="button"
                                  className="flxk-linkbtn is-danger"
                                  onClick={() =>
                                    setConfirm({
                                      text: t('Delete this backup? It also holds the backup of the other packages in the same bulk update. This cannot be undone.'),
                                      run: () => del.mutate({ input: { mode: 'ONE', ids: [b.id] } }),
                                    })
                                  }
                                >
                                  {t('Delete')}
                                </button>
                              </>
                            )}
                          </>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </>
      )}

      {confirm && (
        <Modal title={t('Delete')} onClose={() => setConfirm(null)}>
          <p className="flxk-confirm">{confirm.text}</p>
          <div className="flxk-modal-actions">
            <button type="button" className="flxk-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxk-btn is-danger"
              onClick={() => {
                confirm.run();
                setConfirm(null);
              }}
            >
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </section>
  );
}
