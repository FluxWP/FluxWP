// Settings › Admin theme › Menu and bottom bar: each role's own side menu and mobile bottom bar.
// Rows reorder by drag (pointer, also touch) or the up/down buttons; everything saves through GraphQL.
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, Eye, EyeOff, GripVertical, Lock, X } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAdminMenuLayoutsSave, FlxAdminMenuScreen } from '@fluxwp/graphql/operations';
import type { FlxAdminMenuLayoutInput, FlxAdminMenuScreenQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import type { Translate } from './i18n';
import { MenuSvg } from './MenuIcons';

type Data = FlxAdminMenuScreenQuery['flxAdminMenuEditor'];
type Entry = Data['layouts'][number]['entries'][number];
interface Cfg {
  entries: Entry[];
  bottom: string[];
  custom: boolean;
}
export interface PanelProps {
  t: Translate;
  active: boolean;
  onDirty: (dirty: boolean) => void;
  onNotice: (n: { kind: 'success' | 'error'; text: string }) => void;
  /** Opens the Permissions tab. */
  onOpenCaps: () => void;
}

const newGroup = (label: string): Entry => ({ type: 'GROUP', label, collapsed: false, slug: null, hidden: false, blocked: false, subHidden: [], platformsOff: [] });
const cfgOf = (d: Data): Record<string, Cfg> => Object.fromEntries(d.layouts.map((l) => [l.role, { entries: l.entries.map((e) => ({ ...e })), bottom: [...l.bottom], custom: l.custom }]));
const toInput = (e: Entry) =>
  e.type === 'GROUP'
    ? { type: e.type, label: e.label, collapsed: e.collapsed }
    : { type: e.type, slug: e.slug ?? '', label: e.label, hidden: e.hidden, blocked: e.blocked, subHidden: e.subHidden, platformsOff: e.platformsOff };

export function MenuEditor(p: PanelProps) {
  const q = useFlxQuery(FlxAdminMenuScreen, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxad-notice is-error">{q.error?.message || p.t('The action could not be completed.')}</div>;
  return <Editor {...p} data={q.data.flxAdminMenuEditor} />;
}

function Editor({ t, data, onDirty, onNotice, onOpenCaps }: PanelProps & { data: Data }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const cat = useMemo(() => new Map(data.catalog.map((c) => [c.slug, c])), [data.catalog]);
  const roleName = useMemo(() => new Map(data.roles.map((r) => [r.key, r.label])), [data.roles]);
  const [role, setRole] = useState(() => data.roles.find((r) => r.key !== '_default')?.key ?? '_default');
  const [cfg, setCfg] = useState(() => cfgOf(data));
  const [dirty, setDirty] = useState<ReadonlySet<string>>(new Set());
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [drag, setDrag] = useState<{ from: number; over: number } | null>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const dragRef = useRef<{ from: number; over: number } | null>(null);

  useEffect(() => {
    if (dirty.size === 0) setCfg(cfgOf(data));
  }, [data, dirty]);
  useEffect(() => onDirty(dirty.size > 0), [dirty, onDirty]);

  const cur = cfg[role] ?? { entries: [], bottom: [], custom: false };
  const access = useMemo(() => new Set(data.layouts.find((l) => l.role === role)?.access ?? []), [data.layouts, role]);
  const can = (slug: string) => role === '_default' || access.has(slug);
  const canBlock = (slug: string) => role !== 'administrator' && !data.unblockable.includes(slug);
  const platforms = data.platforms;

  const change = (fn: (c: Cfg) => Cfg, keepCustom = false) => {
    setCfg((old) => {
      const c = old[role];
      return c ? { ...old, [role]: { ...fn(c), custom: keepCustom ? c.custom : true } } : old;
    });
    setDirty((d) => new Set(d).add(role));
  };
  const patch = (i: number, part: Partial<Entry>) => change((c) => ({ ...c, entries: c.entries.map((e, j) => (j === i ? { ...e, ...part } : e)) }));
  const move = (from: number, to: number) =>
    change((c) => {
      const list = [...c.entries];
      const [it] = list.splice(from, 1);
      if (it) list.splice(to, 0, it);
      return { ...c, entries: list };
    });

  // Drag by the handle: the row under the pointer is the drop target.
  const rowAt = (x: number, y: number) => {
    const row = document.elementFromPoint(x, y)?.closest<HTMLElement>('[data-i]');
    return row && row.parentElement === listRef.current ? Number(row.dataset.i) : -1;
  };
  const startDrag = (e: React.PointerEvent, i: number) => {
    e.preventDefault();
    dragRef.current = { from: i, over: i };
    setDrag({ from: i, over: i });
    const onMove = (ev: PointerEvent) => {
      const d = dragRef.current;
      const at = rowAt(ev.clientX, ev.clientY);
      if (!d || at < 0 || at === d.over) return;
      dragRef.current = { ...d, over: at };
      setDrag(dragRef.current);
    };
    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
      const d = dragRef.current;
      dragRef.current = null;
      setDrag(null);
      if (d && d.from !== d.over) move(d.from, d.over);
    };
    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onUp);
  };

  const save = useFlxMutation(FlxAdminMenuLayoutsSave, {
    onSuccess: (r) => {
      const res = r.flxAdminMenuLayoutsSave;
      if (!res) return onNotice({ kind: 'error', text: t('The action could not be completed.') });
      if (res.errors.length) return onNotice({ kind: 'error', text: res.errors.map((e) => e.message).join(' ') });
      qc.setQueryData<FlxAdminMenuScreenQuery>(flxQueryKey(scope, FlxAdminMenuScreen, {}), (old) => old && { flxAdminMenuEditor: { ...old.flxAdminMenuEditor, layouts: res.layouts } });
      setDirty(new Set());
      onNotice({ kind: 'success', text: t('Saved.') });
    },
    onError: (e) => onNotice({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const submit = () => {
    const layouts: FlxAdminMenuLayoutInput[] = [];
    const reset: string[] = [];
    for (const r of dirty) {
      const c = cfg[r];
      if (!c) continue;
      if (c.custom) layouts.push({ role: r, entries: c.entries.map(toInput), bottom: c.bottom.filter(Boolean) });
      else reset.push(r);
    }
    save.mutate({ input: { layouts, reset } });
  };

  // Preview: what this role sees in the web menu.
  const preview: { g: boolean; text: string }[] = [];
  let pending: string | null = null;
  for (const e of cur.entries) {
    if (e.type === 'GROUP') {
      pending = e.label;
      continue;
    }
    const it = cat.get(e.slug ?? '');
    if (!it || e.hidden || e.blocked || !can(it.slug) || e.platformsOff.includes('web')) continue;
    if (pending !== null) preview.push({ g: true, text: pending });
    pending = null;
    preview.push({ g: false, text: e.label || it.label });
  }

  return (
    <div className="flxad-me">
      <div className="flxad-me-roles" role="tablist">
        {data.roles.map((r) => (
          <button key={r.key} type="button" role="tab" className="flxad-me-role" aria-selected={r.key === role} onClick={() => setRole(r.key)}>
            {cfg[r.key]?.custom && <i aria-hidden="true" />}
            {r.label}
            {r.userCount > 0 && <small> · {r.userCount} {t('users')}</small>}
          </button>
        ))}
      </div>

      <section className="flxad-me-main">
        <div className="flxad-me-bar">
          <span className="flxad-me-state">{cur.custom ? t('Custom layout') : t('Default layout')}</span>
          {dirty.size > 0 && <span className="flxad-me-dirty">{t('You have unsaved changes.')}</span>}
          <button type="button" className="flxad-me-btn" onClick={() => change((c) => ({ ...c, entries: [newGroup(t('New group')), ...c.entries] }))}>
            {t('+ Add group')}
          </button>
          <select
            aria-label={t('Copy from another role…')}
            value=""
            onChange={(e) => {
              const from = cfg[e.currentTarget.value];
              if (from) change((c) => ({ ...c, entries: from.entries.map((x) => ({ ...x })), bottom: [...from.bottom] }));
            }}
          >
            <option value="">{t('Copy from another role…')}</option>
            {data.roles.filter((r) => r.key !== role).map((r) => (
              <option key={r.key} value={r.key}>
                {r.label}
              </option>
            ))}
          </select>
          {cur.custom && (
            <button
              type="button"
              className="flxad-me-btn"
              onClick={() => {
                setCfg((o) => ({ ...o, [role]: { entries: data.defaults.entries.map((e) => ({ ...e })), bottom: [...data.defaults.bottom], custom: false } }));
                setDirty((d) => new Set(d).add(role));
              }}
            >
              {t('Reset this role to default')}
            </button>
          )}
        </div>

        <ul className="flxad-me-list" ref={listRef}>
          {cur.entries.map((e, i) => {
            const cls = (drag?.from === i ? ' is-drag' : '') + (drag && drag.over === i && drag.from !== i ? ' is-over' : '');
            const handle = (
              <span className="flxad-me-handle" aria-hidden="true" onPointerDown={(ev) => startDrag(ev, i)}>
                <GripVertical size={16} />
              </span>
            );
            const moves = (
              <span className="flxad-me-move">
                <button type="button" className="flxad-me-btn" aria-label={t('Move up')} disabled={i === 0} onClick={() => move(i, i - 1)}>
                  ↑
                </button>
                <button type="button" className="flxad-me-btn" aria-label={t('Move down')} disabled={i === cur.entries.length - 1} onClick={() => move(i, i + 1)}>
                  ↓
                </button>
              </span>
            );
            if (e.type === 'GROUP') {
              return (
                <li key={'g' + i} className={'flxad-me-row is-group' + cls} data-i={i}>
                  {handle}
                  <span className="flxad-me-name">
                    <input type="text" value={e.label} maxLength={40} aria-label={t('Group name')} onChange={(ev) => patch(i, { label: ev.currentTarget.value })} />
                  </span>
                  <label className="flxad-me-chk" title={t('Start collapsed')}>
                    <input type="checkbox" checked={e.collapsed} onChange={(ev) => patch(i, { collapsed: ev.currentTarget.checked })} /> {t('Start collapsed')}
                  </label>
                  {moves}
                  <button type="button" className="flxad-me-btn" aria-label={t('Remove group')} title={t('Remove group')} onClick={() => change((c) => ({ ...c, entries: c.entries.filter((_, j) => j !== i) }))}>
                    <X size={14} />
                  </button>
                </li>
              );
            }
            const it = cat.get(e.slug ?? '');
            if (!it) return null;
            const subs = it.submenu.filter((s) => s.slug !== it.slug || it.submenu.length > 1);
            const allowed = can(it.slug);
            return (
              <Fragment key={it.slug}>
                <li className={'flxad-me-row is-item' + (e.hidden ? ' is-hidden' : '') + (e.blocked ? ' is-blocked' : '') + (allowed ? '' : ' is-noaccess') + cls} data-i={i}>
                  {handle}
                  <span className="flxad-me-ico">
                    <MenuSvg markup={it.iconSvg} />
                  </span>
                  <span className="flxad-me-name">
                    <input type="text" value={e.label} maxLength={40} placeholder={it.label} aria-label={t('Menu name (empty = original name)')} title={t('Menu name (empty = original name)')} onChange={(ev) => patch(i, { label: ev.currentTarget.value })} />
                  </span>
                  {e.label && <span className="flxad-me-src">{it.label}</span>}
                  {!allowed && <span className="flxad-me-tag">{t('This role has no access')}</span>}
                  {e.blocked && (
                    <span className="flxad-me-tag is-block" title={t('Access blocked: not in the menu, opening by URL shows "no permission"')}>
                      {t('Block access')}
                    </span>
                  )}
                  {moves}
                  {subs.length > 1 && (
                    <button type="button" className={'flxad-me-btn' + (open[it.slug] ? ' is-on' : '')} aria-expanded={!!open[it.slug]} onClick={() => setOpen((o) => ({ ...o, [it.slug]: !o[it.slug] }))}>
                      {t('Submenus')} ({subs.length}) <ChevronDown size={14} />
                    </button>
                  )}
                  <button type="button" className={'flxad-me-btn' + (e.hidden ? '' : ' is-on')} aria-pressed={!e.hidden} onClick={() => patch(i, { hidden: !e.hidden })}>
                    {e.hidden ? <EyeOff size={16} /> : <Eye size={16} />}
                    <span>{e.hidden ? t('Hidden') : t('Visible')}</span>
                  </button>
                  {platforms.length > 0 && !e.hidden && !e.blocked && (
                    <span className="flxad-me-pf" role="group" aria-label={t('Where to show')} title={t('Where to show')}>
                      {platforms.map((pf) => {
                        const on = !e.platformsOff.includes(pf.key);
                        return (
                          <button
                            key={pf.key}
                            type="button"
                            className={'flxad-me-btn' + (on ? ' is-on' : '')}
                            aria-pressed={on}
                            title={on ? pf.label : t('%s: hidden', pf.label)}
                            onClick={() => patch(i, { platformsOff: on ? [...e.platformsOff, pf.key] : e.platformsOff.filter((x) => x !== pf.key) })}
                          >
                            {pf.label}
                          </button>
                        );
                      })}
                    </span>
                  )}
                  {allowed && canBlock(it.slug) && (
                    <button type="button" className={'flxad-me-btn' + (e.blocked ? ' is-block' : '')} aria-pressed={e.blocked} aria-label={t('Block access')} title={e.blocked ? t('Access blocked: not in the menu, opening by URL shows "no permission"') : t('Block access')} onClick={() => patch(i, { blocked: !e.blocked })}>
                      <Lock size={16} />
                    </button>
                  )}
                </li>
                {open[it.slug] && subs.length > 1 && (
                  <li className="flxad-me-subs">
                    {subs.map((s) => (
                      <label key={s.slug}>
                        <input
                          type="checkbox"
                          checked={!e.subHidden.includes(s.slug)}
                          onChange={(ev) => patch(i, { subHidden: ev.currentTarget.checked ? e.subHidden.filter((x) => x !== s.slug) : [...e.subHidden, s.slug] })}
                        />{' '}
                        {s.label}
                      </label>
                    ))}
                  </li>
                )}
              </Fragment>
            );
          })}
        </ul>
        <p className="flxad-me-note">{t('Menus of newly installed plugins automatically land in the "Other" group.')}</p>
        {role === 'administrator' && <p className="flxad-me-note">{t("Access can't be blocked for the Administrator role.")}</p>}
        <p className="flxad-me-note">
          <a
            href="#caps"
            onClick={(e) => {
              e.preventDefault();
              onOpenCaps();
            }}
          >
            {t('All permissions: Permissions tab')}
          </a>
        </p>
      </section>

      <aside className="flxad-me-side">
        <div className="flxad-me-box">
          <h3>
            {t('Preview')} · {roleName.get(role)}
          </h3>
          <ul className="flxad-me-prev">
            {preview.map((x, i) => (
              <li key={i} className={x.g ? 'is-g' : undefined}>
                {x.text}
              </li>
            ))}
          </ul>
        </div>
        <div className="flxad-me-box flxad-me-bottom">
          <h3>{t('Mobile bottom bar')}</h3>
          <p className="flxad-me-note">{t('Up to 4 shortcuts; the fifth button "More" opens the side panel.')}</p>
          {[0, 1, 2, 3].map((k) => (
            <select
              key={k}
              aria-label={t('Mobile bottom bar') + ' ' + (k + 1)}
              value={cur.bottom[k] ?? ''}
              onChange={(ev) => {
                const v = ev.currentTarget.value;
                change((c) => {
                  const next = [...c.bottom];
                  next[k] = v;
                  return { ...c, bottom: next.filter((x, j) => x && next.indexOf(x) === j) };
                });
              }}
            >
              <option value="">{t('— empty —')}</option>
              {data.catalog.filter((it) => can(it.slug)).map((it) => (
                <option key={it.slug} value={it.slug}>
                  {it.label}
                </option>
              ))}
            </select>
          ))}
        </div>
      </aside>

      <div className="flxad-actions" style={{ flex: '1 1 100%' }}>
        <button type="button" className="flxad-btn is-primary" disabled={save.isPending || dirty.size === 0} onClick={submit}>
          {t('Save')}
        </button>
        {dirty.size > 0 && <span className="flxad-dirty">{t('You have unsaved changes.')}</span>}
      </div>
    </div>
  );
}
