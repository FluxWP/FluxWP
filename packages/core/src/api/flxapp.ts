import type { ApiClient } from './client';
import type { Dashboard, Me } from './types';

export const flxapp = (api: ApiClient) => ({
  me: () => api<Me>('flxapp/v1/me'),
  counts: () => api<Record<string, number>>('flxapp/v1/counts'),
  dashboard: () => api<Dashboard>('flxapp/v1/dashboard'),
  /** Yerel ekranı olmayan menü için tek seferlik panel girişi (60 sn). */
  session: (path: string) => api<{ url: string; expires_in: number }>('flxapp/v1/session', { method: 'POST', body: { path } }),
  logout: () => api<{ ok: boolean }>('flxapp/v1/logout', { method: 'POST' }),
});
