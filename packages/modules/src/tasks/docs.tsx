// Flux Tasks dokümanları: WP'deki doküman editörü (plugins/flx-tasks/assets/js/docs.js) ve çekirdeği (core.js)
// aynı dosyalardan yüklenir; tek kaynak, aynı davranış. Burada yalnız köprü var: motorun durumu (S), API istemcisi,
// yönlendirme, açılır menü / pencere / bildirim React katmanına (ui.tsx) bağlanır.
import { useEffect, useLayoutEffect, useRef, type ReactNode } from 'react';
import type { FlxgFile, FlxgTask } from '@fluxwp/core';
import { useHost, type ModuleHost } from '../host';
import { rank, between, norm, type FlxgDocRef, type Route } from './engine';
import { flxgLocale, tt } from './i18n';
import { openAI } from './pages';
import { useYtg, type Ctx, type MenuItem } from './ui';

type Fn = (...a: never[]) => unknown;
/** WP çekirdeğinin (window.FLXG) bu köprünün kullandığı yüzü. */
interface Legacy {
  [k: string]: unknown;
  h: (tag: string, ...rest: unknown[]) => HTMLElement;
  icon: (n: string, s?: number, cls?: string) => SVGElement;
  showDoc: (main: HTMLElement, id: number, pg: number) => Promise<void>;
  leaveDoc: () => void;
  newDoc: (space: number, folder: number) => void;
  docMenu: (anchor: HTMLElement, d: FlxgDocRef) => void;
  docSyncNow?: () => void;
}
declare global {
  interface Window {
    FLXG?: Legacy;
    FLXG_BOOT?: Record<string, unknown>;
  }
}

/** Ekranın o anki bağlamı: köprü işlevleri hep bunu okur (FluxApp'te hesap değişebilir). */
const cur: { c: Ctx | null; host: ModuleHost | null; toggleSide: () => void } = { c: null, host: null, toggleSide: () => {} };
const need = () => {
  if (!cur.c) throw new Error('Flux Tasks: no screen');
  return cur.c;
};

/** Yönlendirme: '#/doc/3/p/5', '#/space/2', '#/home' … */
function routeOf(hash: string): Partial<Route> {
  const p = hash.replace(/^#\/?/, '').split('/');
  const r: Partial<Route> = { page: 'home', id: 0, view: 0, task: 0, pg: 0 };
  if (p[0] === 'list' || p[0] === 'space' || p[0] === 'doc') {
    r.page = p[0];
    r.id = +(p[1] ?? 0);
  } else if (p[0] === 'inbox' || p[0] === 'dashboard' || p[0] === 'settings' || p[0] === 'profile') r.page = p[0];
  for (let i = 2; i < p.length; i += 2) {
    if (p[i] === 'p') r.pg = +(p[i + 1] ?? 0);
    if (p[i] === 't') r.task = +(p[i + 1] ?? 0);
  }
  return r;
}

/** DOM düğümünü React açılır kutusunun içine koyar. */
function DomNode({ node }: { node: Node }) {
  const ref = useRef<HTMLDivElement>(null);
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    el.appendChild(node);
    return () => {
      if (node.parentNode === el) el.removeChild(node);
    };
  }, [node]);
  return <div ref={ref} style={{ display: 'contents' }} />;
}

let loading: Promise<Legacy> | null = null;
/** core.js + docs.js bir kez yüklenir; sonra köprü işlevleri bağlanır. */
function load(host: ModuleHost): Promise<Legacy> {
  if (loading) return loading;
  loading = (async () => {
    const lang = flxgLocale();
    const dict = await import('./legacy-i18n').then((m) => m.legacyDict(lang)).catch(() => ({}));
    window.FLXG_BOOT = {
      rest: (host.siteUrl ?? '') + '/wp-json/flx-tasks/v1/',
      nonce: '',
      locale: lang,
      brand: host.siteName ?? '',
      i18n: dict,
      ...(window.FLXG_BOOT ?? {}),
    };
    window.FLXG = window.FLXG ?? ({} as Legacy);
    await import('../../../../plugins/flx-tasks/assets/js/core.js');
    const Y = window.FLXG!;
    bridge(Y); // docs.js yüklenirken Y.S'yi yakalar: önce köprü.
    await import('../../../../plugins/flx-tasks/assets/js/docs.js');
    return Y;
  })();
  loading.catch(() => (loading = null));
  return loading;
}

function bridge(Y: Legacy) {
  const set = (k: string, f: Fn | unknown) => (Y[k] = f);
  // Durum: o anki ekranın motoru (hesap değişince yeni motor; yakalanan nesne hep güncele bakar).
  const S = new Proxy({} as Record<string | symbol, unknown>, {
    get: (_, k) => (need().e.S as unknown as Record<string | symbol, unknown>)[k],
    set: (_, k, v) => (((need().e.S as unknown as Record<string | symbol, unknown>)[k] = v), true),
    has: (_, k) => k in need().e.S,
  });
  Object.defineProperty(Y, 'S', { get: () => S, configurable: true });
  // Veri ve yetki.
  set('rank', rank);
  set('between', between);
  set('norm', norm);
  set('list', (id: number) => need().e.list(id));
  set('space', (id: number) => need().e.space(id));
  set('folder', (id: number) => need().e.folder(id));
  set('canList', (id: number, n?: string) => need().e.canList(id, n));
  set('status', (x: FlxgTask) => need().e.status(x));
  set('isDone', (x: FlxgTask) => need().e.isDone(x));
  set('applyStructure', (d: Record<string, unknown>) => need().e.applyStructure(d));
  set('reloadTasks', () => void need().e.reloadTasks().catch(() => {}));
  set('openTask', (id: number) => need().e.openTask(id));
  set('openAI', (ctx?: { page?: { id: number; title: string } | null; task?: number }) => openAI(ctx ?? {}));
  set('isMobile', () => window.innerWidth <= 720);
  set('go', (hash: string) => void need().e.go(routeOf(hash)));
  // API: uygulamada belirteç, WP'de çerez + nonce; ikisi de istemciden geçer.
  set('api', (method: string, path: string, body?: unknown) => {
    const m = method.toUpperCase() as 'GET' | 'POST' | 'PATCH' | 'DELETE';
    return m === 'GET' ? need().e.api.rest('GET', path, undefined, body as Record<string, string | number>) : need().e.api.rest(m, path, body);
  });
  set('sendLeave', (pageId: number, body: Record<string, unknown>) => void need().e.api.rest('POST', `doc-pages/${pageId}/sync`, body).catch(() => {}));
  set('upload', (file: File, params: Record<string, string | number>) => need().e.api.upload(file, params));
  const coreFileUrl = Y.fileUrl as (id: number, size?: string, dl?: boolean) => string;
  set('fileUrl', (id: number, size?: string, dl?: boolean) => {
    if (cur.host?.mode === 'wp' && !cur.host.blobMedia) return coreFileUrl(id, size, dl);
    // Uygulamada dosya oturum çerezi olmadan açılmaz: önce boş, sonra istemciden blob (aşağıdaki gözlemci).
    return 'data:image/gif;base64,R0lGODlhAQABAAAAACw=#flxg-file-' + (parseInt(String(id), 10) || 0) + (size ? '-' + size : '');
  });
  // Açılır menü, kutu, pencere, bildirim: React katmanı (aynı tasarım, tek açık kutu).
  set('closePop', () => need().ui.closePop());
  set('pop', (anchor: HTMLElement, node: Node, o: { alignRight?: boolean; cls?: string; inline?: boolean; onClose?: () => void } = {}) => {
    need().ui.openPop(anchor, <DomNode node={node} />, { alignRight: o.alignRight, cls: o.cls, inline: o.inline, onClose: o.onClose });
    return node;
  });
  set('menu', (anchor: HTMLElement, items: (MenuItem | null)[], o?: { alignRight?: boolean }) => need().ui.menu(anchor, items, o));
  set('confirm', (title: string, text: string, ok?: string, danger?: boolean) => need().ui.confirm(title, text, ok, danger));
  set('prompt', (title: string, value?: string, ok?: string) => need().ui.prompt(title, value, ok));
  set('modal', (title: string, body: Node, o: { actions?: { label: string; primary?: boolean; danger?: boolean; onClick?: () => unknown }[]; wide?: boolean; onClose?: () => void } = {}) => {
    const close = need().ui.modal({ title, body: <DomNode node={body} />, actions: o.actions, wide: o.wide, onClose: o.onClose });
    return { close };
  });
  set('toast', (msg: string, o: { error?: boolean; action?: { label: string; fn: () => void } | null } = {}) => need().ui.toast(msg, { error: o.error, action: o.action ?? undefined }));
  set('fail', (er: unknown) => need().ui.fail(er));
  set('lightbox', (files: (Partial<FlxgFile> & { id: number })[], index: number) => {
    const c = need();
    c.ui.lightbox = { files: files.map((f) => ({ task: 0, comment: 0, field: 0, context: 'doc', user: 0, name: '', mime: 'image/*', size: 0, image: true, created: null, ...f }) as FlxgFile), index };
    c.ui.bump();
  });
  // Üst şerit: React'teki TopBar ile aynı yapı.
  set('topBar', (title: Node, extra: (Node | null)[]) => {
    const h = Y.h;
    const unread = need().e.S.unread;
    return h(
      'header.flxg-top',
      h('button.flxg-icon-btn.flxg-menu-btn', { type: 'button', title: tt('Show sidebar'), 'aria-label': tt('Menu'), onclick: () => cur.toggleSide() }, Y.icon(window.innerWidth <= 720 ? 'menu' : 'sidebar', 20)),
      h('.flxg-top-title', title),
      h(
        '.flxg-top-actions',
        extra,
        h('a.flxg-icon-btn.flxg-only-mobile.flxg-bell', { href: '#/inbox', 'aria-label': tt('Notifications'), onclick: (ev: Event) => (ev.preventDefault(), void need().e.go({ page: 'inbox' })) }, Y.icon('bell', 19), unread ? h('span.flxg-dotbadge', unread > 9 ? '9+' : String(unread)) : null),
      ),
    );
  });
}

/** Uygulamada doküman görselleri: istemciden blob olarak çekilir. */
function useBlobImages(root: React.RefObject<HTMLElement | null>, on: boolean) {
  const c = useYtg();
  useEffect(() => {
    const el = root.current;
    if (!on || !el) return;
    const urls = new Map<string, string>();
    const fix = () => {
      el.querySelectorAll<HTMLImageElement>('img[src*="#flxg-file-"]').forEach((img) => {
        const m = /#flxg-file-(\d+)(?:-(\w+))?$/.exec(img.src);
        if (!m) return;
        const key = m[1] + (m[2] ?? '');
        img.dataset.flxgBlob = key;
        const done = urls.get(key);
        if (done) return void (img.src = done);
        urls.set(key, '');
        c.e.api.file(+m[1]!, m[2]).then((b) => {
          const u = URL.createObjectURL(b);
          urls.set(key, u);
          el.querySelectorAll<HTMLImageElement>(`img[data-flxg-blob="${key}"]`).forEach((x) => (x.src = u));
        }, () => {});
      });
    };
    const mo = new MutationObserver(fix);
    mo.observe(el, { subtree: true, childList: true, attributes: true, attributeFilter: ['src'] });
    fix();
    return () => {
      mo.disconnect();
      urls.forEach((u) => u && URL.revokeObjectURL(u));
    };
  }, [root, on, c]);
}

/** Doküman sayfası: #/doc/<id>[/p/<sayfa>]. */
export function DocPage({ Top, toggleSide }: { Top: (p: { title: ReactNode; extra?: ReactNode }) => ReactNode; toggleSide: () => void }) {
  const c = useYtg();
  const host = useHost();
  const main = useRef<HTMLDivElement>(null);
  const r = c.e.S.route;
  cur.c = c;
  cur.host = host;
  cur.toggleSide = toggleSide;
  useBlobImages(main, host.mode !== 'wp' || !!host.blobMedia);
  useEffect(() => {
    let live = true;
    load(host).then(
      (Y) => {
        if (live && main.current) void Y.showDoc(main.current, r.id, r.pg ?? 0);
      },
      (er: unknown) => c.ui.fail(er),
    );
    return () => void (live = false);
  }, [r.id, r.pg]); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => () => window.FLXG?.leaveDoc?.(), []);
  // Uzaktan (asistan) değişince hemen eşitle.
  useEffect(() => {
    const off = c.e.on('doc-remote', () => window.FLXG?.docSyncNow?.());
    return () => void off();
  }, [c]);
  // İçeriği çekirdek kurar (React çocuk yok); yüklenene kadar WP ile aynı iskelet.
  useLayoutEffect(() => {
    const el = main.current;
    if (el && !el.firstChild) el.innerHTML = '<div class="flxg-content"><div style="padding:24px;display:flex;flex-direction:column;gap:14px">' + ['35%', '80%', '72%', '86%'].map((w, i) => `<span class="flxg-sk" style="display:block;width:${w};height:${i ? 16 : 24}px"></span>`).join('') + '</div></div>';
  }, []);
  void Top;
  return <div ref={main} className="flxg-doc-host" style={{ display: 'contents' }} />;
}

/** Kenar menüden: yeni doküman, doküman menüsü (çekirdek gerekirse önce yüklenir). */
export function newDoc(c: Ctx, space: number, folder: number) {
  cur.c = c;
  if (!cur.host) cur.host = { mode: 'app' } as ModuleHost;
  load(cur.host).then((Y) => Y.newDoc(space, folder), c.ui.fail);
}
export function docMenu(c: Ctx, anchor: HTMLElement, d: FlxgDocRef) {
  cur.c = c;
  if (!cur.host) cur.host = { mode: 'app' } as ModuleHost;
  load(cur.host).then((Y) => Y.docMenu(anchor, d), c.ui.fail);
}
/** Ekran açılınca köprüye ortam bilgisini verir (doküman sayfası açılmadan menü kullanılabilsin). */
export function useDocBridge(toggleSide: () => void) {
  const c = useYtg();
  const host = useHost();
  cur.c = c;
  cur.host = host;
  cur.toggleSide = toggleSide;
}
