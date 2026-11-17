# Ekran kabuğu (Flux GraphQL 0.4.0)

wp-admin'deki her Flux React ekranı tek bir kabuktan açılır: `plugins/flx-graphql/includes/screens.php` +
`assets/js/screens.js`. Amaç: sol menüden başka bir Flux ekranına geçerken sayfa yenilenmez, ortak sorgular
tekrar gitmez, ilk açılışta veri PHP'den gelir (spinner yok). Klasik PHP sayfası hissi.

## Nasıl çalışır

1. **İlk yükleme.** Paket sayfayı `flx_admin_screen()` ile kaydeder. Kabuk paketin bundle'ını (type=module)
   ekler, ekranın ilk sorgularını PHP'de `graphql()` ile çalıştırır ve sonucu sayfaya basar:
   `<script type="application/json" id="flx-gql-preload">{"at":…,"entries":{"<persisted id>:<vars>":…}}</script>`.
   `useFlxQuery` bunu initialData olarak kullanır; ekran veriyle boyanır, eskiyse arkada yeniler.
2. **Geçiş.** Kayıtlı başka bir ekranın linkine tıklama (sol menü, Flux Admin alt çubuğu, ekran içi link)
   sayfayı yenilemez. `screens.js`:
   - hedef paketin bundle'ını bir kez `import()` eder (aynı commit'ten çıkan bundle'lar tek runtime paylaşır,
     id = entry + chunks.json md5),
   - hover / focus / touch anında ilk sorguları tek batch POST ile önceden çeker,
   - yeni ekranı gizli kapta mount eder, boyanınca değiştirir, eskisini unmount eder,
   - adres (pushState), başlık, body class, menü vurgusu ve CSS'i günceller; geri / ileri çalışır,
   - `flx-screens:navigate` olayını yayar (`detail.route`). Flux Admin sayfa başlığı bununla güncellenir.
3. **Ortak sonuç deposu.** `globalThis.__flxGqlStore` (`packages/graphql/src/store.ts`): anahtar
   `<persisted id>:<değişkenler, anahtarlar sıralı JSON; boş = {}>` (PHP: `flx_admin_screen_stable_vars()`).
   Her başarılı sorgu yazar; iki pakette iki React Query kopyası olsa da sonuç paylaşılır.
   Mutation sonrası depo "stale" işaretlenir (gösterilir, ilk kullanımda yenilenir).
4. **Batch.** Aynı tick'te başlayan sorgular tek WPGraphQL batch POST'unda gider (en çok 10). Tek sorgu
   persisted GET kalır.

## Paket kuralları

- Ekran sayfası `flx_admin_screen( $page, $args )` ile kaydedilir (`admin_menu`, menü eklendikten sonra,
  öncelik 20). Pakete özel `<script type="module">` / inline mount / bundle enqueue YAZILMAZ.
- Sayfa callback'i: `if ( <yeni görünüm açık> && flx_admin_screen_render() ) { return; }` sonra klasik ekran.
- `preload`: ekranın ilk açılışta çalıştırdığı sorgular, `array( array( 'FlxOp', $vars ) )`. Değişkenler
  ekranın `useFlxQuery`'ye verdiğiyle **aynı** olmalı (anahtar aynı olmazsa preload boşa gider).
  Ağır / kullanıcıya göre değişken sorgu preload'a konmaz.
- `start_param` (örn. `tab`): ekranın başlangıç sayfasını seçen query argümanı. Bu argüman dışındaki
  argümanlı linkler normal sayfa yüklemesiyle açılır.
- `options`: mount seçenekleri (siteName, renkler, classicUrl…) callable'dan döner; nonce, graphqlUrl, dil
  kabuktan gelir.
- `enabled`: kullanıcının yeni görünümü açık mı (callable). Sayfaya değil kullanıcı tercihine bakmalı;
  yoksa route listesine girmez.
- `layout => 'full'` (wp-admin'i örten ekran, örn. Flux Tasks): kendi kabuğu olduğu için SPA geçişine
  girmez, her zaman sayfa olarak açılır. Diğer ekranlardan ona link normal yüklemedir.
- Ekranın ihtiyaç duyduğu klasik script'ler `scripts`, sayfada olması gereken global'ler `needs`.
- Ekran kendi `useFlxQuery` / `useFlxMutation`'ını kullanır; REST ile veri çeken ekran (Tasks şimdilik)
  preload'dan yararlanmaz, yalnız geçişten yararlanır.
- Bundle `tools/fluxapp/embed-sync.sh` ile senkronlanır; aynı commit'ten gelen paketler tek runtime paylaşır.
- FluxWP dışındaki eklentiler ortak runtime üzerinde çalışır (`flux-screens.js`): EKLENTI-EKRANLARI.md.
- Kabuğu kapatmak için: `add_filter( 'flx_admin_screens_navigation', '__return_false' );`.
- Route'a ek bilgi: `flx_admin_screen_route` filtresi (`$route, $page`), olayda `detail.route` olarak gelir.

## Test

`tools/tests/wp-graphql/flx-graphql/contract-test.php`: preload anahtarı TS ile aynı, kayıt, bundle id,
render çıktısı, route listesi, navigation filtresi.

## Ölçüm (pilot, yerel test sitesi, hover 150 ms + tık, 3 koşu medyanı)

| Geçiş | Önce | Sonra |
|---|---|---|
| Tasks içi (journeys, settings) | ~750–850 ms, ~40 istek, tam yükleme | ~100 ms, 0–1 istek |
| SEO ↔ Speed ↔ Analytics | ~750–850 ms, ~40 istek, tam yükleme | ~110–310 ms, 1–3 istek |
| Ziyaret edilmiş ekrana dönüş | tam yükleme | ~60 ms, 0 istek |
| İlk açılış GraphQL isteği | 1–3 | 0 (PHP preload) |
