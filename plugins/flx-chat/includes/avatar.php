<?php
/**
 * 1.47.0: Kişi profil fotoğrafları sunucuda önbelleklenir.
 *
 * - WhatsApp'ın fotoğraf adresi (pps.whatsapp.net, süresi dolan imzalı adres) tarayıcıya hiç gitmez;
 *   fotoğrafı sunucu indirir, uploads/flx-chat/avatars/<sohbet>-<hash>.<uzantı> olarak saklar,
 *   tarayıcı yalnız sitenin kendi adresinden (REST /chats/{id}/photo?v=<hash>) alır. Hash değişince adres değişir,
 *   o yüzden tarayıcı önbelleği uzun tutulabilir.
 * - Güncellik kontrolü sunucuda yapılır: sohbet açılınca (sohbet başına en sık 10 dk'da bir) ve
 *   sayfa açıkken arka planda küçük gruplar halinde (REST /avatars/check, istekte en fazla 6 sohbet, ~12 sn bütçe).
 * - chats.avatar sütunu WhatsApp'ın kaynak adresini (yalnız sunucuda) tutar, avatar_at son kontrol zamanıdır (0 = bekliyor).
 */

defined( 'ABSPATH' ) || exit;

/** Sohbet açılınca kontrol sıklığı (sn). */
function flxc_avatar_open_ttl() {
	return (int) apply_filters( 'flxc_avatar_open_ttl', 10 * MINUTE_IN_SECONDS );
}

/** Arka plan kontrolünde bir fotoğrafın "eski" sayılma süresi (sn). */
function flxc_avatar_stale_ttl() {
	return (int) apply_filters( 'flxc_avatar_stale_ttl', DAY_IN_SECONDS );
}

function flxc_avatar_dir() {
	$dir = flxc_media_dir() . '/avatars';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/**
 * Önbellekteki dosya. Klasör istek başına bir kez taranır (liste yüzlerce sohbet için çağırır).
 *
 * @return array|null path, hash, mime
 */
function flxc_avatar_file( $chat_id, $reset = false ) {
	static $map = null;
	if ( $reset ) {
		$map = null;
		return null;
	}
	if ( null === $map ) {
		$map  = array();
		$mime = array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$dir  = flxc_avatar_dir();
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( preg_match( '/^(\d+)-([a-f0-9]{10})\.(jpg|png|webp)$/', (string) $name, $m ) ) {
				$map[ (int) $m[1] ] = array( 'path' => $dir . '/' . $name, 'hash' => $m[2], 'mime' => $mime[ $m[3] ] );
			}
		}
	}
	return $map[ (int) $chat_id ] ?? null;
}

/** Tarayıcının kullanacağı adres (yalnız site); fotoğraf yoksa boş. */
function flxc_avatar_url( $chat ) {
	if ( empty( $chat['id'] ) ) {
		return '';
	}
	// Kanal eklentisi kendi avatarını verir (site sohbeti vb.: zaten site adresidir).
	$own = apply_filters( 'flxc_chat_avatar_url', null, $chat );
	if ( is_string( $own ) ) {
		return $own;
	}
	$f = flxc_avatar_file( $chat['id'] );
	if ( ! $f ) {
		return '';
	}
	static $nonce = null;
	if ( null === $nonce ) {
		$nonce = wp_create_nonce( 'wp_rest' );
	}
	return add_query_arg(
		array(
			'v'        => $f['hash'],
			'_wpnonce' => $nonce,
		),
		rest_url( 'flx-chat/v1/chats/' . (int) $chat['id'] . '/photo' )
	);
}

/** Bu sohbetin fotoğrafı WhatsApp'tan sorulabilir mi (Evolution hattı, birebir sohbet). */
function flxc_avatar_supported( $chat ) {
	return ! empty( $chat['conn'] ) && flxc_conn_can_revoke( $chat['conn'] ) && ! flxc_is_group_jid( (string) $chat['jid'] );
}

/**
 * Kaynak adresten fotoğrafı indirip saklar. Adres boşsa (kişi fotoğrafı kaldırdı / gizledi) önbellek silinir.
 *
 * @return bool|WP_Error Fotoğraf değiştiyse true.
 */
function flxc_avatar_store( $chat, $src ) {
	$id  = (int) $chat['id'];
	$old = flxc_avatar_file( $id );
	if ( '' === $src ) {
		if ( $old ) {
			wp_delete_file( $old['path'] );
			flxc_avatar_file( 0, true );
		}
		flxc_update_chat( $id, array( 'avatar' => '', 'avatar_at' => time() ) );
		return (bool) $old;
	}
	// WhatsApp aynı fotoğraf için yalnız imza parametrelerini değiştirir; dosya yolu aynıysa indirmeye gerek yok.
	if ( $old && strtok( (string) $chat['avatar'], '?' ) === strtok( $src, '?' ) ) {
		flxc_update_chat( $id, array( 'avatar' => esc_url_raw( $src ), 'avatar_at' => time() ) );
		return false;
	}
	$res = wp_safe_remote_get(
		$src,
		array(
			'timeout'             => 8,
			'limit_response_size' => 3 * MB_IN_BYTES,
			'redirection'         => 2,
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$bin = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || '' === $bin ) {
		return new WP_Error( 'flxc_avatar', 'HTTP ' . (int) wp_remote_retrieve_response_code( $res ) );
	}
	$info = @getimagesizefromstring( $bin ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$exts = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
	if ( ! $info || empty( $exts[ $info['mime'] ] ) ) {
		return new WP_Error( 'flxc_avatar', 'Not an image' );
	}
	$hash = substr( sha1( $bin ), 0, 10 );
	if ( $old && $old['hash'] === $hash ) {
		flxc_update_chat( $id, array( 'avatar' => esc_url_raw( $src ), 'avatar_at' => time() ) );
		return false;
	}
	$path = flxc_avatar_dir() . '/' . $id . '-' . $hash . '.' . $exts[ $info['mime'] ];
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === file_put_contents( $path, $bin ) ) {
		return new WP_Error( 'flxc_avatar', 'Save failed' );
	}
	if ( $old && $old['path'] !== $path ) {
		wp_delete_file( $old['path'] );
	}
	flxc_avatar_file( 0, true );
	flxc_update_chat( $id, array( 'avatar' => esc_url_raw( $src ), 'avatar_at' => time() ) );
	return true;
}

/**
 * WhatsApp'tan güncel fotoğrafı sorar ve önbelleği yeniler (yalnız sunucuda).
 *
 * @param int $ttl Son kontrolden bu kadar saniye geçmediyse hiçbir şey yapılmaz (0 = hemen).
 * @return bool Fotoğraf değiştiyse true.
 */
function flxc_avatar_check( $chat, $ttl = 0 ) {
	if ( ! flxc_avatar_supported( $chat ) ) {
		return false;
	}
	$has = (bool) flxc_avatar_file( $chat['id'] );
	// Kaynak adres bilinip dosya henüz indirilmediyse (geçmiş içe aktarma), önce onu dene.
	if ( ! $has && '' !== (string) $chat['avatar'] && 0 === (int) $chat['avatar_at'] ) {
		$r = flxc_avatar_store( $chat, (string) $chat['avatar'] );
		if ( true === $r ) {
			return true;
		}
	}
	if ( $ttl && time() - (int) $chat['avatar_at'] < $ttl ) {
		return false;
	}
	// Aynı sohbet için eşzamanlı iki kontrol olmasın.
	$lock = 'flxc_avl_' . (int) $chat['id'];
	if ( get_transient( $lock ) ) {
		return false;
	}
	set_transient( $lock, 1, 30 );
	$r = flxc_evo( $chat['conn'], 'POST', '/chat/fetchProfilePictureUrl/{instance}', array( 'number' => flxc_chat_number( $chat ) ), 8 );
	if ( ! $r['ok'] ) {
		// Hat erişilemiyorsa fotoğrafa dokunma; bir sonraki turda yeniden denensin diye zamanı yine de işaretle.
		flxc_update_chat( $chat['id'], array( 'avatar_at' => time() ) );
		delete_transient( $lock );
		return false;
	}
	$src = (string) ( $r['json']['profilePictureUrl'] ?? '' );
	$res = flxc_avatar_store( $chat, $src );
	if ( is_wp_error( $res ) ) {
		flxc_update_chat( $chat['id'], array( 'avatar_at' => time() ) );
		$res = false;
	}
	delete_transient( $lock );
	return $res;
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$use = function () {
			return flxc_can_use();
		};
		register_rest_route( 'flx-chat/v1', '/chats/(?P<id>\d+)/photo', array( 'methods' => 'GET', 'callback' => 'flxc_rest_avatar_photo', 'permission_callback' => $use ) );
		register_rest_route( 'flx-chat/v1', '/avatars/check', array( 'methods' => 'POST', 'callback' => 'flxc_rest_avatars_check', 'permission_callback' => $use ) );
	}
);

/** Önbellekteki fotoğrafı verir. Adres hash içerdiği için tarayıcı uzun süre önbellekleyebilir. */
function flxc_rest_avatar_photo( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$f = flxc_avatar_file( $chat['id'] );
	if ( ! $f ) {
		return new WP_Error( 'flxc_avatar', 'Not found', array( 'status' => 404 ) );
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	status_header( 200 );
	header( 'Content-Type: ' . $f['mime'] );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . filesize( $f['path'] ) );
	header( 'Cache-Control: private, max-age=31536000, immutable' );
	header( 'Content-Disposition: inline; filename="' . (int) $chat['id'] . '.' . pathinfo( $f['path'], PATHINFO_EXTENSION ) . '"' );
	readfile( $f['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/**
 * Arka plan kontrolü: tarayıcı listede gördüğü sohbet id'lerini yollar, sunucu en eskilerinden birkaçını kontrol eder.
 * Hafif tutulur: istek başına en fazla 6 sohbet ve ~12 sn; kullanıcı başına 20 sn'de bir istek.
 */
function flxc_rest_avatars_check( WP_REST_Request $req ) {
	$key = 'flxc_avq_' . get_current_user_id();
	if ( get_transient( $key ) ) {
		return array( 'updated' => (object) array(), 'more' => true, 'wait' => 20 );
	}
	set_transient( $key, 1, 20 );
	$ids = array_slice( array_unique( array_filter( array_map( 'intval', (array) $req->get_param( 'ids' ) ) ) ), 0, 200 );
	if ( ! $ids ) {
		return array( 'updated' => (object) array(), 'more' => false );
	}
	global $wpdb;
	$t     = flxc_table( 'chats' );
	$in    = implode( ',', $ids );
	$stale = time() - flxc_avatar_stale_ttl();
	// phpcs:ignore WordPress.DB.PreparedSQL
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE id IN ($in) AND avatar_at < %d ORDER BY avatar_at ASC, last_ts DESC LIMIT 7", $stale ), ARRAY_A );
	$out   = array();
	$start = microtime( true );
	$n     = 0;
	foreach ( $rows as $chat ) {
		if ( $n >= 6 || microtime( true ) - $start > 12 ) {
			break;
		}
		++$n;
		if ( ! flxc_user_can_see_chat( $chat ) || ! flxc_avatar_supported( $chat ) ) {
			flxc_update_chat( $chat['id'], array( 'avatar_at' => time() ) );
			continue;
		}
		if ( flxc_avatar_check( $chat, 0 ) ) {
			$out[ (int) $chat['id'] ] = flxc_avatar_url( flxc_get_chat( $chat['id'] ) );
		}
	}
	return array(
		'updated' => (object) $out,
		'more'    => count( $rows ) > $n,
	);
}
