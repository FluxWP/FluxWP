// Sohbet alt alanı (klasik .flxc-foot): "/" hazır cevap ve emoji açılırı, hazır cevap baloncukları, alıntı çubuğu,
// ek çubuğu, yazma kutusu (dahili not etiketi, otomatik büyür, Enter gönderir), ses kaydı, sohbet başına taslak.
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FlxcMessage, FlxcReply } from '@fluxwp/core';
import type { WaEngine } from './engine';
import { I } from './icons';
import { EmojiPicker, Recorder, canRecord, emoRecent } from './extras';
import { Ib, fmtSize, store, useL, useUi } from './ui';

const mobile = () => typeof matchMedia === 'function' && matchMedia('(max-width: 899px)').matches;
const touch = () => typeof window !== 'undefined' && 'ontouchstart' in window;

export interface ComposerProps {
  engine: WaEngine;
  chatId: number;
  chatTitle: string;
  quote: FlxcMessage | null;
  clearQuote: () => void;
  replies: FlxcReply[];
  ai: boolean;
  chips: boolean;
  repliesUrl: string;
  onSent: () => void;
  /** Dışarıdan (kişi paneli) metne eklenecek; n her eklemede artar. */
  inject?: { text: string; n: number } | null;
}

interface Att {
  file: File;
  kind: 'image' | 'video' | 'document';
  url: string;
}
const attKind = (f: File): Att['kind'] => (f.type.startsWith('image/') && f.type !== 'image/svg+xml' ? 'image' : f.type.startsWith('video/') ? 'video' : 'document');

export function Composer({ engine, chatId, chatTitle, quote, clearQuote, replies, ai, chips, repliesUrl, onSent, inject }: ComposerProps) {
  const { L, lang } = useL();
  const ui = useUi();
  const draftKey = 'draft_' + chatId;
  const [text, setText] = useState(() => store(draftKey) || '');
  const [note, setNote] = useState(false);
  const [att, setAtt] = useState<Att[]>([]);
  const [pop, setPop] = useState<'' | 'emoji' | 'slash'>('');
  const [sel, setSel] = useState(0);
  const [aiBusy, setAiBusy] = useState(false);
  const [recording, setRecording] = useState(false);
  const [, setMq] = useState(0);
  const ta = useRef<HTMLTextAreaElement>(null);
  const fileIn = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const mq = matchMedia('(max-width: 899px)');
    const f = () => setMq((x) => x + 1);
    mq.addEventListener?.('change', f);
    return () => mq.removeEventListener?.('change', f);
  }, []);
  useEffect(() => {
    store(draftKey, text);
  }, [draftKey, text]);
  useEffect(() => () => att.forEach((a) => URL.revokeObjectURL(a.url)), []); // eslint-disable-line react-hooks/exhaustive-deps

  const insertAtCursor = (s: string) => {
    const el = ta.current;
    const a = el?.selectionStart ?? text.length;
    const b = el?.selectionEnd ?? text.length;
    setText((cur) => cur.slice(0, a) + s + cur.slice(b));
    requestAnimationFrame(() => {
      el?.focus();
      el?.setSelectionRange(a + s.length, a + s.length);
    });
  };
  useEffect(() => {
    if (inject?.text) insertAtCursor((text && !/\s$/.test(text) ? ' ' : '') + inject.text);
  }, [inject?.n]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!mobile()) ta.current?.focus();
  }, [chatId, quote]);
  useEffect(() => {
    const el = ta.current;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  }, [text]);

  // "/" ile hazır cevap
  const slash = /^\/([^\s]*)$/.exec(text)?.[1];
  useEffect(() => {
    if (slash !== undefined && !note) setPop('slash');
    else if (pop === 'slash' && slash === undefined) setPop('');
  }, [slash, note]); // eslint-disable-line react-hooks/exhaustive-deps
  const slashItems = useMemo(() => {
    const q = (slash ?? '').toLocaleLowerCase(lang);
    return replies
      .filter((r) => !q || (r.shortcut + ' ' + r.title + ' ' + r.body).toLocaleLowerCase(lang).includes(q))
      .sort((a, b) => (a.shortcut.indexOf(q) === 0 ? 0 : 1) - (b.shortcut.indexOf(q) === 0 ? 0 : 1) || b.uses - a.uses);
  }, [slash, replies, lang]);
  useEffect(() => setSel(0), [slash]);

  const useReply = async (r: FlxcReply) => {
    setPop('');
    try {
      const res = await engine.api.useReply(r.id, chatId);
      const body = res.text || r.body;
      setText((v) => {
        const cur = v.replace(/^\/\S*$/, '');
        return cur ? cur + (/\s$/.test(cur) ? '' : ' ') + body : body;
      });
      requestAnimationFrame(() => {
        const el = ta.current;
        if (el) {
          el.focus();
          el.selectionStart = el.selectionEnd = el.value.length;
        }
      });
    } catch (e) {
      ui.toast((e as Error).message, 'err');
    }
  };

  const addFiles = (files: File[]) => {
    if (!files.length) return;
    setAtt((cur) => [...cur, ...files.map((f) => ({ file: f, kind: attKind(f), url: URL.createObjectURL(f) }))]);
    ta.current?.focus();
  };
  const removeAtt = (a: Att) => {
    URL.revokeObjectURL(a.url);
    setAtt((cur) => cur.filter((x) => x !== a));
  };

  const sendVoice = async (blob: Blob, name: string) => {
    setRecording(false);
    onSent();
    try {
      await engine.send(chatId, { type: 'audio', file: blob, name, quoted: quote?.id });
      clearQuote();
    } catch (e) {
      ui.toast((e as Error).message, 'err');
    }
  };

  const submit = async () => {
    const body = text.trim();
    if (att.length) {
      const list = att;
      setAtt([]);
      setText('');
      clearQuote();
      onSent();
      for (let i = 0; i < list.length; i++) {
        const a = list[i]!;
        try {
          await engine.send(chatId, { type: a.kind, file: a.file, name: a.file.name, body: i === list.length - 1 ? body : '', quoted: i === 0 ? quote?.id : undefined });
        } catch (e) {
          ui.toast((e as Error).message, 'err');
        }
        URL.revokeObjectURL(a.url);
      }
      return;
    }
    if (!body) return;
    setText('');
    clearQuote();
    onSent();
    try {
      await engine.send(chatId, { type: note ? 'note' : 'text', body, quoted: quote?.id });
      if (note) setNote(false);
    } catch (e) {
      ui.toast((e as Error).message, 'err');
    }
  };

  const suggest = async () => {
    setAiBusy(true);
    try {
      const r = await engine.api.ai(chatId, { fresh: true });
      if (r.text) setText(r.text);
    } catch (e) {
      ui.toast((e as Error).message, 'err');
    } finally {
      setAiBusy(false);
      ta.current?.focus();
    }
  };

  const setNoteMode = (on: boolean) => {
    setNote(on);
    if (on) ta.current?.focus();
  };

  // Escape: açılır → alıntı → not
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      if (pop) return setPop('');
      if (quote) return clearQuote();
      if (note) setNote(false);
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [pop, quote, note, clearQuote]);

  const has = text.trim().length > 0 || att.length > 0;
  const send = has || note || mobile();
  const showChips = chips && replies.length > 0 && !note;
  const chipList = useMemo(() => replies.slice().sort((a, b) => b.uses - a.uses).slice(0, 14), [replies]);
  const ph = note ? L('Yalnız temsilcilerin göreceği notu yazın') : mobile() ? L('Mesaj') : L('Mesaj yazın — hazır cevap için /');

  return (
    <div
      className="flxc-foot"
      onDragOver={(e) => e.dataTransfer.types.includes('Files') && e.preventDefault()}
      onDrop={(e) => {
        if (!e.dataTransfer.files.length) return;
        e.preventDefault();
        addFiles(Array.from(e.dataTransfer.files));
      }}
    >
      {pop && (
        <div className="flxc-pop" data-kind={pop}>
          {pop === 'emoji' ? (
            <EmojiPicker
              onPick={(e) => {
                emoRecent(e);
                insertAtCursor(e);
              }}
            />
          ) : (
            <>
              <div className="flxc-qr-head">
                <I n="bolt" />
                <strong>{L('Hazır cevaplar')}</strong>
                {repliesUrl && (
                  <a className="flxc-link" href={repliesUrl}>
                    {L('Düzenle')}
                  </a>
                )}
              </div>
              <div className="flxc-qr-list" role="listbox">
                {!slashItems.length && <div className="flxc-qr-empty">{replies.length ? L('Eşleşen hazır cevap yok.') : L('Henüz hazır cevap yok. "Düzenle" ile ekleyin.')}</div>}
                {slashItems.map((r, i) => (
                  <button key={r.id} type="button" role="option" aria-selected={i === sel} className={'flxc-qr-i' + (i === sel ? ' is-on' : '')} onMouseEnter={() => setSel(i)} onClick={() => void useReply(r)}>
                    <span className="flxc-qr-top">
                      {r.shortcut && <code>{'/' + r.shortcut}</code>}
                      <strong>{r.title}</strong>
                      {r.category && <em>{r.category}</em>}
                    </span>
                    <span className="flxc-qr-body">{r.body}</span>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      )}
      {showChips && (
        <div className="flxc-qchips" role="toolbar" aria-label={L('Hazır cevaplar')}>
          {chipList.map((r) => (
            <button key={r.id} type="button" className="flxc-qchip" title={r.body} onClick={() => void useReply(r)}>
              {r.title || '/' + r.shortcut}
            </button>
          ))}
          {repliesUrl && (
            <a className="flxc-qchip flxc-qchip-edit" href={repliesUrl} title={L('Hazır cevapları düzenle')} aria-label={L('Hazır cevapları düzenle')}>
              <I n="edit" />
            </a>
          )}
        </div>
      )}
      {quote && (
        <div className="flxc-replybar">
          <div className={'flxc-quote flxc-quote-' + quote.dir}>
            <strong>{quote.dir === 'in' ? chatTitle : L('Siz')}</strong>
            <span>{quote.body || ({ image: L('Fotoğraf'), video: L('Video'), audio: L('Sesli mesaj'), document: quote.type === 'document' ? L('Belge') : '' } as Record<string, string>)[quote.type] || ''}</span>
          </div>
          <Ib n="close" title={L('Yanıtı iptal et')} onClick={clearQuote} />
        </div>
      )}
      {att.length > 0 && (
        <div className="flxc-attbar" role="list" aria-label={L('Ekler')}>
          {att.map((a, i) => (
            <div key={i} className={'flxc-att is-' + a.kind + ' is-done'} title={a.file.name + ' · ' + fmtSize(a.file.size, lang)}>
              {a.kind === 'image' ? (
                <img src={a.url} alt="" />
              ) : a.kind === 'video' ? (
                <video src={a.url + '#t=0.1'} muted preload="metadata" playsInline />
              ) : (
                <span className="flxc-att-doc">
                  <I n="doc" />
                  <small>{(a.file.name.split('.').pop() || '').slice(0, 5).toUpperCase()}</small>
                </span>
              )}
              {a.kind === 'video' && <span className="flxc-att-play"><I n="play" /></span>}
              {a.kind === 'document' && <span className="flxc-att-name">{a.file.name}</span>}
              <button type="button" className="flxc-att-x" aria-label={L('Kaldır')} title={L('Kaldır')} onMouseDown={(e) => e.preventDefault()} onClick={() => removeAtt(a)}>
                <I n="close" />
              </button>
            </div>
          ))}
        </div>
      )}
      <div className={'flxc-comp' + (note ? ' is-note' : '')}>
        <Ib
          n="emoji"
          title={L('Emoji')}
          cls="flxc-emo-b"
          onClick={(e) => {
            e.stopPropagation();
            setPop((p) => (p === 'emoji' ? '' : 'emoji'));
          }}
        />
        <Ib
          n="plus"
          title={L('Ekle')}
          cls="flxc-att-b"
          onClick={(e) =>
            ui.menu(
              e.currentTarget,
              [
                {
                  icon: 'image',
                  label: L('Fotoğraf ve video'),
                  fn: () => {
                    if (fileIn.current) fileIn.current.accept = 'image/*,video/*';
                    fileIn.current?.click();
                  },
                },
                {
                  icon: 'doc',
                  label: L('Belge'),
                  fn: () => {
                    if (fileIn.current) fileIn.current.accept = '';
                    fileIn.current?.click();
                  },
                },
                { icon: 'bolt', label: L('Hazır cevap'), fn: () => (setText('/'), ta.current?.focus()) },
                { icon: 'note', label: L('Dahili not'), fn: () => setNoteMode(true) },
                ai ? { icon: 'ai', label: L('Yapay zekâ önerisi'), fn: () => void suggest() } : null,
              ],
              { alignLeft: true },
            )
          }
        />
        {ai && <Ib n="ai" title={L('Yapay zekâ önerisi')} cls={'flxc-ai-b' + (aiBusy ? ' is-busy' : '')} onClick={() => void suggest()} />}
        <Ib n="mic" title={L('Sesli mesaj kaydet')} cls="flxc-mic-b" onClick={() => canRecord() && setRecording(true)} />
        <div className="flxc-input-wrap">
          {note && (
            <span className="flxc-note-tag">
              <I n="note" />
              {L('Dahili not')}
              <button type="button" aria-label={L('Notu iptal et')} onClick={() => setNote(false)}>
                <I n="close" />
              </button>
            </span>
          )}
          <textarea
            ref={ta}
            className="flxc-input"
            rows={1}
            value={text}
            placeholder={ph}
            aria-label={L('Mesaj')}
            enterKeyHint="send"
            onChange={(e) => {
              setText(e.target.value);
              if (e.target.value && !note) engine.typing(chatId);
            }}
            onPaste={(e) => {
              const files = Array.from(e.clipboardData.files);
              if (files.length) {
                e.preventDefault();
                addFiles(files);
              }
            }}
            onKeyDown={(e) => {
              if (pop === 'slash') {
                if (e.key === 'ArrowDown') return e.preventDefault(), setSel((s) => Math.min(slashItems.length - 1, s + 1));
                if (e.key === 'ArrowUp') return e.preventDefault(), setSel((s) => Math.max(0, s - 1));
                if ((e.key === 'Enter' || e.key === 'Tab') && slashItems[sel]) return e.preventDefault(), void useReply(slashItems[sel]!);
              }
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing && !(mobile() && touch())) {
                e.preventDefault();
                void submit();
              }
            }}
          />
        </div>
        <button
          type="button"
          className={'flxc-send' + (!send ? ' is-mic' : '') + (send && !has ? ' is-empty' : '')}
          aria-label={send ? L('Gönder') : L('Sesli mesaj kaydet')}
          onMouseDown={(e) => att.length && e.preventDefault()}
          onClick={() => {
            if (has) void submit();
            else if (mobile()) ta.current?.focus();
            else if (canRecord()) setRecording(true);
          }}
        >
          <I n={send ? 'send' : 'mic'} />
        </button>
        <input
          ref={fileIn}
          type="file"
          hidden
          multiple
          onChange={(e) => {
            addFiles(Array.from(e.target.files ?? []));
            e.target.value = '';
          }}
        />
        {recording && <Recorder onDone={(b, n) => void sendVoice(b, n)} onCancel={() => setRecording(false)} onTick={() => engine.typing(chatId, 'recording')} onError={(m) => ui.toast(m, 'err')} />}
      </div>
    </div>
  );
}
