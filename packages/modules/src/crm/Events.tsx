// CRM › Events: gatherings (list, dashboard, people, messages) through Flux CRM GraphQL.
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCrmGathering,
  FlxCrmGatheringDelete,
  FlxCrmGatheringForm,
  FlxCrmGatheringMessages,
  FlxCrmGatheringPage,
  FlxCrmGatheringPeople,
  FlxCrmGatheringSave,
  FlxCrmGatheringSend,
  FlxCrmGatheringSendControl,
  FlxCrmGatheringSendView,
  FlxCrmGatheringStatus,
  FlxCrmGatherings,
} from '@fluxwp/graphql/operations';
import type {
  FlxCrmGatheringFormQuery,
  FlxCrmGatheringMessagesQuery,
  FlxCrmGatheringQuery,
  FlxCrmGatheringsQuery,
  FlxGatheringChannel,
  FlxGatheringControl,
  FlxGatheringKind,
  FlxGatheringStatus,
} from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, Empty, Head, Ic, errText, href, useCrm, type Route } from './ui';

type CardNode = FlxCrmGatheringsQuery['flxGatherings']['events']['nodes'][number];
type Detail = NonNullable<FlxCrmGatheringQuery['flxGathering']>;
type Messages = NonNullable<FlxCrmGatheringMessagesQuery['flxGatheringMessages']>;

/** datetime-local value in the site timezone, from an FlxDateTime timestamp. */
function siteLocalInput(timestamp: number, timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timeZone || 'UTC',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(timestamp * 1000));
  const pick = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';
  const hour = pick('hour') === '24' ? '00' : pick('hour');
  return `${pick('year')}-${pick('month')}-${pick('day')}T${hour}:${pick('minute')}`;
}
type Send = Messages['sends'][number];
type FormData = NonNullable<FlxCrmGatheringFormQuery['flxGatheringForm']>;

interface WpAttachment {
  id: number;
  url?: string;
}
interface WpMediaFrame {
  on(event: 'select', cb: () => void): void;
  open(): void;
  state(): { get(key: 'selection'): { first(): { toJSON(): WpAttachment } } };
}
interface WpWithMedia {
  media?(opts: { title: string; button: { text: string }; multiple: boolean; library?: { type: string } }): WpMediaFrame;
}

export function Events({ route, go }: { route: Route; go: (to: string) => void }) {
  if (route.name === 'event' && route.id) {
    if (route.q.get('edit') === '1') return <GatheringForm id={Number(route.id)} go={go} />;
    return <GatheringDetail id={Number(route.id)} route={route} go={go} />;
  }
  if (route.id === 'new') return <GatheringForm id={0} go={go} />;
  return <GatheringList route={route} />;
}

function useRefresh() {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  return () => {
    for (const op of [FlxCrmGatherings, FlxCrmGathering, FlxCrmGatheringMessages, FlxCrmGatheringSendView, FlxCrmGatheringPeople, FlxCrmGatheringForm]) {
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, op) });
    }
  };
}

function GatheringList({ route }: { route: Route }) {
  const crm = useCrm();
  const { t, num, can } = crm;
  const tab = route.q.get('tab') === 'site' ? 'site' : route.q.get('status') === 'closed' ? 'closed' : 'open';
  const after = route.q.get('after') || undefined;
  const status: FlxGatheringStatus = tab === 'closed' ? 'CLOSED' : 'OPEN';
  const q = useFlxQuery(FlxCrmGatherings, { status, first: 24, after });
  if (q.isLoading) return <Loading />;
  if (!q.data) return <div className="flxcrm-notice is-bad">{q.error?.message ?? t('The action could not be completed.')}</div>;
  const board = q.data.flxGatherings;
  const page = board.events;
  return (
    <>
      <Head
        title={t('Events')}
        lead={t('Planting days, meetings and trainings. People other plugins attach, and the messages you send them.')}
        actions={
          can && (
            <a className="button button-primary" href={href('events/new')}>
              <Ic n="plus" /> {t('New event')}
            </a>
          )
        }
      />
      <nav className="flxcrm-subtabs">
        <a href={href('events')} className={tab === 'open' ? 'is-on' : undefined}>
          {t('Open')} <b>{num(board.openCount)}</b>
        </a>
        <a href={href('events?status=closed')} className={tab === 'closed' ? 'is-on' : undefined}>
          {t('Archived')} <b>{num(board.closedCount)}</b>
        </a>
        {board.hasSite && (
          <a href={href('events?tab=site')} className={tab === 'site' ? 'is-on' : undefined}>
            {t('On the site')}
          </a>
        )}
      </nav>
      {tab === 'site' ? (
        <div className="flxcrm-evsite" dangerouslySetInnerHTML={{ __html: board.siteHtml }} />
      ) : page.nodes.length === 0 ? (
        <Empty icon="clock" title={tab === 'open' ? t('No open events.') : t('The archive is empty.')}>
          {can && tab === 'open' && (
            <p>
              <a className="button button-primary" href={href('events/new')}>
                {t('New event')}
              </a>
            </p>
          )}
        </Empty>
      ) : (
        <>
          <div className="flxcrm-evgrid">
            {page.nodes.map((e) => (
              <EventCard key={e.databaseId} e={e} mailReady={board.mailReady} />
            ))}
          </div>
          {page.pageInfo.hasNextPage && page.pageInfo.endCursor && (
            <p>
              <a className="button" href={href('events?' + qs({ status: tab === 'closed' ? 'closed' : undefined, after: page.pageInfo.endCursor }))}>
                {t('Load more')}
              </a>
            </p>
          )}
        </>
      )}
    </>
  );
}

function EventCard({ e, mailReady }: { e: CardNode; mailReady: boolean }) {
  const crm = useCrm();
  const { t, num, can } = crm;
  const refresh = useRefresh();
  const status = useFlxMutation(FlxCrmGatheringStatus);
  const del = useFlxMutation(FlxCrmGatheringDelete);
  const page = useFlxMutation(FlxCrmGatheringPage);
  const [ask, setAsk] = useState(false);
  const url = href('event/' + e.databaseId);
  const bar = e.stats.find((s) => s.progress != null) ?? (e.progress != null ? { progress: e.progress, hint: e.progressHint } : null);
  const run = async (fn: () => Promise<{ errors: ReadonlyArray<{ message: string }> } | null | undefined>, ok: string) => {
    try {
      const r = await fn();
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      crm.tell('ok', ok);
      refresh();
    } catch (err) {
      crm.fail(err);
    }
  };
  return (
    <article className={'flxcrm-card flxcrm-evcard' + (e.status === 'CLOSED' ? ' is-closed' : '')}>
      <a className="flxcrm-evcard__cover" href={url} tabIndex={-1} aria-hidden="true">
        <Cover e={e} />
      </a>
      <div className="flxcrm-evcard__body">
        <span className={'flxcrm-badge is-' + e.phaseTone}>{e.phaseLabel}</span>
        <h2>
          <a href={url}>{e.name}</a>
        </h2>
        <p className="flxcrm-evcard__meta">
          <span>
            <Ic n="clock" size={13} /> {e.whenLabel}
          </span>
          {e.place ? (
            <span>
              <Ic n="pin" size={13} /> {e.place}
            </span>
          ) : null}
        </p>
        <div className="flxcrm-evcard__nums">
          <div>
            <b>{num(e.peopleCount)}</b>
            <span>{t('contacts')}</span>
          </div>
          {e.stats.map((s) => (
            <div key={s.label}>
              <b>{s.value}</b>
              <span>{s.label}</span>
            </div>
          ))}
        </div>
        {bar && bar.progress != null && (
          <div className="flxcrm-evcard__goal">
            <i className="flxcrm-evbar">
              <em style={{ width: bar.progress + '%' }} />
            </i>
            <small>
              {t('%s%%', num(bar.progress))}
              {bar.hint ? ' · ' + bar.hint : ''}
            </small>
          </div>
        )}
      </div>
      <footer className="flxcrm-evcard__foot">
        <a className="button button-primary button-small" href={url}>
          {t('Open dashboard')}
        </a>
        {can && mailReady && (
          <a className="button button-small" href={href('event/' + e.databaseId + '?view=message&sub=new')}>
            <Ic n="send" size={14} /> {t('Message')}
          </a>
        )}
        <span className="flxcrm-evcard__sp" />
        <details className="flxcrm-more">
          <summary className="button button-small" aria-label={t('More actions')}>
            <span aria-hidden="true">⋯</span>
          </summary>
          <div className="flxcrm-more__menu" role="menu">
            {e.actions.map((a) => (
              <a key={a.url + a.label} className="flxcrm-more__item" role="menuitem" href={a.url} target={a.target || undefined}>
                <Ic n="link" /> {a.label}
              </a>
            ))}
            {can && (
              <>
                <a className="flxcrm-more__item" role="menuitem" href={href('event/' + e.databaseId + '?edit=1')}>
                  <Ic n="edit" /> {t('Edit')}
                </a>
                {e.pageState === 'published' && e.pageUrl ? (
                  <a className="flxcrm-more__item" role="menuitem" href={e.pageUrl} target="_blank" rel="noopener">
                    <Ic n="arrow" /> {t('View on site')}
                  </a>
                ) : (
                  e.canCreatePage && (
                    <button type="button" className="flxcrm-more__item" role="menuitem" disabled={page.isPending} onClick={() => void run(async () => (await page.mutateAsync({ input: { id: e.databaseId } })).flxGatheringEnsurePage, e.postId ? t('Publish on site') : t('Create site page'))}>
                      {e.postId ? t('Publish on site') : t('Create site page')}
                    </button>
                  )
                )}
                <button
                  type="button"
                  className="flxcrm-more__item"
                  role="menuitem"
                  disabled={status.isPending}
                  onClick={() => void run(async () => (await status.mutateAsync({ input: { id: e.databaseId, status: e.status === 'CLOSED' ? 'OPEN' : 'CLOSED' } })).flxGatheringSetStatus, t('Saved.'))}
                >
                  {e.status === 'CLOSED' ? t('Reopen') : t('Close and archive')}
                </button>
                {e.status === 'CLOSED' && (
                  <button type="button" className="flxcrm-more__item is-bad" role="menuitem" onClick={() => setAsk(true)}>
                    {t('Delete')}
                  </button>
                )}
              </>
            )}
          </div>
        </details>
      </footer>
      {ask && (
        <Modal title={t('Delete')} onClose={() => setAsk(false)}>
          <p>{t('Are you sure? This cannot be undone.')}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setAsk(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="button button-danger"
              disabled={del.isPending}
              onClick={() => {
                setAsk(false);
                void run(async () => (await del.mutateAsync({ input: { id: e.databaseId } })).flxGatheringDelete, t('Deleted.'));
              }}
            >
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </article>
  );
}

function Cover({ e }: { e: { coverUrl: string; dateDay: string; dateMonth: string; dateYear: string } }) {
  if (e.coverUrl) return <img src={e.coverUrl} alt="" />;
  if (!e.dateDay) return <span className="flxcrm-evdate"><Ic n="clock" size={26} /></span>;
  return (
    <span className="flxcrm-evdate">
      <b>{e.dateDay}</b>
      <span>{e.dateMonth}</span>
      <small>{e.dateYear}</small>
    </span>
  );
}

function GatheringDetail({ id, route, go }: { id: number; route: Route; go: (to: string) => void }) {
  const crm = useCrm();
  const q = useFlxQuery(FlxCrmGathering, { id });
  if (q.isLoading) return <Loading />;
  const e = q.data?.flxGathering;
  if (!e) {
    return (
      <>
        <Head title={crm.t('Event not found.')} back={{ to: 'events', label: crm.t('Events') }} />
      </>
    );
  }
  const view = route.q.get('view') === 'people' || (route.q.get('view') === 'message' && crm.can) ? route.q.get('view')! : 'overview';
  const back = e.status === 'CLOSED' ? 'events?status=closed' : 'events';
  return (
    <>
      <Head
        title={e.name}
        lead={e.whenLabel + (e.place ? ' · ' + e.place : '')}
        back={{ to: back, label: crm.t('Events') }}
        actions={
          crm.can && (
            <>
              <a className="button" href={href('event/' + id + '?edit=1')}>
                <Ic n="edit" /> {crm.t('Edit')}
              </a>
              {e.mailReady && view !== 'message' && (
                <a className="button button-primary" href={href('event/' + id + '?view=message&sub=new')}>
                  <Ic n="send" /> {crm.t('Send message')}
                </a>
              )}
            </>
          )
        }
      />
      <nav className="flxcrm-subtabs">
        <a href={href('event/' + id)} className={view === 'overview' ? 'is-on' : undefined} aria-current={view === 'overview' ? 'page' : undefined}>
          {crm.t('Overview')}
        </a>
        <a href={href('event/' + id + '?view=people')} className={view === 'people' ? 'is-on' : undefined} aria-current={view === 'people' ? 'page' : undefined}>
          {crm.t('Contacts')} <b>{crm.num(e.peopleCount)}</b>
        </a>
        {crm.can && (
          <a href={href('event/' + id + '?view=message')} className={view === 'message' ? 'is-on' : undefined} aria-current={view === 'message' ? 'page' : undefined}>
            {crm.t('Messages')} <b>{crm.num(e.sendCount)}</b>
          </a>
        )}
      </nav>
      {view === 'people' ? <People id={id} /> : view === 'message' ? <Messages id={id} route={route} go={go} name={e.name} /> : <Overview e={e} />}
    </>
  );
}

function Overview({ e }: { e: Detail }) {
  const { t, num, can } = useCrm();
  const icons = ['chart', 'badge', 'coins', 'heart', 'chart'];
  const max = Math.max(1, ...e.reasons.map((r) => r.count));
  return (
    <>
      <div className="flxcrm-kpis flxcrm-evkpis">
        <div className="flxcrm-kpi">
          <span className="flxcrm-kpi__icon">
            <Ic n="users" size={17} />
          </span>
          <div className="flxcrm-kpi__body">
            <span className="flxcrm-kpi__label">{t('Contacts')}</span>
            <span className="flxcrm-kpi__value">{num(e.peopleCount)}</span>
          </div>
        </div>
        {e.stats.map((s, i) => (
          <div className="flxcrm-kpi" key={s.label}>
            <span className="flxcrm-kpi__icon">
              <Ic n={icons[i % icons.length] ?? 'chart'} size={17} />
            </span>
            <div className="flxcrm-kpi__body">
              <span className="flxcrm-kpi__label">{s.label}</span>
              <span className="flxcrm-kpi__value">{s.value}</span>
              {s.hint ? <span className="flxcrm-kpi__hint">{s.hint}</span> : null}
              {s.progress != null && (
                <i className="flxcrm-evbar">
                  <em style={{ width: s.progress + '%' }} />
                </i>
              )}
            </div>
          </div>
        ))}
        <div className="flxcrm-kpi">
          <span className="flxcrm-kpi__icon">
            <Ic n="send" size={17} />
          </span>
          <div className="flxcrm-kpi__body">
            <span className="flxcrm-kpi__label">{t('Messages delivered')}</span>
            <span className="flxcrm-kpi__value">{num(e.deliveredCount)}</span>
            <span className="flxcrm-kpi__hint">{useCrm().tn('%s send', '%s sends', e.sendCount, num(e.sendCount))}</span>
          </div>
        </div>
      </div>
      <div className="flxcrm-evdash">
        <div className="flxcrm-evdash__main">
          <section className="flxcrm-card flxcrm-evinfo">
            <div className="flxcrm-evinfo__cover">
              <Cover e={e} />
            </div>
            <div className="flxcrm-evinfo__body">
              <span className={'flxcrm-badge is-' + e.phaseTone}>{e.phaseLabel}</span>
              {e.summary ? <p className="flxcrm-evinfo__sum">{e.summary}</p> : null}
              <dl className="flxcrm-evinfo__dl">
                <dt>{t('Date')}</dt>
                <dd>{e.whenLabel}</dd>
                {e.place ? (
                  <>
                    <dt>{t('Place')}</dt>
                    <dd>
                      {e.place}
                      {e.address ? <small>{e.address}</small> : null}
                      {e.mapUrl ? (
                        <a href={e.mapUrl} target="_blank" rel="noopener">
                          {t('Map')}
                        </a>
                      ) : null}
                    </dd>
                  </>
                ) : null}
              </dl>
              {can && e.note ? (
                <p className="flxcrm-evinfo__note">
                  <Ic n="note" size={14} /> {e.note}
                </p>
              ) : null}
            </div>
          </section>
          <section className="flxcrm-card">
            <div className="flxcrm-card__row">
              <h2 className="flxcrm-card__title">{t('Last messages')}</h2>
              {can && (
                <a className="button button-small" href={href('event/' + e.databaseId + '?view=message')}>
                  {t('All and new')}
                </a>
              )}
            </div>
            {e.recentSends.length === 0 ? (
              <p className="flxcrm-muted">{t('No message sent to this event yet.')}</p>
            ) : (
              <ul className="flxcrm-evlast">
                {e.recentSends.map((s) => (
                  <li key={s.key}>
                    <a href={href('event/' + e.databaseId + '?view=message&send=' + s.key)}>
                      <div>
                        <strong>{s.text}</strong>
                        <small>
                          {(s.sendAt ? t('Planned for %s', s.sendAt.label) : s.at?.relative ?? '') + ' · ' + s.channels.map((c) => c.label).join(', ')}
                          {s.eta ? ' · ' + t('Estimated finish: %s', s.eta.label) : ''}
                        </small>
                      </div>
                      <span className={'flxcrm-badge is-' + s.tone}>{s.overallLabel}</span>
                      <span className="flxcrm-pill">
                        {num(s.channels.reduce((n, c) => n + c.sent, 0))} / {num(s.channels.reduce((n, c) => n + c.total, 0))}
                      </span>
                      <Ic n="arrow" size={14} />
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
        <aside className="flxcrm-evdash__side">
          <section className="flxcrm-card">
            <div className="flxcrm-card__row">
              <h2 className="flxcrm-card__title">{t('Who is in this event')}</h2>
              <a className="button button-small" href={href('event/' + e.databaseId + '?view=people')}>
                {t('List')}
              </a>
            </div>
            {e.reasons.length === 0 ? (
              <p className="flxcrm-muted">{t('No one is tied to this event yet.')}</p>
            ) : (
              <ul className="flxcrm-evwhy">
                {e.reasons.map((r) => (
                  <li key={r.label}>
                    <span>{r.label}</span>
                    <b>{num(r.count)}</b>
                    <i className="flxcrm-evbar">
                      <em style={{ width: Math.round((r.count / max) * 100) + '%' }} />
                    </i>
                  </li>
                ))}
              </ul>
            )}
          </section>
          {e.actions.length > 0 && (
            <section className="flxcrm-card">
              <div className="flxcrm-card__row">
                <h2 className="flxcrm-card__title">{t('Shortcuts')}</h2>
              </div>
              <div className="flxcrm-evlinks">
                {e.actions.map((a) => (
                  <a key={a.url + a.label} href={a.url} target={a.target || undefined}>
                    <Ic n="link" /> <span>{a.label}</span>
                  </a>
                ))}
              </div>
            </section>
          )}
        </aside>
      </div>
    </>
  );
}

function People({ id }: { id: number }) {
  const { t, num } = useCrm();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [after, setAfter] = useState<string | undefined>();
  useEffect(() => {
    const h = window.setTimeout(() => {
      setQ(search);
      setAfter(undefined);
    }, 250);
    return () => window.clearTimeout(h);
  }, [search]);
  const query = useFlxQuery(FlxCrmGatheringPeople, { id, search: q || undefined, first: 40, after });
  const rows = query.data?.flxGatheringPeople.nodes ?? [];
  const page = query.data?.flxGatheringPeople;
  return (
    <section className="flxcrm-card flxcrm-card--flush">
      <div className="flxcrm-evpeople__bar">
        <input type="search" value={search} placeholder={t('Filter by name, phone or e-mail')} aria-label={t('Filter')} onChange={(e) => setSearch(e.currentTarget.value)} />
        {page?.totalCount != null && <small className="flxcrm-muted">{num(page.totalCount)}</small>}
      </div>
      {query.isLoading ? (
        <Loading />
      ) : rows.length === 0 ? (
        <p className="flxcrm-muted" style={{ padding: '14px 18px' }}>
          {t('No one is tied to this event yet.')}
        </p>
      ) : (
        <table className="widefat striped flxcrm-table">
          <thead>
            <tr>
              <th>{t('Name')}</th>
              <th>{t('Phone')}</th>
              <th>{t('E-mail')}</th>
              <th>{t('Why')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((p) => (
              <tr key={p.databaseId + ':' + p.why}>
                <td>{p.name || t('(no name)')}</td>
                <td>{p.phone}</td>
                <td>{p.email}</td>
                <td>{p.why}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {page?.pageInfo.hasNextPage && page.pageInfo.endCursor && (
        <p style={{ padding: '12px 14px' }}>
          <button type="button" className="button" onClick={() => setAfter(page.pageInfo.endCursor ?? undefined)}>
            {t('Load more')}
          </button>
        </p>
      )}
    </section>
  );
}

function Messages({ id, route, go, name }: { id: number; route: Route; go: (to: string) => void; name: string }) {
  const sendKey = route.q.get('send') ?? '';
  if (sendKey) return <SendDetail id={id} sendKey={sendKey} route={route} />;
  return <MessageBoard id={id} route={route} go={go} name={name} />;
}

function MessageBoard({ id, route, go, name }: { id: number; route: Route; go: (to: string) => void; name: string }) {
  const { t, num } = useCrm();
  const q = useFlxQuery(FlxCrmGatheringMessages, { id }, { refetchInterval: (query) => (query.state.data?.flxGatheringMessages?.sends.some((s) => s.overall === 'RUNNING' || s.overall === 'PAUSED') ? 60_000 : false) });
  if (q.isLoading) return <Loading />;
  const m = q.data?.flxGatheringMessages;
  if (!m) return <div className="flxcrm-notice is-bad">{q.error?.message ?? t('The action could not be completed.')}</div>;
  if (!m.mailReady) {
    return (
      <section className="flxcrm-card flxcrm-evmsg">
        <h2 className="flxcrm-card__title">{t('Message to the event')}</h2>
        <p className="flxcrm-muted">{t('Install and turn on Flux Mail to send e-mail, WhatsApp and SMS to the people of this event.')}</p>
      </section>
    );
  }
  const active = m.sends.filter((s) => s.overall === 'RUNNING' || s.overall === 'PAUSED');
  const planned = m.sends.filter((s) => s.overall === 'PLANNED');
  const sent = m.sends.filter((s) => s.overall !== 'RUNNING' && s.overall !== 'PAUSED' && s.overall !== 'PLANNED');
  const asked = route.q.get('sub');
  const sub = asked === 'new' || asked === 'sent' || asked === 'planned' ? asked : m.sends.length ? (active.length || sent.length ? 'sent' : 'planned') : 'new';
  const list = sub === 'sent' ? [...active, ...sent] : planned;
  return (
    <>
      {active.length > 0 && sub !== 'sent' && (
        <div className="flxcrm-sendlist flxcrm-sendlist--active">
          {active.map((s) => (
            <SendCard key={s.key} id={id} s={s} />
          ))}
        </div>
      )}
      <nav className="flxcrm-segtabs" aria-label={t('Messages')}>
        <a href={href('event/' + id + '?view=message&sub=new')} className={sub === 'new' ? 'is-on' : undefined}>
          <Ic n="plus" size={14} /> {t('New message')}
        </a>
        <a href={href('event/' + id + '?view=message&sub=sent')} className={sub === 'sent' ? 'is-on' : undefined}>
          {t('Sent')} <b>{num(active.length + sent.length)}</b>
        </a>
        <a href={href('event/' + id + '?view=message&sub=planned')} className={sub === 'planned' ? 'is-on' : undefined}>
          {t('Planned')} <b>{num(planned.length)}</b>
        </a>
      </nav>
      {sub === 'new' ? (
        <Composer id={id} name={name} data={m} go={go} />
      ) : list.length === 0 ? (
        <Empty icon="send" title={sub === 'sent' ? t('No message sent to this event yet.') : t('No planned message.')}>
          <p>
            <a className="button button-primary" href={href('event/' + id + '?view=message&sub=new')}>
              {t('New message')}
            </a>
          </p>
        </Empty>
      ) : (
        <div className="flxcrm-sendlist">
          {list.map((s) => (
            <SendCard key={s.key} id={id} s={s} />
          ))}
        </div>
      )}
    </>
  );
}

function SendCard({ id, s, full }: { id: number; s: Send; full?: boolean }) {
  const crm = useCrm();
  const { t, num } = crm;
  const refresh = useRefresh();
  const control = useFlxMutation(FlxCrmGatheringSendControl);
  const [ask, setAsk] = useState(false);
  const url = href('event/' + id + '?view=message&send=' + s.key);
  const act = async (do_: FlxGatheringControl) => {
    try {
      const r = (await control.mutateAsync({ input: { id, key: s.key, do: do_ } })).flxGatheringSendControl;
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      refresh();
    } catch (e) {
      crm.fail(e);
    }
  };
  return (
    <article className={'flxcrm-card flxcrm-sendcard is-' + s.overall.toLowerCase() + (full ? ' is-full' : '')}>
      {!full && <a className="flxcrm-sendcard__link" href={url} aria-label={t('View details')} />}
      <div className="flxcrm-sendcard__top">
        <span className={'flxcrm-badge is-' + s.tone}>
          {s.overall === 'RUNNING' && <i className="flxcrm-livedot" />}
          {s.overallLabel}
        </span>
        <span className="flxcrm-badge">{s.kind === 'MARKETING' ? t('Promotion') : t('Information')}</span>
        <span className="flxcrm-muted">{(s.sendAt ? t('Planned for %s', s.sendAt.label) : s.at?.label ?? '') + (s.byName ? ' · ' + s.byName : '')}</span>
      </div>
      <div className="flxcrm-sendcard__main">
        {s.thumbUrl ? <img className="flxcrm-sendcard__thumb" src={s.thumbUrl} alt="" /> : s.mediaId ? <span className="flxcrm-sendcard__thumb"><Ic n="link" size={18} /></span> : null}
        <p className="flxcrm-sendcard__text">{s.text}</p>
      </div>
      {s.overall !== 'PLANNED' && (
        <div className="flxcrm-sendnums">
          {s.delivered != null && <Num icon="send" label={t('Reached')} n={s.delivered} />}
          {s.read != null && <Num icon="check" label={t('Read')} n={s.read} />}
          {s.replied != null && <Num icon="message" label={t('Replied')} n={s.replied} />}
          {s.optout != null && <Num icon="x" label={t('Turned off messages')} n={s.optout} tone="is-warn" />}
          <Num icon="alert" label={t('Failed')} n={s.failed} tone={s.failed ? 'is-bad' : ''} />
        </div>
      )}
      <div className="flxcrm-sendcard__chs">
        {s.channels.map((c) => (
          <div className="flxcrm-sendcard__ch" key={c.key}>
            <b>
              <Ic n={c.key === 'email' ? 'mail' : c.key === 'sms' ? 'phone' : 'message'} size={14} /> {c.label}
            </b>
            {c.missing ? (
              <small>{t('not found')}</small>
            ) : (
              <>
                <i className="flxcrm-evbar">
                  <em style={{ width: c.percent + '%' }} />
                </i>
                <small>
                  <strong>
                    {num(c.sent)} / {num(c.total)}
                  </strong>
                  {c.failed ? ' · ' + t('%s failed', num(c.failed)) : ''}
                  {c.skipped ? ' · ' + t('%s skipped', num(c.skipped)) : ''}
                </small>
              </>
            )}
          </div>
        ))}
      </div>
      <div className="flxcrm-sendcard__foot">
        {s.eta ? (
          <span className="flxcrm-sendcard__eta">
            <Ic n="clock" size={14} /> {t('Estimated finish: %s', s.eta.label)}
          </span>
        ) : s.overall === 'RUNNING' ? (
          <span className="flxcrm-sendcard__eta flxcrm-muted">{t('Estimated finish shows after the first messages.')}</span>
        ) : null}
        <span className="flxcrm-evcard__sp" />
        <div className="flxcrm-sendcard__acts">
          {s.canPause && s.overall === 'RUNNING' && (
            <button type="button" className="button button-small" disabled={control.isPending} onClick={() => void act('PAUSE')}>
              {t('Pause')}
            </button>
          )}
          {s.canPause && s.overall === 'PAUSED' && (
            <button type="button" className="button button-small button-primary" disabled={control.isPending} onClick={() => void act('RESUME')}>
              {t('Resume')}
            </button>
          )}
          {s.pending && (
            <button type="button" className="button button-small flxcrm-btn-bad" onClick={() => setAsk(true)}>
              {s.overall === 'PLANNED' ? t('Cancel the plan') : t('Stop the send')}
            </button>
          )}
          {!full && <span className="flxcrm-sendcard__more">{t('View details')} <Ic n="arrow" size={14} /></span>}
        </div>
      </div>
      {ask && (
        <Modal title={s.overall === 'PLANNED' ? t('Cancel the plan') : t('Stop the send')} onClose={() => setAsk(false)}>
          <p>{t('Messages not sent yet will not go out. This cannot be undone.')}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setAsk(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="button button-danger"
              onClick={() => {
                setAsk(false);
                void act('CANCEL');
              }}
            >
              {s.overall === 'PLANNED' ? t('Cancel the plan') : t('Stop the send')}
            </button>
          </div>
        </Modal>
      )}
    </article>
  );
}

function Num({ icon, label, n, tone }: { icon: string; label: string; n: number; tone?: string }) {
  const { num } = useCrm();
  return (
    <div className={'flxcrm-sendnums__i ' + (n && tone ? tone : '')}>
      <span>
        <Ic n={icon} size={13} /> {label}
      </span>
      <b>{num(n)}</b>
    </div>
  );
}

function SendDetail({ id, sendKey, route }: { id: number; sendKey: string; route: Route }) {
  const { t, num } = useCrm();
  const channel = route.q.get('ch') || undefined;
  const status = route.q.get('st') || undefined;
  const after = route.q.get('after') || undefined;
  const q = useFlxQuery(FlxCrmGatheringSendView, { id, key: sendKey, channel, status, first: 40, after }, { refetchInterval: (query) => (query.state.data?.flxGatheringSend?.overall === 'RUNNING' ? 60_000 : false) });
  if (q.isLoading) return <Loading />;
  const s = q.data?.flxGatheringSend;
  if (!s) return <p className="flxcrm-muted">{t('not found')}</p>;
  const back = s.overall === 'PLANNED' ? 'planned' : 'sent';
  const rec = s.recipients;
  const base = 'event/' + id + '?view=message&send=' + sendKey;
  return (
    <>
      <a className="flxcrm-back" href={href('event/' + id + '?view=message&sub=' + back)}>
        <Ic n="back" size={15} /> {back === 'planned' ? t('Planned') : t('Sent')}
      </a>
      <SendCard id={id} s={s} full />
      <section className="flxcrm-card flxcrm-card--flush flxcrm-sendrec">
        <div className="flxcrm-sendrec__bar">
          <h2 className="flxcrm-card__title">{t('Recipients')}</h2>
          {rec.channels.length > 1 && (
            <nav className="flxcrm-segtabs flxcrm-segtabs--sm">
              {rec.channels.map((c) => (
                <a key={c} href={href(base + '&ch=' + c)} className={c === rec.channel ? 'is-on' : undefined}>
                  {c}
                </a>
              ))}
            </nav>
          )}
        </div>
        <nav className="flxcrm-chips flxcrm-sendrec__filter">
          <FilterChip to={base + '&ch=' + rec.channel} on={!status} label={t('All')} n={(rec.countSent ?? 0) + (rec.countWaiting ?? 0) + (rec.countFailed ?? 0) + (rec.countOther ?? 0)} />
          <FilterChip to={base + '&ch=' + rec.channel + '&st=sent'} on={status === 'sent'} label={t('Sent')} n={rec.countSent} />
          <FilterChip to={base + '&ch=' + rec.channel + '&st=waiting'} on={status === 'waiting'} label={t('Waiting')} n={rec.countWaiting} />
          <FilterChip to={base + '&ch=' + rec.channel + '&st=failed'} on={status === 'failed'} label={t('Failed')} n={rec.countFailed} />
          <FilterChip to={base + '&ch=' + rec.channel + '&st=other'} on={status === 'other'} label={t('Skipped or cancelled')} n={rec.countOther} />
        </nav>
        {rec.nodes.length === 0 ? (
          <p className="flxcrm-muted" style={{ padding: '6px 18px 16px' }}>
            {t('Nobody here.')}
          </p>
        ) : (
          <table className="widefat striped flxcrm-table">
            <thead>
              <tr>
                <th>{t('Name')}</th>
                <th>{rec.channel === 'email' ? t('E-mail') : t('Phone')}</th>
                <th>{t('Status')}</th>
                <th>{t('Time')}</th>
              </tr>
            </thead>
            <tbody>
              {rec.nodes.map((r, i) => (
                <tr key={i}>
                  <td>
                    <strong>{r.name || t('(no name)')}</strong>
                  </td>
                  <td>{r.recipient}</td>
                  <td>
                    <span className="flxcrm-badge">{r.status}</span>
                    {r.error ? <small className="flxcrm-sendrec__err">{r.error}</small> : null}
                  </td>
                  <td>{r.sentAt?.label || r.scheduledAt?.label || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {rec.pageInfo.hasNextPage && rec.pageInfo.endCursor && (
          <div className="flxcrm-sendrec__pager">
            <a className="button button-small" href={href(base + '&ch=' + rec.channel + (status ? '&st=' + status : '') + '&after=' + encodeURIComponent(rec.pageInfo.endCursor))}>
              {t('Next')}
            </a>
          </div>
        )}
      </section>
    </>
  );
}

function FilterChip({ to, on, label, n }: { to: string; on: boolean; label: string; n: number }) {
  const { num } = useCrm();
  if (!on && !n) return null;
  return (
    <a className={'flxcrm-chip' + (on ? ' is-on' : '')} href={href(to)}>
      {label} <b>{num(n)}</b>
    </a>
  );
}

function Composer({ id, name, data, go }: { id: number; name: string; data: Messages; go: (to: string) => void }) {
  const crm = useCrm();
  const { t } = crm;
  const host = useHost();
  const refresh = useRefresh();
  const send = useFlxMutation(FlxCrmGatheringSend);
  const text = useRef<HTMLTextAreaElement>(null);
  const [channels, setChannels] = useState<FlxGatheringChannel[]>(() => (data.channels.some((c) => c.ready && c.key === 'whatsapp') ? ['WHATSAPP'] : []));
  const [mediaUploadId, setMediaUploadId] = useState('');
  const [kind, setKind] = useState<FlxGatheringKind>('INFO');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const planned = data.defaultSendAt ? siteLocalInput(data.defaultSendAt.timestamp, data.timezone) : '';
  const [when, setWhen] = useState(planned ? 'later' : 'now');
  const [sendAt, setSendAt] = useState(planned);
  const [mediaId, setMediaId] = useState(0);
  const [mediaUrl, setMediaUrl] = useState('');
  const [who, setWho] = useState(0);
  const counts = (ch: string) => {
    const row = data.channels.find((c) => c.key === ch);
    return kind === 'MARKETING' ? (row?.marketingCount ?? 0) : (row?.infoCount ?? 0);
  };
  const total = channels.reduce((n, c) => n + counts(c), 0);
  const insert = (key: string) => {
    const el = text.current;
    const token = '{' + key + '}';
    if (!el) {
      setBody((b) => b + token);
      return;
    }
    const start = el.selectionStart ?? body.length;
    const next = body.slice(0, start) + token + body.slice(el.selectionEnd ?? start);
    setBody(next);
  };
  const sample = data.samples[who];
  const preview = useMemo(() => fillTokens(body, sample?.values ?? [], data.variables), [body, sample, data.variables]);
  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (
        await send.mutateAsync({
          input: {
            id,
            channels,
            kind,
            subject: subject || undefined,
            text: body,
            sendAt: when === 'later' ? sendAt : '',
            mediaId: mediaUploadId ? undefined : mediaId || undefined,
            mediaUploadId: mediaUploadId || undefined,
          },
        })
      ).flxGatheringMessageSend;
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      const skipped = r.skipped.map((s) => s.channel + ': ' + s.reason).join(' ');
      crm.tell('ok', skipped ? t('Saved.') + ' ' + skipped : t('Saved.'));
      refresh();
      go('event/' + id + '?view=message&sub=' + (when === 'later' ? 'planned' : 'sent'));
    } catch (err) {
      crm.fail(err);
    }
  };
  const pick = () => {
    const wp = (window as unknown as { wp?: WpWithMedia }).wp;
    const frame = wp?.media?.({ title: t('Photo, video or file'), button: { text: t('Use this file') }, multiple: false });
    if (!frame) return;
    frame.on('select', () => {
      const file = frame.state().get('selection').first().toJSON();
      setMediaId(file.id);
      setMediaUrl(file.url ?? '');
    });
    frame.open();
  };
  const upload = async (file: File) => {
    const fd = new FormData();
    fd.append('context', 'flx-crm/event-file');
    fd.append('file', file, file.name);
    const r = await host.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', formData: fd });
    setMediaUploadId(r.uploadId);
    setMediaId(0);
    setMediaUrl(file.name);
  };
  return (
    <section className="flxcrm-card flxcrm-evmsg">
      <h2 className="flxcrm-card__title">
        <Ic n="send" size={16} /> {t('Message to the event')}
      </h2>
      <p className="flxcrm-muted">{t('One extra message to everyone tied to this event, e.g. a thank-you with a photo when it is over. Send it now or plan it. It goes through Flux Mail: its queue, the wait between WhatsApp messages, the blocklist and the daily e-mail limit apply.')}</p>
      <form className="flxcrm-form flxcrm-evmsg__form" onSubmit={(e) => void onSubmit(e)}>
        <fieldset>
          <legend>{t('Channels')}</legend>
          <div className="flxcrm-evmsg__chips">
            {data.channels.map((c) => (
              <label key={c.key} className={'flxcrm-evmsg__chip' + (c.ready ? '' : ' is-off')}>
                <input
                  type="checkbox"
                  checked={!!channelEnum(c.key) && channels.includes(channelEnum(c.key)!)}
                  disabled={!c.ready}
                  onChange={(e) => {
                    const key = channelEnum(c.key);
                    if (!key) return;
                    setChannels((cur) => (e.currentTarget.checked ? [...cur, key] : cur.filter((x) => x !== key)));
                  }}
                />
                <span>{c.label}</span> <b>{crm.num(counts(c.key))}</b>
                {!c.ready && <small>{t('no connection')}</small>}
              </label>
            ))}
          </div>
          <small>{t('The number is how many people get it on that channel (one per address).')}</small>
        </fieldset>
        <fieldset>
          <legend>{t('Message type')}</legend>
          <label className="flxcrm-check">
            <input type="radio" checked={kind === 'INFO'} onChange={() => setKind('INFO')} /> {t('Event information or thank-you: everyone of the event')}
          </label>
          <label className="flxcrm-check">
            <input type="radio" checked={kind === 'MARKETING'} onChange={() => setKind('MARKETING')} /> {t('Promotion or campaign: only people who allowed that channel')}
          </label>
        </fieldset>
        {channels.includes('EMAIL') && (
          <label>
            <span>{t('E-mail subject')}</span>
            <input value={subject} maxLength={190} placeholder={t('Thank you for joining %s', name)} onChange={(e) => setSubject(e.currentTarget.value)} />
            <small>{t('Empty: the event name.')}</small>
          </label>
        )}
        <label>
          <span>{t('Message')} *</span>
          <textarea ref={text} rows={6} required value={body} placeholder={t('Hello {ad}, thank you for being with us at %s. 🌱', name)} onChange={(e) => setBody(e.currentTarget.value)} />
          <small>{t('The same text goes on every channel; e-mail turns it into paragraphs. Click a value below to add it where the cursor is.')}</small>
        </label>
        <div className="flxcrm-evmsg__vars">
          {groupVars(data.variables).map((g) => (
            <div className="flxcrm-evmsg__vgroup" key={g.group}>
              <span>{g.group}</span>
              {g.items.map((v) => (
                <button key={v.key} type="button" className={'flxcrm-evmsg__var' + (v.off ? ' is-off' : '')} disabled={v.off} title={'{' + v.key + '}'} onClick={() => insert(v.key)}>
                  {v.label}
                </button>
              ))}
            </div>
          ))}
        </div>
        <div className="flxcrm-evmsg__row">
          <span className="flxcrm-evmsg__label">{t('Photo, video or file (optional)')}</span>
          <div className="flxcrm-evimg flxcrm-evmsg__media">
            <div className="flxcrm-evimg__prev">{mediaUrl ? <img src={mediaUrl} alt="" /> : <span>{t('None')}</span>}</div>
            <p>
              <button type="button" className="button" onClick={pick}>
                {t('Choose from media')}
              </button>
              <label className="button">
                {t('Upload')}
                <input
                  type="file"
                  hidden
                  onChange={(e) => {
                    const file = e.currentTarget.files?.[0];
                    if (file) void upload(file).catch(crm.fail);
                  }}
                />
              </label>
              {mediaId > 0 && (
                <button type="button" className="button-link" onClick={() => { setMediaId(0); setMediaUrl(''); }}>
                  {t('Remove')}
                </button>
              )}
            </p>
          </div>
          <small>{data.hasMedia ? t('WhatsApp: sent as an attachment with the text. E-mail: a photo shows above the text, other files get a button. SMS: the link is added.') : t('E-mail: a photo shows above the text, other files get a button. WhatsApp and SMS: the file link is added to the text.')}</small>
        </div>
        <div className="flxcrm-evmsg__preview">
          <div className="flxcrm-evmsg__phead">
            <span className="flxcrm-evmsg__label">{t('Preview')}</span>
            <select aria-label={t('Preview for')} value={who} onChange={(e) => setWho(Number(e.currentTarget.value))}>
              {data.samples.map((s, i) => (
                <option key={i} value={i}>
                  {s.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flxcrm-evmsg__chat">
            <div className="flxcrm-evmsg__bubble">{preview || '…'}</div>
          </div>
        </div>
        <fieldset>
          <legend>{t('When')}</legend>
          <label className="flxcrm-check">
            <input type="radio" checked={when === 'now'} onChange={() => setWhen('now')} /> {t('Now')}
          </label>
          <label className="flxcrm-check">
            <input type="radio" checked={when === 'later'} onChange={() => setWhen('later')} /> {t('Plan for')}
          </label>
          <input type="datetime-local" value={sendAt} disabled={when !== 'later'} onChange={(e) => setSendAt(e.currentTarget.value)} />
          <small>{t('Site time (%s). Planned sends can be cancelled below until they start.', data.timezone)}</small>
        </fieldset>
        <p className="flxcrm-evmsg__submit">
          <button className="button button-primary" disabled={send.isPending || !body.trim() || channels.length === 0}>
            {when === 'later' ? t('Plan the message') : t('Send now')}
          </button>
          <span className="flxcrm-muted">{t('%s messages in total', crm.num(total))}</span>
        </p>
      </form>
    </section>
  );
}

function GatheringForm({ id, go }: { id: number; go: (to: string) => void }) {
  const crm = useCrm();
  const host = useHost();
  const refresh = useRefresh();
  const q = useFlxQuery(FlxCrmGatheringForm, { id: id || undefined });
  const save = useFlxMutation(FlxCrmGatheringSave);
  const [values, setValues] = useState<Record<string, string>>({});
  const [createPost, setCreatePost] = useState(false);
  const [imageUploadId, setImageUploadId] = useState('');
  const [ready, setReady] = useState(false);
  useEffect(() => {
    const form = q.data?.flxGatheringForm;
    if (!form || ready) return;
    const next: Record<string, string> = {};
    for (const s of form.sections) for (const f of s.fields) next[f.key] = f.value;
    setValues(next);
    setCreatePost(form.createPost);
    setReady(true);
  }, [q.data, ready]);
  if (q.isLoading) return <Loading />;
  const form = q.data?.flxGatheringForm;
  if (!form) return <Head title={crm.t('Event not found.')} back={{ to: 'events', label: crm.t('Events') }} />;
  const set = (key: string, value: string) => setValues((v) => ({ ...v, [key]: value }));
  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (
        await save.mutateAsync({
          input: {
            id: id || undefined,
            createPost,
            imageUploadId: imageUploadId || undefined,
            fields: Object.entries(values).map(([key, value]) => ({ key, value })),
          },
        })
      ).flxGatheringSave;
      if (!r || r.errors.length || !r.gathering) throw new Error(errText(r?.errors) || crm.t('The action could not be completed.'));
      crm.tell('ok', crm.t('Saved.'));
      refresh();
      go('event/' + r.gathering.databaseId);
    } catch (err) {
      crm.fail(err);
    }
  };
  return (
    <>
      <Head title={id ? crm.t('Edit event') : crm.t('New event')} lead={id ? form.name : undefined} back={{ to: id ? 'event/' + id : 'events', label: id ? form.name : crm.t('Events') }} />
      <form className="flxcrm-evform" onSubmit={(e) => void onSubmit(e)}>
        <div className="flxcrm-form--grid">
          {form.sections.map((s) => (
            <section className="flxcrm-card flxcrm-form" key={s.key}>
              {s.title ? <h2 className="flxcrm-card__title">{s.title}</h2> : null}
              {s.fields.map((f) => (
                <label key={f.key} className="flxcrm-jf">
                  <span>
                    {f.label}
                    {f.required ? ' *' : ''}
                  </span>
                  <FieldInput f={f} value={values[f.key] ?? ''} onChange={(v) => set(f.key, v)} onUpload={async (file) => {
                    const fd = new FormData();
                    fd.append('context', 'flx-crm/event-image');
                    fd.append('file', file, file.name);
                    const up = await host.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', formData: fd });
                    setImageUploadId(up.uploadId);
                  }} />
                  {f.help ? <small className="flxcrm-muted">{f.help}</small> : null}
                </label>
              ))}
              {s.html ? <div className="flxcrm-html" dangerouslySetInnerHTML={{ __html: s.html }} /> : null}
            </section>
          ))}
        </div>
        <div className="flxcrm-card flxcrm-form">
          {form.afterHtml ? <div className="flxcrm-html" dangerouslySetInnerHTML={{ __html: form.afterHtml }} /> : null}
          {form.postType && (
            <label className="flx-ev-check">
              <input type="checkbox" checked={createPost} onChange={(e) => setCreatePost(e.currentTarget.checked)} /> {crm.t('Create site page')}
            </label>
          )}
          <p>
            <button className="button button-primary" disabled={save.isPending}>
              {id ? crm.t('Save') : crm.t('Create event')}
            </button>
          </p>
        </div>
      </form>
    </>
  );
}

function FieldInput({ f, value, onChange, onUpload }: { f: FormData['sections'][number]['fields'][number]; value: string; onChange: (v: string) => void; onUpload: (file: File) => Promise<void> }) {
  const { t } = useCrm();
  if (f.type === 'textarea') return <textarea rows={4} required={f.required} placeholder={f.placeholder} value={value} onChange={(e) => onChange(e.currentTarget.value)} />;
  if (f.type === 'post' || (f.options.length > 0 && f.type !== 'image')) {
    return (
      <select value={value} onChange={(e) => onChange(e.currentTarget.value)}>
        <option value="">{t('None')}</option>
        {f.options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    );
  }
  if (f.type === 'image') {
    return (
      <span>
        <input value={value} onChange={(e) => onChange(e.currentTarget.value)} placeholder={f.placeholder} />
        <label className="button button-small">
          {t('Upload')}
          <input type="file" accept="image/*" hidden onChange={(e) => { const file = e.currentTarget.files?.[0]; if (file) void onUpload(file); }} />
        </label>
      </span>
    );
  }
  const type = f.type === 'date' || f.type === 'time' || f.type === 'number' || f.type === 'url' ? f.type : 'text';
  return <input type={type} required={f.required} placeholder={f.placeholder} value={value} onChange={(e) => onChange(e.currentTarget.value)} />;
}

function groupVars(vars: Messages['variables']) {
  const out: Array<{ group: string; items: typeof vars }> = [];
  for (const v of vars) {
    const g = out.find((x) => x.group === v.group);
    if (g) g.items = [...g.items, v];
    else out.push({ group: v.group, items: [v] });
  }
  return out;
}

function fillTokens(text: string, values: ReadonlyArray<{ key: string; value: string }>, vars: ReadonlyArray<{ key: string; off: boolean }>): string {
  return text.replace(/\{([a-z0-9_]+)\}/gi, (all, key: string) => {
    if (vars.some((v) => v.key === key && v.off)) return all;
    return values.find((v) => v.key === key)?.value ?? all;
  });
}

function channelEnum(key: string): FlxGatheringChannel | null {
  const k = key.toLowerCase();
  if (k === 'whatsapp') return 'WHATSAPP';
  if (k === 'email') return 'EMAIL';
  if (k === 'sms') return 'SMS';
  return null;
}

function qs(parts: Record<string, string | undefined>): string {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(parts)) if (v) q.set(k, v);
  return q.toString();
}
