import { useEffect, useMemo, useState } from 'react';
import { useOverlay } from '@fluxwp/ui';
import { useTranslation } from 'react-i18next';
import { CornerDownLeft } from 'lucide-react';
import type { NavItem } from '@fluxwp/core';
import { Icon } from '@fluxwp/ui';
import { useRoute, type Route } from '../state/route';

interface Entry { key: string; title: string; hint?: string; icon?: string; slug?: string; route: Route }

const fold = (s: string) => s.toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');

export function CommandPalette({ nav, onClose }: { nav: NavItem[]; onClose: () => void }) {
  const { t } = useTranslation();
  const go = useRoute((s) => s.go);
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(0);
  useEffect(() => useOverlay.getState().pushOverlay(), []);

  const all = useMemo<Entry[]>(() => {
    const out: Entry[] = [];
    for (const n of nav.filter((x) => !x.hidden)) {
      out.push({ key: n.id, title: n.title, icon: n.icon, slug: n.slug, route: { kind: 'nav', id: n.id } });
      for (const c of n.children) if (c.title !== n.title) out.push({ key: n.id + '/' + c.id, title: c.title, hint: n.title, icon: n.icon, slug: n.slug, route: { kind: 'nav', id: n.id, sub: c.id } });
    }
    out.push({ key: 'settings', title: t('settings.title'), icon: 'settings', route: { kind: 'settings' } });
    return out;
  }, [nav, t]);

  const list = useMemo(() => {
    const f = fold(q.trim());
    return f ? all.filter((e) => fold(e.title + ' ' + (e.hint ?? '')).includes(f)) : all;
  }, [all, q]);

  useEffect(() => setSel(0), [q]);

  const choose = (e?: Entry) => {
    if (!e) return;
    go(e.route);
    onClose();
  };

  return (
    <div className="overlay" onMouseDown={onClose}>
      <div className="palette" onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-label={t('nav.search')}>
        <input
          autoFocus
          value={q}
          placeholder={t('palette.placeholder')}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowDown') (e.preventDefault(), setSel((s) => Math.min(s + 1, list.length - 1)));
            if (e.key === 'ArrowUp') (e.preventDefault(), setSel((s) => Math.max(s - 1, 0)));
            if (e.key === 'Enter') choose(list[sel]);
          }}
        />
        <div className="palette-list">
          {list.length === 0 && <p className="muted pad">{t('palette.empty')}</p>}
          {list.map((e, i) => (
            <button key={e.key} className={'palette-item' + (i === sel ? ' sel' : '')} onMouseEnter={() => setSel(i)} onClick={() => choose(e)}>
              <Icon name={e.icon} slug={e.slug} size={16} />
              <span className="grow">{e.title}</span>
              {e.hint && <span className="muted small">{e.hint}</span>}
              {i === sel && <CornerDownLeft size={14} className="muted" />}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
