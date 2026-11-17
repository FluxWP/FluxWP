// Flux AI › Connected systems › <plugin>: the AI switch, the model of one plugin and the settings it declared
// with the Flux AI SDK. Same features as the classic page; goes over flxAiSystemSettings / ...Update.
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiSystemSettings, FlxAiSystemSettingsUpdate } from '@fluxwp/graphql/operations';
import type { FlxAiSystemSettingsFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, errText, useAi, type Notice } from './common';
import { ModelInput } from './Settings';
import { Field, firstError } from './Skills';

type S = FlxAiSystemSettingsFieldsFragment;

export function SystemSettings({ source }: { source: string }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxAiSystemSettings, { source });
  const key = flxQueryKey(scope, FlxAiSystemSettings, { source });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  return <Form s={q.data.flxAiSystemSettings} cacheKey={key} />;
}

function Form({ s, cacheKey }: { s: S; cacheKey: ReturnType<typeof flxQueryKey> }) {
  const { t, num } = useAi();
  const host = useHost();
  const qc = useQueryClient();
  const adminUrl = host.adminUrl ?? '';
  const [on, setOn] = useState(s.on);
  const [model, setModel] = useState(s.model);
  const [vals, setVals] = useState<Record<string, string>>(() => Object.fromEntries(s.fields.map((f) => [f.key, f.value])));
  const [notice, setNotice] = useState<Notice | null>(null);
  const save = useFlxMutation(FlxAiSystemSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxAiSystemSettingsUpdate;
      const e = firstError(p?.errors);
      if (e || !p?.settings) return setNotice({ kind: 'error', text: e?.text ?? t('The action could not be completed.') });
      qc.setQueryData(cacheKey, { flxAiSystemSettings: p.settings });
      setOn(p.settings.on);
      setModel(p.settings.model);
      setVals(Object.fromEntries(p.settings.fields.map((f) => [f.key, f.value]))); // the server may have clamped a value
      setNotice({ kind: 'success', text: t('Saved.') });
    },
    onError: (e) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  const set = (k: string, v: string) => setVals((x) => ({ ...x, [k]: v }));
  const submit = () => save.mutate({ input: { source: s.source, on, model, values: s.fields.map((f) => ({ key: f.key, value: vals[f.key] ?? '' })) } });
  const back = adminUrl + 'admin.php?page=flx-ai-systems';

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">
        <strong>{s.label}</strong> — {s.description || t('AI settings of this plugin. Values are stored in Flux AI and read by the plugin.')}
      </p>
      <p>
        <a href={back}>← {t('Connected systems')}</a>
      </p>
      <Card>
        <Field label={t('AI')} hint={t("When off, the plugin's model requests are rejected; its tools are turned off separately on the Tools screen.")}>
          <label className="flxai-check">
            <input type="checkbox" checked={on} onChange={(e) => setOn(e.target.checked)} /> {t('This plugin may use AI')}
          </label>
        </Field>
        <Field label={t('Model')} hint={t('Empty uses the default model.')}>
          <ModelInput value={model} onChange={setModel} placeholder={s.defaultModel} />
        </Field>
        {s.fields.map((f) => (
          <Field key={f.key} label={f.label} hint={f.help || undefined}>
            {f.type === 'toggle' ? (
              <label className="flxai-check">
                <input type="checkbox" checked={vals[f.key] === '1'} onChange={(e) => set(f.key, e.target.checked ? '1' : '0')} /> {t('On')}
              </label>
            ) : f.type === 'textarea' ? (
              <textarea className="flxai-input" rows={4} value={vals[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} />
            ) : f.type === 'number' ? (
              <input className="flxai-input is-small" type="number" step="any" min={f.min ?? undefined} max={f.max ?? undefined} value={vals[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} />
            ) : f.type === 'select' ? (
              <select className="flxai-input" value={vals[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)}>
                {f.options.map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.label}
                  </option>
                ))}
              </select>
            ) : f.type === 'model' ? (
              <ModelInput value={vals[f.key] ?? ''} onChange={(v) => set(f.key, v)} placeholder={model || s.defaultModel} />
            ) : (
              <input className="flxai-input" value={vals[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} />
            )}
          </Field>
        ))}
        <Field label={t('Tools')}>
          {s.toolsTotal > 0 ? (
            <span>
              {t('%1$s / %2$s tools on', num(s.toolsOn), num(s.toolsTotal))} · <a href={`${adminUrl}admin.php?page=flx-ai-tools#src=${encodeURIComponent(s.source)}`}>{t('Manage tools')}</a>
            </span>
          ) : (
            t('This plugin has no tools.')
          )}
        </Field>
        {!s.declared && <p className="flxai-hint">{t('This plugin declares no settings with the Flux AI SDK; only on/off and the model can be set.')}</p>}
        {s.sdkVersion && <p className="flxai-hint">{t('Flux AI SDK %s loaded.', s.sdkVersion)}</p>}
        <div className="flxai-appr-acts">
          <button type="button" className="flxai-btn is-primary" disabled={save.isPending} onClick={submit}>
            {t('Save')}
          </button>
        </div>
      </Card>
    </>
  );
}
