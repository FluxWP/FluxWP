// Demo server data (stand-in for a real site's REST answers; server text, not UI strings).
const now = Date.now();
const ago = (min: number) => new Date(now - min * 60_000).toISOString().replace(/\.\d+Z$/, '');

const people: [string, string, string, string, number, number][] = [
  ['Ayşe Kaya', 'ayse@example.org', '+90 532 000 00 01', 'donor', 4350, 12],
  ['Mehmet Tan', 'mehmet@example.org', '+90 533 000 00 02', 'donor', 1500, 85],
  ['Zeynep Arslan', 'zeynep@example.org', '', 'volunteer', 0, 20],
  ['Can Demir', '', '+90 535 000 00 04', 'customer', 620, 40],
  ['Elif Yıldız', 'elif@example.org', '+90 536 000 00 05', 'donor', 9800, 5],
  ['Burak Şahin', 'burak@example.org', '', 'customer', 240, 72],
  ['Selin Aksoy', 'selin@example.org', '+90 537 000 00 07', 'donor', 300, 30],
  ['Emre Koç', 'emre@example.org', '+90 538 000 00 08', 'volunteer', 0, 10],
  ['Deniz Öztürk', 'deniz@example.org', '', 'donor', 2250, 55],
  ['Gizem Polat', 'gizem@example.org', '+90 539 000 00 10', 'customer', 1180, 15],
  ['Kerem Aydın', 'kerem@example.org', '+90 530 000 00 11', 'donor', 750, 64],
  ['Nazlı Erdem', 'nazli@example.org', '', 'donor', 150, 8],
];

const roleLabel: Record<string, string> = { donor: 'Kişi', volunteer: 'Editör', customer: 'Müşteri' };
const segments = [
  { id: 1, key: 'loyal', name: 'Düzenli müşteriler', summary: 'Son altı ayda üç veya daha fazla işlem', count: 4 },
  { id: 2, key: 'new', name: 'Yeni kişiler', summary: 'Son 30 günde eklendi', count: 3 },
  { id: 3, key: 'risk', name: 'Kayıp riski', summary: '90 gündür etkileşim yok', count: 2 },
];

export const contacts = people.map(([name, email, phone, role, ltv, churn], i) => ({
  id: 100 + i,
  name,
  email,
  phone,
  company: i % 4 === 0 ? 'Örnek Site' : '',
  city: ['İstanbul', 'Ankara', 'İzmir', 'Bursa'][i % 4],
  ltv,
  currency: 'TRY',
  churn,
  roles: [{ key: role, label: roleLabel[role] }],
  segment_label: ltv > 2000 ? 'Düzenli müşteriler' : i % 3 === 0 ? 'Yeni kişiler' : '',
  source: ['Bağış', 'Form', 'Sipariş'][i % 3],
  note: i === 0 ? 'Yeni içeriklerle ilgili bilgi istiyor.' : '',
  last_activity_at: ago(30 + i * 400),
  consent: { email: !!email, sms: i % 2 === 0, whatsapp: !!phone, ads: false },
  stats: { donations: Math.round(ltv / 150), orders: i % 3 },
  tags: i % 2 ? [{ id: 7, name: 'Bülten', color: '#5B7CFA' }] : [{ id: 8, name: 'Etkinlik', color: '#22C3A6' }],
  timeline: [
    { type: 'donation', type_label: 'Bağış', title: '5 fidan bağışı', body: 'Havale/EFT', at: ago(60 + i * 30), url: '' },
    { type: 'note', type_label: 'Not', title: 'Telefonla arandı', body: 'Sertifika e-postası tekrar gönderildi.', at: ago(60 * 24 * (i + 2)), url: '' },
  ],
}));

export const crm = {
  meta: {
    roles: Object.entries(roleLabel).map(([key, label]) => ({ key, label })),
    segments: segments.map((s) => ({ key: s.key, label: s.name })),
    tags: [
      { id: 7, name: 'Bülten' },
      { id: 8, name: 'Etkinlik' },
    ],
  },
  stats: { total: 1284, new30: 42, active: 612, paying: 388, repeat: 141, risk: 57, ltv: 418250 },
  segments,
};

const titles: [string, string][] = [
  ['Yeni ürün koleksiyonu yayında', 'publish'],
  ['Ekibimizle tanışın', 'publish'],
  ['Kasım içerik planı', 'draft'],
  ['Web siteniz için beş içerik fikri', 'publish'],
  ['Ürün görsellerimiz yenilendi', 'pending'],
  ['Yıl sonu değerlendirmesi', 'draft'],
  ['Basında biz: Ekim 2026', 'publish'],
  ['Müşterilerimize teşekkürler', 'future'],
];

export const posts = titles.map(([t, status], i) => ({
  id: 500 + i,
  date: ago(60 * 24 * i + 90),
  status,
  link: `https://demo.fluxapp.local/?p=${500 + i}`,
  author: 1,
  title: { raw: t, rendered: t },
  excerpt: { raw: '', rendered: i === 0 ? '<p>Yeni koleksiyonumuzu keşfedin. Ürünleri ve ayrıntıları bu yazıda bulabilirsiniz.</p>' : '' },
}));

export const pages = ['Hakkımızda', 'Hizmetler', 'İletişim', 'KVKK aydınlatma metni'].map((t, i) => ({
  id: 700 + i,
  date: ago(60 * 24 * 40 * (i + 1)),
  status: 'publish',
  link: `https://demo.fluxapp.local/?page_id=${700 + i}`,
  title: { raw: t, rendered: t },
}));

export const comments = ([
  ['Hasan B.', 'Şenliğe çocuklarla katılabilir miyiz? Yaş sınırı var mı?', 'hold'],
  ['Merve T.', 'Sertifikam e-postama gelmedi, yardımcı olur musunuz?', 'hold'],
  ['Ali R.', 'Emeğinize sağlık, geçen yıl çok güzeldi.', 'approved'],
  ['Seda K.', 'Okulumuz da kampanyaya katılmak istiyor.', 'approved'],
  ['Onur Y.', 'Raporu sabırsızlıkla bekliyoruz.', 'approved'],
] as [string, string, string][]).map(([author, text, status], i) => ({
  id: 900 + i,
  post: 500 + (i % 3),
  author_name: author,
  date: ago(35 + i * 240),
  status,
  content: { raw: text, rendered: `<p>${text}</p>` },
}));
