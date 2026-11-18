// Flux Block Editor › dashboard. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and
// write goes through the Flux Block Editor GraphQL operations (plugins/flx-blocks/graphql/operations).
// Blocks, editor widths, admin bar and the design
// library around the editor: linked libraries, sharing and a pattern browser with live previews.
// The block editor itself is not here; it stays in WordPress.
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxBlocksBlockTypeSetActive,
  FlxBlocksDashboard,
  FlxBlocksLibraryPatterns,
  FlxBlocksLibraryShare,
  FlxBlocksLibraryShareUpdate,
  FlxBlocksLibrarySourceAdd,
  FlxBlocksLibrarySourceRemove,
  FlxBlocksPatternPreview,
  FlxBlocksSettingsUpdate,
} from '@fluxwp/graphql/operations';
import type {
  FlxBlocksBlockTypeRowFragment,
  FlxBlocksDashboardQuery,
  FlxBlocksEditorWidthMode,
  FlxBlocksPatternRowFragment,
  FlxBlocksSettingsFieldsFragment,
  FlxBlocksSourceRowFragment,
} from '@fluxwp/graphql/types';
import { Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './blocks.css';

type Notice = { kind: 'success' | 'error'; text: string };
type Confirm = { text: string; action: string; run: () => void };
type Tab = 'blocks' | 'library' | 'settings';
const MODES: FlxBlocksEditorWidthMode[] = ['SMALL', 'LARGE', 'FULL_WIDTH'];

const errText = (e: unknown, t: Translate) => (e instanceof Error && e.message ? e.message : t('The action could not be completed.'));

export function BlocksScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Dashboard />
    </FlxGraphQLProvider>
  );
}

function Dashboard() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const q = useFlxQuery(FlxBlocksDashboard, {});
  const [tab, setTab] = useState<Tab>(host.startPage === 'library' ? 'library' : 'blocks');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxbk-app"><div className="flxbk-notice is-error">{q.error?.message || t('The action could not be completed.')}</div></div>;
  const d = q.data;
  const showSettings = d.flxBlocksSettings.showAdminBarSetting || d.flxBlocksSettings.editorWidth !== null;
  const tabs: [Tab, string][] = [
    ['blocks', t('Blocks')],
    ['library', t('Design library')],
    ...(showSettings ? ([['settings', t('Settings')]] as [Tab, string][]) : []),
  ];

  return (
    <Screen
      className="flxbk-app"
      icon={host.appIcon}
      title={t('Flux Block Editor')}
      actions={
        <a className="flxbk-btn" href={d.flxBlocksSettings.styleLibraryUrl} onClick={(e) => { e.preventDefault(); host.openUrl(d.flxBlocksSettings.styleLibraryUrl); }}>
          {t('Style Library')}
        </a>
      }
      tabs={tabs.map(([id, label]) => ({ id, label }))}
      tab={tab}
      onTab={setTab}
    >
      {notice && (
        <div className={'flxbk-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
          <span>{notice.text}</span>
          <button type="button" className="flxbk-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      {tab === 'blocks' && <Blocks types={d.flxBlocksBlockTypes} t={t} onNotice={setNotice} />}
      {tab === 'library' && <Library data={d} t={t} onNotice={setNotice} onConfirm={setConfirm} />}
      {tab === 'settings' && <Settings settings={d.flxBlocksSettings} t={t} onNotice={setNotice} />}
      {confirm && (
        <Modal title={confirm.action} onClose={() => setConfirm(null)}>
          <p className="flxbk-confirm">{confirm.text}</p>
          <div className="flxbk-modal-actions">
            <button type="button" className="flxbk-btn" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxbk-btn is-danger"
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
    </Screen>
  );
}

function Blocks({ types, t, onNotice }: { types: FlxBlocksBlockTypeRowFragment[]; t: Translate; onNotice: (n: Notice) => void }) {
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const set = useFlxMutation(FlxBlocksBlockTypeSetActive, {
    onSuccess: (r) => {
      const p = r.flxBlocksBlockTypeSetActive;
      if (!p || p.errors.length || !p.blockType) return onNotice({ kind: 'error', text: p?.errors[0]?.message ?? t('The action could not be completed.') });
      const bt = p.blockType;
      qc.setQueryData<FlxBlocksDashboardQuery>(flxQueryKey(scope, FlxBlocksDashboard, {}), (old) =>
        old && { ...old, flxBlocksBlockTypes: old.flxBlocksBlockTypes.map((o) => (o.slug === bt.slug ? bt : o)) },
      );
      onNotice({ kind: 'success', text: bt.active ? t('Activated') : t('Deactivated') });
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
  });
  return (
    <div className="flxbk-grid">
      {types.map((b) => (
        <div key={b.slug} className={'flxbk-card flxbk-item' + (b.active ? '' : ' is-off')}>
          <h3>{b.name}</h3>
          <p>{b.description}</p>
          <div className="flxbk-item-foot">
            {b.active ? (
              <span className="flxbk-badge is-on">{t('Active')}</span>
            ) : (
              <button type="button" className="flxbk-btn is-primary" disabled={set.isPending} onClick={() => set.mutate({ input: { slug: b.slug, active: true } })}>
                {set.isPending && set.variables?.input.slug === b.slug ? t('Activating…') : t('Activate')}
              </button>
            )}
            {b.link && b.linkText && (
              <a className="flxbk-btn" href={b.link} onClick={(e) => { e.preventDefault(); host.openUrl(b.link ?? ''); }}>
                {b.linkText}
              </a>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

function Settings({ settings, t, onNotice }: { settings: FlxBlocksSettingsFieldsFragment; t: Translate; onNotice: (n: Notice) => void }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const w = settings.editorWidth;
  const [bar, setBar] = useState(settings.showHeadersInAdminBar);
  const [width, setWidth] = useState({ enabled: w?.enabled ?? true, small: String(w?.small ?? 750), large: String(w?.large ?? 1140), postDefault: w?.postDefault ?? 'SMALL', pageDefault: w?.pageDefault ?? 'SMALL' });
  const save = useFlxMutation(FlxBlocksSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxBlocksSettingsUpdate;
      if (!p || p.errors.length || !p.settings) return onNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      const s = p.settings;
      qc.setQueryData<FlxBlocksDashboardQuery>(flxQueryKey(scope, FlxBlocksDashboard, {}), (old) => old && { ...old, flxBlocksSettings: s });
      onNotice({ kind: 'success', text: t('Settings saved.') });
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
  });
  const modeLabel = (m: FlxBlocksEditorWidthMode) => (m === 'SMALL' ? t('Small') : m === 'LARGE' ? t('Large') : t('Fullwidth'));
  const submitWidth = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ input: { editorWidth: { enabled: width.enabled, small: Number.parseInt(width.small, 10) || 0, large: Number.parseInt(width.large, 10) || 0, postDefault: width.postDefault, pageDefault: width.pageDefault } } });
  };
  return (
    <>
      {w && (
        <form className="flxbk-card" onSubmit={submitWidth}>
          <h2>{t('Editor Max Widths')}</h2>
          <p className="flxbk-muted">{t('Match the editor width to your sites width.')}</p>
          {w.contentWidth > 0 && <p className="flxbk-note">{t('Note: The current active themes "content_width" is set to:') + ' ' + String(w.contentWidth) + 'px'}</p>}
          <div className="flxbk-form">
            <Field id="flxbk-small" label={t('Small Template')}>
              <input id="flxbk-small" type="number" min={200} max={4000} value={width.small} onChange={(e) => setWidth({ ...width, small: e.currentTarget.value })} /> <span className="flxbk-muted">{t('px')}</span>
            </Field>
            <Field id="flxbk-large" label={t('Large Template')}>
              <input id="flxbk-large" type="number" min={200} max={4000} value={width.large} onChange={(e) => setWidth({ ...width, large: e.currentTarget.value })} /> <span className="flxbk-muted">{t('px')}</span>
            </Field>
            {(['postDefault', 'pageDefault'] as const).map((k) => (
              <Field key={k} id={'flxbk-' + k} label={k === 'postDefault' ? t('Post default') : t('Page Default')}>
                <select id={'flxbk-' + k} value={width[k]} onChange={(e) => setWidth({ ...width, [k]: e.currentTarget.value as FlxBlocksEditorWidthMode })}>
                  {MODES.map((m) => (
                    <option key={m} value={m}>
                      {modeLabel(m)}
                    </option>
                  ))}
                </select>
              </Field>
            ))}
            <Field id="flxbk-width-on" label={t('Enable Editor Width')}>
              <Switch id="flxbk-width-on" checked={width.enabled} label={t('Enable Editor Width')} onChange={(v) => setWidth({ ...width, enabled: v })} />
              <p className="flxbk-desc">{t('Allows for changes to the editor width on per page/post basis with preset defaults.')}</p>
            </Field>
          </div>
          <div className="flxbk-actions">
            <button type="submit" className="flxbk-btn is-primary" disabled={save.isPending}>
              {t('Save Changes')}
            </button>
          </div>
        </form>
      )}
      {settings.showAdminBarSetting && (
        <form
          className="flxbk-card"
          onSubmit={(e) => {
            e.preventDefault();
            save.mutate({ input: { showHeadersInAdminBar: bar } });
          }}
        >
          <h2>{t('Admin Bar')}</h2>
          <div className="flxbk-form">
            <Field id="flxbk-bar" label={t('Show Block Headers in Admin Bar')}>
              <Switch id="flxbk-bar" checked={bar} label={t('Show Block Headers in Admin Bar')} onChange={setBar} />
            </Field>
          </div>
          <div className="flxbk-actions">
            <button type="submit" className="flxbk-btn is-primary" disabled={save.isPending}>
              {t('Save Changes')}
            </button>
          </div>
        </form>
      )}
    </>
  );
}

function Library({ data, t, onNotice, onConfirm }: { data: FlxBlocksDashboardQuery; t: Translate; onNotice: (n: Notice) => void; onConfirm: (c: Confirm) => void }) {
  const admin = data.flxViewer.canManageOptions;
  return (
    <>
      <Patterns sources={data.flxBlocksLibrarySources} t={t} onNotice={onNotice} />
      <Sources sources={data.flxBlocksLibrarySources} admin={admin} t={t} onNotice={onNotice} onConfirm={onConfirm} />
      {admin && <Share t={t} onNotice={onNotice} onConfirm={onConfirm} />}
    </>
  );
}

function Patterns({ sources, t, onNotice }: { sources: FlxBlocksSourceRowFragment[]; t: Translate; onNotice: (n: Notice) => void }) {
  const [source, setSource] = useState('');
  const [category, setCategory] = useState('');
  const [search, setSearch] = useState('');
  const [typed, setTyped] = useState('');
  const [refresh, setRefresh] = useState(false);
  const [more, setMore] = useState<{ nodes: FlxBlocksPatternRowFragment[]; after: string | null; next: boolean } | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const { client } = useGraphQL();
  const [open, setOpen] = useState<FlxBlocksPatternRowFragment | null>(null);
  useEffect(() => {
    const id = setTimeout(() => setSearch(typed.trim()), 300);
    return () => clearTimeout(id);
  }, [typed]);
  useEffect(() => setMore(null), [source, category, search]);
  const vars = { source: source || null, category: category || null, search: search || null, first: 24, refresh: refresh || null };
  const q = useFlxQuery(FlxBlocksLibraryPatterns, vars);
  useEffect(() => {
    if (q.isFetched) setRefresh(false);
  }, [q.isFetched]);
  const lib = q.data?.flxBlocksLibrary;
  const nodes = [...(lib?.patterns.nodes ?? []), ...(more?.nodes ?? [])];
  const hasNext = more ? more.next : (lib?.patterns.pageInfo.hasNextPage ?? false);
  const loadMore = async () => {
    setLoadingMore(true);
    try {
      const r = await client.request(FlxBlocksLibraryPatterns, { ...vars, refresh: null, after: more ? more.after : (lib?.patterns.pageInfo.endCursor ?? null) });
      const page = r.flxBlocksLibrary.patterns;
      setMore({ nodes: [...(more?.nodes ?? []), ...page.nodes], after: page.pageInfo.endCursor, next: page.pageInfo.hasNextPage });
    } catch (e) {
      onNotice({ kind: 'error', text: errText(e, t) });
    } finally {
      setLoadingMore(false);
    }
  };
  return (
    <div className="flxbk-card">
      <h2>{t('Design library')}</h2>
      <p className="flxbk-muted">{t('Patterns of this site (theme, presets, My patterns) and of linked libraries. Insert them from the Design library button in the editor.')}</p>
      <div className="flxbk-filters">
        <label className="flxbk-sr" htmlFor="flxbk-src">
          {t('Library')}
        </label>
        <select id="flxbk-src" value={source} onChange={(e) => { setSource(e.currentTarget.value); setCategory(''); }}>
          <option value="">{t('This site')}</option>
          {sources.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
        <label className="flxbk-sr" htmlFor="flxbk-cat">
          {t('Category')}
        </label>
        <select id="flxbk-cat" value={category} onChange={(e) => setCategory(e.currentTarget.value)}>
          <option value="">{t('All categories')}</option>
          {(lib?.categories ?? []).map((c) => (
            <option key={c.slug} value={c.slug}>
              {c.label + ' (' + String(c.count) + ')'}
            </option>
          ))}
        </select>
        <label className="flxbk-sr" htmlFor="flxbk-search">
          {t('Search patterns')}
        </label>
        <input id="flxbk-search" type="search" placeholder={t('Search patterns')} value={typed} onChange={(e) => setTyped(e.currentTarget.value)} />
        {source && (
          <button type="button" className="flxbk-btn" disabled={q.isFetching} onClick={() => setRefresh(true)}>
            {t('Refresh')}
          </button>
        )}
      </div>
      {q.isLoading ? (
        <Loading />
      ) : q.isError ? (
        <div className="flxbk-notice is-error">{errText(q.error, t)}</div>
      ) : nodes.length === 0 ? (
        <p className="flxbk-muted">{t('No patterns found.')}</p>
      ) : (
        <>
          <p className="flxbk-muted flxbk-count">{t('%s patterns', String(lib?.patterns.totalCount ?? nodes.length))}</p>
          <ul className="flxbk-patterns">
            {nodes.map((p) => (
              <li key={p.id}>
                <button type="button" className="flxbk-pattern" onClick={() => setOpen(p)}>
                  <strong>{p.title}</strong>
                  {p.description && <span>{p.description}</span>}
                  <code>{p.id}</code>
                </button>
              </li>
            ))}
          </ul>
          {hasNext && (
            <button type="button" className="flxbk-btn" disabled={loadingMore} onClick={() => void loadMore()}>
              {t('Show more')}
            </button>
          )}
        </>
      )}
      {open && <Preview pattern={open} t={t} onNotice={onNotice} onClose={() => setOpen(null)} />}
    </div>
  );
}

function Preview({ pattern, t, onNotice, onClose }: { pattern: FlxBlocksPatternRowFragment; t: Translate; onNotice: (n: Notice) => void; onClose: () => void }) {
  const q = useFlxQuery(FlxBlocksPatternPreview, { id: pattern.id, source: pattern.source });
  const [copied, setCopied] = useState(false);
  const p = q.data?.flxBlocksPattern;
  // The preview runs in a sandboxed frame with no scripts: the pattern's own CSS and HTML only.
  const doc = p ? '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">' + p.rendered.headHtml + '</head><body>' + p.rendered.html + '</body></html>' : '';
  const copy = () => {
    if (!p) return;
    void navigator.clipboard?.writeText(p.content).then(
      () => {
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
      },
      () => onNotice({ kind: 'error', text: t('The action could not be completed.') }),
    );
  };
  return (
    <Modal title={pattern.title} onClose={onClose} wide>
      {q.isLoading ? (
        <Loading />
      ) : !p ? (
        <div className="flxbk-notice is-error">{q.error ? errText(q.error, t) : t('Pattern not found.')}</div>
      ) : (
        <>
          <iframe className="flxbk-frame" title={pattern.title} sandbox="" srcDoc={doc} />
          <div className="flxbk-modal-actions">
            <button type="button" className="flxbk-btn" onClick={copy}>
              {copied ? t('Copied') : t('Copy block markup')}
            </button>
            <button type="button" className="flxbk-btn is-primary" onClick={onClose}>
              {t('Close')}
            </button>
          </div>
        </>
      )}
    </Modal>
  );
}

function Sources({ sources, admin, t, onNotice, onConfirm }: { sources: FlxBlocksSourceRowFragment[]; admin: boolean; t: Translate; onNotice: (n: Notice) => void; onConfirm: (c: Confirm) => void }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [form, setForm] = useState({ url: '', name: '', key: '' });
  const [fieldError, setFieldError] = useState('');
  const put = (list: FlxBlocksSourceRowFragment[]) =>
    qc.setQueryData<FlxBlocksDashboardQuery>(flxQueryKey(scope, FlxBlocksDashboard, {}), (old) => old && { ...old, flxBlocksLibrarySources: list });
  const add = useFlxMutation(FlxBlocksLibrarySourceAdd, {
    onSuccess: (r) => {
      const p = r.flxBlocksLibrarySourceAdd;
      if (!p) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      if (p.errors.length) return setFieldError(p.errors.map((e) => e.message).join(' '));
      put(p.sources);
      setForm({ url: '', name: '', key: '' });
      setFieldError('');
      onNotice({ kind: 'success', text: t('Library added.') });
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
  });
  const remove = useFlxMutation(FlxBlocksLibrarySourceRemove, {
    onSuccess: (r) => {
      const p = r.flxBlocksLibrarySourceRemove;
      if (!p || p.errors.length) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      put(p.sources);
      onNotice({ kind: 'success', text: t('Library removed.') });
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
  });
  return (
    <div className="flxbk-card">
      <h2>{t('Linked libraries')}</h2>
      <p className="flxbk-muted">{t('Add other Flux sites by address (and key) to use their patterns here.')}</p>
      {sources.length ? (
        <ul className="flxbk-list">
          {sources.map((s) => (
            <li key={s.id}>
              <div>
                <strong>{s.name}</strong>
                <span>{s.url}</span>
                {s.hasKey && <span className="flxbk-badge">{t('Key saved')}</span>}
              </div>
              {admin && (
                <button type="button" className="flxbk-remove" onClick={() => onConfirm({ text: t('Remove this library?'), action: t('Remove'), run: () => remove.mutate({ input: { id: s.id } }) })}>
                  {t('Remove')}
                </button>
              )}
            </li>
          ))}
        </ul>
      ) : (
        <p className="flxbk-muted">{t('No linked libraries yet.')}</p>
      )}
      {admin && (
        <form
          className="flxbk-form"
          onSubmit={(e) => {
            e.preventDefault();
            add.mutate({ input: { url: form.url.trim(), name: form.name.trim() || null, key: form.key.trim() || null } });
          }}
        >
          <Field id="flxbk-url" label={t('Site address')}>
            <input id="flxbk-url" type="url" required placeholder="https://" value={form.url} aria-invalid={fieldError ? true : undefined} onChange={(e) => setForm({ ...form, url: e.currentTarget.value })} />
            {fieldError && <p className="flxbk-field-error">{fieldError}</p>}
          </Field>
          <Field id="flxbk-name" label={t('Name (optional)')}>
            <input id="flxbk-name" type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.currentTarget.value })} />
          </Field>
          <Field id="flxbk-key" label={t('Library key (optional)')}>
            <input id="flxbk-key" type="password" autoComplete="off" value={form.key} onChange={(e) => setForm({ ...form, key: e.currentTarget.value })} />
          </Field>
          <div className="flxbk-actions">
            <button type="submit" className="flxbk-btn is-primary" disabled={add.isPending}>
              {add.isPending ? t('Checking…') : t('Add library')}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

function Share({ t, onNotice, onConfirm }: { t: Translate; onNotice: (n: Notice) => void; onConfirm: (c: Confirm) => void }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxBlocksLibraryShare, {});
  const [copied, setCopied] = useState('');
  const update = useFlxMutation(FlxBlocksLibraryShareUpdate, {
    onSuccess: (r) => {
      const s = r.flxBlocksLibraryShareUpdate?.share;
      if (!s) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      qc.setQueryData(flxQueryKey(scope, FlxBlocksLibraryShare, {}), { flxBlocksLibraryShare: s });
      onNotice({ kind: 'success', text: t('Settings saved.') });
    },
    onError: (e) => onNotice({ kind: 'error', text: errText(e, t) }),
  });
  const s = q.data?.flxBlocksLibraryShare;
  const copy = (v: string, what: string) =>
    void navigator.clipboard?.writeText(v).then(() => {
      setCopied(what);
      setTimeout(() => setCopied(''), 1500);
    });
  return (
    <div className="flxbk-card">
      <h2>{t('Share this library')}</h2>
      <p className="flxbk-muted">{t('Other Flux sites can add this site\'s patterns with the address and key below.')}</p>
      {!s ? (
        <Loading />
      ) : (
        <div className="flxbk-form">
          <Field id="flxbk-share" label={t('Sharing')}>
            <Switch id="flxbk-share" checked={s.enabled} label={t('Share this library')} disabled={update.isPending} onChange={(v) => update.mutate({ input: { enabled: v } })} />
          </Field>
          {s.enabled && (
            <>
              <Field id="flxbk-share-url" label={t('Site address')}>
                <div className="flxbk-copy">
                  <input id="flxbk-share-url" type="text" readOnly value={s.url} onFocus={(e) => e.currentTarget.select()} />
                  <button type="button" className="flxbk-btn" onClick={() => copy(s.url, 'url')}>
                    {copied === 'url' ? t('Copied') : t('Copy')}
                  </button>
                </div>
              </Field>
              <Field id="flxbk-share-key" label={t('Library key')}>
                <div className="flxbk-copy">
                  <input id="flxbk-share-key" type="text" readOnly value={s.key} onFocus={(e) => e.currentTarget.select()} />
                  <button type="button" className="flxbk-btn" onClick={() => copy(s.key, 'key')}>
                    {copied === 'key' ? t('Copied') : t('Copy')}
                  </button>
                  <button
                    type="button"
                    className="flxbk-btn"
                    disabled={update.isPending}
                    onClick={() => onConfirm({ text: t('Make a new key? Sites using the old key lose access.'), action: t('New key'), run: () => update.mutate({ input: { regenerateKey: true } }) })}
                  >
                    {t('New key')}
                  </button>
                </div>
              </Field>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function Field({ id, label, children }: { id: string; label: string; children: ReactNode }) {
  return (
    <div className="flxbk-field">
      <label htmlFor={id}>{label}</label>
      <div>{children}</div>
    </div>
  );
}

function Switch({ id, checked, label, disabled, onChange }: { id: string; checked: boolean; label: string; disabled?: boolean; onChange: (v: boolean) => void }) {
  return (
    <label className="flxbk-switch">
      <input id={id} type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span aria-hidden="true" />
      <span className="flxbk-sr">{label}</span>
    </label>
  );
}
