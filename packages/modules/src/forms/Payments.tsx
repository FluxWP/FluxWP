// Forms › Payments: providers with their webhook address (Stripe: the signing secret) and the latest payments,
// over flxFormsPayments / flxFormsStripeWebhookSecret. Keys and the active provider stay in the panel settings.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsPaymentsAllFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsPayments, FlxFormsStripeWebhookSecret } from '@fluxwp/graphql/operations';
import { Check, Loading } from '@fluxwp/ui';
import { copyText, errorText, payloadError, useForms } from './common';

type Provider = FlxFormsPaymentsAllFragment['providers'][number];

export function PaymentsPage() {
  const { t, toast } = useForms();
  const q = useFlxQuery(FlxFormsPayments, {});
  const d = q.data?.flxFormsPayments;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;
  if (!d.available) return <p className="flxfrm-empty">{t('Flux Donate handles payments: see Donations › Payments.')}</p>;
  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('Payments taken through your forms, from every payment provider. API keys and the active provider are set on the plugin Settings page, Payment tab.')}</p>
      <h2>{t('Providers and webhooks')}</h2>
      <div className="flxfrm-grid2">
        {d.providers.map((p) => (
          <ProviderCard key={p.id} p={p} onCopy={(text) => void copyText(text, toast, t)} />
        ))}
      </div>
      <h2>{t('Recent payments')}</h2>
      <div className="flxfrm-card is-flush">
        {d.payments.length ? (
          <table className="flxfrm-table">
            <thead>
              <tr>
                <th>{t('Date')}</th>
                <th>{t('Form')}</th>
                <th>{t('Confirmation code')}</th>
                <th>{t('Provider')}</th>
                <th>{t('Type')}</th>
                <th className="is-num">{t('Amount')}</th>
                <th>{t('Status')}</th>
                <th>{t('Transaction ID')}</th>
                <th>{t('Email')}</th>
              </tr>
            </thead>
            <tbody>
              {d.payments.map((r) => (
                <tr key={r.id}>
                  <td data-label={t('Date')}>{r.date ? <span title={r.date.label}>{r.date.label}</span> : '—'}</td>
                  <td data-label={t('Form')}>
                    <strong>{r.formName || '—'}</strong> <span className="flxfrm-muted">#{r.formId}</span>
                  </td>
                  <td data-label={t('Confirmation code')}>
                    <code>{r.track}</code>
                  </td>
                  <td data-label={t('Provider')}>{r.provider}</td>
                  <td data-label={t('Type')}>{(r.type === 'subscription' ? t('Subscription') : t('One-time')) + (r.interval ? ' / ' + r.interval : '')}</td>
                  <td data-label={t('Amount')} className="is-num">
                    {r.formatted}
                  </td>
                  <td data-label={t('Status')}>
                    <span className={'flxfrm-pill is-' + r.tone}>{r.statusLabel}</span>
                  </td>
                  <td data-label={t('Transaction ID')}>
                    <code className="flxfrm-clip">{r.reference}</code>
                  </td>
                  <td data-label={t('Email')}>{r.email}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="flxfrm-empty">{t('No payments yet.')}</p>
        )}
      </div>
      {d.payments.length ? <p className="flxfrm-muted">{t('The latest {n} payments are shown.', { n: d.limit })}</p> : null}
    </div>
  );
}

function ProviderCard({ p, onCopy }: { p: Provider; onCopy: (text: string) => void }) {
  const { t, toast } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [secret, setSecret] = useState('');
  const [clear, setClear] = useState(false);
  const save = useFlxMutation(FlxFormsStripeWebhookSecret);
  const submit = async () => {
    try {
      const r = (await save.mutateAsync({ input: clear ? { clear: true } : { secret } })).flxFormsStripeWebhookSecret;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      if (r?.payments) qc.setQueryData(flxQueryKey(scope, FlxFormsPayments, {}), { flxFormsPayments: r.payments });
      setSecret('');
      setClear(false);
      toast(t('Settings saved.'));
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  return (
    <section className={'flxfrm-card' + (p.active ? ' is-active' : '')}>
      <div className="flxfrm-card__head">
        <h3>{p.label}</h3>
        {p.active ? <span className="flxfrm-pill is-ok">{t('Active')}</span> : null}
        <span className={'flxfrm-pill ' + (p.configured ? 'is-soft' : 'is-pending')}>{p.configured ? t('Configured') : t('Not configured')}</span>
      </div>
      <p className="flxfrm-muted">{p.hint}</p>
      <p>
        <button type="button" className="flxfrm-code" title={t('Copy')} onClick={() => onCopy(p.webhookUrl)}>
          {p.webhookUrl}
        </button>
      </p>
      {p.secretOption ? (
        p.secretFixed ? (
          <p className="flxfrm-muted">{t('The signing secret is set in wp-config.php.')}</p>
        ) : (
          <div className="flxfrm-form">
            <div className="flxfrm-field">
              <label className="flxfrm-field__label" htmlFor={'flxfrm-secret-' + p.id}>
                {t('Webhook signing secret')}
              </label>
              <div className="flxfrm-inline">
                <input
                  id={'flxfrm-secret-' + p.id}
                  type="password"
                  autoComplete="off"
                  value={secret}
                  disabled={clear}
                  placeholder={p.secretSet ? t('Saved - leave empty to keep it') : 'whsec_...'}
                  onChange={(e) => setSecret(e.target.value)}
                />
                <button type="button" className="flxfrm-btn is-primary" disabled={save.isPending || (!secret && !clear)} onClick={() => void submit()}>
                  {t('Save')}
                </button>
              </div>
            </div>
            {p.secretSet ? (
              <Check checked={clear} onChange={setClear}>
                {t('Remove the saved secret')}
              </Check>
            ) : null}
          </div>
        )
      ) : null}
    </section>
  );
}
