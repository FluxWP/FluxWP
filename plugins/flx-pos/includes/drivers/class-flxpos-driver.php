<?php
/**
 * Sürücü tabanı. Bir sürücü bir protokolü (Nestpay, GVP, Posnet…) uygular; aynı protokolü kullanan
 * sağlayıcılar (bankalar) yalnız adres ve ayarla ayrılır.
 *
 * Akış:
 *  1. start(): ödemeyi başlatır; dönüş bir eylem: form (3D sayfasına otomatik POST), redirect, html, iframe
 *     ya da result (3D'siz anında sonuç).
 *  2. finish(): banka ödeyeni dönüş adresine gönderdiğinde çağrılır. Dönüş verisine güvenilmez: imza/hash
 *     doğrulanır, gerekirse sunucudan provizyon ya da sorgu yapılır. Dönüş bir sonuç dizisi.
 *  3. notify(): sağlayıcının sunucudan sunucuya bildirimi (PayTR, Mollie, Papara…).
 *
 * Sonuç dizisi: status (paid|failed|pending|review), message, bank_ref, auth_code, amount (bankanın
 * çektiği; bilinmiyorsa null), installment, card (maske), data (günlük).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

abstract class FLXPOS_Driver {

	/** @var array Sağlayıcı tanımı. */
	protected $p;
	/** @var array Hesap (çözülmüş anahtarlar). */
	protected $a;
	/** @var bool */
	protected $test;

	public function __construct( array $provider, array $account ) {
		$this->p    = $provider;
		$this->a    = $account;
		$this->test = ! empty( $account['test'] );
	}

	/** Hesap alanı. */
	protected function c( $key, $default = '' ) {
		$v = $this->a['creds'][ $key ] ?? '';
		return '' === $v || null === $v ? $default : trim( (string) $v );
	}

	/** Seçili 3D modeli (mode). */
	public function mode() {
		$modes = array_keys( (array) ( $this->p['modes'] ?? array( '3d_pay' => '' ) ) );
		$m     = (string) ( $this->a['mode'] ?? '' );
		return in_array( $m, $modes, true ) ? $m : (string) reset( $modes );
	}

	/** Bu model kart bilgisini sitemizde alır mı? (host/ortak ödeme sayfası modellerinde hayır.) */
	public function needs_card() {
		return ! in_array( $this->mode(), array( '3d_host', 'hosted', 'iframe', 'redirect', 'wallet' ), true );
	}

	/** Uç nokta: hesapta elle girilmişse o, yoksa tanımdaki test/canlı adres. */
	protected function url( $key ) {
		$own = $this->c( 'url_' . $key );
		if ( '' !== $own ) {
			return $own;
		}
		$set = (array) ( $this->p['endpoints'][ $this->test ? 'test' : 'live' ] ?? array() );
		if ( ! isset( $set[ $key ] ) ) {
			$set = (array) ( $this->p['endpoints']['live'] ?? array() );
		}
		return (string) apply_filters( 'flxpos_endpoint', (string) ( $set[ $key ] ?? '' ), $key, $this->p['id'], $this->test );
	}

	protected function opt( $key, $default = null ) {
		return $this->p['opts'][ $key ] ?? $default;
	}

	protected function name() {
		return (string) $this->p['name'];
	}

	/**
	 * Ödemeyi başlat.
	 *
	 * @return array|WP_Error form{url,fields} | redirect | html | iframe{url} | result.
	 */
	abstract public function start( FLXPOS_Payment $p );

	/**
	 * Banka dönüşü.
	 *
	 * @param array $txn İşlem kaydı.
	 * @param array $req post, get, raw.
	 * @return array Sonuç.
	 */
	abstract public function finish( array $txn, array $req );

	/** Sunucu bildirimi: sonuç + ref + reply (sağlayıcıya yazılacak yanıt). */
	public function notify( array $req ) {
		return new WP_Error( 'flxpos_unsupported', __( 'Bu sağlayıcı bildirim göndermiyor.', 'flx-pos' ) );
	}

	/** İade (kısmi olabilir). @return array{ok:bool,message:string,bank_ref?:string}|WP_Error */
	public function refund( array $txn, $amount ) {
		return new WP_Error( 'flxpos_unsupported', __( 'Bu sağlayıcıda siteden iade yok; sağlayıcının panelinden yapın.', 'flx-pos' ) );
	}

	/** Gün sonu öncesi iptal. */
	public function cancel( array $txn ) {
		return new WP_Error( 'flxpos_unsupported', __( 'Bu sağlayıcıda siteden iptal yok.', 'flx-pos' ) );
	}

	/** Bankadan durum sorgusu: finish() ile aynı sonuç biçimi. */
	public function status( array $txn ) {
		return new WP_Error( 'flxpos_unsupported', __( 'Bu sağlayıcıda durum sorgusu yok.', 'flx-pos' ) );
	}

	/**
	 * Karta göre taksit seçenekleri (sağlayıcı API'si). null = desteklemiyor.
	 *
	 * @return array<int,array{total:float,rate:float}>|null|WP_Error
	 */
	public function installments( $bin, $amount, $currency ) {
		return null;
	}

	/** Bağlantı testi: true, WP_Error ya da null (test edilemiyor). */
	public function test_connection() {
		return null;
	}

	/* ---------------- Ortak yardımcılar ---------------- */

	protected function ok( array $more = array() ) {
		return array_merge(
			array(
				'status'      => 'paid',
				'message'     => '',
				'bank_ref'    => '',
				'auth_code'   => '',
				'amount'      => null,
				'installment' => null,
				'data'        => array(),
			),
			$more
		);
	}

	protected function fail( $message, array $more = array() ) {
		return array_merge( $this->ok( $more ), array( 'status' => 'failed', 'message' => (string) $message ) );
	}

	protected function pending( $message = '', array $more = array() ) {
		return array_merge( $this->ok( $more ), array( 'status' => 'pending', 'message' => (string) $message ) );
	}

	protected function err( $message ) {
		return new WP_Error( 'flxpos_' . $this->p['driver'], (string) $message );
	}

	/** Bir ret: mesaj, sağlayıcı adı önekli. */
	protected function reason( $msg, $code = '' ) {
		$msg = trim( wp_strip_all_tags( (string) $msg ) );
		if ( '' === $msg && '' !== (string) $code ) {
			/* translators: %s: hata kodu */
			$msg = sprintf( __( 'Hata kodu %s', 'flx-pos' ), $code );
		}
		return '' === $msg ? __( 'Ödeme onaylanmadı.', 'flx-pos' ) : $msg;
	}

	/** Form POST. */
	protected function post_form( $url, array $fields, array $headers = array() ) {
		return flxpos_http( $url, $fields, array( 'headers' => $headers + array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
	}

	/** JSON POST; dizi ya da WP_Error. */
	protected function post_json( $url, $body, array $headers = array(), $method = 'POST' ) {
		$r = flxpos_http(
			$url,
			null === $body ? null : wp_json_encode( $body ),
			array(
				'method'  => $method,
				'headers' => $headers + array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			)
		);
		return flxpos_json( $r, $this->name() );
	}

	/** XML POST; dizi ya da WP_Error. */
	protected function post_xml( $url, $xml, array $headers = array(), $as_form_field = '' ) {
		$body = '' !== $as_form_field ? array( $as_form_field => $xml ) : $xml;
		$h    = '' !== $as_form_field ? array( 'Content-Type' => 'application/x-www-form-urlencoded' ) : array( 'Content-Type' => 'text/xml; charset=utf-8' );
		$r    = flxpos_http( $url, $body, array( 'headers' => $headers + $h ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$x = flxpos_xml_to_array( $r['body'] );
		if ( null === $x ) {
			/* translators: 1: sağlayıcı, 2: HTTP durum kodu */
			return $this->err( sprintf( __( '%1$s yanıt vermedi (HTTP %2$d).', 'flx-pos' ), $this->name(), $r['code'] ) );
		}
		return $x;
	}

	/** SOAP 1.1 çağrısı; Body içindeki ilk düğüm dizi olarak. */
	protected function soap( $url, $action, $inner_xml, $ns = '' ) {
		$env = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>' . $inner_xml . '</soap:Body></soap:Envelope>';
		$r   = flxpos_http(
			$url,
			$env,
			array(
				'headers' => array(
					'Content-Type' => 'text/xml; charset=utf-8',
					'SOAPAction'   => '"' . $action . '"',
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$x = flxpos_xml_to_array( $r['body'] );
		if ( null === $x ) {
			/* translators: 1: sağlayıcı, 2: HTTP durum kodu */
			return $this->err( sprintf( __( '%1$s yanıt vermedi (HTTP %2$d).', 'flx-pos' ), $this->name(), $r['code'] ) );
		}
		$body = $x['Body'] ?? $x;
		return is_array( $body ) ? (array) reset( $body ) : array();
	}

	/** Dönüşte gelen alan (POST, yoksa GET). */
	protected function in( array $req, $key, $default = '' ) {
		foreach ( array( 'post', 'get' ) as $k ) {
			if ( isset( $req[ $k ][ $key ] ) && is_scalar( $req[ $k ][ $key ] ) ) {
				return trim( (string) $req[ $k ][ $key ] );
			}
		}
		return $default;
	}

	/** Dönüş verisinin tamamı (POST + GET, düz metin alanlar). */
	protected function all( array $req ) {
		$out = array();
		foreach ( array( 'get', 'post' ) as $k ) {
			foreach ( (array) ( $req[ $k ] ?? array() ) as $kk => $v ) {
				if ( is_scalar( $v ) ) {
					$out[ $kk ] = (string) $v;
				}
			}
		}
		return $out;
	}

	/** Kart numarası maskesi. */
	protected function mask( $pan ) {
		$pan = preg_replace( '/\D/', '', (string) $pan );
		return strlen( $pan ) >= 10 ? substr( $pan, 0, 6 ) . '******' . substr( $pan, -4 ) : '';
	}

	/** Rastgele metin. */
	protected function rnd( $len = 20 ) {
		return substr( bin2hex( random_bytes( (int) ceil( $len / 2 ) ) ), 0, $len );
	}
}
