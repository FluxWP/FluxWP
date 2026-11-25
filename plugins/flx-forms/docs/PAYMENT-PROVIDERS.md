# Ödeme sağlayıcısı (Flux Forms + WooCommerce)

> **Flux Forms'un kendi ödeme altyapısı yoktur; ödeme formları WooCommerce ödeme sayfasında ödenir** (2.9.0).
> Tek sağlayıcı `woocommerce` (`includes/flx/payments/providers/class-flxf-payment-wc.php`): WooCommerce etkinken kaydolur;
> form tutarıyla bekleyen bir sipariş açar, ödeme WooCommerce'te açık herhangi bir yöntemle alınır (Flux POS kart, havale…).
> Form para birimi mağaza para birimi olmalı. Ödeme almayan formlar WooCommerce istemez. WooCommerce yoksa ödeme kutusu
> "online ödeme kullanılamıyor" der, Sistem kontrolü hata gösterir.
>
> Flux Donate etkinse ödeme katmanını Donate tanımlar (`flxf_payment_*` fonksiyonları onda), Forms'unki yüklenmez.
>
> Yeni banka/sanal POS Forms'a değil, WooCommerce gateway'i olarak (Flux POS) eklenir.

Kod: `includes/flx/payments/` (sözleşme, çekirdek `class-flxf-payment-core.php`, kayıt `FLXF_Payment_Records`, genel REST
akışı, `compat.php`). Ön yüz: `flxf-pay.js` (`window.FLXFPay`) + `flxf-pay-wc.js` (ödeme sayfası penceresi, dönüş `?flxf_pay_return=1`).

## Akış

1. Ziyaretçi seçim yaptıkça `POST Emsfb/v1/forms/payment/quote` tutarı **sunucuda** hesaplar
   (form tanımından). "Şimdi Öde" düğmesi ancak geçerli bir teklif ve sağlayıcının
   `ctx.setReady(true)` çağrısıyla açılır.
2. Ödeme: `POST Emsfb/v1/forms/payment/start` → etkin sağlayıcının `create_payment( $amount, $currency, $meta )`
   yöntemi. **Tutar her zaman sunucu teklifidir**, tarayıcıdan gelen tutar kullanılmaz.
3. Sonuç `FLXF_Payment_Result` olarak döner: `completed`, `pending`, `requires_action`, `redirect`, `failed`.
   Çekirdek bekleyen form kaydını (onay kodu) ve `{$wpdb->prefix}emsfb_pay_` tablosundaki ödeme
   kaydını (`gateway` = sağlayıcı kimliği) oluşturur; yanıt görüntüleyici ve Ödemeler sayfası bunu okur.
4. Form gönderilirken (`forms/message/add`) ödeme, sağlayıcının `verify( $record )` yöntemiyle doğrulanır;
   `completed` değilse gönderim reddedilir.
5. Webhook: `POST Emsfb/v1/forms/payment/webhook/{id}` → `handle_webhook( $request )` (imzayı sağlayıcı doğrular).
   Yönlendirmeli ödemelerde dönüşte `POST Emsfb/v1/forms/payment/confirm` (`{track}`) kullanılır.

## En küçük örnek

```php
<?php
/* Plugin Name: Flux Forms – Örnek Sağlayıcı */

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'FLXF_Payment_Provider_Base' ) ) {
		return; // Flux Forms yüklü değil
	}

	class Ornek_Odeme extends FLXF_Payment_Provider_Base {
		public function id()    { return 'ornek'; }
		public function label() { return 'Örnek Ödeme'; }

		public function supports( $currency, $method ) {
			return 'TRY' === $currency && 'charge' === $method; // tek seferlik TL ödemesi
		}

		/* Ayarlar › Ödemeler sekmesinde kendiliğinden görünür; anahtarlara sağlayıcı öneki verin. */
		public function settings_fields() {
			return array(
				array( 'key' => 'ornekApiKey', 'label' => __( 'API key', 'my-domain' ), 'type' => 'text', 'required' => true ),
				array( 'key' => 'ornekSecret', 'label' => __( 'Secret key', 'my-domain' ), 'type' => 'password', 'required' => true ),
			);
		}

		public function front_assets() {
			return array( 'scripts' => array(
				'ornek-odeme' => array( 'src' => plugins_url( 'ornek.js', __FILE__ ), 'ver' => '1.0' ),
			) );
		}

		public function create_payment( $amount, $currency, array $meta ) {
			// $this->setting( 'ornekApiKey' ) ile ayarı okuyun, sağlayıcı API'sini çağırın…
			$resp = wp_remote_post( 'https://api.ornek.test/pay', array( 'body' => array(
				'amount' => $amount, 'currency' => $currency, 'order' => $meta['reference'],
				'token'  => $meta['params']['token'] ?? '',
			) ) );
			if ( is_wp_error( $resp ) ) {
				return FLXF_Payment_Result::failed( $resp->get_error_message() );
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			return ! empty( $body['paid'] )
				? FLXF_Payment_Result::completed( $body['id'] )
				: FLXF_Payment_Result::failed( $body['message'] ?? 'Ödeme alınamadı.' );
			// Barındırılan ödeme sayfası için: return FLXF_Payment_Result::redirect( $ref, $url );
		}

		/* Tamamlanmamış kayıtları sağlayıcıya sorun (varsayılan: yalnızca 'completed' kayıt geçer). */
		public function verify( $record, array $params = array() ) {
			return parent::verify( $record, $params );
		}
	}

	add_filter( 'flxf_payment_providers', function ( $providers ) {
		$providers['ornek'] = 'Ornek_Odeme'; // sınıf adı veya nesne
		return $providers;
	} );
} );
```

`ornek.js` (ön yüz; `flxf-pay` otomatik olarak bağımlılık eklenir):

```js
FLXFPay.register('ornek', {
  mount: function (ctx) {
    // ctx.section: kart alanlarının kutusu (boş verilir), ctx.btn: Öde düğmesi
    ctx.section.innerHTML = '<div id="ornek-kart"></div>';
    ctx.setReady(true); // alanlar tamam olunca çağırın
  },
  pay: function (ctx) {
    // Tarayıcıdan gelen ekstra veri (ör. kart jetonu) $meta['params'] içine düşer; tutar gönderilmez.
    return FLXFPay.start(ctx, { token: 'tok_...' }).then(FLXFPay.result);
  }
});
```

Ardından sağlayıcıyı seçin: Flux Donate etkinse **Bağışlar › Ödemeler** sayfasında sağlayıcının kartında
"Ödemeleri bu sağlayıcıyla al" kutusunu işaretleyip kaydedin; değilse **Ayarlar › Ödemeler › Etkin ödeme
sağlayıcısı** listesinden seçin.

## Notlar

- Sözleşme: `flx-donate/providers/contract.php` (yedek: `includes/flx/payments/interface-flxf-payment-provider.php`)
  (`id, label, supports, settings_fields, is_configured, front_assets, create_payment, verify, handle_webhook, refund`).
- Ayarlar: Flux Forms'ta ödeme ayarı yok; ödeme yöntemleri WooCommerce › Ayarlar › Ödemeler'de. Flux Donate etkinken
  Donate'in kendi ayarları (`flxb_payments`) geçerlidir.
- Bağış formu bir bağış türüne bağlıysa tutarı türün fiyatları belirler (filtre `flxf_payment_server_amount`,
  Flux Donate). Sağlayıcıya gelen `$amount` her zaman bu sunucu tutarıdır.
- `window.flxfPayCfg.configured` false ise (sağlayıcı ayarlanmamış) ya da sağlayıcı yüklenemezse ödeme kutusunda
  yerelleştirilmiş "Online ödeme şu anda kullanılamıyor." mesajı çıkar ve Öde düğmesi kapalı kalır. Kendi
  sağlayıcınız başlayamadığında `FLXFPay.unavailable( formId )` çağırın; ham hata kodu göstermeyin.
- `flxf_payment_server_amount` filtresi `error` (metin) döndürürse tutar sorgusu bu mesajla reddedilir.
- Fiyatların tek kaynağı Flux Donate'tır: `flxf_sapling_unit_price( $price, $form_id )` formun bağlı olduğu türün
  birim fiyatını, `flxf_automation_unit_price( $unit, $ctx )` sertifikadaki "tutar ÷ birim fiyat" hesabı için
  bağışçının ödediği birim fiyatı döner. Flux Donate yokken varsayılan 150 ₺ ve akışta kayıtlı birim fiyat kullanılır.
- İsteğe bağlı: `supports_refunds()` true dönen sağlayıcılar için Flux Donate bağış ayrıntısında "İade et" düğmesi,
  `cancel_subscription( $id )` yöntemi olanlar için düzenli bağışlarda "İptal et" düğmesi gösterir.
- Kart kutusu EFB'de `stripe` tipli öğedir; tüm sağlayıcılar aynı kutuyu kullanır (`getway` = `stripe` kalır).
- Yalnızca geliştirme için bir test sağlayıcısı vardır (para çekmez), varsayılan olarak **kapalıdır**:
  `add_filter( 'flxf_payments_enable_test_provider', '__return_true' );`
  veya `define( 'FLXF_PAYMENTS_TEST_PROVIDER', true );`. Canlı sitede açmayın.
- Tüm arayüz metinleri İngilizce kaynak + çeviri (`__()`, metin alanı sizin eklentinizin).
