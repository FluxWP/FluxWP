// Menü birleştirme: sunucu menüsü (rol bazlı) + yerel modüller + site varsayılanı + kullanıcı tercihi.
import type { MenuItem, ModuleInfo } from '../api/types';

export interface LayoutPrefs {
  /** Üst menü id sırası; listede olmayanlar sunucu sırasıyla sona eklenir. */
  order?: string[];
  hidden?: string[];
  pinned?: string[];
  /** Yerel ekranı olsa da web görünümünde açılacak modüller. */
  forceWeb?: string[];
  /** Dashboard kart sırası ve gizlenenler (kart id). */
  cardOrder?: string[];
  cardHidden?: string[];
}

export interface NavItem {
  id: string;
  title: string;
  icon: string;
  /** WP menü slug'ı (ikon yoksa slug'dan ikon seçilir). */
  slug?: string;
  iconUrl?: string;
  badge?: number | string;
  /** native: uygulamanın kendi ekranı, web: panel sayfası. */
  kind: 'native' | 'web';
  moduleId?: string;
  /** wp-admin göreli yol (session ucu için), ör. admin.php?page=flx-tasks */
  path: string;
  url: string;
  children: { id: string; title: string; path: string; url: string; badge?: number | string }[];
  hidden: boolean;
  pinned: boolean;
}

/** Tam admin adresinden wp-admin göreli yol. */
export function adminPath(url: string): string {
  const i = url.indexOf('/wp-admin/');
  return i < 0 ? url : url.slice(i + 10) || 'index.php';
}

const pick = <K extends keyof LayoutPrefs>(k: K, user?: LayoutPrefs, site?: LayoutPrefs): LayoutPrefs[K] =>
  user?.[k] !== undefined ? user[k] : site?.[k];

export function mergeLayout(input: {
  menu: MenuItem[];
  modules: Record<string, ModuleInfo>;
  /** Panelde ekranı yazılmış modül id'leri. */
  implemented: ReadonlySet<string>;
  site?: LayoutPrefs;
  user?: LayoutPrefs;
}): NavItem[] {
  const { menu, modules, implemented } = input;
  const order = pick('order', input.user, input.site) ?? [];
  const hidden = new Set(pick('hidden', input.user, input.site) ?? []);
  const pinned = new Set(pick('pinned', input.user, input.site) ?? []);
  const forceWeb = new Set(pick('forceWeb', input.user, input.site) ?? []);

  const bySlug = new Map<string, string>();
  for (const [id, m] of Object.entries(modules)) if (m.menu) bySlug.set(m.menu, id);

  const native = (modId: string | undefined) => !!modId && implemented.has(modId) && !forceWeb.has(modId);

  const items: NavItem[] = menu.map((m) => {
    const modId = bySlug.get(m.slug);
    return {
      id: m.id || m.slug,
      title: m.title,
      icon: (modId && modules[modId]?.icon) || m.icon,
      slug: m.slug,
      iconUrl: m.icon_url,
      badge: m.badge,
      kind: native(modId) ? 'native' : 'web',
      moduleId: modId,
      path: adminPath(m.url),
      url: m.url,
      children: (m.items ?? []).map((c) => ({ id: c.slug, title: c.title, path: adminPath(c.url), url: c.url, badge: c.badge })),
      hidden: false,
      pinned: false,
    };
  });

  // Menüye bağlı olmayan modüller kendi öğesi olur (yalnız yerel ekranı varsa); ilk öğenin (Başlangıç) hemen altına.
  const used = new Set(items.map((i) => i.moduleId).filter(Boolean));
  Object.entries(modules)
    .filter(([id, m]) => !m.menu && !used.has(id) && implemented.has(id))
    .sort(([, a], [, b]) => (a.order ?? 50) - (b.order ?? 50))
    .reverse()
    .forEach(([id, m]) =>
      items.splice(Math.min(1, items.length), 0, { id: `mod:${id}`, title: m.title, icon: m.icon ?? '', kind: 'native', moduleId: id, path: '', url: '', children: [], hidden: false, pinned: false }),
    );

  const rank = new Map(order.map((id, i) => [id, i]));
  const base = new Map(items.map((it, i) => [it.id, i]));
  return items
    .map((it) => ({ ...it, hidden: hidden.has(it.id), pinned: pinned.has(it.id) }))
    .sort((a, b) => {
      const ra = rank.get(a.id) ?? Infinity;
      const rb = rank.get(b.id) ?? Infinity;
      return ra !== rb ? ra - rb : (base.get(a.id) ?? 0) - (base.get(b.id) ?? 0);
    });
}

/** Sürükle bırak sonrası yeni sıra. */
export function moveItem(ids: string[], from: number, to: number): string[] {
  const next = ids.slice();
  const [x] = next.splice(from, 1);
  if (x !== undefined) next.splice(Math.max(0, Math.min(to, next.length)), 0, x);
  return next;
}

/** Kartları kullanıcı sırasına göre dizer; gizlenenleri işaretler. */
export function arrangeCards<T extends { id: string; order?: number }>(cards: T[], prefs?: LayoutPrefs): (T & { hidden: boolean })[] {
  const rank = new Map((prefs?.cardOrder ?? []).map((id, i) => [id, i]));
  const hidden = new Set(prefs?.cardHidden ?? []);
  return cards
    .slice()
    .sort((a, b) => {
      const ra = rank.get(a.id) ?? Infinity;
      const rb = rank.get(b.id) ?? Infinity;
      return ra !== rb ? ra - rb : (a.order ?? 50) - (b.order ?? 50);
    })
    .map((c) => ({ ...c, hidden: hidden.has(c.id) }));
}
