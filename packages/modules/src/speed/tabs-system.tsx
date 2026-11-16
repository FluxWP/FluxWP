// Performance › Server, Database, Autoload, Cron and System check.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSpeedAutoload, FlxSpeedCleanup, FlxSpeedCron, FlxSpeedDatabase, FlxSpeedServer, FlxSpeedSystem } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, Cards, ErrorBox, Kv, Table, errorText, fmtBytes, fmtMs, num, useSpeed, useT } from './ui';
import { LiveCards } from './tabs-monitor';

export function ServerTab() {
  const { t } = useT();
  const q = useFlxQuery(FlxSpeedServer, {});
  return (
    <>
      <LiveCards />
      {q.isLoading && <Loading />}
      {q.isError && <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />}
      {q.data && (
        <div className="flxs-env">
          {q.data.flxSpeedServer.map((s) => (
            <Kv key={s.key} title={s.title} rows={s.rows} />
          ))}
        </div>
      )}
    </>
  );
}

export function DatabaseTab() {
  const { t, lang } = useT();
  const { notify } = useSpeed();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxSpeedDatabase, {});
  const [ask, setAsk] = useState<{ key: string; label: string } | null>(null);
  const run = useFlxMutation(FlxSpeedCleanup, {
    onSuccess: (r) => {
      const p = r.flxSpeedCleanup;
      if (p?.errors.length) return notify('error', p.errors.map((e) => e.message).join(' '));
      notify('success', t('Cleanup done: %s items.', num(p?.done ?? 0, lang)));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSpeedDatabase) });
    },
    onError: (e) => notify('error', errorText(e, t('The action could not be completed.'))),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const d = q.data.flxSpeedDatabase;
  return (
    <>
      <Cards>
        {d.cards.map((c) => (
          <Card key={c.label} label={c.label} value={c.value} tone={c.tone} />
        ))}
      </Cards>
      <div className="flxs-env">
        {d.sections.map((s) => (
          <Kv key={s.key} title={s.title} rows={s.rows} />
        ))}
      </div>
      <h2>{t('Cleanup')}</h2>
      <p className="flxs-desc">{t('Safe jobs only: posts, pages and revisions are never deleted. Limit revisions with WP_POST_REVISIONS in wp-config.')}</p>
      <Table
        head={[t('Job'), t('Count'), '']}
        numeric={[1]}
        empty={t('No records.')}
        rows={[
          ...d.jobs.map((j) => [
            j.label,
            num(j.count, lang),
            j.runnable ? (
              <button type="button" className="flxs-btn is-small" onClick={() => setAsk({ key: j.key, label: j.label })}>
                {t('Clean')}
              </button>
            ) : (
              ''
            ),
          ]),
          ...d.info.map((i) => [i.label, num(i.count, lang), '']),
        ]}
      />
      <h2>{t('Tables')}</h2>
      <Table
        head={[t('Table'), t('Engine'), t('Rows'), t('Data'), t('Index'), t('Free space'), t('Collation')]}
        numeric={[2, 3, 4, 5]}
        empty={t('No records.')}
        rows={d.tables.map((x) => [<code>{x.name}</code>, x.engine, num(x.rows, lang), fmtBytes(x.data, lang), fmtBytes(x.index, lang), x.free ? fmtBytes(x.free, lang) : '—', x.collate])}
      />
      {ask && (
        <Modal title={ask.label} onClose={() => setAsk(null)}>
          <p className="flxs-confirm">{ask.label + ' — ' + t('continue?')}</p>
          <div className="flxs-modal-actions">
            <button type="button" className="flxs-btn" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxs-btn is-primary"
              onClick={() => {
                run.mutate({ input: { job: ask.key } });
                setAsk(null);
              }}
            >
              {t('Clean')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

export function AutoloadTab() {
  const { t, lang } = useT();
  const q = useFlxQuery(FlxSpeedAutoload, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const a = q.data.flxSpeedAutoload;
  return (
    <>
      <Cards>
        <Card label={t('Autoloaded options')} value={num(a.count, lang)} />
        <Card label={t('Total size')} value={num(a.totalKb, lang) + ' KB'} tone={a.tone} />
      </Cards>
      <p className="flxs-desc">{t('These options are loaded from the database on every request. Over 800 KB in total slows pages down; shrink the setting or cache in the plugin that owns the large rows.')}</p>
      <Table
        head={[t('Option'), t('Owner'), t('Size')]}
        numeric={[2]}
        empty={t('No records.')}
        rows={a.largest.map((o) => [<code>{o.name}</code>, o.owner || '—', num(o.kb, lang, 1) + ' KB'])}
      />
    </>
  );
}

export function CronTab() {
  const { t, lang } = useT();
  const q = useFlxQuery(FlxSpeedCron, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const c = q.data.flxSpeedCron;
  return (
    <>
      <Cards>
        <Card label={t('Events')} value={num(c.events, lang)} />
        <Card label={t('Overdue')} value={num(c.overdue, lang)} tone={c.overdue ? 'WARNING' : 'NONE'} />
        <Card label={t('Last run')} value={c.lastSeen ? c.lastSeen.relative : t('Not seen')} tone={c.lastWarn ? 'WARNING' : 'NONE'} />
        <Card label="DISABLE_WP_CRON" value={c.disabled ? t('On') : t('Off')} />
      </Cards>
      {c.disabled && <p className="flxs-desc">{t('WordPress cron is disabled: events run only when a server cron job calls wp-cron.php. On Hostinger, add a cron job that calls it every 5 minutes.')}</p>}
      <h2>{t('Task durations')}</h2>
      <p className="flxs-desc">{t('Measured per task each time wp-cron.php runs; sorted by total time. A summary older than a day resets when the task runs again. "Cut off": the task hit the time limit and stopped halfway.')}</p>
      <Table
        head={[t('Hook'), t('Component'), t('Runs'), t('Average'), t('Longest'), t('Last'), t('Last run')]}
        numeric={[2, 3, 4, 5]}
        empty={t('No records.')}
        rows={c.timings.map((x) => [
          <>
            <code>{x.hook}</code>
            {x.cut && <span className="flxs-errtext"> {t('Cut off')}</span>}
          </>,
          x.component,
          num(x.runs, lang),
          <span className={x.slow ? 'flxs-errtext' : undefined}>{fmtMs(x.avgMs, lang)}</span>,
          fmtMs(x.maxMs, lang),
          fmtMs(x.lastMs, lang),
          x.lastAt ? x.lastAt.relative : '—',
        ])}
      />
      <h2>{t('Scheduled events')}</h2>
      <Table
        head={[t('Hook'), t('Next'), t('Recurrence'), t('Args')]}
        numeric={[3]}
        empty={t('No records.')}
        rows={c.items.map((e) => {
          const when = e.next.short + ' · ' + (e.overdue ? t('Overdue: due %s', e.next.relative) : e.next.relative);
          return [<code>{e.hook}</code>, <span className={e.overdue ? 'flxs-errtext' : undefined}>{when}</span>, e.schedule || t('once'), e.args];
        })}
      />
    </>
  );
}

export function SystemTab() {
  const { t } = useT();
  const q = useFlxQuery(FlxSpeedSystem, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const s = q.data.flxSpeedSystem;
  const groups: { key: string; label: string; items: typeof s.checks }[] = [];
  for (const c of s.checks) {
    let g = groups.find((x) => x.key === c.group);
    if (!g) groups.push((g = { key: c.group, label: c.groupLabel, items: [] }));
    g.items.push(c);
  }
  const mark = (st: string) => (st === 'OK' ? '✓' : st === 'ERROR' ? '✕' : st === 'WARNING' ? '!' : 'i');
  return (
    <>
      <div className="flxs-sysbar">
        <span className="flxs-pill is-on">{t('%s OK', s.summary.ok)}</span>
        {s.summary.warning > 0 && <span className="flxs-pill is-warn">{t('%s warnings', s.summary.warning)}</span>}
        {s.summary.error > 0 && <span className="flxs-pill is-err">{t('%s errors', s.summary.error)}</span>}
        <button type="button" className="flxs-btn" disabled={q.isFetching} onClick={() => void q.refetch()}>
          {t('Check again')}
        </button>
      </div>
      {groups.map((g) => (
        <section key={g.key} className="flxs-box">
          <h2>{g.label}</h2>
          <ul className="flxs-checks">
            {g.items.map((c) => (
              <li key={c.id} className={'is-' + c.status.toLowerCase()}>
                <span className="flxs-mark" aria-hidden="true">
                  {mark(c.status)}
                </span>
                <span>
                  <strong>{c.label}</strong> {c.message}
                  {c.actionUrl && (
                    <>
                      {' '}
                      <a href={c.actionUrl}>{c.actionLabel}</a>
                    </>
                  )}
                </span>
              </li>
            ))}
          </ul>
        </section>
      ))}
      {s.hasFixes && (
        <>
          <h2>{t('Recent fixes (AI / MCP)')}</h2>
          <Table
            head={[t('Time'), t('User'), t('Fix'), t('Before → After'), t('Result')]}
            empty={t('No records.')}
            rows={s.fixes.map((f) => [
              f.at.short,
              f.user,
              <>
                {f.label}
                {f.value && <code> {f.value}</code>}
              </>,
              f.before + ' → ' + f.after,
              (f.ok ? '✓ ' : '✗ ') + f.message,
            ])}
          />
        </>
      )}
    </>
  );
}
