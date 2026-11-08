<?php
/**
 * WhatsApp ve SMS bağlantıları (tek yerde). Tipler `flxi_connection_types` filtresiyle eklenir:
 * channel, label, help, fields, send( $config, $msg, $conn ), test( $config, $to, $conn ).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_channels() {
	return array(
		'email'    => __( 'Email', 'flx-mail' ),
		'whatsapp' => 'WhatsApp',
		'sms'      => 'SMS',
	);
}

function flxi_connection_types() {
	static $types = null;
	if ( null === $types ) {
		$types = (array) apply_filters( 'flxi_connection_types', array() );
	}
	return $types;
}

function flxi_prepare_connection( $row ) {
	if ( ! $row ) {
		return null;
	}
	$cfg = json_decode( (string) $row['config'], true );
	$cfg = is_array( $cfg ) ? $cfg : array();
	// Gizli alanlar şifreli saklanır.
	$type = flxi_connection_types()[ $row['type'] ] ?? null;
	if ( $type ) {
		foreach ( $type['fields'] as $f ) {
			if ( 'password' === $f['type'] && isset( $cfg[ $f['key'] ] ) ) {
				$cfg[ $f['key'] ] = flxi_decrypt( $cfg[ $f['key'] ] );
			}
		}
	}
	$row['config']     = $cfg;
	$row['id']         = (int) $row['id'];
	$row['active']     = (int) $row['active'];
	$row['is_default'] = (int) $row['is_default'];
	return $row;
}

function flxi_get_connection( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'connections' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return flxi_prepare_connection( $row );
}

function flxi_list_connections( $channel = '', $only_active = false ) {
	global $wpdb;
	$sql = 'SELECT * FROM ' . flxi_table( 'connections' ) . ' WHERE 1=1';
	$prm = array();
	if ( $channel ) {
		$sql  .= ' AND channel = %s';
		$prm[] = $channel;
	}
	if ( $only_active ) {
		$sql .= ' AND active = 1';
	}
	$sql .= ' ORDER BY channel, is_default DESC, id';
	$rows = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ); // phpcs:ignore
	return array_map( 'flxi_prepare_connection', (array) $rows );
}

/** Kanal için kullanılacak bağlantı: verilen id > varsayılan > ilk aktif. */
function flxi_resolve_connection( $channel, $id = 0 ) {
	if ( $id ) {
		$c = flxi_get_connection( $id );
		if ( $c && $c['channel'] === $channel && $c['active'] ) {
			return $c;
		}
	}
	$list = flxi_list_connections( $channel, true );
	return $list ? $list[0] : null;
}

/**
 * Kaydet. Boş bırakılan gizli alanlar eski değerini korur.
 *
 * @return int|WP_Error
 */
function flxi_save_connection( $id, $type, $name, array $input, $active, $is_default = null ) {
	global $wpdb;
	$types = flxi_connection_types();
	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'flxi_type', __( 'Unknown connection type.', 'flx-mail' ) );
	}
	$def    = $types[ $type ];
	$old    = $id ? flxi_get_connection( $id ) : null;
	$config = array();
	foreach ( $def['fields'] as $f ) {
		$k = $f['key'];
		$v = $input[ $k ] ?? ( $f['default'] ?? '' );
		switch ( $f['type'] ) {
			case 'password':
				$v = trim( (string) $v );
				if ( '' === $v && $old ) {
					$v = $old['config'][ $k ] ?? '';
				}
				break;
			case 'checkbox':
				$v = empty( $input[ $k ] ) ? 0 : 1;
				break;
			case 'number':
				$v = is_numeric( $v ) ? $v + 0 : ( $f['default'] ?? 0 );
				break;
			case 'url':
				$v = esc_url_raw( trim( (string) $v ) );
				break;
			case 'select':
				$v = array_key_exists( (string) $v, $f['options'] ) ? (string) $v : (string) ( $f['default'] ?? '' );
				break;
			default:
				$v = sanitize_text_field( (string) $v );
		}
		if ( ! empty( $f['required'] ) && '' === (string) $v ) {
			/* translators: %s: alan adı */
			return new WP_Error( 'flxi_required', sprintf( __( '%s is required.', 'flx-mail' ), $f['label'] ) );
		}
		$config[ $k ] = 'password' === $f['type'] ? flxi_encrypt( $v ) : $v;
	}
	$data = array(
		'type'       => $type,
		'channel'    => $def['channel'],
		'name'       => sanitize_text_field( $name ) ? sanitize_text_field( $name ) : $def['label'],
		'config'     => flxi_json( $config ),
		'active'     => $active ? 1 : 0,
		'updated_at' => flxi_now(),
	);
	if ( $id ) {
		$wpdb->update( flxi_table( 'connections' ), $data, array( 'id' => (int) $id ) );
	} else {
		$data['created_at'] = flxi_now();
		$wpdb->insert( flxi_table( 'connections' ), $data );
		$id = (int) $wpdb->insert_id;
	}
	$has_default = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxi_table( 'connections' ) . ' WHERE channel = %s AND is_default = 1 AND id <> %d', $def['channel'], $id ) ); // phpcs:ignore
	if ( $is_default || ( null === $is_default && ! $has_default ) ) {
		flxi_set_default_connection( $id );
	}
	do_action( 'flxi_connection_saved', $id );
	return (int) $id;
}

function flxi_set_default_connection( $id ) {
	global $wpdb;
	$c = flxi_get_connection( $id );
	if ( ! $c ) {
		return;
	}
	$wpdb->update( flxi_table( 'connections' ), array( 'is_default' => 0 ), array( 'channel' => $c['channel'] ) );
	$wpdb->update( flxi_table( 'connections' ), array( 'is_default' => 1 ), array( 'id' => (int) $id ) );
}

function flxi_delete_connection( $id ) {
	global $wpdb;
	$wpdb->delete( flxi_table( 'connections' ), array( 'id' => (int) $id ) );
	do_action( 'flxi_connection_deleted', $id );
}

/**
 * WhatsApp / SMS mesajı gönderir (hemen, kuyruksuz).
 *
 * @param string $channel whatsapp|sms
 * @param string $to      telefon
 * @param string $text    mesaj
 * @param array  $args    connection_id, source, template (kayıt adı), wa_template (Cloud API şablonu: name, lang, params), campaign_id, media (url), file_name, log (bool)
 * @return array ok, external_id, error, retry, response, connection_id
 */
function flxi_send_message( $channel, $to, $text, array $args = array() ) {
	if ( empty( $args['ignore_blocklist'] ) && flxi_contact_blocked( $to, '', $channel ) ) {
		$r = array( 'ok' => false, 'blocked' => true, 'error' => __( 'On the blocklist: not sent', 'flx-mail' ), 'retry' => false );
		if ( ! isset( $args['log'] ) || $args['log'] ) {
			flxi_log_message( $channel, $to, $text, false, $r['error'], array( 'source' => $args['source'] ?? '', 'template' => $args['template'] ?? '', 'campaign_id' => $args['campaign_id'] ?? 0, 'status' => 'blocked' ) );
		}
		return $r;
	}
	$conn = flxi_resolve_connection( $channel, (int) ( $args['connection_id'] ?? 0 ) );
	if ( ! $conn ) {
		/* translators: %s: kanal adı */
		$r = array( 'ok' => false, 'error' => sprintf( __( 'There is no active connection for %s. Add one in Communication › Connections.', 'flx-mail' ), flxi_channels()[ $channel ] ?? $channel ), 'retry' => false );
		return $r;
	}
	$type = flxi_connection_types()[ $conn['type'] ] ?? null;
	if ( ! $type ) {
		return array( 'ok' => false, 'error' => 'Unknown type: ' . $conn['type'], 'retry' => false );
	}
	$msg = array(
		'recipient' => $to,
		'body'      => (string) $text,
		'media'     => (string) ( $args['media'] ?? '' ),
		'file_name' => (string) ( $args['file_name'] ?? '' ),
		'template'  => is_array( $args['wa_template'] ?? null ) ? $args['wa_template'] : array(), // Cloud API şablonu: name, lang, params
	);
	try {
		$r = call_user_func( $type['send'], $conn['config'], $msg, $conn );
	} catch ( Throwable $e ) {
		$r = array( 'ok' => false, 'error' => $e->getMessage(), 'retry' => true );
	}
	$r                  = wp_parse_args( $r, array( 'ok' => false, 'external_id' => '', 'response' => null, 'error' => '', 'retry' => true ) );
	$r['connection_id'] = $conn['id'];
	if ( ! isset( $args['log'] ) || $args['log'] ) {
		flxi_log_message(
			$channel,
			$to,
			$text,
			$r['ok'],
			$r['error'],
			array(
				'source'      => $args['source'] ?? '',
				'template'    => $args['template'] ?? '',
				'campaign_id' => $args['campaign_id'] ?? 0,
				'mailer'      => $conn['type'],
			)
		);
	}
	do_action( 'flxi_message_sent', $channel, $to, $text, $r, $args );
	return $r;
}

/** Bağlantıyı dener ($to verilirse test mesajı gönderir). */
function flxi_test_connection( $id, $to = '' ) {
	$conn = flxi_get_connection( $id );
	if ( ! $conn ) {
		return array( 'ok' => false, 'error' => __( 'Connection not found.', 'flx-mail' ) );
	}
	$type = flxi_connection_types()[ $conn['type'] ] ?? null;
	if ( ! $type || empty( $type['test'] ) ) {
		return array( 'ok' => false, 'error' => __( 'This type cannot be tested.', 'flx-mail' ) );
	}
	try {
		$r = call_user_func( $type['test'], $conn['config'], $to, $conn );
	} catch ( Throwable $e ) {
		$r = array( 'ok' => false, 'error' => $e->getMessage() );
	}
	$r = wp_parse_args( $r, array( 'ok' => false, 'error' => '' ) );
	$tests        = (array) get_option( 'flxi_conn_tests', array() );
	$tests[ $id ] = array( 'ok' => (bool) $r['ok'], 'error' => mb_substr( (string) $r['error'], 0, 300 ), 'at' => time() );
	update_option( 'flxi_conn_tests', $tests, false );
	return $r;
}

/** HTTP isteği; yanıtı kayda uygun biçimde döner. */
function flxi_http( $method, $url, $args = array() ) {
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
