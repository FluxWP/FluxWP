// Route (runtime) → expo-router href. Routes are account scoped: /<account>/… (plan §5).
import type { Href } from 'expo-router';
import type { Route } from '@fluxwp/runtime';

/** "host#1" → "host@1": '#' cannot live in a path segment. */
export const accountKey = (id: string) => id.replace('#', '@');
export const accountIdFromKey = (key: string) => key.replace('@', '#');

export function hrefFor(account: string, route: Route): Href | null {
  const a = accountKey(account);
  switch (route.kind) {
    case 'manifest':
      return { pathname: '/[account]/m/[module]/[screen]', params: { account: a, module: route.module, screen: route.screen, ...route.params } } as unknown as Href;
    case 'native':
      return { pathname: '/[account]/n/[component]', params: { account: a, component: route.component, ...route.params } } as unknown as Href;
    case 'web':
      return { pathname: '/[account]/web', params: { account: a, path: route.path, ...(route.title ? { title: route.title } : {}) } } as unknown as Href;
    case 'shell':
      if (route.name === 'accounts') return { pathname: '/[account]/accounts', params: { account: a } } as unknown as Href;
      if (route.name === 'settings') return { pathname: '/[account]/settings', params: { account: a } } as unknown as Href;
      return { pathname: '/[account]/more', params: { account: a } } as unknown as Href;
    default:
      return null;
  }
}

/** Search params minus the route's own keys = manifest params. */
export function paramsOf(all: Record<string, string | string[] | undefined>, omit: string[]): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(all)) if (!omit.includes(k) && typeof v === 'string') out[k] = v;
  return out;
}
