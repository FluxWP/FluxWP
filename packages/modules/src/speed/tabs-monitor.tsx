// Performance › Summary, Records and one record. Reads flxSpeedLive / Hints / Summary / Records / Record.
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSpeedLive, FlxSpeedProfile, FlxSpeedRecord, FlxSpeedRecords, FlxSpeedRecordsClear, FlxSpeedSummary } from '@fluxwp/graphql/operations';
import type { FlxSpeedSummaryQuery } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, Cards, ErrorBox, Table, errorText, fmtKb, fmtMs, num, useSpeed, useT, type RecordFilter, type RecordRow } from './ui';

const PERIODS: [number, string][] = [
  [1, 'Last hour'],
  [24, 'Last 24 hours'],
  [168, 'Last 7 days'],
];

/** Live server cards; refreshed every 15 seconds while the screen is open. */
export function LiveCards({ note = true }: { note?: boolean }) {
  const { t } = useT();
  const q = useFlxQuery(FlxSpeedLive, {}, { refetchInterval: 15_000, refetchIntervalInBackground: false });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const l = q.data.flxSpeedLive;
  return (
    <>
      <div className="flxpf-cards flxpf-live">
        {l.meters.map((m) => {
          const pct = m.pct;
          const cls = pct === null ? '' : pct >= 90 ? 'is-err' : pct >= 70 ? 'is-warn' : '';
          return (
            <div key={m.key} className={'flxpf-card flxpf-meter ' + cls}>
              <span>{m.label}</span>
              <b>{m.text}</b>
              {pct !== null && (
                <i className="flxpf-bar">
                  <em style={{ width: Math.min(100, Math.max(0, pct)) + '%' }} />
                </i>
              )}
              <small>{m.sub}</small>
            </div>
          );
        })}
      </div>
      {note && <p className="flxpf-desc">{l.note}</p>}
    </>
  );
}

function FlagBadges({ flags }: { flags: { key: string; label: string }[] }) {
  return (
    <>
      {flags.map((f) => (
        <span key={f.key} className={'flxpf-flag flxpf-flag-' + f.key}>
          {f.label}
        </span>
      ))}{' '}
    </>
  );
}

function Period({ value, onChange }: { value: number; onChange: (h: number) => void }) {
  const { t } = useT();
  return (
    <div className="flxpf-filters">
      <select value={value} onChange={(e) => onChange(Number(e.currentTarget.value))} aria-label={t('Period')}>
        {PERIODS.map(([h, l]) => (
          <option key={h} value={h}>
            {t(l)}
          </option>
        ))}
      </select>
    </div>
  );
}

export function SummaryTab() {
  const { t } = useT();
  const { hours } = useSpeed();
  const q = useFlxQuery(FlxSpeedSummary, { hours });
  return (
    <>
      <LiveCards />
      {q.isLoading && <Loading />}
      {q.isError && <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />}
      {q.data && <SummaryBody data={q.data} />}
    </>
  );
}

function SummaryBody({ data }: { data: FlxSpeedSummaryQuery }) {
  const { t, lang: lg } = useT();
  const { go, hours, setHours } = useSpeed();
  const s = data.flxSpeedSummary;
  const hints = data.flxSpeedHints;
  const order = ['fatal', 'error', '5xx', 'slow', 'slow_query', 'http_fail'];
  const flags = order.map((k) => s.flags.find((f) => f.key === k)).filter((f): f is NonNullable<typeof f> => !!f && f.count > 0);
  return (
    <>
      {hints.length > 0 && (
        <div className="flxpf-hints">
          <h2>{t('Suggestions')}</h2>
          <ul>
            {hints.map((h, i) => (
              <li key={i} className={'is-' + h.status.toLowerCase()}>
                <strong>{h.label}:</strong> {h.message}
                {h.actionUrl && (
                  <>
                    {' '}
                    <a href={h.actionUrl}>{h.actionLabel}</a>
                  </>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}
      <Period value={hours} onChange={setHours} />
      <Cards>
        <Card label={t('Recorded requests')} value={num(s.recorded, lg)} />
        <Card label={t('Average time')} value={fmtMs(s.msAvg, lg)} />
        <Card label="p95" value={fmtMs(s.msP95, lg)} />
        <Card label={t('Longest')} value={fmtMs(s.msMax, lg)} />
        {flags.map((f) => (
          <Card key={f.key} label={f.label} value={num(f.count, lg)} tone={f.tone} onClick={() => go('records', { filter: { flag: f.key, hours: String(hours) } })} />
        ))}
      </Cards>
      <p className="flxpf-desc">{t('Times are calculated from recorded requests: sampled requests plus slow/failing ones, so the average may look higher than the real average.')}</p>

      <h2>{t('By request type')}</h2>
      <p className="flxpf-desc">{t('The time visitors wait is the "Front end" row. Cron, Ajax and REST are mostly background work (backup step, scheduled task, Heartbeat); when they take long they do not slow pages directly, but they keep the server busy.')}</p>
      <Table
        head={[t('Type'), t('Request'), t('Average'), 'p50', 'p95', t('Longest')]}
        numeric={[1, 2, 3, 4, 5]}
        empty={t('No records.')}
        rows={s.byType.map((r) => [
          <button type="button" className="flxpf-linkbtn" onClick={() => go('records', { filter: { type: r.type, hours: String(hours), slow: true } })}>
            {r.label}
          </button>,
          num(r.count, lg),
          fmtMs(r.msAvg, lg),
          fmtMs(r.msP50, lg),
          fmtMs(r.msP95, lg),
          fmtMs(r.msMax, lg),
        ])}
      />

      <h2>{t('Slowest URLs')}</h2>
      <Table
        head={[t('URL'), t('Request'), t('Average'), t('Longest'), t('Errors')]}
        numeric={[1, 2, 3, 4]}
        empty={t('No records.')}
        rows={s.slowestUrls.map((u) => [
          <button type="button" className="flxpf-linkbtn" onClick={() => go('records', { filter: { q: u.url, slow: true } })}>
            {u.url}
          </button>,
          num(u.count, lg),
          fmtMs(u.msAvg, lg),
          fmtMs(u.msMax, lg),
          num(u.errors, lg),
        ])}
      />

      <h2>{t('Query time by component (per request)')}</h2>
      <Table
        head={[t('Component'), t('Request'), t('Queries'), t('Per request')]}
        numeric={[1, 2, 3]}
        empty={t('No records.')}
        rows={s.components.map((c) => [c.component, num(c.requests, lg), num(c.queries, lg), fmtMs(c.msPerRequest, lg)])}
      />

      <h2>{t('Slow and failed queries')}</h2>
      <Table
        head={['SQL', t('Component'), t('Count'), t('Average'), t('Longest')]}
        numeric={[2, 3, 4]}
        empty={t('No records.')}
        rows={s.slowQueries.map((x) => [
          <>
            <code>{x.sql}</code>
            {x.error && <div className="flxpf-errtext">{x.error}</div>}{' '}
            <button type="button" className="flxpf-linkbtn" onClick={() => go('records', { id: x.lastId })}>
              #{x.lastId}
            </button>
          </>,
          x.component,
          num(x.count, lg),
          fmtMs(x.msAvg, lg),
          fmtMs(x.msMax, lg),
        ])}
      />

      <h2>{t('PHP errors')}</h2>
      <Table
        head={[t('Level'), t('Message'), t('Component'), t('Request')]}
        numeric={[3]}
        empty={t('No records.')}
        rows={s.phpErrors.map((e) => [
          e.level,
          <>
            {e.message}
            <br />
            <code>{e.file}</code>{' '}
            <button type="button" className="flxpf-linkbtn" onClick={() => go('records', { id: e.lastId })}>
              #{e.lastId}
            </button>
          </>,
          e.component,
          num(e.requests, lg),
        ])}
      />

      <h2>{t('HTTP API calls')}</h2>
      <Table
        head={[t('Endpoint'), t('Component'), t('Count'), t('Failed'), t('Average'), t('Last error')]}
        numeric={[2, 3, 4]}
        empty={t('No records.')}
        rows={s.httpCalls.map((h) => [<code>{h.endpoint}</code>, h.component, num(h.count, lg), num(h.failed, lg), fmtMs(h.msAvg, lg), h.lastError])}
      />
    </>
  );
}

const PAGE = 30;

export function RecordsTab({ filter, setFilter }: { filter: RecordFilter; setFilter: (f: RecordFilter) => void }) {
  const { t, lang } = useT();
  const { config, go, notify } = useSpeed();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [draft, setDraft] = useState<RecordFilter>(filter);
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [page, setPage] = useState(0);
  const [profileUrl, setProfileUrl] = useState('');
  const [confirm, setConfirm] = useState(false);

  useEffect(() => {
    setDraft(filter);
    setCursors([null]);
    setPage(0);
  }, [filter]);

  const vars = {
    first: PAGE,
    after: cursors[page] ?? null,
    hours: filter.hours ? Number(filter.hours) : null,
    flag: filter.flag || null,
    type: filter.type || null,
    q: filter.q || null,
    slow: filter.slow,
  };
  const q = useFlxQuery(FlxSpeedRecords, vars);
  const clear = useFlxMutation(FlxSpeedRecordsClear, {
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSpeedRecords) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSpeedSummary) });
      notify('success', t('All records deleted.'));
    },
    onError: (e) => notify('error', errorText(e, t('The action could not be completed.'))),
  });
  const profile = useFlxMutation(FlxSpeedProfile, {
    onSuccess: (r) => {
      const p = r.flxSpeedProfile;
      if (p?.errors.length) return notify('error', p.errors.map((e) => e.message).join(' '));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSpeedRecords) });
      if (p?.recordId) go('records', { id: p.recordId });
    },
    onError: (e) => notify('error', errorText(e, t('The action could not be completed.'))),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    setFilter(draft);
  };
  const submitProfile = (e: FormEvent) => {
    e.preventDefault();
    profile.mutate({ input: { url: profileUrl } });
  };
  const conn = q.data?.flxSpeedRecords;
  const total = conn?.totalCount ?? 0;
  const pages = Math.max(1, Math.ceil(total / PAGE));
  const typeLabel = (k: string) => config.types.find((x) => x.key === k)?.label ?? k;
  const upd = (patch: Partial<RecordFilter>) => setDraft((d) => ({ ...d, ...patch }));

  return (
    <>
      {!config.recording && <div className="flxpf-notice is-warn inline">{t('Recording is off: only the live panel works.')}</div>}
      <div className="flxpf-toolbar">
        <form className="flxpf-filters" onSubmit={submit}>
          <select value={draft.hours} onChange={(e) => upd({ hours: e.currentTarget.value })} aria-label={t('Period')}>
            <option value="">{t('All time')}</option>
            {PERIODS.map(([h, l]) => (
              <option key={h} value={String(h)}>
                {t(l)}
              </option>
            ))}
          </select>
          <select value={draft.flag} onChange={(e) => upd({ flag: e.currentTarget.value })} aria-label={t('Flags')}>
            <option value="">{t('All flags')}</option>
            {config.flags.map((f) => (
              <option key={f.key} value={f.key}>
                {f.label}
              </option>
            ))}
          </select>
          <select value={draft.type} onChange={(e) => upd({ type: e.currentTarget.value })} aria-label={t('Type')}>
            <option value="">{t('All types')}</option>
            {config.types.map((f) => (
              <option key={f.key} value={f.key}>
                {f.label}
              </option>
            ))}
          </select>
          <select value={draft.slow ? 'slow' : 'new'} onChange={(e) => upd({ slow: e.currentTarget.value === 'slow' })} aria-label={t('Order')}>
            <option value="new">{t('Newest')}</option>
            <option value="slow">{t('Slowest')}</option>
          </select>
          <input type="search" value={draft.q} onChange={(e) => upd({ q: e.currentTarget.value })} placeholder={t('Search URL')} />
          <button type="submit" className="flxpf-btn">
            {t('Filter')}
          </button>
        </form>
        <form className="flxpf-filters" onSubmit={submitProfile}>
          <input type="text" value={profileUrl} onChange={(e) => setProfileUrl(e.currentTarget.value)} placeholder="/donate/" aria-label={t('URL to profile')} />
          <button type="submit" className="flxpf-btn is-primary" disabled={profile.isPending}>
            {t('Profile page')}
          </button>
        </form>
      </div>
      <p className="flxpf-desc">{t('"Profile page" opens the URL as a visitor (bypassing cache) and records it in full detail.')}</p>
      {q.isLoading && <Loading />}
      {q.isError && <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />}
      {conn && (
        <>
          <div className="flxpf-tablewrap">
            <table className="flxpf-list">
              <thead>
                <tr>
                  <th>{t('Time')}</th>
                  <th>{t('URL')}</th>
                  <th className="n">{t('Status')}</th>
                  <th className="n">{t('Time')}</th>
                  <th className="n">{t('Memory')}</th>
                  <th className="n">{t('Queries')}</th>
                  <th className="n">HTTP</th>
                  <th className="n">{t('Errors')}</th>
                  <th>{t('Flags')}</th>
                </tr>
              </thead>
              <tbody>
                {conn.nodes.length === 0 && (
                  <tr>
                    <td colSpan={9} className="flxpf-empty">
                      {t('No records yet. Slow or failing requests and sampled requests appear here.')}
                    </td>
                  </tr>
                )}
                {conn.nodes.map((r) => (
                  <RecordLine key={r.databaseId} r={r} lang={lang} typeLabel={typeLabel} open={() => go('records', { id: r.databaseId })} />
                ))}
              </tbody>
            </table>
          </div>
          <div className="flxpf-pager">
            <span>{t('Records: %s', num(total, lang))}</span>
            {pages > 1 && (
              <>
                <button type="button" className="flxpf-btn is-small" disabled={page === 0} onClick={() => setPage(page - 1)}>
                  ‹
                </button>
                <span>
                  {page + 1} / {pages}
                </span>
                <button
                  type="button"
                  className="flxpf-btn is-small"
                  disabled={!conn.pageInfo.hasNextPage}
                  onClick={() => {
                    setCursors((c) => {
                      const n = c.slice(0, page + 1);
                      n.push(conn.pageInfo.endCursor ?? null);
                      return n;
                    });
                    setPage(page + 1);
                  }}
                >
                  ›
                </button>
              </>
            )}
          </div>
          {config.canManage && total > 0 && (
            <button type="button" className="flxpf-linkbtn is-danger" onClick={() => setConfirm(true)}>
              {t('Delete all records')}
            </button>
          )}
        </>
      )}
      {confirm && (
        <Modal title={t('Delete all records')} onClose={() => setConfirm(false)}>
          <p className="flxpf-confirm">{t('Delete all records?')}</p>
          <div className="flxpf-modal-actions">
            <button type="button" className="flxpf-btn" onClick={() => setConfirm(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxpf-btn is-danger"
              onClick={() => {
                setConfirm(false);
                clear.mutate({});
              }}
            >
              {t('Delete all records')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function RecordLine({ r, lang, typeLabel, open }: { r: RecordRow; lang: string; typeLabel: (k: string) => string; open: () => void }) {
  const { t } = useT();
  const bad = r.flags.some((f) => ['fatal', 'error', '5xx'].includes(f.key));
  const link = (c: React.ReactNode) => (
    <button type="button" className="flxpf-linkbtn" onClick={open}>
      {c}
    </button>
  );
  return (
    <tr className={bad ? 'flxpf-row-err' : undefined}>
      <td data-l={t('Time')}>{link(r.at.short)}</td>
      <td data-l={t('URL')} className="flxpf-url">
        {link(
          <>
            <span className="flxpf-m">{r.method}</span> {r.url}
          </>,
        )}{' '}
        <span className="flxpf-type">{r.typeLabel || typeLabel(r.type)}</span>
      </td>
      <td data-l={t('Status')} className="n">
        {r.status}
      </td>
      <td data-l={t('Time')} className="n">
        {fmtMs(r.ms, lang)}
      </td>
      <td data-l={t('Memory')} className="n">
        {fmtKb(r.memKb, lang)}
      </td>
      <td data-l={t('Queries')} className="n">
        {num(r.queries, lang)}
      </td>
      <td data-l="HTTP" className="n">
        {num(r.http, lang)}
      </td>
      <td data-l={t('Errors')} className="n">
        {num(r.errors, lang)}
      </td>
      <td data-l={t('Flags')}>
        <FlagBadges flags={r.flags} />
      </td>
    </tr>
  );
}

/** One record: the shared request panel (assets/panel.js) draws it, the same script the bottom panel uses. */
export function RecordDetail({ id }: { id: number }) {
  const { t } = useT();
  const { go } = useSpeed();
  const q = useFlxQuery(FlxSpeedRecord, { id });
  const rootRef = useRef<HTMLDivElement>(null);
  const d = q.data?.flxSpeedRecord ?? null;

  useEffect(() => {
    const host = rootRef.current;
    if (!host || !d) return undefined;
    const nodes: HTMLElement[] = [];
    const json = (key: string, value: unknown) => {
      const s = document.createElement('script');
      s.type = 'application/json';
      s.id = key;
      s.textContent = JSON.stringify(value).replace(/</g, '\\u003c');
      host.appendChild(s);
      nodes.push(s);
    };
    json('flxp-data', d.data);
    json('flxp-cfg', { mode: 'embed', slowQ: d.panel.slowQueryMs, slowMs: d.panel.slowMs, labels: d.labels });
    json('flxp-i18n', d.panel.strings);
    if (!document.getElementById('flxp-css')) {
      const l = document.createElement('link');
      l.rel = 'stylesheet';
      l.id = 'flxp-css';
      l.href = d.panel.styleUrl;
      document.head.appendChild(l);
    }
    const s = document.createElement('script');
    s.src = d.panel.scriptUrl;
    host.appendChild(s);
    nodes.push(s);
    return () => {
      nodes.forEach((n) => n.remove());
      const root = document.getElementById('flxp-panel');
      if (root) {
        root.innerHTML = '';
        root.hidden = true;
        root.className = 'flxp';
      }
    };
  }, [d]);

  return (
    <>
      <p>
        <button type="button" className="flxpf-linkbtn" onClick={() => go('records')}>
          ← {t('Records')}
        </button>
      </p>
      {q.isLoading && <Loading />}
      {q.isError && <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />}
      {q.data && !d && <div className="flxpf-notice is-error">{t('Record not found.')}</div>}
      {d && (
        <>
          <p className="flxpf-detail-head">
            <strong>#{d.record.databaseId}</strong> · {d.record.at.date} {d.record.at.short.split(' ')[1]} · <FlagBadges flags={d.record.flags} />
          </p>
          <div ref={rootRef}>
            <div id="flxp-panel" className="flxp" hidden />
          </div>
        </>
      )}
    </>
  );
}
