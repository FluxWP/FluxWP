# Ödeme sağlayıcısı ekleme (Flux Forms + Flux Donate)

> **Ödeme sağlayıcı katmanı artık Flux Donate eklentisindedir.** Flux Donate etkinse Flux Forms ödemeleri
> ona devreder (`flxf_payments_delegate` filtresi): sağlayıcı kaydı, ayarlar, Stripe, webhook'lar ve
> **Bağışlar › Ödemeler** sayfası Flux Donate'tadır (`flx-donate/providers/`). Flux Donate kapalıyken Flux Forms
> kendi yerleşik katmanını (yalnızca Stripe) yedek olarak kullanır; ikisi de aynı adları, REST
> yollarını ve `{prefix}emsfb_pay_` tablosunu kullandığı için geçişte hiçbir şey taşınmaz.
>
> | | Flux Donate etkin | Flux Donate kapalı |
> |---|---|---|
> | Kod | `flx-donate/providers/` | `includes/flx/payments/` |
> | Ayarlar | Bağışlar › Ödemeler (boşsa Flux Forms ayarlarındaki Stripe anahtarları) | Flux Forms Ayarlar › Ödeme |
> | Webhook | `Emsfb/v1/forms/payment/webhook/{id}` ve eski `…/stripe/webhook` | aynı yollar |
> | Yinelenen bağış (Stripe aboneliği) | Flux Donate'ın düzenli bağışlarına kaydedilir | yalnızca ödeme kaydı |
>
> Aşağıdaki sözleşme iki durumda da aynıdır; sağlayıcınızı `FLXF_Payment_Provider_Base` ile yazın
> (Flux Donate'ta bu ad `FLXB_Payment_Provider_Base` sınıfının takma adıdır).

Ödemeler sağlayıcıdan bağımsızdır. Stripe ilk (ve şimdilik tek) sağlayıcıdır; yeni bir
sağlayıcı (iyzico, PayTR…) **tek bir sınıf + bir filtre** ile eklenir, çekirdeğe dokunulmaz.

Kod: `flx-donate/providers/` (Flux Donate etkinken) veya `includes/flx/payments/` (yedek): sözleşme, kayıt,
genel REST akışı, Ödemeler sayfası. Ön yüz katmanı: `flxf-pay.js` (`window.FLXFPay`, iki eklentide aynı).

## Akış

1. Ziyaretçi seçim yaptıkça `POST Emsfb/v1/forms/payment/quote` tutarı **sunucuda** hesaplar
   (form tanımından). "Şimdi Öde" düğmesi ancak geçerli bir teklif ve sağlayıcının
   `ctx.setReady(true)` çağrısıyla açılır.
2. Ödeme: `POST Emsfb/v1/forms/payment/start` → etkin sağlayıcının `create_payment( $amount, $currency, $meta )`
   yöntemi. **Tutar her zaman sunucu teklifidir**, tarayıcıdan gelen tutar kullanılmaz.
   (Eski `forms/payment/stripe/card/add` adresi çalışmaya devam eder; Stripe dışı bir sağlayıcı etkinse
   isteği `start`'a yönlendirir.)
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
- Ayarlar: Flux Donate etkinken `flxb_payments` seçeneğinde (`provider` + `fields`); `flxf_payment_setting()` önce
  oraya, sonra Flux Forms'un ayar satırına bakar. Yedek katmanda ayarlar Flux Forms'un ayar satırında düz anahtarlardır
  (`flxfPayProvider` = etkin sağlayıcı, Stripe: `stripePKey`, `stripeSKey`).
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
