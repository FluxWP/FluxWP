// Settings: tracking platforms (Meta, TikTok, GA4), suggestion rules and privacy. Secrets are write-only:
// the screen only learns whether one is set and its last characters.
import { useEffect, useState, type ReactNode } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAdsConnectionTest, FlxAdsContext, FlxAdsSettings, FlxAdsSettingsSave } from '@fluxwp/graphql/operations';
import type { FlxAdsPlatform, FlxAdsSettingsFragment, FlxAdsSettingsInput } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Btn, Card, Field, Notice, useAct, useEnv } from './common';
import { Fail } from './AdsScreen';

type Secret = FlxAdsSettingsFragment['metaToken'];
type SecretKey = 'metaToken' | 'metaAdsToken' | 'tiktokToken' | 'ga4Secret';
const CLEAR = '__clear__';

interface Form {
  metaOn: boolean;
  metaPixel: string;
  metaTest: string;
  metaAccount: string;
  metaPage: string;
  tiktokOn: boolean;
  tiktokPixel: string;
  tiktokTest: string;
  ga4On: boolean;
  ga4Id: string;
  consent: string;
  pii: string;
  browser: boolean;
  windowDays: string;
  optRoas: string;
  optMinSpend: string;
}
type Secrets = Record<SecretKey, string>;
const NO_SECRETS: Secrets = { metaToken: '', metaAdsToken: '', tiktokToken: '', ga4Secret: '' };

const formOf = (s: FlxAdsSettingsFragment): Form => ({
  metaOn: s.metaOn,
  metaPixel: s.metaPixel,
  metaTest: s.metaTest,
  metaAccount: s.metaAccount,
  metaPage: s.metaPage,
  tiktokOn: s.tiktokOn,
  tiktokPixel: s.tiktokPixel,
  tiktokTest: s.tiktokTest,
  ga4On: s.ga4On,
  ga4Id: s.ga4Id,
  consent: s.consent,
  pii: s.pii,
  browser: s.browser,
  windowDays: String(s.windowDays),
  optRoas: String(s.optRoas),
  optMinSpend: String(s.optMinSpend),
});

const inputOf = (f: Form, sec: Secrets): FlxAdsSettingsInput => ({
  ...f,
  windowDays: Number(f.windowDays) || 90,
  optRoas: Number(f.optRoas) || 2,
  optMinSpend: Number(f.optMinSpend) || 0,
  // An empty secret is left out so the stored one stays.
  ...Object.fromEntries(Object.entries(sec).filter(([, v]) => v !== '')),
});

export function SettingsTab() {
  const { t, ctx } = useEnv();
  const q = useFlxQuery(FlxAdsSettings, {});
  const [form, setForm] = useState<Form | null>(null);
  const [sec, setSec] = useState<Secrets>(NO_SECRETS);
  const [result, setResult] = useState<{ key: string; ok: boolean; text: string } | null>(null);
  const s = q.data?.flxAdsSettings;

  useEffect(() => {
    if (s && !form) setForm(formOf(s));
  }, [s, form]);

  const saved = (r: FlxAdsSettingsFragment | null | undefined) => {
    if (r) setForm(formOf(r));
    setSec(NO_SECRETS);
  };
  const save = useAct(FlxAdsSettingsSave, (r) => r.flxAdsSettingsSave, t('Settings saved.'), [FlxAdsSettings, FlxAdsContext], (r) => saved(r.flxAdsSettingsSave?.settings));
  const test = useAct(
    FlxAdsConnectionTest,
    (r) => r.flxAdsConnectionTest,
    (r) => r.flxAdsConnectionTest?.test?.message ?? t('Settings saved.'),
    [FlxAdsSettings, FlxAdsContext],
    (r) => {
      saved(r.flxAdsConnectionTest?.settings);
    },
  );

  if (!ctx.canManage) return <Card pad><p>{t('You are not allowed to do this.')}</p></Card>;
  if (q.isLoading || (!form && !q.isError)) return <Loading />;
  if (!s || !form) return <Fail q={q} />;
  const set = (p: Partial<Form>) => setForm({ ...form, ...p });
  const busy = save.isPending || test.isPending;
  const runTest = (key: FlxAdsPlatform) => {
    setResult(null);
    test.mutate({ input: { platform: key, settings: inputOf(form, sec) } }, { onSuccess: (r) => {
      const x = r.flxAdsConnectionTest?.test;
      if (x) setResult({ key, ok: x.ok, text: x.message });
    } });
  };
  const plat = (key: string) => s.platforms.find((p) => p.key === key);

  const secret = (k: SecretKey, label: string, hint: string) => {
    const cur: Secret = s[k];
    const cleared = sec[k] === CLEAR;
    return (
      <Field label={label} hint={hint}>
        {cur.fromConfig ? (
          <span className="flxads-muted">{t('Set in wp-config.php')}</span>
        ) : (
          <span className="flxads-inline">
            <input
              type="password"
              autoComplete="new-password"
              value={cleared ? '' : sec[k]}
              disabled={cleared}
              placeholder={cur.set ? '••••' + cur.mask : ''}
              onChange={(e) => setSec({ ...sec, [k]: e.target.value })}
            />
            {cur.set && !cleared && (
              <Btn small onClick={() => setSec({ ...sec, [k]: CLEAR })}>
                {t('Remove')}
              </Btn>
            )}
            {cleared && (
              <Btn small onClick={() => setSec({ ...sec, [k]: '' })}>
                {t('Undo')}
              </Btn>
            )}
          </span>
        )}
      </Field>
    );
  };

  const box = (k: FlxAdsPlatform, dot: string, title: string, on: boolean, children: ReactNode) => {
    const p = plat(k.toLowerCase());
    const shown = result && result.key === k ? { ok: result.ok, text: result.text } : p?.test ? { ok: p.test.ok, text: p.test.message } : null;
    return (
      <section className="flxads-card flxads-pad flxads-platform">
        <div className="flxads-platform__head">
          <h2 className="flxads-h2">
            <span className={'flxads-dot flxads-dot--' + dot} />
            {title}
          </h2>
          <label className="flxads-switch">
            <input type="checkbox" checked={on} onChange={(e) => set(({ META: { metaOn: e.target.checked }, TIKTOK: { tiktokOn: e.target.checked }, GA4: { ga4On: e.target.checked } } as Record<string, Partial<Form>>)[k] ?? {})} /> {t('On')}
          </label>
        </div>
        {children}
        {shown && (
          <Notice kind={shown.ok ? 'ok' : 'bad'}>
            {shown.text}
            {p?.test?.at && result?.key !== k ? <small> {p.test.at.relative}</small> : null}
          </Notice>
        )}
        {p?.canTest && (
          <Btn disabled={busy} onClick={() => runTest(k)}>
            {t('Test the connection')}
          </Btn>
        )}
      </section>
    );
  };

  const text = (key: keyof Form, label: string, hint: string, ph = '') => (
    <Field label={label} hint={hint}>
      <input value={form[key] as string} placeholder={ph} onChange={(e) => set({ [key]: e.target.value })} />
    </Field>
  );

  return (
    <form
      className="flxads-settings"
      onSubmit={(e) => {
        e.preventDefault();
        save.mutate({ input: { settings: inputOf(form, sec) } });
      }}
    >
      {box('META', 'meta', 'Meta Pixel + Conversions API', form.metaOn, (
        <>
        {text('metaPixel', t('Pixel (dataset) ID'), t('Events Manager › Data sources › your pixel › Settings.'), '123456789012345')}
        {secret('metaToken', t('Conversions API access token'), t('Events Manager › Settings › Conversions API › Generate access token. Needed for server events (bank transfers, ad blockers).'))}
        {text('metaTest', t('Test event code (optional)'), t('While testing only. Empty it after testing, or real donations are reported as test events.'), 'TEST12345')}
        <h3 className="flxads-h3">{t('Ad spend (for ROAS)')}</h3>
        {text('metaAccount', t('Ad account ID'), t('Ads Manager › account menu at the top left, the number under the account name (act_ is optional).'), '1234567890')}
        {text('metaPage', t('Facebook Page ID'), t('The page Studio ads are published as. Page › About › Page transparency › Page ID.'), '1029384756')}
        {secret('metaAdsToken', t('Marketing API token'), t('Business Settings › Users › System users › add the ad account › Generate token. ads_read is enough for spend; audiences, Campaigns changes and Studio drafts need ads_management. Empty = the Conversions API token is tried. Flux Ads changes your ad account only when you approve it: an audience, a pause or budget change, or a Studio draft (always created paused).'))}
      </>
      ))}

      {box('TIKTOK', 'tiktok', 'TikTok Pixel + Events API', form.tiktokOn, (
        <>
        {text('tiktokPixel', t('Pixel ID'), t('TikTok Ads Manager › Tools › Events › Web events.'), 'C1A2B3C4D5E6F7G8H9I0')}
        {secret('tiktokToken', t('Events API access token'), t('Pixel › Settings › Events API › Generate access token.'))}
        {text('tiktokTest', t('Test event code (optional)'), t('While testing only.'), 'TEST12345')}
      </>
      ))}

      {box('GA4', 'google', 'Google Analytics 4', form.ga4On, (
        <>
        {text(
          'ga4Id',
          t('Measurement ID'),
          s.ga4ThemeId !== '' ? t("Empty = the theme's ID (%s). The theme already loads the tag; Flux Ads only adds the donation events.", s.ga4ThemeId) : t('Admin › Data streams › Measurement ID.'),
          'G-XXXXXXXXXX',
        )}
        {secret('ga4Secret', t('Measurement Protocol API secret'), t('Data stream › Measurement Protocol API secrets › Create. Needed for bank-transfer donations approved later. To see donations in Google Ads, import the GA4 "purchase" conversion there.'))}
      </>
      ))}

      <section className="flxads-card flxads-pad" id="flxads-opt">
        <h2 className="flxads-h2">{t('Suggestions')}</h2>
        <Field label={t('Target ROAS')} className="flxads-field--short" hint={t('Income on the site per 1 spent on ads. Under half of it: lower the budget; 1.5 times and more: raise it.')}>
          <input type="number" step={0.1} min={0.1} value={form.optRoas} onChange={(e) => set({ optRoas: e.target.value })} />
        </Field>
        <Field label={t('Spend before judging (7 days)')} className="flxads-field--short" hint={t('In the ad account currency. 0 = automatic: twice your average cost per payment.')}>
          <input type="number" step={1} min={0} value={form.optMinSpend} onChange={(e) => set({ optMinSpend: e.target.value })} />
        </Field>
      </section>

      <section className="flxads-card flxads-pad">
        <h2 className="flxads-h2">{t('Privacy')}</h2>
        <fieldset className="flxads-field">
          <legend>{t('Cookie consent')}</legend>
          <label>
            <input type="radio" name="consent" checked={form.consent === 'auto'} onChange={() => set({ consent: 'auto' })} />{' '}
            {t('Wait for consent (recommended): pixels and the ad cookie start only after the visitor accepts the theme cookie banner. Donations of visitors who did not accept are counted in the report but not sent to ad platforms.')}
          </label>
          <label>
            <input type="radio" name="consent" checked={form.consent === 'always'} onChange={() => set({ consent: 'always' })} /> {t('Always: you collect consent another way.')}
          </label>
        </fieldset>
        <fieldset className="flxads-field">
          <legend>{t('E-mail and phone (for better matching)')}</legend>
          {[
            ['consent', t('Only for contacts with ad consent in Flux CRM (recommended). Sent as SHA-256 hashes, never in plain text.')],
            ['never', t('Never: only the browser ID, IP and browser type.')],
            ['always', t('Always (hashed). Your privacy notice must say so.')],
          ].map(([k, l]) => (
            <label key={k}>
              <input type="radio" name="pii" checked={form.pii === k} onChange={() => set({ pii: k })} /> {l}
            </label>
          ))}
        </fieldset>
        <label className="flxads-check">
          <input type="checkbox" checked={form.browser} onChange={(e) => set({ browser: e.target.checked })} /> {t('Load the browser pixels too (page views, form and checkout steps). Off = server events only.')}
        </label>
        <Field label={t('Attribution window (days)')} className="flxads-field--short" hint={t('How long a click counts for a later donation.')}>
          <input type="number" min={1} max={365} value={form.windowDays} onChange={(e) => set({ windowDays: e.target.value })} />
        </Field>
      </section>

      <p>
        <Btn primary type="submit" disabled={busy}>
          {t('Save settings')}
        </Btn>
      </p>
    </form>
  );
}
