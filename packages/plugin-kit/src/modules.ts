/**
 * Module specifiers the shared runtime (Flux GraphQL) provides to plugin bundles. Same list and order as
 * `modules` in apps/wp-embed/src/runtime.tsx (scripts/runtime-exports.ts checks it). A plugin imports these
 * and only these from React, React Query, i18next and the open @fluxwp packages; `@fluxwp/host` is
 * useHost / useModuleApi / ModuleHost.
 */
export const RUNTIME_MODULES = [
  'react',
  'react/jsx-runtime',
  'react-dom',
  'react-dom/client',
  '@tanstack/react-query',
  'i18next',
  'react-i18next',
  '@fluxwp/core',
  '@fluxwp/tokens',
  '@fluxwp/ui',
  '@fluxwp/graphql',
  '@fluxwp/graphql/react',
  '@fluxwp/host',
] as const;

export type RuntimeModule = (typeof RUNTIME_MODULES)[number];
