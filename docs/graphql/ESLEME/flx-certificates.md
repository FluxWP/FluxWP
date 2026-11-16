# Flux PDF & Certificates (flx-certificates): REST → GraphQL eşlemesi

Paket sürümü: 2.1.2 · Önek: `flxs_` · Dal: `gql/flx-certificates-1` · Durum: taslak (envanter). `flx_settings_schema` kaydı yok; ayarlar `flxs_settings` ve `flxs_site` seçeneklerinde.

Satır sayıları (GraphQL’e bağlanan / istisna):

| Kategori | Satır | GraphQL | İstisna |
|---|---:|---:|---:|
| REST | 28 (23 rota, 27 yöntem; retry ve cancel ayrı satır) | 27 | 1 |
| AJAX (`wp_ajax_` / nopriv) | 0 | 0 | 0 |
| admin-post | 22 (19 action; `flxs_tpl_action` dört satır) | 18 | 4 |
| Admin sayfası | 16 (12 slug, alt görünümler ayrı) | 15 ekran | 1 yönlendirme (ekran yok) |
| MCP / AI aracı | 0 | 0 | 0 |
| Ayar alanı | 24 | 23 | 0 (SMTP şifresi gizli; okunmaz, istisna değil) |
| Cron / zamanlanmış kanca | 4 | 0 (adım mutation’ları ayrı) | 4 |
| Herkese açık uç | 5 | 2 | 3 |
| Dosya indirme / yükleme | 8 | 2 (kit `uploadId`) | 6 akış |
| Kısa kod / blok | 5 | 4 | 0 (marka bloğu bu paketin şeması değil) |

İstisna satırları `docs/graphql/durum/flx-certificates.md` içindedir. `ISTISNALAR.md` bu PR’da değişmez.

## Özet

- REST: `flx-certificates/v1`, 23 `register_rest_route`, 27 yöntem bağı. İzin: `flxs_can_use` (`flxs_use` veya `flxs_manage`), `flxs_can_manage`, `flxs_can_bulk` (`flxs_bulk` veya `flxs_manage`), `flxs_can_dashboard`, iki uç `__return_true`.
- AJAX: pakette `wp_ajax_` / `wp_ajax_nopriv_` kaydı yok. Kuyruk sayfası ve yeni sertifika ekranı REST `POST /queue/run` ile pompalar.
- Admin: 12 slug. Sağlayıcılar sayfası v1.3.0’dan beri ekran değil; gizli slug Flux Mail bağlantılarına (yoksa sistem kontrolüne) yönlenir.
- MCP: `flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools` yok. FluxApp manifesti REST kaynaklarını tarif eder (`includes/app-manifest.php`).
- Kişi: `flx_person_ensure` / `flx_person_update`, bağ `certificates`. CRM varken `flx_person_pre_*` CRM’e düşer.
- PDF: TCPDF + FPDI + GD (`includes/render.php`). Gotenberg yok. Tarayıcı yedeği `assets/js/renderer.js` (pdf-lib). Sağlayıcılar e-posta / SMS / WhatsApp gönderimidir.
- Servisler (sonraki PR’lar, bu dalda yazılmadı): `FLXS_Certificate_Service`, `FLXS_Person_Service`, `FLXS_Template_Service`, `FLXS_Queue_Service`, `FLXS_Event_Service`, `FLXS_Stats_Service`, `FLXS_Settings_Service`, `FLXS_Log_Service`.

Yetkiler (`flx_gql_capabilities`, grup `certificates`): `flxs_use`, `flxs_manage`, `flxs_bulk`, `flxs_dashboard`, `flxs_own_ref`. `flxs_own_ref` ve ayar `staff_own_only` cap değil, loader `can_see` daraltmasıdır (`includes/helpers.php:243-287`).

## REST uçları

Namespace `flx-certificates/v1`. Kayıt: `includes/rest.php:6-69`. `$use` = `flxs_can_use`, `$man` = `flxs_can_manage`, `$bulk` = `flxs_can_bulk`.

| Yöntem | Yol | cap | → GraphQL | Girdi | Çıktı | Kaynak |
|---|---|---|---|---|---|---|
| GET | `/people` | `flxs_use` \| `flxs_manage` | `flxCertificatePeople` | `search` | `[FlxCertificatePerson!]!` (en çok 8) | `rest.php:18` `flxs_rest_people` |
| GET | `/directory` | aynı | `flxCertificateDirectory` | `s, page, per_page` | `FlxCertificatePersonConnection!` | `rest.php:19` `flxs_rest_directory` |
| GET | `/certificates` | aynı | `flxCertificates` | `s, status, first, after` | `FlxCertificateConnection!` | `rest.php:24` `flxs_rest_cert_list` |
| POST | `/certificates` | aynı | `flxCertificateCreate` | `FlxCertificateSaveInput` | `{ certificate, errors }` | `rest.php:25` `flxs_rest_cert_save` |
| GET | `/messages` | aynı | `flxCertificateMessages` | `s, status, channel, batch, certificate, first, after` | `FlxCertificateMessageConnection!` | `rest.php:28` `flxs_rest_messages` |
| GET | `/overview` | `flxs_dashboard` \| `flxs_manage` | `flxCertificateOverview` | — | `FlxCertificateOverview!` | `rest.php:29` `flxs_rest_overview` |
| GET | `/certificates/{id}` | use + `flxs_can_see_certificate` | `flxCertificate` | `id` | `FlxCertificate` | `rest.php:35` `flxs_rest_cert_get` |
| POST | `/certificates/{id}` | use + `flxs_user_can_edit_certificate` | `flxCertificateUpdate` | `FlxCertificateSaveInput` | `{ certificate, errors }` | `rest.php:36` |
| POST | `/certificates/{id}/files` | aynı (bugün multipart) | `flxCertificateAttachFiles` | `uploadId` (kit `flx-certificates/file`) | `{ certificate, errors }` | `rest.php:39` `flxs_rest_cert_files` |
| POST | `/certificates/{id}/send` | use + görebilme | `flxCertificateSend` | `channels, providerIds` | `{ results, errors }` | `rest.php:40` `flxs_rest_cert_send` |
| GET | `/certificates/{id}/message` | aynı | `flxCertificateMessagePreview` | `channel` | `FlxCertificateMessagePreview` | `rest.php:45` `flxs_rest_cert_message` |
| POST | `/certificates/{id}/message` | aynı | `flxCertificateSendTo` | `channel, to, name, subject, body` | `{ message, errors }` | `rest.php:46` `flxs_rest_cert_send_to` |
| GET | `/recipients` | use | `flxCertificateRecipients` | — | `[FlxCertificateRecipient!]!` | `rest.php:53` |
| POST | `/recipients` | use | `flxCertificateRecipientAdd` | `name, phone, email` | `{ recipient, errors }` | `rest.php:54` |
| DELETE | `/recipients/{rid}` | use | `flxCertificateRecipientDelete` | `id` | `{ deleted, errors }` | `rest.php:57` |
| GET | `/stats` | dashboard | `flxCertificateStats` | `from, to, ref, template, event` | `FlxCertificateStats!` | `rest.php:58` `flxs_rest_stats` |
| POST | `/certificates/{id}/ref` | use + düzenleme | `flxCertificateSetReferrer` | `userId` | `{ certificate, errors }` | `rest.php:59` |
| POST | `/certificates/{id}/status` | aynı | `flxCertificateSetStatus` | `status: ACTIVE \| REVOKED` | `{ certificate, errors }` | `rest.php:60` |
| POST | `/templates` | `flxs_manage` | `flxCertificateTemplateSave` | `FlxCertificateTemplateInput` | `{ template, errors }` | `rest.php:61` |
| POST | `/templates/{id}/thumb` | `flxs_manage` (multipart JPEG) | `flxCertificateTemplateThumb` | `uploadId` (`flx-certificates/thumb`) | `{ template, errors }` | `rest.php:62` |
| POST | `/queue/run` | use | `flxCertificateQueueStep` | — (bütçe sunucuda ≤ 15 sn) | `FlxCertificateQueueStepPayload!` | `rest.php:63` `flxs_rest_queue_run` |
| GET | `/queue/status` | use | `flxCertificateQueue` | `batch, ids` | `FlxCertificateQueue!` | `rest.php:64` |
| POST | `/messages/{id}/retry` | use | `flxCertificateMessageRetry` | `id` | `{ message, errors }` | `rest.php:65` `op=retry` |
| POST | `/messages/{id}/cancel` | use | `flxCertificateMessageCancel` | `id` | `{ message, errors }` | `rest.php:65` `op=cancel` |
| POST | `/bulk` | `flxs_bulk` \| `flxs_manage` | `flxCertificateBulkSend` | `FlxCertificateBulkInput` (`dry`) | `{ batch, counts, errors }` | `rest.php:66` `flxs_rest_bulk` |
| POST | `/providers/{id}/test` | `flxs_manage` | `flxCertificateProviderTest` | `id, to` | `{ ok, message, errors }` | `rest.php:67` |
| POST | `/public-files/{code}` | `__return_true` + HMAC | **İSTİSNA** | jeton, multipart | eksik dosya listesi | `rest.php:68` `flxs_rest_public_files` `public.php:272` |
| POST | `/lookup` | `__return_true` + oran sınırı | `flxCertificateLookup` (`public`) | `contact, website` (bal küpü) | `{ message }` (kayıt sızmaz) | `rest.php:69` `flxs_rest_lookup` |

`GET /people` arama ucudur (limit 8, `rest.php:80`). Sayfalı dizin `GET /directory` (`rest.php:607`). Connection `first` en çok 50; bugünkü REST `per_page` 100’e kadar (`rest.php:577`).

## AJAX

Kayıt yok. `includes/flx-brand/core.php:877` `flx_job_kick` imzalı admin-ajax loopback yardımcısıdır; bu paket onu bir `wp_ajax_` aksiyonuna bağlamaz.

Kuyruk pompası REST’tir, GraphQL adımı `flxCertificateQueueStep` olur:

- Kuyruk sayfası: `assets/js/admin.js:471-483`. Açılış 2,5 sn, sonra 6 sn; sekme gizliyken 4 sn. Yeni kural: ≥ 10 sn ve gizli sekmede dur.
- Yeni sertifika: `assets/js/create.js:788-815`. 0,8 sn, sonra 4 sn, en çok 40 tur. Aynı kural.

## admin-post

Hepsi nonce + cap. Dosya akışı olanlar istisna; GraphQL imzalı indirme adresi verir, gövdeyi taşımaz.

| Action | cap | → GraphQL | Kaynak |
|---|---|---|---|
| `flxs_render_missing` | `flxs_manage` veya `manage_options` | `flxCertificateRenderMissing` (en çok 25, kalan varsa tekrar) | `system-checks.php:282` |
| `flxs_tpl_action` `duplicate` | `flxs_manage` | `flxCertificateTemplateDuplicate` | `page-templates.php:4` `op` `:19` |
| `flxs_tpl_action` `toggle` | `flxs_manage` | `flxCertificateTemplateSetStatus` | `:23` |
| `flxs_tpl_action` `delete` | `flxs_manage` | `flxCertificateTemplateDelete` | `:27` |
| `flxs_tpl_action` `export` | `flxs_manage` | **İSTİSNA** (JSON + gömülü arka plan) | `:31` |
| `flxs_tpl_import` | `flxs_manage` | `flxCertificateTemplateImport` (`uploadId`, `flx-certificates/template`) | `page-templates.php:5` |
| `flxs_settings_save` | `flxs_manage` | `flxCertificateSettingsSave` | `page-settings.php:4` |
| `flxs_font_add` | `flxs_manage` | `flxCertificateFontAdd` (`uploadId`, TTF/OTF) | `page-settings.php:5` |
| `flxs_font_del` | `flxs_manage` | `flxCertificateFontDelete` | `page-settings.php:6` |
| `flxs_person_save` | use; görme `flxs_can_see_person` | `flxCertificatePersonSave` | `page-people.php:4` |
| `flxs_person_delete` | `flxs_manage` | `flxCertificatePersonDelete` | `page-people.php:5` |
| `flxs_people_bulk` | use (hedef sayfa `flxs_can_bulk`) | `flxCertificateBulkSend` (seçili kişiler) | `page-people.php:6` `:73` |
| `flxs_logs_csv` | `flxs_manage` | **İSTİSNA** (CSV akışı) | `page-logs.php:4` |
| `flxs_event_save` | `flxs_manage` | `flxCertificateEventSave` | `page-events.php:5` |
| `flxs_event_status` | `flxs_manage` | `flxCertificateEventSetStatus` | `page-events.php:6` |
| `flxs_event_delete` | `flxs_manage` | `flxCertificateEventDelete` | `page-events.php:7` |
| `flxs_event_page` | `flxs_manage` | `flxCertificateEventPublish` | `page-events.php:8` |
| `flxs_site_save` | `flxs_manage` | `flxCertificateSiteSave` | `page-events.php:9` |
| `flxs_cert_bulk` | use; silme `flxs_manage`; gönderim `flxs_bulk` | `flxCertificateBulk` | `page-certificates.php:4` |
| `flxs_cert_export` | use (kapsam listeyle aynı) | **İSTİSNA** (xlsx akışı) | `export.php:9` |
| `flxs_ecards_import` | `flxs_manage` | `flxCertificateEcardsImport` | `ecards.php:12` |
| `flxs_ecards_zip` | `flxs_manage` | **İSTİSNA** (zip akışı) | `ecards.php:13` |

`flxs_cert_bulk` işlemleri (`page-certificates.php:140-151`): `send_whatsapp`, `send_sms`, `send_email`, `revoke`, `restore`, `event`, `ref`, `delete` (yalnız yönetici). Toplu gönderim yetkisi yoksa en çok 20 kayıt tek tek kuyruğa alınır (`:16-29`).

## Admin sayfaları

Menü: `includes/admin/admin.php:20-51`. İstemci klasörü (sonraki PR): `packages/modules/src/certificates/`. wp-admin varsayılanı React; klasik `flxs_ui` kullanıcı metası (Ads/CRM’deki `flxa_ui` / `flxcrm_ui` kalıbı).

| Sayfa (`page=`) | Ne gösterir | Sorgular / mutation | İstemci | Kaynak |
|---|---|---|---|---|
| `flxs` | Liste. Filtre: `s`, `status` (active/revoked), `event` (0 = etkinliksiz), `template`, `ref` (0 = referanssız), `by` (kapsamlı kullanıcıda gizli), `person`. Toplu: yukarıdaki sekiz işlem. Excel, satırda sayfa / PDF / referans düzeltme | `flxCertificates`, `flxCertificateBulk`, `flxCertificateSetReferrer`, `flxCertificateSetStatus` | `certificates/List` | `page-certificates.php:67` filtre `export.php:12` |
| `flxs-dashboard` | Sayaç, seri, tablo. Filtre: preset `today\|7\|30\|90\|month\|year\|all\|custom`, `from`, `to`, `event` (açık + arşiv), `ref`, `template`, metrik `fidan`. CSV tarayıcıda üretilir | `flxCertificateStats`, `flxCertificateOverview` | `certificates/Dashboard` | `page-dashboard.php:5` `assets/js/dashboard.js:10-53` |
| `flxs-etkinlikler` | CRM yoksa: sekmeler Açık / `tab=arsiv`. Satır: dashboard, sertifikalar, düzenle, sitede yayınla, kapat/aç, arşivde sil. Altta site formu. CRM varken slug `flxcrm_events_url`’e yönlenir, menü gizli | `flxCertificateEvents`, `flxCertificateEventSave`, `flxCertificateEventSetStatus`, `flxCertificateEventDelete`, `flxCertificateEventPublish`, `flxCertificateSite` | `certificates/Events` ya da CRM bağlantısı | `page-events.php:72` `admin.php:27-31` `events-shared.php:217` |
| `flxs-etkinlikler&add=1` / `&edit=` | Ad, tarih, saat, hedef, ek fidan, özet, görsel, konum adı/adres/harita, duyuru, not. Yeni kayıtta etkinliksiz sertifikaları devralma | `flxCertificateEventSave` | aynı ekranın formu | `page-events.php:74` `flxs_event_form` `:155` |
| `flxs-yeni` | Yeni: çok satır (kişi başına bir sertifika), şablon, etkinlik, referans, kanal, canlı önizleme. `&id=` düzenleme (tek kayıt, PDF yenileme) | `flxCertificateCreate` / `Update`, `flxCertificatePeople`, `flxCertificateSend`, `flxCertificateQueue` | `certificates/Create` | `page-create.php:8` |
| `flxs-kisiler` | Arama `s`. Toplu: kanala göre toplu gönderim sayfasına. | `flxCertificateDirectory`, `flxCertificateBulkSend` | `certificates/People` | `page-people.php:82` |
| `flxs-kisiler&add=1` | Ad, telefon, e-posta, not | `flxCertificatePersonSave` | aynı | `:88` |
| `flxs-kisiler&person=` | Form, sil (yönetici), sertifika listesi, mesaj listesi | `flxCertificatePerson`, `flxCertificates`, `flxCertificateMessages` | `certificates/Person` | `:83` `:181` |
| `flxs-kuyruk` | Sekmeler: tümü + `queued\|processing\|sent\|failed\|cancelled`. Filtre: `s`, `channel`, `batch`, `cert`. Şimdi işle, tekrar, iptal, detay. Son 4 parti | `flxCertificateMessages`, `flxCertificateQueue`, `flxCertificateQueueStep`, retry/cancel | `certificates/Queue` | `page-queue.php:4` |
| `flxs-toplu` | Mod: sertifika linki / kişilere mesaj / düz liste. Kanal, sağlayıcı, konu, metin, önizleme (`dry`) | `flxCertificateBulkPreview`, `flxCertificateBulkSend` | `certificates/Bulk` | `page-bulk.php:4` |
| `flxs-sablonlar` | Liste: çoğalt, aktif/pasif, dışa aktar, sil. JSON içe aktar. E-kart içe aktar / zip (görsel varsa) | template mutation’ları, `flxCertificateEcardsImport` | `certificates/Templates` | `page-templates.php:90` `ecards.php:12` |
| `flxs-sablonlar&edit=` / `&new=1` | Tuval editörü: alanlar, bağış sayfasında seçilebilirlik, OG, kanala özel mesaj, canlı önizleme | `flxCertificateTemplateSave`, `flxCertificateTemplateThumb` | `certificates/TemplateEditor` | `page-templates.php:200` |
| `flxs-saglayicilar` | Ekran yok. Flux Mail varsa `flxi-baglantilar`, yoksa `flxs-sistem` | — | yönlendirme kalır | `admin.php:40-47` |
| `flxs-kayitlar` | Filtre: `s`, `act`, `user`, `from`, `to` (sorgu ayrıca `type`, `object`). CSV | `flxCertificateLogs` | `certificates/Logs` | `page-logs.php:46` `:78` |
| `flxs-ayarlar` | Genel, mesajlar, personel, kuyruk, sorgulama, veri, ek fontlar | `flxCertificateSettings`, `flxCertificateSettingsSave`, font mutation’ları | `certificates/Settings` | `page-settings.php:83` |
| `flxs-sistem` | Kurulum / gönderim / site grupları. Eksik dosya düğmesi `flxs_render_missing`. WhatsApp/SMS testi Flux Mail | `flxCertificateSystemChecks`, `flxCertificateRenderMissing` | `certificates/System` | `page-system.php:5` `system-checks.php:51` |

FluxApp sekmeleri (`app-manifest.php:51`): `overview`, `certificates`, `people`, `queue`. Şablon ve yeni sertifika `type: web` (klasik sayfa). Manifest REST yollarını gösterir; GraphQL’e geçince FluxApp işi, bu paketin şema dosyası değil.

## MCP / AI araçları

Yok. `flx_mcp_tools`, `flxai_tools`, `flxseo_mcp_tools` pakette geçmiyor.

## Ayarlar

`flx_settings_schema` yok. Sonraki PR grubu `flxs_manage` cap’i ile kaydeder; gizli alan okunmaz. SMTP şifresi ayar seçeneğinde değil, sağlayıcı satırında (`type: password`, boş gönderilirse eski değer kalır, `flx1:` ile mühürlenir). GraphQL’e değer girmez. Sağlayıcı ekranı kaldırıldığı için şifre alanı şemada yazma girdisi de olmaz; bağlantılar Flux Mail’dedir.

`flxs_settings` (`includes/helpers.php:36-56`, form `page-settings.php:95-144`):

| Alan | Tip | GraphQL | Not | Kaynak |
|---|---|---|---|---|
| `slug` | string | `slug` | doğrulama sayfası yolu | `:38` `:98` |
| `org_name` | string | `orgName` | herkese açık sayfada kurum adı | `:39` `:102` |
| `code_length` | int 8–32 | `codeLength` | | `:40` `:99` |
| `number_format` | string | `numberFormat` | `{yil} {sira} {id} {kod}` | `:41` `:100` |
| `noindex` | bool | `noindex` | | `:40` `:103` |
| `msg_sms` | text | `messageSms` | | `:43` `:109` |
| `msg_whatsapp` | text | `messageWhatsapp` | | `:44` `:110` |
| `email_subject` | string | `emailSubject` | | `:45` `:111` |
| `email_body` | text | `emailBody` | | `:46` `:112` |
| `default_provider` | `{email,sms,whatsapp}` id | `defaultProviderIds` | formda yok; Flux Mail senkronu yazar. Anahtar değil | `:47` `providers.php:133` |
| `queue_batch` | int 1–200 | `queueBatch` | | `:48` `:125` |
| `max_attempts` | int 1–10 | `maxAttempts` | | `:49` `:126` |
| `staff_bulk` | bool | `staffBulk` | role `flxs_bulk` ekler/çıkarır | `:50` `:118` `install.php:280` |
| `staff_own_only` | bool | `staffOwnOnly` | | `:51` `:119` |
| `lookup_enabled` | bool | `lookupEnabled` | | `:52` `:133` |
| `lookup_channels` | enum listesi | `lookupChannels` | `EMAIL \| SMS \| WHATSAPP` | `:53` `:135` |
| `log_retention` | int gün, 0 = süresiz | `logRetentionDays` | | `:54` `:142` |
| `delete_on_uninstall` | bool | `deleteOnUninstall` | | `:55` `:143` |

`flxs_site` (`includes/site.php:33-59`), etkinlik sayfasının alt formu:

| Alan | Tip | GraphQL | Kaynak |
|---|---|---|---|
| `home_section` | bool | `homeSection` | `site.php:36` `:54` |
| `title` | string | `eventsTitle` | `:37` |
| `subtitle` | string | `eventsSubtitle` | `:38` |
| `donate_url` | string | `donateUrl` | `:39` |
| `site_base` | int | `siteBaseCount` | `:40` |

| Alan | Tip | GraphQL | Not | Kaynak |
|---|---|---|---|---|
| `flxs_custom_fonts[]` | attachment id + etiket | `fonts` | gizli değil | `page-settings.php:59` |
| sağlayıcı `password` (SMTP) | password | yazılmaz, okunmaz | gizli. Mühür `providers.php:217-226`. Alan tanımı `providers/email.php:30`. Ekran yok | `providers/email.php:20-36` |

## Herkese açık uçlar

| Uç | cap | → GraphQL | Kaynak |
|---|---|---|---|
| `/{slug}/{kod}` doğrulama sayfası | herkese açık sayfa | `flxCertificateVerify(code:)` `public` | `public.php:22` `template_redirect` `:38` `templates/sertifika.php:82-107` |
| `/{slug}/{kod}/pdf` | aynı, dosya akışı | **İSTİSNA**; `pdfUrl` | `public.php:21` `flxs_serve_pdf` `:111` |
| `/{konum}/{id\|tarih}` QR | 302 harici harita, sayaç | **İSTİSNA** | `public.php:24` `events.php:149` `flxs_location_redirect` |
| `POST /lookup` | public + limit | `flxCertificateLookup` | `rest.php:342` kısa kod `public.php:358` |
| `POST /public-files/{code}` | HMAC, oran 12 / 6 saat | **İSTİSNA** | `rest.php:174` |

`flxCertificateVerify` yalnız sayfanın gösterdiği alanlar (`templates/sertifika.php:82-107`): `status`, `title`, `holderName`, `number`, `issuedOn`, `issuer`, `code`, `pageUrl`, `pdfUrl` (iptalde veya dosya yoksa null), `imageUrl`, `imageWidth`, `imageHeight`, `ogTitle`, `ogDescription`. İptalde indirme ve paylaşım yok. Telefon, e-posta, kişi id, iç id, not, referans, oluşturan kullanıcı yok. Bilinmeyen kod `null` (varlık sızmaz). Gerekçe: KURALLAR §3 sertifika doğrulama örneği.

`flxCertificateLookup` yanıtı her zaman aynı cümle (`rest.php:405`). Limit: IP başına saatte 10, adres başına 15 dakikada 1 (`rest.php:356-367`). Bal küpü `website`.

## Dosya indirme / yükleme

| Akış | → GraphQL | Kaynak |
|---|---|---|
| PDF indirme (`?indir=1` ek ile) | **İSTİSNA**, `pdfUrl` | `public.php:111-127` |
| Sertifika Excel | **İSTİSNA**, liste sorgusu aynı filtre | `export.php:37` |
| Log CSV | **İSTİSNA** | `page-logs.php:58` |
| Şablon JSON dışa aktarma | **İSTİSNA** | `page-templates.php:31` |
| E-kart zip | **İSTİSNA** | `ecards.php:281` |
| Sertifika pdf/image/og yükleme (oturumlu) | kit upload + `flxCertificateAttachFiles` | `rest.php:149` |
| Şablon küçük resim, font, şablon JSON içe aktarma | kit upload + ilgili mutation | `rest.php:267` `page-settings.php:48` `page-templates.php:53` |
| Herkese açık eksik dosya | **İSTİSNA** | `rest.php:174` |

Dosyalar `uploads/flx-certificates/` (`helpers.php:196`).

## Kısa kod ve blok

| Ad | → GraphQL | Kaynak |
|---|---|---|
| `[flxs_etkinlikler]` | `flxCertificateEvents` (açık etkinlikler, herkese açık alanlar: ad, tarih, fidan, hedef, konum etiketi) | `site.php:289` |
| `[flxs_etkinlik id=""]` | `flxCertificateEvent` | `site.php:290` |
| `[flxs_fidan_sayaci etkinlik="" ek=""]` | `flxCertificateSaplingCount` `public` | `site.php:298` |
| `[flx_sertifika_sorgula]` | kısa kod kalır; form `flxCertificateLookup` | `public.php:358` |
| `[flx_credit]` / blok `flx/credit` | bu paketin şeması değil (gömülü marka) | `includes/flx-brand/core.php:134` `:149` |

Ayrıca `render_block` ile ana sayfaya etkinlik bölümü (`site.php:274`) ve etkinlik başlığına fidan rozeti (`site.php:319`). Tema kancası, GraphQL değil.

## Cron, kuyruk, çizim

Hepsi istisna (zamanlayıcı). Ayrı nopriv AJAX yok. Adım mutation’ları GraphQL’de.

| Kanca | Ne yapar | GraphQL | Kaynak |
|---|---|---|---|
| `flxs_queue_tick` | her dakika `flxs_run_queue` | durum `flxCertificateQueue`; adım `flxCertificateQueueStep` | `install.php:329` `queue.php:11` |
| `flxs_run_queue` | tek seferlik; bütçe cron 25 sn, diğer 20 sn, REST 15 sn | aynı adım | `queue.php:190` `:235` |
| `flxs_render_files` | bir sertifikanın eksik pdf/image/og dosyası | `flxCertificateRenderMissing` | `render.php:12-33` |
| `flxs_daily` | `log_retention` gününden eski log | ayar alanı; kanca kalır | `install.php:332` `log.php:64` |

Kuyruk (`queue.php:235-301`): `queued` ve vakti gelmiş satır claim edilir, `processing` 10 dakikadan eskiyse kuyruğa döner, deneme `max_attempts`’i aşınca `failed`. Tur `queue_batch` ya da kalan süre &lt; 5 sn olunca biter. Kalan varsa `flxs_kick_queue`. İşçi kalp atışı varsa web isteği erteler (`flx_background_should_defer`). Sağlayıcı zaman aşımı en çok 20 sn.

Çizim (`render.php:20-99`): `flxs_certificate_created` 60 sn sonra `flxs_render_files`. Kilit `certificate-files-{id}` 180 sn. Hata 3 deneme. GD (FreeType) yoksa yalnız tarayıcı çizer; herkese açık sayfa eksik dosyayı `public-files`’a basar.

## Flux Donate ile bağ

Donate sertifika üretmez; fonksiyon çağırır. Ortak bir `do_action` / `apply_filters` çifti yok. Çoklu ad (bir bağış, bir ödeme, ad başına sertifika) Donate’indir: `FLXB_WC_Certificates::issue` → `recipients_with_contacts` → `make_one` (`plugins/flx-donate/includes/wc/class-flxb-wc-certificates.php:97`, `:142`, `:267`). Certificates GraphQL bu döngüyü yeniden yazmaz.

Donate’in çağırdığı fonksiyonlar: `flxs_list_templates`, `flxs_donor_templates`, `flxs_get_template`, `flxs_upsert_person`, `flxs_create_certificate`, `flxs_set_certificate_event`, `flxs_set_certificate_status`, `flxs_send_certificate`, `flxs_resolve_provider`, `flxs_kick_queue`, `flxs_get_certificate`, `flxs_get_certificate_by_code`, `flxs_set_certificate_ref`, `flxs_certificate_url`, `flxs_certificate_pdf_url`, `flxs_certificate_file_url`, `flxs_certificate_name`, `flxs_page_data`, `flxs_pdf_filename`, `flxs_certificate_missing_files`, `flxs_public_file_token`, `flxs_renderer_scripts`, `flxs_renderer_config`, `flxs_enqueue`, `flxs_query_certificates`, `flxs_find_person_by_phone`, `flxs_find_person_by_email`, `flxs_get_events`, `flxs_table`, `flxs_cert_fidan`.

Donate filtresi (Certificates uygulamaz): `flxb_wc_certificate_values` (`class-flxb-wc-certificates.php:306`).

Certificates filtreleri (Donate bağlamıyor; Mail `flxs_provider_types` bağlar, `plugins/flx-mail/includes/sertifika.php:111`): `flxs_donor_templates` (`templates.php:269`), `flxs_certificate_vars` (`certificates.php:104`), `flxs_certificate_created` (`certificates.php:169`), `flxs_provider_types` (`providers.php:11`), `flxs_bundled_dirs` (`templates.php:112`), `flxs_location_slug` (`events.php:103`), `flxs_location_hit` (`events.php:159`), `flxs_message_sent` / `flxs_message_failed` (`queue.php:339` `:358`).

Aynı fonksiyonları Flux Forms (`class-flxf-automations-engine.php`) ve Flux Chat (`includes/integrations.php`) de çağırır. Sözleşme bu fonksiyonlarda kalır.

Kişi: `flxs_upsert_person` → `flx_person_ensure` kaynak `certificates` (`people.php:93`). Silme, başka bağ varsa yalnız bağı keser (`people.php:156`). `flx_person_in_use` sertifikası olan kişiyi silinmez işaretler (`people-shared.php:155`). `flx_person_merged` sertifika ve mesaj `person_id`’sini tutulan kişiye taşır (`people-shared.php:169`).

GraphQL’de `FlxCertificate.person: FlxContact` nullable. Görme: `flxcrm_gql_can_see_contact` içinde `flxs_can_see_person`. CRM alanları (`note`, zaman çizelgesi, …) `flxcrm_view` ister; sertifika personeli onları görmez. Kişi notu sertifika formunda duruyor (`page-people.php:18`): `FlxCertificatePerson.note`, cap `flxs_use` \| `flxs_manage`, CRM not alanının kopyası değil, aynı sütunun bu pakete açık okuması.

## Tipler

| Tip | Alanlar | Nullable | Loader |
|---|---|---|---|
| `FlxCertificate` | id, code, number, title, status, template, values, person, event, referrer, pageUrl, pdfUrl, imageUrl, createdAt | `person` (göremezse), `pdfUrl` / `imageUrl` (dosya yoksa), `event` / `referrer` (yoksa) | `can_see` = `flxs_can_see_certificate` |
| `FlxCertificateVerification` | yukarıdaki herkese açık küme | `pdfUrl` iptalde null | kod ile; kişi yüklenmez |
| `FlxCertificatePerson` | id, name, phone, email, note, certificateCount | `phone`, `email`, `note` boşsa | `flxs_can_see_person` |
| `FlxCertificateTemplate` | id, name, status, config, thumbUrl, donorSelectable | `thumbUrl` | `flxs_manage` |
| `FlxCertificateMessage` | id, channel, status, recipient, subject, attempts, lastError, sentAt | `lastError`, `sentAt` | kuyruk kapsamı |
| `FlxCertificateEvent` | id, name, status, date, target, counts, location | konum alanları | yönetici; herkese açık sorguda iletişim yok |
| `FlxCertificateStats` / `Overview` | toplam, seri, kuyruk sayıları | — | dashboard |
| `FlxCertificateSettings` | yukarıdaki ayar alanları | — | `flxs_manage` |

Enum: `FlxCertificateStatus` `ACTIVE \| REVOKED`, `FlxCertificateChannel` `EMAIL \| SMS \| WHATSAPP`, `FlxCertificateMessageStatus` `QUEUED \| PROCESSING \| SENT \| FAILED \| CANCELLED`, `FlxCertificateEventStatus` `OPEN \| CLOSED`.

Connection’lar `first ≤ 50`: sertifika, kişi dizini, mesaj, log, etkinlik.

Önbellek: `flx-certificates:certificate:{id}`, `flx-certificates:list`, `flx-certificates:person:{id}`, `flx-certificates:queue`, `flx-certificates:settings`, `flx-certificates:events`. Doğrulama sayfası girişsiz persisted GET; iptal ve dosya yazımı purge eder. `noindex` açıksa (varsayılan) herkese açık önbellek süresi kısa tutulur.

## Açık sorular

Ürün kararları uygulayıcıya bırakılmadı; koordinatör listesinde. Teknik varsayılan: herkese açık doğrulama yalnız sayfadaki alanlar; sağlayıcı ekranı geri gelmez; kuyruk oturumlu adım mutation’ıdır; etkinlik ekranı CRM varken CRM’e bağlanır.
