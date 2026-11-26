// Accounts: connect (OAuth stays on REST, the browser comes back here), pick step, WhatsApp targets,
// rename, switch off, reconnect, refresh, reorder and remove.
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxSocialAccountDelete,
  FlxSocialAccountRefresh,
  FlxSocialAccountReorder,
  FlxSocialAccountUpdate,
  FlxSocialConnectUrl,
  FlxSocialSettings,
  FlxSocialWhatsAppAdd,
  FlxSocialWhatsAppTargets,
} from '@fluxwp/graphql/operations';
import { Icon, Loading, Modal } from '@fluxwp/ui';
import { createPortal } from 'react-dom';
import { useHost } from '../host';
import { Avatar, NetDot, errorText, payloadError, useSocial, type Account, type Query } from './common';

interface PickItem {
  id: string;
  provider: string;
  network: string;
  name: string;
  username: string;
  avatar: string;
  kind: string;
}

export function AccountsPage({ query }: { query: Query }) {
  const s = useSocial();
  const { t, accounts, boot } = s;
  const cfg = boot.flxSocialConfig;
  const host = useHost();
  const { client } = useGraphQL();
  const qc = useQueryClient();
  const [rename, setRename] = useState<Account | null>(null);
  const [wa, setWa] = useState(false);
  const [pick, setPick] = useState<string | null>(null);
  const [notice, setNotice] = useState<{ text: string; kind: 'ok' | 'error' } | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const apps = useFlxQuery(FlxSocialSettings, {}, { enabled: cfg.canManage });

  // The OAuth callback returns here with #/accounts?added=1|updated=N|pick=KEY|error=…
  useEffect(() => {
    if (query.error) setNotice({ text: query.error, kind: 'error' });
    else if (query.pick) setPick(query.pick);
    else if (query.added || query.updated) {
      setNotice({ text: query.updated && !query.added ? t('{n} accounts updated.', { n: Number(query.updated) || 1 }) : t('Account connected.'), kind: 'ok' });
      s.reloadBoot();
    }
    if (query.error || query.pick || query.added || query.updated) s.go('accounts');
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const done = () => {
    s.reloadBoot();
    void qc.invalidateQueries({ queryKey: [host.scope, 'gql', 'FlxSocialAccounts'] });
  };
  const fail = (e: unknown) => s.toast(errorText(e, t('Something went wrong.')), 'error');
  const upd = useFlxMutation(FlxSocialAccountUpdate, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialAccountUpdate?.errors);
      if (err) s.toast(err, 'error');
      else done();
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxSocialAccountDelete, { onSuccess: done, onError: fail });
  const refresh = useFlxMutation(FlxSocialAccountRefresh, {
    onSuccess: (r) => {
      const a = r.flxSocialAccountRefresh?.account;
      const err = payloadError(r.flxSocialAccountRefresh?.errors);
      if (err) s.toast(err, 'error');
      else s.toast(a?.status === 'EXPIRED' ? t('The connection expired; connect again.') : t('Connection works.'), a?.status === 'EXPIRED' ? 'error' : 'ok');
      done();
      setBusy(null);
    },
    onError: (e) => {
      setBusy(null);
      fail(e);
    },
  });
  const order = useFlxMutation(FlxSocialAccountReorder, { onSuccess: done, onError: fail });

  const connect = async (forKey: string, provider: string, local: boolean) => {
    if (local || provider === 'whatsapp') {
      setWa(true);
      return;
    }
    try {
      const r = await client.request(FlxSocialConnectUrl, { for: forKey });
      const url = r.flxSocialConnectUrl;
      if (!url) throw new Error(t('Something went wrong.'));
      if (s.appMode) s.openUrl(url);
      else window.location.href = url;
    } catch (e) {
      fail(e);
    }
  };

  const move = (i: number, d: number) => {
    const ids = accounts.map((a) => a.id);
    const j = i + d;
    if (j < 0 || j >= ids.length) return;
    [ids[i], ids[j]] = [ids[j]!, ids[i]!];
    order.mutate({ input: { ids } });
  };

  const [connOpen, setConnOpen] = useState(false);
  const head =
    cfg.canManage && s.topSlot
      ? createPortal(
          <button type="button" className="flxsm-btn is-primary" onClick={() => setConnOpen(true)}>
            <Icon name="plus-alt" size={15} /> {t('Connect account')}
          </button>,
          s.topSlot,
        )
      : null;

  return (
    <div className="flxsm-accounts">
      {notice && (
        <div className={'flxsm-notice is-' + notice.kind}>
          <span>{notice.text}</span>
          <button type="button" className="flxsm-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
            ×
          </button>
        </div>
      )}
      {head}
      {connOpen && (
        <Modal title={t('Connect account')} onClose={() => setConnOpen(false)}>
          <p className="flxsm-muted">{t('The network sign-in page opens; after you allow access you come back here. If there are several pages or channels you choose which to add.')}</p>
          {apps.isLoading ? (
            <Loading />
          ) : (
            <div className="flxsm-connect">
              {(apps.data?.flxSocialApps ?? []).flatMap((app) =>
                app.connect.map((c) => (
                  <button
                    key={app.id + c.for}
                    type="button"
                    className={'flxsm-conn' + (app.ready ? '' : ' is-off')}
                    onClick={() => {
                      setConnOpen(false);
                      if (!app.ready) s.go('settings', { tab: 'apps' });
                      else void connect(c.for, c.provider, c.local);
                    }}
                  >
                    <NetDot provider={c.provider} size={30} />
                    <span className="flxsm-conn-l">
                      <strong>{c.label}</strong>
                      <small>{app.ready ? app.label : t('Enter the keys first: {app}', { app: app.label })}</small>
                    </span>
                    <Icon name={app.ready ? 'external' : 'key'} size={16} />
                  </button>
                )),
              )}
            </div>
          )}
        </Modal>
      )}

      {!accounts.length ? (
        <div className="flxsm-empty">
          <Icon name="users" size={30} />
          <h3>{t('No accounts yet')}</h3>
          <p>{cfg.canManage ? t('First enter the network keys in Settings › Apps, then connect the account here.') : t('Accounts are connected by an administrator.')}</p>
          {cfg.canManage && (
            <div className="flxsm-row">
              <button type="button" className="flxsm-btn is-primary" onClick={() => setConnOpen(true)}>
                <Icon name="plus-alt" size={15} /> {t('Connect account')}
              </button>
              <button type="button" className="flxsm-btn" onClick={() => s.go('settings', { tab: 'apps' })}>
                <Icon name="key" size={15} /> {t('App keys')}
              </button>
            </div>
          )}
        </div>
      ) : (
        <ul className="flxsm-acc-list">
          {accounts.map((a, i) => (
            <li key={a.id} className={'flxsm-card flxsm-acc is-' + a.status.toLowerCase()}>
              <div className="flxsm-acc-main">
                <Avatar src={a.avatar} name={a.name} size={40} />
                <div className="flxsm-acc-t">
                  <strong>{a.name}</strong>
                  <small className="flxsm-muted">
                    {a.network}
                    {a.username ? ' · @' + a.username.replace(/^@/, '') : ''}
                    {a.kind ? ' · ' + a.kind : ''}
                    {a.via && a.via !== a.provider ? ' · ' + a.via : ''}
                  </small>
                  {a.status === 'EXPIRED' && <small className="flxsm-bad">{t('The connection expired; connect again.')}</small>}
                  {a.status === 'DISABLED' && <small className="flxsm-muted">{t('Switched off')}</small>}
                  {a.lastError && a.status !== 'EXPIRED' && <small className="flxsm-bad">{a.lastError}</small>}
                  {a.expires?.timestamp ? <small className="flxsm-muted">{t('Connection valid until {date}', { date: s.fmt(a.expires.timestamp, 'date') })}</small> : null}
                </div>
              </div>
              <div className="flxsm-acc-act">
                <button type="button" className="flxsm-btn is-ghost" aria-label={t('Move up')} disabled={i === 0 || order.isPending} onClick={() => move(i, -1)}>
                  ↑
                </button>
                <button type="button" className="flxsm-btn is-ghost" aria-label={t('Move down')} disabled={i === accounts.length - 1 || order.isPending} onClick={() => move(i, 1)}>
                  ↓
                </button>
                <button type="button" className="flxsm-btn" onClick={() => setRename(a)}>
                  {t('Rename')}
                </button>
                <button
                  type="button"
                  className="flxsm-btn"
                  disabled={busy === a.id}
                  onClick={() => {
                    setBusy(a.id);
                    refresh.mutate({ input: { id: a.id } });
                  }}
                >
                  {busy === a.id ? t('Checking…') : t('Test connection')}
                </button>
                {cfg.canManage && a.status === 'EXPIRED' && (
                  <button type="button" className="flxsm-btn is-primary" onClick={() => void connect(a.via || a.provider, a.provider, a.provider === 'whatsapp')}>
                    {t('Reconnect')}
                  </button>
                )}
                <button type="button" className="flxsm-btn" onClick={() => upd.mutate({ input: { id: a.id, disabled: a.status !== 'DISABLED' } })}>
                  {a.status === 'DISABLED' ? t('Switch on') : t('Switch off')}
                </button>
                <button
                  type="button"
                  className="flxsm-btn is-danger"
                  onClick={() =>
                    s.confirm({
                      title: t('Remove account'),
                      text: t('{name} is removed from the panel. Posts already shared stay on the network.', { name: a.name }),
                      action: t('Remove'),
                      danger: true,
                      run: () => del.mutate({ input: { id: a.id } }),
                    })
                  }
                >
                  {t('Remove')}
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {rename && (
        <RenameModal
          account={rename}
          onClose={() => setRename(null)}
          onSave={(name) => {
            upd.mutate({ input: { id: rename.id, name } }, { onSuccess: () => setRename(null) });
          }}
        />
      )}
      {wa && (
        <WhatsAppModal
          onClose={() => setWa(false)}
          onAdded={() => {
            setWa(false);
            done();
          }}
        />
      )}
      {pick && (
        <PickModal
          keyId={pick}
          onClose={() => setPick(null)}
          onDone={(n) => {
            setPick(null);
            setNotice({ text: t('{n} accounts added.', { n }), kind: 'ok' });
            done();
          }}
        />
      )}
    </div>
  );
}

function RenameModal({ account, onClose, onSave }: { account: Account; onClose: () => void; onSave: (name: string) => void }) {
  const { t } = useSocial();
  const [name, setName] = useState(account.name);
  return (
    <Modal title={t('Rename account')} onClose={onClose}>
      <label className="flxsm-field">
        <span>{t('Name')}</span>
        <input type="text" value={name} autoFocus onChange={(e) => setName(e.target.value)} />
      </label>
      <div className="flxsm-actions">
        <button type="button" className="flxsm-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        <button type="button" className="flxsm-btn is-primary" disabled={!name.trim()} onClick={() => onSave(name.trim())}>
          {t('Save')}
        </button>
      </div>
    </Modal>
  );
}

function WhatsAppModal({ onClose, onAdded }: { onClose: () => void; onAdded: () => void }) {
  const s = useSocial();
  const { t } = s;
  const q = useFlxQuery(FlxSocialWhatsAppTargets, {});
  const [sel, setSel] = useState<string[]>([]);
  const add = useFlxMutation(FlxSocialWhatsAppAdd, {
    onSuccess: (r) => {
      const err = payloadError(r.flxSocialWhatsAppAdd?.errors);
      if (err) s.toast(err, 'error');
      else {
        s.toast(t('{n} accounts added.', { n: r.flxSocialWhatsAppAdd?.added ?? 0 }));
        onAdded();
      }
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
  const items = q.data?.flxSocialWhatsAppTargets ?? [];
  return (
    <Modal title={t('Add WhatsApp groups or Status')} onClose={onClose}>
      {q.isLoading ? (
        <Loading />
      ) : q.isError ? (
        <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>
      ) : !items.length ? (
        <p className="flxsm-muted">{t('No WhatsApp groups found. Check the WhatsApp connection in Settings.')}</p>
      ) : (
        <ul className="flxsm-pick">
          {items.map((i) => (
            <li key={i.extId}>
              <label className="flxsm-chk">
                <input type="checkbox" disabled={i.exists} checked={i.exists || sel.includes(i.extId)} onChange={(e) => setSel(e.target.checked ? [...sel, i.extId] : sel.filter((x) => x !== i.extId))} />
                <Avatar src={i.avatar} name={i.name} size={28} />
                <span>{i.name}</span>
                {i.exists && <small className="flxsm-muted">{t('Added')}</small>}
              </label>
            </li>
          ))}
        </ul>
      )}
      <div className="flxsm-actions">
        <button type="button" className="flxsm-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        <button
          type="button"
          className="flxsm-btn is-primary"
          disabled={!sel.length || add.isPending}
          onClick={() => add.mutate({ input: { items: items.filter((i) => sel.includes(i.extId)).map((i) => ({ extId: i.extId, name: i.name, kind: i.kind, avatar: i.avatar })) } })}
        >
          {t('Add')}
        </button>
      </div>
    </Modal>
  );
}

/** Pages / channels the network returned after OAuth (REST: the list lives in a short-lived server transient). */
function PickModal({ keyId, onClose, onDone }: { keyId: string; onClose: () => void; onDone: (n: number) => void }) {
  const s = useSocial();
  const { t } = s;
  const host = useHost();
  const [items, setItems] = useState<PickItem[] | null>(null);
  const [err, setErr] = useState('');
  const [sel, setSel] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    host
      .client<{ items: PickItem[] }>('flx-social/v1/oauth/pick/' + encodeURIComponent(keyId))
      .then((r) => {
        setItems(r.items);
        setSel(r.items.map((i) => i.id));
      })
      .catch((e: unknown) => setErr(errorText(e, t('The selection list expired; please reconnect.'))));
  }, [keyId]); // eslint-disable-line react-hooks/exhaustive-deps
  const save = () => {
    setBusy(true);
    host
      .client<{ added: number }>('flx-social/v1/oauth/pick/' + encodeURIComponent(keyId), { method: 'POST', body: { ids: sel } })
      .then((r) => onDone(r.added))
      .catch((e: unknown) => {
        setBusy(false);
        s.toast(errorText(e, t('Something went wrong.')), 'error');
      });
  };
  return (
    <Modal title={t('Choose accounts to add')} onClose={onClose}>
      {err ? (
        <div className="flxsm-notice is-error">{err}</div>
      ) : !items ? (
        <Loading />
      ) : (
        <ul className="flxsm-pick">
          {items.map((i) => (
            <li key={i.id}>
              <label className="flxsm-chk">
                <input type="checkbox" checked={sel.includes(i.id)} onChange={(e) => setSel(e.target.checked ? [...sel, i.id] : sel.filter((x) => x !== i.id))} />
                <Avatar src={i.avatar} name={i.name} size={28} />
                <span>
                  {i.name}
                  <small className="flxsm-muted">
                    {' '}
                    {i.network}
                    {i.username ? ' · @' + i.username.replace(/^@/, '') : ''}
                    {i.kind ? ' · ' + i.kind : ''}
                  </small>
                </span>
              </label>
            </li>
          ))}
        </ul>
      )}
      <div className="flxsm-actions">
        <button type="button" className="flxsm-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        <button type="button" className="flxsm-btn is-primary" disabled={!sel.length || busy} onClick={save}>
          {t('Add')}
        </button>
      </div>
    </Modal>
  );
}
