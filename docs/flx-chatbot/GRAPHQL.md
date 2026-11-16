# Flux AI Chatbot GraphQL API

Uç: `/graphql` (Flux GraphQL). Yönetici yüzü: ayarlar `manage_options`; sohbetler `flxcb_reply` (yönetici, `flxcb_reply` yetkisi ya da Flux Chat temsilcisi). Sohbet silme `manage_options`.
İşlemler `plugins/flx-chatbot/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql`.
Ziyaretçi widget'ının uçları (`/wp-json/flx-chatbot/v1/session|message|poll|handoff|contact|upload|file|rate|close|typing`) REST'te kalır: ISTISNALAR.md.

## Ayarlar

```graphql
query { flxSettings { chatbot { enabled mode color theme position welcome aiPersona hoursMode hours } } }
mutation {
  flxChatbotSettingsSave(input: { settings: { enabled: true, mode: HYBRID, color: "#2563eb", teaserDelay: 12 } }) {
    settings { enabled color } errors { code field message }
  }
}
```
`flxSettings.chatbot` ayar şemasından (`flx_settings_schema`) üretilir; kaydetme klasik formla aynı temizleme ve sınırlardan geçer, yalnız gönderilen alanlar değişir. Geçersiz `notifyEmail` → `INVALID_INPUT` (`field: notifyEmail`).
Ekran verisi: `flxChatbotAdmin { stats(days) status textDefaults pageTypes weekdays avatarUrl preview { config scriptUrl } … }`.

## Sohbetler

```graphql
query {
  flxChatbotInbox { fluxChatUrl settingsUrl canDelete agentName maxUploadMb }
  flxChatbotConversations(filter: OPEN, search: "zeynep", first: 50) { nodes { id databaseId title status unread lastMessage { role body } } pageInfo { hasNextPage endCursor } totalCount }
  flxChatbotMessages(conversationId: "5", afterId: 0, peek: false) { nodes { databaseId role body sentAt { label } file { name url } cards { title price } } lastId hasMore }
}
```
`peek: false` iken mesajları okumak ziyaretçi mesajlarını okundu yapar. Ekran `afterId` ile 4-10 sn aralıkla yeniler.

| Mutation | Girdi | Dönüş |
|---|---|---|
| `flxChatbotConversationReply` | `id`, `text`, `uploadId` (bağlam `flx-chatbot/reply`, önce `/wp-json/flx/v1/upload`) | `conversation` |
| `flxChatbotConversationClose` | `id` | `conversation` |
| `flxChatbotConversationDelete` | `id` | `deletedId` |
| `flxChatbotConversationTyping` | `id` | `ok` |

Hatalar payload `errors` içinde: `INVALID_INPUT`, `NOT_FOUND`, `FORBIDDEN`.
Önbellek tag'leri: `flx-chatbot:chats`, `flx-chatbot:settings`.
