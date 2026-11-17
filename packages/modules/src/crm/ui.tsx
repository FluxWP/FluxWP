// Flux CRM screen: shared context (texts, vocabulary, notices, routing) and small building blocks.
import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
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
  classicUrl?: string;
  adminUrl?: string;
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
  shield: ShieldCheck, user: User, download: Download, pin: MapPin, edit: Pencil, trash: Trash2, x: X,
};
export function Ic({ n, size = 15 }: { n: string; size?: number }) {
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
  return (
    <header className={'flxcrm-head' + (back ? ' has-back' : '')}>
      {back && (
        <a className="flxcrm-backbtn" href={href(back.to)} aria-label={back.label} title={back.label}>
          <Ic n="back" size={18} />
        </a>
      )}
      <div className="flxcrm-head__text">
        <h1>{title}</h1>
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
