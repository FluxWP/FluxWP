<?php
/**
 * Donor lookup: start, verify, the current session, logout.
 * The answer of start is the same whether or not a donation exists (the classic rule).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxbLookupStart array{ok: bool, via: string, ttl: int, message: string}
 * @phpstan-type FlxbLookupData array{who: string, account: bool, count: int, total_label: string, items: list<array{id: int, title: string, status: string, date: string, amount: float, currency: string}>, certificates: list<array{code: string}>, donate_url: string}
 * @phpstan-type FlxbLookupVerify array{ok: bool, data: FlxbLookupData}
 */
final class FLXB_Lookup_Service {

	/**
	 * Ask for a code. Same payload for a known and an unknown address.
	 *
	 * @return FlxbLookupStart|WP_Error
	 */
	public static function start( string $login ) {
		if ( empty( FLXB_Lookup::settings()['on'] ) ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_off', __( 'Donation lookup is not available.', 'flx-donate' ), 403 ) );
		}
		list( $type, $value ) = FLXB_Lookup::normalize( $login );
		if ( '' === $type ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_login', __( 'Enter a valid e-mail address or phone number.', 'flx-donate' ) ) );
		}
		if ( 'phone' === $type && ! FLXB_Lookup::phone_ready() ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_phone', __( 'Codes cannot be sent by phone at the moment. Please use your e-mail address.', 'flx-donate' ) ) );
		}
		if ( 'email' === $type && ! FLXB_Lookup::channel_ready( 'email' ) ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_email', __( 'Codes cannot be sent by e-mail at the moment. Please use your phone number.', 'flx-donate' ) ) );
		}
		if ( ! FLXB_Lookup::rate( 'ip|' . FLXB_Lookup::ip(), 8, 10 * MINUTE_IN_SECONDS ) || ! FLXB_Lookup::rate( 'login|' . $value, 3, 10 * MINUTE_IN_SECONDS ) ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-donate' ), 429 ) );
		}
		$via = 'email' === $type ? 'email' : '';
		$m   = FLXB_Lookup::match( $type, $value );
		if ( $m['donors'] || $m['orders'] ) {
			$code = (string) wp_rand( 100000, 999999 );
			$sent = FLXB_Lookup::deliver( $type, $value, $code );
			if ( false === $sent ) {
				return self::as_error( FLXB_Lookup::err( 'flxb_lookup_send', __( 'The code could not be sent. Please try again later or contact us.', 'flx-donate' ), 503 ) );
			}
			$via = $sent;
			set_transient( 'flxb_lkc_' . FLXB_Lookup::h( $value ), array( 'h' => wp_hash_password( $code ), 'tries' => 0 ), FLXB_Lookup::TTL );
		} elseif ( 'phone' === $type ) {
			$via = FLXB_Lookup::channel_ready( 'whatsapp' ) ? 'whatsapp' : 'sms';
		}
		return array(
			'ok'      => true,
			'via'     => $via,
			'ttl'     => FLXB_Lookup::TTL,
			'message' => FLXB_Lookup::sent_message( $via ),
		);
	}

	/**
	 * Check the code and open the session. A wrong code and an unknown address get the same error.
	 *
	 * @return FlxbLookupVerify|WP_Error
	 */
	public static function verify( string $login, string $code ) {
		list( $type, $value ) = FLXB_Lookup::normalize( $login );
		$code                 = (string) preg_replace( '/\D+/', '', $code );
		$bad                  = self::as_error( FLXB_Lookup::err( 'flxb_lookup_code', __( 'The code is wrong or has expired.', 'flx-donate' ) ) );
		if ( '' === $type || 6 !== strlen( $code ) ) {
			return $bad;
		}
		if ( ! FLXB_Lookup::rate( 'verify|' . FLXB_Lookup::ip(), 20, 10 * MINUTE_IN_SECONDS ) ) {
			return self::as_error( FLXB_Lookup::err( 'flxb_lookup_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-donate' ), 429 ) );
		}
		$key = 'flxb_lkc_' . FLXB_Lookup::h( $value );
		$d   = get_transient( $key );
		if ( ! is_array( $d ) || '' === self::str( $d['h'] ?? '' ) ) {
			return $bad;
		}
		if ( ! wp_check_password( $code, self::str( $d['h'] ?? '' ) ) ) {
			$d['tries'] = self::int( $d['tries'] ?? 0 ) + 1;
			if ( $d['tries'] >= FLXB_Lookup::TRIES ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $d, FLXB_Lookup::TTL );
			}
			return $bad;
		}
		delete_transient( $key );
		$token = wp_generate_password( 40, false, false );
		set_transient( 'flxb_lks_' . FLXB_Lookup::h( $token ), array( 'type' => $type, 'value' => $value ), FLXB_Lookup::SESSION );
		FLXB_Lookup::set_cookie( $token, time() + FLXB_Lookup::SESSION );
		return array(
			'ok'   => true,
			'data' => FLXB_Lookup::proven( $type, $value ),
		);
	}

	/**
	 * The lookup page's current answer (signed-in account, else the session).
	 *
	 * @return array{ok: bool, data?: FlxbLookupData}
	 */
	public static function current(): array {
		if ( is_user_logged_in() ) {
			return array(
				'ok'   => true,
				'data' => FLXB_Lookup::account( get_current_user_id() ),
			);
		}
		$s = FLXB_Lookup::session();
		if ( ! is_array( $s ) || empty( $s['type'] ) ) {
			return array( 'ok' => false );
		}
		return array(
			'ok'   => true,
			'data' => FLXB_Lookup::proven( $s['type'], $s['value'] ),
		);
	}

	/**
	 * End the lookup session.
	 *
	 * @return array{ok: bool}
	 */
	public static function end(): array {
		$raw = $_COOKIE[ FLXB_Lookup::COOKIE ] ?? '';
		$t   = is_string( $raw ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $raw ) ) : '';
		if ( is_string( $t ) && '' !== $t ) {
			delete_transient( 'flxb_lks_' . FLXB_Lookup::h( $t ) );
		}
		FLXB_Lookup::set_cookie( '', time() - HOUR_IN_SECONDS );
		return array( 'ok' => true );
	}

	/**
	 * @param mixed $err Error from the classic helper.
	 */
	private static function as_error( $err ): WP_Error {
		return $err instanceof WP_Error ? $err : new WP_Error( 'flxb_lookup', '' );
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function int( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}
}
