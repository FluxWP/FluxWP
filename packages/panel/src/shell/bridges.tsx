// Prefs sync (GET/POST /prefs) and desktop notifications from /sync events.
import { useEffect, useRef } from 'react';
import { layoutStore, platform } from '@fluxwp/core';
import { qk, savePrefs, useMe, useSync, useUserPrefs, type SyncEvent } from '@fluxwp/runtime';
import { useScope } from '@fluxwp/runtime';
import { create } from 'zustand';
import { useWarm } from './warm';

interface NoteLog {
  items: SyncEvent[];
  add: (events: SyncEvent[]) => void;
}

const keyOf = (e: SyncEvent) => `${e.event}:${e.at ?? ''}:${e.title}`;

export const useNotes = create<NoteLog>((set, get) => ({
  items: [],
  add: (events) => {
    const seen = new Set(get().items.map(keyOf));
    const fresh = events.filter((e) => e.title && !seen.has(keyOf(e)));
    if (fresh.length) set({ items: [...fresh, ...get().items].slice(0, 40) });
  },
}));

/** Applies an empty local layout from the server, and writes local edits back. */
export function PrefsBridge() {
  const { api, account } = useScope();
  const remote = useUserPrefs();
  const applied = useRef(false);
  const echo = useRef(false);

  useEffect(() => {
    if (applied.current || !remote.isSuccess) return;
    applied.current = true;
    const local = layoutStore.getState().byAccount[account.id];
    if (remote.data && (!local || !Object.keys(local).length)) {
      echo.current = true;
      void layoutStore.getState().set(account.id, remote.data);
    }
  }, [remote.isSuccess, remote.data, account.id]);

  useEffect(() => {
    return layoutStore.subscribe((state, prev) => {
      if (echo.current) {
        echo.current = false;
        return;
      }
      const next = state.byAccount[account.id];
      if (!next || next === prev.byAccount[account.id]) return;
      void savePrefs(api, next).catch(() => undefined);
    });
  }, [api, account.id]);

  return null;
}

/** New /sync events → platform().notify. Clicks come back as a window "flux-notify" event. */
export function SyncNotifier() {
  const sync = useSync();
  const add = useNotes((s) => s.add);
  const seen = useRef(new Set<string>());
  useEffect(() => {
    const events = sync.data?.events ?? [];
    add(events);
    for (const ev of events) {
      const id = keyOf(ev);
      if (seen.current.has(id)) continue;
      seen.current.add(id);
      void platform().notify?.({ title: ev.title, body: ev.body, data: { target: ev.target, params: ev.params, site: undefined } });
    }
  }, [sync.data, add]);
  return null;
}

/**
 * Keeps the app in step with the site without an app update. `/sync` carries digests of /me, /config and
 * /manifest; a changed config or manifest (preset or plugin settings) refreshes every cached screen in the
 * background, and a changed plugin version set (/me `versions`) also remounts the kept screens.
 */
export function SiteSync() {
  const { client } = useScope();
  const hashes = useSync().data?.hashes;
  const versions = useMe().data?.versions;
  const last = useRef<Record<string, string> | null>(null);
  const lastVersions = useRef('');

  useEffect(() => {
    if (!hashes) return;
    const prev = last.current;
    last.current = hashes;
    if (!prev) return;
    if (prev.config !== hashes.config || prev.manifest !== hashes.manifest) void client.invalidateQueries();
    else if (prev.me !== hashes.me) void client.invalidateQueries({ queryKey: qk.me() });
  }, [hashes?.me, hashes?.config, hashes?.manifest]); // eslint-disable-line react-hooks/exhaustive-deps

  const vkey = versions ? JSON.stringify(Object.entries(versions).sort()) : '';
  useEffect(() => {
    if (!vkey) return;
    const prev = lastVersions.current;
    lastVersions.current = vkey;
    if (!prev || prev === vkey) return;
    void client.invalidateQueries();
    useWarm.getState().bump();
  }, [vkey, client]);

  return null;
}
