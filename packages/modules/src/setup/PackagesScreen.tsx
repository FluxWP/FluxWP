// FluxWP Panel › Packages. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes
// through the FluxWP Panel GraphQL operations (plugins/flx-setup/graphql/operations). Connection card, tabs with counts, search, "only updates", the package table (select,
// install / update, change version, automatic updates, details), bulk actions, "update all", the step log,
// the backup strip and the update history.
import { useEffect, useMemo, useRef, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { flxStoreGet, flxStoreKey, flxStoreNote, flxStoreSet } from '@fluxwp/graphql';
import { flxQueryKey, useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import {
  FlxSetupAutoUpdateSet,
  FlxSetupCheck,
  FlxSetupGoogleCardDismiss,
  FlxSetupGoogleCards,
  FlxSetupManage,
  FlxSetupOverview,
  FlxSetupPackages,
  FlxSetupPlan,
  FlxSetupStepRun,
} from '@fluxwp/graphql/operations';
import type { FlxSetupOverviewQuery, FlxSetupPackagesQuery, FlxSetupPackageTab, FlxSetupPlanQuery, FlxSetupStepKind } from '@fluxwp/graphql/types';
import { Loading, Modal, Screen, Tabs } from '@fluxwp/ui';
import { useHost } from '../host';
import { History } from './History';
import { errorText, NoticeBar, SetupProvider, Switch, useSetup, type Notice } from './shared';

type PlanStep = FlxSetupPlanQuery['flxSetupPlan'][number];
type Row = FlxSetupPackagesQuery['flxSetupPackages']['nodes'][number];
type Overview = FlxSetupOverviewQuery['flxSetupOverview'];
type StepState = 'wait' | 'run' | 'ok' | 'err';
interface RunStep {
  kind: FlxSetupStepKind;
  name: string;
  state: StepState;
  lines: { ok: boolean; text: string }[];
}
interface Run {
  title: string;
  steps: RunStep[];
  finished: boolean;
  hidden: boolean;
  failed: number;
}
type Confirm = { text: string; action: string; danger?: boolean; run: () => void };

const TABS: FlxSetupPackageTab[] = ['ALL', 'FLUX', 'PLUGINS', 'THEMES', 'CORE'];

/** Rows per table request (the server's page limit). wp-admin prints the first pages with the same variables (flxk_packages_preload). */
const PAGE = 50;
type RowsFilter = { tab: FlxSetupPackageTab; query: string; onlyUpdates: boolean };
const pageVars = (f: RowsFilter, after: string | null) => ({ tab: f.tab, search: f.query || null, onlyUpdates: f.onlyUpdates || null, first: PAGE, after });

/** Every page of the table from the page-wide store (what wp-admin printed into the page), or nothing when a page is missing. */
function storedRows(f: RowsFilter): { rows: Row[]; at: number } | undefined {
  const rows: Row[] = [];
  let at = Infinity;
  let after: string | null = null;
  for (let i = 0; i < 40; i++) {
    const e = flxStoreGet(flxStoreKey(FlxSetupPackages.id, pageVars(f, after)));
    if (!e || !e.at) return undefined;
    const r = (e.data as FlxSetupPackagesQuery).flxSetupPackages;
    rows.push(...r.nodes);
    at = Math.min(at, e.at);
    if (!r.pageInfo.hasNextPage) return { rows, at };
    after = r.pageInfo.endCursor ?? null;
  }
  return undefined;
}

export function PackagesScreen() {
  return (
    <SetupProvider>
      <Packages />
    </SetupProvider>
  );
}

function Packages() {
  const { t, tn, client, scope, locale, refresh } = useSetup();
  const host = useHost();
  const [tab, setTab] = useState<FlxSetupPackageTab>('ALL');
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [onlyUpdates, setOnlyUpdates] = useState(false);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [activate, setActivate] = useState(true);
  const [bulkOp, setBulkOp] = useState('');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [run, setRun] = useState<Run | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);
  const [busy, setBusy] = useState(false);
  const autoChecked = useRef(false);
  const autoRun = useRef(false);

  useEffect(() => {
    const id = window.setTimeout(() => setQuery(search.trim()), 200);
    return () => window.clearTimeout(id);
  }, [search]);

  const overview = useFlxQuery(FlxSetupOverview, { search: query || null }, { placeholderData: keepPreviousData });
  const filter: RowsFilter = { tab, query, onlyUpdates };
  const [seed] = useState(() => storedRows(filter));
  flxStoreNote(flxStoreKey(FlxSetupPackages.id, pageVars(filter, null)));
  const rowsQ = useQuery({
    queryKey: flxQueryKey(scope, FlxSetupPackages, filter),
    placeholderData: keepPreviousData,
    // Printed by wp-admin: the table shows at once; the screen's own update check refreshes it.
    ...(seed && tab === 'ALL' && !query && !onlyUpdates ? { initialData: seed.rows, initialDataUpdatedAt: seed.at } : {}),
    staleTime: 30_000,
    // The server pages the table (50 at most); the screen shows it whole.
    queryFn: async ({ signal }): Promise<Row[]> => {
      const out: Row[] = [];
      let after: string | null = null;
      for (let i = 0; i < 40; i++) {
        const vars = pageVars(filter, after);
        const r: FlxSetupPackagesQuery = await client.request(FlxSetupPackages, vars, { signal });
        flxStoreSet(flxStoreKey(FlxSetupPackages.id, vars), r);
        out.push(...r.flxSetupPackages.nodes);
        if (!r.flxSetupPackages.pageInfo.hasNextPage) break;
        after = r.flxSetupPackages.pageInfo.endCursor;
      }
      return out;
    },
  });

  const fail = (e: unknown) => setNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const afterChange = (restart = false) => {
    host.refreshCounts?.();
    return refresh(restart);
  };

  const check = useFlxMutation(FlxSetupCheck, {
    onSuccess: (r) => {
      const p = r.flxSetupCheck;
      // The check the page runs by itself stays quiet when all is well: the header already says so.
      if (p && !(autoRun.current && p.ok)) setNotice({ kind: p.ok ? 'success' : 'warning', text: p.summary || t('Updates checked.') });
      autoRun.current = false;
      void afterChange();
    },
    onError: fail,
  });

  // The page checks for updates once by itself when the last check is not recent.
  useEffect(() => {
    if (!autoChecked.current && overview.data?.flxSetupOverview.autoCheck) {
      autoChecked.current = true;
      autoRun.current = true;
      check.mutate({});
    }
  }, [overview.data, check]);

  const rows = useMemo(() => rowsQ.data ?? [], [rowsQ.data]);
  const byKey = useMemo(() => new Map(rows.map((r) => [r.key, r])), [rows]);
  const o = overview.data?.flxSetupOverview;

  // Keep only the selection that is still in the table.
  useEffect(() => {
    setSelected((old) => {
      const next = new Set([...old].filter((k) => byKey.has(k)));
      return next.size === old.size ? old : next;
    });
  }, [byKey]);

  const selectable = rows.filter((r) => r.canUpdate || r.canInstall || r.canManage);
  const pick = (key: string, on: boolean) =>
    setSelected((old) => {
      const next = new Set(old);
      if (on) next.add(key);
      else next.delete(key);
      return next;
    });
  const split = (keys: Iterable<string>) => {
    const slugs: string[] = [];
    const wpKeys: string[] = [];
    for (const k of keys) {
      const r = byKey.get(k);
      if (!r) continue;
      if (r.group === 'FLUX') slugs.push(r.key);
      else wpKeys.push(r.key);
    }
    return { slugs, wpKeys };
  };
  const todo = [...selected].filter((k) => {
    const r = byKey.get(k);
    return r && (r.canUpdate || r.canInstall);
  });


  /** Steps that failed in the last run, for "Retry failed ones". */
  const [retry, setRetry] = useState<{ title: string; steps: PlanStep[] } | null>(null);

  /** Runs planned steps one request per step, with a live log. */
  const runSteps = async (title: string, plan: PlanStep[]) => {
    const label = (s: PlanStep) => ({ kind: s.kind, name: s.why ? t('%1$s (needed by %2$s)', s.name, s.why) : s.name });
    const steps: RunStep[] = plan.map((s) => ({ ...label(s), state: 'wait', lines: [] }));
    const failedSteps: PlanStep[] = [];
    const show = (finished: boolean) => setRun({ title, steps: steps.map((s) => ({ ...s, lines: [...s.lines] })), finished, hidden: false, failed: failedSteps.length });
    setRetry(null);
    show(false);
    for (let i = 0; i < plan.length; i++) {
      const s = plan[i]!;
      steps[i]!.state = 'run';
      show(false);
      try {
        const r = (await client.request(FlxSetupStepRun, { input: { kind: s.kind, slug: s.slug, id: s.id || null, version: s.version || null } })).flxSetupStepRun;
        const ok = !!r && r.ok && !r.errors.length;
        steps[i]!.state = ok ? 'ok' : 'err';
        steps[i]!.lines = r?.lines ?? r?.errors.map((e) => ({ ok: false, text: e.message })) ?? [];
        if (!ok) failedSteps.push(s);
      } catch (e) {
        steps[i]!.state = 'err';
        steps[i]!.lines = [{ ok: false, text: t('The server did not respond (%s). Check the history and system checks.', errorText(e, '')) }];
        failedSteps.push(s);
      }
      show(false);
    }
    show(true);
    setRetry(failedSteps.length ? { title, steps: failedSteps } : null);
    setNotice({ kind: failedSteps.length ? 'warning' : 'success', text: t('Done: %1$d succeeded, %2$d failed.', plan.length - failedSteps.length, failedSteps.length) });
  };

  /** Runs the steps the server plans for a selection. */
  const runPlan = async (title: string, vars: { slugs?: string[]; wpKeys?: string[]; all?: boolean; activate?: boolean }) => {
    if (busy) return;
    setBusy(true);
    setNotice(null);
    try {
      const plan = (await client.request(FlxSetupPlan, vars)).flxSetupPlan;
      if (!plan.length) {
        setNotice({ kind: 'success', text: t('Nothing to do.') });
        return;
      }
      await runSteps(title, plan);
      setSelected(new Set());
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
      void afterChange();
    }
  };

  /** Runs only the steps that failed last time, in the same order. */
  const retryFailed = async () => {
    if (busy || !retry) return;
    setBusy(true);
    setNotice(null);
    try {
      await runSteps(retry.title, retry.steps);
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
      void afterChange();
    }
  };

  const manage = useFlxMutation(FlxSetupManage, {
    onSuccess: (r) => {
      const p = r.flxSetupManage;
      if (!p) return fail(null);
      if (p.errors.length) return fail(new Error(p.errors[0]?.message));
      setSelected(new Set());
      setBulkOp('');
      setNotice({ kind: p.failed?.length ? 'warning' : 'success', text: p.summary ?? '' });
      void afterChange(true);
    },
    onError: fail,
  });
  const auto = useFlxMutation(FlxSetupAutoUpdateSet, {
    onSuccess: (r) => (r.flxSetupAutoUpdateSet?.errors.length ? fail(new Error(r.flxSetupAutoUpdateSet.errors[0]?.message)) : void afterChange(true)),
    onError: fail,
  });

  const applyBulk = () => {
    const op = bulkOp.toUpperCase() as 'ACTIVATE' | 'DEACTIVATE' | 'DELETE' | 'AUTO_ON' | 'AUTO_OFF';
    // Automatic updates exist only for installed packages: rows still to be installed are left out, not reported as skipped.
    const isAuto = op === 'AUTO_ON' || op === 'AUTO_OFF';
    const { slugs, wpKeys } = split([...selected].filter((k) => byKey.get(k)?.canManage && (!isAuto || byKey.get(k)?.installed)));
    if (!slugs.length && !wpKeys.length) return setNotice({ kind: 'warning', text: t('Nothing to do.') });
    const go = () => manage.mutate({ input: { op, slugs, wpKeys } });
    if (op === 'DELETE') {
      setConfirm({ text: t('Delete these packages from the site? Their files will be removed. %s', ''), action: t('Delete'), danger: true, run: go });
    } else go();
  };

  const updateAll = () => runPlan(t('Update all'), { all: true, activate });
  const installSelected = () => {
    const { slugs, wpKeys } = split(todo);
    void runPlan(t('Install / update selected'), { slugs, wpKeys, activate });
  };
  const installOne = (r: Row) => runPlan(r.canUpdate ? t('Updating %s…', r.name) : t('Installing %s…', r.name), r.group === 'FLUX' ? { slugs: [r.key], activate: true } : { wpKeys: [r.key] });
  const changeVersion = (r: Row, version: string) =>
    setConfirm({
      text: t('Install version %2$s of %1$s?', r.name, version),
      action: t('Install this version'),
      run: () => {
        setBusy(true);
        setRun({ title: t('Changing the version of %s (backup first)…', r.name), steps: [{ kind: 'VERSION', name: `${r.name} ${version}`, state: 'run', lines: [] }], finished: false, hidden: false, failed: 0 });
        client
          .request(FlxSetupStepRun, { input: { kind: 'VERSION', slug: r.key, id: null, version } })
          .then((res) => {
            const p = res.flxSetupStepRun;
            const ok = !!p && p.ok && !p.errors.length;
            setRun({ title: t('Changing the version of %s (backup first)…', r.name), steps: [{ kind: 'VERSION', name: `${r.name} ${version}`, state: ok ? 'ok' : 'err', lines: p?.lines ?? [] }], finished: true, hidden: false, failed: ok ? 0 : 1 });
            setNotice({ kind: ok ? 'success' : 'warning', text: t('Done: %1$d succeeded, %2$d failed.', ok ? 1 : 0, ok ? 0 : 1) });
          })
          .catch(fail)
          .finally(() => {
            setBusy(false);
            void afterChange();
          });
      },
    });

  const previewRow = preview ? byKey.get(preview) : undefined;
  const count = (n: number) => n.toLocaleString(locale);

  if (overview.isLoading && !o) return <Loading />;
  if (overview.isError && !o) {
    return (
      <div className="flxk-app">
        <div className="flxk-notice is-error">{overview.error.message || t('The action could not be completed.')}</div>
      </div>
    );
  }

  const updateTotal = o?.updateCount ?? 0;
  const tabLabel = (c: Overview['tabs'][number]) => c.label;

  return (
    <Screen
      className="flxk-app"
      width="wide"
      icon={host.appIcon}
      title={t('Packages')}
      actions={
        <div className="flxk-top">
          <div className="flxk-top__check">
            <button type="button" className="flxk-btn" disabled={check.isPending || busy} onClick={() => check.mutate({})}>
              {check.isPending && <span className="flxk-spin" aria-hidden="true" />}
              {check.isPending ? t('Checking…') : t('Check for updates')}
            </button>
            <span className="flxk-muted flxk-top__when">{check.data?.flxSetupCheck?.checkedLabel || o?.connection.checkedLabel}</span>
          </div>
          {updateTotal > 0 ? (
            <button type="button" className="flxk-btn is-primary flxk-updall" disabled={busy} onClick={() => void updateAll()}>
              {t('Update all (%s)', count(updateTotal))}
            </button>
          ) : (
            o?.connection.state === 'CONNECTED' && <span className="flxk-allgood">{t('All up to date ✓')}</span>
          )}
        </div>
      }
    >

      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <GoogleCards t={t} />

      {run && (
        <RunLog
          run={run}
          t={t}
          onToggle={() => setRun({ ...run, hidden: !run.hidden })}
          onClose={() => {
            setRun(null);
            setRetry(null);
          }}
          onRetry={run.finished && retry && !busy ? () => void retryFailed() : undefined}
        />
      )}

      {o && o.connection.state !== 'CONNECTED' && <StatusCard o={o} t={t} />}
      {o?.failingSources.map((f) => (
        <div key={f.name} className="flxk-strip is-err">
          <span>{t('Could not reach %1$s: %2$s Its packages are shown from the last known data.', f.name, f.error.replace(/\.$/, '') + '.')}</span>
          {o.settingsUrl && (
            <a href={o.settingsUrl + '#flxu-sources'}>{t('Sources')}</a>
          )}
        </div>
      ))}

      <div className="flxk-filter">
        <Tabs
          label={t('Filter packages')}
          current={tab}
          onChange={setTab}
          items={TABS.map((id) => {
            const c = o?.tabs.find((x) => x.tab === id);
            return {
              id,
              label: (
                <>
                  <span>{c ? tabLabel(c) : id}</span>
                  <span className="flxk-tab__n">{count(c?.count ?? 0)}</span>
                  {!!c?.updates && (
                    <span className="flxk-tab__up" title={t('Updates available')}>
                      {count(c.updates)}
                    </span>
                  )}
                </>
              ),
            };
          })}
        />
        <div className="flxk-filter__find">
          <input
            type="search"
            className="flxk-search"
            value={search}
            placeholder={t('Search plugins and themes…')}
            aria-label={t('Search plugins and themes')}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Escape' && setSearch('')}
          />
          <label className="flxk-check-label">
            <input type="checkbox" checked={onlyUpdates} onChange={(e) => setOnlyUpdates(e.target.checked)} /> {t('Only updates')}
          </label>
        </div>
      </div>

      {selected.size > 0 && (
        <div className="flxk-selbar" role="region" aria-label={t('Bulk actions')}>
          <strong>{tn('%s selected', '%s selected', selected.size, count(selected.size))}</strong>
          <button type="button" className="flxk-linkbtn" onClick={() => setSelected(new Set())}>
            {t('Clear selection')}
          </button>
          <span className="flxk-spacer" />
          {todo.length > 0 && (
            <>
              <label className="flxk-check-label">
                <input type="checkbox" checked={activate} onChange={(e) => setActivate(e.target.checked)} /> {t('Activate installed')}
              </label>
              <button type="button" className="flxk-btn is-primary" disabled={busy} onClick={installSelected}>
                {t('Install / update selected')}
              </button>
            </>
          )}
          {[...selected].some((k) => byKey.get(k)?.canManage) && (
            <span className="flxk-bulk">
              <select value={bulkOp} aria-label={t('Bulk action')} onChange={(e) => setBulkOp(e.target.value)}>
                <option value="">{t('Bulk actions')}</option>
                <option value="activate">{t('Activate')}</option>
                <option value="deactivate">{t('Deactivate')}</option>
                <option value="delete">{t('Delete')}</option>
                <option value="auto_on">{t('Turn on automatic updates')}</option>
                <option value="auto_off">{t('Turn off automatic updates')}</option>
              </select>
              <button type="button" className="flxk-btn" disabled={!bulkOp || manage.isPending} onClick={applyBulk}>
                {t('Apply')}
              </button>
            </span>
          )}
        </div>
      )}

      <div className="flxk-card flxk-table-wrap">
        <table className="flxk-table">
          <thead>
            <tr>
              <td className="flxk-c-check">
                <input
                  type="checkbox"
                  aria-label={t('Select all')}
                  checked={selectable.length > 0 && selectable.every((r) => selected.has(r.key))}
                  onChange={(e) => setSelected(e.target.checked ? new Set(selectable.map((r) => r.key)) : new Set())}
                />
              </td>
              <th>{t('Package')}</th>
              <th>{t('Installed')}</th>
              <th>{t('Latest version')}</th>
              <th className="flxk-c-auto">{t('Auto-update')}</th>
              <th>{t('Status')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <PackageRow
                key={r.key}
                row={r}
                t={t}
                selected={selected.has(r.key)}
                busy={busy}
                backupReady={!!o?.backup.ready}
                onPick={(on) => pick(r.key, on)}
                onPreview={() => setPreview(r.key)}
                onInstall={() => void installOne(r)}
                onVersion={(v) => changeVersion(r, v)}
                onAuto={(on) => r.autoUpdate.type && r.autoUpdate.id && auto.mutate({ input: { type: r.autoUpdate.type, id: r.autoUpdate.id, on } })}
              />
            ))}
            {!rows.length && (
              <tr>
                <td />
                <td colSpan={5} className="flxk-muted flxk-empty">
                  {rowsQ.isFetching && !rowsQ.data
                    ? t('Checking…')
                    : tab === 'ALL' && !query && !onlyUpdates && o?.connection.state !== 'CONNECTED'
                      ? t('The package list could not be fetched. Check the server address and key (if any) in Settings and press "Test connection".')
                      : t('Nothing matches this filter.')}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {o && <BackupStrip o={o} t={t} onInstall={() => runPlan(t('Install Flux Backup'), { slugs: ['flx-backup'], activate: true })} onActivate={() => runPlan(t('Activate'), { slugs: ['flx-backup'], activate: true })} />}

      <History t={t} tn={tn} locale={locale} notify={setNotice} />

      {previewRow && <Preview row={previewRow} t={t} onClose={() => setPreview(null)} />}
      {confirm && (
        <Modal title={confirm.action} onClose={() => setConfirm(null)}>
          <p className="flxk-confirm">{confirm.text}</p>
          <div className="flxk-modal-actions">
            <button type="button" className="flxk-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className={'flxk-btn ' + (confirm.danger ? 'is-danger' : 'is-primary')}
              onClick={() => {
                confirm.run();
                setConfirm(null);
              }}
            >
              {confirm.action}
            </button>
          </div>
        </Modal>
      )}
      <span className="flxk-sr" aria-live="polite">
        {tn('%s package', '%s packages', rows.length, count(rows.length))}
      </span>
    </Screen>
  );
}

function StatusCard({ o, t }: { o: Overview; t: ReturnType<typeof useSetup>['t'] }) {
  const c = o.connection;
  return (
    <div className={'flxk-status is-' + c.state.toLowerCase()}>
      <span className="flxk-dot" aria-hidden="true" />
      <div className="flxk-status__body">
        <strong>{c.title}</strong>
        <span>{c.message}</span>
        <small className="flxk-muted">
          {c.checkedLabel}
          {c.packageCount > 0 && ' · ' + t('%1$d packages, %2$d updates', c.packageCount, c.updateCount)}
        </small>
      </div>
      {o.settingsUrl && (
        <a className="flxk-btn" href={o.settingsUrl}>
          {t('Settings')}
        </a>
      )}
    </div>
  );
}

/** Optional Google setup cards (Google Site Kit, Google for WooCommerce): install, or "Not needed" to close site-wide. */
function GoogleCards({ t }: { t: ReturnType<typeof useSetup>['t'] }) {
  const q = useFlxQuery(FlxSetupGoogleCards, {});
  const [closed, setClosed] = useState<string[]>([]);
  const dismiss = useFlxMutation(FlxSetupGoogleCardDismiss, {});
  const cards = (q.data?.flxSetupGoogleCards ?? []).filter((c) => !closed.includes(c.id));
  if (!cards.length) return null;
  return (
    <>
      {cards.map((c) => (
        <div key={c.id} className="flxk-gcard">
          <div className="flxk-gcard__body">
            <h2>{c.title}</h2>
            <p>{c.text}</p>
          </div>
          <div className="flxk-gcard__act">
            <button
              type="button"
              className="flxk-btn is-ghost"
              onClick={() => {
                setClosed((x) => [...x, c.id]);
                dismiss.mutate({ input: { id: c.id } });
              }}
            >
              {t('Not needed')}
            </button>
            <a className="flxk-btn is-primary" href={c.installUrl}>
              {c.button}
            </a>
          </div>
        </div>
      ))}
    </>
  );
}

function BackupStrip({ o, t, onInstall, onActivate }: { o: Overview; t: ReturnType<typeof useSetup>['t']; onInstall: () => void; onActivate: () => void }) {
  const wp = (
    <a href={o.wordpressUpdatesUrl} className="flxk-strip__end">
      {t('WordPress updates')}
    </a>
  );
  if (o.backup.ready) {
    return (
      <div className="flxk-strip is-ok">
        <span>{t('A backup is taken before every update (Flux Backup).')}</span>
        <a href={o.backupUrl}>{t('Backups')}</a>
        {wp}
      </div>
    );
  }
  return (
    <div className="flxk-strip is-warn">
      <span>{t('No backup before updates: the Flux Backup plugin is not installed or active.')}</span>
      {o.backup.activatable && (
        <button type="button" className="flxk-linkbtn" onClick={onActivate}>
          {t('Activate')}
        </button>
      )}
      {!o.backup.activatable && o.backup.installable && (
        <button type="button" className="flxk-linkbtn" onClick={onInstall}>
          {t('Install Flux Backup')}
        </button>
      )}
      {wp}
    </div>
  );
}

function RunLog({ run, t, onToggle, onClose, onRetry }: { run: Run; t: ReturnType<typeof useSetup>['t']; onToggle: () => void; onClose: () => void; onRetry?: () => void }) {
  const done = run.steps.filter((s) => s.state === 'ok' || s.state === 'err').length;
  const current = run.steps.find((s) => s.state === 'run');
  const stepText = (s: RunStep) => {
    switch (s.kind) {
      case 'INSTALL':
        return t('Installing %s…', s.name);
      case 'WPORG':
        return t('Installing %s from wordpress.org…', s.name);
      case 'ACTIVATE':
        return t('Activating %s…', s.name);
      case 'UPDATE':
        return t('Updating %s (backup first)…', s.name);
      case 'VERSION':
        return t('Changing the version of %s (backup first)…', s.name);
      default:
        return t('Updating %s…', s.name);
    }
  };
  return (
    <div className="flxk-log" role="status">
      <div className="flxk-log__head">
        {!run.finished && <span className="flxk-spin" aria-hidden="true" />}
        <strong>{run.finished ? t('Done: %1$d succeeded, %2$d failed.', run.steps.length - run.failed, run.failed) : current ? stepText(current) : t('Working, please stay on this page…')}</strong>
        <span className="flxk-muted">{t('%1$d of %2$d', Math.min(done + (run.finished ? 0 : 1), run.steps.length), run.steps.length)}</span>
        <span className="flxk-spacer" />
        {onRetry && (
          <button type="button" className="flxk-btn is-small" onClick={onRetry}>
            {t('Retry failed ones')}
          </button>
        )}
        <button type="button" className="flxk-linkbtn" onClick={onToggle}>
          {run.hidden ? t('Show log') : t('Hide log')}
        </button>
        {run.finished && (
          <button type="button" className="flxk-linkbtn" onClick={onClose}>
            {t('Dismiss')}
          </button>
        )}
      </div>
      <div className="flxk-log__bar">
        <span style={{ width: `${run.steps.length ? Math.round((done / run.steps.length) * 100) : 0}%` }} />
      </div>
      {!run.finished && <p className="flxk-muted flxk-log__stay">{t('Stay on this page until it finishes')}</p>}
      {!run.hidden && (
        <ol className="flxk-log__lines">
          {run.steps.map((s, i) => (
            <li key={i} className={'is-' + s.state}>
              <span className="flxk-log__mark" aria-hidden="true">
                {s.state === 'ok' ? '✓' : s.state === 'err' ? '✕' : s.state === 'run' ? '…' : '·'}
              </span>
              <span>
                {s.state === 'wait' ? t('Queued') + ': ' : ''}
                {stepText(s)}
                {s.lines.map((l, j) => (
                  <small key={j} className={l.ok ? 'flxk-muted' : 'flxk-bad'}>
                    {l.text}
                  </small>
                ))}
              </span>
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}

function PackageRow({
  row: r,
  t,
  selected,
  busy,
  backupReady,
  onPick,
  onPreview,
  onInstall,
  onVersion,
  onAuto,
}: {
  row: Row;
  t: ReturnType<typeof useSetup>['t'];
  selected: boolean;
  busy: boolean;
  backupReady: boolean;
  onPick: (on: boolean) => void;
  onPreview: () => void;
  onInstall: () => void;
  onVersion: (v: string) => void;
  onAuto: (on: boolean) => void;
}) {
  const [ver, setVer] = useState(r.versions[0]?.version ?? '');
  const [open, setOpen] = useState(false);
  const [iconBad, setIconBad] = useState(false);
  const hue = useMemo(() => [...r.name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 7), [r.name]);
  const a = r.autoUpdate;
  const date = (x: { label: string } | null) => (x ? x.label : '');
  return (
    <tr className={'flxk-row is-' + r.state.toLowerCase() + (selected ? ' is-sel' : '')}>
      <th scope="row" className="flxk-c-check">
        {(r.canUpdate || r.canInstall || r.canManage) && <input type="checkbox" checked={selected} aria-label={r.name} onChange={(e) => onPick(e.target.checked)} />}
      </th>
      <td className="flxk-c-pkg">
        <div className="flxk-pkg">
          {r.iconUrl && !iconBad ? (
            <img src={r.iconUrl} width={40} height={40} alt="" onError={() => setIconBad(true)} />
          ) : (
            <span className="flxk-pkg__ph" style={{ ['--flxk-h' as string]: hue }} aria-hidden="true">
              {r.name.charAt(0)}
            </span>
          )}
          <div className="flxk-pkg__main">
            <strong>
              <button type="button" className="flxk-name" onClick={onPreview} aria-haspopup="dialog">
                {r.name}
              </button>
            </strong>
            {r.tags.length > 0 && (
              <span className="flxk-tags">
                {r.tags.map((tag) => (
                  <span key={tag.label + tag.kind} className={'flxk-tag is-' + tag.kind}>
                    {tag.label}
                  </span>
                ))}
              </span>
            )}
            {r.subtitle && r.group !== 'FLUX' && <small className="flxk-muted">{r.subtitle}</small>}
            {r.showSource && (
              <small className="flxk-muted">
                {t('Source: %s', r.sourceName)}
                {r.sourceCount > 1 && ' · ' + t('available from %d sources', r.sourceCount)}
              </small>
            )}
            {r.sourceNote && <small className="flxk-muted">{r.sourceNote}</small>}
            {r.requires.length > 0 && (
              <small className="flxk-muted">
                {t('Requires:')}{' '}
                {r.requires.map((d, i) => (
                  <span
                    key={d.slug}
                    className={'flxk-req ' + (d.state === 'ACTIVE' ? 'is-ok' : 'is-missing')}
                    title={d.state === 'ACTIVE' ? t('Active') : d.state === 'INACTIVE' ? t('Installed, inactive') : t('Not installed')}
                  >
                    {i > 0 && ', '}
                    {d.name}
                    {d.state === 'ACTIVE' && ' ✓'}
                  </span>
                ))}
              </small>
            )}
          </div>
        </div>
      </td>
      <td className="flxk-c-ver" data-label={t('Installed')}>
        {r.installed || '—'}
        {r.installed && !r.active && r.kind !== 'FLUX_THEME' && r.kind !== 'WP_THEME' && r.kind !== 'WP_TRANSLATIONS' && r.kind !== 'WP_CORE' && (
          <>
            <br />
            <small className="flxk-bad">{t('Inactive')}</small>
          </>
        )}
      </td>
      <td className="flxk-c-ver" data-label={t('Latest version')}>
        {r.latest}
        {r.released && (
          <>
            <br />
            <small className="flxk-muted">{date(r.released)}</small>
          </>
        )}
      </td>
      <td className="flxk-c-auto" data-label={t('Auto-update')}>
        {a.mode === 'UNAVAILABLE' || (!r.installed && a.mode !== 'LOCKED') ? (
          <span className="flxk-muted" title={r.installed ? t('Automatic updates are turned off for this site.') : undefined}>
            —
          </span>
        ) : a.mode === 'LOCKED' ? (
          <span className="flxk-auto is-locked" title={t('On for all FluxWP packages (FluxWP Panel › Settings).')}>
            <Switch checked disabled label={t('On')} onChange={() => undefined} />
            {t('On')}
          </span>
        ) : (
          <span className="flxk-auto">
            <Switch checked={a.mode === 'ON'} disabled={!a.canChange} label={r.name} onChange={onAuto} />
            {a.mode === 'ON' ? t('On') : t('Off')}
          </span>
        )}
      </td>
      <td className="flxk-actions" data-label={t('Status')}>
        {r.state === 'UPDATE' ? (
          <>
            <span className="flxk-badge is-update">{t('Update available')}</span>
            {r.canUpdate && (
              <button type="button" className="flxk-btn is-small" disabled={busy} onClick={onInstall}>
                {t('Update')}
              </button>
            )}
          </>
        ) : r.state === 'CURRENT' ? (
          <span className="flxk-badge is-ok">{t('Up to date')}</span>
        ) : (
          <>
            <span className="flxk-badge">{t('Not installed')}</span>
            {r.canInstall && (
              <button type="button" className="flxk-btn is-small" disabled={busy} onClick={onInstall}>
                {t('Install')}
              </button>
            )}
          </>
        )}
        {r.installed && r.canChangeVersion && r.versions.length > 0 && (
          <span className="flxk-ver">
            <button type="button" className="flxk-linkbtn" aria-expanded={open} onClick={() => setOpen(!open)}>
              {t('Change version')} ▾
            </button>
            {open && (
              <div className="flxk-ver__box">
                <select value={ver} aria-label={t('Version')} onChange={(e) => setVer(e.target.value)}>
                  {r.versions.map((v) => (
                    <option key={v.version} value={v.version}>
                      {v.version}
                      {v.released ? ' · ' + date(v.released) : ''}
                      {v.older ? ' · ' + t('older') : ''}
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  className="flxk-btn is-small"
                  disabled={busy || !ver}
                  onClick={() => {
                    setOpen(false);
                    onVersion(ver);
                  }}
                >
                  {t('Install this version')}
                </button>
                <p className="flxk-muted">{backupReady ? t('A backup is taken first.') : t('Flux Backup is not installed: no backup will be taken.')}</p>
              </div>
            )}
          </span>
        )}
      </td>
    </tr>
  );
}

function Preview({ row: r, t, onClose }: { row: Row; t: ReturnType<typeof useSetup>['t']; onClose: () => void }) {
  const [shot, setShot] = useState(0);
  return (
    <Modal title={r.name} onClose={onClose} wide>
      <div className="flxk-preview">
        <p>{r.description || t('A short overview is not available yet.')}</p>
        {r.images.length > 0 ? (
          <div className="flxk-preview__shots">
            <img src={r.images[shot]} alt={t('Preview image %d', shot + 1)} />
            {r.images.length > 1 && (
              <div className="flxk-preview__thumbs" aria-label={t('Example screenshots')}>
                {r.images.map((src, i) => (
                  <button key={src} type="button" className={i === shot ? 'is-active' : ''} onClick={() => setShot(i)} aria-label={t('Preview image %d', i + 1)}>
                    <img src={src} alt="" />
                  </button>
                ))}
              </div>
            )}
          </div>
        ) : (
          <p className="flxk-muted">{t('No preview images are available yet.')}</p>
        )}
        <dl className="flxk-preview__meta">
          {r.info.map((i) => (
            <div key={i.label}>
              <dt>{i.label}</dt>
              <dd>{i.value}</dd>
            </div>
          ))}
        </dl>
        {r.website && (
          <p>
            <a href={r.website} target="_blank" rel="noopener noreferrer">
              {t('Open on website')}
            </a>{' '}
            <span className="flxk-muted">{t('For full details and more examples, visit the website.')}</span>
          </p>
        )}
        <div className="flxk-modal-actions">
          <button type="button" className="flxk-btn" onClick={onClose}>
            {t('Close preview')}
          </button>
        </div>
      </div>
    </Modal>
  );
}
