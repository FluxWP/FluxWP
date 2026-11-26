<?php
/**
 * Stripe (yurt dışı kart, Apple Pay / Google Pay, Link; 135+ para birimi).
 *
 * Kart verisi sitemize hiç gelmez: kart alanları Stripe.js Payment Element'tir (Stripe'ın iFrame'i).
 *
 * İki yol:
 *  1. Ödeme sayfasında alanlar (elements modeli, kart yöntemi): tarayıcı Payment Element'ten bir
 *     PaymentMethod (pm_…) üretir, sipariş onu "flxpos_token" olarak gönderir. start() PaymentIntent'i
 *     sunucudan oluşturup onaylar (confirm=true). 3D Secure gerekirse Stripe'ın doğrulama adresine
 *     yönlendirir (next_action.redirect_to_url), dönüş finish() ile okunur.
 *  2. Belirteç yoksa (flxpos_charge, JS çalışmadı): start() PaymentIntent açar ve sitenin kendi
 *     ödeme sayfasını (köprüde Payment Element) basar; onay ve 3D Secure tarayıcıda (confirmPayment).
 *
 * Kesin sonuç: dönüşte PaymentIntent sunucudan okunur (dönüş verisine güvenilmez) ve webhook
 * (payment_intent.succeeded / processing / payment_failed) Stripe-Signature ile doğrulanır.
 * İade: /v1/refunds (kısmi olabilir). Kayıtlı kart (aylık bağış): setup_future_usage=off_session +
 * Customer; sonraki çekimler charge_saved() ile off-session.
 *
 * Test/canlı: anahtar öneki (pk_test_/sk_test_ ↔ pk_live_/sk_live_) hesabın test anahtarıyla aynı
 * olmalı; değilse ödeme başlamaz. Protokol başvurusu: Stripe API belgeleri (PaymentIntents, Webhooks).
 *
 * Kaynak: webhook imza doğrulaması, ondalıksız/üç ondalıklı para birimi listeleri ve kart hata kodu
 * mesajları WooCommerce Stripe Payment Gateway'den (woocommerce/woocommerce-gateway-stripe, GPLv3)
 * uyarlandı. Bu dosya GPL-3.0-or-later.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

class FLXPOS_Driver_Stripe extends FLXPOS_Driver {

	/** Sabitlenen API sürümü: yanıt biçimi Stripe hesabının varsayılan sürümüne göre değişmesin. */
	const API_VERSION = '2024-06-20';

	/** Webhook zaman toleransı (sn). */
	const TOLERANCE = 300;

	/** Kuruşsuz para birimleri (tutar olduğu gibi gönderilir). */
	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	/** Üç haneli para birimleri (son hane 0 olmalı). */
	const THREE_DECIMAL = array( 'BHD', 'JOD', 'KWD', 'OMR', 'TND' );

	public function needs_card() {
		return false;
	}

	/** Ön yüze gidecek yayımlanabilir anahtar. */
	public function publishable_key() {
		return $this->c( 'publishable_key' );
	}

	/** Tutar → Stripe'ın en küçük birimi. */
	public static function to_minor( $amount, $currency ) {
		$cur = strtoupper( (string) $currency );
		if ( in_array( $cur, self::ZERO_DECIMAL, true ) ) {
			return (int) round( (float) $amount );
		}
		if ( in_array( $cur, self::THREE_DECIMAL, true ) ) {
			return (int) ( round( (float) $amount * 100 ) * 10 );
		}
		return (int) round( (float) $amount * 100 );
	}

	/** En küçük birim → tutar. */
	public static function from_minor( $minor, $currency ) {
		$cur = strtoupper( (string) $currency );
		if ( in_array( $cur, self::ZERO_DECIMAL, true ) ) {
			return (float) $minor;
		}
		return round( (float) $minor / ( in_array( $cur, self::THREE_DECIMAL, true ) ? 1000 : 100 ), 2 );
	}

	/** Anahtarların test/canlı uyumu; sorun yoksa ''. */
	protected function key_problem() {
		$sk = $this->c( 'secret_key' );
		$pk = $this->c( 'publishable_key' );
		if ( '' === $sk || '' === $pk ) {
			return __( 'Stripe anahtarları eksik: yayımlanabilir anahtar ve gizli anahtar gerekir.', 'flx-pos' );
		}
		$sk_test = (bool) preg_match( '/^(sk|rk)_test_/', $sk );
		$pk_test = 0 === strpos( $pk, 'pk_test_' );
		if ( $sk_test !== $pk_test ) {
			return __( 'Stripe anahtarlarından biri test, biri canlı. İkisi aynı modda olmalı.', 'flx-pos' );
		}
		if ( $this->test && ! $sk_test ) {
			return __( 'Hesap test modunda ama Stripe anahtarları canlı. Test anahtarlarını (sk_test_… / pk_test_…) girin ya da test modunu kapatın.', 'flx-pos' );
		}
		if ( ! $this->test && $sk_test ) {
			return __( 'Hesap canlı modda ama Stripe anahtarları test anahtarı. Canlı anahtarları girin ya da test modunu açın.', 'flx-pos' );
		}
		return '';
	}

	/**
	 * Stripe API çağrısı (form gövdesi, iç içe anahtarlar a[b]=c).
	 *
	 * @return array|WP_Error Yanıt; Stripe hatası 'error' anahtarıyla döner.
	 */
	protected function api( $path, $body = null, $method = '', $idem = '' ) {
		$method  = '' !== $method ? $method : ( null === $body ? 'GET' : 'POST' );
		$headers = array(
			'Authorization'  => 'Bearer ' . $this->c( 'secret_key' ),
			'Stripe-Version' => self::API_VERSION,
		);
		if ( '' !== $idem ) {
			$headers['Idempotency-Key'] = $idem;
		}
		$url = rtrim( $this->url( 'api' ), '/' ) . $path;
		if ( 'GET' === $method && is_array( $body ) ) {
			$url  = $url . '?' . http_build_query( $body );
			$body = null;
		}
		$r = flxpos_http(
			$url,
			is_array( $body ) ? http_build_query( $body ) : $body,
			array(
				'method'  => $method,
				'headers' => $headers + array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
		return flxpos_json( $r, $this->name() );
	}

	/** Stripe hata kodlarının çevrilmiş karşılıkları (Stripe'ın kendi metni İngilizce). */
	public static function messages() {
		return (array) apply_filters(
			'flxpos_stripe_messages',
			array(
				'invalid_number'           => __( 'Kart numarası geçerli değil.', 'flx-pos' ),
				'incorrect_number'         => __( 'Kart numarası hatalı.', 'flx-pos' ),
				'incomplete_number'        => __( 'Kart numarası eksik.', 'flx-pos' ),
				'invalid_expiry_month'     => __( 'Son kullanma ayı geçersiz.', 'flx-pos' ),
				'invalid_expiry_year'      => __( 'Son kullanma yılı geçersiz.', 'flx-pos' ),
				'invalid_expiry_year_past' => __( 'Son kullanma yılı geçmişte.', 'flx-pos' ),
				'incomplete_expiry'        => __( 'Son kullanma tarihi eksik.', 'flx-pos' ),
				'expired_card'             => __( 'Kartın süresi dolmuş.', 'flx-pos' ),
				'invalid_cvc'              => __( 'Güvenlik kodu geçersiz.', 'flx-pos' ),
				'incorrect_cvc'            => __( 'Güvenlik kodu hatalı.', 'flx-pos' ),
				'incomplete_cvc'           => __( 'Güvenlik kodu eksik.', 'flx-pos' ),
				'incorrect_zip'            => __( 'Posta kodu doğrulanamadı.', 'flx-pos' ),
				'postal_code_invalid'      => __( 'Posta kodu geçersiz; düzeltip yeniden deneyin.', 'flx-pos' ),
				'card_declined'            => __( 'Kart reddedildi.', 'flx-pos' ),
				'generic_decline'          => __( 'Kart reddedildi.', 'flx-pos' ),
				'insufficient_funds'       => __( 'Kartın bakiyesi ya da limiti yetersiz.', 'flx-pos' ),
				'lost_card'                => __( 'Kart reddedildi.', 'flx-pos' ),
				'stolen_card'              => __( 'Kart reddedildi.', 'flx-pos' ),
				'do_not_honor'             => __( 'Kartı veren banka ödemeyi onaylamadı.', 'flx-pos' ),
				'card_velocity_exceeded'   => __( 'Kartın işlem sınırı aşıldı.', 'flx-pos' ),
				'authentication_required'  => __( 'Banka kart doğrulaması (3D Secure) istiyor.', 'flx-pos' ),
				'payment_intent_authentication_failure' => __( '3D Secure doğrulaması başarısız; yeniden deneyin ya da başka kart kullanın.', 'flx-pos' ),
				'processing_error'         => __( 'Kart işlenirken bir hata oluştu; yeniden deneyin.', 'flx-pos' ),
				'email_invalid'            => __( 'E-posta adresi geçersiz.', 'flx-pos' ),
				'invalid_request_error'    => __( 'Ödeme işlenemedi; yeniden deneyin ya da başka bir yöntem kullanın.', 'flx-pos' ),
			)
		);
	}

	/** Stripe hata mesajı (kart reddi ödeyene gösterilebilir). */
	protected function msg( array $r ) {
		$e   = (array) ( $r['error'] ?? $r['last_payment_error'] ?? array() );
		$map = self::messages();
		foreach ( array( $e['decline_code'] ?? '', $e['code'] ?? '', $e['type'] ?? '' ) as $k ) {
			if ( '' !== (string) $k && isset( $map[ $k ] ) ) {
				return $map[ $k ];
			}
		}
		return $this->reason( $e['message'] ?? '', $e['decline_code'] ?? ( $e['code'] ?? '' ) );
	}

	/** PaymentIntent'in ortak alanları. */
	protected function intent_body( FLXPOS_Payment $p ) {
		$c    = $p->customer;
		$meta = array(
			'flxpos_ref' => $p->ref,
			'flxpos_txn' => (string) (int) ( $p->txn['id'] ?? 0 ),
			'site'       => wp_parse_url( home_url(), PHP_URL_HOST ),
		);
		if ( $p->order ) {
			$meta['flxpos_order'] = (string) $p->order->get_id(); // order_id değil: resmi Stripe eklentisinin webhook'u bu anahtarla sipariş arar.
			$meta['order_number']  = (string) $p->order->get_order_number();
		}
		foreach ( (array) ( $p->txn['data']['meta'] ?? array() ) as $k => $v ) {
			if ( is_scalar( $v ) && count( $meta ) < 40 ) {
				$meta[ substr( 'm_' . sanitize_key( (string) $k ), 0, 40 ) ] = mb_substr( (string) $v, 0, 400 );
			}
		}
		$body = array(
			'amount'      => self::to_minor( $p->amount, $p->currency ),
			'currency'    => strtolower( $p->currency ),
			'description' => mb_substr( '' !== $p->description ? $p->description : $p->ref, 0, 900 ),
			'metadata'    => $meta,
		);
		$suffix = flxpos_ascii( (string) $this->c( 'descriptor' ), 22 );
		if ( '' !== $suffix ) {
			$body['statement_descriptor_suffix'] = $suffix;
		}
		if ( $this->saves( $p ) ) {
			$cus = $this->customer( $p );
			if ( is_wp_error( $cus ) ) {
				return $cus;
			}
			$body['customer']           = $cus;
			$body['setup_future_usage'] = 'off_session';
		}
		/** Filtre: PaymentIntent gövdesi (ör. statement_descriptor, payment_method_options). */
		return (array) apply_filters( 'flxpos_stripe_intent', $body, $p, $this->a );
	}

	/** Kart sonraki çekimler için saklanacak mı (aylık bağış)? */
	protected function saves( FLXPOS_Payment $p ) {
		$want = ! empty( $p->txn['data']['save'] ) || ( $p->order && 'yes' === $p->order->get_meta( '_flxpos_save' ) );
		return (bool) apply_filters( 'flxpos_stripe_save_card', $want, $p );
	}

	/** Stripe Customer: varsa kullanıcınınki, yoksa yeni. */
	protected function customer( FLXPOS_Payment $p ) {
		$uid  = (int) ( $p->customer['id'] ?? 0 );
		$key  = '_flxpos_stripe_cus_' . ( $this->test ? 'test' : 'live' ) . '_' . md5( $this->c( 'publishable_key' ) );
		$have = $uid ? (string) get_user_meta( $uid, $key, true ) : '';
		if ( '' !== $have ) {
			return $have;
		}
		$r = $this->api(
			'/v1/customers',
			array_filter(
				array(
					'email'    => (string) ( $p->customer['email'] ?? '' ),
					'name'     => (string) ( $p->customer['name'] ?? '' ),
					'phone'    => (string) ( $p->customer['phone'] ?? '' ),
					'metadata' => array_filter( array( 'wp_user' => $uid ? (string) $uid : '' ) ),
				)
			),
			'POST',
			'flxpos-cus-' . $p->ref
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( empty( $r['id'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		if ( $uid ) {
			update_user_meta( $uid, $key, (string) $r['id'] );
		}
		return (string) $r['id'];
	}

	public function start( FLXPOS_Payment $p ) {
		$bad = $this->key_problem();
		if ( '' !== $bad ) {
			return $this->err( $bad );
		}
		// Köprü yeniden açıldıysa (yenileme, geri tuşu) aynı PaymentIntent sürdürülür: ikinci çekim olmasın.
		$have = (string) ( $p->txn['data']['stripe_pi'] ?? '' );
		if ( '' !== $have ) {
			return $this->resume( $have, $p );
		}
		$body = $this->intent_body( $p );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$token = (string) ( $p->txn['data']['pm_in'] ?? '' );
		$idem  = 'flxpos-' . $p->ref;
		if ( preg_match( '/^pm_[A-Za-z0-9]+$/', $token ) ) {
			// 1. Ödeme sayfasında üretilen PaymentMethod: sunucudan onayla.
			$body += array(
				'payment_method'            => $token,
				'confirm'                   => 'true',
				'return_url'                => $p->return_url,
				'automatic_payment_methods' => array( 'enabled' => 'true' ),
				'expand'                    => array( 'latest_charge' ),
			);
			$r = $this->api( '/v1/payment_intents', $body, 'POST', $idem . '-c' );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$pi   = (array) ( isset( $r['error'] ) ? ( $r['error']['payment_intent'] ?? array() ) : $r );
			$data = array(
				'stripe_pi' => (string) ( $pi['id'] ?? '' ),
				'pm_in'     => '',
			);
			if ( isset( $r['error'] ) ) {
				return array(
					'result' => $this->fail( $this->msg( $r ), array( 'bank_ref' => $data['stripe_pi'] ) ),
					'data'   => $data,
				);
			}
			$next = (string) ( $pi['next_action']['redirect_to_url']['url'] ?? '' );
			if ( 'requires_action' === ( $pi['status'] ?? '' ) && '' !== $next ) {
				return array(
					'redirect' => $next,
					'data'     => $data,
				);
			}
			return array(
				'result' => $this->read_intent( $pi, $p->ref ),
				'data'   => $data,
			);
		}
		// 2. Sitenin kendi ödeme sayfası.
		$body += array( 'automatic_payment_methods' => array( 'enabled' => 'true' ) );
		$r     = $this->api( '/v1/payment_intents', $body, 'POST', $idem );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( empty( $r['client_secret'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		return array(
			'html' => FLXPOS_Stripe::page( $this, $p, (string) $r['client_secret'] ),
			'data' => array( 'stripe_pi' => (string) $r['id'] ),
		);
	}

	/** Var olan PaymentIntent'i sürdür. */
	protected function resume( $id, FLXPOS_Payment $p ) {
		$pi = $this->get_intent( $id );
		if ( is_wp_error( $pi ) ) {
			return $pi;
		}
		if ( isset( $pi['error'] ) ) {
			return $this->err( $this->msg( $pi ) );
		}
		$st   = (string) ( $pi['status'] ?? '' );
		$next = (string) ( $pi['next_action']['redirect_to_url']['url'] ?? '' );
		if ( 'requires_action' === $st && '' !== $next ) {
			return array( 'redirect' => $next );
		}
		if ( in_array( $st, array( 'requires_payment_method', 'requires_confirmation', 'requires_action' ), true ) && ! empty( $pi['client_secret'] ) ) {
			return array( 'html' => FLXPOS_Stripe::page( $this, $p, (string) $pi['client_secret'] ) );
		}
		return array( 'result' => $this->read_intent( $pi, $p->ref ) );
	}

	/** PaymentIntent → sonuç dizisi. */
	protected function read_intent( array $pi, $ref ) {
		$meta_ref = (string) ( $pi['metadata']['flxpos_ref'] ?? '' );
		if ( '' === $meta_ref || $meta_ref !== (string) $ref ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$id     = (string) ( $pi['id'] ?? '' );
		$cur    = (string) ( $pi['currency'] ?? '' );
		$charge = is_array( $pi['latest_charge'] ?? null ) ? $pi['latest_charge'] : array();
		$card   = (array) ( $charge['payment_method_details']['card'] ?? array() );
		$more   = array(
			'bank_ref'  => $id,
			'auth_code' => (string) ( $card['authorization_code'] ?? '' ),
			'card'      => ! empty( $card['last4'] ) ? '************' . $card['last4'] : '',
			'data'      => array_filter(
				array(
					'stripe_pi'       => $id,
					'stripe_charge'   => (string) ( is_array( $pi['latest_charge'] ?? null ) ? ( $charge['id'] ?? '' ) : ( $pi['latest_charge'] ?? '' ) ),
					'stripe_customer' => is_string( $pi['customer'] ?? null ) ? $pi['customer'] : '',
					'stripe_pm'       => ! empty( $pi['setup_future_usage'] ) && is_string( $pi['payment_method'] ?? null ) ? $pi['payment_method'] : '',
					'stripe_brand'    => (string) ( $card['brand'] ?? '' ),
				)
			),
		);
		$st = (string) ( $pi['status'] ?? '' );
		if ( 'succeeded' === $st ) {
			return $this->ok( $more + array( 'amount' => self::from_minor( (int) ( $pi['amount_received'] ?? $pi['amount'] ?? 0 ), $cur ) ) );
		}
		if ( in_array( $st, array( 'processing', 'requires_capture' ), true ) ) {
			return $this->pending( __( 'Ödeme Stripe\'ta işleniyor.', 'flx-pos' ), $more );
		}
		if ( in_array( $st, array( 'requires_action', 'requires_confirmation' ), true ) ) {
			return $this->pending( __( 'Ödeme doğrulaması bekleniyor.', 'flx-pos' ), $more );
		}
		if ( 'canceled' === $st ) {
			return $this->fail( __( 'Ödeme iptal edildi.', 'flx-pos' ), $more );
		}
		// requires_payment_method: kart reddedildi ya da doğrulama başarısız.
		return $this->fail( $this->msg( $pi ), $more );
	}

	protected function get_intent( $id ) {
		if ( ! preg_match( '/^pi_[A-Za-z0-9]+$/', (string) $id ) ) {
			return $this->err( 'bad id' );
		}
		return $this->api( '/v1/payment_intents/' . rawurlencode( (string) $id ), array( 'expand' => array( 'latest_charge' ) ), 'GET' );
	}

	public function finish( array $txn, array $req ) {
		$id = (string) ( $txn['data']['stripe_pi'] ?? '' );
		if ( '' === $id ) {
			return $this->fail( __( 'Banka dönüşü bu ödemeye ait değil.', 'flx-pos' ) );
		}
		$pi = $this->get_intent( $id );
		if ( is_wp_error( $pi ) ) {
			return $this->pending( $pi->get_error_message() );
		}
		if ( isset( $pi['error'] ) ) {
			return $this->pending( $this->msg( $pi ) );
		}
		return $this->read_intent( $pi, $txn['ref'] );
	}

	/** Stripe-Signature doğrulaması (t=…,v1=…). */
	public static function verify_signature( $payload, $header, $secret, $now = null ) {
		$header = (string) $header;
		if ( '' === (string) $secret || ! preg_match( '/(?:^|,)\s*t=(\d+)/', $header, $t ) || ! preg_match_all( '/(?:^|,)\s*v1=([0-9a-f]+)/', $header, $v ) ) {
			return false;
		}
		$now = null === $now ? time() : (int) $now;
		if ( abs( $now - (int) $t[1] ) > self::TOLERANCE ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t[1] . '.' . $payload, (string) $secret );
		foreach ( $v[1] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	public function notify( array $req ) {
		$raw = (string) ( $req['raw'] ?? '' );
		$sig = (string) ( $req['headers']['stripe-signature'] ?? '' );
		if ( ! self::verify_signature( $raw, $sig, $this->c( 'webhook_secret' ) ) ) {
			return $this->err( 'bad signature' );
		}
		$ev   = json_decode( $raw, true );
		$type = (string) ( $ev['type'] ?? '' );
		$obj  = (array) ( $ev['data']['object'] ?? array() );
		if ( 0 !== strpos( $type, 'payment_intent.' ) || empty( $obj['id'] ) ) {
			return array(
				'ref'    => '',
				'result' => array(),
				'reply'  => 'ignored',
			);
		}
		$ref = (string) ( $obj['metadata']['flxpos_ref'] ?? '' );
		if ( 'payment_intent.payment_failed' === $type ) {
			// Kart reddi: ödeyen aynı sayfada başka kartla yeniden deneyebilir; işlemi kapatma.
			return array(
				'ref'    => $ref,
				'result' => $this->pending( $this->msg( $obj ), array( 'bank_ref' => (string) $obj['id'] ) ),
				'reply'  => 'OK',
			);
		}
		// Olayın kendi kopyası yerine güncel durum Stripe'tan okunur.
		$pi = $this->get_intent( (string) $obj['id'] );
		if ( is_wp_error( $pi ) ) {
			return $pi;
		}
		if ( isset( $pi['error'] ) ) {
			return $this->err( $this->msg( $pi ) );
		}
		$ref = (string) ( $pi['metadata']['flxpos_ref'] ?? $ref );
		return array(
			'ref'    => $ref,
			'result' => $this->read_intent( $pi, $ref ),
			'reply'  => 'OK',
		);
	}

	public function status( array $txn ) {
		$id = (string) ( $txn['data']['stripe_pi'] ?? $txn['bank_ref'] );
		$pi = $this->get_intent( $id );
		if ( is_wp_error( $pi ) ) {
			return $pi;
		}
		return isset( $pi['error'] ) ? $this->err( $this->msg( $pi ) ) : $this->read_intent( $pi, $txn['ref'] );
	}

	public function refund( array $txn, $amount ) {
		$pi = (string) ( $txn['data']['stripe_pi'] ?? $txn['bank_ref'] );
		$r  = $this->api(
			'/v1/refunds',
			array(
				'payment_intent' => $pi,
				'amount'         => self::to_minor( $amount, $txn['currency'] ),
				'metadata'       => array( 'flxpos_ref' => (string) $txn['ref'] ),
			),
			'POST',
			'flxpos-rf-' . $txn['ref'] . '-' . self::to_minor( (float) $txn['refunded'], $txn['currency'] ) . '-' . self::to_minor( $amount, $txn['currency'] )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = isset( $r['id'] ) && in_array( (string) ( $r['status'] ?? '' ), array( 'succeeded', 'pending' ), true );
		return array(
			'ok'       => $ok,
			'message'  => $ok ? '' : $this->msg( $r ),
			'bank_ref' => (string) ( $r['id'] ?? '' ),
		);
	}

	/**
	 * Kayıtlı kartla çekim (ödeyen sitede değil; aylık bağış). 3D Secure isterse başarısız döner
	 * (data.needs_auth): ödeyene bağlantı gönderip yeni ödeme alınır.
	 */
	public function charge_saved( FLXPOS_Payment $p, $customer, $payment_method ) {
		$bad = $this->key_problem();
		if ( '' !== $bad ) {
			return $this->fail( $bad );
		}
		$body = $this->intent_body( $p );
		if ( is_wp_error( $body ) ) {
			return $this->fail( $body->get_error_message() );
		}
		unset( $body['setup_future_usage'] );
		$body = array_merge(
			$body,
			array(
				'customer'       => (string) $customer,
				'payment_method' => (string) $payment_method,
				'confirm'        => 'true',
				'off_session'    => 'true',
				'expand'         => array( 'latest_charge' ),
			)
		);
		$r = $this->api( '/v1/payment_intents', $body, 'POST', 'flxpos-' . $p->ref . '-o' );
		if ( is_wp_error( $r ) ) {
			return $this->pending( $r->get_error_message() );
		}
		if ( isset( $r['error'] ) ) {
			$pi = (array) ( $r['error']['payment_intent'] ?? array() );
			return $this->fail(
				$this->msg( $r ),
				array(
					'bank_ref' => (string) ( $pi['id'] ?? '' ),
					'data'     => array_filter(
						array(
							'stripe_pi'  => (string) ( $pi['id'] ?? '' ),
							'needs_auth' => 'authentication_required' === ( $r['error']['code'] ?? '' ) ? 1 : 0,
						)
					),
				)
			);
		}
		return $this->read_intent( $r, $p->ref );
	}

	public function test_connection() {
		$bad = $this->key_problem();
		if ( '' !== $bad ) {
			return $this->err( $bad );
		}
		$r = $this->api( '/v1/balance', null, 'GET' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( isset( $r['error'] ) ) {
			return $this->err( $this->msg( $r ) );
		}
		// Apple Pay / Google Pay için alan adı kaydı (zaten kayıtlıysa Stripe hata döner; önemsiz).
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' !== $host && ! in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			$this->api( '/v1/payment_method_domains', array( 'domain_name' => $host ) );
		}
		return true;
	}
}
