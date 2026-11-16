// Flux AI › Logs: every model request and tool call, filters, paging and the masked content.
import { useState } from 'react';
import { FlxAiLogBody, FlxAiLogs } from '@fluxwp/graphql/operations';
import type { FlxAiLogKind } from '@fluxwp/graphql/types';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, Pill, useAi } from './common';

const DAYS = [1, 7, 30, 90, 365];

export function Logs() {
  const { t, num, usd, ms } = useAi();
  const [days, setDays] = useState(7);
  const [kind, setKind] = useState<FlxAiLogKind | ''>('');
  const [source, setSource] = useState('');
  const [errorsOnly, setErrorsOnly] = useState(false);
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [body, setBody] = useState<number | null>(null);
  const after = cursors[cursors.length - 1] ?? null;
  const q = useFlxQuery(FlxAiLogs, { first: 50, after, days, kind: kind || null, source: source || null, errorsOnly }, { placeholderData: (p) => p });
  const reset = () => setCursors([null]);
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const logs = q.data.flxAiLogs;
  const page = cursors.length;
  return (
    <>
      <div className="flxai-filters">
        <select value={days} onChange={(e) => (setDays(Number(e.target.value)), reset())} aria-label={t('Period')}>
          {DAYS.map((d) => (
            <option key={d} value={d}>
              {d === 1 ? t('24 hours') : t('%d days', d)}
            </option>
          ))}
        </select>
        <select value={kind} onChange={(e) => (setKind(e.target.value as FlxAiLogKind | ''), reset())} aria-label={t('Kind')}>
          <option value="">{t('All')}</option>
          <option value="MODEL">{t('Model requests')}</option>
          <option value="TOOL">{t('Tool calls')}</option>
        </select>
        <select value={source} onChange={(e) => (setSource(e.target.value), reset())} aria-label={t('Source')}>
          <option value="">{t('All systems')}</option>
          {q.data.flxAiLogSources.map((s) => (
            <option key={s.id} value={s.id}>
              {s.label}
            </option>
          ))}
        </select>
        <label className="flxai-check">
          <input type="checkbox" checked={errorsOnly} onChange={(e) => (setErrorsOnly(e.target.checked), reset())} /> {t('Errors only')}
        </label>
        <span className="flxai-muted">{t('%s records', num(logs.totalCount ?? 0))}</span>
      </div>
      {logs.nodes.length === 0 ? (
        <p className="flxai-empty">{t('No records.')}</p>
      ) : (
        <Card>
          <div className="flxai-tscroll">
            <table className="flxai-table flxai-log">
              <thead>
                <tr>
                  <th>{t('Time')}</th>
                  <th>{t('Where')}</th>
                  <th>{t('Who')}</th>
                  <th>{t('Model / tool')}</th>
                  <th className="num">{t('Input')}</th>
                  <th className="num">{t('Output')}</th>
                  <th className="num">{t('Cache')}</th>
                  <th className="num">{t('Duration')}</th>
                  <th className="num">{t('Cost')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {logs.nodes.map((r) => (
                  <tr key={r.databaseId} className={r.ok ? '' : 'is-err'}>
                    <td>{r.time.label}</td>
                    <td>{[r.sourceLabel, r.purposeLabel].filter(Boolean).join(' · ') + (r.chatId ? ' #' + r.chatId : '')}</td>
                    <td>{r.userId ? r.userName : t('System')}</td>
                    <td>
                      {r.kind === 'TOOL' ? (
                        <>
                          <Pill>{t('tool')}</Pill> {r.tool}
                        </>
                      ) : (
                        r.modelLabel
                      )}
                      {!r.ok && <div className="flxai-err">{r.errorLabel}</div>}
                    </td>
                    <td className="num">{num(r.inTokens)}</td>
                    <td className="num">{num(r.outTokens)}</td>
                    <td className="num">{r.cacheRead || r.cacheWrite ? `${num(r.cacheRead)} / ${num(r.cacheWrite)}` : '—'}</td>
                    <td className="num">{ms(r.ms)}</td>
                    <td className="num">{r.kind === 'TOOL' ? '—' : usd(r.cost, 5) + (r.estimated ? ' ~' : '')}</td>
                    <td>
                      {r.hasBody && (
                        <button type="button" className="flxai-btn is-small" onClick={() => setBody(r.databaseId)}>
                          {t('Content')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="flxai-pager">
            <button type="button" className="flxai-btn" disabled={page <= 1} onClick={() => setCursors((c) => c.slice(0, -1))}>
              ‹
            </button>
            <span>{t('Page %s', num(page))}</span>
            <button type="button" className="flxai-btn" disabled={!logs.pageInfo.hasNextPage} onClick={() => setCursors((c) => [...c, logs.pageInfo.endCursor ?? null])}>
              ›
            </button>
          </p>
          <p className="flxai-note">{t('Cache column: tokens read / written. "~" is estimated from the price list. Content: last outgoing messages and the incoming reply, shortened; e-mail, phone, IBAN and keys are masked.')}</p>
        </Card>
      )}
      {body !== null && <BodyModal id={body} onClose={() => setBody(null)} />}
    </>
  );
}

function BodyModal({ id, onClose }: { id: number; onClose: () => void }) {
  const { t } = useAi();
  const q = useFlxQuery(FlxAiLogBody, { id });
  const b = q.data?.flxAiLogBody;
  return (
    <Modal title={t('Content')} onClose={onClose} wide>
      {q.isLoading && <Loading />}
      {q.isError && <p className="flxai-err">{q.error.message}</p>}
      {b && (
        <div className="flxai-body">
          {b.request.map((m, i) => (
            <div key={i}>
              <strong>{m.role}</strong>
              <pre>{m.text}</pre>
            </div>
          ))}
          {b.reply && (
            <div>
              <strong>{t('Reply')}</strong>
              <pre>{b.reply}</pre>
            </div>
          )}
          {b.toolCalls && (
            <div>
              <strong>{t('Tool calls')}</strong>
              <pre>{b.toolCalls}</pre>
            </div>
          )}
          {b.error && <pre className="flxai-err">{b.error}</pre>}
        </div>
      )}
    </Modal>
  );
}
