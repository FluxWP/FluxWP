import { useTranslation } from 'react-i18next';
import { LogOut, Monitor, Moon, Sun, RefreshCw } from 'lucide-react';
import { brand, locales, platform, VERSION, type Account } from '@fluxwp/core';
import { useUpdate } from '../state/update';
import { logoutAccount } from '../lib/logout';
import { Avatar } from '@fluxwp/ui';
import { useAccounts } from '../state/hooks';
import { usePrefs, type ThemePref } from '../state/prefs';
import { homeRoute, useRoute } from '../state/route';

export function Settings() {
  const { t, i18n } = useTranslation();
  const { accounts, activeId } = useAccounts();
  const prefs = usePrefs();
  const go = useRoute((s) => s.go);

  const logout = async (a: Account) => {
    if (!confirm(t('settings.logoutConfirm', { site: a.siteName }))) return;
    await logoutAccount(a);
    go(homeRoute());
  };

  const themes: { id: ThemePref; icon: typeof Sun }[] = [
    { id: 'system', icon: Monitor },
    { id: 'light', icon: Sun },
    { id: 'dark', icon: Moon },
  ];

  return (
    <div className="page narrow">
      <header className="page-head"><h1>{t('settings.title')}</h1></header>

      <section className="panel">
        <h2>{t('settings.appearance')}</h2>
        <div className="setting">
          <span>{t('settings.theme')}</span>
          <div className="seg">
            {themes.map(({ id, icon: I }) => (
              <button key={id} className={prefs.theme === id ? 'on' : ''} onClick={() => prefs.setTheme(id)}>
                <I size={14} /> {t(`settings.themes.${id}`)}
              </button>
            ))}
          </div>
        </div>
        <div className="setting">
          <span>{t('settings.language')}</span>
          <div className="seg">
            {locales.map((l) => (
              <button key={l} className={i18n.language === l ? 'on' : ''} onClick={() => prefs.setLocale(l)}>
                {t(`languages.${l}`)}
              </button>
            ))}
          </div>
        </div>
      </section>

      <section className="panel">
        <h2>{t('accounts.title')}</h2>
        <ul>
          {accounts.map((a) => (
            <li key={a.id}>
              <Avatar src={a.siteIcon} name={a.siteName} size={32} square />
              <div className="grow">
                <div>{a.siteName} {a.id === activeId && <span className="pill">{t('accounts.active')}</span>}</div>
                <small className="muted">{a.userName} · {a.siteUrl.replace(/^https?:\/\//, '')} · {t(`accounts.kind.${a.authKind}`)}</small>
              </div>
              {a.needsLogin && (
                <button className="btn-ghost small" onClick={() => go({ kind: 'add-account' })}>
                  <RefreshCw size={14} /> {t('accounts.relogin')}
                </button>
              )}
              <button className="btn-ghost small danger" onClick={() => logout(a)}>
                <LogOut size={14} /> {t('accounts.logout')}
              </button>
            </li>
          ))}
        </ul>
      </section>

      <section className="panel">
        <h2>{t('settings.about')}</h2>
        <div className="setting"><span>{t('settings.app')}</span><span className="muted">{brand.name} {VERSION}</span></div>
        {platform().checkUpdate && <UpdateRow />}
        {brand.supportUrl && (
          <div className="setting"><span>{t('settings.support')}</span><a href={brand.supportUrl} target="_blank" rel="noreferrer">{t('settings.contact')}</a></div>
        )}
      </section>
    </div>
  );
}

function UpdateRow() {
  const { t } = useTranslation();
  const { status, update, progress, error, check, install } = useUpdate();
  const busy = status === 'checking' || status === 'downloading' || status === 'installing';
  let text = '';
  if (status === 'checking') text = t('update.checking');
  else if (status === 'none') text = t('update.upToDate');
  else if (status === 'downloading') text = t('update.downloading', { version: update?.version, pct: progress > 0 ? Math.round(progress * 100) + '%' : '' });
  else if (status === 'ready' || status === 'available') text = t('update.newVersion', { version: update?.version });
  else if (status === 'installing') text = t('update.installing');
  else if (status === 'error') text = t('update.failed');
  return (
    <div className="setting">
      <span>{t('update.title')}</span>
      <span className="row">
        <span className={'muted small' + (status === 'error' ? ' text-danger' : '')} title={error || undefined}>{text}</span>
        {status === 'ready' || status === 'available' ? (
          <button className="btn-primary small" onClick={() => void install()}>{t(status === 'ready' ? 'update.restart' : 'update.download')}</button>
        ) : (
          <button className="btn-ghost small" onClick={() => void check(true)} disabled={busy}>{t('update.check')}</button>
        )}
      </span>
    </div>
  );
}
