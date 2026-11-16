// Settings: general, app keys (secrets are write-only), automatic sharing, activity log.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSocialAppUpdate, FlxSocialLog, FlxSocialSettings, FlxSocialSettingsUpdate, FlxSocialTickKeyReveal } from '@fluxwp/graphql/operations';
import type { FlxSocialSettingsQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { errorText, payloadError, useSocial } from './common';

type Data = FlxSocialSettingsQuery;
type AppRow = Data['flxSocialApps'][number];
const TABS = ['general', 'apps', 'auto', 'log'] as const;
type Tab = (typeof TABS)[number];

export function SettingsPage({ tab }: { tab?: string | undefined }) {
  const s = useSocial();
  const { t } = s;
  const cur: Tab = (TABS as readonly string[]).includes(tab ?? '') ? (tab as Tab) : 'general';
  const q = useFlxQuery(FlxSocialSettings, {});
  const labels: Record<Tab, string> = { general: t('General'), apps: t('App keys'), auto: t('Automatic sharing'), log: t('Activity log') };
  return (
    <div className="flxsm-settings">
      <div className="flxsm-tabs" role="tablist">
        {TABS.map((x) => (
          <button key={x} type="button" role="tab" aria-selected={cur === x} className={'flxsm-tab' + (cur === x ? ' is-on' : '')} onClick={() => s.go('settings', { tab: x })}>
            {labels[x]}
          </button>
        ))}
      </div>
      {q.isLoading ? (
        <Loading />
      ) : q.isError || !q.data ? (
        <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>
      ) : cur === 'general' ? (
        <General data={q.data} />
      ) : cur === 'apps' ? (
        <Apps apps={q.data.flxSocialApps} />
      ) : cur === 'auto' ? (
        <Auto data={q.data} />
      ) : (
        <LogTab />
      )}
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

function Switch({ checked, onChange, label, hint }: { checked: boolean; onChange: (v: boolean) => void; label: string; hint?: string }) {
  return (
    <label className="flxsm-chk flxsm-row-chk">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      <span>
        {label}
        {hint && <small className="flxsm-muted">{hint}</small>}
      </span>
    </label>
  );
}

function General({ data }: { data: Data }) {
  const s = useSocial();
  const { t } = s;
  const d = data.flxSocialSettings;
  const [f, setF] = useState({
    approval: d.approval,
    signature: d.signature,
    utm: d.utm,
    metaVersion: d.metaVersion,
    xTier: d.xTier,
    xPremium: d.xPremium,
    xMonthLimit: d.xMonthLimit,
    tiktokMode: d.tiktokMode,
    pinSandbox: d.pinSandbox,
    liOrg: d.liOrg,
    waConn: d.waConn,
  });
  const save = useSaveSettings();
  const up = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((x) => ({ ...x, [k]: v }));
  return (
    <form
      className="flxsm-card flxsm-form"
      onSubmit={(e) => {
        e.preventDefault();
        save.mutate({ input: f });
      }}
    >
      <Switch checked={f.approval} onChange={(v) => up('approval', v)} label={t('Approval')} hint={t('Posts of people who cannot publish wait for approval.')} />
      <Switch checked={f.utm} onChange={(v) => up('utm', v)} label={t('UTM tags')} hint={t('Add UTM tags to links of this site.')} />
      <label className="flxsm-field">
        <span>{t('Signature')}</span>
        <textarea rows={2} value={f.signature} onChange={(e) => up('signature', e.target.value)} />
        <small className="flxsm-muted">{t('Added to the end of posts. The composer lets you switch it off per post.')}</small>
      </label>
      <label className="flxsm-field">
        <span>{t('Facebook and Instagram API version')}</span>
        <input type="text" value={f.metaVersion} onChange={(e) => up('metaVersion', e.target.value)} />
      </label>
      <label className="flxsm-field">
        <span>{t('X plan')}</span>
        <select value={f.xTier} onChange={(e) => up('xTier', e.target.value)}>
          {d.xTiers.map((x) => (
            <option key={x.id} value={x.id}>
              {x.name}
            </option>
          ))}
        </select>
      </label>
      <Switch checked={f.xPremium} onChange={(v) => up('xPremium', v)} label={t('Paid tier on X')} hint={t('Accounts have the paid tier, so longer posts are allowed.')} />
      <label className="flxsm-field">
        <span>{t('Posts per month on X')}</span>
        <input type="number" min={0} value={f.xMonthLimit} onChange={(e) => up('xMonthLimit', Math.max(0, Number(e.target.value) || 0))} />
        <small className="flxsm-muted">{t('0: no limit. This month: {n}.', { n: d.xMonth })}</small>
      </label>
      <label className="flxsm-field">
        <span>{t('TikTok mode')}</span>
        <select value={f.tiktokMode} onChange={(e) => up('tiktokMode', e.target.value)}>
          <option value="inbox">{t('Draft (lands in the TikTok app inbox)')}</option>
          <option value="direct">{t('Direct publish')}</option>
        </select>
      </label>
      <Switch checked={f.pinSandbox} onChange={(v) => up('pinSandbox', v)} label={t('Pinterest sandbox')} />
      <Switch checked={f.liOrg} onChange={(v) => up('liOrg', v)} label={t('List LinkedIn pages')} />
      <label className="flxsm-field">
        <span>{t('WhatsApp connection')}</span>
        <select value={f.waConn} onChange={(e) => up('waConn', Number(e.target.value))}>
          <option value={0}>{t('None')}</option>
          {d.waChoices.map((x) => (
            <option key={x.id} value={x.id}>
              {x.name}
            </option>
          ))}
        </select>
      </label>
      <div className="flxsm-actions">
        <button type="submit" className="flxsm-btn is-primary" disabled={save.isPending}>
          {t('Save')}
        </button>
      </div>
      <TickBox maskedUrl={d.tickUrl} last={d.lastTick?.timestamp ?? 0} />
    </form>
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
    <section className="flxsm-tick">
      <h3>{t('Scheduler address')}</h3>
      <p className="flxsm-muted">{t('Call this address every minute from a cron service so scheduled posts go out even when nobody is on the panel.')}</p>
      <code className="flxsm-code">{url || maskedUrl}</code>
      <p className="flxsm-muted">{last ? t('Last run: {time}', { time: s.fmt(last) }) : t('Never run from outside.')}</p>
      <div className="flxsm-actions">
        <button type="button" className="flxsm-btn" disabled={reveal.isPending} onClick={() => reveal.mutate({ input: {} })}>
          {t('Show address')}
        </button>
        {url && (
          <button
            type="button"
            className="flxsm-btn"
            onClick={() => {
              void navigator.clipboard?.writeText(url).then(() => s.toast(t('Copied.')));
            }}
          >
            {t('Copy')}
          </button>
        )}
        <button
          type="button"
          className="flxsm-btn is-danger"
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
          {t('New key')}
        </button>
      </div>
    </section>
  );
}

function Apps({ apps }: { apps: AppRow[] }) {
  return (
    <div className="flxsm-apps">
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
    <form
      className="flxsm-card flxsm-form"
      onSubmit={(e) => {
        e.preventDefault();
        save.mutate({ input: { id: app.id, values: Object.entries(vals).map(([key, value]) => ({ key, value })) } });
      }}
    >
      <h2>
        {app.label} <span className={'flxsm-st is-' + (app.ready ? 'published' : 'draft')}>{app.ready ? t('Ready') : t('Not set')}</span>
      </h2>
      {app.help && <p className="flxsm-muted">{app.help}</p>}
      {app.redirect && (
        <p className="flxsm-muted">
          {t('Redirect address')}: <code>{app.redirect}</code>
        </p>
      )}
      {app.console && (
        <p>
          <a href={app.console} target="_blank" rel="noopener noreferrer">
            {t('Open the developer console')}
          </a>
        </p>
      )}
      {app.fields.map((f) => (
        <label key={f.key} className="flxsm-field">
          <span>
            {f.label}
            {f.optional ? ' (' + t('optional') + ')' : ''}
          </span>
          <input
            type={f.secret ? 'password' : 'text'}
            autoComplete="off"
            value={vals[f.key] ?? (f.secret ? '' : f.value)}
            placeholder={f.secret && f.isSet ? t('Saved. Type to replace.') : f.placeholder}
            onChange={(e) => setVals((v) => ({ ...v, [f.key]: e.target.value }))}
          />
        </label>
      ))}
      <div className="flxsm-actions">
        <button type="submit" className="flxsm-btn is-primary" disabled={!dirty || save.isPending}>
          {t('Save')}
        </button>
      </div>
    </form>
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
    <form
      className="flxsm-card flxsm-form"
      onSubmit={(e) => {
        e.preventDefault();
        save.mutate({ input: { auto: a } });
      }}
    >
      <p className="flxsm-muted">{t('When content is published on the site, a post is made automatically.')}</p>
      <Switch checked={a.enabled} onChange={(v) => setA({ ...a, enabled: v })} label={t('Share new content automatically')} />
      <fieldset className="flxsm-field">
        <legend>{t('Content types')}</legend>
        {d.postTypes.map((p) => (
          <label key={p.id} className="flxsm-chk">
            <input type="checkbox" checked={a.postTypes.includes(p.id)} onChange={(e) => setA({ ...a, postTypes: toggle(a.postTypes, p.id, e.target.checked) })} />
            <span>{p.name}</span>
          </label>
        ))}
      </fieldset>
      <fieldset className="flxsm-field">
        <legend>{t('Accounts')}</legend>
        {s.accounts.map((acc) => (
          <label key={acc.id} className="flxsm-chk">
            <input type="checkbox" checked={a.accounts.includes(acc.databaseId)} onChange={(e) => setA({ ...a, accounts: toggle(a.accounts, acc.databaseId, e.target.checked) })} />
            <span>
              {acc.name} · {acc.network}
            </span>
          </label>
        ))}
      </fieldset>
      <label className="flxsm-field">
        <span>{t('Text template')}</span>
        <textarea rows={3} value={a.template} onChange={(e) => setA({ ...a, template: e.target.value })} />
        <small className="flxsm-muted">{t('You can use {title}, {excerpt} and {url}.')}</small>
      </label>
      <label className="flxsm-field">
        <span>{t('What to do')}</span>
        <select value={a.mode} onChange={(e) => setA({ ...a, mode: e.target.value })}>
          <option value="draft">{t('Make a draft')}</option>
          <option value="schedule">{t('Schedule it')}</option>
        </select>
      </label>
      <label className="flxsm-field">
        <span>{t('Minutes to wait')}</span>
        <input type="number" min={0} value={a.delay} onChange={(e) => setA({ ...a, delay: Math.max(0, Number(e.target.value) || 0) })} />
      </label>
      <Switch checked={a.image} onChange={(v) => setA({ ...a, image: v })} label={t('Add the featured image')} />
      <div className="flxsm-actions">
        <button type="submit" className="flxsm-btn is-primary" disabled={save.isPending}>
          {t('Save')}
        </button>
      </div>
    </form>
  );
}

function LogTab() {
  const s = useSocial();
  const { t } = s;
  const q = useFlxQuery(FlxSocialLog, {});
  if (q.isLoading) return <Loading />;
  if (q.isError) return <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>;
  const rows = q.data?.flxSocialLog ?? [];
  const name = (id: number) => s.accounts.find((a) => a.databaseId === id)?.name ?? '';
  return (
    <div className="flxsm-card flxsm-table-wrap">
      {!rows.length ? (
        <p className="flxsm-muted">{t('No log entries.')}</p>
      ) : (
        <table className="flxsm-table">
          <thead>
            <tr>
              <th>{t('Time')}</th>
              <th>{t('Level')}</th>
              <th>{t('Account')}</th>
              <th>{t('Message')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.databaseId}>
                <td className="flxsm-nowrap">{r.time ? s.fmt(r.time.timestamp) : ''}</td>
                <td>
                  <span className={'flxsm-st is-' + (r.level === 'error' ? 'failed' : r.level === 'warn' ? 'pending' : 'published')}>{r.level}</span>
                </td>
                <td>{name(r.accountId)}</td>
                <td>{r.message}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
