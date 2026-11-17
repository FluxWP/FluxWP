# @fluxwp/graphql

[![npm](https://img.shields.io/npm/v/@fluxwp/graphql)](https://www.npmjs.com/package/@fluxwp/graphql)
[![license](https://img.shields.io/npm/l/@fluxwp/graphql)](#license)

**Typed GraphQL client and React hooks for WordPress** sites running Flux GraphQL (WPGraphQL inside the
`flx-graphql` plugin). Used by every [FluxWP](https://fluxwp.com) screen: wp-admin, desktop app, mobile
app and headless front ends.

- **Typed operations.** An operation is a `FlxOperation<Result, Variables>` constant; results and variables
  are checked by TypeScript.
- **Persisted queries.** Reads go out as `GET /graphql?queryId=…` (cacheable by LiteSpeed and CDNs), writes
  as `POST`. A query the server does not know yet is sent again with its document.
- **Batching.** Queries started in the same tick share one HTTP request.
- **Preload.** Data the server printed into the page fills the cache before the first render.
- **Errors you can switch on.** `FlxGraphQLError` with `code` (`FORBIDDEN`, `NOT_FOUND`, `UNAUTHENTICATED`
  …); mutation input errors come back in the payload and `payloadErrors()` maps them to form fields.
- The full schema ships as `@fluxwp/graphql/schema/full.graphql` for your own codegen.

## Install

```sh
npm i @fluxwp/graphql @fluxwp/core @tanstack/react-query react
```

Writing a WordPress plugin screen? Start with
[`@fluxwp/plugin-kit`](https://www.npmjs.com/package/@fluxwp/plugin-kit): it generates operations from your
`.graphql` files and builds the screen.

## Usage

```ts
import { createClient } from '@fluxwp/core';
import { createGraphQLClient, isFlxGraphQLError } from '@fluxwp/graphql';
import { FlxMyOrders } from './generated/operations'; // flx-kit codegen

const api = createClient({ siteUrl: 'https://example.com', getCredentials: async () => null });
const gql = createGraphQLClient({ api });

try {
  const data = await gql.request(FlxMyOrders, {});
} catch (e) {
  if (isFlxGraphQLError(e) && e.code === 'FORBIDDEN') {
    /* … */
  }
}
```

### React

```tsx
import { FlxGraphQLProvider, useFlxQuery, useFlxMutation, useFlxCan } from '@fluxwp/graphql/react';

<FlxGraphQLProvider client={gql} scope="example.com">
  <Orders />
</FlxGraphQLProvider>;

function Orders() {
  const list = useFlxQuery(FlxMyOrders, { first: 20 }); // React Query underneath
  const save = useFlxMutation(FlxMyOrderSave);
  const canEdit = useFlxCan('edit_posts');
  // …
}
```

`useFlxViewer()` returns the signed-in user and their capabilities.

## Server

Flux GraphQL runs an operation by id only from a manifest the plugin registers:

```php
add_filter( 'flx_gql_persisted_manifests', function ( $m ) {
	$m['my-plugin'] = __DIR__ . '/graphql/persisted-queries.json';
	return $m;
} );
```
