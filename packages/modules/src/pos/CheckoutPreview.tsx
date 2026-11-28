import { Check } from '@fluxwp/ui';
import { ArrowUpRight, CreditCard, Eye, LockKeyhole, Wallet } from 'lucide-react';
import { EmptyState, Panel } from './components';
import type { Translate } from './i18n';
import type { Method } from './types';

export function CheckoutPreview({
  t,
  methods,
  selected,
  onSelect,
  checkoutUrl,
}: {
  t: Translate;
  methods: Method[];
  selected: string | undefined;
  onSelect: (id: string) => void;
  checkoutUrl: string;
}) {
  return (
    <Panel
      icon={Eye}
      title={t('Checkout preview')}
      description={t('How the payment step looks at checkout (with unsaved changes).')}
      className="flxpos-preview"
    >
      {methods.length > 0 ? (
        <div className="flxpos-pv">
          <div className="flxpos-pv__title">
            <LockKeyhole size={15} aria-hidden="true" />
            {t('Payment')}
          </div>
          {methods.map((method) => (
            <div key={method.id} className={'flxpos-pv__row' + (selected === method.id ? ' is-on' : '')}>
              <Check radio name="flxpos-pv" checked={selected === method.id} onChange={() => onSelect(method.id)}>
                {method.title || method.methodTitle}
              </Check>
              <span className="flxpos-pv__icons">
                {method.icons.slice(0, 4).map((src) => (
                  <img key={src} src={src} alt="" loading="lazy" />
                ))}
              </span>
              {selected === method.id && (method.description || method.hasFields) && (
                <div className="flxpos-pv__desc">
                  {method.description}
                  {method.hasFields && (
                    <div className="flxpos-pv__fields" aria-hidden="true">
                      <span>
                        <CreditCard size={14} />
                      </span>
                      <span />
                      <span />
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
          <div className="flxpos-pv__btn">{t('Place order')}</div>
        </div>
      ) : (
        <EmptyState icon={Wallet} title={t('No payment method is on. Customers cannot pay.')} />
      )}
      {checkoutUrl && (
        <a className="btn-link flxpos-preview__link" href={checkoutUrl} target="_blank" rel="noopener">
          {t('Open checkout')} <ArrowUpRight size={15} aria-hidden="true" />
        </a>
      )}
    </Panel>
  );
}
