// Shared pieces of the Donations screen: context, the list filter as the classic URL arguments, icons.
import { createContext, useContext, type MouseEvent, type ReactNode } from 'react';
import type { FlxDonationListFilter } from '@fluxwp/graphql/types';
import type { Translate, TranslateN } from './i18n';

export type Page = 'dashboard' | 'list' | 'detail' | 'donors' | 'donor' | 'subscriptions' | 'types' | 'type' | 'add';
export type Notice = { kind: 'ok' | 'error'; text: string };

export interface Ctx {
  t: Translate;
  tn: TranslateN;
  lang: string;
  told: (n: Notice | null) => void;
  open: (url: string) => void;
}

export const DonateCtx = createContext<Ctx | null>(null);
export const useDonate = (): Ctx => {
  const c = useContext(DonateCtx);
  if (!c) throw new Error('DonateCtx missing');
  return c;
};

const STATUSES = ['completed', 'pending', 'receipt', 'payment', 'failed', 'canceled', 'refunded', 'partially_refunded', 'trash'] as const;
const DAY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * The classic list arguments (s, from, to, status, type, dtype, form, donor_id, ref) → the GraphQL filter.
 * Only set keys are kept, so the variables are the same as the ones PHP preloads
 * (FLXB_Admin::flux_list_filter()).
 */
export function filterFromQuery(q: URLSearchParams): FlxDonationListFilter {
  const f: FlxDonationListFilter = {};
  const s = (q.get('s') ?? '').trim();
  if (s) f.search = s;
  const from = q.get('from') ?? '';
  if (DAY.test(from)) f.from = from;
  const to = q.get('to') ?? '';
  if (DAY.test(to)) f.to = to;
  const st = q.get('status') ?? '';
  if ((STATUSES as readonly string[]).includes(st)) f.status = st.toUpperCase() as FlxDonationListFilter['status'];
  const kind = q.get('type') ?? '';
  if (kind === 'one_time' || kind === 'recurring') f.kind = kind.toUpperCase() as FlxDonationListFilter['kind'];
  const num = (k: string): number | undefined => {
    const v = q.get(k);
    return v !== null && v !== '' && /^\d+$/.test(v) ? Number(v) : undefined;
  };
  const dtype = num('dtype');
  if (dtype !== undefined) f.donationTypeId = dtype;
  const form = num('form');
  if (form !== undefined) f.formId = form;
  const donor = num('donor_id');
  if (donor !== undefined) f.donorId = donor;
  const ref = num('ref');
  if (ref !== undefined) f.referralId = ref;
  return f;
}

/** The GraphQL filter → the classic URL arguments (address bar, bookmarks). */
export function filterToQuery(f: FlxDonationListFilter): URLSearchParams {
  const q = new URLSearchParams();
  if (f.search) q.set('s', f.search);
  if (f.from) q.set('from', f.from);
  if (f.to) q.set('to', f.to);
  if (f.status) q.set('status', f.status.toLowerCase());
  if (f.kind) q.set('type', f.kind.toLowerCase());
  if (f.donationTypeId != null) q.set('dtype', String(f.donationTypeId));
  if (f.formId != null) q.set('form', String(f.formId));
  if (f.donorId != null) q.set('donor_id', String(f.donorId));
  if (f.referralId != null) q.set('ref', String(f.referralId));
  return q;
}

/** Filter variables: an empty filter is left out (same key as the PHP preload). */
export const filterVar = (f: FlxDonationListFilter): FlxDonationListFilter | undefined => (Object.keys(f).length ? f : undefined);

/** Offset cursor of the Flux GraphQL kit (flx_gql_cursor): the page after `offset - 1`. */
export const cursorAt = (offset: number): string | null => (offset > 0 ? btoa('flxo:' + (offset - 1)) : null);

const PATHS: Record<string, string> = {
  heart: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  plus: 'M12 5v14M5 12h14',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3',
  file: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6',
  repeat: 'm17 2 4 4-4 4M3 11v-1a4 4 0 0 1 4-4h14m-14 16-4-4 4-4m14-1v1a4 4 0 0 1-4 4H3',
  flag: 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
};

export function Ico({ name, size = 16 }: { name: keyof typeof PATHS | string; size?: number }) {
  return (
    <svg className="flxdn-ico" viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      <path d={PATHS[name] ?? ''} />
    </svg>
  );
}

/** A link that opens through the host (another Flux screen opens in place) on a plain click. */
export function Go({ href, className, children, title }: { href: string; className?: string; children: ReactNode; title?: string }) {
  const { open } = useDonate();
  const click = (e: MouseEvent<HTMLAnchorElement>) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    open(href);
  };
  return (
    <a href={href} className={className} title={title} onClick={click}>
      {children}
    </a>
  );
}

/** Previous / next page of a list (offset pages of `per`). */
export function Pager({ offset, per, total, hasNext, onGo }: { offset: number; per: number; total: number; hasNext: boolean; onGo: (offset: number) => void }) {
  const { t } = useDonate();
  const pages = Math.max(1, Math.ceil(total / per));
  if (pages <= 1) return null;
  const page = offset / per + 1;
  return (
    <nav className="flxdn-pager" aria-label={t('Pages')}>
      <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={page <= 1} onClick={() => onGo(Math.max(0, offset - per))} aria-label={t('Previous page')}>
        ‹
      </button>
      <span>{t('%1$s of %2$s', page, pages)}</span>
      <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={!hasNext} onClick={() => onGo(offset + per)} aria-label={t('Next page')}>
        ›
      </button>
    </nav>
  );
}
