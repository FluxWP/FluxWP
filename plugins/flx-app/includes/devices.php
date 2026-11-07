<?php
/**
 * Cihazlar (masaüstü ve mobil). Her cihaz bir OAuth oturumuna (grant) bağlıdır; oturum kapanınca (çıkış, iptal,
 * parola sıfırlama, kullanıcı silme) kayıt ve push belirteci silinir. Kullanıcı cihazlarını Kullanıcılar ›
 * Uygulama erişimi sayfasında görür ve kaldırır.
 *
 *   POST   /devices        bu cihazı kaydeder/günceller: { token?, provider?, platform, brand, app_version, locale,
 *                          name?, model?, device_id?, events? } → cihaz (`id`). Giriş sonrası ve her açılışta.
 *   GET    /devices        kullanıcının cihazları (bu cihaz `current: true`)
 *   DELETE /devices/<id>   cihazın oturumunu kapatır ve kaydı siler (POST + X-HTTP-Method-Override: DELETE da olur)
 *
 * Push belirteci veritabanında şifreli (`token_enc`) ve özetli (`token_hash`) durur; hiçbir uçta geri dönmez.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_DEVICE_PLATFORMS = array( 'ios', 'android', 'macos', 'windows', 'linux', 'web' );

/** Bu istekteki OAuth oturumu (cihaz kaydı için şart). */
function flxapp_current_grant() {
	$tok = flxapp_current_token();
	return $tok && '' !== (string) $tok['grant_id'] ? $tok : null;
}

/** Gelen cihaz bilgisini temizler; yalnız gönderilen alanlar döner. */
function flxapp_device_clean( array $b ) {
	$out = array();
	$txt = array( 'name' => 190, 'model' => 100, 'app_version' => 30 );
	foreach ( $txt as $k => $len ) {
		if ( isset( $b[ $k ] ) ) {
			$out[ $k ] = mb_substr( sanitize_text_field( (string) $b[ $k ] ), 0, $len );
		}
	}
	if ( isset( $b['platform'] ) ) {
		$p               = sanitize_key( (string) $b['platform'] );
		$out['platform'] = in_array( $p, FLXAPP_DEVICE_PLATFORMS, true ) ? $p : '';
	}
	if ( isset( $b['brand'] ) ) {
		$out['brand'] = substr( sanitize_key( (string) $b['brand'] ), 0, 100 );
	}
	if ( isset( $b['locale'] ) ) {
		$out['locale'] = substr( preg_replace( '/[^A-Za-z_\-]/', '', (string) $b['locale'] ), 0, 20 );
	}
	if ( isset( $b['device_id'] ) ) {
		$out['device_id'] = substr( preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $b['device_id'] ), 0, 64 );
	}
	if ( isset( $b['events'] ) && is_array( $b['events'] ) ) {
		$ev = array();
		foreach ( $b['events'] as $k => $v ) {
			$ev[ preg_replace( '/[^a-z0-9_.\-]/', '', strtolower( (string) $k ) ) ] = (bool) $v;
		}
		$out['events'] = wp_json_encode( $ev );
	}
	return $out;
}

/**
 * Push belirteci: Expo (`ExponentPushToken[…]`) ya da ham FCM/APNs. Boş = bildirim kapalı.
 *
 * @return array|null [ token, provider ] ya da tanınmadıysa null
 */
function flxapp_push_clean( $token, $provider = '' ) {
	$token    = substr( trim( (string) $token ), 0, 255 );
	$provider = sanitize_key( (string) $provider );
	if ( '' === $token ) {
		return array( '', '' );
	}
	if ( '' === $provider ) {
		$provider = preg_match( '/^Expo(nent)?PushToken\[[^\]]+\]$/', $token ) ? 'expo' : '';
	}
	if ( ! in_array( $provider, array( 'expo', 'fcm', 'apns' ), true ) || preg_match( '/[^A-Za-z0-9_\-:\[\].]/', $token ) ) {
		return null;
	}
	return array( $token, $provider );
}

function flxapp_device_get( $id ) {
	global $wpdb;
	$t = flxapp_devices_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxapp_device_by_grant( $grant ) {
	global $wpdb;
	$t = flxapp_devices_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE grant_id = %s LIMIT 1", (string) $grant ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/**
 * Bu oturumun cihazını kaydeder ya da günceller. Aynı kullanıcının aynı `device_id`'li eski kaydı (önceki giriş)
 * kaldırılır, eski oturumu da kapanır.
 *
 * @return array|WP_Error satır
 */
function flxapp_device_save( $user_id, array $tok, array $data ) {
	global $wpdb;
	$t   = flxapp_devices_table();
	$now = time();
	$row = flxapp_device_by_grant( (string) $tok['grant_id'] );
	if ( ! empty( $data['device_id'] ) ) {
		$old = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d AND device_id = %s AND grant_id <> %s", (int) $user_id, $data['device_id'], (string) $tok['grant_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $old as $o ) {
			flxapp_device_revoke( $o );
		}
	}
	if ( isset( $data['token'] ) ) {
		$data['token_hash'] = '' === $data['token'] ? '' : flxapp_hash( $data['token'] );
		$data['token_enc']  = flxapp_encrypt( $data['token'] );
		if ( '' !== $data['token_hash'] ) {
			// Aynı belirteç başka kullanıcıda kalmasın (cihazda hesap değişti).
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET token_hash = '', token_enc = '', provider = '' WHERE token_hash = %s AND grant_id <> %s", $data['token_hash'], (string) $tok['grant_id'] ) ); // phpcs:ignore WordPress.DB
		}
		unset( $data['token'] );
	}
	$data['last_seen'] = $now;
	if ( $row ) {
		$wpdb->update( $t, $data, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$id = (int) $row['id'];
	} else {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$t,
			$data + array(
				'user_id'   => (int) $user_id,
				'grant_id'  => (string) $tok['grant_id'],
				'client_id' => (string) $tok['client_id'],
				'created'   => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		/**
		 * Yeni cihaz.
		 *
		 * @param int $id
		 * @param int $user_id
		 */
		do_action( 'flxapp_device_registered', $id, (int) $user_id );
	}
	return flxapp_device_get( $id );
}

/** Oturumu açık cihazlar. $user_id 0 = herkes (yönetici ekranı). */
function flxapp_user_devices( $user_id = 0 ) {
	global $wpdb;
	$d    = flxapp_devices_table();
	$t    = flxapp_table();
	$sql  = "SELECT d.* FROM {$d} d WHERE EXISTS ( SELECT 1 FROM {$t} k WHERE k.grant_id = d.grant_id AND k.kind = 'refresh' AND k.revoked = 0 AND k.expires > %d )";
	$args = array( time() );
	if ( $user_id ) {
		$sql   .= ' AND d.user_id = %d';
		$args[] = (int) $user_id;
	}
	$sql .= ' ORDER BY d.last_seen DESC LIMIT 500';
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function flxapp_platform_label( $p ) {
	$l = array(
		'ios'     => 'iOS',
		'android' => 'Android',
		'macos'   => 'macOS',
		'windows' => 'Windows',
		'linux'   => 'Linux',
		'web'     => __( 'Web', 'flx-app' ),
	);
	return $l[ $p ] ?? $p;
}

function flxapp_device_out( array $r ) {
	$cur    = flxapp_current_grant();
	$client = flxapp_client( (string) $r['client_id'] );
	return array(
		'id'              => (int) $r['id'],
		'name'            => (string) $r['name'],
		'platform'        => (string) $r['platform'],
		'platform_label'  => flxapp_platform_label( (string) $r['platform'] ),
		'model'           => (string) $r['model'],
		'brand'           => (string) $r['brand'],
		'app'             => $client ? (string) $client['name'] : (string) $r['client_id'],
		'app_version'     => (string) $r['app_version'],
		'locale'          => (string) $r['locale'],
		'push'            => '' !== (string) $r['token_hash'],
		'events'          => (object) (array) json_decode( (string) $r['events'], true ),
		'created'         => (int) $r['created'],
		'last_seen'       => (int) $r['last_seen'],
		'last_seen_label' => $r['last_seen'] ? flx_time_ago( (int) $r['last_seen'] ) : '',
		'current'         => $cur && (string) $cur['grant_id'] === (string) $r['grant_id'],
	);
}

/** Cihazın oturumunu kapatır ve kaydı siler. */
function flxapp_device_revoke( array $row ) {
	global $wpdb;
	$wpdb->delete( flxapp_devices_table(), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	if ( '' !== (string) $row['grant_id'] ) {
		flxapp_revoke_grant( (string) $row['grant_id'] );
	}
}

add_action(
	'flxapp_grant_revoked',
	function ( $grant_id ) {
		global $wpdb;
		if ( '' !== (string) $grant_id ) {
			$wpdb->delete( flxapp_devices_table(), array( 'grant_id' => (string) $grant_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
);
add_action(
	'flxapp_user_revoked',
	function ( $user_id ) {
		global $wpdb;
		$wpdb->delete( flxapp_devices_table(), array( 'user_id' => (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
);

/* ───────── REST ───────── */

function flxapp_grant_permission() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', __( 'Giriş gerekli.', 'flx-app' ), array( 'status' => 401 ) );
	}
	return flxapp_current_grant() ? true : new WP_Error( 'flxapp_no_grant', __( 'Bu işlem yalnız uygulama oturumuyla (OAuth) yapılır.', 'flx-app' ), array( 'status' => 403 ) );
}

function flxapp_rest_device_save( WP_REST_Request $req ) {
	$b    = (array) $req->get_json_params() + (array) $req->get_body_params();
	$data = flxapp_device_clean( $b );
	if ( array_key_exists( 'token', $b ) ) {
		$p = flxapp_push_clean( $b['token'], $b['provider'] ?? '' );
		if ( null === $p ) {
			return new WP_Error( 'flxapp_push_token', __( 'Push belirteci tanınmadı.', 'flx-app' ), array( 'status' => 400 ) );
		}
		list( $data['token'], $data['provider'] ) = $p;
	}
	$row = flxapp_device_save( get_current_user_id(), flxapp_current_grant(), $data );
	return is_wp_error( $row ) ? $row : flxapp_device_out( $row );
}

function flxapp_rest_devices() {
	return array( 'items' => array_map( 'flxapp_device_out', flxapp_user_devices( get_current_user_id() ) ) );
}

function flxapp_rest_device_delete( WP_REST_Request $req ) {
	$row = flxapp_device_get( (int) $req['id'] );
	if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
		return new WP_Error( 'flxapp_not_found', __( 'Cihaz bulunamadı.', 'flx-app' ), array( 'status' => 404 ) );
	}
	flxapp_device_revoke( $row );
	return array( 'ok' => true );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flxapp/v1',
			'/devices',
			array(
				array( 'methods' => 'GET', 'callback' => 'flxapp_rest_devices', 'permission_callback' => 'flxapp_logged_in' ),
				array( 'methods' => 'POST', 'callback' => 'flxapp_rest_device_save', 'permission_callback' => 'flxapp_grant_permission' ),
			)
		);
		register_rest_route( 'flxapp/v1', '/devices/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => 'flxapp_rest_device_delete', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);
