// Flux AI › Tools: every tool the chat and MCP can call; switch on/off (bulk), settle name clashes.
import { useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { FlxAiToolPick, FlxAiTools, FlxAiToolsUpdate } from '@fluxwp/graphql/operations';
import type { FlxAiToolRowFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { Card, NoticeBar, Pill, Switch, errText, useAi, type Notice } from './common';

type Tool = FlxAiToolRowFragment;

export function Tools() {
  const { t, tn, num, ms } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAiTools, {});
  const [text, setText] = useState('');
  const [src, setSrc] = useState('');
  const [kind, setKind] = useState('');
  const [state, setState] = useState('');
  const [sel, setSel] = useState<Set<string>>(new Set());
  const [notice, setNotice] = useState<Notice | null>(null);
  const rows: Tool[] = useMemo(() => q.data?.flxAiTools ?? [], [q.data]);
  const done = (r: { tools?: ReadonlyArray<Tool> | null; errors: ReadonlyArray<{ message: string }> } | null | undefined, ok: string) => {
    if (!r || r.errors.length) return setNotice({ kind: 'error', text: r?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
    if (r.tools) qc.setQueryData(flxQueryKey(scope, FlxAiTools, {}), { flxAiTools: r.tools });
    setNotice({ kind: 'success', text: ok });
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const update = useFlxMutation(FlxAiToolsUpdate, { onSuccess: (r) => done(r.flxAiToolsUpdate, t('Saved.')), onError: fail });
  const pick = useFlxMutation(FlxAiToolPick, { onSuccess: (r) => done(r.flxAiToolPick, t('The plugin used for this tool was changed.')), onError: fail });

  const active = rows.filter((r) => !r.duplicate);
  const bySrc = useMemo(() => {
    const m = new Map<string, { label: string; n: number; on: number }>();
    for (const r of rows.filter((x) => !x.duplicate)) {
      const e = m.get(r.source) ?? { label: r.sourceLabel, n: 0, on: 0 };
      e.n++;
      if (!r.off) e.on++;
      m.set(r.source, e);
    }
    return [...m.entries()].sort((a, b) => b[1].n - a[1].n || a[1].label.localeCompare(b[1].label));
  }, [rows]);
  if (q.isLoading) return <Loading />;
  if (q.isError) return <div className="flxai-notice is-error">{q.error.message}</div>;

  const nOn = active.filter((r) => !r.off).length;
  const nDupe = new Set(rows.filter((r) => r.duplicate).map((r) => r.name)).size;
  const nNoPrefix = active.filter((r) => !r.prefixed).length;
  const needle = text.trim().toLowerCase();
  const visible = rows.filter((r) => {
    if (src && r.source !== src) return false;
    if (kind === 'read' && !r.readonly) return false;
    if (kind === 'write' && r.readonly) return false;
    if (state === 'on' && (r.off || r.duplicate)) return false;
    if (state === 'off' && !r.off) return false;
    if (state === 'conflict' && !r.duplicate && r.rivals.length === 0) return false;
    if (state === 'noprefix' && r.prefixed) return false;
    return !needle || [r.name, r.title, r.description, r.sourceLabel, r.source, ...r.aliases].join(' ').toLowerCase().includes(needle);
  });
  const selectable = visible.filter((r) => !r.duplicate);
  const setMany = (names: string[], on: boolean) => update.mutate({ input: { tools: names.map((name) => ({ name, on })) } });
  const label = (id: string) => bySrc.find(([k]) => k === id)?.[1].label ?? id;

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t('Tools that plugins offer to AI: the shared chat and the MCP server. A tool turned off is unavailable in both; tools that change data ask for approval every time.')}</p>
      <div className="flxai-kpis">
        <Kpi label={t('Tool')} value={num(active.length)} sub={tn('%s plugin', '%s plugins', bySrc.length, num(bySrc.length))} />
        <Kpi label={t('On')} value={num(nOn)} sub={t('%s off', num(active.length - nOn))} />
        <Kpi label={t('Name conflicts')} value={num(nDupe)} sub={nDupe ? t('Only one is on per name') : t('None')} />
        <Kpi label={t('Unprefixed names')} value={num(nNoPrefix)} sub={t('Tools without a plugin prefix')} />
      </div>
      <Card>
        <div className="flxai-chips" role="group" aria-label={t('By plugin')}>
          <button type="button" className={'flxai-chip' + (src === '' ? ' is-on' : '')} onClick={() => setSrc('')}>
            <span>{t('All')}</span>
            <b>{num(active.length)}</b>
          </button>
          {bySrc.map(([id, s]) => (
            <button key={id} type="button" className={'flxai-chip' + (src === id ? ' is-on' : '')} title={id} onClick={() => setSrc(id)}>
              <span>{s.label}</span>
              <b>{s.on === s.n ? num(s.n) : `${num(s.on)}/${num(s.n)}`}</b>
            </button>
          ))}
        </div>
        <div className="flxai-filters">
          <input type="search" value={text} onChange={(e) => setText(e.target.value)} placeholder={t('Search name, description or plugin')} aria-label={t('Search tools')} />
          <Seg value={kind} onChange={setKind} opts={[['', t('All')], ['read', t('Reads')], ['write', t('Changes')]]} />
          <Seg value={state} onChange={setState} opts={[['', t('All')], ['on', t('On')], ['off', t('Off')], ['conflict', t('Conflict')], ['noprefix', t('Unprefixed')]]} />
          <span className="flxai-muted" aria-live="polite">
            {t('%s shown', num(visible.length))}
          </span>
        </div>
        {sel.size > 0 && (
          <div className="flxai-bulk">
            <span>{t('%s selected', num(sel.size))}</span>
            <button type="button" className="flxai-btn" onClick={() => (setMany([...sel], true), setSel(new Set()))}>
              {t('Turn on')}
            </button>
            <button type="button" className="flxai-btn" onClick={() => (setMany([...sel], false), setSel(new Set()))}>
              {t('Turn off')}
            </button>
            <button type="button" className="flxai-link" onClick={() => setSel(new Set())}>
              {t('Clear selection')}
            </button>
          </div>
        )}
        <div className="flxai-tscroll">
          <table className="flxai-table flxai-tools">
            <thead>
              <tr>
                <th>
                  <input
                    type="checkbox"
                    aria-label={t('Select all visible')}
                    checked={selectable.length > 0 && selectable.every((r) => sel.has(r.name))}
                    onChange={(e) => setSel(e.target.checked ? new Set(selectable.map((r) => r.name)) : new Set())}
                  />
                </th>
                <th>{t('On')}</th>
                <th>{t('Tool')}</th>
                <th>{t('Source')}</th>
                <th>{t('Type')}</th>
                <th>{t('Available in')}</th>
                <th className="num">{t('30 days')}</th>
              </tr>
            </thead>
            <tbody>
              {visible.map((r) => (
                <tr key={r.name + '|' + r.source} className={r.duplicate ? 'is-dupe' : r.off ? 'is-off' : ''}>
                  <td>
                    {!r.duplicate && (
                      <input
                        type="checkbox"
                        aria-label={t('Select %s', r.title)}
                        checked={sel.has(r.name)}
                        onChange={(e) =>
                          setSel((s) => {
                            const n = new Set(s);
                            if (e.target.checked) n.add(r.name);
                            else n.delete(r.name);
                            return n;
                          })
                        }
                      />
                    )}
                  </td>
                  <td>
                    {r.duplicate ? (
                      <button type="button" className="flxai-btn is-small" disabled={pick.isPending} onClick={() => pick.mutate({ input: { name: r.name, source: r.source } })}>
                        {t('Use this one')}
                      </button>
                    ) : (
                      <Switch checked={!r.off} label={t('%s on', r.title)} disabled={update.isPending} onChange={(on) => setMany([r.name], on)} />
                    )}
                  </td>
                  <td>
                    <strong>{r.title}</strong> <code>{r.name}</code>
                    <div className="flxai-muted">{trimWords(r.description, 30)}</div>
                    {r.duplicate && <Pill kind="err">{t('Conflict: off, %s is used', label(r.rivals[0] ?? ''))}</Pill>}
                    {!r.duplicate && r.rivals.length > 0 && <Pill kind="warn">{t('Conflict: this one is on, %s is off', r.rivals.map(label).join(', '))}</Pill>}
                    {!r.prefixed && <Pill>{t('unprefixed')}</Pill>}
                    {r.aliases.length > 0 && <div className="flxai-alias">{t('Old name: %s', r.aliases.join(', '))}</div>}
                  </td>
                  <td>{r.sourceLabel}</td>
                  <td>{r.readonly ? t('Reads') : r.approval ? t('Changes (asks approval)') : t('Changes')}</td>
                  <td>{[r.mcp ? 'MCP' : '', r.chat ? t('Chat') : ''].filter(Boolean).join(', ') || '—'}</td>
                  <td className="num">
                    {r.calls > 0 ? `${num(r.calls)} · ${ms(r.avgMs)}` : '—'}
                    {r.errors > 0 && <Pill kind="err">{t('%s errors', num(r.errors))}</Pill>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}

function trimWords(text: string, n: number): string {
  const w = text.split(/\s+/);
  return w.length > n ? w.slice(0, n).join(' ') + '…' : text;
}

function Seg({ value, onChange, opts }: { value: string; onChange: (v: string) => void; opts: [string, string][] }) {
  return (
    <div className="flxai-seg" role="group">
      {opts.map(([v, l]) => (
        <button key={v} type="button" aria-pressed={v === value} className={v === value ? 'is-on' : ''} onClick={() => onChange(v)}>
          {l}
        </button>
      ))}
    </div>
  );
}

function Kpi({ label, value, sub }: { label: string; value: string; sub: string }) {
  return (
    <div className="flxai-card flxai-kpi">
      <div className="flxai-muted">{label}</div>
      <div className="flxai-big">{value}</div>
      <div className="flxai-muted">{sub}</div>
    </div>
  );
}
