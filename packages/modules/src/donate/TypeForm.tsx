// Donation type editor (?edit=N, ?edit=new): basics, prices and options, linked forms, tracking, gifts on
// behalf of a person, publishing, the shortcode and delete. The form mapping, the translations and other
// plugins' cards (flxb_type_fields) are printed by PHP as islands with their classic field names; their fields
// go back to the server as `extra`. PHP preloads the editor (FLXB_Admin::preload_types()).
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateTypeForm, FlxDonateTypes, FlxDonationTypeDelete, FlxDonationTypeSave } from '@fluxwp/graphql/operations';
import type { FlxDonateTypeFormQuery, FlxDonationTypeStatus } from '@fluxwp/graphql/types';
import { Check, DatePicker, Loading, Modal, Screen, type ScreenBack } from '@fluxwp/ui';
import { useHost } from '../host';
import { IslandNode } from './Detail';
import { Go, Ico, useBackButton, useDonate } from './shared';
import { Failed } from './Subscriptions';

type F = NonNullable<FlxDonateTypeFormQuery['flxDonationTypeForm']>;
type Choice = { value: string; label: string };
type Opt = { key: string; label: string; price: string; unit: string; unitsPer: string; badge: string; active: boolean; prices: Record<string, string> };
type Draft = {
  title: string;
  slug: string;
  category: string;
  currency: string;
  description: string;
  subtitle: string;
  icon: string;
  imageId: number;
  imageUrl: string;
  options: Opt[];
  allowFree: boolean;
  minAmount: string;
  minBy: Record<string, string>;
  allowQuantity: boolean;
  maxQuantity: string;
  allowRecurring: boolean;
  formIds: number[];
  lockPreselect: boolean;
  trackItems: boolean;
  fileCompletes: boolean;
  labels: Record<string, string>;
  requiresProxy: boolean;
  intention: string;
  grouping: string;
  meatToNeedy: boolean;
  status: FlxDonationTypeStatus;
  startDate: string;
  endDate: string;
  goal: string;
};

const num = (n: number) => (n ? String(n) : '');
const NONE = '';
const blankOpt = (): Opt => ({ key: NONE, label: NONE, price: NONE, unit: '', unitsPer: '1', badge: '', active: true, prices: {} });
const amounts = (m: Record<string, string>) =>
  Object.entries(m)
    .filter(([, v]) => v.trim() !== '')
    .map(([currency, v]) => ({ currency, amount: Number(v.replace(',', '.')) || 0 }));

function draftOf(f: F): Draft {
  const opts = f.options.map((o) => ({
    key: o.key,
    label: o.label,
    price: num(o.price),
    unit: o.unit,
    unitsPer: String(o.unitsPer),
    badge: o.badge,
    active: o.active,
    prices: Object.fromEntries(o.prices.map((p) => [p.currency, num(p.amount)])),
  }));
  return {
    title: f.title,
    slug: f.slug,
    category: f.category,
    currency: f.currency,
    description: f.description,
    subtitle: f.subtitle,
    icon: f.icon,
    imageId: f.imageId,
    imageUrl: f.imageUrl,
    options: opts.length ? opts : [blankOpt()],
    allowFree: f.allowFree,
    minAmount: num(f.minAmount),
    minBy: Object.fromEntries(f.minByCurrency.map((p) => [p.currency, num(p.amount)])),
    allowQuantity: f.allowQuantity,
    maxQuantity: num(f.maxQuantity),
    allowRecurring: f.allowRecurring,
    formIds: f.formIds,
    lockPreselect: f.lockPreselect,
    trackItems: f.trackItems,
    fileCompletes: f.fileCompletes,
    labels: Object.fromEntries(f.trackLabels.map((l) => [l.key, l.value])),
    requiresProxy: f.requiresProxy,
    intention: f.intention,
    grouping: f.grouping || 'none',
    meatToNeedy: f.meatToNeedy,
    status: f.status,
    startDate: f.startDate,
    endDate: f.endDate,
    goal: num(f.goal),
  };
}

/** The fields of the cards printed by PHP, as a form body (classic names; a checkbox after its hidden 0 wins). */
function islandFields(root: HTMLElement | null): string {
  const out = new URLSearchParams();
  root?.querySelectorAll<HTMLElement>('.flxdn-islandslot').forEach((slot) => {
    slot.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input[name], select[name], textarea[name]').forEach((el) => {
      if (el.disabled) return;
      if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      if (el instanceof HTMLInputElement && (el.type === 'submit' || el.type === 'button' || el.type === 'file')) return;
      out.append(el.name, el.value);
    });
  });
  return out.toString();
}

export function TypeForm({ id, query, notice }: { id: number; query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const q = useFlxQuery(FlxDonateTypeForm, { id });
  const f = q.data?.flxDonationTypeForm;
  // The islands are printed with the page only: keep the first list (a refetch has none).
  const islands = useRef<F['islands'] | null>(null);
  if (f && islands.current === null) islands.current = f.islands;

  useEffect(() => {
    const n = new URLSearchParams(query).get('notice') ?? '';
    const i = n.indexOf(':');
    if (i > 0) told({ kind: n.slice(0, i) === 'ok' ? 'ok' : 'error', text: n.slice(i + 1) });
    // The notice shows once: a reload of the address does not bring it back.
    if (host.mode === 'wp' && typeof history !== 'undefined') {
      const u = new URL(location.href);
      if (u.searchParams.has('flxb_msg') || u.searchParams.has('flxb_err')) {
        u.searchParams.delete('flxb_msg');
        u.searchParams.delete('flxb_err');
        history.replaceState(history.state, '', u.pathname + u.search);
      }
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const back = useBackButton(f?.listUrl ?? 'admin.php?page=flxb-types', t('Donation types'));
  const title = id ? t('Edit donation type') : t('New donation type');
  if (!q.data) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={title} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  if (!f) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donation type not found')} notice={notice} backButton={back}>
        {null}
      </Screen>
    );
  }
  return <Editor key={f.databaseId} f={f} islands={islands.current ?? []} title={title} back={back} notice={notice} />;
}

function Editor({ f, islands, title, back, notice }: { f: F; islands: F['islands']; title: string; back: ScreenBack | undefined; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [v, setV] = useState<Draft>(() => draftOf(f));
  const [ask, setAsk] = useState(false);
  const root = useRef<HTMLFormElement>(null);
  const has = (k: string) => islands.some((i) => i.id === k);
  const set = <K extends keyof Draft>(k: K, val: Draft[K]) => setV((d) => ({ ...d, [k]: val }));
  const sym = f.currencies.find((c) => c.value === v.currency)?.label.match(/\(([^)]+)\)/)?.[1] ?? v.currency;
  const page = (args: string) => f.listUrl + (f.listUrl.includes('?') ? '&' : '?') + args;

  const save = useFlxMutation(FlxDonationTypeSave, {
    onSuccess: (r) => {
      const res = r.flxDonationTypeSave;
      const err = res?.errors?.[0]?.message;
      if (err || !res?.databaseId) {
        told({ kind: 'error', text: err || t('The action could not be completed.') });
        return;
      }
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTypes) });
      // The cards printed by PHP follow the saved forms, options and currencies only after a reload.
      const keys = (o: Opt[]) => o.map((x) => x.key + '|' + x.label).join(',');
      const moved = !f.databaseId || v.formIds.join(',') !== f.formIds.join(',') || v.currency !== f.currency || keys(v.options) !== keys(draftOf(f).options);
      if (host.mode === 'wp' && islands.length && moved && typeof location !== 'undefined') {
        const extra = res.warning ? '&flxb_msg=warn&flxb_err=' + encodeURIComponent(res.warning) : '&flxb_msg=saved';
        location.assign(page('edit=' + res.databaseId + extra));
        return;
      }
      told(res.warning ? { kind: 'error', text: res.warning } : { kind: 'ok', text: res.message || t('Saved.') });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTypeForm) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const del = useFlxMutation(FlxDonationTypeDelete, {
    onSuccess: (r) => {
      const err = r.flxDonationTypeDelete?.errors?.[0]?.message;
      if (err) {
        told({ kind: 'error', text: err });
        setAsk(false);
        return;
      }
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTypes) });
      if (host.mode === 'wp' && typeof location !== 'undefined') location.assign(page('flxb_msg=deleted'));
      else host.openUrl(f.listUrl);
    },
    onError: (e) => {
      told({ kind: 'error', text: e.message || t('The action could not be completed.') });
      setAsk(false);
    },
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const n = (s: string) => Number(s.replace(',', '.')) || 0;
    save.mutate({
      input: {
        id: f.databaseId,
        title: v.title,
        slug: v.slug,
        category: v.category,
        currency: v.currency,
        description: v.description,
        subtitle: v.subtitle,
        icon: v.icon,
        imageId: v.imageId,
        options: v.options.map((o) => ({
          key: o.key,
          label: o.label,
          price: n(o.price),
          unit: o.unit,
          unitsPer: Math.max(1, Math.round(n(o.unitsPer))),
          badge: o.badge,
          active: o.active,
          prices: amounts(o.prices),
        })),
        allowFree: v.allowFree,
        minAmount: n(v.minAmount),
        minByCurrency: amounts(v.minBy),
        allowQuantity: v.allowQuantity,
        maxQuantity: Math.max(0, Math.round(n(v.maxQuantity))),
        allowRecurring: v.allowRecurring,
        formIds: v.formIds,
        lockPreselect: v.lockPreselect,
        ...(f.trackOn ? { trackItems: v.trackItems, fileCompletes: v.fileCompletes, trackLabels: Object.entries(v.labels).map(([key, value]) => ({ key, value })) } : {}),
        ...(f.kurbanOn ? { requiresProxy: v.requiresProxy, intention: v.intention, grouping: v.grouping, meatToNeedy: v.meatToNeedy } : {}),
        status: v.status,
        startDate: v.startDate,
        endDate: v.endDate,
        goal: n(v.goal),
        extra: islandFields(root.current),
      },
    });
  };

  const opt = (i: number, patch: Partial<Opt>) => setV((d) => ({ ...d, options: d.options.map((o, j) => (j === i ? { ...o, ...patch } : o)) }));
  const units: Choice[] = f.units;

  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={title} notice={notice} backButton={back}>
      <form ref={root} className="flxdn-typeform" onSubmit={submit}>
        <div className="flxdn-grid flxdn-grid--2-1">
          <div className="flxdn-stack">
            <Card title={t('Basics')}>
              <div className="flxdn-form">
                <Field id="flxdn-t-title" label={t('Title')}>
                  <input id="flxdn-t-title" type="text" required maxLength={191} value={v.title} onChange={(e) => set('title', e.target.value)} />
                </Field>
                <div className="flxdn-fields-2">
                  <Field
                    id="flxdn-t-cat"
                    label={t('Category')}
                    help={
                      <Go href={f.categoriesUrl}>{t('Edit categories')}</Go>
                    }
                  >
                    <Select id="flxdn-t-cat" value={v.category} onChange={(x) => set('category', x)} opts={[{ value: '', label: t('No category') }, ...f.categories]} />
                  </Field>
                  <Field id="flxdn-t-slug" label={t('Slug')} help={t('Optional; created from the title.')}>
                    <input id="flxdn-t-slug" type="text" maxLength={191} value={v.slug} onChange={(e) => set('slug', e.target.value)} />
                  </Field>
                </div>
                <Field id="flxdn-t-cur" label={t('Currency')} help={t('Prices and the goal are in this currency. A linked form must charge in the same currency, otherwise its payments are stopped.')}>
                  <Select id="flxdn-t-cur" value={v.currency} onChange={(x) => set('currency', x)} opts={f.currencies} />
                </Field>
                <Field id="flxdn-t-desc" label={t('Short description')}>
                  <textarea id="flxdn-t-desc" rows={3} value={v.description} onChange={(e) => set('description', e.target.value)} />
                </Field>
                <div className="flxdn-fields-2">
                  <Field id="flxdn-t-sub" label={t('Card subtitle')} help={t('One short line under the title on the type cards, e.g. "Sheep or goat".')}>
                    <input id="flxdn-t-sub" type="text" maxLength={80} value={v.subtitle} onChange={(e) => set('subtitle', e.target.value)} />
                  </Field>
                  <Field id="flxdn-t-icon" label={t('Icon')} help={t('Shown when the type has no image.')}>
                    <Select id="flxdn-t-icon" value={v.icon} onChange={(x) => set('icon', x)} opts={f.icons} />
                  </Field>
                </div>
                <Image v={v} setV={setV} />
              </div>
            </Card>

            <Card
              title={t('Prices and options')}
              hint={
                t('One option is a fixed price per unit (e.g. one sapling). Several options give the donor a choice (e.g. adak, akika, şifa). The amount charged is always computed on the server from these prices.') +
                ' ' +
                t('A price of 0 means the price is not announced yet: the option is shown but cannot be paid. An inactive option is hidden and cannot be paid.')
              }
            >
              <div className="flxdn-form">
                <div className="flxdn-repeater">
                  <div className="flxdn-optrows">
                    {v.options.map((o, i) => (
                      <div className="flxdn-optrow flxdn-optrow--full" key={i}>
                        <label className="flxdn-field">
                          <span className="flxdn-label">{t('Option name')}</span>
                          <input type="text" value={o.label} placeholder={t('e.g. Sapling, Adak, Iftar')} onChange={(e) => opt(i, { label: e.target.value })} />
                        </label>
                        <label className="flxdn-field">
                          <span className="flxdn-label">{t('Key')}</span>
                          <input type="text" maxLength={48} pattern="[a-z0-9_\-]*" value={o.key} placeholder={t('from the name')} onChange={(e) => opt(i, { key: e.target.value })} />
                        </label>
                        <label className="flxdn-field">
                          <span className="flxdn-label">
                            {t('Price')} ({sym})
                          </span>
                          <input type="number" min={0} step="0.01" value={o.price} onChange={(e) => opt(i, { price: e.target.value })} />
                        </label>
                        <button type="button" className="flxdn-iconbtn flxdn-iconbtn--danger" aria-label={t('Remove option')} onClick={() => setV((d) => ({ ...d, options: d.options.filter((_, j) => j !== i) }))}>
                          ×
                        </button>
                        <div className="flxdn-optrow__more">
                          <label className="flxdn-field">
                            <span className="flxdn-label">{t('Unit')}</span>
                            <Select value={o.unit} onChange={(x) => opt(i, { unit: x })} opts={[{ value: '', label: t('— not set') }, ...units]} />
                          </label>
                          <label className="flxdn-field">
                            <span className="flxdn-label">{t('Units per choice')}</span>
                            <input type="number" min={1} step={1} value={o.unitsPer} title={t('e.g. a whole animal = 7 shares')} onChange={(e) => opt(i, { unitsPer: e.target.value })} />
                          </label>
                          <label className="flxdn-field">
                            <span className="flxdn-label">{t('Badge')}</span>
                            <Select value={o.badge} onChange={(x) => opt(i, { badge: x })} opts={f.badges} />
                          </label>
                          <Check switch checked={o.active} onChange={(on: boolean) => opt(i, { active: on })}>
                            {t('Active')}
                          </Check>
                        </div>
                        {f.extraCurrencies.length ? (
                          <div className="flxdn-optrow__more flxdn-optrow__cur">
                            {f.extraCurrencies.map((c) => (
                              <label className="flxdn-field" key={c}>
                                <span className="flxdn-label">
                                  {t('Price')} ({c})
                                </span>
                                <input type="number" min={0} step="0.01" value={o.prices[c] ?? ''} placeholder={t('not offered')} onChange={(e) => opt(i, { prices: { ...o.prices, [c]: e.target.value } })} />
                              </label>
                            ))}
                          </div>
                        ) : null}
                      </div>
                    ))}
                  </div>
                  <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={() => setV((d) => ({ ...d, options: [...d.options, blankOpt()] }))}>
                    <Ico name="plus" size={14} />
                    {t('Add option')}
                  </button>
                </div>
                <div className="flxdn-fields-2">
                  <Check switch checked={v.allowFree} onChange={(on: boolean) => set('allowFree', on)}>
                    {t('Free amount allowed')}
                  </Check>
                  <Field id="flxdn-t-min" label={t('Minimum amount') + ' (' + sym + ')'}>
                    <input id="flxdn-t-min" type="number" min={0} step="0.01" value={v.minAmount} onChange={(e) => set('minAmount', e.target.value)} />
                  </Field>
                </div>
                {f.extraCurrencies.length ? (
                  <div className="flxdn-fields-2">
                    {f.extraCurrencies.map((c) => (
                      <Field key={c} id={'flxdn-t-min-' + c} label={t('Minimum amount') + ' (' + c + ')'}>
                        <input id={'flxdn-t-min-' + c} type="number" min={0} step="0.01" value={v.minBy[c] ?? ''} onChange={(e) => set('minBy', { ...v.minBy, [c]: e.target.value })} />
                      </Field>
                    ))}
                  </div>
                ) : null}
                <div className="flxdn-fields-2">
                  <Check switch checked={v.allowQuantity} onChange={(on: boolean) => set('allowQuantity', on)}>
                    {t('Quantity allowed')}
                  </Check>
                  <Field id="flxdn-t-maxq" label={t('Maximum quantity')} help={t('Empty = no limit.')}>
                    <input id="flxdn-t-maxq" type="number" min={0} step={1} value={v.maxQuantity} onChange={(e) => set('maxQuantity', e.target.value)} />
                  </Field>
                </div>
                <Check switch checked={v.allowRecurring} onChange={(on: boolean) => set('allowRecurring', on)}>
                  {t('Recurring giving allowed')}
                </Check>
              </div>
            </Card>

            <Card title={t('Linked forms')} hint={t('Gifts through these Flux Forms forms count for this type. Map each priced choice of the form to an option: the form then charges the price set here.')}>
              <div className="flxdn-form">
                {!f.forms.length ? (
                  <p className="flxdn-muted">{t('No payment form found in Flux Forms yet.')}</p>
                ) : (
                  <>
                    <div className="flxdn-checks">
                      {f.forms.map((fm) => (
                        <Check
                          key={fm.databaseId}
                          className="flxdn-check"
                          checked={v.formIds.includes(fm.databaseId)}
                          onChange={(on: boolean) => set('formIds', on ? [...v.formIds, fm.databaseId] : v.formIds.filter((x) => x !== fm.databaseId))}
                        >
                          {fm.title} <span className="flxdn-muted">#{fm.databaseId}</span>
                          {fm.others ? <span className="flxdn-pill flxdn-pill--pending">{t('also linked to: %s', fm.others)}</span> : null}
                        </Check>
                      ))}
                    </div>
                    {has('map') ? <IslandNode id="map" /> : null}
                    {!f.formIds.length || v.formIds.join(',') !== f.formIds.join(',') ? <p className="flxdn-help">{t('Save after choosing a form to map its choices.')}</p> : null}
                    <Check switch checked={v.lockPreselect} onChange={(on: boolean) => set('lockPreselect', on)}>
                      {t('Hide the choice in the form when it was preselected')}
                    </Check>
                    <p className="flxdn-help">{t('When the page passes a choice (flxb_opt in the address or FLXB.preselect()), the form shows a short summary with a "Change" link instead of the choice list.')}</p>
                  </>
                )}
              </div>
            </Card>

            {f.trackOn ? (
              <Card title={t('Tracking')} hint={t('Each unit of a gift becomes its own item with a 7-digit code: approved, sent to a partner, completed, delivered to the owner, with files and history.')}>
                <div className="flxdn-form">
                  <Check switch checked={v.trackItems || v.requiresProxy} disabled={v.requiresProxy} onChange={(on: boolean) => set('trackItems', on)}>
                    {t('Track every unit one by one')}
                  </Check>
                  {v.requiresProxy ? <p className="flxdn-help">{t('Always on for gifts given on behalf of a person.')}</p> : null}
                  <Check switch checked={v.fileCompletes} onChange={(on: boolean) => set('fileCompletes', on)}>
                    {t('The first file marks it completed')}
                  </Check>
                  <details className="flxdn-details">
                    <summary>{t('Words for this type')}</summary>
                    <div className="flxdn-fields-2">
                      {f.trackLabels.map((l) => (
                        <Field key={l.key} id={'flxdn-tl-' + l.key} label={l.label}>
                          <input
                            id={'flxdn-tl-' + l.key}
                            type="text"
                            maxLength={l.key === 'caption' ? 300 : 80}
                            value={v.labels[l.key] ?? ''}
                            placeholder={l.placeholder}
                            onChange={(e) => set('labels', { ...v.labels, [l.key]: e.target.value })}
                          />
                        </Field>
                      ))}
                    </div>
                  </details>
                </div>
              </Card>
            ) : null}

            {f.kurbanOn ? (
              <Card title={t('On behalf of a person')} hint={t("For types given on behalf of a named person: each unit becomes its own item with that person's name, and the donor must accept the proxy (vekâlet) text.")}>
                <div className="flxdn-form">
                  <Check switch checked={v.requiresProxy} onChange={(on: boolean) => set('requiresProxy', on)}>
                    {t('Proxy (vekâlet) required')}
                  </Check>
                  <div className="flxdn-fields-2">
                    <Field id="flxdn-t-intent" label={t('Intention')}>
                      <Select id="flxdn-t-intent" value={v.intention} onChange={(x) => set('intention', x)} opts={[{ value: '', label: t('None') }, ...f.intentions]} />
                    </Field>
                    <Field id="flxdn-t-group" label={t('Animal')}>
                      <Select
                        id="flxdn-t-group"
                        value={v.grouping}
                        onChange={(x) => set('grouping', x)}
                        opts={[
                          { value: 'none', label: t('None') },
                          { value: 'buyukbas', label: t('Cattle (7 shares)') },
                          { value: 'kucukbas', label: t('Sheep or goat') },
                        ]}
                      />
                    </Field>
                  </div>
                  <Check switch checked={v.meatToNeedy} onChange={(on: boolean) => set('meatToNeedy', on)}>
                    {t('All of the meat goes to people in need')}
                  </Check>
                </div>
              </Card>
            ) : null}

            {has('i18n') ? <IslandNode id="i18n" /> : null}
            {has('addons') ? <IslandNode id="addons" /> : null}
          </div>

          <div className="flxdn-stack">
            <Card title={t('Publishing')}>
              <div className="flxdn-form">
                <Field id="flxdn-t-status" label={t('Status')} help={t('A closed type keeps its history but its forms no longer take payments.')}>
                  <Select
                    id="flxdn-t-status"
                    value={v.status}
                    onChange={(x) => set('status', x as FlxDonationTypeStatus)}
                    opts={[
                      { value: 'ACTIVE', label: t('Open') },
                      { value: 'CLOSED', label: t('Closed') },
                      { value: 'ARCHIVED', label: t('Archived') },
                    ]}
                  />
                </Field>
                <Field id="flxdn-t-start" label={t('Start date')}>
                  <DatePicker id="flxdn-t-start" value={v.startDate} onChange={(x: string) => set('startDate', x)} />
                </Field>
                <Field id="flxdn-t-end" label={t('End date')}>
                  <DatePicker id="flxdn-t-end" value={v.endDate} onChange={(x: string) => set('endDate', x)} />
                </Field>
                <Field id="flxdn-t-goal" label={t('Goal amount') + ' (' + sym + ')'} help={t('Optional. Shown as a progress bar.')}>
                  <input id="flxdn-t-goal" type="number" min={0} step={1} value={v.goal} onChange={(e) => set('goal', e.target.value)} />
                </Field>
                <div className="flxdn-formactions">
                  <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
                    {t('Save')}
                  </button>
                  <Go className="flxdn-btn" href={f.listUrl}>
                    {t('Cancel')}
                  </Go>
                </div>
              </div>
            </Card>
            {f.shortcode ? (
              <Card title={t('Show it on the site')}>
                <p>
                  <code>{f.shortcode}</code>
                </p>
                <p className="flxdn-muted">{t('Add baslik="hayir" to hide the title and description.')}</p>
                {has('preview') ? <IslandNode id="preview" /> : null}
              </Card>
            ) : null}
          </div>
        </div>
      </form>
      {f.databaseId ? (
        f.hasGifts ? (
          <p className="flxdn-muted">{t('This type has gifts, so it can be archived but not deleted.')}</p>
        ) : (
          <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setAsk(true)}>
            {t('Delete this donation type')}
          </button>
        )
      ) : null}
      {ask ? (
        <Modal title={t('Delete this donation type')} onClose={() => !del.isPending && setAsk(false)}>
          <p>{t('Delete this donation type?')}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={del.isPending} onClick={() => setAsk(false)}>
              {t('Close')}
            </button>
            <button type="button" className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid" disabled={del.isPending} onClick={() => del.mutate({ id: f.databaseId })}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </Screen>
  );
}

function Card({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <section className="flxdn-card">
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{title}</h2>
          {hint ? <p className="flxdn-card__hint">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

function Field({ id, label, help, children }: { id: string; label: string; help?: ReactNode; children: ReactNode }) {
  return (
    <div className="flxdn-field">
      <label htmlFor={id}>{label}</label>
      {children}
      {help ? <p className="flxdn-help">{help}</p> : null}
    </div>
  );
}

function Select({ id, value, onChange, opts }: { id?: string; value: string; onChange: (v: string) => void; opts: Choice[] }) {
  const list = opts.some((o) => o.value === value) ? opts : [...opts, { value, label: value }];
  return (
    <select id={id} value={value} onChange={(e) => onChange(e.target.value)}>
      {list.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

type WpMedia = (o: { title: string; multiple: boolean; library: { type: string } }) => {
  on: (ev: string, cb: () => void) => void;
  open: () => void;
  state: () => { get: (k: string) => { first: () => { toJSON: () => { id: number; url: string; sizes?: Record<string, { url: string }> } } } };
};

/** The image: the WordPress media library where it is on the page; elsewhere it can only be removed. */
function Image({ v, setV }: { v: Draft; setV: (fn: (d: Draft) => Draft) => void }) {
  const { t } = useDonate();
  const media = typeof window !== 'undefined' ? (window as { wp?: { media?: WpMedia } }).wp?.media : undefined;
  const pick = () => {
    if (!media) return;
    const fr = media({ title: t('Choose an image'), multiple: false, library: { type: 'image' } });
    fr.on('select', () => {
      const a = fr.state().get('selection').first().toJSON();
      setV((d) => ({ ...d, imageId: a.id, imageUrl: a.sizes?.medium?.url ?? a.url }));
    });
    fr.open();
  };
  return (
    <div className="flxdn-field">
      <span className="flxdn-label">{t('Image')}</span>
      <div className="flxdn-media">
        {v.imageUrl ? <img alt="" src={v.imageUrl} /> : null}
        {media ? (
          <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={pick}>
            {t('Choose image')}
          </button>
        ) : null}
        {v.imageId ? (
          <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setV((d) => ({ ...d, imageId: 0, imageUrl: '' }))}>
            {t('Remove')}
          </button>
        ) : null}
      </div>
    </div>
  );
}
