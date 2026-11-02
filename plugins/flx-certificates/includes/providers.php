<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sağlayıcı tipleri. Yeni tip eklemek için 'flxs_provider_types' filtresi:
 * channel, label, fields (panel formu), send(callable $config, $msg) ve test(callable $config, $to).
 */
function flxs_provider_types() {
	static $types = null;
	if ( null === $types ) {
		$types = apply_filters( 'flxs_provider_types', array() );
	}
	return $types;
}

function flxs_get_provider( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'providers' ) . ' WHERE id = %d', $id ), ARRAY_A );
	if ( $row ) {
		$row['config'] = flxs_decode( $row['config'] );
	}
	return $row;
}

function flxs_list_providers( $channel = '', $only_active = false ) {
	global $wpdb;
	$where = array( '1=1' );
	$prm   = array();
	if ( $channel ) {
		$where[] = 'channel = %s';
		$prm[]   = $channel;
	}
	if ( $only_active ) {
		$where[] = 'active = 1';
	}
	$sql  = 'SELECT * FROM ' . flxs_table( 'providers' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY channel, id';
	$rows = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A );
	foreach ( $rows as &$r ) {
		$r['config'] = flxs_decode( $r['config'] );
	}
	return $rows;
}

/** E-postada sağlayıcı yoksa WordPress'in kendi wp_mail'i kullanılır (sanal sağlayıcı #0). */
function flxs_wpmail_provider() {
	return array(
		'id'      => 0,
		'type'    => 'wpmail',
		'channel' => 'email',
		'name'    => 'WordPress (wp_mail)',
		'config'  => array(),
		'active'  => 1,
	);
}

/** WhatsApp/SMS bağlantıları Flux Mail'de tutulur (v1.3.0); bu eklentide ayrı sağlayıcı ekranı yok. */
function flxs_iletisim_active() {
	return function_exists( 'flxi_send_email' ) && function_exists( 'flxi_list_connections' );
}

/**
 * Flux Mail bağlantılarını sertifika gönderimine bağlar: her etkin WhatsApp/SMS bağlantısı için bir
 * "flxi_*" köprü satırı tutulur (gönderimi Flux Mail'in flxs_provider_types köprüsü yapar). Eski
 * Evolution/Netgsm satırları kullanılmaz. Bağlantı listesi değişmedikçe hiçbir şey yazılmaz.
 */
function flxs_sync_flxi_providers( $force = false ) {
	static $done = false;
	if ( ( $done && ! $force ) || ! flxs_iletisim_active() ) {
		return;
	}
	$done  = true;
	$conns = array();
	foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
		foreach ( (array) flxi_list_connections( $ch, true ) as $c ) {
			$conns[] = array( 'id' => (int) $c['id'], 'channel' => $ch, 'name' => (string) $c['name'], 'default' => (int) ( $c['is_default'] ?? 0 ) );
		}
	}
	$sig = md5( wp_json_encode( $conns ) );
	if ( ! $force && get_option( 'flxs_flxi_sync' ) === $sig ) {
		return;
	}
	global $wpdb;
	$t     = flxs_table( 'providers' );
	$rows  = flxs_list_providers();
	$byc   = array();
	$keep  = array();
	$def   = (array) flxs_setting( 'default_provider' );
	$first = array();
	foreach ( $rows as $r ) {
		if ( 0 === strpos( $r['type'], 'flxi_' ) && '' !== (string) ( $r['config']['connection'] ?? '' ) ) {
			$byc[ (int) $r['config']['connection'] ] = $r;
		}
	}
	foreach ( $conns as $c ) {
		$r = $byc[ $c['id'] ] ?? null;
		if ( $r && $r['channel'] === $c['channel'] ) {
			$id = (int) $r['id'];
			if ( ! $r['active'] || $r['name'] !== $c['name'] ) {
				$wpdb->update( $t, array( 'active' => 1, 'name' => $c['name'], 'updated_at' => flxs_now() ), array( 'id' => $id ) );
			}
		} else {
			$cfg = array( 'connection' => (string) $c['id'], 'delay' => 'whatsapp' === $c['channel'] ? 4 : 0 );
			$wpdb->insert( $t, array( 'type' => 'flxi_' . $c['channel'], 'channel' => $c['channel'], 'name' => $c['name'], 'config' => flxs_json( $cfg ), 'active' => 1, 'created_by' => 0, 'created_at' => flxs_now(), 'updated_at' => flxs_now() ) );
			$id = (int) $wpdb->insert_id;
		}
		$keep[] = $id;
		if ( ! isset( $first[ $c['channel'] ] ) || $c['default'] ) {
			$first[ $c['channel'] ] = $id; // Flux Mail'deki varsayılan bağlantı (liste varsayılanla başlar)
		}
	}
	// Bağlantısı kalmayan köprüler ve eski WhatsApp/SMS satırları kapatılır (silinmez: kuyruktaki eski mesajlar ve diğer eklentiler okuyabilir).
	foreach ( $rows as $r ) {
		if ( in_array( $r['channel'], array( 'whatsapp', 'sms' ), true ) && $r['active'] && ! in_array( (int) $r['id'], $keep, true ) ) {
			$wpdb->update( $t, array( 'active' => 0, 'updated_at' => flxs_now() ), array( 'id' => (int) $r['id'] ) );
		}
	}
	foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
		$def[ $ch ] = $first[ $ch ] ?? 0;
	}
	$s                     = (array) get_option( 'flxs_settings', array() );
	$s['default_provider'] = $def;
	update_option( 'flxs_settings', $s, false );
	update_option( 'flxs_flxi_sync', $sig, false );
}
add_action( 'admin_init', 'flxs_sync_flxi_providers' );

/** Bir Flux Mail bağlantısına bağlı köprü satırı mı (bağlantısı boş eski köprüler sayılmaz) */
function flxs_is_bridge_provider( $p ) {
	return 0 === strpos( (string) $p['type'], 'flxi_' ) && '' !== (string) ( $p['config']['connection'] ?? '' );
}

/** Kanal için kullanılacak sağlayıcı: verilen id > varsayılan > ilk aktif. WhatsApp/SMS yalnızca Flux Mail üzerinden. */
function flxs_resolve_provider( $channel, $provider_id = 0 ) {
	if ( in_array( $channel, array( 'whatsapp', 'sms' ), true ) ) {
		if ( ! flxs_iletisim_active() ) {
			return null;
		}
		flxs_sync_flxi_providers();
	}
	$ok = function ( $p ) use ( $channel ) {
		return $p && $p['active'] && $p['channel'] === $channel && ( 'email' === $channel || flxs_is_bridge_provider( $p ) );
	};
	if ( $provider_id ) {
		$p = flxs_get_provider( $provider_id );
		if ( $ok( $p ) ) {
			return $p;
		}
	}
	$def = (array) flxs_setting( 'default_provider' );
	if ( ! empty( $def[ $channel ] ) ) {
		$p = flxs_get_provider( (int) $def[ $channel ] );
		if ( $ok( $p ) ) {
			return $p;
		}
	}
	foreach ( flxs_list_providers( $channel, true ) as $p ) {
		if ( $ok( $p ) ) {
			return $p;
		}
	}
	return 'email' === $channel ? flxs_wpmail_provider() : null;
}

function flxs_provider_options_for_js() {
	$out = array();
	foreach ( array_keys( flxs_channels() ) as $ch ) {
		$out[ $ch ] = array();
		if ( 'email' === $ch ) {
			$out[ $ch ][] = array( 'id' => 0, 'name' => flxs_iletisim_active() ? __( 'Flux Mail (kurumsal şablon)', 'flx-certificates' ) : 'WordPress (wp_mail)' );
		}
		if ( 'email' !== $ch ) {
			if ( ! flxs_iletisim_active() ) {
				continue;
			}
			flxs_sync_flxi_providers();
		}
		foreach ( flxs_list_providers( $ch, true ) as $p ) {
			if ( 'email' === $ch || flxs_is_bridge_provider( $p ) ) {
				$out[ $ch ][] = array( 'id' => (int) $p['id'], 'name' => $p['name'] );
			}
		}
	}
	return $out;
}

/**
 * Kaydet. Boş bırakılan gizli alanlar (şifre/key) eski değerini korur.
 */
function flxs_save_provider( $id, $type, $name, array $input, $active ) {
	global $wpdb;
	$types = flxs_provider_types();
	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'flxs_type', 'Bilinmeyen sağlayıcı tipi.' );
	}
	$def    = $types[ $type ];
	$old    = $id ? flxs_get_provider( $id ) : null;
	$config = array();
	foreach ( $def['fields'] as $f ) {
		$k = $f['key'];
		$v = $input[ $k ] ?? '';
		if ( 'password' === $f['type'] ) {
			$v = trim( (string) $v );
			if ( '' === $v && $old ) {
				$v = $old['config'][ $k ] ?? '';
			}
		} elseif ( 'checkbox' === $f['type'] ) {
			$v = empty( $v ) ? 0 : 1;
		} elseif ( 'number' === $f['type'] ) {
			$v = is_numeric( $v ) ? $v + 0 : ( $f['default'] ?? 0 );
		} elseif ( 'url' === $f['type'] ) {
			$v = esc_url_raw( trim( (string) $v ) );
		} elseif ( 'textarea' === $f['type'] ) {
			$v = sanitize_textarea_field( $v );
		} else {
			$v = sanitize_text_field( $v );
		}
		if ( ! empty( $f['required'] ) && '' === (string) $v ) {
			return new WP_Error( 'flxs_required', $f['label'] . ' gerekli.' );
		}
		$config[ $k ] = $v;
	}
	$data = array(
		'type'       => $type,
		'channel'    => $def['channel'],
		'name'       => sanitize_text_field( $name ) ? sanitize_text_field( $name ) : $def['label'],
		'config'     => flxs_json( $config ),
		'active'     => $active ? 1 : 0,
		'updated_at' => flxs_now(),
	);
	if ( $id ) {
		$wpdb->update( flxs_table( 'providers' ), $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = flxs_now();
		$data['created_by'] = get_current_user_id();
		$wpdb->insert( flxs_table( 'providers' ), $data );
		$id = (int) $wpdb->insert_id;
	}
	flxs_log( 'provider_saved', 'provider', $id, $data['name'] . ' (' . $def['label'] . ')', array( 'config' => $config, 'active' => $data['active'] ) );
	return $id;
}

function flxs_delete_provider( $id ) {
	global $wpdb;
	$p = flxs_get_provider( $id );
	$wpdb->delete( flxs_table( 'providers' ), array( 'id' => $id ) );
	flxs_log( 'provider_deleted', 'provider', $id, $p ? $p['name'] : '' );
}

/**
 * Tek mesajı gönderir.
 * @return array ok, external_id, response, error, retry (geçici hata mı)
 */
function flxs_provider_send( $provider, $msg ) {
	$types = flxs_provider_types();
	$type  = $provider['type'];
	if ( ! isset( $types[ $type ] ) ) {
		return array( 'ok' => false, 'error' => 'Sağlayıcı tipi yok: ' . $type, 'retry' => false );
	}
	try {
		$r = call_user_func( $types[ $type ]['send'], (array) $provider['config'], $msg, $provider );
	} catch ( Throwable $e ) {
		$r = array( 'ok' => false, 'error' => 'Hata: ' . $e->getMessage(), 'retry' => true );
	}
	return wp_parse_args( $r, array( 'ok' => false, 'external_id' => '', 'response' => null, 'error' => '', 'retry' => true ) );
}

/** HTTP isteği; yanıtı loglanabilir biçimde döner. */
function flxs_http( $method, $url, $args = array() ) {
	$args = wp_parse_args( $args, array( 'timeout' => 20, 'method' => $method ) );
	$res  = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	return array(
		'ok'    => $code >= 200 && $code < 300,
		'code'  => $code,
		'body'  => mb_substr( $body, 0, 4000 ),
		'json'  => json_decode( $body, true ),
		'error' => '',
	);
}
