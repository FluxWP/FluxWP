// Flux Files dialogs: info, permissions, folder picker, preview, version history (with diff), settings.
import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  FlxFilesChmod,
  FlxFilesFile,
  FlxFilesRevert,
  FlxFilesSettingsUpdate,
  FlxFilesShare,
  FlxFilesSize,
  FlxFilesVersionContent,
  FlxFilesVersions,
} from '@fluxwp/graphql/operations';
import { Ic } from './icons';
import type { Translate } from './i18n';
import { Modal, useDialogs } from './ui';
import { copyText, download, errText, FileIcon, loadFolder, ok, rawUrl, useEnv, type Settings } from './data';
import { base, diffLines, join, lower, size, type Item } from './util';

const flagText = (it: Item, t: Translate) => ({
  locked: it.locked ? t('locked_hint') : '',
  sensitive: it.sensitive ? t('sensitive_hint') : '',
  core: it.core ? t('core_hint') : '',
});

/* ------------------------------------------------------------------- diff */
export function DiffView({ a, b, t }: { a: string; b: string; t: Translate }) {
  const rows = useMemo(() => (a === b ? [] : diffLines(a, b)), [a, b]);
  if (a === b) return <p className="flxfm-muted">{t('no_changes')}</p>;
  const add = rows.filter((x) => x[0] === '+').length;
  const del = rows.filter((x) => x[0] === '-').length;
  return (
    <>
      <div className="flxfm-diffstat">
        <span className="is-add">+{add}</span> <span className="is-del">−{del}</span>
      </div>
      <pre className="flxfm-diff">
        {rows.map((x, i) => (
          <span key={i} className={'flxfm-d flxfm-d--' + (x[0] === '+' ? 'add' : x[0] === '-' ? 'del' : x[0] === '@' ? 'gap' : 'eq')}>
            {x[0] === '@' ? '⋯ ' + t('lines_same', x[1]) : x[0] + ' ' + x[1]}
            {'\n'}
          </span>
        ))}
      </pre>
    </>
  );
}

export function ViaBadge({ via, t }: { via: string | null; t: Translate }) {
  const v = via ? lower(via) : 'panel';
  return <span className={'flxfm-via flxfm-via--' + v}>{t('via_' + v)}</span>;
}

/* ------------------------------------------------------------------- info */
export function InfoDialog({ it, onClose, t }: { it: Item; onClose: () => void; t: Translate }) {
  const { client } = useEnv();
  const d = useDialogs();
  const [calc, setCalc] = useState<'idle' | 'busy' | string>('idle');
  const f = flagText(it, t);
  const rows: [string, React.ReactNode][] = [
    [t('col_name'), it.name],
    [t('location'), <code key="l">{it.path || '/'}</code>],
    [t('type'), it.isDir ? t('folder') : (it.extension ? it.extension.toUpperCase() + ' · ' : '') + t('kind_' + lower(it.kind))],
    [
      t('col_size'),
      it.isDir ? (
        calc === 'idle' ? (
          <button type="button" className="flxfm-link" onClick={() => {
            setCalc('busy');
            client.request(FlxFilesSize, { path: it.path }).then((r) => {
              const z = r.flxFilesSize;
              setCalc(size(z.bytes) + ' · ' + t('files_n', z.files) + (z.partial ? ' · ' + t('partial') : ''));
            }).catch((e) => {
              setCalc('idle');
              d.toast(errText(e, t('error')), 'bad');
            });
          }}>
            {t('calculate')}
          </button>
        ) : calc === 'busy' ? (
          t('calculating')
        ) : (
          calc
        )
      ) : (
        size(it.size)
      ),
    ],
    [t('col_modified'), <span key="m">{it.modified.label} <span className="flxfm-muted">· {it.modified.relative}</span></span>],
    [t('col_perms'), <span key="p"><code>{it.permissions}</code>{it.writable ? '' : <> <span className="flxfm-tag">{t('not_writable')}</span></>}</span>],
  ];
  const notes = [f.locked && 'locked', f.sensitive && 'sensitive', f.core && 'core', it.shared && 'shared'].filter(Boolean);
  return (
    <Modal t={t} title={it.isDir ? t('folder_info') : t('file_info')} onClose={onClose}>
      <dl className="flxfm-dl">
        {rows.map(([k, v]) => (
          <div key={k} style={{ display: 'contents' }}>
            <dt>{k}</dt>
            <dd>{v}</dd>
          </div>
        ))}
        {notes.length ? (
          <>
            <dt>{t('notes')}</dt>
            <dd>
              {f.locked ? <span className="flxfm-tag"><Ic n="lock" />{f.locked}</span> : null}{' '}
              {f.sensitive ? <span className="flxfm-tag is-warn"><Ic n="shield" />{f.sensitive}</span> : null}{' '}
              {f.core ? <span className="flxfm-tag">{f.core}</span> : null}{' '}
              {it.shared ? <span className="flxfm-tag is-ok"><Ic n="globe" />{t('shared_title')} · <code>[flx_files folder="{it.path}"]</code></span> : null}
            </dd>
          </>
        ) : null}
      </dl>
    </Modal>
  );
}

/* ------------------------------------------------------------ permissions */
export function PermsDialog({ it, onClose, onDone, t }: { it: Item; onClose: () => void; onDone: () => void; t: Translate }) {
  const { client } = useEnv();
  const d = useDialogs();
  const [mode, setMode] = useState(it.permissions.slice(-3));
  const who = [t('perm_owner'), t('perm_group'), t('perm_public')];
  const what: [number, string][] = [[4, t('perm_read')], [2, t('perm_write')], [1, t('perm_exec')]];
  const digit = (i: number): number => parseInt(mode[i] ?? '0', 10) || 0;
  const flip = (i: number, v: number) => {
    const ds = [0, 1, 2].map(digit);
    ds[i] = (ds[i] ?? 0) ^ v;
    setMode(ds.join(''));
  };
  const save = () => {
    client.request(FlxFilesChmod, { input: { path: it.path, mode } }).then((r) => {
      ok(r.flxFilesChmod);
      d.toast(t('saved'));
      onClose();
      onDone();
    }).catch((e) => d.toast(errText(e, t('error')), 'bad'));
  };
  return (
    <Modal
      t={t}
      title={t('permissions')}
      onClose={onClose}
      foot={<>
        <button type="button" className="flxfm-btn" onClick={onClose}>{t('cancel')}</button>
        <button type="button" className="flxfm-btn flxfm-btn--pri" onClick={save} disabled={!/^[0-7]{3}$/.test(mode)}>{t('save')}</button>
      </>}
    >
      <p className="flxfm-muted">{it.path}</p>
      <table className="flxfm-perm">
        <thead><tr><th />{what.map((w) => <th key={w[0]}>{w[1]}</th>)}</tr></thead>
        <tbody>
          {who.map((w, i) => (
            <tr key={w}>
              <th>{w}</th>
              {what.map((x) => (
                <td key={x[0]}><input type="checkbox" checked={Boolean(digit(i) & x[0])} onChange={() => flip(i, x[0])} /></td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      <label className="flxfm-field">
        <span>{t('perm_numeric')}</span>
        <input type="text" className="flxfm-octal" inputMode="numeric" maxLength={3} value={mode} onChange={(e) => setMode(e.target.value.replace(/[^0-7]/g, ''))} />
      </label>
      <p className="flxfm-muted">{t('perm_hint')}</p>
    </Modal>
  );
}

/* ----------------------------------------------------------- folder picker */
export function PickFolder({ title, start, onPick, onClose, t }: { title: string; start: string; onPick: (dest: string) => void; onClose: () => void; t: Translate }) {
  const { client, cfg, scope } = useEnv();
  const [cur, setCur] = useState(start);
  const q = useQuery({ queryKey: [scope, 'files-pick', cur], queryFn: ({ signal }) => loadFolder(client, cur, { foldersOnly: true }, signal) });
  const parts = cur ? cur.split('/') : [];
  let acc = '';
  return (
    <Modal
      t={t}
      title={title}
      onClose={onClose}
      foot={<>
        <button type="button" className="flxfm-btn" onClick={onClose}>{t('cancel')}</button>
        <button type="button" className="flxfm-btn flxfm-btn--pri" onClick={() => { onClose(); onPick(cur); }}>{t('choose_here')}</button>
      </>}
    >
      <div className="flxfm-pick">
        <div className="flxfm-pick-crumbs">
          <button type="button" onClick={() => setCur('')}>{cfg.rootName}</button>
          {parts.map((x) => {
            acc = join(acc, x);
            const to = acc;
            return (
              <span key={to}>
                <Ic n="right" cls="flxfm-i-sm" />
                <button type="button" onClick={() => setCur(to)}>{x}</button>
              </span>
            );
          })}
        </div>
        <ul className="flxfm-pick-list">
          {q.isPending ? <li className="flxfm-muted">{t('loading')}</li> : null}
          {q.data && !q.data.items.length ? <li className="flxfm-muted">{t('no_subfolders')}</li> : null}
          {q.data?.items.filter((i) => i.isDir).map((i) => (
            <li key={i.path}>
              <button type="button" onClick={() => setCur(i.path)}><Ic n="folder" />{i.name}</button>
            </li>
          ))}
        </ul>
      </div>
    </Modal>
  );
}

/* ---------------------------------------------------------------- preview */
export function Preview({ list, index, onClose, t }: { list: Item[]; index: number; onClose: () => void; t: Translate }) {
  const { cfg } = useEnv();
  const [i, setI] = useState(index);
  const x = list[i];
  useEffect(() => {
    const key = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft') setI((v) => Math.max(0, v - 1));
      if (e.key === 'ArrowRight') setI((v) => Math.min(list.length - 1, v + 1));
    };
    window.addEventListener('keydown', key);
    return () => window.removeEventListener('keydown', key);
  }, [list.length]);
  if (!x) return null;
  const u = rawUrl(cfg, x.path);
  const kind = x.kind;
  return (
    <Modal
      t={t}
      title={x.name}
      full
      onClose={onClose}
      foot={<>
        <span className="flxfm-pv-meta">{size(x.size)} · {x.modified.relative}{list.length > 1 ? ` · ${i + 1}/${list.length}` : ''}</span>
        <button type="button" className="flxfm-btn" disabled={i <= 0} onClick={() => setI(i - 1)} aria-label={t('previous')}><Ic n="left" /></button>
        <button type="button" className="flxfm-btn" disabled={i >= list.length - 1} onClick={() => setI(i + 1)} aria-label={t('next')}><Ic n="right" /></button>
        <button type="button" className="flxfm-btn" onClick={() => download(cfg, [x.path])}><Ic n="download" />{t('download')}</button>
      </>}
    >
      <div className="flxfm-pv">
        {kind === 'IMAGE' ? <img src={u} alt="" /> : kind === 'VIDEO' ? <video src={u} controls autoPlay playsInline /> : kind === 'AUDIO' ? <audio src={u} controls autoPlay /> : <iframe src={u} title={x.name} />}
      </div>
    </Modal>
  );
}

/* ---------------------------------------------------------------- versions */
export function VersionsDialog({ path, current, settings, onClose, onRestored, t }: { path: string; current?: () => string; settings: Settings; onClose: () => void; onRestored: () => void; t: Translate }) {
  const { client, scope } = useEnv();
  const d = useDialogs();
  const [sel, setSel] = useState<string | null>(null);
  const list = useQuery({ queryKey: [scope, 'files-versions', path], queryFn: () => client.request(FlxFilesVersions, { path }).then((r) => r.flxFilesVersions) });
  useEffect(() => {
    if (!sel && list.data?.[0]) setSel(list.data[0].id);
  }, [list.data, sel]);
  const both = useQuery({
    queryKey: [scope, 'files-version', path, sel],
    enabled: !!sel,
    queryFn: async () => {
      const old = await client.request(FlxFilesVersionContent, { path, versionId: sel ?? '' });
      const now = current ? current() : ((await client.request(FlxFilesFile, { path })).flxFilesFile.content ?? '');
      return { old: old.flxFilesVersionContent ?? '', now };
    },
  });
  const revert = async () => {
    if (!sel) return;
    if (!(await d.confirm({ title: t('restore_version'), message: t('restore_version_msg'), ok: t('restore_version') }))) return;
    try {
      ok((await client.request(FlxFilesRevert, { input: { path, versionId: sel } })).flxFilesRevert);
      d.toast(t('restored'));
      onClose();
      onRestored();
    } catch (e) {
      d.toast(errText(e, t('error')), 'bad');
    }
  };
  return (
    <Modal t={t} title={t('versions') + ' · ' + base(path)} wide onClose={onClose}>
      <p className="flxfm-muted">{t('versions_hint', settings.versions)}</p>
      <div className="flxfm-vers">
        <ul className="flxfm-vlist">
          {list.isPending ? <li className="flxfm-muted">{t('loading')}</li> : null}
          {list.data && !list.data.length ? <li className="flxfm-muted">{t('no_versions')}</li> : null}
          {list.data?.map((v) => (
            <li key={v.id}>
              <button type="button" className={sel === v.id ? 'is-on' : undefined} onClick={() => setSel(v.id)}>
                <b title={v.saved.label}>{v.saved.relative}</b>
                <span>{v.user} · <ViaBadge via={v.via} t={t} /> · {size(v.size)}</span>
              </button>
            </li>
          ))}
        </ul>
        <div className="flxfm-vview">
          {sel && both.isPending ? <p className="flxfm-muted">{t('loading')}</p> : null}
          {both.isError ? <p className="flxfm-err">{errText(both.error, t('error'))}</p> : null}
          {both.data ? (
            <>
              <div className="flxfm-vact">
                <span className="flxfm-muted">{t('diff_vs_current')}</span>
                <button type="button" className="flxfm-btn flxfm-btn--pri" onClick={revert}><Ic n="restore" />{t('restore_version')}</button>
              </div>
              <DiffView a={both.data.old} b={both.data.now} t={t} />
            </>
          ) : null}
        </div>
      </div>
    </Modal>
  );
}

/* ----------------------------------------------------------------- settings */
export function SettingsDialog({ onClose, onSaved, t }: { onClose: () => void; onSaved: () => void; t: Translate }) {
  const { client, settings: s, cfg } = useEnv();
  const d = useDialogs();
  const [f, setF] = useState({ showHidden: s.showHidden, mcpWrite: s.mcpWrite, aiCode: s.aiCode, trashDays: String(s.trashDays), versions: String(s.versions), quotaGb: String(s.quotaGb || 0) });
  const [shared, setShared] = useState(s.shared);
  const sw = (k: 'showHidden' | 'mcpWrite' | 'aiCode', label: string, desc: string) => (
    <label className="flxfm-switch">
      <input type="checkbox" checked={f[k]} onChange={(e) => setF({ ...f, [k]: e.target.checked })} />
      <span className="flxfm-sw" />
      <span><b>{label}</b><small>{desc}</small></span>
    </label>
  );
  const num = (k: 'trashDays' | 'versions' | 'quotaGb', label: string, desc: string, max?: number) => (
    <label className="flxfm-field flxfm-field--row">
      <span><b>{label}</b><small>{desc}</small></span>
      <input type="number" min={0} max={max} step={1} value={f[k]} onChange={(e) => setF({ ...f, [k]: e.target.value })} />
    </label>
  );
  const save = async () => {
    const risky = (f.mcpWrite && !s.mcpWrite) || (f.aiCode && !s.aiCode);
    if (risky && !(await d.confirm({ title: t('set_ai'), message: t('set_risky'), ok: t('save'), danger: true }))) return;
    try {
      ok(
        (
          await client.request(FlxFilesSettingsUpdate, {
            input: { settings: { showHidden: f.showHidden, mcpWrite: f.mcpWrite, aiCode: f.aiCode, trashDays: Number(f.trashDays) || 0, versions: Number(f.versions) || 0, quotaGb: Number(f.quotaGb) || 0 } },
          })
        ).flxFilesSettingsUpdate,
      );
      d.toast(t('saved'));
      onClose();
      onSaved();
    } catch (e) {
      d.toast(errText(e, t('error')), 'bad');
    }
  };
  const unshare = async (p: string) => {
    try {
      ok((await client.request(FlxFilesShare, { input: { path: p, on: false } })).flxFilesShare);
      setShared((l) => l.filter((x) => x !== p));
      onSaved();
    } catch (e) {
      d.toast(errText(e, t('error')), 'bad');
    }
  };
  void cfg;
  return (
    <Modal
      t={t}
      title={t('settings')}
      onClose={onClose}
      foot={<>
        <button type="button" className="flxfm-btn" onClick={onClose}>{t('cancel')}</button>
        <button type="button" className="flxfm-btn flxfm-btn--pri" onClick={save}>{t('save')}</button>
      </>}
    >
      <div className="flxfm-settings">
        <h3>{t('set_view')}</h3>
        {sw('showHidden', t('set_hidden'), t('set_hidden_d'))}
        {num('quotaGb', t('set_quota'), t('set_quota_d'))}
        <h3>{t('set_safety')}</h3>
        {num('trashDays', t('set_trash'), t('set_trash_d'), 365)}
        {num('versions', t('set_versions'), t('set_versions_d'), 50)}
        <h3>{t('set_ai')}</h3>
        <p className="flxfm-muted">{t('set_ai_d')}</p>
        {sw('mcpWrite', t('set_mcp'), t('set_mcp_d'))}
        {sw('aiCode', t('set_code'), t('set_code_d'))}
        <h3>{t('set_shared')}</h3>
        <p className="flxfm-muted">{t('set_shared_d')}</p>
        {shared.length ? (
          <ul className="flxfm-shared">
            {shared.map((p) => (
              <li key={p}>
                <code>[flx_files folder="{p}"]</code>
                <button type="button" className="flxfm-ib" aria-label={t('copy')} onClick={() => copyText(`[flx_files folder="${p}"]`, () => d.toast(t('copied')))}><Ic n="copy" /></button>
                <button type="button" className="flxfm-ib is-danger" aria-label={t('unshare')} onClick={() => unshare(p)}><Ic n="x" /></button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="flxfm-muted"><i>{t('set_shared_none')}</i></p>
        )}
      </div>
    </Modal>
  );
}

export { FileIcon };
