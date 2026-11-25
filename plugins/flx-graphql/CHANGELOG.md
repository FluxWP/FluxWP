# Changelog

## 0.7.4
- Eklenti ekranları runtime'ı (`assets/runtime/`) güncel paketlerle yeniden derlendi: ortak ekran kabuğu (`Screen`, `Tabs`) ve stilleri artık runtime'da. `@fluxwp/plugin-kit` 0.1.2 ile derlenen ekranların (Flux Extras: Qurban, Agents…) başlık ve sekmeleri diğer FluxWP ekranlarıyla aynı görünür; eski runtime'da sekmeler stilsiz kalıyordu.

## 0.7.3
- Sayfa yenilemeden ekran geçişinde sol menü: `#` ile başlayan bağlantılar (ör. alt menüdeki "Diğer" düğmesi) geçerli sayfa sayılmaz. Menü işaretlenince `flx:menu-marked` olayı yayınlanır; Flux Admin yeni sayfanın grubunu açar.

## 0.7.2
- Gömülü WPGraphQL'in "Review your WPGraphQL settings" bildirimi ve "Review Settings" menü öğesi gizlendi: erişim, istek sınırı ve hata ayıklama ayarlarının hepsi zaten Flux GraphQL ayarlarından yönetiliyor.

## 0.7.1
- Tam ekran uygulamalar (Studio, Görevler, Sosyal Medya) pencereden taşmıyor: yönetim çubuğu ve alt çubuk payı düşülünce alt kısım (Studio yazma kutusu) artık kesilmiyor.

## 0.7.0

- **Ortak "ekran yüklenemedi" kutusu.** `flx_admin_screen_page()`, `flx_admin_screen_unavailable()` ve `flx_admin_screen_failsafe()`: React ekranı açılamazsa (Flux GraphQL hazır değil, paket dosyası eksik, bundle/parça yüklenemedi, 6 sn sonra ekran hâlâ boş) boş sayfa yerine "Bu ekran yüklenemedi" kutusu ve **Sayfayı yenile** düğmesi çıkar. `flx_admin_screen_render()` bu kutuyu her ekrana kendisi ekler.
- Ekran kabuğundan klasik sayfa yedeği ve `enabled` örneği kaldırıldı; klasik ekranlar artık yok.

## 0.6.0

- Ekran kabuğu: ekranın ilk açılışta gönderdiği sorgular kullanıcı başına öğrenilir (admin-ajax `flx_screen_learn`, kullanıcı meta `flx_gql_screen_queries`, ekran başına en çok 10) ve bir sonraki açılışta PHP'de önceden basılır. Önyükleme 0,8 sn bütçeyle sınırlı.
- Ekranın JS parçaları `<link rel="modulepreload">` ile sayfa başında birlikte iner (chunks.json `preload`, tools/fluxapp/embed-preload.mjs).
- Yeni argüman `wrap_class`; seçeneklerdeki `startPage` boş değilse sayfa argümanını ezer; `needs` noktalı yol kabul eder (`wp.media`).
- Gezgin, menü adresinin kendi argümanlarını (edit.php?post_type=…) tanır.
- Belge: docs/graphql/EKRAN-KABUGU.md.

## 0.5.0
- Eklenti ekranları için ortak runtime (`assets/runtime/`): React, React Query, i18next ve açık `@fluxwp` paketleri sayfaya bir kez yüklenir. `@fluxwp/plugin-kit` ile derlenen bir eklenti yalnız kendi ekranlarını taşır (`assets/flux/flux-screens.js`, birkaç KB) ve `FluxRuntime.register()` ile kaydeder. FluxWP dışındaki eklentiler de wp-admin'e aynı kabukla React ekranı ekleyebilir.
- `flx_admin_screen()` klasörde `flux-screens.js` görürse ekranı runtime üzerinden açar; menüden geçiş, ilk sorguların PHP'den gelmesi ve geri/ileri aynı çalışır. FluxWP paketlerinin `flux-modules.js` bundle'ları değişmeden çalışır.
- Kurallar: docs/graphql/EKLENTI-EKRANLARI.md.

## 0.4.0

- Ekran kabuğu: wp-admin'deki Flux React ekranları `flx_admin_screen()` ile kaydedilir, `flx_admin_screen_render()` ile basılır. Sol menüden başka bir Flux ekranına geçiş sayfayı yenilemez: hedef paketin bundle'ı bir kez yüklenir, ilk sorgular üzerine gelince tek istekte önceden çekilir, ekran hazır olunca yer değiştirir; adres, başlık ve menü vurgusu güncellenir, geri/ileri çalışır. Kayıtlı olmayan sayfalar normal açılır.
- İlk açılışta ekranın ilk sorguları PHP'de çalışır ve sonucu sayfaya basılır; ekran veriyle açılır, yükleniyor göstergesi görünmez.
- `flx_admin_screens_navigation` (kabuğu kapatır) ve `flx_admin_screen_route` filtreleri, `flx-screens:navigate` olayı. Kurallar: docs/graphql/EKRAN-KABUGU.md.

## 0.3.1

- Geliştirici: `wp flx-gql schema --output=… --owners=…` şemayı ve her tip/alanın hangi paketten geldiğini yazar. Şema snapshot'ı paket başına dosyalara bölünür (`plugins/<slug>/graphql/schema.graphql`), paralel paket PR'ları aynı dosyaya dokunmaz. Sahip kaydı yalnız bu komutta açılır; normal GraphQL isteklerinin davranışı ve hızı değişmez.

## 0.3.0

- GraphQL sepet köprüsü: `flxCart` sorgusu ve `flxCartItemAdd`, `flxCartItemUpdate`, `flxCartItemRemove`, `flxCartClear`, `flxCartCouponApply`, `flxCartCouponRemove`, `flxCartShippingRateSelect`, `flxCartCustomerUpdate`. Hepsi içeride WooCommerce Store API'yi çağırır; fiyat, kupon, stok ve ödeme yöntemleri blok ödeme sayfasındakiyle aynı çalışır.
- Headless ön yüz ve uygulamalar için `Cart-Token` oturumu (cevapta `cart.token` ve `Cart-Token` başlığı); aynı sitedeki sayfalar için Store API `Nonce`. Çerez oturumunda nonce'suz yazma reddedilir.
- Ödeme GraphQL'e girmez: checkout Store API'de kalır, kart verisi köprüden geçmez. Sepet cevapları hiçbir zaman önbelleğe alınmaz.
- Paketler için `FLXGQL_Cart::add_item()` (kendi sepet satırı verisiyle ekleme, ör. bağış), `flx_gql_cart_item_kind` filtresi ve `flx_gql_no_store()`.
- Kayıtlı işlemler ve Türkçe çeviri.

## 0.2.1

- Güvenlik: panel ayarlarından üretilen GraphQL tiplerinde gizli alan süzgeci sıkılaştı (fail closed). `key_*`, `*_key`, `*secret*`, `*token*`, `*password*`/`pass`/`pwd`, `auth`, `private`, `credential` biçimleri ve camelCase/kebab yazımları şemaya girmez; adı gizli görünen seçeneğin tamamı dışarıda kalır; alanda `'secret' => true` ya da `type: password` desteklenir. Nesne/liste ayarlarındaki gizli alt anahtarlar okumada silinir, yazmada saklı değer korunur. Önceki sürümde `key_*` biçimi açıkta kalabiliyordu.

## 0.2.0

- Yetki modeli: `flx_gql_any_cap()` (biri yeter) ve `flx_gql_all_caps()` (hepsi gerekir) `cap` yardımcıları; paketler yetkilerini `flx_gql_capabilities` filtresiyle bildirir.
- `flxViewer` (giriş yapan kişi, yönetici mi, katalogdaki yetkileri, `can(capability:)`) ve `flxCapabilities` (katalog + kişide var mı). Paketler `FlxViewer`'a kendi alanlarını ekleyebilir.
- Kendi kayıtlı sorgusu `FlxViewer` (persisted manifest).

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
