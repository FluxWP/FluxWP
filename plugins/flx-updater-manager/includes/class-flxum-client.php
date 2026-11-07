<?php
/**
 * Güncelleme sunucusunun yönetici API'si (/v1/admin/*) istemcisi.
 *
 * Sunucu adresi: FLXUM_SERVER sabiti → panel ayarı → FluxWP Panel'in (flx-setup) sunucusu → updater.fluxwp.com.
 * Yönetici anahtarı: FLXUM_KEY sabiti → panel ayarı (şifreli).
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

final class FLXUM_Client {

	/** @var array Aynı istekteki GET yanıtları. */
	private static $memo = array();

	public static function server() {
		if ( defined( 'FLXUM_SERVER' ) && FLXUM_SERVER ) {
			return untrailingslashit( (string) FLXUM_SERVER );
		}
		$s = (string) flxum_settings()['server'];
		if ( '' !== $s ) {
			return untrailingslashit( $s );
		}
		return self::suggested_server();
	}

	/** Ayar yoksa önerilen adres: bu sitedeki FluxWP Panel'in sunucusu, yoksa resmi sunucu. */
	public static function suggested_server() {
		if ( class_exists( 'FLXU_Updater' ) && method_exists( 'FLXU_Updater', 'server' ) ) {
			return untrailingslashit( (string) FLXU_Updater::server() );
		}
		return FLXUM_DEFAULT_SERVER;
	}

	public static function key() {
		if ( defined( 'FLXUM_KEY' ) && FLXUM_KEY ) {
			return (string) FLXUM_KEY;
		}
		return flxum_unseal( flxum_settings()['key'] );
	}

	public static function key_from_config() {
		return defined( 'FLXUM_KEY' ) && FLXUM_KEY;
	}

	public static function configured() {
		return '' !== self::key();
	}

	public static function lang() {
		return 0 === strpos( get_user_locale(), 'tr' ) ? 'tr' : 'en';
	}

	public static function get( $route, $query = array(), $timeout = 20 ) {
		$k = $route . '?' . http_build_query( $query );
		if ( ! isset( self::$memo[ $k ] ) ) {
			self::$memo[ $k ] = self::request( 'GET', $route, null, $query, $timeout );
		}
		return self::$memo[ $k ];
	}

	/** Panel işlemi: {ok, code, message, data}. */
	public static function action( $action, $fields = array(), $timeout = 30 ) {
		self::$memo = array();
		return self::request( 'POST', 'action', array( 'action' => $action ) + $fields, array(), $timeout );
	}

	/**
	 * @return array|WP_Error Çözülmüş JSON (+ _status) ya da hata.
	 */
	public static function request( $method, $route, $body = null, $query = array(), $timeout = 20, $server = null, $key = null ) {
		$server = null === $server ? self::server() : untrailingslashit( (string) $server );
		$key    = null === $key ? self::key() : (string) $key;
		if ( '' === $key ) {
			return new WP_Error( 'flxum_no_key', __( 'Yönetici anahtarı girilmemiş.', 'flx-updater-manager' ) );
		}
		if ( ! wp_http_validate_url( $server ) && ! preg_match( '#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#', $server ) ) {
			return new WP_Error( 'flxum_bad_server', __( 'Sunucu adresi geçersiz.', 'flx-updater-manager' ) );
		}
		$url  = $server . '/v1/admin/' . $route . '?' . http_build_query( array( 'lang' => self::lang() ) + (array) $query );
		$args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 0,
			'headers'     => array(
				'X-FluxWP-Manager-Key' => $key,
				'Accept'               => 'application/json',
			),
			'user-agent'  => 'FluxUpdaterManager/' . FLXUM_VERSION . '; ' . home_url( '/' ),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'flxum_http', sprintf( /* translators: %s: hata */ __( 'Sunucuya ulaşılamadı: %s', 'flx-updater-manager' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'flxum_bad_response',
				404 === $code
					? __( 'Bu sunucuda yönetici API\'si yok. Güncelleme sunucusunu 1.12.0 ya da üstüne yükseltin.', 'flx-updater-manager' )
					: sprintf( /* translators: %d: HTTP kodu */ __( 'Sunucudan beklenmeyen yanıt (HTTP %d).', 'flx-updater-manager' ), $code )
			);
		}
		if ( 401 === $code ) {
			return new WP_Error( 'flxum_key', __( 'Yönetici anahtarı geçersiz ya da sunucuda silinmiş.', 'flx-updater-manager' ) );
		}
		if ( $code >= 400 && 422 !== $code ) {
			return new WP_Error( 'flxum_' . sanitize_key( (string) ( $data['code'] ?? 'error' ) ), (string) ( $data['error'] ?? $data['message'] ?? __( 'Sunucu hatası.', 'flx-updater-manager' ) ) );
		}
		$data['_status'] = $code;
		return $data;
	}
}
