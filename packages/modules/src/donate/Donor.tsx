// Donor card: person, KPIs, latest gifts, recurring donations, certificates, consent and notes. With Flux
// CRM the donor page is the CRM person card instead (PHP keeps this screen off there). Staff who see only
// their own donors get the card with only the gifts they referred.
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateDonor, FlxDonateDonors, FlxDonorSave } from '@fluxwp/graphql/operations';
import type { FlxDonateDonorQuery } from '@fluxwp/graphql/types';
import { Check, Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { GiftTable } from './Donations';
import { Go, Ico, useDonate } from './shared';
import { Failed, SubsTable } from './Subscriptions';

type D = NonNullable<FlxDonateDonorQuery['flxDonor']>;

export function Donor({ id, notice }: { id: number; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  const q = useFlxQuery(FlxDonateDonor, { id });
  const d = q.data?.flxDonor;
  const back = (
    <Go className="flxdn-back" href={d?.listUrl ?? 'admin.php?page=flxb-donors'}>
      <span aria-hidden="true">‹</span> {d?.ownOnly ? t('My donors') : t('Donors')}
    </Go>
  );
  if (!q.data) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donor card')} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  if (!d) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donor not found')} notice={notice}>
        {back}
      </Screen>
    );
  }
  const actions = d.canAdd ? (
    <Go className="flxdn-btn flxdn-btn--primary" href={d.addUrl}>
      <Ico name="plus" />
      {t('Add donation')}
    </Go>
  ) : null;
  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donor card')} actions={actions} notice={notice}>
      {back}
      <section className="flxdn-card">
        <div className="flxdn-person">
          <span className="flxdn-avatar" aria-hidden="true">
            {d.initial}
          </span>
          <div>
            <h2 className="flxdn-person__name">{d.name}</h2>
            <div className="flxdn-person__contact flxdn-muted">
              {d.email ? (
                <span>
                  <a href={'mailto:' + d.email}>{d.email}</a>
                </span>
              ) : null}
              {d.phone ? (
                <span>
                  <a href={'tel:' + d.phone}>{d.phoneLabel}</a>
                </span>
              ) : null}
              {d.sinceLabel ? <span>{t('Donor since %s', d.sinceLabel)}</span> : null}
            </div>
          </div>
        </div>
      </section>
      <div className="flxdn-kpis">
        <Kpi label={t('Total given')} value={d.totalLabel} />
        <Kpi label={t('Gifts')} value={d.giftCount.toLocaleString()} accent="#2F6FA3" />
        <Kpi label={t('Average gift')} value={d.averageLabel} accent="#E0A526" />
        <Kpi label={t('Latest gift')} value={d.lastGiftLabel || '—'} accent="#7A8C96" />
      </div>
      <div className={d.ownOnly ? 'flxdn-stack' : 'flxdn-grid flxdn-grid--2-1'}>
        <div className="flxdn-stack">
          <section className="flxdn-card">
            <div className="flxdn-card__head">
              <h2 className="flxdn-card__title">{t('All gifts')}</h2>
            </div>
            {d.gifts.length ? (
              <>
                <GiftTable compact rows={d.gifts} showRef={false} bulk={false} picked={new Set()} setPicked={() => undefined} />
                {d.giftsTotal > d.gifts.length ? (
                  <p className="flxdn-tablefoot">
                    <Go href={d.giftsUrl}>{t('See all %s gifts', d.giftsTotal.toLocaleString())}</Go>
                  </p>
                ) : null}
              </>
            ) : (
              <p className="flxdn-empty">{t('No gifts yet.')}</p>
            )}
          </section>
          {!d.ownOnly ? (
            <section className="flxdn-card">
              <div className="flxdn-card__head">
                <h2 className="flxdn-card__title">{t('Recurring donations')}</h2>
              </div>
              {d.subscriptions.length ? <SubsTable rows={d.subscriptions} showDonor={false} /> : <p className="flxdn-empty">{t('No recurring donation.')}</p>}
            </section>
          ) : null}
        </div>
        {!d.ownOnly ? (
          <div className="flxdn-stack">
            {d.certificatesOn ? <Certs d={d} /> : null}
            <Consent d={d} />
          </div>
        ) : null}
      </div>
    </Screen>
  );
}

function Kpi({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return (
    <div className="flxdn-kpi" style={accent ? ({ '--accent': accent } as React.CSSProperties) : undefined}>
      <div className="flxdn-kpi__label">{label}</div>
      <div className="flxdn-kpi__value" title={value}>
        {value}
      </div>
    </div>
  );
}

function Certs({ d }: { d: D }) {
  const { t, tn } = useDonate();
  return (
    <section className="flxdn-card">
      <div className="flxdn-card__head">
        <h2 className="flxdn-card__title">{t('Certificates')}</h2>
      </div>
      {d.certificates.length ? (
        <>
          <ul className="flxdn-certs">
            {d.certificates.map((c, i) => (
              <li key={c.number + i}>
                <span>
                  <strong>{c.number}</strong> <span className="flxdn-muted">{c.dateLabel}</span>
                </span>
                {c.url ? (
                  <a href={c.url} target="_blank" rel="noopener noreferrer">
                    {t('View')}
                  </a>
                ) : null}
              </li>
            ))}
          </ul>
          {d.moreCertificates > 0 ? <p className="flxdn-muted">{tn('and %s more', 'and %s more', d.moreCertificates, d.moreCertificates.toLocaleString())}</p> : null}
        </>
      ) : (
        <p className="flxdn-muted">{t('No certificate yet.')}</p>
      )}
    </section>
  );
}

function Consent({ d }: { d: D }) {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const from = () => ({ consentKvkk: d.consentKvkk, consentEmail: d.consentEmail, consentSms: d.consentSms, note: d.note });
  const [v, setV] = useState(from);
  useEffect(() => setV(from()), [d.consentKvkk, d.consentEmail, d.consentSms, d.note]); // eslint-disable-line react-hooks/exhaustive-deps
  const save = useFlxMutation(FlxDonorSave, {
    onSuccess: (r) => {
      const res = r.flxDonorSave;
      const err = res?.errors?.[0]?.message;
      if (err) {
        told({ kind: 'error', text: err });
        return;
      }
      if (res?.message) told({ kind: 'ok', text: res.message });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDonor) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDonors) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ id: d.databaseId, ...v });
  };
  const sw = (k: 'consentKvkk' | 'consentEmail' | 'consentSms', label: string) => (
    <Check switch checked={v[k]} disabled={!d.canEdit} onChange={(on: boolean) => setV({ ...v, [k]: on })}>
      {label}
    </Check>
  );
  return (
    <section className="flxdn-card">
      <div className="flxdn-card__head">
        <h2 className="flxdn-card__title">{t('Consent and notes')}</h2>
      </div>
      <form className="flxdn-form" onSubmit={submit}>
        {sw('consentKvkk', t('Privacy notice (KVKK) accepted'))}
        {sw('consentEmail', t('May be contacted by e-mail'))}
        {sw('consentSms', t('May be contacted by SMS'))}
        <div className="flxdn-field">
          <label htmlFor="flxdn-donor-notes">{t('Notes')}</label>
          <textarea id="flxdn-donor-notes" className="flxdn-input" rows={4} value={v.note} disabled={!d.canEdit} onChange={(e) => setV({ ...v, note: e.target.value })} />
        </div>
        {d.canEdit ? (
          <div className="flxdn-formactions">
            <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
              {t('Save')}
            </button>
          </div>
        ) : null}
      </form>
    </section>
  );
}
