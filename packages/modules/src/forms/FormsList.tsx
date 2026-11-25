// Forms › Forms: every form with its responses, shortcode, builder link, copy and delete.
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsAdminFormsQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsAdminForms, FlxFormsDashboard, FlxFormsFormDelete, FlxFormsFormDuplicate, FlxFormsFormOptions } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, typeLabel, useForms } from './common';

type Row = FlxFormsAdminFormsQuery['flxFormsAdminForms']['nodes'][number];

const TYPES = ['form', 'survey', 'subscribe', 'payment', 'register', 'login', 'smart'];

export function FormsPage() {
  const { t, go, toast, num, wp } = useForms();
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [type, setType] = useState('');
  const [cursor, setCursor] = useState<string | undefined>(undefined);
  const [rows, setRows] = useState<Row[]>([]);
  const [ask, setAsk] = useState<Row | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);
  useEffect(() => {
    setCursor(undefined);
  }, [q, type]);

  const list = useFlxQuery(FlxFormsAdminForms, { first: 50, after: cursor, search: q || undefined, type: type || undefined });
  useEffect(() => {
    const nodes = list.data?.flxFormsAdminForms.nodes;
    if (!nodes) return;
    setRows((prev) => {
      const next = cursor ? [...prev, ...nodes] : nodes;
      const seen = new Set<number>();
      return next.filter((r) => (seen.has(r.databaseId) ? false : (seen.add(r.databaseId), true)));
    });
  }, [list.dataUpdatedAt]); // eslint-disable-line react-hooks/exhaustive-deps

  const refresh = () => {
    setCursor(undefined);
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAdminForms) });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsFormOptions) });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsDashboard) });
  };
  const dup = useFlxMutation(FlxFormsFormDuplicate);
  const del = useFlxMutation(FlxFormsFormDelete);

  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast(t('Copied.'));
    } catch {
      toast(text);
    }
  };
  const duplicate = async (row: Row) => {
    try {
      const r = (await dup.mutateAsync({ input: { id: row.databaseId } })).flxFormsFormDuplicate;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      toast(t('Form copied.'));
      refresh();
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  const remove = async (row: Row) => {
    try {
      const r = (await del.mutateAsync({ input: { id: row.databaseId } })).flxFormsFormDelete;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      setRows((l) => l.filter((x) => x.databaseId !== row.databaseId));
      toast(t('Form deleted.'));
      setAsk(null);
      refresh();
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  const total = list.data?.flxFormsAdminForms.totalCount ?? rows.length;

  return (
    <div>
      <div className="flxfrm-filters">
        <input type="search" value={search} placeholder={t('Search name or id')} aria-label={t('Search name or id')} onChange={(e) => setSearch(e.target.value)} />
        <select aria-label={t('Type')} value={type} onChange={(e) => setType(e.target.value)}>
          <option value="">{t('All types')}</option>
          {TYPES.map((x) => (
            <option key={x} value={x}>
              {typeLabel(x, t)}
            </option>
          ))}
        </select>
        <span className="flxfrm-muted">{t('{n} forms', { n: num(total) })}</span>
        {wp ? (
          <button type="button" className="flxfrm-btn is-primary flxfrm-push" onClick={() => go('create')}>
            {t('New form')}
          </button>
        ) : null}
      </div>
      {list.isLoading && !rows.length ? <Loading /> : null}
      {list.isError ? <p className="flxfrm-bad">{errorText(list.error, t('Something went wrong.'))}</p> : null}
      <div className="flxfrm-card is-flush">
        <table className="flxfrm-table">
          <thead>
            <tr>
              <th>{t('Name')}</th>
              <th>{t('Type')}</th>
              <th className="is-num">{t('Responses')}</th>
              <th>{t('Last response')}</th>
              <th>{t('Shortcode')}</th>
              <th aria-label={t('Edit')} />
            </tr>
          </thead>
          <tbody>
            {!rows.length && list.isSuccess ? (
              <tr>
                <td colSpan={6} className="flxfrm-muted">
                  {t('No forms yet.')}
                </td>
              </tr>
            ) : null}
            {rows.map((row) => (
              <tr key={row.databaseId}>
                <td data-label={t('Name')}>
                  <button type="button" className="flxfrm-link" onClick={() => go('responses', { form: row.databaseId })}>
                    <strong>{row.title}</strong>
                  </button>
                  <div className="flxfrm-muted">
                    #{row.databaseId} · {t('Created')} {row.createdAt.label}
                  </div>
                </td>
                <td data-label={t('Type')}>
                  <span className="flxfrm-badge">{typeLabel(row.formType, t)}</span>
                </td>
                <td data-label={t('Responses')} className="is-num">
                  {num(row.responses)}
                  {row.unread ? <span className="flxfrm-badge is-new">{t('{n} unread', { n: num(row.unread) })}</span> : null}
                </td>
                <td data-label={t('Last response')}>{row.lastResponseAt ? <span title={row.lastResponseAt.label}>{row.lastResponseAt.relative}</span> : <span className="flxfrm-muted">—</span>}</td>
                <td data-label={t('Shortcode')}>
                  <button type="button" className="flxfrm-code" title={t('Copy shortcode')} onClick={() => void copy(row.shortcode)}>
                    {row.shortcode}
                  </button>
                </td>
                <td className="flxfrm-rowactions">
                  {wp ? (
                    <button type="button" className="flxfrm-btn" onClick={() => host.openUrl(row.editUrl)}>
                      {t('Edit')}
                    </button>
                  ) : null}
                  <button type="button" className="flxfrm-btn" disabled={dup.isPending} onClick={() => void duplicate(row)}>
                    {t('Duplicate')}
                  </button>
                  <button type="button" className="flxfrm-btn is-danger-text" onClick={() => setAsk(row)}>
                    {t('Delete')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {list.data?.flxFormsAdminForms.pageInfo.hasNextPage ? (
        <button type="button" className="flxfrm-btn" disabled={list.isFetching} onClick={() => setCursor(list.data?.flxFormsAdminForms.pageInfo.endCursor ?? undefined)}>
          {t('Load more')}
        </button>
      ) : null}
      {ask ? (
        <Modal title={t('Delete')} onClose={() => setAsk(null)}>
          <p>{t('Delete the form "{name}" and its {n} responses? This cannot be undone.', { name: ask.title, n: num(ask.responses) })}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxfrm-btn is-danger" disabled={del.isPending} onClick={() => void remove(ask)}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
