# Flux Commerce — API

Önek `flxcm_` / `FLXCM_`, REST `flx-commerce/v1`, metin alanı `flx-commerce`.

## Fonksiyonlar
| | |
|---|---|
| `flxcm_setting( $key )`, `flxcm_settings()` | Ayarlar (option `flxcm_settings`) |
| `flxcm_paid_statuses()` | Ciroya sayılan durumlar (wc- öneki yok) |
| `flxcm_count_order( WC_Order )` | Sipariş istatistiklere girer mi |
| `FLXCM_Stats::summary( $from, $to )` | Y-m-d aralığı özeti: revenue, orders, aov, items, customers, series, products, categories, methods |
| `FLXCM_Bank::reference( WC_Order )` | Havale açıklaması |
| `FLXCM_Bank::mark_paid( WC_Order )` | Havale ödemesini onaylar |
| `FLXCM_Receipts::of( WC_Order )` | Yüklenen dekontlar |
| `FLXCM_Customers::recompute( $email )` | Müşteri satırını siparişlerden yeniden hesaplar |
| `FLXCM_Customers::mark_dirty( $email )` | Satırı hemen hesaplamaz; `flxcm_customers_dirty` içine yazar. `shutdown` (bağlantı kapanınca) ya da `flxcm_daily` hesaplar. `get` / `by_email` kirli satırı okurken kendisi hesaplar |

## Filtreler
| Filtre | |
|---|---|
| `flxcm_default_settings` | Varsayılan ayarlar |
| `flxcm_paid_statuses` | Sayılan durumlar |
| `flxcm_count_order( bool, WC_Order )` | Siparişi istatistikten çıkarma (Flux Donate bağışları varsayılan olarak çıkar) |
| `flxcm_vip_threshold` | "En çok harcayan" eşiği |
| `flxcm_bank_account` | Havale hesabı { holder, iban, bank } |
| `flxcm_free_shipping_threshold` | Ücretsiz kargo eşiği |
| `flxcm_feed_products`, `flxcm_feed_item_extra` | Feed ürünleri / ek XML |
| `flxcm_wa_bank_placed`, `flxcm_wa_bank_confirmed` | Müşteri WhatsApp metinleri |
| `flxcm_phone` | Telefon normalleştirme |

## Eylemler
`flxcm_loaded`, `flxcm_bank_placed( $order )`, `flxcm_receipt_uploaded( $order, $rec )`, `flxcm_bank_confirmed( $order )`, `flxcm_daily`.

## REST
`POST /wp-json/flx-commerce/v1/receipt` — `order`, `key` (sipariş anahtarı), `dekont` (dosya). Yetki sipariş anahtarıdır.

Uygulama (Flux App manifesti `flxcm`, 0.2.3). Okuma `view_woocommerce_reports` ya da `manage_woocommerce`. Onay `edit_shop_orders`.

| Yöntem | Yol | Ne |
|---|---|---|
| GET | `/summary?days=30` | Ciro, sipariş, ortalama sepet, yeni müşteri, dekont bekleyen, günlük `series` |
| GET | `/customers` | `s, segment, orderby, order, page, per_page` → `items, total, pages` |
| GET | `/customers/{id}` | Müşteri kartı |
| GET | `/receipts` | Havale ve ödeme bekleyen siparişler |
| GET | `/receipts/{id}` | Tek sipariş ve dekont dosyaları |
| POST | `/receipts/{id}/approve` | `FLXCM_Bank::mark_paid()` |

## Sipariş meta
`_flxcm_ref`, `_flxcm_code`, `_flxcm_receipts`, `_flxcm_confirmed`, `_flxcm_confirmed_by`, `_flxcm_wa_placed`, `_flxcm_wa_admin`.
Ürün meta `_flxcm_no_feed` = feed'e girmez.

## Paylaşılan kancalar
`flx_system_checks`, `flxa_page_heads`, `flxa_package_heads`, `flxa_menu_logos`, `flxa_default_groups` (Flux Admin), `flxu_package_icons` (FluxWP Panel), Flux Mail `flxi_send_message`.

## Abonelikler (0.4.0)

Aylık / yıllık yinelenen ödeme, WooCommerce üzerinde (ücretli WooCommerce Subscriptions yok). Faz 1: yenileme
Havale/EFT ya da ödeme bağlantısıyla (müşteri ödeme sayfasında açık olan her yöntemi seçebilir). Faz 2 (kartla
otomatik çekim) aynı olayları kullanacak; dinleyicilerin değişmesi gerekmez.

### Ürün
Basit üründe **Abonelik** kutusu + **Aylık fiyat** / **Yıllık fiyat** (boş = o dönem yok). Ürün meta:
`_flxcm_sub` (`yes`), `_flxcm_sub_month`, `_flxcm_sub_year`. Fatura adı/adresi/e-posta normal WooCommerce ödeme
akışından gelir ve her yenileme siparişine kopyalanır. Sepette abonelik varsa hesap açmak zorunludur.
Ürün sayfasında dönem seçimi `flxcm_period=month|year` (ör. `?add-to-cart=12&flxcm_period=year` ya da ürün
sayfası `?flxcm_period=year` ön seçimi).

Fiyatı başka yerden hesaplamak (ör. Flux Cloud'un yıllık indirim parametresi):
```php
add_filter( 'flxcm_subscription_prices', function ( $prices, $product ) {
	// $prices = [ 'month' => 1299.0, 'year' => 12990.0 ]
	return $prices;
}, 10, 2 );
```

### Durumlar
| Durum | Anlamı | `has_access` |
|---|---|---|
| `pending` | Sipariş verildi, ilk ödeme gelmedi | hayır |
| `active` | Ödendi, dönem sürüyor | evet |
| `past_due` | Dönem bitti, yenileme ödenmedi, ek süre (`sub_grace_days`) | evet |
| `cancelled` | Müşteri/yönetici yenilemeyi kapattı, `period_end`'e kadar geçerli | evet (bitişe kadar) |
| `expired` | Bitti | hayır |

Akış: sipariş → `pending` → ödeme → `active` (`period_end` = +1 ay/yıl, ay sonu kaymasız) → bitişten
`sub_renew_days` (7) gün önce yenileme siparişi + e-posta → ödenirse dönem eskisinin sonundan uzar → ödenmezse
`period_end`'de `past_due` → `sub_grace_days` (3) sonra `expired`. Saatlik cron `flxcm_subs_tick`; gerçek cron
yoksa sayfa isteklerinin sonunda saatte bir döner. Sistem kontrolü `flxcm.subscriptions`.

### Olaylar
Hepsi ilk argüman olarak `$sub` (aşağıdaki dizi) alır.

| Eylem | Argümanlar | Ne zaman |
|---|---|---|
| `flxcm_subscription_started` | `$sub, WC_Order $order` | İlk ödeme alındı (her yöntem; Havale onayı dahil) |
| `flxcm_subscription_renewed` | `$sub, WC_Order $order` | Yenileme siparişi ödendi; `$sub['period_end']` yeni bitiş |
| `flxcm_subscription_ended` | `$sub, string $reason` | Erişim kapanmalı. `$reason`: `cancelled` (iptal edilmişti, dönem bitti), `unpaid` (ek süre doldu), `refunded` (ilk sipariş iade), `admin` (yönetici "Şimdi bitir") |
| `flxcm_subscription_cancelled` | `$sub, string $by` | Yenileme kapatıldı (`customer`/`admin`); erişim `period_end`'e kadar sürer |
| `flxcm_subscription_resumed` | `$sub` | İptal geri alındı |
| `flxcm_subscription_past_due` | `$sub` | Dönem bitti, ödeme yok; ek süre başladı (erişim açık) |
| `flxcm_subscription_renewal_created` | `$sub, WC_Order $order` | Yenileme siparişi açıldı |
| `flxcm_subscription_status_changed` | `$sub, string $old, string $new` | Her durum değişikliği |

`$sub` dizisi (zamanlar Unix, UTC):
```
id, status, has_access, auto_renew, user_id, email, name, product_id, product_name, period (month|year),
amount, currency, payment_method, parent_order_id, last_order_id, renewal_order_id, renewals,
started_at, period_end, cancelled_at, ended_at, end_reason, meta (dizi), created_at
```

### Fonksiyonlar
| | |
|---|---|
| `flxcm_add_subscription_to_cart( $product_id, $period = '', array $meta = [] )` | Sepete abonelik ekler (aynı ürünün eski satırı çıkar), sonra `wc_get_checkout_url()`'e yönlendirin. `$meta` aboneliğin `meta`'sına taşınır (ör. `[ 'flx_cloud_account' => 12 ]`). Dönüş sepet anahtarı ya da `WP_Error` |
| `flxcm_get_subscription( $id )` | `$sub` ya da `null` |
| `flxcm_get_subscriptions( $args )` | `status`, `user_id`, `email`, `product_id`, `s`, `paged`, `per_page` |
| `flxcm_user_subscriptions( $user_id = 0, $product_id = 0 )` | Erişimi açık abonelikler |
| `flxcm_cancel_subscription( $id, $immediately = false )` | Dönem sonunda (ya da hemen) bitir |
| `flxcm_update_subscription_meta( $id, $key, $value )` | `meta`'ya yaz (`null` siler) |
| `FLXCM_Subscriptions::create_renewal( $id, $force = false )` | Yenileme siparişini şimdi aç |

### Filtreler
| Filtre | |
|---|---|
| `flxcm_subscription_prices( $prices, WC_Product )` | Dönem fiyatları |
| `flxcm_subscription_renewal_amount( $amount, $sub )` | Yenileme tutarı (varsayılan: abonelikteki kilitli tutar) |
| `flxcm_subscription_manual_methods` | Yenileme siparişine yazılan elle ödeme yöntemleri (`flxcm_bank`, `flxb_havale`, `bacs`) |
| `flxcm_subscription_send_mail( bool, $kind, $sub, $order )` | `renewal` / `ended` e-postasını kapat |

E-posta şablonları (Flux Mail › E-postalar): `flx-commerce/sub-renewal`, `flx-commerce/sub-ended`; Flux Mail
yoksa `wp_mail` düz metin. Tablo `wp_flxcm_subscriptions` (kaldırmada silinmez). Hesabım uç noktası `subscriptions`.
Sipariş meta: `_flxcm_subs` (ilk sipariş → abonelik id'leri), `_flxcm_renewal_of`, `_flxcm_renewal_for`.
Satır meta: `_flxcm_period`, `flxcm_period` (görünen), `_flxcm_sub_id`, `_flxcm_sub_meta`.

## Gizli ürün (0.4.1)
Ürün meta `_flxcm_hidden = yes` (ürün › Genel › Gizli ürün). Kaydedince katalog görünürlüğü `hidden` olur.
- Müşteri ürün sayfasını açarsa ayar `hidden_redirect` adresine (boş = ana sayfa) 302 ile gider; `edit_post` yetkisi olan görür.
- Site haritası, mağaza/arama, ürün akışı ve ilgili ürünlerde yok; sepet/sipariş satırında ürün bağlantısı boş.
- Satın alma: `?add-to-cart=ID&flxcm_period=month|year` ya da `flxcm_add_subscription_to_cart( $id, $period, $meta )` sonra `wc_get_checkout_url()`.
- Filtre `flxcm_hidden_product_redirect( string $url, int $product_id )`.
- `FLXCM_Hidden_Product::is_hidden( $product )`.

## Kartla otomatik yenileme (0.5.0)
Ödeme eklentisi sağlayıcı kaydeder:
```php
add_filter( 'flxcm_card_providers', function ( $providers ) {
	$providers['my_pos'] = array(
		'label'    => 'My POS',
		'gateways' => array( 'my_pos_gateway' ),          // Bu WC ödeme yöntemleriyle ödenen siparişlerde kart aranır.
		'card'     => function ( WC_Order $order ) {      // İlk/elle ödenen siparişten saklanacak kart (mühürlü token).
			return array( 'data' => array( 'token' => '…' ), 'label' => 'Visa •••• 4242' ); // ya da null
		},
		'charge'   => function ( WC_Order $renewal, array $data, array $sub ) {
			// Kartı çek. payment_complete() çağırma; Commerce çağırır.
			return array( 'result' => 'paid', 'txn' => 'abc' );           // paid | failed | uncertain
		},
	);
	return $providers;
} );
```
- Sağlayıcı kartı saklamak için siparişte abonelik olup olmadığına `flxcm_order_has_subscription( $order )` / `flxcm_cart_has_subscription()` ile bakar.
- `uncertain`: cevap gelmedi; deneme sayılmaz, bir saat sonra yeniden çağrılır (sağlayıcı önce durumu sorup iki kez çekmemeli).
- Tekrar deneme günleri `flxcm_card_retry_days` (varsayılan `[1, 1]`: dönem sonu, +1, +2 gün). Son başarısızlıkta `flxcm_subscription_charge_failed( $sub, $order, $error )` ve ödeme bağlantılı e-posta.
- Yenileme siparişi meta: `_flxcm_card_auto`, `_flxcm_charge_attempts`, `_flxcm_charge_next`, `_flxcm_charge_failed`.
- Abonelik dizisinde `card` (etiket) ve `auto_charge`; kart verisi `meta`'da görünmez.
- E-posta şablonları: `flx-commerce/sub-card-notice` (değişken `{kart}`), `flx-commerce/sub-card-failed`.
