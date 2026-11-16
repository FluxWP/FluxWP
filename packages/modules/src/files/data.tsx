// Flux Files: data helpers. Folder pages (the server pages at 50), GraphQL payload errors, URLs, file icons.
import { createContext, useContext } from 'react';
import type { GraphQLClient } from '@fluxwp/graphql';
import { FlxFilesFolder } from '@fluxwp/graphql/operations';
import type { FlxFilesFolderQuery, FlxFilesOrder, FlxFilesSort } from '@fluxwp/graphql/types';
import { Ic } from './icons';
import { badge, type Item } from './util';

export interface Cfg {
  rootPath: string;
  rootName: string;
  uploadsPath: string;
  uploadsUrl: string;
  homeUrl: string;
  fileUrl: string;
  host: string;
  writable: boolean;
  chunkSize: number;
  aiReady: boolean;
}

export interface Settings {
  trashDays: number;
  versions: number;
  quotaGb: number;
  showHidden: boolean;
  mcpWrite: boolean;
  aiCode: boolean;
  shared: string[];
}

export interface FilesEnv {
  cfg: Cfg;
  settings: Settings;
  client: GraphQLClient;
  scope: string;
}
const EnvCtx = createContext<FilesEnv | null>(null);
export const EnvProvider = EnvCtx.Provider;
export const useEnv = (): FilesEnv => {
  const e = useContext(EnvCtx);
  if (!e) throw new Error('FilesEnv missing');
  return e;
};

export interface FolderData {
  path: string;
  parent: string | null;
  dir: Item;
  items: Item[];
  total: number;
}

const PAGE = 50;
const CAP = 5000;

/** Every entry of a folder (the server pages at 50): folders first, sorted on the server. */
export async function loadFolder(
  client: GraphQLClient,
  path: string,
  o: { sort?: FlxFilesSort; order?: FlxFilesOrder; hidden?: boolean; foldersOnly?: boolean; countItems?: boolean } = {},
  signal?: AbortSignal,
): Promise<FolderData> {
  const items: Item[] = [];
  let after: string | null = null;
  let head: FolderData | null = null;
  for (;;) {
    const r: FlxFilesFolderQuery = await client.request(
      FlxFilesFolder,
      { path, first: PAGE, after, sort: o.sort ?? null, order: o.order ?? null, hidden: o.hidden ?? null, foldersOnly: o.foldersOnly ?? null, countItems: o.countItems ?? null },
      signal ? { signal } : undefined,
    );
    const f = r.flxFilesFolder;
    head ??= { path: f.path, parent: f.parent, dir: f.dir, items, total: f.items.totalCount ?? 0 };
    items.push(...f.items.nodes);
    if (!f.items.pageInfo.hasNextPage || items.length >= CAP) break;
    after = f.items.pageInfo.endCursor;
  }
  return head;
}

/** A mutation payload that carries user errors: the first one becomes an exception. */
export class PayloadError extends Error {
  constructor(
    public code: string,
    message: string,
  ) {
    super(message);
  }
}
export function ok<T extends { errors?: ReadonlyArray<{ code: string; message: string }> | null }>(p: T | null | undefined): T {
  if (!p) throw new PayloadError('INTERNAL', '');
  const e = p.errors?.[0];
  if (e) throw new PayloadError(e.code, e.message);
  return p;
}
export const errText = (e: unknown, fallback: string): string => (e instanceof Error && e.message ? e.message : fallback);
export const errCode = (e: unknown): string => (typeof e === 'object' && e !== null && 'code' in e ? String((e as { code: unknown }).code) : '');

/* ---------------------------------------------------------------------- urls */
export const enc2 = (p: string): string => encodeURIComponent(encodeURIComponent(p));

export function rawUrl(cfg: Cfg, p: string, download = false): string {
  const up = cfg.uploadsPath;
  if (!download && up && (p === up || p.startsWith(up + '/'))) return cfg.uploadsUrl + p.slice(up.length).split('/').map(encodeURIComponent).join('/');
  return cfg.fileUrl + '&path=' + enc2(p) + (download ? '' : '&inline=1');
}
export function download(cfg: Cfg, paths: string[]): void {
  const u = paths.length === 1 ? cfg.fileUrl + '&path=' + enc2(paths[0] ?? '') : cfg.fileUrl + '&paths=' + enc2(paths.join('\n'));
  const a = document.createElement('a');
  a.href = u;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
}
export const publicUrl = (cfg: Cfg, p: string): string => cfg.homeUrl + p.split('/').map(encodeURIComponent).join('/');

export function copyText(s: string, done: () => void): void {
  const legacy = () => {
    const a = document.createElement('textarea');
    a.value = s;
    document.body.appendChild(a);
    a.select();
    try {
      document.execCommand('copy');
      done();
    } catch {
      /* ignore */
    }
    a.remove();
  };
  if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(s).then(done, legacy);
  else legacy();
}

/* ---------------------------------------------------------------- file icon */
export function FileIcon({ it, big, cfg }: { it: Pick<Item, 'name' | 'extension' | 'kind' | 'isDir' | 'shared'> & { size?: number | null; path?: string }; big?: boolean; cfg?: Cfg }) {
  if (it.isDir) {
    return (
      <span className="flxfm-fi flxfm-fi--dir">
        <Ic n="folder" />
        {it.shared ? (
          <span className="flxfm-fi-badge">
            <Ic n="globe" />
          </span>
        ) : null}
      </span>
    );
  }
  if (big && cfg && it.path && it.kind === 'IMAGE' && (it.size ?? 0) < 8 * 1024 * 1024) {
    return (
      <span className="flxfm-fi flxfm-fi--thumb">
        <img loading="lazy" alt="" src={rawUrl(cfg, it.path)} />
      </span>
    );
  }
  const b = badge(it);
  if (b.gear) {
    return (
      <span className="flxfm-fi flxfm-fi--c-orange">
        <Ic n="settings" />
      </span>
    );
  }
  if (!b.text) {
    return (
      <span className="flxfm-fi flxfm-fi--c-slate">
        <Ic n="file" />
      </span>
    );
  }
  return <span className={'flxfm-fi flxfm-fi--ext flxfm-fi--c-' + b.color}>{b.text}</span>;
}
