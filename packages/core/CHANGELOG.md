# Changelog

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
