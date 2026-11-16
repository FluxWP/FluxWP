// Calendar: month grid and an agenda list. Drag a scheduled post to another day to move it; click a day to add a post.
import { useMemo, useState } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSocialPostUpdate, FlxSocialPosts } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { NetDot, errorText, excerpt, payloadError, postTime, useSocial, type Post } from './common';

const DAY = 86400;
const SHOWN = 3;
const MOVABLE = new Set(['DRAFT', 'SCHEDULED', 'PENDING']);

export function CalendarPage() {
  const s = useSocial();
  const { t, boot } = s;
  const cfg = boot.flxSocialConfig;
  const tz = cfg.tzOffset;
  const nowLocal = new Date((Math.floor(Date.now() / 1000) + tz) * 1000);
  const [cur, setCur] = useState({ y: nowLocal.getUTCFullYear(), m: nowLocal.getUTCMonth() });
  const [view, setView] = useState<'month' | 'list'>('month');
  const [drag, setDrag] = useState<string | null>(null);
  const [over, setOver] = useState<number | null>(null);

  // First cell of the grid: the week start on or before the 1st of the month (local days, stored as UTC midnight).
  const grid = useMemo(() => {
    const first = Date.UTC(cur.y, cur.m, 1) / 1000;
    const dow = new Date(first * 1000).getUTCDay();
    const back = (dow - cfg.weekStart + 7) % 7;
    const start = first - back * DAY;
    const days = Date.UTC(cur.y, cur.m + 1, 0) / 1000;
    const total = Math.ceil((back + (days - first) / DAY + 1) / 7) * 7;
    return { start, total, first };
  }, [cur, cfg.weekStart]);

  const from = grid.start - tz;
  const to = grid.start + grid.total * DAY - tz - 1;
  const list = useFlxQuery(FlxSocialPosts, { first: 200, from, to, ascending: true }, { placeholderData: (p) => p });
  const move = useFlxMutation(FlxSocialPostUpdate);

  const byDay = useMemo(() => {
    const m = new Map<number, Post[]>();
    for (const p of list.data?.flxSocialPosts.nodes ?? []) {
      const ts = postTime(p);
      if (!ts) continue;
      const key = Math.floor((ts + tz) / DAY) * DAY;
      m.set(key, [...(m.get(key) ?? []), p]);
    }
    return m;
  }, [list.data, tz]);

  const todayKey = Math.floor((Math.floor(Date.now() / 1000) + tz) / DAY) * DAY;
  const monthLabel = new Intl.DateTimeFormat(s.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(grid.first * 1000));
  const dayName = (i: number) => new Intl.DateTimeFormat(s.locale, { weekday: 'short', timeZone: 'UTC' }).format(new Date((Date.UTC(2024, 0, 7 + ((cfg.weekStart + i) % 7))) ));
  const step = (d: number) => setCur((c) => {
    const n = new Date(Date.UTC(c.y, c.m + d, 1));
    return { y: n.getUTCFullYear(), m: n.getUTCMonth() };
  });

  const dropOn = (dayKey: number) => {
    const p = (list.data?.flxSocialPosts.nodes ?? []).find((x) => x.id === drag);
    setDrag(null);
    setOver(null);
    if (!p || !MOVABLE.has(p.status)) return;
    const old = p.scheduledAt?.timestamp ?? 0;
    const secs = old ? (((old + tz) % DAY) + DAY) % DAY : 9 * 3600;
    const next = dayKey + secs - tz;
    if (old && Math.floor((old + tz) / DAY) * DAY === dayKey) return;
    if (next < Math.floor(Date.now() / 1000) - 60 && p.status !== 'DRAFT') {
      s.toast(t('Pick a time in the future.'), 'error');
      return;
    }
    move.mutate(
      { input: { id: p.id, scheduledAt: next } },
      {
        onSuccess: (r) => {
          const err = payloadError(r.flxSocialPostUpdate?.errors);
          if (err) s.toast(err, 'error');
          else {
            s.toast(t('Post moved to {date}.', { date: s.fmt(next, 'date') }));
            s.refreshPosts();
          }
        },
        onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
      },
    );
  };

  const addAt = (dayKey: number) => {
    const at = dayKey + 9 * 3600 - tz;
    s.edit(null, { scheduledAt: at > Math.floor(Date.now() / 1000) ? at : undefined });
  };

  const days = Array.from({ length: grid.total }, (_, i) => grid.start + i * DAY);

  return (
    <div className="flxsm-cal">
      <div className="flxsm-cal-bar">
        <button type="button" className="flxsm-btn" aria-label={t('Previous month')} onClick={() => step(-1)}>
          ‹
        </button>
        <strong className="flxsm-cal-title">{monthLabel}</strong>
        <button type="button" className="flxsm-btn" aria-label={t('Next month')} onClick={() => step(1)}>
          ›
        </button>
        <button type="button" className="flxsm-btn" onClick={() => setCur({ y: nowLocal.getUTCFullYear(), m: nowLocal.getUTCMonth() })}>
          {t('Today')}
        </button>
        <span className="flxsm-grow" />
        <div className="flxsm-seg" role="group">
          <button type="button" className={view === 'month' ? 'is-on' : ''} onClick={() => setView('month')}>
            {t('Month')}
          </button>
          <button type="button" className={view === 'list' ? 'is-on' : ''} onClick={() => setView('list')}>
            {t('List')}
          </button>
        </div>
      </div>
      <p className="flxsm-muted flxsm-tznote">{t('Times are shown in the site time zone ({tz}).', { tz: cfg.timezone })}</p>
      {list.isLoading ? (
        <Loading />
      ) : list.isError ? (
        <div className="flxsm-notice is-error">{errorText(list.error, t('Something went wrong.'))}</div>
      ) : view === 'month' ? (
        <div className="flxsm-grid" role="grid">
          {Array.from({ length: 7 }, (_, i) => (
            <div key={i} className="flxsm-dow" role="columnheader">
              {dayName(i)}
            </div>
          ))}
          {days.map((d) => {
            const dt = new Date(d * 1000);
            const inMonth = dt.getUTCMonth() === cur.m;
            const items = byDay.get(d) ?? [];
            return (
              <div
                key={d}
                role="gridcell"
                className={'flxsm-day' + (inMonth ? '' : ' is-out') + (d === todayKey ? ' is-today' : '') + (over === d ? ' is-over' : '')}
                onDragOver={(e) => {
                  if (drag) {
                    e.preventDefault();
                    setOver(d);
                  }
                }}
                onDragLeave={() => setOver((o) => (o === d ? null : o))}
                onDrop={(e) => {
                  e.preventDefault();
                  dropOn(d);
                }}
              >
                <div className="flxsm-day-head">
                  <span>{dt.getUTCDate()}</span>
                  {d >= todayKey && (
                    <button type="button" className="flxsm-add" aria-label={t('New post')} title={t('New post')} onClick={() => addAt(d)}>
                      +
                    </button>
                  )}
                </div>
                {items.slice(0, SHOWN).map((p) => (
                  <button
                    key={p.id}
                    type="button"
                    draggable={MOVABLE.has(p.status)}
                    onDragStart={() => setDrag(p.id)}
                    onDragEnd={() => {
                      setDrag(null);
                      setOver(null);
                    }}
                    className={'flxsm-chip is-' + p.status.toLowerCase()}
                    onClick={() => s.detail(p)}
                    title={excerpt(p, t)}
                  >
                    <NetDot provider={p.targets[0]?.provider ?? ''} size={14} />
                    <span className="flxsm-chip-t">{s.fmt(postTime(p), 'time')}</span>
                    <span className="flxsm-chip-x">{excerpt(p, t)}</span>
                  </button>
                ))}
                {items.length > SHOWN && (
                  <button
                    type="button"
                    className="flxsm-more-day"
                    onClick={() => {
                      setView('list');
                    }}
                  >
                    {t('+{n} more', { n: items.length - SHOWN })}
                  </button>
                )}
              </div>
            );
          })}
        </div>
      ) : (
        <div className="flxsm-agenda">
          {[...byDay.entries()]
            .sort((a, b) => a[0] - b[0])
            .filter(([d]) => new Date(d * 1000).getUTCMonth() === cur.m)
            .map(([d, items]) => (
              <section key={d} className="flxsm-card">
                <h3>{s.fmt(d - tz, 'day')}</h3>
                {items.map((p) => (
                  <button key={p.id} type="button" className="flxsm-agenda-row" onClick={() => s.detail(p)}>
                    <span className="flxsm-nowrap">{s.fmt(postTime(p), 'time')}</span>
                    <span className="flxsm-nets">
                      {p.targets.map((x) => (
                        <NetDot key={x.id} provider={x.provider} size={18} />
                      ))}
                    </span>
                    <span>{excerpt(p, t)}</span>
                  </button>
                ))}
              </section>
            ))}
          {![...byDay.keys()].some((d) => new Date(d * 1000).getUTCMonth() === cur.m) && <div className="flxsm-empty">{t('No posts this month')}</div>}
        </div>
      )}
    </div>
  );
}
