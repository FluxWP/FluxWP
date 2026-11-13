import { demoDashboard, demoMe } from './fixtures';
import * as M from './modules';
import * as Y from './flxc';
import { flxgRoutes } from './flxg';
import { DEMO_ORIGIN } from './origin';
import { getDemoLocale, localizeDemo, localizedDemoResponse } from './locale';

export const DEMO_SITE = DEMO_ORIGIN;

type Handler = (m: RegExpMatchArray, q: URLSearchParams, body: Record<string, unknown>) => unknown;
const page = <T>(items: T[]) => ({ items, total: items.length, page: 1, pages: 1 });
let seq = 1000;

const routes: [string, RegExp, Handler][] = [
  ...flxgRoutes,
  ['GET', /^flxapp\/v1\/me$/, () => demoMe],
  ['GET', /^flxapp\/v1\/counts$/, () => ({ ...demoMe.counts, 'flx-chat': M.chats.reduce((n, c) => n + c.unread, 0) })],
  ['GET', /^flxapp\/v1\/dashboard$/, () => demoDashboard],
  ['POST', /^flxapp\/v1\/session$/, () => ({ url: 'about:blank', expires_in: 60 })],
  ['POST', /^flxapp\/v1\/logout$/, () => ({ ok: true })],
  // Flux Donate
  ['GET', /^flxb\/v1\/app\/pending$/, () => page(M.pending)],
  ['POST', /^flxb\/v1\/app\/donations\/(\d+)\/(approve|reject)$/, (m) => {
    const i = M.pending.findIndex((p) => p.id === Number(m[1]));
    if (i >= 0) M.pending.splice(i, 1);
    return m[2] === 'approve' ? { ok: true, status_label: 'Tamamlandı', certificates: 1 } : { ok: true, status_label: 'İptal edildi' };
  }],
  // Flux Tasks
  ['GET', /^flx-tasks\/v1\/app\/projects$/, () => ({ items: M.projects })],
  ['GET', /^flx-tasks\/v1\/app\/tasks$/, (_m, q) => {
    const view = q.get('view') ?? 'mine';
    const s = (q.get('q') ?? '').toLocaleLowerCase('tr');
    let list = M.tasks.filter((t) => (view === 'project' ? t.project.id === Number(q.get('project')) : t.status === 'open'));
    if (view === 'today') list = list.filter((t) => t.due_label === 'Bugün' || t.overdue);
    if (view === 'overdue') list = list.filter((t) => t.overdue);
    if (s) list = list.filter((t) => t.title.toLocaleLowerCase('tr').includes(s));
    return page(list);
  }],
  ['GET', /^flx-tasks\/v1\/app\/tasks\/(\d+)$/, (m) => M.taskDetail(Number(m[1]))],
  ['POST', /^flx-tasks\/v1\/app\/tasks$/, (_m, _q, b) => {
    const p = M.projects.find((x) => x.id === Number(b.project)) ?? M.projects[0]!;
    const t = { ...M.tasks[0]!, id: ++seq, title: String(b.title), project: { id: p.id, title: p.title, color: p.color }, due: null, due_label: '', overdue: false, priority: 0 as const, comments: 0, checklist: { done: 0, total: 0 } };
    M.tasks.unshift(t);
    return t;
  }],
  ['POST', /^flx-tasks\/v1\/app\/tasks\/(\d+)$/, (m, _q, b) => {
    const t = M.tasks.find((x) => x.id === Number(m[1]));
    if (t) Object.assign(t, b);
    return t;
  }],
  ['POST', /^flx-tasks\/v1\/app\/tasks\/(\d+)\/comments$/, (_m, _q, b) => ({ id: ++seq, author: { id: 1, name: demoMe.user.name }, text: String(b.text), at: new Date().toISOString(), at_label: 'şimdi' })],
  ['POST', /^flx-tasks\/v1\/app\/tasks\/(\d+)\/checklist\/(\d+)$/, () => ({ ok: true })],
  // Flux Chat
  // Flux Chat tam REST (WhatsApp ekranı)
  ['GET', /^flx-chat\/v1\/bootstrap$/, () => Y.flxcBoot()],
  ['GET', /^flx-chat\/v1\/sync$/, (_m, q) => Y.flxcSync(q)],
  ['GET', /^flx-chat\/v1\/chats$/, (_m, q) => Y.flxcChatsQuery(q)],
  ['GET', /^flx-chat\/v1\/chats\/(\d+)$/, (m) => {
    const c = Y.flxcChats.find((x) => x.id === Number(m[1]));
    return c ? { chat: c, sections: c.id === 301 ? [{ id: 'flxs', title: 'Sertifikalar', items: [{ label: 'Ayşe Kaya adına 5 fidan', sub: 'FLX-2026-00388 · 2 Ekim 2026', url: 'https://example.org/sertifika/FLX-2026-00388', insert: 'https://example.org/sertifika/FLX-2026-00388' }] }] : [] } : null;
  }],
  ['POST', /^flx-chat\/v1\/check-number$/, (_m, _q, b) => Y.flxcCheck(String(b.phone ?? ''))],
  ['POST', /^flx-chat\/v1\/chats$/, (_m, _q, b) => Y.flxcNewChat(String(b.phone ?? ''), String(b.name ?? ''))],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)$/, (m, _q, b) => Y.flxcUpdate(Number(m[1]), b)],
  ['GET', /^flx-chat\/v1\/chats\/(\d+)\/messages$/, (m) => ({ messages: Y.flxcMessages[Number(m[1])] ?? [], more: false, note: '' })],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)\/messages$/, (m, _q, b) => Y.flxcSend(Number(m[1]), b)],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)\/read$/, (m) => Y.flxcRead(Number(m[1]))],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)\/typing$/, () => ({ ok: true })],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)\/bot$/, (m, _q, b) => Y.flxcSetBot(Number(m[1]), b.state === 'on')],
  ['POST', /^flx-chat\/v1\/chats\/(\d+)\/ai$/, () => ({ text: 'Merhaba Ayşe Hanım, dekontunuzu aldık 🌱 Ekibimiz onayladığında sertifikanız WhatsApp ve e-postayla gelecek.' })],
  ['DELETE', /^flx-chat\/v1\/messages\/(\d+)$/, (m, q) => Y.flxcDelete(Number(m[1]), q.get('scope') ?? 'panel')],
  ['POST', /^flx-chat\/v1\/messages\/(\d+)\/react$/, (m, _q, b) => Y.flxcReact(Number(m[1]), String(b.emoji ?? ''))],
  ['POST', /^flx-chat\/v1\/replies\/(\d+)\/use$/, (m) => ({ text: (Y.flxcBoot().replies.find((r) => r.id === Number(m[1]))?.body ?? '').replace('{first_name}', 'Ayşe') })],
  ['GET', /^flx-chat\/v1\/link-preview$/, (_m, q) => ({ ok: true, url: q.get('url'), title: 'Örnek Vakfı', domain: new URL(q.get('url') ?? 'https://x').hostname })],
  ['GET', /^flx-chat\/v1\/media\/(\d+)$/, (m) => Y.flxcMedia(Number(m[1]))],
  ['GET', /^flx-chat\/v1\/app\/chats$/, (_m, q) => {
    const f = q.get('filter') ?? 'open';
    const s = (q.get('q') ?? '').toLocaleLowerCase('tr');
    let list = M.chats.slice();
    if (f === 'mine') list = list.filter((c) => c.assigned?.id === 1);
    if (f === 'unread') list = list.filter((c) => c.unread > 0);
    if (s) list = list.filter((c) => (c.name + c.phone).toLocaleLowerCase('tr').includes(s));
    return page(list);
  }],
  ['GET', /^flx-chat\/v1\/app\/chats\/(\d+)$/, (m) => ({ ...M.chats.find((c) => c.id === Number(m[1])), notes: '', donor: Number(m[1]) === 301 ? { name: 'Ayşe Kaya', total_label: '2.250 ₺', count: 3 } : null })],
  ['GET', /^flx-chat\/v1\/app\/chats\/(\d+)\/messages$/, (m, q) => {
    const all = M.messages[Number(m[1])] ?? [];
    const after = Number(q.get('after') || 0);
    return { items: after ? all.filter((x) => x.id > after) : all, has_more: false };
  }],
  ['POST', /^flx-chat\/v1\/app\/chats\/(\d+)\/send$/, (m, _q, b) => {
    const id = Number(m[1]);
    const now = new Date();
    const msg = { id: ++seq, dir: 'out' as const, by: 'staff' as const, author: demoMe.user.name, text: String(b.text), media: null, at: now.toISOString(), at_label: now.toTimeString().slice(0, 5), status: 'sent' as const };
    (M.messages[id] ??= []).push(msg);
    const c = M.chats.find((x) => x.id === id);
    if (c) c.last = { text: msg.text, at: msg.at, at_label: msg.at_label, dir: 'out', by: 'staff' };
    return msg;
  }],
  ['POST', /^flx-chat\/v1\/app\/chats\/(\d+)\/read$/, (m) => {
    const c = M.chats.find((x) => x.id === Number(m[1]));
    if (c) c.unread = 0;
    return { ok: true };
  }],
  ['POST', /^flx-chat\/v1\/app\/chats\/(\d+)\/bot$/, (m, _q, b) => {
    const c = M.chats.find((x) => x.id === Number(m[1]));
    if (c) c.bot = { on: Boolean(b.on) };
    return c;
  }],
];

export const demoFetch: typeof fetch = async (input, init) => {
  const language = getDemoLocale();
  const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);
  const path = url.pathname.replace(/^\/wp-json\//, '');
  const method = (init?.method ?? 'GET').toUpperCase();
  let body: Record<string, unknown> = {};
  if (init?.body instanceof FormData) {
    body = Object.fromEntries([...(init.body as unknown as { entries(): Iterable<[string, unknown]> }).entries()].filter(([, v]) => typeof v === 'string'));
  } else if (typeof init?.body === 'string') {
    try {
      body = JSON.parse(init.body);
    } catch {
      body = Object.fromEntries(new URLSearchParams(init.body));
    }
  }
  await new Promise((r) => setTimeout(r, 120));
  for (const [m, re, h] of routes) {
    const match = method === m ? path.match(re) : null;
    if (match) {
      const out = h(match, url.searchParams, body);
      if (out instanceof Response) return localizedDemoResponse(out, language);
      if (out === null || out === undefined) return new Response(JSON.stringify({ code: 'not_found', message: path }), { status: 404 });
      return new Response(JSON.stringify(localizeDemo(out, language)), { status: 200, headers: { 'Content-Type': 'application/json' } });
    }
  }
  return new Response(JSON.stringify({ code: 'rest_no_route', message: path }), { status: 404 });
};
