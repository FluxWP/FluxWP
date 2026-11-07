// Draws a resolved Route in place (tab roots, n/[component]).
import { Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import type { Route } from '@fluxwp/runtime';
import { NATIVE } from '@/modules/registry';
import { ManifestScreen } from '@/render/ManifestScreen';
import { EmptyState, Spinner } from '@/ui/primitives';
import { WebScreen } from '@/web/WebScreen';
import { MoreScreen } from './MoreScreen';

export function RouteView({ route }: { route: Route }) {
  const { t } = useTranslation();
  switch (route.kind) {
    case 'manifest':
      return <ManifestScreen module={route.module} screen={route.screen} params={route.params} />;
    case 'native': {
      const C = NATIVE[route.component];
      if (!C) return <EmptyState icon="smartphone" title={t('mobile.shell.native')} />;
      return (
        <Suspense fallback={<Spinner />}>
          <C params={route.params} />
        </Suspense>
      );
    }
    case 'web':
      return <WebScreen path={route.path} title={route.title} />;
    case 'shell':
      return <MoreScreen />;
    default:
      return <EmptyState icon="alert" title={t('mobile.shell.notFound')} />;
  }
}
