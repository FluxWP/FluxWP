<?php
/**
 * WhatsApp hesap yönetimi (Evolution API ve Flux WPAPI): QR / eşleştirme kodu, durum, yeniden başlatma, çıkış.
 * Her bağlantı bir instance (bir numara). Aynı sunucuya birden çok bağlantı eklenebilir.
 * Webhook'u bağlantıyı kullanan eklenti kurar (ör. Flux Chat); burada dokunulmaz.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Evolution v2 uçlarını konuşan bağlantı tipi mi (evolution, flxwpapi). */
function flxi_is_evo_type( $type ) {
	return ! empty( flxi_connection_types()[ $type ]['evo'] );
}

/** Durumu izlenen WhatsApp tipi mi (Evolution uyumlu ya da Cloud API). */
function flxi_is_wa_type( $type ) {
	return flxi_is_evo_type( $type ) || 'wacloud' === $type;
}

/** @return array|WP_Error Evolution uyumlu bağlantı. */
function flxi_wa_conn( $id ) {
	$c = flxi_get_connection( (int) $id );
	if ( ! $c || 'whatsapp' !== $c['channel'] || ! flxi_is_evo_type( $c['type'] ) ) {
		return new WP_Error( 'flxi_wa', __( 'WhatsApp connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
	}
	return $c;
}

function flxi_wa_inst( $c ) {
	return rawurlencode( (string) $c['config']['instance'] );
}

/** Sunucu hata gövdesinden okunur metin. */
function flxi_wa_error( $r ) {
	if ( $r['error'] ) {
		return __( 'Couldn\'t reach the server:', 'flx-mail' ) . ' ' . $r['error'];
	}
	$j   = is_array( $r['json'] ) ? $r['json'] : array();
	$msg = $j['response']['message'] ?? ( $j['message'] ?? ( $j['error'] ?? '' ) );
	if ( is_array( $msg ) ) {
		$msg = implode( ' ', array_map( 'strval', array_filter( $msg, 'is_scalar' ) ) );
	}
	if ( 401 === $r['code'] || 403 === $r['code'] ) {
		$msg = __( 'API key was not accepted.', 'flx-mail' );
	}
	return 'HTTP ' . $r['code'] . ( $msg ? ': ' . mb_substr( (string) $msg, 0, 240 ) : '' );
}

/** connect / create yanıtından QR ve eşleştirme kodu. */
function flxi_wa_parse_qr( $j ) {
	$j    = is_array( $j ) ? $j : array();
	$q    = isset( $j['qrcode'] ) && is_array( $j['qrcode'] ) ? $j['qrcode'] : $j;
	$b64  = (string) ( $q['base64'] ?? '' );
	$pair = (string) ( $q['pairingCode'] ?? '' );
	if ( $b64 && 0 !== strpos( $b64, 'data:image' ) ) {
		$b64 = 'data:image/png;base64,' . $b64;
	}
	$state = (string) ( $j['instance']['state'] ?? ( $j['instance']['status'] ?? '' ) );
	return array(
		'qr'      => preg_match( '~^data:image/(png|jpeg|svg\+xml);base64,[A-Za-z0-9+/=]+$~', $b64 ) ? $b64 : '',
		'pairing' => preg_replace( '/[^A-Z0-9-]/i', '', $pair ),
		'state'   => 'open' === $state ? 'open' : ( $state ? $state : 'connecting' ),
	);
}

/** Önbellekteki durum. */
function flxi_wa_cached_state( $id ) {
	$all = (array) get_option( 'flxi_wa_state', array() );
	return wp_parse_args( (array) ( $all[ (int) $id ] ?? array() ), array( 'state' => '', 'me' => '', 'name' => '', 'at' => 0, 'error' => '' ) );
}

function flxi_wa_store_state( $id, array $s ) {
	$all              = (array) get_option( 'flxi_wa_state', array() );
	$all[ (int) $id ] = array_merge( flxi_wa_cached_state( $id ), $s, array( 'at' => time() ) );
	update_option( 'flxi_wa_state', $all, false );
	return $all[ (int) $id ];
}

/**
 * Hesabın durumu: state (open | connecting | close | missing | error), me (numara), name (profil adı).
 * 60 sn önbellekli; $fresh ile sunucuya sorulur.
 */
function flxi_wa_state( $id, $fresh = false ) {
	$cached = flxi_wa_cached_state( $id );
	if ( ! $fresh && $cached['at'] > time() - 60 ) {
		return $cached;
	}
	$cl = flxi_get_connection( (int) $id );
	if ( $cl && 'wacloud' === $cl['type'] ) {
		return flxi_wa_store_state( $id, flxi_wacloud_state( $cl ) );
	}
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return array_merge( $cached, array( 'state' => 'error', 'error' => $c->get_error_message() ) );
	}
	$r = flxi_evolution_request( $c['config'], '/instance/connectionState/' . flxi_wa_inst( $c ), null, 'GET' );
	if ( 404 === $r['code'] ) {
		return flxi_wa_store_state( $id, array( 'state' => 'missing', 'me' => '', 'name' => '', 'error' => '' ) );
	}
	if ( ! $r['ok'] ) {
		return flxi_wa_store_state( $id, array( 'state' => 'error', 'error' => flxi_wa_error( $r ) ) );
	}
	$j     = is_array( $r['json'] ) ? $r['json'] : array();
	$state = (string) ( $j['instance']['state'] ?? ( $j['state'] ?? '' ) );
	$out   = array( 'state' => $state ? $state : 'close', 'error' => '' );
	if ( 'open' === $state && ( $fresh || ! $cached['me'] ) ) {
		$f = flxi_evolution_request( $c['config'], '/instance/fetchInstances?instanceName=' . flxi_wa_inst( $c ), null, 'GET' );
		foreach ( (array) ( is_array( $f['json'] ) ? $f['json'] : array() ) as $row ) {
			$row = isset( $row['instance'] ) && is_array( $row['instance'] ) ? $row['instance'] : $row; // v1 biçimi
			if ( ! is_array( $row ) || (string) ( $row['name'] ?? ( $row['instanceName'] ?? '' ) ) !== (string) $c['config']['instance'] ) {
				continue;
			}
			$jid         = (string) ( $row['ownerJid'] ?? ( $row['owner'] ?? '' ) );
			$out['me']   = $jid ? '+' . preg_replace( '/\D/', '', strtok( $jid, '@:' ) ) : '';
			$out['name'] = sanitize_text_field( (string) ( $row['profileName'] ?? '' ) );
		}
	} elseif ( 'open' !== $state ) {
		$out['me']   = '';
		$out['name'] = '';
	}
	return flxi_wa_store_state( $id, $out );
}

/** Genel API: hesabın durumu (diğer eklentiler için). */
function flxi_whatsapp_state( $id, $fresh = false ) {
	return array_intersect_key( flxi_wa_state( $id, $fresh ), array_flip( array( 'state', 'me', 'name', 'at' ) ) );
}

/**
 * QR / eşleştirme kodu ister; instance sunucuda yoksa oluşturur.
 *
 * @param string $number Telefonla eşleştirme için numara (boşsa QR).
 * @return array|WP_Error state, qr (data URI), pairing
 */
function flxi_wa_connect( $id, $number = '' ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$number = $number ? flxi_normalize_phone( $number ) : '';
	$q      = $number ? '?number=' . rawurlencode( $number ) : '';
	$r      = flxi_evolution_request( $c['config'], '/instance/connect/' . flxi_wa_inst( $c ) . $q, null, 'GET' );
	if ( 404 === $r['code'] ) {
		$body = array( 'instanceName' => (string) $c['config']['instance'], 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS' );
		if ( $number ) {
			$body['number'] = $number;
		}
		$r = flxi_evolution_request( $c['config'], '/instance/create', $body );
		if ( $r['ok'] ) {
			$p = flxi_wa_parse_qr( $r['json'] );
			if ( ! $p['qr'] && ! $p['pairing'] ) {
				$r = flxi_evolution_request( $c['config'], '/instance/connect/' . flxi_wa_inst( $c ) . $q, null, 'GET' );
			}
		}
	}
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	$p = flxi_wa_parse_qr( $r['json'] );
	flxi_wa_store_state( $id, array( 'state' => $p['state'], 'error' => '' ) );
	if ( 'open' === $p['state'] ) {
		$p = array_merge( $p, flxi_wa_state( $id, true ) );
	}
	return $p;
}

function flxi_wa_restart( $id ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$path = '/instance/restart/' . flxi_wa_inst( $c );
	$r    = flxi_evolution_request( $c['config'], $path, array() );
	if ( 404 === $r['code'] || 405 === $r['code'] ) { // Eski Evolution sürümleri PUT bekler.
		$r = flxi_evolution_request( $c['config'], $path, array(), 'PUT' );
	}
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	return flxi_wa_state( $id, true );
}

/** Telefondaki bağlı cihaz kaydını siler; numara yeniden QR ister. Instance sunucuda kalır. */
function flxi_wa_logout( $id ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$r = flxi_evolution_request( $c['config'], '/instance/logout/' . flxi_wa_inst( $c ), null, 'DELETE' );
	if ( ! $r['ok'] && 404 !== $r['code'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	return flxi_wa_store_state( $id, array( 'state' => 'close', 'me' => '', 'name' => '', 'error' => '' ) );
}

/** Sunucudaki instance'lar (aynı sunucuya hesap eklerken listelemek için). */
function flxi_wa_server_instances( $id ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$r = flxi_evolution_request( $c['config'], '/instance/fetchInstances', null, 'GET' );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	$used = array();
	foreach ( flxi_list_connections( 'whatsapp' ) as $o ) {
		if ( flxi_is_evo_type( $o['type'] ) && untrailingslashit( (string) $o['config']['base_url'] ) === untrailingslashit( (string) $c['config']['base_url'] ) ) {
			$used[ (string) $o['config']['instance'] ] = (int) $o['id'];
		}
	}
	$out = array();
	foreach ( (array) ( is_array( $r['json'] ) ? $r['json'] : array() ) as $row ) {
		$row  = isset( $row['instance'] ) && is_array( $row['instance'] ) ? $row['instance'] : $row;
		$name = is_array( $row ) ? (string) ( $row['name'] ?? ( $row['instanceName'] ?? '' ) ) : '';
		if ( '' === $name ) {
			continue;
		}
		$jid   = (string) ( $row['ownerJid'] ?? ( $row['owner'] ?? '' ) );
		$out[] = array(
			'name'  => sanitize_text_field( $name ),
			'state' => sanitize_key( (string) ( $row['connectionStatus'] ?? ( $row['status'] ?? '' ) ) ),
			'me'    => $jid ? '+' . preg_replace( '/\D/', '', strtok( $jid, '@:' ) ) : '',
			'used'  => $used[ $name ] ?? 0,
		);
	}
	return $out;
}

/**
 * Numaranın üye olduğu WhatsApp grupları (Evolution / Flux WPAPI). 10 dk önbellek.
 * Gönderim: flxi_send_message( 'whatsapp', $grup['id'], $metin, [ 'connection_id' => $id ] ).
 *
 * @param int  $id    bağlantı; 0 = varsayılan WhatsApp bağlantısı.
 * @param bool $fresh önbelleği atla.
 * @return array|WP_Error [ [ 'id' => '120363…@g.us', 'name', 'size', 'announce' ] ], ada göre sıralı.
 */
function flxi_whatsapp_groups( $id = 0, $fresh = false ) {
	if ( ! (int) $id ) {
		$def = flxi_resolve_connection( 'whatsapp', 0 );
		$id  = $def ? (int) $def['id'] : 0;
	}
	$any = flxi_get_connection( (int) $id );
	if ( $any && 'wacloud' === $any['type'] ) {
		return new WP_Error( 'flxi_wa_groups', flxi_wa_group_unsupported()['error'], array( 'status' => 400 ) );
	}
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$key = 'flxi_wa_groups_' . (int) $id;
	if ( ! $fresh ) {
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
	}
	$r = flxi_evolution_request( $c['config'], '/group/fetchAllGroups/' . flxi_wa_inst( $c ) . '?getParticipants=false', null, 'GET' );
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa_groups', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	$out = array();
	foreach ( (array) ( is_array( $r['json'] ) ? $r['json'] : array() ) as $g ) {
		$gid = is_array( $g ) ? (string) ( $g['id'] ?? '' ) : '';
		if ( ! flxi_is_wa_group( $gid ) ) {
			continue;
		}
		$out[] = array(
			'id'       => $gid,
			'name'     => sanitize_text_field( (string) ( $g['subject'] ?? '' ) ),
			'size'     => (int) ( $g['size'] ?? 0 ),
			'announce' => ! empty( $g['announce'] ),
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		}
	);
	set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

/* ---------------- Flux WPAPI sunucu ayarları (/flxw/server) ---------------- */

/** Sunucu bilgisi; parola gövdede gelse bile atılır. */
function flxi_wa_server_clean( $j ) {
	$j = is_array( $j ) ? $j : array();
	if ( isset( $j['db'] ) && is_array( $j['db'] ) ) {
		unset( $j['db']['password'], $j['db']['pass'] );
	}
	unset( $j['apiKey'], $j['api_key'], $j['password'], $j['token'] );
	if ( isset( $j['instances'] ) && is_array( $j['instances'] ) ) {
		foreach ( $j['instances'] as &$i ) {
			if ( is_array( $i ) ) {
				unset( $i['token'] );
			}
		}
		unset( $i );
	}
	return $j;
}

function flxi_wa_server_get( $id ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	if ( 'flxwpapi' !== $c['type'] ) {
		return new WP_Error( 'flxi_wa', __( 'Server settings exist only for Flux WPAPI connections.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	$r = flxi_evolution_request( $c['config'], '/flxw/server', null, 'GET' );
	if ( 404 === $r['code'] ) {
		return new WP_Error( 'flxi_wa', __( 'This Flux WPAPI version doesn\'t support changing server settings from WordPress. Update the bridge.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	return flxi_wa_server_clean( $r['json'] );
}

/**
 * @param array $in publicUrl, keepAlive, db (driver, host, port, database, user, password, prefix), test (yalnız veritabanını dene)
 */
function flxi_wa_server_save( $id, array $in ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	if ( 'flxwpapi' !== $c['type'] ) {
		return new WP_Error( 'flxi_wa', __( 'Server settings exist only for Flux WPAPI connections.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	$body = array();
	if ( isset( $in['publicUrl'] ) ) {
		$body['publicUrl'] = esc_url_raw( trim( (string) $in['publicUrl'] ) );
	}
	if ( isset( $in['keepAlive'] ) ) {
		$body['keepAlive'] = (bool) $in['keepAlive'];
	}
	if ( isset( $in['db'] ) && is_array( $in['db'] ) ) {
		$d  = $in['db'];
		$db = array(
			'driver'   => 'mysql' === ( $d['driver'] ?? '' ) ? 'mysql' : 'file',
			'host'     => sanitize_text_field( (string) ( $d['host'] ?? 'localhost' ) ),
			'port'     => max( 1, (int) ( $d['port'] ?? 3306 ) ),
			'database' => sanitize_text_field( (string) ( $d['database'] ?? '' ) ),
			'user'     => sanitize_text_field( (string) ( $d['user'] ?? '' ) ),
			'prefix'   => preg_replace( '/[^a-z0-9_]/i', '', (string) ( $d['prefix'] ?? 'flxw_' ) ),
		);
		if ( '' !== (string) ( $d['password'] ?? '' ) ) {
			$db['password'] = (string) $d['password']; // Yalnız köprüye gider; WordPress'te saklanmaz.
		}
		$body['db'] = $db;
	}
	$test = ! empty( $in['test'] );
	$r    = flxi_evolution_request( $c['config'], $test ? '/flxw/server/db/test' : '/flxw/server', $test ? array( 'db' => $body['db'] ?? array() ) : $body );
	if ( 409 === $r['code'] ) {
		return new WP_Error( 'flxi_wa', __( 'The database comes from the bridge\'s environment variables; it can\'t be changed here.', 'flx-mail' ), array( 'status' => 400 ) );
	}
	if ( ! $r['ok'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ), array( 'status' => 400 ) );
	}
	return flxi_wa_server_clean( $r['json'] );
}

/** Numarayı sunucudan siler (oturum, geçmiş, kişiler). WordPress'teki bağlantıyı silmez. */
function flxi_wa_delete_remote( $id ) {
	$c = flxi_wa_conn( $id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$r = flxi_evolution_request( $c['config'], '/instance/delete/' . flxi_wa_inst( $c ), null, 'DELETE' );
	if ( ! $r['ok'] && 404 !== $r['code'] ) {
		return new WP_Error( 'flxi_wa', flxi_wa_error( $r ) );
	}
	return true;
}

/* ---------------- REST ---------------- */

add_action(
	'rest_api_init',
	function () {
		$perm = function () {
			return flxi_can_manage();
		};
		$ns   = 'flxi/v1';
		$id   = '/wa/(?P<id>\d+)';
		register_rest_route( $ns, $id . '/state', array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wa_state( (int) $r['id'], ! empty( $r['fresh'] ) );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/connect', array( 'methods' => 'POST', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wa_connect( (int) $r['id'], sanitize_text_field( (string) $r['number'] ) );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/restart', array( 'methods' => 'POST', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wa_restart( (int) $r['id'] );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/logout', array( 'methods' => 'POST', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wa_logout( (int) $r['id'] );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/instances', array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_wa_server_instances( (int) $r['id'] );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/groups', array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
			return flxi_whatsapp_groups( (int) $r['id'], ! empty( $r['fresh'] ) );
		}, 'permission_callback' => $perm ) );
		register_rest_route( $ns, $id . '/server', array(
			array( 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
				return flxi_wa_server_get( (int) $r['id'] );
			}, 'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => function ( WP_REST_Request $r ) {
				return flxi_wa_server_save( (int) $r['id'], (array) $r->get_json_params() );
			}, 'permission_callback' => $perm ),
		) );
	}
);

add_action(
	'flxi_connection_deleted',
	function ( $id ) {
		$all = (array) get_option( 'flxi_wa_state', array() );
		unset( $all[ (int) $id ] );
		update_option( 'flxi_wa_state', $all, false );
		delete_transient( 'flxi_wa_groups_' . (int) $id );
	}
);
