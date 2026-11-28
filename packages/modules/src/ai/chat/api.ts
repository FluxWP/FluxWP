// REST calls of the chat protocol over the host's API client (cookie + nonce in wp-admin, token in the app).
import { useMemo } from 'react';
import { ApiError } from '@fluxwp/core';
import { useHost } from '../../host';
import { ChatError, type Boot, type Change, type ChatData, type DecideResult, type LiveEvent, type ModelInfo, type StepResult, type WsFile, type WsItem, type WsState } from './types';

const NS = 'flx-ai/v1/';

function wrap(e: unknown, netText: string): ChatError {
  if (e instanceof ChatError) return e;
  if (e instanceof ApiError) {
    if (e.status === 0) return new ChatError(netText, e.code, 0, true);
    return new ChatError(e.message, e.code, e.status);
  }
  return new ChatError(netText, 'network', 0, true);
}

/** Reads an NDJSON body; `onEv` gets every event, the `result` line (or a bare `{state}`) is returned. */
async function readStream(res: Response, onEv: (ev: LiveEvent) => void): Promise<StepResult | null> {
  if (!res.body) return (await res.json()) as StepResult;
  const reader = res.body.getReader();
  const dec = new TextDecoder();
  let buf = '';
  let result: StepResult | null = null;
  const line = (raw: string) => {
    const l = raw.trim();
    if (!l) return;
    let ev: { t?: string; state?: string };
    try {
      ev = JSON.parse(l) as typeof ev;
    } catch {
      return;
    }
    if (ev.t === 'result' || (!ev.t && ev.state)) result = ev as unknown as StepResult;
    else onEv(ev as unknown as LiveEvent);
  };
  for (;;) {
    const x = await reader.read();
    if (x.done) break;
    buf += dec.decode(x.value, { stream: true });
    let i: number;
    while ((i = buf.indexOf('\n')) >= 0) {
      line(buf.slice(0, i));
      buf = buf.slice(i + 1);
    }
  }
  line(buf);
  return result;
}

export function useChatApi(netText: string) {
  const host = useHost();
  const c = host.client;
  return useMemo(() => {
    const call = async <T>(path: string, init?: { method?: 'POST'; body?: unknown; query?: Record<string, string | number> }): Promise<T> => {
      try {
        return await c<T>(NS + path, { ...(init?.method ? { method: init.method } : {}), ...(init?.body !== undefined ? { body: init.body } : {}), ...(init?.query ? { query: init.query } : {}) });
      } catch (e) {
        throw wrap(e, netText);
      }
    };
    return {
      boot: () => call<Boot>('boot'),
      models: async (): Promise<ModelInfo[]> => {
        const r = await call<{ models: (ModelInfo & { tools?: boolean })[] }>('models');
        return (r.models || []).filter((m) => m.tools).map((m) => ({ id: m.id, name: m.name, family: m.family || '', ctx: m.ctx || 0, in: m.in, out: m.out, locked: !!m.locked }));
      },
      get: (id: number) => call<ChatData>(`chats/${id}`, { query: { _t: Date.now() } }),
      update: (id: number, body: { model?: string }) => call<ChatData>(`chats/${id}`, { method: 'POST', body }),
      send: (id: number, body: Record<string, unknown>) => call<StepResult>(`chats/${id}/send`, { method: 'POST', body }),
      decide: (id: number, decisions: Record<string, string>, since: number) => call<StepResult>(`chats/${id}/decide`, { method: 'POST', body: { decisions, since } }),
      stop: (id: number, since: number) => call<StepResult>(`chats/${id}/stop`, { method: 'POST', body: { since } }),
      rewind: (id: number, msg: string, mode: 'edit' | 'keep') => call<ChatData>(`chats/${id}/rewind`, { method: 'POST', body: { msg, mode } }),
      /** One agent step, streamed. Resolves with the final state; a dropped stream resolves `null` (the step goes on server side). */
      step: async (id: number, since: number, onEv: (ev: LiveEvent) => void, signal?: AbortSignal): Promise<StepResult | null> => {
        try {
          const res = await c<Response>(`${NS}chats/${id}/step`, { method: 'POST', body: { since, stream: 1 }, as: 'stream', ...(signal ? { signal } : {}) });
          const ct = res.headers.get('content-type') ?? '';
          if (!ct.includes('ndjson')) return (await res.json()) as StepResult;
          return await readStream(res, onEv);
        } catch (e) {
          if (e instanceof ApiError && e.status > 0) throw wrap(e, netText);
          if (e instanceof ChatError) throw e;
          return null;
        }
      },
      upload: async (file: File): Promise<{ key: string; name: string }> => {
        const fd = new FormData();
        fd.append('file', file, file.name);
        try {
          const r = await c<{ key: string }>(NS + 'files', { method: 'POST', formData: fd });
          return { key: r.key, name: file.name };
        } catch (e) {
          throw wrap(e, netText);
        }
      },
      transcribe: async (blob: Blob, lang: string): Promise<string> => {
        const fd = new FormData();
        fd.append('file', blob, 'audio.' + (/wav/.test(blob.type) ? 'wav' : /mp4/.test(blob.type) ? 'm4a' : 'webm'));
        fd.append('lang', lang);
        try {
          return (await c<{ text: string }>(NS + 'transcribe', { method: 'POST', formData: fd })).text;
        } catch (e) {
          throw wrap(e, netText);
        }
      },
      ws: (id: number) => call<WsState>(`chats/${id}/ws`),
      wsSettings: (id: number, access: string, folders: string) => call<WsState>(`chats/${id}/ws`, { method: 'POST', body: { access, folders } }),
      wsList: (id: number, path: string) => call<{ path: string; items: WsItem[] }>(`chats/${id}/ws/list`, { query: { path } }),
      wsFile: (id: number, path: string) => call<WsFile>(`chats/${id}/ws/file`, { query: { path } }),
      wsSave: (id: number, path: string, content: string) => call<{ path: string; content: string }>(`chats/${id}/ws/save`, { method: 'POST', body: { path, content } }),
      change: (cid: number) => call<Change>(`changes/${cid}`),
      decideChange: (cid: number, merge: boolean, path?: string) => call<DecideResult>(`changes/${cid}/${merge ? 'merge' : 'reject'}`, { method: 'POST', body: path ? { paths: [path] } : {} }),
      file: async (key: string): Promise<Blob> => {
        try {
          return await c<Blob>(NS + 'files/' + encodeURIComponent(key), { as: 'blob' });
        } catch (e) {
          throw wrap(e, netText);
        }
      },
    };
  }, [c, netText]);
}

export type ChatApi = ReturnType<typeof useChatApi>;
