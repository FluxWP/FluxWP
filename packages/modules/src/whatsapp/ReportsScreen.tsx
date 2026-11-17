// Flux Chat WhatsApp reports: every rule card of the classic page (recipients, content, period, schedule,
// selected people, closing lines, preview, test, send now, delete) over Flux Chat GraphQL, plus the delivery
// state (due, running, next run) and a button that runs the schedule on the token-protected background
// request. A send or delete first saves the cards, like the classic form.
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxChatReportAdd, FlxChatReportDelete, FlxChatReportPreview, FlxChatReportRun, FlxChatReports, FlxChatReportsSave, FlxChatReportSend, FlxChatReportStatus,
} from '@fluxwp/graphql/operations';
import type { FlxChatReportsQuery, FlxChatReportAudience, FlxChatReportContent, FlxChatReportFreq, FlxChatReportPeriod } from '@fluxwp/graphql/types';
import { Check, Loading } from '@fluxwp/ui';
import { AdminRoot, Card, errorText, Field, NoticeBar, useAdminT, type Notice } from './admin-common';
import type { Translate } from './admin-i18n';

type Reports = FlxChatReportsQuery['flxChatReports'];
type Rule = Reports['rules'][number];

interface Draft {
  id: string;
  title: string;
  active: boolean;
  audience: FlxChatReportAudience;
  users: number[];
  content: FlxChatReportContent;
  period: FlxChatReportPeriod;
  freq: FlxChatReportFreq;
  weekday: number;
  monthday: number;
  time: string;
  motivation: string;
}

const cardOf = (r: Rule): Draft => ({ id: r.id, title: r.title, active: r.active, audience: r.audience, users: [...r.users], content: r.content, period: r.period, freq: r.freq, weekday: r.weekday, monthday: r.monthday, time: r.time, motivation: r.motivation });
const toInput = (c: Draft) => ({ id: c.id, title: c.title, active: c.active, audience: c.audience, users: c.users, content: c.content, period: c.period, freq: c.freq, weekday: c.weekday, monthday: c.monthday, time: c.time, motivation: c.motivation });

export function ReportsScreen() {
  return (
    <AdminRoot>
      <ReportsView />
    </AdminRoot>
  );
}

function ReportsView() {
  const t = useAdminT();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxChatReports, {});
  const status = useFlxQuery(FlxChatReportStatus, {});
  const [cards, setCards] = useState<Draft[]>([]);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [busyId, setBusyId] = useState('');
  const rules = q.data?.flxChatReports.rules;
  useEffect(() => {
    if (rules) setCards(rules.map(cardOf));
  }, [rules]);

  const put = (r: Reports) => qc.setQueryData<FlxChatReportsQuery>(flxQueryKey(scope, FlxChatReports, {}), { flxChatReports: r });
  const fail = (e: unknown) => setNotice({ kind: 'bad', text: errorText(e, t) });
  const save = useFlxMutation(FlxChatReportsSave, {});
  const add = useFlxMutation(FlxChatReportAdd, {});
  const del = useFlxMutation(FlxChatReportDelete, {});
  const send = useFlxMutation(FlxChatReportSend, {});
  const run = useFlxMutation(FlxChatReportRun, {});

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxca-app"><div className="flxca-notice is-bad">{errorText(q.error, t)}</div></div>;
  const r = q.data.flxChatReports;

  /** Saves every card (the classic form always does) and shows the stored result. */
  const saveAll = async (): Promise<boolean> => {
    const p = (await save.mutateAsync({ input: { rules: cards.map(toInput) } })).flxChatReportsSave;
    if (!p || p.errors.length) {
      setNotice({ kind: 'bad', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      return false;
    }
    put(p.reports);
    return true;
  };
  const act = async (id: string, fn: () => Promise<void>) => {
    setBusyId(id);
    try {
      await fn();
    } catch (e) {
      fail(e);
    }
    setBusyId('');
  };

  const onSave = () => act('save', async () => {
    if (await saveAll()) setNotice({ kind: 'ok', text: t('Saved.') });
  });
  const onAdd = () => act('add', async () => {
    if (!(await saveAll())) return;
    const p = (await add.mutateAsync({ input: {} })).flxChatReportAdd;
    if (p) {
      put(p.reports);
      setNotice({ kind: 'ok', text: t('Saved.') });
    }
  });
  const onDelete = (c: Draft) => {
    if (!window.confirm(t('Delete this report?'))) return;
    void act(c.id, async () => {
      if (!(await saveAll())) return;
      const p = (await del.mutateAsync({ input: { id: c.id } })).flxChatReportDelete;
      if (p) {
        put(p.reports);
        setNotice({ kind: 'ok', text: t('Saved.') });
      }
    });
  };
  const onSend = (c: Draft, mode: 'TEST' | 'ALL') => {
    if (mode === 'ALL' && !window.confirm(t('Send the report to all recipients now?'))) return;
    void act(c.id + mode, async () => {
      if (!(await saveAll())) return;
      const p = (await send.mutateAsync({ input: { id: c.id, mode } })).flxChatReportSend;
      if (!p) throw new Error('');
      if (p.errors.length) return setNotice({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      setNotice({ kind: 'ok', text: t('%d messages sent.', p.sent ?? 0) });
    });
  };
  const onRun = () => act('run', async () => {
    const p = (await run.mutateAsync({ input: {} })).flxChatReportRun;
    await status.refetch();
    setNotice(p?.started ? { kind: 'ok', text: t('The schedule was started in the background.') } : { kind: 'warn', text: t('The background request did not start. Check the connection below.') });
  });

  const patch = (id: string, p: Partial<Draft>) => setCards((cs) => cs.map((c) => (c.id === id ? { ...c, ...p } : c)));
  const st = status.data?.flxChatReportStatus;

  return (
    <div className="flxca-app">
      <header className="flxca-head">
        <div>
          <h1>{t('WhatsApp reports')}</h1>
          <p className="flxca-lead">{t('Scheduled donation reports for staff and managers; a short motivational line can be added at the end.')}</p>
        </div>
      </header>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      {!r.on && <div className="flxca-notice is-bad">{t('Reports need Flux Roles 1.1.0 and Flux Donations.')}</div>}

      {st && (
        <Card title={t('Sending')}>
          <div className="flxca-sendstate">
            <span className={'flxca-badge is-' + (st.due ? 'warn' : 'ok')}>{st.due ? t('A report is waiting to be sent') : t('Nothing is waiting')}</span>
            {st.running && <span className="flxca-badge is-warn">{t('Sending now')}</span>}
            <span className="flxca-small">{st.nextAt ? t('Next scheduled run: %s', st.nextAt.label) : t('No run is scheduled.')}</span>
            <button type="button" className="flxca-btn" disabled={busyId !== '' || !st.on} onClick={() => void onRun()}>{t('Run the schedule now')}</button>
          </div>
          {st.loopbackError && <p className="flxca-warn">{t('The last background request failed: %s', st.loopbackError)}{st.loopbackErrorAt ? ' · ' + st.loopbackErrorAt.relative : ''}</p>}
          <p className="flxca-small">{t('Reports go out at the chosen time on a background request to this site, so a server scheduler is not needed. Press the button to send what is due right away.')}</p>
        </Card>
      )}

      {cards.map((c) => {
        const rule = r.rules.find((x) => x.id === c.id);
        return (
          <section key={c.id} className="flxca-card flxca-report" id={c.id}>
            <div className="flxca-report__head">
              <input type="text" className="flxca-report__title" value={c.title} aria-label={t('Report name')} onChange={(e) => patch(c.id, { title: e.currentTarget.value })} />
              <Check checked={c.active} onChange={(v) => patch(c.id, { active: v })}>{t('Active')}</Check>
            </div>
            <div className="flxca-grid is-3">
              <Sel label={t('To')} value={c.audience} onChange={(v) => patch(c.id, { audience: v as FlxChatReportAudience })} options={[['RECEIVE', t('Everyone who can receive reports')], ['VIEW', t('Those who can see the overall report (managers)')], ['USERS', t('Selected people')]]} />
              <Sel label={t('Content')} value={c.content} onChange={(v) => patch(c.id, { content: v as FlxChatReportContent })} options={[['OWN', t("The person's own donations")], ['GENERAL', t('Overall totals + per staff')]]} />
              <Sel label={t('Period')} value={c.period} onChange={(v) => patch(c.id, { period: v as FlxChatReportPeriod })} options={[['TODAY', t('Today')], ['YESTERDAY', t('Yesterday')], ['WEEK', t('Last 7 days')], ['MONTH', t('This month')]]} />
              <Sel label={t('Frequency')} value={c.freq} onChange={(v) => patch(c.id, { freq: v as FlxChatReportFreq })} options={[['DAILY', t('Every day')], ['WEEKLY', t('Every week')], ['MONTHLY', t('Every month')]]} />
              <Sel label={t('Day of week')} value={String(c.weekday)} onChange={(v) => patch(c.id, { weekday: Number(v) })} options={r.days.map((d) => [String(d.value), d.label] as [string, string])} />
              <Field label={t('Day of month')}>
                <input type="number" min={1} max={28} value={c.monthday} onChange={(e) => patch(c.id, { monthday: Number(e.currentTarget.value) })} />
              </Field>
              <Field label={t('Time')}>
                <input type="time" value={c.time} onChange={(e) => patch(c.id, { time: e.currentTarget.value })} />
              </Field>
            </div>
            {r.people.length > 0 && (
              <details className="flxca-details" open={c.audience === 'USERS'}>
                <summary>{t('Selected people')}</summary>
                <div className="flxca-people">
                  {r.people.map((p) => (
                    <Check key={p.databaseId} checked={c.users.includes(p.databaseId)} onChange={(v) => patch(c.id, { users: v ? [...c.users, p.databaseId] : c.users.filter((x) => x !== p.databaseId) })}>
                      {p.name}{!p.hasNumber && <small> ({t('no number')})</small>}
                    </Check>
                  ))}
                </div>
              </details>
            )}
            <Field label={t('Motivational lines (one per line; one is added at random to each report)')}>
              <textarea rows={3} value={c.motivation} onChange={(e) => patch(c.id, { motivation: e.currentTarget.value })} />
            </Field>
            <p className="flxca-small">
              {t('%d recipients (with a registered number and permission).', rule?.recipientCount ?? 0)}
              {rule?.lastSent ? ' ' + t('Last sent: %s.', rule.lastSent.label) : ''}
            </p>
            <Preview id={c.id} dirty={rule ? JSON.stringify(cardOf(rule)) !== JSON.stringify(c) : true} t={t} />
            <div className="flxca-actions">
              <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => onSend(c, 'TEST')}>{t('Send me a test')}</button>
              <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => onSend(c, 'ALL')}>{t('Send to everyone now')}</button>
              <button type="button" className="flxca-btn is-danger" disabled={busyId !== ''} onClick={() => onDelete(c)}>{t('Delete')}</button>
            </div>
          </section>
        );
      })}
      <div className="flxca-save">
        <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => void onAdd()}>{t('+ New report')}</button>
        <button type="button" className="flxca-btn is-primary" disabled={busyId !== ''} onClick={() => void onSave()}>{t('Save')}</button>
      </div>
      <p className="flxca-small">{t("The recipient's number comes from Users › Profile › WhatsApp number; permissions from Users › Roles and access (receive reports, view overall report, manage reports). Reports go out at the chosen time; without a server scheduler they are sent on the next site visit or chat panel use.")}</p>
    </div>
  );
}

function Sel({ label, value, options, onChange }: { label: string; value: string; options: [string, string][]; onChange: (v: string) => void }) {
  return (
    <Field label={label}>
      <select value={value} onChange={(e) => onChange(e.currentTarget.value)}>
        {options.map(([v, l]) => (
          <option key={v} value={v}>{l}</option>
        ))}
      </select>
    </Field>
  );
}

/** The report text as the viewer would get it. It reads the saved rule, so unsaved edits ask for a save first. */
function Preview({ id, dirty, t }: { id: string; dirty: boolean; t: Translate }) {
  const [open, setOpen] = useState(false);
  const q = useFlxQuery(FlxChatReportPreview, { id }, { enabled: open && !dirty });
  const { refetch } = q;
  useEffect(() => {
    if (open && !dirty) void refetch();
  }, [open, dirty, refetch]);
  return (
    <details className="flxca-details" onToggle={(e) => setOpen(e.currentTarget.open)}>
      <summary>{t('Preview (for you)')}</summary>
      {dirty ? <p className="flxca-small">{t('Save the report to see its preview.')}</p> : q.isLoading ? <Loading /> : <pre className="flxca-pre">{q.data?.flxChatReportPreview ?? ''}</pre>}
    </details>
  );
}
