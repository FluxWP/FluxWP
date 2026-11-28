// Shared pieces of the Social Media screen: context, helpers, small components.
import { createContext, useContext, type ReactNode } from 'react';
import type {
  FlxSocialAccountRowFragment,
  FlxSocialBootQuery,
  FlxSocialMediaRowFragment,
  FlxSocialPostRowFragment,
  FlxSocialPostStatus,
  FlxSocialTargetRowFragment,
  FlxSocialTargetStatus,
} from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Boot = FlxSocialBootQuery;
export type Account = FlxSocialAccountRowFragment;
export type Post = FlxSocialPostRowFragment;
export type Target = FlxSocialTargetRowFragment;
export type Media = FlxSocialMediaRowFragment;
export type Network = Boot['flxSocialConfig']['networks'][number];
export type Page = 'dashboard' | 'posts' | 'approvals' | 'calendar' | 'accounts' | 'settings';
export type Query = Record<string, string>;

export interface Ctx {
  t: Translate;
  boot: Boot;
  accounts: Account[];
  net: (id: string) => Network | undefined;
  go: (page: Page, query?: Query) => void;
  edit: (post: Post | null, preset?: { scheduledAt?: number }) => void;
  detail: (post: Post) => void;
  toast: (text: string, kind?: 'ok' | 'error') => void;
  confirm: (o: { title: string; text: string; action: string; danger?: boolean; extra?: ReactNode; run: () => void }) => void;
  reloadBoot: () => void;
  refreshPosts: () => void;
  locale: string;
  fmt: (ts: number, style?: 'date' | 'time' | 'datetime' | 'day') => string;
  inputToTs: (v: string) => number;
  tsToInput: (ts: number) => string;
  openUrl: (url: string) => void;
  appMode: boolean;
  /** Header slot for page controls (portal target), null until mounted. */
  topSlot: HTMLElement | null;
}

export const SocialCtx = createContext<Ctx | null>(null);
export function useSocial(): Ctx {
  const c = useContext(SocialCtx);
  if (!c) throw new Error('SocialCtx missing');
  return c;
}

export const STATUS_KEYS: Record<FlxSocialPostStatus, string> = {
  DRAFT: 'Draft',
  PENDING: 'Awaiting approval',
  SCHEDULED: 'Scheduled',
  PUBLISHING: 'Publishing',
  PUBLISHED: 'Published',
  PARTIAL: 'Partially published',
  FAILED: 'Failed',
  CANCELLED: 'Cancelled',
};

export const TARGET_KEYS: Record<FlxSocialTargetStatus, string> = {
  DRAFT: 'Draft',
  SCHEDULED: 'Scheduled',
  PROCESSING: 'Sending',
  PUBLISHED: 'Published',
  FAILED: 'Failed',
  CANCELLED: 'Cancelled',
  DELETED: 'Deleted from network',
};

export function StatusBadge({ status, label }: { status: string; label: string }) {
  return <span className={'flxsm-st is-' + status.toLowerCase()}>{label}</span>;
}

/** Network mark: a round chip in the brand colour with the first letter. */
export function NetDot({ provider, size = 22 }: { provider: string; size?: number }) {
  const { net } = useSocial();
  const n = net(provider);
  return (
    <span className="flxsm-net" title={n?.label ?? provider} style={{ background: n?.color ?? '#888', width: size, height: size, fontSize: Math.round(size * 0.5) }}>
      {(n?.label ?? provider).slice(0, 1)}
    </span>
  );
}

export function Avatar({ src, name, size = 32 }: { src: string; name: string; size?: number }) {
  return src ? (
    <img className="flxsm-av" src={src} alt="" width={size} height={size} loading="lazy" />
  ) : (
    <span className="flxsm-av is-letter" style={{ width: size, height: size, fontSize: Math.round(size * 0.45) }}>
      {name.slice(0, 1).toUpperCase()}
    </span>
  );
}

export function errorText(e: unknown, fallback: string): string {
  return e instanceof Error && e.message ? e.message : fallback;
}

/** First payload error message, if any. */
export function payloadError(errors: ReadonlyArray<{ message: string }> | undefined | null): string | null {
  return errors && errors.length ? errors.map((e) => e.message).join(' ') : null;
}

export const num = (n: number, locale: string) => new Intl.NumberFormat(locale).format(n);

export function excerpt(p: Post, t: Translate): string {
  return p.title || p.content.replace(/\s+/g, ' ').trim().slice(0, 140) || t('(no text)');
}

/** Time a post is shown at: published, scheduled or created. */
export function postTime(p: Post): number {
  return p.publishedAt?.timestamp || p.scheduledAt?.timestamp || p.created?.timestamp || 0;
}

export const isOpen = (s: FlxSocialTargetStatus) => s === 'DRAFT' || s === 'SCHEDULED' || s === 'FAILED' || s === 'CANCELLED';
