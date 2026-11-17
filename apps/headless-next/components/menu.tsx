import Link from 'next/link';
import type { Menu, MenuItem } from '@/lib/types';

/** Menu items come flat; children point at their parent. */
function children(items: MenuItem[], parent: string | null): MenuItem[] {
  return items.filter((i) => (i.parentId ?? null) === parent).sort((a, b) => a.order - b.order);
}

function Item({ item, items }: { item: MenuItem; items: MenuItem[] }) {
  const sub = children(items, item.id);
  const link = item.path && !item.external ? (
    <Link href={item.path} prefetch>{item.label}</Link>
  ) : (
    <a href={item.url} target={item.target ?? undefined} rel={item.external ? 'noopener' : undefined}>{item.label}</a>
  );
  return (
    <li>
      {link}
      {sub.length > 0 && (
        <ul>
          {sub.map((s) => (
            <Item key={s.id} item={s} items={items} />
          ))}
        </ul>
      )}
    </li>
  );
}

export function MenuList({ menu, className }: { menu: Menu | undefined; className?: string }) {
  if (!menu) return null;
  return (
    <ul className={className}>
      {children(menu.items, null).map((i) => (
        <Item key={i.id} item={i} items={menu.items} />
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
