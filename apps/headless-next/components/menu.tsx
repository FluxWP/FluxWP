import Link from 'next/link';
import type { Menu, MenuItem } from '@/lib/types';

/** Menu items come flat; children point at their parent. */
function children(items: MenuItem[], parent: string | null): MenuItem[] {
  return items.filter((i) => (i.parentId ?? null) === parent).sort((a, b) => a.order - b.order);
}

function ItemLink({ item }: { item: MenuItem }) {
  return item.path && !item.external ? (
    <Link href={item.path} prefetch>{item.label}</Link>
  ) : (
    <a href={item.url} target={item.target ?? undefined} rel={item.external ? 'noopener' : undefined}>{item.label}</a>
  );
}

/**
 * An item with children opens its sub-menu with a hidden checkbox: on hover or focus in the desktop
 * header, on tap on phones (see globals.css). No client JavaScript.
 */
function Item({ item, items, scope }: { item: MenuItem; items: MenuItem[]; scope: string }) {
  const sub = children(items, item.id);
  if (!sub.length) {
    return (
      <li>
        <ItemLink item={item} />
      </li>
    );
  }
  const id = `sub-${scope}-${item.id}`;
  // The parent's own page stays reachable as the first link of its sub-menu, unless a child already
  // points to the same address.
  const self = item.path && !sub.some((s) => s.path === item.path);
  return (
    <li className="has-sub">
      <input type="checkbox" id={id} className="sub-toggle" />
      <label htmlFor={id} className="sub-label">{item.label}</label>
      <ul>
        {self && (
          <li>
            <ItemLink item={item} />
          </li>
        )}
        {sub.map((s) => (
          <Item key={s.id} item={s} items={items} scope={scope} />
        ))}
      </ul>
    </li>
  );
}

export function MenuList({ menu, className }: { menu: Menu | undefined; className?: string }) {
  if (!menu) return null;
  return (
    <ul className={className}>
      {children(menu.items, null).map((i) => (
        <Item key={i.id} item={i} items={menu.items} scope={menu.location} />
      ))}
    </ul>
  );
}

/** First menu found in these places (menu locations, then header/footer navigation blocks). */
export function pick(menus: Menu[], places: string[]): Menu | undefined {
  for (const p of places) {
    const m = menus.find((x) => x.location === p && x.items.length);
    if (m) return m;
  }
  return undefined;
}

/** Every footer menu: the footer location and the footer's navigation blocks (footer, footer-2 …). */
export function footerMenus(menus: Menu[]): Menu[] {
  return menus.filter((m) => /^footer(-\d+)?$/.test(m.location) && m.items.length);
}
