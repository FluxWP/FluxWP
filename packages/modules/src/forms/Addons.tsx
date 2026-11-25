// Forms › Add-ons: the add-ons that ship with Flux Forms (flxFormsAddons). Flux Forms keeps them on and
// downloads nothing; an add-on PHP cannot run says which functions it misses.
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsAddons } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { errorText, useForms } from './common';

export function AddonsPage() {
  const { t } = useForms();
  const q = useFlxQuery(FlxFormsAddons, {});
  const list = q.data?.flxFormsAddons;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!list) return <Loading />;
  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('These add-ons ship with Flux Forms and are always on. Nothing is downloaded.')}</p>
      {!list.length ? <p className="flxfrm-empty">{t('No add-ons.')}</p> : null}
      <div className="flxfrm-grid2">
        {list.map((a) => {
          const blocked = a.disabledFunctions.length > 0 || a.missingFunctions.length > 0;
          const state = !a.available ? ['is-error', t('Files missing')] : blocked ? ['is-warning', t('Paused')] : a.enabled ? ['is-ok', t('On')] : ['is-soft', t('Off')];
          return (
            <section key={a.key} className={'flxfrm-card' + (a.enabled && a.available && !blocked ? ' is-active' : '')}>
              <div className="flxfrm-card__head">
                <h3>{a.title}</h3>
                <span className={'flxfrm-pill ' + state[0]}>{state[1]}</span>
              </div>
              <p className="flxfrm-muted">{a.description}</p>
              {blocked ? (
                <div className="flxfrm-note is-bad">
                  <p>
                    <strong>{t('Needs a PHP server change')}</strong> {t('(currently enabled and paused)')}
                  </p>
                  {a.disabledFunctions.length ? (
                    <p>
                      {t('disabled in php.ini:')} <code>{a.disabledFunctions.join(', ')}</code>
                    </p>
                  ) : null}
                  {a.missingFunctions.length ? (
                    <p>
                      {t('not provided by PHP or its required extension:')} <code>{a.missingFunctions.join(', ')}</code>
                    </p>
                  ) : null}
                  <p className="flxfrm-muted">{t('Ask your hosting provider to enable it, then refresh this page.')}</p>
                </div>
              ) : null}
              {!a.available ? <p className="flxfrm-bad">{t('The files of this add-on are missing. Reinstall Flux Forms.')}</p> : null}
            </section>
          );
        })}
      </div>
    </div>
  );
}
