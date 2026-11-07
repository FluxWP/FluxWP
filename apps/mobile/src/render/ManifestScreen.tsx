// m/[module]/[screen]: picks the renderer for the screen type. Unknown type, missing screen or a render
// error opens the same page in the webview (plan §6), with a small note.
import { Component, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { screenOf, useManifest } from '@fluxwp/runtime';
import type { Manifest } from '@fluxwp/manifest';
import { EmptyState, Skeleton } from '@/ui/primitives';
import { WebScreen } from '@/web/WebScreen';
import { DashboardScreen } from './DashboardScreen';
import { DetailScreen } from './DetailScreen';
import { FormScreen } from './FormScreen';
import { ListScreen } from './ListScreen';

class Boundary extends Component<{ fallback: ReactNode; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() {
    return { failed: true };
  }
  componentDidCatch(e: unknown) {
    // Local log only (KVKK: nothing leaves the device without opt-in).
    console.warn('[manifest] render error, opening web fallback', e);
  }
  render() {
    return this.state.failed ? this.props.fallback : this.props.children;
  }
}

export function ManifestScreen({ module, screen, params }: { module: string; screen: string; params: Record<string, string> }) {
  const { t } = useTranslation();
  const q = useManifest();
  const found = screenOf(q.data?.modules, module, screen);
  if (!found) return q.isPending ? <Skeleton /> : <EmptyState icon="alert" title={t('mobile.shell.notFound')} />;
  const { manifest, screen: s } = found;
  const web = fallbackPath(manifest);
  const fallback = web ? <WebScreen path={web} note={t('shell.openedInWeb')} /> : <EmptyState icon="alert" title={t('mobile.shell.notFound')} />;
  let body: ReactNode;
  switch (s.type) {
    case 'list':
      body = <ListScreen manifest={manifest} screen={s} params={params} />;
      break;
    case 'detail':
      body = <DetailScreen manifest={manifest} screen={s} params={params} />;
      break;
    case 'form':
      body = <FormScreen manifest={manifest} screen={s} params={params} />;
      break;
    case 'dashboard':
      body = <DashboardScreen manifest={manifest} screen={s} params={params} />;
      break;
    case 'web':
      body = <WebScreen path={s.path} />;
      break;
    default:
      return fallback;
  }
  return <Boundary fallback={fallback}>{body}</Boundary>;
}

const fallbackPath = (m: Manifest) => m.fallback?.web ?? (m.menu ? (m.menu.includes('.php') ? m.menu : `admin.php?page=${m.menu}`) : null);
