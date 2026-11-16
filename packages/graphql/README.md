# @fluxwp/graphql

Typed client for Flux GraphQL (`/graphql`, plugin `flx-graphql`). Every screen (wp-admin, FluxWP desktop, mobile, headless) uses the same generated operations and types.

## Writing an operation

1. Put the operation in the plugin that ships it: `plugins/<slug>/graphql/operations/<name>.graphql`. Name it PascalCase with the `Flx` prefix (`FlxTasksList`); fragments are shared inside the plugin.
2. `bun run --filter @fluxwp/graphql generate` (also run by `bun install`) writes:
   - `src/generated/operations.ts`: one `FlxOperation<Result, Variables>` constant per operation (not committed);
   - `src/generated/types.ts`: result, variable, input and enum types (not committed);
   - `plugins/<slug>/graphql/persisted-queries.json`: `{ sha256: document }` (committed: the plugin ships it).
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

The schema snapshot of a test site with every GraphQL package is split by owner, so parallel package pull requests never edit the same file:

| File | Holds |
|---|---|
| `schema/core.graphql` | WordPress / WPGraphQL types and anything without an owner |
| `plugins/<slug>/graphql/schema.graphql` | the plugin's types; fields it adds to other types as `extend type …` |
| `themes/<slug>/graphql/schema.graphql` | the same for a theme |

The kit records who registered each type and field (`wp flx-gql schema --owners`, Flux GraphQL 0.3.1); `scripts/parts.ts` splits and assembles. `generate` builds the full schema from the parts (`schema/full.graphql`, not committed).

```sh
bun run --filter @fluxwp/graphql schema                        # every part (needs PHP + wp-cli, tools/tests/wp-graphql/site.sh)
bun run --filter @fluxwp/graphql schema -- plugins/flx-tasks   # only that plugin's part
bun packages/graphql/scripts/schema-parts.ts assemble          # print the full schema
```

CI (`.github/workflows/graphql.yml`) regenerates the parts and fails when a package part is out of date (the CI parts are the `graphql-schema-ci` artifact; `core.graphql` only warns, it follows the WordPress release CI downloads), on a breaking change against the base commit unless the PR has the `graphql-breaking` label, when a committed manifest is stale, and when a client does not typecheck against the generated types.
