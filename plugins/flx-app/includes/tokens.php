<?php
/**
 * Belirteçler: erişim (flxat_), yenileme (flxar_) ve kişisel API anahtarı (flxak_).
 * Veritabanında yalnız sha256 özeti ve son 4 karakter (hint) durur.
 * REST isteklerinde `Authorization: Bearer <belirteç>` (ya da `X-FluxWP-Token`) kullanıcıyı belirler.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_PREFIX = array(
	'access'  => 'flxat_',
	'refresh' => 'flxar_',
	'key'     => 'flxak_',
);

/**
 * Yeni belirteç kaydeder, düz metni döndürür (yalnız bu anda görünür).
 *
 * @param string $kind access|refresh|key
 * @param array  $a    user_id, client_id, app_id, grant_id, name, scope, ttl (sn; 0 = süresiz)
 * @return string
 */
function flxapp_issue( $kind, array $a ) {
	global $wpdb;
	$token = flxapp_random( FLXAPP_PREFIX[ $kind ] );
	$now   = time();
	$ttl   = (int) ( $a['ttl'] ?? 0 );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		flxapp_table(),
		array(
			'kind'      => $kind,
			'hash'      => flxapp_hash( $token ),
			'hint'      => substr( $token, -4 ),
			'user_id'   => (int) $a['user_id'],
			'client_id' => substr( (string) ( $a['client_id'] ?? '' ), 0, 100 ),
			'app_id'    => substr( (string) ( $a['app_id'] ?? '' ), 0, 100 ),
			'grant_id'  => (string) ( $a['grant_id'] ?? '' ),
			'name'      => substr( (string) ( $a['name'] ?? '' ), 0, 190 ),
			'scope'     => substr( (string) ( $a['scope'] ?? '' ), 0, 190 ),
			'created'   => $now,
			'expires'   => $ttl > 0 ? $now + $ttl : 0,
		)
	);
	return $token;
}

/** Belirteç kaydını bulur (iptal/süre kontrolü yapmadan). */
function flxapp_find( $token ) {
	global $wpdb;
	if ( ! is_string( $token ) || strlen( $token ) < 20 || strlen( $token ) > 200 ) {
		return null;
	}
	$t = flxapp_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE hash = %s", flxapp_hash( $token ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxapp_is_live( $row ) {
	return $row && ! (int) $row['revoked'] && ( ! (int) $row['expires'] || (int) $row['expires'] > time() );
}

function flxapp_revoke_id( $id ) {
	global $wpdb;
	$wpdb->update( flxapp_table(), array( 'revoked' => 1 ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
}

/** Bir oturumun (grant) tüm erişim ve yenileme belirteçlerini iptal eder. */
function flxapp_revoke_grant( $grant_id ) {
	global $wpdb;
	if ( '' === (string) $grant_id ) {
		return;
	}
	$wpdb->update( flxapp_table(), array( 'revoked' => 1 ), array( 'grant_id' => (string) $grant_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	/** Oturum kapandı (cihaz kaydı ve push belirteci de silinir). */
	do_action( 'flxapp_grant_revoked', (string) $grant_id );
}

/** Kullanıcı silinince ya da parolası değişince tüm belirteçleri iptal. */
function flxapp_revoke_user( $user_id ) {
	global $wpdb;
	$wpdb->update( flxapp_table(), array( 'revoked' => 1 ), array( 'user_id' => (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	do_action( 'flxapp_user_revoked', (int) $user_id );
}
add_action( 'delete_user', 'flxapp_revoke_user' );
add_action(
	'after_password_reset',
	function ( $user ) {
		flxapp_revoke_user( $user->ID );
	}
);

/** Süresi geçmiş/iptal kayıtları ara sıra temizler (cron'a güvenmeden, istek başına küçük olasılıkla). */
function flxapp_gc() {
	global $wpdb;
	$t   = flxapp_table();
	$old = time() - 30 * DAY_IN_SECONDS;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE ( revoked = 1 AND created < %d ) OR ( expires > 0 AND expires < %d ) LIMIT 500", $old, $old ) ); // phpcs:ignore WordPress.DB
}

/** İstekteki belirteç (Bearer ya da X-FluxWP-Token). Yalnız bizim öneklerimiz. */
function flxapp_request_token() {
	$auth = (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( '' === $auth && function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $k => $v ) {
			if ( 'authorization' === strtolower( $k ) ) {
				$auth = (string) $v;
			}
		}
	}
	$token = 0 === stripos( $auth, 'Bearer ' ) ? trim( substr( $auth, 7 ) ) : trim( (string) wp_unslash( $_SERVER['HTTP_X_FLUXWP_TOKEN'] ?? $_SERVER['HTTP_X_FLX_TOKEN'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( 0 === strpos( $token, FLXAPP_PREFIX['access'] ) || 0 === strpos( $token, FLXAPP_PREFIX['key'] ) ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $token );
	}
	return '';
}

function flxapp_is_rest_request() {
	$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) || isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Bu istekte kullanılan belirteç kaydı (yoksa null). REST yanıtları ve /me için.
 */
function flxapp_current_token( $set = null ) {
	static $row = null;
	if ( null !== $set ) {
		$row = $set;
	}
	return $row;
}

add_filter(
	'determine_current_user',
	function ( $user_id ) {
		if ( $user_id || ! flxapp_is_rest_request() ) {
			return $user_id;
		}
		$token = flxapp_request_token();
		if ( '' === $token ) {
			return $user_id;
		}
		$row = flxapp_find( $token );
		if ( ! flxapp_is_live( $row ) || 'refresh' === $row['kind'] ) {
			return $user_id;
		}
		$user = get_userdata( (int) $row['user_id'] );
		if ( ! $user ) {
			return $user_id;
		}
		flxapp_current_token( $row );
		if ( time() - (int) $row['last_used'] > 300 ) {
			global $wpdb;
			$wpdb->update( flxapp_table(), array( 'last_used' => time(), 'last_ip' => flxapp_ip() ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( 1 === wp_rand( 1, 50 ) ) {
				flxapp_gc();
			}
		}
		return (int) $user->ID;
	},
	20
);

/*
 * Belirteçle gelen REST isteği çerez nonce'u istemez; WordPress bunu zaten yalnız çerez oturumunda arar.
 * Belirteç verilmiş ama geçersizse 401 dön ki istemci yenilemeyi denesin (sessizce anonim olmasın).
 */
add_filter(
	'rest_authentication_errors',
	function ( $result ) {
		if ( null !== $result && true !== $result ) {
			return $result;
		}
		if ( '' !== flxapp_request_token() && ! flxapp_current_token() ) {
			return new WP_Error( 'flxapp_invalid_token', __( 'Belirteç geçersiz ya da süresi dolmuş.', 'flx-app' ), array( 'status' => 401 ) );
		}
		return $result;
	},
	50
);

/** Kullanıcının kişisel API anahtarları ve oturumları (yönetim ekranı için). */
function flxapp_user_tokens( $user_id = 0, $kinds = array( 'key', 'refresh' ) ) {
	global $wpdb;
	$t    = flxapp_table();
	$in   = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );
	$args = $kinds;
	$sql  = "SELECT * FROM {$t} WHERE kind IN ({$in}) AND revoked = 0 AND ( expires = 0 OR expires > %d )";
	$args[] = time();
	if ( $user_id ) {
		$sql   .= ' AND user_id = %d';
		$args[] = (int) $user_id;
	}
	$sql .= ' ORDER BY id DESC LIMIT 500';
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/** Bir grant'ın son kullanım zamanı (erişim belirteçleri dahil). */
function flxapp_grant_last_used( $grant_id ) {
	global $wpdb;
	$t = flxapp_table();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(last_used) FROM {$t} WHERE grant_id = %s", $grant_id ) ); // phpcs:ignore WordPress.DB
}
