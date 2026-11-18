// Settings › Admin theme. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux Admin GraphQL operations (plugins/flx-admin/graphql/operations).
// It is the only admin page (no classic page): General, Appearance, Menu and bottom bar, Permissions,
// Dashboard, App (PWA) and the System check link.
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAdminSettingsScreen, FlxAdminSettingsUpdate } from '@fluxwp/graphql/operations';
import type {
  FlxAdminDashStat,
  FlxAdminDensity,
  FlxAdminFont,
  FlxAdminLogoStyle,
  FlxAdminMode,
  FlxAdminRadius,
  FlxAdminSettingsScreenQuery,
  FlxAdminSettingsUpdateInput,
} from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { CapsTable } from './CapsTable';
import { translator, type Translate } from './i18n';
import { MenuEditor, type PanelProps } from './MenuEditor';
import './admin.css';

type Settings = FlxAdminSettingsScreenQuery['flxAdminSettings'];
type Config = FlxAdminSettingsScreenQuery['flxAdminSettingsConfig'];
type Notice = { kind: 'success' | 'error'; text: string };
type Tab = 'general' | 'look' | 'menu' | 'caps' | 'dashboard' | 'pwa' | 'system';
type Mode = 'light' | 'dark';
type ColorKey = 'bg' | 'side' | 'surface' | 'text';

const TABS: Tab[] = ['general', 'look', 'menu', 'caps', 'dashboard', 'pwa', 'system'];
const SAVED_FLAG = 'flxad-saved';

/** Draft of the settings the four form tabs edit; the logo is a picked file or "removed". */
interface Draft {
  s: Settings;
  logoFile: File | null;
  logoPreview: string | null;
  logoRemoved: boolean;
}

const draftOf = (s: Settings): Draft => ({ s: structuredClone(s), logoFile: null, logoPreview: null, logoRemoved: false });

export function AdminScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Admin />
    </FlxGraphQLProvider>
  );
}

function Admin() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const q = useFlxQuery(FlxAdminSettingsScreen, {});
  const [tab, setTabState] = useState<Tab>(() => {
    const v = new URLSearchParams(location.search).get('tab') ?? host.startPage;
    return TABS.includes(v as Tab) ? (v as Tab) : 'general';
  });
  const [seen, setSeen] = useState<ReadonlySet<Tab>>(() => new Set([tab]));
  const [notice, setNotice] = useState<Notice | null>(null);
  const [dirty, setDirty] = useState<Record<string, boolean>>({});
  const anyDirty = Object.values(dirty).some(Boolean);

  useEffect(() => {
    try {
      if (sessionStorage.getItem(SAVED_FLAG)) {
        sessionStorage.removeItem(SAVED_FLAG);
        setNotice({ kind: 'success', text: t('Saved.') });
      }
    } catch {
      /* private mode: no notice */
    }
  }, [t]);
  useEffect(() => {
    if (!anyDirty) return;
    const warn = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [anyDirty]);

  const setTab = (next: Tab) => {
    setTabState(next);
    setSeen((s) => new Set(s).add(next));
    setNotice(null);
    try {
      const u = new URL(location.href);
      u.searchParams.set('tab', next);
      history.replaceState(null, '', u);
    } catch {
      /* the address stays */
    }
  };
  const markDirty = useCallback((key: string) => (v: boolean) => setDirty((d) => (d[key] === v ? d : { ...d, [key]: v })), []);
  const dirtyForm = useMemo(() => markDirty('form'), [markDirty]);
  const dirtyMenu = useMemo(() => markDirty('menu'), [markDirty]);
  const dirtyCaps = useMemo(() => markDirty('caps'), [markDirty]);

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) {
    return (
      <div className="flxad-app">
        <div className="flxad-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>
      </div>
    );
  }
  const { flxAdminSettings: settings, flxAdminSettingsConfig: config } = q.data;
  const labels: Record<Tab, string> = {
    general: t('General'),
    look: t('Appearance'),
    menu: t('Menu and bottom bar'),
    caps: t('Permissions'),
    dashboard: t('Dashboard'),
    pwa: t('App (PWA)'),
    system: t('System check'),
  };
  // The system check lives in Flux Speed (Performance › System check); without it there is no tab.
  const tabs = TABS.filter((k) => k !== 'system' || config.systemUrl);
  const panel: Omit<PanelProps, 'onDirty' | 'onOpenCaps'> = { t, active: true, onNotice: setNotice };

  return (
    <div className="flxad-app">
      <div className="flxad-head">
        <h1 className="flxad-title">
          {host.appIcon && <img src={host.appIcon} alt="" width={32} height={32} />}
          {t('Admin theme')}
        </h1>
      </div>
      {notice && (
        <div className={'flxad-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxad-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      <div className="flxad-tabs" role="tablist">
        {tabs.map((k) => (
          <button key={k} type="button" role="tab" className="flxad-tab" aria-selected={tab === k} onClick={() => setTab(k)}>
            {labels[k]}
          </button>
        ))}
      </div>

      <SettingsForm settings={settings} config={config} tab={tab} t={t} locale={i18n.language} onDirty={dirtyForm} onNotice={setNotice} />
      {seen.has('menu') && (
        <div hidden={tab !== 'menu'}>
          <p className="flxad-lead">{t('Each role sees its own side menu and mobile bottom bar. Drag to reorder, rename, hide, group. Hiding does not change permissions; it only removes the item from the menu.')}</p>
          <MenuEditor {...panel} active={tab === 'menu'} onDirty={dirtyMenu} onOpenCaps={() => setTab('caps')} />
          <p className="flxad-desc">{t('"Block access" really closes that menu for this role: it isn\'t shown, and opening it by URL shows a "no permission" page. See the Permissions tab for detailed permissions.')}</p>
        </div>
      )}
      {seen.has('caps') && (
        <div hidden={tab !== 'caps'}>
          <CapsTable {...panel} active={tab === 'caps'} onDirty={dirtyCaps} />
        </div>
      )}
      {tab === 'system' && (
        <div className="flxad-card">
          <h2>{t('System check')}</h2>
          <p className="flxad-lead">
            <a className="flxad-btn" href={config.systemUrl || '#'}>
              {t('Open the system check')}
            </a>
          </p>
        </div>
      )}
    </div>
  );
}

const clean = (v: string) => v.trim();

function SettingsForm({ settings, config, tab, t, locale, onDirty, onNotice }: { settings: Settings; config: Config; tab: Tab; t: Translate; locale: string; onDirty: (v: boolean) => void; onNotice: (n: Notice) => void }) {
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [d, setD] = useState(() => draftOf(settings));
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  const s = d.s;

  useEffect(() => {
    if (!dirty) setD(draftOf(settings));
  }, [settings, dirty]);
  useEffect(() => onDirty(dirty), [dirty, onDirty]);
  useEffect(() => () => void (d.logoPreview && URL.revokeObjectURL(d.logoPreview)), [d.logoPreview]);

  const set = <K extends keyof Settings>(k: K, v: Settings[K]) => {
    setD((o) => ({ ...o, s: { ...o.s, [k]: v } }));
    setDirty(true);
  };
  const setTheme = (part: Partial<Settings['theme']>) => {
    setD((o) => ({ ...o, s: { ...o.s, theme: { ...o.s.theme, ...part } } }));
    setDirty(true);
  };
  const setColor = (m: Mode, k: ColorKey, v: string) => {
    setD((o) => ({ ...o, s: { ...o.s, theme: { ...o.s.theme, [m]: { ...o.s.theme[m], [k]: v } } } }));
    setDirty(true);
  };
  const pickLogo = (f: File | null) => {
    if (!f) return;
    setD((o) => ({ ...o, logoFile: f, logoPreview: URL.createObjectURL(f), logoRemoved: false }));
    setDirty(true);
  };

  const update = useFlxMutation(FlxAdminSettingsUpdate);
  const submit = async () => {
    setBusy(true);
    try {
      const input: FlxAdminSettingsUpdateInput = {
        enabled: s.enabled,
        brand: clean(s.brand),
        subtitle: clean(s.subtitle),
        accent: s.accent,
        mode: s.mode,
        darkExclude: s.darkExclude,
        lineIcons: s.lineIcons,
        logoStyle: s.logoStyle,
        bottomBar: s.bottomBar,
        appRail: s.appRail,
        appSwitcher: s.appSwitcher,
        fastNav: s.fastNav,
        palette: s.palette,
        login: s.login,
        heartbeat: s.heartbeat,
        dashHeader: s.dashHeader,
        dashStats: s.dashStats,
        quickLinks: s.quickLinks,
        pwa: s.pwa,
        pwaName: clean(s.pwaName),
        pwaShort: clean(s.pwaShort),
        theme: {
          radius: s.theme.radius,
          font: s.theme.font,
          fontCustom: clean(s.theme.fontCustom),
          fontSize: s.theme.fontSize,
          density: s.theme.density,
          light: s.theme.light,
          dark: s.theme.dark,
        },
      };
      if (d.logoFile) {
        const form = new FormData();
        form.append('file', d.logoFile);
        const up = await host.client<{ uploadId?: string }>('flx/v1/upload?context=flx-admin/logo', { method: 'POST', formData: form });
        if (!up?.uploadId) throw new Error(t('The action could not be completed.'));
        input.logoUploadId = up.uploadId;
      } else if (d.logoRemoved) {
        input.logoId = 0;
      }
      const r = (await update.mutateAsync({ input })).flxAdminSettingsUpdate;
      if (!r || r.errors.length || !r.settings || !r.config) {
        onNotice({ kind: 'error', text: r?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
        return;
      }
      qc.setQueryData<FlxAdminSettingsScreenQuery>(flxQueryKey(scope, FlxAdminSettingsScreen, {}), { flxAdminSettings: r.settings, flxAdminSettingsConfig: r.config });
      setDirty(false);
      onDirty(false);
      if (host.mode === 'wp') {
        // The side menu, colors and fonts are drawn by the page itself: reload so the new look shows.
        try {
          sessionStorage.setItem(SAVED_FLAG, '1');
        } catch {
          /* no flag, no notice */
        }
        location.reload();
        return;
      }
      onNotice({ kind: 'success', text: t('Saved.') });
    } catch (e) {
      onNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') });
    } finally {
      setBusy(false);
    }
  };

  if (tab === 'menu' || tab === 'caps' || tab === 'system') return null;
  const c = s.theme;
  const cd = config.colorDefaults;
  const colorOf = (m: Mode, k: ColorKey) => c[m][k] || cd[m][k];
  const logoSrc = d.logoRemoved ? '' : d.logoPreview || s.logoUrl;

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
    >
      {tab === 'general' && (
        <>
          <div className="flxad-card">
            <h2>{t('Brand')}</h2>
            <Row label={t('Admin theme')} htmlFor="flxad-enabled">
              <Check id="flxad-enabled" checked={s.enabled} onChange={(v) => set('enabled', v)} text={t('On (turning it off restores the standard WordPress dashboard)')} />
            </Row>
            <Row label={t('Name')} htmlFor="flxad-brand">
              <input id="flxad-brand" type="text" className="flxad-wide" value={s.brand} placeholder={config.siteName} onChange={(e) => set('brand', e.currentTarget.value)} />
            </Row>
            <Row label={t('Subtitle')} htmlFor="flxad-subtitle">
              <input id="flxad-subtitle" type="text" className="flxad-wide" value={s.subtitle} placeholder={t('Admin')} onChange={(e) => set('subtitle', e.currentTarget.value)} />
            </Row>
            <Row label={t('Logo')}>
              <div className="flxad-logo">
                {logoSrc ? <img src={logoSrc} alt="" width={48} height={48} /> : <span className="flxad-nologo" />}
                <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/gif,image/webp" className="flxad-sr" tabIndex={-1} onChange={(e) => pickLogo(e.currentTarget.files?.[0] ?? null)} />
                <button type="button" className="flxad-btn" onClick={() => fileRef.current?.click()}>
                  {t('Choose logo')}
                </button>
                {(s.logo || d.logoFile) && !d.logoRemoved && (
                  <button
                    type="button"
                    className="flxad-linkbtn"
                    onClick={() => {
                      setD((o) => ({ ...o, logoFile: null, logoPreview: null, logoRemoved: true }));
                      setDirty(true);
                    }}
                  >
                    {t('Remove')}
                  </button>
                )}
              </div>
              <p className="flxad-desc">{t('If empty, the site icon is used; if there is none, the Flux Admin icon.')}</p>
            </Row>
          </div>
          <div className="flxad-card">
            <h2>{t('Options')}</h2>
            <Row label={t('Features')}>
              <Check checked={s.bottomBar} onChange={(v) => set('bottomBar', v)} text={t('Bottom bar on mobile')} block />
              <Check checked={s.appRail} onChange={(v) => set('appRail', v)} text={t('Open WhatsApp and Tasks right next to the side menu (menu shrinks to icons)')} block />
              <Check checked={s.appSwitcher} onChange={(v) => set('appSwitcher', v)} text={t('Show the app switcher on WhatsApp and Tasks screens on phones')} block />
              <Check checked={s.fastNav} onChange={(v) => set('fastNav', v)} text={t('Fast navigation: the clicked menu page is prepared in advance, transitions are smooth')} block />
              <Check checked={s.palette} onChange={(v) => set('palette', v)} text={t('Command palette (Ctrl+K / ⌘K)')} block />
              <Check checked={s.login} onChange={(v) => set('login', v)} text={t('Style the login screen too')} block />
            </Row>
            <Row label={t('Live counter interval')} htmlFor="flxad-hb">
              <select id="flxad-hb" value={s.heartbeat} onChange={(e) => set('heartbeat', Number(e.currentTarget.value))}>
                {[30, 60, 120].map((n) => (
                  <option key={n} value={n}>
                    {t('%d s', n)}
                  </option>
                ))}
              </select>
              <p className="flxad-desc">{t('Badges (WhatsApp, tasks, pending donations, updates) ride on the WordPress Heartbeat request; no extra requests. 60 s is recommended for Hostinger.')}</p>
            </Row>
          </div>
        </>
      )}

      {tab === 'look' && (
        <div className="flxad-card">
          <h2>{t('Theme')}</h2>
          <Row label={t('Default theme')}>
            {(
              [
                ['AUTO', t('Automatic (device setting)')],
                ['LIGHT', t('Light')],
                ['DARK', t('Dark')],
              ] as [FlxAdminMode, string][]
            ).map(([k, l]) => (
              <Radio key={k} name="flxad-mode" checked={s.mode === k} onChange={() => set('mode', k)} text={l} />
            ))}
            <p className="flxad-desc">{t('Everyone can change their own preference with the moon/sun button at the bottom left.')}</p>
          </Row>
          <Row label={t('Accent color')} htmlFor="flxad-accent">
            <input id="flxad-accent" type="color" value={s.accent || config.defaultAccent} onChange={(e) => set('accent', e.currentTarget.value)} /> <code className="flxad-code">{s.accent || config.defaultAccent}</code>
          </Row>
          <Row label={t('Colors')}>
            <table className="flxad-colors">
              <thead>
                <tr>
                  <th />
                  <th>{t('Light')}</th>
                  <th>{t('Dark')}</th>
                </tr>
              </thead>
              <tbody>
                {(
                  [
                    ['bg', t('Background')],
                    ['side', t('Side menu')],
                    ['surface', t('Cards')],
                    ['text', t('Text')],
                  ] as [ColorKey, string][]
                ).map(([k, l]) => (
                  <tr key={k}>
                    <th scope="row">{l}</th>
                    {(['light', 'dark'] as Mode[]).map((m) => (
                      <td key={m}>
                        <input type="color" aria-label={l + ' · ' + (m === 'light' ? t('Light') : t('Dark'))} value={colorOf(m, k)} onChange={(e) => setColor(m, k, e.currentTarget.value)} />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
            <button
              type="button"
              className="flxad-linkbtn"
              onClick={() => {
                const blank = { bg: '', side: '', surface: '', text: '' };
                setTheme({ light: blank, dark: blank });
              }}
            >
              {t('Reset colors to default')}
            </button>
          </Row>
          <Row label={t('Font')} htmlFor="flxad-font">
            <select id="flxad-font" value={c.font} onChange={(e) => setTheme({ font: e.currentTarget.value as FlxAdminFont })}>
              <option value="FIGTREE">Figtree</option>
              <option value="SYSTEM">{t('System font')}</option>
              <option value="CUSTOM">{t('Custom…')}</option>
            </select>{' '}
            <input type="text" className="flxad-wide" aria-label={t('Custom…')} value={c.fontCustom} placeholder={'Inter, "Open Sans"'} onChange={(e) => setTheme({ fontCustom: e.currentTarget.value })} />
            <p className="flxad-desc">{t('Custom: the name of a font installed on the computer or loaded on the site.')}</p>
          </Row>
          <Row label={t('Font size')} htmlFor="flxad-fs">
            <select id="flxad-fs" value={c.fontSize} onChange={(e) => setTheme({ fontSize: Number(e.currentTarget.value) })}>
              {(
                [
                  [13, t('Normal')],
                  [14, t('Large')],
                  [15, t('Larger')],
                ] as [number, string][]
              ).map(([n, l]) => (
                <option key={n} value={n}>
                  {l + ' (' + n + 'px)'}
                </option>
              ))}
            </select>
          </Row>
          <Row label={t('Corners')}>
            {(
              [
                ['NONE', 'none', t('Sharp')],
                ['SMALL', 'small', t('Slightly rounded')],
                ['MEDIUM', 'medium', t('Rounded')],
                ['LARGE', 'large', t('Very rounded')],
              ] as [FlxAdminRadius, string, string][]
            ).map(([k, css, l]) => (
              <Radio key={k} name="flxad-radius" checked={c.radius === k} onChange={() => setTheme({ radius: k })} text={l} extra={<i className={'flxad-rp flxad-rp-' + css} />} />
            ))}
          </Row>
          <Row label={t('Density')}>
            {(
              [
                ['COMFORTABLE', t('Comfortable')],
                ['COMPACT', t('Compact (more rows in the menu)')],
              ] as [FlxAdminDensity, string][]
            ).map(([k, l]) => (
              <Radio key={k} name="flxad-density" checked={c.density === k} onChange={() => setTheme({ density: k })} text={l} />
            ))}
          </Row>
          <Row label={t('Icons')}>
            <Check checked={s.lineIcons} onChange={(v) => set('lineIcons', v)} text={t('Line icons in WordPress menus (FluxWP logos stay in color)')} />
          </Row>
          <Row label={t('Package logos')}>
            {(
              [
                ['GRADIENT', t('Gradient (default)')],
                ['PASTEL', t('Pastel')],
              ] as [FlxAdminLogoStyle, string][]
            ).map(([k, l]) => (
              <Radio key={k} name="flxad-logostyle" checked={s.logoStyle === k} onChange={() => set('logoStyle', k)} text={l} />
            ))}
            <p className="flxad-desc">{t('Used in the side menu, the FluxWP Panel package list and FluxWP App. Colours show the package group: platform, maintenance, communication, content, commerce.')}</p>
          </Row>
          <Row label={t('Screens without dark theme')} htmlFor="flxad-dark-ex">
            <textarea id="flxad-dark-ex" rows={3} value={s.darkExclude} onChange={(e) => set('darkExclude', e.currentTarget.value)} />
            <p className="flxad-desc">{t('One screen ID or body class per line (may end with *). Complex screens without their own dark theme stay light here.')}</p>
          </Row>
        </div>
      )}

      {tab === 'dashboard' && (
        <div className="flxad-card">
          <h2>{t('Welcome and summary')}</h2>
          <Row label={t('Top section')}>
            <Check checked={s.dashHeader} onChange={(v) => set('dashHeader', v)} text={t('Greeting, date and summary tiles')} />
          </Row>
          <Row label={t('Summary tiles')}>
            {config.dashStatChoices.map((ch) => (
              <Check
                key={ch.key}
                block
                checked={s.dashStats.includes(ch.key)}
                onChange={(v) => set('dashStats', v ? [...s.dashStats, ch.key] : s.dashStats.filter((x: FlxAdminDashStat) => x !== ch.key))}
                text={ch.label}
              />
            ))}
            <p className="flxad-desc">{t('People only see tiles they have permission for. Plugins can add tiles with the `flxa_dashboard_stats` filter.')}</p>
          </Row>
          <Row label={t('Extra quick actions')} htmlFor="flxad-ql">
            <textarea id="flxad-ql" rows={4} value={s.quickLinks} placeholder={t("Events|edit.php?post_type=etkinlik\nLive site|https://")} onChange={(e) => set('quickLinks', e.currentTarget.value)} />
            <p className="flxad-desc">{t('One "Label|Address" per line. The address can be relative to wp-admin or a full https:// URL.')}</p>
          </Row>
          <p className="flxad-desc">{t('Module order and visibility are personal: open "Edit modules" on the dashboard and drag the boxes.')}</p>
        </div>
      )}

      {tab === 'pwa' && (
        <div className="flxad-card">
          <h2>{t('Install as an app')}</h2>
          {!config.pwaPossible && <div className="flxad-notice is-warning">{t('The site is not HTTPS; browsers will not allow installing.')}</div>}
          <Row label={t('PWA')}>
            <Check checked={s.pwa} onChange={(v) => set('pwa', v)} text={t('Allow installing the dashboard as an app on phones and computers')} />
            <p className="flxad-desc">{t('Opens full screen, shows the unread count on the app icon, and shows an offline screen when the connection drops. Admin pages are never cached.')}</p>
          </Row>
          <Row label={t('App name')} htmlFor="flxad-pn">
            <input id="flxad-pn" type="text" className="flxad-wide" value={s.pwaName} placeholder={config.pwaDefaultName} onChange={(e) => set('pwaName', e.currentTarget.value)} />
          </Row>
          <Row label={t('Short name (under the icon)')} htmlFor="flxad-ps">
            <input id="flxad-ps" type="text" className="flxad-wide" maxLength={20} value={s.pwaShort} placeholder={config.pwaDefaultShort} onChange={(e) => set('pwaShort', e.currentTarget.value)} />
          </Row>
          <Row label={t('Icon')}>
            {config.pwaIconUrl && <img className="flxad-pwa-icon" src={config.pwaIconUrl} alt="" width={64} height={64} />}
            <p className="flxad-desc">{t('The site icon (Appearance › Customize › Site Identity) is used if set.')}</p>
          </Row>
          <h3>{t('How to install?')}</h3>
          <ul className="flxad-howto">
            <li>
              <strong>Android / Chrome:</strong> {t('The "Install" button on the dashboard, or menu › Install the app.')}
            </li>
            <li>
              <strong>iPhone / Safari:</strong> {t('Share › Add to Home Screen.')}
            </li>
            <li>
              <strong>{t('Computer')}:</strong> {t('The install icon in the address bar.')}
            </li>
          </ul>
        </div>
      )}

      <div className="flxad-actions">
        <button type="submit" className="flxad-btn is-primary" disabled={busy || !dirty}>
          {t('Save')}
        </button>
        {dirty && <span className="flxad-dirty">{t('Unsaved changes')}</span>}
      </div>
      <span hidden>{locale}</span>
    </form>
  );
}

function Row({ label, htmlFor, children }: { label: string; htmlFor?: string; children: ReactNode }) {
  return (
    <div className="flxad-row">
      <div className="flxad-th">{htmlFor ? <label htmlFor={htmlFor}>{label}</label> : label}</div>
      <div className="flxad-td">{children}</div>
    </div>
  );
}

function Check({ id, checked, onChange, text, block }: { id?: string; checked: boolean; onChange: (v: boolean) => void; text: string; block?: boolean }) {
  return (
    <label className={block ? 'flxad-block' : undefined}>
      <input id={id} type="checkbox" checked={checked} onChange={(e) => onChange(e.currentTarget.checked)} /> {text}
    </label>
  );
}

function Radio({ name, checked, onChange, text, extra }: { name: string; checked: boolean; onChange: () => void; text: string; extra?: ReactNode }) {
  return (
    <label>
      <input type="radio" name={name} checked={checked} onChange={onChange} /> {extra} {text}
    </label>
  );
}
