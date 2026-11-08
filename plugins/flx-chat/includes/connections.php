<?php
defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp bağlantıları (Evolution API instance'ları). Bir bağlantının kimlik bilgisi üç yerden gelir:
 * - source=flxi: Flux Mail'deki WhatsApp bağlantısı (önerilen; anahtar tek yerde güncellenir),
 * - source=flxs: Flux Certificates'daki Evolution sağlayıcısı (eski yol; Flux Mail yokken),
 * - source=own: bu eklentide girilen adres/key/instance.
 * flxi bağlantısı Flux Certificates'dan taşındıysa eski sağlayıcı id'si flxs_id'de tutulur; Flux Mail
 * kapatılırsa bağlantı oradan çalışmaya devam eder.
 */

function flxc_new_connection( $args = array() ) {
	return wp_parse_args(
		$args,
		array(
			'id'          => 'c' . strtolower( wp_generate_password( 8, false, false ) ),
			'label'       => 'WhatsApp',
			'source'      => 'own',
			'provider_id' => 0,
			'flxs_id'      => 0,
			'base_url'    => '',
			'api_key'     => '',
			'instance'    => '',
			'secret'      => wp_generate_password( 32, false, false ),
			'color'       => '',
		)
	);
}

function flxc_connections() {
	$list = (array) flxc_setting( 'connections' );
	$out  = array();
	foreach ( $list as $c ) {
		if ( is_array( $c ) && ! empty( $c['id'] ) ) {
			$out[ $c['id'] ] = flxc_new_connection( $c );
		}
	}
	return $out;
}

function flxc_get_connection( $id ) {
	$all = flxc_connections();
	return $all[ $id ] ?? null;
}

function flxc_default_connection() {
	$all = flxc_connections();
	return $all ? reset( $all ) : null;
}

/**
 * 1.34.0: Evolution v2 uçlarını konuşan bağlantı türleri. Flux Mail'de Flux WPAPI ayrı sağlayıcı olarak
 * eklenince (type 'flxwpapi', Flux Mail 1.9.0) Çağrı Merkezi onu da Evolution gibi kullanır. Başka uyumlu tür: flxc_evo_compatible_types.
 */
function flxc_evo_compatible_type( $type ) {
	if ( function_exists( 'flxi_is_evo_type' ) && flxi_is_evo_type( (string) $type ) ) {
		return true;
	}
	return in_array( (string) $type, (array) apply_filters( 'flxc_evo_compatible_types', array( 'evolution', 'flxwpapi', 'flx-wpapi' ) ), true );
}

/**
 * 1.37.0: Sanal kanal mı (ör. Flux AI Chatbot'un site sohbeti, `flx-web`). Bu bağlantılarda Evolution'a istek gitmez;
 * flxc_evo() isteği `flxc_virtual_request` filtresine verir, kanalı sağlayan eklenti cevaplar.
 *
 * @param string|array $conn Bağlantı id'si ya da bağlantı.
 */
function flxc_is_virtual_conn( $conn ) {
	$id = is_array( $conn ) ? (string) ( $conn['id'] ?? '' ) : (string) $conn;
	return '' !== $id && (bool) apply_filters( 'flxc_virtual_conn', false, $id );
}

/**
 * 1.37.0: Gelen kutusu kanalları. Çağrı merkezi tek gelen kutusudur; WhatsApp dışındaki kanallar (site sohbeti, ileride
 * Instagram, e-posta…) `flxc_channels` filtresiyle kendini kaydeder ve sohbetler listede kanal ikonuyla görünür.
 * Her kanal: label, icon (24'lük viewBox içi SVG; stroke çizgi), color, active (kanal açık mı), conns (sanal bağlantı id'leri).
 *
 * @return array<string,array>
 */
function flxc_channels() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$wa = 0;
	foreach ( flxc_connections() as $c ) {
		if ( ! flxc_is_virtual_conn( $c ) ) {
			++$wa;
		}
	}
	$list = array(
		'whatsapp' => array(
			'label'  => __( 'WhatsApp', 'flx-chat' ),
			'icon'   => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.4z"/><path d="M9 8.6c0 3.3 3 6.4 6.4 6.4l1.1-1.6-2-1-1 .9a5 5 0 0 1-2.8-2.8l.9-1-1-2z"/>',
			'color'  => '#25d366',
			'active' => $wa > 0,
			'conns'  => array(),
		),
		'web'      => array(
			'label'  => __( 'Site chat', 'flx-chat' ),
			'icon'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
			'color'  => '#5b8def',
			'active' => false,
			'conns'  => array(),
		),
	);
	$list = (array) apply_filters( 'flxc_channels', $list );
	$out  = array();
	foreach ( $list as $id => $ch ) {
		$id = sanitize_key( (string) $id );
		if ( '' === $id || ! is_array( $ch ) ) {
			continue;
		}
		$out[ $id ] = array(
			'label'  => (string) ( $ch['label'] ?? $id ),
			'icon'   => (string) ( $ch['icon'] ?? '' ),
			'color'  => sanitize_hex_color( (string) ( $ch['color'] ?? '' ) ) ? (string) $ch['color'] : '#8696a0',
			'active' => ! empty( $ch['active'] ),
			'conns'  => array_values( array_filter( array_map( 'strval', (array) ( $ch['conns'] ?? array() ) ) ) ),
		);
	}
	$memo = $out;
	return $out;
}

/** Sohbetin kanalı: sanal bağlantıyı kaydeden kanal, yoksa sanal ise 'web', değilse 'whatsapp'. */
function flxc_chat_channel( $chat ) {
	$conn = (string) ( is_array( $chat ) ? ( $chat['conn'] ?? '' ) : $chat );
	if ( ! flxc_is_virtual_conn( $conn ) ) {
		return 'whatsapp';
	}
	foreach ( flxc_channels() as $id => $ch ) {
		if ( in_array( $conn, $ch['conns'], true ) ) {
			return $id;
		}
	}
	return 'web';
}

/** Arayüze giden kanal listesi (ikon dahil). */
function flxc_channels_for_js() {
	$out = array();
	foreach ( flxc_channels() as $id => $ch ) {
		$out[] = array(
			'id'     => $id,
			'label'  => $ch['label'],
			'icon'   => wp_kses(
				$ch['icon'],
				array(
					'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true ),
					'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
					'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true ),
					'polyline' => array( 'points' => true ),
					'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ),
				)
			),
			'color'  => $ch['color'],
			'active' => $ch['active'],
		);
	}
	return $out;
}

/** Flux Mail etkin mi (WhatsApp/SMS bağlantıları orada yönetilir). */
function flxc_flxi_on() {
	return function_exists( 'flxi_get_channel_connection' ) && function_exists( 'flxi_connection_choices' );
}

/** Flux Mail'deki etkin Evolution uyumlu (Evolution API, Flux WPAPI) WhatsApp bağlantıları: id, name, type, default. */
function flxc_flxi_whatsapp_choices() {
	if ( ! flxc_flxi_on() ) {
		return array();
	}
	return array_values(
		array_filter(
			(array) flxi_connection_choices( 'whatsapp' ),
			function ( $c ) {
				return flxc_evo_compatible_type( $c['type'] ?? '' ) || flxc_wacloud_type( $c['type'] ?? '' );
			}
		)
	);
}

/** Flux Certificates sağlayıcısından taşınmış Flux Mail bağlantısının id'si (yoksa 0). */
function flxc_flxi_id_for_flxs( $provider_id ) {
	if ( ! flxc_flxi_on() || ! function_exists( 'flxi_list_connections' ) || ! $provider_id ) {
		return 0;
	}
	foreach ( (array) flxi_list_connections( 'whatsapp', true ) as $c ) {
		if ( 'flxs:' . (int) $provider_id === (string) ( $c['origin'] ?? '' ) && flxc_evo_compatible_type( $c['type'] ) ) {
			return (int) $c['id'];
		}
	}
	return 0;
}

/**
 * Flux Mail kurulunca Flux Certificates'ya bağlı hatlar kendiliğinden taşınmış bağlantıya geçer
 * (eski sağlayıcı id'si geri dönüş için saklanır). Yönetim ekranında bir kez çalışır.
 */
add_action(
	'admin_init',
	function () {
		if ( ! flxc_flxi_on() ) {
			return;
		}
		$s       = flxc_settings();
		$changed = false;
		foreach ( (array) ( $s['connections'] ?? array() ) as $i => $c ) {
			if ( is_array( $c ) && 'flxs' === ( $c['source'] ?? '' ) ) {
				$new = flxc_flxi_id_for_flxs( (int) ( $c['provider_id'] ?? 0 ) );
				if ( $new ) {
					$s['connections'][ $i ]['flxs_id']      = (int) $c['provider_id'];
					$s['connections'][ $i ]['source']      = 'flxi';
					$s['connections'][ $i ]['provider_id'] = $new;
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			update_option( 'flxc_settings', $s, false );
			flxc_settings( true );
		}
	}
);

/** Bağlantının bilgisi nereden geliyor: 'flxi' | 'flxs' | 'own' (Flux Mail kapalıysa flxi → flxs geri dönüşü dahil). */
function flxc_connection_origin( $conn ) {
	if ( 'flxi' === $conn['source'] ) {
		return flxc_flxi_on() || ! (int) $conn['flxs_id'] ? 'flxi' : 'flxs';
	}
	return $conn['source'];
}

/** Flux Certificates'daki aktif Evolution sağlayıcıları. */
function flxc_flxs_evolution_providers() {
	if ( ! function_exists( 'flxs_list_providers' ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) flxs_list_providers( 'whatsapp' ) as $p ) {
		if ( flxc_evo_compatible_type( $p['type'] ?? '' ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Bağlantının çalışan API ayarı: base_url, api_key, instance.
 * @return array|WP_Error
 */
function flxc_connection_config( $conn ) {
	if ( is_string( $conn ) ) {
		$conn = flxc_get_connection( $conn );
	}
	if ( ! $conn ) {
		return new WP_Error( 'flxc_conn', __( 'WhatsApp connection not found. Add one under Settings › Connections.', 'flx-chat' ) );
	}
	$origin = flxc_connection_origin( $conn );
	if ( 'flxi' === $origin ) {
		if ( ! flxc_flxi_on() ) {
			return new WP_Error( 'flxc_flxi', __( 'This connection uses the Flux Mail plugin\'s settings but the plugin is not active.', 'flx-chat' ) );
		}
		$c = flxi_get_channel_connection( 'whatsapp', (int) $conn['provider_id'] );
		// Verilen id kapalıysa Flux Mail varsayılana düşer; başka bir hattan gönderilmesin diye id eşleşmeli.
		$cloud = $c && flxc_wacloud_type( $c['type'] ?? '' );
		if ( ! $c || (int) $c['id'] !== (int) $conn['provider_id'] || ( ! $cloud && ! flxc_evo_compatible_type( $c['type'] ) ) ) {
			return new WP_Error( 'flxc_flxi', __( 'The WhatsApp connection in Flux Mail was not found or is disabled (Communication › Connections).', 'flx-chat' ) );
		}
		if ( $cloud ) {
			// Resmi Cloud API: istekler flxc_wacloud_evo() ile Flux Mail üzerinden gider.
			return array( 'base_url' => '', 'api_key' => '', 'instance' => 'wacloud-' . (int) $c['id'], 'wacloud' => (int) $c['id'] );
		}
		$cfg = (array) $c['config'];
		return array(
			'base_url' => (string) ( $cfg['base_url'] ?? '' ),
			'api_key'  => (string) ( $cfg['api_key'] ?? '' ),
			'instance' => (string) ( $cfg['instance'] ?? '' ),
		);
	}
	if ( 'flxs' === $origin ) {
		if ( ! function_exists( 'flxs_get_provider' ) ) {
			return new WP_Error( 'flxc_flxs', __( 'This connection uses the Flux Certificates plugin\'s settings, but that plugin is not active.', 'flx-chat' ) );
		}
		$p = flxs_get_provider( (int) ( 'flxi' === $conn['source'] ? $conn['flxs_id'] : $conn['provider_id'] ) );
		if ( ! $p || ! flxc_evo_compatible_type( $p['type'] ) ) {
			return new WP_Error( 'flxc_flxs', __( 'The Evolution provider in Flux Certificates was not found.', 'flx-chat' ) );
		}
		$cfg = (array) $p['config'];
		return array(
			'base_url' => (string) ( $cfg['base_url'] ?? '' ),
			'api_key'  => (string) ( $cfg['api_key'] ?? '' ),
			'instance' => (string) ( $cfg['instance'] ?? '' ),
		);
	}
	if ( '' === $conn['base_url'] || '' === $conn['api_key'] || '' === $conn['instance'] ) {
		return new WP_Error( 'flxc_conf', __( 'The connection\'s API URL, API key or instance name is missing.', 'flx-chat' ) );
	}
	return array(
		'base_url' => $conn['base_url'],
		'api_key'  => $conn['api_key'],
		'instance' => $conn['instance'],
	);
}

/** Evolution'ın mesajları göndereceği adres. Gizli anahtar adreste; başkası sahte mesaj gönderemez. */
function flxc_webhook_url( $conn ) {
	if ( is_string( $conn ) ) {
		$conn = flxc_get_connection( $conn );
	}
	return add_query_arg( 't', $conn['secret'], rest_url( 'flx-chat/v1/webhook/' . $conn['id'] ) );
}

function flxc_connection_state( $id ) {
	$all = get_option( 'flxc_conn_state', array() );
	return is_array( $all ) && isset( $all[ $id ] ) ? $all[ $id ] : array( 'state' => '', 'at' => 0 );
}

function flxc_set_connection_state( $id, $state ) {
	$all = get_option( 'flxc_conn_state', array() );
	$all = is_array( $all ) ? $all : array();
	$old = $all[ $id ]['state'] ?? '';
	$all[ $id ] = array( 'state' => (string) $state, 'at' => time() );
	update_option( 'flxc_conn_state', $all, false );
	if ( $old !== $state ) {
		update_option( 'flxc_conn_rev', flxc_rev(), false );
	}
}

/** Arayüze giden bağlantı özeti (gizli bilgiler yok). */
function flxc_connections_for_js() {
	$out = array();
	foreach ( flxc_connections() as $c ) {
		$st    = flxc_connection_state( $c['id'] );
		$out[] = array(
			'id'    => $c['id'],
			'label' => $c['label'],
			'color' => $c['color'],
			'state' => $st['state'],
			'at'    => (int) $st['at'],
		);
	}
	return $out;
}
