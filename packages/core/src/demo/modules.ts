// Demo modül verisi (sunucu cevabı taklidi, bellek içi; sayfa yenilenince sıfırlanır).
import type { PendingDonation } from '../modules/bagis';
import type { Task, TaskDetail, TaskProject } from '../modules/gorev';
import type { Chat, Message } from '../modules/cagri';

const ago = (min: number) => new Date(Date.now() - min * 60_000).toISOString();
const img = (fill: string, label: string) =>
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="820"><rect width="600" height="820" fill="#fff"/><rect x="40" y="40" width="520" height="90" rx="10" fill="${fill}"/><text x="60" y="98" font-family="Arial" font-size="34" fill="#fff">${label}</text>` +
      Array.from({ length: 9 }, (_, i) => `<rect x="60" y="${190 + i * 60}" width="${300 + ((i * 53) % 180)}" height="18" rx="9" fill="#e3e6eb"/>`).join('') +
      `</svg>`,
  );

export const pending: PendingDonation[] = [
  {
    id: 101, order_id: 5012, title: 'Fidan bağışı', amount: 750, amount_label: '750,00 ₺', at: ago(60 * 26), date_label: '31 Ekim 2026 13:10', waiting_label: '1 gündür',
    donor: { name: 'Ayşe Kaya', phone: '+90 532 000 00 01', email: 'ayse@example.org' }, ref: null,
    lines: [{ title: 'Fidan bağışı', quantity: 5, amount_label: '750,00 ₺' }],
    receipt: { url: img('#1D7932', 'DEKONT · 750 TL'), type: 'image', name: 'dekont.jpg', at_label: 'WhatsApp · 31 Ekim 14:02' }, note: 'Annemin adına', web_url: '',
  },
  {
    id: 102, order_id: 5019, title: 'Hatıra ormanı', amount: 1500, amount_label: '1.500,00 ₺', at: ago(180), date_label: '1 Kasım 2026 12:40', waiting_label: '3 saattir',
    donor: { name: 'Mehmet Tan', phone: '+90 533 000 00 02', email: '' }, ref: { name: 'Okul kampanyası' },
    lines: [{ title: 'Hatıra ormanı', quantity: 10, amount_label: '1.500,00 ₺' }],
    receipt: { url: img('#5B7CFA', 'EFT · 1.500 TL'), type: 'image', name: 'eft.png', at_label: 'E-posta · 1 Kasım 13:05', warn: 'Tutar farklı olabilir' }, note: '', web_url: '',
  },
  {
    id: 103, order_id: 5021, title: 'Fidan bağışı', amount: 150, amount_label: '150,00 ₺', at: ago(40), date_label: '1 Kasım 2026 15:20', waiting_label: '40 dakikadır',
    donor: { name: 'Zeynep Arslan', phone: '', email: 'zeynep@example.org' }, ref: null,
    lines: [{ title: 'Fidan bağışı', quantity: 1, amount_label: '150,00 ₺' }],
    receipt: null, note: '', web_url: '',
  },
];

export const projects: TaskProject[] = [
  { id: 1, title: 'Etkinlik', color: '#5B7CFA', open: 3, lists: [{ id: 11, title: 'Yapılacak', status: 'open' }, { id: 12, title: 'Bitti', status: 'done' }] },
  { id: 2, title: 'Sertifika', color: '#22C3A6', open: 2, lists: [{ id: 21, title: 'Yapılacak', status: 'open' }] },
];

const me = { id: 1, name: 'Demo Kullanıcı' };
const t = (id: number, title: string, p: number, due: string, dueLabel: string, extra: Partial<Task> = {}): Task => ({
  id, title, status: 'open', project: { id: p, title: projects[p - 1]!.title, color: projects[p - 1]!.color }, list: { id: p * 10 + 1, title: 'Yapılacak' },
  due, due_label: dueLabel, overdue: false, priority: 1, assignees: [me], comments: 0, checklist: { done: 0, total: 0 }, url: '', ...extra,
});

export const tasks: Task[] = [
  t(201, '11 Kasım etkinlik afişi', 1, '2026-11-01', 'Bugün', { priority: 3, comments: 2, checklist: { done: 1, total: 3 } }),
  t(202, 'Katılımcı listesini kesinleştir', 1, '2026-10-30', '30 Ekim', { overdue: true, priority: 2 }),
  t(203, 'Sertifika şablonu kontrolü', 2, '2026-11-02', 'Yarın', { comments: 1 }),
  t(204, 'Fidan dikim alanı izni', 1, '2026-11-06', '6 Kasım', { assignees: [me, { id: 2, name: 'Elif Yıldız' }] }),
  t(205, 'Bağışçılara teşekkür e-postası', 2, '', '', { priority: 0 }),
];

export const taskDetail = (id: number): TaskDetail | null => {
  const x = tasks.find((k) => k.id === id);
  if (!x) return null;
  return {
    ...x,
    description_html: '<p>Afiş A3 ve Instagram ölçüsünde hazırlanacak. Logo ve şube imzası alt kısımda.</p>',
    checklist: [
      { id: 1, text: 'Metin onayı', done: true },
      { id: 2, text: 'Görsel taslak', done: false },
      { id: 3, text: 'Baskıya gönder', done: false },
    ].slice(0, x.checklist.total || 0),
    comments: x.comments
      ? [{ id: 1, author: { id: 2, name: 'Elif Yıldız' }, text: 'Renkleri yeşil tonlarda tutalım.', at: ago(90), at_label: '1 sa önce' }]
      : [],
  };
};

export const chats: Chat[] = [
  { id: 301, name: 'Ayşe Kaya', phone: '+90 532 000 00 01', avatar: '', unread: 2, last: { text: 'Dekontu gönderdim, sertifika ne zaman gelir?', at: ago(4), at_label: '4 dk', dir: 'in', by: 'customer' }, bot: { on: true }, tags: ['bağışçı'], assigned: null, blocked: false },
  { id: 302, name: 'Mehmet Tan', phone: '+90 533 000 00 02', avatar: '', unread: 0, last: { text: 'Teşekkürler, sertifikanız e-postanıza gönderildi.', at: ago(55), at_label: '55 dk', dir: 'out', by: 'bot' }, bot: { on: true }, tags: [], assigned: { id: 1, name: 'Demo Kullanıcı' }, blocked: false },
  { id: 303, name: '+90 535 000 00 03', phone: '+90 535 000 00 03', avatar: '', unread: 1, last: { text: 'Gönüllü olmak istiyorum', at: ago(130), at_label: '2 sa', dir: 'in', by: 'customer' }, bot: { on: false }, tags: ['gönüllü'], assigned: null, blocked: false },
];

export const messages: Record<number, Message[]> = {
  301: [
    { id: 1, dir: 'in', by: 'customer', author: 'Ayşe Kaya', text: 'Merhaba, annem adına 5 fidan bağışı yaptım.', media: null, at: ago(30), at_label: '15:01', status: 'read' },
    { id: 2, dir: 'out', by: 'bot', author: 'Asistan', text: 'Merhaba Ayşe Hanım, bağışınız için teşekkür ederiz. Havale/EFT dekontunu buradan gönderebilirsiniz.', media: null, at: ago(29), at_label: '15:02', status: 'read' },
    { id: 3, dir: 'in', by: 'customer', author: 'Ayşe Kaya', text: '', media: { type: 'image', url: img('#1D7932', 'DEKONT · 750 TL'), name: 'dekont.jpg' }, at: ago(6), at_label: '15:25', status: 'read' },
    { id: 4, dir: 'in', by: 'customer', author: 'Ayşe Kaya', text: 'Dekontu gönderdim, sertifika ne zaman gelir?', media: null, at: ago(4), at_label: '15:27', status: 'delivered' },
  ],
  302: [
    { id: 5, dir: 'in', by: 'customer', author: 'Mehmet Tan', text: 'Sertifikam gelmedi', media: null, at: ago(60), at_label: '14:30', status: 'read' },
    { id: 6, dir: 'out', by: 'bot', author: 'Asistan', text: 'Teşekkürler, sertifikanız e-postanıza gönderildi.', media: null, at: ago(55), at_label: '14:35', status: 'read' },
  ],
  303: [{ id: 7, dir: 'in', by: 'customer', author: '', text: 'Gönüllü olmak istiyorum', media: null, at: ago(130), at_label: '13:20', status: 'delivered' }],
};
