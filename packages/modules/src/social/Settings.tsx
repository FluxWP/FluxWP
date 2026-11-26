// Settings: general, app keys (secrets are write-only), automatic sharing, activity log.
import { useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSocialAppUpdate, FlxSocialLog, FlxSocialSettings, FlxSocialSettingsUpdate, FlxSocialTickKeyReveal } from '@fluxwp/graphql/operations';
import type { FlxSocialSettingsQuery } from '@fluxwp/graphql/types';
import { Icon, Loading } from '@fluxwp/ui';
import { Avatar, NetDot, errorText, payloadError, useSocial } from './common';

type Data = FlxSocialSettingsQuery;
type AppRow = Data['flxSocialApps'][number];
const TABS = ['general', 'apps', 'networks', 'auto', 'log'] as const;
const TAB_ICONS: Record<string, string> = { general: 'settings', apps: 'key', networks: 'admin-site', auto: 'update', log: 'list-view' };
type Tab = (typeof TABS)[number];

export function SettingsPage({ tab }: { tab?: string | undefined }) {
  const s = useSocial();
  const { t } = s;
  const cur: Tab = (TABS as readonly string[]).includes(tab ?? '') ? (tab as Tab) : 'general';
  const q = useFlxQuery(FlxSocialSettings, {});
  const labels: Record<Tab, string> = { general: t('General'), apps: t('Apps'), networks: t('Network settings'), auto: t('Automatic sharing'), log: t('Log') };
  return (
    <div className="flxsm-settings">
      <nav className="flxsm-tabs flxsm-stabs">
        {TABS.map((x) => (
          <button key={x} type="button" className={'flxsm-tab' + (cur === x ? ' is-on' : '')} onClick={() => s.go('settings', { tab: x })}>
            <Icon name={TAB_ICONS[x]} size={15} />
            <span>{labels[x]}</span>
          </button>
        ))}
      </nav>
      <div className="flxsm-sbody">
        {q.isLoading ? (
          <Loading />
        ) : q.isError || !q.data ? (
          <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>
        ) : cur === 'general' ? (
          <General data={q.data} />
        ) : cur === 'apps' ? (
          <Apps apps={q.data.flxSocialApps} />
        ) : cur === 'networks' ? (
          <Networks data={q.data} />
        ) : cur === 'auto' ? (
          <Auto data={q.data} />
        ) : (
          <LogTab />
        )}
      </div>
    </div>
  );
}

function useSaveSettings() {
  const s = useSocial();
  const { t } = s;
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  return useFlxMutation(FlxSocialSettingsUpdate, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialSettingsUpdate?.errors);
      if (err) s.toast(err, 'error');
      else {
        s.toast(t('Settings saved.'));
        void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialSettings'] });
        s.reloadBoot();
      }
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
}

function Toggle({ checked, onChange, label, hint }: { checked: boolean; onChange: (v: boolean) => void; label: string; hint?: string }) {
  return (
    <div className="flxsm-sfield is-check">
      <label className="flxsm-chk">
        <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
        <span>{label}</span>
      </label>
      {hint && <small className="flxsm-muted">{hint}</small>}
    </div>
  );
}

function SField({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  return (
    <div className="flxsm-sfield">
      <label>{label}</label>
      {children}
      {hint && <small className="flxsm-muted">{hint}</small>}
    </div>
  );
}

function Card({ title, children }: { title: ReactNode; children: ReactNode }) {
  return (
    <section className="flxsm-card">
      <div className="flxsm-card-h">
        <h3>{title}</h3>
      </div>
      {children}
    </section>
  );
}

/** General: approval and signature (each change saves at once, like the classic screen) and the scheduler address. */
function General({ data }: { data: Data }) {
  const s = useSocial();
  const { t } = s;
  const d = data.flxSocialSettings;
  const [sig, setSig] = useState(d.signature);
  const save = useSaveSettings();
  return (
    <>
      <Card title={t('Approval and signature')}>
        <Toggle
          checked={d.approval}
          onChange={(v) => save.mutate({ input: { approval: v } })}
          label={t('Posts of people who cannot publish wait for approval')}
          hint={t('Permissions come from Flux Roles: Social media: prepare / publish / manage posts.')}
        />
        <Toggle checked={d.utm} onChange={(v) => save.mutate({ input: { utm: v } })} label={t('Add UTM tags to site links (utm_source=network)')} hint={t('See how many visits each network brings in Analytics.')} />
        <SField label={t('Ready signature (added with one click in the composer)')}>
          <textarea rows={2} value={sig} onChange={(e) => setSig(e.target.value)} />
        </SField>
        <div className="flxsm-row">
          <button type="button" className="flxsm-btn" disabled={save.isPending} onClick={() => save.mutate({ input: { signature: sig } })}>
            <Icon name="check" size={15} /> {t('Save signature')}
          </button>
        </div>
      </Card>
      <TickBox maskedUrl={d.tickUrl} last={d.lastTick?.timestamp ?? 0} />
    </>
  );
}

/** Network settings: one card per network; each change saves at once. */
function Networks({ data }: { data: Data }) {
  const s = useSocial();
  const { t } = s;
  const d = data.flxSocialSettings;
  const save = useSaveSettings();
  const [lim, setLim] = useState(String(d.xMonthLimit));
  const [mv, setMv] = useState(d.metaVersion);
  return (
    <>
      <Card
        title={
          <>
            <NetDot provider="x" size={18} /> X (Twitter)
          </>
        }
      >
        <SField label={t('API tier')} hint={t('The free tier allows few posts a month and reads no metrics. Paid tiers (Basic/Pro or pay per use) bring metrics too.')}>
          <select value={d.xTier} onChange={(e) => save.mutate({ input: { xTier: e.target.value } })}>
            {d.xTiers.map((x) => (
              <option key={x.id} value={x.id}>
                {x.name}
              </option>
            ))}
          </select>
        </SField>
        <Toggle checked={d.xPremium} onChange={(v) => save.mutate({ input: { xPremium: v } })} label={t('X Premium account (long posts, 25,000 characters)')} />
        <SField label={t('Monthly post limit (0 = no limit)')} hint={t('Sent this month: {n}. At the limit new X posts are refused (to stay within the tier quota).', { n: d.xMonth })}>
          <div className="flxsm-row">
            <input type="number" min={0} style={{ maxWidth: 140 }} value={lim} onChange={(e) => setLim(e.target.value)} />
            <button type="button" className="flxsm-btn" onClick={() => save.mutate({ input: { xMonthLimit: Math.max(0, Number(lim) || 0) } })}>
              <Icon name="check" size={15} /> {t('Save')}
            </button>
          </div>
        </SField>
      </Card>
      <Card
        title={
          <>
            <NetDot provider="tiktok" size={18} /> TikTok
          </>
        }
      >
        <SField label={t('Sharing mode')} hint={t('Direct publishing needs your TikTok app to pass the audit; until then videos are visible only to you. Draft mode needs no audit.')}>
          <div className="flxsm-seg is-small">
            {(
              [
                ['inbox', t('Draft (lands in the TikTok app inbox)')],
                ['direct', t('Direct publish')],
              ] as const
            ).map(([id, label]) => (
              <button key={id} type="button" className={d.tiktokMode === id ? 'is-on' : ''} onClick={() => save.mutate({ input: { tiktokMode: id } })}>
                {label}
              </button>
            ))}
          </div>
        </SField>
      </Card>
      <Card
        title={
          <>
            <NetDot provider="pinterest" size={18} /> Pinterest
          </>
        }
      >
        <Toggle
          checked={d.pinSandbox}
          onChange={(v) => save.mutate({ input: { pinSandbox: v } })}
          label={t('Sandbox (trial access)')}
          hint={t('Turn on while your Pinterest app has no Standard access yet; pins are created only in the sandbox.')}
        />
      </Card>
      <Card
        title={
          <>
            <NetDot provider="linkedin" size={18} /> LinkedIn
          </>
        }
      >
        <Toggle
          checked={d.liOrg}
          onChange={(v) => save.mutate({ input: { liOrg: v } })}
          label={t('Also list the company pages I manage')}
          hint={t('Needs Community Management API approval. Without it turn this off; only the personal profile connects.')}
        />
      </Card>
      <Card
        title={
          <>
            <NetDot provider="facebook" size={18} /> <NetDot provider="instagram" size={18} /> Meta
          </>
        }
      >
        <SField label={t('Graph API version')} hint={t('Meta retires old versions after two years; upgrade here when needed (e.g. v25.0).')}>
          <div className="flxsm-row">
            <input type="text" style={{ maxWidth: 120 }} value={mv} onChange={(e) => setMv(e.target.value)} />
            <button type="button" className="flxsm-btn" onClick={() => save.mutate({ input: { metaVersion: mv.trim() } })}>
              <Icon name="check" size={15} /> {t('Save')}
            </button>
          </div>
        </SField>
      </Card>
      <Card
        title={
          <>
            <NetDot provider="whatsapp" size={18} /> WhatsApp
          </>
        }
      >
        {d.waChoices.length ? (
          <SField label={t('Evolution connection (Flux Mail)')}>
            <select value={d.waConn} onChange={(e) => save.mutate({ input: { waConn: Number(e.target.value) } })}>
              <option value={0}>{t('Default connection')}</option>
              {d.waChoices.map((x) => (
                <option key={x.id} value={x.id}>
                  {x.name}
                </option>
              ))}
            </select>
          </SField>
        ) : (
          <p className="flxsm-muted">{t('No WhatsApp (Evolution) connection in Flux Mail; Flux Chat settings are used.')}</p>
        )}
      </Card>
    </>
  );
}

/** External scheduler address. The server only sends it masked; "Show" fetches it once, "New key" replaces it. */
function TickBox({ maskedUrl, last }: { maskedUrl: string; last: number }) {
  const s = useSocial();
  const { t } = s;
  const [url, setUrl] = useState('');
  const reveal = useFlxMutation(FlxSocialTickKeyReveal, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialTickKeyReveal?.errors);
      if (err) s.toast(err, 'error');
      else setUrl(r.flxSocialTickKeyReveal?.url ?? '');
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
  return (
    <Card title={t('Scheduled posts')}>
      <p>
        {t(
          'Where WP-Cron is unreliable, scheduled posts need an outside trigger every minute. Create a job at cron-job.org (free) that calls this address every minute. The panel also triggers it while open and when a post is saved.',
        )}
      </p>
      <SField label={t('Trigger address (keep it secret)')}>
        <div className="flxsm-row flxsm-copy">
          <input type="text" readOnly value={url || maskedUrl} onFocus={() => !url && !reveal.isPending && reveal.mutate({ input: {} })} />
          <button
            type="button"
            className="flxsm-btn"
            disabled={reveal.isPending}
            onClick={() => {
              if (url) void navigator.clipboard?.writeText(url).then(() => s.toast(t('Copied.')));
              else reveal.mutate({ input: {} });
            }}
          >
            <Icon name="clipboard" size={15} /> {url ? t('Copy') : t('Show address')}
          </button>
          <button
            type="button"
            className="flxsm-btn"
            onClick={() =>
              s.confirm({
                title: t('Make a new key'),
                text: t('The old address stops working. Update your cron service afterwards.'),
                action: t('Make a new key'),
                danger: true,
                run: () => reveal.mutate({ input: { regenerate: true } }),
              })
            }
          >
            <Icon name="update" size={15} /> {t('New key')}
          </button>
        </div>
      </SField>
      <ul className="flxsm-beats">
        <li>
          <span>{t('External trigger')}</span>
          <strong className={last && last > Date.now() / 1000 - 600 ? 'flxsm-up' : 'flxsm-down'}>{last ? s.fmt(last) : t('never')}</strong>
        </li>
      </ul>
    </Card>
  );
}

function Apps({ apps }: { apps: AppRow[] }) {
  const { t } = useSocial();
  return (
    <div className="flxsm-apps">
      <p className="flxsm-muted flxsm-lead">{t('Open your own developer app for each network and enter its keys here. Secret keys are stored encrypted and never shown again; type a new one to change it.')}</p>
      {apps.map((a) => (
        <AppCard key={a.id} app={a} />
      ))}
    </div>
  );
}

function AppCard({ app }: { app: AppRow }) {
  const s = useSocial();
  const { t } = s;
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [vals, setVals] = useState<Record<string, string>>({});
  const save = useFlxMutation(FlxSocialAppUpdate, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialAppUpdate?.errors);
      if (err) s.toast(err, 'error');
      else {
        s.toast(t('{name} keys saved.', { name: app.label }));
        setVals({});
        void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialSettings'] });
      }
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
  const dirty = Object.keys(vals).length > 0;
  return (
    <details className={'flxsm-appd' + (app.ready ? ' is-ready' : '')} id={'app-' + app.id}>
      <summary>
        <span className="flxsm-app-nets">
          {app.connect.map((c) => (
            <NetDot key={c.provider + c.for} provider={c.provider} size={22} />
          ))}
        </span>
        <strong>{app.label}</strong>
        <span className="flxsm-grow" />
        <span className={'flxsm-st is-' + (app.ready ? 'published' : 'draft')}>{app.ready ? t('Ready') : app.fields.length ? t('No key') : t('Not connected')}</span>
        <span className="flxsm-chev" aria-hidden="true">
          ⌄
        </span>
      </summary>
      <form
        className="flxsm-app-b"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate({ input: { id: app.id, values: Object.entries(vals).map(([key, value]) => ({ key, value })) } });
        }}
      >
        {app.help && <p className="flxsm-app-help">{app.help}</p>}
        {app.console && (
          <p>
            <a href={app.console} target="_blank" rel="noopener noreferrer">
              <Icon name="external" size={13} /> {t('Open the developer console')}
            </a>
          </p>
        )}
        {app.redirect && (
          <SField label={t('Redirect (callback) address')}>
            <div className="flxsm-row flxsm-copy">
              <input type="text" readOnly value={app.redirect} />
              <button type="button" className="flxsm-btn" onClick={() => void navigator.clipboard?.writeText(app.redirect).then(() => s.toast(t('Copied.')))}>
                <Icon name="clipboard" size={15} /> {t('Copy')}
              </button>
            </div>
          </SField>
        )}
        {app.fields.length > 0 && (
          <div className="flxsm-copts-g">
            {app.fields.map((f) => (
              <div key={f.key} className="flxsm-copt">
                <label>
                  {f.label}
                  {f.optional && !f.label.includes('(') ? ' (' + t('optional') + ')' : ''}
                </label>
                <input
                  type={f.secret ? 'password' : 'text'}
                  autoComplete="off"
                  value={vals[f.key] ?? (f.secret ? '' : f.value)}
                  placeholder={f.secret ? (f.isSet ? t('Saved. Type to replace.') : t('Not set')) : f.placeholder}
                  onChange={(e) => setVals((v) => ({ ...v, [f.key]: e.target.value }))}
                />
              </div>
            ))}
          </div>
        )}
        <div className="flxsm-row">
          {app.fields.length > 0 && (
            <button type="submit" className="flxsm-btn is-primary" disabled={!dirty || save.isPending}>
              <Icon name="check" size={15} /> {t('Save')}
            </button>
          )}
          {app.ready && (
            <button type="button" className="flxsm-btn" onClick={() => s.go('accounts')}>
              <Icon name="plus-alt" size={15} /> {t('Connect account')}
            </button>
          )}
        </div>
      </form>
    </details>
  );
}

function Auto({ data }: { data: Data }) {
  const s = useSocial();
  const { t } = s;
  const d = data.flxSocialSettings;
  const [a, setA] = useState({ ...d.auto });
  const save = useSaveSettings();
  const toggle = <T,>(list: T[], v: T, on: boolean) => (on ? [...list, v] : list.filter((x) => x !== v));
  return (
    <>
      <Card title={t('Share when a post is published on the site')}>
        <Toggle checked={a.enabled} onChange={(v) => setA({ ...a, enabled: v })} label={t('On')} />
        <SField label={t('Which content types')}>
          <div className="flxsm-row">
            {d.postTypes.map((p) => (
              <label key={p.id} className="flxsm-chk">
                <input type="checkbox" checked={a.postTypes.includes(p.id)} onChange={(e) => setA({ ...a, postTypes: toggle(a.postTypes, p.id, e.target.checked) })} />
                <span>{p.name}</span>
              </label>
            ))}
          </div>
        </SField>
        <SField label={t('Which accounts')}>
          {s.accounts.length ? (
            <div className="flxsm-apick">
              {s.accounts.map((acc) => {
                const on = a.accounts.includes(acc.databaseId);
                return (
                  <button
                    key={acc.id}
                    type="button"
                    className={'flxsm-apick-b' + (on ? ' is-on' : '')}
                    title={acc.network + ' › ' + acc.name}
                    aria-pressed={on}
                    onClick={() => setA({ ...a, accounts: toggle(a.accounts, acc.databaseId, !on) })}
                  >
                    <Avatar src={acc.avatar} name={acc.name} size={36} />
                    <span className="flxsm-apick-ck">
                      <Icon name="check" size={11} />
                    </span>
                  </button>
                );
              })}
            </div>
          ) : (
            <p className="flxsm-muted">{t('Connect an account first.')}</p>
          )}
        </SField>
        <SField label={t('Text template')} hint={t('Available: {title} {excerpt} {link} {site} {author} {category} {tags}. The link builds the network preview card.')}>
          <textarea rows={5} value={a.template} onChange={(e) => setA({ ...a, template: e.target.value })} />
        </SField>
        <Toggle checked={a.image} onChange={(v) => setA({ ...a, image: v })} label={t('Add the featured image')} />
        <SField label={t('What to do')}>
          <select style={{ maxWidth: 320 }} value={a.mode} onChange={(e) => setA({ ...a, mode: e.target.value })}>
            <option value="draft">{t('Make a draft (I review and send it)')}</option>
            <option value="schedule">{t('Schedule it automatically')}</option>
          </select>
        </SField>
        <SField label={t('Minutes until it is shared (when scheduling)')}>
          <input type="number" min={0} max={1440} style={{ maxWidth: 120 }} value={a.delay} onChange={(e) => setA({ ...a, delay: Math.max(0, Number(e.target.value) || 0) })} />
        </SField>
        <div className="flxsm-row">
          <button type="button" className="flxsm-btn is-primary" disabled={save.isPending} onClick={() => save.mutate({ input: { auto: a } })}>
            <Icon name="check" size={15} /> {t('Save')}
          </button>
        </div>
      </Card>
      <p className="flxsm-muted flxsm-small">{t('Runs once per post. Accounts that do not fit a network rule (e.g. Instagram without an image) stay in the draft.')}</p>
    </>
  );
}

function LogTab() {
  const s = useSocial();
  const { t } = s;
  const q = useFlxQuery(FlxSocialLog, {});
  const rows = q.data?.flxSocialLog ?? [];
  const acc = (id: number) => s.accounts.find((a) => a.databaseId === id);
  return (
    <>
      <div className="flxsm-row">
        <p className="flxsm-muted">{t('Last 200 entries (kept for 45 days).')}</p>
        <span className="flxsm-grow" />
        <button type="button" className="flxsm-btn" disabled={q.isFetching} onClick={() => void q.refetch()}>
          <Icon name="update" size={15} /> {t('Refresh')}
        </button>
      </div>
      <div className="flxsm-log">
        {q.isLoading ? (
          <Loading />
        ) : q.isError ? (
          <p className="flxsm-bad">{errorText(q.error, t('Something went wrong.'))}</p>
        ) : !rows.length ? (
          <p className="flxsm-muted">{t('No log entries.')}</p>
        ) : (
          rows.map((r) => {
            const a = r.accountId ? acc(r.accountId) : undefined;
            return (
              <div key={r.databaseId} className={'flxsm-logrow is-' + r.level}>
                <time>{r.time ? s.fmt(r.time.timestamp) : ''}</time>
                <span className="flxsm-loglv">{r.level}</span>
                {a && (
                  <span className="flxsm-logacc">
                    <NetDot provider={a.provider} size={13} />
                    {a.name}
                  </span>
                )}
                <span className="flxsm-logmsg">{r.message}</span>
              </div>
            );
          })
        )}
      </div>
    </>
  );
}
