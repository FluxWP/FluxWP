// Flux AI › Settings: providers and API keys (write-only), models, cache and thinking, spending limit, shared chat,
// image and video, web, visuals and logs. Saved through flxAiSettingsUpdate.
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState, type ReactNode } from 'react';
import { FlxAiModels, FlxAiModelTest, FlxAiRenderTest, FlxAiSettingsScreen, FlxAiSettingsUpdate } from '@fluxwp/graphql/operations';
import type { FlxAiCacheMode, FlxAiChatMode, FlxAiEffort, FlxAiLimitAction, FlxAiSettingsScreenQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { Card, NoticeBar, errText, useAi, type Notice } from './common';

type Data = FlxAiSettingsScreenQuery;
type Form = Omit<Data['flxAiSettings'], '__typename' | 'sourceModels'> & { sourceModels: Record<string, string> };

function toForm(s: Data['flxAiSettings']): Form {
  const sourceModels: Record<string, string> = {};
  for (const m of s.sourceModels) sourceModels[m.source] = m.model;
  const { sourceModels: _drop, ...rest } = s as Data['flxAiSettings'] & { __typename?: string };
  void _drop;
  return { ...rest, sourceModels } as Form;
}

/** Placeholder of an empty key field (the shape of the key). */
const KEY_PLACEHOLDER: Record<string, string> = { key_openrouter: 'sk-or-…', key_anthropic: 'sk-ant-…', key_openai: 'sk-…', key_gemini: 'AIza…', key_evolink: 'sk-…' };

/** Where to get the main provider keys (the catalog leaves these empty). */
function KEY_HELP(name: string, t: (s: string) => string): string {
  if (name === 'key_openrouter') return t('openrouter.ai › Keys. Hundreds of models with one key; actual cost is reported with every request.');
  if (name === 'key_anthropic') return t('console.anthropic.com › API Keys. Claude models are listed with the "claude:" prefix.');
  return '';
}

function Field({ label, hint, children }: { label: ReactNode; hint?: ReactNode; children: ReactNode }) {
  return (
    <div className="flxai-field">
      <div className="flxai-field-label">{label}</div>
      <div className="flxai-field-body">
        {children}
        {hint && <p className="flxai-hint">{hint}</p>}
      </div>
    </div>
  );
}

function Check({ checked, onChange, children }: { checked: boolean; onChange: (v: boolean) => void; children: ReactNode }) {
  return (
    <label className="flxai-check">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} /> {children}
    </label>
  );
}

/** Model id input with a suggestion list from the model catalog. */
export function ModelInput({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder?: string }) {
  const [term, setTerm] = useState('');
  useEffect(() => {
    const id = setTimeout(() => setTerm(value), 300);
    return () => clearTimeout(id);
  }, [value]);
  const q = useFlxQuery(FlxAiModels, { search: term, first: 20 }, { enabled: term.length > 1 });
  const list = 'flxai-models-' + (placeholder ?? 'x').replace(/\W/g, '');
  return (
    <>
      <input className="flxai-input" list={list} value={value} placeholder={placeholder} autoComplete="off" onChange={(e) => onChange(e.target.value)} />
      <datalist id={list}>
        {(q.data?.flxAiModels ?? []).map((m) => (
          <option key={m.id} value={m.id}>
            {m.name}
          </option>
        ))}
      </datalist>
    </>
  );
}

export function Settings() {
  const { t, num } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAiSettingsScreen, {});
  const [form, setForm] = useState<Form | null>(null);
  const [keys, setKeys] = useState<Record<string, string>>({});
  const [clear, setClear] = useState<Record<string, boolean>>({});
  const [renderPass, setRenderPass] = useState('');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [test, setTest] = useState<{ kind: 'ok' | 'err'; text: string; image?: string } | null>(null);

  useEffect(() => {
    if (q.data && !form) setForm(toForm(q.data.flxAiSettings));
  }, [q.data, form]);

  const save = useFlxMutation(FlxAiSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxAiSettingsUpdate;
      if (!p || p.errors.length) return setNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      if (q.data && p.settings) qc.setQueryData(flxQueryKey(scope, FlxAiSettingsScreen, {}), { ...q.data, flxAiSettings: p.settings, flxAiSecrets: p.secrets });
      if (p.settings) setForm(toForm(p.settings));
      setKeys({});
      setClear({});
      setNotice({ kind: 'success', text: t('Settings saved.') });
    },
    onError: (e) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const modelTest = useFlxMutation(FlxAiModelTest, {
    onSuccess: (r) => {
      const p = r.flxAiModelTest;
      setTest(p?.errors.length ? { kind: 'err', text: p.errors.map((e) => e.message).join(' ') } : { kind: 'ok', text: t('%s (%s ms)', p?.result?.text ?? '', num(p?.result?.ms ?? 0)) });
    },
    onError: (e) => setTest({ kind: 'err', text: errText(e, t('The action could not be completed.')) }),
  });
  const renderTest = useFlxMutation(FlxAiRenderTest, {
    onSuccess: (r) => {
      const p = r.flxAiRenderTest;
      setTest(p?.errors.length ? { kind: 'err', text: p.errors.map((e) => e.message).join(' ') } : { kind: 'ok', text: t('Rendered via %s in %s ms.', p?.result?.via ?? '', num(p?.result?.ms ?? 0)), image: p?.result?.image });
    },
    onError: (e) => setTest({ kind: 'err', text: errText(e, t('The action could not be completed.')) }),
  });

  if (q.isLoading || !form) return q.isError ? <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div> : <Loading />;
  const data = q.data!;
  const o = data.flxAiSettingsOptions;
  const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm((f) => (f ? { ...f, [k]: v } : f));
  const secret = (name: string) => data.flxAiSecrets.find((s) => s.name === name);

  const KeyRow = ({ name }: { name: string }) => {
    const s = secret(name);
    if (!s) return null;
    return (
      <Field label={t('%s API key', s.label)} hint={s.fromConfig ? t('Set in wp-config.php; remove it there.') : s.help || KEY_HELP(name, t) || undefined}>
        <input
          className="flxai-input"
          type="password"
          autoComplete="new-password"
          value={keys[name] ?? ''}
          placeholder={s.configured ? s.hint : (KEY_PLACEHOLDER[name] ?? '')}
          disabled={s.fromConfig || clear[name]}
          onChange={(e) => setKeys((k) => ({ ...k, [name]: e.target.value }))}
        />
        {s.configured && !s.fromConfig && (
          <Check checked={!!clear[name]} onChange={(v) => setClear((c) => ({ ...c, [name]: v }))}>
            {t('Remove')}
          </Check>
        )}
      </Field>
    );
  };

  const submit = () => {
    const secrets = data.flxAiSecrets
      .map((s) => ({ name: s.name, value: keys[s.name] ?? '', clear: !!clear[s.name] }))
      .concat(renderPass ? [{ name: 'key_render', value: renderPass, clear: false }] : [])
      .filter((s, i, a) => (s.value || s.clear) && a.findIndex((x: { name: string }) => x.name === s.name) === i);
    const { sourceModels, ...rest } = form;
    save.mutate({
      input: {
        ...rest,
        sourceModels: Object.entries(sourceModels).map(([source, model]) => ({ source, model })),
        secrets,
      },
    });
  };

  const group = (g: string) => data.flxAiSecrets.filter((s) => s.group === g.toUpperCase());

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <Card title={t('Providers')}>
        {group('provider').map((s) => (
          <KeyRow key={s.name} name={s.name} />
        ))}
        <Field label={t('Anthropic workspace ID')} hint={t('Only needed when the key is not tied to a workspace (if the error asks for "anthropic-workspace-id").')}>
          <input className="flxai-input" value={form.anthropicWorkspace} placeholder="wrkspc_…" onChange={(e) => set('anthropicWorkspace', e.target.value)} />
        </Field>
      </Card>

      <Card title={t('Models')}>
        <Field label={t('Default model')} hint={t('Used when a plugin does not specify its own model.') + (o.defaultModel ? ' ' + t('Empty uses %s.', o.defaultModel) : '')}>
          <ModelInput value={form.model} onChange={(v) => set('model', v)} placeholder={o.defaultModel} />
          <button type="button" className="flxai-btn is-small" disabled={modelTest.isPending} onClick={() => modelTest.mutate({ input: { model: form.model } })}>
            {t('Test')}
          </button>
        </Field>
        <Field label={t('Chat model')} hint={t('Choose a model that supports tools.')}>
          <ModelInput value={form.chatModel} onChange={(v) => set('chatModel', v)} placeholder={t('Default')} />
        </Field>
        <Field label={t('Chat title model')} hint={t('A cheap model is enough for automatic chat titles.')}>
          <ModelInput value={form.titleModel} onChange={(v) => set('titleModel', v)} placeholder={t('Chat model')} />
        </Field>
        <h3 className="flxai-subhead">{t('Per-plugin model')}</h3>
        <p className="flxai-hint">{t("Used when the plugin's request names no model; empty means the default.")}</p>
        {data.flxAiSettings.sourceModels.map((m) => (
          <Field key={m.source} label={m.label}>
            <ModelInput value={form.sourceModels[m.source] ?? ''} onChange={(v) => set('sourceModels', { ...form.sourceModels, [m.source]: v })} placeholder={t('Default')} />
          </Field>
        ))}
        {test && !renderTest.isPending && (
          <div className={'flxai-notice is-' + (test.kind === 'ok' ? 'success' : 'error')} role="status">
            {test.text}
            {test.image && <img className="flxai-test-img" src={test.image} alt="" />}
          </div>
        )}
      </Card>

      <Card title={t('Cache and thinking')}>
        <Field label={t('Claude prompt cache')} hint={t('When the same instructions and tools are sent again most of the input cost drops (read 0.1x; write 5 min 1.25x, 1 hour 2x). Plugins can override per request. OpenRouter caching is automatic at the provider.')}>
          <select className="flxai-input" value={form.cache} onChange={(e) => set('cache', e.target.value as FlxAiCacheMode)}>
            <option value="OFF">{t('Off')}</option>
            <option value="FIVE_MINUTES">{t('On, 5 minutes')}</option>
            <option value="ONE_HOUR">{t('On, 1 hour')}</option>
          </select>
        </Field>
        <Field label={t('Claude effort')}>
          <select className="flxai-input" value={form.effort} onChange={(e) => set('effort', e.target.value as FlxAiEffort)}>
            <option value="LOW">{t('Low: fast and cheap')}</option>
            <option value="MEDIUM">{t('Medium')}</option>
            <option value="HIGH">{t('High: thinks deeper')}</option>
          </select>
        </Field>
        <Field label={t('Thinking steps')}>
          <Check checked={form.thinking} onChange={(v) => set('thinking', v)}>
            {t("Show the model's thinking summary in chat")}
          </Check>
        </Field>
      </Card>

      <Card title={t('Spending limit')}>
        {(
          [
            ['limitDay', t('Daily (resets every day at 00:00)')],
            ['limitWeek', t('Weekly (resets on Monday)')],
            ['limitMonth', t('Monthly (resets on the 1st)')],
          ] as const
        ).map(([k, label]) => (
          <Field key={k} label={label} hint={t('0 = unlimited')}>
            <span>$ </span>
            <input className="flxai-input is-small" type="number" min={0} step={0.01} value={form[k]} onChange={(e) => set(k, Number(e.target.value))} />
          </Field>
        ))}
        <Field label={t('When the limit is reached')}>
          <div className="flxai-radios" role="radiogroup" aria-label={t('When the limit is reached')}>
            {(
              [
                ['BLOCK', t('Stop requests (plugins switch to their fallback behaviour)')],
                ['WARN', t('Warn only')],
              ] as [FlxAiLimitAction, string][]
            ).map(([v, label]) => (
              <label key={v} className="flxai-check">
                <input type="radio" name="flxai-limit-action" checked={form.limitAction === v} onChange={() => set('limitAction', v)} /> {label}
              </label>
            ))}
          </div>
        </Field>
        <Field label={t('Warning threshold')}>
          <span>% </span>
          <input className="flxai-input is-small" type="number" min={10} max={99} value={form.limitWarnPct} onChange={(e) => set('limitWarnPct', Number(e.target.value))} />
          <Check checked={form.limitNotify} onChange={(v) => set('limitNotify', v)}>
            {t('Email the admin at the threshold and when reached')}
          </Check>
        </Field>
      </Card>

      <Card title={t('Shared chat')}>
        <Field label={t('Who can use it')} hint={t('Everyone only uses tools their own capabilities allow. Individual users can also be given the "flxai_chat" capability.')}>
          {o.roles.map((r) => (
            <Check key={r.id} checked={form.chatRoles.includes(r.id)} onChange={(v) => set('chatRoles', v ? [...form.chatRoles, r.id] : form.chatRoles.filter((x) => x !== r.id))}>
              {r.label}
            </Check>
          ))}
        </Field>
        <Field label={t('Where to show')}>
          <Check checked={form.chatAdmin} onChange={(v) => set('chatAdmin', v)}>
            {t('Admin panel')}
          </Check>
          <Check checked={form.chatFront} onChange={(v) => set('chatFront', v)}>
            {t('Site (for logged-in permitted users)')}
          </Check>
        </Field>
        <Field label={t('Default layout')} hint={t("Users can switch from the panel; the choice stays in their browser.")}>
          <select className="flxai-input" value={form.chatMode} onChange={(e) => set('chatMode', e.target.value as FlxAiChatMode)}>
            <option value="BUBBLE">{t('Floating window')}</option>
            <option value="DOCK">{t('Docked right (pushes the page)')}</option>
            <option value="PAGE">{t('Full screen')}</option>
          </select>
        </Field>
        <Field label={t('Max steps per turn')}>
          <input className="flxai-input is-small" type="number" min={2} max={30} value={form.chatMaxRounds} onChange={(e) => set('chatMaxRounds', Number(e.target.value))} />
        </Field>
        <Field label={t('Max messages sent to the model')}>
          <input className="flxai-input is-small" type="number" min={6} max={200} value={form.chatHistory} onChange={(e) => set('chatHistory', Number(e.target.value))} />
        </Field>
        <Field label={t('Extra instructions')}>
          <textarea className="flxai-input" rows={5} value={form.chatInstructions} placeholder={t('E.g. Use a formal tone; always create new pages as drafts.')} onChange={(e) => set('chatInstructions', e.target.value)} />
        </Field>
        <Field label={t('Visuals')}>
          <Check checked={form.chatWidgets} onChange={(v) => set('chatWidgets', v)}>
            {t('Let the model draw tables, cards, diagrams and charts as visuals')}
          </Check>
        </Field>
      </Card>

      <Card title={t('Image and video generation')}>
        <p className="flxai-hint">{t('Plugins that generate images and video (Flux Studio etc.) use these keys. In automatic mode the first provider with a key for the model is used; a request that may have been charged is never resent to another provider. The spending limit and Logs apply here too.')}</p>
        {group('media').map((s) => (
          <KeyRow key={s.name} name={s.name} />
        ))}
        {(
          [
            ['mediaImageModel', 'IMAGE', t('Default image model')],
            ['mediaVideoModel', 'VIDEO', t('Default video model')],
          ] as const
        ).map(([k, kind, label]) => (
          <Field key={k} label={label}>
            <select className="flxai-input" value={form[k]} onChange={(e) => set(k, e.target.value)}>
              {o.mediaModels
                .filter((m) => m.kind === kind)
                .map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.label}
                  </option>
                ))}
            </select>
          </Field>
        ))}
        <Field label={t('Image tool')}>
          <Check checked={form.imageToolOn} onChange={(v) => set('imageToolOn', v)}>
            {t('Chat and agents may generate images with the default image model (flxai_image_generate; each image is charged and counted in the spending limit)')}
          </Check>
        </Field>
        <Field label={t('Model › provider')}>
          <div className="flxai-tscroll">
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Model')}</th>
                  <th>{t('Order')}</th>
                  <th>{t('Automatic')}</th>
                </tr>
              </thead>
              <tbody>
                {o.mediaModels.map((m) => (
                  <tr key={m.id}>
                    <td>{m.label + (m.kind === 'VIDEO' ? ' · ' + t('video') : '')}</td>
                    <td>{m.routes.map((r) => o.mediaProviders.find((p) => p.id === r)?.label ?? r).join(' → ')}</td>
                    <td>{m.auto ? o.mediaProviders.find((p) => p.id === m.auto)?.label ?? m.auto : <span className="flxai-hint">{t('No key')}</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Field>
        <Field label={t('Job records')} hint={t('days kept (generated files are not deleted)')}>
          <input className="flxai-input is-small" type="number" min={7} max={1825} value={form.mediaDays} onChange={(e) => set('mediaDays', Number(e.target.value))} />
        </Field>
      </Card>

      <Card title={t('Web')}>
        <p className="flxai-hint">{t('Chat, scheduled tasks and agents can search the web and read public pages. Page content is treated as data, never as instructions. Local and private network addresses cannot be read.')}</p>
        <Field label={t('Web search')}>
          <Check checked={form.webOn} onChange={(v) => set('webOn', v)}>
            {t('Allow the flxai_web_search tool')}
          </Check>
        </Field>
        <Field label={t('Read web pages')}>
          <Check checked={form.webFetchOn} onChange={(v) => set('webFetchOn', v)}>
            {t('Allow the flxai_web_fetch tool (no key needed)')}
          </Check>
        </Field>
        <Field label={t('Virtual shell')}>
          <Check checked={form.shellOn} onChange={(v) => set('shellOn', v)}>
            {t('Allow the flxai_sh tool: shell-like commands (ls, grep, sed, jq, curl…) on the workspace files. No real processes; the workspace rules apply.')}
          </Check>
        </Field>
        {group('web').map((s) => (
          <KeyRow key={s.name} name={s.name} />
        ))}
        <Field label={t('Search provider')}>
          <select className="flxai-input" value={form.webProvider} onChange={(e) => set('webProvider', e.target.value)}>
            <option value="auto">{t('Automatic (the first provider with a key)')}</option>
            {o.webProviders.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </select>
        </Field>
        <Field label={t('Monthly search limit')} hint={t('0 = unlimited. Used this month: %s.', num(o.webMonthCount))}>
          <input className="flxai-input is-small" type="number" min={0} value={form.webSearchMonth} onChange={(e) => set('webSearchMonth', Number(e.target.value))} />
        </Field>
        <Field label={t('Cost per 1,000 searches')} hint={t('Above 0 each search is logged and counted in the spending limit.')}>
          <span>$ </span>
          <input className="flxai-input is-small" type="number" min={0} step={0.01} value={form.webSearchCost} onChange={(e) => set('webSearchCost', Number(e.target.value))} />
        </Field>
      </Card>

      <Card title={t('Visuals')}>
        <p className="flxai-hint">{t('Visuals (tables, cards, diagrams, charts) become images in PDFs and e-mails through Gotenberg; with no address the server tries a local renderer.')}</p>
        <Field label={t('Gotenberg address')}>
          <input className="flxai-input" type="url" value={form.renderUrl} placeholder="https://" onChange={(e) => set('renderUrl', e.target.value)} />
        </Field>
        <Field label={t('Gotenberg user')}>
          <input className="flxai-input" autoComplete="off" value={form.renderUser} onChange={(e) => set('renderUser', e.target.value)} />
        </Field>
        <Field label={t('Gotenberg password')}>
          <input className="flxai-input" type="password" autoComplete="new-password" value={renderPass} placeholder={secret('key_render')?.configured ? secret('key_render')?.hint : ''} onChange={(e) => setRenderPass(e.target.value)} />
          <button type="button" className="flxai-btn is-small" disabled={renderTest.isPending} onClick={() => renderTest.mutate({ input: { url: form.renderUrl, user: form.renderUser, pass: renderPass } })}>
            {t('Test')}
          </button>
        </Field>
      </Card>

      <Card title={t('Logs')}>
        <Field label={t('Keep usage logs')} hint={t('days; 0 keeps them forever')}>
          <input className="flxai-input is-small" type="number" min={0} max={3650} value={form.logDays} onChange={(e) => set('logDays', Number(e.target.value))} />
        </Field>
        <Field label={t('Content')}>
          <Check checked={form.logBodies} onChange={(v) => set('logBodies', v)}>
            {t('Also store shortened, masked request and reply content')}
          </Check>
        </Field>
        <Field label={t('Keep content')} hint={t('days; 0 keeps it forever')}>
          <input className="flxai-input is-small" type="number" min={0} max={3650} value={form.logBodyDays} onChange={(e) => set('logBodyDays', Number(e.target.value))} />
        </Field>
        <Field label={t('On delete')}>
          <Check checked={form.deleteData} onChange={(v) => set('deleteData', v)}>
            {t('Delete logs and chats when the plugin is deleted')}
          </Check>
        </Field>
      </Card>

      <div className="flxai-savebar">
        <button type="button" className="flxai-btn is-primary" disabled={save.isPending} onClick={submit}>
          {save.isPending ? t('Saving…') : t('Save')}
        </button>
      </div>
    </>
  );
}
