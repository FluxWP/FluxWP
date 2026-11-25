// Forms › Automations: the flow list with the ready-made donation flow, and the run log
// (flxFormsAutomations / flxFormsAutomationRuns). One flow opens the editor (FlowEditor).
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsAutomationsQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxFormsAutomationDefault,
  FlxFormsAutomationDelete,
  FlxFormsAutomationDonationForm,
  FlxFormsAutomationDuplicate,
  FlxFormsAutomationRuns,
  FlxFormsAutomationRunsClear,
  FlxFormsAutomationToggle,
  FlxFormsAutomations,
} from '@fluxwp/graphql/operations';
import { Check, Loading, Modal } from '@fluxwp/ui';
import { copyText, errorText, payloadError, useForms } from './common';
import { FlowEditor, actionLabel } from './FlowEditor';

type Flow = FlxFormsAutomationsQuery['flxFormsAutomations']['flows'][number];

export function AutomationsPage({ flow, tab }: { flow: number; tab: string }) {
  if (flow) return <FlowEditor id={flow} />;
  return <AutomationsList runs={tab === 'runs'} />;
}

function AutomationsList({ runs }: { runs: boolean }) {
  const { t, go, num } = useForms();
  const q = useFlxQuery(FlxFormsAutomations, {});
  const d = q.data?.flxFormsAutomations;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;
  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('Run steps automatically after a form is submitted or a payment is completed: create and send certificates, show them on the thank-you screen, e-mail, webhook.')}</p>
      {!d.certAvailable ? (
        <p className="flxfrm-note is-warn">
          <strong>{t('Flux Certificates is not active.')}</strong> {t('Certificate steps are disabled until the Flux Certificates plugin is activated. E-mail and webhook steps keep working.')}
        </p>
      ) : null}
      <div className="flxfrm-toolbar">
        <div className="flxfrm-seg" role="tablist">
          <button type="button" role="tab" aria-selected={!runs} className={!runs ? 'is-on' : ''} onClick={() => go('automations')}>
            {t('Flows')}
          </button>
          <button type="button" role="tab" aria-selected={runs} className={runs ? 'is-on' : ''} onClick={() => go('automations', { tab: 'runs' })}>
            {t('Run log')} <span className="flxfrm-badge">{num(d.runCount)}</span>
          </button>
        </div>
        <button type="button" className="flxfrm-btn is-primary" onClick={() => go('automations', { flow: -1 })}>
          + {t('New automation')}
        </button>
      </div>
      {runs ? <RunLog max={d.maxRuns} /> : <Flows d={d} />}
    </div>
  );
}

function Flows({ d }: { d: FlxFormsAutomationsQuery['flxFormsAutomations'] }) {
  const { t, toast, go, num } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [ask, setAsk] = useState<Flow | null>(null);
  const toggle = useFlxMutation(FlxFormsAutomationToggle);
  const dup = useFlxMutation(FlxFormsAutomationDuplicate);
  const del = useFlxMutation(FlxFormsAutomationDelete);
  const def = useFlxMutation(FlxFormsAutomationDefault);
  const donation = useFlxMutation(FlxFormsAutomationDonationForm);
  const refresh = () => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomations) });
  const run = async (fn: () => Promise<ReadonlyArray<{ message: string }> | undefined | null>, ok?: string) => {
    try {
      const err = payloadError(await fn());
      if (err) throw new Error(err);
      if (ok) toast(ok);
      refresh();
      return true;
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
      return false;
    }
  };
  const setEnabled = (f: Flow, enabled: boolean) => {
    qc.setQueryData(flxQueryKey(scope, FlxFormsAutomations, {}), { flxFormsAutomations: { ...d, flows: d.flows.map((x) => (x.id === f.id ? { ...x, enabled } : x)) } });
    void run(async () => (await toggle.mutateAsync({ input: { id: f.id, enabled } })).flxFormsAutomationToggle?.errors);
  };
  const createDefault = async () => {
    try {
      const r = (await def.mutateAsync({ input: {} })).flxFormsAutomationDefault;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      refresh();
      if (r?.id) go('automations', { flow: r.id });
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  const createForm = async () => {
    try {
      const r = (await donation.mutateAsync({ input: {} })).flxFormsAutomationDonationForm;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      refresh();
      if (r?.shortcode) void copyText(r.shortcode, toast, t);
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  return (
    <>
      <div className="flxfrm-card is-flush">
        {d.flows.length ? (
          <table className="flxfrm-table">
            <thead>
              <tr>
                <th>{t('Enabled')}</th>
                <th>{t('Automation')}</th>
                <th>{t('Trigger')}</th>
                <th>{t('Form')}</th>
                <th>{t('Last run')}</th>
                <th className="is-num">{t('Runs')}</th>
                <th aria-label={t('Actions')} />
              </tr>
            </thead>
            <tbody>
              {d.flows.map((f) => (
                <tr key={f.id}>
                  <td data-label={t('Enabled')}>
                    <Check switch checked={f.enabled} aria-label={t('Enable {name}', { name: f.title })} onChange={(v) => setEnabled(f, v)} />
                  </td>
                  <td data-label={t('Automation')}>
                    <button type="button" className="flxfrm-link" onClick={() => go('automations', { flow: f.id })}>
                      <strong>{f.title}</strong>
                    </button>
                    <div className="flxfrm-steps">
                      {f.steps.map((st, i) => (
                        <span key={i}>
                          {i ? <span className="flxfrm-muted"> → </span> : null}
                          <span className="flxfrm-badge">{actionLabel(st, t)}</span>
                        </span>
                      ))}
                    </div>
                  </td>
                  <td data-label={t('Trigger')}>{f.triggerType === 'payment_completed' ? t('Payment completed') : t('Form submitted')}</td>
                  <td data-label={t('Form')}>{f.formId ? f.formTitle : t('Any form')}</td>
                  <td data-label={t('Last run')}>{f.lastRun ? <span title={f.lastRun.label}>{f.lastRun.relative}</span> : <span className="flxfrm-muted">—</span>}</td>
                  <td data-label={t('Runs')} className="is-num">
                    {num(f.runs)}
                  </td>
                  <td className="flxfrm-rowactions">
                    <button type="button" className="flxfrm-btn" onClick={() => go('automations', { flow: f.id })}>
                      {t('Edit')}
                    </button>
                    <button type="button" className="flxfrm-btn" disabled={dup.isPending} onClick={() => void run(async () => (await dup.mutateAsync({ input: { id: f.id } })).flxFormsAutomationDuplicate?.errors, t('Automation copied.'))}>
                      {t('Duplicate')}
                    </button>
                    <button type="button" className="flxfrm-btn is-danger-text" onClick={() => setAsk(f)}>
                      {t('Delete')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="flxfrm-empty">{t('No automations yet.')}</p>
        )}
      </div>

      <section className="flxfrm-card flxfrm-starter">
        <h2>{t('Ready-made: sapling donation certificate')}</h2>
        <p className="flxfrm-muted">{t('After a verified payment on the donation form: create a “Fidan Bağış Sertifikası” (sapling count = amount ÷ unit price), send it by WhatsApp and e-mail, and show it on the thank-you screen.')}</p>
        <div className="flxfrm-actions">
          <button type="button" className="flxfrm-btn" disabled={def.isPending} onClick={() => void createDefault()}>
            {t('Create default flow')}
          </button>
          {d.donationFormId ? (
            <span className="flxfrm-muted">{t('Donation form: {name}', { name: d.donationFormTitle })}</span>
          ) : (
            <button type="button" className="flxfrm-btn" disabled={donation.isPending} onClick={() => void createForm()}>
              {t('Create the “Sapling Donation” form')}
            </button>
          )}
        </div>
      </section>

      {ask ? (
        <Modal title={t('Delete')} onClose={() => setAsk(null)}>
          <p>{t('Delete this automation? This cannot be undone.')}</p>
          <p>
            <strong>{ask.title}</strong>
          </p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxfrm-btn is-danger"
              disabled={del.isPending}
              onClick={() =>
                void run(async () => (await del.mutateAsync({ input: { id: ask.id } })).flxFormsAutomationDelete?.errors, t('Automation deleted.')).then((ok) => {
                  if (ok) setAsk(null);
                })
              }
            >
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </>
  );
}

function RunLog({ max }: { max: number }) {
  const { t, toast, go } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxFormsAutomationRuns, {});
  const clear = useFlxMutation(FlxFormsAutomationRunsClear);
  const [ask, setAsk] = useState(false);
  const rows = q.data?.flxFormsAutomationRuns;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!rows) return <Loading />;
  const status: Record<string, string> = { ok: t('Done'), partial: t('Partly done'), error: t('Error'), skipped: t('Skipped'), running: t('Running') };
  const tone: Record<string, string> = { ok: 'ok', partial: 'warning', error: 'error', skipped: 'info', running: 'pending' };
  const doClear = async () => {
    try {
      const r = (await clear.mutateAsync({ input: {} })).flxFormsAutomationRunsClear;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      setAsk(false);
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomationRuns) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomations) });
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  if (!rows.length) return <p className="flxfrm-card flxfrm-empty">{t('No runs yet. Runs appear here after a matching submission.')}</p>;
  return (
    <>
      <div className="flxfrm-toolbar">
        <p className="flxfrm-muted">{t('The latest {n} runs are kept.', { n: max })}</p>
        <button type="button" className="flxfrm-btn" onClick={() => setAsk(true)}>
          {t('Clear log')}
        </button>
      </div>
      <div className="flxfrm-card is-flush">
        <table className="flxfrm-table">
          <thead>
            <tr>
              <th>{t('Time')}</th>
              <th>{t('Automation')}</th>
              <th>{t('Form')}</th>
              <th>{t('Tracking code')}</th>
              <th>{t('Status')}</th>
              <th>{t('Steps')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td data-label={t('Time')}>{r.time ? <span title={r.time.relative}>{r.time.label}</span> : '—'}</td>
                <td data-label={t('Automation')}>
                  {r.flowId ? (
                    <button type="button" className="flxfrm-link" onClick={() => go('automations', { flow: r.flowId })}>
                      <strong>{r.flowTitle}</strong>
                    </button>
                  ) : (
                    r.flowTitle
                  )}
                </td>
                <td data-label={t('Form')}>{r.formTitle}</td>
                <td data-label={t('Tracking code')}>
                  <code>{r.track}</code>
                </td>
                <td data-label={t('Status')}>
                  <span className={'flxfrm-pill is-' + (tone[r.status] ?? 'info')}>{status[r.status] ?? r.status}</span>
                </td>
                <td data-label={t('Steps')}>
                  <ol className="flxfrm-steplog">
                    {r.steps.map((st, i) => (
                      <li key={i}>
                        <span className={'flxfrm-dot is-' + (tone[st.status] ?? 'info')} aria-hidden="true" /> <strong>{actionLabel(st.type, t)}</strong> <span className="flxfrm-muted">{status[st.status] ?? st.status}</span>
                        {st.message ? (
                          <div className="flxfrm-muted flxfrm-steplog__msg">
                            {st.type === 'show_on_thankyou' && st.message.startsWith('http') ? (
                              <a href={st.message} target="_blank" rel="noopener noreferrer">
                                {st.message}
                              </a>
                            ) : (
                              st.message
                            )}
                          </div>
                        ) : null}
                      </li>
                    ))}
                  </ol>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {ask ? (
        <Modal title={t('Clear log')} onClose={() => setAsk(false)}>
          <p>{t('Clear the run log?')}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAsk(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxfrm-btn is-danger" disabled={clear.isPending} onClick={() => void doClear()}>
              {t('Clear log')}
            </button>
          </div>
        </Modal>
      ) : null}
    </>
  );
}
