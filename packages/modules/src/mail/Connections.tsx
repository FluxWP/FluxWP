import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxMailConnectionFragment, FlxMailConnectionMetaQuery, FlxMailConnectionSaveInput, FlxMailWaStateFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxMailConnectionDelete,
  FlxMailConnectionMeta,
  FlxMailConnections,
  FlxMailConnectionSave,
  FlxMailConnectionSetDefault,
  FlxMailConnectionTest,
  FlxMailWaConnect,
  FlxMailWaLogout,
  FlxMailWaRestart,
  FlxMailWaServer,
  FlxMailWaServerUpdate,
  FlxMailWaState,
  FlxMailWacloudSetup,
  FlxMailWacloudTemplates,
} from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useMail } from './common';

type Conn = FlxMailConnectionFragment;
type Meta = FlxMailConnectionMetaQuery['flxMailConnectionMeta'];
type TypeInfo = Meta['types'][number];
type FieldDef = TypeInfo['fields'][number];
type WaState = Pick<FlxMailWaStateFragment, 'state' | 'me' | 'name' | 'error'>;
type Dialog =
  | { kind: 'qr'; conn: Conn; intro?: string }
  | { kind: 'setup'; conn: Conn }
  | { kind: 'test'; conn: Conn }
  | { kind: 'server'; conn: Conn }
  | { kind: 'logout' | 'delete' | 'deleteRemote'; conn: Conn };

const CHANNELS: { key: 'WHATSAPP' | 'SMS'; label: string }[] = [
  { key: 'WHATSAPP', label: 'WhatsApp' },
  { key: 'SMS', label: 'SMS' },
];

const shortType = (label: string) => label.replace(/^(WhatsApp|SMS)\s*·\s*/u, '').trim();

function hostOf(url: string): string {
  try {
    return new URL(url).host;
  } catch {
    return url;
  }
}

export function ConnectionsPage({ editKey, onEdit }: { editKey: string | null; onEdit: (key: string | null) => void }) {
  const { t, toast } = useMail();
  const { client, scope } = useGraphQL();
  const qc = useQueryClient();
  const meta = useFlxQuery(FlxMailConnectionMeta, {});
  const list = useFlxQuery(FlxMailConnections, { first: 50 });
  const setDefault = useFlxMutation(FlxMailConnectionSetDefault);
  const remove = useFlxMutation(FlxMailConnectionDelete);
  const restart = useFlxMutation(FlxMailWaRestart);
  const logout = useFlxMutation(FlxMailWaLogout);
  const [states, setStates] = useState<Record<number, WaState>>({});
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const refreshed = useRef('');

  const nodes = useMemo(() => list.data?.flxMailConnections.nodes ?? [], [list.data]);
  const reload = useCallback(() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailConnections) }), [qc, scope]);
  const setState = useCallback((id: number, s: WaState) => setStates((m) => ({ ...m, [id]: s })), []);

  // Opening the list refreshes every WhatsApp state once, one after the other (hosting rate limits).
  useEffect(() => {
    const ids = nodes.filter((c) => c.waState !== null).map((c) => c.databaseId);
    const sig = ids.join(',');
    if (!sig || refreshed.current === sig) return;
    refreshed.current = sig;
    let alive = true;
    void (async () => {
      for (const id of ids) {
        if (!alive) return;
        try {
          const r = await client.request(FlxMailWaState, { id, fresh: true });
          if (alive) setState(id, r.flxMailWaState);
        } catch {
          /* the cached state stays */
        }
      }
    })();
    return () => {
      alive = false;
    };
  }, [nodes, client, setState]);

  if (meta.isLoading || list.isLoading) return <Loading />;
  if (meta.isError || list.isError || !meta.data) return <p className="flxmail-bad">{errorText(meta.error ?? list.error, t('Something went wrong.'))}</p>;
  const info = meta.data.flxMailConnectionMeta;

  if (editKey) {
    const isNew = editKey.startsWith('new:');
    const type = isNew ? editKey.slice(4) : '';
    const conn = isNew ? null : (nodes.find((c) => String(c.databaseId) === editKey) ?? null);
    const kind = isNew ? type : (conn?.type ?? '');
    const def = info.types.find((x) => x.type === kind) ?? null;
    if (!def || (!isNew && !conn)) {
      return (
        <p>
          {t('Connection not found.')}{' '}
          <button type="button" className="flxmail-link" onClick={() => onEdit(null)}>
            {t('Connections')}
          </button>
        </p>
      );
    }
    return (
      <>
        <ConnectionForm
          def={def}
          conn={conn}
          all={nodes}
          onCancel={() => onEdit(null)}
          onSaved={(saved) => {
            reload();
            onEdit(null);
            if (!conn && saved.type === 'wacloud') {
              toast(t('Connection saved. Now enter the webhook address in your Meta app.'));
              setDialog({ kind: 'setup', conn: saved });
            } else if (!conn && def.evolution) {
              setDialog({ kind: 'qr', conn: saved, intro: t('Connection saved. On the phone, scan the QR in WhatsApp › Linked devices › Link a device.') });
            } else {
              toast(t('Connection saved. Try it with "Test".'));
            }
          }}
        />
        {dialog ? <Dialogs dialog={dialog} info={info} close={() => setDialog(null)} setState={setState} states={states} reload={reload} /> : null}
      </>
    );
  }

  const act = async (run: () => Promise<{ errors?: ReadonlyArray<{ message: string }> | null }>, ok: string, then?: () => void) => {
    try {
      const err = payloadError((await run()).errors);
      if (err) toast(err, 'error');
      else {
        toast(ok);
        then?.();
        reload();
      }
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  return (
    <div>
      {CHANNELS.map((ch) => {
        const rows = nodes.filter((c) => c.channel === ch.key);
        const types = info.types.filter((x) => x.channel === ch.key);
        return (
          <section key={ch.key} className="flxmail-card">
            <div className="flxmail-cardhead">
              <h2>{ch.label}</h2>
              <div className="flxmail-actions">
                {types.map((x) => (
                  <button key={x.type} type="button" className="flxmail-btn" onClick={() => onEdit('new:' + x.type)}>
                    + {shortType(x.label)}
                  </button>
                ))}
              </div>
            </div>
            {ch.key === 'WHATSAPP' && rows.length ? <p className="flxmail-muted">{t('Each row is one number. Press "Connect" to link a number, see its status or reconnect it; no separate panel needed.')}</p> : null}
            {rows.length ? null : <p className="flxmail-muted">{t('No connections yet.')}</p>}
            <ul className="flxmail-list">
              {rows.map((c) => (
                <ConnectionRow
                  key={c.databaseId}
                  conn={c}
                  info={info}
                  state={states[c.databaseId] ?? c.waState}
                  onEdit={() => onEdit(String(c.databaseId))}
                  open={(d) => setDialog(d)}
                  makeDefault={() => void act(() => setDefault.mutateAsync({ input: { id: c.databaseId } }).then((r) => r.flxMailConnectionSetDefault ?? {}), t('Default connection changed.'))}
                  doRestart={() =>
                    void restart
                      .mutateAsync({ input: { id: c.databaseId } })
                      .then((r) => {
                        const err = payloadError(r.flxMailWaRestart?.errors);
                        if (err) toast(err, 'error');
                        else {
                          if (r.flxMailWaRestart?.state) setState(c.databaseId, r.flxMailWaRestart.state);
                          toast(t('OK'));
                        }
                      })
                      .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
                  }
                />
              ))}
            </ul>
          </section>
        );
      })}
      {info.flxsActive ? <p className="flxmail-muted">{t('Flux Certificates sends WhatsApp and SMS through the active connections here; certificate emails use the corporate template.')}</p> : null}
      {dialog?.kind === 'logout' ? (
        <Confirm
          message={t("Log the number out? Messages won't arrive; reconnecting needs a QR scan.")}
          confirm={t('Log the number out')}
          busy={logout.isPending}
          onClose={() => setDialog(null)}
          onYes={() => {
            const c = dialog.conn;
            setDialog(null);
            void logout
              .mutateAsync({ input: { id: c.databaseId } })
              .then((r) => {
                const err = payloadError(r.flxMailWaLogout?.errors);
                if (err) toast(err, 'error');
                else {
                  if (r.flxMailWaLogout?.state) setState(c.databaseId, r.flxMailWaLogout.state);
                  toast(t('OK'));
                }
              })
              .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'));
          }}
        />
      ) : null}
      {dialog?.kind === 'delete' || dialog?.kind === 'deleteRemote' ? (
        <Confirm
          message={
            dialog.kind === 'delete'
              ? t('Delete this connection?')
              : t('Also delete the number from the server? The session, message history and contacts are deleted on the server; adding it again needs a QR scan.')
          }
          confirm={dialog.kind === 'delete' ? t('Delete') : t('Also delete from the server')}
          danger
          busy={remove.isPending}
          onClose={() => setDialog(null)}
          onYes={() => {
            const c = dialog.conn;
            const remote = dialog.kind === 'deleteRemote';
            setDialog(null);
            void act(() => remove.mutateAsync({ input: { id: c.databaseId, remote } }).then((r) => r.flxMailConnectionDelete ?? {}), t('Connection deleted.'));
          }}
        />
      ) : null}
      {dialog && dialog.kind !== 'logout' && dialog.kind !== 'delete' && dialog.kind !== 'deleteRemote' ? (
        <Dialogs dialog={dialog} info={info} close={() => setDialog(null)} setState={setState} states={states} reload={reload} />
      ) : null}
    </div>
  );
}

function Confirm({ message, confirm, danger, busy, onYes, onClose }: { message: string; confirm: string; danger?: boolean; busy?: boolean; onYes: () => void; onClose: () => void }) {
  const { t } = useMail();
  return (
    <Modal onClose={onClose}>
      <p>{message}</p>
      <div className="flxmail-actions">
        <button type="button" className={'flxmail-btn ' + (danger ? 'is-danger' : 'is-primary')} disabled={busy} onClick={onYes}>
          {confirm}
        </button>
        <button type="button" className="flxmail-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
      </div>
    </Modal>
  );
}

function waLabel(t: ReturnType<typeof useMail>['t'], s: WaState | null): string {
  if (!s) return '';
  const base =
    { open: t('Connected'), connecting: t('Waiting for QR'), close: t('Not connected'), missing: t('Not on the server'), error: t('Server unreachable') }[s.state] ?? t('Status unknown');
  return s.state === 'open' && s.me ? base + ' · ' + s.me : base;
}

function ConnectionRow({
  conn: c,
  info,
  state,
  onEdit,
  open,
  makeDefault,
  doRestart,
}: {
  conn: Conn;
  info: Meta;
  state: WaState | null;
  onEdit: () => void;
  open: (d: Dialog) => void;
  makeDefault: () => void;
  doRestart: () => void;
}) {
  const { t } = useMail();
  const def = info.types.find((x) => x.type === c.type);
  const evo = !!def?.evolution;
  const cloud = !!def?.cloud;
  const detail = cloud ? 'Phone ID ' + c.phoneId : evo ? hostOf(c.baseUrl) + ' · ' + c.instance : c.header + ' · ' + c.username;
  const menu = useRef<HTMLDetailsElement>(null);
  const run = (fn: () => void) => () => {
    if (menu.current) menu.current.open = false;
    fn();
  };
  return (
    <li className={'flxmail-row flxmail-conn' + (c.active ? '' : ' is-off')}>
      <div className="flxmail-connmain">
        <strong>{c.name}</strong>{' '}
        {c.isDefault ? <span className="flxmail-badge is-sent">{t('Default')}</span> : null}{' '}
        {c.active ? null : <span className="flxmail-badge">{t('Disabled')}</span>}{' '}
        {state ? (
          <span className="flxmail-badge flxmail-wastate" data-state={state.state} title={state.error || state.name}>
            {waLabel(t, state)}
          </span>
        ) : null}{' '}
        {cloud && c.appSecretMissing ? <span className="flxmail-badge is-warn">{t('App Secret missing · webhook off')}</span> : null}
        <div className="flxmail-muted">
          {c.typeLabel} · {detail}
          {c.lastTest ? (
            <>
              {' · '}
              <span className={c.lastTest.ok ? 'flxmail-ok' : 'flxmail-bad'}>{c.lastTest.ok ? t('Test successful') : c.lastTest.error}</span>
            </>
          ) : null}
          {c.origin ? <> · {t('Migrated from Flux Certificates')}</> : null}
        </div>
      </div>
      <div className="flxmail-connactions">
        {cloud ? (
          <button type="button" className="flxmail-btn is-primary" onClick={() => open({ kind: 'setup', conn: c })}>
            {t('Setup')}
          </button>
        ) : null}
        {evo && state?.state !== 'open' ? (
          <button type="button" className="flxmail-btn is-primary" onClick={() => open({ kind: 'qr', conn: c })}>
            {t('Connect')}
          </button>
        ) : null}
        <button type="button" className={'flxmail-btn' + (!evo || state?.state === 'open' ? ' is-primary' : '')} onClick={() => open({ kind: 'test', conn: c })}>
          {t('Test')}
        </button>
        <button type="button" className="flxmail-btn" onClick={onEdit}>
          {t('Edit')}
        </button>
        <details className="flxmail-more" ref={menu}>
          <summary className="flxmail-btn" aria-label={t('More actions')}>
            ⋯
          </summary>
          <div className="flxmail-moremenu">
            {evo ? (
              <>
                <button type="button" onClick={run(() => open({ kind: 'qr', conn: c }))}>
                  {t('Status / reconnect (QR)')}
                </button>
                <button type="button" onClick={run(doRestart)}>
                  {t('Restart')}
                </button>
                <button type="button" onClick={run(() => open({ kind: 'logout', conn: c }))}>
                  {t('Log the number out')}
                </button>
                {c.type === 'flxwpapi' ? (
                  <button type="button" onClick={run(() => open({ kind: 'server', conn: c }))}>
                    {t('Server settings')}
                  </button>
                ) : null}
              </>
            ) : null}
            {c.isDefault ? null : (
              <button type="button" onClick={run(makeDefault)}>
                {t('Make default')}
              </button>
            )}
            <button type="button" onClick={run(() => open({ kind: 'delete', conn: c }))}>
              {evo ? t('Delete (number stays on the server)') : t('Delete')}
            </button>
            {evo ? (
              <button type="button" className="is-danger" onClick={run(() => open({ kind: 'deleteRemote', conn: c }))}>
                {t('Also delete from the server')}
              </button>
            ) : null}
          </div>
        </details>
      </div>
    </li>
  );
}

function Dialogs({
  dialog,
  info,
  close,
  setState,
  states,
  reload,
}: {
  dialog: Dialog;
  info: Meta;
  close: () => void;
  setState: (id: number, s: WaState) => void;
  states: Record<number, WaState>;
  reload: () => void;
}) {
  if (dialog.kind === 'qr') return <QrDialog conn={dialog.conn} intro={dialog.intro} close={close} setState={setState} />;
  if (dialog.kind === 'setup') return <SetupDialog conn={dialog.conn} close={close} setState={setState} />;
  if (dialog.kind === 'server') return <ServerDialog conn={dialog.conn} close={close} />;
  if (dialog.kind === 'test') return <TestDialog conn={dialog.conn} info={info} state={states[dialog.conn.databaseId] ?? dialog.conn.waState} close={close} reload={reload} />;
  return null;
}

/* ------------------------------ form ------------------------------ */

function ConnectionForm({ def, conn, all, onCancel, onSaved }: { def: TypeInfo; conn: Conn | null; all: Conn[]; onCancel: () => void; onSaved: (c: Conn) => void }) {
  const { t, toast } = useMail();
  const save = useFlxMutation(FlxMailConnectionSave);
  const existing = conn as unknown as Record<string, unknown> | null;
  const servers = useMemo(() => {
    if (conn || !def.evolution) return [] as Conn[];
    const seen = new Set<string>();
    return all.filter((o) => {
      const k = o.baseUrl.replace(/\/+$/, '');
      if (o.type !== def.type || !k || seen.has(k)) return false;
      seen.add(k);
      return true;
    });
  }, [all, conn, def]);
  const [name, setName] = useState(conn?.name ?? def.label);
  const [active, setActive] = useState(conn ? conn.active : true);
  const [copyFrom, setCopyFrom] = useState<string>(servers[0] ? String(servers[0].databaseId) : '');
  const [values, setValues] = useState<Record<string, string | boolean>>(() => {
    const v: Record<string, string | boolean> = {};
    for (const f of def.fields) {
      if (f.kind === 'heading' || f.kind === 'password' || !f.name) continue;
      const cur = existing?.[f.name];
      if (f.kind === 'checkbox') v[f.name] = existing ? !!cur : f.defaultValue === '1' || f.defaultValue === 'true';
      else v[f.name] = existing ? String(cur ?? '') : f.defaultValue;
    }
    return v;
  });
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const hidden = (f: FieldDef) => !!copyFrom && (f.name === 'baseUrl' || f.name === 'apiKey');

  const submit = async () => {
    const input: FlxMailConnectionSaveInput & Record<string, unknown> = { name, active };
    if (conn) input.id = conn.databaseId;
    else {
      input.type = def.type;
      if (copyFrom) input.copyFrom = Number(copyFrom);
    }
    for (const f of def.fields) {
      if (f.kind === 'heading' || !f.name || hidden(f)) continue;
      if (f.kind === 'password') {
        if ((secrets[f.name] ?? '') !== '') input[f.name] = secrets[f.name];
      } else if (f.kind === 'checkbox') input[f.name] = !!values[f.name];
      else if (f.kind === 'number') {
        const raw = String(values[f.name] ?? '').trim();
        if (raw !== '' && Number.isFinite(Number(raw))) input[f.name] = Math.trunc(Number(raw));
      } else input[f.name] = String(values[f.name] ?? '');
    }
    try {
      const r = await save.mutateAsync({ input });
      const errs = r.flxMailConnectionSave?.errors ?? [];
      if (errs.length) {
        const byField: Record<string, string> = {};
        for (const e of errs) if (e.field) byField[e.field] = e.message;
        setErrors(byField);
        toast(errs.map((e) => e.message).join(' '), 'error');
        return;
      }
      if (r.flxMailConnectionSave?.connection) onSaved(r.flxMailConnectionSave.connection);
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  return (
    <form
      className="flxmail-card flxmail-narrow"
      autoComplete="off"
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
    >
      <h2>{def.label}</h2>
      <p className="flxmail-muted">{def.help}</p>
      {def.cloud ? <CloudLimits /> : null}
      <label className="flxmail-field">
        <span>{t('First name')}</span>
        <input type="text" value={name} onChange={(e) => setName(e.target.value)} />
      </label>
      {servers.length ? (
        <label className="flxmail-field">
          <span>{t('Server')}</span>
          <select value={copyFrom} onChange={(e) => setCopyFrom(e.target.value)}>
            <option value="">{t("I'll enter new server details")}</option>
            {servers.map((s) => (
              <option key={s.databaseId} value={s.databaseId}>
                {t('Same server: {name} ({host})', { name: s.name, host: hostOf(s.baseUrl) })}
              </option>
            ))}
          </select>
          <small>{t("When adding a second number to the same server, the address and API key aren't entered again; just type the new instance name.")}</small>
        </label>
      ) : null}
      {def.fields.map((f, i) => {
        if (f.kind === 'heading') {
          return (
            <div key={'h' + i}>
              <h3>{f.label}</h3>
              {f.help ? <p className="flxmail-muted">{f.help}</p> : null}
            </div>
          );
        }
        if (hidden(f)) return null;
        if (f.kind === 'checkbox') {
          return (
            <label key={f.name} className="flxmail-check">
              <input type="checkbox" checked={!!values[f.name]} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.checked }))} />
              <span>{f.label}</span>
            </label>
          );
        }
        const secret = f.kind === 'password' ? ((existing?.[f.name] as { set: boolean; mask: string } | undefined) ?? null) : null;
        return (
          <label key={f.name} className="flxmail-field">
            <span>
              {f.label}
              {f.required ? <em aria-hidden="true"> *</em> : null}
            </span>
            {f.kind === 'select' ? (
              <select value={String(values[f.name] ?? '')} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))}>
                {f.options.map((o) => (
                  <option key={o.key} value={o.key}>
                    {o.label}
                  </option>
                ))}
              </select>
            ) : f.kind === 'password' ? (
              <input
                type="password"
                value={secrets[f.name] ?? ''}
                autoComplete="new-password"
                placeholder={secret?.set ? secret.mask + ' · ' + t('type to change') : ''}
                required={f.required && !secret?.set}
                onChange={(e) => setSecrets((s) => ({ ...s, [f.name]: e.target.value }))}
              />
            ) : (
              <input
                type={f.kind === 'number' ? 'number' : f.kind === 'url' ? 'url' : 'text'}
                value={String(values[f.name] ?? '')}
                placeholder={f.placeholder}
                required={f.required}
                onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))}
              />
            )}
            {f.help ? <small>{f.help}</small> : null}
            {errors[f.name] ? <small className="flxmail-bad">{errors[f.name]}</small> : null}
          </label>
        );
      })}
      <label className="flxmail-check">
        <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} />
        <span>{t('Active')}</span>
      </label>
      <div className="flxmail-actions">
        <button type="submit" className="flxmail-btn is-primary" disabled={save.isPending}>
          {t('Save')}
        </button>
        <button type="button" className="flxmail-btn" onClick={onCancel}>
          {t('Cancel')}
        </button>
      </div>
    </form>
  );
}

function CloudLimits() {
  const { t } = useMail();
  return (
    <div className="flxmail-callout">
      <strong>{t("What's different in Cloud API")}</strong>
      <ul>
        <li>{t("24-hour rule: if the person hasn't written in the last 24 hours, only a Meta-approved template can be sent.")}</li>
        <li>{t("No group messages: Call Center's team group mirror doesn't work on this line.")}</li>
        <li>{t("Sent messages can't be edited; past chats can't be imported.")}</li>
        <li>{t('"Typing…" only shows while replying to an incoming message.')}</li>
        <li>{t('Cost: template messages and replies beyond 1000 a month are charged by Meta.')}</li>
        <li>{t('No ban risk; the number can stay in the WhatsApp Business app on the phone too (coexistence).')}</li>
      </ul>
    </div>
  );
}

/* --------------------------- QR / pairing --------------------------- */

function QrDialog({ conn, intro, close, setState }: { conn: Conn; intro?: string; close: () => void; setState: (id: number, s: WaState) => void }) {
  const { t } = useMail();
  const { client } = useGraphQL();
  const connect = useFlxMutation(FlxMailWaConnect);
  const [msg, setMsg] = useState<{ text: string; kind: '' | 'ok' | 'err' }>({ text: intro ?? t('Please wait…'), kind: '' });
  const [qr, setQr] = useState('');
  const [pairing, setPairing] = useState('');
  const [done, setDone] = useState(false);
  const [number, setNumber] = useState('');
  const live = useRef({ alive: true, number: '', tick: 0, timer: 0 });
  const id = conn.databaseId;

  const apply = useCallback(
    (r: { state?: string | null; qr?: string | null; pairing?: string | null; me?: string | null; name?: string | null; error?: string | null }) => {
      const st = { state: r.state ?? '', me: r.me ?? '', name: r.name ?? '', error: r.error ?? '' };
      if (st.state === 'open') {
        setDone(true);
        setQr('');
        setPairing('');
        setMsg({ text: t('Connected.') + (st.me ? ' ' + st.me : '') + (st.name ? ' (' + st.name + ')' : ''), kind: 'ok' });
        setState(id, st);
        return;
      }
      setMsg({ text: r.pairing ? t('Type this code in WhatsApp on the phone.') : t('Scan the QR with the phone.'), kind: '' });
      if (r.pairing) {
        setPairing(r.pairing.replace(/^(.{4})(.{4})$/, '$1-$2'));
        setQr('');
      } else if (r.qr) setQr(r.qr);
    },
    [id, setState, t],
  );

  const ask = useCallback(async () => {
    const r = await connect.mutateAsync({ input: { id, number: live.current.number } });
    const err = payloadError(r.flxMailWaConnect?.errors);
    if (err) throw new Error(err);
    if (r.flxMailWaConnect && live.current.alive) apply(r.flxMailWaConnect);
    return r.flxMailWaConnect?.state ?? '';
  }, [apply, connect, id]);

  // The QR changes about every 20 seconds: poll the state every 5 s and renew the QR on every 4th round.
  const poll = useCallback(() => {
    window.clearTimeout(live.current.timer);
    live.current.timer = window.setTimeout(async () => {
      if (!live.current.alive) return;
      live.current.tick += 1;
      try {
        let state: string;
        if (live.current.tick % 4 === 0 && !live.current.number) state = await ask();
        else {
          const r = await client.request(FlxMailWaState, { id, fresh: true });
          state = r.flxMailWaState.state;
          if (state === 'open' && live.current.alive) apply(r.flxMailWaState);
        }
        if (state !== 'open') poll();
      } catch (e) {
        if (live.current.alive) setMsg({ text: errorText(e, t('Something went wrong.')), kind: 'err' });
        poll();
      }
    }, 5000);
  }, [ask, apply, client, id, t]);

  useEffect(() => {
    const l = live.current;
    l.alive = true;
    void ask()
      .then((s) => {
        if (s !== 'open') poll();
      })
      .catch((e) => setMsg({ text: errorText(e, t('Something went wrong.')), kind: 'err' }));
    return () => {
      l.alive = false;
      window.clearTimeout(l.timer);
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <Modal title={conn.name || 'WhatsApp'} onClose={close}>
      <p className={'flxmail-wamsg' + (msg.kind ? ' is-' + msg.kind : '')} aria-live="polite">
        {msg.text}
      </p>
      <div className="flxmail-qr">
        {qr ? <img src={qr} alt={t('WhatsApp QR code')} width={264} height={264} /> : null}
        {pairing ? <div className="flxmail-paircode">{pairing}</div> : null}
      </div>
      {done ? null : (
        <>
          {pairing ? null : (
            <ol>
              <li>{t('Open WhatsApp on the phone.')}</li>
              <li>{t('Settings › Linked devices › Link a device.')}</li>
              <li>{t('Scan this QR. The code refreshes by itself.')}</li>
            </ol>
          )}
          <details>
            <summary>{t("Can't scan the QR: get a code with the phone number")}</summary>
            <div className="flxmail-inline">
              <input type="tel" value={number} placeholder="905301234567" aria-label={t('Phone number')} onChange={(e) => setNumber(e.target.value)} />
              <button
                type="button"
                className="flxmail-btn"
                onClick={() => {
                  const digits = number.replace(/\D/g, '');
                  if (!digits) return;
                  live.current.number = digits;
                  setMsg({ text: t('Please wait…'), kind: '' });
                  void ask()
                    .then(() => poll())
                    .catch((e) => setMsg({ text: errorText(e, t('Something went wrong.')), kind: 'err' }));
                }}
              >
                {t('Get code')}
              </button>
            </div>
            <small>{t('Type this code in WhatsApp › Linked devices › Link a device › "Link with phone number instead".')}</small>
          </details>
        </>
      )}
      <div className="flxmail-actions">
        <button type="button" className="flxmail-btn" onClick={close}>
          {t('Close')}
        </button>
      </div>
    </Modal>
  );
}

/* ------------------------- WhatsApp Cloud setup ------------------------- */

function CopyRow({ label, value }: { label: string; value: string }) {
  const { t } = useMail();
  const [copied, setCopied] = useState(false);
  return (
    <label className="flxmail-field">
      <span>{label}</span>
      <div className="flxmail-inline">
        <input type="text" readOnly value={value} onFocus={(e) => e.currentTarget.select()} />
        <button
          type="button"
          className="flxmail-btn"
          onClick={() => {
            void navigator.clipboard?.writeText(value).catch(() => undefined);
            setCopied(true);
          }}
        >
          {copied ? t('Copied') : t('Copy')}
        </button>
      </div>
    </label>
  );
}

function SetupDialog({ conn, close, setState }: { conn: Conn; close: () => void; setState: (id: number, s: WaState) => void }) {
  const { t } = useMail();
  const q = useFlxQuery(FlxMailWacloudSetup, { id: conn.databaseId }, { gcTime: 0, staleTime: 0 });
  const [showTpl, setShowTpl] = useState(false);
  const s = q.data?.flxMailWacloudSetup ?? null;
  const sid = conn.databaseId;
  useEffect(() => {
    if (!s) return;
    setState(sid, s.error ? { state: 'error', me: '', name: '', error: s.error } : { state: 'open', me: s.phone?.displayPhoneNumber ?? '', name: s.phone?.verifiedName ?? '', error: '' });
  }, [s, sid, setState]);
  const quality = { green: t('High'), yellow: t('Medium'), red: t('Low') }[(s?.phone?.qualityRating ?? '').toLowerCase()] ?? '';
  const when = (d: { label: string } | null | undefined) => d?.label ?? t('none yet');
  return (
    <Modal title={conn.name || 'WhatsApp Cloud API'} onClose={close} wide>
      {q.isLoading ? <p className="flxmail-muted">{t('Please wait…')}</p> : null}
      {q.isError ? <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p> : null}
      {q.data && !s ? <p className="flxmail-bad">{t('Cloud API connection not found.')}</p> : null}
      {s ? (
        <>
          {s.error ? (
            <p className="flxmail-wamsg is-err">{s.error}</p>
          ) : (
            <p className="flxmail-wamsg is-ok">{t('Connected.') + ' ' + (s.phone?.displayPhoneNumber ?? '') + (s.phone?.verifiedName ? ' (' + s.phone.verifiedName + ')' : '')}</p>
          )}
          {quality ? <p className="flxmail-muted">{t('Number quality') + ': ' + quality}</p> : null}
          <h3>{t('Webhook (incoming messages)')}</h3>
          <p className="flxmail-muted">
            {t('Meta for Developers › your app › WhatsApp › Configuration › Webhook: enter the address and verify token below, press "Verify and save", then subscribe to the "messages" field.')}
          </p>
          <CopyRow label={t('Callback URL')} value={s.webhookUrl} />
          <CopyRow label={t('Verify token')} value={s.webhookVerifyValue} />
          <dl className="flxmail-dl flxmail-dl--wide">
            <dt>{t('Meta verification')}</dt>
            <dd>{when(s.lastVerifyAt)}</dd>
            <dt>{t('Last incoming event')}</dt>
            <dd>{when(s.lastEventAt)}</dd>
            {s.badSignatureAt ? (
              <>
                <dt>{t('Request with a bad signature')}</dt>
                <dd className="flxmail-bad">{s.badSignatureAt.label}</dd>
              </>
            ) : null}
            {s.noSecretAt ? (
              <>
                <dt>{t('Rejected (App Secret missing)')}</dt>
                <dd className="flxmail-bad">{s.noSecretAt.label}</dd>
              </>
            ) : null}
            <dt>{t('Open 24-hour windows')}</dt>
            <dd>{s.openChats}</dd>
          </dl>
          {s.signed ? null : <p className="flxmail-callout is-warning">{t('App Secret not entered: incoming webhooks are rejected until it is entered. Add it under Edit.')}</p>}
          <div className="flxmail-actions">
            <button type="button" className="flxmail-btn" disabled={!s.hasWaba} title={s.hasWaba ? undefined : t('WABA ID is required for templates.')} onClick={() => setShowTpl((v) => !v)}>
              {t('Approved templates')}
            </button>
          </div>
          {showTpl ? <TemplateList id={sid} /> : null}
          <CloudLimits />
        </>
      ) : null}
      <div className="flxmail-actions">
        <button type="button" className="flxmail-btn" onClick={close}>
          {t('Close')}
        </button>
      </div>
    </Modal>
  );
}

function TemplateList({ id }: { id: number }) {
  const { t } = useMail();
  const q = useFlxQuery(FlxMailWacloudTemplates, { id, first: 50 }, { gcTime: 0, staleTime: 0 });
  if (q.isLoading) return <p className="flxmail-muted">{t('Please wait…')}</p>;
  if (q.isError) return <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  const nodes = q.data?.flxMailWacloudTemplates.nodes ?? [];
  if (!nodes.length) return <p className="flxmail-muted">{t('No templates. Add them in WhatsApp Manager › Message templates.')}</p>;
  return (
    <ul className="flxmail-list">
      {nodes.map((x) => (
        <li key={x.name + x.lang}>
          <strong>{x.name}</strong> <span className="flxmail-muted">{x.lang + ' · ' + x.category}</span>{' '}
          <span className={'flxmail-badge' + (x.status === 'approved' ? ' is-sent' : x.status === 'rejected' ? ' is-failed' : '')}>{x.status}</span>
          <div className="flxmail-muted">{x.body}</div>
        </li>
      ))}
    </ul>
  );
}

/* --------------------------- Flux WPAPI server --------------------------- */

function ServerDialog({ conn, close }: { conn: Conn; close: () => void }) {
  const { t, toast } = useMail();
  const q = useFlxQuery(FlxMailWaServer, { id: conn.databaseId }, { gcTime: 0, staleTime: 0 });
  const update = useFlxMutation(FlxMailWaServerUpdate);
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const server = q.data?.flxMailWaServer ?? null;
  const [form, setForm] = useState<{ publicUrl: string; keepAlive: boolean; driver: string; host: string; port: string; database: string; user: string; password: string; prefix: string } | null>(null);
  const [status, setStatus] = useState<{ text: string; kind: '' | 'ok' | 'err' }>({ text: '', kind: '' });
  const [confirmSwitch, setConfirmSwitch] = useState(false);

  useEffect(() => {
    if (!server) return;
    setForm({
      publicUrl: server.publicUrl,
      keepAlive: server.keepAlive,
      driver: server.db.driver,
      host: server.db.host || 'localhost',
      port: String(server.db.port || 3306),
      database: server.db.database,
      user: server.db.user,
      password: '',
      prefix: server.db.prefix || 'flxw_',
    });
  }, [server]);

  if (q.isLoading) return <Modal title={t('Flux WPAPI server')} onClose={close}><p className="flxmail-muted">{t('Please wait…')}</p></Modal>;
  if (q.isError || !server || !form) {
    return (
      <Modal title={t('Flux WPAPI server')} onClose={close}>
        <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p>
      </Modal>
    );
  }
  const locked = server.dbLocked;
  const set = (k: keyof typeof form, v: string | boolean) => setForm((f) => (f ? { ...f, [k]: v } : f));
  const input = (withDb: boolean, test: boolean) => {
    const db =
      withDb && !locked && (form.driver !== server.db.driver || form.driver === 'mysql')
        ? { driver: form.driver, host: form.host, port: Math.trunc(Number(form.port) || 3306), database: form.database, user: form.user, password: form.password, prefix: form.prefix }
        : test
          ? { driver: form.driver }
          : undefined;
    return { id: conn.databaseId, publicUrl: form.publicUrl, keepAlive: form.keepAlive, ...(db ? { db } : {}), ...(test ? { test: true } : {}) };
  };
  const run = (test: boolean) => {
    setStatus({ text: test ? t('Testing…') : t('Please wait…'), kind: '' });
    void update
      .mutateAsync({ input: input(true, test) })
      .then((r) => {
        const p = r.flxMailWaServerUpdate;
        const err = payloadError(p?.errors) ?? (test && p && !p.ok ? p.error : null);
        if (err) setStatus({ text: t('Error') + ': ' + err, kind: 'err' });
        else {
          setStatus({ text: test ? t('OK') : t('OK'), kind: 'ok' });
          if (!test) {
            toast(t('OK'));
            void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailWaServer, { id: conn.databaseId }) });
          }
        }
      })
      .catch((e) => setStatus({ text: t('Error') + ': ' + errorText(e, t('Something went wrong.')), kind: 'err' }));
  };
  return (
    <Modal title={t('Flux WPAPI server')} onClose={close} wide>
      <dl className="flxmail-dl flxmail-dl--wide">
        <dt>{t('Version')}</dt>
        <dd>{(server.version || '?') + ' · Baileys ' + (server.baileys || '?') + ' · Node ' + (server.node || '?')}</dd>
        <dt>{t('Data store')}</dt>
        <dd>
          {server.store === 'mysql' ? 'MySQL' : t('File')}
          {server.storeError ? <span className="flxmail-bad"> · {server.storeError}</span> : null}
        </dd>
        <dt>{t('Last keep-alive')}</dt>
        <dd>{server.lastKeepAlive ? server.lastKeepAlive.label + (server.keepAliveOk ? '' : ' · ' + (server.keepAliveError || t('Error'))) : t('none yet')}</dd>
        <dt>{t('Voice message converter')}</dt>
        <dd>{server.ffmpeg ? t('Yes') : t('None')}</dd>
      </dl>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (form.driver !== server.db.driver && !locked) setConfirmSwitch(true);
          else run(false);
        }}
      >
        <label className="flxmail-field">
          <span>{t('Bridge public address')}</span>
          <input type="url" value={form.publicUrl} placeholder="https://wa.example.com" onChange={(e) => set('publicUrl', e.target.value)} />
          <small>{t('Keep-alive sends requests to this address. E.g. https://example.org')}</small>
        </label>
        <label className="flxmail-check">
          <input type="checkbox" checked={form.keepAlive} onChange={(e) => set('keepAlive', e.target.checked)} />
          <span>{t("Keep awake (so Hostinger doesn't put the app to sleep)")}</span>
        </label>
        <fieldset className="flxmail-field" disabled={locked}>
          <legend>{t('Database')}</legend>
          {locked ? <p className="flxmail-muted">{t("The database comes from the bridge's environment variables; it can't be changed here.")}</p> : null}
          <label className="flxmail-field">
            <span>{t('Store')}</span>
            <select value={form.driver} onChange={(e) => set('driver', e.target.value)}>
              <option value="file">{t('File')}</option>
              <option value="mysql">MySQL</option>
            </select>
          </label>
          {form.driver === 'mysql' ? (
            <div className="flxmail-grid2">
              <label className="flxmail-field">
                <span>{t('Server')}</span>
                <input type="text" value={form.host} onChange={(e) => set('host', e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>Port</span>
                <input type="number" value={form.port} onChange={(e) => set('port', e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>{t('Database name')}</span>
                <input type="text" value={form.database} onChange={(e) => set('database', e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>{t('User')}</span>
                <input type="text" value={form.user} autoComplete="off" onChange={(e) => set('user', e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>{t('Password')}</span>
                <input type="password" value={form.password} autoComplete="new-password" placeholder={server.db.passwordSet ? t('Saved · type to change') : ''} onChange={(e) => set('password', e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>{t('Table prefix')}</span>
                <input type="text" value={form.prefix} onChange={(e) => set('prefix', e.target.value)} />
              </label>
            </div>
          ) : null}
          <p className="flxmail-muted">{t("The password is only sent to the bridge; it isn't stored in WordPress.")}</p>
        </fieldset>
        <div className="flxmail-actions">
          <button type="submit" className="flxmail-btn is-primary" disabled={update.isPending}>
            {t('Save')}
          </button>
          <button type="button" className="flxmail-btn" disabled={locked || update.isPending} onClick={() => run(true)}>
            {t('Test database')}
          </button>
          <button type="button" className="flxmail-btn" onClick={close}>
            {t('Close')}
          </button>
          <span className={status.kind === 'err' ? 'flxmail-bad' : status.kind === 'ok' ? 'flxmail-ok' : 'flxmail-muted'} aria-live="polite">
            {status.text}
          </span>
        </div>
      </form>
      {confirmSwitch ? (
        <Confirm
          message={t("Changing the store closes the numbers' connections and they need a new QR scan. Continue?")}
          confirm={t('Save')}
          onClose={() => setConfirmSwitch(false)}
          onYes={() => {
            setConfirmSwitch(false);
            run(false);
          }}
        />
      ) : null}
    </Modal>
  );
}

/* ------------------------------- test ------------------------------- */

type Scenario = 'text' | 'image' | 'audio' | 'document' | 'template';
interface Tpl {
  name: string;
  lang: string;
  status: string;
  body: string;
  params: number;
}

function TestDialog({ conn, info, state, close, reload }: { conn: Conn; info: Meta; state: WaState | null; close: () => void; reload: () => void }) {
  const { t } = useMail();
  const host = useHost();
  const test = useFlxMutation(FlxMailConnectionTest);
  const { client } = useGraphQL();
  const scenarios = conn.scenarios.filter((s) => s !== 'check') as Scenario[];
  const wa = conn.type !== 'netgsm' && scenarios.length > 1;
  const site = host.siteName ?? '';
  const [to, setTo] = useState(() => {
    try {
      return window.localStorage.getItem('flxi-test-to') ?? '';
    } catch {
      return '';
    }
  });
  const [sel, setSel] = useState<Record<string, boolean>>({ text: true });
  const [text, setText] = useState(wa ? t('Hello, this is a {site} test message. 🌱', { site }) : t('Test SMS · {site}', { site }));
  const [caption, setCaption] = useState(t('Test image · {site}', { site }));
  const [tpls, setTpls] = useState<Tpl[] | null>(null);
  const [tplErr, setTplErr] = useState('');
  const [tplIdx, setTplIdx] = useState('');
  const [params, setParams] = useState<string[]>([]);
  const [check, setCheck] = useState<{ text: string; kind: '' | 'ok' | 'err' }>({ text: '', kind: '' });
  const [results, setResults] = useState<Record<string, { state: 'sending' | 'ok' | 'err'; error?: string }>>({});
  const [summary, setSummary] = useState<{ text: string; kind: '' | 'ok' | 'err' }>({ text: '', kind: '' });
  const [busy, setBusy] = useState(false);

  const chosen = scenarios.filter((s) => sel[s]);
  const tpl = tplIdx === '' || !tpls ? null : (tpls[Number(tplIdx)] ?? null);

  useEffect(() => {
    if (!sel.template || tpls || tplErr) return;
    void client
      .request(FlxMailWacloudTemplates, { id: conn.databaseId, first: 50 })
      .then((r) => setTpls(r.flxMailWacloudTemplates.nodes.filter((x) => x.status === 'approved')))
      .catch((e) => {
        setTpls([]);
        setTplErr(errorText(e, t('Something went wrong.')));
      });
  }, [sel.template]); // eslint-disable-line react-hooks/exhaustive-deps

  const tplText = () => {
    if (!tpl) return '';
    let body = tpl.body || tpl.name;
    for (let i = 0; i < tpl.params; i++) body = body.split('{{' + (i + 1) + '}}').join(params[i] || '{{' + (i + 1) + '}}');
    return body;
  };

  const runCheck = async () => {
    setCheck({ text: t('Testing…'), kind: '' });
    try {
      const r = await test.mutateAsync({ input: { id: conn.databaseId } });
      const p = r.flxMailConnectionTest;
      const err = payloadError(p?.errors) ?? (p && !p.ok ? p.error : null);
      setCheck(err ? { text: t('Error') + ': ' + err, kind: 'err' } : { text: t('OK'), kind: 'ok' });
      reload();
    } catch (e) {
      setCheck({ text: t('Error') + ': ' + errorText(e, t('Something went wrong.')), kind: 'err' });
    }
  };

  const send = async () => {
    if (!to.trim()) {
      setSummary({ text: t('Enter the recipient number first.'), kind: 'err' });
      return;
    }
    try {
      window.localStorage.setItem('flxi-test-to', to.trim());
    } catch {
      /* private window */
    }
    setBusy(true);
    setResults({});
    let okN = 0;
    for (let i = 0; i < chosen.length; i++) {
      const k = chosen[i] ?? 'text';
      setResults((r) => ({ ...r, [k]: { state: 'sending' } }));
      setSummary({ text: t('Sending…') + ' ' + (i + 1) + '/' + chosen.length, kind: '' });
      try {
        const r = await test.mutateAsync({
          input: {
            id: conn.databaseId,
            to: to.trim(),
            scenario: k,
            text,
            caption,
            ...(k === 'template' && tpl ? { template: { name: tpl.name, lang: tpl.lang, params: tpl.params ? params.slice(0, tpl.params).map((x) => x ?? '') : [] } } : {}),
          },
        });
        const p = r.flxMailConnectionTest;
        const err = payloadError(p?.errors) ?? (p && !p.ok ? p.error || t('Something went wrong.') : null);
        if (err) setResults((m) => ({ ...m, [k]: { state: 'err', error: err } }));
        else {
          okN++;
          setResults((m) => ({ ...m, [k]: { state: 'ok' } }));
        }
      } catch (e) {
        setResults((m) => ({ ...m, [k]: { state: 'err', error: errorText(e, t('Something went wrong.')) } }));
      }
    }
    setSummary({ text: t('{ok} / {total} sent.', { ok: okN, total: chosen.length }) + (okN ? ' ' + t('Check the phone.') : ''), kind: okN === chosen.length ? 'ok' : 'err' });
    setBusy(false);
    reload();
  };

  const names: Record<string, string> = { text: t('Text'), image: t('Image'), audio: t('Voice message'), document: t('Document (PDF)'), template: t('Layout') };
  const clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  return (
    <Modal title={t('Test') + ' · ' + conn.name} onClose={close} wide>
      <p className="flxmail-muted">{conn.typeLabel}</p>
      <div className="flxmail-actions">
        <button type="button" className="flxmail-btn" disabled={test.isPending} onClick={() => void runCheck()}>
          {t('Check connection')}
        </button>
        <span className={check.kind === 'err' ? 'flxmail-bad' : check.kind === 'ok' ? 'flxmail-ok' : 'flxmail-muted'} aria-live="polite">
          {check.text}
        </span>
        <small className="flxmail-muted">{t('Sends no message; only checks the connection.')}</small>
      </div>
      {wa && conn.type !== 'wacloud' && state && state.state && state.state !== 'open' ? (
        <p className="flxmail-callout is-warning">{t('The number is not connected; messages will not go out. Scan the QR with "Connect" first.')}</p>
      ) : null}
      {conn.type === 'wacloud' ? (
        <p className="flxmail-callout">
          {t('24-hour rule: if the person has not written to this line in the last 24 hours, text, image, audio and documents will not go out; only approved templates. Send a message to this line from that number first.')}
        </p>
      ) : null}
      {wa ? null : <p className="flxmail-callout">{t('SMS is paid; each message is deducted from the balance.')}</p>}
      <div className="flxmail-split">
        <div>
          <label className="flxmail-field">
            <span>{t('Recipient number')}</span>
            <input type="tel" inputMode="tel" value={to} placeholder="905301234567" onChange={(e) => setTo(e.target.value)} />
            <small className="flxmail-muted">{t('With country code, e.g. 905301234567. Messages go to this number.')}</small>
          </label>
          {wa ? (
            <fieldset className="flxmail-field">
              <legend>{t('What to send?')}</legend>
              <div className="flxmail-chips">
                {scenarios.map((k) => (
                  <label key={k} className="flxmail-check">
                    <input type="checkbox" checked={!!sel[k]} onChange={(e) => setSel((s) => ({ ...s, [k]: e.target.checked }))} />
                    <span>{names[k]}</span>
                  </label>
                ))}
              </div>
            </fieldset>
          ) : null}
          {sel.text ? (
            <label className="flxmail-field">
              <span>{t('Message text')}</span>
              <textarea rows={3} value={text} onChange={(e) => setText(e.target.value)} />
            </label>
          ) : null}
          {sel.image || sel.document ? (
            <label className="flxmail-field">
              <span>{t('Image / document caption')}</span>
              <input type="text" value={caption} onChange={(e) => setCaption(e.target.value)} />
            </label>
          ) : null}
          {sel.template ? (
            <div className="flxmail-field">
              <span>{t('Layout')}</span>
              <select
                value={tplIdx}
                onChange={(e) => {
                  setTplIdx(e.target.value);
                  setParams([]);
                }}
              >
                <option value="">{tpls ? t('Choose a template') : t('Loading templates…')}</option>
                {(tpls ?? []).map((x, i) => (
                  <option key={x.name + x.lang} value={i}>
                    {x.name + ' · ' + x.lang}
                  </option>
                ))}
              </select>
              {tpl
                ? Array.from({ length: tpl.params }, (_, i) => (
                    <input
                      key={i}
                      type="text"
                      value={params[i] ?? ''}
                      placeholder={t('Variable {{{n}}}', { n: i + 1 })}
                      onChange={(e) =>
                        setParams((p) => {
                          const n = [...p];
                          n[i] = e.target.value;
                          return n;
                        })
                      }
                    />
                  ))
                : null}
              {tpls && !tpls.length ? <small className={tplErr ? 'flxmail-bad' : 'flxmail-muted'}>{tplErr || t('No approved templates.')}</small> : null}
            </div>
          ) : null}
        </div>
        <div className={'flxmail-phone' + (wa ? '' : ' is-sms')}>
          <div className="flxmail-phonehead">
            {t('Preview')}
            {wa ? '' : ' · ' + t('SMS')}
          </div>
          <div className="flxmail-chat">
            {chosen.length ? (
              chosen.map((k) => {
                const r = results[k];
                return (
                  <div key={k} className={'flxmail-bubble' + (r ? ' is-' + r.state : '')}>
                    {k === 'text' ? <div>{text}</div> : null}
                    {k === 'image' ? (
                      <>
                        <img src={info.sampleImageUrl} alt="" className="flxmail-bubbleimg" />
                        {caption ? <div>{caption}</div> : null}
                      </>
                    ) : null}
                    {k === 'audio' ? <audio controls preload="none" src={info.sampleAudioUrl} /> : null}
                    {k === 'document' ? (
                      <>
                        <div>
                          <strong>test-belgesi.pdf</strong> <small>{t('1 page · PDF')}</small>
                        </div>
                        {caption ? <div>{caption}</div> : null}
                      </>
                    ) : null}
                    {k === 'template' ? <div>{tpl ? tplText() : <em>{t('Choose a template')}</em>}</div> : null}
                    <span className="flxmail-bubblemeta">
                      {clock} {r?.state === 'sending' ? '🕓' : r?.state === 'ok' ? '✓✓' : r?.state === 'err' ? '✕' : ''}
                    </span>
                    {r?.state === 'err' ? <div className="flxmail-bad">{r.error}</div> : null}
                  </div>
                );
              })
            ) : (
              <p className="flxmail-muted">{t('Pick at least one scenario.')}</p>
            )}
          </div>
        </div>
      </div>
      <div className="flxmail-actions">
        <span className={summary.kind === 'err' ? 'flxmail-bad' : summary.kind === 'ok' ? 'flxmail-ok' : 'flxmail-muted'} aria-live="polite">
          {summary.text}
        </span>
        <button type="button" className="flxmail-btn is-primary" disabled={busy || !chosen.length} onClick={() => void send()}>
          {chosen.length > 1 ? t('Send {n} messages', { n: chosen.length }) : t('Send')}
        </button>
        <button type="button" className="flxmail-btn" onClick={close}>
          {t('Close')}
        </button>
      </div>
    </Modal>
  );
}
