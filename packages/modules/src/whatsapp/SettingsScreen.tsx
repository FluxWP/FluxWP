// Flux Chat settings: every tab of the classic page (connections, general, auto replies, labels, records,
// AI and assistant, system check) over Flux Chat GraphQL. A tab sends only the fields it changed, so a save
// cannot overwrite what another tab or the classic page wrote; limits and cleaning are the PHP service's.
// The API key is write-only: an empty box keeps the saved one, "Remove key" clears it.
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxChatAiModels, FlxChatAiTest, FlxChatConnectionImport, FlxChatConnectionQr, FlxChatConnectionTest, FlxChatConnectionWebhook,
  FlxChatGroups, FlxChatSettings, FlxChatSettingsSave, FlxChatSystemChecks, FlxChatWebhookUrlReveal,
} from '@fluxwp/graphql/operations';
import type { FlxChatSettingsQuery, FlxChatSettingsSaveInput } from '@fluxwp/graphql/types';
import { Check, Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { AdminRoot, Card, errorText, Field, NoticeBar, useAdminT, type Notice } from './admin-common';
import type { Translate } from './admin-i18n';

type S = FlxChatSettingsQuery['flxChatSettings'];
type Line = S['connections'][number];
type Draft = Record<string, unknown>;

const versionOld = (v: string): boolean => {
  const [a = 0, b = 0] = v.split('.').map((x) => Number.parseInt(x, 10) || 0);
  return a < 2 || (a === 2 && b < 3);
};

const TABS = ['baglantilar', 'genel', 'otomatik', 'etiketler', 'kayitlar', 'ai', 'kontrol'] as const;
type Tab = (typeof TABS)[number];

export function SettingsScreen() {
  return (
    <AdminRoot>
      <Settings />
    </AdminRoot>
  );
}

function Settings() {
  const host = useHost();
  const t = useAdminT();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxChatSettings, {});
  const [tab, setTabState] = useState<Tab>(() => (TABS as readonly string[]).includes(host.startPage ?? '') ? (host.startPage as Tab) : 'baglantilar');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [draft, setDraft] = useState<Draft>({});

  const setTab = (next: Tab) => {
    setTabState(next);
    setDraft({});
    setNotice(null);
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('tab', next);
      window.history.replaceState(window.history.state, '', url.toString());
    } catch {
      /* the address bar is cosmetic */
    }
  };

  const put = (s: S) => qc.setQueryData<FlxChatSettingsQuery>(flxQueryKey(scope, FlxChatSettings, {}), { flxChatSettings: s });
  const save = useFlxMutation(FlxChatSettingsSave, {
    onSuccess: (r) => {
      const p = r.flxChatSettingsSave;
      if (!p) return setNotice({ kind: 'bad', text: t('The action could not be completed.') });
      if (p.errors.length) return setNotice({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      put(p.settings);
      setDraft({});
      setNotice({ kind: 'ok', text: t('Settings saved.') });
    },
    onError: (e) => setNotice({ kind: 'bad', text: errorText(e, t) }),
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxca-app"><div className="flxca-notice is-bad">{errorText(q.error, t)}</div></div>;
  const s = q.data.flxChatSettings;
  const ctx = s.context;

  const val = (k: string): unknown => (k in draft ? draft[k] : (s as unknown as Record<string, unknown>)[k]);
  const set = (k: string, v: unknown) => setDraft((d) => ({ ...d, [k]: v }));
  const dirty = Object.keys(draft).length > 0;
  const submit = () => {
    const input = toInput(draft);
    save.mutate({ input });
  };
  const labels: Record<Tab, string> = {
    baglantilar: t('Connections'),
    genel: t('General'),
    otomatik: t('Auto replies'),
    etiketler: t('Labels'),
    kayitlar: t('Records'),
    ai: t('AI and assistant'),
    kontrol: t('System check'),
  };
  const bag: Bag = { s, val, set, t, ctx };

  return (
    <div className="flxca-app">
      <header className="flxca-head">
        <div>
          <h1>{t('Call Center Settings')}</h1>
          <p className="flxca-lead">{t('Connect your WhatsApp line, manage auto replies and labels.')}</p>
        </div>
        <a className="flxca-btn" href={ctx.chatsUrl}>{t('Open chats')} →</a>
      </header>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <nav className="flxca-tabs" role="tablist">
        {TABS.map((k) => (
          <button key={k} type="button" role="tab" aria-selected={k === tab} className={k === tab ? 'is-on' : ''} onClick={() => setTab(k)}>
            {labels[k]}
          </button>
        ))}
      </nav>
      {tab === 'baglantilar' && <ConnectionsTab s={s} t={t} busy={save.isPending} onSave={(connections) => save.mutate({ input: { connections } })} setNotice={setNotice} />}
      {tab === 'genel' && <GeneralTab bag={bag} />}
      {tab === 'otomatik' && <AutoTab bag={bag} />}
      {tab === 'etiketler' && <LabelsTab s={s} t={t} busy={save.isPending} onSave={(labelsIn) => save.mutate({ input: { labels: labelsIn } })} />}
      {tab === 'kayitlar' && <RecordsTab bag={bag} />}
      {tab === 'ai' && <AiTab bag={bag} />}
      {tab === 'kontrol' && <ChecksTab t={t} onTab={(k) => (TABS as readonly string[]).includes(k) && setTab(k as Tab)} />}
      {['genel', 'otomatik', 'kayitlar', 'ai'].includes(tab) && (
        <div className="flxca-save">
          <button type="button" className="flxca-btn is-primary" disabled={save.isPending || !dirty} onClick={submit}>
            {t('Save')}
          </button>
        </div>
      )}
    </div>
  );
}

interface Bag {
  s: S;
  ctx: S['context'];
  val: (k: string) => unknown;
  set: (k: string, v: unknown) => void;
  t: Translate;
}

const INT_KEYS = new Set([
  'pollInterval', 'deleteEveryoneHours', 'awayThrottle', 'botContextHours', 'botPauseHours', 'botDailyLimit', 'memAfterHours', 'workersMax',
  'workersReserved', 'botTimeBudget', 'remMax', 'verifyTtl', 'verifyMaxFails', 'botFallbackWait', 'staffCertMax', 'botVoiceMax',
]);

/** The changed fields as the mutation input: numbers typed in a box are numbers again. */
function toInput(draft: Draft): FlxChatSettingsSaveInput {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(draft)) {
    if (INT_KEYS.has(k)) out[k] = Number.parseInt(String(v), 10) || 0;
    else if (k === 'aiTemperature') out[k] = Number.parseFloat(String(v).replace(',', '.')) || 0;
    else out[k] = v;
  }
  return out as FlxChatSettingsSaveInput;
}

/* ---------- field helpers ---------- */

function Txt({ bag, k, label, note, ph, type = 'text', min, max, step, className }: { bag: Bag; k: string; label: string; note?: ReactNode; ph?: string; type?: 'text' | 'url' | 'number' | 'time' | 'color'; min?: number; max?: number; step?: number; className?: string }) {
  return (
    <Field label={label} note={note} className={className}>
      <input type={type} value={String(bag.val(k) ?? '')} placeholder={ph} min={min} max={max} step={step} onChange={(e) => bag.set(k, e.currentTarget.value)} />
    </Field>
  );
}

function Area({ bag, k, label, note, ph, rows = 3 }: { bag: Bag; k: string; label?: string; note?: ReactNode; ph?: string; rows?: number }) {
  return (
    <Field label={label} note={note}>
      <textarea rows={rows} value={String(bag.val(k) ?? '')} placeholder={ph} onChange={(e) => bag.set(k, e.currentTarget.value)} />
    </Field>
  );
}

function Tick({ bag, k, children, note, strong, disabled }: { bag: Bag; k: string; children: ReactNode; note?: ReactNode; strong?: boolean; disabled?: boolean }) {
  return (
    <Check checked={Boolean(bag.val(k))} disabled={disabled} note={note} onChange={(v) => bag.set(k, v)}>
      {strong ? <strong>{children}</strong> : children}
    </Check>
  );
}

function Select({ bag, k, label, note, options }: { bag: Bag; k: string; label: string; note?: ReactNode; options: [string, string][] }) {
  return (
    <Field label={label} note={note}>
      <select value={String(bag.val(k) ?? '')} onChange={(e) => bag.set(k, e.currentTarget.value)}>
        {options.map(([v, l]) => (
          <option key={v} value={v}>{l}</option>
        ))}
      </select>
    </Field>
  );
}

/* ---------- connections ---------- */

interface LineDraft {
  uid: string;
  id: string;
  label: string;
  source: string;
  baseUrl: string;
  instance: string;
  apiKey: string;
  clearKey: boolean;
  rotate: boolean;
  hasApiKey: boolean;
  line: Line | null;
}

type Out = { kind: 'ok' | 'warn' | 'bad'; body: ReactNode };

function ConnectionsTab({ s, t, busy, onSave, setNotice }: { s: S; t: Translate; busy: boolean; onSave: (c: { id: string; label: string; source: string; baseUrl: string; instance: string; apiKey: string; rotateSecret: boolean }[]) => void; setNotice: (n: Notice | null) => void }) {
  const ctx = s.context;
  const fromSaved = (): LineDraft[] =>
    s.connections.map((c) => ({ uid: c.id, id: c.id, label: c.label, source: c.sourceValue, baseUrl: c.baseUrl, instance: c.instance, apiKey: '', clearKey: false, rotate: false, hasApiKey: c.hasApiKey, line: c }));
  const [lines, setLines] = useState<LineDraft[]>(fromSaved);
  const [outs, setOuts] = useState<Record<string, Out>>({});
  const [urls, setUrls] = useState<Record<string, string>>({});
  const [busyId, setBusyId] = useState('');
  useEffect(() => {
    setLines(fromSaved());
    setUrls({});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [s.connections]);
  const patch = (uid: string, p: Partial<LineDraft>) => setLines((ls) => ls.map((l) => (l.uid === uid ? { ...l, ...p } : l)));
  const say = (id: string, out: Out | null) => setOuts((o) => {
    const n = { ...o };
    if (out) n[id] = out;
    else delete n[id];
    return n;
  });

  const test = useFlxMutation(FlxChatConnectionTest, {});
  const hook = useFlxMutation(FlxChatConnectionWebhook, {});
  const qr = useFlxMutation(FlxChatConnectionQr, {});
  const imp = useFlxMutation(FlxChatConnectionImport, {});
  const reveal = useFlxMutation(FlxChatWebhookUrlReveal, {});

  const run = async (id: string, act: 'test' | 'qr' | 'webhook' | 'import') => {
    setBusyId(id + act);
    say(id, { kind: 'warn', body: act === 'import' ? t('Importing recent chats, this may take a while…') : t('Checking…') });
    try {
      if (act === 'test') {
        const r = (await test.mutateAsync({ input: { id } })).flxChatConnectionTest?.result;
        if (!r) throw new Error('');
        const ver = r.version ? t('Evolution API version: %s', r.version) + (versionOld(r.version) ? ' ' + t('(old: replies to hidden-number (@lid) contacts may fail, update to 2.3 or later)') : '') : '';
        say(id, {
          kind: r.state === 'open' && r.webhookOk ? 'ok' : 'warn',
          body: (
            <>
              <div>{r.state === 'open' ? t('Connected ✓') : r.error ? t('Error:') + ' ' + r.error : t('Not connected (state: %s). Connect with QR.', r.state || '?')}</div>
              <div>{r.webhookOk ? t('Webhook points to this site ✓') : t('The webhook is not pointed at this site. Press "Set up webhook".')}</div>
              {ver && <div>{ver}</div>}
            </>
          ),
        });
      } else if (act === 'webhook') {
        const r = (await hook.mutateAsync({ input: { id } })).flxChatConnectionWebhook;
        say(id, r?.ok ? { kind: 'ok', body: t('Webhook set up ✓ Incoming messages will now arrive here.') } : { kind: 'bad', body: t('Error:') + ' ' + (r?.error ?? '') });
      } else if (act === 'import') {
        const r = (await imp.mutateAsync({ input: { id, chats: 30 } })).flxChatConnectionImport;
        say(id, r?.ok ? { kind: 'ok', body: t('%1$s chats, %2$s messages imported ✓', r.chats, r.messages) } : { kind: 'bad', body: t('Error:') + ' ' + (r?.error ?? '') });
      } else {
        const r = (await qr.mutateAsync({ input: { id } })).flxChatConnectionQr;
        if (r?.state === 'open') say(id, { kind: 'ok', body: t('This line is already connected ✓') });
        else if (!r?.ok && r?.error) say(id, { kind: 'bad', body: t('Error:') + ' ' + r.error });
        else
          say(id, {
            kind: 'warn',
            body: (
              <div className="flxca-qr">
                {r?.qr && <img src={r.qr.startsWith('data:') ? r.qr : 'data:image/png;base64,' + r.qr} alt="QR" />}
                <p>{t('Scan this code via WhatsApp › Linked devices › Link a device.')}</p>
                {r?.code && <p><strong>{t('Pairing code:')} {r.code}</strong></p>}
              </div>
            ),
          });
      }
    } catch (e) {
      say(id, { kind: 'bad', body: t('Error:') + ' ' + errorText(e, t) });
    }
    setBusyId('');
  };

  const showUrl = async (id: string) => {
    try {
      const r = (await reveal.mutateAsync({ input: { id } })).flxChatWebhookUrlReveal;
      if (r?.url) setUrls((u) => ({ ...u, [id]: r.url }));
    } catch (e) {
      say(id, { kind: 'bad', body: t('Error:') + ' ' + errorText(e, t) });
    }
  };
  const copy = (text: string) => {
    try {
      void navigator.clipboard?.writeText(text);
      setNotice({ kind: 'ok', text: t('Copied') });
    } catch {
      /* the address stays selectable */
    }
  };

  const add = () => {
    const src = ctx.defaultSource.value;
    setLines((ls) => [...ls, { uid: 'new' + Date.now() + ls.length, id: '', label: '', source: src, baseUrl: '', instance: '', apiKey: '', clearKey: false, rotate: false, hasApiKey: false, line: null }]);
  };
  const remove = (l: LineDraft) => {
    if (!window.confirm(t('Delete this connection? Chat history is kept.'))) return;
    setLines((ls) => ls.filter((x) => x.uid !== l.uid));
  };
  const submit = () =>
    onSave(
      lines.map((l) => ({
        id: l.id,
        label: l.label,
        source: l.source,
        baseUrl: l.baseUrl,
        instance: l.instance,
        apiKey: l.clearKey ? '__clear__' : l.apiKey,
        rotateSecret: l.rotate,
      })),
    );

  return (
    <>
      <Card
        title={t('WhatsApp lines')}
        lead={
          <>
            {ctx.flxiOn
              ? t('Messages are sent and received through Evolution API. WhatsApp connections are managed in Flux Mail; choose which one to use here. The URL and API key stay there, so you do not need to update them here when they change.')
              : t('Messages are sent and received via Evolution API. If an Evolution provider is set up in Flux Certificates, you can pick the same connection; the URL and API key stay there.')}{' '}
            {ctx.flxiOn && <a href={ctx.flxiConnectionsUrl}>{t('Communication › Connections')} →</a>}
          </>
        }
      >
        <div className="flxca-conns">
          {lines.map((l) => {
            const choices = l.line ? l.line.sources : ctx.sourceChoices;
            const own = l.source === 'own';
            const out = outs[l.id];
            return (
              <div key={l.uid} className="flxca-conn">
                <div className="flxca-conn__head">
                  <span className={'flxca-dot is-' + (l.line?.state || 'unknown')} />
                  <input type="text" value={l.label} placeholder={t('Line name (e.g. Main line)')} onChange={(e) => patch(l.uid, { label: e.currentTarget.value })} />
                  <button type="button" className="flxca-link is-danger" onClick={() => remove(l)}>{t('Delete')}</button>
                </div>
                <div className="flxca-grid">
                  <Field label={t('Connection details')} className="flxca-span2">
                    <select value={l.source} onChange={(e) => patch(l.uid, { source: e.currentTarget.value })}>
                      {choices.map((c) => (
                        <option key={c.value} value={c.value}>{c.label}</option>
                      ))}
                    </select>
                  </Field>
                  {own && (
                    <>
                      <Field label={t('API URL')}>
                        <input type="url" value={l.baseUrl} placeholder="https://wa.example.com" onChange={(e) => patch(l.uid, { baseUrl: e.currentTarget.value })} />
                      </Field>
                      <Field label={t('Instance name')}>
                        <input type="text" value={l.instance} onChange={(e) => patch(l.uid, { instance: e.currentTarget.value })} />
                      </Field>
                      <Field label={t('API key (apikey)')} className="flxca-span2">
                        <input
                          type="password"
                          autoComplete="new-password"
                          value={l.apiKey}
                          disabled={l.clearKey}
                          placeholder={l.hasApiKey && !l.clearKey ? t('•••••••• (saved; type to change)') : ''}
                          onChange={(e) => patch(l.uid, { apiKey: e.currentTarget.value })}
                        />
                        {l.hasApiKey && (
                          <Check checked={l.clearKey} onChange={(v) => patch(l.uid, { clearKey: v, apiKey: '' })}>
                            {t('Remove the saved key')}
                          </Check>
                        )}
                      </Field>
                    </>
                  )}
                </div>
                {l.id ? (
                  <>
                    <div className="flxca-wh">
                      <span>{t('Webhook URL')}</span>
                      {urls[l.id] ? (
                        <>
                          <code>{urls[l.id]}</code>
                          <button type="button" className="flxca-link" onClick={() => copy(urls[l.id] ?? '')}>{t('Copy')}</button>
                        </>
                      ) : (
                        <button type="button" className="flxca-link" disabled={reveal.isPending} onClick={() => void showUrl(l.id)}>{t('Show webhook URL')}</button>
                      )}
                      <Check checked={l.rotate} onChange={(v) => patch(l.uid, { rotate: v })}>{t('Regenerate secret key')}</Check>
                    </div>
                    <p className="flxca-small">
                      {l.line?.lastWebhookAt ? t('Last webhook: %s', l.line.lastWebhookAt.relative) : t('No webhook received yet.')}
                    </p>
                    <div className="flxca-actions">
                      <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => void run(l.id, 'test')}>{t('Test connection')}</button>
                      {!l.source.startsWith('flxi:') && <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => void run(l.id, 'qr')}>{t('Connect with QR')}</button>}
                      {ctx.flxiOn && l.source.startsWith('flxi:') && <a className="flxca-link" href={ctx.flxiConnectionsUrl}>{t('QR, status and accounts: Communication › Connections')} →</a>}
                      <button type="button" className="flxca-btn is-primary" disabled={busyId !== ''} onClick={() => void run(l.id, 'webhook')}>{t('Set up webhook')}</button>
                      <button type="button" className="flxca-btn" disabled={busyId !== ''} onClick={() => void run(l.id, 'import')}>{t('Import recent chats')}</button>
                    </div>
                    {out && <div className={'flxca-out is-' + out.kind} aria-live="polite">{out.body}</div>}
                  </>
                ) : (
                  <p className="flxca-small">{t('The webhook URL and test buttons appear after saving.')}</p>
                )}
              </div>
            );
          })}
        </div>
        <button type="button" className="flxca-btn" onClick={add}>+ {t('Add line')}</button>
      </Card>
      <Card title={t('Setup steps')}>
        <ol className="flxca-steps">
          <li>{t('Add the line and save.')}</li>
          <li>{t('Use "Test connection" to see that Evolution is reachable. If the line is not linked, choose "Connect with QR" and scan it from the phone.')}</li>
          <li>{t('Press "Set up webhook". Incoming messages, and messages sent from the phone and from Flux Certificates, then appear here live.')}</li>
          <li>{t('Optionally bring in past messages with "Import recent chats".')}</li>
        </ol>
      </Card>
      <div className="flxca-save">
        <button type="button" className="flxca-btn is-primary" disabled={busy} onClick={submit}>{t('Save')}</button>
      </div>
    </>
  );
}

/* ---------- general ---------- */

function GeneralTab({ bag }: { bag: Bag }) {
  const { t, ctx } = bag;
  return (
    <>
      <Card title={t('Appearance')}>
        <div className="flxca-grid">
          <Txt bag={bag} k="brandName" label={t('Panel title')} ph={ctx.siteName} note={t('Site name if empty. The {brand} variable uses this too.')} />
          <Txt bag={bag} k="accent" type="color" label={t('Accent color')} />
        </div>
      </Card>
      <Card title={t('Behavior')}>
        <div className="flxca-stack">
          <Tick bag={bag} k="sendRead">{t('Send read receipts (blue ticks) when a chat is opened')}</Tick>
          <Tick bag={bag} k="agentSignature">{t("Prefix messages with the agent's name (*Name:*)")}</Tick>
          <Tick bag={bag} k="restrictAgents">{t('Agents see only chats assigned to them and unassigned chats (admins see all)')}</Tick>
          <Tick bag={bag} k="showGroups">{t('Also show WhatsApp group messages')}</Tick>
          <Tick bag={bag} k="uiFlux">{t('New interface (experimental): the Chats page opens with the same screen as the desktop and mobile app')}</Tick>
        </div>
        <Txt bag={bag} k="deleteEveryoneHours" type="number" min={0} max={60} className="flxca-narrow" label={t('Delete for everyone time limit (hours)')} note={t("WhatsApp lets you delete a sent message for everyone only for about 2 days and 12 hours (60 hours). After this limit, or for received messages, the message is only hidden in the panel: it stays on the contact's phone. 0 turns off delete for everyone. Deleted messages are never removed from the server; the team can still open them.")} />
        <Txt bag={bag} k="pollInterval" type="number" min={3} max={60} className="flxca-narrow" label={t('Live update interval (sec)')} note={t('The open tab checks for new messages at this interval (10 recommended), every 3 s while a reply is streaming, and up to 15 s when nothing changes. A background tab checks once a minute. Errors back off up to 60 s.')} />
      </Card>
      <Card title={t('Agents')} lead={t('Give a user the %s role; they see only the chat screen and cannot open settings. Administrators always have access.', t('Call Center Agent'))}>
        <ul className="flxca-agents">
          {ctx.agents.map((a) => (
            <li key={a.databaseId}>{a.name}</li>
          ))}
        </ul>
        <a className="flxca-link" href={ctx.newUserUrl}>{t('Add new user')} →</a>
      </Card>
      <Card title={t('Data')}>
        <Tick bag={bag} k="deleteData">{t('Also delete all chats, messages and media files when the plugin is deleted')}</Tick>
        <p className="flxca-small">{t('Media files are kept in wp-content/uploads/flx-chat, closed to outside access. They move with uploads when the site is migrated.')}</p>
      </Card>
    </>
  );
}

/* ---------- auto replies ---------- */

function AutoTab({ bag }: { bag: Bag }) {
  const { t, ctx } = bag;
  const days: [number, string][] = [[1, t('Mon')], [2, t('Tue')], [3, t('Wed')], [4, t('Thu')], [5, t('Fri')], [6, t('Sat')], [7, t('Sun')]];
  const sel = (bag.val('awayDays') as number[]) ?? [];
  return (
    <>
      <Card title={t('Welcome message')}>
        <Tick bag={bag} k="welcomeEnabled">{t('Send automatically to people who write to us for the first time')}</Tick>
        <Area bag={bag} k="welcomeText" />
      </Card>
      <Card title={t('Away message')}>
        <Tick bag={bag} k="awayEnabled">{t('Auto reply to messages received outside business hours')}</Tick>
        <div className="flxca-days">
          <span>{t('Business days')}</span>
          {days.map(([d, l]) => (
            <label key={d} className="flxca-day">
              <input type="checkbox" checked={sel.includes(d)} onChange={(e) => bag.set('awayDays', e.currentTarget.checked ? [...sel, d].sort() : sel.filter((x) => x !== d))} />
              <span>{l}</span>
            </label>
          ))}
        </div>
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="awayStart" type="time" label={t('Start')} />
          <Txt bag={bag} k="awayEnd" type="time" label={t('End')} />
          <Txt bag={bag} k="awayThrottle" type="number" min={1} max={72} label={t('At most once per contact every (hours)')} />
        </div>
        <Area bag={bag} k="awayText" />
        <p className="flxca-small">{t('Timezone: %1$s. Now: %2$s.', ctx.timezone, ctx.isOpenHours ? t('within business hours') : t('outside business hours'))}</p>
        <p className="flxca-small">{t('The {first_name}, {name}, {brand}, {site}, {date} variables can be used in the texts.')}</p>
      </Card>
    </>
  );
}

/* ---------- labels ---------- */

function LabelsTab({ s, t, busy, onSave }: { s: S; t: Translate; busy: boolean; onSave: (l: { id: string; name: string; color: string }[]) => void }) {
  const [rows, setRows] = useState(() => s.labels.map((l) => ({ ...l })));
  useEffect(() => setRows(s.labels.map((l) => ({ ...l }))), [s.labels]);
  const patch = (i: number, p: Partial<{ name: string; color: string }>) => setRows((r) => r.map((x, j) => (j === i ? { ...x, ...p } : x)));
  return (
    <>
      <Card title={t('Chat labels')} lead={t('Label chats and filter the list (e.g. Donation, Complaint, Follow-up).')}>
        <div className="flxca-labels">
          {rows.map((l, i) => (
            <div key={i} className="flxca-label">
              <input type="color" value={l.color} aria-label={t('Color')} onChange={(e) => patch(i, { color: e.currentTarget.value })} />
              <input type="text" value={l.name} placeholder={t('Label name')} onChange={(e) => patch(i, { name: e.currentTarget.value })} />
              <button type="button" className="flxca-link" aria-label={t('Delete')} onClick={() => setRows((r) => r.filter((_, j) => j !== i))}>✕</button>
            </div>
          ))}
        </div>
        <button type="button" className="flxca-btn" onClick={() => setRows((r) => [...r, { id: '', name: '', color: '#25d366' }])}>+ {t('Add label')}</button>
      </Card>
      <div className="flxca-save">
        <button type="button" className="flxca-btn is-primary" disabled={busy} onClick={() => onSave(rows.map((l) => ({ id: l.id, name: l.name, color: l.color })))}>{t('Save')}</button>
      </div>
    </>
  );
}

/* ---------- records ---------- */

function RecordsTab({ bag }: { bag: Bag }) {
  const { t, ctx } = bag;
  return (
    <>
      <Card title={t('Creating records from chats')} lead={t('Buttons in the chat header create a donation record (Flux Donate) and a certificate (Flux Certificates), and an incoming receipt is matched with a record. If a plugin is not installed, its button is hidden.')}>
        <ul className="flxca-agents">
          <li>{ctx.flxbOn ? '✓ ' : '✕ '}Flux Donate</li>
          <li>{ctx.flxsOn ? '✓ ' : '✕ '}Flux Certificates</li>
        </ul>
        <div className="flxca-stack">
          <Tick bag={bag} k="agentsDonations">{t('Agents can create donation records and match receipts')}</Tick>
          <Tick bag={bag} k="agentsCertificates">{t('Agents can create and send certificates')}</Tick>
          <Tick bag={bag} k="chipsBar">{t('Show quick reply bubbles above the message box')}</Tick>
        </div>
      </Card>
      <Card title={t('Donation messages')} lead={t('Ready texts sent to the person when a record is created (editable before sending). If left empty, the default text is used.')}>
        <Area bag={bag} k="msgDonationPending" rows={5} label={t('Awaiting payment (promised transfer)')} ph={ctx.defaultDonationPending} />
        <Area bag={bag} k="msgDonationDone" rows={4} label={t('Donation received')} ph={ctx.defaultDonationDone} />
        <Area bag={bag} k="msgReceipt" rows={3} label={t('Receipt confirmed')} ph={ctx.defaultReceipt} />
        <Area bag={bag} k="bankText" rows={3} label={t('Bank details ({bank})')} ph={ctx.defaultBankText || t('Recipient name, bank, IBAN')} note={t('If empty, the account in WooCommerce › Payments › Bank transfer is used.')} />
        <p className="flxca-small">{t('Variables: {first_name}, {name}, {donation}, {amount}, {quantity}, {bank}, {certificate_link}, {agent}, {brand}, {date}.')}</p>
      </Card>
    </>
  );
}

/* ---------- AI and assistant ---------- */

function AiTab({ bag }: { bag: Bag }) {
  const { t, ctx } = bag;
  const [aiOut, setAiOut] = useState<Out | null>(null);
  const test = useFlxMutation(FlxChatAiTest, {});
  const [refresh, setRefresh] = useState(false);
  const groups = useFlxQuery(FlxChatGroups, { refresh });
  const [price, setPrice] = useState(false);
  const run = async () => {
    setAiOut({ kind: 'warn', body: t('Asking the AI…') });
    try {
      const r = (await test.mutateAsync({ input: {} })).flxChatAiTest;
      setAiOut(r ? { kind: 'ok', body: t('Working ✓ (%2$s ms): %1$s', r.text, r.ms) } : { kind: 'bad', body: t('Error:') });
    } catch (e) {
      setAiOut({ kind: 'bad', body: t('Error:') + ' ' + errorText(e, t) });
    }
  };
  const gl = groups.data?.flxChatGroups ?? [];
  const mirror = String(bag.val('mirrorGroup') ?? '');
  const sel = (bag.val('botTypes') as number[]) ?? [];
  const siteSel = bag.val('botSiteTypes') as string[];
  const effective = (name: string, auto: boolean) => (siteSel.length ? siteSel.includes(name) : auto);
  const toggleSite = (name: string, on: boolean) => {
    const cur = ctx.postTypes.filter((p) => effective(p.name, p.autoSelected)).map((p) => p.name);
    bag.set('botSiteTypes', on ? [...new Set([...cur, name])] : cur.filter((n) => n !== name));
  };
  const botCase = (k: string, ph: string, label: string, note?: string) => <Txt bag={bag} k={k} label={label} ph={ph} note={note} />;
  const bank = { holder: ctx.defaultBankHolder, iban: ctx.defaultBankIban };
  const url = ctx.aiSettingsUrl;
  return (
    <>
      <Card title={t('AI connection')}>
        {ctx.aiHub ? (
          <p className="flxca-lead">
            {t("Keys, prompt caching, spending limits and usage logs come from the Flux AI & MCP plugin; all of the Call Center's AI requests go through it. Model choices stay here; if a field is empty, the model chosen for this plugin in Flux AI (or the default) is used.")}{' '}
            <a href={url}>{t('Open Flux AI settings')}</a>
          </p>
        ) : (
          <div className="flxca-notice is-warn">{t('AI needs the Flux AI & MCP plugin. Install and activate it, then enter the OpenRouter and/or Anthropic key in Flux AI & MCP › Settings. Until then suggestions, the assistant, receipt reading, voice messages and summaries are off.')}</div>
        )}
        <div className="flxca-grid">
          <Txt bag={bag} k="aiTemperature" type="number" min={0} max={1} step={0.1} label={t('Creativity (0–1)')} note={t('Lower values give more consistent replies, higher values freer ones. 0.4 recommended.')} />
          <Field label={t('Model (for suggestions)')} className="flxca-span2"><ModelPicker bag={bag} k="aiModel" empty={t('Choose a model')} /></Field>
          <Field label={t('Assistant model (optional)')} className="flxca-span2" note={t('Only models that support tools are listed.')}><ModelPicker bag={bag} k="aiModelBot" tools empty={t('Model above')} /></Field>
          <Field label={t('Receipt reading model (optional)')} className="flxca-span2" note={t('If the selected model cannot read images, a cheap vision model is used automatically.')}><ModelPicker bag={bag} k="aiModelVision" vision empty={t('Assistant model')} /></Field>
        </div>
        <p className="flxca-row">
          <button type="button" className="flxca-btn" disabled={test.isPending} onClick={() => void run()}>{t('Test connection')}</button>
          {aiOut && <span className={'flxca-out is-' + aiOut.kind} aria-live="polite">{aiOut.body}</span>}
        </p>
        {ctx.aiUsage && <p className="flxca-small">{t('This month (%1$s): %2$s requests, %3$s tokens, about $%4$s.', ctx.aiUsage.month, ctx.aiUsage.calls.toLocaleString(), ctx.aiUsage.tokens.toLocaleString(), ctx.aiUsage.cost.toFixed(4))}</p>}
      </Card>

      <Card title={t('Reply suggestions')} lead={t('Above the message box, the next message is suggested from the conversation. The agent clicks "Send" or takes it into the box with "Edit". A suggestion is never sent on its own.')}>
        <Tick bag={bag} k="aiAutoSuggest">{t('Prepare a suggestion automatically when the last message came from the person (if off, ask with the ✨ button)')}</Tick>
        <Area bag={bag} k="aiInstructions" rows={6} label={t('Organization info and tone')} ph={t('E.g. Our association plants saplings in Istanbul. Planting is done every year between November and March. The certificate is sent on WhatsApp after the donation. Use a warm but formal tone.')} note={t('Suggestions and the assistant use this. Donation types, prices, bank details and quick replies are added automatically.')} />
        {ctx.aiProviders.length > 1 && (
          <Select bag={bag} k="aiProvider" label={t('Suggestion provider')} options={ctx.aiProviders.map((p) => [p.id, p.label] as [string, string])} />
        )}
      </Card>

      <Card title={t('Donation assistant (automatic replies)')}>
        {!ctx.flxbOn && <div className="flxca-notice is-warn">{t('The Flux Donate plugin is not active; the assistant cannot create donation records.')}</div>}
        <p><a className="flxca-btn" href={ctx.assistantUrl}>{t('Instructions, documents, AI chats and test chat')} →</a></p>
        <Tick bag={bag} k="botEnabled" strong>{t('Turn on the donation assistant (untick to turn it off everywhere)')}</Tick>
        <Field label={t('Which chats should it reply to')} note={t('When an agent writes in a chat (from the panel or the phone), the assistant stays quiet there for the time below. Use the assistant button in the chat header to turn it on or off per chat.')}>
          {([['all', t('In all chats (recommended)')], ['unassigned', t('In chats not assigned to an agent')], ['away', t('Only outside working hours')]] as [string, string][]).map(([v, l]) => (
            <Check key={v} radio name="botScope" value={v} checked={bag.val('botScope') === v} onChange={() => bag.set('botScope', v)}>{l}</Check>
          ))}
        </Field>
        <Tick bag={bag} k="botTestOnly">{t('Test mode: reply only to the numbers below')}</Tick>
        <Area bag={bag} k="botTestNumbers" rows={2} label={t('Test numbers')} ph="905321234567, 905551112233" note={t('Separate with commas or new lines. Not used when test mode is off.')} />
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="botPauseHours" type="number" min={1} max={720} label={t('Quiet after an agent writes (hours)')} />
          <Txt bag={bag} k="botDailyLimit" type="number" min={5} max={500} label={t('Daily reply limit per chat')} />
          <Txt bag={bag} k="botPresets" label={t('Quantities in the price list')} ph="1, 3, 5, 10" />
          <Txt bag={bag} k="botContextHours" type="number" min={1} max={168} label={t('Conversation history (hours)')} note={t('Before replying, the assistant reads all messages from this period.')} />
        </div>
        {ctx.donationTypes.length > 0 && (
          <Field label={t('Donation types the assistant offers')} note={t('If none is selected, all open types are offered. When a new type is opened in Flux Donate, the assistant knows it too.')}>
            {ctx.donationTypes.map((d) => (
              <Check key={d.databaseId} checked={sel.includes(d.databaseId)} onChange={(v) => bag.set('botTypes', v ? [...sel, d.databaseId] : sel.filter((x) => x !== d.databaseId))}>{d.title}</Check>
            ))}
          </Field>
        )}
        <Tick bag={bag} k="botReceiptAi">{t('Receipt pre-check: when the contact sends a receipt, AI compares the amount, recipient and IBAN with the donation, tells the contact the result, attaches the file to the donation record and hands the chat to the team for final approval (does not mark it paid)')}</Tick>
        {botCase('botPriceFooter', t('Just write which one and how many you would like.'), t('Last line of the price list'))}
        <h3 className="flxca-h3">{t('Payment details')}</h3>
        <p className="flxca-small">{t('If the recipient and IBAN are empty, those in WooCommerce › Payments › Bank transfer are used (the account shown on orders). Fill them in only if the assistant should use a different account.')}</p>
        <div className="flxca-grid">
          <Txt bag={bag} k="bankHolder" label={t('Recipient name')} ph={bank.holder} />
          <Txt bag={bag} k="bankIban" label="IBAN" ph={bank.iban || 'TR00 0000 0000 0000 0000 0000 00'} />
          <Txt bag={bag} k="bankDesc" label={t('Transfer description')} ph="{type} {name}" note={t('Variables: {type}, {name}, {amount}, {quantity}')} />
          <Txt bag={bag} k="donateUrl" type="url" label={t('Donation page URL')} ph={ctx.defaultDonateUrl} />
        </div>
        {botCase('botAfterPayment', t('After paying, you can send the receipt here. Thank you so much for your support 🤲'), t('Message after the payment details'))}
        <Area bag={bag} k="botCancelText" rows={3} label={t('Donation cancellation question')} ph={ctx.defaultCancelText} />
        {botCase('botLimitText', t("We've received your message and will get back to you as soon as possible 🌱"), t('Message sent when the daily limit is reached'))}
        {botCase('botHandoffText', t("I'm passing you to a colleague from our team, they will get back to you here shortly 🌱"), t('Message sent when handing over to the team'))}
        {botCase('botFallbackText', t('We are sorry to keep you waiting. We will get back to you as soon as possible. Thank you for your understanding.'), t('Message sent when the assistant cannot answer'))}
        <Txt bag={bag} k="botFallbackWait" type="number" min={0} max={45} label={t('Minimum wait before the holding message (s)')} />
        <Txt bag={bag} k="botTimeBudget" type="number" min={20} max={110} label={t('Longest time for one answer (s)')} note={t('Hostinger can cut requests at ~60 s; 50 is recommended.')} />
        <Area bag={bag} k="botNotifyNumbers" rows={2} label={t('Numbers notified on handoff')} ph="905321234567, 905551112233" />
        <Area bag={bag} k="botHandoffWords" rows={2} label={t('Agent request phrases')} ph={ctx.defaultHandoffWords} />
        <Area bag={bag} k="botInstructions" rows={4} label={t('Extra instructions for the assistant')} ph={t('E.g. Saplings are planted in Kocaeli. Certificates are sent once the donation is confirmed.')} />
        {botCase('botOptoutText', t('Understood, you will not receive any more messages from us. Have a nice day.'), t('Last message when a contact opts out'))}

        <h3 className="flxca-h3">{t('Contact memory and background jobs')}</h3>
        <Tick bag={bag} k="memEnabled">{t("Contact memory: the assistant remembers lasting details (form of address, relatives' names, numbers, preferences); expired chats are summarized")}</Tick>
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="memAfterHours" type="number" min={1} max={168} label={t('Summarize a chat after it has been quiet for (hours)')} />
          <Txt bag={bag} k="workersMax" type="number" min={1} max={30} label={t('Maximum jobs at the same time')} />
          <Txt bag={bag} k="workersReserved" type="number" min={0} max={30} label={t('Workers reserved for replies')} note={t('This many workers never take summaries or reminders.')} />
          <Field label={t('Summary hours')}>
            <span className="flxca-inline">
              <input type="time" value={String(bag.val('memWindowFrom'))} onChange={(e) => bag.set('memWindowFrom', e.currentTarget.value)} /> –{' '}
              <input type="time" value={String(bag.val('memWindowTo'))} onChange={(e) => bag.set('memWindowTo', e.currentTarget.value)} />
            </span>
          </Field>
        </div>

        <h3 className="flxca-h3">{t('Voice message')}</h3>
        <Tick bag={bag} k="botReactions">{t('Let the assistant react to messages with an emoji (👍 for "okay", 🤲 for thanks and prayers); once goodbyes are said it reacts instead of writing another farewell')}</Tick>
        <Tick bag={bag} k="botVoice">{t('When someone sends a voice message, the assistant turns it into text and replies (the text also appears in the panel as a system line)')}</Tick>
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="botVoiceMax" type="number" min={10} max={300} label={t('Longest voice message (sec)')} />
          <Field label={t('Voice message model (optional)')} className="flxca-span2" note={t('Must be an OpenRouter model that accepts audio (Claude does not). An OpenRouter key is required.')}>
            <ModelPicker bag={bag} k="aiModelAudio" empty={t('Cheap audio model (Gemini Flash Lite)')} />
          </Field>
        </div>

        <h3 className="flxca-h3">{t('Images')}</h3>
        <Tick bag={bag} k="botWidgets">{t('Let the assistant draw an image when useful (table, report, diagram); it goes to WhatsApp as a picture, the text is sent separately')}</Tick>
        <p className="flxca-small">
          {ctx.widgetsPluginOk
            ? ctx.widgetsRendererOk || !bag.val('botWidgets')
              ? t('The Flux AI image renderer is used (Flux AI & MCP › Settings › Images).')
              : t('No image renderer on the server: enter a Gotenberg address in Flux AI & MCP › Settings › Images or install Imagick (SVG). Until then the assistant does not draw images.')
            : t('Needs the Flux AI & MCP plugin.')}
        </p>

        <h3 className="flxca-h3">{t('Donation on behalf of someone')}</h3>
        <Tick bag={bag} k="benefCertNotify">{t("If the donation is in someone else's name and their number was given (contact card or typed number), also send the certificate to that number on WhatsApp once the payment is approved")}</Tick>
        {botCase('benefCertText', ctx.defaultBenefCertText, t('Message sent before the certificate'))}

        <h3 className="flxca-h3">{t('Reminders')}</h3>
        <Tick bag={bag} k="remEnabled">{t('If a person asks, the assistant sets up a reminder (e.g. "remind me of sapling planting every month", "send me your latest projects every month", "ask me for a sapling every month"); when it is due, the assistant writes and sends the message')}</Tick>
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="remTime" type="time" label={t('When no time is given')} />
          <Txt bag={bag} k="remMax" type="number" min={1} max={50} label={t('At most per person')} />
        </div>

        <h3 className="flxca-h3">{t('Staff (Flux Roles)')}</h3>
        {!ctx.rolesOn && <p className="flxca-small">{t('Works once Flux Roles 1.1.0 is installed: users whose number is set in Profile › WhatsApp number get extra assistant tools based on their permissions.')}</p>}
        <Tick bag={bag} k="staffOn">{t('Give staff numbers extra assistant tools by permission (donation on behalf of someone with payment link, my donors, general report)')}</Tick>
        <Tick bag={bag} k="refNotify">{t('Notify staff on WhatsApp when a donation they referred is paid or cancelled')}</Tick>
        {botCase('staffDonorText', t('Hello {ad} 🌱 Your {bagis} donation request via {personel} has been received. You can pay with the details below.'), t('First message to the donor for a donation opened by staff'))}
        <Tick bag={bag} k="refCertNotify">{t('When a donation referred by a staff member is paid and its certificates are made, send them to the staff member on WhatsApp too (just like the donor)')}</Tick>
        <div className="flxca-grid is-3">
          {botCase('refCertText', ctx.defaultRefCertText, t('Message sent to the staff member before the certificate'))}
          <Txt bag={bag} k="staffCertMax" type="number" min={0} max={20} label={t("How many times staff can resend a donation's certificate")} note={t('The assistant lists the recipients and asks for confirmation before sending. 0: off.')} />
        </div>

        <h3 className="flxca-h3">{t('Code verification for record lookups')}</h3>
        <Tick bag={bag} k="emailVerify">{t('When a person asks for their records and their number is hidden (or they donated with another number/e-mail), send a code to the number or e-mail they give; once verified, its records are shown too')}</Tick>
        <div className="flxca-grid is-3">
          <Txt bag={bag} k="verifyTtl" type="number" min={1} max={30} label={t('Code validity (min)')} />
          <Txt bag={bag} k="verifyMaxFails" type="number" min={1} max={10} label={t('Maximum failed attempts')} />
          <Select bag={bag} k="verifyPhoneChannel" label={t('Code to phone')} note={t('SMS is used if Flux Mail has an SMS connection (e.g. Netgsm).')} options={[['auto', t('WhatsApp, SMS if it fails')], ['whatsapp', t('WhatsApp only')], ['sms', t('SMS only')]]} />
        </div>
        {botCase('verifyFailText', ctx.defaultVerifyFailText, t('Message when verification fails'))}
        <Area bag={bag} k="verifyMsg" rows={3} label={t('WhatsApp/SMS code message')} ph={ctx.defaultVerifyMsg} />

        <h3 className="flxca-h3">{t('Site content')}</h3>
        <Tick bag={bag} k="botSiteSearch">{t('Let the assistant search published site content (e.g. "do you have a post about tree planting")')}</Tick>
        <Field label={t('Content types to search')} note={t('Only published content without a password is returned. Drafts, private and password-protected content are never shown.')}>
          {ctx.postTypes.map((p) => (
            <Check key={p.name} checked={effective(p.name, p.autoSelected)} onChange={(v) => toggleSite(p.name, v)}>{p.label}</Check>
          ))}
        </Field>
        {botCase('botSiteExclude', t('e.g. corporate-offers, internal-notice'), t('Categories and tags never to share'))}

        <h3 className="flxca-h3">{t('Team group')}</h3>
        <Field
          label={t('WhatsApp group')}
          note={
            <>
              {t('Groups the line is a member of. If a group you just created is missing,')}{' '}
              <button type="button" className="flxca-link" onClick={() => setRefresh(true)}>{t('refresh the list')}</button>.
            </>
          }
        >
          <select value={mirror} onChange={(e) => bag.set('mirrorGroup', e.currentTarget.value)}>
            <option value="">{t('— Not selected —')}</option>
            {gl.map((g) => (
              <option key={g.id} value={g.id}>{g.name}</option>
            ))}
            {mirror !== '' && !gl.some((g) => g.id === mirror) && <option value={mirror}>{mirror}</option>}
          </select>
        </Field>
        <Tick bag={bag} k="mirrorEnabled">{t('Post incoming messages and replies (assistant, team, automatic) to this group')}</Tick>
        <Tick bag={bag} k="notifyToGroup">{t('Send handoff notifications to this group instead of each number; the numbers above are mentioned in the message')}</Tick>
        {ctx.mirrorError && <p className="flxca-warn">{t('Last post to the group failed: ')}{ctx.mirrorError.error}</p>}
        {ctx.pricePreview && (
          <div>
            <button type="button" className="flxca-link" onClick={() => setPrice((v) => !v)}>{t('Price list preview')}</button>
            {price && <pre className="flxca-pre">{ctx.pricePreview}</pre>}
          </div>
        )}
        {!ctx.aiReady && <p className="flxca-small">{t('Enter an AI key and model first so the assistant can work.')}</p>}
        {ctx.aiTypesProblem && <p className="flxca-warn">{t('The assistant will not reply right now: ')}{ctx.aiTypesProblem}</p>}
      </Card>
    </>
  );
}

/* ---------- model picker ---------- */

function ModelPicker({ bag, k, empty, tools, vision }: { bag: Bag; k: string; empty: string; tools?: boolean; vision?: boolean }) {
  const { t } = bag;
  const value = String(bag.val(k) ?? '');
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [debounced, setDebounced] = useState('');
  useEffect(() => {
    const id = window.setTimeout(() => setDebounced(search), 250);
    return () => window.clearTimeout(id);
  }, [search]);
  const vars = useMemo(() => ({ search: debounced || undefined, ids: value ? [value] : undefined, tools: tools || undefined, vision: vision || undefined, first: 50 }), [debounced, value, tools, vision]);
  const q = useFlxQuery(FlxChatAiModels, vars, { enabled: open || value !== '' });
  const models = q.data?.flxChatAiModels.models ?? [];
  const current = models.find((m) => m.id === value);
  const total = q.data?.flxChatAiModels.total ?? 0;
  return (
    <div className="flxca-mp">
      <button type="button" className="flxca-mp__btn" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        {value ? (current ? current.name : value) : empty}
        {current && <small>{money(current.priceIn)} / {money(current.priceOut)} {t('per million tokens')}</small>}
      </button>
      {open && (
        <div className="flxca-mp__pop">
          <input type="search" autoFocus value={search} placeholder={t('Search models')} onChange={(e) => setSearch(e.currentTarget.value)} />
          <ul>
            <li>
              <button type="button" onClick={() => { bag.set(k, ''); setOpen(false); }}>{empty}</button>
            </li>
            {q.isLoading && <li className="flxca-small">{t('Loading…')}</li>}
            {q.isError && <li className="flxca-warn">{errorText(q.error, t)}</li>}
            {models.map((m) => (
              <li key={m.id}>
                <button type="button" className={m.id === value ? 'is-on' : ''} onClick={() => { bag.set(k, m.id); setOpen(false); }}>
                  <span>{m.name}</span>
                  <small>{m.id} · {money(m.priceIn)} / {money(m.priceOut)}</small>
                </button>
              </li>
            ))}
            {total > models.length && <li className="flxca-small">{t('Refine the search to see more models.')}</li>}
          </ul>
        </div>
      )}
    </div>
  );
}

const money = (n: number) => '$' + (n >= 1 ? n.toFixed(2) : n.toFixed(3));

/* ---------- system check ---------- */

function ChecksTab({ t, onTab }: { t: Translate; onTab: (tab: string) => void }) {
  const q = useFlxQuery(FlxChatSystemChecks, {});
  const c = q.data?.flxChatSystemChecks;
  const groups = useMemo(() => {
    const m = new Map<string, NonNullable<typeof c>['items']>();
    for (const i of c?.items ?? []) m.set(i.group, [...(m.get(i.group) ?? []), i]);
    return [...m.entries()];
  }, [c]);
  return (
    <Card title={t('System check')} lead={t('Connections and settings the call center needs. Saved status is shown; use the "test" buttons on each tab for a live check. The same checks also appear with the other plugins on the theme\'s system-check screen.')}>
      {q.isLoading && <Loading />}
      {q.isError && <div className="flxca-notice is-bad">{errorText(q.error, t)}</div>}
      {c && (
        <>
          <p className="flxca-small">
            <span className="flxca-badge is-bad">{c.errorCount}</span> {t('Error')} · <span className="flxca-badge is-warn">{c.warningCount}</span> {t('Warning')} · <span className="flxca-badge is-ok">{c.okCount}</span> {t('OK')}
          </p>
          {groups.map(([g, items]) => (
            <div key={g} className="flxca-checks">
              <h3 className="flxca-h3">{g}</h3>
              <ul>
                {items.map((i) => (
                  <li key={i.id} className={'is-' + i.status}>
                    <span className={'flxca-badge is-' + (i.status === 'error' ? 'bad' : i.status === 'warning' ? 'warn' : i.status === 'ok' ? 'ok' : '')}>{i.status}</span>
                    <div>
                      <strong>{i.label}</strong>
                      <div>{i.message}</div>
                      {i.actionLabel && (i.actionTab ? (
                        <button type="button" className="flxca-link" onClick={() => onTab(i.actionTab)}>{i.actionLabel} →</button>
                      ) : i.actionUrl ? (
                        <a className="flxca-link" href={i.actionUrl}>{i.actionLabel} →</a>
                      ) : null)}
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </>
      )}
    </Card>
  );
}
