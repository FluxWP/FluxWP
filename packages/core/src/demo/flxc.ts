// Demo: Flux Chat tam REST (flx-chat/v1). Gerçek sunucunun cevap biçimleriyle aynı, durum bellekte.
import type { FlxcBootstrap, FlxcChat, FlxcMessage } from '../modules/flxc';
import { DEMO_ORIGIN } from './origin';

const now = Date.now();
const min = 60_000;
let rev = now;
const nextRev = () => (rev = Math.max(rev + 1, Date.now()));
let seq = 5000;

const base = (c: Partial<FlxcChat> & Pick<FlxcChat, 'id' | 'title' | 'phone'>): FlxcChat => ({
  conn: 'ana', jid: c.phone + '@s.whatsapp.net', phoneFmt: '+' + c.phone.replace(/^(\d{2})(\d{3})(\d{3})(\d{2})(\d{2})$/, '$1 $2 $3 $4 $5'),
  name: c.title, pushName: c.title, avatar: '', avatarAt: 0, labels: [], notes: '', status: 'open', assigned: 0, unread: 0, pinned: 0,
  lastTs: now, lastInTs: now, group: false, typing: 0, rev: now, created: now - 30 * 86_400_000, last: null, unknownName: false, meta: {},
  bot: 'on', botBusy: 0, ai: 0, optout: 0, ...c,
});

export const flxcChats: FlxcChat[] = [
  base({ id: 301, title: 'Ayşe Kaya', phone: '905320000001', labels: ['bagis'], unread: 2, assigned: 1, pinned: 1 }),
  base({ id: 302, title: 'Mehmet Tan', phone: '905330000002', labels: ['sertifika'], bot: 'off' }),
  base({ id: 303, title: '+90 535 000 00 03', name: '', phone: '905350000003', unread: 1, unknownName: true }),
  base({ id: 304, title: 'Zeynep Arslan', phone: '905360000004', labels: ['gonullu'], bot: 'paused' }),
  base({ id: 305, title: 'Burak Demir', phone: '905370000005', status: 'closed', bot: 'off' }),
  base({ id: 306, title: 'Site ziyaretçisi', name: '', phone: '', conn: 'web', jid: 'web-306', phoneFmt: 'Site sohbeti', channel: 'web', unread: 1, bot: 'off' }),
];
for (const c of flxcChats) c.channel ??= 'whatsapp';

const msg = (chat: number, m: Partial<FlxcMessage> & Pick<FlxcMessage, 'dir' | 'ts'>): FlxcMessage => ({
  id: ++seq, chat, wa: 'WA' + seq, type: 'text', body: '', status: m.dir === 'out' ? 'read' : '', error: '', user: '', userId: 0,
  source: m.dir === 'out' ? 'agent' : '', rev: now, react: {}, edited: false, sender: '', ...m,
});
const media = (id: number, mime: string, name: string, extra: Partial<NonNullable<FlxcMessage['media']>> = {}) => ({
  url: `${DEMO_ORIGIN}/wp-json/flx-chat/v1/media/${id}?_wpnonce=demo`, mime, name, size: 48_000, ready: true, ptt: false, secs: 0, w: 0, h: 0, thumb: '', ...extra,
});

const day = 86_400_000;
export const flxcMessages: Record<number, FlxcMessage[]> = { 301: [], 302: [], 303: [], 304: [], 305: [], 306: [] };
{
  const a = flxcMessages[301]!;
  a.push(msg(301, { dir: 'in', ts: now - day - 40 * min, body: 'Merhaba, geçen ay *5 fidan* bağışı yapmıştım.' }));
  a.push(msg(301, { dir: 'out', ts: now - day - 38 * min, body: 'Merhaba Ayşe Hanım, kaydınızı gördüm 🌱 Sertifikanız e-postanıza gönderildi.', user: 'Demo Kullanıcı', userId: 1, react: { in: '🙏' } }));
  a.push(msg(301, { dir: 'system', ts: now - day - 37 * min, body: 'Demo Kullanıcı sohbeti kendine atadı' }));
  a.push(msg(301, { dir: 'in', ts: now - 32 * min, body: 'Bu sefer annem adına 5 fidan daha bağışladım.' }));
  a.push(msg(301, { dir: 'out', ts: now - 31 * min, body: 'Bağışınız için teşekkür ederiz! Havale/EFT dekontunu buradan gönderebilirsiniz.\nIBAN: TR00 0000 0000 0000 0000 0000 00', source: 'bot' }));
  const rc = msg(301, { dir: 'in', ts: now - 6 * min, type: 'image', body: '' });
  rc.media = media(rc.id, 'image/svg+xml', 'dekont.png', { w: 300, h: 420 });
  rc.rcheck = { v: 'ok', s: 'Dekont tutarı 750 TL, alıcı doğru' };
  a.push(rc);
  a.push(msg(301, { dir: 'note', ts: now - 5 * min, body: 'Dekont tutarı doğru, muhasebe onayı bekleniyor.', user: 'Demo Kullanıcı', userId: 1 }));
  const v = msg(301, { dir: 'in', ts: now - 4 * min, type: 'audio' });
  v.media = media(v.id, 'audio/wav', 'ses.wav', { ptt: true, secs: 3 });
  a.push(v);
  a.push(msg(301, { dir: 'in', ts: now - 4 * min + 20_000, body: 'Sertifika ne zaman gelir? https://example.org/sertifika', lp: { url: 'https://example.org/sertifika', title: 'Sertifika sorgulama', desc: 'Bağış sertifikanızı numarasıyla doğrulayın.', domain: 'example.org' } }));
  const q = a[a.length - 1]!;
  const ans = msg(301, { dir: 'out', ts: now - 3 * min, body: 'Onaylandığı anda WhatsApp ve e-postayla iletilecek 👍', user: 'Demo Kullanıcı', userId: 1, status: 'delivered' });
  ans.quoted = { id: q.id, dir: 'in', type: 'text', body: 'Sertifika ne zaman gelir?', user: '' };
  ans.revokeUntil = ans.ts + 60 * 60 * min;
  a.push(ans);

  const b = flxcMessages[302]!;
  b.push(msg(302, { dir: 'in', ts: now - 2 * day, body: 'Sertifikamı yazdırmak istiyorum, PDF olarak alabilir miyim?' }));
  const d = msg(302, { dir: 'out', ts: now - 2 * day + 5 * min, type: 'document', body: '', user: 'Demo Kullanıcı', userId: 1 });
  d.media = media(d.id, 'application/pdf', 'FLX-2026-00412.pdf', { size: 182_000 });
  b.push(d);
  const gone = msg(302, { dir: 'in', ts: now - 58 * min, body: 'Yanlış dosyayı gönderdim, kusura bakmayın.' });
  gone.deleted = true;
  gone.del = { scope: 'contact', by: '', at: now - 57 * min, atText: '', ago: '57 dk önce' };
  b.push(gone);
  b.push(msg(302, { dir: 'in', ts: now - 55 * min, body: 'Teşekkürler 🙏🙏' }));

  flxcMessages[303]!.push(msg(303, { dir: 'in', ts: now - 2 * 60 * min, body: 'Merhaba, _gönüllü_ olmak istiyorum. Ne yapmam gerekiyor?' }));
  const z = flxcMessages[304]!;
  z.push(msg(304, { dir: 'in', ts: now - 3 * day, type: 'location', body: '', loc: { lat: 41.0082, lng: 28.9784, name: 'Fidan dikim alanı' } }));
  z.push(msg(304, { dir: 'out', ts: now - 3 * day + 2 * min, body: 'Konumu aldık, ~cumartesi~ pazar sabah 9:00\'da görüşmek üzere.', user: 'Demo Kullanıcı', userId: 1 }));
  flxcMessages[306]!.push(msg(306, { dir: 'in', ts: now - 9 * min, body: 'Merhaba, kurumsal bağış için kiminle görüşebilirim?' }));
  flxcMessages[305]!.push(msg(305, { dir: 'in', ts: now - 6 * day, body: 'Tamamdır, teşekkürler.' }));
}

for (const c of flxcChats) {
  const list = flxcMessages[c.id]!;
  const last = [...list].reverse().find((m) => m.dir !== 'system');
  const lastIn = [...list].reverse().find((m) => m.dir === 'in');
  if (last) {
    c.lastTs = last.ts;
    c.last = { dir: last.dir, type: last.type, body: last.body || ({ image: 'Fotoğraf', audio: 'Sesli mesaj', document: last.media?.name ?? 'Belge', location: 'Konum' } as Record<string, string>)[last.type] || '', status: last.status, user: last.user };
  }
  if (lastIn) c.lastInTs = lastIn.ts;
}

export const flxcBoot = (): FlxcBootstrap => ({
  me: { id: 1, name: 'Demo Kullanıcı', manage: true },
  agents: [{ id: 1, name: 'Demo Kullanıcı' }, { id: 2, name: 'Elif Yıldız' }],
  labels: [
    { id: 'bagis', name: 'Bağış', color: '#1D7932' },
    { id: 'sertifika', name: 'Sertifika', color: '#5B7CFA' },
    { id: 'gonullu', name: 'Gönüllü', color: '#E3A008' },
  ],
  connections: [{ id: 'ana', label: 'Ana hat', color: '#22C3A6', state: 'open', at: now }],
  channels: [
    { id: 'whatsapp', label: 'WhatsApp', icon: '', color: '#25d366', active: true },
    { id: 'web', label: 'Site sohbeti', icon: '', color: '#5b8def', active: true },
  ],
  replies: [
    { id: 1, shortcut: 'iban', title: 'IBAN', body: 'IBAN: TR00 0000 0000 0000 0000 0000 00 (Örnek Site)', category: 'Bağış', uses: 12 },
    { id: 2, shortcut: 'tesekkur', title: 'Teşekkür', body: 'Merhaba {first_name}, desteğiniz için çok teşekkür ederiz 🌱', category: 'Genel', uses: 30 },
    { id: 3, shortcut: 'sertifika', title: 'Sertifika süresi', body: 'Sertifikanız ödeme onayından sonra 1 iş günü içinde gönderilir.', category: 'Sertifika', uses: 7 },
  ],
  unread: flxcChats.reduce((n, c) => n + (c.status !== 'closed' ? c.unread : 0), 0),
  ai: { id: 'flxai', label: 'Flux AI', auto: false },
  bot: true,
  records: {},
  rev,
});

const touch = (c: FlxcChat) => (c.rev = nextRev());

export function flxcChatsQuery(q: URLSearchParams) {
  const f = q.get('filter') || 'all';
  const s = (q.get('q') ?? '').toLocaleLowerCase('tr');
  const label = q.get('label') ?? '';
  const channel = q.get('channel') ?? '';
  let list = flxcChats.slice();
  if (f === 'unread') list = list.filter((c) => c.unread > 0 && c.status !== 'closed');
  else if (f === 'mine') list = list.filter((c) => c.assigned === 1 && c.status !== 'closed');
  else if (f === 'unassigned') list = list.filter((c) => !c.assigned && c.status !== 'closed');
  else if (f === 'closed') list = list.filter((c) => c.status === 'closed');
  else if (f !== 'any') list = list.filter((c) => c.status !== 'closed');
  if (label) list = list.filter((c) => c.labels.includes(label));
  if (channel) list = list.filter((c) => (c.channel || 'whatsapp') === channel);
  if (s) list = list.filter((c) => (c.title + c.phone + c.notes + (flxcMessages[c.id] ?? []).map((m) => m.body).join(' ')).toLocaleLowerCase('tr').includes(s));
  list.sort((a, b) => b.pinned - a.pinned || b.lastTs - a.lastTs);
  return { chats: list };
}

export function flxcSync(q: URLSearchParams) {
  const since = Number(q.get('since') || 0);
  const chat = Number(q.get('chat') || 0);
  const after = Number(q.get('after') || 0);
  return {
    rev: nextRev(),
    chats: since ? flxcChats.filter((c) => c.rev > since) : [],
    messages: since && chat ? (flxcMessages[chat] ?? []).filter((m) => m.rev > since || (after && m.id > after)) : [],
    incoming: [],
    connections: flxcBoot().connections,
    unread: flxcChats.reduce((n, c) => n + (c.status !== 'closed' ? c.unread : 0), 0),
    nonce: 'demo',
  };
}

export function flxcSend(id: number, b: Record<string, unknown>) {
  const c = flxcChats.find((x) => x.id === id);
  if (!c) return null;
  const type = String(b.type || 'text') as FlxcMessage['type'];
  const m = msg(id, { dir: type === 'note' ? 'note' : 'out', ts: Date.now(), type, body: String(b.body ?? ''), status: type === 'note' ? '' : 'sent', user: 'Demo Kullanıcı', userId: 1, rev: nextRev() });
  if (['image', 'video', 'audio', 'document'].includes(type)) {
    m.media = media(m.id, type === 'audio' ? 'audio/wav' : 'application/octet-stream', String(b.name ?? ''), { ptt: type === 'audio', secs: 3 });
  }
  if (b.quoted) {
    const q = flxcMessages[id]?.find((x) => x.id === Number(b.quoted));
    if (q) m.quoted = { id: q.id, dir: q.dir, type: q.type, body: q.body, user: q.user };
  }
  (flxcMessages[id] ??= []).push(m);
  if (type !== 'note') {
    c.lastTs = m.ts;
    c.last = { dir: m.dir, type: m.type, body: m.body, status: m.status, user: m.user };
    if (!c.assigned) c.assigned = 1;
  }
  touch(c);
  return { message: m };
}

export function flxcUpdate(id: number, b: Record<string, unknown>) {
  const c = flxcChats.find((x) => x.id === id);
  if (!c) return null;
  if (b.pinned !== undefined) c.pinned = b.pinned ? 1 : 0;
  if (b.status) {
    c.status = b.status as FlxcChat['status'];
    flxcMessages[id]!.push(msg(id, { dir: 'system', ts: Date.now(), body: c.status === 'closed' ? 'Demo Kullanıcı sohbeti kapattı' : 'Demo Kullanıcı sohbeti yeniden açtı', rev: nextRev() }));
  }
  if (b.assigned !== undefined) c.assigned = Number(b.assigned);
  if (Array.isArray(b.labels)) c.labels = b.labels as string[];
  if (b.notes !== undefined) c.notes = String(b.notes);
  touch(c);
  return { chat: c };
}

export function flxcSetBot(id: number, on: boolean) {
  const c = flxcChats.find((x) => x.id === id);
  if (!c) return null;
  c.bot = on ? 'on' : 'off';
  flxcMessages[id]!.push(msg(id, { dir: 'system', ts: Date.now(), body: on ? 'Asistan açıldı (Demo Kullanıcı)' : 'Asistan kapatıldı (Demo Kullanıcı)', rev: nextRev() }));
  touch(c);
  return { chat: c };
}

export function flxcReact(mid: number, emoji: string) {
  for (const list of Object.values(flxcMessages)) {
    const m = list.find((x) => x.id === mid);
    if (m) {
      m.react = { ...m.react, out: emoji };
      m.rev = nextRev();
      return { message: m };
    }
  }
  return null;
}

/** 1.38.0: yumuşak silme; içerik saklanır, `del` ile işaretlenir. */
export function flxcDelete(mid: number, scope: string) {
  for (const list of Object.values(flxcMessages)) {
    const m = list.find((x) => x.id === mid);
    if (m) {
      m.deleted = true;
      m.del = { scope: m.dir === 'note' || scope !== 'everyone' ? 'panel' : 'everyone', by: 'Demo Kullanıcı', at: Date.now(), atText: '', ago: 'şimdi' };
      delete m.revokeUntil;
      m.rev = nextRev();
      return { message: m };
    }
  }
  return null;
}

export function flxcRead(id: number) {
  const c = flxcChats.find((x) => x.id === id);
  if (c) {
    c.unread = 0;
    touch(c);
  }
  return { ok: true };
}

/* ---------- medya ---------- */

const receiptSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="300" height="420" viewBox="0 0 300 420"><rect width="300" height="420" fill="#fff"/><rect x="20" y="20" width="260" height="44" rx="4" fill="#1D7932"/><text x="34" y="49" font-family="Arial" font-size="18" fill="#fff">DEKONT · 750 TL</text>${Array.from({ length: 9 }, (_, i) => `<rect x="34" y="${96 + i * 30}" width="${150 + ((i * 37) % 90)}" height="8" rx="4" fill="#e5e7eb"/>`).join('')}</svg>`;

function toneWav(secs = 3, rate = 8000): Uint8Array {
  const n = secs * rate;
  const buf = new ArrayBuffer(44 + n);
  const v = new DataView(buf);
  const w = (o: number, s: string) => [...s].forEach((ch, i) => v.setUint8(o + i, ch.charCodeAt(0)));
  w(0, 'RIFF'); v.setUint32(4, 36 + n, true); w(8, 'WAVE'); w(12, 'fmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
  v.setUint32(24, rate, true); v.setUint32(28, rate, true); v.setUint16(32, 1, true); v.setUint16(34, 8, true); w(36, 'data'); v.setUint32(40, n, true);
  for (let i = 0; i < n; i++) v.setUint8(44 + i, 128 + Math.round(20 * Math.sin((i / rate) * 2 * Math.PI * 330) * Math.sin((i / n) * Math.PI)));
  return new Uint8Array(buf);
}

export function flxcMedia(mid: number): Response {
  for (const list of Object.values(flxcMessages)) {
    const m = list.find((x) => x.id === mid);
    if (!m?.media) continue;
    if (m.type === 'image') return new Response(receiptSvg, { headers: { 'Content-Type': 'image/svg+xml' } });
    if (m.type === 'audio') return new Response(toneWav() as BodyInit, { headers: { 'Content-Type': 'audio/wav' } });
    return new Response('Demo belge', { headers: { 'Content-Type': 'text/plain' } });
  }
  return new Response('', { status: 404 });
}

const norm = (p: string) => {
  let d = p.replace(/\D+/g, '');
  if (d.startsWith('0')) d = '90' + d.slice(1);
  if (d.length === 10) d = '90' + d;
  return d.length >= 11 && d.length <= 15 ? d : '';
};

export function flxcCheck(phone: string) {
  const d = norm(phone);
  if (!d) return { valid: false };
  const found = flxcChats.find((c) => c.phone === d);
  return { valid: true, phone: d, phoneFmt: '+' + d, exists: !d.endsWith('0000'), chat: found?.id ?? 0, name: found?.title ?? '' };
}

export function flxcNewChat(phone: string, name: string) {
  const d = norm(phone);
  if (!d) return null;
  let c = flxcChats.find((x) => x.phone === d);
  if (!c) {
    c = base({ id: ++seq, title: name || '+' + d, name, phone: d, lastTs: Date.now(), bot: 'off', rev: nextRev() });
    flxcChats.push(c);
    flxcMessages[c.id] = [];
  }
  return { chat: c };
}
