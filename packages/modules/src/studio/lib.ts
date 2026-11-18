// Shared types and helpers of the Studio screen.
import type { ApiClient } from '@fluxwp/core';
import type { FlxStudioItemRowFragment, FlxStudioKind, FlxStudioRefType, FlxStudioWorkspaceQuery } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Item = FlxStudioItemRowFragment;
export type Workspace = FlxStudioWorkspaceQuery['flxStudio'];
export type Model = Workspace['catalog']['models'][number];
export type Folder = Workspace['folders'][number];
export type Person = Workspace['people'][number];
export type SavedRef = Workspace['refs'][number];
export type Template = Workspace['templates'][number];
export type Budget = Workspace['budget'][number];
export type ItemRef = Item['params']['refs'][number];

export type Kind = 'image' | 'video';
export const toKind = (k: Kind): FlxStudioKind => (k === 'video' ? 'VIDEO' : 'IMAGE');
export const fromKind = (k: FlxStudioKind): Kind => (k === 'VIDEO' ? 'video' : 'image');

export interface Filter {
  folder: 'all' | number;
  kind: '' | Kind;
  fav: boolean;
  q: string;
  owner: 'me' | 'all';
}

/** A reference in the generation bar. `id` is a string while the photo is still uploading. */
export interface CRef {
  type: FlxStudioRefType;
  id: number | string;
  role: string;
  name: string;
  thumb: string;
}

export interface Cfg {
  kind: Kind;
  model: string;
  provider: string;
  aspect: string;
  resolution: string;
  quality: string;
  count: number;
  duration: number;
  audio: boolean;
  prompt: string;
  enhanced: boolean;
  draft: string;
}

export const isPending = (it: Item): boolean => it.state === 'QUEUED' || it.state === 'RUNNING';
export const isDone = (it: Item): boolean => it.state === 'DONE';
export const ROLE_KEYS = ['', 'identity', 'style', 'product', 'background', 'composition', 'first_frame', 'last_frame'] as const;

export function roleLabel(t: Translate, k: string): string {
  switch (k) {
    case '':
      return t('General');
    case 'identity':
      return t('Person (face/identity)');
    case 'style':
      return t('Style');
    case 'product':
      return t('Product');
    case 'background':
      return t('Background');
    case 'composition':
      return t('Composition');
    case 'first_frame':
      return t('First frame');
    case 'last_frame':
      return t('Last frame');
    default:
      return k;
  }
}

export function qualityLabel(t: Translate, q: string): string {
  switch (q) {
    case 'auto':
      return t('Auto');
    case 'low':
      return t('Low');
    case 'medium':
      return t('Medium');
    case 'high':
      return t('High');
    default:
      return q;
  }
}

export const num = (v: unknown, d = 0): number => (typeof v === 'number' && Number.isFinite(v) ? v : d);

export function loadPref<T>(key: string): T | null {
  try {
    return JSON.parse(localStorage.getItem('flxst.' + key) ?? 'null') as T | null;
  } catch {
    return null;
  }
}
export function savePref(key: string, v: unknown): void {
  try {
    localStorage.setItem('flxst.' + key, JSON.stringify(v));
  } catch {
    /* private mode */
  }
}

export const DEFAULT_CFG: Cfg = { kind: 'image', model: '', provider: 'auto', aspect: '', resolution: '', quality: '', count: 1, duration: 5, audio: true, prompt: '', enhanced: false, draft: '' };

export function storedCfg(): Cfg {
  const s = loadPref<Partial<Cfg> & { n?: number }>('composer') ?? {};
  return { ...DEFAULT_CFG, ...s, count: s.count ?? s.n ?? 1, prompt: '', enhanced: false, draft: '' };
}

export const modelsOf = (ws: Workspace, kind: Kind): Model[] => ws.catalog.models.filter((m) => m.kind === toKind(kind));
export const modelById = (ws: Workspace, id: string): Model | undefined => ws.catalog.models.find((m) => m.id === id);
export const providerLabel = (ws: Workspace, id: string): string => ws.catalog.providers.find((p) => p.id === id)?.label ?? id;

const EMPTY_MODEL: Model = { id: '', label: '', kind: 'IMAGE', routes: [], providers: [], auto: null, aspects: [], resolutions: [], qualities: [], durationMin: null, durationMax: null, durationDefault: null, maxRefs: 14, maxCount: 1, mask: false, prices: [] };

/** Brings the stored settings in line with the model's abilities. */
export function normalizeCfg(c: Cfg, ws: Workspace): { cfg: Cfg; model: Model } {
  const out: Cfg = { ...c, kind: c.kind === 'video' ? 'video' : 'image' };
  const list = modelsOf(ws, out.kind);
  const cur = modelById(ws, out.model);
  if (!cur || cur.kind !== toKind(out.kind)) {
    const def = out.kind === 'video' ? ws.settings.videoModel || ws.catalog.defaultVideo : ws.settings.imageModel || ws.catalog.defaultImage;
    const dm = def ? modelById(ws, def) : undefined;
    const usable = list.filter((m) => m.providers.length);
    out.model = dm && dm.providers.length && dm.kind === toKind(out.kind) ? dm.id : (usable[0] ?? list[0])?.id ?? '';
  }
  const m = modelById(ws, out.model) ?? EMPTY_MODEL;
  if (!m.aspects.includes(out.aspect)) out.aspect = out.kind === 'video' ? (m.aspects.includes('16:9') ? '16:9' : m.aspects[0] ?? '') : m.aspects.includes('1:1') ? '1:1' : m.aspects[0] ?? '';
  if (!m.resolutions.includes(out.resolution)) out.resolution = m.resolutions[out.kind === 'video' ? Math.min(1, m.resolutions.length - 1) : 0] ?? '';
  if (m.qualities.length && !m.qualities.includes(out.quality)) out.quality = m.qualities[0] ?? '';
  const maxN = Math.max(1, Math.min(ws.settings.maxCount, m.maxCount || 1));
  out.count = out.kind === 'video' ? 1 : Math.max(1, Math.min(maxN, Math.round(out.count) || 1));
  if (m.durationMin !== null && m.durationMax !== null) out.duration = Math.max(m.durationMin, Math.min(m.durationMax, Math.round(out.duration) || m.durationDefault || m.durationMin));
  if (out.provider && out.provider !== 'auto' && !m.providers.includes(out.provider)) out.provider = 'auto';
  return { cfg: out, model: m };
}

export function priceOf(m: Model, c: Cfg): number {
  const p = m.prices.find((x) => x.resolution === c.resolution)?.usd;
  if (!p) return 0;
  return c.kind === 'video' ? p * (c.duration || 5) : p * c.count;
}

export function money(n: number, locale: string): string {
  return '$' + (Math.round((n || 0) * 1000) / 1000).toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 3 });
}

export function ago(t: Translate, ts: number, locale: string): string {
  const d = Math.max(0, Date.now() / 1000 - ts);
  if (d < 60) return t('just now');
  if (d < 3600) return t('{n} min ago', { n: Math.floor(d / 60) });
  if (d < 86400) return t('{n} h ago', { n: Math.floor(d / 3600) });
  return new Date(ts * 1000).toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
}

export function norm(s: string, locale: string): string {
  return s.toLocaleLowerCase(locale).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
}

export function ratioOf(it: Item): string {
  if (it.width && it.height) return `${it.width} / ${it.height}`;
  const m = /^(\d+):(\d+)$/.exec(it.params.aspect ?? '1:1');
  return m ? `${m[1]} / ${m[2]}` : it.kind === 'VIDEO' ? '16 / 9' : '1 / 1';
}

/** Payload with `errors`: throws the first error so callers use one try/catch. */
export async function call<P extends { errors: Array<{ message: string }> } | null | undefined>(p: Promise<P>): Promise<NonNullable<P>> {
  const r = await p;
  if (!r) throw new Error('');
  if (r.errors.length) throw new Error(r.errors[0]?.message ?? '');
  return r;
}

/** Sends a file to the shared upload endpoint; the returned ID goes into the mutation. */
export async function uploadFile(api: ApiClient, context: string, file: Blob, name: string): Promise<string> {
  const fd = new FormData();
  fd.append('context', context);
  fd.append('file', file, name);
  const r = await api<{ uploadId: string }>('flx/v1/upload', { method: 'POST', formData: fd });
  return r.uploadId;
}

export function dataUrlToBlob(url: string): Blob {
  const [head, body = ''] = url.split(',');
  const mime = /data:([^;]+)/.exec(head ?? '')?.[1] ?? 'image/png';
  const bin = atob(body);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return new Blob([bytes], { type: mime });
}

export function refPayload(refs: CRef[]): Array<{ type: FlxStudioRefType; id: number; role: string }> {
  return refs.flatMap((r) => (typeof r.id === 'number' ? [{ type: r.type, id: r.id, role: r.role }] : []));
}
