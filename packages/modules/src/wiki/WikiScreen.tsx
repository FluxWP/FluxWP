// Flux Wiki: pages, settings and tools. Same screen in wp-admin (wp-embed) and the FluxWP app; every read
// and write goes through the Flux Wiki GraphQL operations (plugins/flx-wiki/graphql/operations).
// General / Appearance / AI
// settings, addresses, bundled guides import, page list with filters, sections and the Markdown editor
// (write / preview / side by side, toolbar, parent, section, status, summary, order).
import { createContext, useContext, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxWikiGuidesImport,
  FlxWikiPageEdit,
  FlxWikiPages,
  FlxWikiPageSave,
  FlxWikiPageTrash,
  FlxWikiPreview,
  FlxWikiScreen,
  FlxWikiSectionDelete,
  FlxWikiSectionSave,
  FlxWikiSettingsSave,
  FlxWikiTree,
} from '@fluxwp/graphql/operations';
import type { FlxSettingsWikiInput, FlxWikiPageStatus, FlxWikiPagesQuery, FlxWikiScreenQuery } from '@fluxwp/graphql/types';
import { Loading, Modal, sanitizeHtml, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './wiki.css';

type Screen = FlxWikiScreenQuery;
type Settings = NonNullable<Screen['flxSettings']['wiki']>;
type Row = FlxWikiPagesQuery['flxWikiPages']['nodes'][number];
type Notice = { kind: 'success' | 'error'; text: string };
type Confirm = { text: string; action: string; run: () => void };
type Tab = 'pages' | 'general' | 'look' | 'ai' | 'tools';

/** wp-admin settings page: the classic WordPress layout (form-table, nav tabs, side panels); the app keeps cards. */
const Classic = createContext(false);

const STATUSES: FlxWikiPageStatus[] = ['PUBLISH', 'DRAFT', 'PENDING', 'PRIVATE'];

export function WikiScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Wiki />
    </FlxGraphQLProvider>
  );
}

const errText = (e: unknown, fallback: string) => (e instanceof Error && e.message ? e.message : fallback);

function Wiki() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxWikiScreen, {});
  // wp-admin: the settings page (pages are listed and edited on the WordPress wiki screens); the app: everything.
  const wp = host.mode === 'wp';
  const [tab, setTab] = useState<Tab>(wp ? 'general' : 'pages');
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);

  const told = (text: string) => {
    setNotice({ kind: 'success', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const refreshScreen = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxWikiScreen) });
  const refreshPages = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxWikiPages) });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data)
    return (
      <div className="flxw-app">
        <div className="flxw-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>
      </div>
    );

  const d = q.data;
  const o = d.flxWikiOverview;
  const s = d.flxSettings.wiki;
  const tabs: [Tab, string][] = [
    ['pages', t('Pages')],
    ['general', t('General')],
    ['look', t('Appearance')],
    ['ai', t('AI')],
    ['tools', t('Addresses and guides')],
  ];

  if (wp) {
    const st = tab === 'look' || tab === 'ai' ? tab : 'general';
    return (
      <Classic.Provider value={true}>
        <Screen
          className="flxw-app"
          icon={host.appIcon}
          title={t('Wiki settings')}
          subtitle={t('Developer wiki: Markdown pages, search, LLM and MCP support')}
          actions={
            <>
              <a className="flxw-btn" href={(host.adminUrl ?? '/wp-admin/') + 'post-new.php?post_type=flx_wiki'}>
                {t('Add page')}
              </a>
              <a className="flxw-btn" href={o.homeUrl} target="_blank" rel="noopener noreferrer">
                {t('Open wiki')}
              </a>
            </>
          }
        >
          <div className="flx-raw">
            {s && s.enabled === false && (
              <div className="notice notice-warning">
                <p>{t('The wiki is closed to visitors; only people who can write pages see it.')}</p>
              </div>
            )}
            {notice && (
              <div className={'notice is-dismissible notice-' + notice.kind}>
                <p>{notice.text}</p>
                <button type="button" className="notice-dismiss" onClick={() => setNotice(null)}>
                  <span className="screen-reader-text">{t('Close')}</span>
                </button>
              </div>
            )}
            <div className="flxwk-admin__grid">
              {s && (
                <SettingsForm
                  key={st}
                  t={t}
                  tab={st}
                  onTab={setTab}
                  s={s}
                  defaultTitle={o.defaultTitle}
                  defaultIntro={o.defaultIntro}
                  siteUrl={o.siteUrl}
                  aiInstalled={o.aiInstalled}
                  aiReady={o.aiReady}
                  onSaved={() => {
                    told(t('Settings saved.'));
                    void refreshScreen();
                  }}
                  onFail={fail}
                />
              )}
              <Tools t={t} o={o} onNotice={setNotice} onChanged={() => void refreshScreen()} />
            </div>
          </div>
        </Screen>
      </Classic.Provider>
    );
  }

  return (
    <Screen
      className="flxw-app"
      icon={host.appIcon}
      title={t('Wiki')}
      actions={
        <>
          <button
            type="button"
            className="flxw-btn"
            onClick={() => {
              setTab('pages');
              setEditing('new');
            }}
          >
            {t('Add page')}
          </button>
          <a className="flxw-btn" href={o.homeUrl} target="_blank" rel="noopener noreferrer">
            {t('Open wiki')}
          </a>
        </>
      }
      notice={
        <>
          {s && s.enabled === false && <div className="flxw-notice is-warn">{t('The wiki is closed to visitors; only people who can write pages see it.')}</div>}
          {notice && (
            <div className={'flxw-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
              <span>{notice.text}</span>
              <button type="button" className="flxw-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
                ×
              </button>
            </div>
          )}
        </>
      }
      tabs={tabs.map(([id, label]) => ({ id, label }))}
      tab={tab}
      onTab={(k) => {
        setTab(k);
        setEditing(null);
      }}
    >
      {tab === 'pages' && editing === null && (
        <PageList
          t={t}
          tn={tn}
          sections={d.flxWikiSections}
          onEdit={setEditing}
          onNotice={setNotice}
          onConfirm={setConfirm}
          onChanged={() => {
            void refreshPages();
            void refreshScreen();
          }}
        />
      )}
      {tab === 'pages' && editing !== null && (
        <Editor
          t={t}
          id={editing}
          sections={d.flxWikiSections}
          onClose={() => setEditing(null)}
          onSaved={(text) => {
            told(text);
            void refreshPages();
            void refreshScreen();
          }}
          onNotice={setNotice}
          onConfirm={setConfirm}
        />
      )}
      {tab !== 'pages' && tab !== 'tools' && s && (
        <SettingsForm
          key={tab}
          t={t}
          tab={tab}
          s={s}
          defaultTitle={o.defaultTitle}
          defaultIntro={o.defaultIntro}
          siteUrl={o.siteUrl}
          aiInstalled={o.aiInstalled}
          aiReady={o.aiReady}
          onSaved={() => {
            told(t('Settings saved.'));
            void refreshScreen();
          }}
          onFail={fail}
        />
      )}
      {tab === 'tools' && (
        <Tools
          t={t}
          o={o}
          onNotice={setNotice}
          onChanged={() => {
            void refreshScreen();
            void refreshPages();
          }}
        />
      )}

      {confirm && (
        <Modal title={confirm.action} onClose={() => setConfirm(null)}>
          <p className="flxw-confirm">{confirm.text}</p>
          <div className="flxw-modal-actions">
            <button type="button" className="flxw-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxw-btn is-danger"
              onClick={() => {
                confirm.run();
                setConfirm(null);
              }}
            >
              {confirm.action}
            </button>
          </div>
        </Modal>
      )}
    </Screen>
  );
}

interface Common {
  t: Translate;
}

function Field({ id, label, children, hint }: { id: string; label: string; children: ReactNode; hint?: string }) {
  if (useContext(Classic)) {
    return (
      <tr>
        <th scope="row">
          <label htmlFor={id}>{label}</label>
        </th>
        <td>
          {children}
          {hint && <p className="description">{hint}</p>}
        </td>
      </tr>
    );
  }
  return (
    <div className="flxw-field">
      <label htmlFor={id}>{label}</label>
      <div>
        {children}
        {hint && <p className="flxw-desc">{hint}</p>}
      </div>
    </div>
  );
}

/** Classic page: the fields sit in a form-table; the app: plain field rows. */
function Rows({ children }: { children: ReactNode }) {
  return useContext(Classic) ? (
    <table className="form-table" role="presentation">
      <tbody>{children}</tbody>
    </table>
  ) : (
    <>{children}</>
  );
}

/* ------------------------------ settings ------------------------------ */

function SettingsForm({
  t,
  tab,
  onTab,
  s,
  defaultTitle,
  defaultIntro,
  siteUrl,
  aiInstalled,
  aiReady,
  onSaved,
  onFail,
}: Common & {
  tab: 'general' | 'look' | 'ai';
  /** Classic page only: the nav tabs sit inside the form. */
  onTab?: (tab: Tab) => void;
  s: Settings;
  defaultTitle: string;
  defaultIntro: string;
  siteUrl: string;
  aiInstalled: boolean;
  aiReady: boolean;
  onSaved: () => void;
  onFail: (e: unknown) => void;
}) {
  const [v, setV] = useState({
    enabled: s.enabled ?? true,
    title: s.title ?? '',
    intro: s.intro ?? '',
    base: s.base ?? 'wiki',
    aiLinks: s.aiLinks ?? true,
    editLink: s.editLink ?? true,
    accent: s.accent ?? '',
    width: s.width ?? 'NORMAL',
    nav: s.nav ?? true,
    toc: s.toc ?? true,
    ask: s.ask ?? 'USERS',
    askLimit: String(s.askLimit ?? 20),
  });
  const [err, setErr] = useState('');
  const set = <K extends keyof typeof v>(k: K, val: (typeof v)[K]) => setV((o) => ({ ...o, [k]: val }));
  const save = useFlxMutation(FlxWikiSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxSettingsUpdate;
      if (p?.errors.length) return setErr(p.errors.map((e) => e.message).join(' '));
      setErr('');
      onSaved();
    },
    onError: onFail,
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    let wiki: FlxSettingsWikiInput;
    if (tab === 'general') {
      wiki = { enabled: v.enabled, title: v.title.trim(), intro: v.intro.trim(), base: v.base.trim().toLowerCase() || 'wiki', aiLinks: v.aiLinks, editLink: v.editLink };
    } else if (tab === 'look') {
      wiki = { accent: v.accent, width: v.width, nav: v.nav, toc: v.toc };
    } else {
      const n = Number.parseInt(v.askLimit, 10);
      wiki = { ask: v.ask, askLimit: Number.isFinite(n) ? Math.max(1, Math.min(500, n)) : 20 };
    }
    save.mutate({ input: { wiki } });
  };
  const themeColour = v.accent === '';
  const classic = useContext(Classic);
  const c = (a: string, b: string) => (classic ? a : b);

  return (
    <form className={c('flxwk-panel', 'flxw-card')} onSubmit={submit}>
      {classic && onTab && (
        <nav className="nav-tab-wrapper flxwk-tabs">
          {(
            [
              ['general', t('General')],
              ['look', t('Appearance')],
              ['ai', t('AI')],
            ] as const
          ).map(([k, label]) => (
            <a
              key={k}
              className={'nav-tab' + (k === tab ? ' nav-tab-active' : '')}
              href={'#' + k}
              onClick={(e) => {
                e.preventDefault();
                onTab(k);
              }}
            >
              {label}
            </a>
          ))}
        </nav>
      )}
      {err && (
        <div className={c('notice notice-error inline', 'flxw-notice is-error')} role="alert">
          {classic ? <p>{err}</p> : err}
        </div>
      )}
      {tab === 'general' && (
        <Rows>
          <Field id="flxw-enabled" label={t('Wiki')} hint={t('When closed, wiki addresses, .md and llms.txt return 404 to visitors and leave the sitemap. People who can write pages still see it.')}>
            <label className="flxw-check">
              <input id="flxw-enabled" type="checkbox" checked={v.enabled} onChange={(e) => set('enabled', e.currentTarget.checked)} /> {t('Wiki open to visitors')}
            </label>
          </Field>
          <Field id="flxw-title" label={t('Wiki name')}>
            <input id="flxw-title" className={c('regular-text', 'flxw-text')} value={v.title} placeholder={defaultTitle} onChange={(e) => set('title', e.currentTarget.value)} />
          </Field>
          <Field id="flxw-intro" label={t('Intro')} hint={t('Used on the wiki home, in the search engine description and in llms.txt.')}>
            <textarea id="flxw-intro" className={c('large-text', 'flxw-text')} rows={2} value={v.intro} placeholder={defaultIntro} onChange={(e) => set('intro', e.currentTarget.value)} />
          </Field>
          <Field id="flxw-base" label={t('Address')} hint={t('Lowercase letters, digits and hyphens. Old addresses stop working when you change it.')}>
            <code>{siteUrl}</code>
            <input id="flxw-base" className={c('regular-text flxwk-base', 'flxw-base')} value={v.base} placeholder="wiki" pattern="[a-z0-9\-]+" onChange={(e) => set('base', e.currentTarget.value)} />
            <code>/</code>
          </Field>
          <Field id="flxw-ailinks" label={t('Page buttons')}>
            <label className="flxw-check">
              <input id="flxw-ailinks" type="checkbox" checked={v.aiLinks} onChange={(e) => set('aiLinks', e.currentTarget.checked)} /> {t('ChatGPT, Claude, Google AI and Perplexity links in the "Ask AI" menu')}
            </label>
            <label className="flxw-check">
              <input type="checkbox" checked={v.editLink} onChange={(e) => set('editLink', e.currentTarget.checked)} /> {t('"Edit" button for editors')}
            </label>
          </Field>
        </Rows>
      )}
      {tab === 'look' && (
        <Rows>
          <Field id="flxw-accent" label={t('Accent colour')} hint={t('Links, the selected menu item and accent buttons.')}>
            <label className="flxw-check">
              <input type="checkbox" checked={themeColour} onChange={(e) => set('accent', e.currentTarget.checked ? '' : '#3b82f6')} /> {t('Use the theme colour')}
            </label>
            <input id="flxw-accent" type="color" value={v.accent || '#3b82f6'} disabled={themeColour} onChange={(e) => set('accent', e.currentTarget.value)} />
          </Field>
          <Field id="flxw-width" label={t('Width')}>
            <label className={c('flxwk-radio', 'flxw-check')}>
              <input type="radio" name="flxw-width" checked={v.width === 'NORMAL'} onChange={() => set('width', 'NORMAL')} /> {t('Normal')}
            </label>
            <label className={c('flxwk-radio', 'flxw-check')}>
              <input type="radio" name="flxw-width" checked={v.width === 'WIDE'} onChange={() => set('width', 'WIDE')} /> {t('Wide (for long code and tables)')}
            </label>
          </Field>
          <Field id="flxw-nav" label={t('Page layout')}>
            <label className="flxw-check">
              <input id="flxw-nav" type="checkbox" checked={v.nav} onChange={(e) => set('nav', e.currentTarget.checked)} /> {t('Page menu and search on the left')}
            </label>
            <label className="flxw-check">
              <input type="checkbox" checked={v.toc} onChange={(e) => set('toc', e.currentTarget.checked)} /> {t('"On this page" (contents) on the right')}
            </label>
          </Field>
        </Rows>
      )}
      {tab === 'ai' && (
        <>
          {!aiInstalled && <p className={c('flxwk-note', 'flxw-note')}>{t('Flux AI is not installed. Once installed, visitors can use "Ask the wiki", which answers from the wiki content.')}</p>}
          {aiInstalled && !aiReady && <p className={c('flxwk-note', 'flxw-note')}>{t('Flux AI has no key or the Flux Wiki system is off (Flux AI › Connected systems).')}</p>}
          <Rows>
            <Field id="flxw-ask" label={t('Who can ask')} hint={t('Every question counts towards the Flux AI spending limit; keep the hourly limit low with "Everyone".')}>
              {(
                [
                  ['OFF', t('Off')],
                  ['USERS', t('Logged-in users')],
                  ['ALL', t('Everyone (including visitors)')],
                ] as const
              ).map(([k, label]) => (
                <label key={k} className={c('flxwk-radio', 'flxw-check')}>
                  <input type="radio" name="flxw-ask" checked={v.ask === k} onChange={() => set('ask', k)} /> {label}
                </label>
              ))}
            </Field>
            <Field id="flxw-limit" label={t('Hourly question limit')}>
              <input id="flxw-limit" type="number" min={1} max={500} className={c('small-text', 'flxw-num')} value={v.askLimit} onChange={(e) => set('askLimit', e.currentTarget.value)} />{' '}
              {t('per person (unlimited for administrators)')}
            </Field>
          </Rows>
        </>
      )}
      <div className={c('submit', 'flxw-actions')}>
        <button type="submit" className={c('button button-primary', 'flxw-btn is-primary')} disabled={save.isPending}>
          {t('Save')}
        </button>
      </div>
    </form>
  );
}

/* ------------------------------- tools -------------------------------- */

function Tools({ t, o, onNotice, onChanged }: Common & { o: Screen['flxWikiOverview']; onNotice: (n: Notice) => void; onChanged: () => void }) {
  const imp = useFlxMutation(FlxWikiGuidesImport, {
    onSuccess: (r) => {
      const p = r.flxWikiGuidesImport;
      if (!p?.result) return onNotice({ kind: 'error', text: p?.errors[0]?.message ?? t('The action could not be completed.') });
      onNotice({ kind: 'success', text: t('Guides imported: %1$d new, %2$d updated, %3$d skipped (edited by hand or up to date).', p.result.created, p.result.updated, p.result.skipped) });
      onChanged();
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const rel = (u: string) => u.replace(/^https?:\/\/[^/]+/, '');
  const classic = useContext(Classic);
  const c = (a: string, b: string) => (classic ? a : b);
  return (
    <div className={c('flxwk-side', 'flxw-grid')}>
      <div className={c('flxwk-panel', 'flxw-card')}>
        <h2>{t('Addresses')}</h2>
        <ul className={c('flxwk-urls', 'flxw-urls')}>
          <li>
            <span>{t('Wiki')}</span>{' '}
            <a href={o.homeUrl} target="_blank" rel="noopener noreferrer">
              <code>{rel(o.homeUrl)}</code>
            </a>
          </li>
          <li>
            <span>llms.txt</span>{' '}
            <a href={o.llmsUrl} target="_blank" rel="noopener noreferrer">
              <code>/{o.base}/llms.txt</code>
            </a>
          </li>
          <li>
            <span>llms-full.txt</span>{' '}
            <a href={o.llmsFullUrl} target="_blank" rel="noopener noreferrer">
              <code>/{o.base}/llms-full.txt</code>
            </a>
          </li>
          <li>
            <span>{t('Search API')}</span> <code>{o.searchApi}</code>
          </li>
          <li>
            <span>MCP</span> {o.mcpEnabled ? <code>{o.mcpTools.join(', ')}</code> : <em>{t('Flux AI › MCP is off')}</em>}
          </li>
        </ul>
        <p className={c('description', 'flxw-desc')}>{t('Add .md to any page address to get it as Markdown. With Flux SEO installed, a "Wiki" section is added to the site\'s /llms.txt.')}</p>
      </div>
      <div className={c('flxwk-panel', 'flxw-card')}>
        <h2>{t('Bundled guides')}</h2>
        <p>
          {t(
            'The plugin ships %d guides: writing plugins and themes, AI-enabled plugins, MCP tools, settings schema, translation, versions and releases. Missing pages are added; pages you have not edited are updated to the new version.',
            o.guideCount,
          )}
        </p>
        <button type="button" className={c('button', 'flxw-btn')} disabled={imp.isPending} onClick={() => imp.mutate({})}>
          {t('Import / update guides')}
        </button>
        <p className={c('description', 'flxw-desc')}>{t('%d published pages.', o.publishedCount)}</p>
      </div>
    </div>
  );
}

/* ------------------------------ page list ----------------------------- */

function statusLabel(t: Translate, s: FlxWikiPageStatus): string {
  return { PUBLISH: t('Published'), DRAFT: t('Draft'), PENDING: t('Pending review'), PRIVATE: t('Private') }[s];
}

function PageList({
  t,
  tn,
  sections,
  onEdit,
  onNotice,
  onConfirm,
  onChanged,
}: Common & {
  tn: (single: string, plural: string, n: number, ...args: (string | number)[]) => string;
  sections: Screen['flxWikiSections'];
  onEdit: (id: number) => void;
  onNotice: (n: Notice) => void;
  onConfirm: (c: Confirm) => void;
  onChanged: () => void;
}) {
  const [search, setSearch] = useState('');
  const [term, setTerm] = useState('');
  const [status, setStatus] = useState<FlxWikiPageStatus | ''>('');
  const [section, setSection] = useState('');
  const [after, setAfter] = useState<string[]>([]);
  useEffect(() => {
    const h = window.setTimeout(() => {
      setTerm(search.trim());
      setAfter([]);
    }, 250);
    return () => window.clearTimeout(h);
  }, [search]);
  const cursor = after[after.length - 1] ?? null;
  const q = useFlxQuery(FlxWikiPages, { first: 20, after: cursor, search: term || null, statuses: status ? [status] : null, section: section || null });
  const trash = useFlxMutation(FlxWikiPageTrash, {
    onSuccess: (r) => {
      if (r.flxWikiPageTrash?.errors.length) return onNotice({ kind: 'error', text: r.flxWikiPageTrash.errors[0]?.message ?? '' });
      onNotice({ kind: 'success', text: t('The page was moved to the trash.') });
      onChanged();
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const list = q.data?.flxWikiPages;
  return (
    <>
      <div className="flxw-filters">
        <input type="search" className="flxw-text" value={search} placeholder={t('Search pages…')} aria-label={t('Search pages…')} onChange={(e) => setSearch(e.currentTarget.value)} />
        <select
          value={status}
          aria-label={t('Status')}
          onChange={(e) => {
            setStatus(e.currentTarget.value as FlxWikiPageStatus | '');
            setAfter([]);
          }}
        >
          <option value="">{t('All statuses')}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {statusLabel(t, s)}
            </option>
          ))}
        </select>
        <select
          value={section}
          aria-label={t('Section')}
          onChange={(e) => {
            setSection(e.currentTarget.value);
            setAfter([]);
          }}
        >
          <option value="">{t('All sections')}</option>
          {sections.map((s) => (
            <option key={s.databaseId} value={s.slug}>
              {s.name}
            </option>
          ))}
        </select>
      </div>
      <div className="flxw-card flxw-listcard">
        {q.isLoading && <Loading />}
        {q.isError && <p className="flxw-note">{q.error.message}</p>}
        {list && list.nodes.length === 0 && <p className="flxw-empty">{t('No pages found')}</p>}
        {list && list.nodes.length > 0 && (
          <table className="flxw-table">
            <thead>
              <tr>
                <th scope="col">{t('Title')}</th>
                <th scope="col">{t('Section')}</th>
                <th scope="col">{t('Status')}</th>
                <th scope="col">{t('Updated')}</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {list.nodes.map((p: Row) => (
                <tr key={p.databaseId}>
                  <td data-label={t('Title')}>
                    <button type="button" className="flxw-link" onClick={() => onEdit(p.databaseId)}>
                      {p.title || t('(no title)')}
                    </button>
                    <code className="flxw-path">/{p.path}</code>
                  </td>
                  <td data-label={t('Section')}>{p.section?.name ?? '—'}</td>
                  <td data-label={t('Status')}>
                    <span className={'flxw-badge is-' + p.status.toLowerCase()}>{statusLabel(t, p.status)}</span>
                  </td>
                  <td data-label={t('Updated')}>{p.modified?.relative ?? ''}</td>
                  <td className="flxw-rowactions">
                    {p.status === 'PUBLISH' && (
                      <a href={p.url} target="_blank" rel="noopener noreferrer">
                        {t('View')}
                      </a>
                    )}
                    {p.markdownUrl && p.status === 'PUBLISH' && (
                      <a href={p.markdownUrl} target="_blank" rel="noopener noreferrer">
                        .md
                      </a>
                    )}
                    {p.canEdit && (
                      <button
                        type="button"
                        className="flxw-link is-danger"
                        onClick={() => onConfirm({ text: t('Move this page to the trash?'), action: t('Move to trash'), run: () => trash.mutate({ input: { id: String(p.databaseId) } }) })}
                      >
                        {t('Trash')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {list && (
          <div className="flxw-pager">
            <span>{tn('%s page', '%s pages', list.totalCount ?? list.nodes.length, String(list.totalCount ?? list.nodes.length))}</span>
            <button type="button" className="flxw-btn" disabled={after.length === 0} onClick={() => setAfter((a) => a.slice(0, -1))}>
              {t('Previous')}
            </button>
            <button type="button" className="flxw-btn" disabled={!list.pageInfo.hasNextPage} onClick={() => list.pageInfo.endCursor && setAfter((a) => [...a, list.pageInfo.endCursor ?? ''])}>
              {t('Next')}
            </button>
          </div>
        )}
      </div>
      <Sections t={t} sections={sections} onNotice={onNotice} onConfirm={onConfirm} onChanged={onChanged} />
    </>
  );
}

/* ------------------------------ sections ------------------------------ */

function Sections({ t, sections, onNotice, onConfirm, onChanged }: Common & { sections: Screen['flxWikiSections']; onNotice: (n: Notice) => void; onConfirm: (c: Confirm) => void; onChanged: () => void }) {
  const [name, setName] = useState('');
  const [orders, setOrders] = useState<Record<number, string>>({});
  const done = (ok: string) => (r: { errors: { message: string }[] } | null | undefined) => {
    if (r?.errors.length) return onNotice({ kind: 'error', text: r.errors.map((e) => e.message).join(' ') });
    onNotice({ kind: 'success', text: ok });
    onChanged();
  };
  const save = useFlxMutation(FlxWikiSectionSave, {
    onSuccess: (r) => done(t('Section saved.'))(r.flxWikiSectionSave),
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const del = useFlxMutation(FlxWikiSectionDelete, {
    onSuccess: (r) => done(t('Section deleted; its pages stay on the wiki.'))(r.flxWikiSectionDelete),
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  return (
    <div className="flxw-card">
      <h2>{t('Sections')}</h2>
      <p className="flxw-desc">{t('Lower numbers come first on the wiki home and in the menu.')}</p>
      <ul className="flxw-sections">
        {sections.map((s) => (
          <li key={s.databaseId}>
            <span className="flxw-secname">{s.name}</span>
            <span className="flxw-muted">{s.pageCount}</span>
            <label className="flxw-inline">
              {t('Order')}
              <input
                type="number"
                className="flxw-num"
                value={orders[s.databaseId] ?? String(s.order)}
                onChange={(e) => {
                  const v = e.currentTarget.value;
                  setOrders((o) => ({ ...o, [s.databaseId]: v }));
                }}
              />
            </label>
            <button
              type="button"
              className="flxw-btn"
              disabled={save.isPending || orders[s.databaseId] === undefined || orders[s.databaseId] === String(s.order)}
              onClick={() => save.mutate({ input: { id: s.databaseId, name: s.name, order: Number.parseInt(orders[s.databaseId] ?? '0', 10) || 0 } })}
            >
              {t('Save')}
            </button>
            <button
              type="button"
              className="flxw-link is-danger"
              onClick={() => onConfirm({ text: t('Delete this section? Its pages stay and move out of any section.'), action: t('Delete section'), run: () => del.mutate({ input: { id: s.databaseId } }) })}
            >
              {t('Delete section')}
            </button>
          </li>
        ))}
      </ul>
      <form
        className="flxw-inlineform"
        onSubmit={(e) => {
          e.preventDefault();
          if (name.trim()) {
            save.mutate({ input: { name: name.trim() } });
            setName('');
          }
        }}
      >
        <input className="flxw-text" value={name} placeholder={t('New section')} aria-label={t('New section')} onChange={(e) => setName(e.currentTarget.value)} />
        <button type="submit" className="flxw-btn" disabled={save.isPending || !name.trim()}>
          {t('Add section')}
        </button>
      </form>
    </div>
  );
}

/* ------------------------------- editor ------------------------------- */

type Tool = 'h2' | 'h3' | 'b' | 'i' | 'code' | 'pre' | 'link' | 'wiki' | 'ul' | 'note' | 'table';

/** Inserts Markdown around the selection. */
function applyTool(tool: Tool, text: string, a: number, b: number, t: Translate): { text: string; start: number; end: number } {
  const sel = text.slice(a, b);
  const wrap = (l: string, r: string, ph: string) => {
    const mid = sel || ph;
    return { text: text.slice(0, a) + l + mid + r + text.slice(b), start: a + l.length, end: a + l.length + mid.length };
  };
  const line = (prefix: string, ph: string) => {
    const mid = sel || ph;
    const lead = a > 0 && text[a - 1] !== '\n' ? '\n' : '';
    return { text: text.slice(0, a) + lead + prefix + mid + text.slice(b), start: a + lead.length + prefix.length, end: a + lead.length + prefix.length + mid.length };
  };
  switch (tool) {
    case 'h2':
      return line('## ', t('Heading'));
    case 'h3':
      return line('### ', t('Subheading'));
    case 'b':
      return wrap('**', '**', t('text'));
    case 'i':
      return wrap('*', '*', t('text'));
    case 'code':
      return wrap('`', '`', t('code'));
    case 'pre':
      return wrap('\n```\n', '\n```\n', t('code'));
    case 'link':
      return wrap('[', '](https://)', t('link'));
    case 'wiki':
      return wrap('[[', ']]', t('page-slug'));
    case 'ul':
      return line('- ', t('item'));
    case 'note':
      return line('> [!NOTE]\n> ', t('Note'));
    case 'table':
      return wrap('\n| A | B |\n|---|---|\n| ', ' | |\n', '1');
  }
}

function Editor({
  t,
  id,
  sections,
  onClose,
  onSaved,
  onNotice,
  onConfirm,
}: Common & {
  id: number | 'new';
  sections: Screen['flxWikiSections'];
  onClose: () => void;
  onSaved: (text: string) => void;
  onNotice: (n: Notice) => void;
  onConfirm: (c: Confirm) => void;
}) {
  const existing = id === 'new' ? null : id;
  const page = useFlxQuery(FlxWikiPageEdit, { id: String(existing ?? 0) }, { enabled: existing !== null });
  const tree = useFlxQuery(FlxWikiTree, {});
  const [f, setF] = useState({ title: '', slug: '', parent: '0', section: '', status: 'DRAFT' as FlxWikiPageStatus, summary: '', order: '0', markdown: '' });
  const [loaded, setLoaded] = useState(existing === null);
  const [view, setView] = useState<'write' | 'preview' | 'split'>('write');
  const [html, setHtml] = useState('');
  const [fieldErr, setFieldErr] = useState<Record<string, string>>({});
  const area = useRef<HTMLTextAreaElement | null>(null);
  const p = page.data?.flxWikiPage;
  useEffect(() => {
    if (!loaded && p) {
      setF({ title: p.title, slug: p.slug, parent: String(p.parentId ?? 0), section: p.section?.name ?? '', status: p.status, summary: p.summary, order: String(p.menuOrder), markdown: p.markdown });
      setLoaded(true);
    }
  }, [p, loaded]);
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((o) => ({ ...o, [k]: v }));
  const preview = useFlxMutation(FlxWikiPreview, { onSuccess: (r) => setHtml(r.flxWikiPreview?.rendered?.html ?? ''), onError: () => setHtml('') });
  const previewMutate = preview.mutate;
  useEffect(() => {
    if (view === 'write') return;
    if (!f.markdown.trim()) {
      setHtml('');
      return;
    }
    const h = window.setTimeout(() => previewMutate({ input: { markdown: f.markdown } }), 350);
    return () => window.clearTimeout(h);
  }, [view, f.markdown, previewMutate]);
  const save = useFlxMutation(FlxWikiPageSave, {
    onSuccess: (r) => {
      const res = r.flxWikiPageSave;
      if (res?.errors.length) {
        setFieldErr(Object.fromEntries(res.errors.map((e) => [e.field ?? '', e.message])));
        return onNotice({ kind: 'error', text: res.errors.map((e) => e.message).join(' ') });
      }
      onSaved(t('Page saved.'));
      onClose();
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const trash = useFlxMutation(FlxWikiPageTrash, {
    onSuccess: () => {
      onSaved(t('The page was moved to the trash.'));
      onClose();
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  if (existing !== null && page.isLoading) return <Loading />;
  if (existing !== null && !p)
    return (
      <div className="flxw-card">
        <p className="flxw-note">{t('Wiki page not found.')}</p>
        <button type="button" className="flxw-btn" onClick={onClose}>
          {t('Back to pages')}
        </button>
      </div>
    );

  const parents = (tree.data?.flxWikiTree ?? []).flatMap((g) => g.pages).filter((e) => e.page.databaseId !== existing);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    setFieldErr({});
    save.mutate({
      input: {
        id: existing === null ? null : String(existing),
        title: f.title,
        markdown: f.markdown,
        slug: f.slug,
        parentId: Number.parseInt(f.parent, 10) || 0,
        section: f.section,
        summary: f.summary,
        status: f.status,
        menuOrder: Number.parseInt(f.order, 10) || 0,
      },
    });
  };
  const tools: [Tool, string, string][] = [
    ['h2', 'H2', t('Heading')],
    ['h3', 'H3', t('Subheading')],
    ['b', 'B', t('Bold')],
    ['i', 'I', t('Italic')],
    ['code', '`', t('Inline code')],
    ['pre', '```', t('Code block')],
    ['link', '🔗', t('Link')],
    ['wiki', '[[ ]]', t('Wiki link')],
    ['ul', '•', t('List')],
    ['note', '!', t('Callout')],
    ['table', '▦', t('Table')],
  ];
  const insert = (tool: Tool) => {
    const el = area.current;
    const a = el?.selectionStart ?? f.markdown.length;
    const b = el?.selectionEnd ?? f.markdown.length;
    const r = applyTool(tool, f.markdown, a, b, t);
    set('markdown', r.text);
    window.setTimeout(() => {
      el?.focus();
      el?.setSelectionRange(r.start, r.end);
    }, 0);
  };
  const err = (k: string) =>
    fieldErr[k] ? (
      <p className="flxw-err" role="alert">
        {fieldErr[k]}
      </p>
    ) : null;

  return (
    <form className="flxw-card flxw-editor" onSubmit={submit}>
      <div className="flxw-edhead">
        <h2>{existing === null ? t('New wiki page') : t('Edit wiki page')}</h2>
        <button type="button" className="flxw-link" onClick={onClose}>
          {t('Back to pages')}
        </button>
      </div>
      <Field id="flxw-t" label={t('Title')}>
        <input id="flxw-t" className="flxw-text" required value={f.title} onChange={(e) => set('title', e.currentTarget.value)} />
        {err('title')}
      </Field>
      <div className="flxw-meta">
        <Field id="flxw-slug" label={t('Short name')}>
          <input id="flxw-slug" className="flxw-text" value={f.slug} onChange={(e) => set('slug', e.currentTarget.value)} />
        </Field>
        <Field id="flxw-parent" label={t('Parent page')}>
          <select id="flxw-parent" value={f.parent} onChange={(e) => set('parent', e.currentTarget.value)}>
            <option value="0">{t('(top level)')}</option>
            {parents.map((e) => (
              <option key={e.page.databaseId} value={e.page.databaseId}>
                {'— '.repeat(e.depth) + e.page.title}
              </option>
            ))}
          </select>
          {err('parent')}
        </Field>
        <Field id="flxw-section" label={t('Section')} hint={t('Only top-level pages need a section.')}>
          <input id="flxw-section" className="flxw-text" list="flxw-sections" value={f.section} onChange={(e) => set('section', e.currentTarget.value)} />
          <datalist id="flxw-sections">
            {sections.map((s) => (
              <option key={s.databaseId} value={s.name} />
            ))}
          </datalist>
        </Field>
        <Field id="flxw-status" label={t('Status')}>
          <select id="flxw-status" value={f.status} onChange={(e) => set('status', e.currentTarget.value as FlxWikiPageStatus)}>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {statusLabel(t, s)}
              </option>
            ))}
          </select>
        </Field>
        <Field id="flxw-order" label={t('Order')}>
          <input id="flxw-order" type="number" className="flxw-num" value={f.order} onChange={(e) => set('order', e.currentTarget.value)} />
        </Field>
      </div>
      <Field id="flxw-sum" label={t('Summary')} hint={t('One sentence, used as the search engine description. Empty: the first paragraph is used.')}>
        <textarea id="flxw-sum" className="flxw-text" rows={2} value={f.summary} onChange={(e) => set('summary', e.currentTarget.value)} />
      </Field>
      <div className="flxw-ed">
        <div className="flxw-edbar">
          <div className="flxw-edtabs" role="tablist">
            {(
              [
                ['write', t('Write')],
                ['preview', t('Preview')],
                ['split', t('Side by side')],
              ] as const
            ).map(([k, label]) => (
              <button key={k} type="button" role="tab" aria-selected={view === k} className={view === k ? 'is-on' : undefined} onClick={() => setView(k)}>
                {label}
              </button>
            ))}
          </div>
          <div className="flxw-edtools">
            {tools.map(([k, label, title]) => (
              <button key={k} type="button" title={title} aria-label={title} onClick={() => insert(k)} disabled={view === 'preview'}>
                {label}
              </button>
            ))}
          </div>
        </div>
        <div className={'flxw-edbody is-' + view}>
          {view !== 'preview' && (
            <textarea
              ref={area}
              className="flxw-md"
              spellCheck
              value={f.markdown}
              aria-label={t('Content')}
              placeholder={t('Write Markdown. ## Heading, ```php code```, [[page-slug]] wiki link, > [!NOTE] callout.')}
              onChange={(e) => set('markdown', e.currentTarget.value)}
            />
          )}
          {view !== 'write' && <div className="flxw-preview" dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) || '<p class="flxw-muted"></p>' }} />}
        </div>
        <p className="flxw-desc">
          {t('Markdown: GitHub flavour (tables, code blocks). Wiki links [[slug]] or [[slug|label]]. Callouts: > [!NOTE], [!TIP], [!IMPORTANT], [!WARNING], [!CAUTION]. Do not repeat the title in the content.')}
        </p>
      </div>
      <div className="flxw-actions">
        <button type="submit" className="flxw-btn is-primary" disabled={save.isPending}>
          {t('Save')}
        </button>
        <button type="button" className="flxw-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        {p?.status === 'PUBLISH' && (
          <a className="flxw-btn" href={p.url} target="_blank" rel="noopener noreferrer">
            {t('View')}
          </a>
        )}
        {p?.editUrl && (
          <a className="flxw-btn" href={p.editUrl}>
            {t('WordPress editor')}
          </a>
        )}
        {existing !== null && p?.canEdit && (
          <button
            type="button"
            className="flxw-btn is-danger"
            disabled={trash.isPending}
            onClick={() => onConfirm({ text: t('Move this page to the trash?'), action: t('Move to trash'), run: () => trash.mutate({ input: { id: String(existing) } }) })}
          >
            {t('Trash')}
          </button>
        )}
      </div>
    </form>
  );
}
