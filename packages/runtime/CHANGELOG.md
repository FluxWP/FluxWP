# @fluxwp/runtime

## 0.2.2

- Hesabın simgesi `/me` ya da bootstrap markasından güncellenir. Site simgesi boşsa tema veya Flux Admin logosu kayda yazılır; hesabı silmeden logo gelir.

## 0.2.1

- Yazı kaydı düzenleyicinin HTML gövdesini de gönderir. Gövde boşsa eskisi gibi yalnız başlık ve özet gider.

## 0.2.0

- Shared module engines for the native screens: WhatsApp and Tasks (platform ports instead of `document` / `localStorage`), plus post, media, social, studio, AI stream and TV remote helpers.
- Picked-file uploads go through `uploadPickedMedia`. Form fields keep `uploadMedia`.
