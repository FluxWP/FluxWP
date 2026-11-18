// FluxWP Panel › Settings: connection to the update server, automatic updates, post-update check,
// usage data consent and the other update sources. Same screen in wp-admin and the FluxWP app.
import { useEffect, useState, type FormEvent } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSetupSettingsSave, FlxSetupSettingsScreen, FlxSetupSourceAdd, FlxSetupSourceChange } from '@fluxwp/graphql/operations';
import type { FlxSetupSettingsScreenQuery } from '@fluxwp/graphql/types';
import { Loading, Modal, Screen } from '@fluxwp/ui';
import { errorText, NoticeBar, SetupProvider, useSetup, type Notice } from './shared';
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
  const [form, setForm] = useState({ server: '', key: '', clearKey: false, auto: false, health: false, usage: false });
  const [add, setAdd] = useState({ url: '', name: '', key: '' });
  const [confirm, setConfirm] = useState<Source | null>(null);
  const s = q.data?.flxSetupSettings;

  useEffect(() => {
    if (s) setForm({ server: s.server, key: '', clearKey: false, auto: s.auto, health: s.health, usage: s.usage });
  }, [s]);

  const fail = (e: unknown) => setNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const reload = () => void refresh();

  const save = useFlxMutation(FlxSetupSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxSetupSettingsSave;
      if (!p) return fail(null);
      if (p.errors.length) return setNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      setNotice({ kind: 'success', text: t('Settings saved and updates checked.') });
      reload();
    },
    onError: fail,
  });
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
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({
      input: {
        ...(s.serverFromConfig ? {} : { server: form.server }),
        ...(s.keyFromConfig ? {} : { key: form.key, clearKey: form.clearKey }),
        auto: form.auto,
        health: form.health,
        ...(s.usageAvailable ? { usage: form.usage } : {}),
      },
    });
  };
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
      </div>

      <h2>{t('Connection')}</h2>
      <form className="flxk-card flxk-form" onSubmit={submit}>
        <div className="flxk-field">
          <label htmlFor="flxk-server">{t('Server URL')}</label>
          <div>
            {s.serverFromConfig ? (
              <>
                <code>{s.effectiveServer}</code>
                <p className="flxk-desc">{t('Set by the FLX_UPDATE_SERVER constant in wp-config.php.')}</p>
              </>
            ) : (
              <>
                <input id="flxk-server" type="url" value={form.server} placeholder={s.defaultServer} onChange={(e) => setForm({ ...form, server: e.target.value })} />
                <p className="flxk-desc">{t('Leave empty to use the default server. https only.')}</p>
              </>
            )}
          </div>
        </div>
        <div className="flxk-field">
          <label htmlFor="flxk-key">{t('Site key')}</label>
          <div>
            {s.keyFromConfig ? (
              <>
                <code>••••••••</code>
                <p className="flxk-desc">{t('Set by the FLX_UPDATE_KEY constant in wp-config.php.')}</p>
              </>
            ) : (
              <>
                <input
                  id="flxk-key"
                  type="password"
                  autoComplete="off"
                  value={form.key}
                  placeholder={s.hasKey ? t('Saved (type a new key to change it)') : 'flxu_…'}
                  onChange={(e) => setForm({ ...form, key: e.target.value })}
                />
                {s.hasKey && (
                  <label className="flxk-check-label">
                    <input type="checkbox" checked={form.clearKey} onChange={(e) => setForm({ ...form, clearKey: e.target.checked })} /> {t('Remove key')}
                  </label>
                )}
                <p className="flxk-desc">{t('Optional. Public packages need no key; for private packages, get one from the update server panel (/admin) with "Add site". Give each site its own key.')}</p>
              </>
            )}
          </div>
        </div>
        <div className="flxk-field">
          <span className="flxk-label">{t('Post-update check')}</span>
          <div>
            <label className="flxk-check-label">
              <input type="checkbox" checked={form.health} onChange={(e) => setForm({ ...form, health: e.target.checked })} /> {t('Check that the site still loads after updating; roll the package back if it fails')}
            </label>
            <p className="flxk-desc">{t('Only HTTP 500 (critical error) triggers a rollback; network or firewall errors do not.')}</p>
          </div>
        </div>
        <div className="flxk-field">
          <span className="flxk-label">{t('Automatic updates')}</span>
          <div>
            <label className="flxk-check-label">
              <input type="checkbox" checked={form.auto} onChange={(e) => setForm({ ...form, auto: e.target.checked })} /> {t('Install new versions of FluxWP packages automatically')}
            </label>
            <p className="flxk-desc">{t('When this is off, WordPress\'s per-plugin "Enable auto-updates" setting applies.')}</p>
          </div>
        </div>
        {s.usageAvailable && (
          <div className="flxk-field">
            <span className="flxk-label">{t('Usage data')}</span>
            <div>
              <label className="flxk-check-label">
                <input type="checkbox" checked={form.usage} onChange={(e) => setForm({ ...form, usage: e.target.checked })} /> {t('Help us improve FluxWP (send usage data)')}
              </label>
              <p className="flxk-desc">
                {t('Update checks always send the site address; the server also looks up the country from the IP and does not store the IP (mandatory record). When on, update checks also send the WordPress and PHP version, time zone and language, and FluxWP plugin versions and errors are sent anonymously. When off, only what updates need is sent and updates work the same. You can withdraw consent here at any time.')}
              </p>
              <p className="flxk-desc">
                <a href={s.legal.kvkk} target="_blank" rel="noopener noreferrer">
                  {t('KVKK Privacy Notice')}
                </a>{' '}
                ·{' '}
                <a href={s.legal.privacy} target="_blank" rel="noopener noreferrer">
                  {t('Privacy Policy')}
                </a>{' '}
                ·{' '}
                <a href={s.legal.terms} target="_blank" rel="noopener noreferrer">
                  {t('Terms of Use')}
                </a>
              </p>
            </div>
          </div>
        )}
        <div>
          <button type="submit" className="flxk-btn is-primary" disabled={save.isPending}>
            {t('Save and check')}
          </button>
        </div>
      </form>

      <h2 id="flxu-sources">{t('Other sources')}</h2>
      <p className="flxk-desc">{t('Packages are collected from these sources as well as the main server. If a package is in more than one source, it updates from the source it was installed from; if it is not installed, the source higher in the list wins.')}</p>
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
