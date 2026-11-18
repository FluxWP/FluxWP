import { create } from 'zustand';

/** `fluxwp://connect?site=<site url>`: a site's "Open in app" button. The link carries only the address, no secret. */
export function parseConnectLink(raw: string): string | null {
  let u: URL;
  try { u = new URL(raw); } catch { return null; }
  if (u.protocol !== 'fluxwp:' || u.hostname !== 'connect') return null;
  const site = u.searchParams.get('site');
  if (!site) return null;
  try {
    const s = new URL(site);
    const local = s.hostname === 'localhost' || s.hostname === '127.0.0.1';
    if (s.protocol !== 'https:' && !(local && s.protocol === 'http:')) return null;
    if (s.username || s.password) return null;
    return (s.origin + s.pathname).replace(/\/+$/, '');
  } catch {
    return null;
  }
}

interface ConnectState {
  pending: { siteUrl: string; seq: number } | null;
  handled: number;
  open(siteUrl: string): void;
  markHandled(): void;
}

export const useConnect = create<ConnectState>((set, get) => ({
  pending: null,
  handled: 0,
  open: (siteUrl) => set({ pending: { siteUrl, seq: (get().pending?.seq ?? 0) + 1 } }),
  markHandled: () => set({ handled: get().pending?.seq ?? get().handled }),
}));

/** Called by the shell (Tauri) for every incoming link; other links are ignored. */
export function openConnectLink(raw: string): boolean {
  const site = parseConnectLink(raw);
  if (!site) return false;
  useConnect.getState().open(site);
  return true;
}
