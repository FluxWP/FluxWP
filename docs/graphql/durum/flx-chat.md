# flx-chat 1.48.0

- Faz: 2
- Durum: ✱
- PR: draft

Envanter. REST `flx-chat/v1` 101 satır (94 GraphQL, 7 istisna), AJAX yok, admin-post 4 (2 GraphQL, 2 dosya istisnası), menü 4 sayfa. `flx_settings_schema` yok; ayarlar `flxc_settings`. MCP filtresi yok; AI `flxai_chat()`. SSE ucu yok. Eşleme `docs/graphql/ESLEME/flx-chat.md`. Sonraki tur: servis, şema, ekran. Kişi kartı bölümü `gql/flx-crm-2` kayıt API'sini bekler.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| `POST /wp-json/flx-chat/v1/webhook/{conn}` | flx-chat | Evolution API sabit URL'e POST atar; doğrulama `?t=` gizli anahtar | Sohbet ve mesaj okuma `flxChats`, `flxChatMessages`, `flxChatUpdates` |
| `GET /wp-json/flx-chat/v1/wacloud-media/{key}` | flx-chat | Meta Cloud API geçici dosyayı bu adresten indirir (1 saat) | Giden mesaj `flxChatMessageSend` |
| `POST /wp-json/flx-chat/v1/jobs/bg/{id}` | flx-chat | Hostinger WP-Cron çalıştırmaz; jetonlu loopback, oturum yok | Kuyruk durumu `flxChatJobs`, tarama `flxChatJobsScan` |
| `POST /wp-json/flx-chat/v1/bot/bench/bg/{id}` | flx-chat | Aynı loopback; benchmark adımı 60 sn sınırında parçalanır | `flxChatBench`, `flxChatBenchRun` |
| `GET /wp-json/flx-chat/v1/media/{id}` | flx-chat | Binary dosya ve HTTP Range; `<img>` / `<audio>` GraphQL konuşmaz | Mesajda `FlxFile.url` bu adresi verir |
| `GET /wp-json/flx-chat/v1/chats/{id}/photo` | flx-chat | Binary profil fotoğrafı | `FlxChat.avatarUrl` |
| `GET /wp-json/flx-chat/v1/app/media/{id}` | flx-chat | İmzalı süreli adres; uygulama medyayı başlıksız açar | Aynı `FlxFile` |
| `admin-post.php?action=flxc_export` | flx-chat | Toplu sohbet dosyası (en çok 1000); PHP ~60 sn | Tek sohbet metni `flxChatExport` |
| `admin-post.php?action=flxc_receipt` | flx-chat | Dekont binary; Flux Donate de bu adresi açar | `FlxFile.url` |
