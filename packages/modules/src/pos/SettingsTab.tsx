import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxPosAccounts, FlxPosSettingsUpdate } from '@fluxwp/graphql/operations';
import { Check, Loading } from '@fluxwp/ui';
import { CreditCard, Globe, SlidersHorizontal } from 'lucide-react';
import { Failed, Panel, SaveBar, msg } from './components';
import type { AccountsData, Settings, TabProps } from './types';

export function SettingsTab({ t, say }: TabProps) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosAccounts, {});
  const [d, setD] = useState<Settings | null>(null);
  const s = d ?? q.data?.flxPosSettings ?? null;
  const set = (patch: Partial<Settings>) => s && setD({ ...s, ...patch });
  const save = useFlxMutation(FlxPosSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxPosSettingsUpdate;
      if (!p || p.errors.length)
        return say({ kind: 'bad', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      const old = qc.getQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}));
      if (old) qc.setQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}), { ...old, flxPosSettings: p.settings });
      setD(null);
      say({ kind: 'ok', text: t('Saved.') });
    },
    onError: (e) => say({ kind: 'bad', text: msg(e, t) }),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data || !s) return <Failed t={t} error={q.error} />;
  const accounts = q.data.flxPosAccounts;
  const currencies = Array.from(
    new Set(
      accounts.flatMap((a) =>
        a.currencies.length ? a.currencies : (q.data.flxPosProviders.find((p) => p.id === a.provider)?.currencies ?? []),
      ),
    ),
  );
  const route = (c: string) => s.routes.find((r) => r.value === c)?.label ?? '';
  const submit = () =>
    save.mutate({
      input: {
        cardTitle: s.cardTitle,
        cardDesc: s.cardDesc,
        cardAccount: s.cardAccount,
        routes: s.routes.filter((r) => r.label).map((r) => ({ currency: r.value, account: r.label })),
        display: s.display,
        showBrands: s.showBrands,
        blockFallback: s.blockFallback,
        saveLog: s.saveLog,
      },
    });
  return (
    <div className="flxpos-settings">
      <Panel icon={CreditCard} title={t('Card payment at checkout')}>
        <div className="flxpos-fields">
          <label className="field flxpos-field">
            <span>{t('Title')}</span>
            <input type="text" value={s.cardTitle} onChange={(e) => set({ cardTitle: e.currentTarget.value })} />
          </label>
          <label className="field flxpos-field">
            <span>{t('Description')}</span>
            <input type="text" value={s.cardDesc} onChange={(e) => set({ cardDesc: e.currentTarget.value })} />
          </label>
          <label className="field flxpos-field">
            <span>{t('Default POS account')}</span>
            <select value={s.cardAccount} onChange={(e) => set({ cardAccount: e.currentTarget.value })}>
              <option value="">{t('First active account')}</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
          </label>
          <label className="field flxpos-field">
            <span>{t('Payment page')}</span>
            <select value={s.display} onChange={(e) => set({ display: e.currentTarget.value })}>
              {s.displays.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
        </div>
      </Panel>
      {currencies.length > 1 && (
        <Panel icon={Globe} title={t('Account per currency')} className="flxpos-routes">
          <div className="flxpos-fields">
            {currencies.map((c) => (
              <label key={c} className="field flxpos-field">
                <span>{c}</span>
                <select
                  value={route(c)}
                  onChange={(e) => set({ routes: [...s.routes.filter((r) => r.value !== c), { value: c, label: e.currentTarget.value }] })}
                >
                  <option value="">{t('Default account')}</option>
                  {accounts.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.name}
                    </option>
                  ))}
                </select>
              </label>
            ))}
          </div>
        </Panel>
      )}
      <Panel icon={SlidersHorizontal} title={t('Display and diagnostics')}>
        <div className="flxpos-checks">
          <Check
            switch
            checked={s.showBrands}
            onChange={(v) => set({ showBrands: v })}
            note={t('Visa, Mastercard and Troy logos next to the card number.')}
          >
            {t('Show card logos')}
          </Check>
          <Check
            switch
            checked={s.blockFallback}
            onChange={(v) => set({ blockFallback: v })}
            note={t('If the checkout block cannot show the card form, the customer goes to the payment page.')}
          >
            {t('Payment page fallback for the checkout block')}
          </Check>
          <Check
            switch
            checked={s.saveLog}
            onChange={(v) => set({ saveLog: v })}
            note={t('Provider requests and answers are kept for 7 days with the keys and card masked.')}
          >
            {t('Keep a technical log')}
          </Check>
        </div>
      </Panel>
      <SaveBar t={t} label={t('Save settings')} dirty={!!d} pending={save.isPending} onSave={submit} onReset={() => setD(null)} />
    </div>
  );
}
