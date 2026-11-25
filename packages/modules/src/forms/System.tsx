// Forms › System check: the shared flx_system_checks rows of every FluxWP plugin (flxFormsSystem), grouped,
// with a status filter. Shown only when Flux Donate or Flux Speed does not host the screen.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsSystemQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsSystem } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, useForms } from './common';

type Check = FlxFormsSystemQuery['flxFormsSystem']['checks'][number];
type Status = 'ok' | 'warning' | 'error' | 'info';
const STATUSES: Status[] = ['ok', 'warning', 'error', 'info'];

function initial(): Status | '' {
  const h = typeof window !== 'undefined' ? window.location.hash.replace(/^#\/?/, '') : '';
  return (STATUSES as string[]).includes(h) ? (h as Status) : '';
}

export function SystemPage() {
  const { t, num } = useForms();
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxFormsSystem, {});
  const [only, setOnly] = useState<Status | ''>(initial);
  const d = q.data?.flxFormsSystem;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;

  const label: Record<Status, string> = { ok: t('OK'), warning: t('Warning'), error: t('Error'), info: t('Info') };
  const count = (rows: Check[]) => {
    const n: Record<Status, number> = { ok: 0, warning: 0, error: 0, info: 0 };
    for (const c of rows) if (c.status in n) n[c.status as Status] += 1;
    return n;
  };
  const all = count(d.checks);
  const summary = (n: Record<Status, number>) => t('{ok} OK · {warning} warnings · {error} errors', { ok: n.ok, warning: n.warning, error: n.error });
  const groups: { key: string; label: string; rows: Check[] }[] = [];
  for (const c of d.checks) {
    let g = groups.find((x) => x.key === c.group);
    if (!g) groups.push((g = { key: c.group, label: c.groupLabel, rows: [] }));
    g.rows.push(c);
  }
  const shown = groups.map((g) => ({ ...g, visible: only ? g.rows.filter((c) => c.status === only) : g.rows })).filter((g) => g.visible.length);

  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.')}</p>
      <div className="flxfrm-toolbar">
        <div className="flxfrm-inline" role="group" aria-label={summary(all)}>
          {STATUSES.filter((s) => s !== 'info' || all.info).map((s) => (
            <button
              key={s}
              type="button"
              className={'flxfrm-chip is-' + s + (only === s ? ' is-on' : '')}
              aria-pressed={only === s}
              disabled={!all[s]}
              title={t('Show only these (click again to show all)')}
              onClick={() => setOnly((o) => (o === s ? '' : s))}
            >
              <strong>{num(all[s])}</strong> {label[s]}
            </button>
          ))}
        </div>
        <button type="button" className="flxfrm-btn" disabled={q.isFetching} onClick={() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsSystem, {}) })}>
          {q.isFetching ? t('Checking…') : t('Check again')}
        </button>
      </div>
      {!d.checks.length ? <p className="flxfrm-empty">{t('No checks are registered.')}</p> : null}
      {shown.map((g) => (
        <section key={g.key} className="flxfrm-card is-flush">
          <h2 className="flxfrm-card__title">
            {g.label} <span className="flxfrm-muted">{summary(count(g.rows))}</span>
          </h2>
          <table className="flxfrm-table">
            <thead>
              <tr>
                <th>{t('Status')}</th>
                <th>{t('Check')}</th>
                <th>{t('Details')}</th>
                <th>{t('Source')}</th>
                <th aria-label={t('Action')} />
              </tr>
            </thead>
            <tbody>
              {g.visible.map((c) => (
                <tr key={c.id || c.label}>
                  <td data-label={t('Status')}>
                    <span className={'flxfrm-pill is-' + c.status}>{label[c.status as Status] ?? c.status}</span>
                  </td>
                  <td data-label={t('Check')}>
                    <strong>{c.label}</strong>
                  </td>
                  <td data-label={t('Details')}>{c.message}</td>
                  <td data-label={t('Source')} className="flxfrm-muted">
                    {c.source}
                  </td>
                  <td className="flxfrm-rowactions">
                    {c.actionUrl ? (
                      <button type="button" className="flxfrm-btn" onClick={() => host.openUrl(c.actionUrl)}>
                        {c.actionLabel || t('Open')}
                      </button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      ))}
    </div>
  );
}
