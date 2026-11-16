// Shared pieces of the Backups screen: the context the panels use, byte formatting, the type badge.
import { createContext, useContext } from 'react';
import type { FlxBackupHealthFieldsFragment, FlxBackupJobFieldsFragment, FlxBackupRowFragment } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Job = FlxBackupJobFieldsFragment;
export type Row = FlxBackupRowFragment;
export type Health = FlxBackupHealthFieldsFragment;
export type Page = 'dashboard' | 'list' | 'settings' | `detail:${string}`;

export interface Ctx {
  t: Translate;
  tn: (single: string, plural: string, n: number, ...args: (string | number)[]) => string;
  lang: string;
  go: (page: Page) => void;
  openBackup: () => void;
  openRestore: (row: Row) => void;
  askDelete: (ids: string[], then?: () => void) => void;
  told: (text: string) => void;
  failed: (e: unknown) => void;
  /** A job runs: dialogs that start another job are blocked. */
  busy: boolean;
  /** Refetch everything that shows backups. */
  refresh: () => void;
}

export const BackupCtx = createContext<Ctx | null>(null);
export const useBackup = (): Ctx => {
  const c = useContext(BackupCtx);
  if (!c) throw new Error('BackupCtx missing');
  return c;
};

export function bytes(n: number, lang: string): string {
  const u = ['B', 'KB', 'MB', 'GB', 'TB'];
  let i = 0;
  let v = n;
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024;
    i++;
  }
  return new Intl.NumberFormat(lang, { maximumFractionDigits: i ? 1 : 0 }).format(v) + ' ' + (u[i] ?? 'B');
}

export function Badge({ label, color }: { label: string; color: string }) {
  return (
    <span className="flxy-badge" style={{ ['--c' as string]: color }}>
      {label}
    </span>
  );
}

export const errText = (e: unknown, t: Translate): string => (e instanceof Error && e.message ? e.message : t('An error occurred.'));
