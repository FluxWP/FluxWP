// Flux Tasks yapı yönetimi (WP assets/js/pages.js karşılığı): proje / klasör / liste menüleri, yeni proje ve
// liste pencereleri, liste ayarları (genel, durumlar, özel alanlar, yetkiler), listeyi taşıma, sol ağaçta sürükle-bırak.
import { useEffect, useRef, useState } from 'react';
import type { FlxgField, FlxgList, FlxgListMembers, FlxgMember, FlxgMoveCheck, FlxgRole, FlxgSpace, FlxgStatus } from '@fluxwp/core';
import { between, norm, rank, store } from './engine';
import { tt } from './i18n';
import { Avatar, Ic, pickPeople, type Ctx } from './ui';
import { fieldTypeIcon } from './views';

export const COLORS = ['#2e7d32', '#16a34a', '#0d9488', '#0284c7', '#2563eb', '#7c3aed', '#c026d3', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#64748b'];
const cls = (...a: (string | false | null | undefined | 0)[]) => a.filter(Boolean).join(' ');
const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v)) as T;

/* ---------------- Sol ağaç: açık projeler ve klasörler (cihazda saklanır) ---------------- */
type Listener = () => void;
const treeSubs = new Set<Listener>();
function readMap(k: string): Record<string, 1> {
  return store.json<Record<string, 1>>(k) ?? {};
}
export const tree = {
  subscribe(f: Listener) {
    treeSubs.add(f);
    return () => void treeSubs.delete(f);
  },
  get(k: 'sp-open' | 'collapsed') {
    return readMap(k);
  },
  set(k: 'sp-open' | 'collapsed', key: string | number, v: boolean) {
    const m = readMap(k);
    if (v) m[key] = 1;
    else delete m[key];
    store.set(k, JSON.stringify(m));
    treeSubs.forEach((f) => f());
  },
};

export function Skel({ rows = 3 }: { rows?: number }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '6px 0' }}>
      {Array.from({ length: rows }, (_, i) => (
        <span key={i} className="flxg-sk" style={{ display: 'block', width: ['70%', '86%', '60%', '78%'][i % 4], height: 14 }} />
      ))}
    </div>
  );
}

/* ---------------- Ortak parçalar ---------------- */
export function ColorPicker({ value, onPick }: { value: string; onPick: (c: string) => void }) {
  const [cur, setCur] = useState(value);
  const pick = (c: string) => (setCur(c), onPick(c));
  return (
    <div className="flxg-colors">
      {COLORS.map((c) => (
        <button key={c} type="button" className={cls('flxg-color', c === cur && 'is-on')} style={{ background: c }} aria-label={c} onClick={() => pick(c)} />
      ))}
      <label className="flxg-color is-custom" title={tt("Custom color")}>
        <input type="color" value={cur || '#1F2937'} onChange={(ev) => pick(ev.target.value)} />
      </label>
    </div>
  );
}

function RoleSelect({ c, value, onChange, allowNone }: { c: Ctx; value: string; onChange: (v: FlxgRole | '') => void; allowNone?: boolean }) {
  const roles = (c.e.S.config as { roles?: Record<string, string> }).roles ?? {};
  return (
    <select className="flxg-input is-sm" defaultValue={value} onChange={(ev) => onChange(ev.target.value as FlxgRole | '')}>
      {allowNone && <option value="">{tt("No access")}</option>}
      {Object.keys(roles).map((k) => (
        <option key={k} value={k}>
          {roles[k]}
        </option>
      ))}
    </select>
  );
}

function MembersEditor({ c, members, empty, defRole = 'edit' }: { c: Ctx; members: FlxgMember[]; empty?: string; defRole?: FlxgRole }) {
  const [, force] = useState(0);
  const redraw = () => force((n) => n + 1);
  return (
    <div className="flxg-members">
      {members.map((m, i) => {
        const u = c.e.S.users[m.user];
        return (
          <div key={m.user} className="flxg-member">
            <Avatar u={u} size={28} />
            <span className="flxg-grow">{u ? u.name : '#' + m.user}</span>
            <RoleSelect c={c} value={m.role} onChange={(v) => (m.role = v)} />
            <button type="button" className="flxg-icon-btn is-sm" aria-label={tt("Remove")} onClick={() => (members.splice(i, 1), redraw())}>
              <Ic n="x" s={15} />
            </button>
          </div>
        );
      })}
      {!members.length && <div className="flxg-empty-sm">{empty ?? tt("No members added yet.")}</div>}
      <button
        type="button"
        className="flxg-btn is-sm"
        onClick={(ev) =>
          pickPeople(
            c,
            ev.currentTarget,
            members.map((m) => m.user),
            (ids) => {
              for (const id of ids) if (!members.some((m) => m.user === id)) members.push({ user: id, role: defRole });
              for (let j = members.length - 1; j >= 0; j--) if (!ids.includes(members[j]!.user)) members.splice(j, 1);
              redraw();
            },
          )
        }
      >
        <Ic n="plus" s={14} />
        {tt("Add person")}
      </button>
    </div>
  );
}

function Radios<K extends string>({ name, value, opts, onChange }: { name: string; value: K; opts: [K, string, string][]; onChange: (k: K) => void }) {
  return (
    <>
      {opts.map(([k, label, help]) => (
        <label key={k} className={cls('flxg-radio', value === k && 'is-on')}>
          <input type="radio" name={name} checked={value === k} onChange={() => onChange(k)} />
          <span>
            <b>{label}</b>
            <small>{help}</small>
          </span>
        </label>
      ))}
    </>
  );
}

/* ---------------- Proje penceresi ---------------- */
type SpaceDraft = { name: string; color: string; access: string; default_role: string; subtask_depth: number; list?: string };
function SpaceForm({ c, sp, d, st }: { c: Ctx; sp: FlxgSpace | null; d: SpaceDraft; st: { members: FlxgMember[]; loaded: boolean; name: HTMLInputElement | null } }) {
  const [access, setAccess] = useState(d.access);
  const [loaded, setLoaded] = useState(st.loaded);
  useEffect(() => {
    if (!sp) return;
    c.e.api.spaceMembers(sp.id).then((r) => {
      st.members = r.members;
      st.loaded = true;
      setLoaded(true);
    }, c.ui.fail);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return (
    <div className="flxg-form">
      <label className="flxg-field">
        <span>{tt("Name")}</span>
        <input ref={(el) => void (st.name = el)} className="flxg-input" type="text" defaultValue={d.name} placeholder={tt("Project name")} autoFocus onChange={(ev) => (d.name = ev.target.value)} />
      </label>
      <div className="flxg-field">
        <span>{tt("Color")}</span>
        <ColorPicker value={d.color} onPick={(v) => (d.color = v)} />
      </div>
      <div className="flxg-field">
        <span>{tt("Access")}</span>
        <div className="flxg-radios">
          <Radios
            name="flxg-acc"
            value={access}
            onChange={(k) => ((d.access = k), setAccess(k))}
            opts={[
              ['everyone', tt("Everyone"), tt("Everyone who can use Tasks can enter this project.")],
              ['members', tt("Members only"), tt("Only the people added below can see it.")],
            ]}
          />
          {access === 'everyone' && (
            <label className="flxg-field is-inline">
              <span>{tt("Default role for everyone")}</span>
              <RoleSelect c={c} value={d.default_role} onChange={(v) => (d.default_role = v)} />
            </label>
          )}
        </div>
      </div>
      <div className="flxg-field">
        <span>{tt("Members and their roles")}</span>
        <div>{loaded ? <MembersEditor c={c} members={st.members} empty={sp ? undefined : tt("You will be added as a manager.")} /> : <Skel />}</div>
      </div>
      <label className="flxg-field">
        <span>{tt("Subtask nesting limit")}</span>
        <input className="flxg-input is-num" type="number" min={1} max={10} step={1} defaultValue={d.subtask_depth} onChange={(ev) => (d.subtask_depth = Math.max(1, Math.min(10, parseInt(ev.target.value, 10) || 3)))} />
        <small className="flxg-muted">{tt("How many levels of subtasks can be nested under a task (1–10). Default is 3.")}</small>
      </label>
      {!sp && (
        <label className="flxg-field">
          <span>{tt("First list")}</span>
          <input className="flxg-input" type="text" defaultValue={d.list} onChange={(ev) => (d.list = ev.target.value)} />
        </label>
      )}
    </div>
  );
}

export function spaceModal(c: Ctx, sp: FlxgSpace | null = null) {
  const { e, ui } = c;
  const d: SpaceDraft = sp
    ? { name: sp.name, color: sp.color, access: sp.access, default_role: sp.default_role ?? 'edit', subtask_depth: sp.subtask_depth || 3 }
    : { name: '', color: COLORS[0]!, access: 'everyone', default_role: 'edit', subtask_depth: 3, list: tt("Tasks") };
  const st = { members: [] as FlxgMember[], loaded: !sp, name: null as HTMLInputElement | null };
  ui.modal({
    title: sp ? tt("Project settings") : tt("New project"),
    body: <SpaceForm c={c} sp={sp} d={d} st={st} />,
    actions: [
      { label: tt("Cancel") },
      {
        label: sp ? tt("Save") : tt("Create"),
        primary: true,
        onClick: async () => {
          d.name = d.name.trim();
          if (!d.name) {
            st.name?.focus();
            return false;
          }
          if (sp) {
            const r = await e.api.updateSpace(sp.id, { name: d.name, color: d.color, subtask_depth: d.subtask_depth });
            if (r?.spaces) e.applyStructure(r);
            if (st.loaded) e.applyStructure(await e.api.saveSpaceMembers(sp.id, { members: st.members, access: d.access, default_role: d.default_role }));
            ui.toast(tt("Project saved"));
            return true;
          }
          const r = await e.api.createSpace({ ...d });
          e.applyStructure(r);
          if (st.members.length && r.id) e.applyStructure(await e.api.saveSpaceMembers(r.id, { members: st.members }));
          if (r.id) {
            tree.set('sp-open', r.id, true);
            void e.go({ page: 'space', id: r.id });
          }
          return true;
        },
      },
    ],
  });
}

/* ---------------- Yeni liste ---------------- */
type ListDraft = { name: string; color: string; access: string; folder: number; kind: 'task' | 'meeting' };
function ListForm({ c, spaceId, d, st }: { c: Ctx; spaceId: number; d: ListDraft; st: { name: HTMLInputElement | null } }) {
  const [kind, setKind] = useState(d.kind);
  const folders = c.e.S.folders.filter((f) => f.space_id === spaceId);
  return (
    <div className="flxg-form">
      <div className="flxg-field">
        <span>{tt("Template")}</span>
        <div className="flxg-tpl-pick">
          {(
            [
              ['task', 'list', tt("Task list"), tt("Tasks, subtasks, statuses")],
              ['meeting', 'meeting', tt("Meeting list"), tt("Meetings, agenda, decisions, PDF report")],
            ] as const
          ).map(([k, ic, label, help]) => (
            <button key={k} type="button" className={cls('flxg-tpl', kind === k && 'is-on')} onClick={() => ((d.kind = k), setKind(k))}>
              <span className="flxg-tpl-ic">
                <Ic n={ic} s={18} />
              </span>
              <span className="flxg-tpl-txt">
                <b>{label}</b>
                <small>{help}</small>
              </span>
            </button>
          ))}
        </div>
      </div>
      <label className="flxg-field">
        <span>{tt("Name")}</span>
        <input
          ref={(el) => void (st.name = el)}
          className="flxg-input"
          type="text"
          autoFocus
          placeholder={kind === 'meeting' ? tt("e.g. Board meetings") : tt("List name")}
          onChange={(ev) => (d.name = ev.target.value)}
        />
      </label>
      <div className="flxg-field">
        <span>{tt("Color")}</span>
        <ColorPicker value={d.color} onPick={(v) => (d.color = v)} />
      </div>
      {folders.length > 0 && (
        <label className="flxg-field">
          <span>{tt("Folder")}</span>
          <select className="flxg-input" defaultValue={d.folder} onChange={(ev) => (d.folder = +ev.target.value)}>
            <option value={0}>{tt("No folder (under the project)")}</option>
            {folders.map((f) => (
              <option key={f.id} value={f.id}>
                {f.name}
              </option>
            ))}
          </select>
        </label>
      )}
      <label className="flxg-switch-row">
        <input type="checkbox" onChange={(ev) => (d.access = ev.target.checked ? 'members' : 'inherit')} />
        <span>
          <b>{tt("Private list")}</b>
          <small>{tt("Only the people you add can see it. When off, project members can see it.")}</small>
        </span>
      </label>
    </div>
  );
}

export function listModal(c: Ctx, spaceId: number, folderId = 0, kind: 'task' | 'meeting' = 'task') {
  const { e, ui } = c;
  const d: ListDraft = { name: '', color: COLORS[4]!, access: 'inherit', folder: folderId, kind };
  const st = { name: null as HTMLInputElement | null };
  ui.modal({
    title: tt("New list"),
    body: <ListForm c={c} spaceId={spaceId} d={d} st={st} />,
    actions: [
      { label: tt("Cancel") },
      {
        label: tt("Create"),
        primary: true,
        onClick: async () => {
          d.name = d.name.trim();
          if (!d.name) {
            st.name?.focus();
            return false;
          }
          const r = await e.api.createList({ space: spaceId, folder: d.folder, name: d.name, color: d.color, access: d.access, kind: d.kind });
          e.applyStructure(r);
          if (r.id) void e.go({ page: 'list', id: r.id });
          return true;
        },
      },
    ],
  });
}

/* ---------------- Liste ayarları ---------------- */
type LTab = 'general' | 'statuses' | 'fields' | 'access';
function ListSettings({ c, listId, tab }: { c: Ctx; listId: number; tab: LTab }) {
  const { e } = c;
  const [cur, setCur] = useState<LTab>(tab);
  const [fields, setFields] = useState<FlxgField[] | null>(null);
  const [members, setMembers] = useState<FlxgListMembers | null>(null);
  const l = e.list(listId);
  useEffect(() => {
    if (cur === 'fields' && !fields) e.api.list(listId).then((r) => setFields(r.fields ?? []), c.ui.fail);
    if (cur === 'access' && !members) e.api.listMembers(listId).then(setMembers, c.ui.fail);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cur, fields, members]);
  if (!l) return null;
  return (
    <div className="flxg-lset">
      <div className="flxg-seg flxg-lset-tabs">
        {(
          [
            ['general', tt("General")],
            ['statuses', tt("Statuses")],
            ['fields', tt("Custom fields")],
            ['access', tt("Permissions")],
          ] as [LTab, string][]
        ).map(([k, label]) => (
          <button key={k} type="button" className={cur === k ? 'is-on' : ''} onClick={() => setCur(k)}>
            {label}
          </button>
        ))}
      </div>
      <div className="flxg-lset-pane">
        {cur === 'general' && <ListGeneral c={c} l={l} />}
        {cur === 'statuses' && <ListStatuses c={c} l={l} />}
        {cur === 'fields' && (fields ? <ListFields c={c} l={l} fields={fields} setFields={setFields} /> : <Skel rows={2} />)}
        {cur === 'access' && (members ? <ListAccess c={c} l={l} m={members} reload={() => setMembers(null)} /> : <Skel rows={2} />)}
      </div>
    </div>
  );
}

function ListGeneral({ c, l }: { c: Ctx; l: FlxgList }) {
  const { e, ui } = c;
  const d = useRef<{ name: string; color: string; folder_id: number; kind: string; space_id?: number }>({ name: l.name, color: l.color, folder_id: l.folder_id, kind: l.kind ?? 'task' }).current;
  const [space, setSpace] = useState(l.space_id);
  const folders = e.S.folders.filter((f) => f.space_id === space);
  return (
    <div className="flxg-form">
      <label className="flxg-field">
        <span>{tt("Name")}</span>
        <input className="flxg-input" type="text" defaultValue={l.name} onChange={(ev) => (d.name = ev.target.value)} />
      </label>
      <label className="flxg-field">
        <span>{tt("List type")}</span>
        <select className="flxg-input" defaultValue={d.kind} onChange={(ev) => (d.kind = ev.target.value)}>
          <option value="task">{tt("Task list")}</option>
          <option value="meeting">{tt("Meeting list")}</option>
        </select>
      </label>
      <div className="flxg-field">
        <span>{tt("Color")}</span>
        <ColorPicker value={l.color} onPick={(v) => (d.color = v)} />
      </div>
      <label className="flxg-field">
        <span>{tt("Folder")}</span>
        <select key={space} className="flxg-input" defaultValue={space === l.space_id ? l.folder_id : 0} onChange={(ev) => (d.folder_id = +ev.target.value)}>
          <option value={0}>{tt("No folder (under the project)")}</option>
          {folders.map((f) => (
            <option key={f.id} value={f.id}>
              {f.name}
            </option>
          ))}
        </select>
      </label>
      {e.S.spaces.length > 1 && (
        <label className="flxg-field">
          <span>{tt("Project")}</span>
          <select
            className="flxg-input"
            defaultValue={l.space_id}
            onChange={(ev) => {
              d.space_id = +ev.target.value;
              d.folder_id = 0;
              setSpace(d.space_id);
            }}
          >
            {e.S.spaces
              .filter((sp) => rank(sp.role) >= 3)
              .map((sp) => (
                <option key={sp.id} value={sp.id}>
                  {sp.name}
                </option>
              ))}
          </select>
        </label>
      )}
      <div className="flxg-row-btns">
        <button
          type="button"
          className="flxg-btn is-primary"
          onClick={() => {
            const body = { ...d, name: d.name.trim() || l.name };
            e.api.updateList(l.id, body).then((r) => (e.applyStructure(r), ui.toast(tt("Saved"))), ui.fail);
          }}
        >
          {tt("Save")}
        </button>
      </div>
    </div>
  );
}

function ListStatuses({ c, l }: { c: Ctx; l: FlxgList }) {
  const { e, ui } = c;
  const [arr, setArr] = useState<FlxgStatus[]>(() => clone(l.statuses));
  const set = (a: FlxgStatus[]) => setArr([...a]);
  const move = (i: number, to: number) => {
    const a = [...arr];
    a.splice(to, 0, a.splice(i, 1)[0]!);
    set(a);
  };
  return (
    <>
      <p className="flxg-muted">{tt("Tasks in a deleted status move to the first status. Statuses of the \"Done\" type count the task as finished.")}</p>
      <div className="flxg-statuses">
        {arr.map((s, i) => (
          <div key={(s.key || 'new') + i} className="flxg-status-edit">
            <div className="flxg-updown">
              <button type="button" className="flxg-icon-btn is-xs" disabled={i === 0} onClick={() => move(i, i - 1)}>
                <Ic n="chevD" s={12} cls="is-up" />
              </button>
              <button type="button" className="flxg-icon-btn is-xs" disabled={i === arr.length - 1} onClick={() => move(i, i + 1)}>
                <Ic n="chevD" s={12} />
              </button>
            </div>
            <input className="flxg-color-in" type="color" defaultValue={s.color} onChange={(ev) => (s.color = ev.target.value)} />
            <input className="flxg-input is-sm" type="text" defaultValue={s.label} onChange={(ev) => (s.label = ev.target.value)} />
            <select className="flxg-input is-sm" defaultValue={s.type} onChange={(ev) => (s.type = ev.target.value as FlxgStatus['type'])}>
              <option value="open">{tt("Light")}</option>
              <option value="done">{tt("Done")}</option>
            </select>
            <button type="button" className="flxg-icon-btn is-sm" disabled={arr.length < 2} aria-label={tt("Delete")} onClick={() => set(arr.filter((_, j) => j !== i))}>
              <Ic n="trash" s={15} />
            </button>
          </div>
        ))}
        <button
          type="button"
          className="flxg-btn is-sm"
          onClick={() => {
            const a = [...arr];
            a.splice(Math.max(0, a.length - 1), 0, { key: '', label: tt("New status"), color: '#f59e0b', type: 'open' });
            set(a);
          }}
        >
          <Ic n="plus" s={14} />
          {tt("Add status")}
        </button>
      </div>
      <div className="flxg-row-btns">
        <button
          type="button"
          className="flxg-btn is-primary"
          onClick={() =>
            e.api.updateList(l.id, { statuses: arr }).then((r) => {
              e.applyStructure(r);
              ui.toast(tt("Statuses saved"));
              if (e.S.scope) void e.reloadTasks();
              const nl = e.list(l.id);
              if (nl) setArr(clone(nl.statuses));
            }, ui.fail)
          }
        >
          {tt("Save")}
        </button>
      </div>
    </>
  );
}

function ListFields({ c, l, fields, setFields }: { c: Ctx; l: FlxgList; fields: FlxgField[]; setFields: (f: FlxgField[]) => void }) {
  const { e, ui } = c;
  const [edit, setEdit] = useState<FlxgField | 'new' | null>(null);
  const types = e.S.config.fieldTypes;
  const after = (list: FlxgField[]) => {
    const S = e.S;
    if (S.scope && S.scope.type === 'list' && S.scope.id === l.id) S.fields = list.slice();
    else if (S.scope) S.fields = S.fields.filter((f) => f.list_id !== l.id).concat(list);
    setFields(list);
    setEdit(null);
    e.emit('view');
    e.bump();
  };
  if (edit) return <FieldForm c={c} l={l} f={edit === 'new' ? null : edit} cancel={() => setEdit(null)} done={after} />;
  return (
    <>
      <div className="flxg-fieldlist">
        {fields.map((f) => (
          <div key={f.id} className="flxg-fielditem">
            <span className="flxg-ftype">{fieldTypeIcon(f.type)}</span>
            <span className="flxg-grow">
              <b>{f.name}</b>
              <small className="flxg-muted">{' · ' + (types[f.type] ?? f.type)}</small>
            </span>
            <button type="button" className="flxg-icon-btn is-sm" aria-label={tt("Edit")} onClick={() => setEdit(f)}>
              <Ic n="edit" s={15} />
            </button>
            <button
              type="button"
              className="flxg-icon-btn is-sm"
              aria-label={tt("Delete")}
              onClick={() =>
                void ui.confirm(tt("Delete field"), tt("The field \"{n}\" and its values on all tasks will be deleted.", { n: f.name }), tt("Delete"), true).then((ok) => {
                  if (ok) e.api.deleteField(f.id).then((r) => after(r.fields ?? []), ui.fail);
                })
              }
            >
              <Ic n="trash" s={15} />
            </button>
          </div>
        ))}
        {!fields.length && <div className="flxg-empty-sm">{tt("This list has no custom fields. You can add fields such as text, number, date, dropdown, star rating or file.")}</div>}
      </div>
      <div className="flxg-row-btns is-left">
        <button type="button" className="flxg-btn" onClick={() => setEdit('new')}>
          <Ic n="plus" s={14} />
          {tt("Add field")}
        </button>
      </div>
    </>
  );
}

function FieldForm({ c, l, f, cancel, done }: { c: Ctx; l: FlxgList; f: FlxgField | null; cancel: () => void; done: (list: FlxgField[]) => void }) {
  const { e, ui } = c;
  const d = useRef<{ name: string; type: string; options: FlxgField['options'] }>(f ? clone({ name: f.name, type: f.type, options: f.options ?? {} }) : { name: '', type: 'text', options: {} }).current;
  const [type, setType] = useState(d.type);
  const [choices, setChoices] = useState(() => d.options.choices ?? [{ id: '', label: '', color: COLORS[1]! }]);
  const nameRef = useRef<HTMLInputElement>(null);
  const types = e.S.config.fieldTypes;
  const hasChoices = type === 'dropdown' || type === 'labels';
  const save = () => {
    d.name = d.name.trim();
    if (!d.name) return nameRef.current?.focus();
    if (hasChoices) d.options.choices = choices;
    const p = f ? e.api.updateField(f.id, { name: d.name, options: d.options }) : e.api.createField(l.id, d);
    p.then((r) => {
      const S = e.S;
      // Yeni alan görünümlerde varsayılan olarak görünür olsun.
      if (!f && r.field && S.draft && S.scope?.type === 'list' && S.scope.id === l.id) {
        S.draft.fields = (S.draft.fields ?? []).concat(['f' + r.field.id]);
        S.dirty = true;
      }
      done(r.fields ?? []);
    }, ui.fail);
  };
  return (
    <div className="flxg-form">
      <label className="flxg-field">
        <span>{tt("Name")}</span>
        <input ref={nameRef} className="flxg-input" type="text" autoFocus defaultValue={d.name} placeholder={tt("Field name")} onChange={(ev) => (d.name = ev.target.value)} />
      </label>
      <label className="flxg-field">
        <span>{tt("Type")}</span>
        <select className="flxg-input" disabled={!!f} defaultValue={d.type} onChange={(ev) => ((d.type = ev.target.value), setType(ev.target.value))}>
          {Object.keys(types).map((k) => (
            <option key={k} value={k}>
              {types[k]}
            </option>
          ))}
        </select>
        {f && <small className="flxg-help">{tt("The type cannot be changed later.")}</small>}
      </label>
      {hasChoices && (
        <div className="flxg-field">
          <span>{tt("Options")}</span>
          <div className="flxg-choices">
            {choices.map((ch, i) => (
              <div key={i} className="flxg-choice-edit">
                <input className="flxg-color-in" type="color" defaultValue={ch.color || '#94a3b8'} onChange={(ev) => (ch.color = ev.target.value)} />
                <input className="flxg-input is-sm" type="text" defaultValue={ch.label} placeholder={tt("Option")} autoFocus={i > 0 && i === choices.length - 1 && !ch.label} onChange={(ev) => (ch.label = ev.target.value)} />
                <button type="button" className="flxg-icon-btn is-sm" onClick={() => setChoices(choices.filter((_, j) => j !== i))}>
                  <Ic n="x" s={14} />
                </button>
              </div>
            ))}
            <button type="button" className="flxg-btn is-sm" onClick={() => setChoices([...choices, { id: '', label: '', color: COLORS[(choices.length * 3) % COLORS.length]! }])}>
              <Ic n="plus" s={14} />
              {tt("Add option")}
            </button>
          </div>
        </div>
      )}
      {type === 'rating' && (
        <label className="flxg-field">
          <span>{tt("Max stars")}</span>
          <input className="flxg-input" type="number" min={3} max={10} defaultValue={d.options.max ?? 5} onChange={(ev) => (d.options.max = +ev.target.value)} />
        </label>
      )}
      {type === 'money' && (
        <label className="flxg-field">
          <span>{tt("Currency")}</span>
          <input className="flxg-input" type="text" maxLength={3} defaultValue={d.options.currency ?? 'TRY'} onChange={(ev) => (d.options.currency = ev.target.value.toUpperCase())} />
        </label>
      )}
      {type === 'number' && (
        <label className="flxg-field">
          <span>{tt("Decimal places")}</span>
          <input className="flxg-input" type="number" min={0} max={4} defaultValue={d.options.decimals ?? 0} onChange={(ev) => (d.options.decimals = +ev.target.value)} />
        </label>
      )}
      <label className="flxg-field">
        <span>{tt("Description (optional)")}</span>
        <input className="flxg-input" type="text" defaultValue={d.options.hint ?? ''} onChange={(ev) => (d.options.hint = ev.target.value)} />
      </label>
      <div className="flxg-row-btns">
        <button type="button" className="flxg-btn" onClick={cancel}>
          {tt("Cancel")}
        </button>
        <button type="button" className="flxg-btn is-primary" onClick={save}>
          {f ? tt("Save") : tt("Add the field")}
        </button>
      </div>
    </div>
  );
}

function ListAccess({ c, l, m, reload }: { c: Ctx; l: FlxgList; m: FlxgListMembers; reload: () => void }) {
  const { e, ui } = c;
  const [acc, setAcc] = useState(m.access);
  const members = useRef(m.members.slice()).current;
  const roles = (e.S.config as { roles?: Record<string, string> }).roles ?? {};
  return (
    <div className="flxg-form">
      <div className="flxg-field">
        <span>{tt("Who can see it")}</span>
        <div className="flxg-radios">
          <Radios
            name="flxg-lacc"
            value={acc}
            onChange={setAcc}
            opts={[
              ['inherit', tt("Project members"), m.spaceAccess === 'everyone' ? tt("Everyone who can use Tasks ({r}).", { r: roles[m.spaceDefault] ?? m.spaceDefault }) : tt("The project's members, with their project roles.")],
              ['members', tt("Selected people only"), tt("Private list: only the people below and project managers can see it.")],
            ]}
          />
        </div>
      </div>
      <div className="flxg-field">
        <span>{tt("Per-person roles")}</span>
        <small className="flxg-help">{tt("Can view: read only. Can comment: read and comment. Can edit: add and change tasks. Can manage: list settings, fields and permissions.")}</small>
        <MembersEditor c={c} members={members} />
      </div>
      <div className="flxg-row-btns">
        <button
          type="button"
          className="flxg-btn is-primary"
          onClick={() =>
            e.api.saveListMembers(l.id, { members, access: acc }).then((r) => {
              e.applyStructure(r);
              ui.toast(tt("Permissions saved"));
              reload();
            }, ui.fail)
          }
        >
          {tt("Save")}
        </button>
      </div>
    </div>
  );
}

export function listSettings(c: Ctx, listId: number, tab: LTab = 'general') {
  const l = c.e.list(listId);
  if (!l) return;
  c.ui.modal({ title: tt("List settings: {n}", { n: l.name }), body: <ListSettings c={c} listId={listId} tab={tab} />, wide: true });
}

/* ---------------- Listeyi taşı ---------------- */
function MoveForm({ c, l, st }: { c: Ctx; l: FlxgList; st: MoveState }) {
  const { e, ui } = c;
  const [, force] = useState(0);
  const redraw = () => force((n) => n + 1);
  const seq = useRef(0);
  const check = () => {
    st.check = null;
    st.tmpl = 0;
    redraw();
    if (st.space === l.space_id) return;
    const my = ++seq.current;
    e.api.moveCheck(l.id, st.space).then((d) => {
      if (my !== seq.current) return;
      st.check = d;
      redraw();
    }, ui.fail);
  };
  useEffect(check, []); // eslint-disable-line react-hooks/exhaustive-deps
  const autoMap = (tl: FlxgList) => {
    st.map = {};
    // Önce aynı ad; yoksa aynı türdeki durumlar arasında sıradaki yerine en yakın olan.
    for (const s of l.statuses) {
      const same = tl.statuses.find((x) => norm(x.label) === norm(s.label));
      const src = l.statuses.filter((x) => x.type === s.type);
      const dst = tl.statuses.filter((x) => x.type === s.type);
      const byType = dst.length ? dst[src.length > 1 ? Math.round((src.indexOf(s) * (dst.length - 1)) / (src.length - 1)) : 0] : null;
      st.map[s.key] = (same ?? byType ?? tl.statuses[0]!).key;
    }
  };
  const tls = e.S.lists.filter((x) => x.space_id === st.space && x.id !== l.id);
  const tl = st.tmpl ? e.list(st.tmpl) : null;
  return (
    <div className="flxg-move">
      <label className="flxg-field">
        <span>{tt("Target project or folder")}</span>
        <select
          className="flxg-input"
          defaultValue={st.space + ':' + st.folder}
          onChange={(ev) => {
            const [sp, f] = ev.target.value.split(':');
            st.space = +sp!;
            st.folder = +f!;
            check();
          }}
        >
          {e.S.spaces
            .filter((sp) => rank(sp.role) >= 3)
            .map((sp) => (
              <optgroup key={sp.id} label={sp.name}>
                <option value={sp.id + ':0'}>{sp.name}</option>
                {e.S.folders
                  .filter((f) => f.space_id === sp.id)
                  .map((f) => (
                    <option key={f.id} value={sp.id + ':' + f.id}>
                      {sp.name + ' › ' + f.name}
                    </option>
                  ))}
              </optgroup>
            ))}
        </select>
      </label>
      <div className="flxg-move-extra">
        {st.space === l.space_id ? (
          <p className="flxg-muted">{tt("Moves within the same project; statuses, fields and tags stay the same.")}</p>
        ) : !st.check ? (
          <div className="flxg-skel-line">
            <span className="flxg-sk" style={{ display: 'block', width: '70%', height: 12 }} />
          </div>
        ) : (
          <>
            <div className="flxg-move-sec">
              <h4>{tt("Statuses")}</h4>
              <select
                className="flxg-input"
                value={String(st.tmpl)}
                onChange={(ev) => {
                  st.tmpl = +ev.target.value;
                  const t2 = st.tmpl ? e.list(st.tmpl) : null;
                  if (t2) autoMap(t2);
                  redraw();
                }}
              >
                <option value="0">{tt("Keep the list's own statuses")}</option>
                {tls.map((x) => (
                  <option key={x.id} value={x.id}>
                    {tt("Use the statuses of \"{n}\"", { n: x.name })}
                  </option>
                ))}
              </select>
              {tl ? (
                <>
                  <p className="flxg-muted">{tt("Pick a match for each status; tasks move to that status.")}</p>
                  <div className="flxg-move-map">
                    {l.statuses.map((s) => (
                      <div key={s.key} className="flxg-move-row">
                        <span className="flxg-move-src">
                          <span className="flxg-dot" style={{ background: s.color }} />
                          {s.label}
                        </span>
                        <Ic n="chevR" s={14} />
                        <select className="flxg-input" defaultValue={st.map[s.key]} onChange={(ev) => (st.map[s.key] = ev.target.value)}>
                          {tl.statuses.map((x) => (
                            <option key={x.key} value={x.key}>
                              {x.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <p className="flxg-muted">{tt("Statuses and custom fields move with the list; tasks keep their status.")}</p>
              )}
            </div>
            {st.check.tags.length > 0 && (
              <div className="flxg-move-sec">
                <h4>{tt("Tags")}</h4>
                <p className="flxg-muted">
                  {tt("{n} tags are linked by name in the target project: {e} already exist, {c} will be created.", {
                    n: st.check.tags.length,
                    e: st.check.tags.filter((x) => x.exists).length,
                    c: st.check.tags.filter((x) => !x.exists).length,
                  })}
                </p>
                <div className="flxg-move-tags">
                  {st.check.tags.map((x) => (
                    <span key={x.id} className="flxg-tag" style={{ ['--c' as string]: x.color }}>
                      {x.name + (x.exists ? '' : ' +')}
                    </span>
                  ))}
                </div>
              </div>
            )}
            {st.check.people.length > 0 && (
              <div className="flxg-move-sec">
                <h4>{tt("People who will lose access")}</h4>
                <p className="flxg-muted">{tt("These people are not members of the target project; checked ones are added as members.")}</p>
                {st.check.people.map((u) => (
                  <label key={u.id} className="flxg-move-person">
                    <input type="checkbox" defaultChecked={st.add[u.id] !== false} onChange={(ev) => (st.add[u.id] = ev.target.checked)} />
                    <Avatar u={e.S.users[u.id] ?? { id: u.id, name: u.name, initials: u.name.slice(0, 2), avatar: '', color: '#94a3b8' }} size={20} />
                    <span>{u.name}</span>
                  </label>
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
type MoveState = { space: number; folder: number; check: FlxgMoveCheck | null; tmpl: number; map: Record<string, string>; add: Record<number, boolean> };

export function moveListDialog(c: Ctx, l: FlxgList, toSpace = 0, toFolder = 0) {
  const { e, ui } = c;
  const st: MoveState = { space: toSpace || l.space_id, folder: toSpace ? toFolder : l.folder_id || 0, check: null, tmpl: 0, map: {}, add: {} };
  ui.modal({
    title: tt("Move \"{n}\"", { n: l.name }),
    body: <MoveForm c={c} l={l} st={st} />,
    actions: [
      { label: tt("Cancel") },
      {
        label: tt("Move"),
        primary: true,
        onClick: async () => {
          if (st.space === l.space_id && st.folder === (l.folder_id || 0)) return true;
          const cross = st.space !== l.space_id;
          if (cross && !st.check) return false;
          const b: Record<string, unknown> = { space: st.space, folder: st.folder };
          if (cross && st.tmpl) {
            b.statuses_from = st.tmpl;
            b.status_map = st.map;
          }
          if (cross && st.check) b.add_members = st.check.people.filter((u) => st.add[u.id] !== false).map((u) => u.id);
          e.applyStructure(await e.api.moveList(l.id, b));
          tree.set('sp-open', st.space, true);
          if (st.folder) tree.set('collapsed', 'f' + st.folder, false);
          ui.toast(tt("\"{n}\" moved.", { n: l.name }));
          e.emit('list-moved', l.id);
          return true;
        },
      },
    ],
  });
}

/* ---------------- Menüler ---------------- */
function home(c: Ctx) {
  const { e } = c;
  const last = store.get('last');
  const m = last ? /^(list|space):(\d+)$/.exec(last) : null;
  if (m && ((m[1] === 'list' && e.list(+m[2]!)) || (m[1] === 'space' && e.space(+m[2]!)))) return void e.go({ page: m[1] as 'list' | 'space', id: +m[2]! });
  const first = e.S.lists[0];
  void e.go(first ? { page: 'list', id: first.id } : { page: 'home' });
}

export function addMenu(c: Ctx, anchor: HTMLElement, spaceId: number, folderId: number, newDoc: (space: number, folder: number) => void) {
  c.ui.menu(anchor, [
    { label: tt("Task list"), icon: 'list', onClick: () => listModal(c, spaceId, folderId, 'task') },
    { label: tt("Meeting list"), icon: 'meeting', onClick: () => listModal(c, spaceId, folderId, 'meeting') },
    { label: tt("Document"), icon: 'doc', onClick: () => newDoc(spaceId, folderId) },
  ]);
}

export function spaceMenu(c: Ctx, anchor: HTMLElement, sp: FlxgSpace, newDoc: (space: number, folder: number) => void) {
  const { e, ui } = c;
  const make = !!e.S.me?.can.create && rank(sp.role) >= 3;
  const admin = rank(sp.role) >= 4;
  ui.menu(anchor, [
    make && { label: tt("New list"), icon: 'list', onClick: () => listModal(c, sp.id, 0) },
    make && { label: tt("New meeting list"), icon: 'meeting', onClick: () => listModal(c, sp.id, 0, 'meeting') },
    make && { label: tt("New document"), icon: 'doc', onClick: () => newDoc(sp.id, 0) },
    make && {
      label: tt("New folder"),
      icon: 'folder',
      onClick: () =>
        void ui.prompt(tt("Folder name"), '', tt("Create")).then((n) => {
          if (n) e.api.createFolder(sp.id, n).then((r) => e.applyStructure(r), ui.fail);
        }),
    },
    admin && { sep: true },
    admin && { label: tt("Project settings and members"), icon: 'settings', onClick: () => spaceModal(c, sp) },
    admin && {
      label: tt("Delete project"),
      icon: 'trash',
      danger: true,
      onClick: () =>
        void ui.confirm(tt("Delete project"), tt("All lists, tasks, comments and files in the project \"{n}\" will be permanently deleted.", { n: sp.name }), tt("Delete"), true).then((ok) => {
          if (ok)
            e.api.deleteSpace(sp.id).then((r) => {
              e.applyStructure(r);
              home(c);
            }, ui.fail);
        }),
    },
  ]);
}

export function folderMenu(c: Ctx, anchor: HTMLElement, f: { id: number; name: string }, sp: FlxgSpace, newDoc: (space: number, folder: number) => void) {
  const { e, ui } = c;
  ui.menu(anchor, [
    { label: tt("Add a list to this folder"), icon: 'plus', onClick: () => listModal(c, sp.id, f.id) },
    { label: tt("Add a meeting list to this folder"), icon: 'meeting', onClick: () => listModal(c, sp.id, f.id, 'meeting') },
    { label: tt("Add a document to this folder"), icon: 'doc', onClick: () => newDoc(sp.id, f.id) },
    {
      label: tt("Rename"),
      icon: 'edit',
      onClick: () =>
        void ui.prompt(tt("Folder name"), f.name).then((n) => {
          if (n) e.api.updateFolder(f.id, { name: n }).then((r) => e.applyStructure(r), ui.fail);
        }),
    },
    rank(sp.role) >= 4 && {
      label: tt("Delete folder"),
      icon: 'trash',
      danger: true,
      onClick: () =>
        void ui.confirm(tt("Delete folder"), tt("The folder will be deleted; its lists will move to the project root."), tt("Delete"), true).then((ok) => {
          if (ok) e.api.deleteFolder(f.id).then((r) => e.applyStructure(r), ui.fail);
        }),
    },
  ]);
}

export function listMenu(c: Ctx, anchor: HTMLElement, l: FlxgList) {
  const { e, ui } = c;
  ui.menu(anchor, [
    { label: tt("List settings"), icon: 'settings', onClick: () => listSettings(c, l.id, 'general') },
    { label: tt("Statuses"), icon: 'check', onClick: () => listSettings(c, l.id, 'statuses') },
    { label: tt("Custom fields"), icon: 'columns', onClick: () => listSettings(c, l.id, 'fields') },
    { label: tt("Permissions"), icon: 'lock', onClick: () => listSettings(c, l.id, 'access') },
    { label: tt("Move…"), icon: 'move', onClick: () => moveListDialog(c, l) },
    { sep: true },
    {
      label: tt("Delete list"),
      icon: 'trash',
      danger: true,
      onClick: () =>
        void ui.confirm(tt("Delete list"), tt("All tasks, comments and files in the list \"{n}\" will be permanently deleted.", { n: l.name }), tt("Delete"), true).then((ok) => {
          if (ok)
            e.api.deleteList(l.id).then((r) => {
              e.applyStructure(r);
              if (e.S.scope?.type === 'list' && e.S.scope.id === l.id) home(c);
            }, ui.fail);
        }),
    },
  ]);
}

/* ---------------- Sol ağaçta sürükle-bırak: proje sırası, listeyi projeye/klasöre taşı ---------------- */
type Drag = { list?: FlxgList; space?: FlxgSpace } | null;
let DRAG: Drag = null;
export function treeDragProps(info: NonNullable<Drag>, setTreeDrag: (v: boolean) => void) {
  return {
    draggable: true,
    onDragStart: (ev: React.DragEvent<HTMLElement>) => {
      DRAG = info;
      ev.dataTransfer.effectAllowed = 'move';
      try {
        ev.dataTransfer.setData('text/plain', info.list ? 'list:' + info.list.id : 'space:' + info.space!.id);
      } catch {
        /* yok */
      }
      ev.currentTarget.classList.add('is-dragging');
      setTreeDrag(true);
    },
    onDragEnd: (ev: React.DragEvent<HTMLElement>) => {
      DRAG = null;
      ev.currentTarget.classList.remove('is-dragging');
      setTreeDrag(false);
      document.querySelectorAll('.is-drop-in,.is-drop-before,.is-drop-after').forEach((x) => x.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after'));
    },
  };
}
/** Proje satırı: proje sürükleniyorsa üstüne/altına (sıra), liste sürükleniyorsa içine (taşı). Klasör satırı: liste içine. */
export function treeDropProps(c: Ctx, target: { space: FlxgSpace; folder: number }) {
  const where = (ev: React.DragEvent<HTMLElement>) => {
    if (!DRAG) return '';
    if (DRAG.list) {
      const l = DRAG.list;
      if (l.space_id === target.space.id && (l.folder_id || 0) === target.folder) return '';
      return rank(target.space.role) >= 3 ? 'in' : '';
    }
    if (!target.folder && DRAG.space && DRAG.space.id !== target.space.id) {
      const r = ev.currentTarget.getBoundingClientRect();
      return ev.clientY < r.top + r.height / 2 ? 'before' : 'after';
    }
    return '';
  };
  const clear = (el: HTMLElement) => el.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after');
  return {
    onDragOver: (ev: React.DragEvent<HTMLElement>) => {
      const w = where(ev);
      const el = ev.currentTarget;
      el.classList.toggle('is-drop-in', w === 'in');
      el.classList.toggle('is-drop-before', w === 'before');
      el.classList.toggle('is-drop-after', w === 'after');
      if (w) {
        ev.preventDefault();
        ev.dataTransfer.dropEffect = 'move';
      }
    },
    onDragLeave: (ev: React.DragEvent<HTMLElement>) => clear(ev.currentTarget),
    onDrop: (ev: React.DragEvent<HTMLElement>) => {
      const w = where(ev);
      const d = DRAG;
      clear(ev.currentTarget);
      if (!w || !d) return;
      ev.preventDefault();
      if (d.list) return moveListDialog(c, d.list, target.space.id, target.folder);
      const { e, ui } = c;
      const sp = d.space!;
      const others = e.S.spaces.filter((x) => x.id !== sp.id);
      const i = others.indexOf(target.space) + (w === 'after' ? 1 : 0);
      const pos = between(i > 0 ? others[i - 1]!.position : null, i < others.length ? others[i]!.position : null);
      sp.position = pos;
      e.S.spaces = [...others.slice(0, i), sp, ...others.slice(i)];
      e.bump();
      e.api.updateSpace(sp.id, { position: pos }).then(
        (r) => e.applyStructure(r),
        (er) => {
          ui.fail(er);
          e.api.boot().then((b) => e.applyStructure(b), () => {});
        },
      );
    },
  };
}
