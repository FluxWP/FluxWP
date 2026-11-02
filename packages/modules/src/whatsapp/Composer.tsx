// Yazma alanı: metin (otomatik büyür, Enter gönderir), dahili not modu, alıntı çubuğu, ek (seç/yapıştır/sürükle),
// "/" hazır cevap seçici, sohbet başına taslak, yazıyor sinyali.
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FileText, Mic, Paperclip, Send, Smile, Sparkles, StickyNote, X, Zap } from 'lucide-react';
import type { FlxcMessage, FlxcReply } from '@fluxwp/core';
import type { WaEngine } from './engine';
import { fileSize } from './format';
import { EmojiPicker, Recorder, canRecord } from './extras';

const drafts = new Map<string, string>();
const touch = typeof matchMedia === 'function' && matchMedia('(pointer: coarse)').matches;

export interface ComposerProps {
  engine: WaEngine;
  chatId: number;
  scope: string;
  quote: FlxcMessage | null;
  clearQuote: () => void;
  replies: FlxcReply[];
  ai: boolean;
  onSent: () => void;
  onError: (msg: string) => void;
  /** Dışarıdan (kişi paneli) metne eklenecek; n her eklemede artar. */
  inject?: { text: string; n: number } | null;
}

export function Composer({ engine, chatId, scope, quote, clearQuote, replies, ai, onSent, onError, inject }: ComposerProps) {
  const { t } = useTranslation();
  const key = scope + '|' + chatId;
  const [text, setText] = useState(() => drafts.get(key) ?? '');
  const [note, setNote] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [pick, setPick] = useState(0);
  const [aiBusy, setAiBusy] = useState(false);
  const [drag, setDrag] = useState(false);
  const [emoji, setEmoji] = useState(false);
  const [recording, setRecording] = useState(false);
  const ta = useRef<HTMLTextAreaElement>(null);
  const fileInput = useRef<HTMLInputElement>(null);
  const preview = useMemo(() => (file?.type.startsWith('image/') ? URL.createObjectURL(file) : ''), [file]);
  useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

  useEffect(() => {
    drafts.set(key, text);
  }, [key, text]);
  const insertAtCursor = (str: string) => {
    const el = ta.current;
    const a = el?.selectionStart ?? text.length;
    const b = el?.selectionEnd ?? text.length;
    setText((cur) => cur.slice(0, a) + str + cur.slice(b));
    requestAnimationFrame(() => {
      el?.focus();
      el?.setSelectionRange(a + str.length, a + str.length);
    });
  };
  useEffect(() => {
    if (inject?.text) insertAtCursor((text && !text.endsWith(' ') ? ' ' : '') + inject.text);
  }, [inject?.n]); // eslint-disable-line react-hooks/exhaustive-deps

  const sendVoice = async (blob: Blob, name: string) => {
    setRecording(false);
    onSent();
    try {
      await engine.send(chatId, { type: 'audio', file: blob, name, quoted: quote?.id });
      clearQuote();
    } catch (e) {
      onError((e as Error).message);
    }
  };
  useEffect(() => {
    ta.current?.focus();
  }, [chatId, quote]);
  useEffect(() => {
    const el = ta.current;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(160, el.scrollHeight) + 'px';
  }, [text]);

  const slash = /^\/(\S*)$/.exec(text)?.[1];
  const matches = useMemo(() => {
    if (slash === undefined) return [];
    const s = slash.toLocaleLowerCase('tr');
    return replies.filter((r) => !s || r.shortcut.includes(s) || r.title.toLocaleLowerCase('tr').includes(s) || r.body.toLocaleLowerCase('tr').includes(s)).slice(0, 8);
  }, [slash, replies]);

  const useReply = async (r: FlxcReply) => {
    setText(r.body);
    try {
      const filled = await engine.api.useReply(r.id, chatId);
      if (filled.text) setText(filled.text);
    } catch {
      /* metin dolduruldu, değişkenler kalır */
    }
    ta.current?.focus();
  };

  const submit = async () => {
    const body = text.trim();
    if (!body && !file) return;
    const args = file
      ? { type: (file.type.startsWith('image/') ? 'image' : file.type.startsWith('video/') ? 'video' : 'document') as 'image' | 'video' | 'document', file, name: file.name, body, quoted: quote?.id }
      : { type: (note ? 'note' : 'text') as 'note' | 'text', body, quoted: quote?.id };
    setText('');
    setFile(null);
    clearQuote();
    onSent();
    try {
      await engine.send(chatId, args);
    } catch (e) {
      onError((e as Error).message);
    }
  };

  const suggest = async () => {
    setAiBusy(true);
    try {
      const r = await engine.api.ai(chatId, { fresh: !!text.trim() });
      if (r.text) setText(r.text);
    } catch (e) {
      onError((e as Error).message);
    } finally {
      setAiBusy(false);
      ta.current?.focus();
    }
  };

  const takeFiles = (list: FileList | null | undefined) => {
    const f = list?.[0];
    if (f) setFile(f);
  };

  return (
    <div
      className={'wa-composer' + (note ? ' note' : '') + (drag ? ' drag' : '')}
      onDragOver={(e) => (e.preventDefault(), setDrag(true))}
      onDragLeave={() => setDrag(false)}
      onDrop={(e) => (e.preventDefault(), setDrag(false), takeFiles(e.dataTransfer.files))}
    >
      {matches.length > 0 && (
        <div className="wa-picker" role="listbox">
          {matches.map((r, i) => (
            <button key={r.id} type="button" className={i === pick ? 'on' : ''} onMouseEnter={() => setPick(i)} onClick={() => void useReply(r)}>
              <strong>/{r.shortcut || r.title}</strong>
              <span className="muted ellipsis">{r.body}</span>
            </button>
          ))}
        </div>
      )}
      {quote && (
        <div className="wa-quote-bar">
          <div className={'wa-quote ' + quote.dir}>
            <strong>{quote.dir === 'in' ? t('wa.customer') : quote.user || t('wa.you')}</strong>
            <span className="ellipsis">{quote.body || t(`wa.types.${quote.type}`)}</span>
          </div>
          <button type="button" className="icon-btn" onClick={clearQuote} aria-label={t('common.cancel')}><X size={16} /></button>
        </div>
      )}
      {file && (
        <div className="wa-file-bar">
          {preview ? <img src={preview} alt="" /> : <FileText size={22} />}
          <span className="grow ellipsis">{file.name} <span className="muted small">{fileSize(file.size)}</span></span>
          <button type="button" className="icon-btn" onClick={() => setFile(null)} aria-label={t('common.cancel')}><X size={16} /></button>
        </div>
      )}
      {recording && (
        <Recorder onDone={(b, n) => void sendVoice(b, n)} onCancel={() => setRecording(false)} onTick={() => engine.typing(chatId, 'recording')} onError={onError} />
      )}
      {note && <div className="wa-note-hint"><StickyNote size={13} /> {t('wa.noteHint')}</div>}
      {!recording && <div className="wa-compose-row">
        <button type="button" className="icon-btn" onClick={() => fileInput.current?.click()} title={t('wa.attach')} disabled={note}>
          <Paperclip size={18} />
        </button>
        <input ref={fileInput} type="file" hidden onChange={(e) => (takeFiles(e.target.files), (e.target.value = ''))} />
        <button type="button" className={'icon-btn' + (note ? ' active' : '')} onClick={() => setNote((x) => !x)} title={t('wa.noteMode')}>
          <StickyNote size={18} />
        </button>
        {replies.length > 0 && (
          <button type="button" className="icon-btn wa-opt" onClick={() => (setText('/'), ta.current?.focus())} title={t('wa.quickReplies')}>
            <Zap size={18} />
          </button>
        )}
        {ai && !note && (
          <button type="button" className="icon-btn wa-opt" onClick={() => void suggest()} disabled={aiBusy} title={t('wa.aiSuggest')}>
            <Sparkles size={18} className={aiBusy ? 'spin-slow' : ''} />
          </button>
        )}
        <span className="wa-emoji-wrap">
          <button type="button" className={'icon-btn' + (emoji ? ' active' : '')} onClick={() => setEmoji((x) => !x)} title={t('wa.emoji')}>
            <Smile size={18} />
          </button>
          {emoji && <EmojiPicker onPick={(e) => insertAtCursor(e)} onClose={() => setEmoji(false)} />}
        </span>
        <textarea
          ref={ta}
          rows={1}
          value={text}
          placeholder={note ? t('wa.notePlaceholder') : file ? t('wa.caption') : t('wa.write')}
          onChange={(e) => {
            setText(e.target.value);
            setPick(0);
            if (!note && e.target.value) engine.typing(chatId);
          }}
          onPaste={(e) => {
            if (e.clipboardData.files.length) {
              e.preventDefault();
              takeFiles(e.clipboardData.files);
            }
          }}
          onKeyDown={(e) => {
            if (matches.length) {
              if (e.key === 'ArrowDown') return e.preventDefault(), setPick((p) => (p + 1) % matches.length);
              if (e.key === 'ArrowUp') return e.preventDefault(), setPick((p) => (p - 1 + matches.length) % matches.length);
              if (e.key === 'Enter' || e.key === 'Tab') return e.preventDefault(), void useReply(matches[pick]!);
            }
            if (e.key === 'Escape') {
              if (quote) return clearQuote();
              if (note) return setNote(false);
            }
            if (e.key === 'Enter' && !e.shiftKey && !touch && !e.nativeEvent.isComposing) {
              e.preventDefault();
              void submit();
            }
          }}
        />
        {!text.trim() && !file && !note && canRecord() ? (
          <button type="button" className="btn-primary wa-send" onClick={() => setRecording(true)} aria-label={t('wa.voice.start')} title={t('wa.voice.start')}>
            <Mic size={18} />
          </button>
        ) : (
          <button type="button" className="btn-primary wa-send" onClick={() => void submit()} disabled={!text.trim() && !file} aria-label={t('common.send')}>
            <Send size={17} />
          </button>
        )}
      </div>}
    </div>
  );
}
