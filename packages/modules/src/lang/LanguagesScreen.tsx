// Flux Lang › Languages. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes
// through the Flux Lang GraphQL operations (plugins/flx-lang/graphql/operations).
// The language table (active, name, flag, locale, default,
// remove, add from the catalog), translated content types, fallback, switcher style and place, the
// automatic language for visitors (mode, suggested language, home countries, country info), the site
// texts per language and the AI translation note.
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxLanguageSettingsScreen, FlxLanguageSettingsUpdate } from '@fluxwp/graphql/operations';
import type { FlxLanguageSettingsFieldsFragment, FlxLanguageSuggestMode, FlxLanguageSwitcherPlace, FlxLanguageSwitcherStyle } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import './lang.css';

type Settings = FlxLanguageSettingsFieldsFragment;
type Notice = { kind: 'success' | 'error'; text: string };

interface Row {
  code: string;
  name: string;
  flag: string;
  locale: string;
  active: boolean;
  remove: boolean;
}

interface Draft {
  rows: Row[];
  def: string;
  postTypes: string[];
  fallback: boolean;
  suggest: FlxLanguageSuggestMode;
  suggestTo: string;
  homeCountries: string;
  switcher: FlxLanguageSwitcherStyle;
  switcherPlace: FlxLanguageSwitcherPlace;
  /** language → text key → value */
  texts: Record<string, Record<string, string>>;
}

const draftOf = (s: Settings): Draft => {
  const texts: Draft['texts'] = {};
  for (const x of s.texts) (texts[x.lang] ??= {})[x.key] = x.value;
  return {
    rows: s.languages.map((l) => ({ code: l.code, name: l.name, flag: l.flag ?? '', locale: l.locale, active: l.active, remove: false })),
    def: s.defaultLanguage,
    postTypes: s.postTypes,
    fallback: s.fallback,
    suggest: s.suggest,
    suggestTo: s.suggestTo,
    homeCountries: s.homeCountries.join(', '),
    switcher: s.switcher,
    switcherPlace: s.switcherPlace,
    texts,
  };
};

export function LanguagesScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Languages />
    </FlxGraphQLProvider>
  );
}

function Languages() {
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxLanguageSettingsScreen, {});
  const [draft, setDraft] = useState<Draft | null>(null);
  const [dirty, setDirty] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [adding, setAdding] = useState('');

  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data.flxLanguageSettings));
  }, [q.data, dirty]);

  const fail = (e: unknown) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
  const save = useFlxMutation(FlxLanguageSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxLanguageSettingsUpdate;
      if (!p) return fail(null);
      if (p.errors.length || !p.settings) return setNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      qc.setQueryData(flxQueryKey(scope, FlxLanguageSettingsScreen, {}), { flxLanguageSettings: p.settings });
      setDirty(false);
      setDraft(draftOf(p.settings));
      setNotice({ kind: 'success', text: t('Saved.') });
      window.scrollTo?.({ top: 0, behavior: 'smooth' });
    },
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxl-app"><div className="flxl-notice is-error">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const s = q.data.flxLanguageSettings;
  const change = (patch: Partial<Draft>) => {
    setDraft((o) => o && { ...o, ...patch });
    setDirty(true);
  };
  const setRow = (code: string, patch: Partial<Row>) => change({ rows: draft.rows.map((r) => (r.code === code ? { ...r, ...patch } : r)) });
  const others = draft.rows.filter((r) => !r.remove && r.code !== draft.def);
  const kept = draft.rows.filter((r) => !r.remove);
  const addable = s.catalog.filter((c) => !draft.rows.some((r) => r.code === c.code));

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const codes = new Set(kept.map((r) => r.code));
    save.mutate({
      input: {
        languages: kept.map((r) => ({ code: r.code, name: r.name, locale: r.locale, flag: r.flag, active: r.code === draft.def || r.active })),
        defaultLanguage: codes.has(draft.def) ? draft.def : kept[0]?.code,
        postTypes: draft.postTypes,
        fallback: draft.fallback,
        suggest: draft.suggest,
        suggestTo: codes.has(draft.suggestTo) ? draft.suggestTo : (kept[0]?.code ?? draft.suggestTo),
        homeCountries: draft.homeCountries.split(/[\s,;]+/).filter(Boolean),
        switcher: draft.switcher,
        switcherPlace: draft.switcherPlace,
        texts: Object.entries(draft.texts).flatMap(([lang, vals]) => (codes.has(lang) && lang !== draft.def ? Object.entries(vals).map(([key, value]) => ({ lang, key, value })) : [])),
      },
    });
  };

  const addLanguage = (code: string) => {
    const c = s.catalog.find((x) => x.code === code);
    setAdding('');
    // Added inactive: prepare the content first, then activate it.
    if (c) change({ rows: [...draft.rows, { code: c.code, name: c.name, flag: c.flag, locale: c.locale, active: false, remove: false }] });
  };

  return (
    <div className="flxl-app">
      <div className="flxl-head">
        <h1>{t("Languages")}</h1>
      </div>
      {notice && (
        <div className={'flxl-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxl-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      <p className="flxl-lead">{t("The default language has no prefix in the address (example.org/hakkimizda/); other languages open with a prefix such as /en/. Each language version of a content is a separate page/post: create it from the language badge in the list or from the Language box in the editor. Theme and plugin interface texts use their own translations.")}</p>

      <form onSubmit={submit}>
        <h2>{t("Languages")}</h2>
        <div className="flxl-card flxl-table-wrap">
          <table className="flxl-langs">
            <thead>
              <tr>
                <th>{t("Active")}</th>
                <th>{t("Code / address")}</th>
                <th>{t("Name")}</th>
                <th>{t("Flag")}</th>
                <th>{t("WordPress locale")}</th>
                <th>{t("Default")}</th>
                <th>{t("Remove")}</th>
              </tr>
            </thead>
            <tbody>
              {draft.rows.map((r) => {
                const isDef = r.code === draft.def;
                return (
                  <tr key={r.code} className={r.remove ? 'is-removed' : undefined}>
                    <td data-label={t("Active")}>
                      <input type="checkbox" aria-label={t('Active') + ': ' + r.name} checked={isDef || r.active} disabled={isDef || r.remove} onChange={(e) => setRow(r.code, { active: e.currentTarget.checked })} />
                    </td>
                    <td data-label={t("Code / address")}>
                      <code>{isDef ? '/' : '/' + r.code + '/'}</code>
                    </td>
                    <td data-label={t("Name")}>
                      <input type="text" className="flxl-small" aria-label={t('Name') + ': ' + r.code} value={r.name} onChange={(e) => setRow(r.code, { name: e.currentTarget.value })} />
                    </td>
                    <td data-label={t("Flag")}>
                      <input type="text" className="flxl-tiny" aria-label={t('Flag') + ': ' + r.code} value={r.flag} onChange={(e) => setRow(r.code, { flag: e.currentTarget.value })} />
                    </td>
                    <td data-label={t("WordPress locale")}>
                      <input type="text" className="flxl-small" aria-label={t('WordPress locale') + ': ' + r.code} value={r.locale} onChange={(e) => setRow(r.code, { locale: e.currentTarget.value })} />
                    </td>
                    <td data-label={t("Default")}>
                      <input type="radio" name="flxl-default" aria-label={t('Default') + ': ' + r.name} checked={isDef} disabled={r.remove} onChange={() => change({ def: r.code })} />
                    </td>
                    <td data-label={t("Remove")}>
                      {!isDef && <input type="checkbox" aria-label={t('Remove') + ': ' + r.name} checked={r.remove} onChange={(e) => setRow(r.code, { remove: e.currentTarget.checked })} />}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <p className="flxl-add">
          <label htmlFor="flxl-add">{t("Add language")}</label>
          <select id="flxl-add" value={adding} onChange={(e) => addLanguage(e.currentTarget.value)}>
            <option value="">{t("— choose —")}</option>
            {addable.map((c) => (
              <option key={c.code} value={c.code}>
                {c.flag + ' ' + c.name + ' (/' + c.code + '/)'}
              </option>
            ))}
          </select>
          <span className="flxl-desc">{t("A new language is added inactive: prepare its content, then activate it. Languages other than English also need the WordPress language pack (System check warns).")}</span>
        </p>

        <h2>{t("Content")}</h2>
        <div className="flxl-card">
          <div className="flxl-field">
            <span className="flxl-label">{t("Translated content types")}</span>
            <div>
              {s.postTypeChoices.map((pt) => (
                <label key={pt.name} className="flxl-inline">
                  <input
                    type="checkbox"
                    checked={draft.postTypes.includes(pt.name)}
                    onChange={(e) => change({ postTypes: e.currentTarget.checked ? [...draft.postTypes, pt.name] : draft.postTypes.filter((x) => x !== pt.name) })}
                  />{' '}
                  {pt.label}
                </label>
              ))}
              <p className="flxl-desc">{t("Cart, checkout and my account pages are shared by all languages. Categories are shared; their names are translated per language on the category edit screen. Language-specific alt text and replacement images are on the media edit screen.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <span className="flxl-label">{t("Content without a translation")}</span>
            <div>
              <label>
                <input type="checkbox" checked={draft.fallback} onChange={(e) => change({ fallback: e.currentTarget.checked })} /> {t("Show the Turkish version in other languages (menu and interface stay in that language; search engines get the Turkish canonical address)")}
              </label>
              <p className="flxl-desc">{t("When off, the English site lists only content that has an English version.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <label className="flxl-label" htmlFor="flxl-sw">
              {t("Language switcher")}
            </label>
            <div>
              <select id="flxl-sw" value={draft.switcher} onChange={(e) => change({ switcher: e.currentTarget.value as FlxLanguageSwitcherStyle })}>
                <option value="CODE">TR · EN</option>
                <option value="NAME">Türkçe · English</option>
                <option value="FLAG">🇹🇷 · 🇬🇧</option>
              </select>
              <p className="flxl-desc">{t("Elsewhere: the \"Flux Language Switcher\" block or the [flx_dil_secici] shortcode.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <span className="flxl-label">{t("Switcher position")}</span>
            <fieldset className="flxl-radios">
              {(
                [
                  ['FOOTER', t("Footer")],
                  ['HEADER', t("Header and mobile menu")],
                  ['BOTH', t("Both")],
                ] as const
              ).map(([v, label]) => (
                <label key={v}>
                  <input type="radio" name="flxl-place" checked={draft.switcherPlace === v} onChange={() => change({ switcherPlace: v })} /> {label}
                </label>
              ))}
            </fieldset>
          </div>
        </div>

        <h2>{t("Language suggestion for visitors")}</h2>
        <div className="flxl-card">
          <div className="flxl-field">
            <span className="flxl-label">{t("Automatic language")}</span>
            <div>
              <fieldset className="flxl-radios">
                {(
                  [
                    ['REDIRECT', t("Open directly in their language (recommended)")],
                    ['BANNER', t("Show a suggestion bar at the top of the page")],
                    ['OFF', t("Off")],
                  ] as const
                ).map(([v, label]) => (
                  <label key={v}>
                    <input type="radio" name="flxl-suggest" checked={draft.suggest === v} onChange={() => change({ suggest: v })} /> {label}
                  </label>
                ))}
              </fieldset>
              <p className="flxl-desc">{t("On the first visit, before the page renders, the site opens in the visitor's browser language if it is active here; otherwise in their country's language; otherwise in the suggested language below. The choice is kept on later visits, and a visitor who picks another language in the switcher stays in it. Search engine bots are never redirected. Not applied to logged-in users; use a private window to test.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <label className="flxl-label" htmlFor="flxl-to">
              {t("Suggested language")}
            </label>
            <div>
              <select id="flxl-to" value={draft.suggestTo} onChange={(e) => change({ suggestTo: e.currentTarget.value })}>
                {kept.map((r) => (
                  <option key={r.code} value={r.code}>
                    {r.name}
                  </option>
                ))}
              </select>
              <p className="flxl-desc">{t("Used when neither the browser language nor the country's language is active on the site.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <label className="flxl-label" htmlFor="flxl-hc">
              {t("Countries that see the default language")}
            </label>
            <div>
              <input id="flxl-hc" type="text" className="flxl-wide" value={draft.homeCountries} onChange={(e) => change({ homeCountries: e.currentTarget.value })} />
              <p className="flxl-desc">{t("Two-letter country codes (TR, CY…). Used only when the browser language is not on the site.")}</p>
            </div>
          </div>
          <div className="flxl-field">
            <span className="flxl-label">{t("Country information")}</span>
            <p className="flxl-geo">{s.geo.country ? t("Country in your request: %1$s (%2$s header). Country-based suggestion works.", s.geo.country, s.geo.source ?? '') : t("The server sends no country information; the browser's time zone is used (Türkiye time = Turkish). For more accurate results, put the domain behind Cloudflare (free plan, the CF-IPCountry header is used automatically) or enable your host's GeoIP header.")}</p>
          </div>
        </div>

        {others.length > 0 && (
          <>
            <h2>{t("Site texts per language")}</h2>
            <p className="flxl-desc">{t("Empty fields are shown in Turkish.")}</p>
            <div className="flxl-card">
              {others.map((l) => (
                <div key={l.code} className="flxl-texts">
                  <h3>{(l.flag + ' ' + l.name).trim()}</h3>
                  {s.textFields.map((f) => {
                    const id = 'flxl-t-' + l.code + '-' + f.key;
                    const value = draft.texts[l.code]?.[f.key] ?? '';
                    const set = (v: string) => change({ texts: { ...draft.texts, [l.code]: { ...draft.texts[l.code], [f.key]: v } } });
                    return (
                      <div key={f.key} className="flxl-field">
                        <label className="flxl-label" htmlFor={id}>
                          {f.label}
                        </label>
                        {f.textarea ? (
                          <textarea id={id} rows={2} lang={l.code} placeholder={f.placeholder} value={value} onChange={(e) => set(e.currentTarget.value)} />
                        ) : (
                          <input id={id} type="text" className="flxl-wide" lang={l.code} placeholder={f.placeholder} value={value} onChange={(e) => set(e.currentTarget.value)} />
                        )}
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </>
        )}

        <div className="flxl-actions">
          <button type="submit" className="flxl-btn is-primary" disabled={save.isPending}>
            {t("Save")}
          </button>
          {dirty && <span className="flxl-dirty">{t('Unsaved changes')}</span>}
        </div>
      </form>

      <h2>{t("Translation with AI")}</h2>
      <p className="flxl-desc flxl-ai">{t("With Flux AI installed, a \"Translate with AI\" button appears in the list and the editor. AI agents connected over MCP (Claude etc.) and the Flux AI chat use these tools: flxl_languages, flxl_untranslated, flxl_translations, flxl_save_translation, flxl_set_language, flxl_image_language, flxl_term_translation, flxl_site_texts. Flux SEO tools (list_content, create_content, update_content) have a lang parameter.")}</p>
    </div>
  );
}
