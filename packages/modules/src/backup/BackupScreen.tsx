// Flux Backup › Backups. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux Backup GraphQL operations (plugins/flx-backup/graphql/operations).
// Dashboard, backup list, detail with the size breakdown,
// settings, back up now, restore, delete, lock, chunked upload and the running job's progress.
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxBackupCancel, FlxBackupDashboard, FlxBackupDelete, FlxBackupDetail, FlxBackupJobRead, FlxBackupList, FlxBackupRestoreStart, FlxBackupSettingsScreen, FlxBackupStart, FlxBackupStep } from '@fluxwp/graphql/operations';
import type { FlxBackupStartType } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { BackupCtx, errText, type Ctx, type Health, type Job, type Page, type Row } from './shared';
import { Dashboard } from './Dashboard';
import { BackupDetail, BackupList } from './List';
import { Settings } from './Settings';
import './backup.css';

type Notice = { kind: 'success' | 'error' | 'warn'; text: string };

export function BackupScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Backup />
    </FlxGraphQLProvider>
  );
}

const startPage = (s?: string): Page => (s === 'list' || s === 'settings' ? s : s?.startsWith('detail:') ? (s as Page) : 'dashboard');

function Backup() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [page, setPage] = useState<Page>(() => startPage(host.startPage));
  const [notice, setNotice] = useState<Notice | null>(null);
  const [backupOpen, setBackupOpen] = useState(false);
  const [restoreRow, setRestoreRow] = useState<Row | null>(null);
  const [del, setDel] = useState<{ ids: string[]; then?: () => void } | null>(null);
  const [job, setJob] = useState<Job | null>(null);
  const [jobOpen, setJobOpen] = useState(false);
  const info = useFlxQuery(FlxBackupSettingsScreen, {});
  const jobQ = useFlxQuery(FlxBackupJobRead, {});
  const health = info.data?.flxBackupHealth;

  const told = useCallback((text: string) => {
    setNotice({ kind: 'success', text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  }, []);
  const failed = useCallback((e: unknown) => setNotice({ kind: 'error', text: errText(e, t) }), [t]);
  const refresh = useCallback(() => {
    for (const op of [FlxBackupDashboard, FlxBackupList, FlxBackupDetail, FlxBackupSettingsScreen]) void qc.invalidateQueries({ queryKey: flxQueryKey(scope, op) });
  }, [qc, scope]);

  // A job already running when the page opens (started elsewhere, or before a reload).
  const picked = useRef(false);
  useEffect(() => {
    const j = jobQ.data?.flxBackupJob;
    if (j && !picked.current) {
      picked.current = true;
      if (j.status === 'RUNNING') setJob(j);
    }
  }, [jobQ.data]);

  const step = useFlxMutation(FlxBackupStep, {});
  const finish = useCallback(
    (j: Job) => {
      refresh();
      if (j.status === 'DONE' && j.kind === 'BACKUP') told(t('Backup ready.') + (j.sizeLabel ? ' ' + j.sizeLabel : ''));
    },
    [refresh, told, t],
  );
  useEffect(() => {
    if (!job || job.status !== 'RUNNING') return;
    const timer = window.setTimeout(() => {
      step
        .mutateAsync({})
        .then((r) => r.flxBackupStep?.job ?? null)
        .catch(() => ajaxStep(job, health?.ajaxUrl, health?.nonce, i18n.language))
        .then((next) => {
          if (!next) return setJob((o) => (o ? { ...o, status: 'FAILED', error: t('An error occurred.') } : o));
          setJob(next);
          if (next.status !== 'RUNNING') finish(next);
        });
    }, 1300);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [job]);

  const start = useFlxMutation(FlxBackupStart, {
    onSuccess: (r) => {
      const p = r.flxBackupStart;
      if (p?.errors.length || !p?.job) return failed(new Error(p?.errors[0]?.message));
      setBackupOpen(false);
      setJob(p.job);
      setJobOpen(true);
    },
    onError: failed,
  });
  const restore = useFlxMutation(FlxBackupRestoreStart, {
    onSuccess: (r) => {
      const p = r.flxBackupRestoreStart;
      if (p?.errors.length || !p?.job) return failed(new Error(p?.errors[0]?.message));
      setRestoreRow(null);
      setJob(p.job);
      setJobOpen(true);
    },
    onError: failed,
  });
  const remove = useFlxMutation(FlxBackupDelete, {
    onSuccess: (r) => {
      const p = r.flxBackupDelete;
      if (p?.errors.length) return failed(new Error(p.errors[0]?.message));
      setDel(null);
      refresh();
      told(tn('%s backup deleted.', '%s backups deleted.', p?.deletedIds?.length ?? 0, String(p?.deletedIds?.length ?? 0)));
      del?.then?.();
    },
    onError: failed,
  });

  const busy = job?.status === 'RUNNING';
  const ctx: Ctx = {
    t,
    tn,
    lang: i18n.language,
    go: (p) => {
      setPage(p);
      setNotice(null);
    },
    openBackup: () => setBackupOpen(true),
    openRestore: setRestoreRow,
    askDelete: (ids, then) => setDel({ ids, then }),
    told,
    failed,
    busy,
    refresh,
  };

  const tabs: [Page, string][] = [
    ['dashboard', t('Dashboard')],
    ['list', t('Backups')],
    ['settings', t('Settings')],
  ];
  const current: Page = page.startsWith('detail:') ? 'list' : page;
  const lastRestore = health?.lastRestore;

  return (
    <BackupCtx.Provider value={ctx}>
      <div className="flxy-app">
        <div className="flxy-head">
          {host.appIcon && <img className="flxy-logo" src={host.appIcon} alt="" width={44} height={44} />}
          <div className="flxy-head-text">
            <h1>{t('Flux Backup')}</h1>
            <p>{t('Automatic pre-update backups, scheduled backups and restore')}</p>
          </div>
          <div className="flxy-head-actions">
            {current !== 'settings' && health && (
              <>
                {current === 'list' && <Upload health={health} onDone={(id) => { refresh(); setPage(`detail:${id}`); }} onError={failed} t={t} lang={i18n.language} />}
                <button type="button" className="flxy-btn is-primary is-hero" disabled={busy} onClick={() => setBackupOpen(true)}>
                  {t('Back up now')}
                </button>
              </>
            )}
          </div>
        </div>
        <nav className="flxy-tabs" aria-label={t('Flux Backup')}>
          {tabs.map(([p, label]) => (
            <button key={p} type="button" aria-current={current === p ? 'page' : undefined} onClick={() => ctx.go(p)}>
              {label}
            </button>
          ))}
        </nav>
        {health && !health.zipAvailable && <div className="flxy-notice is-error">{t('ZipArchive (PHP zip extension) is not available on the server.')}</div>}
        {health && !health.writable && <div className="flxy-notice is-error">{t('The backup folder is not writable: %s', health.folder)}</div>}
        {lastRestore && <div className="flxy-notice">{lastRestore}</div>}
        {notice && (
          <div className={'flxy-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
            <span>{notice.text}</span>
            <button type="button" className="flxy-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )}
        {job && !jobOpen && (
          <div className="flxy-notice is-warn">
            <span>
              {job.status === 'RUNNING' ? (job.kind === 'RESTORE' ? t('The restore has started in the background. Progress is shown in the toolbar indicator; do not change the site until it finishes.') : t('The backup is running in the background. Follow its progress in the toolbar indicator at the top; you can leave this page.')) : job.status === 'FAILED' ? t('Failed') + ': ' + job.error : job.kind === 'RESTORE' ? job.result : t('Completed')}{' '}
              <button type="button" className="flxy-link" onClick={() => (job.status === 'RUNNING' ? setJobOpen(true) : setJob(null))}>
                {job.status === 'RUNNING' ? t('Details') : t('Dismiss this notice.')}
              </button>
            </span>
          </div>
        )}

        {page === 'dashboard' && <Dashboard />}
        {page === 'list' && <BackupList />}
        {page === 'settings' && <Settings />}
        {page.startsWith('detail:') && <BackupDetail id={page.slice(7)} onGone={() => ctx.go('list')} />}

        {backupOpen && health && <BackupDialog t={t} core={health.includeCoreDefault} busy={start.isPending} onClose={() => setBackupOpen(false)} onStart={(input) => start.mutate({ input })} />}
        {restoreRow && health && <RestoreDialog t={t} row={restoreRow} safety={health.safetyDefault} busy={restore.isPending} onClose={() => setRestoreRow(null)} onStart={(input) => restore.mutate({ input })} />}
        {del && (
          <Modal title={t('Delete')} onClose={() => setDel(null)}>
            <p>{del.ids.length > 1 ? t('Permanently delete the selected backups?') : t('Permanently delete this backup?')}</p>
            <div className="flxy-modal-actions">
              <button type="button" className="flxy-btn" onClick={() => setDel(null)}>
                {t('Cancel')}
              </button>
              <button type="button" className="flxy-btn is-danger" disabled={remove.isPending} onClick={() => remove.mutate({ input: { ids: del.ids } })}>
                {t('Delete')}
              </button>
            </div>
          </Modal>
        )}
        {job && jobOpen && <JobDialog t={t} job={job} onClose={() => setJobOpen(false)} onJob={setJob} onFail={failed} />}
        {!health && info.isLoading && <Loading />}
      </div>
    </BackupCtx.Provider>
  );
}

/** After a database restore the login session may be gone: step through admin-ajax with the job token. */
async function ajaxStep(job: Job, url?: string, nonce?: string, locale?: string): Promise<Job | null> {
  if (!url) return null;
  try {
    const body = new FormData();
    body.append('action', 'flxy_step');
    body.append('token', job.token);
    if (nonce) body.append('nonce', nonce);
    if (locale) body.append('locale', locale);
    const res = (await (await fetch(url, { method: 'POST', credentials: 'same-origin', body })).json()) as { success: boolean; data: Record<string, unknown> };
    if (!res.success) return null;
    const d = res.data;
    const status = String(d.status ?? '').toUpperCase();
    return {
      ...job,
      status: status === 'DONE' ? 'DONE' : status === 'FAILED' ? 'FAILED' : status === 'IDLE' ? 'IDLE' : 'RUNNING',
      phase: String(d.phase ?? job.phase),
      percent: Number(d.percent ?? job.percent),
      text: String(d.text ?? job.text),
      error: String(d.error ?? ''),
      cancelled: Boolean(d.cancelled),
      elapsed: Number(d.elapsed ?? 0),
      elapsedLabel: String(d.elapsed_h ?? job.elapsedLabel),
      eta: d.eta === null || d.eta === undefined ? null : Number(d.eta),
      etaLabel: String(d.eta_h ?? ''),
      sizeLabel: String(d.size_h ?? job.sizeLabel),
      result: String(d.result ?? job.result),
    };
  } catch {
    return null;
  }
}

function BackupDialog({ t, core, busy, onClose, onStart }: { t: Ctx['t']; core: boolean; busy: boolean; onClose: () => void; onStart: (i: { type: FlxBackupStartType; includeCore: boolean; label: string; lock: boolean }) => void }) {
  const [type, setType] = useState<FlxBackupStartType>('FULL');
  const [inc, setInc] = useState(core);
  const [label, setLabel] = useState('');
  const [lock, setLock] = useState(false);
  const opts: [FlxBackupStartType, string, string][] = [
    ['FULL', t('Full backup'), t('Database + wp-content (plugins, themes, uploads)')],
    ['DB', t('Database only'), t('Posts, pages, settings, users')],
    ['FILES', t('Files only'), t('The wp-content folder')],
  ];
  const submit = (e: FormEvent) => {
    e.preventDefault();
    onStart({ type, includeCore: type === 'FULL' && inc, label, lock });
  };
  return (
    <Modal title={t('Back up now')} onClose={onClose}>
      <form onSubmit={submit}>
        <fieldset className="flxy-choices">
          <legend className="flxy-sr">{t('Backup type')}</legend>
          {opts.map(([v, title, sub]) => (
            <label key={v} className="flxy-choice">
              <input type="radio" name="type" checked={type === v} onChange={() => setType(v)} />
              <span>
                <strong>{title}</strong>
                <small>{sub}</small>
              </span>
            </label>
          ))}
        </fieldset>
        {type === 'FULL' && (
          <label className="flxy-check">
            <input type="checkbox" checked={inc} onChange={(e) => setInc(e.target.checked)} /> {t('Also include WordPress core files (wp-admin, wp-includes)')}
          </label>
        )}
        <p>
          <label htmlFor="flxy-label">{t('Description (optional)')}</label>
          <br />
          <input type="text" id="flxy-label" maxLength={120} style={{ width: '100%' }} value={label} placeholder={t('e.g. Before the theme change')} onChange={(e) => setLabel(e.target.value)} />
        </p>
        <label className="flxy-check">
          <input type="checkbox" checked={lock} onChange={(e) => setLock(e.target.checked)} /> {t('Lock: never delete automatically, even when the quota is full')}
        </label>
        <div className="flxy-modal-actions">
          <button type="button" className="flxy-btn" onClick={onClose}>
            {t('Cancel')}
          </button>
          <button type="submit" className="flxy-btn is-primary" disabled={busy}>
            {t('Start backup')}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function RestoreDialog({ t, row, safety, busy, onClose, onStart }: { t: Ctx['t']; row: Row; safety: boolean; busy: boolean; onClose: () => void; onStart: (i: { id: string; database: boolean; paths: string[]; safety: boolean }) => void }) {
  const r = row.restore;
  const [db, setDb] = useState(false);
  const [paths, setPaths] = useState<Set<string>>(new Set());
  const [safe, setSafe] = useState(safety);
  const [confirm, setConfirm] = useState(false);
  const [err, setErr] = useState('');
  if (!r) return null;
  const mismatch = !!r.prefixInBackup && !!r.prefixOnSite && r.prefixInBackup !== r.prefixOnSite;
  const toggle = (path: string, on: boolean) =>
    setPaths((o) => {
      const n = new Set(o);
      if (on) n.add(path);
      else n.delete(path);
      return n;
    });
  const next = (e: FormEvent) => {
    e.preventDefault();
    if (!db && paths.size === 0) return setErr(t('Select at least one part.'));
    setErr('');
    setConfirm(true);
  };
  return (
    <Modal title={t('Restore')} onClose={onClose} wide>
      <p>
        <strong>{r.title}</strong>
      </p>
      {r.foreignSite && <div className="flxy-warn">{t("This backup was taken on another site: %s. If you restore the database, the content comes from that site; this site's address (siteurl/home) is kept, but links to the old address inside the content are not changed automatically.", r.foreignSite)}</div>}
      {mismatch && <div className="flxy-warn">{t("The table prefix in the backup (%1$s) differs from this site's prefix (%2$s); the database cannot be restored to this site, only files can be selected.", r.prefixInBackup ?? '', r.prefixOnSite ?? '')}</div>}
      {confirm ? (
        <>
          <div className="flxy-warn">{t('The selected parts will be returned to their state in the backup and their current state will be overwritten. Continue?')}</div>
          <div className="flxy-modal-actions">
            <button type="button" className="flxy-btn" onClick={() => setConfirm(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxy-btn is-danger" disabled={busy} onClick={() => onStart({ id: row.id, database: db, paths: [...paths], safety: db && safe })}>
              {t('Start restore')}
            </button>
          </div>
        </>
      ) : (
        <form onSubmit={next}>
          <div className="flxy-parts">
            {r.database && !mismatch && (
              <label className="flxy-check">
                <input type="checkbox" checked={db} onChange={(e) => setDb(e.target.checked)} /> {t('Database (all tables)')}
              </label>
            )}
            {r.groups.map((g) => (
              <fieldset key={g.key} style={{ border: 0, padding: 0, margin: 0 }}>
                <h3>{g.label}</h3>
                {g.items.map((i) => (
                  <label key={i.path} className="flxy-check">
                    <input type="checkbox" checked={paths.has(i.path)} onChange={(e) => toggle(i.path, e.target.checked)} /> {i.label}
                  </label>
                ))}
              </fieldset>
            ))}
          </div>
          {db && (
            <label className="flxy-check">
              <input type="checkbox" checked={safe} onChange={(e) => setSafe(e.target.checked)} /> {t('First take a safety backup of the current database (recommended)')}
            </label>
          )}
          <p className="flxy-desc">{t("The site address (siteurl/home), Flux Backup settings and the backup list are kept. Plugin and theme folders are replaced with their backed-up version; in other folders files are overwritten.")}</p>
          {err && <p className="flxy-failed">{err}</p>}
          <div className="flxy-modal-actions">
            <button type="button" className="flxy-btn" onClick={onClose}>
              {t('Cancel')}
            </button>
            <button type="submit" className="flxy-btn is-primary">
              {t('Start restore')}
            </button>
          </div>
        </form>
      )}
    </Modal>
  );
}

function JobDialog({ t, job, onClose, onJob, onFail }: { t: Ctx['t']; job: Job; onClose: () => void; onJob: (j: Job) => void; onFail: (e: unknown) => void }) {
  const cancel = useFlxMutation(FlxBackupCancel, {
    onSuccess: (r) => {
      if (r.flxBackupCancel?.job) onJob(r.flxBackupCancel.job);
    },
    onError: onFail,
  });
  const running = job.status === 'RUNNING';
  const restore = job.kind === 'RESTORE';
  const title = restore ? t('Restoring') : t('Backing up');
  return (
    <Modal title={running ? title : job.status === 'FAILED' ? t('Failed') : t('Completed')} onClose={onClose}>
      <p>
        <strong>{job.title}</strong>
      </p>
      <p className="flxy-muted">{job.status === 'FAILED' ? job.error : job.status === 'DONE' && restore ? job.result : job.text || t('Starting…')}</p>
      <div className="flxy-progress" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(job.percent)}>
        <span style={{ width: Math.max(0, Math.min(100, job.percent)) + '%' }} />
      </div>
      <p className="flxy-sub">
        {Math.round(job.percent)}% · {job.elapsedLabel}
        {job.etaLabel ? ' · ' + job.etaLabel : ''}
        {job.status === 'DONE' && job.sizeLabel ? ' · ' + job.sizeLabel : ''}
      </p>
      {running && <p className="flxy-sub">{restore ? t('Do not close this page until the restore finishes.') : t('You can keep this page open; if you close it, the job continues in the background.')}</p>}
      <div className="flxy-modal-actions">
        {running && (
          <button type="button" className="flxy-btn" disabled={cancel.isPending} onClick={() => cancel.mutate({})}>
            {t('Cancel')}
          </button>
        )}
        {!running && restore && job.status === 'DONE' && (
          <button type="button" className="flxy-btn is-primary" onClick={() => window.location.reload()}>
            {t('Reload page')}
          </button>
        )}
        <button type="button" className="flxy-btn" onClick={onClose}>
          {t('Close')}
        </button>
      </div>
    </Modal>
  );
}

/** Chunked upload (admin-ajax flxy_upload_*; a file stream does not go through GraphQL). */
function Upload({ health, onDone, onError, t, lang }: { health: Health; onDone: (id: string) => void; onError: (e: unknown) => void; t: Ctx['t']; lang: string }) {
  const [state, setState] = useState<{ name: string; pct: number; text: string } | null>(null);
  const cancelled = useRef(false);
  const uid = useRef('');
  const post = async (action: string, data: Record<string, string>) => {
    const body = new FormData();
    body.append('action', action);
    body.append('nonce', health.nonce);
    for (const [k, v] of Object.entries(data)) body.append(k, v);
    return (await (await fetch(health.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })).json()) as { success: boolean; data: { message?: string; uid?: string; chunk?: number; received?: number; id?: string } };
  };
  const nf = (b: number) => {
    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let v = b;
    while (v >= 1024 && i < u.length - 1) (v /= 1024), i++;
    return new Intl.NumberFormat(lang, { maximumFractionDigits: i ? 1 : 0 }).format(v) + ' ' + u[i];
  };
  const fail = (msg?: string) => {
    if (uid.current) void post('flxy_upload_abort', { uid: uid.current });
    uid.current = '';
    setState(null);
    onError(new Error(t('Upload failed: %s', msg || t('An error occurred.'))));
  };
  const send = async (file: File, chunk: number, offset: number, tries: number): Promise<void> => {
    if (cancelled.current) return;
    if (offset >= file.size) {
      setState((s) => s && { ...s, pct: 100, text: t('Verifying…') });
      const res = await post('flxy_upload_finish', { uid: uid.current }).catch(() => null);
      if (!res?.success) return fail(res?.data?.message);
      uid.current = '';
      setState(null);
      onDone(String(res.data.id ?? ''));
      return;
    }
    const body = new FormData();
    body.append('action', 'flxy_upload_chunk');
    body.append('nonce', health.nonce);
    body.append('uid', uid.current);
    body.append('offset', String(offset));
    body.append('chunk', file.slice(offset, Math.min(file.size, offset + chunk)), 'chunk.bin');
    try {
      const res = (await (await fetch(health.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })).json()) as { success: boolean; data: { message?: string; received?: number } };
      if (!res.success) {
        if (typeof res.data?.received === 'number' && tries < 3) return send(file, chunk, res.data.received, tries + 1);
        return fail(res.data?.message);
      }
      const got = res.data.received ?? offset;
      setState({ name: file.name, pct: (100 * got) / file.size, text: t('%1$s / %2$s', nf(got), nf(file.size)) });
      return send(file, chunk, got, 0);
    } catch {
      if (tries < 5 && !cancelled.current) {
        setState((s) => s && { ...s, text: t('Connection lost, retrying…') });
        await new Promise((r) => setTimeout(r, 1500 * (tries + 1)));
        return send(file, chunk, offset, tries + 1);
      }
      return fail();
    }
  };
  const pick = async (file: File | undefined) => {
    if (!file || state) return;
    if (!/\.zip$/i.test(file.name)) return onError(new Error(t('Only .zip backup files made with Flux Backup can be uploaded.')));
    cancelled.current = false;
    setState({ name: file.name, pct: 0, text: t('%1$s / %2$s', nf(0), nf(file.size)) });
    const res = await post('flxy_upload_start', { name: file.name, size: String(file.size) }).catch(() => null);
    if (!res?.success) return fail(res?.data?.message);
    uid.current = res.data.uid ?? '';
    await send(file, res.data.chunk || health.chunkSize || 2097152, 0, 0);
  };
  useEffect(() => {
    if (!state) return;
    const h = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener('beforeunload', h);
    return () => window.removeEventListener('beforeunload', h);
  }, [state]);
  return (
    <>
      <label className="flxy-btn is-hero flxy-file" aria-disabled={!!state}>
        {t('Upload backup')}
        <input type="file" accept=".zip,application/zip" disabled={!!state} onChange={(e) => { void pick(e.target.files?.[0]); e.target.value = ''; }} />
      </label>
      {state && (
        <Modal title={t('Uploading %1$s…', state.name)} onClose={() => undefined}>
          <div className="flxy-progress" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(state.pct)}>
            <span style={{ width: state.pct + '%' }} />
          </div>
          <p className="flxy-sub">{state.text}</p>
          <div className="flxy-modal-actions">
            <button
              type="button"
              className="flxy-btn"
              onClick={() => {
                cancelled.current = true;
                if (uid.current) void post('flxy_upload_abort', { uid: uid.current });
                uid.current = '';
                setState(null);
              }}
            >
              {t('Cancel')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}
