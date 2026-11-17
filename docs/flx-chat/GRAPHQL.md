# Flux Chat GraphQL (bölüm 1 ve 2)

Uç: `/graphql`. Temsilci `flxc_use`, yönetici `flxc_manage`. Rapor cap'leri (`flx_reports_manage`, `flx_reports_receive`, `flx_reports_view`) bu paketin kataloğunda. İşlemler `plugins/flx-chat/graphql/operations/` (18). Servisler `includes/services/`. Şema `includes/graphql/schema.php`.

REST `flx-chat/v1` duruyor. `apps/mobile` ve `packages/core` aynı uçları kullanır. GraphQL mutation'ları REST'in çağırdığı servislerden geçer (`FLXC_Chat_Service`, `FLXC_Message_Service`, `FLXC_Desk_Service`); gönderim `flxc_send` içinden yapılır. Tarih alanları `FlxDateTime` (`flx_gql_datetime`). `created_at` GMT'dir (`flxc_now`). Milisaniye sayaçları (`ts`, `rev`, `lastMs`, `since`) `String` kalır çünkü GraphQL `Int` 32 bittir. Gönderimde ayrı bir hız sınırı yok; klasik yol da koymaz.

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

## Ayarlar, bağlantılar ve raporlar (bölüm 2)

Kaynak `includes/graphql/schema-admin.php`. Servisler `FLXC_Settings_Service` ve `FLXC_Report_Service`; klasik `flxc_save_settings`, `flxc_reports_post` ve `flx-chat/v1/admin/connections/*`, `admin/ai/*` bunları çağırır, REST cevapları aynı anahtar ve sırada kalır.

- `flxChatSettings` açık ayarları, hat listesini (`hasApiKey`), etiketleri ve ekranın bağlamını (kurulu paketler, varsayılan metinler, seçenekler) verir. API anahtarı, webhook sırrı ve mühürlü değer hiçbir cevapta yoktur.
- `flxChatSettingsSave` yalnız gönderilen alanları yazar; sınır ve temizleme klasik sekmeyle aynıdır (`poll_interval` 3–60 vb.). Hat satırındaki `apiKey` yaz-only: boş kalsın demek korur, `"__clear__"` siler. Klasik sekme formu `"__clear__"` yazısını komut saymaz.
- Kurulum adresi `?t=<sır>` taşır. Yalnız yönetici mutation'ı `flxChatWebhookUrlReveal` verir (önbelleğe girmez, listelenmez); `regenerate` yeni anahtar üretir.
- `flxChatConnectionTest|Webhook|Qr|Import`, `flxChatAiTest`, `flxChatAiModels` (aramalı, en çok 50, seçili modeller `ids` ile hep gelir), `flxChatGroups`, `flxChatSystemChecks` klasik REST uçlarının servisidir.
- Raporlar: `flxChatReports`, `flxChatReportPreview`, `flxChatReportsSave|Add|Delete|Send`. Kural kimliği büyük harf taşır; GraphQL kimliği olduğu gibi eşler (klasik `rtarget` `sanitize_key` ile küçülttüğü için büyük harfli kuralda sil/gönder çalışmaz; klasik olduğu gibi bırakıldı). Hiç kaydedilmemiş sitede iki başlangıç kuralı ilk okumada bir kez saklanır, böylece ekranın gösterdiği kimlik sonraki istekte geçerli kalır.
- Gönderim: `spawn_cron` yerine jobs ile aynı jetonlu loopback (`POST flx-chat/v1/reports/bg`, `flxc_bench_key`). `flxChatReportStatus` (due, running, nextAt, son loopback hatası) bir şey göndermez; `flxChatReportRun` döngüyü loopback ile başlatır. Cap: `flx_reports_manage` ya da `flxc_manage`.

## Sonraki bölümler

- Asistan sayfası (stüdyo): istatistik, istem, belge, düzenleyici/test turları, benchmark, hafıza ve işler bir ekranda. Uzun AI turları ve adımlar (PHP ~60 sn) bölüm 3–4 ile birlikte taşınır; sayfa o zamana kadar klasik kalır.
- Geçmiş içe aktarma, AI düzenleyici / test / istem turu ve bench adımı PHP ~60 sn sınırını aşabilir. Adım adım ya da mevcut loopback.
- Kişi kartındaki sohbet bölümü `gql/flx-crm-2` (PR #333) sonra.

## wp-admin

Klasik sohbet sayfası varsayılan kalır. React ekranı (`packages/modules/src/whatsapp`) GraphQL kullanır; bağış/sertifika sihirbazı, dekont eşleme, hafıza, hatırlatıcı, kara liste yönetimi, geçmişi temizleme, toplu dışa aktarma, asistan kullanımı, mesaj düzenleme ve herkesten silme arayüzü klasik sayfadadır.

Ayarlar (`flx-chat-ayarlar`, yedi sekme) ve Raporlar (`flx-chat-raporlar`) sayfaları varsayılan olarak React ekranıdır (`packages/modules/src/whatsapp/SettingsScreen.tsx`, `ReportsScreen.tsx`, ekran kabuğu `flx_admin_screen`). Paket dosyaları ya da Flux GraphQL yoksa klasik PHP sayfası açılır; `flxc_flux_ui` filtresi React'i kapatır.
