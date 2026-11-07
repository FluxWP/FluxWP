import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { resolveTarget, useResolveEnv } from '@fluxwp/runtime';
import { useNotes } from '../shell/bridges';
import { useRoute } from '../state/route';

export function Notifications() {
  const { t } = useTranslation();
  const items = useNotes((s) => s.items);
  const env = useResolveEnv();
  const go = useRoute((s) => s.go);

  useEffect(() => {
    const on = (e: Event) => {
      const detail = (e as CustomEvent<{ target?: string; params?: Record<string, string> }>).detail;
      if (!detail?.target) return;
      const route = resolveTarget(detail.target, env, detail.params);
      if (route.kind !== 'none') go(route);
    };
    window.addEventListener('flux-notify', on);
    return () => window.removeEventListener('flux-notify', on);
  }, [env, go]);

  return (
    <div className="page narrow">
      <header className="page-head"><h1>{t('desktop.notifications')}</h1></header>
      {items.length === 0 && <p className="muted">{t('desktop.notificationsEmpty')}</p>}
      <div className="stack">
        {items.map((ev, i) => (
          <button
            key={`${ev.event}-${ev.at ?? i}`}
            className="card clickable"
            onClick={() => {
              const route = resolveTarget(ev.target ?? 'notifications', env, ev.params);
              if (route.kind !== 'none' && route.kind !== 'url') go(route);
            }}
          >
            <strong>{ev.title}</strong>
            {ev.body && <span className="muted">{ev.body}</span>}
          </button>
        ))}
      </div>
    </div>
  );
}
