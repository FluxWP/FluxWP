// Flux Analytics › Product telemetry: anonymous data FluxWP installs send once a day (usage, versions,
// PHP errors, setup wizard). Only managers, and only when the telemetry hub is on in Settings.
import { useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAnalyticsTelemetry, FlxAnalyticsTelemetryErrors, FlxAnalyticsTelemetryForget } from '@fluxwp/graphql/operations';
import type { FlxAnalyticsTelemetryQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { ConfirmDialog, Kpi, Panel, Shell, Tabs, useFilters, useFmt, withAnalytics } from './lib';

type T = FlxAnalyticsTelemetryQuery['flxAnalyticsTelemetry'];
type Tab = 'overview' | 'errors' | 'onboarding';

/** Share table: the first value is a count, drawn as a bar against the total. */
function Share({ title, rows, head, total, empty }: { title: string; rows: { key: string; label: ReactNode; vals: ReactNode[]; n: number }[]; head: string[]; total: number; empty?: string }) {
  const { t, pf } = useFmt();
  return (
    <Panel title={title}>
      {!rows.length ? (
        <div className="flxan-empty">{empty ?? t('No data in this range.')}</div>
      ) : (
        <table className="flxan-table">
          <thead>
            <tr>
              {head.map((h, i) => (
                <th key={i} className={i ? 'n' : ''}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.key}>
                <td className="flxan-lbl">
                  <span className="bar" style={{ width: (total ? Math.min(100, (r.n / total) * 100) : 0).toFixed(1) + '%' }} />
                  <span className="txt">{r.label}</span>
                </td>
                {r.vals.map((v, i) => (
                  <td key={i} className="n">
                    {v}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <span hidden>{pf(0)}</span>
    </Panel>
  );
}

function Telemetry() {
  const { t, n, pf } = useFmt();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const { param, setParam } = useFilters(['tab', 'r', 'c', 'p', 't']);
  const tab = (['overview', 'errors', 'onboarding'].includes(param('tab') ?? '') ? param('tab') : 'overview') as Tab;
  const days = [7, 30, 90].includes(Number(param('r'))) ? Number(param('r')) : 30;
  const country = param('c') ?? '';
  const plugin = param('p') ?? '';
  const type = param('t') ?? '';
  const [clearAll, setClearAll] = useState(false);
  const [notice, setNotice] = useState('');

  const q = useFlxQuery(FlxAnalyticsTelemetry, { days, country }, { placeholderData: (p) => p });
  const err = useFlxQuery(FlxAnalyticsTelemetryErrors, { days, country, plugin, type }, { enabled: tab === 'errors', placeholderData: (p) => p });
  const forget = useFlxMutation(FlxAnalyticsTelemetryForget, {
    onSuccess: (r) => {
      if (r.flxAnalyticsTelemetryForget?.errors.length) return setNotice(r.flxAnalyticsTelemetryForget.errors[0]?.message ?? '');
      setNotice(t('Error record removed. It will reappear if it happens again.'));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAnalyticsTelemetryErrors) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAnalyticsTelemetry) });
    },
    onError: (e) => setNotice(e.message),
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <Shell title={t('Product telemetry')}><div className="flxan-banner">{q.error?.message || t('Could not load. Try again in a moment.')}</div></Shell>;
  const d: T = q.data.flxAnalyticsTelemetry;
  const o = d.overview;
  const installs = o.installs;
  const share = (c: number) => (installs ? pf((c / installs) * 100) + '%' : '0%');

  return (
    <Shell
      title={t('Product telemetry')}
      notice={notice ? <div className="flxan-notice is-success" role="status"><span>{notice}</span><button type="button" className="flxan-x" aria-label={t('Close')} onClick={() => setNotice('')}>×</button></div> : undefined}
      head={
        <>
          <Tabs value={String(days) as '7' | '30' | '90'} onChange={(k) => setParam('r', k)} items={[7, 30, 90].map((r) => ({ key: String(r) as '7' | '30' | '90', label: t('%s days', r) }))} />
          {(d.countries.length > 0 || country) && (
            <select aria-label={t('Country')} value={country} onChange={(e) => setParam('c', e.target.value)}>
              <option value="">{t('All countries')}</option>
              {d.countries.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.name} ({n(c.sites)})
                </option>
              ))}
            </select>
          )}
        </>
      }
    >
      <p className="description" style={{ margin: '-8px 0 14px' }}>{t('Anonymous data sent once a day by sites that tick "Help us improve the product" in FluxWP Panel. IP and site address are not stored; records are kept for 90 days.')}</p>
      <Tabs value={tab} onChange={(k) => setParam('tab', k === 'overview' ? null : k)} items={[{ key: 'overview', label: t('Overview') }, { key: 'errors', label: t('Errors') }, { key: 'onboarding', label: t('Setup wizard') }]} />

      {tab === 'overview' && (
        <>
          <div className="flxan-cards">
            <Kpi label={t('Reporting sites')} value={n(installs)} sub={t('Unique installs')} tone="green" icon="users" />
            <Kpi label={t('Reports')} value={n(o.reports)} sub={t('At most one per site per day')} tone="blue" icon="eye" />
            <Kpi label={t('Distinct errors')} value={n(o.errorsOpen)} sub={t('%s fatal', n(o.fatalOpen))} tone="rose" icon="bounce" />
            <Kpi label={t('Error events')} value={n(o.events)} sub={t('Total occurrences')} tone="amber" icon="layers" />
          </div>
          {!installs ? (
            <div className="flxan-panel flxan-empty--big">
              <b>{t('No reports yet.')}</b>
              <p>{t('FluxWP Panel on sites sends to this URL:')}</p>
              <code>{d.endpoint}</code>
            </div>
          ) : (
            <>
              <Share title={t('Mobile app: daily active accounts')} head={[t('UTC day / platform'), t('Consenting accounts')]} total={Math.max(1, ...o.appUsage.map((x) => x.accounts))} empty={t('No eligible totals yet. Both device and site consent are needed.')} rows={o.appUsage.map((x) => ({ key: x.day + x.platform, label: x.day + ' · ' + x.platform, vals: [n(x.accounts)], n: x.accounts }))} />
              <div className="flxan-grid">
                <Share title={t('Plugin usage')} head={[t('Package'), t('Site'), t('Share'), t('Common version')]} total={installs} rows={o.usage.map((x) => ({ key: x.slug, label: x.name, vals: [n(x.sites), share(x.sites), x.topVersion], n: x.sites }))} />
                <Share title={t('Used together')} head={[t('Pair'), t('Site'), t('Share')]} total={installs} rows={o.pairs.map((x) => ({ key: x.aName + x.bName, label: x.aName + ' + ' + x.bName, vals: [n(x.sites), share(x.sites)], n: x.sites }))} />
                <div className="flxan-wide">
                  <Share title={t('Most common package sets')} head={[t('Set (excluding FluxWP Panel)'), t('Site'), t('Share')]} total={installs} rows={o.combos.map((x, i) => ({ key: String(i), label: <>{x.names.length ? x.names.join(', ') : t('FluxWP Panel only')} <small>{x.names.length}</small></>, vals: [n(x.sites), share(x.sites)], n: x.sites }))} />
                </div>
                <Share title="PHP" head={[t('Version'), t('Site'), t('Share')]} total={installs} rows={o.php.map((x) => ({ key: x.key, label: 'PHP ' + x.label, vals: [n(x.sites), share(x.sites)], n: x.sites }))} />
                <Share title="WordPress" head={[t('Version'), t('Site'), t('Share')]} total={installs} rows={o.wp.map((x) => ({ key: x.key, label: 'WordPress ' + x.label, vals: [n(x.sites), share(x.sites)], n: x.sites }))} />
                <Share title={t('Site language')} head={[t('Language'), t('Site'), t('Share')]} total={installs} rows={o.locales.map((x) => ({ key: x.key, label: x.label, vals: [n(x.sites), share(x.sites)], n: x.sites }))} />
                {!country && (
                  <Share
                    title={t('Country')}
                    head={[t('Country'), t('Site'), t('Share')]}
                    total={installs}
                    rows={o.countries.map((x) => ({
                      key: x.key,
                      label: x.key !== '?' ? <><a className="tm-cc-a" href="#" onClick={(e) => { e.preventDefault(); setParam('c', x.key.toLowerCase()); }}>{x.label}</a> <small>{x.key}</small></> : x.label,
                      vals: [n(x.sites), share(x.sites)],
                      n: x.sites,
                    }))}
                  />
                )}
              </div>
              <p className="description">{t('Country comes from the sending site\'s settings (WooCommerce store country or the Settings › General time zone). IP addresses are never looked up or stored. Click a country to filter every tab.')}</p>
            </>
          )}
        </>
      )}

      {tab === 'errors' && (
        <div className="flxan-panel">
          <div className="tm-filter">
            <select aria-label={t('Package')} value={plugin} onChange={(e) => setParam('p', e.target.value)}>
              <option value="">{t('All packages')}</option>
              {(err.data?.flxAnalyticsTelemetryErrors.plugins ?? []).map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </select>
            <select aria-label={t('Type')} value={type} onChange={(e) => setParam('t', e.target.value)}>
              <option value="">{t('All types')}</option>
              <option value="fatal">{t('Fatal only')}</option>
              {['E_WARNING', 'E_NOTICE', 'E_DEPRECATED', 'E_USER_WARNING', 'E_USER_NOTICE', 'E_USER_DEPRECATED'].map((x) => (
                <option key={x} value={x}>
                  {x}
                </option>
              ))}
            </select>
            <span className="flxan-muted" style={{ marginLeft: 'auto' }}>{t('%s records', n(err.data?.flxAnalyticsTelemetryErrors.items.length ?? 0))}</span>
          </div>
          {err.isLoading ? (
            <Loading />
          ) : !err.data?.flxAnalyticsTelemetryErrors.items.length ? (
            <p className="flxan-empty">{t('No errors in this range.')}</p>
          ) : (
            <>
              <div style={{ overflowX: 'auto' }}>
                <table className="flxan-table tm-err">
                  <thead>
                    <tr>
                      <th>{t('Error')}</th>
                      <th>{t('Package')}</th>
                      <th className="n">{t('Site')}</th>
                      <th className="n">{t('Count')}</th>
                      <th className="n tm-hide-m">{t('Last')}</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {err.data.flxAnalyticsTelemetryErrors.items.map((e) => (
                      <tr key={e.hash}>
                        <td>
                          <span className={'tm-t ' + (e.fatal ? 'fatal' : e.type.includes('WARNING') ? 'warn' : '')}>{e.type.replace('E_', '')}</span>
                          {e.more ? (
                            <details>
                              <summary>
                                <p className="tm-msg">{e.message} <span className="flxan-muted">▾</span></p>
                              </summary>
                              <p className="tm-msg flxan-muted">{e.more}</p>
                            </details>
                          ) : (
                            <p className="tm-msg">{e.message}</p>
                          )}
                          <code className="flxan-muted" style={{ fontSize: 11 }}>{e.file + (e.line ? ':' + e.line : '')}</code>
                        </td>
                        <td style={{ whiteSpace: 'nowrap' }}>
                          {e.pluginName}
                          <br />
                          <small className="flxan-muted">{e.version}</small>
                        </td>
                        <td className="n">{n(e.sites)}</td>
                        <td className="n">{n(e.count)}</td>
                        <td className="n tm-hide-m" title={e.last.label}>{e.last.relative}</td>
                        <td className="n">
                          <button type="button" className="flxan-btn" title={t('Remove as resolved')} onClick={() => forget.mutate({ input: { hash: e.hash } })}>
                            {t('Resolved')}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="flxan-more">
                <button type="button" className="flxan-link-danger" onClick={() => setClearAll(true)}>
                  {t('Clear all errors')}
                </button>
              </div>
            </>
          )}
          <p className="description">{t('Server paths, domain, URLs, emails and IPs are removed from messages. The same error in the same package, version, file and line is one record; "Sites" is how many different installs saw it.')}</p>
        </div>
      )}

      {tab === 'onboarding' && <Onboarding d={d} />}
      {clearAll && <ConfirmDialog danger text={t('Delete all error records?')} action={t('Clear all errors')} onYes={() => forget.mutate({ input: { hash: 'all' } })} onClose={() => setClearAll(false)} />}
    </Shell>
  );
}

function Onboarding({ d }: { d: T }) {
  const { t, n, pf } = useFmt();
  const O = d.onboarding;
  const runs = O.runs;
  const pct = (c: number, of: number) => (of ? pf((c / of) * 100) + '%' : '0%');
  if (!runs) {
    return (
      <div className="flxan-panel flxan-empty--big">
        <b>{t('No wizard data yet.')}</b>
        <p>{t('FluxWP Panel 1.0.11 and later sends the last step and choices when the wizard is finished or closed.')}</p>
      </div>
    );
  }
  return (
    <>
      <div className="flxan-cards">
        <Kpi label={t('Wizard runs')} value={n(runs)} sub={t('Latest run per install')} tone="blue" icon="users" />
        <Kpi label={t('Finished')} value={pct(O.done, runs)} sub={n(O.done) + ' ' + t('sites')} tone="green" icon="target" />
        <Kpi label={t('Skipped')} value={pct(O.skipped, runs)} sub={n(O.skipped) + ' ' + t('sites')} tone="amber" icon="exit" />
        <Kpi label={t('Abandoned')} value={pct(O.left, runs)} sub={O.medianSeconds ? t('Median time for finishers: %s min', n(Math.max(1, Math.round(O.medianSeconds / 60)))) : t('No finisher yet')} tone="rose" icon="bounce" />
      </div>
      <div className="flxan-grid">
        <div className="tm-funnel">
          <Share title={t('Reached each step')} head={[t('Steps'), t('Site'), t('Share')]} total={runs} rows={O.reach.map((x) => ({ key: x.step, label: x.label, vals: [n(x.count), pct(x.count, runs)], n: x.count }))} />
        </div>
        <Share title={t('Where they stopped')} head={[t('Last step'), t('Site'), t('Share')]} total={runs} empty={t('Everyone finished.')} rows={O.stops.map((x) => ({ key: x.step + x.outcome, label: <>{x.label} <small>{x.outcome === 'skip' ? t('skipped') : t('closed')}</small></>, vals: [n(x.count), pct(x.count, runs)], n: x.count }))} />
        <div className="flxan-wide">
          <Share title={t('Card choices')} head={[t('Card'), t('Picked'), t('Share'), t('Picked then removed'), t('Removed suggestion')]} total={runs} rows={[...O.cards].sort((a, b) => b.picked - a.picked).map((x) => ({ key: x.slug, label: x.name, vals: [n(x.picked), pct(x.picked, runs), n(x.dropped), n(x.recOff)], n: x.picked }))} />
        </div>
        <Share title={t('Preset (finishers)')} head={[t('Choice'), t('Site'), t('Share')]} total={O.done} rows={O.presets.map((x) => ({ key: x.key, label: x.key, vals: [n(x.count), pct(x.count, O.done)], n: x.count }))} />
        <Share title={t('Colour style (finishers)')} head={[t('Choice'), t('Site'), t('Share')]} total={O.done} rows={O.styles.map((x) => ({ key: x.key, label: x.key, vals: [n(x.count), pct(x.count, O.done)], n: x.count }))} />
      </div>
    </>
  );
}

export const TelemetryScreen = withAnalytics(Telemetry);
