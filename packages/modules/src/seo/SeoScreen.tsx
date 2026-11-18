// Flux SEO. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the
// Flux SEO GraphQL operations (plugins/flx-seo/graphql/operations). It is the only admin page (no classic tabs):
// General (site name, titles, search engines, IndexNow, indexing, images), Organization and social,
// SEO check (content recommendations), Site audit (on-site + PageSpeed, agent readiness, recommendations
// with one-click fixes, report history) and Settings (PageSpeed key, agents, security headers, schedule, guide).
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxSeoAuditRun,
  FlxSeoAuditScreen,
  FlxSeoContentCheck,
  FlxSeoFixApply,
  FlxSeoGuide,
  FlxSeoIndexNowSubmit,
  FlxSeoPsiKeySave,
  FlxSeoSettingsSave,
  FlxSeoSettingsScreen,
} from '@fluxwp/graphql/operations';
import type {
  FlxSeoAuditScreenQuery,
  FlxSeoGuideLang,
  FlxSeoPriority,
  FlxSeoSettingsFieldsFragment,
  FlxSeoSettingsScreenQuery,
  FlxSeoStrategy,
  FlxSettingsFlxseoInput,
} from '@fluxwp/graphql/types';
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
    <Screen className="flxs-app" icon={host.appIcon} title={t('SEO')} tabs={TABS.map((k) => ({ id: k, label: labels[k] }))} tab={tab} onTab={go}>
      {notice && (
        <div className={'flxs-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxs-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      {tab === 'kontrol' ? (
        <ContentCheck t={t} />
      ) : tab === 'denetim' ? (
        <Audit t={t} onNotice={setNotice} />
      ) : (
        <SettingsTabs tab={tab} t={t} onNotice={setNotice} dirty={dirty} setDirty={setDirty} />
      )}
    </Screen>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * Shared bits
 * ------------------------------------------------------------------------------------------------------- */

function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (on: boolean) => void; label: ReactNode; disabled?: boolean }) {
  return (
    <label className="flxs-check">
      <span className="flxs-switch">
        <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
        <span aria-hidden="true" />
      </span>
      <span>{label}</span>
    </label>
  );
}

function Row({ label, htmlFor, children, help }: { label: ReactNode; htmlFor?: string; children: ReactNode; help?: ReactNode }) {
  return (
    <div className="flxs-field">
      <label htmlFor={htmlFor}>{label}</label>
      <div>
        {children}
        {help && <p className="flxs-desc">{help}</p>}
      </div>
    </div>
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
  if (q.isError || !q.data || !draft || !server) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;

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

  const actions = (
    <div className="flxs-actions">
      <button type="submit" className="flxs-btn is-primary" disabled={busy}>
        {t('Save')}
      </button>
      {dirty && <span className="flxs-dirty">{t('Unsaved changes')}</span>}
    </div>
  );

  return (
    <form onSubmit={submit}>
      {tab === 'genel' && (
        <>
          {setup.otherPlugin && <div className="flxs-banner is-warn">{t('Another SEO plugin is active. To avoid conflicts this plugin\'s meta and schema output is off; favicon, SEO check and AI tools keep working.')}</div>}
          {!setup.aiReady && <div className="flxs-banner">{t('Flux SEO\'s AI (MCP) tools run through the shared MCP server. The server, keys and settings are in Flux AI & MCP › MCP; install and activate Flux AI 0.7.0 or later.')}</div>}
          <div className="flxs-card">
            <div className="flxs-form">
              <Row label={t('SEO output')}>
                <Switch checked={on('enabled')} onChange={(v) => set('enabled', v)} label={t('Add meta tags, Open Graph and schema')} />
              </Row>
              <Row
                label={t('Site name shown in Google')}
                htmlFor="flxs-sn"
                help={t('Search results show this name instead of the domain (WebSite schema). Pick a short, well-known name. A change shows in Google after it crawls again.')}
              >
                <input id="flxs-sn" type="text" className="is-wide" value={text('siteName')} placeholder={setup.blogName} onChange={(e) => set('siteName', e.currentTarget.value)} />
                <label className="flxs-inline" htmlFor="flxs-an">
                  {t('Alternative name')}
                </label>
                <input id="flxs-an" type="text" value={text('altName')} onChange={(e) => set('altName', e.currentTarget.value)} />
              </Row>
              <Row label={t('Title separator')} htmlFor="flxs-sep" help={t('Example: %s', t('About') + ' ' + text('sep') + ' ' + (text('altName') || setup.blogName))}>
                <input id="flxs-sep" type="text" className="is-small" value={text('sep')} onChange={(e) => set('sep', e.currentTarget.value)} />
              </Row>
              <Row label={t('Home page title')} htmlFor="flxs-ht">
                <input id="flxs-ht" type="text" className="is-wide" maxLength={70} value={text('homeTitle')} placeholder={setup.homeTitle} onChange={(e) => set('homeTitle', e.currentTarget.value)} />
              </Row>
              <Row label={t('Home page description')} htmlFor="flxs-hd" help={t('150–160 characters is ideal. When empty, the site tagline is used.')}>
                <textarea id="flxs-hd" rows={3} maxLength={320} value={text('homeDesc')} placeholder={setup.homeDesc} onChange={(e) => set('homeDesc', e.currentTarget.value)} />
              </Row>
              <Row label={t('Default share image')} htmlFor="flxs-og" help={t('Used on pages without a featured image. 1200×630 JPG/PNG. When empty, the plugin\'s image.')}>
                <input id="flxs-og" type="text" className="is-wide is-code" value={text('ogImage')} placeholder={setup.imageDefault} onChange={(e) => set('ogImage', e.currentTarget.value)} />
              </Row>
            </div>
          </div>

          <div className="flxs-card" id="search-engines">
            <h2>{t('Search engines')}</h2>
            <p className="flxs-desc">{t('Search engines other than Google (Bing, Yandex, DuckDuckGo, Yahoo, Ecosia) crawl a site quickly only once it is introduced to them. Add the site in each one\'s webmaster panel, paste the verification code below and submit the sitemap URL.')}</p>
            <div className="flxs-row">
              <label htmlFor="flxs-sitemap">
                <strong>{t('Sitemap URL')}</strong>
              </label>
              <input id="flxs-sitemap" type="text" readOnly className="is-wide is-code" value={setup.sitemapUrl} onFocus={(e) => e.currentTarget.select()} />
              <CopyButton text={setup.sitemapUrl} t={t} />
            </div>
            <p className="flxs-desc">{t('Also listed in robots.txt; add this URL in Search Console, Bing and Yandex.')}</p>
            <table className="flxs-table">
              <tbody>
                {setup.engines.map((e) => {
                  const f = e.field as SettingsKey;
                  return (
                    <tr key={e.key}>
                      <td className="flxs-eng">
                        <label htmlFor={'flxs-' + e.key}>
                          <strong>{e.name}</strong>
                        </label>
                        <br />
                        <a href={e.url} target="_blank" rel="noopener noreferrer">
                          {e.link} ↗
                        </a>
                      </td>
                      <td>
                        <div className="flxs-row">
                          <input id={'flxs-' + e.key} type="text" className="is-wide is-code" value={text(f)} placeholder={e.placeholder} onChange={(ev) => verifyPaste(f, ev.currentTarget.value)} />
                          {text(f) && (
                            <span className="flxs-ok" title={t('Verification tag is on the site')}>
                              ✓
                            </span>
                          )}
                        </div>
                        {e.key === 'verify_google' && setup.sitekit && <p className="flxs-desc">✓ {t('Connected with Google Site Kit; Search Console verification and the sitemap are handled there. You can leave this empty.')}</p>}
                        <p className="flxs-desc">{e.help}</p>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="flxs-card" id="indexnow">
            <h2>{t('IndexNow')}</h2>
            <Switch checked={on('indexnow')} onChange={(v) => set('indexnow', v)} label={t('Instantly notify Bing, Yandex, DuckDuckGo (via Bing), Seznam and Naver about published and updated pages')} />
            <p className="flxs-desc">
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
            <button type="button" className="flxs-btn" disabled={indexnow.isPending || !setup.indexnowReady} onClick={() => indexnow.mutate({ input: {} })}>
              {t('Submit the whole site now')}
            </button>
          </div>

          <div className="flxs-card">
            <h2>{t('Indexing')}</h2>
            <div className="flxs-stack">
              <Switch checked={on('noindexShop')} onChange={(v) => set('noindexShop', v)} label={t('Keep cart, checkout and My Account pages out of the index')} />
              <Switch checked={on('noindexSearch')} onChange={(v) => set('noindexSearch', v)} label={t('Keep on-site search results out of the index')} />
              <Switch checked={on('noindexAuthor')} onChange={(v) => set('noindexAuthor', v)} label={t('Turn off author pages (redirect to the home page, remove from the sitemap)')} />
              <Switch checked={on('noindexTags')} onChange={(v) => set('noindexTags', v)} label={t('Keep tag pages out of the index (also removed from the sitemap; categories stay open)')} />
            </div>
            <p className="flxs-desc">{t('RSS feeds are always served with a "noindex" header; they should not show up as pages in Google.')}</p>
            <h2 className="flxs-sub">{t('Images')}</h2>
            <Switch checked={on('autoAlt')} onChange={(v) => set('autoAlt', v)} label={t('Give images with empty alt text the media library alt text, or else the file name')} />
          </div>
          {actions}
        </>
      )}

      {tab === 'kurum' && (
        <>
          <div className="flxs-card">
            <div className="flxs-form">
              <Row label={t('Organization name')} htmlFor="flxs-on">
                <input id="flxs-on" type="text" className="is-wide" value={text('orgName')} onChange={(e) => set('orgName', e.currentTarget.value)} />
              </Row>
              <Row label={t('Organization type')} htmlFor="flxs-ot">
                <select id="flxs-ot" value={draft.orgType ?? 'ORGANIZATION'} onChange={(e) => set('orgType', e.currentTarget.value as Settings['orgType'])}>
                  <option value="NGO">{t('Association / NGO')}</option>
                  <option value="ORGANIZATION">{t('Organization')}</option>
                </select>
                <label className="flxs-inline" htmlFor="flxs-fy">
                  {t('Founded')}
                </label>
                <input id="flxs-fy" type="text" className="is-small" value={text('founding')} onChange={(e) => set('founding', e.currentTarget.value)} />
              </Row>
              <Row label={t('Logo (square)')} htmlFor="flxs-lg" help={t('For the Google knowledge panel. At least 112×112 PNG. When empty, the Flux SEO icon.')}>
                <input id="flxs-lg" type="text" className="is-wide is-code" value={text('logo')} placeholder={setup.logoDefault} onChange={(e) => set('logo', e.currentTarget.value)} />
              </Row>
              <Row label={t('Address')} htmlFor="flxs-st" help={t('Country is a two-letter code (TR, US, DE). Empty fields are filled from the organization profile (Flux Setup) when it has an address or phone.')}>
                <div className="flxs-wrap">
                  <input id="flxs-st" type="text" placeholder={t('Street, number')} value={text('street')} onChange={(e) => set('street', e.currentTarget.value)} />
                  <input type="text" placeholder={t('District, city')} aria-label={t('District, city')} value={text('locality')} onChange={(e) => set('locality', e.currentTarget.value)} />
                  <input type="text" className="is-small" placeholder={t('Postal code')} aria-label={t('Postal code')} value={text('postal')} onChange={(e) => set('postal', e.currentTarget.value)} />
                  <input type="text" className="is-small" maxLength={2} placeholder="TR" aria-label={t('Country code')} value={text('country')} onChange={(e) => set('country', e.currentTarget.value)} />
                </div>
              </Row>
              <Row label={t('Contact')} htmlFor="flxs-ph">
                <div className="flxs-wrap">
                  <input id="flxs-ph" type="text" placeholder="+90 ..." value={text('phone')} onChange={(e) => set('phone', e.currentTarget.value)} />
                  <input type="text" placeholder={t('Email')} aria-label={t('Email')} value={text('email')} onChange={(e) => set('email', e.currentTarget.value)} />
                </div>
              </Row>
              <Row label={t('Social media profiles')} htmlFor="flxs-sa" help={t('One address per line. Google matches the organization to these profiles.')}>
                <textarea id="flxs-sa" rows={5} className="is-code" placeholder={'https://www.instagram.com/...\nhttps://www.youtube.com/@...'} value={text('sameAs')} onChange={(e) => set('sameAs', e.currentTarget.value)} />
              </Row>
              <Row label={t('X (Twitter) username')} htmlFor="flxs-tw">
                <input id="flxs-tw" type="text" placeholder="@username" value={text('twitter')} onChange={(e) => set('twitter', e.currentTarget.value)} />
              </Row>
              <Row
                label={t('Areas of expertise')}
                htmlFor="flxs-ka"
                help={t('Topics the organization is an expert in (schema knowsAbout). Search engines and AI assistants match the organization to these topics. Left out when empty.')}
              >
                <textarea id="flxs-ka" rows={4} placeholder={t('One topic per line')} value={text('knowsAbout')} onChange={(e) => set('knowsAbout', e.currentTarget.value)} />
              </Row>
              <Row label={t('Area served')} htmlFor="flxs-as" help={t('Country served (schema areaServed). Left out when empty.')}>
                <input id="flxs-as" type="text" placeholder={t('e.g. United States')} value={text('areaServed')} onChange={(e) => set('areaServed', e.currentTarget.value)} />
              </Row>
              <Row
                label={t('Donation call to action')}
                htmlFor="flxs-dl"
                help={t('For organizations that accept donations (schema DonateAction). Left out when the label is empty; when the URL is empty the donation page is found automatically.')}
              >
                <div className="flxs-wrap">
                  <input id="flxs-dl" type="text" placeholder={t('e.g. Donate')} value={text('donateLabel')} onChange={(e) => set('donateLabel', e.currentTarget.value)} />
                  <input type="text" className="is-code" placeholder={setup.homeUrl + '…'} aria-label={t('Donation page address')} value={text('donateUrl')} onChange={(e) => set('donateUrl', e.currentTarget.value)} />
                </div>
              </Row>
            </div>
          </div>
          {actions}
        </>
      )}

      {tab === 'ayarlar' && (
        <>
          <div className="flxs-card">
            <h2>{t('Site audit and agent readiness')}</h2>
            <div className="flxs-form">
              <Row
                label={t('PageSpeed Insights API key')}
                htmlFor="flxs-psi"
                help={t('Optional but recommended: the keyless quota is shared and runs out quickly. In Google Cloud Console › APIs, enable "PageSpeed Insights API" and create a free key.')}
              >
                <input
                  id="flxs-psi"
                  type="password"
                  autoComplete="off"
                  className="is-wide is-code"
                  value={psiKey}
                  placeholder={setup.psiKeySaved ? t('Saved (type to replace)') : 'AIza…'}
                  onChange={(e) => {
                    setPsiKey(e.currentTarget.value);
                    setDirty(true);
                  }}
                />
                {setup.psiKeySaved && (
                  <Switch
                    checked={psiClear}
                    onChange={(v) => {
                      setPsiClear(v);
                      setDirty(true);
                    }}
                    label={t('Delete key')}
                  />
                )}
              </Row>
            </div>
            <h3 className="flxs-sub">{t('Agentic browsing')}</h3>
            <div className="flxs-stack">
              <Switch checked={on('llms')} onChange={(v) => set('llms', v)} label={t('Generate /llms.txt (site name, description, menu pages, recent content)')} />
              <label className="flxs-sublabel" htmlFor="flxs-intro">
                {t('llms.txt intro text (optional)')}
              </label>
              <textarea id="flxs-intro" rows={3} placeholder={t('E.g. who your organization is, what it does, which pages agents should read first.')} value={text('llmsIntro')} onChange={(e) => set('llmsIntro', e.currentTarget.value)} />
              <Switch checked={on('webmcp')} onChange={(v) => set('webmcp', v)} label={t('WebMCP: register site tools for browser agents (search, read page as Markdown, site overview) and mark the search form as a tool')} />
              <Switch checked={on('markdown')} onChange={(v) => set('markdown', v)} label={t('Serve Markdown when an agent asks for it (Accept: text/markdown)')} />
              <Switch checked={on('contentSignal')} onChange={(v) => set('contentSignal', v)} label={t('Declare Content-Signal in robots.txt')} />
              <div className="flxs-wrap">
                {(
                  [
                    ['csSearch', t('Search (search)')],
                    ['csAiInput', t('AI answers (ai-input)')],
                    ['csAiTrain', t('AI training (ai-train)')],
                  ] as [SettingsKey, string][]
                ).map(([k, l]) => (
                  <label key={k} className="flxs-select">
                    {l}
                    <select value={text(k)} onChange={(e) => set(k, e.currentTarget.value as Settings[typeof k])}>
                      <option value="yes">{t('Allowed')}</option>
                      <option value="no">{t('Not allowed')}</option>
                      <option value="">{t('Not stated')}</option>
                    </select>
                  </label>
                ))}
              </div>
              <p className="flxs-desc">{t('Content signals tell crawlers how your content may be used: in search results, as live input to AI answers, and for training AI models. They are a stated preference; crawlers are not forced to follow them.')}</p>
            </div>
            <h3 className="flxs-sub">{t('Security headers')}</h3>
            <div className="flxs-stack">
              <Switch checked={on('hdrFrame')} onChange={(v) => set('hdrFrame', v)} label={t('Clickjacking protection (X-Frame-Options: SAMEORIGIN, frame-ancestors \'self\')')} />
              <Switch checked={on('hdrCoop')} onChange={(v) => set('hdrCoop', v)} label={t('Origin isolation (Cross-Origin-Opener-Policy: same-origin-allow-popups)')} />
              <Switch checked={on('hdrCsp')} onChange={(v) => set('hdrCsp', v)} label={t('Basic CSP (object-src \'none\'; base-uri \'self\'; upgrade-insecure-requests)')} />
              <Switch checked={on('hstsSub')} onChange={(v) => set('hstsSub', v)} label={t('HSTS covers subdomains (includeSubDomains)')} />
              <Switch checked={on('hstsPreload')} onChange={(v) => set('hstsPreload', v)} label={t('HSTS preload (takes months to undo; every subdomain must be HTTPS)')} />
            </div>
            <h3 className="flxs-sub">{t('Automatic audit')}</h3>
            <div className="flxs-stack">
              <div className="flxs-wrap">
                <label className="flxs-select">
                  {t('Frequency')}
                  <select value={draft.auditFreq ?? 'DAILY'} onChange={(e) => set('auditFreq', e.currentTarget.value as Settings['auditFreq'])}>
                    <option value="DAILY">{t('Daily')}</option>
                    <option value="WEEKLY">{t('Weekly')}</option>
                    <option value="OFF">{t('Off')}</option>
                  </select>
                </label>
                {draft.auditFreq === 'WEEKLY' && (
                  <label className="flxs-select">
                    {t('Day')}
                    <select value={text('auditDay') || '1'} onChange={(e) => set('auditDay', e.currentTarget.value)}>
                      {[t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday'), t('Sunday')].map((d, i) => (
                        <option key={d} value={String(i + 1)}>
                          {d}
                        </option>
                      ))}
                    </select>
                  </label>
                )}
                {draft.auditFreq !== 'OFF' && (
                  <label className="flxs-select">
                    {t('Time')}
                    <select value={text('auditHour') || '3'} onChange={(e) => set('auditHour', e.currentTarget.value)}>
                      {Array.from({ length: 24 }, (_, h) => (
                        <option key={h} value={String(h)}>
                          {String(h).padStart(2, '0') + ':00'}
                        </option>
                      ))}
                    </select>
                  </label>
                )}
              </div>
              <p className="flxs-desc">{t('Runs in the background and saves the result to Report history. The time uses the site time zone; if the site gets no visit at that time, it starts on the next request.')}</p>
              <Switch checked={on('auditPsi')} onChange={(v) => set('auditPsi', v)} label={t('Also run PageSpeed Insights (mobile + desktop; needs the API key)')} />
              <Switch checked={on('auditMail')} onChange={(v) => set('auditMail', v)} label={t('Send an email when a new critical or high priority recommendation appears')} />
              <label className="flxs-sublabel" htmlFor="flxs-mail">
                {t('Email address (empty: site admin)')}
              </label>
              <input id="flxs-mail" type="email" className="is-wide" value={text('auditMailTo')} placeholder={setup.adminEmail} onChange={(e) => set('auditMailTo', e.currentTarget.value)} />
            </div>
          </div>
          {actions}

          <div className="flxs-card">
            <h2>{t('New site build guide')}</h2>
            <p className="flxs-desc">{t('Rules and a checklist for SEO, performance, accessibility, security and agent readiness when you build a new page, theme or preset. AI agents get the same guide with the MCP tool flxseo_site_build_guide, and Flux AI chat gets it as a tool.')}</p>
            <div className="flxs-actions">
              <GuideButton lang="TR" label={t('Download Turkish (.md)')} t={t} onError={fail} />
              <GuideButton lang="EN" label={t('Download English (.md)')} t={t} onError={fail} />
            </div>
            <ul className="flxs-toc">
              {setup.guideSections.map((x) => (
                <li key={x}>{x}</li>
              ))}
            </ul>
          </div>
        </>
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
    <button type="button" className="flxs-btn" disabled={busy} onClick={() => void run()}>
      {label}
    </button>
  );
}

function CopyButton({ text, t }: { text: string; t: Translate }) {
  const [done, setDone] = useState(false);
  return (
    <button
      type="button"
      className="flxs-btn is-small"
      onClick={() => {
        void navigator.clipboard?.writeText(text).then(() => {
          setDone(true);
          window.setTimeout(() => setDone(false), 1500);
        });
      }}
    >
      {done ? t('Copied') : t('Copy')}
    </button>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * SEO check: published content with recommendations
 * ------------------------------------------------------------------------------------------------------- */

function ContentCheck({ t }: { t: Translate }) {
  const q = useFlxQuery(FlxSeoContentCheck, { limit: 300 });
  if (q.isLoading) return <Loading />;
  const d = q.data?.flxSeoContentCheck;
  if (q.isError || !d) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const rows = d.items.slice(0, 100);
  return (
    <div className="flxs-card">
      <p className="flxs-lead">{t('%1$s published items were checked; %2$s have recommendations. To fix one, open the item and use the SEO panel on the right.', d.checked, d.items.length)}</p>
      <div className="flxs-scroll">
        <table className="flxs-table is-striped">
          <thead>
            <tr>
              <th scope="col">{t('Content')}</th>
              <th scope="col">{t('Type')}</th>
              <th scope="col">{t('Recommendations')}</th>
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
                  <a className="flxs-small" href={r.url} target="_blank" rel="noopener noreferrer">
                    {t('view')} ↗
                  </a>
                </td>
                <td>{r.postType}</td>
                <td>
                  <ul className="flxs-issues">
                    {r.issues.map((i) => (
                      <li key={i.code} className={i.level === 'ERROR' ? 'is-error' : 'is-warn'}>
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
    </div>
  );
}

/* ---------------------------------------------------------------------------------------------------------
 * Site audit
 * ------------------------------------------------------------------------------------------------------- */

const tone = (score: number | null) => (score === null ? 'na' : score >= 90 ? 'good' : score >= 50 ? 'mid' : 'bad');

function Ring({ score, label }: { score: number | null; label: string }) {
  const r = 26;
  const c = 2 * Math.PI * r;
  const v = score === null ? 0 : Math.max(0, Math.min(100, score));
  return (
    <figure className={'flxs-ring is-' + tone(score)}>
      <svg viewBox="0 0 64 64" width="64" height="64" role="img" aria-label={label + ': ' + (score ?? '–')}>
        <circle cx="32" cy="32" r={r} className="bg" />
        <circle cx="32" cy="32" r={r} className="fg" strokeDasharray={`${(c * v) / 100} ${c}`} transform="rotate(-90 32 32)" />
        <text x="32" y="37" textAnchor="middle">
          {score === null ? '–' : score}
        </text>
      </svg>
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
  if (q.isError || !a) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
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
    <div className="flxs-audit">
      <section className="flxs-card">
        <h2>{t('Site audit')}</h2>
        <p className="flxs-desc">{t('The five PageSpeed Insights sections (Performance, Accessibility, Best Practices, SEO, Agentic Browsing) by priority. The on-site audit needs no key; a PageSpeed run adds Google\'s real Lighthouse results.')}</p>
        <p className="flxs-meta">
          {a.local ? (
            <>
              {t('On-site audit: %s', a.local.time.relative)} ·{' '}
              <a href={a.local.url} target="_blank" rel="noopener noreferrer">
                {a.local.url}
              </a>
            </>
          ) : (
            t('No audit has run yet.')
          )}
        </p>
        <form
          className="flxs-run"
          onSubmit={(e) => {
            e.preventDefault();
            void runLocal();
          }}
        >
          <label className="flxs-sr" htmlFor="flxs-url">
            {t('Address to audit')}
          </label>
          <input id="flxs-url" type="url" className="is-wide" value={url} placeholder={a.auditUrl} onChange={(e) => setUrl(e.currentTarget.value)} />
          <button type="submit" className="flxs-btn is-primary" disabled={running}>
            {t('On-site audit')}
          </button>
          <button type="button" className="flxs-btn" disabled={running} onClick={() => void runPsi()}>
            {t('Measure with PageSpeed')}
          </button>
        </form>
        <p className="flxs-status" role="status" aria-live="polite">
          {running ? status : history?.running ? t('An audit is running in the background…') : ''}
        </p>
        {history && history.errors.length > 0 && <p className="flxs-small is-error">{history.errors.join(' · ')}</p>}
      </section>

      <section className="flxs-scores">
        {(['MOBILE', 'DESKTOP'] as FlxSeoStrategy[]).map((st) => {
          const p = a.pageSpeed.find((x) => x.strategy === st);
          return (
            <div key={st} className="flxs-card flxs-strat">
              <h3>
                {st === 'MOBILE' ? t('Mobile') : t('Desktop')}
                {p && (
                  <span className="flxs-meta">
                    {' '}
                    {p.time.relative}
                    {p.lighthouse ? ' · Lighthouse ' + p.lighthouse : ''}
                  </span>
                )}
              </h3>
              {!p ? (
                <p className="flxs-empty">{t('No PageSpeed run yet. Press "Measure with PageSpeed" (mobile + desktop takes about 1 minute).')}</p>
              ) : (
                <>
                  <div className="flxs-rings">
                    {p.scores.map((x) => (
                      <Ring key={x.category} score={x.score} label={x.label} />
                    ))}
                  </div>
                  <dl className="flxs-metrics">
                    {p.metrics.map((m) => (
                      <div key={m.key} className={'flxs-metric is-' + tone(m.score === null ? null : m.score * 100)}>
                        <dt>{m.label}</dt>
                        <dd>{m.value}</dd>
                      </div>
                    ))}
                  </dl>
                </>
              )}
            </div>
          );
        })}
        <div className="flxs-card flxs-strat">
          <h3>
            {t('Agentic browsing')}{' '}
            <span className={'flxs-pill is-' + (a.agenticPassed === a.agentic.length ? 'good' : 'mid')}>
              {a.agenticPassed}/{a.agentic.length}
            </span>
          </h3>
          <ul className="flxs-checks">
            {a.agentic.map((c) => (
              <li key={c.id} className={c.passed ? 'is-ok' : 'is-bad'}>
                <span aria-hidden="true">{c.passed ? '✓' : '✕'}</span> {c.label}
              </li>
            ))}
          </ul>
          <p className="flxs-meta">
            <a href={a.llmsUrl} target="_blank" rel="noopener noreferrer">
              /llms.txt
            </a>{' '}
            · {a.mcpServerOn ? t('MCP server on') : t('MCP server off')}
          </p>
        </div>
      </section>

      <section className="flxs-card">
        <div className="flxs-bar">
          <h2>
            {t('Recommendations')} <span className="flxs-meta">{a.findings.length.toLocaleString()}</span>
          </h2>
          <div className="flxs-filters" role="group" aria-label={t('Filter by priority')}>
            <button type="button" className={'flxs-chip' + (prio === '' ? ' is-on' : '')} aria-pressed={prio === ''} onClick={() => setPrio('')}>
              {t('All')}
            </button>
            {PRIORITIES.map((p) => (
              <button key={p} type="button" className={'flxs-chip is-' + p.toLowerCase() + (prio === p ? ' is-on' : '')} aria-pressed={prio === p} onClick={() => setPrio(prio === p ? '' : p)}>
                {prioLabel[p]} <b>{count(p)}</b>
              </button>
            ))}
          </div>
          <label className="flxs-sr" htmlFor="flxs-cat">
            {t('Section')}
          </label>
          <select id="flxs-cat" value={cat} onChange={(e) => setCat(e.currentTarget.value)}>
            <option value="">{t('All sections')}</option>
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {catLabel[c]}
              </option>
            ))}
          </select>
        </div>
        {a.findings.length === 0 ? (
          <p className="flxs-empty">{a.local ? t('No recommendations. All good.') : t('Start the audit; recommendations are listed here by priority.')}</p>
        ) : (
          <div className="flxs-list">
            {list.map((f) => (
              <details key={f.id} className={'flxs-f is-' + f.priority.toLowerCase()}>
                <summary>
                  <span className={'flxs-badge is-' + f.priority.toLowerCase()}>{f.priorityLabel}</span>
                  <span className="flxs-f-title">
                    {f.title}
                    {f.detail && <span className="flxs-f-detail"> {f.detail}</span>}
                  </span>
                  <span className="flxs-tags">
                    {[f.category, ...f.also].map((c) => (
                      <span key={c} className="flxs-tag">
                        {catLabel[c] ?? c}
                      </span>
                    ))}
                  </span>
                </summary>
                <div className="flxs-f-body">
                  {f.fix && (
                    <p>
                      <strong>{t('How to fix:')}</strong> {f.fix}
                    </p>
                  )}
                  {f.items.length > 0 && (
                    <ul className="flxs-items">
                      {f.items.map((i) => (
                        <li key={i}>{i}</li>
                      ))}
                    </ul>
                  )}
                  <p className="flxs-meta">
                    {f.ownerLabel && <>{t('Where to fix: %s', f.ownerLabel)} · </>}
                    {t('Found by: %s', f.sources.map((x) => sourceLabel[x]).join(', '))}
                  </p>
                  {f.fixId && (
                    <button type="button" className="flxs-btn is-primary" disabled={fix.isPending} onClick={() => applyFix(f)}>
                      {f.fixLabel || t('Fix')}
                    </button>
                  )}
                </div>
              </details>
            ))}
            {list.length === 0 && <p className="flxs-empty">{t('No recommendations match this filter.')}</p>}
          </div>
        )}
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

function LineChart({ title, series, labels, max, empty }: { title: string; series: Series[]; labels: string[]; max?: number; empty: string }) {
  const w = 520;
  const h = 150;
  const pad = { l: 30, r: 8, t: 8, b: 18 };
  const n = labels.length;
  const vals = series.flatMap((x) => x.points.filter((p): p is number => p !== null));
  if (n < 1 || vals.length === 0) return <p className="flxs-empty">{empty}</p>;
  const top = max ?? Math.max(1, ...vals);
  const x = (i: number) => pad.l + (n === 1 ? (w - pad.l - pad.r) / 2 : (i * (w - pad.l - pad.r)) / (n - 1));
  const y = (v: number) => pad.t + (h - pad.t - pad.b) * (1 - v / top);
  return (
    <figure className="flxs-chart">
      <svg viewBox={`0 0 ${w} ${h}`} role="img" aria-label={title}>
        {[0, 0.5, 1].map((g) => (
          <g key={g}>
            <line x1={pad.l} x2={w - pad.r} y1={y(top * g)} y2={y(top * g)} className="grid" />
            <text x={pad.l - 4} y={y(top * g) + 3} textAnchor="end" className="axis">
              {Math.round(top * g)}
            </text>
          </g>
        ))}
        {series.map((sr) => {
          const pts = sr.points.map((p, i) => (p === null ? null : [x(i), y(p)] as const));
          const path = pts.reduce((d, p, i) => (p ? d + (pts[i - 1] ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1) : d), '');
          return (
            <g key={sr.label} className={'line ' + sr.cls}>
              <path d={path} fill="none" />
              {pts.map((p, i) => p && <circle key={i} cx={p[0]} cy={p[1]} r="2.5" />)}
            </g>
          );
        })}
        <text x={pad.l} y={h - 4} className="axis">
          {labels[0]}
        </text>
        {n > 1 && (
          <text x={w - pad.r} y={h - 4} textAnchor="end" className="axis">
            {labels[n - 1]}
          </text>
        )}
      </svg>
      <figcaption>
        {title}
        <span className="flxs-legend">
          {series.map((sr) => (
            <span key={sr.label} className={'flxs-key ' + sr.cls}>
              {sr.label}
            </span>
          ))}
        </span>
      </figcaption>
    </figure>
  );
}

function History({ history, t }: { history: AuditData['flxSeoAuditHistory'] | null; t: Translate }) {
  const entries: HistoryEntry[] = history?.entries ?? [];
  const chrono = [...entries].reverse();
  const labels = chrono.map((e) => e.time.label);
  const score = (e: HistoryEntry, strategy: FlxSeoStrategy, category: string) => e.scores.find((x) => x.strategy === strategy && x.category === category)?.score ?? null;
  const recs: Series[] = [
    { label: t('Critical'), cls: 'is-critical', points: chrono.map((e) => e.counts.critical) },
    { label: t('High'), cls: 'is-high', points: chrono.map((e) => e.counts.high) },
    { label: t('Medium'), cls: 'is-medium', points: chrono.map((e) => e.counts.medium) },
    { label: t('Low'), cls: 'is-low', points: chrono.map((e) => e.counts.low) },
  ];
  const cats: [string, string, string][] = [
    ['performance', t('Performance'), 'is-c1'],
    ['accessibility', t('Accessibility'), 'is-c2'],
    ['best-practices', t('Best practices'), 'is-c3'],
    ['seo', t('SEO'), 'is-c4'],
  ];
  const hasPsi = entries.some((e) => e.scores.length > 0);
  return (
    <section className="flxs-card">
      <div className="flxs-bar">
        <h2>
          {t('Report history')} <span className="flxs-meta">{(history?.total ?? 0).toLocaleString()}</span>
        </h2>
        <span className="flxs-meta">{history?.next ? t('Next automatic audit: %s', history.next.label) : t('Automatic audit is off.')}</span>
      </div>
      {entries.length === 0 ? (
        <p className="flxs-empty">{t('No reports yet. Every audit (manual, automatic or by AI) is saved here.')}</p>
      ) : (
        <>
          <div className="flxs-charts">
            <LineChart title={t('Recommendations')} series={recs} labels={labels} empty={t('No data yet.')} />
            {(['MOBILE', 'DESKTOP'] as FlxSeoStrategy[]).map((st) => (
              <LineChart
                key={st}
                title={(st === 'MOBILE' ? t('Mobile') : t('Desktop')) + ' · ' + t('PageSpeed scores')}
                series={cats.map(([c, l, cls]) => ({ label: l, cls, points: chrono.map((e) => score(e, st, c)) }))}
                labels={labels}
                max={100}
                empty={hasPsi ? t('No data yet.') : t('No report with a PageSpeed run yet. Enter the API key and turn on PageSpeed in the automatic audit.')}
              />
            ))}
          </div>
          <div className="flxs-scroll">
            <table className="flxs-table is-striped">
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
                    <td>
                      {e.time.label}
                      <br />
                      <span className="flxs-small">{e.time.relative}</span>
                    </td>
                    <td>{e.triggerLabel}</td>
                    <td className="flxs-counts">
                      <span className="is-critical">{e.counts.critical}</span> <span className="is-high">{e.counts.high}</span> <span className="is-medium">{e.counts.medium}</span> <span className="is-low">{e.counts.low}</span>
                    </td>
                    <td>
                      {e.scores.length === 0 ? (
                        <span className="flxs-meta">{t('PageSpeed not run')}</span>
                      ) : (
                        (['MOBILE', 'DESKTOP'] as FlxSeoStrategy[]).map((st) => {
                          const p = score(e, st, 'performance');
                          return p === null && !e.scores.some((x) => x.strategy === st) ? null : (
                            <span key={st} className={'flxs-score is-' + tone(p)}>
                              {st === 'MOBILE' ? t('Mobile') : t('Desktop')} {p ?? '–'}
                            </span>
                          );
                        })
                      )}
                    </td>
                    <td>
                      {e.added.length === 0 && e.resolved.length === 0 ? (
                        <span className="flxs-meta">{t('No change')}</span>
                      ) : (
                        <>
                          {e.added.length > 0 && (
                            <span className="is-bad" title={e.added.map((x) => x.title).join('\n')}>
                              +{e.added.length} {t('new')}
                            </span>
                          )}{' '}
                          {e.resolved.length > 0 && (
                            <span className="is-good" title={e.resolved.map((x) => x.title).join('\n')}>
                              −{e.resolved.length} {t('resolved')}
                            </span>
                          )}
                        </>
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
