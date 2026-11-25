---
"@fluxwp/modules": minor
"@fluxwp/graphql": minor
---

Mail: new `flxi-mail` screen for Settings, Templates (including the template editor), the send log and the system check. Same fields as the classic pages, on FluxWP tokens; data over the `flxMail*` GraphQL operations (write-only SMTP and IMAP secrets). Also available as a FluxApp module.

Mail screen uses the shared `Screen` header and tabs; Send, Queue, Blocklist and Connections show the PHP form the page prints (island). The classic-view link is gone.
