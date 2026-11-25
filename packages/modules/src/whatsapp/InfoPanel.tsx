// Kişi bilgisi paneli (klasik .flxc-info): ad, telefon, etiketler, atanan, kişi notları, eklenti bölümleri, sabitle/kapat.
import { useEffect, useRef, useState } from 'react';
import { useStore } from 'zustand';
import type { FlxcSection } from '@fluxwp/core';
import type { WaEngine } from './engine';
import { I } from './icons';
import { Av, Ib, fmt, useL, useUi } from './ui';

export function InfoPanel({ engine, id, onClose, onInsert, onLightbox }: { engine: WaEngine; id: number | null; onClose: () => void; onInsert: (s: string) => void; onLightbox: (src: string) => void }) {
  const { L, lang } = useL();
  const ui = useUi();
  const c = useStore(engine.store, (s) => (id ? s.chats[id] : undefined));
  const boot = useStore(engine.store, (s) => s.boot);
  const [sections, setSections] = useState<FlxcSection[]>([]);
  const [name, setName] = useState(c?.name ?? '');
  const [notes, setNotes] = useState(c?.notes ?? '');
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!id) return;
    let live = true;
    setSections([]);
    void engine.api.chat(id).then(
      (r) => {
        if (!live) return;
        engine.upsert(r.chat);
        setSections(r.sections);
        setName(r.chat.name);
        setNotes(r.chat.notes);
      },
      () => undefined,
    );
    return () => {
      live = false;
    };
  }, [engine, id]);
  useEffect(() => {
    setName(c?.name ?? '');
    setNotes(c?.notes ?? '');
  }, [id]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!c) return null;
  const patch = (p: Parameters<WaEngine['update']>[1], ok?: string) =>
    void engine.update(c.id, p).then(
      () => ok && ui.toast(ok),
      (e: Error) => ui.toast(e.message, 'err'),
    );
  const labels = boot?.labels ?? [];
  const agents = boot?.agents ?? [];
  const f = fmt(lang);

  return (
    <>
      <header className="flxc-info-head">
        <Ib n="close" title={L('Kapat')} onClick={onClose} />
        <strong>{L('Kişi bilgisi')}</strong>
      </header>
      <div className="flxc-info-body">
        <div className="flxc-info-hero">
          {c.avatar ? (
            <button type="button" className="flxc-info-av" title={L('Büyüt')} aria-label={L('Fotoğrafı büyüt')} onClick={() => onLightbox(c.avatar)}>
              <Av c={c} size="xl" />
            </button>
          ) : (
            <Av c={c} size="xl" />
          )}
          <label className="flxc-info-name">
            <input
              type="text"
              className="flxc-info-name-in"
              value={name}
              placeholder={c.pushName || c.phoneFmt}
              aria-label={L('Kişi adı')}
              onChange={(e) => setName(e.target.value)}
              onBlur={() => name.trim() !== c.name && patch({ name: name.trim() })}
              onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
            />
            <I n="edit" />
          </label>
          {c.pushName && c.pushName !== c.name && (c.channel || 'whatsapp') === 'whatsapp' && <small className="flxc-muted">{L('WhatsApp adı: {n}', { n: c.pushName })}</small>}
          <div className="flxc-info-phone">
            <span>{c.phoneFmt || c.jid}</span>
            {c.phone && (
              <Ib
                n="copy"
                title={L('Numarayı kopyala')}
                onClick={() =>
                  void navigator.clipboard?.writeText('+' + c.phone).then(
                    () => ui.toast(L('Kopyalandı')),
                    () => undefined,
                  )
                }
              />
            )}
            {c.phone && (
              <a className="flxc-ib" href={'tel:+' + c.phone} title={L('Ara')}>
                <I n="phone" />
              </a>
            )}
          </div>
        </div>
        <div className="flxc-info-sec">
          <h4>
            <I n="label" />
            {L('Etiketler')}
          </h4>
          <div className="flxc-info-labels">
            {labels.map((l) => {
              const on = c.labels.includes(l.id);
              return (
                <button
                  key={l.id}
                  type="button"
                  className={'flxc-chip flxc-chip-label' + (on ? ' is-on' : '')}
                  style={{ ['--lc' as string]: l.color }}
                  onClick={() => {
                    const ls = on ? c.labels.filter((x) => x !== l.id) : [...c.labels, l.id];
                    patch({ labels: ls.length ? ls : [''] });
                  }}
                >
                  <i className="flxc-dot" />
                  {l.name}
                </button>
              );
            })}
            {!labels.length && <small className="flxc-muted">{L('Etiketler Ayarlar sayfasından tanımlanır.')}</small>}
          </div>
        </div>
        <div className="flxc-info-sec">
          <h4>
            <I n="assign" />
            {L('Atanan temsilci')}
          </h4>
          <select className="flxc-field" value={c.assigned} onChange={(e) => patch({ assigned: +e.target.value })}>
            <option value="0">{L('— Atanmadı —')}</option>
            {agents.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flxc-info-sec">
          <h4>
            <I n="note" />
            {L('Kişi notları')}
          </h4>
          <textarea
            className="flxc-field"
            rows={4}
            value={notes}
            placeholder={L('Bu kişi hakkında yalnız temsilcilerin göreceği notlar')}
            onChange={(e) => {
              const v = e.target.value;
              setNotes(v);
              if (timer.current) clearTimeout(timer.current);
              timer.current = setTimeout(() => patch({ notes: v }, L('Not kaydedildi')), 900);
            }}
          />
        </div>
        <div>
          {sections.map((s) => (
            <div key={s.id} className="flxc-info-sec">
              <h4>
                {s.id === 'flxs' ? <I n="cert" /> : s.id === 'flxb' ? <I n="gift" /> : null}
                {s.title}
              </h4>
              {s.items.map((it, i) => (
                <div key={i} className="flxc-info-item">
                  <span>
                    <strong>{it.label}</strong>
                    {it.sub && <small>{it.sub}</small>}
                  </span>
                  {it.url && (
                    <a className="flxc-ib" href={it.url} target="_blank" rel="noopener" title={L('Aç')}>
                      <I n="open" />
                    </a>
                  )}
                  {it.insert && <Ib n="send" title={L('Mesaja ekle')} onClick={() => onInsert(it.insert!)} />}
                </div>
              ))}
            </div>
          ))}
        </div>
        <div className="flxc-info-sec flxc-info-actions">
          <button type="button" className="flxc-info-act" onClick={() => patch({ pinned: !c.pinned })}>
            <I n="pin" />
            {c.pinned ? L('Sabitlemeyi kaldır') : L('Sohbeti sabitle')}
          </button>
          {c.status === 'closed' ? (
            <button type="button" className="flxc-info-act" onClick={() => patch({ status: 'open' })}>
              <I n="reopen" />
              {L('Sohbeti yeniden aç')}
            </button>
          ) : (
            <button type="button" className="flxc-info-act is-danger" onClick={() => patch({ status: 'closed' })}>
              <I n="done" />
              {L('Sohbeti kapat')}
            </button>
          )}
        </div>
        <p className="flxc-help flxc-center">{L('İlk kayıt: {d}', { d: f.long(c.created) })}</p>
      </div>
    </>
  );
}
