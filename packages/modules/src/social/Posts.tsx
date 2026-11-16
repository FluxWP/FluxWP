// Posts list and the approvals queue: tabs, search and filters, bulk actions, row actions, load more.
import { useEffect, useMemo, useState } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSocialPostBulkAct, FlxSocialPostDelete, FlxSocialPostDuplicate, FlxSocialPosts } from '@fluxwp/graphql/operations';
import type { FlxSocialBulkAction, FlxSocialPostStatus } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { NetDot, StatusBadge, STATUS_KEYS, errorText, excerpt, payloadError, postTime, useSocial, type Post, type Query } from './common';

const PAGE = 20;
const TABS: { id: string; label: string; status: FlxSocialPostStatus[] }[] = [
  { id: 'all', label: 'All', status: [] },
  { id: 'draft', label: 'Drafts', status: ['DRAFT'] },
  { id: 'scheduled', label: 'Scheduled', status: ['SCHEDULED', 'PUBLISHING'] },
  { id: 'published', label: 'Published', status: ['PUBLISHED'] },
  { id: 'failed', label: 'Errors', status: ['FAILED', 'PARTIAL'] },
];

const ALL_STATUS = Object.keys(STATUS_KEYS) as FlxSocialPostStatus[];

function tabFromStatus(s: string | undefined): string {
  if (!s) return 'all';
  const want = s.split(',').sort().join(',');
  return TABS.find((x) => x.status.length && [...x.status].sort().join(',') === want)?.id ?? 'all';
}

export function PostsPage({ approvals, initial }: { approvals: boolean; initial: Query }) {
  const s = useSocial();
  const { t, boot } = s;
  const cfg = boot.flxSocialConfig;
  const [tab, setTab] = useState(() => tabFromStatus(initial.status));
  const [search, setSearch] = useState(initial.search ?? '');
  const [q, setQ] = useState(initial.search ?? '');
  const [account, setAccount] = useState(initial.account ?? '');
  const [mine, setMine] = useState(initial.mine === '1');
  const [limit, setLimit] = useState(PAGE);
  const [sel, setSel] = useState<string[]>([]);

  useEffect(() => {
    const id = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(id);
  }, [search]);
  useEffect(() => {
    setLimit(PAGE);
    setSel([]);
  }, [tab, q, account, mine]);

  const status = useMemo<FlxSocialPostStatus[]>(() => {
    if (approvals) return ['PENDING'];
    const fromQuery = initial.status && tab === 'all' ? (initial.status.split(',').filter((x) => (ALL_STATUS as string[]).includes(x)) as FlxSocialPostStatus[]) : [];
    return fromQuery.length ? fromQuery : (TABS.find((x) => x.id === tab)?.status ?? []);
  }, [approvals, tab, initial.status]);

  const list = useFlxQuery(
    FlxSocialPosts,
    {
      first: limit,
      status: status.length ? status : undefined,
      search: q || undefined,
      accountId: account ? Number(account) : undefined,
      mine: mine || undefined,
      ascending: approvals || tab === 'scheduled' ? true : undefined,
    },
    { placeholderData: (prev) => prev },
  );

  const bulk = useFlxMutation(FlxSocialPostBulkAct);
  const del = useFlxMutation(FlxSocialPostDelete);
  const dup = useFlxMutation(FlxSocialPostDuplicate);

  const rows = list.data?.flxSocialPosts.nodes ?? [];
  const total = list.data?.flxSocialPosts.totalCount ?? 0;
  const allOn = rows.length > 0 && rows.every((p) => sel.includes(p.id));

  const runBulk = (action: FlxSocialBulkAction) => {
    const go = () =>
      bulk.mutate(
        { input: { action, ids: sel } },
        {
          onSuccess: (r) => {
            const p = r.flxSocialPostBulkAct;
            const err = payloadError(p?.errors);
            if (err) s.toast(err, 'error');
            else s.toast(t('{n} posts updated.', { n: p?.done ?? 0 }) + (p?.issues?.length ? ' ' + p.issues.join(' ') : ''), p?.issues?.length ? 'error' : 'ok');
            setSel([]);
            s.refreshPosts();
          },
          onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
        },
      );
    if (action === 'DELETE') s.confirm({ title: t('Delete posts'), text: t('{n} posts will be deleted from the panel. Posts already shared stay on the networks.', { n: sel.length }), action: t('Delete'), danger: true, run: go });
    else go();
  };

  const rowDelete = (p: Post) => {
    const shared = p.targets.some((x) => x.status === 'PUBLISHED' && x.remoteUrl);
    s.confirm({
      title: t('Delete post'),
      text: shared ? t('This post is deleted from the panel only. To remove it from a network, open the post and use "Delete from network".') : t('This post will be deleted.'),
      action: t('Delete'),
      danger: true,
      run: () =>
        del.mutate(
          { input: { id: p.id } },
          {
            onSuccess: (r) => {
              const err = payloadError(r.flxSocialPostDelete?.errors);
              if (err) s.toast(err, 'error');
              else {
                s.toast(t('Post deleted.'));
                s.refreshPosts();
              }
            },
            onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
          },
        ),
    });
  };
  const rowDup = (p: Post) =>
    dup.mutate(
      { input: { id: p.id } },
      {
        onSuccess: (r) => {
          const np = r.flxSocialPostDuplicate?.post;
          const err = payloadError(r.flxSocialPostDuplicate?.errors);
          if (err || !np) s.toast(err ?? t('Something went wrong.'), 'error');
          else {
            s.toast(t('Copy created as a draft.'));
            s.refreshPosts();
            s.edit(np);
          }
        },
        onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
      },
    );

  const bulkActions: { action: FlxSocialBulkAction; label: string; show: boolean; danger?: boolean }[] = [
    { action: 'APPROVE', label: t('Approve'), show: cfg.canPublish && (approvals || tab === 'all') },
    { action: 'SUBMIT', label: t('Send for approval'), show: !cfg.canPublish },
    { action: 'SCHEDULE', label: t('Schedule'), show: cfg.canPublish && !approvals },
    { action: 'NOW', label: t('Share now'), show: cfg.canPublish && !approvals },
    { action: 'DRAFT', label: t('Back to draft'), show: true },
    { action: 'CANCEL', label: t('Cancel'), show: true },
    { action: 'DELETE', label: t('Delete'), show: true, danger: true },
  ];

  return (
    <div className="flxsm-posts">
      {approvals ? <p className="flxsm-lead">{t('Posts waiting for someone who can publish to approve them.')}</p> : (
        <div className="flxsm-tabs" role="tablist">
          {TABS.map((x) => (
            <button key={x.id} type="button" role="tab" aria-selected={tab === x.id} className={'flxsm-tab' + (tab === x.id ? ' is-on' : '')} onClick={() => setTab(x.id)}>
              {t(x.label)}
            </button>
          ))}
        </div>
      )}
      <div className="flxsm-filters">
        <input type="search" value={search} placeholder={t('Search posts')} aria-label={t('Search posts')} onChange={(e) => setSearch(e.target.value)} />
        <select value={account} aria-label={t('Account')} onChange={(e) => setAccount(e.target.value)}>
          <option value="">{t('All accounts')}</option>
          {s.accounts.map((a) => (
            <option key={a.id} value={a.databaseId}>
              {a.name} · {s.net(a.provider)?.label ?? a.provider}
            </option>
          ))}
        </select>
        <label className="flxsm-chk">
          <input type="checkbox" checked={mine} onChange={(e) => setMine(e.target.checked)} />
          <span>{t('Only mine')}</span>
        </label>
        <span className="flxsm-grow" />
        <span className="flxsm-muted">{t('{n} posts', { n: total })}</span>
      </div>

      {sel.length > 0 && (
        <div className="flxsm-bulk" role="toolbar">
          <strong>{t('{n} selected', { n: sel.length })}</strong>
          {bulkActions
            .filter((b) => b.show)
            .map((b) => (
              <button key={b.action} type="button" className={'flxsm-btn' + (b.danger ? ' is-danger' : '')} disabled={bulk.isPending} onClick={() => runBulk(b.action)}>
                {b.label}
              </button>
            ))}
          <button type="button" className="flxsm-btn is-ghost" onClick={() => setSel([])}>
            {t('Clear')}
          </button>
        </div>
      )}

      {list.isLoading ? (
        <Loading />
      ) : list.isError ? (
        <div className="flxsm-notice is-error">{errorText(list.error, t('Something went wrong.'))}</div>
      ) : !rows.length ? (
        <div className="flxsm-empty">
          <h2>{approvals ? t('Nothing waiting for approval') : t('No posts here')}</h2>
          {!approvals && (
            <button type="button" className="flxsm-btn is-primary" onClick={() => s.edit(null)}>
              + {t('New post')}
            </button>
          )}
        </div>
      ) : (
        <div className="flxsm-card flxsm-table-wrap">
          <table className="flxsm-table">
            <thead>
              <tr>
                <th className="flxsm-c-chk">
                  <input type="checkbox" aria-label={t('Select all')} checked={allOn} onChange={(e) => setSel(e.target.checked ? rows.map((p) => p.id) : [])} />
                </th>
                <th>{t('Post')}</th>
                <th>{t('Accounts')}</th>
                <th>{t('Status')}</th>
                <th>{t('Time')}</th>
                <th aria-label={t('Actions')} />
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => {
                const thumb = p.media[0]?.thumb || (p.media[0]?.kind === 'image' ? p.media[0].url : '');
                const ts = postTime(p);
                return (
                  <tr key={p.id} className={sel.includes(p.id) ? 'is-sel' : ''}>
                    <td className="flxsm-c-chk">
                      <input type="checkbox" aria-label={t('Select')} checked={sel.includes(p.id)} onChange={(e) => setSel(e.target.checked ? [...sel, p.id] : sel.filter((x) => x !== p.id))} />
                    </td>
                    <td>
                      <button type="button" className="flxsm-post" onClick={() => s.detail(p)}>
                        {thumb ? <img src={thumb} alt="" width={44} height={44} loading="lazy" /> : <span className="flxsm-thumb-empty" aria-hidden />}
                        <span>
                          <strong>{excerpt(p, t)}</strong>
                          <small className="flxsm-muted">
                            {p.author}
                            {p.source === 'ai' ? ' · AI' : p.source === 'app' ? ' · ' + t('Automatic') : ''}
                          </small>
                        </span>
                      </button>
                    </td>
                    <td>
                      <span className="flxsm-nets">
                        {p.targets.map((x) => (
                          <span key={x.id} className={'flxsm-tg is-' + x.status.toLowerCase()} title={x.account + ' · ' + x.status}>
                            <NetDot provider={x.provider} size={20} />
                          </span>
                        ))}
                      </span>
                    </td>
                    <td>
                      <StatusBadge status={p.status} label={t(STATUS_KEYS[p.status])} />
                    </td>
                    <td className="flxsm-nowrap">{ts ? s.fmt(ts) : '—'}</td>
                    <td className="flxsm-c-act">
                      <button type="button" className="flxsm-btn is-ghost" onClick={() => s.edit(p)}>
                        {t('Edit')}
                      </button>
                      <button type="button" className="flxsm-btn is-ghost" onClick={() => rowDup(p)}>
                        {t('Duplicate')}
                      </button>
                      <button type="button" className="flxsm-btn is-ghost is-danger-text" onClick={() => rowDelete(p)}>
                        {t('Delete')}
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      {list.data?.flxSocialPosts.pageInfo.hasNextPage && (
        <div className="flxsm-more">
          <button type="button" className="flxsm-btn" disabled={list.isFetching} onClick={() => setLimit((n) => n + PAGE)}>
            {list.isFetching ? t('Loading…') : t('Load more')}
          </button>
        </div>
      )}
    </div>
  );
}
