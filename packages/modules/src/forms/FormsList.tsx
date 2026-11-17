// The forms of the panel with their response counts. A row opens the responses of that form.
import { useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsPanel } from '@fluxwp/graphql/operations';
import { Icon, Loading } from '@fluxwp/ui';
import type { Translate } from './i18n';
import { copyText } from './ui';

const PAGE = 50;

export function FormsList({ t, lang, onOpen, createUrl, classicUrl }: { t: Translate; lang: string; onOpen: (id: number) => void; createUrl: string; classicUrl?: string }) {
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const page = cursors.length - 1;
  const q = useFlxQuery(FlxFormsPanel, { first: PAGE, after: cursors[page] ?? null });
  const [copied, setCopied] = useState('');
  if (q.isPending) return <Loading />;
  if (q.isError) return <p className="flxf-empty">{t('The forms could not be loaded.')}</p>;
  const list = q.data.flxForms;
  if (!list.nodes.length && page === 0) {
    return (
      <div className="flxf-card flxf-empty-card">
        <Icon name="forms" size={28} />
        <h2>{t('No forms yet')}</h2>
        <p>{t('Create your first form in the form builder. Its responses appear here.')}</p>
        {createUrl && (
          <a className="flxf-btn is-primary" href={createUrl}>
            {t('Create a form')}
          </a>
        )}
      </div>
    );
  }
  return (
    <section className="flxf-card">
      <div className="flxf-table-wrap">
        <table className="flxf-table">
          <thead>
            <tr>
              <th>{t('Shortcode')}</th>
              <th>{t('Form')}</th>
              <th>{t('Created')}</th>
              <th className="is-num">{t('Responses')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {list.nodes.map((f) => (
              <tr key={f.id} className="is-click" onClick={() => onOpen(Number(f.id))}>
                <td data-label={t('Shortcode')}>
                  <span className="flxf-code">
                    <code>{f.shortcode}</code>
                    <button
                      type="button"
                      className="flxf-icon-btn"
                      aria-label={t('Copy the shortcode')}
                      title={copied === f.id ? t('Copied') : t('Copy the shortcode')}
                      onClick={(e) => {
                        e.stopPropagation();
                        copyText(f.shortcode);
                        setCopied(f.id);
                      }}
                    >
                      <Icon name={copied === f.id ? 'check' : 'clipboard'} size={14} />
                    </button>
                  </span>
                </td>
                <td data-label={t('Form')}>
                  <strong>{f.title}</strong>
                  <span className="flxf-tag">{f.formType.toLowerCase()}</span>
                </td>
                <td data-label={t('Created')}>{f.createdAt?.label ?? '—'}</td>
                <td className="is-num" data-label={t('Responses')}>
                  {f.total.toLocaleString(lang)}
                  {f.unread > 0 && <span className="flxf-badge is-new">{t('%s new', f.unread.toLocaleString(lang))}</span>}
                </td>
                <td className="is-act">
                  <button
                    type="button"
                    className="flxf-btn"
                    onClick={(e) => {
                      e.stopPropagation();
                      onOpen(Number(f.id));
                    }}
                  >
                    <Icon name="mail" size={14} />
                    {t('Responses')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {(page > 0 || list.pageInfo.hasNextPage) && (
        <div className="flxf-pager">
          <button type="button" className="flxf-btn" disabled={page === 0} onClick={() => setCursors((c) => c.slice(0, -1))}>
            {t('Previous')}
          </button>
          <span>{t('Page %s', page + 1)}</span>
          <button type="button" className="flxf-btn" disabled={!list.pageInfo.hasNextPage} onClick={() => setCursors((c) => [...c, list.pageInfo.endCursor])}>
            {t('Next')}
          </button>
        </div>
      )}
      <p className="flxf-muted">
        {t('Editing, copying and deleting a form happen in the form builder.')}{' '}
        {classicUrl && (
          <a href={classicUrl}>{t('Open the classic panel')}</a>
        )}
      </p>
    </section>
  );
}
