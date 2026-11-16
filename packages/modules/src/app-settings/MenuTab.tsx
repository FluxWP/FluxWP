// Application › Menu: the app menu is the role menu of Flux Admin; this lists the items hidden on some platform.
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAppAdminMenu } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useScreen } from './common';

export function MenuTab() {
  const { t } = useScreen();
  const q = useFlxQuery(FlxAppAdminMenu, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxs-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const m = q.data.flxAppAdminMenu;
  return (
    <>
      <p className="flxs-lead">
        {t("The app's side menu is the same as the panel menu for the user's role (order, groups, names, hidden items). Whether each item shows on web, mobile or desktop is chosen in the menu editor. An item with Web off shows only in the app.")}
      </p>
      {!m.available || !m.editorUrl ? (
        <div className="flxs-notice is-warning">{t('Platform selection needs Flux Admin 1.3.0 or later.')}</div>
      ) : (
        <>
          <p>
            <a className="flxs-btn is-primary" href={m.editorUrl}>
              {t('Open menu editor')}
            </a>
          </p>
          <h2>{t('Items hidden by platform')}</h2>
          {!m.rows.length ? (
            <p className="flxs-empty">{t('None: every item shows everywhere.')}</p>
          ) : (
            <div className="flxs-card flxs-table-wrap">
              <table className="flxs-table">
                <thead>
                  <tr>
                    <th>{t('Role')}</th>
                    <th>{t('Menu')}</th>
                    {m.platforms.map((p) => (
                      <th key={p.key}>{p.label}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {m.rows.map((r, i) => (
                    <tr key={i}>
                      <td data-label={t('Role')}>{r.role}</td>
                      <td data-label={t('Menu')}>
                        <strong>{r.menu}</strong>
                      </td>
                      {m.platforms.map((p) => {
                        const vis = r.platforms.find((x) => x.key === p.key)?.visible ?? true;
                        return (
                          <td key={p.key} data-label={p.label}>
                            <span className={'flxs-pf' + (vis ? ' is-on' : '')}>{vis ? t('Visible') : t('Hidden')}</span>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </>
  );
}
