<?php
/**
 * Belirteci Google'a doğrulatır. Aynı istek içinde aynı belirteç bir kez doğrulanır (WooCommerce girişi
 * hem kendi kancasından hem `authenticate`ten geçer; Google belirteci ikinci kez kabul etmez).
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Verify {

	/** POST alanı ve REST başlığı. */
	const FIELD  = 'flx-captcha-token';
	const HEADER = 'X-Flx-Captcha';

	/** @var array<string, true|WP_Error> Bu istekte doğrulanan belirteçler. */
	private static $done = array();

	/** İstekteki belirteç: POST alanı, v2'nin kendi alanı ya da başlık. */
	public static function token_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification
		foreach ( array( self::FIELD, 'g-recaptcha-response' ) as $f ) {
			if ( isset( $_POST[ $f ] ) && is_string( $_POST[ $f ] ) && '' !== $_POST[ $f ] ) {
				return sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
			}
		}
		// phpcs:enable
		$h = 'HTTP_' . strtoupper( str_replace( '-', '_', self::HEADER ) );
		return isset( $_SERVER[ $h ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) : '';
	}

	/**
	 * Doğrular. Koruma bu form için gerekmiyorsa true.
	 *
	 * @param string      $ctx   Bağlam.
	 * @param string|null $token Belirteç; null ise istekten okunur.
	 * @param bool        $force Gerekli olup olmadığına bakmadan doğrula (panel testi).
	 * @return true|WP_Error
	 */
	public static function check( $ctx, $token = null, $force = false ) {
		if ( ! $force && ! flxrc_required( $ctx ) ) {
			return true;
		}
		$token = null === $token ? self::token_from_request() : (string) $token;
		$key   = $ctx . '|' . $token;
		if ( isset( self::$done[ $key ] ) ) {
			return self::$done[ $key ];
		}
		$res = self::remote( $ctx, $token );
		if ( ! $force ) {
			flxrc_stat( $ctx, true === $res );
		}
		$res = apply_filters( 'flx_captcha_result', $res, $ctx );
		if ( is_wp_error( $res ) ) {
			do_action( 'flx_captcha_failed', $ctx, $res );
		}
		self::$done[ $key ] = $res;
		return $res;
	}

	/** Kullanıcıya gösterilecek hata metni. */
	public static function message( $code = '' ) {
		if ( 'empty' === $code ) {
			return 'v2' === flxrc_version()
				? __( 'Please tick the "I\'m not a robot" box.', 'flx-recaptcha' )
				: __( 'The security check could not load. Refresh the page and try again.', 'flx-recaptcha' );
		}
		return __( 'The security check failed. Refresh the page and try again.', 'flx-recaptcha' );
	}

	private static function fail( $code, $detail = '' ) {
		return new WP_Error( 'flxrc_' . $code, self::message( $code ), array( 'status' => 403, 'detail' => $detail ) );
	}

	private static function remote( $ctx, $token ) {
		if ( '' === $token ) {
			return self::fail( 'empty' );
		}
		if ( strlen( $token ) > 4000 ) {
			return self::fail( 'invalid' );
		}
		$resp = wp_remote_post(
			flxrc_api_host() . '/recaptcha/api/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => flxrc_secret(),
					'response' => $token,
					'remoteip' => flxrc_client_ip(),
				),
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			$why = is_wp_error( $resp ) ? $resp->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $resp );
			return self::unreachable( $why );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $body ) ) {
			return self::unreachable( 'bad json' );
		}
		$codes = isset( $body['error-codes'] ) ? array_map( 'strval', (array) $body['error-codes'] ) : array();
		/* Anahtar hatası ziyaretçinin suçu değil: kaydet, sistem kontrolünde göster, ayara göre geçir. */
		if ( array_intersect( $codes, array( 'missing-input-secret', 'invalid-input-secret', 'bad-request' ) ) ) {
			return self::unreachable( implode( ', ', $codes ), 'config' );
		}
		if ( empty( $body['success'] ) ) {
			return self::fail( 'invalid', implode( ', ', $codes ) );
		}
		if ( isset( $body['score'] ) ) {
			$want = flxrc_contexts()[ $ctx ]['action'] ?? $ctx;
			if ( isset( $body['action'] ) && (string) $body['action'] !== $want ) {
				return self::fail( 'action', (string) $body['action'] );
			}
			$min = (float) apply_filters( 'flx_captcha_threshold', (float) flxrc_settings( 'threshold' ), $ctx );
			if ( (float) $body['score'] < $min ) {
				return self::fail( 'score', (string) $body['score'] );
			}
		}
		return true;
	}

	/** Google'a ulaşılamadı ya da anahtar reddedildi: ayara göre geçir ya da engelle. */
	private static function unreachable( $why, $kind = 'network' ) {
		flxrc_status(
			'last_error',
			array(
				'time' => time(),
				'kind' => $kind,
				'why'  => substr( (string) $why, 0, 200 ),
			)
		);
		return flxrc_settings( 'fail_open' ) ? true : self::fail( 'unreachable', $why );
	}

	/** Panel "Test": verilen belirteci zorla doğrular; ham sonucu da döner. */
	public static function test( $token, $secret, $ctx = 'test' ) {
		$resp = wp_remote_post(
			flxrc_api_host() . '/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => (string) $token,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'msg' => sprintf( /* translators: %s: error */ __( 'Could not reach Google: %s', 'flx-recaptcha' ), $resp->get_error_message() ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $body ) ) {
			return array( 'ok' => false, 'msg' => __( 'Google returned an unexpected response.', 'flx-recaptcha' ) );
		}
		$codes = isset( $body['error-codes'] ) ? array_map( 'strval', (array) $body['error-codes'] ) : array();
		if ( in_array( 'invalid-input-secret', $codes, true ) || in_array( 'missing-input-secret', $codes, true ) ) {
			return array( 'ok' => false, 'msg' => __( 'The secret key is invalid. Copy the "Secret key" from the Google reCAPTCHA console.', 'flx-recaptcha' ) );
		}
		if ( empty( $body['success'] ) ) {
			$msg = in_array( 'invalid-input-response', $codes, true )
				? __( 'The site key and the secret key do not belong to the same entry, or the wrong reCAPTCHA type (v2/v3) is selected.', 'flx-recaptcha' )
				: sprintf( /* translators: %s: Google error codes */ __( 'Could not verify (%s).', 'flx-recaptcha' ), $codes ? implode( ', ', $codes ) : '?' );
			return array( 'ok' => false, 'msg' => $msg );
		}
		$host = isset( $body['hostname'] ) ? (string) $body['hostname'] : '';
		$msg  = isset( $body['score'] )
			? sprintf( /* translators: 1: score, 2: hostname */ __( 'The keys work. Score: %1$s, domain: %2$s', 'flx-recaptcha' ), number_format_i18n( (float) $body['score'], 1 ), $host )
			: sprintf( /* translators: %s: hostname */ __( 'The keys work. Domain: %s', 'flx-recaptcha' ), $host );
		return array(
			'ok'    => true,
			'msg'   => $msg,
			'score' => isset( $body['score'] ) ? (float) $body['score'] : null,
			'v3'    => isset( $body['score'] ),
		);
	}
}
