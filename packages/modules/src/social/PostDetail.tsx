// Post details: text, media, one row per account (status, error, link, numbers) and the actions.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxSocialPost,
  FlxSocialPostAct,
  FlxSocialPostDelete,
  FlxSocialPostDuplicate,
  FlxSocialTargetRemoteDelete,
  FlxSocialTargetRemoteEdit,
  FlxSocialTargetRetry,
} from '@fluxwp/graphql/operations';
import type { FlxSocialPostAction } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Avatar, NetDot, StatusBadge, STATUS_KEYS, TARGET_KEYS, errorText, excerpt, num, payloadError, postTime, useSocial, type Post, type Target } from './common';

export function PostDetail({ postId, initial, onClose }: { postId: number; initial: Post; onClose: () => void }) {
  const s = useSocial();
  const { t, locale } = s;
  const cfg = s.boot.flxSocialConfig;
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxSocialPost, { id: String(postId) }, { placeholderData: { flxSocialPost: initial } });
  const p = q.data?.flxSocialPost ?? initial;
  const [reject, setReject] = useState(false);
  const [note, setNote] = useState('');
  const [edit, setEdit] = useState<Target | null>(null);

  const reload = () => {
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialPost'] });
    s.refreshPosts();
  };
  const fail = (e: unknown) => s.toast(errorText(e, t('Something went wrong.')), 'error');
  const handle = (errs: ReadonlyArray<{ message: string }> | undefined, ok: string, after?: () => void) => {
    const err = payloadError(errs);
    if (err) s.toast(err, 'error');
    else {
      s.toast(ok);
      reload();
      after?.();
    }
  };

  const act = useFlxMutation(FlxSocialPostAct, { onError: fail });
  const del = useFlxMutation(FlxSocialPostDelete, { onError: fail });
  const dup = useFlxMutation(FlxSocialPostDuplicate, { onError: fail });
  const retry = useFlxMutation(FlxSocialTargetRetry, { onError: fail });
  const rdel = useFlxMutation(FlxSocialTargetRemoteDelete, { onError: fail });

  const run = (action: FlxSocialPostAction, ok: string, n?: string) =>
    act.mutate({ input: { id: p.id, action, ...(n ? { note: n } : {}) } }, { onSuccess: (r) => handle(r.flxSocialPostAct?.errors, ok, () => setReject(false)) });

  const tm = postTime(p);
  const canPublish = cfg.canPublish;
  const canEdit = p.status !== 'PUBLISHING';
  const metric = (n: number, label: string) => (n ? <span key={label} title={label}>{label} {num(n, locale)}</span> : null);

  return (
    <Modal title={t('Post')} onClose={onClose} wide>
      {q.isLoading && !p ? (
        <Loading />
      ) : (
        <div className="flxsm-detail">
          <div className="flxsm-detail-h">
            <StatusBadge status={p.status} label={t(STATUS_KEYS[p.status])} />
            {tm > 0 && <span className="flxsm-muted">{s.fmt(tm)}</span>}
            {p.author && <span className="flxsm-muted">{p.author}</span>}
            {p.approvedBy && <span className="flxsm-muted">{t('Approved by {name}', { name: p.approvedBy })}</span>}
            <span className="flxsm-grow" />
            {canEdit && (
              <button
                type="button"
                className="flxsm-btn"
                onClick={() => {
                  onClose();
                  s.edit(p);
                }}
              >
                {t('Edit')}
              </button>
            )}
            <button
              type="button"
              className="flxsm-btn"
              disabled={dup.isPending}
              onClick={() =>
                dup.mutate(
                  { input: { id: p.id } },
                  {
                    onSuccess: (r) => {
                      const np = r.flxSocialPostDuplicate?.post;
                      handle(r.flxSocialPostDuplicate?.errors, t('Copy created as a draft.'), () => {
                        if (np) {
                          onClose();
                          s.edit(np);
                        }
                      });
                    },
                  },
                )
              }
            >
              {t('Duplicate')}
            </button>
          </div>
          {p.title && <h3>{p.title}</h3>}
          {p.content && <div className="flxsm-detail-text">{p.content}</div>}
          {p.media.length > 0 && (
            <div className="flxsm-detail-media">
              {p.media.map((m) => (
                <a key={m.databaseId} href={m.url} target="_blank" rel="noopener noreferrer">
                  <img src={m.thumb || m.url} alt={m.alt || ''} />
                </a>
              ))}
            </div>
          )}
          {p.note && <div className="flxsm-notice">{p.note}</div>}
          {p.tags.length > 0 && <p className="flxsm-muted">{p.tags.map((x) => '#' + x).join(' ')}</p>}
          {p.repeatDays > 0 && <p className="flxsm-muted">{t('Shared again every {n} days.', { n: p.repeatDays })}</p>}

          <ul className="flxsm-tlist">
            {p.targets.map((x) => (
              <li key={x.id} className={'flxsm-trow is-' + x.status.toLowerCase()}>
                <div className="flxsm-trow-m">
                  <Avatar src={x.avatar} name={x.account} size={32} />
                  <NetDot provider={x.provider} size={16} />
                  <div>
                    <strong>{x.account}</strong>
                    <small className="flxsm-muted">
                      {t(TARGET_KEYS[x.status])}
                      {x.publishedAt?.timestamp ? ' · ' + s.fmt(x.publishedAt.timestamp) : ''}
                    </small>
                    {x.error && <small className="flxsm-bad">{x.error}</small>}
                    <small className="flxsm-stats">
                      {metric(x.metrics.views || x.metrics.impressions || x.metrics.reach, t('Views'))}
                      {metric(x.metrics.likes, t('Likes'))}
                      {metric(x.metrics.comments, t('Comments'))}
                      {metric(x.metrics.shares, t('Shares'))}
                    </small>
                  </div>
                </div>
                <div className="flxsm-trow-a">
                  {x.remoteUrl && (
                    <a className="flxsm-btn is-ghost" href={x.remoteUrl} target="_blank" rel="noopener noreferrer" onClick={(e) => { if (s.appMode) { e.preventDefault(); s.openUrl(x.remoteUrl); } }}>
                      {t('Open')}
                    </a>
                  )}
                  {canPublish && x.status === 'FAILED' && (
                    <button
                      type="button"
                      className="flxsm-btn"
                      disabled={retry.isPending}
                      onClick={() => retry.mutate({ input: { id: x.id } }, { onSuccess: (r) => handle(r.flxSocialTargetRetry?.errors, t('Queued again.')) })}
                    >
                      {t('Try again')}
                    </button>
                  )}
                  {canPublish && x.status === 'PUBLISHED' && x.caps.edit && (
                    <button type="button" className="flxsm-btn" onClick={() => setEdit(x)}>
                      {t('Edit on the network')}
                    </button>
                  )}
                  {canPublish && x.status === 'PUBLISHED' && x.caps.delete && (
                    <button
                      type="button"
                      className="flxsm-btn is-danger"
                      onClick={() =>
                        s.confirm({
                          title: t('Delete from network'),
                          text: t('The post on {name} is deleted for good.', { name: x.account }),
                          action: t('Delete'),
                          danger: true,
                          run: () => rdel.mutate({ input: { id: x.id } }, { onSuccess: (r) => handle(r.flxSocialTargetRemoteDelete?.errors, t('Deleted on the network.')) }),
                        })
                      }
                    >
                      {t('Delete from network')}
                    </button>
                  )}
                </div>
              </li>
            ))}
          </ul>

          <div className="flxsm-actions">
            {p.status === 'PENDING' && canPublish && (
              <>
                <button type="button" className="flxsm-btn is-primary" disabled={act.isPending} onClick={() => run('APPROVE', t('Approved.'))}>
                  {t('Approve')}
                </button>
                <button type="button" className="flxsm-btn" onClick={() => setReject(true)}>
                  {t('Send back')}
                </button>
              </>
            )}
            {(p.status === 'DRAFT' || p.status === 'FAILED' || p.status === 'CANCELLED') && canPublish && p.targets.length > 0 && (
              <button type="button" className="flxsm-btn is-primary" disabled={act.isPending} onClick={() => run('NOW', t('Queued; it goes out in a moment.'))}>
                {t('Share now')}
              </button>
            )}
            {(p.status === 'SCHEDULED' || p.status === 'PENDING') && (
              <button type="button" className="flxsm-btn" disabled={act.isPending} onClick={() => run('CANCEL', t('Cancelled.'))}>
                {t('Cancel')}
              </button>
            )}
            {p.status === 'SCHEDULED' && (
              <button type="button" className="flxsm-btn" disabled={act.isPending} onClick={() => run('DRAFT', t('Moved back to drafts.'))}>
                {t('Back to draft')}
              </button>
            )}
            <span className="flxsm-grow" />
            <button
              type="button"
              className="flxsm-btn is-danger"
              onClick={() =>
                s.confirm({
                  title: t('Delete post'),
                  text: t('"{name}" is deleted from the panel. Posts already shared stay on the networks.', { name: excerpt(p, t).slice(0, 60) }),
                  action: t('Delete'),
                  danger: true,
                  run: () => del.mutate({ input: { id: p.id } }, { onSuccess: (r) => handle(r.flxSocialPostDelete?.errors, t('Post deleted.'), onClose) }),
                })
              }
            >
              {t('Delete')}
            </button>
          </div>
        </div>
      )}

      {reject && (
        <Modal title={t('Send back to draft')} onClose={() => setReject(false)}>
          <label className="flxsm-field">
            <span>{t('Note for the author')}</span>
            <textarea rows={3} value={note} autoFocus onChange={(e) => setNote(e.target.value)} />
          </label>
          <div className="flxsm-actions">
            <button type="button" className="flxsm-btn" onClick={() => setReject(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxsm-btn is-primary" disabled={act.isPending} onClick={() => run('REJECT', t('Sent back.'), note)}>
              {t('Send back')}
            </button>
          </div>
        </Modal>
      )}
      {edit && <RemoteEdit target={edit} onClose={() => setEdit(null)} onDone={() => { setEdit(null); reload(); }} />}
    </Modal>
  );
}

/** Change the text of a post that is already live (networks that allow it). */
function RemoteEdit({ target, onClose, onDone }: { target: Target; onClose: () => void; onDone: () => void }) {
  const s = useSocial();
  const { t } = s;
  const [text, setText] = useState(target.content || '');
  const m = useFlxMutation(FlxSocialTargetRemoteEdit, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialTargetRemoteEdit?.errors);
      if (err) s.toast(err, 'error');
      else {
        s.toast(t('Updated on the network.'));
        onDone();
      }
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
  return (
    <Modal title={t('Edit on the network')} onClose={onClose}>
      <p className="flxsm-muted">{t('Changes the live post on {name}.', { name: target.account })}</p>
      <textarea rows={6} value={text} onChange={(e) => setText(e.target.value)} aria-label={t('Text')} />
      <div className="flxsm-actions">
        <button type="button" className="flxsm-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        <button type="button" className="flxsm-btn is-primary" disabled={!text.trim() || m.isPending} onClick={() => m.mutate({ input: { id: target.id, content: text } })}>
          {t('Save')}
        </button>
      </div>
    </Modal>
  );
}
