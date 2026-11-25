// Flux AI › Chat: chat list, conversation, approvals and the message box. Turns run on the chat REST protocol
// (send → step stream → approval → step …), the same one the floating chat and the FluxWP app use.
import { useQueryClient } from '@tanstack/react-query';
import { FolderOpen, PanelLeft, SquarePen } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Modal } from '@fluxwp/ui';
import { useHost } from '../../host';
import { useAi } from '../common';
import { useChatApi } from './api';
import { Composer, type PendingFile } from './Composer';
import { Body } from './Messages';
import { ModelPicker } from './ModelPicker';
import { Sidebar } from './Sidebar';
import { Workspace, type WsFocus, type WsTab } from './Workspace';
import './chat.css';
import { ChatError, type Boot, type ChatData, type Command, type Decisions, type LiveEvent, type Msg, type Pending, type StepResult } from './types';

const MAX_FILES = 5;

export function ChatScreen() {
  const { t, lang } = useAi();
  const host = useHost();
  const qc = useQueryClient();
  const api = useChatApi(t('Could not connect. Check your internet connection and try again.'));
  const [boot, setBoot] = useState<Boot | null>(null);
  const [chat, setChatState] = useState<ChatData | null>(null);
  const [msgs, setMsgsState] = useState<Msg[]>([]);
  const [pending, setPending] = useState<Pending[]>([]);
  const [decisions, setDecisions] = useState<Decisions>({});
  const [running, setRunning] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [canRetry, setCanRetry] = useState(false);
  const [optimistic, setOptimistic] = useState<string | null>(null);
  const [optFiles, setOptFiles] = useState<PendingFile[]>([]);
  const [draft, setDraft] = useState('');
  const [files, setFiles] = useState<PendingFile[]>([]);
  const [editId, setEditId] = useState('');
  const [editText, setEditText] = useState('');
  const [newModel, setNewModel] = useState('');
  const [models, setModels] = useState(false);
  const [sideOpen, setSideOpen] = useState(true);
  const [wsOpen, setWsOpenState] = useState<boolean>(() => {
    try {
      const v = window.localStorage.getItem('flxai-ws');
      return v === null ? window.innerWidth >= 1280 : v === '1';
    } catch {
      return false;
    }
  });
  const [wsTab, setWsTab] = useState<WsTab>('files');
  const [wsFocus, setWsFocus] = useState<WsFocus>({ n: 0 });
  const [wsReload, setWsReload] = useState(0);
  const setWsOpen = (v: boolean) => {
    setWsOpenState(v);
    try {
      window.localStorage.setItem('flxai-ws', v ? '1' : '0');
    } catch {
      /* private mode */
    }
  };
  const [asking, setAsking] = useState<string | null>(null);
  const [modelLabels, setModelLabels] = useState<Record<string, string>>({});

  const chatRef = useRef<ChatData | null>(null);
  const msgsRef = useRef<Msg[]>([]);
  const liveRef = useRef<Msg | null>(null);
  const toBottom = useRef(true);
  const bodyEl = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement | null>(null);
  const filePicker = useRef<(() => void) | null>(null);
  const stepping = useRef(false);
  const alive = useRef(true);
  const bump = useRef(0);
  const optRef = useRef<string | null>(null);
  const decisionsRef = useRef<Decisions>({});
  decisionsRef.current = decisions;

  const setMsgs = (m: Msg[]) => {
    msgsRef.current = m;
    setMsgsState(m);
  };
  const flush = () => {
    if (bump.current) return;
    bump.current = window.setTimeout(() => {
      bump.current = 0;
      if (alive.current) setMsgsState([...msgsRef.current]);
    }, 60);
  };

  useEffect(() => {
    alive.current = true;
    return () => {
      alive.current = false;
      window.clearTimeout(bump.current);
    };
  }, []);

  // Follow the conversation while the reader is near the bottom.
  useLayoutEffect(() => {
    const el = bodyEl.current;
    if (!el) return;
    if (toBottom.current || el.scrollHeight - el.scrollTop - el.clientHeight < 160) {
      el.scrollTop = el.scrollHeight;
      toBottom.current = false;
    }
  });

  const dropLive = () => {
    const l = liveRef.current;
    if (l) {
      const i = msgsRef.current.indexOf(l);
      if (i >= 0) setMsgs(msgsRef.current.filter((x) => x !== l));
      liveRef.current = null;
    }
  };

  const setChat = (c: ChatData, replace: boolean) => {
    const first = !chatRef.current || chatRef.current.id !== c.id;
    setMsgs(replace || first || !c.since ? c.messages || [] : msgsRef.current.slice(0, c.since).concat(c.messages || []));
    chatRef.current = c;
    setChatState(c);
    setPending(c.pending || []);
    optRef.current = null;
    setOptimistic(null);
    setOptFiles([]);
    if (first) toBottom.current = true;
    qc.invalidateQueries({ queryKey: [host.scope] }).catch(() => {});
    try {
      const u = new URL(window.location.href);
      if (u.searchParams.get('flxai_chat') !== String(c.id)) {
        u.searchParams.set('flxai_chat', String(c.id));
        window.history.replaceState(null, '', u.toString());
      }
    } catch {
      /* the address bar is optional */
    }
  };

  const fail = (e: unknown) => {
    setRunning(false);
    stepping.current = false;
    dropLive();
    const ce = e instanceof ChatError ? e : new ChatError(e instanceof Error ? e.message : String(e));
    if (optRef.current) setDraft((d) => d || optRef.current || '');
    optRef.current = null;
    setOptimistic(null);
    if (ce.code === 'flxai_not_ready') {
      setBoot((b) => (b ? { ...b, ready: false } : b));
      return;
    }
    setErr(ce.message);
    setCanRetry(!!chatRef.current && ce.net);
  };

  const handle = (r: StepResult) => {
    if (r.chat) setChat(r.chat, false);
    if (r.state === 'continue') {
      setRunning(true);
      void step();
      return;
    }
    if (r.state === 'busy') {
      setRunning(true);
      window.setTimeout(() => void step(), 2500);
      return;
    }
    setRunning(false);
    stepping.current = false;
    if (r.state === 'approval') setDecisions({});
  };

  const onLive = (ev: LiveEvent) => {
    if (!alive.current) return;
    if (ev.t === 'chat') {
      dropLive();
      if (ev.chat) setChat(ev.chat, false);
      return;
    }
    if (ev.t === 'model') {
      dropLive();
      const m: Msg = { role: 'assistant', content: '', _live: 1, tool_calls: [] };
      liveRef.current = m;
      setMsgs([...msgsRef.current, m]);
      flush();
      return;
    }
    const m = liveRef.current;
    if (!m) return;
    if (ev.t === 'text') m.content = (m.content || '') + (ev.d || '');
    else if (ev.t === 'think') m._thinking = (m._thinking || '') + (ev.d || '');
    else if (ev.t === 'tool') {
      const calls = m.tool_calls ?? (m.tool_calls = []);
      const c = calls.find((x) => ev.id && x.id === ev.id);
      if (c) c.function.name = ev.name || c.function.name;
      else calls.push({ id: ev.id || 'live' + calls.length, type: 'function', function: { name: ev.name || '', arguments: '{}' } });
    }
    flush();
  };

  const step = async () => {
    const c = chatRef.current;
    if (!c || stepping.current) return;
    stepping.current = true;
    try {
      const r = await api.step(c.id, msgsRef.current.length, onLive);
      dropLive();
      stepping.current = false;
      if (!alive.current) return;
      handle(r ?? { state: 'busy' });
    } catch (e) {
      stepping.current = false;
      if (alive.current) fail(e);
    }
  };

  const loadChat = async (id: number, quiet = false) => {
    setErr(null);
    try {
      const c = await api.get(id);
      if (!alive.current) return;
      setRunning(false);
      stepping.current = false;
      setChat(c, true);
      if (c.status === 'running') {
        setRunning(true);
        void step();
      }
    } catch (e) {
      if (e instanceof ChatError && e.status === 404) {
        chatRef.current = null;
        setChatState(null);
        setMsgs([]);
        setPending([]);
      } else if (!quiet) setErr(e instanceof Error ? e.message : String(e));
    }
  };

  // First load: boot data and the chat named in the address (?flxai_chat=ID).
  useEffect(() => {
    api.boot().then((b) => alive.current && setBoot(b), () => {});
    api.models().then((l) => alive.current && setModelLabels(Object.fromEntries(l.map((m) => [m.id, m.name]))), () => {});
    const id = Number(new URL(window.location.href).searchParams.get('flxai_chat'));
    if (id > 0) void loadChat(id, true);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const newChat = () => {
    if (running) return;
    chatRef.current = null;
    setChatState(null);
    setMsgs([]);
    setPending([]);
    setErr(null);
    setOptimistic(null);
    setDecisions({});
    try {
      const u = new URL(window.location.href);
      u.searchParams.delete('flxai_chat');
      window.history.replaceState(null, '', u.toString());
    } catch {
      /* ignore */
    }
  };

  const addFiles = (list: FileList | File[]) => {
    const add: PendingFile[] = [];
    let msg: string | null = null;
    for (const f of Array.from(list)) {
      if (files.length + add.length >= MAX_FILES) {
        msg = t('At most %s files can be attached.', MAX_FILES);
        break;
      }
      if (boot?.maxBytes && f.size > boot.maxBytes) {
        msg = t('%s is too large.', f.name);
        continue;
      }
      add.push({ file: f, name: f.name, type: f.type || '', url: /^image\//.test(f.type) ? URL.createObjectURL(f) : '' });
    }
    if (msg) setErr(msg);
    if (add.length) setFiles((x) => [...x, ...add]);
  };
  const removeFile = (i: number) =>
    setFiles((x) => {
      const f = x[i];
      if (f?.url) URL.revokeObjectURL(f.url);
      return x.filter((_, k) => k !== i);
    });

  const send = async (text: string) => {
    const sending = files;
    setFiles([]);
    setRunning(true);
    setErr(null);
    optRef.current = text;
    setOptimistic(text);
    setOptFiles(sending);
    toBottom.current = true;
    setDraft('');
    const id = chatRef.current?.id ?? 0;
    try {
      const up: { key: string; name: string }[] = [];
      for (const f of sending) up.push(await api.upload(f.file));
      const body: Record<string, unknown> = { text, page: { title: document.title.slice(0, 160), url: window.location.href.slice(0, 400), admin: true }, since: msgsRef.current.length };
      if (up.length) {
        body.files = up.map((u) => u.key);
        body.names = up.map((u) => u.name);
      }
      if (!id && newModel) body.model = newModel;
      const r = await api.send(id, body);
      if (alive.current) handle(r);
    } catch (e) {
      setFiles(sending);
      if (alive.current) fail(e);
    }
  };

  const submit = () => {
    const text = draft.trim();
    if ((!text && !files.length) || running) return;
    void send(text);
  };

  const stop = async () => {
    const c = chatRef.current;
    if (!c) return setRunning(false);
    setRunning(false);
    try {
      handle(await api.stop(c.id, msgsRef.current.length));
    } catch (e) {
      fail(e);
    }
  };

  const decide = async () => {
    const c = chatRef.current;
    if (!c) return;
    setRunning(true);
    setPending([]);
    try {
      handle(await api.decide(c.id, decisionsRef.current, msgsRef.current.length));
    } catch (e) {
      fail(e);
    }
  };

  const rewind = async (mid: string, mode: 'edit' | 'keep') => {
    const c = chatRef.current;
    if (!c || running) throw new Error(t('The assistant is working; stop it first.'));
    const r = await api.rewind(c.id, mid, mode);
    setPending([]);
    setDecisions({});
    setErr(null);
    setEditId('');
    setChat(r, true);
  };

  const editSend = () => {
    const text = editText.trim();
    if (!text || !editId) return;
    rewind(editId, 'edit').then(() => send(text), (e: unknown) => setErr(e instanceof Error ? e.message : String(e)));
  };

  const pickModel = async (id: string) => {
    setModels(false);
    const c = chatRef.current;
    if (!c) return setNewModel(id);
    try {
      setChat(await api.update(c.id, { model: id }), false);
    } catch (e) {
      setErr(e instanceof Error ? e.message : String(e));
    }
  };

  const command = useCallback(
    (c: Command) => {
      const a = c.action;
      if (a === 'new') newChat();
      else if (a === 'side') setSideOpen((v) => !v);
      else if (a === 'search') {
        setSideOpen(true);
        window.setTimeout(() => searchRef.current?.focus(), 0);
      } else if (a === 'attach') filePicker.current?.();
      else if (a === 'model' && boot?.canManage) setModels(true);
      else if (a === 'ws') setWsOpen(!wsOpen);
      else window.dispatchEvent(new CustomEvent('flxai:command', { detail: c }));
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [boot?.canManage, running, wsOpen],
  );

  useEffect(() => {
    if (!running) setWsReload((n) => n + 1);
  }, [running, chat?.id]);

  const curModel = chat?.model || (!chat ? newModel : '');
  const modelLabel = curModel ? modelLabels[curModel] || curModel : boot?.modelLabel || '';
  const settingsUrl = `${host.adminUrl ?? ''}admin.php?page=flx-ai-settings`;

  return (
    <div className={'flxai-chatpage' + (sideOpen ? ' has-side' : '') + (wsOpen ? ' has-ws' : '')}>
      {sideOpen && (
        <Sidebar
          current={chat?.id ?? 0}
          locked={running}
          searchRef={searchRef}
          onOpen={(id) => void loadChat(id)}
          onNew={newChat}
          onGone={(id) => {
            if (chatRef.current?.id === id) newChat();
          }}
          onError={setErr}
        />
      )}
      <section className="flxai-chatmain">
        <header className="flxai-chath">
          <button type="button" className="flxai-ibtn" aria-pressed={sideOpen} title={t('Chats')} aria-label={t('Chats')} onClick={() => setSideOpen(!sideOpen)}>
            <PanelLeft size={16} />
          </button>
          <h2>{chat?.title || t('New chat')}</h2>
          <button type="button" className="flxai-ibtn" disabled={running} title={t('New chat')} aria-label={t('New chat')} onClick={newChat}>
            <SquarePen size={16} />
          </button>
          <button type="button" className="flxai-ibtn" aria-pressed={wsOpen} title={t('Workspace')} aria-label={t('Workspace')} onClick={() => setWsOpen(!wsOpen)}>
            <FolderOpen size={16} />
          </button>
        </header>
        <div ref={bodyEl} className="flxai-chatbody">
          <Body
            api={api}
            boot={boot}
            msgs={msgs}
            running={running}
            optimistic={optimistic}
            optFiles={optFiles}
            err={err}
            canRetry={canRetry}
            onRetry={() => {
              setErr(null);
              setRunning(true);
              void step();
            }}
            pending={pending}
            decisions={decisions}
            onDecision={(id, d) => setDecisions((x) => ({ ...x, [id]: d }))}
            onAll={(d) => setDecisions(Object.fromEntries(pending.map((p) => [p.id, d])))}
            onContinue={() => void decide()}
            editId={editId}
            editText={editText}
            setEditText={setEditText}
            onEdit={(id) => {
              setEditId(id);
              setEditText(msgs.find((m) => m._id === id)?.content ?? '');
            }}
            onEditCancel={() => setEditId('')}
            onEditSend={editSend}
            onRewind={(id) => setAsking(id)}
            onSuggest={(s) => void send(s)}
            onChange={(id) => {
              setWsOpen(true);
              setWsFocus((f) => ({ change: id, n: f.n + 1 }));
            }}
            onReport={(path) => {
              setWsOpen(true);
              setWsFocus((f) => ({ report: path, n: f.n + 1 }));
            }}
          />
        </div>
        {boot && !boot.ready && (
          <div className="flxai-notice is-warn">
            {t('Flux AI is not set up yet: an API key and a model are needed.')}{' '}
            {boot.canManage && <a href={settingsUrl}>{t('Settings')}</a>}
          </div>
        )}
        <Composer
          api={api}
          value={draft}
          onChange={setDraft}
          onSend={submit}
          onStop={() => void stop()}
          running={running}
          files={files}
          onFiles={addFiles}
          onRemoveFile={removeFile}
          commands={boot?.commands ?? []}
          onCommand={command}
          modelLabel={modelLabel}
          canPickModel={!!boot?.canManage}
          onPickModel={() => setModels(true)}
          locale={lang}
          stt={!!boot?.stt}
          openFilePicker={filePicker}
        />
      </section>
      {wsOpen && (
        <Workspace api={api} boot={boot} chatId={chat?.id ?? 0} tab={wsTab} setTab={setWsTab} focus={wsFocus} reloadKey={wsReload} rest={host.mode === 'wp'} onClose={() => setWsOpen(false)} onDecided={() => chat && void loadChat(chat.id, true)} />
      )}
      {models && <ModelPicker api={api} current={curModel} defaultLabel={boot?.modelLabel ?? ''} onPick={(id) => void pickModel(id)} onClose={() => setModels(false)} />}
      {asking && (
        <Modal title={t('Go back to this point')} onClose={() => setAsking(null)}>
          <p>{t('The conversation after this message will be deleted. Continue?')}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxai-btn is-danger"
              onClick={() => {
                const id = asking;
                setAsking(null);
                rewind(id, 'keep').catch((e: unknown) => setErr(e instanceof Error ? e.message : String(e)));
              }}
            >
              {t('Go back')}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}
