// Flux AI › Scheduled tasks: the list with run now / pause / delete, and the form (instructions, skill, repeat,
// notifications with recipients, files). Everything goes over the flxAiTask* operations.
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { FlxAiRecipients, FlxAiTaskDelete, FlxAiTaskRun, FlxAiTaskSave, FlxAiTaskSetActive, FlxAiTasks, FlxAiTasksToggle } from '@fluxwp/graphql/operations';
import type { FlxAiRecipientFieldsFragment, FlxAiTaskFieldsFragment, FlxAiTaskOverviewFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { DatePicker, Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, errText, useAi, type AiT, type Notice } from './common';

type Task = FlxAiTaskFieldsFragment;
type Overview = FlxAiTaskOverviewFieldsFragment;
type Recipient = FlxAiRecipientFieldsFragment;

const FREQS: [string, string][] = [
  ['daily', 'Every day'],
  ['weekdays', 'Weekdays'],
  ['weekly', 'Selected days of the week'],
  ['monthly', 'Every month'],
  ['hourly', 'Every few hours'],
  ['once', 'Once'],
];

function StatePill({ task, t }: { task: Task; t: AiT['t'] }) {
  if (task.status === 'paused') return <Pill>{t('Paused')}</Pill>;
  if (task.status === 'done') return <Pill>{t('Done')}</Pill>;
  const map: Record<string, ['ok' | 'warn' | 'err' | undefined, string]> = {
    ok: ['ok', 'Completed'],
    running: [undefined, 'Running'],
    approval: ['warn', 'Awaiting approval'],
    waiting: ['warn', 'Skipped: approval pending'],
    error: ['err', 'Error'],
  };
  const m = map[task.lastState];
  return m ? <Pill kind={m[0]}>{t(m[1])}</Pill> : <Pill kind="ok">{t('Active')}</Pill>;
}

export function Tasks() {
  const { t, num, usd, ms } = useAi();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [notice, setNotice] = useState<Notice | null>(null);
  const [editing, setEditing] = useState<Task | 'new' | null>(null);
  const [asking, setAsking] = useState<Task | null>(null);
  const q = useFlxQuery(FlxAiTasks, {});
  const key = flxQueryKey(scope, FlxAiTasks, {});
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const refresh = () => qc.invalidateQueries({ queryKey: key });
  const check = (errors: { message: string }[] | undefined | null) => {
    if (errors?.length) {
      setNotice({ kind: 'error', text: errors.map((e) => e.message).join(' ') });
      return false;
    }
    return true;
  };
  const run = useFlxMutation(FlxAiTaskRun, {
    onSuccess: (r) => {
      if (check(r.flxAiTaskRun?.errors)) setNotice({ kind: 'success', text: t('The task will start in a few seconds. Its report appears in the task chat.') });
      refresh();
    },
    onError: fail,
  });
  const pause = useFlxMutation(FlxAiTaskSetActive, {
    onSuccess: (r) => {
      check(r.flxAiTaskSetActive?.errors);
      refresh();
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxAiTaskDelete, {
    onSuccess: (r) => {
      if (check(r.flxAiTaskDelete?.errors)) setNotice({ kind: 'success', text: t('Task deleted. Its chat was kept.') });
      setAsking(null);
      refresh();
    },
    onError: fail,
  });
  const toggle = useFlxMutation(FlxAiTasksToggle, {
    onSuccess: (r) => {
      check(r.flxAiTasksToggle?.errors);
      refresh();
    },
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const o: Overview = q.data.flxAiTasks;
  const adminUrl = host.adminUrl ?? '';
  const chatUrl = (id: number) => `${adminUrl}admin.php?page=flx-ai-tasks&open=${id}`;
  const active = o.tasks.filter((x) => x.status === 'active');
  const busy = run.isPending || pause.isPending || del.isPending || toggle.isPending;

  if (editing)
    return (
      <>
        <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
        <TaskForm
          task={editing === 'new' ? null : editing}
          o={o}
          onCancel={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            setNotice({ kind: 'success', text: t('Saved.') });
            refresh();
          }}
        />
      </>
    );

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t("Instructions the assistant runs on its own at set times: a daily briefing, a weekly donor review, a site performance check. Each task runs with its owner's permissions and writes its report to its own chat; changes wait for approval.")}</p>
      <div className="flxai-kpis">
        <Kpi label={t('Active')} value={num(active.length)} sub={t('%s in total', num(o.tasks.length))} />
        <Kpi label={t('Next run')} value={o.nextRun?.label ?? '—'} />
        <Kpi label={t('Awaiting approval')} value={num(o.awaiting)} sub={t('Across all your chats and tasks')} warn={o.awaiting > 0} />
        <Kpi label={t('Last check')} value={o.lastCheck?.label ?? '—'} sub={o.enabled ? t('Checked every 5 minutes') : t('Tasks are turned off')} warn={!o.enabled} />
      </div>
      {o.awaiting > 0 && (
        <div className="flxai-notice is-warn">
          {t('%s actions are waiting for your approval.', num(o.awaiting))}{' '}
          <a href={`${adminUrl}admin.php?page=flx-ai-approvals`}>{t('Review approvals')}</a>
        </div>
      )}
      <Card
        title={
          <span className="flxai-card-head">
            {t('Tasks')}
            <span className="flxai-card-acts">
              <button type="button" className="flxai-btn" disabled={busy} onClick={() => toggle.mutate({ input: { enabled: !o.enabled } })}>
                {o.enabled ? t('Turn off all tasks') : t('Turn on tasks')}
              </button>
              <button type="button" className="flxai-btn is-primary" onClick={() => setEditing('new')}>
                {t('New task')}
              </button>
            </span>
          </span>
        }
      >
        {!o.tasks.length && (
          <div className="flxai-tasks-empty">
            <p>
              <strong>{t('No scheduled tasks yet.')}</strong>
            </p>
            <p className="flxai-hint">{t('Add one here, or just tell the assistant, for example:')}</p>
            <ul>
              {[
                "Give me an AI briefing every morning at 9: yesterday's donations, new messages and anything that needs me.",
                'Every Monday at 10, review donors who have not given in 3 months and suggest who to call.',
                'Check the site speed every 6 hours and tell me only if something got worse.',
              ].map((ex) => (
                <li key={ex}>“{t(ex)}”</li>
              ))}
            </ul>
          </div>
        )}
        {o.tasks.map((k) => (
          <article key={k.id} className={'flxai-tc' + (k.lastState === 'error' ? ' is-err' : '') + (k.status !== 'active' ? ' is-off' : '') + (k.unread ? ' is-new' : '')}>
            <div className="flxai-tc-head">
              <div className="flxai-tc-main">
                <div className="flxai-tc-title">
                  {k.chatId ? <a href={chatUrl(k.id)}>{k.title}</a> : <strong>{k.title}</strong>} <StatePill task={k} t={t} />
                  {k.unread && <Pill kind="ok">{t('New report')}</Pill>}
                  {k.skill && <code className="flxai-skill-slug">/{k.skill}</code>}
                </div>
                <div className="flxai-tc-meta">
                  <span>{k.scheduleLabel}</span>
                  {k.nextRun && k.status === 'active' && <span>{t('Next: %s', k.nextRun.label)}</span>}
                  {k.runs > 0 && (
                    <span>
                      {[t('Runs: %s', num(k.runs)), k.avgMs ? t('avg. %s', ms(k.avgMs)) : '', k.cost > 0 ? usd(k.cost) : ''].filter(Boolean).join(' · ')}
                    </span>
                  )}
                  {k.notify.label && <span title={t('Notifications')}>🔔 {k.notify.label}</span>}
                  {!k.mine && <span>{t('Runs as %s', k.owner)}</span>}
                </div>
              </div>
              <div className="flxai-tc-acts">
                {k.chatId > 0 && (
                  <a className="flxai-btn is-small" href={chatUrl(k.id)}>
                    {t('Details')}
                  </a>
                )}
                <button type="button" className="flxai-btn is-small" disabled={busy} onClick={() => run.mutate({ input: { id: k.id } })}>
                  ▶ {t('Run now')}
                </button>
                <button type="button" className="flxai-btn is-small" disabled={busy} onClick={() => pause.mutate({ input: { id: k.id, active: k.status !== 'active' } })}>
                  {k.status === 'active' ? t('Pause') : t('Resume')}
                </button>
                <button type="button" className="flxai-btn is-small" onClick={() => setEditing(k)}>
                  {t('Edit')}
                </button>
                <button type="button" className="flxai-btn is-small is-danger" onClick={() => setAsking(k)}>
                  {t('Delete')}
                </button>
              </div>
            </div>
            {!k.lastRun ? (
              <div className="flxai-tc-prompt">{k.prompt.split(/\s+/).slice(0, 30).join(' ')}</div>
            ) : k.lastError && k.lastState === 'error' ? (
              <div className="flxai-tc-err">{k.lastError.slice(0, 300)}</div>
            ) : (
              <details className="flxai-tc-out" open={k.unread}>
                <summary>
                  <span>{t('Last output')}</span> <span className="flxai-hint">{k.lastRun.label}</span>
                </summary>
                <div className="flxai-tc-body">
                  {k.lastOutput ? <div className="flxai-appr-note">{k.lastOutput}</div> : <p className="flxai-hint">{t('No details were kept for this run.')}</p>}
                  {k.chatId > 0 && (
                    <p>
                      <a href={chatUrl(k.id)}>{t('Open in the AI chat: reasoning, tool calls and follow-up')} →</a>
                    </p>
                  )}
                </div>
              </details>
            )}
          </article>
        ))}
        {o.tasks.length > 0 && <p className="flxai-hint">{t('Tip: you can also create and change tasks from the chat, e.g. "move my daily briefing to 8:30 and add the weather".')}</p>}
      </Card>
      {asking && (
        <Modal title={t('Delete')} onClose={() => setAsking(null)}>
          <p>{t('Delete the task "%s"? Its chat is kept.', asking.title)}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxai-btn is-danger" disabled={del.isPending} onClick={() => del.mutate({ input: { id: asking.id } })}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function Kpi({ label, value, sub, warn }: { label: string; value: string; sub?: string; warn?: boolean }) {
  return (
    <div className={'flxai-kpi' + (warn ? ' is-warn' : '')}>
      <div className="flxai-kpi-k">{label}</div>
      <div className="flxai-kpi-v">{value}</div>
      {sub && <div className="flxai-kpi-s">{sub}</div>}
    </div>
  );
}

const rec = (r: Recipient) => ({ kind: r.kind, id: r.id, name: r.name, email: r.email, phone: r.phone, jid: r.jid, channels: r.channels });

function TaskForm({ task, o, onCancel, onSaved }: { task: Task | null; o: Overview; onCancel: () => void; onSaved: () => void }) {
  const { t } = useAi();
  const [err, setErr] = useState<{ text: string; field?: string } | null>(null);
  const [f, setF] = useState(() => ({
    title: task?.title ?? '',
    prompt: task?.prompt ?? '',
    skill: task?.skill ?? '',
    freq: task?.schedule.freq ?? 'daily',
    time: task?.schedule.time ?? '09:00',
    days: task?.schedule.days ?? [1],
    day: task?.schedule.day ?? 1,
    every: task?.schedule.every ?? 6,
    at: (task?.schedule.at ?? '').replace(' ', 'T'),
    active: !task || task.status === 'active',
    mode: task?.notify.mode ?? 'important',
    panel: task?.notify.panel ?? true,
    email: task?.notify.email ?? false,
    wa: !!task?.notify.whatsapp,
    whatsapp: task?.notify.whatsapp ? '+' + task.notify.whatsapp : '',
    promo: task?.notify.promo ?? false,
    to: (task?.notify.to ?? []).map(rec),
    access: task?.workspace?.access === 'site' ? 'site' : 'workspace',
    folders: (task?.workspace?.folders ?? []).join('\n'),
  }));
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((s) => ({ ...s, [k]: v }));
  const save = useFlxMutation(FlxAiTaskSave, {
    onSuccess: (r) => {
      const p = r.flxAiTaskSave;
      if (p?.errors.length) return setErr({ text: p.errors.map((e) => e.message).join(' '), field: p.errors[0]?.field ?? undefined });
      onSaved();
    },
    onError: (e) => setErr({ text: errText(e, t('The action could not be completed.')) }),
  });
  const submit = () =>
    save.mutate({
      input: {
        id: task?.id ?? null,
        title: f.title,
        prompt: f.prompt,
        skill: f.skill,
        active: f.active,
        schedule: { freq: f.freq, time: f.time, days: f.days, day: f.day, every: f.every, at: f.at },
        notify: { mode: f.mode, email: f.email, panel: f.panel, whatsapp: f.wa ? f.whatsapp : '', promo: f.promo, to: f.to },
        workspace: o.workspaceReady || task?.workspace ? { access: f.access, folders: f.folders.split('\n').map((x) => x.trim()).filter(Boolean) } : undefined,
      },
    });
  const wd = (d: number) => new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(new Date(2024, 0, d)); // 2024-01-01 is a Monday
  const row = (label: string, body: React.ReactNode, hint?: string) => (
    <div className="flxai-field">
      <div className="flxai-field-label">{label}</div>
      <div className="flxai-field-body">
        {body}
        {hint && <p className="flxai-hint">{hint}</p>}
      </div>
    </div>
  );
  return (
    <Card title={task ? t('Edit task') : t('New task')}>
      {err && (
        <div className="flxai-notice is-error" role="alert">
          {err.text}
        </div>
      )}
      {row(t('Name'), <input className="flxai-input" maxLength={190} value={f.title} placeholder={t('Daily AI briefing')} onChange={(e) => set('title', e.target.value)} />)}
      {row(
        t('Instructions'),
        <textarea className="flxai-input" rows={7} value={f.prompt} placeholder={t("Summarise yesterday's donations, unanswered messages and site issues. Keep it short, with headings.")} onChange={(e) => set('prompt', e.target.value)} />,
        t('Written to the assistant as if you sent it in the chat. It uses the same tools (CRM, Donate, SEO, Speed…) with your permissions.'),
      )}
      {row(
        t('Skill'),
        <select className="flxai-input" value={f.skill} onChange={(e) => set('skill', e.target.value)}>
          <option value="">{t('None')}</option>
          {o.skills.map((s) => (
            <option key={s.slug} value={s.slug}>
              {s.name + (s.active ? '' : ' (' + t('Off') + ')')}
            </option>
          ))}
        </select>,
        t("The skill's instructions are added on every run, always in their latest version. The instructions above can stay short (e.g. \"only donations\")."),
      )}
      {row(
        t('Repeat'),
        <select className="flxai-input" value={f.freq} onChange={(e) => set('freq', e.target.value)}>
          {FREQS.map(([k, l]) => (
            <option key={k} value={k}>
              {t(l)}
            </option>
          ))}
        </select>,
      )}
      {f.freq === 'weekly' &&
        row(
          t('Days'),
          <>
            {[1, 2, 3, 4, 5, 6, 7].map((d) => (
              <label key={d} className="flxai-check">
                <input type="checkbox" checked={f.days.includes(d)} onChange={(e) => set('days', e.target.checked ? [...f.days, d] : f.days.filter((x) => x !== d))} /> {wd(d)}
              </label>
            ))}
          </>,
        )}
      {f.freq === 'monthly' && row(t('Day of month'), <input className="flxai-input is-small" type="number" min={1} max={31} value={f.day} onChange={(e) => set('day', Number(e.target.value))} />, t('In shorter months the task runs on the last day.'))}
      {f.freq === 'hourly' && row(t('Every'), <><input className="flxai-input is-small" type="number" min={1} max={24} value={f.every} onChange={(e) => set('every', Number(e.target.value))} /> {t('hours')}</>)}
      {f.freq !== 'once' && row(t('Time'), <input className="flxai-input is-small" type="time" value={f.time} onChange={(e) => set('time', e.target.value)} />, t('Site time zone: %s.', o.timezone))}
      {f.freq === 'once' && row(t('Date and time'), <DatePicker time value={f.at} onChange={(v) => set('at', v)} />)}
      {row(
        t('Status'),
        <label className="flxai-check">
          <input type="checkbox" checked={f.active} onChange={(e) => set('active', e.target.checked)} /> {t('Active')}
        </label>,
      )}
      {row(
        t('Notify me'),
        <select className="flxai-input" value={f.mode} onChange={(e) => set('mode', e.target.value)}>
          <option value="important">{t('Only when something important comes up')}</option>
          <option value="always">{t('After every run')}</option>
          <option value="none">{t('Never')}</option>
        </select>,
        t('"Important": the assistant decides there is something you should know now, an action waits for your approval, or the task failed.'),
      )}
      {row(
        t('Channels'),
        <>
          <label className="flxai-check">
            <input type="checkbox" checked={f.panel} onChange={(e) => set('panel', e.target.checked)} /> {t('In the admin panel (badge on the menu)')}
          </label>
          <label className="flxai-check">
            <input type="checkbox" checked={f.email} onChange={(e) => set('email', e.target.checked)} /> {t('By e-mail (%s)', o.ownerEmail)}
          </label>
          <label className="flxai-check">
            <input type="checkbox" checked={f.wa} disabled={!o.whatsappReady} onChange={(e) => set('wa', e.target.checked)} /> {t('By WhatsApp')}
          </label>
          {o.whatsappReady ? f.wa && <input className="flxai-input" type="tel" value={f.whatsapp} placeholder="905321234567" onChange={(e) => set('whatsapp', e.target.value)} /> : <p className="flxai-hint">{t('WhatsApp needs Flux Mail with a WhatsApp connection.')}</p>}
        </>,
      )}
      {row(
        t('Also notify'),
        <Recipients to={f.to} channels={o.channels} onChange={(v) => set('to', v)} />,
        t('People from Flux CRM, site users or WhatsApp groups. Pick a channel for each.'),
      )}
      {row(
        t('Promotional content'),
        <label className="flxai-check">
          <input type="checkbox" checked={f.promo} onChange={(e) => set('promo', e.target.checked)} /> {t('Promotional content: skip people who opted out (STOP) and add an opt-out line')}
        </label>,
      )}
      {(o.workspaceReady || task?.workspace) &&
        row(
          t('Files'),
          <>
            {!o.workspaceReady && <p className="flxai-hint">{t('Install Flux Files to give tasks a workspace folder (reports, notes, drafts).')}</p>}
            <label className="flxai-check">
              <input type="radio" name="ws" checked={f.access === 'workspace'} onChange={() => set('access', 'workspace')} /> {t('Only its own folder and the folders below')}
            </label>
            <label className="flxai-check">
              <input type="radio" name="ws" checked={f.access === 'site'} onChange={() => set('access', 'site')} /> {t('Can read all site files')}
            </label>
            <textarea className="flxai-input" rows={3} value={f.folders} placeholder="wp-content/uploads/raporlar" onChange={(e) => set('folders', e.target.value)} />
            <p className="flxai-hint">
              {t('Allowed folders (one per line, relative to the site root)')}. {t("The task writes freely only in its own folder. Any change to other files waits for your approval as a change request. Other chats' folders, passwords and WordPress core are always off limits.")} {task?.workspace?.dir && <code>{task.workspace.dir}</code>}
            </p>
          </>,
        )}
      <div className="flxai-appr-acts">
        <button type="button" className="flxai-btn is-primary" disabled={save.isPending} onClick={submit}>
          {save.isPending ? t('Saving…') : t('Save')}
        </button>
        <button type="button" className="flxai-btn" onClick={onCancel}>
          {t('Cancel')}
        </button>
      </div>
    </Card>
  );
}

function Recipients({ to, channels, onChange }: { to: ReturnType<typeof rec>[]; channels: Overview['channels']; onChange: (v: ReturnType<typeof rec>[]) => void }) {
  const { t } = useAi();
  const [term, setTerm] = useState('');
  const [q, setQ] = useState('');
  useEffect(() => {
    const id = setTimeout(() => setQ(term.trim()), 300);
    return () => clearTimeout(id);
  }, [term]);
  const res = useFlxQuery(FlxAiRecipients, { search: q }, { enabled: q.length > 1 });
  const avail = (['email', 'sms', 'whatsapp'] as const).filter((c) => channels[c]);
  const label: Record<string, string> = { email: t('E-mail'), sms: 'SMS', whatsapp: 'WhatsApp' };
  const add = (r: Recipient) => {
    if (to.some((x) => x.kind === r.kind && x.id === r.id && x.email === r.email && x.phone === r.phone && x.jid === r.jid)) return;
    const ch = r.kind === 'group' ? ['whatsapp'] : r.email && channels.email ? ['email'] : avail.filter((c) => (c === 'email' ? r.email : r.phone)).slice(0, 1);
    onChange([...to, { ...rec(r), channels: ch.length ? ch : ['email'] }]);
    setTerm('');
  };
  return (
    <div className="flxai-rcp">
      {to.map((r, i) => (
        <div key={i} className="flxai-rcp-row">
          <strong>{r.name}</strong>
          {r.kind === 'group' ? (
            <span className="flxai-hint">WhatsApp</span>
          ) : (
            avail.map((c) => (
              <label key={c} className="flxai-check">
                <input type="checkbox" checked={r.channels.includes(c)} onChange={(e) => onChange(to.map((x, j) => (j === i ? { ...x, channels: e.target.checked ? [...x.channels, c] : x.channels.filter((y) => y !== c) } : x)))} /> {label[c]}
              </label>
            ))
          )}
          <button type="button" className="flxai-btn is-small" onClick={() => onChange(to.filter((_, j) => j !== i))}>
            {t('Remove')}
          </button>
        </div>
      ))}
      <input className="flxai-input" type="search" autoComplete="off" value={term} placeholder={t('Search name, e-mail, phone or group…')} aria-label={t('Add recipient')} onChange={(e) => setTerm(e.target.value)} />
      {q.length > 1 && res.data && (
        <div className="flxai-rcp-res">
          {res.data.flxAiRecipients.items.map((r, i) => (
            <button key={i} type="button" className="flxai-btn is-small" onClick={() => add(r)}>
              {r.name} <span className="flxai-hint">{r.sub}</span>
            </button>
          ))}
          {!res.data.flxAiRecipients.items.length && <span className="flxai-hint">{t('No matches')}</span>}
        </div>
      )}
    </div>
  );
}
