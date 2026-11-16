# Flux AI Chatbot (flx-chatbot): klasik → GraphQL eşlemesi

Paket sürümü: 0.2.0 · Önek: `flxcb_` · Sahip thread: Faz 3 / flx-chatbot · Durum: PR `gql/flx-chatbot-1`

## Özet

- REST uçları: 10 ziyaretçi + 6 temsilci · AJAX: 0 · admin-post işleyici: 1 · Admin sayfası: 2 · MCP/AI aracı: 0
- İstisna: 10 ziyaretçi ucu REST'te kalır (herkese açık widget protokolü, NDJSON akışı, yükleme, dosya servisi); ISTISNALAR.md'de satırı var.
- Servisler: `includes/services/class-flxcb-chat-service.php`, `class-flxcb-settings-service.php`; DTO'lar `class-flxcb-dto.php`. Ayar alanları ve şema `includes/settings.php` (her istekte yüklenir).
- Node tipleri: `FlxChatbotConversation` (loader `FLXCB_Conversation_Loader`).

## REST uçları (temsilci)

| Uç | → GraphQL | Not |
|---|---|---|
| `GET agent/conversations` | `flxChatbotConversations` | Yanıt biçimi servisten, değişmedi |
| `GET agent/conversations/{id}` | `flxChatbotConversation`, `flxChatbotMessages` | |
| `POST …/reply` | `flxChatbotConversationReply` | Dosya: `uploadId` |
| `POST …/close` | `flxChatbotConversationClose` | |
| `POST …/typing` | `flxChatbotConversationTyping` | |
| `DELETE agent/conversations/{id}` | `flxChatbotConversationDelete` | `manage_options` |

Klasik REST uçları servis katmanını çağırır; sözleşme testi iki yolu karşılaştırır. Eski FluxApp sürümleri bu uçları kullanır (ISTISNALAR geçici satırı).

## admin-post işleyicileri

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxcb_save` | `manage_options` / `flxcb_save` | `flxChatbotSettingsSave` | Aynı `FLXCB_Settings_Service::save` |

## Admin sayfaları

| Sayfa (`page=`) | İstemci ekranı |
|---|---|
| `flx-chatbot` (Ayarlar) | `packages/modules/src/chatbot/ChatbotSettingsScreen.tsx` (`flxcb-settings`), wp-admin varsayılanı; "Klasik görünüm" kullanıcı başına (`flxcb_ui` meta) |
| `flx-chatbot-inbox` (Sohbetler; Flux Chat tek gelen kutusuysa yok) | `ChatbotInboxScreen.tsx` (`flxcb-inbox`) |

## Tipler

| Tip | Not |
|---|---|
| `FlxChatbotConversation` | `email`, `phone`, `orderUrl` alan yetkisi ister; boşsa null |
| `FlxChatbotMessage`, `FlxChatbotMessagePage` | `afterId` ile artımlı okuma |
| `FlxChatbotAdmin`, `FlxChatbotInbox`, `FlxChatbotStats` | Ekran verisi |
| `FlxSettingsChatbot` (+ `Input`) | `flx_settings_schema`'dan |

## Önbellek tag'leri

`flx-chatbot:chats` (sohbet okumaları; yanıt/kapatma/silme/ziyaretçi mesajı temizler), `flx-chatbot:settings`.

## Açık sorular

- Sonraki adım: sohbetin kişi alanlarını (ad, e-posta, telefon) `FlxPerson` (Flux CRM) ile bağlamak.
- Ziyaretçi widget'ının GraphQL'e geçip geçmeyeceği (akış/anonim): şimdilik istisna.
- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
