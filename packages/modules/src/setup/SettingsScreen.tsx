// FluxWP Panel › Settings: connection to the update server, automatic updates, post-update check,
// usage data consent and the other update sources. Same screen in wp-admin and the FluxWP app.
import { useEffect, useState } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSetupCheck, FlxSetupCloudCard, FlxSetupSettingsScreen, FlxSetupSourceAdd, FlxSetupSourceChange, FlxSetupTelemetrySave } from '@fluxwp/graphql/operations';
import type { FlxSetupSettingsScreenQuery } from '@fluxwp/graphql/types';
import { Loading, Modal, Screen } from '@fluxwp/ui';
import { errorText, NoticeBar, SetupProvider, useSetup, type Notice } from './shared';
import { ConnectionForm } from './ConnectionForm';
import { useHost } from '../host';

type Data = FlxSetupSettingsScreenQuery;
type Source = Data['flxSetupSources'][number];

export function SettingsScreen() {
  return (
    <SetupProvider>
      <Settings />
    </SetupProvider>
  );
}

function Settings() {
  const host = useHost();
  const { t, tn, refresh, scope } = useSetup();
  const q = useFlxQuery(FlxSetupSettingsScreen, {});
  const [notice, setNotice] = useState<Notice | null>(null);
  const [add, setAdd] = useState({ url: '', name: '', key: '' });
  const [confirm, setConfirm] = useState<Source | null>(null);
  const s = q.data?.flxSetupSettings;

  const fail = (e: unknown) => setNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const reload = () => void refresh();

  const addSource = useFlxMutation(FlxSetupSourceAdd, {
    onSuccess: (r) => {
      const p = r.flxSetupSourceAdd;
      if (!p) return fail(null);
      if (p.errors.length) return setNotice({ kind: 'error', text: t('Source not added: %s', p.errors[0]?.message ?? '') });
      setAdd({ url: '', name: '', key: '' });
      setNotice({ kind: 'success', text: t('Source added and its packages loaded.') });
      reload();
    },
    onError: fail,
  });
  const test = useFlxMutation(FlxSetupCheck, {
    onSuccess: (r) => {
      const p = r.flxSetupCheck;
      setNotice({ kind: p?.ok ? 'success' : 'warning', text: p?.summary || t('Updates checked.') });
      reload();
    },
    onError: fail,
  });
  const change = useFlxMutation(FlxSetupSourceChange, {
    onSuccess: (r, v) => {
      if (r.flxSetupSourceChange?.errors.length) return fail(new Error(r.flxSetupSourceChange.errors[0]?.message));
      if (v.input.op === 'REMOVE') setNotice({ kind: 'success', text: t('Source removed.') });
      reload();
    },
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data || !s) {
    return (
      <div className="flxk-app">
        <div className="flxk-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>
      </div>
    );
  }
  const { flxSetupConnection: c, flxSetupSources: sources } = q.data;
  void scope;

  return (
    <Screen className="flxk-app" width="md" icon={host.appIcon} title={t('Update settings')} subtitle={t('FluxWP themes and plugins are updated from your own update server instead of wordpress.org.')}>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />

      <div className={'flxk-status is-' + c.state.toLowerCase()}>
        <span className="flxk-dot" aria-hidden="true" />
        <div className="flxk-status__body">
          <strong>{c.title}</strong>
          <span>{c.message}</span>
          <small className="flxk-muted">
            {c.checkedLabel}
            {c.packageCount > 0 && ' · ' + t('%1$d packages, %2$d updates', c.packageCount, c.updateCount)}
          </small>
        </div>
        <button type="button" className="flxk-btn" disabled={test.isPending} onClick={() => test.mutate({})}>
          {test.isPending ? t('Checking…') : t('Test connection')}
        </button>
      </div>

      <CloudCard cloud={q.data.flxSetupCloud} t={t} onNotice={setNotice} />

      <ConnectionForm s={s} onNotice={setNotice} />

      <h2 id="flxu-sources">{t('Other sources')}</h2>
      <p className="flxk-desc">
        {t(
          'Packages are collected from these sources as well as the main server. If a package is in more than one source, it updates from the source it was installed from; if it is not installed, the source higher in the list wins.',
        )}
      </p>
      <div className="flxk-card flxk-table-wrap">
        <table className="flxk-table">
          <thead>
            <tr>
              <th>{t('Name')}</th>
              <th>{t('Address')}</th>
              <th>{t('Type')}</th>
              <th>{t('Status')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {sources.map((x) => (
              <tr key={x.id} className={x.enabled ? '' : 'is-off'}>
                <td>
                  <strong>{x.name}</strong>
                  <br />
                  <small className="flxk-muted">{x.main ? t('Main server') : x.type === 'SERVER' && x.hasKey ? t('Key saved') : ''}</small>
                </td>
                <td>
                  <code>{x.url}</code>
                </td>
                <td>{x.type === 'GITHUB' ? t('GitHub (public)') : t('Server')}</td>
                <td>
                  <span className={'flxk-src-st is-' + x.state.toLowerCase()}>{x.state === 'OFF' ? t('Off') : x.state === 'UNCHECKED' ? t('Not checked yet') : x.message}</span>
                </td>
                <td className="flxk-src-act">
                  {x.main ? (
                    <small className="flxk-muted">{t('Set under Connection above')}</small>
                  ) : (
                    <>
                      {x.canMoveUp && (
                        <button type="button" className="flxk-linkbtn" onClick={() => change.mutate({ input: { op: 'UP', id: x.id } })}>
                          {t('Move up')}
                        </button>
                      )}
                      <button type="button" className="flxk-linkbtn" onClick={() => change.mutate({ input: { op: 'TOGGLE', id: x.id } })}>
                        {x.enabled ? t('Turn off') : t('Turn on')}
                      </button>
                      <button type="button" className="flxk-linkbtn is-danger" onClick={() => setConfirm(x)}>
                        {t('Delete')}
                      </button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h3>{t('Add source')}</h3>
      <form
        className="flxk-card flxk-form"
        onSubmit={(e) => {
          e.preventDefault();
          addSource.mutate({ input: { url: add.url, name: add.name || null, key: add.key || null } });
        }}
      >
        <div className="flxk-field">
          <label htmlFor="flxk-src-url">{t('Address')}</label>
          <div>
            <input id="flxk-src-url" type="url" required value={add.url} placeholder="https://github.com/owner/repo" onChange={(e) => setAdd({ ...add, url: e.target.value })} />
            <p className="flxk-desc">{t('A public GitHub repository (with <slug>.zip on its releases) or a FluxWP update-server address. https only.')}</p>
          </div>
        </div>
        <div className="flxk-field">
          <label htmlFor="flxk-src-name">{t('Name')}</label>
          <input id="flxk-src-name" type="text" value={add.name} placeholder={t('Taken from the address if empty')} onChange={(e) => setAdd({ ...add, name: e.target.value })} />
        </div>
        <div className="flxk-field">
          <label htmlFor="flxk-src-key">{t('Site key')}</label>
          <div>
            <input id="flxk-src-key" type="password" autoComplete="off" value={add.key} placeholder={t('Optional')} onChange={(e) => setAdd({ ...add, key: e.target.value })} />
            <p className="flxk-desc">{t("Only for servers that require a key. The key is sent only to that server's address.")}</p>
          </div>
        </div>
        <p className="flxk-strip is-warn">{t('Code from this source runs on your site; only add sources you trust.')}</p>
        <div>
          <button type="submit" className="flxk-btn" disabled={addSource.isPending || !add.url}>
            {t('Test and add')}
          </button>
        </div>
      </form>

      <GraphqlCard status={q.data.flxSetupGraphqlStatus} t={t} />
      <TelemetryCard telemetry={q.data.flxSetupTelemetry} t={t} onNotice={setNotice} />
      <span className="flxk-sr">{tn('%s package', '%s packages', c.packageCount, c.packageCount)}</span>

      {confirm && (
        <Modal title={t('Delete')} onClose={() => setConfirm(null)}>
          <p className="flxk-confirm">{t('Remove this source? Packages installed from it stay on the site; they just stop receiving updates.')}</p>
          <div className="flxk-modal-actions">
            <button type="button" className="flxk-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxk-btn is-danger"
              onClick={() => {
                change.mutate({ input: { op: 'REMOVE', id: confirm.id } });
                setConfirm(null);
              }}
            >
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </Screen>
  );
}

function GraphqlCard({ status: g, t }: { status: Data['flxSetupGraphqlStatus']; t: ReturnType<typeof useSetup>['t'] }) {
  return (
    <>
      <h2>{t('Flux GraphQL')}</h2>
      <div className={'flxk-status ' + (g.state === 'OK' && g.phpOk ? 'is-connected' : 'is-error')}>
        <span className="flxk-dot" aria-hidden="true" />
        <div className="flxk-status__body">
          <strong>{g.state === 'OK' ? t('Flux GraphQL %s is running', g.version) : t('Flux GraphQL is not running')}</strong>
          <span>{t('PHP %s. FluxWP GraphQL packages run on it.', g.phpVersion)}</span>
          {!g.phpOk && <span>{t('%1$s needs PHP %2$s or newer; this site runs PHP %3$s. Raise the PHP version in the hosting panel.', 'Flux GraphQL', g.phpMin, g.phpVersion)}</span>}
        </div>
        {g.actionUrl && (
          <a className="flxk-btn" href={g.actionUrl}>
            {g.actionLabel}
          </a>
        )}
      </div>
    </>
  );
}

/** Result codes the sign-in flow adds to the address when it comes back (?flxk_cloud=…). */
function cloudFlash(code: string, t: ReturnType<typeof useSetup>['t']): [Notice['kind'], string] | null {
  switch (code) {
    case 'ok':
      return ['success', t("Connected to your FluxWP account. Your plan's packages are on the Packages screen.")];
    case 'denied':
      return ['warning', t('Connecting was cancelled on FluxWP.')];
    case 'off':
      return ['success', t('Disconnected. Installed plugins keep working; updates of paid packages stop.')];
    case 'refreshed':
      return ['success', t('Plan information refreshed.')];
    case 'fail':
      return ['error', t('Connecting failed. Try again.')];
    default:
      return null;
  }
}

/** Choices for "show the hidden card again after": days, 0 = never. */
const CLOUD_CARD_DAYS = [1, 3, 7, 14, 30, 0];

/**
 * FluxWP account: "Sign in with FluxWP" (fluxwp.com gives this site its update key and plan packages).
 * Not connected: × hides the card for this user; it comes back after the chosen number of days (default 7).
 */
function CloudCard({ cloud: c, t, onNotice }: { cloud: Data['flxSetupCloud']; t: ReturnType<typeof useSetup>['t']; onNotice: (n: Notice) => void }) {
  const { refresh } = useSetup();
  const code = typeof window === 'undefined' ? '' : new URLSearchParams(window.location.search).get('flxk_cloud') || '';
  const flash = code === 'fail' && c.flash ? (['error', c.flash] as const) : cloudFlash(code, t);
  const card = useFlxMutation(FlxSetupCloudCard, {
    onSuccess: (r) => {
      const p = r.flxSetupCloudCard;
      if (p?.errors.length) return onNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      void refresh();
    },
    onError: (e) => onNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) }),
  });
  if (!c.postUrl) return null;
  const daysLabel = (d: number) => (d === 0 ? t('Never') : d === 1 ? t('After 1 day') : t('After %d days', d));
  if (c.hidden && !flash) {
    return (
      <div className="flxk-cloud-hidden">
        <span className="flxk-muted">{c.hideDays === 0 ? t('The FluxWP account card is hidden.') : t('The FluxWP account card is hidden for %d days after you close it.', c.hideDays)}</span>
        <button type="button" className="flxk-linkbtn" disabled={card.isPending} onClick={() => card.mutate({ input: { hide: false } })}>
          {t('Show now')}
        </button>
      </div>
    );
  }
  return (
    <>
      {flash && (
        <div className={'flxk-notice is-' + flash[0]} role={flash[0] === 'error' ? 'alert' : 'status'}>
          <span>{flash[1]}</span>
        </div>
      )}
      <div className={'flxk-cloud' + (c.connected ? ' is-connected' : ' is-dismissible')}>
        <span className="flxk-cloud__logo" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M7 18h10a4 4 0 0 0 .6-7.96A6 6 0 0 0 6.2 9.1 4.5 4.5 0 0 0 7 18z" />
            <path d="m9.5 13.5 2 2 3.5-4" />
          </svg>
        </span>
        <div className="flxk-cloud__body">
          <strong>{c.title}</strong>
          {c.text && <span>{c.text}</span>}
          {c.error ? <small className="flxk-cloud__err">{c.error}</small> : c.note ? <small className="flxk-muted">{c.note}</small> : null}
          {!c.connected && (
            <label className="flxk-cloud__again">
              <span className="flxk-muted">{t('If hidden, show again')}</span>
              <select value={c.hideDays} disabled={card.isPending} onChange={(e) => card.mutate({ input: { days: Number(e.target.value) } })}>
                {CLOUD_CARD_DAYS.map((d) => (
                  <option key={d} value={d}>
                    {daysLabel(d)}
                  </option>
                ))}
              </select>
            </label>
          )}
        </div>
        <div className="flxk-cloud__actions">
          {c.manageUrl && (
            <a className="flxk-btn" href={c.manageUrl} target="_blank" rel="noopener noreferrer">
              {t('Manage account')}
            </a>
          )}
          {c.actions.map((a) => (
            <form key={a.action} method="post" action={c.postUrl}>
              <input type="hidden" name="action" value={a.action} />
              <input type="hidden" name="_wpnonce" value={a.nonce} />
              <button type="submit" className={a.kind === 'primary' ? 'flxk-btn is-primary' : a.kind === 'danger' ? 'flxk-linkbtn is-danger' : 'flxk-btn'}>
                {a.label}
              </button>
            </form>
          ))}
        </div>
        {!c.connected && (
          <button type="button" className="flxk-cloud__close" aria-label={t('Hide')} title={t('Hide')} disabled={card.isPending} onClick={() => card.mutate({ input: { hide: true } })}>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              <path d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        )}
      </div>
    </>
  );
}

/** Anonymous usage data: state, hub address (own Flux Analytics hub), send now and the data that is sent. */
function TelemetryCard({ telemetry: m, t, onNotice }: { telemetry: Data['flxSetupTelemetry']; t: ReturnType<typeof useSetup>['t']; onNotice: (n: Notice) => void }) {
  const { refresh } = useSetup();
  const [url, setUrl] = useState(m.url);
  const [opts, setOpts] = useState<Record<string, boolean>>({});
  useEffect(() => setUrl(m.url), [m.url]);
  useEffect(() => setOpts(Object.fromEntries(m.options.map((o) => [o.id, o.on]))), [m.options]);
  const options = Object.entries(opts).map(([id, on]) => ({ id, on }));
  const save = useFlxMutation(FlxSetupTelemetrySave, {
    onSuccess: (r) => {
      const p = r.flxSetupTelemetrySave;
      if (!p || p.errors.length) return onNotice({ kind: 'error', text: p?.errors[0]?.message || t('The action could not be completed.') });
      onNotice(
        p.sent === null || p.sent === undefined
          ? { kind: 'success', text: t('Saved.') }
          : p.sent
            ? { kind: 'success', text: t('Sent.') }
            : { kind: 'error', text: t('Could not send. Check the URL and that the Flux Analytics telemetry hub is on at the target site.') },
      );
      void refresh();
    },
    onError: (e) => onNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) }),
  });
  return (
    <>
      <h2 id="flxk-telemetry">{t('Anonymous usage data')}</h2>
      <div className="flxk-card">
        <p>
          <span className={'flxk-badge' + (m.enabled ? ' is-ok' : '')}>{m.enabled ? t('On') : t('Off')}</span> {m.status}
        </p>
        <p className="flxk-desc">
          {t(
            'Sent: a random install ID, WordPress and PHP version, site language, country (from the time zone or store country), active FluxWP plugins and theme, PHP errors from FluxWP (with paths, domain, emails and IPs removed), setup wizard progress and choices. Site address, IP, users and content are never sent.',
          )}
        </p>
        <form
          className="flxk-form"
          onSubmit={(e) => {
            e.preventDefault();
            save.mutate({ input: { url, send: false, options } });
          }}
        >
          <div className="flxk-field">
            <label className="flxk-label" htmlFor="flxk-tm-url">
              {t('Telemetry URL')}
            </label>
            <div>
              <input id="flxk-tm-url" type="url" className="flxk-code" value={url} placeholder={m.defaultUrl} onChange={(e) => setUrl(e.target.value)} />
              <p className="flxk-desc">{t('Leave empty to send to FluxWP. To run your own hub, turn on Flux Analytics › Settings › Telemetry hub on one site and paste that address here. https only.')}</p>
            </div>
          </div>
          {m.options.map((o) => (
            <div key={o.id} className="flxk-field">
              <span className="flxk-label">{o.title}</span>
              <div>
                <label className="flxk-check-label">
                  <input type="checkbox" checked={!!opts[o.id]} onChange={(e) => setOpts((x) => ({ ...x, [o.id]: e.target.checked }))} /> {o.label}
                </label>
                <p className="flxk-desc">{o.text}</p>
              </div>
            </div>
          ))}
          <div className="flxk-btnrow">
            <button type="submit" className="flxk-btn is-primary" disabled={save.isPending}>
              {t('Save')}
            </button>
            {m.enabled && (
              <button type="button" className="flxk-btn" disabled={save.isPending} onClick={() => save.mutate({ input: { url, send: true, options } })}>
                {t('Send now')}
              </button>
            )}
          </div>
        </form>
        <details className="flxk-details">
          <summary>{t('Show the data that will be sent')}</summary>
          <pre>{m.payload}</pre>
        </details>
      </div>
    </>
  );
}
