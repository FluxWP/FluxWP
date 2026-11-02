import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { clientFor, layoutStore, platform, type Account } from '@fluxwp/core';
import { ModuleHostProvider, type ModuleHost } from '@fluxwp/modules';
import { Sidebar } from '../components/Sidebar';
import { TitleBar } from '../components/TitleBar';
import { useSidebar } from '../state/sidebar';
import { startUpdateChecks } from '../state/update';
import { CommandPalette } from '../components/CommandPalette';
import { useMe, useNav } from '../state/hooks';
import { useRoute } from '../state/route';
import { screens } from '../modules/registry';
import { WebPanel } from './WebPanel';
import { Settings } from './Settings';
import { AddSite } from './AddSite';

export function Shell({ account }: { account: Account }) {
  const { t } = useTranslation();
  const me = useMe(account);
  const nav = useNav(account);
  const { route, go } = useRoute();
  const [palette, setPalette] = useState(false);
  const [editing, setEditing] = useState(false);
  const collapsed = useSidebar((s) => s.collapsed);
  const host = useMemo<ModuleHost>(() => ({ mode: 'app', client: clientFor(account), scope: account.id, siteUrl: account.siteUrl, siteName: account.siteName, openUrl: (u) => void platform().openExternal(u) }), [account]);

  useEffect(() => void layoutStore.getState().load(account.id), [account.id]);
  useEffect(() => startUpdateChecks(), []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const mod = e.metaKey || e.ctrlKey;
      if (mod && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPalette((p) => !p);
      } else if (mod && e.key === '[') {
        e.preventDefault();
        useRoute.getState().back();
      } else if (mod && e.key === ']') {
        e.preventDefault();
        useRoute.getState().forward();
      } else if (mod && e.key === '\\') {
        e.preventDefault();
        useSidebar.getState().toggle();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  if (route.kind === 'add-account') return <AddSite onCancel={() => go({ kind: 'nav', id: 'dashboard' })} />;

  const item = route.kind === 'nav' ? (nav.find((n) => n.id === route.id) ?? nav.find((n) => n.moduleId === 'core-dashboard')) : undefined;
  let content: React.ReactNode;
  if (me.isLoading) content = <div className="page"><div className="card skeleton tall" /></div>;
  else if (me.isError) content = <div className="page"><p className="error">{t(account.needsLogin ? 'accounts.needsLogin' : 'errors.server')}</p></div>;
  else if (route.kind === 'settings') content = <Settings />;
  // Yerel ekranın alt sayfaları (ör. Görevler › Ayarlar) web panelinde açılır; ilk alt sayfa yerel ekranın kendisi.
  else if (item?.kind === 'native' && item.moduleId && screens[item.moduleId] && !(route.kind === 'nav' && route.sub && route.sub !== item.slug && route.sub !== item.children[0]?.id)) {
    const S = screens[item.moduleId]!;
    content = <S />;
  } else if (item) content = <WebPanel item={item} sub={route.kind === 'nav' ? route.sub : undefined} />;
  else {
    const S = screens['core-dashboard']!;
    content = <S />;
  }

  return (
    <div className="shell">
      <Sidebar account={account} nav={nav} activeId={item?.id ?? null} onSearch={() => setPalette(true)} editing={editing} onEditDone={() => setEditing(false)} />
      <main className={'main' + (collapsed ? ' with-bar' : '')}>
        {collapsed && <TitleBar className="main-bar" />}
        <div className="main-scroll"><ModuleHostProvider value={host}>{content}</ModuleHostProvider></div>
      </main>
      {palette && <CommandPalette nav={nav} onClose={() => setPalette(false)} />}
    </div>
  );
}
