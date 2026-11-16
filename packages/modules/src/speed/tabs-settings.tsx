// Performance › PageSpeed, Cache and Settings (administrators).
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxSpeedCache,
  FlxSpeedCacheRun,
  FlxSpeedConfig,
  FlxSpeedPageSpeed,
  FlxSpeedPageSpeedClear,
  FlxSpeedPageSpeedSave,
  FlxSpeedSettings,
  FlxSpeedSettingsSave,
} from '@fluxwp/graphql/operations';
import type { FlxSpeedCacheFieldsFragment, FlxSpeedCacheOp, FlxSpeedPageSpeedFieldsFragment, FlxSpeedSettingsFieldsFragment } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Check, ErrorBox, Pill, Switch, errorText, fmtBytes, num, useSpeed, useT } from './ui';
import { psSections } from './pagespeed-rows';

type PS = FlxSpeedPageSpeedFieldsFragment;
type CacheState = FlxSpeedCacheFieldsFragment;

/* ------------------------------------------------------------ PageSpeed */

interface PsDraft {
  on: Record<string, boolean>;
  cssScope: 'THEME' | 'LOCAL';
  inlineKb: string;
  safelist: string;
  exclude: string;
  skipUrls: string;
}
const psDraft = (p: PS): PsDraft => ({
  on: Object.fromEntries(p.toggles.map((x) => [x.key, x.on])),
  cssScope: p.cssScope,
  inlineKb: String(p.inlineKb),
  safelist: p.safelist,
  exclude: p.exclude,
  skipUrls: p.skipUrls,
});

export function PageSpeedTab() {
  const { t, lang } = useT();
  const { notify } = useSpeed();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxSpeedPageSpeed, {});
  const [d, setD] = useState<PsDraft | null>(null);
  useEffect(() => {
    if (q.data) setD(psDraft(q.data.flxSpeedPageSpeed));
  }, [q.data]);
  const put = (p: PS) => qc.setQueryData(flxQueryKey(scope, FlxSpeedPageSpeed, {}), { flxSpeedPageSpeed: p });
  const fail = (e: unknown) => notify('error', errorText(e, t('The action could not be completed.')));
  const save = useFlxMutation(FlxSpeedPageSpeedSave, {
    onSuccess: (r) => {
      const p = r.flxSpeedPageSpeedSave;
      if (p?.errors.length) return notify('error', p.errors.map((e) => e.message).join(' '));
      if (p?.pageSpeed) put(p.pageSpeed);
      notify('success', t('Saved. The cache and the LiteSpeed page cache were cleared; browse a few pages to check.'));
    },
    onError: fail,
  });
  const clear = useFlxMutation(FlxSpeedPageSpeedClear, {
    onSuccess: (r) => {
      const p = r.flxSpeedPageSpeedClear;
      if (p?.errors.length) return notify('error', p.errors.map((e) => e.message).join(' '));
      if (p?.pageSpeed) put(p.pageSpeed);
      notify('success', t('PageSpeed cache cleared.'));
    },
    onError: fail,
  });
  if (q.isLoading || (!d && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !d) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const p = q.data.flxSpeedPageSpeed;
  const forced = (k: string) => p.toggles.find((x) => x.key === k)?.forced ?? false;
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const kb = Number.parseInt(d.inlineKb, 10);
    const on = (k: string) => d.on[k] ?? false;
    save.mutate({
      input: {
        viewport: on('ps_viewport'),
        emoji: on('ps_emoji'),
        css: on('ps_css'),
        minCss: on('ps_min_css'),
        minJs: on('ps_min_js'),
        defer: on('ps_defer'),
        deferJquery: on('ps_defer_jquery'),
        fonts: on('ps_fonts'),
        preload: on('ps_preload'),
        cssScope: d.cssScope,
        inlineKb: Number.isFinite(kb) ? Math.max(0, Math.min(500, kb)) : 0,
        safelist: d.safelist,
        exclude: d.exclude,
        skipUrls: d.skipUrls,
      },
    });
  };
  const sub = (kind: 'css' | 'defer'): ReactNode =>
    kind === 'css' ? (
      <div className="flxs-sub">
        <fieldset>
          <legend>{t('Scope')}</legend>
          <label>
            <input type="radio" name="ps-scope" checked={d.cssScope === 'THEME'} onChange={() => setD({ ...d, cssScope: 'THEME' })} /> {t('Theme CSS only (recommended)')}
          </label>{' '}
          <label>
            <input type="radio" name="ps-scope" checked={d.cssScope === 'LOCAL'} onChange={() => setD({ ...d, cssScope: 'LOCAL' })} /> {t('Theme + plugins (all CSS under wp-content)')}
          </label>
        </fieldset>
        <p>
          <label htmlFor="flxs-ps-inline">{t('Inline limit')}</label>{' '}
          <input id="flxs-ps-inline" type="number" min={0} max={500} className="flxs-small" value={d.inlineKb} onChange={(e) => setD({ ...d, inlineKb: e.currentTarget.value })} /> KB
          <span className="flxs-desc"> {t('Trimmed CSS is written into <head> up to this size; anything beyond is loaded as a separate cached file. 0 = always a file.')}</span>
        </p>
        <p>
          <label htmlFor="flxs-ps-safelist">{t('Always keep these classes')}</label>
          <textarea id="flxs-ps-safelist" rows={3} className="flxs-code" placeholder={'menu-open\nswiper-*'} value={d.safelist} onChange={(e) => setD({ ...d, safelist: e.currentTarget.value })} />
          <span className="flxs-desc">{t('One class or id per line, * as wildcard. If a style added later by JavaScript breaks, add its class here. is-*, has-*, wp-*, swiper-* are always kept.')}</span>
        </p>
      </div>
    ) : (
      <div className="flxs-sub">
        <p className="flxs-desc">{t('jQuery stays blocking unless "Defer jQuery too" is on. WordPress itself drops defer for scripts that have an inline script after them or that a blocking script depends on.')}</p>
      </div>
    );
  return (
    <>
      <p className="flxs-intro">{t('Google PageSpeed / Lighthouse suggestions that can be applied without changing the theme. Each one is switched on separately; after enabling a "Test first" item, check a few pages of the site (home, a post, donation/checkout).')}</p>
      <form onSubmit={submit}>
        {psSections(t).map((s) => (
          <section key={s.title} className="flxs-card2">
            <h2>{s.title}</h2>
            {s.rows.map((r) => (
              <div key={r.key} className={'flxs-ps-row' + (d.on[r.key] ? ' is-on' : '')}>
                <Switch checked={d.on[r.key] ?? false} label={r.title} onChange={(v) => setD({ ...d, on: { ...d.on, [r.key]: v } })} />
                <div className="flxs-ps-text">
                  <strong>{r.title}</strong> <span className={'flxs-badge ' + (r.risk === 'safe' ? 'is-safe' : 'is-test')}>{r.risk === 'safe' ? t('Safe') : t('Test first')}</span>
                  <p className="flxs-desc">{r.desc}</p>
                  {forced(r.key) && <p className="flxs-forced">{t('The theme or a preset overrides this feature in code (flxp_pagespeed_enabled); this setting may not take effect.')}</p>}
                  {r.extra && sub(r.extra)}
                </div>
              </div>
            ))}
          </section>
        ))}
        <section className="flxs-card2">
          <h2>{t('Exclusions')}</h2>
          <p>
            <label htmlFor="flxs-ps-exclude">
              <strong>{t('Files to leave alone')}</strong>
            </label>
            <textarea id="flxs-ps-exclude" rows={3} className="flxs-code" placeholder={'woocommerce\nflx-donate'} value={d.exclude} onChange={(e) => setD({ ...d, exclude: e.currentTarget.value })} />
            <span className="flxs-desc">{t('One handle or part of a URL per line. Trimming, minifying and defer are not applied to these files.')}</span>
          </p>
          <p>
            <label htmlFor="flxs-ps-skip">
              <strong>{t('Pages to leave alone')}</strong>
            </label>
            <textarea id="flxs-ps-skip" rows={2} className="flxs-code" placeholder={'/checkout/\n/my-account/'} value={d.skipUrls} onChange={(e) => setD({ ...d, skipUrls: e.currentTarget.value })} />
            <span className="flxs-desc">{t('One URL part per line. No PageSpeed optimization runs on these pages.')}</span>
          </p>
        </section>
        <button type="submit" className="flxs-btn is-primary" disabled={save.isPending}>
          {t('Save and clear cache')}
        </button>
      </form>

      <div className="flxs-env">
        <section className="flxs-card2">
          <h2>{t('Status')}</h2>
          <table className="flxs-kv">
            <tbody>
              <tr>
                <th>{t('Cache')}</th>
                <td>
                  {t('%1$d CSS, %2$d JS copies · %3$s', p.cssFiles, p.jsFiles, fmtBytes(p.cacheBytes, lang)) + ' '}
                  <code>wp-content/uploads/flx-speed/</code>
                </td>
              </tr>
              <tr>
                <th>{t('Compare')}</th>
                <td>
                  <a href={p.homeUrl} target="_blank" rel="noopener noreferrer">
                    {t('Optimized')}
                  </a>{' '}
                  ·{' '}
                  <a href={p.offUrl} target="_blank" rel="noopener noreferrer">
                    {t('Off (?flxp_off=1)')}
                  </a>
                  <p className="flxs-desc">{t('Adding ?flxp_off=1 to a URL opens the page without optimizations; if the page looks different, the cause is here.')}</p>
                </td>
              </tr>
              <tr>
                <th>{t('Measure')}</th>
                <td>
                  {p.auditUrl ? (
                    <>
                      <a href={p.auditUrl}>{t('Flux SEO › Site audit')}</a>
                      <p className="flxs-desc">{t('The site audit suggests these settings itself and turns them on with one click.')}</p>
                    </>
                  ) : (
                    <a href={'https://pagespeed.web.dev/analysis?url=' + encodeURIComponent(p.homeUrl)} target="_blank" rel="noopener noreferrer">
                      PageSpeed Insights
                    </a>
                  )}
                </td>
              </tr>
            </tbody>
          </table>
          <button type="button" className="flxs-btn" disabled={clear.isPending} onClick={() => clear.mutate({})}>
            {t('Clear cache')}
          </button>
        </section>
        <section className="flxs-card2">
          <h2>{t('Animations not running on the GPU')}</h2>
          <p className="flxs-desc">{t('Lighthouse "non-composited animations": properties other than transform and opacity (stroke-dashoffset, background, width…) repaint every frame. A setting cannot fix this; the theme or preset must rewrite them with transform/opacity.')}</p>
          {p.animations.length > 0 ? (
            <div className="flxs-tablewrap">
              <table className="flxs-list">
                <thead>
                  <tr>
                    <th>{t('Animation')}</th>
                    <th>{t('Properties')}</th>
                    <th>{t('File')}</th>
                  </tr>
                </thead>
                <tbody>
                  {p.animations.map((a) => (
                    <tr key={a.name + a.file}>
                      <td data-l={t('Animation')}>
                        <code>@keyframes {a.name}</code>
                      </td>
                      <td data-l={t('Properties')}>{a.props.join(', ')}</td>
                      <td data-l={t('File')} className="flxs-url">
                        {a.file} {!a.reduced && <span className="flxs-flag flxs-flag-slow">{t('no prefers-reduced-motion')}</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="flxs-ok">{t('All good: animations in the CSS files seen use transform/opacity.')}</p>
          )}
          <p className="flxs-desc">
            {p.seenAt && p.seenFiles !== null
              ? t('Scanned %1$d CSS files seen on the front end (%2$s).', p.seenFiles, p.seenAt.relative)
              : t('The front end has not been seen yet, so the theme folder was scanned. Once the site is opened, the files the page loads are scanned.')}
          </p>
        </section>
      </div>
    </>
  );
}

/* ------------------------------------------------------------ Cache */

type Op = FlxSpeedCacheOp;

export function CacheTab() {
  const { t, lang } = useT();
  const { notify } = useSpeed();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxSpeedCache, {});
  const [ask, setAsk] = useState<{ text: string; label: string; op: Op } | null>(null);
  const run = useFlxMutation(FlxSpeedCacheRun, {
    onSuccess: (r) => {
      const p = r.flxSpeedCacheRun;
      if (p?.errors.length) return notify('error', p.errors.map((e) => e.message).join(' '));
      if (p?.cache) qc.setQueryData(flxQueryKey(scope, FlxSpeedCache, {}), { flxSpeedCache: p.cache });
      if (p?.message) notify('success', p.message);
    },
    onError: (e) => notify('error', errorText(e, t('The action could not be completed.'))),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const st: CacheState = q.data.flxSpeedCache;
  const ls = st.litespeed;
  const sq = st.sqlite;
  const ext = st.extensions;
  const busy = run.isPending;
  const btn = (op: Op, label: string, primary = false, confirmText = '') => (
    <button
      key={op}
      type="button"
      className={'flxs-btn' + (primary ? ' is-primary' : '')}
      disabled={busy}
      onClick={() => (confirmText ? setAsk({ text: confirmText, label, op }) : run.mutate({ input: { op } }))}
    >
      {label}
    </button>
  );
  const owners: Record<string, string> = { litespeed: 'LiteSpeed Cache', sqlite: 'SQLite Object Cache', redis: 'Redis', memcached: 'Memcached', other: st.dropinName || t('another plugin'), none: t('None') };
  const provider = owners[st.provider] ?? st.provider;
  const req = (state: boolean | 'opt', label: string, hint = '') => (
    <li className={'flxs-req ' + (state === true ? 'is-ok' : state === 'opt' ? 'is-opt' : 'is-no')}>
      <span className="flxs-mark" aria-hidden="true">
        {state === true ? '✓' : state === 'opt' ? '–' : '✕'}
      </span>
      <span>
        {label} {hint && <span className="flxs-hint">{hint}</span>}
      </span>
    </li>
  );
  const needPlugin = (key: 'litespeed' | 'sqlite', feature: string) => {
    const p = key === 'litespeed' ? ls : sq;
    if (p.active) return null;
    return (
      <div className="flxs-need">
        <p>{p.installed ? t('%1$s needs the %2$s plugin to be active. It is installed but turned off.', feature, p.name) : t('%1$s needs the %2$s plugin. It is not installed yet.', feature, p.name)}</p>
        {p.installed ? (
          btn(key === 'litespeed' ? 'ACTIVATE_LITESPEED' : 'ACTIVATE_SQLITE', t('Activate'), true)
        ) : st.canInstall ? (
          btn(key === 'litespeed' ? 'INSTALL_LITESPEED' : 'INSTALL_SQLITE', t('Install and activate'), true)
        ) : (
          <p className="flxs-desc">{t('Ask an administrator who can install plugins.')}</p>
        )}
      </div>
    );
  };
  const rec = ext.memcached ? 'litespeed' : 'sqlite';
  const usedSqlite = sq.active && st.dropinOwner === 'sqlite';
  const usedLs = ls.active && ls.object && st.dropinOwner === 'litespeed';
  const back = ext.memcached || ext.redis;
  const verLabel = (p: { active: boolean; installed: boolean; version: string }, notInstalled: string) => (p.active ? t('version %s', p.version) : p.installed ? t('installed, not active') : notInstalled);
  const testLine = () => {
    const x = st.test;
    if (!x) return <p className="flxs-desc">{t('Test: writes a value in this request and reads it in a separate request. Only then is the cache really shared between visitors.')}</p>;
    const prov = owners[x.provider] ?? (x.provider || owners.none || '');
    if (x.error) return <p className="flxs-test is-warn">{t('Last test (%1$s) could not finish: %2$s', x.at.relative, x.error)}</p>;
    if (x.persistent) return <p className="flxs-test is-ok">{t('Last test (%1$s): works, %2$s keeps values between requests. 100 writes + 100 reads took %3$s ms.', x.at.relative, prov, num(x.ms, lang, 1))}</p>;
    return <p className="flxs-test is-warn">{t('Last test (%s): values are lost after the request ends, so there is no persistent object cache.', x.at.relative)}</p>;
  };
  const badge = (used: boolean, recommended: boolean) =>
    used ? <span className="flxs-badge is-safe">{t('In use')}</span> : recommended ? <span className="flxs-badge is-test">{t('Recommended here')}</span> : null;
  return (
    <div className="flxs-cache">
      <p className="flxs-intro">{t('Two layers make the site fast: the page cache serves ready HTML without running PHP, the object cache keeps database results between requests. Only one object cache can run at a time; this screen keeps it that way.')}</p>
      {st.pendingError && <div className="flxs-notice is-warn inline">{st.pendingError}</div>}
      {st.conflicts.map((c) => (
        <div key={c.code} className="flxs-alert">
          <p>
            <strong>{t('Cache conflict')}</strong> {c.text}
          </p>
          {c.repairable && btn('REPAIR', t('Repair'), true)}
        </div>
      ))}

      <section className="flxs-card2">
        <h2>
          {t('Page cache')} <span className="flxs-by">LiteSpeed Cache</span>
        </h2>
        <ul className="flxs-reqs">
          {req(st.litespeedServer, t('LiteSpeed web server'), st.litespeedServer ? '' : t('Not detected: LiteSpeed page cache works only on a LiteSpeed server (or through QUIC.cloud).'))}
          {req(ls.active, t('LiteSpeed Cache plugin'), verLabel(ls, t('not installed')))}
        </ul>
        {needPlugin('litespeed', t('Page cache'))}
        {ls.active && (
          <>
            <div className="flxs-row">
              <div>
                {t('Page cache')} <Pill on={ls.pageCache} yes={t('On')} no={t('Off')} />
              </div>
              <div className="flxs-actions">
                {ls.pageCache ? btn('PAGE_OFF', t('Turn off')) : btn('PAGE_ON', t('Turn on'), true)}
                {btn('PURGE', t('Purge all'))}
                <a href={st.settingsUrls.litespeedPage}>{t('LiteSpeed settings')}</a>
              </div>
            </div>
            <p className="flxs-desc">{t('Logged-in users, cart and checkout are not cached by LiteSpeed by default. Purge after changing the theme or menus.')}</p>
          </>
        )}
      </section>

      <section className="flxs-card2">
        <h2>{t('Object cache')}</h2>
        <div className="flxs-row">
          <div>
            {t('In use:')} <strong>{provider}</strong> <Pill on={st.persistent} yes={t('Persistent')} no={t('Not persistent')} />
          </div>
          <div className="flxs-actions">
            {btn('TEST', t('Test'))}
            {st.persistent && btn('FLUSH', t('Empty object cache'))}
          </div>
        </div>
        {testLine()}
        <div className="flxs-opts">
          <div className={'flxs-opt' + (usedSqlite ? ' is-used' : '')}>
            <h3>SQLite Object Cache {badge(usedSqlite, rec === 'sqlite')}</h3>
            <p className="flxs-desc">{t('Keeps the cache in a SQLite file in wp-content. Needs no extra service, so it fits shared hosting without Redis/Memcached.')}</p>
            <ul className="flxs-reqs">
              {req(ext.sqlite3, t('PHP sqlite3 extension'), ext.sqlite3 ? '' : t('required; enable it in the hosting PHP settings'))}
              {req(ext.igbinary ? true : 'opt', t('PHP igbinary extension'), ext.igbinary ? '' : t('optional, makes it smaller and faster'))}
              {req(sq.active, t('SQLite Object Cache plugin'), verLabel(sq, t('not installed, installed from wordpress.org with one click')))}
            </ul>
            {usedSqlite && sq.fileName && (
              <p className="flxs-meta">
                {t('Cache file %1$s, %2$s.', sq.fileName, fmtBytes(sq.fileSize, lang))} <a href={st.settingsUrls.sqlitePlugin}>{t('Plugin settings')}</a>
              </p>
            )}
            {!usedSqlite &&
              (ext.sqlite3 ? (
                btn(
                  'USE_SQLITE',
                  sq.installed ? t('Use SQLite Object Cache') : t('Install and use SQLite Object Cache'),
                  rec === 'sqlite' || !st.dropinExists,
                  st.dropinExists ? t('The current object cache is turned off and SQLite Object Cache takes over. Continue?') : '',
                )
              ) : (
                <p className="flxs-blocked">{t('Cannot be used on this server without the sqlite3 extension.')}</p>
              ))}
          </div>
          <div className={'flxs-opt' + (usedLs ? ' is-used' : '')}>
            <h3>
              {t('LiteSpeed Cache + Memcached')} {badge(usedLs, rec === 'litespeed')}
            </h3>
            <p className="flxs-desc">{t("LiteSpeed Cache's own object cache, kept in Memcached memory. On Hostinger, Memcached is switched on per site in hPanel.")}</p>
            <ul className="flxs-reqs">
              {req(ext.memcached ? true : ext.redis ? 'opt' : false, t('Memcached PHP extension'), ext.memcached ? '' : ext.redis ? t('missing; Redis is used instead') : t('missing; on Hostinger: hPanel › Advanced › PHP Configuration › Memcached'))}
              {req(ls.active, t('LiteSpeed Cache plugin'), verLabel(ls, t('not installed, installed from wordpress.org with one click')))}
            </ul>
            {usedLs && (
              <p className="flxs-meta">
                {(ls.objectKind === 'REDIS' ? 'Redis' : 'Memcached') + ' ' + ls.objectHost} <a href={st.settingsUrls.litespeedObject}>{t('Plugin settings')}</a>
              </p>
            )}
            {!usedLs &&
              (back ? (
                btn(
                  'USE_LITESPEED',
                  ls.installed ? t('Use LiteSpeed object cache') : t('Install LiteSpeed Cache and use it'),
                  rec === 'litespeed' || !st.dropinExists,
                  st.dropinExists ? t('The current object cache is turned off and LiteSpeed Cache takes over. Continue?') : '',
                )
              ) : (
                <p className="flxs-blocked">{t('Enable Memcached for this site in the hosting panel first; this option unlocks afterwards.')}</p>
              ))}
          </div>
        </div>
        {st.dropinExists && (st.provider === 'sqlite' || st.provider === 'litespeed') && (
          <div className="flxs-foot">{btn('OBJECT_OFF', t('Turn object cache off'), false, t('Turn the object cache off? The site keeps working, only slower on database-heavy pages.'))}</div>
        )}
      </section>
      {ask && (
        <Modal title={ask.label} onClose={() => setAsk(null)}>
          <p className="flxs-confirm">{ask.text}</p>
          <div className="flxs-modal-actions">
            <button type="button" className="flxs-btn" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxs-btn is-primary"
              onClick={() => {
                run.mutate({ input: { op: ask.op } });
                setAsk(null);
              }}
            >
              {ask.label}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

/* ------------------------------------------------------------ Settings */

type SettingsData = FlxSpeedSettingsFieldsFragment;
interface SDraft {
  panel: boolean;
  panelFront: boolean;
  caps: boolean;
  record: boolean;
  recordAdmins: boolean;
  sample: string;
  slowMs: string;
  slowQueryMs: string;
  keepDays: string;
  maxRows: string;
  diskQuotaGb: string;
  loginSlug: string;
  loginBlocked: 'NOT_FOUND' | 'HOME';
}
const sDraft = (s: SettingsData): SDraft => ({
  panel: s.panel,
  panelFront: s.panelFront,
  caps: s.caps,
  record: s.record,
  recordAdmins: s.recordAdmins,
  sample: String(s.sample),
  slowMs: String(s.slowMs),
  slowQueryMs: String(s.slowQueryMs),
  keepDays: String(s.keepDays),
  maxRows: String(s.maxRows),
  diskQuotaGb: s.diskQuotaGb ? String(s.diskQuotaGb) : '',
  loginSlug: s.loginSlug,
  loginBlocked: s.loginBlocked,
});

export function SettingsTab() {
  const { t } = useT();
  const { notify } = useSpeed();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxSpeedSettings, {});
  const [d, setD] = useState<SDraft | null>(null);
  const [moved, setMoved] = useState<{ url: string; left: number; stopped: boolean } | null>(null);
  const [copied, setCopied] = useState(false);
  const [fieldError, setFieldError] = useState('');
  useEffect(() => {
    if (q.data) setD(sDraft(q.data.flxSpeedSettings));
  }, [q.data]);
  const save = useFlxMutation(FlxSpeedSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxSpeedSettingsSave;
      if (p?.errors.length) {
        setFieldError(p.errors.map((e) => e.message).join(' '));
        return notify('error', p.errors.map((e) => e.message).join(' '));
      }
      setFieldError('');
      if (p?.settings) qc.setQueryData(flxQueryKey(scope, FlxSpeedSettings, {}), { flxSpeedSettings: p.settings });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSpeedConfig) });
      if (p?.loginChanged && p.loginRemoved) notify('success', t('Custom login URL turned off; login is back on wp-login.php.'));
      else if (p?.loginChanged && p.settings?.loginMovedUrl) setMoved({ url: p.settings.loginMovedUrl, left: 5, stopped: false });
      else notify('success', t('Settings saved.'));
    },
    onError: (e) => notify('error', errorText(e, t('The action could not be completed.'))),
  });
  useEffect(() => {
    if (!moved || moved.stopped) return undefined;
    if (moved.left <= 0) {
      window.location.href = moved.url;
      return undefined;
    }
    const id = window.setTimeout(() => setMoved((m) => m && { ...m, left: m.left - 1 }), 1000);
    return () => window.clearTimeout(id);
  }, [moved]);
  if (q.isLoading || (!d && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !d) return <ErrorBox error={q.error} fallback={t('The action could not be completed.')} />;
  const s = q.data.flxSpeedSettings;
  const int = (v: string, fb: number) => (Number.isFinite(Number.parseInt(v, 10)) ? Number.parseInt(v, 10) : fb);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const quota = Number.parseFloat(d.diskQuotaGb.replace(',', '.'));
    save.mutate({
      input: {
        panel: d.panel,
        panelFront: d.panelFront,
        caps: d.caps,
        record: d.record,
        recordAdmins: d.recordAdmins,
        sample: int(d.sample, 2),
        slowMs: int(d.slowMs, 1500),
        slowQueryMs: int(d.slowQueryMs, 100),
        keepDays: int(d.keepDays, 7),
        maxRows: int(d.maxRows, 5000),
        diskQuotaGb: Number.isFinite(quota) ? quota : 0,
        loginBlocked: d.loginBlocked,
        ...(s.loginSlugLocked ? {} : { loginSlug: d.loginSlug }),
      },
    });
  };
  const num2 = (k: keyof SDraft, props: { min?: number; max?: number; step?: number; id: string }) => (
    <input {...props} type="number" className="flxs-small" value={String(d[k])} onChange={(e) => setD({ ...d, [k]: e.currentTarget.value })} />
  );
  const copy = () => {
    void navigator.clipboard?.writeText(s.loginUrl).then(() => {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    });
  };
  return (
    <>
      {moved && (
        <div className="flxs-notice is-warn">
          <div>
            <p>
              <strong>{t('Login URL changed.')}</strong> {t('New address:')} <code>{s.loginUrl}</code>
            </p>
            {!moved.stopped ? (
              <p>{t('You are being logged out so you can see the new address work; you will be redirected to the new login page in %s seconds. After logging in you come back to this screen.', String(moved.left))}</p>
            ) : (
              <p>{t('Redirect stopped. You are still logged in; the new address is used the next time you log in.')}</p>
            )}
            <p>
              <a className="flxs-btn is-primary" href={moved.url}>
                {t('Go now')}
              </a>{' '}
              {!moved.stopped && (
                <button type="button" className="flxs-btn" onClick={() => setMoved({ ...moved, stopped: true })}>
                  {t('Stay logged in')}
                </button>
              )}
            </p>
          </div>
        </div>
      )}
      <form onSubmit={submit} className="flxs-settings">
        <table className="flxs-form">
          <tbody>
            <tr>
              <th>{t('Developer panel')}</th>
              <td>
                <Check checked={d.panel} onChange={(v) => setD({ ...d, panel: v })}>
                  {t('Show a summary in the admin bar and a bottom panel to authorized users')}
                </Check>
                <br />
                <Check checked={d.panelFront} onChange={(v) => setD({ ...d, panelFront: v })}>
                  {t('Show on the front end too')}
                </Check>
                <br />
                <Check checked={d.caps} onChange={(v) => setD({ ...d, caps: v })}>
                  {t('Collect capability checks')}
                </Check>
                <p className="flxs-desc">{t('The panel is visible to administrators and to staff granted "View performance" by Flux Roles.')}</p>
              </td>
            </tr>
            <tr>
              <th>{t('Recording')}</th>
              <td>
                <Check checked={d.record} onChange={(v) => setD({ ...d, record: v })}>
                  {t('Record slow and failing requests')}
                </Check>
                <br />
                <Check checked={d.recordAdmins} onChange={(v) => setD({ ...d, recordAdmins: v })}>
                  {t('Also record my own requests while the panel is on (only when something is wrong)')}
                </Check>
              </td>
            </tr>
            <tr>
              <th>
                <label htmlFor="flxs-sample">{t('Sampling')}</label>
              </th>
              <td>
                {num2('sample', { id: 'flxs-sample', min: 0, max: 100 })} %<p className="flxs-desc">{t('This share of requests is collected in detail (with the query list) and recorded. 1-5 is recommended on Hostinger; 0 turns it off.')}</p>
              </td>
            </tr>
            <tr>
              <th>
                <label htmlFor="flxs-slow">{t('Slow request threshold')}</label>
              </th>
              <td>{num2('slowMs', { id: 'flxs-slow', min: 100, step: 100 })} ms</td>
            </tr>
            <tr>
              <th>
                <label htmlFor="flxs-slowq">{t('Slow query threshold')}</label>
              </th>
              <td>{num2('slowQueryMs', { id: 'flxs-slowq', min: 1 })} ms</td>
            </tr>
            <tr>
              <th>
                <label htmlFor="flxs-keep">{t('Retention')}</label>
              </th>
              <td>
                {num2('keepDays', { id: 'flxs-keep', min: 1, max: 90 })} {t('days')}, {t('at most')} {num2('maxRows', { id: 'flxs-rows', min: 100, max: 50000, step: 100 })} {t('records')}
              </td>
            </tr>
            <tr>
              <th>
                <label htmlFor="flxs-quota">{t('Disk quota')}</label>
              </th>
              <td>
                {num2('diskQuotaGb', { id: 'flxs-quota', min: 0, step: 0.1 })} GB
                <p className="flxs-desc">{t("Your shared hosting plan's disk limit (Hostinger: hPanel › Account resources). PHP cannot read it; when set, the Disk card shows account usage as a percentage. Leave empty to show used space only.")}</p>
              </td>
            </tr>
          </tbody>
        </table>

        <h2 id="flxs-login">{t('Admin login URL')}</h2>
        <p>{t('Opens the login screen at an address of your own instead of wp-login.php. wp-login.php and wp-admin requests from logged-out visitors no longer respond, which cuts bot password guessing and server load.')}</p>
        {s.loginDisabled ? (
          <div className="flxs-notice is-warn inline">{t('FLXP_LOGIN_DISABLE is defined in wp-config.php: the custom URL is off and login uses wp-login.php. Remove that line once the problem is solved.')}</div>
        ) : (
          s.loginActive && (
            <>
              <div className="flxs-login-now">
                <span>{t('Your login URL')}</span> <code>{s.loginUrl}</code>{' '}
                <button type="button" className="flxs-btn is-small" onClick={copy}>
                  {copied ? t('Copied') : t('Copy')}
                </button>
              </div>
              <p className="flxs-desc">{t('Bookmark this address and share it with everyone who logs in. If you forget it, use the recovery step below.')}</p>
            </>
          )
        )}
        <table className="flxs-form">
          <tbody>
            <tr>
              <th>
                <label htmlFor="flxs-login-slug">{t('Login URL')}</label>
              </th>
              <td>
                <div className="flxs-slug">
                  <span className="flxs-slug-pre">{s.loginPrefix}</span>
                  <input
                    id="flxs-login-slug"
                    type="text"
                    value={d.loginSlug}
                    maxLength={40}
                    placeholder="fluxadmin"
                    autoComplete="off"
                    spellCheck={false}
                    disabled={s.loginSlugLocked}
                    onChange={(e) => setD({ ...d, loginSlug: e.currentTarget.value })}
                  />
                </div>
                {fieldError && <p className="flxs-errtext">{fieldError}</p>}
                <p className="flxs-desc">
                  {s.loginSlugLocked
                    ? t('The address comes from the FLXP_LOGIN_SLUG constant in wp-config.php; edit or remove the constant to change it.')
                    : t('Leave empty to use the standard WordPress address (wp-login.php). 3-40 characters: lowercase letters, digits, hyphen, underscore. Pick something hard to guess (admin and login are not allowed).')}
                </p>
              </td>
            </tr>
            <tr>
              <th>{t('Visitors to the old addresses')}</th>
              <td>
                <label>
                  <input type="radio" name="flxs-blocked" checked={d.loginBlocked === 'NOT_FOUND'} onChange={() => setD({ ...d, loginBlocked: 'NOT_FOUND' })} /> {t('Show the 404 page (recommended)')}
                </label>
                <br />
                <label>
                  <input type="radio" name="flxs-blocked" checked={d.loginBlocked === 'HOME'} onChange={() => setD({ ...d, loginBlocked: 'HOME' })} /> {t('Redirect to the home page')}
                </label>
                <p className="flxs-desc">{t('Applies to wp-login.php and to wp-admin requests from logged-out visitors. admin-ajax.php, admin-post.php, the REST API, wp-cron, WooCommerce My Account login and the Flux App connection are not affected.')}</p>
              </td>
            </tr>
            <tr>
              <th>{t('Recovery')}</th>
              <td>
                <p className="flxs-desc">{t("If you forget the address or login won't open: add the line below to wp-config.php using your file manager (Hostinger hPanel › File Manager). Login goes back to wp-login.php; once you're in, fix the address here and remove the line.")}</p>
                <code className="flxs-codeline">define( &apos;FLXP_LOGIN_DISABLE&apos;, true );</code>
              </td>
            </tr>
          </tbody>
        </table>

        <h2>{t('AI (MCP)')}</h2>
        <p>{t('The data is read by read-only tools on the Flux AI & MCP server and in Flux AI chat: flxp_summary, flxp_requests, flxp_request, flxp_slow_queries, flxp_php_errors, flxp_http_calls, flxp_environment, flxp_server, flxp_database, flxp_autoload, flxp_cron, flxp_profile_url.')}</p>
        <p>{s.mcpActive ? t('Flux AI & MCP is active: tools are registered.') : t('Flux AI & MCP is not active: tools are available in Flux AI chat only.')}</p>
        <button type="submit" className="flxs-btn is-primary" disabled={save.isPending}>
          {t('Save Changes')}
        </button>
      </form>
    </>
  );
}
