# Flux Lang: GraphQL

Flux Lang 0.3.0. Flux GraphQL (`flx-graphql`) gerekli. Şema `packages/graphql/schema/schema.graphql`, işlemler `plugins/flx-lang/graphql/operations/`.

## Sorgular

| Alan | Yetki | Not |
|---|---|---|
| `flxLanguages` | herkese açık | Aktif diller (`FlxLanguage`); önbellek tag'i `flx-lang:languages` |
| `flxLanguageSettings` | `manage_options` | Dil listesi, ayarlar, site metinleri, katalog, ülke bilgisi |
| `flxVisitorCountry` | herkese açık | Ziyaretçi ülkesi. **Önbelleğe alınmaz** (`Cache-Control: no-store`) |
| `flxLanguageJob(id)` | giriş yapmış | AI çeviri işi durumu |

## Mutation'lar

`flxLanguageSettingsUpdate`, `flxLanguageSet` (içerik dilini ata), `flxLanguageTranslationCreate` (boş çeviri taslağı), `flxLanguageTranslateStart` + `flxLanguageTranslateStep` (AI çeviri, iki adımlı). Hatalar `extensions.code`: ayar doğrulamasında alan başına `flxl_invalid_<alan>`.

```graphql
query { page(id: "/hakkimizda/", idType: URI) { title flxLanguage flxTranslations { lang id url status isSelf } } }
```

## Diğer paketler için tasarım

- **Her içerik tipinde** `flxLanguage: String` ve `flxTranslations: [FlxTranslation!]!` var (`ContentNode` arayüzü). Çeviri eşi listesi içerik okuma yetkisini izler; `url` yayınlanmamışsa null, `editUrl` yalnız düzenleyene.
- **Yeni içerik tipi** (ör. flx-donate kampanyası) bağlanmak için `flxl_gql_content_types` filtresine tip adını ekler: `add_filter( 'flxl_gql_content_types', fn( $t ) => array_merge( $t, [ 'FlxCampaign' ] ) );`
- **Dil seçimi**: GraphQL isteğinde `?lang=en` verilirse içerik/terim listeleri o dile süzülür; verilmezse REST gibi süzülmez (tüm diller).
- **Dil servisi**: PHP tarafında `FLXL_Language_Service` (`languages()`, `post_language()`, `translations()`, `set_post_language()`, `create_translation()`, `visitor_country()`) kullanılır; `flxl_*` eski fonksiyonlar çalışmaya devam eder.
- **Tarayıcı dili önce** davranışı (0.2.12) değişmedi; `flxVisitorCountry` yalnız ülke ipucudur.
- Dil çözümlemesinde `locale` filtresi reentrancy guard'ı korunur; GraphQL isteği REST gibi ele alınır.

## Ekran

Ayarlar > Languages varsayılan olarak React ekranını açar (`flxl-lang`, FluxWP uygulamasında da). Klasik sayfa "Classic view" bağlantısıyla (kullanıcı meta `flxl_ui=classic`).
