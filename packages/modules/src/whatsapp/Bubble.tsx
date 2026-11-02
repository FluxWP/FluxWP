// Tek mesaj balonu: tüm türler, alıntı, tepkiler, tikler, başarısız + tekrar dene, mesaj menüsü.
import { memo, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  AlertCircle, Bot, Check, CheckCheck, ChevronDown, Clock, Copy, Download, FileText, MapPin,
  Pencil, Reply, RotateCw, Smile, StickyNote, Trash2, UserRound, Video,
} from 'lucide-react';
import type { FlxcLinkPreview, FlxcMessage, FlxcStatus } from '@fluxwp/core';
import { useOverlay } from '@fluxwp/ui';
import { AudioPlayer, useMediaSrc } from './media';
import { WaText, clock, emojiOnly, fileSize } from './format';

export const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

export interface BubbleActions {
  quote: (m: FlxcMessage) => void;
  jump: (id: number) => void;
  react: (m: FlxcMessage, emoji: string) => void;
  retry: (m: FlxcMessage) => void;
  deleteNote: (m: FlxcMessage) => void;
  openChatWith?: (phone: string) => void;
  linkPreview: (url: string) => Promise<FlxcLinkPreview | null>;
}

function Ticks({ s }: { s: FlxcStatus }) {
  if (s === 'pending') return <Clock size={12} />;
  if (s === 'failed') return <AlertCircle size={12} className="text-danger" />;
  if (s === 'read' || s === 'played') return <CheckCheck size={13} className="read" />;
  if (s === 'delivered') return <CheckCheck size={13} />;
  if (s === 'sent') return <Check size={13} />;
  return null;
}

function Lightbox({ src, onClose }: { src: string; onClose: () => void }) {
  useEffect(() => useOverlay.getState().pushOverlay(), []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, [onClose]);
  return (
    <div className="wa-lightbox" onClick={onClose}>
      <img src={src} alt="" />
    </div>
  );
}

function ImageMedia({ m }: { m: FlxcMessage }) {
  const md = m.media!;
  const { src } = useMediaSrc(md.url);
  const [big, setBig] = useState(false);
  const ratio = md.w && md.h ? md.w / md.h : undefined;
  const sticker = m.type === 'sticker';
  return (
    <>
      <button type="button" className={'wa-img' + (sticker ? ' sticker' : '')} onClick={() => src && setBig(true)} style={ratio ? { aspectRatio: String(ratio) } : undefined}>
        {src ? <img src={src} alt={md.name} /> : md.thumb ? <img src={`data:image/jpeg;base64,${md.thumb}`} alt="" className="blur" /> : <span className="wa-img-ph" />}
      </button>
      {big && <Lightbox src={src} onClose={() => setBig(false)} />}
    </>
  );
}

function VideoMedia({ m }: { m: FlxcMessage }) {
  const [want, setWant] = useState(false);
  const { src } = useMediaSrc(m.media!.url, want);
  if (!want) {
    return (
      <button type="button" className="wa-video-ph" onClick={() => setWant(true)}>
        {m.media!.thumb && <img src={`data:image/jpeg;base64,${m.media!.thumb}`} alt="" />}
        <Video size={28} />
      </button>
    );
  }
  return src ? <video className="wa-video" src={src} controls autoPlay /> : <span className="wa-img-ph" />;
}

function DocMedia({ m }: { m: FlxcMessage }) {
  const md = m.media!;
  const [want, setWant] = useState(false);
  const { src } = useMediaSrc(md.url, want);
  useEffect(() => {
    if (want && src) {
      const a = document.createElement('a');
      a.href = src;
      a.download = md.name || 'file';
      a.target = '_blank';
      a.click();
      setWant(false);
    }
  }, [want, src, md.name]);
  const ext = (md.name.split('.').pop() || md.mime.split('/').pop() || '').toUpperCase().slice(0, 5);
  return (
    <button type="button" className="wa-doc" onClick={() => setWant(true)}>
      <FileText size={26} />
      <span className="grow ellipsis">
        <strong className="ellipsis">{md.name || ext}</strong>
        <span className="muted small">{[ext, fileSize(md.size)].filter(Boolean).join(' · ')}</span>
      </span>
      <Download size={16} />
    </button>
  );
}

const lpSeen = new Map<string, FlxcLinkPreview | null>();
function LinkCard({ m, load }: { m: FlxcMessage; load: BubbleActions['linkPreview'] }) {
  const url = typeof m.lp === 'object' && m.lp ? m.lp.url : '';
  const [lp, setLp] = useState<FlxcLinkPreview | null>(typeof m.lp === 'object' && m.lp && m.lp.title ? m.lp : lpSeen.get(url) ?? null);
  useEffect(() => {
    if (lp || !url || lpSeen.has(url)) return;
    let live = true;
    void load(url).then((r) => {
      lpSeen.set(url, r);
      if (live) setLp(r);
    });
    return () => {
      live = false;
    };
  }, [url, lp, load]);
  if (!lp || !lp.title) return null;
  return (
    <a className="wa-lp" href={lp.url} target="_blank" rel="noreferrer noopener">
      {lp.image && <img src={lp.image} alt="" />}
      <span>
        <strong>{lp.title}</strong>
        {lp.desc && <span className="muted small clamp2">{lp.desc}</span>}
        <span className="muted xsmall">{lp.domain || lp.site}</span>
      </span>
    </a>
  );
}

function Menu({ m, a, onClose }: { m: FlxcMessage; a: BubbleActions; onClose: () => void }) {
  const { t } = useTranslation();
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const k = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && onClose();
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    setTimeout(() => document.addEventListener('mousedown', k));
    window.addEventListener('keydown', esc);
    return () => (document.removeEventListener('mousedown', k), window.removeEventListener('keydown', esc));
  }, [onClose]);
  const canReact = !!m.wa && (m.dir === 'in' || m.dir === 'out');
  const item = (icon: React.ReactNode, label: string, f: () => void) => (
    <button type="button" onClick={() => (f(), onClose())}>{icon}{label}</button>
  );
  return (
    <div className="wa-menu" ref={ref}>
      {canReact && (
        <div className="wa-menu-react">
          {QUICK_REACTIONS.map((e) => (
            <button key={e} type="button" onClick={() => (a.react(m, e), onClose())}>{e}</button>
          ))}
        </div>
      )}
      {m.dir !== 'system' && m.id > 0 && item(<Reply size={15} />, t('wa.reply'), () => a.quote(m))}
      {m.body && item(<Copy size={15} />, t('wa.copy'), () => void navigator.clipboard?.writeText(m.body))}
      {m.status === 'failed' && item(<RotateCw size={15} />, t('wa.retry'), () => a.retry(m))}
      {m.dir === 'note' && m.type !== 'deleted' && item(<Trash2 size={15} />, t('wa.deleteNote'), () => a.deleteNote(m))}
    </div>
  );
}

function sourceLabel(m: FlxcMessage, t: (k: string) => string): string {
  if (m.dir !== 'out') return '';
  if (m.source === 'bot') return t('wa.src.bot');
  if (m.source === 'ai') return t('wa.src.ai');
  if (m.source === 'phone') return t('wa.src.phone');
  if (m.source === 'auto' || m.source === 'automation') return t('wa.src.auto');
  if (m.source === 'api') return t('wa.src.api');
  if (m.source === 'cert' || m.source === 'certificate') return t('wa.src.cert');
  return '';
}

export const Bubble = memo(function Bubble({ m, a, first, group, highlight }: { m: FlxcMessage; a: BubbleActions; first: boolean; group: boolean; highlight: boolean }) {
  const { t, i18n } = useTranslation();
  const [menu, setMenu] = useState(false);
  const lang = i18n.language;

  if (m.dir === 'system') {
    return <div className="wa-system"><span>{m.body}</span></div>;
  }

  const reacts = Object.values(m.react ?? {}).filter(Boolean);
  const big = m.type === 'text' && emojiOnly(m.body);
  const src = sourceLabel(m, t);
  const cls = ['wa-bubble', m.dir, first ? 'first' : '', big ? 'big-emoji' : '', highlight ? 'flash' : '', m.source === 'bot' ? 'by-bot' : '', m.type === 'sticker' ? 'is-sticker' : ''].filter(Boolean).join(' ');

  let content: React.ReactNode;
  switch (m.type) {
    case 'image':
    case 'sticker':
      content = m.media ? <ImageMedia m={m} /> : null;
      break;
    case 'video':
      content = m.media ? <VideoMedia m={m} /> : null;
      break;
    case 'audio':
      content = m.media ? <AudioPlayer url={m.media.url} secs={m.media.secs} ptt={m.media.ptt} /> : null;
      break;
    case 'document':
      content = m.media ? <DocMedia m={m} /> : null;
      break;
    case 'location':
      content = m.loc ? (
        <a className="wa-loc" href={`https://maps.google.com/?q=${m.loc.lat},${m.loc.lng}`} target="_blank" rel="noreferrer noopener">
          <MapPin size={18} /> {m.loc.name || `${m.loc.lat.toFixed(5)}, ${m.loc.lng.toFixed(5)}`}
        </a>
      ) : null;
      break;
    case 'contact':
      content = (
        <div className="wa-contact">
          <UserRound size={18} /> <span className="grow">{m.body || m.vcardPhone}</span>
          {m.vcardPhone && a.openChatWith && <button type="button" className="btn-ghost small" onClick={() => a.openChatWith!(m.vcardPhone!)}>{t('wa.message')}</button>}
        </div>
      );
      break;
    case 'deleted':
      content = <em className="muted">{t('wa.deletedMsg')}</em>;
      break;
    case 'unknown':
    case 'poll':
      content = <em className="muted">{m.type === 'poll' ? t('wa.poll') : t('wa.unsupported')}</em>;
      break;
    default:
      content = null;
  }
  const showBody = m.body && !['deleted', 'contact', 'location', 'unknown'].includes(m.type);

  return (
    <div className={'wa-row ' + m.dir} id={`wa-m-${m.id}`}>
      <div className={cls} onDoubleClick={() => m.id > 0 && a.quote(m)}>
        {m.dir === 'note' && <span className="wa-by"><StickyNote size={11} /> {t('wa.note')} · {m.user}</span>}
        {m.dir === 'in' && group && first && m.sender && <span className="wa-sender">{m.sender}</span>}
        {m.dir === 'out' && first && !!(src || m.user) && (
          <span className="wa-by">{m.source === 'bot' && <Bot size={11} />} {src || m.user}</span>
        )}
        {m.deleted && <span className="pill warn small">{t('wa.deletedBadge')}</span>}
        {m.quoted && (
          <button type="button" className={'wa-quote ' + m.quoted.dir} onClick={() => a.jump(m.quoted!.id)}>
            <strong>{m.quoted.dir === 'in' ? t('wa.customer') : m.quoted.user || t('wa.you')}</strong>
            <span className="ellipsis">{m.quoted.body}</span>
          </button>
        )}
        {content}
        {showBody && <div className="wa-text"><WaText text={m.body} /></div>}
        {typeof m.lp === 'object' && m.lp && <LinkCard m={m} load={a.linkPreview} />}
        {m.rcheck && <span className={'wa-rcheck ' + m.rcheck.v}>{m.rcheck.s}</span>}
        <span className="wa-meta">
          {m.edited && <span><Pencil size={10} /> {t('wa.edited')}</span>}
          {clock(m.ts, lang)}
          {m.dir === 'out' && <Ticks s={m.status} />}
        </span>
        <button type="button" className="wa-more" onClick={() => setMenu((x) => !x)} aria-label={t('wa.menu')}>
          <ChevronDown size={16} />
        </button>
        {menu && <Menu m={m} a={a} onClose={() => setMenu(false)} />}
      </div>
      {reacts.length > 0 && (
        <div className="wa-reacts">{reacts.join(' ')}</div>
      )}
      {m.status === 'failed' && (
        <button type="button" className="wa-failed" onClick={() => a.retry(m)} title={m.error}>
          <AlertCircle size={13} /> {t('wa.failed')} · {t('wa.retry')}
        </button>
      )}
      {m.wa && m.id > 0 && (m.dir === 'in' || m.dir === 'out') && !menu && (
        <button type="button" className="wa-react-btn" onClick={() => setMenu(true)} aria-label={t('wa.react')}><Smile size={15} /></button>
      )}
    </div>
  );
});
