# @fluxwp/plugin-kit

Build wp-admin screens for any WordPress plugin on the Flux runtime that ships with Flux GraphQL (0.5.0+).
React, React Query, i18next and the open `@fluxwp` packages are loaded once per page by the runtime; a plugin
bundle carries only its own screens (a few KB).

```ts
// vite.config.ts
import { fluxScreens } from '@fluxwp/plugin-kit/vite';
export default fluxScreens({ entry: 'screens/index.ts', outDir: 'assets/flux' });
```

Example: `example/acme-hello` (`bun run example`). Rules (Turkish): `docs/graphql/EKLENTI-EKRANLARI.md`.
