// Application › Appearance: theme, mobile and desktop shell, screens, and the two-platform preview.
// The site layer is an open versioned document (flxapp_app option); the server sanitizes it exactly as for presets.
import { useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAdminAppearance, FlxAppAdminAppearanceSave } from '@fluxwp/graphql/operations';
import type { FlxAppAdminAppearanceQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Check, payloadError, SaveBar, useScreen } from './common';

type Ap = FlxAppAdminAppearanceQuery['flxAppAdminAppearance'];
type Rec = Record<string, unknown>;
const rec = (v: unknown): Rec => (v && typeof v === 'object' && !Array.isArray(v) ? (v as Rec) : {});
const list = (v: unknown): unknown[] => (Array.isArray(v) ? v : []);
const str = (v: unknown): string => (typeof v === 'string' ? v : '');

interface Tab {
  target: string;
  label: string;
  icon: string;
  badge: string;
}
interface Draft {
  mode: string;
  radius: string;
  density: string;
  fontBody: string;
  fontHeading: string;
  colors: Record<'light' | 'dark', Record<string, string>>;
  nav: string;
  mHome: string;
  tabs: Tab[];
  drawer: boolean;
  showLogo: boolean;
  mSwitcher: boolean;
  topStyle: string;
  dHome: string;
  dSwitcher: boolean;
  commands: string[];
  screens: Record<string, boolean>;
}

const COLOR_KEYS = ['primary', 'accent', 'bg', 'surface', 'text'];

function draftOf(a: Ap): Draft {
  const o = rec(a.option);
  const theme = rec(o.theme);
  const shell = rec(o.shell);
  const mob = rec(shell.mobile);
  const desk = rec(shell.desktop);
  const tabs = list(mob.tabs).map((t) => rec(t));
  const colors = { light: {} as Record<string, string>, dark: {} as Record<string, string> };
  for (const m of ['light', 'dark'] as const) for (const k of COLOR_KEYS) colors[m][k] = str(rec(theme[m])[k]);
  const on = (v: unknown, d: boolean) => (v === undefined ? d : !!v);
  return {
    mode: str(theme.mode) || 'system',
    radius: str(theme.radius),
    density: str(theme.density),
    fontBody: str(rec(theme.font).body),
    fontHeading: str(rec(theme.font).heading),
    colors,
    nav: str(mob.nav) || 'tabs+drawer',
    mHome: str(mob.home),
    tabs: Array.from({ length: 5 }, (_, i) => ({ target: str(tabs[i]?.target), label: str(tabs[i]?.label), icon: str(tabs[i]?.icon), badge: str(tabs[i]?.badge) })),
    drawer: on(rec(mob.drawer).enabled, true),
    showLogo: on(rec(mob.topbar).show_logo, true),
    mSwitcher: on(rec(mob.topbar).site_switcher, true),
    topStyle: str(rec(mob.topbar).style) || 'large',
    dHome: str(desk.home),
    dSwitcher: on(rec(desk.topbar).site_switcher, true),
    commands: Array.from({ length: 4 }, (_, i) => str(list(desk.commands)[i])),
    screens: Object.fromEntries(a.screens.map((s) => [s.key, s.on])),
  };
}

export function AppearanceTab() {
  const { t, told, fail } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAppAdminAppearance, {});
  const [draft, setDraft] = useState<Draft | null>(() => (q.data ? draftOf(q.data.flxAppAdminAppearance) : null));
  const [dirty, setDirty] = useState(false);
  useEffect(() => {
    if (q.data && !dirty) setDraft(draftOf(q.data.flxAppAdminAppearance));
  }, [q.data, dirty]);

  const save = useFlxMutation(FlxAppAdminAppearanceSave, {
    onSuccess: (r) => {
      const p = r.flxAppAdminAppearanceSave;
      const err = payloadError(p?.errors);
      if (!p?.appearance || err) return fail(err ? new Error(err) : null);
      qc.setQueryData(flxQueryKey(scope, FlxAppAdminAppearance, {}), { flxAppAdminAppearance: p.appearance });
      setDraft(draftOf(p.appearance));
      setDirty(false);
      told(t('Saved.'));
    },
    onError: fail,
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const a = q.data.flxAppAdminAppearance;
  const theme = rec(a.theme);
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft((d) => d && { ...d, [k]: v });
    setDirty(true);
  };
  const modes: Record<string, string> = { system: t('Follow device'), light: t('On'), dark: t('Dark') };
  const label = (key: string) => a.targetLabels.find((x) => x.key === key)?.label ?? key;

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const themeIn: Rec = { mode: draft.mode, radius: draft.radius, density: draft.density, font: { body: draft.fontBody, heading: draft.fontHeading }, light: draft.colors.light, dark: draft.colors.dark };
    save.mutate({
      input: {
        theme: themeIn,
        mobile: {
          nav: draft.nav,
          home: draft.mHome,
          tabs: draft.tabs.map((x) => ({ target: x.target, label: x.label, icon: x.icon, badge: x.badge })),
          drawer: { enabled: draft.drawer },
          topbar: { show_logo: draft.showLogo, site_switcher: draft.mSwitcher, style: draft.topStyle },
        },
        desktop: { home: draft.dHome, topbar: { site_switcher: draft.dSwitcher }, commands: draft.commands },
        screensOn: Object.entries(draft.screens).filter(([, on]) => on).map(([k]) => k),
      },
    });
  };

  const TargetSelect = ({ id, value, onChange, empty }: { id: string; value: string; onChange: (v: string) => void; empty?: string }) => (
    <select id={id} className="flxs-select" value={value} onChange={(e) => onChange(e.currentTarget.value)}>
      <option value="">{empty ?? '—'}</option>
      {a.targets.map((g) => (
        <optgroup key={g.label} label={g.label}>
          {g.items.map((i) => (
            <option key={i.key} value={i.key}>
              {i.label}
            </option>
          ))}
        </optgroup>
      ))}
    </select>
  );
  const fromTheme = (k: 'body' | 'heading') => t('From theme (%s)', str(rec(theme.font)[k]));

  return (
    <>
      <p className="flxs-lead">
        {t("Appearance of the desktop and mobile app. Empty colours come from the theme (theme.json) and the Flux Admin colour. Menus are filtered by the user's role: an item they cannot see in the admin does not show in the app either.")}
      </p>
      <div className="flxs-appearance">
        <form onSubmit={submit}>
          <section className="flxs-card">
            <h2>{t('Theme')}</h2>
            <div className="flxs-row">
              <label>
                {t('Mode')}{' '}
                <select value={draft.mode} onChange={(e) => set('mode', e.currentTarget.value)}>
                  {Object.entries(modes).map(([k, l]) => (
                    <option key={k} value={k}>
                      {l}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                {t('Corners')}{' '}
                <select value={draft.radius} onChange={(e) => set('radius', e.currentTarget.value)}>
                  <option value="">{t('From theme')}</option>
                  <option value="none">{t('Sharp')}</option>
                  <option value="small">{t('Slight')}</option>
                  <option value="medium">{t('Medium')}</option>
                  <option value="large">{t('Round')}</option>
                </select>
              </label>
              <label>
                {t('Density')}{' '}
                <select value={draft.density} onChange={(e) => set('density', e.currentTarget.value)}>
                  <option value="">{t('From theme')}</option>
                  <option value="comfortable">{t('Comfortable')}</option>
                  <option value="compact">{t('Compact')}</option>
                </select>
              </label>
              {(['body', 'heading'] as const).map((k) => (
                <label key={k}>
                  {k === 'body' ? t('Font') : t('Heading font')}{' '}
                  <select value={k === 'body' ? draft.fontBody : draft.fontHeading} onChange={(e) => set(k === 'body' ? 'fontBody' : 'fontHeading', e.currentTarget.value)}>
                    <option value="">{fromTheme(k)}</option>
                    {a.fonts.map((f) => (
                      <option key={f}>{f}</option>
                    ))}
                  </select>
                </label>
              ))}
            </div>
            <table className="flxs-colors">
              <thead>
                <tr>
                  <th />
                  {a.colorKeys.map((c) => (
                    <th key={c.key}>{c.label}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(['light', 'dark'] as const).map((m) => (
                  <tr key={m}>
                    <th>{m === 'light' ? t('On') : t('Dark')}</th>
                    {a.colorKeys.map((c) => {
                      const resolved = str(rec(theme[m])[c.key]);
                      const typed = draft.colors[m][c.key] ?? '';
                      return (
                        <td key={c.key}>
                          <span className="flxs-swatch" style={{ background: /^#[0-9a-f]{6}$/i.test(typed) ? typed : resolved }} />
                          <input
                            type="text"
                            className="flxs-hex"
                            maxLength={7}
                            pattern="#[0-9A-Fa-f]{6}"
                            placeholder={resolved}
                            aria-label={(m === 'light' ? t('On') : t('Dark')) + ' ' + c.label}
                            value={typed}
                            onChange={(e) => {
                              const v = e.currentTarget.value;
                              setDraft((d) => d && { ...d, colors: { ...d.colors, [m]: { ...d.colors[m], [c.key]: v } } });
                              setDirty(true);
                            }}
                          />
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="flxs-card">
            <h2>{t('Mobile')}</h2>
            <div className="flxs-row">
              <label>
                {t('Navigation')}{' '}
                <select value={draft.nav} onChange={(e) => set('nav', e.currentTarget.value)}>
                  <option value="tabs+drawer">{t('Tab bar + drawer')}</option>
                  <option value="tabs">{t('Tab bar only')}</option>
                  <option value="drawer">{t('Drawer only')}</option>
                </select>
              </label>
              <label>
                {t('Home screen')} <TargetSelect id="flxs-mhome" value={draft.mHome} empty={t('Home')} onChange={(v) => set('mHome', v)} />
              </label>
            </div>
            <h3>{t('Tab bar (up to 5)')}</h3>
            <p className="flxs-desc">{t('If empty, the Flux Admin mobile bottom bar setting is used. Tabs the user cannot open are hidden; "Menu" always stays reachable.')}</p>
            <table className="flxs-tabs">
              <tbody>
                {draft.tabs.map((tab, i) => {
                  const patch = (p: Partial<Tab>) => set('tabs', draft.tabs.map((x, j) => (j === i ? { ...x, ...p } : x)));
                  return (
                    <tr key={i}>
                      <th>{i + 1}</th>
                      <td>
                        <TargetSelect id={'flxs-tab' + i} value={tab.target} onChange={(v) => patch({ target: v })} />
                      </td>
                      <td>
                        <input type="text" placeholder={t('Label')} aria-label={t('Label')} value={tab.label} onChange={(e) => patch({ label: e.currentTarget.value })} />
                      </td>
                      <td>
                        <input type="text" placeholder={t('Icon (home, chat…)')} aria-label={t('Icon (home, chat…)')} value={tab.icon} onChange={(e) => patch({ icon: e.currentTarget.value })} />
                      </td>
                      <td>
                        <input type="text" placeholder={t('Badge')} aria-label={t('Badge')} value={tab.badge} onChange={(e) => patch({ badge: e.currentTarget.value })} />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <div className="flxs-row">
              <Check checked={draft.drawer} onChange={(v) => set('drawer', v)}>
                {t('Drawer (role menu groups)')}
              </Check>
              <Check checked={draft.showLogo} onChange={(v) => set('showLogo', v)}>
                {t('Logo in the top bar')}
              </Check>
              <Check checked={draft.mSwitcher} onChange={(v) => set('mSwitcher', v)}>
                {t('Site switcher')}
              </Check>
              <label>
                {t('Top bar')}{' '}
                <select value={draft.topStyle} onChange={(e) => set('topStyle', e.currentTarget.value)}>
                  <option value="large">{t('Large title')}</option>
                  <option value="compact">{t('Compact')}</option>
                </select>
              </label>
            </div>
          </section>

          <section className="flxs-card">
            <h2>{t('Desktop')}</h2>
            <div className="flxs-row">
              <label>
                {t('Home screen')} <TargetSelect id="flxs-dhome" value={draft.dHome} empty={t('Home')} onChange={(v) => set('dHome', v)} />
              </label>
              <Check checked={draft.dSwitcher} onChange={(v) => set('dSwitcher', v)}>
                {t('Site switcher')}
              </Check>
            </div>
            <p className="flxs-desc">{t("The sidebar follows the role's Flux Admin menu layout (pins and groups). Shortcuts always shown in the command palette (⌘K):")}</p>
            <div className="flxs-row">
              {draft.commands.map((c, i) => (
                <TargetSelect key={i} id={'flxs-cmd' + i} value={c} onChange={(v) => set('commands', draft.commands.map((x, j) => (j === i ? v : x)))} />
              ))}
            </div>
          </section>

          <section className="flxs-card">
            <h2>{t('Screens')}</h2>
            <p className="flxs-desc">{t('A disabled screen does not open natively in the app; that page opens in the web view.')}</p>
            <div className="flxs-screens">
              {a.screens.map((s) => (
                <Check key={s.key} checked={draft.screens[s.key] ?? false} onChange={(v) => set('screens', { ...draft.screens, [s.key]: v })}>
                  {s.label}
                </Check>
              ))}
            </div>
          </section>
          <SaveBar busy={save.isPending} dirty={dirty} />
        </form>

        <aside className="flxs-preview">
          <h2>{t('Preview')}</h2>
          <Preview a={a} label={label} />
        </aside>
      </div>
    </>
  );
}

/** Two phones (light, dark) and a desktop window from the resolved configs of the signed-in administrator. */
function Preview({ a, label }: { a: Ap; label: (key: string) => string }) {
  const { t } = useScreen();
  const m = rec(a.previewMobile);
  const d = rec(a.previewDesktop);
  const mt = rec(m.theme);
  const style = (mode: 'light' | 'dark') => {
    const c = rec(mt[mode]);
    return { '--p': str(c.primary), '--op': str(c.onPrimary), '--bg': str(c.bg), '--sf': str(c.surface), '--tx': str(c.text), '--ac': str(c.accent) } as React.CSSProperties;
  };
  const brand = rec(m.brand);
  const logo = str(brand.icon) || str(brand.logo);
  const shell = rec(m.shell);
  const tabs = list(shell.tabs).map(rec);
  const sidebar = rec(rec(d.shell).sidebar);
  return (
    <>
      {(['light', 'dark'] as const).map((mode) => (
        <div key={mode} className={'flxs-phone is-' + mode} style={style(mode)}>
          <div className="flxs-phone-top">
            {logo && !!rec(shell.topbar).show_logo && <img src={logo} alt="" width={20} height={20} />}
            <strong>{str(brand.name)}</strong>
          </div>
          <div className="flxs-phone-body">
            <div className="flxs-ph-card" />
            <div className="flxs-ph-card is-half" />
            <div className="flxs-ph-card is-half" />
            <div className="flxs-ph-row" />
            <div className="flxs-ph-row" />
          </div>
          <div className="flxs-phone-tabs">
            {tabs.map((tab, i) => (
              <span key={i} className={i === 0 ? 'is-on' : ''}>
                <i />
                {str(tab.label) || label(str(tab.target))}
              </span>
            ))}
          </div>
        </div>
      ))}
      <div className="flxs-desk" style={style('light')}>
        <div className="flxs-desk-side">
          <strong>{str(rec(d.brand).name)}</strong>
          {list(sidebar.pins).slice(0, 3).map((p, i) => (
            <span key={'p' + i} className="is-pin">
              {label(str(p))}
            </span>
          ))}
          {list(sidebar.groups).slice(0, 3).map(rec).map((g, gi) => (
            <div key={gi} className="flxs-desk-group">
              {str(g.label) && <em>{str(g.label)}</em>}
              {list(g.items).slice(0, 4).map((it, i) => (
                <span key={i}>{label(str(it))}</span>
              ))}
            </div>
          ))}
        </div>
        <div className="flxs-desk-main">
          <div className="flxs-desk-top">⌘K</div>
          <div className="flxs-ph-card" />
          <div className="flxs-ph-row" />
          <div className="flxs-ph-row" />
          <div className="flxs-ph-row" />
        </div>
      </div>
      <p className="flxs-desc">{t('Preview uses your role; other roles see their own menus.')}</p>
    </>
  );
}
