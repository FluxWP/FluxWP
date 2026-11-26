// Posts list and the approvals queue: tabs, search and filters, bulk actions, row actions, load more.
import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSocialPostBulkAct, FlxSocialPostDelete, FlxSocialPostDuplicate, FlxSocialPosts } from '@fluxwp/graphql/operations';
import type { FlxSocialBulkAction, FlxSocialPostStatus } from '@fluxwp/graphql/types';
import { Icon, Loading } from '@fluxwp/ui';
import { NetDot, StatusBadge, STATUS_KEYS, errorText, excerpt, payloadError, postTime, useSocial, type Post, type Query } from './common';

const PAGE = 20;
const TABS: { id: string; label: string; status: FlxSocialPostStatus[] }[] = [
  { id: 'all', label: 'All', status: [] },
  { id: 'draft', label: 'Drafts', status: ['DRAFT'] },
  { id: 'pending', label: 'Awaiting approval', status: ['PENDING'] },
  { id: 'scheduled', label: 'Scheduled', status: ['SCHEDULED', 'PUBLISHING'] },
  { id: 'published', label: 'Published', status: ['PUBLISHED', 'PARTIAL'] },
  { id: 'failed', label: 'Failed', status: ['FAILED', 'PARTIAL'] },
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
    if (action === 'DELETE')
      s.confirm({ title: t('Delete posts'), text: t('{n} posts will be deleted from the panel. Posts already shared stay on the networks.', { n: sel.length }), action: t('Delete'), danger: true, run: go });
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

  const head = s.topSlot
    ? createPortal(
        <label className="flxsm-searchbox">
          <Icon name="search" size={16} />
          <input type="search" className="flxsm-search" value={search} placeholder={t('Search text, title or tag')} aria-label={t('Search posts')} onChange={(e) => setSearch(e.target.value)} />
        </label>,
        s.topSlot,
      )
    : null;

  return (
    <div className="flxsm-posts">
      {head}
      {approvals ? (
        <p className="flxsm-muted flxsm-lead">{t('Posts waiting for someone who can publish to approve them.')}</p>
      ) : (
        <div className="flxsm-toolbar">
          <div className="flxsm-tabs" role="tablist">
            {TABS.map((x) => (
              <button key={x.id} type="button" role="tab" aria-selected={tab === x.id} className={'flxsm-tab' + (tab === x.id ? ' is-on' : '')} onClick={() => setTab(x.id)}>
                {t(x.label)}
              </button>
            ))}
          </div>
          <div className="flxsm-row">
            <select className="flxsm-sel" value={account} aria-label={t('Account')} onChange={(e) => setAccount(e.target.value)}>
              <option value="">{t('All accounts')}</option>
              {s.accounts.map((a) => (
                <option key={a.id} value={a.databaseId}>
                  {(s.net(a.provider)?.label ?? a.provider) + ' › ' + a.name}
                </option>
              ))}
            </select>
            <label className="flxsm-chk">
              <input type="checkbox" checked={mine} onChange={(e) => setMine(e.target.checked)} />
              <span>{t('Only mine')}</span>
            </label>
          </div>
        </div>
      )}

      {sel.length > 0 && (
        <div className="flxsm-bulk" role="toolbar">
          <span>{t('{n} selected', { n: sel.length })}</span>
          {bulkActions
            .filter((b) => b.show)
            .map((b) => (
              <button key={b.action} type="button" className={'flxsm-btn' + (b.danger ? ' is-danger' : '')} disabled={bulk.isPending} onClick={() => runBulk(b.action)}>
                {b.label}
              </button>
            ))}
          <button type="button" className="flxsm-link" onClick={() => setSel([])}>
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
          <Icon name={approvals ? 'check' : 'inbox'} size={28} />
          <h3>{approvals ? t('Nothing waiting for approval') : t('No posts')}</h3>
          {!approvals && <p>{t('Create your first post; you can share to several accounts at once, with a separate text for each.')}</p>}
          {!approvals && (
            <button type="button" className="flxsm-btn is-primary" onClick={() => s.edit(null)}>
              <Icon name="plus-alt" size={15} /> {t('New post')}
            </button>
          )}
        </div>
      ) : (
        <div className="flxsm-plist">
          {rows.map((p) => {
            const m = p.media[0];
            const thumb = m ? m.thumb || (m.kind === 'image' ? m.url : '') : '';
            const ts = postTime(p);
            return (
              <div key={p.id} className={'flxsm-prow' + (sel.includes(p.id) ? ' is-sel' : '')} onClick={() => s.detail(p)}>
                <label className="flxsm-pck" onClick={(e) => e.stopPropagation()}>
                  <input type="checkbox" aria-label={t('Select')} checked={sel.includes(p.id)} onChange={(e) => setSel(e.target.checked ? [...sel, p.id] : sel.filter((x) => x !== p.id))} />
                </label>
                <div className={'flxsm-pthumb' + (thumb ? '' : ' is-text')}>
                  {thumb ? <img src={thumb} alt="" loading="lazy" /> : <Icon name="comment" size={18} />}
                  {m?.kind === 'video' && (
                    <span className="flxsm-pplay">
                      <Icon name="play" size={12} />
                    </span>
                  )}
                </div>
                <div className="flxsm-pbody">
                  <div className="flxsm-ptext">{excerpt(p, t)}</div>
                  <div className="flxsm-pmeta">
                    <StatusBadge status={p.status} label={t(STATUS_KEYS[p.status])} />
                    {ts > 0 && (
                      <span>
                        <Icon name="clock" size={13} />
                        {s.fmt(ts)}
                      </span>
                    )}
                    {p.author && <span>{p.author}</span>}
                    {p.repeatDays > 0 && (
                      <span>
                        <Icon name="update" size={13} />
                        {t('Every {n} days', { n: p.repeatDays })}
                      </span>
                    )}
                    {p.note && p.status === 'DRAFT' && (
                      <span className="flxsm-pnote">
                        <Icon name="comment" size={13} />
                        {p.note}
                      </span>
                    )}
                  </div>
                </div>
                <span className="flxsm-nets">
                  {p.targets.map((x) => (
                    <span key={x.id} className={'flxsm-tg is-' + x.status.toLowerCase()} title={x.account + ' · ' + x.status}>
                      <NetDot provider={x.provider} size={20} />
                    </span>
                  ))}
                </span>
                <RowMenu
                  label={t('Actions')}
                  items={[
                    { label: t('Edit'), run: () => s.edit(p) },
                    { label: t('Duplicate'), run: () => rowDup(p) },
                    { label: t('Delete'), run: () => rowDelete(p), danger: true },
                  ]}
                />
              </div>
            );
          })}
        </div>
      )}
      {list.data?.flxSocialPosts.pageInfo.hasNextPage && (
        <button type="button" className="flxsm-btn flxsm-more" disabled={list.isFetching} onClick={() => setLimit((n) => n + PAGE)}>
          {list.isFetching ? t('Loading…') : t('Load more')}
        </button>
      )}
    </div>
  );
}

/** Row "…" menu (classic dots button with a small popover). */
function RowMenu({ label, items }: { label: string; items: { label: string; run: () => void; danger?: boolean }[] }) {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    if (!open) return;
    const close = () => setOpen(false);
    document.addEventListener('click', close);
    return () => document.removeEventListener('click', close);
  }, [open]);
  return (
    <span className="flxsm-rmenu" onClick={(e) => e.stopPropagation()}>
      <button type="button" className="flxsm-iconbtn" aria-label={label} title={label} aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        <span aria-hidden="true">⋯</span>
      </button>
      {open && (
        <span className="flxsm-rmenu-pop" role="menu">
          {items.map((it) => (
            <button
              key={it.label}
              type="button"
              role="menuitem"
              className={it.danger ? 'is-danger' : ''}
              onClick={() => {
                setOpen(false);
                it.run();
              }}
            >
              {it.label}
            </button>
          ))}
        </span>
      )}
    </span>
  );
}
