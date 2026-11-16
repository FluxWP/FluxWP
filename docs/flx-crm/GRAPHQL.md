# Flux CRM GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma `flxcrm_view`, yazma `flxcrm_manage`. İşlemler `plugins/flx-crm/graphql/operations/*.graphql`
(40), tipleri `@fluxwp/graphql` (`FlxCrmOverview`, `FlxCrmGatherings` …). Servisler `plugins/flx-crm/includes/services/`; şema `includes/graphql/schema.php`, `gatherings.php`, `sections.php`.

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

- **Kural: başka paketin `FlxContact` döndüren alanı her zaman nullable'dır** (`'type' => 'FlxContact'`, `!` yok). Kişiyi göremeyen kullanıcıda loader `null` döner; non-null alan tüm üst nesneyi siler.
- Görünürlük: loader `flxcrm_can()` ister. Donate (bağışçının kendi kaydı), Chat temsilcisi gibi CRM yetkisi olmayan paket kullanıcıları için `flxcrm_gql_can_see_contact( bool $ok, FLXCRM_Contact $contact, int $user_id )` filtresi true dönebilir; yalnız kendi kayıtları için true dönün.
- Ortak `FlxPerson` alanları (`databaseId`, `name`, `email`, `phone`, `avatar`) kaydı görebilene açıktır. CRM'e özel tüm alanlar (`note`, `timeline`, `ltv`, `rfm`, `churn`, `atRisk`, `consent`, `owner*`, `sources`, `tags`, `utm*`, `segment*`, `editUrl` …) alan düzeyinde `flxcrm_view` ister ve **nullable**'dır; yetkisiz kişide `null` + `FORBIDDEN` hatası döner.
- `avatar`: Gravatar adresi yalnız Ayarlar > Tartışma'da avatarlar açıksa döner; kapalıysa `null` (e-posta özeti siteden çıkmaz). İstemci baş harfleri gösterir.
- Başka tipten kişiye bağlanırken `$context->get_loader( 'FlxContact' )->load_deferred( $id )` kullan (N+1 olmaz).
- Kişi değişince önbellek etiketleri `flx-crm:contact:<id>`, `flx-crm:contacts` ve `flx-crm:overview` temizlenir (`flx_gql_purge`). Yeni yazarlar bunu bozmaz: klasik REST/AJAX/admin yazıcıları da aynı kancalarla temizler.
- Kişi kartının eklenti bölümleri `FlxContact.sections` (aşağıda kayıt). Klasik kancalar durur; kayıt `replaces` demedikçe HTML'leri de döner.

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
| `flxGatherings`, `flxGathering`, `flxGatheringForm`, `flxGatheringPeople`, `flxGatheringMessages`, `flxGatheringSend` | Olaylar ekranı. Tip adı `FlxGathering` (`FlxEvent` kişi zaman çizgisinde durur) |
| `flxCrmSettings`, `flxCrmAi`, `flxCrmAiLog` | Ayarlar, AI & MCP, AI günlüğü |

## Yazma

`flxContactCreate/Update/Delete/Bulk/Merge`, `flxContactTagAdd/Remove`, `flxContactEventAdd/Delete`,
`flxSegmentSave/Delete`, `flxJourneySave/FromTemplate/SetStatus/Delete`,
`flxGatheringSave/SetStatus/Delete/EnsurePage`, `flxGatheringMessageSend`, `flxGatheringSendControl`,
`flxCrmSettingsSave`, `flxCrmScoreRun`, `flxCrmSyncStep`.
Hatalar payload `errors` içinde (`INVALID_INPUT`, `field`); bulunamayan kayıt üst düzey `NOT_FOUND`.
Toplu iş ve skorlama sayfalı: `flxCrmScoreRun` `{ done, scored, total }`, `flxCrmSyncStep` `{ next, page, done }` döner; istemci `done` olana dek çağırır.

## Kararlar

- Eski `flxcrm_sync` AJAX'ı `flxCrmSyncStep` mutation'ı oldu (adım adım, ilerleme döner).
- Yolculuklar ve kopyalar için yeni sorgular eklendi (`flxJourneys`, `flxContactDuplicates`).
- AI araçları kendi `flxcrm_tools` filtresinde kalır (MCP `flx_mcp_tools` değil); aynı servisleri çağırır.

## Kişi kartı bölüm kaydı

Başka bir paket CRM dosyasına dokunmadan bir bölüm ekler. Kayıt GraphQL olmasa da çalışır (`includes/person-card.php`).

```php
flxcrm_register_person_section( array(
	'id'        => 'donate_gifts',          // sanitize_key; boşsa yok sayılır
	'package'   => 'flx-donate',
	'title'     => __( 'Recurring gifts', 'flx-donate' ),
	'placement' => 'side',                  // actions | main_before | main | side
	'replaces'  => 'flxcrm_person_card_side', // bu kancanın HTML'i artık eklenmez
	'cap'       => 'flxd_view',             // string cap, ya da fn(FLXCRM_Contact): bool. Varsayılan flxcrm_view
	'resolve'   => static function ( FLXCRM_Contact $contact, string $context ) {
		// Kendi verini yükle. İstek static'ine güvenme. null = bölüm yok.
		return array(
			array( 'kind' => 'stats', 'items' => array( array( 'label' => 'Gifts', 'value' => '3' ) ) ),
			array( 'kind' => 'links', 'items' => array( array( 'label' => 'Open', 'url' => 'https://example.com' ) ) ),
		);
	},
) );
```

`resolve` dönüşü: `null` (gizle), bir blok listesi, ya da `array{ title?: string, blocks: list }`. Blok türleri: `stats`, `links`, `list`, `table`, `note`, `html`, `actions`.

| Tür | Alanlar |
|---|---|
| stats, links, list, actions | `items[]`: `label`, `value`, `url`, `meta`, `primary` |
| table | `columns[]`, `rows[]`: `cells[]`, `url`, `label`, `meta` |
| note, html | `text` |

GraphQL: `FlxContact.sections(placement, context): [FlxPersonSection!]!`. Alan `flxcrm_view` ister (yalnız `flxcrm_gql_can_see_contact` ile kişiyi gören, alanı isteyince `FORBIDDEN` alır). Cap'i geçmeyen bölüm listede yoktur.

- **`html` blokları ve güvenlik:** Klasik PHP kartında eklenti kancaları ham HTML basar; GraphQL'de ise `FLXCRM_Section_Service::block()` önce `<script>` ve `<style>` öğelerini (içindeki metinle) siler, ardından `wp_kses_post` uygular (`<iframe>` ve `on*` olay öznitelikleri atılır). `items[].url` ve `rows[].url` alanları `esc_url_raw(..., array('http', 'https', 'mailto', 'tel'))` ile temizlenir (`javascript:` vb. geçersiz şemalar `''` olur, göreli admin yolları korunur).
- **`legacy:<kanca>` geçici bir köprüdür:** Kayıtlı bölümü olmayan ve klasik kancaya yazan eklentiler için CRM context'inde (`''`) `do_action` çıktısı `kind: html`, `id: legacy:<kanca>` olarak döner (aynı `wp_kses_post` süzgecinden geçer). Bu HTML içindeki **formlar ve betikler React ile masaüstü uygulamasında (FluxApp) çalışmaz**; paket sahipleri `stats`, `links`, `list`, `table`, `note`, `actions` gibi tipli bloklara ve `replaces` kaydına geçmelidir. Donate'in `context === 'donate'` bölümü CRM kartında görünmez (klasikle aynı). Commerce ve Ads, `replaces` deyene kadar HTML olarak gelir.
- **Yinelenen `id`'ler:** Her paket benzersiz bir `id` vermelidir (`sanitize_key`); `for_contact()` aynı `id` ile gelen kayıtları birleştirmez veya elemez, çıktıya ikisi de girer ve React anahtarlarında çakışır. `sanitize_key` sonucu boş olan `id`'ler kayıt sırasında yok sayılır.
- **Olmayan kancaya işaret eden `replaces`:** `replaces` tanımlı dört kancadan (`flxcrm_person_card_actions`, `main_before`, `main`, `side`) biriyle eşleşmezse hata vermez; kayıtlı bölüm normal şekilde döner ve mevcut kancalar susturulmaz.
- **Hata fırlatan `resolve` veya kanca:** `resolve` (ya da eski kanca callback'i) `Throwable` fırlatırsa `finally` ile çıktı tamponu (`ob_get_clean`) kapatılır, yarım kalan çıktı atılır ve yalnız o bölüm/kanca düşer; kişi sorgusu ve diğer bölümler çalışmayı sürdürür.
- **CRM etkin değilken:** `flxcrm_register_person_section()` yalnız Flux CRM etkinken (`includes/person-card.php`) tanımlanır ve `FlxContact.sections` yalnız o zaman şemaya eklenir. Diğer paketler çağrıyı `if ( function_exists( 'flxcrm_register_person_section' ) )` kontrolüyle (ör. `plugins_loaded` sonrasında) sarmalıdır; CRM kapalıysa kayıt sessizce atlanır.

Etkinlik formu ve "Sitede" sekmesinin paket HTML'i (`afterHtml`, bölüm `html`, `siteHtml`) de kayıt defterinden çıkar (`form`, `label`, `input`, `select`, `option`, `textarea` ve QR `<svg>` öğeleri korunur; `<script>` ve `<style>` atılır). Etkinlik kancası hata fırlatırsa tampon `finally` ile kapatılır ve yalnız o parça `''` döner.

Tarihler `FlxDateTime`'dır ve `flx_gql_datetime()` ile kurulur. `startsAt` ve planlanan gönderim sitenin saat dilimindeki yerel metindir (`DateTimeImmutable` + `wp_timezone()`). Alıcı `sentAt` / `scheduledAt` GMT mysql'dir (`flx_ts` ile aynı). Yazma girdisi `sendAt` klasik form gibi `Y-m-dTH:i` metnidir.

## İstisnalar

- Klasik REST/AJAX/admin-post işleyicileri durur (etkinlik yazmaları `admin-post.php`). React onları çağırmaz.
- Etkinlik kişi listesi React'te sunucuda aranır ve sayfalanır. Klasik sayfa değişmedi: ilk 500 kişiyi yükler, süzme tarayıcıdadır, fazlası için "First 500 of N shown" yazar. 500'den fazla kişide React hepsini arar ve sayfalar; klasik yalnızca ilk 500'ü gösterir.

## React

`packages/modules/src/crm` (`moduleScreens.crm`). wp-admin'de varsayılan; kullanıcı başına `flxcrm_ui` meta ile klasiğe geçilir (başlıktaki bağlantılar). Metinler `plugins/flx-crm/tools/i18n-screen.py` ile üretilir.
