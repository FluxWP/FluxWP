// Tek mesaj balonu, klasik Flux Chat işaretlemesi (flxc-m / flxc-b / flxc-c): tüm türler, alıntı, tepkiler, tikler,
// silinen mesaj satırı, başarısız + tekrar dene, mesaj menüsü (sağ tık, ▾, uzun bas).
import { memo, useEffect, useRef, useState } from 'react';
import type { FlxcLinkPreview, FlxcMessage, FlxcStatus } from '@fluxwp/core';
import { AudioPlayer, useMediaSrc } from './media';
import { I, Ticks } from './icons';
import { fmt, fmtSecs, fmtSize, useL, waFormat, type L } from './ui';

export const REACTS = ['👍', '❤️', '😂', '😮', '😢', '🤲'];

export interface BubbleActions {
  quote: (m: FlxcMessage) => void;
  jump: (id: number) => void;
  menu: (m: FlxcMessage, anchor: HTMLElement, at?: { x: number; y: number }) => void;
  retry: (m: FlxcMessage) => void;
  toggleDeleted: (m: FlxcMessage) => void;
  lightbox: (src: string, kind: 'image' | 'video', m: FlxcMessage) => void;
  openChatWith?: (phone: string, name: string) => void;
  linkPreview: (url: string) => Promise<FlxcLinkPreview | null>;
  chatTitle: string;
  meId: number;
}

export function statusLabel(s: FlxcStatus, L: L) {
  return ({ pending: L('Gönderiliyor'), sent: L('Gönderildi'), delivered: L('İletildi'), read: L('Okundu'), played: L('Dinlendi'), failed: L('Gönderilemedi') } as Record<string, string>)[s] || '';
}

function sourceTag(m: FlxcMessage, L: L, meId: number) {
  const map: Record<string, string> = { phone: L('Telefondan'), certificate: L('Sertifika'), cert: L('Sertifika'), record: L('Kayıt'), auto: L('Otomatik'), automation: L('Otomatik'), ai: L('Yapay zekâ önerisi'), bot: L('Asistan'), api: L('Sistem'), quick: '' };
  const s = map[m.source];
  if (m.dir === 'out' && m.user && m.userId !== meId) return m.user + (s ? ' · ' + s : '');
  return s || '';
}

function Meta({ m }: { m: FlxcMessage }) {
  const { L, lang } = useL();
  const f = fmt(lang);
  return (
    <span className="flxc-meta">
      {m.edited && <span className="flxc-edited">{L('düzenlendi')}</span>}
      <time dateTime={new Date(m.ts).toISOString()} title={f.long(m.ts) + ' ' + f.time(m.ts)}>{f.time(m.ts)}</time>
      {m.dir === 'out' && <Ticks s={m.status} label={statusLabel(m.status, L)} />}
    </span>
  );
}

const ratio = (w?: number, h?: number) => (w && h ? Math.min(1.6, Math.max(0.5, h / w)) : 0.75);
const thumbUrl = (t?: string) => (!t ? '' : /^(https?:|data:|blob:)/.test(t) ? t : 'data:image/jpeg;base64,' + t);

function ImageMedia({ m, a }: { m: FlxcMessage; a: BubbleActions }) {
  const { L } = useL();
  const md = m.media!;
  const { src } = useMediaSrc(md.url);
  const [broken, setBroken] = useState(false);
  const sticker = m.type === 'sticker';
  const th = thumbUrl(md.thumb);
  return (
    <button
      type="button"
      className={'flxc-img' + (sticker ? ' flxc-sticker' : '') + (broken ? ' is-broken' : '')}
      style={{ ...(sticker ? {} : { aspectRatio: '1 / ' + ratio(md.w, md.h) }), ...(th ? { backgroundImage: `url("${th}")` } : {}) }}
      onClick={() => src && a.lightbox(src, 'image', m)}
    >
      {broken ? (
        <span>
          <I n="image" />
          {L('Görsel yüklenemedi')}
        </span>
      ) : (
        src && <img src={src} alt={m.body || L('Fotoğraf')} loading="lazy" onError={() => setBroken(true)} />
      )}
    </button>
  );
}

function VideoMedia({ m, a }: { m: FlxcMessage; a: BubbleActions }) {
  const { L } = useL();
  const md = m.media!;
  const [want, setWant] = useState(false);
  const { src } = useMediaSrc(md.url, want);
  const [ar, setAr] = useState(ratio(md.w, md.h));
  const [secs, setSecs] = useState(md.secs || 0);
  const th = thumbUrl(md.thumb);
  useEffect(() => {
    if (want && src) {
      a.lightbox(src, 'video', m);
      setWant(false);
    }
  }, [want, src]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <button type="button" className="flxc-img flxc-vid" style={{ aspectRatio: '1 / ' + ar, ...(th ? { backgroundImage: `url("${th}")` } : {}) }} aria-label={L('Videoyu oynat')} onClick={() => (src ? a.lightbox(src, 'video', m) : setWant(true))}>
      {src && !th && !src.startsWith('blob:') && (
        <video
          src={src + '#t=0.1'}
          preload="metadata"
          playsInline
          muted
          tabIndex={-1}
          aria-hidden="true"
          onLoadedMetadata={(e) => {
            const v = e.currentTarget;
            if (!(md.w && md.h) && v.videoWidth) setAr(Math.min(1.6, Math.max(0.5, v.videoHeight / v.videoWidth)));
            if (!md.secs && Number.isFinite(v.duration) && v.duration > 0) setSecs(Math.round(v.duration));
          }}
        />
      )}
      <span className="flxc-vid-play"><I n="play" /></span>
      <span className="flxc-vid-dur"><I n="video" /><em>{secs ? fmtSecs(secs) : ''}</em></span>
    </button>
  );
}

function DocMedia({ m }: { m: FlxcMessage }) {
  const { L, lang } = useL();
  const md = m.media!;
  const [want, setWant] = useState(false);
  const { src } = useMediaSrc(md.url, want);
  useEffect(() => {
    if (want && src) {
      const el = document.createElement('a');
      el.href = src + (src.startsWith('blob:') ? '' : (src.includes('?') ? '&' : '?') + 'download=1');
      el.download = md.name || '';
      document.body.appendChild(el);
      el.click();
      setTimeout(() => el.remove(), 1000);
      setWant(false);
    }
  }, [want, src, md.name]);
  return (
    <a
      className="flxc-doc"
      href="#"
      onClick={(e) => {
        e.preventDefault();
        setWant(true);
      }}
    >
      <span className="flxc-doc-i">
        <I n="doc" />
        <em>{((md.name || '').split('.').pop() || 'file').slice(0, 4).toUpperCase()}</em>
      </span>
      <span className="flxc-doc-t">
        <strong>{md.name || L('Belge')}</strong>
        <small>{[fmtSize(md.size, lang), (md.mime || '').split('/').pop()].filter(Boolean).join(' · ')}</small>
      </span>
      <I n="download" cls="flxc-doc-dl" />
    </a>
  );
}

const lpSeen = new Map<string, FlxcLinkPreview | null>();
const RX_URL = /(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?*_~])/;
function LpCard({ d }: { d: { url: string; title?: string; desc?: string; image?: string; thumb?: string; domain?: string } }) {
  const [bad, setBad] = useState(false);
  const img = d.image || d.thumb || '';
  const wide = !!d.image && !bad;
  let dom = d.domain || '';
  if (!dom) {
    try {
      dom = new URL(d.url).hostname.replace(/^www\./, '');
    } catch {
      dom = '';
    }
  }
  return (
    <a className={'flxc-lp' + (wide ? ' is-wide' : '')} href={d.url} target="_blank" rel="noopener noreferrer">
      {img && !bad && (
        <span className="flxc-lp-img">
          <img src={img} alt="" loading="lazy" referrerPolicy="no-referrer" onError={() => setBad(true)} />
        </span>
      )}
      <span className="flxc-lp-t">
        {d.title && <strong>{d.title}</strong>}
        {d.desc && <small>{d.desc}</small>}
        <em>{dom}</em>
      </span>
    </a>
  );
}
function LpSlot({ m, text, load }: { m: FlxcMessage; text: string; load: BubbleActions['linkPreview'] }) {
  const own = typeof m.lp === 'object' && m.lp ? m.lp : null;
  const u = own?.url || RX_URL.exec(text)?.[1] || '';
  const [d, setD] = useState<FlxcLinkPreview | null>(lpSeen.get(u) ?? null);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!u || lpSeen.has(u)) return;
    let live = true;
    const go = () =>
      void load(u).then((r) => {
        lpSeen.set(u, r);
        if (live) setD(r);
      });
    const el = ref.current;
    if (el && typeof IntersectionObserver !== 'undefined') {
      const ob = new IntersectionObserver(
        (es) => {
          if (es.some((e) => e.isIntersecting)) {
            ob.disconnect();
            go();
          }
        },
        { rootMargin: '400px 0px' },
      );
      ob.observe(el);
      return () => {
        live = false;
        ob.disconnect();
      };
    }
    go();
    return () => {
      live = false;
    };
  }, [u, load]);
  const card = d && d.title ? d : own && (own.title || own.desc) ? own : null;
  if (!u || (!card && lpSeen.has(u))) return null;
  return <div className="flxc-lp-slot" ref={ref}>{card && <LpCard d={card} />}</div>;
}

function Content({ m, a }: { m: FlxcMessage; a: BubbleActions }) {
  const { L } = useL();
  const md = m.media;
  let body: React.ReactNode = null;
  switch (m.type) {
    case 'deleted':
      body = <span className="flxc-deleted"><I n="close" />{L('Bu mesaj silindi')}</span>;
      break;
    case 'image':
    case 'sticker':
      body = md ? <ImageMedia m={m} a={a} /> : null;
      break;
    case 'video':
      body = md ? <VideoMedia m={m} a={a} /> : null;
      break;
    case 'audio':
      body = md ? <AudioPlayer url={md.url} secs={md.secs} ptt={md.ptt} /> : null;
      break;
    case 'document':
      body = md ? <DocMedia m={m} /> : null;
      break;
    case 'location': {
      const lq = m.loc ? m.loc.lat + ',' + m.loc.lng : '';
      body = (
        <a className="flxc-loc" href={'https://maps.google.com/?q=' + encodeURIComponent(lq)} target="_blank" rel="noopener">
          <span className="flxc-loc-map"><I n="loc" /></span>
          <span>
            <strong>{m.loc?.name || L('Konum')}</strong>
            <small>{lq}</small>
          </span>
        </a>
      );
      break;
    }
    case 'contact':
      body = (
        <div className="flxc-vcard">
          <span className="flxc-av flxc-av-sm" style={{ background: '#8696a0' }}><I n="person" /></span>
          <span>
            <strong>{m.body || L('Kişi kartı')}</strong>
            {m.vcardPhone && <small>+{m.vcardPhone}</small>}
          </span>
          {m.vcardPhone && a.openChatWith && (
            <button type="button" className="flxc-link" onClick={() => a.openChatWith!(m.vcardPhone!, m.body)}>
              {L('Mesaj gönder')}
            </button>
          )}
        </div>
      );
      break;
    case 'unknown':
    case 'poll':
      body = <span className="flxc-deleted"><I n="warn" />{L('Bu mesaj türü burada gösterilemiyor. Telefondan bakın.')}</span>;
      break;
  }
  const text = m.type === 'contact' || m.type === 'deleted' || m.type === 'location' || m.type === 'unknown' || m.type === 'poll' ? '' : m.body;
  const jumbo = !!text && m.type === 'text' && /^(\p{Extended_Pictographic}|️|‍|\s){1,6}$/u.test(text);
  const over = !text && (m.type === 'image' || m.type === 'video' || m.type === 'sticker');
  return (
    <div className="flxc-c">
      {body}
      {text && m.lp !== false && m.type === 'text' && <LpSlot m={m} text={text} load={a.linkPreview} />}
      {text && (
        <div className={'flxc-text' + (jumbo ? ' is-jumbo' : '')}>
          <span dangerouslySetInnerHTML={{ __html: waFormat(text) }} />
          <span className={'flxc-meta-sp' + (m.edited ? ' is-edited' : '')} />
        </div>
      )}
      {over ? (
        <span className="flxc-meta is-over">
          <MetaInner m={m} />
        </span>
      ) : (
        <Meta m={m} />
      )}
    </div>
  );
}
function MetaInner({ m }: { m: FlxcMessage }) {
  const { L, lang } = useL();
  const f = fmt(lang);
  return (
    <>
      {m.edited && <span className="flxc-edited">{L('düzenlendi')}</span>}
      <time dateTime={new Date(m.ts).toISOString()} title={f.long(m.ts) + ' ' + f.time(m.ts)}>{f.time(m.ts)}</time>
      {m.dir === 'out' && <Ticks s={m.status} label={statusLabel(m.status, L)} />}
    </>
  );
}

function delLabel(m: FlxcMessage, L: L) {
  const d = m.del;
  const what = ({ contact: L('Kişi bu mesajı sildi'), phone: L('Telefondan silindi'), everyone: L('Herkesten silindi'), panel: L('Panelde gizlendi') } as Record<string, string>)[d?.scope ?? ''] || L('Bu mesaj silindi');
  return [what, d?.by, d?.ago].filter(Boolean).join(' · ');
}

function longPress(fn: () => void) {
  let tm: ReturnType<typeof setTimeout> | undefined;
  let moved = false;
  return {
    onTouchStart: () => {
      moved = false;
      tm = setTimeout(() => {
        if (!moved) {
          fn();
          navigator.vibrate?.(15);
        }
      }, 520);
    },
    onTouchMove: () => {
      moved = true;
      clearTimeout(tm);
    },
    onTouchEnd: () => clearTimeout(tm),
  };
}

export const Bubble = memo(function Bubble({ m, a, first, open, flash }: { m: FlxcMessage; a: BubbleActions; first: boolean; open: boolean; flash: boolean }) {
  const { L, lang } = useL();
  const bRef = useRef<HTMLDivElement>(null);
  const f = fmt(lang);
  if (m.dir === 'system') {
    return (
      <div className="flxc-sys" data-mid={m.id}>
        <span>{m.body + ' · ' + f.time(m.ts)}</span>
      </div>
    );
  }
  const isNote = m.dir === 'note';
  const dirCls = isNote ? 'note' : m.dir;
  if (m.deleted && (!open || m.del?.empty)) {
    const canOpen = !m.del?.empty;
    const tip = [delLabel(m, L), m.del?.atText, L('Yapay zekâ bu mesajı okumaz.')].filter(Boolean).join('\n');
    const inner = (
      <>
        <span className="flxc-deleted">
          <I n="block" />
          {L('Bu mesaj silindi')}
          {canOpen && <small className="flxc-del-show">{L('Göster')}</small>}
        </span>
        <Meta m={m} />
      </>
    );
    return (
      <div className={'flxc-m flxc-m-' + dirCls + ' is-deleted is-folded'} data-mid={m.id} id={`wa-m-${m.id}`}>
        {canOpen ? (
          <button type="button" className="flxc-b flxc-b-folded" title={tip} aria-expanded="false" onClick={() => a.toggleDeleted(m)}>
            {inner}
          </button>
        ) : (
          <div className="flxc-b flxc-b-folded" title={tip}>
            {inner}
          </div>
        )}
      </div>
    );
  }
  const tmp = !!m.local || m.id < 0;
  const reacts = Object.values(m.react ?? {}).filter(Boolean);
  const tag = isNote ? L('Dahili not · {n}', { n: m.user || '' }) : sourceTag(m, L, a.meId);
  const media = !m.deleted && (m.type === 'image' || m.type === 'video') && !!m.media;
  const hasLp = m.type === 'text' && m.lp !== false && !!m.body && (RX_URL.test(m.body) || (typeof m.lp === 'object' && !!m.lp));
  const rowCls =
    'flxc-m flxc-m-' + dirCls + (first ? ' is-first' : '') + (tmp ? ' is-tmp' : '') + (m.status === 'failed' ? ' is-failed' : '') + (m.type === 'sticker' ? ' is-sticker' : '') + (m.deleted ? ' is-deleted' : '') + (reacts.length ? ' has-react' : '') + (flash ? ' is-flash' : '');
  const canMenu = !tmp && !m.deleted;
  const lp = canMenu ? longPress(() => bRef.current && a.menu(m, bRef.current)) : {};
  return (
    <div className={rowCls} data-mid={m.id} id={`wa-m-${m.id}`}>
      <div
        ref={bRef}
        className={'flxc-b' + (media ? ' has-media' : '') + (hasLp ? ' has-lp' : '')}
        {...lp}
        onContextMenu={(e) => {
          if (!canMenu) return;
          if (matchMedia('(hover: none)').matches) return e.preventDefault();
          const sel = String(window.getSelection?.() ?? '');
          if (sel || (e.target as HTMLElement).closest('a[href]')) return;
          e.preventDefault();
          a.menu(m, e.currentTarget, { x: e.clientX, y: e.clientY });
        }}
        onDoubleClick={(e) => {
          if (canMenu && !(e.target as HTMLElement).closest('a,audio,video,button')) a.quote(m);
        }}
      >
        {m.deleted && (
          <button type="button" className="flxc-b-del" title={L('Gizle')} onClick={() => a.toggleDeleted(m)}>
            <I n="block" />
            {delLabel(m, L)}
            <span className="flxc-b-del-x"><I n="down" /></span>
          </button>
        )}
        {tag && (
          <div className="flxc-b-tag">
            {isNote && <I n="note" />}
            {tag}
          </div>
        )}
        {m.sender && m.dir === 'in' && <div className="flxc-b-sender">{m.sender}</div>}
        {m.quoted && (
          <button type="button" className={'flxc-quote flxc-quote-' + m.quoted.dir} onClick={() => a.jump(m.quoted!.id)}>
            <strong>{m.quoted.dir === 'in' ? a.chatTitle : L('Siz')}</strong>
            <span>{m.quoted.body}</span>
          </button>
        )}
        <Content m={m} a={a} />
        {m.rcheck && m.dir === 'in' && (m.type === 'image' || m.type === 'document') && (
          <div className={'flxc-rai is-' + m.rcheck.v} title={L('Yapay zekâ ön kontrolü. Son kontrol ekipte.')}>
            <I n={m.rcheck.v === 'match' ? 'check' : 'warn'} />
            <span>{'AI · ' + m.rcheck.s}</span>
          </div>
        )}
        {canMenu && (
          <button
            type="button"
            className="flxc-m-menu"
            aria-label={L('Mesaj menüsü')}
            onClick={(e) => {
              e.stopPropagation();
              a.menu(m, e.currentTarget);
            }}
          >
            <I n="down" />
          </button>
        )}
      </div>
      {reacts.length > 0 && <span className="flxc-react">{reacts.join('')}</span>}
      {m.status === 'failed' && (
        <>
          <button type="button" className="flxc-retry" title={m.error || ''} onClick={() => a.retry(m)}>
            <I n="reopen" />
            {L('Tekrar dene')}
          </button>
          {m.error && <div className="flxc-err">{m.error}</div>}
        </>
      )}
    </div>
  );
});
