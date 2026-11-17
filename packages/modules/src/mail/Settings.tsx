import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxMailSettingsInput, FlxMailSettingsQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxMailDnsCheck, FlxMailImapTest, FlxMailPreview, FlxMailSettings, FlxMailSettingsUpdate, FlxMailTestSend, FlxMailWcBrandApply } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useMail } from './common';

type Settings = FlxMailSettingsQuery['flxMailSettings'];
type SecretKey = 'smtpPassword' | 'smtpNoreplyPassword' | 'imapPassword';

function toDraft(s: Settings): FlxMailSettingsInput {
  const url = (network: string) => s.social.find((x) => x.network === network)?.url ?? '';
  return {
    fromName: s.fromName,
    fromEmail: s.fromEmail,
    noreplyName: s.noreplyName,
    noreplyEmail: s.noreplyEmail,
    replyTo: s.replyTo,
    forceFrom: s.forceFrom,
    setSender: s.setSender,
    mailer: s.mailer,
    smtpHost: s.smtpHost,
    smtpPort: s.smtpPort,
    smtpSecure: s.smtpSecure,
    smtpAuthentication: s.smtpAuthentication,
    smtpUser: s.smtpUser,
    smtpNoreplyUser: s.smtpNoreplyUser,
    mailboxEnabled: s.mailboxEnabled,
    imapHost: s.imapHost,
    imapPort: s.imapPort,
    imapSecure: s.imapSecure,
    imapUser: s.imapUser,
    mailboxDays: s.mailboxDays,
    mailboxKeepDays: s.mailboxKeepDays,
    mailboxSignature: s.mailboxSignature,
    wrapPlain: s.wrapPlain,
    wrapHtml: s.wrapHtml,
    emailImagesFix: s.emailImagesFix,
    embedImages: s.embedImages,
    footerArt: s.footerArt,
    logo: s.logo,
    logoWhite: s.logoWhite,
    logoWidth: s.logoWidth,
    colorPrimary: s.colorPrimary,
    colorDark: s.colorDark,
    colorBg: s.colorBg,
    colorText: s.colorText,
    orgName: s.orgName,
    tagline: s.tagline,
    address: s.address,
    phone: s.phone,
    contactEmail: s.contactEmail,
    website: s.website,
    social: s.socialNetworks.map((n) => ({ network: n.key, url: url(n.key) })),
    footerNote: s.footerNote,
    socialBlockCore: s.socialBlockCore,
    socialBlockWc: s.socialBlockWc,
    socialVerified: s.socialVerified,
    logEnabled: s.logEnabled,
    logBody: s.logBody,
    logDays: s.logDays,
    bulkPerRun: s.bulkPerRun,
    bulkUnsubscribe: s.bulkUnsubscribe,
    optoutEnabled: s.optoutEnabled,
    optoutFooterWa: s.optoutFooterWa,
    optoutFooterSms: s.optoutFooterSms,
    optoutText: s.optoutText,
    optoutKeywords: s.optoutKeywords,
    optinKeywords: s.optinKeywords,
    optoutReply: s.optoutReply,
    optinReply: s.optinReply,
    limitDaily: s.limitDaily,
    limitRecipients: s.limitRecipients,
    limitReserve: s.limitReserve,
    limitSizeMb: s.limitSizeMb,
    limitAttachMb: s.limitAttachMb,
    limitStorageGb: s.limitStorageGb,
    limitMailboxEmails: s.limitMailboxEmails,
    limitAliases: s.limitAliases,
    limitForwarders: s.limitForwarders,
  };
}

const EMPTY_SECRETS: Record<SecretKey, string> = { smtpPassword: '', smtpNoreplyPassword: '', imapPassword: '' };

export function SettingsPage({ section = '' }: { section?: string }) {
  const { t, toast } = useMail();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxMailSettings, {});
  const save = useFlxMutation(FlxMailSettingsUpdate);
  const test = useFlxMutation(FlxMailTestSend);
  const dns = useFlxMutation(FlxMailDnsCheck);
  const imap = useFlxMutation(FlxMailImapTest);
  const brand = useFlxMutation(FlxMailWcBrandApply);
  const preview = useFlxMutation(FlxMailPreview);
  const [draft, setDraft] = useState<FlxMailSettingsInput | null>(null);
  const [live, setLive] = useState<Settings | null>(null);
  const [secrets, setSecrets] = useState(EMPTY_SECRETS);
  const [logos, setLogos] = useState<{ logo: File | null; white: File | null }>({ logo: null, white: null });
  const [testTo, setTestTo] = useState('');
  const [testSender, setTestSender] = useState<'INFO' | 'NOREPLY'>('INFO');
  const [device, setDevice] = useState<'desktop' | 'phone' | 'dark'>('desktop');
  const [html, setHtml] = useState('');

  useEffect(() => {
    if (!q.data) return;
    setLive(q.data.flxMailSettings);
    setDraft((d) => d ?? toDraft(q.data.flxMailSettings));
  }, [q.data]);

  const ready = !!live;
  useEffect(() => {
    if (!section || !ready) return;
    const alias: Record<string, string> = {
      gonderen: 'sender',
      posta: 'mailbox',
      gorunum: 'look',
      woocommerce: 'woo',
      kayit: 'log',
      iptal: 'optout',
      sinirlar: 'limits',
    };
    document.getElementById(alias[section] ?? section)?.scrollIntoView({ behavior: 'smooth' });
  }, [section, ready]);

  const appearance = useMemo(() => {
    if (!draft) return '';
    return [draft.logo, draft.logoWhite, draft.logoWidth, draft.colorPrimary, draft.colorDark, draft.colorBg, draft.colorText, draft.orgName, draft.tagline, draft.address, draft.phone, draft.contactEmail, draft.website, draft.footerNote, draft.footerArt, draft.fromName, JSON.stringify(draft.social)].join('|');
  }, [draft]);

  useEffect(() => {
    if (!draft) return;
    const timer = window.setTimeout(() => {
      void preview
        .mutateAsync({
          input: {
            key: 'brand',
            brand: {
              logo: draft.logo,
              logoWhite: draft.logoWhite,
              logoWidth: draft.logoWidth,
              colorPrimary: draft.colorPrimary,
              colorDark: draft.colorDark,
              colorBg: draft.colorBg,
              colorText: draft.colorText,
              orgName: draft.orgName,
              tagline: draft.tagline,
              address: draft.address,
              phone: draft.phone,
              contactEmail: draft.contactEmail,
              website: draft.website,
              footerNote: draft.footerNote,
              footerArt: draft.footerArt,
              fromName: draft.fromName,
              social: draft.social,
            },
          },
        })
        .then((r) => {
          const body = r.flxMailPreview?.preview?.html ?? '';
          setHtml(device === 'dark' ? body.replace(/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all') : body);
        })
        .catch(() => undefined);
    }, html ? 450 : 0);
    return () => window.clearTimeout(timer);
  }, [appearance, device]); // eslint-disable-line react-hooks/exhaustive-deps

  if (q.isLoading || !draft || !live) return <Loading />;
  if (q.isError) return <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p>;

  const setText = (key: keyof FlxMailSettingsInput, value: string) => setDraft({ ...draft, [key]: value } as FlxMailSettingsInput);
  const setBool = (key: keyof FlxMailSettingsInput, value: boolean) => setDraft({ ...draft, [key]: value } as FlxMailSettingsInput);
  const setNum = (key: keyof FlxMailSettingsInput, value: string) => setDraft({ ...draft, [key]: value === '' ? 0 : Number(value) } as FlxMailSettingsInput);
  const socialUrl = (network: string) => draft.social?.find((x) => x.network === network)?.url ?? '';
  const setSocial = (network: string, url: string) => setDraft({ ...draft, social: (draft.social ?? []).map((x) => (x.network === network ? { ...x, url } : x)) });

  const onSave = async () => {
    const settings: FlxMailSettingsInput = { ...draft };
    (Object.keys(secrets) as SecretKey[]).forEach((k) => {
      if (secrets[k]) settings[k] = secrets[k];
    });
    try {
      if (logos.logo) settings.logoUploadId = await upload(logos.logo);
      if (logos.white) settings.logoWhiteUploadId = await upload(logos.white);
      const r = await save.mutateAsync({ input: { settings } });
      const err = payloadError(r.flxMailSettingsUpdate?.errors);
      if (err) {
        toast(err, 'error');
        return;
      }
      const next = r.flxMailSettingsUpdate?.settings;
      if (next) {
        setLive(next);
        setDraft(toDraft(next));
      }
      setSecrets(EMPTY_SECRETS);
      setLogos({ logo: null, white: null });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailSettings) });
      toast(t('Saved'));
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  const upload = async (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    const up = await host.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', query: { context: 'flx-mail/logo' }, formData: fd });
    return up.uploadId;
  };

  const sections = [
    ['sender', t('Sender')],
    ['smtp', t('Sending method')],
    ['mailbox', t('Mailbox')],
    ['test', t('Test')],
    ['look', t('Appearance')],
    ['google', t('Google sign-in')],
    ['woo', 'WooCommerce'],
    ['log', t('Log and bulk send')],
    ['optout', t('Opt-out (STOP)')],
    ['limits', t('Mailbox limits')],
  ] as const;

  return (
    <div>
      <nav className="flxmail-subnav">
        {sections.map(([id, label]) => (
          <button key={id} type="button" onClick={() => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })}>
            {label}
          </button>
        ))}
      </nav>
      <section className="flxmail-card" id="sender">
        <h2>{t('Sender')}</h2>
        <p className="flxmail-muted">{t('Inbox name and addresses replace WordPress. Replies go from the info address; passwords and notices go from no-reply.')}</p>
        <div className="flxmail-grid2">
          <Text label={t('Sender name')} value={draft.fromName ?? ''} onChange={(v) => setText('fromName', v)} />
          <Text label={t('Sender address')} type="email" value={draft.fromEmail ?? ''} onChange={(v) => setText('fromEmail', v)} />
          <Text label={t('No-reply name')} value={draft.noreplyName ?? ''} onChange={(v) => setText('noreplyName', v)} />
          <Text label={t('No-reply address')} type="email" value={draft.noreplyEmail ?? ''} onChange={(v) => setText('noreplyEmail', v)} />
        </div>
        <Text label={t('Reply-to address (optional)')} type="email" value={draft.replyTo ?? ''} help={t('If empty, replies go to the sender address.')} onChange={(v) => setText('replyTo', v)} />
        <Check label={t('Also change the sender address of other plugins to this')} on={!!draft.forceFrom} onChange={(v) => setBool('forceFrom', v)} />
      </section>

      <section className="flxmail-card" id="smtp">
        <h2>{t('Sending method')}</h2>
        {live.otherSmtpPlugin ? <p className="flxmail-callout">{t('{name} is active; it sends the emails and the SMTP settings here are not used.', { name: live.otherSmtpPlugin })}</p> : null}
        <div className="flxmail-seg">
          <button type="button" className={draft.mailer === 'SMTP' ? 'is-on' : ''} onClick={() => setDraft({ ...draft, mailer: 'SMTP' })}>
            {t('SMTP (recommended)')}
          </button>
          <button type="button" className={draft.mailer === 'PHP' ? 'is-on' : ''} onClick={() => setDraft({ ...draft, mailer: 'PHP' })}>
            {t('Server PHP mail()')}
          </button>
        </div>
        {draft.mailer === 'PHP' ? <p className="flxmail-muted">{t('PHP mail() often lands in spam.')}</p> : null}
        {draft.mailer === 'SMTP' ? (
          <div>
            <p className="flxmail-callout">
              <strong>Hostinger: </strong>
              {t('Server smtp.hostinger.com, port 465, SSL. The username is the full email address.')}{' '}
              <button type="button" className="flxmail-btn" onClick={() => setDraft({ ...draft, smtpHost: 'smtp.hostinger.com', smtpPort: 465, smtpSecure: 'SSL', smtpAuthentication: true })}>
                {t('Fill in Hostinger settings')}
              </button>
            </p>
            <div className="flxmail-grid3">
              <Text label={t('SMTP server')} value={draft.smtpHost ?? ''} onChange={(v) => setText('smtpHost', v)} />
              <Text label={t('Port')} type="number" value={String(draft.smtpPort ?? 0)} onChange={(v) => setNum('smtpPort', v)} />
              <Secure label={t('Encryption')} value={draft.smtpSecure ?? 'SSL'} options={[['SSL', 'SSL (465)'], ['TLS', 'TLS (587)'], ['NONE', t('None')]]} onChange={(v) => setDraft({ ...draft, smtpSecure: v })} />
            </div>
            <Check label={t('Authentication')} on={!!draft.smtpAuthentication} onChange={(v) => setBool('smtpAuthentication', v)} />
            <div className="flxmail-grid2">
              <Text label={t('Username (info@)')} value={draft.smtpUser ?? ''} onChange={(v) => setText('smtpUser', v)} />
              <Secret label={t('Password')} secret={live.smtpPassword} value={secrets.smtpPassword} onChange={(v) => setSecrets({ ...secrets, smtpPassword: v })} t={t} />
              <Text label={t('Separate user for no-reply (optional)')} value={draft.smtpNoreplyUser ?? ''} help={t('If empty, no-reply mail uses the account above.')} onChange={(v) => setText('smtpNoreplyUser', v)} />
              <Secret label={t('No-reply password')} secret={live.smtpNoreplyPassword} value={secrets.smtpNoreplyPassword} onChange={(v) => setSecrets({ ...secrets, smtpNoreplyPassword: v })} t={t} />
            </div>
            {live.smtpAccountNotes.map((n) => (
              <p key={n.mailbox + n.text} className={'flxmail-callout is-' + n.level}>
                {n.text}
              </p>
            ))}
          </div>
        ) : null}
        <Check label={t('Set Return-Path to the sender address')} on={!!draft.setSender} onChange={(v) => setBool('setSender', v)} />
      </section>

      <section className="flxmail-card" id="mailbox">
        <h2>{t('Mailbox')}</h2>
        <p className="flxmail-muted">{t('Mail for the info address shows on the Mailbox screen. The site fetches a few messages over IMAP.')}</p>
        <Check label={t('Enable mailbox')} on={!!draft.mailboxEnabled} onChange={(v) => setBool('mailboxEnabled', v)} />
        <div className="flxmail-grid3">
          <Text label={t('IMAP server')} value={draft.imapHost ?? ''} onChange={(v) => setText('imapHost', v)} />
          <Text label={t('Port')} type="number" value={String(draft.imapPort ?? 0)} onChange={(v) => setNum('imapPort', v)} />
          <Secure label={t('Encryption')} value={draft.imapSecure ?? 'SSL'} options={[['SSL', 'SSL (993)'], ['TLS', 'STARTTLS (143)'], ['NONE', t('None')]]} onChange={(v) => setDraft({ ...draft, imapSecure: v })} />
        </div>
        <div className="flxmail-grid2">
          <Text label={t('Username')} value={draft.imapUser ?? ''} help={t('If empty, the SMTP user is used.')} onChange={(v) => setText('imapUser', v)} />
          <Secret label={t('Password')} secret={live.imapPassword} value={secrets.imapPassword} onChange={(v) => setSecrets({ ...secrets, imapPassword: v })} t={t} />
          <Text label={t('How many days of email to fetch on first setup')} type="number" value={String(draft.mailboxDays ?? 0)} onChange={(v) => setNum('mailboxDays', v)} />
          <Text label={t('How many days to keep on the site')} type="number" value={String(draft.mailboxKeepDays ?? 0)} help={t('Mail on the server is not deleted.')} onChange={(v) => setNum('mailboxKeepDays', v)} />
        </div>
        <label className="flxmail-field">
          <span>{t('Signature (optional)')}</span>
          <textarea rows={3} value={draft.mailboxSignature ?? ''} onChange={(e) => setText('mailboxSignature', e.target.value)} />
        </label>
        <div className="flxmail-actions">
          <button
            type="button"
            className="flxmail-btn"
            disabled={imap.isPending}
            onClick={() =>
              void imap
                .mutateAsync({ input: {} })
                .then((r) => {
                  const err = payloadError(r.flxMailImapTest?.errors);
                  toast(err || r.flxMailImapTest?.message || (r.flxMailImapTest?.ok ? t('Connected.') : t('Could not connect.')), err || !r.flxMailImapTest?.ok ? 'error' : 'ok');
                })
                .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
            }
          >
            {t('Test connection')}
          </button>
          <span className="flxmail-muted">{t('Save first, then test.')}</span>
        </div>
      </section>

      <div className="flxmail-actions">
        <button type="button" className="flxmail-btn is-primary" disabled={save.isPending} onClick={() => void onSave()}>
          {t('Save settings')}
        </button>
      </div>

      <section className="flxmail-card" id="test">
        <h2>{t('Test')}</h2>
        <p className="flxmail-muted">{t('Save the settings first, then test.')}</p>
        <div className="flxmail-inline">
          <input type="email" aria-label={t('Test address')} value={testTo} placeholder={live.fromEmail} onChange={(e) => setTestTo(e.target.value)} />
          <select aria-label={t('Sender')} value={testSender} onChange={(e) => setTestSender(e.target.value === 'NOREPLY' ? 'NOREPLY' : 'INFO')}>
            <option value="INFO">{draft.fromEmail}</option>
            <option value="NOREPLY">{draft.noreplyEmail}</option>
          </select>
          <button
            type="button"
            className="flxmail-btn"
            disabled={test.isPending}
            onClick={() =>
              void test
                .mutateAsync({ input: { to: testTo || undefined, sender: testSender } })
                .then((r) => {
                  const err = payloadError(r.flxMailTestSend?.errors);
                  const row = r.flxMailTestSend;
                  toast(err || (row?.ok ? t('Sent to {to}', { to: row.to ?? '' }) : row?.error || t('Could not send.')), err || !row?.ok ? 'error' : 'ok');
                  if (!err && row && row.to != null) {
                    setLive((prev) => (prev ? { ...prev, lastTest: { ok: !!row.ok, to: row.to ?? '', error: row.error ?? '', at: row.at ?? null } } : prev));
                  }
                  void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailSettings) });
                })
                .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
            }
          >
            {t('Send test email')}
          </button>
        </div>
        {live.lastTest ? (
          <p className="flxmail-muted">
            {t('Last test: {when} · {to}', { when: live.lastTest.at?.label ?? '', to: live.lastTest.to })} · {live.lastTest.ok ? t('sent') : live.lastTest.error}
          </p>
        ) : null}
        <h3 id="dns">{t('Domain records (SPF / DMARC)')}</h3>
        <button
          type="button"
          className="flxmail-btn"
          disabled={dns.isPending}
          onClick={() =>
            void dns
              .mutateAsync({ input: {} })
              .then((r) => {
                const err = payloadError(r.flxMailDnsCheck?.errors);
                if (err) {
                  toast(err, 'error');
                  return;
                }
                toast(t('DNS checked.'));
                if (r.flxMailDnsCheck?.dns) {
                  setLive((prev) => (prev ? { ...prev, dns: r.flxMailDnsCheck?.dns ?? prev.dns } : prev));
                }
                void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailSettings) });
              })
              .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
          }
        >
          {t('Check DNS')}
        </button>
        {live.dns ? (
          <dl className="flxmail-dl">
            <dt>SPF</dt>
            <dd>{live.dns.spf || t('none')}</dd>
            <dt>DMARC</dt>
            <dd>{live.dns.dmarc || t('none')}</dd>
            <dt>MX</dt>
            <dd>{live.dns.mx.join(', ') || t('none')}</dd>
            {live.dns.error ? (
              <>
                <dt>{t('Error')}</dt>
                <dd>{live.dns.error}</dd>
              </>
            ) : null}
          </dl>
        ) : null}
      </section>

      <section className="flxmail-card" id="look">
        <h2>{t('Appearance')}</h2>
        <div className="flxmail-split">
          <div>
            <Logo label={t('Logo')} url={draft.logo ?? ''} file={logos.logo} onFile={(f) => setLogos({ ...logos, logo: f })} onClear={() => { setText('logo', ''); setLogos({ ...logos, logo: null }); }} t={t} />
            <Logo label={t('Dark background logo')} url={draft.logoWhite ?? ''} file={logos.white} onFile={(f) => setLogos({ ...logos, white: f })} onClear={() => { setText('logoWhite', ''); setLogos({ ...logos, white: null }); }} t={t} />
            <Text label={t('Logo width (px)')} type="number" value={String(draft.logoWidth ?? 0)} onChange={(v) => setNum('logoWidth', v)} />
            <div className="flxmail-grid4">
              <Color label={t('Primary color')} value={draft.colorPrimary ?? '#000000'} onChange={(v) => setText('colorPrimary', v)} />
              <Color label={t('Dark color')} value={draft.colorDark ?? '#000000'} onChange={(v) => setText('colorDark', v)} />
              <Color label={t('Background')} value={draft.colorBg ?? '#ffffff'} onChange={(v) => setText('colorBg', v)} />
              <Color label={t('Heading color')} value={draft.colorText ?? '#000000'} onChange={(v) => setText('colorText', v)} />
            </div>
            <h3>{t('Footer (organization)')}</h3>
            <Text label={t('Organization name')} value={draft.orgName ?? ''} onChange={(v) => setText('orgName', v)} />
            <Text label={t('Tagline')} value={draft.tagline ?? ''} onChange={(v) => setText('tagline', v)} />
            <Text label={t('Address')} value={draft.address ?? ''} onChange={(v) => setText('address', v)} />
            <div className="flxmail-grid2">
              <Text label={t('Phone')} value={draft.phone ?? ''} onChange={(v) => setText('phone', v)} />
              <Text label={t('Contact email')} type="email" value={draft.contactEmail ?? ''} onChange={(v) => setText('contactEmail', v)} />
            </div>
            <Text label={t('Website')} type="url" value={draft.website ?? ''} onChange={(v) => setText('website', v)} />
            <div className="flxmail-grid2">
              {live.socialNetworks.map((n) => (
                <Text key={n.key} label={n.label} type="url" value={socialUrl(n.key)} onChange={(v) => setSocial(n.key, v)} />
              ))}
            </div>
            <label className="flxmail-field">
              <span>{t('Footnote (optional)')}</span>
              <textarea rows={2} value={draft.footerNote ?? ''} onChange={(e) => setText('footerNote', e.target.value)} />
            </label>
            <fieldset className="flxmail-field">
              <legend>{t('Footer illustration')}</legend>
              <div className="flxmail-arts">
                {live.footerArts.map((a) => (
                  <label key={a.key} className="flxmail-art">
                    <input type="radio" name="footerArt" checked={draft.footerArt === a.key} onChange={() => setText('footerArt', a.key)} />
                    <span>{a.label}</span>
                  </label>
                ))}
              </div>
            </fieldset>
            <h3>{t('Images')}</h3>
            <Check label={t('Make images work in every email app')} on={!!draft.emailImagesFix} onChange={(v) => setBool('emailImagesFix', v)} />
            <Check label={t('Embed the site images in the email')} on={!!draft.embedImages} onChange={(v) => setBool('embedImages', v)} />
            <h3>{t('Emails from other plugins')}</h3>
            <Check label={t('Wrap plain-text emails in the branded template')} on={!!draft.wrapPlain} onChange={(v) => setBool('wrapPlain', v)} />
            <Check label={t('Also wrap simple HTML emails')} on={!!draft.wrapHtml} onChange={(v) => setBool('wrapHtml', v)} />
          </div>
          <div className="flxmail-preview">
            <div className="flxmail-preview-bar">
              <strong>{t('Preview')}</strong>
              <div className="flxmail-seg">
                {(['desktop', 'phone', 'dark'] as const).map((d) => (
                  <button key={d} type="button" className={device === d ? 'is-on' : ''} onClick={() => setDevice(d)}>
                    {t(d === 'desktop' ? 'Desktop' : d === 'phone' ? 'Phone' : 'Dark mode')}
                  </button>
                ))}
              </div>
            </div>
            <iframe className={device === 'phone' ? 'is-phone' : ''} title={t('Email preview')} sandbox="allow-same-origin" srcDoc={html} />
          </div>
        </div>
      </section>

      <section className="flxmail-card" id="google">
        <h2>{t('Google sign-in')}</h2>
        <p className="flxmail-muted">{t('People who sign up with Google do not get the set-password email.')}</p>
        <p>{live.activeSocialPlugins.length ? t('Detected plugin: {name}', { name: live.activeSocialPlugins.join(', ') }) : t('No known Google sign-in plugin was detected.')}</p>
        <Check label={t('Do not send the WordPress login-details email')} on={!!draft.socialBlockCore} onChange={(v) => setBool('socialBlockCore', v)} />
        <Check label={t('Do not send the WooCommerce account-created email')} on={!!draft.socialBlockWc} onChange={(v) => setBool('socialBlockWc', v)} />
        <Check label={t('Mark the email as verified')} on={!!draft.socialVerified} onChange={(v) => setBool('socialVerified', v)} />
      </section>

      <section className="flxmail-card" id="woo">
        <h2>WooCommerce</h2>
        {live.wooActive ? (
          <>
            <p className="flxmail-muted">{t('Apply the logo, colors, sender and footer to WooCommerce emails.')}</p>
            {live.wcBranded ? <p>{t('Last applied: {when}', { when: live.wcBranded.label })}</p> : null}
            <button
              type="button"
              className="flxmail-btn"
              disabled={brand.isPending}
              onClick={() =>
                void brand
                  .mutateAsync({ input: {} })
                  .then((r) => {
                    const err = payloadError(r.flxMailWcBrandApply?.errors);
                    if (err) {
                      toast(err, 'error');
                      return;
                    }
                    toast(t('Applied.'));
                    if (r.flxMailWcBrandApply?.wcBranded) {
                      setLive((prev) => (prev ? { ...prev, wcBranded: r.flxMailWcBrandApply?.wcBranded ?? prev.wcBranded } : prev));
                    }
                    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailSettings) });
                  })
                  .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
              }
            >
              {t('Apply appearance to WooCommerce')}
            </button>
          </>
        ) : (
          <p className="flxmail-muted">{t('WooCommerce is not active.')}</p>
        )}
      </section>

      <section className="flxmail-card" id="log">
        <h2>{t('Log and bulk send')}</h2>
        <Check label={t('Log outgoing emails and messages')} on={!!draft.logEnabled} onChange={(v) => setBool('logEnabled', v)} />
        <Check label={t('Also store the content')} on={!!draft.logBody} onChange={(v) => setBool('logBody', v)} />
        <div className="flxmail-grid2">
          <Text label={t('Keep log for how many days')} type="number" value={String(draft.logDays ?? 0)} onChange={(v) => setNum('logDays', v)} />
          <Text label={t('Number to send per round in bulk email')} type="number" value={String(draft.bulkPerRun ?? 0)} onChange={(v) => setNum('bulkPerRun', v)} />
        </div>
        <Check label={t('Add an unsubscribe link to bulk emails')} on={!!draft.bulkUnsubscribe} onChange={(v) => setBool('bulkUnsubscribe', v)} />
        <p className="flxmail-muted">{t('Unsubscribed: {n} people', { n: live.unsubscribedCount })}</p>
      </section>

      <section className="flxmail-card" id="optout">
        <h2>{t('Opt-out (STOP)')}</h2>
        <p className="flxmail-muted">{t('A reply that is only the opt-out word stops promotional messages on that channel.')}</p>
        <Check label={t('Use opt-out')} on={!!draft.optoutEnabled} onChange={(v) => setBool('optoutEnabled', v)} />
        <Check label={t('Add the opt-out line to promotional WhatsApp messages')} on={!!draft.optoutFooterWa} onChange={(v) => setBool('optoutFooterWa', v)} />
        <Check label={t('Add the opt-out line to promotional SMS')} on={!!draft.optoutFooterSms} onChange={(v) => setBool('optoutFooterSms', v)} />
        <Text label={t('Opt-out line')} value={draft.optoutText ?? ''} onChange={(v) => setText('optoutText', v)} />
        <div className="flxmail-grid2">
          <Text label={t('Opt-out words (comma separated)')} value={draft.optoutKeywords ?? ''} onChange={(v) => setText('optoutKeywords', v)} />
          <Text label={t('Start words (comma separated)')} value={draft.optinKeywords ?? ''} onChange={(v) => setText('optinKeywords', v)} />
        </div>
        <Text label={t('Reply after opting out')} value={draft.optoutReply ?? ''} onChange={(v) => setText('optoutReply', v)} />
        <Text label={t('Reply after starting again')} value={draft.optinReply ?? ''} onChange={(v) => setText('optinReply', v)} />
        <p>
          <strong>{t('Opted out: {wa} on WhatsApp, {sms} on SMS', { wa: live.optoutWhatsappCount, sms: live.optoutSmsCount })}</strong>
        </p>
        <p>
          <button type="button" className="flxmail-link" onClick={() => host.openUrl((host.adminUrl ?? '') + 'admin.php?page=flxi-kara-liste&type=optout')}>
            {t('See the list on the Blocklist page')}
          </button>
        </p>
      </section>

      <section className="flxmail-card" id="limits">
        <h2>{t('Mailbox limits')}</h2>
        {live.quota.map((box) => (
          <p key={box.mailbox}>
            {box.mailbox} · {t('Last 24 hours: {used} / {limit}', { used: box.used, limit: box.limit || '∞' })}
            {box.next ? ' · ' + box.next.label : ''}
          </p>
        ))}
        {live.quotaFullDays > 0 ? <p className="flxmail-callout">{t('The daily limit was hit on {n} days in the last 30 days.', { n: live.quotaFullDays })}</p> : null}
        <div className="flxmail-grid2">
          <Text label={t('Daily sending (per 24 hours, per mailbox)')} type="number" value={String(draft.limitDaily ?? 0)} onChange={(v) => setNum('limitDaily', v)} />
          <Text label={t('Reserve for individual emails')} type="number" value={String(draft.limitReserve ?? 0)} onChange={(v) => setNum('limitReserve', v)} />
          <Text label={t('Recipients per message')} type="number" value={String(draft.limitRecipients ?? 0)} onChange={(v) => setNum('limitRecipients', v)} />
          <Text label={t('Outgoing email size (MB)')} type="number" value={String(draft.limitSizeMb ?? 0)} onChange={(v) => setNum('limitSizeMb', v)} />
          <Text label={t('Attachment size (MB)')} type="number" value={String(draft.limitAttachMb ?? 0)} onChange={(v) => setNum('limitAttachMb', v)} />
          <Text label={t('Storage per mailbox (GB)')} type="number" value={String(draft.limitStorageGb ?? 0)} onChange={(v) => setNum('limitStorageGb', v)} />
          <Text label={t('Emails per mailbox')} type="number" value={String(draft.limitMailboxEmails ?? 0)} onChange={(v) => setNum('limitMailboxEmails', v)} />
          <Text label={t('Aliases per mailbox')} type="number" value={String(draft.limitAliases ?? 0)} onChange={(v) => setNum('limitAliases', v)} />
          <Text label={t('Mailbox forwarders')} type="number" value={String(draft.limitForwarders ?? 0)} onChange={(v) => setNum('limitForwarders', v)} />
        </div>
      </section>
      <div className="flxmail-actions">
        <button type="button" className="flxmail-btn is-primary" disabled={save.isPending} onClick={() => void onSave()}>
          {t('Save settings')}
        </button>
      </div>
    </div>
  );
}

function Text({ label, value, onChange, type = 'text', help }: { label: string; value: string; onChange: (v: string) => void; type?: string; help?: string }) {
  return (
    <label className="flxmail-field">
      <span>{label}</span>
      <input type={type} value={value} onChange={(e) => onChange(e.target.value)} />
      {help ? <small>{help}</small> : null}
    </label>
  );
}

function Check({ label, on, onChange }: { label: string; on: boolean; onChange: (v: boolean) => void }) {
  return (
    <label className="flxmail-check">
      <input type="checkbox" checked={on} onChange={(e) => onChange(e.target.checked)} />
      <span>{label}</span>
    </label>
  );
}

function Secure({ label, value, options, onChange }: { label: string; value: 'SSL' | 'TLS' | 'NONE'; options: [string, string][]; onChange: (v: 'SSL' | 'TLS' | 'NONE') => void }) {
  return (
    <label className="flxmail-field">
      <span>{label}</span>
      <select value={value} onChange={(e) => onChange(e.target.value === 'TLS' ? 'TLS' : e.target.value === 'NONE' ? 'NONE' : 'SSL')}>
        {options.map(([v, l]) => (
          <option key={v} value={v}>
            {l}
          </option>
        ))}
      </select>
    </label>
  );
}

function Secret({ label, secret, value, onChange, t }: { label: string; secret: { set: boolean; mask: string; fromConfig: boolean }; value: string; onChange: (v: string) => void; t: (s: string) => string }) {
  const clearing = value === '__clear__';
  return (
    <label className="flxmail-field">
      <span>{label}</span>
      <input
        type="password"
        value={clearing ? '' : value}
        autoComplete="new-password"
        disabled={secret.fromConfig || clearing}
        placeholder={clearing ? t('Will be removed on save') : secret.fromConfig ? t('From wp-config.php') : secret.set ? secret.mask : ''}
        onChange={(e) => onChange(e.target.value)}
      />
      <small>
        {clearing ? (
          <>
            {t('Will be removed on save.')}{' '}
            <button type="button" className="flxmail-link" onClick={() => onChange('')}>
              {t('Cancel')}
            </button>
          </>
        ) : (
          <>
            {t('Empty keeps the stored password.')}{' '}
            {secret.set && !secret.fromConfig ? (
              <button type="button" className="flxmail-link" onClick={() => onChange('__clear__')}>
                {t('Remove')}
              </button>
            ) : null}
          </>
        )}
      </small>
    </label>
  );
}

function Color({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  const hex = /^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000';
  return (
    <label className="flxmail-field">
      <span>{label}</span>
      <input type="color" value={hex} onChange={(e) => onChange(e.target.value)} />
    </label>
  );
}

function httpLogo(url: string): string {
  try {
    const u = new URL(url);
    return u.protocol === 'http:' || u.protocol === 'https:' ? u.href : '';
  } catch {
    return '';
  }
}

function Logo({ label, url, file, onFile, onClear, t }: { label: string; url: string; file: File | null; onFile: (f: File) => void; onClear: () => void; t: (s: string) => string }) {
  const blobUrl = useMemo(() => (file ? URL.createObjectURL(file) : ''), [file]);
  useEffect(() => () => void (blobUrl && URL.revokeObjectURL(blobUrl)), [blobUrl]);
  const src = blobUrl ? encodeURI(blobUrl) : httpLogo(url);
  return (
    <div className="flxmail-field">
      <span>{label}</span>
      {src ? <img src={src} alt="" className="flxmail-logo" /> : null}
      <div className="flxmail-inline">
        <label className="flxmail-btn">
          {t('Change')}
          <input
            type="file"
            accept="image/png,image/jpeg"
            hidden
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) onFile(f);
              e.target.value = '';
            }}
          />
        </label>
        <button type="button" className="flxmail-link" onClick={onClear}>
          {t('Default')}
        </button>
      </div>
    </div>
  );
}
