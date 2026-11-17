# @fluxwp/runtime

## 0.4.0
- Manifest kaynakları, aksiyonlar ve form kayıtları GraphQL'den de çalışır (`graphql: {op, id, variables, data}`). İstek yalnız persisted `queryId` taşır (strict sorgu modu), sorgular önbelleklenebilir GET, mutation POST. Sayfalama/arama/filtre değerleri değişken olur (`first`/`after`, `pageInfo.hasNextPage` → `paging.has_next`). İlk GraphQL hatası ya da payload `errors { code message field }` `ApiError` olur; alan hataları formda alanın altında görünür.

## 0.3.6
- `NavEntry.children`: a package's wp-admin sub-pages, so drawers and sidebars can list its sub-tabs.

## 0.3.5
- Giriş öncesi `loadBootstrap` da kayıtlı istemci geliştiricisinden geçer (FluxApp GraphQL taşıyıcısı).


## 0.3.3
- Flux AI stream request omits cookies (token only).

## 0.3.2
- `flxai-chat` (Flux AI site manifest chat screen) is a known native screen id.

## 0.3.1
- A panel link whose own screen is a web page stays in the same webview. Before, it pushed a new webview on the same page, which SSO-redirected back and pushed again forever (endless spinner on Home / FluxWP Panel).

## 0.3.0
- Chat engine: `deleteMessage(m, scope)` keeps the returned row (content stays, folded on screen); `channel` view filter. `deleteNote` still works.

## 0.2.4
- The AI stream URL is resolved before the access token is attached.

## 0.2.3
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.

## 0.2.2

- Hesabın simgesi `/me` ya da bootstrap markasından güncellenir. Site simgesi boşsa tema veya Flux Admin logosu kayda yazılır; hesabı silmeden logo gelir.

## 0.2.1

- Yazı kaydı düzenleyicinin HTML gövdesini de gönderir. Gövde boşsa eskisi gibi yalnız başlık ve özet gider.

## 0.2.0

- Shared module engines for the native screens: WhatsApp and Tasks (platform ports instead of `document` / `localStorage`), plus post, media, social, studio, AI stream and TV remote helpers.
- Picked-file uploads go through `uploadPickedMedia`. Form fields keep `uploadMedia`.
