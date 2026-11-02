import { describe, expect, it } from 'vitest';
import { adminPath, arrangeCards, mergeLayout, moveItem } from './merge';
import type { MenuItem } from '../api/types';

const A = 'https://x.org/wp-admin/';
const menu: MenuItem[] = [
  { id: 'dashboard', slug: 'index.php', title: 'Başlangıç', url: A + 'index.php', icon: 'dashicons-dashboard', items: [] },
  { id: 'flx-tasks', slug: 'flx-tasks', title: 'Görevler', url: A + 'admin.php?page=flx-tasks', icon: 'dashicons-yes', items: [] },
  { id: 'edit.php', slug: 'edit.php', title: 'Yazılar', url: A + 'edit.php', icon: '', items: [{ slug: 'edit.php', title: 'Tüm', url: A + 'edit.php' }] },
];
const modules = {
  'core-dashboard': { title: 'D', menu: 'index.php' },
  'flxg-tasks': { title: 'Görevler', menu: 'flx-tasks', icon: 'task' },
  'flxb-review': { title: 'Onay', icon: 'check', order: 5 },
  'flxx-hidden': { title: 'Yazılmamış' },
};

describe('mergeLayout', () => {
  it('menüye bağlı ve ekranı olan modülü native yapar', () => {
    const nav = mergeLayout({ menu, modules, implemented: new Set(['core-dashboard', 'flxg-tasks']) });
    expect(nav.map((n) => [n.id, n.kind])).toEqual([
      ['dashboard', 'native'],
      ['flx-tasks', 'native'],
      ['edit.php', 'web'],
    ]);
    expect(nav[1]!.icon).toBe('task');
    expect(nav[2]!.children[0]!.path).toBe('edit.php');
  });

  it('menüsüz modül yalnız ekranı varsa ayrı öğe olur', () => {
    const nav = mergeLayout({ menu, modules, implemented: new Set(['flxb-review']) });
    expect(nav.map((n) => n.id)).toContain('mod:flxb-review');
    expect(nav.map((n) => n.id)).not.toContain('mod:flxx-hidden');
  });

  it('kullanıcı sırası ve gizleme site varsayılanını ezer', () => {
    const nav = mergeLayout({
      menu,
      modules,
      implemented: new Set(),
      site: { order: ['edit.php'], hidden: ['flx-tasks'] },
      user: { hidden: [] },
    });
    expect(nav[0]!.id).toBe('edit.php');
    expect(nav.find((n) => n.id === 'flx-tasks')!.hidden).toBe(false);
  });

  it('forceWeb yerel ekranı web görünümüne düşürür', () => {
    const nav = mergeLayout({ menu, modules, implemented: new Set(['flxg-tasks']), user: { forceWeb: ['flxg-tasks'] } });
    expect(nav.find((n) => n.id === 'flx-tasks')!.kind).toBe('web');
  });
});

describe('yardımcılar', () => {
  it('adminPath', () => {
    expect(adminPath(A + 'admin.php?page=x')).toBe('admin.php?page=x');
    expect(adminPath(A)).toBe('index.php');
  });
  it('moveItem', () => {
    expect(moveItem(['a', 'b', 'c'], 0, 2)).toEqual(['b', 'c', 'a']);
  });
  it('arrangeCards', () => {
    const out = arrangeCards([{ id: 'a', order: 2 }, { id: 'b', order: 1 }, { id: 'c' }], { cardOrder: ['c'], cardHidden: ['a'] });
    expect(out.map((c) => c.id)).toEqual(['c', 'b', 'a']);
    expect(out[2]!.hidden).toBe(true);
  });
});
