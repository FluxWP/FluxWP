# flx-chatbot 0.2.0

- Faz: 1
- Durum: ✓
- PR: #315

`gql/flx-chatbot-1`: servis katmanı, 5 sorgu + 5 mutation (+ `flxSettings.chatbot`), 9 kayıtlı işlem, sözleşme testi 84/84. React ekranları (`flxcb-settings`, `flxcb-inbox`) klasikle eşit, wp-admin varsayılanı; klasik bağlantıyla. Ziyaretçi uçları REST istisnası. Sonraki adım: FlxPerson (Flux CRM) bağı. Eşleme `ESLEME/flx-chatbot.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Chatbot ziyaretçi uçları `flx-chatbot/v1` (session, message NDJSON akışı, poll, handoff, contact, upload, file, rate, close, typing) | flx-chatbot | Herkese açık widget protokolü: anonim ziyaretçi, NDJSON akışı, multipart yükleme, dosya servisi; GraphQL burada akış ve anonim çağrı için uygun değil | Temsilci/yönetici yüzü GraphQL'de: `flxChatbotConversations`, `flxChatbotConversationReply`, `flxChatbotSettingsSave` |
