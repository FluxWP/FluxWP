// Sol menü düzeni, WP'deki Flux Admin menüsü gibi: üstte sabit kutucuklar, altta gruplar.
// Sunucu (Flux App → Flux Admin) kişinin düzenini gönderirse o, yoksa Flux Admin'in varsayılanları.
import type { MenuLayout, NavItem } from '@fluxwp/core';

/** Flux Admin flxa_default_groups() ile aynı. Etiketler i18n: nav.groups.<id>. */
const DEFAULT_GROUPS: { id: string; items: string[] }[] = [
  { id: 'general', items: ['index.php'] },
  { id: 'comms', items: ['flx-chat', 'flxi-posta', 'flx-tasks', 'flxi'] },
  { id: 'donate', items: ['flxb-dashboard', 'flxs', 'Emsfb', 'woocommerce', 'wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'flx-wc-bagis', 'edit.php?post_type=product'] },
  { id: 'content', items: ['edit.php?post_type=page', 'edit.php', 'edit.php?post_type=proje', 'edit.php?post_type=etkinlik', 'upload.php', 'edit-comments.php', 'flx-studio'] },
  { id: 'growth', items: ['woocommerce-marketing', 'flx-social', 'flxseo', 'flx-analytics', 'wc-admin&path=/analytics/overview', 'googlesitekit-dashboard'] },
  { id: 'screens', items: ['flx-tv-board', 'flx-tv-remote', 'flxapp-settings'] },
  { id: 'system', items: ['flx-updater', 'flx-backup', 'flx-speed', 'flx-ai', 'flx-roles', 'plugins.php', 'themes.php', 'users.php', 'options-general.php', 'tools.php', 'hostinger', 'litespeed'] },
];
const DEFAULT_PINS = ['index.php', 'flx-chat', 'flx-tasks', 'flxb-dashboard'];

export interface MenuGroup { id: string; label?: string; collapsed?: boolean; items: NavItem[] }

export function buildMenu(nav: NavItem[], layout?: MenuLayout | null): { pins: NavItem[]; groups: MenuGroup[] } {
  const visible = nav.filter((n) => !n.hidden);
  const bySlug = new Map<string, NavItem>();
  for (const n of visible) if (n.slug) bySlug.set(n.slug, n);
  const used = new Set<string>();
  const take = (slug: string) => {
    const n = bySlug.get(slug);
    if (!n || used.has(n.id)) return null;
    used.add(n.id);
    return n;
  };

  const pins = (layout?.pins ?? DEFAULT_PINS).map(take).filter((n): n is NavItem => !!n);
  const src = layout?.groups?.length ? layout.groups : DEFAULT_GROUPS;
  const groups: MenuGroup[] = src.map((g) => ({ id: g.id, label: 'label' in g ? (g as { label: string }).label : undefined, collapsed: 'collapsed' in g ? !!(g as { collapsed?: boolean }).collapsed : false, items: g.items.map(take).filter((n): n is NavItem => !!n) }));

  // Menüsüz yerel modüller (ör. Onay bekleyenler) ilk grubun başına; kalanlar "Diğer"e.
  const orphans = visible.filter((n) => !used.has(n.id));
  const modules = orphans.filter((n) => !n.slug);
  const rest = orphans.filter((n) => n.slug);
  if (modules.length) {
    if (!groups[0]) groups.push({ id: 'general', items: [] });
    groups[0]!.items.unshift(...modules);
  }
  if (rest.length) groups.push({ id: 'other', items: rest });
  return { pins, groups: groups.filter((g) => g.items.length) };
}
