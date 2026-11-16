# GraphQL sepet köprüsü (`flxCart*`)

Flux GraphQL 0.3.0. WooCommerce Store API sepetini GraphQL'de sunar. Plan §5 Faz 2'nin ilk parçası; Flux Commerce ve Flux Donate bunun üstüne kurulur.

## 1. Karar: köprü nerede?

**Flux GraphQL'in içinde** (`plugins/flx-graphql/includes/woo/`), ayrı bir WooCommerce modülü olarak. Kit çekirdeğine (registry, errors…) dokunmaz; kit'e yalnız `flx_gql_no_store()` eklendi.

Gerekçe:
- **Bağış siteleri Flux Commerce kurmaz.** Flux Donate `Requires Plugins: woocommerce, flx-crm`; sistem kontrolü bağış sitesinde Commerce istemez. Köprü Commerce'te olsaydı Donate'in sepeti için Commerce kurmak gerekirdi.
- Store API WooCommerce çekirdeği; paket kodu değil. Her WC kullanan paket (Commerce, Donate, ileride Zakat/Qurban preset'leri) aynı tek kopyayı kullanır, iki köprü birbirinden sapmaz.
- Şema her sitede aynı: tipler WooCommerce yokken de kayıtlı (alanlar `NOT_FOUND` döner), böylece şema snapshot'ı ve üretilen TS tipleri sitede ne kurulu olduğuna bağlı değil.

## 2. İlke: köprü yalnız sarar

- Her okuma/yazma içeride `rest_do_request()` ile **Store API** `/wc/store/v1/cart/*` rotasına gider. Fiyat, kupon, stok, ücret, vergi, kargo ve gateway kancaları blok checkout'takiyle aynı çalışır.
- **Ödeme GraphQL'de yok.** Checkout `POST /wc/store/v1/checkout` olarak kalır (ISTISNALAR.md). EsnekPOS direct (`PaymentContext`), iyzico, havale gateway'leri değişmeden çalışır.
- **Kart verisi hiçbir yerde tutulmaz ya da loglanmaz:** şemada kart alanı yok (test bunu denetler), köprü istek/yanıt gövdesi loglamaz, beklenmeyen hatada iç ayrıntı yerine genel mesaj döner.
- Cevaplar kişiseldir: `Cache-Control: no-store, private` + `X-LiteSpeed-Cache-Control: no-cache` (persisted GET bile önbelleğe girmez).

## 3. Oturum

| İstekte | Ne olur |
|---|---|
| `Cart-Token` başlığı (headless, mobil, FluxApp) | Token doğrulanır (Store API'nin imzası), istek boyunca Store API `SessionHandler` kullanılır. Nonce gerekmez. |
| `Nonce` başlığı (aynı sitedeki sayfa, WC çerez oturumu) | Store API'ye aynen geçer; CSRF denetimi WooCommerce'te kalır. |
| İkisi de yok, WC oturum / giriş çerezi de yok (ilk ziyaret) | Yeni sepet açılır; sahte çapraz istek atılacak oturum olmadığı için nonce köprüden. Cevapta yeni `Cart-Token` gelir. |
| İkisi de yok ama çerez oturumu var | Yazma `FORBIDDEN` (olası CSRF). |
| Geçersiz / süresi dolmuş token | `UNAUTHENTICATED` ("Sepetinizin süresi doldu"). |

Her cevap `cart.token` (ve `Cart-Token` yanıt başlığı) taşır; istemci saklar ve geri gönderir. CORS: `Cart-Token` ve `Nonce` istek başlığı izinli, yanıtta `Access-Control-Expose-Headers: Cart-Token, Nonce`.

Giriş yapmış kişi tarayıcıdan çağırıyorsa `X-WP-Nonce` göndermeli (WPGraphQL nonce'suz çerezi ziyaretçi sayar) ve sepet için ya `Cart-Token` ya `Nonce` kullanmalı.

## 4. Şema

Sorgu: `flxCart: FlxCart!` (cap `public`: sepet oturumu tutan kişinin; başkasının sepeti okunamaz).

Mutation'lar (hepsi `cap: public`, çıktı `{ cart: FlxCart, errors }`):

| Mutation | Store API | Girdi |
|---|---|---|
| `flxCartItemAdd` | `cart/add-item` | `productId: Int!`, `quantity: Int` (1), `variation: [FlxCartVariationInput!]` → ayrıca `itemKey` |
| `flxCartItemUpdate` | `cart/update-item` | `key: String!`, `quantity: Int!` (0 = sil) |
| `flxCartItemRemove` | `cart/remove-item` | `key: String!` |
| `flxCartClear` | `DELETE cart/items` | — |
| `flxCartCouponApply` / `flxCartCouponRemove` | `cart/apply-coupon` / `remove-coupon` | `code: String!` |
| `flxCartShippingRateSelect` | `cart/select-shipping-rate` | `rateId: String!`, `packageId: Int` |
| `flxCartCustomerUpdate` | `cart/update-customer` | `billingAddress`, `shippingAddress: FlxCartAddressInput` (verilmeyen alan değişmez) |

`FlxCart`: `token nonce itemsCount items coupons fees totals needsPayment needsShipping hasCalculatedShipping shippingPackages paymentMethods paymentRequirements notices billingAddress shippingAddress checkout { endpoint pageUrl cartPageUrl }`.
Tutarlar `FlxMoney` (Store API'nin kuruş cinsinden değerleri çevrilir). `FlxCartItem.kind` satırın türü (`product`; Donate `donation`). `quantityLimits.editable` false ise adet değiştirilemez (bağış satırı).

Hatalar: Store API hatası kit koduna çevrilir; 4xx → payload `errors` (`INVALID_INPUT`, `CONFLICT`) ve alan (`productId`, `quantity`, `key`, `code`, `variation`, `rateId`). Nonce/oturum → `FORBIDDEN` / `UNAUTHENTICATED`. WooCommerce yok → `NOT_FOUND`.

Kayıtlı işlemler: `plugins/flx-graphql/graphql/operations/` (`FlxCart`, `FlxCartItemAdd` … ve ortak fragment `FlxCartFields`). Okuma persisted GET, yazma POST.

## 5. İstemci

```ts
import { flxCart } from '@fluxwp/graphql/flx-cart';
const cart = flxCart(gql, { store: localStorageTokenStore });  // store: { get, set } (varsayılan bellek)
await cart.add(42, 2);                 // { cart, itemKey }
await cart.applyCoupon('YAZ10');       // FlxCartInputError (errors: alan → mesaj, payloadErrors())
const headers = await cart.checkoutHeaders();  // Store API checkout POST'u için { 'Cart-Token': … }
```

Aynı sitedeki sayfa çerez oturumundaysa `flxCart(gql, { nonce: () => wcSettings.storeApiNonce })`.

## 6. Flux Commerce ve Flux Donate için

Bu PR main'e girince başlarlar. Kullanacakları:

**Sorgu / mutation'lar:** yukarıdaki `flxCart` + 8 mutation, `FlxCartFields` fragment'ı. Kendi işlemlerinde sepeti `cart { ...FlxCartFields }` olarak döndürsünler (ekranlar tek tip görür).

**Genişleme noktası: pakete özel satır (bağışın sepete eklenmesi).** Paket kendi mutation'ını yazar, sepete köprünün PHP API'siyle ekler:

```php
// flx-donate (örnek): flxDonationCartAdd(input: FlxDonationCartAddInput!) { cart itemKey errors }
add_action( 'flx_gql_register', function () {
	flx_gql_mutation( 'flxDonationCartAdd', array(
		'cap'     => 'public',
		'input'   => array( /* typeId, option, amount, names… (tipli) */ ),
		'output'  => array( 'cart' => array( 'type' => 'FlxCart' ), 'itemKey' => array( 'type' => 'String' ) ),
		'resolve' => function ( array $input ) {
			$line = FLXB_WC_Cart::build_line( $in );            // doğrulama Donate'te kalır
			if ( is_wp_error( $line ) ) { return $line; }       // INVALID_INPUT → payload errors
			// para birimi: FLXB_WC_Currency::set( … ) burada, add_item'dan önce (add_line'daki gibi)
			return FLXGQL_Cart::add_item( FLXB_WC_Products::product_id( (int) $line['type_id'] ), 1, array(), array( FLXB_WC_Cart::KEY => $line ) );
		},
	) );
} );
```

- `FLXGQL_Cart::add_item( int $product_id, int $qty = 1, array $variation = [], array $cart_item_data = [] ): array{cart, itemKey}|WP_Error`
  Satır Store API `cart/add-item` ile eklenir; `$cart_item_data` o tek çağrıda WooCommerce'in kendi `woocommerce_add_cart_item_data` filtresine (öncelik 5) eklenir. Yani satır, bugünkü `WC()->cart->add_to_cart( $pid, 1, 0, [], [ KEY => $line ] )` ile aynı biçimde saklanır; `from_session`, `prices`, `order_line` kancaları değişmeden çalışır. Donate'in `default_line` filtresi (öncelik 10) `KEY` varsa dokunmaz.
- Satırın türü: `add_filter( 'flx_gql_cart_item_kind', fn( $kind, $wc, $store ) => isset( $wc[ FLXB_WC_Cart::KEY ] ) ? 'donation' : $kind, 10, 3 );`
- Satıra tipli alan: `flx_gql_field( 'FlxCartItem', 'flxDonation', array( 'type' => 'FlxDonationLine', 'resolve' => fn( $item ) => … FLXGQL_Cart::wc_item( $item['key'] ) … ) );` (`wc_item()` bu istekte bir sepet rotası çalıştıktan sonra ham WC satırını verir).
- Satır ayrıntısı (`itemData`) WooCommerce'in `woocommerce_get_item_data` filtresinden gelir; Donate'in `item_data()`'sı zaten bunu yapıyor.
- Sabit adet: Store API `woocommerce_store_api_product_quantity_editable` filtresi (Donate bağış satırında false döndürmeli) → `quantityLimits.editable: false`.
- Diğer servis çağrıları: `FLXGQL_Cart::get()`, `update_item()`, `remove_item()`, `clear()`, `apply_coupon()`, `remove_coupon()`, `select_shipping_rate()`, `update_customer()`; hepsi `FlxCart` dizisi ya da `WP_Error`.

Donate için notlar:
- GraphQL isteği REST değil; `FLXB_WC_Currency::front()` /graphql'i ön yüz sayar (bağışçının para birimi uygulanır). Değişiklik gerekmez, testte doğrulanmalı.
- Bugünkü form POST'u (`wp_loaded`) ve klasik/blok checkout aynen kalır; GraphQL ek yol.
- Checkout'taki Donate kancaları (`woocommerce_store_api_checkout_update_order_from_request`: iletişim, şartlar, adres, ücret) Store API checkout'unda çalışmaya devam eder; köprü checkout'a girmez.

Commerce için: abonelik (`woocommerce_store_api_checkout_order_processed`) checkout'ta kalır; ürün ekleri `flx_gql_field( 'FlxCartItem', … )` ile, müşteri adresi `flxCartCustomerUpdate` ile.

## 7. Test

- `tools/tests/wp-graphql/flx-cart/http-test.sh` (run.sh içinde): ziyaretçi sepeti, token oturumu ve yalıtım, ekle/güncelle/sil/temizle, kupon (+hata alanı), adres, paket genişleme noktası (`flxTestCartNoteAdd`: cart item data, kind, sabit adet), sahte/eksik token, nonce'suz çerez (CSRF), önbellek başlıkları, kart alanı ve logda token yok. 31 kontrol.
- WooCommerce yoksa (cloud container indiremiyor) `fixtures/mu-fake-store-api.php` Store API yerine geçer: aynı rotalar, şekil, başlıklar ve WC kancaları. CI'daki `woocommerce` işi gerçek WooCommerce'i kurar (`WOOCOMMERCE_ZIP`, `FLX_CART_REQUIRE_WC=1`).
- Gerçek ödeme (EsnekPOS TEST1234) Ahmet'te; köprü ödemeye girmediği için sepetten checkout'a geçişi sitede bir kez denemek yeterli.
