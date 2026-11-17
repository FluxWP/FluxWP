---
"@fluxwp/graphql": minor
"@fluxwp/modules": patch
---
wp-admin first load: `flxStoreNote` / `flxStoreRecord` record the queries a screen sends first (the wp-admin shell sends them to the server so the next open preloads them). Settings screens (roles, languages, reCAPTCHA, chatbot, SEO, analytics, theme, app settings) start their form from preloaded data, so no loading frame shows before the content.
