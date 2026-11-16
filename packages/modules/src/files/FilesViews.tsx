// Flux Files: Trash and Activity views, and the upload panel.
import { useState } from 'react';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { FlxFilesLog, FlxFilesTrashList, FlxFilesTrashPurge, FlxFilesTrashRestore } from '@fluxwp/graphql/operations';
import type { FlxFilesVia } from '@fluxwp/graphql/types';
import { Ic } from './icons';
import type { Translate } from './i18n';
import { useDialogs } from './ui';
import { errText, FileIcon, ok, useEnv } from './data';
import { ViaBadge } from './FilesDialogs';
import { dir, extOf, lower } from './util';

export function TrashView({ t, onChanged, onCount }: { t: Translate; onChanged: () => void; onCount: (n: number) => void }) {
  const { client, scope, cfg } = useEnv();
  const d = useDialogs();
  const [busy, setBusy] = useState(false);
  const q = useQuery({
    queryKey: [scope, 'files-trash'],
    queryFn: async () => {
      const r = (await client.request(FlxFilesTrashList, { first: 50 })).flxFilesTrash;
      const nodes = [...r.items.nodes];
      let next = r.items.pageInfo;
      while (next.hasNextPage && nodes.length < 2000) {
        const more = (await client.request(FlxFilesTrashList, { first: 50, after: next.endCursor })).flxFilesTrash;
        nodes.push(...more.items.nodes);
        next = more.items.pageInfo;
      }
      onCount(nodes.length);
      return { days: r.days, items: nodes };
    },
  });
  const run = async (fn: () => Promise<void>) => {
    setBusy(true);
    try {
      await fn();
      await q.refetch();
      onChanged();
    } catch (e) {
      d.toast(errText(e, t('error')), 'bad');
    }
    setBusy(false);
  };
  const restore = (id: string) =>
    run(async () => {
      const r = ok((await client.request(FlxFilesTrashRestore, { input: { ids: [id] } })).flxFilesTrashRestore);
      if (r.failed.length) await d.alert({ title: t('some_failed'), message: r.failed.map((x) => x.path + ': ' + x.message).join('\n') });
      if (r.items[0]) d.toast(t('restored_to', r.items[0].path));
    });
  const purge = async (id: string) => {
    if (!(await d.confirm({ title: t('delete_forever'), message: t('delete_forever_msg'), ok: t('delete_forever'), danger: true }))) return;
    await run(async () => void ok((await client.request(FlxFilesTrashPurge, { input: { ids: [id] } })).flxFilesTrashPurge));
  };
  const empty = async () => {
    if (!(await d.confirm({ title: t('empty_trash'), message: t('empty_trash_msg'), ok: t('empty_trash'), danger: true }))) return;
    await run(async () => {
      const r = ok((await client.request(FlxFilesTrashPurge, { input: { all: true } })).flxFilesTrashPurge);
      d.toast(t('removed_n', r.removed ?? 0));
    });
  };
  if (q.isPending) return <p className="flxfm-muted" style={{ padding: 16 }}>{t('loading')}</p>;
  if (q.isError) return <p className="flxfm-err" style={{ padding: 16 }}>{errText(q.error, t('error'))}</p>;
  const { days, items } = q.data;
  return (
    <>
      <div className="flxfm-subhead">
        <p>{days ? t('trash_days', days) : t('trash_forever')}</p>
        {items.length && cfg.writable ? (
          <button type="button" className="flxfm-btn is-danger" disabled={busy} onClick={empty}>
            <Ic n="trash" />
            <span>{t('empty_trash')}</span>
          </button>
        ) : null}
      </div>
      {!items.length ? (
        <div className="flxfm-empty">
          <Ic n="trash" />
          <p>{t('trash_empty')}</p>
        </div>
      ) : (
        <div className="flxfm-table flxfm-table--trash">
          <div className="flxfm-thead">
            <span className="flxfm-c-name">{t('col_name')}</span>
            <span className="flxfm-c-where">{t('col_origin')}</span>
            <span className="flxfm-c-mod">{t('col_deleted')}</span>
            <span className="flxfm-c-act" />
          </div>
          {items.map((it) => (
            <div className="flxfm-row" key={it.id}>
              <span className="flxfm-c-name">
                <FileIcon it={{ name: it.name, extension: extOf(it.name), kind: it.kind, isDir: it.isDir, shared: false }} />
                <span className="flxfm-nm"><span className="flxfm-nm-t" title={it.name}>{it.name}</span></span>
              </span>
              <span className="flxfm-c-where" title={it.path}>{dir(it.path) || '/'}</span>
              <span className="flxfm-c-mod" title={it.deleted.label}>
                {it.deleted.relative} · {it.user} <ViaBadge via={it.via} t={t} />
                {it.until ? <small>{t('until', it.until)}</small> : null}
              </span>
              <span className="flxfm-c-act">
                {cfg.writable ? (
                  <>
                    <button type="button" className="flxfm-btn" disabled={busy} onClick={() => void restore(it.id)}><Ic n="restore" /><span>{t('restore')}</span></button>
                    <button type="button" className="flxfm-btn is-danger" disabled={busy} onClick={() => void purge(it.id)}><Ic n="trash" /><span>{t('delete_forever')}</span></button>
                  </>
                ) : null}
              </span>
            </div>
          ))}
        </div>
      )}
    </>
  );
}

const VIAS: Array<FlxFilesVia | ''> = ['', 'PANEL', 'AI', 'MCP'];

export function LogView({ t, path, onClearPath, onGo, onCount }: { t: Translate; path: string; onClearPath: () => void; onGo: (p: string) => void; onCount: (n: number) => void }) {
  const { client, scope } = useEnv();
  const [via, setVia] = useState<FlxFilesVia | ''>('');
  const q = useInfiniteQuery({
    queryKey: [scope, 'files-log', path, via],
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const r = (await client.request(FlxFilesLog, { first: 50, after: pageParam, path: path || null, via: via || null })).flxFilesLog;
      if (r.totalCount != null) onCount(r.totalCount);
      return r;
    },
    getNextPageParam: (p) => (p.pageInfo.hasNextPage ? p.pageInfo.endCursor : undefined),
  });
  const rows = q.data?.pages.flatMap((p) => p.nodes) ?? [];
  const link = (p: string) => (
    <button type="button" className="flxfm-link" onClick={() => onGo(p)}>
      {p}
    </button>
  );
  return (
    <>
      <div className="flxfm-subhead">
        <div className="flxfm-chips">
          {VIAS.map((v) => (
            <button type="button" key={v || 'all'} className={'flxfm-chipbtn' + (via === v ? ' is-on' : '')} onClick={() => setVia(v)}>
              {v ? t('via_' + lower(v)) : t('all')}
            </button>
          ))}
        </div>
        {path ? (
          <span className="flxfm-tag">
            {path}{' '}
            <button type="button" className="flxfm-link" onClick={onClearPath}>
              <Ic n="x" cls="flxfm-i-sm" />
            </button>
          </span>
        ) : null}
      </div>
      {q.isPending ? <p className="flxfm-muted" style={{ padding: 16 }}>{t('loading')}</p> : null}
      {q.isError ? <p className="flxfm-err" style={{ padding: 16 }}>{errText(q.error, t('error'))}</p> : null}
      {q.data && !rows.length ? (
        <div className="flxfm-empty">
          <Ic n="activity" />
          <p>{t('log_empty')}</p>
        </div>
      ) : null}
      {rows.length ? (
        <ol className="flxfm-log">
          {rows.map((e, i) => {
            const a = e.action ? lower(e.action) : '';
            return (
              <li key={i}>
                <span className={'flxfm-log-dot flxfm-log-' + a} />
                <div>
                  <div className="flxfm-log-line">
                    <b>{t('a_' + a)}</b> {e.path ? link(e.path) : null}
                    {e.to ? (
                      <>
                        {' '}
                        <span className="flxfm-muted">→</span> {a === 'chmod' ? <code>{e.to}</code> : link(e.to)}
                      </>
                    ) : null}
                  </div>
                  <div className="flxfm-log-meta">
                    <span title={e.when.label}>{e.when.relative}</span> · {e.user} · <ViaBadge via={e.via} t={t} />
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      ) : null}
      {q.hasNextPage ? (
        <div className="flxfm-more">
          <button type="button" className="flxfm-btn" disabled={q.isFetchingNextPage} onClick={() => void q.fetchNextPage()}>
            {t('load_more')}
          </button>
        </div>
      ) : null}
    </>
  );
}

export interface UpJob {
  id: number;
  name: string;
  size: number;
  pct: number;
  state: 'queue' | 'run' | 'ok' | 'skip' | 'bad';
  msg: string;
}

export function UploadPanel({ jobs, onClose, t }: { jobs: UpJob[]; onClose: () => void; t: Translate }) {
  if (!jobs.length) return null;
  const done = jobs.filter((j) => j.state === 'ok' || j.state === 'skip' || j.state === 'bad').length;
  return (
    <div className="flxfm-uploads">
      <div className="flxfm-up-head">
        <b>{done < jobs.length ? t('uploading_n', done, jobs.length) : t('uploaded_n', jobs.length)}</b>
        <button type="button" className="flxfm-ib" onClick={onClose} aria-label={t('close')}>
          <Ic n="x" />
        </button>
      </div>
      <ul>
        {jobs.map((j) => (
          <li key={j.id} className={j.state === 'ok' ? 'is-ok' : j.state === 'skip' ? 'is-skip' : j.state === 'bad' ? 'is-bad' : undefined}>
            <span className="flxfm-up-n" title={j.name}>{j.name}</span>
            <span className="flxfm-up-s">{j.state === 'skip' ? t('skipped') : j.state === 'bad' ? j.msg : j.size >= 0 ? sizeText(j.size) : ''}</span>
            <span className="flxfm-up-bar"><i style={{ width: j.pct + '%' }} /></span>
          </li>
        ))}
      </ul>
    </div>
  );
}
import { size as sizeText } from './util';
