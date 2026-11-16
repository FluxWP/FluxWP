# @fluxwp/graphql

Typed client for Flux GraphQL (`/graphql`, plugin `flx-graphql`). Every screen (wp-admin, FluxWP desktop, mobile, headless) uses the same generated operations and types.

## Writing an operation

1. Put the operation in the plugin that ships it: `plugins/<slug>/graphql/operations/<name>.graphql`. Name it PascalCase with the `Flx` prefix (`FlxTasksList`); fragments are shared inside the plugin.
2. `bun run --filter @fluxwp/graphql generate` writes:
   - `src/generated/operations.ts`: one `FlxOperation<Result, Variables>` constant per operation;
   - `src/generated/types.ts`: result, variable, input and enum types;
   - `plugins/<slug>/graphql/persisted-queries.json`: `{ sha256: document }`.
3. The plugin registers its manifest so the server runs the operation by id:

```php
add_filter( 'flx_gql_persisted_manifests', function ( $m ) {
	$m['flx-tasks'] = FLXG_DIR . 'graphql/persisted-queries.json';
	return $m;
} );
```

## Using it

```ts
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery, useFlxMutation } from '@fluxwp/graphql/react';
import { FlxTasksList, FlxTaskSave } from '@fluxwp/graphql/operations';

const gql = createGraphQLClient({ api }); // api = the site's @fluxwp/core client
const data = await gql.request(FlxTasksList, { first: 20 });

// React: <FlxGraphQLProvider client={gql} scope={accountKey}> … </FlxGraphQLProvider>
const list = useFlxQuery(FlxTasksList, { first: 20 });
const save = useFlxMutation(FlxTaskSave);
```

Reads go out as `GET /graphql?queryId=…` (LiteSpeed can cache public ones), writes as `POST`. Errors throw `FlxGraphQLError` with `code` (`FORBIDDEN`, `NOT_FOUND`, …); input problems of a mutation come back in its payload's `errors` (`payloadErrors()` maps them to form fields).

## Schema

`schema/schema.graphql` is a snapshot of a test site with every GraphQL package. Refresh it with `bun run --filter @fluxwp/graphql schema` (needs PHP + wp-cli; see `tools/tests/wp-graphql/site.sh`). CI (`.github/workflows/graphql.yml`) fails when the snapshot, the generated files or a client's types are out of date, and on a breaking schema change unless the PR has the `graphql-breaking` label.
