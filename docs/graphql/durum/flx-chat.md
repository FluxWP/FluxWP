# flx-chat 1.49.0

- Faz: 2
- Durum: ✱
- PR: #337

Bölüm 1: sohbet, mesaj, `flxChatUpdates` ve sohbet sayfasının işlemleri GraphQL'de. React WhatsApp ekranı GraphQL kullanır. wp-admin varsayılanı klasik sayfadır (bağış/sertifika sihirbazı, dekont, hafıza, hatırlatıcı, kara liste, temizlik, toplu dışa aktarma, asistan kullanımı klasikte). REST duruyor. Asistan, ayar ve rapor ekranı sonraki bölüm. Rapor gönderimi jetonlu loopback (kalıcı istisna) + durum sorgusu + çalıştırma. Gömülü React paketi `tools/fluxapp/embed-sync.sh flx-chat` ile yeniden üretildi. Eşleme `docs/graphql/ESLEME/flx-chat.md`. Kişi kartı bölümü `gql/flx-crm-2` (PR #333) sonra.

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
| `admin-post.php?action=flxc_receipt` | flx-chat | Dekont binary; Flux Donate de bu adresi açar. Klasik indirme sohbet görünürlüğü süzmez | `FlxFile.url`. GraphQL dekont mutation'ı sohbeti süzer |
| Rapor gönderimi (`flxc_reports_tick`) | flx-chat | Hostinger WP-Cron çalıştırmaz. `spawn_cron` kalıcı çözüm değil. Jobs ile aynı jetonlu loopback | Durum `flxChatReportStatus`, çalıştır `flxChatReportRun` |
