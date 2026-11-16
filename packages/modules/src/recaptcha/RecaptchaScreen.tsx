// Flux reCAPTCHA. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes
// through the Flux reCAPTCHA GraphQL operations (plugins/flx-recaptcha/graphql/operations).
// It has everything the classic page has: state and last 30 days, keys with the Google test, the
// protected forms, options, IP allow list. The secret key is write-only: it is typed, sent once and
// never read back.
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxRecaptchaKeysTest, FlxRecaptchaScreen, FlxRecaptchaSettingsUpdate } from '@fluxwp/graphql/operations';
import type {
  FlxRecaptchaDomain,
  FlxRecaptchaSettingsUpdateInput,
  FlxRecaptchaFormGroup,
  FlxRecaptchaScreenQuery,
  FlxRecaptchaSize,
  FlxRecaptchaTheme,
  FlxRecaptchaVersion,
} from '@fluxwp/graphql/types';
import { Icon, Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './recaptcha.css';

type Screen = FlxRecaptchaScreenQuery;
type Form = Screen['flxRecaptchaForms'][number];
type Notice = { kind: 'success' | 'warn' | 'error'; text: string };
type Say = { kind: 'ok' | 'bad' | 'busy' | ''; text: string };

interface Draft {
  version: FlxRecaptchaVersion;
  siteKey: string;
  secret: string;
  threshold: number;
  domain: FlxRecaptchaDomain;
  v2Theme: FlxRecaptchaTheme;
  v2Size: FlxRecaptchaSize;
  hideBadge: boolean;
  skipLoggedIn: boolean;
  failOpen: boolean;
  allowIps: string;
  forms: Record<string, boolean>;
}

const GROUPS: FlxRecaptchaFormGroup[] = ['WORDPRESS', 'WOOCOMMERCE', 'FLUXWP', 'OTHER'];
/** Set before the page reloads so a test that needs another Google script starts on its own. */
const AUTOTEST = 'flxrcAutotest';

const draftOf = (d: Screen): Draft => ({
  version: d.flxRecaptchaSettings.version,
  siteKey: d.flxRecaptchaSettings.siteKey,
  secret: '',
  threshold: d.flxRecaptchaSettings.threshold,
  domain: d.flxRecaptchaSettings.domain,
  v2Theme: d.flxRecaptchaSettings.v2Theme,
  v2Size: d.flxRecaptchaSettings.v2Size,
  hideBadge: d.flxRecaptchaSettings.hideBadge,
  skipLoggedIn: d.flxRecaptchaSettings.skipLoggedIn,
  failOpen: d.flxRecaptchaSettings.failOpen,
  allowIps: d.flxRecaptchaSettings.allowIps.join('\n'),
  forms: Object.fromEntries(d.flxRecaptchaForms.map((f) => [f.key, f.enabled])),
});

/** The minimal slice of Google's grecaptcha object this screen uses. */
interface Grecaptcha {
  ready(cb: () => void): void;
  execute(key: string, opts: { action: string }): Promise<string>;
  render(el: HTMLElement, opts: { sitekey: string; theme: 'light' | 'dark'; callback: (token: string) => void; 'error-callback': () => void }): number;
}
type GoogleWindow = Window & { grecaptcha?: Grecaptcha; flxrcAdminOnload?: () => void };

const sleep = (ms: number) => new Promise<void>((r) => setTimeout(r, ms));

export function RecaptchaScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Recaptcha />
    </FlxGraphQLProvider>
  );
}

function Recaptcha() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxRecaptchaScreen, {});
  const [draft, setDraft] = useState<Draft | null>(null);
  const [dirty, setDirty] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [say, setSay] = useState<Say>({ kind: '', text: '' });
  const [testing, setTesting] = useState(false);
  const box = useRef<HTMLDivElement | null>(null);
  const loaded = useRef<{ key: string; version: FlxRecaptchaVersion } | null>(null);
  const run = useRef<() => void>(() => undefined);

  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data));
  }, [q.data, dirty]);

  // After a reload for a new key the test starts on its own, once.
  const ready = draft !== null;
  useEffect(() => {
    if (!ready) return;
    let flag = false;
    try {
      flag = sessionStorage.getItem(AUTOTEST) === '1';
      if (flag) sessionStorage.removeItem(AUTOTEST);
    } catch {
      flag = false;
    }
    if (flag) run.current();
  }, [ready]);

  const put = (d: Screen['flxRecaptchaSettings'], forms: Screen['flxRecaptchaForms'], status: Screen['flxRecaptchaStatus']) =>
    qc.setQueryData<Screen>(flxQueryKey(scope, FlxRecaptchaScreen, {}), { flxRecaptchaSettings: d, flxRecaptchaForms: forms, flxRecaptchaStatus: status });
  const fail = (e: unknown) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
  const edit = (patch: Partial<Draft>) => {
    setDraft((o) => o && { ...o, ...patch });
    setDirty(true);
  };

  const wasOn = q.data?.flxRecaptchaStatus.state === 'ON';
  const save = useFlxMutation(FlxRecaptchaSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxRecaptchaSettingsUpdate;
      if (!p) return fail(null);
      if (p.errors.length) return setNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      put(p.settings, p.forms, p.status);
      setDirty(false);
      setDraft(null);
      setNotice(wasOn && p.status.state === 'AWAITING_TEST' ? { kind: 'warn', text: t('Saved. The keys changed, so protection stays off until they are tested.') } : { kind: 'success', text: t('Saved.') });
      window.scrollTo?.({ top: 0, behavior: 'smooth' });
    },
    onError: fail,
  });
  const keysTest = useFlxMutation(FlxRecaptchaKeysTest);

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxrc-app"><div className="flxrc-notice is-bad">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const d = q.data;
  const s = d.flxRecaptchaSettings;
  const st = d.flxRecaptchaStatus;
  const apiHost = s.domain === 'RECAPTCHA' ? 'https://www.recaptcha.net' : 'https://www.google.com';
  const keyOf = () => (s.siteKeyLocked ? s.siteKey : draft.siteKey.trim());

  const payload = (): FlxRecaptchaSettingsUpdateInput => {
    const input: FlxRecaptchaSettingsUpdateInput = {
      version: draft.version,
      threshold: Math.round(draft.threshold * 10) / 10,
      domain: draft.domain,
      v2Theme: draft.v2Theme,
      v2Size: draft.v2Size,
      hideBadge: draft.hideBadge,
      skipLoggedIn: draft.skipLoggedIn,
      failOpen: draft.failOpen,
      allowIps: draft.allowIps.split(/[\s,]+/).filter(Boolean),
      forms: d.flxRecaptchaForms.filter((f) => f.available).map((f) => ({ key: f.key, enabled: draft.forms[f.key] ?? f.enabled })),
    };
    if (!s.siteKeyLocked) input.siteKey = draft.siteKey.trim();
    if (!s.secretLocked && draft.secret.trim()) input.secret = draft.secret.trim();
    return input;
  };
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ input: payload() });
  };

  const load = (key: string, version: FlxRecaptchaVersion): Promise<Grecaptcha> =>
    new Promise((resolve, reject) => {
      const w = window as GoogleWindow;
      const ready = () => w.grecaptcha?.ready(() => resolve(w.grecaptcha as Grecaptcha));
      if (loaded.current) return loaded.current.key === key && loaded.current.version === version ? ready() : reject(new Error('reload'));
      loaded.current = { key, version };
      w.flxrcAdminOnload = ready;
      const el = document.createElement('script');
      el.src = `${apiHost}/recaptcha/api.js?render=${version === 'V3' ? encodeURIComponent(key) : 'explicit'}&onload=flxrcAdminOnload&hl=${encodeURIComponent(i18n.language.slice(0, 2))}`;
      el.async = true;
      el.onerror = () => {
        loaded.current = null;
        reject(new Error('load'));
      };
      document.head.appendChild(el);
      setTimeout(() => !w.grecaptcha && reject(new Error('load')), 15000);
    });

  const send = (token: string) => {
    setSay({ kind: 'busy', text: t('Verifying with Google…') });
    keysTest.mutate(
      { input: { version: draft.version, siteKey: keyOf(), secret: draft.secret.trim(), token } },
      {
        onSuccess: (r) => {
          const p = r.flxRecaptchaKeysTest;
          setTesting(false);
          if (!p || p.errors.length || !p.result) return setSay({ kind: 'bad', text: p?.errors[0]?.message ?? t('Something went wrong. Please try again.') });
          if (!p.result.ok) return setSay({ kind: 'bad', text: p.result.message });
          put(p.settings, p.forms, p.status);
          setDirty(false);
          setDraft(null);
          setSay({ kind: 'ok', text: `${p.result.message} ${t('Keys saved; protection is on.')}` });
        },
        onError: () => {
          setTesting(false);
          setSay({ kind: 'bad', text: t('Something went wrong. Please try again.') });
        },
      },
    );
  };

  const runTest = async () => {
    const key = keyOf();
    if (!key) return setSay({ kind: 'bad', text: t('Enter the site key first.') });
    if (!s.secretSaved && !draft.secret.trim()) return setSay({ kind: 'bad', text: t('Enter the secret key.') });
    setTesting(true);
    setSay({ kind: 'busy', text: t('Loading Google reCAPTCHA…') });
    try {
      const g = await load(key, draft.version);
      if (draft.version === 'V3') {
        await sleep(0);
        send(await g.execute(key, { action: 'flx_test' }));
        return;
      }
      const target = box.current;
      if (!target) throw new Error('load');
      target.innerHTML = '<div></div>';
      setSay({ kind: '', text: t('Tick the box; the test continues on its own once it is ticked.') });
      const dark = document.documentElement.classList.contains('flxa-dark') || document.querySelector('.flux-root')?.getAttribute('data-theme') === 'dark';
      g.render(target.firstElementChild as HTMLElement, {
        sitekey: key,
        theme: dark ? 'dark' : 'light',
        callback: send,
        'error-callback': () => {
          setTesting(false);
          setSay({ kind: 'bad', text: t('Google reCAPTCHA could not be loaded. Check the site key and the domain; if Google is unreachable, try the "recaptcha.net" option.') });
        },
      });
    } catch (e) {
      if (e instanceof Error && e.message === 'reload') {
        // Google's script was loaded with another key or type: save the settings, then reload; the test starts again by itself.
        setSay({ kind: 'busy', text: t('The site key changed; reloading the page to test with the new key…') });
        save.mutate(
          { input: payload() },
          {
            onSuccess: () => {
              try {
                sessionStorage.setItem(AUTOTEST, '1');
              } catch {
                /* the test just does not start by itself */
              }
              window.location.reload();
            },
          },
        );
        return;
      }
      setTesting(false);
      setSay({ kind: 'bad', text: t('Google reCAPTCHA could not be loaded. Check the site key and the domain; if Google is unreachable, try the "recaptcha.net" option.') });
    }
  };

  run.current = () => void runTest();

  const lastError = st.lastError;
  const stateCard =
    st.state === 'OFF'
      ? { value: t('Off'), cls: 'is-bad', hint: 'FLXRC_DISABLE' }
      : st.state === 'ON'
        ? { value: t('On'), cls: 'is-ok', hint: st.version === 'V3' ? t('v3 · invisible') : t('v2 · checkbox') }
        : { value: t('Awaiting test'), cls: 'is-warn', hint: t('Test your keys') };

  return (
    <div className="flxrc-app">
      <div className="flxrc-head">
        <div>
          <h1>{t('reCAPTCHA')}</h1>
          <p className="flxrc-lead">{t('Protects login, registration, comments, checkout, donations and form submissions from bots with Google reCAPTCHA. Choose below which forms to protect.')}</p>
        </div>
        {host.classicUrl && (
          <a className="flxrc-classic" href={host.classicUrl}>
            {t('Classic view')}
          </a>
        )}
      </div>

      {notice && (
        <div className={'flxrc-notice is-' + (notice.kind === 'success' ? 'ok' : notice.kind === 'warn' ? 'warn' : 'bad')} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <Icon name={notice.kind === 'success' ? 'check' : 'warning'} size={16} />
          <span>{notice.text}</span>
          <button type="button" className="flxrc-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}

      {st.state === 'OFF' ? (
        <Callout kind="warn" icon="warning">
          <strong>{t('Protection is turned off by the recovery constant.')}</strong> {t('Remove the FLXRC_DISABLE line from wp-config.php to turn protection back on.')}
        </Callout>
      ) : (
        st.state === 'AWAITING_TEST' && (
          <Callout kind="info" icon="info">
            <strong>{t('Protection is not running yet.')}</strong>{' '}
            {t('Enter your keys and press "Test". No form is checked until the test passes, so a wrong key can never lock you out of the login screen.')}
          </Callout>
        )
      )}
      {lastError && (
        <Callout kind="warn" icon="warning">
          <strong>{lastError.kind === 'CONFIG' ? t('Google rejected the secret key') : t('Google could not be reached')}</strong> ({lastError.time.label}): <code>{lastError.reason}</code>{' '}
          {lastError.letPass ? t('Submissions were let through without a check at that time.') : t('Submissions were blocked at that time.')}
        </Callout>
      )}

      <div className="flxrc-kpis">
        <Kpi icon="shield" label={t('Protection')} value={stateCard.value} hint={stateCard.hint} cls={stateCard.cls} />
        <Kpi icon="check" label={t('Protected forms')} value={t('%1$s / %2$s', st.formsOn.toLocaleString(i18n.language), st.formsAvailable.toLocaleString(i18n.language))} hint={t('forms of installed plugins')} />
        <Kpi icon="user" label={t('Passed')} value={st.passed.toLocaleString(i18n.language)} hint={t('last 30 days')} />
        <Kpi icon="warning" label={t('Blocked')} value={st.blocked.toLocaleString(i18n.language)} hint={t('last 30 days')} cls={st.blocked ? 'is-warn' : ''} />
      </div>

      <form onSubmit={submit}>
        <div className="flxrc-grid">
          <section className="flxrc-card" id="flxrc-keys">
            <div className="flxrc-card__head">
              <h2>
                <Icon name="lock" size={16} />
                {t('Keys')}
              </h2>
              {s.keysVerified ? (
                <span className="flxrc-badge is-ok">
                  <Icon name="check" size={12} />
                  {t('Tested')}
                </span>
              ) : (
                <span className="flxrc-badge is-warn">{t('Not tested')}</span>
              )}
            </div>
            <p className="flxrc-muted">
              <Linked text={t('Get your keys <a>from the Google reCAPTCHA console</a>: choose the reCAPTCHA type and add your domain. The site key and the secret key must belong to the same entry.')} href="https://www.google.com/recaptcha/admin/create" external />
            </p>
            <fieldset className="flxrc-choice">
              <legend className="flxrc-sr">{t('reCAPTCHA type')}</legend>
              {(
                [
                  ['V3', t('v3 · Invisible'), t('Visitors are never asked anything; Google returns a score from 0 to 1. Recommended.')],
                  ['V2', t('v2 · "I\'m not a robot"'), t('A checkbox appears on the form; suspicious visitors get an image challenge.')],
                ] as const
              ).map(([v, label, help]) => (
                <label key={v} className="flxrc-choice__item">
                  <input type="radio" name="version" value={v} checked={draft.version === v} onChange={() => edit({ version: v })} />
                  <span>
                    <strong>{label}</strong>
                    <small>{help}</small>
                  </span>
                </label>
              ))}
            </fieldset>
            <div className="flxrc-fields">
              <label>
                <span>{t('Site key')}</span>
                <input
                  type="text"
                  className="code"
                  value={s.siteKeyLocked ? '' : draft.siteKey}
                  placeholder={s.siteKeyLocked ? 'FLXRC_SITE_KEY' : ''}
                  disabled={s.siteKeyLocked}
                  autoComplete="off"
                  spellCheck={false}
                  onChange={(e) => edit({ siteKey: e.currentTarget.value })}
                />
              </label>
              <label>
                <span>{t('Secret key')}</span>
                <input
                  type="password"
                  className="code"
                  value={draft.secret}
                  disabled={s.secretLocked}
                  autoComplete="new-password"
                  spellCheck={false}
                  placeholder={s.secretSaved ? t('Saved (%s). Type to replace it.', s.secretHint) : t('Paste the secret key')}
                  onChange={(e) => edit({ secret: e.currentTarget.value })}
                />
                <small>{t('Entered once, stored encrypted and never shown again.')}</small>
              </label>
            </div>
            {(s.siteKeyLocked || s.secretLocked) && <p className="flxrc-muted">{t('One of the keys comes from a wp-config.php constant (FLXRC_SITE_KEY / FLXRC_SECRET_KEY); the value in this panel is not used.')}</p>}
            <div className="flxrc-test">
              <div className="flxrc-test__row">
                <button type="button" className="flxrc-btn is-primary" disabled={testing || keysTest.isPending} onClick={() => void runTest()}>
                  {t('Test and save')}
                </button>
                <span className="flxrc-muted">{t('Runs a real check with Google; if it passes, the keys are saved and protection turns on.')}</span>
              </div>
              <div className="flxrc-test__box" ref={box} hidden={draft.version !== 'V2' || !testing} />
              {say.text && (
                <p className={'flxrc-test__msg' + (say.kind ? ' is-' + say.kind : '')} role="status">
                  {say.text}
                </p>
              )}
            </div>
            {st.lastTest && <p className="flxrc-muted">{t('Last test: %1$s · %2$s', st.lastTest.time.label, st.lastTest.message)}</p>}
          </section>

          <section className="flxrc-card flxrc-span-2" id="flxrc-forms">
            <div className="flxrc-card__head">
              <h2>
                <Icon name="check" size={16} />
                {t('Protected forms')}
              </h2>
              <span className="flxrc-muted">{t('Last 30 days: passed / blocked')}</span>
            </div>
            {GROUPS.map((g) => {
              const rows = d.flxRecaptchaForms.filter((f) => f.group === g);
              if (!rows.length) return null;
              return (
                <div key={g}>
                  <h3 className="flxrc-sub">{rows[0]?.groupLabel}</h3>
                  <ul className="flxrc-rows">
                    {rows.map((f) => (
                      <FormRow key={f.key} f={f} on={draft.forms[f.key] ?? f.enabled} t={t} lang={i18n.language} onToggle={(on) => edit({ forms: { ...draft.forms, [f.key]: on } })} />
                    ))}
                  </ul>
                </div>
              );
            })}
            {s.formsOverlap && (
              <p className="flxrc-muted flxrc-note">
                <Icon name="info" size={14} />
                {t('If a form has its own reCAPTCHA option turned on in Flux Forms, turn it off; two checks on one page conflict.')}
              </p>
            )}
          </section>

          <section className="flxrc-card flxrc-span-3" id="flxrc-settings">
            <div className="flxrc-card__head">
              <h2>
                <Icon name="settings" size={16} />
                {t('Settings')}
              </h2>
            </div>
            <div className="flxrc-fields flxrc-fields--grid">
              {draft.version === 'V3' && (
                <label>
                  <span>
                    {t('Bot threshold (v3)')} <output>{draft.threshold.toLocaleString(i18n.language, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</output>
                  </span>
                  <input type="range" min={0.1} max={0.9} step={0.1} value={draft.threshold} onChange={(e) => edit({ threshold: Number(e.currentTarget.value) })} />
                  <small>{t('Submissions scoring below this are blocked. 0.5 suits most sites; lower it if real people get stuck.')}</small>
                </label>
              )}
              {draft.version === 'V2' && (
                <>
                  <label>
                    <span>{t('Checkbox theme (v2)')}</span>
                    <select value={draft.v2Theme} onChange={(e) => edit({ v2Theme: e.currentTarget.value as FlxRecaptchaTheme })}>
                      <option value="AUTO">{t("Automatic (follows the site's dark mode)")}</option>
                      <option value="LIGHT">{t('Light')}</option>
                      <option value="DARK">{t('Dark')}</option>
                    </select>
                  </label>
                  <label>
                    <span>{t('Checkbox size (v2)')}</span>
                    <select value={draft.v2Size} onChange={(e) => edit({ v2Size: e.currentTarget.value as FlxRecaptchaSize })}>
                      <option value="NORMAL">{t('Normal')}</option>
                      <option value="COMPACT">{t('Compact')}</option>
                    </select>
                  </label>
                </>
              )}
              <label>
                <span>{t('Google host')}</span>
                <select value={draft.domain} onChange={(e) => edit({ domain: e.currentTarget.value as FlxRecaptchaDomain })}>
                  <option value="GOOGLE">www.google.com</option>
                  <option value="RECAPTCHA">www.recaptcha.net</option>
                </select>
                <small>{t('Use recaptcha.net on networks where google.com is blocked.')}</small>
              </label>
            </div>
            <div className="flxrc-checks">
              <Check checked={draft.skipLoggedIn} onChange={(v) => edit({ skipLoggedIn: v })} label={t('Skip signed-in users')} help={t('Members are not checked on comments, checkout, donations and forms. Login and registration forms are always protected.')} />
              <Check checked={draft.failOpen} onChange={(v) => edit({ failOpen: v })} label={t('Let submissions through if Google is unreachable')} help={t('Recommended. When off, no protected form can be sent during a Google outage (admin login included).')} />
              {draft.version === 'V3' && (
                <Check checked={draft.hideBadge} onChange={(v) => edit({ hideBadge: v })} label={t('Hide the v3 badge')} help={t('The reCAPTCHA badge in the bottom corner is hidden; the notice Google requires is added under protected forms.')} />
              )}
            </div>
            <label className="flxrc-ips">
              <span>{t('IP addresses that skip the check')}</span>
              <textarea className="code" rows={3} placeholder={'203.0.113.7\n198.51.100.0/24'} value={draft.allowIps} onChange={(e) => edit({ allowIps: e.currentTarget.value })} />
              <small>{t('One IP or range (CIDR) per line. For your office, tests and monitoring services. Your current IP address: %s', s.currentIp ?? '?')}</small>
            </label>
            <p className="flxrc-muted flxrc-note">
              <Icon name="info" size={14} />
              {t("If you get locked out of the login screen, add define( 'FLXRC_DISABLE', true ); to wp-config.php; protection turns off completely.")}
            </p>
          </section>
        </div>
        <div className="flxrc-actions">
          <button type="submit" className="flxrc-btn is-primary is-large" disabled={save.isPending}>
            {t('Save settings')}
          </button>
          {dirty && <span className="flxrc-dirty">{t('Unsaved changes')}</span>}
        </div>
      </form>
    </div>
  );
}

function Callout({ kind, icon, children }: { kind: 'info' | 'warn'; icon: string; children: ReactNode }) {
  return (
    <div className={'flxrc-callout is-' + kind}>
      <Icon name={icon} size={18} />
      <div>{children}</div>
    </div>
  );
}

function Kpi({ icon, label, value, hint, cls = '' }: { icon: string; label: string; value: string; hint: string; cls?: string }) {
  return (
    <div className={'flxrc-kpi ' + cls}>
      <span className="flxrc-kpi__icon">
        <Icon name={icon} size={18} />
      </span>
      <div className="flxrc-kpi__body">
        <span className="flxrc-kpi__label">{label}</span>
        <span className="flxrc-kpi__value">{value}</span>
        <span className="flxrc-kpi__hint">{hint}</span>
      </div>
    </div>
  );
}

function FormRow({ f, on, t, lang, onToggle }: { f: Form; on: boolean; t: Translate; lang: string; onToggle: (on: boolean) => void }) {
  return (
    <li className={'flxrc-row' + (f.available ? '' : ' is-off')}>
      <div className="flxrc-row__text">
        <strong>{f.label}</strong>
        {!f.available && <span className="flxrc-badge">{t('not installed')}</span>}
        <small>{f.description}</small>
      </div>
      <div className="flxrc-row__stat" title={t('Last 30 days: passed / blocked')}>
        <span className="is-ok">{f.passed.toLocaleString(lang)}</span>
        <span className="flxrc-sep">/</span>
        <span className={f.blocked ? 'is-bad' : ''}>{f.blocked.toLocaleString(lang)}</span>
      </div>
      <label className="flxrc-switch">
        <input type="checkbox" checked={on} disabled={!f.available} onChange={(e) => onToggle(e.currentTarget.checked)} />
        <span className="flxrc-switch__ui" aria-hidden="true" />
        <span className="flxrc-sr">{f.label}</span>
      </label>
    </li>
  );
}

function Check({ checked, onChange, label, help }: { checked: boolean; onChange: (v: boolean) => void; label: string; help: string }) {
  return (
    <label className="flxrc-check">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span>
        <strong>{label}</strong>
        <small>{help}</small>
      </span>
    </label>
  );
}

/** A translated sentence with one <a href="%s">…</a> link. */
function Linked({ text, href, external = false }: { text: string; href: string; external?: boolean }) {
  const m = /^(.*)<a>(.*)<\/a>(.*)$/s.exec(text);
  // A translation without the link markup is shown as text; React escapes it, nothing is parsed as HTML.
  if (!m) return <>{text.split('<a>').join('').split('</a>').join('')}</>;
  return (
    <>
      {m[1]}
      <a href={href} {...(external ? { target: '_blank', rel: 'noopener' } : {})}>
        {m[2]}
      </a>
      {m[3]}
    </>
  );
}
