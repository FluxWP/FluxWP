# @fluxwp/host

[![npm](https://img.shields.io/npm/v/@fluxwp/host)](https://www.npmjs.com/package/@fluxwp/host)
[![license](https://img.shields.io/npm/l/@fluxwp/host)](#license)

**The host context of a FluxWP screen.** A screen runs in wp-admin, in the FluxWP desktop app or in the
mobile app; `@fluxwp/host` gives it the same things everywhere: the signed-in site's REST client, the
site address, the cache scope and a way to open links.

## Install

```sh
npm i @fluxwp/host react
```

Usually you get it with [`@fluxwp/plugin-kit`](https://www.npmjs.com/package/@fluxwp/plugin-kit). In
wp-admin the host is provided by the Flux GraphQL shared runtime; you only call the hooks.

## Usage

```tsx
import { useHost, useModuleApi } from '@fluxwp/host';
import { createGraphQLClient } from '@fluxwp/graphql';

function Orders() {
  const host = useHost();                       // { mode, client, siteUrl, scope, openUrl, … }
  const gql = useModuleApi((api) => createGraphQLClient({ api })); // memoised per site
  // …
}
```

| Export | |
|---|---|
| `useHost()` | The current `ModuleHost`: `mode` (`'wp'` or `'app'`), `client`, `siteUrl`, `scope`, `openUrl()` … |
| `useModuleApi(factory)` | Builds an API object from the site client once per site |
| `ModuleHostProvider` | Provides a host (for tests, or when embedding screens yourself) |
| `ModuleHost` | Type of the host object |
