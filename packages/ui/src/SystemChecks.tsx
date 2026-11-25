// Ortak "Sistem kontrolü" ekranı: klasik flx_system_checks_render() düzeninin birebir React hâli (flxsc-* sınıfları,
// stil styles.css'te). Durum çipleri filtreler (tekrar tıkla = hepsi), #error gibi hash filtreli açar.
import { useEffect, useState } from 'react';
import { normalizeLocale } from '@fluxwp/core';
import dicts from './system-checks.i18n.json';

export type SystemCheckStatus = 'ok' | 'warning' | 'error' | 'info';
export interface SystemCheckRow {
  id: string;
  source: string;
  group: string;
  groupLabel: string;
  label: string;
  status: string;
  message: string;
  actionUrl?: string | null;
  actionLabel?: string | null;
}
export interface SystemChecksProps {
  checks: SystemCheckRow[];
  /** Screen language (en, tr, de, …); the texts are built in, as the classic screen printed them. */
  lang?: string;
  onRecheck: () => void;
  busy?: boolean;
  /** Time of the check, already formatted (HH:MM). */
  checkedAt: string;
  onAction?: (url: string) => void;
}

const STATUSES: SystemCheckStatus[] = ['ok', 'warning', 'error', 'info'];
const PATHS: Record<SystemCheckStatus, string[]> = {
  ok: ['M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', 'm8 12.5 2.6 2.5L16 9.5'],
  warning: ['M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', 'M12 9v4M12 17h.01'],
  error: ['M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', 'm15 9-6 6M9 9l6 6'],
  info: ['M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', 'M12 16v-4M12 8h.01'],
};

const norm = (s: string): SystemCheckStatus => {
  const v = s.toLowerCase();
  return (STATUSES as string[]).includes(v) ? (v as SystemCheckStatus) : 'info';
};

function Ico({ status }: { status: SystemCheckStatus }) {
  return (
    <svg className="flxsc-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      {PATHS[status].map((d) => (
        <path key={d} d={d} />
      ))}
    </svg>
  );
}

function fromHash(): SystemCheckStatus | '' {
  const h = typeof window !== 'undefined' ? window.location.hash.replace(/^#\/?/, '') : '';
  return (STATUSES as string[]).includes(h) ? (h as SystemCheckStatus) : '';
}

export function SystemChecks({ checks, lang = 'en', onRecheck, busy, checkedAt, onAction }: SystemChecksProps) {
  const dict = (dicts as Record<string, Record<string, string>>)[normalizeLocale(lang)] ?? {};
  const t = (s: string): string => dict[s] || s;
  const [only, setOnly] = useState<SystemCheckStatus | ''>(fromHash);
  useEffect(() => {
    try {
      const base = window.location.pathname + window.location.search;
      const h = window.location.hash;
      // Keep a router hash (#/…) as it is; only the plain status hash belongs to this screen.
      if (h.startsWith('#/')) return;
      window.history.replaceState(null, '', only ? '#' + only : base);
    } catch {
      /* ignore */
    }
  }, [only]);

  const name: Record<SystemCheckStatus, string> = { ok: t('OK'), warning: t('Warning'), error: t('Error'), info: t('Info') };
  const count = (rows: SystemCheckRow[]) => {
    const n: Record<SystemCheckStatus, number> = { ok: 0, warning: 0, error: 0, info: 0 };
    for (const c of rows) n[norm(c.status)] += 1;
    return n;
  };
  const summary = (n: Record<SystemCheckStatus, number>) => t('{ok} OK · {warning} warnings · {error} errors').replace('{ok}', String(n.ok)).replace('{warning}', String(n.warning)).replace('{error}', String(n.error));
  const all = count(checks);
  const groups: { key: string; label: string; rows: SystemCheckRow[] }[] = [];
  for (const c of checks) {
    let g = groups.find((x) => x.key === c.group);
    if (!g) groups.push((g = { key: c.group, label: c.groupLabel || c.group, rows: [] }));
    g.rows.push(c);
  }

  return (
    <div className="flxsc">
      <p className="flxsc-intro">{t('Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.')}</p>
      <div className="flxsc-top">
        <div className="flxsc-chips" role="group" aria-label={summary(all)}>
          {STATUSES.filter((s) => s !== 'info' || all.info).map((s) => (
            <button
              key={s}
              type="button"
              className={'flxsc-chip flxsc-s-' + s}
              aria-pressed={only === s}
              title={t('Show only these (click again to show all)')}
              disabled={!all[s]}
              onClick={() => setOnly((o) => (o === s ? '' : s))}
            >
              <Ico status={s} />
              <strong>{all[s]}</strong> {name[s]}
            </button>
          ))}
        </div>
        <span className="flxsc-when">{t('Checked: %s').replace('%s', checkedAt)}</span>
        <button type="button" className="flxsc-btn" disabled={busy} onClick={onRecheck}>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-2.6-6.4L21 8" />
            <path d="M21 3v5h-5" />
          </svg>
          {t('Check again')}
        </button>
      </div>
      {!checks.length ? (
        <div className="flxsc-group">
          <p className="flxsc-empty">{t('No checks are registered.')}</p>
        </div>
      ) : null}
      {groups.map((g, i) => {
        const rows = only ? g.rows.filter((c) => norm(c.status) === only) : g.rows;
        if (!rows.length) return null;
        const gid = 'flxsc-g-' + (i + 1);
        return (
          <section key={g.key} className="flxsc-group" aria-labelledby={gid}>
            <h2 id={gid}>
              {g.label}
              <span className="flxsc-count">{summary(count(g.rows))}</span>
            </h2>
            <table className="flxsc-table">
              <thead>
                <tr>
                  <th scope="col" className="flxsc-col-st">
                    {t('Status')}
                  </th>
                  <th scope="col" className="flxsc-col-check">
                    {t('Check')}
                  </th>
                  <th scope="col">{t('Details')}</th>
                  <th scope="col" className="flxsc-col-src">
                    {t('Source')}
                  </th>
                  <th scope="col" className="flxsc-col-act">
                    {t('Action')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {rows.map((c, j) => {
                  const st = norm(c.status);
                  return (
                    <tr key={(c.id || c.label) + j} id={c.id ? 'flxsc-' + c.id : undefined} data-status={st}>
                      <td>
                        <span className={'flxsc-st flxsc-s-' + st}>
                          <Ico status={st} />
                          {name[st]}
                        </span>
                      </td>
                      <td className="flxsc-label">{c.label}</td>
                      <td className="flxsc-msg">{c.message}</td>
                      <td className="flxsc-src">{c.source}</td>
                      <td className="flxsc-act">
                        {c.actionUrl ? (
                          <a
                            href={c.actionUrl}
                            onClick={
                              onAction
                                ? (e) => {
                                    e.preventDefault();
                                    onAction(c.actionUrl as string);
                                  }
                                : undefined
                            }
                          >
                            {c.actionLabel || t('Action')}
                          </a>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </section>
        );
      })}
    </div>
  );
}
