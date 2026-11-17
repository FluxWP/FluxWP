# GraphQL kuralları

FluxWP paketlerinin GraphQL şeması, kit kullanımı ve istemci tarafı için bağlayıcı kurallar.
Kaynak: plan §2.2, §2.3, §2.7. Kit kodu `plugins/flx-graphql/includes/kit/`, istemci `packages/graphql`.
Kit ve `packages/graphql` yalnız Faz 0 sahibi tarafından değişir; ihtiyaç koordinatör üzerinden iş olarak gelir.

## 1. Adlar

| Şey | Kural | Örnek |
|---|---|---|
| Tip, input, enum, interface | `Flx` + PascalCase | `FlxTask`, `FlxTaskUpdateInput`, `FlxTaskStatus` |
| Enum değeri | UPPER_SNAKE, PHP değeri `value` ile | `OPEN => array( 'value' => 'open' )` |
| Kök sorgu | `flx` + camelCase, tekil/çoğul | `flxTask(id:)`, `flxTasks` |
| Mutation | `flx` + nesne + fiil | `flxTaskUpdate`, `flxDonationApprove` |
| WP tipine alan | `flx` önekli, cap zorunlu | `Post.flxSeo` |
| İstemci operasyonu | `Flx` + PascalCase, dosya başına bir operasyon | `FlxTaskList.graphql` |
| Fragment | paket içinde benzersiz | `FlxTaskCard` |

Kit adı kayıtta denetler; kurala uymayan kayıt reddedilir, sistem kontrolünde "kayıt hatası" satırı çıkar.

## 2. Kayıt

Her şey `flx_gql_register` action'ında. Bu action yalnız GraphQL isteğinde çalışır (lazy kayıt): normal sayfaya yük binmez.

```php
add_action( 'flx_gql_register', function () {
	flx_gql_enum( 'FlxTaskStatus', array( 'values' => array( 'OPEN' => array( 'value' => 'open' ), 'DONE' => array( 'value' => 'done' ) ) ) );
	flx_gql_type( 'FlxTask', array(
		'node'   => FLXG_Task_Loader::class,           // Node arayüzü: id (global) + databaseId otomatik
		'fields' => array(
			'title'  => array( 'type' => 'String!', 'description' => __( 'Task title.', 'flx-tasks' ) ),
			'status' => array( 'type' => 'FlxTaskStatus!' ),
			'due'    => array( 'type' => 'FlxDateTime', 'resolve' => fn( $t ) => flx_gql_datetime( $t->due ) ),
		),
	) );
	flx_gql_connection( 'FlxTask' );                  // FlxTaskConnection + FlxTaskEdge
	flx_gql_query( 'flxTasks', array(
		'type'    => 'FlxTaskConnection!',
		'cap'     => 'flxg_view',
		'args'    => flx_gql_connection_args( array( 'list' => array( 'type' => 'ID' ) ) ),
		'resolve' => fn( $root, $args ) => flx_gql_paginate( $args, fn( $offset, $limit ) => FLXG_Task_Service::page( $args, $offset, $limit ) ),
	) );
	flx_gql_mutation( 'flxTaskUpdate', array(
		'cap'     => 'flxg_edit',
		'owner'   => fn( $src, $input ) => FLXG_Task_Service::is_assignee( $input['id'] ?? '' ),
		'input'   => array( 'id' => array( 'type' => 'ID!' ), 'title' => array( 'type' => 'String' ) ),
		'output'  => array( 'task' => array( 'type' => 'FlxTask' ) ),
		'resolve' => fn( $input ) => array( 'task' => FLXG_Task_Service::update( $input ) ),
	) );
} );
```

- Fonksiyonlar: `flx_gql_type / interface / input / enum / query / field / mutation / connection / node_loader`.
- Tip yazımı string (`'String!'`, `'[FlxTask!]!'`) ya da WPGraphQL dizisi (`array( 'non_null' => 'ID' )`).
- Resolver iş mantığı yazmaz: **servisi çağırır** (§5). REST, GraphQL ve MCP aynı servisi kullanır.

## 3. Yetki (fail closed)

- Her kök sorgu, mutation ve WP tipine eklenen alan `cap` almak zorunda. `cap` yoksa kayıt reddedilir.
- `cap`: yetki adı (`'flxg_view'`), `'logged_in'`, `'public'` ya da callable `( $source, $args, $context ) => bool`.
- `owner`: cap tutmazsa ikinci şans (kişinin kendi kaydı). İkisi de tutmazsa `FORBIDDEN` (girişsizse `UNAUTHENTICATED`).
- `'public'` yalnız herkese açık veride (yayımlanmış bağış türü, sertifika doğrulama). PR'da gerekçe yazılır.
- Node: loader'ın `can_see()` metodu görmeyen kullanıcıya `null` döndürür (hata değil). `node(id:)` de aynı loader'dan geçer.
- Node loader'ı ayrıca `flx_gql_loaders` aksiyonunda adlandırılır: HTTP isteğinde WPGraphQL AppContext loader listesini şema kurulmadan okur, yalnız `flx_gql_register` içinde adlandırılan loader "No loader assigned" hatası verir. `add_action( 'flx_gql_loaders', fn() => flx_gql_node_loader( 'FlxTask', 'FLXG_Task_Loader' ) );` Sınıf adı yeter, sınıf şemayla birlikte sonra yüklenebilir.
- Nesne tipinin alanları cap almaz; görünürlük kök alanda ve loader'da çözülür. Hassas alan (e-posta, telefon, not) ayrıca `cap` ile alan düzeyinde kapatılır.

### 3.1 Yetki modeli (kit 0.2.0)

Birden fazla yetkiyle açılan alan için callable yazılmaz, kit yardımcıları kullanılır:

```php
'cap' => flx_gql_any_cap( 'flxb_view_own', 'flx_bagis_view' ),   // biri yeter
'cap' => flx_gql_all_caps( 'manage_options', 'promote_users' ),   // hepsi gerekir (boş liste = kapalı)
```

Paket yetkilerini katalogla bildirir; istemci (React, FluxApp) menü ve düğmeleri buna göre gizler. Sunucu yine her alanda kendi `cap`'ini uygular, istemcideki kontrol yalnız görünüm içindir.

```php
add_filter( 'flx_gql_capabilities', function ( array $caps ): array {
	$caps['flxb_dashboard'] = array( 'label' => __( 'Sees the donation dashboard', 'flx-donate' ), 'group' => 'donations', 'package' => 'flx-donate' );
	return $caps;
} );
```

- Anahtar `^[a-z0-9_]+$`; geçersizler atlanır. Etiket çevrilmiş metin.
- `flxViewer` (public): `loggedIn databaseId name isAdmin capabilities can(capability:)`. `capabilities` yalnız katalogdaki ve kişide olan yetkiler.
- `flxCapabilities` (logged_in): katalog satırları + `granted`.
- Paket `FlxViewer`'a kendi alanını ekleyebilir (`flx_gql_field( 'FlxViewer', … )`, ör. Flux Roles `roleKey`, `referralLink`); ziyaretçide null döner.
- İstemci: `useFlxViewer()`, `useFlxCan( 'flxb_dashboard' )` (`@fluxwp/graphql/react`; yüklenirken `undefined`). Yöneticiler için kısa yol yoktur: yönetici de katalogdaki yetkileriyle görünür.

## 4. Hatalar

| `extensions.code` | Ne zaman |
|---|---|
| `UNAUTHENTICATED` | Giriş yok / belirteç geçersiz |
| `FORBIDDEN` | Yetki yok |
| `NOT_FOUND` | Kayıt yok |
| `INVALID_INPUT` | Girdi geçersiz (alan: `field`) |
| `CONFLICT` | Kayıt bu arada değişti |
| `RATE_LIMITED` | Sınır aşıldı |
| `INTERNAL` | Beklenmeyen; mesaj genel, ayrıntı yalnız debug + yönetici |

- Servis `WP_Error` döner ya da `throw flx_gql_error( 'INVALID_INPUT', $mesaj, 'title' )`.
  `WP_Error` kodu `data['graphql_code']` → HTTP `status` → `INTERNAL` sırasıyla eşlenir.
- **Mutation'da** `INVALID_INPUT` ve `CONFLICT` payload'daki `errors: [FlxUserError!]!` listesine düşer (form alan yanında gösterir);
  diğerleri üst düzey hata. Sorgularda hepsi üst düzey.
- Mesaj kullanıcı dilinde (`__()`), iç ayrıntı (SQL, yol, anahtar) asla.
- İstemci `FlxGraphQLError` (`code`, `field`, `status`, `operation`) alır; `payloadErrors(result.errors)` ile alan hatalarını eşler.

## 5. Servis katmanı ve tipler

- Mantık `includes/services/class-<önek>-<alan>-service.php`. GraphQL `includes/graphql/`. İkisi de **PHPStan level max**: klasör var olduğu için strict analize kendiliğinden girer (`tools/phpstan/strict-packages.php`), `phpstan.neon`'a satır eklenmez. Pakete özel ayar `tools/phpstan/packages/<slug>.php`, sabit `packages/<slug>.bootstrap.php` (§12).
- Servis girdisi/çıktısı tipli: DTO sınıfı (`readonly` özellikler, PHP 8.1) ya da `@phpstan-type` dizi şekli. `array` / `mixed` döndüren servis kabul edilmez.
- Resolver servisten gelen DTO'yu döner; alan resolver'ları DTO özelliklerini okur.
- Ortak tipler: `FlxDateTime` (`flx_gql_datetime()`; `label` `flx_date()` ile), `FlxMoney` (`flx_gql_money()`), `FlxPhone` (`flx_gql_phone()`),
  `FlxFile` (`flx_gql_file()`), `FlxPerson` (sahibi Flux CRM; `flx_gql_person_type` filtresi), `FlxJSON` (yalnız gerçekten serbest yapı; gerekçe şart).

## 6. Şema tasarımı

- Varsayılan **non-null**. Nullable bilinçli seçilir ve açıklamada ne zaman boş olduğu yazılır.
- Durum, tür, rol gibi sabit kümeler **enum** (string değil).
- Liste = connection (`flx_gql_connection` + `flx_gql_connection_args` + `flx_gql_paginate`). Sayfa boyu en çok `max_page` (50). Düz liste yalnız küçük, sınırlı kümelerde (≤ 50, ör. etiketler).
- Mutation girdisi tek `input`, çıktı `{ <nesne>, errors }`. Kısmi güncelleme: verilmeyen alan değişmez.
- İlişki alanları DataLoader'la (`$context->get_loader( 'FlxTask' )->load_deferred( $id )`): N+1 yok.
- Her alan ve argümanda açıklama: **EN kaynak, `__()` ile**. Hardcoded Türkçe yok.
- Ayarlar elle tiplenmez: `flx_settings_schema`'daki grup otomatik `flxSettings` / `flxSettingsUpdate` altında görünür.
- **Gizli ayarlar GraphQL'e hiç girmez (fail closed, kit 0.2.1, `flx_gql_is_secret_key()`).** Ad kelimelere bölünür (snake, kebab, camelCase, nokta); `key`, `keys`, `secret`, `token`, `password`, `passwd`, `pass`, `passphrase`, `pwd`, `pin`, `auth`, `private`, `credential`, `salt`, `signature`, `bearer`, `dsn` kelimelerinden biri ya da bitişik `apikey`, `accesstoken`, `clientsecret`, `privatekey`, `passw…`, `secret`, `token` geçerse gizli sayılır. Örnek: `key_live`, `stripe_key`, `clientSecret`, `access-token`, `smtp_pass`.
  - Böyle bir **alan** tipe girmez; okunamaz, yazılamaz (panelden yazılır).
  - **Seçenek adı** gizli görünüyorsa (`flxai_keys`) grubun tamamı dışarıda kalır. Gruba `'secret' => true` yazmak da aynı.
  - Alanda `'secret' => true` ya da `'type' => 'password'|'secret'` → dışarıda.
  - `object`/`list` (FlxJSON) değerlerinde gizli görünen alt anahtarlar okumada silinir; yazmada istemci göndermediği için saklı değerleri korunur (liste öğesi sırasıyla eşlenir).
  - Paket `flx_gql_secret_key` filtresiyle ek ad gizleyebilir; kit'in gizli bulduğunu açamaz. Yanlışlıkla gizlenen alan (ör. `sort_key`) yeniden adlandırılır (`sort_field`), istisna açılmaz.
  - Test: `kit-test.php` "Secrets in settings".
- **Gizli değer yazılır, okunmaz.** Değer hiçbir GraphQL çıktısında görünmez: hiçbir tipte, hatada, logda, test ve tanı sonucunda da. Otomatik ayar tipleri (`flx_settings_schema`) fail closed kalır.
  - Paket gizli değeri kendi girdi tipinin write-only alanında alır (ör. `FlxAdsSettingsInput`, `FlxSocialAppValueInput`). Alan verilmez ya da boşsa saklı değer kalır; düz `"__clear__"` siler. Servis klasik ekranın yazdığı gibi saklar (paket zaten mühürlüyor ya da şifreliyorsa öyle).
  - Okuma yalnız durum döner, paket tipiyle (ortak kit tipi yok): kayıtlı mı; klasik ekran maske gösteriyorsa yalnız o (son karakterler); wp-config sabitiyse ve bu yüzden düzenlenemez mi. Örnek: `FlxAdsSecret` (`set`, `mask`, `fromConfig`); `FlxSocialAppField` (gizli `value` boş, `isSet`).
  - Dış servise yapıştırılacak kurulum değeri (webhook doğrulama değeri, tick anahtarı) ayar ya da liste tipine konmaz. Yöneticiye özel alan ya da mutation döner (ör. `flxSocialTickKeyReveal`); gerekçe `docs/graphql/ESLEME/<slug>.md`. Önbellekli cevapta dönmez.
  - Sözleşme testi hiçbir cevapta gizli değer olmadığını doğrular.
  - `ISTISNALAR.md` satırı değildir. Gizli yazmayı admin-post'ta tutmak artık istisna gerekçesi sayılmaz.
- Dosya: önce `POST /wp-json/flx/v1/upload` (`flx_gql_upload_contexts` filtresiyle `<paket>/<amaç>` bağlamı, `cap`, `mimes`, `max_mb`), sonra mutation `uploadId` alır ve `flx_gql_upload_take( $id, $bağlam )` ile dosyayı devralır.

## 7. Kırıcı değişiklik

- Alan/argüman/enum değeri **silinmez, adı değişmez, tipi daralmaz**. Önce `deprecationReason`, en az 2 paket sürümü sonra silinir.
- Şema snapshot'ı sahibine göre parçalı (§12): `packages/graphql/schema/core.graphql` (WordPress / WPGraphQL) ve
  `plugins/<slug>/graphql/schema.graphql` (paketin tipleri; başka tipe eklediği alanlar `extend type …`).
- CI (`graphql.yml`) parçaları yeniden üretir; paketin parçası commit edilmemişse ya da base commit'e göre kırıcı fark varsa kırılır.
  `core.graphql` farkı yalnız uyarıdır (CI'nın indirdiği WordPress sürümüne bağlı); onu Flux GraphQL sahibi tazeler.
  Bilinçli kırıcı değişiklik yalnız `graphql-breaking` etiketiyle ve PR'da gerekçeyle.

## 8. Önbellek ve performans

- Okuyan resolver tag ekler: `flx_gql_cache_tag( 'flx-tasks:list:12' )`. Yazan servis temizler: `flx_gql_purge( array( 'flx-tasks:list:12' ) )`.
  Tag biçimi `<slug>:<şey>[:<id>]`.
- Girişsiz persisted GET `cache_ttl` kadar herkese açık önbelleğe girer (LiteSpeed etiketleriyle). Girişli istek ve POST asla.
- Derinlik / karmaşıklık / batch sınırları panelden; bir ekranın sorgusu varsayılan sınırlara (12 / 1000) sığmalı.
- Polling: hafif "since" sorgusu (`flxChatUpdates(since:)`), ≥ 10 sn, sekme gizliyse dur (Hostinger kuralları).

## 9. İstemci (TypeScript)

- Operasyonlar `plugins/<slug>/graphql/operations/*.graphql`. Dosya başına tek operasyon, adı `Flx…`.
- `bun run --filter @fluxwp/graphql generate` (`bun install` de çalıştırır) üretir:
  - `plugins/<slug>/graphql/persisted-queries.json` (sha256 → belge; paket bunu `flx_gql_persisted_manifests` filtresiyle kaydeder). **Commit edilir**, elle düzenlenmez (CI `generate:check`).
  - `packages/graphql/src/generated/{types,operations}.ts` ve `packages/graphql/schema/full.graphql`. **Commit edilmez** (`.gitignore`); şema parçalarından ve operasyonlardan her kurulumda yeniden üretilir, CI typecheck'i bunlarla yapar.
- Ekranlar yalnız üretilmiş operasyonları kullanır: `useFlxQuery(FlxTaskList, vars)`, `useFlxMutation(FlxTaskUpdate)`.
  Okuma persisted GET, yazma POST. Serbest belge (`gql\`…\``) yazılmaz; strict kipte sunucu reddeder.
- API verisi için elle tip yazılmaz; `ResultOf<typeof Op>` / `VariablesOf<typeof Op>`.
- Strict TS: paket `tsconfig.strict.json`'dan türer, `any` yok (`tools/ts-strict/README.md`).
- `@fluxwp/modules` ve `@fluxwp/graphql` sürümü PR'da artmaz (`package.json` ve CHANGELOG'a dokunulmaz, CI reddeder). PR `.changes/<ad>.md` notu bırakır; sürümü ve CHANGELOG'u release anında "JS package versions" workflow'u yazar (`.changes/README.md`).
- wp-admin ekranı `flx_admin_screen()` ile kaydedilir, `flx_admin_screen_render()` ile basılır; pakete özel
  mount / module tag yazılmaz. İlk sorgular `preload` ile PHP'den gelir (değişkenler ekranınkiyle aynı).
  Ekranlar arası geçiş sayfa yenilemeden olur; sonuçlar sayfa çapında paylaşılır (`EKRAN-KABUGU.md`).

## 10. PHP sürümü

- `Requires PHP: 8.1`. Sözdizimi 8.1 sınırında: enum, `readonly` özellik, `match`, `never`, first-class callable serbest;
  `readonly class`, DNF tip, typed class constant, property hook, asymmetric visibility, `|>` yasak.
- Attribute serbest: `#[\Override]`, `#[\SensitiveParameter]` (şifre/token parametreleri), `#[\Deprecated]`, `#[\NoDiscard]`.
- 8.2+ fonksiyonları kit polyfill'inden (`json_validate`, `array_find/any/all`, `array_first/last`, `mb_trim`, `mb_str_pad`, `str_increment` …). `function_exists` kontrolü gerekmez.
- `GLOB_BRACE` yok (musl).

## 11. Test

- Sözleşme testleri `tools/tests/wp-graphql/<slug>/`: yetkili / yetkisiz / girişsiz kullanıcı, boş ve dolu veri, hata kodları, mutation `errors`.
- `tools/tests/wp-graphql/run.sh` hepsini PHP 8.1–8.5 matrisinde koşar (`flx-graphql.yml`).
- Pakete özel test kurulumu ortak betiklere yazılmaz: `tools/tests/wp-graphql/<slug>/deps.sh` (etkinleştirmeden önce; `Requires Plugins` vekili), `<slug>/site.sh` (site kurulunca source edilir; `$WP`, `$site`, `$root`, `$url`, `FIXTURES` hazır) ve `<slug>/wp-args` (sözleşme testinin `wp eval-file` ek argümanları, ör. `--skip-plugins=flx-ai`).

## 12. Paralel PR düzeni (ortak dosya yok)

Paket PR'ları aynı anda açılır ve birbirini beklemeden merge edilir. Bunun için **paket PR'ı yalnız kendi dosyalarına yazar**; ortak bir listeye satır eklemez.

| Eskiden ortak | Şimdi | Paket PR'ı ne yapar |
|---|---|---|
| `packages/graphql/schema/schema.graphql` | `plugins/<slug>/graphql/schema.graphql` (+ `core.graphql`) | Yalnız kendi parçasını commit eder: `bun run --filter @fluxwp/graphql schema -- plugins/<slug>`, CI'nın `graphql-schema-ci` artifact'ındaki kendi dosyası ya da (artifact indirilemiyorsa) `gh api repos/FluxWP/FluxWP/actions/jobs/<job id>/logs \| bash tools/graphql/ci-parts.sh plugins/<slug>`. Başka paketin parçası ya da `core.graphql` farkı commit edilmez. |
| `packages/graphql/src/generated/*.ts` | commit dışı (`bun install` üretir) | Hiçbir şey; `persisted-queries.json` yine commit edilir. |
| `@fluxwp/graphql` sürümü + CHANGELOG | `.changes/<slug>.md` (§9) | Not bırakır (`.changes/README.md`). `package.json` sürümüne ve `## x.y.z` başlığına dokunmaz. |
| `packages/modules/src/index.ts` | `packages/modules/src/<klasör>/screens.ts` | Ekranını kendi klasöründeki `screens.ts`'e yazar (`registry.ts`). `index.ts`'e dokunmaz. |
| `@fluxwp/modules` sürümü + CHANGELOG | `.changes/<slug>.md` (§9) | Not bırakır (`.changes/README.md`). `package.json` sürümüne ve `## x.y.z` başlığına dokunmaz. |
| `tools/phpstan/phpstan.neon` `paths` | otomatik (`strict-packages.php`) | Hiçbir şey; özel ayar gerekirse `tools/phpstan/packages/<slug>.php`. |
| `tools/phpstan/bootstrap.php` sabitleri | otomatik (`strict-bootstrap.php`, ana dosya + `includes/*.php` `define`'ları) | Hiçbir şey; değeri önemli sabit için `tools/phpstan/packages/<slug>.bootstrap.php`. |
| `tools/tests/wp-graphql/site.sh`, `run.sh` | paket kancaları (§11) | `<slug>/deps.sh`, `<slug>/site.sh`, `<slug>/wp-args`. |
| `docs/graphql/DURUM.md` tablosu | `docs/graphql/durum/<slug>.md` | Yalnız kendi durum dosyası. Tablo: `bun tools/graphql/durum.ts`. |
| `docs/graphql/ISTISNALAR.md` paket satırları | `docs/graphql/durum/<slug>.md` → `## İstisnalar` | Pakete özel istisna kendi durum dosyasına. `ISTISNALAR.md` yalnız birden çok paketi ilgilendiren satırlar (Faz 0 sahibi). Liste: `bun tools/graphql/durum.ts --istisnalar`. |

- Merge sırası önemsiz: GitHub PR CI'ı main ile birleşmiş hali koşar; yeni main'i almak için yeniden koşturmak gerekmez. Paket gerçekten başka paketin kodunu kullanıyorsa (ör. `FlxPerson`) bağımlılık PR'da yazılır, o merge edilince yeniden koşulur.
- main'e push'ta `graphql.yml` yine tam koşar; iki PR'ın birlikte bozduğu bir şey (aynı operasyon adı, aynı ekran id'si) orada görünür.
- Ortak dosyayı değiştirmek gerekirse (kit, `@fluxwp/graphql` çekirdeği, `registry.ts`, workflow) iş koordinatör üzerinden Faz 0 sahibine gider; paket PR'ına girmez.
- Lockfile (`bun.lock`): paket PR'ı yeni bağımlılık eklemez; gerekiyorsa ayrı küçük PR.
