---
"@fluxwp/modules": patch
"@fluxwp/graphql": minor
---
Screen shell (Flux GraphQL 0.4.0): page-wide result store (`flxStoreKey`, `flxStoreGet`, `flxStoreSet`, `flxStoreMarkStale`) shared by every bundle on the page and seeded from the server preload; `useFlxQuery` shows a stored result at once and refreshes it in the background; queries started in the same tick go out in one batch request (`batch: false` turns it off); a mutation marks stored results stale. Modules: `preloadScreen(id)` loads a screen's code before it mounts, so it paints without a loading flash.
