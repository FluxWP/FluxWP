// The message box: autosizing text, attachments, "/" commands, model button, dictation, send / stop.
import { ArrowUp, Cpu, Mic, Paperclip, Square } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useAi } from '../common';
import type { ChatApi } from './api';
import { FileChip } from './Messages';
import { canRecord, pickMime, toWav } from './stt';
import type { Command } from './types';

export interface PendingFile {
  file: File;
  name: string;
  type: string;
  url: string;
}

interface SpeechRec {
  lang: string;
  interimResults: boolean;
  continuous: boolean;
  onresult: ((e: { results: ArrayLike<{ isFinal: boolean; 0: { transcript: string } }> }) => void) | null;
  onend: (() => void) | null;
  onerror: ((e: { error: string }) => void) | null;
  start(): void;
  stop(): void;
}
type RecCtor = new () => SpeechRec;
const Rec = (): RecCtor | undefined => {
  const w = window as unknown as { SpeechRecognition?: RecCtor; webkitSpeechRecognition?: RecCtor };
  return w.SpeechRecognition ?? w.webkitSpeechRecognition;
};

const norm = (x: string, lang: string) => x.toLocaleLowerCase(lang).replace(/[ıİ]/g, 'i').normalize('NFD').replace(/[̀-ͯ]/g, '');
const GROUPS: Record<string, string> = { actions: 'Commands', skills: 'Skills', apps: 'Apps' };
const ORDER = ['actions', 'skills', 'apps'];

export interface ComposerProps {
  api: ChatApi;
  value: string;
  onChange: (v: string) => void;
  onSend: () => void;
  onStop: () => void;
  running: boolean;
  files: PendingFile[];
  onFiles: (list: FileList | File[]) => void;
  onRemoveFile: (i: number) => void;
  commands: Command[];
  onCommand: (c: Command) => void;
  modelLabel: string;
  canPickModel: boolean;
  onPickModel: () => void;
  locale: string;
  /** The site can transcribe recordings (OpenAI, Gemini or OpenRouter key). */
  stt?: boolean;
  openFilePicker: { current: (() => void) | null };
}

export function Composer(p: ComposerProps) {
  const { t } = useAi();
  const ta = useRef<HTMLTextAreaElement>(null);
  const fileIn = useRef<HTMLInputElement>(null);
  const [sel, setSel] = useState(0);
  const [slashOff, setSlashOff] = useState(false);
  const [rec, setRec] = useState<SpeechRec | null>(null);
  const [micErr, setMicErr] = useState(false);
  const [micMsg, setMicMsg] = useState('');
  const [srvRec, setSrvRec] = useState<MediaRecorder | null>(null);
  const [busy, setBusy] = useState(false);
  const valueRef = useRef(p.value);
  valueRef.current = p.value;
  const srv = !!p.stt && canRecord();
  p.openFilePicker.current = () => fileIn.current?.click();

  useEffect(() => {
    const el = ta.current;
    if (!el) return;
    if (!p.value) {
      el.style.height = '';
      return;
    }
    el.style.height = '0px';
    const h = el.scrollHeight;
    el.style.height = h ? Math.min(200, Math.max(30, h)) + 'px' : '';
  }, [p.value]);

  const m = /^\/([^\s]*)$/.exec(p.value);
  const items = useMemo(() => {
    if (!m || slashOff || !p.commands.length) return [];
    const q = norm(m[1] ?? '', p.locale);
    const list = p.commands.filter((c) => !q || norm(`${c.id} ${c.slug ?? ''} ${c.title} ${c.desc ?? ''}`, p.locale).includes(q));
    if (q) list.sort((a, b) => Number(!norm(`${a.slug ?? a.id} ${a.title}`, p.locale).startsWith(q)) - Number(!norm(`${b.slug ?? b.id} ${b.title}`, p.locale).startsWith(q)));
    const rank = (g?: string) => {
      const i = ORDER.indexOf(g ?? 'apps');
      return i < 0 ? 9 : i;
    };
    return [...list].sort((a, b) => rank(a.group) - rank(b.group));
  }, [m?.[1], slashOff, p.commands, p.locale]); // eslint-disable-line react-hooks/exhaustive-deps
  const open = !!m && !slashOff && p.commands.length > 0;
  const idx = Math.min(sel, Math.max(0, items.length - 1));

  const pick = (c: Command) => {
    setSlashOff(false);
    if (c.kind === 'action') {
      p.onChange('');
      p.onCommand(c);
      return;
    }
    p.onChange(c.insert ?? '');
    window.setTimeout(() => {
      const el = ta.current;
      el?.focus();
      el?.setSelectionRange(el.value.length, el.value.length);
    }, 0);
  };

  const onKey = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (open && items.length) {
      if (e.key === 'ArrowDown') return e.preventDefault(), setSel((idx + 1) % items.length);
      if (e.key === 'ArrowUp') return e.preventDefault(), setSel((idx - 1 + items.length) % items.length);
      if ((e.key === 'Enter' || e.key === 'Tab') && !e.shiftKey) return e.preventDefault(), pick(items[idx]!);
    }
    if (open && e.key === 'Escape') return e.preventDefault(), e.stopPropagation(), setSlashOff(true);
    if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
      e.preventDefault();
      if (p.value.trim() || p.files.length) p.onSend();
    }
  };

  const R = Rec();
  const dictateServer = async () => {
    if (busy) return;
    if (srvRec) return srvRec.stop();
    setMicErr(false);
    setMicMsg('');
    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      setMicErr(true);
      return;
    }
    const mt = pickMime();
    const mr = mt ? new MediaRecorder(stream, { mimeType: mt }) : new MediaRecorder(stream);
    const chunks: Blob[] = [];
    const timer = window.setTimeout(() => mr.state === 'recording' && mr.stop(), 150000);
    mr.ondataavailable = (e) => e.data.size && chunks.push(e.data);
    mr.onstop = () => {
      window.clearTimeout(timer);
      stream.getTracks().forEach((k) => k.stop());
      setSrvRec(null);
      const blob = new Blob(chunks, { type: mr.mimeType || mt || 'audio/webm' });
      if (blob.size < 1200) return;
      setBusy(true);
      void toWav(blob)
        .then((w) => p.api.transcribe(w, p.locale || document.documentElement.lang || ''))
        .then((txt) => {
          const x = txt.trim();
          if (x) p.onChange((valueRef.current ? valueRef.current.replace(/\s*$/, ' ') : '') + x);
          ta.current?.focus();
        })
        .catch((e: unknown) => setMicMsg(e instanceof Error ? e.message : String(e)))
        .finally(() => setBusy(false));
    };
    mr.start();
    setSrvRec(mr);
    ta.current?.focus();
  };
  const dictate = () => {
    if (srv) return void dictateServer();
    if (rec) return rec.stop();
    if (!R) return;
    const r = new R();
    r.lang = p.locale || document.documentElement.lang || 'en-US';
    r.interimResults = true;
    r.continuous = true;
    const base = p.value ? p.value.replace(/\s*$/, ' ') : '';
    r.onresult = (e) => {
      let fin = '';
      let tmp = '';
      for (let i = 0; i < e.results.length; i++) {
        const x = e.results[i]!;
        if (x.isFinal) fin += x[0].transcript;
        else tmp += x[0].transcript;
      }
      p.onChange(base + fin + tmp);
    };
    r.onend = () => setRec(null);
    r.onerror = (e) => {
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') setMicErr(true);
    };
    try {
      r.start();
      setRec(r);
      setMicErr(false);
      ta.current?.focus();
    } catch {
      setRec(null);
    }
  };

  let lastGroup = '';
  return (
    <div className="flxai-composer">
      {p.files.length > 0 && (
        <div className="flxai-cfiles">
          {p.files.map((f, i) => (
            <FileChip key={i} api={p.api} f={f} onRemove={() => p.onRemoveFile(i)} />
          ))}
        </div>
      )}
      <div className="flxai-cbox">
        {open && (
          <div className="flxai-slash" role="listbox">
            {!items.length && <div className="flxai-sl-empty">{t('No command found.')}</div>}
            {items.map((c, k) => {
              const g = c.group || 'apps';
              const head = g !== lastGroup ? <div className="flxai-sl-g">{GROUPS[g] ? t(GROUPS[g]!) : c.groupLabel || g}</div> : null;
              lastGroup = g;
              return (
                <div key={c.id}>
                  {head}
                  <button type="button" className={'flxai-sl-i' + (k === idx ? ' is-sel' : '')} role="option" aria-selected={k === idx} onMouseEnter={() => setSel(k)} onClick={() => pick(c)}>
                    <span className="flxai-sl-t">
                      <b>
                        {c.title}
                        {c.kind === 'skill' && <code> /{c.slug}</code>}
                      </b>
                      {c.desc && <small>{c.desc}</small>}
                    </span>
                  </button>
                </div>
              );
            })}
          </div>
        )}
        <textarea
          ref={ta}
          rows={1}
          value={p.value}
          placeholder={t('Message the assistant…')}
          aria-label={t('Message')}
          onChange={(e) => {
            p.onChange(e.target.value);
            setSlashOff(false);
            setSel(0);
          }}
          onKeyDown={onKey}
          onPaste={(e) => {
            const fl = Array.from(e.clipboardData.files);
            if (fl.length) {
              e.preventDefault();
              p.onFiles(fl);
            }
          }}
        />
        <div className="flxai-cbar">
          <button type="button" className="flxai-ibtn" title={t('Attach a file')} aria-label={t('Attach a file')} onClick={() => fileIn.current?.click()}>
            <Paperclip size={16} />
          </button>
          <input
            ref={fileIn}
            type="file"
            multiple
            hidden
            accept="image/*,application/pdf,.txt,.md,.csv,.json,.doc,.docx,.xls,.xlsx"
            onChange={(e) => {
              if (e.target.files) p.onFiles(e.target.files);
              e.target.value = '';
            }}
          />
          {(R || srv) && (
            <button type="button" className={'flxai-ibtn' + (rec || srvRec ? ' is-on' : '') + (busy ? ' is-busy' : '')} disabled={busy} aria-pressed={!!(rec || srvRec)} title={t('Dictate')} aria-label={t('Dictate')} onClick={dictate}>
              <Mic size={16} />
            </button>
          )}
          <span className="flxai-grow" />
          <button type="button" className={'flxai-send' + (p.running ? ' is-stop' : '')} disabled={!p.running && !p.value.trim() && !p.files.length} aria-label={p.running ? t('Stop') : t('Send')} onClick={p.running ? p.onStop : p.onSend}>
            {p.running ? <Square size={14} fill="currentColor" /> : <ArrowUp size={16} />}
          </button>
        </div>
      </div>
      <div className="flxai-chint">
        {p.modelLabel &&
          (p.canPickModel ? (
            <button type="button" className="flxai-model" title={t('Choose a model')} onClick={p.onPickModel}>
              <Cpu size={13} /> <span>{p.modelLabel}</span>
            </button>
          ) : (
            <span className="flxai-model">
              <Cpu size={13} /> <span>{p.modelLabel}</span>
            </span>
          ))}
        <span className="flxai-k">
          {t('Enter sends, Shift+Enter adds a line')} · {t('/ commands')}
        </span>
        {micErr && <span className="flxai-k is-warn">{t('Microphone permission was not granted.')}</span>}
        {micMsg && <span className="flxai-k is-warn">{micMsg}</span>}
      </div>
    </div>
  );
}
