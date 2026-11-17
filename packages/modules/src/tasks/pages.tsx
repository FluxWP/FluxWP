// Flux Tasks diğer sayfalar (WP assets/js/pages.js karşılığı): kullanım panosu, ayarlar (sistem kontrolü dahil),
// profil, yapay zekâ asistanı paneli. Görünüm WP ile aynı (flxg.css).
import { useEffect, useLayoutEffect, useRef, useState, useSyncExternalStore, type ReactNode } from 'react';
import type { FlxgAiAction, FlxgDashboard, FlxgSettings, FlxgSystemCheck } from '@fluxwp/core';
import { openSanitizedLink, sanitizeHtml } from '@fluxwp/ui';
import { useHost } from '../host';
import { cmpStr, store } from './engine';
import { flxgLocale, tt } from './i18n';
import { Avatar, bytes, Ic, richText, useYtg, type Ctx } from './ui';
import { Skel } from './admin';

const cls = (...a: (string | false | null | undefined | 0)[]) => a.filter(Boolean).join(' ');
const num = (n: number) => (n || 0).toLocaleString(flxgLocale());

/* ---------------- Tema tercihi (WP ile aynı anahtar: flxg-theme) ---------------- */
export type ThemeMode = 'auto' | 'light' | 'dark';
const themeSubs = new Set<() => void>();
export const themeMode = {
  get: (): ThemeMode => {
    const v = store.get('theme');
    return v === 'light' || v === 'dark' ? v : 'auto';
  },
  set(m: ThemeMode) {
    store.set('theme', m === 'auto' ? null : m);
    themeSubs.forEach((f) => f());
  },
  subscribe(f: () => void) {
    themeSubs.add(f);
    return () => void themeSubs.delete(f);
  },
};
export const useThemeMode = () => useSyncExternalStore(themeMode.subscribe, themeMode.get, themeMode.get);
export function themeMenu(c: Ctx, anchor: HTMLElement) {
  const m = themeMode.get();
  c.ui.menu(anchor, [
    { label: tt("System"), checked: m === 'auto', onClick: () => themeMode.set('auto') },
    { label: tt("Light"), icon: 'sun', checked: m === 'light', onClick: () => themeMode.set('light') },
    { label: tt("Dark"), icon: 'moon', checked: m === 'dark', onClick: () => themeMode.set('dark') },
  ]);
}

type Top = (p: { title: ReactNode; extra?: ReactNode }) => ReactNode;

function Page({ wide, children }: { wide?: boolean; children: ReactNode }) {
  return (
    <div className="flxg-content is-page">
      <div className={cls('flxg-page-inner', wide && 'is-wide')}>{children}</div>
    </div>
  );
}
const Crumb = ({ t }: { t: string }) => (
  <div className="flxg-crumbs">
    <h1>{t}</h1>
  </div>
);

/* ---------------- Kullanım panosu (yönetici) ---------------- */
function Kpi({ label, value, sub, tone }: { label: string; value: string; sub?: string; tone?: string }) {
  return (
    <div className={cls('flxg-kpi', tone && 'is-' + tone)}>
      <span className="flxg-kpi-label">{label}</span>
      <b className="flxg-kpi-val">{value}</b>
      {sub && <span className="flxg-kpi-sub">{sub}</span>}
    </div>
  );
}
function Bar({ v, max, color }: { v: number; max: number; color?: string }) {
  return (
    <span className="flxg-bar">
      <span style={{ width: (max ? Math.max(1, Math.round((v / max) * 100)) : 0) + '%', background: color ?? 'var(--accent)' }} />
    </span>
  );
}
function MonthBars({ months }: { months: Record<string, number> }) {
  const keys = Object.keys(months ?? {});
  if (!keys.length) return <p className="flxg-muted">{tt("No data.")}</p>;
  const max = Math.max(1, ...keys.map((k) => months[k]!));
  return (
    <div className="flxg-vbars">
      {keys.map((k) => {
        const [y, m] = k.split('-');
        return (
          <div key={k} className="flxg-vbar" title={bytes(months[k])}>
            <span className="flxg-vbar-v">{bytes(months[k])}</span>
            <span className="flxg-vbar-b" style={{ height: Math.max(2, Math.round((months[k]! / max) * 100)) + '%' }} />
            <span className="flxg-vbar-l">{new Date(+y!, +m! - 1, 1).toLocaleDateString(flxgLocale(), { month: 'short' })}</span>
          </div>
        );
      })}
    </div>
  );
}

export function DashboardPage({ Top }: { Top: Top }) {
  const c = useYtg();
  const { e, ui } = c;
  const host = useHost();
  const [d, setD] = useState<FlxgDashboard | null>(null);
  const [err, setErr] = useState('');
  const [scan, setScan] = useState(false);
  const load = () => {
    setD(null);
    e.api.dashboard().then(setD, (x: unknown) => setErr(x instanceof Error ? x.message : String(x)));
  };
  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps
  const fileUrl = (id: number) => (host.siteUrl ?? '') + '/wp-json/flx-tasks/v1/files/' + id + '?download=1';
  const body = (() => {
    if (err) return <p className="flxg-error">{err}</p>;
    if (!d) return <Skel rows={4} />;
    const s = d.storage;
    const tk = d.tasks;
    const kinds: Record<string, [string, string]> = {
      image: [tt("Images"), '#22c55e'], pdf: [tt("PDF"), '#ef4444'], doc: [tt("Documents"), '#3b82f6'], video: [tt("Video"), '#a855f7'],
      audio: [tt("Audio"), '#f59e0b'], archive: [tt("Archives"), '#64748b'], other: [tt("Other"), '#94a3b8'],
    };
    const ks = Object.keys(kinds).filter((k) => s.kinds[k]);
    const up = s.uploads;
    const maxL = Math.max(1, ...d.lists.map((x) => x.size));
    const maxU = Math.max(1, ...d.users.map((x) => x.size));
    return (
      <>
        <div className="flxg-kpis">
          <Kpi label={tt("File storage")} value={bytes(s.total)} sub={tt("{n} files", { n: num(s.count) }) + (s.thumbs ? ' · ' + tt("previews {s}", { s: bytes(s.thumbs) }) : '')} />
          <Kpi label={tt("Open tasks")} value={num(tk.open)} sub={tt("{n} completed", { n: num(tk.done) })} />
          <Kpi label={tt("Overdue")} value={num(tk.overdue)} sub={tt("open tasks past their due date")} tone={tk.overdue ? 'warn' : ''} />
          <Kpi label={tt("Last 30 days")} value={'+' + num(tk.created30)} sub={tt("{n} tasks completed", { n: num(tk.completed30) })} />
          <Kpi label={tt("Comments")} value={num(tk.comments)} sub={tt("{n} active users (30 days)", { n: num(tk.active30) })} />
          <Kpi label={tt("Structure")} value={num(tk.spaces) + ' / ' + num(tk.lists)} sub={tt("projects / lists · {n} users", { n: num(tk.users) })} />
        </div>
        <section className="flxg-card-box">
          <h2>{tt("Storage")}</h2>
          {s.total ? (
            <div className="flxg-stack">
              {ks.map((k) => (
                <span key={k} style={{ width: (s.kinds[k]!.size / s.total) * 100 + '%', background: kinds[k]![1] }} title={kinds[k]![0] + ': ' + bytes(s.kinds[k]!.size)} />
              ))}
            </div>
          ) : (
            <p className="flxg-muted">{tt("No files uploaded yet.")}</p>
          )}
          <div className="flxg-legend">
            {ks.map((k) => (
              <span key={k} className="flxg-legend-item">
                <span className="flxg-dot" style={{ background: kinds[k]![1] }} />
                {kinds[k]![0]}
                <b>{bytes(s.kinds[k]!.size)}</b>
                <span className="flxg-muted">{'(' + num(s.kinds[k]!.count) + ')'}</span>
              </span>
            ))}
          </div>
          <div className="flxg-dl">
            <div>
              <span>{tt("Task files (uploads/flx-tasks)")}</span>
              <b>{bytes(s.total + s.thumbs)}</b>
            </div>
            {s.pending.count > 0 && (
              <div>
                <span>{tt("Unsent comment attachments (deleted after 24 hours)")}</span>
                <b>{bytes(s.pending.size) + ' · ' + num(s.pending.count)}</b>
              </div>
            )}
            <div>
              <span>{tt("Single file limit")}</span>
              <b>{bytes(s.limit)}</b>
            </div>
            {s.free !== null && (
              <div>
                <span>{tt("Free space on server")}</span>
                <b>{bytes(s.free)}</b>
              </div>
            )}
            <div>
              <span>{tt("Entire media folder (wp-content/uploads)")}</span>
              <b>{up ? bytes(up.size) + ' · ' + tt("{n} files", { n: num(up.count) }) + (up.complete ? '' : ' +') : '—'}</b>
              <button type="button" className="flxg-btn is-sm" disabled={scan} onClick={() => (setScan(true), e.api.dashboardScan().then(load, ui.fail).finally(() => setScan(false)))}>
                {scan ? tt("Calculating…") : up ? tt("Recalculate") : tt("Calculate")}
              </button>
            </div>
          </div>
        </section>
        <section className="flxg-card-box">
          <h2>{tt("By list")}</h2>
          <div className="flxg-table-wrap">
            <table className="flxg-table">
              <thead>
                <tr>
                  <th>{tt("List")}</th>
                  <th>{tt("Project")}</th>
                  <th className="is-num">{tt("Task")}</th>
                  <th className="is-num">{tt("File")}</th>
                  <th className="is-num">{tt("Size")}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {d.lists.map((x) => (
                  <tr key={x.id}>
                    <td>
                      <a href={'#/list/' + x.id} onClick={(ev) => (ev.preventDefault(), void e.go({ page: 'list', id: x.id }))}>
                        {x.name}
                      </a>
                    </td>
                    <td className="flxg-muted">{x.space}</td>
                    <td className="is-num">{num(x.tasks)}</td>
                    <td className="is-num">{num(x.files)}</td>
                    <td className="is-num">{bytes(x.size)}</td>
                    <td className="is-bar">
                      <Bar v={x.size} max={maxL} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
        <div className="flxg-two">
          <section className="flxg-card-box">
            <h2>{tt("Uploaded by person")}</h2>
            {d.users.length ? (
              <div className="flxg-table-wrap">
                <table className="flxg-table">
                  <tbody>
                    {d.users.map((x) => (
                      <tr key={x.user}>
                        <td>
                          <span className="flxg-person">
                            <Avatar u={e.S.users[x.user]} size={22} />
                            {x.name}
                          </span>
                        </td>
                        <td className="is-num">{num(x.count)}</td>
                        <td className="is-num">{bytes(x.size)}</td>
                        <td className="is-bar">
                          <Bar v={x.size} max={maxU} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="flxg-muted">{tt("No data.")}</p>
            )}
          </section>
          <section className="flxg-card-box">
            <h2>{tt("Monthly uploads")}</h2>
            <MonthBars months={s.months} />
          </section>
        </div>
        <section className="flxg-card-box">
          <h2>{tt("Largest files")}</h2>
          {d.largest.length ? (
            <div className="flxg-table-wrap">
              <table className="flxg-table">
                <thead>
                  <tr>
                    <th>{tt("File")}</th>
                    <th>{tt("Task")}</th>
                    <th>{tt("Uploader")}</th>
                    <th className="is-num">{tt("Size")}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {d.largest.map((x) => (
                    <tr key={x.id}>
                      <td>
                        <a href={fileUrl(x.id)} target="_blank" rel="noopener noreferrer">
                          {x.name}
                        </a>
                      </td>
                      <td>
                        {x.task ? (
                          <a href={'#/task/' + x.task} onClick={(ev) => (ev.preventDefault(), e.openTask(x.task))}>
                            {x.title || '#' + x.task}
                          </a>
                        ) : (
                          <span className="flxg-muted">{tt("unlinked")}</span>
                        )}
                        {x.list && <span className="flxg-muted">{' · ' + x.list}</span>}
                      </td>
                      <td className="flxg-muted">{x.user}</td>
                      <td className="is-num">{bytes(x.size)}</td>
                      <td>
                        <button
                          type="button"
                          className="flxg-icon-btn is-sm"
                          title={tt("Delete")}
                          onClick={() =>
                            void ui.confirm(tt("Delete file"), x.name + ' (' + bytes(x.size) + ')', tt("Delete"), true).then((ok) => {
                              if (ok) e.api.deleteFile(x.id).then(load, ui.fail);
                            })
                          }
                        >
                          <Ic n="trash" s={15} />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="flxg-muted">{tt("No files uploaded yet.")}</p>
          )}
        </section>
        <div className="flxg-two">
          <section className="flxg-card-box">
            <h2>{tt("Database")}</h2>
            <div className="flxg-table-wrap">
              <table className="flxg-table">
                <thead>
                  <tr>
                    <th>{tt("Table")}</th>
                    <th className="is-num">{tt("Rows")}</th>
                    <th className="is-num">{tt("Size")}</th>
                  </tr>
                </thead>
                <tbody>
                  {d.db.tables.map((x) => (
                    <tr key={x.name}>
                      <td>
                        <code>{'flxg_' + x.name}</code>
                      </td>
                      <td className="is-num">{num(x.rows)}</td>
                      <td className="is-num">{x.size === null ? '—' : bytes(x.size)}</td>
                    </tr>
                  ))}
                </tbody>
                {d.db.total !== null && (
                  <tfoot>
                    <tr>
                      <th>{tt("Total")}</th>
                      <th />
                      <th className="is-num">{bytes(d.db.total)}</th>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </section>
          <section className="flxg-card-box">
            <h2>{tt("AI usage")}</h2>
            {d.ai ? (
              <div className="flxg-dl">
                <div>
                  <span>{tt("Month")}</span>
                  <b>{d.ai.month}</b>
                </div>
                <div>
                  <span>{tt("Requests")}</span>
                  <b>{num(d.ai.calls)}</b>
                </div>
                <div>
                  <span>{tt("Tokens")}</span>
                  <b>{num(d.ai.tokens)}</b>
                </div>
                <div>
                  <span>{tt("Cost (OpenRouter)")}</span>
                  <b>{'$' + (d.ai.cost || 0).toFixed(4)}</b>
                </div>
              </div>
            ) : (
              <p className="flxg-muted">{tt("No usage this month.")}</p>
            )}
          </section>
        </div>
      </>
    );
  })();
  return (
    <>
      {Top({
        title: <Crumb t={tt("Usage dashboard")} />,
        extra: (
          <button type="button" className="flxg-btn is-sm" onClick={load}>
            {tt("Refresh")}
          </button>
        ),
      })}
      <Page wide>{body}</Page>
    </>
  );
}

/* ---------------- Ayarlar (yönetici) ---------------- */
function SystemChecks({ checks, refresh }: { checks: FlxgSystemCheck[]; refresh: () => void }) {
  const order = { error: 0, warning: 1, info: 2, ok: 3 } as const;
  const cnt = { ok: 0, warning: 0, error: 0, info: 0 };
  for (const x of checks) cnt[x.status] = (cnt[x.status] || 0) + 1;
  const label = { ok: tt("OK"), warning: tt("Warning"), error: tt("Error"), info: tt("Info") };
  const sum = cnt.error ? tt("{n} problem(s)", { n: num(cnt.error) }) : cnt.warning ? tt("{n} warning(s)", { n: num(cnt.warning) }) : tt("Everything is in place");
  const bySrc: Record<string, FlxgSystemCheck[]> = {};
  for (const x of checks) (bySrc[x.source ?? ''] ??= []).push(x);
  return (
    <section className="flxg-card-box flxg-syscheck">
      <div className="flxg-sys-head">
        <div className={'flxg-sys-badge is-' + (cnt.error ? 'error' : cnt.warning ? 'warning' : 'ok')}>
          <Ic n={cnt.error || cnt.warning ? 'alert' : 'check'} s={16} />
        </div>
        <div>
          <h3>{tt("System check")}</h3>
          <p className="flxg-muted">{sum}</p>
        </div>
        <span className="flxg-grow" />
        <button type="button" className="flxg-btn is-sm" onClick={refresh}>
          {tt("Check again")}
        </button>
      </div>
      {Object.keys(bySrc).map((src) => {
        const list = bySrc[src]!.slice().sort((a, b) => order[a.status] - order[b.status] || cmpStr(a.group, b.group));
        const bad = list.filter((x) => x.status === 'error' || x.status === 'warning').length;
        return (
          <details key={src} className="flxg-sys-src" open={bad > 0}>
            <summary>
              <b>{src || tt("Other")}</b>
              <span className="flxg-muted">{bad ? tt("{n} item(s)", { n: num(bad) }) : tt("OK")}</span>
            </summary>
            {list.map((x) => (
              <div key={x.id} className={'flxg-sys-row is-' + x.status}>
                <span className="flxg-sys-dot" title={label[x.status] ?? x.status} />
                <div className="flxg-sys-txt">
                  <b>{x.label}</b>
                  {x.group && <span className="flxg-sys-group">{x.group}</span>}
                  <span className="flxg-muted">{x.message}</span>
                </div>
                {x.action_url && !x.action_url.includes('page=flx-tasks-ayarlar') && (
                  <a className="flxg-btn is-sm is-ghost" href={x.action_url}>
                    {x.action_label || tt("Open")}
                  </a>
                )}
              </div>
            ))}
          </details>
        );
      })}
    </section>
  );
}

declare global {
  interface Window {
    FLXAIModelPicker?: { attach: (el: HTMLInputElement) => void };
    FlxAIChat?: {
      mount: (el: HTMLElement, o: Record<string, unknown>) => { destroy: () => void; focus: () => void };
    };
  }
}

function SettingsForm({ d, reload }: { d: FlxgSettings; reload: (r: FlxgSettings) => void }) {
  const c = useYtg();
  const { e, ui } = c;
  const s = d.settings as Record<string, unknown> & FlxgSettings['settings'];
  const vals = useRef<Record<string, unknown>>({}).current;
  const [busy, setBusy] = useState(false);
  const modelRef = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (s.ai_hub && modelRef.current && window.FLXAIModelPicker) window.FLXAIModelPicker.attach(modelRef.current);
  }, [s.ai_hub]);
  const roleChecks = (key: 'roles_use' | 'roles_create' | 'ai_roles') => (
    <div className="flxg-checks">
      {d.roles.map((r) => (
        <label key={r.key} className="flxg-check-item">
          <input
            type="checkbox"
            defaultChecked={(s[key] ?? []).includes(r.key) || r.key === 'administrator'}
            disabled={r.key === 'administrator'}
            onChange={(ev) => {
              let arr = ((vals[key] as string[] | undefined) ?? s[key] ?? []).slice();
              if (ev.target.checked) arr.push(r.key);
              else arr = arr.filter((x) => x !== r.key);
              vals[key] = arr;
            }}
          />
          <span>{r.name}</span>
        </label>
      ))}
    </div>
  );
  const sw = (key: string, label: string, help?: string) => (
    <label className="flxg-switch-row is-setting">
      <input type="checkbox" defaultChecked={!!s[key]} onChange={(ev) => (vals[key] = ev.target.checked ? 1 : 0)} />
      <span>
        <b>{label}</b>
        {help && <small>{help}</small>}
      </span>
    </label>
  );
  const numIn = (key: string, label: string, min: number, max: number, help?: string) => (
    <label className="flxg-field">
      <span>{label}</span>
      <input className="flxg-input" type="number" min={min} max={max} defaultValue={String(s[key] ?? '')} onChange={(ev) => (vals[key] = +ev.target.value)} />
      {help && <small className="flxg-help">{help}</small>}
    </label>
  );
  const str = (k: string) => (typeof s[k] === 'string' ? (s[k] as string) : '');
  return (
    <>
      <section className="flxg-card-box">
        <h2>{tt("Access")}</h2>
        <p className="flxg-muted">{tt("Choose which WordPress roles can use Tasks. Administrators always see all projects.")}</p>
        <h3>{tt("Can use Tasks")}</h3>
        {roleChecks('roles_use')}
        <h3>{tt("Can create projects, folders and lists")}</h3>
        {roleChecks('roles_create')}
        <h3>{tt("Can use AI")}</h3>
        {roleChecks('ai_roles')}
      </section>
      <section className="flxg-card-box">
        <h2>{tt("Notifications and updates")}</h2>
        {numIn('poll_interval', tt("Live update interval (seconds)"), 15, 600, tt("An open tab sends a single lightweight request at this interval; a background tab sends none. Increase it if the server is busy (default 45)."))}
        {sw('email_notify', tt("Email notifications"), tt("Emails are sent for assignments, mentions and upcoming due dates. People can turn them off in their profile."))}
      </section>
      <section className="flxg-card-box">
        <h2>{tt("Files")}</h2>
        {numIn('max_upload_mb', tt("Single file limit (MB)"), 1, 512, tt("If the server's PHP limit is lower, that limit applies."))}
        {sw('yt_preview', tt("Preview image for YouTube links"), tt("A YouTube link on its own line in a document shows as a card with the video thumbnail. The thumbnail is loaded from YouTube; when off, the link stays plain text."))}
      </section>
      <section className="flxg-card-box">
        <h2>{tt("AI")}</h2>
        {s.ai_ready ? (
          <p className="flxg-muted">
            {tt("Keys, spending limit and reports come from the Flux AI & MCP plugin. Leave the model empty to use the Flux AI default.")}{' '}
            <a href={str('ai_hub_url')}>{tt("Flux AI settings")}</a>
          </p>
        ) : (
          <div className="flxg-notice is-warning">
            <Ic n="alert" s={16} />
            <span>{s.ai_hub ? tt("AI is off: a key or model is missing in Flux AI.") : tt("AI is off: the Flux AI & MCP plugin is not installed. The assistant and quick actions work through Flux AI.")}</span>{' '}
            <a href={str('ai_hub_url')}>{s.ai_hub ? tt("Flux AI settings") : tt("Plugins")}</a>
          </div>
        )}
        {!!s.ai_hub && (
          <label className="flxg-field">
            <span>{tt("Model (optional)")}</span>
            <input
              ref={modelRef}
              data-flxai-model=""
              className="flxg-input"
              type="text"
              defaultValue={str('ai_model')}
              placeholder={str('ai_model_eff') ? tt("Flux AI default: {m}", { m: str('ai_model_eff') }) : tt("Flux AI default")}
              onChange={(ev) => (vals.ai_model = ev.target.value.trim())}
            />
          </label>
        )}
        <label className="flxg-field">
          <span>{tt("Creativity (temperature)")}</span>
          <input className="flxg-input" type="number" step="0.1" min={0} max={1.5} defaultValue={String(s.ai_temperature ?? '')} onChange={(ev) => (vals.ai_temperature = +ev.target.value)} />
        </label>
        <label className="flxg-field">
          <span>{tt("Organization notes (the assistant knows this on every request)")}</span>
          <textarea
            className="flxg-input"
            rows={4}
            defaultValue={str('ai_instructions')}
            placeholder={tt("E.g. Association Istanbul branch. Add the \"Event\" tag to event tasks. Write dates as day.month.year.")}
            onChange={(ev) => (vals.ai_instructions = ev.target.value)}
          />
        </label>
        {d.usage && <p className="flxg-muted">{tt("This month: {c} requests, {t} tokens, ${m}", { c: num(d.usage.calls), t: num(d.usage.tokens), m: (d.usage.cost || 0).toFixed(4) })}</p>}
      </section>
      <section className="flxg-card-box">
        <h2>{tt("Data")}</h2>
        {sw('delete_data', tt("Also delete all task data when the plugin is deleted"), tt("When off, deleting the plugin leaves your data untouched."))}
      </section>
      <div className="flxg-savebar">
        <button
          type="button"
          className="flxg-btn is-primary"
          disabled={busy}
          onClick={() => {
            setBusy(true);
            e.api.saveSettings(vals).then(
              (r) => {
                setBusy(false);
                ui.toast(tt("Settings saved"));
                e.S.config.poll = Math.max(15, r.settings.poll_interval);
                e.api.boot().then((b) => e.applyStructure(b), () => {});
                reload(r);
              },
              (er) => (setBusy(false), ui.fail(er)),
            );
          }}
        >
          {tt("Save")}
        </button>
      </div>
    </>
  );
}

export function SettingsPage({ Top }: { Top: Top }) {
  const { e } = useYtg();
  const [d, setD] = useState<FlxgSettings | null>(null);
  const [err, setErr] = useState('');
  const [ver, setVer] = useState(0);
  const load = () => {
    setD(null);
    e.api.settings().then(setD, (x: unknown) => setErr(x instanceof Error ? x.message : String(x)));
  };
  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <>
      {Top({ title: <Crumb t={tt("Settings")} /> })}
      <Page>
        {err ? (
          <p className="flxg-error">{err}</p>
        ) : !d ? (
          <Skel rows={5} />
        ) : (
          <>
            {!!d.checks?.length && <SystemChecks checks={d.checks} refresh={load} />}
            <SettingsForm key={ver} d={d} reload={(r) => (setD(r), setVer((v) => v + 1))} />
          </>
        )}
      </Page>
    </>
  );
}

/* ---------------- Profil ---------------- */
export function ProfilePage({ Top }: { Top: Top }) {
  const c = useYtg();
  const host = useHost();
  const { e, ui } = c;
  const S = e.S;
  const mode = useThemeMode();
  const [, force] = useState(0);
  const cfg = S.config as typeof S.config & { emailOn?: boolean; aiMcpUrl?: string };
  const hasNotif = typeof window !== 'undefined' && 'Notification' in window;
  return (
    <>
      {Top({ title: <Crumb t={tt("Profile")} /> })}
      <Page>
        <section className="flxg-card-box">
          <div className="flxg-profile-head">
            <Avatar u={S.me} size={48} />
            <div>
              <b>{S.me?.name}</b>
              <span className="flxg-muted">{tt("Your profile photo and name come from your WordPress profile.")}</span>
            </div>
          </div>
          <h3>{tt("Theme")}</h3>
          <div className="flxg-seg">
            {(
              [
                ['auto', tt("System")],
                ['light', tt("Light")],
                ['dark', tt("Dark")],
              ] as [ThemeMode, string][]
            ).map(([k, l]) => (
              <button key={k} type="button" className={mode === k ? 'is-on' : ''} onClick={() => themeMode.set(k)}>
                {l}
              </button>
            ))}
          </div>
          {cfg.emailOn && (
            <label className="flxg-switch-row is-setting">
              <input
                type="checkbox"
                defaultChecked={!!S.me?.email}
                onChange={(ev) => {
                  if (S.me) S.me.email = ev.target.checked;
                  e.api.me({ email: ev.target.checked }).then(() => ui.toast(tt("Saved")), ui.fail);
                }}
              />
              <span>
                <b>{tt("Email notifications")}</b>
                <small>{tt("Get emails when you are assigned, mentioned, or a due date approaches.")}</small>
              </span>
            </label>
          )}
          {hasNotif && (
            <label className="flxg-switch-row is-setting">
              <input
                type="checkbox"
                checked={store.get('desktop') === '1' && Notification.permission === 'granted'}
                onChange={(ev) => {
                  if (ev.target.checked)
                    void Notification.requestPermission().then((p) => {
                      store.set('desktop', p === 'granted' ? '1' : null);
                      if (p !== 'granted') ui.toast(tt("The browser did not allow it."), { error: true });
                      force((n) => n + 1);
                    });
                  else {
                    store.set('desktop', null);
                    force((n) => n + 1);
                  }
                }}
              />
              <span>
                <b>{tt("Desktop notifications")}</b>
                <small>{tt("While the Tasks tab is open, new notifications also arrive as browser notifications.")}</small>
              </span>
            </label>
          )}
        </section>
        <section className="flxg-card-box">
          <h2>{tt("MCP")}</h2>
          {cfg.aiMcpUrl ? (
            <p className="flxg-muted">
              {tt("Flux Tasks tools are available through Flux AI & MCP.")} <a href={cfg.aiMcpUrl}>{tt("Flux AI & MCP")}</a>
            </p>
          ) : (
            <p className="flxg-muted">{tt("Flux Tasks tools require Flux AI & MCP.")}</p>
          )}
        </section>
      </Page>
    </>
  );
}

/* ---------------- Yapay zekâ asistanı (sayfa geneli) ---------------- */
type AiMsg = { role: 'user' | 'assistant'; content: string; actions?: FlxgAiAction[] };
const aiState = {
  read(): AiMsg[] {
    try {
      return JSON.parse(sessionStorage.getItem('flxg-ai') ?? '[]') as AiMsg[];
    } catch {
      return [];
    }
  },
  save(m: AiMsg[]) {
    try {
      sessionStorage.setItem('flxg-ai', JSON.stringify(m.slice(-30)));
    } catch {
      /* yok */
    }
  },
};
const aiLabel = (a: FlxgAiAction) => {
  const map: Record<string, string> = {
    create_task: tt("Task created"), update_task: tt("Task updated"), add_comment: tt("Comment added"), delete_task: tt("Task deleted"),
    create_list: tt("List created"), edit_doc_page: tt("Document page edited"), create_doc: tt("Document created"), create_doc_page: tt("Page added to document"),
  };
  return (map[a.tool] ?? a.tool) + (a.task ? ' #' + a.task : '') + (a.title ? ': ' + a.title : '') + (a.error ? ' – ' + a.error : '');
};
const PLACEHOLDER = () => tt("What do you want to do? e.g. \"Show tasks in this list due tomorrow\" or \"Open a poster task for Ayşe due Friday\"");

/** Flux AI'ın ortak sohbeti (WP'de flx-ai betiği yüklüyse): dosya/PDF, araç adımları, onay kartları. */
function AiEmbed({ close, dark }: { close: () => void; dark: boolean }) {
  const { e, ui } = useYtg();
  const box = useRef<HTMLDivElement>(null);
  const chat = useRef<{ destroy: () => void; focus: () => void } | null>(null);
  const [url, setUrl] = useState('');
  const darkRef = useRef(dark);
  darkRef.current = dark;
  const thread = (fresh: boolean) =>
    e.api.aiThread(fresh).then((r) => {
      chat.current?.destroy();
      if (!box.current || !window.FlxAIChat) return;
      box.current.textContent = '';
      chat.current = window.FlxAIChat.mount(box.current, {
        chat: r.chat,
        head: false,
        placeholder: PLACEHOLDER(),
        emptyText: tt("Hi! What should we do with your tasks? You can also attach meeting notes or a PDF and say \"create tasks from this\"."),
        dark: () => darkRef.current,
        themeEl: box.current.closest('.flxg') ?? undefined,
      });
      setUrl(r.url ?? '');
      setTimeout(() => chat.current?.focus(), 50);
    }, ui.fail);
  useEffect(() => {
    void thread(false);
    return () => chat.current?.destroy();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <aside className="flxg-ai is-embed">
      <header className="flxg-ai-head">
        <Ic n="sparkle" s={18} />
        <b>{tt("AI assistant")}</b>
        <span className="flxg-grow" />
        <button type="button" className="flxg-icon-btn" title={tt("New chat")} onClick={() => void thread(true)}>
          <Ic n="plus" s={16} />
        </button>
        {url && (
          <a className="flxg-icon-btn" href={url} target="_blank" rel="noopener noreferrer" title={tt("Open full screen")}>
            <Ic n="external" s={16} />
          </a>
        )}
        <button type="button" className="flxg-icon-btn" title={tt("Close")} onClick={close}>
          <Ic n="x" s={18} />
        </button>
      </header>
      <div className="flxg-ai-embed" ref={box} />
    </aside>
  );
}

function AiSimple({ close, ctx }: { close: () => void; ctx: { task?: number; page?: { id: number; title: string } | null } }) {
  const { e, ui } = useYtg();
  const S = e.S;
  const [msgs, setMsgs] = useState<AiMsg[]>(aiState.read);
  const [busy, setBusy] = useState(false);
  const [text, setText] = useState('');
  const [taskCtx, setTaskCtx] = useState(ctx.task ?? S.openTask ?? 0);
  const [pageCtx, setPageCtx] = useState(ctx.page ?? null);
  const log = useRef<HTMLDivElement>(null);
  const ta = useRef<HTMLTextAreaElement>(null);
  useLayoutEffect(() => {
    if (log.current) log.current.scrollTop = log.current.scrollHeight;
  }, [msgs, busy]);
  useEffect(() => void setTimeout(() => ta.current?.focus(), 50), []);
  const put = (m: AiMsg[]) => (aiState.save(m), setMsgs(m));
  const send = () => {
    const t = text.trim();
    if (!t || busy) return;
    const next: AiMsg[] = [...msgs, { role: 'user', content: t }];
    put(next);
    setText('');
    setBusy(true);
    const list = S.scope?.type === 'list' ? S.scope.id : 0;
    e.api.aiChat({ messages: next.map((m) => ({ role: m.role, content: m.content })), context: { list_id: list, task_id: taskCtx, doc_page: pageCtx?.id ?? 0 } }).then(
      (r) => {
        setBusy(false);
        put([...next, { role: 'assistant', content: r.reply || '…', actions: r.actions ?? [] }]);
        if (r.actions?.length) {
          // Asistanın dokunduğu görevler filtreye uymasa da ekranda kalsın.
          for (const a of r.actions) if (a.ok && a.task && a.tool !== 'delete_task') e.keepTask(a.task);
          void e.reloadTasks();
          e.emit('task-remote');
          if (r.actions.some((a) => a.ok && a.doc)) e.emit('doc-remote');
        }
      },
      (er: unknown) => {
        setBusy(false);
        put([...next, { role: 'assistant', content: '⚠️ ' + (er instanceof Error ? er.message : String(er)) }]);
      },
    );
  };
  const listCtx = S.scope?.type === 'list' ? e.list(S.scope.id) : null;
  const go = (a: FlxgAiAction) => (a.task ? e.openTask(a.task) : a.doc ? void e.go({ page: 'doc', id: a.doc, pg: a.page ?? 0 }) : undefined);
  return (
    <aside className="flxg-ai">
      <header className="flxg-ai-head">
        <Ic n="sparkle" s={18} />
        <b>{tt("AI assistant")}</b>
        <span className="flxg-grow" />
        <button type="button" className="flxg-icon-btn" title={tt("Clear chat")} onClick={() => put([])}>
          <Ic n="trash" s={16} />
        </button>
        <button type="button" className="flxg-icon-btn" title={tt("Close")} onClick={close}>
          <Ic n="x" s={18} />
        </button>
      </header>
      <div className="flxg-ai-log" ref={log}>
        {!msgs.length && (
          <div className="flxg-ai-hello">
            <Ic n="sparkle" s={28} />
            <b>{tt("Hi! What shall we do with your tasks?")}</b>
            <div className="flxg-ai-sugs">
              {[tt("List my overdue tasks assigned to me"), tt("Add 3 tasks to this list: …"), tt("Summarize this week")].map((s) => (
                <button key={s} type="button" className="flxg-chip-btn" onClick={() => (setText(s), ta.current?.focus())}>
                  {s}
                </button>
              ))}
            </div>
          </div>
        )}
        {msgs.map((m, i) => (
          <div key={i} className={'flxg-ai-msg is-' + m.role}>
            {m.role === 'assistant' ? (
              <div
                className="flxg-prose"
                onClick={(ev) => {
                  const a = (ev.target as HTMLElement).closest('a');
                  const m = /^#\/task\/(\d+)$/.exec(a?.getAttribute('href') ?? '');
                  if (m) {
                    ev.preventDefault();
                    e.openTask(+m[1]!);
                  } else openSanitizedLink(ev);
                }}
                dangerouslySetInnerHTML={{ __html: sanitizeHtml(richText(m.content, S.users, S.me?.id ?? 0).replace(/#(\d+)/g, '<a href="#/task/$1">#$1</a>')) }}
              />
            ) : (
              <div>{m.content}</div>
            )}
            {!!m.actions?.length && (
              <div className="flxg-ai-actions">
                {m.actions.map((a, j) =>
                  a.task || a.doc ? (
                    <a key={j} className={cls('flxg-chip', !a.ok && 'is-error')} href="#" onClick={(ev) => (ev.preventDefault(), go(a))}>
                      <Ic n={a.ok ? 'check' : 'x'} s={12} />
                      {aiLabel(a)}
                    </a>
                  ) : (
                    <span key={j} className={cls('flxg-chip', !a.ok && 'is-error')}>
                      <Ic n={a.ok ? 'check' : 'x'} s={12} />
                      {aiLabel(a)}
                    </span>
                  ),
                )}
              </div>
            )}
          </div>
        ))}
        {busy && (
          <div className="flxg-ai-msg is-assistant">
            <div className="flxg-typing">
              <span />
              <span />
              <span />
            </div>
          </div>
        )}
      </div>
      <div className="flxg-ai-foot">
        <div className="flxg-ai-ctx">
          {listCtx && (
            <span className="flxg-chip">
              <span className="flxg-dot" style={{ background: listCtx.color }} />
              {listCtx.name}
            </span>
          )}
          {!!taskCtx && (
            <span className="flxg-chip">
              {'#' + taskCtx}
              <button type="button" aria-label={tt("Remove")} onClick={() => setTaskCtx(0)}>
                <Ic n="x" s={12} />
              </button>
            </span>
          )}
          {pageCtx && (
            <span className="flxg-chip">
              <Ic n="doc" s={12} />
              {pageCtx.title || tt("Untitled")}
              <button type="button" aria-label={tt("Remove")} onClick={() => setPageCtx(null)}>
                <Ic n="x" s={12} />
              </button>
            </span>
          )}
        </div>
        <div className="flxg-composer">
          <textarea
            ref={ta}
            className="flxg-composer-input"
            rows={2}
            value={text}
            placeholder={PLACEHOLDER()}
            onChange={(ev) => setText(ev.target.value)}
            onKeyDown={(ev) => {
              if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                send();
              }
            }}
          />
          <div className="flxg-composer-bar">
            <span className="flxg-muted flxg-hint">{tt("The assistant adds and changes tasks with your permissions.")}</span>
            <span className="flxg-grow" />
            <button type="button" className="flxg-btn is-primary is-sm" onClick={send}>
              <Ic n="send" s={14} />
              {tt("Send")}
            </button>
          </div>
        </div>
      </div>
    </aside>
  );
}

/* Panel durumu: açık/kapalı + bağlam (görev, doküman sayfası). */
type AiOpen = { task?: number; page?: { id: number; title: string } | null; n: number } | null;
let aiOpen: AiOpen = null;
const aiSubs = new Set<() => void>();
export function openAI(ctx: { task?: number; page?: { id: number; title: string } | null } = {}) {
  aiOpen = aiOpen && !ctx.task && !ctx.page ? null : { ...ctx, n: (aiOpen?.n ?? 0) + 1 };
  aiSubs.forEach((f) => f());
}
const closeAI = () => ((aiOpen = null), aiSubs.forEach((f) => f()));
export function AiPanel({ embed, dark }: { embed: boolean; dark: boolean }) {
  const st = useSyncExternalStore(
    (f) => (aiSubs.add(f), () => void aiSubs.delete(f)),
    () => aiOpen,
    () => aiOpen,
  );
  if (!st) return null;
  return embed && window.FlxAIChat ? <AiEmbed key={st.n} close={closeAI} dark={dark} /> : <AiSimple key={st.n} close={closeAI} ctx={st} />;
}
