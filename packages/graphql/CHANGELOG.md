# Changelog

## 0.1.0
- First version: GraphQL client on the `@fluxwp/core` API client (persisted GET for reads, POST for writes, document fallback, typed `FlxGraphQLError`), React hooks `useFlxQuery` / `useFlxMutation`, `payloadErrors()`.
- Codegen: `plugins/<slug>/graphql/operations/*.graphql` → typed `FlxOperation` constants + `plugins/<slug>/graphql/persisted-queries.json`; schema snapshot and breaking change check.
