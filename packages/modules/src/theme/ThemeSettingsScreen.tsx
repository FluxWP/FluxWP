// FluxWP theme › Theme Settings. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and
// write goes through the theme's GraphQL operations (themes/fluxwp/graphql/operations).
// It has everything the classic page has: site visibility (coming soon, synced with WooCommerce),
// appearance, cookie banner, Google Analytics and consent scripts, bank account, payment logos and the
// "migrate from the previous theme" action. The Presets tab stays a WordPress page (Flux Setup).
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxThemeMigrate, FlxThemeMigrationSources, FlxThemePayLogosSave, FlxThemeSettings, FlxThemeSettingsSave } from '@fluxwp/graphql/operations';
import type {
  FlxThemeCookiePosition,
  FlxThemeDarkMode,
  FlxThemeGaMode,
  FlxThemePagination,
  FlxThemePattern,
  FlxThemeSettingsQuery,
  FlxThemeSettingsSaveInput,
  FlxThemeSiteType,
  FlxThemeVisibility,
} from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './theme.css';

type Settings = FlxThemeSettingsQuery['flxThemeSettings'];
type Logo = FlxThemeSettingsQuery['flxThemePayLogos']['current'][number];
type Notice = { kind: 'success' | 'error'; text: string };

/** The fields the screen edits (everything else in the settings object is context). */
const EDITABLE = [
  'visibility',
  'comingSoonTitle',
  'comingSoonText',
  'storeNoticeTitle',
  'storeNoticeText',
  'contactEmail',
  'siteType',
  'pattern',
  'tableHead',
  'newsIntro',
  'darkMode',
  'animations',
  'pagination',
  'activeCard',
  'activeCampaign',
  'cookieEnabled',
  'cookieTitle',
  'cookieText',
  'cookieAccept',
  'cookieReject',
  'cookieMore',
  'cookiePage',
  'cookiePosition',
  'ga4Id',
  'gaMode',
  'consentScripts',
  'bankHolder',
  'bankIban',
] as const;
type EditKey = (typeof EDITABLE)[number];
type Draft = Pick<Settings, EditKey> & { cookieReset: boolean };

/** Server field names of mutation errors → the draft key they belong to. */
const ERROR_KEYS: Record<string, EditKey> = {
  visibility: 'visibility',
  bank_iban: 'bankIban',
  ga4_id: 'ga4Id',
  table_head: 'tableHead',
  contact_email: 'contactEmail',
  consent_scripts: 'consentScripts',
};

interface LogoDraft {
  automatic: boolean;
  list: Logo[];
}

interface WpMedia {
  (opts: unknown): { on: (e: string, cb: () => void) => unknown; off: (e: string) => unknown; open: () => void; state: () => { get: (k: string) => { each: (cb: (a: { toJSON: () => Record<string, unknown> }) => void) => void } } };
}
declare global {
  interface Window {
    wp?: { media?: WpMedia };
  }
}

const draftOf = (s: Settings): Draft => ({ ...(Object.fromEntries(EDITABLE.map((k) => [k, s[k]])) as Pick<Settings, EditKey>), cookieReset: false });

/** IBAN in groups of four, like the classic page. */
const iban = (v: string) => v.replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();

export function ThemeSettingsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <ThemeSettings />
    </FlxGraphQLProvider>
  );
}

function ThemeSettings() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxThemeSettings, {});
  const sources = useFlxQuery(FlxThemeMigrationSources, {}, { retry: false });
  const [draft, setDraft] = useState<Draft | null>(() => (q.data ? draftOf(q.data.flxThemeSettings) : null));
  const [logos, setLogos] = useState<LogoDraft | null>(() => (q.data ? { automatic: q.data.flxThemePayLogos.automatic, list: q.data.flxThemePayLogos.current } : null));
  const [dirty, setDirty] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<EditKey, string>>>({});
  const [notice, setNotice] = useState<Notice | null>(null);

  useEffect(() => {
    if (q.data && !dirty) {
      setDraft(draftOf(q.data.flxThemeSettings));
      setLogos({ automatic: q.data.flxThemePayLogos.automatic, list: q.data.flxThemePayLogos.current });
    }
  }, [q.data, dirty]);

  const fail = (e: unknown) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
  const told = (text: string) => {
    setNotice({ kind: 'success', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };

  const saveLogos = useFlxMutation(FlxThemePayLogosSave, { onError: fail });
  const save = useFlxMutation(FlxThemeSettingsSave, { onError: fail });
  const migrate = useFlxMutation(FlxThemeMigrate, {
    onSuccess: (r) => {
      const p = r.flxThemeMigrate;
      if (!p || p.errors.length) return fail(new Error(p?.errors[0]?.message));
      told(t('Migrated from the previous theme.') + ' ' + p.log.join(' '));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxThemeMigrationSources) });
    },
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft || !logos) return <div className="flxt-app"><div className="flxt-notice is-error">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const s = q.data.flxThemeSettings;
  const pages = q.data.pages?.nodes ?? [];
  const builtin = q.data.flxThemePayLogos.builtin;
  const busy = save.isPending || saveLogos.isPending;
  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => {
    setDraft((old) => old && { ...old, [key]: value });
    setDirty(true);
  };
  const changeLogos = (next: LogoDraft) => {
    setLogos(next);
    setDirty(true);
  };
  const logosChanged = logos.automatic !== q.data.flxThemePayLogos.automatic || logos.list.map((l) => l.key).join() !== q.data.flxThemePayLogos.current.map((l) => l.key).join();
  const err = (key: EditKey) => errors[key];
  const invalid = (key: EditKey) => (errors[key] ? 'is-invalid' : undefined);
  const Err = ({ k }: { k: EditKey }) => (errors[k] ? <p className="flxt-err" role="alert">{errors[k]}</p> : null);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setErrors({});
    setNotice(null);
    const input: Record<string, unknown> = {};
    for (const k of EDITABLE) if (draft[k] !== s[k] && !(k === 'consentScripts' && s.consentScripts === null)) input[k] = draft[k];
    if (draft.cookieReset) input.cookieReset = true;
    try {
      if (Object.keys(input).length) {
        const r = (await save.mutateAsync({ input: input as FlxThemeSettingsSaveInput })).flxThemeSettingsSave;
        if (!r) return fail(null);
        if (r.errors.length) {
          const byKey: Partial<Record<EditKey, string>> = {};
          for (const x of r.errors) {
            const key = x.field ? ERROR_KEYS[x.field] : undefined;
            if (key) byKey[key] = x.message;
          }
          setErrors(byKey);
          return setNotice({ kind: 'error', text: r.errors.map((x) => x.message).join(' ') });
        }
        if (r.settings) qc.setQueryData<FlxThemeSettingsQuery>(flxQueryKey(scope, FlxThemeSettings, {}), (old) => old && { ...old, flxThemeSettings: r.settings as Settings });
      }
      if (logosChanged) {
        const r = (await saveLogos.mutateAsync({ input: logos.automatic ? { automatic: true } : { keys: logos.list.map((l) => l.key) } })).flxThemePayLogosSave;
        if (!r || r.errors.length || !r.logos) return fail(new Error(r?.errors[0]?.message));
        const next = r.logos;
        qc.setQueryData<FlxThemeSettingsQuery>(flxQueryKey(scope, FlxThemeSettings, {}), (old) => old && { ...old, flxThemePayLogos: next });
        setLogos({ automatic: next.automatic, list: next.current });
      }
      setDirty(false);
      told(t('Settings saved.'));
    } catch (x) {
      fail(x);
    }
  };

  const pick = () => {
    const media = window.wp?.media;
    if (!media) return;
    const frame = media({ title: t('Payment logos'), button: { text: t('Add logos') }, library: { type: 'image' }, multiple: true });
    frame.off('select');
    frame.on('select', () => {
      const add: Logo[] = [];
      frame.state().get('selection').each((a) => {
        const o = a.toJSON() as { id: number; alt?: string; title?: string; url: string; sizes?: { medium?: { url: string } } };
        add.push({ key: 'a:' + o.id, label: o.alt || o.title || '', url: o.sizes?.medium?.url ?? o.url, chip: true });
      });
      setLogos((old) => {
        if (!old) return old;
        const have = new Set(old.list.map((l) => l.key));
        return { automatic: false, list: [...old.list, ...add.filter((l) => !have.has(l.key))] };
      });
      setDirty(true);
    });
    frame.open();
  };

  const visible = (...modes: FlxThemeVisibility[]) => modes.includes(draft.visibility);

  return (
    <div className="flxt-app">
      <div className="flxt-head">
        <h1>{t('Theme Settings')}</h1>
        {host.classicUrl && (
          <a className="flxt-classic" href={host.classicUrl}>
            {t('Classic view')}
          </a>
        )}
      </div>
      {s.presetsUrl && (
        <nav className="flxt-tabs" aria-label={t('Theme Settings')}>
          <span className="is-active" aria-current="page">
            {t('General')}
          </span>
          <a href={s.presetsUrl}>{t('Presets')}</a>
        </nav>
      )}
      {notice && (
        <div className={'flxt-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxt-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}

      <form onSubmit={(e) => void submit(e)}>
        <section className="flxt-card" id="flx-visibility">
          <h2>{t('Site visibility')}</h2>
          <div className="flxt-form">
            <div className="flxt-field">
              <span className="flxt-label">{t('Visibility')}</span>
              <fieldset className="flxt-fieldset" style={{ border: 0, margin: 0, padding: 0 }}>
                <legend className="flxt-sr">{t('Visibility')}</legend>
                <Choice name="visibility" value="LIVE" current={draft.visibility} onChange={(v) => set('visibility', v)} title={t('Live')} desc={t('Everyone can see the whole site.')} />
                <Choice name="visibility" value="COMING_SOON" current={draft.visibility} onChange={(v) => set('visibility', v)} title={t('Coming soon: entire site')} desc={t('Visitors, including signed-in customers, only see the coming-soon page.')} />
                <Choice
                  name="visibility"
                  value="STORE"
                  current={draft.visibility}
                  onChange={(v) => set('visibility', v)}
                  disabled={!s.woocommerceActive}
                  title={t('Coming soon: store pages only')}
                  desc={s.woocommerceActive ? t('The site stays open. Shop, product, cart and checkout pages show a coming-soon notice inside the normal header and footer.') : t('Requires WooCommerce.')}
                />
                <p className="flxt-desc">
                  {s.woocommerceActive
                    ? t('Administrators, shop managers and editors keep seeing the site. This is the same setting as WooCommerce › Settings › Site visibility; changing either one updates the other, and WooCommerce private preview links keep working.')
                    : t('Administrators and editors keep seeing the site.')}
                </p>
                <Err k="visibility" />
              </fieldset>
            </div>
            {visible('COMING_SOON') && (
              <>
                <Field id="flxt-soon-title" label={t('Coming-soon title')}>
                  <input id="flxt-soon-title" type="text" value={draft.comingSoonTitle} placeholder={t('Something good is on its way.')} onChange={(e) => set('comingSoonTitle', e.currentTarget.value)} />
                </Field>
                <Field id="flxt-soon-text" label={t('Coming-soon message')}>
                  <textarea id="flxt-soon-text" rows={3} value={draft.comingSoonText} placeholder={t("We're putting the finishing touches on our website. Please check back soon.")} onChange={(e) => set('comingSoonText', e.currentTarget.value)} />
                  <p className="flxt-desc">{t('Leave the title and message empty to use the translated defaults.')}</p>
                </Field>
              </>
            )}
            {visible('STORE') && (
              <>
                <Field id="flxt-store-title" label={t('Store notice title')}>
                  <input id="flxt-store-title" type="text" value={draft.storeNoticeTitle} placeholder={s.storeNoticeTitleDefault} onChange={(e) => set('storeNoticeTitle', e.currentTarget.value)} />
                </Field>
                <Field id="flxt-store-text" label={t('Store notice message')}>
                  <textarea id="flxt-store-text" rows={3} value={draft.storeNoticeText} placeholder={s.storeNoticeTextDefault} onChange={(e) => set('storeNoticeText', e.currentTarget.value)} />
                  <p className="flxt-desc">{t('Leave the title and message empty to use the translated defaults. The wording follows the site type (shop or donations).')}</p>
                </Field>
              </>
            )}
            {!visible('LIVE') && (
              <Field id="flxt-soon-email" label={t('Public contact email')}>
                <input id="flxt-soon-email" type="email" className={invalid('contactEmail')} value={draft.contactEmail} onChange={(e) => set('contactEmail', e.currentTarget.value)} />
                <p className="flxt-desc">{t('Optional. Only this address is shown; the administrator email stays private.')}</p>
                <Err k="contactEmail" />
              </Field>
            )}
          </div>
        </section>

        <section className="flxt-card">
          <h2>{t('Appearance')}</h2>
          <div className="flxt-form">
            <Field label={t('Site type')}>
              <Choice name="siteType" value="SHOP" current={draft.siteType} onChange={(v: FlxThemeSiteType) => set('siteType', v)} title={t('Shop: Orders, Return to shop')} />
              <Choice name="siteType" value="DONATION" current={draft.siteType} onChange={(v: FlxThemeSiteType) => set('siteType', v)} title={t('Donation: Donations, Donate button, return to the donation page')} />
              <p className="flxt-desc">{t('Sets the wording in My account, the cart and the mobile menu.')}</p>
            </Field>
            <Field id="flxt-pattern" label={t('Background pattern')}>
              <select id="flxt-pattern" value={draft.pattern} onChange={(e) => set('pattern', e.currentTarget.value as FlxThemePattern)}>
                <option value="DOTS">{t('Dots')}</option>
                <option value="LEAVES">{t('Leaves')}</option>
                <option value="NONE">{t('None')}</option>
              </select>
            </Field>
            <Field id="flxt-table-head" label={t('Table header background')}>
              <div className="flxt-color">
                <input type="color" aria-label={t('Table header background')} value={/^#[0-9a-f]{6}$/i.test(draft.tableHead) ? draft.tableHead : '#F1F2F4'} onChange={(e) => set('tableHead', e.currentTarget.value.toUpperCase())} />
                <input id="flxt-table-head" type="text" className={invalid('tableHead')} placeholder="#F1F2F4" value={draft.tableHead} onChange={(e) => set('tableHead', e.currentTarget.value)} />
              </div>
              <p className="flxt-desc">{t('Header cells of tables in pages, Wiki and WooCommerce. Pick a tone slightly darker than the page background. Leave empty to use the style colour.')}</p>
              <Err k="tableHead" />
            </Field>
            <Field id="flxt-news" label={t('News introduction')}>
              <input id="flxt-news" type="text" value={draft.newsIntro} placeholder={t('Latest news, updates and articles.')} onChange={(e) => set('newsIntro', e.currentTarget.value)} />
            </Field>
            <Field label={t('Dark mode')}>
              <Choice name="darkMode" value="AUTO" current={draft.darkMode} onChange={(v: FlxThemeDarkMode) => set('darkMode', v)} title={t('Automatic: the site is dark when the device is in dark mode')} />
              <Choice name="darkMode" value="OFF" current={draft.darkMode} onChange={(v: FlxThemeDarkMode) => set('darkMode', v)} title={t('Off: the site is always light')} />
              <p className="flxt-desc">{t('Either way, browser extensions (Dark Reader, Noir etc.) are stopped from darkening the site again.')}</p>
            </Field>
            <Field label={t('Scroll animations')}>
              <label className="flxt-choice">
                <input type="checkbox" checked={draft.animations} onChange={(e) => set('animations', e.currentTarget.checked)} />
                {t('Headings, cards and images fade in gently as the page is scrolled')}
              </label>
              <p className="flxt-desc">{t('Visitors whose device asks to reduce motion do not see animations.')}</p>
            </Field>
            <Field label={t('List pages')}>
              <Choice name="pagination" value="INFINITE" current={draft.pagination} onChange={(v: FlxThemePagination) => set('pagination', v)} title={t('Infinite scroll: the next posts load by themselves as you scroll down')} />
              <Choice name="pagination" value="NUMBERED" current={draft.pagination} onChange={(v: FlxThemePagination) => set('pagination', v)} title={t('Page numbers (1 2 3 … Next)')} />
              <p className="flxt-desc">{t('News, category, tag, search and archive pages. With infinite scroll the page links stay in the background (for Google and visitors without JavaScript).')}</p>
            </Field>
            {s.activeCardAvailable && (
              <Field label={t('Home page: active project card')}>
                <label className="flxt-choice">
                  <input type="checkbox" checked={draft.activeCard} onChange={(e) => set('activeCard', e.currentTarget.checked)} />
                  {t('Show the latest ongoing project with a donate button under the slider')}
                </label>
                {s.campaigns.length > 0 && (
                  <p>
                    <label>
                      {t('Donation progress')}{' '}
                      <select value={draft.activeCampaign} onChange={(e) => set('activeCampaign', Number(e.currentTarget.value))}>
                        <option value={0}>{t('Automatic (first open donation type with a goal)')}</option>
                        {s.campaigns.map((c) => (
                          <option key={c.databaseId} value={c.databaseId}>
                            {c.title}
                          </option>
                        ))}
                      </select>
                    </label>
                  </p>
                )}
                <p className="flxt-desc">{t("The project's image, title and summary come from the project itself.")}</p>
              </Field>
            )}
          </div>
        </section>

        <section className="flxt-card">
          <h2>{t('Cookie banner')}</h2>
          <div className="flxt-form">
            <Field label={t('Status')}>
              <label className="flxt-choice">
                <input type="checkbox" checked={draft.cookieEnabled} onChange={(e) => set('cookieEnabled', e.currentTarget.checked)} />
                {t('Show the cookie banner on the first visit')}
              </label>
            </Field>
            <Field id="flxt-ct" label={t('Title')}>
              <input id="flxt-ct" type="text" value={draft.cookieTitle} onChange={(e) => set('cookieTitle', e.currentTarget.value)} />
            </Field>
            <Field id="flxt-cx" label={t('Text')}>
              <textarea id="flxt-cx" rows={3} value={draft.cookieText} onChange={(e) => set('cookieText', e.currentTarget.value)} />
            </Field>
            <Field label={t('Buttons')}>
              <div className="flxt-inline">
                <input type="text" aria-label={t('Accept button')} value={draft.cookieAccept} onChange={(e) => set('cookieAccept', e.currentTarget.value)} />
                <input type="text" aria-label={t('Reject button')} value={draft.cookieReject} onChange={(e) => set('cookieReject', e.currentTarget.value)} />
                <input type="text" aria-label={t('Policy link')} value={draft.cookieMore} onChange={(e) => set('cookieMore', e.currentTarget.value)} />
              </div>
              <p className="flxt-desc">{t('Accept · Reject · Policy link texts.')}</p>
            </Field>
            <Field id="flxt-cp" label={t('Cookie policy page')}>
              <select id="flxt-cp" value={draft.cookiePage} onChange={(e) => set('cookiePage', Number(e.currentTarget.value))}>
                <option value={0}>{t('Automatic (/cerez-politikasi/)')}</option>
                {draft.cookiePage > 0 && !pages.some((p) => p.databaseId === draft.cookiePage) && <option value={draft.cookiePage}>{'#' + draft.cookiePage}</option>}
                {pages.map((p) => (
                  <option key={p.databaseId} value={p.databaseId}>
                    {p.title || '#' + p.databaseId}
                  </option>
                ))}
              </select>
            </Field>
            <Field label={t('Layout')}>
              <Choice name="cookiePosition" value="CARD" current={draft.cookiePosition} onChange={(v: FlxThemeCookiePosition) => set('cookiePosition', v)} title={t('Card at the bottom left')} />
              <Choice name="cookiePosition" value="BAR" current={draft.cookiePosition} onChange={(v: FlxThemeCookiePosition) => set('cookiePosition', v)} title={t('Full-width bar at the bottom')} />
            </Field>
            <Field label={t('Reset consents')}>
              <label className="flxt-choice">
                <input type="checkbox" checked={draft.cookieReset} onChange={(e) => set('cookieReset', e.currentTarget.checked)} />
                {t('Show the banner to everyone again after saving')}
              </label>
              <span className="flxt-desc">{t('(when the policy changes; the current version is %s)', s.cookieRevision)}</span>
            </Field>
          </div>
        </section>

        <section className="flxt-card">
          <h2>{t('Google Analytics and consent code')}</h2>
          <p>{t('The items below load only after the visitor says “%s” (Google Consent Mode v2 default: denied). With the banner off they load directly.', draft.cookieAccept)}</p>
          <div className="flxt-form">
            <Field id="flxt-ga" label={t('GA4 measurement ID')}>
              <input id="flxt-ga" type="text" className={'flxt-code ' + (invalid('ga4Id') ?? '')} placeholder="G-XXXXXXXXXX" value={draft.ga4Id} onChange={(e) => set('ga4Id', e.currentTarget.value)} />
              <p className="flxt-desc">{t('Google Analytics › Admin › Data streams › Measurement ID.')}</p>
              <Err k="ga4Id" />
            </Field>
            <Field label={t('Loading')}>
              <Choice name="gaMode" value="CONSENT" current={draft.gaMode} onChange={(v: FlxThemeGaMode) => set('gaMode', v)} title={t('Google tag in the head of every page, cookie-free until consent (Google Consent Mode v2, recommended; Google’s tag test sees it)')} />
              <Choice name="gaMode" value="AFTER" current={draft.gaMode} onChange={(v: FlxThemeGaMode) => set('gaMode', v)} title={t('Load the tag only after “Accept” (Google’s tag test cannot find it)')} />
            </Field>
            <Field id="flxt-cs" label={t('Extra code')}>
              <textarea
                id="flxt-cs"
                rows={6}
                className={'flxt-code ' + (invalid('consentScripts') ?? '')}
                disabled={!s.canEditScripts}
                placeholder="<script>...</script> (Meta Pixel, Hotjar, GTM …)"
                value={draft.consentScripts ?? ''}
                onChange={(e) => set('consentScripts', e.currentTarget.value)}
              />
              <p className="flxt-desc">{t('Added to the page after consent.')}</p>
              <Err k="consentScripts" />
            </Field>
          </div>
        </section>

        <section className="flxt-card">
          <h2>{t('Bank account (transfer/EFT)')}</h2>
          <p>
            {t('Fallback account: the IBAN is read from WooCommerce › Settings › Payments › Transfer (donation) first, then from Direct bank transfer; if both are empty this account is used (donation page, checkout page, Call Center).')}
            {s.bankInUse && <strong> {t('In use now: %s', s.bankInUse)}</strong>}
          </p>
          <div className="flxt-form">
            <Field id="flxt-bh" label={t('Account holder')}>
              <input id="flxt-bh" type="text" value={draft.bankHolder} onChange={(e) => set('bankHolder', e.currentTarget.value)} />
            </Field>
            <Field id="flxt-bi" label={t('TRY IBAN')}>
              <input id="flxt-bi" type="text" className={'flxt-code ' + (invalid('bankIban') ?? '')} value={iban(draft.bankIban)} onChange={(e) => set('bankIban', e.currentTarget.value)} />
              <Err k="bankIban" />
            </Field>
          </div>
        </section>

        <section className="flxt-card" id="flx-odeme-logolari">
          <h2>{t('Payment logos')}</h2>
          <p>{t('Logos that virtual POS providers (iyzico, EsnekPOS, etc.) want on the site. Shown in the footer; add them anywhere else with the [flx_odeme_logolari] shortcode.')}</p>
          <div className="flxt-logos" aria-live="polite">
            {logos.list.length === 0 && <span className="flxt-logos-empty">{t('No logos: the footer shows no payment logos.')}</span>}
            {logos.list.map((l, i) => (
              <span key={l.key} className={'flxt-logo' + (l.chip ? ' is-chip' : '')}>
                <img src={l.url} alt={l.label} title={l.label} />
                {i > 0 && (
                  <button
                    type="button"
                    aria-label={t('Move left') + ': ' + l.label}
                    onClick={() => changeLogos({ automatic: false, list: logos.list.map((x, j) => (j === i - 1 ? logos.list[i] : j === i ? logos.list[i - 1] : x)).filter((x): x is Logo => !!x) })}
                  >
                    ‹
                  </button>
                )}
                <button type="button" aria-label={t('Remove') + ': ' + l.label} onClick={() => changeLogos({ automatic: false, list: logos.list.filter((x) => x.key !== l.key) })}>
                  ×
                </button>
              </span>
            ))}
          </div>
          <div className="flxt-logos-actions">
            {host.mode === 'wp' && window.wp?.media && (
              <button type="button" className="flxt-btn" onClick={pick}>
                {t('Upload logo')}
              </button>
            )}
            <select
              aria-label={t('Built-in logo')}
              value=""
              onChange={(e) => {
                const b = builtin.find((x) => x.key === e.currentTarget.value);
                if (b && !logos.list.some((x) => x.key === b.key)) changeLogos({ automatic: false, list: [...logos.list, b] });
              }}
            >
              <option value="">{t('Add a built-in logo…')}</option>
              {builtin.map((b) => (
                <option key={b.key} value={b.key}>
                  {b.label}
                </option>
              ))}
            </select>
            <label>
              <input
                type="checkbox"
                checked={logos.automatic}
                onChange={(e) => (e.currentTarget.checked ? changeLogos({ automatic: true, list: q.data.flxThemePayLogos.automaticList }) : changeLogos({ ...logos, automatic: false }))}
              />{' '}
              {t('Back to automatic')}
            </label>
          </div>
          <p className="flxt-desc">
            {logos.automatic
              ? t('Automatic now: the iyzico band and the logos of enabled virtual POS plugins. Once you add or remove a logo, the list is your own choice.')
              : t('Hand-picked list. "Back to automatic" returns to the iyzico band and the logos of enabled virtual POS plugins.')}{' '}
            {t('SVG or transparent PNG is best; colored logos are shown in a white box on a dark footer.')}
          </p>
        </section>

        <div className="flxt-actions">
          <button type="submit" className="flxt-btn is-primary" disabled={busy}>
            {t('Save settings')}
          </button>
          {dirty && <span className="flxt-dirty">{t('Unsaved changes')}</span>}
        </div>
      </form>

      {(sources.data?.flxThemeMigrationSources.length ?? 0) > 0 && (
        <section className="flxt-card">
          <h2>{t('Migrate from the previous theme')}</h2>
          <p>{t("Copies the previous theme's settings (logo, menus, additional CSS) and the templates, parts and styles you saved in the Site Editor to this theme. The previous theme is left untouched; records with the same name in this theme are not overwritten.")}</p>
          <div className="flxt-inline">
            {sources.data?.flxThemeMigrationSources.map((m) => (
              <button key={m.stylesheet} type="button" className="flxt-btn" disabled={migrate.isPending} onClick={() => migrate.mutate({ input: { source: m.stylesheet } })}>
                {m.lastRun ? t('Migrate from %1$s again (last: %2$s)', m.stylesheet, m.lastRun.label) : t('Migrate from %s', m.stylesheet)}
              </button>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}

function Field({ id, label, children }: { id?: string; label: string; children: ReactNode }) {
  return (
    <div className="flxt-field">
      {id ? <label htmlFor={id}>{label}</label> : <span className="flxt-label">{label}</span>}
      <div>{children}</div>
    </div>
  );
}

function Choice<V extends string>({ name, value, current, onChange, title, desc, disabled }: { name: string; value: V; current: string; onChange: (v: V) => void; title: string; desc?: string; disabled?: boolean }) {
  return (
    <label className={'flxt-choice' + (disabled ? ' is-disabled' : '')}>
      <input type="radio" name={name} value={value} checked={current === value} disabled={disabled} onChange={() => onChange(value)} />
      <strong>{title}</strong>
      {desc && <span className="flxt-desc">{desc}</span>}
    </label>
  );
}

export type { Translate };
