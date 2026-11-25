import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxMailBrandInput, FlxMailSettingsInput, FlxMailSettingsQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxMailDnsCheck, FlxMailImapTest, FlxMailPreview, FlxMailSettings, FlxMailSettingsUpdate, FlxMailTestSend, FlxMailWcBrandApply } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useMail } from './common';
import { Preview, type Device } from './Preview';

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
    colorAccent: s.colorAccent,
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

const EMPTY_SECRETS: Record<SecretKey, string> = {
  smtpPassword: '',
  smtpNoreplyPassword: '',
  imapPassword: '',
};

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
  const [device, setDevice] = useState<Device>('desktop');
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
    return [
      draft.logo,
      draft.logoWhite,
      draft.logoWidth,
      draft.colorPrimary,
      draft.colorDark,
      draft.colorBg,
      draft.colorText,
      draft.colorAccent,
      draft.orgName,
      draft.tagline,
      draft.address,
      draft.phone,
      draft.contactEmail,
      draft.website,
      draft.footerNote,
      draft.footerArt,
      draft.fromName,
      JSON.stringify(draft.social),
    ].join('|');
  }, [draft]);

  useEffect(() => {
    if (!draft) return;
    const timer = window.setTimeout(
      () => {
        void preview
          .mutateAsync({
            input: {
              key: 'brand',
              brand: brandInput(draft),
            },
          })
          .then((r) => {
            const body = r.flxMailPreview?.preview?.html ?? '';
            setHtml(body);
          })
          .catch(() => undefined);
      },
      html ? 450 : 0,
    );
    return () => window.clearTimeout(timer);
  }, [appearance]); // eslint-disable-line react-hooks/exhaustive-deps

  if (q.isLoading || !draft || !live) return <Loading />;
  if (q.isError) return <p className="flxi-err">{errorText(q.error, t('Something went wrong.'))}</p>;

  const setText = (key: keyof FlxMailSettingsInput, value: string) => setDraft({ ...draft, [key]: value } as FlxMailSettingsInput);
  const setBool = (key: keyof FlxMailSettingsInput, value: boolean) => setDraft({ ...draft, [key]: value } as FlxMailSettingsInput);
  const setNum = (key: keyof FlxMailSettingsInput, value: string) =>
    setDraft({
      ...draft,
      [key]: value === '' ? 0 : Number(value),
    } as FlxMailSettingsInput);
  const socialUrl = (network: string) => draft.social?.find((x) => x.network === network)?.url ?? '';
  const setSocial = (network: string, url: string) =>
    setDraft({
      ...draft,
      social: (draft.social ?? []).map((x) => (x.network === network ? { ...x, url } : x)),
    });

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
      void qc.invalidateQueries({
        queryKey: flxQueryKey(scope, FlxMailSettings),
      });
      toast(t('Saved'));
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  const upload = async (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    const up = await host.client<{ uploadId: string }>('flx/v1/upload', {
      method: 'POST',
      query: { context: 'flx-mail/logo' },
      formData: fd,
    });
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
      <nav className="flxi-subnav">
        {sections.map(([id, label]) => (
          <a key={id} href={'#' + id} onClick={(e) => (e.preventDefault(), document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }))}>
            {label}
          </a>
        ))}
      </nav>
      <section className="flxi-card" id="sender">
        <h2 className="flxi-card__title">{t('Sender')}</h2>
        <p className="flxi-muted">
          {t(
            'In the inbox, your organization\'s name and address appear instead of "WordPress <wordpress@…>". Emails that expect a reply are sent from the bilgi@ address; emails that don\'t, such as passwords and notifications, are sent from the no-reply@ address. You can choose which one to use for each email separately.',
          )}
        </p>
        <div className="flxi-grid2">
          <Text label={t('Sender name')} value={draft.fromName ?? ''} onChange={(v) => setText('fromName', v)} />
          <Text label={t('Sender address')} type="email" value={draft.fromEmail ?? ''} onChange={(v) => setText('fromEmail', v)} />
          <Text label={t('No-reply name')} value={draft.noreplyName ?? ''} onChange={(v) => setText('noreplyName', v)} />
          <Text label={t('No-reply address')} type="email" value={draft.noreplyEmail ?? ''} onChange={(v) => setText('noreplyEmail', v)} />
        </div>
        <Text label={t('Reply-to address (optional)')} type="email" value={draft.replyTo ?? ''} help={t('If empty, replies go to the sender address.')} onChange={(v) => setText('replyTo', v)} />
        <Check
          label={t('Also change the sender address of other plugins to this (recommended; for SPF/DKIM, all emails should be sent from the same domain)')}
          on={!!draft.forceFrom}
          onChange={(v) => setBool('forceFrom', v)}
        />
      </section>

      <section className="flxi-card" id="smtp">
        <h2 className="flxi-card__title">{t('Sending method')}</h2>
        {live.otherSmtpPlugin ? (
          <div className="flxi-callout flxi-callout--info">
            {t('{name} is active; it sends the emails and the SMTP settings here are not used. Do not use both at the same time: turn one off.', { name: live.otherSmtpPlugin })}
          </div>
        ) : null}
        <div className="flxi-seg flxi-seg--wide">
          <label className={draft.mailer === 'SMTP' ? 'is-active' : ''}>
            <input type="radio" name="flxi-mailer" checked={draft.mailer === 'SMTP'} onChange={() => setDraft({ ...draft, mailer: 'SMTP' })} /> {t('SMTP (recommended)')}
          </label>
          <label className={draft.mailer === 'PHP' ? 'is-active' : ''}>
            <input type="radio" name="flxi-mailer" checked={draft.mailer === 'PHP'} onChange={() => setDraft({ ...draft, mailer: 'PHP' })} /> {t('Server PHP mail()')}
          </label>
        </div>
        {draft.mailer === 'PHP' ? <p className="flxi-muted">{t('With emails sent via PHP mail(), Gmail shows "via srv….main-hosting.eu" and the risk of landing in spam is high.')}</p> : null}
        {draft.mailer === 'SMTP' ? (
          <div>
            <div className="flxi-callout">
              <strong>Hostinger:</strong>{' '}
              {t('In hPanel › Emails › Email accounts, create the bilgi@ account (a separate account or an alias for no-reply@). Server smtp.hostinger.com, port 465, SSL; the username is the full email address.')}{' '}
              <button
                type="button"
                className="button button-small"
                onClick={() =>
                  setDraft({
                    ...draft,
                    smtpHost: 'smtp.hostinger.com',
                    smtpPort: 465,
                    smtpSecure: 'SSL',
                    smtpAuthentication: true,
                  })
                }
              >
                {t('Fill in Hostinger settings')}
              </button>
            </div>
            <div className="flxi-grid3">
              <Text label={t('SMTP server')} placeholder="smtp.hostinger.com" value={draft.smtpHost ?? ''} onChange={(v) => setText('smtpHost', v)} />
              <Text label={t('Port')} type="number" value={String(draft.smtpPort ?? 0)} onChange={(v) => setNum('smtpPort', v)} />
              <Secure
                label={t('Encryption')}
                value={draft.smtpSecure ?? 'SSL'}
                options={[
                  ['SSL', 'SSL (465)'],
                  ['TLS', 'TLS (587)'],
                  ['NONE', t('None')],
                ]}
                onChange={(v) => setDraft({ ...draft, smtpSecure: v })}
              />
            </div>
            <Check label={t('Authentication')} on={!!draft.smtpAuthentication} onChange={(v) => setBool('smtpAuthentication', v)} />
            <div className="flxi-grid2">
              <Text label={t('Username (bilgi@)')} placeholder={draft.fromEmail ?? ''} value={draft.smtpUser ?? ''} onChange={(v) => setText('smtpUser', v)} />
              <Secret label={t('Password')} secret={live.smtpPassword} value={secrets.smtpPassword} onChange={(v) => setSecrets({ ...secrets, smtpPassword: v })} t={t} />
              <Text
                label={t('Separate user for no-reply (optional)')}
                placeholder={draft.noreplyEmail ?? ''}
                value={draft.smtpNoreplyUser ?? ''}
                help={t('If no-reply@ is a separate account. If empty, no-reply emails are also sent with the account above (no-reply@ must be an alias of the bilgi@ account).')}
                onChange={(v) => setText('smtpNoreplyUser', v)}
              />
              <Secret label={t('No-reply password')} quiet secret={live.smtpNoreplyPassword} value={secrets.smtpNoreplyPassword} onChange={(v) => setSecrets({ ...secrets, smtpNoreplyPassword: v })} t={t} />
            </div>
            {live.smtpAccountNotes.map((n) => (
              <div key={n.mailbox + n.text} className={'flxi-callout flxi-callout--' + (n.level === 'warning' ? 'warn' : n.level)}>
                {n.text}
              </div>
            ))}
          </div>
        ) : null}
        <Check label={t('Set Return-Path to the sender address (recommended for SPF alignment)')} on={!!draft.setSender} onChange={(v) => setBool('setSender', v)} />
      </section>

      <section className="flxi-card" id="mailbox">
        <h2 className="flxi-card__title">{t('Mailbox')}</h2>
        <p className="flxi-muted">
          {t(
            'Email arriving at bilgi@ and email sent from webmail appear on the Mailbox screen in the left menu, where you can reply. The site connects over IMAP and fetches a few emails every few minutes (light on shared hosting). Attachments are not stored on the site; they are read from the server when opened.',
          )}
        </p>
        <Check label={t('Enable mailbox')} on={!!draft.mailboxEnabled} onChange={(v) => setBool('mailboxEnabled', v)} />
        <div className="flxi-grid3">
          <Text label={t('IMAP server')} placeholder="imap.hostinger.com" value={draft.imapHost ?? ''} onChange={(v) => setText('imapHost', v)} />
          <Text label={t('Port')} type="number" value={String(draft.imapPort ?? 0)} onChange={(v) => setNum('imapPort', v)} />
          <Secure
            label={t('Encryption')}
            value={draft.imapSecure ?? 'SSL'}
            options={[
              ['SSL', 'SSL (993)'],
              ['TLS', 'STARTTLS (143)'],
              ['NONE', t('None')],
            ]}
            onChange={(v) => setDraft({ ...draft, imapSecure: v })}
          />
        </div>
        <div className="flxi-grid2">
          <Text label={t('Username')} placeholder={draft.smtpUser || draft.fromEmail || ''} value={draft.imapUser ?? ''} help={t('If empty, the SMTP user (bilgi@) is used.')} onChange={(v) => setText('imapUser', v)} />
          <Secret label={t('Password')} empty={t('If empty, the SMTP password is used')} secret={live.imapPassword} value={secrets.imapPassword} onChange={(v) => setSecrets({ ...secrets, imapPassword: v })} t={t} />
          <Text label={t('How many days of email to fetch on first setup')} type="number" value={String(draft.mailboxDays ?? 0)} onChange={(v) => setNum('mailboxDays', v)} />
          <Text
            label={t('How many days to keep on the site')}
            type="number"
            value={String(draft.mailboxKeepDays ?? 0)}
            help={t('Emails on the server are not deleted; only the copy on the site.')}
            onChange={(v) => setNum('mailboxKeepDays', v)}
          />
        </div>
        <label className="flxi-field">
          <span>{t('Signature (optional)')}</span>
          <textarea rows={3} placeholder="{ad_soyad}" value={draft.mailboxSignature ?? ''} onChange={(e) => setText('mailboxSignature', e.target.value)} />
          <small>{t("Added to the end of emails written from the mailbox; the organization info with logo comes below it. {ad_soyad} becomes the writer's name. If empty, nothing is added.")}</small>
        </label>
        <div className="flxi-inline">
          <button
            type="button"
            className="button"
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
        </div>
        <p className="flxi-muted">{t('Save first, then test.')}</p>
      </section>

      <div className="flxi-sticky">
        <button type="button" className="button button-primary button-large" disabled={save.isPending} onClick={() => void onSave()}>
          {t('Save settings')}
        </button>
      </div>

      <section className="flxi-card" id="test">
        <h2 className="flxi-card__title">{t('Test')}</h2>
        <p className="flxi-muted">{t('Save the settings first, then test.')}</p>
        <div className="flxi-inline">
          <input type="email" aria-label={t('Test address')} value={testTo} placeholder={live.fromEmail} onChange={(e) => setTestTo(e.target.value)} />
          <select aria-label={t('Sender')} value={testSender} onChange={(e) => setTestSender(e.target.value === 'NOREPLY' ? 'NOREPLY' : 'INFO')}>
            <option value="INFO">{draft.fromEmail}</option>
            <option value="NOREPLY">{draft.noreplyEmail}</option>
          </select>
          <button
            type="button"
            className="button"
            disabled={test.isPending}
            onClick={() =>
              void test
                .mutateAsync({
                  input: { to: testTo || undefined, sender: testSender },
                })
                .then((r) => {
                  const err = payloadError(r.flxMailTestSend?.errors);
                  const row = r.flxMailTestSend;
                  toast(err || (row?.ok ? t('Sent to {to}', { to: row.to ?? '' }) : row?.error || t('Could not send.')), err || !row?.ok ? 'error' : 'ok');
                  if (!err && row && row.to != null) {
                    setLive((prev) =>
                      prev
                        ? {
                            ...prev,
                            lastTest: {
                              ok: !!row.ok,
                              to: row.to ?? '',
                              error: row.error ?? '',
                              at: row.at ?? null,
                            },
                          }
                        : prev,
                    );
                  }
                  void qc.invalidateQueries({
                    queryKey: flxQueryKey(scope, FlxMailSettings),
                  });
                })
                .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
            }
          >
            {t('Send test email')}
          </button>
        </div>
        {live.lastTest ? (
          <p className="flxi-muted">
            {t('Last test: {when} · {to}', {
              when: live.lastTest.at?.short ?? '',
              to: live.lastTest.to,
            })}{' '}
            · {live.lastTest.ok ? <span className="flxi-ok">{t('sent')}</span> : <span className="flxi-err">{live.lastTest.error}</span>}
          </p>
        ) : null}
        <h3 id="dns">{t('Domain records (SPF / DMARC)')}</h3>
        <p className="flxi-muted">
          {t("To keep emails out of spam, the domain's DNS must have SPF, DKIM and DMARC records. When you create an email account on Hostinger, SPF and DKIM are usually added automatically.")}
        </p>
        <div className="flxi-inline">
          <button
            type="button"
            className="button"
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
                  void qc.invalidateQueries({
                    queryKey: flxQueryKey(scope, FlxMailSettings),
                  });
                })
                .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
            }
          >
            {t('Check DNS')}
          </button>
        </div>
        {live.dns ? (
          <dl className="flxi-dl">
            <dt>SPF</dt>
            <dd>{live.dns.spf ? <code>{live.dns.spf}</code> : <span className="flxi-err">{t('none')}</span>}</dd>
            <dt>DMARC</dt>
            <dd>{live.dns.dmarc ? <code>{live.dns.dmarc}</code> : <span className="flxi-muted">{t('none')}</span>}</dd>
            <dt>MX</dt>
            <dd>{live.dns.mx.join(', ')}</dd>
            {live.dns.error ? (
              <>
                <dt>{t('Error')}</dt>
                <dd>{live.dns.error}</dd>
              </>
            ) : null}
          </dl>
        ) : null}
      </section>

      <section className="flxi-card" id="look">
        <h2 className="flxi-card__title">{t('Appearance')}</h2>
        <div className="flxi-split">
          <div>
            <Logo
              label={t('Logo')}
              help={t("PNG or JPG (email clients do not display SVG). Default: the site's own logo (organisation profile, Flux SEO or the theme's Site Logo). Without a logo the site name is written.")}
              url={draft.logo ?? ''}
              file={logos.logo}
              onFile={(f) => setLogos({ ...logos, logo: f })}
              onClear={() => {
                setText('logo', '');
                setLogos({ ...logos, logo: null });
              }}
              t={t}
            />
            <Logo
              label={t('Dark background logo')}
              dark
              help={t('Shown in dark mode (Apple Mail, iOS). Default: the light logo from the organisation profile; without one the normal logo stays.')}
              url={draft.logoWhite ?? ''}
              file={logos.white}
              onFile={(f) => setLogos({ ...logos, white: f })}
              onClear={() => {
                setText('logoWhite', '');
                setLogos({ ...logos, white: null });
              }}
              t={t}
            />
            <div className="flxi-grid2">
              <Text label={t('Logo width (px)')} type="number" value={String(draft.logoWidth ?? 0)} onChange={(v) => setNum('logoWidth', v)} />
            </div>
            <p className="flxi-muted">{t('Colours come from the site theme. Change one to use your own; Reset goes back to the theme colour.')}</p>
            <div className="flxi-grid4">
              <Color label={t('Primary color')} value={draft.colorPrimary ?? ''} theme={live.themeColors.primary} onChange={(v) => setText('colorPrimary', v)} t={t} />
              <Color label={t('Dark color')} value={draft.colorDark ?? ''} theme={live.themeColors.dark} onChange={(v) => setText('colorDark', v)} t={t} />
              <Color label={t('Accent color')} value={draft.colorAccent ?? ''} theme={live.themeColors.accent} onChange={(v) => setText('colorAccent', v)} t={t} />
              <Color label={t('Background')} value={draft.colorBg ?? ''} theme={live.themeColors.bg} onChange={(v) => setText('colorBg', v)} t={t} />
              <Color label={t('Heading color')} value={draft.colorText ?? ''} theme={live.themeColors.text} onChange={(v) => setText('colorText', v)} t={t} />
            </div>
            <h3>{t('Footer (organization)')}</h3>
            <p className="flxi-muted">{t('Empty fields are taken from SEO › Organization and social settings.')}</p>
            <Text label={t('Organization name')} value={draft.orgName ?? ''} onChange={(v) => setText('orgName', v)} />
            <Text label={t('Tagline')} value={draft.tagline ?? ''} onChange={(v) => setText('tagline', v)} />
            <Text label={t('Address')} value={draft.address ?? ''} onChange={(v) => setText('address', v)} />
            <div className="flxi-grid2">
              <Text label={t('Phone')} value={draft.phone ?? ''} onChange={(v) => setText('phone', v)} />
              <Text label={t('Contact email')} type="email" value={draft.contactEmail ?? ''} onChange={(v) => setText('contactEmail', v)} />
            </div>
            <Text label={t('Website')} type="url" value={draft.website ?? ''} onChange={(v) => setText('website', v)} />
            <div className="flxi-grid2">
              {live.socialNetworks.map((n) => (
                <Text key={n.key} label={n.label} type="url" placeholder="https://" value={socialUrl(n.key)} onChange={(v) => setSocial(n.key, v)} />
              ))}
            </div>
            <label className="flxi-field">
              <span>{t('Footnote (optional)')}</span>
              <textarea rows={2} value={draft.footerNote ?? ''} onChange={(e) => setText('footerNote', e.target.value)} />
              <small>{t('E.g. the association registration number or a data protection (KVKK) note. Shown in small print at the very bottom of all emails.')}</small>
            </label>
            <div className="flxi-field" role="radiogroup" aria-label={t('Footer illustration')}>
              <span>{t('Footer illustration')}</span>
              <div className="flxi-arts">
                {live.footerArts.map((a) => (
                  <label key={a.key} className="flxi-art">
                    <input type="radio" name="footerArt" checked={draft.footerArt === a.key} onChange={() => setText('footerArt', a.key)} />
                    <span className="flxi-art__img">{a.image ? <img src={a.image} alt="" /> : null}</span>
                    <span>{a.label}</span>
                  </label>
                ))}
              </div>
              <small>{t('A tree silhouette at the very bottom of every email. A darker drawing is shown in dark mode.')}</small>
            </div>
            <h3>{t('Images')}</h3>
            <Check label={t('Make images work in every email app (WebP/AVIF → JPG/PNG, full URLs)')} on={!!draft.emailImagesFix} onChange={(v) => setBool('emailImagesFix', v)} />
            <Check label={t('Embed the site\'s images in the email (recipients see them without clicking "show images")')} on={!!draft.embedImages} onChange={(v) => setBool('embedImages', v)} />
            <h3>{t('Emails from other plugins')}</h3>
            <Check label={t('Wrap plain-text emails in the branded template')} on={!!draft.wrapPlain} onChange={(v) => setBool('wrapPlain', v)} />
            <Check label={t('Also wrap simple HTML emails (those with their own full design are left untouched)')} on={!!draft.wrapHtml} onChange={(v) => setBool('wrapHtml', v)} />
          </div>
          <div className="flxi-split__preview">
            <Preview
              head={<strong>{t('Preview')}</strong>}
              html={html}
              device={device}
              onDevice={setDevice}
              pdf={() => ({ key: 'brand', brand: brandInput(draft) })}
              note={t('Appearance changes are applied to all emails when you save.')}
            />
          </div>
        </div>
      </section>

      <section className="flxi-card" id="google">
        <h2 className="flxi-card__title">{t('Google sign-in')}</h2>
        <p className="flxi-muted">
          {t('Because Google has already verified the email address, people who sign up with Google do not receive the "set your password / login details" email, and their account is treated as verified.')}
        </p>
        <p>
          {live.activeSocialPlugins.length
            ? t('Detected plugin: {name}', {
                name: live.activeSocialPlugins.join(', '),
              })
            : t('No known Google sign-in plugin was detected; verification applies when a supported plugin records a Google login (or via the flxi_user_is_social filter).')}
        </p>
        <Check label={t('Do not send the WordPress "login details / set password" email')} on={!!draft.socialBlockCore} onChange={(v) => setBool('socialBlockCore', v)} />
        <Check label={t('Do not send the WooCommerce "your account has been created" email')} on={!!draft.socialBlockWc} onChange={(v) => setBool('socialBlockWc', v)} />
        <Check label={t('Mark the email as verified (for verification plugins)')} on={!!draft.socialVerified} onChange={(v) => setBool('socialVerified', v)} />
      </section>

      <section className="flxi-card" id="woo">
        <h2 className="flxi-card__title">WooCommerce</h2>
        {live.wooActive ? (
          <>
            <p className="flxi-muted">{t('WooCommerce emails use their own template. You can apply our logo, colors, sender name and organization footer to WooCommerce › Settings › Emails with one click.')}</p>
            {live.wcBranded ? (
              <p>
                {t('Last applied: {when}', {
                  when: live.wcBranded.short ?? live.wcBranded.label,
                })}
              </p>
            ) : null}
            <button
              type="button"
              className="button"
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
                      setLive((prev) =>
                        prev
                          ? {
                              ...prev,
                              wcBranded: r.flxMailWcBrandApply?.wcBranded ?? prev.wcBranded,
                            }
                          : prev,
                      );
                    }
                    void qc.invalidateQueries({
                      queryKey: flxQueryKey(scope, FlxMailSettings),
                    });
                  })
                  .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
              }
            >
              {t('Apply appearance to WooCommerce')}
            </button>
          </>
        ) : (
          <p className="flxi-muted">{t('WooCommerce is not active.')}</p>
        )}
      </section>

      <section className="flxi-card" id="log">
        <h2 className="flxi-card__title">{t('Log and bulk send')}</h2>
        <Check label={t('Log outgoing emails and messages')} on={!!draft.logEnabled} onChange={(v) => setBool('logEnabled', v)} />
        <Check label={t('Also store the content (to view the email in the log)')} on={!!draft.logBody} onChange={(v) => setBool('logBody', v)} />
        <div className="flxi-grid2">
          <Text label={t('Keep log for how many days')} type="number" value={String(draft.logDays ?? 0)} onChange={(v) => setNum('logDays', v)} />
          <Text
            label={t('Number to send per round in bulk email')}
            help={t('Hostinger shared hosting has an hourly sending limit; the default is 20 (one round per minute).')}
            type="number"
            value={String(draft.bulkPerRun ?? 0)}
            onChange={(v) => setNum('bulkPerRun', v)}
          />
        </div>
        <Check label={t('Add an "unsubscribe" link to bulk emails')} on={!!draft.bulkUnsubscribe} onChange={(v) => setBool('bulkUnsubscribe', v)} />
        <p className="flxi-muted">
          {t('Unsubscribed: {n} people', {
            n: live.unsubscribedCount.toLocaleString(),
          })}
        </p>
      </section>

      <section className="flxi-card" id="optout">
        <h2 className="flxi-card__title">{t('Opt-out (STOP)')}</h2>
        <p className="flxi-muted">
          {t(
            'A line at the bottom of promotional WhatsApp and SMS messages tells people how to stop them. When someone replies with only the opt-out word, promotional messages are no longer sent to them on that channel. Information messages (event details, receipts, certificates) and replies to their own messages still go out. If they reply with the start word, they get promotional messages again.',
          )}
        </p>
        <Check label={t('Use opt-out')} on={!!draft.optoutEnabled} onChange={(v) => setBool('optoutEnabled', v)} />
        <div className="flxi-grid2">
          <Check label={t('Add the opt-out line to promotional WhatsApp messages')} on={!!draft.optoutFooterWa} onChange={(v) => setBool('optoutFooterWa', v)} />
          <Check label={t('Add the opt-out line to promotional SMS (costs extra characters; Netgsm İYS rules apply separately)')} on={!!draft.optoutFooterSms} onChange={(v) => setBool('optoutFooterSms', v)} />
        </div>
        <Text label={t('Opt-out line')} help={t('Leave empty to use the default in the site language.')} placeholder={live.optoutDefaults.text} value={draft.optoutText ?? ''} onChange={(v) => setText('optoutText', v)} />
        <div className="flxi-grid2">
          <Text
            label={t('Opt-out words (comma separated)')}
            help={t('The whole message must be the word; "I want to cancel my donation" is not counted. Default: {words}', { words: live.optoutDefaults.keywords })}
            placeholder={live.optoutDefaults.keywords}
            value={draft.optoutKeywords ?? ''}
            onChange={(v) => setText('optoutKeywords', v)}
          />
          <Text
            label={t('Start words (comma separated)')}
            help={t('Default: {words}', {
              words: live.optoutDefaults.optinKeywords,
            })}
            placeholder={live.optoutDefaults.optinKeywords}
            value={draft.optinKeywords ?? ''}
            onChange={(v) => setText('optinKeywords', v)}
          />
        </div>
        <Text
          label={t('Reply after opting out')}
          help={t('Sent once on WhatsApp. Leave empty for the default; type - to send nothing.')}
          placeholder={live.optoutDefaults.reply}
          value={draft.optoutReply ?? ''}
          onChange={(v) => setText('optoutReply', v)}
        />
        <Text label={t('Reply after starting again')} placeholder={live.optoutDefaults.optinReply} value={draft.optinReply ?? ''} onChange={(v) => setText('optinReply', v)} />
        <p className="flxi-muted">
          <strong>
            {t('Opted out: {wa} on WhatsApp, {sms} on SMS', {
              wa: live.optoutWhatsappCount,
              sms: live.optoutSmsCount,
            })}
          </strong>
        </p>
        <p>
          <a
            href={(host.adminUrl ?? '') + 'admin.php?page=flxi-kara-liste&type=optout'}
            onClick={(e) => {
              if (e.metaKey || e.ctrlKey) return;
              e.preventDefault();
              host.openUrl((host.adminUrl ?? '') + 'admin.php?page=flxi-kara-liste&type=optout');
            }}
          >
            {t('See the list on the Blocklist page')}
          </a>
        </p>
      </section>

      <section className="flxi-card" id="limits">
        <h2 className="flxi-card__title">{t('Mailbox limits')}</h2>
        <p className="flxi-muted">
          {t(
            'The limits of your Hostinger email plan. The send counter uses them; bulk sends never exceed the daily limit, and the rest go out as allowance frees up. If you hit the limits often, upgrade to a higher email plan at Hostinger and update the values here.',
          )}
        </p>
        {draft.limitDaily ? (
          <div className="flxi-quota">
            {live.quota.map((box) => (
              <div key={box.mailbox} className={'flxi-quota__row' + (box.percent >= 100 ? ' is-full' : box.percent >= 80 ? ' is-high' : '')}>
                <div className="flxi-quota__head">
                  <strong>{box.mailbox}</strong>
                  <span>
                    {t('Last 24 hours: {used} / {limit}', {
                      used: box.used.toLocaleString(),
                      limit: box.limit.toLocaleString(),
                    })}
                  </span>
                </div>
                <div className="flxi-bar">
                  <span style={{ width: Math.min(100, box.percent) + '%' }} />
                </div>
                {box.left != null && box.left < box.limit && box.next ? (
                  <small>
                    {t('Next free slot: {when}', {
                      when: box.next.short ?? box.next.label,
                    })}
                  </small>
                ) : null}
              </div>
            ))}
          </div>
        ) : null}
        {live.quotaFullDays > 0 ? (
          <p className="flxi-notice-inline">
            {t('The daily limit was hit on {n} days in the last 30 days.', {
              n: live.quotaFullDays,
            })}
          </p>
        ) : null}
        <div className="flxi-grid2">
          <Text
            label={t('Daily sending (per 24 hours, per mailbox)')}
            help={t('Each To, Cc and Bcc recipient counts as one. 0 = no limit.')}
            type="number"
            value={String(draft.limitDaily ?? 0)}
            onChange={(v) => setNum('limitDaily', v)}
          />
          <Text
            label={t('Reserve for individual emails')}
            help={t('Bulk sending leaves this many sends for password reset, donation and certificate emails.')}
            type="number"
            value={String(draft.limitReserve ?? 0)}
            onChange={(v) => setNum('limitReserve', v)}
          />
          <Text label={t('Recipients per message (To, Cc, Bcc)')} type="number" value={String(draft.limitRecipients ?? 0)} onChange={(v) => setNum('limitRecipients', v)} />
          <Text label={t('Outgoing email size (MB)')} type="number" value={String(draft.limitSizeMb ?? 0)} onChange={(v) => setNum('limitSizeMb', v)} />
          <Text label={t('Attachment size (MB)')} type="number" value={String(draft.limitAttachMb ?? 0)} onChange={(v) => setNum('limitAttachMb', v)} />
          <Text label={t('Storage per mailbox (GB)')} type="number" value={String(draft.limitStorageGb ?? 0)} onChange={(v) => setNum('limitStorageGb', v)} />
          <Text label={t('Emails per mailbox')} type="number" value={String(draft.limitMailboxEmails ?? 0)} onChange={(v) => setNum('limitMailboxEmails', v)} />
          <Text label={t('Aliases per mailbox')} type="number" value={String(draft.limitAliases ?? 0)} onChange={(v) => setNum('limitAliases', v)} />
          <Text label={t('Mailbox forwarders')} type="number" value={String(draft.limitForwarders ?? 0)} onChange={(v) => setNum('limitForwarders', v)} />
        </div>
      </section>
      <div className="flxi-actions">
        <button type="button" className="button button-primary button-large" disabled={save.isPending} onClick={() => void onSave()}>
          {t('Save settings')}
        </button>
      </div>
    </div>
  );
}

function Text({ label, value, onChange, type = 'text', help, placeholder }: { label: string; value: string; onChange: (v: string) => void; type?: string; help?: string; placeholder?: string }) {
  return (
    <label className="flxi-field">
      <span>{label}</span>
      <input type={type} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
      {help ? <small>{help}</small> : null}
    </label>
  );
}

function Check({ label, on, onChange }: { label: string; on: boolean; onChange: (v: boolean) => void }) {
  return (
    <div className="flxi-field">
      <label className="flxi-switch">
        <input type="checkbox" checked={on} onChange={(e) => onChange(e.target.checked)} />
        <span className="flxi-switch__track" aria-hidden="true" />
        <span className="flxi-switch__label">{label}</span>
      </label>
    </div>
  );
}

function Secure({ label, value, options, onChange }: { label: string; value: 'SSL' | 'TLS' | 'NONE'; options: [string, string][]; onChange: (v: 'SSL' | 'TLS' | 'NONE') => void }) {
  return (
    <label className="flxi-field">
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

function Secret({
  label,
  secret,
  value,
  onChange,
  t,
  empty = '',
  quiet = false,
}: {
  empty?: string;
  quiet?: boolean;
  label: string;
  secret: { set: boolean; mask: string; fromConfig: boolean };
  value: string;
  onChange: (v: string) => void;
  t: (s: string) => string;
}) {
  const clearing = value === '__clear__';
  return (
    <label className="flxi-field">
      <span>{label}</span>
      <input
        type="password"
        value={clearing ? '' : value}
        autoComplete="new-password"
        disabled={secret.fromConfig || clearing}
        placeholder={clearing ? t('Will be removed on save') : secret.fromConfig ? t('defined in wp-config.php') : secret.set ? secret.mask + ' · ' + t('type to change') : empty}
        onChange={(e) => onChange(e.target.value)}
      />
      {clearing || !quiet || (secret.set && !secret.fromConfig) ? (
        <small>
          {clearing ? (
            <>
              {t('Will be removed on save.')}{' '}
              <button type="button" className="button-link" onClick={() => onChange('')}>
                {t('Cancel')}
              </button>
            </>
          ) : (
            <>
              {quiet ? null : t('Stored encrypted, not shown in the panel.')}{' '}
              {secret.set && !secret.fromConfig ? (
                <button type="button" className="button-link" onClick={() => onChange('__clear__')}>
                  {t('Remove')}
                </button>
              ) : null}
            </>
          )}
        </small>
      ) : null}
    </label>
  );
}

// Empty value = the theme colour (the server stores nothing when the colour equals the theme's).
function Color({ label, value, theme, onChange, t }: { label: string; value: string; theme: string; onChange: (v: string) => void; t: (s: string) => string }) {
  const own = /^#[0-9a-fA-F]{6}$/.test(value) && value.toLowerCase() !== theme.toLowerCase();
  const hex = own ? value : /^#[0-9a-fA-F]{6}$/.test(theme) ? theme : '#000000';
  return (
    <div className="flxi-field flxi-color">
      <label>
        <span>{label}</span>
        <input type="color" value={hex.toLowerCase()} onChange={(e) => onChange(e.target.value)} />
      </label>
      {own ? (
        <button type="button" className="button-link" onClick={() => onChange('')}>
          {t('Reset')}
        </button>
      ) : (
        <small className="flxi-muted">{t('From theme')}</small>
      )}
    </div>
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

function Logo({
  label,
  help,
  dark,
  url,
  file,
  onFile,
  onClear,
  t,
}: {
  label: string;
  help: string;
  dark?: boolean;
  url: string;
  file: File | null;
  onFile: (f: File) => void;
  onClear: () => void;
  t: (s: string) => string;
}) {
  const blobUrl = useMemo(() => (file ? URL.createObjectURL(file) : ''), [file]);
  useEffect(() => () => void (blobUrl && URL.revokeObjectURL(blobUrl)), [blobUrl]);
  const src = blobUrl ? encodeURI(blobUrl) : httpLogo(url);
  return (
    <div className="flxi-field">
      <span>{label}</span>
      <div className={'flxi-media' + (dark ? ' flxi-media--dark' : '')}>
        {src ? <img src={src} alt="" /> : null}
        <label className="button">
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
        <button type="button" className="button-link" onClick={onClear}>
          {t('Default')}
        </button>
      </div>
      <small>{help}</small>
    </div>
  );
}

function brandInput(draft: FlxMailSettingsInput): FlxMailBrandInput {
  return {
    logo: draft.logo,
    logoWhite: draft.logoWhite,
    logoWidth: draft.logoWidth,
    colorPrimary: draft.colorPrimary,
    colorDark: draft.colorDark,
    colorBg: draft.colorBg,
    colorText: draft.colorText,
    colorAccent: draft.colorAccent,
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
  };
}
