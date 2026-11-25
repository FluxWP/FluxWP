# Ekran kabuğu (Flux GraphQL 0.5.0)

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
4. **Öğrenen preload (0.5.0).** Ekran mount olunca ilk 2,5 sn'de (ya da sayfadan önce çıkılırsa o ana
   kadar) gönderdiği sorgular kaydedilir (`flxStoreRecord`). PHP'nin bastığından farklıysa
   `admin-ajax.php?action=flx_screen_learn` (wp_rest nonce) ile gider; kullanıcı meta
   `flx_gql_screen_queries` içinde `<modül>|<başlangıç sayfası>` anahtarıyla saklanır (ekran başına 10,
   en çok 60 ekran). Sonraki açılışta açık `preload` listesine eklenip PHP'de çalışır. Toplam preload
   süresi 0,8 sn ile sınırlı; aşan sorgular tarayıcıda çalışır.
5. **Parça önyükleme (0.5.0).** `embed-sync.sh` Vite manifest'inden `chunks.json` `preload`
   alanını yazar (`entry`: girişin statik parçaları, `screens.<modül>`: ekranın parçaları;
   `tools/fluxapp/embed-preload.mjs`). Kabuk bunları `<link rel="modulepreload">` olarak `<head>`'e basar:
   parçalar sırayla değil birlikte iner.
6. **Tek giriş.** Parça derlemesinde giriş adı hash taşır (`flux-modules-<hash>.js`); `flux-modules.js`
   yalnız onu yeniden dışa veren tek satırlık dosyadır. Böylece `?ver=` ile yüklenen giriş ve parçaların
   import ettiği giriş aynı modüldür (önceden 510 KB giriş iki kez çalışıyordu, iki ayrı depo oluşuyordu).
7. **Batch.** Aynı tick'te başlayan sorgular tek WPGraphQL batch POST'unda gider (en çok 10). Tek sorgu
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
- Ekranın ihtiyaç duyduğu klasik script'ler `scripts`, sayfada olması gereken global'ler `needs`
  (noktalı yol olabilir: `wp.media`; sayfada yoksa link normal yüklenir). Sayfaya özel enqueue `enqueue`.
- `wrap_class`: satır içi ekranın `.wrap`'ine eklenen sınıf (paketin eski CSS seçicileri için).
- `options` içindeki `startPage` boş değilse sayfa argümanından gelen başlangıcı ezer (ör. detay sayfası).
- Menü adresinin kendi argümanları (edit.php?post_type=…&page=…) route'ta `args` olarak gelir; gezgin
  bunları bekler, başka argüman varsa normal yükler.
- Ayar ekranı formu (draft) `useState(() => q.data ? … : null)` ile başlar; yoksa preload'lı veriyle bile
  ilk karede yükleniyor göstergesi çıkar.
- Ekran kendi `useFlxQuery` / `useFlxMutation`'ını kullanır; REST ile veri çeken ekran (Tasks şimdilik)
  preload'dan yararlanmaz, yalnız geçişten yararlanır.
- Bundle `tools/fluxapp/embed-sync.sh` ile senkronlanır; aynı commit'ten gelen paketler tek runtime paylaşır.
- FluxWP dışındaki eklentiler ortak runtime üzerinde çalışır (`flux-screens.js`): EKLENTI-EKRANLARI.md.
- Kabuğu kapatmak için: `add_filter( 'flx_admin_screens_navigation', '__return_false' );`.
- Route'a ek bilgi: `flx_admin_screen_route` filtresi (`$route, $page`), olayda `detail.route` olarak gelir.
- `$route['pageLoad'] = true`: o ekrana girerken ve ondan çıkarken gezgin sayfayı normal yükler. wp-admin'in kendi
  kabuğunu değiştiren ekranlar için (Flux Admin uygulama modu: Çağrı Merkezi, Görevler — sol menü ikon şeridi,
  `flxa-appmode folded` gövde sınıfları yalnız sunucuda basılır).
- Seçeneklerde `menuTitle`: paketin sol menüdeki üst öğe adı (rozetsiz). `Screen` ürün adıyla ("Flux …") verilen
  başlığı bununla gösterir.

## Dil

`includes/locale.php`: `_locale=user` taşıyan GraphQL isteği (tüm FluxWP istemcileri ekler) WPGraphQL kimliği
doğruladıktan sonra kullanıcının diline geçer (`switch_to_locale`); Flux Lang `?lang=` ile dil seçen istek kendi
dilinde kalır. `lang_dir_for_domain` filtresi `flx-*` metin alanları için eklentinin `languages/` klasörünü verir:
paketlerin `locale-loader.php`'si ilk yüklemeyi WordPress'e klasörü öğretmeden yaptığı için dil değişince çeviri
bulunamıyor, metinler İngilizce kalıyordu. `locale` filtresi eklenmez (seçenek okuyan filtre kendine döner).

## Test

`tools/tests/wp-graphql/flx-graphql/contract-test.php`: preload anahtarı TS ile aynı, kayıt, bundle id,
render çıktısı, route listesi, navigation filtresi, parça listesi, öğrenilen sorgular (kullanıcı başına,
doğrulama, birleşme), `learn` seçenekleri, `wrap_class`.

## Ölçüm (pilot, yerel test sitesi, hover 150 ms + tık, 3 koşu medyanı)

| Geçiş | Önce | Sonra |
|---|---|---|
| Tasks içi (journeys, settings) | ~750–850 ms, ~40 istek, tam yükleme | ~100 ms, 0–1 istek |
| SEO ↔ Speed ↔ Analytics | ~750–850 ms, ~40 istek, tam yükleme | ~110–310 ms, 1–3 istek |
| Ziyaret edilmiş ekrana dönüş | tam yükleme | ~60 ms, 0 istek |
| İlk açılış GraphQL isteği | 1–3 | 0 (PHP preload) |

## İlk açılış ölçümü (0.5.0, yerel test sitesi, sıcak önbellek, ilk ziyaret, 3 koşu medyanı)

"Hazır" = ekranda son DOM değişikliği. Önce = main (#336 sonrası, SEO/Speed/Analytics dışı ekranlar kabuksuz).

| Ekran | Önce | Sonra | Spinner önce → sonra |
|---|---|---|---|
| Roller | 864 ms | 572 ms | 2 → 0 |
| Diller | 757 | 463 | 2 → 0 |
| reCAPTCHA | 879 | 448 | 2 → 0 |
| Flux Admin ayarları | 801 | 521 | 2 → 0 |
| Yedekleme | 845 | 503 | 2 → 0 |
| Wiki | 1037 | 476 | 3 → 0 |
| Kurulum (Setup) | 1025 | 737 | 2 → 0 |
| Dosyalar | 938 | 622 | 2 → 0 |
| Studio | 922 | 514 | 1 → 0 |
| Chatbot | 836 | 521 | 2 → 0 |
| Tema Ayarları | 950 | 630 | 2 → 0 |
| Uygulama ayarları | 773 | 466 | 2 → 0 |
| Speed | 608 | 468 | 0 → 0 |

Flux AI ekranları da kabukta (0.29.1); ilk sorguları öğrenilerek preload edilir. Genel bakış 596 → 553 ms, spinner 1 → 0.
