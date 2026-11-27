import { useState } from 'react';
import { useFlxMutation } from '@fluxwp/graphql/react';
import { FlxPosAccountSave } from '@fluxwp/graphql/operations';
import { Check } from '@fluxwp/ui';
import { CreditCard, Globe, Info, KeyRound, Plus, SlidersHorizontal, Trash2 } from 'lucide-react';
import { Dialog, FieldGroup, InlineNotice, msg } from './components';
import type { Translate } from './i18n';
import type { Account, Provider, Settings } from './types';

interface Draft {
  id: string;
  provider: string;
  label: string;
  enabled: boolean;
  test: boolean;
  mode: string;
  creds: Record<string, string>;
  currencies: string[];
  installments: { count: number; rate: string }[];
  instMin: string;
  title: string;
  display: string;
}

const draftOf = (a: Account | null, p: Provider): Draft => ({
  id: a?.id ?? '',
  provider: p.id,
  label: a?.label ?? '',
  enabled: a ? a.enabled : true,
  test: a ? a.test : true,
  mode: a?.mode || p.modes[0]?.value || '',
  creds: Object.fromEntries([
    ...p.fields.map((f) => [f.key, f.secret ? '' : (a?.creds.find((c) => c.key === f.key)?.value ?? f.defaultValue)] as const),
    ...(a?.creds.filter((c) => c.key.startsWith('url_')).map((c) => [c.key, c.value] as const) ?? []),
  ]),
  currencies: a?.currencies ?? [],
  installments: (a?.installments ?? []).map((i) => ({ count: i.count, rate: String(i.rate) })),
  instMin: a && a.instMin ? String(a.instMin) : '',
  title: a?.title ?? '',
  display: a?.display ?? '',
});

export function AccountEditor({
  t,
  account,
  provider: p,
  settings,
  onClose,
  onSaved,
}: {
  t: Translate;
  account: Account | null;
  provider: Provider;
  settings: Settings;
  onClose: () => void;
  onSaved: (accounts: Account[], isNew: boolean) => void;
}) {
  const [d, setD] = useState<Draft>(() => draftOf(account, p));
  const [err, setErr] = useState('');
  const set = (patch: Partial<Draft>) => setD((o) => ({ ...o, ...patch }));
  const save = useFlxMutation(FlxPosAccountSave, {
    onSuccess: (r) => {
      const x = r.flxPosAccountSave;
      if (!x) return setErr(t('The action could not be completed.'));
      if (x.errors.length) return setErr(x.errors.map((e) => e.message).join(' '));
      onSaved(x.accounts, !account);
    },
    onError: (e) => setErr(msg(e, t)),
  });
  const submit = () => {
    setErr('');
    save.mutate({
      input: {
        ...(d.id ? { id: d.id } : {}),
        provider: d.provider,
        label: d.label.trim(),
        enabled: d.enabled,
        test: d.test,
        mode: d.mode,
        creds: Object.entries(d.creds).map(([key, value]) => ({ key, value: value.trim() })),
        currencies: d.currencies,
        installments: d.installments
          .filter((i) => i.count >= 2 && i.rate.trim() !== '')
          .map((i) => ({ count: i.count, rate: Number(i.rate.replace(',', '.')) || 0 })),
        instMin: Number(d.instMin.replace(',', '.')) || 0,
        title: d.title.trim(),
        display: d.display,
      },
    });
  };
  const saved = (key: string) => account?.creds.find((c) => c.key === key);
  const cardCaps = p.caps.includes('installment');
  return (
    <Dialog
      t={t}
      title={(account ? t('Edit POS account') : t('New POS account')) + ' · ' + p.name}
      onClose={() => !save.isPending && onClose()}
      wide
      actions={
        <>
          <button type="button" className="btn-ghost" disabled={save.isPending} onClick={onClose}>
            {t('Cancel')}
          </button>
          <button type="submit" form="flxpos-account-form" className="btn-primary" disabled={save.isPending}>
            {save.isPending ? t('Saving…') : t('Save')}
          </button>
        </>
      }
    >
      <form
        id="flxpos-account-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          if (!save.isPending) submit();
        }}
      >
        {p.notes && <InlineNotice kind="info">{p.notes}</InlineNotice>}
        <FieldGroup icon={KeyRound} title={t('Account details')}>
          <div className="flxpos-fields">
            <label className="field flxpos-field">
              <span>{t('Name in the panel')}</span>
              <input type="text" value={d.label} placeholder={p.name} onChange={(e) => set({ label: e.currentTarget.value })} />
            </label>
            {p.modes.length > 1 && (
              <label className="field flxpos-field">
                <span>{t('Payment model')}</span>
                <select value={d.mode} onChange={(e) => set({ mode: e.currentTarget.value })}>
                  {p.modes.map((m) => (
                    <option key={m.value} value={m.value}>
                      {m.label}
                    </option>
                  ))}
                </select>
              </label>
            )}
            {p.fields.map((f) => {
              const sv = saved(f.key);
              return (
                <label key={f.key} className="field flxpos-field">
                  <span>{f.label}</span>
                  <input
                    type={f.secret ? 'password' : 'text'}
                    className="code"
                    autoComplete={f.secret ? 'new-password' : 'off'}
                    value={d.creds[f.key] ?? ''}
                    placeholder={f.secret && sv?.isSet ? t('Saved (%s). Leave empty to keep it.', sv.mask) : ''}
                    onChange={(e) => set({ creds: { ...d.creds, [f.key]: e.currentTarget.value } })}
                  />
                  {f.help && <small>{f.help}</small>}
                </label>
              );
            })}
          </div>
        </FieldGroup>
        <FieldGroup icon={SlidersHorizontal} title={t('Account status')}>
          <div className="flxpos-checks">
            <Check switch checked={d.enabled} onChange={(v) => set({ enabled: v })} note={t('Customers can pay with this account.')}>
              {t('On')}
            </Check>
            <Check
              switch
              checked={d.test}
              onChange={(v) => set({ test: v })}
              note={t('Uses the provider test server; no real money moves.')}
            >
              {t('Test mode')}
            </Check>
          </div>
        </FieldGroup>
        {p.currencies.length > 1 && (
          <FieldGroup icon={Globe} title={t('Currencies (none chosen = all)')}>
            <div className="flxpos-currencies">
              {p.currencies.map((c) => (
                <Check
                  key={c}
                  checked={d.currencies.includes(c)}
                  onChange={(v) => set({ currencies: v ? [...d.currencies, c] : d.currencies.filter((x) => x !== c) })}
                >
                  {c}
                </Check>
              ))}
            </div>
          </FieldGroup>
        )}
        {cardCaps && (
          <FieldGroup icon={CreditCard} title={t('Installments')}>
            <p className="flxpos-muted">{t('Extra charge in percent for each installment count. Empty table = single payment only.')}</p>
            {d.installments.map((row, i) => (
              <div key={i} className="flxpos-inst__row">
                <input
                  type="number"
                  min={2}
                  max={36}
                  value={row.count}
                  aria-label={t('Installment count')}
                  onChange={(e) =>
                    set({ installments: d.installments.map((x, j) => (j === i ? { ...x, count: Number(e.currentTarget.value) } : x)) })
                  }
                />
                <span>{t('installments')}</span>
                <input
                  type="text"
                  inputMode="decimal"
                  value={row.rate}
                  aria-label={t('Extra charge (%)')}
                  onChange={(e) =>
                    set({ installments: d.installments.map((x, j) => (j === i ? { ...x, rate: e.currentTarget.value } : x)) })
                  }
                />
                <span>%</span>
                <button
                  type="button"
                  className="icon-btn flxpos-danger"
                  aria-label={t('Remove')}
                  onClick={() => set({ installments: d.installments.filter((_x, j) => j !== i) })}
                >
                  <Trash2 size={15} aria-hidden="true" />
                </button>
              </div>
            ))}
            <button
              type="button"
              className="btn-ghost small"
              onClick={() => set({ installments: [...d.installments, { count: (d.installments.at(-1)?.count ?? 1) + 1, rate: '0' }] })}
            >
              <Plus size={14} aria-hidden="true" />
              {t('Add installment')}
            </button>
            <label className="field flxpos-field flxpos-field--inline">
              <span>{t('Smallest amount with installments')}</span>
              <input type="text" inputMode="decimal" value={d.instMin} onChange={(e) => set({ instMin: e.currentTarget.value })} />
            </label>
          </FieldGroup>
        )}
        <FieldGroup icon={CreditCard} title={t('Card payment at checkout')}>
          <div className="flxpos-fields">
            <label className="field flxpos-field">
              <span>{t('Name at checkout (optional)')}</span>
              <input type="text" value={d.title} placeholder={settings.cardTitle} onChange={(e) => set({ title: e.currentTarget.value })} />
            </label>
            <label className="field flxpos-field">
              <span>{t('Payment page')}</span>
              <select value={d.display} onChange={(e) => set({ display: e.currentTarget.value })}>
                <option value="">{t('General setting')}</option>
                {settings.displays.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </FieldGroup>
        {account?.notifyUrl && (
          <FieldGroup icon={Info} title={t('Provider notifications')}>
            <label className="field flxpos-field">
              <span>{t('Notification address (give it to the provider if it asks)')}</span>
              <input type="text" className="code" readOnly value={account.notifyUrl} onFocus={(e) => e.currentTarget.select()} />
            </label>
          </FieldGroup>
        )}
        {err && <InlineNotice kind="bad">{err}</InlineNotice>}
      </form>
    </Dialog>
  );
}
