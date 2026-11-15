# Strict TypeScript

Packages listed in `strict.json` extend `tsconfig.strict.json` (base + `exactOptionalPropertyTypes`,
`noImplicitOverride`, `noFallthroughCasesInSwitch`) and may not use explicit `any` (`no-any.ts`, CI step
"No explicit any" in `graphql.yml`).

A package joins when its GraphQL work package lands:

1. `"extends": "../../tsconfig.strict.json"` in its `tsconfig.json`.
2. Add its folder to `strict.json`.
3. `bunx turbo run typecheck` and `bun tools/ts-strict/no-any.ts` pass.

Optional properties that may hold `undefined` say so: `name?: string | undefined`.
Generated code (`src/generated/`) is skipped by the any check.
