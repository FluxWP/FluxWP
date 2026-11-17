// The conversation: user messages, assistant turns (reasoning + tools folded, answer open), approvals and errors.
import { Brain, ChevronDown, Clock, FileText, Pencil, RotateCcw, Sparkles, Undo2, Wrench, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAi } from '../common';
import type { ChatApi } from './api';
import { Approval } from './Approval';
import { Markdown } from './Markdown';
import { argLabel, buildTurns, pretty, type AiTurn } from './turns';
import type { Boot, ChatFile, Decisions, Msg, Pending } from './types';

/** Image attachments are fetched with the host's credentials and shown from a blob URL. */
function useFileUrl(api: ChatApi, f: ChatFile | { url: string }): string {
  const key = 'key' in f ? f.key : '';
  const [url, setUrl] = useState('url' in f ? f.url : '');
  useEffect(() => {
    if (!key) return;
    let live = true;
    let made = '';
    api.file(key).then((b) => {
      if (!live) return;
      made = URL.createObjectURL(b);
      setUrl(made);
    }, () => {});
    return () => {
      live = false;
      if (made) URL.revokeObjectURL(made);
    };
  }, [api, key]);
  return url;
}

export function FileChip({ api, f, onRemove }: { api: ChatApi; f: { name: string; type?: string; key?: string; url?: string }; onRemove?: () => void }) {
  const { t } = useAi();
  const isImg = /^image\//.test(f.type || '');
  const url = useFileUrl(api, f.key ? { key: f.key, name: f.name } : { url: f.url || '' });
  return (
    <span className={'flxai-fchip' + (isImg ? ' is-img' : '')} title={f.name}>
      {isImg && url ? <img src={url} alt="" /> : <FileText size={16} />}
      <span className="flxai-fn">{f.name}</span>
      {onRemove && (
        <button type="button" className="flxai-frm" aria-label={t('Remove')} onClick={onRemove}>
          <X size={12} />
        </button>
      )}
    </span>
  );
}

export interface BodyProps {
  api: ChatApi;
  boot: Boot | null;
  msgs: Msg[];
  running: boolean;
  optimistic: string | null;
  optFiles: { name: string; type?: string; url?: string }[];
  err: string | null;
  canRetry: boolean;
  onRetry: () => void;
  pending: Pending[];
  decisions: Decisions;
  onDecision: (id: string, d: 'approve' | 'reject') => void;
  onAll: (d: 'approve' | 'reject') => void;
  onContinue: () => void;
  editId: string;
  editText: string;
  setEditText: (s: string) => void;
  onEdit: (id: string) => void;
  onEditCancel: () => void;
  onEditSend: () => void;
  onRewind: (id: string) => void;
  onSuggest: (text: string) => void;
  /** Wrapped by the screen: opens the workspace change / report links of a turn (workspace panel). */
  onChange?: (id: number) => void;
  onReport?: (path: string) => void;
}

function msText(v: number, t: ReturnType<typeof useAi>['t'], num: (n: number, d?: number) => string): string {
  if (v < 1000) return t('%s ms', num(Math.round(v)));
  const s = v / 1000;
  if (s < 60) return t('%s s', num(s, 1));
  return t('%1$s min %2$s s', Math.floor(s / 60), Math.round(s % 60));
}

function ToolRow({ w, boot, pending }: { w: Extract<AiTurn['work'][number], { k: 'tool' }>; boot: Boot | null; pending: Pending[] }) {
  const { t, num } = useAi();
  const [open, setOpen] = useState(false);
  const r = w.res;
  const meta = boot?.tools[w.name];
  const state = !r ? 'run' : r._rejected ? 'rej' : r._ok === false ? 'err' : 'ok';
  const info = r ? (r._rejected ? t('rejected') : msText(Number(r._ms) || 0, t, num)) : pending.some((p) => p.id === w.id) ? t('awaiting approval') : '';
  const lbl = argLabel(w.args);
  let res = r ? pretty(r.content) : '';
  if (res.length > 6000) res = res.slice(0, 6000) + '\n…';
  return (
    <div>
      <button type="button" className={'flxai-tchip' + (state === 'err' ? ' is-err' : state === 'rej' ? ' is-rej' : '') + (meta?.write ? ' is-write' : '')} aria-expanded={open} onClick={() => setOpen(!open)}>
        <span className="flxai-tico">{state === 'run' ? <span className="flxai-spin" /> : meta?.write ? <Pencil size={13} /> : <Wrench size={13} />}</span>
        <b>{meta?.title || w.name}</b>
        {lbl && <small>{lbl}</small>}
        {info && <span className="flxai-tms">{info}</span>}
      </button>
      {open && (
        <div className="flxai-tdetail">
          <div>
            <label>{t('Tool')}</label>
            <pre>{w.name}</pre>
          </div>
          {w.args && Object.keys(w.args).length > 0 && (
            <div>
              <label>{t('Input')}</label>
              <pre>{pretty(w.args)}</pre>
            </div>
          )}
          <div>
            <label>{t('Result')}</label>
            <pre>{res || t('No result')}</pre>
          </div>
        </div>
      )}
    </div>
  );
}

function AiTurnView({ x, live, p }: { x: AiTurn; live: boolean; p: BodyProps }) {
  const { t, tn, num } = useAi();
  const [open, setOpen] = useState<boolean | null>(null);
  const isOpen = open ?? (live && !x.answer);
  const md = (text: string, running: boolean) => <Markdown text={text} mermaid={p.boot?.mermaid ?? ''} widgets={!!p.boot?.widgets} running={running} />;
  let label = live ? (x.calls ? t('Working') : t('Thinking')) : x.ms > 0 ? t('Thought for %s', msText(x.ms, t, num)) : t('Steps');
  if (x.calls) label += ' · ' + tn('1 tool', '%s tools', x.calls, num(x.calls));
  if (x.rejected) label += ' · ' + tn('1 rejected', '%s rejected', x.rejected, num(x.rejected));
  const changes = Object.values(x.ch);
  const reports = Object.values(x.rep);
  return (
    <div className="flxai-msg is-ai">
      <div className="flxai-b">
        {(x.work.length > 0 || live) && (
          <div className="flxai-work">
            <button type="button" className="flxai-work-btn" aria-expanded={isOpen} onClick={() => setOpen(!isOpen)}>
              {live ? <span className="flxai-spin" /> : <Brain size={14} />}
              <span className={live ? 'flxai-dots' : ''}>{label}</span>
              {x.failed > 0 && <span className="flxai-fail"> · {tn('1 error', '%s errors', x.failed, num(x.failed))}</span>}
              <ChevronDown size={14} className="flxai-chev" />
            </button>
            {isOpen && (
              <div className="flxai-work-list">
                {x.work.map((w, i) =>
                  w.k === 'think' ? (
                    <div key={i} className="flxai-think">{w.text}</div>
                  ) : w.k === 'note' ? (
                    <div key={i}>{md(w.text, false)}</div>
                  ) : (
                    <ToolRow key={w.id} w={w} boot={p.boot} pending={p.pending} />
                  ),
                )}
                {live && !x.work.length && <div className="flxai-think">{t('The model is preparing an answer…')}</div>}
              </div>
            )}
          </div>
        )}
        {x.answer && md(x.answer, live)}
        {changes.length > 0 && p.onChange && (
          <div className="flxai-chlinks">
            {changes.map((c) => (
              <button key={c.id + (c.pr ? 'p' : 'c')} type="button" className={'flxai-chlink' + (c.pr ? ' is-pr' : '')} onClick={() => p.onChange?.(c.id)}>
                <span>{c.pr ? t('Change request #%s', c.id) : t('Change in the assistant\'s folder')}</span>
                <b>{t('See the diff')}</b>
              </button>
            ))}
          </div>
        )}
        {reports.length > 0 && p.onReport && (
          <div className="flxai-chlinks">
            {reports.map((r) => (
              <button key={r.path} type="button" className="flxai-chlink" onClick={() => p.onReport?.(r.path)}>
                <span>
                  <strong>{r.title}</strong> <small>{t('Visual report')}</small>
                </span>
                <b>{t('Open')}</b>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export function Body(p: BodyProps) {
  const { t } = useAi();
  const turns = buildTurns(p.msgs);
  const last = turns[turns.length - 1];
  let lastUser: (typeof turns)[number] | undefined;
  for (const x of turns) if (x.kind === 'user') lastUser = x;
  const empty = !p.msgs.length && !p.optimistic && !p.running && !p.optFiles.length;
  if (empty)
    return (
      <div className="flxai-empty-chat">
        <span className="flxai-logo">
          <Sparkles size={22} />
        </span>
        <h3>{t('How can I help?')}</h3>
        <p>{p.boot?.canManage ? t('Content, settings, reports: everything your permissions allow. I ask for your approval before changing anything.') : t('Ask about this site or give me a task.')}</p>
        <div className="flxai-sugs">
          {(p.boot?.suggestions ?? []).map((s) => (
            <button key={s} type="button" className="flxai-sug" onClick={() => p.onSuggest(s)}>
              {s}
            </button>
          ))}
        </div>
      </div>
    );
  return (
    <>
      {turns.map((x, i) => {
        if (x.kind === 'err')
          return (
            <div key={'e' + i} className="flxai-msg is-err">
              <div className="flxai-b">⚠️ {x.m.content}</div>
            </div>
          );
        if (x.kind === 'ai') return <AiTurnView key={x.id} x={x} live={x === last && p.running && !p.optimistic} p={p} />;
        const m = x.m;
        const id = m._id || '';
        if (id && p.editId === id)
          return (
            <div key={id} className="flxai-msg is-user is-editing">
              <div className="flxai-edit">
                <textarea autoFocus rows={2} aria-label={t('Edit the message')} value={p.editText} onChange={(e) => p.setEditText(e.target.value)} />
                <div className="flxai-edit-f">
                  <span>{t('This message and everything after it is rewritten.')}</span>
                  <button type="button" className="flxai-btn" onClick={p.onEditCancel}>
                    {t('Cancel')}
                  </button>
                  <button type="button" className="flxai-btn is-primary" onClick={p.onEditSend}>
                    {t('Send')}
                  </button>
                </div>
              </div>
            </div>
          );
        const txt = m._show != null ? m._show : m.content || '';
        return (
          <div key={id || 'u' + i} className={'flxai-msg is-user' + (m._task ? ' is-task' : '')}>
            {id && !p.running && (
              <div className="flxai-acts">
                <button type="button" title={t('Edit')} aria-label={t('Edit')} onClick={() => p.onEdit(id)}>
                  <Pencil size={14} />
                </button>
                {x !== lastUser && (
                  <button type="button" title={t('Go back to this point')} aria-label={t('Go back to this point')} onClick={() => p.onRewind(id)}>
                    <Undo2 size={14} />
                  </button>
                )}
              </div>
            )}
            {(m._files?.length ?? 0) > 0 && (
              <div className="flxai-mfiles">
                {m._files!.map((f) => (
                  <FileChip key={f.key} api={p.api} f={f} />
                ))}
              </div>
            )}
            {(m._task || txt) && (
              <div className="flxai-b">
                {m._task && (
                  <div className="flxai-task" title={t('Scheduled task')}>
                    <Clock size={13} />
                    <span>{t('Scheduled task · %s', m._task.title || '')}</span>
                  </div>
                )}
                {txt}
              </div>
            )}
          </div>
        );
      })}
      {(p.optimistic || (p.optFiles.length > 0 && p.running)) && (
        <div className="flxai-msg is-user">
          {p.optFiles.length > 0 && (
            <div className="flxai-mfiles">
              {p.optFiles.map((f, i) => (
                <FileChip key={i} api={p.api} f={f} />
              ))}
            </div>
          )}
          {p.optimistic && <div className="flxai-b">{p.optimistic}</div>}
        </div>
      )}
      {p.running && (p.optimistic || !last || last.kind !== 'ai') && (
        <div className="flxai-msg is-ai">
          <div className="flxai-live">
            <span className="flxai-spin" />
            <span className="flxai-dots">{t('Thinking')}</span>
          </div>
        </div>
      )}
      {p.pending.length > 0 && !p.running && <Approval pending={p.pending} decisions={p.decisions} onDecision={p.onDecision} onAll={p.onAll} onContinue={p.onContinue} mermaid={p.boot?.mermaid ?? ''} widgets={!!p.boot?.widgets} />}
      {p.err && (
        <div className="flxai-msg is-err">
          <div className="flxai-b">
            ⚠️ {p.err}
            {p.canRetry && (
              <button type="button" className="flxai-btn" style={{ marginLeft: 6 }} onClick={p.onRetry}>
                <RotateCcw size={13} /> {t('Try again')}
              </button>
            )}
          </div>
        </div>
      )}
    </>
  );
}
