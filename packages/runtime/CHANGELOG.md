# @fluxwp/runtime

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
