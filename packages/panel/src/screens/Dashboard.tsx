import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Eye, EyeOff, GripVertical, Pencil, RefreshCw } from 'lucide-react';
import { arrangeCards, layoutStore, moveItem, type DashboardCard } from '@fluxwp/core';
import { Icon } from '@fluxwp/ui';
import { useActiveAccount, useApi, useLayoutPrefs, useMe } from '../state/hooks';
import { useRoute } from '../state/route';

export function Dashboard() {
  const account = useActiveAccount()!;
  const { t } = useTranslation();
  const api = useApi(account);
  const me = useMe(account).data;
  const prefs = useLayoutPrefs(account);
  const go = useRoute((s) => s.go);
  const q = useQuery({ queryKey: [account.id, 'dashboard'], queryFn: api.dashboard, refetchInterval: 60_000 });
  const [edit, setEdit] = useState(false);
  const [drag, setDrag] = useState<number | null>(null);

  const cards = arrangeCards(q.data?.cards ?? [], prefs);
  const shown = edit ? cards : cards.filter((c) => !c.hidden);
  const lists = (q.data?.lists ?? []).slice().sort((a, b) => (a.order ?? 50) - (b.order ?? 50));

  const open = (c: DashboardCard) => {
    if (edit) return;
    if (c.open?.startsWith('mod:')) go({ kind: 'nav', id: c.open });
  };
  const toggle = (id: string) => {
    const h = new Set(prefs?.cardHidden ?? []);
    h.has(id) ? h.delete(id) : h.add(id);
    void layoutStore.getState().set(account.id, { cardHidden: [...h] });
  };
  const drop = (to: number) => {
    if (drag === null) return;
    void layoutStore.getState().set(account.id, { cardOrder: moveItem(cards.map((c) => c.id), drag, to) });
    setDrag(null);
  };

  const hour = new Date().getHours();
  const greet = hour < 12 ? 'dashboard.morning' : hour < 18 ? 'dashboard.afternoon' : 'dashboard.evening';

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>{t(greet, { name: me?.user.name.split(' ')[0] ?? '' })}</h1>
          <p className="muted">{me?.site.name}</p>
        </div>
        <div className="row">
          <button className="icon-btn" onClick={() => q.refetch()} aria-label={t('common.refresh')}>
            <RefreshCw size={16} className={q.isFetching ? 'spin' : ''} />
          </button>
          <button className={edit ? 'btn-primary small' : 'btn-ghost small'} onClick={() => setEdit((e) => !e)}>
            {edit ? t('common.done') : (<><Pencil size={14} /> {t('dashboard.customize')}</>)}
          </button>
        </div>
      </header>

      {q.isError && <p className="error">{t('errors.server')}</p>}
      {q.isLoading && <div className="cards">{Array.from({ length: 4 }, (_, i) => <div key={i} className="card skeleton" />)}</div>}

      <div className="cards">
        {shown.map((c, i) => (
          <div
            key={c.id}
            className={`card tone-${c.tone || 'none'}${c.hidden ? ' is-hidden' : ''}${c.open && !edit ? ' clickable' : ''}`}
            onClick={() => open(c)}
            draggable={edit}
            onDragStart={() => setDrag(i)}
            onDragOver={(e) => edit && e.preventDefault()}
            onDrop={() => drop(i)}
          >
            <div className="card-top">
              <span className="card-icon"><Icon name={c.icon} size={16} /></span>
              <span className="muted grow">{c.title}</span>
              {edit && (
                <>
                  <button className="icon-btn" onClick={() => toggle(c.id)} aria-label={c.hidden ? t('nav.show') : t('nav.hide')}>
                    {c.hidden ? <EyeOff size={14} /> : <Eye size={14} />}
                  </button>
                  <GripVertical size={14} className="muted grip" />
                </>
              )}
            </div>
            <div className="card-value">{c.value}</div>
            {c.sub && <div className="card-sub muted">{c.sub}</div>}
          </div>
        ))}
      </div>

      <div className="lists">
        {lists.map((l) => (
          <section key={l.id} className="panel">
            <h2><Icon name={l.icon} size={16} /> {l.title}</h2>
            {l.items.length === 0 && <p className="muted small">{t('common.empty')}</p>}
            <ul>
              {l.items.map((it, i) => (
                <li key={i}>
                  <div className="grow">
                    <div>{it.title}</div>
                    {it.sub && <small className="muted">{it.sub}</small>}
                  </div>
                  {it.meta && <small className="muted">{it.meta}</small>}
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}
