// Flux Forms settings: the classic panel's tabs (general, responses, captchas, email, email template,
// localization, payments, SMS). Keys and tokens are write-only: they are typed, sent once and never
// read back; a "saved" flag says whether one is stored.
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { payloadErrors } from '@fluxwp/graphql';
import { FlxFormsSettings, FlxFormsSettingsUpdate } from '@fluxwp/graphql/operations';
import type { FlxFormsSettingsQuery, FlxFormsSettingsUpdateInput, FlxFormsSmsService, FlxFormsTrackCodeStyle } from '@fluxwp/graphql/types';
import { Icon, Loading } from '@fluxwp/ui';
import type { Translate } from './i18n';
import { Check, errorText, Field, type Notice } from './ui';

type S = FlxFormsSettingsQuery['flxFormsSettings'];
type Style = S['responseStyle'];

const CLEAR = '__clear__';
/** A saved secret shows this constant mask; nothing of the secret ever reaches the screen. */
const MASK = '••••••••';

export const SETTINGS_TABS = ['general', 'responses', 'captchas', 'email', 'email-template', 'localization', 'payments', 'sms'] as const;
type TabId = (typeof SETTINGS_TABS)[number];

/** Classic tab aliases (list_form-efb.js efb_normalize_setting_tab_efb). */
const ALIASES: Record<string, TabId> = { response: 'responses', google: 'captchas', captcha: 'captchas', emailtemplate: 'email-template', text: 'localization', stripe: 'payments', smsconfig: 'sms' };
const tabOf = (s: string): TabId => ((SETTINGS_TABS as readonly string[]).includes(s) ? (s as TabId) : (ALIASES[s] ?? 'general'));

const STYLE_DEFAULTS: Style = {
  preset: 'light',
  brandColor: '#0f766e',
  primary: '#3644d2',
  primaryDark: '#202a8d',
  accent: '#ffc107',
  text: '#1a1a2e',
  textMuted: '#657096',
  bgCard: '#ffffff',
  bgMeta: '#f6f7fb',
  bgTrack: '#ffffff',
  bgResp: '#f8f9fd',
  bgEditor: '#ffffff',
  editorText: '#1a1a2e',
  editorPh: '#a0aec0',
  btnText: '#ffffff',
  fontFamily: 'inherit',
  fontSize: '0.9rem',
  customFont: '',
};

interface Secrets {
  secretKey: string;
  mapKey: string;
  stripeSecretKey: string;
  paypalSecretKey: string;
  payToken: string;
  smsPhoneNumbers: string;
}
type SecretKey = keyof Secrets;
const NO_SECRETS: Secrets = { secretKey: '', mapKey: '', stripeSecretKey: '', paypalSecretKey: '', payToken: '', smsPhoneNumbers: '' };

const PLAIN: (keyof S & keyof FlxFormsSettingsUpdateInput)[] = [
  'bootstrap',
  'osLocationPicker',
  'sessionDuration',
  'trackCodeStyle',
  'scaptcha',
  'dsupfile',
  'activeDlBtn',
  'showIp',
  'adminSN',
  'siteKey',
  'shieldSilentCaptcha',
  'emailSupporter',
  'femail',
  'smtp',
  'weeklyEmailReport',
  'emailStatsReport',
  'emailTemp',
  'emailBtnBgColor',
  'emailBtnTextColor',
  'payProvider',
  'stripePKey',
  'paypalPKey',
  'smsConfig',
];

export function Settings({ t, tab, onTab, classicUrl, onNotice }: { t: Translate; tab: string; onTab: (tab: string) => void; classicUrl?: string; onNotice: (n: Notice | null) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxFormsSettings, {});
  const [draft, setDraft] = useState<S | null>(null);
  const [secrets, setSecrets] = useState<Secrets>(NO_SECRETS);
  const [clear, setClear] = useState<Set<SecretKey>>(new Set());
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [dirty, setDirty] = useState(false);
  const current = tabOf(tab);

  useEffect(() => {
    if (q.data && !dirty) setDraft(q.data.flxFormsSettings);
  }, [q.data, dirty]);

  const save = useFlxMutation(FlxFormsSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxFormsSettingsUpdate;
      if (!p) return onNotice({ kind: 'error', text: t('The settings could not be saved.') });
      if (p.errors.length || !p.settings) {
        setErrors(payloadErrors(p));
        return onNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') || t('The settings could not be saved.') });
      }
      qc.setQueryData<FlxFormsSettingsQuery>(flxQueryKey(scope, FlxFormsSettings, {}), { flxFormsSettings: p.settings });
      setDraft(p.settings);
      setSecrets(NO_SECRETS);
      setClear(new Set());
      setErrors({});
      setDirty(false);
      onNotice({ kind: 'success', text: t('Saved.') });
      window.scrollTo?.({ top: 0, behavior: 'smooth' });
    },
    onError: (e) => onNotice({ kind: 'error', text: errorText(e, t('The settings could not be saved.')) }),
  });

  const edit = (patch: Partial<S>) => {
    setDraft((o) => (o ? { ...o, ...patch } : o));
    setDirty(true);
  };
  const editStyle = (patch: Partial<Style>) => {
    setDraft((o) => (o ? { ...o, responseStyle: { ...o.responseStyle, ...patch } } : o));
    setDirty(true);
  };
  const secret = (key: SecretKey, value: string) => {
    setSecrets((o) => ({ ...o, [key]: value }));
    setClear((o) => {
      const n = new Set(o);
      n.delete(key);
      return n;
    });
    setDirty(true);
  };
  const markClear = (key: SecretKey, on: boolean) => {
    setClear((o) => {
      const n = new Set(o);
      if (on) n.add(key);
      else n.delete(key);
      return n;
    });
    setSecrets((o) => ({ ...o, [key]: '' }));
    setDirty(true);
  };

  const input = useMemo<FlxFormsSettingsUpdateInput | null>(() => {
    const base = q.data?.flxFormsSettings;
    if (!base || !draft) return null;
    const out: Record<string, unknown> = {};
    for (const k of PLAIN) if (draft[k] !== base[k]) out[k] = draft[k];
    const style: Record<string, string> = {};
    for (const k of Object.keys(draft.responseStyle) as (keyof Style)[]) if (draft.responseStyle[k] !== base.responseStyle[k]) style[k] = draft.responseStyle[k];
    if (Object.keys(style).length) out.responseStyle = style;
    for (const k of Object.keys(secrets) as SecretKey[]) {
      if (clear.has(k)) out[k] = CLEAR;
      else if (secrets[k].trim() !== '') out[k] = secrets[k].trim();
    }
    return out as FlxFormsSettingsUpdateInput;
  }, [q.data, draft, secrets, clear]);

  if (q.isPending) return <Loading />;
  if (q.isError || !draft) return <p className="flxf-empty">{t('The settings could not be loaded.')}</p>;
  const s = draft;
  const changed = input !== null && Object.keys(input).length > 0;

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!input || !changed) return onNotice({ kind: 'success', text: t('Nothing to save.') });
    onNotice(null);
    setErrors({});
    save.mutate({ input });
  };
  const tabs: [TabId, string, string][] = [
    ['general', t('General'), 'settings'],
    ['responses', t('Response box'), 'comment'],
    ['captchas', t('Captchas'), 'shield'],
    ['email', t('Email'), 'mail'],
    ['email-template', t('Email template'), 'page'],
    ['localization', t('Localization'), 'translation'],
    ['payments', t('Payments'), 'money'],
    ['sms', t('SMS'), 'phone'],
  ];
  const secretField = (key: SecretKey, label: string, saved: boolean, help: string, placeholder?: string) => (
    <Field label={label} help={help} error={errors[key]}>
      <span className="flxf-secret">
        <input
          type="password"
          className="code"
          value={secrets[key]}
          disabled={clear.has(key)}
          autoComplete="new-password"
          spellCheck={false}
          placeholder={clear.has(key) ? t('Will be removed when you save') : saved ? MASK : (placeholder ?? t('Not set'))}
          title={saved && !clear.has(key) ? t('Saved. Type to replace it.') : undefined}
          onChange={(e) => secret(key, e.currentTarget.value)}
        />
        {saved && !clear.has(key) && (
          <button type="button" className="flxf-btn" onClick={() => markClear(key, true)}>
            {t('Remove')}
          </button>
        )}
        {clear.has(key) && (
          <button type="button" className="flxf-btn" onClick={() => markClear(key, false)}>
            {t('Undo')}
          </button>
        )}
      </span>
    </Field>
  );

  return (
    <form className="flxf-settings" onSubmit={submit}>
      <div className="flxf-subtabs" role="tablist" aria-label={t('Settings')}>
        {tabs.map(([id, label, icon]) => (
          <button key={id} type="button" role="tab" aria-selected={current === id} className={current === id ? 'is-on' : ''} onClick={() => onTab(id)}>
            <Icon name={icon} size={14} />
            {label}
          </button>
        ))}
      </div>

      {current === 'general' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="settings" size={16} />
              {t('General')}
            </h2>
          </div>
          <div className="flxf-checks">
            <Check checked={s.bootstrap} onChange={(v) => edit({ bootstrap: v })} label={t('My theme already uses Bootstrap')} help={t('Turn this on if your theme loads Bootstrap, so forms do not load it a second time.')} />
            <Check checked={s.osLocationPicker} onChange={(v) => edit({ osLocationPicker: v })} label={t('Use the OpenStreetMap location picker')} help={t('Location fields use OpenStreetMap instead of Google Maps.')} />
          </div>
          {secretField('mapKey', t('Google Maps key'), s.mapKeySaved, t('Entered once, stored and never shown again. Needed for the Google Maps location field.'), t('Paste the key'))}
          {s.version && <p className="flxf-muted">{t('Form builder version: %s', s.version)}</p>}
        </section>
      )}

      {current === 'responses' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="comment" size={16} />
              {t('Response box')}
            </h2>
          </div>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Session length (days)')} help={t('How long a visitor stays signed in to the response box (1 to 7).')} error={errors.sessionDuration}>
              <select value={s.sessionDuration} onChange={(e) => edit({ sessionDuration: Number(e.currentTarget.value) })}>
                {[1, 2, 3, 4, 5, 6, 7].map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </Field>
            <Field label={t('Tracking code style')} help={t('How new tracking codes look.')} error={errors.trackCodeStyle}>
              <select value={s.trackCodeStyle} onChange={(e) => edit({ trackCodeStyle: e.currentTarget.value as FlxFormsTrackCodeStyle })}>
                {(
                  [
                    ['DATE_NUM', t('Date and number')],
                    ['DATE_LOCAL_MIX', t('Date, local letters and numbers')],
                    ['DATE_LOCAL_ALPHA', t('Date and local letters')],
                    ['DATE_EN_MIX', t('Date, English letters and numbers')],
                    ['DATE_LOCAL_NUM', t('Date and local digits')],
                    ['UNIQUE_NUM', t('Unique number')],
                    ['LOCAL_MIX', t('Local letters and numbers')],
                  ] as [FlxFormsTrackCodeStyle, string][]
                ).map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </select>
            </Field>
          </div>
          <div className="flxf-checks">
            <Check checked={s.scaptcha} onChange={(v) => edit({ scaptcha: v })} label={t('Captcha on the tracking page')} help={t('Visitors solve a captcha before they open a response.')} />
            <Check checked={s.dsupfile} onChange={(v) => edit({ dsupfile: v })} label={t('Visitors can attach files to replies')} />
            <Check checked={s.activeDlBtn} onChange={(v) => edit({ activeDlBtn: v })} label={t('Show a download button on the tracking page')} />
            <Check checked={s.showIp} onChange={(v) => edit({ showIp: v })} label={t('Show the sender IP address')} />
            <Check checked={s.adminSN} onChange={(v) => edit({ adminSN: v })} label={t('Show the staff name on replies')} />
          </div>
          <StyleEditor t={t} style={s.responseStyle} error={errors.responseStyle} onChange={editStyle} />
        </section>
      )}

      {current === 'captchas' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="shield" size={16} />
              {t('Captchas')}
            </h2>
          </div>
          <p className="flxf-muted">{t('Google reCAPTCHA keys of the form builder. Form submissions sent over GraphQL use Flux reCAPTCHA instead.')}</p>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Site key')} help={t('The public key from the Google reCAPTCHA console.')} error={errors.siteKey}>
              <input type="text" className="code" value={s.siteKey} autoComplete="off" spellCheck={false} onChange={(e) => edit({ siteKey: e.currentTarget.value })} />
            </Field>
            {secretField('secretKey', t('Secret key'), s.secretKeySaved, t('Entered once, stored and never shown again.'), t('Paste the secret key'))}
          </div>
          <div className="flxf-checks">
            <Check
              checked={s.shieldSilentCaptcha}
              onChange={(v) => edit({ shieldSilentCaptcha: v })}
              label={t('Silent captcha with Shield')}
              help={s.shieldAvailable ? t('Shield judges visitors without asking them anything.') : t('Shield is not installed, so this has no effect yet.')}
            />
          </div>
        </section>
      )}

      {current === 'email' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="mail" size={16} />
              {t('Email')}
            </h2>
          </div>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Notification address')} help={t('Receives a mail for every new response. Separate several addresses with commas on the form itself.')} error={errors.emailSupporter}>
              <input type="email" value={s.emailSupporter} autoComplete="off" onChange={(e) => edit({ emailSupporter: e.currentTarget.value })} />
            </Field>
            <Field label={t('Sender address')} help={t('The "from" address of the mails. Use an address on your own domain.')} error={errors.femail}>
              <input type="email" value={s.femail} autoComplete="off" onChange={(e) => edit({ femail: e.currentTarget.value })} />
            </Field>
          </div>
          <div className="flxf-checks">
            <Check checked={s.smtp} onChange={(v) => edit({ smtp: v })} label={t('This site can send email')} help={t('Turn this off if the server cannot send mail; no notification mails are attempted.')} />
            <Check checked={s.weeklyEmailReport} onChange={(v) => edit({ weeklyEmailReport: v })} label={t('Weekly delivery report')} help={t('A weekly mail that says whether the form mails were delivered.')} />
            <Check checked={s.emailStatsReport} onChange={(v) => edit({ emailStatsReport: v })} label={t('Include delivery statistics')} />
          </div>
          {classicUrl && (
            <p className="flxf-muted flxf-note">
              <Icon name="info" size={14} />
              <span>
                {t('The live mail server test is in the classic panel.')} <a href={classicUrl}>{t('Open the classic panel')}</a>
              </span>
            </p>
          )}
        </section>
      )}

      {current === 'email-template' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="page" size={16} />
              {t('Email template')}
            </h2>
          </div>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Button colour')} error={errors.emailBtnBgColor}>
              <ColorInput value={s.emailBtnBgColor} onChange={(v) => edit({ emailBtnBgColor: v })} />
            </Field>
            <Field label={t('Button text colour')} error={errors.emailBtnTextColor}>
              <ColorInput value={s.emailBtnTextColor} onChange={(v) => edit({ emailBtnTextColor: v })} />
            </Field>
          </div>
          <Field label={t('Template (HTML)')} help={t('Has to contain shortcode_message where the message goes. Scripts and forms are removed when you save. Leave it empty to use the default.')} error={errors.emailTemp}>
            <textarea className="code" rows={14} value={s.emailTemp} spellCheck={false} onChange={(e) => edit({ emailTemp: e.currentTarget.value })} />
          </Field>
          {s.emailTemp.trim() !== '' && (
            <>
              <h3 className="flxf-sub">{t('Preview')}</h3>
              <iframe className="flxf-preview" title={t('Preview')} sandbox="" srcDoc={s.emailTemp.split('shortcode_message').join(t('Your message appears here.'))} />
            </>
          )}
        </section>
      )}

      {current === 'localization' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="translation" size={16} />
              {t('Localization')}
            </h2>
          </div>
          <p>{t('The texts of the forms and the response box follow the language of the site. They are translated in the plugin language files, so there is nothing to edit here.')}</p>
          {classicUrl && (
            <p className="flxf-muted">
              {t('The text editor of the form builder is in the classic panel.')} <a href={classicUrl}>{t('Open the classic panel')}</a>
            </p>
          )}
        </section>
      )}

      {current === 'payments' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="money" size={16} />
              {t('Payments')}
            </h2>
          </div>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Payment provider')} help={t('The id of the provider that handles payment forms, for example stripe.')} error={errors.payProvider}>
              <input type="text" value={s.payProvider} autoComplete="off" onChange={(e) => edit({ payProvider: e.currentTarget.value })} />
            </Field>
          </div>
          <h3 className="flxf-sub">Stripe</h3>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Publishable key')} error={errors.stripePKey}>
              <input type="text" className="code" value={s.stripePKey} autoComplete="off" spellCheck={false} onChange={(e) => edit({ stripePKey: e.currentTarget.value })} />
            </Field>
            {secretField('stripeSecretKey', t('Secret key'), s.stripeSecretSaved, t('Entered once, stored and never shown again.'), t('Paste the secret key'))}
          </div>
          <h3 className="flxf-sub">PayPal</h3>
          <div className="flxf-fields flxf-fields--grid">
            <Field label={t('Client id')} error={errors.paypalPKey}>
              <input type="text" className="code" value={s.paypalPKey} autoComplete="off" spellCheck={false} onChange={(e) => edit({ paypalPKey: e.currentTarget.value })} />
            </Field>
            {secretField('paypalSecretKey', t('Secret'), s.paypalSecretSaved, t('Entered once, stored and never shown again.'), t('Paste the secret'))}
          </div>
          <h3 className="flxf-sub">{t('Payment gateway token')}</h3>
          <div className="flxf-fields flxf-fields--grid">{secretField('payToken', t('Token'), s.payTokenSaved, t('Entered once, stored and never shown again.'), t('Paste the token'))}</div>
        </section>
      )}

      {current === 'sms' && (
        <section className="flxf-card">
          <div className="flxf-card__head">
            <h2>
              <Icon name="phone" size={16} />
              {t('SMS')}
            </h2>
          </div>
          <fieldset className="flxf-choice">
            <legend className="flxf-sr">{t('SMS service')}</legend>
            {(
              [
                ['NONE', t('No SMS'), t('No text messages are sent.')],
                ['WP_SMS', t('WP SMS plugin'), t('Uses the WP SMS plugin you installed.')],
                ['SERVICE', t('Built-in SMS service'), t('Uses the SMS service of the form builder.')],
              ] as [FlxFormsSmsService, string, string][]
            ).map(([v, l, h]) => (
              <label key={v} className="flxf-choice__item">
                <input type="radio" name="sms" value={v} checked={s.smsConfig === v} onChange={() => edit({ smsConfig: v })} />
                <span>
                  <strong>{l}</strong>
                  <small>{h}</small>
                </span>
              </label>
            ))}
          </fieldset>
          {secretField('smsPhoneNumbers', t('Phone numbers'), s.smsPhoneSaved, t('With the country code, separated by commas, for example +12345678900. Stored and never shown again.'), '+12345678900')}
        </section>
      )}

      <div className="flxf-actions">
        <button type="submit" className="flxf-btn is-primary is-large" disabled={save.isPending}>
          {save.isPending ? t('Saving…') : t('Save settings')}
        </button>
        {changed && <span className="flxf-dirty">{t('Unsaved changes')}</span>}
      </div>
    </form>
  );
}

function ColorInput({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const hex = /^#[0-9a-f]{6}$/i.test(value) ? value : '#000000';
  return (
    <span className="flxf-color">
      <input type="color" value={hex} aria-label={value} onChange={(e) => onChange(e.currentTarget.value)} />
      <input type="text" className="code" value={value} maxLength={7} spellCheck={false} onChange={(e) => onChange(e.currentTarget.value)} />
    </span>
  );
}

/** Colours and fonts of the public response box, with a live preview. */
function StyleEditor({ t, style, error, onChange }: { t: Translate; style: Style; error?: string; onChange: (patch: Partial<Style>) => void }) {
  const colors: [keyof Style, string][] = [
    ['primary', t('Primary')],
    ['primaryDark', t('Primary, dark')],
    ['accent', t('Accent')],
    ['text', t('Text')],
    ['textMuted', t('Muted text')],
    ['bgCard', t('Card background')],
    ['bgMeta', t('Meta background')],
    ['bgTrack', t('Tracker background')],
    ['bgResp', t('Reply background')],
    ['bgEditor', t('Editor background')],
    ['editorText', t('Editor text')],
    ['editorPh', t('Editor placeholder')],
    ['btnText', t('Button text')],
  ];
  return (
    <div className="flxf-style">
      <h3 className="flxf-sub">{t('Colours and fonts')}</h3>
      <p className="flxf-muted">{t('How the public response box looks to your visitors.')}</p>
      {error && (
        <p className="flxf-field__error" role="alert">
          {error}
        </p>
      )}
      <div className="flxf-style__grid">
        <div className="flxf-fields flxf-fields--grid">
          {colors.map(([k, label]) => (
            <Field key={k} label={label}>
              <ColorInput value={style[k]} onChange={(v) => onChange({ [k]: v, preset: 'custom' })} />
            </Field>
          ))}
          <Field label={t('Font')} help={t('"inherit" follows the site font.')}>
            <input type="text" value={style.fontFamily} onChange={(e) => onChange({ fontFamily: e.currentTarget.value })} />
          </Field>
          <Field label={t('Font size')}>
            <input type="text" value={style.fontSize} onChange={(e) => onChange({ fontSize: e.currentTarget.value })} />
          </Field>
        </div>
        <div className="flxf-box" style={{ background: style.bgTrack, color: style.text, fontFamily: style.fontFamily, fontSize: style.fontSize }} aria-hidden="true">
          <div className="flxf-box__card" style={{ background: style.bgCard, borderColor: style.bgMeta }}>
            <div className="flxf-box__meta" style={{ background: style.bgMeta, color: style.textMuted }}>
              {t('Tracking code')} · 260311-AB12
            </div>
            <p>{t('Your message appears here.')}</p>
            <div className="flxf-box__reply" style={{ background: style.bgResp }}>
              <strong style={{ color: style.primaryDark }}>{t('Support')}</strong>
              <p style={{ color: style.textMuted }}>{t('Thanks, we are on it.')}</p>
            </div>
            <div className="flxf-box__editor" style={{ background: style.bgEditor, color: style.editorPh }}>
              {t('Write a reply')}
            </div>
            <span className="flxf-box__btn" style={{ background: style.primary, color: style.btnText }}>
              {t('Send')}
            </span>
          </div>
        </div>
      </div>
      <button type="button" className="flxf-btn" onClick={() => onChange({ ...STYLE_DEFAULTS })}>
        {t('Restore the default colours')}
      </button>
    </div>
  );
}
