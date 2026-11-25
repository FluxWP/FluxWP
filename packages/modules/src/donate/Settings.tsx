// Donation settings (?page=flxb-settings&tab=…): general, organisation, categories, tracking words, extra tabs
// and the import of earlier payments. Tabs other modules add (WooCommerce, look, lookup, system check) stay
// their own PHP forms: the page prints the open one hidden ("island", FLXB_Admin_Settings_Service) and it is
// moved into place here; another such tab opens with the page.
// PHP preloads the settings (FLXB_Admin::preload_settings()).
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxDonateSettings,
  FlxDonationSettingsBackfill,
  FlxDonationSettingsCategories,
  FlxDonationSettingsGeneral,
  FlxDonationSettingsOrganisation,
  FlxDonationSettingsTabs,
  FlxDonationSettingsTrack,
} from '@fluxwp/graphql/operations';
import type { FlxDonateSettingsQuery } from '@fluxwp/graphql/types';
import { Check, Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';
import { Failed } from './Subscriptions';
import { useStartNotice } from './Partners';
import { IslandNode } from './Detail';

type S = FlxDonateSettingsQuery['flxDonationSettings'];
type Res = { message?: string | null; errors?: ReadonlyArray<{ message: string }> | null } | null | undefined;

/** A save of one tab: the notice, then the settings again. */
function useSave() {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  return {
    done: (r: Res): boolean => {
      const err = r?.errors?.[0]?.message;
      if (err || !r?.message) {
        told({ kind: 'error', text: err || t('The action could not be completed.') });
        return false;
      }
      told({ kind: 'ok', text: r.message });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateSettings) });
      return true;
    },
    fail: (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  };
}

export function Settings({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, open } = useDonate();
  useStartNotice(query);
  const [start] = useState(() => new URLSearchParams(query).get('tab') || 'general');
  const [tab, setTab] = useState(start);
  const q = useFlxQuery(FlxDonateSettings, {});
  const s = q.data?.flxDonationSettings;

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-settings') return;
    const next = '?' + new URLSearchParams({ page: 'flxb-settings', tab }).toString();
    if (u.search !== next) history.replaceState(history.state, '', u.pathname + next);
  }, [tab, host.mode]);

  const onTab = (id: string) => {
    const x = s?.tabs.find((y) => y.id === id);
    // Another module's tab is printed with the page: open its address (the start tab is already here).
    if (x?.island && id !== start) {
      if (host.mode === 'wp' && typeof location !== 'undefined') location.assign(x.url);
      else open(x.url);
      return;
    }
    setTab(id);
  };

  let body: ReactNode;
  if (!s) body = q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />;
  else if (tab === 'organisation') body = <Organisation s={s} />;
  else if (tab === 'categories') body = <Categories s={s} />;
  else if (tab === 'kurban') body = <Tracking s={s} />;
  else if (tab === 'tabs') body = <XTabs s={s} />;
  else if (tab === 'import') body = <Backfill s={s} />;
  else if (tab === 'general') body = <General s={s} island={start === 'general'} />;
  else
    body =
      tab === start ? (
        <IslandNode id={'settings-' + tab} />
      ) : (
        <p className="flxdn-muted">{t('Open this page in wp-admin.')}</p>
      );

  return (
    <Screen<string>
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={t('Donation settings')}
      notice={notice}
      tabs={(s?.tabs ?? []).map((x) => ({ id: x.id, label: x.label }))}
      tab={tab}
      onTab={onTab}
    >
      {body}
    </Screen>
  );
}

function General({ s, island }: { s: S; island: boolean }) {
  const host = useHost();
  const { t } = useDonate();
  const { done, fail } = useSave();
  const [v, setV] = useState(() => ({
    currency: s.currency,
    donationForms: s.donationForms,
    formIds: [...s.formIds],
    moduleTrack: s.moduleTrack,
    moduleReports: s.moduleReports,
    moduleKurban: s.moduleKurban,
  }));
  const save = useFlxMutation(FlxDonationSettingsGeneral, {
    onSuccess: (r) => {
      if (!done(r.flxDonationSettingsGeneral)) return;
      // The modules change the menu: the page comes again with it.
      const modules = v.moduleTrack !== s.moduleTrack || v.moduleReports !== s.moduleReports || v.moduleKurban !== s.moduleKurban;
      if (modules && host.mode === 'wp' && typeof location !== 'undefined') location.reload();
    },
    onError: fail,
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ input: v });
  };
  const toggle = (id: number, on: boolean) => setV((d) => ({ ...d, formIds: on ? [...d.formIds, id] : d.formIds.filter((x) => x !== id) }));
  return (
    <>
      <div className="flxdn-grid flxdn-grid--2-1">
        <section className="flxdn-card">
          <form className="flxdn-form" onSubmit={submit}>
            <div className="flxdn-field">
              <label htmlFor="flxdn-currency">{t('Default currency')}</label>
              <select id="flxdn-currency" value={v.currency} onChange={(e) => setV((d) => ({ ...d, currency: e.target.value }))}>
                {s.currencies.map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label}
                  </option>
                ))}
              </select>
              <p className="flxdn-help">{t('Dashboard totals are in this currency; gifts in other currencies are listed but not added to the totals.')}</p>
            </div>
            <div className="flxdn-field">
              <Check switch checked={v.moduleTrack} onChange={(x) => setV((d) => ({ ...d, moduleTrack: x }))} note={t('Chosen donation types get one item per unit with a code, sent to partners (with agreed prices and settlement), files and history. Leave it off if you do not need it.')}>
                {t('Tracking module')}
              </Check>
            </div>
            <div className="flxdn-field">
              <Check switch checked={v.moduleReports} onChange={(x) => setV((d) => ({ ...d, moduleReports: x }))} note={t('A Reports screen with editable JSON reports (always on with the tracking module).')}>
                {t('Reports')}
              </Check>
            </div>
            <div className="flxdn-field">
              <Check switch checked={v.moduleKurban} onChange={(x) => setV((d) => ({ ...d, moduleKurban: x }))} note={t('Adds a proxy (vekâlet) text the donor accepts, intentions and one item per unit with the name of the person it is given for. Turns tracking on too.')}>
                {t('Gifts on behalf of a person')}
              </Check>
            </div>
            <fieldset className="flxdn-field">
              <legend className="flxdn-label">{t('Which payments are donations?')}</legend>
              <Check radio name="flxdn-dforms" checked={v.donationForms === 'payments'} onChange={() => setV((d) => ({ ...d, donationForms: 'payments' }))}>
                {t('Every payment taken through a Flux Forms form')}
              </Check>
              <Check radio name="flxdn-dforms" checked={v.donationForms === 'list'} onChange={() => setV((d) => ({ ...d, donationForms: 'list' }))}>
                {t('Only payments through the forms selected below (and forms linked to a donation type)')}
              </Check>
              {s.forms.length ? (
                <div className="flxdn-checks">
                  {s.forms.map((f) => (
                    <Check key={f.value} checked={v.formIds.includes(Number(f.value))} onChange={(x) => toggle(Number(f.value), x)}>
                      {f.label}
                    </Check>
                  ))}
                </div>
              ) : null}
            </fieldset>
            <div className="flxdn-formactions">
              <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
                {t('Save settings')}
              </button>
            </div>
          </form>
        </section>
        <aside className="flxdn-card">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{t('How donations are recorded')}</h2>
          </div>
          <p>{t("A donation is stored when a Flux Forms payment is completed: at the final form submission (with name, phone and e-mail from the form), from the payment provider's notifications (refunds, renewals of recurring gifts) and by an hourly check that catches payments whose form was not submitted.")}</p>
          <p>{t('Donors are matched on phone number or e-mail address, never on name alone.')}</p>
        </aside>
      </div>
      {island ? <IslandNode id="settings-general" /> : null}
    </>
  );
}

type WpMedia = (o: { title: string; multiple: boolean; library: { type: string } }) => {
  on: (ev: string, cb: () => void) => void;
  open: () => void;
  state: () => { get: (k: string) => { first: () => { toJSON: () => { id: number; url: string; sizes?: Record<string, { url: string }> } } } };
};

function Organisation({ s }: { s: S }) {
  const { t } = useDonate();
  const { done, fail } = useSave();
  const [v, setV] = useState(() => ({ orgName: s.orgName, logoId: s.logoId, logoUrl: s.logoUrl, primaryColor: s.primaryColor, receiptText: s.receiptText, footerText: s.footerText }));
  const save = useFlxMutation(FlxDonationSettingsOrganisation, { onSuccess: (r) => void done(r.flxDonationSettingsOrganisation), onError: fail });
  const media = typeof window !== 'undefined' ? (window as { wp?: { media?: WpMedia } }).wp?.media : undefined;
  const pick = () => {
    if (!media) return;
    const fr = media({ title: t('Choose a logo'), multiple: false, library: { type: 'image' } });
    fr.on('select', () => {
      const a = fr.state().get('selection').first().toJSON();
      setV((d) => ({ ...d, logoId: a.id, logoUrl: a.sizes?.medium?.url ?? a.url }));
    });
    fr.open();
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const { logoUrl, ...input } = v;
    void logoUrl;
    save.mutate({ input });
  };
  return (
    <div className="flxdn-grid flxdn-grid--2-1">
      <section className="flxdn-card">
        <div className="flxdn-card__head">
          <div>
            <h2 className="flxdn-card__title">{t('Organisation')}</h2>
            <p className="flxdn-card__hint">{t('Shown on receipts, donation pages and in exports. Empty fields fall back to the site name, the theme logo and the theme colours.')}</p>
          </div>
        </div>
        <form className="flxdn-form" onSubmit={submit}>
          <div className="flxdn-field">
            <label htmlFor="flxdn-org">{t('Organisation name')}</label>
            <input type="text" id="flxdn-org" maxLength={190} value={v.orgName} placeholder={s.orgPlaceholder} onChange={(e) => setV((d) => ({ ...d, orgName: e.target.value }))} />
          </div>
          <div className="flxdn-field">
            <span className="flxdn-label">{t('Logo')}</span>
            <div className="flxdn-media">
              {v.logoUrl ? <img alt="" src={v.logoUrl} /> : null}
              {media ? (
                <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={pick}>
                  {t('Choose logo')}
                </button>
              ) : null}
              {v.logoId ? (
                <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setV((d) => ({ ...d, logoId: 0, logoUrl: '' }))}>
                  {t('Remove')}
                </button>
              ) : null}
            </div>
          </div>
          <div className="flxdn-field">
            <label htmlFor="flxdn-color">{t('brand color')}</label>
            <span className="flxdn-colorrow">
              <input type="color" value={/^#[0-9a-fA-F]{6}$/.test(v.primaryColor) ? v.primaryColor : s.colorHolder} onChange={(e) => setV((d) => ({ ...d, primaryColor: e.target.value.toUpperCase() }))} aria-hidden="true" tabIndex={-1} />
              <input type="text" id="flxdn-color" maxLength={7} pattern="#[0-9a-fA-F]{6}" value={v.primaryColor} placeholder={s.colorHolder} onChange={(e) => setV((d) => ({ ...d, primaryColor: e.target.value }))} />
            </span>
            <p className="flxdn-help">{t("Hex color such as #1F2937. Empty = the theme's primary colour.")}</p>
          </div>
          <div className="flxdn-field">
            <label htmlFor="flxdn-receipt">{t('Receipt text')}</label>
            <textarea id="flxdn-receipt" rows={3} value={v.receiptText} onChange={(e) => setV((d) => ({ ...d, receiptText: e.target.value }))} />
            <p className="flxdn-help">{t('A thank-you note printed on the donation receipt.')}</p>
          </div>
          <div className="flxdn-field">
            <label htmlFor="flxdn-footer">{t('Footer text')}</label>
            <textarea id="flxdn-footer" rows={3} value={v.footerText} onChange={(e) => setV((d) => ({ ...d, footerText: e.target.value }))} />
            <p className="flxdn-help">{t('Address, registration number, bank details and similar; printed at the bottom of receipts.')}</p>
          </div>
          <div className="flxdn-formactions">
            <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
              {t('Save settings')}
            </button>
          </div>
        </form>
      </section>
      <aside className="flxdn-card">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Preview')}</h2>
        </div>
        <div className="flxdn-person">
          {s.brandLogo ? <img src={s.brandLogo} alt="" className="flxdn-brandlogo" /> : null}
          <div>
            <div className="flxdn-person__name">{s.brandName}</div>
            <div className="flxdn-muted">
              <span className="flxdn-swatch" style={{ background: s.brandPrimary }} /> <span className="flxdn-swatch" style={{ background: s.brandDark }} />
            </div>
          </div>
        </div>
      </aside>
    </div>
  );
}

function Categories({ s }: { s: S }) {
  const { t, tn } = useDonate();
  const { done, fail } = useSave();
  const [rows, setRows] = useState(() => s.categories.map((c) => ({ key: c.key, label: c.label, used: c.used })));
  const save = useFlxMutation(FlxDonationSettingsCategories, { onSuccess: (r) => void done(r.flxDonationSettingsCategories), onError: fail });
  const set = (i: number, p: Partial<{ key: string; label: string }>) => setRows((d) => d.map((x, j) => (j === i ? { ...x, ...p } : x)));
  return (
    <section className="flxdn-card flxdn-card--narrow">
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{t('Donation type categories')}</h2>
          <p className="flxdn-card__hint">{t('Group donation types, e.g. Sacrifice, Ramadan, General. The key is stored on the types; a category that is in use cannot be removed.')}</p>
        </div>
      </div>
      <form
        className="flxdn-form"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate({ input: { categories: rows.map((r) => ({ key: r.key, label: r.label })) } });
        }}
      >
        <div className="flxdn-repeater">
          <div className="flxdn-optrows">
            {rows.map((r, i) => (
              <div className="flxdn-optrow flxdn-catrow" key={i}>
                <label className="flxdn-field">
                  <span className="flxdn-label">{t('Name')}</span>
                  <input type="text" maxLength={80} value={r.label} onChange={(e) => set(i, { label: e.target.value })} />
                </label>
                <label className="flxdn-field">
                  <span className="flxdn-label">{t('Key')}</span>
                  <input type="text" maxLength={40} value={r.key} readOnly={r.used > 0} title={r.used > 0 ? tn('Used by %s type', 'Used by %s types', r.used, r.used) : undefined} onChange={(e) => set(i, { key: e.target.value })} />
                </label>
                {r.used > 0 ? (
                  <span className="flxdn-iconbtn is-off" aria-hidden="true" />
                ) : (
                  <button type="button" className="flxdn-iconbtn flxdn-iconbtn--danger" aria-label={t('Remove category')} onClick={() => setRows((d) => d.filter((_, j) => j !== i))}>
                    ×
                  </button>
                )}
              </div>
            ))}
          </div>
          <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={() => setRows((d) => [...d, { key: '', used: 0, label: String() }])}>
            <Ico name="plus" size={14} /> {t('Add category')}
          </button>
        </div>
        <div className="flxdn-formactions">
          <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
            {t('Save categories')}
          </button>
        </div>
      </form>
    </section>
  );
}

function Tracking({ s }: { s: S }) {
  const { t } = useDonate();
  const { done, fail } = useSave();
  const [early, setEarly] = useState(String(s.earlyDays));
  const [labels, setLabels] = useState<Record<string, string>>(() => Object.fromEntries(s.labels.map((l) => [l.key, l.value])));
  const [proxy, setProxy] = useState(s.proxyText);
  const save = useFlxMutation(FlxDonationSettingsTrack, { onSuccess: (r) => void done(r.flxDonationSettingsTrack), onError: fail });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ input: { earlyDays: Math.max(0, Math.min(30, Number(early) || 0)), labels: s.labels.map((l) => ({ key: l.key, value: labels[l.key] ?? '' })), proxyText: proxy } });
  };
  return (
    <div className="flxdn-grid flxdn-grid--2-1">
      <section className="flxdn-card">
        <div className="flxdn-card__head">
          <div>
            <h2 className="flxdn-card__title">{t('Tracking')}</h2>
            <p className="flxdn-card__hint">{t('The words on the tracking screens. A donation type can use its own words (Donation types › Tracking); empty fields use these, and empty ones here the built-in words.')}</p>
          </div>
        </div>
        <form className="flxdn-form" onSubmit={submit}>
          <div className="flxdn-field">
            <label htmlFor="flxdn-early">{t('Early days')}</label>
            <input id="flxdn-early" type="number" min={0} max={30} step={1} className="flxdn-num" value={early} onChange={(e) => setEarly(e.target.value)} />
            <p className="flxdn-help">{t('How many days before the target date an item may already be done. After the target day it is still allowed, but shown as late.')}</p>
          </div>
          <div className="flxdn-fields-2">
            {s.labels.map((l) => (
              <div className="flxdn-field" key={l.key}>
                <label htmlFor={'flxdn-lb-' + l.key}>{l.label}</label>
                <input id={'flxdn-lb-' + l.key} type="text" maxLength={l.max} value={labels[l.key] ?? ''} placeholder={l.placeholder} onChange={(e) => setLabels((d) => ({ ...d, [l.key]: e.target.value }))} />
              </div>
            ))}
          </div>
          {s.kurban ? (
            <div className="flxdn-field">
              <label htmlFor="flxdn-proxy">{t('Proxy (vekâlet) text')}</label>
              <textarea id="flxdn-proxy" rows={8} value={proxy} onChange={(e) => setProxy(e.target.value)} />
              <p className="flxdn-help">{t('The text a donor accepts to authorise the organisation to give on their behalf. Every change is saved as a new version; each donation keeps the version that was accepted.')}</p>
            </div>
          ) : null}
          <div className="flxdn-formactions">
            <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
              {t('Save settings')}
            </button>
          </div>
        </form>
      </section>
      <aside className="flxdn-card">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Partners')}</h2>
        </div>
        <p>{t('Who items are sent to, their agreed prices and payments are kept under Donations › Partners.')}</p>
        <p>
          <Go className="flxdn-btn" href="admin.php?page=flxb-partners">
            {t('Partners')}
          </Go>
        </p>
        {s.kurban ? (
          <>
            <h3 className="flxdn-h3">{t('Proxy text versions')}</h3>
            {s.proxyVersions.length ? (
              <ul className="flxdn-list">
                {s.proxyVersions.map((x) => (
                  <li key={x}>{x}</li>
                ))}
              </ul>
            ) : (
              <p className="flxdn-muted">{t('No text yet. Donations of types that require a proxy cannot be paid until a text is saved.')}</p>
            )}
          </>
        ) : null}
      </aside>
    </div>
  );
}

function XTabs({ s }: { s: S }) {
  const { t, tn } = useDonate();
  const { done, fail } = useSave();
  const [json, setJson] = useState(s.tabsJson);
  const save = useFlxMutation(FlxDonationSettingsTabs, { onSuccess: (r) => void done(r.flxDonationSettingsTabs), onError: fail });
  return (
    <div className="flxdn-grid flxdn-grid--2-1">
      <section className="flxdn-card">
        <div className="flxdn-card__head">
          <div>
            <h2 className="flxdn-card__title">{t('Extra tabs')}</h2>
            <p className="flxdn-card__hint">{t('Add your own tabs with fields to tracked records ("on": "item") or donations ("on": "donation"), for all or some donation types. Leave it empty if you do not need any.')}</p>
          </div>
        </div>
        <form
          className="flxdn-form"
          onSubmit={(e) => {
            e.preventDefault();
            save.mutate({ input: { json } });
          }}
        >
          <div className="flxdn-field">
            <label htmlFor="flxdn-xj">{t('Tabs (JSON list)')}</label>
            <textarea id="flxdn-xj" rows={20} className="flxdn-code" spellCheck={false} placeholder="[]" value={json} onChange={(e) => setJson(e.target.value)} />
          </div>
          <div className="flxdn-actions">
            <button className="flxdn-btn flxdn-btn--primary" type="submit" disabled={save.isPending}>
              {t('Save')}
            </button>
          </div>
        </form>
      </section>
      <aside className="flxdn-card">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('Tabs in use')}</h2>
        </div>
        {s.tabsInUse.length ? (
          <ul className="flxdn-list">
            {s.tabsInUse.map((x) => (
              <li key={x.id}>
                <b>{x.title}</b> <code>{x.id}</code> · {x.donation ? t('Donation page') : t('Record page')} · {tn('%d field', '%d fields', x.fields, x.fields)}
                {x.builtin ? <span className="flxdn-muted"> · {t('from a plugin')}</span> : null}
              </li>
            ))}
          </ul>
        ) : (
          <p className="flxdn-muted">{t('No extra tabs yet.')}</p>
        )}
        <p className="flxdn-help">
          <b>type</b> {s.fieldTypes}
        </p>
        <details className="flxdn-details">
          <summary>{t('Example')}</summary>
          <pre className="flxdn-code">{s.tabsExample}</pre>
        </details>
      </aside>
    </div>
  );
}

function Backfill({ s }: { s: S }) {
  const { t } = useDonate();
  const { done, fail } = useSave();
  const run = useFlxMutation(FlxDonationSettingsBackfill, { onSuccess: (r) => void done(r.flxDonationSettingsBackfill), onError: fail });
  return (
    <section className="flxdn-card flxdn-card--narrow">
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{t('Import existing payments')}</h2>
          <p className="flxdn-card__hint">{t('Reads completed payments stored by Flux Forms and adds the missing ones as donations. Safe to run more than once: a payment is never imported twice.')}</p>
        </div>
      </div>
      {!s.importReady ? (
        <div className="flxdn-notice flxdn-notice--warn">
          <p>{t("Flux Forms's payment table was not found. Is Flux Forms installed?")}</p>
        </div>
      ) : null}
      <button type="button" className="flxdn-btn flxdn-btn--primary" disabled={run.isPending} onClick={() => run.mutate({ input: {} })}>
        <Ico name="download" /> {run.isPending ? t('Importing...') : t('Import now')}
      </button>
      {s.importLast ? <p className="flxdn-muted">{s.importLast}</p> : null}
    </section>
  );
}
