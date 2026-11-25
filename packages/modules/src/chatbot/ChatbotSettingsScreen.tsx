// Flux AI Chatbot › Settings. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux AI Chatbot GraphQL operations (plugins/flx-chatbot/graphql/operations).
// Six tabs, figures, connections, the live preview with the
// real chat window, colours, avatar, launcher, texts, AI, live chat hours and visibility.
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle, BookOpen, Bot, Check as CheckIcon, Clock, ExternalLink, Eye, Headphones, Home, Image as ImageIcon, Info, Inbox, MessageCircle, Palette, Settings, Shield, ShoppingCart, Smile, Sparkles, Star, Type, Users, HelpCircle,
} from 'lucide-react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxChatbotSettingsSave, FlxChatbotSettingsScreen } from '@fluxwp/graphql/operations';
import type { FlxChatbotSettingsFieldsFragment, FlxChatbotSettingsScreenQuery, FlxSettingsChatbotInput } from '@fluxwp/graphql/types';
import { Loading, Screen, Tabs } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { Area, Card, Check, Choices, IconPick, Kpi, Range, Select, Switch, Text, type Tr } from './fields';
import './chatbot.css';

type S = FlxChatbotSettingsFieldsFragment;
type Admin = FlxChatbotSettingsScreenQuery['flxChatbotAdmin'];
type Notice = { kind: 'ok' | 'bad'; text: string };
type Tab = 'general' | 'look' | 'texts' | 'ai' | 'live' | 'visibility';
type Day = { on: boolean; from: string; to: string };
type View = 'home' | 'chat' | 'teaser' | 'closed';

const TABS: Tab[] = ['general', 'look', 'texts', 'ai', 'live', 'visibility'];
const SWATCHES = ['#1f2937', '#2563eb', '#0f766e', '#16a34a', '#7c3aed', '#db2777', '#ea580c', '#0891b2'];
const SIZE = 15;

/** GraphQL enum value → the raw setting value the chat window uses (FLX_CHAT → flx-chat). */
const raw = (v: string | null | undefined, d = ''): string => (v ? v.toLowerCase().replace(/_/g, '-') : d);

interface Widget {
  update: (cfg: Record<string, unknown>) => void;
  view?: (v: string) => void;
  teaser: () => void;
  close: () => void;
}
/** Own view of window (the global Window.wp type is declared by another screen with a narrower media shape). */
interface WpWindow {
  flxcbConfig?: unknown;
  FluxChatbot?: Widget;
  wp?: { media?: (o: Record<string, unknown>) => MediaFrame };
}
interface MediaFrame {
  on: (ev: string, cb: () => void) => void;
  open: () => void;
  state: () => { get: (k: string) => { first: () => { toJSON: () => { id: number; url: string; sizes?: Record<string, { url: string }> } } } };
}

/** Same threshold as PHP flxcb_contrast(). */
function contrast(c: string): string {
  let h = c.replace('#', '');
  if (h.length === 3) h = h[0]! + h[0]! + h[1]! + h[1]! + h[2]! + h[2]!;
  const f = (x: string) => {
    const n = parseInt(x, 16) / 255;
    return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4);
  };
  const l = 0.2126 * f(h.slice(0, 2)) + 0.7152 * f(h.slice(2, 4)) + 0.0722 * f(h.slice(4, 6));
  return l > 0.45 ? '#111827' : '#ffffff';
}
const hex = (c: string | null): string => (c && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c) ? c : '');
const full = (c: string): string => (c.length === 4 ? '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3] : c);

function lines(v: string | null): string[] {
  return (v ?? '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
}

/** Same rule as flxcb_whatsapp() in the plugin: digits only, 7-15 long, empty hides the button. */
function whatsapp(d: { whatsappNumber?: string | null; whatsappMessage?: string | null }, label: string): { url: string; label: string } | null {
  const n = (d.whatsappNumber ?? '').replace(/\D+/g, '').replace(/^00/, '');
  if (n.length < 7 || n.length > 15) return null;
  const text = (d.whatsappMessage ?? '').trim();
  return { url: 'https://wa.me/' + n + (text ? '?text=' + encodeURIComponent(text) : ''), label };
}

function links(v: string | null): Array<{ label: string; url: string; kind: string }> {
  const out: Array<{ label: string; url: string; kind: string }> = [];
  for (const l of lines(v)) {
    const p = l.split('|');
    const label = (p[0] ?? '').trim();
    let url = (p[1] ?? '').trim();
    if (!url && /^(https?:\/\/|mailto:|tel:)/i.test(label)) url = label;
    if (!/^(https?:\/\/|mailto:|tel:|whatsapp:)/i.test(url)) continue;
    const kind = /^mailto:/i.test(url) ? 'mail' : /^tel:/i.test(url) ? 'phone' : /(wa\.me|whatsapp\.com|^whatsapp:)/i.test(url) ? 'whatsapp' : 'link';
    out.push({ label: label && label !== url ? label : url.replace(/^(https?:\/\/|mailto:|tel:)/i, ''), url, kind });
  }
  return out.slice(0, 6);
}

const dayOf = (hours: unknown, key: string): Day => {
  const h = hours && typeof hours === 'object' ? (hours as Record<string, unknown>)[key] : undefined;
  const d = h && typeof h === 'object' ? (h as Record<string, unknown>) : {};
  return { on: Boolean(d.on) && d.on !== '0', from: typeof d.from === 'string' ? d.from : '09:00', to: typeof d.to === 'string' ? d.to : '18:00' };
};

export function ChatbotSettingsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <ChatbotSettings />
    </FlxGraphQLProvider>
  );
}

function ChatbotSettings() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxChatbotSettingsScreen, {});
  const [draft, setDraft] = useState<S | null>(() => q.data?.flxSettings.chatbot ?? null);
  const [dirty, setDirty] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [tab, setTab] = useState<Tab>(() => {
    const p = new URLSearchParams(window.location.search).get('tab');
    return TABS.includes(p as Tab) ? (p as Tab) : 'general';
  });
  const [view, setView] = useState<View>('home');
  const [avatarUrl, setAvatarUrl] = useState<string | null>(() => q.data?.flxChatbotAdmin.avatarUrl ?? null);

  useEffect(() => {
    if (q.data && !dirty) {
      setDraft(q.data.flxSettings.chatbot);
      setAvatarUrl(q.data.flxChatbotAdmin.avatarUrl);
    }
  }, [q.data, dirty]);

  useEffect(() => {
    if (!dirty) return;
    const h = (e: BeforeUnloadEvent) => {
      e.preventDefault();
    };
    window.addEventListener('beforeunload', h);
    return () => window.removeEventListener('beforeunload', h);
  }, [dirty]);

  const save = useFlxMutation(FlxChatbotSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxChatbotSettingsSave;
      if (!p) return setNotice({ kind: 'bad', text: t('Something went wrong. Please try again.') });
      if (p.errors.length) return setNotice({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      const next = p.settings;
      if (next) {
        qc.setQueryData<FlxChatbotSettingsScreenQuery>(flxQueryKey(scope, FlxChatbotSettingsScreen, {}), (old) => old && { ...old, flxSettings: { ...old.flxSettings, chatbot: next } });
        setDraft(next);
      }
      setDirty(false);
      setNotice({ kind: 'ok', text: t('Settings saved.') });
      window.scrollTo?.({ top: 0, behavior: 'smooth' });
    },
    onError: (e) => setNotice({ kind: 'bad', text: e.message || t('Something went wrong. Please try again.') }),
  });

  if (q.isLoading || (!draft && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !draft) return <div className="flxcb"><div className="flxcb-notice is-bad">{q.error?.message || t('Something went wrong. Please try again.')}</div></div>;

  const a = q.data.flxChatbotAdmin;
  const set = <K extends keyof S>(k: K, v: S[K]) => {
    setDraft((old) => (old ? { ...old, [k]: v } : old));
    setDirty(true);
    setNotice(null);
  };
  const submit = () => {
    const entries = Object.entries(draft).filter(([, v]) => v !== null && v !== undefined);
    save.mutate({ input: { settings: Object.fromEntries(entries) as FlxSettingsChatbotInput } });
  };
  const goTab = (k: Tab) => {
    setTab(k);
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', k);
      window.history.replaceState(null, '', u.toString());
    } catch {
      /* the address bar is not essential */
    }
  };

  const tabs: Array<[Tab, string, ReactNode]> = [
    ['general', t('General'), <Settings key="g" size={SIZE} />],
    ['look', t('Appearance'), <Palette key="l" size={SIZE} />],
    ['texts', t('Texts'), <Type key="x" size={SIZE} />],
    ['ai', t('AI'), <Sparkles key="a" size={SIZE} />],
    ['live', t('Live chat'), <Headphones key="v" size={SIZE} />],
    ['visibility', t('Visibility'), <Eye key="s" size={SIZE} />],
  ];
  const tryUrl = (() => {
    try {
      const u = new URL(a.siteUrl);
      u.searchParams.set('flxcb_preview', '1');
      return u.toString();
    } catch {
      return a.siteUrl;
    }
  })();
  const st = a.stats;

  return (
    <Screen
      className="flxcb"
      width="wide"
      icon={host.appIcon}
      title={t('Flux AI Chatbot')}
      subtitle={t('An AI chat window for your site: it answers from your content and products and hands the chat to a live agent when needed. Changes show up in the preview on the right right away.')}
      actions={
        <>
          <a className="button" href={a.inboxUrl}>
            <Inbox size={14} />
            {t('Chats')}
          </a>
          <a className="button" href={tryUrl} target="_blank" rel="noopener noreferrer">
            <ExternalLink size={14} />
            {t('Try on site')}
          </a>
        </>
      }
    >

      {notice && (
        <div className={'flxcb-notice ' + (notice.kind === 'ok' ? 'is-ok' : 'is-bad')} role="status">
          <span>{notice.text}</span>
        </div>
      )}

      <div className="flxcb-kpis">
        <Kpi icon={<MessageCircle size={18} />} label={t('Chats')} value={String(st.total)} hint={t('last 30 days')} />
        <Kpi icon={<Sparkles size={18} />} label={t('Solved by the assistant')} value={st.total ? '%' + st.resolved : '–'} hint={t('without an agent')} ok />
        <Kpi icon={<Users size={18} />} label={t('Lead')} value={String(st.leads)} hint={t('left contact details')} />
        {a.shopActive && <Kpi icon={<ShoppingCart size={18} />} label={t('Orders after chat')} value={String(st.orders)} hint={t('within 7 days')} />}
        <Kpi
          icon={<Star size={18} />}
          label={t('Satisfaction')}
          value={st.rated && st.rating !== null ? st.rating.toFixed(1) + ' / 5' : '–'}
          hint={st.rated ? t('%s ratings', st.rated) : t('no ratings yet')}
        />
      </div>

      <Tabs
        items={tabs.map(([id, label, icon]) => ({ id, label: (<>{icon}<span>{label}</span></>) }))}
        current={tab}
        onChange={goTab}
        label={t('Flux AI Chatbot')}
      />

      <div className="flxcb-layout">
        <div className="flxcb-main">
          <form
            className="flxcb-form"
            onSubmit={(e) => {
              e.preventDefault();
              submit();
            }}
          >
            {tab === 'general' && <General t={t} a={a} d={draft} set={set} />}
            {tab === 'look' && <Look t={t} d={draft} set={set} avatarUrl={avatarUrl} setAvatarUrl={setAvatarUrl} mode={host.mode} />}
            {tab === 'texts' && <Texts t={t} a={a} d={draft} set={set} />}
            {tab === 'ai' && <Ai t={t} a={a} d={draft} set={set} />}
            {tab === 'live' && <Live t={t} a={a} d={draft} set={set} />}
            {tab === 'visibility' && <Visibility t={t} a={a} d={draft} set={set} />}
            <div className="flxcb-savebar">
              {dirty && <span className="flxcb-dirty">{t('You have unsaved changes')}</span>}
              <button type="submit" className="button button-primary button-large" disabled={save.isPending}>
                {t('Save')}
              </button>
            </div>
          </form>
        </div>

        <aside className="flxcb-side">
          <Preview t={t} a={a} d={draft} avatarUrl={avatarUrl} view={view} setView={setView} />
        </aside>
      </div>
    </Screen>
  );
}

type Set = <K extends keyof S>(k: K, v: S[K]) => void;
interface PaneProps {
  t: Tr;
  d: S;
  set: Set;
}
interface AdminPaneProps extends PaneProps {
  a: Admin;
}

/* ---------------- General ---------------- */
function General({ t, a, d, set }: AdminPaneProps) {
  return (
    <div className="flxcb-pane">
      <Card id="flxcb-on" icon={<MessageCircle size={16} />} title={t('Chat window')}>
        <Switch label={t('Show on site')} help={t("While off, visitors don't see the window; admins can preview it with \"Try on site\".")} on={Boolean(d.enabled)} onChange={(v) => set('enabled', v)} />
        <h3 className="flxcb-sub">{t('Mode')}</h3>
        <Choices
          value={d.mode}
          onChange={(v) => set('mode', v)}
          options={[
            { value: 'HYBRID', title: t('AI + live chat'), text: t('The assistant answers and hands over to an agent when needed. Recommended.'), icon: <Sparkles size={18} /> },
            { value: 'AI', title: t('AI only'), text: t('No handoff; the assistant shares your contact details.'), icon: <Bot size={18} /> },
            { value: 'LIVE', title: t('Live chat only'), text: t('No assistant; messages go straight to agents.'), icon: <Headphones size={18} /> },
          ]}
        />
      </Card>

      <Card id="flxcb-health" icon={<Shield size={16} />} title={t('Connections')}>
        <ul className="flxcb-statuses">
          {a.status.map((r) => (
            <li key={r.key} className={'flxcb-status ' + (r.state === 'OK' ? 'is-ok' : r.state === 'INFO' ? 'is-info' : 'is-warn')}>
              <span className="flxcb-status__i">{r.state === 'OK' ? <CheckIcon size={14} /> : r.state === 'INFO' ? <Info size={14} /> : <AlertTriangle size={14} />}</span>
              <span>
                <strong>{r.title}</strong>
                <small>{r.text}</small>
              </span>
              {r.url && (
                <a className="button" href={r.url}>
                  {r.urlLabel}
                </a>
              )}
            </li>
          ))}
        </ul>
      </Card>

      <Card id="flxcb-embed" icon={<ExternalLink size={16} />} title={t('Open from a page')} desc={t('Any button or link can open the window.')}>
        <ul className="flxcb-code">
          <li>
            <code>{'[flx_chatbot label="' + t('Message us') + '"]'}</code>
            <small>{t('Shortcode: a button inside the page.')}</small>
          </li>
          <li>
            <code>{'<a href="#flxcb-open">…</a>'}</code>
            <small>{t('A link to #flxcb-open opens the window.')}</small>
          </li>
          <li>
            <code>data-flxcb-message="…"</code>
            <small>{t('Added to a button, the window opens with this message.')}</small>
          </li>
          <li>
            <code>FluxChatbot.open()</code>
            <small>{t('Open with JavaScript.')}</small>
          </li>
        </ul>
      </Card>
    </div>
  );
}

/* ---------------- Appearance ---------------- */
function ColorField({ label, help, value, fallback, clearLabel, placeholder, onChange }: { label: string; help: string; value: string | null; fallback: string; clearLabel: string; placeholder: string; onChange: (v: string) => void }) {
  const v = value ?? '';
  return (
    <div className="flxcb-field">
      <span>{label}</span>
      <div className="flxcb-color">
        <input type="color" value={hex(v) ? full(v) : fallback} aria-label={label} onChange={(e) => onChange(e.target.value)} />
        <input type="text" className="code" maxLength={7} value={v} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
        <button type="button" className="button-link" onClick={() => onChange('')}>
          {clearLabel}
        </button>
      </div>
      <small>{help}</small>
    </div>
  );
}

function Look({ t, d, set, avatarUrl, setAvatarUrl, mode }: PaneProps & { avatarUrl: string | null; setAvatarUrl: (u: string | null) => void; mode: 'app' | 'wp' }) {
  const frame = useRef<MediaFrame | null>(null);
  const media = typeof window !== 'undefined' ? (window as unknown as WpWindow).wp?.media : undefined;
  const pick = () => {
    if (!media) return;
    if (!frame.current) {
      const f = media({ title: t('Choose avatar'), button: { text: t('Use this image') }, library: { type: 'image' }, multiple: false });
      f.on('select', () => {
        const att = f.state().get('selection').first().toJSON();
        const size = att.sizes?.thumbnail ?? att.sizes?.medium;
        set('avatarId', att.id);
        setAvatarUrl(size?.url ?? att.url);
      });
      frame.current = f;
    }
    frame.current.open();
  };
  const hasAvatar = Boolean(d.avatarId);
  return (
    <div className="flxcb-pane">
      <Card id="flxcb-brand" icon={<Palette size={16} />} title={t('Color and theme')}>
        <div className="flxcb-grid2">
          <ColorField label={t('Main color')} help={t("When empty, the site's theme color is used.")} value={d.color} fallback="#1f2937" clearLabel={t('Follow theme')} placeholder={t('Theme color')} onChange={(v) => set('color', v)} />
          <ColorField label={t('Text color (header and buttons)')} help={t('When empty, a readable color is picked from the main color.')} value={d.colorText} fallback="#ffffff" clearLabel={t('Automatic')} placeholder={t('Automatic')} onChange={(v) => set('colorText', v)} />
        </div>
        <div className="flxcb-swatches">
          {SWATCHES.map((c) => (
            <button key={c} type="button" style={{ ['--sw' as string]: c }} aria-label={c} onClick={() => set('color', c)} />
          ))}
        </div>
        <div className="flxcb-grid3">
          <Select label={t('Light / dark')} value={d.theme} onChange={(v) => set('theme', v)} options={[['AUTO', t('Automatic (follows device and site)')], ['LIGHT', t('Always light')], ['DARK', t('Always dark')]]} />
          <Select label={t('Header')} value={d.headerStyle} onChange={(v) => set('headerStyle', v)} options={[['GRADIENT', t('Gradient')], ['SOLID', t('Solid color')]]} />
          <Select label={t('Font')} value={d.font} onChange={(v) => set('font', v)} options={[['INHERIT', t('Same as site')], ['SYSTEM', t('System font')]]} />
        </div>
      </Card>

      <Card id="flxcb-avatar" icon={<Bot size={16} />} title={t('Assistant')}>
        <div className="flxcb-grid2">
          <Text label={t('Assistant name')} help={t('Shown in the chat header.')} value={d.botName} onChange={(v) => set('botName', v)} />
          <div className="flxcb-field">
            <span>{t('Avatar image')}</span>
            <div className="flxcb-media">
              <span className="flxcb-media__img">{avatarUrl && hasAvatar ? <img src={avatarUrl} alt="" /> : <ImageIcon size={18} />}</span>
              {media && (
                <button type="button" className="button" onClick={pick}>
                  {t('Choose')}
                </button>
              )}
              {hasAvatar && (
                <button
                  type="button"
                  className="button-link"
                  onClick={() => {
                    set('avatarId', 0);
                    setAvatarUrl(null);
                  }}
                >
                  {t('Remove')}
                </button>
              )}
            </div>
            <small>{mode === 'wp' || media ? t('A square image works best. Without one, the icon below is used.') : t('Pick the avatar image in the WordPress admin; here you can only remove it.')}</small>
          </div>
        </div>
        <div className="flxcb-field">
          <span>{t('Avatar icon')}</span>
          <IconPick
            value={d.avatarIcon}
            onChange={(v) => set('avatarIcon', v)}
            options={[
              { value: 'BOT', title: t('Robot'), icon: <Bot size={20} /> },
              { value: 'SPARKLES', title: t('Sparkles'), icon: <Sparkles size={20} /> },
              { value: 'HEADSET', title: t('Headset'), icon: <Headphones size={20} /> },
              { value: 'SMILE', title: t('Smiley'), icon: <Smile size={20} /> },
              { value: 'LOGO', title: t('Site icon'), icon: <Home size={20} /> },
            ]}
          />
        </div>
      </Card>

      <Card id="flxcb-launch" icon={<MessageCircle size={16} />} title={t('Launcher button and position')}>
        <div className="flxcb-field">
          <span>{t('Button icon')}</span>
          <IconPick
            value={d.launcherIcon}
            onChange={(v) => set('launcherIcon', v)}
            options={[
              { value: 'CHAT', title: t('Bubble'), icon: <MessageCircle size={20} /> },
              { value: 'SPARKLES', title: t('Sparkles'), icon: <Sparkles size={20} /> },
              { value: 'HEADSET', title: t('Headset'), icon: <Headphones size={20} /> },
              { value: 'HELP', title: t('Question'), icon: <HelpCircle size={20} /> },
              { value: 'AVATAR', title: t('Avatar'), icon: <ImageIcon size={20} /> },
            ]}
          />
        </div>
        <div className="flxcb-grid2">
          <Text label={t('Label next to the button')} help={t('When empty only the button shows. Example: Need help?')} value={d.launcherLabel} placeholder={t('None')} onChange={(v) => set('launcherLabel', v)} />
          <Select label={t('Position')} value={d.position} onChange={(v) => set('position', v)} options={[['RIGHT', t('Bottom right')], ['LEFT', t('Bottom left')]]} />
        </div>
        <div className="flxcb-grid3">
          <Range label={t('Button size')} value={d.launcherSize} min={44} max={80} onChange={(v) => set('launcherSize', v)} />
          <Range label={t('Side spacing')} value={d.offsetX} min={0} max={120} onChange={(v) => set('offsetX', v)} />
          <Range label={t('Bottom spacing')} value={d.offsetY} min={0} max={200} onChange={(v) => set('offsetY', v)} />
          <Range label={t('Window width')} value={d.width} min={320} max={480} onChange={(v) => set('width', v)} />
          <Range label={t('Corner radius')} value={d.radius} min={0} max={28} onChange={(v) => set('radius', v)} />
        </div>
        <div className="flxcb-toggles">
          <Switch label={t('Full screen on mobile')} help={t('On small screens the window covers the whole screen.')} on={Boolean(d.mobileFull)} onChange={(v) => set('mobileFull', v)} />
          <Switch label={t('Notification sound')} help={t('A short sound for a new reply while the window is closed.')} on={Boolean(d.sound)} onChange={(v) => set('sound', v)} />
          <Switch label={t('"Powered by FluxWP" note')} help={t('A small signature at the bottom of the window.')} on={Boolean(d.showBranding)} onChange={(v) => set('showBranding', v)} />
        </div>
      </Card>
    </div>
  );
}

/* ---------------- Texts ---------------- */
function Texts({ t, a, d, set }: AdminPaneProps) {
  const def = (k: string) => a.textDefaults.find((x) => x.key === k)?.text ?? '';
  return (
    <div className="flxcb-pane">
      <Card id="flxcb-home" icon={<Home size={16} />} title={t('Home screen')} desc={t("The welcome screen shown when the window opens. Empty texts use the default for the visitor's language (TR/EN).")}>
        <Switch label={t('Show home screen')} help={t('When off, the window opens straight into the chat.')} on={Boolean(d.homeEnabled)} onChange={(v) => set('homeEnabled', v)} />
        <div className="flxcb-grid2">
          <Text label={t('Header')} value={d.homeTitle} placeholder={def('home_title')} onChange={(v) => set('homeTitle', v)} />
          <Text label={t('Message field')} value={d.placeholder} placeholder={def('placeholder')} onChange={(v) => set('placeholder', v)} />
        </div>
        <Area label={t('Subtitle')} rows={2} value={d.homeText} placeholder={def('home_text')} onChange={(v) => set('homeText', v)} />
        <Switch label={t('Team avatars')} help={t('Photos of agents who replied recently show in the header.')} on={Boolean(d.showTeam)} onChange={(v) => set('showTeam', v)} />
        <div className="flxcb-grid2">
          <Area label={t('Suggested questions')} help={t('One question per line (up to 6). Clickable on the home screen and at the start of a chat.')} rows={5} value={d.quickReplies} placeholder={t('How long does shipping take?\nHow do returns work?\nWhere is my order?')} onChange={(v) => set('quickReplies', v)} />
          <Area label={t('Contact links')} help={t('One per line: Label | address. https, mailto:, tel: and wa.me are supported.')} rows={5} value={d.links} placeholder={'WhatsApp | https://wa.me/905xxxxxxxxx\n' + t('Email') + ' | mailto:info@example.com\n' + t('Phone') + ' | tel:+90212xxxxxxx'} onChange={(v) => set('links', v)} />
        </div>
        <div className="flxcb-grid3">
          <Text label={t('WhatsApp number')} help={t('With country code, digits only (e.g. 905301234567). Shows a WhatsApp button under the chat card; leave empty to hide it.')} value={d.whatsappNumber} placeholder="905301234567" onChange={(v) => set('whatsappNumber', v)} />
          <Text label={t('WhatsApp button text')} value={d.whatsappLabel} placeholder={def('whatsapp_label')} onChange={(v) => set('whatsappLabel', v)} />
          <Text label={t('WhatsApp ready-made message')} help={t('The text the visitor\'s WhatsApp chat opens with. Optional.')} value={d.whatsappMessage} onChange={(v) => set('whatsappMessage', v)} />
        </div>
      </Card>

      <Card id="flxcb-msgs" icon={<MessageCircle size={16} />} title={t('Chat texts')}>
        <Area label={t('Welcome message')} help={t("The assistant's first message.")} rows={2} value={d.welcome} placeholder={def('welcome')} onChange={(v) => set('welcome', v)} />
      </Card>

      <Card id="flxcb-teaser" icon={<Sparkles size={16} />} title={t('Teaser bubble')} desc={t('After the visitor stays on the page a while, a short message pops up above the button (once per session).')}>
        <Switch label={t('Show teaser bubble')} on={Boolean(d.teaserEnabled)} onChange={(v) => set('teaserEnabled', v)} />
        <div className="flxcb-grid2">
          <Text label={t('Message')} value={d.teaserText} placeholder={def('teaser_text')} onChange={(v) => set('teaserText', v)} />
          <Text label={t('After how many seconds')} type="number" min={0} max={300} value={d.teaserDelay} placeholder="12" onChange={(v) => set('teaserDelay', v === '' ? null : Number(v))} />
        </div>
      </Card>
    </div>
  );
}

/* ---------------- AI ---------------- */
function Ai({ t, a, d, set }: AdminPaneProps) {
  return (
    <div className="flxcb-pane">
      {!a.aiReady ? (
        <div className="flxcb-callout is-warn">
          <AlertTriangle size={18} />
          <div>
            <strong>{t('The assistant is not working right now.')}</strong> {t('AI calls go through Flux AI & MCP, where the key, model and spending limit live. Until Flux AI is ready, visitor messages go to an agent.')}
          </div>
        </div>
      ) : (
        <div className="flxcb-callout is-info">
          <Info size={18} />
          <div>
            {t('Key, model, spending limit and logs are in Flux AI & MCP. To pick a model just for this plugin:')}{' '}
            {a.aiSettingsUrl && <a href={a.aiSettingsUrl}>{t('Flux AI › Connected systems › Flux AI Chatbot')}</a>}
          </div>
        </div>
      )}
      <Card id="flxcb-brain" icon={<Sparkles size={16} />} title={t('Personality and instructions')}>
        <Area label={t('Personality and tone')} help={t('Example: Be friendly but polite, keep answers short.')} rows={2} value={d.aiPersona} onChange={(v) => set('aiPersona', v)} />
        <Area label={t('Rules')} help={t("What the assistant must follow. Example: Don't give discount codes. Hand business sales questions to an agent.")} rows={4} value={d.aiInstructions} onChange={(v) => set('aiInstructions', v)} />
        <Area
          label={t('Knowledge base')}
          help={t('Information not on your site: shipping, returns, opening hours, FAQs. Plain text; the assistant answers only from here and your site.')}
          rows={9}
          value={d.aiKnowledge}
          placeholder={t('Shipping: 1-3 business days, free over $50.\nReturns: within 14 days with the invoice.\nQ: Do you have a store?\nA: We sell online only.')}
          onChange={(v) => set('aiKnowledge', v)}
        />
      </Card>
      <Card id="flxcb-sources" icon={<BookOpen size={16} />} title={t('Sources and tools')} desc={t('The assistant searches these before answering. Only published, non-password-protected content is read.')}>
        <div className="flxcb-toggles">
          <Switch label={t('Pages')} on={Boolean(d.aiSrcPages)} onChange={(v) => set('aiSrcPages', v)} />
          <Switch label={t('Posts')} on={Boolean(d.aiSrcPosts)} onChange={(v) => set('aiSrcPosts', v)} />
          <Switch label={t('WooCommerce products')} help={t('Price, stock and link; products appear as cards in the window.')} on={Boolean(d.aiSrcProducts)} disabled={!a.shopActive} onChange={(v) => set('aiSrcProducts', v)} />
          <Switch label={t('Product cards')} help={t('Image, price, "View" and "Add to cart".')} on={Boolean(d.aiCards)} disabled={!a.shopActive} onChange={(v) => set('aiCards', v)} />
          <Switch label={t('Flux Wiki pages')} on={Boolean(d.aiSrcWiki)} disabled={!a.wikiActive} onChange={(v) => set('aiSrcWiki', v)} />
          <Switch label={t('Order status')} help={t('A logged-in customer can only ask about their own orders.')} on={Boolean(d.aiToolOrders)} disabled={!a.ordersActive} onChange={(v) => set('aiToolOrders', v)} />
          <Switch label={t('Save contact details')} help={t('If the visitor gives an email or phone, it is saved to the chat (and to Flux CRM when installed).')} on={Boolean(d.aiToolLead)} onChange={(v) => set('aiToolLead', v)} />
        </div>
      </Card>
      <Card id="flxcb-limits" icon={<Shield size={16} />} title={t('Limits')} desc={t('A public AI can burn through the budget fast. The overall spending limit is in Flux AI; these are extra protection.')}>
        <div className="flxcb-grid3">
          <Text label={t('Replies per chat')} help={t('When reached, the chat goes to an agent.')} type="number" min={1} max={200} value={d.aiMaxTurns} placeholder="40" onChange={(v) => set('aiMaxTurns', v === '' ? null : Number(v))} />
          <Text label={t('Per chat per hour')} help={t('0 = unlimited')} type="number" min={0} max={500} value={d.aiHourLimit} placeholder="30" onChange={(v) => set('aiHourLimit', v === '' ? null : Number(v))} />
          <Text label={t('Daily total on the site')} help={t('0 = unlimited')} type="number" min={0} value={d.aiDailyLimit} placeholder="400" onChange={(v) => set('aiDailyLimit', v === '' ? null : Number(v))} />
        </div>
      </Card>
    </div>
  );
}

/* ---------------- Live chat ---------------- */
function Live({ t, a, d, set }: AdminPaneProps) {
  const setDay = (key: string, patch: Partial<Day>) => {
    const next: Record<string, Day> = {};
    for (const w of a.weekdays) next[w.key] = dayOf(d.hours, w.key);
    next[key] = { ...dayOf(d.hours, key), ...patch };
    set('hours', next);
  };
  return (
    <div className="flxcb-pane">
      <Card id="flxcb-target" icon={<Headphones size={16} />} title={t('Handoff to a person')}>
        <Choices
          value={d.handoffTo}
          onChange={(v) => set('handoffTo', v)}
          options={[
            { value: 'AUTO', title: t('Automatic'), text: t('Flux Chat when available, otherwise the plugin inbox.'), icon: <Sparkles size={18} /> },
            { value: 'FLX_CHAT', title: t('Flux Chat'), text: a.fluxChatReady ? t('Call center inbox, together with WhatsApp chats.') : t('Flux Chat 1.37+ is not installed; the inbox is used.'), icon: <MessageCircle size={18} /> },
            { value: 'INBOX', title: t('Plugin inbox'), text: t('Chatbot › Chats screen, with email notification.'), icon: <Inbox size={18} /> },
          ]}
        />
        <div className="flxcb-grid2">
          <Select label={t('Ask for email')} value={d.askEmail} onChange={(v) => set('askEmail', v)} options={[['OFFLINE', t('Only outside working hours')], ['ALWAYS', t('Before handing over')], ['NEVER', t('Never')]]} />
          <Text label={t('Notification email')} help={t('New handoffs and after-hours messages. When empty, the site admin.')} type="email" value={d.notifyEmail} placeholder={a.notifyEmailDefault} onChange={(v) => set('notifyEmail', v)} />
        </div>
        <Area label={t('After-hours message')} rows={2} value={d.offlineText} onChange={(v) => set('offlineText', v)} />
        <div className="flxcb-toggles">
          <Switch label={t('Also send replies by email')} help={t("If the visitor has left the page, the agent's reply goes to their email.")} on={Boolean(d.emailReplies)} onChange={(v) => set('emailReplies', v)} />
          <Switch label={t('End-of-chat rating')} help={t('1-5 stars and an optional note.')} on={Boolean(d.rating)} onChange={(v) => set('rating', v)} />
          <Switch label={t('File sharing')} help={t('Images, PDFs and office documents; files are not put in a public folder.')} on={Boolean(d.uploads)} onChange={(v) => set('uploads', v)} />
          <Switch label={t('Voice dictation')} help={t("A microphone button in the message field: the visitor speaks and the text lands in the field. Uses the browser's speech recognition; the button is hidden where it isn't supported.")} on={Boolean(d.dictation)} onChange={(v) => set('dictation', v)} />
        </div>
        <div className="flxcb-grid3">
          <Text label={t('Largest file (MB)')} type="number" min={1} max={20} value={d.uploadMb} placeholder="5" onChange={(v) => set('uploadMb', v === '' ? null : Number(v))} />
        </div>
      </Card>

      <Card id="flxcb-hours" icon={<Clock size={16} />} title={t('Working hours')} desc={t('Outside working hours visitors see "leave a message" and the agent replies by email. Hours follow the site time zone.')}>
        <Choices
          value={d.hoursMode}
          onChange={(v) => set('hoursMode', v)}
          options={[
            { value: 'ALWAYS', title: t('Always open'), icon: <CheckIcon size={18} /> },
            { value: 'CUSTOM', title: t('Weekly hours'), icon: <Clock size={18} /> },
            { value: 'FLX_CHAT', title: t('Flux Chat hours'), text: a.fluxChatActive ? undefined : t('Flux Chat is not installed'), icon: <MessageCircle size={18} /> },
          ]}
        />
        {d.hoursMode === 'CUSTOM' && (
          <table className="flxcb-hours">
            <tbody>
              {a.weekdays.map((w) => {
                const day = dayOf(d.hours, w.key);
                return (
                  <tr key={w.key}>
                    <th scope="row">
                      <label>
                        <input type="checkbox" checked={day.on} onChange={(e) => setDay(w.key, { on: e.target.checked })} /> {w.label}
                      </label>
                    </th>
                    <td>
                      <input type="time" value={day.from} aria-label={t('From')} onChange={(e) => setDay(w.key, { from: e.target.value })} /> –{' '}
                      <input type="time" value={day.to} aria-label={t('To')} onChange={(e) => setDay(w.key, { to: e.target.value })} />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}

/* ---------------- Visibility ---------------- */
const pageField = (key: string): keyof S => ('showOn' + key.charAt(0).toUpperCase() + key.slice(1)) as keyof S;

function Visibility({ t, a, d, set }: AdminPaneProps) {
  return (
    <div className="flxcb-pane">
      <Card id="flxcb-where" icon={<Eye size={16} />} title={t('Where to show')}>
        <div className="flxcb-checks">
          {a.pageTypes.map((p) => {
            const f = pageField(p.key);
            return <Check key={p.key} label={p.label} on={Boolean(d[f])} onChange={(v) => set(f, v as S[typeof f])} />;
          })}
        </div>
        <div className="flxcb-grid2">
          <Select label={t('Who sees it')} value={d.audience} onChange={(v) => set('audience', v)} options={[['ALL', t('Everyone')], ['GUESTS', t('Only visitors (not logged in)')], ['MEMBERS', t('Only members (logged in)')]]} />
          <div className="flxcb-field">
            <span>{t('Device')}</span>
            <div className="flxcb-checks">
              <Check label={t('Desktop')} on={Boolean(d.deviceDesktop)} onChange={(v) => set('deviceDesktop', v)} />
              <Check label={t('Mobile')} on={Boolean(d.deviceMobile)} onChange={(v) => set('deviceMobile', v)} />
            </div>
          </div>
        </div>
        <Area label={t('Hidden on these addresses')} help={t('One path per line. * is a wildcard. Example: /checkout/* or /campaign')} rows={3} value={d.excludeUrls} placeholder={'/checkout/*\n/landing'} onChange={(v) => set('excludeUrls', v)} />
      </Card>
      <Card id="flxcb-privacy" icon={<Shield size={16} />} title={t('Privacy')} desc={t("The visitor's IP address is not stored; the chat is matched by a random key in the browser.")}>
        <div className="flxcb-grid2">
          <Text label={t('Note under the message field')} help={t('Example: Chats are kept under our <a href="/privacy">Privacy Policy</a>. Links are allowed.')} value={d.privacyText} onChange={(v) => set('privacyText', v)} />
          <Text label={t('Retention (days)')} help={t('Expired chats and their files are deleted. 0 = keep.')} type="number" min={0} value={d.retentionDays} placeholder="180" onChange={(v) => set('retentionDays', v === '' ? null : Number(v))} />
        </div>
      </Card>
    </div>
  );
}

/* ---------------- Live preview (the real chat window) ---------------- */
function previewConfig(a: Admin, d: S, avatarUrl: string | null): Record<string, unknown> {
  const base = (a.preview.config ?? {}) as Record<string, unknown> & { ai?: boolean; live?: boolean; home?: { team?: unknown[] } };
  const mode = raw(d.mode, 'hybrid');
  const def = (k: string) => a.textDefaults.find((x) => x.key === k)?.text ?? '';
  const txt = (v: string | null, k: string) => (v ?? '').trim() || def(k);
  const color = hex(d.color);
  const tcol = hex(d.colorText);
  return {
    mode,
    ai: mode !== 'live' && Boolean(base.ai),
    live: mode !== 'ai' && Boolean(base.live),
    botName: txt(d.botName, 'bot_name'),
    avatar: d.avatarId ? avatarUrl ?? '' : '',
    avatarIcon: raw(d.avatarIcon, 'bot'),
    launcher: { icon: raw(d.launcherIcon, 'chat'), label: (d.launcherLabel ?? '').trim(), size: d.launcherSize ?? 56 },
    color,
    colorText: tcol || (color ? contrast(color) : ''),
    theme: raw(d.theme, 'auto'),
    position: d.position === 'LEFT' ? 'left' : 'right',
    offsetX: d.offsetX ?? 20,
    offsetY: d.offsetY ?? 20,
    radius: d.radius ?? 18,
    width: d.width ?? 380,
    header: d.headerStyle === 'SOLID' ? 'solid' : 'gradient',
    font: d.font === 'SYSTEM' ? 'system' : 'inherit',
    mobileFull: Boolean(d.mobileFull),
    branding: Boolean(d.showBranding),
    sound: false,
    home: {
      enabled: Boolean(d.homeEnabled),
      title: txt(d.homeTitle, 'home_title'),
      text: txt(d.homeText, 'home_text'),
      team: d.showTeam && mode !== 'ai' ? base.home?.team ?? [] : [],
      links: links(d.links),
      whatsapp: whatsapp(d, txt(d.whatsappLabel, 'whatsapp_label')),
    },
    quick: lines(d.quickReplies).slice(0, 6),
    welcome: mode === 'live' ? '' : txt(d.welcome, 'welcome'),
    placeholder: txt(d.placeholder, 'placeholder'),
    teaser: { enabled: Boolean(d.teaserEnabled), text: txt(d.teaserText, 'teaser_text'), delay: d.teaserDelay ?? 12 },
    offlineText: txt(d.offlineText, 'offline_text'),
    askEmail: raw(d.askEmail, 'offline'),
    rating: Boolean(d.rating),
    uploads: Boolean(d.uploads),
    uploadMb: d.uploadMb ?? 5,
    dictation: Boolean(d.dictation),
    privacy: (d.privacyText ?? '').trim(),
  };
}

function Preview({ t, a, d, avatarUrl, view, setView }: { t: Tr; a: Admin; d: S; avatarUrl: string | null; view: View; setView: (v: View) => void }) {
  const stage = useRef<HTMLDivElement | null>(null);
  const [ready, setReady] = useState(false);
  const cfg = useMemo(() => previewConfig(a, d, avatarUrl), [a, d, avatarUrl]);

  /* The chat window script builds itself inside the stage; load it fresh each time the screen opens. */
  useEffect(() => {
    const w = window as unknown as WpWindow;
    const el = stage.current;
    if (!el) return;
    delete w.FluxChatbot;
    w.flxcbConfig = JSON.parse(JSON.stringify(a.preview.config ?? {}));
    el.style.setProperty('--flxcb-stage-h', el.clientHeight + 'px');
    el.style.setProperty('--flxcb-stage-w', el.clientWidth + 'px');
    const s = document.createElement('script');
    s.src = a.preview.scriptUrl;
    s.async = true;
    s.onload = () => setReady(true);
    document.body.appendChild(s);
    const resize = () => {
      el.style.setProperty('--flxcb-stage-h', el.clientHeight + 'px');
      el.style.setProperty('--flxcb-stage-w', el.clientWidth + 'px');
    };
    window.addEventListener('resize', resize);
    return () => {
      window.removeEventListener('resize', resize);
      s.remove();
      delete w.FluxChatbot;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [a.preview.scriptUrl]);

  useEffect(() => {
    if (!ready) return;
    const h = window.setTimeout(() => {
      const f = (window as unknown as WpWindow).FluxChatbot;
      if (!f) return;
      f.update(cfg);
      if (view === 'closed') f.close();
      else if (view === 'teaser') f.teaser();
      else f.view?.(view);
    }, 120);
    return () => window.clearTimeout(h);
  }, [ready, cfg, view]);

  const views: Array<[View, string]> = [
    ['home', t('Home screen')],
    ['chat', t('Chats')],
    ['teaser', t('Bubble')],
    ['closed', t('Closed')],
  ];
  return (
    <div className="flxcb-preview">
      <div className="flxcb-preview__bar">
        <strong>{t('Preview')}</strong>
        <span className="flxcb-seg" role="group">
          {views.map(([k, label]) => (
            <button key={k} type="button" className={view === k ? 'is-on' : ''} onClick={() => setView(k)}>
              {label}
            </button>
          ))}
        </span>
      </div>
      <div className="flxcb-stage" data-flxcb-stage ref={stage}>
        <div className="flxcb-fake" aria-hidden="true">
          <i />
          <i />
          <i className="s" />
          <b />
          <b />
          <b className="s" />
        </div>
      </div>
      <p className="flxcb-muted flxcb-preview__note">{t('Messages you type in the preview go to the real assistant and show up in Chats.')}</p>
    </div>
  );
}
