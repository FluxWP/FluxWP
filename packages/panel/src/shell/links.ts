// Sidebar rows. Config shell (desktop sidebar / drawer) or, without /config, today's /me menu.
import type { NavItem } from '@fluxwp/core';
import type { NavEntry } from '@fluxwp/runtime';
import type { Route } from '../state/route';

export interface SideChild {
  id: string;
  title: string;
  route: Route;
}

export interface SideLink {
  id: string;
  title: string;
  icon: string;
  slug?: string;
  iconUrl?: string;
  badge?: number | string;
  route: Route;
  children: SideChild[];
}

export interface SideGroup {
  id: string;
  label?: string;
  collapsed?: boolean;
  items: SideLink[];
}

type TFn = (key: string, vars?: Record<string, unknown>) => string;

export const labelOf = (label: string, t: TFn) => (label.startsWith('$t:') ? t(label.slice(3)) : label);

export function sameRoute(a: Route, b: Route): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}

export function fromEntry(e: NavEntry, t: TFn): SideLink {
  return { id: e.id, title: labelOf(e.label, t), icon: e.icon, slug: e.slug, iconUrl: e.iconUrl, badge: e.badge, route: e.route, children: [] };
}

export function fromNav(n: NavItem): SideLink {
  const route: Route = n.kind === 'native'
    ? { kind: 'native', component: n.moduleId === 'core-dashboard' ? 'dashboard' : (n.moduleId ?? n.id), params: {} }
    : { kind: 'web', path: n.path, title: n.title };
  return {
    id: n.id,
    title: n.title,
    icon: n.icon,
    slug: n.slug,
    iconUrl: n.iconUrl,
    badge: n.badge,
    route,
    children: n.children.filter((c) => c.title !== n.title).map((c) => ({ id: c.id, title: c.title, route: { kind: 'web', path: c.path, title: c.title } })),
  };
}

export function activeOf(pins: SideLink[], groups: SideGroup[], route: Route): { id: string | null; child: string | null } {
  for (const link of [...pins, ...groups.flatMap((g) => g.items)]) {
    if (sameRoute(link.route, route)) return { id: link.id, child: null };
    const child = link.children.find((c) => sameRoute(c.route, route));
    if (child) return { id: link.id, child: child.id };
    if (route.kind === 'manifest' && link.route.kind === 'manifest' && route.module === link.route.module) return { id: link.id, child: null };
    if (route.kind === 'native' && link.route.kind === 'native' && route.component === link.route.component) return { id: link.id, child: null };
  }
  return { id: null, child: null };
}
