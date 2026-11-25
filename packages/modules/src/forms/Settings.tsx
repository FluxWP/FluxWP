// Forms › Settings: the classic settings row over flxFormsSettings / flxFormsSettingsUpdate. One draft for every
// section; Save sends only what changed. The reCAPTCHA secret is write-only.
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsSettingsAllFragment, FlxFormsSettingsUpdateInput } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsClearFiles, FlxFormsSettings, FlxFormsSettingsUpdate } from '@fluxwp/graphql/operations';
import { Check, Loading, Modal } from '@fluxwp/ui';
import { Field, errorText, payloadError, useForms } from './common';

type S = FlxFormsSettingsAllFragment;
type Draft = Omit<S, 'secretKeySet' | 'fromHint' | 'trackShortcode' | 'canEmailReport' | 'canEmailStats'>;
type Section = 'general' | 'responses' | 'colors' | 'captcha' | 'email' | 'template' | 'sms';

const READONLY = ['secretKeySet', 'fromHint', 'trackShortcode', 'canEmailReport', 'canEmailStats', '__typename'] as const;

const draftOf = (s: S): Draft => {
  const d: Record<string, unknown> = { ...s };
  for (const k of READONLY) delete d[k];
  return d as Draft;
};

const TRACK: [string, string][] = [
  ['date_num', 'Date + number'],
  ['date_local_mix', 'Date + local letters and numbers'],
  ['date_local_alpha', 'Date + local letters'],
  ['date_en_mix', 'Date + English letters and numbers'],
  ['date_local_num', 'Date + local numbers'],
  ['unique_num', 'Unique number'],
  ['local_mix', 'Local letters and numbers'],
];

const COLORS = [
  ['respPrimary', 'Primary'],
  ['respPrimaryDark', 'Primary (dark)'],
  ['respAccent', 'Accent'],
  ['respText', 'Text'],
  ['respTextMuted', 'Muted text'],
  ['respBgCard', 'Card background'],
  ['respBgMeta', 'Details background'],
  ['respBgTrack', 'Track code background'],
  ['respBgResp', 'Reply background'],
  ['respBgEditor', 'Editor background'],
  ['respEditorText', 'Editor text'],
  ['respEditorPh', 'Editor placeholder'],
  ['respBtnText', 'Button text'],
] as const satisfies readonly (readonly [keyof Draft, string])[];

type ColorKey = (typeof COLORS)[number][0];
type Palette = Partial<Record<ColorKey, string>>;
const LIGHT: Palette = {
  respPrimary: '#3644d2', respPrimaryDark: '#202a8d', respAccent: '#ffc107', respBtnText: '#ffffff', respText: '#1a1a2e', respTextMuted: '#657096',
  respBgCard: '#ffffff', respBgMeta: '#f6f7fb', respBgResp: '#f8f9fd', respBgTrack: '#ffffff', respBgEditor: '#ffffff', respEditorText: '#1a1a2e', respEditorPh: '#a0aec0',
};
// Same palettes as the classic Colors & Fonts dialog (list_form-efb.js).
const DARK: Palette = {
  respPrimary: '#7b8cff', respPrimaryDark: '#3f4bd8', respAccent: '#ffc107', respBtnText: '#ffffff', respText: '#e9ebf7', respTextMuted: '#9aa3c7',
  respBgCard: '#1c2030', respBgMeta: '#252a3d', respBgResp: '#13161f', respBgTrack: '#1c2030', respBgEditor: '#252a3d', respEditorText: '#e9ebf7', respEditorPh: '#6b7394',
};
const shade = (h: string, amount: number) => {
  const n = parseInt(h.slice(1), 16);
  return '#' + [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((c) => Math.max(0, Math.min(255, Math.round(c + amount))).toString(16).padStart(2, '0')).join('');
};
const brand = (c: string): Palette => ({ ...LIGHT, respPrimary: c, respPrimaryDark: shade(c, -48), respAccent: shade(c, 70) });
const FONTS: [string, string][] = [
  ['inherit', 'Default (theme font)'],
  ["'Inter', sans-serif", 'Inter'],
  ["'Roboto', sans-serif", 'Roboto'],
  ["'Open Sans', sans-serif", 'Open Sans'],
  ['Tahoma, Geneva, sans-serif', 'Tahoma'],
  ["Georgia, 'Times New Roman', serif", 'Georgia (Serif)'],
  ["'Courier New', Courier, monospace", 'Courier (Mono)'],
];

export function SettingsPage() {
  const { t, toast } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxFormsSettings, {});
  const s = q.data?.flxFormsSettings;
  const [draft, setDraft] = useState<Draft | null>(() => (s ? draftOf(s) : null));
  const [secret, setSecret] = useState('');
  const [clearSecret, setClearSecret] = useState(false);
  const [section, setSection] = useState<Section>('general');
  const [askClear, setAskClear] = useState(false);
  const save = useFlxMutation(FlxFormsSettingsUpdate);
  const clear = useFlxMutation(FlxFormsClearFiles);

  useEffect(() => {
    if (s && !draft) setDraft(draftOf(s));
  }, [s, draft]);

  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!s || !draft) return <Loading />;

  const base = draftOf(s);
  const changed = (Object.keys(draft) as (keyof Draft)[]).filter((k) => draft[k] !== base[k]);
  const dirty = changed.length > 0 || secret !== '' || clearSecret;
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => setDraft((d) => (d ? { ...d, [k]: v } : d));
  const preset = (p: string, brandColor = draft.respBrandColor) => setDraft((d) => (d ? { ...d, ...(p === 'light' ? LIGHT : p === 'dark' ? DARK : p === 'brand' ? brand(brandColor) : {}), respPreset: p, respBrandColor: brandColor } : d));
  const paint = (k: keyof Draft, v: string) => setDraft((d) => (d ? { ...d, [k]: v, respPreset: 'custom' } : d));

  const submit = async () => {
    const input: Record<string, unknown> = {};
    for (const k of changed) input[k] = draft[k];
    if (secret) input.secretKey = secret;
    else if (clearSecret) input.clearSecretKey = true;
    try {
      const r = (await save.mutateAsync({ input: input as FlxFormsSettingsUpdateInput })).flxFormsSettingsUpdate;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      if (r?.settings) {
        qc.setQueryData(flxQueryKey(scope, FlxFormsSettings, {}), { flxFormsSettings: r.settings });
        setDraft(draftOf(r.settings));
      }
      setSecret('');
      setClearSecret(false);
      toast(t('Settings saved.'));
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  const clearFiles = async () => {
    try {
      const r = (await clear.mutateAsync({ input: {} })).flxFormsClearFiles;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      toast(t('{n} files deleted.', { n: r?.deleted ?? 0 }));
      setAskClear(false);
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast(t('Copied.'));
    } catch {
      toast(text);
    }
  };

  const sections: [Section, string][] = [
    ['general', t('General')],
    ['responses', t('Responses')],
    ['colors', t('Response box')],
    ['captcha', t('Captcha')],
    ['email', t('E-mail')],
    ['template', t('E-mail template')],
    ['sms', t('SMS')],
  ];
  const sw = (k: keyof Draft, label: string, note?: string, disabled?: boolean) => (
    <Check checked={Boolean(draft[k])} disabled={disabled} note={note} onChange={(v) => set(k, v as never)}>
      {label}
    </Check>
  );
  const text = (k: keyof Draft, label: string, opts?: { type?: string; placeholder?: string; note?: string }) => (
    <Field label={label} note={opts?.note}>
      <input type={opts?.type ?? 'text'} value={String(draft[k] ?? '')} placeholder={opts?.placeholder} onChange={(e) => set(k, e.target.value as never)} />
    </Field>
  );
  const color = (k: keyof Draft, label: string, onPick: (v: string) => void = (v) => set(k, v as never)) => (
    <label key={k} className="flxfrm-color">
      <input type="color" value={String(draft[k])} onChange={(e) => onPick(e.target.value)} aria-label={label} />
      <span>{label}</span>
      <code>{String(draft[k])}</code>
    </label>
  );

  return (
    <div className="flxfrm-settings">
      <div className="flxfrm-toolbar">
        <div className="flxfrm-seg flxfrm-seg--wrap" role="tablist">
          {sections.map(([id, label]) => (
            <button key={id} type="button" role="tab" aria-selected={section === id} className={section === id ? 'is-on' : ''} onClick={() => setSection(id)}>
              {label}
            </button>
          ))}
        </div>
        <button type="button" className="flxfrm-btn is-primary" disabled={!dirty || save.isPending} onClick={() => void submit()}>
          {save.isPending ? t('Saving…') : t('Save')}
        </button>
      </div>

      {section === 'general' ? (
        <div className="flxfrm-card flxfrm-form">
          {sw('bootstrap', t('My theme uses Bootstrap'), t('Turn on when the site theme already loads Bootstrap, so forms do not load it again.'))}
          {sw('locationPicker', t('Location picker without Google'), t('Uses OpenStreetMap for map fields; no API key needed.'))}
          {sw('devMode', t('Developer mode'), t('Loads the unminified scripts. Keep it off on a live site.'))}
          <Field label={t('Unused uploads')} note={t('Deletes uploaded files that no response uses any more.')}>
            <button type="button" className="flxfrm-btn is-danger-text" onClick={() => setAskClear(true)}>
              {t('Delete unused files')}
            </button>
          </Field>
        </div>
      ) : null}

      {section === 'responses' ? (
        <div className="flxfrm-card flxfrm-form">
          <Field label={t('Track code finder')} note={t('Put this shortcode on a page so visitors can look up their response.')}>
            <button type="button" className="flxfrm-code" onClick={() => void copy(s.trackShortcode)}>
              {s.trackShortcode}
            </button>
          </Field>
          <Field label={t('Response session')} note={t('How long a visitor stays signed in to their response after opening it.')}>
            <select value={draft.sessionDuration} onChange={(e) => set('sessionDuration', Number(e.target.value))}>
              {[1, 2, 3, 4, 5, 6, 7].map((n) => (
                <option key={n} value={n}>
                  {n === 1 ? t('1 day') : t('{n} days', { n })}
                </option>
              ))}
            </select>
          </Field>
          <Field label={t('Track code style')}>
            <select value={draft.trackCodeStyle} onChange={(e) => set('trackCodeStyle', e.target.value)}>
              {TRACK.map(([v, l]) => (
                <option key={v} value={v}>
                  {t(l)}
                </option>
              ))}
            </select>
          </Field>
          {sw('shortCaptcha', t('Captcha on replies'), t('Visitors solve a captcha before they reply to a response.'))}
          {sw('showUploadedFiles', t('Show uploaded files'), t('Visitors see the files they sent with their response.'))}
          {sw('downloadButton', t('Download button'), t('Shows a button to download the response.'))}
          {sw('adminSeesName', t('Show staff names'), t('Visitors see the name of the staff member who replied.'))}
        </div>
      ) : null}

      {section === 'colors' ? (
        <div className="flxfrm-card flxfrm-form">
          <p className="flxfrm-muted">{t('Colours and font of the response box visitors see after sending a form.')}</p>
          <Field label={t('Preset')}>
            <select value={draft.respPreset} onChange={(e) => preset(e.target.value)}>
              <option value="light">{t('Light')}</option>
              <option value="dark">{t('Dark')}</option>
              <option value="brand">{t('Brand colour')}</option>
              <option value="custom">{t('Custom')}</option>
            </select>
          </Field>
          {draft.respPreset === 'brand' ? color('respBrandColor', t('Brand colour'), (v) => preset('brand', v)) : null}
          <div className="flxfrm-colors">{COLORS.map(([k, l]) => color(k, t(l), (v) => paint(k, v)))}</div>
          <Field label={t('Font family')}>
            <select value={draft.respFontFamily} onChange={(e) => set('respFontFamily', e.target.value)}>
              {FONTS.some(([v]) => v === draft.respFontFamily) ? null : <option value={draft.respFontFamily}>{draft.respFontFamily}</option>}
              {FONTS.map(([v, l]) => (
                <option key={v} value={v}>
                  {v === 'inherit' ? t(l) : l}
                </option>
              ))}
            </select>
          </Field>
          {text('respFontSize', t('Font size'), { placeholder: '0.9rem', note: t('A size like 0.9rem, 14px or 100%.') })}
          {text('respCustomFont', t('Custom font address'), { placeholder: 'https://…', note: t('Optional stylesheet address of a web font.') })}
        </div>
      ) : null}

      {section === 'captcha' ? (
        <div className="flxfrm-card flxfrm-form">
          <h2>{t('Google reCAPTCHA')}</h2>
          {text('siteKey', t('Site key'))}
          <Field label={t('Secret key')} note={s.secretKeySet && !clearSecret ? t('A secret key is saved. Enter a new one to replace it.') : t('Not saved yet.')}>
            <div className="flxfrm-inline">
              <input type="password" autoComplete="new-password" value={secret} placeholder={s.secretKeySet && !clearSecret ? '••••••••' : ''} onChange={(e) => setSecret(e.target.value)} />
              {s.secretKeySet ? (
                <button type="button" className="flxfrm-btn is-danger-text" disabled={clearSecret} onClick={() => setClearSecret(true)}>
                  {t('Remove')}
                </button>
              ) : null}
            </div>
          </Field>
          {sw('silentCaptcha', t('Silent captcha'), t('Human Shield checks visitors in the background, without a puzzle.'))}
        </div>
      ) : null}

      {section === 'email' ? (
        <div className="flxfrm-card flxfrm-form">
          {text('adminEmail', t('Admin e-mail'), { type: 'email', note: t('New responses are sent to this address.') })}
          {text('fromEmail', t('Sender address'), { type: 'email', placeholder: s.fromHint, note: t('Use an address on this site’s domain so mail is not marked as spam.') })}
          {sw('smtp', t('This server can send e-mail'), t('Turn on after you set up SMTP (Flux Mail or another mail plugin).'))}
          {sw('weeklyReport', t('Weekly report'), s.canEmailReport ? t('A weekly summary of responses by e-mail.') : t('Only an administrator can change this.'), !s.canEmailReport)}
          {sw('emailStatsReport', t('E-mail statistics report'), s.canEmailStats ? t('Delivery statistics of form e-mails.') : t('Only an administrator can change this.'), !s.canEmailStats)}
        </div>
      ) : null}

      {section === 'template' ? (
        <div className="flxfrm-card flxfrm-form">
          <Field label={t('E-mail template (HTML)')} note={t('Leave empty for the default. A template must contain shortcode_message where the message goes.')}>
            <textarea rows={14} className="flxfrm-mono" value={draft.emailTemplate} onChange={(e) => set('emailTemplate', e.target.value)} />
          </Field>
          <div className="flxfrm-colors">
            {color('emailButtonColor', t('Button colour'))}
            {color('emailButtonText', t('Button text colour'))}
          </div>
        </div>
      ) : null}

      {section === 'sms' ? (
        <div className="flxfrm-card flxfrm-form">
          <Field label={t('SMS sender')}>
            <select value={draft.smsSender} onChange={(e) => set('smsSender', e.target.value)}>
              <option value="null">{t('Off')}</option>
              <option value="wp_sms_plugin">{t('WP SMS plugin')}</option>
            </select>
          </Field>
          {text('smsAdminNumbers', t('Admin phone numbers'), { type: 'tel', placeholder: '+12345678900', note: t('Numbers that get an SMS for new responses, separated by commas.') })}
        </div>
      ) : null}

      {askClear ? (
        <Modal title={t('Delete unused files')} onClose={() => setAskClear(false)}>
          <p>{t('Delete uploaded files that no response uses? This cannot be undone.')}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAskClear(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxfrm-btn is-danger" disabled={clear.isPending} onClick={() => void clearFiles()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
