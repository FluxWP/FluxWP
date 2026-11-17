// Flux Headless. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through
// the Flux Headless GraphQL operations (plugins/flx-headless/graphql/operations).
// Front ends (one WordPress, many headless sites), what WordPress does with its own pages, the connection
// details a developer needs, and the revalidate webhook log. Webhook secrets are write-only.
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxHeadlessFrontendDelete,
  FlxHeadlessFrontendSave,
  FlxHeadlessLogClear,
  FlxHeadlessRevalidateAll,
  FlxHeadlessRevalidateTest,
  FlxHeadlessScreen,
  FlxHeadlessWpFrontSet,
} from '@fluxwp/graphql/operations';
import type { FlxHeadlessFrontendSaveInput, FlxHeadlessScreenQuery, FlxHeadlessWpFront } from '@fluxwp/graphql/types';
import { Check, Icon, Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './headless.css';

type Settings = FlxHeadlessScreenQuery['flxHeadlessSettings'];
type Frontend = Settings['frontends'][number];
type Delivery = Settings['deliveries'][number];
type Notice = { kind: 'ok' | 'warn' | 'bad'; text: string };
type UserError = { field?: string | null; message: string };

interface Draft {
  id: string;
  name: string;
  url: string;
  languages: string[];
  enabled: boolean;
  isDefault: boolean;
  revalidateUrl: string;
  secret: string;
  clearSecret: boolean;
  previewPath: string;
}

const draftOf = (f: Frontend | null, first: boolean): Draft => ({
  id: f?.id ?? '',
  name: f?.name ?? '',
  url: f?.url ?? '',
  languages: f?.languages ?? [],
  enabled: f?.enabled ?? true,
  isDefault: f?.isDefault ?? first,
  revalidateUrl: f?.revalidateUrl ?? '',
  secret: '',
  clearSecret: false,
  previewPath: f?.previewPath ?? '',
});

/** 48 random hex characters from the browser's crypto (never sent anywhere until saved). */
const newSecret = () => Array.from(crypto.getRandomValues(new Uint8Array(24)), (b) => b.toString(16).padStart(2, '0')).join('');

const copy = (text: string) => {
  try {
    void navigator.clipboard?.writeText(text);
  } catch {
    /* clipboard blocked: the value stays selectable on screen */
  }
};

export function HeadlessScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Headless />
    </FlxGraphQLProvider>
  );
}

function Headless() {
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxHeadlessScreen, {});
  const [notice, setNotice] = useState<Notice | null>(null);
  const [editing, setEditing] = useState<Draft | null>(null);
  const [errors, setErrors] = useState<UserError[]>([]);
  const [removing, setRemoving] = useState<Frontend | null>(null);
  const [testing, setTesting] = useState('');

  const put = (s: Settings) => qc.setQueryData<FlxHeadlessScreenQuery>(flxQueryKey(scope, FlxHeadlessScreen, {}), { flxHeadlessSettings: s });
  const fail = (e: unknown) => setNotice({ kind: 'bad', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
  const done = (p: { settings: Settings; errors: UserError[] } | null | undefined, ok: string) => {
    if (!p) return fail(null);
    if (p.errors.length) {
      setNotice({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      return false;
    }
    put(p.settings);
    setNotice({ kind: 'ok', text: ok });
    return true;
  };

  const save = useFlxMutation(FlxHeadlessFrontendSave, {
    onSuccess: (r) => {
      const p = r.flxHeadlessFrontendSave;
      if (!p) return fail(null);
      if (p.errors.length) return setErrors(p.errors);
      put(p.settings);
      setEditing(null);
      setErrors([]);
      setNotice({ kind: 'ok', text: t('Saved.') });
    },
    onError: fail,
  });
  const remove = useFlxMutation(FlxHeadlessFrontendDelete, {
    onSuccess: (r) => {
      setRemoving(null);
      done(r.flxHeadlessFrontendDelete, t('Front end removed.'));
    },
    onError: fail,
  });
  const mode = useFlxMutation(FlxHeadlessWpFrontSet, { onSuccess: (r) => done(r.flxHeadlessWpFrontSet, t('Saved.')), onError: fail });
  const test = useFlxMutation(FlxHeadlessRevalidateTest, {
    onSuccess: (r) => {
      setTesting('');
      const p = r.flxHeadlessRevalidateTest;
      if (!p) return fail(null);
      if (p.errors.length) return setNotice({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      put(p.settings);
      setNotice(p.delivery?.ok ? { kind: 'ok', text: t('Webhook works: %s', p.delivery.message) } : { kind: 'bad', text: p.delivery?.message ?? t('The action could not be completed.') });
    },
    onError: (e) => {
      setTesting('');
      fail(e);
    },
  });
  const all = useFlxMutation(FlxHeadlessRevalidateAll, { onSuccess: (r) => done(r.flxHeadlessRevalidateAll, t('Every front end was asked to refresh all pages.')), onError: fail });
  const clear = useFlxMutation(FlxHeadlessLogClear, { onSuccess: (r) => done(r.flxHeadlessLogClear, t('Log cleared.')), onError: fail });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxhl-app"><div className="flxhl-notice is-bad">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const s = q.data.flxHeadlessSettings;
  const active = s.frontends.filter((f) => f.enabled);
  const hooked = active.filter((f) => f.revalidateUrl && f.secretSet);
  const last = s.deliveries[0];

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!editing) return;
    const input: FlxHeadlessFrontendSaveInput = {
      name: editing.name.trim(),
      url: editing.url.trim(),
      languages: editing.languages,
      enabled: editing.enabled,
      isDefault: editing.isDefault,
      revalidateUrl: editing.revalidateUrl.trim(),
      previewPath: editing.previewPath.trim(),
    };
    if (editing.id) input.id = editing.id;
    if (editing.clearSecret) input.secret = '__clear__';
    else if (editing.secret.trim()) input.secret = editing.secret.trim();
    save.mutate({ input });
  };

  return (
    <div className="flxhl-app">
      <div className="flxhl-head">
        <div>
          <h1>{t('Headless')}</h1>
          <p className="flxhl-lead">{t('Serve this site to one or more headless front ends (Next.js, Nuxt, Astro or any framework) over GraphQL. Each front end gets its own address, languages and revalidate webhook; content changes reach them within seconds.')}</p>
        </div>
        <button type="button" className="flxhl-btn is-primary" onClick={() => { setErrors([]); setEditing(draftOf(null, s.frontends.length === 0)); }}>
          <Icon name="plus-alt" size={16} />
          {t('Add front end')}
        </button>
      </div>

      {notice && (
        <div className={'flxhl-notice is-' + notice.kind} role={notice.kind === 'bad' ? 'alert' : 'status'}>
          <Icon name={notice.kind === 'ok' ? 'check' : 'warning'} size={16} />
          <span>{notice.text}</span>
          <button type="button" className="flxhl-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}

      <div className="flxhl-kpis">
        <Kpi icon="admin-site" label={t('Front ends')} value={String(active.length)} hint={tn('%d added', '%d added', s.frontends.length, s.frontends.length)} cls={active.length ? 'is-ok' : 'is-warn'} />
        <Kpi icon="update" label={t('Webhooks')} value={`${hooked.length} / ${active.length}`} hint={t('front ends refresh on change')} cls={active.length && hooked.length === active.length ? 'is-ok' : active.length ? 'is-warn' : ''} />
        <Kpi icon="clock" label={t('Last call')} value={last ? (last.ok ? t('Delivered') : t('Failed')) : t('None yet')} hint={last ? `${last.frontendName} · ${last.time.label}` : t('sent when content changes')} cls={last ? (last.ok ? 'is-ok' : 'is-bad') : ''} />
        <Kpi icon="shield" label={t('Queries')} value={s.persistedOnly ? t('Registered only') : t('Open')} hint={t('Flux GraphQL query mode')} />
      </div>

      {!active.length && (
        <div className="flxhl-callout is-info">
          <Icon name="info" size={16} />
          <span>
            <strong>{t('No front end yet.')}</strong> {t('Add the address of your headless site. Its origin is allowed to call GraphQL from the browser, links and canonical addresses point at it, and it can receive a signed webhook when content changes.')}
          </span>
        </div>
      )}

      <div className="flxhl-grid">
        <section className="flxhl-card flxhl-span-2">
          <div className="flxhl-card__head">
            <h2>
              <Icon name="admin-site" size={16} />
              {t('Front ends')}
            </h2>
            {active.length > 0 && (
              <button type="button" className="flxhl-btn" disabled={all.isPending || !hooked.length} onClick={() => all.mutate({})}>
                <Icon name="update" size={14} />
                {t('Refresh all front ends')}
              </button>
            )}
          </div>
          {s.frontends.length === 0 ? (
            <p className="flxhl-muted">{t('Front ends you add are listed here.')}</p>
          ) : (
            <ul className="flxhl-rows">
              {s.frontends.map((f) => (
                <li key={f.id} className={'flxhl-fe' + (f.enabled ? '' : ' is-off')}>
                  <div className="flxhl-fe__main">
                    <div className="flxhl-fe__title">
                      <strong>{f.name}</strong>
                      {f.isDefault && <span className="flxhl-badge is-ok">{t('Default')}</span>}
                      {!f.enabled && <span className="flxhl-badge">{t('Off')}</span>}
                      <code className="flxhl-badge">{f.id}</code>
                    </div>
                    <a href={f.url} target="_blank" rel="noreferrer" className="flxhl-fe__url">
                      {f.url}
                    </a>
                    <div className="flxhl-fe__meta">
                      <span>{f.languages.length ? t('Languages: %s', f.languages.join(', ')) : t('Every language')}</span>
                      <span className={f.revalidateUrl && f.secretSet ? 'is-ok' : 'is-warn'}>{f.revalidateUrl && f.secretSet ? t('Webhook on') : t('No webhook')}</span>
                      {f.previewPath && <span>{t('Previews on the front end')}</span>}
                    </div>
                  </div>
                  <div className="flxhl-fe__actions">
                    {f.revalidateUrl && f.secretSet && (
                      <button type="button" className="flxhl-btn" disabled={testing !== ''} onClick={() => { setTesting(f.id); test.mutate({ id: f.id }); }}>
                        {testing === f.id ? t('Testing…') : t('Test webhook')}
                      </button>
                    )}
                    <button type="button" className="flxhl-btn" onClick={() => { setErrors([]); setEditing(draftOf(f, false)); }}>
                      {t('Edit')}
                    </button>
                    <button type="button" className="flxhl-btn is-danger" aria-label={t('Remove %s', f.name)} onClick={() => setRemoving(f)}>
                      <Icon name="trash" size={14} />
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="flxhl-card">
          <div className="flxhl-card__head">
            <h2>
              <Icon name="admin-home" size={16} />
              {t('WordPress pages')}
            </h2>
          </div>
          <p className="flxhl-muted">{t('What visitors and search engines get when they open this WordPress address directly.')}</p>
          <div className="flxhl-choice" role="radiogroup" aria-label={t('WordPress pages')}>
            {(
              [
                ['KEEP', t('Keep showing them'), t('Both sites stay open. Good while the front end is being built.')],
                ['NOINDEX', t('Show, but hide from search'), t('Pages stay open with noindex and a canonical link to the front end, so search engines index only the front end.')],
                ['REDIRECT', t('Send visitors to the front end'), t('301 to the same address on the default front end. Cart, checkout, account, previews and editors stay on WordPress.')],
              ] as const
            ).map(([v, label, help]) => (
              <Check key={v} radio name="flxhl-wpfront" value={v} checked={s.wpFront === v} disabled={mode.isPending || (v !== 'KEEP' && !active.length)} note={help} onChange={() => mode.mutate({ mode: v as FlxHeadlessWpFront })}>
                {label}
              </Check>
            ))}
          </div>
        </section>

        <section className="flxhl-card flxhl-span-3">
          <div className="flxhl-card__head">
            <h2>
              <Icon name="editor-code" size={16} />
              {t('Connection details')}
            </h2>
            <a href={s.graphqlSettingsUrl}>{t('Flux GraphQL settings')}</a>
          </div>
          <div className="flxhl-conn">
            <Copyable label={t('GraphQL address')} value={s.graphqlUrl} t={t} />
            <div className="flxhl-conn__item">
              <span>{t('Allowed browser origins (CORS)')}</span>
              <ul className="flxhl-chips">
                {s.corsOrigins.map((o) => (
                  <li key={o}>
                    <code>{o}</code>
                  </li>
                ))}
              </ul>
              <small>{t('This site, the Flux GraphQL list and every enabled front end. Add other origins in Flux GraphQL settings.')}</small>
            </div>
          </div>
          {active[0] && (
            <>
              <h3 className="flxhl-sub">{t('Environment of the front end')}</h3>
              <pre className="flxhl-env">{`FLUX_GRAPHQL_URL=${s.graphqlUrl}\nFLUX_FRONTEND_ID=${(active.find((f) => f.isDefault) ?? active[0]).id}\nFLUX_REVALIDATE_SECRET=${t('<the secret you saved>')}`}</pre>
              <p className="flxhl-muted">{t('The front end sends flxRoute and flxSite by their registered ids and checks the X-Flx-Signature header of every webhook call with the secret. The integration guide is in docs/flx-headless/.')}</p>
            </>
          )}
        </section>

        <section className="flxhl-card flxhl-span-3">
          <div className="flxhl-card__head">
            <h2>
              <Icon name="list-view" size={16} />
              {t('Webhook calls')}
            </h2>
            {s.deliveries.length > 0 && (
              <button type="button" className="flxhl-btn" disabled={clear.isPending} onClick={() => clear.mutate({})}>
                {t('Clear log')}
              </button>
            )}
          </div>
          {s.deliveries.length === 0 ? (
            <p className="flxhl-muted">{t('Calls are listed here once content changes or you test a webhook.')}</p>
          ) : (
            <div className="flxhl-table-wrap">
              <table className="flxhl-table">
                <thead>
                  <tr>
                    <th>{t('Time')}</th>
                    <th>{t('Front end')}</th>
                    <th>{t('Event')}</th>
                    <th>{t('Tags')}</th>
                    <th>{t('Result')}</th>
                  </tr>
                </thead>
                <tbody>
                  {s.deliveries.map((d, i) => (
                    <DeliveryRow key={i} d={d} t={t} />
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      {editing && (
        <Modal title={editing.id ? t('Edit front end') : t('Add front end')} onClose={() => setEditing(null)} wide>
          <FrontendForm draft={editing} errors={errors} langs={s.languagesActive ? s.languages : []} busy={save.isPending} saved={s.frontends.find((f) => f.id === editing.id) ?? null} t={t} onChange={(p) => setEditing((o) => (o ? { ...o, ...p } : o))} onSubmit={submit} onCancel={() => setEditing(null)} />
        </Modal>
      )}
      {removing && (
        <Modal title={t('Remove front end?')} onClose={() => setRemoving(null)}>
          <p>{t('"%s" stops receiving webhook calls and its origin can no longer call GraphQL from the browser. Its secret is deleted.', removing.name)}</p>
          <div className="flxhl-modal-actions">
            <button type="button" className="flxhl-btn" onClick={() => setRemoving(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxhl-btn is-danger" disabled={remove.isPending} onClick={() => remove.mutate({ id: removing.id })}>
              {t('Remove')}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

function FrontendForm({ draft, errors, langs, busy, saved, t, onChange, onSubmit, onCancel }: {
  draft: Draft;
  errors: UserError[];
  langs: Settings['languages'];
  busy: boolean;
  saved: Frontend | null;
  t: Translate;
  onChange: (p: Partial<Draft>) => void;
  onSubmit: (e: FormEvent) => void;
  onCancel: () => void;
}) {
  const err = (field: string) => errors.find((e) => e.field === field)?.message;
  const general = errors.filter((e) => !e.field || !['name', 'url', 'languages', 'revalidateUrl', 'secret', 'previewPath'].includes(e.field));
  const field = (name: string, label: string, input: ReactNode, help?: string) => (
    <label className={'flxhl-field' + (err(name) ? ' has-error' : '')}>
      <span>{label}</span>
      {input}
      {err(name) ? <small className="flxhl-err">{err(name)}</small> : help ? <small>{help}</small> : null}
    </label>
  );
  return (
    <form className="flxhl-form" onSubmit={onSubmit}>
      {general.map((e, i) => (
        <div key={i} className="flxhl-notice is-bad">
          {e.message}
        </div>
      ))}
      <div className="flxhl-form__grid">
        {field('name', t('Name'), <input type="text" value={draft.name} onChange={(e) => onChange({ name: e.currentTarget.value })} placeholder={t('Main site')} required />)}
        {field('url', t('Address'), <input type="url" className="code" value={draft.url} onChange={(e) => onChange({ url: e.currentTarget.value })} placeholder="https://www.example.com" required />, t('Where visitors open the front end. WordPress usually moves to another address, e.g. cms.example.com.'))}
      </div>
      {langs.length > 0 && (
        <fieldset className={'flxhl-langs' + (err('languages') ? ' has-error' : '')}>
          <legend>{t('Languages')}</legend>
          <div className="flxhl-langs__list">
            {langs.map((l) => (
              <Check key={l.code} checked={draft.languages.includes(l.code)} onChange={(on) => onChange({ languages: on ? [...draft.languages, l.code] : draft.languages.filter((c) => c !== l.code) })}>
                {l.name} <code>{l.code}</code>
              </Check>
            ))}
          </div>
          <small>{err('languages') ?? t('None selected: the front end serves every language.')}</small>
        </fieldset>
      )}
      <div className="flxhl-form__switches">
        <Check switch checked={draft.enabled} onChange={(v) => onChange({ enabled: v })} note={t('Off: no CORS, links or webhook for it.')}>
          {t('In use')}
        </Check>
        <Check switch checked={draft.isDefault} onChange={(v) => onChange({ isDefault: v })} note={t('Answers requests that name no front end; previews and redirects go here.')}>
          {t('Default front end')}
        </Check>
      </div>
      <h3 className="flxhl-sub">{t('Revalidate webhook')}</h3>
      <div className="flxhl-form__grid">
        {field('revalidateUrl', t('Webhook address'), <input type="url" className="code" value={draft.revalidateUrl} onChange={(e) => onChange({ revalidateUrl: e.currentTarget.value })} placeholder="https://www.example.com/api/revalidate" />, t('Receives a signed POST with the cache tags when content changes. Empty: no webhook.'))}
        <label className={'flxhl-field' + (err('secret') ? ' has-error' : '')}>
          <span>{t('Secret')}</span>
          <div className="flxhl-secret">
            <input
              type="text"
              className="code"
              value={draft.clearSecret ? '' : draft.secret}
              disabled={draft.clearSecret}
              autoComplete="off"
              spellCheck={false}
              placeholder={saved?.secretSet ? t('Saved (%s). Type to replace it.', saved.secretHint) : t('At least 24 characters')}
              onChange={(e) => onChange({ secret: e.currentTarget.value })}
            />
            <button type="button" className="flxhl-btn" onClick={() => onChange({ secret: newSecret(), clearSecret: false })}>
              {t('Generate')}
            </button>
            {draft.secret && (
              <button type="button" className="flxhl-btn" onClick={() => copy(draft.secret)}>
                {t('Copy')}
              </button>
            )}
          </div>
          {err('secret') ? <small className="flxhl-err">{err('secret')}</small> : <small>{draft.secret ? t('Copy it into the front end (FLUX_REVALIDATE_SECRET) now; it is stored encrypted and never shown again.') : t('Signs every call (HMAC-SHA256) and preview links.')}</small>}
          {saved?.secretSet && (
            <Check checked={draft.clearSecret} onChange={(v) => onChange({ clearSecret: v, secret: '' })}>
              {t('Remove the saved secret')}
            </Check>
          )}
        </label>
        {field('previewPath', t('Preview path'), <input type="text" className="code" value={draft.previewPath} onChange={(e) => onChange({ previewPath: e.currentTarget.value })} placeholder="/api/preview" />, t('The editor\'s Preview button opens this path on the default front end with a signed link. Empty: previews stay on WordPress.'))}
      </div>
      <div className="flxhl-modal-actions">
        <button type="button" className="flxhl-btn" onClick={onCancel}>
          {t('Cancel')}
        </button>
        <button type="submit" className="flxhl-btn is-primary" disabled={busy}>
          {busy ? t('Saving…') : t('Save')}
        </button>
      </div>
    </form>
  );
}

function DeliveryRow({ d, t }: { d: Delivery; t: Translate }) {
  const event = d.event === 'test' ? t('Test') : d.event === 'all' ? t('Refresh all') : t('Content changed');
  return (
    <tr>
      <td data-label={t('Time')} title={d.time.iso}>{d.time.label}</td>
      <td data-label={t('Front end')}>{d.frontendName}</td>
      <td data-label={t('Event')}>{event}</td>
      <td data-label={t('Tags')}>
        <span className="flxhl-tags" title={[...d.tags, ...d.paths].join('\n')}>
          {d.tags.slice(0, 3).join(', ')}
          {d.tags.length > 3 ? ` +${d.tags.length - 3}` : ''}
        </span>
      </td>
      <td data-label={t('Result')}>
        <span>
        <span className={'flxhl-badge ' + (d.ok ? 'is-ok' : 'is-bad')}>{d.status ? `HTTP ${d.status}` : d.ok ? t('Sent') : t('Failed')}</span> <span className="flxhl-muted-inline">{d.message} · {d.ms} ms</span>
        </span>
      </td>
    </tr>
  );
}

function Copyable({ label, value, t }: { label: string; value: string; t: Translate }) {
  const [done, setDone] = useState(false);
  return (
    <div className="flxhl-conn__item">
      <span>{label}</span>
      <div className="flxhl-copy">
        <code>{value}</code>
        <button
          type="button"
          className="flxhl-btn"
          onClick={() => {
            copy(value);
            setDone(true);
            setTimeout(() => setDone(false), 1500);
          }}
        >
          {done ? t('Copied') : t('Copy')}
        </button>
      </div>
    </div>
  );
}

function Kpi({ icon, label, value, hint, cls = '' }: { icon: string; label: string; value: string; hint: string; cls?: string }) {
  return (
    <div className={'flxhl-kpi ' + cls}>
      <span className="flxhl-kpi__icon">
        <Icon name={icon} size={18} />
      </span>
      <span className="flxhl-kpi__body">
        <span className="flxhl-kpi__label">{label}</span>
        <span className="flxhl-kpi__value">{value}</span>
        <span className="flxhl-kpi__hint">{hint}</span>
      </span>
    </div>
  );
}
