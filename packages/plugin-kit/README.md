# @fluxwp/plugin-kit

Add wp-admin React screens to any WordPress plugin, on the same shell as FluxWP's own screens, with typed
GraphQL operations. Needs the **Flux GraphQL** plugin (0.5+) on the site: it serves GraphQL and loads the
shared runtime (React, React Query, i18next, `@fluxwp/ui`, `@fluxwp/graphql`) once per page, so your
bundle carries only your screens (a few KB).

## Setup

```sh
bun add -d @fluxwp/plugin-kit react @tanstack/react-query react-i18next typescript vite
```

```
my-plugin/
  my-plugin.php                       flx_admin_screen() + flx_gql_persisted_manifests (below)
  graphql/operations/FlxMyOrders.graphql
  graphql/schema.graphql              your own types, if the plugin registers any (flx-kit schema-split)
  screens/index.ts                    export const screens = { 'my-orders': () => import('./Orders') … }
vite.config.ts
```

```ts
// vite.config.ts
import { fluxScreens } from '@fluxwp/plugin-kit/vite';
export default fluxScreens({ entry: 'my-plugin/screens/index.ts', outDir: 'my-plugin/assets/flux' });
```

```sh
flx-kit codegen my-plugin      # → my-plugin/screens/generated/{types,operations}.ts + graphql/persisted-queries.json
vite build                     # → my-plugin/assets/flux/flux-screens.js (+ chunks, css, chunks.json)
```

## Screen

```tsx
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { useHost } from '@fluxwp/host';
import { FlxMyOrders } from './generated/operations';

export function Orders() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return <FlxGraphQLProvider client={client} scope={host.scope}><List /></FlxGraphQLProvider>;
}
function List() {
  const { t } = useTranslation('my');
  const q = useFlxQuery(FlxMyOrders, {});   // typed from the schema
  return <h1>{t('title')} ({q.data?.flxMyOrders.totalCount ?? '…'})</h1>;
}
```

`screens/index.ts` may also export `resources` (`{ en: { my: {…} }, tr: { my: {…} } }`) for i18next.

## PHP

```php
add_filter( 'flx_gql_persisted_manifests', function ( $m ) {
	$m['my-plugin'] = __DIR__ . '/graphql/persisted-queries.json';
	return $m;
} );
add_action( 'admin_menu', function () {
	add_menu_page( 'Orders', 'Orders', 'read', 'my-orders', function () {
		if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) return;
		echo 'Flux GraphQL is needed for this screen.';
	} );
	if ( function_exists( 'flx_admin_screen' ) ) {
		flx_admin_screen( 'my-orders', array(
			'module'     => 'my-orders',
			'assets_dir' => __DIR__ . '/assets/flux/',
			'assets_url' => plugin_dir_url( __FILE__ ) . 'assets/flux/',
			'preload'    => array( array( 'FlxMyOrders' ) ),   // first data comes with the page
		) );
	}
}, 20 );
```

Your own GraphQL types are registered in PHP with the kit (`flx_gql_type`, `flx_gql_query` … on
`flx_gql_register`). To type-check against them, export the site's schema with your plugin active and keep
your part in the plugin:

```sh
wp flx-gql schema --output=schema.graphql --owners=owners.json
flx-kit schema-split schema.graphql owners.json my-plugin   # → my-plugin/graphql/schema.graphql
```

## Rules

- Import only what the runtime provides: `react`, `react/jsx-runtime`, `react-dom`, `react-dom/client`,
  `@tanstack/react-query`, `i18next`, `react-i18next`, `@fluxwp/core`, `@fluxwp/tokens`, `@fluxwp/ui`,
  `@fluxwp/graphql`, `@fluxwp/graphql/react`, `@fluxwp/host`. Other `@fluxwp/*` imports fail the build.
- Operation names start with `Flx`, one operation per file; screen ids are unique (prefix them).
- Your CSS is scoped under `.flux-root`; `@fluxwp/ui` styles are already on the page.

Example plugin: [`example/acme-hello`](https://github.com/FluxWP/FluxWP/tree/main/packages/plugin-kit/example).
