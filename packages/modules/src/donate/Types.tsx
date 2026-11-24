// Donation types: open and closed ones or the archive, order (up / down), open, close and archive, and the
// sample sets. The type form (?edit=) is still the plugin's own page; its links open there. PHP preloads the
// list of the view in the address (FLXB_Admin::preload_types()).
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateDashboard, FlxDonateTypes, FlxDonationTypeMove, FlxDonationTypeSamples, FlxDonationTypeSetStatus } from '@fluxwp/graphql/operations';
import type { FlxDonateTypesQuery, FlxDonationTypeStatus } from '@fluxwp/graphql/types';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';
import { Failed } from './Subscriptions';

type Row = FlxDonateTypesQuery['flxDonationTypeAdminList']['items'][number];
type Res = { message?: string | null; errors?: { message: string }[] | null } | null | undefined;

export function Types({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const start = new URLSearchParams(query);
  const [archived, setArchived] = useState(start.get('view') === 'archived');
  const q = useFlxQuery(FlxDonateTypes, { archived }, { placeholderData: keepPreviousData });
  const d = q.data?.flxDonationTypeAdminList;

  // The notice of the classic type form (deleted…) comes with the address once.
  useEffect(() => {
    const n = start.get('notice') ?? '';
    const i = n.indexOf(':');
    if (i > 0) told({ kind: n.slice(0, i) === 'ok' ? 'ok' : 'error', text: n.slice(i + 1) });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-types' || u.searchParams.get('edit')) return;
    const s = '?page=flxb-types' + (archived ? '&view=archived' : '');
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [archived, host.mode]);

  const done = (res: Res, quiet = false) => {
    const err = res?.errors?.[0]?.message;
    if (err) told({ kind: 'error', text: err });
    else if (res?.message && !quiet) told({ kind: 'ok', text: res.message });
    for (const op of [FlxDonateTypes, FlxDonateDashboard]) void qc.invalidateQueries({ queryKey: flxQueryKey(scope, op) });
  };
  const fail = (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const status = useFlxMutation(FlxDonationTypeSetStatus, { onSuccess: (r) => done(r.flxDonationTypeSetStatus), onError: fail });
  const move = useFlxMutation(FlxDonationTypeMove, { onSuccess: (r) => done(r.flxDonationTypeMove, true), onError: fail });
  const busy = status.isPending || move.isPending;

  const actions = d?.newUrl ? (
    <Go className="flxdn-btn flxdn-btn--primary" href={d.newUrl}>
      <Ico name="plus" />
      {t('New donation type')}
    </Go>
  ) : null;
  const order = !!d?.canManage && !archived;

  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={t('Donation types and campaigns')}
      subtitle={t('What people can give for: each type has its own prices and options, dates, goal and linked forms.')}
      actions={actions}
      notice={notice}
    >
      <div className="flxdn-filters">
        <div className="flxdn-seg" role="group" aria-label={t('View')}>
          <button type="button" className={archived ? '' : 'is-on'} aria-pressed={!archived} onClick={() => setArchived(false)}>
            {t('Open and closed')}
          </button>
          <button type="button" className={archived ? 'is-on' : ''} aria-pressed={archived} onClick={() => setArchived(true)}>
            {t('Archive')}
          </button>
        </div>
      </div>
      <div className={'flxdn-panel' + ((q.isFetching && d) || busy ? ' is-busy' : '')}>
        {!d ? (
          q.isError ? (
            <Failed retry={() => void q.refetch()} />
          ) : (
            <Loading />
          )
        ) : !d.items.length ? (
          <div className="flxdn-emptystate">
            <Ico name="flag" size={32} />
            <h2>{archived ? t('The archive is empty') : t('No donation types yet')}</h2>
            {!archived ? <p>{t('Create a type such as a general donation, a sapling or a kurban share, set its prices, and link the forms that collect for it.')}</p> : null}
          </div>
        ) : (
          <div className="flxdn-tablewrap">
            <table className="flxdn-table flxdn-table--types">
              <thead>
                <tr>
                  {order ? (
                    <th scope="col">
                      <span className="screen-reader-text">{t('Order')}</span>
                    </th>
                  ) : null}
                  <th scope="col">{t('Donation type')}</th>
                  <th scope="col">{t('Prices')}</th>
                  <th scope="col">{t('Progress')}</th>
                  <th scope="col">{t('Dates')}</th>
                  <th scope="col">{t('Forms')}</th>
                  {d.canManage ? (
                    <th scope="col">
                      <span className="screen-reader-text">{t('Actions')}</span>
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {d.items.map((r, i) => (
                  <TypeRow
                    key={r.databaseId}
                    r={r}
                    order={order}
                    manage={d.canManage}
                    first={i === 0}
                    last={i === d.items.length - 1}
                    busy={busy}
                    onMove={(up) => move.mutate({ id: r.databaseId, up })}
                    onStatus={(s) => status.mutate({ id: r.databaseId, status: s })}
                  />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      {d?.canManage && !archived && d.samples.length ? <Samples samples={d.samples} onDone={done} onFail={fail} /> : null}
    </Screen>
  );
}

function TypeRow({
  r,
  order,
  manage,
  first,
  last,
  busy,
  onMove,
  onStatus,
}: {
  r: Row;
  order: boolean;
  manage: boolean;
  first: boolean;
  last: boolean;
  busy: boolean;
  onMove: (up: boolean) => void;
  onStatus: (s: FlxDonationTypeStatus) => void;
}) {
  const { t } = useDonate();
  return (
    <tr>
      {order ? (
        <td className="flxdn-order">
          <button type="button" className={'flxdn-iconbtn' + (first ? ' is-off' : '')} disabled={busy || first} onClick={() => onMove(true)}>
            <span aria-hidden="true">▲</span>
            <span className="screen-reader-text">{t('Move up')}</span>
          </button>
          {!last ? (
            <button type="button" className="flxdn-iconbtn" disabled={busy} onClick={() => onMove(false)}>
              <span aria-hidden="true">▼</span>
              <span className="screen-reader-text">{t('Move down')}</span>
            </button>
          ) : null}
        </td>
      ) : null}
      <td className="flxdn-col-main">
        <Go href={r.listUrl}>{r.title}</Go> <span className={'flxdn-pill flxdn-pill--' + r.tone.toLowerCase()}>{r.stateLabel}</span>
        <span className="flxdn-sub2">
          {r.category} · <code>{r.shortcode}</code>
        </span>
      </td>
      <td data-label={t('Prices')}>
        <span>
          {r.prices}
          {r.flags ? <span className="flxdn-sub2">{r.flags}</span> : null}
        </span>
      </td>
      <td data-label={t('Progress')}>
        <div className="flxdn-progresscell">
          {r.hasGoal ? (
            <>
              <div className="flxdn-progress" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(r.percent)}>
                <span style={{ width: Math.min(100, r.percent) + '%' }} />
              </div>
              <small>{r.progress}</small>
            </>
          ) : (
            <>
              <strong>{r.progress}</strong>
              <small>{r.donors}</small>
            </>
          )}
        </div>
      </td>
      <td className="flxdn-col-date" data-label={t('Dates')}>
        <span>{r.dates}</span>
      </td>
      <td data-label={t('Forms')}>
        <span>{r.forms || '—'}</span>
      </td>
      {manage ? (
        <td className="flxdn-col-act">
          <div className="flxdn-actions">
            {r.editUrl ? (
              <Go className="flxdn-btn flxdn-btn--sm" href={r.editUrl}>
                {t('Edit')}
              </Go>
            ) : null}
            <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={busy} onClick={() => onStatus(r.status === 'ACTIVE' ? 'CLOSED' : 'ACTIVE')}>
              {r.status === 'ACTIVE' ? t('Close') : t('Open')}
            </button>
            {r.status !== 'ARCHIVED' ? (
              <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={busy} onClick={() => onStatus('ARCHIVED')}>
                {t('Archive')}
              </button>
            ) : null}
          </div>
        </td>
      ) : null}
    </tr>
  );
}

function Samples({ samples, onDone, onFail }: { samples: { value: string; label: string }[]; onDone: (r: Res) => void; onFail: (e: Error) => void }) {
  const { t } = useDonate();
  const [key, setKey] = useState(samples[0]?.value ?? '');
  const load = useFlxMutation(FlxDonationTypeSamples, { onSuccess: (r) => onDone(r.flxDonationTypeSamples), onError: onFail });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (key) load.mutate({ key });
  };
  return (
    <section className="flxdn-card flxdn-card--samples">
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{t('Load sample types')}</h2>
          <p className="flxdn-card__hint">{t('Adds ready-made types and categories that you can edit. Types whose sample price is 0 start closed until you set their prices.')}</p>
        </div>
      </div>
      <form className="flxdn-filters flxdn-filters--flat" onSubmit={submit}>
        <label className="screen-reader-text" htmlFor="flxdn-sample">
          {t('Sample set')}
        </label>
        <select id="flxdn-sample" value={key} onChange={(e) => setKey(e.target.value)}>
          {samples.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </select>
        <button type="submit" className="flxdn-btn" disabled={load.isPending}>
          <Ico name="plus" />
          {t('Load')}
        </button>
      </form>
    </section>
  );
}
