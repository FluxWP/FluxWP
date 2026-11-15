<?php
/**
 * reCAPTCHA settings: the one place that reads and changes the keys, the protected forms and the
 * options, and that tests keys with Google. The classic page (admin-post / admin-ajax handlers) and
 * GraphQL call this. Permission checks stay with the caller (manage_options).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT and the input `field`. Nothing is written when
 * any part of an update is invalid.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxrcUpdate array{version?: string, site_key?: string, secret?: string, threshold?: float, domain?: string, v2_theme?: string, v2_size?: string, hide_badge?: bool, skip_logged_in?: bool, fail_open?: bool, allow_ips?: list<string>, forms?: list<array{key: string, enabled: bool}>}
 */
final class FLXRC_Settings_Service {

	public const CACHE_TAG = 'flx-recaptcha:config';

	/** Characters a Google key can have. */
	private const KEY_PATTERN = '/^[A-Za-z0-9_-]*$/';

	/** An IP or a CIDR range. */
	private const IP_PATTERN = '#^[0-9a-fA-F:.]+(/\d{1,3})?$#';

	/**
	 * Settings of the page; the secret key is never returned.
	 */
	public static function settings(): FLXRC_Settings {
		$s    = self::opts();
		$ips  = array_values( array_filter( preg_split( '/[\s,]+/', self::str( $s['allow_ips'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) ?: array(), 'strlen' ) );
		$sc   = flxrc_secret();
		$sk_c = defined( 'FLXRC_SITE_KEY' ) && is_string( FLXRC_SITE_KEY ) && '' !== FLXRC_SITE_KEY;
		$sc_c = defined( 'FLXRC_SECRET_KEY' ) && is_string( FLXRC_SECRET_KEY ) && '' !== FLXRC_SECRET_KEY;
		return new FLXRC_Settings(
			flxrc_version(),
			flxrc_site_key(),
			$sk_c,
			'' !== $sc,
			flxrc_mask( $sc ),
			$sc_c,
			self::num( $s['threshold'] ?? 0.5 ),
			'recaptcha' === ( $s['domain'] ?? '' ) ? 'recaptcha' : 'google',
			in_array( $s['v2_theme'] ?? '', array( 'light', 'dark' ), true ) ? self::str( $s['v2_theme'] ) : 'auto',
			'compact' === ( $s['v2_size'] ?? '' ) ? 'compact' : 'normal',
			! empty( $s['hide_badge'] ),
			! empty( $s['skip_logged_in'] ),
			! empty( $s['fail_open'] ),
			$ips,
			self::str( flxrc_client_ip() ),
			flxrc_keys_verified(),
			flxrc_disabled_by_constant(),
			defined( 'FLXF_PLUGIN_FILE' ) && flxrc_context_on( 'forms' ),
		);
	}

	/**
	 * Every form (context) with its switch and the last 30 days.
	 *
	 * @return list<FLXRC_Form>
	 */
	public static function forms(): array {
		$stats  = flxrc_stats_totals( 30 );
		$groups = flxrc_groups();
		$out    = array();
		foreach ( flxrc_contexts() as $key => $c ) {
			$group  = isset( $groups[ self::str( $c['group'] ?? '' ) ] ) ? self::str( $c['group'] ) : 'other';
			$st     = $stats[ $key ] ?? array( 0, 0 );
			$out[]  = new FLXRC_Form(
				(string) $key,
				self::str( $c['label'] ?? '' ),
				self::str( $c['desc'] ?? '' ),
				$group,
				self::str( $groups[ $group ] ?? $group ),
				! empty( $c['available'] ),
				flxrc_context_on( (string) $key ),
				(int) $st[0],
				(int) $st[1],
			);
		}
		return $out;
	}

	/**
	 * Protection state, forms on, the last 30 days, the last test and the last Google error.
	 */
	public static function status(): FLXRC_Status {
		$locked   = flxrc_disabled_by_constant();
		$verified = flxrc_keys_verified();
		$on       = 0;
		$avail    = 0;
		foreach ( flxrc_contexts() as $k => $c ) {
			if ( ! empty( $c['available'] ) ) {
				++$avail;
				$on += flxrc_context_on( (string) $k ) ? 1 : 0;
			}
		}
		$totals = flxrc_stats_totals( 30 );
		$test   = flxrc_status( 'last_test' );
		$err    = flxrc_status( 'last_error' );
		$log    = is_array( $test ) && ! empty( $test['time'] ) ? new FLXRC_Test_Log( (int) $test['time'], ! empty( $test['ok'] ), self::str( $test['msg'] ?? '' ) ) : null;
		$errlog = null;
		if ( $verified && is_array( $err ) && ! empty( $err['time'] ) && (int) $err['time'] > time() - DAY_IN_SECONDS ) {
			$errlog = new FLXRC_Error_Log( (int) $err['time'], 'config' === ( $err['kind'] ?? '' ) ? 'config' : 'network', self::str( $err['why'] ?? '' ), (bool) flxrc_settings( 'fail_open' ) );
		}
		return new FLXRC_Status(
			$locked ? 'off' : ( $verified ? 'on' : 'awaiting_test' ),
			flxrc_version(),
			$on,
			$avail,
			(int) $totals['_all'][0],
			(int) $totals['_all'][1],
			$log,
			$errlog,
		);
	}

	/**
	 * Changes the keys, the options and the form switches in one go. Fields left out stay as they are.
	 * An empty secret key keeps the saved one; the secret key is stored sealed.
	 *
	 * @param FlxrcUpdate $in Input.
	 * @return FLXRC_Settings|WP_Error
	 */
	public static function update( array $in ) {
		$s = self::opts();

		if ( isset( $in['version'] ) && ! in_array( $in['version'], array( 'v2', 'v3' ), true ) ) {
			return self::invalid( 'version', __( 'Choose v2 or v3.', 'flx-recaptcha' ) );
		}
		foreach ( array( 'site_key' => 'siteKey', 'secret' => 'secret' ) as $k => $field ) {
			if ( isset( $in[ $k ] ) && 1 !== preg_match( self::KEY_PATTERN, $in[ $k ] ) ) {
				return self::invalid( $field, __( 'A key can only contain letters, numbers, "-" and "_".', 'flx-recaptcha' ) );
			}
		}
		if ( isset( $in['threshold'] ) && ( $in['threshold'] < 0.1 || $in['threshold'] > 0.9 ) ) {
			return self::invalid( 'threshold', __( 'The bot threshold must be between 0.1 and 0.9.', 'flx-recaptcha' ) );
		}
		if ( isset( $in['domain'] ) && ! in_array( $in['domain'], array( 'google', 'recaptcha' ), true ) ) {
			return self::invalid( 'domain', __( 'Choose the Google host.', 'flx-recaptcha' ) );
		}
		if ( isset( $in['v2_theme'] ) && ! in_array( $in['v2_theme'], array( 'auto', 'light', 'dark' ), true ) ) {
			return self::invalid( 'v2Theme', __( 'Choose the checkbox theme.', 'flx-recaptcha' ) );
		}
		if ( isset( $in['v2_size'] ) && ! in_array( $in['v2_size'], array( 'normal', 'compact' ), true ) ) {
			return self::invalid( 'v2Size', __( 'Choose the checkbox size.', 'flx-recaptcha' ) );
		}
		$ips = null;
		if ( isset( $in['allow_ips'] ) ) {
			$ips = array();
			foreach ( $in['allow_ips'] as $i => $ip ) {
				$ip = trim( $ip );
				if ( '' === $ip ) {
					continue;
				}
				if ( 1 !== preg_match( self::IP_PATTERN, $ip ) ) {
					return self::invalid( 'allowIps.' . $i, __( 'Enter an IP address or a range like 198.51.100.0/24.', 'flx-recaptcha' ) );
				}
				$ips[] = $ip;
			}
		}
		$forms = array();
		if ( isset( $in['forms'] ) ) {
			$known = flxrc_contexts();
			foreach ( $in['forms'] as $i => $f ) {
				$key = sanitize_key( $f['key'] );
				if ( ! isset( $known[ $key ] ) ) {
					return self::invalid( 'forms.' . $i . '.key', __( 'This form is not known.', 'flx-recaptcha' ) );
				}
				$forms[ $key ] = (int) $f['enabled'];
			}
		}

		// All parts are valid: write.
		$s = self::apply_keys( $s, $in['version'] ?? self::str( $s['version'] ), $in['site_key'] ?? null, $in['secret'] ?? '' );
		if ( isset( $in['threshold'] ) ) {
			$s['threshold'] = round( $in['threshold'], 1 );
		}
		foreach ( array( 'domain', 'v2_theme', 'v2_size' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $k ] = $in[ $k ];
			}
		}
		foreach ( array( 'hide_badge', 'skip_logged_in', 'fail_open' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $k ] = (int) $in[ $k ];
			}
		}
		if ( null !== $ips ) {
			$s['allow_ips'] = implode( "\n", array_unique( $ips ) );
		}
		if ( $forms ) {
			$s['forms'] = array_merge( is_array( $s['forms'] ) ? $s['forms'] : array(), $forms );
		}
		flxrc_update_settings( $s );
		return self::settings();
	}

	/**
	 * Tests the keys with the token the browser got from Google. When the test passes the keys are saved
	 * (a typed secret key replaces the saved one) and protection can turn on.
	 *
	 * @param string $version 'v3' or 'v2'.
	 * @param string $site    Site key typed ('' with FLXRC_SITE_KEY: the constant is used).
	 * @param string $typed   Secret key typed ('' keeps the saved one).
	 * @param string $token   Token from Google.
	 * @return FLXRC_Test_Result|WP_Error
	 */
	public static function test_keys( string $version, string $site, string $typed, string $token ) {
		$version = 'v2' === $version ? 'v2' : 'v3';
		$site    = defined( 'FLXRC_SITE_KEY' ) && is_string( FLXRC_SITE_KEY ) && '' !== FLXRC_SITE_KEY ? FLXRC_SITE_KEY : (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $site );
		$typed   = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $typed );
		$secret  = '' !== $typed ? $typed : flxrc_secret();
		if ( '' === $site || '' === $secret ) {
			return self::invalid( '' === $site ? 'siteKey' : 'secret', __( 'The site key and the secret key are required.', 'flx-recaptcha' ) );
		}
		$res = FLXRC_Verify::test( $token, $secret );
		if ( $res['ok'] && ( 'v3' === $version ) !== ! empty( $res['v3'] ) ) {
			$res = array(
				'ok'  => false,
				'msg' => 'v3' === $version ? __( 'These are v2 (checkbox) keys. Choose v2, or create v3 keys in the Google console.', 'flx-recaptcha' ) : __( 'These are v3 keys. Choose v3, or create v2 keys in the Google console.', 'flx-recaptcha' ),
			);
		}
		flxrc_status( 'last_test', array( 'time' => time(), 'ok' => $res['ok'], 'msg' => wp_strip_all_tags( (string) $res['msg'] ) ) );
		if ( ! $res['ok'] ) {
			return new FLXRC_Test_Result( false, (string) $res['msg'], null );
		}
		$s = self::apply_keys( self::opts(), $version, $site, $typed );
		flxrc_update_settings( $s );
		$s['verified'] = flxrc_key_fingerprint();
		flxrc_update_settings( $s );
		flxrc_status_clear( 'last_error' );
		return new FLXRC_Test_Result( true, (string) $res['msg'], isset( $res['score'] ) ? (float) $res['score'] : null );
	}

	/**
	 * Writes the version and keys into the settings; a changed key drops the test approval.
	 *
	 * @param array<string,mixed> $s       Settings.
	 * @param string              $version 'v3' or 'v2'.
	 * @param string|null         $site    Site key (null keeps it).
	 * @param string              $secret  Secret key typed ('' keeps the saved one).
	 * @return array<string,mixed>
	 */
	public static function apply_keys( array $s, string $version, ?string $site, string $secret ): array {
		$s['version'] = 'v2' === $version ? 'v2' : 'v3';
		if ( null !== $site && ! ( defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ) ) {
			$s['site_key'] = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $site );
		}
		$secret = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $secret );
		if ( '' !== $secret ) {
			$s['secret'] = flxrc_seal( $secret );
		}
		// The approval is valid only for the same version + keys.
		$const_site   = defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ? (string) FLXRC_SITE_KEY : self::str( $s['site_key'] ?? '' );
		$const_secret = defined( 'FLXRC_SECRET_KEY' ) && FLXRC_SECRET_KEY ? (string) FLXRC_SECRET_KEY : flxrc_unseal( self::str( $s['secret'] ?? '' ) );
		$fp           = substr( hash( 'sha256', $s['version'] . '|' . $const_site . '|' . $const_secret ), 0, 24 );
		if ( ! hash_equals( self::str( $s['verified'] ?? '' ), $fp ) ) {
			$s['verified'] = '';
		}
		return $s;
	}

	/**
	 * Drops the cached public configuration (the settings changed).
	 */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	/**
	 * Every setting with the defaults filled in.
	 *
	 * @return array<string,mixed>
	 */
	private static function opts(): array {
		$s = flxrc_settings();
		return array_merge( flxrc_defaults(), is_array( $s ) ? $s : array() );
	}

	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error( 'flxrc_invalid', $message, array( 'graphql_code' => 'INVALID_INPUT', 'field' => $field ) );
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
	private static function num( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}
}
