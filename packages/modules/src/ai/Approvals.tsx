// Flux AI › Awaiting approval: the actions the assistant prepared in chats and scheduled tasks, and open change
// requests. Nothing changes on the site until the user approves; the input can be edited first.
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiApprovalDecide, FlxAiApprovals, FlxAiChangeDecide } from '@fluxwp/graphql/operations';
import type { FlxAiApprovalQueueFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, errText, useAi, type Notice, Lead } from './common';

type Item = FlxAiApprovalQueueFieldsFragment['items'][number];

export function Approvals() {
  const { t, num } = useAi();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [notice, setNotice] = useState<Notice | null>(null);
  const [edits, setEdits] = useState<Record<string, string>>({});
  const q = useFlxQuery(FlxAiApprovals, {});
  const store = (queue: FlxAiApprovalQueueFieldsFragment | null | undefined) => {
    if (queue) qc.setQueryData(flxQueryKey(scope, FlxAiApprovals, {}), { flxAiApprovals: queue });
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const decide = useFlxMutation(FlxAiApprovalDecide, {
    onSuccess: (r, vars) => {
      const p = r.flxAiApprovalDecide;
      if (!p || p.errors.length) return setNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      store(p.queue);
      const msg = !vars.input.approve ? 'Rejected; nothing was changed.' : p.state === 'continue' ? 'Approved. The assistant is still working; open the chat to follow.' : "Approved and done. The assistant's reply is in the chat.";
      setNotice({ kind: 'success', text: t(msg) });
    },
    onError: fail,
  });
  const change = useFlxMutation(FlxAiChangeDecide, {
    onSuccess: (r, vars) => {
      const p = r.flxAiChangeDecide;
      if (!p || p.errors.length) return setNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      store(p.queue);
      setNotice({ kind: 'success', text: vars.input.merge ? t('Merged; the files were changed.') : t('Rejected; nothing was changed.') });
    },
    onError: fail,
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const { items, changes } = q.data.flxAiApprovals;
  const adminUrl = host.adminUrl ?? '';
  const chatUrl = (id: number, extra = '') => `${adminUrl}admin.php?page=flx-ai-chat&flxai_chat=${id}${extra}`;
  const busy = decide.isPending || change.isPending;
  const run = (it: Item, approve: boolean, withEdit = false) => decide.mutate({ input: { chatId: it.chatId, callId: it.callId, approve, args: withEdit ? edits[it.callId] ?? it.args : null } });
  const ago = (l: string) => l;

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <Lead text={t('Actions the assistant prepared in your chats and scheduled tasks. Nothing changes on the site until you approve. You can edit the input before approving.')} />
      {changes.map((c) => (
        <Card key={'pr' + c.id}>
          <header className="flxai-appr-head">
            <div>
              <div className="flxai-appr-title">{t('Change request #%s', num(c.id))} · {c.title}</div>
              <div className="flxai-hint">
                {c.taskId ? <Pill kind="ok">{t('Scheduled task')}</Pill> : <Pill>{t('Chat')}</Pill>} {c.taskTitle} · {ago(c.created.label)}
              </div>
            </div>
            <a className="flxai-btn is-small" href={chatUrl(c.chatId, `&flxai_ws=${c.id}`)}>
              {t('Review the diff')}
            </a>
          </header>
          <ul className="flxai-pr-files">
            {c.files.map((f) => (
              <li key={f.path}>
                <Pill>{f.op === 'add' ? t('added') : f.op === 'delete' ? t('deleted') : t('changed')}</Pill> <code>{f.path}</code>{' '}
                <span className="flxai-pr-n">
                  <b>+{num(f.add)}</b> <i>−{num(f.del)}</i>
                </span>
              </li>
            ))}
          </ul>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn is-primary" disabled={busy} onClick={() => change.mutate({ input: { id: c.id, merge: true } })}>
              {t('Merge')}
            </button>
            <button type="button" className="flxai-btn" disabled={busy} onClick={() => change.mutate({ input: { id: c.id, merge: false } })}>
              {t('Reject')}
            </button>
          </div>
        </Card>
      ))}
      {!items.length && !changes.length && (
        <Card>
          <p>
            <strong>{t('Nothing is waiting for approval.')}</strong>
          </p>
          <p className="flxai-hint">{t('When the assistant or a scheduled task wants to change something (create a draft, update a setting, send a message), it appears here.')}</p>
        </Card>
      )}
      {items.map((it) => {
        const open = it.callId in edits;
        return (
          <Card key={it.callId}>
            <header className="flxai-appr-head">
              <div>
                <div className="flxai-appr-title">
                  {it.title} <code>{it.tool}</code>
                </div>
                <div className="flxai-hint">
                  {it.taskId ? <Pill kind="ok">{t('Scheduled task')}</Pill> : <Pill>{t('Chat')}</Pill>} {it.taskId ? it.taskTitle : it.chatTitle || t('New chat')} · {ago(it.time.label)}
                </div>
              </div>
              <a className="flxai-btn is-small" href={chatUrl(it.chatId)}>
                {t('Open in chat')}
              </a>
            </header>
            {it.note && <p className="flxai-appr-note">{it.note}</p>}
            <div className="flxai-tl-k">{t('Input')}</div>
            <pre className="flxai-tl-pre">{it.args.slice(0, 4000)}</pre>
            <div className="flxai-appr-acts">
              <button type="button" className="flxai-btn is-primary" disabled={busy} onClick={() => run(it, true)}>
                {t('Approve')}
              </button>
              <button type="button" className="flxai-btn" disabled={busy} onClick={() => run(it, false)}>
                {t('Reject')}
              </button>
              <button type="button" className="flxai-btn" disabled={busy} onClick={() => setEdits((e) => (open ? Object.fromEntries(Object.entries(e).filter(([k]) => k !== it.callId)) : { ...e, [it.callId]: it.args }))}>
                {t('Edit and approve')}
              </button>
            </div>
            {open && (
              <div className="flxai-appr-edit">
                <textarea className="flxai-input" rows={Math.min(18, Math.max(5, it.args.split('\n').length + 2))} spellCheck={false} value={edits[it.callId]} onChange={(e) => setEdits((s) => ({ ...s, [it.callId]: e.target.value }))} />
                <p className="flxai-hint">{t('Change the values (JSON) and approve; the action runs with your version.')}</p>
                <button type="button" className="flxai-btn is-primary" disabled={busy} onClick={() => run(it, true, true)}>
                  {t('Approve with changes')}
                </button>
              </div>
            )}
          </Card>
        );
      })}
    </>
  );
}
