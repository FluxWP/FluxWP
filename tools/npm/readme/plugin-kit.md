# @fluxwp/plugin-kit

[![npm](https://img.shields.io/npm/v/@fluxwp/plugin-kit)](https://www.npmjs.com/package/@fluxwp/plugin-kit)
[![license](https://img.shields.io/npm/l/@fluxwp/plugin-kit)](#license)

**Build wp-admin screens for your WordPress plugin in React and TypeScript**, with typed GraphQL
(WPGraphQL) queries, persisted queries and server-side preloaded data. Your screens open inside the same
fast single-page admin shell as the [FluxWP](https://fluxwp.com) plugins: no page reload between screens,
light / dark theme, translations.

- **Tiny bundles.** React, React Query, i18next and the FluxWP UI kit come from a shared runtime that the
  Flux GraphQL plugin loads once per page. A plugin screen is usually a few KB.
- **End-to-end types.** `flx-kit codegen` turns your `.graphql` operations into TypeScript types and
  ready-to-use operation constants, checked against the site's schema.
- **Fast first paint.** PHP preloads the first query, so the screen renders without a spinner.
- **Vite preset.** `fluxScreens()` does the wiring: runtime imports, scoped CSS, code splitting.

Needs the **Flux GraphQL** plugin (`flx-graphql` 0.5 or newer) on the WordPress site.

## Install

```sh
npm i -D @fluxwp/plugin-kit react @tanstack/react-query react-i18next typescript vite
# or: bun add -d … / pnpm add -D …
```

## Project layout

```
my-plugin/
  my-plugin.php                         registers the screen and the persisted queries (below)
  graphql/operations/FlxMyOrders.graphql
  graphql/schema.graphql                your own GraphQL types, if the plugin registers any
  screens/index.ts                      export const screens = { 'my-orders': () => import('./Orders') }
vite.config.ts
```

```ts
// vite.config.ts
import { fluxScreens } from '@fluxwp/plugin-kit/vite';

export default fluxScreens({ entry: 'my-plugin/screens/index.ts', outDir: 'my-plugin/assets/flux' });
```

```graphql
# my-plugin/graphql/operations/FlxMyOrders.graphql
query FlxMyOrders {
  viewer { name }
}
```

```sh
npx flx-kit codegen my-plugin   # → screens/generated/{types,operations}.ts + graphql/persisted-queries.json
npx vite build                  # → assets/flux/flux-screens.js (+ chunks, css, chunks.json)
```

## A screen

```tsx
// my-plugin/screens/Orders.tsx
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { useHost } from '@fluxwp/host';
import { Loading } from '@fluxwp/ui';
import { FlxMyOrders } from './generated/operations';

export default function Orders() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Hello />
    </FlxGraphQLProvider>
  );
}

function Hello() {
  const { t } = useTranslation('my');
  const q = useFlxQuery(FlxMyOrders, {}); // q.data is typed from the schema
  if (!q.data) return <Loading />;
  return <h1>{t('hello', { name: q.data.viewer?.name })}</h1>;
}
```

`screens/index.ts` may also export `resources` (`{ en: { my: { hello: 'Hello {{name}}' } }, tr: { … } }`)
for i18next.

## PHP side

```php
add_filter( 'flx_gql_persisted_manifests', function ( $m ) {
	$m['my-plugin'] = __DIR__ . '/graphql/persisted-queries.json';
	return $m;
} );

add_action( 'admin_menu', function () {
	add_menu_page( 'Orders', 'Orders', 'read', 'my-orders', function () {
		if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
			return;
		}
		echo 'This screen needs the Flux GraphQL plugin.';
	} );
	if ( function_exists( 'flx_admin_screen' ) ) {
		flx_admin_screen( 'my-orders', array(
			'module'     => 'my-orders',
			'assets_dir' => __DIR__ . '/assets/flux/',
			'assets_url' => plugin_dir_url( __FILE__ ) . 'assets/flux/',
			'preload'    => array( array( 'FlxMyOrders' ) ), // first data ships with the page
		) );
	}
}, 20 );
```

### Your own GraphQL types

Register them in PHP with the Flux GraphQL kit (`flx_gql_type`, `flx_gql_query`, `flx_gql_mutation` on
`flx_gql_register`; every field declares a capability). To type-check your screens against them, export the
schema of a site with your plugin active and keep your part in the plugin:

```sh
wp flx-gql schema --output=schema.graphql --owners=owners.json
npx flx-kit schema-split schema.graphql owners.json my-plugin   # → my-plugin/graphql/schema.graphql
```

## CLI

| Command | Does |
|---|---|
| `flx-kit codegen <plugin-dir> [--check]` | Types, operation constants and `persisted-queries.json` for the plugin's operations (`--check`: fail if out of date, for CI) |
| `flx-kit schema-split <schema> <owners> <plugin-dir>` | Writes the plugin's own part of a site schema |

## Rules

- Import only what the runtime provides: `react`, `react/jsx-runtime`, `react-dom`, `react-dom/client`,
  `@tanstack/react-query`, `i18next`, `react-i18next`, `@fluxwp/core`, `@fluxwp/tokens`, `@fluxwp/ui`,
  `@fluxwp/graphql`, `@fluxwp/graphql/react`, `@fluxwp/host`. Anything else is bundled with your screens;
  other `@fluxwp/*` imports fail the build.
- Operation names start with `Flx` (`FlxMyOrders`), one operation per file. Prefix screen ids with your
  plugin (`acme-orders`).
- Your CSS is scoped under `.flux-root`; the `@fluxwp/ui` styles are already on the page.

Complete example plugin:
[`example/acme-hello`](https://github.com/FluxWP/FluxWP/tree/main/packages/plugin-kit/example/acme-hello).
