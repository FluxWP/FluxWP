# Flux CRM (flx-crm): REST → GraphQL eşlemesi

Paket sürümü: 0.10.0 · Önek: `flxcrm_` (sınıf `FLXCRM_`) · Cap: `flxcrm_view` (okuma), `flxcrm_manage` (yazma) · Durum: uygulandı (gql/flx-crm-1), ayrıntı `docs/flx-crm/GRAPHQL.md`

Not: Bu dosya yalnız envanterdir, kod yazılmadı. "belirsiz" işaretli satırlar uygulayıcıdan önce karar ister.

## Özet

- REST: 12 yol (15 yöntem) `flx-crm/v1` · AJAX: 3 (`flxcrm_score`, `flxcrm_timeline`, `flxcrm_sync`) · Admin sayfası: 7 (Overview, Contacts, Segments, Events, Journeys, Settings + Duplicates alt sayfası)
- AI/MCP: `flx_mcp_tools` değil, kendi filtresi `flxcrm_tools` (`includes/ai-tools.php`). Çekirdek + ek araçlar. Ayrıca yıkıcı işlemler onay token'ı ister (`flxcrm_confirm_token`).
- İstisna: yok (flxcrm_sync için belirsiz, bkz. Açık sorular).
- Servis (önerilen): `includes/services/class-flxcrm-contact-service.php`, `class-flxcrm-segment-service.php`, `class-flxcrm-event-service.php`, `class-flxcrm-score-service.php`. Mevcut mantık `FLXCRM_Contacts`, `FLXCRM_Events`, `FLXCRM_Segments`, `FLXCRM_Tags`, `FLXCRM_Scoring` içinde zaten var; REST ve AJAX onlara delege ediyor.
- Node tipleri: `FlxContact` (loader `FLXCRM_Contact_Loader`), `FlxContactEvent`, `FlxSegment`, `FlxTag`, `FlxJourney`.
- Ortak tip sahibi: `FlxPerson` arayüzü bu pakette tanımlanır (kişi kartı `person-card.php`, `flxcrm_person_card`).

## REST uçları

Görünen okuma uçları `flxcrm_view`, yazma uçları `flxcrm_manage` cap'i ile. Kural: `flx_gql_query` / `flx_gql_mutation` ile kayıt, cap zorunlu.

| Yöntem | Yol | cap | → GraphQL | Girdi (taslak) | Çıktı (taslak) | Not |
|---|---|---|---|---|---|---|
| GET | `/contacts` | view | `flxContacts` (query) | `FlxContactFilterInput` (s, role, segment, tag, consent, risk, status), connection args | `FlxContactConnection!` | Sayfa boyu ≤ 50 |
| POST | `/contacts` | manage | `flxContactCreate` (mutation) | `FlxContactCreateInput` (name, email, phone, company, city, country, note, owner_id, roles, status) | `{ contact: FlxContact, errors }` | |
| GET | `/contacts/{id}` | view | `flxContact(id:)` (query) | `ID!` | `FlxContact!` | `stats`, `consent`, `note` alanları yalnız tam kayıtta; cap alan düzeyinde (belirsiz, bkz. not) |
| POST | `/contacts/{id}` | manage | `flxContactUpdate` (mutation) | `FlxContactUpdateInput` (kısmi) | `{ contact, errors }` | Kısmi güncelleme: verilmeyen alan değişmez |
| DELETE | `/contacts/{id}` | manage | `flxContactDelete` (mutation) | `ID!` | `{ deletedId, errors }` | Yıkıcı; AI'de onay token'lı |
| POST | `/contacts/{id}/events` | manage | `flxContactEventAdd` (mutation) | `{ contactId, type (enum), text }` | `{ event: FlxContactEvent, errors }` | Manuel olay tipleri `flxcrm_manual_event_types` |
| POST | `/contacts/{id}/tags` | manage | `flxContactTagAdd` (mutation) | `{ contactId, tag (ID veya ad) }` | `{ contact, errors }` | Ad verilirse etiket oluşturulur |
| DELETE | `/contacts/{id}/tags/{tag}` | manage | `flxContactTagRemove` (mutation) | `{ contactId, tagId }` | `{ contact, errors }` | |
| DELETE | `/events/{id}` | manage | `flxEventDelete` (mutation) | `ID!` | `{ deletedId, errors }` | |
| GET | `/stats` | view | `flxCrmOverview` (query) | — | `FlxCrmOverview!` (kişi sayısı, 30 gün yeni, aktif, churn riski, LTV toplamı, rol/RFM/kaynak dağılımı) | `flxcrm_tool` overview ile aynı servis |
| GET | `/meta` | view | `flxCrmMeta` (query) | — | `FlxCrmMeta!` (roller, RFM anahtarları, kanallar, kaynaklar) | Sözlük; cache tag `flx-crm:meta` |
| GET | `/segments` | view | `flxSegments` (query) | — | `[FlxSegment!]!` (≤ 50) | Düz liste (küçük küme) |
| GET | `/segments/{id}/contacts` | view | `flxSegmentContacts(id:)` (query) | connection args | `FlxContactConnection!` | |
| POST | `/segments/preview` | view | `flxSegmentPreview` (query, input ile) | `FlxSegmentRulesInput` (match, rules[]) | `{ count, sample: [FlxContact!]! }` | Girdi ile query; GET cache'lenmez (girişli) |
| POST | `/score` | manage | `flxCrmScoreRun` (mutation) | — | `{ result: FlxScoreRunResult!, errors }` | Ağır iş; sonuç özeti döner |

Girdi/çıktı tipi taslakları içinde tekrar kullanılan tipler: `FlxContact`, `FlxContactEvent` (type, typeLabel, source, title, body, amount, currency, paid, url, at), `FlxRole`, `FlxSegment`, `FlxTag`, `FlxMoney` (LTV, tutar), `FlxDateTime` (`last_activity_at`, `at`, `created_at`), `FlxRfm` (`[r,f,m]`, tip olarak `{r,f,m}` yapılır — dizi yerine nesne, kırıcı olmayan yeni alan).

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxcrm_score` | `manage` + `flxcrm_admin` | `flxCrmScoreRun` | REST `/score` ile aynı servis |
| `flxcrm_timeline` | `view`(?) + `flxcrm_admin` | `flxContactTimeline(contactId:, tl:, offset:, day:)` (query) | Kişi detayında zaman tüneli sayfalaması; belirsiz: cap kodda `current_user_can` ile kontrol ediliyor, tam cap doğrulanmalı |
| `flxcrm_sync` | `manage`? + `flxcrm_admin` | belirsiz | Adım adım içe aktarma/senkron (`class-flxcrm-sync.php`, `step`/`page`). Bkz. Açık sorular |

## Admin sayfaları

| Sayfa (`page=`) | Ne gösterir | Kullandığı sorgular | İstemci ekranı |
|---|---|---|---|
| `flx-crm` | Genel bakış (istatistik, churn, son olaylar) | `flxCrmOverview`, `flxCrmMeta`, `flxContacts` (özet) | `packages/modules/src/crm/overview` |
| `flx-crm-contacts` | Kişi listesi, filtre, kişi detayı (`page-contact.php`), toplu işlem | `flxContacts`, `flxContact`, `flxContactUpdate`, `flxContactTagAdd/Remove`, `flxContactEventAdd`, `flxContactTimeline` | `crm/contacts`, `crm/contact` |
| `flx-crm-contacts` (`page-duplicates.php`) | Yinelenen kişi birleştirme | belirsiz (birleştirme servisi REST'te yok; bulk/tool üzerinden) | `crm/duplicates` (belirsiz) |
| `flx-crm-segments` | Kayıtlı segmentler ve kuralları | `flxSegments`, `flxSegmentContacts`, `flxSegmentPreview` | `crm/segments` |
| `flx-crm-events` | Olay listesi, manuel olay | `flxContactEventAdd`, `flxEventDelete`, sorgu: `flxCrmEvents` (belirsiz; REST'te yok) | `crm/events` |
| `flx-crm-journeys` | Otomasyon akışları (journeys) | Yalnız AI aracı `flxcrm_tools` içinde (journey create/update/status); REST yok → yeni query/mutation gerekir | `crm/journeys` (belirsiz) |
| `flx-crm-settings` | CRM ayarları | belirsiz (bkz. Ayarlar) | `crm/settings` |

## MCP / AI araçları (`flxcrm_tools` filtresi)

Araçlar `flxcrm_core_tools()` ve ek filtrelerle gelir. Her araç servis metodunu çağırır; GraphQL karşılığı aynı servis.

| Araç grubu | Örnek araçlar | Servis | GraphQL karşılığı |
|---|---|---|---|
| Genel bakış | overview | `FLXCRM_Contacts` istatistik | `flxCrmOverview` |
| Kişi | contacts (liste), contact (tek), contact_form (oluştur/güncelle), delete | `FLXCRM_Contacts` | `flxContacts`, `flxContact`, `flxContactCreate`, `flxContactUpdate`, `flxContactDelete` |
| Etiket | tag_add, tag_remove | `FLXCRM_Tags` | `flxContactTagAdd/Remove` |
| Not / olay | note_add | `FLXCRM_Events` | `flxContactEventAdd` |
| Segment | segments, segment (ayrıntı), segment_rules | `FLXCRM_Segments` | `flxSegments`, `flxSegmentPreview` |
| Toplu | bulk target + action (önizleme + çalıştır) | `flxcrm_tool_bulk_*` | belirsiz: toplu mutation şekli (`flxContactBulk`, önizleme + onay token) önerilir, cap manage |
| Kanal izni | consent | `flxcrm_tool_*` | `flxContactConsentUpdate` (belirsiz; REST'te `consent` yalnız `update_contact` içinde) |
| Kampanya | winback, welcome | `flxcrm_tool_*` | belirsiz; GraphQL'de yalnız istemci ekranında gerekirse |
| Yolculuk | journey create/update/status | `FLXCRM_Journeys` | belirsiz (REST yok) |

Not: `flx_mcp_tools` kullanılmıyor. Plan'daki "MCP/AI aracı: 0" satırı bu pakete uymuyor, düzeltilmeli.

## Ayarlar

- `flx_settings_schema` bu pakette yok (grep sonucu boş). Bu nedenle `flxCrmSettings` otomatik üretilmez.
- Önerilen: `flxCrmSettings` (query) ve `flxCrmSettingsUpdate` (mutation) elle tanımlanır, cap `flxcrm_manage`. Belirsiz: ayar anahtarları (`ai_tools`, `ai_write`, `ai_destructive`, risk eşiği, saat dilimi/para birimi) tam listelenmedi.

## Tipler

| Tip | Alanlar (kısa) | Nullable alanlar ve nedeni | Loader |
|---|---|---|---|
| `FlxContact` (implements `Node`, `FlxPerson`) | id, databaseId, name, email, phone, roles, segment, segmentLabel, ltv, currency, churn, lastActivityAt, url; tam kayıt: company, city, country, source, utmSource, utmCampaign, userId, ownerId, status, note, consent, stats, createdAt | `phone` boş string (nullable değil); `lastActivityAt` nullable (hiç aktivite yok); `userId`/`ownerId` 0 olabilir | `FLXCRM_Contact_Loader` (`can_see`: flxcrm_view) |
| `FlxContactEvent` | id, type, typeLabel, source, title, body, amount, currency, paid, url, at, contact | `url` boş olabilir; `amount` 0 | Nesne tipi; kök alanda cap |
| `FlxContactConsent` | email, sms, whatsapp, ads (bool) | yok (non-null) | — |
| `FlxContactStats` | donations, orders, paidDays, firstPaidAt, lastPaidAt, avgGapDays, rfm | `firstPaidAt`, `lastPaidAt` nullable (hiç ödeme yok) | — |
| `FlxSegment` | id, name, count, summary, url | yok | `FLXCRM_Segment_Loader` (belirsiz; düz liste) |
| `FlxTag` | id, name, color, count | `color` boş olabilir | — |
| `FlxJourney` | id, name, status, steps, people (belirsiz) | belirsiz | — |

Hassas alanlar (e-posta, telefon, not, consent) için alan düzeyinde cap: `flxcrm_view` ve tam kayıt için `flxcrm_manage` önerilir (belirsiz: mevcut REST'te yalnız okuma iznine göre dönüyor, ayrım onaylanmalı).

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-crm:contacts` | `flxContacts`, `flxSegmentContacts` | `flxContactCreate/Update/Delete`, `flxContactTagAdd/Remove`, `flxCrmScoreRun`, sync adımları |
| `flx-crm:contact:{id}` | `flxContact`, `flxContactTimeline` | aynı kişiye yazan mutation'lar, `flxContactEventAdd`, `flxEventDelete` |
| `flx-crm:overview` | `flxCrmOverview` | kişi/olay/skor yazımları |
| `flx-crm:meta` | `flxCrmMeta` | rol/etiket/segment değişimi |
| `flx-crm:segments` | `flxSegments` | segment yazımları |

## Açık sorular

- `flxcrm_sync` (WP ajax, adım adım senkron): istisna mı, yoksa `flxCrmSyncStep` mutation'ı mı? Dosya sayfa/adım mantığı taşıyor; uzun iş için arka plan + durum sorgusu önerilir.
- Kişi detayında `consent`, `note` ve istatistik alanlarının cap'i: `flxcrm_view` mi, `flxcrm_manage` mı? Mevcut REST'te `view` ile tam kayıt dönüyor.
- Yinelenen kişi (duplicates) birleştirme ve olay listesi (`flx-crm-events`) için REST ucu yok; GraphQL'de yeni uç mu açılacak?
- Yolculuk (journeys) için REST yok; yalnız AI aracı var. Ekran portu için query/mutation eklenmeli mi?
- `FlxPerson` arayüzünün sahibi bu paket (Faz 1 öncelikli); Donate ve Commerce `FlxPerson`'a bağlandığı için tip adı ve alanları Faz 1'de kesinleşmeli.
- Ayar şeması yok: `flxCrmSettings` elle tanımlansın mı?
