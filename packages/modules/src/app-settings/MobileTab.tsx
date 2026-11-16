// Application › Applications: which apps may connect, store links, notifications (Expo push), web view, branded apps.
import { useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAdminMobile, FlxAppAdminMobileSave, FlxAppAdminPushTest } from '@fluxwp/graphql/operations';
import type { FlxAppAdminMobileQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Check, Field, payloadError, SaveBar, useScreen } from './common';

type Mobile = FlxAppAdminMobileQuery['flxAppAdminMobile'];
interface AppRow {
  id: string;
  name: string;
  platform: string;
  uris: string;
}
interface Draft {
  genericApp: boolean;
  storeIos: string;
  storeAndroid: string;
  pushEnabled: boolean;
  pushHide: boolean;
  expoToken: string;
  clearExpoToken: boolean;
  embedFront: boolean;
  devRedirects: boolean;
  apps: AppRow[];
}

const blank = (): AppRow => ({ id: '', name: '', platform: 'mobile', uris: '' });
const draftOf = (m: Mobile): Draft => ({
  genericApp: m.genericApp,
  storeIos: m.storeIos,
  storeAndroid: m.storeAndroid,
  pushEnabled: m.pushEnabled,
  pushHide: m.pushHide,
  expoToken: '',
  clearExpoToken: false,
  embedFront: m.embedFront,
  devRedirects: m.devRedirects,
  apps: [...m.apps.map((a) => ({ id: a.id, name: a.name, platform: a.platform, uris: a.redirectUris.join('\n') })), blank()],
});

export function MobileTab() {
  const { t, told, fail } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAppAdminMobile, {});
  const [draft, setDraft] = useState<Draft | null>(null);
  const [dirty, setDirty] = useState(false);
  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data.flxAppAdminMobile));
  }, [q.data, dirty]);

  const save = useFlxMutation(FlxAppAdminMobileSave, {
    onSuccess: (r) => {
      const p = r.flxAppAdminMobileSave;
      const err = payloadError(p?.errors);
      if (!p?.mobile || err) return fail(err ? new Error(err) : null);
      qc.setQueryData(flxQueryKey(scope, FlxAppAdminMobile, {}), { flxAppAdminMobile: p.mobile });
      setDirty(false);
      setDraft(draftOf(p.mobile));
      told(t('Saved.'));
    },
    onError: fail,
  });
  const test = useFlxMutation(FlxAppAdminPushTest, {
    onSuccess: (r) => {
      const p = r.flxAppAdminPushTest;
      const err = payloadError(p?.errors);
      if (!p || err) return fail(err ? new Error(err) : null);
      told(t('Test notification: %1$d sent, %2$d failed, %3$d skipped.', p.sent ?? 0, p.failed ?? 0, p.skipped ?? 0));
    },
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const m = q.data.flxAppAdminMobile;
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft((d) => d && { ...d, [k]: v });
    setDirty(true);
  };
  const setApp = (i: number, patch: Partial<AppRow>) => {
    setDraft((d) => d && { ...d, apps: d.apps.map((a, j) => (j === i ? { ...a, ...patch } : a)) });
    setDirty(true);
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({
      input: {
        genericApp: draft.genericApp,
        storeIos: draft.storeIos,
        storeAndroid: draft.storeAndroid,
        pushEnabled: draft.pushEnabled,
        pushHide: draft.pushHide,
        expoToken: draft.expoToken || undefined,
        clearExpoToken: draft.clearExpoToken,
        embedFront: draft.embedFront,
        devRedirects: draft.devRedirects,
        apps: draft.apps.filter((a) => a.id.trim()).map((a) => ({ id: a.id.trim(), name: a.name.trim(), platform: a.platform, redirectUris: a.uris.split(/[\r\n]+/).map((u) => u.trim()).filter(Boolean) })),
      },
    });
  };

  return (
    <form onSubmit={submit}>
      <p className="flxs-lead">{t('Which apps may connect to this site, store links and notifications.')}</p>
      <div className="flxs-card flxs-form">
        <Field label={t('Main FluxWP app')} desc={t('When off, only the branded apps below can connect; open sessions of the main app end at their next refresh.')}>
          <Check checked={draft.genericApp} onChange={(v) => set('genericApp', v)}>
            {t('Allow the FluxWP desktop and mobile app (flx-masaustu, flx-mobil)')}
          </Check>
        </Field>
        <Field label={t('Store links')}>
          <p>
            <input type="url" className="flxs-wide" placeholder="https://apps.apple.com/…" aria-label="App Store" value={draft.storeIos} onChange={(e) => set('storeIos', e.currentTarget.value)} /> App Store
          </p>
          <p>
            <input type="url" className="flxs-wide" placeholder="https://play.google.com/…" aria-label="Google Play" value={draft.storeAndroid} onChange={(e) => set('storeAndroid', e.currentTarget.value)} /> Google Play
          </p>
        </Field>
        <Field label={t('Notifications')} desc={t('Only needed when enhanced push security is on in the Expo account. The desktop app does not receive push; it shows notifications through sync while open.')}>
          <Check checked={draft.pushEnabled} onChange={(v) => set('pushEnabled', v)}>
            {t('Send push notifications to mobile devices (Expo)')}
          </Check>
          <Check checked={draft.pushHide} onChange={(v) => set('pushHide', v)}>
            {t('Hide content on the lock screen (only the notification type shows)')}
          </Check>
          <p>
            <input
              type="password"
              className="flxs-wide"
              autoComplete="new-password"
              aria-label={t('Expo access token (optional)')}
              placeholder={m.hasExpoToken ? t('Saved (type to replace)') : t('Expo access token (optional)')}
              value={draft.expoToken}
              onChange={(e) => set('expoToken', e.currentTarget.value)}
            />{' '}
            <Check checked={draft.clearExpoToken} onChange={(v) => set('clearExpoToken', v)}>
              {t('Delete')}
            </Check>
          </p>
          <p>
            {t('Devices: %1$d · notifications on: %2$d', m.deviceCount, m.pushCount)}
            {m.lastPush && (
              <>
                {' · ' + t('Last send %1$s: %2$d sent, %3$d failed', m.lastPush.ago, m.lastPush.sent, m.lastPush.failed)}
                {m.lastPush.error && <span className="flxs-bad"> {m.lastPush.error}</span>}
              </>
            )}
          </p>
        </Field>
        <Field label={t('Web view')}>
          <Check checked={draft.embedFront} onChange={(v) => set('embedFront', v)}>
            {t('Hide the admin bar on site pages opened in the app')}
          </Check>
        </Field>
        <Field label={t('Developer mode')} desc={t('Turn on only on test sites.')}>
          <Check checked={draft.devRedirects} onChange={(v) => set('devRedirects', v)}>
            {t('Allow Expo development redirects (exp://…/--/oauth/callback)')}
          </Check>
        </Field>
      </div>

      <h2>{t('Branded apps')}</h2>
      <p className="flxs-desc">{t("An app published under the organisation's own name connects to this site only if it is listed here. Usually filled in by the preset.")}</p>
      <div className="flxs-card flxs-table-wrap">
        <table className="flxs-table">
          <thead>
            <tr>
              <th>client_id</th>
              <th>{t('Name')}</th>
              <th>{t('Platform')}</th>
              <th>{t('Redirect URIs (one per line)')}</th>
            </tr>
          </thead>
          <tbody>
            {draft.apps.map((a, i) => (
              <tr key={i}>
                <td data-label="client_id">
                  <input type="text" placeholder="flx-mobil-marka" aria-label="client_id" value={a.id} onChange={(e) => setApp(i, { id: e.currentTarget.value })} />
                </td>
                <td data-label={t('Name')}>
                  <input type="text" aria-label={t('Name')} value={a.name} onChange={(e) => setApp(i, { name: e.currentTarget.value })} />
                </td>
                <td data-label={t('Platform')}>
                  <select aria-label={t('Platform')} value={a.platform} onChange={(e) => setApp(i, { platform: e.currentTarget.value })}>
                    <option value="mobile">{t('Mobile')}</option>
                    <option value="desktop">{t('Desktop')}</option>
                  </select>
                </td>
                <td data-label={t('Redirect URIs')}>
                  <textarea rows={2} className="flxs-wide" placeholder="marka://oauth/callback" aria-label={t('Redirect URIs')} value={a.uris} onChange={(e) => setApp(i, { uris: e.currentTarget.value })} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <SaveBar busy={save.isPending} dirty={dirty}>
        <button type="button" className="flxs-btn" disabled={test.isPending} onClick={() => test.mutate({})}>
          {t('Send me a test notification')}
        </button>
      </SaveBar>

      <h2>{t('Connection details')}</h2>
      <div className="flxs-card">
        <dl className="flxs-meta">
          <div>
            <dt>bootstrap</dt>
            <dd>
              <code>{m.bootstrapUrl}</code>
            </dd>
          </div>
          <div>
            <dt>flx-mobil</dt>
            <dd>
              <code>fluxwp://oauth/callback</code>
            </dd>
          </div>
          <div>
            <dt>flx-masaustu</dt>
            <dd>
              <code>http://127.0.0.1:&lt;port&gt;/callback</code> · <code>fluxapp://oauth/callback</code>
            </dd>
          </div>
          <div>
            <dt>{t('Active preset')}</dt>
            <dd>
              <code>{m.preset || '—'}</code>
            </dd>
          </div>
        </dl>
      </div>
    </form>
  );
}
