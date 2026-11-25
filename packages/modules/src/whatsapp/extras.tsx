// Yeni sohbet penceresi, sekmeli emoji seçici, ses kaydı çubuğu (klasik Flux Chat işaretlemesi).
import { useEffect, useRef, useState } from 'react';
import type { WaEngine } from './engine';
import { I } from './icons';
import { Ib, Modal, fmtSecs, store, useL } from './ui';

/* ---------- yeni sohbet ---------- */

export function NewChat({ engine, prefill, prefName, onClose, onOpen, toast }: { engine: WaEngine; prefill?: string; prefName?: string; onClose: () => void; onOpen: (id: number) => void; toast: (m: string, k?: 'err') => void }) {
  const { L } = useL();
  const conns = engine.s.conns;
  const [phone, setPhone] = useState(prefill ?? '');
  const [name, setName] = useState(prefName ?? '');
  const [conn, setConn] = useState(conns[0]?.id ?? '');
  const [chk, setChk] = useState<Awaited<ReturnType<WaEngine['api']['checkNumber']>> | null>(null);
  const [checking, setChecking] = useState(false);
  const [busy, setBusy] = useState(false);
  const ref = useRef<HTMLInputElement>(null);
  useEffect(() => {
    setTimeout(() => ref.current?.focus(), 50);
  }, []);

  useEffect(() => {
    setChk(null);
    if (phone.replace(/\D+/g, '').length < 10) return;
    const h = setTimeout(() => {
      setChecking(true);
      void engine.api.checkNumber(phone, conns.length > 1 ? conn : '').then(
        (r) => (setChk(r), setChecking(false)),
        () => (setChk(null), setChecking(false)),
      );
    }, 450);
    return () => clearTimeout(h);
  }, [phone, conn, engine]); // eslint-disable-line react-hooks/exhaustive-deps

  const short = phone.replace(/\D+/g, '').length < 10;
  const disabled = busy || short || checking || (chk ? !chk.valid || chk.exists === false : false);
  const go = async () => {
    if (disabled) return;
    if (chk?.chat) return onOpen(chk.chat);
    setBusy(true);
    try {
      const r = await engine.api.newChat({ phone, name: name || undefined, conn: conns.length > 1 ? conn : '', check: true });
      engine.upsert(r.chat);
      onOpen(r.chat.id);
    } catch (e) {
      setBusy(false);
      toast((e as Error).message, 'err');
    }
  };
  const enter = (e: React.KeyboardEvent) => e.key === 'Enter' && void go();

  return (
    <Modal title={L('Yeni sohbet')} cls="flxc-nc" onClose={onClose}>
      <div className="flxc-nc-art"><I n="newchat" /></div>
      <label className="flxc-lbl">
        {L('Telefon numarası')}
        <input ref={ref} type="tel" className="flxc-field flxc-field-lg" placeholder={L('+90 5xx xxx xx xx')} autoComplete="tel" inputMode="tel" value={phone} onChange={(e) => setPhone(e.target.value)} onKeyDown={enter} />
      </label>
      <div className="flxc-nc-status">
        {checking && (
          <span className="flxc-muted">
            <span className="flxc-spin flxc-spin-sm" />
            {L('Kontrol ediliyor…')}
          </span>
        )}
        {chk && !checking && !chk.valid && <span className="is-err"><I n="warn" />{L('Geçersiz numara')}</span>}
        {chk && !checking && chk.valid && chk.exists === true && <span className="is-ok"><I n="check" />{L('{p} WhatsApp kullanıyor', { p: chk.phoneFmt ?? '' })}</span>}
        {chk && !checking && chk.valid && chk.exists === false && <span className="is-err"><I n="warn" />{L('{p} numarasında WhatsApp yok', { p: chk.phoneFmt ?? '' })}</span>}
        {chk && !checking && chk.valid && chk.exists == null && <span className="flxc-muted">{L('{p} — WhatsApp kontrol edilemedi, yine de deneyebilirsiniz.', { p: chk.phoneFmt ?? '' })}</span>}
        {chk && !checking && chk.chat && <span className="flxc-muted">{L('Bu kişiyle sohbet zaten var: {n}', { n: chk.name ?? '' })}</span>}
      </div>
      <label className="flxc-lbl">
        {L('Kişi adı')}
        <input type="text" className="flxc-field" placeholder={chk?.name || L('İsteğe bağlı')} value={name} onChange={(e) => setName(e.target.value)} onKeyDown={enter} />
      </label>
      {conns.length > 1 && (
        <label className="flxc-lbl">
          {L('Hangi hattan?')}
          <select className="flxc-field" value={conn} onChange={(e) => setConn(e.target.value)}>
            {conns.map((c) => (
              <option key={c.id} value={c.id}>
                {c.label}
              </option>
            ))}
          </select>
        </label>
      )}
      <button type="button" className="flxc-btn flxc-btn-block" disabled={disabled} onClick={() => void go()}>
        <I n="newchat" />
        {chk?.chat ? L('Sohbete git') : L('Sohbeti başlat')}
      </button>
      <p className="flxc-help">{L('Numarayı 0 ile, +90 ile ya da ülke koduyla yazabilirsiniz.')}</p>
    </Modal>
  );
}

/* ---------- emoji ---------- */

const EMOJI: Record<string, string> = {
  '🕒': '',
  '😀': '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 😉 😍 🥰 😘 😋 😜 🤗 🤭 🤔 🤐 😐 😏 😌 😴 😷 🤒 🥳 😎 🤓 😕 😟 😮 😲 😳 🥺 😢 😭 😱 😤 😡',
  '👍': '👍 👎 👌 ✌️ 🤞 🤝 🤲 🙏 👏 🙌 👋 💪 👀 👉 👈 👆 👇 ☝️ ✍️ ✋ 🤚 🖐️ 🫶 🫡 🤙 💁 🙋 🙆 🙅 🤷 🤦 🧕 👨‍👩‍👧 👶 🧓',
  '❤️': '❤️ 🧡 💛 💚 💙 💜 🤎 🖤 🤍 💔 ❤️‍🩹 💕 💞 💓 💗 💖 💝 💯 ✨ ⭐ 🌟 🔥 🎉 🎊 🎁 🎈 🏆 🥇 ✅ ☑️ ❌ ⚠️ ❓ ❗ ‼️ 📌 📍 🔔 🆗 🆕 ➡️ ⬅️ 🔝',
  '🌱': '🌱 🌿 ☘️ 🍀 🌳 🌲 🌴 🌵 🌾 🍃 🍂 🍁 🌸 🌼 🌻 🌷 🌹 💐 🌍 🌎 🏞️ ⛰️ ☀️ 🌤️ ⛅ 🌧️ ❄️ 🌈 💧 🌊 🌙 ⭐ 🕊️ 🐝 🦋 🐦 🐑 🐄 🍎 🍞 🫖 ☕ 🍲 🥘 🍉 🥗',
  '📞': '📞 ☎️ 📱 💬 🗨️ 📧 📨 📅 🗓️ 🕒 ⏰ ⏳ 📝 📄 📃 📑 📎 🔗 🖨️ 💳 💰 💵 💸 🏦 🧾 🏠 🏢 🕌 🏫 🏥 🚚 🚗 🚌 ✈️ 📦 🔒 🔑 💡 📢 📣 📷 🎥 🎤 🔍 ⚙️ 🛠️',
};
const EMO_KEYS = Object.keys(EMOJI);
const emoRecentList = () => (store('emo_recent') || '').split(' ').filter(Boolean);
export function emoRecent(e: string) {
  const l = emoRecentList().filter((x) => x !== e);
  l.unshift(e);
  store('emo_recent', l.slice(0, 24).join(' '));
}

export function EmojiPicker({ onPick }: { onPick: (e: string) => void }) {
  const { L } = useL();
  const [cat, setCat] = useState(() => (emoRecentList().length ? '🕒' : EMO_KEYS[1]!));
  const list = cat === '🕒' ? emoRecentList() : EMOJI[cat]!.split(' ');
  return (
    <div className="flxc-emo">
      <div className="flxc-emo-tabs">
        {EMO_KEYS.map((k) => (
          <button
            key={k}
            type="button"
            className={k === cat ? 'is-on' : ''}
            title={k === '🕒' ? L('Son kullanılanlar') : ''}
            onClick={(ev) => {
              ev.stopPropagation();
              setCat(k);
            }}
          >
            {k}
          </button>
        ))}
      </div>
      <div className="flxc-emo-grid">
        {!list.length && <p className="flxc-emo-empty">{L('Son kullanılan emoji yok.')}</p>}
        {list.map((e) => (
          <button
            key={e}
            type="button"
            onClick={(ev) => {
              ev.stopPropagation();
              onPick(e);
            }}
          >
            {e}
          </button>
        ))}
      </div>
    </div>
  );
}

/* ---------- ses kaydı ---------- */

function pickMime(): string {
  const list = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
  return typeof MediaRecorder !== 'undefined' ? (list.find((m) => MediaRecorder.isTypeSupported(m)) ?? '') : '';
}

export const canRecord = () => typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined';

/** Kayıt çubuğu; açılınca kayda başlar. Gönder → onDone(blob, ad). 0.7 sn'den kısa kayıt atılır. */
export function Recorder({ onDone, onCancel, onTick, onError }: { onDone: (b: Blob, name: string) => void; onCancel: () => void; onTick: () => void; onError: (m: string) => void }) {
  const { L } = useL();
  const [secs, setSecs] = useState(0);
  const rec = useRef<MediaRecorder | null>(null);
  const chunks = useRef<Blob[]>([]);
  const started = useRef(0);
  const cancelled = useRef(false);

  useEffect(() => {
    let stream: MediaStream | null = null;
    let iv: ReturnType<typeof setInterval>;
    void navigator.mediaDevices.getUserMedia({ audio: true }).then(
      (s) => {
        stream = s;
        const mime = pickMime();
        const r = new MediaRecorder(s, mime ? { mimeType: mime } : undefined);
        r.ondataavailable = (e) => e.data.size && chunks.current.push(e.data);
        r.onstop = () => {
          s.getTracks().forEach((tr) => tr.stop());
          const len = (Date.now() - started.current) / 1000;
          if (cancelled.current || len < 0.7) return onCancel();
          const type = r.mimeType || mime || 'audio/webm';
          const ext = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'm4a' : 'webm';
          onDone(new Blob(chunks.current, { type }), `ses-${Date.now()}.${ext}`);
        };
        rec.current = r;
        started.current = Date.now();
        r.start(250);
        iv = setInterval(() => {
          setSecs((Date.now() - started.current) / 1000);
          onTick();
        }, 250);
      },
      () => (onError(L('Mikrofon izni verilmedi.')), onCancel()),
    );
    return () => {
      clearInterval(iv);
      if (rec.current?.state === 'recording') {
        cancelled.current = true;
        rec.current.stop();
      }
      stream?.getTracks().forEach((tr) => tr.stop());
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="flxc-rec">
      <Ib n="trash" title={L('Kaydı sil')} cls="flxc-rec-del" onClick={() => ((cancelled.current = true), rec.current ? rec.current.stop() : onCancel())} />
      <span className="flxc-rec-dot" />
      <span className="flxc-rec-t">{fmtSecs(secs)}</span>
      <span className="flxc-rec-l">{L('Kaydediliyor…')}</span>
      <button type="button" className="flxc-send" aria-label={L('Gönder')} onClick={() => rec.current?.stop()}>
        <I n="send" />
      </button>
    </div>
  );
}
