// Reports (?page=flxb-reports): pick a report, choose a period, read the totals and the table, download the CSV.
// Managers edit a report's JSON (?edit=<id>), add new ones (?new=1) and put shipped reports back.
// PHP preloads the page of the address (FLXB_Admin::preload_reports()).
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateReport, FlxDonateReportSource, FlxDonationReportDelete, FlxDonationReportSave } from '@fluxwp/graphql/operations';
import { DatePicker, Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Ico, useDonate } from './shared';
import { Failed } from './Subscriptions';
import { useStartNotice } from './Partners';

type View = { page: 'reports'; id: string; period: string; from: string; to: string } | { page: 'edit'; id: string };

/** start page 'reports?id&period&from&to' or 'report-edit?id' → the view (the variables PHP preloads). */
export function reportView(page: string, query: string): View {
  const q = new URLSearchParams(query);
  if (page === 'report-edit') return { page: 'edit', id: q.get('id') ?? '' };
  return { page: 'reports', id: q.get('id') ?? '', period: q.get('period') ?? '', from: q.get('from') ?? '', to: q.get('to') ?? '' };
}

function address(v: View): string {
  const q = new URLSearchParams({ page: 'flxb-reports' });
  if (v.page === 'edit') {
    if (v.id) q.set('edit', v.id);
    else q.set('new', '1');
  } else {
    if (v.id) q.set('r', v.id);
    if (v.period) q.set('period', v.period);
    if (v.period === 'custom') {
      if (v.from) q.set('from', v.from);
      if (v.to) q.set('to', v.to);
    }
  }
  return '?' + q.toString();
}

export function Reports({ page, query, notice }: { page: string; query: string; notice: ReactNode }) {
  const host = useHost();
  useStartNotice(query);
  const [view, setView] = useState<View>(() => reportView(page, query));
  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-reports') return;
    const s = address(view);
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [view, host.mode]);
  return view.page === 'edit' ? <Editor key={view.id} id={view.id} go={setView} notice={notice} /> : <Report v={view} go={setView} notice={notice} />;
}

function Report({ v, go, notice }: { v: Extract<View, { page: 'reports' }>; go: (v: View) => void; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  const q = useFlxQuery(FlxDonateReport, { id: v.id, period: v.period, from: v.from, to: v.to }, { placeholderData: keepPreviousData });
  const r = q.data?.flxDonationReport;
  const [period, setPeriod] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  useEffect(() => {
    if (!r) return;
    setPeriod(r.period);
    setFrom(r.from);
    setTo(r.to);
  }, [r?.id, r?.period, r?.from, r?.to]); // eslint-disable-line react-hooks/exhaustive-deps

  const actions =
    r?.canManage ? (
      <>
        <button type="button" className="flxdn-btn" onClick={() => go({ page: 'edit', id: '' })}>
          <Ico name="plus" size={15} /> {t('New report')}
        </button>
        {r.id ? (
          <button type="button" className="flxdn-btn" onClick={() => go({ page: 'edit', id: r.id })}>
            {t('Edit JSON')}
          </button>
        ) : null}
      </>
    ) : null;
  const show = (e: FormEvent) => {
    e.preventDefault();
    go(period === 'custom' ? { ...v, id: r?.id ?? v.id, period, from, to } : { ...v, id: r?.id ?? v.id, period, from: '', to: '' });
  };
  let body: ReactNode;
  if (!r) body = q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />;
  else if (!r.id)
    body = (
      <div className="flxdn-panel">
        <div className="flxdn-emptystate">
          <h2>{t('No reports')}</h2>
        </div>
      </div>
    );
  else
    body = (
      <div className={q.isFetching ? 'is-busy' : undefined}>
        <nav className="flxdn-chipnav" aria-label={t('Reports')}>
          {r.reports.map((x) => (
            <button key={x.value} type="button" className={'flxdn-chipnav__a' + (x.value === r.id ? ' is-active' : '')} onClick={() => go({ page: 'reports', id: x.value, period: '', from: '', to: '' })}>
              {x.label}
            </button>
          ))}
        </nav>
        <form className="flxdn-filters" onSubmit={show}>
          <label>
            <select value={period} onChange={(e) => setPeriod(e.target.value)} aria-label={t('Period')}>
              {r.periods.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </select>
          </label>
          <label>
            {t('From')}{' '}
            <DatePicker
              value={from}
              onChange={(x: string) => {
                setFrom(x);
                setPeriod('custom');
              }}
              aria-label={t('From')}
            />
          </label>
          <label>
            {t('To')}{' '}
            <DatePicker
              value={to}
              onChange={(x: string) => {
                setTo(x);
                setPeriod('custom');
              }}
              aria-label={t('To')}
            />
          </label>
          <div className="flxdn-filters__end">
            <button className="flxdn-btn flxdn-btn--primary" type="submit">
              {t('Show')}
            </button>{' '}
            <a className="flxdn-btn" href={r.csvUrl}>
              <Ico name="download" /> {t('CSV')}
            </a>
          </div>
        </form>
        {r.description ? <p className="flxdn-muted">{r.description}</p> : null}
        {r.kpis.length ? (
          <div className="flxdn-kpis">
            {r.kpis.map((k) => (
              <div key={k.label} className="flxdn-kpi">
                <div className="flxdn-kpi__label">{k.label}</div>
                <div className="flxdn-kpi__value">{k.value}</div>
                {k.sub ? <div className="flxdn-kpi__sub">{k.sub}</div> : null}
              </div>
            ))}
          </div>
        ) : null}
        <div className="flxdn-panel">
          {!r.rows.length ? (
            <div className="flxdn-emptystate">
              <h2>{t('Nothing in this period')}</h2>
            </div>
          ) : (
            <div className="flxdn-tablewrap">
              <table className="flxdn-table flxdn-report">
                <thead>
                  <tr>
                    {r.columns.map((c) => (
                      <th key={c.label} scope="col" className={c.num ? 'num' : undefined}>
                        {c.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {r.rows.map((row, i) => {
                    const firstNum = r.columns.findIndex((c) => c.num);
                    return (
                      <tr key={i}>
                        {row.cells.map((cell, j) => {
                          const c = r.columns[j];
                          return (
                            <td key={j} className={c?.num ? 'num' : j === 0 ? 'flxdn-col-main' : undefined} data-label={c?.label}>
                              <span>{cell}</span>
                              {row.bar > 0 && j === firstNum ? <span className="flxdn-rbar" style={{ width: row.bar + '%' }} /> : null}
                            </td>
                          );
                        })}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          {r.note ? <p className="flxdn-muted">{r.note}</p> : null}
        </div>
      </div>
    );
  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Reports')} subtitle={t('Counts and totals grouped the way each report says. Reports are JSON and can be edited.')} actions={actions} notice={notice}>
      {body}
    </Screen>
  );
}

function Editor({ id, go, notice }: { id: string; go: (v: View) => void; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxDonateReportSource, { id });
  const s = q.data?.flxDonationReportSource;
  const [json, setJson] = useState<string | null>(null);
  const [ask, setAsk] = useState(false);
  const text = json ?? s?.json ?? '';
  const reload = () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateReport) });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateReportSource) });
  };
  const fail = (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const save = useFlxMutation(FlxDonationReportSave, {
    onSuccess: (r) => {
      const res = r.flxDonationReportSave;
      const err = res?.errors?.[0]?.message;
      if (err || !res?.id) {
        told({ kind: 'error', text: err || t('The action could not be completed.') });
        return;
      }
      told({ kind: 'ok', text: res.message || t('Saved.') });
      reload();
      go({ page: 'reports', id: res.id, period: '', from: '', to: '' });
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxDonationReportDelete, {
    onSuccess: (r) => {
      setAsk(false);
      told({ kind: 'ok', text: r.flxDonationReportDelete?.message || t('Saved.') });
      reload();
      go({ page: 'reports', id, period: '', from: '', to: '' });
    },
    onError: fail,
  });
  const back = (
    <button type="button" className="flxdn-back flxdn-linkbtn" onClick={() => go({ page: 'reports', id, period: '', from: '', to: '' })}>
      <span aria-hidden="true">‹</span> {t('Reports')}
    </button>
  );
  if (!s) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Reports')} actions={back} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  const busy = save.isPending || del.isPending;
  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={s.title || t('New report')} actions={back} notice={notice}>
      <div className="flxdn-grid flxdn-grid--2-1">
        <section className="flxdn-card">
          <form
            className="flxdn-form"
            onSubmit={(e) => {
              e.preventDefault();
              save.mutate({ input: { json: text, old: s.id } });
            }}
          >
            <div className="flxdn-field">
              <label htmlFor="flxdn-rj">{t('Report (JSON)')}</label>
              <textarea id="flxdn-rj" rows={22} className="flxdn-code" spellCheck={false} value={text} onChange={(e) => setJson(e.target.value)} />
            </div>
            <div className="flxdn-actions">
              <button className="flxdn-btn flxdn-btn--primary" type="submit" disabled={busy}>
                {t('Save')}
              </button>
              {s.edited ? (
                <button className="flxdn-btn flxdn-btn--danger" type="button" disabled={busy} onClick={() => setAsk(true)}>
                  {s.builtin ? t('Put back the original') : t('Delete')}
                </button>
              ) : null}
            </div>
          </form>
        </section>
        <aside className="flxdn-card">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{t('What you can use')}</h2>
          </div>
          {s.help.map((h) => (
            <div key={h.source}>
              <h3 className="flxdn-h3">
                <code>"source": "{h.source}"</code> {h.label}
              </h3>
              <p className="flxdn-help">
                <b>group</b> {h.group.join(', ')}
              </p>
              <p className="flxdn-help">
                <b>metrics</b> {h.metrics.join(', ')}
              </p>
            </div>
          ))}
          <p className="flxdn-help">
            <b>filter</b> <code>paid</code>, <code>type_slugs</code>, <code>type_ids</code>, <code>status</code>, <code>currency</code>, <code>partner_ids</code>, <code>source_ids</code>, <code>tracked</code>
          </p>
          <p className="flxdn-help">
            <b>period</b> {s.periods} · <b>date</b> created, done, target · <b>sort</b> "-amount" · <b>chart</b> "bar" · <b>limit</b>
          </p>
          <p className="flxdn-help">{t('Titles can be one text or {"tr": "...", "en": "..."}.')}</p>
        </aside>
      </div>
      {ask ? (
        <Modal title={s.builtin ? t('Put back the original') : t('Delete')} onClose={() => !busy && setAsk(false)}>
          <p>{t('Are you sure?')}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={busy} onClick={() => setAsk(false)}>
              {t('Close')}
            </button>
            <button type="button" className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid" disabled={busy} onClick={() => del.mutate({ input: { id: s.id } })}>
              {s.builtin ? t('Put back the original') : t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </Screen>
  );
}
