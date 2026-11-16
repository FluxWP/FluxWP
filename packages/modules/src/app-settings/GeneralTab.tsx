// Application › General: access and session lifetime, MCP self-registration, admin sessions, sign-in with code, API key rights.
import { useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAdminSettings, FlxAppAdminSettingsSave } from '@fluxwp/graphql/operations';
import type { FlxAppAdminSettingsQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Check, Field, payloadError, SaveBar, useScreen } from './common';

type Settings = FlxAppAdminSettingsQuery['flxAppAdminSettings'];
type Draft = { accessMinutes: string; refreshDays: string; dynamicClients: boolean; webSession: boolean; otpEnabled: boolean; otpCreate: boolean; otpStaff: boolean; phoneSelfVerify: boolean; keysCap: string };

const draftOf = (s: Settings): Draft => ({
  accessMinutes: String(s.accessMinutes),
  refreshDays: String(s.refreshDays),
  dynamicClients: s.dynamicClients,
  webSession: s.webSession,
  otpEnabled: s.otpEnabled,
  otpCreate: s.otpCreate,
  otpStaff: s.otpStaff,
  phoneSelfVerify: s.phoneSelfVerify,
  keysCap: s.keysCap,
});

export function GeneralTab() {
  const { t, told, fail } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAppAdminSettings, {});
  const [draft, setDraft] = useState<Draft | null>(null);
  const [dirty, setDirty] = useState(false);
  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data.flxAppAdminSettings));
  }, [q.data, dirty]);

  const save = useFlxMutation(FlxAppAdminSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxAppAdminSettingsSave;
      const err = payloadError(p?.errors);
      if (!p?.settings || err) return fail(err ? new Error(err) : null);
      qc.setQueryData(flxQueryKey(scope, FlxAppAdminSettings, {}), { flxAppAdminSettings: p.settings });
      setDirty(false);
      told(t('Saved.'));
    },
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const s = q.data.flxAppAdminSettings;
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft((d) => d && { ...d, [k]: v });
    setDirty(true);
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({
      input: {
        accessMinutes: Number.parseInt(draft.accessMinutes, 10) || 60,
        refreshDays: Number.parseInt(draft.refreshDays, 10) || 60,
        dynamicClients: draft.dynamicClients,
        webSession: draft.webSession,
        otpEnabled: draft.otpEnabled,
        otpCreate: draft.otpCreate,
        otpStaff: draft.otpStaff,
        phoneSelfVerify: draft.phoneSelfVerify,
        keysCap: draft.keysCap,
      },
    });
  };

  return (
    <form onSubmit={submit}>
      <p className="flxs-lead">{t('Sign-in, session and panel settings for the desktop (macOS, Windows) and mobile (iOS, Android) app.')}</p>
      <div className="flxs-card flxs-form">
        <Field label={t('Access token lifetime')} htmlFor="flxs-am">
          <input id="flxs-am" type="number" min={5} max={1440} className="flxs-num" value={draft.accessMinutes} onChange={(e) => set('accessMinutes', e.currentTarget.value)} /> {t('minutes')}
        </Field>
        <Field label={t('Session lifetime (when unused)')} htmlFor="flxs-rd" desc={t('The period renews while the app is used; after this many days without use, sign-in is required again.')}>
          <input id="flxs-rd" type="number" min={1} max={365} className="flxs-num" value={draft.refreshDays} onChange={(e) => set('refreshDays', e.currentTarget.value)} /> {t('days')}
        </Field>
        <Field label={t('MCP clients')}>
          <Check checked={draft.dynamicClients} onChange={(v) => set('dynamicClients', v)}>
            {t('Allow self-registration (MCP clients such as Claude connect via OAuth; every connection still needs your approval)')}
          </Check>
        </Field>
        <Field label={t('Admin session')}>
          <Check checked={draft.webSession} onChange={(v) => set('webSession', v)}>
            {t('Let the app open menus without a native screen as admin pages (one-time sign-in link)')}
          </Check>
        </Field>
        <Field
          label={t('Sign in with code')}
          desc={t('Phone codes go out through Flux Call Center (WhatsApp) when that channel is available; otherwise the code is emailed to the account. A phone code signs in only the account whose saved number is exactly that full number. Donations and orders never open or claim an account.')}
        >
          <Check checked={draft.otpEnabled} onChange={(v) => set('otpEnabled', v)}>
            {t('Let donors sign in with a code sent to their email, or to the phone number saved on their account')}
          </Check>
          <Check checked={draft.otpCreate} onChange={(v) => set('otpCreate', v)}>
            {t('Create a customer account for an email after its sign-in code is confirmed')}
          </Check>
          <Check checked={draft.otpStaff} onChange={(v) => set('otpStaff', v)}>
            {t('Let staff sign in with a code too (not recommended; staff use OAuth in the browser)')}
          </Check>
        </Field>
        <Field
          label={t('Own phone number')}
          desc={t('The code is sent only to that phone (WhatsApp or an SMS provider), never by email. People who can edit posts keep an administrator-managed number unless staff code sign-in is turned on.')}
        >
          <Check checked={draft.phoneSelfVerify} onChange={(v) => set('phoneSelfVerify', v)}>
            {t('Let a signed-in donor confirm a phone number and save it on their account')}
          </Check>
        </Field>
        <Field label={t('Can create API keys')} htmlFor="flxs-kc">
          <select id="flxs-kc" value={draft.keysCap} onChange={(e) => set('keysCap', e.currentTarget.value)}>
            {s.keysCapChoices.map((c) => (
              <option key={c.key} value={c.key}>
                {c.label}
              </option>
            ))}
          </select>
        </Field>
      </div>
      <SaveBar busy={save.isPending} dirty={dirty} />
    </form>
  );
}
