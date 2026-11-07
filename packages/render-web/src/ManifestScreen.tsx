// Picks the view for a manifest screen. Unknown type or a render error opens the web fallback.
import { Component, type ReactNode } from 'react';
import type { Manifest } from '@fluxwp/manifest';
import { screenOf, useManifest, useRenderer } from '@fluxwp/runtime';
import { ContentScreenView, SettingsScreenView, TabsScreenView } from './MiscScreens';
import { DashboardScreenView, DetailScreen } from './DetailScreen';
import { FormScreen } from './FormScreen';
import { ListScreen } from './ListScreen';

class Boundary extends Component<{ fallback: ReactNode; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() {
    return { failed: true };
  }
  componentDidCatch(e: unknown) {
    console.warn('[manifest] render error, opening web fallback', e);
  }
  render() {
    return this.state.failed ? this.props.fallback : this.props.children;
  }
}

const webPath = (m: Manifest) => m.fallback?.web ?? (m.menu ? (m.menu.includes('.php') ? m.menu : `admin.php?page=${m.menu}`) : null);

export function ManifestScreen({ module, screen, params }: { module: string; screen: string; params: Record<string, string> }) {
  const { t, nav } = useRenderer();
  const q = useManifest();
  const found = screenOf(q.data?.modules, module, screen);
  if (!found) {
    return q.isPending ? <div className="fx-skel" /> : <div className="fx-empty"><strong>{t('shell.notFound')}</strong></div>;
  }
  const { manifest, screen: model } = found;
  const path = webPath(manifest);
  const fallback = (
    <div className="fx-empty">
      <strong>{t('shell.openedInWeb')}</strong>
      {path && <button type="button" className="fx-btn" onClick={() => nav.push({ kind: 'web', path })}>{t('core.openInPanel')}</button>}
    </div>
  );
  let body: ReactNode;
  switch (model.type) {
    case 'list':
      body = <ListScreen manifest={manifest} screen={model} params={params} />;
      break;
    case 'detail':
      body = <DetailScreen manifest={manifest} screen={model} params={params} />;
      break;
    case 'form':
      body = <FormScreen manifest={manifest} screen={model} params={params} />;
      break;
    case 'dashboard':
      body = <DashboardScreenView manifest={manifest} screen={model} params={params} />;
      break;
    case 'settings':
      body = <SettingsScreenView manifest={manifest} screen={model} />;
      break;
    case 'content':
      body = <ContentScreenView manifest={manifest} screen={model} params={params} />;
      break;
    case 'tabs':
      body = <TabsScreenView manifest={manifest} screen={model} params={params} />;
      break;
    case 'web':
      return (
        <div className="fx-empty">
          <strong>{t('shell.openedInWeb')}</strong>
          <button type="button" className="fx-btn" onClick={() => nav.push({ kind: 'web', path: model.path })}>{t('core.openInPanel')}</button>
        </div>
      );
    default:
      return fallback;
  }
  return <Boundary fallback={fallback}>{body}</Boundary>;
}
