// Flux SEO. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the
// Flux SEO GraphQL operations (plugins/flx-seo/graphql/operations). It is the only admin page (no classic tabs):
// General (site name, titles, search engines, IndexNow, indexing, images), Organization and social,
// SEO check (content recommendations), Site audit (on-site + PageSpeed, agent readiness, recommendations
// with one-click fixes, report history) and Settings (PageSpeed key, agents, security headers, schedule, guide).
import { Fragment, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Chart } from '@fluxwp/ui/chart';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSeoAuditRun, FlxSeoAuditScreen, FlxSeoContentCheck, FlxSeoFixApply, FlxSeoGuide, FlxSeoIndexNowSubmit, FlxSeoPsiKeySave, FlxSeoSettingsSave, FlxSeoSettingsScreen } from '@fluxwp/graphql/operations';
import type { FlxSeoAuditScreenQuery, FlxSeoGuideLang, FlxSeoPriority, FlxSeoSettingsFieldsFragment, FlxSeoSettingsScreenQuery, FlxSeoStrategy, FlxSettingsFlxseoInput } from '@fluxwp/graphql/types';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './seo.css';

type Tab = 'genel' | 'kurum' | 'kontrol' | 'denetim' | 'ayarlar';
type Notice = { kind: 'success' | 'error'; text: string };
type Settings = FlxSeoSettingsFieldsFragment;
type SettingsKey = keyof Settings;
type Setup = FlxSeoSettingsScreenQuery['flxSeoSetup'];
type AuditData = FlxSeoAuditScreenQuery;
type Finding = AuditData['flxSeoAudit']['findings'][number];
type HistoryEntry = NonNullable<AuditData['flxSeoAuditHistory']>['entries'][number];

const TABS: Tab[] = ['genel', 'kurum', 'kontrol', 'denetim', 'ayarlar'];
const PRIORITIES: FlxSeoPriority[] = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
const CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo', 'agentic'];

const isTab = (v: string | undefined): v is Tab => TABS.includes(v as Tab);

export function SeoScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Seo />
    </FlxGraphQLProvider>
  );
}

function Seo() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const [tab, setTab] = useState<Tab>(isTab(host.startPage) ? host.startPage : 'genel');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [dirty, setDirty] = useState(false);

  const labels: Record<Tab, string> = {
    genel: t('General'),
    kurum: t('Organization and social'),
    kontrol: t('SEO check'),
    denetim: t('Site audit'),
    ayarlar: t('Settings'),
  };
  const go = (next: Tab) => {
    setTab(next);
    setNotice(null);
    try {
      const u = new URL(window.location.href);
      if (u.searchParams.get('page') === 'flxseo') {
        if (next === 'genel') u.searchParams.delete('tab');
        else u.searchParams.set('tab', next);
        window.history.replaceState(null, '', u.toString());
      }
    } catch {
      /* the address bar is only a convenience */
    }
  };

  return (
    <Screen className="flxseo-app" icon={host.appIcon} title={t('SEO')} subtitle={t('Meta tags, schema, sitemap and SEO audit')} tabs={TABS.map((k) => ({ id: k, label: labels[k] }))} tab={tab} onTab={go}>
      {notice && (
        <div className={'flxseo-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxseo-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      {tab === 'kontrol' ? <ContentCheck t={t} /> : tab === 'denetim' ? <Audit t={t} onNotice={setNotice} /> : <SettingsTabs tab={tab} t={t} onNotice={setNotice} dirty={dirty} setDirty={setDirty} />}
    </Screen>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * Shared bits
 * ------------------------------------------------------------------------------------------------------- */

function Check({ checked, onChange, label }: { checked: boolean; onChange: (on: boolean) => void; label: ReactNode }) {
  return (
    <label>
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.currentTarget.checked)} /> {label}
    </label>
  );
}

const errText = (e: unknown, t: Translate) => (e instanceof Error && e.message ? e.message : t('The action could not be completed.'));

/* ---------------------------------------------------------------------------------------------------------
 * General, Organization and Settings tabs: the `flxseo` option
 * ------------------------------------------------------------------------------------------------------- */

const s = (v: unknown): string => (typeof v === 'string' ? v : '');
const b = (v: unknown): boolean => v === true;

function SettingsTabs({ tab, t, onNotice, dirty, setDirty }: { tab: Tab; t: Translate; onNotice: (n: Notice | null) => void; dirty: boolean; setDirty: (d: boolean) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxSeoSettingsScreen, {});
  const [draft, setDraft] = useState<Settings | null>(() => q.data?.flxSettings.flxseo ?? null);
  const [psiKey, setPsiKey] = useState('');
  const [psiClear, setPsiClear] = useState(false);

  useEffect(() => {
    if (q.data?.flxSettings.flxseo && !dirty) setDraft(q.data.flxSettings.flxseo);
  }, [q.data, dirty]);

  const key = flxQueryKey(scope, FlxSeoSettingsScreen, {});
  const told = (text: string) => {
    onNotice({ kind: 'success', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };
  const fail = (text: string) => {
    onNotice({ kind: 'error', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };

  const save = useFlxMutation(FlxSeoSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxSettingsUpdate;
      if (!p) return fail(t('The action could not be completed.'));
      if (p.errors.length) return fail(p.errors.map((e) => e.message).join(' '));
      const next = p.settings?.flxseo;
      if (next) {
        qc.setQueryData<FlxSeoSettingsScreenQuery>(key, (old) => old && { ...old, flxSettings: { ...old.flxSettings, flxseo: next } });
        setDraft(next);
      }
      setDirty(false);
      void qc.invalidateQueries({ queryKey: key });
      told(t('Saved.'));
    },
    onError: (e) => fail(errText(e, t)),
  });
  const psi = useFlxMutation(FlxSeoPsiKeySave, {
    onSuccess: (r) => {
      const p = r.flxSeoPsiKeySave;
      if (!p || p.errors.length) return fail(p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.'));
      setPsiKey('');
      setPsiClear(false);
      void qc.invalidateQueries({ queryKey: key });
    },
    onError: (e) => fail(errText(e, t)),
  });
  const indexnow = useFlxMutation(FlxSeoIndexNowSubmit, {
    onSuccess: (r) => {
      const p = r.flxSeoIndexNowSubmit;
      if (!p || p.errors.length) return fail(p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.'));
      if (p.setup) qc.setQueryData<FlxSeoSettingsScreenQuery>(key, (old) => old && { ...old, flxSeoSetup: p.setup as Setup });
      told(t('%s addresses sent to IndexNow.', p.submitted ?? 0));
    },
    onError: (e) => fail(errText(e, t)),
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  const server = q.data?.flxSettings.flxseo;
  if (q.isError || !q.data || !draft || !server) return <div className="flxseo-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;

  const setup = q.data.flxSeoSetup;
  const set = <K extends SettingsKey>(k: K, v: Settings[K]) => {
    setDraft((o) => o && { ...o, [k]: v });
    setDirty(true);
  };
  const text = (k: SettingsKey) => s(draft[k]);
  const on = (k: SettingsKey) => b(draft[k]);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const changed: Record<string, unknown> = {};
    for (const k of Object.keys(draft) as SettingsKey[]) {
      if (draft[k] === server[k]) continue;
      changed[k] = draft[k];
    }
    if (Object.keys(changed).length) save.mutate({ flxseo: changed as FlxSettingsFlxseoInput });
    else if (!psiKey && !psiClear) told(t('Nothing to save.'));
    if (tab === 'ayarlar' && (psiKey.trim() || psiClear)) psi.mutate({ input: { key: psiKey.trim(), clear: psiClear } });
  };
  const busy = save.isPending || psi.isPending;
  const verifyPaste = (k: SettingsKey, raw: string) => {
    // A pasted <meta> tag becomes its content value (the kit strips tags, so it is done here first).
    const m = /content=["']([^"']+)["']/.exec(raw);
    set(k, (m ? m[1] : raw) as Settings[typeof k]);
  };

  const classicSubmit = (
    <p className="submit">
      <button type="submit" className="button button-primary" disabled={busy}>
        {t('Save Changes')}
      </button>
    </p>
  );

  return (
    <form onSubmit={submit}>
      {tab === 'genel' && (
        <div className="flx-raw flxseo-classic">
          {setup.otherPlugin && (
            <div className="notice notice-warning inline">
              <p>{t("Another SEO plugin is active. To avoid conflicts this plugin's meta and schema output is off; favicon, SEO check and AI tools keep working.")}</p>
            </div>
          )}
          {!setup.aiReady && (
            <div className="notice notice-info inline">
              <p>{t("Flux SEO's AI (MCP) tools run through the shared MCP server. The server, keys and settings are in Flux AI & MCP › MCP; install and activate Flux AI 0.7.0 or later.")}</p>
            </div>
          )}
          <table className="form-table" role="presentation">
            <tbody>
              <tr>
                <th scope="row">{t('SEO output')}</th>
                <td>
                  <Check checked={on('enabled')} onChange={(v) => set('enabled', v)} label={t('Add meta tags, Open Graph and schema')} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-sn">{t('Site name shown in Google')}</label>
                </th>
                <td>
                  <input id="ys-sn" type="text" className="regular-text" value={text('siteName')} placeholder={setup.blogName} onChange={(e) => set('siteName', e.currentTarget.value)} />
                  <p className="description">
                    {t('Search results show this name instead of the domain (WebSite schema). Pick a short, well-known name. A change shows in Google after it crawls again.')} {t('Alternative:')}{' '}
                    <input type="text" className="regular-text" aria-label={t('Alternative name')} value={text('altName')} onChange={(e) => set('altName', e.currentTarget.value)} />
                  </p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-sep">{t('Title separator')}</label>
                </th>
                <td>
                  <input id="ys-sep" type="text" className="small-text" value={text('sep')} onChange={(e) => set('sep', e.currentTarget.value)} />{' '}
                  <span className="description">{t('Example: %s', t('About') + ' ' + text('sep') + ' ' + text('altName'))}</span>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-ht">{t('Home page title')}</label>
                </th>
                <td>
                  <input id="ys-ht" type="text" className="large-text" maxLength={70} value={text('homeTitle')} placeholder={setup.homeTitle} onChange={(e) => set('homeTitle', e.currentTarget.value)} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-hd">{t('Home page description')}</label>
                </th>
                <td>
                  <textarea id="ys-hd" className="large-text" rows={3} maxLength={320} value={text('homeDesc')} placeholder={setup.homeDesc} onChange={(e) => set('homeDesc', e.currentTarget.value)} />
                  <p className="description">{t('150–160 characters is ideal. When empty, the site tagline is used.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-og">{t('Default share image')}</label>
                </th>
                <td>
                  <input id="ys-og" type="text" className="large-text code" value={text('ogImage')} placeholder={setup.imageDefault} onChange={(e) => set('ogImage', e.currentTarget.value)} />
                  <p className="description">{t("Used on pages without a featured image. 1200×630 JPG/PNG. When empty, the plugin's image.")}</p>
                </td>
              </tr>
              <tr id="search-engines">
                <th scope="row">{t('Search engines')}</th>
                <td>
                  <p className="description">
                    {t(
                      "Search engines other than Google (Bing, Yandex, DuckDuckGo, Yahoo, Ecosia) crawl a site quickly only once it is introduced to them. Add the site in each one's webmaster panel, paste the verification code below and submit the sitemap URL.",
                    )}
                  </p>
                  <p>
                    <label htmlFor="flxseo-sitemap-url">
                      <strong>{t('Sitemap URL')}</strong>
                    </label>
                    <br />
                    <input id="flxseo-sitemap-url" type="text" className="regular-text code" readOnly value={setup.sitemapUrl} onClick={(e) => e.currentTarget.select()} />{' '}
                    <span className="description">{t('Also listed in robots.txt; add this URL in Search Console, Bing and Yandex.')}</span>
                  </p>
                  <table className="widefat striped" style={{ maxWidth: 760, marginTop: 8 }}>
                    <tbody>
                      {setup.engines.map((e) => {
                        const f = e.field as SettingsKey;
                        return (
                          <tr key={e.key}>
                            <td style={{ width: 180 }}>
                              <label htmlFor={'flxseo-' + e.key}>
                                <strong>{e.name}</strong>
                              </label>
                              <br />
                              <a href={e.url} target="_blank" rel="noopener noreferrer">
                                {e.link} ↗
                              </a>
                            </td>
                            <td>
                              <input id={'flxseo-' + e.key} type="text" className="regular-text code" value={text(f)} placeholder={e.placeholder} onChange={(ev) => verifyPaste(f, ev.currentTarget.value)} />
                              {text(f) ? <span className="dashicons dashicons-yes-alt" style={{ color: '#008a20' }} title={t('Verification tag is on the site')}></span> : null}
                              {e.key === 'verify_google' && setup.sitekit ? (
                                <p>
                                  <span className="dashicons dashicons-yes-alt" style={{ color: '#008a20' }}></span>{' '}
                                  {t('Connected with Google Site Kit; Search Console verification and the sitemap are handled there. You can leave this empty.')}
                                </p>
                              ) : null}
                              <p className="description">{e.help}</p>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </td>
              </tr>
              <tr id="indexnow">
                <th scope="row">IndexNow</th>
                <td>
                  <p>
                    <Check checked={on('indexnow')} onChange={(v) => set('indexnow', v)} label={t('Instantly notify Bing, Yandex, DuckDuckGo (via Bing), Seznam and Naver about published and updated pages')} />
                  </p>
                  <p className="description">
                    {t('Key file:')}{' '}
                    <a href={setup.indexnowUrl} target="_blank" rel="noopener noreferrer">
                      {setup.indexnowKey}.txt
                    </a>
                    . {t('When on, the whole site is submitted once automatically. Google does not use IndexNow; add your sitemap in Search Console for Google.')}
                    {!setup.sitePublic && (
                      <>
                        <br />
                        <strong>{t('The site is hidden from search engines (Settings › Reading); IndexNow sends nothing.')}</strong>
                      </>
                    )}
                    {setup.indexnowLast && (
                      <>
                        <br />
                        {t('Last bulk submission:')} {setup.indexnowLast}
                      </>
                    )}
                  </p>
                  <p>
                    <button type="button" className="button" disabled={indexnow.isPending} onClick={() => indexnow.mutate({ input: {} })}>
                      {t('Submit the whole site now')}
                    </button>
                  </p>
                </td>
              </tr>
              <tr>
                <th scope="row">{t('Indexing')}</th>
                <td>
                  <p>
                    <Check checked={on('noindexShop')} onChange={(v) => set('noindexShop', v)} label={t('Keep cart, checkout and My Account pages out of the index')} />
                  </p>
                  <p>
                    <Check checked={on('noindexSearch')} onChange={(v) => set('noindexSearch', v)} label={t('Keep on-site search results out of the index')} />
                  </p>
                  <p>
                    <Check checked={on('noindexAuthor')} onChange={(v) => set('noindexAuthor', v)} label={t('Turn off author pages (redirect to the home page, remove from the sitemap)')} />
                  </p>
                  <p>
                    <Check checked={on('noindexTags')} onChange={(v) => set('noindexTags', v)} label={t('Keep tag pages out of the index (also removed from the sitemap; categories stay open)')} />
                  </p>
                  <p className="description">{t('RSS feeds are always served with a "noindex" header; they should not show up as pages in Google.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">{t('Images')}</th>
                <td>
                  <Check checked={on('autoAlt')} onChange={(v) => set('autoAlt', v)} label={t('Give images with empty alt text the media library alt text, or else the file name')} />
                </td>
              </tr>
            </tbody>
          </table>
          {classicSubmit}
        </div>
      )}

      {tab === 'kurum' && (
        <div className="flx-raw flxseo-classic">
          <table className="form-table" role="presentation">
            <tbody>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-on">{t('Organization name')}</label>
                </th>
                <td>
                  <input id="ys-on" type="text" className="large-text" value={text('orgName')} onChange={(e) => set('orgName', e.currentTarget.value)} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-ot">{t('Organization type')}</label>
                </th>
                <td>
                  <select id="ys-ot" value={draft.orgType ?? 'ORGANIZATION'} onChange={(e) => set('orgType', e.currentTarget.value as Settings['orgType'])}>
                    <option value="NGO">{t('Association / NGO')}</option>
                    <option value="ORGANIZATION">{t('Organization (Organization)')}</option>
                  </select>{' '}
                  {t('Founded')} <input type="text" className="small-text" aria-label={t('Founded')} value={text('founding')} onChange={(e) => set('founding', e.currentTarget.value)} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-lg">{t('Logo (square)')}</label>
                </th>
                <td>
                  <input id="ys-lg" type="text" className="large-text code" value={text('logo')} placeholder={setup.logoDefault} onChange={(e) => set('logo', e.currentTarget.value)} />
                  <p className="description">{t('For the Google knowledge panel. At least 112×112 PNG. When empty, the Flux SEO icon.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">{t('Address')}</th>
                <td>
                  <input type="text" className="regular-text" placeholder={t('Street, number')} aria-label={t('Street, number')} value={text('street')} onChange={(e) => set('street', e.currentTarget.value)} />{' '}
                  <input type="text" className="regular-text" placeholder={t('District, city')} aria-label={t('District, city')} value={text('locality')} onChange={(e) => set('locality', e.currentTarget.value)} />{' '}
                  <input type="text" className="small-text" placeholder={t('Postal code')} aria-label={t('Postal code')} value={text('postal')} onChange={(e) => set('postal', e.currentTarget.value)} />{' '}
                  <input type="text" className="small-text" maxLength={2} placeholder="TR" aria-label={t('Country code')} value={text('country')} onChange={(e) => set('country', e.currentTarget.value)} />
                  <p className="description">{t('Country is a two-letter code (TR, US, DE). Empty fields are filled from the organization profile (Flux Setup) when it has an address or phone.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">{t('Contact')}</th>
                <td>
                  <input type="text" className="regular-text" placeholder="+90 ..." aria-label={t('Phone')} value={text('phone')} onChange={(e) => set('phone', e.currentTarget.value)} />{' '}
                  <input type="text" className="regular-text" placeholder={t('email')} aria-label={t('Email')} value={text('email')} onChange={(e) => set('email', e.currentTarget.value)} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-sa">{t('Social media profiles')}</label>
                </th>
                <td>
                  <textarea
                    id="ys-sa"
                    className="large-text code"
                    rows={5}
                    placeholder={'https://www.instagram.com/...\nhttps://www.youtube.com/@...'}
                    value={text('sameAs')}
                    onChange={(e) => set('sameAs', e.currentTarget.value)}
                  />
                  <p className="description">{t('One address per line. Google matches the organization to these profiles.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-tw">{t('X (Twitter) username')}</label>
                </th>
                <td>
                  <input id="ys-tw" type="text" className="regular-text" placeholder={t('@username')} value={text('twitter')} onChange={(e) => set('twitter', e.currentTarget.value)} />
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-ka">{t('Areas of expertise')}</label>
                </th>
                <td>
                  <textarea id="ys-ka" className="large-text" rows={4} placeholder={t('One topic per line')} value={text('knowsAbout')} onChange={(e) => set('knowsAbout', e.currentTarget.value)} />
                  <p className="description">{t('Topics the organization is an expert in (schema knowsAbout). Search engines and AI assistants match the organization to these topics. Left out when empty.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-as">{t('Area served')}</label>
                </th>
                <td>
                  <input id="ys-as" type="text" className="regular-text" placeholder={t('e.g. United States')} value={text('areaServed')} onChange={(e) => set('areaServed', e.currentTarget.value)} />
                  <p className="description">{t('Country served (schema areaServed). Left out when empty.')}</p>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <label htmlFor="ys-dl">{t('Donation call to action')}</label>
                </th>
                <td>
                  <input id="ys-dl" type="text" className="regular-text" placeholder={t('e.g. Donate')} value={text('donateLabel')} onChange={(e) => set('donateLabel', e.currentTarget.value)} />{' '}
                  <input
                    type="text"
                    className="regular-text code"
                    placeholder={setup.homeUrl + '…/'}
                    aria-label={t('Donation page address')}
                    value={text('donateUrl')}
                    onChange={(e) => set('donateUrl', e.currentTarget.value)}
                  />
                  <p className="description">{t('For organizations that accept donations (schema DonateAction). Left out when the label is empty; when the URL is empty the donation page is found automatically.')}</p>
                </td>
              </tr>
            </tbody>
          </table>
          {classicSubmit}
        </div>
      )}

      {tab === 'ayarlar' && (
        <div className="flx-raw flxseo-au" id="flxseo-au">
          <section className="au-grid2">
            <div className="au-card au-settings" data-freq={(draft.auditFreq ?? 'DAILY').toLowerCase()}>
              <h2>{t('Site audit and agent readiness')}</h2>
              <p>
                <label htmlFor="au-key">
                  <strong>{t('PageSpeed Insights API key')}</strong>
                </label>
                <br />
                <input
                  type="password"
                  id="au-key"
                  className="regular-text code"
                  autoComplete="off"
                  value={psiKey}
                  placeholder={setup.psiKeySaved ? t('Saved (type to replace)') : 'AIza…'}
                  onChange={(e) => {
                    setPsiKey(e.currentTarget.value);
                    setDirty(true);
                  }}
                />
                {setup.psiKeySaved ? (
                  <Check
                    checked={psiClear}
                    onChange={(v) => {
                      setPsiClear(v);
                      setDirty(true);
                    }}
                    label={t('Delete key')}
                  />
                ) : null}
                <span className="description">
                  {t('Optional but recommended: the keyless quota is shared and runs out quickly. In Google Cloud Console › APIs, enable "PageSpeed Insights API" and create a free key.')}
                </span>
              </p>
              <fieldset>
                <legend>
                  <strong>{t('Agentic browsing')}</strong>
                </legend>
                <Check checked={on('llms')} onChange={(v) => set('llms', v)} label={t('Generate /llms.txt (site name, description, menu pages, recent content)')} />
                <br />
                <label htmlFor="au-intro" className="au-sub">
                  {t('llms.txt intro text (optional)')}
                </label>
                <textarea
                  id="au-intro"
                  rows={3}
                  className="large-text"
                  placeholder={t('E.g. who your organization is, what it does, which pages agents should read first.')}
                  value={text('llmsIntro')}
                  onChange={(e) => set('llmsIntro', e.currentTarget.value)}
                />
                <Check
                  checked={on('webmcp')}
                  onChange={(v) => set('webmcp', v)}
                  label={t('WebMCP: register site tools for browser agents (search, read page as Markdown, site overview) and mark the search form as a tool')}
                />
                <br />
                <Check checked={on('markdown')} onChange={(v) => set('markdown', v)} label={t('Serve Markdown when an agent asks for it (Accept: text/markdown)')} />
                <br />
                <Check checked={on('contentSignal')} onChange={(v) => set('contentSignal', v)} label={t('Declare Content-Signal in robots.txt')} />
                <span className="au-cs">
                  {(
                    [
                      ['csSearch', t('Search (search)')],
                      ['csAiInput', t('AI answers (ai-input)')],
                      ['csAiTrain', t('AI training (ai-train)')],
                    ] as [SettingsKey, string][]
                  ).map(([k, l]) => (
                    <label key={k}>
                      {l}
                      <select value={text(k)} onChange={(e) => set(k, e.currentTarget.value as Settings[typeof k])}>
                        <option value="yes">{t('Allowed')}</option>
                        <option value="no">{t('Not allowed')}</option>
                        <option value="">{t('Not stated')}</option>
                      </select>
                    </label>
                  ))}
                </span>
                <span className="description">
                  {t(
                    'Content signals tell crawlers how your content may be used: in search results, as live input to AI answers, and for training AI models. They are a stated preference; crawlers are not forced to follow them.',
                  )}
                </span>
              </fieldset>
              <fieldset>
                <legend>
                  <strong>{t('Security headers')}</strong>
                </legend>
                <Check checked={on('hdrFrame')} onChange={(v) => set('hdrFrame', v)} label={t("Clickjacking protection (X-Frame-Options: SAMEORIGIN, frame-ancestors 'self')")} />
                <br />
                <Check checked={on('hdrCoop')} onChange={(v) => set('hdrCoop', v)} label={t('Origin isolation (Cross-Origin-Opener-Policy: same-origin-allow-popups)')} />
                <br />
                <Check checked={on('hdrCsp')} onChange={(v) => set('hdrCsp', v)} label={t("Basic CSP (object-src 'none'; base-uri 'self'; upgrade-insecure-requests)")} />
                <br />
                <Check checked={on('hstsSub')} onChange={(v) => set('hstsSub', v)} label={t('HSTS covers subdomains (includeSubDomains)')} />
                <br />
                <Check checked={on('hstsPreload')} onChange={(v) => set('hstsPreload', v)} label={t('HSTS preload (takes months to undo; every subdomain must be HTTPS)')} />
              </fieldset>
              <fieldset>
                <legend>
                  <strong>{t('Automatic audit')}</strong>
                </legend>
                <span className="au-sched">
                  <label>
                    {t('Frequency')}
                    <select id="au-freq" value={draft.auditFreq ?? 'DAILY'} onChange={(e) => set('auditFreq', e.currentTarget.value as Settings['auditFreq'])}>
                      <option value="DAILY">{t('Daily')}</option>
                      <option value="WEEKLY">{t('Weekly')}</option>
                      <option value="OFF">{t('Off')}</option>
                    </select>
                  </label>
                  <label className="au-day">
                    {t('Day')}
                    <select value={text('auditDay') || '1'} onChange={(e) => set('auditDay', e.currentTarget.value)}>
                      {[t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday'), t('Sunday')].map((d, i) => (
                        <option key={d} value={String(i + 1)}>
                          {d}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label>
                    {t('Time')}
                    <select value={text('auditHour') || '3'} onChange={(e) => set('auditHour', e.currentTarget.value)}>
                      {Array.from({ length: 24 }, (_, h) => (
                        <option key={h} value={String(h)}>
                          {String(h).padStart(2, '0') + ':00'}
                        </option>
                      ))}
                    </select>
                  </label>
                </span>
                <span className="description">
                  {t('Runs in the background and saves the result to Report history. The time uses the site time zone; if the site gets no visit at that time, it starts on the next request.')}
                </span>
                <Check checked={on('auditPsi')} onChange={(v) => set('auditPsi', v)} label={t('Also run PageSpeed Insights (mobile + desktop; needs the API key)')} />
                <br />
                <Check checked={on('auditMail')} onChange={(v) => set('auditMail', v)} label={t('Send an email when a new critical or high priority recommendation appears')} />
                <label htmlFor="au-mail" className="au-sub">
                  {t('Email address (empty: site admin)')}
                </label>
                <input type="email" id="au-mail" className="regular-text" value={text('auditMailTo')} placeholder={setup.adminEmail} onChange={(e) => set('auditMailTo', e.currentTarget.value)} />
              </fieldset>
              <button type="submit" className="button button-primary" disabled={busy}>
                {t('Save')}
              </button>
            </div>

            <div className="au-card au-guide">
              <h2>{t('New site build guide')}</h2>
              <p>
                {t(
                  'Rules and a checklist for SEO, performance, accessibility, security and agent readiness when you build a new page, theme or preset. AI agents get the same guide with the MCP tool flxseo_site_build_guide, and Flux AI chat gets it as a tool.',
                )}
              </p>
              <p>
                <GuideButton lang="TR" label={t('Download Turkish (.md)')} t={t} onError={fail} /> <GuideButton lang="EN" label={t('Download English (.md)')} t={t} onError={fail} />
              </p>
              <ul className="au-toc">
                {setup.guideSections.map((x) => (
                  <li key={x}>{x}</li>
                ))}
              </ul>
            </div>
          </section>
        </div>
      )}
    </form>
  );
}

/** The guide is a query, but it is only asked for when the person presses a download button. */
function GuideButton({ lang, label, t, onError }: { lang: FlxSeoGuideLang; label: string; t: Translate; onError: (text: string) => void }) {
  const { client } = useGraphQL();
  const [busy, setBusy] = useState(false);
  const run = async () => {
    setBusy(true);
    try {
      const r = await client.request(FlxSeoGuide, { lang });
      const blob = new Blob([r.flxSeoGuide.markdown], { type: 'text/markdown;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'flux-seo-site-build-guide-' + lang.toLowerCase() + '.md';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 2000);
    } catch (e) {
      onError(errText(e, t));
    } finally {
      setBusy(false);
    }
  };
  return (
    <button type="button" className="button" disabled={busy} onClick={() => void run()}>
      {label}
    </button>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * SEO check: published content with recommendations
 * ------------------------------------------------------------------------------------------------------- */

/** A translated text with %1$s… placeholders filled by React nodes (bold numbers, emphasis). */
function rich(text: string, parts: ReactNode[]): ReactNode[] {
  return text.split(/(%\d\$s)/).map((p, i) => {
    const m = /^%(\d)\$s$/.exec(p);
    return m ? <Fragment key={i}>{parts[Number(m[1]) - 1]}</Fragment> : p;
  });
}

function ContentCheck({ t }: { t: Translate }) {
  const q = useFlxQuery(FlxSeoContentCheck, { limit: 300 });
  if (q.isLoading) return <Loading />;
  const d = q.data?.flxSeoContentCheck;
  if (q.isError || !d) return <div className="flxseo-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const rows = d.items.slice(0, 100);
  return (
    <div className="flx-raw flxseo-classic">
      <p>
        {rich(t('%1$s published items were checked; %2$s have recommendations. To fix one, open the item and use the %3$s panel on the right or tell the AI assistant (MCP) "fix the SEO gaps".', '%1$s', '%2$s', '%3$s'), [
          <strong key="c">{d.checked}</strong>,
          <strong key="t">{d.total}</strong>,
          <em key="e">SEO</em>,
        ])}
      </p>
      <table className="widefat striped">
        <thead>
          <tr>
            <th>{t('Content')}</th>
            <th>{t('Type')}</th>
            <th>{t('Suggestions')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.databaseId}>
              <td>
                {r.editUrl ? (
                  <a href={r.editUrl}>
                    <strong>{r.title}</strong>
                  </a>
                ) : (
                  <strong>{r.title}</strong>
                )}
                <br />
                <a href={r.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: 12 }}>
                  {t('view')}
                </a>
              </td>
              <td>{r.postType}</td>
              <td>
                <ul style={{ margin: 0 }}>
                  {r.issues.map((i) => (
                    <li key={i.code} style={{ color: i.level === 'ERROR' ? 'var(--flxa-error, #d63638)' : 'var(--flxa-warn, #8a6100)' }}>
                      {i.message}
                    </li>
                  ))}
                </ul>
              </td>
            </tr>
          ))}
          {rows.length === 0 && (
            <tr>
              <td colSpan={3}>{t('All good.')}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * Site audit
 * ------------------------------------------------------------------------------------------------------- */

const tone = (score: number | null) => (score === null ? 'na' : score >= 90 ? 'good' : score >= 50 ? 'mid' : 'bad');

function Ring({ score, label, text }: { score: number | null; label: string; text?: string }) {
  const c = 2 * Math.PI * 26;
  const v = score === null ? 0 : Math.max(0, Math.min(100, score));
  return (
    <figure className={'au-ring au-tone-' + tone(score)}>
      <svg viewBox="0 0 64 64" aria-hidden="true">
        <circle cx="32" cy="32" r="26" className="au-ring__bg" />
        <circle cx="32" cy="32" r="26" className="au-ring__fg" strokeDasharray={`${((c * v) / 100).toFixed(1)} ${c.toFixed(1)}`} transform="rotate(-90 32 32)" />
      </svg>
      <span className="au-ring__val">{text || (score === null ? '–' : String(v))}</span>
      <figcaption>{label}</figcaption>
    </figure>
  );
}

function Audit({ t, onNotice }: { t: Translate; onNotice: (n: Notice | null) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const key = flxQueryKey(scope, FlxSeoAuditScreen, {});
  const q = useFlxQuery(FlxSeoAuditScreen, {}, { refetchInterval: (query) => (query.state.data?.flxSeoAuditHistory?.running ? 6000 : false) });
  const [url, setUrl] = useState('');
  const [status, setStatus] = useState('');
  const [running, setRunning] = useState(false);
  const [prio, setPrio] = useState<'' | FlxSeoPriority>('');
  const [cat, setCat] = useState('');
  const urlSet = useRef(false);
  const run = useFlxMutation(FlxSeoAuditRun);
  const fix = useFlxMutation(FlxSeoFixApply);

  useEffect(() => {
    const local = q.data?.flxSeoAudit.local?.url;
    if (local && !urlSet.current) {
      urlSet.current = true;
      setUrl(local);
    }
  }, [q.data]);

  if (q.isLoading) return <Loading />;
  const a = q.data?.flxSeoAudit;
  if (q.isError || !a) return <div className="flxseo-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const history = q.data?.flxSeoAuditHistory ?? null;

  const apply = (audit: AuditData['flxSeoAudit'] | null | undefined, hist: AuditData['flxSeoAuditHistory'] | null | undefined) => {
    qc.setQueryData<AuditData>(key, (old) => (old ? { ...old, flxSeoAudit: audit ?? old.flxSeoAudit, flxSeoAuditHistory: hist ?? old.flxSeoAuditHistory } : old));
  };

  const step = async (mode: 'LOCAL' | 'PAGESPEED', strategy: FlxSeoStrategy | null, text: string): Promise<string | null> => {
    setStatus(text);
    try {
      const r = await run.mutateAsync({ input: { mode, strategy, url: url.trim() || null } });
      const p = r.flxSeoAuditRun;
      if (!p) return t('The action could not be completed.');
      if (p.audit) apply(p.audit, p.history);
      return p.errors.length ? p.errors.map((e) => e.message).join(' ') : null;
    } catch (e) {
      return errText(e, t);
    }
  };
  const runLocal = async () => {
    setRunning(true);
    const err = await step('LOCAL', null, t('On-site audit running…'));
    finish(err ? [err] : []);
  };
  const runPsi = async () => {
    setRunning(true);
    const errs: string[] = [];
    for (const [strategy, text] of [
      ['MOBILE', t('Measuring PageSpeed mobile (30–40 s)…')],
      ['DESKTOP', t('Measuring PageSpeed desktop (30–40 s)…')],
    ] as [FlxSeoStrategy, string][]) {
      const err = await step('PAGESPEED', strategy, text);
      if (err) errs.push(err);
    }
    const err = await step('LOCAL', null, t('On-site audit running…'));
    if (err) errs.push(err);
    finish(errs);
  };
  const finish = (errs: string[]) => {
    setRunning(false);
    setStatus('');
    void qc.invalidateQueries({ queryKey: key });
    onNotice(errs.length ? { kind: 'error', text: errs.join(' ') } : { kind: 'success', text: t('The audit is done.') });
  };
  const applyFix = (f: Finding) => {
    if (!f.fixId) return;
    fix.mutate(
      { input: { id: f.fixId } },
      {
        onSuccess: (r) => {
          const p = r.flxSeoFixApply;
          if (!p || p.errors.length) return onNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
          if (p.audit) apply(p.audit, undefined);
          void qc.invalidateQueries({ queryKey: key });
          onNotice({ kind: 'success', text: t('Turned on. Run the site audit again.') });
        },
        onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
      },
    );
  };

  const prioLabel: Record<FlxSeoPriority, string> = { CRITICAL: t('Critical'), HIGH: t('High'), MEDIUM: t('Medium'), LOW: t('Low') };
  const catLabel: Record<string, string> = {
    performance: t('Performance'),
    accessibility: t('Accessibility'),
    'best-practices': t('Best practices'),
    seo: t('SEO'),
    agentic: t('Agentic browsing'),
  };
  const sourceLabel = { LOCAL: t('On-site'), PSI_MOBILE: t('PageSpeed mobile'), PSI_DESKTOP: t('PageSpeed desktop') };
  const list = a.findings.filter((f) => (!prio || f.priority === prio) && (!cat || f.category === cat || f.also.includes(cat)));
  const count = (p: FlxSeoPriority) => a.counts[p.toLowerCase() as 'critical'];

  return (
    <div className="flx-raw flxseo-au" id="flxseo-au">
      <section className="au-card au-head">
        <div className="au-head__text">
          <h2>{t('Site audit')}</h2>
          <p>
            {t(
              "The five PageSpeed Insights sections (Performance, Accessibility, Best Practices, SEO, Agentic Browsing) by priority. The on-site audit needs no key; a PageSpeed run adds Google's real Lighthouse result. Automatic audit, API key and security headers are in the Settings tab.",
            )}
          </p>
          <p className="au-meta">
            {a.local
              ? rich(t('On-site audit: %1$s · %2$s', '%1$s', '%2$s'), [
                  a.local.time.relative,
                  <a key="u" href={a.local.url} target="_blank" rel="noopener noreferrer">
                    {a.local.url}
                  </a>,
                ])
              : t('No audit has run yet.')}
          </p>
        </div>
        <div className="au-run" id="au-run">
          <label className="screen-reader-text" htmlFor="au-url">
            {t('Address to audit')}
          </label>
          <input
            type="url"
            id="au-url"
            className="regular-text"
            value={url}
            placeholder={a.auditUrl}
            onChange={(e) => setUrl(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                void runLocal();
              }
            }}
          />
          <button type="button" className="button button-primary" disabled={running} onClick={() => void runLocal()}>
            {t('On-site audit')}
          </button>
          <button type="button" className="button" disabled={running} onClick={() => void runPsi()}>
            {t('Measure with PageSpeed')}
          </button>
        </div>
        <p className="au-status" id="au-status" role="status" aria-live="polite">
          {running ? status : history?.running ? t('An audit is running in the background…') : ''}
        </p>
      </section>

      <section className="au-scores">
        {(['MOBILE', 'DESKTOP'] as FlxSeoStrategy[]).map((st) => {
          const p = a.pageSpeed.find((x) => x.strategy === st);
          return (
            <div key={st} className="au-card au-strat">
              <h3>
                {st === 'MOBILE' ? t('Mobile') : t('Desktop')}
                {p ? <span className="au-meta">{p.time.relative + (p.lighthouse ? ' · Lighthouse ' + p.lighthouse : '')}</span> : null}
              </h3>
              {!p ? (
                <p className="au-empty">{t('No PageSpeed run yet. Press "Measure with PageSpeed" (mobile + desktop takes about 1 minute).')}</p>
              ) : (
                <>
                  <div className="au-rings">
                    {p.scores.map((x) => (
                      <Ring key={x.category} score={x.score} label={catLabel[x.category] ?? x.label} />
                    ))}
                  </div>
                  {p.metrics.length ? (
                    <dl className="au-metrics">
                      {p.metrics.map((m) => (
                        <div key={m.key} className={'au-metric au-tone-' + tone(m.score === null ? null : m.score * 100)}>
                          <dt>{m.label}</dt>
                          <dd>{m.value}</dd>
                        </div>
                      ))}
                    </dl>
                  ) : null}
                </>
              )}
            </div>
          );
        })}
        <div className="au-card au-strat au-agentic">
          <h3>
            {t('Agentic browsing')} <span className={'au-pill au-tone-' + (a.agenticPassed === a.agentic.length ? 'good' : 'mid')}>{a.agenticPassed + '/' + a.agentic.length}</span>
          </h3>
          <ul className="au-checks">
            {a.agentic.map((c) => (
              <li key={c.id} className={c.passed ? 'is-ok' : 'is-bad'}>
                <span aria-hidden="true">{c.passed ? '✓' : '✕'}</span> {c.label}
              </li>
            ))}
          </ul>
          <p className="au-meta">
            <a href={a.llmsUrl} target="_blank" rel="noopener noreferrer">
              /llms.txt
            </a>{' '}
            · {a.mcpServerOn ? t('MCP server on') : t('MCP server off')}
          </p>
        </div>
      </section>

      <section className="au-card au-findings">
        <div className="au-bar">
          <h2>
            {t('Recommendations')} <span className="au-meta">{a.findings.length.toLocaleString()}</span>
          </h2>
          <div className="au-filters" role="group" aria-label={t('Filter by priority')}>
            <button type="button" className={'au-chip' + (prio === '' ? ' is-on' : '')} aria-pressed={prio === ''} onClick={() => setPrio('')}>
              {t('All')}
            </button>
            {PRIORITIES.map((p) => (
              <button key={p} type="button" className={'au-chip au-p-' + p.toLowerCase() + (prio === p ? ' is-on' : '')} aria-pressed={prio === p} onClick={() => setPrio(prio === p ? '' : p)}>
                {prioLabel[p]} <b>{count(p)}</b>
              </button>
            ))}
          </div>
          <label className="screen-reader-text" htmlFor="au-cat">
            {t('Section')}
          </label>
          <select id="au-cat" value={cat} onChange={(e) => setCat(e.currentTarget.value)}>
            <option value="">{t('All sections')}</option>
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {catLabel[c]}
              </option>
            ))}
          </select>
        </div>
        {a.findings.length === 0 ? <p className="au-empty">{a.local ? t('No recommendations. All good.') : t('Start the audit; recommendations are listed here by priority.')}</p> : null}
        <div className="au-list">
          {list.map((f) => (
            <details key={f.id} className={'au-f au-p-' + f.priority.toLowerCase()}>
              <summary>
                <span className={'au-badge au-p-' + f.priority.toLowerCase()}>{f.priorityLabel}</span>
                <span className="au-f__title">
                  {f.title}
                  {f.detail ? <span className="au-f__detail"> {f.detail}</span> : null}
                </span>
                <span className="au-tags">
                  {[...new Set([f.category, ...f.also])].map((c) => (
                    <span key={c} className="au-tag">
                      {catLabel[c] ?? c}
                    </span>
                  ))}
                </span>
              </summary>
              <div className="au-f__body">
                {f.fix ? (
                  <p className="au-fix">
                    <strong>{t('How to fix:')}</strong> {f.fix}
                  </p>
                ) : null}
                {f.items.length > 0 ? (
                  <ul className="au-items">
                    {f.items.map((i) => (
                      <li key={i}>
                        <code>{i}</code>
                      </li>
                    ))}
                  </ul>
                ) : null}
                <p className="au-meta">
                  {f.ownerLabel ? <>{t('Where to fix: %s', f.ownerLabel)} · </> : null}
                  {f.sources.map((x) => sourceLabel[x]).join(', ')} · <code>{f.id}</code>
                </p>
                {f.fixId ? (
                  <button type="button" className="button button-primary" disabled={fix.isPending} onClick={() => applyFix(f)}>
                    {f.fixLabel || t('Fix')}
                  </button>
                ) : null}
              </div>
            </details>
          ))}
        </div>
      </section>

      <History history={history} t={t} />
    </div>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * Report history: charts + table
 * ------------------------------------------------------------------------------------------------------- */

interface Series {
  label: string;
  cls: string;
  points: (number | null)[];
}

const LINE_COLORS: Record<string, string> = {
  'is-critical': '#c62828',
  'is-high': '#d9480f',
  'is-medium': '#b26200',
  'is-low': '#2271b1',
  'is-c1': '#3b82f6',
  'is-c2': '#8b5cf6',
  'is-c3': '#14b8a6',
  'is-c4': '#f59e0b',
};

function HistChart({ title, kind, series, labels, max, empty, seg }: { title: ReactNode; kind: 'bar' | 'line'; series: Series[]; labels: string[]; max?: number; empty: string; seg?: ReactNode }) {
  const has = labels.length > 0 && series.some((x) => x.points.some((p) => p !== null));
  return (
    <figure className={'au-chart' + (kind === 'line' ? ' au-chart--scores' : '')}>
      <figcaption>
        {title}
        {seg}
      </figcaption>
      {has ? (
        <Chart
          kind={kind}
          stacked={kind === 'bar'}
          height={190}
          labels={labels}
          series={series.map((sr) => ({ label: sr.label, data: sr.points, color: LINE_COLORS[sr.cls] }))}
          legend
          format={(v) => (max === 100 ? String(Math.round(v)) : v.toLocaleString())}
          label={typeof title === 'string' ? title : undefined}
        />
      ) : (
        <p className="au-empty">{empty}</p>
      )}
    </figure>
  );
}

function History({ history, t }: { history: AuditData['flxSeoAuditHistory'] | null; t: Translate }) {
  const entries: HistoryEntry[] = history?.entries ?? [];
  const chrono = [...entries].reverse();
  const labels = chrono.map((e) => e.time.label);
  const score = (e: HistoryEntry, strategy: FlxSeoStrategy, category: string) => e.scores.find((x) => x.strategy === strategy && x.category === category)?.score ?? null;
  const recs: Series[] = [
    { label: t('Critical'), cls: 'is-critical', points: chrono.map((e) => e.counts.critical || null) },
    { label: t('High'), cls: 'is-high', points: chrono.map((e) => e.counts.high || null) },
    { label: t('Medium'), cls: 'is-medium', points: chrono.map((e) => e.counts.medium || null) },
    { label: t('Low'), cls: 'is-low', points: chrono.map((e) => e.counts.low || null) },
  ];
  const cats: [string, string, string][] = [
    ['performance', t('Performance'), 'is-c1'],
    ['accessibility', t('Accessibility'), 'is-c2'],
    ['best-practices', t('Best practices'), 'is-c3'],
    ['seo', t('SEO'), 'is-c4'],
  ];
  const strat: [FlxSeoStrategy, string][] = [
    ['MOBILE', t('Mobile')],
    ['DESKTOP', t('Desktop')],
  ];
  const hasPsi = entries.some((e) => e.scores.length > 0);
  const strats = strat.filter(([st]) => entries.some((e) => e.scores.some((x) => x.strategy === st)));
  const [devPick, setDev] = useState<FlxSeoStrategy | null>(null);
  const dev = devPick ?? strats[0]?.[0] ?? 'MOBILE';
  const shortLabels = chrono.map((e) => {
    const d = new Date(e.time.iso);
    return Number.isNaN(d.getTime()) ? e.time.label : d.toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short' });
  });
  const prioCls: [keyof HistoryEntry['counts'], string][] = [
    ['critical', t('Critical')],
    ['high', t('High')],
    ['medium', t('Medium')],
    ['low', t('Low')],
  ];
  return (
    <section className="au-card au-history" id="au-history">
      <div className="au-bar">
        <h2>
          {t('Report history')} <span className="au-meta">{(history?.total ?? 0).toLocaleString()}</span>
        </h2>
        <p className="au-meta">
          {history?.next ? t('Next automatic audit: %s', history.next.label) : t('Automatic audit is off.')}
          {history && history.errors.length > 0 ? (
            <>
              {' · '}
              <span className="au-tone-bad">{history.errors.join(' · ')}</span>
            </>
          ) : null}
        </p>
      </div>
      {entries.length === 0 ? (
        <p className="au-empty">{t('No reports yet. Every audit (manual, automatic or by AI) is saved here.')}</p>
      ) : (
        <>
          <div className="au-charts">
            <HistChart kind="bar" title={t('Recommendations')} series={[...recs].reverse()} labels={shortLabels} empty={t('No data yet.')} />
            <HistChart
              kind="line"
              title={t('PageSpeed scores')}
              seg={
                strats.length > 1 ? (
                  <span className="au-seg" role="group" aria-label={t('Device')}>
                    {strats.map(([st, l]) => (
                      <button key={st} type="button" className={'au-chip' + (dev === st ? ' is-on' : '')} aria-pressed={dev === st} onClick={() => setDev(st)}>
                        {l}
                      </button>
                    ))}
                  </span>
                ) : null
              }
              series={cats.map(([c, l, cls]) => ({ label: l, cls, points: chrono.map((e) => score(e, dev, c)) }))}
              labels={shortLabels}
              max={100}
              empty={hasPsi ? t('No data yet.') : t('No report with a PageSpeed run yet. Enter the API key and turn on PageSpeed in the automatic audit.')}
            />
          </div>
          <div className="au-table-wrap">
            <table className="au-table">
              <thead>
                <tr>
                  <th scope="col">{t('Date')}</th>
                  <th scope="col">{t('Trigger')}</th>
                  <th scope="col">{t('Recommendations')}</th>
                  <th scope="col">{t('PageSpeed scores')}</th>
                  <th scope="col">{t('Change')}</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((e, i) => (
                  <tr key={e.time.iso + i}>
                    <td className="au-td-date">
                      {e.time.label}
                      <br />
                      <span className="au-meta">{e.time.relative}</span>
                    </td>
                    <td className="au-td-trig">{e.triggerLabel}</td>
                    <td className="au-counts">
                      {prioCls.map(([k, l]) => (
                        <span key={k} className={'au-badge au-p-' + k} title={l}>
                          {e.counts[k]}
                        </span>
                      ))}
                    </td>
                    <td className="au-scorecell">
                      {e.scores.length === 0 ? (
                        <span className="au-meta">{t('PageSpeed not run')}</span>
                      ) : (
                        strat.map(([st, l]) => {
                          const row = e.scores.filter((x) => x.strategy === st);
                          return row.length ? (
                            <span key={st} className="au-srow">
                              <span className="au-srow__k">{l}</span>
                              {row.map((x) => (
                                <span key={x.category} className={'au-tone-' + tone(x.score)} title={cats.find((c) => c[0] === x.category)?.[1] ?? x.category}>
                                  {x.score ?? '–'}
                                </span>
                              ))}
                            </span>
                          ) : null;
                        })
                      )}
                    </td>
                    <td className="au-td-diff">
                      {e.added.length === 0 && e.resolved.length === 0 ? (
                        <span className="au-meta">{t('No change')}</span>
                      ) : (
                        <details>
                          <summary>
                            {e.added.length > 0 ? <span className="au-tone-bad">{'+' + e.added.length + ' ' + t('new')}</span> : null}{' '}
                            {e.resolved.length > 0 ? <span className="au-tone-good">{'−' + e.resolved.length + ' ' + t('resolved')}</span> : null}
                          </summary>
                          <ul className="au-diff">
                            {e.added.map((x) => (
                              <li key={'a' + x.id} className="au-tone-bad">
                                + {x.title}
                              </li>
                            ))}
                            {e.resolved.map((x) => (
                              <li key={'r' + x.id} className="au-tone-good">
                                ✓ {x.title}
                              </li>
                            ))}
                          </ul>
                        </details>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  );
}
