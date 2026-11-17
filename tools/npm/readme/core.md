# @fluxwp/core

[![npm](https://img.shields.io/npm/v/@fluxwp/core)](https://www.npmjs.com/package/@fluxwp/core)
[![license](https://img.shields.io/npm/l/@fluxwp/core)](#license)

**A WordPress REST client and sign-in toolkit in TypeScript**, platform independent (browser, wp-admin,
Tauri desktop, React Native). The base of the [FluxWP](https://fluxwp.com) apps.

- **REST client** for `/wp-json/`: bearer tokens or application passwords, one retry after 401, extra
  headers (nonce), same-origin checks for every path, upload progress, typed `ApiError`.
- **Sign-in:** OAuth 2 with PKCE, WordPress application passwords, one-time codes, API keys; site discovery.
- **Accounts:** several WordPress sites side by side, token storage, sessions.
- **i18n:** i18next setup with 10 languages and locale normalisation.

## Install

```sh
npm i @fluxwp/core
```

## Usage

```ts
import { createClient, ApiError } from '@fluxwp/core';

const api = createClient({
  siteUrl: 'https://example.com',
  getCredentials: async () => ({ scheme: 'basic', token: btoa('admin:' + appPassword) }),
});

const posts = await api<{ id: number }[]>('wp/v2/posts', { query: { per_page: 5 } });
```

Inside wp-admin (cookie + nonce):

```ts
const api = createClient({
  siteUrl: location.origin,
  getCredentials: async () => null,
  credentials: 'same-origin',
  extraHeaders: () => ({ 'X-WP-Nonce': wpApiSettings.nonce }),
});
```

For GraphQL, pass this client to [`@fluxwp/graphql`](https://www.npmjs.com/package/@fluxwp/graphql).
