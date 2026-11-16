// Application › Modules: which package screens the app shows natively; a module that is off opens as an admin page.
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAppAdminModules, FlxAppAdminModulesSave } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { payloadError, SaveBar, Switch, useScreen } from './common';

export function ModulesTab() {
  const { t, tn, told, fail } = useScreen();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAppAdminModules, {});
  const [on, setOn] = useState<Set<string> | null>(null);
  const [dirty, setDirty] = useState(false);
  useEffect(() => {
    if (q.data && !dirty) setOn(new Set(q.data.flxAppAdminModules.filter((m) => m.on).map((m) => m.id)));
  }, [q.data, dirty]);

  const save = useFlxMutation(FlxAppAdminModulesSave, {
    onSuccess: (r) => {
      const p = r.flxAppAdminModulesSave;
      const err = payloadError(p?.errors);
      if (!p?.modules || err) return fail(err ? new Error(err) : null);
      qc.setQueryData(flxQueryKey(scope, FlxAppAdminModules, {}), { flxAppAdminModules: p.modules });
      setDirty(false);
      told(t('Saved.'));
    },
    onError: fail,
  });

  if (q.isLoading || (!on && !q.isError)) return <Loading />;
  if (q.isError || !q.data || !on) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const mods = q.data.flxAppAdminModules;
  const kinds: Record<string, string> = { manifest: t('Manifest'), native: t('Native screen'), web: t('Web view') };

  return (
    <>
      <p className="flxs-lead">
        {t("Plugin screens in the app. Manifest modules are drawn from the same definition on desktop and mobile; native modules open with the app's own screen. A disabled module's menu opens as an admin page in the app. Who sees what follows capabilities and the role menu.")}
      </p>
      {!mods.length ? (
        <p className="flxs-empty">{t('No modules registered.')}</p>
      ) : (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            save.mutate({ input: { on: [...on] } });
          }}
        >
          <div className="flxs-card flxs-table-wrap">
            <table className="flxs-table">
              <thead>
                <tr>
                  <th>{t('On')}</th>
                  <th>{t('Module')}</th>
                  <th>{t('In the app')}</th>
                  <th>{t('Menu')}</th>
                  <th>{t('Capability')}</th>
                  <th>{t('Version')}</th>
                </tr>
              </thead>
              <tbody>
                {mods.map((m) => {
                  const isOn = on.has(m.id);
                  const kind = isOn ? m.kind : 'web';
                  return (
                    <tr key={m.id}>
                      <td data-label={t('On')}>
                        <Switch
                          checked={isOn}
                          label={m.title}
                          onChange={(v) => {
                            setOn((o) => {
                              const n = new Set(o);
                              if (v) n.add(m.id);
                              else n.delete(m.id);
                              return n;
                            });
                            setDirty(true);
                          }}
                        />
                      </td>
                      <td data-label={t('Module')}>
                        <strong>{m.title}</strong> <code>{m.id}</code>
                        {m.errors.length > 0 && <div className="flxs-bad">{t('Manifest error: %s', m.errors.slice(0, 6).join(', '))}</div>}
                      </td>
                      <td data-label={t('In the app')}>
                        <span className="flxs-pf is-on">{kinds[kind] ?? kind}</span>
                        {kind === 'manifest' && m.screens > 0 && <span className="flxs-muted"> {tn('%d screen', '%d screens', m.screens, m.screens)}</span>}
                      </td>
                      <td data-label={t('Menu')}>{m.menu ? <code>{m.menu}</code> : <span className="flxs-muted">{t('Separate item')}</span>}</td>
                      <td data-label={t('Capability')}>{m.cap ? <code>{m.cap}</code> : '—'}</td>
                      <td data-label={t('Version')}>{m.version || '—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <SaveBar busy={save.isPending} dirty={dirty} />
        </form>
      )}
    </>
  );
}
