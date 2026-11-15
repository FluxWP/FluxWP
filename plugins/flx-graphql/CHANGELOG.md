# Changelog

## 0.1.0

- İlk sürüm: FluxWP eklentileri için ortak GraphQL sunucusu (`/graphql`).
- Gömülü, sabit sürüm WPGraphQL 2.28.0; ayrıca wordpress.org'dan kurulmaz. Ayrı kurulu WPGraphQL varsa çakışma olmaz: desteklenen aralıktaysa o kullanılır, değilse uyarı ve "Flux GraphQL sürümünü kullan" düğmesi. Kullanım verisi (Appsero) gönderilmez.
- Flux GraphQL kit: tipli kayıt sarmalayıcıları (`flx_gql_type/query/mutation/field/enum/input`), zorunlu yetki (cap yoksa alan kaydedilmez), sabit kodlu hata modeli, sayfalama (en fazla 50 kayıt), DataLoader tabanı, global ID, ortak tipler (`FlxDateTime`, `FlxMoney`, `FlxPhone`, `FlxFile`, `FlxPerson`, `FlxUserError`).
- `flx_settings_schema` ile tanımlı panel ayarları elle tip yazmadan GraphQL'de (`flxSettings`, `flxSettingsUpdate`).
- Kayıtlı sorgular (persisted queries): üretimde yalnız FluxWP paketleriyle gelen sorgular çalışır; herkese açık okumalar GET ile LiteSpeed/CDN önbelleğine girer, cache tag ile temizlenir.
- Ortak dosya yükleme ucu `/wp-json/flx/v1/upload` (GraphQL işlemleri `uploadId` ile çalışır).
- Derinlik ve karmaşıklık sınırları, CORS izin listesi, PHP 8.2–8.5 fonksiyonları için polyfill seti.
- Node yükleyicileri `flx_gql_loaders` aksiyonunda adlandırılır (HTTP isteğinde AppContext şemadan önce kurulduğu için).
- Ayarlar › Flux GraphQL paneli ve sistem kontrolü satırları. Türkçe çeviri.
