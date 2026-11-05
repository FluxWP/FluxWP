import { useEffect, useMemo, useState } from 'react';
import { useOverlay } from '@fluxwp/ui';
import { useTranslation } from 'react-i18next';
import { CornerDownLeft } from 'lucide-react';
import { parsePage, resolveTarget, runRequest, sourceRequest, useApi, useManifest, useResolveEnv } from '@fluxwp/runtime';
import { render, value } from '@fluxwp/manifest';
import { Icon } from '@fluxwp/ui';
import type { Route } from '../state/route';
import type { SideLink } from '../shell/links';

interface Entry { key: string; title: string; hint?: string; icon?: string; slug?: string; route: Route }

const fold = (s: string) => s.toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');

export function CommandPalette({ links, commands, onClose, onOpen }: { links: SideLink[]; commands: SideLink[]; onClose: () => void; onOpen: (route: Route) => void }) {
  const { t } = useTranslation();
  const api = useApi();
  const env = useResolveEnv();
  const manifests = useManifest().data?.modules;
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(0);
  const [hits, setHits] = useState<Entry[]>([]);
  useEffect(() => useOverlay.getState().pushOverlay(), []);

  const all = useMemo<Entry[]>(() => {
    const out: Entry[] = [];
    const push = (link: SideLink, hint?: string) => {
      out.push({ key: hint ? hint + link.id : link.id, title: link.title, hint, icon: link.icon, slug: link.slug, route: link.route });
      for (const c of link.children) out.push({ key: link.id + '/' + c.id, title: c.title, hint: link.title, icon: link.icon, slug: link.slug, route: c.route });
    };
    for (const link of commands) push(link, t('palette.placeholder'));
    for (const link of links) push(link);
    out.push({ key: 'settings', title: t('settings.title'), icon: 'settings', route: { kind: 'shell', name: 'settings' } });
    out.push({ key: 'notifications', title: t('desktop.notifications'), icon: 'bell', route: { kind: 'shell', name: 'notifications' } });
    return out;
  }, [links, commands, t]);

  const local = useMemo(() => {
    const f = fold(q.trim());
    return f ? all.filter((e) => fold(e.title + ' ' + (e.hint ?? '')).includes(f)) : all;
  }, [all, q]);

  useEffect(() => {
    const query = q.trim();
    if (query.length < 2 || !manifests) {
      setHits([]);
      return;
    }
    let dead = false;
    const timer = setTimeout(() => {
      void (async () => {
        const found: Entry[] = [];
        for (const m of Object.values(manifests)) {
          if (!m.search) continue;
          const src = m.sources[m.search.source];
          if (!src) continue;
          try {
            const req = sourceRequest(src, {}, { search: { param: m.search.param, value: query } });
            const page = parsePage(src, await runRequest(api, req), 1);
            for (const item of page.items.slice(0, 5)) {
              const ctx = { scope: item, item };
              const title = render(m.search.item.title, ctx);
              if (!title) continue;
              const route = m.search.onPress
                ? resolveTarget(render(m.search.onPress.target, ctx), env, Object.fromEntries(Object.entries(m.search.onPress.params ?? {}).map(([k, v]) => [k, String(value(v, ctx) ?? '')])))
                : resolveTarget(`module:${m.id}`, env);
              if (route.kind === 'none') continue;
              found.push({ key: m.id + title, title, hint: m.title, icon: m.icon, route });
            }
          } catch {
            /* one source failing does not hide the rest */
          }
        }
        if (!dead) setHits(found);
      })();
    }, 250);
    return () => {
      dead = true;
      clearTimeout(timer);
    };
  }, [q, manifests, api, env]);

  const list = q.trim().length >= 2 ? [...hits, ...local] : local;
  useEffect(() => setSel(0), [q]);

  const choose = (e?: Entry) => {
    if (!e) return;
    onOpen(e.route);
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
            if (e.key === 'ArrowDown') { e.preventDefault(); setSel((s) => Math.min(s + 1, Math.max(list.length - 1, 0))); }
            if (e.key === 'ArrowUp') { e.preventDefault(); setSel((s) => Math.max(s - 1, 0)); }
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
