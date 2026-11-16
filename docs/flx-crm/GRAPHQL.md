# Flux CRM GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma `flxcrm_view`, yazma `flxcrm_manage`. İşlemler `plugins/flx-crm/graphql/operations/*.graphql`
(28), tipleri `@fluxwp/graphql` (`FlxCrmOverview`, `FlxCrmContacts` …). Servisler `plugins/flx-crm/includes/services/`; şema `includes/graphql/schema.php`.

## FlxPerson: kişi için ortak arayüz

`FlxPerson` arayüzünün tek somut tipi `FlxContact`'tır (kişi kaydı Flux CRM'de, tablo `{prefix}flx_people`).
Ortak alanlar: `databaseId`, `name`, `email`, `phone`, `avatar`. Donate, Commerce, Chat ve Mail kendi alanını **CRM'e dokunmadan** ekler:

```php
flx_gql_field( 'FlxContact', 'donationTotal', array(
	'type'    => 'FlxMoney',
	'cap'     => 'flxd_view',
	'resolve' => static fn( $contact, $args, $context ) => flxd_total_for( (int) $contact->databaseId ),
) );
```

- Başka tipten kişiye bağlanırken `$context->get_loader( 'FlxContact' )->load_deferred( $id )` kullan (N+1 olmaz).
- Kişi değişince önbellek etiketleri `flx-crm:contact:<id>`, `flx-crm:contacts` ve `flx-crm:overview` temizlenir (`flx_gql_purge`). Yeni yazarlar bunu bozmaz: klasik REST/AJAX/admin yazıcıları da aynı kancalarla temizler.
- Kişi kartının eklenti bölümleri (`flxcrm_person_card_main_before/main/side`) hâlâ klasik PHP kancasıdır (aşağıda İstisnalar).

## Okuma

| Sorgu | Not |
|---|---|
| `flxCrmMeta`, `flxTags`, `flxSegments` | Sözlük (roller, RFM, kaynaklar, para birimi), etiketler, kayıtlı segmentler. İstemci kabuğu `FlxCrmShell` |
| `flxCrmOverview`, `flxCrmRecentEvents` | Sayaçlar, RFM, roller, kaynaklar; risk listesi `flxContacts(risk:true, orderBy:CHURN)` |
| `flxContacts(filter, orderBy, first, after)` | Cursor sayfalama (≤ 50) |
| `flxContact(id)`, `flxContact.timeline(filter, first, after)` | Kart, zaman çizgisi |
| `flxContactDuplicates` | Olası kopyalar |
| `flxSegmentEditor`, `flxSegmentPreview(input)` | Kural alanları ve canlı sayı |
| `flxJourneys`, `flxJourneyConfig` | Yolculuklar, şablonlar, varsayılanlar |
| `flxCrmSettings`, `flxCrmAi`, `flxCrmAiLog` | Ayarlar, AI & MCP, AI günlüğü |

## Yazma

`flxContactCreate/Update/Delete/Bulk/Merge`, `flxContactTagAdd/Remove`, `flxContactEventAdd/Delete`,
`flxSegmentSave/Delete`, `flxJourneySave/FromTemplate/SetStatus/Delete`, `flxCrmSettingsSave`, `flxCrmScoreRun`, `flxCrmSyncStep`.
Hatalar payload `errors` içinde (`INVALID_INPUT`, `field`); bulunamayan kayıt üst düzey `NOT_FOUND`.
Toplu iş ve skorlama sayfalı: `flxCrmScoreRun` `{ done, scored, total }`, `flxCrmSyncStep` `{ next, page, done }` döner; istemci `done` olana dek çağırır.

## Kararlar

- Eski `flxcrm_sync` AJAX'ı `flxCrmSyncStep` mutation'ı oldu (adım adım, ilerleme döner).
- Yolculuklar ve kopyalar için yeni sorgular eklendi (`flxJourneys`, `flxContactDuplicates`).
- AI araçları kendi `flxcrm_tools` filtresinde kalır (MCP `flx_mcp_tools` değil); aynı servisleri çağırır.

## İstisnalar (bu sürümde klasik)

- **Olaylar sayfası** (`flx-crm-events`): klasik PHP; React sekmesi bağlantı verir. Sonraki paket: `gql/flx-crm-2`.
- **Kişi kartı eklenti bölümleri**: kancalar PHP; React kartında "More sections" kartı klasik karta bağlar.
- Klasik REST/AJAX/admin işleyicileri servislere taşınmadı, çalışmaya devam eder.

## React

`packages/modules/src/crm` (`moduleScreens.crm`). wp-admin'de varsayılan; kullanıcı başına `flxcrm_ui` meta ile klasiğe geçilir (başlıktaki bağlantılar). Metinler `plugins/flx-crm/tools/i18n-screen.py` ile üretilir.
