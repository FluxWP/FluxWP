// Backups › Backups (list with filters, bulk delete, lock, upload) and the detail page with the size breakdown.
import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL, flxQueryKey } from '@fluxwp/graphql/react';
import { FlxBackupDetail, FlxBackupList, FlxBackupLock } from '@fluxwp/graphql/operations';
import type { FlxBackupDetailQuery, FlxBackupType } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Badge, bytes, useBackup, type Row } from './shared';

const PAGE = 20;

export function BackupList() {
  const { t, tn, lang, go, openRestore, askDelete, failed, busy, refresh } = useBackup();
  const [type, setType] = useState<FlxBackupType | null>(null);
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [sel, setSel] = useState<Set<string>>(new Set());
  const after = cursors[cursors.length - 1] ?? null;
  const q = useFlxQuery(FlxBackupList, { first: PAGE, after, type });
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const lock = useFlxMutation(FlxBackupLock, {
    onSuccess: (r) => {
      const e = r.flxBackupLock?.errors[0];
      if (e) return failed(new Error(e.message));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxBackupList) });
    },
    onError: failed,
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxy-notice is-error">{q.error?.message || t('An error occurred.')}</div>;
  const rows = q.data.flxBackups?.nodes ?? [];
  const counts = q.data.flxBackupCounts;
  const info = q.data.flxBackups?.pageInfo;
  const nf = new Intl.NumberFormat(lang);
  const pick = (tp: FlxBackupType | null) => {
    setType(tp);
    setCursors([null]);
    setSel(new Set());
  };
  const all = rows.length > 0 && rows.every((r) => sel.has(r.id));
  return (
    <>
      <ul className="flxy-filters">
        <li>
          <button type="button" aria-current={type === null} onClick={() => pick(null)}>
            {t('All')} <span>({nf.format(counts.all)})</span>
          </button>
        </li>
        {counts.types.map((c) => (
          <li key={c.type}>
            <button type="button" aria-current={type === c.type} onClick={() => pick(c.type)}>
              {c.label} <span>({nf.format(c.count)})</span>
            </button>
          </li>
        ))}
        <li className="flxy-filters__end">
          <button type="button" disabled={q.isFetching} onClick={() => void q.refetch()}>
            {t('Refresh list')}
          </button>
        </li>
      </ul>
      <p className="flxy-sub">{t('Backups are stored in %s. Hostinger does not include this folder in its own backups: download important backups to your computer.', q.data.flxBackupHealth.folder)}</p>
      {sel.size > 0 && (
        <div className="flxy-bulk">
          <button type="button" className="flxy-btn is-danger" disabled={busy} onClick={() => askDelete([...sel], () => setSel(new Set()))}>
            {t('Delete selected')} ({nf.format(sel.size)})
          </button>
        </div>
      )}
      <div className="flxy-card flxy-tablewrap">
        <table className="flxy-table">
          <thead>
            <tr>
              <th scope="col" style={{ width: 28 }}>
                <input type="checkbox" aria-label={t('Select all')} checked={all} onChange={(e) => setSel(e.target.checked ? new Set(rows.filter((r) => r.status !== 'RUNNING').map((r) => r.id)) : new Set())} />
              </th>
              <th scope="col">{t('Backup')}</th>
              <th scope="col">{t('Type')}</th>
              <th scope="col">{t('Date')}</th>
              <th scope="col" className="num">
                {t('Size')}
              </th>
              <th scope="col">{t('Retention')}</th>
              <th scope="col">
                <span className="flxy-sr">{t('Actions')}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 && (
              <tr>
                <td colSpan={7} className="flxy-empty">
                  {t('No backups.')}
                </td>
              </tr>
            )}
            {rows.map((r) => (
              <RowView key={r.id} r={r} sel={sel.has(r.id)} onSel={(on) => setSel((o) => { const n = new Set(o); if (on) n.add(r.id); else n.delete(r.id); return n; })} go={go} />
            ))}
          </tbody>
        </table>
      </div>
      {(cursors.length > 1 || info?.hasNextPage) && (
        <div className="flxy-bulk" style={{ marginTop: 12 }}>
          <button type="button" className="flxy-btn" disabled={cursors.length < 2} onClick={() => setCursors((c) => c.slice(0, -1))}>
            ‹
          </button>
          <span className="flxy-muted">{nf.format(q.data.flxBackups?.totalCount ?? rows.length)}</span>
          <button type="button" className="flxy-btn" disabled={!info?.hasNextPage} onClick={() => setCursors((c) => [...c, info?.endCursor ?? null])}>
            ›
          </button>
        </div>
      )}
    </>
  );

  function RowView({ r, sel: on, onSel, go: nav }: { r: Row; sel: boolean; onSel: (on: boolean) => void; go: (p: `detail:${string}`) => void }) {
    const running = r.status === 'RUNNING';
    return (
      <tr>
        <td>
          <input type="checkbox" aria-label={t('Select')} checked={on} disabled={running} onChange={(e) => onSel(e.target.checked)} />
        </td>
        <td>
          <button type="button" className="flxy-link" onClick={() => nav(`detail:${r.id}`)}>
            {r.title}
          </button>
          {r.status === 'FAILED' && <small className="flxy-failed">{r.error || t('Failed')}</small>}
          {running && <small>{r.progress || t('Running…')}</small>}
          {r.label && r.label !== r.title && <small>{r.label}</small>}
        </td>
        <td>
          <Badge label={r.typeLabel} color={r.typeColor} />
        </td>
        <td title={r.created?.label}>{r.created?.relative}</td>
        <td className="num">{r.status === 'OK' ? r.sizeLabel : '—'}</td>
        <td>
          <button
            type="button"
            className={'flxy-lock flxy-link' + (r.locked ? ' is-on' : '')}
            disabled={r.status !== 'OK' || lock.isPending}
            title={r.locked ? t('Locked: never deleted automatically. Click to unlock.') : t('Lock: the quota and retention period will not delete this backup.')}
            aria-pressed={r.locked}
            onClick={() => lock.mutate({ input: { id: r.id, locked: !r.locked } })}
          >
            {r.locked ? '🔒' : '🔓'}
          </button>{' '}
          <span className="flxy-muted">{r.keepLabel}</span>
        </td>
        <td>
          <div className="flxy-actions">
            {r.status === 'OK' && (
              <>
                <a className="flxy-btn is-small" href={r.downloadUrl}>
                  {t('Download')}
                </a>
                <button type="button" className="flxy-btn is-small" disabled={busy} onClick={() => openRestore(r)}>
                  {t('Restore')}
                </button>
              </>
            )}
            {!running && (
              <button type="button" className="flxy-btn is-small" disabled={busy} onClick={() => askDelete([r.id])}>
                {t('Delete')}
              </button>
            )}
          </div>
        </td>
      </tr>
    );
  }
}

type Detail = NonNullable<FlxBackupDetailQuery['flxBackup']>;

export function BackupDetail({ id, onGone }: { id: string; onGone: () => void }) {
  const { t, tn, lang, go, openRestore, askDelete, busy } = useBackup();
  const q = useFlxQuery(FlxBackupDetail, { id });
  const [zip, setZip] = useState(false);
  if (q.isLoading) return <Loading />;
  const b: Detail | null | undefined = q.data?.flxBackup;
  if (q.isError || !b) {
    return (
      <div className="flxy-notice is-error">
        <span>{q.error?.message || t('Backup not found.')}</span>
        <button type="button" className="flxy-link" onClick={onGone}>
          {t('All backups')}
        </button>
      </div>
    );
  }
  const nf = new Intl.NumberFormat(lang);
  const br = b.breakdown;
  const total = br ? (zip ? br.zip : br.raw) : 0;
  return (
    <>
      <p>
        <button type="button" className="flxy-link" onClick={() => go('list')}>
          ‹ {t('All backups')}
        </button>
      </p>
      <section className="flxy-card" style={{ marginBottom: 16 }}>
        <div className="flxy-card-head">
          <h2 style={{ fontSize: 18, color: 'inherit' }}>{b.title}</h2>
          <div className="flxy-actions">
            {b.status === 'OK' && (
              <>
                <a className="flxy-btn" href={b.downloadUrl}>
                  {t('Download')}
                </a>
                <button type="button" className="flxy-btn" disabled={busy} onClick={() => openRestore(b)}>
                  {t('Restore')}
                </button>
              </>
            )}
            {b.status !== 'RUNNING' && (
              <button type="button" className="flxy-btn" disabled={busy} onClick={() => askDelete([b.id], onGone)}>
                {t('Delete')}
              </button>
            )}
          </div>
        </div>
        <dl className="flxy-meta">
          <dt>{t('Type')}</dt>
          <dd>
            <Badge label={b.typeLabel} color={b.typeColor} />
          </dd>
          <dt>{t('Date')}</dt>
          <dd>{b.created?.label}</dd>
          {b.originalCreated && (
            <>
              <dt>{t('Originally taken')}</dt>
              <dd>{b.originalCreated.label}</dd>
            </>
          )}
          {b.originalSite && (
            <>
              <dt>{t('Taken on site')}</dt>
              <dd>{b.originalSite}</dd>
            </>
          )}
          <dt>{t('Size')}</dt>
          <dd>{b.status === 'OK' ? b.sizeLabel + ' · ' + tn('%s file', '%s files', b.files, nf.format(b.files)) : '—'}</dd>
          <dt>{t('Contents')}</dt>
          <dd>{b.contents.join(', ')}</dd>
          <dt>{t('Source')}</dt>
          <dd>{b.sourceLabel}</dd>
          {b.packages.length > 0 && (
            <>
              <dt>{t('Updated packages')}</dt>
              <dd>{b.packages.map((p) => p.label).join(', ')}</dd>
            </>
          )}
          <dt>{t('Retention')}</dt>
          <dd>{b.keepLabel}</dd>
          <dt>WordPress</dt>
          <dd>{b.wpVersion}</dd>
          {b.error && (
            <>
              <dt>{t('Failed')}</dt>
              <dd className="flxy-failed">{b.error}</dd>
            </>
          )}
        </dl>
      </section>
      {b.status === 'RUNNING' && <p className="flxy-empty">{t('The backup is running; details will appear here when it finishes.')}</p>}
      {b.status === 'OK' && !br && <p className="flxy-empty">{t('The backup file could not be read; the size breakdown cannot be shown.')}</p>}
      {br && (
        <section className="flxy-card">
          <div className="flxy-card-head">
            <h2>{t('Size breakdown')}</h2>
            <div className="flxy-range" role="group" aria-label={t('Measure')}>
              <button type="button" className={'flxy-btn' + (!zip ? ' is-active' : '')} aria-pressed={!zip} onClick={() => setZip(false)}>
                {t('Original size')}
              </button>
              <button type="button" className={'flxy-btn' + (zip ? ' is-active' : '')} aria-pressed={zip} onClick={() => setZip(true)}>
                {t('In archive')}
              </button>
            </div>
          </div>
          <p className="flxy-sub">{t('Original size: the size of the files on the site (for the database, the size of the SQL dump). In archive: the compressed size inside the zip. Images and videos are already compressed, so they do not get smaller in the archive.')}</p>
          <table className="flxy-table">
            <thead>
              <tr>
                <th scope="col">{t('Section')}</th>
                <th scope="col" className="num">
                  {t('Files / rows')}
                </th>
                <th scope="col" className="num">
                  {zip ? t('Size in archive') : t('Original size')}
                </th>
                <th scope="col">{t('Share')}</th>
              </tr>
            </thead>
            <tbody>
              {br.categories.map((c) => (
                <Category key={c.key} c={c} zip={zip} total={total} lang={lang} />
              ))}
              <tr>
                <th scope="row">{t('Total %s', '')}</th>
                <td />
                <td className="num">
                  <b>{bytes(total, lang)}</b>
                </td>
                <td />
              </tr>
            </tbody>
          </table>
        </section>
      )}
    </>
  );
}

function Category({ c, zip, total, lang }: { c: NonNullable<Detail['breakdown']>['categories'][number]; zip: boolean; total: number; lang: string }) {
  const { t, tn } = useBackup();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLTableRowElement>(null);
  const size = zip ? c.zip : c.raw;
  const nf = new Intl.NumberFormat(lang);
  const pct = total > 0 ? (100 * size) / total : 0;
  const count = c.isDatabase ? tn('%s row', '%s rows', c.count, nf.format(c.count)) : tn('%s file', '%s files', c.count, nf.format(c.count));
  return (
    <>
      <tr ref={ref} className="flxy-cat">
        <td>
          {c.items.length > 0 ? (
            <button type="button" className="flxy-link" aria-expanded={open} onClick={() => setOpen(!open)}>
              <span className="flxy-twisty">{open ? '▾' : '▸'}</span>
              <i style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 2, background: c.color, marginRight: 6 }} />
              {c.label}
            </button>
          ) : (
            <>
              <i style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 2, background: c.color, margin: '0 6px 0 16px' }} />
              {c.label}
            </>
          )}
        </td>
        <td className="num">{count}</td>
        <td className="num">{bytes(size, lang)}</td>
        <td>
          <span className="flxy-share" style={{ width: Math.max(2, pct) * 0.8, background: c.color }} />
          {nf.format(Math.round(pct * 10) / 10)}%
        </td>
      </tr>
      {open &&
        c.items.map((i) => (
          <tr key={i.name} className="flxy-sub-row">
            <td>{i.label}</td>
            <td className="num">{nf.format(i.count)}</td>
            <td className="num">{bytes(zip ? i.zip : i.raw, lang)}</td>
            <td />
          </tr>
        ))}
      {open && c.moreCount > 0 && (
        <tr className="flxy-sub-row">
          <td>{tn('%s other item', '%s other items', c.moreItems, nf.format(c.moreItems))}</td>
          <td className="num">{nf.format(c.moreCount)}</td>
          <td className="num">{bytes(zip ? c.moreZip : c.moreRaw, lang)}</td>
          <td />
        </tr>
      )}
    </>
  );
}
