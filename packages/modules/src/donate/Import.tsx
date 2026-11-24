// Import from Excel (?page=flxb-import): the upload page with earlier imports, the preview of one import
// (?import=<id>: counts, summary, columns, options, matches, rows) and its run, which writes rows step by step
// while the page is open. The file upload stays a form post (the file goes with it).
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateImport, FlxDonationImportDelete, FlxDonationImportSave, FlxDonationImportStep } from '@fluxwp/graphql/operations';
import type { FlxDonateImportQuery } from '@fluxwp/graphql/types';
import { Check, DatePicker, Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';
import { Failed } from './Subscriptions';
import { useStartNotice } from './Partners';

type D = NonNullable<FlxDonateImportQuery['flxDonationImport']>;
type Pv = NonNullable<D['preview']>;
type Run = NonNullable<D['run']>;

const Kpis = ({ k }: { k: { label: string; value: string; accent: string }[] }) => (
  <div className="flxdn-kpis">
    {k.map((x) => (
      <div key={x.label} className="flxdn-kpi" style={x.accent ? ({ '--accent': x.accent } as React.CSSProperties) : undefined}>
        <div className="flxdn-kpi__label">{x.label}</div>
        <div className="flxdn-kpi__value">{x.value}</div>
      </div>
    ))}
  </div>
);

export function Import({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  useStartNotice(query);
  const [id, setId] = useState(() => new URLSearchParams(query).get('id') ?? '');
  const q = useFlxQuery(FlxDonateImport, { id });
  const d = q.data?.flxDonationImport;

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-import') return;
    const s = '?page=flxb-import' + (id ? '&import=' + encodeURIComponent(id) : '');
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [id, host.mode]);

  const back = id ? (
    <button type="button" className="flxdn-back flxdn-linkbtn" onClick={() => setId('')}>
      <span aria-hidden="true">‹</span> {t('Import from Excel')}
    </button>
  ) : null;
  const title = d && id ? d.file : t('Import from Excel');
  const subtitle = !d
    ? undefined
    : !id
      ? t('Bring in older donations and records from an Excel (.xlsx) or CSV file. You see everything before anything is written; a row is never imported twice.')
      : d.preview
        ? t('Check the columns and the matches, then start.')
        : d.run?.done
          ? t('Finished.')
          : t('Importing... Keep this page open; if it closes, opening it again continues where it stopped.');
  let body: ReactNode;
  if (!q.data) body = q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />;
  else if (!d) body = <p className="flxdn-muted">{t('The import was not found or has finished.')}</p>;
  else if (!id) body = <Upload d={d} open={setId} />;
  else if (d.preview) body = <Preview key={JSON.stringify(d.preview.columns) + JSON.stringify(d.preview.options)} id={id} p={d.preview} onGone={() => setId('')} />;
  else if (d.run) body = <Running id={id} r={d.run} again={() => setId('')} />;
  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={title} subtitle={subtitle} actions={back} notice={notice}>
      {body}
    </Screen>
  );
}

function Upload({ d, open }: { d: D; open: (id: string) => void }) {
  const host = useHost();
  const { t } = useDonate();
  const [busy, setBusy] = useState(false);
  return (
    <div className="flxdn-grid flxdn-grid--2-1">
      <section className="flxdn-card">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Upload a file')}</h2>
        </div>
        {host.mode === 'wp' ? (
          <form className="flxdn-form" method="post" action={d.uploadUrl} encType="multipart/form-data" onSubmit={() => setBusy(true)}>
            <input type="hidden" name="action" value="flxb_import_upload" />
            <input type="hidden" name="_wpnonce" value={d.uploadNonce} />
            <div className="flxdn-field">
              <label htmlFor="flxdn-if">{t('File')}</label>
              <input id="flxdn-if" type="file" name="file" accept=".xlsx,.csv,.txt,.tsv" required />
              <p className="flxdn-help">
                {t('The first row names the columns. Recognised: date, donor, phone, e-mail, on behalf of, donation type, option, quantity, amount, currency, payment, status, partner, brought in by, target date, referral, note.')}
              </p>
              <p className="flxdn-help">{t('Nothing is sent to donors or staff while importing (no e-mail, WhatsApp or certificate).')}</p>
            </div>
            <div className="flxdn-actions">
              <button className="flxdn-btn flxdn-btn--primary" type="submit" disabled={busy}>
                <Ico name="download" /> {busy ? t('Reading...') : t('Read the file')}
              </button>
            </div>
          </form>
        ) : (
          <p className="flxdn-muted">{t('Open this page in wp-admin to upload a file.')}</p>
        )}
      </section>
      <aside className="flxdn-card">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Earlier imports')}</h2>
        </div>
        {!d.earlier.length ? (
          <p className="flxdn-muted">{t('None yet.')}</p>
        ) : (
          <ul className="flxdn-list">
            {d.earlier.map((x) => (
              <li key={x.id}>
                <button type="button" className="flxdn-linkbtn" onClick={() => open(x.id)}>
                  <b>{x.file}</b>
                </button>{' '}
                · {x.state}
                <span className="flxdn-sub2">{x.sub}</span>
              </li>
            ))}
          </ul>
        )}
      </aside>
    </div>
  );
}

function Preview({ id, p, onGone }: { id: string; p: Pv; onGone: () => void }) {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [map, setMap] = useState<Record<number, string>>(() => Object.fromEntries(p.columns.map((c) => [c.col, c.field])));
  const [opt, setOpt] = useState(p.options);
  const [res, setRes] = useState<Record<string, string>>(() => Object.fromEntries(p.matches.flatMap((m) => m.rows.map((r) => [m.group + '|' + r.key, r.to]))));
  const [stale, setStale] = useState(false);
  const [ask, setAsk] = useState<'run' | 'delete' | null>(null);
  const reload = () => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateImport) });
  const fail = (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const save = useFlxMutation(FlxDonationImportSave, {
    onSuccess: (r) => {
      setAsk(null);
      const err = r.flxDonationImportSave?.errors?.[0]?.message;
      if (err) told({ kind: 'error', text: err });
      else told(null);
      reload();
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxDonationImportDelete, {
    onSuccess: (r) => {
      setAsk(null);
      told({ kind: 'ok', text: r.flxDonationImportDelete?.message || t('Deleted.') });
      reload();
      onGone();
    },
    onError: fail,
  });
  const send = (run: boolean) =>
    save.mutate({
      input: {
        id,
        map: Object.entries(map).map(([col, field]) => ({ col: Number(col), field })),
        matches: Object.entries(res).map(([k, value]) => {
          const i = k.indexOf('|');
          return { group: k.slice(0, i), key: k.slice(i + 1), value };
        }),
        type: opt.type,
        paid: opt.paid,
        keepDate: opt.keepDate,
        nodate: opt.nodate,
        nodateDay: opt.nodateDay,
        run,
      },
    });
  const touch = <T,>(f: (x: T) => void) => (x: T) => {
    f(x);
    setStale(true);
  };
  const busy = save.isPending || del.isPending;

  return (
    <div>
      <Kpis k={p.stats} />
      <section className="flxdn-card flxdn-card--gap">
        <div className="flxdn-card__head">
          <div>
            <h2 className="flxdn-card__title">{t('Summary')}</h2>
            <p className="flxdn-card__hint">{t('Only ready rows are counted. After changing a match, press "Apply and check again" to update it.')}</p>
          </div>
        </div>
        <Kpis k={p.summary} />
        {p.byRef.length ? (
          <div className="flxdn-tablewrap">
            <table className="flxdn-table flxdn-table--compact">
              <thead>
                <tr>
                  <th>{t('Referral')}</th>
                  <th className="num">{t('Records')}</th>
                  <th className="num">{t('Units')}</th>
                  <th className="num">{t('Amount')}</th>
                </tr>
              </thead>
              <tbody>
                {p.byRef.map((r) => (
                  <tr key={r.name}>
                    <td className="flxdn-col-main">{r.name}</td>
                    <td className="num" data-label={t('Records')}>
                      <span>{r.rows}</span>
                    </td>
                    <td className="num" data-label={t('Units')}>
                      <span>{r.units}</span>
                    </td>
                    <td className="num" data-label={t('Amount')}>
                      <span>{r.amount}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>

      <section className="flxdn-card flxdn-card--gap">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Columns')}</h2>
        </div>
        <div className="flxdn-fields-3">
          {p.columns.map((c) => (
            <div key={c.col} className="flxdn-field">
              <label htmlFor={'flxdn-ic' + c.col}>{c.header}</label>
              <select id={'flxdn-ic' + c.col} value={map[c.col] ?? ''} onChange={(e) => touch((v: string) => setMap({ ...map, [c.col]: v }))(e.target.value)}>
                {p.fields.map((f) => (
                  <option key={f.value} value={f.value}>
                    {f.label}
                  </option>
                ))}
              </select>
              {c.example ? <p className="flxdn-help">{c.example}</p> : null}
            </div>
          ))}
        </div>
      </section>

      <section className="flxdn-card flxdn-card--gap">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Options')}</h2>
        </div>
        <div className="flxdn-fields-2">
          <div className="flxdn-field">
            <label htmlFor="flxdn-io-type">{t('Donation type when the row has none')}</label>
            <select id="flxdn-io-type" value={opt.type} onChange={(e) => touch((v: string) => setOpt({ ...opt, type: v }))(e.target.value)}>
              {p.types.map((x) => (
                <option key={x.value} value={x.value}>
                  {x.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flxdn-field">
            <label htmlFor="flxdn-io-paid">{t('Payment when the row does not say')}</label>
            <select id="flxdn-io-paid" value={opt.paid ? '1' : '0'} onChange={(e) => touch((v: string) => setOpt({ ...opt, paid: v === '1' }))(e.target.value)}>
              <option value="1">{t('Paid')}</option>
              <option value="0">{t('Waiting for payment')}</option>
            </select>
          </div>
        </div>
        <div className="flxdn-fields-2">
          <div className="flxdn-field">
            <label htmlFor="flxdn-io-nd">{t('Date when the row has none')}</label>
            <select id="flxdn-io-nd" value={opt.nodate} onChange={(e) => touch((v: string) => setOpt({ ...opt, nodate: v }))(e.target.value)}>
              <option value="above">{t('Same as the row above')}</option>
              <option value="target">{t('The target date of the row')}</option>
              <option value="day">{t('A day I choose')}</option>
              <option value="today">{t('Today')}</option>
            </select>
          </div>
          {opt.nodate === 'day' ? (
            <div className="flxdn-field">
              <label htmlFor="flxdn-io-day">{t('Day')}</label>
              <DatePicker id="flxdn-io-day" value={opt.nodateDay} onChange={(v: string) => touch((x: string) => setOpt({ ...opt, nodateDay: x }))(v)} />
            </div>
          ) : null}
        </div>
        <p className="flxdn-help">{t('Imported records are history: no e-mail, WhatsApp message, certificate or ad conversion is sent to anyone.')}</p>
        <Check switch checked={opt.keepDate} onChange={(x) => touch((v: boolean) => setOpt({ ...opt, keepDate: v }))(x)}>
          {t('Keep the date in the file (otherwise today)')}
        </Check>
      </section>

      {p.matches.length ? (
        <section className="flxdn-card flxdn-card--gap">
          <div className="flxdn-card__head">
            <div>
              <h2 className="flxdn-card__title">{t('Matches')}</h2>
              <p className="flxdn-card__hint">{t('Each different value in the file is matched once.')}</p>
            </div>
          </div>
          <div className="flxdn-matches">
            {p.matches.map((m) => (
              <div key={m.group} className="flxdn-match">
                <h3 className="flxdn-h3">{m.title}</h3>
                <table className="flxdn-table flxdn-table--compact">
                  <tbody>
                    {m.rows.map((r) => (
                      <tr key={r.key}>
                        <td>
                          {r.raw} <span className="flxdn-muted">×{r.count}</span>
                        </td>
                        <td>
                          <select value={res[m.group + '|' + r.key] ?? ''} aria-label={r.raw} onChange={(e) => touch((v: string) => setRes({ ...res, [m.group + '|' + r.key]: v }))(e.target.value)}>
                            {m.choices.map((c) => (
                              <option key={c.value} value={c.value}>
                                {c.label}
                              </option>
                            ))}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}
          </div>
        </section>
      ) : null}

      <div className="flxdn-actions flxdn-actions--sticky">
        <button className="flxdn-btn" type="button" disabled={busy} onClick={() => send(false)}>
          {t('Apply and check again')}
        </button>{' '}
        <button className="flxdn-btn flxdn-btn--primary" type="button" disabled={busy || stale || !p.ready} onClick={() => setAsk('run')}>
          {p.runLabel}
        </button>
        {stale ? <span className="flxdn-help">{t('You changed something: press "Apply and check again" first.')}</span> : null}
      </div>

      <section className="flxdn-card flxdn-card--gap">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Rows')}</h2>
        </div>
        <div className="flxdn-tablewrap">
          <table className="flxdn-table flxdn-table--compact">
            <thead>
              <tr>
                <th>#</th>
                <th>{t('Donor')}</th>
                <th>{t('Date')}</th>
                <th>{t('Donation type')}</th>
                <th className="num">{t('Quantity')}</th>
                <th className="num">{t('Amount')}</th>
                <th>{t('Status')}</th>
                <th>{t('Check')}</th>
              </tr>
            </thead>
            <tbody>
              {p.rows.map((r) => (
                <tr key={r.n}>
                  <td>{r.n}</td>
                  <td className="flxdn-col-main">
                    {r.who}
                    {r.sub ? <span className="flxdn-sub2">{r.sub}</span> : null}
                  </td>
                  <td data-label={t('Date')}>
                    <span className={r.dated ? undefined : 'flxdn-muted'}>{r.date}</span>
                  </td>
                  <td data-label={t('Donation type')}>
                    <span>{r.type}</span>
                  </td>
                  <td className="num" data-label={t('Quantity')}>
                    <span>{r.quantity}</span>
                  </td>
                  <td className="num" data-label={t('Amount')}>
                    <span>{r.amount}</span>
                  </td>
                  <td data-label={t('Status')}>
                    <span>{r.status}</span>
                  </td>
                  <td data-label={t('Check')}>
                    <span>
                      {r.checks.map((c, i) => (
                        <span key={i} className={'flxdn-pill flxdn-pill--' + c.tone}>
                          {c.label}
                        </span>
                      ))}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {p.more ? <p className="flxdn-muted">{p.more}</p> : null}
      </section>
      <div className="flxdn-actions">
        <button className="flxdn-btn flxdn-btn--danger" type="button" disabled={busy} onClick={() => setAsk('delete')}>
          {t('Discard this file')}
        </button>
      </div>

      {ask ? (
        <Modal title={ask === 'run' ? t('Start the import?') : t('Discard this file')} onClose={() => !busy && setAsk(null)}>
          <p>{ask === 'run' ? p.confirm : t('Are you sure?')}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={busy} onClick={() => setAsk(null)}>
              {t('Close')}
            </button>
            <button
              type="button"
              className={'flxdn-btn flxdn-btn--primary' + (ask === 'delete' ? ' flxdn-btn--danger-solid' : '')}
              disabled={busy}
              onClick={() => (ask === 'run' ? send(true) : del.mutate({ input: { id } }))}
            >
              {ask === 'run' ? t('Import') : t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}

/** A running import writes its next rows while the page is open; a failed step is tried again (3 times). */
function Running({ id, r, again }: { id: string; r: Run; again: () => void }) {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const fails = useRef(0);
  const step = useFlxMutation(FlxDonationImportStep, {
    onSuccess: (x) => {
      const err = x.flxDonationImportStep?.errors?.[0]?.message;
      if (err) {
        told({ kind: 'error', text: err });
        return;
      }
      fails.current = 0;
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateImport) });
    },
    onError: () => {
      fails.current += 1;
      if (fails.current < 4) setTimeout(() => step.mutate({ input: { id } }), 3000 * fails.current);
      else told({ kind: 'error', text: t('The action could not be completed.') });
    },
  });
  // Every new state of the run (after a step and its refetch) starts the next step.
  useEffect(() => {
    if (!r.done && !step.isPending) step.mutate({ input: { id } });
  }, [r.pos, r.done]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <section className="flxdn-card">
      <div className="flxdn-progress">
        <span style={{ width: r.percent + '%' }} />
      </div>
      <p>{r.progress}</p>
      <Kpis k={r.kpis} />
      {r.done ? (
        <div className="flxdn-actions">
          <Go className="flxdn-btn flxdn-btn--primary" href={r.nextUrl}>
            {r.nextLabel}
          </Go>{' '}
          <button type="button" className="flxdn-btn" onClick={again}>
            {t('Another file')}
          </button>
        </div>
      ) : null}
      {r.errors.length ? (
        <>
          <h3 className="flxdn-h3">{t('Errors')}</h3>
          <div className="flxdn-tablewrap">
            <table className="flxdn-table flxdn-table--compact">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{t('Name')}</th>
                  <th>{t('Reason')}</th>
                </tr>
              </thead>
              <tbody>
                {r.errors.map((e) => (
                  <tr key={e.n + e.msg}>
                    <td>{e.n}</td>
                    <td>{e.name}</td>
                    <td>{e.msg}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      ) : null}
    </section>
  );
}
