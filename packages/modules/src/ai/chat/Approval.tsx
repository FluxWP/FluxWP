// "Your approval is needed": the actions the assistant prepared, with a readable preview of every input.
import { Check, ChevronDown, TriangleAlert, X } from 'lucide-react';
import { useAi } from '../common';
import { Markdown } from './Markdown';
import { pretty } from './turns';
import type { Decisions, Pending } from './types';

const ARG_LABEL: Record<string, string> = {
  title: 'Title',
  excerpt: 'Excerpt',
  content: 'Content',
  body: 'Content',
  text: 'Text',
  message: 'Message',
  status: 'Status',
  post_type: 'Type',
  slug: 'Slug',
  date: 'Date',
  to: 'Recipient',
  subject: 'Subject',
  summary: 'Summary',
};

const isImgUrl = (k: string, v: unknown) => typeof v === 'string' && /^https?:\/\//.test(v) && (/image|img|photo|thumb/.test(k) || /\.(png|jpe?g|webp|gif|avif)(\?|$)/i.test(v));

function Preview({ a, mermaid, widgets }: { a: Record<string, unknown>; mermaid: string; widgets: boolean }) {
  const { t } = useAi();
  const label = (k: string) => (ARG_LABEL[k] ? t(ARG_LABEL[k]) : k.replace(/_/g, ' '));
  const keys = Object.keys(a);
  const used = new Set<string>();
  const title = typeof a.title === 'string' && a.title ? a.title : '';
  if (title) used.add('title');
  const short = keys.filter((k) => {
    const v = a[k];
    return !used.has(k) && (v == null || typeof v !== 'object') && !(typeof v === 'string' && (v.length > 140 || v.includes('\n'))) && !isImgUrl(k, v);
  }).slice(0, 8);
  short.forEach((k) => used.add(k));
  const imgs = keys.filter((k) => !used.has(k) && isImgUrl(k, a[k]));
  imgs.forEach((k) => used.add(k));
  const texts = keys.filter((k) => !used.has(k) && typeof a[k] === 'string');
  texts.forEach((k) => used.add(k));
  const lists = keys.filter((k) => !used.has(k) && Array.isArray(a[k]) && (a[k] as unknown[]).every((x) => x == null || typeof x !== 'object'));
  return (
    <>
      {title && <div className="flxai-ap-title">{title}</div>}
      {short.length > 0 && (
        <dl className="flxai-kv">
          {short.map((k) => (
            <div key={k}>
              <dt>{label(k)}</dt>
              <dd title={String(a[k])}>{String(a[k]).slice(0, 140)}</dd>
            </div>
          ))}
        </dl>
      )}
      {imgs.map((k) => (
        <div key={k} className="flxai-ap-img">
          <img src={String(a[k])} alt="" loading="lazy" referrerPolicy="no-referrer" />
          <small>{label(k)}</small>
        </div>
      ))}
      {texts.map((k) => {
        const v = String(a[k]);
        return (
          <div key={k} className="flxai-ap-text">
            <label>{label(k)}</label>
            <Markdown text={v.length > 12000 ? v.slice(0, 12000) + '\n\n…' : v} mermaid={mermaid} widgets={widgets} running={false} />
          </div>
        );
      })}
      {lists.length > 0 && (
        <dl className="flxai-kv">
          {lists.map((k) => (
            <div key={k}>
              <dt>{label(k)}</dt>
              <dd>{(a[k] as unknown[]).join(', ')}</dd>
            </div>
          ))}
        </dl>
      )}
    </>
  );
}

export function Approval({ pending, decisions, onDecision, onAll, onContinue, mermaid, widgets }: { pending: Pending[]; decisions: Decisions; onDecision: (id: string, d: 'approve' | 'reject') => void; onAll: (d: 'approve' | 'reject') => void; onContinue: () => void; mermaid: string; widgets: boolean }) {
  const { t } = useAi();
  const n = pending.length;
  const done = pending.filter((p) => decisions[p.id]).length;
  return (
    <div className="flxai-approve">
      <div className="flxai-approve-h">
        <TriangleAlert size={18} />
        <div>
          <b>{t('Your approval is needed')}</b>
          <p>{t('The assistant wants to make the changes below. None of them runs unless you approve it.')}</p>
        </div>
      </div>
      {pending.map((p) => {
        const d = decisions[p.id];
        const a = p.args && typeof p.args === 'object' ? p.args : {};
        return (
          <div key={p.id} className="flxai-call">
            <div className="flxai-call-h">
              <div className="flxai-call-t">
                {p.title}
                <small>
                  {p.name}
                  {p.source ? ' · ' + p.source : ''}
                </small>
              </div>
              <span className="flxai-seg">
                <button type="button" className={d === 'reject' ? 'is-on is-no' : ''} aria-pressed={d === 'reject'} onClick={() => onDecision(p.id, 'reject')}>
                  <X size={14} /> {t('Reject')}
                </button>
                <button type="button" className={d === 'approve' ? 'is-on' : ''} aria-pressed={d === 'approve'} onClick={() => onDecision(p.id, 'approve')}>
                  <Check size={14} /> {t('Approve')}
                </button>
              </span>
            </div>
            <Preview a={a} mermaid={mermaid} widgets={widgets} />
            {Object.keys(a).length > 0 && (
              <details className="flxai-args">
                <summary>
                  <ChevronDown size={14} /> {t('Data that will be sent')}
                </summary>
                <pre>{pretty(a)}</pre>
              </details>
            )}
          </div>
        );
      })}
      <div className="flxai-approve-f">
        <span>{t('%1$s of %2$s decided', done, n)}</span>
        {n > 1 && (
          <>
            <button type="button" className="flxai-btn" onClick={() => onAll('reject')}>
              {t('Reject all')}
            </button>
            <button type="button" className="flxai-btn" onClick={() => onAll('approve')}>
              {t('Approve all')}
            </button>
          </>
        )}
        <button type="button" className="flxai-btn is-primary" disabled={done < n} onClick={onContinue}>
          <Check size={14} /> {t('Continue')}
        </button>
      </div>
    </div>
  );
}
