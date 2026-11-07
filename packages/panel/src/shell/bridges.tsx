// Prefs sync (GET/POST /prefs) and desktop notifications from /sync events.
import { useEffect, useRef } from 'react';
import { layoutStore, platform } from '@fluxwp/core';
import { savePrefs, useSync, useUserPrefs, type SyncEvent } from '@fluxwp/runtime';
import { useScope } from '@fluxwp/runtime';
import { create } from 'zustand';

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
