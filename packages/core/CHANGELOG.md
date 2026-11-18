# Changelog

## 0.4.8
- `mergeLayout`: a menu sub-page whose slug belongs to a module with a native screen gets `moduleId` (FluxWP Panel › Update settings).

## 0.4.7
- Strings (10 languages): `boot.*` for the app loading screen, `settings.poweredBy` and `settings.website` for Settings › About.

## 0.4.6
- npm: README with install and usage, description and keywords.

## 0.4.5
- `setClientEnhancer()`: bir paket, hesap istemcisini (`clientFor`) saran bir taşıyıcı kaydedebilir (FluxApp GraphQL taşıyıcısı). Kayıtlı yoksa davranış aynı.


## 0.4.4
- API client: `throwOnError: false` returns a 4xx/5xx JSON body as a `raw` result (GraphQL error bodies). `../graphql` reaches `/graphql` next to `/wp-json/`, also under a subdirectory install.
- Strict TS (`tsconfig.strict.json`): optional fields that may be `undefined` say so; `fetch` gets no `undefined` init keys. No behaviour change.

## 0.4.2
- API client sends no cookies with token requests (`credentials: omit`, XHR `withCredentials = false`). A wp-admin login cookie shared from the app web views made WordPress ignore the token (401 `flxapp_invalid_token`).

## 0.4.1
- Demo: Flux AI module entry (opens the native screen), 🙏 replaced in demo chats, English strings for the new demo texts.

## 0.4.0
- Flux Chat 1.37/1.38 contract: chat `channel`, bootstrap `channels`, `chats` channel filter; messages carry `deleted`, `del` and `revokeUntil`. `deleteMessage(id, 'everyone' | 'panel')`, `isDeletedMessage()`, `canDeleteForEveryone()`.
- Demo: a site-chat conversation, a message deleted by the contact, delete for everyone; Flux AI demo chats (scheduled task, waiting for approval, archived).

## 0.3.4
- Reject API paths that resolve outside the site origin or its base path, including backslash and protocol-relative hosts, before a credential is attached.
- Account ids include the install path (`host/path#userId`). Root installs stay `host#userId`. Old stored ids still parse.

## 0.3.3
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.
