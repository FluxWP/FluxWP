// Forms › Automations: the flow list with the ready-made donation flow, and the run log
// (flxFormsAutomations / flxFormsAutomationRuns). One flow opens the editor (FlowEditor).
import { Fragment } from 'react';
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
import { Loading, PageActions, confirmDialog, usePageHead } from '@fluxwp/ui';
import { copyText, errorText, payloadError, useForms } from './common';
import { FlowEditor, actionLabel } from './FlowEditor';
import { Ico } from './classic';

type Flow = FlxFormsAutomationsQuery['flxFormsAutomations']['flows'][number];

export function AutomationsPage({ flow, tab }: { flow: number; tab: string }) {
  if (flow) return <FlowEditor id={flow} />;
  return <AutomationsList runs={tab === 'runs'} />;
}

function AutomationsList({ runs }: { runs: boolean }) {
  const { t, go, num } = useForms();
  const q = useFlxQuery(FlxFormsAutomations, {});
  const d = q.data?.flxFormsAutomations;
  usePageHead(t('Automations'), t('Run steps automatically after a form is submitted or a payment is completed: create and send certificates, show them on the thank-you screen, e-mail, webhook.'));
  const head = (
    <PageActions>
      <button type="button" className="flxfa-btn flxfa-btn--primary" onClick={() => go('automations', { flow: -1 })}>
        <Ico name="plus" />
        {t('New automation')}
      </button>
    </PageActions>
  );
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;
  return (
    <div className="flxfa flx-raw">
      {head}
      {!d.certAvailable ? (
        <div className="flxfa-notice flxfa-notice--warn">
          <Ico name="alert" />
          <div>
            <strong>{t('Flux Certificates is not active.')}</strong> {t('Certificate steps are disabled until the Flux Certificates plugin is activated. E-mail and webhook steps keep working.')}
          </div>
        </div>
      ) : null}
      <nav className="flxfa-tabs" aria-label={t('Automations')}>
        <a
          className={'flxfa-tab' + (!runs ? ' is-active' : '')}
          href="#/automations"
          aria-current={!runs ? 'page' : undefined}
          onClick={(e) => {
            e.preventDefault();
            go('automations');
          }}
        >
          {t('Flows')}
        </a>
        <a
          className={'flxfa-tab' + (runs ? ' is-active' : '')}
          href="#/automations?tab=runs"
          aria-current={runs ? 'page' : undefined}
          onClick={(e) => {
            e.preventDefault();
            go('automations', { tab: 'runs' });
          }}
        >
          {t('Run log')} <span className="flxfa-count">{num(d.runCount)}</span>
        </a>
      </nav>
      {runs ? <RunLog max={d.maxRuns} /> : <Flows d={d} />}
    </div>
  );
}

function Flows({ d }: { d: FlxFormsAutomationsQuery['flxFormsAutomations'] }) {
  const { t, toast, go, num } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
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
  const remove = async (f: Flow) => {
    if (!(await confirmDialog(t('Delete this automation? This cannot be undone.'), true))) return;
    await run(async () => (await del.mutateAsync({ input: { id: f.id } })).flxFormsAutomationDelete?.errors, t('Automation deleted.'));
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
      <div className="flxfa-panel" id="flxfa-list">
        {d.flows.length ? (
          <div className="flxfa-table-wrap">
            <table className="flxfa-table">
              <thead>
                <tr>
                  <th scope="col">{t('Enabled')}</th>
                  <th scope="col">{t('Automation')}</th>
                  <th scope="col">{t('Trigger')}</th>
                  <th scope="col">{t('Form')}</th>
                  <th scope="col">{t('Last run')}</th>
                  <th scope="col" className="num">
                    {t('Runs')}
                  </th>
                  <th scope="col">
                    <span className="screen-reader-text">{t('Actions')}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {d.flows.map((f) => (
                  <tr key={f.id}>
                    <td>
                      <label className="flxfa-switch">
                        <input type="checkbox" className="flxfa-toggle" checked={f.enabled} aria-label={t('Enable {name}', { name: f.title })} onChange={(e) => setEnabled(f, e.target.checked)} />
                        <span></span>
                      </label>
                    </td>
                    <td>
                      <a
                        className="flxfa-name"
                        href={'#/automations?flow=' + f.id}
                        onClick={(e) => {
                          e.preventDefault();
                          go('automations', { flow: f.id });
                        }}
                      >
                        {f.title}
                      </a>
                      <div className="flxfa-steps">
                        {f.steps.map((st, i) => (
                          <Fragment key={i}>
                            {i ? (
                              <span className="flxfa-arrow" aria-hidden="true">
                                →
                              </span>
                            ) : null}
                            <span className="flxfa-chip">{actionLabel(st, t)}</span>
                          </Fragment>
                        ))}
                      </div>
                    </td>
                    <td>
                      {f.triggerType === 'payment_completed' ? (
                        <>
                          <Ico name="card" />
                          {t('Payment completed')}
                        </>
                      ) : (
                        <>
                          <Ico name="form" />
                          {t('Form submitted')}
                        </>
                      )}
                    </td>
                    <td>{f.formId ? f.formTitle : t('Any form')}</td>
                    <td>{f.lastRun ? <time title={f.lastRun.relative}>{f.lastRun.label}</time> : <span className="flxfa-muted">—</span>}</td>
                    <td className="num">{num(f.runs)}</td>
                    <td className="flxfa-row-actions">
                      <a
                        className="flxfa-icon-btn"
                        href={'#/automations?flow=' + f.id}
                        title={t('Edit')}
                        onClick={(e) => {
                          e.preventDefault();
                          go('automations', { flow: f.id });
                        }}
                      >
                        <Ico name="edit" />
                        <span className="screen-reader-text">{t('Edit')}</span>
                      </a>
                      <button
                        type="button"
                        className="flxfa-icon-btn flxfa-dup"
                        title={t('Duplicate')}
                        disabled={dup.isPending}
                        onClick={() => void run(async () => (await dup.mutateAsync({ input: { id: f.id } })).flxFormsAutomationDuplicate?.errors, t('Automation copied.'))}
                      >
                        <Ico name="copy" />
                        <span className="screen-reader-text">{t('Duplicate')}</span>
                      </button>
                      <button type="button" className="flxfa-icon-btn flxfa-icon-btn--danger flxfa-del" title={t('Delete')} onClick={() => void remove(f)}>
                        <Ico name="trash" />
                        <span className="screen-reader-text">{t('Delete')}</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="flxfa-empty">
            <Ico name="bolt" />
            <p>{t('No automations yet.')}</p>
          </div>
        )}
      </div>

      <div className="flxfa-panel flxfa-starter">
        <div className="flxfa-starter__icon">
          <Ico name="tree" />
        </div>
        <div className="flxfa-starter__body">
          <h2>{t('Ready-made: sapling donation certificate')}</h2>
          <p>{t('After a verified payment on the donation form: create a “Fidan Bağış Sertifikası” (sapling count = amount ÷ unit price), send it by WhatsApp and e-mail, and show it on the thank-you screen.')}</p>
          <div className="flxfa-starter__actions">
            <button type="button" className="flxfa-btn" disabled={def.isPending} onClick={() => void createDefault()}>
              <Ico name="bolt" />
              {t('Create default flow')}
            </button>
            {d.donationFormId ? (
              <span className="flxfa-muted">{t('Donation form: {name}', { name: d.donationFormTitle })}</span>
            ) : (
              <button type="button" className="flxfa-btn flxfa-btn--ghost" disabled={donation.isPending} onClick={() => void createForm()}>
                <Ico name="form" />
                {t('Create the “Sapling Donation” form')}
              </button>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

function RunLog({ max }: { max: number }) {
  const { t, toast, go } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxFormsAutomationRuns, {});
  const clear = useFlxMutation(FlxFormsAutomationRunsClear);
  const rows = q.data?.flxFormsAutomationRuns;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!rows) return <Loading />;
  const status: Record<string, string> = { ok: t('Done'), partial: t('Partly done'), error: t('Error'), skipped: t('Skipped'), running: t('Running') };
  const doClear = async () => {
    try {
      const r = (await clear.mutateAsync({ input: {} })).flxFormsAutomationRunsClear;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomationRuns) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomations) });
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  if (!rows.length)
    return (
      <div className="flxfa-panel" id="flxfa-runs">
        <div className="flxfa-empty">
          <Ico name="bolt" />
          <p>{t('No runs yet. Runs appear here after a matching submission.')}</p>
        </div>
      </div>
    );
  return (
    <>
      <div className="flxfa-panel" id="flxfa-runs">
        <div className="flxfa-runs-head">
          <p className="flxfa-muted">{t('The latest {n} runs are kept.', { n: max })}</p>
          <button type="button" className="flxfa-btn" id="flxfa-clear-runs" onClick={() => void confirmDialog(t('Clear the run log?'), true).then((ok) => ok && void doClear())}>
            {t('Clear log')}
          </button>
        </div>
        <div className="flxfa-table-wrap">
          <table className="flxfa-table flxfa-runs">
            <thead>
              <tr>
                <th scope="col">{t('Time')}</th>
                <th scope="col">{t('Automation')}</th>
                <th scope="col">{t('Form')}</th>
                <th scope="col">{t('Tracking code')}</th>
                <th scope="col">{t('Status')}</th>
                <th scope="col">{t('Steps')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td>{r.time ? <time title={r.time.relative}>{r.time.label}</time> : '—'}</td>
                  <td>
                    <a
                      href={'#/automations?flow=' + r.flowId}
                      onClick={(e) => {
                        e.preventDefault();
                        if (r.flowId) go('automations', { flow: r.flowId });
                      }}
                    >
                      {r.flowTitle}
                    </a>
                  </td>
                  <td>{r.formTitle}</td>
                  <td>
                    <code>{r.track}</code>
                  </td>
                  <td>
                    <span className={'flxfa-status flxfa-status--' + r.status}>{status[r.status] ?? r.status}</span>
                  </td>
                  <td>
                    <ol className="flxfa-steplog">
                      {r.steps.map((st, i) => (
                        <li key={i}>
                          <span className={'flxfa-dot flxfa-dot--' + st.status} aria-hidden="true"></span>
                          <strong>{actionLabel(st.type, t)}</strong> <span className="flxfa-status-text">{status[st.status] ?? st.status}</span>
                          {st.message ? (
                            <div className="flxfa-steplog__msg">
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
      </div>
    </>
  );
}
