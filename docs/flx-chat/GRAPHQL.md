# Flux Chat GraphQL (bölüm 1)

Uç: `/graphql`. Temsilci `flxc_use`, yönetici `flxc_manage`. Rapor cap'leri (`flx_reports_manage`, `flx_reports_receive`, `flx_reports_view`) bu paketin kataloğunda. İşlemler `plugins/flx-chat/graphql/operations/` (18). Servisler `includes/services/`. Şema `includes/graphql/schema.php`.

REST `flx-chat/v1` duruyor. `apps/mobile` ve `packages/core` aynı uçları kullanır.

## Görünürlük

Sohbet, mesaj, kullanım, dışa aktarma, öneri, dekont ve sohbete bağlı kayıt: hedef sohbet `flxc_user_can_see_chat`. Göremezse `NOT_FOUND` veya `null`. `flxChatCheckNumber` göremediği sohbetin kimliğini ve başlığını vermez.

Klasik REST'te bu kontrolün olmadığı yerler bu sürümde değiştirilmedi: dekont indirme (`flxc_receipt_download`), admin-post tek sohbet dışa aktarma, `POST /check-number`, `GET /records/donations/{id}` ve `GET /records/certificates/{id}?chat=`.

Dekont ayırma GraphQL'de eşleme ile aynı yetkiyi ister (`flxc_can_record_donation` / `flxc_can_record_certificate`). Klasik ayırma bu yetkiye bakmaz.

## Kişi

`FlxChat.person` telefonla, tek sorguda yüklenir. Temsilci yalnız görebildiği sohbetin telefonundaki kişiyi görür (`flxcrm_gql_can_see_contact`). CRM'e özel alanlar yine `flxcrm_view`.

## Canlı güncelleme

`flxChatUpdates(since:, after:, chatId:, afterChat:)`. `since` ve `rev` milisaniye dizisidir (GraphQL `Int` 32 bit). Sayfa en çok 50 sohbet, `rev` artan. `truncated` ise istemci `continueRev` ve `continueId` ile devam eder; cevaptaki `rev` (tavan) ile sormak sığmayan sohbeti atlar. `after` açık sohbetin son mesaj kimliğidir.

İstemci 10 saniyede bir sorar ve sekme gizliyken durur.

## Medya

Giden dosya `POST /flx/v1/upload` bağlam `flx-chat/outbound`, sonra `flxChatMessageSend.uploadId`. `voice: true` ses notu (`audio` + `ptt`); `voice: false` ses dosyası. İndirme adresleri izleyiciye özeldir (nonce / HMAC) ve paylaşılan önbellek etiketine girmez. Binary indirme REST'te kalır.

## Bağış ve sertifika

`flxChatDonationRecord` mevcut `flxc_create_donation` çağrısını yapar. Sertifika aynı şekilde `flxc_make_certificate`. Donate ve Certificates GraphQL'i gelince ekran onların mutation'ına geçer. İkinci kalıcı API yok.

## Sonraki bölümler

- Asistan sayfası, ayarlar, rapor ekranı.
- Rapor gönderimi: `spawn_cron` değil. Jobs ile aynı jetonlu loopback (kalıcı istisna) + `flxChatReportStatus` + `flxChatReportRun`.
- Ayarlardaki kurulum adresi `?t=<secret>` taşır. `FlxChatSettings` bu adresi vermez. Yöneticiye özel `flxChatWebhookUrlReveal` (flx-social `flxSocialTickKeyReveal` kalıbı).
- Geçmiş içe aktarma, AI düzenleyici / test / prompt turu ve bench adımı PHP ~60 sn sınırını aşabilir. Adım adım ya da mevcut loopback.
- Kişi kartındaki sohbet bölümü `gql/flx-crm-2` (PR #333) sonra.

## wp-admin

Klasik sohbet sayfası varsayılan kalır. React ekranı (`packages/modules/src/whatsapp`) GraphQL kullanır; bağış/sertifika sihirbazı, dekont eşleme, hafıza, hatırlatıcı, kara liste yönetimi, geçmişi temizleme, toplu dışa aktarma, asistan kullanımı, mesaj düzenleme ve herkesten silme arayüzü klasik sayfadadır.
