// Flux CRM screen: shared context (texts, vocabulary, notices, routing) and small building blocks.
import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  BarChart3,
  Check,
  Clock,
  Coins,
  Download,
  FileText,
  Heart,
  Link2,
  Mail,
  MessageCircle,
  Phone,
  Plus,
  RefreshCw,
  MapPin,
  Pencil,
  Send,
  ShieldCheck,
  ShoppingBag,
  StickyNote,
  Trash2,
  Layers,
  TrendingUp,
  User,
  Users,
  X,
} from 'lucide-react';
import type { FlxCrmShellQuery } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Shell = FlxCrmShellQuery;
export type Notice = { kind: 'ok' | 'bad'; text: string } | null;
export type ErrorLike = { message: string; field?: string | null };

export interface Crm {
  t: Translate;
  tn: (single: string, plural: string, n: number, ...args: (string | number)[]) => string;
  lang: string;
  shell: Shell;
  can: boolean;
  tell: (kind: 'ok' | 'bad', text: string) => void;
  fail: (e: unknown) => void;
  /** Reload vocabulary, tags and saved segments. */
  reloadShell: () => void;
  /** Money in the site currency, short (12.4K). */
  short: (n: number) => string;
  num: (n: number) => string;
  adminUrl?: string;
  /** Page title and lead go to the shared screen header (classic layout: title, lead, buttons, then tabs). */
  setHead?: (h: { title: string; lead?: string; back?: { to: string; label: string } }) => void;
  /** Header button slot; Head portals its actions here. */
  actionsEl?: HTMLElement | null;
}

export const CrmCtx = createContext<Crm | null>(null);
export function useCrm(): Crm {
  const c = useContext(CrmCtx);
  if (!c) throw new Error('CrmCtx missing');
  return c;
}

/** Messages of a mutation payload's errors, joined; '' when there are none. */
export const errText = (errors: ReadonlyArray<ErrorLike> | null | undefined): string => (errors ?? []).map((e) => e.message).join(' ');

/* ---- Hash routing: #/contacts?role=donor, #/contact/12, #/segments/5 ---- */
export interface Route {
  name: string;
  id: string;
  q: URLSearchParams;
}
const parse = (hash: string, start: string): Route => {
  const raw = (hash.replace(/^#\/?/, '') || start).replace(/^\/+/, '');
  const [path = '', query = ''] = raw.split('?');
  const [name = '', id = ''] = path.split('/');
  return { name: name || start, id, q: new URLSearchParams(query) };
};
export function useRoute(start: string): [Route, (to: string) => void] {
  const [route, setRoute] = useState<Route>(() => parse(window.location.hash, start));
  useEffect(() => {
    const on = () => setRoute(parse(window.location.hash, start));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [start]);
  const go = useCallback((to: string) => {
    window.location.hash = '#/' + to.replace(/^#?\/?/, '');
    window.scrollTo?.({ top: 0 });
  }, []);
  return [route, go];
}
export const href = (to: string) => '#/' + to;

/* ---- Icons ---- */
const ICONS: Record<string, typeof Plus> = {
  plus: Plus, users: Users, trend: TrendingUp, alert: AlertTriangle, coins: Coins, chart: BarChart3, badge: BadgeCheck,
  clock: Clock, link: Link2, check: Check, arrow: ArrowRight, back: ArrowLeft, refresh: RefreshCw, note: StickyNote,
  mail: Mail, phone: Phone, message: MessageCircle, send: Send, heart: Heart, bag: ShoppingBag, form: FileText,
  shield: ShieldCheck, user: User, download: Download, pin: MapPin, edit: Pencil, trash: Trash2, x: X, layers: Layers,
};
/** Classic CRM icon set (same SVG as the old PHP pages: flxcrm_icon). */
const PATHS: Record<string, string> = {
  users: "<path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M22 21v-2a4 4 0 0 0-3-3.87\"/><path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/>",
  user: "<circle cx=\"12\" cy=\"8\" r=\"5\"/><path d=\"M20 21a8 8 0 0 0-16 0\"/>",
  plus: "<path d=\"M5 12h14\"/><path d=\"M12 5v14\"/>",
  heart: "<path d=\"M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z\"/>",
  bag: "<path d=\"M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z\"/><path d=\"M3 6h18\"/><path d=\"M16 10a4 4 0 0 1-8 0\"/>",
  form: "<rect width=\"8\" height=\"4\" x=\"8\" y=\"2\" rx=\"1\"/><path d=\"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2\"/><path d=\"M12 11h4\"/><path d=\"M12 16h4\"/><path d=\"M8 11h.01\"/><path d=\"M8 16h.01\"/>",
  message: "<path d=\"M7.9 20A9 9 0 1 0 4 16.1L2 22Z\"/>",
  send: "<path d=\"M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.03.94l7.93 3.18a2 2 0 0 1 1.11 1.11z\"/><path d=\"m21.85 2.15-10.94 10.94\"/>",
  mail: "<rect width=\"20\" height=\"16\" x=\"2\" y=\"4\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/>",
  note: "<path d=\"M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z\"/><path d=\"M15 3v6h6\"/>",
  phone: "<path d=\"M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z\"/>",
  badge: "<path d=\"M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z\"/><path d=\"m9 12 2 2 4-4\"/>",
  chart: "<path d=\"M3 3v16a2 2 0 0 0 2 2h16\"/><path d=\"m19 9-5 5-4-4-3 3\"/>",
  shield: "<path d=\"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z\"/>",
  tag: "<path d=\"M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z\"/><circle cx=\"7.5\" cy=\"7.5\" r=\".5\" fill=\"currentColor\"/>",
  alert: "<path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3\"/><path d=\"M12 9v4\"/><path d=\"M12 17h.01\"/>",
  clock: "<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M12 6v6l4 2\"/>",
  coins: "<circle cx=\"8\" cy=\"8\" r=\"6\"/><path d=\"M18.09 10.37A6 6 0 1 1 10.34 18\"/><path d=\"M7 6h1v4\"/><path d=\"m16.71 13.88.7.71-2.82 2.82\"/>",
  refresh: "<path d=\"M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8\"/><path d=\"M21 3v5h-5\"/><path d=\"M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16\"/><path d=\"M8 16H3v5\"/>",
  download: "<path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><path d=\"m7 10 5 5 5-5\"/><path d=\"M12 15V3\"/>",
  search: "<circle cx=\"11\" cy=\"11\" r=\"8\"/><path d=\"m21 21-4.3-4.3\"/>",
  arrow: "<path d=\"M5 12h14\"/><path d=\"m12 5 7 7-7 7\"/>",
  back: "<path d=\"m12 19-7-7 7-7\"/><path d=\"M19 12H5\"/>",
  trend: "<path d=\"m22 7-8.5 8.5-5-5L2 17\"/><path d=\"M16 7h6v6\"/>",
  filter: "<path d=\"M22 3H2l8 9.46V19l4 2v-8.54L22 3z\"/>",
  link: "<path d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\"/><path d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\"/>",
  x: "<path d=\"M18 6 6 18\"/><path d=\"m6 6 12 12\"/>",
  check: "<path d=\"M20 6 9 17l-5-5\"/>",
  trash: "<path d=\"M3 6h18\"/><path d=\"M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6\"/><path d=\"M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2\"/>",
  edit: "<path d=\"M12 20h9\"/><path d=\"M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z\"/>",
  sparkles: "<path d=\"M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.13a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.13 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z\"/>",
  layers: "<path d=\"m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z\"/><path d=\"m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65\"/><path d=\"m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65\"/>",
  whatsapp: "<path d=\"M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21\"/><path d=\"M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1\"/>",
};
export function Ic({ n, size = 15 }: { n: string; size?: number }) {
  const d = PATHS[n === 'pin' ? 'tag' : n];
  if (d) {
    return (
      <svg className="flxcrm-i" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" dangerouslySetInnerHTML={{ __html: d }} />
    );
  }
  const C = ICONS[n] ?? StickyNote;
  return <C className="flxcrm-i" size={size} aria-hidden="true" />;
}

export function Money({ value, strike }: { value: { formatted: string }; strike?: boolean }) {
  return <strong className={strike ? 'flxcrm-strike' : undefined}>{value.formatted}</strong>;
}

export function Avatar({ name, src, size }: { name: string; src?: string | null; size?: 'sm' | 'lg' }) {
  let h = 0;
  for (const ch of name) h = (h * 31 + ch.codePointAt(0)!) % 360;
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => [...w][0])
    .join('')
    .toUpperCase();
  return (
    <span className={'flxcrm-avatar' + (size ? ' is-' + size : '')} style={{ ['--h' as string]: h }} aria-hidden="true">
      {src ? <img src={src} alt="" loading="lazy" /> : initials || '?'}
    </span>
  );
}

export function RolePills({ keys }: { keys: ReadonlyArray<string> }) {
  const { shell } = useCrm();
  return (
    <>
      {keys.map((k) => {
        const r = shell.flxCrmMeta.roles.find((x) => x.key === k);
        return (
          <span key={k} className="flxcrm-role" style={{ ['--rc' as string]: r?.color ?? '#64748b' }}>
            {r?.label ?? k}
          </span>
        );
      })}
    </>
  );
}

const TONES = new Set(['ok', 'info', 'warn', 'bad']);
export function SegmentBadge({ segment, label }: { segment: string | null; label: string | null }) {
  const { shell } = useCrm();
  if (!segment || !label) return null;
  const tone = shell.flxCrmMeta.rfm.find((r) => r.key === segment)?.tone ?? '';
  return <span className={'flxcrm-badge' + (TONES.has(tone) ? ' is-' + tone : '')}>{label}</span>;
}

export function ChurnMeter({ churn, paid, risk }: { churn: number; paid: boolean; risk: boolean }) {
  if (!paid) return <span className="flxcrm-muted">—</span>;
  const tone = risk ? ' is-bad' : churn >= 50 ? ' is-warn' : '';
  return (
    <>
      <span className={'flxcrm-meter' + tone}>
        <span style={{ width: Math.min(100, Math.max(0, churn)) + '%' }} />
      </span>
      <span className="flxcrm-meter__val">%{churn}</span>
    </>
  );
}

export function Kpi({ icon, label, value, hint, warn }: { icon: string; label: string; value: ReactNode; hint?: string; warn?: boolean }) {
  return (
    <div className={'flxcrm-kpi' + (warn ? ' is-warn' : '')}>
      <span className="flxcrm-kpi__icon">
        <Ic n={icon} size={17} />
      </span>
      <span className="flxcrm-kpi__body">
        <span className="flxcrm-kpi__label">{label}</span>
        <span className="flxcrm-kpi__value">{value}</span>
        {hint ? <span className="flxcrm-kpi__hint">{hint}</span> : null}
      </span>
    </div>
  );
}

export function Card({ title, icon, aside, children, className }: { title?: string; icon?: string; aside?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={'flxcrm-card' + (className ? ' ' + className : '')}>
      {title && (
        <div className="flxcrm-card__head">
          <h2>
            {icon && <Ic n={icon} size={16} />}
            {title}
          </h2>
          {aside}
        </div>
      )}
      {children}
    </section>
  );
}

export function Head({ title, lead, actions, back }: { title: string; lead?: string; actions?: ReactNode; back?: { to: string; label: string } }) {
  const c = useContext(CrmCtx);
  const setHead = c?.setHead;
  const backTo = back?.to;
  const backLabel = back?.label;
  useLayoutEffect(() => {
    setHead?.({ title, lead, ...(backTo !== undefined && backLabel !== undefined ? { back: { to: backTo, label: backLabel } } : {}) });
  }, [setHead, title, lead, backTo, backLabel]);
  if (setHead) {
    // The back button lives in the shared screen header (replaces the module icon), not in the content.
    return (
      <>
        {actions && c?.actionsEl ? createPortal(actions, c.actionsEl) : null}
      </>
    );
  }
  return (
    <header className={'flxcrm-head' + (back ? ' has-back' : '')}>
      {back && (
        <a className="flxcrm-backbtn" href={href(back.to)} aria-label={back.label} title={back.label}>
          <Ic n="back" size={18} />
        </a>
      )}
      <div className="flxcrm-head__text">
        <h2>{title}</h2>
        {lead && <p className="flxcrm-lead">{lead}</p>}
      </div>
      {actions && <div className="flxcrm-head__actions">{actions}</div>}
    </header>
  );
}

export function Empty({ icon = 'users', title, hint, children }: { icon?: string; title: string; hint?: string; children?: ReactNode }) {
  return (
    <div className="flxcrm-card flxcrm-empty-state">
      <Ic n={icon} size={28} />
      <p>
        <strong>{title}</strong>
      </p>
      {hint && <p className="flxcrm-muted">{hint}</p>}
      {children}
    </div>
  );
}

/** Compact "3 donations · 2 orders". */
export function countsLabel(crm: Crm, donations: number, orders: number): string {
  const p: string[] = [];
  if (donations) p.push(crm.tn('%s donation', '%s donations', donations, crm.num(donations)));
  if (orders) p.push(crm.tn('%s order', '%s orders', orders, crm.num(orders)));
  return p.join(' · ');
}

/** Intl time zone for the site timezone ("Europe/Istanbul", "+03:00" or "UTC"); undefined = the browser's. */
export function siteTimeZone(tz: string | null | undefined): string | undefined {
  if (!tz) return undefined;
  try {
    new Intl.DateTimeFormat('en', { timeZone: tz });
    return tz;
  } catch {
    return undefined;
  }
}
