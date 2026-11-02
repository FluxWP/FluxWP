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
		return new WP_Error( 'flxc_conn', __( 'WhatsApp bağlantısı bulunamadı. Ayarlar › Bağlantılar bölümünden ekleyin.', 'flx-chat' ) );
	}
	$origin = flxc_connection_origin( $conn );
	if ( 'flxi' === $origin ) {
		if ( ! flxc_flxi_on() ) {
			return new WP_Error( 'flxc_flxi', __( 'Bu bağlantı Flux Mail eklentisinin ayarını kullanıyor ama eklenti etkin değil.', 'flx-chat' ) );
		}
		$c = flxi_get_channel_connection( 'whatsapp', (int) $conn['provider_id'] );
		// Verilen id kapalıysa Flux Mail varsayılana düşer; başka bir hattan gönderilmesin diye id eşleşmeli.
		$cloud = $c && flxc_wacloud_type( $c['type'] ?? '' );
		if ( ! $c || (int) $c['id'] !== (int) $conn['provider_id'] || ( ! $cloud && ! flxc_evo_compatible_type( $c['type'] ) ) ) {
			return new WP_Error( 'flxc_flxi', __( 'Flux Mail\'deki WhatsApp bağlantısı bulunamadı ya da kapalı (İletişim › Bağlantılar).', 'flx-chat' ) );
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
			return new WP_Error( 'flxc_flxs', __( 'Bu bağlantı Flux Certificates eklentisinin ayarını kullanıyor ama eklenti etkin değil.', 'flx-chat' ) );
		}
		$p = flxs_get_provider( (int) ( 'flxi' === $conn['source'] ? $conn['flxs_id'] : $conn['provider_id'] ) );
		if ( ! $p || ! flxc_evo_compatible_type( $p['type'] ) ) {
			return new WP_Error( 'flxc_flxs', __( 'Flux Certificates\'daki Evolution sağlayıcısı bulunamadı.', 'flx-chat' ) );
		}
		$cfg = (array) $p['config'];
		return array(
			'base_url' => (string) ( $cfg['base_url'] ?? '' ),
			'api_key'  => (string) ( $cfg['api_key'] ?? '' ),
			'instance' => (string) ( $cfg['instance'] ?? '' ),
		);
	}
	if ( '' === $conn['base_url'] || '' === $conn['api_key'] || '' === $conn['instance'] ) {
		return new WP_Error( 'flxc_conf', __( 'Bağlantının API adresi, API key veya instance adı eksik.', 'flx-chat' ) );
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
