// Flux Analytics › Settings: counting, exclusions, events, funnels (visual editor), display and retention,
// telemetry hub and the data actions. Same screen in wp-admin and the FluxWP app; saved with the
// FlxAnalyticsSettingsSave mutation (fields you do not send stay as they are).
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAnalyticsAggregate, FlxAnalyticsFastTest, FlxAnalyticsKokoImport, FlxAnalyticsReset, FlxAnalyticsSettings, FlxAnalyticsSettingsSave } from '@fluxwp/graphql/operations';
import type { FlxAnalyticsCollectMethod, FlxAnalyticsSettingsQuery, FlxAnalyticsStepType } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { ConfirmDialog, Icon, PromptDialog, Shell, useConfig, useFmt, withAnalytics } from './lib';

type S = FlxAnalyticsSettingsQuery['flxAnalyticsSettings'];
type Step = { label: string; type: FlxAnalyticsStepType; value: string; auto?: boolean };
type Funnel = { name: string; steps: Step[] };
type Notice = { kind: 'success' | 'error'; text: string };

const MAX_FUNNELS = 5;
const MAX_STEPS = 10;
const stripF = (f: S['funnels']): Funnel[] => f.map((x) => ({ name: x.name, steps: x.steps.map((s) => ({ label: s.label, type: s.type, value: s.value })) }));
const clone = <T,>(x: T): T => JSON.parse(JSON.stringify(x)) as T;

interface Draft {
  on: boolean;
  method: FlxAnalyticsCollectMethod;
  dnt: boolean;
  excludeRoles: string[];
  excludeIps: string;
  evOutbound: boolean;
  evDownload: boolean;
  evContact: boolean;
  evSearch: boolean;
  evDonation: boolean;
  postsColumn: boolean;
  adminbar: boolean;
  keepMonths: number;
  tmHub: boolean;
  funnels: Funnel[];
}

const draftOf = (s: S): Draft => ({
  on: s.on,
  method: s.method,
  dnt: s.dnt,
  excludeRoles: [...s.excludeRoles],
  excludeIps: s.excludeIps,
  evOutbound: s.evOutbound,
  evDownload: s.evDownload,
  evContact: s.evContact,
  evSearch: s.evSearch,
  evDonation: s.evDonation,
  postsColumn: s.postsColumn,
  adminbar: s.adminbar,
  keepMonths: s.keepMonths,
  tmHub: s.tmHub,
  funnels: stripF(s.funnels),
});

function Settings() {
  const { t, n } = useFmt();
  const cfg = useConfig();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAnalyticsSettings, {});
  const [draft, setDraft] = useState<Draft | null>(() => (q.data ? draftOf(q.data.flxAnalyticsSettings) : null));
  const [dirty, setDirty] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<'reset' | null>(null);
  const [fast, setFast] = useState<'ok' | 'err' | null>(null);
  const tested = useRef(false);

  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data.flxAnalyticsSettings));
  }, [q.data, dirty]);

  const told = (kind: Notice['kind'], text: string) => {
    setNotice({ kind, text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };
  const fail = (e: unknown) => told('error', e instanceof Error && e.message ? e.message : t('The action could not be completed.'));
  const put = (s: S) => qc.setQueryData<FlxAnalyticsSettingsQuery>(flxQueryKey(scope, FlxAnalyticsSettings, {}), { flxAnalyticsSettings: s });
  const stale = () => {
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxAnalyticsReport'] });
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxAnalyticsConfig'] });
  };

  const save = useFlxMutation(FlxAnalyticsSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxAnalyticsSettingsSave;
      if (p?.errors.length) return told('error', p.errors.map((e) => e.message).join(' '));
      if (p?.settings) put(p.settings);
      setDirty(false);
      stale();
      told('success', t('Settings saved.'));
    },
    onError: fail,
  });
  const aggregate = useFlxMutation(FlxAnalyticsAggregate, {
    onSuccess: (r) => {
      const p = r.flxAnalyticsAggregate;
      if (p?.settings) put(p.settings);
      stale();
      told('success', t('%s visit rows processed.', n(p?.processed ?? 0)));
    },
    onError: fail,
  });
  const reset = useFlxMutation(FlxAnalyticsReset, {
    onSuccess: (r) => {
      if (r.flxAnalyticsReset?.settings) put(r.flxAnalyticsReset.settings);
      stale();
      told('success', t('All statistics were deleted.'));
    },
    onError: fail,
  });
  const koko = useFlxMutation(FlxAnalyticsKokoImport, {
    onSuccess: (r) => {
      const p = r.flxAnalyticsKokoImport;
      if (p?.settings) put(p.settings);
      stale();
      told('success', t('Koko Analytics data imported: %s rows.', n(p?.rows ?? 0)));
    },
    onError: fail,
  });
  const fastMut = useFlxMutation(FlxAnalyticsFastTest);

  // The fast endpoint is tested every time Settings opens; the result is remembered on the server.
  useEffect(() => {
    if (!cfg.fastEndpoint || tested.current || !q.data) return;
    tested.current = true;
    fetch(cfg.fastEndpoint + '?test=1&_t=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
      .then((r) => (r.ok ? r.text() : ''))
      .catch(() => '')
      .then((txt) => {
        const ok = txt.trim() === 'ok';
        setFast(ok ? 'ok' : 'err');
        fastMut.mutate({ input: { ok } });
      });
  }, [cfg.fastEndpoint, q.data, fastMut]);

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <Shell title={t('Site Analytics settings')}><div className="flxan-banner">{q.error?.message || t('The action could not be completed.')}</div></Shell>;

  const s = q.data.flxAnalyticsSettings;
  const up = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft({ ...draft, [k]: v });
    setDirty(true);
  };
  const check = (k: keyof Draft, label: string) => (
    <label>
      <input type="checkbox" checked={draft[k] as boolean} onChange={(e) => up(k, e.target.checked as never)} /> {label}
    </label>
  );
  const row = (label: ReactNode, body: ReactNode, htmlFor?: string) => (
    <tr>
      <th scope="row">{htmlFor ? <label htmlFor={htmlFor}>{label}</label> : label}</th>
      <td>{body}</td>
    </tr>
  );
  const submit = () =>
    save.mutate({
      input: {
        on: draft.on,
        method: draft.method,
        dnt: draft.dnt,
        excludeRoles: draft.excludeRoles,
        excludeIps: draft.excludeIps,
        evOutbound: draft.evOutbound,
        evDownload: draft.evDownload,
        evContact: draft.evContact,
        evSearch: draft.evSearch,
        evDonation: draft.evDonation,
        postsColumn: draft.postsColumn,
        adminbar: draft.adminbar,
        keepMonths: Math.max(0, Math.min(120, Math.round(draft.keepMonths) || 0)),
        tmHub: draft.tmHub,
        funnels: draft.funnels.map((f) => ({ name: f.name, steps: f.steps.map((x) => ({ label: x.label, type: x.type, value: x.value })) })),
      },
    });
  const csv = (type: string) => cfg.exportUrl + (cfg.exportUrl.includes('?') ? '&' : '?') + 'type=' + type + '&range=all';

  return (
    <Shell
      title={t('Site Analytics settings')}
      notice={
        notice && (
          <div className={'flxan-notice is-' + notice.kind} role="status">
            <span>{notice.text}</span>
            <button type="button" className="flxan-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )
      }
    >
      <div className="flxan-settings">
        <div className="flxan-box">
          <h2>{t('Counting')}</h2>
          <table className="form-table" role="presentation">
            <tbody>
              {row(
                t('Count visits'),
                <>
                  <label>
                    <input type="checkbox" checked={draft.on} onChange={(e) => up('on', e.target.checked)} /> {t('On')}
                  </label>
                  <p className="description">{t('No IP address or browser data is stored. A visitor is recognised only within the same day by a random key that is deleted the next day. A cookie is set only for visitors who arrive via a referral link (?r=code); it holds only the code (30 days). Mention in your privacy policy that statistics are collected.')}</p>
                </>,
              )}
              {row(
                t('Collection method'),
                <>
                  <select value={draft.method} onChange={(e) => up('method', e.target.value as FlxAnalyticsCollectMethod)}>
                    <option value="AUTO">{t('Automatic (recommended)')}</option>
                    <option value="FAST">{t('Fast endpoint (WordPress is not loaded)')}</option>
                    <option value="REST">{t('WordPress REST')}</option>
                  </select>
                  <span className={'flxan-pill flxan-pill--' + (s.methodNow === 'FAST' ? 'ok' : 'info')}>{s.methodNow === 'FAST' ? t('Now: fast endpoint') : t('Now: REST')}</span>
                  {fast && <span className={'flxan-pill flxan-pill--' + fast}>{fast === 'ok' ? t('Fast endpoint works') : t('Fast endpoint is unreachable, REST will be used')}</span>}
                  <p className="description">{t('Automatic: the fast endpoint is tested when this page opens; if it fails, REST is used.')}</p>
                </>,
              )}
              {row(t('"Do Not Track"'), check('dnt', t('Do not count visitors whose browser sends "Do Not Track" or GPC')))}
            </tbody>
          </table>
        </div>

        <div className="flxan-box">
          <h2>{t('Exclusions')}</h2>
          <table className="form-table" role="presentation">
            <tbody>
              {row(
                t('Roles'),
                <div className="flxan-roles">
                  {s.roles.map((r) => (
                    <label key={r.value}>
                      <input type="checkbox" checked={draft.excludeRoles.includes(r.value)} onChange={(e) => up('excludeRoles', e.target.checked ? [...draft.excludeRoles, r.value] : draft.excludeRoles.filter((x) => x !== r.value))} /> {r.label}
                    </label>
                  ))}
                  <p className="description">{t('Logged-in people with these roles are not counted. The browser you open this screen in is not counted either (even after logging out).')}</p>
                </div>,
              )}
              {row(
                t('IP addresses'),
                <>
                  <textarea id="flxan-ips" rows={3} className="large-text code" placeholder={'85.105.1.2\n10.0.0.0/8\n192.168.1.*'} value={draft.excludeIps} onChange={(e) => up('excludeIps', e.target.value)} />
                  <p className="description">{t('One address per line, "*" or CIDR (e.g. office network).')}</p>
                </>,
                'flxan-ips',
              )}
            </tbody>
          </table>
        </div>

        <div className="flxan-box">
          <h2>{t('Events')}</h2>
          <table className="form-table" role="presentation">
            <tbody>
              {row(
                t('Tracked automatically'),
                <div className="flxan-roles">
                  {check('evOutbound', t('Outbound link clicks'))}
                  {check('evDownload', t('File downloads (PDF, ZIP, DOCX…)'))}
                  {check('evContact', t('Phone, email and WhatsApp clicks'))}
                  {check('evSearch', t('Site searches (search term)'))}
                  {check('evDonation', t('Paid donations and orders (WooCommerce; type and amount)'))}
                  <p className="description">{t('Your own event: add data-flxan-event="name" (and optional data-flxan-param) to a link, or call flxan.track("name", "detail") in JavaScript. PHP: flxan_track_event( "name", "detail", amount ).')}</p>
                </div>,
              )}
            </tbody>
          </table>
        </div>

        <div className="flxan-box" id="flxan-funnels">
          <h2>{t('Funnels')}</h2>
          <p className="description">{t('Shows how far visitors get. Each step can be a page (all pages starting with that path), an event (e.g. the "Pay" click) or the site entry. Up to 5 funnels with 10 steps each. Changes apply to new visits; funnel steps can also be picked as goals on the Journeys screen.')}</p>
          <FunnelEditor value={draft.funnels} onChange={(f) => up('funnels', f)} s={s} />
        </div>

        <div className="flxan-box">
          <h2>{t('Display and retention')}</h2>
          <table className="form-table" role="presentation">
            <tbody>
              {row(
                t('Admin screens'),
                <div className="flxan-roles">
                  {check('postsColumn', t('"Views" column in the post and page lists'))}
                  {check('adminbar', t('Page views in the admin bar while browsing the site'))}
                </div>,
              )}
              {row(
                t('Retention'),
                <>
                  <input id="flxan-keep" type="number" min={0} max={120} className="small-text" value={draft.keepMonths} onChange={(e) => up('keepMonths', Number(e.target.value))} /> {t('months (0 = unlimited)')}
                  <p className="description">{t('Daily summaries take little space; keeping them forever is usually fine.')}</p>
                </>,
                'flxan-keep',
              )}
            </tbody>
          </table>
        </div>

        <div className="flxan-box" id="flxan-tm">
          <h2>{t('Telemetry hub')}</h2>
          <table className="form-table" role="presentation">
            <tbody>
              {row(
                t('Receive data from FluxWP installs'),
                <>
                  <label>
                    <input type="checkbox" checked={draft.tmHub} onChange={(e) => up('tmHub', e.target.checked)} /> {t('On')}
                  </label>
                  <p className="description">{t('Sites that tick "Help us improve the product" in FluxWP Panel send anonymous usage data once a day: which FluxWP plugins are used together, versions, FluxWP PHP errors and setup-wizard progress. IP and site address are not stored; records are kept for 90 days. When enabled, "Product telemetry" appears in the menu.')}</p>
                  {s.hubEndpoint && (
                    <p>
                      {t('Collection URL (FluxWP Panel › Settings › Telemetry URL):')} <code>{s.hubEndpoint}</code>
                    </p>
                  )}
                </>,
              )}
            </tbody>
          </table>
        </div>

        <p className="flxan-savebar">
          <button type="button" className="flxan-btn flxan-btn--primary" disabled={save.isPending} onClick={submit}>
            {t('Save Changes')}
          </button>
          {dirty && <span className="flxan-muted"> {t('Unsaved changes')}</span>}
        </p>

        <div className="flxan-box">
          <h2>{t('Data')}</h2>
          <p>{s.lastAggregated ? t('Last processed %1$s. Waiting in queue: %2$s.', s.lastAggregated.relative, s.pendingLabel) : t('No visits processed yet.')}</p>
          <p className="flxan-actions">
            <button type="button" className="flxan-btn" disabled={aggregate.isPending} onClick={() => aggregate.mutate({ input: {} })}>
              {t('Process now')}
            </button>
            <a className="flxan-btn" href={csv('site')}>
              {t('Download daily totals (CSV)')}
            </a>
            <a className="flxan-btn" href={csv('pages')}>
              {t('Download pages (CSV)')}
            </a>
          </p>
          {s.koko && (
            <div className="flxan-inline">
              <p>{t('Koko Analytics data found: %1$s days (%2$s – %3$s). Days before Flux Analytics was installed are imported; Koko tables are not modified.', n(s.koko.days), s.koko.first, s.koko.last)}</p>
              <button type="button" className="flxan-btn" disabled={koko.isPending} onClick={() => koko.mutate({ input: {} })}>
                {t('Import Koko Analytics data')}
              </button>
            </div>
          )}
          <p className="flxan-inline flxan-danger">
            <button type="button" className="flxan-link-danger" onClick={() => setConfirm('reset')}>
              {t('Delete all statistics')}
            </button>
          </p>
        </div>

        <div className="flxan-box">
          <h2>{t('Shortcodes and AI')}</h2>
          <p>
            <code>[flx_analiz_populer sayi="5" gun="30" tur="post" baslik="En çok okunanlar"]</code> — {t('list of the most viewed posts.')}
          </p>
          <p>
            <code>[flx_analiz_sayac]</code> — {t('total views of this page (gun="30" for the last 30 days).')}
          </p>
          <p>{t('Flux AI chat and MCP clients (Claude and others) read stats with: flxan_summary, flxan_timeseries, flxan_pages, flxan_referrers, flxan_breakdown, flxan_events, flxan_realtime, flxan_page, flxan_compare, flxan_funnel, flxan_journeys, flxan_refs, flxan_ref_save. Access needs the "View statistics" capability (flxan_view), which Flux Roles can grant.')}</p>
        </div>
      </div>
      {confirm === 'reset' && <ConfirmDialog danger text={t('All statistics will be permanently deleted. Are you sure?')} action={t('Delete all statistics')} onYes={() => reset.mutate({ input: {} })} onClose={() => setConfirm(null)} />}
    </Shell>
  );
}

export const SettingsScreen = withAnalytics(Settings);

/* ------------------------------------------------------------- funnel editor */

const TYPES: { key: FlxAnalyticsStepType; icon: string; label: string }[] = [
  { key: 'ANY', icon: 'enter', label: 'Site entry' },
  { key: 'PATH', icon: 'page', label: 'Page' },
  { key: 'EVENT', icon: 'bolt', label: 'Event' },
];

/** "# name" then "label | value" lines: * = site entry, olay:x = event, otherwise a path. */
const toText = (F: Funnel[]) => F.map((f) => '# ' + f.name + '\n' + f.steps.map((s) => s.label + ' | ' + (s.type === 'ANY' ? '*' : s.type === 'EVENT' ? 'olay:' + s.value : s.value)).join('\n')).join('\n\n');
function fromText(text: string): Funnel[] {
  const out: Funnel[] = [];
  let cur: Funnel | null = null;
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (!line) continue;
    if (line.startsWith('#')) {
      cur = { name: line.replace(/^#\s*/, ''), steps: [] };
      out.push(cur);
      continue;
    }
    if (!cur) {
      cur = { name: '', steps: [] };
      out.push(cur);
    }
    const [label = '', ...rest] = line.split('|');
    const v = rest.join('|').trim();
    cur.steps.push({ label: label.trim(), type: v === '*' || v === '' ? 'ANY' : v.startsWith('olay:') ? 'EVENT' : 'PATH', value: v === '*' ? '' : v.replace(/^olay:/, '') });
  }
  return out.slice(0, MAX_FUNNELS);
}

function FunnelEditor({ value, onChange, s }: { value: Funnel[]; onChange: (f: Funnel[]) => void; s: S }) {
  const { t } = useFmt();
  const [text, setText] = useState<string | null>(null);
  const [ask, setAsk] = useState<{ fi: number; si: number } | null>(null);
  const [del, setDel] = useState<number | null>(null);
  const [reset, setReset] = useState(false);
  const [drag, setDrag] = useState<{ fi: number; si: number; over?: number } | null>(null);
  const presets: Step[] = (s.funnelDefaults[0]?.steps ?? []).map((x) => ({ label: x.label, type: x.type, value: x.value }));
  const events = new Map(s.funnelEvents.map((e) => [e.value, e.label]));
  const pageTitle = new Map(s.funnelPages.map((p) => [p.path, p.title]));
  const upd = (fn: (f: Funnel[]) => void) => {
    const next = clone(value);
    fn(next);
    onChange(next);
  };
  const setStep = (fi: number, si: number, p: Partial<Step>) => upd((f) => void Object.assign(f[fi]!.steps[si]!, p));

  if (text !== null) {
    return (
      <div className="flxan-fed">
        <textarea rows={9} className="large-text code flxan-ftext" value={text} onChange={(e) => setText(e.target.value)} />
        <div className="flxan-fed__foot">
          <button
            type="button"
            className="flxan-btn flxan-btn--ghost"
            onClick={() => {
              const f = fromText(text);
              if (f.length) onChange(f);
              setText(null);
            }}
          >
            {t('Back to the visual editor')}
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flxan-fed">
      <datalist id="flxan-fpages">
        {s.funnelPages.map((p) => (
          <option key={p.path} value={p.path}>
            {p.title}
          </option>
        ))}
      </datalist>
      {value.map((f, fi) => (
        <div key={fi} className="flxan-fcard">
          <div className="flxan-fcard__head">
            <span className="flxan-fcard__badge">{fi + 1}</span>
            <input className="flxan-fcard__name" value={f.name} aria-label={t('Funnel name')} placeholder={t('Funnel name')} onChange={(e) => upd((x) => void (x[fi]!.name = e.target.value))} />
            <span className="flxan-muted">{t('%s steps', f.steps.length)}</span>
            {value.length > 1 && (
              <button type="button" className="flxan-ico" title={t('Delete funnel')} aria-label={t('Delete funnel')} onClick={() => setDel(fi)}>
                🗑
              </button>
            )}
          </div>
          <ol className="flxan-fsteps">
            {f.steps.map((st, si) => {
              const w = Math.max(28, 100 - si * (60 / Math.max(1, f.steps.length - 1)));
              const parts = st.value.split(':');
              const evName = parts.shift() ?? '';
              const full = evName + (parts.length ? ':' + parts.join(':') : '');
              return (
                <li
                  key={si}
                  className={'flxan-fs flxan-fs--' + st.type.toLowerCase() + (drag?.fi === fi && drag.si === si ? ' dragging' : '') + (drag?.fi === fi && drag.over === si ? ' over' : '')}
                  draggable={drag?.fi === fi && drag.si === si}
                  onDragStart={(e) => {
                    try {
                      e.dataTransfer.setData('text/plain', '');
                      e.dataTransfer.effectAllowed = 'move';
                    } catch {
                      /* some browsers refuse */
                    }
                  }}
                  onDragOver={(e) => {
                    if (drag && drag.fi === fi) {
                      e.preventDefault();
                      if (drag.over !== si) setDrag({ ...drag, over: si });
                    }
                  }}
                  onDrop={(e) => {
                    if (!drag || drag.fi !== fi) return;
                    e.preventDefault();
                    const from = drag.si;
                    setDrag(null);
                    if (from !== si) upd((x) => void x[fi]!.steps.splice(si, 0, x[fi]!.steps.splice(from, 1)[0]!));
                  }}
                  onDragEnd={() => setDrag(null)}
                >
                  <span className="flxan-fs__grip" title={t('Drag to move')} aria-hidden="true" onPointerDown={() => setDrag({ fi, si })}>
                    ⋮⋮
                  </span>
                  <span className="flxan-fs__num" style={{ ['--w' as string]: w + '%' }}>
                    {si + 1}
                  </span>
                  <div className="flxan-fs__body">
                    <input className="flxan-fs__label" value={st.label} placeholder={t('Step name')} aria-label={t('Step name')} onChange={(e) => setStep(fi, si, { label: e.target.value, auto: false })} />
                    <div className="flxan-fs__row">
                      <div className="flxan-seg" role="radiogroup" aria-label={t('Step type')}>
                        {TYPES.map((ty) => (
                          <button
                            key={ty.key}
                            type="button"
                            role="radio"
                            aria-checked={st.type === ty.key}
                            onClick={() => {
                              if (st.type === ty.key) return;
                              setStep(fi, si, {
                                type: ty.key,
                                value: ty.key === 'PATH' ? (st.value.startsWith('/') ? st.value : '') : '',
                                ...(ty.key === 'ANY' && (!st.label || st.auto) ? { label: t('Site entry'), auto: true } : {}),
                              });
                            }}
                          >
                            <Icon name={ty.icon} size={14} />
                            {t(ty.label)}
                          </button>
                        ))}
                      </div>
                      {st.type === 'ANY' && <div className="flxan-fval flxan-fval--any">{t('The first page of every visit counts')}</div>}
                      {st.type === 'EVENT' && (
                        <div className="flxan-fval">
                          <select
                            value={full}
                            onChange={(e) => {
                              if (e.target.value === '__custom') return setAsk({ fi, si });
                              setStep(fi, si, { value: e.target.value, ...(e.target.value && (!st.label || st.auto) ? { label: events.get(e.target.value) ?? e.target.value, auto: true } : {}) });
                            }}
                          >
                            {!full && <option value="">{t('Choose an event…')}</option>}
                            {full && !events.has(full) && <option value={full}>{full}</option>}
                            {[...events.entries()].map(([k, l]) => (
                              <option key={k} value={k}>
                                {l} ({k})
                              </option>
                            ))}
                            <option value="__custom">{t('Type another event…')}</option>
                          </select>
                        </div>
                      )}
                      {st.type === 'PATH' && (
                        <div className="flxan-fval">
                          <input
                            list="flxan-fpages"
                            value={st.value}
                            placeholder="/donate/"
                            spellCheck={false}
                            autoComplete="off"
                            onChange={(e) => {
                              const v = e.target.value.trim();
                              const title = pageTitle.get(v);
                              setStep(fi, si, { value: v, ...(title && (!st.label || st.auto) ? { label: title, auto: true } : {}) });
                            }}
                          />
                          <span className="flxan-fval__hint">
                            {pageTitle.get(st.value) ? pageTitle.get(st.value) + ' · ' : ''}
                            {t('all pages starting with this path')}
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="flxan-fs__acts">
                    <button type="button" className="flxan-ico" disabled={si === 0} aria-label={t('Move up')} title={t('Move up')} onClick={() => upd((x) => void x[fi]!.steps.splice(si - 1, 0, x[fi]!.steps.splice(si, 1)[0]!))}>
                      ↑
                    </button>
                    <button type="button" className="flxan-ico" disabled={si >= f.steps.length - 1} aria-label={t('Move down')} title={t('Move down')} onClick={() => upd((x) => void x[fi]!.steps.splice(si + 1, 0, x[fi]!.steps.splice(si, 1)[0]!))}>
                      ↓
                    </button>
                    <button type="button" className="flxan-ico flxan-ico--del" disabled={f.steps.length <= 1} aria-label={t('Delete step')} title={t('Delete step')} onClick={() => upd((x) => void x[fi]!.steps.splice(si, 1))}>
                      ✕
                    </button>
                  </div>
                </li>
              );
            })}
          </ol>
          <div className="flxan-fcard__foot">
            {f.steps.length < MAX_STEPS ? (
              <>
                <button type="button" className="flxan-btn" onClick={() => upd((x) => void x[fi]!.steps.push({ label: '', type: 'PATH', value: '' }))}>
                  + {t('Add step')}
                </button>
                <select
                  className="flxan-fpreset"
                  aria-label={t('Add a ready-made step')}
                  value=""
                  onChange={(e) => e.target.value !== '' && upd((x) => void x[fi]!.steps.push(clone(presets[Number(e.target.value)]!)))}
                >
                  <option value="">{t('Add a ready-made step…')}</option>
                  {presets.map((p, i) => (
                    <option key={i} value={i}>
                      {p.label}
                    </option>
                  ))}
                </select>
              </>
            ) : (
              <span className="flxan-muted">{t('At most %s steps.', MAX_STEPS)}</span>
            )}
          </div>
        </div>
      ))}
      <div className="flxan-fed__foot">
        {value.length < MAX_FUNNELS && (
          <button type="button" className="flxan-btn" onClick={() => upd((x) => void x.push({ name: t('Funnel %s', x.length + 1), steps: [{ label: t('Site entry'), type: 'ANY', value: '' }, { label: '', type: 'PATH', value: '' }] }))}>
            + {t('New funnel')}
          </button>
        )}
        <button type="button" className="flxan-btn" onClick={() => setReset(true)}>
          {t('Back to the default donation funnel')}
        </button>
        <button type="button" className="flxan-btn flxan-btn--ghost" onClick={() => setText(toText(value))}>
          {t('Edit as text')}
        </button>
      </div>
      {ask && (
        <PromptDialog
          title={t('Event name (e.g. donation_form or checkout:submit):')}
          value={value[ask.fi]?.steps[ask.si]?.value ?? ''}
          onDone={(v) => {
            const val = v.trim().replace(/^olay:/, '');
            if (val) setStep(ask.fi, ask.si, { value: val, ...(!value[ask.fi]?.steps[ask.si]?.label || value[ask.fi]?.steps[ask.si]?.auto ? { label: events.get(val) ?? val, auto: true } : {}) });
          }}
          onClose={() => setAsk(null)}
        />
      )}
      {del !== null && <ConfirmDialog danger text={t('Delete the funnel "%s"?', value[del]?.name ?? '')} action={t('Delete funnel')} onYes={() => upd((x) => void x.splice(del, 1))} onClose={() => setDel(null)} />}
      {reset && <ConfirmDialog danger text={t('Replace all funnels with the default donation funnel? (Applies when you press Save.)')} action={t('Replace')} onYes={() => onChange(stripF(s.funnelDefaults))} onClose={() => setReset(false)} />}
    </div>
  );
}
