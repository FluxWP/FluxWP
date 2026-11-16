// CRM › Journeys: automatic contact flows (templates, list, editor).
import { useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmJourneyDelete, FlxCrmJourneyFromTemplate, FlxCrmJourneySave, FlxCrmJourneySetStatus, FlxCrmJourneys } from '@fluxwp/graphql/operations';
import type { FlxCrmJourneysQuery, FlxJourneyStatus } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, Empty, Head, Ic, errText, href, useCrm, type Route } from './ui';

type Journey = FlxCrmJourneysQuery['flxJourneys'][number];
interface Step {
  type: string;
  days: string;
  hours: string;
  tag: string;
  condition: string;
  value: string;
  subject: string;
  text: string;
}
const STATUS_TONE: Record<string, string> = { ACTIVE: ' is-ok', PAUSED: ' is-warn', DRAFT: '' };

export function Journeys({ route, go }: { route: Route; go: (to: string) => void }) {
  const crm = useCrm();
  const q = useFlxQuery(FlxCrmJourneys, {});
  if (q.isLoading) return <Loading />;
  if (!q.data) return <div className="flxcrm-notice is-bad">{q.error?.message ?? crm.t('The action could not be completed.')}</div>;
  return route.id && crm.can ? <Editor key={route.id} id={route.id} data={q.data} go={go} /> : <List data={q.data} go={go} />;
}

function useRefresh() {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const crm = useCrm();
  return () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmJourneys) });
    crm.reloadShell();
  };
}

function List({ data, go }: { data: FlxCrmJourneysQuery; go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, can } = crm;
  const refresh = useRefresh();
  const tpl = useFlxMutation(FlxCrmJourneyFromTemplate);
  const status = useFlxMutation(FlxCrmJourneySetStatus);
  const del = useFlxMutation(FlxCrmJourneyDelete);
  const [ask, setAsk] = useState<{ kind: 'on' | 'delete'; j: Journey } | null>(null);
  const statusName = (k: string) => data.flxJourneyConfig.statuses.find((s) => s.key === k.toLowerCase())?.label ?? k;

  const run = async (fn: () => Promise<{ errors: ReadonlyArray<{ message: string }> } | null | undefined>, ok: string) => {
    try {
      const r = await fn();
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      crm.tell('ok', ok);
      refresh();
    } catch (e) {
      crm.fail(e);
    }
  };
  const setTo = (j: Journey, to: FlxJourneyStatus) => run(async () => (await status.mutateAsync({ input: { id: j.databaseId, status: to } })).flxJourneySetStatus, t('Journey updated.'));
  const confirm = () => {
    if (!ask) return;
    const { kind, j } = ask;
    setAsk(null);
    if (kind === 'on') void setTo(j, 'ACTIVE');
    else void run(async () => (await del.mutateAsync({ input: { id: j.databaseId } })).flxJourneyDelete, t('Journey deleted.'));
  };

  return (
    <>
      <Head
        title={t('Journeys')}
        lead={t('Automatic flows: when something happens to a contact, messages and tags follow on a schedule. A new journey starts as a draft; nothing is sent until you turn it on. Messages go only to people who allowed that channel.')}
        actions={
          can && (
            <a className="button button-primary" href={href('journeys/new')}>
              <Ic n="plus" /> {t('New journey')}
            </a>
          )
        }
      />
      {can && (
        <Card title={t('Start from a template')} icon="chart">
          <div className="flxcrm-jtpl">
            {data.flxJourneyConfig.templates.map((x) => (
              <button
                key={x.key}
                type="button"
                className="button"
                disabled={tpl.isPending}
                onClick={() =>
                  void tpl
                    .mutateAsync({ input: { template: x.key } })
                    .then((r) => {
                      const j = r.flxJourneyFromTemplate;
                      if (!j?.journey) throw new Error(errText(j?.errors));
                      refresh();
                      go('journeys/' + j.journey.databaseId);
                    })
                    .catch(crm.fail)
                }
              >
                {x.label}
              </button>
            ))}
          </div>
          <p className="flxcrm-muted">{t('A template is saved as a draft for you to read and edit before turning it on.')}</p>
        </Card>
      )}
      {data.flxJourneys.length === 0 ? (
        <Empty icon="send" title={t('No journeys yet.')} />
      ) : (
        <div className="flxcrm-seglist">
          {data.flxJourneys.map((j) => (
            <article key={j.databaseId} className="flxcrm-card flxcrm-segcard">
              <div className="flxcrm-segcard__head">
                <div>
                  <h2>
                    {j.name} <span className={'flxcrm-badge' + (STATUS_TONE[j.status] ?? '')}>{statusName(j.status)}</span>
                  </h2>
                  <p className="flxcrm-muted">{j.description}</p>
                </div>
                <strong className="flxcrm-segcard__count">
                  {num(j.stats.active)}
                  <small>{t('in the journey')}</small>
                </strong>
              </div>
              <p className="flxcrm-rule-sum">
                {t('Entered %1$s · finished %2$s · reached goal %3$s · messages sent %4$s · skipped %5$s', num(j.stats.entered), num(j.stats.finished), num(j.stats.goal), num(j.stats.sent), num(j.stats.skipped))}
              </p>
              {can && (
                <div className="flxcrm-segcard__actions">
                  <button type="button" className="button button-small" onClick={() => go('journeys/' + j.databaseId)}>
                    {t('Edit')}
                  </button>
                  <button type="button" className="button button-small" disabled={status.isPending} onClick={() => (j.status === 'ACTIVE' ? void setTo(j, 'PAUSED') : setAsk({ kind: 'on', j }))}>
                    {j.status === 'ACTIVE' ? t('Pause') : t('Turn on')}
                  </button>
                  <button type="button" className="button button-small" onClick={() => setAsk({ kind: 'delete', j })}>
                    {t('Delete')}
                  </button>
                </div>
              )}
            </article>
          ))}
        </div>
      )}
      {ask && (
        <Modal title={ask.kind === 'on' ? t('Turn on') : t('Delete journey')} onClose={() => setAsk(null)}>
          <p>{ask.kind === 'on' ? t('"%s" starts now: contacts who match from now on enter it and get its messages.', ask.j.name) : t('"%s" and its progress will be deleted. Messages already sent stay on the timeline.', ask.j.name)}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className={'button ' + (ask.kind === 'on' ? 'button-primary' : 'button-danger')} onClick={confirm}>
              {ask.kind === 'on' ? t('Turn on') : t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function Editor({ id, data, go }: { id: string; data: FlxCrmJourneysQuery; go: (to: string) => void }) {
  const crm = useCrm();
  const { t, shell } = crm;
  const refresh = useRefresh();
  const save = useFlxMutation(FlxCrmJourneySave);
  const cfg = data.flxJourneyConfig;
  const isNew = id === 'new';
  const j = isNew ? undefined : data.flxJourneys.find((x) => x.databaseId === Number(id));
  const tagName = (v: string) => shell.flxTags.find((x) => String(x.databaseId) === v)?.name ?? v;
  const [name, setName] = useState(j?.name ?? '');
  const [tt, setTt] = useState(j?.triggerType ?? 'segment');
  const [tv, setTv] = useState(j?.triggerValue ?? 'new');
  const [steps, setSteps] = useState<Step[]>(
    (j?.steps ?? [{ type: 'email', days: null, hours: null, subject: '', text: '', condition: null, value: null, tag: null }]).map((s) => ({
      type: s.type,
      days: String(s.days ?? 0),
      hours: String(s.hours ?? 0),
      tag: tagName(s.tag ?? ''),
      condition: s.condition ?? 'segment',
      value: s.condition === 'tag' || s.condition === 'no_tag' ? tagName(s.value ?? '') : (s.value ?? ''),
      subject: s.subject ?? '',
      text: s.text ?? '',
    })),
  );
  const d = cfg.defaults;
  const [o, setO] = useState({ goal: j?.opts.goal ?? d.goal, reenterDays: String(j?.opts.reenterDays ?? d.reenterDays), quietFrom: String(j?.opts.quietFrom ?? d.quietFrom), quietTo: String(j?.opts.quietTo ?? d.quietTo), capWeek: String(j?.opts.capWeek ?? d.capWeek) });
  if (!isNew && !j) return <Empty title={t('Journey not found.')} />;

  const values = cfg.triggerValues.find((x) => x.trigger === tt)?.values ?? [];
  const setStep = (i: number, p: Partial<Step>) => setSteps((s) => s.map((x, k) => (k === i ? { ...x, ...p } : x)));
  const num = (v: string) => Number.parseInt(v, 10) || 0;

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (await save.mutateAsync({
        input: {
          id: isNew ? null : Number(id),
          name,
          triggerType: tt,
          triggerValue: values.length ? tv : '',
          steps: steps.map((s) => ({ type: s.type, days: num(s.days), hours: num(s.hours), tag: s.tag, condition: s.condition, value: s.value, subject: s.subject, text: s.text })),
          opts: { goal: o.goal, reenterDays: num(o.reenterDays), quietFrom: num(o.quietFrom), quietTo: num(o.quietTo), capWeek: num(o.capWeek) },
        },
      })).flxJourneySave;
      if (!r?.journey) throw new Error(errText(r?.errors));
      refresh();
      crm.tell('ok', t('Journey saved.'));
      go('journeys');
    } catch (err) {
      crm.fail(err);
    }
  };
  const onTrigger = (v: string) => {
    setTt(v);
    setTv(cfg.triggerValues.find((x) => x.trigger === v)?.values[0]?.value ?? '');
  };

  return (
    <>
      <Head title={isNew ? t('New journey') : t('Edit journey')} lead={t('Use {first_name}, {name} and {site} in texts. Saving an active journey pauses it so you can check it before turning it back on.')} back={{ to: 'journeys', label: t('Journeys') }} />
      <form onSubmit={(e) => void submit(e)}>
        <datalist id="flxcrm-jtags">
          {shell.flxTags.map((x) => (
            <option key={x.databaseId} value={x.name} />
          ))}
        </datalist>
        <div className="flxcrm-card">
          <label className="flxcrm-jf">
            <span>{t('Name')}</span>
            <input type="text" required maxLength={190} value={name} onChange={(e) => setName(e.currentTarget.value)} />
          </label>
          <div className="flxcrm-jrow">
            <label className="flxcrm-jf">
              <span>{t('Starts when a contact')}</span>
              <select value={tt} onChange={(e) => onTrigger(e.currentTarget.value)}>
                {cfg.triggers.map((x) => (
                  <option key={x.key} value={x.key}>
                    {x.label}
                  </option>
                ))}
              </select>
            </label>
            {values.length > 0 && (
              <label className="flxcrm-jf">
                <span>{t('Which')}</span>
                <select value={tv} onChange={(e) => setTv(e.currentTarget.value)}>
                  {values.map((x) => (
                    <option key={x.value} value={x.value}>
                      {x.label}
                    </option>
                  ))}
                </select>
              </label>
            )}
          </div>
        </div>

        <h2 className="flxcrm-h2">{t('Steps')}</h2>
        <ol className="flxcrm-jsteps">
          {steps.map((s, i) => (
            <li key={i} className="flxcrm-card flxcrm-jstep">
              <div className="flxcrm-jrow">
                <label className="flxcrm-jf">
                  <span>{t('Step')}</span>
                  <select value={s.type} onChange={(e) => setStep(i, { type: e.currentTarget.value })}>
                    {cfg.stepTypes.map((x) => (
                      <option key={x.key} value={x.key}>
                        {x.label}
                      </option>
                    ))}
                  </select>
                </label>
                {s.type === 'wait' && (
                  <>
                    <label className="flxcrm-jf">
                      <span>{t('Days')}</span>
                      <input type="number" min={0} max={365} value={s.days} onChange={(e) => setStep(i, { days: e.currentTarget.value })} />
                    </label>
                    <label className="flxcrm-jf">
                      <span>{t('Hours')}</span>
                      <input type="number" min={0} max={23} value={s.hours} onChange={(e) => setStep(i, { hours: e.currentTarget.value })} />
                    </label>
                  </>
                )}
                {(s.type === 'tag' || s.type === 'untag') && (
                  <label className="flxcrm-jf">
                    <span>{t('Tag')}</span>
                    <input type="text" list="flxcrm-jtags" value={s.tag} onChange={(e) => setStep(i, { tag: e.currentTarget.value })} />
                  </label>
                )}
                {s.type === 'if' && (
                  <>
                    <label className="flxcrm-jf">
                      <span>{t('Condition')}</span>
                      <select value={s.condition} onChange={(e) => setStep(i, { condition: e.currentTarget.value })}>
                        {cfg.conditions.map((x) => (
                          <option key={x.key} value={x.key}>
                            {x.label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label className="flxcrm-jf">
                      <span>{t('Value (value group key or tag)')}</span>
                      <input type="text" value={s.value} placeholder="loyal" onChange={(e) => setStep(i, { value: e.currentTarget.value })} />
                    </label>
                  </>
                )}
                <button type="button" className="button-link flxcrm-jdel" aria-label={t('Remove step')} onClick={() => setSteps((x) => x.filter((_, k) => k !== i))}>
                  ×
                </button>
              </div>
              {s.type === 'email' && (
                <label className="flxcrm-jf">
                  <span>{t('Subject')}</span>
                  <input type="text" value={s.subject} onChange={(e) => setStep(i, { subject: e.currentTarget.value })} />
                </label>
              )}
              {(s.type === 'email' || s.type === 'whatsapp' || s.type === 'sms') && (
                <label className="flxcrm-jf">
                  <span>{t('Text')}</span>
                  <textarea rows={4} value={s.text} onChange={(e) => setStep(i, { text: e.currentTarget.value })} />
                </label>
              )}
            </li>
          ))}
        </ol>
        <p>
          <button type="button" className="button" onClick={() => setSteps((s) => [...s, { type: 'wait', days: '1', hours: '0', tag: '', condition: 'segment', value: '', subject: '', text: '' }])}>
            <Ic n="plus" size={14} /> {t('Add step')}
          </button>
        </p>

        <h2 className="flxcrm-h2">{t('Rules')}</h2>
        <div className="flxcrm-card">
          <div className="flxcrm-jrow">
            <label className="flxcrm-jf">
              <span>{t('Goal: leave the journey when')}</span>
              <select value={o.goal} onChange={(e) => setO({ ...o, goal: e.currentTarget.value })}>
                <option value="">{t('No goal (run all steps)')}</option>
                {cfg.goals.map((x) => (
                  <option key={x.key} value={x.key}>
                    {x.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="flxcrm-jf">
              <span>{t('Can enter again after (days, 0 = never)')}</span>
              <input type="number" min={0} max={3650} value={o.reenterDays} onChange={(e) => setO({ ...o, reenterDays: e.currentTarget.value })} />
            </label>
            <label className="flxcrm-jf">
              <span>{t('No messages between (hour)')}</span>
              <span className="flxcrm-jhours">
                <input type="number" min={0} max={23} value={o.quietFrom} onChange={(e) => setO({ ...o, quietFrom: e.currentTarget.value })} /> –{' '}
                <input type="number" min={0} max={23} value={o.quietTo} onChange={(e) => setO({ ...o, quietTo: e.currentTarget.value })} />
              </span>
            </label>
            <label className="flxcrm-jf">
              <span>{t('Most messages per person in 7 days (all journeys)')}</span>
              <input type="number" min={1} max={14} value={o.capWeek} onChange={(e) => setO({ ...o, capWeek: e.currentTarget.value })} />
            </label>
          </div>
        </div>
        <p className="flxcrm-jsave">
          <button className="button button-primary" disabled={save.isPending || !name.trim()}>
            {isNew ? t('Save as draft') : t('Save')}
          </button>
          <a className="button" href={href('journeys')}>
            {t('Cancel')}
          </a>
        </p>
      </form>
    </>
  );
}
