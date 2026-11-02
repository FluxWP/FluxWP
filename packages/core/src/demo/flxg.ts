// Flux Tasks tam REST (flx-tasks/v1) demo verisi: ekran görüntüleri ve tarayıcı denemesi için bellek içi sunucu.
import type { FlxgActivity, FlxgComment, FlxgField, FlxgFile, FlxgList, FlxgNotif, FlxgTask, FlxgTaskDetail, FlxgUser, FlxgView } from '../modules/flxg';
import { demoMe } from './fixtures';

const ME = demoMe.user.id;
const pad = (n: number) => String(n).padStart(2, '0');
const day = (off: number) => {
  const d = new Date();
  d.setDate(d.getDate() + off);
  return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
};
const ago = (min: number) => new Date(Date.now() - min * 60000).toISOString();
let seq = 5000;

const users: FlxgUser[] = [
  { id: ME, name: demoMe.user.name, initials: 'DK', avatar: '', color: '#2e7d32' },
  { id: 2, name: 'Elif Demir', initials: 'ED', avatar: '', color: '#7c3aed' },
  { id: 3, name: 'Mert Kaya', initials: 'MK', avatar: '', color: '#0891b2' },
  { id: 4, name: 'Zeynep Arslan', initials: 'ZA', avatar: '', color: '#db2777' },
  { id: 5, name: 'Can Öztürk', initials: 'CÖ', avatar: '', color: '#ea580c' },
];
const st = (open: [string, string, string][], done: [string, string, string]) => [
  ...open.map(([key, label, color]) => ({ key, label, color, type: 'open' as const })),
  { key: done[0], label: done[1], color: done[2], type: 'done' as const },
];
const TASK_ST = st([['todo', 'Yapılacak', '#94a3b8'], ['doing', 'Devam ediyor', '#3b82f6'], ['review', 'İncelemede', '#a855f7']], ['done', 'Tamamlandı', '#16a34a']);
const MTG_ST = st([['planned', 'Planlandı', '#3b82f6']], ['held', 'Yapıldı', '#16a34a']);

const spaces = [
  { id: 1, name: 'Ağaçlandırma', color: '#2e7d32', owner_id: ME, access: 'all' as const, position: 1, role: 'admin' as const, subtask_depth: 3 },
  { id: 2, name: 'Dernek Yönetimi', color: '#2563eb', owner_id: ME, access: 'members' as const, position: 2, role: 'admin' as const, subtask_depth: 3 },
  { id: 3, name: 'İletişim', color: '#db2777', owner_id: 2, access: 'all' as const, position: 3, role: 'edit' as const, subtask_depth: 3 },
];
const folders = [{ id: 1, space_id: 1, name: 'Etkinlikler', position: 1 }];
const lists: FlxgList[] = [
  { id: 11, space_id: 1, folder_id: 1, name: '11 Kasım dikim etkinliği', color: '#16a34a', access: 'all', statuses: TASK_ST, kind: 'task', position: 1, role: 'admin' },
  { id: 13, space_id: 1, folder_id: 1, name: 'Okul fidan günleri', color: '#65a30d', access: 'all', statuses: TASK_ST, kind: 'task', position: 2, role: 'admin' },
  { id: 12, space_id: 1, folder_id: 0, name: 'Fidan bağışları', color: '#0d9488', access: 'all', statuses: TASK_ST, kind: 'task', position: 3, role: 'admin' },
  { id: 21, space_id: 2, folder_id: 0, name: 'Yönetim kurulu toplantıları', color: '#2563eb', access: 'members', statuses: MTG_ST, kind: 'meeting', position: 1, role: 'admin' },
  { id: 22, space_id: 2, folder_id: 0, name: 'Gönüllüler', color: '#6366f1', access: 'all', statuses: TASK_ST, kind: 'task', position: 2, role: 'admin' },
  { id: 31, space_id: 3, folder_id: 0, name: 'Sosyal medya', color: '#db2777', access: 'all', statuses: TASK_ST, kind: 'task', position: 1, role: 'edit' },
];
const tags = [
  { id: 1, space_id: 1, name: 'Acil', color: '#dc2626' },
  { id: 2, space_id: 1, name: 'Lojistik', color: '#7c3aed' },
  { id: 3, space_id: 1, name: 'Basın', color: '#0891b2' },
  { id: 4, space_id: 1, name: 'Gönüllü', color: '#16a34a' },
  { id: 5, space_id: 2, name: 'Bütçe', color: '#d97706' },
  { id: 6, space_id: 3, name: 'Instagram', color: '#db2777' },
];
const fields: FlxgField[] = [
  { id: 1, list_id: 11, name: 'Fidan sayısı', type: 'number', options: {} },
  { id: 2, list_id: 11, name: 'Bölge', type: 'dropdown', options: { choices: [{ id: 'a', label: 'Sarıyer', color: '#16a34a' }, { id: 'b', label: 'Beykoz', color: '#2563eb' }, { id: 'c', label: 'Arnavutköy', color: '#d97706' }] } },
];

type T = FlxgTask & { description?: string };
const base = (o: Partial<T> & Pick<T, 'id' | 'list' | 'title'>): T => ({
  parent: 0, kind: 'task', status: 'todo', priority: 0, start: '', due: '', cover: 0, pos: o.id * 1000, creator: ME, created: ago(60 * 24 * 6), updated: ago(90), completed: null,
  assignees: [], tags: [], fields: {}, comments: 0, files: 0, subtasks: 0, archived: 0, description: '', ...o,
});
const tasks: T[] = [
  base({ id: 101, list: 11, title: 'Dikim alanı için belediyeden izin yazısı', status: 'done', priority: 1, due: day(-3), assignees: [ME], tags: [1], completed: ago(60 * 30), comments: 3, fields: { 2: 'a' } }),
  base({ id: 102, list: 11, title: 'Fidan siparişi ve teslim planı', status: 'doing', priority: 2, start: day(-2), due: day(3), assignees: [ME, 3], tags: [2], comments: 5, files: 2, subtasks: 3, cover: 9001, fields: { 1: 1500, 2: 'a' },
    description: '<p>1.500 fidan (sapsız meşe, kızılçam, ıhlamur) <b>9 Kasım</b> akşamı alana teslim edilecek.</p><ul data-checklist="1"><li data-checked="1">Fidanlık ile adet teyidi</li><li data-checked="1">Kamyon kiralama</li><li data-checked="0">Teslim saatini gönüllü ekibine bildir</li></ul>' }),
  base({ id: 103, list: 11, parent: 102, title: 'Fidanlık ile adet teyidi', status: 'done', assignees: [3], completed: ago(60 * 20) }),
  base({ id: 104, list: 11, parent: 102, title: 'Kamyon kiralama', status: 'done', assignees: [3], completed: ago(60 * 8) }),
  base({ id: 105, list: 11, parent: 102, title: 'Teslim saatini gönüllülere bildir', status: 'todo', assignees: [ME], due: day(5) }),
  base({ id: 106, list: 11, title: 'Gönüllü kayıt formunu yayınla', status: 'review', priority: 3, due: day(1), assignees: [2], tags: [4], comments: 2, fields: { 1: 0 } }),
  base({ id: 107, list: 11, title: 'Basın bülteni ve davet metni', status: 'doing', priority: 3, due: day(4), assignees: [4], tags: [3], comments: 1 }),
  base({ id: 108, list: 11, title: 'Ulaşım: servis güzergâhı ve saatleri', status: 'todo', priority: 2, due: day(6), assignees: [5, ME], tags: [2] }),
  base({ id: 109, list: 11, title: 'Etkinlik günü ilk yardım ekibi', status: 'todo', priority: 1, due: day(9), assignees: [3], tags: [1, 4] }),
  base({ id: 110, list: 11, title: 'Dikim sonrası sertifikaların gönderimi', status: 'todo', priority: 4, due: day(10), assignees: [2] }),
  base({ id: 111, list: 11, title: 'Alan keşfi ve fotoğraf çekimi', status: 'done', start: day(-8), due: day(-6), assignees: [5], files: 1, completed: ago(60 * 24 * 5), fields: { 2: 'a' } }),
  base({ id: 121, list: 12, title: 'Kurumsal bağış teşekkür e-postaları', status: 'doing', priority: 3, due: day(2), assignees: [ME], tags: [] }),
  base({ id: 122, list: 12, title: 'Havale/EFT dekontlarını eşleştir', status: 'todo', priority: 2, due: day(0), assignees: [ME, 2] }),
  base({ id: 131, list: 13, title: 'Okul müdürlükleriyle görüşme takvimi', status: 'todo', priority: 3, due: day(7), assignees: [4] }),
  base({ id: 201, list: 21, title: 'Kasım ayı yönetim kurulu toplantısı', status: 'planned', due: day(2), assignees: [ME, 2, 3, 4], tags: [5],
    description: '<p>Yer: Dernek ofisi, saat 19.00. Gündem maddeleri aşağıda.</p>' }),
  base({ id: 202, list: 21, parent: 201, kind: 'agenda', title: '11 Kasım etkinliği son durum', status: 'held', pos: 1, description: '<p>Fidan teslimi ve gönüllü sayısı.</p>' }),
  base({ id: 203, list: 21, parent: 201, kind: 'agenda', title: '2027 bütçe taslağı', status: 'planned', pos: 2 }),
  base({ id: 204, list: 21, parent: 201, kind: 'agenda', title: 'Yeni gönüllü koordinatörü', status: 'planned', pos: 3 }),
  base({ id: 205, list: 21, parent: 201, kind: 'decision', title: 'Etkinlik için 2 servis aracı kiralanacak', status: 'planned', assignees: [5], due: day(5) }),
  base({ id: 221, list: 22, title: 'Gönüllü oryantasyon sunumu', status: 'todo', priority: 3, due: day(8), assignees: [2, ME] }),
  base({ id: 311, list: 31, title: 'Instagram: dikim etkinliği geri sayım serisi', status: 'doing', priority: 3, due: day(3), assignees: [4, ME], tags: [6], comments: 4 }),
];
const kidsOf = (id: number) => tasks.filter((t) => t.parent === id);
for (const t of tasks) t.subtasks = kidsOf(t.id).filter((k) => k.kind === 'task').length;
(tasks.find((t) => t.id === 201) as T).agenda = 3;
(tasks.find((t) => t.id === 201) as T).decisions = 1;

const svg = (c1: string, c2: string, label: string) =>
  `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500"><defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs><rect width="800" height="500" fill="url(#g)"/><path d="M0 380 Q200 300 400 360 T800 340 V500 H0z" fill="#14532d" opacity=".85"/>${[80, 190, 300, 430, 560, 680].map((x, i) => `<g transform="translate(${x} ${330 + (i % 2) * 20})"><rect x="-4" y="0" width="8" height="40" fill="#5b3a1e"/><circle cy="-10" r="${34 + (i % 3) * 8}" fill="#15803d"/><circle cx="-14" cy="6" r="22" fill="#166534"/></g>`).join('')}<text x="30" y="60" font-family="sans-serif" font-size="30" font-weight="700" fill="#fff">${label}</text></svg>`;
const fileBlobs: Record<number, string> = {
  9001: svg('#bae6fd', '#86efac', 'Sarıyer dikim alanı'),
  9002: svg('#fde68a', '#bbf7d0', 'Fidan teslim noktası'),
};
const file = (id: number, task: number, name: string, image: boolean, size: number, user = ME): FlxgFile => ({ id, task, comment: 0, field: 0, context: 'task', user, name, mime: image ? 'image/svg+xml' : 'application/pdf', size, image, created: ago(60 * 26) });
const attachments: Record<number, FlxgFile[]> = {
  102: [file(9001, 102, 'dikim-alani.svg', true, 182_000), file(9002, 102, 'teslim-noktasi.svg', true, 164_000, 3), file(9003, 102, 'fidan-siparis-formu.pdf', false, 412_000, 3)],
};
const comments: Record<number, FlxgComment[]> = {
  102: [
    { id: 1, user: 3, body: 'Fidanlık 1.500 adedi teyit etti, sapsız meşe 600 adet.', created: ago(60 * 22), files: [] },
    { id: 2, user: ME, body: '@{3} süper 👍 Kamyonu da **Cuma** için ayarlayalım.', created: ago(60 * 20), files: [] },
    { id: 3, user: 2, body: 'Gönüllü listesi 84 kişiye ulaştı. Teslim saatini paylaşırsak servisleri ona göre planlarım.', created: ago(60 * 3), files: [] },
  ],
};
const activity: Record<number, FlxgActivity[]> = {
  102: [
    { id: 1, user: ME, action: 'created', data: {}, created: ago(60 * 24 * 6) },
    { id: 2, user: ME, action: 'assign', data: { user: 3 }, created: ago(60 * 24 * 6) },
    { id: 3, user: 3, action: 'status', data: { from: 'todo', to: 'doing' }, created: ago(60 * 23) },
    { id: 4, user: 3, action: 'attach', data: { name: 'fidan-siparis-formu.pdf' }, created: ago(60 * 21) },
  ],
};
const views: FlxgView[] = [];
let vseq = 1;
const mkViews = (scope: FlxgView['scope'], scope_id: number, meeting = false) => {
  const fieldsCfg = meeting ? ['assignees', 'due', 'tags'] : ['assignees', 'due', 'priority', 'tags'];
  const out: FlxgView[] = [
    { id: vseq++, scope, scope_id, user_id: 0, name: 'Liste', type: 'list', config: { fields: scope === 'list' && scope_id === 11 ? [...fieldsCfg, 'f1', 'f2'] : scope === 'list' ? fieldsCfg : ['list', ...fieldsCfg], group: 'status', subtasks: 'closed' }, position: 1 },
    { id: vseq++, scope, scope_id, user_id: 0, name: 'Pano', type: 'board', config: { fields: ['assignees', 'due', 'tags'], cover: true, group: 'status' }, position: 2 },
    { id: vseq++, scope, scope_id, user_id: 0, name: 'Takvim', type: 'calendar', config: { fields: [], group: 'none' }, position: 3 },
  ];
  views.push(...out);
  return out;
};
mkViews('home', ME);
for (const l of lists) mkViews('list', l.id, l.kind === 'meeting');
for (const s of spaces) mkViews('space', s.id);
const viewsFor = (scope: string, id: number) => views.filter((v) => v.scope === scope && v.scope_id === id);

const notifs: FlxgNotif[] = [
  { id: 41, type: 'mention', actor: 2, task: 102, list: 11, title: 'Fidan siparişi ve teslim planı', read: false, created: ago(12), data: { excerpt: 'Teslim saatini paylaşırsak servisleri ona göre planlarım.' } },
  { id: 40, type: 'assigned', actor: 3, task: 108, list: 11, title: 'Ulaşım: servis güzergâhı ve saatleri', read: false, created: ago(95) },
  { id: 39, type: 'status', actor: 2, task: 106, list: 11, title: 'Gönüllü kayıt formunu yayınla', read: false, created: ago(60 * 5), data: { label: 'İncelemede' } },
  { id: 38, type: 'comment', actor: 4, task: 311, list: 31, title: 'Instagram: dikim etkinliği geri sayım serisi', read: true, created: ago(60 * 26), data: { excerpt: 'İlk görsel taslağı ekledim.' } },
  { id: 37, type: 'due', actor: 0, task: 122, list: 12, title: 'Havale/EFT dekontlarını eşleştir', read: true, created: ago(60 * 30) },
];
const prefs: Record<string, unknown> = {};

const pub = (t: T): FlxgTask => {
  const { description, ...rest } = t;
  return { ...rest, hasDesc: !!description, files: (attachments[t.id] ?? []).length || t.files };
};
const roleOf = (listId: number) => lists.find((l) => l.id === listId)?.role ?? 'view';
function detail(id: number): FlxgTaskDetail | null {
  const t = tasks.find((x) => x.id === id);
  if (!t) return null;
  const children = kidsOf(id).map(pub);
  const subtree: FlxgTask[] = [];
  const walk = (pid: number) => {
    for (const k of kidsOf(pid)) {
      subtree.push(pub(k));
      walk(k.id);
    }
  };
  for (const k of kidsOf(id)) walk(k.id);
  const parent = t.parent ? tasks.find((x) => x.id === t.parent) : null;
  let depth = 0;
  for (let p = parent; p; p = p.parent ? tasks.find((x) => x.id === p!.parent) : null) depth++;
  return {
    ...pub(t),
    description: t.description ?? '',
    comments_list: comments[id] ?? [],
    activity: activity[id] ?? [{ id: 1, user: t.creator, action: 'created', data: {}, created: t.created ?? ago(60) }],
    attachments: attachments[id] ?? [],
    watchers: t.assignees,
    watching: t.assignees.includes(ME),
    children,
    subtree,
    role: roleOf(t.list),
    parentInfo: parent ? { id: parent.id, title: parent.title } : null,
    depth,
    depthLimit: 3,
    childFiles: {},
  };
}
function apply(t: T, b: Record<string, unknown>) {
  for (const [k, v] of Object.entries(b)) {
    if (k === 'detail') continue;
    if (k === 'fields') t.fields = Object.fromEntries(Object.entries({ ...t.fields, ...(v as object) }).filter(([, x]) => x !== null && x !== ''));
    else if (k === 'position') t.pos = Number(v);
    else if (k === 'list') t.list = Number(v);
    else if (k === 'archived') t.archived = v ? 1 : 0;
    else (t as unknown as Record<string, unknown>)[k] = v;
  }
  if ('status' in b) {
    const s = lists.find((l) => l.id === t.list)?.statuses.find((x) => x.key === b.status);
    t.completed = s?.type === 'done' ? new Date().toISOString() : null;
  }
  t.updated = new Date().toISOString();
}
const structure = () => ({ spaces, folders, lists, tags, docs: [], rev: 1 });

type H = (m: RegExpMatchArray, q: URLSearchParams, body: Record<string, unknown>) => unknown;
const P = 'flx-tasks\\/v1\\/';
const r = (s: string) => new RegExp('^' + P + s + '$');
export const flxgRoutes: [string, RegExp, H][] = [
  ['GET', r('boot'), () => ({
    ...structure(),
    me: { ...users[0]!, can: { create: true, manage: true, ai: true }, email: true, prefs },
    users,
    unread: notifs.filter((n) => !n.read).length,
    time: new Date().toISOString(),
    config: { poll: 45, maxUpload: 20 * 1024 * 1024, priorities: [
      { id: 1, label: 'Acil', color: '#dc2626' }, { id: 2, label: 'Yüksek', color: '#f59e0b' }, { id: 3, label: 'Normal', color: '#3b82f6' }, { id: 4, label: 'Düşük', color: '#94a3b8' },
    ], today: day(0), weekStart: 1, fieldTypes: {} },
  })],
  ['POST', r('me'), (_m, _q, b) => (Object.assign(prefs, b.prefs ?? {}), { ok: true })],
  ['GET', r('home'), () => ({ role: 'admin', views: viewsFor('home', ME), fields })],
  ['GET', r('lists\\/(\\d+)'), (m) => {
    const l = lists.find((x) => x.id === +m[1]!);
    return l ? { role: l.role, list: l, views: viewsFor('list', l.id), fields: fields.filter((f) => f.list_id === l.id) } : null;
  }],
  ['GET', r('spaces\\/(\\d+)'), (m) => {
    const s = spaces.find((x) => x.id === +m[1]!);
    return s ? { role: s.role, space: s, views: viewsFor('space', s.id), fields: fields.filter((f) => lists.some((l) => l.id === f.list_id && l.space_id === s.id)) } : null;
  }],
  ['GET', r('tasks'), (_m, q) => {
    let list = tasks.filter((t) => !t.archived);
    if (q.get('list')) list = list.filter((t) => t.list === +q.get('list')!);
    else if (q.get('space')) list = list.filter((t) => lists.find((l) => l.id === t.list)?.space_id === +q.get('space')!);
    else list = list.filter((t) => t.assignees.includes(ME) || tasks.some((p) => p.id === t.parent && p.assignees.includes(ME)));
    return { tasks: list.map(pub), time: new Date().toISOString(), fields };
  }],
  ['GET', r('tasks\\/(\\d+)'), (m) => {
    const d = detail(+m[1]!);
    return d ? { task: d, unread: notifs.filter((n) => !n.read).length } : null;
  }],
  ['POST', r('tasks'), (_m, _q, b) => {
    const l = lists.find((x) => x.id === Number(b.list)) ?? lists[0]!;
    const t = base({ id: ++seq, list: l.id, title: String(b.title ?? ''), status: String(b.status ?? l.statuses[0]!.key), parent: Number(b.parent ?? 0), kind: String(b.kind ?? 'task'), assignees: (b.assignees as number[]) ?? [], created: new Date().toISOString() });
    apply(t, { ...b, list: l.id, parent: Number(b.parent ?? 0) });
    tasks.push(t);
    const p = tasks.find((x) => x.id === t.parent);
    if (p && t.kind === 'task') p.subtasks++;
    return { task: pub(t) };
  }],
  ['PATCH', r('tasks\\/(\\d+)'), (m, _q, b) => {
    const t = tasks.find((x) => x.id === +m[1]!);
    if (!t) return null;
    apply(t, b);
    return { task: b.detail ? detail(t.id) : pub(t) };
  }],
  ['DELETE', r('tasks\\/(\\d+)'), (m) => {
    const i = tasks.findIndex((x) => x.id === +m[1]!);
    if (i > -1) tasks.splice(i, 1);
    return { deleted: +m[1]! };
  }],
  ['POST', r('tasks\\/bulk'), (_m, _q, b) => {
    const ids = (b.ids as number[]) ?? [];
    for (const id of ids) {
      const t = tasks.find((x) => x.id === id);
      if (!t) continue;
      if (b.delete) tasks.splice(tasks.indexOf(t), 1);
      else if (b.patch) apply(t, b.patch as Record<string, unknown>);
    }
    return { ok: true };
  }],
  ['POST', r('tasks\\/(\\d+)\\/watch'), (m, _q, b) => {
    const t = tasks.find((x) => x.id === +m[1]!);
    return { watchers: b.watch ? [...(t?.assignees ?? []), ME].filter((v, i, a) => a.indexOf(v) === i) : (t?.assignees ?? []).filter((x) => x !== ME) };
  }],
  ['POST', r('tasks\\/(\\d+)\\/comments'), (m, _q, b) => {
    const id = +m[1]!;
    const c: FlxgComment = { id: ++seq, task: id, user: ME, body: String(b.body ?? ''), created: new Date().toISOString(), files: [] };
    (comments[id] ??= []).push(c);
    const t = tasks.find((x) => x.id === id);
    if (t) t.comments++;
    return c;
  }],
  ['PATCH', r('comments\\/(\\d+)'), () => ({ ok: true })],
  ['DELETE', r('comments\\/(\\d+)'), () => ({ ok: true })],
  ['GET', r('files\\/(\\d+)'), (m) => {
    const s = fileBlobs[+m[1]!];
    return s ? new Response(s, { headers: { 'Content-Type': 'image/svg+xml' } }) : new Response('%PDF-1.4', { headers: { 'Content-Type': 'application/pdf' } });
  }],
  ['POST', r('files'), (_m, _q, b) => file(++seq, Number(b.task ?? 0), 'dosya', false, 1000)],
  ['DELETE', r('files\\/(\\d+)'), () => ({ ok: true })],
  ['POST', r('tags'), (_m, _q, b) => {
    const t = { id: ++seq, space_id: Number(b.space), name: String(b.name), color: '#64748b' };
    tags.push(t);
    return { tag: t, tags };
  }],
  ['POST', r('views'), (_m, _q, b) => {
    const v: FlxgView = { id: ++seq, scope: b.scope as FlxgView['scope'], scope_id: Number(b.scope_id), user_id: b.shared ? 0 : ME, name: String(b.name), type: b.type as FlxgView['type'], config: (b.config ?? {}) as FlxgView['config'], position: 9 };
    views.push(v);
    return { view: v, views: viewsFor(v.scope, v.scope_id) };
  }],
  ['PATCH', r('views\\/(\\d+)'), (m, _q, b) => {
    const v = views.find((x) => x.id === +m[1]!);
    if (!v) return null;
    if (b.name) v.name = String(b.name);
    if (b.config) v.config = b.config as FlxgView['config'];
    if ('shared' in b) v.user_id = b.shared ? 0 : ME;
    return { view: v };
  }],
  ['DELETE', r('views\\/(\\d+)'), (m) => {
    const v = views.find((x) => x.id === +m[1]!);
    if (v) views.splice(views.indexOf(v), 1);
    return { views: v ? viewsFor(v.scope, v.scope_id) : [] };
  }],
  ['GET', r('notifications'), (_m, q) => {
    const before = Number(q.get('before') || 0);
    let list = notifs.filter((n) => !before || n.id < before);
    if (q.get('unread')) list = list.filter((n) => !n.read);
    return { items: list, unread: notifs.filter((n) => !n.read).length };
  }],
  ['POST', r('notifications\\/read'), (_m, _q, b) => {
    for (const n of notifs) if (b.all || ((b.ids as number[]) ?? []).includes(n.id) || n.task === b.task) n.read = true;
    return { unread: notifs.filter((n) => !n.read).length };
  }],
  ['GET', r('sync'), () => ({ time: new Date().toISOString(), unread: notifs.filter((n) => !n.read).length, rev: 1, notifs: [], tasks: [], gone: [] })],
  ['POST', r('ai\\/task\\/(\\d+)'), (_m, _q, b) =>
    b.action === 'subtasks'
      ? { items: ['Teslim noktası krokisini hazırla', 'Gönüllü ekip liderlerini belirle', 'Fidan dağıtım listesini paylaş'] }
      : b.action === 'title'
        ? { text: 'Fidan siparişi, teslim ve dağıtım planı' }
        : b.action === 'summary'
          ? { text: 'Fidanlık 1.500 adedi teyit etti, kamyon Cuma günü. Gönüllü sayısı 84; servis planı teslim saatine bağlı.' }
          : { html: '<p><b>Amaç:</b> 1.500 fidanın 9 Kasım akşamı Sarıyer dikim alanına eksiksiz teslimi.</p><ul data-checklist="1"><li data-checked="1">Fidanlık ile adet teyidi</li><li data-checked="1">Kamyon kiralama</li><li data-checked="0">Teslim saatini gönüllülere bildir</li><li data-checked="0">Teslim tutanağını imzalat</li></ul>' }],
];
