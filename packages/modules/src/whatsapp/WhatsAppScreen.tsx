// Flux Chat (Çağrı Merkezi › Sohbetler). Klasik WhatsApp Web görünümüyle birebir: aynı işaretleme ve sınıflar
// (#flxc-app, flxc-side / flxc-main / flxc-info), stil flxc.css (plugins/flx-chat/tools/app.css'ten üretilir).
// Sohbetler, mesajlar ve canlı güncellemeler GraphQL motorundan (WaEngine) gelir.
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useStore } from 'zustand';
import type { FlxcChat, FlxcFilter, FlxcMessage } from '@fluxwp/core';
import { useHost } from '../host';
import { WaEngine, type WaState } from './engine';
import { Bubble, REACTS, statusLabel, type BubbleActions } from './Bubble';
import { Composer } from './Composer';
import { InfoPanel } from './InfoPanel';
import { EmojiPicker, NewChat, emoRecent } from './extras';
import { notifyIncoming } from './notify';
import { I, Ticks } from './icons';
import { Av, Ib, Modal, UiCtx, dayLabelL, fmt, initials, listTimeL, sameDayTs, store, useL, useMenuHost, useUi, waFormat, type L } from './ui';
import './flxc.css';

export function WhatsAppScreen() {
  const host = useHost();
  const engine = useMemo(
    () => {
      const e: WaEngine = new WaEngine({
        client: host.client,
        onNonce: host.setNonce,
        onUnreadChange: () => host.refreshCounts?.(),
        realtime: host.realtime,
        onIncoming: (items) => notifyIncoming(items, e.s.active, host.notify, (id) => e.open(id)),
      });
      return e;
    },
    [host.client], // eslint-disable-line react-hooks/exhaustive-deps
  );
  useEffect(() => {
    void engine.start();
    return () => engine.stop();
  }, [engine]);
  return <WhatsApp engine={engine} />;
}

function useWa<T>(engine: WaEngine, sel: (s: WaState) => T): T {
  return useStore(engine.store, sel);
}

type Theme = 'auto' | 'light' | 'dark';
interface Toast {
  id: number;
  msg: string;
  kind?: 'err';
  out?: boolean;
}
interface Lb {
  src: string;
  kind: 'image' | 'video';
  m?: FlxcMessage | null;
  who?: string;
}

const mobile = () => typeof matchMedia === 'function' && matchMedia('(max-width: 899px)').matches;

function WhatsApp({ engine }: { engine: WaEngine }) {
  const { L } = useL();
  const host = useHost();
  const active = useWa(engine, (s) => s.active);
  const bootError = useWa(engine, (s) => s.bootError);
  const [root, setRoot] = useState<HTMLDivElement | null>(null);
  const [theme, setThemeS] = useState<Theme>(() => (store('theme') as Theme) || 'auto');
  const [info, setInfo] = useState(false);
  const [chips, setChips] = useState(() => store('chips') !== '0');
  const [nc, setNc] = useState<{ phone?: string; name?: string } | null>(null);
  const [lb, setLb] = useState<Lb | null>(null);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [inject, setInject] = useState<{ text: string; n: number } | null>(null);
  const seq = useRef(0);
  const { menu, closeMenu, node: menuNode } = useMenuHost(root);

  const toast = useCallback((msg: string, kind?: 'err') => {
    if (!msg) return;
    const id = ++seq.current;
    setToasts((l) => [...l, { id, msg, kind }]);
    setTimeout(() => {
      setToasts((l) => l.map((x) => (x.id === id ? { ...x, out: true } : x)));
      setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), 300);
    }, kind === 'err' ? 5000 : 2400);
  }, []);
  const ui = useMemo(() => ({ root, menu, closeMenu, toast }), [root, menu, closeMenu, toast]);

  useEffect(() => {
    const dark = theme === 'dark' || (theme === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('flxc-dark-doc', dark);
    return () => document.documentElement.classList.remove('flxc-dark-doc');
  }, [theme]);
  const setTheme = (v: Theme) => (setThemeS(v), store('theme', v));

  useEffect(() => {
    const f = store('filter') as FlxcFilter | null;
    if (f && f !== engine.s.filter) engine.setView({ filter: f });
  }, [engine]);
  useEffect(() => {
    if (!active) setInfo(false);
  }, [active]);

  const settingsUrl = (host.adminUrl ?? '') + 'admin.php?page=flx-chat-ayarlar';
  const openChat = (id: number) => engine.open(id);

  if (bootError) {
    return (
      <div id="flxc-app" className={'flxc' + (host.mode === 'app' ? ' is-embed' : '')} data-theme={theme}>
        <main className="flxc-main" style={{ gridColumn: '1 / -1' }}>
          <div className="flxc-empty">
            <h2>{L('Sohbetler yüklenemedi')}</h2>
            <p>{bootError}</p>
            <button type="button" className="flxc-btn" onClick={() => location.reload()}>
              <I n="reopen" />
              {L('Yenile')}
            </button>
          </div>
        </main>
      </div>
    );
  }

  return (
    <UiCtx.Provider value={ui}>
      <div ref={setRoot} id="flxc-app" className={'flxc' + (host.mode === 'app' ? ' is-embed' : '') + (info ? ' is-info' : '')} data-theme={theme} data-view={active ? 'chat' : 'list'}>
        <Side
          engine={engine}
          settingsUrl={settingsUrl}
          theme={theme}
          setTheme={setTheme}
          chips={chips}
          setChips={(v) => (setChips(v), store('chips', v ? '1' : '0'))}
          onNew={(phone) => setNc({ phone })}
          openInfo={(id) => (openChat(id), setInfo(true))}
        />
        <main className={'flxc-main' + (active ? ' has-chat' : '')}>
          {active ? (
            <Conversation
              key={active}
              engine={engine}
              id={active}
              info={info}
              setInfo={setInfo}
              chips={chips}
              settingsUrl={settingsUrl}
              inject={inject}
              onNew={(phone, name) => setNc({ phone, name })}
              lightbox={(src, kind, m) => setLb({ src, kind, m })}
            />
          ) : (
            <EmptyMain onNew={() => setNc({})} />
          )}
        </main>
        <section className="flxc-info" aria-hidden={!info}>
          {info && active && (
            <InfoPanel
              engine={engine}
              id={active}
              onClose={() => setInfo(false)}
              onInsert={(s) => {
                setInject((x) => ({ text: s, n: (x?.n ?? 0) + 1 }));
                if (mobile()) setInfo(false);
              }}
              onLightbox={(src) => setLb({ src, kind: 'image', who: engine.s.chats[active]?.title })}
            />
          )}
        </section>
        <div className="flxc-toasts" aria-live="polite">
          {toasts.map((x) => (
            <div key={x.id} className={'flxc-toast' + (x.kind ? ' is-' + x.kind : '') + (x.out ? ' is-out' : '')} role={x.kind === 'err' ? 'alert' : 'status'}>
              {x.msg}
            </div>
          ))}
        </div>
        {menuNode}
        {nc && <NewChat engine={engine} prefill={nc.phone} prefName={nc.name} onClose={() => setNc(null)} onOpen={(id) => (setNc(null), openChat(id))} toast={toast} />}
        {lb && <Lightbox lb={lb} chatTitle={active ? (engine.s.chats[active]?.title ?? '') : ''} onClose={() => setLb(null)} />}
      </div>
    </UiCtx.Provider>
  );
}

/* ---------- ışık kutusu ---------- */

function Lightbox({ lb, chatTitle, onClose }: { lb: Lb; chatTitle: string; onClose: () => void }) {
  const { L, lang } = useL();
  const f = fmt(lang);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', k, true);
    return () => document.removeEventListener('keydown', k, true);
  }, [onClose]);
  const m = lb.m;
  const who = lb.who ?? (!m ? '' : m.dir === 'out' ? L('Siz') : m.sender || chatTitle);
  const dl = lb.src.startsWith('blob:') ? lb.src : lb.src + (lb.src.includes('?') ? '&' : '?') + 'download=1';
  return (
    <div
      className="flxc-lb"
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        const t = e.target as HTMLElement;
        if (t === e.currentTarget || t.tagName === 'IMG') onClose();
      }}
    >
      <div className="flxc-lb-bar">
        <span className="flxc-lb-who">
          <strong>{who}</strong>
          {m?.ts ? <small>{f.long(m.ts) + ' ' + f.time(m.ts)}</small> : null}
        </span>
        <a className="flxc-ib" href={dl} download={m?.media?.name || ''} title={L('İndir')}>
          <I n="download" />
        </a>
        <Ib n="close" title={L('Kapat')} onClick={onClose} />
      </div>
      {lb.kind === 'video' ? <video src={lb.src} controls autoPlay playsInline preload="auto" /> : <img src={lb.src} alt={m?.body || ''} />}
      {m?.body && <div className="flxc-lb-cap" dangerouslySetInnerHTML={{ __html: waFormat(m.body) }} />}
    </div>
  );
}

/* ---------- boş ana alan ---------- */

const EMPTY_ART =
  '<svg viewBox="0 0 220 160" aria-hidden="true"><defs><linearGradient id="flxcg" x1="0" x2="1"><stop offset="0" stop-color="var(--flxc-accent)" stop-opacity=".18"/><stop offset="1" stop-color="var(--flxc-accent)" stop-opacity=".05"/></linearGradient></defs><circle cx="110" cy="80" r="74" fill="url(#flxcg)"/><rect x="48" y="40" width="96" height="58" rx="14" fill="var(--flxc-bubble-in)" stroke="var(--flxc-line)"/><path d="M62 98l-6 16 20-16z" fill="var(--flxc-bubble-in)"/><rect x="62" y="56" width="60" height="7" rx="3.5" fill="var(--flxc-muted)" opacity=".35"/><rect x="62" y="70" width="42" height="7" rx="3.5" fill="var(--flxc-muted)" opacity=".25"/><rect x="92" y="78" width="84" height="48" rx="14" fill="var(--flxc-bubble-out)"/><path d="M162 126l8 14-22-14z" fill="var(--flxc-bubble-out)"/><rect x="104" y="92" width="52" height="7" rx="3.5" fill="var(--flxc-accent)" opacity=".45"/><path d="M146 110l4 4 8-8M152 114l1 0 8-8" stroke="#53bdeb" stroke-width="2.2" fill="none" stroke-linecap="round"/><path d="M178 44c8-10 22-8 22-8s0 14-10 20c-6 3-12 0-12-12z" fill="var(--flxc-accent)" opacity=".55"/><path d="M178 56c4-6 8-10 14-14" stroke="var(--flxc-accent)" stroke-width="2" fill="none"/></svg>';

function brandName(L: L, site?: string) {
  return site || L('Çağrı Merkezi');
}

function EmptyMain({ onNew }: { onNew: () => void }) {
  const { L } = useL();
  const host = useHost();
  return (
    <div className="flxc-empty">
      <div className="flxc-empty-art" dangerouslySetInnerHTML={{ __html: EMPTY_ART }} />
      <h2>{brandName(L, host.siteName)}</h2>
      <p>{L('Gelen WhatsApp mesajları burada canlı görünür. Soldan bir sohbet seçin ya da yeni bir sohbet başlatın.')}</p>
      <button type="button" className="flxc-btn" onClick={onNew}>
        <I n="newchat" />
        {L('Yeni sohbet')}
      </button>
      <p className="flxc-empty-foot">
        <I n="lock" />
        {L('Mesajlar yalnız yetkili temsilcilere görünür.')}
      </p>
    </div>
  );
}

/* ---------- sol: başlık, arama, filtreler, liste ---------- */

const FILTERS: [FlxcFilter, string][] = [
  ['all', 'Tümü'],
  ['unread', 'Okunmamış'],
  ['mine', 'Bana atanan'],
  ['unassigned', 'Atanmamış'],
  ['ai', 'Asistan'],
  ['closed', 'Kapalı'],
];

function Side({
  engine,
  settingsUrl,
  theme,
  setTheme,
  chips,
  setChips,
  onNew,
  openInfo,
}: {
  engine: WaEngine;
  settingsUrl: string;
  theme: Theme;
  setTheme: (t: Theme) => void;
  chips: boolean;
  setChips: (v: boolean) => void;
  onNew: (phone?: string) => void;
  openInfo: (id: number) => void;
}) {
  const { L } = useL();
  const host = useHost();
  const ui = useUi();
  const order = useWa(engine, (s) => s.order);
  const chats = useWa(engine, (s) => s.chats);
  const filter = useWa(engine, (s) => s.filter);
  const label = useWa(engine, (s) => s.label);
  const loading = useWa(engine, (s) => s.listLoading);
  const more = useWa(engine, (s) => s.listMore);
  const boot = useWa(engine, (s) => s.boot);
  const conns = useWa(engine, (s) => s.conns);
  const offline = useWa(engine, (s) => s.offline);
  const [q, setQ] = useState('');
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = setTimeout(() => q.trim() !== engine.s.q && engine.setView({ q: q.trim() }), 280);
    return () => clearTimeout(h);
  }, [q, engine]);

  const me = boot?.me;
  const others = (boot?.channels ?? []).filter((x) => x.active && x.id !== 'whatsapp');
  const open = conns.filter((c) => c.state === 'open').length;
  let state = !conns.length ? 'none' : open === conns.length ? 'open' : open ? 'partial' : conns.some((c) => c.state) ? 'close' : 'unknown';
  let connText = (
    {
      none: L('Bağlantı yok'),
      open: conns.length > 1 ? L('{n} hat bağlı', { n: open }) : L('WhatsApp bağlı'),
      partial: L('{n}/{m} hat bağlı', { n: open, m: conns.length }),
      close: L('WhatsApp bağlı değil'),
      unknown: L('Durum bilinmiyor'),
    } as Record<string, string>
  )[state]!;
  if (state === 'open' && others.length) connText += ' · ' + others.map((x) => x.label).join(', ');
  let warn = state === 'none' || state === 'close';
  if (state === 'none' && others.length) {
    state = 'open';
    connText = others.map((x) => x.label).join(', ');
    warn = false;
  }
  if (!boot) {
    state = 'unknown';
    connText = '';
    warn = false;
  }

  const sideMenu = (anchor: HTMLElement) =>
    ui.menu(anchor, [
      { icon: 'newchat', label: L('Yeni sohbet'), fn: () => onNew() },
      me?.manage ? { icon: 'bolt', label: L('Hazır cevaplar'), fn: () => host.openUrl(settingsUrl) } : null,
      { icon: 'bolt', label: chips ? L('Hazır cevap baloncuklarını gizle') : L('Hazır cevap baloncuklarını göster'), fn: () => setChips(!chips) },
      { sep: true },
      { icon: 'moon', label: L('Tema: Otomatik'), on: theme === 'auto', fn: () => setTheme('auto') },
      { icon: 'moon', label: L('Tema: Açık'), on: theme === 'light', fn: () => setTheme('light') },
      { icon: 'moon', label: L('Tema: Koyu'), on: theme === 'dark', fn: () => setTheme('dark') },
      { sep: true },
      typeof Notification !== 'undefined' && Notification.permission !== 'granted'
        ? { icon: 'bell', label: L('Masaüstü bildirimlerini aç'), fn: () => void Notification.requestPermission().then((p) => ui.toast(p === 'granted' ? L('Bildirimler açıldı') : L('Bildirim izni verilmedi'))) }
        : null,
      me?.manage && host.mode === 'wp' ? { icon: 'gear', label: L('Ayarlar'), fn: () => host.openUrl(settingsUrl) } : null,
      host.mode === 'wp' && host.adminUrl ? { icon: 'exit', label: L('WordPress paneline dön'), fn: () => host.openUrl(host.adminUrl!) } : null,
    ]);

  const digits = q.replace(/\D+/g, '');
  return (
    <aside className="flxc-side">
      <header className="flxc-side-head">
        <div className="flxc-brand">
          <span className="flxc-me">{me && <Av c={{ id: 'me' + me.id, title: me.name || '?' }} size="sm" />}</span>
          <div className="flxc-brand-t">
            <strong>{brandName(L, host.siteName)}</strong>
            <span className="flxc-brand-s">
              <span className={'flxc-conn-dot flxc-conn-' + state} />
              <span>{connText}</span>
            </span>
          </div>
        </div>
        <div className="flxc-head-actions">
          <Ib n="newchat" title={L('Yeni sohbet')} onClick={() => onNew()} />
          {me?.manage && <Ib n="bolt" title={L('Hazır cevaplar')} onClick={() => host.openUrl(settingsUrl)} />}
          <Ib n="more" title={L('Menü')} onClick={(e) => sideMenu(e.currentTarget)} />
        </div>
      </header>
      <div className="flxc-banner" hidden={!warn && !offline}>
        {(warn || offline) && (
          <>
            <I n="warn" />
            <div>
              {offline ? (
                <strong>{offline === 'auth' ? L('Oturum süresi doldu. Sayfayı yenileyin.') : L('Sunucuya ulaşılamıyor, yeniden deneniyor…')}</strong>
              ) : (
                <>
                  <strong>{state === 'none' ? L('Henüz iletişim kanalı kurulmadı') : L('WhatsApp bağlantısı koptu')}</strong>
                  <span>{state === 'none' ? L('Mesaj almak için iletişim ayarlarından bir kanal kurun.') : L('Telefon çevrimdışı olabilir ya da QR yeniden okutulmalı.')}</span>
                  {me?.manage && <a href={settingsUrl}>{state === 'none' ? L('İletişim ayarlarına git') : L('Ayarlara git')}</a>}
                </>
              )}
            </div>
          </>
        )}
      </div>
      <div className="flxc-search">
        <I n="search" cls="flxc-search-i" />
        <input
          type="search"
          className="flxc-search-in"
          placeholder={L('Ara veya yeni sohbet başlat')}
          aria-label={L('Ara')}
          autoComplete="off"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && digits.length >= 10 && !order.length) onNew(q);
          }}
        />
      </div>
      <div className="flxc-filters" role="tablist">
        {FILTERS.map(([f, lbl]) => {
          const on = filter === f && !label;
          return (
            <button
              key={f}
              type="button"
              role="tab"
              className={'flxc-chip' + (on ? ' is-on' : '')}
              aria-selected={on}
              onClick={() => {
                store('filter', f);
                engine.setView({ filter: f, label: '' });
              }}
            >
              {L(lbl)}
            </button>
          );
        })}
        {(boot?.labels ?? []).map((l) => (
          <button key={l.id} type="button" className={'flxc-chip flxc-chip-label' + (label === l.id ? ' is-on' : '')} style={{ ['--lc' as string]: l.color }} onClick={() => engine.setView({ label: label === l.id ? '' : l.id })}>
            <i className="flxc-dot" />
            {l.name}
          </button>
        ))}
      </div>
      <div
        ref={listRef}
        className="flxc-list"
        role="list"
        onScroll={(e) => {
          const el = e.currentTarget;
          if (more && !loading && el.scrollTop + el.clientHeight > el.scrollHeight - 200) void engine.loadChats(true);
        }}
      >
        {!order.length && !loading && (
          <div className="flxc-list-empty">
            <p>{engine.s.q ? L('Sonuç bulunamadı.') : filter === 'unread' ? L('Okunmamış mesaj yok. 🎉') : L('Henüz sohbet yok.')}</p>
            {digits.length >= 10 && (
              <button type="button" className="flxc-btn flxc-btn-sm" onClick={() => onNew(q)}>
                <I n="newchat" />
                {L('{p} ile sohbet başlat', { p: q })}
              </button>
            )}
          </div>
        )}
        {order.map((id) => chats[id] && <ChatRow key={id} engine={engine} c={chats[id]} openInfo={openInfo} />)}
      </div>
    </aside>
  );
}

function chatMenuItems(engine: WaEngine, c: FlxcChat, L: L, meId: number, openInfo: (id: number) => void, toast: (m: string, k?: 'err') => void) {
  const patch = (p: Parameters<WaEngine['update']>[1], ok?: string) =>
    void engine.update(c.id, p).then(
      () => ok && toast(ok),
      (e: Error) => toast(e.message, 'err'),
    );
  return [
    { icon: 'person', label: L('Kişi bilgisi'), fn: () => openInfo(c.id) },
    { icon: 'pin', label: c.pinned ? L('Sabitlemeyi kaldır') : L('Sabitle'), fn: () => patch({ pinned: !c.pinned }) },
    c.unread ? null : { icon: 'unread', label: L('Okunmadı olarak işaretle'), fn: () => patch({ unread: 1 }) },
    { icon: 'assign', label: c.assigned === meId ? L('Atamamı kaldır') : L('Bana ata'), fn: () => patch({ assigned: c.assigned === meId ? 0 : meId }) },
    { sep: true },
    c.status === 'closed' ? { icon: 'reopen', label: L('Sohbeti yeniden aç'), fn: () => patch({ status: 'open' }) } : { icon: 'done', label: L('Sohbeti kapat'), fn: () => patch({ status: 'closed' }, L('Sohbet kapatıldı')) },
  ];
}

const TYPE_ICON: Record<string, string> = { image: 'image', video: 'video', audio: 'mic', document: 'doc', location: 'loc', contact: 'person' };

function ChatRow({ engine, c, openInfo }: { engine: WaEngine; c: FlxcChat; openInfo: (id: number) => void }) {
  const { L, lang } = useL();
  const ui = useUi();
  const sel = useWa(engine, (s) => s.active === c.id);
  const fresh = useWa(engine, (s) => (s.fresh[c.id] ?? 0) > Date.now() - 1500);
  const labels = useWa(engine, (s) => s.boot?.labels ?? []);
  const agents = useWa(engine, (s) => s.boot?.agents ?? []);
  const meId = useWa(engine, (s) => s.boot?.me.id ?? 0);
  const last = c.last;
  let prev: React.ReactNode;
  let typing = false;
  if (c.typing) {
    typing = true;
    prev = c.typing === 2 ? L('ses kaydediyor…') : L('yazıyor…');
  } else if (c.botBusy) {
    typing = true;
    prev = L('Asistan yazıyor…');
  } else if (last) {
    const ti = TYPE_ICON[last.type];
    prev = (
      <>
        {last.dir === 'out' && <TicksSmall s={last.status} L={L} />}
        {last.dir === 'out' && last.user && agents.length > 1 && <b>{last.user.split(' ')[0] + ': '}</b>}
        {ti && <I n={ti} cls="flxc-prev-i" />}
        {last.body}
      </>
    );
  } else prev = c.phoneFmt;
  const ag = c.assigned ? agents.find((a) => a.id === c.assigned)?.name : '';
  const rowRef = useRef<HTMLDivElement>(null);
  const menu = (anchor: HTMLElement) => ui.menu(anchor, chatMenuItems(engine, c, L, meId, openInfo, ui.toast));
  const press = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  return (
    <div
      ref={rowRef}
      className={'flxc-row' + (sel ? ' is-active' : '') + (c.unread ? ' is-unread' : '') + (fresh ? ' is-new' : '') + (c.status === 'closed' ? ' is-closed' : '')}
      role="listitem"
      tabIndex={0}
      data-id={c.id}
      onClick={() => engine.open(c.id)}
      onKeyDown={(e) => e.key === 'Enter' && engine.open(c.id)}
      onTouchStart={() => (press.current = setTimeout(() => rowRef.current && menu(rowRef.current), 520))}
      onTouchMove={() => clearTimeout(press.current)}
      onTouchEnd={() => clearTimeout(press.current)}
    >
      <Av c={c} />
      <div className="flxc-row-main">
        <div className="flxc-row-top">
          <span className="flxc-row-name">
            <span>{c.title}</span>
            <span className="flxc-row-labels">
              {c.labels.map((id) => {
                const l = labels.find((x) => x.id === id);
                return l ? <i key={id} className="flxc-dot" style={{ ['--lc' as string]: l.color }} title={l.name} /> : null;
              })}
            </span>
          </span>
          <span className="flxc-row-time">{listTimeL(c.lastTs, L, lang)}</span>
        </div>
        <div className="flxc-row-bot">
          <span className={'flxc-row-prev' + (typing ? ' is-typing' : '')}>{prev}</span>
          <span className="flxc-row-badges">
            {c.pinned > 0 && <I n="pin" cls="flxc-pin" />}
            {ag && (
              <span className={'flxc-row-ag' + (c.assigned === meId ? ' is-me' : '')} title={L('Atanan: {n}', { n: ag })}>
                {initials(ag)}
              </span>
            )}
            {c.unread > 0 && <span className="flxc-badge">{c.unread > 99 ? '99+' : String(c.unread)}</span>}
          </span>
        </div>
      </div>
      <button
        type="button"
        className="flxc-row-menu"
        aria-label={L('Sohbet menüsü')}
        onClick={(e) => {
          e.stopPropagation();
          menu(e.currentTarget);
        }}
      >
        <I n="down" />
      </button>
    </div>
  );
}

function TicksSmall({ s, L }: { s: FlxcMessage['status']; L: L }) {
  return <Ticks s={s} label={statusLabel(s, L)} />;
}

/* ---------- sohbet ---------- */

function sameGroup(a: FlxcMessage | undefined, b: FlxcMessage) {
  return !!a && a.dir === b.dir && a.dir !== 'system' && (a.userId === b.userId || a.dir === 'in') && b.ts - a.ts < 5 * 60000 && sameDayTs(a.ts, b.ts) && a.sender === b.sender;
}

function Conversation({
  engine,
  id,
  info,
  setInfo,
  chips,
  settingsUrl,
  inject,
  onNew,
  lightbox,
}: {
  engine: WaEngine;
  id: number;
  info: boolean;
  setInfo: (v: boolean) => void;
  chips: boolean;
  settingsUrl: string;
  inject: { text: string; n: number } | null;
  onNew: (phone: string, name: string) => void;
  lightbox: BubbleActions['lightbox'];
}) {
  const { L, lang } = useL();
  const ui = useUi();
  const c = useWa(engine, (s) => s.chats[id]);
  const box = useWa(engine, (s) => s.msgs[id]);
  const boot = useWa(engine, (s) => s.boot);
  const conns = useWa(engine, (s) => s.conns);
  const [quote, setQuote] = useState<FlxcMessage | null>(null);
  const [flash, setFlash] = useState(0);
  const [atBottom, setAtBottom] = useState(true);
  const [newCount, setNewCount] = useState(0);
  const [delOpen, setDelOpen] = useState<Set<number>>(() => new Set());
  const [del, setDel] = useState<FlxcMessage | null>(null);
  const scroller = useRef<HTMLDivElement>(null);
  const stick = useRef(true);
  const inner = useRef<HTMLDivElement>(null);
  const prevHeight = useRef(0);
  const prevLast = useRef(0);
  const list = box?.list ?? [];
  const lastId = list.length ? list[list.length - 1]!.id : 0;
  const meId = boot?.me.id ?? 0;

  useLayoutEffect(() => {
    const el = scroller.current;
    if (!el) return;
    if (prevHeight.current && el.scrollTop < 40 && lastId === prevLast.current) {
      el.scrollTop = el.scrollHeight - prevHeight.current;
    } else if (atBottom || prevLast.current === 0 || (list.length && (list[list.length - 1]!.local || list[list.length - 1]!.dir !== 'in'))) {
      el.scrollTop = el.scrollHeight;
    } else if (lastId !== prevLast.current) {
      setNewCount((n) => n + 1);
    }
    prevHeight.current = el.scrollHeight;
    prevLast.current = lastId;
  }, [list.length, lastId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const el = scroller.current;
    if (!el || typeof ResizeObserver === 'undefined') return;
    const ro = new ResizeObserver(() => {
      if (stick.current) el.scrollTop = el.scrollHeight;
    });
    ro.observe(inner.current!);
    return () => ro.disconnect();
  }, []);

  const onScroll = () => {
    const el = scroller.current!;
    const bottom = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
    stick.current = bottom;
    setAtBottom(bottom);
    if (bottom) {
      setNewCount(0);
      engine.markRead();
    }
    if (el.scrollTop < 120 && box?.more && !box.loading) {
      prevHeight.current = el.scrollHeight;
      void engine.loadOlder(id);
    }
  };

  const err = useCallback((x: Error) => ui.toast(x.message, 'err'), [ui]);
  const jump = useCallback(
    (mid: number) => {
      const node = inner.current?.querySelector(`[data-mid="${mid}"]`);
      if (!node) return ui.toast(L('Alıntılanan mesaj yukarıda, daha eski mesajları yükleyin.'));
      node.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setFlash(mid);
      setTimeout(() => setFlash(0), 1400);
    },
    [ui, L],
  );

  const msgMenu = useCallback(
    (m: FlxcMessage, anchor: HTMLElement, at?: { x: number; y: number }) => {
      const f = fmt(lang);
      const items: Parameters<typeof ui.menu>[1] = [];
      if (m.wa && m.dir !== 'note') {
        const cur = m.react?.out;
        items.push({
          node: (
            <div className="flxc-react-row">
              {REACTS.map((e) => (
                <button
                  key={e}
                  type="button"
                  className={cur === e ? 'is-on' : ''}
                  onClick={(ev) => {
                    ev.stopPropagation();
                    ui.closeMenu();
                    void engine.react(m, cur === e ? '' : e).catch(err);
                  }}
                >
                  {e}
                </button>
              ))}
              {cur && !REACTS.includes(cur) && (
                <button
                  type="button"
                  className="is-on"
                  onClick={(ev) => {
                    ev.stopPropagation();
                    ui.closeMenu();
                    void engine.react(m, '').catch(err);
                  }}
                >
                  {cur}
                </button>
              )}
              <button
                type="button"
                className="flxc-react-more"
                aria-label={L('Başka emoji')}
                title={L('Başka emoji')}
                onClick={(ev) => {
                  ev.stopPropagation();
                  ui.menu(
                    anchor,
                    [
                      {
                        node: (
                          <EmojiPicker
                            onPick={(e) => {
                              ui.closeMenu();
                              emoRecent(e);
                              void engine.react(m, e).catch(err);
                            }}
                          />
                        ),
                      },
                    ],
                    { cls: 'flxc-menu-emo' },
                  );
                }}
              >
                <I n="plus" />
              </button>
            </div>
          ),
        });
      }
      if (m.dir !== 'note' && m.wa) items.push({ icon: 'reply', label: L('Yanıtla'), fn: () => setQuote(m) });
      if (m.body) items.push({ icon: 'copy', label: L('Kopyala'), fn: () => void navigator.clipboard?.writeText(m.body).then(() => ui.toast(L('Kopyalandı'))) });
      if (m.status === 'failed') items.push({ icon: 'reopen', label: L('Tekrar gönder'), fn: () => void engine.retry(m).catch(err) });
      if (m.dir === 'note' && (m.userId === meId || boot?.me.manage)) items.push({ icon: 'trash', label: L('Notu sil'), danger: true, fn: () => void engine.deleteNote(m).catch(err) });
      if ((m.dir === 'in' || m.dir === 'out') && m.type !== 'deleted' && m.status !== 'pending') items.push({ icon: 'trash', label: L('Sil'), danger: true, fn: () => setDel(m) });
      items.push({ sep: true });
      items.push({ node: <div className="flxc-menu-info">{f.long(m.ts) + ' ' + f.time(m.ts) + (m.dir === 'out' ? ' · ' + statusLabel(m.status, L) : '')}</div> });
      ui.menu(anchor, items, { at: at ?? null });
    },
    [ui, engine, err, L, lang, meId, boot?.me.manage],
  );

  const actions: BubbleActions = useMemo(
    () => ({
      quote: (m) => setQuote(m),
      jump,
      menu: msgMenu,
      retry: (m) => void engine.retry(m).catch(err),
      toggleDeleted: (m) =>
        setDelOpen((s) => {
          const n = new Set(s);
          if (n.has(m.id)) n.delete(m.id);
          else n.add(m.id);
          return n;
        }),
      lightbox,
      openChatWith: onNew,
      linkPreview: (url) => engine.api.linkPreview(url).then((r) => (r.ok === false ? null : r), () => null),
      chatTitle: c?.title ?? '',
      meId,
    }),
    [engine, jump, msgMenu, err, lightbox, onNew, c?.title, meId],
  );

  if (!c) return null;
  const agentName = (aid: number) => boot?.agents.find((a) => a.id === aid)?.name;
  let sub: React.ReactNode;
  if (c.typing) sub = <span className="flxc-sub is-typing">{c.typing === 2 ? L('ses kaydediyor…') : L('yazıyor…')}</span>;
  else if (c.botBusy) sub = <span className="flxc-sub is-typing">{L('Asistan yazıyor…')}</span>;
  else {
    const bits: string[] = [];
    if (c.title !== c.phoneFmt && c.phoneFmt) bits.push(c.phoneFmt);
    if (c.assigned) bits.push(L('Atanan: {n}', { n: agentName(c.assigned) || '?' }));
    if (c.status === 'closed') bits.push(L('Kapalı'));
    if (c.optout) bits.push(L('Kara listede: mesaj gönderilmez'));
    if (conns.length > 1) {
      const cn = conns.find((x) => x.id === c.conn);
      if (cn) bits.push(cn.label);
    }
    sub = <span className="flxc-sub">{bits.join(' · ') || L('kişi bilgisi için dokunun')}</span>;
  }
  const patch = (p: Parameters<WaEngine['update']>[1], ok?: string) =>
    void engine.update(c.id, p).then(
      () => ok && ui.toast(ok),
      err,
    );
  const assignMenu = (anchor: HTMLElement) => {
    const items: Parameters<typeof ui.menu>[1] = (boot?.agents ?? []).map((a) => ({
      icon: 'person',
      label: a.id === meId ? L('{n} (ben)', { n: a.name }) : a.name,
      on: c.assigned === a.id,
      fn: () => patch({ assigned: a.id }),
    }));
    if (c.assigned) {
      items.push({ sep: true });
      items.push({ icon: 'close', label: L('Atamayı kaldır'), fn: () => patch({ assigned: 0 }) });
    }
    ui.menu(anchor, items);
  };
  const botTip = ({ on: L('Asistan bu sohbete otomatik cevap veriyor. Kapatmak için tıklayın.'), paused: L('Bir temsilci yazdığı için asistan bir süre susuyor. Hemen açmak için tıklayın.'), off: L('Bu sohbette asistan kapalı. Açmak için tıklayın.') } as Record<string, string>)[c.bot];
  const botLbl = ({ on: L('Asistan açık'), paused: L('Asistan beklemede'), off: L('Asistan kapalı') } as Record<string, string>)[c.bot];

  const rows: React.ReactNode[] = [];
  if (box?.note)
    rows.push(
      <div key="note" className="flxc-sys flxc-sys-warn">
        <I n="warn" />
        {L('Eski mesajlar WhatsApp hattından alınamadı: {e}', { e: box.note })}
      </div>,
    );
  if (box && !box.more && box.loaded)
    rows.push(
      <div key="lock" className="flxc-sys flxc-sys-lock">
        <I n="lock" />
        {L('Bu sohbetteki mesajlar yalnız yetkili temsilcilere görünür. Dahili notları müşteri görmez.')}
      </div>,
    );
  list.forEach((m, i) => {
    const p = list[i - 1];
    if (!p || !sameDayTs(p.ts, m.ts))
      rows.push(
        <div key={'d' + m.id} className="flxc-day">
          <span>{dayLabelL(m.ts, L, lang)}</span>
        </div>,
      );
    rows.push(<Bubble key={m.id} m={m} a={actions} first={!sameGroup(p, m)} open={delOpen.has(m.id)} flash={flash === m.id} />);
  });

  return (
    <>
      <header className="flxc-chat-head">
        <Ib n="back" title={L('Geri')} cls="flxc-back" onClick={() => engine.open(null)} />
        <button type="button" className="flxc-head-who" onClick={() => setInfo(!info)}>
          <Av c={c} />
          <span className="flxc-head-t">
            <strong>{c.title}</strong>
            {sub}
          </span>
        </button>
        <div className="flxc-head-actions">
          {boot?.bot && c.bot && !c.group && (
            <button
              type="button"
              className={'flxc-botpill is-' + c.bot}
              title={botTip}
              onClick={() =>
                void engine.setBot(c.id, c.bot !== 'on').then(
                  () => ui.toast(c.bot !== 'on' ? L('Asistan açıldı') : L('Asistan kapatıldı')),
                  err,
                )
              }
            >
              <I n="bot" />
              <span>{botLbl}</span>
            </button>
          )}
          <Ib n="assign" title={L('Ata')} cls={'flxc-hb-sec' + (c.assigned ? ' is-on' : '')} onClick={(e) => assignMenu(e.currentTarget)} />
          {c.status === 'closed' ? (
            <Ib n="reopen" title={L('Sohbeti yeniden aç')} cls="flxc-hb-sec" onClick={() => patch({ status: 'open' })} />
          ) : (
            <Ib n="done" title={L('Sohbeti kapat')} cls="flxc-hb-sec" onClick={() => patch({ status: 'closed' }, L('Sohbet kapatıldı'))} />
          )}
          <Ib n="more" title={L('Menü')} onClick={(e) => ui.menu(e.currentTarget, chatMenuItems(engine, c, L, meId, () => setInfo(true), ui.toast))} />
        </div>
      </header>
      <div className="flxc-msgs-wrap">
        <div className="flxc-msgs" tabIndex={-1} ref={scroller} onScroll={onScroll}>
          <div className="flxc-msgs-in" ref={inner}>
            {box?.loading && !list.length && <div className="flxc-sys"><span className="flxc-spin flxc-spin-sm" /></div>}
            {rows}
          </div>
        </div>
        <button
          type="button"
          className="flxc-down"
          hidden={atBottom}
          aria-label={L('En alta in')}
          onClick={() => scroller.current?.scrollTo({ top: scroller.current.scrollHeight, behavior: 'smooth' })}
        >
          <I n="down" />
          <span className="flxc-badge" hidden={!newCount}>
            {newCount}
          </span>
        </button>
      </div>
      <Composer
        engine={engine}
        chatId={c.id}
        chatTitle={c.title}
        quote={quote}
        clearQuote={() => setQuote(null)}
        replies={boot?.replies ?? []}
        ai={!!boot?.ai}
        chips={chips}
        repliesUrl={boot?.me.manage ? settingsUrl : ''}
        onSent={() => ((stick.current = true), setAtBottom(true))}
        inject={inject}
      />
      {del && <DeleteDialog m={del} engine={engine} meId={meId} manage={!!boot?.me.manage} onClose={() => setDel(null)} />}
    </>
  );
}

function DeleteDialog({ m, engine, meId, manage, onClose }: { m: FlxcMessage; engine: WaEngine; meId: number; manage: boolean; onClose: () => void }) {
  const { L } = useL();
  const ui = useUi();
  const [busy, setBusy] = useState(false);
  const now = Date.now();
  const mine = m.userId === meId || manage;
  const until = m.revokeUntil || 0;
  const canAll = m.dir === 'out' && until > now && mine;
  let why: string;
  if (m.dir === 'in') why = L('Gelen mesajı WhatsApp karşı taraftan silmeye izin vermez; mesaj kişinin telefonunda kalır, yalnız panelde gizlenir.');
  else if (!until) why = L('Bu kanalda herkesten silme desteklenmiyor; mesaj yalnız panelde gizlenir, kişinin telefonunda kalır.');
  else if (until <= now) why = L('Herkesten silme süresi doldu. Mesaj kişinin telefonunda kalır, yalnız panelde gizlenir.');
  else if (!mine) why = L('Bu mesajı yalnız gönderen ya da yönetici herkesten silebilir; siz panelde gizleyebilirsiniz.');
  else why = L('Herkesten silinirse mesaj kişinin telefonunda da "Bu mesaj silindi" olur. Kalan süre: yaklaşık {n} saat.', { n: Math.max(1, Math.round((until - now) / 3600000)) });
  const go = (scope: 'everyone' | 'panel') => {
    if (busy) return;
    setBusy(true);
    void engine.deleteMessage(m, scope).then(
      () => (onClose(), ui.toast(scope === 'everyone' ? L('Herkesten silindi') : L('Panelde gizlendi'))),
      (e: Error) => (setBusy(false), ui.toast(e.message, 'err')),
    );
  };
  return (
    <Modal title={L('Mesajı sil')} cls="flxc-exp flxc-del-dlg" onClose={onClose}>
      <p className={canAll ? 'flxc-help' : 'flxc-warn'}>{why}</p>
      <p className="flxc-help">{L('Silinen mesaj sunucuda saklanır: sohbette "Bu mesaj silindi" satırı görünür, ekip tıklayıp içeriği açabilir. Yapay zekâ silinen mesajı okumaz.')}</p>
      <div className="flxc-del-acts">
        {canAll && (
          <button type="button" className="flxc-btn flxc-btn-block flxc-btn-danger" disabled={busy} onClick={() => go('everyone')}>
            <I n="trash" />
            {L('Herkesten sil')}
          </button>
        )}
        <button type="button" className={'flxc-btn flxc-btn-block' + (canAll ? ' flxc-btn-ghost flxc-del-hide' : ' flxc-btn-danger')} disabled={busy} onClick={() => go('panel')}>
          <I n="block" />
          {L('Yalnız panelde gizle')}
        </button>
      </div>
    </Modal>
  );
}
