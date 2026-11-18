import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { clientFor, platform, type Account } from '@fluxwp/core';
import { ModuleHostProvider, type ModuleHost } from '@fluxwp/modules';
import { useAccount, useBootstrap, useMe, useShellModel, useSiteConfig } from '@fluxwp/runtime';
import { Sidebar } from '../components/Sidebar';
import { TitleBar } from '../components/TitleBar';
import { useSidebar } from '../state/sidebar';
import { startUpdateChecks } from '../state/update';
import { CommandPalette } from '../components/CommandPalette';
import { useNav } from '../state/hooks';
import { useRoute } from '../state/route';
import { AddSite } from './AddSite';
import { useConnect } from '../state/connect';
import { buildMenu } from '../lib/menuGroups';
import { activeOf, fromEntry, fromNav, labelOf, type SideLink } from '../shell/links';
import { PrefsBridge, SiteSync, SyncNotifier } from '../shell/bridges';
import { Stage } from '../shell/Stage';
import { Warmup } from '../shell/Warmup';
import type { SiteBrand } from '../components/SiteLogo';
import { homeRoute, type Route } from '../state/route';

export function Shell() {
  const account = useAccount();
  const { t } = useTranslation();
  const me = useMe();
  const site = useSiteConfig();
  const boot = useBootstrap().data;
  const shell = useShellModel();
  const legacy = useNav(account);
  const { route, go } = useRoute();
  const [palette, setPalette] = useState(false);
  const collapsed = useSidebar((s) => s.collapsed);
  const booted = useRef(false);
  const host = useMemo<ModuleHost>(() => ({ mode: 'app', client: clientFor(account), scope: account.id, siteUrl: account.siteUrl, siteName: account.siteName, openUrl: (u) => void platform().openExternal(u) }), [account]);

  const configShell = site.isSuccess && !!site.data;
  const menu = useMemo(() => {
    if (configShell) {
      return {
        pins: shell.sidebar.pins.map((e) => fromEntry(e, t)),
        groups: shell.sidebar.groups.map((g) => ({ id: g.id, label: labelOf(g.label, t), collapsed: g.collapsed, items: g.items.map((e) => fromEntry(e, t)) })),
      };
    }
    const built = buildMenu(legacy);
    return { pins: built.pins.map(fromNav), groups: built.groups.map((g) => ({ ...g, items: g.items.map(fromNav) })) };
  }, [configShell, shell.sidebar, legacy, t]);

  const siteBrand = useMemo<SiteBrand>(() => ({
    name: me.data?.site.name || boot?.brand?.name || account.siteName,
    logo: boot?.brand?.logo || '',
    logoDark: boot?.brand?.logo_dark || '',
    icon: boot?.brand?.icon || me.data?.site.icon || account.siteIcon || '',
    url: account.siteUrl,
  }), [me.data?.site.name, me.data?.site.icon, boot?.brand, account.siteName, account.siteIcon, account.siteUrl]);

  const commands = useMemo(() => shell.commands.map((e) => fromEntry(e, t)), [shell.commands, t]);
  const active = activeOf(menu.pins, menu.groups, route);

  useEffect(() => startUpdateChecks(), []);
  useEffect(() => {
    if (booted.current || me.isLoading || !configShell && site.isLoading) return;
    booted.current = true;
    if (useRoute.getState().past.length) return;
    useRoute.getState().replace(configShell ? shell.home.route : homeRoute());
  }, [me.isLoading, site.isLoading, configShell, shell.home]);

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

  // "Open in app" link from a site: add that site (the sign-in itself still asks the user).
  const connectRequest = useConnect((c) => c.pending);
  const connectHandled = useConnect((c) => c.handled);
  useEffect(() => {
    if (!connectRequest || connectRequest.seq <= connectHandled) return;
    useConnect.getState().markHandled();
    go({ kind: 'add-account', siteUrl: connectRequest.siteUrl });
  }, [connectRequest, connectHandled, go]);

  const open = (next: Route) => {
    if (next.kind === 'shell' && next.name === 'search') {
      setPalette(true);
      return;
    }
    if (next.kind === 'url') {
      void platform().openExternal(next.url);
      return;
    }
    go(next);
  };

  if (route.kind === 'add-account') return <AddSite initialUrl={route.siteUrl} onCancel={() => go(homeRoute())} />;

  return (
    <div className="shell">
      <PrefsBridge />
      <SyncNotifier />
      <SiteSync />
      <Warmup account={account} ready={!me.isLoading && !(site.isLoading && !configShell)} failed={me.isError && !me.data} logo={siteBrand.icon} pins={menu.pins} groups={menu.groups} />
      <Sidebar
        account={account}
        site={siteBrand}
        pins={menu.pins}
        groups={menu.groups}
        activeId={active.id}
        childId={active.child}
        onSearch={() => setPalette(true)}
        onOpen={open}
        actions={shell.topbar.actionEntries.map((e) => fromEntry(e, t))}
      />
      <main className={'main' + (collapsed ? ' with-bar' : '')}>
        {collapsed && <TitleBar className="main-bar" />}
        <div className="main-scroll">
          <ModuleHostProvider value={host}>
            {me.isLoading ? <div className="page"><div className="card skeleton tall" /></div> : me.isError ? <div className="page"><p className="error">{t(account.needsLogin ? 'accounts.needsLogin' : 'errors.server')}</p></div> : <Stage route={route} groups={menu.groups} />}
          </ModuleHostProvider>
        </div>
      </main>
      {palette && <CommandPalette links={[...menu.pins, ...menu.groups.flatMap((g) => g.items)]} commands={commands} onClose={() => setPalette(false)} onOpen={open} />}
    </div>
  );
}

export type { Account, SideLink };
