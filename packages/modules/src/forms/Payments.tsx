// Forms › Payments: Flux POS (which takes the payments of payment forms) and the latest payments, over
// flxFormsPayments. Flux Forms has no payment gateway of its own; forms that take no payment need nothing.
import type { FlxFormsPaymentsAllFragment } from '@fluxwp/graphql/types';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsPayments } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, useForms } from './common';

type Pos = FlxFormsPaymentsAllFragment['pos'];

export function PaymentsPage() {
  const { t } = useForms();
  const q = useFlxQuery(FlxFormsPayments, {});
  const d = q.data?.flxFormsPayments;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;
  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('Payment forms take payments through Flux POS. Forms that take no payment need nothing.')}</p>
      <PosCard pos={d.pos} />
      {!d.available ? <p className="flxfrm-muted">{t('Flux Donate keeps the payment records: see Donations › Payments.')}</p> : null}
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

function PosCard({ pos }: { pos: Pos }) {
  const { t } = useForms();
  const host = useHost();
  const [tone, label, text] = !pos.active
    ? (['is-pending', pos.installed ? t('Not active') : t('Not installed'), t('Flux POS is needed to take payments. Until it is active, the payment box of payment forms says online payment is not available.')] as const)
    : !pos.configured
      ? (['is-pending', t('No account'), t('Flux POS is active but has no active POS account yet. Add an account in Flux POS.')] as const)
      : (['is-ok', t('Ready'), t('Payment forms take card payments through Flux POS (3D Secure). Currencies: {list}.', { list: pos.currencies.join(', ') })] as const);
  return (
    <section className={'flxfrm-card' + (pos.active && pos.configured ? ' is-active' : '')}>
      <div className="flxfrm-card__head">
        <h3>{t('Flux POS')}</h3>
        <span className={'flxfrm-pill ' + tone}>{label}</span>
        {pos.test ? <span className="flxfrm-pill is-warning">{t('Test mode')}</span> : null}
      </div>
      <p className="flxfrm-muted">{text}</p>
      <p>
        <button type="button" className="flxfrm-btn" onClick={() => host.openUrl(pos.url)}>
          {pos.active ? t('Open Flux POS') : t('Plugins')}
        </button>
      </p>
    </section>
  );
}
