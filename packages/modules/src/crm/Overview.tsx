// CRM › Overview: counters, value segments, roles, people to reach out to, recent activity, sources.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmOverview, FlxCrmScoreRun, FlxCrmSyncStep } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { Avatar, Card, Head, Ic, Kpi, Money, errText, href, useCrm } from './ui';
import { runSync } from './sync';

export function Overview(_props: { go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, short, can, shell } = crm;
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxCrmOverview, {});
  const [progress, setProgress] = useState('');
  const sync = useFlxMutation(FlxCrmSyncStep);
  const score = useFlxMutation(FlxCrmScoreRun);
  const busy = sync.isPending || score.isPending || progress !== '';

  const reload = () => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmOverview) });
  const syncAll = async () => {
    try {
      await runSync((step, page) => sync.mutateAsync({ input: { step, page } }).then((r) => r.flxCrmSyncStep), setProgress, t);
      crm.tell('ok', t('Past data synced.'));
    } catch (e) {
      crm.fail(e);
    } finally {
      setProgress('');
      reload();
    }
  };
  const recalc = async () => {
    try {
      let done = false;
      let n = 0;
      while (!done && n++ < 200) {
        const r = (await score.mutateAsync({ input: {} })).flxCrmScoreRun;
        if (!r || r.errors.length) throw new Error(errText(r?.errors));
        done = r.result?.done ?? true;
        setProgress(t('Scoring: %s of %s', num(r.result?.scored ?? 0), num(r.result?.total ?? 0)));
      }
      crm.tell('ok', t('Scores recalculated.'));
    } catch (e) {
      crm.fail(e);
    } finally {
      setProgress('');
      reload();
    }
  };

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxcrm-notice is-bad">{q.error?.message}</div>;
  const o = q.data.flxCrmOverview;
  const risk = q.data.atRisk.nodes;
  const delta = o.newPrev ? ((o.new30 - o.newPrev) / o.newPrev) * 100 : null;
  const maxSeg = Math.max(0, ...o.segments.map((s) => s.count));
  const maxSrc = Math.max(1, ...o.sources.map((s) => s.count));

  return (
    <>
      <Head
        title={t('CRM')}
        lead={t('Donors, customers, volunteers and leads in one place: who did what, who is drifting away, who should hear what.')}
        actions={
          can && (
            <a className="button button-primary" href={href('contacts/new')}>
              <Ic n="plus" /> {t('Add contact')}
            </a>
          )
        }
      />
      {o.needsSync && can && (
        <>
          <div className="flxcrm-callout">
            <Ic n="refresh" size={18} />
            <div>
              <strong>{t('Import your past data.')}</strong>{' '}
              {o.syncSources.length
                ? t('New records arrive on their own. To link older ones to contacts too, sync: %s.', o.syncSources.join(', '))
                : t('No source brings in contacts; you can add them by hand.')}
              {o.syncSources.length > 0 && (
                <button type="button" className="button button-small" disabled={busy} onClick={() => void syncAll()}>
                  {t('Sync everything')}
                </button>
              )}
            </div>
          </div>
        </>
      )}
      {progress && (
        <p className="flxcrm-progress" role="status">
          {progress}
        </p>
      )}

      <div className="flxcrm-kpis">
        <Kpi icon="users" label={t('Contact')} value={num(o.total)} hint={t('%s have paid', num(o.paying))} />
        <Kpi
          icon="plus"
          label={t('New in the last 30 days')}
          value={num(o.new30)}
          hint={delta !== null ? t('%s against the previous 30 days', (delta > 0 ? '+' : '') + num(Math.round(delta)) + '%') : ''}
        />
        <Kpi icon="trend" label={t('Active')} value={num(o.active)} hint={t('with activity in the last %s days', num(o.activeDays))} />
        <Kpi icon="alert" label={t('High churn risk')} value={num(o.risk)} hint={t('risk %s%% and above', o.riskThreshold)} warn={o.risk > 0} />
        <Kpi icon="coins" label={t('Total value')} value={short(o.ltv.amount)} hint={o.paying ? t('%s per contact', short(o.ltv.amount / o.paying)) : ''} />
      </div>

      <div className="flxcrm-grid">
        <Card
          className="flxcrm-span-2"
          title={t('RFM segments')}
          icon="chart"
          aside={
            <span className="flxcrm-muted">
              {o.scoredAt ? t('Calculated: %s', o.scoredAt.relative) : ''}
              {can && (
                <>
                  {' '}
                  <button type="button" className="button-link" disabled={busy} onClick={() => void recalc()}>
                    {t('Recalculate')}
                  </button>
                </>
              )}
            </span>
          }
        >
          {maxSeg === 0 ? (
            <p className="flxcrm-empty">{t('Once contacts have paid, donors and customers are grouped here by frequency, recency and value.')}</p>
          ) : (
            <ul className="flxcrm-segs">
              {o.segments.map((s) => (
                <li key={s.key} className={'is-' + s.tone}>
                  <a href={href('contacts?segment=' + s.key)}>
                    <span className="flxcrm-segs__top">
                      <strong>{s.label}</strong>
                      <span>{num(s.count)}</span>
                    </span>
                    <span className="flxcrm-segs__track">
                      <span style={{ width: (maxSeg ? Math.round((s.count / maxSeg) * 1000) / 10 : 0) + '%' }} />
                    </span>
                    <span className="flxcrm-segs__sub">
                      {short(s.value.amount)} · {s.hint}
                    </span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card title={t('Roles')} icon="badge">
          {o.roles.length === 0 ? (
            <p className="flxcrm-empty">{t('No contacts yet.')}</p>
          ) : (
            <ul className="flxcrm-list">
              {o.roles.map((r) => (
                <li key={r.key}>
                  <a href={href('contacts?role=' + r.key)}>
                    <span className="flxcrm-role" style={{ ['--rc' as string]: r.color }}>
                      {r.label}
                    </span>
                    <strong className="flxcrm-num">{num(r.count)}</strong>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card
          title={t('Reach out')}
          icon="alert"
          aside={
            <a href={href('contacts?risk=1&orderby=CHURN')}>
              {t('All')}
              <Ic n="arrow" size={14} />
            </a>
          }
        >
          {risk.length === 0 ? (
            <p className="flxcrm-empty">
              <Ic n="check" size={16} />
              {t('No valuable contacts with a high churn risk.')}
            </p>
          ) : (
            <ul className="flxcrm-list">
              {risk.map((c) => (
                <li key={c.id}>
                  <a href={href('contact/' + c.databaseId)}>
                    <Avatar name={c.name} src={c.avatar} size="sm" />
                    <span className="flxcrm-list__main">
                      <strong>{c.name}</strong>
                      <small>{t('last payment %s', c.lastPaidAt?.relative ?? '—')}</small>
                    </span>
                    <span className="flxcrm-list__side">
                      {c.ltv && <Money value={c.ltv} />}
                      <span className="flxcrm-badge is-bad">%{c.churn}</span>
                    </span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card className="flxcrm-span-2" title={t('Recent activity')} icon="clock">
          {q.data.flxCrmRecentEvents.length === 0 ? (
            <p className="flxcrm-empty">{t('Donations, orders, forms and WhatsApp activity appear here.')}</p>
          ) : (
            <ul className="flxcrm-feed">
              {q.data.flxCrmRecentEvents.map((e) => (
                <li key={e.databaseId}>
                  <span className={'flxcrm-feed__icon is-' + e.type}>
                    <Ic n={e.icon} />
                  </span>
                  <span className="flxcrm-feed__main">
                    <a href={href('contact/' + e.contactId)}>
                      <strong>{e.contactName ?? '#' + e.contactId}</strong>
                    </a>{' '}
                    <span className="flxcrm-muted">{e.typeLabel}</span>
                    <small>{e.title}</small>
                  </span>
                  <span className="flxcrm-feed__side">
                    {e.amount && e.amount.amount > 0 && <Money value={e.amount} strike={!e.paid} />}
                    <small>{e.at.relative}</small>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card title={t('Where they came from')} icon="link">
          {o.sources.length === 0 ? (
            <p className="flxcrm-empty">{t('No contacts yet.')}</p>
          ) : (
            <ul className="flxcrm-bars">
              {o.sources.map((s) => (
                <li key={s.key}>
                  <div className="flxcrm-bars__top">
                    <span>{s.label}</span>
                    <strong>{num(s.count)}</strong>
                  </div>
                  <div className="flxcrm-bars__track">
                    <span style={{ width: Math.round((s.count / maxSrc) * 1000) / 10 + '%' }} />
                  </div>
                </li>
              ))}
            </ul>
          )}
          {shell.flxSegments.length > 0 && (
            <>
              <h3 className="flxcrm-sub">{t('Saved segments')}</h3>
              <ul className="flxcrm-list">
                {shell.flxSegments.slice(0, 5).map((s) => (
                  <li key={s.databaseId}>
                    <a href={href('contacts?sid=' + s.databaseId)}>
                      <span className="flxcrm-list__main">
                        <strong>{s.name}</strong>
                      </span>
                      <strong className="flxcrm-num">{num(s.count)}</strong>
                    </a>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Card>
      </div>
    </>
  );
}
