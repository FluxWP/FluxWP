// FluxWP Panel connection form (server URL, site key, post-update check, automatic updates, usage data), in the
// classic WordPress form-table layout. Settings shows it always; Packages shows it when the server cannot be
// reached and no FluxWP package is listed.
import { useEffect, useState, type FormEvent } from 'react';
import { useFlxMutation } from '@fluxwp/graphql/react';
import { FlxSetupSettingsSave } from '@fluxwp/graphql/operations';
import type { FlxSetupSettingsScreenQuery } from '@fluxwp/graphql/types';
import { errorText, useSetup, type Notice } from './shared';

type Settings = FlxSetupSettingsScreenQuery['flxSetupSettings'];

export function ConnectionForm({ s, onNotice }: { s: Settings; onNotice: (n: Notice) => void }) {
  const { t, refresh } = useSetup();
  const [form, setForm] = useState({ server: s.server, key: '', clearKey: false, auto: s.auto, health: s.health, usage: s.usage });
  useEffect(() => setForm({ server: s.server, key: '', clearKey: false, auto: s.auto, health: s.health, usage: s.usage }), [s]);
  const fail = (e: unknown) => onNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const save = useFlxMutation(FlxSetupSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxSetupSettingsSave;
      if (!p) return fail(null);
      if (p.errors.length) return onNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      onNotice({ kind: 'success', text: t('Settings saved and updates checked.') });
      void refresh();
    },
    onError: fail,
  });
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
  const check = (key: 'auto' | 'health' | 'usage', label: string) => (
    <label>
      <input type="checkbox" checked={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.checked })} /> {label}
    </label>
  );
  return (
    <div className="flx-raw flxk-classic">
      <h2>{t('Connection')}</h2>
      <form onSubmit={submit}>
        <table className="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row">
                <label htmlFor="flxu-server">{t('Server URL')}</label>
              </th>
              <td>
                {s.serverFromConfig ? (
                  <>
                    <code>{s.effectiveServer}</code>
                    <p className="description">{t('Set by the FLX_UPDATE_SERVER constant in wp-config.php.')}</p>
                  </>
                ) : (
                  <>
                    <input type="url" id="flxu-server" className="regular-text" value={form.server} placeholder={s.defaultServer} onChange={(e) => setForm({ ...form, server: e.target.value })} />
                    <p className="description">{t('Leave empty to use the default server. https only.')}</p>
                  </>
                )}
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label htmlFor="flxu-key">{t('Site key')}</label>
              </th>
              <td>
                {s.keyFromConfig ? (
                  <>
                    <code>••••••••</code>
                    <p className="description">{t('Set by the FLX_UPDATE_KEY constant in wp-config.php.')}</p>
                  </>
                ) : (
                  <>
                    <input
                      type="password"
                      id="flxu-key"
                      className="regular-text"
                      autoComplete="off"
                      value={form.key}
                      placeholder={s.hasKey ? t('Saved (type a new key to change it)') : 'flxu_…'}
                      onChange={(e) => setForm({ ...form, key: e.target.value })}
                    />
                    {s.hasKey && (
                      <label style={{ marginLeft: 8 }}>
                        <input type="checkbox" checked={form.clearKey} onChange={(e) => setForm({ ...form, clearKey: e.target.checked })} /> {t('Remove key')}
                      </label>
                    )}
                    <p className="description">{t('Optional. Public packages need no key; for private packages, get one from the update server panel (/admin) with "Add site". Give each site its own key.')}</p>
                  </>
                )}
              </td>
            </tr>
            <tr>
              <th scope="row">{t('Post-update check')}</th>
              <td>
                {check('health', t('Check that the site still loads after updating; roll the package back if it fails'))}
                <p className="description">{t('Only HTTP 500 (critical error) triggers a rollback; network or firewall errors do not.')}</p>
              </td>
            </tr>
            <tr>
              <th scope="row">{t('Automatic updates')}</th>
              <td>
                {check('auto', t('Install new versions of FluxWP packages automatically'))}
                <p className="description">{t('When this is off, WordPress\'s per-plugin "Enable auto-updates" setting applies.')}</p>
              </td>
            </tr>
            {s.usageAvailable && (
              <tr>
                <th scope="row">{t('Usage data')}</th>
                <td>
                  {check('usage', t('Help us improve FluxWP (send usage data)'))}
                  <p className="description">
                    {t(
                      'Update checks always send the site address; the server also looks up the country from the IP and does not store the IP (mandatory record). When on, update checks also send the WordPress and PHP version, time zone and language, and FluxWP plugin versions and errors are sent anonymously. When off, only what updates need is sent and updates work the same. You can withdraw consent here at any time.',
                    )}
                  </p>
                  <p className="description">
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
                </td>
              </tr>
            )}
          </tbody>
        </table>
        <p className="submit">
          <button type="submit" className="button button-primary" disabled={save.isPending}>
            {t('Save and check')}
          </button>
        </p>
      </form>
    </div>
  );
}
