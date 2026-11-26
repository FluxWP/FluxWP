<?php
/**
 * Stripe sürücüsü birim testi (WordPress'siz; Stripe API'si sahte yanıtlarla).
 * Çalıştır: php plugins/flx-pos/tests/stripe-driver-test.php
 *
 * @package FLX_POS
 */

// phpcs:ignoreFile
define( 'ABSPATH', __DIR__ . '/' );
define( 'FLXPOS_VERSION', 'test' );
define( 'FLXPOS_DIR', dirname( __DIR__ ) . '/' );
define( 'FLXPOS_URL', 'https://example.test/wp-content/plugins/flx-pos/' );

$GLOBALS['flxt_filters'] = array();
$GLOBALS['flxt_calls']   = array();
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function add_filter( $h, $cb ) { $GLOBALS['flxt_filters'][ $h ][] = $cb; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['flxt_filters'][ $h ] ?? array() as $cb ) { $v = $cb( $v, ...$a ); } return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_request() { throw new Exception( 'no network in tests' ); }
function get_user_meta() { return ''; }
function update_user_meta() {}
function determine_locale() { return 'tr_TR'; }
function remove_accents( $s ) { return $s; }
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } }
class FLXPOS_Payment { public $txn = array(); public $order; public $ref; public $amount; public $currency; public $customer = array(); public $return_url = 'https://example.test/?flxpos=return'; public $description = ''; public $lang = 'tr'; }
class FLXPOS_Stripe { static function page( $d, $p, $cs ) { return 'PAGE:' . $cs; } }

require FLXPOS_DIR . 'includes/helpers.php';
require FLXPOS_DIR . 'includes/drivers/class-flxpos-driver.php';
require FLXPOS_DIR . 'includes/drivers/class-flxpos-driver-stripe.php';

/** Sahte Stripe: path → yanıt. */
$GLOBALS['flxt_routes'] = array();
add_filter(
	'flxpos_http_pre',
	static function ( $pre, $url, $req ) {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		parse_str( (string) $req['body'], $body );
		$GLOBALS['flxt_calls'][] = array( 'method' => $req['method'], 'path' => $path, 'body' => $body, 'headers' => $req['headers'] );
		$r = $GLOBALS['flxt_routes'][ $req['method'] . ' ' . $path ] ?? array( 'error' => array( 'message' => 'no route ' . $path ) );
		$r = is_callable( $r ) ? $r( $body ) : $r;
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $r ), 'headers' => array() );
	}
);
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_headers( $r ) { return $r['headers']; }

$fail = 0;
function ok( $cond, $name ) { global $fail; echo ( $cond ? "  ok   " : "  FAIL " ) . $name . "\n"; if ( ! $cond ) { $fail++; } }

$provider = array( 'id' => 'stripe', 'name' => 'Stripe', 'driver' => 'stripe', 'modes' => array( 'elements' => '' ), 'endpoints' => array( 'live' => array( 'api' => 'https://api.stripe.com' ) ), 'opts' => array() );
$acc      = array( 'test' => 1, 'mode' => 'elements', 'creds' => array( 'publishable_key' => 'pk_test_abc', 'secret_key' => 'sk_test_xyz', 'webhook_secret' => 'whsec_123' ) );
$d        = new FLXPOS_Driver_Stripe( $provider, $acc );

echo "Tutar birimleri\n";
ok( 15000 === FLXPOS_Driver_Stripe::to_minor( 150, 'usd' ), 'USD 150 → 15000' );
ok( 150 === FLXPOS_Driver_Stripe::to_minor( 150, 'JPY' ), 'JPY ondalıksız' );
ok( 12350 === FLXPOS_Driver_Stripe::to_minor( 12.345, 'KWD' ), 'KWD üç hane, son hane 0' );
ok( 150.0 === FLXPOS_Driver_Stripe::from_minor( 15000, 'eur' ), 'EUR geri' );
ok( 12.34 === FLXPOS_Driver_Stripe::from_minor( 12340, 'KWD' ), 'KWD geri' );

echo "Anahtar modu\n";
$live = new FLXPOS_Driver_Stripe( $provider, array( 'test' => 1 ) + array( 'creds' => array( 'publishable_key' => 'pk_live_a', 'secret_key' => 'sk_live_b' ) ) );
ok( is_wp_error( $live->start( new FLXPOS_Payment() ) ), 'test hesabında canlı anahtar reddedilir' );
ok( 0 === count( $GLOBALS['flxt_calls'] ), 've Stripe\'a istek gitmez' );

function pay( $data = array() ) {
	$p           = new FLXPOS_Payment();
	$p->ref      = 'FX12TABC';
	$p->amount   = 150;
	$p->currency = 'USD';
	$p->txn      = array( 'id' => 7, 'test' => 1, 'data' => $data );
	return $p;
}

echo "Belirteçle ödeme (3DS'siz)\n";
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = static fn( $b ) => array( 'id' => 'pi_1', 'status' => 'succeeded', 'amount' => (int) $b['amount'], 'amount_received' => (int) $b['amount'], 'currency' => 'usd', 'metadata' => $b['metadata'], 'latest_charge' => array( 'id' => 'ch_1', 'payment_method_details' => array( 'card' => array( 'last4' => '4242', 'brand' => 'visa' ) ) ) );
$r    = $d->start( pay( array( 'pm_in' => 'pm_card123' ) ) );
$call = end( $GLOBALS['flxt_calls'] );
ok( 'paid' === ( $r['result']['status'] ?? '' ), 'sonuç paid' );
ok( 150.0 === $r['result']['amount'], 'çekilen tutar okunur' );
ok( '************4242' === $r['result']['card'], 'kart son 4' );
ok( 'true' === $call['body']['confirm'] && 'pm_card123' === $call['body']['payment_method'], 'sunucudan onay, pm gönderildi' );
ok( '15000' === $call['body']['amount'] && 'usd' === $call['body']['currency'], 'tutar en küçük birimde' );
ok( 'FX12TABC' === $call['body']['metadata']['flxpos_ref'], 'metadata.flxpos_ref' );
ok( 0 === strpos( $call['headers']['Idempotency-Key'], 'flxpos-FX12TABC' ), 'idempotency anahtarı' );
ok( FLXPOS_Driver_Stripe::API_VERSION === $call['headers']['Stripe-Version'], 'API sürümü sabit' );
ok( 'Bearer sk_test_xyz' === $call['headers']['Authorization'], 'gizli anahtar başlıkta' );
ok( '' === $r['data']['pm_in'] && 'pi_1' === $r['data']['stripe_pi'], 'pm silinir, pi saklanır' );

echo "3D Secure yönlendirmesi\n";
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = static fn( $b ) => array( 'id' => 'pi_2', 'status' => 'requires_action', 'metadata' => $b['metadata'], 'next_action' => array( 'type' => 'redirect_to_url', 'redirect_to_url' => array( 'url' => 'https://hooks.stripe.com/3ds/x' ) ) );
$r = $d->start( pay( array( 'pm_in' => 'pm_card3ds' ) ) );
ok( 'https://hooks.stripe.com/3ds/x' === ( $r['redirect'] ?? '' ), 'Stripe doğrulama adresine gider' );
$GLOBALS['flxt_routes']['GET /v1/payment_intents/pi_2'] = array( 'id' => 'pi_2', 'status' => 'succeeded', 'amount_received' => 15000, 'currency' => 'usd', 'metadata' => array( 'flxpos_ref' => 'FX12TABC' ) );
$f = $d->finish( array( 'ref' => 'FX12TABC', 'data' => array( 'stripe_pi' => 'pi_2' ) ), array() );
ok( 'paid' === $f['status'], 'dönüşte PaymentIntent sunucudan okunur: paid' );
$f = $d->finish( array( 'ref' => 'BASKA', 'data' => array( 'stripe_pi' => 'pi_2' ) ), array() );
ok( 'failed' === $f['status'], 'başka işlemin PaymentIntent\'i reddedilir' );
$GLOBALS['flxt_routes']['GET /v1/payment_intents/pi_2'] = array( 'id' => 'pi_2', 'status' => 'requires_payment_method', 'currency' => 'usd', 'metadata' => array( 'flxpos_ref' => 'FX12TABC' ), 'last_payment_error' => array( 'code' => 'payment_intent_authentication_failure', 'message' => 'auth failed' ) );
$f = $d->finish( array( 'ref' => 'FX12TABC', 'data' => array( 'stripe_pi' => 'pi_2' ) ), array() );
ok( 'failed' === $f['status'] && false !== strpos( $f['message'], '3D Secure' ), '3DS başarısız: Türkçe mesaj' );

echo "Kart reddi\n";
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = array( 'error' => array( 'type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Your card has insufficient funds.', 'payment_intent' => array( 'id' => 'pi_3' ) ) );
$r = $d->start( pay( array( 'pm_in' => 'pm_x' ) ) );
ok( 'failed' === $r['result']['status'] && 'Kartın bakiyesi ya da limiti yetersiz.' === $r['result']['message'], 'ret mesajı çevrildi' );

echo "Belirteçsiz: sitenin ödeme sayfası\n";
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = static fn( $b ) => array( 'id' => 'pi_4', 'client_secret' => 'pi_4_secret_z', 'status' => 'requires_payment_method', 'metadata' => $b['metadata'] );
$r    = $d->start( pay() );
$call = end( $GLOBALS['flxt_calls'] );
ok( 'PAGE:pi_4_secret_z' === ( $r['html'] ?? '' ), 'Payment Element sayfası' );
ok( empty( $call['body']['confirm'] ) && 'true' === $call['body']['automatic_payment_methods']['enabled'], 'onaysız intent, otomatik yöntemler' );

echo "Yeniden açılan köprü aynı intent'i sürdürür\n";
$n = count( $GLOBALS['flxt_calls'] );
$GLOBALS['flxt_routes']['GET /v1/payment_intents/pi_4'] = array( 'id' => 'pi_4', 'client_secret' => 'pi_4_secret_z', 'status' => 'requires_payment_method', 'metadata' => array( 'flxpos_ref' => 'FX12TABC' ) );
$r = $d->start( pay( array( 'stripe_pi' => 'pi_4' ) ) );
ok( 'PAGE:pi_4_secret_z' === ( $r['html'] ?? '' ), 'aynı client secret' );
$new = array_slice( $GLOBALS['flxt_calls'], $n );
ok( 1 === count( $new ) && 'GET' === $new[0]['method'], 'yeni PaymentIntent açılmaz' );

echo "Webhook\n";
$GLOBALS['flxt_routes']['GET /v1/payment_intents/pi_5'] = array( 'id' => 'pi_5', 'status' => 'succeeded', 'amount_received' => 15000, 'currency' => 'usd', 'metadata' => array( 'flxpos_ref' => 'FX99' ) );
$payload = json_encode( array( 'type' => 'payment_intent.succeeded', 'data' => array( 'object' => array( 'id' => 'pi_5', 'metadata' => array( 'flxpos_ref' => 'FX99' ) ) ) ) );
$t       = time();
$sig     = 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $payload, 'whsec_123' );
$n       = $d->notify( array( 'raw' => $payload, 'headers' => array( 'stripe-signature' => $sig ) ) );
ok( ! is_wp_error( $n ) && 'FX99' === $n['ref'] && 'paid' === $n['result']['status'], 'imzalı olay: paid' );
ok( is_wp_error( $d->notify( array( 'raw' => $payload . ' ', 'headers' => array( 'stripe-signature' => $sig ) ) ) ), 'değişmiş gövde reddedilir' );
ok( is_wp_error( $d->notify( array( 'raw' => $payload, 'headers' => array() ) ) ), 'imzasız reddedilir' );
$old = 't=' . ( $t - 3600 ) . ',v1=' . hash_hmac( 'sha256', ( $t - 3600 ) . '.' . $payload, 'whsec_123' );
ok( is_wp_error( $d->notify( array( 'raw' => $payload, 'headers' => array( 'stripe-signature' => $old ) ) ) ), 'eski zaman damgası reddedilir' );
ok( FLXPOS_Driver_Stripe::verify_signature( 'x', 't=' . $t . ',v1=bad,v1=' . hash_hmac( 'sha256', $t . '.x', 's' ), 's' ), 'birden çok v1 imzası' );
$fp = json_encode( array( 'type' => 'payment_intent.payment_failed', 'data' => array( 'object' => array( 'id' => 'pi_6', 'metadata' => array( 'flxpos_ref' => 'FX98' ), 'last_payment_error' => array( 'code' => 'card_declined' ) ) ) ) );
$n  = $d->notify( array( 'raw' => $fp, 'headers' => array( 'stripe-signature' => 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $fp, 'whsec_123' ) ) ) );
ok( 'pending' === $n['result']['status'], 'payment_failed işlemi kapatmaz (yeniden denenebilir)' );
$cp = json_encode( array( 'type' => 'charge.dispute.created', 'data' => array( 'object' => array( 'id' => 'dp_1' ) ) ) );
$n  = $d->notify( array( 'raw' => $cp, 'headers' => array( 'stripe-signature' => 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $cp, 'whsec_123' ) ) ) );
ok( 'ignored' === $n['reply'], 'diğer olaylar yok sayılır' );

echo "İade\n";
$GLOBALS['flxt_routes']['POST /v1/refunds'] = static fn( $b ) => array( 'id' => 're_1', 'status' => 'succeeded', 'amount' => (int) $b['amount'] );
$r    = $d->refund( array( 'ref' => 'FX12TABC', 'currency' => 'EUR', 'refunded' => 0, 'bank_ref' => 'pi_1', 'data' => array( 'stripe_pi' => 'pi_1' ) ), 40.5 );
$call = end( $GLOBALS['flxt_calls'] );
ok( $r['ok'] && 're_1' === $r['bank_ref'], 'kısmi iade' );
ok( '4050' === $call['body']['amount'] && 'pi_1' === $call['body']['payment_intent'], 'iade tutarı ve intent' );

echo "Kayıtlı kart (aylık bağış)\n";
$GLOBALS['flxt_routes']['POST /v1/customers'] = array( 'id' => 'cus_1' );
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = static fn( $b ) => array( 'id' => 'pi_7', 'status' => 'succeeded', 'amount_received' => (int) $b['amount'], 'currency' => 'usd', 'customer' => $b['customer'] ?? null, 'payment_method' => $b['payment_method'], 'setup_future_usage' => $b['setup_future_usage'] ?? null, 'metadata' => $b['metadata'] );
$r    = $d->start( pay( array( 'pm_in' => 'pm_save', 'save' => 1 ) ) );
$call = end( $GLOBALS['flxt_calls'] );
ok( 'off_session' === $call['body']['setup_future_usage'] && 'cus_1' === $call['body']['customer'], 'ilk ödemede kart saklanır' );
ok( 'cus_1' === $r['result']['data']['stripe_customer'] && 'pm_save' === $r['result']['data']['stripe_pm'], 'customer + pm sonuca yazılır' );
$r    = $d->charge_saved( pay(), 'cus_1', 'pm_save' );
$call = end( $GLOBALS['flxt_calls'] );
ok( 'paid' === $r['status'] && 'true' === $call['body']['off_session'], 'off-session çekim' );
$GLOBALS['flxt_routes']['POST /v1/payment_intents'] = array( 'error' => array( 'code' => 'authentication_required', 'message' => 'auth', 'payment_intent' => array( 'id' => 'pi_8' ) ) );
$r = $d->charge_saved( pay(), 'cus_1', 'pm_save' );
ok( 'failed' === $r['status'] && 1 === $r['data']['needs_auth'], '3DS isterse needs_auth' );

echo $fail ? "\n$fail test başarısız\n" : "\nHepsi geçti\n";
exit( $fail ? 1 : 0 );
